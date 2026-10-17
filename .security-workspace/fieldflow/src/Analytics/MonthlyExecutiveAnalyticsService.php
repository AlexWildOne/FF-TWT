<?php
namespace RoutesPro\Analytics;

if (!defined('ABSPATH')) exit;

/**
 * Deterministic monthly executive layer built only from the already scoped
 * /stats + canonical operational report envelope. No external AI or routing
 * request is performed here.
 */
final class MonthlyExecutiveAnalyticsService {
    public const VERSION = '1.0.0';
    public const CONTRACT = 'fieldflow-monthly-executive-analytics-v1';

    public static function build(array $stats, array $context = []): array {
        $from = self::cleanDate((string)($stats['from'] ?? ($context['from'] ?? '')));
        $to = self::cleanDate((string)($stats['to'] ?? ($context['to'] ?? '')));
        $canonical = is_array($stats['canonical_operational_report'] ?? null) ? $stats['canonical_operational_report'] : [];
        $kpis = is_array($canonical['kpis'] ?? null) ? $canonical['kpis'] : [];
        $previous = is_array($stats['previous_summary'] ?? null) ? $stats['previous_summary'] : [];
        $locations = array_values(array_filter((array)($stats['by_location'] ?? []), 'is_array'));
        $team = is_array($stats['team_performance'] ?? null) ? $stats['team_performance'] : [];

        $routesTotal = (int)($kpis['routes_total'] ?? $stats['total_routes'] ?? 0);
        $routesCompleted = (int)($kpis['routes_completed'] ?? $stats['completed_routes'] ?? 0);
        $routeCompletion = $routesTotal > 0 ? round(($routesCompleted / $routesTotal) * 100, 1) : (float)($stats['completion_rate'] ?? 0);
        $visitsPlanned = (int)($kpis['visits_planned'] ?? $stats['total_stops'] ?? 0);
        $visitsCompleted = (int)($kpis['visits_completed'] ?? $stats['done_stops'] ?? 0);
        $visitsJustified = (int)($kpis['visits_justified'] ?? 0);
        $visitsRemaining = (int)($kpis['visits_remaining'] ?? max(0, $visitsPlanned - $visitsCompleted - $visitsJustified));
        $executionRate = $visitsPlanned > 0 ? round(($visitsCompleted / $visitsPlanned) * 100, 1) : (float)($kpis['execution_rate'] ?? $stats['done_rate'] ?? 0);
        $closureRate = $visitsPlanned > 0 ? round((($visitsCompleted + $visitsJustified) / $visitsPlanned) * 100, 1) : (float)($kpis['operational_closure_rate'] ?? 0);

        $storesPlanned = count($locations);
        $storesVisited = 0;
        $riskLocations = [];
        foreach ($locations as $row) {
            $done = (int)($row['stops_done'] ?? 0);
            $planned = (int)($row['stops'] ?? 0);
            $rate = is_numeric($row['done_rate'] ?? null) ? (float)$row['done_rate'] : ($planned > 0 ? round(($done / $planned) * 100, 1) : 0.0);
            if ($done > 0) $storesVisited++;
            $pending = max(0, $planned - $done);
            if ($pending > 0 || $rate < 100.0) {
                $riskLocations[] = [
                    'location_id' => self::absint($row['location_id'] ?? 0),
                    'location_name' => self::text((string)($row['location_name'] ?? 'Loja')),
                    'city' => self::text((string)($row['city'] ?? '')),
                    'planned_visits' => $planned,
                    'completed_visits' => $done,
                    'pending_visits' => $pending,
                    'execution_rate' => round($rate, 1),
                    'severity' => $rate < 60 ? 'critical' : ($rate < 90 ? 'watch' : 'attention'),
                ];
            }
        }
        usort($riskLocations, static function(array $a, array $b): int {
            $rate = ($a['execution_rate'] <=> $b['execution_rate']);
            return $rate !== 0 ? $rate : ($b['pending_visits'] <=> $a['pending_visits']);
        });
        $riskLocations = array_slice($riskLocations, 0, 8);
        $coverageRate = $storesPlanned > 0 ? round(($storesVisited / $storesPlanned) * 100, 1) : 0.0;

        $mobilityDistance = round((float)($kpis['mobility_distance_km'] ?? 0), 2);
        $mobilityCost = round((float)($kpis['mobility_total_cost_eur'] ?? 0), 2);
        $mobilityTolls = round((float)($kpis['mobility_toll_cost_eur'] ?? 0), 2);
        $costPerVisit = $visitsCompleted > 0 && $mobilityCost > 0 ? round($mobilityCost / $visitsCompleted, 2) : null;
        $orders = (int)($kpis['order_suggestions'] ?? 0);
        $orderBoxes = round((float)($kpis['order_boxes'] ?? 0), 2);

        $prevExecution = is_numeric($previous['done_rate'] ?? null) ? (float)$previous['done_rate'] : null;
        $prevRoutes = is_numeric($previous['total_routes'] ?? null) ? (int)$previous['total_routes'] : null;
        $prevVisits = is_numeric($previous['done_stops'] ?? null) ? (int)$previous['done_stops'] : null;
        $trends = [
            'execution' => self::trend($executionRate, $prevExecution, 'pp'),
            'routes' => self::trend($routesCompleted, $prevRoutes, 'count'),
            'visits' => self::trend($visitsCompleted, $prevVisits, 'count'),
        ];

        $health = self::health($executionRate, $coverageRate, $visitsRemaining, $visitsPlanned);
        $label = self::periodLabel($from, $to);
        $narrative = self::narrative($label, $visitsCompleted, $visitsPlanned, $executionRate, $coverageRate, $riskLocations, $orders, $mobilityDistance, $mobilityCost);

        $leader = is_array($team['leader'] ?? null) ? $team['leader'] : (is_array($team['focus'] ?? null) ? $team['focus'] : []);
        $commercialSignals = self::commercialSignals((array)($stats['form_analytics']['metrics'] ?? []));

        return [
            'version' => self::VERSION,
            'contract' => self::CONTRACT,
            'period' => [
                'from' => $from,
                'to' => $to,
                'label' => $label,
                'is_full_month' => self::isFullMonth($from, $to) ? 1 : 0,
            ],
            'health' => $health,
            'narrative' => $narrative,
            'primary_kpis' => [
                'execution' => ['label'=>'Execução','value'=>$executionRate,'unit'=>'%','note'=>$visitsCompleted . ' de ' . $visitsPlanned . ' visitas executadas'],
                'coverage' => ['label'=>'Cobertura PDV','value'=>$coverageRate,'unit'=>'%','note'=>$storesVisited . ' de ' . $storesPlanned . ' lojas com visita executada'],
                'route_completion' => ['label'=>'Rotas fechadas','value'=>$routeCompletion,'unit'=>'%','note'=>$routesCompleted . ' de ' . $routesTotal . ' rotas'],
            ],
            'secondary_kpis' => [
                'visits_planned'=>$visitsPlanned,
                'visits_completed'=>$visitsCompleted,
                'visits_justified'=>$visitsJustified,
                'visits_remaining'=>$visitsRemaining,
                'operational_closure_rate'=>$closureRate,
                'stores_planned'=>$storesPlanned,
                'stores_visited'=>$storesVisited,
                'mobility_distance_km'=>$mobilityDistance,
                'mobility_total_cost_eur'=>$mobilityCost,
                'mobility_toll_cost_eur'=>$mobilityTolls,
                'mobility_cost_per_visit_eur'=>$costPerVisit,
                'order_suggestions'=>$orders,
                'order_boxes'=>$orderBoxes,
                'fronts_gain'=>round((float)($kpis['fronts_gain'] ?? 0), 2),
                'fronts_growth_percent'=>is_numeric($kpis['fronts_growth_percent'] ?? null) ? round((float)$kpis['fronts_growth_percent'], 1) : null,
                'evidence_files'=>(int)($kpis['evidence_files'] ?? 0),
            ],
            'trends' => $trends,
            'risk_locations' => $riskLocations,
            'top_performer' => $leader ? [
                'name'=>self::text((string)($leader['name'] ?? '')),
                'performance_index'=>is_numeric($leader['performance_index'] ?? null) ? round((float)$leader['performance_index'], 1) : null,
                'execution_rate'=>is_numeric($leader['execution_rate'] ?? null) ? round((float)$leader['execution_rate'], 1) : null,
            ] : [],
            'commercial_signals' => $commercialSignals,
            'data_quality' => [
                'mobility_cost_available' => $mobilityCost > 0 ? 1 : 0,
                'locations_available' => $storesPlanned > 0 ? 1 : 0,
                'canonical_report_available' => !empty($canonical) ? 1 : 0,
                'previous_period_available' => !empty($previous) ? 1 : 0,
                'note' => 'Resumo calculado apenas com dados FieldFlow do âmbito e período selecionados.',
            ],
        ];
    }

    private static function commercialSignals(array $metrics): array {
        $out = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric)) continue;
            $value = $metric['value'] ?? null;
            if (!is_numeric($value) && !(is_string($value) && trim($value) !== '')) continue;
            $title = self::text((string)($metric['title'] ?? $metric['label'] ?? $metric['question_label'] ?? ''));
            if ($title === '') continue;
            $out[] = [
                'label' => $title,
                'value' => is_numeric($value) ? round((float)$value, 1) : self::text((string)$value),
                'unit' => self::text((string)($metric['unit'] ?? '')),
                'note' => self::text((string)($metric['note'] ?? '')),
            ];
            if (count($out) >= 4) break;
        }
        return $out;
    }

    private static function narrative(string $label, int $done, int $planned, float $execution, float $coverage, array $risks, int $orders, float $distance, float $cost): string {
        $parts = [];
        $parts[] = $label . ': ' . $done . ' de ' . $planned . ' visitas executadas (' . self::number($execution, 1) . '%).';
        $parts[] = 'Cobertura de ' . self::number($coverage, 1) . '% dos PDVs com atividade no período.';
        if ($risks) $parts[] = count($risks) . ' PDV' . (count($risks) === 1 ? '' : 's') . ' no topo da fila de atenção operacional.';
        if ($orders > 0) $parts[] = $orders . ' sugest' . ($orders === 1 ? 'ão' : 'ões') . ' de encomenda registada' . ($orders === 1 ? '' : 's') . '.';
        if ($distance > 0) {
            $mobility = self::number($distance, 0) . ' km de mobilidade';
            if ($cost > 0) $mobility .= ', com custo estimado de ' . self::eur($cost);
            $parts[] = $mobility . '.';
        }
        return implode(' ', $parts);
    }

    private static function health(float $execution, float $coverage, int $remaining, int $planned): array {
        $pendingRate = $planned > 0 ? ($remaining / $planned) * 100 : 0.0;
        if ($execution >= 95 && $coverage >= 90 && $pendingRate <= 5) return ['status'=>'good','label'=>'Operação saudável'];
        if ($execution >= 85 && $coverage >= 75 && $pendingRate <= 15) return ['status'=>'watch','label'=>'Acompanhar'];
        return ['status'=>'risk','label'=>'Requer atenção'];
    }

    private static function trend($current, $previous, string $unit): array {
        if (!is_numeric($current) || !is_numeric($previous)) return ['available'=>0,'delta'=>null,'direction'=>'flat','unit'=>$unit];
        $delta = round((float)$current - (float)$previous, 1);
        return ['available'=>1,'delta'=>$delta,'direction'=>$delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),'unit'=>$unit];
    }

    private static function isFullMonth(string $from, string $to): bool {
        if (!$from || !$to || substr($from, 0, 7) !== substr($to, 0, 7)) return false;
        $first = substr($from, 0, 7) . '-01';
        $last = date('Y-m-t', strtotime($first));
        return $from === $first && $to === $last;
    }

    private static function periodLabel(string $from, string $to): string {
        if (!$from || !$to) return 'Período selecionado';
        if (substr($from, 0, 7) === substr($to, 0, 7)) {
            $ts = strtotime($from . ' 12:00:00');
            if (function_exists('wp_date') && function_exists('wp_timezone')) return ucfirst((string)wp_date('F Y', $ts, wp_timezone()));
            return date('m/Y', $ts);
        }
        return $from . ' a ' . $to;
    }

    private static function cleanDate(string $value): string {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
    private static function number(float $value, int $decimals = 1): string { return number_format($value, $decimals, ',', '.'); }
    private static function eur(float $value): string { return number_format($value, 2, ',', '.') . ' €'; }
    private static function absint($value): int { return function_exists('absint') ? absint($value) : abs((int)$value); }
    private static function text(string $value): string { return function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim(strip_tags($value)); }
}
