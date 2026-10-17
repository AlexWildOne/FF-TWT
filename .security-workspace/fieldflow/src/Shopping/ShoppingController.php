<?php
namespace RoutesPro\Shopping;

if (!defined('ABSPATH')) exit;

class ShoppingController extends \WP_REST_Controller {
    public function __construct() {
        $this->namespace = 'fieldflow/v1';
        $this->rest_base = 'shopping';
    }

    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/assignments', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'assignments'],
            'permission_callback' => [$this, 'permission'],
            'args' => [
                'client_id' => ['sanitize_callback'=>'absint'],
                'project_id' => ['sanitize_callback'=>'absint'],
            ],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/assignments/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'assignment'],
            'permission_callback' => [$this, 'permission'],
            'args' => ['id'=>['validate_callback'=>static fn($value): bool => absint($value) > 0]],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/assignments/(?P<id>\d+)/associate-location', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'associateLocation'],
            'permission_callback' => [$this, 'permission'],
            'args' => ['id'=>['validate_callback'=>static fn($value): bool => absint($value) > 0]],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/submissions', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'submit'],
            'permission_callback' => [$this, 'permission'],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/results', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'results'],
            'permission_callback' => [$this, 'permission'],
            'args' => [
                'client_id' => ['sanitize_callback'=>'absint'],
                'project_id' => ['sanitize_callback'=>'absint'],
                'location_id' => ['sanitize_callback'=>'absint'],
                'user_id' => ['sanitize_callback'=>'absint'],
                'batch_id' => ['sanitize_callback'=>'absint'],
                'page' => ['sanitize_callback'=>'absint'],
                'per_page' => ['sanitize_callback'=>'absint'],
                'from' => ['sanitize_callback'=>'sanitize_text_field'],
                'to' => ['sanitize_callback'=>'sanitize_text_field'],
                'q' => ['sanitize_callback'=>'sanitize_text_field'],
            ],
        ]);
        register_rest_route($this->namespace, '/' . $this->rest_base . '/results/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'result'],
            'permission_callback' => [$this, 'permission'],
            'args' => ['id'=>['validate_callback'=>static fn($value): bool => absint($value) > 0]],
        ]);
    }

    public function permission() {
        if (!is_user_logged_in()) return new \WP_Error('fieldflow_auth_required', 'Inicia sessão para aceder às verificações Shopping.', ['status'=>401]);
        if (!current_user_can('routespro_manage') && !\RoutesPro\Support\Permissions::can_access_front()) {
            return new \WP_Error('fieldflow_forbidden', 'Sem permissões para aceder a este módulo.', ['status'=>403]);
        }
        return true;
    }

    public function assignments(\WP_REST_Request $request) {
        try {
            return rest_ensure_response([
                'items' => ShoppingService::listAssignmentsForUser(get_current_user_id(), [
                    'client_id' => absint($request->get_param('client_id')),
                    'project_id' => absint($request->get_param('project_id')),
                ]),
            ]);
        } catch (\Throwable $error) {
            return new \WP_Error('fieldflow_shopping_list_failed', $error->getMessage(), ['status'=>500]);
        }
    }

    public function assignment(\WP_REST_Request $request) {
        try {
            return rest_ensure_response(ShoppingService::assignmentPayload(absint($request['id']), get_current_user_id(), absint($request->get_param('location_id'))));
        } catch (\Throwable $error) {
            $status = stripos($error->getMessage(), 'atribu') !== false ? 403 : 404;
            return new \WP_Error('fieldflow_shopping_assignment_failed', $error->getMessage(), ['status'=>$status]);
        }
    }

    public function associateLocation(\WP_REST_Request $request) {
        try {
            $payload = $request->get_json_params();
            if (!is_array($payload)) $payload = $request->get_body_params();
            $locationId = absint($payload['location_id'] ?? 0);
            if (!$locationId) return new \WP_Error('fieldflow_shopping_location_required', 'Seleciona a loja a associar.', ['status'=>400]);
            return rest_ensure_response(ShoppingService::associateLocationToInsignia(absint($request['id']), get_current_user_id(), $locationId));
        } catch (\Throwable $error) {
            return new \WP_Error('fieldflow_shopping_association_failed', $error->getMessage(), ['status'=>400]);
        }
    }

    public function submit(\WP_REST_Request $request) {
        try {
            $payload = $request->get_json_params();
            if (!is_array($payload)) $payload = $request->get_body_params();
            $assignmentId = absint($payload['assignment_id'] ?? 0);
            if (!$assignmentId) return new \WP_Error('fieldflow_shopping_assignment_required', 'Seleciona uma insígnia antes de submeter.', ['status'=>400]);
            return rest_ensure_response(ShoppingService::submit($assignmentId, get_current_user_id(), $payload));
        } catch (\Throwable $error) {
            return new \WP_Error('fieldflow_shopping_submit_failed', $error->getMessage(), ['status'=>400]);
        }
    }

    public function results(\WP_REST_Request $request) {
        try {
            return rest_ensure_response(ShoppingResultsService::query([
                'client_id' => absint($request->get_param('client_id')),
                'project_id' => absint($request->get_param('project_id')),
                'location_id' => absint($request->get_param('location_id')),
                'user_id' => absint($request->get_param('user_id')),
                'batch_id' => absint($request->get_param('batch_id')),
                'page' => max(1, absint($request->get_param('page'))),
                'per_page' => max(1, absint($request->get_param('per_page'))),
                'from' => sanitize_text_field((string)$request->get_param('from')),
                'to' => sanitize_text_field((string)$request->get_param('to')),
                'q' => sanitize_text_field((string)$request->get_param('q')),
            ], get_current_user_id()));
        } catch (\Throwable $error) {
            return new \WP_Error('fieldflow_shopping_results_failed', $error->getMessage(), ['status'=>403]);
        }
    }

    public function result(\WP_REST_Request $request) {
        try {
            return rest_ensure_response(ShoppingResultsService::detail(absint($request['id']), get_current_user_id()));
        } catch (\Throwable $error) {
            return new \WP_Error('fieldflow_shopping_result_failed', $error->getMessage(), ['status'=>404]);
        }
    }

}
