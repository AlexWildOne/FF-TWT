<?php
namespace RoutesPro\Services;

use RoutesPro\Support\RouteStatus;

if (!defined('ABSPATH')) exit;

/**
 * Mobility Cost Intelligence.
 *
 * Persistence intentionally reuses existing project/route meta_json structures:
 * - projects.meta_json.mobility_cost_default
 * - projects.meta_json.mobility_cost_profiles[user_id]
 * - routes.meta_json.mobility_cost_override
 * - routes.meta_json.mobility_cost_snapshot
 *
 * No routing, distance or toll calculation is owned here. The service only derives
 * energy and mobility cost from canonical route distance/toll metrics.
 */
class MobilityCostService {
    private static array $projectConfigCache = [];
    public const VERSION = '1.0.0';
    public const CONTRACT = 'fieldflow-mobility-cost-intelligence-v1';

    public const TYPE_GASOLINE = 'gasoline';
    public const TYPE_DIESEL = 'diesel';
    public const TYPE_LPG = 'lpg';
    public const TYPE_HYBRID = 'hybrid';
    public const TYPE_ELECTRIC = 'electric';

    public static function energyTypes(): array {
        return [
            self::TYPE_GASOLINE => 'Gasolina',
            self::TYPE_DIESEL => 'Gasóleo',
            self::TYPE_LPG => 'GPL',
            self::TYPE_HYBRID => 'Híbrido',
            self::TYPE_ELECTRIC => '100% elétrico',
        ];
    }

    public static function fuelTypes(): array {
        return [
            self::TYPE_GASOLINE => 'Gasolina',
            self::TYPE_DIESEL => 'Gasóleo',
            self::TYPE_LPG => 'GPL',
        ];
    }

    public static function emptyProfile(): array {
        return [
            'enabled' => 0,
            'energy_type' => self::TYPE_DIESEL,
            'vehicle_label' => '',
            'fuel_type' => self::TYPE_DIESEL,
            'hybrid_mode' => 'standard',
            'fuel_price_eur_per_l' => 0.0,
            'consumption_l_per_100km' => 0.0,
            'electricity_price_eur_per_kwh' => 0.0,
            'consumption_kwh_per_100km' => 0.0,
            'electric_share_pct' => 50.0,
            'operational_cost_eur_per_km' => 0.0,
            'valid_from' => '',
            'updated_at' => '',
            'updated_by' => 0,
        ];
    }

    public static function normalizeProfile(array $raw): array {
        $p = array_merge(self::emptyProfile(), $raw);
        $types = array_keys(self::energyTypes());
        $energyType = sanitize_key((string)($p['energy_type'] ?? self::TYPE_DIESEL));
        if (!in_array($energyType, $types, true)) $energyType = self::TYPE_DIESEL;

        $fuelType = sanitize_key((string)($p['fuel_type'] ?? self::TYPE_DIESEL));
        if (!in_array($fuelType, array_keys(self::fuelTypes()), true)) $fuelType = self::TYPE_DIESEL;
        if (in_array($energyType, array_keys(self::fuelTypes()), true)) $fuelType = $energyType;

        $hybridMode = sanitize_key((string)($p['hybrid_mode'] ?? 'standard'));
        if (!in_array($hybridMode, ['standard','plug_in'], true)) $hybridMode = 'standard';

        $number = static function($value, float $min, float $max, int $precision = 3): float {
            $value = is_string($value) ? str_replace(',', '.', trim($value)) : $value;
            $n = is_numeric($value) ? (float)$value : 0.0;
            return round(max($min, min($max, $n)), $precision);
        };

        $validFrom = sanitize_text_field((string)($p['valid_from'] ?? ''));
        if ($validFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validFrom)) $validFrom = '';

        return [
            'enabled' => !empty($p['enabled']) ? 1 : 0,
            'energy_type' => $energyType,
            'vehicle_label' => sanitize_text_field((string)($p['vehicle_label'] ?? '')),
            'fuel_type' => $fuelType,
            'hybrid_mode' => $hybridMode,
            'fuel_price_eur_per_l' => $number($p['fuel_price_eur_per_l'] ?? 0, 0, 10, 4),
            'consumption_l_per_100km' => $number($p['consumption_l_per_100km'] ?? 0, 0, 80, 3),
            'electricity_price_eur_per_kwh' => $number($p['electricity_price_eur_per_kwh'] ?? 0, 0, 5, 4),
            'consumption_kwh_per_100km' => $number($p['consumption_kwh_per_100km'] ?? 0, 0, 100, 3),
            'electric_share_pct' => $number($p['electric_share_pct'] ?? 50, 0, 100, 1),
            'operational_cost_eur_per_km' => $number($p['operational_cost_eur_per_km'] ?? 0, 0, 10, 4),
            'valid_from' => $validFrom,
            'updated_at' => sanitize_text_field((string)($p['updated_at'] ?? '')),
            'updated_by' => absint($p['updated_by'] ?? 0),
        ];
    }

    public static function profileIssues(array $profile): array {
        $profile = self::normalizeProfile($profile);
        if (empty($profile['enabled'])) return [];
        $issues = [];
        $fuelRequired = false;
        $electricRequired = false;
        if (in_array($profile['energy_type'], [self::TYPE_GASOLINE, self::TYPE_DIESEL, self::TYPE_LPG], true)) {
            $fuelRequired = true;
        } elseif ($profile['energy_type'] === self::TYPE_HYBRID) {
            if ($profile['hybrid_mode'] === 'plug_in') {
                $share = (float)$profile['electric_share_pct'];
                $fuelRequired = $share < 100.0;
                $electricRequired = $share > 0.0;
            } else {
                $fuelRequired = true;
            }
        } elseif ($profile['energy_type'] === self::TYPE_ELECTRIC) {
            $electricRequired = true;
        }
        if ($fuelRequired && (float)$profile['fuel_price_eur_per_l'] <= 0) $issues[] = 'Indica o preço do combustível em €/L.';
        if ($fuelRequired && (float)$profile['consumption_l_per_100km'] <= 0) $issues[] = 'Indica o consumo em L/100 km.';
        if ($electricRequired && (float)$profile['electricity_price_eur_per_kwh'] <= 0) $issues[] = 'Indica o preço da eletricidade em €/kWh.';
        if ($electricRequired && (float)$profile['consumption_kwh_per_100km'] <= 0) $issues[] = 'Indica o consumo em kWh/100 km.';
        return array_values(array_unique($issues));
    }

    public static function profileReady(array $profile): bool {
        return self::profileIssues($profile) === [];
    }

    private static function profilesEquivalent(array $expected, array $actual): bool {
        $a = self::normalizeProfile($expected);
        $b = self::normalizeProfile($actual);
        return hash_equals((string)wp_json_encode($a), (string)wp_json_encode($b));
    }

    /**
     * Refresh a mobility snapshot only while the route is still operationally
     * editable. Historical/in-progress routes retain the snapshot they started
     * with, while draft/planned routes follow legitimate owner/project/date edits.
     */
    public static function refreshSnapshotForIdentityChange(int $routeId, array $before, array $after, int $actorUserId = 0): array {
        if ($routeId <= 0) return ['ok'=>false,'error'=>'invalid_route'];
        $beforeStatus = (string)($before['status'] ?? 'draft');
        $afterStatus = (string)($after['status'] ?? $beforeStatus);
        if (!RouteStatus::isSnapshotMutable($beforeStatus) || !RouteStatus::isSnapshotMutable($afterStatus)) {
            return ['ok'=>true,'skipped'=>true,'reason'=>'historical_snapshot_protected'];
        }
        $changed = absint($before['project_id'] ?? 0) !== absint($after['project_id'] ?? 0)
            || absint($before['owner_user_id'] ?? 0) !== absint($after['owner_user_id'] ?? 0)
            || (string)($before['date'] ?? '') !== (string)($after['date'] ?? '');
        if (!$changed) return ['ok'=>true,'skipped'=>true,'reason'=>'identity_unchanged'];
        return self::ensureRouteSnapshot($routeId, true, $actorUserId);
    }

    private static function decodeMeta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function getProjectMobilityConfig(int $projectId): array {
        global $wpdb;
        if ($projectId <= 0) return ['default' => self::emptyProfile(), 'profiles' => []];
        if (isset(self::$projectConfigCache[$projectId])) return self::$projectConfigCache[$projectId];
        $table = $wpdb->prefix . 'routespro_projects';
        $raw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $projectId));
        $meta = self::decodeMeta((string)$raw);
        $profiles = [];
        foreach ((array)($meta['mobility_cost_profiles'] ?? []) as $userId => $profile) {
            $uid = absint($userId);
            if ($uid <= 0 || !is_array($profile)) continue;
            $profiles[$uid] = self::normalizeProfile($profile);
        }
        ksort($profiles, SORT_NUMERIC);
        self::$projectConfigCache[$projectId] = [
            'default' => self::normalizeProfile(is_array($meta['mobility_cost_default'] ?? null) ? $meta['mobility_cost_default'] : []),
            'profiles' => $profiles,
        ];
        return self::$projectConfigCache[$projectId];
    }

    public static function saveProjectDefault(int $projectId, array $profile, int $actorUserId = 0): bool {
        return self::saveProjectProfileInternal($projectId, 0, $profile, $actorUserId);
    }

    public static function saveProjectUserProfile(int $projectId, int $userId, array $profile, int $actorUserId = 0): bool {
        if ($userId <= 0) return false;
        return self::saveProjectProfileInternal($projectId, $userId, $profile, $actorUserId);
    }

    private static function saveProjectProfileInternal(int $projectId, int $userId, array $profile, int $actorUserId): bool {
        global $wpdb;
        if ($projectId <= 0) return false;
        $table = $wpdb->prefix . 'routespro_projects';
        $row = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $projectId), ARRAY_A);
        if (!$row) return false;
        $meta = self::decodeMeta((string)($row['meta_json'] ?? ''));
        $profile = self::normalizeProfile($profile);
        if (self::profileIssues($profile)) return false;
        $profile['updated_at'] = current_time('mysql');
        $profile['updated_by'] = $actorUserId > 0 ? $actorUserId : get_current_user_id();
        if ($userId > 0) {
            $profiles = is_array($meta['mobility_cost_profiles'] ?? null) ? $meta['mobility_cost_profiles'] : [];
            $profiles[(string)$userId] = $profile;
            $meta['mobility_cost_profiles'] = $profiles;
        } else {
            $meta['mobility_cost_default'] = $profile;
        }
        $meta['mobility_cost_contract'] = self::CONTRACT;
        $ok = $wpdb->update($table, ['meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id' => $projectId], ['%s'], ['%d']);
        if ($ok === false) return false;

        // Financial configuration must be proven durable, not merely accepted by $wpdb.
        // Invalidate the request cache, read the persisted JSON back and compare the
        // normalized profile that subsequent route resolution will actually consume.
        unset(self::$projectConfigCache[$projectId]);
        $stored = self::getProjectMobilityConfig($projectId);
        $readback = $userId > 0
            ? (array)($stored['profiles'][$userId] ?? [])
            : (array)($stored['default'] ?? []);
        return self::profilesEquivalent($profile, $readback);
    }

    private static function profileActiveOnDate(array $profile, string $routeDate = ''): bool {
        $profile = self::normalizeProfile($profile);
        if (empty($profile['enabled'])) return false;
        $validFrom = (string)($profile['valid_from'] ?? '');
        if ($validFrom === '' || $routeDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $routeDate)) return true;
        return $routeDate >= $validFrom;
    }

    public static function resolveProfile(int $projectId, int $ownerUserId = 0, array $routeMeta = [], string $routeDate = ''): array {
        if (!empty($routeMeta['mobility_cost_override']) && is_array($routeMeta['mobility_cost_override'])) {
            $profile = self::normalizeProfile($routeMeta['mobility_cost_override']);
            if (self::profileActiveOnDate($profile, $routeDate)) {
                $profile['_source'] = 'route_override';
                return $profile;
            }
        }
        $config = self::getProjectMobilityConfig($projectId);
        if ($ownerUserId > 0 && !empty($config['profiles'][$ownerUserId])) {
            $profile = self::normalizeProfile((array)$config['profiles'][$ownerUserId]);
            if (self::profileActiveOnDate($profile, $routeDate)) {
                $profile['_source'] = 'operator_profile';
                return $profile;
            }
        }
        $profile = self::normalizeProfile((array)($config['default'] ?? []));
        if (self::profileActiveOnDate($profile, $routeDate)) {
            $profile['_source'] = 'campaign_default';
            return $profile;
        }
        $profile = self::emptyProfile();
        $profile['_source'] = 'none';
        return $profile;
    }

    public static function profileLabel(array $profile): string {
        $profile = self::normalizeProfile($profile);
        $types = self::energyTypes();
        $label = $types[$profile['energy_type']] ?? 'Mobilidade';
        if ($profile['energy_type'] === self::TYPE_HYBRID) {
            $fuel = self::fuelTypes()[$profile['fuel_type']] ?? 'Combustível';
            $label .= $profile['hybrid_mode'] === 'plug_in' ? ' plug-in, ' . $fuel : ', ' . $fuel;
        }
        if ($profile['vehicle_label'] !== '') $label .= ' · ' . $profile['vehicle_label'];
        return $label;
    }

    public static function estimate(float $distanceKm, float $tollCostEur, array $profile): array {
        $profile = self::normalizeProfile($profile);
        $distanceKm = max(0.0, $distanceKm);
        $tollCostEur = max(0.0, $tollCostEur);
        $litres = 0.0;
        $kwh = 0.0;
        $fuelCost = 0.0;
        $electricCost = 0.0;
        $fuelDistance = 0.0;
        $electricDistance = 0.0;

        if (!empty($profile['enabled'])) {
            if (in_array($profile['energy_type'], [self::TYPE_GASOLINE, self::TYPE_DIESEL, self::TYPE_LPG], true)) {
                $fuelDistance = $distanceKm;
            } elseif ($profile['energy_type'] === self::TYPE_HYBRID) {
                if ($profile['hybrid_mode'] === 'plug_in') {
                    $share = max(0.0, min(100.0, (float)$profile['electric_share_pct'])) / 100;
                    $electricDistance = $distanceKm * $share;
                    $fuelDistance = $distanceKm - $electricDistance;
                } else {
                    $fuelDistance = $distanceKm;
                }
            } elseif ($profile['energy_type'] === self::TYPE_ELECTRIC) {
                $electricDistance = $distanceKm;
            }

            if ($fuelDistance > 0 && $profile['consumption_l_per_100km'] > 0) {
                $litres = $fuelDistance * ((float)$profile['consumption_l_per_100km'] / 100);
                $fuelCost = $litres * (float)$profile['fuel_price_eur_per_l'];
            }
            if ($electricDistance > 0 && $profile['consumption_kwh_per_100km'] > 0) {
                $kwh = $electricDistance * ((float)$profile['consumption_kwh_per_100km'] / 100);
                $electricCost = $kwh * (float)$profile['electricity_price_eur_per_kwh'];
            }
        }

        $energyCost = $fuelCost + $electricCost;
        $operationalCost = !empty($profile['enabled']) ? $distanceKm * (float)$profile['operational_cost_eur_per_km'] : 0.0;
        $total = $energyCost + $operationalCost + $tollCostEur;

        return [
            'contract' => self::CONTRACT,
            'enabled' => !empty($profile['enabled']) ? 1 : 0,
            'profile_label' => self::profileLabel($profile),
            'energy_type' => $profile['energy_type'],
            'fuel_type' => $profile['energy_type'] === self::TYPE_ELECTRIC ? '' : $profile['fuel_type'],
            'hybrid_mode' => $profile['hybrid_mode'],
            'vehicle_label' => $profile['vehicle_label'],
            'distance_km' => round($distanceKm, 2),
            'fuel_distance_km' => round($fuelDistance, 2),
            'electric_distance_km' => round($electricDistance, 2),
            'fuel_price_eur_per_l' => (float)$profile['fuel_price_eur_per_l'],
            'consumption_l_per_100km' => (float)$profile['consumption_l_per_100km'],
            'estimated_litres' => round($litres, 3),
            'fuel_cost_eur' => round($fuelCost, 2),
            'electricity_price_eur_per_kwh' => (float)$profile['electricity_price_eur_per_kwh'],
            'consumption_kwh_per_100km' => (float)$profile['consumption_kwh_per_100km'],
            'electric_share_pct' => (float)$profile['electric_share_pct'],
            'estimated_kwh' => round($kwh, 3),
            'electricity_cost_eur' => round($electricCost, 2),
            'energy_cost_eur' => round($energyCost, 2),
            'operational_cost_eur_per_km' => (float)$profile['operational_cost_eur_per_km'],
            'operational_cost_eur' => round($operationalCost, 2),
            'toll_cost_eur' => round($tollCostEur, 2),
            'total_mobility_cost_eur' => round($total, 2),
        ];
    }

    public static function estimateForMetrics(array $metrics, array $profile): array {
        $profile = self::normalizeProfile($profile);
        $distanceStatus = sanitize_key((string)($metrics['distance_status'] ?? ''));
        $endpointsComplete = !array_key_exists('endpoints_complete', $metrics) || !empty($metrics['endpoints_complete']);
        $endpointsRoutable = array_key_exists('endpoints_routable', $metrics) ? !empty($metrics['endpoints_routable']) : $endpointsComplete;
        $distance = is_numeric($metrics['distance_km'] ?? null) ? max(0.0, (float)$metrics['distance_km']) : null;
        $knownDistance = is_numeric($metrics['known_distance_km'] ?? null) ? max(0.0, (float)$metrics['known_distance_km']) : $distance;
        $complete = $distance !== null && $distanceStatus === 'complete' && $endpointsRoutable;

        if (!$complete) {
            $estimate = self::estimate(0.0, 0.0, $profile);
            $estimate['distance_km'] = null;
            $estimate['known_distance_km'] = $knownDistance !== null ? round($knownDistance, 2) : null;
            $estimate['costs_available'] = 0;
            $estimate['calculation_status'] = 'distance_incomplete';
            $estimate['distance_status'] = $distanceStatus !== '' ? $distanceStatus : 'incomplete';
            $estimate['endpoint_status'] = sanitize_key((string)($metrics['endpoint_status'] ?? 'incomplete'));
            $estimate['endpoints_complete'] = $endpointsComplete ? 1 : 0;
            $estimate['endpoints_routable'] = $endpointsRoutable ? 1 : 0;
            $estimate['endpoints_trusted'] = $endpointsComplete ? 1 : 0;
            $estimate['missing_endpoint_count'] = max(0, (int)($metrics['missing_endpoint_count'] ?? ($endpointsRoutable ? 0 : 1)));
            $estimate['unresolved_leg_count'] = max(0, (int)($metrics['unresolved_leg_count'] ?? 0));
            return $estimate;
        }

        $toll = is_numeric($metrics['toll_cost_eur'] ?? null) ? max(0.0, (float)$metrics['toll_cost_eur']) : 0.0;
        $estimate = self::estimate($distance, $toll, $profile);
        $estimate['known_distance_km'] = $knownDistance !== null ? round($knownDistance, 2) : round($distance, 2);
        $estimate['costs_available'] = 1;
        $estimate['calculation_status'] = 'complete';
        $estimate['distance_status'] = 'complete';
        $estimate['endpoint_status'] = sanitize_key((string)($metrics['endpoint_status'] ?? 'complete')) ?: 'complete';
        $estimate['endpoints_complete'] = $endpointsComplete ? 1 : 0;
        $estimate['endpoints_routable'] = 1;
        $estimate['endpoints_trusted'] = $endpointsComplete ? 1 : 0;
        $estimate['missing_endpoint_count'] = 0;
        $estimate['unresolved_leg_count'] = max(0, (int)($metrics['unresolved_leg_count'] ?? 0));
        return $estimate;
    }

    public static function snapshotPayload(array $profile, string $source = '', int $actorUserId = 0): array {
        $profile = self::normalizeProfile($profile);
        return [
            'contract' => self::CONTRACT,
            'profile' => $profile,
            'profile_source' => sanitize_key($source ?: (string)($profile['_source'] ?? 'campaign_default')),
            'captured_at' => current_time('mysql'),
            'captured_by' => $actorUserId > 0 ? $actorUserId : get_current_user_id(),
        ];
    }

    public static function profileFromRouteMeta(array $routeMeta, int $projectId = 0, int $ownerUserId = 0, string $routeDate = ''): array {
        if (!empty($routeMeta['mobility_cost_snapshot']['profile']) && is_array($routeMeta['mobility_cost_snapshot']['profile'])) {
            $profile = self::normalizeProfile((array)$routeMeta['mobility_cost_snapshot']['profile']);
            $profile['_source'] = sanitize_key((string)($routeMeta['mobility_cost_snapshot']['profile_source'] ?? 'route_snapshot')) ?: 'route_snapshot';
            return $profile;
        }
        return self::resolveProfile($projectId, $ownerUserId, $routeMeta, $routeDate);
    }

    public static function calculateForRouteArray(array $route): array {
        $meta = self::decodeMeta($route['meta_decoded'] ?? ($route['meta_json'] ?? ''));
        $canonical = RouteMetricsService::canonicalFromRouteArray(array_merge($route, ['meta_decoded' => $meta]));
        $profile = self::profileFromRouteMeta($meta, absint($route['project_id'] ?? 0), absint($route['owner_user_id'] ?? 0), sanitize_text_field((string)($route['date'] ?? '')));
        $estimate = self::estimateForMetrics($canonical, $profile);
        $routeId = absint($route['id'] ?? $route['route_id'] ?? 0);
        $storedLegs = $routeId > 0 && method_exists(RouteMetricsService::class, 'reportingStoredRouteLegBreakdown')
            ? RouteMetricsService::reportingStoredRouteLegBreakdown($routeId, $meta)
            : [];
        $estimate['profile_source'] = sanitize_key((string)($profile['_source'] ?? ''));
        $estimate['has_snapshot'] = !empty($meta['mobility_cost_snapshot']) ? 1 : 0;
        $estimate['distance_value_source'] = sanitize_text_field((string)($canonical['distance_value_source'] ?? ''));
        $estimate['known_distance_value_source'] = sanitize_text_field((string)($canonical['known_distance_value_source'] ?? ''));
        $estimate['toll_value_source'] = sanitize_text_field((string)($canonical['toll_value_source'] ?? ''));
        $estimate['known_toll_cost_eur'] = is_numeric($canonical['known_toll_cost_eur'] ?? null) ? round((float)$canonical['known_toll_cost_eur'], 2) : null;
        $estimate['known_toll_value_source'] = sanitize_text_field((string)($canonical['known_toll_value_source'] ?? ''));
        $estimate['toll_is_real_api'] = !empty($canonical['toll_is_real_api']) ? 1 : 0;
        $estimate['toll_provider'] = sanitize_key((string)($canonical['toll_provider'] ?? ''));
        $estimate['toll_model'] = sanitize_text_field((string)($canonical['toll_model'] ?? ''));
        foreach (['access_distance_km','inter_stop_distance_km','return_distance_km'] as $key) {
            $estimate[$key] = is_numeric($canonical[$key] ?? null) ? round((float)$canonical[$key], 2) : null;
            $estimate['stored_' . $key] = is_numeric($storedLegs[$key] ?? null) ? round((float)$storedLegs[$key], 2) : null;
        }
        $estimate['stored_route_distance_km'] = is_numeric($storedLegs['distance_km'] ?? null) ? round((float)$storedLegs['distance_km'], 2) : null;
        $estimate['stored_route_known_distance_km'] = is_numeric($storedLegs['known_distance_km'] ?? null) ? round((float)$storedLegs['known_distance_km'], 2) : null;
        $estimate['stored_route_toll_cost_eur'] = is_numeric($storedLegs['toll_cost_eur'] ?? null) ? round((float)$storedLegs['toll_cost_eur'], 2) : null;
        $estimate['stored_route_legs_complete'] = !empty($storedLegs['complete']) ? 1 : 0;
        $estimate['route_metrics_resolver'] = sanitize_text_field((string)($canonical['resolver_contract'] ?? ''));
        $estimate['route_distance_integrity'] = sanitize_text_field((string)($canonical['integrity_contract'] ?? ''));
        return $estimate;
    }

    /**
     * Reporting-only projection for closed routes whose canonical integrity is
     * not fully authoritative but where FieldFlow still owns a known distance
     * and toll value. This never mutates route metrics and never upgrades the
     * canonical costs_available flag. It only prevents a known estimate from
     * being rendered as a false zero in Daily Close reporting.
     */
    public static function reportingProjection(array $calculated): array {
        $out = $calculated;
        $baseDistance = is_numeric($calculated['distance_km'] ?? null)
            ? round((float)$calculated['distance_km'], 2)
            : (is_numeric($calculated['known_distance_km'] ?? null) ? round((float)$calculated['known_distance_km'], 2) : null);

        $canonicalParts = [
            is_numeric($calculated['access_distance_km'] ?? null) ? max(0.0, (float)$calculated['access_distance_km']) : null,
            is_numeric($calculated['inter_stop_distance_km'] ?? null) ? max(0.0, (float)$calculated['inter_stop_distance_km']) : null,
            is_numeric($calculated['return_distance_km'] ?? null) ? max(0.0, (float)$calculated['return_distance_km']) : null,
        ];
        $storedParts = [
            is_numeric($calculated['stored_access_distance_km'] ?? null) ? max(0.0, (float)$calculated['stored_access_distance_km']) : null,
            is_numeric($calculated['stored_inter_stop_distance_km'] ?? null) ? max(0.0, (float)$calculated['stored_inter_stop_distance_km']) : null,
            is_numeric($calculated['stored_return_distance_km'] ?? null) ? max(0.0, (float)$calculated['stored_return_distance_km']) : null,
        ];
        $canonicalSum = !in_array(null, $canonicalParts, true) ? round(array_sum($canonicalParts), 2) : null;
        $storedSum = !empty($calculated['stored_route_legs_complete']) && is_numeric($calculated['stored_route_distance_km'] ?? null)
            ? round((float)$calculated['stored_route_distance_km'], 2)
            : (!in_array(null, $storedParts, true) ? round(array_sum($storedParts), 2) : null);

        $reportingDistance = $baseDistance;
        $reportingParts = $canonicalParts;
        $reportingBasis = $baseDistance !== null ? 'canonical_total' : 'unavailable';
        foreach ([['distance'=>$canonicalSum,'parts'=>$canonicalParts,'basis'=>'component_sum'], ['distance'=>$storedSum,'parts'=>$storedParts,'basis'=>'stored_route_legs']] as $candidate) {
            if (!is_numeric($candidate['distance'])) continue;
            $candidateDistance = round((float)$candidate['distance'], 2);
            if ($reportingDistance === null || $candidateDistance > $reportingDistance + 0.05) {
                $reportingDistance = $candidateDistance;
                $reportingParts = $candidate['parts'];
                $reportingBasis = (string)$candidate['basis'];
            }
        }

        $distanceReconciled = $reportingDistance !== null && ($baseDistance === null || $reportingDistance > $baseDistance + 0.05);
        $out['reporting_distance_km'] = $reportingDistance;
        $out['reporting_access_distance_km'] = is_numeric($reportingParts[0] ?? null) ? round((float)$reportingParts[0], 2) : null;
        $out['reporting_inter_stop_distance_km'] = is_numeric($reportingParts[1] ?? null) ? round((float)$reportingParts[1], 2) : null;
        $out['reporting_return_distance_km'] = is_numeric($reportingParts[2] ?? null) ? round((float)$reportingParts[2], 2) : null;
        $out['reporting_distance_basis'] = $reportingBasis;
        $out['reporting_full_route_reconciled'] = $distanceReconciled ? 1 : 0;
        $out['reporting_costs_available'] = !empty($calculated['costs_available']) ? 1 : 0;
        $out['reporting_partial'] = (empty($calculated['costs_available']) && $reportingDistance !== null) || $distanceReconciled ? 1 : 0;
        $out['reporting_recovered'] = $distanceReconciled ? 1 : 0;
        $out['reporting_quality'] = $distanceReconciled ? 'full_route_component_reconciled' : (!empty($calculated['costs_available']) ? 'canonical_complete' : ($reportingDistance !== null ? 'known_distance_only' : 'unavailable'));

        if ($reportingDistance === null) return $out;
        if (!empty($calculated['costs_available']) && !$distanceReconciled) return $out;

        $profile = self::normalizeProfile($calculated);
        if (empty($profile['enabled']) || !self::profileReady($profile)) return $out;

        $knownToll = null;
        if ($reportingBasis === 'stored_route_legs' && is_numeric($calculated['stored_route_toll_cost_eur'] ?? null)) {
            $knownToll = max(0.0, (float)$calculated['stored_route_toll_cost_eur']);
            $out['reporting_toll_basis'] = 'stored_route_legs';
        } elseif (!$distanceReconciled || !empty($calculated['toll_is_real_api'])) {
            if (is_numeric($calculated['known_toll_cost_eur'] ?? null)) {
                $knownToll = max(0.0, (float)$calculated['known_toll_cost_eur']);
                $out['reporting_toll_basis'] = !empty($calculated['toll_is_real_api']) ? 'real_api_known_total' : 'known_total';
            }
        }
        if ($knownToll === null && class_exists('\RoutesPro\Support\TollEstimator')) {
            $knownToll = \RoutesPro\Support\TollEstimator::costFromKm((float)$reportingDistance, 'route');
            $out['reporting_toll_basis'] = 'route_estimator_reconciled';
        }
        if ($knownToll === null) $knownToll = 0.0;

        $recovered = self::estimate((float)$reportingDistance, (float)$knownToll, $profile);
        $out['reporting_costs_available'] = 1;
        $out['reporting_recovered'] = 1;
        $out['reporting_partial'] = 1;
        if (!$distanceReconciled) $out['reporting_quality'] = 'known_distance_estimate';
        foreach ([
            'energy_cost_eur','fuel_cost_eur','electricity_cost_eur','operational_cost_eur',
            'toll_cost_eur','total_mobility_cost_eur','estimated_litres','estimated_kwh'
        ] as $key) {
            $out['reporting_' . $key] = $recovered[$key] ?? null;
        }
        return $out;
    }

    public static function ensureRouteSnapshot(int $routeId, bool $force = false, int $actorUserId = 0): array {
        global $wpdb;
        if ($routeId <= 0) return ['ok' => false, 'error' => 'invalid_route'];
        $table = $wpdb->prefix . 'routespro_routes';
        $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $routeId), ARRAY_A);
        if (!$route) return ['ok' => false, 'error' => 'route_not_found'];
        $meta = self::decodeMeta((string)($route['meta_json'] ?? ''));
        if (!$force && !empty($meta['mobility_cost_snapshot']['profile'])) {
            return ['ok' => true, 'skipped' => true, 'snapshot' => $meta['mobility_cost_snapshot'], 'costs' => self::calculateForRouteArray($route)];
        }
        $profile = self::resolveProfile(absint($route['project_id'] ?? 0), absint($route['owner_user_id'] ?? 0), $meta, sanitize_text_field((string)($route['date'] ?? '')));
        if (empty($profile['enabled'])) {
            if ($force && !empty($meta['mobility_cost_snapshot'])) {
                unset($meta['mobility_cost_snapshot']);
                $meta['mobility_cost_contract'] = self::CONTRACT;
                $ok = $wpdb->update(
                    $table,
                    ['meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ['id' => $routeId],
                    ['%s'],
                    ['%d']
                );
                if ($ok === false) return ['ok' => false, 'error' => $wpdb->last_error ?: 'db_error'];
                return ['ok' => true, 'cleared' => true, 'reason' => 'profile_disabled'];
            }
            return ['ok' => false, 'skipped' => true, 'error' => 'profile_disabled'];
        }
        $meta['mobility_cost_snapshot'] = self::snapshotPayload($profile, (string)($profile['_source'] ?? ''), $actorUserId);
        $meta['mobility_cost_contract'] = self::CONTRACT;
        $ok = $wpdb->update($table, ['meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id' => $routeId], ['%s'], ['%d']);
        if ($ok === false) return ['ok' => false, 'error' => $wpdb->last_error ?: 'db_error'];
        $route['meta_json'] = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ['ok' => true, 'snapshot' => $meta['mobility_cost_snapshot'], 'costs' => self::calculateForRouteArray($route)];
    }

    public static function saveRouteOverride(int $routeId, array $profile, bool $snapshotNow = true, int $actorUserId = 0): bool {
        global $wpdb;
        if ($routeId <= 0) return false;
        $table = $wpdb->prefix . 'routespro_routes';
        $row = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $routeId), ARRAY_A);
        if (!$row) return false;
        $meta = self::decodeMeta((string)($row['meta_json'] ?? ''));
        $profile = self::normalizeProfile($profile);
        if (self::profileIssues($profile)) return false;
        $profile['updated_at'] = current_time('mysql');
        $profile['updated_by'] = $actorUserId > 0 ? $actorUserId : get_current_user_id();
        $meta['mobility_cost_override'] = $profile;
        $meta['mobility_cost_contract'] = self::CONTRACT;
        if ($snapshotNow && !empty($profile['enabled'])) {
            $profile['_source'] = 'route_override';
            $meta['mobility_cost_snapshot'] = self::snapshotPayload($profile, 'route_override', $actorUserId);
        }
        $ok = $wpdb->update($table, ['meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id' => $routeId], ['%s'], ['%d']);
        if ($ok === false) return false;
        $storedRaw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $routeId));
        $storedMeta = self::decodeMeta((string)$storedRaw);
        $storedOverride = is_array($storedMeta['mobility_cost_override'] ?? null) ? (array)$storedMeta['mobility_cost_override'] : [];
        return self::profilesEquivalent($profile, $storedOverride);
    }

    public static function backfillRoutes(int $projectId, int $ownerUserId, string $dateFrom, string $dateTo, bool $includeCompleted = false, bool $forceExistingSnapshots = false, int $actorUserId = 0): array {
        global $wpdb;
        if ($projectId <= 0) return ['processed'=>0,'updated'=>0,'skipped'=>0,'errors'=>1];
        $table = $wpdb->prefix . 'routespro_routes';
        $where = ['project_id=%d'];
        $args = [$projectId];
        if ($ownerUserId > 0) { $where[] = 'owner_user_id=%d'; $args[] = $ownerUserId; }
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'date >= %s'; $args[] = $dateFrom; }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'date <= %s'; $args[] = $dateTo; }
        if (!$includeCompleted) $where[] = "status NOT IN ('completed','done','canceled','cancelled')";
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY date ASC,id ASC LIMIT 5000', ...$args)) ?: [];
        $out = ['processed'=>0,'updated'=>0,'skipped'=>0,'errors'=>0];
        foreach ($ids as $routeId) {
            $out['processed']++;
            $result = self::ensureRouteSnapshot((int)$routeId, $forceExistingSnapshots, $actorUserId);
            if (!empty($result['ok']) && empty($result['skipped'])) $out['updated']++;
            elseif (!empty($result['skipped'])) $out['skipped']++;
            else $out['errors']++;
        }
        return $out;
    }
}
