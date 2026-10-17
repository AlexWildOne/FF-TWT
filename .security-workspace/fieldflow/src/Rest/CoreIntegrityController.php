<?php
namespace RoutesPro\Rest;

use WP_REST_Request;
use WP_REST_Server;
use RoutesPro\Core\CanonicalStateService;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

class CoreIntegrityController {
    public function register_routes(): void {
        register_rest_route('fieldflow/v1', '/core/state/route/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'routeState'],
            'permission_callback' => [$this, 'canRead'],
            'args' => ['id' => ['validate_callback' => static function($value) { return absint($value) > 0; }]],
        ]);
        register_rest_route('fieldflow/v1', '/core/state/visit/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'visitState'],
            'permission_callback' => [$this, 'canRead'],
            'args' => ['id' => ['validate_callback' => static function($value) { return absint($value) > 0; }]],
        ]);
        register_rest_route('fieldflow/v1', '/core/state/submission/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'submissionState'],
            'permission_callback' => [$this, 'canRead'],
            'args' => ['id' => ['validate_callback' => static function($value) { return absint($value) > 0; }]],
        ]);
    }

    public function canRead(WP_REST_Request $request): bool {
        if (!is_user_logged_in()) return false;
        $userId = get_current_user_id();
        if (Permissions::is_manager($userId)) return true;

        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $id = absint($request['id']);
        $routePath = (string)$request->get_route();
        $context = [];
        if (strpos($routePath, '/core/state/route/') !== false) {
            $context = $wpdb->get_row($wpdb->prepare("SELECT id AS route_id,client_id,project_id,owner_user_id FROM {$px}routes WHERE id=%d LIMIT 1", $id), ARRAY_A) ?: [];
        } elseif (strpos($routePath, '/core/state/visit/') !== false) {
            $context = $wpdb->get_row($wpdb->prepare(
                "SELECT r.id AS route_id,r.client_id,r.project_id,r.owner_user_id FROM {$px}route_stops rs INNER JOIN {$px}routes r ON r.id=rs.route_id WHERE rs.id=%d LIMIT 1",
                $id
            ), ARRAY_A) ?: [];
        } elseif (strpos($routePath, '/core/state/submission/') !== false) {
            $context = $wpdb->get_row($wpdb->prepare(
                "SELECT route_id,client_id,project_id,user_id AS owner_user_id FROM {$px}form_submissions WHERE id=%d LIMIT 1",
                $id
            ), ARRAY_A) ?: [];
        }
        if (!$context) return false;
        if (absint($context['owner_user_id'] ?? 0) === $userId) return true;

        $scope = Permissions::get_scope($userId);
        $routeId = absint($context['route_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);
        $clientId = absint($context['client_id'] ?? 0);
        return ($routeId > 0 && in_array($routeId, (array)($scope['route_ids'] ?? []), true))
            || ($projectId > 0 && in_array($projectId, (array)($scope['project_ids'] ?? []), true))
            || ($clientId > 0 && in_array($clientId, (array)($scope['client_ids'] ?? []), true));
    }

    public function routeState(WP_REST_Request $request) {
        return rest_ensure_response(CanonicalStateService::route(absint($request['id'])));
    }

    public function visitState(WP_REST_Request $request) {
        return rest_ensure_response(CanonicalStateService::visit(absint($request['id'])));
    }

    public function submissionState(WP_REST_Request $request) {
        return rest_ensure_response(CanonicalStateService::submission(absint($request['id'])));
    }
}
