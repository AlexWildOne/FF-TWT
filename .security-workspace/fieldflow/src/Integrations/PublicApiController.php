<?php
namespace RoutesPro\Integrations;

use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

class PublicApiController {
    public const NS = 'fieldflow-public/v1';

    public function register_routes(): void {
        register_rest_route(self::NS, '/schema', [
            'methods' => 'GET',
            'callback' => [$this, 'schema'],
            'permission_callback' => [$this, 'authenticate'],
        ]);
        register_rest_route(self::NS, '/resources/(?P<resource>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'resource'],
            'permission_callback' => [$this, 'authenticate'],
        ]);
        register_rest_route(self::NS, '/powerbi/(?P<dataset>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'powerbi'],
            'permission_callback' => [$this, 'authenticate'],
        ]);
    }

    public function authenticate(WP_REST_Request $request) {
        return ApiKeyService::authenticate($request);
    }

    public function schema(WP_REST_Request $request): WP_REST_Response {
        return $this->response(['ok' => true, 'api' => PublicQueryService::schema()]);
    }

    public function resource(WP_REST_Request $request) {
        $resource = sanitize_key((string)$request['resource']);
        $data = PublicQueryService::fetch($resource, $request->get_params(), ApiKeyService::current());
        if (class_exists('RoutesPro\\Services\\IntegrationPlatform')) {
            \RoutesPro\Services\IntegrationPlatform::log(
                'public_api',
                'read',
                is_wp_error($data) ? 'error' : 'success',
                [
                    'resource' => $resource,
                    'key_id' => (int)(ApiKeyService::current()['id'] ?? 0),
                    'count' => is_array($data) ? (int)($data['count'] ?? 0) : 0,
                ],
                is_wp_error($data) ? $data->get_error_message() : ''
            );
        }
        return is_wp_error($data) ? $data : $this->response(['ok' => true] + $data);
    }

    public function powerbi(WP_REST_Request $request) {
        $map = [
            'execution' => 'analytics-execution',
            'routes' => 'routes',
            'visits' => 'route-stops',
            'submissions' => 'submissions',
            'stocks' => 'stocks',
        ];
        $dataset = sanitize_key((string)$request['dataset']);
        if (!isset($map[$dataset])) {
            return new \WP_Error('fieldflow_powerbi_dataset', 'Dataset Power BI inválido.', ['status' => 404]);
        }
        $data = PublicQueryService::fetch($map[$dataset], $request->get_params(), ApiKeyService::current());
        if (is_wp_error($data)) return $data;
        if (class_exists('RoutesPro\\Services\\IntegrationPlatform')) {
            \RoutesPro\Services\IntegrationPlatform::log('powerbi', 'read', 'success', [
                'dataset' => $dataset,
                'key_id' => (int)(ApiKeyService::current()['id'] ?? 0),
                'count' => (int)$data['count'],
            ]);
        }
        return $this->response([
            'value' => $data['items'],
            'fieldflow' => [
                'dataset' => $dataset,
                'generated_at' => $data['generated_at'],
                'has_more' => $data['has_more'],
                'next_cursor' => $data['next_cursor'],
                'scope' => $data['scope'],
            ],
        ]);
    }

    private function response(array $data, int $status = 200): WP_REST_Response {
        $response = new WP_REST_Response($data, $status);
        $response->header('Cache-Control', 'private, no-store, max-age=0');
        $response->header('X-FieldFlow-API-Version', '1');
        return $response;
    }
}
