<?php
namespace RoutesPro\Services;

use RoutesPro\Observability\ApiUsageLedger;
use RoutesPro\Support\Config;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow 2.2.585 Google Cost Control Center.
 *
 * Privacy-safe financial guardrail around Google Maps Platform usage. The
 * service classifies operations before outbound HTTP, blocks premium tiers by
 * policy, and exposes an aggregated health snapshot without storing routes,
 * addresses, coordinates, API responses or keys.
 */
final class GoogleCostControlCenter {
    public const CONTRACT = 'fieldflow-google-cost-control-center-v1';

    public static function enabled(): bool {
        return !empty(Config::get('google_cost_control_enabled', 1));
    }

    public static function policy(): array {
        return [
            'enabled' => self::enabled(),
            'block_matrix' => !empty(Config::get('google_cost_block_matrix', 1)),
            'block_pro' => !empty(Config::get('google_cost_block_pro', 1)),
            'block_enterprise' => !empty(Config::get('google_cost_block_enterprise', 1)),
            'alert_premium' => !empty(Config::get('google_cost_alert_premium', 1)),
        ];
    }

    public static function tier(string $operation): string {
        $operation = sanitize_key($operation);
        if (strpos($operation, 'enterprise') !== false) return 'enterprise';
        if (strpos($operation, 'pro') !== false) return 'pro';
        if (strpos($operation, 'essentials') !== false) return 'essentials';
        if (strpos($operation, 'matrix') !== false) return 'matrix';
        if (in_array($operation, ['geocode','places','autocomplete'], true)) return 'places';
        return 'standard';
    }

    public static function authorizeOperation(string $operation, array $context = []): array {
        $operation = sanitize_key($operation);
        $policy = self::policy();
        if (!$policy['enabled']) return ['allowed'=>true,'operation'=>$operation,'tier'=>self::tier($operation),'reason'=>'cost_control_disabled'];

        $isMatrix = strpos($operation, 'compute_route_matrix_') === 0 || $operation === 'matrix_element';
        $tier = self::tier($operation);
        $reason = '';
        if ($isMatrix && $policy['block_matrix']) $reason = 'matrix_blocked';
        elseif ($tier === 'enterprise' && $policy['block_enterprise']) $reason = 'enterprise_blocked';
        elseif ($tier === 'pro' && $policy['block_pro']) $reason = 'pro_blocked';

        if ($reason !== '') {
            self::recordBlocked($operation, $reason, $context);
            return ['allowed'=>false,'operation'=>$operation,'tier'=>$tier,'reason'=>$reason];
        }
        return ['allowed'=>true,'operation'=>$operation,'tier'=>$tier,'reason'=>'allowed'];
    }

    public static function recordBlocked(string $operation, string $reason, array $context = []): void {
        if (!class_exists(ApiUsageLedger::class)) return;
        try {
            ApiUsageLedger::record('google_routes', $operation, [
                'status' => 'blocked',
                'cache_status' => 'disabled',
                'request_count' => 0,
                'units' => max(1.0, (float)($context['units'] ?? 1)),
                'billable' => false,
                'duration_ms' => 0,
                'source' => 'google_cost_control_center',
                'meta' => [
                    'policy_reason' => sanitize_key($reason),
                    'authority_contract' => self::CONTRACT,
                ],
            ]);
            if (!empty(Config::get('google_cost_alert_premium', 1)) && class_exists('\\RoutesPro\\Observability\\Monitor')) {
                $alertKey = 'ff_google_cost_block_' . md5($operation . '|' . $reason);
                $alreadyAlerted = function_exists('get_transient') ? (bool)get_transient($alertKey) : false;
                if (!$alreadyAlerted) {
                    if (function_exists('set_transient')) set_transient($alertKey, 1, defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400);
                    \RoutesPro\Observability\Monitor::record('google_cost_control', 'warning', 'Google Cost Control Center bloqueou uma operação premium antes do pedido HTTP.', [
                        'operation' => $operation,
                        'reason' => sanitize_key($reason),
                    ]);
                }
            }
        } catch (\Throwable $ignored) {}
    }

    public static function configurationDrift(): array {
        if (!self::enabled()) return [];
        $policy = self::policy();
        $drift = [];
        $preference = (string)Config::get('google_routes_preference', 'TRAFFIC_UNAWARE');
        if ($policy['block_pro'] && in_array($preference, ['TRAFFIC_AWARE','TRAFFIC_AWARE_OPTIMAL'], true)) {
            $drift[] = 'Preferência de trânsito Google ativa, o Cost Control bloqueia pedidos Pro.';
        }
        if ($policy['block_pro'] && !empty(Config::get('google_routes_waypoint_optimization_enabled', 0))) {
            $drift[] = 'Otimização Google de waypoints ativa, o Cost Control bloqueia pedidos Pro.';
        }
        if ($policy['block_enterprise'] && !empty(Config::get('google_routes_tolls_enabled', 0))) {
            $drift[] = 'Google TOLLS ativo, o Cost Control bloqueia pedidos Enterprise.';
        }
        if ($policy['block_matrix'] && !empty(Config::get('google_route_matrix_enabled', 0))) {
            $drift[] = 'Google Route Matrix ativa, o Cost Control bloqueia a chamada faturável.';
        }
        if (!empty(Config::get('google_places_contact_enrichment_enabled', 0))) {
            $drift[] = 'Enriquecimento de contactos Google Places está ativo e pode aumentar custos de Places.';
        }
        return $drift;
    }

    public static function status(int $days = 30): array {
        $summary = class_exists(ApiUsageLedger::class) ? ApiUsageLedger::summary($days) : [];
        $operations = class_exists(ApiUsageLedger::class) ? ApiUsageLedger::byOperation($days, 100) : [];
        $result = [
            'health' => 'healthy',
            'health_label' => 'POLÍTICA SAUDÁVEL',
            'essentials_requests' => 0,
            'pro_requests' => 0,
            'enterprise_requests' => 0,
            'matrix_units' => 0.0,
            'blocked_events' => 0,
            'google_places_requests' => 0,
            'google_geocode_requests' => 0,
            'premium_requests' => 0,
            'avoided_events' => (int)($summary['avoided_events'] ?? 0),
            'drift' => self::configurationDrift(),
            'operations' => $operations,
        ];
        foreach ($operations as $row) {
            $provider = sanitize_key((string)($row['provider'] ?? ''));
            $operation = sanitize_key((string)($row['operation'] ?? ''));
            $requests = max(0, (int)($row['requests'] ?? 0));
            $blocked = max(0, (int)($row['blocked'] ?? 0));
            $units = max(0.0, (float)($row['units'] ?? 0));
            $executedUnits = max(0.0, (float)($row['executed_units'] ?? $units));
            $result['blocked_events'] += $blocked;
            if ($provider === 'google_routes') {
                if ($operation === 'compute_routes_essentials') $result['essentials_requests'] += $requests;
                elseif ($operation === 'compute_routes_pro') $result['pro_requests'] += $requests;
                elseif ($operation === 'compute_routes_enterprise') $result['enterprise_requests'] += $requests;
                if (strpos($operation, 'compute_route_matrix_') === 0) $result['matrix_units'] += $executedUnits;
            } elseif ($provider === 'google_maps') {
                if ($operation === 'places') $result['google_places_requests'] += $requests;
                if ($operation === 'geocode') $result['google_geocode_requests'] += $requests;
            }
        }
        $result['premium_requests'] = $result['pro_requests'] + $result['enterprise_requests'];
        $budgetPct = (float)($summary['budget_usage_pct'] ?? 0);
        if ($result['premium_requests'] > 0 || $result['matrix_units'] > 0 || $budgetPct >= 100) {
            $result['health'] = 'critical';
            $result['health_label'] = 'REVER CUSTOS';
        } elseif ($result['drift'] || $budgetPct >= 80) {
            $result['health'] = 'warning';
            $result['health_label'] = 'ATENÇÃO';
        }
        return array_merge($result, ['summary'=>$summary, 'policy'=>self::policy()]);
    }
}
