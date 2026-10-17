<?php
namespace RoutesPro\Analytics;

use RoutesPro\Reporting\CanonicalOperationalReportService;

if (!defined('ABSPATH')) exit;

/**
 * System-backed Analytics widgets for route execution and mobility.
 *
 * These widgets never recalculate routing or costs. They consume the same
 * canonical route/mobility projection used by Daily Close, Excel and PDF.
 */
final class RouteMobilityWidgetService {
    public const VERSION = '1.0.0';
    public const CONTRACT = 'fieldflow-route-mobility-dashboard-widgets-v1';
    public const SOURCE_TYPE = 'route_mobility';

    public static function catalog(): array {
        return [
            'route_execution' => [
                'label' => 'Execução de rotas',
                'description' => 'Rotas fechadas face às rotas do período, com detalhe por rota.',
                'chart_type' => 'bar', 'aggregation' => 'avg', 'dimension' => 'submitted_at',
                'show_kpi' => 1, 'show_table' => 1, 'show_empty' => 1,
            ],
            'visit_execution' => [
                'label' => 'Execução de visitas',
                'description' => 'PDVs executados face às visitas planeadas no período.',
                'chart_type' => 'line', 'aggregation' => 'avg', 'dimension' => 'submitted_at',
                'show_kpi' => 1, 'show_table' => 1, 'show_empty' => 1,
            ],
            'route_distance_km' => [
                'label' => 'Km executados',
                'description' => 'Quilometragem das rotas operacionalmente fechadas.',
                'chart_type' => 'bar', 'aggregation' => 'sum', 'dimension' => 'submitted_at',
                'show_kpi' => 1, 'show_table' => 1, 'show_empty' => 1,
            ],
            'route_distance_breakdown' => [
                'label' => 'Composição dos km da rota',
                'description' => 'Acesso, deslocação entre PDVs e regresso, reconciliados com a rota integral.',
                'chart_type' => 'bar', 'aggregation' => 'sum', 'dimension' => 'route_id',
                'show_kpi' => 1, 'show_table' => 1, 'show_empty' => 1,
            ],
            'route_energy_cost_eur' => [
                'label' => 'Combustível / energia',
                'description' => 'Custo estimado de combustível ou energia das rotas fechadas.',
                'chart_type' => 'bar', 'aggregation' => 'sum', 'dimension' => 'submitted_at',
                'show_kpi' => 1, 'show_table' => 1, 'show_empty' => 1,
            ],
            'route_toll_cost_eur' => [
                'label' => 'Portagens',
                'description' => 'Portagens estimadas das rotas fechadas.',
                'chart_type' => 'bar', 'aggregation' => 'sum', 'dimension' => 'submitted_at',
                'show_kpi' => 1, 'show_table' => 1, 'show_empty' => 1,
            ],
            'route_mobility_cost_eur' => [
                'label' => 'Custo mobilidade',
                'description' => 'Combustível/energia + portagens, alinhado com o resumo do Daily Close.',
                'chart_type' => 'line', 'aggregation' => 'sum', 'dimension' => 'submitted_at',
                'show_kpi' => 1, 'show_table' => 1, 'show_empty' => 1,
            ],
            'route_daily_mobility' => [
                'label' => 'Mobilidade por dia',
                'description' => 'Leitura diária de km, acesso, PDVs, regresso, combustível/energia e portagens.',
                'chart_type' => 'bar', 'aggregation' => 'sum', 'dimension' => 'submitted_at',
                'show_kpi' => 0, 'show_table' => 1, 'show_empty' => 1,
            ],
        ];
    }

    public static function isSystemSettings(array $settings): bool {
        return sanitize_key((string)($settings['metric_source'] ?? '')) === self::SOURCE_TYPE
            && isset(self::catalog()[sanitize_key((string)($settings['system_metric_key'] ?? ''))]);
    }

    public static function questionKey(string $metricKey, int $dashboardId = 0): string {
        $metricKey = sanitize_key($metricKey);
        return sanitize_key('__system_' . $metricKey . '_d' . max(0, $dashboardId));
    }

    public static function buildMetric(array $binding, array $settings, array $dashboard, array $stats): array {
        $catalog = self::catalog();
        $systemKey = sanitize_key((string)($settings['system_metric_key'] ?? ''));
        if ($systemKey === '' || empty($catalog[$systemKey])) return [];
        $definition = $catalog[$systemKey];

        $scoped = self::scopeStats($stats, $settings);
        $snapshot = CanonicalOperationalReportService::routeMobilitySnapshot($scoped);
        $routing = is_array($snapshot['routing'] ?? null) ? $snapshot['routing'] : [];
        $mobility = is_array($snapshot['mobility'] ?? null) ? $snapshot['mobility'] : [];
        $summary = is_array($mobility['summary'] ?? null) ? $mobility['summary'] : [];
        $days = array_values(array_filter((array)($mobility['by_day'] ?? []), 'is_array'));
        $routes = array_values(array_filter((array)($routing['routes'] ?? []), 'is_array'));

        usort($routes, static function(array $a, array $b): int {
            $date = strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
            return $date !== 0 ? $date : ((int)($a['route_id'] ?? 0) <=> (int)($b['route_id'] ?? 0));
        });

        $routeTotal = count($routes);
        $routeClosed = 0;
        $visitsPlanned = 0;
        $visitsCompleted = 0;
        $visitsJustified = 0;
        $executionByDay = [];
        $visitByDay = [];
        foreach ($routes as $route) {
            $planned = (int)($route['planned_stops'] ?? 0);
            $completed = (int)($route['completed_stops'] ?? 0);
            $justified = (int)($route['justified_stops'] ?? 0);
            $visitsPlanned += $planned;
            $visitsCompleted += $completed;
            $visitsJustified += $justified;
            $closed = self::routeClosed($route);
            if ($closed) $routeClosed++;
            $date = (string)($route['date'] ?? 'Sem data');
            if (!isset($executionByDay[$date])) $executionByDay[$date] = ['total'=>0,'closed'=>0];
            $executionByDay[$date]['total']++;
            if ($closed) $executionByDay[$date]['closed']++;
            if (!isset($visitByDay[$date])) $visitByDay[$date] = ['planned'=>0,'completed'=>0];
            $visitByDay[$date]['planned'] += $planned;
            $visitByDay[$date]['completed'] += $completed;
        }
        $routeCompletionRate = $routeTotal > 0 ? round(($routeClosed / $routeTotal) * 100, 1) : 0.0;
        $visitExecutionRate = $visitsPlanned > 0 ? round(($visitsCompleted / $visitsPlanned) * 100, 1) : 0.0;

        $showEmpty = array_key_exists('show_empty', $settings) ? !empty($settings['show_empty']) : !empty($definition['show_empty']);
        $recordCount = in_array($systemKey, ['route_execution','visit_execution'], true) ? $routeTotal : (int)($summary['closed_routes'] ?? 0);
        if (!$showEmpty && $recordCount === 0) return [];

        $metric = [
            'binding_id' => (int)($binding['id'] ?? 0),
            'dashboard_id' => (int)($dashboard['id'] ?? 0),
            'dashboard_title' => (string)($dashboard['title'] ?? 'Analytics operacional'),
            'dashboard_sort_order' => (int)($dashboard['sort_order'] ?? 999),
            'question_key' => (string)($binding['question_key'] ?? self::questionKey($systemKey, (int)($dashboard['id'] ?? 0))),
            'question_keys' => [],
            'source_question_count' => 0,
            'question_labels' => [],
            'question_label' => (string)$definition['label'],
            'question_type' => 'system_route_mobility',
            'metric_key' => (string)($binding['metric_key'] ?? $systemKey),
            'metric_label' => trim((string)($binding['metric_label'] ?? '')) ?: (string)$definition['label'],
            'chart_type' => (string)($binding['chart_type'] ?? $definition['chart_type']),
            'aggregation' => (string)($binding['aggregation'] ?? $definition['aggregation']),
            'aggregation_label' => self::aggregationLabel((string)($binding['aggregation'] ?? $definition['aggregation'])),
            'dimension' => (string)($binding['dimension'] ?? $definition['dimension']),
            'dimension_label' => self::dimensionLabel((string)($binding['dimension'] ?? $definition['dimension'])),
            'secondary_dimension' => '',
            'secondary_dimension_label' => '',
            'store_group_id' => 0,
            'store_group_name' => '',
            'show_empty' => $showEmpty ? 1 : 0,
            'show_table' => array_key_exists('show_table', $settings) ? (int)!empty($settings['show_table']) : (int)!empty($definition['show_table']),
            'show_kpi' => array_key_exists('show_kpi', $settings) ? (int)!empty($settings['show_kpi']) : (int)!empty($definition['show_kpi']),
            'sort_order' => (int)($settings['sort_order'] ?? 10),
            'series' => [],
            'rows' => [],
            'value' => null,
            'note' => '',
            'record_count' => $recordCount,
            'source_type' => self::SOURCE_TYPE,
            'system_metric' => 1,
            'system_metric_key' => $systemKey,
            'system_metric_family' => in_array($systemKey, ['route_execution','visit_execution'], true) ? 'route_execution' : 'route_mobility',
            'canonical_metric_key' => $systemKey,
            'orchestration_pinned' => !empty($settings['orchestration_pinned']) ? 1 : 0,
            'submission_integrity' => 'canonical_route_mobility',
            'ai_reason' => 'Métrica operacional canónica de rota e mobilidade.',
        ];

        $routeRows = self::routeRows($routes);
        $dailyRows = self::dailyRows($days);
        $partial = !empty($summary['partial']);
        $recovered = (int)($summary['recovered_routes'] ?? 0) > 0;
        $qualityNote = self::qualityNote($partial, $recovered, (int)($summary['incomplete_routes'] ?? 0));

        switch ($systemKey) {
            case 'route_execution':
                $metric['value'] = self::pct($routeCompletionRate);
                $metric['note'] = $routeClosed . '/' . $routeTotal . ' rotas fechadas';
                foreach ($executionByDay as $date => $item) {
                    $metric['series'][] = ['label'=>self::dateLabel($date),'value'=>$item['total'] > 0 ? round(($item['closed'] / $item['total']) * 100, 1) : 0.0];
                }
                $metric['rows'] = $routeRows;
                break;
            case 'visit_execution':
                $metric['value'] = self::pct($visitExecutionRate);
                $metric['note'] = $visitsCompleted . '/' . $visitsPlanned . ' visitas executadas' . ($visitsJustified > 0 ? ' · ' . $visitsJustified . ' justificadas' : '');
                foreach ($visitByDay as $date => $item) {
                    $metric['series'][] = ['label'=>self::dateLabel($date),'value'=>$item['planned'] > 0 ? round(($item['completed'] / $item['planned']) * 100, 1) : 0.0];
                }
                $metric['rows'] = $routeRows;
                break;
            case 'route_distance_km':
                $metric['value'] = self::km((float)($summary['distance_km'] ?? 0));
                $metric['note'] = (int)($summary['closed_routes'] ?? 0) . ' rota(s) fechada(s)' . $qualityNote;
                foreach ($days as $day) $metric['series'][] = ['label'=>self::dateLabel((string)($day['date'] ?? '')),'value'=>(float)($day['distance_km'] ?? 0)];
                $metric['rows'] = $dailyRows;
                break;
            case 'route_distance_breakdown':
                $metric['value'] = self::km((float)($summary['distance_km'] ?? 0));
                $metric['note'] = 'Acesso + entre PDVs + regresso' . $qualityNote;
                $metric['series'] = [
                    ['label'=>'Acesso','value'=>(float)($summary['access_distance_km'] ?? 0)],
                    ['label'=>'Entre PDVs','value'=>(float)($summary['inter_stop_distance_km'] ?? 0)],
                    ['label'=>'Regresso','value'=>(float)($summary['return_distance_km'] ?? 0)],
                ];
                $metric['rows'] = $dailyRows;
                break;
            case 'route_energy_cost_eur':
                $costed = (int)($summary['costed_routes'] ?? 0) > 0;
                $metric['value'] = $costed ? self::eur((float)($summary['energy_cost_eur'] ?? 0)) : 'Por calcular';
                $metric['note'] = 'Combustível/energia estimado' . $qualityNote;
                foreach ($days as $day) $metric['series'][] = ['label'=>self::dateLabel((string)($day['date'] ?? '')),'value'=>(float)($day['energy_cost_eur'] ?? 0)];
                $metric['rows'] = $dailyRows;
                break;
            case 'route_toll_cost_eur':
                $costed = (int)($summary['costed_routes'] ?? 0) > 0;
                $metric['value'] = $costed ? self::eur((float)($summary['toll_cost_eur'] ?? 0)) : 'Por calcular';
                $metric['note'] = 'Portagens estimadas' . $qualityNote;
                foreach ($days as $day) $metric['series'][] = ['label'=>self::dateLabel((string)($day['date'] ?? '')),'value'=>(float)($day['toll_cost_eur'] ?? 0)];
                $metric['rows'] = $dailyRows;
                break;
            case 'route_mobility_cost_eur':
                $costed = (int)($summary['costed_routes'] ?? 0) > 0;
                $metric['value'] = $costed ? self::eur((float)($summary['fuel_tolls_cost_eur'] ?? 0)) : 'Por calcular';
                $metric['note'] = 'Combustível/energia + portagens' . $qualityNote;
                foreach ($days as $day) $metric['series'][] = ['label'=>self::dateLabel((string)($day['date'] ?? '')),'value'=>(float)($day['fuel_tolls_cost_eur'] ?? 0)];
                $metric['rows'] = $dailyRows;
                break;
            case 'route_daily_mobility':
                $metric['value'] = self::km((float)($summary['distance_km'] ?? 0));
                $metric['note'] = count($days) . ' dia(s) com mobilidade fechada' . $qualityNote;
                foreach ($days as $day) $metric['series'][] = ['label'=>self::dateLabel((string)($day['date'] ?? '')),'value'=>(float)($day['distance_km'] ?? 0)];
                $metric['rows'] = $dailyRows;
                break;
        }
        return $metric;
    }

    private static function scopeStats(array $stats, array $settings): array {
        $clientId = absint($settings['client_id'] ?? 0);
        $projectId = absint($settings['project_id'] ?? 0);
        $routeId = absint($settings['route_id'] ?? 0);
        $rows = array_values(array_filter((array)($stats['by_day'] ?? []), static function($row) use ($clientId, $projectId, $routeId): bool {
            if (!is_array($row)) return false;
            if ($clientId && absint($row['client_id'] ?? 0) !== $clientId) return false;
            if ($projectId && absint($row['project_id'] ?? 0) !== $projectId) return false;
            if ($routeId && absint($row['route_id'] ?? 0) !== $routeId) return false;
            return true;
        }));
        $stats['by_day'] = $rows;
        return $stats;
    }

    private static function routeClosed(array $route): bool {
        $status = sanitize_key((string)($route['status'] ?? ''));
        return in_array($status, ['completed','done'], true) || (float)($route['operational_closure_rate'] ?? 0) >= 99.95;
    }

    private static function routeRows(array $routes): array {
        $rows = [];
        foreach ($routes as $route) {
            $rows[] = [
                'date'=>(string)($route['date'] ?? ''),
                'route'=>(int)($route['route_id'] ?? 0),
                'status'=>(string)($route['status'] ?? ''),
                'planned_stops'=>(int)($route['planned_stops'] ?? 0),
                'completed_stops'=>(int)($route['completed_stops'] ?? 0),
                'justified_stops'=>(int)($route['justified_stops'] ?? 0),
                'remaining_stops'=>(int)($route['remaining_stops'] ?? 0),
                'execution_rate'=>(float)($route['execution_rate'] ?? 0),
                'closure_rate'=>(float)($route['operational_closure_rate'] ?? 0),
                'access_distance_km'=>self::nullableFloat($route['access_distance_km'] ?? null),
                'inter_stop_distance_km'=>self::nullableFloat($route['inter_stop_distance_km'] ?? null),
                'return_distance_km'=>self::nullableFloat($route['return_distance_km'] ?? null),
                'distance_km'=>self::nullableFloat($route['distance_km'] ?? null),
                'energy_cost_eur'=>self::nullableFloat($route['energy_cost_eur'] ?? null),
                'toll_cost_eur'=>self::nullableFloat($route['toll_cost_eur'] ?? null),
                'fuel_tolls_cost_eur'=>(self::nullableFloat($route['energy_cost_eur'] ?? null) !== null && self::nullableFloat($route['toll_cost_eur'] ?? null) !== null)
                    ? round((float)$route['energy_cost_eur'] + (float)$route['toll_cost_eur'], 2) : null,
                'partial'=>!empty($route['mobility_reporting_partial']) ? 1 : 0,
                'recovered'=>!empty($route['mobility_reporting_recovered']) ? 1 : 0,
                'profile'=>(string)($route['mobility_profile_label'] ?? ''),
            ];
        }
        return $rows;
    }

    private static function dailyRows(array $days): array {
        $rows = [];
        foreach ($days as $day) {
            $rows[] = [
                'date'=>(string)($day['date'] ?? ''),
                'route'=>(int)($day['closed_routes'] ?? 0),
                'status'=>!empty($day['partial']) ? 'Parcial' : 'Completo',
                'access_distance_km'=>(float)($day['access_distance_km'] ?? 0),
                'inter_stop_distance_km'=>(float)($day['inter_stop_distance_km'] ?? 0),
                'return_distance_km'=>(float)($day['return_distance_km'] ?? 0),
                'distance_km'=>(float)($day['distance_km'] ?? 0),
                'energy_cost_eur'=>(int)($day['costed_routes'] ?? 0) > 0 ? (float)($day['energy_cost_eur'] ?? 0) : null,
                'toll_cost_eur'=>(int)($day['costed_routes'] ?? 0) > 0 ? (float)($day['toll_cost_eur'] ?? 0) : null,
                'fuel_tolls_cost_eur'=>(int)($day['costed_routes'] ?? 0) > 0 ? (float)($day['fuel_tolls_cost_eur'] ?? 0) : null,
                'closed_routes'=>(int)($day['closed_routes'] ?? 0),
                'incomplete_routes'=>(int)($day['incomplete_routes'] ?? 0),
                'recovered_routes'=>(int)($day['recovered_routes'] ?? 0),
                'partial'=>!empty($day['partial']) ? 1 : 0,
            ];
        }
        return $rows;
    }

    private static function qualityNote(bool $partial, bool $recovered, int $incomplete): string {
        $bits = [];
        if ($partial) $bits[] = 'parcial';
        if ($recovered) $bits[] = 'estimativa recuperada';
        if ($incomplete > 0) $bits[] = $incomplete . ' rota(s) incompleta(s)';
        return $bits ? ' · ' . implode(' · ', $bits) : '';
    }

    private static function nullableFloat($value): ?float {
        return is_numeric($value) ? round((float)$value, 2) : null;
    }

    private static function km(float $value): string { return number_format($value, 1, ',', '.') . ' km'; }
    private static function eur(float $value): string { return number_format($value, 2, ',', '.') . ' €'; }
    private static function pct(float $value): string { return number_format($value, 1, ',', '.') . '%'; }
    private static function dateLabel(string $date): string {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return $m[3] . '/' . $m[2];
        return $date;
    }
    private static function aggregationLabel(string $value): string {
        return ['latest'=>'Último valor','sum'=>'Soma','avg'=>'Média','count'=>'Contagem','evidence'=>'Todas as evidências'][$value] ?? $value;
    }
    private static function dimensionLabel(string $value): string {
        return ['submitted_at'=>'Data da visita','location_id'=>'Local','route_id'=>'Rota','project_id'=>'Campanha','client_id'=>'Cliente'][$value] ?? $value;
    }
}
