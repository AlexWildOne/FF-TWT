<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

class GoogleMapsUrlService {
    private const MAX_WAYPOINTS = 9;

    public static function forRouteId(int $route_id): string {
        if ($route_id <= 0) return '';
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_json FROM {$px}routes WHERE id=%d LIMIT 1",
            $route_id
        ), ARRAY_A);
        if (!$route) return '';
        $meta = !empty($route['meta_json']) ? json_decode((string)$route['meta_json'], true) : [];
        if (!is_array($meta)) $meta = [];
        $stops = $wpdb->get_results($wpdb->prepare(
            "SELECT l.name AS location_name, l.address, l.lat, l.lng
             FROM {$px}route_stops rs
             INNER JOIN {$px}locations l ON l.id=rs.location_id
             WHERE rs.route_id=%d
             ORDER BY rs.seq ASC, rs.id ASC",
            $route_id
        ), ARRAY_A) ?: [];
        return self::fromRouteData($meta, $stops);
    }

    public static function fromRouteData(array $meta, array $stops): string {
        $points = [];
        $start = is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : [];
        $end = is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : [];

        $startValue = self::pointValue($start);
        $endValue = self::pointValue($end);
        // Operational Endpoint Source of Truth: navigation may never silently
        // promote the first/last PDV to route endpoints. Missing real endpoints
        // means there is no canonical directions URL yet.
        if ($startValue === '' || $endValue === '') return '';
        $points[] = $startValue;
        foreach ($stops as $stop) {
            if (!is_array($stop)) continue;
            $value = self::pointValue($stop);
            if ($value !== '') $points[] = $value;
        }
        $points[] = $endValue;

        $points = self::deduplicateAdjacent($points);
        if (!$points) return '';
        if (count($points) === 1) {
            return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($points[0]);
        }

        $origin = array_shift($points);
        $destination = array_pop($points);
        $waypoints = array_slice($points, 0, self::MAX_WAYPOINTS);
        $url = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&dir_action=navigate'
            . '&origin=' . rawurlencode($origin)
            . '&destination=' . rawurlencode($destination);
        if ($waypoints) $url .= '&waypoints=' . rawurlencode(implode('|', $waypoints));
        return $url;
    }

    public static function pointValue(array $point): string {
        $lat = $point['lat'] ?? null;
        $lng = $point['lng'] ?? null;
        if (is_numeric($lat) && is_numeric($lng)) {
            return self::coordinate((float)$lat) . ',' . self::coordinate((float)$lng);
        }
        foreach (['address', 'formatted_address', 'location_name', 'name', 'label'] as $key) {
            $value = trim(wp_strip_all_tags((string)($point[$key] ?? '')));
            if ($value !== '') return $value;
        }
        return '';
    }

    public static function routePayload(array $meta, array $stops): array {
        return [
            'start_point' => is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : [],
            'end_point' => is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : [],
            'google_maps_url' => self::fromRouteData($meta, $stops),
        ];
    }

    private static function coordinate(float $value): string {
        return rtrim(rtrim(number_format($value, 7, '.', ''), '0'), '.');
    }

    private static function deduplicateAdjacent(array $points): array {
        $out = [];
        $previous = null;
        foreach ($points as $point) {
            $point = trim((string)$point);
            if ($point === '') continue;
            $key = strtolower($point);
            if ($previous !== null && $key === $previous) continue;
            $out[] = $point;
            $previous = $key;
        }
        return $out;
    }
}
