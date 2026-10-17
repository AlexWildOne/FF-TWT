<?php
namespace RoutesPro\Expenses;

if (!defined('ABSPATH')) exit;

class ExpensesController {
    public function register_routes(): void {
        register_rest_route('routespro/v1', '/expenses', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'index'],
                'permission_callback' => [$this, 'canUse'],
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create'],
                'permission_callback' => [$this, 'canUse'],
            ],
        ]);
        register_rest_route('routespro/v1', '/expenses/(?P<id>\d+)', [
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update'],
                'permission_callback' => function(){ return current_user_can('routespro_manage'); },
            ],
        ]);
        register_rest_route('routespro/v1', '/expenses/statement-context', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'statementContext'],
                'permission_callback' => [$this, 'canUse'],
            ],
        ]);
        register_rest_route('routespro/v1', '/expenses/statement-evidence', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submitStatementEvidence'],
                'permission_callback' => [$this, 'canUse'],
            ],
        ]);
    }

    public function canUse(): bool {
        return is_user_logged_in() && \RoutesPro\Support\Permissions::can_access_front();
    }

    public function index(\WP_REST_Request $request) {
        global $wpdb;
        $table = ExpenseService::table();
        $isManager = current_user_can('routespro_manage');
        $currentUser = get_current_user_id();
        $userId = $isManager && absint($request->get_param('user_id')) ? absint($request->get_param('user_id')) : $currentUser;
        $page = max(1, absint($request->get_param('page')) ?: 1);
        $perPage = max(5, min(50, absint($request->get_param('per_page')) ?: 10));

        [$where, $args] = self::buildWhere($request->get_params(), $userId, $isManager);
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $countSql = "SELECT COUNT(*) FROM {$table}{$whereSql}";
        $sumSql = "SELECT COALESCE(SUM(amount_total),0) FROM {$table}{$whereSql}";
        $total = (int)($args ? $wpdb->get_var($wpdb->prepare($countSql, ...$args)) : $wpdb->get_var($countSql));
        $sum = (float)($args ? $wpdb->get_var($wpdb->prepare($sumSql, ...$args)) : $wpdb->get_var($sumSql));

        $offset = ($page - 1) * $perPage;
        $listSql = "SELECT * FROM {$table}{$whereSql} ORDER BY expense_date DESC, id DESC LIMIT %d OFFSET %d";
        $listArgs = array_merge($args, [$perPage, $offset]);
        $rows = $wpdb->get_results($wpdb->prepare($listSql, ...$listArgs), ARRAY_A) ?: [];

        return rest_ensure_response([
            'items' => array_map([ExpenseService::class, 'publicRow'], $rows),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => max(1, (int)ceil($total / $perPage)),
            ],
            'summary' => [
                'count' => $total,
                'total' => round($sum, 2),
            ],
        ]);
    }

    public function create(\WP_REST_Request $request) {
        $receiptPath = '';
        $insertedId = 0;
        try {
            return $this->createInternal($request, $receiptPath, $insertedId);
        } catch (\Throwable $e) {
            $reference = self::logRuntimeError('create', $e);
            if ($insertedId > 0) {
                return new \WP_REST_Response([
                    'ok' => true,
                    'message' => 'Despesa guardada com sucesso. A confirmação detalhada encontrou um erro interno, mas o registo ficou concluído.',
                    'item' => null,
                    'reference' => $reference,
                ], 201);
            }
            if ($receiptPath !== '') {
                try {
                    $path = ExpenseService::absoluteReceiptPath($receiptPath);
                    if (is_file($path)) @unlink($path);
                } catch (\Throwable $cleanupError) {
                    self::logRuntimeError('create_cleanup', $cleanupError);
                }
            }
            return new \WP_Error(
                'fieldflow_expense_internal',
                'O servidor encontrou um erro interno ao guardar a despesa. Referência ' . $reference . '. Tenta novamente. Se persistir, envia esta referência ao administrador.',
                ['status' => 500, 'reference' => $reference]
            );
        }
    }

    private function createInternal(\WP_REST_Request $request, &$receiptPath, &$insertedId) {

        global $wpdb;
        $userId = get_current_user_id();
        $params = $request->get_params();
        $files = $request->get_file_params();
        $qrPayload = trim((string)($params['qr_payload'] ?? ''));
        $qr = ExpenseService::parseQr($qrPayload);
        $qrFields = is_array($qr['fields'] ?? null) ? $qr['fields'] : [];
        $qrCoreValid = !empty($qrFields['A']) && preg_match('/^\d{9}$/', (string)$qrFields['A'])
            && !empty($qrFields['D']) && !empty($qrFields['F']) && !empty($qrFields['G']) && !empty($qrFields['O'])
            && !empty($qr['document_date']) && (float)$qr['gross_total'] > 0;
        $qrHasOfficialTotal = (bool)$qrCoreValid;

        $amountTotal = $qrHasOfficialTotal ? (float)$qr['gross_total'] : ExpenseService::money($params['amount_total'] ?? 0);
        if ($amountTotal <= 0) {
            return new \WP_Error('fieldflow_expense_amount', 'Indica o valor total da despesa.', ['status' => 400]);
        }
        if (empty($files['receipt'])) {
            $contentLength = max(0, (int)($_SERVER['CONTENT_LENGTH'] ?? 0));
            $runtimeMax = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
            if ($runtimeMax > 0 && $contentLength > $runtimeMax) {
                return new \WP_Error('fieldflow_expense_request_too_large', 'A fotografia ultrapassa o limite de upload deste servidor. O FieldFlow irá reduzi-la antes do próximo envio.', ['status' => 413]);
            }
            return new \WP_Error('fieldflow_expense_receipt', 'É obrigatória uma fotografia do comprovativo.', ['status' => 400]);
        }
        $receipt = ExpenseService::savePrivateReceipt($files['receipt'], $userId);
        if (empty($receipt['ok'])) {
            return new \WP_Error('fieldflow_expense_upload', (string)($receipt['message'] ?? 'Falha ao guardar comprovativo.'), ['status' => 400]);
        }
        $receiptPath = (string)($receipt['path'] ?? '');

        $category = sanitize_key((string)($params['category'] ?? 'outros'));
        if (!isset(ExpenseService::categories()[$category])) $category = 'outros';
        $payment = sanitize_key((string)($params['payment_method'] ?? 'other'));
        if (!isset(ExpenseService::paymentMethods()[$payment])) $payment = 'other';
        $expenseDate = !empty($qr['document_date']) ? $qr['document_date'] : ExpenseService::normalizeDate((string)($params['expense_date'] ?? ''));
        $taxAmount = !empty($qr['tax_total']) ? (float)$qr['tax_total'] : ExpenseService::money($params['tax_amount'] ?? 0);
        $netAmount = ExpenseService::money($params['amount_net'] ?? 0);
        if ($netAmount <= 0 && $amountTotal >= $taxAmount) $netAmount = round($amountTotal - $taxAmount, 2);

        $clientSource = sanitize_key((string)($params['capture_source'] ?? 'manual'));
        if (!in_array($clientSource, ['manual', 'photo_ocr'], true)) $clientSource = 'manual';

        $data = [
            'user_id' => $userId,
            'expense_date' => $expenseDate,
            'category' => $category,
            'merchant_name' => sanitize_text_field((string)($params['merchant_name'] ?? '')),
            'issuer_tax_id' => !empty($qr['issuer_tax_id']) ? $qr['issuer_tax_id'] : sanitize_text_field((string)($params['issuer_tax_id'] ?? '')),
            'document_no' => !empty($qr['document_no']) ? $qr['document_no'] : sanitize_text_field((string)($params['document_no'] ?? '')),
            'atcud' => !empty($qr['atcud']) ? $qr['atcud'] : sanitize_text_field((string)($params['atcud'] ?? '')),
            'description' => sanitize_textarea_field((string)($params['description'] ?? '')),
            'payment_method' => $payment,
            'amount_net' => $netAmount,
            'tax_amount' => $taxAmount,
            'amount_total' => $amountTotal,
            'currency' => 'EUR',
            'status' => 'pending',
            'capture_source' => $qrHasOfficialTotal ? 'qr_at' : $clientSource,
            'verification_state' => $qrHasOfficialTotal ? ($qr['valid'] ? 'qr_complete' : 'qr_partial') : 'manual',
            'qr_payload' => $qrPayload,
            'qr_fields_json' => !empty($qr['fields']) ? wp_json_encode($qr['fields'], JSON_UNESCAPED_UNICODE) : null,
            'receipt_path' => $receipt['path'],
            'receipt_mime' => $receipt['mime'],
            'receipt_original_name' => $receipt['original_name'],
            'receipt_size' => $receipt['size'],
            'duplicate_hash' => '',
            'duplicate_of_id' => null,
            'meta_json' => wp_json_encode([
                'qr_errors' => $qr['errors'] ?? [],
                'client_ocr_used' => !empty($params['client_ocr_used']),
                'submitted_user_agent' => substr(sanitize_text_field((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 300),
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        $data['duplicate_hash'] = ExpenseService::duplicateHash($userId, $data);
        $duplicateId = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . ExpenseService::table() . " WHERE duplicate_hash=%s ORDER BY id DESC LIMIT 1",
            $data['duplicate_hash']
        ));
        if ($duplicateId) $data['duplicate_of_id'] = $duplicateId;

        $inserted = $wpdb->insert(ExpenseService::table(), $data);
        if (!$inserted) {
            $path = ExpenseService::absoluteReceiptPath((string)$receipt['path']);
            if (is_file($path)) @unlink($path);
            return new \WP_Error('fieldflow_expense_db', 'Não foi possível guardar a despesa.', ['status' => 500]);
        }
        $insertedId = (int)$wpdb->insert_id;
        $item = null;
        try {
            $row = ExpenseService::get($insertedId);
            if ($row) $item = ExpenseService::publicRow($row);
        } catch (\Throwable $responseError) {
            self::logRuntimeError('create_response', $responseError);
        }
        return new \WP_REST_Response([
            'ok' => true,
            'message' => $duplicateId ? 'Despesa guardada. Foi detetado um possível registo duplicado.' : 'Despesa guardada com sucesso.',
            'item' => $item,
        ], 201);
    }

    private static function logRuntimeError(string $scope, \Throwable $e): string {
        $reference = 'EXP-' . strtoupper(substr(hash('sha256', $scope . '|' . get_class($e) . '|' . $e->getMessage() . '|' . $e->getFile() . '|' . $e->getLine()), 0, 10));
        if (function_exists('error_log')) {
            @error_log(sprintf(
                '[FieldFlow Expenses][%s][%s] %s: %s in %s:%d',
                $scope,
                $reference,
                get_class($e),
                $e->getMessage(),
                basename((string)$e->getFile()),
                (int)$e->getLine()
            ));
        }
        return $reference;
    }

    public function update(\WP_REST_Request $request) {
        global $wpdb;
        $id = absint($request['id']);
        $row = ExpenseService::get($id);
        if (!$row) return new \WP_Error('not_found', 'Despesa não encontrada.', ['status' => 404]);
        $status = sanitize_key((string)$request->get_param('status'));
        if (!isset(ExpenseService::statuses()[$status])) {
            return new \WP_Error('invalid_status', 'Estado inválido.', ['status' => 400]);
        }
        ExpenseService::setStatus($id, $status, get_current_user_id(), 'rest_manager');
        return rest_ensure_response(['ok' => true, 'item' => ExpenseService::publicRow(ExpenseService::get($id))]);
    }

    public function statementContext(\WP_REST_Request $request) {
        try {
            $userId = get_current_user_id();
            $params = $request->get_params();
            $clientId = absint($request->get_param('client_id'));
            $projectId = absint($request->get_param('project_id'));
            return rest_ensure_response(['ok'=>true,'context'=>ExpenseSettlementService::context($userId, $params, $clientId, $projectId)]);
        } catch (\Throwable $e) {
            return new \WP_Error('fieldflow_expense_settlement_context', $e->getMessage(), ['status'=>400]);
        }
    }

    public function submitStatementEvidence(\WP_REST_Request $request) {
        try {
            $userId = get_current_user_id();
            $params = $request->get_params();
            $clientId = absint($request->get_param('client_id'));
            $projectId = absint($request->get_param('project_id'));
            $mode = sanitize_key((string)$request->get_param('mode'));
            if (empty($params['declaration_confirmed'])) {
                return new \WP_Error('fieldflow_expense_settlement_declaration', 'Confirma a declaração de recebimento antes de submeter.', ['status'=>400]);
            }
            if ($mode === 'upload') {
                $files = $request->get_file_params();
                if (empty($files['signed_statement'])) return new \WP_Error('fieldflow_expense_settlement_file', 'Seleciona a folha assinada em PDF, JPG ou PNG.', ['status'=>400]);
                $evidence = ExpenseSettlementService::submitUpload($userId, $params, $files['signed_statement'], $clientId, $projectId);
            } elseif ($mode === 'signature') {
                $signatureData = (string)$request->get_param('signature_data');
                $evidence = ExpenseSettlementService::submitDirectSignature($userId, $params, $signatureData, $clientId, $projectId);
            } else {
                return new \WP_Error('fieldflow_expense_settlement_mode', 'Escolhe enviar uma folha assinada ou assinar diretamente no FieldFlow.', ['status'=>400]);
            }
            return new \WP_REST_Response(['ok'=>true,'message'=>'Comprovativo de recebimento guardado com sucesso.','evidence'=>$evidence], 201);
        } catch (\Throwable $e) {
            return new \WP_Error('fieldflow_expense_settlement_submit', $e->getMessage(), ['status'=>400]);
        }
    }

    public static function buildWhere(array $params, int $userId = 0, bool $isManager = false): array {
        global $wpdb;
        $where = [];
        $args = [];
        if ($userId > 0) {
            $where[] = 'user_id=%d';
            $args[] = $userId;
        } elseif (!$isManager) {
            $where[] = 'user_id=%d';
            $args[] = get_current_user_id();
        }
        $dateFrom = sanitize_text_field((string)($params['date_from'] ?? ''));
        $dateTo = sanitize_text_field((string)($params['date_to'] ?? ''));
        $month = sanitize_text_field((string)($params['month'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $where[] = "DATE_FORMAT(expense_date, '%%Y-%%m')=%s";
            $args[] = $month;
        } else {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'expense_date >= %s'; $args[] = $dateFrom; }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'expense_date <= %s'; $args[] = $dateTo; }
        }
        $category = sanitize_key((string)($params['category'] ?? ''));
        if ($category && isset(ExpenseService::categories()[$category])) { $where[] = 'category=%s'; $args[] = $category; }
        $status = sanitize_key((string)($params['status'] ?? ''));
        if ($status && isset(ExpenseService::statuses()[$status])) { $where[] = 'status=%s'; $args[] = $status; }
        $search = sanitize_text_field((string)($params['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(merchant_name LIKE %s OR document_no LIKE %s OR issuer_tax_id LIKE %s OR description LIKE %s)';
            array_push($args, $like, $like, $like, $like);
        }
        return [$where, $args];
    }
}
