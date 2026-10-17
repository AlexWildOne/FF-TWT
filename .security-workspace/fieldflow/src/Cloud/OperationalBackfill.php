<?php
namespace RoutesPro\Cloud;

use RoutesPro\Core\CanonicalStateService;
use RoutesPro\Core\DataContract;

if (!defined('ABSPATH')) exit;

/**
 * Phase 3B, controlled operational snapshot for Locations, Routes and Visits.
 *
 * Snapshot events are queued directly through the certified Azure Cloud queue,
 * without replaying route/location mutation hooks. UUID v5-style identifiers are
 * deterministic inside this initial snapshot, so retries remain idempotent.
 */
final class OperationalBackfill {
    public const VERSION = '1.0.0';
    private const UUID_NAMESPACE = 'fieldflow-cloud-operational-backfill-v1';
    private const OPTION_PREFIX = 'fieldflow_cloud_backfill_operations_';

    private static array $entities = [
        'locations' => [
            'table' => 'locations',
            'event' => 'location.snapshot',
            'label' => 'Lojas / PDVs',
        ],
        'routes' => [
            'table' => 'routes',
            'event' => 'route.snapshot',
            'label' => 'Rotas',
        ],
        'visits' => [
            'table' => 'route_stops',
            'event' => 'visit.snapshot',
            'label' => 'Visitas / Paragens',
        ],
    ];

    public static function status(): array {
        $out = [
            'available' => true,
            'cloud_ready' => AzureConnectionManager::ready(),
            'sync_enabled' => AzureConnectionManager::syncEnabled(),
            'queue_ready' => !empty(AzureSyncFoundation::health()['table_ready']),
            'entities' => [],
            'queue_pending' => 0,
            'queue_running' => 0,
            'queue_dead' => 0,
            'queue_completed' => 0,
            'fully_completed' => true,
        ];

        foreach (array_keys(self::$entities) as $entity) {
            $status = self::entityStatus($entity);
            $out['entities'][$entity] = $status;
            if (empty($status['available'])) $out['available'] = false;
            foreach (['queue_pending','queue_running','queue_dead','queue_completed'] as $key) {
                $out[$key] += (int)($status[$key] ?? 0);
            }
            if (empty($status['fully_completed'])) $out['fully_completed'] = false;
        }

        return $out;
    }

    public static function startAll(bool $reset = false): array {
        $result = ['ok'=>true,'entities'=>[]];
        foreach (array_keys(self::$entities) as $entity) {
            $started = self::startEntity($entity, $reset);
            $result['entities'][$entity] = $started;
            if (empty($started['ok'])) $result['ok'] = false;
        }
        $result['status'] = self::status();
        return $result;
    }

    public static function startEntity(string $entity, bool $reset = false): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg) return ['ok'=>false,'reason'=>'invalid_entity'];
        $table = $wpdb->prefix . 'routespro_' . $cfg['table'];
        if (!self::tableExists($table)) return ['ok'=>false,'reason'=>'table_missing','entity'=>$entity];

        $maxOption = self::option($entity, 'max_id');
        $cursorOption = self::option($entity, 'cursor');
        $startedOption = self::option($entity, 'started_at');
        $existingMax = max(0, (int)get_option($maxOption, 0));
        if ($existingMax > 0 && !$reset) {
            return array_merge(['ok'=>true,'already_started'=>true,'entity'=>$entity], self::entityStatus($entity));
        }

        $maxId = max(0, (int)$wpdb->get_var("SELECT MAX(id) FROM {$table}"));
        update_option($cursorOption, 0, false);
        update_option($maxOption, $maxId, false);
        update_option($startedOption, current_time('mysql', true), false);
        return array_merge(['ok'=>true,'already_started'=>false,'entity'=>$entity], self::entityStatus($entity));
    }

    public static function enqueueNext(string $entity, int $limit = 25): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg) return ['ok'=>false,'reason'=>'invalid_entity','enqueued'=>0];
        $limit = max(1, min(100, $limit));
        $status = self::entityStatus($entity);
        if (empty($status['available'])) return ['ok'=>false,'reason'=>'table_missing','enqueued'=>0];
        if (empty($status['started'])) $status = self::startEntity($entity, false);
        if (!AzureConnectionManager::ready()) return ['ok'=>false,'reason'=>'cloud_not_configured','enqueued'=>0];
        if (!AzureConnectionManager::syncEnabled()) return ['ok'=>false,'reason'=>'cloud_sync_disabled','enqueued'=>0];
        if (empty(AzureSyncFoundation::health()['table_ready'])) return ['ok'=>false,'reason'=>'cloud_queue_missing','enqueued'=>0];

        $kickLimit = max(1, min(25, $limit));
        $dispatchBefore = !empty($status['queue_pending'])
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];

        $table = $wpdb->prefix . 'routespro_' . $cfg['table'];
        $cursor = max(0, (int)($status['cursor'] ?? 0));
        $maxId = max(0, (int)($status['max_id'] ?? 0));
        if ($maxId <= 0) {
            $after = self::entityStatus($entity);
            return ['ok'=>true,'entity'=>$entity,'enqueued'=>0,'completed'=>!empty($after['fully_completed']),'dispatch_before'=>$dispatchBefore,'status'=>$after];
        }

        $rows = self::candidateRows($entity, $cursor, $maxId, $limit);

        $enqueued = 0;
        $duplicates = 0;
        $failed = 0;
        $skipped = 0;
        $skippedReasons = [];
        $lastId = $cursor;
        foreach ($rows as $row) {
            $entityId = absint($row['id'] ?? 0);
            if ($entityId <= 0) continue;
            try {
                $built = self::buildEntity($entity, $entityId, true);
                if (empty($built['ok'])) {
                    // A historical row can legitimately become orphaned while a snapshot is running
                    // (for example a legacy route_stop whose route was deleted). Do not let one
                    // invalid historical row permanently block the cursor. Skip it, record the
                    // integrity reason and continue with the remaining eligible rows.
                    $skipped++;
                    $reason = sanitize_key((string)($built['reason'] ?? 'invalid_snapshot_row'));
                    $skippedReasons[$reason] = (int)($skippedReasons[$reason] ?? 0) + 1;
                    $lastId = $entityId;
                    update_option(self::option($entity, 'cursor'), $lastId, false);
                    continue;
                }
                $result = AzureSyncFoundation::enqueue(
                    (string)$cfg['event'],
                    (array)$built['contract'],
                    (array)$built['context'],
                    self::snapshotEventUuid($entity, $entityId)
                );
                if (empty($result['ok'])) {
                    $failed++;
                    break;
                }
                if (!empty($result['duplicate'])) $duplicates++;
                else $enqueued++;
                $lastId = $entityId;
                update_option(self::option($entity, 'cursor'), $lastId, false);
            } catch (\Throwable $e) {
                $failed++;
                if (class_exists('\RoutesPro\Support\Logger')) {
                    \RoutesPro\Support\Logger::captureThrowable('Falha no snapshot operacional Cloud.', $e, [
                        'context_key' => 'cloud_operational_backfill',
                        'entity_type' => $entity,
                        'entity_id' => $entityId,
                    ]);
                }
                break;
            }
        }

        $queueNow = self::entityQueueStats($entity);
        $dispatchAfter = ($enqueued > 0 || $duplicates > 0 || (int)$queueNow['pending'] > 0)
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];

        $after = self::entityStatus($entity);
        return [
            'ok' => $failed === 0 && !empty($dispatchAfter['ok']),
            'entity' => $entity,
            'enqueued' => $enqueued,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'skipped' => $skipped,
            'skipped_reasons' => $skippedReasons,
            'cursor' => $lastId,
            'completed' => !empty($after['fully_completed']),
            'dispatch_before' => $dispatchBefore,
            'dispatch_after' => $dispatchAfter,
            'queue_pending' => (int)($after['queue_pending'] ?? 0),
            'queue_running' => (int)($after['queue_running'] ?? 0),
            'queue_dead' => (int)($after['queue_dead'] ?? 0),
            'status' => $after,
        ];
    }

    public static function processPendingNow(int $limit = 25): array {
        $limit = max(1, min(25, $limit));
        if (!AzureConnectionManager::ready()) return ['ok'=>false,'reason'=>'cloud_not_configured'];
        if (!AzureConnectionManager::syncEnabled()) return ['ok'=>false,'reason'=>'cloud_sync_disabled'];
        $dispatch = AzureSyncFoundation::dispatchNow($limit);
        $status = self::status();
        return [
            'ok' => !empty($dispatch['ok']),
            'dispatch' => $dispatch,
            'queue_pending' => (int)($status['queue_pending'] ?? 0),
            'queue_running' => (int)($status['queue_running'] ?? 0),
            'queue_dead' => (int)($status['queue_dead'] ?? 0),
            'completed' => !empty($status['fully_completed']),
            'status' => $status,
        ];
    }

    /** Build a location snapshot without personal contact fields. */
    public static function buildLocationContract(int $locationId, bool $backfill = false): array {
        global $wpdb;
        $locationId = absint($locationId);
        if ($locationId <= 0) return ['ok'=>false,'reason'=>'invalid_location'];
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}locations WHERE id=%d LIMIT 1", $locationId), ARRAY_A);
        if (!$row) return ['ok'=>false,'reason'=>'location_missing'];

        $clientId = absint($row['client_id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        $scope = self::scopeNames($clientId, $projectId);
        if ($clientId <= 0) $clientId = (int)$scope['client_id'];

        $profile = [];
        $profileTable = $px . 'location_profiles';
        if (self::tableExists($profileTable)) {
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT store_format,assortment_profile,assortment_profile_source,assortment_profile_confidence,revision_no,updated_at FROM {$profileTable} WHERE location_id=%d LIMIT 1",
                $locationId
            ), ARRAY_A) ?: [];
        }
        $assortmentProfile = sanitize_key((string)($profile['assortment_profile'] ?? 'general')) ?: 'general';
        $assortment = [
            'store_format' => sanitize_text_field((string)($profile['store_format'] ?? '')),
            'profile' => $assortmentProfile,
            'source' => sanitize_key((string)($profile['assortment_profile_source'] ?? '')),
            'confidence' => max(0, min(100, absint($profile['assortment_profile_confidence'] ?? 0))),
            'revision_no' => absint($profile['revision_no'] ?? 0),
            'updated_at' => (string)($profile['updated_at'] ?? ''),
        ];
        if (class_exists('\RoutesPro\Forms\ProductCardex') && method_exists('\RoutesPro\Forms\ProductCardex', 'assortment_profile_context')) {
            $context = \RoutesPro\Forms\ProductCardex::assortment_profile_context($locationId, $clientId, $projectId, $assortmentProfile);
            $assortment['cardex_id'] = absint($context['cardex_id'] ?? 0);
            $assortment['cardex_name'] = sanitize_text_field((string)($context['cardex_name'] ?? ''));
            $assortment['insignia'] = sanitize_text_field((string)($context['insignia'] ?? ''));
            $assortment['profile_label'] = sanitize_text_field((string)($context['selected_label'] ?? 'Geral'));
            $assortment['cumulative'] = !empty($context['cumulative']);
        }

        $data = [
            'location_id' => $locationId,
            'client_id' => $clientId,
            'client_name' => $scope['client_name'],
            'project_id' => $projectId,
            'project_name' => $scope['project_name'],
            'name' => sanitize_text_field((string)($row['name'] ?? '')),
            'address' => sanitize_text_field((string)($row['address'] ?? '')),
            'district' => sanitize_text_field((string)($row['district'] ?? '')),
            'county' => sanitize_text_field((string)($row['county'] ?? '')),
            'city' => sanitize_text_field((string)($row['city'] ?? '')),
            'parish' => sanitize_text_field((string)($row['parish'] ?? '')),
            'postal_code' => sanitize_text_field((string)($row['postal_code'] ?? '')),
            'country' => sanitize_text_field((string)($row['country'] ?? '')),
            'lat' => $row['lat'] ?? null,
            'lng' => $row['lng'] ?? null,
            'window_start' => (string)($row['window_start'] ?? ''),
            'window_end' => (string)($row['window_end'] ?? ''),
            'service_time_min' => isset($row['service_time_min']) ? (int)$row['service_time_min'] : 0,
            'tags' => sanitize_text_field((string)($row['tags'] ?? '')),
            'external_ref' => sanitize_text_field((string)($row['external_ref'] ?? '')),
            'source' => sanitize_key((string)($row['source'] ?? '')),
            'is_active' => !isset($row['is_active']) || !empty($row['is_active']),
            'is_validated' => !empty($row['is_validated']),
            'assortment' => $assortment,
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'cloud_backfill' => $backfill,
            'cloud_backfill_version' => $backfill ? self::VERSION : '',
        ];
        $contract = DataContract::state('location', $data);
        $context = [
            'entity_type' => 'location',
            'entity_id' => $locationId,
            'location_id' => $locationId,
            'client_id' => $clientId,
            'project_id' => $projectId,
        ];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context,'data'=>$data];
    }

    public static function buildRouteContract(int $routeId, bool $backfill = false): array {
        global $wpdb;
        $routeId = absint($routeId);
        if ($routeId <= 0) return ['ok'=>false,'reason'=>'invalid_route'];
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$px}routes WHERE id=%d LIMIT 1", $routeId), ARRAY_A);
        if (!$row) return ['ok'=>false,'reason'=>'route_missing'];

        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status IN ('done','completed','visited') THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status IN ('in_progress','active','started') THEN 1 ELSE 0 END) AS in_progress,
                SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed
             FROM {$px}route_stops WHERE route_id=%d",
            $routeId
        ), ARRAY_A) ?: [];
        $total = (int)($counts['total'] ?? 0);
        $completed = (int)($counts['completed'] ?? 0);
        $inProgress = (int)($counts['in_progress'] ?? 0);
        $failed = (int)($counts['failed'] ?? 0);
        $pending = max(0, $total - $completed - $inProgress - $failed);
        $legacyStatus = sanitize_key((string)($row['status'] ?? 'draft'));
        $status = $legacyStatus;
        if (!in_array($legacyStatus, ['cancelled','canceled'], true)) {
            if ($total > 0 && ($completed + $failed) >= $total) $status = 'completed';
            elseif ($completed > 0 || $failed > 0 || $inProgress > 0) $status = 'in_progress';
            elseif ($total > 0) $status = 'planned';
            else $status = 'draft';
        }

        $clientId = absint($row['client_id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        $scope = self::scopeNames($clientId, $projectId);
        if ($clientId <= 0) $clientId = (int)$scope['client_id'];
        $issues = $status !== $legacyStatus ? [['route_id'=>$routeId,'code'=>'route_status_mismatch']] : [];
        $data = [
            'route_id' => $routeId,
            'client_id' => $clientId,
            'client_name' => $scope['client_name'],
            'project_id' => $projectId,
            'project_name' => $scope['project_name'],
            'owner_user_id' => absint($row['owner_user_id'] ?? 0),
            'date' => (string)($row['date'] ?? ''),
            'status' => $status,
            'legacy_status' => $legacyStatus,
            'counts' => [
                'total' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'failed' => $failed,
                'pending' => $pending,
            ],
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'integrity' => $issues ? 'warning' : 'valid',
            'issues' => $issues,
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'cloud_backfill' => $backfill,
            'cloud_backfill_version' => $backfill ? self::VERSION : '',
        ];

        $contract = DataContract::state('route', $data);
        $context = [
            'entity_type' => 'route',
            'entity_id' => $routeId,
            'route_id' => $routeId,
            'client_id' => $clientId,
            'project_id' => $projectId,
            'actor_user_id' => absint($data['owner_user_id'] ?? 0),
        ];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context,'data'=>$data];
    }

    public static function buildVisitContract(int $routeStopId, bool $backfill = false): array {
        global $wpdb;
        $routeStopId = absint($routeStopId);
        if ($routeStopId <= 0) return ['ok'=>false,'reason'=>'invalid_visit'];
        $px = $wpdb->prefix . 'routespro_';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT rs.*, r.client_id, r.project_id, r.date AS route_date, r.owner_user_id, l.name AS location_name
             FROM {$px}route_stops rs
             INNER JOIN {$px}routes r ON r.id=rs.route_id
             LEFT JOIN {$px}locations l ON l.id=rs.location_id
             WHERE rs.id=%d LIMIT 1",
            $routeStopId
        ), ARRAY_A);
        if (!$row) return ['ok'=>false,'reason'=>'visit_missing'];

        $canonical = CanonicalStateService::visit($routeStopId);
        $data = (array)($canonical['data'] ?? []);
        $clientId = absint($data['client_id'] ?? $row['client_id'] ?? 0);
        $projectId = absint($data['project_id'] ?? $row['project_id'] ?? 0);
        $scope = self::scopeNames($clientId, $projectId);
        if ($clientId <= 0) $clientId = (int)$scope['client_id'];

        $data['route_stop_id'] = $routeStopId;
        $data['route_id'] = absint($data['route_id'] ?? $row['route_id'] ?? 0);
        $data['location_id'] = absint($data['location_id'] ?? $row['location_id'] ?? 0);
        $data['location_name'] = sanitize_text_field((string)($row['location_name'] ?? ''));
        $data['client_id'] = $clientId;
        $data['client_name'] = $scope['client_name'];
        $data['project_id'] = $projectId;
        $data['project_name'] = $scope['project_name'];
        $data['owner_user_id'] = absint($data['owner_user_id'] ?? $row['owner_user_id'] ?? 0);
        $data['route_date'] = (string)($data['route_date'] ?? $row['route_date'] ?? '');
        $data['seq'] = isset($row['seq']) ? (int)$row['seq'] : 0;
        $data['planned_arrival'] = (string)($row['planned_arrival'] ?? '');
        $data['planned_departure'] = (string)($row['planned_departure'] ?? '');
        $data['arrived_at'] = (string)($row['arrived_at'] ?? ($data['timestamps']['arrived_at'] ?? ''));
        $data['departed_at'] = (string)($row['departed_at'] ?? ($data['timestamps']['departed_at'] ?? ''));
        $data['duration_s'] = isset($row['duration_s']) && $row['duration_s'] !== null ? (int)$row['duration_s'] : null;
        $data['fail_reason'] = sanitize_text_field((string)($row['fail_reason'] ?? ''));
        $data['qty'] = isset($row['qty']) && $row['qty'] !== null ? (float)$row['qty'] : null;
        $data['weight'] = isset($row['weight']) && $row['weight'] !== null ? (float)$row['weight'] : null;
        $data['volume'] = isset($row['volume']) && $row['volume'] !== null ? (float)$row['volume'] : null;
        $data['real_lat'] = $row['real_lat'] ?? null;
        $data['real_lng'] = $row['real_lng'] ?? null;
        $data['cloud_backfill'] = $backfill;
        $data['cloud_backfill_version'] = $backfill ? self::VERSION : '';

        $contract = DataContract::state('visit', $data);
        $context = [
            'entity_type' => 'visit',
            'entity_id' => $routeStopId,
            'route_stop_id' => $routeStopId,
            'route_id' => absint($data['route_id'] ?? 0),
            'location_id' => absint($data['location_id'] ?? 0),
            'submission_id' => absint($data['submission_id'] ?? 0),
            'client_id' => $clientId,
            'project_id' => $projectId,
            'actor_user_id' => absint($data['owner_user_id'] ?? 0),
        ];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context,'data'=>$data];
    }

    /** Deterministic UUID for related live snapshots emitted with a visit. */
    public static function relatedEventUuid(string $sourceEventUuid, string $eventName, int $entityId): string {
        return self::uuidFromSeed('live|' . sanitize_text_field($sourceEventUuid) . '|' . sanitize_key($eventName) . '|' . absint($entityId));
    }

    private static function buildEntity(string $entity, int $entityId, bool $backfill): array {
        if ($entity === 'locations') return self::buildLocationContract($entityId, $backfill);
        if ($entity === 'routes') return self::buildRouteContract($entityId, $backfill);
        if ($entity === 'visits') return self::buildVisitContract($entityId, $backfill);
        return ['ok'=>false,'reason'=>'invalid_entity'];
    }

    private static function entityStatus(string $entity): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg) return ['available'=>false,'started'=>false,'entity'=>$entity];
        $table = $wpdb->prefix . 'routespro_' . $cfg['table'];
        if (!self::tableExists($table)) {
            return ['available'=>false,'started'=>false,'entity'=>$entity,'label'=>$cfg['label'],'cursor'=>0,'max_id'=>0,'total'=>0,'remaining'=>0,'enqueued'=>0,'queue_pending'=>0,'queue_running'=>0,'queue_dead'=>0,'queue_completed'=>0,'fully_completed'=>false];
        }

        $cursor = max(0, (int)get_option(self::option($entity, 'cursor'), 0));
        $maxId = max(0, (int)get_option(self::option($entity, 'max_id'), 0));
        $startedAt = (string)get_option(self::option($entity, 'started_at'), '');
        $started = $maxId > 0 || $startedAt !== '';
        $total = 0;
        $remaining = 0;
        if ($started && $maxId > 0) {
            $total = self::eligibleCount($entity, 0, $maxId);
            $remaining = self::eligibleCount($entity, $cursor, $maxId);
        }
        $queue = self::entityQueueStats($entity);
        $enqueued = max(0, $total - $remaining);
        $ignoredIntegrity = self::ignoredIntegrityCount($entity, $maxId);
        return [
            'available' => true,
            'entity' => $entity,
            'label' => $cfg['label'],
            'event_name' => $cfg['event'],
            'started' => $started,
            'started_at' => $startedAt,
            'cursor' => $cursor,
            'max_id' => $maxId,
            'total' => $total,
            'remaining' => $remaining,
            'enqueued' => $enqueued,
            'queue_pending' => (int)$queue['pending'],
            'queue_running' => (int)$queue['running'],
            'queue_dead' => (int)$queue['dead'],
            'queue_completed' => (int)$queue['completed'],
            'ignored_integrity' => $ignoredIntegrity,
            'fully_completed' => $started && $remaining === 0 && (int)$queue['pending'] === 0 && (int)$queue['running'] === 0 && (int)$queue['dead'] === 0,
        ];
    }

    private static function entityQueueStats(string $entity): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        $stats = ['pending'=>0,'running'=>0,'dead'=>0,'completed'=>0];
        if (!$cfg) return $stats;
        $table = AzureSyncFoundation::table();
        if (!self::tableExists($table)) return $stats;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(status='pending') AS pending,
                SUM(status='running') AS running,
                SUM(status='dead') AS dead,
                SUM(status='completed') AS completed
             FROM {$table}
             WHERE destination='azure'
               AND event_name=%s
               AND payload_json LIKE %s",
            (string)$cfg['event'],
            '%\"cloud_backfill\":true%'
        ), ARRAY_A) ?: [];
        foreach ($stats as $key=>$value) $stats[$key] = (int)($row[$key] ?? 0);
        return $stats;
    }

    /**
     * Returns only rows that can be snapshotted safely. Visits require an
     * existing parent route; old orphan route_stops are excluded from the
     * operational snapshot instead of blocking the cursor forever.
     */
    private static function candidateRows(string $entity, int $cursor, int $maxId, int $limit): array {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg || $maxId <= 0) return [];
        $limit = max(1, min(100, $limit));
        $cursor = max(0, $cursor);
        $maxId = max(0, $maxId);
        $px = $wpdb->prefix . 'routespro_';

        if ($entity === 'visits') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT rs.id
                 FROM {$px}route_stops rs
                 INNER JOIN {$px}routes r ON r.id=rs.route_id
                 WHERE rs.id>%d AND rs.id<=%d
                 ORDER BY rs.id ASC
                 LIMIT %d",
                $cursor, $maxId, $limit
            ), ARRAY_A) ?: [];
        }

        $table = $px . $cfg['table'];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE id>%d AND id<=%d ORDER BY id ASC LIMIT %d",
            $cursor, $maxId, $limit
        ), ARRAY_A) ?: [];
    }

    /** Count eligible snapshot rows between cursor (exclusive) and max id. */
    private static function eligibleCount(string $entity, int $cursor, int $maxId): int {
        global $wpdb;
        $cfg = self::entityConfig($entity);
        if (!$cfg || $maxId <= 0) return 0;
        $cursor = max(0, $cursor);
        $maxId = max(0, $maxId);
        $px = $wpdb->prefix . 'routespro_';

        if ($entity === 'visits') {
            return (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$px}route_stops rs
                 INNER JOIN {$px}routes r ON r.id=rs.route_id
                 WHERE rs.id>%d AND rs.id<=%d",
                $cursor, $maxId
            ));
        }

        $table = $px . $cfg['table'];
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE id>%d AND id<=%d",
            $cursor, $maxId
        ));
    }

    /** Number of historical visit rows intentionally excluded for integrity. */
    private static function ignoredIntegrityCount(string $entity, int $maxId): int {
        global $wpdb;
        if ($entity !== 'visits' || $maxId <= 0) return 0;
        $px = $wpdb->prefix . 'routespro_';
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$px}route_stops rs
             LEFT JOIN {$px}routes r ON r.id=rs.route_id
             WHERE rs.id<=%d AND r.id IS NULL",
            $maxId
        ));
    }

    private static function scopeNames(int $clientId, int $projectId): array {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $projectName = '';
        if ($projectId > 0) {
            $project = $wpdb->get_row($wpdb->prepare("SELECT id,client_id,name FROM {$px}projects WHERE id=%d LIMIT 1", $projectId), ARRAY_A);
            if ($project) {
                if ($clientId <= 0) $clientId = absint($project['client_id'] ?? 0);
                $projectName = sanitize_text_field((string)($project['name'] ?? ''));
            }
        }
        $clientName = $clientId > 0 ? sanitize_text_field((string)$wpdb->get_var($wpdb->prepare("SELECT name FROM {$px}clients WHERE id=%d LIMIT 1", $clientId))) : '';
        return ['client_id'=>$clientId,'client_name'=>$clientName,'project_name'=>$projectName];
    }

    private static function snapshotEventUuid(string $entity, int $entityId): string {
        return self::uuidFromSeed('snapshot|' . sanitize_key($entity) . '|' . absint($entityId));
    }

    private static function uuidFromSeed(string $seed): string {
        $tenant = TenantIdentity::tenantId();
        $hash = substr(sha1(self::UUID_NAMESPACE . '|' . $tenant . '|' . $seed), 0, 32);
        $hash[12] = '5';
        $variant = hexdec($hash[16]);
        $hash[16] = dechex(($variant & 0x3) | 0x8);
        return sprintf('%s-%s-%s-%s-%s', substr($hash,0,8), substr($hash,8,4), substr($hash,12,4), substr($hash,16,4), substr($hash,20,12));
    }

    private static function entityConfig(string $entity): ?array {
        $entity = sanitize_key($entity);
        return self::$entities[$entity] ?? null;
    }

    private static function option(string $entity, string $suffix): string {
        return self::OPTION_PREFIX . sanitize_key($entity) . '_' . sanitize_key($suffix);
    }

    private static function tableExists(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
