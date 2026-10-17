<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Canonical geographic context for routing/geocoding.
 *
 * Prevents ambiguous route endpoints such as "Montijo" from resolving in the
 * wrong country. The campaign/project country is authoritative; legacy
 * projects can infer the country from their linked PDVs.
 */
final class RoutingGeographyService {
    public const CONTRACT = 'fieldflow-routing-endpoint-geographic-integrity-v1';
    public const VERSION = 'routing_geography_v1';

    public static function normalizeCountryCode($value, string $fallback = 'PT'): string {
        $raw = trim((string)$value);
        $fallback = strtoupper(trim($fallback));
        if ($raw === '') return $fallback;
        $ascii = function_exists('remove_accents') ? remove_accents($raw) : $raw;
        $key = strtolower(trim($ascii));
        $aliases = [
            'pt' => 'PT', 'prt' => 'PT', 'portugal' => 'PT', 'portugues' => 'PT', 'portuguese' => 'PT',
            'es' => 'ES', 'esp' => 'ES', 'espanha' => 'ES', 'espana' => 'ES', 'spain' => 'ES', 'spanish' => 'ES',
        ];
        if (isset($aliases[$key])) return $aliases[$key];
        if (preg_match('/^[a-z]{2}$/i', $raw)) return strtoupper($raw);
        return $fallback;
    }

    public static function countryName(string $code): string {
        $code = self::normalizeCountryCode($code);
        return ['PT'=>'Portugal','ES'=>'Espanha'][$code] ?? $code;
    }

    public static function geocodeOptions(string $countryCode): array {
        $code = self::normalizeCountryCode($countryCode);
        return [
            'country_code' => $code,
            'region' => strtolower($code),
            'language' => $code === 'ES' ? 'es-ES' : 'pt-PT',
        ];
    }

    public static function countryCodeFromGeocodeRaw(array $raw): string {
        foreach ((array)($raw['address_components'] ?? []) as $component) {
            $types = (array)($component['types'] ?? []);
            if (in_array('country', $types, true)) {
                return self::normalizeCountryCode((string)($component['short_name'] ?? $component['long_name'] ?? ''), '');
            }
        }
        $address = is_array($raw['address'] ?? null) ? (array)$raw['address'] : [];
        foreach (['countryCode','countryCodeISO2','countryCodeIso2','country'] as $key) {
            if (!empty($address[$key])) return self::normalizeCountryCode((string)$address[$key], '');
        }
        if (!empty($raw['country_code'])) return self::normalizeCountryCode((string)$raw['country_code'], '');
        return '';
    }

    public static function resultMatchesCountry(array $result, string $countryCode): bool {
        $expected = self::normalizeCountryCode($countryCode);
        $actual = self::normalizeCountryCode((string)($result['country_code'] ?? self::countryCodeFromGeocodeRaw((array)($result['raw'] ?? []))), '');
        return $actual === '' || $actual === $expected;
    }


    /**
     * Conservative geographic guard used only to decide whether existing
     * coordinates can survive a transient geocoder failure. It is not a route
     * calculator. PT includes mainland, Madeira and Azores; ES includes
     * mainland/Balearics and Canary Islands.
     */
    public static function coordinatesLikelyInCountry($lat, $lng, string $countryCode): bool {
        if (!is_numeric($lat) || !is_numeric($lng)) return false;
        $lat = (float)$lat; $lng = (float)$lng;
        $code = self::normalizeCountryCode($countryCode);
        $boxes = [
            'PT' => [
                // Mainland is split by latitude so western Spain (including
                // Montijo/Badajoz) is not accepted by an overly broad box.
                [40.0, 42.3, -9.7, -6.1],
                [38.5, 40.0, -9.7, -6.75],
                [36.7, 38.5, -9.7, -6.9],
                [32.2, 33.3, -17.6, -15.7],
                [36.4, 40.2, -31.6, -24.3],
            ],
            'ES' => [
                [35.7, 43.9, -9.6, 4.5],
                [27.5, 29.6, -18.3, -13.2],
            ],
        ];
        if (empty($boxes[$code])) return true;
        foreach ($boxes[$code] as $box) {
            if ($lat >= $box[0] && $lat <= $box[1] && $lng >= $box[2] && $lng <= $box[3]) return true;
        }
        return false;
    }

    public static function projectCountryCode(int $projectId, string $fallback = 'PT'): string {
        global $wpdb;
        $projectId = absint($projectId);
        if ($projectId <= 0) return self::normalizeCountryCode($fallback);
        static $cache = [];
        if (isset($cache[$projectId])) return $cache[$projectId];
        $px = $wpdb->prefix . 'routespro_';
        $raw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$px}projects WHERE id=%d", $projectId));
        $meta = json_decode((string)$raw, true);
        if (!is_array($meta)) $meta = [];
        foreach (['routing_country_code','country_code','holiday_country'] as $key) {
            if (!empty($meta[$key])) return $cache[$projectId] = self::normalizeCountryCode($meta[$key], $fallback);
        }

        // Legacy inference. Locations already carry a country field. Use the
        // dominant non-empty country of the campaign instead of guessing from
        // endpoint text.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT l.country, COUNT(*) AS c FROM {$px}campaign_locations cl INNER JOIN {$px}locations l ON l.id=cl.location_id WHERE cl.project_id=%d AND cl.is_active=1 AND l.is_active=1 AND COALESCE(l.country,'')<>'' GROUP BY l.country ORDER BY c DESC",
            $projectId
        ), ARRAY_A) ?: [];
        if ($rows) {
            $top = self::normalizeCountryCode((string)($rows[0]['country'] ?? ''), $fallback);
            return $cache[$projectId] = $top;
        }
        return $cache[$projectId] = self::normalizeCountryCode($fallback);
    }

    public static function persistProjectCountryCode(int $projectId, string $countryCode): bool {
        global $wpdb;
        $projectId = absint($projectId);
        if ($projectId <= 0) return false;
        $code = self::normalizeCountryCode($countryCode);
        $table = $wpdb->prefix . 'routespro_projects';
        $row = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $projectId), ARRAY_A);
        if (!$row) return false;
        $meta = json_decode((string)($row['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        if (($meta['routing_country_code'] ?? '') === $code && ($meta['routing_geography_contract'] ?? '') === self::CONTRACT) return true;
        $meta['routing_country_code'] = $code;
        $meta['routing_geography_contract'] = self::CONTRACT;
        $meta['routing_country_updated_at'] = current_time('mysql');
        $ok = $wpdb->update($table, ['meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id'=>$projectId], ['%s'], ['%d']);
        return $ok !== false;
    }
}
