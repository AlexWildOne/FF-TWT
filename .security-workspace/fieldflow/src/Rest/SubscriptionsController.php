<?php
namespace RoutesPro\Rest;

use RoutesPro\SaaS\EntitlementService;
use RoutesPro\Support\Permissions;
use WP_REST_Request;
use WP_Error;

if (!defined('ABSPATH')) exit;

class SubscriptionsController {
    public function register_routes(): void {
        register_rest_route('fieldflow/v1','/entitlements',[
            'methods'=>'GET','callback'=>[$this,'get'],'permission_callback'=>function(){ return is_user_logged_in(); }
        ]);
    }
    public function get(WP_REST_Request $request) {
        $clientId=absint($request->get_param('client_id'));
        if($clientId && !Permissions::is_allowed_client($clientId)) return new WP_Error('forbidden','Sem acesso ao cliente.',['status'=>403]);
        if(!$clientId) $clientId=EntitlementService::resolveClientId();
        if(!$clientId) return ['client_id'=>0,'capabilities'=>[],'subscription'=>null,'usage'=>[]];
        return EntitlementService::effectiveForClient($clientId);
    }
}
