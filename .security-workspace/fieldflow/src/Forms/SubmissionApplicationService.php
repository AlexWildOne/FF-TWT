<?php
namespace RoutesPro\Forms;

use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Canonical application service for the durable portion of FieldFlow form submissions.
 *
 * Channel adapters (web/mobile) remain responsible for request transport, uploads and
 * presentation-specific errors. This service owns the shared application semantics:
 * schema assembly, durable submission/answer persistence and post-commit envelopes.
 *
 * Side effects such as e-mail, Analytics, Azure delivery and route notifications stay
 * outside this service and are intentionally handled by SubmissionPipeline/SubmissionSideEffectDispatcher
 * post-commit flows. This keeps the durable write independent from secondary systems.
 */
final class SubmissionApplicationService {
    public const VERSION = 1;
    public const CONTRACT = 'fieldflow-submission-application-service-v1';

    public static function buildSchema(array $form, array $submitContext): array {
        $schema = Forms::decode_schema((string)($form['schema_json'] ?? ''));
        if (class_exists(ContextQuestions::class)) {
            $schema = ContextQuestions::inject_into_schema($schema, ContextQuestions::regular_for_context($submitContext));
        }
        if (class_exists(BriefingFormBridge::class)) {
            $schema = BriefingFormBridge::expand_blocks($schema, $submitContext);
        }
        if (class_exists(EvidenceFlow::class)) {
            $schema = EvidenceFlow::expand_schema($schema);
        }
        if (class_exists(ConditionalFlow::class)) {
            $schema = ConditionalFlow::normalise_schema($schema);
        }
        if (class_exists(ProductCardex::class)) {
            $schema = ProductCardex::synchronise_order_questions_with_fronts($schema, $submitContext);
        }
        return $schema;
    }

    /**
     * Persist a submission and its canonical answers inside one transaction.
     *
     * The caller may keep the transaction open for the legacy mobile flow by
     * passing commit_after_persist=false. This preserves the certified behaviour
     * while moving the duplicated SQL into one application service.
     *
     * @return array|WP_Error
     */
    public static function persist(array $command) {
        global $wpdb;

        $formId = absint($command['form_id'] ?? 0);
        $bindingId = absint($command['binding_id'] ?? 0);
        $context = is_array($command['context'] ?? null) ? $command['context'] : [];
        $userId = absint($command['user_id'] ?? 0);
        $ownerUserId = absint($command['owner_user_id'] ?? 0);
        $reportDay = DailySubmissionService::normalise_report_day((string)($command['report_day'] ?? ''));
        $savedAt = sanitize_text_field((string)($command['saved_at'] ?? current_time('mysql')));
        $baseMeta = is_array($command['base_meta'] ?? null) ? $command['base_meta'] : [];
        $dailyExisting = is_array($command['daily_existing'] ?? null) ? $command['daily_existing'] : null;
        $isUpdate = !empty($command['is_update']);
        $answers = is_array($command['answers'] ?? null) ? $command['answers'] : [];
        $channel = in_array((string)($command['channel'] ?? ''), ['web', 'mobile'], true)
            ? (string)$command['channel'] : 'web';
        $commitAfterPersist = !array_key_exists('commit_after_persist', $command) || !empty($command['commit_after_persist']);

        if ($formId <= 0 || $userId <= 0 || $reportDay === '') {
            return self::error('invalid_command', 'Invalid submission application command.', 422, [
                'form_id' => $formId,
                'user_id' => $userId,
                'report_day' => $reportDay,
            ]);
        }
        if ($isUpdate && empty($dailyExisting['id'])) {
            return self::error('daily_report_missing', 'The report selected for editing no longer exists.', 409, [
                'report_day' => $reportDay,
            ]);
        }

        $started = $wpdb->query('START TRANSACTION');
        if ($started === false) {
            return self::error('transaction_start_failed', $wpdb->last_error ?: 'Could not start submission transaction.', 500);
        }

        $submissionId = 0;
        $originalSubmittedAt = $savedAt;
        $auditBefore = null;
        $meta = DailySubmissionService::build_meta($baseMeta, $reportDay, $dailyExisting, $userId, $savedAt);
        $metaJson = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($isUpdate) {
            $locked = $wpdb->get_row($wpdb->prepare(
                'SELECT * FROM ' . Forms::table_submissions() . ' WHERE id=%d LIMIT 1 FOR UPDATE',
                (int)$dailyExisting['id']
            ), ARRAY_A);
            if (!$locked) {
                self::rollback();
                return self::error('daily_report_missing', 'The report selected for editing no longer exists.', 409, [
                    'report_day' => $reportDay,
                ]);
            }
            if (!DailySubmissionService::can_edit($locked, $userId)) {
                self::rollback();
                return self::error('daily_report_edit_forbidden', 'The existing report cannot be edited by this user.', 403, [
                    'submission_id' => (int)$locked['id'],
                    'report_day' => $reportDay,
                ]);
            }
            $contextConflict = DailySubmissionService::edit_context_conflict($locked, $context);
            if ($contextConflict) {
                self::rollback();
                return self::error('daily_report_context_conflict', 'This report belongs to another route or stop.', 409, [
                    'submission_id' => (int)$locked['id'],
                    'report_day' => $reportDay,
                    'conflict' => $contextConflict,
                    'original_context' => DailySubmissionService::original_context($locked),
                ]);
            }

            $submissionId = (int)$locked['id'];
            $originalSubmittedAt = (string)($locked['submitted_at'] ?? $savedAt);
            $auditBefore = [
                'submission' => $locked,
                'answers' => $wpdb->get_results($wpdb->prepare(
                    'SELECT question_key,question_label,value_text,value_number,value_json FROM ' . Forms::table_answers() . ' WHERE submission_id=%d ORDER BY id ASC',
                    $submissionId
                ), ARRAY_A) ?: [],
            ];
            $meta = DailySubmissionService::build_meta($baseMeta, $reportDay, $locked, $userId, $savedAt);
            $metaJson = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $updated = $wpdb->update(Forms::table_submissions(), [
                'binding_id' => $bindingId,
                'client_id' => (int)($context['client_id'] ?? 0),
                'project_id' => (int)($context['project_id'] ?? 0),
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
                'report_day' => $reportDay,
                'status' => 'submitted',
                'meta_json' => $metaJson,
            ], ['id' => $submissionId], ['%d','%d','%d','%d','%d','%d','%s','%s','%s'], ['%d']);
            if ($updated === false) {
                $dbError = (string)$wpdb->last_error;
                self::rollback();
                return self::databaseError('submission_update_failed', $dbError, $reportDay);
            }
            if ($wpdb->delete(Forms::table_answers(), ['submission_id' => $submissionId], ['%d']) === false) {
                $dbError = (string)$wpdb->last_error;
                self::rollback();
                return self::error('answer_reset_failed', $dbError ?: 'Could not replace submission answers.', 500, [
                    'db_error' => $dbError,
                ]);
            }
        } else {
            $reportDayValue = (int)($context['location_id'] ?? 0) > 0 ? $reportDay : null;
            $inserted = $wpdb->insert(Forms::table_submissions(), [
                'form_id' => $formId,
                'binding_id' => $bindingId,
                'client_id' => (int)($context['client_id'] ?? 0),
                'project_id' => (int)($context['project_id'] ?? 0),
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
                'user_id' => $userId,
                'submitted_at' => $savedAt,
                'report_day' => $reportDayValue,
                'status' => 'submitted',
                'meta_json' => $metaJson,
            ], ['%d','%d','%d','%d','%d','%d','%d','%d','%s','%s','%s','%s']);
            if ($inserted === false) {
                $dbError = (string)$wpdb->last_error;
                self::rollback();
                return self::databaseError('submission_insert_failed', $dbError, $reportDay);
            }
            $submissionId = (int)$wpdb->insert_id;
            if (isset($meta['daily_report']) && is_array($meta['daily_report'])) {
                $meta['daily_report']['original_submission_id'] = $submissionId;
            }
            $metaJson = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($wpdb->update(Forms::table_submissions(), ['meta_json' => $metaJson], ['id' => $submissionId], ['%s'], ['%d']) === false) {
                $dbError = (string)$wpdb->last_error;
                self::rollback();
                return self::error('submission_finalize_failed', $dbError ?: 'Could not finalize submission metadata.', 500, [
                    'db_error' => $dbError,
                ]);
            }
        }

        foreach ($answers as $key => $item) {
            if (!is_array($item)) continue;
            $questionKey = sanitize_key((string)$key);
            if ($questionKey === '') continue;
            $type = sanitize_key((string)($item['type'] ?? 'text')) ?: 'text';
            $stored = self::storage($item['value'] ?? null, $type, $channel);
            $ok = $wpdb->insert(Forms::table_answers(), [
                'submission_id' => $submissionId,
                'question_key' => $questionKey,
                'question_label' => sanitize_text_field((string)($item['label'] ?? $questionKey)),
                'value_text' => $stored['text'],
                'value_number' => $stored['number'],
                'value_json' => $stored['json'],
                'created_at' => $savedAt,
            ], ['%d','%s','%s','%s','%f','%s','%s']);
            if ($ok === false) {
                $dbError = (string)$wpdb->last_error;
                self::rollback();
                return self::error('answer_save_failed', $dbError ?: 'Could not save an answer.', 500, [
                    'db_error' => $dbError,
                    'question_key' => $questionKey,
                    'is_update' => $isUpdate,
                ]);
            }
        }

        $committed = false;
        if ($commitAfterPersist) {
            if ($wpdb->query('COMMIT') === false) {
                self::rollback();
                return self::error('transaction_commit_failed', $wpdb->last_error ?: 'Could not confirm submission.', 500);
            }
            $committed = true;
        }

        return [
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'channel' => $channel,
            'submission_id' => $submissionId,
            'original_submitted_at' => $originalSubmittedAt,
            'saved_at' => $savedAt,
            'report_day' => $reportDay,
            'is_update' => $isUpdate,
            'audit_before' => $auditBefore,
            'meta' => $meta,
            'meta_json' => $metaJson,
            'committed' => $committed,
            'transaction_open' => !$committed,
            'owner_user_id' => $ownerUserId,
        ];
    }

    public static function buildSubmissionPayload(array $state): array {
        $context = is_array($state['context'] ?? null) ? $state['context'] : [];
        $submissionId = absint($state['submission_id'] ?? 0);
        return [
            'id' => $submissionId,
            'submission_id' => $submissionId,
            'form_id' => absint($state['form_id'] ?? 0),
            'binding_id' => absint($state['binding_id'] ?? 0),
            'client_id' => (int)($context['client_id'] ?? 0),
            'project_id' => (int)($context['project_id'] ?? 0),
            'route_id' => (int)($context['route_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
            'user_id' => absint($state['user_id'] ?? 0),
            'submitted_at' => sanitize_text_field((string)($state['original_submitted_at'] ?? $state['saved_at'] ?? '')),
            'saved_at' => sanitize_text_field((string)($state['saved_at'] ?? '')),
            'report_day' => sanitize_text_field((string)($state['report_day'] ?? '')),
            'is_update' => !empty($state['is_update']),
        ];
    }

    public static function buildPipelineJob(array $state): array {
        $payload = self::buildSubmissionPayload($state);
        $context = is_array($state['context'] ?? null) ? $state['context'] : [];
        $answers = is_array($state['answers'] ?? null) ? $state['answers'] : [];
        $source = sanitize_key((string)($state['source'] ?? 'web')) ?: 'web';
        return [
            'submission_id' => (int)$payload['submission_id'],
            'form_id' => (int)$payload['form_id'],
            'is_update' => !empty($payload['is_update']),
            'source' => $source,
            'user_id' => (int)$payload['user_id'],
            'saved_at' => (string)$payload['saved_at'],
            'history_mode' => (string)($state['history_mode'] ?? 'deferred'),
            'has_cardex_override' => !empty($state['has_cardex_override']),
            'context' => $context,
            'submission_row' => array_merge($payload, [
                'owner_user_id' => absint($state['owner_user_id'] ?? 0),
                'submitted_at' => (string)$payload['saved_at'],
                'meta_json' => (string)($state['meta_json'] ?? ''),
            ]),
            'submission_payload' => $payload,
            'answers' => $answers,
            'audit_before' => $state['audit_before'] ?? null,
        ];
    }

    public static function descriptor(): array {
        return [
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'channels' => ['web', 'mobile'],
            'owns' => ['schema_build', 'durable_submission', 'answers', 'pipeline_envelope'],
            'excludes' => ['mail', 'analytics', 'azure', 'route_notifications'],
        ];
    }

    private static function storage($value, string $type, string $channel): array {
        $text = null;
        $number = null;
        $json = null;

        if (is_array($value) || is_object($value)) {
            $json = $channel === 'web'
                ? wp_json_encode($value, JSON_UNESCAPED_UNICODE)
                : wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // Preserve the certified channel storage semantics during this migration.
            if ($channel === 'web') $text = $json;
        } elseif (in_array($type, ['number', 'currency', 'percent'], true)) {
            if ($channel === 'mobile') {
                if (is_numeric($value)) {
                    $number = (float)$value;
                    $text = (string)$value;
                } else {
                    $text = (string)$value;
                }
            } elseif ($value !== '') {
                $number = (float)$value;
                $text = (string)$value;
            }
        } elseif ($type === 'checkbox') {
            $number = !empty($value) ? 1.0 : 0.0;
            $text = !empty($value) ? '1' : '0';
        } elseif ($channel === 'web') {
            $text = is_scalar($value) ? (string)$value : wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $text = (string)$value;
        }

        return ['text' => $text, 'number' => $number, 'json' => $json];
    }

    private static function databaseError(string $reason, string $dbError, string $reportDay): WP_Error {
        $duplicate = DailySubmissionService::is_duplicate_database_error($dbError);
        return self::error($duplicate ? 'daily_report_exists' : $reason, $dbError ?: 'Could not persist submission.', $duplicate ? 409 : 500, [
            'db_error' => $dbError,
            'report_day' => $reportDay,
            'duplicate' => $duplicate,
        ]);
    }

    private static function error(string $reason, string $message, int $status, array $data = []): WP_Error {
        $data['reason'] = $reason;
        $data['status'] = $status;
        $data['contract'] = self::CONTRACT;
        return new WP_Error('fieldflow_submission_application_' . sanitize_key($reason), $message, $data);
    }

    private static function rollback(): void {
        global $wpdb;
        try { $wpdb->query('ROLLBACK'); } catch (\Throwable $ignored) {}
    }
}
