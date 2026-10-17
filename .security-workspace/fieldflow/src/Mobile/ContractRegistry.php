<?php
namespace RoutesPro\Mobile;

use RoutesPro\Support\StabilityFlags;
use WP_HTTP_Response;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

/**
 * Versioned, additive-only contract consumed by the FlutterFlow application.
 *
 * This registry does not replace any response shape. It documents and signs
 * the current one so future builds can detect accidental breaking changes.
 */
class ContractRegistry {
    public const API_VERSION = '1.8';
    public const CONTRACT_VERSION = '1.3.2';
    public const CONTRACT_ID = 'fieldflow-mobile-v1.8';
    public const POLICY = 'additive-only';

    private static array $correlationIds = [];

    public static function registerHooks(): void {
        add_filter('rest_post_dispatch', [self::class, 'decorateResponse'], 30, 3);
        add_filter('rest_exposed_cors_headers', [self::class, 'exposedHeaders']);
    }

    public static function exposedHeaders(array $headers): array {
        foreach ([
            'X-FieldFlow-API-Version',
            'X-FieldFlow-Contract-Version',
            'X-FieldFlow-Contract-Checksum',
            'X-FieldFlow-Plugin-Version',
            'X-FieldFlow-Correlation-ID',
        ] as $header) {
            if (!in_array($header, $headers, true)) $headers[] = $header;
        }
        return $headers;
    }

    public static function decorateResponse($response, $server, WP_REST_Request $request) {
        $route = (string)$request->get_route();
        if (strpos($route, '/' . Controller::NS . '/') !== 0) return $response;
        if (!StabilityFlags::enabled('contract_headers')) return $response;
        if (!$response instanceof WP_HTTP_Response) return $response;

        $response->header('X-FieldFlow-API-Version', self::API_VERSION);
        $response->header('X-FieldFlow-Contract-Version', self::CONTRACT_VERSION);
        $response->header('X-FieldFlow-Contract-Checksum', self::checksum());
        $response->header('X-FieldFlow-Plugin-Version', defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '');
        $response->header('X-FieldFlow-Correlation-ID', self::correlationId($request));
        return $response;
    }

    public static function correlationId(WP_REST_Request $request): string {
        $objectId = spl_object_id($request);
        if (isset(self::$correlationIds[$objectId])) return self::$correlationIds[$objectId];

        $incoming = preg_replace('/[^A-Za-z0-9._:-]/', '', (string)$request->get_header('x-fieldflow-correlation-id'));
        $incoming = substr((string)$incoming, 0, 96);
        if (strlen($incoming) >= 8) return self::$correlationIds[$objectId] = $incoming;

        $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('ff-', true);
        return self::$correlationIds[$objectId] = 'ff-' . substr(str_replace('-', '', (string)$uuid), 0, 32);
    }

    public static function summary(): array {
        return [
            'id' => self::CONTRACT_ID,
            'api_version' => self::API_VERSION,
            'contract_version' => self::CONTRACT_VERSION,
            'checksum' => self::checksum(),
            'policy' => self::POLICY,
            'status' => 'stable',
            'breaking_changes_allowed' => false,
            'compatibility_endpoint' => function_exists('rest_url') ? rest_url(Controller::NS . '/contract') : '',
        ];
    }

    public static function manifest(): array {
        return [
            'id' => self::CONTRACT_ID,
            'api_version' => self::API_VERSION,
            'contract_version' => self::CONTRACT_VERSION,
            'policy' => self::POLICY,
            'namespace' => Controller::NS,
            'authentication' => [
                'access_header' => 'X-FieldFlow-Mobile-Token',
                'legacy_bearer_supported' => true,
                'mutation_header' => 'X-FieldFlow-Mutation-Id',
                'device_header' => 'X-FieldFlow-Device-ID',
                'correlation_header' => 'X-FieldFlow-Correlation-ID',
                'app_version_header' => 'X-FieldFlow-App-Version',
            ],
            'protected_invariants' => [
                'existing_routes_are_not_removed',
                'existing_response_keys_are_not_renamed',
                'existing_scalar_types_are_not_changed',
                'new_fields_are_additive',
                'mutation_ids_remain_idempotent',
                'webview_portal_paths_remain_stable',
            ],
            'critical_endpoints' => self::criticalEndpoints(),
            'webview' => [
                'launch' => '/fieldflow-mobile/launch/',
                'operational' => '/fieldflow-mobile/portal/operational/',
                'client' => '/fieldflow-mobile/portal/client/',
                'billing' => '/fieldflow-mobile/portal/billing/',
                'subscriptions' => '/fieldflow-mobile/portal/subscriptions/',
                'logout' => '/fieldflow-mobile/logout/',
                'native_shell_version' => WebViewBridge::NATIVE_SHELL_VERSION,
            ],
        ];
    }

    public static function criticalEndpoints(): array {
        return [
            'auth_login' => self::endpoint('POST', '/auth/login', false, ['tokens','user'], ['username|email','password','mfa_code?']),
            'auth_refresh' => self::endpoint('POST', '/auth/refresh', false, ['tokens','user'], ['refresh_token']),
            'auth_logout' => self::endpoint('POST', '/auth/logout', true, ['ok','web_sessions_revoked']),
            'web_session' => self::endpoint('POST', '/auth/web-session', true, ['target','launch_url','expires_in']),
            'me' => self::endpoint('GET', '/me', true, ['user','session']),
            'app_config' => self::endpoint('GET', '/app-config', false, ['api_version','plugin_version','platform','features','theme','authentication','webview','endpoints']),
            'my_routes' => self::endpoint('GET', '/my-routes', true, ['routes']),
            'route_optimization_preview' => self::endpoint('POST', '/route-optimization/preview', true, ['ordered_location_ids','baseline','optimized','provider'], ['location_ids']),
            'route_optimization_apply' => self::endpoint('POST', '/routes/{id}/optimize', true, ['ordered_location_ids','applied','sync']),
            'route_workspace_save' => self::endpoint('POST', '/routes/{id}/workspace', true, ['ok','route_id','status','stop_ids','route_progress'], ['status','stop_ids']),
            'route_workspace_restore' => self::endpoint('POST', '/routes/{id}/workspace/restore', true, ['ok','route_id','revision_id'], ['revision_id']),
            'route_stops_bulk_remove' => self::endpoint('POST', '/routes/{id}/stops/bulk-remove', true, ['ok','removed_count','removed_stop_ids','route_progress'], ['stop_ids']),
            'route' => self::endpoint('GET', '/routes/{id}', true, ['route']),
            'route_stops' => self::endpoint('GET', '/routes/{id}/stops', true, ['stops']),
            'stop' => self::endpoint('GET', '/stops/{id}', true, ['stop']),
            'stop_form' => self::endpoint('GET', '/stops/{id}/form', true, ['form','binding','context','stop','daily_report','record','prefill']),
            'stop_checkin' => self::endpoint('POST', '/stops/{id}/checkin', true, [], [], true),
            'stop_checkout' => self::endpoint('POST', '/stops/{id}/checkout', true, [], [], true),
            'stop_failure' => self::endpoint('POST', '/stops/{id}/failure', true, [], [], true),
            'form_submission' => self::endpoint('POST', '/form-submissions', true, ['submission_id'], ['form_id','answers'], true),
            'submission' => self::endpoint('GET', '/form-submissions/{id}', true, ['submission','answers']),
            'media' => self::endpoint('POST', '/media', true, ['attachment_id','url','mime_type','filename'], ['file'], true),
            'offline_ping' => self::endpoint('GET', '/offline/ping', true, ['ok','server_time','plugin_version','policy']),
            'offline_bootstrap' => self::endpoint('GET', '/offline/bootstrap', true, ['cursor','version','schema_version']),
            'offline_delta' => self::endpoint('GET', '/offline/delta', true, ['mode','cursor','changes','deleted'], ['cursor']),
            'offline_sync' => self::endpoint('POST', '/offline/sync', true, ['summary','results'], ['operations'], true),
            'offline_status' => self::endpoint(['GET','POST'], '/offline/status', true, []),
            'offline_media_start' => self::endpoint('POST', '/offline/media/start', true, ['ok','protocol','upload_id','upload_token','received_chunks','missing_chunks','total_chunks'], ['mutation_id','field_name','file_index','name','size','sha256']),
            'offline_media_chunk' => self::endpoint('POST', '/offline/media/{upload_id}/chunk', true, ['ok','protocol','upload_id','received_chunks','missing_chunks','received_bytes']),
            'offline_media_complete' => self::endpoint('POST', '/offline/media/{upload_id}/complete', true, ['ok','protocol','complete','media_ref','sha256']),
            'billing_experience' => self::endpoint('GET', '/billing/experience', true, ['experience','user']),
            'health' => self::endpoint('GET', '/health', false, ['ok','api_version','plugin_version']),
            'contract' => self::endpoint('GET', '/contract', false, ['contract','checksum','compatibility']),
            'client_diagnostics' => self::endpoint('POST', '/diagnostics/client-event', true, ['accepted','correlation_id']),
        ];
    }

    private static function endpoint($methods, string $path, bool $authenticated, array $responseKeys, array $required = [], bool $idempotent = false): array {
        return [
            'methods' => array_values((array)$methods),
            'path' => $path,
            'authenticated' => $authenticated,
            'required_request_fields' => array_values($required),
            'protected_response_keys' => array_values($responseKeys),
            'idempotent_mutation' => $idempotent,
        ];
    }

    public static function checksum(): string {
        $manifest = self::manifest();
        $canonical = self::sortRecursive($manifest);
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', (string)$json);
    }

    public static function compatibility(string $clientApiVersion = '', string $clientChecksum = ''): array {
        $apiMatches = $clientApiVersion === '' || version_compare($clientApiVersion, self::API_VERSION, '<=');
        $checksumMatches = $clientChecksum === '' || hash_equals(self::checksum(), strtolower(trim($clientChecksum)));
        return [
            'compatible' => $apiMatches,
            'api_version_matches' => $apiMatches,
            'checksum_matches' => $checksumMatches,
            'enforced' => StabilityFlags::enabled('contract_enforcement'),
            'action' => $apiMatches ? ($checksumMatches ? 'none' : 'refresh_contract') : 'update_app',
        ];
    }

    private static function sortRecursive($value) {
        if (!is_array($value)) return $value;
        foreach ($value as $key => $item) $value[$key] = self::sortRecursive($item);
        if (self::isAssoc($value)) ksort($value, SORT_STRING);
        return $value;
    }

    private static function isAssoc(array $value): bool {
        if ($value === []) return false;
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
