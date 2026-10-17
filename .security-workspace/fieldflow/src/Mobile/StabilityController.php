<?php
namespace RoutesPro\Mobile;

use RoutesPro\Observability\Monitor;
use RoutesPro\Support\StabilityFlags;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

/** Additive endpoints used to protect the current FlutterFlow integration. */
class StabilityController {
    public function register_routes(): void {
        register_rest_route(Controller::NS, '/contract', [
            'methods' => 'GET',
            'callback' => [$this, 'contract'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(Controller::NS, '/diagnostics/client-event', [
            'methods' => 'POST',
            'callback' => [$this, 'clientEvent'],
            'permission_callback' => [$this, 'authenticated'],
        ]);
    }

    public function authenticated() {
        return is_user_logged_in()
            ? true
            : new WP_Error('fieldflow_mobile_auth_required', 'Authentication required.', ['status' => 401]);
    }

    public function contract(WP_REST_Request $request): WP_REST_Response {
        $clientApiVersion = sanitize_text_field((string)$request->get_param('api_version'));
        $clientChecksum = sanitize_text_field((string)$request->get_param('contract_checksum'));
        return new WP_REST_Response([
            'contract' => ContractRegistry::manifest(),
            'checksum' => ContractRegistry::checksum(),
            'compatibility' => ContractRegistry::compatibility($clientApiVersion, $clientChecksum),
            'stability' => StabilityFlags::publicState(),
            'plugin_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
        ], 200);
    }

    public function clientEvent(WP_REST_Request $request) {
        if (!StabilityFlags::enabled('client_diagnostics')) {
            return new WP_Error('fieldflow_mobile_diagnostics_disabled', 'Client diagnostics are disabled.', ['status' => 403]);
        }
        if (!$this->withinRateLimit($request)) {
            return new WP_Error('fieldflow_mobile_diagnostics_rate_limited', 'Too many diagnostic events.', ['status' => 429]);
        }

        $body = $request->get_json_params();
        if (!is_array($body)) $body = [];
        $type = sanitize_key((string)($body['event_type'] ?? 'diagnostic'));
        $allowedTypes = [
            'diagnostic','app_error','webview_error','form_navigation_error','submission_error',
            'media_error','offline_sync_error','session_error','route_error','contract_mismatch',
        ];
        if (!in_array($type, $allowedTypes, true)) $type = 'diagnostic';

        $severity = sanitize_key((string)($body['severity'] ?? 'warning'));
        if (!in_array($severity, ['info','warning','critical'], true)) $severity = 'warning';
        $message = sanitize_textarea_field((string)($body['message'] ?? 'Evento reportado pela aplicação móvel.'));
        $message = substr($message, 0, 500);
        $context = $this->safeContext($body, $request);
        $context['event_type'] = $type;
        $context['user_id'] = get_current_user_id();

        $eventId = class_exists(Monitor::class)
            ? Monitor::record('mobile_' . $type, $severity, $message, $context)
            : 0;

        return new WP_REST_Response([
            'accepted' => true,
            'event_id' => $eventId,
            'correlation_id' => (string)$context['correlation_id'],
            'contract_checksum' => ContractRegistry::checksum(),
        ], 202);
    }

    private function withinRateLimit(WP_REST_Request $request): bool {
        $session = Auth::current_session();
        $deviceId = sanitize_text_field((string)($request->get_header('x-fieldflow-device-id') ?: ($session['device_id'] ?? '')));
        $key = 'fieldflow_mobile_diag_' . md5(get_current_user_id() . '|' . $deviceId);
        $hits = (int)get_transient($key);
        if ($hits >= 30) return false;
        set_transient($key, $hits + 1, MINUTE_IN_SECONDS);
        return true;
    }

    private function safeContext(array $body, WP_REST_Request $request): array {
        $session = Auth::current_session();
        $correlation = preg_replace('/[^A-Za-z0-9._:-]/', '', (string)(
            $body['correlation_id']
            ?? $request->get_header('x-fieldflow-correlation-id')
            ?? ''
        ));
        if (strlen((string)$correlation) < 8) $correlation = ContractRegistry::correlationId($request);

        $context = [
            'correlation_id' => substr((string)$correlation, 0, 96),
            'platform' => $this->cleanEnum($body['platform'] ?? ($session['platform'] ?? ''), ['android','ios','web','unknown']),
            'app_version' => $this->cleanText($body['app_version'] ?? '', 48),
            'build_number' => $this->cleanText($body['build_number'] ?? '', 32),
            'device_id' => $this->cleanText($body['device_id'] ?? ($session['device_id'] ?? ''), 191),
            'screen' => $this->cleanText($body['screen'] ?? '', 96),
            'action' => $this->cleanText($body['action'] ?? '', 96),
            'api_route' => $this->cleanRoute($body['api_route'] ?? ''),
            'error_code' => $this->cleanText($body['error_code'] ?? '', 96),
            'question_key' => sanitize_key((string)($body['question_key'] ?? '')),
            'route_id' => absint($body['route_id'] ?? 0),
            'stop_id' => absint($body['stop_id'] ?? ($body['route_stop_id'] ?? 0)),
            'location_id' => absint($body['location_id'] ?? 0),
            'client_id' => absint($body['client_id'] ?? 0),
            'project_id' => absint($body['project_id'] ?? 0),
            'form_id' => absint($body['form_id'] ?? 0),
            'step_index' => max(0, (int)($body['step_index'] ?? 0)),
            'http_status' => max(0, min(599, (int)($body['http_status'] ?? 0))),
            'offline_queue_size' => max(0, min(100000, (int)($body['offline_queue_size'] ?? 0))),
            'online' => array_key_exists('online', $body) ? (bool)$body['online'] : null,
            'plugin_version' => defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '',
            'contract_checksum' => ContractRegistry::checksum(),
        ];

        if (!empty($body['webview_url'])) {
            $url = wp_parse_url((string)$body['webview_url']);
            if (is_array($url)) {
                $context['webview_host'] = $this->cleanText($url['host'] ?? '', 191);
                $context['webview_path'] = $this->cleanText($url['path'] ?? '', 191);
            }
        }
        // 9CR fieldflow-media-capture-resilience-v1: bounded photo-flow steps reported by the form runtime.
        if (!empty($body['detail']) && is_string($body['detail'])) {
            $context['detail'] = substr(sanitize_textarea_field($body['detail']), 0, 600);
        }

        return $context;
    }

    private function cleanText($value, int $length): string {
        return substr(sanitize_text_field((string)$value), 0, $length);
    }

    private function cleanRoute($value): string {
        $route = preg_replace('/[^A-Za-z0-9_\-\/.{}:?=&]/', '', (string)$value);
        return substr((string)$route, 0, 191);
    }

    private function cleanEnum($value, array $allowed): string {
        $value = sanitize_key((string)$value);
        return in_array($value, $allowed, true) ? $value : 'unknown';
    }
}
