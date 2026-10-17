<?php
namespace RoutesPro\Services;

use WP_Error;
use RoutesPro\Services\Planning\RouteCalculator;
use RoutesPro\Services\Planning\RoutingMatrixService;
use RoutesPro\Support\Permissions;
use RoutesPro\Support\TollEstimator;

if (!defined('ABSPATH')) exit;

/**
 * Canonical route optimisation service shared by BO, front, WebView and native apps.
 *
 * The service never trusts client-side ordering. It validates scope, builds the
 * same routing matrix used by the planner, compares the current sequence with
 * the optimised sequence and can safely persist only draft/planned routes with
 * no operational activity.
 */
final class RouteOptimizationService {
    // Historical 2.2.506 release-gate marker retained for package continuity.
    private const LEGACY_ENDPOINT_UNTRUSTED_ERROR = 'fieldflow_route_endpoints_untrusted';
    public const VERSION = 'fieldflow_live_route_optimization_v1';
    public const PLANNER_WARNING_CONTRACT = 'fieldflow-planner-free-warning-continuity-v1';
    public const MAX_STOPS = 8;

    /**
     * @return array|WP_Error
     */
    public static function preview(array $input, int $userId = 0) {
        return self::previewInternal($input, $userId, false);
    }

    /** Trusted internal entry point for resumable maintenance jobs. */
    public static function previewSystem(array $input) {
        return self::previewInternal($input, 0, true);
    }

    /**
     * @return array|WP_Error
     */
    private static function previewInternal(array $input, int $userId = 0, bool $trusted = false) {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';

        $userId = $userId > 0 ? $userId : get_current_user_id();
        $routeId = absint($input['route_id'] ?? 0);
        $clientId = absint($input['client_id'] ?? 0);
        $projectId = absint($input['project_id'] ?? 0);
        $locationIds = array_values(array_unique(array_filter(array_map('absint', (array)($input['location_ids'] ?? [])))));
        $manualLocked = !empty($input['manual_order_locked']);
        $lockedLocationIds = array_values(array_unique(array_filter(array_map('absint', (array)($input['locked_location_ids'] ?? [])))));
        $forceRefresh = !empty($input['force_refresh']);
        $mode = sanitize_key((string)($input['mode'] ?? 'hybrid'));
        if (!in_array($mode, ['hybrid', 'google', 'internal'], true)) $mode = 'hybrid';
        $plannerMode = !empty($input['planner_mode']) || sanitize_key((string)($input['planning_source'] ?? '')) === 'self_planner';
        $planningDate = sanitize_text_field((string)($input['date'] ?? ''));

        if (!$locationIds && $routeId > 0) {
            $locationIds = array_values(array_map('absint', $wpdb->get_col($wpdb->prepare(
                "SELECT location_id FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC",
                $routeId
            )) ?: []));
        }
        if (!$locationIds) {
            return new WP_Error('fieldflow_route_optimization_empty', 'Adiciona pelo menos uma loja para otimizar a rota.', ['status' => 400]);
        }
        if (count($locationIds) > self::MAX_STOPS) {
            return new WP_Error('fieldflow_route_optimization_limit', 'Cada rota pode ter no máximo 8 lojas.', ['status' => 400, 'limit' => self::MAX_STOPS]);
        }

        $route = [];
        $routeMeta = [];
        if ($routeId > 0) {
            $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A) ?: [];
            if (!$route) return new WP_Error('fieldflow_route_optimization_route_missing', 'A rota já não existe.', ['status' => 404]);
            if (!$trusted && !Permissions::can_access_route($routeId, $userId)) {
                return new WP_Error('fieldflow_route_optimization_forbidden', 'Não tens acesso a esta rota.', ['status' => 403]);
            }
            // An existing route is always authoritative for scope. Preview
            // callers may propose a sequence, but cannot move it to another
            // client or project by changing the request payload.
            $clientId = absint($route['client_id'] ?? 0);
            $projectId = absint($route['project_id'] ?? 0);
            $routeMeta = self::decodeMeta($route['meta_json'] ?? '');
            if (!array_key_exists('manual_order_locked', $input) && !empty($routeMeta['manual_order_locked'])) $manualLocked = true;
            if (!array_key_exists('locked_location_ids', $input)) {
                $lockedLocationIds = array_values(array_unique(array_filter(array_map('absint', (array)($routeMeta['position_locked_location_ids'] ?? [])))));
            }
        }

        if ($clientId > 0 || $projectId > 0) {
            $scope = $trusted ? true : Permissions::assert_scope_or_error($clientId, $projectId, $userId);
            if (is_wp_error($scope)) return $scope;
        }

        $access = $trusted ? true : self::validateLocations($locationIds, $projectId, $routeId, $userId, $plannerMode);
        if (is_wp_error($access)) return $access;

        $placeholders = implode(',', array_fill(0, count($locationIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,name,address,city,postal_code,lat,lng,service_time_min,window_start,window_end,meta_json
             FROM {$px}locations WHERE id IN ({$placeholders})",
            ...$locationIds
        ), ARRAY_A) ?: [];
        $lookup = [];
        foreach ($rows as $row) $lookup[(int)$row['id']] = self::normaliseLocation($row);

        $items = [];
        $missing = [];
        foreach ($locationIds as $locationId) {
            if (!isset($lookup[$locationId])) {
                $missing[] = $locationId;
                continue;
            }
            $items[] = $lookup[$locationId];
        }
        if ($missing) {
            return new WP_Error('fieldflow_route_optimization_locations_missing', 'Algumas lojas já não existem.', ['status' => 404, 'location_ids' => $missing]);
        }

        $rawStart = is_array($input['start_point'] ?? null) ? (array)$input['start_point'] : [];
        $rawEnd = is_array($input['end_point'] ?? null) ? (array)$input['end_point'] : [];
        $returnToStart = !empty($input['return_to_start']);
        if (!array_key_exists('return_to_start', $input) && !empty($routeMeta['return_to_start'])) $returnToStart = true;
        $firstItem = $items[0] ?? [];
        $lastItem = $items ? $items[count($items)-1] : [];
        $ownerUserId = absint($route['owner_user_id'] ?? ($input['owner_user_id'] ?? (!$trusted ? $userId : 0)));
        if (class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
            $resolvedEndpoints = RouteEndpointProfileService::resolveForRoute(
                $clientId,
                $projectId,
                $ownerUserId,
                $routeMeta,
                $rawStart,
                $rawEnd,
                $firstItem,
                $lastItem,
                ['return_to_start'=>$returnToStart]
            );
            $rawStart = (array)($resolvedEndpoints['start_point'] ?? []);
            $rawEnd = (array)($resolvedEndpoints['end_point'] ?? []);
            $returnToStart = !empty($resolvedEndpoints['return_to_start']);
        } else {
            if (!RouteEndpointResolverService::hasRoutingData($rawStart)) $rawStart = (array)($routeMeta['start_point'] ?? []);
            if (!RouteEndpointResolverService::hasRoutingData($rawEnd)) $rawEnd = (array)($routeMeta['end_point'] ?? []);
        }
        $start = class_exists('\RoutesPro\Services\RouteEndpointResolverService')
            ? RouteEndpointResolverService::resolve($rawStart, 'start', (string)($rawStart['provenance'] ?? 'route_optimizer'), ['allow_matches_stop'=>!empty($rawStart['allow_matches_stop'])])
            : self::normalisePoint($rawStart, '__start__');
        $end = class_exists('\RoutesPro\Services\RouteEndpointResolverService')
            ? RouteEndpointResolverService::resolve($rawEnd, 'end', (string)($rawEnd['provenance'] ?? 'route_optimizer'), ['allow_matches_stop'=>!empty($rawEnd['allow_matches_stop'])])
            : self::normalisePoint($rawEnd, '__end__');
        $start['_matrix_key'] = '__start__';
        $end['_matrix_key'] = '__end__';
        if ($returnToStart && self::pointValue($start) !== '') {
            $end = $start;
            $end['provenance'] = 'return_to_start';
            $end['allow_matches_stop'] = !empty($rawEnd['allow_matches_stop']) ? 1 : 0;
            $end['_matrix_key'] = '__end__';
        }
        $endpointIntegrity = class_exists('\RoutesPro\Services\RouteEndpointResolverService')
            ? RouteEndpointResolverService::integrity($start, $end, $firstItem, $lastItem, [
                'allow_start_matches_stop'=>!empty($start['allow_matches_stop']),
                'allow_end_matches_stop'=>!empty($end['allow_matches_stop']),
            ])
            : RouteMetricsService::endpointIntegrity($start, $end);
        if (empty($endpointIntegrity['endpoints_routable'])) {
            return new WP_Error(
                'fieldflow_route_endpoints_required',
                'Indica o ponto de partida e o ponto de chegada antes de calcular ou otimizar a rota.',
                [
                    'status' => 400,
                    'endpoint_status' => (string)($endpointIntegrity['endpoint_status'] ?? 'incomplete'),
                    'endpoint_warning_codes' => (array)($endpointIntegrity['endpoint_warning_codes'] ?? []),
                ]
            );
        }

        $routeSafety = $routeId > 0 ? self::routeSafety($routeId, $route) : ['editable' => true, 'reason' => '', 'status' => 'new'];
        $locked = $manualLocked || empty($routeSafety['editable']);
        $lockReason = $manualLocked ? 'manual_order_locked' : (string)($routeSafety['reason'] ?? '');

        $matrixPoints = [];
        if (self::hasRoutingData($start)) $matrixPoints[] = $start;
        foreach ($items as $item) $matrixPoints[] = $item;
        if (self::hasRoutingData($end)) $matrixPoints[] = $end;
        $matrix = RoutingMatrixService::build($matrixPoints, ['mode' => $mode, 'force_refresh' => $forceRefresh]);

        $calculator = new RouteCalculator($matrix, sanitize_key((string)($input['territorial_mode'] ?? 'balanced')));
        $baselineMetrics = $calculator->pathMetrics($items, $start, $end, $matrix);
        if ($locked || count($items) <= 1) {
            $orderedItems = $items;
        } else {
            $candidateItems = array_values($calculator->reorderStops($items, $start, $end, $matrix));
            if ($lockedLocationIds) {
                $lockedSet = array_fill_keys($lockedLocationIds, true);
                $unlockedCandidates = array_values(array_filter($candidateItems, static fn(array $item): bool => empty($lockedSet[(int)($item['id'] ?? 0)])));
                $cursor = 0;
                $orderedItems = [];
                foreach ($items as $originalItem) {
                    $originalId = (int)($originalItem['id'] ?? 0);
                    if (!empty($lockedSet[$originalId])) {
                        $orderedItems[] = $originalItem;
                    } else {
                        $orderedItems[] = $unlockedCandidates[$cursor] ?? $originalItem;
                        $cursor++;
                    }
                }
            } else {
                $orderedItems = $candidateItems;
            }
        }
        $optimizedMetrics = $calculator->pathMetrics($orderedItems, $start, $end, $matrix);
        $endpointMetrics = [
            'endpoint_status' => (string)($endpointIntegrity['endpoint_status'] ?? 'complete'),
            'endpoints_complete' => !empty($endpointIntegrity['endpoints_complete']) ? 1 : 0,
            'endpoints_routable' => !empty($endpointIntegrity['endpoints_routable']) ? 1 : 0,
            'endpoints_trusted' => !empty($endpointIntegrity['endpoints_complete']) ? 1 : 0,
            'missing_endpoint_count' => (int)($endpointIntegrity['missing_endpoint_count'] ?? 0),
            'suspicious_endpoint_count' => (int)($endpointIntegrity['suspicious_endpoint_count'] ?? 0),
            'endpoint_warning_codes' => (array)($endpointIntegrity['endpoint_warning_codes'] ?? []),
            'start_endpoint_provenance' => (string)($endpointIntegrity['start_provenance'] ?? ($start['provenance'] ?? 'legacy')),
            'end_endpoint_provenance' => (string)($endpointIntegrity['end_provenance'] ?? ($end['provenance'] ?? 'legacy')),
            'endpoint_reconciliation_contract' => class_exists('\RoutesPro\Services\RouteEndpointResolverService') ? RouteEndpointResolverService::CONTRACT : '',
        ];
        $baselineMetrics = array_merge($baselineMetrics, $endpointMetrics);
        $optimizedMetrics = array_merge($optimizedMetrics, $endpointMetrics);

        $originalOrder = array_values(array_map(static fn(array $item): int => (int)$item['id'], $items));
        $optimizedOrder = array_values(array_map(static fn(array $item): int => (int)$item['id'], $orderedItems));
        $changed = $originalOrder !== $optimizedOrder;
        $baselineKm = self::metricNumber($baselineMetrics, 'distance_km', 'known_distance_km');
        $optimizedKm = self::metricNumber($optimizedMetrics, 'distance_km', 'known_distance_km');
        $baselineMin = self::metricNumber($baselineMetrics, 'travel_min', 'known_travel_min');
        $optimizedMin = self::metricNumber($optimizedMetrics, 'travel_min', 'known_travel_min');
        $savingKm = max(0.0, $baselineKm - $optimizedKm);
        $savingMin = max(0.0, $baselineMin - $optimizedMin);

        $warnings = [];
        foreach ($items as $item) {
            if (!self::hasRoutingData($item)) $warnings[] = 'A loja #' . (int)$item['id'] . ' não tem coordenadas ou morada utilizável.';
        }
        if (!empty($matrix['google_error'])) $warnings[] = sanitize_text_field((string)$matrix['google_error']);
        if ($locked && $lockReason === 'route_started') $warnings[] = 'A rota já tem atividade operacional e a ordem foi preservada.';
        if ($manualLocked) $warnings[] = 'A ordem manual está bloqueada pelo utilizador.';
        if ($lockedLocationIds) $warnings[] = count($lockedLocationIds) . ' loja(s) mantida(s) na posição definida.';
        $planningWarnings = $plannerMode ? self::plannerWarnings($locationIds, $projectId, $routeId, $userId, $planningDate) : [];
        if ($planningWarnings) $warnings = array_merge($warnings, $planningWarnings);

        return [
            'optimization_version' => self::VERSION,
            'route_id' => $routeId,
            'client_id' => $clientId,
            'project_id' => $projectId,
            'original_order' => $originalOrder,
            'ordered_location_ids' => $optimizedOrder,
            'changed' => $changed ? 1 : 0,
            'locked' => $locked ? 1 : 0,
            'lock_reason' => $lockReason,
            'locked_location_ids' => $lockedLocationIds,
            'route_status' => (string)($routeSafety['status'] ?? ($route['status'] ?? 'new')),
            'provider' => (string)($matrix['provider'] ?? 'internal_road_estimator'),
            'provider_label' => (string)($matrix['source_label'] ?? 'Estimador rodoviário interno'),
            'coverage_pct' => (float)($matrix['coverage_pct'] ?? 0),
            'baseline' => self::publicMetrics($baselineMetrics),
            'optimized' => self::publicMetrics($optimizedMetrics),
            'saving_km' => round($savingKm, 2),
            'saving_minutes' => round($savingMin, 1),
            'start_point' => self::publicPoint($start),
            'end_point' => self::publicPoint($end),
            'return_to_start' => $returnToStart ? 1 : 0,
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'planning_mode' => $plannerMode ? 1 : 0,
            'planning_warnings' => array_values(array_unique(array_filter($planningWarnings))),
            'optimized_at' => current_time('mysql'),
        ];
    }

    /**
     * @return array|WP_Error
     */
    public static function applyToRoute(int $routeId, array $input = [], int $userId = 0) {
        return self::applyToRouteInternal($routeId, $input, $userId, false);
    }

    /** Trusted internal entry point for resumable maintenance jobs. */
    public static function applyToRouteSystem(int $routeId, array $input = []) {
        return self::applyToRouteInternal($routeId, $input, 0, true);
    }

    /**
     * @return array|WP_Error
     */
    private static function applyToRouteInternal(int $routeId, array $input = [], int $userId = 0, bool $trusted = false) {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $routeId = absint($routeId);
        $userId = $userId > 0 ? $userId : get_current_user_id();
        if ($routeId <= 0) return new WP_Error('fieldflow_route_optimization_route_required', 'Rota inválida.', ['status' => 400]);

        $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A) ?: [];
        if (!$route) return new WP_Error('fieldflow_route_optimization_route_missing', 'A rota já não existe.', ['status' => 404]);
        if (!$trusted && !Permissions::can_edit_route($routeId, $userId)) return new WP_Error('fieldflow_route_optimization_forbidden', 'Não tens permissão para alterar esta rota.', ['status' => 403]);
        $safety = self::routeSafety($routeId, $route);
        if (empty($safety['editable'])) {
            return new WP_Error('fieldflow_route_optimization_locked', 'A rota já foi iniciada e não pode ser reordenada automaticamente.', ['status' => 409, 'reason' => $safety['reason']]);
        }

        $stops = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC",
            $routeId
        ), ARRAY_A) ?: [];
        $workspaceSnapshot = class_exists('\\RoutesPro\\Services\\RouteWorkspaceService')
            ? RouteWorkspaceService::captureCurrent($routeId)
            : null;
        if (is_wp_error($workspaceSnapshot)) return $workspaceSnapshot;
        $input['route_id'] = $routeId;
        // Applying an optimisation is a reorder-only operation. Store
        // additions and removals continue to use the guarded route plan sync
        // flow, preventing this endpoint from changing route composition.
        $input['location_ids'] = array_values(array_map('absint', wp_list_pluck($stops, 'location_id')));
        $preview = self::previewInternal($input, $userId, $trusted);
        if (is_wp_error($preview)) return $preview;
        if (!empty($preview['locked'])) return new WP_Error('fieldflow_route_optimization_locked', 'A ordem desta rota está bloqueada.', ['status' => 409, 'reason' => $preview['lock_reason']]);

        $stopByLocation = [];
        foreach ($stops as $stop) {
            $locationId = (int)$stop['location_id'];
            if (isset($stopByLocation[$locationId])) return new WP_Error('fieldflow_route_optimization_repeated_stop', 'A rota contém visitas repetidas e deve ser editada no planeador.', ['status'=>409]);
            $stopByLocation[$locationId] = $stop;
        }
        $locationIds = array_keys($stopByLocation);
        $placeholders = implode(',', array_fill(0, count($locationIds), '%d'));
        $locations = $wpdb->get_results($wpdb->prepare(
            "SELECT id,name,address,lat,lng,service_time_min,window_start,window_end,meta_json FROM {$px}locations WHERE id IN ({$placeholders})",
            ...$locationIds
        ), ARRAY_A) ?: [];
        $locationById = [];
        foreach ($locations as $location) $locationById[(int)$location['id']] = self::normaliseLocation($location);
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT location_id,time_window_start,time_window_end FROM {$px}campaign_locations WHERE project_id=%d AND location_id IN ({$placeholders})",
            (int)($route['project_id'] ?? 0), ...$locationIds
        ), ARRAY_A) ?: [];
        foreach ($rules as $rule) {
            $id = (int)$rule['location_id'];
            if (isset($locationById[$id])) $locationById[$id] = array_merge($locationById[$id], $rule);
        }
        $plannedStops = [];
        foreach ((array)$preview['ordered_location_ids'] as $seq => $locationId) {
            if (empty($locationById[(int)$locationId])) return new WP_Error('fieldflow_route_optimization_locations_missing', 'Uma loja já não existe.', ['status'=>409]);
            $existing = $stopByLocation[(int)$locationId];
            $plannedStops[] = array_merge($existing, [
                'route_stop_id'=>(int)$existing['id'], 'seq'=>(int)$seq, 'item'=>$locationById[(int)$locationId],
            ]);
        }
        $start = (array)$preview['start_point'];
        $end = (array)$preview['end_point'];
        $start['_matrix_key'] = '__start__';
        $end['_matrix_key'] = '__end__';
        $matrix = RoutingMatrixService::build(array_merge([$start], array_values($locationById), [$end]), ['mode'=>$input['mode'] ?? 'hybrid']);
        $meta = self::decodeMeta($route['meta_json'] ?? '');
        $routeStart = (string)($meta['route_metrics']['route_start'] ?? $meta['plan_summary']['route_start'] ?? '');
        if ($routeStart === '' && !empty($stops[0]['planned_arrival'])) {
            $first = $locationById[(int)$stops[0]['location_id']] ?? [];
            $edge = RoutingMatrixService::edge($matrix, $start, $first);
            if (is_numeric($edge['travel_min'] ?? null)) $routeStart = date('Y-m-d H:i:s', strtotime($stops[0]['planned_arrival']) - (int)round((float)$edge['travel_min'] * 60));
        }
        $plannedStops = RoutePlanSyncService::scheduleOrderedStops((string)($route['date'] ?? ''), $plannedStops, $start, $end, $matrix, $routeStart);
        if (is_wp_error($plannedStops)) return $plannedStops;

        $transaction = $wpdb->query('START TRANSACTION') !== false;
        if (!$transaction) return new WP_Error('fieldflow_route_optimization_transaction', 'Não foi possível iniciar a atualização segura da rota.', ['status' => 500]);
        try {
            $lockedRoute = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d FOR UPDATE", $routeId), ARRAY_A) ?: [];
            $lockedStops = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC FOR UPDATE", $routeId), ARRAY_A) ?: [];
            if ($lockedRoute !== $route || $lockedStops !== $stops || empty(self::routeSafety($routeId, $lockedRoute)['editable'])) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('fieldflow_route_optimization_conflict', 'A rota mudou durante o cálculo. Volta a calcular.', ['status'=>409]);
            }
            $sync = RoutePlanSyncService::sync($routeId, $plannedStops);
            if (is_wp_error($sync)) { $wpdb->query('ROLLBACK'); return $sync; }
            $progress = RouteProgressService::sync($routeId, true);
            if (is_wp_error($progress)) { $wpdb->query('ROLLBACK'); return $progress; }
            $meta = self::decodeMeta($route['meta_json'] ?? '');
            if (is_array($workspaceSnapshot) && class_exists('\\RoutesPro\\Services\\RouteWorkspaceService')) {
                $meta = RouteWorkspaceService::appendRevision($meta, $workspaceSnapshot, 'before_optimize', $userId, 'Antes de aplicar otimização');
            }
            $meta = self::mergeOptimizationMeta($meta, $preview, !empty($input['manual_order_locked']));
            $meta['route_workspace_version'] = class_exists('\\RoutesPro\\Services\\RouteWorkspaceService') ? RouteWorkspaceService::VERSION : 'fieldflow_route_workspace_v1';
            $meta['route_workspace_last_saved_at'] = current_time('mysql');
            $meta['route_workspace_last_saved_by'] = $userId;
            $ok = $wpdb->update($px . 'routes', [
                'meta_json' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ], ['id' => $routeId], ['%s'], ['%d']);
            if ($ok === false) { $wpdb->query('ROLLBACK'); return new WP_Error('fieldflow_route_optimization_meta', 'A otimização foi calculada, mas não foi possível guardar os metadados.', ['status' => 500]); }
            if ($wpdb->query('COMMIT') === false) { $wpdb->query('ROLLBACK'); return new WP_Error('fieldflow_route_optimization_commit', 'Não foi possível confirmar a nova ordem.', ['status' => 500]); }
            self::invalidateUserRouteCache((int)($route['owner_user_id'] ?? 0));
            $preview['applied'] = 1;
            $preview['sync'] = $sync;
            $preview['progress'] = $progress;
            $preview['google_maps_url'] = GoogleMapsUrlService::forRouteId($routeId);
            if (class_exists('\RoutesPro\Services\RouteMetricsService')) {
                $preview['routing_finalization'] = RouteMetricsService::maybeRefreshGoogleRoute($routeId, [], false);
            }
            return $preview;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('fieldflow_route_optimization_failure', 'Não foi possível aplicar a otimização.', ['status' => 500]);
        }
    }

    public static function invalidateUserRouteCache(int $userId): void {
        $userId = absint($userId);
        if ($userId <= 0) return;
        $key = '_fieldflow_route_cache_generation';
        $current = max(1, absint(get_user_meta($userId, $key, true)));
        update_user_meta($userId, $key, $current + 1);
    }

    public static function mergeOptimizationMeta(array $meta, array $preview, bool $manualLocked = false): array {
        $optimized = is_array($preview['optimized'] ?? null) ? (array)$preview['optimized'] : [];
        $baseline = is_array($preview['baseline'] ?? null) ? (array)$preview['baseline'] : [];
        $meta['manual_order_locked'] = $manualLocked ? 1 : 0;
        $meta['position_locked_location_ids'] = array_values(array_unique(array_filter(array_map('absint', (array)($preview['locked_location_ids'] ?? [])))));
        $meta['optimization_status'] = !empty($preview['locked']) ? 'locked' : 'optimized';
        $meta['optimization_version'] = (string)($preview['optimization_version'] ?? self::VERSION);
        $meta['optimized_at'] = (string)($preview['optimized_at'] ?? current_time('mysql'));
        $meta['optimization_provider'] = (string)($preview['provider'] ?? 'internal_road_estimator');
        $meta['optimization_provider_label'] = (string)($preview['provider_label'] ?? '');
        $meta['optimization_original_order'] = array_values(array_map('absint', (array)($preview['original_order'] ?? [])));
        $meta['optimization_order'] = array_values(array_map('absint', (array)($preview['ordered_location_ids'] ?? [])));
        $meta['optimization_baseline_distance_km'] = is_numeric($baseline['distance_km'] ?? null) ? (float)$baseline['distance_km'] : null;
        $meta['optimization_distance_km'] = is_numeric($optimized['distance_km'] ?? null) ? (float)$optimized['distance_km'] : null;
        $meta['optimization_baseline_travel_min'] = is_numeric($baseline['travel_min'] ?? null) ? (float)$baseline['travel_min'] : null;
        $meta['optimization_travel_min'] = is_numeric($optimized['travel_min'] ?? null) ? (float)$optimized['travel_min'] : null;
        $meta['optimization_saved_distance_km'] = (float)($preview['saving_km'] ?? 0);
        $meta['optimization_saved_travel_min'] = (float)($preview['saving_minutes'] ?? 0);

        if (is_array($preview['start_point'] ?? null)) $meta['start_point'] = (array)$preview['start_point'];
        if (is_array($preview['end_point'] ?? null)) $meta['end_point'] = (array)$preview['end_point'];
        $meta['route_endpoint_reconciliation_contract'] = class_exists('\RoutesPro\Services\RouteEndpointResolverService') ? RouteEndpointResolverService::CONTRACT : '';
        $meta['route_metrics'] = RouteMetricsService::canonicalFromSchedule(array_merge($optimized, [
            'routing_provider' => (string)($preview['provider'] ?? 'internal_road_estimator'),
            'routing_calculated_at' => (string)($preview['optimized_at'] ?? current_time('mysql')),
            'distance_source' => (string)($preview['provider'] ?? 'internal_road_estimator'),
            'matrix_provider' => (string)($preview['provider'] ?? 'internal_road_estimator'),
            'matrix_source_label' => (string)($preview['provider_label'] ?? ''),
            'optimization_method' => !empty($preview['locked']) ? 'preserved_order' : 'route_optimizer',
            'optimization_saved_distance_km' => (float)($preview['saving_km'] ?? 0),
            'optimization_saved_travel_min' => (float)($preview['saving_minutes'] ?? 0),
        ]), [
            'calculated_at' => (string)($preview['optimized_at'] ?? current_time('mysql')),
        ]);
        $needsFinalGoogle = class_exists('\RoutesPro\Services\GoogleRoutes') && GoogleRoutes::enabled()
            && (string)($preview['provider'] ?? '') !== 'google_routes';
        $meta['route_metrics_stale'] = $needsFinalGoogle ? 1 : 0;
        $meta['routing_refresh_required'] = $needsFinalGoogle ? 1 : 0;
        if ($needsFinalGoogle) $meta['routing_refresh_reason'] = 'route_order_or_composition_changed';
        return $meta;
    }

    public static function routeSafety(int $routeId, array $route = []): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        if (!$route) $route = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A) ?: [];
        $status = sanitize_key((string)($route['status'] ?? 'draft'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id,location_id,seq,status,arrived_at,departed_at
             FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC",
            $routeId
        ), ARRAY_A) ?: [];

        $protectedStopIds = [];
        $protectedLocationIds = [];
        $mutableStopIds = [];
        foreach ($rows as $row) {
            $stopStatus = sanitize_key((string)($row['status'] ?? 'pending'));
            $operational = !empty($row['arrived_at']) || !empty($row['departed_at'])
                || !in_array($stopStatus, ['pending', 'planned', 'draft'], true);
            if ($operational) {
                $protectedStopIds[] = absint($row['id'] ?? 0);
                $protectedLocationIds[] = absint($row['location_id'] ?? 0);
            } else {
                $mutableStopIds[] = absint($row['id'] ?? 0);
            }
        }
        $protectedStopIds = array_values(array_filter(array_unique($protectedStopIds)));
        $protectedLocationIds = array_values(array_filter(array_unique($protectedLocationIds)));
        $mutableStopIds = array_values(array_filter(array_unique($mutableStopIds)));
        $active = count($protectedStopIds);
        $pending = count($mutableStopIds);

        $fullEditable = in_array($status, ['draft', 'planned'], true) && $active === 0;
        $liveAdjustable = ($status === 'in_progress') || (in_array($status, ['planned', 'completed'], true) && $active > 0);
        $canPlan = $fullEditable || $liveAdjustable;
        $reason = '';
        if (!$canPlan) {
            if ($status === 'canceled') $reason = 'route_finished';
            elseif ($active > 0) $reason = 'route_started';
            else $reason = 'route_locked';
        }

        return [
            'editable' => $fullEditable,
            'live_adjustable' => $liveAdjustable,
            'can_plan' => $canPlan,
            'can_restore' => $fullEditable,
            'mode' => $fullEditable ? 'full' : ($liveAdjustable ? 'live' : 'locked'),
            'reason' => $reason,
            'status' => $status,
            'active_count' => $active,
            'pending_count' => $pending,
            'protected_stop_ids' => $protectedStopIds,
            'protected_location_ids' => $protectedLocationIds,
            'mutable_stop_ids' => $mutableStopIds,
        ];
    }

    /** @return true|WP_Error */
    private static function validateLocations(array $locationIds, int $projectId, int $routeId, int $userId, bool $plannerMode = false) {
        if (Permissions::is_manager($userId)) return true;
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $allowed = [];
        if ($projectId > 0) {
            $placeholders = implode(',', array_fill(0, count($locationIds), '%d'));
            $lifecycleSql = $plannerMode ? '' : " AND is_active=1 AND status='active'";
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT location_id FROM {$px}campaign_locations
                 WHERE project_id=%d AND assigned_to=%d{$lifecycleSql}
                   AND location_id IN ({$placeholders})",
                $projectId, $userId, ...$locationIds
            )) ?: [];
            foreach ($ids as $id) $allowed[(int)$id] = true;
        }
        if ($routeId > 0) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT location_id FROM {$px}route_stops WHERE route_id=%d", $routeId)) ?: [];
            foreach ($ids as $id) $allowed[(int)$id] = true;
        }
        $denied = array_values(array_filter($locationIds, static fn(int $id): bool => empty($allowed[$id])));
        if ($denied) return new WP_Error('fieldflow_route_optimization_location_forbidden', 'Existem lojas fora da tua atribuição ou rota.', ['status' => 403, 'location_ids' => $denied]);
        return true;
    }

    /**
     * Planear is intentionally permissive for assigned stores. Cadence, status
     * and scheduling collisions are advisory here, never hard blockers.
     *
     * @return string[]
     */
    private static function plannerWarnings(array $locationIds, int $projectId, int $routeId, int $userId, string $date): array {
        if (!$locationIds || $projectId <= 0 || $userId <= 0 || Permissions::is_manager($userId)) return [];
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $placeholders = implode(',', array_fill(0, count($locationIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cl.location_id,cl.status,cl.is_active,cl.min_gap_days,cl.preferred_weekdays,cl.blocked_weekdays,l.name
             FROM {$px}campaign_locations cl
             INNER JOIN {$px}locations l ON l.id=cl.location_id
             WHERE cl.project_id=%d AND cl.assigned_to=%d
               AND cl.location_id IN ({$placeholders})",
            $projectId, $userId, ...$locationIds
        ), ARRAY_A) ?: [];
        $warnings = [];
        $inactive = [];
        foreach ($rows as $row) {
            if ((int)($row['is_active'] ?? 0) !== 1 || sanitize_key((string)($row['status'] ?? '')) !== 'active') {
                $inactive[] = (string)($row['name'] ?? ('#' . (int)($row['location_id'] ?? 0)));
            }
        }
        if ($inactive) {
            $warnings[] = 'Aviso: ' . count($inactive) . ' loja(s) têm a ligação à campanha pausada ou inativa. O planeamento foi mantido.';
        }

        $validDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date . ' 12:00:00 UTC') !== false;
        if (!$validDate) return array_values(array_unique($warnings));
        $weekday = (int)gmdate('N', strtotime($date . ' 12:00:00 UTC'));
        $blocked = [];
        $outsidePreferred = [];
        foreach ($rows as $row) {
            $blockedDays = class_exists('\RoutesPro\Services\Planning\VisitRuleResolver')
                ? \RoutesPro\Services\Planning\VisitRuleResolver::weekdayList($row['blocked_weekdays'] ?? '')
                : [];
            $preferredDays = class_exists('\RoutesPro\Services\Planning\VisitRuleResolver')
                ? \RoutesPro\Services\Planning\VisitRuleResolver::weekdayList($row['preferred_weekdays'] ?? '')
                : [];
            $label = (string)($row['name'] ?? ('#' . (int)($row['location_id'] ?? 0)));
            if ($blockedDays && in_array($weekday, $blockedDays, true)) $blocked[] = $label;
            elseif ($preferredDays && !in_array($weekday, $preferredDays, true)) $outsidePreferred[] = $label;
        }
        if ($blocked) $warnings[] = 'Periodicidade: ' . count($blocked) . ' loja(s) estão num dia configurado como bloqueado. Podes planear na mesma e rever antes da execução.';
        if ($outsidePreferred) $warnings[] = 'Periodicidade: ' . count($outsidePreferred) . ' loja(s) estão fora dos dias preferenciais. O plano continua editável.';

        $overlapArgs = [$projectId, $date];
        $overlapSql = "SELECT DISTINCT rs.location_id
                       FROM {$px}route_stops rs
                       INNER JOIN {$px}routes r ON r.id=rs.route_id
                       WHERE r.project_id=%d AND r.date=%s
                         AND r.status NOT IN ('canceled','cancelled')
                         AND rs.location_id IN ({$placeholders})";
        $overlapArgs = array_merge($overlapArgs, $locationIds);
        if ($routeId > 0) { $overlapSql .= ' AND r.id<>%d'; $overlapArgs[] = $routeId; }
        $overlapIds = array_values(array_unique(array_map('intval', $wpdb->get_col($wpdb->prepare($overlapSql, ...$overlapArgs)) ?: [])));
        if ($overlapIds) {
            $warnings[] = 'Sobreposição: ' . count($overlapIds) . ' loja(s) já aparecem noutra rota em ' . date_i18n('d/m/Y', strtotime($date)) . '. O planeamento continua permitido; revê antes de executar.';
        }

        $gapWarnings = 0;
        foreach ($rows as $row) {
            $gap = max(0, (int)($row['min_gap_days'] ?? 0));
            $locationId = (int)($row['location_id'] ?? 0);
            if ($gap <= 0 || $locationId <= 0) continue;
            $sql = "SELECT r.id
                    FROM {$px}route_stops rs
                    INNER JOIN {$px}routes r ON r.id=rs.route_id
                    WHERE r.project_id=%d AND rs.location_id=%d
                      AND r.status NOT IN ('canceled','cancelled')
                      AND r.date<>%s
                      AND ABS(DATEDIFF(r.date,%s))<%d";
            $args = [$projectId, $locationId, $date, $date, $gap];
            if ($routeId > 0) { $sql .= ' AND r.id<>%d'; $args[] = $routeId; }
            $sql .= ' LIMIT 1';
            if ($wpdb->get_var($wpdb->prepare($sql, ...$args))) $gapWarnings++;
        }
        if ($gapWarnings) {
            $warnings[] = 'Periodicidade: ' . $gapWarnings . ' loja(s) ficam abaixo do intervalo mínimo entre visitas. É apenas um alerta e não bloqueia o plano.';
        }
        return array_values(array_unique(array_filter($warnings)));
    }

    private static function normaliseLocation(array $row): array {
        $meta = self::decodeMeta($row['meta_json'] ?? '');
        return [
            'id' => (int)$row['id'],
            'location_id' => (int)$row['id'],
            '_matrix_key' => 'location_' . (int)$row['id'],
            'name' => sanitize_text_field((string)($row['name'] ?? '')),
            'address' => sanitize_text_field((string)($row['address'] ?? '')),
            'lat' => is_numeric($row['lat'] ?? null) ? (float)$row['lat'] : null,
            'lng' => is_numeric($row['lng'] ?? null) ? (float)$row['lng'] : null,
            'city' => sanitize_text_field((string)($row['city'] ?? $meta['city'] ?? $meta['locality'] ?? '')),
            'county' => sanitize_text_field((string)($meta['county'] ?? $meta['municipality'] ?? '')),
            'district' => sanitize_text_field((string)($meta['district'] ?? '')),
            'postal_code' => sanitize_text_field((string)($row['postal_code'] ?? '')),
            'service_time_min' => max(0, (int)($row['service_time_min'] ?? 0)),
            'window_start' => sanitize_text_field((string)($row['window_start'] ?? '')),
            'window_end' => sanitize_text_field((string)($row['window_end'] ?? '')),
        ];
    }

    private static function normalisePoint(array $point, string $key): array {
        return [
            '_matrix_key' => $key,
            'name' => sanitize_text_field((string)($point['name'] ?? '')),
            'address' => sanitize_text_field((string)($point['address'] ?? $point['formatted_address'] ?? '')),
            'lat' => is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null,
            'lng' => is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null,
        ];
    }

    private static function hasRoutingData(array $point): bool {
        return (is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) || trim((string)($point['address'] ?? '')) !== '';
    }

    private static function pointValue(array $point): string {
        if (is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) return (string)$point['lat'] . ',' . (string)$point['lng'];
        return trim((string)($point['address'] ?? $point['name'] ?? ''));
    }

    private static function publicPoint(array $point): array {
        if (class_exists('\RoutesPro\Services\RouteEndpointResolverService')) return RouteEndpointResolverService::publicPoint($point);
        $out = ['address' => (string)($point['address'] ?? '')];
        if (is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) { $out['lat'] = (float)$point['lat']; $out['lng'] = (float)$point['lng']; }
        return $out;
    }

    private static function publicMetrics(array $metrics): array {
        $number = static function($value, int $precision) {
            return is_numeric($value) ? round(max(0.0, (float)$value), $precision) : null;
        };
        $distance = $number($metrics['distance_km'] ?? null, 2);
        $knownDistance = $number($metrics['known_distance_km'] ?? $distance, 2);
        $travel = $number($metrics['travel_min'] ?? null, 1);
        $knownTravel = $number($metrics['known_travel_min'] ?? $travel, 1);
        $distanceStatus = sanitize_key((string)($metrics['distance_status'] ?? ($distance !== null ? 'complete' : 'incomplete')));
        $endpointStatus = sanitize_key((string)($metrics['endpoint_status'] ?? ($distanceStatus === 'complete' ? 'complete' : 'incomplete')));
        $endpointsComplete = array_key_exists('endpoints_complete', $metrics) ? !empty($metrics['endpoints_complete']) : $endpointStatus === 'complete';
        $endpointsRoutable = array_key_exists('endpoints_routable', $metrics) ? !empty($metrics['endpoints_routable']) : ($distanceStatus === 'complete');
        $toll = $distanceStatus === 'complete' && $endpointsRoutable && $distance !== null ? TollEstimator::costFromKm($distance, 'route') : null;
        return [
            'distance_km' => $distance,
            'known_distance_km' => $knownDistance,
            'travel_min' => $travel,
            'known_travel_min' => $knownTravel,
            'access_distance_km' => $number($metrics['access_distance_km'] ?? null, 2),
            'inter_stop_distance_km' => $number($metrics['inter_stop_distance_km'] ?? null, 2),
            'return_distance_km' => $number($metrics['return_distance_km'] ?? null, 2),
            'access_travel_min' => $number($metrics['access_travel_min'] ?? null, 1),
            'inter_stop_travel_min' => $number($metrics['inter_stop_travel_min'] ?? null, 1),
            'return_travel_min' => $number($metrics['return_travel_min'] ?? null, 1),
            'toll_cost_eur' => $toll !== null ? round((float)$toll, 2) : null,
            'known_toll_cost_eur' => $toll !== null ? round((float)$toll, 2) : null,
            'distance_source' => (string)($metrics['distance_source'] ?? ''),
            'distance_status' => $distanceStatus,
            'endpoint_status' => $endpointStatus,
            'endpoints_complete' => $endpointsComplete ? 1 : 0,
            'endpoints_routable' => $endpointsRoutable ? 1 : 0,
            'endpoints_trusted' => $endpointsComplete ? 1 : 0,
            'missing_endpoint_count' => max(0, (int)($metrics['missing_endpoint_count'] ?? ($endpointsRoutable ? 0 : 1))),
            'suspicious_endpoint_count' => max(0, (int)($metrics['suspicious_endpoint_count'] ?? 0)),
            'endpoint_warning_codes' => array_values(array_filter(array_map('sanitize_key', (array)($metrics['endpoint_warning_codes'] ?? [])))),
            'endpoint_reconciliation_contract' => sanitize_text_field((string)($metrics['endpoint_reconciliation_contract'] ?? '')),
            'unresolved_leg_count' => (int)($metrics['unresolved_leg_count'] ?? 0),
            'segment_count' => max(0, (int)($metrics['segment_count'] ?? 0)),
        ];
    }

    private static function metricNumber(array $metrics, string $preferred, string $fallback): float {
        if (is_numeric($metrics[$preferred] ?? null)) return (float)$metrics[$preferred];
        return is_numeric($metrics[$fallback] ?? null) ? (float)$metrics[$fallback] : 0.0;
    }

    private static function decodeMeta($raw): array {
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
