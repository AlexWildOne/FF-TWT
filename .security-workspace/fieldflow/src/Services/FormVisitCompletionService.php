<?php
namespace RoutesPro\Services;

use WP_Error;

if (!defined('ABSPATH')) exit;

class FormVisitCompletionService {
    /**
     * Marca a paragem associada a uma submissao como visitada/concluida.
     *
     * O metodo e chamado depois de a submissao e as respostas estarem confirmadas.
     * Uma falha operacional na rota nao anula o reporte e fica disponivel para
     * reconciliacao posterior.
     *
     * @return array|WP_Error
     */
    public static function complete_from_submission(int $submission_id, array $submission) {
        global $wpdb;

        $route_id = absint($submission['route_id'] ?? 0);
        $route_stop_id = absint($submission['route_stop_id'] ?? 0);
        $location_id = absint($submission['location_id'] ?? 0);
        $form_id = absint($submission['form_id'] ?? 0);
        $user_id = absint($submission['user_id'] ?? get_current_user_id());
        $submitted_at = self::normalise_mysql_datetime((string)($submission['submitted_at'] ?? ''));
        $px = $wpdb->prefix . 'routespro_';

        // A submissao persistida e a fonte autoritativa do fecho da visita.
        // Alem de completar identificadores em falta, valida que o reporte esta
        // num estado operacional final antes de tocar na rota.
        $submission_status = sanitize_key((string)($submission['status'] ?? ''));
        if ($submission_id > 0) {
            $stored_submission = $wpdb->get_row($wpdb->prepare(
                "SELECT form_id, route_id, route_stop_id, location_id, user_id, submitted_at, status
                 FROM {$px}form_submissions WHERE id=%d LIMIT 1",
                $submission_id
            ), ARRAY_A);
            if (!$stored_submission) {
                return new WP_Error('visit_submission_missing', 'O reporte concluido ja nao existe.', ['status' => 409]);
            }
            if ($form_id <= 0) $form_id = absint($stored_submission['form_id'] ?? 0);
            if ($route_id <= 0) $route_id = absint($stored_submission['route_id'] ?? 0);
            if ($route_stop_id <= 0) $route_stop_id = absint($stored_submission['route_stop_id'] ?? 0);
            if ($location_id <= 0) $location_id = absint($stored_submission['location_id'] ?? 0);
            if ($user_id <= 0) $user_id = absint($stored_submission['user_id'] ?? 0);
            if (empty($submission['submitted_at']) && !empty($stored_submission['submitted_at'])) {
                $submitted_at = self::normalise_mysql_datetime((string)$stored_submission['submitted_at']);
            }
            if ($submission_status === '') $submission_status = sanitize_key((string)($stored_submission['status'] ?? ''));
            if (!VisitCompletionPolicy::submissionClosesVisit($submission_status)) {
                return new WP_Error(
                    'visit_submission_not_operational',
                    'A visita so pode ser concluida por um reporte final valido.',
                    ['status' => 409, 'submission_status' => VisitCompletionPolicy::normaliseSubmissionStatus($submission_status)]
                );
            }
        }

        if ($route_id <= 0 && $route_stop_id <= 0) {
            return [
                'applied' => false,
                'reason' => 'no_route_context',
                'route_id' => 0,
                'route_stop_id' => 0,
                'route_completed_now' => false,
            ];
        }

        if ($route_stop_id <= 0 && $route_id > 0 && $location_id > 0) {
            $candidate_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$px}route_stops WHERE route_id=%d AND location_id=%d ORDER BY id ASC LIMIT 2",
                $route_id,
                $location_id
            ));
            $candidate_ids = array_values(array_filter(array_map('absint', is_array($candidate_ids) ? $candidate_ids : [])));
            if (count($candidate_ids) === 1) {
                $route_stop_id = (int)$candidate_ids[0];
            } elseif (count($candidate_ids) > 1) {
                return new WP_Error(
                    'visit_stop_ambiguous',
                    'Existem varias paragens da mesma loja nesta rota. A paragem deve ser identificada antes de submeter.',
                    ['status' => 409]
                );
            }
        }

        if ($route_stop_id <= 0) {
            if ($route_id > 0 && $location_id > 0) {
                return new WP_Error(
                    'visit_stop_missing',
                    'Nao foi encontrada uma paragem desta loja na rota selecionada.',
                    ['status' => 409]
                );
            }
            return [
                'applied' => false,
                'reason' => 'no_exact_stop',
                'route_id' => $route_id,
                'route_stop_id' => 0,
                'route_completed_now' => false,
            ];
        }

        $stop = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1 FOR UPDATE",
            $route_stop_id
        ), ARRAY_A);
        if (!$stop && $route_id > 0 && $location_id > 0) {
            $candidate_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$px}route_stops WHERE route_id=%d AND location_id=%d ORDER BY id ASC LIMIT 2",
                $route_id,
                $location_id
            ));
            $candidate_ids = array_values(array_filter(array_map('absint', is_array($candidate_ids) ? $candidate_ids : [])));
            if (count($candidate_ids) === 1) {
                $route_stop_id = (int)$candidate_ids[0];
                $stop = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1 FOR UPDATE",
                    $route_stop_id
                ), ARRAY_A);
            } elseif (count($candidate_ids) > 1) {
                return new WP_Error('visit_stop_ambiguous', 'Existem varias paragens desta loja na rota. Atualiza a rota antes de submeter.', ['status' => 409]);
            }
        }
        if (!$stop) {
            return new WP_Error('visit_stop_missing', 'A paragem associada ao formulario ja nao existe.', ['status' => 409]);
        }

        $resolved_route_id = absint($stop['route_id'] ?? 0);
        $resolved_location_id = absint($stop['location_id'] ?? 0);
        if ($route_id > 0 && $resolved_route_id !== $route_id) {
            return new WP_Error('visit_stop_route_mismatch', 'A paragem nao pertence a rota da submissao.', ['status' => 409]);
        }
        if ($location_id > 0 && $resolved_location_id !== $location_id) {
            return new WP_Error('visit_stop_location_mismatch', 'A paragem nao pertence a loja da submissao.', ['status' => 409]);
        }

        $route_id = $resolved_route_id;
        $location_id = $resolved_location_id;

        if ($submission_id > 0) {
            $submission_linked = $wpdb->update(
                $px . 'form_submissions',
                [
                    'route_id' => $route_id,
                    'route_stop_id' => $route_stop_id,
                    'location_id' => $location_id,
                ],
                ['id' => $submission_id],
                ['%d', '%d', '%d'],
                ['%d']
            );
            if ($submission_linked === false) {
                return new WP_Error('visit_submission_link', 'Nao foi possivel ligar o reporte a paragem concluida.', ['status' => 500]);
            }
        }

        $previous_status = sanitize_key((string)($stop['status'] ?? 'pending'));
        $already_completed = in_array($previous_status, ['done', 'completed'], true);
        $stop_meta = self::decode_meta($stop['meta_json'] ?? '');
        // A new explicit submission is the only authority that resolves an
        // administrative reopen. The historical report is never deleted.
        if (!empty($stop_meta['visit_admin_reopen']['active'])) {
            $reopen = (array)$stop_meta['visit_admin_reopen'];
            $reopen['active'] = 0;
            $reopen['resolved_at'] = current_time('mysql');
            $reopen['resolved_at_gmt'] = current_time('mysql', true);
            $reopen['resolved_by'] = $user_id;
            $reopen['resolved_submission_id'] = $submission_id;
            $stop_meta['visit_admin_reopen'] = $reopen;
        }
        $completion_meta = is_array($stop_meta['form_visit_completion'] ?? null)
            ? $stop_meta['form_visit_completion']
            : [];

        if (!$already_completed && empty($completion_meta['first_submission_id'])) {
            $completion_meta['first_submission_id'] = $submission_id;
            $completion_meta['first_form_id'] = $form_id;
            $completion_meta['first_user_id'] = $user_id;
            $completion_meta['first_completed_at'] = $submitted_at;
            $completion_meta['previous_status'] = $previous_status;
        }
        $completion_meta['source'] = 'form_submission';
        $completion_meta['last_submission_id'] = $submission_id;
        $completion_meta['last_form_id'] = $form_id;
        $completion_meta['last_user_id'] = $user_id;
        $completion_meta['last_submitted_at'] = $submitted_at;
        $stop_meta['form_visit_completion'] = $completion_meta;

        $stop_updates = [
            'meta_json' => wp_json_encode($stop_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $stop_formats = ['%s'];

        if (!$already_completed) {
            $stop_updates['status'] = 'done';
            $stop_formats[] = '%s';
            if (empty($stop['departed_at'])) {
                $stop_updates['departed_at'] = $submitted_at;
                $stop_formats[] = '%s';
            }
            if (!empty($stop['arrived_at']) && empty($stop['duration_s'])) {
                $arrival_ts = strtotime((string)$stop['arrived_at']);
                $departure_ts = strtotime($submitted_at);
                if ($arrival_ts && $departure_ts && $departure_ts >= $arrival_ts) {
                    $stop_updates['duration_s'] = max(0, $departure_ts - $arrival_ts);
                    $stop_formats[] = '%d';
                }
            }
        }

        $stop_updated = $wpdb->update(
            $px . 'route_stops',
            $stop_updates,
            ['id' => $route_stop_id],
            $stop_formats,
            ['%d']
        );
        if ($stop_updated === false) {
            return new WP_Error('visit_stop_update', 'Nao foi possivel marcar a visita como concluida.', ['status' => 500]);
        }

        $completed_now = !$already_completed;
        if ($completed_now) {
            self::insert_completion_event($route_stop_id, $user_id, [
                'submission_id' => $submission_id,
                'form_id' => $form_id,
                'route_id' => $route_id,
                'location_id' => $location_id,
                'completed_at' => $submitted_at,
                'previous_status' => $previous_status,
                'status' => 'done',
            ]);
        }

        $route_progress = RouteProgressService::sync($route_id);
        if (is_wp_error($route_progress)) {
            return new WP_Error(
                'visit_route_update',
                $route_progress->get_error_message() ?: 'Nao foi possivel sincronizar o estado da rota.',
                ['status' => 500]
            );
        }

        // O fecho diario e idempotente e fica tambem agendado de forma duravel.
        // Se este caminho correr dentro de uma transacao maior, o worker volta a
        // confirmar o estado da rota depois do commit antes de enviar.
        if (($route_progress['route_status'] ?? '') === 'completed'
            && !empty($route_progress['operationally_complete'])
            && class_exists('\RoutesPro\Admin\ReportAutomations')) {
            \RoutesPro\Admin\ReportAutomations::queueDailyCloseForRoute($route_id);
        }

        // Confirma a persistencia da rota depois do commit do reporte. Evita
        // considerar a visita concluida quando outro fluxo alterou a paragem.
        $verified_status = sanitize_key((string)$wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$px}route_stops WHERE id=%d LIMIT 1",
            $route_stop_id
        )));
        if (!in_array($verified_status, ['done', 'completed'], true)) {
            return new WP_Error('visit_stop_update', 'A paragem nao ficou concluida na rota.', ['status' => 500]);
        }

        if (class_exists('\RoutesPro\Core\AuditLog')) {
            try {
                $afterStop = $wpdb->get_row($wpdb->prepare(
                    "SELECT id,route_id,location_id,status,arrived_at,departed_at,duration_s,meta_json FROM {$px}route_stops WHERE id=%d LIMIT 1",
                    $route_stop_id
                ), ARRAY_A) ?: [];
                \RoutesPro\Core\AuditLog::record('visit.completed_from_submission', 'visit', $route_stop_id,
                    [
                        'status' => $previous_status,
                        'arrived_at' => $stop['arrived_at'] ?? null,
                        'departed_at' => $stop['departed_at'] ?? null,
                        'duration_s' => $stop['duration_s'] ?? null,
                    ],
                    $afterStop,
                    [
                        'route_id' => $route_id,
                        'route_stop_id' => $route_stop_id,
                        'location_id' => $location_id,
                        'submission_id' => $submission_id,
                        'actor_user_id' => $user_id,
                    ],
                    ['form_id' => $form_id, 'completed_now' => $completed_now]
                );
            } catch (\Throwable $e) {
                if (class_exists('\RoutesPro\Support\Logger')) {
                    \RoutesPro\Support\Logger::captureThrowable('Falha não bloqueante na auditoria da visita concluída.', $e, [
                        'context_key' => 'visit_completion_audit',
                        'route_id' => $route_id,
                    ]);
                }
            }
        }


        return [
            'applied' => true,
            'completed_now' => $completed_now,
            'already_completed' => $already_completed,
            'route_id' => $route_id,
            'route_stop_id' => $route_stop_id,
            'location_id' => $location_id,
            'stop_status' => $verified_status,
            'route_status' => (string)($route_progress['route_status'] ?? ''),
            'total_stops' => (int)($route_progress['total_stops'] ?? 0),
            'completed_stops' => (int)($route_progress['completed_stops'] ?? 0),
            'remaining_stops' => (int)($route_progress['remaining_stops'] ?? 0),
            'route_completed_now' => !empty($route_progress['route_completed_now']),
            'operationally_complete' => !empty($route_progress['operationally_complete']),
            'daily_close_queued' => (($route_progress['route_status'] ?? '') === 'completed' && !empty($route_progress['operationally_complete'])),
            'completed_at' => $submitted_at,
        ];
    }

    private static function insert_completion_event(int $route_stop_id, int $user_id, array $payload): void {
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_events';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ((string)$exists !== $table) return;

        $wpdb->insert($table, [
            'route_stop_id' => $route_stop_id,
            'user_id' => $user_id,
            'event_type' => 'form_submission_visit',
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], ['%d', '%d', '%s', '%s']);
    }

    private static function decode_meta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function normalise_mysql_datetime(string $value): string {
        $value = trim($value);
        if ($value !== '' && strtotime($value)) return $value;
        return current_time('mysql');
    }
}
