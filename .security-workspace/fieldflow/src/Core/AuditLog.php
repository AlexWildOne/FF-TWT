<?php
namespace RoutesPro\Core;

if (!defined('ABSPATH')) exit;

class AuditLog {
    private static $correlationId = null;

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_audit_log';
    }

    public static function exists(): bool {
        global $wpdb;
        $table = self::tableName();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function correlationId(): string {
        if (self::$correlationId !== null) return self::$correlationId;
        $header = isset($_SERVER['HTTP_X_FIELDFLOW_CORRELATION_ID'])
            ? sanitize_text_field(wp_unslash((string)$_SERVER['HTTP_X_FIELDFLOW_CORRELATION_ID']))
            : '';
        $header = preg_replace('/[^a-zA-Z0-9._:-]/', '', $header) ?: '';
        self::$correlationId = $header !== '' ? substr($header, 0, 64) : substr(wp_generate_uuid4(), 0, 64);
        return self::$correlationId;
    }

    public static function source(): string {
        if (defined('WP_CLI') && WP_CLI) return 'cli';
        if (function_exists('wp_doing_cron') && wp_doing_cron()) return 'cron';
        if (defined('REST_REQUEST') && REST_REQUEST) return 'api';
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return 'ajax';
        if (is_admin()) return 'backoffice';
        return 'frontend';
    }

    public static function record(string $action, string $entityType, int $entityId, $before = null, $after = null, array $context = [], array $meta = []): int {
        global $wpdb;
        if (!self::exists()) return 0;

        $action = self::normaliseKey($action, 96, 'unknown.action');
        $entityType = self::normaliseKey($entityType, 64, 'unknown');
        $source = self::normaliseKey((string)($context['source'] ?? self::source()), 32, 'system');
        $correlationId = sanitize_text_field((string)($context['correlation_id'] ?? self::correlationId()));

        $result = $wpdb->insert(self::tableName(), [
            'action_name' => $action,
            'entity_type' => $entityType,
            'entity_id' => max(0, $entityId),
            'actor_user_id' => !empty($context['actor_user_id']) ? absint($context['actor_user_id']) : (get_current_user_id() ?: null),
            'source' => $source,
            'client_id' => !empty($context['client_id']) ? absint($context['client_id']) : null,
            'project_id' => !empty($context['project_id']) ? absint($context['project_id']) : null,
            'route_id' => !empty($context['route_id']) ? absint($context['route_id']) : null,
            'route_stop_id' => !empty($context['route_stop_id']) ? absint($context['route_stop_id']) : null,
            'location_id' => !empty($context['location_id']) ? absint($context['location_id']) : null,
            'submission_id' => !empty($context['submission_id']) ? absint($context['submission_id']) : null,
            'correlation_id' => substr($correlationId, 0, 64),
            'before_json' => self::encode(self::preparePayload($before, $entityType, $entityId)),
            'after_json' => self::encode(self::preparePayload($after, $entityType, $entityId)),
            'meta_json' => self::encode(self::sanitizeRecursive($meta)),
            'created_at' => current_time('mysql'),
        ], ['%s','%s','%d','%d','%s','%d','%d','%d','%d','%d','%d','%s','%s','%s','%s','%s']);

        return $result ? (int)$wpdb->insert_id : 0;
    }

    public static function recent(int $limit = 50, array $filters = []): array {
        global $wpdb;
        if (!self::exists()) return [];
        $limit = max(1, min(250, $limit));
        $where = ['1=1'];
        $args = [];
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type=%s';
            $args[] = self::normaliseKey((string)$filters['entity_type'], 64, 'unknown');
        }
        if (!empty($filters['action_name'])) {
            $where[] = 'action_name=%s';
            $args[] = self::normaliseKey((string)$filters['action_name'], 96, 'unknown.action');
        }
        if (!empty($filters['route_id'])) {
            $where[] = 'route_id=%d';
            $args[] = absint($filters['route_id']);
        }
        $sql = 'SELECT * FROM ' . self::tableName() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit;
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public static function countSince(string $since): int {
        global $wpdb;
        if (!self::exists()) return 0;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::tableName() . ' WHERE created_at >= %s', $since));
    }

    private static function normaliseKey(string $value, int $limit, string $fallback): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._:-]+/', '_', $value) ?: '';
        return substr($value !== '' ? $value : $fallback, 0, $limit);
    }

    private static function preparePayload($value, string $entityType, int $entityId) {
        if ($value === null || $value === []) return $value;

        // Submission answers belong in the canonical submission/version tables, not in the audit log.
        // The audit trail only needs enough metadata to prove who changed what and when.
        if ($entityType === 'submission') {
            return self::summarizeSubmission($value, $entityId);
        }

        return self::sanitizeRecursive($value);
    }

    private static function summarizeSubmission($value, int $entityId): array {
        $source = is_object($value) ? get_object_vars($value) : (is_array($value) ? $value : []);
        $data = $source;
        if (isset($source['data']) && is_array($source['data'])) {
            $data = $source['data'];
        }

        $submission = isset($source['submission']) && is_array($source['submission'])
            ? $source['submission']
            : $data;
        $answers = [];
        if (isset($source['answers']) && is_array($source['answers'])) {
            $answers = $source['answers'];
        } elseif (isset($data['answers']) && is_array($data['answers'])) {
            $answers = $data['answers'];
        }

        $summary = [
            'submission_id' => absint($submission['submission_id'] ?? $submission['id'] ?? $data['submission_id'] ?? $entityId),
            'form_id' => absint($submission['form_id'] ?? $data['form_id'] ?? 0),
            'binding_id' => absint($submission['binding_id'] ?? $data['binding_id'] ?? 0),
            'client_id' => absint($submission['client_id'] ?? $data['client_id'] ?? 0),
            'project_id' => absint($submission['project_id'] ?? $data['project_id'] ?? 0),
            'route_id' => absint($submission['route_id'] ?? $data['route_id'] ?? 0),
            'route_stop_id' => absint($submission['route_stop_id'] ?? $data['route_stop_id'] ?? 0),
            'location_id' => absint($submission['location_id'] ?? $data['location_id'] ?? 0),
            'user_id' => absint($submission['user_id'] ?? $submission['owner_user_id'] ?? $data['user_id'] ?? 0),
            'record_id' => absint($submission['record_id'] ?? $data['record_id'] ?? 0),
            'record_version_id' => absint($submission['record_version_id'] ?? $data['record_version_id'] ?? 0),
            'status' => sanitize_key((string)($submission['status'] ?? $data['status'] ?? '')),
            'report_day' => sanitize_text_field((string)($submission['report_day'] ?? $data['report_day'] ?? '')),
            'submitted_at' => sanitize_text_field((string)($submission['submitted_at'] ?? $data['submitted_at'] ?? '')),
            'saved_at' => sanitize_text_field((string)($submission['saved_at'] ?? $data['saved_at'] ?? '')),
            'answer_count' => count($answers),
        ];

        if (array_key_exists('updated_existing', $data)) {
            $summary['updated_existing'] = (bool)$data['updated_existing'];
        }

        // Remove empty optional values to keep the audit payload compact.
        foreach ($summary as $key => $item) {
            if ($item === '' || $item === 0 || $item === null) unset($summary[$key]);
        }

        return self::sanitizeRecursive($summary);
    }

    private static function sanitizeRecursive($value, string $parentKey = '') {
        if (is_object($value)) $value = get_object_vars($value);
        if (!is_array($value)) {
            if (is_string($value) && self::isSensitiveKey($parentKey)) return '[redacted]';
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $keyString = is_string($key) ? $key : (string)$key;
            if (self::isSensitiveKey($keyString)) {
                $clean[$key] = '[redacted]';
                continue;
            }
            $clean[$key] = self::sanitizeRecursive($item, $keyString);
        }
        return $clean;
    }

    private static function isSensitiveKey(string $key): bool {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?: '';
        $key = trim($key, '_');
        if ($key === '') return false;

        $exact = [
            'password', 'passwd', 'pwd', 'passphrase',
            'token', 'access_token', 'refresh_token', 'bearer_token', 'id_token',
            'authorization', 'proxy_authorization', 'nonce',
            'secret', 'client_secret', 'webhook_secret',
            'api_key', 'apikey', 'private_key', 'key_hash',
            'cookie', 'cookies', 'set_cookie', 'session_id', 'session_token',
        ];
        if (in_array($key, $exact, true)) return true;

        foreach (['_password', '_secret', '_token', '_api_key', '_private_key'] as $suffix) {
            if (substr($key, -strlen($suffix)) === $suffix) return true;
        }
        return false;
    }

    private static function encode($value): ?string {
        if ($value === null || $value === []) return null;
        if (is_string($value)) return wp_json_encode(['value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
