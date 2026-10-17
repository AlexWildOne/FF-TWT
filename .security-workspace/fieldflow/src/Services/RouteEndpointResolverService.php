<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Canonical resolver for route start/end endpoints.
 *
 * Keeps the visible address authoritative, records provenance, invalidates
 * stale coordinates, and detects legacy endpoints that silently collapse to
 * the first/last PDV. No routing/optimisation rule is changed here.
 */
final class RouteEndpointResolverService {
    public const CONTRACT = 'fieldflow-route-endpoint-provenance-reconciliation-v1';
    public const SEMANTIC_EDGE_CONTRACT = 'fieldflow-semantic-edge-endpoint-reconciliation-v1';
    public const GEOGRAPHIC_CONTRACT = 'fieldflow-routing-endpoint-geographic-integrity-v1';
    public const VERSION = 'fieldflow_route_endpoint_resolver_v2';
    public const COINCIDENCE_KM = 0.03;

    public static function resolve(array $point, string $role = 'start', string $provenance = 'legacy', array $options = []): array {
        $role = $role === 'end' ? 'end' : 'start';
        $address = trim(sanitize_text_field((string)($point['address'] ?? $point['formatted_address'] ?? '')));
        $name = sanitize_text_field((string)($point['name'] ?? ''));
        $lat = is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null;
        $lng = is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null;
        if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
            $lat = null;
            $lng = null;
        }
        $source = sanitize_key((string)($point['provenance'] ?? $point['endpoint_provenance'] ?? $point['_endpoint_provenance'] ?? $provenance));
        if ($source === '') $source = 'legacy';
        $allowMatch = !empty($point['allow_matches_stop']) || !empty($point['_allow_matches_stop']) || !empty($options['allow_matches_stop']);
        $forceAddressAuthority = !array_key_exists('force_address_authority', $options) || !empty($options['force_address_authority']);
        $forceExternalGeocode = !empty($options['force_external_geocode']);
        $discardStaleOnFailure = !array_key_exists('discard_stale_on_failure', $options) || !empty($options['discard_stale_on_failure']);
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService')
            ? RoutingGeographyService::normalizeCountryCode((string)($options['country_code'] ?? $point['country_code'] ?? $point['routing_country_code'] ?? 'PT'))
            : strtoupper((string)($options['country_code'] ?? $point['country_code'] ?? $point['routing_country_code'] ?? 'PT'));

        $beforeLat = $lat;
        $beforeLng = $lng;
        $geocodeVerified = 0;
        $coordinatesRefreshed = 0;
        $coordinatesDiscarded = 0;
        $geocodeStatus = $address === '' ? 'not_required' : 'pending';
        $geo = null;

        if ($address !== '' && $forceAddressAuthority) {
            $reuseExisting = !$forceExternalGeocode
                && class_exists('\RoutesPro\Services\PlacesCostAuthority')
                && PlacesCostAuthority::canReuseEndpoint($point, $countryCode);
            if ($reuseExisting) {
                $geocodeVerified = 1;
                $geocodeStatus = 'signature_reuse';
                PlacesCostAuthority::recordAvoided('geocode', 'endpoint_signature_reuse');
            } else {
                $geo = self::geocodeAddress($address, $countryCode);
                if ($geo) {
                    $lat = (float)$geo['lat'];
                    $lng = (float)$geo['lng'];
                    $geocodeVerified = 1;
                    $geocodeStatus = 'verified';
                    $coordinatesRefreshed = ($beforeLat === null || $beforeLng === null || abs($beforeLat - $lat) > 0.00001 || abs($beforeLng - $lng) > 0.00001) ? 1 : 0;
                } else {
                    $geocodeStatus = 'failed';
                    $countrySafeCoordinates = $lat !== null && $lng !== null
                        && class_exists('\RoutesPro\Services\RoutingGeographyService')
                        && RoutingGeographyService::coordinatesLikelyInCountry($lat, $lng, $countryCode);
                    $fingerprint = (string)($point['endpoint_address_fingerprint'] ?? '');
                    $sameAddress = $fingerprint !== '' && hash_equals($fingerprint, self::addressFingerprint($address));
                    if ($countrySafeCoordinates && $sameAddress
                        && (!class_exists(PlacesCostAuthority::class) || PlacesCostAuthority::googleCoordinateWindowValid($point))) {
                        $geocodeStatus = 'country_safe_coordinate_fallback';
                    } elseif (!$sameAddress || ($discardStaleOnFailure && ($lat !== null || $lng !== null))) {
                        $lat = null;
                        $lng = null;
                        $coordinatesDiscarded = 1;
                    }
                }
            }
        } elseif ($lat !== null && $lng !== null) {
            $geocodeStatus = 'coordinate_only';
        }

        return [
            'name' => $name,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'provenance' => $source,
            'allow_matches_stop' => $allowMatch ? 1 : 0,
            'endpoint_role' => $role,
            'endpoint_resolver_version' => self::VERSION,
            'endpoint_address_fingerprint' => self::addressFingerprint($address),
            'endpoint_geocode_verified' => $geocodeVerified,
            'endpoint_geocode_status' => $geocodeStatus,
            'endpoint_coordinates_refreshed' => $coordinatesRefreshed,
            'endpoint_coordinates_discarded' => $coordinatesDiscarded,
            'country_code' => $countryCode,
            'formatted_address' => sanitize_text_field((string)($geo['formatted_address'] ?? ($coordinatesDiscarded ? $address : ($point['formatted_address'] ?? $address)))),
            'place_id' => sanitize_text_field((string)($geo['place_id'] ?? ($coordinatesDiscarded ? '' : ($point['place_id'] ?? '')))),
            'geocode_provider' => sanitize_key((string)($geo['provider'] ?? $point['geocode_provider'] ?? '')),
            'geocode_verified_at' => $coordinatesDiscarded ? '' : ($geocodeStatus === 'signature_reuse' ? sanitize_text_field((string)($point['geocode_verified_at'] ?? '')) : ($geocodeVerified ? current_time('mysql') : sanitize_text_field((string)($point['geocode_verified_at'] ?? '')))),
            'routing_geography_contract' => self::GEOGRAPHIC_CONTRACT,
            // Compatibility markers consumed by the 2.2.505 route layer.
            '_endpoint_geocode_verified' => $geocodeVerified,
            '_endpoint_coordinates_refreshed' => $coordinatesRefreshed,
            '_endpoint_provenance' => $source,
            '_allow_matches_stop' => $allowMatch ? 1 : 0,
        ];
    }

    public static function resolvePair(array $start, array $end, array $options = []): array {
        $startSource = sanitize_key((string)($options['start_provenance'] ?? $start['provenance'] ?? 'legacy')) ?: 'legacy';
        $endSource = sanitize_key((string)($options['end_provenance'] ?? $end['provenance'] ?? 'legacy')) ?: 'legacy';
        return [
            'start' => self::resolve($start, 'start', $startSource, [
                'allow_matches_stop' => !empty($options['allow_start_matches_stop']) || !empty($start['allow_matches_stop']),
                'force_address_authority' => $options['force_address_authority'] ?? true,
                'force_external_geocode' => $options['force_external_geocode'] ?? false,
                'discard_stale_on_failure' => $options['discard_stale_on_failure'] ?? true,
                'country_code' => $options['country_code'] ?? $start['country_code'] ?? 'PT',
            ]),
            'end' => self::resolve($end, 'end', $endSource, [
                'allow_matches_stop' => !empty($options['allow_end_matches_stop']) || !empty($end['allow_matches_stop']),
                'force_address_authority' => $options['force_address_authority'] ?? true,
                'force_external_geocode' => $options['force_external_geocode'] ?? false,
                'discard_stale_on_failure' => $options['discard_stale_on_failure'] ?? true,
                'country_code' => $options['country_code'] ?? $end['country_code'] ?? 'PT',
            ]),
        ];
    }

    public static function integrity(array $start, array $end, array $firstStop = [], array $lastStop = [], array $options = []): array {
        $hasStart = self::hasRoutingData($start);
        $hasEnd = self::hasRoutingData($end);
        $allowStart = !empty($options['allow_start_matches_stop']) || !empty($start['allow_matches_stop']) || !empty($start['_allow_matches_stop']);
        $allowEnd = !empty($options['allow_end_matches_stop']) || !empty($end['allow_matches_stop']) || !empty($end['_allow_matches_stop']);
        $startMatch = $hasStart && self::hasRoutingData($firstStop) ? self::pointsCoincide($start, $firstStop) : false;
        $endMatch = $hasEnd && self::hasRoutingData($lastStop) ? self::pointsCoincide($end, $lastStop) : false;
        $suspiciousStart = $startMatch && !$allowStart;
        $suspiciousEnd = $endMatch && !$allowEnd;

        $warnings = [];
        if ($suspiciousStart) $warnings[] = 'start_matches_first_stop_unconfirmed';
        elseif ($startMatch && $allowStart) $warnings[] = 'start_matches_first_stop_confirmed';
        if ($suspiciousEnd) $warnings[] = 'end_matches_last_stop_unconfirmed';
        elseif ($endMatch && $allowEnd) $warnings[] = 'end_matches_last_stop_confirmed';
        if (!$hasStart) $warnings[] = 'missing_start';
        if (!$hasEnd) $warnings[] = 'missing_end';
        if ($hasStart && !empty($start['address']) && empty($start['endpoint_geocode_verified']) && ($start['endpoint_geocode_status'] ?? '') === 'failed') $warnings[] = 'start_geocode_unverified';
        if ($hasEnd && !empty($end['address']) && empty($end['endpoint_geocode_verified']) && ($end['endpoint_geocode_status'] ?? '') === 'failed') $warnings[] = 'end_geocode_unverified';

        if (!$hasStart && !$hasEnd) $status = 'missing_both';
        elseif (!$hasStart) $status = 'missing_start';
        elseif (!$hasEnd) $status = 'missing_end';
        elseif ($suspiciousStart && $suspiciousEnd) $status = 'suspicious_both';
        elseif ($suspiciousStart) $status = 'suspicious_start';
        elseif ($suspiciousEnd) $status = 'suspicious_end';
        else $status = 'complete';

        $complete = $status === 'complete';
        $routable = $hasStart && $hasEnd;
        return [
            'endpoint_status' => $status,
            // Historical trust flag: suspicious coincident endpoints are not
            // considered trusted until explicitly confirmed/reconciled.
            'endpoints_complete' => $complete ? 1 : 0,
            // Financial/routing continuity flag: when both visible endpoints
            // are routable we can still calculate every route leg while
            // surfacing the trust warning separately. A warning must never
            // turn an otherwise complete route into a silent zero-cost route.
            'endpoints_routable' => $routable ? 1 : 0,
            'endpoints_trusted' => $complete ? 1 : 0,
            'missing_endpoint_count' => ($hasStart ? 0 : 1) + ($hasEnd ? 0 : 1),
            'suspicious_endpoint_count' => ($suspiciousStart ? 1 : 0) + ($suspiciousEnd ? 1 : 0),
            'has_start' => $hasStart ? 1 : 0,
            'has_end' => $hasEnd ? 1 : 0,
            'start_matches_first_stop' => $startMatch ? 1 : 0,
            'end_matches_last_stop' => $endMatch ? 1 : 0,
            'start_match_confirmed' => ($startMatch && $allowStart) ? 1 : 0,
            'end_match_confirmed' => ($endMatch && $allowEnd) ? 1 : 0,
            'endpoint_warning_codes' => array_values(array_unique($warnings)),
            'start_provenance' => sanitize_key((string)($start['provenance'] ?? $start['_endpoint_provenance'] ?? 'legacy')),
            'end_provenance' => sanitize_key((string)($end['provenance'] ?? $end['_endpoint_provenance'] ?? 'legacy')),
            'start_to_first_air_km' => self::airDistanceOrNull($start, $firstStop),
            'last_to_end_air_km' => self::airDistanceOrNull($lastStop, $end),
            'contract' => self::CONTRACT,
        ];
    }

    public static function withProvenance(array $point, string $provenance, bool $allowMatchesStop = false): array {
        $point['provenance'] = sanitize_key($provenance) ?: 'legacy';
        $point['allow_matches_stop'] = $allowMatchesStop ? 1 : 0;
        return $point;
    }

    public static function pointsCoincide(array $a, array $b, float $thresholdKm = self::COINCIDENCE_KM): bool {
        // 2.2.510 semantic edge reconciliation. Address identity must win over
        // coordinate drift. Google/geocoder coordinates for the very same PDV
        // can legitimately differ from the stored location centroid by 100-200m.
        // Checking distance first caused those legacy first/last-PDV endpoints
        // to be trusted as real route bases, so the operative profile was never
        // reached during reconciliation.
        $aAddress = self::normaliseText((string)($a['address'] ?? $a['formatted_address'] ?? ''));
        $bAddress = self::normaliseText((string)($b['address'] ?? $b['formatted_address'] ?? ''));
        if ($aAddress !== '' && $bAddress !== '' && $aAddress === $bAddress) return true;

        $distance = self::airDistanceOrNull($a, $b);
        return $distance !== null && $distance <= max(0.0, $thresholdKm);
    }

    public static function hasRoutingData(array $point): bool {
        if (is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) return true;
        return trim((string)($point['address'] ?? $point['formatted_address'] ?? '')) !== '';
    }

    public static function publicPoint(array $point): array {
        return [
            'address' => sanitize_text_field((string)($point['address'] ?? '')),
            'lat' => is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null,
            'lng' => is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null,
            'provenance' => sanitize_key((string)($point['provenance'] ?? $point['_endpoint_provenance'] ?? 'legacy')) ?: 'legacy',
            'allow_matches_stop' => !empty($point['allow_matches_stop']) || !empty($point['_allow_matches_stop']) ? 1 : 0,
            'endpoint_address_fingerprint' => sanitize_text_field((string)($point['endpoint_address_fingerprint'] ?? self::addressFingerprint((string)($point['address'] ?? '')))),
            'endpoint_geocode_verified' => !empty($point['endpoint_geocode_verified']) || !empty($point['_endpoint_geocode_verified']) ? 1 : 0,
            'endpoint_geocode_status' => sanitize_key((string)($point['endpoint_geocode_status'] ?? '')),
            'endpoint_resolver_version' => self::VERSION,
            'country_code' => class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::normalizeCountryCode((string)($point['country_code'] ?? 'PT')) : strtoupper((string)($point['country_code'] ?? 'PT')),
            'formatted_address' => sanitize_text_field((string)($point['formatted_address'] ?? $point['address'] ?? '')),
            'place_id' => sanitize_text_field((string)($point['place_id'] ?? '')),
            'geocode_provider' => sanitize_key((string)($point['geocode_provider'] ?? '')),
            'geocode_verified_at' => sanitize_text_field((string)($point['geocode_verified_at'] ?? '')),
            'routing_geography_contract' => self::GEOGRAPHIC_CONTRACT,
        ];
    }

    private static function geocodeAddress(string $address, string $countryCode = 'PT'): ?array {
        static $cache = [];
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::normalizeCountryCode($countryCode) : strtoupper($countryCode ?: 'PT');
        $key = $countryCode . '|' . self::normaliseText($address);
        if ($key === '') return null;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $provider = class_exists('\\RoutesPro\\Services\\MapsFactory') ? MapsFactory::make() : null;
        if (!$provider || !method_exists($provider, 'geocode')) return $cache[$key] = null;
        try {
            $options = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::geocodeOptions($countryCode) : ['country_code'=>$countryCode,'region'=>strtolower($countryCode)];
            $result = $provider->geocode($address, $options);
        } catch (\Throwable $error) {
            return $cache[$key] = null;
        }
        if (is_array($result) && isset($result['lat'], $result['lng']) && is_numeric($result['lat']) && is_numeric($result['lng'])
            && is_finite((float)$result['lat']) && is_finite((float)$result['lng']) && abs((float)$result['lat']) <= 90 && abs((float)$result['lng']) <= 180) {
            if (class_exists('\\RoutesPro\\Services\\RoutingGeographyService') && !RoutingGeographyService::resultMatchesCountry($result, $countryCode)) return $cache[$key] = null;
            return $cache[$key] = [
                'lat' => (float)$result['lat'],
                'lng' => (float)$result['lng'],
                'formatted_address' => sanitize_text_field((string)($result['formatted_address'] ?? $address)),
                'place_id' => sanitize_text_field((string)($result['place_id'] ?? '')),
                'country_code' => $countryCode,
                'provider' => sanitize_key((string)($result['provider'] ?? '')),
            ];
        }
        return $cache[$key] = null;
    }

    private static function addressFingerprint(string $address): string {
        $normalised = self::normaliseText($address);
        return $normalised === '' ? '' : hash('sha256', $normalised);
    }

    private static function normaliseText(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        if (function_exists('remove_accents')) $value = remove_accents($value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?: $value);
    }

    private static function airDistanceOrNull(array $a, array $b): ?float {
        if (!is_numeric($a['lat'] ?? null) || !is_numeric($a['lng'] ?? null) || !is_numeric($b['lat'] ?? null) || !is_numeric($b['lng'] ?? null)) return null;
        return round(self::haversineKm((float)$a['lat'], (float)$a['lng'], (float)$b['lat'], (float)$b['lng']), 3);
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }
}
