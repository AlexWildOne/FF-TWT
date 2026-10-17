<?php
namespace RoutesPro\Services;

use WP_Error;

if (!defined('ABSPATH')) exit;

class RouteProgressService {
    private static $submission_support = null;

    // N->N+1 compatibility for the certified 2.2.540 package gate.
    // SubmissionStatusPolicy::operationalSql('fs') expands to COALESCE(fs.status,'submitted') IN ('submitted','reviewed','approved').

    /**
     * Recalcula o estado da rota a partir do estado real das paragens.
     * Antes da contagem, reconcilia reportes válidos que ainda não ficaram
     * refletidos no estado da paragem.
     *
     * @return array|WP_Error
     */
    public static function sync(int $route_id, bool $reconcile_submissions = true, bool $emit_completion_event = true) {
        global $wpdb;

        $route_id = absint($route_id);
        if ($route_id <= 0) {
            return new WP_Error('route_progress_invalid', 'Rota inválida para sincronização.', ['status' => 400]);
        }

        $px = $wpdb->prefix . 'routespro_';
        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT id, client_id, project_id, owner_user_id, date, status FROM {$px}routes WHERE id=%d LIMIT 1",
            $route_id
        ), ARRAY_A);
        if (!$route) {
            return new WP_Error('route_progress_missing', 'A rota já não existe.', ['status' => 404]);
        }

        $reconciliation = ['matched' => 0, 'updated' => 0, 'stop_ids' => []];
        if ($reconcile_submissions) {
            $reconciliation = self::reconcile_submissions($route_id, (string)($route['date'] ?? ''));
            if (is_wp_error($reconciliation)) return $reconciliation;
        }

        $executedSql = VisitCompletionPolicy::executedSql();
        $justifiedSql = VisitCompletionPolicy::justifiedNonVisitSql();
        $remainingSql = VisitCompletionPolicy::remainingSql();
        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total_stops,
                SUM(CASE WHEN {$executedSql} THEN 1 ELSE 0 END) AS completed_stops,
                SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed_stops,
                SUM(CASE WHEN {$justifiedSql} THEN 1 ELSE 0 END) AS justified_failed_stops,
                SUM(CASE WHEN status='failed' AND TRIM(COALESCE(fail_reason,''))='' THEN 1 ELSE 0 END) AS unjustified_failed_stops,
                SUM(CASE WHEN status IN ('in_progress','active','started') OR (arrived_at IS NOT NULL AND status NOT IN ('done','completed','failed')) THEN 1 ELSE 0 END) AS active_stops,
                SUM(CASE WHEN {$remainingSql} THEN 1 ELSE 0 END) AS remaining_stops
             FROM {$px}route_stops
             WHERE route_id=%d",
            $route_id
        ), ARRAY_A) ?: [];

        $total = (int)($counts['total_stops'] ?? 0);
        $completed = (int)($counts['completed_stops'] ?? 0);
        $failed = (int)($counts['failed_stops'] ?? 0);
        $justified_failed = (int)($counts['justified_failed_stops'] ?? 0);
        $unjustified_failed = (int)($counts['unjustified_failed_stops'] ?? 0);
        $terminal = $completed + $justified_failed;
        $active = (int)($counts['active_stops'] ?? 0);
        $remaining = (int)($counts['remaining_stops'] ?? 0);
        $previous_status = sanitize_key((string)($route['status'] ?? 'draft'));
        $next_status = $previous_status;

        if (!in_array($previous_status, ['cancelled', 'canceled'], true)) {
            if ($total > 0 && $remaining === 0) {
                $next_status = 'completed';
            } elseif ($completed > 0 || $failed > 0 || $active > 0) {
                $next_status = 'in_progress';
            } elseif (in_array($previous_status, ['completed', 'done', 'in_progress', 'active', 'started'], true)) {
                $next_status = 'planned';
            }
        }

        $updated = 0;
        if ($next_status !== $previous_status) {
            $updated = $wpdb->update(
                $px . 'routes',
                ['status' => $next_status],
                ['id' => $route_id],
                ['%s'],
                ['%d']
            );
            if ($updated === false) {
                return new WP_Error('route_progress_update', 'Não foi possível sincronizar o estado da rota.', ['status' => 500]);
            }
            if ($updated > 0) {
                $context = [
                    'entity_type' => 'route',
                    'entity_id' => $route_id,
                    'route_id' => $route_id,
                    'client_id' => absint($route['client_id'] ?? 0),
                    'project_id' => absint($route['project_id'] ?? 0),
                    'actor_user_id' => get_current_user_id(),
                ];
                try {
                    if (class_exists('\RoutesPro\Core\AuditLog')) {
                        \RoutesPro\Core\AuditLog::record('route.status_changed', 'route', $route_id,
                            ['status' => $previous_status],
                            ['status' => $next_status, 'counts' => $counts, 'reconciliation' => $reconciliation],
                            $context
                        );
                    }
                    \RoutesPro\Core\EventBus::emit('route.status_changed', \RoutesPro\Core\DataContract::state('route', [
                        'route_id' => $route_id,
                        'previous_status' => $previous_status,
                        'status' => $next_status,
                        'counts' => $counts,
                        'reconciliation' => $reconciliation,
                    ]), $context);
                    if ($next_status === 'completed' && $emit_completion_event) {
                        \RoutesPro\Core\EventBus::emit('route.completed', \RoutesPro\Core\DataContract::state('route', [
                            'route_id' => $route_id,
                            'status' => $next_status,
                            'completed_stops' => $completed,
                            'justified_failed_stops' => $justified_failed,
                            'terminal_stops' => $terminal,
                            'total_stops' => $total,
                        ]), $context);
                    }
                } catch (\Throwable $e) {
                    if (class_exists('\RoutesPro\Support\Logger')) {
                        \RoutesPro\Support\Logger::captureThrowable('Falha não bloqueante na auditoria da rota.', $e, [
                            'context_key' => 'route_progress_post_update',
                            'route_id' => $route_id,
                            'client_id' => absint($route['client_id'] ?? 0),
                            'project_id' => absint($route['project_id'] ?? 0),
                        ]);
                    }
                }
            }
        }

        return [
            'route_id' => $route_id,
            'previous_status' => $previous_status,
            'route_status' => $next_status,
            'status_changed' => $next_status !== $previous_status && $updated > 0,
            'route_completed_now' => $next_status === 'completed' && $previous_status !== 'completed' && $previous_status !== 'done',
            'total_stops' => $total,
            'completed_stops' => $completed,
            'failed_stops' => $failed,
            'justified_failed_stops' => $justified_failed,
            'unjustified_failed_stops' => $unjustified_failed,
            'terminal_stops' => $terminal,
            'active_stops' => $active,
            'remaining_stops' => $remaining,
            'done_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'operational_completion_rate' => $total > 0 ? round(($terminal / $total) * 100, 1) : 0,
            'reconciliation' => $reconciliation,
            'completion_event_emitted' => $next_status === 'completed' && $previous_status !== 'completed' && $previous_status !== 'done' && $updated > 0 && $emit_completion_event,
            'operationally_complete' => $total > 0 && $remaining === 0,
        ];
    }

    /**
     * Sincroniza apenas rotas que tenham reportes ativos capazes de concluir
     * paragens ainda pendentes. Evita percorrer todas as rotas do histórico.
     */
    public static function sync_many(array $route_ids): array {
        global $wpdb;
        $route_ids = array_values(array_unique(array_filter(array_map('absint', $route_ids))));
        if (!$route_ids || !self::submission_support_available()) return [];
        $route_ids = array_slice($route_ids, 0, 500);
        $px = $wpdb->prefix . 'routespro_';
        $in = implode(',', array_map('intval', $route_ids));
        $active = SubmissionStatusPolicy::operationalSql('fs');
        $candidate_ids = $wpdb->get_col(
            "SELECT DISTINCT r.id
             FROM {$px}routes r
             INNER JOIN {$px}route_stops rs ON rs.route_id=r.id
             INNER JOIN {$px}form_submissions fs ON {$active} AND (
                fs.route_stop_id=rs.id
                OR (
                    fs.location_id=rs.location_id
                    AND (
                        fs.route_id=r.id
                        OR (
                            COALESCE(fs.route_id,0)=0
                            AND (fs.report_day=r.date OR (fs.report_day IS NULL AND DATE(fs.submitted_at)=r.date))
                            AND NOT EXISTS (
                                SELECT 1
                                FROM {$px}routes r_other
                                INNER JOIN {$px}route_stops rs_other ON rs_other.route_id=r_other.id
                                WHERE r_other.date=r.date
                                  AND r_other.id<>r.id
                                  AND rs_other.location_id=rs.location_id
                            )
                        )
                    )
                )
             )
             WHERE r.id IN ({$in})
               AND (rs.status IS NULL OR (rs.status NOT IN ('done','completed') AND NOT (rs.status='failed' AND TRIM(COALESCE(rs.fail_reason,''))<>'')))"
        ) ?: [];

        $results = [];
        foreach (array_values(array_unique(array_map('absint', $candidate_ids))) as $route_id) {
            $result = self::sync($route_id, true);
            if (!is_wp_error($result)) $results[$route_id] = $result;
        }
        return $results;
    }

    /**
     * Usa a submissão diária como fonte de verdade operacional. Isto recupera
     * situações em que o reporte ficou gravado mas a paragem foi posteriormente
     * apresentada como pendente por um fluxo legado ou por contexto incompleto.
     *
     * @return array|WP_Error
     */
    private static function reconcile_submissions(int $route_id, string $route_date) {
        global $wpdb;
        if (!self::submission_support_available()) {
            return ['matched' => 0, 'updated' => 0, 'stop_ids' => []];
        }

        $px = $wpdb->prefix . 'routespro_';
        $route_date = self::normalise_date($route_date);
        $stops = $wpdb->get_results($wpdb->prepare(
            "SELECT rs.*, counts.location_stop_count
             FROM {$px}route_stops rs
             LEFT JOIN (
                SELECT location_id, COUNT(*) AS location_stop_count
                FROM {$px}route_stops
                WHERE route_id=%d
                GROUP BY location_id
             ) counts ON counts.location_id=rs.location_id
             WHERE rs.route_id=%d
               AND (rs.status IS NULL OR (rs.status NOT IN ('done','completed') AND NOT (rs.status='failed' AND TRIM(COALESCE(rs.fail_reason,''))<>'')))
             ORDER BY rs.id ASC",
            $route_id,
            $route_id
        ), ARRAY_A) ?: [];

        if (!$stops) return ['matched' => 0, 'updated' => 0, 'stop_ids' => []];

        $matched = 0;
        $updated = 0;
        $stop_ids = [];
        $active = SubmissionStatusPolicy::operationalSql('fs');

        foreach ($stops as $stop) {
            $stop_id = absint($stop['id'] ?? 0);
            $location_id = absint($stop['location_id'] ?? 0);
            if ($stop_id <= 0 || $location_id <= 0) continue;
            $stop_meta = self::decode_meta($stop['meta_json'] ?? '');
            // 9BQ: an administrative reopen must survive passive reconciliation.
            // Historical submissions remain available, but only a deliberate new
            // submission can resolve the reopen marker and close the visit again.
            if (!empty($stop_meta['visit_admin_reopen']['active'])) continue;
            $allow_location_fallback = (int)($stop['location_stop_count'] ?? 0) === 1 ? 1 : 0;

            $submission = $wpdb->get_row($wpdb->prepare(
                "SELECT fs.id, fs.form_id, fs.user_id, fs.route_id, fs.route_stop_id, fs.location_id,
                        fs.report_day, fs.submitted_at,
                        CASE WHEN fs.route_stop_id=%d THEN 1 ELSE 0 END AS exact_stop,
                        CASE WHEN fs.route_id=%d THEN 1 ELSE 0 END AS exact_route
                 FROM {$px}form_submissions fs
                 WHERE {$active}
                   AND (
                        fs.route_stop_id=%d
                        OR (
                            %d=1
                            AND fs.location_id=%d
                            AND (
                                fs.route_id=%d
                                OR (
                                    COALESCE(fs.route_id,0)=0
                                    AND (fs.report_day=%s OR (fs.report_day IS NULL AND DATE(fs.submitted_at)=%s))
                                    AND NOT EXISTS (
                                        SELECT 1
                                        FROM {$px}routes r_other
                                        INNER JOIN {$px}route_stops rs_other ON rs_other.route_id=r_other.id
                                        WHERE r_other.date=%s
                                          AND r_other.id<>%d
                                          AND rs_other.location_id=%d
                                    )
                                )
                            )
                        )
                   )
                 ORDER BY exact_stop DESC, exact_route DESC, fs.id DESC
                 LIMIT 1",
                $stop_id,
                $route_id,
                $stop_id,
                $allow_location_fallback,
                $location_id,
                $route_id,
                $route_date,
                $route_date,
                $route_date,
                $route_id,
                $location_id
            ), ARRAY_A);
            if (!$submission) continue;
            $matched++;

            $submission_id = absint($submission['id'] ?? 0);
            $completed_at = self::normalise_datetime((string)($submission['submitted_at'] ?? ''));
            $meta = self::decode_meta($stop['meta_json'] ?? '');
            $existing_completion = is_array($meta['form_visit_completion'] ?? null) ? $meta['form_visit_completion'] : [];
            if (empty($existing_completion['first_submission_id'])) {
                $existing_completion['first_submission_id'] = $submission_id;
                $existing_completion['first_form_id'] = absint($submission['form_id'] ?? 0);
                $existing_completion['first_user_id'] = absint($submission['user_id'] ?? 0);
                $existing_completion['first_completed_at'] = $completed_at;
                $existing_completion['previous_status'] = sanitize_key((string)($stop['status'] ?? 'pending'));
            }
            $existing_completion['source'] = 'submission_reconciliation';
            $existing_completion['last_submission_id'] = $submission_id;
            $existing_completion['last_form_id'] = absint($submission['form_id'] ?? 0);
            $existing_completion['last_user_id'] = absint($submission['user_id'] ?? 0);
            $existing_completion['last_submitted_at'] = $completed_at;
            $existing_completion['reconciled_at'] = current_time('mysql');
            $meta['form_visit_completion'] = $existing_completion;

            $fields = [
                'status' => 'done',
                'meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $formats = ['%s', '%s'];
            if (empty($stop['departed_at'])) {
                $fields['departed_at'] = $completed_at;
                $formats[] = '%s';
            }
            $ok = $wpdb->update($px . 'route_stops', $fields, ['id' => $stop_id], $formats, ['%d']);
            if ($ok === false) {
                return new WP_Error('route_submission_reconciliation', 'Não foi possível reconciliar o reporte com a paragem da rota.', ['status' => 500]);
            }

            if ((int)($submission['route_stop_id'] ?? 0) !== $stop_id || (int)($submission['route_id'] ?? 0) !== $route_id) {
                $wpdb->update(
                    $px . 'form_submissions',
                    ['route_id' => $route_id, 'route_stop_id' => $stop_id, 'location_id' => $location_id],
                    ['id' => $submission_id],
                    ['%d', '%d', '%d'],
                    ['%d']
                );
            }
            $updated += max(0, (int)$ok);
            $stop_ids[] = $stop_id;
        }

        return [
            'matched' => $matched,
            'updated' => $updated,
            'stop_ids' => array_values(array_unique($stop_ids)),
        ];
    }

    private static function submission_support_available(): bool {
        if (self::$submission_support !== null) return (bool)self::$submission_support;
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_form_submissions';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            self::$submission_support = false;
            return false;
        }
        foreach (['route_id', 'route_stop_id', 'location_id', 'report_day'] as $column) {
            if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column))) {
                self::$submission_support = false;
                return false;
            }
        }
        self::$submission_support = true;
        return true;
    }

    private static function normalise_date(string $value): string {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        $timestamp = $value !== '' ? strtotime($value) : false;
        return $timestamp ? wp_date('Y-m-d', $timestamp, wp_timezone()) : current_time('Y-m-d');
    }

    private static function normalise_datetime(string $value): string {
        $value = trim($value);
        return ($value !== '' && strtotime($value)) ? $value : current_time('mysql');
    }

    private static function decode_meta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
