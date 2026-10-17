<?php
namespace RoutesPro\Core;

use RoutesPro\Services\RouteProgressService;

if (!defined('ABSPATH')) exit;

class ReconciliationService {
    public static function runsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_reconciliation_runs';
    }

    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_integrity_reconcile', [self::class, 'handleAdminRun']);
    }

    public static function handleAdminRun(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_integrity_reconcile');
        $mode = sanitize_key((string)($_POST['mode'] ?? 'preview'));
        if (!in_array($mode, ['preview','apply'], true)) $mode = 'preview';
        $result = self::run($mode, 250, get_current_user_id());
        $url = add_query_arg([
            'page' => 'routespro-system-health',
            'fieldflow_integrity_mode' => $mode,
            'fieldflow_integrity_run' => (int)($result['run_id'] ?? 0),
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public static function run(string $mode = 'preview', int $limit = 250, int $userId = 0): array {
        global $wpdb;
        $mode = $mode === 'apply' ? 'apply' : 'preview';
        $limit = max(25, min(1000, $limit));
        $started = current_time('mysql');
        $runId = 0;
        if (self::runsExists()) {
            $wpdb->insert(self::runsTable(), [
                'run_type' => 'core_integrity',
                'run_mode' => $mode,
                'status' => 'running',
                'initiated_by' => $userId ?: (get_current_user_id() ?: null),
                'started_at' => $started,
                'created_at' => $started,
            ], ['%s','%s','%s','%d','%s','%s']);
            $runId = (int)$wpdb->insert_id;
        }

        $issues = self::scan($limit);
        $applied = [];
        $errors = [];
        if ($mode === 'apply') {
            foreach ($issues as $issue) {
                $result = self::applyIssue($issue);
                if (!empty($result['applied'])) $applied[] = $result;
                elseif (!empty($result['error'])) $errors[] = $result;
            }
        }
        $summary = self::summarise($issues, $applied, $errors);
        $completed = current_time('mysql');
        $status = $errors ? 'completed_with_errors' : 'completed';
        if ($runId > 0) {
            $wpdb->update(self::runsTable(), [
                'status' => $status,
                'summary_json' => wp_json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'details_json' => wp_json_encode(['issues' => $issues, 'applied' => $applied, 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'completed_at' => $completed,
            ], ['id' => $runId], ['%s','%s','%s','%s'], ['%d']);
        }

        $context = ['entity_type' => 'reconciliation_run', 'entity_id' => $runId, 'actor_user_id' => $userId];
        EventBus::emit('integrity.reconciliation.' . $mode, DataContract::envelope('reconciliation', $summary, ['run_id' => $runId]), $context);
        AuditLog::record('integrity.reconciliation.' . $mode, 'reconciliation_run', $runId, null, $summary, $context, ['errors' => count($errors)]);

        return ['run_id' => $runId, 'mode' => $mode, 'summary' => $summary, 'issues' => $issues, 'applied' => $applied, 'errors' => $errors];
    }

    public static function scan(int $limit = 250): array {
        global $wpdb;
        $limit = max(25, min(1000, $limit));
        $px = $wpdb->prefix . 'routespro_';
        $issues = [];

        $orphanAnswers = $wpdb->get_results(
            "SELECT a.id,a.submission_id,a.question_key
             FROM {$px}form_submission_answers a
             LEFT JOIN {$px}form_submissions s ON s.id=a.submission_id
             WHERE s.id IS NULL ORDER BY a.id DESC LIMIT {$limit}",
            ARRAY_A
        ) ?: [];
        foreach ($orphanAnswers as $row) {
            $issues[] = self::issue('orphan_answer', 'warning', 'Resposta sem submissão associada.', [
                'answer_id' => absint($row['id'] ?? 0),
                'submission_id' => absint($row['submission_id'] ?? 0),
                'question_key' => (string)($row['question_key'] ?? ''),
            ], false);
        }

        $submissions = $wpdb->get_results(
            "SELECT id,form_id,client_id,project_id,route_id,route_stop_id,location_id,report_day,submitted_at,status
             FROM {$px}form_submissions
             WHERE COALESCE(status,'submitted') NOT IN ('deleted','trash','void','cancelled')
             ORDER BY id DESC LIMIT {$limit}",
            ARRAY_A
        ) ?: [];
        foreach ($submissions as $submission) {
            $id = absint($submission['id'] ?? 0);
            $state = (array)((CanonicalStateService::submission($id))['data'] ?? []);
            foreach ((array)($state['issues'] ?? []) as $code) {
                $safe = false;
                $data = ['submission_id' => $id] + $submission;
                if (in_array($code, ['route_stop_missing','route_stop_unlinked'], true) && absint($submission['route_id'] ?? 0) > 0 && absint($submission['location_id'] ?? 0) > 0) {
                    $matches = array_values(array_filter(array_map('absint', $wpdb->get_col($wpdb->prepare(
                        "SELECT id FROM {$px}route_stops WHERE route_id=%d AND location_id=%d ORDER BY id ASC LIMIT 2",
                        absint($submission['route_id']), absint($submission['location_id'])
                    )) ?: [])));
                    if (count($matches) === 1) {
                        $safe = true;
                        $data['candidate_route_stop_id'] = $matches[0];
                    }
                }
                $severity = in_array($code, ['route_stop_missing','submission_route_mismatch','submission_location_mismatch','route_missing','location_missing'], true) ? 'critical' : 'warning';
                $issues[] = self::issue('submission_' . $code, $severity, 'Inconsistência na submissão #' . $id . '.', $data, $safe);
            }
        }

        $routeIds = array_values(array_filter(array_map('absint', $wpdb->get_col(
            "SELECT id FROM {$px}routes WHERE status NOT IN ('cancelled','canceled') ORDER BY id DESC LIMIT {$limit}"
        ) ?: [])));
        foreach ($routeIds as $routeId) {
            $state = (array)((CanonicalStateService::route($routeId))['data'] ?? []);
            if (($state['status'] ?? '') !== ($state['legacy_status'] ?? '')) {
                $issues[] = self::issue('route_status_mismatch', 'warning', 'Estado da rota divergente da execução real.', [
                    'route_id' => $routeId,
                    'legacy_status' => (string)($state['legacy_status'] ?? ''),
                    'canonical_status' => (string)($state['status'] ?? ''),
                ], true);
            }
            foreach ((array)($state['issues'] ?? []) as $routeIssue) {
                if (($routeIssue['code'] ?? '') !== 'submission_not_reflected_in_stop') continue;
                $issues[] = self::issue('visit_state_mismatch', 'warning', 'Submissão válida ainda não refletida na paragem.', [
                    'route_id' => $routeId,
                    'route_stop_id' => absint($routeIssue['route_stop_id'] ?? 0),
                ], true);
            }
        }

        $forms = $wpdb->get_results("SELECT id,title,schema_json FROM {$px}forms WHERE status='active' ORDER BY id DESC LIMIT 200", ARRAY_A) ?: [];
        $supported = self::supportedQuestionTypes();
        foreach ($forms as $form) {
            $schema = json_decode((string)($form['schema_json'] ?? ''), true);
            foreach ((array)($schema['questions'] ?? []) as $question) {
                if (!is_array($question)) continue;
                $type = sanitize_key((string)($question['type'] ?? 'text'));
                if ($type !== '' && !in_array($type, $supported, true)) {
                    $issues[] = self::issue('unknown_question_type', 'warning', 'Tipo de pergunta não reconhecido no formulário.', [
                        'form_id' => absint($form['id'] ?? 0),
                        'form_title' => (string)($form['title'] ?? ''),
                        'question_key' => sanitize_key((string)($question['key'] ?? '')),
                        'question_type' => $type,
                    ], false);
                }
            }
        }

        $unique = [];
        foreach ($issues as $issue) {
            $fingerprint = md5(($issue['type'] ?? '') . '|' . wp_json_encode($issue['data'] ?? []));
            $unique[$fingerprint] = $issue;
        }
        return array_slice(array_values($unique), 0, $limit * 4);
    }

    public static function quickOverview(): array {
        global $wpdb;
        $eventCounts = EventBus::counts();
        $runs = self::recentRuns(1);
        $last = $runs[0] ?? [];
        $lastSummary = json_decode((string)($last['summary_json'] ?? ''), true);
        return [
            'events' => $eventCounts,
            'audit_last_24h' => AuditLog::countSince(wp_date('Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS)),
            'last_run_id' => absint($last['id'] ?? 0),
            'last_run_at' => (string)($last['completed_at'] ?? $last['started_at'] ?? ''),
            'last_run_mode' => (string)($last['run_mode'] ?? ''),
            'last_run_status' => (string)($last['status'] ?? ''),
            'last_issue_count' => (int)($lastSummary['total_issues'] ?? 0),
            'tables_ready' => EventBus::exists() && AuditLog::exists() && self::runsExists(),
        ];
    }

    public static function recentRuns(int $limit = 10): array {
        global $wpdb;
        if (!self::runsExists()) return [];
        $limit = max(1, min(50, $limit));
        return $wpdb->get_results('SELECT * FROM ' . self::runsTable() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    private static function applyIssue(array $issue): array {
        global $wpdb;
        $type = (string)($issue['type'] ?? '');
        $data = (array)($issue['data'] ?? []);
        if (empty($issue['safe_fix'])) return ['applied' => false, 'type' => $type, 'reason' => 'manual_review_required'];

        if ($type === 'route_status_mismatch' || $type === 'visit_state_mismatch') {
            $routeId = absint($data['route_id'] ?? 0);
            $result = $routeId > 0 ? RouteProgressService::sync($routeId, true) : null;
            if (is_wp_error($result)) return ['applied' => false, 'type' => $type, 'route_id' => $routeId, 'error' => $result->get_error_message()];
            return ['applied' => true, 'type' => $type, 'route_id' => $routeId, 'result' => $result];
        }

        if (in_array($type, ['submission_route_stop_missing','submission_route_stop_unlinked'], true)) {
            $submissionId = absint($data['submission_id'] ?? 0);
            $stopId = absint($data['candidate_route_stop_id'] ?? 0);
            if ($submissionId <= 0 || $stopId <= 0) return ['applied' => false, 'type' => $type, 'error' => 'Contexto insuficiente.'];
            $stop = $wpdb->get_row($wpdb->prepare('SELECT route_id,location_id FROM ' . $wpdb->prefix . 'routespro_route_stops WHERE id=%d LIMIT 1', $stopId), ARRAY_A);
            if (!$stop) return ['applied' => false, 'type' => $type, 'error' => 'Paragem candidata inexistente.'];
            $before = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'routespro_form_submissions WHERE id=%d LIMIT 1', $submissionId), ARRAY_A) ?: [];
            if (!$before) return ['applied' => false, 'type' => $type, 'error' => 'Submissão inexistente.'];
            $routeId = absint($stop['route_id'] ?? 0);
            $locationId = absint($stop['location_id'] ?? 0);
            $wpdb->query('START TRANSACTION');
            $ok = $wpdb->update($wpdb->prefix . 'routespro_form_submissions', [
                'route_id' => $routeId,
                'route_stop_id' => $stopId,
                'location_id' => $locationId,
            ], ['id' => $submissionId], ['%d','%d','%d'], ['%d']);
            if ($ok === false) {
                $wpdb->query('ROLLBACK');
                return ['applied' => false, 'type' => $type, 'error' => $wpdb->last_error ?: 'Falha ao ligar submissão.'];
            }
            $progress = $routeId > 0 ? RouteProgressService::sync($routeId, true) : null;
            if (is_wp_error($progress)) {
                $wpdb->query('ROLLBACK');
                return ['applied' => false, 'type' => $type, 'submission_id' => $submissionId, 'error' => $progress->get_error_message()];
            }
            $after = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . 'routespro_form_submissions WHERE id=%d LIMIT 1', $submissionId), ARRAY_A) ?: [];
            $wpdb->query('COMMIT');
            $route = $wpdb->get_row($wpdb->prepare('SELECT client_id,project_id FROM ' . $wpdb->prefix . 'routespro_routes WHERE id=%d LIMIT 1', $routeId), ARRAY_A) ?: [];
            $context = [
                'source' => 'reconciliation',
                'entity_type' => 'submission',
                'entity_id' => $submissionId,
                'submission_id' => $submissionId,
                'route_id' => $routeId,
                'route_stop_id' => $stopId,
                'location_id' => $locationId,
                'client_id' => absint($route['client_id'] ?? $after['client_id'] ?? 0),
                'project_id' => absint($route['project_id'] ?? $after['project_id'] ?? 0),
            ];
            AuditLog::record('submission.reconciled', 'submission', $submissionId, $before, $after, $context, ['issue_type' => $type]);
            EventBus::emit('submission.reconciled', DataContract::submission($submissionId, $after), $context, 'reconciliation');
            return ['applied' => true, 'type' => $type, 'submission_id' => $submissionId, 'route_stop_id' => $stopId, 'route_progress' => $progress];
        }

        return ['applied' => false, 'type' => $type, 'reason' => 'no_handler'];
    }

    private static function issue(string $type, string $severity, string $message, array $data, bool $safeFix): array {
        return [
            'type' => sanitize_key($type),
            'severity' => in_array($severity, ['info','warning','critical'], true) ? $severity : 'warning',
            'message' => sanitize_text_field($message),
            'safe_fix' => $safeFix,
            'data' => $data,
        ];
    }

    private static function summarise(array $issues, array $applied, array $errors): array {
        $summary = [
            'total_issues' => count($issues),
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
            'safe_fixes_available' => 0,
            'applied' => count($applied),
            'errors' => count($errors),
            'by_type' => [],
        ];
        foreach ($issues as $issue) {
            $severity = (string)($issue['severity'] ?? 'warning');
            if (isset($summary[$severity])) $summary[$severity]++;
            if (!empty($issue['safe_fix'])) $summary['safe_fixes_available']++;
            $type = (string)($issue['type'] ?? 'unknown');
            $summary['by_type'][$type] = (int)($summary['by_type'][$type] ?? 0) + 1;
        }
        ksort($summary['by_type']);
        return $summary;
    }

    private static function supportedQuestionTypes(): array {
        return [
            'text','textarea','long_text','number','currency','percent','select','single_choice','radio','multi_select','multiple_choice','checkbox_group','visual_choice','checkbox','date','datetime','time','url','email','phone',
            'image_upload','categorized_image_upload','file_upload','signature','location','product_matrix','order_suggestion','share_space_matrix','briefing_comparison','comparative','comparison','briefing_block','briefing_intro',
        ];
    }

    private static function runsExists(): bool {
        global $wpdb;
        $table = self::runsTable();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
