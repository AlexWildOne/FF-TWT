<?php
namespace RoutesPro\Expenses;

if (!defined('ABSPATH')) exit;

/**
 * Expense payment settlement + signed evidence authority.
 *
 * A settlement is deliberately stored as a private WordPress record instead of
 * duplicating expense rows or introducing release-time DDL. The record captures
 * the exact paid expense snapshot, period, total and signed evidence path.
 */
final class ExpenseSettlementService {
    public const CONTRACT = 'fieldflow-expense-settlement-signature-authority-v1';
    public const POST_TYPE = 'ff_exp_settlement';
    public const MAX_EVIDENCE_SIZE = 15728640; // 15 MB

    public static function registerHooks(): void {
        add_action('init', [self::class, 'registerPostType'], 4);
        add_action('admin_post_fieldflow_expense_settlement_file', [self::class, 'serveEvidence']);
    }

    public static function registerPostType(): void {
        if (post_type_exists(self::POST_TYPE)) return;
        register_post_type(self::POST_TYPE, [
            'label' => 'Fechos de despesas',
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'supports' => ['title', 'author'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function normalizeSettlementFilters(array $input): array {
        $filters = ExpenseStatementService::normalizeFilters($input);
        $hasPeriod = (string)($filters['date_from'] ?? '') !== '' || (string)($filters['date_to'] ?? '') !== '' || (string)($filters['month'] ?? '') !== '';
        if (!$hasPeriod) {
            throw new \RuntimeException('Seleciona um mês ou um intervalo De/Até antes de fechar e assinar as despesas.');
        }
        // Signed receipt authority is always the paid/reimbursed subset of the
        // chosen period. Review filters do not redefine what was actually paid.
        $filters['status'] = 'paid';
        return $filters;
    }

    public static function context(int $userId, array $input, int $clientId = 0, int $projectId = 0): array {
        $filters = self::normalizeSettlementFilters($input);
        $data = ExpenseStatementService::statementData($userId, $filters, $clientId, $projectId);
        $snapshotHash = self::snapshotHash($data);
        $latest = self::latestBySnapshotHash($snapshotHash);
        return [
            'contract' => self::CONTRACT,
            'filters' => $filters,
            'period_label' => (string)($data['period_label'] ?? ''),
            'user_id' => (int)($data['user_id'] ?? 0),
            'user_name' => (string)($data['user_name'] ?? ''),
            'client_id' => (int)($data['client_id'] ?? 0),
            'project_id' => (int)($data['project_id'] ?? 0),
            'count' => (int)($data['count'] ?? 0),
            'days' => (int)($data['days'] ?? 0),
            'total' => (float)($data['total'] ?? 0),
            'can_submit' => (int)($data['count'] ?? 0) > 0,
            'snapshot_hash' => $snapshotHash,
            'latest_evidence' => $latest ? self::publicEvidence($latest, false) : null,
        ];
    }

    public static function submitUpload(int $userId, array $input, array $file, int $clientId = 0, int $projectId = 0): array {
        $filters = self::normalizeSettlementFilters($input);
        $data = ExpenseStatementService::statementData($userId, $filters, $clientId, $projectId);
        self::assertPayableSnapshot($data);
        $stored = self::saveUploadedEvidence($file, $userId, 'signed-statement');
        try {
            return self::recordEvidence($data, 'uploaded_signed_file', $stored, '');
        } catch (\Throwable $e) {
            self::deleteRelativeFile((string)($stored['path'] ?? ''));
            throw $e;
        }
    }

    public static function submitDirectSignature(int $userId, array $input, string $signatureData, int $clientId = 0, int $projectId = 0): array {
        $filters = self::normalizeSettlementFilters($input);
        $data = ExpenseStatementService::statementData($userId, $filters, $clientId, $projectId);
        self::assertPayableSnapshot($data);
        $signature = self::saveSignatureData($signatureData, $userId);
        $pdfStored = [];
        try {
            $absoluteSignature = self::absolutePath((string)$signature['path']);
            $data['signature_image_path'] = $absoluteSignature;
            $data['signature_signed_at'] = current_time('mysql');
            $data['signature_method'] = 'fieldflow_direct';
            $pdf = ExpenseStatementService::buildPdf($data);
            if (strlen($pdf) < 64 || strncmp($pdf, '%PDF-', 5) !== 0) {
                throw new \RuntimeException('Não foi possível gerar a folha assinada.');
            }
            $pdfStored = self::saveBytes($pdf, 'application/pdf', 'pdf', $userId, 'signed-statement');
            $pdfStored['original_name'] = ExpenseStatementService::signedFilename($data);
            return self::recordEvidence($data, 'fieldflow_direct_signature', $pdfStored, (string)$signature['path']);
        } catch (\Throwable $e) {
            self::deleteRelativeFile((string)($signature['path'] ?? ''));
            self::deleteRelativeFile((string)($pdfStored['path'] ?? ''));
            throw $e;
        }
    }

    private static function assertPayableSnapshot(array $data): void {
        if ((int)($data['count'] ?? 0) <= 0) {
            throw new \RuntimeException('Não existem despesas marcadas como Paga / Reembolsada neste período. O pagamento deve ser fechado primeiro no BO.');
        }
        if ((float)($data['total'] ?? 0) <= 0) {
            throw new \RuntimeException('O período pago não tem valor elegível para recibo.');
        }
    }

    private static function recordEvidence(array $data, string $method, array $stored, string $signatureRelativePath): array {
        self::registerPostType();
        $snapshotHash = self::snapshotHash($data);
        $userId = (int)($data['user_id'] ?? 0);
        $title = 'Folha assinada - ' . (string)($data['user_name'] ?? ('Utilizador ' . $userId)) . ' - ' . (string)($data['period_label'] ?? 'Período');
        $postId = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sanitize_text_field($title),
            'post_author' => get_current_user_id(),
        ], true);
        if (is_wp_error($postId) || !$postId) {
            throw new \RuntimeException(is_wp_error($postId) ? $postId->get_error_message() : 'Não foi possível registar o fecho assinado.');
        }
        $expenseIds = array_values(array_map(static fn($item) => (int)($item['id'] ?? 0), (array)($data['items'] ?? [])));
        $meta = [
            '_ff_contract' => self::CONTRACT,
            '_ff_user_id' => $userId,
            '_ff_client_id' => (int)($data['client_id'] ?? 0),
            '_ff_project_id' => (int)($data['project_id'] ?? 0),
            '_ff_date_from' => (string)($data['filters']['date_from'] ?? ''),
            '_ff_date_to' => (string)($data['filters']['date_to'] ?? ''),
            '_ff_month' => (string)($data['filters']['month'] ?? ''),
            '_ff_filters_json' => wp_json_encode((array)($data['filters'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '_ff_snapshot_hash' => $snapshotHash,
            '_ff_expense_ids_json' => wp_json_encode($expenseIds),
            '_ff_expense_count' => (int)($data['count'] ?? 0),
            '_ff_total' => number_format((float)($data['total'] ?? 0), 2, '.', ''),
            '_ff_method' => sanitize_key($method),
            '_ff_evidence_path' => (string)($stored['path'] ?? ''),
            '_ff_evidence_mime' => (string)($stored['mime'] ?? 'application/octet-stream'),
            '_ff_evidence_name' => sanitize_file_name((string)($stored['original_name'] ?? 'folha-assinada')),
            '_ff_evidence_size' => (int)($stored['size'] ?? 0),
            '_ff_signature_path' => (string)$signatureRelativePath,
            '_ff_submitted_at' => current_time('mysql'),
            '_ff_submitted_by' => get_current_user_id(),
            '_ff_document_id' => (string)($data['document_id'] ?? ''),
        ];
        foreach ($meta as $key => $value) update_post_meta((int)$postId, $key, $value);
        if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
            \RoutesPro\Core\AuditLog::record('expense_settlement_signed', 'expense_settlement', (int)$postId, null, null, [
                'user_id' => $userId,
                'count' => (int)($data['count'] ?? 0),
                'total' => (float)($data['total'] ?? 0),
                'method' => $method,
                'snapshot' => substr($snapshotHash, 0, 16),
            ]);
        }
        return self::publicEvidence((int)$postId, false);
    }

    public static function snapshotHash(array $data): string {
        $items = [];
        foreach ((array)($data['items'] ?? []) as $item) {
            $items[] = [
                'id' => (int)($item['id'] ?? 0),
                'date' => (string)($item['expense_date'] ?? ''),
                'amount' => number_format((float)($item['amount_total'] ?? 0), 2, '.', ''),
                'status' => (string)($item['status'] ?? ''),
            ];
        }
        return hash('sha256', wp_json_encode([
            'user_id' => (int)($data['user_id'] ?? 0),
            'client_id' => (int)($data['client_id'] ?? 0),
            'project_id' => (int)($data['project_id'] ?? 0),
            'filters' => (array)($data['filters'] ?? []),
            'items' => $items,
            'total' => number_format((float)($data['total'] ?? 0), 2, '.', ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function latestBySnapshotHash(string $snapshotHash): int {
        $snapshotHash = preg_replace('/[^a-f0-9]/', '', strtolower($snapshotHash));
        if (strlen($snapshotHash) !== 64) return 0;
        self::registerPostType();
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_ff_snapshot_hash',
            'meta_value' => $snapshotHash,
            'suppress_filters' => true,
        ]);
        return $ids ? (int)$ids[0] : 0;
    }

    public static function adminList(array $params = [], int $limit = 50): array {
        self::registerPostType();
        $limit = max(1, min(500, $limit));
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => min(500, max($limit * 3, 60)),
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => true,
        ]);
        $userId = absint($params['user_id'] ?? 0);
        $dateFrom = sanitize_text_field((string)($params['date_from'] ?? ''));
        $dateTo = sanitize_text_field((string)($params['date_to'] ?? ''));
        $month = sanitize_text_field((string)($params['month'] ?? ''));
        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $dateFrom = $month . '-01';
            $dt = \DateTime::createFromFormat('!Y-m-d', $dateFrom);
            if ($dt) { $dt->modify('last day of this month'); $dateTo = $dt->format('Y-m-d'); }
        }
        $out = [];
        foreach ($posts as $post) {
            $id = (int)$post->ID;
            $rowUser = (int)get_post_meta($id, '_ff_user_id', true);
            if ($userId > 0 && $rowUser !== $userId) continue;
            $rowFrom = (string)get_post_meta($id, '_ff_date_from', true);
            $rowTo = (string)get_post_meta($id, '_ff_date_to', true);
            if ($dateFrom !== '' && $rowTo !== '' && $rowTo < $dateFrom) continue;
            if ($dateTo !== '' && $rowFrom !== '' && $rowFrom > $dateTo) continue;
            $out[] = self::publicEvidence($id, true);
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    public static function publicEvidence(int $postId, bool $includeUrl = false): array {
        $post = get_post($postId);
        if (!$post || $post->post_type !== self::POST_TYPE) return [];
        $userId = (int)get_post_meta($postId, '_ff_user_id', true);
        $user = get_userdata($userId);
        $method = (string)get_post_meta($postId, '_ff_method', true);
        $row = [
            'id' => $postId,
            'user_id' => $userId,
            'user_name' => $user ? (string)$user->display_name : ('Utilizador #' . $userId),
            'client_id' => (int)get_post_meta($postId, '_ff_client_id', true),
            'project_id' => (int)get_post_meta($postId, '_ff_project_id', true),
            'date_from' => (string)get_post_meta($postId, '_ff_date_from', true),
            'date_to' => (string)get_post_meta($postId, '_ff_date_to', true),
            'month' => (string)get_post_meta($postId, '_ff_month', true),
            'count' => (int)get_post_meta($postId, '_ff_expense_count', true),
            'total' => (float)get_post_meta($postId, '_ff_total', true),
            'method' => $method,
            'method_label' => $method === 'fieldflow_direct_signature' ? 'Assinatura no FieldFlow' : 'Ficheiro assinado enviado',
            'submitted_at' => (string)get_post_meta($postId, '_ff_submitted_at', true),
            'submitted_by' => (int)get_post_meta($postId, '_ff_submitted_by', true),
            'document_id' => (string)get_post_meta($postId, '_ff_document_id', true),
            'evidence_name' => (string)get_post_meta($postId, '_ff_evidence_name', true),
        ];
        if ($includeUrl && self::canRead($postId, get_current_user_id())) {
            $row['evidence_url'] = self::evidenceUrl($postId, false);
            $row['download_url'] = self::evidenceUrl($postId, true);
        }
        return $row;
    }

    public static function canRead(int $postId, int $viewerId): bool {
        if ($viewerId <= 0) return false;
        if (current_user_can('routespro_manage')) return true;
        return (int)get_post_meta($postId, '_ff_user_id', true) === $viewerId;
    }

    public static function evidenceUrl(int $postId, bool $download = false): string {
        return add_query_arg([
            'action' => 'fieldflow_expense_settlement_file',
            'id' => $postId,
            'download' => $download ? 1 : 0,
            '_wpnonce' => wp_create_nonce('fieldflow_expense_settlement_file_' . $postId),
        ], admin_url('admin-post.php'));
    }

    public static function serveEvidence(): void {
        if (!is_user_logged_in()) auth_redirect();
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('fieldflow_expense_settlement_file_' . $id);
        if (!$id || !self::canRead($id, get_current_user_id())) wp_die('Sem permissões.', 403);
        $relative = (string)get_post_meta($id, '_ff_evidence_path', true);
        $path = self::absolutePath($relative);
        if ($path === '' || !is_readable($path)) wp_die('Folha assinada não encontrada.', 404);
        $mime = (string)get_post_meta($id, '_ff_evidence_mime', true);
        if ($mime === '') $mime = 'application/octet-stream';
        $name = sanitize_file_name((string)get_post_meta($id, '_ff_evidence_name', true));
        if ($name === '') $name = 'fieldflow-folha-assinada';
        $disposition = !empty($_GET['download']) ? 'attachment' : 'inline';
        while (ob_get_level() > 0) @ob_end_clean();
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $name) . '"');
        header('X-Content-Type-Options: nosniff');
        header('X-FieldFlow-Expense-Settlement: ' . self::CONTRACT);
        readfile($path);
        exit;
    }

    public static function absolutePath(string $relative): string {
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');
        if ($relative === '' || strpos($relative, 'fieldflow-private-expense-settlements/') !== 0) return '';
        $uploads = wp_upload_dir();
        if (!is_array($uploads) || empty($uploads['basedir'])) return '';
        $base = realpath((string)$uploads['basedir']);
        $path = (string)$uploads['basedir'] . '/' . $relative;
        $real = is_file($path) ? realpath($path) : false;
        if (!$base || !$real || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) return '';
        return $real;
    }

    private static function saveUploadedEvidence(array $file, int $userId, string $prefix): array {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Seleciona o ficheiro assinado antes de submeter.');
        if (!empty($file['error'])) throw new \RuntimeException('O upload da folha assinada falhou.');
        $size = (int)($file['size'] ?? 0);
        $runtimeMax = function_exists('wp_max_upload_size') ? (int)wp_max_upload_size() : 0;
        $max = $runtimeMax > 0 ? min(self::MAX_EVIDENCE_SIZE, $runtimeMax) : self::MAX_EVIDENCE_SIZE;
        if ($size <= 0 || $size > $max) throw new \RuntimeException('O ficheiro assinado excede o limite permitido.');
        $mime = self::detectMime((string)$file['tmp_name']);
        $map = ['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if (!isset($map[$mime])) throw new \RuntimeException('Usa um ficheiro PDF, JPG ou PNG para a folha assinada.');
        $raw = @file_get_contents((string)$file['tmp_name']);
        if ($raw === false || $raw === '') throw new \RuntimeException('Não foi possível ler a folha assinada enviada.');
        $stored = self::saveBytes($raw, $mime, $map[$mime], $userId, $prefix);
        $name = sanitize_file_name((string)($file['name'] ?? 'folha-assinada.' . $map[$mime]));
        if ($name === '') $name = 'folha-assinada.' . $map[$mime];
        $stored['original_name'] = $name;
        return $stored;
    }

    private static function saveSignatureData(string $dataUrl, int $userId): array {
        if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=\r\n]+)$#', trim($dataUrl), $m)) {
            throw new \RuntimeException('A assinatura recebida não é válida. Volta a assinar no campo indicado.');
        }
        $raw = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
        if ($raw === false || strlen($raw) < 200 || strlen($raw) > 2097152) throw new \RuntimeException('A assinatura recebida está vazia ou é demasiado grande.');
        $info = @getimagesizefromstring($raw);
        if (!$info || strtolower((string)($info['mime'] ?? '')) !== 'image/jpeg') throw new \RuntimeException('A assinatura não pôde ser validada como imagem.');
        $stored = self::saveBytes($raw, 'image/jpeg', 'jpg', $userId, 'signature');
        $stored['original_name'] = 'assinatura-fieldflow.jpg';
        return $stored;
    }

    private static function saveBytes(string $bytes, string $mime, string $ext, int $userId, string $prefix): array {
        $uploads = wp_upload_dir();
        if (!is_array($uploads) || !empty($uploads['error']) || empty($uploads['basedir'])) throw new \RuntimeException('O arquivo privado não está disponível.');
        $base = trailingslashit((string)$uploads['basedir']) . 'fieldflow-private-expense-settlements';
        $ym = gmdate('Y/m');
        $dir = trailingslashit($base) . $ym;
        if (!wp_mkdir_p($dir)) throw new \RuntimeException('Não foi possível preparar o arquivo privado de assinaturas.');
        self::protectDirectory($base);
        self::protectDirectory($dir);
        try { $token = bin2hex(random_bytes(18)); } catch (\Throwable $e) { $token = substr(hash('sha256', wp_generate_uuid4() . microtime(true)), 0, 36); }
        $filename = sanitize_file_name($prefix . '-' . $userId . '-' . $token . '.' . $ext);
        $absolute = trailingslashit($dir) . $filename;
        $written = @file_put_contents($absolute, $bytes, LOCK_EX);
        if ($written === false || (int)$written !== strlen($bytes)) { @unlink($absolute); throw new \RuntimeException('Não foi possível guardar o documento assinado.'); }
        @chmod($absolute, 0600);
        $relative = 'fieldflow-private-expense-settlements/' . $ym . '/' . $filename;
        return ['path'=>$relative,'mime'=>$mime,'size'=>(int)$written,'original_name'=>$filename];
    }

    private static function protectDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        $index = trailingslashit($dir) . 'index.php';
        if (!is_file($index)) @file_put_contents($index, "<?php\nhttp_response_code(404);\nexit;\n");
        $ht = trailingslashit($dir) . '.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Options -Indexes\n<FilesMatch \".*\">\nRequire all denied\n</FilesMatch>\n");
    }

    private static function detectMime(string $path): string {
        if (class_exists('finfo')) {
            $f = new \finfo(FILEINFO_MIME_TYPE);
            $mime = strtolower((string)$f->file($path));
            if ($mime !== '') return $mime;
        }
        $type = function_exists('mime_content_type') ? strtolower((string)@mime_content_type($path)) : '';
        return $type;
    }

    private static function deleteRelativeFile(string $relative): void {
        $path = self::absolutePath($relative);
        if ($path !== '' && is_file($path)) @unlink($path);
    }
}
