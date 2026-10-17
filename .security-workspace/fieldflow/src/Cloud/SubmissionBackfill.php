<?php
namespace RoutesPro\Cloud;

use RoutesPro\Core\CanonicalStateService;
use RoutesPro\Core\DataContract;

if (!defined('ABSPATH')) exit;

/**
 * Phase 3A, controlled historical submission backfill into the Cloud queue.
 *
 * The backfill never replays the normal form-submitted hooks, so billing,
 * webhooks and client-side integrations are not triggered a second time.
 * Each historical submission receives a deterministic UUID, making a reset
 * and rerun idempotent at both the local Cloud queue and Azure ingest layers.
 */
final class SubmissionBackfill {
    public const VERSION = '1.0.1';
    private const CURSOR_OPTION = 'fieldflow_cloud_backfill_submissions_cursor';
    private const MAX_ID_OPTION = 'fieldflow_cloud_backfill_submissions_max_id';
    private const STARTED_AT_OPTION = 'fieldflow_cloud_backfill_submissions_started_at';
    private const UUID_NAMESPACE = 'fieldflow-cloud-submission-backfill-v1';

    public static function status(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_form_submissions';
        if (!self::tableExists($table)) {
            return [
                'available' => false,
                'started' => false,
                'cursor' => 0,
                'max_id' => 0,
                'total' => 0,
                'remaining' => 0,
                'started_at' => '',
                'queue_ready' => !empty(AzureSyncFoundation::health()['table_ready']),
            ];
        }

        $cursor = max(0, (int)get_option(self::CURSOR_OPTION, 0));
        $maxId = max(0, (int)get_option(self::MAX_ID_OPTION, 0));
        $started = $maxId > 0 || (string)get_option(self::STARTED_AT_OPTION, '') !== '';
        $where = "COALESCE(status,'submitted') NOT IN ('deleted','trash','void','cancelled')";
        $total = 0;
        $remaining = 0;
        if ($started && $maxId > 0) {
            $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where} AND id<=%d", $maxId));
            $remaining = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where} AND id>%d AND id<=%d", $cursor, $maxId));
        }

        $queueStats = self::backfillQueueStats();
        $enqueued = max(0, $total - $remaining);
        return [
            'available' => true,
            'started' => $started,
            'cursor' => $cursor,
            'max_id' => $maxId,
            'total' => $total,
            'remaining' => $remaining,
            'processed' => $enqueued,
            'enqueued' => $enqueued,
            'started_at' => (string)get_option(self::STARTED_AT_OPTION, ''),
            'queue_ready' => !empty(AzureSyncFoundation::health()['table_ready']),
            'cloud_ready' => AzureConnectionManager::ready(),
            'sync_enabled' => AzureConnectionManager::syncEnabled(),
            'submissions_enabled' => AzureSyncFoundation::eventEnabled('submission.created'),
            'queue_pending' => (int)$queueStats['pending'],
            'queue_running' => (int)$queueStats['running'],
            'queue_dead' => (int)$queueStats['dead'],
            'queue_completed' => (int)$queueStats['completed'],
            'fully_completed' => $started && $remaining === 0 && (int)$queueStats['pending'] === 0 && (int)$queueStats['running'] === 0 && (int)$queueStats['dead'] === 0,
        ];
    }

    public static function start(bool $reset = false): array {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_form_submissions';
        if (!self::tableExists($table)) return ['ok'=>false,'reason'=>'submissions_table_missing'];

        $existingMax = max(0, (int)get_option(self::MAX_ID_OPTION, 0));
        if ($existingMax > 0 && !$reset) return array_merge(['ok'=>true,'already_started'=>true], self::status());

        $maxId = max(0, (int)$wpdb->get_var("SELECT MAX(id) FROM {$table}"));
        update_option(self::CURSOR_OPTION, 0, false);
        update_option(self::MAX_ID_OPTION, $maxId, false);
        update_option(self::STARTED_AT_OPTION, current_time('mysql', true), false);
        return array_merge(['ok'=>true,'already_started'=>false], self::status());
    }

    public static function enqueueNext(int $limit = 25): array {
        global $wpdb;
        $limit = max(1, min(100, $limit));
        $status = self::status();
        if (empty($status['available'])) return ['ok'=>false,'reason'=>'submissions_table_missing','enqueued'=>0];
        if (empty($status['started'])) $status = self::start(false);
        if (empty($status['cloud_ready'])) return ['ok'=>false,'reason'=>'cloud_not_configured','enqueued'=>0];
        if (empty($status['sync_enabled'])) return ['ok'=>false,'reason'=>'cloud_sync_disabled','enqueued'=>0];
        if (empty($status['submissions_enabled'])) return ['ok'=>false,'reason'=>'submission_sync_disabled','enqueued'=>0];
        if (empty($status['queue_ready'])) return ['ok'=>false,'reason'=>'cloud_queue_missing','enqueued'=>0];

        // Manual backfill should not depend on WP-Cron to wake the dispatcher.
        // Drain a bounded amount of older backlog before adding the next chunk.
        $kickLimit = max(1, min(25, $limit));
        $dispatchBefore = !empty($status['queue_pending'])
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];

        $table = $wpdb->prefix . 'routespro_form_submissions';
        $cursor = max(0, (int)($status['cursor'] ?? 0));
        $maxId = max(0, (int)($status['max_id'] ?? 0));
        if ($maxId <= 0) {
            $after = self::status();
            return ['ok'=>true,'enqueued'=>0,'completed'=>!empty($after['fully_completed']),'dispatch_before'=>$dispatchBefore,'status'=>$after];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE id>%d AND id<=%d
               AND COALESCE(status,'submitted') NOT IN ('deleted','trash','void','cancelled')
             ORDER BY id ASC LIMIT %d",
            $cursor, $maxId, $limit
        ), ARRAY_A) ?: [];

        $enqueued = 0;
        $duplicates = 0;
        $failed = 0;
        $lastId = $cursor;
        foreach ($rows as $row) {
            $submissionId = absint($row['id'] ?? 0);
            if ($submissionId <= 0) continue;
            try {
                $built = self::buildSubmission($row);
                if (empty($built['ok'])) {
                    $failed++;
                    break;
                }

                $result = AzureSyncFoundation::enqueue(
                    'submission.created',
                    (array)$built['contract'],
                    (array)$built['context'],
                    self::eventUuid($submissionId)
                );
                if (empty($result['ok'])) {
                    $failed++;
                    break;
                }
                if (!empty($result['duplicate'])) $duplicates++;
                else $enqueued++;
                $lastId = $submissionId;
                update_option(self::CURSOR_OPTION, $lastId, false);
            } catch (\Throwable $e) {
                $failed++;
                if (class_exists('\RoutesPro\Support\Logger')) {
                    \RoutesPro\Support\Logger::captureThrowable('Falha no backfill historico Cloud de uma submissao.', $e, [
                        'context_key' => 'cloud_submission_backfill',
                        'submission_id' => $submissionId,
                    ]);
                }
                break;
            }
        }

        // Process a bounded amount immediately through the same certified
        // HMAC dispatcher. WP-Cron remains available for retries/background.
        $queueBeforeAfter = self::backfillQueueStats();
        $dispatchAfter = ($enqueued > 0 || $duplicates > 0 || (int)$queueBeforeAfter['pending'] > 0)
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];

        $after = self::status();
        return [
            'ok' => $failed === 0 && !empty($dispatchAfter['ok']),
            'enqueued' => $enqueued,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'cursor' => $lastId,
            'completed' => !empty($after['fully_completed']),
            'dispatch_before' => $dispatchBefore,
            'dispatch_after' => $dispatchAfter,
            'queue_pending' => (int)($after['queue_pending'] ?? 0),
            'queue_running' => (int)($after['queue_running'] ?? 0),
            'queue_dead' => (int)($after['queue_dead'] ?? 0),
            'status' => $after,
        ];
    }

    public static function processPendingNow(int $limit = 25): array {
        $limit = max(1, min(25, $limit));
        if (!AzureConnectionManager::ready()) return ['ok'=>false,'reason'=>'cloud_not_configured'];
        if (!AzureConnectionManager::syncEnabled()) return ['ok'=>false,'reason'=>'cloud_sync_disabled'];
        $dispatch = AzureSyncFoundation::dispatchNow($limit);
        $status = self::status();
        return [
            'ok'=>!empty($dispatch['ok']),
            'dispatch'=>$dispatch,
            'queue_pending'=>(int)($status['queue_pending'] ?? 0),
            'queue_running'=>(int)($status['queue_running'] ?? 0),
            'queue_dead'=>(int)($status['queue_dead'] ?? 0),
            'completed'=>!empty($status['fully_completed']),
            'status'=>$status,
        ];
    }

    private static function backfillQueueStats(): array {
        global $wpdb;
        $stats = ['pending'=>0,'running'=>0,'dead'=>0,'completed'=>0];
        $table = AzureSyncFoundation::table();
        if (!self::tableExists($table)) return $stats;
        $row = $wpdb->get_row(
            "SELECT
                SUM(status='pending') AS pending,
                SUM(status='running') AS running,
                SUM(status='dead') AS dead,
                SUM(status='completed') AS completed
             FROM {$table}
             WHERE destination='azure'
               AND event_name IN ('submission.created','submission.updated')
               AND payload_json LIKE '%\"cloud_backfill\":true%'",
            ARRAY_A
        ) ?: [];
        foreach ($stats as $key=>$value) $stats[$key] = (int)($row[$key] ?? 0);
        return $stats;
    }

    private static function buildSubmission(array $row): array {
        global $wpdb;
        $submissionId = absint($row['id'] ?? 0);
        if ($submissionId <= 0) return ['ok'=>false,'reason'=>'invalid_submission'];

        $answerTable = $wpdb->prefix . 'routespro_form_submission_answers';
        $answerRows = self::tableExists($answerTable) ? ($wpdb->get_results($wpdb->prepare(
            "SELECT question_key,question_label,value_text,value_number,value_json
             FROM {$answerTable} WHERE submission_id=%d ORDER BY id ASC",
            $submissionId
        ), ARRAY_A) ?: []) : [];
        $answers = [];
        foreach ($answerRows as $answer) {
            $key = sanitize_key((string)($answer['question_key'] ?? ''));
            if ($key === '') continue;
            $json = (string)($answer['value_json'] ?? '');
            if ($json !== '') {
                $decoded = json_decode($json, true);
                $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $json;
                $type = 'json';
            } elseif ($answer['value_number'] !== null && $answer['value_number'] !== '') {
                $value = (float)$answer['value_number'];
                $type = 'number';
            } else {
                $value = (string)($answer['value_text'] ?? '');
                $type = 'text';
            }
            $answers[$key] = [
                'type' => $type,
                'label' => sanitize_text_field((string)($answer['question_label'] ?? $key)),
                'value' => $value,
            ];
        }

        $payload = [
            'id' => $submissionId,
            'submission_id' => $submissionId,
            'form_id' => absint($row['form_id'] ?? 0),
            'binding_id' => absint($row['binding_id'] ?? 0),
            'client_id' => absint($row['client_id'] ?? 0),
            'project_id' => absint($row['project_id'] ?? 0),
            'route_id' => absint($row['route_id'] ?? 0),
            'route_stop_id' => absint($row['route_stop_id'] ?? 0),
            'location_id' => absint($row['location_id'] ?? 0),
            'user_id' => absint($row['user_id'] ?? 0),
            'status' => sanitize_key((string)($row['status'] ?? 'submitted')),
            'submitted_at' => (string)($row['submitted_at'] ?? ''),
            'report_day' => (string)($row['report_day'] ?? ''),
            'cloud_backfill' => true,
            'cloud_backfill_version' => self::VERSION,
        ];

        if (class_exists(CanonicalStateService::class)) {
            $stateContract = CanonicalStateService::submission($submissionId);
            $state = (array)($stateContract['data'] ?? []);
            foreach ($state as $key => $value) {
                if (!array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === 0 || $payload[$key] === null) {
                    $payload[$key] = $value;
                }
            }
        }

        $contract = DataContract::submission($submissionId, $payload, $answers);
        $context = [
            'entity_type' => 'submission',
            'entity_id' => $submissionId,
            'submission_id' => $submissionId,
            'client_id' => absint($payload['client_id'] ?? 0),
            'project_id' => absint($payload['project_id'] ?? 0),
            'route_id' => absint($payload['route_id'] ?? 0),
            'route_stop_id' => absint($payload['route_stop_id'] ?? 0),
            'location_id' => absint($payload['location_id'] ?? 0),
            'actor_user_id' => absint($payload['user_id'] ?? 0),
        ];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context];
    }

    private static function eventUuid(int $submissionId): string {
        $tenant = TenantIdentity::tenantId();
        $hash = substr(sha1(self::UUID_NAMESPACE . '|' . $tenant . '|' . $submissionId), 0, 32);
        $hash[12] = '5';
        $variant = hexdec($hash[16]);
        $hash[16] = dechex(($variant & 0x3) | 0x8);
        return sprintf('%s-%s-%s-%s-%s', substr($hash,0,8), substr($hash,8,4), substr($hash,12,4), substr($hash,16,4), substr($hash,20,12));
    }

    private static function tableExists(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
