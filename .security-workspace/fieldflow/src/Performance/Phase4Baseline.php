<?php
namespace RoutesPro\Performance;

use RoutesPro\Observability\ApiUsageLedger;
use RoutesPro\Observability\Monitor;
use RoutesPro\Services\GoogleCostControlCenter;

if (!defined('ABSPATH')) exit;

/**
 * Phase 4A: read-only commercial performance and API cost baseline.
 * Reuses existing private, aggregated measurements. Never sends HTTP or
 * stores new telemetry, changes provider policy, or alters release gates.
 */
final class Phase4Baseline {
    public const CONTRACT = 'fieldflow-phase4-observability-cost-baseline-9df-v1';

    public static function collect(array $gate): array {
        return self::summarize(
            $gate,
            ApiUsageLedger::summary(30),
            ApiUsageLedger::byProvider(30),
            GoogleCostControlCenter::status(30),
            Monitor::summary()
        );
    }

    /** Pure mapping for local tests and verifiable empty-data handling. */
    public static function summarize(array $gate, array $api, array $providers, array $google, array $events): array {
        $byProvider = ['google'=>0.0,'azure'=>0.0,'other'=>0.0];
        foreach ($providers as $row) {
            if (!is_array($row)) continue;
            $name = strtolower((string)($row['provider'] ?? ''));
            $amount = max(0.0, (float)($row['estimated_cost_eur'] ?? 0));
            $key = str_starts_with($name, 'google_') ? 'google' : (str_starts_with($name, 'azure_') ? 'azure' : 'other');
            $byProvider[$key] += $amount;
        }
        $category = [];
        foreach (['mobile','rest','submission','export','admin','web'] as $key) {
            $row = is_array(($gate['categories'] ?? null)) ? ($gate['categories'][$key] ?? null) : null;
            if (!is_array($row)) $row=[];
            $category[$key] = [
                'samples'=>max(0,(int)($row['samples'] ?? 0)),
                'p95_ms'=>max(0,(int)($row['p95_duration_ms'] ?? 0)),
                'status'=>(string)($row['status'] ?? 'collecting'),
            ];
        }
        $apiReady = !empty($api['table_ready']);
        $drift = is_array($google['drift'] ?? null) ? $google['drift'] : [];
        $googleHealth = $apiReady ? (string)($google['health'] ?? 'unknown') : 'unknown';
        $budget = max(0.0,(float)($api['monthly_budget_eur'] ?? 0));
        $monthCost = max(0.0,(float)($api['month_cost_eur'] ?? 0));
        return [
            'contract'=>self::CONTRACT,
            'read_only'=>true,
            'cost_window_days'=>30,
            'sample_window_hours'=>max(0,(int)($gate['window_hours'] ?? 0)),
            'sampling_status'=>(string)($gate['status'] ?? 'collecting'),
            'samples'=>max(0,(int)($gate['samples'] ?? 0)),
            'categories'=>$category,
            'api_ready'=>$apiReady,
            'api_requests'=>max(0,(int)($api['requests'] ?? 0)),
            'total_estimated_eur'=>$apiReady ? max(0.0,(float)($api['estimated_cost_eur'] ?? 0)) : null,
            'by_provider_eur'=>$byProvider,
            'monthly_budget_eur'=>$budget,
            'month_estimated_eur'=>$monthCost,
            'budget_warning'=>$apiReady && $budget>0 && $monthCost >= $budget * 0.85,
            // 9DG reuses the 9BN byOperation snapshot, avoiding a duplicate GROUP BY.
            'api_operation_rows'=>array_slice(is_array($google['operations'] ?? null) ? $google['operations'] : [],0,80),
            'google_health'=>$googleHealth,
            'google_drift_count'=>count($drift),
            'google_premium_requests'=>max(0,(int)($google['premium_requests'] ?? 0)),
            'google_blocked_events'=>max(0,(int)($google['blocked_events'] ?? 0)),
            'critical_active'=>max(0,(int)($events['critical_active'] ?? 0)),
            'slowest'=>array_slice(is_array($events['slowest'] ?? null) ? $events['slowest'] : [],0,3),
        ];
    }
}
