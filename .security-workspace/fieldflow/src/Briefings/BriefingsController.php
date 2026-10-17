<?php
namespace RoutesPro\Briefings;

use RoutesPro\Support\Permissions;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) exit;

class BriefingsController {
    public function register_routes(): void {
        register_rest_route('routespro/v1', '/briefings/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
        register_rest_route('routespro/v1', '/briefings/client', [
            'methods' => 'GET',
            'callback' => [$this, 'client'],
            'permission_callback' => static fn() => is_user_logged_in() && Permissions::can_access_front(),
        ]);
        register_rest_route('routespro/v1', '/briefings/(?P<id>\d+)/view', [
            'methods' => 'POST',
            'callback' => [$this, 'view'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
        register_rest_route('routespro/v1', '/briefings/(?P<id>\d+)/ack', [
            'methods' => 'POST',
            'callback' => [$this, 'ack'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
    }

    public function mine(WP_REST_Request $request) {
        $requestedClientId = absint($request->get_param('client_id'));
        $requestedProjectId = absint($request->get_param('project_id'));
        [$clientId, $projectId] = Permissions::sanitize_scope_selection($requestedClientId, $requestedProjectId);
        if (($requestedClientId && !$clientId) || ($requestedProjectId && !$projectId)) {
            return new WP_Error('fieldflow_briefings_scope', 'Sem acesso ao âmbito selecionado.', ['status' => 403]);
        }
        $allowed = Permissions::assert_scope_or_error($clientId, $projectId);
        if (is_wp_error($allowed)) return $allowed;
        try {
            return new WP_REST_Response([
                'briefings' => BriefingService::mine(get_current_user_id(), $clientId, $projectId),
                'scope' => ['client_id' => $clientId, 'project_id' => $projectId],
            ], 200);
        } catch (\Throwable $e) {
            return new WP_Error('fieldflow_briefings_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function client(WP_REST_Request $request) {
        $requestedClientId = absint($request->get_param('client_id'));
        $requestedProjectId = absint($request->get_param('project_id'));
        [$clientId, $projectId] = Permissions::sanitize_scope_selection($requestedClientId, $requestedProjectId);

        // An explicitly requested but unauthorized scope must never silently fall back to another client.
        if (($requestedClientId && !$clientId) || ($requestedProjectId && !$projectId)) {
            return new WP_Error('fieldflow_briefings_scope', 'Sem acesso ao âmbito selecionado.', ['status' => 403]);
        }

        $allowed = Permissions::assert_scope_or_error($clientId, $projectId);
        if (is_wp_error($allowed)) return $allowed;
        try {
            return new WP_REST_Response([
                'briefings' => BriefingService::clientList($clientId, $projectId, get_current_user_id()),
                'scope' => ['client_id' => $clientId, 'project_id' => $projectId],
            ], 200);
        } catch (\Throwable $e) {
            return new WP_Error('fieldflow_briefings_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function view(WP_REST_Request $request) {
        try {
            $id = absint($request['id']);
            BriefingService::markViewed($id, get_current_user_id());
            return new WP_REST_Response(['ok' => true], 200);
        } catch (\Throwable $e) {
            return new WP_Error('fieldflow_briefing_view_failed', $e->getMessage(), ['status' => 403]);
        }
    }

    public function ack(WP_REST_Request $request) {
        try {
            $id = absint($request['id']);
            BriefingService::acknowledge($id, get_current_user_id());
            return new WP_REST_Response(['ok' => true, 'acknowledged_at' => current_time('mysql')], 200);
        } catch (\Throwable $e) {
            return new WP_Error('fieldflow_briefing_ack_failed', $e->getMessage(), ['status' => 403]);
        }
    }
}
