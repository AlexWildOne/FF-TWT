<?php
namespace RoutesPro\Mobile;

use WP_Error;
use RoutesPro\Forms\AnswerSemantics;
use RoutesPro\Forms\BriefingFormBridge;
use RoutesPro\Forms\ContextQuestions;
use RoutesPro\Forms\ConditionalFlow;
use RoutesPro\Forms\DailySubmissionService;
use RoutesPro\Forms\EvidenceFlow;
use RoutesPro\Forms\Forms;
use RoutesPro\Forms\ProductCardex;
use RoutesPro\Forms\RecordService;
use RoutesPro\Forms\SubmissionContext;
use RoutesPro\Forms\SubmissionPipeline;
use RoutesPro\Forms\SubmissionApplicationService;
use RoutesPro\Forms\SubmissionSideEffectDispatcher;
use RoutesPro\Forms\SubmissionValidation;
use RoutesPro\Forms\SpecializedValidation;
use RoutesPro\Services\FormVisitCompletionService;

if (!defined('ABSPATH')) exit;

class SubmissionService {
    public static function submit(array $input, ?int $user_id = null) {
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        $form_id = absint($input['form_id'] ?? 0);
        $binding_id = absint($input['binding_id'] ?? 0);
        $form = Forms::get_form($form_id);
        if (!$form || (string)($form['status'] ?? '') !== 'active') {
            return new WP_Error('fieldflow_mobile_form_not_found', 'Form not found or inactive.', ['status' => 404]);
        }

        $normalized = SubmissionContext::normalize_for_submission([
            'client_id' => absint($input['client_id'] ?? 0),
            'project_id' => absint($input['project_id'] ?? 0),
            'route_id' => absint($input['route_id'] ?? 0),
            'route_stop_id' => absint($input['route_stop_id'] ?? $input['stop_id'] ?? 0),
            'location_id' => absint($input['location_id'] ?? 0),
            'context_date' => sanitize_text_field((string)($input['report_day'] ?? $input['context_date'] ?? '')),
        ], $form_id, $binding_id, $user_id);
        if (is_wp_error($normalized)) return $normalized;

        $context = (array)($normalized['context'] ?? []);
        $binding = is_array($normalized['binding'] ?? null) ? $normalized['binding'] : null;
        $binding_id = (int)($binding['id'] ?? $binding_id);
        $owner_user_id = (int)($normalized['owner_user_id'] ?? 0);
        $report_day = DailySubmissionService::normalise_report_day(
            $normalized['route_date'] ?? $input['report_day'] ?? current_time('Y-m-d')
        );
        $submit_context = [
            'client_id' => (int)($context['client_id'] ?? 0),
            'project_id' => (int)($context['project_id'] ?? 0),
            'route_id' => (int)($context['route_id'] ?? 0),
            'stop_id' => (int)($context['route_stop_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
            'date' => $report_day,
            'route_date' => $report_day,
            'form_id' => $form_id,
        ];

        $schema = \RoutesPro\Forms\SubmissionApplicationService::buildSchema($form, $submit_context);
        if (empty($schema['questions'])) return new WP_Error('fieldflow_mobile_empty_schema', 'The form has no questions.', ['status' => 422]);

        $base_revision = sanitize_text_field((string)($input['base_revision'] ?? ''));
        $server_schema_hash = Forms::schema_hash($schema);
        $server_revision = substr($server_schema_hash, 0, 20);
        $server_form_version = Forms::definition_version($form);
        $client_schema_hash = sanitize_text_field((string)($input['schema_hash'] ?? ''));
        $provided = self::answers_map($input['answers'] ?? []);
        $offline_schema_rebased = false;
        $schema_changed = ($base_revision !== '' && !hash_equals($base_revision, $server_revision))
            || ($client_schema_hash !== '' && !hash_equals($client_schema_hash, $server_schema_hash));
        if ($schema_changed) {
            $current_keys = [];
            foreach ((array)($schema['questions'] ?? []) as $question) {
                if (!is_array($question)) continue;
                $key = sanitize_key((string)($question['key'] ?? ''));
                if ($key !== '') $current_keys[$key] = true;
            }
            $meaningful = static function($value) use (&$meaningful): bool {
                if ($value === null || $value === '' || $value === false) return false;
                if (is_array($value)) {
                    foreach ($value as $part) if ($meaningful($part)) return true;
                    return false;
                }
                return true;
            };
            $answered_keys = [];
            foreach ($provided as $provided_key => $provided_value) {
                if ($meaningful($provided_value)) $answered_keys[] = $provided_key;
            }
            $unknown_keys = array_values(array_diff($answered_keys, array_keys($current_keys)));
            if ($unknown_keys) {
                return new WP_Error('fieldflow_offline_conflict', 'The form changed while the device was offline and contains answers for removed or renamed questions.', [
                    'status' => 409,
                    'conflict' => [
                        'type' => 'form_schema_changed',
                        'entity_type' => 'form',
                        'entity_id' => $form_id,
                        'base_revision' => $base_revision,
                        'server_revision' => $server_revision,
                        'client_schema_hash' => $client_schema_hash,
                        'server_schema_hash' => $server_schema_hash,
                        'unknown_question_keys' => array_slice($unknown_keys, 0, 30),
                    ],
                ]);
            }
            $offline_schema_rebased = true;
        }

        $conditional_visibility = class_exists(ConditionalFlow::class)
            ? (array)(ConditionalFlow::payload($schema, $provided)['initial_visibility'] ?? [])
            : [];
        $daily_existing = DailySubmissionService::find_existing($form_id, (int)($context['location_id'] ?? 0), $report_day);
        $requested_existing_id = absint($input['existing_submission_id'] ?? 0);
        $requested_edit = !empty($input['edit_existing']);
        $is_update = false;
        if ($daily_existing) {
            $editable = DailySubmissionService::can_edit($daily_existing, $user_id);
            if (!$requested_edit || $requested_existing_id !== (int)$daily_existing['id']) {
                return new WP_Error('daily_report_exists', 'A report already exists for this store and day.', [
                    'status' => 409,
                    'submission_id' => (int)$daily_existing['id'],
                    'editable' => $editable,
                    'report_day' => $report_day,
                ]);
            }
            if (!$editable) return new WP_Error('daily_report_edit_forbidden', 'The existing report cannot be edited by this user.', ['status' => 403]);
            $context_conflict = DailySubmissionService::edit_context_conflict($daily_existing, $context);
            if ($context_conflict) {
                return new WP_Error('daily_report_context_conflict', 'This report belongs to another route or stop and must be edited from its original visit.', [
                    'status' => 409,
                    'submission_id' => (int)$daily_existing['id'],
                    'report_day' => $report_day,
                    'conflict' => $context_conflict,
                    'original_context' => DailySubmissionService::original_context($daily_existing),
                ]);
            }
            $is_update = true;
        } elseif ($requested_edit || $requested_existing_id > 0) {
            return new WP_Error('daily_report_missing', 'The report selected for editing no longer exists.', ['status' => 409]);
        }

        $saas_client_id = (int)($context['client_id'] ?? 0);
        if ($saas_client_id > 0 && class_exists('\RoutesPro\SaaS\EntitlementService')) {
            if (!\RoutesPro\SaaS\EntitlementService::clientHas($saas_client_id, 'reporting.submit')) {
                return new WP_Error('fieldflow_plan_required', 'Report submission is not included in this client plan.', ['status' => 403, 'capability' => 'reporting.submit', 'client_id' => $saas_client_id]);
            }
            if (!$is_update && class_exists('\RoutesPro\SaaS\UsageService')) {
                $quota = \RoutesPro\SaaS\UsageService::canConsume($saas_client_id, 'submissions_monthly', 1);
                if (empty($quota['allowed'])) return \RoutesPro\SaaS\UsageService::quotaError($saas_client_id, 'submissions_monthly');
            }
        }

        // 9DS: edits replace all answers, so preserve the exact existing
        // submission's fronts if its Cardex becomes unavailable. Never copy
        // the previous visit or trust a stale mobile payload for this fallback.
        $prior_visit_answers = $is_update && $daily_existing
            ? DailySubmissionService::submission_prefill((int)$daily_existing['id'], $schema)
            : [];
        $answers = [];
        $cardex_overrides = [];
        foreach ((array)$schema['questions'] as $question) {
            if (!is_array($question)) continue;
            $key = sanitize_key((string)($question['key'] ?? ''));
            $type = sanitize_key((string)($question['type'] ?? 'text'));
            if ($key === '' || in_array($type, ['briefing_block', 'briefing_intro'], true)) continue;
            // 9U certification compatibility only (non-executable historical marker):
            // if ($type === 'product_matrix'
            // Keep the active 9DV condition below: both Fronts and Order Suggestion
            // must be validated for store/Cardex scope in web and mobile/offline.
            if (in_array($type, ['product_matrix','order_suggestion'], true)
                && class_exists(ProductCardex::class)
                && ProductCardex::question_uses_cardex($question)
                && !ProductCardex::question_is_available_for_context($question, $submit_context)) {
                if ($is_update && array_key_exists($key, $prior_visit_answers)) {
                    $answers[$key] = [
                        'type' => $type,
                        'value' => $prior_visit_answers[$key],
                        'label' => sanitize_text_field((string)($question['label'] ?? $key)),
                    ];
                }
                continue;
            }
            if (!SubmissionValidation::is_question_active($key, $conditional_visibility)) continue;

            $has_value = array_key_exists($key, $provided);
            $value = $has_value ? self::sanitize_value($provided[$key], $type) : null;
            if ($has_value && class_exists(AnswerSemantics::class)) {
                $semantic = AnswerSemantics::normalize($question, $value, true);
                if (empty($semantic['valid'])) {
                    return new WP_Error('fieldflow_mobile_invalid_answer', (string)($semantic['message'] ?? 'Invalid answer.'), [
                        'status' => 422,
                        'question_key' => $key,
                        'question_type' => $type,
                        'issue' => sanitize_key((string)($semantic['issue'] ?? 'invalid_answer')),
                        'semantic_contract' => AnswerSemantics::CONTRACT,
                    ]);
                }
                $value = $semantic['value'] ?? null;
            }
            if ($has_value && $type === 'product_matrix' && class_exists(ProductCardex::class) && is_array($value)) {
                $value = ProductCardex::normalise_fronts_submission_rows($value, $question, $submit_context);
            }
            if ($has_value && $type === 'order_suggestion' && class_exists(ProductCardex::class) && is_array($value)) {
                // 9DV: offline/native suggestions use the same server-owned
                // SKU authorisation and zero-confirmation rules as the web.
                $paired_key = sanitize_key((string)($question['_fieldflow_paired_fronts_key'] ?? ''));
                $fronts_present = $paired_key !== '' && isset($provided[$paired_key]) && is_array($provided[$paired_key]);
                $fronts = $fronts_present ? $provided[$paired_key] : [];
                if ($fronts_present && ProductCardex::question_uses_cardex($question)) {
                    $paired_valid = false;
                    foreach ((array)($schema['questions'] ?? []) as $paired_question) {
                        if (!is_array($paired_question) || sanitize_key((string)($paired_question['key'] ?? '')) !== $paired_key) continue;
                        $paired_valid = sanitize_key((string)($paired_question['type'] ?? '')) === 'product_matrix'
                            && ProductCardex::question_cardex_id($paired_question, $submit_context) === ProductCardex::question_cardex_id($question, $submit_context)
                            && ProductCardex::question_is_available_for_context($paired_question, $submit_context);
                        break;
                    }
                    if (!$paired_valid) { $fronts_present = false; $fronts = []; }
                }
                $value = ProductCardex::normalise_order_submission_rows($value, $question,
                    $submit_context, is_array($fronts) ? $fronts : [], $fronts_present);
            }
            $is_cardex_matrix = $has_value
                && $type === 'product_matrix'
                && class_exists(ProductCardex::class)
                && ProductCardex::question_uses_cardex($question);
            if (SubmissionValidation::required_missing($question, $value, $conditional_visibility)) {
                $label = sanitize_text_field((string)($question['label'] ?? $key));
                return new WP_Error('fieldflow_mobile_required_field', 'Falta responder ao campo obrigatório: ' . ($label !== '' ? $label : $key) . '.', ['status' => 422, 'question_key' => $key, 'question_label' => $label]);
            }
            if (!$is_cardex_matrix && !SubmissionValidation::should_persist($value, $type, $has_value)) continue;
            $answers[$key] = [
                'type' => $type,
                'value' => $is_cardex_matrix && !is_array($value) ? [] : $value,
                'label' => sanitize_text_field((string)($question['label'] ?? $key)),
            ];
            if ($is_cardex_matrix) {
                $cardex_id = ProductCardex::question_cardex_id($question, $submit_context);
                if ($cardex_id) $cardex_overrides[$cardex_id] = ['question' => $question, 'rows' => is_array($value) ? $value : []];
            }
        }

        if (class_exists(EvidenceFlow::class)) {
            $evidence_error = EvidenceFlow::validate_answers($schema, $answers);
            if (is_wp_error($evidence_error)) return $evidence_error;
        }

        if (class_exists(SpecializedValidation::class)) {
            $specialized_error = SpecializedValidation::validate_schema($schema, $answers, $conditional_visibility);
            if (is_wp_error($specialized_error)) return $specialized_error;
        }

        $saved_at = current_time('mysql');
        $base_meta = [
            'source' => 'fieldflow_mobile_api',
            'context_model' => 'record_version_v1',
            'route_date' => $report_day,
            'schema_revision' => $server_revision,
            'form_definition' => [
                'server_version' => $server_form_version,
                'client_version' => sanitize_text_field((string)($input['form_version'] ?? $input['definition_version'] ?? '')),
                'server_schema_hash' => $server_schema_hash,
                'client_schema_hash' => sanitize_text_field((string)($input['schema_hash'] ?? '')),
                'downloaded_at' => sanitize_text_field((string)($input['form_downloaded_at'] ?? $input['downloaded_at'] ?? '')),
                'offline_replay' => !empty($input['mutation_id']) ? 1 : 0,
                'offline_schema_rebased' => $offline_schema_rebased ? 1 : 0,
            ],
            'submission_context' => [
                'client_id' => (int)($context['client_id'] ?? 0),
                'project_id' => (int)($context['project_id'] ?? 0),
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
            ],
            'mobile' => [
                'device_id' => sanitize_text_field((string)((Auth::current_session()['device_id'] ?? ''))),
                'app_version' => sanitize_text_field((string)((Auth::current_session()['app_version'] ?? ''))),
                'mutation_id' => sanitize_text_field((string)($input['mutation_id'] ?? '')),
            ],
        ];
        if (class_exists(ConditionalFlow::class)) {
            $base_meta['conditional_flow'] = ConditionalFlow::submission_meta($schema, $conditional_visibility);
        }
        $snapshot = self::location_snapshot($context);
        if ($snapshot) $base_meta['location_snapshot'] = $snapshot;
        $pipeline_state = class_exists(SubmissionPipeline::class)
            ? SubmissionPipeline::requestState('mobile', array_merge($context, ['user_id' => $user_id]))
            : ['owner' => 'legacy'];

        $application_result = SubmissionApplicationService::persist([
            'form_id' => $form_id,
            'binding_id' => $binding_id,
            'context' => $context,
            'user_id' => $user_id,
            'owner_user_id' => $owner_user_id,
            'report_day' => $report_day,
            'saved_at' => $saved_at,
            'base_meta' => $base_meta,
            'daily_existing' => $daily_existing,
            'is_update' => $is_update,
            'answers' => $answers,
            'channel' => 'mobile',
            'commit_after_persist' => (($pipeline_state['owner'] ?? 'legacy') === 'pipeline'),
        ]);
        if (is_wp_error($application_result)) {
            return self::application_service_error($application_result, $is_update);
        }

        $submission_id = (int)($application_result['submission_id'] ?? 0);
        $original_submitted_at = (string)($application_result['original_submitted_at'] ?? $saved_at);
        $audit_before = $application_result['audit_before'] ?? null;
        $meta = is_array($application_result['meta'] ?? null) ? $application_result['meta'] : $base_meta;
        $meta_json = (string)($application_result['meta_json'] ?? wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if (($pipeline_state['owner'] ?? 'legacy') === 'pipeline') {
            // The application service already committed the durable submission
            // for pipeline-owned mobile requests.
            self::observe_media_non_blocking($submission_id, $answers, $context, $user_id);

            $application_state = [
                'submission_id' => $submission_id,
                'form_id' => $form_id,
                'binding_id' => $binding_id,
                'context' => $context,
                'user_id' => $user_id,
                'owner_user_id' => $owner_user_id,
                'original_submitted_at' => $original_submitted_at,
                'saved_at' => $saved_at,
                'report_day' => $report_day,
                'is_update' => $is_update,
                'meta_json' => $meta_json,
                'answers' => $answers,
                'audit_before' => $audit_before,
                'has_cardex_override' => !empty($cardex_overrides),
                'source' => 'mobile',
                'history_mode' => !empty($pipeline_state['inline_mobile_history']) ? 'inline' : 'deferred',
            ];
            $submission_payload = SubmissionApplicationService::buildSubmissionPayload($application_state);
            $pipeline_job = SubmissionApplicationService::buildPipelineJob($application_state);
            try {
                $pipeline_result = SubmissionPipeline::afterCommit($pipeline_job);
            } catch (\Throwable $pipeline_error) {
                if (class_exists('\\RoutesPro\\Support\\Logger')) {
                    \RoutesPro\Support\Logger::captureThrowable('Falha protegida na Submission Pipeline mobile depois do commit.', $pipeline_error, [
                        'context_key' => 'submission_pipeline_mobile_fallback',
                        'submission_id' => $submission_id,
                        'route_id' => (int)($context['route_id'] ?? 0),
                    ]);
                }
                $fallback_completion = [
                    'applied' => false,
                    'reason' => 'pipeline_fallback_after_commit',
                    'route_id' => (int)($context['route_id'] ?? 0),
                    'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                    'location_id' => (int)($context['location_id'] ?? 0),
                    'route_completed_now' => false,
                ];
                try {
                    if (!wp_next_scheduled('fieldflow_reconcile_submission_history', [$submission_id])) {
                        wp_schedule_single_event(time() + 1, 'fieldflow_reconcile_submission_history', [$submission_id]);
                    }
                    if ($cardex_overrides && !wp_next_scheduled('fieldflow_reconcile_submission_cardex', [$submission_id])) {
                        wp_schedule_single_event(time() + 2, 'fieldflow_reconcile_submission_cardex', [$submission_id]);
                    }
                    if (!empty($context['route_id']) && !wp_next_scheduled('fieldflow_reconcile_route_after_submission', [(int)$context['route_id']])) {
                        wp_schedule_single_event(time() + 5, 'fieldflow_reconcile_route_after_submission', [(int)$context['route_id']]);
                    }
                    $fallback_integrations = [$submission_id, $is_update ? 1 : 0, wp_json_encode($fallback_completion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                    if (!wp_next_scheduled('fieldflow_submission_post_commit', $fallback_integrations)) {
                        wp_schedule_single_event(time() + 5, 'fieldflow_submission_post_commit', $fallback_integrations);
                    }
                } catch (\Throwable $ignored) {}
                $pipeline_result = [
                    'record' => ['record_id' => 0, 'version_id' => 0, 'version_no' => 0, 'deferred' => true],
                    'visit_completion' => $fallback_completion,
                    'warnings' => ['A sincronização complementar ficou agendada depois da gravação do reporte.'],
                ];
            }
            $pipeline_record = is_array($pipeline_result['record'] ?? null) ? $pipeline_result['record'] : [];
            $completion = is_array($pipeline_result['visit_completion'] ?? null)
                ? $pipeline_result['visit_completion']
                : [
                    'applied' => false,
                    'reason' => 'deferred_after_submission',
                    'route_id' => (int)($context['route_id'] ?? 0),
                    'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                    'location_id' => (int)($context['location_id'] ?? 0),
                    'route_completed_now' => false,
                ];
            return [
                'submission_id' => $submission_id,
                'record_id' => (int)($pipeline_record['record_id'] ?? 0),
                'record_version_id' => (int)($pipeline_record['version_id'] ?? 0),
                'visit_completion' => $completion,
                'updated_existing' => $is_update,
                'report_day' => $report_day,
                'submitted_at' => $original_submitted_at,
                'saved_at' => $saved_at,
            ];
        }

        $submit_context['cardex_source'] = 'mobile_api';
        foreach ($cardex_overrides as $payload) {
            if (!ProductCardex::sync_store_override_from_rows((array)$payload['question'], $submit_context, (array)$payload['rows'], $user_id)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_mobile_cardex_override_failed', 'Could not update the store cardex.', ['status' => 500]);
            }
        }

        $synced = RecordService::sync_submission($submission_id, [
            'form_id' => $form_id,
            'binding_id' => $binding_id,
            'client_id' => (int)($context['client_id'] ?? 0),
            'project_id' => (int)($context['project_id'] ?? 0),
            'route_id' => (int)($context['route_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
            'user_id' => $user_id,
            'owner_user_id' => $owner_user_id,
            'submitted_at' => $saved_at,
            'meta_json' => $meta_json,
        ], $answers);
        if (is_wp_error($synced)) {
            $wpdb->query('ROLLBACK');
            return $synced;
        }
        if ($wpdb->update(Forms::table_submissions(), [
            'record_id' => (int)($synced['record_id'] ?? 0),
            'record_version_id' => (int)($synced['version_id'] ?? 0),
            'meta_json' => $meta_json,
        ], ['id' => $submission_id], ['%d','%d','%s'], ['%d']) === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('fieldflow_mobile_submission_record_link_failed', $wpdb->last_error ?: 'Could not link the record version.', ['status' => 500]);
        }

        $completion = FormVisitCompletionService::complete_from_submission($submission_id, [
            'form_id' => $form_id,
            'route_id' => (int)($context['route_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
            'user_id' => $user_id,
            'submitted_at' => $saved_at,
        ]);
        if (is_wp_error($completion)) {
            $wpdb->query('ROLLBACK');
            return $completion;
        }
        $wpdb->query('COMMIT');

        self::observe_media_non_blocking($submission_id, $answers, $context, $user_id);

        $payload = [
            'submission_id' => $submission_id,
            'record_id' => (int)($synced['record_id'] ?? 0),
            'record_version_id' => (int)($synced['version_id'] ?? 0),
            'visit_completion' => $completion,
            'updated_existing' => $is_update,
            'report_day' => $report_day,
            'submitted_at' => $original_submitted_at,
            'saved_at' => $saved_at,
        ];
        if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
            try {
                $contract = \RoutesPro\Core\DataContract::submission($submission_id, $payload, $answers);
                \RoutesPro\Core\AuditLog::record('submission.persisted', 'submission', $submission_id, $audit_before, $contract, [
                    'client_id' => (int)($context['client_id'] ?? 0),
                    'project_id' => (int)($context['project_id'] ?? 0),
                    'route_id' => (int)($context['route_id'] ?? 0),
                    'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                    'location_id' => (int)($context['location_id'] ?? 0),
                    'submission_id' => $submission_id,
                    'actor_user_id' => $user_id,
                    'source' => 'mobile',
                ], ['is_update' => $is_update, 'record_id' => (int)($synced['record_id'] ?? 0), 'record_version_id' => (int)($synced['version_id'] ?? 0)]);
            } catch (\Throwable $audit_error) {
                self::log_non_blocking('Mobile submission audit failed after commit.', $audit_error, $submission_id, $context);
            }
        }
        try {
            SubmissionSideEffectDispatcher::dispatchFromSubmission(
                $submission_id,
                $is_update,
                wp_json_encode($completion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'mobile_legacy_post_commit'
            );
        } catch (\Throwable $dispatch_error) {
            self::log_non_blocking('Mobile submission side-effect dispatcher failed after commit.', $dispatch_error, $submission_id, $context);
            $integration_args = [$submission_id, $is_update ? 1 : 0, wp_json_encode($completion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            if (!wp_next_scheduled('fieldflow_submission_post_commit', $integration_args)) {
                wp_schedule_single_event(time() + 5, 'fieldflow_submission_post_commit', $integration_args);
            }
        }
        return $payload;
    }

    private static function observe_media_non_blocking(int $submission_id, array $answers, array $context, int $user_id): void {
        if (!class_exists('\\RoutesPro\\Security\\MediaRegistry')) return;
        try {
            \RoutesPro\Security\MediaRegistry::registerSubmissionAnswers($submission_id, $answers, [
                'client_id' => (int)($context['client_id'] ?? 0),
                'project_id' => (int)($context['project_id'] ?? 0),
                'route_id' => (int)($context['route_id'] ?? 0),
                'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
                'location_id' => (int)($context['location_id'] ?? 0),
            ], $user_id);
        } catch (\Throwable $media_error) {
            self::log_non_blocking('Mobile media registry failed after durable submission.', $media_error, $submission_id, $context);
        }
    }

    private static function log_non_blocking(string $message, \Throwable $error, int $submission_id, array $context): void {
        if (!class_exists('\\RoutesPro\\Support\\Logger')) return;
        \RoutesPro\Support\Logger::captureThrowable($message, $error, [
            'context_key' => 'mobile_submission_post_commit',
            'submission_id' => $submission_id,
            'route_id' => (int)($context['route_id'] ?? 0),
            'route_stop_id' => (int)($context['route_stop_id'] ?? 0),
            'location_id' => (int)($context['location_id'] ?? 0),
        ]);
    }

    private static function application_service_error(\WP_Error $error, bool $is_update): \WP_Error {
        $data = is_array($error->get_error_data()) ? $error->get_error_data() : [];
        $reason = sanitize_key((string)($data['reason'] ?? ''));
        $status = max(400, min(599, (int)($data['status'] ?? 500)));
        $questionKey = sanitize_key((string)($data['question_key'] ?? ''));

        switch ($reason) {
            case 'daily_report_exists':
                return new \WP_Error('daily_report_exists', 'A report already exists for this store and day.', [
                    'status' => 409,
                    'report_day' => sanitize_text_field((string)($data['report_day'] ?? '')),
                ]);
            case 'daily_report_missing':
                return new \WP_Error('daily_report_missing', 'The report selected for editing no longer exists.', ['status' => 409]);
            case 'daily_report_edit_forbidden':
                return new \WP_Error('daily_report_edit_forbidden', 'The report cannot be edited.', ['status' => 403]);
            case 'daily_report_context_conflict':
                return new \WP_Error('daily_report_context_conflict', 'This report belongs to another route or stop and must be edited from its original visit.', array_merge($data, ['status' => 409]));
            case 'answer_save_failed':
                return new \WP_Error('fieldflow_mobile_answer_save_failed', $error->get_error_message() ?: 'Could not save an answer.', [
                    'status' => 500,
                    'question_key' => $questionKey,
                ]);
            case 'answer_reset_failed':
            case 'submission_update_failed':
            case 'submission_finalize_failed':
                return new \WP_Error('fieldflow_mobile_submission_update_failed', $error->get_error_message() ?: 'Could not update submission.', ['status' => $status]);
            case 'transaction_commit_failed':
                return new \WP_Error('fieldflow_mobile_submission_commit_failed', $error->get_error_message() ?: 'Could not confirm submission.', ['status' => 500]);
            case 'submission_insert_failed':
            case 'transaction_start_failed':
            case 'invalid_command':
            default:
                return new \WP_Error(
                    $is_update ? 'fieldflow_mobile_submission_update_failed' : 'fieldflow_mobile_submission_create_failed',
                    $error->get_error_message() ?: ($is_update ? 'Could not update submission.' : 'Could not create submission.'),
                    ['status' => $status]
                );
        }
    }

    private static function answers_map($raw): array {
        if (!is_array($raw)) return [];
        $out = [];
        $is_list = array_keys($raw) === range(0, count($raw) - 1);
        if ($is_list) {
            foreach ($raw as $item) {
                if (!is_array($item)) continue;
                $key = sanitize_key((string)($item['key'] ?? $item['question_key'] ?? ''));
                if ($key !== '') $out[$key] = $item['value'] ?? null;
            }
            return $out;
        }
        foreach ($raw as $key => $value) {
            $clean = sanitize_key((string)$key);
            if ($clean !== '') $out[$clean] = $value;
        }
        return $out;
    }

    private static function sanitize_value($value, string $type) {
        if ($type === 'order_suggestion' && is_array($value)) {
            return self::sanitize_order_suggestion_value($value);
        }
        if (in_array($type, ['number', 'currency', 'percent'], true)) {
            if ($value === '' || $value === null) return '';
            return sanitize_text_field((string)$value);
        }
        if ($type === 'checkbox') return $value;
        if (is_array($value)) return self::sanitize_array($value);
        if (in_array($type, ['textarea', 'long_text'], true)) return sanitize_textarea_field((string)$value);
        if ($type === 'url') return esc_url_raw((string)$value);
        return sanitize_text_field((string)$value);
    }

    private static function sanitize_order_suggestion_value(array $value): array {
        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) continue;
            $clean = self::sanitize_array($row);
            $qtyRaw = trim(str_replace(',', '.', (string)($clean['order_qty'] ?? $clean['boxes'] ?? $clean['qty'] ?? '')));
            $source = sanitize_key((string)($clean['quantity_source'] ?? $clean['order_source'] ?? ''));
            if (!in_array($source, ['auto', 'manual'], true)) $source = $qtyRaw !== '' && is_numeric($qtyRaw) ? 'manual' : '';
            $clean['quantity_source'] = $source;
            $rows[] = $clean;
        }
        return $rows;
    }

    private static function sanitize_array(array $value): array {
        $out = [];
        foreach ($value as $key => $item) {
            $clean_key = is_int($key) ? $key : sanitize_key((string)$key);
            if (is_array($item)) $out[$clean_key] = self::sanitize_array($item);
            elseif (is_bool($item) || is_int($item) || is_float($item) || $item === null) $out[$clean_key] = $item;
            else $out[$clean_key] = sanitize_textarea_field((string)$item);
        }
        return $out;
    }

    private static function location_snapshot(array $context): array {
        global $wpdb;
        $location_id = absint($context['location_id'] ?? 0);
        if (!$location_id) return [];
        $table = $wpdb->prefix . 'routespro_locations';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id,name,address,district,county,city,parish,postal_code,country,external_ref,lat,lng,place_id,source FROM {$table} WHERE id=%d LIMIT 1",
            $location_id
        ), ARRAY_A);
        if (!$row) return [];
        $row['location_id'] = (int)$row['id'];
        $row['route_id'] = absint($context['route_id'] ?? 0);
        $row['route_stop_id'] = absint($context['route_stop_id'] ?? 0);
        return $row;
    }
}
