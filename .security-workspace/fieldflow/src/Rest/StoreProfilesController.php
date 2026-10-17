<?php
namespace RoutesPro\Rest;

use RoutesPro\Stores\StoreProfileService;
use RoutesPro\Support\Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) exit;

final class StoreProfilesController {
    public const NS = 'routespro/v1';

    public function register_routes(): void {
        register_rest_route(self::NS, '/locations/(?P<id>\d+)/profile', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_profile'],
                'permission_callback' => [$this, 'permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'save_profile'],
                'permission_callback' => [$this, 'permission'],
            ],
        ]);
    }

    public function permission(): bool {
        return is_user_logged_in() && (current_user_can('routespro_manage') || Permissions::can_access_front());
    }

    public function get_profile(WP_REST_Request $request) {
        $result = StoreProfileService::snapshot(
            absint($request['id']),
            absint($request->get_param('project_id')),
            absint($request->get_param('route_id'))
        );
        if (is_wp_error($result)) return $result;
        return new WP_REST_Response($result, 200);
    }

    public function save_profile(WP_REST_Request $request) {
        $payload = $request->get_json_params();
        if (!is_array($payload)) $payload = [];
        $result = StoreProfileService::save(
            absint($request['id']),
            $payload,
            get_current_user_id(),
            absint($payload['project_id'] ?? $request->get_param('project_id')),
            absint($payload['route_id'] ?? $request->get_param('route_id'))
        );
        if (is_wp_error($result)) return $result;
        return new WP_REST_Response($result, 200);
    }
}
