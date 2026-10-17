<?php
namespace RoutesPro\Expenses;

if (!defined('ABSPATH')) exit;

class ExpenseService {
    public const TABLE = 'expenses';
    public const MAX_FILE_SIZE = 12582912; // 12 MB

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_' . self::TABLE;
    }

    public static function categories(): array {
        return [
            'combustivel' => 'Combustível',
            'portagens' => 'Portagens',
            'estacionamento' => 'Estacionamento',
            'refeicao' => 'Refeição',
            'alojamento' => 'Alojamento',
            'transportes' => 'Transportes',
            'materiais' => 'Materiais',
            'correio' => 'Correio e envios',
            'outros' => 'Outros',
        ];
    }

    public static function statuses(): array {
        return [
            'pending' => 'Pendente',
            'approved' => 'Aprovada',
            'paid' => 'Paga / Reembolsada',
            'rejected' => 'Rejeitada',
        ];
    }

    public static function paymentMethods(): array {
        return [
            'company_card' => 'Cartão da empresa',
            'personal_card' => 'Cartão pessoal',
            'cash' => 'Numerário',
            'transfer' => 'Transferência',
            'other' => 'Outro',
        ];
    }

    public static function parseQr(string $payload): array {
        $payload = trim(wp_unslash($payload));
        if ($payload === '') {
            return ['valid' => false, 'fields' => [], 'errors' => ['Código QR vazio.']];
        }
        if (strlen($payload) > 4096) {
            return ['valid' => false, 'fields' => [], 'errors' => ['Código QR demasiado extenso.']];
        }

        $fields = [];
        foreach (explode('*', $payload) as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part, ':') === false) continue;
            [$key, $value] = explode(':', $part, 2);
            $key = strtoupper(trim($key));
            if (!preg_match('/^[A-Z][A-Z0-9]{0,2}$/', $key)) continue;
            $fields[$key] = trim($value);
        }

        $required = ['A','B','C','D','E','F','G','H','N','O','Q','R'];
        $errors = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $fields) || $fields[$key] === '') {
                $errors[] = 'Campo obrigatório em falta no QR: ' . $key . '.';
            }
        }
        if (!empty($fields['A']) && !preg_match('/^\d{9}$/', $fields['A'])) {
            $errors[] = 'NIF do emitente inválido no QR.';
        }
        if (!empty($fields['F']) && !preg_match('/^\d{8}$/', $fields['F'])) {
            $errors[] = 'Data inválida no QR.';
        }
        if (isset($fields['O']) && !is_numeric(str_replace(',', '.', $fields['O']))) {
            $errors[] = 'Total inválido no QR.';
        }

        $date = '';
        if (!empty($fields['F']) && preg_match('/^(\d{4})(\d{2})(\d{2})$/', $fields['F'], $m)) {
            if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                $date = $m[1] . '-' . $m[2] . '-' . $m[3];
            }
        }

        return [
            'valid' => empty($errors),
            'fields' => $fields,
            'errors' => $errors,
            'issuer_tax_id' => sanitize_text_field($fields['A'] ?? ''),
            'buyer_tax_id' => sanitize_text_field($fields['B'] ?? ''),
            'document_type' => sanitize_text_field($fields['D'] ?? ''),
            'document_date' => $date,
            'document_no' => sanitize_text_field($fields['G'] ?? ''),
            'atcud' => sanitize_text_field($fields['H'] ?? ''),
            'tax_total' => self::money($fields['N'] ?? 0),
            'gross_total' => self::money($fields['O'] ?? 0),
        ];
    }

    public static function money($value): float {
        if (is_string($value)) {
            $value = preg_replace('/[^0-9,\.\-]/', '', $value);
            if (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
                $value = str_replace(',', '.', $value);
            } elseif (substr_count($value, ',') >= 1 && substr_count($value, '.') >= 1) {
                $lastComma = strrpos($value, ',');
                $lastDot = strrpos($value, '.');
                if ($lastComma > $lastDot) {
                    $value = str_replace('.', '', $value);
                    $value = str_replace(',', '.', $value);
                } else {
                    $value = str_replace(',', '', $value);
                }
            }
        }
        return round(max(0, (float)$value), 2);
    }

    public static function savePrivateReceipt(array $file, int $userId): array {
        try {
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return ['ok' => false, 'message' => 'Fotografia inválida ou não recebida.'];
            }
            if (!empty($file['error'])) {
                $uploadError = (int)$file['error'];
                if (in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    return ['ok' => false, 'message' => 'A fotografia ultrapassa o limite de upload do servidor.'];
                }
                if ($uploadError === UPLOAD_ERR_PARTIAL) return ['ok' => false, 'message' => 'O envio da fotografia ficou incompleto. Tenta novamente.'];
                return ['ok' => false, 'message' => 'Falha no envio da fotografia.'];
            }
            $size = (int)($file['size'] ?? 0);
            $runtimeMax = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
            $effectiveMax = $runtimeMax > 0 ? min(self::MAX_FILE_SIZE, $runtimeMax) : self::MAX_FILE_SIZE;
            if ($size <= 0 || $size > $effectiveMax) {
                $limitMb = max(0.1, round($effectiveMax / 1048576, 1));
                return ['ok' => false, 'message' => 'A fotografia deve ter no máximo ' . $limitMb . ' MB neste servidor.'];
            }

            $inspected = self::inspectReceiptFile($file);
            if (empty($inspected['ok'])) {
                return ['ok' => false, 'message' => (string)($inspected['message'] ?? 'Formato de fotografia não suportado.')];
            }
            $mime = (string)$inspected['mime'];
            $ext = (string)$inspected['ext'];

            $uploads = wp_upload_dir();
            if (!is_array($uploads) || !empty($uploads['error']) || empty($uploads['basedir'])) {
                return ['ok' => false, 'message' => 'A pasta de uploads não está disponível.'];
            }
            $base = trailingslashit($uploads['basedir']) . 'fieldflow-private-expenses';
            $ym = gmdate('Y/m');
            $dir = trailingslashit($base) . $ym;
            if (!wp_mkdir_p($dir)) {
                return ['ok' => false, 'message' => 'Não foi possível preparar o arquivo privado.'];
            }
            self::protectDirectory($base);
            self::protectDirectory($dir);

            $token = wp_generate_password(32, false, false);
            $filename = sprintf('expense-%d-%s.%s', $userId, strtolower($token), $ext);
            $destination = trailingslashit($dir) . $filename;
            if (!@move_uploaded_file($file['tmp_name'], $destination)) {
                return ['ok' => false, 'message' => 'Não foi possível guardar a fotografia.'];
            }
            @chmod($destination, 0640);
            $storedSize = @filesize($destination);

            return [
                'ok' => true,
                'path' => $ym . '/' . $filename,
                'mime' => $mime,
                'original_name' => sanitize_file_name((string)($file['name'] ?? 'comprovativo.' . $ext)),
                'size' => $storedSize !== false ? (int)$storedSize : $size,
            ];
        } catch (\Throwable $e) {
            self::logRuntimeError('receipt', $e);
            return ['ok' => false, 'message' => 'Não foi possível validar ou guardar a fotografia do comprovativo. Tenta novamente.'];
        }
    }

    private static function inspectReceiptFile(array $file): array {
        $tmp = (string)($file['tmp_name'] ?? '');
        $name = sanitize_file_name((string)($file['name'] ?? 'comprovativo'));
        $declaredExt = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
        if ($declaredExt === 'jpeg') $declaredExt = 'jpg';

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/heic-sequence' => 'heic',
            'image/heif-sequence' => 'heif',
        ];
        $allowedExt = ['jpg', 'png', 'webp', 'heic', 'heif'];
        $mime = '';

        if (function_exists('getimagesize')) {
            try {
                $imageInfo = @getimagesize($tmp);
                if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
                    $candidate = strtolower((string)$imageInfo['mime']);
                    if (isset($mimeToExt[$candidate])) $mime = $candidate;
                }
            } catch (\Throwable $e) {
                self::logRuntimeError('receipt_getimagesize', $e);
            }
        }

        if ($mime === '' && function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
            try {
                $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $candidate = strtolower((string)@finfo_file($finfo, $tmp));
                    @finfo_close($finfo);
                    if (isset($mimeToExt[$candidate])) $mime = $candidate;
                    if ($mime === '' && in_array($candidate, ['application/octet-stream', 'binary/octet-stream'], true) && in_array($declaredExt, ['heic', 'heif'], true)) {
                        $mime = $declaredExt === 'heif' ? 'image/heif' : 'image/heic';
                    }
                }
            } catch (\Throwable $e) {
                self::logRuntimeError('receipt_fileinfo', $e);
            }
        }

        if ($mime === '' && in_array($declaredExt, ['heic', 'heif'], true)) {
            $mime = $declaredExt === 'heif' ? 'image/heif' : 'image/heic';
        }
        if ($mime === '' || !isset($mimeToExt[$mime])) {
            return ['ok' => false, 'message' => 'Formato não suportado. Usa JPG, PNG, WEBP ou HEIC.'];
        }

        $canonicalExt = $mimeToExt[$mime];
        if ($declaredExt !== '' && in_array($declaredExt, $allowedExt, true)) {
            $declaredCanonical = $declaredExt === 'jpeg' ? 'jpg' : $declaredExt;
            $heicFamily = in_array($canonicalExt, ['heic', 'heif'], true) && in_array($declaredCanonical, ['heic', 'heif'], true);
            if ($declaredCanonical !== $canonicalExt && !$heicFamily) {
                return ['ok' => false, 'message' => 'A extensão da fotografia não corresponde ao conteúdo do ficheiro. Seleciona novamente o comprovativo.'];
            }
        }

        return ['ok' => true, 'mime' => $mime, 'ext' => $canonicalExt];
    }

    private static function logRuntimeError(string $scope, \Throwable $e): void {
        if (!function_exists('error_log')) return;
        $message = sprintf(
            '[FieldFlow Expenses][%s] %s: %s in %s:%d',
            $scope,
            get_class($e),
            $e->getMessage(),
            basename((string)$e->getFile()),
            (int)$e->getLine()
        );
        @error_log($message);
    }

    private static function protectDirectory(string $dir): void {
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $index = trailingslashit($dir) . 'index.html';
        if (!file_exists($index)) @file_put_contents($index, '');
        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \".*\">\nRequire all denied\nDeny from all\n</FilesMatch>\n");
        }
    }

    public static function absoluteReceiptPath(string $relative): string {
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . 'fieldflow-private-expenses/' . $relative;
    }

    public static function receiptUrl(int $expenseId): string {
        return wp_nonce_url(
            admin_url('admin-post.php?action=fieldflow_expense_receipt&id=' . $expenseId),
            'fieldflow_expense_receipt_' . $expenseId
        );
    }

    public static function canRead(array $row, int $userId): bool {
        return current_user_can('routespro_manage') || ((int)($row['user_id'] ?? 0) === $userId);
    }

    public static function normalizeDate(string $value): string {
        $value = sanitize_text_field($value);
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return ($dt && $dt->format('Y-m-d') === $value) ? $value : current_time('Y-m-d');
    }

    public static function duplicateHash(int $userId, array $data): string {
        $parts = [
            $userId,
            strtolower(trim((string)($data['issuer_tax_id'] ?? ''))),
            strtolower(trim((string)($data['document_no'] ?? ''))),
            (string)($data['expense_date'] ?? ''),
            number_format((float)($data['amount_total'] ?? 0), 2, '.', ''),
        ];
        return hash('sha256', implode('|', $parts));
    }


    public static function setStatus(int $id, string $status, int $actorUserId, string $source = 'admin'): bool {
        global $wpdb;
        $row = self::get($id);
        if (!$row || !isset(self::statuses()[$status])) return false;
        $meta = json_decode((string)($row['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $history = isset($meta['payment_history']) && is_array($meta['payment_history']) ? $meta['payment_history'] : [];
        $now = current_time('mysql');
        $previous = (string)($row['status'] ?? 'pending');
        if ($previous !== $status) {
            $history[] = [
                'from' => $previous,
                'to' => $status,
                'at' => $now,
                'by' => $actorUserId,
                'source' => sanitize_key($source),
            ];
            if (count($history) > 20) $history = array_slice($history, -20);
        }
        $meta['payment_history'] = $history;
        if ($status === 'paid') {
            $meta['payment'] = [
                'state' => 'paid',
                'paid_at' => $now,
                'paid_by' => $actorUserId,
                'source' => sanitize_key($source),
            ];
        } elseif ($previous === 'paid') {
            $payment = isset($meta['payment']) && is_array($meta['payment']) ? $meta['payment'] : [];
            $payment['state'] = 'reopened';
            $payment['reopened_at'] = $now;
            $payment['reopened_by'] = $actorUserId;
            $payment['reopened_to'] = $status;
            $meta['payment'] = $payment;
        }
        $updated = $wpdb->update(self::table(), [
            'status' => $status,
            'reviewed_by' => $actorUserId,
            'reviewed_at' => $now,
            'meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => $now,
        ], ['id' => $id]);
        return $updated !== false;
    }

    public static function get(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function publicRow(array $row): array {
        $fields = json_decode((string)($row['qr_fields_json'] ?? ''), true);
        $meta = json_decode((string)($row['meta_json'] ?? ''), true);
        $user = get_userdata((int)$row['user_id']);
        $payment = is_array($meta) && isset($meta['payment']) && is_array($meta['payment']) ? $meta['payment'] : [];
        $paidById = (int)($payment['paid_by'] ?? 0);
        $paidBy = $paidById > 0 ? get_userdata($paidById) : null;
        return [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $user ? $user->display_name : 'Utilizador removido',
            'expense_date' => (string)$row['expense_date'],
            'category' => (string)$row['category'],
            'category_label' => self::categories()[$row['category']] ?? ucfirst((string)$row['category']),
            'merchant_name' => (string)$row['merchant_name'],
            'issuer_tax_id' => (string)$row['issuer_tax_id'],
            'document_no' => (string)$row['document_no'],
            'atcud' => (string)$row['atcud'],
            'description' => (string)$row['description'],
            'payment_method' => (string)$row['payment_method'],
            'payment_method_label' => self::paymentMethods()[$row['payment_method']] ?? ucfirst((string)$row['payment_method']),
            'amount_net' => (float)$row['amount_net'],
            'tax_amount' => (float)$row['tax_amount'],
            'amount_total' => (float)$row['amount_total'],
            'currency' => (string)$row['currency'],
            'status' => (string)$row['status'],
            'status_label' => self::statuses()[$row['status']] ?? ucfirst((string)$row['status']),
            'paid_at' => ((string)($payment['state'] ?? '') === 'paid' && (string)$row['status'] === 'paid') ? (string)($payment['paid_at'] ?? '') : '',
            'paid_by' => ((string)($payment['state'] ?? '') === 'paid' && (string)$row['status'] === 'paid') ? $paidById : 0,
            'paid_by_name' => ((string)($payment['state'] ?? '') === 'paid' && (string)$row['status'] === 'paid' && $paidBy) ? (string)$paidBy->display_name : '',
            'capture_source' => (string)$row['capture_source'],
            'verification_state' => (string)$row['verification_state'],
            'qr_fields' => is_array($fields) ? $fields : [],
            'duplicate_of_id' => (int)$row['duplicate_of_id'],
            'has_receipt' => !empty($row['receipt_path']),
            'receipt_url' => !empty($row['receipt_path']) ? self::receiptUrl((int)$row['id']) : '',
            'created_at' => (string)$row['created_at'],
            'meta' => is_array($meta) ? $meta : [],
        ];
    }
}
