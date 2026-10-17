<?php
namespace RoutesPro\Front;

use RoutesPro\Billing\Settings;
use RoutesPro\Billing\SignupService;
use RoutesPro\Billing\BillingRepository;
use RoutesPro\Billing\BillingService;
use RoutesPro\Billing\RoleMappingService;
use RoutesPro\SaaS\PlanRepository;
use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

class SignupShortcodeRenderer {
    public static function render(array $atts = []): string {
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (function_exists('nocache_headers') && !headers_sent()) nocache_headers();
        $settings = Settings::get();
        if (empty($settings['onboarding_enabled'])) return '<div class="ff-signup-empty">As novas subscrições estão temporariamente indisponíveis.</div>';
        $token = SignupService::currentToken();
        $intent = $token !== '' ? SignupService::intentByToken($token) : null;
        $attachError = null;
        if ($intent && is_user_logged_in()) {
            $attached = SignupService::attachUser($token, get_current_user_id(), 'Página pública de subscrição aberta.');
            if (is_wp_error($attached)) $attachError = $attached->get_error_message();
        }
        $visibleSubscriptions = self::visibleSubscriptions();
        if (is_user_logged_in() && class_exists(RoleMappingService::class) && method_exists(BillingRepository::class, 'effectiveSubscriptionsForUser')) {
            $assignedSubscriptions = BillingRepository::effectiveSubscriptionsForUser(get_current_user_id(), false);
            if ($assignedSubscriptions) RoleMappingService::syncUserFromSubscriptions(get_current_user_id(), 'Reconciliação ao abrir a página pública de subscrições.');
        }
        $plans = [];
        foreach (PlanRepository::plans(true) as $plan) {
            if ((string)($plan['slug'] ?? '') === 'legacy') continue;
            $monthly = BillingService::commercialPrice((int)$plan['id'], 'month');
            $annual = BillingService::commercialPrice((int)$plan['id'], 'year');
            $plan['monthly_price'] = (float)$monthly['amount'];
            $plan['annual_price'] = (float)$annual['amount'];
            $plan['currency'] = (string)($monthly['currency'] ?: $annual['currency'] ?: ($plan['currency'] ?? 'EUR'));
            $plan['monthly_checkout'] = BillingService::checkoutAvailable((int)$plan['id'], 'month');
            $plan['annual_checkout'] = BillingService::checkoutAvailable((int)$plan['id'], 'year');
            if ((string)($plan['slug'] ?? '') === 'enterprise' && empty($plan['monthly_checkout']) && empty($plan['annual_checkout'])) continue;
            $plans[] = $plan;
        }
        $nonce = wp_create_nonce('wp_rest');
        $intentEndpoint = rest_url('fieldflow/v1/billing/signup/intent');
        $checkoutEndpoint = rest_url('fieldflow/v1/billing/signup/checkout');
        ob_start();
        ?>
        <section class="ff-subscription-signup" data-intent-endpoint="<?php echo esc_url($intentEndpoint); ?>" data-checkout-endpoint="<?php echo esc_url($checkoutEndpoint); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-token="<?php echo esc_attr($token); ?>" data-resume-url="<?php echo esc_attr(SignupService::resumeUrl($token, $intent)); ?>">
          <style>
            .ff-subscription-signup{font-family:inherit;color:#0f172a;width:100%;min-width:0;max-width:100%;overflow-x:hidden}.ff-su-hero{text-align:center;max-width:820px;margin:0 auto 28px}.ff-su-hero h1{font-size:clamp(32px,5vw,58px);line-height:1.04;margin:0 0 14px}.ff-su-hero p{font-size:18px;color:#64748b;margin:0}.ff-su-plans{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;max-width:1180px;margin:0 auto}.ff-su-plan{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:24px;box-shadow:0 14px 40px rgba(15,23,42,.06);display:flex;flex-direction:column;min-width:0}.ff-su-plan.is-selected{border-color:#2563eb;box-shadow:0 18px 50px rgba(37,99,235,.14)}.ff-su-plan h2{margin:0 0 9px;font-size:24px}.ff-su-plan p{color:#64748b;flex:1}.ff-su-price{font-size:34px;font-weight:850;margin:10px 0 2px}.ff-su-price small{font-size:13px;color:#64748b;font-weight:600}.ff-su-annual{font-size:13px;color:#64748b;margin-bottom:16px}.ff-su-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px}.ff-su-btn{border:0;border-radius:13px;padding:13px 12px;font-weight:800;cursor:pointer;background:#0f172a;color:#fff}.ff-su-btn.alt{background:#eef2ff;color:#3730a3}.ff-su-btn:disabled{opacity:.5;cursor:wait}.ff-su-stage{max-width:900px;margin:24px auto 0;background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:24px}.ff-su-summary{display:flex;gap:16px;justify-content:space-between;align-items:center;background:#f8fafc;border-radius:18px;padding:18px;margin-bottom:20px}.ff-su-summary strong{font-size:22px}.ff-su-summary span{color:#64748b}.ff-su-login{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}.ff-su-link{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:11px 14px;text-decoration:none;font-weight:750;background:#eef2ff;color:#3730a3}.ff-su-checkout{width:100%;font-size:17px;padding:15px}.ff-su-message{display:none;margin:14px 0;padding:13px 15px;border-radius:13px}.ff-su-message.ok{display:block;background:#dcfce7;color:#166534}.ff-su-message.error{display:block;background:#fee2e2;color:#991b1b}.ff-su-um{margin-top:18px}.ff-su-note{margin-top:18px;color:#64748b;font-size:13px}.ff-su-existing{max-width:1180px;margin:0 auto 24px;padding:20px;border:1px solid #e2e8f0;border-radius:22px;background:#f8fafc}.ff-su-existing-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px}.ff-su-existing-head h2{margin:0 0 4px;font-size:22px}.ff-su-existing-head p{margin:0;color:#64748b}.ff-su-existing-count{display:inline-flex;align-items:center;justify-content:center;min-width:34px;height:34px;padding:0 10px;border-radius:999px;background:#0f172a;color:#fff;font-weight:800}.ff-su-existing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ff-su-existing-item{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px;background:#fff;border:1px solid #e2e8f0;border-radius:15px}.ff-su-existing-item strong{display:block}.ff-su-existing-meta{margin-top:4px;color:#64748b;font-size:13px}.ff-su-existing-badges{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.ff-su-badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:800}.ff-su-badge.live{background:#dcfce7;color:#166534}.ff-su-badge.sandbox{background:#fef3c7;color:#92400e}.ff-su-existing-link{display:inline-flex;margin-top:8px;color:#3730a3;font-size:13px;font-weight:800;text-decoration:none}.ff-su-consent{max-width:1180px;margin:0 auto 18px;padding:18px 20px;border:1px solid #fed7aa;border-left:4px solid #f2622e;border-radius:16px;background:#fff7ed}.ff-su-consent label{display:flex;gap:10px;align-items:flex-start;margin:8px 0;line-height:1.5}.ff-su-consent input{margin-top:3px}.ff-su-consent a{color:#bf2a2a;font-weight:750}.ff-su-consent small{display:block;color:#64748b;margin-top:8px}.ff-su-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0}.ff-su-step{border-radius:14px;background:#f8fafc;padding:13px}.ff-su-step b{display:block;margin-bottom:3px}@media(max-width:900px){.ff-su-plans{grid-template-columns:1fr 1fr}.ff-su-existing-grid{grid-template-columns:1fr}}@media(max-width:640px){.ff-su-plans{grid-template-columns:1fr}.ff-su-actions{grid-template-columns:1fr}.ff-su-summary{display:block}.ff-su-steps{grid-template-columns:1fr}.ff-su-stage{padding:18px}}
          </style>
          <div class="ff-su-hero"><h1>Escolhe o FieldFlow certo para a tua operação</h1><p>Cria a conta, conclui a subscrição e recebe automaticamente o acesso correspondente ao plano.</p></div>
          <div class="ff-su-message<?php echo $attachError ? ' error' : ''; ?>" role="status"<?php echo $attachError ? ' style="display:block"' : ''; ?>><?php echo $attachError ? esc_html($attachError) : ''; ?></div>
          <?php if ($visibleSubscriptions): ?>
            <div class="ff-su-existing">
              <div class="ff-su-existing-head">
                <div><h2>Subscrições ativas sincronizadas</h2><p><?php echo current_user_can('routespro_manage') ? 'Todas as subscrições ativas dos clientes, incluindo sandbox e live.' : 'Subscrições ativas associadas à tua conta e aos clientes a que tens acesso.'; ?></p></div>
                <span class="ff-su-existing-count"><?php echo count($visibleSubscriptions); ?></span>
              </div>
              <div class="ff-su-existing-grid">
                <?php foreach ($visibleSubscriptions as $subscription): ?>
                  <div class="ff-su-existing-item">
                    <div style="min-width:0">
                      <strong><?php echo esc_html((string)($subscription['client_name'] ?: 'Cliente sem nome')); ?></strong>
                      <div class="ff-su-existing-meta"><?php echo esc_html((string)($subscription['plan_name'] ?: 'Plano sem nome')); ?> · <?php echo (string)($subscription['billing_interval'] ?? 'month') === 'year' ? 'Anual' : 'Mensal'; ?></div>
                      <?php if (Settings::portalUrl()): ?><a class="ff-su-existing-link" href="<?php echo esc_url(add_query_arg('ff_billing_client', absint($subscription['client_id'] ?? 0), Settings::portalUrl())); ?>">Gerir esta subscrição</a><?php endif; ?>
                    </div>
                    <div class="ff-su-existing-badges">
                      <span class="ff-su-badge"><?php echo esc_html(self::statusLabel((string)($subscription['status'] ?? 'active'))); ?></span>
                      <span class="ff-su-badge <?php echo (string)($subscription['environment'] ?? '') === 'live' ? 'live' : 'sandbox'; ?>"><?php echo (string)($subscription['environment'] ?? '') === 'live' ? 'Live' : 'Sandbox'; ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!$intent): ?>
            <?php if (!empty($settings['legal_consent_required'])): ?>
              <div class="ff-su-consent">
                <strong>Consentimentos para iniciar a subscrição</strong>
                <label><input type="checkbox" data-ff-terms> <span>Li e aceito os <a href="<?php echo esc_url((string)($settings['terms_url'] ?: '#')); ?>" target="_blank" rel="noopener">Termos e Condições</a> (versão <?php echo esc_html((string)($settings['terms_version'] ?? '1.0')); ?>).</span></label>
                <label><input type="checkbox" data-ff-privacy> <span>Li e aceito a <a href="<?php echo esc_url((string)($settings['privacy_url'] ?: '#')); ?>" target="_blank" rel="noopener">Política de Privacidade</a> (versão <?php echo esc_html((string)($settings['privacy_version'] ?? '1.0')); ?>).</span></label>
                <label><input type="checkbox" data-ff-marketing> <span>Aceito receber comunicações comerciais do FieldFlow. Este consentimento é opcional.</span></label>
                <small>O FieldFlow guarda a versão dos documentos aceites, a data e uma impressão técnica protegida para auditoria.</small>
              </div>
            <?php endif; ?>
            <div class="ff-su-plans">
              <?php foreach ($plans as $plan): ?>
                <article class="ff-su-plan">
                  <h2><?php echo esc_html((string)$plan['name']); ?></h2>
                  <p><?php echo esc_html((string)$plan['description']); ?></p>
                  <div class="ff-su-price"><?php echo esc_html(number_format_i18n((float)$plan['monthly_price'], 2)); ?> € <small>/ mês</small></div>
                  <div class="ff-su-annual"><?php echo esc_html(number_format_i18n((float)$plan['annual_price'], 2)); ?> € por ano</div>
                  <div class="ff-su-actions"><button type="button" class="ff-su-btn" data-start-plan="<?php echo intval($plan['id']); ?>" data-interval="month" <?php disabled(empty($plan['monthly_checkout'])); ?>><?php echo !empty($plan['monthly_checkout']) ? 'Escolher mensal' : 'Mensal indisponível'; ?></button><button type="button" class="ff-su-btn alt" data-start-plan="<?php echo intval($plan['id']); ?>" data-interval="year" <?php disabled(empty($plan['annual_checkout'])); ?>><?php echo !empty($plan['annual_checkout']) ? 'Escolher anual' : 'Anual indisponível'; ?></button></div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="ff-su-stage">
              <div class="ff-su-summary"><div><span>Plano selecionado</span><br><strong><?php echo esc_html((string)$intent['plan_name']); ?></strong></div><div><span>Periodicidade</span><br><strong><?php echo (string)$intent['billing_interval'] === 'year' ? 'Anual' : 'Mensal'; ?></strong></div><div><span>Valor</span><br><strong><?php $amount=(string)$intent['billing_interval']==='year'?(float)$intent['annual_price']:(float)$intent['monthly_price']; echo esc_html(number_format_i18n($amount,2)); ?> €</strong></div></div>
              <div class="ff-su-steps"><div class="ff-su-step"><b>1. Conta</b><span>Regista-te ou inicia sessão.</span></div><div class="ff-su-step"><b>2. Pagamento</b><span>Conclui o checkout seguro.</span></div><div class="ff-su-step"><b>3. Acesso</b><span>A role do plano é aplicada automaticamente.</span></div></div>
              <?php if (!is_user_logged_in()): ?>
                <?php $resume = !empty($_GET['resume']); ?>
                <h2><?php echo $resume ? 'Conta criada, inicia sessão' : 'Criar conta'; ?></h2>
                <p><?php echo $resume ? 'A conta já foi criada ou ativada. Inicia sessão para retomar o pagamento do plano escolhido.' : 'O plano escolhido fica guardado durante o registo. A role paga só será atribuída depois da confirmação do pagamento.'; ?></p>
                <?php if (!$resume && SignupService::ultimateMemberAvailable() && absint($settings['um_form_id'] ?? 0) > 0): ?>
                  <div class="ff-su-um"><?php echo do_shortcode('[ultimatemember form_id="'.absint($settings['um_form_id']).'"]'); ?></div>
                  <script>(function(){const root=document.currentScript.closest('.ff-subscription-signup');if(!root)return;const token=root.dataset.token||'';const inject=()=>root.querySelectorAll('.um-form form,form.um-form').forEach(form=>{let field=form.querySelector('input[name="fieldflow_signup_token"]');if(!field){field=document.createElement('input');field.type='hidden';field.name='fieldflow_signup_token';form.appendChild(field);}field.value=token;});inject();new MutationObserver(inject).observe(root,{childList:true,subtree:true});})();</script>
                <?php elseif (!$resume): ?>
                  <div class="ff-su-message error" style="display:block">O Ultimate Member ainda não está configurado para este formulário. O administrador deve selecionar o Form ID em FieldFlow &gt; Faturação &gt; Onboarding.</div>
                <?php endif; ?>
                <div class="ff-su-login"><a class="ff-su-link" href="<?php echo esc_url(self::loginUrl($token)); ?>"><?php echo $resume ? 'Iniciar sessão e continuar' : 'Já tenho conta'; ?></a><a class="ff-su-link" href="<?php echo esc_url(remove_query_arg(['ff_signup','resume'])); ?>">Escolher outro plano</a></div>
              <?php else: ?>
                <?php $existing=BillingRepository::preferredSubscriptionForUser(get_current_user_id()); ?>
                <h2><?php echo $existing ? 'Alterar ou concluir a subscrição' : 'Continuar para pagamento'; ?></h2>
                <p>Conta autenticada como <strong><?php echo esc_html(wp_get_current_user()->display_name); ?></strong>. O checkout será associado a este utilizador.</p>
                <button type="button" class="ff-su-btn ff-su-checkout" data-signup-checkout>Continuar para pagamento</button>
                <div class="ff-su-login"><a class="ff-su-link" href="<?php echo esc_url(remove_query_arg(['ff_signup','resume'])); ?>">Escolher outro plano</a></div>
              <?php endif; ?>
              <div class="ff-su-note">Os dados de cartão são processados pela Stripe ou pelo simulador sandbox. O FieldFlow nunca guarda os números do cartão.</div>
            </div>
          <?php endif; ?>
          <script>(function(){const root=document.currentScript.closest('.ff-subscription-signup');if(!root)return;const msg=root.querySelector('.ff-su-message');const show=(text,type)=>{msg.textContent=text;msg.className='ff-su-message '+type;};async function request(url,body,auth){const headers={'Content-Type':'application/json'};if(auth)headers['X-WP-Nonce']=root.dataset.nonce;const res=await fetch(url,{method:'POST',credentials:'same-origin',headers,body:JSON.stringify(body)});const data=await res.json();if(!res.ok)throw new Error(data.message||'Não foi possível continuar.');return data;}root.addEventListener('click',async e=>{const start=e.target.closest('[data-start-plan]');if(start){start.disabled=true;show('A preparar o registo...','ok');try{const here=new URL(window.location.href);here.searchParams.delete('ff_signup');here.searchParams.delete('resume');here.hash='';const terms=root.querySelector('[data-ff-terms]');const privacy=root.querySelector('[data-ff-privacy]');const marketing=root.querySelector('[data-ff-marketing]');if((terms&&!terms.checked)||(privacy&&!privacy.checked))throw new Error('Aceita os Termos e Condições e a Política de Privacidade para continuar.');const data=await request(root.dataset.intentEndpoint,{plan_id:parseInt(start.dataset.startPlan,10),interval:start.dataset.interval,source:'public_page',return_url:here.toString(),terms_accepted:terms?terms.checked:true,privacy_accepted:privacy?privacy.checked:true,marketing_consent:marketing?marketing.checked:false},false);const next=data.resume_url||data.url;if(!next)throw new Error('Não foi possível determinar a página de continuação.');window.location.assign(next);}catch(err){show(err.message,'error');start.disabled=false;}return;}const checkout=e.target.closest('[data-signup-checkout]');if(checkout){checkout.disabled=true;show('A preparar o checkout seguro...','ok');try{const data=await request(root.dataset.checkoutEndpoint,{token:root.dataset.token,return_url:window.location.href},true);if(!data.url)throw new Error('O checkout não devolveu um endereço válido.');window.location.href=data.url;}catch(err){show(err.message,'error');checkout.disabled=false;}}});})();</script>
        </section>
        <?php
        return (string)ob_get_clean();
    }

    private static function visibleSubscriptions(): array {
        if (!is_user_logged_in() || !method_exists(BillingRepository::class, 'activeSubscriptionsForClients')) return [];
        $userId = get_current_user_id();
        $manager = current_user_can('routespro_manage');
        $clientIds = [];
        if (!$manager) {
            $scope = Permissions::get_scope($userId);
            foreach ((array)($scope['client_ids'] ?? []) as $clientId) {
                $clientId = absint($clientId);
                if ($clientId > 0) $clientIds[$clientId] = $clientId;
            }
            if (method_exists(BillingRepository::class, 'effectiveSubscriptionsForUser')) {
                foreach (BillingRepository::effectiveSubscriptionsForUser($userId, true) as $subscription) {
                    $clientId = absint($subscription['client_id'] ?? 0);
                    if ($clientId > 0) $clientIds[$clientId] = $clientId;
                }
            }
            if (!$clientIds) return [];
        }

        $rows = [];
        foreach (['live','sandbox'] as $environment) {
            foreach (BillingRepository::activeSubscriptionsForClients($manager ? [] : array_values($clientIds), $environment) as $row) {
                if (!$manager) {
                    $billingUserId = absint($row['billing_user_id'] ?? 0);
                    $clientId = absint($row['client_id'] ?? 0);
                    if ($billingUserId > 0 && $billingUserId !== $userId && !in_array($clientId, array_values($clientIds), true)) continue;
                }
                $key = (string)($row['environment'] ?? $environment) . ':' . absint($row['id'] ?? 0);
                $rows[$key] = $row;
            }
        }
        uasort($rows, static function(array $a, array $b): int {
            $envA = (string)($a['environment'] ?? '') === 'live' ? 0 : 1;
            $envB = (string)($b['environment'] ?? '') === 'live' ? 0 : 1;
            if ($envA !== $envB) return $envA <=> $envB;
            $priority = static fn(string $status): int => in_array($status, ['active','trial','trialing'], true) ? 0 : 1;
            $statusOrder = $priority((string)($a['status'] ?? '')) <=> $priority((string)($b['status'] ?? ''));
            if ($statusOrder !== 0) return $statusOrder;
            return strcasecmp((string)($a['client_name'] ?? ''), (string)($b['client_name'] ?? ''));
        });
        return array_values($rows);
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

    private static function loginUrl(string $token): string {
        $redirect = SignupService::resumeUrl($token, null, true);
        if (function_exists('um_get_core_page')) {
            $url = um_get_core_page('login');
            if (is_string($url) && $url !== '') return add_query_arg('redirect_to', $redirect, $url);
        }
        return wp_login_url($redirect);
    }
}
