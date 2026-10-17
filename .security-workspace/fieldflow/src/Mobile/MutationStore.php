<?php
namespace RoutesPro\Mobile;

use WP_Error;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

class MutationStore {
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_mobile_mutations';
    }

    public static function execute(string $mutation_id, string $type, callable $callback) {
        $mutation_id = self::sanitize_id($mutation_id);
        if ($mutation_id === '') {
            return new WP_Error('fieldflow_mobile_mutation_required', 'mutation_id is required for mobile write operations.', ['status' => 400]);
        }

        $existing = self::find($mutation_id);
        if ($existing) return self::response_from_row($existing);

        $reserved = self::reserve($mutation_id, $type);
        if (!$reserved) {
            $existing = self::find($mutation_id);
            if ($existing) return self::response_from_row($existing);
            return new WP_Error('fieldflow_mobile_mutation_reserve_failed', 'Could not reserve the mobile operation.', ['status' => 503]);
        }

        try {
            $result = $callback();
        } catch (\Throwable $e) {
            self::release($mutation_id);
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Mobile mutation failed.', $e, [
                    'context_key' => 'mobile_mutation',
                    'mutation_id' => $mutation_id,
                    'mutation_type' => $type,
                ]);
            }
            return new WP_Error('fieldflow_mobile_mutation_failed', 'The mobile operation failed unexpectedly.', ['status' => 500]);
        }

        $status = self::status_of($result);
        $payload = self::payload_of($result);
        if (self::retryable_status($status)) {
            self::release($mutation_id);
        } else {
            self::complete($mutation_id, $status, $payload, is_wp_error($result) ? 'failed' : 'completed');
        }
        return $result;
    }

    public static function beginExternal(string $mutation_id, string $type) {
        $mutation_id = self::sanitize_id($mutation_id);
        if ($mutation_id === '') return true;
        $existing = self::find($mutation_id);
        if ($existing) return self::response_from_row($existing);
        if (self::reserve($mutation_id, $type)) return true;
        $existing = self::find($mutation_id);
        return $existing ? self::response_from_row($existing) : new WP_Error('fieldflow_mobile_mutation_reserve_failed', 'Could not reserve the mobile operation.', ['status' => 503]);
    }

    public static function finishExternal(string $mutation_id, int $status, $payload, bool $failed = false): void {
        $mutation_id = self::sanitize_id($mutation_id);
        if ($mutation_id === '') return;
        if (self::retryable_status($status)) self::release($mutation_id);
        else self::complete($mutation_id, $status, $payload, $failed ? 'failed' : 'completed');
    }

    public static function releaseExternal(string $mutation_id): void {
        $mutation_id = self::sanitize_id($mutation_id);
        if ($mutation_id !== '') self::release($mutation_id);
    }

    public static function find(string $mutation_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE mutation_id=%s AND user_id=%d LIMIT 1',
            self::sanitize_id($mutation_id),
            get_current_user_id()
        ), ARRAY_A);
        if ($row && (string)($row['status'] ?? '') !== 'processing' && self::retryable_status((int)($row['response_code'] ?? 200))) {
            // Older runtimes cached throttling responses. Do not replay them forever.
            $wpdb->delete(self::table(), [
                'id' => (int)$row['id'],
                'status' => (string)$row['status'],
                'response_code' => (int)$row['response_code'],
            ], ['%d','%s','%d']);
            return null;
        }
        if ($row && (string)($row['status'] ?? '') === 'processing') {
            $updated = strtotime((string)($row['updated_at'] ?? $row['created_at'] ?? '')) ?: 0;
            if ($updated > 0 && $updated < time() - 300) {
                $wpdb->delete(self::table(), ['id' => (int)$row['id']], ['%d']);
                return null;
            }
        }
        return $row ?: null;
    }

    private static function reserve(string $mutation_id, string $type): bool {
        global $wpdb;
        $session = Auth::current_session();
        $ok = $wpdb->insert(self::table(), [
            'mutation_id' => $mutation_id,
            'user_id' => get_current_user_id(),
            'device_id' => sanitize_text_field((string)($session['device_id'] ?? '')),
            'mutation_type' => sanitize_key($type),
            'status' => 'processing',
            'response_code' => 202,
            'response_json' => wp_json_encode(['ok' => false, 'code' => 'fieldflow_mobile_mutation_processing']),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 90 * DAY_IN_SECONDS),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ], ['%s','%d','%s','%s','%s','%d','%s','%s','%s','%s']);
        return $ok !== false && (int)$wpdb->insert_id > 0;
    }

    private static function complete(string $mutation_id, int $status, $payload, string $state): void {
        global $wpdb;
        $wpdb->update(self::table(), [
            'status' => sanitize_key($state),
            'response_code' => $status,
            'response_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => current_time('mysql', true),
        ], [
            'mutation_id' => $mutation_id,
            'user_id' => get_current_user_id(),
        ], ['%s','%d','%s','%s'], ['%s','%d']);
    }

    private static function release(string $mutation_id): void {
        global $wpdb;
        $wpdb->delete(self::table(), [
            'mutation_id' => $mutation_id,
            'user_id' => get_current_user_id(),
            'status' => 'processing',
        ], ['%s','%d','%s']);
    }

    private static function response_from_row(array $row) {
        if ((string)($row['status'] ?? '') === 'processing') {
            return new WP_Error('fieldflow_mobile_mutation_processing', 'This mobile operation is already being processed.', [
                'status' => 409,
                'retry_after_seconds' => 2,
            ]);
        }
        $payload = json_decode((string)($row['response_json'] ?? ''), true);
        if (!is_array($payload)) $payload = ['ok' => false, 'code' => 'fieldflow_mobile_cached_response_invalid'];
        $payload['_idempotent_replay'] = true;
        return new WP_REST_Response($payload, max(100, (int)($row['response_code'] ?? 200)));
    }

    private static function retryable_status(int $status): bool {
        return $status >= 500 || in_array($status, [408, 425, 429], true);
    }

    private static function status_of($result): int {
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            return is_array($data) ? (int)($data['status'] ?? 500) : 500;
        }
        if ($result instanceof WP_REST_Response) return $result->get_status();
        return 200;
    }

    private static function payload_of($result) {
        if (is_wp_error($result)) {
            return [
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
                'data' => $result->get_error_data(),
            ];
        }
        if ($result instanceof WP_REST_Response) return $result->get_data();
        return $result;
    }

    private static function sanitize_id(string $value): string {
        return substr(preg_replace('/[^A-Za-z0-9._:-]/', '', trim($value)) ?: '', 0, 64);
    }
}
