<?php
namespace RoutesPro\Billing;

use RoutesPro\Support\Permissions;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) exit;

class BillingController {
    public function register_routes(): void {
        register_rest_route('fieldflow/v1','/billing/account',['methods'=>'GET','callback'=>[$this,'account'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('fieldflow/v1','/billing/portal',['methods'=>'POST','callback'=>[$this,'portal'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('fieldflow/v1','/billing/checkout',['methods'=>'POST','callback'=>[$this,'checkout'],'permission_callback'=>function(){return is_user_logged_in();}]);
        register_rest_route('fieldflow/v1','/billing/sync',['methods'=>'POST','callback'=>[$this,'sync'],'permission_callback'=>function(){return current_user_can('routespro_manage');}]);
        register_rest_route('fieldflow-billing/v1','/stripe/webhook',['methods'=>'POST','callback'=>[$this,'webhook'],'permission_callback'=>'__return_true']);
        register_rest_route('fieldflow/v1','/billing/signup/intent',['methods'=>'POST','callback'=>[$this,'signupIntent'],'permission_callback'=>'__return_true']);
        register_rest_route('fieldflow/v1','/billing/signup/checkout',['methods'=>'POST','callback'=>[$this,'signupCheckout'],'permission_callback'=>function(){return is_user_logged_in();}]);
    }

    public function account(WP_REST_Request$request){$clientId=absint($request->get_param('client_id'));if(!$clientId)$clientId=\RoutesPro\SaaS\EntitlementService::resolveClientId();if(!$clientId)return new WP_Error('fieldflow_billing_client','Seleciona um cliente.',['status'=>400]);if(!current_user_can('routespro_manage')&&!Permissions::is_allowed_client($clientId))return new WP_Error('fieldflow_billing_forbidden','Sem acesso ao cliente.',['status'=>403]);return BillingService::account($clientId);}
    public function checkout(WP_REST_Request$request){$clientId=absint($request->get_param('client_id'));$planId=absint($request->get_param('plan_id'));$interval=sanitize_key((string)$request->get_param('interval'));if(!current_user_can('routespro_manage')&&!Permissions::is_allowed_client($clientId))return new WP_Error('fieldflow_billing_forbidden','Sem acesso ao cliente.',['status'=>403]);return BillingService::createCheckout($clientId,$planId,$interval,get_current_user_id(),0,(string)$request->get_param('return_url'));}
    public function portal(WP_REST_Request$request){$clientId=absint($request->get_param('client_id'));return BillingService::createPortalSession($clientId,get_current_user_id(),(string)$request->get_param('return_url'));}
    public function sync(WP_REST_Request$request){return BillingService::syncClientFromStripe(absint($request->get_param('client_id')));}
    public function webhook(WP_REST_Request$request){return BillingService::processStripeWebhook((string)$request->get_body(),(string)$request->get_header('stripe-signature'));}
    public function signupIntent(WP_REST_Request$request){return SignupService::createIntentRest($request);}
    public function signupCheckout(WP_REST_Request$request){return SignupService::checkoutRest($request);}
}
