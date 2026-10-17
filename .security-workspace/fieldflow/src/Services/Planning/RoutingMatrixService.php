<?php
namespace RoutesPro\Services\Planning;

use RoutesPro\Services\GoogleRoutes;

if (!defined('ABSPATH')) exit;

/**
 * Canonical cost matrix used by the planner, BO and front-office diagnostics.
 *
 * The matrix is intentionally provider-agnostic. Since 2.2.582 the internal
 * calibrated road estimator is the default planning authority. Google Route
 * Matrix is premium opt-in only, preventing origin x destination billing during
 * routine planning while preserving a complete matrix for the optimizer.
 */
class RoutingMatrixService {
    public const VERSION = 'fieldflow_routing_matrix_v3';

    public static function build(array $points, array $options = []): array {
        $mode = sanitize_key((string)($options['mode'] ?? 'hybrid'));
        if (!in_array($mode, ['hybrid', 'google', 'internal'], true)) $mode = 'hybrid';
        $points = self::normalizePoints($points);
        $count = count($points);
        $matrix = self::emptyMatrix($points, $mode);
        if ($count <= 0) return $matrix;

        $cacheDays = max(0, min(365, (int)($options['cache_days'] ?? (function_exists('get_option') ? (int)\RoutesPro\Support\Config::get('routing_cache_days', 30) : 30))));
        $cacheKey = 'ff_route_matrix_' . md5(wp_json_encode([
            'v' => self::VERSION,
            'mode' => $mode,
            'points' => array_map(static function(array $p): array {
                return [$p['key'], $p['lat'], $p['lng'], $p['address']];
            }, $points),
            'preference' => (string)\RoutesPro\Support\Config::get('google_routes_preference', 'TRAFFIC_UNAWARE'),
            'google_matrix_enabled' => !empty(\RoutesPro\Support\Config::get('google_route_matrix_enabled', 0)) ? 1 : 0,
            'route_mode' => (string)\RoutesPro\Support\Config::get('google_routes_route_mode', 'fastest_tolls'),
        ]));
        if (empty($options['force_refresh']) && function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached) && (string)($cached['matrix_version'] ?? '') === self::VERSION) return $cached;
        }

        // Always create a complete internal baseline. Google values then replace
        // the corresponding directed pairs. This prevents missing API elements
        // from becoming false zero-kilometre edges.
        foreach ($points as $from) {
            foreach ($points as $to) {
                $edge = self::internalEdge($from, $to);
                self::setEdge($matrix, $from['key'], $to['key'], $edge);
            }
        }

        $googleAttempted = false;
        $googleApplied = 0;
        $googleError = '';
        $googleLimit = self::googlePointLimit();
        $canUseGoogle = $mode !== 'internal'
            && !empty(\RoutesPro\Support\Config::get('google_route_matrix_enabled', 0))
            && class_exists('\\RoutesPro\\Services\\GoogleRoutes')
            && GoogleRoutes::enabled()
            && $count <= $googleLimit;

        if ($canUseGoogle) {
            $googleAttempted = true;
            $google = GoogleRoutes::calculateMatrix($points, ['force_refresh' => !empty($options['force_refresh'])]);
            if (!empty($google['ok']) && is_array($google['edges'] ?? null)) {
                foreach ((array)$google['edges'] as $fromKey => $destinations) {
                    foreach ((array)$destinations as $toKey => $edge) {
                        if (!array_key_exists($toKey, $matrix['distance_km'][$fromKey] ?? [])) continue;
                        if (($edge['status'] ?? '') === 'impossible') {
                            self::setEdge($matrix, (string)$fromKey, (string)$toKey, [
                                'distance_km' => null, 'travel_min' => null,
                                'source' => 'google_route_matrix', 'status' => 'impossible',
                            ]);
                            continue;
                        }
                        if (!is_numeric($edge['distance_km'] ?? null) || !is_numeric($edge['travel_min'] ?? null)) continue;
                        self::setEdge($matrix, (string)$fromKey, (string)$toKey, [
                            'distance_km' => round(max(0.0, (float)$edge['distance_km']), 3),
                            'travel_min' => round(max(0.0, (float)$edge['travel_min']), 1),
                            'source' => 'google_route_matrix',
                            'status' => 'complete',
                        ]);
                        if ((string)$fromKey !== (string)$toKey) $googleApplied++;
                    }
                }
            } else {
                $googleError = sanitize_text_field((string)($google['error'] ?? 'Google Route Matrix indisponível.'));
            }
        }

        $directedPairs = max(0, $count * max(0, $count - 1));
        $matrix['provider'] = $googleApplied > 0 ? ($googleApplied >= $directedPairs ? 'google_route_matrix' : 'hybrid') : 'internal_road_estimator';
        $matrix['source_label'] = $matrix['provider'] === 'google_route_matrix'
            ? 'Google Route Matrix'
            : ($matrix['provider'] === 'hybrid' ? 'Matriz híbrida Google + estimador interno' : 'Estimador rodoviário interno');
        $matrix['google_attempted'] = $googleAttempted ? 1 : 0;
        $matrix['google_edges'] = $googleApplied;
        $matrix['google_error'] = $googleError;
        $matrix['point_count'] = $count;
        $matrix['pair_count'] = $directedPairs;
        $matrix['coverage_pct'] = $directedPairs > 0 ? round(($googleApplied / $directedPairs) * 100, 1) : 100.0;
        $matrix['status'] = $count > 0 ? 'complete' : 'unavailable';
        $matrix['calculated_at'] = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');

        if ($cacheDays > 0 && function_exists('set_transient')) set_transient($cacheKey, $matrix, $cacheDays * DAY_IN_SECONDS);
        return $matrix;
    }

    public static function edge(array $matrix, array $from, array $to): array {
        $fromKey = self::pointKey($from, 'from');
        $toKey = self::pointKey($to, 'to');
        if (($matrix['edge_status'][$fromKey][$toKey] ?? '') === 'impossible') {
            return ['distance_km' => null, 'travel_min' => null, 'source' => 'google_route_matrix', 'status' => 'impossible'];
        }
        if (isset($matrix['distance_km'][$fromKey][$toKey]) && is_numeric($matrix['distance_km'][$fromKey][$toKey])) {
            return [
                'distance_km' => (float)$matrix['distance_km'][$fromKey][$toKey],
                'travel_min' => is_numeric($matrix['travel_min'][$fromKey][$toKey] ?? null) ? (float)$matrix['travel_min'][$fromKey][$toKey] : null,
                'source' => (string)($matrix['edge_source'][$fromKey][$toKey] ?? $matrix['provider'] ?? 'internal_road_estimator'),
                'status' => (string)($matrix['edge_status'][$fromKey][$toKey] ?? 'complete'),
            ];
        }
        return self::internalEdge(self::normalizePoint($from, 0), self::normalizePoint($to, 1));
    }

    public static function pathMetrics(array $items, array $startPoint = [], array $endPoint = [], array $matrix = []): array {
        $items = array_values($items);
        $start = self::normalizePoint($startPoint, -1, '__start__');
        $end = self::normalizePoint($endPoint, -2, '__end__');
        $hasStart = self::hasRoutingData($start);
        $hasEnd = self::hasRoutingData($end);
        $endpointStatus = $hasStart && $hasEnd ? 'complete' : ($hasStart ? 'missing_end' : ($hasEnd ? 'missing_start' : 'missing_both'));
        $missingEndpointCount = ($hasStart ? 0 : 1) + ($hasEnd ? 0 : 1);

        if (!$items) {
            return [
                'distance_km' => $missingEndpointCount === 0 ? 0.0 : null,
                'known_distance_km' => 0.0,
                'travel_min' => $missingEndpointCount === 0 ? 0.0 : null,
                'known_travel_min' => 0.0,
                'access_distance_km' => $hasStart ? 0.0 : null,
                'inter_stop_distance_km' => 0.0,
                'return_distance_km' => $hasEnd ? 0.0 : null,
                'access_travel_min' => $hasStart ? 0.0 : null,
                'inter_stop_travel_min' => 0.0,
                'return_travel_min' => $hasEnd ? 0.0 : null,
                'distance_source' => (string)($matrix['provider'] ?? 'internal_road_estimator'),
                'distance_status' => $missingEndpointCount === 0 ? 'complete' : 'incomplete',
                'endpoint_status' => $endpointStatus,
                'endpoints_complete' => $missingEndpointCount === 0 ? 1 : 0,
                'missing_endpoint_count' => $missingEndpointCount,
                'unresolved_leg_count' => $missingEndpointCount,
                'segment_count' => 0,
            ];
        }

        $distance = 0.0;
        $travel = 0.0;
        $unresolved = $missingEndpointCount;
        $parts = [
            'access_distance_km' => null,
            'inter_stop_distance_km' => 0.0,
            'return_distance_km' => null,
            'access_travel_min' => null,
            'inter_stop_travel_min' => 0.0,
            'return_travel_min' => null,
        ];
        $sources = [];

        $normalizedItems = [];
        foreach ($items as $idx => $item) $normalizedItems[] = self::normalizePoint((array)$item, $idx);

        if ($hasStart) {
            $edge = self::edge($matrix, $start, $normalizedItems[0]);
            $km = is_numeric($edge['distance_km'] ?? null) ? (float)$edge['distance_km'] : null;
            $min = is_numeric($edge['travel_min'] ?? null) ? (float)$edge['travel_min'] : null;
            if ($km === null || $min === null) {
                $unresolved++;
            } else {
                $distance += $km;
                $travel += $min;
                $parts['access_distance_km'] = $km;
                $parts['access_travel_min'] = $min;
                $sources[(string)($edge['source'] ?? 'internal_road_estimator')] = true;
            }
        }

        for ($i = 1; $i < count($normalizedItems); $i++) {
            $edge = self::edge($matrix, $normalizedItems[$i - 1], $normalizedItems[$i]);
            $km = is_numeric($edge['distance_km'] ?? null) ? (float)$edge['distance_km'] : null;
            $min = is_numeric($edge['travel_min'] ?? null) ? (float)$edge['travel_min'] : null;
            if ($km === null || $min === null) {
                $unresolved++;
                continue;
            }
            $distance += $km;
            $travel += $min;
            $parts['inter_stop_distance_km'] += $km;
            $parts['inter_stop_travel_min'] += $min;
            $sources[(string)($edge['source'] ?? 'internal_road_estimator')] = true;
        }

        if ($hasEnd) {
            $edge = self::edge($matrix, $normalizedItems[count($normalizedItems) - 1], $end);
            $km = is_numeric($edge['distance_km'] ?? null) ? (float)$edge['distance_km'] : null;
            $min = is_numeric($edge['travel_min'] ?? null) ? (float)$edge['travel_min'] : null;
            if ($km === null || $min === null) {
                $unresolved++;
            } else {
                $distance += $km;
                $travel += $min;
                $parts['return_distance_km'] = $km;
                $parts['return_travel_min'] = $min;
                $sources[(string)($edge['source'] ?? 'internal_road_estimator')] = true;
            }
        }

        $source = count($sources) === 1 ? (string)array_key_first($sources) : (count($sources) > 1 ? 'hybrid' : (string)($matrix['provider'] ?? 'internal_road_estimator'));
        return array_merge($parts, [
            'distance_km' => $unresolved > 0 ? null : round($distance, 2),
            'known_distance_km' => round($distance, 2),
            'travel_min' => $unresolved > 0 ? null : round($travel, 1),
            'known_travel_min' => round($travel, 1),
            'distance_source' => $source,
            'distance_status' => $unresolved > 0 ? 'incomplete' : 'complete',
            'endpoint_status' => $endpointStatus,
            'endpoints_complete' => $missingEndpointCount === 0 ? 1 : 0,
            'missing_endpoint_count' => $missingEndpointCount,
            'unresolved_leg_count' => $unresolved,
            'segment_count' => count($items) + 1,
        ]);
    }

    public static function pointKey(array $point, string $fallback = ''): string {
        if (!empty($point['_matrix_key'])) return sanitize_key((string)$point['_matrix_key']);
        if (!empty($point['matrix_key'])) return sanitize_key((string)$point['matrix_key']);
        $id = (int)($point['location_id'] ?? $point['id'] ?? 0);
        if ($id > 0) return 'loc_' . $id;
        $lat = is_numeric($point['lat'] ?? null) ? number_format((float)$point['lat'], 6, '.', '') : '';
        $lng = is_numeric($point['lng'] ?? null) ? number_format((float)$point['lng'], 6, '.', '') : '';
        if ($lat !== '' && $lng !== '') return 'geo_' . substr(md5($lat . '|' . $lng), 0, 16);
        $address = trim((string)($point['address'] ?? $point['name'] ?? ''));
        if ($address !== '') return 'addr_' . substr(md5(strtolower($address)), 0, 16);
        return sanitize_key($fallback !== '' ? $fallback : ('point_' . substr(md5(wp_json_encode($point)), 0, 12)));
    }

    private static function emptyMatrix(array $points, string $mode): array {
        return [
            'matrix_version' => self::VERSION,
            'mode' => $mode,
            'provider' => 'internal_road_estimator',
            'source_label' => 'Estimador rodoviário interno',
            'points' => $points,
            'distance_km' => [],
            'travel_min' => [],
            'edge_source' => [],
            'edge_status' => [],
            'status' => $points ? 'complete' : 'unavailable',
            'point_count' => count($points),
            'pair_count' => 0,
            'coverage_pct' => 0.0,
            'calculated_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
        ];
    }

    private static function normalizePoints(array $points): array {
        $out = [];
        $seen = [];
        foreach (array_values($points) as $idx => $point) {
            if (!is_array($point)) continue;
            $normalized = self::normalizePoint($point, $idx);
            if (!self::hasRoutingData($normalized)) continue;
            $key = $normalized['key'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $normalized;
        }
        return $out;
    }

    private static function normalizePoint(array $point, int $idx, string $forcedKey = ''): array {
        $point['_matrix_key'] = $forcedKey !== '' ? $forcedKey : self::pointKey($point, 'p_' . $idx);
        if (!is_numeric($point['lat'] ?? null) || !is_numeric($point['lng'] ?? null)
            || !is_finite((float)$point['lat']) || !is_finite((float)$point['lng'])
            || abs((float)$point['lat']) > 90 || abs((float)$point['lng']) > 180) {
            $point['lat'] = null;
            $point['lng'] = null;
        }
        return [
            'key' => (string)$point['_matrix_key'],
            '_matrix_key' => (string)$point['_matrix_key'],
            'id' => (int)($point['location_id'] ?? $point['id'] ?? 0),
            'name' => sanitize_text_field((string)($point['name'] ?? '')),
            'address' => sanitize_text_field((string)($point['address'] ?? '')),
            'lat' => is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null,
            'lng' => is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null,
        ];
    }

    private static function hasRoutingData(array $point): bool {
        return (is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) || trim((string)($point['address'] ?? '')) !== '';
    }

    private static function setEdge(array &$matrix, string $fromKey, string $toKey, array $edge): void {
        $matrix['distance_km'][$fromKey][$toKey] = is_numeric($edge['distance_km'] ?? null) ? (float)$edge['distance_km'] : null;
        $matrix['travel_min'][$fromKey][$toKey] = is_numeric($edge['travel_min'] ?? null) ? (float)$edge['travel_min'] : null;
        $matrix['edge_source'][$fromKey][$toKey] = (string)($edge['source'] ?? 'internal_road_estimator');
        $matrix['edge_status'][$fromKey][$toKey] = (string)($edge['status'] ?? 'complete');
    }

    private static function internalEdge(array $from, array $to): array {
        if ((string)($from['key'] ?? '') !== '' && (string)($from['key'] ?? '') === (string)($to['key'] ?? '')) {
            return ['distance_km' => 0.0, 'travel_min' => 0.0, 'source' => 'internal_road_estimator', 'status' => 'complete'];
        }
        $valid = is_numeric($from['lat'] ?? null) && is_numeric($from['lng'] ?? null) && is_numeric($to['lat'] ?? null) && is_numeric($to['lng'] ?? null);
        if (!$valid) return ['distance_km' => null, 'travel_min' => null, 'source' => 'unavailable', 'status' => 'unavailable'];
        $airKm = self::haversine((float)$from['lat'], (float)$from['lng'], (float)$to['lat'], (float)$to['lng']);
        // Calibrated road factor. Short urban hops receive a slightly larger
        // correction; long motorway legs stay closer to the geodesic line.
        $factor = $airKm < 8.0 ? 1.38 : ($airKm < 30.0 ? 1.30 : ($airKm < 100.0 ? 1.23 : 1.17));
        $roadKm = max(0.0, $airKm * $factor);
        $speed = $roadKm < 8.0 ? 28.0 : ($roadKm < 35.0 ? 38.0 : ($roadKm < 120.0 ? 55.0 : 72.0));
        $travelMin = ($roadKm / max(1.0, $speed)) * 60.0;
        if ($roadKm > 0.15) $travelMin += min(5.0, 1.5 + ($roadKm * 0.025));
        return [
            'distance_km' => round($roadKm, 3),
            'travel_min' => round(max(0.0, $travelMin), 1),
            'source' => 'internal_road_estimator',
            'status' => 'complete',
        ];
    }

    private static function googlePointLimit(): int {
        $preference = (string)\RoutesPro\Support\Config::get('google_routes_preference', 'TRAFFIC_UNAWARE');
        return $preference === 'TRAFFIC_AWARE_OPTIMAL' ? 10 : 25;
    }

    private static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }
}
