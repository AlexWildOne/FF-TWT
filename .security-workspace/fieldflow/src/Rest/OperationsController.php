<?php
namespace RoutesPro\Rest;

use RoutesPro\Mobile\DeviceManager;
use WP_REST_Server;

if (!defined('ABSPATH')) exit;

class OperationsController {
    public function register_routes(): void {
        register_rest_route('fieldflow-mobile/v1','/device/policy',[
            'methods'=>WP_REST_Server::READABLE,
            'callback'=>[DeviceManager::class,'restPolicy'],
            'permission_callback'=>static function(){return is_user_logged_in();},
        ]);
    }
}
