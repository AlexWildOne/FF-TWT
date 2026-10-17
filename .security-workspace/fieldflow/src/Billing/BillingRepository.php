<?php
namespace RoutesPro\Billing;

if (!defined('ABSPATH')) exit;

class BillingRepository {
    public static function pricesTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_billing_prices'; }
    public static function customersTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_billing_customers'; }
    public static function subscriptionsTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_billing_subscriptions'; }
    public static function invoicesTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_billing_invoices'; }
    public static function eventsTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_billing_events'; }
    public static function sessionsTable(): string { global $wpdb; return $wpdb->prefix . 'routespro_billing_checkout_sessions'; }

    public static function tablesReady(): bool {
        global $wpdb;
        foreach ([self::pricesTable(),self::customersTable(),self::subscriptionsTable(),self::invoicesTable(),self::eventsTable(),self::sessionsTable()] as $table) {
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return false;
        }
        return true;
    }

    public static function savePrice(int $planId, string $interval, array $input): int {
        global $wpdb;
        $interval = in_array($interval, ['month','year'], true) ? $interval : 'month';
        $environment = in_array(($input['environment'] ?? Settings::mode()), ['sandbox','live'], true) ? (string)($input['environment'] ?? Settings::mode()) : 'sandbox';
        $data = [
            'plan_id'=>$planId,'provider'=>'stripe','environment'=>$environment,'billing_interval'=>$interval,
            'external_product_id'=>sanitize_text_field((string)($input['external_product_id'] ?? '')),
            'external_price_id'=>sanitize_text_field((string)($input['external_price_id'] ?? '')),
            'amount_minor'=>absint($input['amount_minor'] ?? 0),
            'currency'=>substr(strtoupper(preg_replace('/[^A-Z]/','',(string)($input['currency'] ?? 'EUR'))),0,3) ?: 'EUR',
            'is_active'=>!empty($input['is_active']) ? 1 : 0,
            'updated_at'=>current_time('mysql'),
        ];
        $id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::pricesTable().' WHERE plan_id=%d AND provider=%s AND environment=%s AND billing_interval=%s',$planId,'stripe',$environment,$interval));
        if($id){$wpdb->update(self::pricesTable(),$data,['id'=>$id]);return$id;}
        $data['created_at']=current_time('mysql');$wpdb->insert(self::pricesTable(),$data);return(int)$wpdb->insert_id;
    }

    public static function priceForPlan(int $planId, string $interval, ?string $environment = null): ?array {
        global $wpdb;$environment=$environment?:Settings::mode();$interval=$interval==='year'?'year':'month';
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::pricesTable().' WHERE plan_id=%d AND provider=%s AND environment=%s AND billing_interval=%s AND is_active=1 LIMIT 1',$planId,'stripe',$environment,$interval),ARRAY_A);
        return $row?:null;
    }

    public static function priceById(int $id): ?array {
        global $wpdb;
        if ($id <= 0) return null;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT bp.*,p.name plan_name,p.slug plan_slug FROM '.self::pricesTable().' bp LEFT JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=bp.plan_id WHERE bp.id=%d LIMIT 1',
            $id
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function duplicateExternalPrice(string $externalPriceId, string $environment, int $excludeId = 0): ?array {
        global $wpdb;
        $externalPriceId = sanitize_text_field($externalPriceId);
        if ($externalPriceId === '') return null;
        $sql = 'SELECT * FROM '.self::pricesTable().' WHERE external_price_id=%s AND environment=%s';
        $args = [$externalPriceId, $environment];
        if ($excludeId > 0) { $sql .= ' AND id<>%d'; $args[] = $excludeId; }
        $sql .= ' LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, ...$args), ARRAY_A);
        return $row ?: null;
    }

    public static function dependentSubscriptions(int $planId, string $interval, string $environment): int {
        global $wpdb;
        $interval = $interval === 'year' ? 'year' : 'month';
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM ".self::subscriptionsTable()." WHERE plan_id=%d AND billing_interval=%s AND environment=%s AND status IN ('active','trial','trialing','past_due','incomplete','unpaid')",
            $planId,
            $interval,
            $environment
        ));
    }

    public static function deletePrice(int $id): bool {
        global $wpdb;
        if ($id <= 0) return false;
        return $wpdb->delete(self::pricesTable(), ['id'=>$id], ['%d']) !== false;
    }

    public static function prices(?string $environment = null): array {
        global $wpdb;$environment=$environment?:Settings::mode();
        return $wpdb->get_results($wpdb->prepare('SELECT bp.*,p.name plan_name,p.slug plan_slug FROM '.self::pricesTable().' bp INNER JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=bp.plan_id WHERE bp.environment=%s ORDER BY p.monthly_price ASC,bp.billing_interval ASC',$environment),ARRAY_A)?:[];
    }

    public static function customer(int $clientId, ?string $environment = null): ?array {
        global $wpdb;$environment=$environment?:Settings::mode();$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::customersTable().' WHERE client_id=%d AND provider=%s AND environment=%s LIMIT 1',$clientId,'stripe',$environment),ARRAY_A);return$row?:null;
    }

    public static function upsertCustomer(int $clientId, array $data, ?string $environment = null): int {
        global $wpdb;$environment=$environment?:Settings::mode();$existing=self::customer($clientId,$environment);
        $row=['client_id'=>$clientId,'provider'=>'stripe','environment'=>$environment,'external_customer_id'=>sanitize_text_field((string)($data['external_customer_id']??'')),'email'=>sanitize_email((string)($data['email']??'')),'billing_name'=>sanitize_text_field((string)($data['billing_name']??'')),'tax_id'=>sanitize_text_field((string)($data['tax_id']??'')),'country'=>substr(strtoupper(sanitize_text_field((string)($data['country']??'PT'))),0,2),'status'=>sanitize_key((string)($data['status']??'active')),'updated_at'=>current_time('mysql')];
        if($existing){$wpdb->update(self::customersTable(),$row,['id'=>(int)$existing['id']]);return(int)$existing['id'];}$row['created_at']=current_time('mysql');$wpdb->insert(self::customersTable(),$row);return(int)$wpdb->insert_id;
    }

    public static function subscriptionById(int $id): ?array {
        global $wpdb;
        if ($id <= 0) return null;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT bs.*,c.name client_name,p.name plan_name,p.slug plan_slug,p.monthly_price,p.annual_price,p.currency FROM '.self::subscriptionsTable().' bs LEFT JOIN '.$wpdb->prefix.'routespro_clients c ON c.id=bs.client_id LEFT JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=bs.plan_id WHERE bs.id=%d LIMIT 1',
            $id
        ), ARRAY_A);
        return $row ?: null;
    }

    public static function subscriptionByClient(int $clientId, ?string $environment = null): ?array {
        global $wpdb;$environment=$environment?:Settings::mode();$row=$wpdb->get_row($wpdb->prepare('SELECT bs.*,p.name plan_name,p.slug plan_slug,p.monthly_price,p.annual_price,p.currency FROM '.self::subscriptionsTable().' bs LEFT JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=bs.plan_id WHERE bs.client_id=%d AND bs.provider=%s AND bs.environment=%s ORDER BY bs.id DESC LIMIT 1',$clientId,'stripe',$environment),ARRAY_A);return$row?:null;
    }

    public static function upsertSubscription(int $clientId, array $data, ?string $environment = null): int {
        global $wpdb;$environment=$environment?:Settings::mode();$existing=self::subscriptionByClient($clientId,$environment);
        $row=['client_id'=>$clientId,'billing_user_id'=>absint($data['billing_user_id']??($existing['billing_user_id']??0)),'provider'=>'stripe','environment'=>$environment,'external_customer_id'=>sanitize_text_field((string)($data['external_customer_id']??'')),'external_subscription_id'=>sanitize_text_field((string)($data['external_subscription_id']??'')),'plan_id'=>absint($data['plan_id']??0),'billing_interval'=>in_array(($data['billing_interval']??'month'),['month','year'],true)?(string)$data['billing_interval']:'month','status'=>sanitize_key((string)($data['status']??'active')),'current_period_start'=>self::date($data['current_period_start']??null),'current_period_end'=>self::date($data['current_period_end']??null),'trial_end'=>self::date($data['trial_end']??null),'cancel_at_period_end'=>!empty($data['cancel_at_period_end'])?1:0,'grace_until'=>self::date($data['grace_until']??null),'latest_invoice_id'=>sanitize_text_field((string)($data['latest_invoice_id']??'')),'meta_json'=>wp_json_encode((array)($data['meta']??[])),'updated_at'=>current_time('mysql')];
        if($existing){$wpdb->update(self::subscriptionsTable(),$row,['id'=>(int)$existing['id']]);return(int)$existing['id'];}$row['created_at']=current_time('mysql');$wpdb->insert(self::subscriptionsTable(),$row);return(int)$wpdb->insert_id;
    }

    public static function upsertInvoice(array $data): int {
        global $wpdb;$external=sanitize_text_field((string)($data['external_invoice_id']??''));if($external==='')return 0;$environment=in_array(($data['environment']??Settings::mode()),['sandbox','live'],true)?(string)($data['environment']??Settings::mode()):Settings::mode();$id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::invoicesTable().' WHERE external_invoice_id=%s AND environment=%s',$external,$environment));
        $row=['external_invoice_id'=>$external,'environment'=>$environment,'client_id'=>absint($data['client_id']??0),'external_subscription_id'=>sanitize_text_field((string)($data['external_subscription_id']??'')),'invoice_number'=>sanitize_text_field((string)($data['invoice_number']??'')),'currency'=>substr(strtoupper(sanitize_text_field((string)($data['currency']??'EUR'))),0,3),'subtotal_minor'=>(int)($data['subtotal_minor']??0),'tax_minor'=>(int)($data['tax_minor']??0),'total_minor'=>(int)($data['total_minor']??0),'amount_paid_minor'=>(int)($data['amount_paid_minor']??0),'status'=>sanitize_key((string)($data['status']??'open')),'hosted_invoice_url'=>esc_url_raw((string)($data['hosted_invoice_url']??'')),'invoice_pdf'=>esc_url_raw((string)($data['invoice_pdf']??'')),'due_at'=>self::date($data['due_at']??null),'paid_at'=>self::date($data['paid_at']??null),'period_start'=>self::date($data['period_start']??null),'period_end'=>self::date($data['period_end']??null),'updated_at'=>current_time('mysql')];
        if($id){$wpdb->update(self::invoicesTable(),$row,['id'=>$id]);return$id;}$row['created_at']=current_time('mysql');$wpdb->insert(self::invoicesTable(),$row);return(int)$wpdb->insert_id;
    }

    public static function beginEvent(string $providerEventId,string $eventType,string $payload,?string $environment=null,string $storedPayload=''): int {
        global $wpdb;
        $providerEventId=sanitize_text_field($providerEventId);$environment=$environment?:Settings::mode();$storedPayload=$storedPayload!==''?$storedPayload:$payload;
        $existing=$wpdb->get_row($wpdb->prepare('SELECT id,status,attempts FROM '.self::eventsTable().' WHERE provider=%s AND provider_event_id=%s AND environment=%s','stripe',$providerEventId,$environment),ARRAY_A);
        if($existing){
            $id=(int)$existing['id'];
            if((string)$existing['status']!=='failed')return-$id;
            $wpdb->update(self::eventsTable(),['status'=>'processing','event_type'=>sanitize_text_field($eventType),'payload_hash'=>hash('sha256',$payload),'payload_json'=>$storedPayload,'attempts'=>max(1,(int)$existing['attempts'])+1,'error_message'=>null,'processed_at'=>null,'updated_at'=>current_time('mysql')],['id'=>$id]);
            return$id;
        }
        $inserted=$wpdb->insert(self::eventsTable(),['provider'=>'stripe','environment'=>$environment,'provider_event_id'=>$providerEventId,'event_type'=>sanitize_text_field($eventType),'status'=>'processing','payload_hash'=>hash('sha256',$payload),'payload_json'=>$storedPayload,'attempts'=>1,'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);
        if($inserted)return(int)$wpdb->insert_id;
        $duplicate=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::eventsTable().' WHERE provider=%s AND provider_event_id=%s AND environment=%s','stripe',$providerEventId,$environment));
        return$duplicate>0?-$duplicate:0;
    }

    public static function finishEvent(int $id,string $status,string $error=''):void{global$wpdb;if($id<=0)return;$wpdb->update(self::eventsTable(),['status'=>sanitize_key($status),'error_message'=>sanitize_textarea_field($error),'processed_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')],['id'=>$id]);}

    public static function createCheckoutSession(array $data): int {
        global $wpdb;$row=['session_token_hash'=>hash('sha256',(string)$data['token']),'provider'=>'stripe','environment'=>Settings::mode(),'external_session_id'=>sanitize_text_field((string)($data['external_session_id']??'')),'client_id'=>absint($data['client_id']??0),'billing_user_id'=>absint($data['billing_user_id']??0),'plan_id'=>absint($data['plan_id']??0),'billing_interval'=>($data['billing_interval']??'month')==='year'?'year':'month','status'=>sanitize_key((string)($data['status']??'created')),'checkout_url'=>esc_url_raw((string)($data['checkout_url']??'')),'expires_at'=>self::date($data['expires_at']??null),'created_by'=>absint($data['created_by']??get_current_user_id()),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')];$wpdb->insert(self::sessionsTable(),$row);return(int)$wpdb->insert_id;
    }

    public static function checkoutByToken(string $token): ?array {global$wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::sessionsTable().' WHERE session_token_hash=%s LIMIT 1',hash('sha256',$token)),ARRAY_A);return$row?:null;}
    public static function updateCheckout(int$id,array$data):void{global$wpdb;if(!$id)return;$data['updated_at']=current_time('mysql');$wpdb->update(self::sessionsTable(),$data,['id'=>$id]);}
    public static function planForPrice(string $externalPriceId, ?string $environment = null): ?array {global$wpdb;$environment=$environment?:Settings::mode();$row=$wpdb->get_row($wpdb->prepare('SELECT bp.*,p.name plan_name,p.slug plan_slug FROM '.self::pricesTable().' bp INNER JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=bp.plan_id WHERE bp.external_price_id=%s AND bp.environment=%s LIMIT 1',$externalPriceId,$environment),ARRAY_A);return$row?:null;}
    public static function invoices(int $limit=200,?string $environment=null):array{global$wpdb;$environment=$environment?:Settings::mode();return$wpdb->get_results($wpdb->prepare('SELECT i.*,c.name client_name FROM '.self::invoicesTable().' i LEFT JOIN '.$wpdb->prefix.'routespro_clients c ON c.id=i.client_id WHERE i.environment=%s ORDER BY i.id DESC LIMIT %d',$environment,max(1,min(500,$limit))),ARRAY_A)?:[];}
    public static function subscriptions(int $limit=200,?string $environment=null):array{global$wpdb;$environment=$environment?:Settings::mode();return$wpdb->get_results($wpdb->prepare('SELECT s.*,c.name client_name,p.name plan_name,p.monthly_price,p.annual_price,p.currency FROM '.self::subscriptionsTable().' s LEFT JOIN '.$wpdb->prefix.'routespro_clients c ON c.id=s.client_id LEFT JOIN '.$wpdb->prefix.'routespro_saas_plans p ON p.id=s.plan_id WHERE s.environment=%s ORDER BY s.id DESC LIMIT %d',$environment,max(1,min(500,$limit))),ARRAY_A)?:[];}

    public static function subscriptionsForUser(int $userId, ?string $environment = null): array {
        global $wpdb;
        if ($userId <= 0) return [];
        $base = "SELECT s.*,c.name client_name,p.name plan_name,p.slug plan_slug,p.monthly_price,p.annual_price,p.currency
                 FROM ".self::subscriptionsTable()." s
                 LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=s.client_id
                 LEFT JOIN {$wpdb->prefix}routespro_saas_plans p ON p.id=s.plan_id
                 WHERE s.billing_user_id=%d";
        $args = [$userId];
        if ($environment !== null && in_array($environment, ['sandbox','live'], true)) {
            $base .= ' AND s.environment=%s';
            $args[] = $environment;
        }
        $base .= " ORDER BY CASE
                    WHEN s.status IN ('active','trial','trialing') THEN 0
                    WHEN s.status='past_due' THEN 1
                    WHEN s.status='suspended' THEN 2
                    WHEN s.status='cancelled' THEN 3
                    WHEN s.status='expired' THEN 4
                    ELSE 5 END ASC,
                    CASE WHEN s.environment='live' THEN 0 ELSE 1 END ASC,
                    COALESCE(p.monthly_price,0) DESC, s.id DESC";
        return $wpdb->get_results($wpdb->prepare($base, ...$args), ARRAY_A) ?: [];
    }

    public static function effectiveSubscriptionsForUser(int $userId, bool $activeOnly = false): array {
        $rows = self::subscriptionsForUser($userId, null);
        $effective = [];
        foreach ($rows as $row) {
            $clientId = absint($row['client_id'] ?? 0);
            if ($clientId <= 0 || isset($effective[$clientId])) continue;
            $status = sanitize_key((string)($row['status'] ?? ''));
            if ($activeOnly && !in_array($status, ['active','trial','trialing','past_due'], true)) continue;
            $effective[$clientId] = $row;
        }
        return array_values($effective);
    }

    public static function activeSubscriptionsForClients(array $clientIds = [], ?string $environment = null): array {
        global $wpdb;
        $environment = in_array((string)$environment, ['sandbox','live'], true) ? (string)$environment : Settings::mode();
        $clientIds = array_values(array_unique(array_filter(array_map('absint', $clientIds))));
        $sql = "SELECT s.*,c.name client_name,p.name plan_name,p.slug plan_slug,p.monthly_price,p.annual_price,p.currency
                FROM ".self::subscriptionsTable()." s
                LEFT JOIN {$wpdb->prefix}routespro_clients c ON c.id=s.client_id
                LEFT JOIN {$wpdb->prefix}routespro_saas_plans p ON p.id=s.plan_id
                WHERE s.environment=%s AND s.status IN ('active','trial','trialing','past_due')";
        $args = [$environment];
        if ($clientIds) {
            $sql .= ' AND s.client_id IN ('.implode(',', array_fill(0, count($clientIds), '%d')).')';
            $args = array_merge($args, $clientIds);
        }
        $sql .= " ORDER BY CASE WHEN s.status IN ('active','trial','trialing') THEN 0 ELSE 1 END ASC, c.name ASC, s.id DESC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
    }

    public static function subscriptionUserIds(?string $environment = null): array {
        global $wpdb;
        $sql = 'SELECT DISTINCT billing_user_id FROM '.self::subscriptionsTable().' WHERE billing_user_id>0';
        $args = [];
        if ($environment !== null && in_array($environment, ['sandbox','live'], true)) {
            $sql .= ' AND environment=%s';
            $args[] = $environment;
        }
        $sql .= ' ORDER BY billing_user_id ASC';
        $values = $args ? $wpdb->get_col($wpdb->prepare($sql, ...$args)) : $wpdb->get_col($sql);
        return array_values(array_unique(array_filter(array_map('absint', (array)$values))));
    }

    public static function hasSubscriptionForUserClient(int $userId, int $clientId): bool {
        global $wpdb;
        if ($userId <= 0 || $clientId <= 0) return false;
        return (int)$wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM '.self::subscriptionsTable().' WHERE billing_user_id=%d AND client_id=%d',
            $userId,
            $clientId
        )) > 0;
    }

    public static function hasActiveSubscriptionForUserClient(int $userId, int $clientId): bool {
        global $wpdb;
        if ($userId <= 0 || $clientId <= 0) return false;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM ".self::subscriptionsTable()." WHERE billing_user_id=%d AND client_id=%d AND status IN ('active','trial','trialing','past_due')",
            $userId,
            $clientId
        )) > 0;
    }

    public static function preferredSubscriptionForUser(int $userId): ?array {
        global $wpdb;
        if ($userId <= 0) return null;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*,p.name plan_name,p.slug plan_slug,p.monthly_price,p.annual_price,p.currency
             FROM ".self::subscriptionsTable()." s
             LEFT JOIN {$wpdb->prefix}routespro_saas_plans p ON p.id=s.plan_id
             WHERE s.billing_user_id=%d
             ORDER BY CASE
                WHEN s.status IN ('active','trial','trialing') THEN 0
                WHEN s.status='past_due' THEN 1
                WHEN s.status='suspended' THEN 2
                WHEN s.status='cancelled' THEN 3
                WHEN s.status='expired' THEN 4
                ELSE 5 END ASC,
                CASE WHEN s.environment='live' THEN 0 ELSE 1 END ASC,
                COALESCE(p.monthly_price,0) DESC, s.id DESC
             LIMIT 1",
            $userId
        ), ARRAY_A);
        return $row ?: null;
    }
    public static function events(int $limit=200,?string $environment=null):array{global$wpdb;$environment=$environment?:Settings::mode();return$wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::eventsTable().' WHERE environment=%s ORDER BY id DESC LIMIT %d',$environment,max(1,min(500,$limit))),ARRAY_A)?:[];}
    public static function metrics(?string $environment=null):array{global$wpdb;$environment=$environment?:Settings::mode();$active=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".self::subscriptionsTable()." WHERE environment=%s AND status IN ('active','trialing','trial')",$environment));$past=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".self::subscriptionsTable()." WHERE environment=%s AND status='past_due'",$environment));$paid=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount_paid_minor),0) FROM ".self::invoicesTable()." WHERE environment=%s AND status='paid'",$environment));$open=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total_minor-amount_paid_minor),0) FROM ".self::invoicesTable()." WHERE environment=%s AND status IN ('open','past_due','uncollectible')",$environment));return['active'=>$active,'past_due'=>$past,'paid_minor'=>$paid,'open_minor'=>$open];}

    private static function date($value): ?string {if($value===null||$value==='')return null;if(is_numeric($value))return wp_date('Y-m-d H:i:s',(int)$value);$ts=strtotime((string)$value);return$ts?wp_date('Y-m-d H:i:s',$ts):null;}
}
