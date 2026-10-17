<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Safe reconciliation for routes manually created in the front office.
 *
 * Only draft/planned routes without operational activity are considered.
 * Simulation and application use the same canonical optimisation service.
 */
final class RouteOptimizationReconciliationService {
    public const VERSION = 'fieldflow_route_reconciliation_v1';

    public static function countEligible(array $filters = []): int {
        global $wpdb;
        list($where, $args) = self::where($filters, 0, false);
        $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}routespro_routes r WHERE " . implode(' AND ', $where);
        return (int)$wpdb->get_var($args ? $wpdb->prepare($sql, ...$args) : $sql);
    }

    public static function processBatch(array $row): array {
        global $wpdb;
        $meta = json_decode((string)($row['meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];

        $mode = sanitize_key((string)($meta['mode'] ?? 'preview'));
        if (!in_array($mode, ['preview', 'apply'], true)) $mode = 'preview';
        $routingMode = sanitize_key((string)($meta['routing_mode'] ?? 'hybrid'));
        if (!in_array($routingMode, ['hybrid', 'internal'], true)) $routingMode = 'hybrid';
        $cursor = max(0, (int)($row['cursor_value'] ?? 0));
        $limit = max(1, min(20, (int)($row['batch_size'] ?? 5)));

        list($where, $args) = self::where($meta, $cursor, true);
        $sql = "SELECT r.* FROM {$wpdb->prefix}routespro_routes r WHERE " . implode(' AND ', $where) . ' ORDER BY r.id ASC LIMIT ' . $limit;
        $routes = $wpdb->get_results($args ? $wpdb->prepare($sql, ...$args) : $sql, ARRAY_A) ?: [];

        $summary = [
            'mode' => $mode,
            'routing_mode' => $routingMode,
            'analysed' => 0,
            'would_change' => 0,
            'applied' => 0,
            'unchanged' => 0,
            'locked' => 0,
            'missing_endpoints' => 0,
            'failed' => 0,
            'saving_km' => 0.0,
            'saving_minutes' => 0.0,
            'routes' => [],
        ];
        $lastId = $cursor;

        foreach ($routes as $route) {
            $routeId = (int)($route['id'] ?? 0);
            if ($routeId <= 0) continue;
            $lastId = max($lastId, $routeId);
            $summary['analysed']++;

            $routeMeta = self::decodeMeta($route['meta_json'] ?? '');
            if (!self::isFrontRoute($routeMeta)) {
                $summary['unchanged']++;
                continue;
            }
            if (!empty($routeMeta['manual_order_locked'])) {
                $summary['locked']++;
                self::appendRoute($summary, $routeId, 'locked', 0, 0, 'Ordem manual bloqueada.');
                continue;
            }
            $safety = RouteOptimizationService::routeSafety($routeId, $route);
            if (empty($safety['editable'])) {
                $summary['locked']++;
                self::appendRoute($summary, $routeId, 'started', 0, 0, 'Rota já iniciada.');
                continue;
            }
            $start = is_array($routeMeta['start_point'] ?? null) ? (array)$routeMeta['start_point'] : [];
            $end = is_array($routeMeta['end_point'] ?? null) ? (array)$routeMeta['end_point'] : [];
            if (GoogleMapsUrlService::pointValue($start) === '' || GoogleMapsUrlService::pointValue($end) === '') {
                $summary['missing_endpoints']++;
                self::appendRoute($summary, $routeId, 'missing_endpoints', 0, 0, 'Partida ou chegada em falta.');
                continue;
            }

            $preview = RouteOptimizationService::previewSystem([
                'route_id' => $routeId,
                'mode' => $routingMode,
                'force_refresh' => 0,
            ]);
            if (is_wp_error($preview)) {
                $summary['failed']++;
                self::appendRoute($summary, $routeId, 'failed', 0, 0, $preview->get_error_message());
                continue;
            }

            $savingKm = max(0.0, (float)($preview['saving_km'] ?? 0));
            $savingMinutes = max(0.0, (float)($preview['saving_minutes'] ?? 0));
            $changed = !empty($preview['changed']);
            if (!$changed) {
                $summary['unchanged']++;
                self::appendRoute($summary, $routeId, 'unchanged', $savingKm, $savingMinutes, 'A ordem atual já é adequada.');
                continue;
            }

            $summary['would_change']++;
            $summary['saving_km'] += $savingKm;
            $summary['saving_minutes'] += $savingMinutes;
            if ($mode === 'apply') {
                $applied = RouteOptimizationService::applyToRouteSystem($routeId, [
                    'mode' => $routingMode,
                    'manual_order_locked' => 0,
                ]);
                if (is_wp_error($applied)) {
                    $summary['failed']++;
                    self::appendRoute($summary, $routeId, 'failed', $savingKm, $savingMinutes, $applied->get_error_message());
                    continue;
                }
                $summary['applied']++;
                self::appendRoute($summary, $routeId, 'applied', $savingKm, $savingMinutes, 'Nova sequência guardada.');
            } else {
                self::appendRoute($summary, $routeId, 'would_change', $savingKm, $savingMinutes, 'Melhoria disponível.');
            }
        }

        $summary['saving_km'] = round((float)$summary['saving_km'], 2);
        $summary['saving_minutes'] = round((float)$summary['saving_minutes'], 1);
        $summary['routes'] = array_slice($summary['routes'], -20);
        $total = max((int)($row['total_rows'] ?? 0), self::countEligible($meta));

        return [
            'done' => count($routes) < $limit,
            'cursor' => $lastId,
            'processed' => count($routes),
            'total' => $total,
            'data' => $summary,
        ];
    }

    private static function where(array $filters, int $cursor, bool $withCursor): array {
        global $wpdb;
        $where = [
            "r.status IN ('draft','planned')",
            "(r.meta_json LIKE '%\"self_planned\":1%' OR r.meta_json LIKE '%\"self_planned\":true%' OR r.meta_json LIKE '%planning_source%front%' OR r.meta_json LIKE '%planning_source%flutterflow_mobile%')",
            "(SELECT COUNT(*) FROM {$wpdb->prefix}routespro_route_stops rs WHERE rs.route_id=r.id) BETWEEN 1 AND 8",
        ];
        $args = [];
        if ($withCursor) {
            $where[] = 'r.id>%d';
            $args[] = $cursor;
        }
        $clientId = absint($filters['client_id'] ?? 0);
        $projectId = absint($filters['project_id'] ?? 0);
        $ownerId = absint($filters['owner_user_id'] ?? 0);
        $dateFrom = self::date((string)($filters['date_from'] ?? ''));
        $dateTo = self::date((string)($filters['date_to'] ?? ''));
        if ($clientId) { $where[] = 'r.client_id=%d'; $args[] = $clientId; }
        if ($projectId) { $where[] = 'r.project_id=%d'; $args[] = $projectId; }
        if ($ownerId) { $where[] = 'r.owner_user_id=%d'; $args[] = $ownerId; }
        if ($dateFrom !== '') { $where[] = 'r.date>=%s'; $args[] = $dateFrom; }
        if ($dateTo !== '') { $where[] = 'r.date<=%s'; $args[] = $dateTo; }
        return [$where, $args];
    }

    private static function isFrontRoute(array $meta): bool {
        if (!empty($meta['self_planned'])) return true;
        $source = sanitize_key((string)($meta['planning_source'] ?? ''));
        return in_array($source, ['flutterflow_mobile', 'merchandiser_front', 'merchandiser_front_edit', 'front_discovery'], true)
            || strpos($source, 'front') !== false;
    }

    private static function appendRoute(array &$summary, int $routeId, string $status, float $savingKm, float $savingMinutes, string $message): void {
        $summary['routes'][] = [
            'route_id' => $routeId,
            'status' => $status,
            'saving_km' => round($savingKm, 2),
            'saving_minutes' => round($savingMinutes, 1),
            'message' => sanitize_text_field($message),
        ];
    }

    private static function decodeMeta($raw): array {
        if (is_array($raw)) return $raw;
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    private static function date(string $value): string {
        $value = trim($value);
        if ($value === '') return '';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }
}
