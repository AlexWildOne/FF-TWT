<?php
namespace RoutesPro\Admin;

use RoutesPro\Billing\BillingRepository;
use RoutesPro\Billing\BillingService;
use RoutesPro\Billing\Settings;
use RoutesPro\Billing\RoleMappingService;
use RoutesPro\Billing\SignupService;
use RoutesPro\Billing\ProductionHardening;
use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\SaaS\PlanRepository;

if (!defined('ABSPATH')) exit;

class Billing {
    public const SLUG='routespro-billing';
    private static bool $registered=false;

    public static function registerHooks():void{
        if(self::$registered)return;self::$registered=true;
        add_action('admin_post_fieldflow_billing_save_settings',[self::class,'saveSettings']);
        add_action('admin_post_fieldflow_billing_save_catalog',[self::class,'saveCatalog']);
        add_action('admin_post_fieldflow_billing_save_catalog_price',[self::class,'saveCatalogPrice']);
        add_action('admin_post_fieldflow_billing_delete_catalog_price',[self::class,'deleteCatalogPrice']);
        add_action('admin_post_fieldflow_billing_create_checkout',[self::class,'createCheckout']);
        add_action('admin_post_fieldflow_billing_sync',[self::class,'sync']);
        add_action('admin_post_fieldflow_billing_portal',[self::class,'portal']);
        add_action('admin_post_fieldflow_billing_save_subscription',[self::class,'saveSubscription']);
        add_action('admin_post_fieldflow_billing_create_portal_page',[self::class,'createPortalPage']);
        add_action('admin_post_fieldflow_billing_save_portal_page',[self::class,'savePortalPage']);
        add_action('admin_post_fieldflow_billing_detect_portal_page',[self::class,'detectPortalPage']);
        add_action('admin_post_fieldflow_billing_save_role_mappings',[self::class,'saveRoleMappings']);
        add_action('admin_post_fieldflow_billing_sync_roles',[self::class,'syncRoles']);
        add_action('admin_post_fieldflow_billing_create_signup_page',[self::class,'createSignupPage']);
        add_action('admin_post_fieldflow_billing_save_production',[self::class,'saveProductionSettings']);
        add_action('admin_post_fieldflow_billing_hardening_reconcile',[self::class,'hardeningReconcile']);
        add_action('admin_post_fieldflow_billing_hardening_maintenance',[self::class,'hardeningMaintenance']);
        add_action('admin_post_fieldflow_billing_hardening_test_email',[self::class,'hardeningTestEmail']);
        add_action('admin_post_fieldflow_billing_hardening_recovery',[self::class,'hardeningRecovery']);
    }

    private static function guard(string$nonce):void{if(!current_user_can('routespro_manage'))wp_die('Sem permissões.');check_admin_referer($nonce);}
    private static function redirect(string$tab,string$message,string$type='success'):void{wp_safe_redirect(add_query_arg(['page'=>self::SLUG,'tab'=>$tab,'ff_billing_message'=>$message,'ff_billing_type'=>$type],admin_url('admin.php')));exit;}

    public static function saveSettings():void{self::guard('fieldflow_billing_save_settings');Settings::save($_POST);$tab=sanitize_key((string)($_POST['return_tab']??'settings'));if(!in_array($tab,['settings','onboarding'],true))$tab='settings';self::redirect($tab,'Configuração de faturação guardada.');}
    public static function saveCatalog():void{self::guard('fieldflow_billing_save_catalog');$environment=in_array(($_POST['environment']??Settings::mode()),['sandbox','live'],true)?(string)($_POST['environment']??Settings::mode()):'sandbox';foreach(PlanRepository::plans(false)as$plan){$id=(int)$plan['id'];if((string)$plan['slug']==='legacy')continue;foreach(['month','year']as$interval){$key=$id.'_'.$interval;$amount=$interval==='year'?(int)round((float)$plan['annual_price']*100):(int)round((float)$plan['monthly_price']*100);BillingRepository::savePrice($id,$interval,['environment'=>$environment,'external_product_id'=>$_POST['product_id'][$key]??'','external_price_id'=>$_POST['price_id'][$key]??'','amount_minor'=>$amount,'currency'=>$plan['currency']??'EUR','is_active'=>!empty($_POST['active'][$key])]);}}self::redirect('catalog','Catálogo de preços guardado.');}

    public static function saveCatalogPrice():void{
        self::guard('fieldflow_billing_save_catalog_price');
        $recordId=absint($_POST['price_record_id']??0);
        $before=$recordId?BillingRepository::priceById($recordId):null;
        if($recordId>0&&!$before)self::redirect('catalog','O registo que tentaste editar já não existe.','error');
        $environment=in_array((string)($_POST['environment']??Settings::mode()),['sandbox','live'],true)?(string)($_POST['environment']??Settings::mode()):Settings::mode();
        $planId=absint($_POST['plan_id']??0);
        $interval=sanitize_key((string)($_POST['billing_interval']??'month'));
        if($before){$environment=(string)$before['environment'];$planId=(int)$before['plan_id'];$interval=(string)$before['billing_interval'];}
        $plan=PlanRepository::plan($planId);
        if(!$plan||in_array((string)($plan['slug']??''),['legacy'],true))self::redirect('catalog','Plano inválido para o catálogo de faturação.','error');
        if(!in_array($interval,['month','year'],true))self::redirect('catalog','Periodicidade inválida.','error');
        $productId=sanitize_text_field((string)($_POST['external_product_id']??''));
        $priceId=sanitize_text_field((string)($_POST['external_price_id']??''));
        if($productId!==''&&!preg_match('/^prod_[A-Za-z0-9_]+$/',$productId))self::redirect('catalog','O Product ID deve começar por prod_.','error');
        if($priceId!==''&&!preg_match('/^price_[A-Za-z0-9_]+$/',$priceId))self::redirect('catalog','O Price ID deve começar por price_.','error');
        if($priceId!==''&&BillingRepository::duplicateExternalPrice($priceId,$environment,$recordId))self::redirect('catalog','Este Price ID já está associado a outro registo do catálogo.','error');
        $amountRaw=str_replace(',','.',sanitize_text_field((string)($_POST['amount']??'0')));
        if(!is_numeric($amountRaw)||(float)$amountRaw<0)self::redirect('catalog','Introduz um valor válido igual ou superior a zero.','error');
        $amountMinor=(int)round((float)$amountRaw*100);
        $currency=strtoupper(preg_replace('/[^A-Za-z]/','',sanitize_text_field((string)($_POST['currency']??'EUR'))));
        if(strlen($currency)!==3)self::redirect('catalog','A moeda deve usar um código de três letras, por exemplo EUR.','error');
        $savedId=BillingRepository::savePrice($planId,$interval,[
            'environment'=>$environment,
            'external_product_id'=>$productId,
            'external_price_id'=>$priceId,
            'amount_minor'=>$amountMinor,
            'currency'=>$currency,
            'is_active'=>!empty($_POST['is_active']),
        ]);
        if($savedId<=0)self::redirect('catalog','Não foi possível guardar o registo do catálogo.','error');
        $after=BillingRepository::priceById($savedId);
        AuditLog::record($before?'billing.catalog.updated':'billing.catalog.created','billing_price',$savedId,$before,$after,['actor_user_id'=>get_current_user_id()],['environment'=>$environment,'plan_id'=>$planId,'billing_interval'=>$interval]);
        EventBus::emit($before?'billing.catalog.updated':'billing.catalog.created',['price_record_id'=>$savedId,'plan_id'=>$planId,'billing_interval'=>$interval,'environment'=>$environment,'is_active'=>!empty($_POST['is_active'])],['entity_type'=>'billing_price','entity_id'=>$savedId,'actor_user_id'=>get_current_user_id()]);
        self::redirect('catalog',$before?'Produto e preço atualizados com sucesso.':'Produto e preço adicionados ao catálogo.');
    }

    public static function deleteCatalogPrice():void{
        self::guard('fieldflow_billing_delete_catalog_price');
        $recordId=absint($_POST['price_record_id']??0);
        $before=BillingRepository::priceById($recordId);
        if(!$before)self::redirect('catalog','O registo do catálogo já não existe.','error');
        $dependents=BillingRepository::dependentSubscriptions((int)$before['plan_id'],(string)$before['billing_interval'],(string)$before['environment']);
        if($dependents>0)self::redirect('catalog','Não é possível eliminar este mapeamento porque existem '.$dependents.' subscrições dependentes. Desativa-o em vez de o eliminar.','error');
        if(!BillingRepository::deletePrice($recordId))self::redirect('catalog','Não foi possível eliminar o registo do catálogo.','error');
        AuditLog::record('billing.catalog.deleted','billing_price',$recordId,$before,null,['actor_user_id'=>get_current_user_id()],['environment'=>$before['environment'],'plan_id'=>(int)$before['plan_id'],'billing_interval'=>$before['billing_interval'],'stripe_unchanged'=>true]);
        EventBus::emit('billing.catalog.deleted',['price_record_id'=>$recordId,'plan_id'=>(int)$before['plan_id'],'billing_interval'=>$before['billing_interval'],'environment'=>$before['environment'],'stripe_unchanged'=>true],['entity_type'=>'billing_price','entity_id'=>$recordId,'actor_user_id'=>get_current_user_id()]);
        self::redirect('catalog','Mapeamento eliminado do FieldFlow. O produto e o preço na Stripe não foram apagados.');
    }
    public static function createCheckout():void{self::guard('fieldflow_billing_create_checkout');$result=BillingService::createCheckout(absint($_POST['client_id']??0),absint($_POST['plan_id']??0),sanitize_key((string)($_POST['interval']??'month')),get_current_user_id(),absint($_POST['billing_user_id']??0));if(is_wp_error($result))self::redirect('subscriptions',$result->get_error_message(),'error');wp_safe_redirect((string)$result['url']);exit;}
    public static function portal():void{self::guard('fieldflow_billing_portal');$result=BillingService::createPortalSession(absint($_POST['client_id']??0),get_current_user_id());if(is_wp_error($result))self::redirect('subscriptions',$result->get_error_message(),'error');wp_safe_redirect((string)$result['url']);exit;}
    public static function sync():void{self::guard('fieldflow_billing_sync');$result=BillingService::syncClientFromStripe(absint($_POST['client_id']??0));if(is_wp_error($result))self::redirect('subscriptions',$result->get_error_message(),'error');self::redirect('subscriptions','Subscrição sincronizada com a Stripe.');}

    public static function saveSubscription():void{
        self::guard('fieldflow_billing_save_subscription');
        $result=BillingService::saveSubscription($_POST,get_current_user_id());
        if(is_wp_error($result))self::redirect('subscriptions',$result->get_error_message(),'error');
        self::redirect('subscriptions',absint($_POST['subscription_id']??0)?'Subscrição atualizada com sucesso.':'Subscrição manual criada com sucesso.');
    }


    public static function saveRoleMappings():void{
        self::guard('fieldflow_billing_save_role_mappings');
        $before=RoleMappingService::get();
        $after=RoleMappingService::save($_POST);
        AuditLog::record('billing.role_mappings.updated','billing_role_mapping',0,$before,$after,['actor_user_id'=>get_current_user_id()],['environment'=>Settings::mode()]);
        EventBus::emit('billing.role_mappings.updated',['environment'=>Settings::mode(),'plan_count'=>count((array)($after['plans']??[]))],['entity_type'=>'billing_role_mapping','entity_id'=>0,'actor_user_id'=>get_current_user_id()]);
        self::redirect('roles','Mapeamento de roles WordPress guardado. Usa “Sincronizar roles agora” para aplicar a matriz às subscrições existentes.');
    }

    public static function syncRoles():void{
        self::guard('fieldflow_billing_sync_roles');
        $result=RoleMappingService::syncAll(Settings::mode());
        self::redirect('roles','Roles sincronizadas: '.intval($result['changed']).' alteradas, '.intval($result['skipped']).' sem alteração.');
    }

    public static function saveProductionSettings():void{
        self::guard('fieldflow_billing_save_production');
        $input=array_merge(Settings::get(),$_POST);
        foreach(['transactional_emails_enabled','checkout_recovery_enabled','renewal_emails_enabled','legal_consent_required']as$field)$input[$field]=!empty($_POST[$field])?1:0;
        Settings::save($input);
        self::redirect('production','Configuração de produção guardada.');
    }

    public static function hardeningReconcile():void{
        self::guard('fieldflow_billing_hardening_reconcile');
        $mode=sanitize_key((string)($_POST['mode']??'preview'))==='apply'?'apply':'preview';
        $result=ProductionHardening::reconcile($mode,Settings::mode());
        set_transient('fieldflow_billing_reconcile_'.get_current_user_id(),$result,10*MINUTE_IN_SECONDS);
        $summary=(array)($result['summary']??[]);
        self::redirect('production','Reconciliação '.$mode.': '.intval($summary['total_issues']??0).' inconsistências, '.intval($summary['applied']??0).' correções aplicadas, '.intval($summary['errors']??0).' erros.',!empty($summary['errors'])?'error':'success');
    }

    public static function hardeningMaintenance():void{
        self::guard('fieldflow_billing_hardening_maintenance');
        $billing=BillingService::maintenance();
        $signup=SignupService::maintenance();
        $hardening=ProductionHardening::maintenance();
        $sent=intval($hardening['recovery']['sent']??0)+intval($hardening['renewals']['sent']??0);
        self::redirect('production','Manutenção executada: '.intval($billing['suspended']??0).' suspensões, '.intval($signup['expired']??0).' processos expirados e '.$sent.' emails enviados.');
    }

    public static function hardeningTestEmail():void{
        self::guard('fieldflow_billing_hardening_test_email');
        $result=ProductionHardening::sendTestEmail(sanitize_email((string)($_POST['test_email']??'')));
        if(is_wp_error($result))self::redirect('production',$result->get_error_message(),'error');
        self::redirect('production','Email de teste enviado.');
    }

    public static function hardeningRecovery():void{
        self::guard('fieldflow_billing_hardening_recovery');
        $result=ProductionHardening::sendRecoveryIntent(absint($_POST['intent_id']??0),true);
        if(is_wp_error($result))self::redirect('production',$result->get_error_message(),'error');
        self::redirect('production','Ligação de recuperação enviada.');
    }

    public static function savePortalPage():void{
        self::guard('fieldflow_billing_save_portal_page');
        $pageId=absint($_POST['portal_page_id']??0);
        if($pageId<=0||get_post_status($pageId)!=='publish')self::redirect('settings','Seleciona uma página publicada.','error');
        if(!Settings::pageContainsShortcode($pageId,'fieldflow_billing_portal'))self::redirect('settings','A página selecionada não contém o shortcode [fieldflow_billing_portal].','error');
        Settings::setPortalPageId($pageId);
        self::redirect('settings','Página do portal de faturação associada com sucesso.');
    }

    public static function detectPortalPage():void{
        self::guard('fieldflow_billing_detect_portal_page');
        $pageId=Settings::discoverShortcodePage('fieldflow_billing_portal');
        if($pageId<=0)self::redirect('settings','Não foi encontrada nenhuma página publicada com [fieldflow_billing_portal].','error');
        Settings::setPortalPageId($pageId);
        self::redirect('settings','Página do portal detetada e associada automaticamente.');
    }

    public static function createPortalPage():void{
        self::guard('fieldflow_billing_create_portal_page');
        $pageId=Settings::discoverShortcodePage('fieldflow_billing_portal');
        if($pageId<=0){
            $existing=get_page_by_path('planos-e-faturacao');
            if($existing&&Settings::pageContainsShortcode((int)$existing->ID,'fieldflow_billing_portal'))$pageId=(int)$existing->ID;
        }
        if($pageId<=0)$pageId=(int)wp_insert_post(['post_title'=>'Planos e faturação','post_name'=>'planos-e-faturacao','post_content'=>'[fieldflow_billing_portal]','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed']);
        if($pageId<=0||is_wp_error($pageId))self::redirect('settings','Não foi possível criar a página de faturação.','error');
        Settings::setPortalPageId($pageId);
        $url=get_permalink($pageId);
        $settings=Settings::get();
        if(empty($settings['success_url'])||empty($settings['cancel_url'])){
            $settings['success_url']=$settings['success_url']?:$url;
            $settings['cancel_url']=$settings['cancel_url']?:$url;
            Settings::save($settings);
        }
        self::redirect('settings','Página de planos e faturação criada e associada.');
    }

    public static function createSignupPage():void{
        self::guard('fieldflow_billing_create_signup_page');
        $pageId=Settings::signupPageId();
        if($pageId<=0||!get_post_status($pageId)){
            $existing=get_page_by_path('subscrever-fieldflow');
            $pageId=$existing?(int)$existing->ID:(int)wp_insert_post(['post_title'=>'Planos e Subscrições','post_name'=>'subscrever-fieldflow','post_content'=>'[fieldflow_subscription_signup]','post_status'=>'publish','post_type'=>'page','comment_status'=>'closed']);
        }else{
            wp_update_post(['ID'=>$pageId,'post_content'=>'[fieldflow_subscription_signup]']);
        }
        if($pageId<=0||is_wp_error($pageId))self::redirect('onboarding','Não foi possível criar a página pública de subscrições.','error');
        Settings::setSignupPageId($pageId);
        self::redirect('onboarding','Página pública de subscrições criada e associada.');
    }

    public static function render():void{
        if(!current_user_can('routespro_manage'))return;
        $tab=sanitize_key((string)($_GET['tab']??'overview'));
        echo '<div class="wrap"><h1>Faturação e Pagamentos</h1><p>Checkout, sincronização de subscrições, pagamentos falhados e histórico comercial. O modo sandbox não movimenta dinheiro. Usa um cliente de teste, porque a simulação também atualiza o plano SaaS desse cliente.</p>';
        if(!empty($_GET['ff_billing_message'])){$type=sanitize_key((string)($_GET['ff_billing_type']??'success'));echo '<div class="notice notice-'.($type==='error'?'error':'success').' is-dismissible"><p>'.esc_html((string)$_GET['ff_billing_message']).'</p></div>';}
        echo '<nav class="nav-tab-wrapper">';foreach(['overview'=>'Resumo','catalog'=>'Catálogo','subscriptions'=>'Subscrições','roles'=>'Roles WordPress','onboarding'=>'Onboarding','production'=>'Produção','invoices'=>'Faturas','events'=>'Eventos','settings'=>'Configuração']as$key=>$label){$url=add_query_arg(['page'=>self::SLUG,'tab'=>$key],admin_url('admin.php'));echo '<a class="nav-tab '.($tab===$key?'nav-tab-active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>';}echo '</nav>';
        if($tab==='catalog')self::catalog();elseif($tab==='subscriptions')self::subscriptions();elseif($tab==='roles')self::roles();elseif($tab==='onboarding')self::onboarding();elseif($tab==='production')self::production();elseif($tab==='invoices')self::invoices();elseif($tab==='events')self::events();elseif($tab==='settings')self::settings();else self::overview();echo '</div>';
    }

    private static function overview():void{$m=BillingRepository::metrics(Settings::mode());$settings=Settings::publicSummary();$mrr=0.0;foreach(BillingRepository::subscriptions(500,Settings::mode())as$s){if(!in_array($s['status'],['active','trial'],true))continue;$mrr+=(string)$s['billing_interval']==='year'?(float)$s['annual_price']/12:(float)$s['monthly_price'];}echo '<div class="ff-b-grid"><div class="ff-b-card"><small>MRR estimado</small><div class="ff-b-value">'.self::money((int)round($mrr*100)).'</div></div><div class="ff-b-card"><small>Subscrições ativas</small><div class="ff-b-value">'.intval($m['active']).'</div></div><div class="ff-b-card"><small>Pagamentos pendentes</small><div class="ff-b-value">'.intval($m['past_due']).'</div></div><div class="ff-b-card"><small>Total recebido</small><div class="ff-b-value">'.self::money((int)$m['paid_minor']).'</div></div></div><div class="ff-b-card"><h2>Estado do motor comercial</h2><p><strong>Modo:</strong> '.esc_html($settings['mode']).' · <strong>Pagamentos:</strong> '.($settings['enabled']?'ativos':'desativados').' · <strong>Chave:</strong> '.($settings['secret_configured']?'configurada':'em falta').' · <strong>Webhook:</strong> '.($settings['webhook_configured']?'configurado':'em falta').'.</p><p>Valor em aberto: <strong>'.self::money((int)$m['open_minor']).'</strong>. Período de tolerância: <strong>'.intval($settings['grace_days']).' dias</strong>.</p></div>';}

    private static function catalog():void{
        $environment=Settings::mode();
        $existing=[];
        foreach(BillingRepository::prices($environment)as$row)$existing[(int)$row['plan_id'].'_'.$row['billing_interval']]=$row;
        $editId=absint($_GET['edit_price']??0);
        $newPlan=absint($_GET['new_plan']??0);
        $newInterval=in_array((string)($_GET['new_interval']??''),['month','year'],true)?(string)$_GET['new_interval']:'';
        if($editId||($newPlan&&$newInterval))self::catalogEditor($environment,$editId,$newPlan,$newInterval);
        echo '<div class="ff-b-card"><h2>Catálogo '.esc_html($environment).'</h2><p>Os mapeamentos existentes podem ser editados sem serem eliminados. A eliminação remove apenas a associação local no FieldFlow e nunca apaga o Product ou Price na Stripe.</p><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Plano</th><th>Periodicidade</th><th>Valor local</th><th>Product ID</th><th>Price ID</th><th>Estado</th><th>Ações</th></tr></thead><tbody>';
        foreach(PlanRepository::plans(false)as$plan){
            if((string)$plan['slug']==='legacy')continue;
            foreach(['month'=>'Mensal','year'=>'Anual']as$interval=>$label){
                $key=(int)$plan['id'].'_'.$interval;
                $row=$existing[$key]??null;
                $minor=$row?(int)$row['amount_minor']:(int)round((float)($interval==='year'?$plan['annual_price']:$plan['monthly_price'])*100);
                $currency=$row?(string)$row['currency']:(string)($plan['currency']??'EUR');
                echo '<tr><td><strong>'.esc_html($plan['name']).'</strong></td><td>'.esc_html($label).'</td><td>'.esc_html(number_format_i18n($minor/100,2).' '.$currency).'</td><td><code>'.esc_html((string)($row['external_product_id']??'Por configurar')).'</code></td><td><code>'.esc_html((string)($row['external_price_id']??'Por configurar')).'</code></td><td><span class="ff-b-status">'.($row?(!empty($row['is_active'])?'Ativo':'Inativo'):'Não configurado').'</span></td><td><div class="ff-b-actions">';
                if($row){
                    $editUrl=add_query_arg(['page'=>self::SLUG,'tab'=>'catalog','edit_price'=>(int)$row['id']],admin_url('admin.php'));
                    echo '<a class="button button-primary" href="'.esc_url($editUrl).'">Editar</a><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Eliminar apenas este mapeamento do FieldFlow? O Product e o Price continuarão na Stripe.\');">';
                    wp_nonce_field('fieldflow_billing_delete_catalog_price');
                    echo '<input type="hidden" name="action" value="fieldflow_billing_delete_catalog_price"><input type="hidden" name="price_record_id" value="'.intval($row['id']).'"><button class="button button-link-delete">Eliminar</button></form>';
                }else{
                    $newUrl=add_query_arg(['page'=>self::SLUG,'tab'=>'catalog','new_plan'=>(int)$plan['id'],'new_interval'=>$interval],admin_url('admin.php'));
                    echo '<a class="button button-primary" href="'.esc_url($newUrl).'">Configurar</a>';
                }
                echo '</div></td></tr>';
            }
        }
        echo '</tbody></table></div></div>';
    }

    private static function catalogEditor(string$environment,int$editId,int$newPlan,string$newInterval):void{
        $row=$editId?BillingRepository::priceById($editId):null;
        if($editId&&(!$row||(string)$row['environment']!==$environment)){echo '<div class="notice notice-error"><p>O registo selecionado não pertence ao ambiente atual.</p></div>';return;}
        $planId=$row?(int)$row['plan_id']:$newPlan;
        $interval=$row?(string)$row['billing_interval']:$newInterval;
        $plan=PlanRepository::plan($planId);
        if(!$plan||!in_array($interval,['month','year'],true))return;
        $minor=$row?(int)$row['amount_minor']:(int)round((float)($interval==='year'?$plan['annual_price']:$plan['monthly_price'])*100);
        $cancel=add_query_arg(['page'=>self::SLUG,'tab'=>'catalog'],admin_url('admin.php'));
        echo '<div class="ff-b-card ff-b-editor"><h2>'.($row?'Editar produto e preço':'Configurar produto e preço').'</h2><p><strong>'.esc_html($plan['name']).'</strong> · '.($interval==='year'?'Anual':'Mensal').' · ambiente '.esc_html($environment).'</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('fieldflow_billing_save_catalog_price');
        echo '<input type="hidden" name="action" value="fieldflow_billing_save_catalog_price"><input type="hidden" name="price_record_id" value="'.intval($row['id']??0).'"><input type="hidden" name="environment" value="'.esc_attr($environment).'"><input type="hidden" name="plan_id" value="'.intval($planId).'"><input type="hidden" name="billing_interval" value="'.esc_attr($interval).'"><table class="form-table"><tr><th>Product ID</th><td><input class="regular-text" name="external_product_id" value="'.esc_attr((string)($row['external_product_id']??'')).'" placeholder="prod_..."><p class="description">Identificador do produto criado na Stripe.</p></td></tr><tr><th>Price ID</th><td><input class="regular-text" name="external_price_id" value="'.esc_attr((string)($row['external_price_id']??'')).'" placeholder="price_..."><p class="description">Identificador do preço recorrente mensal ou anual.</p></td></tr><tr><th>Valor local</th><td><input type="number" min="0" step="0.01" name="amount" value="'.esc_attr(number_format($minor/100,2,'.','')).'"> <input name="currency" value="'.esc_attr((string)($row['currency']??$plan['currency']??'EUR')).'" maxlength="3" size="4"><p class="description">Valor de referência mostrado pelo FieldFlow. A cobrança real usa sempre o Price ID configurado na Stripe.</p></td></tr><tr><th>Estado</th><td><label><input type="checkbox" name="is_active" value="1" '.checked(!$row||!empty($row['is_active']),true,false).'> Ativo para novos checkouts</label></td></tr></table><p class="ff-b-actions"><button class="button button-primary">Guardar alterações</button><a class="button" href="'.esc_url($cancel).'">Cancelar</a></p></form></div>';
    }

    private static function subscriptions():void{
        global $wpdb;
        $clients=$wpdb->get_results('SELECT id,name,email FROM '.$wpdb->prefix.'routespro_clients ORDER BY name',ARRAY_A)?:[];
        $plans=PlanRepository::plans(true);
        $editId=absint($_GET['edit_subscription']??0);
        $editing=$editId?BillingRepository::subscriptionById($editId):null;
        $selectedUser=(int)($editing['billing_user_id']??0);
        echo '<div class="ff-b-card"><h2>'.($editing?'Editar subscrição':'Criar subscrição manual').'</h2><p>A subscrição fica ligada a um utilizador WordPress. Quando o estado ou o plano mudar, o FieldFlow aplica automaticamente a role configurada no separador <strong>Roles WordPress</strong>.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('fieldflow_billing_save_subscription');
        echo '<input type="hidden" name="action" value="fieldflow_billing_save_subscription"><input type="hidden" name="subscription_id" value="'.intval($editing['id']??0).'"><table class="form-table"><tr><th>Cliente</th><td><select name="client_id" required '.($editing?'disabled':'').'><option value="">Cliente</option>';
        foreach($clients as$c)echo '<option value="'.intval($c['id']).'" '.selected((int)($editing['client_id']??0),(int)$c['id'],false).'>'.esc_html($c['name']).'</option>';
        echo '</select>'.($editing?'<input type="hidden" name="client_id" value="'.intval($editing['client_id']).'">':'').'</td></tr>';
        echo '<tr><th>Utilizador da subscrição</th><td><select name="billing_user_id" required>'.self::userOptions($selectedUser).'</select><p class="description">Este é o utilizador que recebe a role do plano. Roles administrativas e outras roles não geridas pelo FieldFlow são preservadas.</p></td></tr>';
        echo '<tr><th>Plano</th><td><select name="plan_id" required><option value="">Plano</option>';
        foreach($plans as$p)if((string)$p['slug']!=='legacy')echo '<option value="'.intval($p['id']).'" '.selected((int)($editing['plan_id']??0),(int)$p['id'],false).'>'.esc_html($p['name']).'</option>';
        $external=(string)($editing['external_subscription_id']??'');$isStripe=$editing&&$external!==''&&strpos($external,'manual_sub_')!==0&&strpos($external,'sandbox_sub_')!==0;
        echo '</select></td></tr><tr><th>Periodicidade</th><td><select name="billing_interval"><option value="month" '.selected((string)($editing['billing_interval']??'month'),'month',false).'>Mensal</option><option value="year" '.selected((string)($editing['billing_interval']??''),'year',false).'>Anual</option></select><p class="description">Em subscrições Stripe, esta alteração troca o Price ID pelo mapeamento do catálogo.</p></td></tr>';
        if($isStripe){
            echo '<tr><th>Acerto Stripe</th><td><select name="proration_behavior"><option value="create_prorations">Criar acertos proporcionais</option><option value="always_invoice">Faturar o acerto imediatamente</option><option value="none">Sem acerto proporcional</option></select><p class="description">A alteração é enviada diretamente à Stripe e depois sincronizada.</p></td></tr>';
        }else{
            echo '<tr><th>Estado</th><td><select name="status">';
            foreach(['trial'=>'Trial','active'=>'Ativa','past_due'=>'Pagamento pendente','suspended'=>'Suspensa','cancelled'=>'Cancelada','expired'=>'Expirada']as$k=>$v)echo '<option value="'.$k.'" '.selected((string)($editing['status']??'active'),$k,false).'>'.$v.'</option>';
            echo '</select></td></tr><tr><th>Período</th><td><input type="datetime-local" name="current_period_start" value="'.esc_attr(self::datetimeLocal((string)($editing['current_period_start']??''))).'"> até <input type="datetime-local" name="current_period_end" value="'.esc_attr(self::datetimeLocal((string)($editing['current_period_end']??''))).'"><p class="description">Se o fim ficar vazio, será calculado pela periodicidade.</p></td></tr><tr><th>Trial e tolerância</th><td><input type="datetime-local" name="trial_end" value="'.esc_attr(self::datetimeLocal((string)($editing['trial_end']??''))).'"> <input type="datetime-local" name="grace_until" value="'.esc_attr(self::datetimeLocal((string)($editing['grace_until']??''))).'"></td></tr><tr><th>Renovação</th><td><label><input type="checkbox" name="cancel_at_period_end" value="1" '.checked(!empty($editing['cancel_at_period_end']),true,false).'> Cancelar no final do período</label></td></tr>';
        }
        $cancel=add_query_arg(['page'=>self::SLUG,'tab'=>'subscriptions'],admin_url('admin.php'));
        echo '</table><p class="ff-b-actions"><button class="button button-primary">'.($editing?'Guardar alterações':'Criar subscrição').'</button>'.($editing?'<a class="button" href="'.esc_url($cancel).'">Cancelar</a>':'').'</p></form></div>';

        echo '<div class="ff-b-card"><h2>Criar checkout Stripe</h2><p>Seleciona o utilizador que ficará associado ao pagamento antes de abrir o checkout.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ff-b-actions">';
        wp_nonce_field('fieldflow_billing_create_checkout');
        echo '<input type="hidden" name="action" value="fieldflow_billing_create_checkout"><select name="client_id" required><option value="">Cliente</option>';
        foreach($clients as$c)echo '<option value="'.intval($c['id']).'">'.esc_html($c['name']).'</option>';
        echo '</select><select name="billing_user_id" required>'.self::userOptions(0).'</select><select name="plan_id" required><option value="">Plano</option>';
        foreach($plans as$p)if((string)$p['slug']!=='legacy')echo '<option value="'.intval($p['id']).'">'.esc_html($p['name']).'</option>';
        echo '</select><select name="interval"><option value="month">Mensal</option><option value="year">Anual</option></select><button class="button button-primary">Abrir checkout</button></form></div>';

        echo '<div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Cliente</th><th>Utilizador e role</th><th>Plano</th><th>Periodicidade</th><th>Estado</th><th>Período</th><th>Ações</th></tr></thead><tbody>';
        $rows=BillingRepository::subscriptions();
        if(!$rows)echo '<tr><td colspan="7">Ainda não existem subscrições de pagamento.</td></tr>';
        foreach($rows as$r){
            $editUrl=add_query_arg(['page'=>self::SLUG,'tab'=>'subscriptions','edit_subscription'=>(int)$r['id']],admin_url('admin.php'));
            $user=get_userdata((int)($r['billing_user_id']??0));
            $mapped=RoleMappingService::targetRole((int)$r['plan_id'],(string)$r['status']);
            echo '<tr><td><strong>'.esc_html((string)$r['client_name']).'</strong><br><small>#'.intval($r['client_id']).'</small></td><td>'.($user?'<strong>'.esc_html($user->display_name).'</strong><br><small>'.esc_html($user->user_email).'</small>':'<span style="color:#b32d2e">Sem utilizador associado</span>').'<br><code>'.esc_html($mapped?:'sem role mapeada').'</code></td><td>'.esc_html((string)$r['plan_name']).'</td><td>'.((string)$r['billing_interval']==='year'?'Anual':'Mensal').'</td><td><span class="ff-b-status">'.esc_html((string)$r['status']).'</span></td><td>'.esc_html(self::date((string)$r['current_period_end'])).'</td><td><div class="ff-b-actions"><a class="button button-primary" href="'.esc_url($editUrl).'">Editar</a>';
            if(!empty($r['external_subscription_id'])&&strpos((string)$r['external_subscription_id'],'manual_sub_')!==0&&strpos((string)$r['external_subscription_id'],'sandbox_sub_')!==0){
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_sync');echo '<input type="hidden" name="action" value="fieldflow_billing_sync"><input type="hidden" name="client_id" value="'.intval($r['client_id']).'"><button class="button">Sincronizar</button></form><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_portal');echo '<input type="hidden" name="action" value="fieldflow_billing_portal"><input type="hidden" name="client_id" value="'.intval($r['client_id']).'"><button class="button">Portal Stripe</button></form>';
            }else echo '<small>Gestão local</small>';
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function roles():void{
        $settings=RoleMappingService::get();
        $roles=RoleMappingService::availableRoles();
        $plans=PlanRepository::plans(false);
        echo '<div class="ff-b-card"><h2>Mapeamento Plano → Role WordPress</h2><p>Quando uma subscrição é ativada, alterada, fica pendente, é suspensa ou cancelada, o FieldFlow sincroniza apenas as roles presentes nesta matriz. As restantes roles do utilizador são preservadas.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('fieldflow_billing_save_role_mappings');
        echo '<input type="hidden" name="action" value="fieldflow_billing_save_role_mappings"><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Plano</th><th>Ativa ou trial</th><th>Pagamento pendente</th><th>Suspensa, cancelada ou expirada</th></tr></thead><tbody>';
        foreach($plans as$plan){
            $id=(int)$plan['id'];$map=(array)($settings['plans'][$id]??[]);
            echo '<tr><td><strong>'.esc_html($plan['name']).'</strong><br><code>'.esc_html($plan['slug']).'</code></td><td>'.self::roleSelect('plans['.$id.'][active_role]',(string)($map['active_role']??''),$roles).'</td><td>'.self::roleSelect('plans['.$id.'][past_due_role]',(string)($map['past_due_role']??''),$roles,'Herdar role ativa').'</td><td>'.self::roleSelect('plans['.$id.'][inactive_role]',(string)($map['inactive_role']??''),$roles,'Remover role do plano').'</td></tr>';
        }
        echo '</tbody></table></div><p class="description">As roles administrativas não podem ser atribuídas por uma subscrição. As roles já existentes no utilizador e que não estejam nesta matriz são sempre preservadas. Quando alteras o mapeamento, a role anteriormente atribuída pelo FieldFlow é removida na próxima sincronização.</p><p><button class="button button-primary">Guardar mapeamento</button></p></form></div>';
        echo '<div class="ff-b-card"><h2>Sincronizar subscrições existentes</h2><p>Aplica agora a role correta a todas as subscrições do ambiente <strong>'.esc_html(Settings::mode()).'</strong> que já tenham um utilizador associado.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_sync_roles');echo '<input type="hidden" name="action" value="fieldflow_billing_sync_roles"><button class="button">Sincronizar roles agora</button></form></div>';
        echo '<div class="ff-b-card"><h2>Botão no FlutterFlow</h2><p>A página configurada é <a href="'.esc_url(Settings::portalUrl()).'" target="_blank" rel="noopener">'.esc_html(Settings::portalUrl()).'</a>. Na app, não abras este URL diretamente. Primeiro pede uma sessão WebView autenticada:</p><pre style="white-space:pre-wrap">POST '.esc_html(rest_url('fieldflow-mobile/v1/auth/web-session'))."\n".'{"target":"billing","client_id":123,"project_id":0}</pre><p>Guarda <code>$.launch_url</code> e abre esse valor no WebView. Assim o utilizador entra na página de subscrições já autenticado.</p></div>';
    }

    private static function onboarding():void{
        $s=Settings::get();
        $roles=RoleMappingService::availableRoles();
        $forms=[];
        if(post_type_exists('um_form')){
            $posts=get_posts(['post_type'=>'um_form','post_status'=>['publish','draft'],'numberposts'=>200,'orderby'=>'title','order'=>'ASC']);
            foreach((array)$posts as$post){
                $mode=(string)get_post_meta($post->ID,'_um_mode',true);
                if($mode===''||$mode==='register')$forms[(int)$post->ID]=(string)$post->post_title;
            }
        }
        $pageId=Settings::signupPageId();$pageUrl=Settings::signupUrl();$umAvailable=SignupService::ultimateMemberAvailable();
        echo '<div class="ff-b-grid"><div class="ff-b-card"><small>Ultimate Member</small><div class="ff-b-value" style="font-size:22px">'.($umAvailable?'Detetado':'Não detetado').'</div></div><div class="ff-b-card"><small>Formulário</small><div class="ff-b-value" style="font-size:22px">'.(absint($s['um_form_id']??0)?'#'.absint($s['um_form_id']):'Por configurar').'</div></div><div class="ff-b-card"><small>Role provisória</small><div class="ff-b-value" style="font-size:22px">'.esc_html((string)($s['pending_role']?:'Por configurar')).'</div></div><div class="ff-b-card"><small>Página pública</small><div class="ff-b-value" style="font-size:22px">'.($pageId?'Criada':'Em falta').'</div></div></div>';
        echo '<div class="ff-b-card"><h2>Funil público com Ultimate Member</h2><p>O visitante escolhe o plano, cria a conta no Ultimate Member, recebe uma role provisória e segue para o checkout. A role paga só é aplicada após confirmação do pagamento.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('fieldflow_billing_save_settings');
        echo '<input type="hidden" name="action" value="fieldflow_billing_save_settings"><input type="hidden" name="return_tab" value="onboarding"><table class="form-table"><tr><th>Ativar onboarding</th><td><input type="hidden" name="onboarding_enabled" value="0"><label><input type="checkbox" name="onboarding_enabled" value="1" '.checked(!empty($s['onboarding_enabled']),true,false).'> Permitir registos públicos com subscrição</label></td></tr><tr><th>Formulário Ultimate Member</th><td><select name="um_form_id"><option value="">Selecionar formulário de registo</option>';
        foreach($forms as$id=>$title)echo '<option value="'.intval($id).'" '.selected(absint($s['um_form_id']??0),$id,false).'>'.esc_html($title).' (#'.intval($id).')</option>';
        echo '</select><p class="description">Cria ou edita o formulário em Ultimate Member &gt; Forms e atribui-lhe uma role neutra. O FieldFlow aplica a role provisória configurada abaixo.</p></td></tr><tr><th>Role provisória</th><td>'.self::roleSelect('pending_role',(string)($s['pending_role']??''),$roles,'Selecionar role pendente').'<p class="description">Usada entre o registo e a confirmação do pagamento. Não deve ter acesso aos módulos pagos.</p></td></tr><tr><th>Campo empresa</th><td><input name="um_company_meta_key" value="'.esc_attr((string)($s['um_company_meta_key']??'company_name')).'" class="regular-text"><p class="description">Meta key do campo Ultimate Member que contém o nome da empresa.</p></td></tr><tr><th>Campo NIF</th><td><input name="um_tax_meta_key" value="'.esc_attr((string)($s['um_tax_meta_key']??'tax_id')).'" class="regular-text"></td></tr><tr><th>Campo telefone</th><td><input name="um_phone_meta_key" value="'.esc_attr((string)($s['um_phone_meta_key']??'mobile_number')).'" class="regular-text"></td></tr></table><input type="hidden" name="enabled" value="'.(!empty($s['enabled'])?'1':'0').'"><input type="hidden" name="mode" value="'.esc_attr((string)$s['mode']).'"><input type="hidden" name="sandbox_driver" value="'.esc_attr((string)$s['sandbox_driver']).'"><input type="hidden" name="grace_days" value="'.intval($s['grace_days']).'"><input type="hidden" name="notification_email" value="'.esc_attr((string)$s['notification_email']).'"><input type="hidden" name="success_url" value="'.esc_attr((string)$s['success_url']).'"><input type="hidden" name="cancel_url" value="'.esc_attr((string)$s['cancel_url']).'"><input type="hidden" name="allow_promotion_codes" value="'.(!empty($s['allow_promotion_codes'])?'1':'0').'"><input type="hidden" name="automatic_tax" value="'.(!empty($s['automatic_tax'])?'1':'0').'"><input type="hidden" name="billing_address_required" value="'.(!empty($s['billing_address_required'])?'1':'0').'"><input type="hidden" name="tax_id_collection" value="'.(!empty($s['tax_id_collection'])?'1':'0').'"><p><button class="button button-primary">Guardar onboarding</button></p></form></div>';
        echo '<div class="ff-b-card"><h2>Página pública de subscrições</h2><p>URL atual: <a href="'.esc_url($pageUrl).'" target="_blank" rel="noopener">'.esc_html($pageUrl).'</a></p><p>A página usa o shortcode <code>[fieldflow_subscription_signup]</code> e apresenta planos, registo, login e checkout num único percurso.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_create_signup_page');echo '<input type="hidden" name="action" value="fieldflow_billing_create_signup_page"><button class="button button-primary">'.($pageId?'Reparar página pública':'Criar página pública automaticamente').'</button></form></div>';
        echo '<div class="ff-b-card"><h2>Estados recentes</h2><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Data</th><th>Utilizador</th><th>Plano</th><th>Periodicidade</th><th>Estado</th><th>Cliente</th></tr></thead><tbody>';
        $rows=SignupService::recentIntents(100);if(!$rows)echo '<tr><td colspan="6">Ainda não existem processos de subscrição iniciados.</td></tr>';foreach($rows as$row)echo '<tr><td>'.esc_html((string)$row['created_at']).'</td><td>'.esc_html((string)($row['user_name']?:$row['email']?:'Visitante')).'</td><td>'.esc_html((string)$row['plan_name']).'</td><td>'.((string)$row['billing_interval']==='year'?'Anual':'Mensal').'</td><td><span class="ff-b-status">'.esc_html((string)$row['status']).'</span></td><td>#'.intval($row['client_id']).'</td></tr>';echo '</tbody></table></div></div>';
    }

    private static function production():void{
        $s=Settings::get();
        $metrics=ProductionHardening::conversionMetrics(30);
        $checks=ProductionHardening::diagnostics();
        $templates=array_replace_recursive(ProductionHardening::templateDefaults(),(array)($s['email_templates']??[]));
        $lastReconcile=get_transient('fieldflow_billing_reconcile_'.get_current_user_id());
        echo '<div class="ff-b-grid">';
        foreach([
            'Processos iniciados'=>intval($metrics['intents']??0),
            'Checkouts iniciados'=>intval($metrics['checkout_started']??0),
            'Subscrições concluídas'=>intval($metrics['completed']??0),
            'Conversão'=>esc_html(number_format_i18n((float)($metrics['conversion_rate']??0),1)).'%',
            'Emails enviados'=>intval($metrics['emails']['sent']??0),
        ]as$label=>$value)echo '<div class="ff-b-card"><small>'.esc_html($label).' · 30 dias</small><div class="ff-b-value">'.$value.'</div></div>';
        echo '</div>';

        echo '<div class="ff-b-card"><h2>Preparação para produção</h2><p>Este centro valida pagamentos, páginas, webhooks, documentos legais, roles e tabelas antes da abertura comercial.</p><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Verificação</th><th>Estado</th><th>Detalhe</th></tr></thead><tbody>';
        foreach($checks as$check)echo '<tr><td><strong>'.esc_html((string)$check['label']).'</strong></td><td><span class="ff-b-status" style="background:'.(!empty($check['ok'])?'#dcfce7':'#fee2e2').';color:'.(!empty($check['ok'])?'#166534':'#991b1b').'">'.(!empty($check['ok'])?'Pronto':'Atenção').'</span></td><td>'.esc_html((string)$check['detail']).'</td></tr>';
        echo '</tbody></table></div></div>';

        echo '<div class="ff-b-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">';
        echo '<div class="ff-b-card"><h3>Reconciliação</h3><p>Compara Stripe ou sandbox, plano SaaS, utilizador, cliente, roles e checkouts expirados.</p><div class="ff-b-actions"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_hardening_reconcile');echo '<input type="hidden" name="action" value="fieldflow_billing_hardening_reconcile"><input type="hidden" name="mode" value="preview"><button class="button">Pré-visualizar</button></form><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Aplicar apenas as correções classificadas como seguras?\');">';wp_nonce_field('fieldflow_billing_hardening_reconcile');echo '<input type="hidden" name="action" value="fieldflow_billing_hardening_reconcile"><input type="hidden" name="mode" value="apply"><button class="button button-primary">Aplicar correções seguras</button></form></div></div>';
        echo '<div class="ff-b-card"><h3>Manutenção imediata</h3><p>Expira processos antigos, suspende tolerâncias terminadas, recupera checkouts e envia avisos de renovação.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_hardening_maintenance');echo '<input type="hidden" name="action" value="fieldflow_billing_hardening_maintenance"><button class="button button-primary">Executar agora</button></form></div>';
        echo '<div class="ff-b-card"><h3>Email de teste</h3><p>Confirma que o WordPress consegue enviar os templates de faturação.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_hardening_test_email');echo '<input type="hidden" name="action" value="fieldflow_billing_hardening_test_email"><input type="email" name="test_email" class="regular-text" value="'.esc_attr((string)($s['notification_email']??get_option('admin_email'))).'" required><p><button class="button">Enviar teste</button></p></form></div>';
        echo '</div>';

        if(is_array($lastReconcile)){
            $summary=(array)($lastReconcile['summary']??[]);$issues=(array)($lastReconcile['issues']??[]);
            echo '<div class="ff-b-card"><h2>Última reconciliação</h2><p><strong>Modo:</strong> '.esc_html((string)($lastReconcile['mode']??'preview')).' · <strong>Ambiente:</strong> '.esc_html((string)($lastReconcile['environment']??Settings::mode())).' · <strong>Inconsistências:</strong> '.intval($summary['total_issues']??0).' · <strong>Correções aplicadas:</strong> '.intval($summary['applied']??0).'</p><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Tipo</th><th>Gravidade</th><th>Descrição</th><th>Tratamento</th></tr></thead><tbody>';
            if(!$issues)echo '<tr><td colspan="4">Não foram encontradas inconsistências.</td></tr>';
            foreach($issues as$issue)echo '<tr><td><code>'.esc_html((string)($issue['type']??'')).'</code></td><td>'.esc_html((string)($issue['severity']??'')).'</td><td>'.esc_html((string)($issue['message']??'')).'</td><td>'.(!empty($issue['safe_fix'])?'Correção segura disponível':'Revisão manual').'</td></tr>';
            echo '</tbody></table></div></div>';
        }

        echo '<div class="ff-b-card"><h2>Automação, recuperação e consentimentos</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_save_production');echo '<input type="hidden" name="action" value="fieldflow_billing_save_production"><table class="form-table">';
        echo '<tr><th>Emails transacionais</th><td><label><input type="checkbox" name="transactional_emails_enabled" value="1" '.checked(!empty($s['transactional_emails_enabled']),true,false).'> Ativar confirmações, falhas, suspensões e alterações</label><br><label><input type="checkbox" name="checkout_recovery_enabled" value="1" '.checked(!empty($s['checkout_recovery_enabled']),true,false).'> Recuperar checkouts abandonados</label><br><label><input type="checkbox" name="renewal_emails_enabled" value="1" '.checked(!empty($s['renewal_emails_enabled']),true,false).'> Avisar sobre renovações próximas</label></td></tr>';
        echo '<tr><th>Recuperação</th><td>Enviar após <input type="number" min="1" max="168" name="abandoned_checkout_hours" value="'.intval($s['abandoned_checkout_hours']??12).'" style="width:80px"> horas, até <input type="number" min="1" max="5" name="max_recovery_emails" value="'.intval($s['max_recovery_emails']??2).'" style="width:70px"> emails por processo.</td></tr>';
        echo '<tr><th>Renovação</th><td>Avisar <input type="number" min="1" max="30" name="renewal_notice_days" value="'.intval($s['renewal_notice_days']??5).'" style="width:80px"> dias antes.</td></tr>';
        echo '<tr><th>Reply-To</th><td><input type="email" name="billing_reply_to" class="regular-text" value="'.esc_attr((string)($s['billing_reply_to']??'')).'"></td></tr>';
        echo '<tr><th>Consentimentos legais</th><td><label><input type="checkbox" name="legal_consent_required" value="1" '.checked(!empty($s['legal_consent_required']),true,false).'> Exigir aceitação antes de criar a intenção</label></td></tr>';
        echo '<tr><th>Termos e Condições</th><td><input type="url" name="terms_url" class="large-text" value="'.esc_attr((string)($s['terms_url']??'')).'" placeholder="https://..."><p>Versão <input type="text" name="terms_version" value="'.esc_attr((string)($s['terms_version']??'1.0')).'" style="width:100px"></p></td></tr>';
        echo '<tr><th>Política de Privacidade</th><td><input type="url" name="privacy_url" class="large-text" value="'.esc_attr((string)($s['privacy_url']??'')).'" placeholder="https://..."><p>Versão <input type="text" name="privacy_version" value="'.esc_attr((string)($s['privacy_version']??'1.0')).'" style="width:100px"></p></td></tr></table>';
        echo '<h3>Templates de email</h3><p>Variáveis disponíveis: <code>{{name}}</code>, <code>{{client}}</code>, <code>{{plan}}</code>, <code>{{portal_url}}</code>, <code>{{resume_url}}</code>, <code>{{grace_until}}</code>, <code>{{period_end}}</code> e <code>{{site_name}}</code>.</p>';
        foreach($templates as$key=>$template){echo '<details style="margin:10px 0;border:1px solid #dcdcde;border-radius:12px;padding:12px"><summary><strong>'.esc_html((string)($template['label']??$key)).'</strong></summary><p><label>Assunto<br><input class="large-text" name="email_templates['.esc_attr($key).'][subject]" value="'.esc_attr((string)$template['subject']).'"></label></p><p><label>Mensagem<br><textarea class="large-text" rows="7" name="email_templates['.esc_attr($key).'][body]">'.esc_textarea((string)$template['body']).'</textarea></label></p></details>';}
        echo '<p><button class="button button-primary">Guardar produção</button></p></form></div>';

        $intents=SignupService::recentIntents(100);
        echo '<div class="ff-b-card"><h2>Processos recuperáveis</h2><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Data</th><th>Utilizador</th><th>Plano</th><th>Estado</th><th>Cliente</th><th>Ação</th></tr></thead><tbody>';
        $shown=0;foreach($intents as$row){if(in_array((string)$row['status'],['completed','expired','cancelled'],true))continue;$shown++;echo '<tr><td>'.esc_html((string)$row['updated_at']).'</td><td>'.esc_html((string)($row['user_name']?:$row['email']?:'Visitante')).'</td><td>'.esc_html((string)$row['plan_name']).' · '.((string)$row['billing_interval']==='year'?'Anual':'Mensal').'</td><td><span class="ff-b-status">'.esc_html((string)$row['status']).'</span></td><td>#'.intval($row['client_id']).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_hardening_recovery');echo '<input type="hidden" name="action" value="fieldflow_billing_hardening_recovery"><input type="hidden" name="intent_id" value="'.intval($row['id']).'"><button class="button">Reenviar ligação</button></form></td></tr>';}
        if(!$shown)echo '<tr><td colspan="6">Não existem processos pendentes recuperáveis.</td></tr>';echo '</tbody></table></div></div>';

        $emails=ProductionHardening::recentEmailLog(100);
        echo '<div class="ff-b-card"><h2>Histórico de emails</h2><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Data</th><th>Template</th><th>Destinatário</th><th>Assunto</th><th>Estado</th><th>Erro</th></tr></thead><tbody>';
        if(!$emails)echo '<tr><td colspan="6">Ainda não existem emails de faturação registados.</td></tr>';foreach($emails as$row)echo '<tr><td>'.esc_html((string)$row['created_at']).'</td><td><code>'.esc_html((string)$row['template_key']).'</code></td><td>'.esc_html((string)$row['recipient']).'</td><td>'.esc_html((string)$row['subject']).'</td><td>'.esc_html((string)$row['status']).'</td><td>'.esc_html((string)$row['error_message']).'</td></tr>';echo '</tbody></table></div></div>';

        $consents=ProductionHardening::recentConsents(50);
        echo '<div class="ff-b-card"><h2>Consentimentos recentes</h2><div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Data</th><th>Utilizador</th><th>Cliente</th><th>Termos</th><th>Privacidade</th><th>Marketing</th></tr></thead><tbody>';
        if(!$consents)echo '<tr><td colspan="6">Ainda não existem consentimentos registados.</td></tr>';foreach($consents as$row)echo '<tr><td>'.esc_html((string)$row['accepted_at']).'</td><td>'.esc_html((string)($row['display_name']?:$row['user_email']?:'Visitante')).'</td><td>#'.intval($row['client_id']).'</td><td>'.esc_html((string)$row['terms_version']).'</td><td>'.esc_html((string)$row['privacy_version']).'</td><td>'.(!empty($row['marketing_consent'])?'Sim':'Não').'</td></tr>';echo '</tbody></table></div></div>';
    }

    private static function roleSelect(string$name,string$selected,array$roles,string$emptyLabel='Não alterar role'):string{
        $html='<select name="'.esc_attr($name).'"><option value="">'.esc_html($emptyLabel).'</option>';
        foreach($roles as$slug=>$label)$html.='<option value="'.esc_attr($slug).'" '.selected($selected,$slug,false).'>'.esc_html($label).' ('.esc_html($slug).')</option>';
        return$html.'</select>';
    }

    private static function userOptions(int$selected):string{
        $html='<option value="">Selecionar utilizador WordPress</option>';
        $users=get_users(['number'=>500,'orderby'=>'display_name','order'=>'ASC','fields'=>['ID','display_name','user_email']]);
        foreach((array)$users as$user)$html.='<option value="'.intval($user->ID).'" '.selected($selected,(int)$user->ID,false).'>'.esc_html($user->display_name.' · '.$user->user_email).'</option>';
        return$html;
    }

    private static function invoices():void{$rows=BillingRepository::invoices();echo '<div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Fatura</th><th>Cliente</th><th>Estado</th><th>Total</th><th>Pago</th><th>Período</th><th>Documento</th></tr></thead><tbody>';if(!$rows)echo '<tr><td colspan="7">Ainda não existem faturas sincronizadas.</td></tr>';foreach($rows as$r){echo '<tr><td>'.esc_html((string)($r['invoice_number']?:$r['external_invoice_id'])).'</td><td>'.esc_html((string)$r['client_name']).'</td><td>'.esc_html((string)$r['status']).'</td><td>'.self::money((int)$r['total_minor']).'</td><td>'.self::money((int)$r['amount_paid_minor']).'</td><td>'.esc_html(self::date((string)$r['period_start'])).' a '.esc_html(self::date((string)$r['period_end'])).'</td><td>'.(!empty($r['invoice_pdf'])?'<a class="button" target="_blank" rel="noopener" href="'.esc_url($r['invoice_pdf']).'">PDF</a>':'').'</td></tr>';}echo '</tbody></table></div>';}
    private static function events():void{$rows=BillingRepository::events();echo '<div class="ff-b-table"><table class="widefat striped"><thead><tr><th>Data</th><th>Evento</th><th>ID externo</th><th>Estado</th><th>Erro</th></tr></thead><tbody>';if(!$rows)echo '<tr><td colspan="5">Ainda não existem eventos de pagamento.</td></tr>';foreach($rows as$r)echo '<tr><td>'.esc_html((string)$r['created_at']).'</td><td>'.esc_html((string)$r['event_type']).'</td><td><code>'.esc_html((string)$r['provider_event_id']).'</code></td><td>'.esc_html((string)$r['status']).'</td><td>'.esc_html((string)$r['error_message']).'</td></tr>';echo '</tbody></table></div>';}

    private static function settings():void{
        $s=Settings::get();
        $webhook=rest_url('fieldflow-billing/v1/stripe/webhook');
        $portalUrl=Settings::portalUrl();
        $portalId=Settings::portalPageId();
        $portalReady=Settings::pageContainsShortcode($portalId,'fieldflow_billing_portal');
        $pages=get_pages(['post_status'=>'publish','sort_column'=>'post_title','sort_order'=>'ASC']);
        echo '<div class="ff-b-card"><h2>Página do portal de faturação</h2>';
        echo '<p>Seleciona a página publicada onde colocaste o shortcode <code>[fieldflow_billing_portal]</code>. O FieldFlow também consegue detetar o shortcode dentro do conteúdo do Elementor.</p>';
        echo '<p><strong>Estado atual:</strong> '.($portalReady?'<span style="color:#0a7a35;font-weight:700">Associada</span>':'<span style="color:#b45309;font-weight:700">Atenção</span>').'</p>';
        echo '<p><strong>URL atual:</strong> <a href="'.esc_url($portalUrl).'" target="_blank" rel="noopener">'.esc_html($portalUrl).'</a></p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('fieldflow_billing_save_portal_page');
        echo '<input type="hidden" name="action" value="fieldflow_billing_save_portal_page">';
        echo '<p><select name="portal_page_id" style="min-width:360px;max-width:100%"><option value="">Selecionar página</option>';
        foreach((array)$pages as$page){
            $contains=Settings::pageContainsShortcode((int)$page->ID,'fieldflow_billing_portal');
            echo '<option value="'.intval($page->ID).'" '.selected($portalId,(int)$page->ID,false).'>'.esc_html($page->post_title.($contains?' · shortcode detetado':'')).'</option>';
        }
        echo '</select> <button class="button button-primary">Guardar página</button></p></form>';
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap">';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_detect_portal_page');echo '<input type="hidden" name="action" value="fieldflow_billing_detect_portal_page"><button class="button">Detetar automaticamente</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_billing_create_portal_page');echo '<input type="hidden" name="action" value="fieldflow_billing_create_portal_page"><button class="button">Criar nova página automaticamente</button></form>';
        echo '</div></div>';
        echo '<div class="ff-b-card"><h2>Stripe e sandbox</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('fieldflow_billing_save_settings');
        echo '<input type="hidden" name="action" value="fieldflow_billing_save_settings"><table class="form-table">';
        echo '<tr><th>Ativar pagamentos</th><td><label><input type="checkbox" name="enabled" value="1" '.checked(!empty($s['enabled']),true,false).'> Permitir checkout</label></td></tr>';
        echo '<tr><th>Modo</th><td><select name="mode"><option value="sandbox" '.selected($s['mode'],'sandbox',false).'>Sandbox</option><option value="live" '.selected($s['mode'],'live',false).'>Live</option></select></td></tr>';
        echo '<tr><th>Checkout sandbox</th><td><select name="sandbox_driver"><option value="local" '.selected((string)($s['sandbox_driver']??'local'),'local',false).'>Simulador local FieldFlow</option><option value="stripe" '.selected((string)($s['sandbox_driver']??'local'),'stripe',false).'>Stripe test mode</option></select><p class="description">O simulador local não requer conta Stripe. Stripe test mode usa sk_test, Price IDs de sandbox e webhooks de teste.</p></td></tr>';
        echo '<tr><th>Chave secreta sandbox</th><td><input type="password" name="sandbox_secret_key" class="large-text" placeholder="'.(Settings::secretKey('sandbox')?'Configurada, deixa vazio para manter':'sk_test_...').'"></td></tr>';
        echo '<tr><th>Webhook secret sandbox</th><td><input type="password" name="sandbox_webhook_secret" class="large-text" placeholder="'.(Settings::webhookSecret('sandbox')?'Configurado, deixa vazio para manter':'whsec_...').'"></td></tr>';
        echo '<tr><th>Chave secreta live</th><td><input type="password" name="live_secret_key" class="large-text" placeholder="'.(Settings::secretKey('live')?'Configurada, deixa vazio para manter':'sk_live_...').'"></td></tr>';
        echo '<tr><th>Webhook secret live</th><td><input type="password" name="live_webhook_secret" class="large-text" placeholder="'.(Settings::webhookSecret('live')?'Configurado, deixa vazio para manter':'whsec_...').'"></td></tr>';
        echo '<tr><th>Endpoint webhook</th><td><code>'.esc_html($webhook).'</code><p class="description">Eventos recomendados: checkout.session.completed, customer.subscription.*, invoice.paid e invoice.payment_failed.</p></td></tr>';
        echo '<tr><th>URL de sucesso</th><td><input type="url" name="success_url" class="large-text" value="'.esc_attr((string)$s['success_url']).'"></td></tr>';
        echo '<tr><th>URL de cancelamento</th><td><input type="url" name="cancel_url" class="large-text" value="'.esc_attr((string)$s['cancel_url']).'"></td></tr>';
        echo '<tr><th>Tolerância</th><td><input type="number" min="0" max="60" name="grace_days" value="'.intval($s['grace_days']).'"> dias</td></tr>';
        echo '<tr><th>Checkout</th><td><label><input type="checkbox" name="allow_promotion_codes" value="1" '.checked(!empty($s['allow_promotion_codes']),true,false).'> Códigos promocionais</label><br><label><input type="checkbox" name="automatic_tax" value="1" '.checked(!empty($s['automatic_tax']),true,false).'> Stripe Tax automático</label><br><label><input type="checkbox" name="billing_address_required" value="1" '.checked(!empty($s['billing_address_required']),true,false).'> Morada obrigatória</label><br><label><input type="checkbox" name="tax_id_collection" value="1" '.checked(!empty($s['tax_id_collection']),true,false).'> Recolher NIF/VAT</label></td></tr>';
        echo '<tr><th>Email de alertas</th><td><input type="email" name="notification_email" value="'.esc_attr((string)$s['notification_email']).'" class="regular-text"></td></tr>';
        echo '</table><p><button class="button button-primary">Guardar configuração</button></p></form></div>';
    }
    private static function datetimeLocal(string$value):string{if($value==='')return'';$ts=strtotime($value);return$ts?wp_date('Y-m-d\TH:i',$ts):'';}
    private static function money(int$minor):string{return number_format_i18n($minor/100,2).' €';}
    private static function date(string$value):string{return$value?date_i18n('d/m/Y H:i',strtotime($value)):'Sem data';}
}
