<?php
namespace RoutesPro\Core;

if (!defined('ABSPATH')) exit;

class EventBus {
    private static $registered = false;

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_core_events';
    }

    public static function exists(): bool {
        global $wpdb;
        $table = self::tableName();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        // Submission/visit events are dispatched by SubmissionSideEffectDispatcher
        // in isolated post-commit lanes. Other domain events remain native hooks.
        add_action('fieldflow_briefing_published', [self::class, 'onBriefingPublished'], 10, 2);
        add_action('fieldflow_stock_imported', [self::class, 'onStockImported'], 10, 2);
    }

    public static function emit(string $eventName, array $payload = [], array $context = [], string $source = ''): string {
        global $wpdb;
        $eventName = self::normaliseEventName($eventName);
        $eventUuid = wp_generate_uuid4();
        $source = $source !== '' ? sanitize_key($source) : AuditLog::source();
        $context = self::contextFromPayload($payload, $context);
        $insertedId = 0;

        if (self::exists()) {
            $ok = $wpdb->insert(self::tableName(), [
                'event_uuid' => $eventUuid,
                'event_name' => $eventName,
                'event_version' => DataContract::VERSION,
                'status' => 'pending',
                'source' => $source,
                'entity_type' => sanitize_key((string)($context['entity_type'] ?? 'system')),
                'entity_id' => absint($context['entity_id'] ?? 0),
                'actor_user_id' => !empty($context['actor_user_id']) ? absint($context['actor_user_id']) : (get_current_user_id() ?: null),
                'client_id' => !empty($context['client_id']) ? absint($context['client_id']) : null,
                'project_id' => !empty($context['project_id']) ? absint($context['project_id']) : null,
                'route_id' => !empty($context['route_id']) ? absint($context['route_id']) : null,
                'route_stop_id' => !empty($context['route_stop_id']) ? absint($context['route_stop_id']) : null,
                'location_id' => !empty($context['location_id']) ? absint($context['location_id']) : null,
                'submission_id' => !empty($context['submission_id']) ? absint($context['submission_id']) : null,
                'briefing_id' => !empty($context['briefing_id']) ? absint($context['briefing_id']) : null,
                'correlation_id' => AuditLog::correlationId(),
                'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'attempts' => 1,
                'available_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
            ], ['%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%d','%d','%d','%d','%d','%s','%s','%d','%s','%s']);
            if ($ok) $insertedId = (int)$wpdb->insert_id;
        }

        try {
            do_action('fieldflow_core_event', $eventName, $payload, $context, $eventUuid);
            if ($insertedId > 0) {
                $wpdb->update(self::tableName(), [
                    'status' => 'processed',
                    'processed_at' => current_time('mysql'),
                    'error_message' => null,
                ], ['id' => $insertedId], ['%s','%s','%s'], ['%d']);
            }
        } catch (\Throwable $e) {
            if ($insertedId > 0) {
                $wpdb->update(self::tableName(), [
                    'status' => 'failed',
                    'error_message' => substr($e->getMessage(), 0, 1000),
                ], ['id' => $insertedId], ['%s','%s'], ['%d']);
            }
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Falha ao processar evento central do FieldFlow.', $e, [
                    'context_key' => 'core_event',
                    'route_id' => absint($context['route_id'] ?? 0),
                    'client_id' => absint($context['client_id'] ?? 0),
                    'project_id' => absint($context['project_id'] ?? 0),
                ]);
            }
        }

        return $eventUuid;
    }

    public static function onSubmissionCreated(int $submissionId, array $payload = [], array $answers = []): void {
        $payload = self::enrichSubmissionPayload($submissionId, $payload);
        $contract = DataContract::submission($submissionId, $payload, $answers);
        $context = self::contextFromPayload($payload, [
            'entity_type' => 'submission',
            'entity_id' => $submissionId,
            'submission_id' => $submissionId,
        ]);
        self::emit('submission.created', $contract, $context);
        AuditLog::record('submission.created', 'submission', $submissionId, null, $contract, $context);
    }

    public static function onSubmissionUpdated(int $submissionId, array $payload = [], array $answers = []): void {
        $payload = self::enrichSubmissionPayload($submissionId, $payload);
        $contract = DataContract::submission($submissionId, $payload, $answers);
        $context = self::contextFromPayload($payload, [
            'entity_type' => 'submission',
            'entity_id' => $submissionId,
            'submission_id' => $submissionId,
        ]);
        self::emit('submission.updated', $contract, $context);
        AuditLog::record('submission.updated', 'submission', $submissionId, null, $contract, $context, ['note' => 'O histórico detalhado de respostas permanece nas versões do registo.']);
    }

    public static function onVisitCompleted(int $submissionId, array $completion = [], array $answers = []): void {
        $stopId = absint($completion['route_stop_id'] ?? 0);
        $context = self::contextFromPayload($completion, [
            'entity_type' => 'visit',
            'entity_id' => $stopId,
            'submission_id' => $submissionId,
        ]);
        self::emit('visit.completed', DataContract::state('visit', $completion), $context);
    }

    public static function onBriefingPublished(int $briefingId, array $payload = []): void {
        $context = self::contextFromPayload($payload, [
            'entity_type' => 'briefing',
            'entity_id' => $briefingId,
            'briefing_id' => $briefingId,
        ]);
        self::emit('briefing.published', DataContract::envelope('briefing', $payload), $context);
        AuditLog::record('briefing.published', 'briefing', $briefingId, null, $payload, $context);
    }

    public static function onStockImported(int $batchId, array $payload = []): void {
        $context = self::contextFromPayload($payload, [
            'entity_type' => 'stock_batch',
            'entity_id' => $batchId,
        ]);
        self::emit('stock.imported', DataContract::envelope('stock_batch', $payload), $context);
        AuditLog::record('stock.imported', 'stock_batch', $batchId, null, $payload, $context);
    }

    public static function recent(int $limit = 50, string $status = ''): array {
        global $wpdb;
        if (!self::exists()) return [];
        $limit = max(1, min(250, $limit));
        if ($status !== '') {
            return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::tableName() . ' WHERE status=%s ORDER BY id DESC LIMIT ' . $limit, sanitize_key($status)), ARRAY_A) ?: [];
        }
        return $wpdb->get_results('SELECT * FROM ' . self::tableName() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function counts(): array {
        global $wpdb;
        if (!self::exists()) return ['total' => 0, 'pending' => 0, 'processed' => 0, 'failed' => 0];
        $rows = $wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . self::tableName() . ' GROUP BY status', ARRAY_A) ?: [];
        $out = ['total' => 0, 'pending' => 0, 'processed' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $status = sanitize_key((string)($row['status'] ?? ''));
            $count = (int)($row['total'] ?? 0);
            $out['total'] += $count;
            if (array_key_exists($status, $out)) $out[$status] = $count;
        }
        return $out;
    }

    private static function contextFromPayload(array $payload, array $context): array {
        foreach (['client_id','project_id','route_id','route_stop_id','location_id','submission_id','briefing_id','actor_user_id'] as $key) {
            if (empty($context[$key]) && !empty($payload[$key])) $context[$key] = absint($payload[$key]);
        }
        if (empty($context['actor_user_id']) && !empty($payload['user_id'])) $context['actor_user_id'] = absint($payload['user_id']);
        return $context;
    }

    private static function normaliseEventName(string $eventName): string {
        $eventName = strtolower(trim($eventName));
        $eventName = preg_replace('/[^a-z0-9._:-]+/', '_', $eventName) ?: 'system.unknown';
        return substr($eventName, 0, 96);
    }

    private static function enrichSubmissionPayload(int $submissionId, array $payload): array {
        if (!class_exists('\\RoutesPro\\Core\\CanonicalStateService')) return $payload;
        $contract = CanonicalStateService::submission($submissionId);
        $state = (array)($contract['data'] ?? []);
        foreach (['form_id','client_id','project_id','route_id','route_stop_id','location_id','user_id','report_day','submitted_at','status','integrity','issues'] as $key) {
            if (!array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === 0 || $payload[$key] === null) {
                if (array_key_exists($key, $state)) $payload[$key] = $state[$key];
            }
        }
        return $payload;
    }
}
