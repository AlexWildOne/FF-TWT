<?php
namespace RoutesPro\Mobile;

use RoutesPro\Briefings\BriefingsController;
use RoutesPro\Rest\EventsController;
use RoutesPro\Stocks\StocksController;
use RoutesPro\Support\Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

/**
 * Offline-first contract shared by the native app and the WebView runtime.
 *
 * Keeps existing online endpoints intact while adding an incremental bundle,
 * an idempotent batch synchroniser, explicit conflicts and technical device
 * diagnostics. Older app binaries continue to use the unchanged endpoints.
 */
class OfflineSyncService {
    public const SCHEMA_VERSION = 2;
    public const CACHE_TTL = 900;
    public const MAX_DAYS = 14;
    public const MAX_ROUTES = 40;
    public const MAX_STOPS = 240;
    public const MAX_BATCH = 40;
    public const CURSOR_TTL = 172800;

    public static function deviceTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_mobile_sync_devices';
    }

    /**
     * Lightweight authenticated connectivity probe for the zero-loss runtime.
     * It deliberately avoids asserting offline permission so the client can
     * distinguish weak connectivity from an administrative device policy.
     */
    public static function ping(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('fieldflow_offline_auth_required', 'Authentication required.', ['status' => 401]);
        }
        $policy = class_exists('\RoutesPro\Mobile\DeviceManager') ? DeviceManager::currentPolicy($request) : [];
        return new WP_REST_Response([
            'ok' => true,
            'server_time' => current_time('mysql', true),
            'server_local_time' => current_time('mysql'),
            'operational_date' => current_time('Y-m-d'),
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : '',
            'plugin_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            'policy' => is_array($policy) ? $policy : [],
        ], 200);
    }

    public static function bootstrap(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('fieldflow_offline_auth_required', 'Authentication required.', ['status' => 401]);
        }
        $devicePolicy = class_exists('\RoutesPro\Mobile\DeviceManager') ? DeviceManager::assertOfflineAllowed($request) : [];
        if (is_wp_error($devicePolicy)) return $devicePolicy;

        $from = self::date((string)$request->get_param('date_from')) ?: current_time('Y-m-d');
        $to = self::date((string)$request->get_param('date_to')) ?: gmdate('Y-m-d', strtotime($from . ' +6 days'));
        if (strtotime($to) < strtotime($from)) $to = $from;
        $maxTo = gmdate('Y-m-d', strtotime($from . ' +' . (self::MAX_DAYS - 1) . ' days'));
        if (strtotime($to) > strtotime($maxTo)) $to = $maxTo;

        $clientId = absint($request->get_param('client_id'));
        $projectId = absint($request->get_param('project_id'));
        $refresh = !empty($request->get_param('refresh'));
        $userId = get_current_user_id();
        $cacheGeneration = is_array($devicePolicy) ? max(1, absint($devicePolicy['cache_generation'] ?? 1)) : 1;
        $routeCacheGeneration = max(1, absint(get_user_meta($userId, '_fieldflow_route_cache_generation', true)));
        $operationalContentRevision = $projectId > 0 && class_exists('\RoutesPro\Mobile\OperationalContentRevision')
            ? OperationalContentRevision::current($projectId)
            : 1;
        $cacheKey = 'ff_offline_bundle_' . md5(implode('|', [$userId, $from, $to, $clientId, $projectId, self::SCHEMA_VERSION, $cacheGeneration, $routeCacheGeneration, $operationalContentRevision]));
        $payload = !$refresh ? get_transient($cacheKey) : false;

        if (!is_array($payload)) {
            $controller = new Controller();
            $routeRequest = new WP_REST_Request('GET', '/' . Controller::NS . '/my-routes');
            $routeRequest->set_param('date_from', $from);
            $routeRequest->set_param('date_to', $to);
            $routeRequest->set_param('client_id', $clientId);
            $routeRequest->set_param('project_id', $projectId);
            $routeRequest->set_param('limit', self::MAX_ROUTES);
            $routesResult = $controller->my_routes($routeRequest);
            if (is_wp_error($routesResult)) return $routesResult;
            $routeData = self::data($routesResult);
            $routes = array_slice((array)($routeData['routes'] ?? []), 0, self::MAX_ROUTES);

            $routeDetails = [];
            $stops = [];
            $locations = [];
            $forms = [];
            $locationContexts = [];
            $stopCount = 0;
            foreach ($routes as $routeSummary) {
                $routeId = absint($routeSummary['id'] ?? 0);
                if (!$routeId) continue;
                $detailRequest = new WP_REST_Request('GET', '/' . Controller::NS . '/routes/' . $routeId);
                $detailRequest->set_param('id', $routeId);
                $detailResult = $controller->route($detailRequest);
                if (is_wp_error($detailResult)) continue;
                $detailData = self::data($detailResult);
                $route = (array)($detailData['route'] ?? []);
                if (!$route) continue;
                $routeStops = (array)($route['stops'] ?? []);
                unset($route['stops']);
                $routeDetails[(string)$routeId] = $route;

                foreach ($routeStops as $stop) {
                    if ($stopCount >= self::MAX_STOPS) break 2;
                    $stopId = absint($stop['id'] ?? 0);
                    if (!$stopId) continue;
                    $stopCount++;
                    $stops[(string)$stopId] = $stop;
                    $locationId = absint($stop['location_id'] ?? 0);
                    if ($locationId) {
                        $locationContexts[$locationId] = [
                            'route_id' => $routeId,
                            'project_id' => absint($route['project_id'] ?? $projectId),
                        ];
                        $locations[(string)$locationId] = [
                            'id' => $locationId,
                            'name' => sanitize_text_field((string)($stop['location_name'] ?? '')),
                            'address' => sanitize_text_field((string)($stop['address'] ?? '')),
                            'city' => sanitize_text_field((string)($stop['city'] ?? '')),
                            'postal_code' => sanitize_text_field((string)($stop['postal_code'] ?? '')),
                            'phone' => sanitize_text_field((string)($stop['phone'] ?? '')),
                            'lat' => isset($stop['lat']) && $stop['lat'] !== '' ? (float)$stop['lat'] : null,
                            'lng' => isset($stop['lng']) && $stop['lng'] !== '' ? (float)$stop['lng'] : null,
                            'store_profile' => null,
                        ];
                    }
                    if (Settings::feature('reporting')) {
                        $formResult = FormPayloadService::for_stop($stopId, $userId, false);
                        if (!is_wp_error($formResult)) {
                            $forms[(string)$stopId] = $formResult;
                        }
                    }
                }
            }

            if ($locationContexts && class_exists('\\RoutesPro\\Stores\\StoreProfileService')) {
                $storeProfiles = \RoutesPro\Stores\StoreProfileService::bulkOfflineSnapshots($locationContexts, $userId);
                foreach ($storeProfiles as $locationId => $snapshot) {
                    $key = (string)$locationId;
                    if (isset($locations[$key])) $locations[$key]['store_profile'] = $snapshot;
                }
                foreach ($forms as $stopKey => $formPayload) {
                    $formLocationId = absint($formPayload['context']['location_id'] ?? 0);
                    if ($formLocationId && isset($storeProfiles[$formLocationId])) {
                        $forms[$stopKey]['store_profile'] = $storeProfiles[$formLocationId];
                    }
                }
            }

            $briefings = [];
            if (Settings::feature('briefings')) {
                $briefingRequest = new WP_REST_Request('GET', '/' . Controller::NS . '/briefings');
                if ($clientId) $briefingRequest->set_param('client_id', $clientId);
                if ($projectId) $briefingRequest->set_param('project_id', $projectId);
                $briefingResult = (new BriefingsController())->mine($briefingRequest);
                if (!is_wp_error($briefingResult)) $briefings = self::data($briefingResult);
            }

            $payload = [
                'schema_version' => self::SCHEMA_VERSION,
                'plugin_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
                'generated_at' => current_time('mysql', true),
                'valid_until' => gmdate('Y-m-d H:i:s', time() + 12 * HOUR_IN_SECONDS),
                'range' => ['date_from' => $from, 'date_to' => $to],
                'scope' => ['client_id' => $clientId, 'project_id' => $projectId],
                'user' => Controller::build_user_payload($userId),
                'routes' => array_values($routes),
                'route_details' => $routeDetails,
                'stops' => $stops,
                'locations' => $locations,
                'forms' => $forms,
                'briefings' => $briefings,
                'device_policy' => is_array($devicePolicy) ? $devicePolicy : [],
                'route_cache_generation' => $routeCacheGeneration,
                'operational_content_revision' => $operationalContentRevision,
                'limits' => [
                    'days' => self::MAX_DAYS,
                    'routes' => self::MAX_ROUTES,
                    'stops' => self::MAX_STOPS,
                    'sync_batch' => self::MAX_BATCH,
                ],
            ];
            $payload = self::decorateBundle($payload);
            $payload['version'] = hash('sha256', wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            set_transient($cacheKey, $payload, self::CACHE_TTL);
        }
        $payload = self::decorateBundle($payload);
        $payload['cursor'] = self::storeCursorSnapshot($payload, $userId);

        $etag = '"' . (string)($payload['version'] ?? hash('sha256', wp_json_encode($payload))) . '"';
        $provided = trim((string)$request->get_header('if-none-match'));
        self::touchDevice($request, [
            'cache_version' => trim($etag, '"'),
            'last_cache_at' => current_time('mysql', true),
        ]);
        if ($provided !== '' && hash_equals($etag, $provided)) {
            $response = new WP_REST_Response(null, 304);
            $response->header('ETag', $etag);
            return $response;
        }
        $response = new WP_REST_Response($payload, 200);
        $response->header('ETag', $etag);
        $response->header('Cache-Control', 'private, max-age=60, must-revalidate');
        return $response;
    }

    public static function delta(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('fieldflow_offline_auth_required', 'Authentication required.', ['status' => 401]);
        }
        $cursor = sanitize_text_field((string)$request->get_param('cursor'));
        if ($cursor === '') {
            return new WP_Error('fieldflow_offline_cursor_required', 'cursor is required.', ['status' => 400]);
        }
        $previous = self::loadCursorSnapshot($cursor, get_current_user_id());
        if (!$previous) {
            return new WP_REST_Response([
                'ok' => true,
                'mode' => 'full_required',
                'reset_required' => true,
                'reason' => 'cursor_expired_or_invalid',
                'server_time' => current_time('mysql', true),
            ], 200);
        }

        $fullRequest = new WP_REST_Request('GET', '/' . Controller::NS . '/offline/bootstrap');
        foreach (['date_from','date_to','client_id','project_id','refresh'] as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') $fullRequest->set_param($key, $value);
        }
        $full = self::bootstrap($fullRequest);
        if (is_wp_error($full)) return $full;
        $payload = self::data($full);
        if (!$payload) {
            return new WP_Error('fieldflow_offline_delta_build_failed', 'Could not build the current offline bundle.', ['status' => 503]);
        }
        $current = self::snapshotForBundle($payload);
        $changes = [];
        $deleted = [];
        foreach (['routes','route_details','stops','locations','forms'] as $group) {
            $currentRows = self::groupMap($payload, $group);
            $oldVersions = is_array($previous['groups'][$group] ?? null) ? $previous['groups'][$group] : [];
            $newVersions = is_array($current['groups'][$group] ?? null) ? $current['groups'][$group] : [];
            $changedRows = [];
            foreach ($newVersions as $id => $revision) {
                if (!isset($oldVersions[$id]) || !hash_equals((string)$oldVersions[$id], (string)$revision)) {
                    if (isset($currentRows[$id])) $changedRows[$id] = $currentRows[$id];
                }
            }
            $removed = array_values(array_map('strval', array_diff(array_keys($oldVersions), array_keys($newVersions))));
            if ($changedRows) $changes[$group] = $group === 'routes' ? array_values($changedRows) : $changedRows;
            if ($removed) $deleted[$group] = $removed;
        }
        foreach (['briefings','user','device_policy'] as $group) {
            $oldHash = (string)($previous['single'][$group] ?? '');
            $newHash = (string)($current['single'][$group] ?? '');
            if ($oldHash === '' || $newHash === '' || !hash_equals($oldHash, $newHash)) {
                $changes[$group] = $payload[$group] ?? ($group === 'briefings' ? [] : null);
            }
        }

        self::touchDevice($request, [
            'cache_version' => (string)($payload['version'] ?? ''),
            'last_cache_at' => current_time('mysql', true),
        ]);
        return new WP_REST_Response([
            'ok' => true,
            'mode' => 'delta',
            'reset_required' => false,
            'base_cursor' => $cursor,
            'cursor' => (string)($payload['cursor'] ?? ''),
            'version' => (string)($payload['version'] ?? ''),
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => (string)($payload['generated_at'] ?? current_time('mysql', true)),
            'server_time' => current_time('mysql', true),
            'range' => (array)($payload['range'] ?? []),
            'scope' => (array)($payload['scope'] ?? []),
            'limits' => (array)($payload['limits'] ?? []),
            'route_cache_generation' => absint($payload['route_cache_generation'] ?? 1),
            'changes' => $changes,
            'deleted' => $deleted,
            'summary' => [
                'changed_groups' => count($changes),
                'deleted_groups' => count($deleted),
                'changed_entities' => self::countNested($changes),
                'deleted_entities' => self::countNested($deleted),
            ],
        ], 200);
    }

    public static function sync(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('fieldflow_offline_auth_required', 'Authentication required.', ['status' => 401]);
        }
        $devicePolicy = class_exists('\RoutesPro\Mobile\DeviceManager') ? DeviceManager::assertOfflineAllowed($request) : [];
        if (is_wp_error($devicePolicy)) return $devicePolicy;
        $body = $request->get_json_params() ?: [];
        $operations = isset($body['operations']) && is_array($body['operations']) ? array_slice($body['operations'], 0, self::MAX_BATCH) : [];
        if (!$operations) {
            return new WP_Error('fieldflow_offline_operations_required', 'At least one queued operation is required.', ['status' => 400]);
        }

        $results = [];
        $completed = 0;
        $failed = 0;
        $retryable = 0;
        $conflicts = 0;
        foreach ($operations as $index => $operation) {
            if (!is_array($operation)) continue;
            $type = sanitize_key((string)($operation['type'] ?? ''));
            $mutationId = sanitize_text_field((string)($operation['mutation_id'] ?? ''));
            $payload = is_array($operation['payload'] ?? null) ? $operation['payload'] : [];
            if (!empty($operation['base_revision']) && empty($payload['base_revision'])) {
                $payload['base_revision'] = sanitize_text_field((string)$operation['base_revision']);
            }
            if (!empty($operation['created_at_ms'])) {
                $payload['_queue_created_at_ms'] = max(0, (int)$operation['created_at_ms']);
            }
            if ($type === '' || $mutationId === '') {
                $results[] = ['index' => $index, 'mutation_id' => $mutationId, 'type' => $type, 'status' => 'failed', 'http_status' => 400, 'error' => 'invalid_operation'];
                $failed++;
                continue;
            }

            $conflict = self::detectConflict($type, $payload, $operation);
            if (is_wp_error($conflict)) {
                $result = $conflict;
            } else {
                $result = MutationStore::execute($mutationId, $type, function() use ($type, $payload) {
                    return self::executeOperation($type, $payload);
                });
            }
            $normal = self::normaliseResult($result);
            $normal['index'] = $index;
            $normal['mutation_id'] = $mutationId;
            $normal['type'] = $type;
            $results[] = $normal;
            if ($normal['status'] === 'completed') {
                $completed++;
            } elseif (($normal['error'] ?? '') === 'fieldflow_offline_conflict') {
                $conflicts++;
            } elseif (!empty($normal['retryable'])) {
                $retryable++;
            } else {
                $failed++;
            }
        }

        self::touchDevice($request, [
            // queue_pending and queue_failed represent unresolved queue state.
            // Conflicts are tracked separately in diagnostics and must never be
            // double-counted as terminal failures.
            'queue_pending' => $retryable,
            'queue_failed' => $failed,
            'last_sync_at' => current_time('mysql', true),
            'last_error' => ($failed || $retryable || $conflicts) ? 'batch_with_errors' : '',
        ]);

        if (class_exists('\\RoutesPro\\Core\\AuditLog')) {
            \RoutesPro\Core\AuditLog::record('offline.sync_batch', 'mobile_device', 0, null, [
                'completed' => $completed,
                'failed' => $failed,
                'retryable' => $retryable,
                'conflicts' => $conflicts,
            ], [
                'actor_user_id' => get_current_user_id(),
                'source' => 'mobile_offline_sync',
            ], ['operations' => count($results)]);
        }

        return new WP_REST_Response([
            'ok' => $failed === 0 && $retryable === 0 && $conflicts === 0,
            'server_time' => current_time('mysql', true),
            'summary' => ['received' => count($results), 'completed' => $completed, 'failed' => $failed, 'retryable' => $retryable, 'conflicts' => $conflicts],
            'results' => $results,
        ], 200);
    }

    public static function status(WP_REST_Request $request) {
        if (!is_user_logged_in()) {
            return new WP_Error('fieldflow_offline_auth_required', 'Authentication required.', ['status' => 401]);
        }
        $body = $request->get_json_params() ?: [];
        $changes = [];
        foreach (['queue_pending','queue_failed'] as $key) {
            $value = $request->get_param($key);
            if ($value === null && array_key_exists($key, $body)) $value = $body[$key];
            if ($value !== null) $changes[$key] = max(0, absint($value));
        }
        if (array_key_exists('last_error', $body)) $changes['last_error'] = sanitize_text_field((string)$body['last_error']);
        $meta = self::sanitiseDeviceMeta(is_array($body['diagnostics'] ?? null) ? $body['diagnostics'] : []);
        if ($meta) $changes['meta_json'] = wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row = self::touchDevice($request, $changes);
        return new WP_REST_Response([
            'ok' => true,
            'schema_version' => self::SCHEMA_VERSION,
            'server_time' => current_time('mysql', true),
            'device' => $row,
            'capabilities' => [
                'operational_cache' => true,
                'form_drafts' => true,
                'sync_queue' => true,
                'idempotent_batch' => true,
                'background_sync_hint' => true,
                'incremental_delta' => true,
                'conflict_state' => true,
                'sync_center' => true,
                'cross_tab_lock' => true,
                'renewable_sync_lease' => true,
                'degraded_network_sync' => true,
                'server_operational_date' => true,
                'outbox_first_forms' => true,
                'strict_form_ack' => 'fieldflow-form-ack-v1',
                'degraded_network_probe' => true,
                'offline_operations_intelligence' => true,
                'sync_history' => true,
                'form_definition_versioning' => true,
                'media_integrity' => 'fieldflow-media-integrity-v1',
                'azure_offline_trust_shadow' => 'fieldflow-offline-trust-v1',
                'resumable_media_transport' => 'fieldflow-resumable-media-v1',
            ],
        ], 200);
    }

    public static function issueFormToken(int $formId, array $context, int $userId, string $reportDay): string {
        if (!$formId || !$userId) return '';
        $payload = [
            'v' => self::SCHEMA_VERSION,
            'uid' => $userId,
            'fid' => $formId,
            'cid' => absint($context['client_id'] ?? 0),
            'pid' => absint($context['project_id'] ?? 0),
            'rid' => absint($context['route_id'] ?? 0),
            'sid' => absint($context['stop_id'] ?? $context['route_stop_id'] ?? 0),
            'lid' => absint($context['location_id'] ?? 0),
            'day' => self::date($reportDay),
            'exp' => time() + 7 * DAY_IN_SECONDS,
        ];
        $encoded = self::base64url(wp_json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $encoded, wp_salt('auth'));
        return $encoded . '.' . $signature;
    }

    public static function verifyFormToken(string $token, int $formId, array $context, int $userId, string $reportDay): bool {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') return false;
        $expected = hash_hmac('sha256', $parts[0], wp_salt('auth'));
        if (!hash_equals($expected, $parts[1])) return false;
        $json = self::base64urlDecode($parts[0]);
        $payload = json_decode($json, true);
        if (!is_array($payload) || absint($payload['exp'] ?? 0) < time()) return false;
        $checks = [
            absint($payload['uid'] ?? 0) === $userId,
            absint($payload['fid'] ?? 0) === $formId,
            absint($payload['cid'] ?? 0) === absint($context['client_id'] ?? 0),
            absint($payload['pid'] ?? 0) === absint($context['project_id'] ?? 0),
            absint($payload['rid'] ?? 0) === absint($context['route_id'] ?? 0),
            absint($payload['sid'] ?? 0) === absint($context['route_stop_id'] ?? $context['stop_id'] ?? 0),
            absint($payload['lid'] ?? 0) === absint($context['location_id'] ?? 0),
            (string)($payload['day'] ?? '') === self::date($reportDay),
        ];
        return !in_array(false, $checks, true);
    }

    public static function stats(): array {
        global $wpdb;
        $table = self::deviceTable();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        $empty = [
            'tables_ready' => false,
            'devices' => 0,
            'pending' => 0,
            'failed' => 0,
            'conflicts' => 0,
            'active_24h' => 0,
            'active_failed_24h' => 0,
            'active_conflicts_24h' => 0,
            'stale_attention_devices' => 0,
            'last_sync_at' => '',
        ];
        if ($exists !== $table) return $empty;

        // The legacy queue_failed column historically mixed terminal failures
        // and conflicts. Modern runtimes report queue_failed and
        // queue_conflicts separately inside meta_json, so prefer those values
        // and only fall back to the legacy column when detailed telemetry is
        // unavailable. This is a read-time reconciliation only; no history is
        // rewritten or deleted.
        $rows = $wpdb->get_results("SELECT queue_pending,queue_failed,last_seen_at,last_sync_at,meta_json FROM {$table}", ARRAY_A) ?: [];
        $now = time();
        $stats = $empty;
        $stats['tables_ready'] = true;
        $stats['devices'] = count($rows);
        foreach ($rows as $row) {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $pending = array_key_exists('queue_pending', $meta)
                ? max(0, (int)$meta['queue_pending'])
                : max(0, (int)($row['queue_pending'] ?? 0));
            $conflicts = array_key_exists('queue_conflicts', $meta) ? max(0, (int)$meta['queue_conflicts']) : 0;
            if (array_key_exists('queue_failed', $meta)) {
                $failed = max(0, (int)$meta['queue_failed']);
            } else {
                // Legacy status reports used queue_failed = failed + conflicts.
                $failed = max(0, (int)($row['queue_failed'] ?? 0) - $conflicts);
            }
            $stats['pending'] += $pending;
            $stats['failed'] += $failed;
            $stats['conflicts'] += $conflicts;

            $lastSeen = (string)($row['last_seen_at'] ?? '');
            $seenTs = $lastSeen !== '' ? strtotime($lastSeen . ' UTC') : 0;
            $active = $seenTs > 0 && $seenTs >= $now - DAY_IN_SECONDS;
            if ($active) {
                $stats['active_24h']++;
                $stats['active_failed_24h'] += $failed;
                $stats['active_conflicts_24h'] += $conflicts;
            } elseif (($pending + $failed + $conflicts) > 0 && $seenTs > 0 && $seenTs < $now - 7 * DAY_IN_SECONDS) {
                $stats['stale_attention_devices']++;
            }

            $lastSync = (string)($row['last_sync_at'] ?? '');
            if ($lastSync !== '' && ($stats['last_sync_at'] === '' || strcmp($lastSync, $stats['last_sync_at']) > 0)) {
                $stats['last_sync_at'] = $lastSync;
            }
        }
        return $stats;
    }

    public static function recentDevices(int $limit = 20): array {
        global $wpdb;
        $table = self::deviceTable();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return [];
        $limit = min(100, max(1, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT d.*, u.display_name, u.user_login FROM {$table} d LEFT JOIN {$wpdb->users} u ON u.ID=d.user_id ORDER BY COALESCE(d.last_seen_at,d.updated_at,d.created_at) DESC LIMIT %d",
            $limit
        ), ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $meta = json_decode((string)($row['meta_json'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $conflicts = array_key_exists('queue_conflicts', $meta) ? max(0, (int)$meta['queue_conflicts']) : 0;
            $row['queue_pending'] = array_key_exists('queue_pending', $meta) ? max(0, (int)$meta['queue_pending']) : max(0, (int)($row['queue_pending'] ?? 0));
            $row['queue_failed'] = array_key_exists('queue_failed', $meta) ? max(0, (int)$meta['queue_failed']) : max(0, (int)($row['queue_failed'] ?? 0) - $conflicts);
            $row['queue_conflicts'] = $conflicts;
        }
        unset($row);
        return $rows;
    }

    private static function executeOperation(string $type, array $payload) {
        if ($type === 'form_submission') {
            $result = SubmissionService::submit($payload);
            return is_wp_error($result) ? $result : new WP_REST_Response($result, !empty($result['updated_existing']) ? 200 : 201);
        }
        if (in_array($type, ['stop_checkin', 'stop_checkout', 'stop_failure'], true)) {
            $event = str_replace('stop_', '', $type);
            $stopId = absint($payload['route_stop_id'] ?? $payload['stop_id'] ?? 0);
            if (!$stopId) return new WP_Error('fieldflow_offline_stop_required', 'route_stop_id is required.', ['status' => 400]);
            global $wpdb;
            $stop = $wpdb->get_row($wpdb->prepare(
                'SELECT id, route_id, status, arrived_at, departed_at, meta_json FROM ' . $wpdb->prefix . 'routespro_route_stops WHERE id=%d LIMIT 1',
                $stopId
            ), ARRAY_A);
            if (!$stop) {
                if ((string)$wpdb->last_error !== '') {
                    return new WP_Error('fieldflow_offline_stop_lookup_failed', 'Could not verify the route stop.', ['status' => 500]);
                }
                return new WP_REST_Response([
                    'ok' => true,
                    'idempotent' => true,
                    'superseded' => true,
                    'superseded_by_route_edit' => true,
                    'route_stop_id' => $stopId,
                    'event_type' => $event,
                    'reason' => 'route_stop_removed',
                ], 200);
            }
            $routeId = (int)($stop['route_id'] ?? 0);
            if (!$routeId || !Permissions::can_edit_route($routeId, get_current_user_id())) {
                return new WP_Error('fieldflow_offline_stop_forbidden', 'Stop access denied.', ['status' => 403]);
            }

            $queuedAtMs = max(0, (int)($payload['_queue_created_at_ms'] ?? $payload['created_at_ms'] ?? $payload['queued_at_ms'] ?? 0));
            $resetAtMs = class_exists('\RoutesPro\Services\VisitResetService')
                ? \RoutesPro\Services\VisitResetService::resetTimestampMs($stop)
                : 0;
            if ($queuedAtMs > 0 && $resetAtMs > 0 && $queuedAtMs <= $resetAtMs) {
                return new WP_REST_Response([
                    'ok' => true,
                    'idempotent' => true,
                    'superseded' => true,
                    'superseded_by_admin_reset' => true,
                    'route_stop_id' => $stopId,
                    'stop_status' => (string)($stop['status'] ?? 'pending'),
                    'event_type' => $event,
                    'reset_at_ms' => $resetAtMs,
                ], 200);
            }

            // Operational events are state transitions, not destructive edits.
            // A form submission can legitimately complete the stop before its
            // dependent checkout reaches the batch. Treat already-applied or
            // superseded transitions as successful no-ops so the queue clears
            // without reopening or overwriting a completed visit.
            $status = strtolower(trim((string)($stop['status'] ?? 'pending')));
            $terminal = in_array($status, ['done', 'completed', 'failed'], true);
            $alreadyApplied = ($type === 'stop_checkin' && ($status === 'in_progress' || $terminal))
                || ($type === 'stop_checkout' && in_array($status, ['done', 'completed'], true))
                || ($type === 'stop_failure' && $status === 'failed');
            $superseded = ($type === 'stop_checkout' && $status === 'failed')
                || ($type === 'stop_failure' && in_array($status, ['done', 'completed'], true));
            if ($alreadyApplied || $superseded) {
                return new WP_REST_Response([
                    'ok' => true,
                    'idempotent' => true,
                    'superseded' => $superseded,
                    'route_stop_id' => $stopId,
                    'stop_status' => $status,
                    'event_type' => $event,
                ], 200);
            }

            $proxy = new WP_REST_Request('POST', '/routespro/v1/events');
            $proxy->set_header('content-type', 'application/json');
            $proxy->set_body(wp_json_encode([
                'route_stop_id' => $stopId,
                'event_type' => $event,
                'payload' => is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload,
            ]));
            return (new EventsController())->create($proxy);
        }
        if (in_array($type, ['briefing_view', 'briefing_ack'], true)) {
            $id = absint($payload['briefing_id'] ?? $payload['id'] ?? 0);
            if (!$id) return new WP_Error('fieldflow_offline_briefing_required', 'briefing_id is required.', ['status' => 400]);
            $proxy = new WP_REST_Request('POST', '/fieldflow-mobile/v1/briefings/' . $id . '/' . ($type === 'briefing_ack' ? 'acknowledge' : 'view'));
            $proxy->set_param('id', $id);
            $proxy->set_body_params($payload);
            return $type === 'briefing_ack' ? (new BriefingsController())->ack($proxy) : (new BriefingsController())->view($proxy);
        }
        if ($type === 'stocks_viewed') {
            $proxy = new WP_REST_Request('POST', '/fieldflow-mobile/v1/stocks/viewed');
            $proxy->set_body_params($payload);
            return (new StocksController())->viewed($proxy);
        }
        return new WP_Error('fieldflow_offline_operation_unsupported', 'Unsupported offline operation.', ['status' => 400, 'type' => $type]);
    }

    private static function normaliseResult($result): array {
        $status = 200;
        $data = null;
        $error = '';
        if (is_wp_error($result)) {
            $details = $result->get_error_data();
            $status = is_array($details) && !empty($details['status']) ? (int)$details['status'] : 400;
            $data = ['code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $details];
            $error = (string)$result->get_error_code();
        } elseif ($result instanceof WP_REST_Response) {
            $status = $result->get_status();
            $data = $result->get_data();
            if ($status >= 400 && is_array($data) && !empty($data['code'])) {
                $error = sanitize_key((string)$data['code']);
            }
        } else {
            $data = $result;
        }
        $ok = $status >= 200 && $status < 300 && !is_wp_error($result);
        return [
            'status' => $ok ? 'completed' : 'failed',
            'http_status' => $status,
            'retryable' => !$ok && ($status >= 500 || in_array($status, [408, 425, 429], true) || ($status === 409 && $error !== 'fieldflow_offline_conflict')),
            'error' => $error,
            'response' => $data,
        ];
    }

    public static function formRevision(int $formId, array $context, array $schema): string {
        $payload = [
            'form_id' => $formId,
            'client_id' => absint($context['client_id'] ?? 0),
            'project_id' => absint($context['project_id'] ?? 0),
            'route_id' => absint($context['route_id'] ?? 0),
            'route_stop_id' => absint($context['route_stop_id'] ?? $context['stop_id'] ?? 0),
            'location_id' => absint($context['location_id'] ?? 0),
            'schema' => $schema,
        ];
        return substr(hash('sha256', (string)wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 24);
    }

    private static function decorateBundle(array $payload): array {
        foreach (['routes','route_details','stops','locations','forms'] as $group) {
            if (!isset($payload[$group]) || !is_array($payload[$group])) continue;
            if ($group === 'routes') {
                foreach ($payload[$group] as $index => $row) {
                    if (!is_array($row)) continue;
                    $payload[$group][$index]['_sync_revision'] = self::fingerprint($row);
                }
            } else {
                foreach ($payload[$group] as $id => $row) {
                    if (!is_array($row)) continue;
                    $payload[$group][$id]['_sync_revision'] = self::fingerprint($row);
                }
            }
        }
        return $payload;
    }

    private static function fingerprint($value): string {
        if (is_array($value)) {
            unset($value['_sync_revision'], $value['cursor']);
            $value = self::sortRecursive($value);
        }
        return substr(hash('sha256', (string)wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 24);
    }

    private static function sortRecursive($value) {
        if (!is_array($value)) return $value;
        foreach ($value as $key => $item) $value[$key] = self::sortRecursive($item);
        if (array_keys($value) !== range(0, count($value) - 1)) ksort($value, SORT_STRING);
        return $value;
    }

    private static function storeCursorSnapshot(array $payload, int $userId): string {
        $claims = [
            'uid' => $userId,
            'version' => (string)($payload['version'] ?? ''),
            'exp' => time() + self::CURSOR_TTL,
            'nonce' => substr(hash('sha256', wp_generate_uuid4()), 0, 16),
        ];
        $encoded = self::base64url((string)wp_json_encode($claims, JSON_UNESCAPED_SLASHES));
        $token = $encoded . '.' . hash_hmac('sha256', $encoded, wp_salt('auth'));
        set_transient('ff_offline_cursor_' . hash('sha256', $token), self::snapshotForBundle($payload), self::CURSOR_TTL);
        return $token;
    }

    private static function loadCursorSnapshot(string $token, int $userId): ?array {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') return null;
        $expected = hash_hmac('sha256', $parts[0], wp_salt('auth'));
        if (!hash_equals($expected, $parts[1])) return null;
        $claims = json_decode(self::base64urlDecode($parts[0]), true);
        if (!is_array($claims) || absint($claims['uid'] ?? 0) !== $userId || absint($claims['exp'] ?? 0) < time()) return null;
        $snapshot = get_transient('ff_offline_cursor_' . hash('sha256', $token));
        return is_array($snapshot) ? $snapshot : null;
    }

    private static function snapshotForBundle(array $payload): array {
        $snapshot = ['groups' => [], 'single' => []];
        foreach (['routes','route_details','stops','locations','forms'] as $group) {
            $rows = self::groupMap($payload, $group);
            $snapshot['groups'][$group] = [];
            foreach ($rows as $id => $row) {
                $snapshot['groups'][$group][(string)$id] = is_array($row) && !empty($row['_sync_revision'])
                    ? (string)$row['_sync_revision']
                    : self::fingerprint($row);
            }
        }
        foreach (['briefings','user','device_policy'] as $group) {
            $snapshot['single'][$group] = self::fingerprint($payload[$group] ?? null);
        }
        $snapshot['version'] = (string)($payload['version'] ?? '');
        return $snapshot;
    }

    private static function groupMap(array $payload, string $group): array {
        $rows = is_array($payload[$group] ?? null) ? $payload[$group] : [];
        if ($group !== 'routes') return $rows;
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = absint($row['id'] ?? 0);
            if ($id) $map[(string)$id] = $row;
        }
        return $map;
    }

    private static function countNested(array $groups): int {
        $total = 0;
        foreach ($groups as $value) $total += is_array($value) ? count($value) : 1;
        return $total;
    }

    private static function detectConflict(string $type, array $payload, array $operation) {
        $expected = sanitize_text_field((string)($operation['base_revision'] ?? $payload['base_revision'] ?? ''));
        if ($type === 'form_submission') return true;
        // Check-in, checkout and failure are ordered operational transitions.
        // Their full stop fingerprint changes during the normal visit flow,
        // including when the form itself completes the stop. Conflict safety is
        // therefore handled semantically in executeOperation(), not by comparing
        // a stale snapshot of the entire stop row.
        if (in_array($type, ['stop_checkin', 'stop_checkout', 'stop_failure'], true)) return true;
        if ($expected === '') return true;
        $entityType = sanitize_key((string)($operation['entity_type'] ?? $payload['entity_type'] ?? ''));
        $entityId = absint($operation['entity_id'] ?? $payload['entity_id'] ?? 0);
        if ($entityType === '' && in_array($type, ['stop_checkin','stop_checkout','stop_failure'], true)) {
            $entityType = 'stop';
            $entityId = absint($payload['route_stop_id'] ?? $payload['stop_id'] ?? 0);
        }
        if ($entityType === '' || !$entityId) return true;
        $current = self::currentEntityRevision($entityType, $entityId);
        if ($current === '') {
            return new WP_Error('fieldflow_offline_conflict', 'The referenced record no longer exists.', [
                'status' => 409,
                'conflict' => ['type' => 'deleted', 'entity_type' => $entityType, 'entity_id' => $entityId, 'base_revision' => $expected, 'server_revision' => ''],
            ]);
        }
        if (!hash_equals($expected, $current)) {
            return new WP_Error('fieldflow_offline_conflict', 'The server record changed while the device was offline.', [
                'status' => 409,
                'conflict' => ['type' => 'changed', 'entity_type' => $entityType, 'entity_id' => $entityId, 'base_revision' => $expected, 'server_revision' => $current],
            ]);
        }
        return true;
    }

    private static function currentEntityRevision(string $entityType, int $entityId): string {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        if ($entityType === 'route') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id,client_id,project_id,date,status,owner_user_id,meta_json,updated_at FROM {$px}routes WHERE id=%d LIMIT 1", $entityId), ARRAY_A);
            if (!$row) return '';
            $stops = $wpdb->get_results($wpdb->prepare("SELECT id,route_id,location_id,seq,planned_arrival,planned_departure,status,meta_json FROM {$px}route_stops WHERE route_id=%d ORDER BY seq ASC,id ASC", $entityId), ARRAY_A) ?: [];
            return self::fingerprint(['route' => $row, 'stops' => $stops]);
        }
        if ($entityType === 'stop') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT rs.*, l.name AS location_name, l.address, l.city, l.postal_code, l.phone, l.lat, l.lng FROM {$px}route_stops rs INNER JOIN {$px}locations l ON l.id=rs.location_id WHERE rs.id=%d LIMIT 1", $entityId), ARRAY_A);
            return $row ? self::fingerprint($row) : '';
        }
        if ($entityType === 'location') {
            $row = $wpdb->get_row($wpdb->prepare("SELECT id,name,address,city,postal_code,lat,lng,updated_at FROM {$px}locations WHERE id=%d LIMIT 1", $entityId), ARRAY_A);
            return $row ? self::fingerprint($row) : '';
        }
        return '';
    }

    private static function sanitiseDeviceMeta(array $meta): array {
        $out = [];
        foreach (['runtime_build','network_state','network_type','effective_type','last_endpoint','last_error_code','last_http_status','last_sync_result','storage_persistence','trust_lease_state','trust_lease_release','trust_lease_certified_at'] as $key) {
            if (array_key_exists($key, $meta)) $out[$key] = substr(sanitize_text_field((string)$meta[$key]), 0, 191);
        }
        foreach (['online','save_data'] as $key) if (array_key_exists($key, $meta)) $out[$key] = !empty($meta[$key]);
        foreach (['queue_conflicts','queue_pending','queue_failed','attempts','probe_latency_ms','storage_usage','storage_quota','storage_available','oldest_pending_at_ms','oldest_pending_age_ms','max_queue_attempts','ack_error_count','last_ack_error_at_ms','degraded_total_ms','degraded_events','auth_required_count','app_resume_count','recovery_count','last_recovery_at_ms','sync_confirmed_count','last_confirmed_at_ms','trust_lease_expires_at_ms','media_pending_files','media_pending_bytes','resumable_uploaded_chunks','resumable_resumed_chunks','resumable_completed_files'] as $key) {
            if (array_key_exists($key, $meta)) $out[$key] = max(0, (int)$meta[$key]);
        }
        if (!empty($meta['sync_history']) && is_array($meta['sync_history'])) {
            $history = [];
            foreach (array_slice($meta['sync_history'], 0, 12) as $event) {
                if (!is_array($event)) continue;
                $history[] = [
                    'at' => max(0, (int)($event['at'] ?? 0)),
                    'state' => substr(sanitize_key((string)($event['state'] ?? '')), 0, 32),
                    'kind' => substr(sanitize_key((string)($event['kind'] ?? '')), 0, 32),
                    'type' => substr(sanitize_key((string)($event['type'] ?? '')), 0, 64),
                    'attempts' => max(0, (int)($event['attempts'] ?? 0)),
                    'submission_id' => max(0, (int)($event['submission_id'] ?? 0)),
                    'code' => substr(sanitize_text_field((string)($event['code'] ?? '')), 0, 80),
                    'message' => substr(sanitize_text_field((string)($event['message'] ?? '')), 0, 180),
                    'form_version' => substr(sanitize_text_field((string)($event['form_version'] ?? '')), 0, 64),
                    'schema_hash' => substr(sanitize_text_field((string)($event['schema_hash'] ?? '')), 0, 64),
                    'downloaded_at' => substr(sanitize_text_field((string)($event['downloaded_at'] ?? '')), 0, 64),
                ];
            }
            if ($history) $out['sync_history'] = $history;
        }
        $out['reported_at'] = current_time('mysql', true);
        return $out;
    }

    private static function deviceConflictCount(): int {
        global $wpdb;
        $table = self::deviceTable();
        $rows = $wpdb->get_col("SELECT meta_json FROM {$table} WHERE meta_json IS NOT NULL AND meta_json<>''") ?: [];
        $total = 0;
        foreach ($rows as $json) {
            $meta = json_decode((string)$json, true);
            if (is_array($meta)) $total += max(0, (int)($meta['queue_conflicts'] ?? 0));
        }
        return $total;
    }

    private static function touchDevice(WP_REST_Request $request, array $changes): array {
        global $wpdb;
        $session = Auth::current_session() ?: [];
        $body = $request->get_json_params() ?: [];
        $deviceId = sanitize_text_field((string)($request->get_header('x-fieldflow-device-id') ?: ($body['device_id'] ?? '') ?: ($session['device_id'] ?? '')));
        if ($deviceId === '') $deviceId = 'web-' . substr(hash('sha256', get_current_user_id() . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 32);
        $platform = sanitize_key((string)($request->get_header('x-fieldflow-platform') ?: ($body['platform'] ?? '') ?: ($session['platform'] ?? 'web')));
        $appVersion = sanitize_text_field((string)($request->get_header('x-fieldflow-app-version') ?: ($body['app_version'] ?? '') ?: ($session['app_version'] ?? '')));
        $now = current_time('mysql', true);
        $base = [
            'user_id' => get_current_user_id(),
            'device_id' => $deviceId,
            'platform' => $platform ?: 'web',
            'app_version' => $appVersion,
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];
        $allowed = ['cache_version','queue_pending','queue_failed','last_cache_at','last_sync_at','last_error','meta_json'];
        foreach ($allowed as $key) if (array_key_exists($key, $changes)) $base[$key] = $changes[$key];
        $table = self::deviceTable();
        $existing = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $table . ' WHERE user_id=%d AND device_id=%s LIMIT 1',
            get_current_user_id(),
            $deviceId
        ), ARRAY_A);
        $formats = [];
        foreach ($base as $key => $value) $formats[] = in_array($key, ['user_id','queue_pending','queue_failed'], true) ? '%d' : '%s';
        if (is_array($existing) && !empty($existing['id'])) {
            $wpdb->update($table, $base, ['id' => (int)$existing['id']], $formats, ['%d']);
        } else {
            $insert = array_merge(['created_at' => $now], $base);
            $wpdb->insert($table, $insert, array_merge(['%s'], $formats));
        }
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $table . ' WHERE user_id=%d AND device_id=%s LIMIT 1', get_current_user_id(), $deviceId), ARRAY_A) ?: $base;
    }

    private static function data($result): array {
        if ($result instanceof WP_REST_Response) {
            $data = $result->get_data();
            return is_array($data) ? $data : [];
        }
        return is_array($result) ? $result : [];
    }

    private static function date(string $value): string {
        $value = sanitize_text_field($value);
        if ($value === '') return '';
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return $dt && $dt->format('Y-m-d') === $value ? $value : '';
    }

    private static function base64url(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $value): string {
        $padding = strlen($value) % 4;
        if ($padding) $value .= str_repeat('=', 4 - $padding);
        return (string)base64_decode(strtr($value, '-_', '+/'), true);
    }
}
