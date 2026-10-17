<?php
namespace RoutesPro\Front;

use RoutesPro\Billing\BillingRepository;
use RoutesPro\Billing\BillingService;
use RoutesPro\Billing\RoleMappingService;
use RoutesPro\Billing\Settings;
use RoutesPro\SaaS\EntitlementService;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

class BillingShortcodeRenderer {
    public static function render(array $atts = []): string {
        if (!is_user_logged_in()) return '<p>Inicia sessão para consultar o plano e a faturação.</p>';

        $userId = get_current_user_id();
        $clients = self::availableClients($userId);
        if (!$clients) {
            return '<div class="ff-billing-empty">Ainda não existem clientes disponíveis para este utilizador.</div>';
        }

        $clientId = self::selectedClientId($atts, $clients, $userId);
        if ($clientId <= 0) {
            return '<div class="ff-billing-empty">Seleciona o cliente cuja subscrição pretendes consultar.</div>';
        }

        $client = null;
        foreach ($clients as $row) {
            if ((int)$row['id'] === $clientId) {
                $client = $row;
                break;
            }
        }
        if (!$client) return '<div class="ff-billing-empty">O cliente selecionado não está disponível para este utilizador.</div>';

        if (class_exists(RoleMappingService::class) && method_exists(BillingRepository::class, 'effectiveSubscriptionsForUser')) {
            $assignedSubscriptions = BillingRepository::effectiveSubscriptionsForUser($userId, false);
            if ($assignedSubscriptions) RoleMappingService::syncUserFromSubscriptions($userId, 'Reconciliação ao abrir o portal de faturação.');
        }

        $account = BillingService::account($clientId);
        $nonce = wp_create_nonce('wp_rest');
        $endpoint = rest_url('fieldflow/v1/billing/checkout');
        $portalEndpoint = rest_url('fieldflow/v1/billing/portal');
        $explicitClient = absint($atts['client_id'] ?? 0) > 0;
        $showSelector = count($clients) > 1 && (!$explicitClient || !empty($atts['show_client_selector']));

        ob_start();
        ?>
        <section class="ff-billing-portal" data-client="<?php echo intval($clientId); ?>" data-endpoint="<?php echo esc_url($endpoint); ?>" data-portal-endpoint="<?php echo esc_url($portalEndpoint); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
          <style>
            .ff-billing-portal{font-family:inherit;color:#0f172a;width:100%;min-width:0;max-width:100%;overflow-x:hidden}.ff-billing-context{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin:0 0 16px;padding:16px 18px;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc}.ff-billing-context label{display:block;margin:0 0 6px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b}.ff-billing-client-select{width:min(100%,420px);min-height:44px;padding:9px 40px 9px 12px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;color:#0f172a;font:inherit;font-weight:700}.ff-billing-context-note{font-size:13px;color:#64748b;text-align:right}.ff-billing-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;padding:20px;border:1px solid #e2e8f0;border-radius:20px;background:#fff;margin-bottom:16px}.ff-billing-kicker{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b}.ff-billing-head h2{margin:4px 0 6px}.ff-billing-status{padding:7px 12px;border-radius:99px;background:#eef2ff;color:#3730a3;font-weight:700}.ff-billing-head-actions{display:flex;align-items:flex-end;flex-direction:column;gap:9px}.ff-billing-manage{border:0;border-radius:12px;padding:10px 13px;background:#0f172a;color:#fff;font-weight:700;cursor:pointer}.ff-billing-plans{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.ff-billing-plan{display:flex;flex-direction:column;background:#fff;border:1px solid #e2e8f0;border-radius:20px;padding:20px;min-width:0}.ff-billing-plan.is-current{border-color:#2563eb;box-shadow:0 12px 30px rgba(37,99,235,.12)}.ff-billing-plan h3{margin:0 0 8px}.ff-billing-plan p{color:#64748b;flex:1}.ff-billing-price{font-size:30px;font-weight:800;margin:8px 0}.ff-billing-price small{font-size:13px;color:#64748b}.ff-billing-actions{display:grid;grid-template-columns:1fr 1fr;gap:8px}.ff-billing-btn{border:0;border-radius:12px;padding:12px 10px;cursor:pointer;font-weight:700;background:#0f172a;color:#fff}.ff-billing-btn.secondary{background:#eef2ff;color:#3730a3}.ff-billing-btn:disabled{opacity:.45;cursor:not-allowed}.ff-billing-note{margin-top:14px;padding:12px 14px;border-radius:14px;background:#f8fafc;color:#475569}.ff-billing-impact{margin:10px 0 0;padding:10px 12px;border-radius:12px;background:#fff7ed;color:#9a3412;font-size:12px;line-height:1.5}.ff-billing-impact strong{display:block;margin-bottom:3px}.ff-billing-message{margin:12px 0;padding:12px;border-radius:12px;display:none}.ff-billing-message.is-error{display:block;background:#fee2e2;color:#991b1b}.ff-billing-message.is-ok{display:block;background:#dcfce7;color:#166534}@media(max-width:980px){.ff-billing-plans{grid-template-columns:1fr 1fr}}@media(max-width:640px){.ff-billing-context{display:block}.ff-billing-context-note{text-align:left;margin-top:9px}.ff-billing-head{display:block}.ff-billing-head-actions{align-items:flex-start;margin-top:10px}.ff-billing-status{display:inline-block;margin-top:8px}.ff-billing-plans{grid-template-columns:1fr}.ff-billing-actions{grid-template-columns:1fr}}
          </style>

          <?php if ($showSelector): ?>
            <div class="ff-billing-context">
              <div style="min-width:0;flex:1">
                <label for="ff-billing-client-selector-<?php echo intval($clientId); ?>">Cliente da subscrição</label>
                <select id="ff-billing-client-selector-<?php echo intval($clientId); ?>" class="ff-billing-client-select" data-billing-client-selector>
                  <?php foreach ($clients as $option): ?>
                    <option value="<?php echo intval($option['id']); ?>" <?php selected((int)$option['id'], $clientId); ?>>
                      <?php
                      $label = (string)$option['name'];
                      if (!empty($option['plan_name'])) $label .= ' · ' . (string)$option['plan_name'];
                      if (!empty($option['billing_status'])) $label .= ' · ' . self::statusLabel((string)$option['billing_status']);
                      echo esc_html($label);
                      ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ff-billing-context-note">Podes alternar entre todos os clientes a que tens acesso.</div>
            </div>
          <?php endif; ?>

          <header class="ff-billing-head">
            <div>
              <span class="ff-billing-kicker"><?php echo esc_html((string)$client['name']); ?> · Plano e faturação</span>
              <h2><?php echo esc_html((string)($account['subscription']['plan_name'] ?? 'Sem plano')); ?></h2>
              <p style="margin:0;color:#64748b">Consulta o plano atual e inicia uma alteração segura através do checkout.</p>
            </div>
            <div class="ff-billing-head-actions">
              <span class="ff-billing-status"><?php echo esc_html(self::statusLabel((string)($account['subscription']['status'] ?? 'sem_estado'))); ?></span>
              <?php if (!empty($account['portal_available'])): ?><button type="button" class="ff-billing-manage" data-billing-portal>Gerir faturação</button><?php endif; ?>
            </div>
          </header>

          <?php if (!empty($account['billing']['grace_until'])): ?>
            <div class="ff-billing-note"><strong>Pagamento pendente.</strong> O período de tolerância termina em <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime((string)$account['billing']['grace_until']))); ?>. Os dados permanecem protegidos.</div>
          <?php endif; ?>

          <div class="ff-billing-message" role="status"></div>
          <div class="ff-billing-plans">
            <?php foreach ((array)$account['plans'] as $plan): $current = (int)($account['subscription']['plan_id'] ?? 0) === (int)$plan['id']; ?>
              <article class="ff-billing-plan <?php echo $current ? 'is-current' : ''; ?>">
                <h3><?php echo esc_html($plan['name']); ?></h3>
                <p><?php echo esc_html($plan['description']); ?></p>
                <?php if ((float)$plan['monthly_price'] > 0 || (float)$plan['annual_price'] > 0): ?>
                  <div class="ff-billing-price"><?php echo (float)$plan['monthly_price'] > 0 ? esc_html(number_format_i18n((float)$plan['monthly_price'], 2)).' € <small>/ mês</small>' : 'Mensal indisponível'; ?></div>
                  <small style="display:block;margin:-4px 0 12px;color:#64748b"><?php echo (float)$plan['annual_price'] > 0 ? esc_html(number_format_i18n((float)$plan['annual_price'], 2)).' € / ano' : 'Periodicidade anual não configurada'; ?></small>
                <?php else: ?>
                  <div class="ff-billing-price" style="font-size:24px">Sob consulta</div>
                  <small style="display:block;margin:-4px 0 12px;color:#64748b">Adiciona preços e ativa o catálogo para permitir checkout</small>
                <?php endif; ?>
                <?php if (!empty($plan['downgrade_impact'])): ?>
                  <div class="ff-billing-impact"><strong>Utilização acima dos limites deste plano</strong>O downgrade preserva todos os dados, mas novas utilizações ficam bloqueadas nos limites excedidos até a operação voltar a caber no plano.</div>
                <?php endif; ?>
                <div class="ff-billing-actions">
                  <button class="ff-billing-btn" data-plan="<?php echo (int)$plan['id']; ?>" data-interval="month" <?php disabled(empty($plan['monthly_checkout'])); ?>>Mensal</button>
                  <button class="ff-billing-btn secondary" data-plan="<?php echo (int)$plan['id']; ?>" data-interval="year" <?php disabled(empty($plan['annual_checkout'])); ?>>Anual</button>
                </div>
                <?php if ($current): ?><small style="margin-top:10px;color:#2563eb;font-weight:700">Plano atual</small><?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
          <div class="ff-billing-note">No modo sandbox, o checkout é apenas uma simulação. Em live, o pagamento é processado pela Stripe. O FieldFlow não guarda dados de cartão.</div>
          <script>(function(){const root=document.currentScript.closest('.ff-billing-portal');if(!root)return;const msg=root.querySelector('.ff-billing-message');const selector=root.querySelector('[data-billing-client-selector]');if(selector){selector.addEventListener('change',function(){const url=new URL(window.location.href);url.searchParams.set('ff_billing_client',selector.value);window.location.href=url.toString();});}async function post(url,body){const res=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':root.dataset.nonce},body:JSON.stringify(body)});const data=await res.json();if(!res.ok)throw new Error(data.message||'Não foi possível abrir a faturação.');if(!data.url)throw new Error('A operação não devolveu um URL válido.');window.location.href=data.url;}root.addEventListener('click',async function(e){const portal=e.target.closest('[data-billing-portal]');if(portal){portal.disabled=true;msg.textContent='A abrir o portal seguro...';msg.className='ff-billing-message is-ok';try{await post(root.dataset.portalEndpoint,{client_id:parseInt(root.dataset.client,10),return_url:window.location.href});}catch(err){msg.textContent=err.message||'Falha ao abrir o portal.';msg.className='ff-billing-message is-error';portal.disabled=false;}return;}const btn=e.target.closest('[data-plan][data-interval]');if(!btn||btn.disabled)return;btn.disabled=true;msg.textContent='A preparar checkout...';msg.className='ff-billing-message is-ok';try{await post(root.dataset.endpoint,{client_id:parseInt(root.dataset.client,10),plan_id:parseInt(btn.dataset.plan,10),interval:btn.dataset.interval,return_url:window.location.href});}catch(err){msg.textContent=err.message||'Falha ao preparar checkout.';msg.className='ff-billing-message is-error';btn.disabled=false;}});})();</script>
        </section>
        <?php
        return (string)ob_get_clean();
    }

    private static function availableClients(int $userId): array {
        global $wpdb;
        $manager = current_user_can('routespro_manage');
        $clientIds = [];
        if (!$manager) {
            $scope = Permissions::get_scope($userId);
            foreach ((array)($scope['client_ids'] ?? []) as $id) {
                $id = absint($id);
                if ($id > 0) $clientIds[$id] = $id;
            }
            if (method_exists(BillingRepository::class, 'effectiveSubscriptionsForUser')) {
                foreach (BillingRepository::effectiveSubscriptionsForUser($userId, false) as $subscription) {
                    $id = absint($subscription['client_id'] ?? 0);
                    if ($id > 0) $clientIds[$id] = $id;
                }
            }
            foreach (array_merge(
                (array)get_user_meta($userId, 'fieldflow_billing_client_ids', true),
                [get_user_meta($userId, 'fieldflow_billing_client_id', true)]
            ) as $id) {
                $id = absint($id);
                if ($id > 0) $clientIds[$id] = $id;
            }
            if (!$clientIds) return [];
        }

        $environment = Settings::mode();
        $sql = "SELECT c.id,c.name,s.status billing_status,s.billing_interval,p.name plan_name
                FROM {$wpdb->prefix}routespro_clients c
                LEFT JOIN ".BillingRepository::subscriptionsTable()." s ON s.id=(
                    SELECT s2.id FROM ".BillingRepository::subscriptionsTable()." s2
                    WHERE s2.client_id=c.id AND s2.environment=%s
                    ORDER BY CASE
                      WHEN s2.status IN ('active','trial','trialing') THEN 0
                      WHEN s2.status='past_due' THEN 1
                      WHEN s2.status='suspended' THEN 2
                      ELSE 3 END ASC, s2.id DESC LIMIT 1
                )
                LEFT JOIN {$wpdb->prefix}routespro_saas_plans p ON p.id=s.plan_id";
        $args = [$environment];
        if (!$manager) {
            $ids = array_values($clientIds);
            $sql .= ' WHERE c.id IN ('.implode(',', array_fill(0, count($ids), '%d')).')';
            $args = array_merge($args, $ids);
        }
        $sql .= " ORDER BY CASE
                    WHEN s.status IN ('active','trial','trialing') THEN 0
                    WHEN s.status='past_due' THEN 1
                    WHEN s.status='suspended' THEN 2
                    ELSE 3 END ASC, c.name ASC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];
    }

    private static function selectedClientId(array $atts, array $clients, int $userId): int {
        $allowed = [];
        foreach ($clients as $client) $allowed[(int)$client['id']] = true;

        $candidates = [
            absint($atts['client_id'] ?? 0),
            absint($_GET['ff_billing_client'] ?? 0),
            EntitlementService::resolveClientId($atts),
            absint(get_user_meta($userId, 'fieldflow_billing_client_id', true)),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate > 0 && isset($allowed[$candidate])) return $candidate;
        }
        foreach ($clients as $client) {
            if (in_array((string)($client['billing_status'] ?? ''), ['active','trial','trialing','past_due'], true)) return (int)$client['id'];
        }
        return (int)($clients[0]['id'] ?? 0);
    }

    private static function statusLabel(string $status): string {
        switch (sanitize_key($status)) {
            case 'active': return 'Ativa';
            case 'trial':
            case 'trialing': return 'Em teste';
            case 'past_due': return 'Pagamento pendente';
            case 'suspended': return 'Suspensa';
            case 'cancelled': return 'Cancelada';
            case 'expired': return 'Expirada';
            default: return 'Sem estado';
        }
    }
}
