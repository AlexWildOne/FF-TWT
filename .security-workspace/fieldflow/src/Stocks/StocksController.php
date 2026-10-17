<?php
namespace RoutesPro\Stocks;

use RoutesPro\Support\Permissions;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

class StocksController {
    public function register_routes(): void {
        register_rest_route('routespro/v1', '/stocks/team', [
            'methods' => 'GET',
            'callback' => [$this, 'team'],
            'permission_callback' => [$this, 'canAccess'],
        ]);
        register_rest_route('routespro/v1', '/stocks/client', [
            'methods' => 'GET',
            'callback' => [$this, 'client'],
            'permission_callback' => [$this, 'canAccess'],
        ]);
        register_rest_route('routespro/v1', '/stocks/viewed', [
            'methods' => 'POST',
            'callback' => [$this, 'viewed'],
            'permission_callback' => [$this, 'canAccess'],
        ]);
    }

    public function canAccess() {
        return is_user_logged_in() && Permissions::can_access_front();
    }

    public function team(WP_REST_Request $request) {
        $payload = StockService::teamPayload(
            get_current_user_id(),
            absint($request->get_param('client_id')),
            absint($request->get_param('project_id')),
            true
        );
        return $this->freshResponse($payload);
    }

    public function client(WP_REST_Request $request) {
        $clientId = absint($request->get_param('client_id'));
        $projectId = absint($request->get_param('project_id'));
        $payload = StockService::clientPayload($clientId, $projectId, get_current_user_id());
        if (!empty($payload['error']) && is_wp_error($payload['error'])) return $payload['error'];
        return $this->freshResponse($payload);
    }

    private function freshResponse(array $payload): WP_REST_Response {
        $response = new WP_REST_Response($payload, 200);
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        $response->header('X-FieldFlow-Data-Version', defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '');
        return $response;
    }

    public function viewed(WP_REST_Request $request) {
        $ids = $request->get_param('batch_ids');
        if (!is_array($ids)) $ids = [];
        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        if (!$ids) return new WP_Error('fieldflow_stock_batches', 'Não foram indicados lotes de stock.', ['status'=>400]);

        global $wpdb;
        $ph = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,client_id,project_id FROM ' . StockService::batchTable() . " WHERE id IN ({$ph})", ...$ids), ARRAY_A) ?: [];
        $allowed = [];
        foreach ($rows as $row) {
            $check = Permissions::assert_scope_or_error((int)$row['client_id'], (int)$row['project_id']);
            if (!is_wp_error($check)) $allowed[] = (int)$row['id'];
        }
        if (!$allowed) return new WP_Error('forbidden', 'Sem acesso aos stocks selecionados.', ['status'=>403]);
        StockService::recordViews($allowed, get_current_user_id());
        return new WP_REST_Response(['ok'=>true,'batch_ids'=>$allowed], 200);
    }
}
