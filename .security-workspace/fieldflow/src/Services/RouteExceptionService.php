<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

final class RouteExceptionService {
    public static function statuses(): array {
        return [
            'none' => 'Sem ocorrência',
            'not_completed' => 'Rota não concluída',
            'partially_completed' => 'Rota parcialmente concluída',
            'absence' => 'Ausência ou indisponibilidade do merchandiser',
            'store_constraints' => 'Constrangimentos nas lojas',
            'transport' => 'Problema de transporte ou deslocação',
            'rescheduled' => 'Rota reagendada',
            'other' => 'Outro motivo',
        ];
    }

    public static function normalize(array $input, array $existing = [], int $actor_user_id = 0): array {
        $statuses = self::statuses();
        $status = sanitize_key((string)($input['status'] ?? 'none'));
        if (!isset($statuses[$status])) $status = 'other';
        $note = sanitize_textarea_field((string)($input['note'] ?? ''));
        $notify = !empty($input['notify_client']) ? 1 : 0;
        $now = current_time('mysql');
        $current = is_array($existing) ? $existing : [];
        $shared = [
            'last_notification_hash' => (string)($current['last_notification_hash'] ?? ''),
            'last_notified_at' => (string)($current['last_notified_at'] ?? ''),
            'last_notification_result' => (string)($current['last_notification_result'] ?? ''),
            'last_notification_recipients' => (array)($current['last_notification_recipients'] ?? []),
            'notification_count' => (int)($current['notification_count'] ?? 0),
            'notification_attempt_count' => (int)($current['notification_attempt_count'] ?? 0),
        ];
        if ($status === 'none') {
            $was_active = !empty($current['active']);
            return array_merge($shared, [
                'status' => 'none',
                'reason_label' => $statuses['none'],
                'note' => '',
                'notify_client' => 0,
                'active' => 0,
                'last_notification_hash' => '',
                'resolved_at' => $was_active ? $now : (string)($current['resolved_at'] ?? ''),
                'resolved_by' => $was_active ? $actor_user_id : (int)($current['resolved_by'] ?? 0),
                'updated_at' => $now,
                'updated_by' => $actor_user_id,
            ]);
        }
        return array_merge($shared, [
            'status' => $status,
            'reason_label' => $statuses[$status],
            'note' => $note,
            'notify_client' => $notify,
            'active' => 1,
            'first_reported_at' => (string)($current['first_reported_at'] ?? $now),
            'resolved_at' => '',
            'resolved_by' => 0,
            'updated_at' => $now,
            'updated_by' => $actor_user_id,
        ]);
    }

    public static function notification_hash(int $route_id, array $exception): string {
        return hash('sha256', wp_json_encode([
            'route_id' => $route_id,
            'status' => (string)($exception['status'] ?? ''),
            'note' => trim((string)($exception['note'] ?? '')),
            'notify_client' => !empty($exception['notify_client']) ? 1 : 0,
        ]));
    }

    public static function should_notify(int $route_id, array $exception, bool $force = false): bool {
        if (empty($exception['active']) || empty($exception['notify_client']) || trim((string)($exception['note'] ?? '')) === '') return false;
        if ($force) return true;
        $previous = (string)($exception['last_notification_hash'] ?? '');
        if ($previous === '') return true;
        return !hash_equals($previous, self::notification_hash($route_id, $exception));
    }

    public static function mark_notification(array $exception, int $route_id, array $result): array {
        $sent = !empty($result['sent']);
        $now = current_time('mysql');
        if ($sent) {
            $exception['last_notification_hash'] = self::notification_hash($route_id, $exception);
            $exception['last_notified_at'] = $now;
        }
        $exception['last_notification_attempt_at'] = $now;
        $exception['last_notification_result'] = $sent ? 'sent' : (string)($result['skipped'] ?? 'failed');
        $exception['last_notification_recipients'] = array_values(array_filter(array_map('sanitize_email', (array)($result['recipients'] ?? []))));
        $exception['notification_attempt_count'] = max(0, (int)($exception['notification_attempt_count'] ?? 0)) + 1;
        if ($sent) {
            $exception['notification_count'] = max(0, (int)($exception['notification_count'] ?? 0)) + 1;
        }
        return $exception;
    }
}
