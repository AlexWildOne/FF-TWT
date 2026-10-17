<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Indice normalizado e reconstruivel para o ambito de acesso.
 *
 * As tabelas de negocio continuam a ser a fonte de verdade. O indice pode ser
 * apagado ou reconstruido sem perder atribuicoes e tem fallback integral para
 * o resolvedor legado em Permissions.
 */
class ScopeIndex {
    public const GENERATION_OPTION = 'fieldflow_scope_index_generation';
    private const CACHE_GROUP = 'fieldflow_scope_index';
    private const CACHE_TTL = 600;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_scope_memberships';
    }

    public static function stateTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_scope_index_state';
    }

    public static function tablesReady(): bool {
        global $wpdb;
        $memberships = self::table();
        $state = self::stateTable();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $memberships)) === $memberships
            && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $state)) === $state;
    }

    public static function enabled(): bool {
        if (defined('FIELDFLOW_SCOPE_INDEX_ENABLED')) return (bool)constant('FIELDFLOW_SCOPE_INDEX_ENABLED');
        return (bool)get_option('fieldflow_scope_index_enabled', 1);
    }

    public static function generation(): int {
        return max(1, absint(get_option(self::GENERATION_OPTION, 1)));
    }

    public static function invalidateAll(): void {
        $next = self::generation() + 1;
        update_option(self::GENERATION_OPTION, $next, false);
    }

    public static function invalidateUser(int $userId): void {
        global $wpdb;
        $userId = absint($userId);
        if (!$userId) return;
        wp_cache_delete(self::cacheKey($userId), self::CACHE_GROUP);
        if (version_compare((string)get_option('routespro_schema_version', '0.0.0'), '2.2.260', '>=')) {
            $wpdb->delete(self::stateTable(), ['user_id' => $userId], ['%d']);
        }
    }

    public static function invalidateUsers(array $userIds): void {
        $ids = [];
        foreach ($userIds as $userId) {
            $userId = absint($userId);
            if ($userId) $ids[$userId] = $userId;
        }
        foreach ($ids as $userId) self::invalidateUser($userId);
    }

    public static function routeUserIds(int $routeId): array {
        global $wpdb;
        $routeId = absint($routeId);
        if (!$routeId) return [];
        $px = $wpdb->prefix . 'routespro_';
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM (
                SELECT owner_user_id AS user_id FROM {$px}routes WHERE id=%d AND owner_user_id>0
                UNION
                SELECT user_id FROM {$px}assignments WHERE route_id=%d AND user_id>0
            ) users",
            $routeId,
            $routeId
        )) ?: [];
        return self::normalizeIds($ids);
    }

    public static function scopeForUser(int $userId): ?array {
        global $wpdb;
        $userId = absint($userId);
        if (!$userId || !self::enabled() || !self::tablesReady()) return null;
        $generation = self::generation();
        $cacheKey = self::cacheKey($userId, $generation);
        $found = false;
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP, false, $found);
        if ($found && is_array($cached)) return $cached;

        $indexedGeneration = absint($wpdb->get_var($wpdb->prepare(
            'SELECT generation FROM ' . self::stateTable() . ' WHERE user_id=%d LIMIT 1',
            $userId
        )));
        if ($indexedGeneration !== $generation && !self::rebuildUser($userId, $generation)) return null;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT scope_type,scope_id FROM ' . self::table() . ' WHERE user_id=%d AND is_active=1',
            $userId
        ), ARRAY_A);
        if (!is_array($rows)) return null;

        $scope = ['client_ids' => [], 'project_ids' => [], 'route_ids' => []];
        foreach ($rows as $row) {
            $id = absint($row['scope_id'] ?? 0);
            if (!$id) continue;
            if ($row['scope_type'] === 'client') $scope['client_ids'][$id] = $id;
            elseif ($row['scope_type'] === 'project') $scope['project_ids'][$id] = $id;
            elseif ($row['scope_type'] === 'route') $scope['route_ids'][$id] = $id;
        }
        foreach ($scope as $key => $ids) {
            ksort($ids);
            $scope[$key] = array_values($ids);
        }
        wp_cache_set($cacheKey, $scope, self::CACHE_GROUP, self::CACHE_TTL);
        return $scope;
    }

    public static function directProjectIdsForUser(int $userId): ?array {
        global $wpdb;
        $userId = absint($userId);
        if (!$userId || self::scopeForUser($userId) === null) return null;
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT scope_id FROM " . self::table() . "
             WHERE user_id=%d AND scope_type='project' AND is_active=1
             AND source_type IN ('project_meta','project_assignment','campaign_location','route_owner','route_assignment')
             ORDER BY scope_id ASC",
            $userId
        ));
        return is_array($rows) ? self::normalizeIds($rows) : null;
    }

    public static function rebuildUser(int $userId, ?int $generation = null): bool {
        global $wpdb;
        $userId = absint($userId);
        if (!$userId || !self::tablesReady()) return false;
        $generation = $generation ?: self::generation();
        $px = $wpdb->prefix . 'routespro_';
        $memberships = [];
        $clientIds = [];

        $clients = $wpdb->get_results("SELECT id,meta_json FROM {$px}clients", ARRAY_A) ?: [];
        foreach ($clients as $client) {
            $clientId = absint($client['id'] ?? 0);
            if ($clientId && in_array($userId, self::idsFromMeta($client['meta_json'] ?? ''), true)) {
                $clientIds[$clientId] = $clientId;
                self::addMembership($memberships, $userId, 'client', $clientId, 'client_meta', $clientId);
            }
        }

        $projects = $wpdb->get_results("SELECT id,client_id,meta_json FROM {$px}projects", ARRAY_A) ?: [];
        foreach ($projects as $project) {
            $projectId = absint($project['id'] ?? 0);
            $clientId = absint($project['client_id'] ?? 0);
            if (!$projectId) continue;
            $direct = in_array($userId, self::idsFromMeta($project['meta_json'] ?? ''), true);
            $inherits = $clientId > 0 && isset($clientIds[$clientId]);
            if ($direct) self::addMembership($memberships, $userId, 'project', $projectId, 'project_meta', $projectId);
            elseif ($inherits) self::addMembership($memberships, $userId, 'project', $projectId, 'client_inheritance', $clientId);
            if (($direct || $inherits) && $clientId) self::addMembership($memberships, $userId, 'client', $clientId, $direct ? 'project_meta' : 'client_meta', $projectId);
        }

        $projectAssignments = $wpdb->get_results($wpdb->prepare(
            "SELECT pa.project_id,p.client_id FROM {$px}project_assignments pa
             INNER JOIN {$px}projects p ON p.id=pa.project_id
             WHERE pa.user_id=%d AND pa.is_active=1",
            $userId
        ), ARRAY_A) ?: [];
        foreach ($projectAssignments as $row) {
            $projectId = absint($row['project_id'] ?? 0);
            $clientId = absint($row['client_id'] ?? 0);
            if ($projectId) self::addMembership($memberships, $userId, 'project', $projectId, 'project_assignment', $projectId);
            if ($clientId) self::addMembership($memberships, $userId, 'client', $clientId, 'project_assignment', $projectId);
        }

        // 9BB predecessor marker: cl.assigned_to=%d AND cl.is_active=1 AND cl.status='active'
        $campaignLocations = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT cl.project_id,p.client_id FROM {$px}campaign_locations cl
             INNER JOIN {$px}projects p ON p.id=cl.project_id
             WHERE cl.assigned_to=%d",
            $userId
        ), ARRAY_A) ?: [];
        foreach ($campaignLocations as $row) {
            $projectId = absint($row['project_id'] ?? 0);
            $clientId = absint($row['client_id'] ?? 0);
            if ($projectId) self::addMembership($memberships, $userId, 'project', $projectId, 'campaign_location', $projectId);
            if ($clientId) self::addMembership($memberships, $userId, 'client', $clientId, 'campaign_location', $projectId);
        }

        $ownedRoutes = $wpdb->get_results($wpdb->prepare(
            "SELECT id,client_id,project_id FROM {$px}routes WHERE owner_user_id=%d",
            $userId
        ), ARRAY_A) ?: [];
        foreach ($ownedRoutes as $row) self::addRouteScope($memberships, $userId, $row, 'route_owner');

        $assignedRoutes = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id,r.client_id,r.project_id FROM {$px}assignments a
             INNER JOIN {$px}routes r ON r.id=a.route_id
             WHERE a.user_id=%d AND a.is_active=1",
            $userId
        ), ARRAY_A) ?: [];
        foreach ($assignedRoutes as $row) self::addRouteScope($memberships, $userId, $row, 'route_assignment');

        $wpdb->query('START TRANSACTION');
        $ok = $wpdb->delete(self::table(), ['user_id' => $userId], ['%d']) !== false;
        if ($ok) {
            foreach ($memberships as $row) {
                $inserted = $wpdb->insert(self::table(), $row, ['%d','%s','%d','%s','%d','%d','%s','%s']);
                if ($inserted === false) { $ok = false; break; }
            }
        }
        if ($ok) {
            $now = current_time('mysql');
            $sql = $wpdb->prepare(
                'INSERT INTO ' . self::stateTable() . ' (user_id,generation,indexed_at) VALUES (%d,%d,%s)
                 ON DUPLICATE KEY UPDATE generation=VALUES(generation),indexed_at=VALUES(indexed_at)',
                $userId,
                $generation,
                $now
            );
            $ok = $wpdb->query($sql) !== false;
        }
        $wpdb->query($ok ? 'COMMIT' : 'ROLLBACK');
        if ($ok) wp_cache_delete(self::cacheKey($userId, $generation), self::CACHE_GROUP);
        return $ok;
    }

    public static function status(): array {
        global $wpdb;
        $ready = self::tablesReady();
        return [
            'enabled' => self::enabled(),
            'tables_ready' => $ready,
            'generation' => self::generation(),
            'indexed_users' => $ready ? (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . self::stateTable()) : 0,
            'memberships' => $ready ? (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_active=1') : 0,
            'fallback_available' => true,
        ];
    }

    private static function addRouteScope(array &$memberships, int $userId, array $row, string $source): void {
        $routeId = absint($row['id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        $clientId = absint($row['client_id'] ?? 0);
        if ($routeId) self::addMembership($memberships, $userId, 'route', $routeId, $source, $routeId);
        if ($projectId) self::addMembership($memberships, $userId, 'project', $projectId, $source, $routeId);
        if ($clientId) self::addMembership($memberships, $userId, 'client', $clientId, $source, $routeId);
    }

    private static function addMembership(array &$memberships, int $userId, string $scopeType, int $scopeId, string $sourceType, int $sourceId): void {
        if (!$userId || !$scopeId) return;
        $key = implode('|', [$userId, $scopeType, $scopeId, $sourceType, $sourceId]);
        $now = current_time('mysql');
        $memberships[$key] = [
            'user_id' => $userId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private static function idsFromMeta($json): array {
        if (is_array($json)) $meta = $json;
        else {
            $meta = is_string($json) && trim($json) !== '' ? json_decode($json, true) : [];
            if (!is_array($meta)) $meta = [];
        }
        $values = [];
        foreach (['associated_user_ids','user_ids','users','assigned_users'] as $key) {
            $candidate = $meta[$key] ?? [];
            if (is_string($candidate)) $candidate = preg_split('/\s*,\s*/', trim($candidate));
            if (!is_array($candidate)) continue;
            foreach ($candidate as $value) {
                if (is_array($value)) $value = $value['user_id'] ?? ($value['id'] ?? 0);
                $id = absint($value);
                if ($id) $values[$id] = $id;
            }
        }
        return array_values($values);
    }

    private static function normalizeIds(array $values): array {
        $ids = [];
        foreach ($values as $value) {
            $id = absint($value);
            if ($id) $ids[$id] = $id;
        }
        ksort($ids);
        return array_values($ids);
    }

    private static function cacheKey(int $userId, ?int $generation = null): string {
        return 'scope_' . ($generation ?: self::generation()) . '_' . absint($userId);
    }
}
