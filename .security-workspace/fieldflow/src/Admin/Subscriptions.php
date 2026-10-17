<?php
namespace RoutesPro\Admin;

use RoutesPro\SaaS\CapabilityCatalog;
use RoutesPro\SaaS\PlanRepository;
use RoutesPro\SaaS\UsageService;
use RoutesPro\SaaS\EntitlementService;
use RoutesPro\Billing\BillingRepository;
use RoutesPro\Billing\RoleMappingService;
use RoutesPro\Billing\Settings as BillingSettings;

if (!defined('ABSPATH')) exit;

class Subscriptions {
    public const SLUG='routespro-subscriptions';

    public static function render(): void {
        if(!current_user_can('routespro_manage')) return;
        PlanRepository::seed();
        self::handlePost();
        $tab=sanitize_key((string)($_GET['tab'] ?? 'clients'));
        echo '<div class="wrap"><h1>Subscrições e Capacidades</h1><p>Controla planos, módulos, limites e estados por cliente. Desativar uma capacidade não elimina dados.</p>';
        echo '<nav class="nav-tab-wrapper">';
        foreach(['clients'=>'Clientes','plans'=>'Planos','usage'=>'Utilização','matrix'=>'Matriz de capacidades'] as $key=>$label){
            $url=add_query_arg(['page'=>self::SLUG,'tab'=>$key],admin_url('admin.php'));
            echo '<a class="nav-tab '.($tab===$key?'nav-tab-active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>';
        }
        echo '</nav>';
        if($tab==='plans') self::renderPlans(); elseif($tab==='usage') self::renderUsage(); elseif($tab==='matrix') self::renderMatrix(); else self::renderClients();
        echo '</div>';
    }

    private static function handlePost(): void {
        if(empty($_POST['fieldflow_saas_action'])) return;
        check_admin_referer('fieldflow_saas_save');
        $action=sanitize_key((string)$_POST['fieldflow_saas_action']);
        if($action==='save_plan'){
            $id=absint($_POST['plan_id'] ?? 0);
            $existing=$id ? PlanRepository::plan($id) : null;
            if($existing && !empty($existing['is_system'])){
                echo '<div class="notice notice-warning"><p>O plano Legacy completo é protegido para garantir compatibilidade.</p></div>';
            } else {
                $saved=PlanRepository::savePlan($_POST,$id);
                echo '<div class="notice notice-success"><p>Plano guardado #'.intval($saved).'.</p></div>';
            }
        }
        if($action==='assign_client'){
            $clientId=absint($_POST['client_id'] ?? 0); $planId=absint($_POST['plan_id'] ?? 0);
            PlanRepository::assign($clientId,$planId,$_POST,get_current_user_id(),true);
            EntitlementService::clearCache();
            echo '<div class="notice notice-success"><p>Subscrição atualizada.</p></div>';
        }
    }

    private static function renderClients(): void {
        $rows=PlanRepository::clientRows(); $plans=PlanRepository::plans(true); $editId=absint($_GET['client_id'] ?? 0);
        if($editId){ self::renderClientEditor($editId,$plans); }
        echo '<h2>Clientes</h2><table class="widefat striped"><thead><tr><th>Cliente</th><th>Plano</th><th>Estado</th><th>Renovação</th><th>Capacidades</th><th></th></tr></thead><tbody>';
        foreach($rows as $row){
            $sub=PlanRepository::subscription((int)$row['id']); $enabled=0;
            foreach(CapabilityCatalog::keys() as $cap) if(EntitlementService::clientHas((int)$row['id'],$cap)) $enabled++;
            $url=add_query_arg(['page'=>self::SLUG,'tab'=>'clients','client_id'=>(int)$row['id']],admin_url('admin.php'));
            echo '<tr><td><strong>'.esc_html($row['name']).'</strong><br><small>#'.intval($row['id']).'</small></td><td>'.esc_html($row['plan_name'] ?: 'Sem plano, compatibilidade total').'</td><td>'.esc_html(self::statusLabel(EntitlementService::clientStatus((int)$row['id']))).'</td><td>'.esc_html(self::dateLabel((string)($row['renews_at'] ?: $row['expires_at']))).'</td><td>'.intval($enabled).' / '.count(CapabilityCatalog::keys()).'</td><td><a class="button" href="'.esc_url($url).'">Gerir</a></td></tr>';
        }
        if(!$rows) echo '<tr><td colspan="6">Sem clientes.</td></tr>';
        echo '</tbody></table>';
    }

    private static function renderClientEditor(int $clientId,array $plans): void {
        global $wpdb; $name=(string)$wpdb->get_var($wpdb->prepare('SELECT name FROM '.$wpdb->prefix.'routespro_clients WHERE id=%d',$clientId));
        if($name==='') return; $sub=PlanRepository::subscription($clientId); $selectedPlan=(int)($sub['plan_id'] ?? 0);
        $billing=BillingRepository::subscriptionByClient($clientId,BillingSettings::mode());
        $billingUser=$billing?get_userdata((int)($billing['billing_user_id']??0)):null;
        $mappedRole=RoleMappingService::targetRole((int)($billing['plan_id']??$selectedPlan),(string)($billing['status']??($sub['status']??'active')));
        echo '<div style="margin:18px 0;padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:12px"><h2 style="margin-top:0">'.esc_html($name).'</h2><form method="post">';
        wp_nonce_field('fieldflow_saas_save');
        echo '<input type="hidden" name="fieldflow_saas_action" value="assign_client"><input type="hidden" name="client_id" value="'.intval($clientId).'">';
        echo '<table class="form-table"><tr><th>Plano</th><td><select name="plan_id" required>';
        foreach($plans as $plan) echo '<option value="'.intval($plan['id']).'" '.selected($selectedPlan,(int)$plan['id'],false).'>'.esc_html($plan['name']).'</option>';
        $rolesUrl=add_query_arg(['page'=>'routespro-billing','tab'=>'roles'],admin_url('admin.php'));
        $billingUrl=add_query_arg(['page'=>'routespro-billing','tab'=>'subscriptions'],admin_url('admin.php'));
        echo '</select></td></tr><tr><th>Role WordPress automática</th><td><strong>'.esc_html($mappedRole?:'Sem role mapeada').'</strong><br><small>'.($billingUser?'Utilizador: '.esc_html($billingUser->display_name.' · '.$billingUser->user_email):'Ainda não existe utilizador associado à subscrição de faturação.').'</small><p><a class="button" href="'.esc_url($rolesUrl).'">Mapear roles</a> <a class="button" href="'.esc_url($billingUrl).'">Gerir subscrição de faturação</a></p></td></tr><tr><th>Estado</th><td><select name="status">';
        foreach(['trial'=>'Teste','active'=>'Ativo','past_due'=>'Pagamento pendente','suspended'=>'Suspenso','expired'=>'Expirado','cancelled'=>'Cancelado'] as $key=>$label) echo '<option value="'.$key.'" '.selected((string)($sub['status'] ?? 'active'),$key,false).'>'.$label.'</option>';
        echo '</select></td></tr>';
        echo '<tr><th>Motivo do estado</th><td><input type="text" class="large-text" name="status_reason" value="'.esc_attr((string)($sub['status_reason'] ?? '')).'" placeholder="Ex.: suspensão solicitada, renovação pendente"></td></tr>';
        foreach(['starts_at'=>'Início','trial_ends_at'=>'Fim do teste','renews_at'=>'Renovação','expires_at'=>'Expiração'] as $key=>$label){
            $value=!empty($sub[$key]) ? date('Y-m-d\TH:i',strtotime((string)$sub[$key])) : '';
            echo '<tr><th>'.esc_html($label).'</th><td><input type="datetime-local" name="'.$key.'" value="'.esc_attr($value).'"></td></tr>';
        }
        echo '<tr><th>Limites personalizados</th><td><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">';
        $limitOverrides = $sub ? json_decode((string)($sub['limit_overrides_json'] ?? ''), true) : [];
        if (!is_array($limitOverrides)) $limitOverrides = [];
        foreach(CapabilityCatalog::limitCatalog() as $key=>$def){
            $v=(int)($limitOverrides[$key] ?? 0);
            $planValue=(int)($sub['plan_limits'][$key] ?? 0);
            echo '<label>'.esc_html($def['label']).'<br><input type="number" min="0" name="limit_overrides['.esc_attr($key).']" value="'.intval($v).'" placeholder="'.intval($planValue).'" style="width:100%"><small>0 herda o plano'.($planValue>0?' ('.$planValue.')':' (ilimitado)').'.</small></label>';
        }
        echo '</div></td></tr><tr><th>Exceções de capacidades</th><td><details><summary>Ativar ou desativar capacidades específicas</summary><div style="columns:2;column-gap:28px;margin-top:10px">';
        $over=(array)($sub['overrides'] ?? []);
        foreach(CapabilityCatalog::all() as $key=>$def){
            $current=EntitlementService::clientHas($clientId,$key); $override=array_key_exists($key,$over)?(string)(int)$over[$key]:'';
            echo '<label style="display:block;break-inside:avoid;margin:6px 0">'.esc_html($def['label']).' <select name="capability_overrides['.esc_attr($key).']"><option value="" '.selected($override,'',false).'>Herdar</option><option value="1" '.selected($override,'1',false).'>Ativar</option><option value="0" '.selected($override,'0',false).'>Desativar</option></select> <small>'.($current?'ativo':'inativo').'</small></label>';
        }
        echo '</div></details></td></tr><tr><th>Notas comerciais</th><td><textarea name="notes" rows="4" class="large-text">'.esc_textarea((string)($sub['notes'] ?? '')).'</textarea></td></tr></table><p><button class="button button-primary">Guardar subscrição</button></p></form></div>';
    }

    private static function renderPlans(): void {
        $plans=PlanRepository::plans(false); $editId=absint($_GET['plan_id'] ?? 0); $edit=$editId?PlanRepository::plan($editId):null;
        echo '<h2>'.($edit?'Editar plano':'Novo plano').'</h2><form method="post" style="background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:18px;margin-bottom:20px">'; wp_nonce_field('fieldflow_saas_save');
        echo '<input type="hidden" name="fieldflow_saas_action" value="save_plan"><input type="hidden" name="plan_id" value="'.intval($editId).'"><table class="form-table">';
        echo '<tr><th>Nome</th><td><input name="name" class="regular-text" required value="'.esc_attr((string)($edit['name'] ?? '')).'"></td></tr>';
        echo '<tr><th>Slug</th><td><input name="slug" class="regular-text" value="'.esc_attr((string)($edit['slug'] ?? '')).'" '.(!empty($edit['is_system'])?'readonly':'').'></td></tr>';
        echo '<tr><th>Descrição</th><td><textarea name="description" class="large-text" rows="3">'.esc_textarea((string)($edit['description'] ?? '')).'</textarea></td></tr>';
        echo '<tr><th>Preço mensal</th><td><input type="number" step="0.01" min="0" name="monthly_price" value="'.esc_attr((string)($edit['monthly_price'] ?? 0)).'"> EUR</td></tr>';
        echo '<tr><th>Preço anual</th><td><input type="number" step="0.01" min="0" name="annual_price" value="'.esc_attr((string)($edit['annual_price'] ?? 0)).'"> EUR</td></tr>';
        echo '<tr><th>Ativo</th><td><label><input type="checkbox" name="is_active" value="1" '.checked(!isset($edit['is_active'])||!empty($edit['is_active']),true,false).'> Disponível para atribuição</label></td></tr>';
        echo '<tr><th>Capacidades</th><td>';
        foreach(CapabilityCatalog::groups() as $group=>$items){ echo '<fieldset style="margin:0 0 16px"><legend><strong>'.esc_html($group).'</strong></legend><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:7px">'; foreach($items as $key=>$label) echo '<label><input type="checkbox" name="capabilities['.esc_attr($key).']" value="1" '.checked(!empty($edit['capabilities'][$key]),true,false).'> '.esc_html($label).'</label>'; echo '</div></fieldset>'; }
        echo '</td></tr><tr><th>Limites</th><td><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">';
        foreach(CapabilityCatalog::limitCatalog() as $key=>$def) echo '<label>'.esc_html($def['label']).'<br><input type="number" min="0" name="limits['.esc_attr($key).']" value="'.intval($edit['limits'][$key] ?? 0).'" style="width:100%"><small>0 significa ilimitado.</small></label>';
        echo '</div></td></tr></table><p><button class="button button-primary">Guardar plano</button></p></form>';
        echo '<h2>Planos existentes</h2><table class="widefat striped"><thead><tr><th>Plano</th><th>Preço mensal</th><th>Preço anual</th><th>Capacidades</th><th>Estado</th><th></th></tr></thead><tbody>';
        foreach($plans as $plan){
            $url=add_query_arg(['page'=>self::SLUG,'tab'=>'plans','plan_id'=>(int)$plan['id']],admin_url('admin.php'));
            $action=!empty($plan['is_system']) ? '<span class="button disabled">Protegido</span>' : '<a class="button" href="'.esc_url($url).'">Editar</a>';
            echo '<tr><td><strong>'.esc_html($plan['name']).'</strong><br><code>'.esc_html($plan['slug']).'</code></td><td>'.self::money((float)$plan['monthly_price']).'</td><td>'.self::money((float)$plan['annual_price']).'</td><td>'.count(array_filter((array)$plan['capabilities'])).'</td><td>'.(!empty($plan['is_active'])?'Ativo':'Inativo').'</td><td>'.$action.'</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function renderUsage(): void {
        $rows=PlanRepository::clientRows(); echo '<h2>Utilização do mês '.esc_html(UsageService::period()).'</h2>';
        foreach($rows as $row){
            $snap=UsageService::snapshot((int)$row['id']);
            echo '<div style="margin:14px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:12px"><h3 style="margin-top:0">'.esc_html($row['name']).'</h3><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px">';
            foreach($snap as $item){
                $pct=!empty($item['limit'])?min(100,round(($item['used']/$item['limit'])*100)):0;
                $bar=$pct>=100?'#dc2626':($pct>=80?'#d97706':'#2563eb');
                $status=$pct>=100?'Limite atingido':($pct>=80?'A aproximar-se do limite':($item['limit']>0?'Dentro do plano':'Ilimitado'));
                echo '<div style="border:1px solid #e2e8f0;border-radius:10px;padding:12px"><strong>'.esc_html($item['label']).'</strong><div style="font-size:22px;margin:5px 0">'.intval($item['used']).($item['limit']>0?' / '.intval($item['limit']):'').'</div><div style="height:6px;background:#e2e8f0;border-radius:99px;overflow:hidden"><span style="display:block;height:100%;width:'.intval($pct).'%;background:'.esc_attr($bar).'"></span></div><small style="display:block;margin-top:6px;color:'.esc_attr($bar).'">'.esc_html($status).'</small></div>';
            }
            echo '</div></div>';
        }
    }

    private static function renderMatrix(): void {
        $plans=PlanRepository::plans(true); echo '<h2>Matriz de capacidades</h2><div style="overflow:auto"><table class="widefat striped"><thead><tr><th>Capacidade</th>'; foreach($plans as $plan) echo '<th>'.esc_html($plan['name']).'</th>'; echo '</tr></thead><tbody>';
        foreach(CapabilityCatalog::all() as $key=>$def){ echo '<tr><td><strong>'.esc_html($def['label']).'</strong><br><code>'.esc_html($key).'</code></td>'; foreach($plans as $plan) echo '<td style="text-align:center">'.(!empty($plan['capabilities'][$key])?'✓':'').'</td>'; echo '</tr>'; }
        echo '</tbody></table></div>';
    }

    private static function money(float $value): string { return number_format_i18n($value,2).' €'; }
    private static function dateLabel(string $value): string { return $value ? date_i18n('d/m/Y',strtotime($value)) : 'Sem data'; }
    private static function statusLabel(string $status): string { return ['trial'=>'Teste','active'=>'Ativo','past_due'=>'Pagamento pendente','suspended'=>'Suspenso','expired'=>'Expirado','cancelled'=>'Cancelado','legacy'=>'Compatibilidade'][sanitize_key($status)] ?? ucfirst($status); }
}
