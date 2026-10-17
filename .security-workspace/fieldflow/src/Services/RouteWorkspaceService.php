<?php
namespace RoutesPro\Services;

use WP_Error;

if (!defined('ABSPATH')) exit;

/**
 * Versioned route-planning workspace.
 *
 * Stores compact, bounded snapshots inside route meta so planning edits can be
 * reviewed and restored without introducing a new table or touching reports.
 */
final class RouteWorkspaceService {
    public const VERSION = 'fieldflow_route_workspace_v1';
    public const HISTORY_KEY = 'route_workspace_history';
    public const HISTORY_LIMIT = 12;

    public static function editableState(int $routeId, array $route = []): array {
        return RouteOptimizationService::routeSafety($routeId, $route);
    }

    /** @return array|WP_Error */
    public static function captureCurrent(int $routeId) {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $routeId = absint($routeId);
        if ($routeId <= 0) return new WP_Error('fieldflow_workspace_route_required', 'Rota inválida.', ['status' => 400]);

        $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A) ?: [];
        if (!$route) return new WP_Error('fieldflow_workspace_route_missing', 'A rota já não existe.', ['status' => 404]);

        $meta = self::decodeMeta($route['meta_json'] ?? '');
        unset($meta[self::HISTORY_KEY]);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT rs.*
             FROM {$px}route_stops rs
             WHERE rs.route_id=%d
             ORDER BY rs.seq ASC, rs.id ASC",
            $routeId
        ), ARRAY_A) ?: [];

        $stops = [];
        foreach ($rows as $row) {
            $stops[] = [
                'route_stop_id' => absint($row['id'] ?? 0),
                'location_id' => absint($row['location_id'] ?? 0),
                'seq' => (int)($row['seq'] ?? 0),
                'status' => sanitize_key((string)($row['status'] ?? 'pending')),
                'planned_arrival' => self::nullableDateTime($row['planned_arrival'] ?? null),
                'planned_departure' => self::nullableDateTime($row['planned_departure'] ?? null),
                'duration_s' => max(0, (int)($row['duration_s'] ?? 0)),
                'meta_json' => self::decodeMeta($row['meta_json'] ?? ''),
            ];
        }

        return [
            'workspace_version' => self::VERSION,
            'captured_at' => current_time('mysql'),
            'route' => [
                'id' => $routeId,
                'client_id' => absint($route['client_id'] ?? 0),
                'project_id' => absint($route['project_id'] ?? 0),
                'date' => sanitize_text_field((string)($route['date'] ?? '')),
                'status' => sanitize_key((string)($route['status'] ?? 'draft')),
                'owner_user_id' => absint($route['owner_user_id'] ?? 0),
                'meta' => $meta,
            ],
            'stops' => $stops,
        ];
    }

    public static function appendRevision(array $meta, array $snapshot, string $action = 'save', int $userId = 0, string $label = ''): array {
        $history = is_array($meta[self::HISTORY_KEY] ?? null) ? array_values($meta[self::HISTORY_KEY]) : [];
        $capturedAt = sanitize_text_field((string)($snapshot['captured_at'] ?? current_time('mysql')));
        $seed = $capturedAt . '|' . wp_json_encode($snapshot['route'] ?? []) . '|' . wp_json_encode($snapshot['stops'] ?? []) . '|' . wp_rand();
        $history[] = [
            'id' => substr(hash('sha256', $seed), 0, 16),
            'action' => sanitize_key($action) ?: 'save',
            'label' => sanitize_text_field($label),
            'created_at' => $capturedAt,
            'created_by' => $userId > 0 ? $userId : get_current_user_id(),
            'route_status' => sanitize_key((string)($snapshot['route']['status'] ?? 'draft')),
            'stop_count' => count((array)($snapshot['stops'] ?? [])),
            'snapshot' => $snapshot,
        ];
        $meta[self::HISTORY_KEY] = array_slice($history, -self::HISTORY_LIMIT);
        $meta['route_workspace_version'] = self::VERSION;
        $meta['route_workspace_last_saved_at'] = current_time('mysql');
        $meta['route_workspace_last_saved_by'] = $userId > 0 ? $userId : get_current_user_id();
        return $meta;
    }

    public static function historyFromMeta(array $meta): array {
        $history = is_array($meta[self::HISTORY_KEY] ?? null) ? array_values($meta[self::HISTORY_KEY]) : [];
        return array_reverse($history);
    }

    /** @return array|WP_Error */
    public static function restore(int $routeId, string $revisionId, int $userId = 0) {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $routeId = absint($routeId);
        $revisionId = sanitize_text_field($revisionId);
        $userId = $userId > 0 ? $userId : get_current_user_id();
        if ($routeId <= 0 || $revisionId === '') return new WP_Error('fieldflow_workspace_restore_invalid', 'Versão inválida.', ['status' => 400]);

        $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A) ?: [];
        if (!$route) return new WP_Error('fieldflow_workspace_route_missing', 'A rota já não existe.', ['status' => 404]);
        $safety = self::editableState($routeId, $route);
        if (empty($safety['editable'])) {
            return new WP_Error('fieldflow_workspace_route_locked', 'A rota já tem atividade operacional e não pode ser restaurada.', ['status' => 409]);
        }

        $currentMeta = self::decodeMeta($route['meta_json'] ?? '');
        $history = is_array($currentMeta[self::HISTORY_KEY] ?? null) ? array_values($currentMeta[self::HISTORY_KEY]) : [];
        $revision = null;
        foreach ($history as $item) {
            if (hash_equals((string)($item['id'] ?? ''), $revisionId)) { $revision = $item; break; }
        }
        if (!$revision || !is_array($revision['snapshot'] ?? null)) {
            return new WP_Error('fieldflow_workspace_revision_missing', 'A versão selecionada já não está disponível.', ['status' => 404]);
        }

        $before = self::captureCurrent($routeId);
        if (is_wp_error($before)) return $before;
        $snapshot = (array)$revision['snapshot'];
        $snapshotRoute = is_array($snapshot['route'] ?? null) ? (array)$snapshot['route'] : [];
        $snapshotMeta = is_array($snapshotRoute['meta'] ?? null) ? (array)$snapshotRoute['meta'] : [];
        // Restoring a planning revision must not roll back operational audit
        // trails or client incident notifications created after that revision.
        foreach (['route_exception', 'route_edit_history'] as $preservedKey) {
            if (array_key_exists($preservedKey, $currentMeta)) $snapshotMeta[$preservedKey] = $currentMeta[$preservedKey];
        }
        $snapshotMeta[self::HISTORY_KEY] = $history;
        $snapshotMeta = self::appendRevision($snapshotMeta, $before, 'before_restore', $userId, 'Estado anterior ao restauro');
        $snapshotMeta['route_workspace_restored_revision'] = $revisionId;
        $snapshotMeta['route_workspace_restored_at'] = current_time('mysql');
        $snapshotMeta['route_workspace_restored_by'] = $userId;

        $plannedStops = [];
        foreach (array_values((array)($snapshot['stops'] ?? [])) as $index => $stop) {
            if (!is_array($stop)) continue;
            $locationId = absint($stop['location_id'] ?? 0);
            if ($locationId <= 0) continue;
            $plannedStops[] = [
                'route_stop_id' => absint($stop['route_stop_id'] ?? 0),
                'location_id' => $locationId,
                'seq' => isset($stop['seq']) ? max(0, (int)$stop['seq']) : $index,
                'planned_arrival' => self::nullableDateTime($stop['planned_arrival'] ?? null),
                'planned_departure' => self::nullableDateTime($stop['planned_departure'] ?? null),
                'duration_s' => max(0, (int)($stop['duration_s'] ?? 0)),
                'meta_json' => is_array($stop['meta_json'] ?? null) ? (array)$stop['meta_json'] : [],
            ];
        }
        if (!$plannedStops) return new WP_Error('fieldflow_workspace_empty_revision', 'A versão selecionada não contém lojas.', ['status' => 400]);

        $status = sanitize_key((string)($snapshotRoute['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'planned'], true)) $status = 'draft';
        $date = sanitize_text_field((string)($snapshotRoute['date'] ?? $route['date'] ?? current_time('Y-m-d')));
        $owner = absint($snapshotRoute['owner_user_id'] ?? $route['owner_user_id'] ?? 0);
        $client = absint($snapshotRoute['client_id'] ?? $route['client_id'] ?? 0);
        $project = absint($snapshotRoute['project_id'] ?? $route['project_id'] ?? 0);

        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('fieldflow_workspace_transaction', 'Não foi possível iniciar o restauro seguro.', ['status' => 500]);
        }
        try {
            $updated = $wpdb->update($px . 'routes', [
                'client_id' => $client,
                'project_id' => $project ?: null,
                'date' => $date,
                'status' => $status,
                'owner_user_id' => $owner ?: null,
                'meta_json' => wp_json_encode($snapshotMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ], ['id' => $routeId], ['%d', '%d', '%s', '%s', '%d', '%s'], ['%d']);
            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_workspace_route_update', 'Não foi possível restaurar os dados da rota.', ['status' => 500]);
            }
            $sync = RoutePlanSyncService::sync($routeId, $plannedStops);
            if (is_wp_error($sync)) { $wpdb->query('ROLLBACK'); return $sync; }
            $progress = RouteProgressService::sync($routeId, true);
            if (is_wp_error($progress)) { $wpdb->query('ROLLBACK'); return $progress; }
            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_workspace_commit', 'Não foi possível confirmar o restauro.', ['status' => 500]);
            }
            if (class_exists('\\RoutesPro\\Support\\AssignmentMatrix')) {
                \RoutesPro\Support\AssignmentMatrix::sync_route_owner_assignment($routeId, $owner, 'owner');
            }
            RouteOptimizationService::invalidateUserRouteCache($owner);
            if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
                $after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A) ?: [];
                $context = [
                    'source' => 'backoffice', 'entity_type' => 'route', 'entity_id' => $routeId,
                    'route_id' => $routeId, 'client_id' => $client, 'project_id' => $project,
                    'actor_user_id' => $userId,
                ];
                \RoutesPro\Core\AuditLog::record('route.workspace_restored', 'route', $routeId, $route, $after, $context, [
                    'revision_id' => $revisionId,
                    'sync' => $sync,
                ]);
                \RoutesPro\Core\EventBus::emit('route.workspace_restored', ['route' => $after, 'revision_id' => $revisionId], $context, 'backoffice');
            }
            return ['ok' => true, 'route_id' => $routeId, 'revision_id' => $revisionId, 'sync' => $sync, 'progress' => $progress];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('fieldflow_workspace_restore_failure', 'Não foi possível restaurar a versão selecionada.', ['status' => 500]);
        }
    }

    private static function decodeMeta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function nullableDateTime($value): ?string {
        $value = is_scalar($value) ? trim((string)$value) : '';
        return ($value !== '' && strtotime($value)) ? $value : null;
    }
}
