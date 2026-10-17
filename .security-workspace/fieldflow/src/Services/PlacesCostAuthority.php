<?php
namespace RoutesPro\Services;

use RoutesPro\Observability\ApiUsageLedger;
use RoutesPro\Support\Config;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow 2.2.584 Places & Geocoding Cost Authority.
 *
 * Avoids external Google Maps calls when FieldFlow already owns enough stable
 * location context to continue safely. It never stores request payloads in the
 * usage ledger and does not introduce an additional Google-content cache.
 */
final class PlacesCostAuthority {
    public const CONTRACT = 'fieldflow-places-geocoding-cost-authority-v1';
    public const GOOGLE_COORDINATE_CACHE_MAX_DAYS = 30;

    public static function normalizeAddress(string $value): string {
        $value = trim(wp_strip_all_tags($value));
        if ($value === '') return '';
        if (function_exists('remove_accents')) $value = remove_accents($value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?: $value);
    }

    public static function addressFingerprint(string $address): string {
        $normal = self::normalizeAddress($address);
        return $normal === '' ? '' : hash('sha256', $normal);
    }

    public static function hasCoordinates(array $point): bool {
        if (!is_numeric($point['lat'] ?? null) || !is_numeric($point['lng'] ?? null)) return false;
        $lat = (float)$point['lat'];
        $lng = (float)$point['lng'];
        return is_finite($lat) && is_finite($lng) && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    public static function isGoogleDerived(array $point): bool {
        foreach (['geocode_provider','provider','source'] as $key) {
            $value = sanitize_key((string)($point[$key] ?? ''));
            if ($value !== '' && (strpos($value, 'google') !== false || $value === 'places')) return true;
        }
        return false;
    }

    public static function googleCoordinateWindowValid(array $point): bool {
        if (!self::isGoogleDerived($point)) return true;
        $verifiedAt = trim((string)($point['geocode_verified_at'] ?? $point['updated_at'] ?? ''));
        $verifiedTs = $verifiedAt !== '' ? (strtotime($verifiedAt) ?: 0) : 0;
        return $verifiedTs > 0 && $verifiedTs >= (time() - self::GOOGLE_COORDINATE_CACHE_MAX_DAYS * DAY_IN_SECONDS);
    }

    /**
     * Endpoint reuse is signature based for FieldFlow-owned/imported coordinates.
     * Google-derived coordinates additionally respect the provider's 30-day
     * coordinate caching window, while place_id can remain retained separately.
     */
    public static function canReuseEndpoint(array $point, string $countryCode = 'PT'): bool {
        if (empty(Config::get('places_cost_authority_enabled', 1))) return false;
        if (!self::hasCoordinates($point)) return false;
        if (!self::googleCoordinateWindowValid($point)) return false;
        $address = trim((string)($point['address'] ?? $point['formatted_address'] ?? ''));
        if ($address === '') return true;
        $current = self::addressFingerprint($address);
        $previous = sanitize_text_field((string)($point['endpoint_address_fingerprint'] ?? ''));
        if ($previous === '' || !hash_equals($previous, $current)) return false;
        $previouslyVerified = !empty($point['endpoint_geocode_verified']) || !empty($point['_endpoint_geocode_verified']) || trim((string)($point['place_id'] ?? '')) !== '';
        if (!$previouslyVerified) return false;
        if (class_exists(RoutingGeographyService::class)) {
            $countryCode = RoutingGeographyService::normalizeCountryCode($countryCode);
            if (!RoutingGeographyService::coordinatesLikelyInCountry((float)$point['lat'], (float)$point['lng'], $countryCode)) return false;
        }
        return true;
    }

    /**
     * Reuses an existing PDV only inside the same operational scope. Google
     * sourced coordinates are accepted only when the record was refreshed in
     * the last 30 days; place_id remains safe to retain separately.
     */
    public static function findReusableLocation(string $address, string $countryCode = 'PT', int $clientId = 0, int $projectId = 0): ?array {
        if (empty(Config::get('places_cost_authority_enabled', 1))) return null;
        $address = trim($address);
        if ($address === '') return null;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $where = ['address=%s'];
        $args = [$address];
        if ($clientId > 0) { $where[] = 'client_id=%d'; $args[] = $clientId; }
        if ($projectId > 0) { $where[] = 'project_id=%d'; $args[] = $projectId; }
        $sql = "SELECT id,address,lat,lng,country,place_id,source,updated_at FROM {$px}locations WHERE " . implode(' AND ', $where) . " ORDER BY updated_at DESC,id DESC LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$args), ARRAY_A);
        if (!$row || !self::hasCoordinates($row)) return null;
        if (class_exists(RoutingGeographyService::class)) {
            $countryCode = RoutingGeographyService::normalizeCountryCode($countryCode);
            if (!RoutingGeographyService::coordinatesLikelyInCountry((float)$row['lat'], (float)$row['lng'], $countryCode)) return null;
        }
        $source = sanitize_key((string)($row['source'] ?? ''));
        $googleDerived = strpos($source, 'google') !== false || $source === 'places';
        if ($googleDerived) {
            $updated = strtotime((string)($row['updated_at'] ?? '')) ?: 0;
            if ($updated <= 0 || $updated < (time() - self::GOOGLE_COORDINATE_CACHE_MAX_DAYS * DAY_IN_SECONDS)) return null;
        }
        return [
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lng'],
            'formatted_address' => sanitize_text_field((string)$row['address']),
            'place_id' => sanitize_text_field((string)($row['place_id'] ?? '')),
            'country_code' => strtoupper($countryCode ?: 'PT'),
            'provider' => 'fieldflow_location',
            'location_id' => (int)$row['id'],
            'source' => $source ?: 'fieldflow',
        ];
    }

    public static function recordAvoided(string $operation, string $source): void {
        if (!class_exists(ApiUsageLedger::class)) return;
        try {
            ApiUsageLedger::record('google_maps', $operation, [
                'status' => 'success',
                'cache_status' => 'hit',
                'billable' => false,
                'request_count' => 0,
                'units' => 1,
                'duration_ms' => 0,
                'source' => $source,
                'meta' => ['authority_contract' => self::CONTRACT],
            ]);
        } catch (\Throwable $ignored) {}
    }
}
