<?php
namespace RoutesPro\Services;

use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Reconcilia uma edicao do plano de rota sem destruir o estado operacional.
 *
 * Paragens existentes mantem o mesmo ID, estado, horas, evidencias e eventos.
 * Paragens novas nascem pendentes. Paragens removidas saem do plano ativo,
 * mas os reportes continuam guardados e o snapshot fica no historico da rota.
 */
class RoutePlanSyncService {
    /** @return array|WP_Error */
    public static function scheduleWindow(string $date, int $arrival, int $duration, array $item) {
        $start = trim((string)($item['time_window_start'] ?? ''));
        $end = trim((string)($item['time_window_end'] ?? ''));
        if ($start === '') $start = trim((string)($item['window_start'] ?? ''));
        if ($end === '') $end = trim((string)($item['window_end'] ?? ''));
        foreach ([$start, $end] as $time) {
            if ($time !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
                return new WP_Error('route_schedule_window_invalid', 'Janela horária inválida.', ['status'=>422, 'location_id'=>(int)($item['id'] ?? $item['location_id'] ?? 0)]);
            }
        }
        $open = $start !== '' ? strtotime($date . ' ' . $start) : null;
        $close = $end !== '' ? strtotime($date . ' ' . $end) : null;
        $serviceStart = $open !== null ? max($arrival, $open) : $arrival;
        $departure = $serviceStart + max(0, $duration);
        if (($open !== null && $close !== null && $open > $close) || ($close !== null && $departure > $close)) {
            return new WP_Error('route_schedule_window_infeasible', 'A visita não cabe na janela horária da loja.', [
                'status'=>422, 'location_id'=>(int)($item['id'] ?? $item['location_id'] ?? 0),
                'arrival'=>date('Y-m-d H:i:s', $serviceStart), 'departure'=>date('Y-m-d H:i:s', $departure),
                'window_start'=>$start, 'window_end'=>$end,
            ]);
        }
        return ['arrival'=>$serviceStart, 'departure'=>$departure, 'waiting_s'=>max(0, $serviceStart - $arrival)];
    }

    /** Rebuild planned times only; operational fields are never included. @return array|WP_Error */
    public static function scheduleOrderedStops(string $date, array $stops, array $start, array $end, array $matrix, string $routeStart = '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            return new WP_Error('route_schedule_date_invalid', 'Data da rota inválida.', ['status'=>422]);
        }
        $cursor = strtotime($routeStart !== '' ? $routeStart : $date . ' 09:00:00');
        if (!$cursor || date('Y-m-d', $cursor) !== $date) return new WP_Error('route_schedule_start_invalid', 'Hora de partida inválida.', ['status'=>422]);
        $previous = $start;
        $previous['_matrix_key'] = '__start__';
        $end['_matrix_key'] = '__end__';
        $scheduled = [];
        foreach ($stops as $seq => $stop) {
            $item = (array)($stop['item'] ?? []);
            $edge = \RoutesPro\Services\Planning\RoutingMatrixService::edge($matrix, $previous, $item);
            if (($edge['status'] ?? '') === 'impossible' || !is_numeric($edge['travel_min'] ?? null)) {
                return new WP_Error('route_schedule_leg_unavailable', 'Não é possível validar o percurso entre paragens.', ['status'=>422, 'seq'=>$seq, 'leg_status'=>$edge['status'] ?? 'unavailable']);
            }
            $duration = max(0, (int)($stop['duration_s'] ?? ((int)($item['service_time_min'] ?? 0) * 60)));
            $window = self::scheduleWindow($date, $cursor + (int)round((float)$edge['travel_min'] * 60), $duration, $item);
            if (is_wp_error($window)) return $window;
            $stop['seq'] = $seq;
            $stop['planned_arrival'] = date('Y-m-d H:i:s', $window['arrival']);
            $stop['planned_departure'] = date('Y-m-d H:i:s', $window['departure']);
            $stop['duration_s'] = $duration;
            $stop['meta_json'] = array_replace(self::decode_meta($stop['meta_json'] ?? []), [
                'distance_from_prev_km'=>$edge['distance_km'], 'travel_min_from_prev'=>$edge['travel_min'],
                'waiting_min'=>round($window['waiting_s'] / 60, 1), 'leg_status'=>$edge['status'],
                'distance_source'=>$edge['source'], 'arrival_point'=>$previous,
            ]);
            unset($stop['item']);
            $scheduled[] = $stop;
            $cursor = $window['departure'];
            $previous = $item;
        }
        $edge = \RoutesPro\Services\Planning\RoutingMatrixService::edge($matrix, $previous, $end);
        if (($edge['status'] ?? '') === 'impossible' || !is_numeric($edge['travel_min'] ?? null)) {
            return new WP_Error('route_schedule_return_unavailable', 'Não é possível validar o regresso da rota.', ['status'=>422, 'leg_status'=>$edge['status'] ?? 'unavailable']);
        }
        return $scheduled;
    }

    /**
     * @return array|WP_Error
     */
    public static function sync(int $route_id, array $planned_stops) {
        global $wpdb;

        $route_id = absint($route_id);
        if ($route_id <= 0) {
            return new WP_Error('route_plan_invalid', 'Rota invalida para reconciliacao.', ['status' => 400]);
        }

        $px = $wpdb->prefix . 'routespro_';
        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT id, meta_json FROM {$px}routes WHERE id=%d LIMIT 1 FOR UPDATE",
            $route_id
        ), ARRAY_A);
        if (!$route) {
            return new WP_Error('route_plan_missing', 'A rota ja nao existe.', ['status' => 404]);
        }

        $existing = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC, id ASC FOR UPDATE",
            $route_id
        ), ARRAY_A) ?: [];

        $by_id = [];
        $by_location = [];
        $protected_existing_ids = [];
        foreach ($existing as $row) {
            $stop_id = absint($row['id'] ?? 0);
            $location_id = absint($row['location_id'] ?? 0);
            if ($stop_id <= 0) continue;
            $by_id[$stop_id] = $row;
            if ($location_id > 0) $by_location[$location_id][] = $stop_id;
            if (self::has_operational_state($row)) $protected_existing_ids[] = $stop_id;
        }

        $used = [];
        $ordered_stop_ids = [];
        $preserved = 0;
        $created = 0;
        $protected_requested_ids = [];

        foreach (array_values($planned_stops) as $index => $planned) {
            if (!is_array($planned)) continue;
            $location_id = absint($planned['location_id'] ?? 0);
            if ($location_id <= 0) continue;

            $requested_stop_id = absint($planned['route_stop_id'] ?? 0);
            $matched = null;

            if ($requested_stop_id > 0 && isset($by_id[$requested_stop_id]) && empty($used[$requested_stop_id])) {
                $candidate = $by_id[$requested_stop_id];
                if ((int)($candidate['location_id'] ?? 0) === $location_id) {
                    $matched = $candidate;
                }
            }

            if (!$matched && !empty($by_location[$location_id])) {
                foreach ($by_location[$location_id] as $candidate_id) {
                    if (!empty($used[$candidate_id]) || empty($by_id[$candidate_id])) continue;
                    $matched = $by_id[$candidate_id];
                    break;
                }
            }

            $planned_meta = self::decode_meta($planned['meta_json'] ?? []);
            $seq = isset($planned['seq']) ? max(0, (int)$planned['seq']) : $index;
            $planned_arrival = self::nullable_datetime($planned['planned_arrival'] ?? null);
            $planned_departure = self::nullable_datetime($planned['planned_departure'] ?? null);
            $planned_duration = max(0, (int)($planned['duration_s'] ?? 0));

            if ($matched) {
                $stop_id = absint($matched['id'] ?? 0);
                $is_operational = self::has_operational_state($matched);
                if ($is_operational) {
                    // Numa rota ativa, o histórico operacional é imutável. Apenas a posição
                    // sequencial pode ser reconciliada para manter a lista coerente.
                    $fields = ['seq' => $seq];
                    $formats = ['%d'];
                    $protected_requested_ids[] = $stop_id;
                } else {
                    $existing_meta = self::decode_meta($matched['meta_json'] ?? []);
                    $merged_meta = array_replace_recursive($existing_meta, $planned_meta);
                    $fields = [
                        'location_id' => $location_id,
                        'seq' => $seq,
                        'planned_arrival' => array_key_exists('planned_arrival', $planned) ? $planned_arrival : ($matched['planned_arrival'] ?? null),
                        'planned_departure' => array_key_exists('planned_departure', $planned) ? $planned_departure : ($matched['planned_departure'] ?? null),
                        'meta_json' => wp_json_encode($merged_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'duration_s' => array_key_exists('duration_s', $planned) ? $planned_duration : max(0, (int)($matched['duration_s'] ?? 0)),
                    ];
                    $formats = ['%d', '%d', '%s', '%s', '%s', '%d'];
                }

                $ok = $wpdb->update($px . 'route_stops', $fields, ['id' => $stop_id], $formats, ['%d']);
                if ($ok === false) {
                    return new WP_Error('route_plan_update', 'Nao foi possivel atualizar uma paragem existente.', ['status' => 500]);
                }
                $used[$stop_id] = true;
                $ordered_stop_ids[] = $stop_id;
                $preserved++;
            } else {
                $ok = $wpdb->insert($px . 'route_stops', [
                    'route_id' => $route_id,
                    'location_id' => $location_id,
                    'seq' => $seq,
                    'planned_arrival' => $planned_arrival,
                    'planned_departure' => $planned_departure,
                    'duration_s' => $planned_duration,
                    'status' => 'pending',
                    'meta_json' => wp_json_encode($planned_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ], ['%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s']);
                if (!$ok) {
                    return new WP_Error('route_plan_insert', 'Nao foi possivel adicionar uma nova paragem.', ['status' => 500]);
                }
                $stop_id = (int)$wpdb->insert_id;
                $used[$stop_id] = true;
                $ordered_stop_ids[] = $stop_id;
                $created++;
            }

            if (class_exists('\\RoutesPro\\Services\\RouteSnapshotService')) {
                RouteSnapshotService::capture($route_id, $stop_id, $location_id);
            }
        }

        $missing_protected = array_values(array_diff($protected_existing_ids, array_keys($used)));
        if ($missing_protected) {
            return new WP_Error(
                'route_plan_operational_stop_protected',
                'As lojas já executadas ou em curso não podem ser removidas do plano ativo.',
                ['status' => 409, 'protected_stop_ids' => $missing_protected]
            );
        }
        if ($protected_requested_ids !== $protected_existing_ids) {
            return new WP_Error(
                'route_plan_operational_order_protected',
                'A ordem das lojas já executadas ou em curso não pode ser alterada.',
                ['status' => 409, 'protected_stop_ids' => $protected_existing_ids]
            );
        }

        $removed_rows = [];
        foreach ($existing as $row) {
            $stop_id = absint($row['id'] ?? 0);
            if ($stop_id <= 0 || !empty($used[$stop_id])) continue;
            $removed_rows[] = $row;
        }

        if ($removed_rows) {
            self::archive_removed_stops($route_id, $route, $removed_rows);
            foreach ($removed_rows as $row) {
                $stop_id = absint($row['id'] ?? 0);
                if ($stop_id <= 0) continue;
                self::detach_live_links($row);
                $ok = $wpdb->delete($px . 'route_stops', ['id' => $stop_id], ['%d']);
                if ($ok === false) {
                    return new WP_Error('route_plan_remove', 'Nao foi possivel remover uma paragem do plano ativo.', ['status' => 500]);
                }
            }
        }

        return [
            'route_id' => $route_id,
            'preserved' => $preserved,
            'created' => $created,
            'removed' => count($removed_rows),
            'stop_ids' => $ordered_stop_ids,
        ];
    }

    /**
     * Remove uma paragem isolada preservando o reporte e o historico.
     *
     * @return array|WP_Error
     */
    public static function remove_stop(int $stop_id) {
        global $wpdb;
        $stop_id = absint($stop_id);
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$px}route_stops WHERE id=%d LIMIT 1 FOR UPDATE",
            $stop_id
        ), ARRAY_A);
        if (!$row) return new WP_Error('route_stop_missing', 'A paragem ja nao existe.', ['status' => 404]);
        if (self::has_operational_state($row)) {
            return new WP_Error(
                'route_stop_operational_protected',
                'Uma loja já executada ou em curso não pode ser removida da rota ativa.',
                ['status' => 409, 'stop_id' => $stop_id]
            );
        }

        $route_id = absint($row['route_id'] ?? 0);
        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT id, meta_json FROM {$px}routes WHERE id=%d LIMIT 1 FOR UPDATE",
            $route_id
        ), ARRAY_A) ?: ['id' => $route_id, 'meta_json' => ''];
        self::archive_removed_stops($route_id, $route, [$row]);
        self::detach_live_links($row);
        $ok = $wpdb->delete($px . 'route_stops', ['id' => $stop_id], ['%d']);
        if ($ok === false) return new WP_Error('route_stop_remove', 'Nao foi possivel remover a paragem.', ['status' => 500]);

        return ['route_id' => $route_id, 'stop_id' => $stop_id, 'removed' => 1];
    }

    public static function stop_is_mutable(array $stop): bool {
        return !self::has_operational_state($stop);
    }

    private static function has_operational_state(array $stop): bool {
        $status = sanitize_key((string)($stop['status'] ?? 'pending'));
        if (in_array($status, ['done', 'completed', 'failed', 'in_progress', 'active', 'started'], true)) return true;
        return !empty($stop['arrived_at']) || !empty($stop['departed_at']);
    }

    private static function archive_removed_stops(int $route_id, array $route, array $rows): void {
        global $wpdb;
        if (!$rows) return;
        $px = $wpdb->prefix . 'routespro_';
        $meta = self::decode_meta($route['meta_json'] ?? []);
        $history = is_array($meta['route_edit_history']['removed_stops'] ?? null)
            ? $meta['route_edit_history']['removed_stops']
            : [];

        foreach ($rows as $row) {
            $stop_id = absint($row['id'] ?? 0);
            if ($stop_id <= 0) continue;
            $submission_ids = [];
            if (self::table_has_column($px . 'form_submissions', 'route_stop_id')) {
                $submission_ids = array_values(array_map('absint', $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$px}form_submissions WHERE route_stop_id=%d ORDER BY id ASC",
                    $stop_id
                )) ?: []));
            }
            $event_count = 0;
            if (self::table_exists($px . 'events')) {
                $event_count = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$px}events WHERE route_stop_id=%d",
                    $stop_id
                ));
            }
            $history[] = [
                'removed_at' => current_time('mysql'),
                'removed_by' => get_current_user_id(),
                'stop_id' => $stop_id,
                'location_id' => absint($row['location_id'] ?? 0),
                'seq' => (int)($row['seq'] ?? 0),
                'status' => sanitize_key((string)($row['status'] ?? 'pending')),
                'planned_arrival' => $row['planned_arrival'] ?? null,
                'planned_departure' => $row['planned_departure'] ?? null,
                'arrived_at' => $row['arrived_at'] ?? null,
                'departed_at' => $row['departed_at'] ?? null,
                'submission_ids' => $submission_ids,
                'event_count' => $event_count,
                'meta' => self::decode_meta($row['meta_json'] ?? []),
            ];
        }

        $history = array_slice($history, -100);
        if (!isset($meta['route_edit_history']) || !is_array($meta['route_edit_history'])) $meta['route_edit_history'] = [];
        $meta['route_edit_history']['removed_stops'] = $history;
        $meta['route_edit_history']['last_edited_at'] = current_time('mysql');
        $meta['route_edit_history']['last_edited_by'] = get_current_user_id();
        $wpdb->update(
            $px . 'routes',
            ['meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['id' => $route_id],
            ['%s'],
            ['%d']
        );
    }

    private static function detach_live_links(array $stop): void {
        global $wpdb;
        $stop_id = absint($stop['id'] ?? 0);
        $route_id = absint($stop['route_id'] ?? 0);
        $location_id = absint($stop['location_id'] ?? 0);
        if ($stop_id <= 0) return;
        $px = $wpdb->prefix . 'routespro_';
        $submissions = $px . 'form_submissions';
        if (self::table_has_column($submissions, 'route_stop_id')) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$submissions} SET route_stop_id=0 WHERE route_stop_id=%d",
                $stop_id
            ));
        }

        $records = $px . 'form_records';
        if (self::table_has_column($records, 'latest_route_stop_id')) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$records} SET latest_route_stop_id=NULL WHERE latest_route_stop_id=%d",
                $stop_id
            ));
        }


        $bindings = $px . 'form_bindings';
        if (self::table_has_column($bindings, 'stop_id')) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$bindings}
                 SET stop_id=NULL,
                     route_id=CASE WHEN COALESCE(route_id,0)=0 THEN %d ELSE route_id END,
                     location_id=CASE WHEN COALESCE(location_id,0)=0 THEN %d ELSE location_id END
                 WHERE stop_id=%d",
                $route_id,
                $location_id,
                $stop_id
            ));
        }
    }

    private static function table_exists(string $table): bool {
        static $cache = [];
        if (array_key_exists($table, $cache)) return $cache[$table];
        global $wpdb;
        return $cache[$table] = ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    }

    private static function table_has_column(string $table, string $column): bool {
        static $cache = [];
        $key = $table . '|' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        global $wpdb;
        if (!self::table_exists($table)) return $cache[$key] = false;
        return $cache[$key] = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }

    private static function nullable_datetime($value): ?string {
        $value = is_scalar($value) ? trim((string)$value) : '';
        return ($value !== '' && strtotime($value)) ? $value : null;
    }

    private static function decode_meta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
