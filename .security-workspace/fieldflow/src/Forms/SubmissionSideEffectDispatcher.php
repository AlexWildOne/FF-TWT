<?php
namespace RoutesPro\Forms;

if (!defined('ABSPATH')) exit;

/**
 * Isolated post-commit side effects for form submissions.
 *
 * Durable submission/answer persistence is completed before this dispatcher is
 * invoked. Each internal effect runs in its own idempotent lane so a failure in
 * Mail, EventBus/Azure, Performance or a public extension hook cannot roll back
 * field data or force already-completed effects to run again.
 */
final class SubmissionSideEffectDispatcher {
    public const VERSION = 1;
    public const CONTRACT = 'fieldflow-submission-side-effects-v1';
    public const RETRY_HOOK = 'fieldflow_submission_side_effect_retry';
    public const MAX_ATTEMPTS = 4;
    private const DONE_TTL = 30 * DAY_IN_SECONDS;
    private const JOB_TTL = 7 * DAY_IN_SECONDS;
    private const LOCK_TTL = 10 * MINUTE_IN_SECONDS;

    public static function registerHooks(): void {
        static $registered = false;
        if ($registered) return;
        $registered = true;
        add_action(self::RETRY_HOOK, [self::class, 'retryLane'], 10, 3);
    }

    /**
     * Rehydrate the canonical submission after COMMIT and fan out isolated lanes.
     */
    public static function dispatchFromSubmission(int $submissionId, bool $isUpdate, string $visitJson = '', string $source = 'scheduled'): array {
        global $wpdb;
        $submissionId = absint($submissionId);
        if ($submissionId <= 0) throw new \RuntimeException('Invalid submission for side-effect dispatch.');

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT s.*,r.owner_user_id FROM ' . Forms::table_submissions() . ' s LEFT JOIN ' . $wpdb->prefix . 'routespro_routes r ON r.id=s.route_id WHERE s.id=%d LIMIT 1',
            $submissionId
        ), ARRAY_A);
        if (!$row) throw new \RuntimeException('Submission not found for side-effect dispatch.');

        $answerRows = $wpdb->get_results($wpdb->prepare(
            'SELECT question_key,question_label,value_text,value_number,value_json FROM ' . Forms::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC',
            $submissionId
        ), ARRAY_A) ?: [];
        $answers = self::decodeAnswers($answerRows);
        $meta = DailySubmissionService::decode_meta((string)($row['meta_json'] ?? ''));
        $daily = is_array($meta['daily_report'] ?? null) ? $meta['daily_report'] : [];
        $revisionToken = self::revisionToken($submissionId, $isUpdate, $daily, $row);
        $completion = json_decode($visitJson, true);
        if (!is_array($completion)) $completion = [];

        $payload = [
            'id' => $submissionId,
            'submission_id' => $submissionId,
            'form_id' => (int)($row['form_id'] ?? 0),
            'binding_id' => (int)($row['binding_id'] ?? 0),
            'client_id' => (int)($row['client_id'] ?? 0),
            'project_id' => (int)($row['project_id'] ?? 0),
            'route_id' => (int)($row['route_id'] ?? 0),
            'route_stop_id' => (int)($row['route_stop_id'] ?? 0),
            'location_id' => (int)($row['location_id'] ?? 0),
            'user_id' => (int)($row['user_id'] ?? 0),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'record_id' => (int)($row['record_id'] ?? 0),
            'record_version_id' => (int)($row['record_version_id'] ?? 0),
            'submitted_at' => (string)($row['submitted_at'] ?? ''),
            'saved_at' => (string)($daily['last_edited_at'] ?? $daily['created_at'] ?? $row['submitted_at'] ?? ''),
            'report_day' => (string)($row['report_day'] ?? $daily['report_day'] ?? ''),
            'is_update' => $isUpdate,
        ];

        return self::dispatchJob([
            'submission_id' => $submissionId,
            'is_update' => $isUpdate,
            'source' => sanitize_key($source) ?: 'scheduled',
            'revision_token' => $revisionToken,
            'submission_payload' => $payload,
            'answers' => $answers,
            'visit_completion' => $completion,
        ]);
    }

    /**
     * Dispatch all eligible side-effect lanes. Never throws for a lane failure.
     */
    public static function dispatchJob(array $job): array {
        $job = self::normaliseJob($job);
        $eventKey = self::eventKey($job);
        set_transient(self::jobKey($eventKey), $job, self::JOB_TTL);

        $results = [];
        foreach (self::laneNames($job) as $lane) {
            $results[$lane] = self::runLane($eventKey, $lane, $job, 0);
        }
        return [
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'event_key' => $eventKey,
            'submission_id' => (int)$job['submission_id'],
            'lanes' => $results,
            'failed' => array_values(array_keys(array_filter($results, static fn($state) => ($state['status'] ?? '') === 'failed'))),
        ];
    }

    public static function retryLane(string $eventKey, string $lane, int $attempt = 1): void {
        $eventKey = sanitize_key($eventKey);
        $lane = sanitize_key($lane);
        $attempt = max(1, $attempt);
        if ($eventKey === '' || $lane === '' || $attempt > self::MAX_ATTEMPTS) return;
        $job = get_transient(self::jobKey($eventKey));
        if (!is_array($job)) {
            self::log('Side-effect retry payload expired before execution.', null, [
                'context_key' => 'submission_side_effect_retry_expired',
                'event_key' => $eventKey,
                'lane' => $lane,
                'attempt' => $attempt,
            ]);
            return;
        }
        self::runLane($eventKey, $lane, self::normaliseJob($job), $attempt);
    }

    public static function descriptor(): array {
        return [
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'retry_hook' => self::RETRY_HOOK,
            'max_attempts' => self::MAX_ATTEMPTS,
            'idempotency' => 'per_revision_per_lane',
            'lanes' => [
                'route_notification',
                'visit_core_event',
                'visit_report_automation',
                'visit_public_hook',
                'submission_core_event',
                'submission_performance',
                'order_suggestion_mail',
                'submission_public_hook',
            ],
        ];
    }

    private static function laneNames(array $job): array {
        $lanes = [];
        $completion = (array)$job['visit_completion'];
        if (!empty($completion['route_completed_now']) && !empty($completion['route_id'])) {
            $lanes[] = 'route_notification';
        }
        if (!empty($completion['completed_now'])) {
            $lanes[] = 'visit_core_event';
            $lanes[] = 'visit_report_automation';
            $lanes[] = 'visit_public_hook';
        }
        $lanes[] = 'submission_core_event';
        if (empty($job['is_update'])) $lanes[] = 'submission_performance';
        $lanes[] = 'order_suggestion_mail';
        $lanes[] = 'submission_public_hook';
        return $lanes;
    }

    private static function runLane(string $eventKey, string $lane, array $job, int $attempt): array {
        if (!in_array($lane, self::laneNames($job), true)) {
            return ['status' => 'not_applicable', 'attempt' => $attempt];
        }
        $doneKey = self::laneKey('done', $eventKey, $lane);
        $lockKey = self::laneKey('lock', $eventKey, $lane);
        if (get_transient($doneKey)) return ['status' => 'done', 'attempt' => $attempt];
        if (get_transient($lockKey)) return ['status' => 'locked', 'attempt' => $attempt];
        set_transient($lockKey, 1, self::LOCK_TTL);

        try {
            self::invokeLane($lane, $job);
            set_transient($doneKey, 1, self::DONE_TTL);
            return ['status' => 'processed', 'attempt' => $attempt];
        } catch (\Throwable $error) {
            self::log('Submission side-effect lane failed.', $error, [
                'context_key' => 'submission_side_effect_lane',
                'submission_id' => (int)$job['submission_id'],
                'event_key' => $eventKey,
                'lane' => $lane,
                'attempt' => $attempt,
            ]);
            $nextAttempt = $attempt + 1;
            $scheduled = false;
            if ($nextAttempt <= self::MAX_ATTEMPTS) {
                $scheduled = self::scheduleRetry($eventKey, $lane, $nextAttempt);
            }
            if (!$scheduled && $nextAttempt > self::MAX_ATTEMPTS) {
                try { do_action('fieldflow_submission_side_effect_failed', $eventKey, $lane, $job, $error->getMessage()); } catch (\Throwable $ignored) {}
            }
            return [
                'status' => 'failed',
                'attempt' => $attempt,
                'retry_scheduled' => $scheduled,
                'error' => sanitize_text_field($error->getMessage()),
            ];
        } finally {
            delete_transient($lockKey);
        }
    }

    private static function invokeLane(string $lane, array $job): void {
        $submissionId = (int)$job['submission_id'];
        $payload = (array)$job['submission_payload'];
        $answers = (array)$job['answers'];
        $completion = (array)$job['visit_completion'];
        $isUpdate = !empty($job['is_update']);

        switch ($lane) {
            case 'route_notification':
                \RoutesPro\Services\Notify::route_event(
                    (int)$completion['route_id'],
                    'completed',
                    'Rota concluída automaticamente após a submissão do formulário na última paragem.'
                );
                return;
            case 'visit_core_event':
                if (class_exists('\\RoutesPro\\Core\\EventBus')) {
                    \RoutesPro\Core\EventBus::onVisitCompleted($submissionId, $completion, $answers);
                }
                return;
            case 'visit_report_automation':
                if (class_exists('\\RoutesPro\\Admin\\ReportAutomations')) {
                    \RoutesPro\Admin\ReportAutomations::handleVisitCompleted($submissionId, $completion, $answers);
                }
                return;
            case 'visit_public_hook':
                do_action('fieldflow_form_visit_completed', $submissionId, $completion, $answers);
                return;
            case 'submission_core_event':
                if (class_exists('\\RoutesPro\\Core\\EventBus')) {
                    if ($isUpdate) \RoutesPro\Core\EventBus::onSubmissionUpdated($submissionId, $payload, $answers);
                    else \RoutesPro\Core\EventBus::onSubmissionCreated($submissionId, $payload, $answers);
                }
                return;
            case 'submission_performance':
                if (class_exists('\\RoutesPro\\Performance\\Module')) {
                    \RoutesPro\Performance\Module::handle_form_submission_automation($submissionId, $payload, $answers);
                }
                return;
            case 'order_suggestion_mail':
                if (class_exists('\\RoutesPro\\Admin\\ReportAutomations')) {
                    \RoutesPro\Admin\ReportAutomations::handleOrderSuggestionSubmission($submissionId, $payload, $answers);
                }
                return;
            case 'submission_public_hook':
                do_action($isUpdate ? 'fieldflow_form_updated' : 'fieldflow_form_submitted', $submissionId, $payload, $answers);
                return;
        }
        throw new \RuntimeException('Unknown submission side-effect lane: ' . $lane);
    }

    private static function scheduleRetry(string $eventKey, string $lane, int $attempt): bool {
        $args = [$eventKey, $lane, $attempt];
        if (wp_next_scheduled(self::RETRY_HOOK, $args)) return true;
        $delay = [1 => 60, 2 => 180, 3 => 600, 4 => 1800][$attempt] ?? 1800;
        $scheduled = wp_schedule_single_event(time() + $delay, self::RETRY_HOOK, $args);
        return $scheduled !== false && !is_wp_error($scheduled);
    }

    private static function normaliseJob(array $job): array {
        $submissionId = absint($job['submission_id'] ?? 0);
        if ($submissionId <= 0) throw new \InvalidArgumentException('Submission side-effect job requires submission_id.');
        return [
            'submission_id' => $submissionId,
            'is_update' => !empty($job['is_update']),
            'source' => sanitize_key((string)($job['source'] ?? 'post_commit')) ?: 'post_commit',
            'revision_token' => sanitize_text_field((string)($job['revision_token'] ?? 'revision-0')),
            'submission_payload' => is_array($job['submission_payload'] ?? null) ? $job['submission_payload'] : ['submission_id' => $submissionId],
            'answers' => is_array($job['answers'] ?? null) ? $job['answers'] : [],
            'visit_completion' => is_array($job['visit_completion'] ?? null) ? $job['visit_completion'] : [],
        ];
    }

    private static function eventKey(array $job): string {
        return substr(hash('sha256', implode('|', [
            self::CONTRACT,
            (string)$job['submission_id'],
            !empty($job['is_update']) ? 'update' : 'create',
            (string)$job['revision_token'],
        ])), 0, 32);
    }

    private static function revisionToken(int $submissionId, bool $isUpdate, array $daily, array $row): string {
        $editCount = max(0, (int)($daily['edit_count'] ?? 0));
        $stamp = (string)($daily['last_edited_at'] ?? $daily['created_at'] ?? $row['submitted_at'] ?? '');
        return implode(':', [$submissionId, $isUpdate ? 'u' : 'c', $editCount, $stamp]);
    }

    private static function decodeAnswers(array $answerRows): array {
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
        return $answers;
    }

    private static function laneKey(string $kind, string $eventKey, string $lane): string {
        return 'ff_se_' . sanitize_key($kind) . '_' . substr(hash('sha256', $eventKey . '|' . $lane), 0, 32);
    }

    private static function jobKey(string $eventKey): string {
        return 'ff_se_job_' . substr(hash('sha256', $eventKey), 0, 32);
    }

    private static function log(string $message, ?\Throwable $error, array $context): void {
        if (class_exists('\\RoutesPro\\Support\\Logger') && $error) {
            \RoutesPro\Support\Logger::captureThrowable($message, $error, $context);
            return;
        }
        if (function_exists('error_log')) error_log('[FieldFlow] ' . $message . ' ' . wp_json_encode($context));
    }
}
