<?php
namespace RoutesPro\Performance;

use RoutesPro\Observability\ApiUsageLedger;

if (!defined('ABSPATH')) exit;

/**
 * Phase 4B: cost and latency investigation based only on already recorded,
 * aggregated telemetry. No provider requests, writes, policy or route changes.
 * Unknown reference tariffs never masquerade as zero-billed consumption.
 */
final class Phase4Drivers {
    public const CONTRACT = 'fieldflow-phase4-cost-performance-drivers-9dg-v1';

    public static function collect(array $gate, array $baseline): array {
        $ready = !empty($baseline['api_ready']);
        // 9DF/9BN has already read byOperation(30,100) while building the
        // baseline. Reuse it instead of repeating the expensive GROUP BY.
        $rows = $ready && is_array($baseline['api_operation_rows'] ?? null)
            ? $baseline['api_operation_rows']
            : ($ready ? ApiUsageLedger::byOperation(30, 80) : []);
        foreach ($rows as &$row) {
            if (!is_array($row)) continue;
            $row['reference_rate_eur_per_1000'] = ApiUsageLedger::rate(
                (string)($row['provider'] ?? ''), (string)($row['operation'] ?? '')
            );
        }
        unset($row);
        $daily = $ready ? ApiUsageLedger::daily(14) : [];
        return self::analyze($gate, $baseline, $rows, $daily);
    }

    /**
     * Pure transformer to keep thresholds, ordering and missing-data semantics
     * independently testable without a WordPress database or provider calls.
     */
    public static function analyze(array $gate, array $baseline, array $operations, array $daily, ?string $todayUtc = null): array {
        $ready = !empty($baseline['api_ready']);
        $ranking = [];
        $unpriced = 0;
        $unpricedRequests = 0;
        $priced = 0;
        $observedRequests = 0;
        $knownCost = 0.0;
        $labels = [
            'mobile'=>'API mobile', 'web'=>'Portal web', 'rest'=>'REST web',
            'submission'=>'Submissões', 'export'=>'Exportações',
            'admin'=>'Backoffice', 'cron'=>'Cron e filas',
        ];
        if ($ready) {
            foreach (array_slice($operations, 0, 80) as $row) {
                if (!is_array($row)) continue;
                $provider = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)($row['provider'] ?? ''))), 0, 48);
                $operation = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)($row['operation'] ?? ''))), 0, 64);
                if ($provider === '' || $operation === '') continue;
                $requests = max(0, (int)($row['requests'] ?? 0));
                $units = max(0.0, (float)($row['units'] ?? 0));
                $rate = max(0.0, (float)($row['reference_rate_eur_per_1000'] ?? 0));
                $cost = max(0.0, (float)($row['estimated_cost_eur'] ?? 0));
                $hits = max(0, (int)($row['cache_hits'] ?? 0));
                $errors = max(0, (int)($row['errors'] ?? 0));
                $blocked = max(0, (int)($row['blocked'] ?? 0));
                $observedRequests += $requests;
                $knownCost += $cost;
                $externalProvider = str_starts_with($provider, 'google_') || str_starts_with($provider, 'azure_');
                // A zero-rate SKU may be billable. Do not claim a zero euro bill.
                $missingRate = $externalProvider && $requests > 0 && $units > 0 && $rate <= 0.0;
                if ($missingRate) { $unpriced++; $unpricedRequests += $requests; }
                elseif ($externalProvider && $requests > 0 && $rate > 0) $priced++;
                $premium = str_starts_with($provider, 'google_') &&
                    (str_contains($operation, 'enterprise') || str_contains($operation, '_pro'));
                $errorRisk = $errors >= 3 && $errors * 10 >= max(1, $requests + $errors);
                $cacheCandidate = str_starts_with($provider, 'google_') && $requests >= 20 && $hits === 0
                    && (str_contains($operation, 'geocode') || str_contains($operation, 'compute_routes_essentials') || $operation === 'places');
                $reason = '';
                $priority = 'inspect';
                if ($missingRate) { $priority = 'unknown_cost'; $reason = 'Configurar tarifa de referência e confrontar com a faturação.'; }
                elseif ($premium) { $priority = 'premium'; $reason = 'Confirmar se o tier premium é necessário e autorizado.'; }
                elseif ($errorRisk) { $priority = 'errors'; $reason = 'Investigar falhas e reenvios; o custo de erros pode variar.'; }
                elseif ($cacheCandidate) { $priority = 'cache'; $reason = 'Rever elegibilidade e isolamento da cache por cliente e operação.'; }
                elseif ($cost > 0) { $priority = 'cost'; $reason = 'Rever frequência e necessidade desta operação.'; }
                elseif ($blocked > 0) { $priority = 'blocked'; $reason = 'Verificar bloqueios previstos pela política; não desativar automaticamente.'; }
                else { $reason = 'Acompanhar até existir volume suficiente para decisão.'; }
                $rank = ['unknown_cost'=>600,'premium'=>500,'errors'=>400,'cache'=>300,'cost'=>200,'blocked'=>100,'inspect'=>0][$priority];
                $ranking[] = [
                    'provider'=>$provider, 'operation'=>$operation, 'requests'=>$requests,
                    'units'=>$units, 'cache_hits'=>$hits, 'errors'=>$errors, 'blocked'=>$blocked,
                    'estimated_eur'=>$cost, 'avoided_eur'=>max(0.0, (float)($row['avoided_cost_eur'] ?? 0)),
                    'reference_rate_eur_per_1000'=>$rate, 'cost_unknown'=>$missingRate,
                    'priority'=>$priority, 'reason'=>$reason, 'sort_score'=>$rank + min(99, (int)round($cost * 10)),
                ];
            }
            usort($ranking, static function(array $a, array $b): int {
                return ($b['sort_score'] <=> $a['sort_score'])
                    ?: ($b['estimated_eur'] <=> $a['estimated_eur'])
                    ?: strcmp($a['provider'].'/'.$a['operation'], $b['provider'].'/'.$b['operation']);
            });
        }
        $slow = [];
        foreach ($labels as $key=>$label) {
            $row = is_array($gate['categories'] ?? null) ? ($gate['categories'][$key] ?? null) : null;
            if (!is_array($row)) continue;
            $samples = max(0, (int)($row['samples'] ?? 0));
            $status = (string)($row['status'] ?? 'collecting');
            if ($samples < 10 || !in_array($status, ['warning','fail','blocked'], true)) continue;
            $slow[] = [
                'category'=>$key, 'label'=>$label, 'samples'=>$samples,
                'status'=>$status, 'p95_ms'=>max(0, (int)($row['p95_duration_ms'] ?? 0)),
                'error_rate_pct'=>max(0.0, (float)($row['error_rate_pct'] ?? 0)),
            ];
        }
        usort($slow, static fn(array $a, array $b): int =>
            (($b['status']==='fail'||$b['status']==='blocked') <=> ($a['status']==='fail'||$a['status']==='blocked'))
            ?: ($b['p95_ms'] <=> $a['p95_ms']));

        $todayUtc = (string)($todayUtc ?: gmdate('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $todayUtc)) $todayUtc = gmdate('Y-m-d');
        $anchor = strtotime($todayUtc . ' 00:00:00 UTC');
        if ($anchor === false) $anchor = time();
        $cutCurrent = gmdate('Y-m-d', $anchor - 6 * 86400);
        $cutPrevious = gmdate('Y-m-d', $anchor - 13 * 86400);
        $current = ['estimated_eur'=>0.0,'requests'=>0,'days_with_data'=>0];
        $previous = ['estimated_eur'=>0.0,'requests'=>0,'days_with_data'=>0];
        if ($ready) {
            foreach ($daily as $row) {
                if (!is_array($row)) continue;
                $day = (string)($row['usage_day'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || $day > $todayUtc || $day < $cutPrevious) continue;
                $target = ($day >= $cutCurrent) ? 'current' : 'previous';
                if ($target === 'current') {
                    $current['estimated_eur'] += max(0.0, (float)($row['estimated_cost_eur'] ?? 0));
                    $current['requests'] += max(0, (int)($row['requests'] ?? 0));
                    $current['days_with_data']++;
                } else {
                    $previous['estimated_eur'] += max(0.0, (float)($row['estimated_cost_eur'] ?? 0));
                    $previous['requests'] += max(0, (int)($row['requests'] ?? 0));
                    $previous['days_with_data']++;
                }
            }
        }
        // Partial current day and missing event-days prevent invoice-like claims.
        $trendReady = $ready && $current['days_with_data'] >= 4 && $previous['days_with_data'] >= 4;
        return [
            'contract'=>self::CONTRACT, 'read_only'=>true, 'window_days'=>30,
            'api_ready'=>$ready, 'operation_rows'=>count($ranking),
            'unpriced_operations'=>$unpriced, 'unpriced_requests'=>$unpricedRequests,
            'priced_operations'=>$priced, 'observed_requests'=>$observedRequests,
            'estimated_known_eur'=>$ready ? $knownCost : null,
            'recommendations'=>array_slice($ranking,0,12), 'slow_flows'=>array_slice($slow,0,6),
            'last_7_days'=>$ready ? $current : null, 'previous_7_days'=>$ready ? $previous : null,
            'trend_comparable'=>$trendReady,
            'trend_change_pct'=>$trendReady && $previous['estimated_eur'] > 0.0
                ? round(100.0 * ($current['estimated_eur']-$previous['estimated_eur'])/$previous['estimated_eur'],1)
                : null,
            'trend_note'=>'Comparação indicativa: o dia atual é parcial e só inclui eventos registados; não corresponde à fatura.',
        ];
    }
}
