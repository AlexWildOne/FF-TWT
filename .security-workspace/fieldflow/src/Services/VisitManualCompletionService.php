<?php
namespace RoutesPro\Services;

use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Backoffice correction for visits that happened in the field but were never
 * operationally closed. If the merchandiser left a server-synchronised form
 * draft, it is promoted through the normal submission pipeline before the stop
 * is closed. No synthetic answers are invented.
 */
final class VisitManualCompletionService {
    private const HISTORY_LIMIT = 12;

    /**
     * @return array|WP_Error
     */
    public static function complete(int $stopId, string $completedAt = '', int $userId = 0, string $reason = '', bool $allowWithoutDraft = false) {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stopId = absint($stopId);
        $userId = $userId > 0 ? $userId : get_current_user_id();
        $reason = sanitize_textarea_field($reason);
        $operationalSubmissionSql = SubmissionStatusPolicy::operationalSql();
        if ($reason === '') $reason = 'Visita realizada no terreno e não concluída na aplicação';

        if ($stopId <= 0) {
            return new WP_Error('fieldflow_visit_manual_invalid', 'A visita indicada não é válida.', ['status' => 400]);
        }

        // Preflight fora da transação. A promoção do rascunho usa o pipeline
        // normal de submissão e abre a sua própria transação, por isso nunca a
        // executamos dentro do lock da rota.
        $preflight = $wpdb->get_row($wpdb->prepare(
            "SELECT rs.*,r.client_id,r.project_id,r.owner_user_id,r.date AS route_date,r.status AS route_status
             FROM {$px}route_stops rs
             INNER JOIN {$px}routes r ON r.id=rs.route_id
             WHERE rs.id=%d LIMIT 1",
            $stopId
        ), ARRAY_A) ?: [];
        if (!$preflight) return new WP_Error('fieldflow_visit_manual_missing', 'A visita já não existe.', ['status' => 404]);
        $preflightStatus = sanitize_key((string)($preflight['status'] ?? 'pending')) ?: 'pending';
        if (in_array($preflightStatus, ['done','completed'], true)) {
            return new WP_Error('fieldflow_visit_manual_done', 'Esta visita já está concluída.', ['status' => 409]);
        }
        if (in_array($preflightStatus, ['cancelled','canceled'], true)) {
            return new WP_Error('fieldflow_visit_manual_cancelled', 'Esta visita está cancelada. Reabre a rota antes de a concluir.', ['status' => 409]);
        }
        $preflightRouteDate = sanitize_text_field((string)($preflight['route_date'] ?? ''));
        if ($preflightRouteDate !== '' && $preflightRouteDate > current_time('Y-m-d')) {
            return new WP_Error('fieldflow_visit_manual_future', 'Uma visita futura não pode ser concluída manualmente.', ['status' => 409]);
        }
        $completionMysql = self::normaliseCompletionTime($completedAt, $preflightRouteDate);
        if (is_wp_error($completionMysql)) return $completionMysql;
        $preflightArrivedAt = self::textOrNull($preflight['arrived_at'] ?? null);
        if ($preflightArrivedAt !== null && $completionMysql < $preflightArrivedAt) {
            return new WP_Error('fieldflow_visit_manual_before_arrival', 'A hora de conclusão não pode ser anterior ao check-in já registado.', ['status' => 422]);
        }

        $existingSubmissionCount = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$px}form_submissions WHERE route_stop_id=%d AND {$operationalSubmissionSql}",
            $stopId
        ));
        $draftRecovery = [
            'found' => false,
            'applied' => false,
            'draft_id' => 0,
            'submission_id' => 0,
            'answers' => 0,
            'files' => 0,
            'status' => 'none',
        ];
        if (class_exists('\RoutesPro\Forms\DraftService') && class_exists('\RoutesPro\Forms\Forms')) {
            $draft = \RoutesPro\Forms\DraftService::recovery_draft_for_stop($stopId);
            if ($draft) {
                $payload = is_array($draft['_payload'] ?? null) ? $draft['_payload'] : [];
                $draftRecovery = [
                    'found' => true,
                    'applied' => false,
                    'draft_id' => (int)($draft['id'] ?? 0),
                    'submission_id' => 0,
                    'answers' => (int)($draft['_answer_count'] ?? 0),
                    'files' => (int)($draft['_file_count'] ?? 0),
                    'status' => 'found',
                ];
                // Se já existe um reporte final e este rascunho não estava em
                // modo de edição, tratamo-lo como sobra antiga e não o usamos.
                $shouldRecover = $existingSubmissionCount <= 0 || !empty($payload['editMode']);
                if ($shouldRecover) {
                    $recovered = \RoutesPro\Forms\Forms::submit_recovered_draft(
                        (int)$draft['id'],
                        $userId,
                        $completionMysql,
                        $reason
                    );
                    if (is_wp_error($recovered)) {
                        return new WP_Error(
                            'fieldflow_visit_manual_draft_recovery_failed',
                            'A visita não foi fechada porque existe informação preenchida que ainda não conseguiu ser recuperada. ' . $recovered->get_error_message(),
                            ['status' => 409, 'draft_id' => (int)$draft['id'], 'cause' => $recovered->get_error_code()]
                        );
                    }
                    $draftRecovery['applied'] = true;
                    $draftRecovery['status'] = 'submitted';
                    $draftRecovery['submission_id'] = absint($recovered['submission_id'] ?? 0);
                } else {
                    $draftRecovery['status'] = 'skipped_existing_submission';
                }
            }
        }

        if ($existingSubmissionCount <= 0 && empty($draftRecovery['found']) && !$allowWithoutDraft) {
            return new WP_Error(
                'fieldflow_visit_manual_no_recoverable_data',
                'Não foi encontrado um reporte final nem um rascunho sincronizado no servidor. Se o merchandiser tiver respostas ou fotografias apenas no dispositivo, o backoffice não as consegue recuperar. Confirma explicitamente o fecho sem dados apenas depois de verificares essa situação.',
                ['status' => 409, 'requires_explicit_confirmation' => true]
            );
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('fieldflow_visit_manual_transaction', 'Não foi possível iniciar a correção segura da visita.', ['status' => 500]);
        }

        try {
            $stop = $wpdb->get_row($wpdb->prepare(
                "SELECT rs.*,r.client_id,r.project_id,r.owner_user_id,r.date AS route_date,r.status AS route_status
                 FROM {$px}route_stops rs
                 INNER JOIN {$px}routes r ON r.id=rs.route_id
                 WHERE rs.id=%d LIMIT 1 FOR UPDATE",
                $stopId
            ), ARRAY_A) ?: [];

            if (!$stop) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_manual_missing', 'A visita já não existe.', ['status' => 404]);
            }

            $routeId = absint($stop['route_id'] ?? 0);
            $status = sanitize_key((string)($stop['status'] ?? 'pending')) ?: 'pending';
            if (in_array($status, ['done', 'completed'], true) && empty($draftRecovery['applied'])) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_manual_done', 'Esta visita já está concluída.', ['status' => 409]);
            }
            if (in_array($status, ['cancelled', 'canceled'], true)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_manual_cancelled', 'Esta visita está cancelada. Reabre a rota antes de a concluir.', ['status' => 409]);
            }

            $routeDate = sanitize_text_field((string)($stop['route_date'] ?? ''));
            $existingArrivedAt = self::textOrNull($stop['arrived_at'] ?? null);

            $meta = self::decodeMeta($stop['meta_json'] ?? '');
            $history = is_array($meta['manual_completion_history'] ?? null) ? array_values($meta['manual_completion_history']) : [];
            $submissionCount = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$px}form_submissions WHERE route_stop_id=%d AND {$operationalSubmissionSql}",
                $stopId
            ));
            $auditEntry = [
                'completed_at' => $completionMysql,
                'corrected_at' => current_time('mysql'),
                'corrected_at_gmt' => current_time('mysql', true),
                'corrected_by' => $userId,
                'reason' => $reason,
                'source' => 'backoffice',
                'submission_count' => $submissionCount,
                'previous_status' => $status,
                'previous_arrived_at' => self::textOrNull($stop['arrived_at'] ?? null),
                'previous_departed_at' => self::textOrNull($stop['departed_at'] ?? null),
                'draft_recovery' => $draftRecovery,
            ];
            $history[] = $auditEntry;
            $meta['manual_completion_history'] = array_slice($history, -self::HISTORY_LIMIT);
            $meta['manual_completion'] = $auditEntry;

            $arrivedAt = $existingArrivedAt ?: $completionMysql;
            $duration = null;
            $arrivedTs = strtotime($arrivedAt . ' UTC');
            $departedTs = strtotime($completionMysql . ' UTC');
            if ($arrivedTs !== false && $departedTs !== false) {
                $duration = max(0, $departedTs - $arrivedTs);
            }
            if ($duration === null) $duration = max(0, (int)($stop['duration_s'] ?? 0));

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$px}route_stops
                 SET status='done',arrived_at=%s,departed_at=%s,duration_s=%d,fail_reason=NULL,meta_json=%s
                 WHERE id=%d",
                $arrivedAt,
                $completionMysql,
                $duration,
                wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $stopId
            ));
            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_manual_update', $wpdb->last_error ?: 'Não foi possível concluir a visita.', ['status' => 500]);
            }

            $progress = RouteProgressService::sync($routeId, true);
            if (is_wp_error($progress)) {
                $wpdb->query('ROLLBACK');
                return $progress;
            }

            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_visit_manual_commit', 'Não foi possível confirmar a correção da visita.', ['status' => 500]);
            }
            if (is_array($progress)
                && sanitize_key((string)($progress['route_status'] ?? '')) === 'completed'
                && !empty($progress['operationally_complete'])
                && class_exists('\RoutesPro\Admin\ReportAutomations')) {
                \RoutesPro\Admin\ReportAutomations::queueDailyCloseForRoute($routeId);
            }

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

            // Everything below is post-commit telemetry/cache hygiene. A failure here
            // must never turn a successfully persisted correction into an API error.
            try {
                RouteOptimizationService::invalidateUserRouteCache(absint($stop['owner_user_id'] ?? 0));
                if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
                    \RoutesPro\Core\AuditLog::record('visit.manual_completion', 'visit', $stopId, $stop, $after, $context, [
                        'reason' => $reason,
                        'completed_at' => $completionMysql,
                        'submission_count' => $submissionCount,
                    ]);
                }
                if (class_exists('\\RoutesPro\\Core\\EventBus')) {
                    \RoutesPro\Core\EventBus::emit('visit.manual_completion', [
                        'before' => $stop,
                        'visit' => $after,
                        'reason' => $reason,
                        'completed_at' => $completionMysql,
                        'submission_count' => $submissionCount,
                    ], $context, 'backoffice');
                }
            } catch (\Throwable $postCommitError) {
                if (class_exists('\\RoutesPro\\Support\\Logger')) {
                    \RoutesPro\Support\Logger::captureThrowable('A visita foi concluída, mas falhou uma tarefa pós-conclusão.', $postCommitError, [
                        'context_key' => 'visit_manual_completion_post_commit',
                        'route_stop_id' => $stopId,
                    ]);
                }
            }

            return [
                'ok' => true,
                'route_id' => $routeId,
                'route_stop_id' => $stopId,
                'status' => 'done',
                'completed_at' => $completionMysql,
                'reason' => $reason,
                'submission_count' => $submissionCount,
                'draft_recovery' => $draftRecovery,
                'route_progress' => $progress,
                'stop' => $after,
            ];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('Falha ao concluir manualmente uma visita.', $error, [
                    'context_key' => 'visit_manual_completion',
                    'route_stop_id' => $stopId,
                ]);
            }
            return new WP_Error('fieldflow_visit_manual_failure', 'Não foi possível concluir manualmente a visita.', ['status' => 500]);
        }
    }

    /** @return string|WP_Error */
    private static function normaliseCompletionTime(string $value, string $routeDate = '') {
        $value = trim(sanitize_text_field($value));
        if ($value === '') {
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $routeDate) ? $routeDate : current_time('Y-m-d');
            $time = $date === current_time('Y-m-d') ? current_time('H:i:s') : '18:00:00';
            return $date . ' ' . $time;
        }
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) $value .= ':00';
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value, wp_timezone());
        $errors = \DateTime::getLastErrors();
        if (!$dt || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
            return new WP_Error('fieldflow_visit_manual_time', 'Indica uma data e hora válidas para a conclusão.', ['status' => 422]);
        }
        if ($dt->format('Y-m-d H:i:s') > current_time('Y-m-d H:i:s')) {
            return new WP_Error('fieldflow_visit_manual_time_future', 'A hora de conclusão não pode estar no futuro.', ['status' => 422]);
        }
        if ($routeDate !== '' && $dt->format('Y-m-d') !== $routeDate) {
            return new WP_Error('fieldflow_visit_manual_wrong_day', 'A data de conclusão deve coincidir com a data da rota.', ['status' => 422]);
        }
        return $dt->format('Y-m-d H:i:s');
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
