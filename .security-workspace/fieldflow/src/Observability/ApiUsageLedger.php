<?php
namespace RoutesPro\Observability;

use RoutesPro\Support\Config;

if (!defined('ABSPATH')) exit;

/**
 * Privacy-safe ledger for external API usage and avoided calls.
 *
 * It stores technical counters only. Request payloads, addresses, coordinates,
 * API keys and response bodies are intentionally excluded.
 */
final class ApiUsageLedger {
    public const CLEANUP_HOOK = 'fieldflow_api_usage_cleanup';
    private static bool $registered = false;
    private static ?bool $tableReady = null;

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_api_usage_logs';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('init', [self::class, 'schedule']);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
    }

    public static function schedule(): void {
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function tableReady(bool $refresh = false): bool {
        if (!$refresh && self::$tableReady !== null) return self::$tableReady;
        global $wpdb;
        $table = self::tableName();
        return self::$tableReady = ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
    }

    public static function enabled(): bool {
        return !empty(Config::get('api_cost_ledger_enabled', 1));
    }

    /**
     * @param string $provider google_routes, google_maps, azure_maps or another sanitized provider key.
     * @param string $operation route, matrix_element, geocode, directions, places, autocomplete or another operation key.
     * @param array  $data Technical metadata only.
     */
    public static function record(string $provider, string $operation, array $data = []): int {
        if (!self::enabled() || !self::tableReady()) return 0;

        global $wpdb;
        $provider = substr(sanitize_key($provider), 0, 48) ?: 'unknown';
        $operation = substr(sanitize_key($operation), 0, 64) ?: 'request';
        $status = sanitize_key((string)($data['status'] ?? 'success'));
        if (!in_array($status, ['success', 'error', 'blocked', 'timeout'], true)) $status = 'success';
        $cacheStatus = sanitize_key((string)($data['cache_status'] ?? 'miss'));
        if (!in_array($cacheStatus, ['hit', 'miss', 'bypass', 'disabled', 'unknown'], true)) $cacheStatus = 'unknown';

        $units = max(0.0, (float)($data['units'] ?? 1));
        $billable = array_key_exists('billable', $data)
            ? !empty($data['billable'])
            : ($cacheStatus !== 'hit' && $status === 'success');
        $requestCount = max(0, (int)($data['request_count'] ?? ($cacheStatus === 'hit' ? 0 : 1)));
        $estimated = $billable ? self::estimate($provider, $operation, $units) : 0.0;
        $avoided = ($cacheStatus === 'hit' || $status === 'blocked') ? self::estimate($provider, $operation, $units) : 0.0;
        $context = self::requestContext($data);
        $meta = self::sanitizeMeta((array)($data['meta'] ?? []));
        if (isset($data['source'])) $meta['source'] = substr(sanitize_key((string)$data['source']), 0, 64);

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(self::tableName(), [
            'provider' => $provider,
            'operation' => $operation,
            'status' => $status,
            'cache_status' => $cacheStatus,
            'request_count' => $requestCount,
            'units' => $units,
            'estimated_cost_eur' => round($estimated, 8),
            'avoided_cost_eur' => round($avoided, 8),
            'duration_ms' => max(0, (int)($data['duration_ms'] ?? 0)),
            'http_status' => max(0, min(599, (int)($data['http_status'] ?? 0))) ?: null,
            'client_id' => $context['client_id'] ?: null,
            'project_id' => $context['project_id'] ?: null,
            'route_id' => $context['route_id'] ?: null,
            'user_id' => $context['user_id'] ?: null,
            'meta_json' => $meta ? wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => $now,
        ], ['%s','%s','%s','%s','%d','%f','%f','%f','%d','%d','%d','%d','%d','%d','%s','%s']);

        if ($inserted === false) return 0;
        self::maybeBudgetAlert();
        return (int)$wpdb->insert_id;
    }

    public static function estimate(string $provider, string $operation, float $units): float {
        $rate = self::rate($provider, $operation);
        if ($rate <= 0 || $units <= 0) return 0.0;
        return ($units / 1000) * $rate;
    }

    public static function rate(string $provider, string $operation): float {
        $providerKey = sanitize_key($provider);
        $operationKey = sanitize_key($operation);
        $key = 'api_rate_' . $providerKey . '_' . $operationKey . '_per_1000_eur';
        $rate = max(0.0, (float)Config::get($key, 0));
        if ($rate > 0) return $rate;

        // 9BL keeps the old generic rate fields as a compatibility fallback,
        // while exposing the actual Google Maps Platform SKU tier in the ledger.
        if ($providerKey === 'google_routes') {
            if (strpos($operationKey, 'compute_routes_') === 0) {
                return max(0.0, (float)Config::get('api_rate_google_routes_route_per_1000_eur', 0));
            }
            if (strpos($operationKey, 'compute_route_matrix_') === 0) {
                return max(0.0, (float)Config::get('api_rate_google_routes_matrix_element_per_1000_eur', 0));
            }
        }
        return 0.0;
    }

    public static function summary(int $days = 30): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $base = [
            'table_ready' => self::tableReady(),
            'days' => $days,
            'requests' => 0,
            'units' => 0.0,
            'estimated_cost_eur' => 0.0,
            'avoided_cost_eur' => 0.0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'errors' => 0,
            'blocked' => 0,
            'avoided_events' => 0,
            'hit_rate_pct' => 0.0,
            'monthly_budget_eur' => max(0.0, (float)Config::get('api_monthly_budget_eur', 0)),
            'month_cost_eur' => 0.0,
            'budget_usage_pct' => 0.0,
        ];
        if (!$base['table_ready']) return $base;

        $table = self::tableName();
        $from = gmdate('Y-m-d H:i:s', time() - ($days - 1) * DAY_IN_SECONDS);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(request_count),0) requests, COALESCE(SUM(units),0) units, COALESCE(SUM(estimated_cost_eur),0) estimated_cost_eur, COALESCE(SUM(avoided_cost_eur),0) avoided_cost_eur, SUM(cache_status='hit') cache_hits, SUM(cache_status IN ('miss','bypass','disabled') AND status<>'blocked') cache_misses, SUM(status IN ('error','timeout')) errors, SUM(status='blocked') blocked, SUM(cache_status='hit' OR status='blocked') avoided_events FROM {$table} WHERE created_at >= %s",
            $from
        ), ARRAY_A) ?: [];
        foreach (['requests','cache_hits','cache_misses','errors','blocked','avoided_events'] as $key) $base[$key] = (int)($row[$key] ?? 0);
        foreach (['units','estimated_cost_eur','avoided_cost_eur'] as $key) $base[$key] = (float)($row[$key] ?? 0);
        $cacheTotal = $base['cache_hits'] + $base['cache_misses'];
        $base['hit_rate_pct'] = $cacheTotal > 0 ? round(($base['cache_hits'] / $cacheTotal) * 100, 1) : 0.0;

        $monthStart = gmdate('Y-m-01 00:00:00');
        $base['month_cost_eur'] = (float)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(estimated_cost_eur),0) FROM {$table} WHERE created_at >= %s",
            $monthStart
        ));
        if ($base['monthly_budget_eur'] > 0) {
            $base['budget_usage_pct'] = round(($base['month_cost_eur'] / $base['monthly_budget_eur']) * 100, 1);
        }
        return $base;
    }

    public static function byProvider(int $days = 30): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $days = max(1, min(365, $days));
        $from = gmdate('Y-m-d H:i:s', time() - ($days - 1) * DAY_IN_SECONDS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT provider, COALESCE(SUM(request_count),0) requests, COALESCE(SUM(units),0) units, COALESCE(SUM(estimated_cost_eur),0) estimated_cost_eur, COALESCE(SUM(avoided_cost_eur),0) avoided_cost_eur, SUM(cache_status='hit') cache_hits, SUM(status IN ('error','timeout')) errors, SUM(status='blocked') blocked, MAX(created_at) last_used_at FROM " . self::tableName() . " WHERE created_at >= %s GROUP BY provider ORDER BY estimated_cost_eur DESC, requests DESC",
            $from
        ), ARRAY_A) ?: [];
    }

    public static function byOperation(int $days = 30, int $limit = 20): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $days = max(1, min(365, $days));
        $limit = max(1, min(100, $limit));
        $from = gmdate('Y-m-d H:i:s', time() - ($days - 1) * DAY_IN_SECONDS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT provider, operation, COALESCE(SUM(request_count),0) requests, COALESCE(SUM(units),0) units, COALESCE(SUM(CASE WHEN request_count>0 THEN units ELSE 0 END),0) executed_units, COALESCE(SUM(estimated_cost_eur),0) estimated_cost_eur, COALESCE(SUM(avoided_cost_eur),0) avoided_cost_eur, SUM(cache_status='hit') cache_hits, SUM(status IN ('error','timeout')) errors, SUM(status='blocked') blocked, ROUND(AVG(duration_ms),0) avg_duration_ms FROM " . self::tableName() . " WHERE created_at >= %s GROUP BY provider, operation ORDER BY estimated_cost_eur DESC, requests DESC LIMIT %d",
            $from,
            $limit
        ), ARRAY_A) ?: [];
    }

    public static function daily(int $days = 14): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $days = max(1, min(90, $days));
        $from = gmdate('Y-m-d 00:00:00', time() - ($days - 1) * DAY_IN_SECONDS);
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) usage_day, COALESCE(SUM(request_count),0) requests, COALESCE(SUM(estimated_cost_eur),0) estimated_cost_eur, COALESCE(SUM(avoided_cost_eur),0) avoided_cost_eur, SUM(cache_status='hit') cache_hits, SUM(status IN ('error','timeout')) errors, SUM(status='blocked') blocked FROM " . self::tableName() . " WHERE created_at >= %s GROUP BY DATE(created_at) ORDER BY usage_day DESC",
            $from
        ), ARRAY_A) ?: [];
    }

    public static function recent(int $limit = 100): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $limit = max(1, min(250, $limit));
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::tableName() . ' ORDER BY id DESC LIMIT %d',
            $limit
        ), ARRAY_A) ?: [];
    }

    public static function cleanup(): void {
        global $wpdb;
        if (!self::tableReady()) return;
        $days = max(7, min(3650, (int)Config::get('api_usage_retention_days', 90)));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::tableName() . ' WHERE created_at < %s', $cutoff));
    }

    private static function maybeBudgetAlert(): void {
        $budget = max(0.0, (float)Config::get('api_monthly_budget_eur', 0));
        if ($budget <= 0 || !class_exists(Monitor::class)) return;
        $checkKey = 'ff_api_budget_check_' . gmdate('Ym');
        if (get_transient($checkKey)) return;
        set_transient($checkKey, 1, 15 * MINUTE_IN_SECONDS);
        $summary = self::summary(31);
        $ratio = (float)($summary['budget_usage_pct'] ?? 0);
        if ($ratio < 80) return;
        $level = $ratio >= 100 ? 'critical' : 'warning';
        $bucket = gmdate('Ym') . '-' . ($ratio >= 100 ? '100' : '80');
        $key = 'ff_api_budget_alert_' . $bucket;
        if (get_transient($key)) return;
        set_transient($key, 1, DAY_IN_SECONDS);
        Monitor::record('api_budget', $level, $ratio >= 100 ? 'O orçamento mensal estimado de APIs foi ultrapassado.' : 'O consumo estimado de APIs atingiu 80% do orçamento mensal.', [
            'month_cost_eur' => round((float)$summary['month_cost_eur'], 4),
            'monthly_budget_eur' => $budget,
            'budget_usage_pct' => $ratio,
        ]);
    }

    private static function requestContext(array $data): array {
        $request = array_merge(is_array($_GET ?? null) ? $_GET : [], is_array($_POST ?? null) ? $_POST : []);
        return [
            'client_id' => absint($data['client_id'] ?? $request['client_id'] ?? $request['routespro_client_id'] ?? 0),
            'project_id' => absint($data['project_id'] ?? $request['project_id'] ?? $request['routespro_project_id'] ?? 0),
            'route_id' => absint($data['route_id'] ?? $request['route_id'] ?? $request['routespro_route_id'] ?? 0),
            'user_id' => absint($data['user_id'] ?? get_current_user_id()),
        ];
    }

    private static function sanitizeMeta(array $meta): array {
        $out = [];
        foreach ($meta as $key => $value) {
            $key = substr(sanitize_key((string)$key), 0, 64);
            if ($key === '' || preg_match('/token|secret|password|authorization|nonce|api.?key|address|coordinate|latitude|longitude|payload|response|body/i', $key)) continue;
            if (is_bool($value)) $out[$key] = $value ? 1 : 0;
            elseif (is_int($value) || is_float($value)) $out[$key] = $value;
            elseif (is_string($value)) $out[$key] = substr(sanitize_text_field($value), 0, 191);
        }
        return $out;
    }
}
