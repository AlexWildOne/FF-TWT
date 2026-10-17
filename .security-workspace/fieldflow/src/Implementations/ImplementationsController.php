<?php
namespace RoutesPro\Implementations;

use RoutesPro\Support\Permissions;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) exit;

class ImplementationsController {
    public function register_routes(): void {
        register_rest_route('routespro/v1', '/implementations/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'mine'],
            'permission_callback' => static fn() => is_user_logged_in(),
        ]);
        register_rest_route('routespro/v1', '/implementations/client', [
            'methods' => 'GET',
            'callback' => [$this, 'client'],
            'permission_callback' => static fn() => is_user_logged_in() && Permissions::can_access_front(),
        ]);
    }

    public function mine(WP_REST_Request $request) {
        $requestedClientId = absint($request->get_param('client_id'));
        $requestedProjectId = absint($request->get_param('project_id'));
        [$clientId, $projectId] = Permissions::sanitize_scope_selection($requestedClientId, $requestedProjectId);
        if (($requestedClientId && !$clientId) || ($requestedProjectId && !$projectId)) {
            return new WP_Error('fieldflow_implementations_scope', 'Sem acesso ao âmbito selecionado.', ['status' => 403]);
        }
        $allowed = Permissions::assert_scope_or_error($clientId, $projectId);
        if (is_wp_error($allowed)) return $allowed;
        try {
            return new WP_REST_Response([
                'documents' => ImplementationService::mine(get_current_user_id(), $clientId, $projectId),
                'scope' => ['client_id' => $clientId, 'project_id' => $projectId],
            ], 200);
        } catch (\Throwable $e) {
            return new WP_Error('fieldflow_implementations_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function client(WP_REST_Request $request) {
        $requestedClientId = absint($request->get_param('client_id'));
        $requestedProjectId = absint($request->get_param('project_id'));
        [$clientId, $projectId] = Permissions::sanitize_scope_selection($requestedClientId, $requestedProjectId);
        if (($requestedClientId && !$clientId) || ($requestedProjectId && !$projectId)) {
            return new WP_Error('fieldflow_implementations_scope', 'Sem acesso ao âmbito selecionado.', ['status' => 403]);
        }
        $allowed = Permissions::assert_scope_or_error($clientId, $projectId);
        if (is_wp_error($allowed)) return $allowed;
        try {
            return new WP_REST_Response([
                'documents' => ImplementationService::clientList(get_current_user_id(), $clientId, $projectId),
                'scope' => ['client_id' => $clientId, 'project_id' => $projectId],
            ], 200);
        } catch (\Throwable $e) {
            return new WP_Error('fieldflow_implementations_failed', $e->getMessage(), ['status' => 500]);
        }
    }
}
