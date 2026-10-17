<?php
namespace RoutesPro\Forms;

use RoutesPro\Support\StabilityFlags;

if (!defined('ABSPATH')) exit;

/**
 * Shared post-commit pipeline for web and mobile form submissions.
 *
 * Since 2.2.486, secondary integrations are fanned out by
 * SubmissionSideEffectDispatcher into isolated idempotent lanes.
 *
 * Persistence of the submission and its answers remains owned by the proven
 * channel handlers. This service starts only after COMMIT, so a secondary
 * failure can never roll back or hide field data that was already confirmed.
 */
class SubmissionPipeline {
    public const OPTION = 'fieldflow_submission_pipeline_rollout';
    public const ROLLBACK_OPTION = 'fieldflow_submission_pipeline_last_rollback';
    public const PIPELINE_VERSION = '1.1.0';
    public const BUILD = '2.2.485';

    public static function defaults(): array {
        return [
            'enabled' => false,
            'mode' => 'shadow',
            'channels' => ['web', 'mobile'],
            'user_ids' => [],
            'project_ids' => [],
            'diagnostics' => true,
            'inline_mobile_history' => true,
            'sample_percent' => 100,
        ];
    }

    public static function get(): array {
        $saved = get_option(self::OPTION, []);
        $settings = wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['mode'] = in_array((string)($settings['mode'] ?? ''), ['shadow', 'pilot'], true)
            ? (string)$settings['mode'] : 'shadow';
        $settings['channels'] = self::channels($settings['channels'] ?? []);
        $settings['user_ids'] = self::ids($settings['user_ids'] ?? []);
        $settings['project_ids'] = self::ids($settings['project_ids'] ?? []);
        $settings['diagnostics'] = !empty($settings['diagnostics']);
        $settings['inline_mobile_history'] = !empty($settings['inline_mobile_history']);
        $settings['sample_percent'] = max(1, min(100, (int)($settings['sample_percent'] ?? 100)));
        return $settings;
    }

    public static function save(array $input): array {
        $userIds = self::ids($input['user_ids'] ?? []);
        $mode = in_array((string)($input['mode'] ?? ''), ['shadow', 'pilot'], true)
            ? (string)$input['mode'] : 'shadow';
        $channels = self::channels($input['channels'] ?? []);

        // The pipeline never receives live ownership without an explicit user
        // allowlist and at least one selected channel.
        if ($mode === 'pilot' && (!$userIds || !$channels)) $mode = 'shadow';

        $settings = [
            'enabled' => !empty($input['enabled']),
            'mode' => $mode,
            'channels' => $channels ?: ['web', 'mobile'],
            'user_ids' => $userIds,
            'project_ids' => self::ids($input['project_ids'] ?? []),
            'diagnostics' => !empty($input['diagnostics']),
            'inline_mobile_history' => !empty($input['inline_mobile_history']),
            'sample_percent' => max(1, min(100, (int)($input['sample_percent'] ?? 100))),
        ];
        update_option(self::OPTION, $settings, false);

        $flags = StabilityFlags::all();
        $flags['new_submission_engine'] = (bool)$settings['enabled'];
        update_option(StabilityFlags::OPTION, $flags, false);
        return $settings;
    }

    public static function emergencyRollback(int $actorId = 0): array {
        $previous = self::get();
        $settings = $previous;
        $settings['enabled'] = false;
        $settings['mode'] = 'shadow';
        update_option(self::OPTION, $settings, false);

        $flags = StabilityFlags::all();
        $flags['new_submission_engine'] = false;
        update_option(StabilityFlags::OPTION, $flags, false);

        $record = [
            'at' => gmdate('c'),
            'actor_user_id' => max(0, $actorId),
            'previous_mode' => (string)($previous['mode'] ?? 'shadow'),
            'previous_enabled' => !empty($previous['enabled']),
            'channels' => array_values((array)($previous['channels'] ?? [])),
            'user_allowlist_count' => count($previous['user_ids'] ?? []),
            'project_allowlist_count' => count($previous['project_ids'] ?? []),
        ];
        update_option(self::ROLLBACK_OPTION, $record, false);
        return $record;
    }

    public static function requestState(string $channel, array $context = []): array {
        $settings = self::get();
        $channel = in_array($channel, ['web', 'mobile'], true) ? $channel : 'web';
        $userId = absint($context['user_id'] ?? get_current_user_id());
        $projectId = absint($context['project_id'] ?? 0);
        $eligible = false;
        $reason = 'feature_disabled';

        if (!$settings['enabled'] || !StabilityFlags::enabled('new_submission_engine')) {
            $reason = 'feature_disabled';
        } elseif ($userId <= 0) {
            $reason = 'anonymous_user';
        } elseif (!in_array($channel, $settings['channels'], true)) {
            $reason = 'channel_not_selected';
        } elseif ($settings['mode'] === 'pilot' && !$settings['user_ids']) {
            $reason = 'pilot_requires_user_allowlist';
        } elseif ($settings['user_ids'] && !in_array($userId, $settings['user_ids'], true)) {
            $reason = 'user_not_allowlisted';
        } elseif (!$settings['user_ids'] && !current_user_can('routespro_manage')) {
            $reason = 'internal_users_only';
        } elseif ($settings['project_ids'] && !in_array($projectId, $settings['project_ids'], true)) {
            $reason = 'project_not_allowlisted';
        } else {
            $eligible = self::sampled($userId, $projectId, $channel, $settings['sample_percent']);
            $reason = $eligible ? 'eligible' : 'outside_sample';
        }

        $mode = $eligible ? $settings['mode'] : 'legacy';
        return [
            'enabled' => $eligible,
            'mode' => $mode,
            'owner' => $mode === 'pilot' ? 'pipeline' : 'legacy',
            'channel' => $channel,
            'reason' => $reason,
            'pipeline_version' => self::PIPELINE_VERSION,
            'build' => self::BUILD,
            'diagnostics' => $eligible && $settings['diagnostics'],
            'inline_mobile_history' => (bool)$settings['inline_mobile_history'],
            'automatic_fallback' => true,
        ];
    }

    /**
     * Runs only after the durable submission COMMIT.
     * Always returns a success-safe result. Failures become warnings and jobs.
     */
    public static function afterCommit(array $job): array {
        $startedAt = microtime(true);
        $submissionId = absint($job['submission_id'] ?? 0);
        $context = self::context($job);
        $answers = is_array($job['answers'] ?? null) ? $job['answers'] : [];
        $submissionRow = is_array($job['submission_row'] ?? null) ? $job['submission_row'] : [];
        $warnings = [];
        $record = [
            'record_id' => absint($job['record_id'] ?? 0),
            'version_id' => absint($job['record_version_id'] ?? 0),
            'version_no' => 0,
            'deferred' => false,
        ];

        if ($submissionId <= 0) {
            return [
                'record' => $record,
                'visit_completion' => self::pendingCompletion($context, 'invalid_submission'),
                'warnings' => ['A submissão foi guardada, mas a reconciliação recebeu um identificador inválido.'],
                'processing_ms' => 0,
            ];
        }

        if (!empty($job['is_update']) && !self::prepareUpdateReplay($submissionId)) {
            $warnings[] = 'A atualização ficou guardada, mas a ligação ao novo histórico analítico aguarda reconciliação.';
        }

        $historyMode = (string)($job['history_mode'] ?? 'deferred');
        if ($historyMode === 'inline') {
            try {
                $record = self::syncHistory($submissionId, $submissionRow, $answers);
                if (!empty($record['deferred'])) {
                    $warnings[] = 'O histórico analítico da loja ficou pendente de reconciliação.';
                }
            } catch (\Throwable $error) {
                self::logFailure('Falha não bloqueante no histórico analítico pós-submissão.', $error, $context, $submissionId);
                $record['deferred'] = true;
                if (!self::schedule('fieldflow_reconcile_submission_history', [$submissionId], 1)) {
                    $warnings[] = 'O histórico analítico da loja ficou pendente de reconciliação.';
                }
            }
        } else {
            $record['deferred'] = true;
            if (!self::schedule('fieldflow_reconcile_submission_history', [$submissionId], 1)) {
                $warnings[] = 'O histórico analítico da loja ficou pendente de reconciliação.';
            }
        }

        if (!empty($job['has_cardex_override'])
            && !self::schedule('fieldflow_reconcile_submission_cardex', [$submissionId], 2)) {
            $warnings[] = 'O sortido específico da loja ficou pendente de reconciliação.';
        }

        $completion = self::pendingCompletion($context, 'pending_after_commit');
        try {
            $completion = \RoutesPro\Services\FormVisitCompletionService::complete_from_submission($submissionId, [
                'form_id' => absint($job['form_id'] ?? ($submissionRow['form_id'] ?? 0)),
                'route_id' => $context['route_id'],
                'route_stop_id' => $context['route_stop_id'],
                'location_id' => $context['location_id'],
                'user_id' => absint($job['user_id'] ?? ($submissionRow['user_id'] ?? 0)),
                'submitted_at' => sanitize_text_field((string)($job['saved_at'] ?? ($submissionRow['submitted_at'] ?? ''))),
            ]);
            if (is_wp_error($completion)) {
                throw new \RuntimeException($completion->get_error_code() . ': ' . $completion->get_error_message());
            }
        } catch (\Throwable $error) {
            self::logFailure('Falha não bloqueante na conclusão da visita pós-submissão.', $error, $context, $submissionId);
            $completion = self::pendingCompletion($context, 'deferred_after_submission');
            $warnings[] = 'A visita será reconciliada com a rota depois da gravação do reporte.';
        }

        if (empty($completion['applied']) && $context['route_id'] > 0
            && !self::schedule('fieldflow_reconcile_route_after_submission', [$context['route_id']], 5)) {
            $warnings[] = 'A reconciliação da rota ficou pendente.';
        }

        $integrationArgs = [
            $submissionId,
            !empty($job['is_update']) ? 1 : 0,
            wp_json_encode($completion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        if (!self::schedule('fieldflow_submission_post_commit', $integrationArgs, 5)) {
            $warnings[] = 'As automações posteriores ao reporte ficaram pendentes de execução.';
        }

        self::audit($submissionId, $job, $record, $answers, $context);
        $warnings = array_values(array_unique(array_filter(array_map('sanitize_text_field', $warnings))));
        if ($warnings) self::diagnoseWarnings($submissionId, $job, $context, $warnings);
        $processingMs = max(0, (int)round((microtime(true) - $startedAt) * 1000));
        if ($processingMs >= 2000) self::diagnoseSlowRun($submissionId, $job, $context, $processingMs);

        return [
            'record' => $record,
            'visit_completion' => is_array($completion) ? $completion : self::pendingCompletion($context, 'invalid_completion'),
            'warnings' => $warnings,
            'processing_ms' => $processingMs,
        ];
    }

    public static function publicState(): array {
        $settings = self::get();
        $rollback = get_option(self::ROLLBACK_OPTION, []);
        return [
            'pipeline_version' => self::PIPELINE_VERSION,
            'build' => self::BUILD,
            'enabled' => (bool)$settings['enabled'],
            'mode' => (string)$settings['mode'],
            'channels' => array_values($settings['channels']),
            'owner' => $settings['enabled'] && StabilityFlags::enabled('new_submission_engine') && $settings['mode'] === 'pilot'
                ? 'pipeline' : 'legacy',
            'automatic_fallback' => true,
            'pilot_requires_explicit_users' => true,
            'user_allowlist_count' => count($settings['user_ids']),
            'project_allowlist_count' => count($settings['project_ids']),
            'inline_mobile_history' => (bool)$settings['inline_mobile_history'],
            'diagnostics' => (bool)$settings['diagnostics'],
            'slow_run_threshold_ms' => 2000,
            'sample_percent' => (int)$settings['sample_percent'],
            'last_rollback' => is_array($rollback) ? $rollback : [],
        ];
    }

    private static function syncHistory(int $submissionId, array $submissionRow, array $answers): array {
        global $wpdb;
        if (!$submissionRow) {
            $submissionRow = $wpdb->get_row($wpdb->prepare(
                'SELECT s.*,r.owner_user_id FROM ' . Forms::table_submissions() . ' s LEFT JOIN ' . $wpdb->prefix . 'routespro_routes r ON r.id=s.route_id WHERE s.id=%d LIMIT 1',
                $submissionId
            ), ARRAY_A) ?: [];
        }
        if (!$submissionRow) throw new \RuntimeException('Submissão não encontrada para sincronizar o histórico.');

        $synced = RecordService::sync_submission($submissionId, $submissionRow, $answers);
        if (is_wp_error($synced)) {
            throw new \RuntimeException($synced->get_error_code() . ': ' . $synced->get_error_message());
        }
        $updated = $wpdb->update(Forms::table_submissions(), [
            'record_id' => absint($synced['record_id'] ?? 0),
            'record_version_id' => absint($synced['version_id'] ?? 0),
        ], ['id' => $submissionId], ['%d', '%d'], ['%d']);
        if ($updated === false) throw new \RuntimeException($wpdb->last_error ?: 'Falha ao ligar o histórico analítico à submissão.');
        $synced['deferred'] = false;
        return $synced;
    }

    /**
     * Daily edits reuse the same submission ID. Previous done markers and the
     * old record-version link must not make the asynchronous workers treat the
     * edited answers as already reconciled.
     */
    public static function prepareUpdateReplay(int $submissionId): bool {
        global $wpdb;
        foreach ([
            'fieldflow_submission_cardex_done_',
            'fieldflow_submission_history_done_',
            'fieldflow_submission_integrations_done_',
        ] as $prefix) {
            delete_transient($prefix . $submissionId);
        }
        $updated = $wpdb->update(Forms::table_submissions(), [
            'record_id' => 0,
            'record_version_id' => 0,
        ], ['id' => $submissionId], ['%d', '%d'], ['%d']);
        return $updated !== false;
    }

    private static function audit(int $submissionId, array $job, array $record, array $answers, array $context): void {
        if (!class_exists('\\RoutesPro\\Core\\AuditLog') || !class_exists('\\RoutesPro\\Core\\DataContract')) return;
        try {
            $payload = is_array($job['submission_payload'] ?? null) ? $job['submission_payload'] : [];
            $payload['id'] = $submissionId;
            $payload['submission_id'] = $submissionId;
            $contract = \RoutesPro\Core\DataContract::submission($submissionId, $payload, $answers);
            \RoutesPro\Core\AuditLog::record('submission.persisted', 'submission', $submissionId, $job['audit_before'] ?? null, $contract, [
                'client_id' => $context['client_id'],
                'project_id' => $context['project_id'],
                'route_id' => $context['route_id'],
                'route_stop_id' => $context['route_stop_id'],
                'location_id' => $context['location_id'],
                'submission_id' => $submissionId,
                'actor_user_id' => absint($job['user_id'] ?? 0),
                'source' => sanitize_key((string)($job['source'] ?? 'submission_pipeline')),
            ], [
                'is_update' => !empty($job['is_update']),
                'record_id' => absint($record['record_id'] ?? 0),
                'record_version_id' => absint($record['version_id'] ?? 0),
                'pipeline_version' => self::PIPELINE_VERSION,
            ]);
        } catch (\Throwable $error) {
            self::logFailure('Falha não bloqueante na auditoria da pipeline de submissão.', $error, $context, $submissionId);
        }
    }

    private static function schedule(string $hook, array $args, int $delay): bool {
        if (class_exists('\\RoutesPro\\Operations\\Queue\\QueueManager')) {
            return \RoutesPro\Operations\Queue\QueueManager::schedule($hook, $args, $delay, [
                'source'=>'submission_pipeline',
            ]);
        }
        if (wp_next_scheduled($hook, $args)) return true;
        $scheduled = wp_schedule_single_event(time() + max(1, $delay), $hook, $args);
        return $scheduled !== false && !is_wp_error($scheduled);
    }

    private static function context(array $job): array {
        $source = is_array($job['context'] ?? null) ? $job['context'] : [];
        $row = is_array($job['submission_row'] ?? null) ? $job['submission_row'] : [];
        $out = [];
        foreach (['client_id', 'project_id', 'route_id', 'route_stop_id', 'location_id'] as $key) {
            $out[$key] = absint($source[$key] ?? ($row[$key] ?? 0));
        }
        return $out;
    }

    private static function pendingCompletion(array $context, string $reason): array {
        return [
            'applied' => false,
            'reason' => sanitize_key($reason),
            'route_id' => absint($context['route_id'] ?? 0),
            'route_stop_id' => absint($context['route_stop_id'] ?? 0),
            'location_id' => absint($context['location_id'] ?? 0),
            'route_completed_now' => false,
        ];
    }

    private static function diagnoseWarnings(int $submissionId, array $job, array $context, array $warnings): void {
        $settings = self::get();
        if (empty($settings['diagnostics'])) return;
        if (class_exists('\\RoutesPro\\Observability\\Monitor')) {
            try {
                \RoutesPro\Observability\Monitor::record(
                    'submission_pipeline_warning',
                    'warning',
                    implode(' ', $warnings),
                    array_merge($context, [
                        'submission_id' => $submissionId,
                        'source' => sanitize_key((string)($job['source'] ?? 'submission_pipeline')),
                        'pipeline_version' => self::PIPELINE_VERSION,
                    ])
                );
            } catch (\Throwable $ignored) {}
        }
    }

    private static function diagnoseSlowRun(int $submissionId, array $job, array $context, int $processingMs): void {
        $settings = self::get();
        if (empty($settings['diagnostics']) || !class_exists('\\RoutesPro\\Observability\\Monitor')) return;
        try {
            \RoutesPro\Observability\Monitor::record(
                'submission_pipeline_slow',
                'warning',
                'A pipeline pós-commit excedeu 2000 ms.',
                array_merge($context, [
                    'submission_id' => $submissionId,
                    'source' => sanitize_key((string)($job['source'] ?? 'submission_pipeline')),
                    'processing_ms' => max(0, $processingMs),
                    'pipeline_version' => self::PIPELINE_VERSION,
                ])
            );
        } catch (\Throwable $ignored) {}
    }

    private static function logFailure(string $label, \Throwable $error, array $context, int $submissionId): void {
        if (!class_exists('\\RoutesPro\\Support\\Logger')) return;
        try {
            \RoutesPro\Support\Logger::captureThrowable($label, $error, array_merge($context, [
                'context_key' => 'submission_pipeline',
                'submission_id' => $submissionId,
                'pipeline_version' => self::PIPELINE_VERSION,
            ]));
        } catch (\Throwable $ignored) {}
    }

    private static function ids($value): array {
        if (is_string($value)) $value = preg_split('/[\s,;]+/', $value) ?: [];
        if (!is_array($value)) $value = [];
        $ids = [];
        foreach ($value as $item) {
            $id = absint($item);
            if ($id > 0) $ids[$id] = $id;
        }
        ksort($ids, SORT_NUMERIC);
        return array_values($ids);
    }

    private static function channels($value): array {
        if (is_string($value)) $value = preg_split('/[\s,;]+/', $value) ?: [];
        if (!is_array($value)) $value = [];
        $channels = [];
        foreach ($value as $key => $item) {
            $candidate = is_string($key) && !is_numeric($key) && !empty($item) ? $key : $item;
            $candidate = sanitize_key((string)$candidate);
            if (in_array($candidate, ['web', 'mobile'], true)) $channels[$candidate] = $candidate;
        }
        return array_values($channels);
    }

    private static function sampled(int $userId, int $projectId, string $channel, int $percent): bool {
        if ($percent >= 100) return true;
        $seed = $userId . '|' . $projectId . '|' . $channel . '|' . self::BUILD;
        $bucket = (int)(hexdec(substr(hash('sha256', $seed), 0, 8)) % 100) + 1;
        return $bucket <= $percent;
    }
}
