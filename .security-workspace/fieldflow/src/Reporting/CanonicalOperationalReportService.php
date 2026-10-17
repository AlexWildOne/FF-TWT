<?php
namespace RoutesPro\Reporting;

if (!defined('ABSPATH')) exit;
if (!class_exists(DailyCommercialReportService::class)) require_once __DIR__ . '/DailyCommercialReportService.php';

/**
 * Canonical operational/commercial reporting contract.
 *
 * One deterministic snapshot feeds Analytics, email, Excel, PDF and CSV so
 * every surface tells the same operational story for the same scope.
 */
final class CanonicalOperationalReportService {
    public const VERSION = '1.2.1';
    public const CONTRACT = 'fieldflow-canonical-operational-commercial-report-v1';

    public static function build(array $data, array $filters = [], array $context = []): array {
        if (array_key_exists('location_id', $filters)) $data['location_scope_id'] = absint($filters['location_id']);
        $metrics = array_values(array_filter((array)($data['form_analytics']['metrics'] ?? []), 'is_array'));
        $routeMobility = self::routeMobilitySnapshot($data);
        $routing = (array)($routeMobility['routing'] ?? []);
        $mobility = (array)($routeMobility['mobility'] ?? []);
        $fronts = self::fronts($metrics);
        $spaces = self::extraSpaces($metrics);
        $evidence = self::evidence($metrics);
        $briefing = self::briefing($metrics, $routing);
        $orders = self::orders($filters);

        $routesTotal = (int)($data['total_routes'] ?? count($routing['routes']));
        $routesCompleted = (int)($data['completed_routes'] ?? 0);
        $visitsPlanned = (int)($data['total_stops'] ?? count($routing['stops']));
        $visitsCompleted = (int)($data['done_stops'] ?? 0);
        $visitsJustified = 0;
        $visitsRemaining = 0;
        foreach ($routing['stops'] as $stop) {
            $status = sanitize_key((string)($stop['status'] ?? ''));
            if ($status === 'failed' && trim((string)($stop['justification'] ?? '')) !== '') $visitsJustified++;
            if (!in_array($status, ['done','completed'], true) && !($status === 'failed' && trim((string)($stop['justification'] ?? '')) !== '')) $visitsRemaining++;
        }
        if (!$routing['stops']) {
            foreach ((array)($data['by_day'] ?? []) as $routeRow) {
                $visitsJustified += (int)($routeRow['stops_justified_failed'] ?? 0);
                $visitsRemaining += (int)($routeRow['stops_remaining'] ?? 0);
            }
        }
        $terminalVisits = $visitsCompleted + $visitsJustified;
        // Concretizacao mede visitas realmente executadas. Justificacoes fecham
        // operacionalmente a rota, mas nunca contam como visita concretizada.
        $executionRate = $visitsPlanned > 0 ? round(($visitsCompleted / $visitsPlanned) * 100, 1) : 0.0;
        $closureRate = $visitsPlanned > 0 ? round(($terminalVisits / $visitsPlanned) * 100, 1) : 0.0;

        $report = [
            'contract' => self::CONTRACT,
            'version' => self::VERSION,
            'generated_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'scope' => [
                'from' => (string)($filters['from'] ?? ''),
                'to' => (string)($filters['to'] ?? ''),
                'client_id' => absint($filters['client_id'] ?? 0),
                'project_id' => absint($filters['project_id'] ?? 0),
                'user_id' => absint($filters['user_id'] ?? 0),
                'location_id' => absint($filters['location_id'] ?? 0),
                'route_id' => absint($filters['route_id'] ?? 0),
                'category' => (string)($filters['category'] ?? ''),
                'product' => (string)($filters['product'] ?? ''),
                'role' => (string)($filters['role'] ?? ''),
                'client_name' => (string)($context['client_name'] ?? 'Global'),
                'project_name' => (string)($context['project_name'] ?? 'Global'),
                'user_name' => (string)($context['user_name'] ?? 'Todos'),
                'location_name' => (string)($context['location_name'] ?? 'Todas'),
            ],
            'kpis' => [
                'routes_total' => $routesTotal,
                'routes_completed' => $routesCompleted,
                'routes_completion_rate' => $routesTotal > 0 ? round(($routesCompleted / $routesTotal) * 100, 1) : 0.0,
                'visits_planned' => $visitsPlanned,
                'visits_completed' => $visitsCompleted,
                'visits_justified' => $visitsJustified,
                'visits_remaining' => $visitsRemaining,
                'execution_rate' => $executionRate,
                'operational_closure_rate' => $closureRate,
                'fronts_before' => (float)$fronts['summary']['before_total'],
                'fronts_after' => (float)$fronts['summary']['after_total'],
                'fronts_gain' => (float)$fronts['summary']['difference_total'],
                'fronts_growth_percent' => $fronts['summary']['growth_percent'],
                'fronts_locations_gain' => (int)$fronts['summary']['locations_gain'],
                'extra_spaces' => (int)$spaces['summary']['count'],
                'extra_space_locations' => (int)$spaces['summary']['locations'],
                'evidence_files' => (int)$evidence['summary']['files'],
                'evidence_locations' => (int)$evidence['summary']['locations'],
                'order_suggestions' => (int)$orders['summary']['submissions'],
                'order_lines' => (int)$orders['summary']['lines'],
                'order_boxes' => (float)$orders['summary']['boxes'],
                'order_units' => (float)$orders['summary']['units'],
                'briefing_questions' => (int)$briefing['summary']['questions'],
                'briefing_answered' => (int)$briefing['summary']['questions_with_data'],
                'briefing_target_assignments' => (int)($briefing['summary']['target_assignments'] ?? $briefing['summary']['questions']),
                'briefing_answered_assignments' => (int)($briefing['summary']['answered_assignments'] ?? $briefing['summary']['questions_with_data']),
                'briefing_pending_assignments' => (int)($briefing['summary']['pending_assignments'] ?? max(0, (int)$briefing['summary']['questions'] - (int)$briefing['summary']['questions_with_data'])),
                'briefing_target_stores' => (int)($briefing['summary']['target_stores'] ?? 0),
                'briefing_answered_stores' => (int)($briefing['summary']['answered_stores'] ?? 0),
                'briefing_coverage_percent' => (float)$briefing['summary']['coverage_percent'],
                'mobility_distance_km' => (float)($mobility['summary']['distance_km'] ?? 0),
                'mobility_energy_cost_eur' => (float)($mobility['summary']['energy_cost_eur'] ?? 0),
                'mobility_toll_cost_eur' => (float)($mobility['summary']['toll_cost_eur'] ?? 0),
                'mobility_fuel_tolls_cost_eur' => (float)($mobility['summary']['fuel_tolls_cost_eur'] ?? 0),
                'mobility_total_cost_eur' => (float)($mobility['summary']['total_mobility_cost_eur'] ?? 0),
                'mobility_closed_routes' => (int)($mobility['summary']['closed_routes'] ?? 0),
                'mobility_incomplete_routes' => (int)($mobility['summary']['incomplete_routes'] ?? 0),
            ],
            'routing' => $routing,
            'mobility' => $mobility,
            'fronts' => $fronts,
            'extra_spaces' => $spaces,
            'evidence' => $evidence,
            'orders' => $orders,
            'briefing' => $briefing,
            'lineage' => [
                'day_policy' => 'report_day_then_route_date_then_submission_date',
                'submission_statuses' => ['submitted','reviewed','approved'],
                'fronts_identity' => 'version_id,value_id,source_question_key,category,product',
                'monetary_policy' => 'EUR mobility estimates only; no order revenue or margin inferred; round monetary totals to 2 decimals',
                'mobility_scope' => !empty($data['location_scope_id']) ? 'whole_routes_containing_selected_location_not_allocated_to_store' : 'whole_routes',
            ],
        ];
        $report['commercial_analysis'] = DailyCommercialReportService::build($report);
        return $report;
    }

    /**
     * Shared route + mobility snapshot used by canonical reporting and
     * Intelligence Center system widgets. This is intentionally public so all
     * surfaces consume one mobility truth instead of rebuilding totals.
     */
    public static function routeMobilitySnapshot(array $data): array {
        $routing = self::routing($data);
        return [
            'routing' => $routing,
            'mobility' => self::mobility($routing),
        ];
    }

    private static function routing(array $data): array {
        $routes = [];
        $routeIds = [];
        foreach ((array)($data['by_day'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $routeId = absint($row['route_id'] ?? 0);
            if (!$routeId) continue;
            $routeIds[$routeId] = $routeId;
            $mobility = is_array($row['mobility_cost'] ?? null) ? (array)$row['mobility_cost'] : [];
            $reportingMobility = class_exists('\RoutesPro\Services\MobilityCostService')
                ? \RoutesPro\Services\MobilityCostService::reportingProjection($mobility)
                : $mobility;
            $routes[$routeId] = [
                'route_id' => $routeId,
                'date' => (string)($row['date'] ?? ''),
                'client_name' => (string)($row['client_name'] ?? ''),
                'project_name' => (string)($row['project_name'] ?? ''),
                'user_name' => (string)($row['user_name'] ?? ''),
                'status' => (string)($row['route_status'] ?? ''),
                'planned_stops' => (int)($row['stops'] ?? 0),
                'completed_stops' => (int)($row['stops_done'] ?? 0),
                'justified_stops' => (int)($row['stops_justified_failed'] ?? 0),
                'remaining_stops' => (int)($row['stops_remaining'] ?? 0),
                'execution_rate' => (float)($row['done_rate'] ?? 0),
                'operational_closure_rate' => (int)($row['stops'] ?? 0) > 0 ? round((((int)($row['stops_done'] ?? 0) + (int)($row['stops_justified_failed'] ?? 0)) / (int)$row['stops']) * 100, 1) : 0.0,
                'start_address' => '',
                'end_address' => '',
                'path' => '',
                'distance_km' => is_numeric($reportingMobility['reporting_distance_km'] ?? null) ? round((float)$reportingMobility['reporting_distance_km'], 2) : self::routeDistanceKm($row),
                'access_distance_km' => is_numeric($reportingMobility['reporting_access_distance_km'] ?? null) ? round((float)$reportingMobility['reporting_access_distance_km'], 2) : null,
                'inter_stop_distance_km' => is_numeric($reportingMobility['reporting_inter_stop_distance_km'] ?? null) ? round((float)$reportingMobility['reporting_inter_stop_distance_km'], 2) : null,
                'return_distance_km' => is_numeric($reportingMobility['reporting_return_distance_km'] ?? null) ? round((float)$reportingMobility['reporting_return_distance_km'], 2) : null,
                'mobility_distance_basis' => sanitize_key((string)($reportingMobility['reporting_distance_basis'] ?? '')),
                'mobility_full_route_reconciled' => !empty($reportingMobility['reporting_full_route_reconciled']) ? 1 : 0,
                'mobility_enabled' => !empty($mobility['enabled']) ? 1 : 0,
                'mobility_costs_available' => !empty($reportingMobility['reporting_costs_available']) ? 1 : 0,
                'mobility_canonical_costs_available' => !empty($mobility['costs_available']) ? 1 : 0,
                'mobility_reporting_partial' => !empty($reportingMobility['reporting_partial']) ? 1 : 0,
                'mobility_reporting_recovered' => !empty($reportingMobility['reporting_recovered']) ? 1 : 0,
                'mobility_reporting_quality' => sanitize_key((string)($reportingMobility['reporting_quality'] ?? '')),
                'mobility_profile_label' => (string)($mobility['profile_label'] ?? ''),
                'energy_cost_eur' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_energy_cost_eur'] ?? ($mobility['energy_cost_eur'] ?? null)) ? round((float)($reportingMobility['reporting_energy_cost_eur'] ?? $mobility['energy_cost_eur']), 2) : null,
                'fuel_cost_eur' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_fuel_cost_eur'] ?? ($mobility['fuel_cost_eur'] ?? null)) ? round((float)($reportingMobility['reporting_fuel_cost_eur'] ?? $mobility['fuel_cost_eur']), 2) : null,
                'electricity_cost_eur' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_electricity_cost_eur'] ?? ($mobility['electricity_cost_eur'] ?? null)) ? round((float)($reportingMobility['reporting_electricity_cost_eur'] ?? $mobility['electricity_cost_eur']), 2) : null,
                'toll_cost_eur' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_toll_cost_eur'] ?? ($mobility['toll_cost_eur'] ?? null)) ? round((float)($reportingMobility['reporting_toll_cost_eur'] ?? $mobility['toll_cost_eur']), 2) : null,
                'estimated_litres' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_estimated_litres'] ?? ($mobility['estimated_litres'] ?? null)) ? round((float)($reportingMobility['reporting_estimated_litres'] ?? $mobility['estimated_litres']), 3) : null,
                'estimated_kwh' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_estimated_kwh'] ?? ($mobility['estimated_kwh'] ?? null)) ? round((float)($reportingMobility['reporting_estimated_kwh'] ?? $mobility['estimated_kwh']), 3) : null,
                'mobility_cost_eur' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_total_mobility_cost_eur'] ?? ($mobility['total_mobility_cost_eur'] ?? null)) ? round((float)($reportingMobility['reporting_total_mobility_cost_eur'] ?? $mobility['total_mobility_cost_eur']), 2) : null,
                'total_mobility_cost_eur' => !empty($reportingMobility['reporting_costs_available']) && is_numeric($reportingMobility['reporting_total_mobility_cost_eur'] ?? ($mobility['total_mobility_cost_eur'] ?? null)) ? round((float)($reportingMobility['reporting_total_mobility_cost_eur'] ?? $mobility['total_mobility_cost_eur']), 2) : null,
            ];
        }

        $stops = [];
        if ($routeIds && isset($GLOBALS['wpdb'])) {
            global $wpdb;
            $px = $wpdb->prefix . 'routespro_';
            $ids = array_values($routeIds);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $locationSql = '';
            if (!empty($data['location_scope_id'])) {
                $locationSql = ' AND s.location_id=%d';
                $ids[] = absint($data['location_scope_id']);
            }
            $sql = "SELECT r.id AS route_id,r.meta_json,s.id AS stop_id,s.location_id,s.seq,s.planned_arrival,s.planned_departure,s.arrived_at,s.departed_at,s.duration_s,s.status,s.note,s.fail_reason,l.name AS location_name,l.address AS location_address FROM {$px}routes r INNER JOIN {$px}route_stops s ON s.route_id=r.id LEFT JOIN {$px}locations l ON l.id=s.location_id WHERE r.id IN ({$placeholders}){$locationSql} ORDER BY r.date ASC,r.id ASC,s.seq ASC,s.id ASC";
            $dbRows = $wpdb->get_results($wpdb->prepare($sql, ...$ids), ARRAY_A) ?: [];
            $pathLabels = [];
            $metaSeen = [];
            foreach ($dbRows as $row) {
                $routeId = absint($row['route_id'] ?? 0);
                if (!$routeId || !isset($routes[$routeId])) continue;
                if (!empty($data['location_scope_id']) && absint($row['location_id'] ?? 0) !== absint($data['location_scope_id'])) continue;
                if (empty($metaSeen[$routeId])) {
                    $metaSeen[$routeId] = true;
                    $meta = json_decode((string)($row['meta_json'] ?? ''), true);
                    if (!is_array($meta)) $meta = [];
                    $start = is_array($meta['start_point'] ?? null) ? $meta['start_point'] : [];
                    $end = is_array($meta['end_point'] ?? null) ? $meta['end_point'] : [];
                    $routes[$routeId]['start_address'] = trim((string)($start['address'] ?? ''));
                    $routes[$routeId]['end_address'] = trim((string)($end['address'] ?? ''));
                }
                $location = trim((string)($row['location_name'] ?? '')) ?: ('Loja #' . absint($row['location_id'] ?? 0));
                $pathLabels[$routeId][] = $location;
                $stops[] = [
                    'route_id' => $routeId,
                    'date' => (string)$routes[$routeId]['date'],
                    'client_name' => (string)$routes[$routeId]['client_name'],
                    'project_name' => (string)$routes[$routeId]['project_name'],
                    'user_name' => (string)$routes[$routeId]['user_name'],
                    'stop_id' => absint($row['stop_id'] ?? 0),
                    'location_id' => absint($row['location_id'] ?? 0),
                    'sequence' => (int)($row['seq'] ?? 0),
                    'location_name' => $location,
                    'location_address' => (string)($row['location_address'] ?? ''),
                    'planned_arrival' => (string)($row['planned_arrival'] ?? ''),
                    'planned_departure' => (string)($row['planned_departure'] ?? ''),
                    'arrived_at' => (string)($row['arrived_at'] ?? ''),
                    'departed_at' => (string)($row['departed_at'] ?? ''),
                    'duration_minutes' => !empty($row['duration_s']) ? round(((int)$row['duration_s']) / 60, 1) : null,
                    'status' => (string)($row['status'] ?? ''),
                    'justification' => trim((string)($row['fail_reason'] ?? '')),
                    'note' => trim((string)($row['note'] ?? '')),
                ];
            }
            foreach ($routes as $routeId => &$route) {
                $path = [];
                if ((string)$route['start_address'] !== '') $path[] = 'Partida: ' . $route['start_address'];
                foreach ((array)($pathLabels[$routeId] ?? []) as $label) $path[] = $label;
                if ((string)$route['end_address'] !== '') $path[] = 'Regresso: ' . $route['end_address'];
                $route['path'] = implode(' → ', $path);
            }
            unset($route);
        }

        return ['routes'=>array_values($routes),'stops'=>$stops];
    }

    private static function routeDistanceKm(array $row): ?float {
        foreach (['distance_km','total_distance_km','route_distance_km'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) return round((float)$row[$key], 2);
        }
        $mobility = is_array($row['mobility_cost'] ?? null) ? $row['mobility_cost'] : [];
        foreach (['distance_km','total_distance_km'] as $key) {
            if (isset($mobility[$key]) && is_numeric($mobility[$key])) return round((float)$mobility[$key], 2);
        }
        return null;
    }

    private static function routeMobilityCost(array $row): ?float {
        $mobility = is_array($row['mobility_cost'] ?? null) ? $row['mobility_cost'] : [];
        if (!empty($mobility['costs_available']) && isset($mobility['total_mobility_cost_eur']) && is_numeric($mobility['total_mobility_cost_eur'])) {
            return round((float)$mobility['total_mobility_cost_eur'], 2);
        }
        return null;
    }

    /**
     * Canonical mobility rollup for mail/export reporting.
     * Only operationally closed routes contribute to "executed" daily totals.
     * Costs are never recalculated here; values come from MobilityCostService.
     */
    private static function mobility(array $routing): array {
        $days = [];
        $summary = [
            'distance_km'=>0.0,
            'access_distance_km'=>0.0,
            'inter_stop_distance_km'=>0.0,
            'return_distance_km'=>0.0,
            'energy_cost_eur'=>0.0,
            'toll_cost_eur'=>0.0,
            'total_mobility_cost_eur'=>0.0,
            'fuel_tolls_cost_eur'=>0.0,
            'estimated_litres'=>0.0,
            'estimated_kwh'=>0.0,
            'closed_routes'=>0,
            'costed_routes'=>0,
            'incomplete_routes'=>0,
            'open_routes_excluded'=>0,
            'recovered_routes'=>0,
            'partial'=>false,
        ];
        foreach ((array)($routing['routes'] ?? []) as $route) {
            if (!is_array($route)) continue;
            $status = sanitize_key((string)($route['status'] ?? ''));
            $closure = (float)($route['operational_closure_rate'] ?? 0);
            $closed = in_array($status, ['completed','done'], true) || $closure >= 99.95;
            if (!$closed) { $summary['open_routes_excluded']++; continue; }
            $date = trim((string)($route['date'] ?? '')) ?: 'Sem data';
            if (!isset($days[$date])) {
                $days[$date] = [
                    'date'=>$date,
                    'distance_km'=>0.0,
                    'access_distance_km'=>0.0,
                    'inter_stop_distance_km'=>0.0,
                    'return_distance_km'=>0.0,
                    'energy_cost_eur'=>0.0,
                    'toll_cost_eur'=>0.0,
                    'total_mobility_cost_eur'=>0.0,
                    'fuel_tolls_cost_eur'=>0.0,
                    'estimated_litres'=>0.0,
                    'estimated_kwh'=>0.0,
                    'closed_routes'=>0,
                    'costed_routes'=>0,
                    'incomplete_routes'=>0,
                    'recovered_routes'=>0,
                    'partial'=>false,
                ];
            }
            $summary['closed_routes']++;
            $days[$date]['closed_routes']++;
            if (!empty($route['mobility_reporting_recovered'])) {
                $summary['recovered_routes']++;
                $days[$date]['recovered_routes']++;
            }
            $distance = self::numeric($route['distance_km'] ?? null);
            $costsAvailable = !empty($route['mobility_costs_available']);
            $routeIncomplete = $distance === null || !$costsAvailable || !empty($route['mobility_reporting_partial']);
            if ($distance !== null) {
                $days[$date]['distance_km'] += $distance;
                $summary['distance_km'] += $distance;
            }
            foreach (['access_distance_km','inter_stop_distance_km','return_distance_km'] as $componentKey) {
                $componentValue = self::numeric($route[$componentKey] ?? null);
                if ($componentValue === null) continue;
                $days[$date][$componentKey] += $componentValue;
                $summary[$componentKey] += $componentValue;
            }
            if ($costsAvailable) {
                $days[$date]['costed_routes']++;
                $summary['costed_routes']++;
                foreach (['energy_cost_eur','toll_cost_eur','total_mobility_cost_eur','estimated_litres','estimated_kwh'] as $key) {
                    $value = self::numeric($route[$key] ?? null) ?? 0.0;
                    $days[$date][$key] += $value;
                    $summary[$key] += $value;
                }
            }
            if ($routeIncomplete) {
                $days[$date]['incomplete_routes']++;
                $summary['incomplete_routes']++;
                $days[$date]['partial'] = true;
                $summary['partial'] = true;
            }
        }
        ksort($days);
        foreach ($days as &$day) {
            $day['fuel_tolls_cost_eur'] = round((float)$day['energy_cost_eur'] + (float)$day['toll_cost_eur'], 2);
            foreach (['distance_km','access_distance_km','inter_stop_distance_km','return_distance_km','energy_cost_eur','toll_cost_eur','total_mobility_cost_eur'] as $key) $day[$key] = round((float)$day[$key], 2);
            foreach (['estimated_litres','estimated_kwh'] as $key) $day[$key] = round((float)$day[$key], 3);
        }
        unset($day);
        $summary['fuel_tolls_cost_eur'] = round((float)$summary['energy_cost_eur'] + (float)$summary['toll_cost_eur'], 2);
        foreach (['distance_km','access_distance_km','inter_stop_distance_km','return_distance_km','energy_cost_eur','toll_cost_eur','total_mobility_cost_eur'] as $key) $summary[$key] = round((float)$summary[$key], 2);
        foreach (['estimated_litres','estimated_kwh'] as $key) $summary[$key] = round((float)$summary[$key], 3);
        return ['summary'=>$summary,'by_day'=>array_values($days)];
    }

    private static function fronts(array $metrics): array {
        $summary = ['before_total'=>0.0,'after_total'=>0.0,'difference_total'=>0.0,'growth_percent'=>null,'locations_gain'=>0,'up'=>0,'down'=>0,'same'=>0,'new'=>0,'removed'=>0];
        $rows = [];
        $locationsGain = [];
        $seen = [];
        foreach ($metrics as $metric) {
            $fs = is_array($metric['fronts_summary'] ?? null) ? $metric['fronts_summary'] : [];
            if (!$fs) continue;
            foreach ((array)($metric['rows'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $identity = self::factIdentity($row);
                if ($identity !== '' && isset($seen[$identity])) continue;
                if ($identity !== '') $seen[$identity] = true;
                $before = self::numeric($row['before'] ?? $row['fronts_before'] ?? null);
                $after = self::numeric($row['after'] ?? $row['fronts_after'] ?? null);
                if ($before === null && $after === null) continue;
                $difference = self::numeric($row['difference'] ?? null);
                if ($difference === null && $before !== null && $after !== null) $difference = $after - $before;
                $growth = self::numeric($row['growth'] ?? null);
                if ($growth === null && $before !== null && $before != 0 && $after !== null) $growth = (($after - $before) / $before) * 100;
                $location = (string)($row['location'] ?? $row['location_name'] ?? '');
                if (($difference ?? 0) > 0 && $location !== '') $locationsGain[!empty($row['location_id']) ? 'id:' . absint($row['location_id']) : 'name:' . $location] = true;
                $summary['before_total'] += $before ?? 0;
                $summary['after_total'] += $after ?? 0;
                $summary['difference_total'] += $difference ?? 0;
                if (($after ?? 0) > ($before ?? 0)) $summary['up']++;
                elseif (($after ?? 0) < ($before ?? 0)) $summary['down']++;
                else $summary['same']++;
                if ($before !== null && $before <= 0 && ($after ?? 0) > 0) $summary['new']++;
                if (($before ?? 0) > 0 && $after !== null && $after <= 0) $summary['removed']++;
                $rows[] = [
                    'submission_id'=>absint($row['submission_id'] ?? 0),
                    'version_id'=>absint($row['version_id'] ?? 0),
                    'value_id'=>absint($row['value_id'] ?? 0),
                    'record_id'=>absint($row['record_id'] ?? 0),
                    'source_question_key'=>(string)($row['source_question_key'] ?? ''),
                    'source_item_index'=>$row['source_item_index'] ?? null,
                    'submitted_at'=>(string)($row['submitted_at'] ?? ''),
                    'date'=>(string)($row['date'] ?? ''),
                    'route'=>(string)($row['route'] ?? ''),
                    'location'=>(string)$location,
                    'location_id'=>absint($row['location_id'] ?? 0),
                    'category'=>(string)($row['category'] ?? ''),
                    'product'=>(string)($row['product'] ?? ''),
                    'question'=>(string)($metric['question_label'] ?? $metric['metric_label'] ?? ''),
                    'before'=>$before,
                    'after'=>$after,
                    'difference'=>$difference,
                    'growth_percent'=>$growth,
                    'status'=>(string)($row['status'] ?? ''),
                ];
            }
        }
        $summary['before_total'] = round($summary['before_total'], 2);
        $summary['after_total'] = round($summary['after_total'], 2);
        $summary['difference_total'] = round($summary['difference_total'], 2);
        $summary['growth_percent'] = $summary['before_total'] != 0 ? round(($summary['difference_total'] / $summary['before_total']) * 100, 1) : null;
        $summary['locations_gain'] = count($locationsGain);
        return ['summary'=>$summary,'rows'=>$rows];
    }

    private static function extraSpaces(array $metrics): array {
        $rows = [];
        $locations = [];
        foreach ($metrics as $metric) {
            $haystack = self::normalize((string)($metric['question_label'] ?? '') . ' ' . (string)($metric['metric_label'] ?? '') . ' ' . (string)($metric['metric_key'] ?? '') . ' ' . (string)($metric['question_key'] ?? ''));
            if (!self::looksLikeExtraSpace($haystack)) continue;
            foreach ((array)($metric['rows'] ?? []) as $row) {
                if (!is_array($row)) continue;
                $value = $row['value'] ?? $row['status'] ?? $row['answer'] ?? '';
                if (!self::positiveSignal($value, $row)) continue;
                $location = trim((string)($row['location'] ?? $row['location_name'] ?? ''));
                if ($location !== '') $locations[$location] = true;
                $rows[] = [
                    'date'=>(string)($row['date'] ?? ''),
                    'route'=>(string)($row['route'] ?? ''),
                    'location'=>$location,
                    'location_id'=>absint($row['location_id'] ?? 0),
                    'type'=>(string)($metric['question_label'] ?? $metric['metric_label'] ?? 'Espaço extra'),
                    'product'=>(string)($row['product'] ?? ''),
                    'category'=>(string)($row['category'] ?? ''),
                    'value'=>is_scalar($value) ? (string)$value : '',
                    'notes'=>(string)($row['note'] ?? $row['notes'] ?? ''),
                ];
            }
        }
        return ['summary'=>['count'=>count($rows),'locations'=>count($locations)],'rows'=>$rows];
    }

    private static function evidence(array $metrics): array {
        $rows = [];
        $seen = [];
        $locations = [];
        foreach ($metrics as $metric) {
            $question = (string)($metric['question_label'] ?? $metric['metric_label'] ?? 'Evidência');
            foreach ((array)($metric['evidence_groups'] ?? []) as $group) {
                if (!is_array($group)) continue;
                $location = trim((string)($group['location'] ?? ''));
                if ($location !== '') $locations[$location] = true;
                foreach ((array)($group['files'] ?? []) as $file) {
                    if (!is_array($file)) continue;
                    $url = trim((string)($file['url'] ?? ''));
                    $attachmentId = absint($file['attachment_id'] ?? $file['id'] ?? 0);
                    $key = $attachmentId > 0 ? 'id:' . $attachmentId : 'url:' . $url;
                    if ($key === 'url:' || isset($seen[$key])) continue;
                    $seen[$key] = true;
                    $rows[] = [
                        'date'=>(string)($file['date'] ?? (!empty($file['submitted_at']) ? substr((string)$file['submitted_at'], 0, 10) : '')),
                        'route_id'=>absint($file['route_id'] ?? 0),
                        'location'=>$location,
                        'question'=>$question,
                        'name'=>(string)($file['name'] ?? 'Evidência'),
                        'url'=>$url,
                        'mime'=>(string)($file['mime'] ?? $file['type'] ?? ''),
                        'attachment_id'=>$attachmentId,
                    ];
                }
            }
        }
        return ['summary'=>['files'=>count($rows),'locations'=>count($locations)],'rows'=>$rows];
    }

    private static function orders(array $filters): array {
        $rows = [];
        if (class_exists('\RoutesPro\Admin\FormSubmissions') && method_exists('\RoutesPro\Admin\FormSubmissions', 'orderSuggestionExportRows')) {
            try {
                foreach ((array)\RoutesPro\Admin\FormSubmissions::orderSuggestionExportRows(array_merge($filters, ['include_lineage'=>true])) as $row) {
                    if (!is_array($row)) continue;
                    $rows[] = [
                        'submission_id'=>absint($row[0] ?? 0),
                        'location_id'=>absint($row['location_id'] ?? 0),
                        'route_id'=>absint($row['route_id'] ?? 0),
                        'submitted_at'=>(string)($row['submitted_at'] ?? ''),
                        'date'=>(string)($row[1] ?? ''),
                        'client'=>(string)($row[2] ?? ''),
                        'project'=>(string)($row[3] ?? ''),
                        'user'=>(string)($row[4] ?? ''),
                        'location'=>(string)($row[5] ?? ''),
                        'question'=>(string)($row[6] ?? ''),
                        'category'=>(string)($row[7] ?? ''),
                        'product'=>(string)($row[9] ?? ''),
                        'quantity'=>(float)($row[13] ?? 0),
                        'reference'=>(string)($row[8] ?? ''),
                        'item_type'=>(string)($row[10] ?? ''),
                        'capacity'=>(string)($row[11] ?? ''),
                        'units_per_case'=>(float)($row[12] ?? 0),
                        'boxes'=>(float)($row[13] ?? 0),
                        'units'=>(float)($row[14] ?? 0),
                        'notes'=>(string)($row[15] ?? ''),
                    ];
                }
            } catch (\Throwable $e) {
                $rows = [];
            }
        }
        $submissions = [];
        $boxes = 0.0;
        $units = 0.0;
        foreach ($rows as $row) {
            if (!empty($row['submission_id'])) $submissions[(int)$row['submission_id']] = true;
            $boxes += (float)($row['boxes'] ?? 0);
            $units += (float)($row['units'] ?? 0);
        }
        return ['summary'=>['submissions'=>count($submissions),'lines'=>count($rows),'boxes'=>round($boxes,2),'units'=>round($units,2)],'rows'=>$rows];
    }

    private static function briefing(array $metrics, array $routing = []): array {
        $routeLocationIds = [];
        foreach ((array)($routing['stops'] ?? []) as $stop) {
            if (!is_array($stop)) continue;
            $locationId = absint($stop['location_id'] ?? 0);
            if ($locationId > 0) $routeLocationIds[$locationId] = true;
        }

        // 2.2.554: prefer the smart briefing rollup because it carries the exact
        // question-to-store assignment map. Daily mail/report scope is the
        // intersection of active briefing assignments and stores present in the
        // routes represented by this canonical snapshot.
        $smartGroups = [];
        foreach ($metrics as $metric) {
            if (empty($metric['smart_briefing_rollup']) || empty($metric['briefing_questions']) || !is_array($metric['briefing_questions'])) continue;
            foreach ((array)$metric['briefing_questions'] as $question) {
                if (!is_array($question)) continue;
                $key = sanitize_key((string)($question['key'] ?? ''));
                if ($key === '') $key = md5((string)($question['label'] ?? 'briefing'));
                if (!isset($smartGroups[$key])) {
                    $smartGroups[$key] = [
                        'key'=>$key,
                        'dashboard'=>(string)($metric['dashboard_title'] ?? 'Briefing'),
                        'question'=>(string)($question['label'] ?? 'Pergunta briefing'),
                        'type'=>(string)($question['type'] ?? ''),
                        'target'=>[],
                        'answered'=>[],
                        'evidence_files'=>(int)($question['image_count'] ?? 0),
                    ];
                }
                foreach ((array)($question['target_assignment_keys'] ?? []) as $assignmentKey) {
                    $assignmentKey = trim((string)$assignmentKey);
                    $locationId = self::briefingAssignmentLocationId($assignmentKey);
                    if ($assignmentKey === '' || ($routeLocationIds && ($locationId <= 0 || empty($routeLocationIds[$locationId])))) continue;
                    $smartGroups[$key]['target'][$assignmentKey] = $assignmentKey;
                }
                foreach ((array)($question['answered_assignment_keys'] ?? []) as $assignmentKey) {
                    $assignmentKey = trim((string)$assignmentKey);
                    $locationId = self::briefingAssignmentLocationId($assignmentKey);
                    if ($assignmentKey === '' || ($routeLocationIds && ($locationId <= 0 || empty($routeLocationIds[$locationId])))) continue;
                    $smartGroups[$key]['answered'][$assignmentKey] = $assignmentKey;
                }
            }
        }

        if ($smartGroups) {
            $rows = [];
            $questionKeys = [];
            $withData = [];
            $allTargetAssignments = [];
            $allAnsweredAssignments = [];
            $allTargetStores = [];
            $allAnsweredStores = [];
            foreach ($smartGroups as $key => $group) {
                $target = array_values((array)$group['target']);
                if (!$target) continue; // Not mapped to any store in this report scope.
                $answered = array_values(array_intersect($target, array_values((array)$group['answered'])));
                $targetStores = [];
                $answeredStores = [];
                foreach ($target as $assignmentKey) {
                    $locationId = self::briefingAssignmentLocationId((string)$assignmentKey);
                    if ($locationId > 0) $targetStores[$locationId] = $locationId;
                    $allTargetAssignments[$assignmentKey] = true;
                }
                foreach ($answered as $assignmentKey) {
                    $locationId = self::briefingAssignmentLocationId((string)$assignmentKey);
                    if ($locationId > 0) $answeredStores[$locationId] = $locationId;
                    $allAnsweredAssignments[$assignmentKey] = true;
                }
                foreach ($targetStores as $locationId) $allTargetStores[$locationId] = $locationId;
                foreach ($answeredStores as $locationId) $allAnsweredStores[$locationId] = $locationId;
                $targetCount = count($target);
                $answeredCount = count($answered);
                $pendingCount = max(0, $targetCount - $answeredCount);
                $coverage = $targetCount > 0 ? round(($answeredCount / $targetCount) * 100, 1) : 0.0;
                $questionKeys[$key] = true;
                if ($answeredCount > 0) $withData[$key] = true;
                $rows[] = [
                    'dashboard'=>(string)$group['dashboard'],
                    'question'=>(string)$group['question'],
                    'question_key'=>$key,
                    'type'=>(string)$group['type'],
                    'value'=>$answeredCount . ' / ' . $targetCount,
                    'record_count'=>$answeredCount,
                    'note'=>$pendingCount > 0 ? $pendingCount . ' resposta(s) pendente(s) nas lojas alvo desta rota.' : 'Cobertura completa nas lojas alvo desta rota.',
                    'evidence_files'=>(int)$group['evidence_files'],
                    'target_assignments'=>$targetCount,
                    'answered_assignments'=>$answeredCount,
                    'pending_assignments'=>$pendingCount,
                    'target_stores'=>count($targetStores),
                    'answered_stores'=>count($answeredStores),
                    'coverage_percent'=>$coverage,
                ];
            }
            $questions = count($questionKeys);
            $answeredQuestions = count($withData);
            $targetAssignments = count($allTargetAssignments);
            $answeredAssignments = count(array_intersect_key($allAnsweredAssignments, $allTargetAssignments));
            return ['summary'=>[
                'questions'=>$questions,
                'questions_with_data'=>$answeredQuestions,
                'target_assignments'=>$targetAssignments,
                'answered_assignments'=>$answeredAssignments,
                'pending_assignments'=>max(0, $targetAssignments - $answeredAssignments),
                'target_stores'=>count($allTargetStores),
                'answered_stores'=>count($allAnsweredStores),
                'coverage_percent'=>$targetAssignments > 0 ? round(($answeredAssignments / $targetAssignments) * 100, 1) : 0.0,
                'coverage_definition'=>'Respostas de briefing apenas nas lojas alvo mapeadas às perguntas e presentes no âmbito/rotas deste relatório.',
                'scope_mode'=>'question_store_mapping_x_report_routes',
            ],'rows'=>$rows];
        }

        // Compatibility fallback for reports produced without the smart rollup.
        $rows = [];
        $questionKeys = [];
        $withData = [];
        foreach ($metrics as $metric) {
            if (empty($metric['briefing_priority']) && sanitize_key((string)($metric['source_type'] ?? '')) !== 'briefing') continue;
            $key = (string)($metric['question_key'] ?? $metric['metric_key'] ?? '');
            if ($key === '') $key = md5((string)($metric['question_label'] ?? $metric['metric_label'] ?? 'briefing'));
            $questionKeys[$key] = true;
            $recordCount = (int)($metric['record_count'] ?? 0);
            if ($recordCount > 0) $withData[$key] = true;
            $rows[] = [
                'dashboard'=>(string)($metric['dashboard_title'] ?? 'Briefing'),
                'question'=>(string)($metric['question_label'] ?? $metric['metric_label'] ?? 'Pergunta briefing'),
                'question_key'=>$key,
                'type'=>(string)($metric['question_type'] ?? ''),
                'value'=>is_scalar($metric['value'] ?? null) ? (string)($metric['value'] ?? '') : '',
                'record_count'=>$recordCount,
                'note'=>(string)($metric['note'] ?? ''),
                'evidence_files'=>(int)($metric['evidence_count'] ?? $metric['image_count'] ?? 0),
            ];
        }
        $questions = count($questionKeys);
        $answered = count($withData);
        return ['summary'=>[
            'questions'=>$questions,
            'questions_with_data'=>$answered,
            'target_assignments'=>$questions,
            'answered_assignments'=>$answered,
            'pending_assignments'=>max(0, $questions - $answered),
            'target_stores'=>0,
            'answered_stores'=>0,
            'coverage_percent'=>$questions > 0 ? round(($answered / $questions) * 100, 1) : 0.0,
            'coverage_definition'=>'Compatibilidade: perguntas prioritárias de briefing com pelo menos um registo no âmbito selecionado.',
            'scope_mode'=>'legacy_metric_fallback',
        ],'rows'=>$rows];
    }

    private static function briefingAssignmentLocationId(string $assignmentKey): int {
        $pos = strrpos($assignmentKey, ':');
        if ($pos === false) return 0;
        return absint(substr($assignmentKey, $pos + 1));
    }

    private static function looksLikeExtraSpace(string $value): bool {
        foreach (['espaco extra','espacos extra','conquista','expositor','display','ilha','topo','ponta','arca','implementacao','ativacao'] as $needle) {
            if (strpos($value, $needle) !== false) return true;
        }
        return false;
    }

    private static function positiveSignal($value, array $row = []): bool {
        if (is_numeric($value)) return (float)$value > 0;
        $text = self::normalize(is_scalar($value) ? (string)$value : '');
        if (preg_match('/^0(?:[.,]0+)?\s+(?:imagens?|fotos?|ficheiros?|images?|files?)$/u', $text)) return false;
        if ($text === '') {
            foreach (['count','qty','quantity','image_count'] as $key) if (isset($row[$key]) && is_numeric($row[$key]) && (float)$row[$key] > 0) return true;
            return false;
        }
        foreach (['nao','não','no','0','false','n/a','na'] as $negative) if ($text === self::normalize($negative)) return false;
        foreach (['nao ','sem ','ausente','nao implement','nao conquist','nao existe','nao aplic'] as $negativePrefix) {
            if (strpos($text, self::normalize($negativePrefix)) === 0) return false;
        }
        foreach (['sim','yes','1','true','conquistado','implementado','presente','ativo','feito','ok'] as $positive) if ($text === self::normalize($positive)) return true;
        return true;
    }

    private static function normalize(string $value): string {
        $value = trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
        $map = ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
        return strtr($value, $map);
    }

    private static function numeric($value): ?float {
        if ($value === null || $value === '') return null;
        if (is_numeric($value)) return (float)$value;
        $text = str_replace([' ', '%'], '', (string)$value);
        if (substr_count($text, ',') === 1 && substr_count($text, '.') === 0) $text = str_replace(',', '.', $text);
        return is_numeric($text) ? (float)$text : null;
    }

    private static function factIdentity(array $row): string {
        $version = absint($row['version_id'] ?? 0);
        $value = absint($row['value_id'] ?? 0);
        // Never merge different observations merely because their displayed values match.
        if (!$version || !$value || !isset($row['source_item_index'])) return '';
        return implode('|', [$version, $value, (string)($row['source_question_key'] ?? ''), (string)$row['source_item_index']]);
    }
}
