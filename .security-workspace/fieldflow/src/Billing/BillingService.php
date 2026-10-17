<?php
namespace RoutesPro\Billing;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\SaaS\PlanRepository;
use RoutesPro\Support\Permissions;
use WP_Error;

if (!defined('ABSPATH')) exit;

class BillingService {
    private static bool $registered = false;
    private static string $eventEnvironment = 'live';

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        add_action('fieldflow_billing_maintenance', [self::class, 'maintenance']);
        if (!wp_next_scheduled('fieldflow_billing_maintenance')) wp_schedule_event(time() + 300, 'fieldflow_billing_hourly', 'fieldflow_billing_maintenance');
        add_action('admin_post_fieldflow_billing_sandbox_checkout', [self::class, 'renderSandboxCheckout']);
        add_action('admin_post_nopriv_fieldflow_billing_sandbox_checkout', [self::class, 'renderSandboxCheckout']);
        add_action('admin_post_fieldflow_billing_sandbox_complete', [self::class, 'completeSandboxCheckout']);
        add_action('admin_post_nopriv_fieldflow_billing_sandbox_complete', [self::class, 'completeSandboxCheckout']);
    }

    public static function cronSchedules(array $schedules): array {
        $schedules['fieldflow_billing_hourly'] = ['interval'=>HOUR_IN_SECONDS,'display'=>'FieldFlow faturação a cada hora'];
        return $schedules;
    }

    public static function account(int $clientId): array {
        $saas = PlanRepository::subscription($clientId);
        $billing = BillingRepository::subscriptionByClient($clientId);
        $customer = BillingRepository::customer($clientId);
        $settings = Settings::get();
        $localSandbox = Settings::isSandbox() && (string)($settings['sandbox_driver'] ?? 'local') === 'local';
        $managedExternally = !$localSandbox && $billing && !empty($billing['external_subscription_id']) && strpos((string)$billing['external_subscription_id'],'sandbox_sub_') !== 0 && in_array((string)$billing['status'],['active','trial','trialing','past_due','suspended'],true);
        $portalAvailable = !$localSandbox && $customer && !empty($customer['external_customer_id']) && Settings::secretKey(Settings::mode()) !== '';
        $plans = [];
        foreach (PlanRepository::plans(true) as $plan) {
            if ((string)$plan['slug'] === 'legacy') continue;
            $monthly = self::commercialPrice((int)$plan['id'], 'month');
            $annual = self::commercialPrice((int)$plan['id'], 'year');
            $plans[] = [
                'id'=>(int)$plan['id'],'slug'=>(string)$plan['slug'],'name'=>(string)$plan['name'],'description'=>(string)$plan['description'],
                'monthly_price'=>(float)$monthly['amount'],'annual_price'=>(float)$annual['amount'],'currency'=>(string)($monthly['currency'] ?: $annual['currency'] ?: $plan['currency']),
                'monthly_checkout'=>!$managedExternally&&self::checkoutAvailable((int)$plan['id'],'month'),
                'annual_checkout'=>!$managedExternally&&self::checkoutAvailable((int)$plan['id'],'year'),
                'downgrade_impact'=>class_exists(ProductionHardening::class)?ProductionHardening::downgradeImpact($clientId,(int)$plan['id']):[],
            ];
        }
        return [
            'client_id'=>$clientId,
            'mode'=>Settings::mode(),
            'billing_enabled'=>Settings::isEnabled(),
            'portal_available'=>$portalAvailable,
            'portal_page_url'=>Settings::portalUrl(),
            'mobile_bridge_target'=>'billing',
            'managed_externally'=>$managedExternally,
            'subscription'=>$saas ? [
                'plan_id'=>(int)$saas['plan_id'],'plan_slug'=>(string)$saas['plan_slug'],'plan_name'=>(string)$saas['plan_name'],
                'status'=>(string)$saas['status'],'renews_at'=>$saas['renews_at'],'expires_at'=>$saas['expires_at'],'trial_ends_at'=>$saas['trial_ends_at'],
            ] : null,
            'billing'=>$billing ? [
                'status'=>(string)$billing['status'],'billing_interval'=>(string)$billing['billing_interval'],'billing_user_id'=>(int)($billing['billing_user_id']??0),
                'current_period_end'=>$billing['current_period_end'],'grace_until'=>$billing['grace_until'],
                'cancel_at_period_end'=>!empty($billing['cancel_at_period_end']),
            ] : null,
            'plans'=>$plans,
        ];
    }

    public static function createCheckout(int $clientId, int $planId, string $interval, int $actorId = 0, int $billingUserId = 0, string $returnUrl = '') {
        global $wpdb;
        if (!BillingRepository::tablesReady()) return new WP_Error('fieldflow_billing_tables', 'As tabelas de faturação ainda não estão disponíveis.', ['status'=>503]);
        if ($clientId <= 0 || $planId <= 0) return new WP_Error('fieldflow_billing_scope', 'Cliente ou plano inválido.', ['status'=>400]);
        if ($actorId > 0 && !current_user_can('routespro_manage') && !Permissions::is_allowed_client($clientId, $actorId)) return new WP_Error('fieldflow_billing_forbidden','Sem acesso a este cliente.',['status'=>403]);
        $plan = PlanRepository::plan($planId);
        if (!$plan || empty($plan['is_active']) || (string)$plan['slug'] === 'legacy') return new WP_Error('fieldflow_billing_plan','O plano selecionado não está disponível para checkout automático.',['status'=>400]);
        $interval = $interval === 'year' ? 'year' : 'month';
        $commercialPrice = self::commercialPrice($planId, $interval);
        $amount = (float)$commercialPrice['amount'];
        if ($amount <= 0) return new WP_Error('fieldflow_billing_price','O plano não tem um preço comercial configurado para esta periodicidade.',['status'=>400]);
        if (!Settings::isEnabled()) return new WP_Error('fieldflow_billing_disabled','Os pagamentos automáticos estão desativados.',['status'=>503]);
        $billingSettings = Settings::get();
        $localSandbox = Settings::isSandbox() && (string)($billingSettings['sandbox_driver'] ?? 'local') === 'local';
        $existingSubscription = BillingRepository::subscriptionByClient($clientId,Settings::mode());
        if(!$localSandbox&&$existingSubscription&&!empty($existingSubscription['external_subscription_id'])&&strpos((string)$existingSubscription['external_subscription_id'],'sandbox_sub_')!==0&&in_array((string)$existingSubscription['status'],['active','trial','trialing','past_due','suspended'],true))return new WP_Error('fieldflow_billing_existing_subscription','Este cliente já tem uma subscrição Stripe. Usa Gerir faturação para alterar o plano ou o método de pagamento, evitando uma segunda subscrição.',['status'=>409]);

        $client = $wpdb->get_row($wpdb->prepare('SELECT id,name,email,taxid FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d',$clientId),ARRAY_A);
        if (!$client) return new WP_Error('fieldflow_billing_client','Cliente não encontrado.',['status'=>404]);
        $billingUserId = self::resolveBillingUser($clientId, $billingUserId, $actorId);
        if ($billingUserId > 0 && !get_userdata($billingUserId)) return new WP_Error('fieldflow_billing_user','O utilizador associado à subscrição não existe.',['status'=>400]);
        $token = bin2hex(random_bytes(32));
        $expires = time() + 30 * MINUTE_IN_SECONDS;

        if (Settings::isSandbox() && (string)($billingSettings['sandbox_driver'] ?? 'local') === 'local') {
            $safeReturnUrl = self::sanitizeReturnUrl($returnUrl);
            $url = add_query_arg(array_filter(['action'=>'fieldflow_billing_sandbox_checkout','token'=>$token,'return_url'=>$safeReturnUrl]), admin_url('admin-post.php'));
            $id = BillingRepository::createCheckoutSession(['token'=>$token,'client_id'=>$clientId,'billing_user_id'=>$billingUserId,'plan_id'=>$planId,'billing_interval'=>$interval,'status'=>'open','checkout_url'=>$url,'expires_at'=>$expires,'created_by'=>$actorId]);
            self::audit('billing.checkout_created','billing_checkout',$id,['client_id'=>$clientId,'plan_id'=>$planId,'interval'=>$interval,'mode'=>'sandbox','billing_user_id'=>$billingUserId]);
            return ['id'=>$id,'mode'=>'sandbox','url'=>$url,'expires_at'=>wp_date(DATE_ATOM,$expires)];
        }

        $environment = Settings::mode();
        $price = BillingRepository::priceForPlan($planId,$interval,$environment);
        if (!$price || empty($price['external_price_id'])) return new WP_Error('fieldflow_billing_price_mapping','Falta associar o Price ID Stripe deste plano e periodicidade.',['status'=>400]);
        $settings = $billingSettings;
        $safeReturnUrl = self::sanitizeReturnUrl($returnUrl);
        $successUrl = $safeReturnUrl !== '' ? add_query_arg('fieldflow_billing','success',$safeReturnUrl) : (string)($settings['success_url'] ?: add_query_arg('fieldflow_billing','success',home_url('/')));
        $cancelUrl = $safeReturnUrl !== '' ? add_query_arg('fieldflow_billing','cancelled',$safeReturnUrl) : (string)($settings['cancel_url'] ?: add_query_arg('fieldflow_billing','cancelled',home_url('/')));
        $existingCustomer = BillingRepository::customer($clientId,$environment);
        $params = [
            'mode'=>'subscription',
            'line_items'=>[['price'=>(string)$price['external_price_id'],'quantity'=>1]],
            'success_url'=>str_replace(['%7B','%7D'],['{','}'],add_query_arg('session_id','{CHECKOUT_SESSION_ID}',$successUrl)),
            'cancel_url'=>$cancelUrl,
            'client_reference_id'=>(string)$clientId,
            'metadata'=>['client_id'=>(string)$clientId,'plan_id'=>(string)$planId,'billing_interval'=>$interval,'wp_user_id'=>(string)$billingUserId],
            'subscription_data'=>['metadata'=>['client_id'=>(string)$clientId,'plan_id'=>(string)$planId,'billing_interval'=>$interval,'wp_user_id'=>(string)$billingUserId]],
            'allow_promotion_codes'=>!empty($settings['allow_promotion_codes']),
            'billing_address_collection'=>!empty($settings['billing_address_required']) ? 'required' : 'auto',
            'tax_id_collection'=>['enabled'=>!empty($settings['tax_id_collection'])],
            'automatic_tax'=>['enabled'=>!empty($settings['automatic_tax'])],
        ];
        if ($existingCustomer && !empty($existingCustomer['external_customer_id'])) $params['customer']=(string)$existingCustomer['external_customer_id'];
        elseif (!empty($client['email'])) $params['customer_email']=(string)$client['email'];
        $stripe = new StripeClient(Settings::secretKey($environment));
        $result = $stripe->createCheckoutSession($params,'fieldflow_checkout_'.$clientId.'_'.$planId.'_'.$interval.'_'.substr(hash('sha256',$token),0,24));
        if (is_wp_error($result)) return $result;
        $externalId=(string)($result['id']??'');$url=(string)($result['url']??'');if($externalId===''||$url==='')return new WP_Error('fieldflow_billing_checkout_response','A Stripe não devolveu uma sessão de checkout válida.',['status'=>502]);
        $id=BillingRepository::createCheckoutSession(['token'=>$token,'external_session_id'=>$externalId,'client_id'=>$clientId,'billing_user_id'=>$billingUserId,'plan_id'=>$planId,'billing_interval'=>$interval,'status'=>'open','checkout_url'=>$url,'expires_at'=>(int)($result['expires_at']??$expires),'created_by'=>$actorId]);
        self::audit('billing.checkout_created','billing_checkout',$id,['client_id'=>$clientId,'plan_id'=>$planId,'interval'=>$interval,'mode'=>$environment,'external_session_id'=>$externalId,'billing_user_id'=>$billingUserId]);
        return ['id'=>$id,'mode'=>$environment,'url'=>$url,'expires_at'=>wp_date(DATE_ATOM,(int)($result['expires_at']??$expires))];
    }

    public static function canManageClientBilling(int $clientId, int $actorId = 0): bool {
        $uid = get_current_user_id();
        if ($clientId <= 0 || $uid <= 0 || ($actorId > 0 && $actorId !== $uid)) return false;
        if (user_can($uid, 'routespro_manage')) return true;
        if (user_can($uid, 'routespro_manage_billing') && Permissions::is_allowed_client($clientId, $uid)) return true;
        $subscription = BillingRepository::subscriptionByClient($clientId, Settings::mode());
        return $subscription && RoleMappingService::userIdFromSubscription($subscription) === $uid;
    }

    public static function createPortalSession(int $clientId,int $actorId=0,string $returnUrl=''){
        if($clientId<=0)return new WP_Error('fieldflow_billing_scope','Cliente inválido.',['status'=>400]);
        if(!self::canManageClientBilling($clientId,$actorId))return new WP_Error('fieldflow_billing_forbidden','Sem autorização financeira para este cliente.',['status'=>403]);
        $settings=Settings::get();$environment=Settings::mode();
        if($environment==='sandbox'&&(string)($settings['sandbox_driver']??'local')==='local')return new WP_Error('fieldflow_billing_portal_sandbox','O simulador local não necessita do portal Stripe.',['status'=>400]);
        $customer=BillingRepository::customer($clientId,$environment);if(!$customer||empty($customer['external_customer_id']))return new WP_Error('fieldflow_billing_customer_mapping','Este cliente ainda não tem um Customer ID Stripe associado neste ambiente.',['status'=>404]);
        $stripe=new StripeClient(Settings::secretKey($environment));if(!$stripe->isConfigured())return new WP_Error('fieldflow_billing_not_configured','A chave Stripe deste ambiente ainda não está configurada.',['status'=>503]);
        $returnUrl=self::sanitizeReturnUrl($returnUrl);if($returnUrl==='')$returnUrl=(string)($settings['success_url']?:\RoutesPro\Billing\Settings::portalUrl());$result=$stripe->createBillingPortalSession(['customer'=>(string)$customer['external_customer_id'],'return_url'=>$returnUrl,'locale'=>'auto'],'fieldflow_portal_'.$environment.'_'.$clientId.'_'.substr(hash('sha256',microtime(true).wp_rand()),0,24));
        if(is_wp_error($result))return$result;$url=(string)($result['url']??'');if($url==='')return new WP_Error('fieldflow_billing_portal_response','A Stripe não devolveu um URL válido para o portal.',['status'=>502]);
        self::audit('billing.portal_created','billing_customer',(int)$customer['id'],['client_id'=>$clientId,'mode'=>$environment]);return['url'=>$url,'mode'=>$environment];
    }

    public static function renderSandboxCheckout(): void {
        $token = sanitize_text_field((string)($_GET['token'] ?? ''));
        $session = $token !== '' ? BillingRepository::checkoutByToken($token) : null;
        if (!$session || (string)$session['environment'] !== 'sandbox' || (string)$session['status'] !== 'open' || (!empty($session['expires_at']) && strtotime((string)$session['expires_at']) < time())) wp_die('Sessão sandbox inválida ou expirada.','FieldFlow',['response'=>410]);
        $returnUrl=self::sanitizeReturnUrl((string)($_GET['return_url']??''));
        $plan=PlanRepository::plan((int)$session['plan_id']);
        global$wpdb;$clientName=(string)$wpdb->get_var($wpdb->prepare('SELECT name FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d',(int)$session['client_id']));
        $commercialPrice=self::commercialPrice((int)$session['plan_id'],(string)$session['billing_interval']);$amount=(float)$commercialPrice['amount'];
        nocache_headers();
        echo '<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FieldFlow Sandbox Checkout</title><style>body{margin:0;background:#f1f5f9;font-family:system-ui,sans-serif;color:#0f172a}.box{max-width:560px;margin:8vh auto;background:#fff;border-radius:24px;padding:28px;box-shadow:0 20px 60px rgba(15,23,42,.12)}.tag{display:inline-block;background:#fef3c7;color:#92400e;padding:6px 10px;border-radius:99px;font-weight:700}.price{font-size:42px;font-weight:800;margin:16px 0}.actions{display:grid;gap:10px}.btn{border:0;border-radius:12px;padding:14px 18px;font-weight:700;cursor:pointer}.ok{background:#16a34a;color:#fff}.fail{background:#fee2e2;color:#991b1b}</style></head><body><div class="box"><span class="tag">Modo sandbox</span><h1>Simulação de checkout</h1><p><strong>'.esc_html($clientName).'</strong><br>'.esc_html((string)($plan['name']??'Plano')).' · '.((string)$session['billing_interval']==='year'?'anual':'mensal').'</p><div class="price">'.esc_html(number_format_i18n($amount,2)).' €</div><p>Nenhum pagamento real será efetuado. Escolhe o resultado para testar a sincronização.</p><div class="actions"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="fieldflow_billing_sandbox_complete"><input type="hidden" name="token" value="'.esc_attr($token).'"><input type="hidden" name="result" value="paid"><input type="hidden" name="return_url" value="'.esc_attr($returnUrl).'"><button class="btn ok" type="submit">Simular pagamento aprovado</button></form><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="fieldflow_billing_sandbox_complete"><input type="hidden" name="token" value="'.esc_attr($token).'"><input type="hidden" name="result" value="failed"><input type="hidden" name="return_url" value="'.esc_attr($returnUrl).'"><button class="btn fail" type="submit">Simular pagamento falhado</button></form></div></div></body></html>';
        exit;
    }

    public static function completeSandboxCheckout(): void {
        $token=sanitize_text_field((string)($_POST['token']??''));$result=sanitize_key((string)($_POST['result']??''));$session=$token!==''?BillingRepository::checkoutByToken($token):null;
        if(!$session||(string)$session['environment']!=='sandbox'||(string)$session['status']!=='open')wp_die('Sessão sandbox inválida.','FieldFlow',['response'=>400]);
        BillingRepository::updateCheckout((int)$session['id'],['status'=>'processing']);
        $clientId=(int)$session['client_id'];$billingUserId=(int)($session['billing_user_id']??0);$planId=(int)$session['plan_id'];$interval=(string)$session['billing_interval'];$now=time();$previousSubscription=BillingRepository::subscriptionByClient($clientId,'sandbox');$previousBillingUserId=(int)($previousSubscription['billing_user_id']??0);
        $plan=PlanRepository::plan($planId);$commercialPrice=self::commercialPrice($planId,$interval);$amountMinor=(int)round((float)$commercialPrice['amount']*100);$invoiceId='sandbox_in_'.$session['id'];
        if($result==='paid'){
            $periodEnd=$interval==='year'?strtotime('+1 year',$now):strtotime('+1 month',$now);
            BillingRepository::upsertSubscription($clientId,['external_customer_id'=>'sandbox_cus_'.$clientId,'external_subscription_id'=>'sandbox_sub_'.$session['id'],'billing_user_id'=>$billingUserId,'plan_id'=>$planId,'billing_interval'=>$interval,'status'=>'active','current_period_start'=>$now,'current_period_end'=>$periodEnd,'grace_until'=>null,'latest_invoice_id'=>$invoiceId,'meta'=>['sandbox'=>true,'billing_user_id'=>$billingUserId]],'sandbox');
            BillingRepository::upsertCustomer($clientId,['external_customer_id'=>'sandbox_cus_'.$clientId,'status'=>'active'],'sandbox');
            BillingRepository::upsertInvoice(['environment'=>'sandbox','external_invoice_id'=>$invoiceId,'client_id'=>$clientId,'external_subscription_id'=>'sandbox_sub_'.$session['id'],'invoice_number'=>'SANDBOX-'.$session['id'],'currency'=>(string)($commercialPrice['currency']??($plan['currency']??'EUR')),'subtotal_minor'=>$amountMinor,'tax_minor'=>0,'total_minor'=>$amountMinor,'amount_paid_minor'=>$amountMinor,'status'=>'paid','paid_at'=>$now,'period_start'=>$now,'period_end'=>$periodEnd]);
            self::syncSaas($clientId,$planId,'active',$periodEnd,null,'Pagamento sandbox aprovado.');RoleMappingService::syncClient($clientId,'sandbox','Pagamento sandbox aprovado.');SignupService::markCompleted($clientId,$billingUserId,$planId,'Pagamento sandbox aprovado.');
            BillingRepository::updateCheckout((int)$session['id'],['status'=>'completed','external_session_id'=>'sandbox_cs_'.$session['id']]);
            self::audit('billing.payment_succeeded','billing_checkout',(int)$session['id'],['client_id'=>$clientId,'plan_id'=>$planId,'mode'=>'sandbox','billing_user_id'=>$billingUserId,'invoice_id'=>$invoiceId,'current_period_end'=>wp_date('Y-m-d H:i:s',$periodEnd)]);
            $message='Pagamento sandbox aprovado. A subscrição foi ativada.';
        }else{
            $graceUntil=strtotime('+'.max(0,(int)Settings::get()['grace_days']).' days',$now);
            BillingRepository::upsertSubscription($clientId,['external_customer_id'=>'sandbox_cus_'.$clientId,'external_subscription_id'=>'sandbox_sub_'.$session['id'],'billing_user_id'=>$billingUserId,'plan_id'=>$planId,'billing_interval'=>$interval,'status'=>'past_due','current_period_start'=>$now,'current_period_end'=>$now,'grace_until'=>$graceUntil,'latest_invoice_id'=>$invoiceId,'meta'=>['sandbox'=>true,'billing_user_id'=>$billingUserId]],'sandbox');
            BillingRepository::upsertCustomer($clientId,['external_customer_id'=>'sandbox_cus_'.$clientId,'status'=>'past_due'],'sandbox');
            BillingRepository::upsertInvoice(['environment'=>'sandbox','external_invoice_id'=>$invoiceId,'client_id'=>$clientId,'external_subscription_id'=>'sandbox_sub_'.$session['id'],'invoice_number'=>'SANDBOX-'.$session['id'],'currency'=>(string)($commercialPrice['currency']??($plan['currency']??'EUR')),'subtotal_minor'=>$amountMinor,'tax_minor'=>0,'total_minor'=>$amountMinor,'amount_paid_minor'=>0,'status'=>'open','due_at'=>$graceUntil,'period_start'=>$now,'period_end'=>$now]);
            self::syncSaas($clientId,$planId,'past_due',null,$graceUntil,'Pagamento sandbox falhado.');RoleMappingService::syncClient($clientId,'sandbox','Pagamento sandbox falhado.');
            BillingRepository::updateCheckout((int)$session['id'],['status'=>'failed','external_session_id'=>'sandbox_cs_'.$session['id']]);
            self::notifyFailure($clientId,$graceUntil,'sandbox',$invoiceId);
            self::audit('billing.payment_failed','billing_checkout',(int)$session['id'],['client_id'=>$clientId,'plan_id'=>$planId,'mode'=>'sandbox','billing_user_id'=>$billingUserId,'invoice_id'=>$invoiceId,'grace_until'=>wp_date('Y-m-d H:i:s',$graceUntil)]);
            $message='Pagamento sandbox falhado. Foi aplicado o período de tolerância.';
        }
        if($previousBillingUserId>0&&$previousBillingUserId!==$billingUserId){RoleMappingService::detachUserFromClient($clientId,$previousBillingUserId);RoleMappingService::releaseUser($previousBillingUserId,'Utilizador alterado numa subscrição sandbox.');}
        $returnUrl=self::sanitizeReturnUrl((string)($_POST['return_url']??''));if($returnUrl!==''){wp_safe_redirect(add_query_arg('fieldflow_billing',$result==='paid'?'success':'failed',$returnUrl),303,'FieldFlow Sandbox');exit;}
        nocache_headers();echo '<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FieldFlow</title><style>body{font-family:system-ui;background:#f1f5f9;margin:0}.box{max-width:560px;margin:12vh auto;background:#fff;padding:30px;border-radius:24px;text-align:center;box-shadow:0 20px 60px rgba(15,23,42,.12)}a{display:inline-block;margin-top:16px;padding:12px 18px;background:#0f172a;color:#fff;border-radius:12px;text-decoration:none}</style></head><body><div class="box"><h1>Teste concluído</h1><p>'.esc_html($message).'</p><a href="'.esc_url(home_url('/')).'">Voltar ao FieldFlow</a></div></body></html>';exit;
    }

    private static function sanitizeReturnUrl(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $safe = wp_validate_redirect(esc_url_raw($url), '');
        if ($safe === '') return '';
        $homeHost = strtolower((string)wp_parse_url(home_url('/'), PHP_URL_HOST));
        $targetHost = strtolower((string)wp_parse_url($safe, PHP_URL_HOST));
        if ($homeHost === '' || $targetHost === '' || $homeHost !== $targetHost) return '';
        return esc_url_raw(remove_query_arg(['fieldflow_billing','session_id'], $safe));
    }

    public static function verifyStripeSignature(string $payload,string $header,string $secret,int $tolerance=300):bool{
        if($payload===''||$header===''||$secret==='')return false;$parts=[];foreach(explode(',',$header)as$piece){$pair=explode('=',trim($piece),2);if(count($pair)===2)$parts[$pair[0]][]=$pair[1];}$timestamp=(int)($parts['t'][0]??0);if($timestamp<=0||abs(time()-$timestamp)>$tolerance)return false;$expected=hash_hmac('sha256',$timestamp.'.'.$payload,$secret);foreach((array)($parts['v1']??[])as$signature)if(hash_equals($expected,(string)$signature))return true;return false;
    }

    public static function processStripeWebhook(string $payload,string $signature) {
        $verifiedEnvironment='';
        foreach(['sandbox','live'] as $candidate){$secret=Settings::webhookSecret($candidate);if($secret!==''&&self::verifyStripeSignature($payload,$signature,$secret)){$verifiedEnvironment=$candidate;break;}}
        if($verifiedEnvironment==='')return new WP_Error('fieldflow_billing_signature','Assinatura Stripe inválida.',['status'=>400]);
        $event=json_decode($payload,true);if(!is_array($event)||empty($event['id'])||empty($event['type']))return new WP_Error('fieldflow_billing_event','Evento Stripe inválido.',['status'=>400]);
        self::$eventEnvironment=!empty($event['livemode'])?'live':'sandbox';
        if(self::$eventEnvironment!==$verifiedEnvironment)return new WP_Error('fieldflow_billing_environment','O ambiente do evento não corresponde ao segredo usado na assinatura.',['status'=>400]);
        $eventId=BillingRepository::beginEvent((string)$event['id'],(string)$event['type'],$payload,self::$eventEnvironment,self::eventSummary($event));if($eventId<0)return['received'=>true,'duplicate'=>true];if($eventId===0)return new WP_Error('fieldflow_billing_event_store','Não foi possível registar o evento de faturação.',['status'=>500]);
        try{$object=(array)($event['data']['object']??[]);self::dispatchStripeEvent((string)$event['type'],$object);BillingRepository::finishEvent($eventId,'processed');return['received'=>true];}catch(\Throwable$e){BillingRepository::finishEvent($eventId,'failed',$e->getMessage());if(class_exists('RoutesPro\\Observability\\Monitor'))\RoutesPro\Observability\Monitor::captureException($e,['context_key'=>'billing_webhook','event_type'=>(string)$event['type']]);return new WP_Error('fieldflow_billing_processing',$e->getMessage(),['status'=>500]);}
    }

    public static function dispatchStripeEvent(string $type,array $object):void{
        if($type==='checkout.session.completed'){self::syncCheckoutObject($object);return;}
        if(in_array($type,['customer.subscription.created','customer.subscription.updated','customer.subscription.deleted'],true)){self::syncSubscriptionObject($object);return;}
        if(in_array($type,['invoice.paid','invoice.payment_failed','invoice.payment_action_required','invoice.finalized','invoice.voided'],true)){self::syncInvoiceObject($object,$type);return;}
        if($type==='charge.refunded'){self::audit('billing.refund_recorded','stripe_charge',0,['charge_id'=>(string)($object['id']??''),'amount_refunded'=>(int)($object['amount_refunded']??0)]);}
    }

    private static function syncCheckoutObject(array $object):void{
        $clientId=absint($object['metadata']['client_id']??$object['client_reference_id']??0);$planId=absint($object['metadata']['plan_id']??0);$billingUserId=absint($object['metadata']['wp_user_id']??0);$interval=(string)($object['metadata']['billing_interval']??'month');$customerId=self::objectId($object['customer']??'');$subscriptionId=self::objectId($object['subscription']??'');
        if($clientId<=0)return;
        if($customerId!=='')BillingRepository::upsertCustomer($clientId,['external_customer_id'=>$customerId,'email'=>(string)($object['customer_details']['email']??''),'billing_name'=>(string)($object['customer_details']['name']??''),'status'=>'active'],self::$eventEnvironment);
        if($subscriptionId!==''){$stripe=new StripeClient(Settings::secretKey(self::$eventEnvironment));$sub=$stripe->retrieveSubscription($subscriptionId);if(!is_wp_error($sub)){if($planId)$sub['metadata']['plan_id']=(string)$planId;if($clientId)$sub['metadata']['client_id']=(string)$clientId;if($interval)$sub['metadata']['billing_interval']=$interval;if($billingUserId)$sub['metadata']['wp_user_id']=(string)$billingUserId;self::syncSubscriptionObject($sub);}}
    }

    private static function syncSubscriptionObject(array $object):void{
        $clientId=absint($object['metadata']['client_id']??0);$planId=absint($object['metadata']['plan_id']??0);$priceId=(string)($object['items']['data'][0]['price']['id']??'');$mapped=$priceId!==''?BillingRepository::planForPrice($priceId,self::$eventEnvironment):null;if(!$planId&&$mapped)$planId=(int)$mapped['plan_id'];if(!$clientId){$customerId=self::objectId($object['customer']??'');global$wpdb;$clientId=(int)$wpdb->get_var($wpdb->prepare('SELECT client_id FROM '.BillingRepository::customersTable().' WHERE external_customer_id=%s AND environment=%s LIMIT 1',$customerId,self::$eventEnvironment));}if($clientId<=0||$planId<=0)return;
        $interval=(string)($object['metadata']['billing_interval']??($object['items']['data'][0]['price']['recurring']['interval']??($mapped['billing_interval']??'month')));$status=self::mapStripeStatus((string)($object['status']??'active'));$periodStart=self::subscriptionPeriod($object,'current_period_start');$periodEnd=self::subscriptionPeriod($object,'current_period_end');$trialEnd=(int)($object['trial_end']??0);$existing=BillingRepository::subscriptionByClient($clientId,self::$eventEnvironment);$previousBillingUserId=absint($existing['billing_user_id']??0);$billingUserId=absint($object['metadata']['wp_user_id']??$previousBillingUserId);$grace=null;
        if($status==='past_due'){$existingGrace=!empty($existing['grace_until'])?strtotime((string)$existing['grace_until']):0;$grace=$existingGrace>time()?$existingGrace:strtotime('+'.max(0,(int)Settings::get()['grace_days']).' days');}
        BillingRepository::upsertSubscription($clientId,['billing_user_id'=>$billingUserId,'external_customer_id'=>self::objectId($object['customer']??''),'external_subscription_id'=>(string)($object['id']??''),'plan_id'=>$planId,'billing_interval'=>$interval==='year'?'year':'month','status'=>$status,'current_period_start'=>$periodStart,'current_period_end'=>$periodEnd,'trial_end'=>$trialEnd,'cancel_at_period_end'=>!empty($object['cancel_at_period_end']),'grace_until'=>$grace,'latest_invoice_id'=>is_array($object['latest_invoice']??null)?(string)($object['latest_invoice']['id']??''):(string)($object['latest_invoice']??''),'meta'=>['stripe_status'=>(string)($object['status']??''),'billing_user_id'=>$billingUserId]],self::$eventEnvironment);
        self::syncSaas($clientId,$planId,$status,$periodEnd?:null,$status==='trial'&&$trialEnd>0?$trialEnd:$grace,'Sincronização automática Stripe.');RoleMappingService::syncClient($clientId,self::$eventEnvironment,'Sincronização automática Stripe.');if(in_array($status,['active','trial'],true))SignupService::markCompleted($clientId,$billingUserId,$planId,'Subscrição Stripe ativa.');if($previousBillingUserId>0&&$previousBillingUserId!==$billingUserId){RoleMappingService::detachUserFromClient($clientId,$previousBillingUserId);RoleMappingService::releaseUser($previousBillingUserId,'Utilizador alterado pela sincronização Stripe.');}
        self::audit('billing.subscription_synced','billing_subscription',0,['client_id'=>$clientId,'plan_id'=>$planId,'status'=>$status,'external_subscription_id'=>(string)($object['id']??''),'billing_user_id'=>$billingUserId,'cancel_at_period_end'=>!empty($object['cancel_at_period_end']),'current_period_end'=>$periodEnd?wp_date('Y-m-d H:i:s',$periodEnd):'']);
    }

    private static function syncInvoiceObject(array $object,string $eventType):void{
        $subscriptionId=self::invoiceSubscriptionId($object);$customerId=self::objectId($object['customer']??'');global$wpdb;$clientId=(int)$wpdb->get_var($wpdb->prepare('SELECT client_id FROM '.BillingRepository::customersTable().' WHERE external_customer_id=%s AND environment=%s LIMIT 1',$customerId,self::$eventEnvironment));if(!$clientId&&$subscriptionId!=='')$clientId=(int)$wpdb->get_var($wpdb->prepare('SELECT client_id FROM '.BillingRepository::subscriptionsTable().' WHERE external_subscription_id=%s AND environment=%s LIMIT 1',$subscriptionId,self::$eventEnvironment));
        $status=(string)($object['status']??($eventType==='invoice.paid'?'paid':'open'));BillingRepository::upsertInvoice(['environment'=>self::$eventEnvironment,'external_invoice_id'=>(string)($object['id']??''),'client_id'=>$clientId,'external_subscription_id'=>$subscriptionId,'invoice_number'=>(string)($object['number']??''),'currency'=>strtoupper((string)($object['currency']??'eur')),'subtotal_minor'=>(int)($object['subtotal']??0),'tax_minor'=>self::invoiceTaxMinor($object),'total_minor'=>(int)($object['total']??0),'amount_paid_minor'=>(int)($object['amount_paid']??0),'status'=>$status,'hosted_invoice_url'=>(string)($object['hosted_invoice_url']??''),'invoice_pdf'=>(string)($object['invoice_pdf']??''),'due_at'=>(int)($object['due_date']??0),'paid_at'=>(int)($object['status_transitions']['paid_at']??0),'period_start'=>(int)($object['period_start']??0),'period_end'=>(int)($object['period_end']??0)]);
        if($clientId<=0)return;$local=BillingRepository::subscriptionByClient($clientId,self::$eventEnvironment);$planId=(int)($local['plan_id']??0);
        if($eventType==='invoice.paid'){
            $periodEnd=(int)($object['period_end']??0);
            BillingRepository::upsertSubscription($clientId,array_merge($local?:[],['plan_id'=>$planId,'external_customer_id'=>$customerId,'external_subscription_id'=>$subscriptionId,'status'=>'active','current_period_end'=>$periodEnd,'grace_until'=>null,'latest_invoice_id'=>(string)($object['id']??'')]),self::$eventEnvironment);
            if($planId)self::syncSaas($clientId,$planId,'active',$periodEnd?:null,null,'Fatura Stripe paga.');RoleMappingService::syncClient($clientId,self::$eventEnvironment,'Fatura Stripe paga.');SignupService::markCompleted($clientId,(int)($local['billing_user_id']??0),$planId,'Fatura Stripe paga.');
            self::audit('billing.invoice_paid','billing_invoice',0,['client_id'=>$clientId,'invoice_id'=>(string)($object['id']??''),'total_minor'=>(int)($object['total']??0)]);
        }
        if(in_array($eventType,['invoice.payment_failed','invoice.payment_action_required'],true)){
            $existingGrace=!empty($local['grace_until'])?strtotime((string)$local['grace_until']):0;$grace=$existingGrace>time()?$existingGrace:strtotime('+'.max(0,(int)Settings::get()['grace_days']).' days');
            BillingRepository::upsertSubscription($clientId,array_merge($local?:[],['plan_id'=>$planId,'external_customer_id'=>$customerId,'external_subscription_id'=>$subscriptionId,'status'=>'past_due','grace_until'=>$grace,'latest_invoice_id'=>(string)($object['id']??'')]),self::$eventEnvironment);
            if($planId)self::syncSaas($clientId,$planId,'past_due',null,$grace,'Pagamento Stripe pendente.');RoleMappingService::syncClient($clientId,self::$eventEnvironment,'Pagamento Stripe pendente.');
            self::notifyFailure($clientId,$grace,self::$eventEnvironment,(string)($object['id']??''));
            self::audit('billing.invoice_payment_failed','billing_invoice',0,['client_id'=>$clientId,'invoice_id'=>(string)($object['id']??''),'grace_until'=>wp_date('Y-m-d H:i:s',$grace)]);
        }
    }

    public static function saveSubscription(array $input, int $actorId = 0) {
        global $wpdb;
        if (!BillingRepository::tablesReady()) return new WP_Error('fieldflow_billing_tables','As tabelas de faturação ainda não estão disponíveis.');
        $recordId = absint($input['subscription_id'] ?? 0);
        $existing = $recordId ? BillingRepository::subscriptionById($recordId) : null;
        if ($recordId && !$existing) return new WP_Error('fieldflow_billing_subscription_missing','A subscrição selecionada já não existe.');
        $clientId = $existing ? (int)$existing['client_id'] : absint($input['client_id'] ?? 0);
        $planId = absint($input['plan_id'] ?? ($existing['plan_id'] ?? 0));
        $previousBillingUserId = absint($existing['billing_user_id'] ?? 0);
        $billingUserId = absint($input['billing_user_id'] ?? $previousBillingUserId);
        $interval = (string)($input['billing_interval'] ?? ($existing['billing_interval'] ?? 'month')) === 'year' ? 'year' : 'month';
        $environment = $existing ? (string)$existing['environment'] : (in_array((string)($input['environment'] ?? Settings::mode()), ['sandbox','live'], true) ? (string)($input['environment'] ?? Settings::mode()) : Settings::mode());
        $plan = PlanRepository::plan($planId);
        if ($clientId <= 0 || !$wpdb->get_var($wpdb->prepare('SELECT id FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d',$clientId))) return new WP_Error('fieldflow_billing_client','Seleciona um cliente válido.');
        if (!$plan || (string)($plan['slug'] ?? '') === 'legacy') return new WP_Error('fieldflow_billing_plan','Seleciona um plano comercial válido.');
        $billingUserId = self::resolveBillingUser($clientId, $billingUserId, $actorId);
        if ($billingUserId <= 0 || !get_userdata($billingUserId)) return new WP_Error('fieldflow_billing_user','Seleciona o utilizador WordPress que ficará associado à subscrição.');

        $externalId = (string)($existing['external_subscription_id'] ?? '');
        $isStripe = $externalId !== '' && strpos($externalId, 'manual_sub_') !== 0 && strpos($externalId, 'sandbox_sub_') !== 0;
        if ($isStripe) {
            return self::changeStripeSubscription($existing, $planId, $interval, sanitize_key((string)($input['proration_behavior'] ?? 'create_prorations')), $actorId, $billingUserId);
        }

        $status = sanitize_key((string)($input['status'] ?? ($existing['status'] ?? 'active')));
        if (!in_array($status,['trial','active','past_due','suspended','cancelled','expired'],true)) $status='active';
        $start = self::inputTimestamp($input['current_period_start'] ?? ($existing['current_period_start'] ?? '')) ?: time();
        $end = self::inputTimestamp($input['current_period_end'] ?? ($existing['current_period_end'] ?? ''));
        if ($end <= 0) $end = $interval === 'year' ? strtotime('+1 year',$start) : strtotime('+1 month',$start);
        if ($end <= $start) return new WP_Error('fieldflow_billing_period','O fim do período tem de ser posterior ao início.');
        $trialEnd = self::inputTimestamp($input['trial_end'] ?? ($existing['trial_end'] ?? ''));
        $grace = self::inputTimestamp($input['grace_until'] ?? ($existing['grace_until'] ?? ''));
        if (!$existing) $externalId = 'manual_sub_'.$clientId.'_'.wp_generate_password(12,false,false);
        $before = $existing;
        $downgradeImpact = class_exists(ProductionHardening::class) ? ProductionHardening::downgradeImpact($clientId, $planId) : [];
        $id = BillingRepository::upsertSubscription($clientId,[
            'billing_user_id'=>$billingUserId,
            'external_customer_id'=>(string)($existing['external_customer_id'] ?? ''),
            'external_subscription_id'=>$externalId,
            'plan_id'=>$planId,
            'billing_interval'=>$interval,
            'status'=>$status,
            'current_period_start'=>$start,
            'current_period_end'=>$end,
            'trial_end'=>$trialEnd ?: null,
            'cancel_at_period_end'=>!empty($input['cancel_at_period_end']),
            'grace_until'=>$grace ?: null,
            'latest_invoice_id'=>(string)($existing['latest_invoice_id'] ?? ''),
            'meta'=>array_merge((array)json_decode((string)($existing['meta_json'] ?? '{}'),true),['source'=>'manual','updated_by'=>$actorId,'billing_user_id'=>$billingUserId,'downgrade_over_limit'=>$downgradeImpact,'downgrade_data_preserved'=>true]),
        ],$environment);
        self::syncSaas($clientId,$planId,$status,$end,$status==='trial'?$trialEnd:($status==='past_due'?$grace:null),'Gestão manual de subscrição.');RoleMappingService::syncClient($clientId,$environment,'Gestão manual de subscrição.');if($previousBillingUserId>0&&$previousBillingUserId!==$billingUserId){RoleMappingService::detachUserFromClient($clientId,$previousBillingUserId);RoleMappingService::releaseUser($previousBillingUserId,'Utilizador alterado na gestão manual.');}
        self::audit($before?'billing.subscription_updated':'billing.subscription_created','billing_subscription',$id,['client_id'=>$clientId,'plan_id'=>$planId,'interval'=>$interval,'status'=>$status,'environment'=>$environment,'manual'=>true,'billing_user_id'=>$billingUserId,'downgrade_impact'=>$downgradeImpact]);
        return self::account($clientId);
    }

    private static function changeStripeSubscription(array $existing, int $planId, string $interval, string $proration, int $actorId = 0, int $billingUserId = 0) {
        $environment = (string)$existing['environment'];
        $price = BillingRepository::priceForPlan($planId,$interval,$environment);
        if (!$price || empty($price['external_price_id'])) return new WP_Error('fieldflow_billing_price_mapping','Falta configurar o Price ID para o plano e periodicidade selecionados.');
        $stripe = new StripeClient(Settings::secretKey($environment));
        $current = $stripe->retrieveSubscription((string)$existing['external_subscription_id']);
        if (is_wp_error($current)) return $current;
        $itemId = (string)($current['items']['data'][0]['id'] ?? '');
        if ($itemId === '') return new WP_Error('fieldflow_billing_subscription_item','A subscrição Stripe não contém um item editável.');
        if (!in_array($proration,['create_prorations','none','always_invoice'],true)) $proration='create_prorations';
        $downgradeImpact = class_exists(ProductionHardening::class) ? ProductionHardening::downgradeImpact((int)$existing['client_id'], $planId) : [];
        $updated = $stripe->updateSubscription((string)$existing['external_subscription_id'],[
            'items'=>[['id'=>$itemId,'price'=>(string)$price['external_price_id']]],
            'proration_behavior'=>$proration,
            'metadata'=>['client_id'=>(string)$existing['client_id'],'plan_id'=>(string)$planId,'billing_interval'=>$interval,'wp_user_id'=>(string)($billingUserId?:($existing['billing_user_id']??0))],
            'expand'=>['latest_invoice','items.data.price'],
        ],'fieldflow_subscription_change_'.(int)$existing['id'].'_'.$planId.'_'.$interval.'_'.time());
        if (is_wp_error($updated)) return $updated;
        self::$eventEnvironment=$environment;
        self::syncSubscriptionObject($updated);
        self::audit('billing.subscription_changed','billing_subscription',(int)$existing['id'],['client_id'=>(int)$existing['client_id'],'plan_id'=>$planId,'interval'=>$interval,'environment'=>$environment,'proration_behavior'=>$proration,'actor_user_id'=>$actorId,'billing_user_id'=>$billingUserId,'downgrade_impact'=>$downgradeImpact]);
        return self::account((int)$existing['client_id']);
    }

    private static function inputTimestamp($value): int {
        if ($value === null || $value === '') return 0;
        if (is_numeric($value)) return (int)$value;
        $value = trim((string)$value);
        if ($value === '') return 0;
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        foreach (['Y-m-d\\TH:i','Y-m-d H:i:s','Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format,$value,$timezone);
            if ($date instanceof \DateTimeImmutable) return $date->getTimestamp();
        }
        $ts = strtotime($value);
        return $ts ?: 0;
    }

    public static function maintenance():array{
        global$wpdb;if(!BillingRepository::tablesReady())return['suspended'=>0];$rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM ".BillingRepository::subscriptionsTable()." WHERE status='past_due' AND grace_until IS NOT NULL AND grace_until<=%s",current_time('mysql')),ARRAY_A)?:[];$count=0;foreach($rows as$row){$clientId=(int)$row['client_id'];$planId=(int)$row['plan_id'];BillingRepository::upsertSubscription($clientId,array_merge($row,['status'=>'suspended']),(string)$row['environment']);self::syncSaas($clientId,$planId,'suspended',null,null,'Período de tolerância terminado.');RoleMappingService::syncClient($clientId,(string)$row['environment'],'Período de tolerância terminado.');self::audit('billing.grace_expired','billing_subscription',(int)$row['id'],['client_id'=>$clientId]);$count++;}return['suspended'=>$count];
    }

    public static function syncClientFromStripe(int$clientId){self::$eventEnvironment=Settings::mode();$local=BillingRepository::subscriptionByClient($clientId,self::$eventEnvironment);if(!$local||empty($local['external_subscription_id'])||strpos((string)$local['external_subscription_id'],'sandbox_sub_')===0)return new WP_Error('fieldflow_billing_no_external','Este cliente ainda não tem uma subscrição Stripe associada neste ambiente.');$stripe=new StripeClient(Settings::secretKey(self::$eventEnvironment));$object=$stripe->retrieveSubscription((string)$local['external_subscription_id']);if(is_wp_error($object))return$object;self::syncSubscriptionObject($object);return self::account($clientId);}



    private static function resolveBillingUser(int $clientId, int $requestedUserId, int $actorId): int {
        if ($requestedUserId > 0) return $requestedUserId;
        if ($actorId > 0 && !user_can($actorId, 'routespro_manage')) return $actorId;
        $associated = Permissions::get_associated_user_ids($clientId, 0);
        return count($associated) === 1 ? (int)$associated[0] : 0;
    }

    private static function eventSummary(array$event):string{$object=(array)($event['data']['object']??[]);return wp_json_encode(['id'=>(string)($event['id']??''),'type'=>(string)($event['type']??''),'livemode'=>!empty($event['livemode']),'created'=>(int)($event['created']??0),'object'=>['id'=>(string)($object['id']??''),'object'=>(string)($object['object']??''),'status'=>(string)($object['status']??''),'customer'=>self::objectId($object['customer']??''),'subscription'=>self::invoiceSubscriptionId($object)?:self::objectId($object['subscription']??''),'currency'=>(string)($object['currency']??''),'total'=>(int)($object['total']??0),'amount_paid'=>(int)($object['amount_paid']??0),'metadata'=>(array)($object['metadata']??[])]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}

    private static function objectId($value):string{if(is_array($value))return sanitize_text_field((string)($value['id']??''));return sanitize_text_field((string)$value);}
    private static function subscriptionPeriod(array$object,string$field):int{$values=[];if(!empty($object[$field])&&is_numeric($object[$field]))$values[]=(int)$object[$field];foreach((array)($object['items']['data']??[])as$item)if(!empty($item[$field])&&is_numeric($item[$field]))$values[]=(int)$item[$field];if(!$values)return 0;return$field==='current_period_start'?min($values):max($values);}
    private static function invoiceSubscriptionId(array$object):string{$legacy=self::objectId($object['subscription']??'');if($legacy!=='')return$legacy;$parent=(array)($object['parent']??[]);if((string)($parent['type']??'')==='subscription_details')return self::objectId($parent['subscription_details']['subscription']??'');return'';}
    private static function invoiceTaxMinor(array$object):int{foreach(['total_tax_amounts','total_taxes']as$key){$rows=(array)($object[$key]??[]);if(!$rows)continue;$sum=0;foreach($rows as$tax)$sum+=(int)($tax['amount']??0);return$sum;}return 0;}

    private static function syncSaas(int$clientId,int$planId,string$status,$renewsAt=null,$graceUntil=null,string$reason=''):void{if(method_exists(PlanRepository::class,'syncBilling')){PlanRepository::syncBilling($clientId,$planId,$status,$renewsAt,$graceUntil,$reason);return;}PlanRepository::assign($clientId,$planId,['status'=>$status,'renews_at'=>$renewsAt?wp_date('Y-m-d H:i:s',(int)$renewsAt):'','status_reason'=>$reason],0,true);}
    private static function mapStripeStatus(string$status):string{return['trialing'=>'trial','active'=>'active','past_due'=>'past_due','unpaid'=>'past_due','incomplete'=>'past_due','incomplete_expired'=>'expired','canceled'=>'cancelled','paused'=>'suspended'][$status]??'active';}
    public static function commercialPrice(int $planId, string $interval, ?string $environment = null): array {
        $interval = $interval === 'year' ? 'year' : 'month';
        $environment = $environment ?: Settings::mode();
        $plan = PlanRepository::plan($planId);
        $mapping = BillingRepository::priceForPlan($planId, $interval, $environment);
        $planAmount = $plan ? ($interval === 'year' ? (float)($plan['annual_price'] ?? 0) : (float)($plan['monthly_price'] ?? 0)) : 0.0;
        $catalogAmount = $mapping && (int)($mapping['amount_minor'] ?? 0) > 0 ? ((int)$mapping['amount_minor'] / 100) : 0.0;
        return [
            'amount' => $catalogAmount > 0 ? $catalogAmount : $planAmount,
            'currency' => (string)($mapping['currency'] ?? ($plan['currency'] ?? 'EUR')),
            'mapping' => $mapping,
        ];
    }

    public static function checkoutAvailable(int $planId, string $interval): bool {
        if (!Settings::isEnabled()) return false;
        $plan = PlanRepository::plan($planId);
        if (!$plan || empty($plan['is_active']) || (string)($plan['slug'] ?? '') === 'legacy') return false;
        $interval = $interval === 'year' ? 'year' : 'month';
        $commercialPrice = self::commercialPrice($planId, $interval);
        if ((float)$commercialPrice['amount'] <= 0) return false;
        $settings = Settings::get();
        if (Settings::isSandbox() && (string)($settings['sandbox_driver'] ?? 'local') === 'local') return true;
        $environment = Settings::mode();
        $price = $commercialPrice['mapping'];
        return Settings::secretKey($environment) !== '' && $price && !empty($price['is_active']) && !empty($price['external_price_id']);
    }
    private static function notifyFailure(int$clientId,int$graceUntil,string$mode,string$reference=''):void{if(class_exists(ProductionHardening::class)&&!empty(Settings::get()['transactional_emails_enabled']))return;$noticeKey='fieldflow_billing_notice_'.md5($mode.'|'.$clientId.'|'.$reference);if(get_transient($noticeKey))return;global$wpdb;$client=$wpdb->get_row($wpdb->prepare('SELECT name,email FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d',$clientId),ARRAY_A);$to=array_filter([sanitize_email((string)($client['email']??'')),sanitize_email((string)Settings::get()['notification_email'])]);if(!$to)return;$subject='FieldFlow: pagamento pendente';$body='O pagamento da subscrição de '.((string)($client['name']??('cliente #'.$clientId))).' falhou em modo '.$mode.'. O acesso permanece disponível até '.wp_date('d/m/Y H:i',$graceUntil).'. Depois dessa data, os módulos pagos passam para modo restrito, sem eliminação de dados.';if(\RoutesPro\Services\MailDispatchService::send(array_values(array_unique($to)),$subject,$body,[],[],['source'=>'billing_failure_notice','client_id'=>$clientId,'mode'=>$mode]))set_transient($noticeKey,1,12*HOUR_IN_SECONDS);}
    private static function audit(string$action,string$entityType,int$entityId,array$data):void{if(class_exists(AuditLog::class))AuditLog::record($action,$entityType,$entityId,null,$data,['client_id'=>(int)($data['client_id']??0),'actor_user_id'=>get_current_user_id()]);if(class_exists(EventBus::class))EventBus::emit($action,$data,['entity_type'=>$entityType,'entity_id'=>$entityId,'client_id'=>(int)($data['client_id']??0)],'billing');}
}
