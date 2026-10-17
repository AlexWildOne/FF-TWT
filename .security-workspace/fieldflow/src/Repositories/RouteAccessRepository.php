<?php
namespace RoutesPro\Repositories;

use RoutesPro\Support\Permissions;
use WP_Error;

if (!defined('ABSPATH')) exit;

class RouteAccessRepository {
    public static function listRoutes(array $filters = []) {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';

        $date = sanitize_text_field((string)($filters['date'] ?? ''));
        $date_from = sanitize_text_field((string)($filters['date_from'] ?? ''));
        $date_to = sanitize_text_field((string)($filters['date_to'] ?? ''));
        $mine = !empty($filters['mine']);
        $client_id = absint($filters['client_id'] ?? 0);
        $project_id = absint($filters['project_id'] ?? 0);
        $owner_user_id = absint($filters['owner_user_id'] ?? 0);
        $location_id = absint($filters['location_id'] ?? 0);
        $limit = max(1, min(1000, absint($filters['limit'] ?? 500)));

        $where = [];
        $args = [];

        if ($date_from || $date_to) {
            if ($date_from) {
                $where[] = 'r.date >= %s';
                $args[] = $date_from;
            }
            if ($date_to) {
                $where[] = 'r.date <= %s';
                $args[] = $date_to;
            }
        } else {
            if (!$date) {
                $date = current_time('Y-m-d');
            }
            $where[] = 'r.date = %s';
            $args[] = $date;
        }

        if ($client_id) {
            $where[] = 'r.client_id = %d';
            $args[] = $client_id;
        }
        if ($project_id) {
            $where[] = 'r.project_id = %d';
            $args[] = $project_id;
        }
        if ($owner_user_id) {
            $where[] = "(r.owner_user_id = %d OR EXISTS (SELECT 1 FROM {$px}assignments ao WHERE ao.route_id = r.id AND ao.user_id = %d AND ao.is_active = 1))";
            $args[] = $owner_user_id;
            $args[] = $owner_user_id;
        }
        if ($location_id) {
            $where[] = "EXISTS (SELECT 1 FROM {$px}route_stops sl WHERE sl.route_id = r.id AND sl.location_id = %d)";
            $args[] = $location_id;
        }

        if ($mine) {
            $uid = get_current_user_id();
            if (!$uid) {
                return new WP_Error('forbidden', 'Não autenticado', ['status' => 401]);
            }
            $where[] = "(r.owner_user_id = %d OR EXISTS (SELECT 1 FROM {$px}assignments ax WHERE ax.route_id = r.id AND ax.user_id = %d AND ax.is_active = 1))";
            $args[] = $uid;
            $args[] = $uid;
        } else {
            $scopeCheck = Permissions::assert_scope_or_error($client_id, $project_id);
            if (is_wp_error($scopeCheck)) {
                return $scopeCheck;
            }

            list($scopeSql, $scopeArgs) = Permissions::scope_sql('r');
            if ($scopeSql !== '1=1') {
                $where[] = $scopeSql;
                $args = array_merge($args, $scopeArgs);
            }
        }

        if (!$where) {
            $where[] = '1=1';
        }

        // Reconciliar reportes já gravados antes de calcular o progresso visível.
        // Assim, calendário, app e portal usam a mesma fonte de verdade.
        $route_id_sql = "SELECT r.id FROM {$px}routes r WHERE " . implode(' AND ', $where) . " ORDER BY r.date ASC, r.id ASC LIMIT %d";
        $route_id_args = array_merge($args, [$limit]);
        $route_ids = $wpdb->get_col($wpdb->prepare($route_id_sql, ...$route_id_args)) ?: [];
        if ($route_ids && class_exists('\RoutesPro\Services\RouteProgressService')) {
            \RoutesPro\Services\RouteProgressService::sync_many($route_ids);
        }

        $select = "SELECT DISTINCT r.*, c.name AS client_name, p.name AS project_name,
                   COALESCE(NULLIF(owner.display_name,''), owner.user_login) AS owner_name,
                   COUNT(rs.id) AS stops_count,
                   SUM(CASE WHEN rs.status IN ('done','completed') THEN 1 ELSE 0 END) AS stops_done";
        $sql = "$select
                FROM {$px}routes r
                LEFT JOIN {$px}clients c ON c.id = r.client_id
                LEFT JOIN {$px}projects p ON p.id = r.project_id
                LEFT JOIN {$wpdb->users} owner ON owner.ID = r.owner_user_id
                LEFT JOIN {$px}route_stops rs ON rs.route_id = r.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY r.id
                ORDER BY r.date ASC, r.id ASC
                LIMIT %d";
        $args[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        $mapStopsByRoute = self::routeStopsForMapsMany(array_map(static function(array $row): int { return absint($row['id'] ?? 0); }, $rows));

        foreach ($rows as &$row) {
            $stops = (int)($row['stops_count'] ?? 0);
            $done = (int)($row['stops_done'] ?? 0);
            $row['stops_count'] = $stops;
            $row['stops_done'] = $done;
            $row['done_rate'] = $stops > 0 ? round(($done / $stops) * 100, 1) : 0;
            $meta = !empty($row['meta_json']) ? json_decode((string)$row['meta_json'], true) : [];
            if (!is_array($meta)) $meta = [];
            // FieldFlow 2.2.503: distance and toll values are resolved once by
            // RouteMetricsService. Portal, mobility, REST and exports must not
            // maintain separate compatibility/fallback trees for the same route.
            $metrics = is_array($meta['route_metrics'] ?? null)
                ? (array)$meta['route_metrics']
                : (is_array($meta['plan_summary'] ?? null) ? (array)$meta['plan_summary'] : []);
            $canonicalMetrics = \RoutesPro\Services\RouteMetricsService::canonicalFromRouteArray([
                'id' => (int)($row['id'] ?? 0),
                'meta_decoded' => $meta,
                'route_metrics' => $metrics,
            ]);
            $row['distance_km'] = is_numeric($canonicalMetrics['distance_km'] ?? null) ? round((float)$canonicalMetrics['distance_km'], 2) : null;
            $row['known_distance_km'] = is_numeric($canonicalMetrics['known_distance_km'] ?? null) ? round((float)$canonicalMetrics['known_distance_km'], 2) : null;
            $row['toll_cost_eur'] = is_numeric($canonicalMetrics['toll_cost_eur'] ?? null) ? round((float)$canonicalMetrics['toll_cost_eur'], 2) : null;
            $row['toll_estimated_eur'] = is_numeric($canonicalMetrics['toll_estimated_eur'] ?? null) ? round((float)$canonicalMetrics['toll_estimated_eur'], 2) : null;
            $row['known_toll_cost_eur'] = is_numeric($canonicalMetrics['known_toll_cost_eur'] ?? null) ? round((float)$canonicalMetrics['known_toll_cost_eur'], 2) : null;
            $row['distance_value_source'] = (string)($canonicalMetrics['distance_value_source'] ?? '');
            $row['toll_value_source'] = (string)($canonicalMetrics['toll_value_source'] ?? '');
            $row['route_metrics'] = array_merge($metrics, $canonicalMetrics);
            $row['distance_status'] = (string)($canonicalMetrics['distance_status'] ?? ($metrics['distance_status'] ?? ''));
            $row['endpoint_status'] = (string)($canonicalMetrics['endpoint_status'] ?? '');
            $row['endpoints_complete'] = !empty($canonicalMetrics['endpoints_complete']) ? 1 : 0;
            $row['endpoints_routable'] = !empty($canonicalMetrics['endpoints_routable']) ? 1 : 0;
            $row['endpoints_trusted'] = !empty($canonicalMetrics['endpoints_complete']) ? 1 : 0;
            $row['missing_endpoint_count'] = max(0, (int)($canonicalMetrics['missing_endpoint_count'] ?? 0));
            foreach (['access_distance_km','inter_stop_distance_km','return_distance_km','travel_min','optimization_saved_distance_km','optimization_saved_travel_min','territory_dominant_share_pct','territory_direction_count','territory_direction_switches','territory_direction_reentries','territory_opposite_direction_switches','territory_locality_count','territory_locality_reentries','territory_bearing_span_deg','territory_far_stop_count','territory_coherence_score'] as $metricKey) {
                $row[$metricKey] = is_numeric($metrics[$metricKey] ?? null) ? (float)$metrics[$metricKey] : null;
            }
            foreach (['distance_source','matrix_provider','matrix_version','optimization_method','territorial_coherence_mode','territory_dominant_direction','territory_dominant_direction_label','territory_coherence_status','territory_coherence_label','territory_route_cone_label'] as $metricKey) {
                $row[$metricKey] = (string)($metrics[$metricKey] ?? '');
            }
            $row['territory_same_base'] = !empty($metrics['territory_same_base']) ? 1 : 0;
            $row['territory_opposite_mix'] = !empty($metrics['territory_opposite_mix']) ? 1 : 0;
            $row['territory_base_crossing_risk'] = !empty($metrics['territory_base_crossing_risk']) ? 1 : 0;
            $row['toll_model'] = (string)($canonicalMetrics['toll_model'] ?? self::extractTollModel($meta));
            $row['toll_provider'] = (string)($canonicalMetrics['toll_provider'] ?? self::extractTollProvider($meta));
            $row['toll_is_real_api'] = (int)($canonicalMetrics['toll_is_real_api'] ?? self::extractTollIsRealApi($meta));
            if (class_exists('\RoutesPro\Services\MobilityCostService')) {
                $row['mobility_cost'] = \RoutesPro\Services\MobilityCostService::calculateForRouteArray(array_merge($row, [
                    'meta_decoded' => $meta,
                    'metrics' => $metrics,
                ]));
                $row['mobility_cost'] = \RoutesPro\Services\MobilityCostService::reportingProjection($row['mobility_cost']);
            }
            $mapsPayload = \RoutesPro\Services\GoogleMapsUrlService::routePayload($meta, $mapStopsByRoute[(int)($row['id'] ?? 0)] ?? []);
            $row['start_point'] = $mapsPayload['start_point'];
            $row['end_point'] = $mapsPayload['end_point'];
            $row['google_maps_url'] = $mapsPayload['google_maps_url'];
        }
        unset($row);

        return $rows;
    }

    private static function routeStopsForMapsMany(array $route_ids): array {
        $route_ids = array_values(array_unique(array_filter(array_map('absint', $route_ids))));
        if (!$route_ids) return [];
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $placeholders = implode(',', array_fill(0, count($route_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT rs.route_id, l.name AS location_name, l.address, l.lat, l.lng FROM {$px}route_stops rs INNER JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id IN ({$placeholders}) ORDER BY rs.route_id ASC, rs.seq ASC, rs.id ASC",
            ...$route_ids
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $route_id = absint($row['route_id'] ?? 0);
            if (!$route_id) continue;
            unset($row['route_id']);
            $out[$route_id][] = $row;
        }
        return $out;
    }

    private static function hasRouteMetrics(array $meta): bool {
        return is_array($meta['route_metrics'] ?? null) && (isset($meta['route_metrics']['distance_km']) || isset($meta['route_metrics']['toll_cost_eur']));
    }

    private static function extractDisplayDistanceKm(int $route_id, array $meta): float {
        foreach (['route_metrics', 'portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (!is_array($bucket)) continue;
            if (isset($bucket['distance_km']) && is_numeric($bucket['distance_km'])) return (float)$bucket['distance_km'];
            if (isset($bucket['known_distance_km']) && is_numeric($bucket['known_distance_km'])) return (float)$bucket['known_distance_km'];
            if (isset($bucket['total_distance_km']) && is_numeric($bucket['total_distance_km'])) return (float)$bucket['total_distance_km'];
            if (isset($bucket['suggestion_distance_km']) && is_numeric($bucket['suggestion_distance_km'])) return (float)$bucket['suggestion_distance_km'];
        }
        if (isset($meta['distance_km']) && is_numeric($meta['distance_km'])) return (float)$meta['distance_km'];
        if (isset($meta['total_distance_km']) && is_numeric($meta['total_distance_km'])) return (float)$meta['total_distance_km'];
        return self::estimateRouteDistanceKm($route_id, $meta);
    }

    private static function extractDisplayTollCostEur(int $route_id, array $meta): float {
        foreach (['route_metrics', 'portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (!is_array($bucket)) continue;
            foreach (['toll_cost_eur', 'toll_estimated_eur', 'suggestion_toll_cost_eur'] as $key) {
                if (isset($bucket[$key]) && is_numeric($bucket[$key])) return (float)$bucket[$key];
            }
        }
        if (isset($meta['toll_cost_eur']) && is_numeric($meta['toll_cost_eur'])) return (float)$meta['toll_cost_eur'];
        return 0.0;
    }

    private static function isAutomaticSuggestionRoute(array $meta): bool {
        if (!empty($meta['generated_week_plan'])) return true;
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (!is_array($bucket)) continue;
            foreach (['source', 'created_from', 'origin'] as $key) {
                if (!empty($bucket[$key]) && strpos((string)$bucket[$key], 'automatic_route_suggestion') !== false) return true;
            }
        }
        return false;
    }

    private static function estimateSuggestionRouteDistanceKm(int $route_id, array $route_meta = []): float {
        if ($route_id <= 0) return 0.0;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stops = $wpdb->get_results($wpdb->prepare("SELECT l.lat, l.lng FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $route_id), ARRAY_A) ?: [];
        $points = [];
        $start = is_array($route_meta['start_point'] ?? null) ? (array)$route_meta['start_point'] : [];
        if (is_numeric($start['lat'] ?? null) && is_numeric($start['lng'] ?? null)) $points[] = [(float)$start['lat'], (float)$start['lng']];
        foreach ($stops as $stop) {
            if (is_numeric($stop['lat'] ?? null) && is_numeric($stop['lng'] ?? null)) $points[] = [(float)$stop['lat'], (float)$stop['lng']];
        }
        $end = is_array($route_meta['end_point'] ?? null) ? (array)$route_meta['end_point'] : [];
        if (is_numeric($end['lat'] ?? null) && is_numeric($end['lng'] ?? null)) $points[] = [(float)$end['lat'], (float)$end['lng']];
        $km = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $km += self::haversineKm($points[$i-1][0], $points[$i-1][1], $points[$i][0], $points[$i][1]);
        }
        return round($km, 2);
    }

    private static function extractDistanceKm(array $meta): float {
        foreach ([['route_metrics','distance_km'], ['metrics','distance_km'], ['plan_summary','distance_km']] as $path) {
            $bucket = $meta[$path[0]] ?? null;
            if (is_array($bucket) && isset($bucket[$path[1]]) && is_numeric($bucket[$path[1]])) {
                return (float)$bucket[$path[1]];
            }
        }
        if (isset($meta['distance_km']) && is_numeric($meta['distance_km'])) {
            return (float)$meta['distance_km'];
        }
        if (isset($meta['total_distance_km']) && is_numeric($meta['total_distance_km'])) {
            return (float)$meta['total_distance_km'];
        }
        return 0.0;
    }


    private static function extractTollCostEur(array $meta): float {
        foreach ([['route_metrics','toll_cost_eur'], ['route_metrics','toll_estimated_eur'], ['plan_summary','toll_cost_eur'], ['plan_summary','toll_estimated_eur'], ['metrics','toll_cost_eur']] as $path) {
            $bucket = $meta[$path[0]] ?? null;
            if (is_array($bucket) && isset($bucket[$path[1]]) && is_numeric($bucket[$path[1]])) {
                return (float)$bucket[$path[1]];
            }
        }
        if (isset($meta['toll_cost_eur']) && is_numeric($meta['toll_cost_eur'])) return (float)$meta['toll_cost_eur'];
        return 0.0;
    }

    private static function extractTollModel(array $meta): string {
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'route_metrics', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (is_array($bucket) && !empty($bucket['toll_model'])) return (string)$bucket['toll_model'];
        }
        return '';
    }

    private static function extractTollProvider(array $meta): string {
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'route_metrics', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (is_array($bucket) && !empty($bucket['toll_provider'])) return (string)$bucket['toll_provider'];
            if (is_array($bucket) && !empty($bucket['routing_provider'])) return (string)$bucket['routing_provider'];
        }
        return '';
    }

    private static function extractTollIsRealApi(array $meta): int {
        foreach (['portal_summary', 'generated_plan_summary', 'original_plan_summary', 'plan_summary', 'route_metrics', 'metrics'] as $bucketKey) {
            $bucket = $meta[$bucketKey] ?? null;
            if (is_array($bucket) && array_key_exists('toll_is_real_api', $bucket)) return !empty($bucket['toll_is_real_api']) ? 1 : 0;
        }
        return 0;
    }

    private static function estimateRouteDistanceKm(int $route_id, array $route_meta = []): float {
        if ($route_id <= 0) return 0.0;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $stops = $wpdb->get_results($wpdb->prepare("SELECT rs.meta_json, l.lat, l.lng FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $route_id), ARRAY_A) ?: [];
        $km = 0.0;
        $hasStoredLegs = false;
        foreach ($stops as $stop) {
            $meta = !empty($stop['meta_json']) ? json_decode((string)$stop['meta_json'], true) : [];
            if (!is_array($meta)) $meta = [];
            foreach (['distance_from_prev_km', 'leg_distance_km'] as $key) {
                if (isset($meta[$key]) && is_numeric($meta[$key]) && (float)$meta[$key] > 0) {
                    $km += (float)$meta[$key];
                    $hasStoredLegs = true;
                    break;
                }
            }
        }
        if (!$hasStoredLegs) {
            $points = [];
            $start = is_array($route_meta['start_point'] ?? null) ? (array)$route_meta['start_point'] : [];
            if (is_numeric($start['lat'] ?? null) && is_numeric($start['lng'] ?? null)) $points[] = [(float)$start['lat'], (float)$start['lng']];
            foreach ($stops as $stop) {
                if (is_numeric($stop['lat'] ?? null) && is_numeric($stop['lng'] ?? null)) $points[] = [(float)$stop['lat'], (float)$stop['lng']];
            }
            $end = is_array($route_meta['end_point'] ?? null) ? (array)$route_meta['end_point'] : [];
            if (is_numeric($end['lat'] ?? null) && is_numeric($end['lng'] ?? null)) $points[] = [(float)$end['lat'], (float)$end['lng']];
            for ($i = 1; $i < count($points); $i++) {
                $km += self::haversineKm($points[$i-1][0], $points[$i-1][1], $points[$i][0], $points[$i][1]) * 1.25;
            }
        }
        $metrics = is_array($route_meta['route_metrics'] ?? null) ? (array)$route_meta['route_metrics'] : (is_array($route_meta['plan_summary'] ?? null) ? (array)$route_meta['plan_summary'] : []);
        $returnKm = $metrics['return_distance_km'] ?? ($metrics['end_leg_distance_km'] ?? null);
        if (is_numeric($returnKm) && $hasStoredLegs) {
            $km += (float)$returnKm;
        }
        return round($km, 2);
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1, sqrt($a)));
    }

}
