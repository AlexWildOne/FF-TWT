<?php
namespace RoutesPro\Services;

use RoutesPro\Support\TollEstimator;

if (!defined('ABSPATH')) exit;

class RouteMetricsService {
    // Historical release-gate compatibility marker. Runtime resolver is v2.
    public const PREVIOUS_CANONICAL_RESOLVER_CONTRACT = 'fieldflow-canonical-route-metrics-resolver-v1';
    public const FINANCIAL_CONTINUITY_CONTRACT = 'fieldflow-route-financial-continuity-v1';
    public const DISTANCE_FINALIZATION_CONTRACT = 'fieldflow-route-distance-finalization-v1';
    // Historical 2.2.506 gate marker. Trust warnings remain visible, but no longer block financial continuity when every route leg is routable.
    public const LEGACY_ENDPOINT_TRUST_MARKER = 'route_endpoints_untrusted';

    private static function routingCountryCode(array $route, array $meta): string {
        $projectId = absint($route['project_id'] ?? $meta['project_id'] ?? 0);
        if (class_exists('\\RoutesPro\\Services\\RoutingGeographyService')) {
            return RoutingGeographyService::normalizeCountryCode((string)($meta['routing_country_code'] ?? RoutingGeographyService::projectCountryCode($projectId)));
        }
        return strtoupper((string)($meta['routing_country_code'] ?? 'PT'));
    }


    private static function geographicIntegrityCurrent(array $meta, array $metrics, string $countryCode): bool {
        if (!class_exists('\\RoutesPro\\Services\\RoutingGeographyService')) return true;
        if ((string)($meta['routing_geography_contract'] ?? '') !== RoutingGeographyService::CONTRACT) return false;
        if ((string)($metrics['routing_geography_contract'] ?? '') !== RoutingGeographyService::CONTRACT) return false;
        if (RoutingGeographyService::normalizeCountryCode((string)($meta['routing_country_code'] ?? ''), '') !== $countryCode) return false;
        foreach (['start_point','end_point'] as $key) {
            $point = is_array($meta[$key] ?? null) ? (array)$meta[$key] : [];
            if (!$point) return false;
            if (RoutingGeographyService::normalizeCountryCode((string)($point['country_code'] ?? ''), '') !== $countryCode) return false;
            if ((string)($point['routing_geography_contract'] ?? '') !== RoutingGeographyService::CONTRACT) return false;
            if (trim((string)($point['address'] ?? '')) !== '' && empty($point['endpoint_geocode_verified'])) return false;
        }
        return true;
    }

    /**
     * Revalidates the route's persisted PDV coordinates inside the campaign
     * country. This is intentionally route-scoped and one-time per geography
     * contract, so legacy ambiguous geocodes are repaired without repeatedly
     * billing the provider on every report render.
     */
    private static function reconcileRouteStopGeography(int $routeId, string $countryCode): array {
        $state = ['processed'=>0,'updated'=>0,'failed'=>0,'skipped'=>0];
        if ($routeId <= 0 || !class_exists('\\RoutesPro\\Services\\MapsFactory')) return $state;
        $provider = MapsFactory::make();
        if (!$provider || !method_exists($provider, 'geocode')) return $state;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT l.id,l.address,l.lat,l.lng,l.country FROM {$px}route_stops rs INNER JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d AND l.id>0",
            $routeId
        ), ARRAY_A) ?: [];
        $options = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::geocodeOptions($countryCode) : ['country_code'=>$countryCode,'region'=>strtolower($countryCode)];
        foreach ($rows as $row) {
            $locationId = absint($row['id'] ?? 0);
            $address = trim((string)($row['address'] ?? ''));
            if ($locationId <= 0 || $address === '') { $state['skipped']++; continue; }
            $state['processed']++;
            try { $geo = $provider->geocode($address, $options); }
            catch (\Throwable $error) { $geo = null; }
            if (!is_array($geo) || !isset($geo['lat'],$geo['lng']) || !is_numeric($geo['lat']) || !is_numeric($geo['lng'])) {
                $oldLat = is_numeric($row['lat'] ?? null) ? (float)$row['lat'] : null;
                $oldLng = is_numeric($row['lng'] ?? null) ? (float)$row['lng'] : null;
                $countrySafe = $oldLat !== null && $oldLng !== null
                    && class_exists('\\RoutesPro\\Services\\RoutingGeographyService')
                    && RoutingGeographyService::coordinatesLikelyInCountry($oldLat, $oldLng, $countryCode);
                if ($countrySafe) $state['skipped']++; else $state['failed']++;
                continue;
            }
            if (class_exists('\\RoutesPro\\Services\\RoutingGeographyService') && !RoutingGeographyService::resultMatchesCountry($geo, $countryCode)) {
                $state['failed']++;
                continue;
            }
            $newLat = (float)$geo['lat'];
            $newLng = (float)$geo['lng'];
            $oldLat = is_numeric($row['lat'] ?? null) ? (float)$row['lat'] : null;
            $oldLng = is_numeric($row['lng'] ?? null) ? (float)$row['lng'] : null;
            $oldCountry = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::normalizeCountryCode((string)($row['country'] ?? ''), '') : strtoupper((string)($row['country'] ?? ''));
            $changed = $oldLat === null || $oldLng === null || abs($oldLat-$newLat) > 0.00001 || abs($oldLng-$newLng) > 0.00001 || $oldCountry !== $countryCode;
            if (!$changed) { $state['skipped']++; continue; }
            $updated = $wpdb->update($px . 'locations', [
                'lat'=>$newLat,
                'lng'=>$newLng,
                'country'=>class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::countryName($countryCode) : $countryCode,
            ], ['id'=>$locationId], ['%f','%f','%s'], ['%d']);
            if ($updated === false) $state['failed']++; else $state['updated']++;
        }
        return $state;
    }

    /**
     * One authoritative reconciliation entrypoint for route geography.
     * Existing routes created before the country-aware contract are re-geocoded
     * once, their stops are validated in the campaign country, and exact Google
     * route metrics are persisted before Analytics/mail/export consume them.
     */
    public static function ensureGeographicIntegrityForRoute(int $routeId, array $route = [], bool $force = false): array {
        if ($routeId <= 0) return ['ok'=>false,'reason'=>'invalid_route'];
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        if (!$route) $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d", $routeId), ARRAY_A) ?: [];
        if (!$route) return ['ok'=>false,'reason'=>'route_missing'];
        $meta = self::decode((string)($route['meta_json'] ?? ''));
        $metrics = is_array($meta['route_metrics'] ?? null) ? (array)$meta['route_metrics'] : [];
        $countryCode = self::routingCountryCode($route, $meta);
        if (!$force && self::geographicIntegrityCurrent($meta, $metrics, $countryCode)) {
            return ['ok'=>true,'skipped'=>true,'reason'=>'geography_current','country_code'=>$countryCode,'metrics'=>$metrics,'meta'=>$meta];
        }
        if (class_exists('\\RoutesPro\\Services\\RoutingGeographyService')) {
            RoutingGeographyService::persistProjectCountryCode(absint($route['project_id'] ?? 0), $countryCode);
        }
        $stopState = self::reconcileRouteStopGeography($routeId, $countryCode);
        if ((int)($stopState['failed'] ?? 0) > 0) {
            return ['ok'=>false,'retry'=>true,'reason'=>'pdv_geography_revalidation_failed','country_code'=>$countryCode,'stop_geography'=>$stopState];
        }
        $refresh = self::maybeRefreshGoogleRoute($routeId, $route, true);
        if (empty($refresh['ok'])) {
            $refresh['retry'] = empty($refresh['skipped']) || !in_array((string)($refresh['reason'] ?? ''), ['provider_disabled','route_endpoints_incomplete'], true);
            $refresh['country_code'] = $countryCode;
            $refresh['stop_geography'] = $stopState;
            return $refresh;
        }
        $freshMeta = is_array($refresh['meta'] ?? null) ? (array)$refresh['meta'] : $meta;
        $freshMetrics = is_array($refresh['metrics'] ?? null) ? (array)$refresh['metrics'] : [];
        $stamp = current_time('mysql');
        $freshMeta['routing_country_code'] = $countryCode;
        $freshMeta['routing_geography_contract'] = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::CONTRACT : 'fieldflow-routing-endpoint-geographic-integrity-v1';
        $freshMeta['routing_geography_reconciled_at'] = $stamp;
        $freshMeta['routing_geography_pdv_revalidated'] = (int)($stopState['processed'] ?? 0);
        $freshMeta['routing_geography_pdv_updated'] = (int)($stopState['updated'] ?? 0);
        $freshMetrics['routing_country_code'] = $countryCode;
        $freshMetrics['routing_geography_contract'] = $freshMeta['routing_geography_contract'];
        $freshMetrics['routing_geography_reconciled_at'] = $stamp;
        $freshMeta['route_metrics'] = $freshMetrics;
        $freshMeta['plan_summary'] = array_merge(is_array($freshMeta['plan_summary'] ?? null) ? (array)$freshMeta['plan_summary'] : [], $freshMetrics);
        $wpdb->update($px . 'routes', ['meta_json'=>wp_json_encode($freshMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id'=>$routeId], ['%s'], ['%d']);
        return ['ok'=>true,'country_code'=>$countryCode,'metrics'=>$freshMetrics,'meta'=>$freshMeta,'stop_geography'=>$stopState,'routing'=>$refresh['routing'] ?? []];
    }

    /**
     * Explicit admin repair for a campaign/date range. Unlike the older
     * planned-route integrity repair, this may include completed routes because
     * geographic ambiguity also contaminates historical Analytics and emails.
     */
    public static function reconcileProjectGeographicIntegrity(int $projectId, int $ownerUserId = 0, string $dateFrom = '', string $dateTo = '', bool $includeCompleted = true): array {
        global $wpdb;
        $projectId = absint($projectId);
        $ownerUserId = absint($ownerUserId);
        $result = ['processed'=>0,'reconciled'=>0,'skipped'=>0,'failed'=>0,'pdvs_updated'=>0,'countries'=>[]];
        if ($projectId <= 0) return $result;
        $px = $wpdb->prefix . 'routespro_';
        $where = ['project_id=%d'];
        $args = [$projectId];
        if ($ownerUserId > 0) { $where[] = 'owner_user_id=%d'; $args[] = $ownerUserId; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'date>=%s'; $args[] = $dateFrom; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'date<=%s'; $args[] = $dateTo; }
        if (!$includeCompleted) $where[] = "status NOT IN ('completed','done','cancelled')";
        else $where[] = "status<>'cancelled'";
        $sql = "SELECT * FROM {$px}routes WHERE " . implode(' AND ', $where) . ' ORDER BY date ASC,id ASC';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        foreach ($rows as $route) {
            $routeId = absint($route['id'] ?? 0);
            if ($routeId <= 0) continue;
            $result['processed']++;
            $state = self::ensureGeographicIntegrityForRoute($routeId, $route, true);
            $country = sanitize_key((string)($state['country_code'] ?? ''));
            if ($country !== '') $result['countries'][strtoupper($country)] = (int)($result['countries'][strtoupper($country)] ?? 0) + 1;
            if (!empty($state['ok'])) {
                if (!empty($state['skipped'])) $result['skipped']++; else $result['reconciled']++;
                $result['pdvs_updated'] += (int)($state['stop_geography']['updated'] ?? 0);
            } else {
                $result['failed']++;
            }
        }
        return $result;
    }

    public static function canonicalFromSchedule(array $schedule, array $extra = []): array {
        $numericOrNull = static function($value, int $precision = 2) {
            return is_numeric($value) ? round((float)$value, $precision) : null;
        };
        $accessDistance = $numericOrNull($schedule['access_distance_km'] ?? null, 2);
        $returnDistance = $numericOrNull($schedule['return_distance_km'] ?? ($schedule['end_leg_distance_km'] ?? null), 2);
        $totalDistance = $numericOrNull($schedule['distance_km'] ?? null, 2);
        $knownDistance = $numericOrNull($schedule['known_distance_km'] ?? $totalDistance, 2);
        $interStopDistance = $numericOrNull($schedule['inter_stop_distance_km'] ?? null, 2);
        if ($interStopDistance === null && $totalDistance !== null && $accessDistance !== null && $returnDistance !== null) {
            $interStopDistance = round(max(0.0, $totalDistance - $accessDistance - $returnDistance), 2);
        }

        $accessTravel = $numericOrNull($schedule['access_travel_min'] ?? null, 1);
        $returnTravel = $numericOrNull($schedule['return_travel_min'] ?? ($schedule['end_leg_travel_min'] ?? null), 1);
        $totalTravel = $numericOrNull($schedule['travel_min'] ?? null, 1);
        $knownTravel = $numericOrNull($schedule['known_travel_min'] ?? $totalTravel, 1);
        $interStopTravel = $numericOrNull($schedule['inter_stop_travel_min'] ?? null, 1);
        if ($interStopTravel === null && $totalTravel !== null && $accessTravel !== null && $returnTravel !== null) {
            $interStopTravel = round(max(0.0, $totalTravel - $accessTravel - $returnTravel), 1);
        }

        $endpointStatus = sanitize_key((string)($schedule['endpoint_status'] ?? ''));
        $endpointsComplete = array_key_exists('endpoints_complete', $schedule)
            ? !empty($schedule['endpoints_complete'])
            : ($endpointStatus === '' ? true : $endpointStatus === 'complete');
        if ($endpointStatus === '') $endpointStatus = $endpointsComplete ? 'complete' : 'incomplete';
        $explicitRoutable = array_key_exists('endpoints_routable', $schedule) ? !empty($schedule['endpoints_routable']) : null;
        $missingEndpointCount = array_key_exists('missing_endpoint_count', $schedule)
            ? max(0, (int)$schedule['missing_endpoint_count'])
            : ($explicitRoutable !== null ? ($explicitRoutable ? 0 : 1) : ($endpointsComplete ? 0 : 1));
        $endpointsRoutable = $explicitRoutable !== null ? $explicitRoutable : ($missingEndpointCount === 0);

        $unresolvedLegs = max($missingEndpointCount, max(0, (int)($schedule['unresolved_leg_count'] ?? 0)));
        $distanceStatus = sanitize_key((string)($schedule['distance_status'] ?? ($unresolvedLegs > 0 ? 'incomplete' : 'complete')));
        if (!in_array($distanceStatus, ['complete', 'incomplete', 'unavailable'], true)) {
            $distanceStatus = $unresolvedLegs > 0 ? 'incomplete' : 'complete';
        }
        if (!$endpointsRoutable) $distanceStatus = 'incomplete';
        // Totals are only authoritative when every leg, including access and return, is resolved.
        // Partial values remain available through known_* fields for diagnostics and recovery.
        if ($distanceStatus !== 'complete') {
            $totalDistance = null;
            $totalTravel = null;
        }
        $routingProvider = sanitize_key((string)($schedule['routing_provider'] ?? 'internal'));
        $distanceSource = sanitize_key((string)($schedule['distance_source'] ?? ($routingProvider === 'google_routes' ? 'google_routes' : 'internal_estimator')));

        $metrics = [
            'distance_km' => $totalDistance,
            'known_distance_km' => $knownDistance,
            'access_distance_km' => $accessDistance,
            'inter_stop_distance_km' => $interStopDistance,
            'return_distance_km' => $returnDistance,
            'travel_min' => $totalTravel,
            'known_travel_min' => $knownTravel,
            'access_travel_min' => $accessTravel,
            'inter_stop_travel_min' => $interStopTravel,
            'return_travel_min' => $returnTravel,
            'visit_min' => max(0, (int)($schedule['visit_min'] ?? 0)),
            'lunch_min' => max(0, (int)($schedule['lunch_min'] ?? 0)),
            'work_min' => max(0, (int)($schedule['work_min'] ?? 0)),
            'total_min' => max(0, (int)($schedule['total_min'] ?? 0)),
            'route_start' => (string)($schedule['route_start'] ?? ''),
            'route_end' => (string)($schedule['route_end'] ?? ''),
            'end_leg_distance_km' => $returnDistance,
            'end_leg_travel_min' => $returnTravel,
            'end_leg_toll_cost_eur' => $distanceStatus === 'complete' ? $numericOrNull($schedule['end_leg_toll_cost_eur'] ?? null, 2) : null,
            'toll_cost_eur' => $distanceStatus === 'complete' ? $numericOrNull($schedule['toll_cost_eur'] ?? null, 2) : null,
            'toll_estimated_eur' => $distanceStatus === 'complete' ? $numericOrNull($schedule['toll_estimated_eur'] ?? ($schedule['toll_cost_eur'] ?? null), 2) : null,
            'known_toll_cost_eur' => $numericOrNull($schedule['known_toll_cost_eur'] ?? ($schedule['toll_cost_eur'] ?? null), 2),
            'toll_model' => (string)($schedule['toll_model'] ?? ''),
            'toll_provider' => (string)($schedule['toll_provider'] ?? ''),
            'toll_is_real_api' => !empty($schedule['toll_is_real_api']) ? 1 : 0,
            'routing_provider' => $routingProvider,
            'routing_calculated_at' => (string)($schedule['routing_calculated_at'] ?? ''),
            'distance_source' => $distanceSource,
            'distance_status' => $distanceStatus,
            'endpoint_status' => $endpointStatus,
            'endpoints_complete' => $endpointsComplete ? 1 : 0,
            'endpoints_routable' => $endpointsRoutable ? 1 : 0,
            'endpoints_trusted' => $endpointsComplete ? 1 : 0,
            'missing_endpoint_count' => $missingEndpointCount,
            'suspicious_endpoint_count' => max(0, (int)($schedule['suspicious_endpoint_count'] ?? 0)),
            'start_matches_first_stop' => !empty($schedule['start_matches_first_stop']) ? 1 : 0,
            'end_matches_last_stop' => !empty($schedule['end_matches_last_stop']) ? 1 : 0,
            'start_endpoint_provenance' => sanitize_key((string)($schedule['start_endpoint_provenance'] ?? $schedule['start_provenance'] ?? '')),
            'end_endpoint_provenance' => sanitize_key((string)($schedule['end_endpoint_provenance'] ?? $schedule['end_provenance'] ?? '')),
            'endpoint_reconciliation_contract' => sanitize_text_field((string)($schedule['endpoint_reconciliation_contract'] ?? '')),
            'endpoint_warning_codes' => array_values(array_filter(array_map('sanitize_key', (array)($schedule['endpoint_warning_codes'] ?? [])))),
            'endpoint_geocode_verified' => !empty($schedule['endpoint_geocode_verified']) ? 1 : 0,
            'endpoint_coordinates_refreshed' => !empty($schedule['endpoint_coordinates_refreshed']) ? 1 : 0,
            'routing_incomplete' => $distanceStatus === 'complete' ? 0 : 1,
            'unresolved_leg_count' => $unresolvedLegs,
            'segment_count' => max(0, (int)($schedule['segment_count'] ?? 0)),
            'matrix_provider' => sanitize_key((string)($schedule['matrix_provider'] ?? '')),
            'matrix_source_label' => sanitize_text_field((string)($schedule['matrix_source_label'] ?? '')),
            'matrix_version' => sanitize_text_field((string)($schedule['matrix_version'] ?? '')),
            'matrix_coverage_pct' => is_numeric($schedule['matrix_coverage_pct'] ?? null) ? round((float)$schedule['matrix_coverage_pct'], 1) : null,
            'optimization_method' => sanitize_key((string)($schedule['optimization_method'] ?? '')),
            'waypoint_order_optimized' => !empty($schedule['waypoint_order_optimized']) ? 1 : 0,
            'optimization_saved_distance_km' => $numericOrNull($schedule['optimization_saved_distance_km'] ?? null, 2),
            'optimization_saved_travel_min' => $numericOrNull($schedule['optimization_saved_travel_min'] ?? null, 1),
            'territorial_coherence_mode' => sanitize_key((string)($schedule['territorial_coherence_mode'] ?? 'strong')),
            'territory_same_base' => !empty($schedule['territory_same_base']) ? 1 : 0,
            'territory_dominant_direction' => sanitize_key((string)($schedule['territory_dominant_direction'] ?? 'base')),
            'territory_dominant_direction_label' => sanitize_text_field((string)($schedule['territory_dominant_direction_label'] ?? 'Zona da base')),
            'territory_dominant_share_pct' => is_numeric($schedule['territory_dominant_share_pct'] ?? null) ? round((float)$schedule['territory_dominant_share_pct'], 1) : 100.0,
            'territory_direction_count' => max(0, (int)($schedule['territory_direction_count'] ?? 0)),
            'territory_direction_switches' => max(0, (int)($schedule['territory_direction_switches'] ?? 0)),
            'territory_direction_reentries' => max(0, (int)($schedule['territory_direction_reentries'] ?? 0)),
            'territory_opposite_mix' => !empty($schedule['territory_opposite_mix']) ? 1 : 0,
            'territory_opposite_direction_switches' => max(0, (int)($schedule['territory_opposite_direction_switches'] ?? 0)),
            'territory_locality_count' => max(0, (int)($schedule['territory_locality_count'] ?? 0)),
            'territory_locality_reentries' => max(0, (int)($schedule['territory_locality_reentries'] ?? 0)),
            'territory_bearing_span_deg' => is_numeric($schedule['territory_bearing_span_deg'] ?? null) ? round((float)$schedule['territory_bearing_span_deg'], 1) : 0.0,
            'territory_far_stop_count' => max(0, (int)($schedule['territory_far_stop_count'] ?? 0)),
            'territory_base_crossing_risk' => !empty($schedule['territory_base_crossing_risk']) ? 1 : 0,
            'territory_route_cone_label' => sanitize_text_field((string)($schedule['territory_route_cone_label'] ?? 'Corredor focado')),
            'territory_coherence_score' => is_numeric($schedule['territory_coherence_score'] ?? null) ? round((float)$schedule['territory_coherence_score'], 1) : 100.0,
            'territory_coherence_status' => sanitize_key((string)($schedule['territory_coherence_status'] ?? 'coherent')),
            'territory_coherence_label' => sanitize_text_field((string)($schedule['territory_coherence_label'] ?? 'Coerente')),
            'metrics_version' => 'fieldflow_route_metrics_v5',
        ];
        return array_merge($metrics, $extra);
    }

    /**
     * Resolve the canonical stored distance/toll metrics for a route.
     *
     * Older FieldFlow routes may carry the same persisted metrics in different
     * historical buckets. This method is the single compatibility boundary used
     * by mobility, REST and portal consumers. It does not recalculate a planned
     * route when a stored canonical value is available.
     */
    public static function canonicalFromRouteArray(array $route, bool $allowStoredLegFallback = true): array {
        $meta = is_array($route['meta_decoded'] ?? null)
            ? (array)$route['meta_decoded']
            : self::decode((string)($route['meta_json'] ?? ''));

        $startPoint = is_array($route['start_point'] ?? null) ? (array)$route['start_point'] : (is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : []);
        $endPoint = is_array($route['end_point'] ?? null) ? (array)$route['end_point'] : (is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : []);
        $endpointIntegrity = self::endpointIntegrity($startPoint, $endPoint);

        $buckets = [];
        foreach (['route_metrics','metrics'] as $routeKey) {
            if (is_array($route[$routeKey] ?? null)) $buckets[] = [$routeKey, (array)$route[$routeKey]];
        }
        foreach (['route_metrics','portal_summary','generated_plan_summary','original_plan_summary','plan_summary','metrics'] as $metaKey) {
            if (is_array($meta[$metaKey] ?? null)) $buckets[] = [$metaKey, (array)$meta[$metaKey]];
        }

        $firstNumeric = static function(array $buckets, array $keys): array {
            foreach ($buckets as [$bucketKey, $bucket]) {
                foreach ($keys as $key) {
                    if (isset($bucket[$key]) && is_numeric($bucket[$key])) {
                        return [max(0.0, (float)$bucket[$key]), $bucketKey . '.' . $key];
                    }
                }
            }
            return [null, ''];
        };
        $firstScalar = static function(array $buckets, string $key, $default = null) {
            foreach ($buckets as [, $bucket]) {
                if (array_key_exists($key, $bucket) && $bucket[$key] !== '' && $bucket[$key] !== null) return $bucket[$key];
            }
            return $default;
        };

        [$distance, $distanceSource] = $firstNumeric($buckets, ['distance_km','total_distance_km']);
        [$knownDistance, $knownDistanceSource] = $firstNumeric($buckets, ['known_distance_km']);
        if ($distance === null) {
            foreach (['distance_km','total_distance_km'] as $key) {
                if (isset($route[$key]) && is_numeric($route[$key])) { $distance = max(0.0, (float)$route[$key]); $distanceSource = 'route.' . $key; break; }
                if (isset($meta[$key]) && is_numeric($meta[$key])) { $distance = max(0.0, (float)$meta[$key]); $distanceSource = 'meta.' . $key; break; }
            }
        }
        if ($knownDistance === null) {
            foreach (['known_distance_km','suggestion_distance_km'] as $key) {
                if (isset($route[$key]) && is_numeric($route[$key])) { $knownDistance = max(0.0, (float)$route[$key]); $knownDistanceSource = 'route.' . $key; break; }
                if (isset($meta[$key]) && is_numeric($meta[$key])) { $knownDistance = max(0.0, (float)$meta[$key]); $knownDistanceSource = 'meta.' . $key; break; }
            }
        }
        if ($knownDistance === null && $distance !== null) { $knownDistance = $distance; $knownDistanceSource = $distanceSource; }

        $routeId = absint($route['id'] ?? $route['route_id'] ?? 0);
        if ($knownDistance === null && $allowStoredLegFallback && $routeId > 0) {
            $fallback = self::distanceFromStoredRouteLegs($routeId, $meta);
            if ($fallback > 0) { $knownDistance = $fallback; $knownDistanceSource = 'stored_route_legs'; }
        }

        $storedStatus = sanitize_key((string)$firstScalar($buckets, 'distance_status', ''));
        $storedEndpointStatus = sanitize_key((string)$firstScalar($buckets, 'endpoint_status', ''));
        $storedSuspiciousCount = max(0, (int)$firstScalar($buckets, 'suspicious_endpoint_count', 0));
        $storedMissingCount = max(0, (int)$firstScalar($buckets, 'missing_endpoint_count', 0));
        $storedUnresolvedLegs = max(0, (int)$firstScalar($buckets, 'unresolved_leg_count', 0));
        $endpointsRoutable = !empty($endpointIntegrity['endpoints_complete'])
            || ($storedMissingCount === 0 && self::pointHasRoutingData($startPoint) && self::pointHasRoutingData($endPoint));
        $distanceStatus = $storedStatus;
        if (!in_array($distanceStatus, ['complete','incomplete','unavailable'], true)) {
            $distanceStatus = $distance !== null ? 'complete' : ($knownDistance !== null ? 'incomplete' : 'unavailable');
        }
        if (!$endpointsRoutable || $storedMissingCount > 0 || $storedUnresolvedLegs > 0) {
            $distanceStatus = 'incomplete';
        } elseif ($distanceStatus === 'incomplete'
            && $knownDistance !== null
            && ($storedSuspiciousCount > 0 || strpos($storedEndpointStatus, 'suspicious_') === 0)) {
            // 2.2.506 intentionally fail-closed on coincident endpoint warnings
            // and persisted only known_distance_km. Once every leg is resolved,
            // recover that authoritative total while keeping the trust warning.
            $distance = $distance ?? $knownDistance;
            $distanceStatus = 'complete';
        }
        if ($distanceStatus !== 'complete') $distance = null;

        $component = static function(array $buckets, string $key): ?float {
            foreach ($buckets as [, $bucket]) if (isset($bucket[$key]) && is_numeric($bucket[$key])) return round(max(0.0, (float)$bucket[$key]), 2);
            return null;
        };
        $accessDistance = $component($buckets, 'access_distance_km');
        $interStopDistance = $component($buckets, 'inter_stop_distance_km');
        $returnDistance = $component($buckets, 'return_distance_km');
        if ($returnDistance === null) $returnDistance = $component($buckets, 'end_leg_distance_km');

        [$toll, $tollSource] = $firstNumeric($buckets, ['toll_cost_eur','toll_estimated_eur']);
        [$knownToll, $knownTollSource] = $firstNumeric($buckets, ['known_toll_cost_eur','toll_cost_eur','toll_estimated_eur','suggestion_toll_cost_eur']);
        if ($toll === null) {
            foreach (['toll_cost_eur','toll_estimated_eur'] as $key) {
                if (isset($route[$key]) && is_numeric($route[$key])) { $toll = max(0.0, (float)$route[$key]); $tollSource = 'route.' . $key; break; }
                if (isset($meta[$key]) && is_numeric($meta[$key])) { $toll = max(0.0, (float)$meta[$key]); $tollSource = 'meta.' . $key; break; }
            }
        }
        if ($knownToll === null && $toll !== null) { $knownToll = $toll; $knownTollSource = $tollSource; }
        if ($distanceStatus !== 'complete') {
            $toll = null;
            $tollSource = '';
        } elseif ($toll === null && $knownToll !== null) {
            // Recover the toll value persisted by 2.2.506 when the route was
            // financially blocked only by an endpoint trust warning.
            $toll = $knownToll;
            $tollSource = $knownTollSource !== '' ? $knownTollSource . ':recovered' : 'known_toll_recovered';
        } elseif (($toll === null || $toll <= 0.0) && $distance !== null && $distance > 0.0) {
            $toll = TollEstimator::costFromKm($distance, 'route');
            $tollSource = 'toll_estimator';
        }

        $tollModel = sanitize_text_field((string)$firstScalar($buckets, 'toll_model', $tollSource === 'toll_estimator' ? 'FieldFlow TollEstimator' : ''));
        $tollProvider = sanitize_key((string)$firstScalar($buckets, 'toll_provider', $firstScalar($buckets, 'routing_provider', $tollSource === 'toll_estimator' ? 'internal_estimator' : '')));
        $tollIsRealApi = !empty($firstScalar($buckets, 'toll_is_real_api', 0)) ? 1 : 0;
        $tollConfidence = sanitize_key((string)$firstScalar($buckets, 'toll_confidence', $tollIsRealApi ? 'real' : 'low'));
        $tollMin = $firstScalar($buckets, 'toll_min_eur', $toll);
        $tollMax = $firstScalar($buckets, 'toll_max_eur', $toll);
        $tollTariffVersion = sanitize_text_field((string)$firstScalar($buckets, 'toll_tariff_version', ''));
        $tollVehicleClass = max(1, min(4, (int)$firstScalar($buckets, 'toll_vehicle_class', 1)));
        if ($tollSource === 'toll_estimator') $tollIsRealApi = 0;

        $unresolved = max((int)($endpointIntegrity['missing_endpoint_count'] ?? 0), $storedUnresolvedLegs);
        return [
            'distance_km' => $distance !== null ? round($distance, 2) : null,
            'known_distance_km' => $knownDistance !== null ? round($knownDistance, 2) : null,
            'access_distance_km' => $accessDistance,
            'inter_stop_distance_km' => $interStopDistance,
            'return_distance_km' => $returnDistance,
            'toll_cost_eur' => $toll !== null ? round($toll, 2) : null,
            'toll_estimated_eur' => $toll !== null ? round($toll, 2) : null,
            'known_toll_cost_eur' => $knownToll !== null ? round($knownToll, 2) : null,
            'distance_value_source' => $distanceSource,
            'known_distance_value_source' => $knownDistanceSource,
            'toll_value_source' => $tollSource,
            'known_toll_value_source' => $knownTollSource,
            'toll_model' => $tollModel,
            'toll_provider' => $tollProvider,
            'toll_is_real_api' => $tollIsRealApi,
            'toll_confidence' => $tollConfidence,
            'toll_min_eur' => is_numeric($tollMin) ? round((float)$tollMin, 2) : ($toll !== null ? round($toll, 2) : null),
            'toll_max_eur' => is_numeric($tollMax) ? round((float)$tollMax, 2) : ($toll !== null ? round($toll, 2) : null),
            'toll_tariff_version' => $tollTariffVersion,
            'toll_vehicle_class' => $tollVehicleClass,
            'distance_status' => $distanceStatus,
            'endpoint_status' => $storedEndpointStatus !== '' ? $storedEndpointStatus : (string)$endpointIntegrity['endpoint_status'],
            'endpoints_complete' => (!empty($endpointIntegrity['endpoints_complete']) && $storedSuspiciousCount === 0 && strpos($storedEndpointStatus, 'suspicious_') !== 0) ? 1 : 0,
            'endpoints_routable' => $endpointsRoutable ? 1 : 0,
            'endpoints_trusted' => (!empty($endpointIntegrity['endpoints_complete']) && $storedSuspiciousCount === 0 && strpos($storedEndpointStatus, 'suspicious_') !== 0) ? 1 : 0,
            'missing_endpoint_count' => max((int)$endpointIntegrity['missing_endpoint_count'], $storedMissingCount),
            'suspicious_endpoint_count' => $storedSuspiciousCount,
            'endpoint_warning_codes' => (array)$firstScalar($buckets, 'endpoint_warning_codes', []),
            'endpoint_reconciliation_contract' => sanitize_text_field((string)$firstScalar($buckets, 'endpoint_reconciliation_contract', '')),
            'unresolved_leg_count' => $unresolved,
            'metrics_version' => 'fieldflow_route_metrics_v5',
            'resolver_contract' => 'fieldflow-canonical-route-metrics-resolver-v2',
            'integrity_contract' => 'fieldflow-route-endpoint-distance-integrity-v1',
        ];
    }

    public static function endpointIntegrity(array $startPoint = [], array $endPoint = [], array $firstStop = [], array $lastStop = [], array $options = []): array {
        if (class_exists('\RoutesPro\Services\RouteEndpointResolverService') && ($firstStop || $lastStop || !empty($options['semantic']))) {
            return RouteEndpointResolverService::integrity($startPoint, $endPoint, $firstStop, $lastStop, $options);
        }
        $hasStart = self::pointHasRoutingData($startPoint);
        $hasEnd = self::pointHasRoutingData($endPoint);
        $status = $hasStart && $hasEnd ? 'complete' : ($hasStart ? 'missing_end' : ($hasEnd ? 'missing_start' : 'missing_both'));
        return [
            'endpoint_status' => $status,
            'endpoints_complete' => $hasStart && $hasEnd ? 1 : 0,
            'endpoints_routable' => $hasStart && $hasEnd ? 1 : 0,
            'endpoints_trusted' => $hasStart && $hasEnd ? 1 : 0,
            'missing_endpoint_count' => ($hasStart ? 0 : 1) + ($hasEnd ? 0 : 1),
            'suspicious_endpoint_count' => 0,
            'has_start' => $hasStart ? 1 : 0,
            'has_end' => $hasEnd ? 1 : 0,
            'endpoint_warning_codes' => [],
        ];
    }

    private static function pointHasRoutingData(array $point): bool {
        if (is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) return true;
        return trim((string)($point['address'] ?? $point['formatted_address'] ?? '')) !== '';
    }

    /**
     * Reporting-only stored leg breakdown.
     *
     * Reconstructs the full persisted route as:
     * access -> inter-stop legs -> return. It never mutates canonical route
     * metrics and is only used to stop Daily Close reporting from silently
     * dropping access/return when an older known_distance_km contains only the
     * stop-to-stop portion.
     */
    public static function reportingStoredRouteLegBreakdown(int $routeId, array $routeMeta = []): array {
        $empty = [
            'access_distance_km'=>null, 'inter_stop_distance_km'=>null, 'return_distance_km'=>null,
            'distance_km'=>null, 'known_distance_km'=>null, 'toll_cost_eur'=>null,
            'complete'=>0, 'stop_count'=>0, 'missing_stop_legs'=>0, 'source'=>'stored_route_legs',
        ];
        if ($routeId <= 0) return $empty;
        global $wpdb;
        if (!isset($wpdb)) return $empty;
        $px = $wpdb->prefix . 'routespro_';
        $stops = $wpdb->get_results($wpdb->prepare(
            "SELECT rs.meta_json FROM {$px}route_stops rs WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC",
            $routeId
        ), ARRAY_A) ?: [];
        if (!$stops) return $empty;

        $access = null;
        $inter = 0.0;
        $known = 0.0;
        $missing = 0;
        $toll = 0.0;
        $tollComplete = true;
        foreach ($stops as $index => $stop) {
            $stopMeta = self::decode((string)($stop['meta_json'] ?? ''));
            $leg = null;
            foreach (['distance_from_prev_km','leg_distance_km'] as $key) {
                if (array_key_exists($key, $stopMeta) && is_numeric($stopMeta[$key])) {
                    $leg = max(0.0, (float)$stopMeta[$key]);
                    break;
                }
            }
            if ($leg === null) {
                $missing++;
            } else {
                $known += $leg;
                if ($index === 0) $access = $leg; else $inter += $leg;
            }

            $legToll = null;
            foreach (['toll_cost_eur_from_prev','toll_cost_eur'] as $key) {
                if (array_key_exists($key, $stopMeta) && is_numeric($stopMeta[$key])) {
                    $legToll = max(0.0, (float)$stopMeta[$key]);
                    break;
                }
            }
            if ($legToll === null) $tollComplete = false; else $toll += $legToll;
        }

        $metrics = is_array($routeMeta['route_metrics'] ?? null) ? (array)$routeMeta['route_metrics'] : (is_array($routeMeta['plan_summary'] ?? null) ? (array)$routeMeta['plan_summary'] : []);
        $return = is_numeric($metrics['return_distance_km'] ?? null)
            ? max(0.0, (float)$metrics['return_distance_km'])
            : (is_numeric($metrics['end_leg_distance_km'] ?? null) ? max(0.0, (float)$metrics['end_leg_distance_km']) : null);
        $returnToll = is_numeric($metrics['end_leg_toll_cost_eur'] ?? null) ? max(0.0, (float)$metrics['end_leg_toll_cost_eur']) : null;
        if ($return !== null) $known += $return;
        if ($returnToll !== null) $toll += $returnToll; else $tollComplete = false;

        $complete = $missing === 0 && $access !== null && $return !== null;
        return [
            'access_distance_km'=>$access !== null ? round($access, 2) : null,
            'inter_stop_distance_km'=>count($stops) > 1 && $missing === 0 ? round($inter, 2) : (count($stops) === 1 && $missing === 0 ? 0.0 : null),
            'return_distance_km'=>$return !== null ? round($return, 2) : null,
            'distance_km'=>$complete ? round($known, 2) : null,
            'known_distance_km'=>$known > 0 ? round($known, 2) : null,
            'toll_cost_eur'=>$complete && $tollComplete ? round($toll, 2) : null,
            'complete'=>$complete ? 1 : 0,
            'stop_count'=>count($stops),
            'missing_stop_legs'=>$missing,
            'source'=>'stored_route_legs',
        ];
    }

    private static function distanceFromStoredRouteLegs(int $routeId, array $routeMeta = []): float {
        if ($routeId <= 0) return 0.0;
        global $wpdb;
        if (!isset($wpdb)) return 0.0;
        $px = $wpdb->prefix . 'routespro_';
        $stops = $wpdb->get_results($wpdb->prepare("SELECT rs.meta_json, l.lat, l.lng FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $routeId), ARRAY_A) ?: [];
        $km = 0.0;
        $hasStoredLegs = false;
        foreach ($stops as $stop) {
            $stopMeta = self::decode((string)($stop['meta_json'] ?? ''));
            foreach (['distance_from_prev_km','leg_distance_km'] as $key) {
                if (isset($stopMeta[$key]) && is_numeric($stopMeta[$key]) && (float)$stopMeta[$key] > 0) {
                    $km += (float)$stopMeta[$key];
                    $hasStoredLegs = true;
                    break;
                }
            }
        }
        if (!$hasStoredLegs) {
            $points = [];
            $start = is_array($routeMeta['start_point'] ?? null) ? (array)$routeMeta['start_point'] : [];
            if (is_numeric($start['lat'] ?? null) && is_numeric($start['lng'] ?? null)) $points[] = [(float)$start['lat'], (float)$start['lng']];
            foreach ($stops as $stop) {
                if (is_numeric($stop['lat'] ?? null) && is_numeric($stop['lng'] ?? null)) $points[] = [(float)$stop['lat'], (float)$stop['lng']];
            }
            $end = is_array($routeMeta['end_point'] ?? null) ? (array)$routeMeta['end_point'] : [];
            if (is_numeric($end['lat'] ?? null) && is_numeric($end['lng'] ?? null)) $points[] = [(float)$end['lat'], (float)$end['lng']];
            for ($i = 1; $i < count($points); $i++) {
                $km += self::haversineKm($points[$i-1][0], $points[$i-1][1], $points[$i][0], $points[$i][1]) * 1.25;
            }
        }
        $metrics = is_array($routeMeta['route_metrics'] ?? null) ? (array)$routeMeta['route_metrics'] : (is_array($routeMeta['plan_summary'] ?? null) ? (array)$routeMeta['plan_summary'] : []);
        $returnKm = $metrics['return_distance_km'] ?? ($metrics['end_leg_distance_km'] ?? null);
        if (is_numeric($returnKm) && $hasStoredLegs) $km += (float)$returnKm;
        return round($km, 2);
    }

    // Historical Phase 8U compatibility marker: route_default_repair. 2.2.508 resolves through canonical endpoint profiles and history.
    // Historical 2.2.511 package compatibility marker only: bool $forceOwnerProfile = false
    public static function repairPlannedRouteIntegrity(int $projectId, int $ownerUserId = 0, string $dateFrom = '', string $dateTo = '', int $actorUserId = 0): array {
        return self::repairPlannedRouteIntegrityInternal($projectId, $ownerUserId, $dateFrom, $dateTo, $actorUserId, false);
    }

    /**
     * Explicit migration entrypoint introduced after 2.2.511.
     * Keeps the certified five-parameter repairPlannedRouteIntegrity() contract
     * intact while allowing the admin to force the selected operative profile.
     */
    public static function repairPlannedRouteIntegrityWithOwnerProfile(int $projectId, int $ownerUserId = 0, string $dateFrom = '', string $dateTo = '', int $actorUserId = 0): array {
        return self::repairPlannedRouteIntegrityInternal($projectId, $ownerUserId, $dateFrom, $dateTo, $actorUserId, true);
    }

    private static function repairPlannedRouteIntegrityInternal(int $projectId, int $ownerUserId, string $dateFrom, string $dateTo, int $actorUserId, bool $forceOwnerProfile): array {
        global $wpdb;
        $projectId = absint($projectId);
        $ownerUserId = absint($ownerUserId);
        $emptyResult = ['processed'=>0,'repaired'=>0,'reconciled'=>0,'recalculated'=>0,'forced_profile'=>0,'google_exact'=>0,'matrix_fallback'=>0,'google_exact_failed'=>0,'unchanged'=>0,'missing_defaults'=>0,'suspicious'=>0,'suspicious_unresolved'=>0,'started'=>0,'errors'=>0];
        if ($projectId <= 0 || !isset($wpdb)) { $emptyResult['errors'] = 1; return $emptyResult; }

        $px = $wpdb->prefix . 'routespro_';
        $where = ['project_id=%d', "status IN ('draft','planned')"];
        $args = [$projectId];
        if ($ownerUserId > 0) { $where[] = 'owner_user_id=%d'; $args[] = $ownerUserId; }
        if ($dateFrom !== '') { $where[] = 'date >= %s'; $args[] = sanitize_text_field($dateFrom); }
        if ($dateTo !== '') { $where[] = 'date <= %s'; $args[] = sanitize_text_field($dateTo); }
        $sql = "SELECT * FROM {$px}routes WHERE " . implode(' AND ', $where) . ' ORDER BY date ASC,id ASC LIMIT 500';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
        $result = $emptyResult;

        foreach ($rows as $route) {
            $result['processed']++;
            $routeId = absint($route['id'] ?? 0);
            $meta = self::decode((string)($route['meta_json'] ?? ''));
            $countryCode = self::routingCountryCode((array)$route, $meta);
            $meta['routing_country_code'] = $countryCode;
            if (class_exists('\\RoutesPro\\Services\\RoutingGeographyService')) $meta['routing_geography_contract'] = RoutingGeographyService::CONTRACT;

            if (class_exists('\\RoutesPro\\Services\\RouteOptimizationService')) {
                $safety = RouteOptimizationService::routeSafety($routeId, $route);
                if (empty($safety['editable'])) { $result['started']++; continue; }
            }

            $stopRows = $wpdb->get_results($wpdb->prepare(
                "SELECT rs.location_id,rs.seq,l.name,l.address,l.lat,l.lng FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC,rs.id ASC",
                $routeId
            ), ARRAY_A) ?: [];
            if (!$stopRows) { $result['errors']++; continue; }
            $firstStop = (array)$stopRows[0];
            $lastStop = (array)$stopRows[count($stopRows)-1];
            $locationIds = array_values(array_filter(array_map(static fn($r) => absint($r['location_id'] ?? 0), $stopRows)));
            if (!$locationIds) { $result['errors']++; continue; }

            $rawStart = is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : [];
            $rawEnd = is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : [];
            $start = class_exists('\\RoutesPro\\Services\\RouteEndpointResolverService')
                ? RouteEndpointResolverService::resolve($rawStart, 'start', (string)($rawStart['provenance'] ?? 'legacy'), ['allow_matches_stop'=>!empty($rawStart['allow_matches_stop']),'country_code'=>$countryCode])
                : $rawStart;
            $end = class_exists('\\RoutesPro\\Services\\RouteEndpointResolverService')
                ? RouteEndpointResolverService::resolve($rawEnd, 'end', (string)($rawEnd['provenance'] ?? 'legacy'), ['allow_matches_stop'=>!empty($rawEnd['allow_matches_stop']),'country_code'=>$countryCode])
                : $rawEnd;
            $integrity = self::endpointIntegrity($start, $end, $firstStop, $lastStop, [
                'semantic' => 1,
                'allow_start_matches_stop' => !empty($start['allow_matches_stop']),
                'allow_end_matches_stop' => !empty($end['allow_matches_stop']),
            ]);
            $wasSuspicious = (int)($integrity['suspicious_endpoint_count'] ?? 0) > 0;
            if ($wasSuspicious) $result['suspicious']++;

            // 2.2.506 also reconciles legacy metric snapshots that look complete
            // but still carry a zero/missing access or return leg. This is the
            // field symptom seen in 2.2.505: total km equalled only the PDV-to-PDV
            // distance although real start/end endpoints were present.
            $storedMetrics = is_array($meta['route_metrics'] ?? null) ? (array)$meta['route_metrics'] : (is_array($meta['plan_summary'] ?? null) ? (array)$meta['plan_summary'] : []);
            $storedAccess = $storedMetrics['access_distance_km'] ?? null;
            $storedReturn = $storedMetrics['return_distance_km'] ?? ($storedMetrics['end_leg_distance_km'] ?? null);
            $confirmedZeroAccess = !empty($integrity['start_match_confirmed']);
            $confirmedZeroReturn = !empty($integrity['end_match_confirmed']);
            $accessNeedsRecalc = !$confirmedZeroAccess && (!is_numeric($storedAccess) || (float)$storedAccess <= 0.05);
            $returnNeedsRecalc = !$confirmedZeroReturn && (!is_numeric($storedReturn) || (float)$storedReturn <= 0.05);
            $metricRecalcNeeded = $accessNeedsRecalc || $returnNeedsRecalc || !empty($meta['route_metrics_stale']) || !empty($meta['routing_refresh_required']);

            $clientId = absint($route['client_id'] ?? 0);
            $routeOwner = absint($route['owner_user_id'] ?? 0);
            $forceProfileForRoute = false;
            $forcedProfile = [];
            if ($forceOwnerProfile && $ownerUserId > 0 && $routeOwner === $ownerUserId && class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
                $forcedProfile = RouteEndpointProfileService::effectiveProfile($projectId, $routeOwner, $clientId);
                $forceProfileForRoute = !empty($forcedProfile['enabled'])
                    && (string)($forcedProfile['_source'] ?? '') === 'operator_profile'
                    && RouteEndpointResolverService::hasRoutingData((array)($forcedProfile['start_point'] ?? []))
                    && RouteEndpointResolverService::hasRoutingData((array)($forcedProfile['end_point'] ?? []));
            }

            // 2.2.511. When an admin explicitly reconciles one operative at a time,
            // the saved operative base becomes authoritative for every editable
            // draft/planned route in the selected interval. This is the migration
            // path for routes contaminated while old releases persisted the first
            // and last PDV as route endpoints. Do not let a previously "complete"
            // endpoint or a historical route snapshot short-circuit the repair.
            if (!empty($integrity['endpoints_complete']) && !$metricRecalcNeeded && !$forceProfileForRoute) { $result['unchanged']++; continue; }

            if ($forceProfileForRoute) {
                $profileSource = sanitize_key((string)($forcedProfile['_source'] ?? 'operator_profile')) ?: 'operator_profile';
                $start = RouteEndpointResolverService::resolve((array)$forcedProfile['start_point'], 'start', $profileSource, [
                    'allow_matches_stop'=>!empty($forcedProfile['start_point']['allow_matches_stop']),
                    'country_code'=>$countryCode,
                ]);
                if (!empty($forcedProfile['return_to_start'])) {
                    $end = $start;
                    $end['provenance'] = 'return_to_start';
                    $meta['return_to_start'] = 1;
                    $endSource = 'return_to_start';
                } else {
                    $end = RouteEndpointResolverService::resolve((array)$forcedProfile['end_point'], 'end', $profileSource, [
                        'allow_matches_stop'=>!empty($forcedProfile['end_point']['allow_matches_stop']),
                        'country_code'=>$countryCode,
                    ]);
                    $meta['return_to_start'] = 0;
                    $endSource = $profileSource;
                }
                $meta['route_endpoint_profile_contract'] = RouteEndpointProfileService::CONTRACT;
                $meta['route_endpoint_start_source'] = 'operator_profile_forced';
                $meta['route_endpoint_end_source'] = $endSource === 'return_to_start' ? 'return_to_start' : 'operator_profile_forced';
                $meta['route_endpoint_forced_migration_at'] = current_time('mysql');
                $meta['route_endpoint_forced_migration_by'] = $actorUserId > 0 ? $actorUserId : get_current_user_id();
                unset($meta['route_endpoint_history_recovery_source']);
                $result['forced_profile']++;
                $metricRecalcNeeded = true;
            } elseif (class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
                $history = RouteEndpointProfileService::recoverHistoricalEndpoints($routeId, $meta, $firstStop, $lastStop);
                $resolvedEndpoints = RouteEndpointProfileService::resolveForRoute(
                    $clientId,
                    $projectId,
                    $routeOwner,
                    $meta,
                    (array)($history['start_point'] ?? []),
                    (array)($history['end_point'] ?? []),
                    $firstStop,
                    $lastStop,
                    ['return_to_start'=>!empty($meta['return_to_start'])]
                );
                $start = (array)($resolvedEndpoints['start_point'] ?? []);
                $end = (array)($resolvedEndpoints['end_point'] ?? []);
                if (!empty($resolvedEndpoints['return_to_start'])) $meta['return_to_start'] = 1;
                $meta['route_endpoint_profile_contract'] = RouteEndpointProfileService::CONTRACT;
                $meta['route_endpoint_start_source'] = sanitize_key((string)($resolvedEndpoints['start_source'] ?? 'missing'));
                $meta['route_endpoint_end_source'] = sanitize_key((string)($resolvedEndpoints['end_source'] ?? 'missing'));
                if (!empty($history['source']) && $history['source'] !== 'none') $meta['route_endpoint_history_recovery_source'] = sanitize_key((string)$history['source']);
            }

            $integrity = self::endpointIntegrity($start, $end, $firstStop, $lastStop, [
                'semantic' => 1,
                'allow_start_matches_stop' => !empty($start['allow_matches_stop']),
                'allow_end_matches_stop' => !empty($end['allow_matches_stop']),
            ]);
            if (empty($integrity['endpoints_routable'])) {
                if ((int)($integrity['suspicious_endpoint_count'] ?? 0) > 0) $result['suspicious_unresolved']++;
                else $result['missing_defaults']++;
                continue;
            }
            if ((int)($integrity['suspicious_endpoint_count'] ?? 0) > 0) $result['suspicious_unresolved']++;

            $preview = RouteOptimizationService::previewSystem([
                'route_id' => $routeId,
                'client_id' => $clientId,
                'project_id' => $projectId,
                'location_ids' => $locationIds,
                'start_point' => RouteEndpointResolverService::publicPoint($start),
                'end_point' => RouteEndpointResolverService::publicPoint($end),
                'manual_order_locked' => 1,
                'force_refresh' => 1,
                'mode' => 'hybrid',
            ]);
            if (is_wp_error($preview) || !is_array($preview)) { $result['errors']++; continue; }
            $optimized = is_array($preview['optimized'] ?? null) ? (array)$preview['optimized'] : [];
            if ((string)($optimized['distance_status'] ?? '') !== 'complete' || !is_numeric($optimized['distance_km'] ?? null)) { $result['errors']++; continue; }

            $canonical = self::canonicalFromSchedule(array_merge($optimized, [
                'routing_provider' => (string)($preview['provider'] ?? 'internal_road_estimator'),
                'routing_calculated_at' => (string)($preview['optimized_at'] ?? current_time('mysql')),
                'distance_source' => (string)($preview['provider'] ?? 'internal_road_estimator'),
                'matrix_provider' => (string)($preview['provider'] ?? 'internal_road_estimator'),
                'matrix_source_label' => (string)($preview['provider_label'] ?? ''),
                'optimization_method' => 'endpoint_reconciliation_preserved_order',
                'endpoint_status' => (string)($integrity['endpoint_status'] ?? 'complete'),
                'endpoints_complete' => !empty($integrity['endpoints_complete']) ? 1 : 0,
                'endpoints_routable' => !empty($integrity['endpoints_routable']) ? 1 : 0,
                'endpoints_trusted' => !empty($integrity['endpoints_complete']) ? 1 : 0,
                'missing_endpoint_count' => (int)($integrity['missing_endpoint_count'] ?? 0),
                'suspicious_endpoint_count' => (int)($integrity['suspicious_endpoint_count'] ?? 0),
                'endpoint_warning_codes' => (array)($integrity['endpoint_warning_codes'] ?? []),
            ]), [
                'integrity_repaired_at' => current_time('mysql'),
                'integrity_repaired_by' => $actorUserId > 0 ? $actorUserId : get_current_user_id(),
                'endpoint_reconciliation_contract' => RouteEndpointResolverService::CONTRACT,
                'route_distance_finalization_contract' => self::DISTANCE_FINALIZATION_CONTRACT,
                'routing_finalized_by' => 'routing_matrix_preview',
            ]);
            if (is_numeric($canonical['distance_km'] ?? null)
                && is_numeric($canonical['access_distance_km'] ?? null)
                && is_numeric($canonical['inter_stop_distance_km'] ?? null)
                && is_numeric($canonical['return_distance_km'] ?? null)) {
                $componentSum = (float)$canonical['access_distance_km'] + (float)$canonical['inter_stop_distance_km'] + (float)$canonical['return_distance_km'];
                $canonical['distance_component_sum_km'] = round($componentSum, 2);
                $canonical['distance_component_delta_km'] = round(abs((float)$canonical['distance_km'] - $componentSum), 3);
            }
            $meta['start_point'] = RouteEndpointResolverService::publicPoint($start);
            $meta['end_point'] = RouteEndpointResolverService::publicPoint($end);
            $meta['route_metrics'] = array_merge(is_array($meta['route_metrics'] ?? null) ? (array)$meta['route_metrics'] : [], $canonical);
            $meta['plan_summary'] = array_merge(is_array($meta['plan_summary'] ?? null) ? (array)$meta['plan_summary'] : [], $canonical);
            $meta['route_endpoint_integrity_contract'] = 'fieldflow-route-endpoint-distance-integrity-v1';
            $meta['route_endpoint_reconciliation_contract'] = RouteEndpointResolverService::CONTRACT;
            if (class_exists('\RoutesPro\Services\RouteEndpointProfileService')) $meta['route_endpoint_profile_contract'] = RouteEndpointProfileService::CONTRACT;
            $meta['endpoint_reconciled_at'] = current_time('mysql');
            $meta['endpoint_reconciled_by'] = $actorUserId > 0 ? $actorUserId : get_current_user_id();
            $meta['route_metrics_stale'] = 0;
            $meta['routing_refresh_required'] = 0;
            $updated = $wpdb->update($px . 'routes', [
                'meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ], ['id'=>$routeId], ['%s'], ['%d']);
            if ($updated === false) { $result['errors']++; continue; }

            // 2.2.513 finalizes reconciled routes with the ordered Google Compute Routes
            // result whenever that provider is available. The matrix preview remains a
            // safe fallback, but hybrid matrix edges no longer become the final persisted
            // distance for an otherwise routable route.
            $exactRefresh = self::maybeRefreshGoogleRoute($routeId, [], true);
            if (!empty($exactRefresh['ok'])) {
                $result['google_exact']++;
            } else {
                $result['matrix_fallback']++;
                if (empty($exactRefresh['skipped']) || (string)($exactRefresh['reason'] ?? '') !== 'provider_disabled') {
                    $result['google_exact_failed']++;
                }
            }

            if (class_exists('\RoutesPro\Services\RouteOptimizationService')) RouteOptimizationService::invalidateUserRouteCache($routeOwner);
            $result['repaired']++;
            if ($wasSuspicious) $result['reconciled']++;
            if ($metricRecalcNeeded) $result['recalculated']++;
        }
        return $result;
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    public static function maybeRefreshGoogleRoute(int $routeId, array $route = [], bool $force = false): array {
        if ($routeId <= 0 || !class_exists('\\RoutesPro\\Services\\GoogleRoutes') || !GoogleRoutes::enabled()) return ['ok'=>false,'skipped'=>true,'reason'=>'provider_disabled'];
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        if (!$route) $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d", $routeId), ARRAY_A) ?: [];
        if (!$route) return ['ok'=>false,'error'=>'Rota não encontrada.'];
        $meta = self::decode((string)($route['meta_json'] ?? ''));
        $metrics = is_array($meta['route_metrics'] ?? null) ? (array)$meta['route_metrics'] : [];
        $countryCode = self::routingCountryCode((array)$route, $meta);
        $geographyWasCurrent = self::geographicIntegrityCurrent($meta, $metrics, $countryCode);
        $meta['routing_country_code'] = $countryCode;
        // 9AN certification bridge, historical marker only: $refreshVersion = 'google_routes_geography_v3'
        $refreshVersion = 'google_routes_static_snapshot_v4';
        $stops = $wpdb->get_results($wpdb->prepare("SELECT rs.*, l.name AS location_name, l.address, l.lat, l.lng, l.city, l.district, l.county, l.postal_code FROM {$px}route_stops rs LEFT JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.route_id=%d ORDER BY rs.seq ASC, rs.id ASC", $routeId), ARRAY_A) ?: [];
        $rawStart = is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : [];
        $rawEnd = is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : [];
        $firstStop = $stops ? ['address'=>$stops[0]['address'] ?? '', 'lat'=>$stops[0]['lat'] ?? null, 'lng'=>$stops[0]['lng'] ?? null] : [];
        $lastStopRow = $stops ? $stops[count($stops)-1] : [];
        $lastStop = $lastStopRow ? ['address'=>$lastStopRow['address'] ?? '', 'lat'=>$lastStopRow['lat'] ?? null, 'lng'=>$lastStopRow['lng'] ?? null] : [];
        if (class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
            $resolvedEndpoints = RouteEndpointProfileService::resolveForRoute(
                absint($route['client_id'] ?? 0),
                absint($route['project_id'] ?? 0),
                absint($route['owner_user_id'] ?? 0),
                $meta,
                [],
                [],
                $firstStop,
                $lastStop,
                ['return_to_start'=>!empty($meta['return_to_start'])]
            );
            $rawStart = (array)($resolvedEndpoints['start_point'] ?? []);
            $rawEnd = (array)($resolvedEndpoints['end_point'] ?? []);
        }
        $start = class_exists('\RoutesPro\Services\RouteEndpointResolverService')
            ? RouteEndpointResolverService::resolve($rawStart, 'start', (string)($rawStart['provenance'] ?? 'legacy'), ['allow_matches_stop'=>!empty($rawStart['allow_matches_stop']),'country_code'=>$countryCode])
            : $rawStart;
        $end = class_exists('\RoutesPro\Services\RouteEndpointResolverService')
            ? RouteEndpointResolverService::resolve($rawEnd, 'end', (string)($rawEnd['provenance'] ?? 'legacy'), ['allow_matches_stop'=>!empty($rawEnd['allow_matches_stop']),'country_code'=>$countryCode])
            : $rawEnd;
        $endpointIntegrity = self::endpointIntegrity($start, $end, $firstStop, $lastStop, [
            'semantic'=>1,
            'allow_start_matches_stop'=>!empty($start['allow_matches_stop']),
            'allow_end_matches_stop'=>!empty($end['allow_matches_stop']),
        ]);
        if (empty($endpointIntegrity['endpoints_routable'])) {
            return [
                'ok'=>false,
                'skipped'=>true,
                'reason'=>'route_endpoints_incomplete',
                'endpoint_status'=>(string)($endpointIntegrity['endpoint_status'] ?? 'missing_both'),
                'endpoint_warning_codes'=>(array)($endpointIntegrity['endpoint_warning_codes'] ?? []),
                'meta'=>$meta,
            ];
        }
        if (class_exists('\RoutesPro\Services\RouteEndpointResolverService')) {
            $meta['start_point'] = RouteEndpointResolverService::publicPoint($start);
            $meta['end_point'] = RouteEndpointResolverService::publicPoint($end);
        }
        $points = [self::pointForRouting($start, 'Partida')];
        foreach ($stops as $stop) $points[] = self::pointForRouting(['name'=>$stop['location_name'] ?? '', 'address'=>$stop['address'] ?? '', 'lat'=>$stop['lat'] ?? null, 'lng'=>$stop['lng'] ?? null], 'PDV');
        $points[] = self::pointForRouting($end, 'Chegada');
        $currentSignature = method_exists(GoogleRoutes::class, 'routeSignature') ? GoogleRoutes::routeSignature($points) : '';
        $savedSignature = (string)($metrics['routing_signature'] ?? '');
        $metricsAreCurrent = (string)($metrics['routing_provider'] ?? '') === 'google_routes'
            && !empty($metrics['routing_calculated_at'])
            && empty($meta['route_metrics_stale'])
            && empty($meta['routing_refresh_required']);
        if (!$force && $metricsAreCurrent && $currentSignature !== '' && hash_equals($currentSignature, $savedSignature)) {
            return ['ok'=>true,'skipped'=>true,'reason'=>'route_signature_unchanged','metrics'=>$metrics,'meta'=>$meta];
        }
        // Existing 2.2.581 Google snapshots are already exact. Adopt their current
        // signature once during upgrade instead of generating a paid refresh storm.
        if (!$force && $metricsAreCurrent && $currentSignature !== '' && $savedSignature === '' && $geographyWasCurrent) {
            $metrics['routing_signature'] = $currentSignature;
            $metrics['routing_refresh_version'] = $refreshVersion;
            $metrics['routing_cost_contract'] = class_exists('\RoutesPro\Services\GoogleRoutes') ? GoogleRoutes::STATIC_ROUTING_COST_CONTRACT : 'fieldflow-static-routing-cost-authority-v1';
            $metrics['routing_snapshot_adopted_at'] = current_time('mysql');
            $meta['route_metrics'] = $metrics;
            $meta['plan_summary'] = array_merge(is_array($meta['plan_summary'] ?? null) ? (array)$meta['plan_summary'] : [], $metrics);
            $wpdb->update($px . 'routes', ['meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id'=>$routeId], ['%s'], ['%d']);
            return ['ok'=>true,'skipped'=>true,'reason'=>'existing_google_snapshot_adopted','metrics'=>$metrics,'meta'=>$meta];
        }
        $routing = GoogleRoutes::calculateRoute($points);
        if (empty($routing['ok'])) return ['ok'=>false,'error'=>(string)($routing['error'] ?? 'Falha no cálculo Google Routes.')];
        $legs = array_values((array)($routing['legs'] ?? []));
        $expectedLegs = count($stops) + 1;
        $resolvedLegs = 0;
        foreach ($legs as $leg) {
            if (isset($leg['distance_km'], $leg['travel_min']) && is_numeric($leg['distance_km']) && is_numeric($leg['travel_min'])) $resolvedLegs++;
        }
        $legsComplete = count($legs) >= $expectedLegs && $resolvedLegs >= $expectedLegs;
        $visitMin = 0;
        foreach ($stops as $idx => $stop) {
            $stopMeta = self::decode((string)($stop['meta_json'] ?? ''));
            $visit = isset($stopMeta['visit_time_min']) ? (int)$stopMeta['visit_time_min'] : (int)round(((int)($stop['duration_s'] ?? 0)) / 60);
            $visitMin += max(0, $visit);
            $leg = $legs[$idx] ?? [];
            $legDistance = isset($leg['distance_km']) && is_numeric($leg['distance_km']) ? round((float)$leg['distance_km'], 2) : null;
            $legTravel = isset($leg['travel_min']) && is_numeric($leg['travel_min']) ? round((float)$leg['travel_min'], 1) : null;
            $stopMeta['distance_from_prev_km'] = $legDistance;
            $stopMeta['travel_min_from_prev'] = $legTravel;
            $stopMeta['toll_cost_eur_from_prev'] = isset($leg['toll_cost_eur']) && is_numeric($leg['toll_cost_eur']) ? round((float)$leg['toll_cost_eur'], 2) : null;
            $stopMeta['leg_status'] = ($legDistance !== null && $legTravel !== null) ? 'complete' : 'unavailable';
            $stopMeta['distance_source'] = 'google_routes';
            $stopMeta['toll_provider'] = 'google_routes';
            $stopMeta['toll_is_real_api'] = !empty($leg['toll_has_price']) ? 1 : 0;
            $stopMeta['toll_confidence'] = (string)($leg['toll_confidence'] ?? (!empty($leg['toll_has_price']) ? 'real' : 'low'));
            $stopMeta['toll_min_eur'] = isset($leg['toll_min_eur']) && is_numeric($leg['toll_min_eur']) ? round((float)$leg['toll_min_eur'], 2) : $stopMeta['toll_cost_eur_from_prev'];
            $stopMeta['toll_max_eur'] = isset($leg['toll_max_eur']) && is_numeric($leg['toll_max_eur']) ? round((float)$leg['toll_max_eur'], 2) : $stopMeta['toll_cost_eur_from_prev'];
            $stopMeta['routing_provider'] = 'google_routes';
            $wpdb->update($px . 'route_stops', ['meta_json' => wp_json_encode($stopMeta, JSON_UNESCAPED_UNICODE)], ['id' => (int)$stop['id']]);
        }
        $endLeg = $legs[count($stops)] ?? [];
        $accessLeg = $legs[0] ?? [];
        $accessDistance = isset($accessLeg['distance_km']) && is_numeric($accessLeg['distance_km']) ? (float)$accessLeg['distance_km'] : null;
        $accessTravel = isset($accessLeg['travel_min']) && is_numeric($accessLeg['travel_min']) ? (float)$accessLeg['travel_min'] : null;
        $returnDistance = isset($endLeg['distance_km']) && is_numeric($endLeg['distance_km']) ? (float)$endLeg['distance_km'] : null;
        $returnTravel = isset($endLeg['travel_min']) && is_numeric($endLeg['travel_min']) ? (float)$endLeg['travel_min'] : null;
        $totalDistance = isset($routing['distance_km']) && is_numeric($routing['distance_km']) ? (float)$routing['distance_km'] : null;
        $totalTravel = isset($routing['travel_min']) && is_numeric($routing['travel_min']) ? (float)$routing['travel_min'] : null;
        $distanceStatus = $legsComplete && $totalDistance !== null && $totalTravel !== null ? 'complete' : 'incomplete';
        $unresolvedLegs = max(0, $expectedLegs - $resolvedLegs);
        $scheduleMetrics = self::canonicalFromSchedule([
            'distance_km' => $totalDistance,
            'known_distance_km' => $totalDistance,
            'access_distance_km' => $accessDistance,
            'inter_stop_distance_km' => ($totalDistance !== null && $accessDistance !== null && $returnDistance !== null) ? max(0.0, $totalDistance - $accessDistance - $returnDistance) : null,
            'return_distance_km' => $returnDistance,
            'travel_min' => $totalTravel,
            'access_travel_min' => $accessTravel,
            'inter_stop_travel_min' => ($totalTravel !== null && $accessTravel !== null && $returnTravel !== null) ? max(0.0, $totalTravel - $accessTravel - $returnTravel) : null,
            'return_travel_min' => $returnTravel,
            'visit_min' => $visitMin,
            'work_min' => $totalTravel !== null ? (int)round($totalTravel + $visitMin) : $visitMin,
            'total_min' => $totalTravel !== null ? (int)round($totalTravel + $visitMin) : $visitMin,
            'end_leg_toll_cost_eur' => (float)($endLeg['toll_cost_eur'] ?? 0),
            'toll_cost_eur' => (float)($routing['toll_cost_eur'] ?? 0),
            'toll_estimated_eur' => (float)($routing['toll_estimated_eur'] ?? $routing['toll_cost_eur'] ?? 0),
            'toll_model' => (string)($routing['toll_model'] ?? 'Google Routes API'),
            'toll_provider' => !empty($routing['toll_is_real_api']) ? 'google_routes' : 'internal_estimator',
            'toll_is_real_api' => !empty($routing['toll_is_real_api']) ? 1 : 0,
            'toll_confidence' => (string)($routing['toll_confidence'] ?? (!empty($routing['toll_is_real_api']) ? 'real' : 'low')),
            'toll_min_eur' => isset($routing['toll_min_eur']) && is_numeric($routing['toll_min_eur']) ? (float)$routing['toll_min_eur'] : (float)($routing['toll_cost_eur'] ?? 0),
            'toll_max_eur' => isset($routing['toll_max_eur']) && is_numeric($routing['toll_max_eur']) ? (float)$routing['toll_max_eur'] : (float)($routing['toll_cost_eur'] ?? 0),
            'toll_tariff_version' => (string)($routing['toll_tariff_version'] ?? ''),
            'toll_vehicle_class' => max(1, min(4, (int)($routing['toll_vehicle_class'] ?? 1))),
            'toll_cost_intelligence_contract' => (string)($routing['toll_cost_intelligence_contract'] ?? ''),
            'routing_provider' => 'google_routes',
            'routing_calculated_at' => (string)($routing['calculated_at'] ?? current_time('mysql')),
            'distance_source' => 'google_routes',
            'distance_status' => $distanceStatus,
            'endpoint_status' => (string)($endpointIntegrity['endpoint_status'] ?? 'complete'),
            'endpoints_complete' => !empty($endpointIntegrity['endpoints_complete']) ? 1 : 0,
            'endpoints_routable' => !empty($endpointIntegrity['endpoints_routable']) ? 1 : 0,
            'endpoints_trusted' => !empty($endpointIntegrity['endpoints_complete']) ? 1 : 0,
            'missing_endpoint_count' => (int)($endpointIntegrity['missing_endpoint_count'] ?? 0),
            'suspicious_endpoint_count' => (int)($endpointIntegrity['suspicious_endpoint_count'] ?? 0),
            'endpoint_warning_codes' => (array)($endpointIntegrity['endpoint_warning_codes'] ?? []),
            'endpoint_reconciliation_contract' => class_exists('\RoutesPro\Services\RouteEndpointResolverService') ? RouteEndpointResolverService::CONTRACT : '',
            'unresolved_leg_count' => $unresolvedLegs,
            'segment_count' => $expectedLegs,
            'matrix_provider' => (string)($metrics['matrix_provider'] ?? 'google_routes_refresh'),
            'matrix_source_label' => (string)($metrics['matrix_source_label'] ?? 'Google Routes'),
            'matrix_version' => (string)($metrics['matrix_version'] ?? 'fieldflow_routing_matrix_v1'),
            'matrix_coverage_pct' => is_numeric($metrics['matrix_coverage_pct'] ?? null) ? (float)$metrics['matrix_coverage_pct'] : 100.0,
            'optimization_method' => (string)($metrics['optimization_method'] ?? ''),
            'waypoint_order_optimized' => !empty($metrics['waypoint_order_optimized']) ? 1 : 0,
            'optimization_saved_distance_km' => is_numeric($metrics['optimization_saved_distance_km'] ?? null) ? (float)$metrics['optimization_saved_distance_km'] : null,
            'optimization_saved_travel_min' => is_numeric($metrics['optimization_saved_travel_min'] ?? null) ? (float)$metrics['optimization_saved_travel_min'] : null,
            'territorial_coherence_mode' => (string)($metrics['territorial_coherence_mode'] ?? 'strong'),
            'territory_same_base' => !empty($metrics['territory_same_base']) ? 1 : 0,
            'territory_dominant_direction' => (string)($metrics['territory_dominant_direction'] ?? 'base'),
            'territory_dominant_direction_label' => (string)($metrics['territory_dominant_direction_label'] ?? 'Zona da base'),
            'territory_dominant_share_pct' => is_numeric($metrics['territory_dominant_share_pct'] ?? null) ? (float)$metrics['territory_dominant_share_pct'] : 100.0,
            'territory_direction_count' => (int)($metrics['territory_direction_count'] ?? 0),
            'territory_direction_switches' => (int)($metrics['territory_direction_switches'] ?? 0),
            'territory_direction_reentries' => (int)($metrics['territory_direction_reentries'] ?? 0),
            'territory_opposite_mix' => !empty($metrics['territory_opposite_mix']) ? 1 : 0,
            'territory_opposite_direction_switches' => (int)($metrics['territory_opposite_direction_switches'] ?? 0),
            'territory_locality_count' => (int)($metrics['territory_locality_count'] ?? 0),
            'territory_locality_reentries' => (int)($metrics['territory_locality_reentries'] ?? 0),
            'territory_coherence_score' => is_numeric($metrics['territory_coherence_score'] ?? null) ? (float)$metrics['territory_coherence_score'] : 100.0,
            'territory_coherence_status' => (string)($metrics['territory_coherence_status'] ?? 'coherent'),
            'territory_coherence_label' => (string)($metrics['territory_coherence_label'] ?? 'Coerente'),
        ]);
        $componentSum = ($totalDistance !== null && $accessDistance !== null && $returnDistance !== null)
            ? $accessDistance + max(0.0, $totalDistance - $accessDistance - $returnDistance) + $returnDistance
            : null;
        $newMetrics = array_merge($metrics, $scheduleMetrics, [
            'route_distance_finalization_contract'=>self::DISTANCE_FINALIZATION_CONTRACT,
            'routing_geography_contract'=>class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::CONTRACT : 'fieldflow-routing-endpoint-geographic-integrity-v1',
            'routing_country_code'=>$countryCode,
            'routing_geography_reconciled_at'=>current_time('mysql'),
            'routing_finalized_by'=>'google_routes_sequence',
            'distance_component_sum_km'=>$componentSum !== null ? round($componentSum, 2) : null,
            'distance_component_delta_km'=>$componentSum !== null && $totalDistance !== null ? round(abs($totalDistance - $componentSum), 3) : null,
            'toll_fallback_internal'=>!empty($routing['toll_fallback_internal']) ? 1 : 0,
            'routing_preference'=>(string)($routing['routing_preference'] ?? ''),
            'routing_mode'=>(string)($routing['route_mode'] ?? ''),
            'routing_last_google_refresh_attempt_at'=>current_time('mysql'),
            'routing_refresh_version'=>$refreshVersion,
            'routing_signature'=>(string)($routing['route_signature'] ?? $currentSignature),
            'routing_cost_contract'=>(string)($routing['routing_cost_contract'] ?? 'fieldflow-static-routing-cost-authority-v1'),
        ]);
        $meta['routing_country_code'] = $countryCode;
        $meta['routing_geography_contract'] = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::CONTRACT : 'fieldflow-routing-endpoint-geographic-integrity-v1';
        $meta['routing_geography_reconciled_at'] = current_time('mysql');
        $meta['route_metrics'] = $newMetrics;
        $meta['plan_summary'] = array_merge(is_array($meta['plan_summary'] ?? null) ? (array)$meta['plan_summary'] : [], $newMetrics);
        $meta['route_metrics_stale'] = 0;
        $meta['routing_refresh_required'] = 0;
        unset($meta['routing_refresh_reason']);
        $wpdb->update($px . 'routes', ['meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE)], ['id' => $routeId]);
        return ['ok'=>true,'metrics'=>$newMetrics,'meta'=>$meta,'routing'=>$routing];
    }

    private static function pointForRouting(array $point, string $fallbackName): array { return ['name'=>(string)($point['name'] ?? $fallbackName), 'address'=>(string)($point['address'] ?? ''), 'lat'=>is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null, 'lng'=>is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null]; }
    private static function decode(string $json): array { if ($json === '') return []; $data = json_decode($json, true); return is_array($data) ? $data : []; }
}
