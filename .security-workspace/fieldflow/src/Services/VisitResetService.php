<?php
namespace RoutesPro\Services;

use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Administrative recovery for an accidentally started visit.
 *
 * The service resets only the operational state of a stop. It never deletes
 * submissions, answers, media attachments or route history.
 */
final class VisitResetService {
    private const HISTORY_LIMIT = 12;

    /**
     * @return array|WP_Error
     */
    public static function resetToPending(int $stopId, int $userId = 0, string $reason = '') {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stopId = absint($stopId);
        $userId = $userId > 0 ? $userId : get_current_user_id();
        $reason = sanitize_textarea_field($reason);
        if ($reason === '') $reason = 'Correção operacional no backoffice';

        if ($stopId <= 0) {
            return new WP_Error('fieldflow_visit_reset_invalid', 'A visita indicada não é válida.', ['status' => 400]);
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('fieldflow_visit_reset_transaction', 'Não foi possível iniciar a reposição segura da visita.', ['status' => 500]);
        }

        try {
            $stop = $wpdb->get_row($wpdb->prepare(
                "SELECT rs.*, r.client_id, r.project_id, r.owner_user_id, r.status AS route_status
                 FROM {$px}route_stops rs
                 INNER JOIN {$px}routes r ON r.id=rs.route_id
                 WHERE rs.id=%d LIMIT 1 FOR UPDATE",
                $stopId
            ), ARRAY_A) ?: [];

            if (!$stop) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_reset_missing', 'A visita já não existe.', ['status' => 404]);
            }

            $routeId = absint($stop['route_id'] ?? 0);
            $status = sanitize_key((string)($stop['status'] ?? 'pending'));
            $terminal = in_array($status, ['done', 'completed', 'failed', 'cancelled', 'canceled'], true)
                || !empty($stop['departed_at']);
            $started = in_array($status, ['in_progress', 'active', 'started', 'arrived'], true)
                || (!empty($stop['arrived_at']) && empty($stop['departed_at']));

            if ($terminal) {
                $wpdb->query('ROLLBACK');
                return new WP_Error(
                    'fieldflow_visit_reset_terminal',
                    'Esta visita já foi concluída ou marcada como falhada. Usa a correção administrativa de submissões em vez de a repor como pendente.',
                    ['status' => 409]
                );
            }

            if (!$started) {
                $wpdb->query('ROLLBACK');
                return new WP_Error(
                    'fieldflow_visit_reset_not_started',
                    'Esta visita já está pendente e não precisa de reposição.',
                    ['status' => 409]
                );
            }

            $submissionCount = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$px}form_submissions WHERE route_stop_id=%d",
                $stopId
            ));
            $meta = self::decodeMeta($stop['meta_json'] ?? '');
            $hasCompletionMeta = !empty($meta['form_visit_completion']['first_submission_id'])
                || !empty($meta['form_visit_completion']['last_submission_id']);

            if ($submissionCount > 0 || $hasCompletionMeta) {
                $wpdb->query('ROLLBACK');
                return new WP_Error(
                    'fieldflow_visit_reset_has_submission',
                    'Esta visita já tem um formulário submetido. A reposição foi bloqueada para proteger respostas e fotografias.',
                    ['status' => 409, 'submission_count' => $submissionCount]
                );
            }

            $resetAtMs = (int)round(microtime(true) * 1000);
            $history = is_array($meta['visit_reset_history'] ?? null) ? array_values($meta['visit_reset_history']) : [];
            $history[] = [
                'reset_at' => current_time('mysql'),
                'reset_at_gmt' => current_time('mysql', true),
                'reset_at_ms' => $resetAtMs,
                'reset_by' => $userId,
                'reason' => $reason,
                'previous' => [
                    'status' => $status,
                    'arrived_at' => self::textOrNull($stop['arrived_at'] ?? null),
                    'departed_at' => self::textOrNull($stop['departed_at'] ?? null),
                    'duration_s' => isset($stop['duration_s']) ? (int)$stop['duration_s'] : null,
                    'fail_reason' => self::textOrNull($stop['fail_reason'] ?? null),
                    'real_lat' => isset($stop['real_lat']) && $stop['real_lat'] !== null ? (float)$stop['real_lat'] : null,
                    'real_lng' => isset($stop['real_lng']) && $stop['real_lng'] !== null ? (float)$stop['real_lng'] : null,
                ],
            ];
            $meta['visit_reset_history'] = array_slice($history, -self::HISTORY_LIMIT);
            $meta['visit_reset_pending'] = [
                'reset_at' => current_time('mysql'),
                'reset_at_gmt' => current_time('mysql', true),
                'reset_at_ms' => $resetAtMs,
                'reset_by' => $userId,
                'reason' => $reason,
                'generation' => max(0, absint($meta['visit_reset_pending']['generation'] ?? 0)) + 1,
            ];

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$px}route_stops
                 SET status='pending', arrived_at=NULL, departed_at=NULL, duration_s=NULL,
                     fail_reason=NULL, real_lat=NULL, real_lng=NULL, meta_json=%s
                 WHERE id=%d",
                wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $stopId
            ));

            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_reset_update', $wpdb->last_error ?: 'Não foi possível repor a visita.', ['status' => 500]);
            }

            $progress = RouteProgressService::sync($routeId, true);
            if (is_wp_error($progress)) {
                $wpdb->query('ROLLBACK');
                return $progress;
            }

            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_reset_commit', 'Não foi possível confirmar a reposição da visita.', ['status' => 500]);
            }

            RouteOptimizationService::invalidateUserRouteCache(absint($stop['owner_user_id'] ?? 0));
            $after = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1",
                $stopId
            ), ARRAY_A) ?: [];

            $context = [
                'source' => 'backoffice',
                'entity_type' => 'visit',
                'entity_id' => $stopId,
                'route_id' => $routeId,
                'route_stop_id' => $stopId,
                'location_id' => absint($stop['location_id'] ?? 0),
                'client_id' => absint($stop['client_id'] ?? 0),
                'project_id' => absint($stop['project_id'] ?? 0),
                'actor_user_id' => $userId,
            ];
            if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
                \RoutesPro\Core\AuditLog::record('visit.reset_to_pending', 'visit', $stopId, $stop, $after, $context, [
                    'reason' => $reason,
                    'reset_at_ms' => $resetAtMs,
                ]);
            }
            if (class_exists('\\RoutesPro\\Core\\EventBus')) {
                \RoutesPro\Core\EventBus::emit('visit.reset_to_pending', [
                    'before' => $stop,
                    'visit' => $after,
                    'reason' => $reason,
                    'reset_at_ms' => $resetAtMs,
                ], $context, 'backoffice');
            }

            return [
                'ok' => true,
                'route_id' => $routeId,
                'route_stop_id' => $stopId,
                'status' => 'pending',
                'reason' => $reason,
                'reset_at_ms' => $resetAtMs,
                'route_progress' => $progress,
                'stop' => $after,
            ];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Falha ao repor visita como pendente.', $error, [
                    'context_key' => 'visit_reset_pending',
                    'route_stop_id' => $stopId,
                ]);
            }
            return new WP_Error('fieldflow_visit_reset_failure', 'Não foi possível repor a visita como pendente.', ['status' => 500]);
        }
    }

    /**
     * Reabre uma visita terminal por decisão administrativa sem apagar qualquer
     * reporte, resposta ou media. O marcador ativo bloqueia a reconciliação
     * passiva do reporte antigo até existir uma nova submissão/confirmacão.
     *
     * @return array|WP_Error
     */
    public static function reopenToPending(int $stopId, int $userId = 0, string $reason = '') {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stopId = absint($stopId);
        $userId = $userId > 0 ? $userId : get_current_user_id();
        $reason = sanitize_textarea_field($reason);
        if ($reason === '') {
            return new WP_Error('fieldflow_visit_reopen_reason_required', 'Indica o motivo da reabertura da loja.', ['status' => 422]);
        }
        if ($stopId <= 0) {
            return new WP_Error('fieldflow_visit_reopen_invalid', 'A visita indicada não é válida.', ['status' => 400]);
        }
        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('fieldflow_visit_reopen_transaction', 'Não foi possível iniciar a reabertura segura da visita.', ['status' => 500]);
        }

        try {
            $stop = $wpdb->get_row($wpdb->prepare(
                "SELECT rs.*, r.client_id, r.project_id, r.owner_user_id, r.status AS route_status
                 FROM {$px}route_stops rs
                 INNER JOIN {$px}routes r ON r.id=rs.route_id
                 WHERE rs.id=%d LIMIT 1 FOR UPDATE",
                $stopId
            ), ARRAY_A) ?: [];
            if (!$stop) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_reopen_missing', 'A visita já não existe.', ['status' => 404]);
            }

            $routeId = absint($stop['route_id'] ?? 0);
            $status = sanitize_key((string)($stop['status'] ?? 'pending'));
            if (!in_array($status, ['done', 'completed', 'failed'], true) && empty($stop['departed_at'])) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_reopen_not_terminal', 'Esta loja ainda não está concluída e não precisa de ser reaberta.', ['status' => 409]);
            }

            $meta = self::decodeMeta($stop['meta_json'] ?? '');
            $reopenAtMs = (int)round(microtime(true) * 1000);
            $currentGeneration = max(0, absint($meta['visit_admin_reopen']['generation'] ?? 0));
            $entry = [
                'active' => 1,
                'reopened_at' => current_time('mysql'),
                'reopened_at_gmt' => current_time('mysql', true),
                'reopened_at_ms' => $reopenAtMs,
                'reopened_by' => $userId,
                'reason' => $reason,
                'generation' => $currentGeneration + 1,
                'previous' => [
                    'status' => $status,
                    'arrived_at' => self::textOrNull($stop['arrived_at'] ?? null),
                    'departed_at' => self::textOrNull($stop['departed_at'] ?? null),
                    'duration_s' => isset($stop['duration_s']) ? (int)$stop['duration_s'] : null,
                    'fail_reason' => self::textOrNull($stop['fail_reason'] ?? null),
                    'real_lat' => isset($stop['real_lat']) && $stop['real_lat'] !== null ? (float)$stop['real_lat'] : null,
                    'real_lng' => isset($stop['real_lng']) && $stop['real_lng'] !== null ? (float)$stop['real_lng'] : null,
                    'route_status' => sanitize_key((string)($stop['route_status'] ?? '')),
                    'last_submission_id' => absint($meta['form_visit_completion']['last_submission_id'] ?? 0),
                ],
            ];
            $history = is_array($meta['visit_admin_reopen_history'] ?? null) ? array_values($meta['visit_admin_reopen_history']) : [];
            $history[] = $entry;
            $meta['visit_admin_reopen_history'] = array_slice($history, -self::HISTORY_LIMIT);
            $meta['visit_admin_reopen'] = $entry;

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$px}route_stops
                 SET status='pending', arrived_at=NULL, departed_at=NULL, duration_s=NULL,
                     fail_reason=NULL, real_lat=NULL, real_lng=NULL, meta_json=%s
                 WHERE id=%d",
                wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $stopId
            ));
            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_reopen_update', $wpdb->last_error ?: 'Não foi possível reabrir a visita.', ['status' => 500]);
            }

            // Deliberadamente sem reconciliação de submissões: o reporte antigo
            // é preservado para auditoria, mas não pode fechar novamente a loja.
            $progress = RouteProgressService::sync($routeId, false, false);
            if (is_wp_error($progress)) {
                $wpdb->query('ROLLBACK');
                return $progress;
            }

            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_reopen_commit', 'Não foi possível confirmar a reabertura da visita.', ['status' => 500]);
            }

            RouteOptimizationService::invalidateUserRouteCache(absint($stop['owner_user_id'] ?? 0));
            $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1", $stopId), ARRAY_A) ?: [];
            $context = [
                'source' => 'backoffice',
                'entity_type' => 'visit',
                'entity_id' => $stopId,
                'route_id' => $routeId,
                'route_stop_id' => $stopId,
                'location_id' => absint($stop['location_id'] ?? 0),
                'client_id' => absint($stop['client_id'] ?? 0),
                'project_id' => absint($stop['project_id'] ?? 0),
                'actor_user_id' => $userId,
            ];
            if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
                \RoutesPro\Core\AuditLog::record('visit.admin_reopened', 'visit', $stopId, $stop, $after, $context, [
                    'reason' => $reason,
                    'reopened_at_ms' => $reopenAtMs,
                ]);
            }
            if (class_exists('\\RoutesPro\\Core\\EventBus')) {
                \RoutesPro\Core\EventBus::emit('visit.admin_reopened', [
                    'before' => $stop,
                    'visit' => $after,
                    'reason' => $reason,
                    'reopened_at_ms' => $reopenAtMs,
                ], $context, 'backoffice');
            }

            return [
                'ok' => true,
                'route_id' => $routeId,
                'route_stop_id' => $stopId,
                'status' => 'pending',
                'reason' => $reason,
                'reopened_at_ms' => $reopenAtMs,
                'route_progress' => $progress,
                'stop' => $after,
                'preserved' => ['submissions'=>true,'answers'=>true,'media'=>true],
            ];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Falha ao reabrir visita concluída.', $error, [
                    'context_key' => 'visit_admin_reopen',
                    'route_stop_id' => $stopId,
                ]);
            }
            return new WP_Error('fieldflow_visit_reopen_failure', 'Não foi possível reabrir a visita.', ['status' => 500]);
        }
    }

    public static function resetTimestampMs(array $stop): int {
        $meta = self::decodeMeta($stop['meta_json'] ?? '');
        return max(0, (int)($meta['visit_reset_pending']['reset_at_ms'] ?? 0));
    }

    private static function decodeMeta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function textOrNull($value): ?string {
        if ($value === null) return null;
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}
