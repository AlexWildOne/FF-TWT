<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Operational Endpoint Source of Truth.
 *
 * Canonical persistence reuses projects.meta_json, with no schema change:
 * - route_endpoint_default
 * - route_endpoint_profiles[user_id]
 *
 * Resolution order:
 * explicit route/day -> trusted route endpoint -> operator profile -> campaign default
 * -> missing. Legacy defaults enter this chain only after explicit migration into canonical project metadata.
 *
 * A first/last PDV is never manufactured as an implicit endpoint.
 */
final class RouteEndpointProfileService {
    public const VERSION = 'fieldflow_route_endpoint_profile_v1';
    public const CONTRACT = 'fieldflow-operational-endpoint-source-of-truth-v1';
    private static array $cache = [];

    public static function emptyProfile(): array {
        return [
            'enabled' => 0,
            'start_point' => [],
            'end_point' => [],
            'return_to_start' => 0,
            'updated_at' => '',
            'updated_by' => 0,
            'source' => 'none',
        ];
    }

    private static function decodeMeta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function cleanPoint(array $point, string $role, string $provenance, string $countryCode = 'PT'): array {
        $point['provenance'] = sanitize_key((string)($point['provenance'] ?? $provenance)) ?: $provenance;
        $resolved = RouteEndpointResolverService::resolve($point, $role, $point['provenance'], [
            'allow_matches_stop' => !empty($point['allow_matches_stop']),
            'force_address_authority' => false,
            'discard_stale_on_failure' => false,
            'country_code' => $countryCode,
        ]);
        return RouteEndpointResolverService::publicPoint($resolved);
    }

    public static function normalizeProfile(array $raw, string $source = 'profile', string $countryCode = 'PT'): array {
        $profile = array_merge(self::emptyProfile(), $raw);
        $enabled = !empty($profile['enabled']);
        $returnToStart = !empty($profile['return_to_start']);
        $startRaw = is_array($profile['start_point'] ?? null) ? (array)$profile['start_point'] : [];
        $endRaw = is_array($profile['end_point'] ?? null) ? (array)$profile['end_point'] : [];
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::normalizeCountryCode($countryCode) : strtoupper($countryCode ?: 'PT');
        $start = self::cleanPoint($startRaw, 'start', $source, $countryCode);
        $end = self::cleanPoint($endRaw, 'end', $source, $countryCode);
        if ($returnToStart && RouteEndpointResolverService::hasRoutingData($start)) {
            $end = $start;
            $end['provenance'] = 'return_to_start';
        }
        return [
            'enabled' => $enabled ? 1 : 0,
            'start_point' => $start,
            'end_point' => $end,
            'return_to_start' => $returnToStart ? 1 : 0,
            'updated_at' => sanitize_text_field((string)($profile['updated_at'] ?? '')),
            'updated_by' => absint($profile['updated_by'] ?? 0),
            'source' => sanitize_key((string)($profile['source'] ?? $source)) ?: $source,
            'country_code' => $countryCode,
        ];
    }

    public static function profileIssues(array $profile): array {
        $profile = self::normalizeProfile($profile, (string)($profile['source'] ?? 'profile'), (string)($profile['country_code'] ?? 'PT'));
        if (empty($profile['enabled'])) return [];
        $issues = [];
        if (!RouteEndpointResolverService::hasRoutingData((array)$profile['start_point'])) $issues[] = 'Indica uma partida válida.';
        if (!RouteEndpointResolverService::hasRoutingData((array)$profile['end_point'])) $issues[] = 'Indica uma chegada válida.';
        return $issues;
    }

    public static function getProjectConfig(int $projectId): array {
        global $wpdb;
        $projectId = absint($projectId);
        if ($projectId <= 0) return ['default'=>self::emptyProfile(),'profiles'=>[]];
        if (isset(self::$cache[$projectId])) return self::$cache[$projectId];
        $table = $wpdb->prefix . 'routespro_projects';
        $raw = $wpdb->get_var($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $projectId));
        $meta = self::decodeMeta((string)$raw);
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::projectCountryCode($projectId) : 'PT';
        $profiles = [];
        foreach ((array)($meta['route_endpoint_profiles'] ?? []) as $userId => $profile) {
            $uid = absint($userId);
            if ($uid <= 0 || !is_array($profile)) continue;
            $profiles[$uid] = self::normalizeProfile($profile, 'operator_profile', $countryCode);
        }
        ksort($profiles, SORT_NUMERIC);
        return self::$cache[$projectId] = [
            'default' => self::normalizeProfile(is_array($meta['route_endpoint_default'] ?? null) ? $meta['route_endpoint_default'] : [], 'campaign_default', $countryCode),
            'profiles' => $profiles,
            'routing_country_code' => $countryCode,
            'legacy_migrated_at' => sanitize_text_field((string)($meta['route_endpoint_legacy_migrated_at'] ?? '')),
        ];
    }

    public static function saveProjectDefault(int $projectId, array $profile, int $actorUserId = 0): bool {
        return self::saveInternal($projectId, 0, $profile, $actorUserId);
    }

    public static function saveProjectUserProfile(int $projectId, int $userId, array $profile, int $actorUserId = 0): bool {
        if ($userId <= 0) return false;
        return self::saveInternal($projectId, $userId, $profile, $actorUserId);
    }

    private static function resolveProfileForSave(array $profile, string $source, string $countryCode = 'PT'): array {
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::normalizeCountryCode($countryCode) : strtoupper($countryCode ?: 'PT');
        $profile = self::normalizeProfile($profile, $source, $countryCode);
        if (empty($profile['enabled'])) return $profile;
        $startRaw = (array)$profile['start_point'];
        $endRaw = (array)$profile['end_point'];
        $start = RouteEndpointResolverService::resolve($startRaw, 'start', $source, [
            'allow_matches_stop' => !empty($startRaw['allow_matches_stop']),
            'force_address_authority' => true,
            'discard_stale_on_failure' => false,
            'country_code' => $countryCode,
        ]);
        if (!empty($profile['return_to_start']) && RouteEndpointResolverService::hasRoutingData($start)) {
            $end = $start;
            $end['provenance'] = 'return_to_start';
        } else {
            $end = RouteEndpointResolverService::resolve($endRaw, 'end', $source, [
                'allow_matches_stop' => !empty($endRaw['allow_matches_stop']),
                'force_address_authority' => true,
                'discard_stale_on_failure' => false,
                'country_code' => $countryCode,
            ]);
        }
        $profile['start_point'] = RouteEndpointResolverService::publicPoint($start);
        $profile['end_point'] = RouteEndpointResolverService::publicPoint($end);
        $profile['source'] = $source;
        return $profile;
    }

    private static function equivalent(array $a, array $b): bool {
        $a = self::normalizeProfile($a, (string)($a['source'] ?? 'profile'), (string)($a['country_code'] ?? 'PT'));
        $b = self::normalizeProfile($b, (string)($b['source'] ?? 'profile'), (string)($b['country_code'] ?? 'PT'));
        foreach (['updated_at','updated_by'] as $key) { unset($a[$key], $b[$key]); }
        return hash_equals((string)wp_json_encode($a), (string)wp_json_encode($b));
    }

    private static function saveInternal(int $projectId, int $userId, array $profile, int $actorUserId): bool {
        global $wpdb;
        $projectId = absint($projectId);
        if ($projectId <= 0) return false;
        $table = $wpdb->prefix . 'routespro_projects';
        $row = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $projectId), ARRAY_A);
        if (!$row) return false;
        $meta = self::decodeMeta((string)($row['meta_json'] ?? ''));
        $source = $userId > 0 ? 'operator_profile' : 'campaign_default';
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::projectCountryCode($projectId) : 'PT';
        $profile = self::resolveProfileForSave($profile, $source, $countryCode);
        if (self::profileIssues($profile)) return false;
        $profile['updated_at'] = current_time('mysql');
        $profile['updated_by'] = $actorUserId > 0 ? $actorUserId : get_current_user_id();
        if ($userId > 0) {
            $profiles = is_array($meta['route_endpoint_profiles'] ?? null) ? $meta['route_endpoint_profiles'] : [];
            $profiles[(string)$userId] = $profile;
            $meta['route_endpoint_profiles'] = $profiles;
        } else {
            $meta['route_endpoint_default'] = $profile;
        }
        $meta['route_endpoint_contract'] = self::CONTRACT;
        $ok = $wpdb->update($table, ['meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id'=>$projectId], ['%s'], ['%d']);
        if ($ok === false) return false;
        unset(self::$cache[$projectId]);
        $readback = self::getProjectConfig($projectId);
        $stored = $userId > 0 ? (array)($readback['profiles'][$userId] ?? []) : (array)($readback['default'] ?? []);
        return self::equivalent($profile, $stored);
    }

    public static function effectiveProfile(int $projectId, int $ownerUserId = 0, int $clientId = 0): array {
        $cfg = self::getProjectConfig($projectId);
        if ($ownerUserId > 0 && !empty($cfg['profiles'][$ownerUserId]['enabled'])) {
            $p = self::normalizeProfile((array)$cfg['profiles'][$ownerUserId], 'operator_profile', (string)($cfg['routing_country_code'] ?? 'PT'));
            $p['_source'] = 'operator_profile';
            return $p;
        }
        if (!empty($cfg['default']['enabled'])) {
            $p = self::normalizeProfile((array)$cfg['default'], 'campaign_default', (string)($cfg['routing_country_code'] ?? 'PT'));
            $p['_source'] = 'campaign_default';
            return $p;
        }
        // Legacy route defaults are never consumed implicitly. They may contain
        // endpoints manufactured by older first/last-PDV fallbacks. An admin
        // must migrate them explicitly before they can become canonical master data.
        $none = self::emptyProfile();
        $none['_source'] = 'none';
        return $none;
    }

    public static function legacyDefault(int $clientId, int $projectId, int $ownerUserId = 0): array {
        global $wpdb;
        if ($projectId <= 0) return self::emptyProfile();
        if ($clientId <= 0) {
            $clientId = absint($wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d", $projectId)));
        }
        $all = get_option('routespro_route_defaults', []);
        if (!is_array($all)) return self::emptyProfile();
        $exact = $clientId . '|' . $projectId . '|' . $ownerUserId;
        $fallback = $clientId . '|' . $projectId . '|0';
        $raw = is_array($all[$exact] ?? null) ? (array)$all[$exact] : (is_array($all[$fallback] ?? null) ? (array)$all[$fallback] : []);
        if (!$raw) return self::emptyProfile();
        $profile = [
            'enabled' => 1,
            'start_point' => is_array($raw['start_point'] ?? null) ? (array)$raw['start_point'] : [],
            'end_point' => is_array($raw['end_point'] ?? null) ? (array)$raw['end_point'] : [],
            'return_to_start' => !empty($raw['return_to_start']) ? 1 : 0,
            'source' => 'legacy_route_default',
        ];
        $profile = self::normalizeProfile($profile, 'legacy_route_default');
        if (self::profileIssues($profile)) return self::emptyProfile();
        return $profile;
    }

    public static function migrateLegacyDefaults(int $projectId, int $clientId = 0, int $actorUserId = 0): array {
        global $wpdb;
        $result = ['migrated_default'=>0,'migrated_profiles'=>0,'skipped'=>0,'errors'=>0];
        if ($projectId <= 0) { $result['errors']++; return $result; }
        if ($clientId <= 0) $clientId = absint($wpdb->get_var($wpdb->prepare("SELECT client_id FROM {$wpdb->prefix}routespro_projects WHERE id=%d", $projectId)));
        $table = $wpdb->prefix . 'routespro_projects';
        $row = $wpdb->get_row($wpdb->prepare("SELECT meta_json FROM {$table} WHERE id=%d", $projectId), ARRAY_A);
        if (!$row) { $result['errors']++; return $result; }
        $meta = self::decodeMeta((string)($row['meta_json'] ?? ''));
        $all = get_option('routespro_route_defaults', []);
        if (!is_array($all)) $all = [];
        $profiles = is_array($meta['route_endpoint_profiles'] ?? null) ? $meta['route_endpoint_profiles'] : [];
        $changed = false;
        foreach ($all as $key => $raw) {
            if (!is_array($raw)) continue;
            $parts = explode('|', (string)$key);
            if (count($parts) !== 3 || (int)$parts[0] !== $clientId || (int)$parts[1] !== $projectId) continue;
            $uid = absint($parts[2]);
            $profile = self::normalizeProfile([
                'enabled'=>1,
                'start_point'=>(array)($raw['start_point'] ?? []),
                'end_point'=>(array)($raw['end_point'] ?? []),
                'return_to_start'=>!empty($raw['return_to_start']) ? 1 : 0,
            ], $uid > 0 ? 'legacy_operator_default' : 'legacy_campaign_default');
            if (self::profileIssues($profile)) { $result['skipped']++; continue; }
            $profile['updated_at'] = current_time('mysql');
            $profile['updated_by'] = $actorUserId > 0 ? $actorUserId : get_current_user_id();
            if ($uid > 0) {
                if (empty($profiles[(string)$uid])) {
                    $profiles[(string)$uid] = $profile;
                    $result['migrated_profiles']++;
                    $changed = true;
                } else $result['skipped']++;
            } else {
                if (empty($meta['route_endpoint_default'])) {
                    $meta['route_endpoint_default'] = $profile;
                    $result['migrated_default']++;
                    $changed = true;
                } else $result['skipped']++;
            }
        }
        if ($changed) {
            $meta['route_endpoint_profiles'] = $profiles;
            $meta['route_endpoint_contract'] = self::CONTRACT;
            $meta['route_endpoint_legacy_migrated_at'] = current_time('mysql');
            $meta['route_endpoint_legacy_migrated_by'] = $actorUserId > 0 ? $actorUserId : get_current_user_id();
            $ok = $wpdb->update($table, ['meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)], ['id'=>$projectId], ['%s'], ['%d']);
            if ($ok === false) $result['errors']++;
        }
        unset(self::$cache[$projectId]);
        return $result;
    }

    private static function isTrustedRouteCandidate(array $point, array $edgeStop, string $role): bool {
        if (!RouteEndpointResolverService::hasRoutingData($point)) return false;
        if (!empty($point['allow_matches_stop']) || !empty($point['_allow_matches_stop'])) return true;
        // A route-level endpoint that collapses onto the edge PDV is never
        // trusted only because an older release labelled it "manual". This is
        // the exact contamination pattern created by historical first/last-PDV
        // fallbacks. Genuine zero-access/return cases require explicit consent.
        if (RouteEndpointResolverService::hasRoutingData($edgeStop) && RouteEndpointResolverService::pointsCoincide($point, $edgeStop)) return false;
        return true;
    }

    public static function resolveForRoute(int $clientId, int $projectId, int $ownerUserId, array $routeMeta = [], array $explicitStart = [], array $explicitEnd = [], array $firstStop = [], array $lastStop = [], array $options = []): array {
        $returnToStart = !empty($options['return_to_start']) || !empty($routeMeta['return_to_start']);
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService')
            ? RoutingGeographyService::normalizeCountryCode((string)($options['country_code'] ?? $routeMeta['routing_country_code'] ?? RoutingGeographyService::projectCountryCode($projectId)))
            : strtoupper((string)($options['country_code'] ?? $routeMeta['routing_country_code'] ?? 'PT'));
        $routeStart = is_array($routeMeta['start_point'] ?? null) ? (array)$routeMeta['start_point'] : [];
        $routeEnd = is_array($routeMeta['end_point'] ?? null) ? (array)$routeMeta['end_point'] : [];
        $start = [];
        $end = [];
        $startSource = 'missing';
        $endSource = 'missing';

        foreach ([['point'=>$explicitStart,'source'=>'explicit'],['point'=>$routeStart,'source'=>'route']] as $candidate) {
            if (!self::isTrustedRouteCandidate((array)$candidate['point'], $firstStop, 'start')) continue;
            $source = sanitize_key((string)($candidate['point']['provenance'] ?? ($candidate['source']==='explicit'?'manual':'route_existing'))) ?: 'route_existing';
            $start = RouteEndpointResolverService::resolve((array)$candidate['point'], 'start', $source, ['allow_matches_stop'=>!empty($candidate['point']['allow_matches_stop']),'country_code'=>$countryCode]);
            $startSource = $candidate['source'];
            break;
        }
        foreach ([['point'=>$explicitEnd,'source'=>'explicit'],['point'=>$routeEnd,'source'=>'route']] as $candidate) {
            if (!self::isTrustedRouteCandidate((array)$candidate['point'], $lastStop, 'end')) continue;
            $source = sanitize_key((string)($candidate['point']['provenance'] ?? ($candidate['source']==='explicit'?'manual':'route_existing'))) ?: 'route_existing';
            $end = RouteEndpointResolverService::resolve((array)$candidate['point'], 'end', $source, ['allow_matches_stop'=>!empty($candidate['point']['allow_matches_stop']),'country_code'=>$countryCode]);
            $endSource = $candidate['source'];
            break;
        }

        $profile = self::effectiveProfile($projectId, $ownerUserId, $clientId);
        if (!RouteEndpointResolverService::hasRoutingData($start) && !empty($profile['enabled'])) {
            $start = RouteEndpointResolverService::resolve((array)$profile['start_point'], 'start', (string)($profile['_source'] ?? 'profile'), ['country_code'=>$countryCode]);
            $startSource = (string)($profile['_source'] ?? 'profile');
        }
        if ($returnToStart && RouteEndpointResolverService::hasRoutingData($start)) {
            $end = $start;
            $end['provenance'] = 'return_to_start';
            $endSource = 'return_to_start';
        } elseif (!RouteEndpointResolverService::hasRoutingData($end) && !empty($profile['enabled'])) {
            $end = RouteEndpointResolverService::resolve((array)$profile['end_point'], 'end', (string)($profile['_source'] ?? 'profile'), ['country_code'=>$countryCode]);
            $endSource = (string)($profile['_source'] ?? 'profile');
        }
        if (!$returnToStart && !empty($profile['return_to_start']) && RouteEndpointResolverService::hasRoutingData($start) && !RouteEndpointResolverService::hasRoutingData($explicitEnd) && !RouteEndpointResolverService::hasRoutingData($routeEnd)) {
            $returnToStart = true;
            $end = $start;
            $end['provenance'] = 'return_to_start';
            $endSource = 'return_to_start';
        }

        return [
            'start_point' => RouteEndpointResolverService::publicPoint($start),
            'end_point' => RouteEndpointResolverService::publicPoint($end),
            'return_to_start' => $returnToStart ? 1 : 0,
            'start_source' => $startSource,
            'end_source' => $endSource,
            'profile_source' => (string)($profile['_source'] ?? 'none'),
            'routing_country_code' => $countryCode,
            'contract' => self::CONTRACT,
        ];
    }

    public static function recoverHistoricalEndpoints(int $routeId, array $routeMeta, array $firstStop = [], array $lastStop = []): array {
        global $wpdb;
        $foundStart = [];
        $foundEnd = [];
        $source = 'none';
        $accept = static function(array $point, array $edge, string $role): bool {
            return self::isTrustedRouteCandidate($point, $edge, $role);
        };

        $history = is_array($routeMeta[RouteWorkspaceService::HISTORY_KEY] ?? null) ? array_reverse((array)$routeMeta[RouteWorkspaceService::HISTORY_KEY]) : [];
        foreach ($history as $revision) {
            $snapshotMeta = (array)($revision['snapshot']['route']['meta'] ?? []);
            $s = is_array($snapshotMeta['start_point'] ?? null) ? (array)$snapshotMeta['start_point'] : [];
            $e = is_array($snapshotMeta['end_point'] ?? null) ? (array)$snapshotMeta['end_point'] : [];
            if (!$foundStart && $accept($s, $firstStop, 'start')) $foundStart = $s;
            if (!$foundEnd && $accept($e, $lastStop, 'end')) $foundEnd = $e;
            if ($foundStart && $foundEnd) { $source = 'workspace_history'; break; }
        }

        if ((!$foundStart || !$foundEnd) && class_exists('\\RoutesPro\\Core\\AuditLog') && \RoutesPro\Core\AuditLog::exists()) {
            $rows = \RoutesPro\Core\AuditLog::recent(80, ['entity_type'=>'route','route_id'=>$routeId]);
            foreach ($rows as $row) {
                foreach (['before_json','after_json'] as $col) {
                    $payload = self::decodeMeta((string)($row[$col] ?? ''));
                    $meta = [];
                    if (is_array($payload['meta'] ?? null)) $meta = (array)$payload['meta'];
                    elseif (is_string($payload['meta_json'] ?? null)) $meta = self::decodeMeta((string)$payload['meta_json']);
                    elseif (is_array($payload['meta_json'] ?? null)) $meta = (array)$payload['meta_json'];
                    $s = is_array($meta['start_point'] ?? null) ? (array)$meta['start_point'] : [];
                    $e = is_array($meta['end_point'] ?? null) ? (array)$meta['end_point'] : [];
                    if (!$foundStart && $accept($s, $firstStop, 'start')) $foundStart = $s;
                    if (!$foundEnd && $accept($e, $lastStop, 'end')) $foundEnd = $e;
                    if ($foundStart && $foundEnd) { $source = 'audit_log'; break 2; }
                }
            }
        }
        return [
            'start_point' => $foundStart ? RouteEndpointResolverService::publicPoint(RouteEndpointResolverService::resolve($foundStart, 'start', $source ?: 'history')) : [],
            'end_point' => $foundEnd ? RouteEndpointResolverService::publicPoint(RouteEndpointResolverService::resolve($foundEnd, 'end', $source ?: 'history')) : [],
            'source' => $source,
        ];
    }
}
