<?php
namespace RoutesPro;

class Shortcodes {
    public static function register() {
        $map = [
            'fieldflow_my_daily_route' => 'my_daily_route',
            'fieldflow_dashboard' => 'dashboard',
            'fieldflow_route_change_form' => 'route_change_form',
            'fieldflow_front_hub' => 'front_hub',
            'fieldflow_front_routes' => 'front_routes',
            'fieldflow_front_commercial' => 'front_commercial',
            'fieldflow_app' => 'app',
            'fieldflow_expenses' => 'expenses',
            'fieldflow_briefings' => 'briefings',
            'fieldflow_client_briefings' => 'client_briefings',
            'fieldflow_implementations' => 'implementations',
            'fieldflow_client_implementations' => 'client_implementations',
            'fieldflow_stocks' => 'stocks',
            'fieldflow_shopping' => 'shopping',
            'fieldflow_shopping_results' => 'shopping_results',
            'fieldflow_client_stocks' => 'client_stocks',
            'fieldflow_route_today' => 'route_today',
            'fieldflow_discovery' => 'discovery',
            'fieldflow_report_visit' => 'report_visit',
            'fieldflow_checkin' => 'checkin',
            'fieldflow_client_portal' => 'client_portal',
            'fieldflow_client_team_mail' => 'client_team_mail',
            'fieldflow_team_inbox' => 'team_inbox',
            'fieldflow_performance_dashboard' => 'performance_dashboard',
            'fieldflow_academy' => 'academy',
            'fieldflow_billing_portal' => 'billing_portal',
            'fieldflow_subscription_signup' => 'subscription_signup',
            // aliases legados para não partir instalações atuais
            'routespro_my_daily_route' => 'my_daily_route',
            'routespro_dashboard' => 'dashboard',
            'routespro_route_change_form' => 'route_change_form',
            'routespro_front_hub' => 'front_hub',
            'routespro_front_routes' => 'front_routes',
            'routespro_front_commercial' => 'front_commercial',
            'routespro_app' => 'app',
            'routespro_expenses' => 'expenses',
            'routespro_briefings' => 'briefings',
            'routespro_client_briefings' => 'client_briefings',
            'routespro_implementations' => 'implementations',
            'routespro_client_implementations' => 'client_implementations',
            'routespro_stocks' => 'stocks',
            'routespro_shopping' => 'shopping',
            'routespro_shopping_results' => 'shopping_results',
            'routespro_client_stocks' => 'client_stocks',
            'routespro_route_today' => 'route_today',
            'routespro_discovery' => 'discovery',
            'routespro_report_visit' => 'report_visit',
            'routespro_checkin' => 'checkin',
            'routespro_client_portal' => 'client_portal',
            'routespro_client_team_mail' => 'client_team_mail',
            'routespro_team_inbox' => 'team_inbox',
            'routespro_performance_dashboard' => 'performance_dashboard',
            'routespro_academy' => 'academy',
            'routespro_billing_portal' => 'billing_portal',
            'routespro_subscription_signup' => 'subscription_signup',
        ];
        foreach ($map as $tag => $method) {
            add_shortcode($tag, static function($atts = [], $content = null, $shortcodeTag = '') use ($method) {
                if (class_exists('\RoutesPro\SaaS\EntitlementService')) {
                    $blocked = \RoutesPro\SaaS\EntitlementService::guardShortcode($method, is_array($atts) ? $atts : []);
                    if ($blocked !== '') return $blocked;
                }
                return call_user_func([self::class, $method], is_array($atts) ? $atts : []);
            });
        }
    }

    /**
     * SHORTCODE: [fieldflow_my_daily_route date="YYYY-MM-DD"]
     * - Mostra TODAS as rotas do utilizador no dia escolhido
     * - Tabs (desktop) ou dropdown (mobile) para trocar de rota
     * - Gestão de paragens: adicionar (Google Places), concluir, falha, foto, assinatura, tempos
     * - Export CSV + abrir no Google Maps
     */
    public static function my_daily_route($atts = []) {
        if (!is_user_logged_in()) return '<p>Por favor, inicia sessão.</p>';

        $date  = sanitize_text_field($atts['date'] ?? current_time('Y-m-d'));
        $mode  = sanitize_key($atts['mode'] ?? 'full');
        $is_app_mode = ($mode === 'app');
        $nonce = wp_create_nonce('wp_rest');
        $export_nonce = wp_create_nonce('routespro_export_submissions');

        // Aparência
        $ap  = \RoutesPro\Admin\Appearance::get();
        $c1  = !empty($ap['primary_transparent']) ? 'transparent' : ($ap['primary_color'] ?? '#2b6cb0');
        $c2  = !empty($ap['accent_transparent'])  ? 'transparent' : ($ap['accent_color'] ?? '#38b2ac');
        $cbg = !empty($ap['bg_transparent'])      ? 'transparent' : ($ap['bg_color'] ?? '#f7fafc');
        $ff  = $ap['font_family'] ?? 'inherit';
        $fz  = intval($ap['font_size_px'] ?? 16);

        // Provider / key
        $opts  = \RoutesPro\Admin\Settings::get();
        $gmKey = $opts['google_maps_key'] ?? '';
        $prov  = $opts['maps_provider']   ?? 'google';

        $user  = wp_get_current_user();
        $user_label = trim(($user->display_name ?: $user->user_login) . ' • ' . ($user->user_email ?: ''));
        $react_route_enabled = class_exists('\RoutesPro\Front\ReactFrontFoundation')
            && \RoutesPro\Front\ReactFrontFoundation::islandEnabled('my-route-toolbar');
        $react_route_fallback_id = 'ff-my-route-toolbar-fallback';
        $react_route_props = $react_route_enabled ? [
            'contract' => \RoutesPro\Front\ReactFrontFoundation::CONTRACT,
            'routeContract' => \RoutesPro\Front\ReactFrontFoundation::MY_ROUTE_CONTRACT,
            'island' => \RoutesPro\Front\ReactFrontFoundation::MY_ROUTE_ISLAND,
            'fallbackId' => $react_route_fallback_id,
            'routeRootId' => 'routespro-my-daily-route',
            'label' => 'Minha Rota',
        ] : [];

        ob_start(); ?>
<style>
  .rp-wrap{border:1px solid #e6e6e6;border-radius:12px;background:<?php echo esc_attr($cbg); ?>;padding:12px;font-family:<?php echo esc_attr($ff); ?>;font-size:<?php echo esc_attr($fz); ?>px}
  .rp-btn{background:<?php echo esc_attr($c1); ?>;color:#fff;border:0;border-radius:8px;padding:8px 12px;cursor:pointer}
  .rp-btn.secondary{background:<?php echo esc_attr($c2); ?>}
  .rp-btn.ghost{background:#fff;color:#333;border:1px solid #ddd}
  .rp-input,.rp-select,.rp-textarea{border:1px solid #ddd;border-radius:8px;padding:8px;width:100%}
  .rp-textarea{min-height:70px}
  .rp-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
  .rp-title{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}
  .rp-muted{opacity:.8}
  .rp-actions{display:flex;gap:6px;margin-top:6px;flex-wrap:wrap}
  .rp-chip{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #e2e8f0;font-size:12px;background:#fff}
  .rp-chip.done{background:#e6ffed;border-color:#b7f5c5}
  .rp-chip.pending{background:#fffbea;border-color:#fde68a}
  .rp-chip.failed{background:#ffeaea;border-color:#ffc4c4}

  .rp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
  .rp-route-main{display:grid;grid-template-columns:minmax(300px,420px) minmax(0,1fr);gap:20px;align-items:start}
  .rp-route-main > div{min-width:0}
  .rp-panel{margin-top:8px;border-top:1px dashed #e5e7eb;padding-top:8px}
  .rp-kv{display:grid;grid-template-columns:140px 1fr auto;gap:6px;align-items:center}
  .rp-kv label{font-size:12px;color:#555}

  .rp-list{list-style:decimal;padding-left:20px}
  .rp-list li{margin:10px 0;padding:10px;border:1px solid #eee;border-radius:10px;background:#fff}
  .rp-list li.rp-done{opacity:.9;background:#f4f8f4;border-color:#dfeee0}
  .rp-list li.rp-failed{background:#fff6f6;border-color:#ffd6d6}
  .rp-list li[draggable="true"]{cursor:grab}
  .rp-list li.rp-workspace-dragging{opacity:.45;border-color:var(--rp-primary)}
  .rp-workspace-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#f8fafc;border:1px solid #dbeafe;border-radius:12px;padding:10px;margin:10px 0}
  .rp-workspace-toolbar .rp-workspace-meta{margin-right:auto;color:#475569;font-size:12px;font-weight:700}
  .rp-workspace-history{margin-top:8px;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#fff}
  .rp-workspace-history-row{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f1f5f9}
  .rp-workspace-history-row:last-child{border-bottom:0}

  .rp-sig-wrap{border:1px dashed #bbb;border-radius:8px;padding:6px;background:#fafafa}
  .rp-sig-canvas{width:100%;height:140px;background:#fff;border:1px solid #ddd;border-radius:6px;touch-action:none}

  /* Tabs (desktop) */
  .rp-tabs{display:flex;gap:6px;overflow:auto;padding:6px;border-bottom:1px solid #e5e7eb;margin-top:8px}
  .rp-tabs.is-single{display:none}
  .rp-tab{white-space:nowrap;background:#fff;border:1px solid #e5e7eb;border-bottom-width:2px;border-radius:8px 8px 0 0;padding:6px 10px;cursor:pointer}
  .rp-tab[aria-selected="true"]{background:#fff;border-bottom-color:<?php echo esc_attr($c1); ?>;box-shadow:0 1px 0 <?php echo esc_attr($c1); ?> inset;font-weight:600}

  /* Mobile route picker (mostra no mobile, esconde tabs) */
  .rp-route-picker{display:none;margin-top:8px}
  .rp-route-picker.is-single{display:none !important}
  .rp-daily-focus{margin-top:12px;border:1px solid #c7d2fe;border-radius:18px;background:linear-gradient(135deg,#f8fafc,#eef2ff 58%,#ecfeff);padding:14px;box-shadow:0 12px 28px rgba(30,64,175,.06)}
  .rp-daily-focus[hidden]{display:none!important}
  .rp-focus-head{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
  .rp-focus-eyebrow{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900;color:#2563eb}
  .rp-focus-head h4{margin:3px 0 0;color:#0f172a;font-size:16px}
  .rp-focus-head-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px;flex-wrap:wrap}
  .rp-focus-count{display:inline-flex;align-items:center;border-radius:999px;padding:6px 9px;font-size:10px;font-weight:900;background:#fff;color:#334155;border:1px solid #dbeafe}.rp-focus-count.attention{background:#fff7ed;color:#c2410c;border-color:#fed7aa}.rp-focus-count.critical{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
  .rp-focus-toggle{display:inline-flex;align-items:center;gap:5px;border:1px solid #cbd5e1;border-radius:9px;padding:7px 9px;background:#fff;color:#0f172a;font-size:10px;font-weight:900;cursor:pointer}.rp-focus-toggle:hover{border-color:#2563eb;color:#1d4ed8}.rp-focus-toggle-icon{display:inline-block;transition:transform .16s ease}.rp-focus-toggle[aria-expanded="true"] .rp-focus-toggle-icon{transform:rotate(180deg)}
  .rp-focus-body[hidden]{display:none!important}.rp-focus-sub{font-size:11px;color:#64748b;margin-top:9px}
  .rp-focus-health{display:inline-flex;align-items:center;border-radius:999px;padding:7px 10px;font-size:10px;font-weight:900;background:#dcfce7;color:#166534}
  .rp-focus-health.active{background:#dbeafe;color:#1d4ed8}.rp-focus-health.attention{background:#ffedd5;color:#c2410c}.rp-focus-health.critical{background:#fee2e2;color:#b91c1c}
  .rp-focus-kpis{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}.rp-focus-kpi{padding:5px 8px;border-radius:999px;background:#fff;border:1px solid #dbeafe;color:#334155;font-size:10px;font-weight:800}
  .rp-focus-list{display:grid;gap:8px;margin-top:11px}.rp-focus-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:10px 11px;border-radius:13px;background:#fff;border:1px solid #e2e8f0}
  .rp-focus-item.critical{border-color:#fecaca}.rp-focus-item.active{border-color:#bfdbfe}.rp-focus-item.attention{border-color:#fed7aa}
  .rp-focus-copy strong{display:block;color:#0f172a;font-size:12px}.rp-focus-copy span{display:block;margin-top:2px;color:#334155;font-size:11px}.rp-focus-copy small{display:block;margin-top:3px;color:#64748b;font-size:10px}
  .rp-focus-action{border:1px solid #cbd5e1;border-radius:9px;padding:7px 9px;background:#fff;color:#0f172a;font-size:10px;font-weight:900;cursor:pointer;white-space:nowrap}.rp-focus-action:hover{border-color:#2563eb;color:#1d4ed8}
  @media (max-width:640px){.rp-daily-focus{padding:10px 11px;border-radius:14px}.rp-focus-head{flex-wrap:nowrap;align-items:center}.rp-focus-head>div:first-child{min-width:0;flex:1}.rp-focus-head h4{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.rp-focus-head-actions{flex-wrap:nowrap;gap:4px}.rp-focus-health{display:none}.rp-focus-count{padding:5px 7px}.rp-focus-toggle{padding:6px 7px}.rp-focus-toggle-label{display:none}.rp-focus-item{grid-template-columns:1fr}.rp-focus-action{justify-self:start}}
  .rp-periodicity{margin-top:12px;border:1px solid #dbe3ee;border-radius:16px;background:#fff;overflow:hidden;box-shadow:0 10px 26px rgba(15,23,42,.05)}
  .rp-periodicity[hidden]{display:none!important}
  .rp-periodicity summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 14px;background:linear-gradient(135deg,#f8fafc,#fff)}
  .rp-periodicity summary::-webkit-details-marker{display:none}
  .rp-periodicity-title{display:grid;gap:2px;min-width:0}
  .rp-periodicity-eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900;color:#64748b}
  .rp-periodicity-title strong{font-size:15px;color:#0f172a}
  .rp-periodicity-sub{font-size:12px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rp-periodicity-kpis{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
  .rp-periodicity-kpi{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:6px 9px;background:#f1f5f9;color:#334155;font-size:11px;font-weight:800;white-space:nowrap}
  .rp-periodicity-kpi.ok{background:#ecfdf5;color:#047857}
  .rp-periodicity-kpi.warn{background:#fff7ed;color:#c2410c}
  .rp-periodicity-kpi.bad{background:#fef2f2;color:#b91c1c}
  .rp-periodicity-kpi.justified{background:#eef2ff;color:#475569}
  .rp-periodicity-body{border-top:1px solid #e2e8f0;padding:12px}
  .rp-periodicity-toolbar{display:flex;gap:7px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
  .rp-periodicity-close{margin-left:auto;color:#64748b;font-size:11px;font-weight:800;text-align:right}
  .rp-periodicity-store{position:relative}
  .rp-periodicity-store:before{content:'';position:absolute;left:0;top:10px;bottom:10px;width:4px;border-radius:999px;background:#cbd5e1}
  .rp-periodicity-store.risk-attention:before{background:#f59e0b}
  .rp-periodicity-store.risk-critical:before{background:#ef4444}
  .rp-periodicity-store.risk-normal:before{background:#22c55e}
  .rp-periodicity-filter{border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:800;cursor:pointer}
  .rp-periodicity-filter.active{background:#0f172a;border-color:#0f172a;color:#fff}
  .rp-periodicity-list{display:grid;gap:8px;max-height:440px;overflow:auto;padding-right:2px}
  .rp-periodicity-store{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(180px,.9fr) auto;gap:12px;align-items:center;border:1px solid #e2e8f0;border-radius:14px;padding:11px 12px;background:#fff}
  .rp-periodicity-store.status-overdue,.rp-periodicity-store.status-no_visit{border-color:#fecaca;background:#fffafa}
  .rp-periodicity-store.status-attention{border-color:#fed7aa;background:#fffdf9}
  .rp-periodicity-store.status-planned{border-color:#bfdbfe;background:#fbfdff}
  .rp-periodicity-store.status-met{border-color:#bbf7d0;background:#fbfffc}
  .rp-periodicity-store.status-justified{border-color:#cbd5e1;background:#f8fafc}
  .rp-periodicity-store.status-justified:before{background:#64748b}
  .rp-periodicity-copy{min-width:0}
  .rp-periodicity-copy strong{display:block;color:#0f172a;font-size:13px;line-height:1.35}
  .rp-periodicity-copy small{display:block;margin-top:2px;color:#64748b;font-size:11px;line-height:1.35;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .rp-periodicity-progress{display:grid;gap:5px}
  .rp-periodicity-progress-top{display:flex;justify-content:space-between;gap:8px;align-items:center;color:#475569;font-size:11px;font-weight:800}
  .rp-periodicity-cadence{display:inline-flex;padding:4px 7px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:10px;font-weight:900;white-space:nowrap}
  .rp-periodicity-bar{height:7px;border-radius:999px;background:#e2e8f0;overflow:hidden}
  .rp-periodicity-bar>i{display:block;height:100%;width:0;background:#0f766e;border-radius:999px}
  .rp-periodicity-note{font-size:10px;color:#64748b}
  .rp-periodicity-statebox{justify-self:end;display:grid;gap:5px;justify-items:end}
  .rp-periodicity-state{display:inline-flex;align-items:center;border-radius:999px;padding:6px 9px;font-size:10px;font-weight:900;white-space:nowrap;background:#f1f5f9;color:#334155}
  .rp-periodicity-risk{display:inline-flex;align-items:center;border-radius:999px;padding:4px 7px;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.04em;background:#f1f5f9;color:#475569}
  .rp-periodicity-risk.risk-critical{background:#fee2e2;color:#b91c1c}
  .rp-periodicity-risk.risk-attention{background:#ffedd5;color:#c2410c}
  .rp-periodicity-risk.risk-normal{background:#dcfce7;color:#15803d}
  .rp-periodicity-plan{border:1px solid #cbd5e1;border-radius:9px;padding:6px 9px;background:#fff;color:#0f172a;font-size:10px;font-weight:900;cursor:pointer}
  .rp-periodicity-plan:hover{border-color:#0f172a;background:#f8fafc}
  .rp-periodicity-justify{border:1px solid #94a3b8;border-radius:9px;padding:6px 9px;background:#f8fafc;color:#334155;font-size:10px;font-weight:900;cursor:pointer}
  .rp-periodicity-justify:hover{border-color:#475569;background:#f1f5f9}
  .status-overdue .rp-periodicity-state,.status-no_visit .rp-periodicity-state{background:#fee2e2;color:#b91c1c}
  .status-attention .rp-periodicity-state{background:#ffedd5;color:#c2410c}
  .status-planned .rp-periodicity-state{background:#dbeafe;color:#1d4ed8}
  .status-met .rp-periodicity-state{background:#dcfce7;color:#15803d}
  .status-justified .rp-periodicity-state{background:#e2e8f0;color:#334155}
  .rp-periodicity-empty{padding:16px;border:1px dashed #cbd5e1;border-radius:12px;background:#f8fafc;color:#64748b;text-align:center;font-size:12px}
  .rp-exception-modal[hidden]{display:none!important}
  .rp-exception-modal{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:18px;background:rgba(15,23,42,.52)}
  .rp-exception-card{width:min(520px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:18px;box-shadow:0 24px 70px rgba(15,23,42,.28);padding:18px}
  .rp-exception-card h4{margin:0 0 4px;color:#0f172a;font-size:18px}
  .rp-exception-card p{margin:0 0 14px;color:#64748b;font-size:12px;line-height:1.45}
  .rp-exception-field{display:grid;gap:6px;margin-top:12px}
  .rp-exception-field label{font-size:11px;font-weight:900;color:#334155;text-transform:uppercase;letter-spacing:.04em}
  .rp-exception-field select,.rp-exception-field textarea,.rp-exception-field input{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:9px 10px;background:#fff;color:#0f172a;font:inherit}
  .rp-exception-field textarea{min-height:88px;resize:vertical}
  .rp-exception-help{margin-top:10px;padding:10px 12px;border-radius:10px;background:#f8fafc;color:#475569;font-size:11px;line-height:1.45}
  .rp-exception-actions{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;margin-top:16px}
  .rp-exception-actions button{border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;background:#fff;color:#0f172a;font-weight:900;cursor:pointer}
  .rp-exception-actions .primary{background:#0f172a;border-color:#0f172a;color:#fff}
  .rp-exception-actions .danger{margin-right:auto;color:#b91c1c;border-color:#fecaca;background:#fffafa}
  @media (max-width: 980px){.rp-route-main{grid-template-columns:1fr}.rp-periodicity-store{grid-template-columns:minmax(0,1fr) auto}.rp-periodicity-progress{grid-column:1/-1;grid-row:2}.rp-periodicity-statebox{grid-column:2;grid-row:1}}
  @media (max-width: 640px){.rp-periodicity summary{display:grid;grid-template-columns:1fr;padding:12px}.rp-periodicity-kpis{justify-content:flex-start}.rp-periodicity-kpi{padding:5px 8px}.rp-periodicity-body{padding:10px}.rp-periodicity-list{max-height:none}.rp-periodicity-store{grid-template-columns:minmax(0,1fr);gap:8px}.rp-periodicity-statebox{grid-column:1;grid-row:auto;justify-self:start}.rp-periodicity-progress{grid-column:1;grid-row:auto}.rp-periodicity-sub{white-space:normal}.rp-periodicity-toolbar{position:sticky;top:0;z-index:2;background:#fff;padding-bottom:4px}.rp-periodicity-close{width:100%;margin-left:0;text-align:left}}
  @media (max-width: 640px){
    .rp-tabs{display:none}
    .rp-route-picker{display:block}
  }
</style>

<div id="routespro-my-daily-route"
     class="rp-wrap"
     data-date="<?php echo esc_attr($date); ?>"
     data-nonce="<?php echo esc_attr($nonce); ?>"
     data-prov="<?php echo esc_attr($prov); ?>"
     data-gmkey="<?php echo esc_attr($gmKey); ?>"
     data-mode="<?php echo esc_attr($mode); ?>">
  <div class="rp-title">
    <div>
      <h3 style="margin:0">As minhas rotas</h3>
      <div class="rp-muted"><?php echo esc_html($user_label); ?></div>
    </div>
  </div>

  <?php if ($react_route_enabled): ?>
  <div class="ff-react-front-route-toolbar-root" data-fieldflow-react-front-island="my-route-toolbar" data-fieldflow-react-front-props="<?php echo esc_attr((string)wp_json_encode($react_route_props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>"></div>
  <?php endif; ?>
  <div id="<?php echo esc_attr($react_route_fallback_id); ?>" data-fieldflow-react-front-fallback="my-route-toolbar"<?php echo $react_route_enabled ? ' data-fieldflow-react-front-fallback-pending="1"' : ''; ?>>
    <div class="rp-row" role="group" aria-label="Data">
      <button class="rp-btn ghost" id="rp-prev-day" title="Ontem">◀</button>
      <input id="rp-date" class="rp-input" type="date" value="<?php echo esc_attr($date); ?>" style="max-width:160px">
      <button class="rp-btn ghost" id="rp-next-day" title="Amanhã">▶</button>
<?php if (!$is_app_mode): ?><button id="btn-export" class="rp-btn secondary">Exportar CSV</button><?php endif; ?>
    </div>

    <!-- Tabs desktop -->
    <div id="rp-tabs" class="rp-tabs" role="tablist" aria-label="Rotas do dia"></div>
    <!-- Dropdown mobile -->
    <div class="rp-route-picker">
      <label class="rp-muted" for="rp-route-select" style="display:block;margin-bottom:4px">Selecionar rota</label>
      <select id="rp-route-select" class="rp-select"></select>
    </div>
  </div>

  <section id="rp-daily-focus" class="rp-daily-focus" hidden data-contract="fieldflow-daily-operational-priority-v1" data-execution-contract="fieldflow-daily-execution-exception-control-v1">
    <div class="rp-focus-head">
      <div><span class="rp-focus-eyebrow">Prioridade operacional</span><h4 id="rp-focus-title">A organizar o dia...</h4></div>
      <div class="rp-focus-head-actions">
        <span id="rp-focus-health" class="rp-focus-health">Operação controlada</span>
        <span id="rp-focus-count" class="rp-focus-count">0 alertas</span>
        <button type="button" id="rp-focus-toggle" class="rp-focus-toggle" aria-expanded="true" aria-controls="rp-focus-body"><span class="rp-focus-toggle-label">Fechar</span><span class="rp-focus-toggle-icon">⌃</span></button>
      </div>
    </div>
    <div id="rp-focus-body" class="rp-focus-body">
      <div id="rp-focus-sub" class="rp-focus-sub"></div>
      <div id="rp-focus-kpis" class="rp-focus-kpis"></div>
      <div id="rp-focus-list" class="rp-focus-list"></div>
    </div>
  </section>

  <details id="rp-periodicity" class="rp-periodicity" hidden>
    <summary>
      <div class="rp-periodicity-title">
        <span class="rp-periodicity-eyebrow">Cobertura do mês</span>
        <strong id="rp-periodicity-title">A calcular periodicidade...</strong>
        <span id="rp-periodicity-sub" class="rp-periodicity-sub"></span>
      </div>
      <div class="rp-periodicity-kpis" aria-label="Resumo da cobertura mensal">
        <span id="rp-periodicity-met" class="rp-periodicity-kpi ok">0 cumpridas</span>
        <span id="rp-periodicity-attention" class="rp-periodicity-kpi warn">0 por resolver</span>
        <span id="rp-periodicity-no-visit" class="rp-periodicity-kpi">0 sem visita</span>
        <span id="rp-periodicity-justified" class="rp-periodicity-kpi justified">0 justificadas</span>
      </div>
    </summary>
    <div class="rp-periodicity-body">
      <div class="rp-periodicity-toolbar" role="group" aria-label="Filtrar cobertura mensal">
        <button type="button" class="rp-periodicity-filter active" data-periodicity-filter="attention">Só falta cumprir</button>
        <button type="button" class="rp-periodicity-filter" data-periodicity-filter="no_visit">Sem visita</button>
        <button type="button" class="rp-periodicity-filter" data-periodicity-filter="justified">Justificadas</button>
        <button type="button" class="rp-periodicity-filter" data-periodicity-filter="all">Todas</button>
        <span id="rp-periodicity-close" class="rp-periodicity-close"></span>
      </div>
      <div id="rp-periodicity-list" class="rp-periodicity-list"></div>
    </div>
  </details>

  <div id="rp-visit-exception-modal" class="rp-exception-modal" hidden aria-hidden="true">
    <div class="rp-exception-card" role="dialog" aria-modal="true" aria-labelledby="rp-exception-title">
      <h4 id="rp-exception-title">Justificar não visita</h4>
      <p id="rp-exception-store">Seleciona o motivo operacional desta exceção.</p>
      <div class="rp-exception-field">
        <label for="rp-exception-reason">Motivo</label>
        <select id="rp-exception-reason">
          <option value="">Selecionar...</option>
          <option value="works">Obras</option>
          <option value="temporarily_closed">Encerramento temporário</option>
          <option value="permanently_closed">Encerramento definitivo</option>
          <option value="access_blocked">Loja inacessível / sem acesso</option>
          <option value="store_refusal">Recusa da loja</option>
          <option value="client_request">Pedido do cliente</option>
          <option value="operational_change">Alteração operacional</option>
          <option value="other">Outro</option>
        </select>
      </div>
      <div class="rp-exception-field">
        <label for="rp-exception-note">Observação</label>
        <textarea id="rp-exception-note" placeholder="Ex.: Loja em obras, reabertura prevista para meados de outubro."></textarea>
      </div>
      <div class="rp-exception-field">
        <label for="rp-exception-until">Válida até</label>
        <input id="rp-exception-until" type="date">
      </div>
      <div class="rp-exception-help">A justificação retira a loja do estado crítico, mas não conta como visita realizada e não altera a periodicidade P1, P2, P3 ou P4. Se a validade terminar, a loja volta automaticamente a exigir atenção.</div>
      <div class="rp-exception-actions">
        <button type="button" id="rp-exception-clear" class="danger" hidden>Reabrir / remover justificação</button>
        <button type="button" data-exception-close>Cancelar</button>
        <button type="button" id="rp-exception-save" class="primary">Guardar justificação</button>
      </div>
    </div>
  </div>

  <div id="rp-content" style="margin-top:12px"></div>
</div>

<script>
(function(){
  const root     = document.getElementById('routespro-my-daily-route');
  const content  = document.getElementById('rp-content');
  const tabsBox  = document.getElementById('rp-tabs');
  const selRoute = document.getElementById('rp-route-select');
  const inpDate  = document.getElementById('rp-date');

  const nonce   = root.dataset.nonce;
  const apiBase = '<?php echo esc_url( rest_url('routespro/v1/') ); ?>';

  const mapsProv = (root.dataset.prov || 'google').toLowerCase();
  const gmKey    = root.dataset.gmkey || '';
  const isAppMode = (root.dataset.mode || '') === 'app';
  let googleReady = false;

  // state
  let routesCache = [];   // [{id,date,status, ...}]
  let activeRouteId = null;
  let activeRouteProjectId = 0;
  let periodicityData = null;
  const periodicityFilterStorageKey = 'fieldflow:my-route:periodicity-filter:v1';
  let periodicityFilter = 'attention';
  try { const stored=sessionStorage.getItem(periodicityFilterStorageKey)||''; if(['attention','no_visit','justified','all'].includes(stored)) periodicityFilter=stored; } catch(_){}
  const periodicityCache = new Map();
  const priorityCache = new Map();
  const executionCache = new Map();
  let priorityData = null;
  let executionData = null;
  const focusOpenStorageKey = 'fieldflow:my-route:daily-focus-open:v1';
  let focusOpen = !(window.matchMedia && window.matchMedia('(max-width:640px)').matches);
  try { const stored=sessionStorage.getItem(focusOpenStorageKey); if(stored==='1'||stored==='0') focusOpen=stored==='1'; } catch(_){}

  const focusRoot = document.getElementById('rp-daily-focus');
  const focusTitle = document.getElementById('rp-focus-title');
  const focusSub = document.getElementById('rp-focus-sub');
  const focusHealth = document.getElementById('rp-focus-health');
  const focusCount = document.getElementById('rp-focus-count');
  const focusToggle = document.getElementById('rp-focus-toggle');
  const focusBody = document.getElementById('rp-focus-body');
  const focusKpis = document.getElementById('rp-focus-kpis');
  const focusList = document.getElementById('rp-focus-list');
  const periodicityRoot = document.getElementById('rp-periodicity');
  const periodicityTitle = document.getElementById('rp-periodicity-title');
  const periodicitySub = document.getElementById('rp-periodicity-sub');
  const periodicityMet = document.getElementById('rp-periodicity-met');
  const periodicityAttention = document.getElementById('rp-periodicity-attention');
  const periodicityNoVisit = document.getElementById('rp-periodicity-no-visit');
  const periodicityJustified = document.getElementById('rp-periodicity-justified');
  const periodicityClose = document.getElementById('rp-periodicity-close');
  const periodicityList = document.getElementById('rp-periodicity-list');
  const exceptionModal = document.getElementById('rp-visit-exception-modal');
  const exceptionStore = document.getElementById('rp-exception-store');
  const exceptionReason = document.getElementById('rp-exception-reason');
  const exceptionNote = document.getElementById('rp-exception-note');
  const exceptionUntil = document.getElementById('rp-exception-until');
  const exceptionClear = document.getElementById('rp-exception-clear');
  const exceptionSave = document.getElementById('rp-exception-save');
  let exceptionLinkId = 0;

  function esc(s){ return (s==null?'':String(s)).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  async function j(url, opts){
    const res = await fetch(url, Object.assign({credentials:'same-origin', cache:'no-store', headers:{'X-WP-Nonce':nonce}}, opts||{}));
    const text = await res.text();
    let jj = {};
    try { jj = text ? JSON.parse(text) : {}; } catch(_){ jj = { message: text }; }
    if(!res.ok) throw new Error(jj.message||res.statusText||'Request failed');
    return jj;
  }
  const localTimeHHMM = () => { const d=new Date(); return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); };
  const joinDateTimeToISO = (dateYYYYMMDD, timeHHMM) => { if (!timeHHMM) return null; const dt = new Date(`${dateYYYYMMDD}T${timeHHMM}`); return isNaN(dt.getTime()) ? null : dt.toISOString(); };

  function fmtDate(d){ return new Date(d).toISOString().slice(0,10); }
  function monthKey(dateStr){
    const value=String(dateStr||'').trim();
    return /^\d{4}-\d{2}/.test(value) ? value.slice(0,7) : fmtDate(new Date()).slice(0,7);
  }
  function monthLabel(month){
    const d=new Date(String(month||'')+'-01T12:00:00');
    if(Number.isNaN(d.getTime())) return String(month||'');
    const label=new Intl.DateTimeFormat('pt-PT',{month:'long',year:'numeric'}).format(d);
    return label ? label.charAt(0).toUpperCase()+label.slice(1) : String(month||'');
  }
  function shortDate(value){
    const d=new Date(String(value||'')+'T12:00:00');
    if(Number.isNaN(d.getTime())) return '';
    return new Intl.DateTimeFormat('pt-PT',{day:'2-digit',month:'2-digit'}).format(d);
  }
  function asInt(value){ const n=parseInt(value||0,10); return Number.isFinite(n)?n:0; }
  function asNum(value){ const n=Number(value||0); return Number.isFinite(n)?n:0; }
  function setDailyFocusOpen(open,persist=true){
    focusOpen=!!open;
    if(focusBody) focusBody.hidden=!focusOpen;
    if(focusToggle){
      focusToggle.setAttribute('aria-expanded',focusOpen?'true':'false');
      const label=focusToggle.querySelector('.rp-focus-toggle-label'); if(label) label.textContent=focusOpen?'Fechar':'Abrir';
      const icon=focusToggle.querySelector('.rp-focus-toggle-icon'); if(icon) icon.textContent='⌃';
    }
    if(persist){ try{sessionStorage.setItem(focusOpenStorageKey,focusOpen?'1':'0');}catch(_){} }
  }
  if(focusToggle) focusToggle.addEventListener('click',()=>setDailyFocusOpen(!focusOpen,true));
  setDailyFocusOpen(focusOpen,false);

  function renderDailyFocus(data){
    priorityData=data||priorityData||null;
    if(!focusRoot || !focusList) return;
    const summary=priorityData?.summary||{};
    const items=Array.isArray(priorityData?.priorities)?priorityData.priorities:[];
    const executionSummary=executionData?.summary||{};
    const exceptions=Array.isArray(executionData?.exceptions)?executionData.exceptions:[];
    const health=priorityData?.health||{};
    const seenStops=new Set();
    const merged=[];
    exceptions.forEach(item=>{ const sid=asInt(item.stop_id); if(sid) seenStops.add(sid); merged.push({...item,kind:'execution_exception'}); });
    items.forEach(item=>{ const sid=asInt(item.stop_id); if(sid && seenStops.has(sid)) return; merged.push(item); });
    focusRoot.hidden=false;
    const alertCount=exceptions.length;
    if(focusCount){
      focusCount.textContent=alertCount>0?(alertCount===1?'1 alerta':(alertCount+' alertas')):(items.length===1?'1 ação':(items.length+' ações'));
      focusCount.className='rp-focus-count '+(asInt(executionSummary.critical_exceptions)>0?'critical':(alertCount>0?'attention':''));
    }
    if(focusTitle){
      if(alertCount>0) focusTitle.textContent=alertCount===1?'1 alerta operacional por resolver':(alertCount+' alertas operacionais por resolver');
      else focusTitle.textContent=items.length?(items[0]?.title||'Prioridades do dia'):'Sem prioridades críticas para hoje';
    }
    if(focusSub){
      const first=exceptions[0]||items[0]||null;
      focusSub.textContent=first?((first.store_name||'Loja')+(first.reason?' · '+first.reason:'')):'As visitas e a cobertura mensal não têm ações urgentes neste momento.';
    }
    if(focusHealth){
      let level=['clear','active','attention','critical'].includes(String(health.level||''))?String(health.level):'clear';
      let label=String(health.label||'Operação controlada');
      if(asInt(executionSummary.critical_exceptions)>0){ level='critical'; label='Exceções críticas'; }
      else if(alertCount>0 && level!=='critical'){ level='attention'; label='Há alertas por resolver'; }
      focusHealth.className='rp-focus-health '+(level==='clear'?'':level);
      focusHealth.textContent=label;
    }
    if(focusKpis){
      const kpis=[];
      const completed=(executionData?asInt(executionSummary.completed):asInt(summary.stops_completed));
      const pending=(executionData?asInt(executionSummary.pending):asInt(summary.stops_pending));
      kpis.push('<span class="rp-focus-kpi">'+completed+' concluídas hoje</span>');
      if(asInt(executionSummary.in_progress)>0) kpis.push('<span class="rp-focus-kpi">'+asInt(executionSummary.in_progress)+' em curso</span>');
      if(pending>0) kpis.push('<span class="rp-focus-kpi">'+pending+' pendentes hoje</span>');
      if(alertCount>0) kpis.push('<span class="rp-focus-kpi">'+alertCount+' alertas de execução</span>');
      if(asInt(summary.critical_unresolved)>0) kpis.push('<span class="rp-focus-kpi">'+asInt(summary.critical_unresolved)+' críticas no mês</span>');
      if(asInt(summary.stops_justified)>0) kpis.push('<span class="rp-focus-kpi">'+asInt(summary.stops_justified)+' justificadas hoje</span>');
      focusKpis.innerHTML=kpis.join('');
    }
    if(!merged.length){ focusList.innerHTML=''; return; }
    focusList.innerHTML=merged.slice(0,8).map(item=>{
      const severity=['critical','active','attention'].includes(String(item.severity||''))?String(item.severity):'attention';
      const meta=[item.project_name,item.client_name].filter(Boolean).join(' · ');
      const action=String(item.action||'');
      const attrs=action==='open_route'?' data-focus-route="'+asInt(item.route_id)+'"':(action==='open_planner'?' data-focus-plan="'+asInt(item.location_id)+'" data-project-id="'+asInt(item.project_id)+'" data-client-id="'+asInt(item.client_id)+'"':(action==='review_justification'?' data-focus-justify="'+asInt(item.link_id)+'"':''));
      return '<article class="rp-focus-item '+severity+'"><div class="rp-focus-copy"><strong>'+esc(item.store_name||item.title||'Prioridade')+'</strong><span>'+esc(item.title||item.label||'')+'</span><small>'+esc((meta?meta+' · ':'')+(item.reason||''))+'</small></div>'+(attrs?'<button type="button" class="rp-focus-action"'+attrs+'>'+esc(item.action_label||'Abrir')+'</button>':'')+'</article>';
    }).join('');
    focusList.querySelectorAll('[data-focus-route]').forEach(btn=>btn.addEventListener('click',()=>{
      const rid=asInt(btn.dataset.focusRoute); if(!rid) return;
      if(routesCache.some(route=>asInt(route.id)===rid)){ activeRouteId=rid; updateActiveTab(); syncMobilePicker(); refetchAndRender(rid).then(()=>content?.scrollIntoView({behavior:'smooth',block:'start'})).catch(()=>{}); }
    }));
    focusList.querySelectorAll('[data-focus-plan]').forEach(btn=>btn.addEventListener('click',()=>{
      const intent={contract:'fieldflow-my-route-periodicity-action-ux-v1',location_id:asInt(btn.dataset.focusPlan),project_id:asInt(btn.dataset.projectId),client_id:asInt(btn.dataset.clientId),month:monthKey(inpDate?.value||''),source:'daily-operational-priority',created_at:Date.now()};
      try{sessionStorage.setItem('fieldflow:planner-intent:v1',JSON.stringify(intent));}catch(_){}
      document.dispatchEvent(new CustomEvent('fieldflow:open-planner',{detail:intent}));
    }));
    focusList.querySelectorAll('[data-focus-justify]').forEach(btn=>btn.addEventListener('click',()=>{
      const linkId=asInt(btn.dataset.focusJustify);
      const row=(Array.isArray(periodicityData?.rows)?periodicityData.rows:[]).find(item=>asInt(item.link_id)===linkId);
      if(row) openVisitException(row); else if(periodicityRoot){ periodicityRoot.open=true; periodicityRoot.scrollIntoView({behavior:'smooth',block:'start'}); }
    }));
  }
  async function loadDailyExecution(dateStr,projectId=0,force=false){
    const key=String(dateStr||'')+'|'+String(asInt(projectId));
    if(!force && executionCache.has(key)){ executionData=executionCache.get(key); renderDailyFocus(priorityData||{}); return executionData; }
    const url=apiBase+'my-execution?date='+encodeURIComponent(dateStr)+(asInt(projectId)>0?'&project_id='+asInt(projectId):'');
    const data=await j(url);
    executionCache.set(key,data); executionData=data; renderDailyFocus(priorityData||{}); return data;
  }
  async function loadDailyFocus(dateStr,projectId=0,force=false){
    const key=String(dateStr||'')+'|'+String(asInt(projectId));
    if(!force && priorityCache.has(key)){ priorityData=priorityCache.get(key); executionData=executionCache.get(key)||null; renderDailyFocus(priorityData); await loadDailyExecution(dateStr,projectId,force); return priorityData; }
    const url=apiBase+'my-priorities?date='+encodeURIComponent(dateStr)+(asInt(projectId)>0?'&project_id='+asInt(projectId):'');
    const data=await j(url);
    priorityCache.set(key,data); priorityData=data; executionData=executionCache.get(key)||null; renderDailyFocus(data); await loadDailyExecution(dateStr,projectId,force); return data;
  }

  function periodicityRows(){
    const rows=Array.isArray(periodicityData?.rows)?periodicityData.rows:[];
    if(periodicityFilter==='all') return rows;
    if(periodicityFilter==='justified') return rows.filter(row=>String(row.status||'')==='justified');
    if(periodicityFilter==='no_visit') return rows.filter(row=>asInt(row.completed_visits)===0 && String(row.status||'')!=='justified' && String(periodicityData?.month_relation||'')!=='future');
    return rows.filter(row=>['overdue','no_visit','attention'].includes(String(row.status||'')));
  }
  function renderPeriodicity(data){
    periodicityData=data||null;
    if(!periodicityRoot || !periodicityList) return;
    const summary=data?.summary||{};
    const total=asInt(summary.stores_total);
    if(!data || total<=0){ periodicityRoot.hidden=true; periodicityList.innerHTML=''; return; }

    periodicityRoot.hidden=false;
    const projectName=String(data?.scope?.project_name||'').trim();
    const clientName=String(data?.scope?.client_name||'').trim();
    const completed=asInt(summary.visits_completed);
    const required=asInt(summary.visits_required);
    const projected=Math.round(asNum(summary.projected_coverage_percent));
    if(periodicityTitle) periodicityTitle.textContent=monthLabel(data.month);
    if(periodicitySub){
      const scope=[projectName,clientName].filter(Boolean).join(' · ');
      const days=asInt(summary.business_days_remaining);
      const relation=String(data?.month_relation||'current');
      const dayCopy=relation==='past'?'mês fechado':(relation==='future'?days+' dias úteis no mês':days+' dias úteis restantes');
      periodicitySub.textContent=(scope?scope+' · ':'')+completed+'/'+required+' visitas realizadas · '+projected+'% cobertas com plano · '+dayCopy;
    }
    if(periodicityMet) periodicityMet.textContent=asInt(summary.stores_met)+' cumpridas';
    if(periodicityAttention){
      const pending=asInt(summary.needs_attention);
      periodicityAttention.textContent=pending+' por resolver';
      periodicityAttention.className='rp-periodicity-kpi '+(pending>0?'warn':'ok');
    }
    if(periodicityNoVisit){
      const noVisit=asInt(summary.without_visit);
      periodicityNoVisit.textContent=noVisit+' sem visita';
      periodicityNoVisit.className='rp-periodicity-kpi '+(noVisit>0?'bad':'ok');
    }
    if(periodicityJustified){
      const justified=asInt(summary.justified_stores);
      periodicityJustified.textContent=justified+' justificada'+(justified===1?'':'s');
      periodicityJustified.className='rp-periodicity-kpi justified';
    }
    if(periodicityClose){
      const covered=asInt(summary.stores_covered);
      const pending=asInt(summary.needs_attention);
      const justified=asInt(summary.justified_stores);
      periodicityClose.textContent=covered+'/'+total+' lojas cobertas'+(pending>0?' · '+pending+' por resolver':' · sem críticos')+(justified>0?' · '+justified+' justificada'+(justified===1?'':'s'):'');
    }

    periodicityRoot.querySelectorAll('[data-periodicity-filter]').forEach(btn=>{
      btn.classList.toggle('active', String(btn.dataset.periodicityFilter||'')===periodicityFilter);
    });
    const rows=periodicityRows();
    if(!rows.length){
      const message=periodicityFilter==='all'?'Sem lojas atribuídas neste âmbito.':(periodicityFilter==='no_visit'?'Boa notícia: não há lojas sem visita não justificada neste mês.':(periodicityFilter==='justified'?'Não existem não visitas justificadas neste mês.':'Periodicidades cobertas. Não tens lojas por resolver neste momento.'));
      periodicityList.innerHTML='<div class="rp-periodicity-empty">'+esc(message)+'</div>';
      return;
    }
    periodicityList.innerHTML=rows.map(row=>{
      const rawStatus=String(row.status||'attention');
      const status=['overdue','no_visit','attention','justified','planned','met'].includes(rawStatus)?rawStatus:'attention';
      const done=asInt(row.completed_visits);
      const need=Math.max(1,asInt(row.required_visits));
      const planned=asInt(row.planned_visits);
      const missing=asInt(row.remaining_after_plan);
      const unresolved=asInt(row.remaining_after_justification ?? row.remaining_after_plan);
      const outstanding=asInt(row.remaining_visits);
      const overdue=asInt(row.overdue_slots);
      const pct=Math.max(0,Math.min(100,asNum(row.coverage_percent)));
      const notes=[];
      notes.push(done+'/'+need+' realizadas');
      if(planned>0) notes.push('+'+planned+' planeada'+(planned===1?'':'s'));
      if(unresolved>0) notes.push('falta garantir '+unresolved);
      if(overdue>0) notes.push(overdue+' em atraso');
      if(status==='justified'){
        const reason=String(row.justification_reason||row.exception?.reason_label||'Não visita justificada');
        notes.push(reason);
        if(row.exception?.valid_until) notes.push('até '+shortDate(row.exception.valid_until));
      }
      if(row.next_planned_date) notes.push('próxima '+shortDate(row.next_planned_date));
      const storeMeta=[row.project_name,row.city].filter(Boolean).join(' · ');
      const riskRaw=String(row.risk_level||'normal');
      const risk=['critical','attention','normal'].includes(riskRaw)?riskRaw:'normal';
      const relation=String(data?.month_relation||'current');
      const canPlan=missing>0 && relation!=='past';
      const planAction=canPlan?'<button type="button" class="rp-periodicity-plan" data-periodicity-plan="'+asInt(row.location_id)+'" data-project-id="'+asInt(row.project_id)+'" data-client-id="'+asInt(row.client_id)+'">Planear</button>':'';
      const canJustify=outstanding>0 && relation!=='future' && ['overdue','no_visit','attention','justified'].includes(status);
      const justifyAction=canJustify?'<button type="button" class="rp-periodicity-justify" data-periodicity-justify="'+asInt(row.link_id)+'">'+(status==='justified'?'Editar motivo':'Justificar')+'</button>':'';
      return '<article class="rp-periodicity-store status-'+status+' risk-'+risk+'">'+
        '<div class="rp-periodicity-copy"><strong>'+esc(row.name||'Loja')+'</strong><small>'+esc(storeMeta)+'</small></div>'+
        '<div class="rp-periodicity-progress">'+
          '<div class="rp-periodicity-progress-top"><span class="rp-periodicity-cadence">'+esc(row.periodicity_label||'Periodicidade')+'</span><span>'+done+'/'+need+'</span></div>'+
          '<div class="rp-periodicity-bar"><i style="width:'+pct+'%"></i></div>'+
          '<div class="rp-periodicity-note">'+esc(notes.join(' · '))+'</div>'+
        '</div>'+
        '<div class="rp-periodicity-statebox"><span class="rp-periodicity-state">'+esc(row.status_label||'A acompanhar')+'</span><span class="rp-periodicity-risk risk-'+risk+'">'+esc(row.risk_label||'Normal')+'</span>'+planAction+justifyAction+'</div>'+
      '</article>';
    }).join('');
    periodicityList.querySelectorAll('[data-periodicity-plan]').forEach(btn=>btn.addEventListener('click',event=>{
      event.preventDefault();
      event.stopPropagation();
      const intent={contract:'fieldflow-my-route-periodicity-action-ux-v1',location_id:asInt(btn.dataset.periodicityPlan),project_id:asInt(btn.dataset.projectId),client_id:asInt(btn.dataset.clientId),month:String(data?.month||monthKey(inpDate?.value||'')),source:'my-route-periodicity',created_at:Date.now()};
      try{sessionStorage.setItem('fieldflow:planner-intent:v1',JSON.stringify(intent));}catch(_){}
      document.dispatchEvent(new CustomEvent('fieldflow:open-planner',{detail:intent}));
    }));
    periodicityList.querySelectorAll('[data-periodicity-justify]').forEach(btn=>btn.addEventListener('click',event=>{
      event.preventDefault();
      event.stopPropagation();
      const linkId=asInt(btn.dataset.periodicityJustify);
      const row=(Array.isArray(periodicityData?.rows)?periodicityData.rows:[]).find(item=>asInt(item.link_id)===linkId);
      openVisitException(row||null);
    }));
  }
  function closeVisitException(){
    if(!exceptionModal) return;
    exceptionModal.hidden=true;
    exceptionModal.setAttribute('aria-hidden','true');
    exceptionLinkId=0;
  }
  function openVisitException(row){
    if(!exceptionModal || !row) return;
    exceptionLinkId=asInt(row.link_id);
    const existing=row.exception&&typeof row.exception==='object'?row.exception:null;
    if(exceptionStore) exceptionStore.textContent=String(row.name||'Loja')+(row.city?' · '+row.city:'');
    if(exceptionReason) exceptionReason.value=String(existing?.reason||'');
    if(exceptionNote) exceptionNote.value=String(existing?.note||'');
    if(exceptionUntil) exceptionUntil.value=String(existing?.valid_until||'');
    if(exceptionClear) exceptionClear.hidden=!existing;
    exceptionModal.hidden=false;
    exceptionModal.setAttribute('aria-hidden','false');
    setTimeout(()=>{ try{exceptionReason?.focus();}catch(_){} },20);
  }
  async function refreshPeriodicityAfterException(){
    periodicityCache.clear();
    await loadPeriodicity(inpDate?.value||fmtDate(new Date()), activeRouteProjectId||0, true);
  }
  if(exceptionModal){
    exceptionModal.querySelectorAll('[data-exception-close]').forEach(btn=>btn.addEventListener('click',closeVisitException));
    exceptionModal.addEventListener('click',event=>{ if(event.target===exceptionModal) closeVisitException(); });
    document.addEventListener('keydown',event=>{ if(event.key==='Escape' && !exceptionModal.hidden) closeVisitException(); });
  }
  if(exceptionSave) exceptionSave.addEventListener('click',async()=>{
    if(exceptionLinkId<=0) return;
    const reason=String(exceptionReason?.value||'').trim();
    if(!reason){ alert('Seleciona o motivo da não visita.'); return; }
    exceptionSave.disabled=true;
    try{
      const month=String(periodicityData?.month||monthKey(inpDate?.value||''));
      const payload={
        link_id:exceptionLinkId,
        reason,
        note:String(exceptionNote?.value||'').trim(),
        valid_from:String(periodicityData?.month_start||month+'-01'),
        valid_until:String(exceptionUntil?.value||''),
        month
      };
      await j(apiBase+'visit-exceptions',{method:'POST',headers:{'X-WP-Nonce':nonce,'Content-Type':'application/json'},body:JSON.stringify(payload)});
      closeVisitException();
      await refreshPeriodicityAfterException();
    }catch(err){ alert(err?.message||'Não foi possível guardar a justificação.'); }
    finally{ exceptionSave.disabled=false; }
  });
  if(exceptionClear) exceptionClear.addEventListener('click',async()=>{
    if(exceptionLinkId<=0) return;
    if(!confirm('Remover esta justificação e voltar a colocar a loja sob acompanhamento normal?')) return;
    exceptionClear.disabled=true;
    try{
      await j(apiBase+'visit-exceptions/'+exceptionLinkId,{method:'DELETE'});
      closeVisitException();
      await refreshPeriodicityAfterException();
    }catch(err){ alert(err?.message||'Não foi possível remover a justificação.'); }
    finally{ exceptionClear.disabled=false; }
  });

  async function loadPeriodicity(dateStr, projectId=0, force=false){
    if(!periodicityRoot) return null;
    const month=monthKey(dateStr);
    const project=Math.max(0,asInt(projectId));
    const cacheKey=month+'|'+project;
    if(!force && periodicityCache.has(cacheKey)){
      const cached=periodicityCache.get(cacheKey);
      renderPeriodicity(cached);
      return cached;
    }
    const url=apiBase+'my-periodicity?month='+encodeURIComponent(month)+(project>0?'&project_id='+project:'');
    try{
      const data=await j(url);
      periodicityCache.set(cacheKey,data);
      renderPeriodicity(data);
      return data;
    }catch(_){
      periodicityRoot.hidden=true;
      return null;
    }
  }
  if(periodicityRoot){
    periodicityRoot.querySelectorAll('[data-periodicity-filter]').forEach(btn=>btn.addEventListener('click', event=>{
      event.preventDefault();
      event.stopPropagation();
      periodicityFilter=String(btn.dataset.periodicityFilter||'attention');
      try{sessionStorage.setItem(periodicityFilterStorageKey,periodicityFilter);}catch(_){}
      renderPeriodicity(periodicityData);
    }));
  }

  // geolocalização
  function getGeo(){
    return new Promise((resolve)=> {
      if (!('geolocation' in navigator)) return resolve(null);
      navigator.geolocation.getCurrentPosition(
        p=> resolve({lat: p.coords.latitude, lng: p.coords.longitude}),
        _=> resolve(null),
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
      );
    });
  }

  // Google Places
  function loadGoogle(cb){
    if (!gmKey) { cb && cb(false); return; }
    if (window.google && google.maps && google.maps.places && google.maps.places.Autocomplete) {
      googleReady = true;
      cb && cb(true);
      return;
    }
    if (window.FieldFlowGoogleMaps && typeof window.FieldFlowGoogleMaps.load === 'function') {
      window.FieldFlowGoogleMaps.load({key:gmKey,libraries:['places'],language:'pt-PT',region:'PT'})
        .then((ok)=>{ googleReady = !!ok; cb && cb(!!ok); })
        .catch(()=>{ cb && cb(false); });
      return;
    }
    const existing=document.querySelector('script[data-routespro-google="1"],script[src*="maps.googleapis.com/maps/api/js"]');
    if(existing){
      let tries=0;
      const wait=()=>{ tries++; if(window.google&&google.maps&&google.maps.places&&google.maps.places.Autocomplete){ googleReady=true; cb&&cb(true); return; } if(window.google&&google.maps&&typeof google.maps.importLibrary==='function'){ google.maps.importLibrary('places').then(()=>{googleReady=!!(google.maps.places&&google.maps.places.Autocomplete);cb&&cb(googleReady);}).catch(()=>cb&&cb(false)); return; } if(tries>=100){cb&&cb(false);return;} setTimeout(wait,120); };
      wait();
      return;
    }
    const callbackName='__fieldflowDailyRouteGoogleReady_'+Math.random().toString(36).slice(2);
    window[callbackName]=()=>{try{delete window[callbackName];}catch(_){window[callbackName]=undefined;}googleReady=!!(window.google&&google.maps&&google.maps.places&&google.maps.places.Autocomplete);cb&&cb(googleReady);};
    const script=document.createElement('script');
    script.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(gmKey)+'&libraries=places&loading=async&callback='+callbackName;
    script.async=true;script.defer=true;script.dataset.routesproGoogle='1';script.onerror=()=>{try{delete window[callbackName];}catch(_){window[callbackName]=undefined;}cb&&cb(false);};
    document.head.appendChild(script);
  }
  function makeAutocomplete(input){
    if (!googleReady || !window.google || !google.maps?.places) return null;
    return new google.maps.places.Autocomplete(input, { fields:['formatted_address','geometry','name'] });
  }
  function geocodeAddress(addr){
    return new Promise((resolve)=>{
      if (!googleReady || !addr) return resolve(null);
      const geocoder = new google.maps.Geocoder();
      geocoder.geocode({ address: addr }, (res, status)=>{
        if (status === 'OK' && res && res[0] && res[0].geometry) {
          const g = res[0].geometry.location;
          resolve({ lat:g.lat(), lng:g.lng(), formatted: res[0].formatted_address });
        } else resolve(null);
      });
    });
  }
  function isNum(n){ return Number.isFinite(n) && !Number.isNaN(n); }

  // Media upload (foto/prova) -> WP Media Library
  async function uploadMedia(file){
    const endpoint = '<?php echo esc_url( rest_url('wp/v2/media') ); ?>';
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'X-WP-Nonce': nonce,
        'Content-Disposition': 'attachment; filename="'+encodeURIComponent(file.name||('foto-'+Date.now()+'.jpg'))+'"'
      },
      body: file
    });
    const dataText = await res.text();
    let data = {};
    try{ data = dataText ? JSON.parse(dataText) : {}; }catch(_){ data = {}; }
    if(!res.ok) throw new Error(data.message || 'Falha no upload');
    return data.source_url || data.guid?.rendered || '';
  }

  // Helpers para criar localização e paragem
  async function ensureLoc(address, lat, lng, client_id, project_id){
    if (!isNum(lat) || !isNum(lng)) throw new Error('Sem coordenadas válidas.');
    const r = await fetch(apiBase+'locations', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
      body: JSON.stringify({
        client_id: parseInt(client_id||0,10) || 0,
        project_id: project_id ? parseInt(project_id,10) : null,
        name: address, address, lat, lng
      })
    });
    const text = await r.text();
    let jx = {};
    try{ jx = text ? JSON.parse(text) : {}; }catch(_){ jx = { message:text }; }
    if(!r.ok) throw new Error(jx.message || 'Falha a criar localização');
    if(!jx.id) throw new Error('Resposta sem id da localização');
    return jx.id;
  }
  async function createStop(routeId, locationId, seq){
    if (!routeId || !locationId) throw new Error('route_id/location_id obrigatórios');
    const r = await fetch(apiBase+'stops', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
      body: JSON.stringify({ route_id: parseInt(routeId,10), location_id: parseInt(locationId,10), seq: parseInt(seq||0,10) })
    });
    if(!r.ok){ const t = await r.text(); throw new Error(t || 'Falha a criar paragem'); }
    return true;
  }
  async function patchStop(id, payload){
    return j(apiBase+'stops/'+id, {
      method:'PATCH',
      headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
      body: JSON.stringify(payload || {})
    });
  }

  async function refetchAndRender(routeId, options={}){
    const det = await j(apiBase+'routes/'+routeId+'?v='+Date.now());
    activeRouteProjectId=Math.max(0,asInt(det.project_id));
    renderRoute(det);
    loadPeriodicity(inpDate.value||det.date||'', activeRouteProjectId, !!options.forcePeriodicity).catch(()=>{});
    loadDailyFocus(inpDate.value||det.date||'', activeRouteProjectId, !!options.forcePeriodicity).catch(()=>{});
  }

  function renderEmpty(dateStr){
    tabsBox.innerHTML = '';
    selRoute.innerHTML = '';
    content.innerHTML =
      '<h3>Não tens rota atribuída para '+esc(dateStr)+'</h3>' +
      '<p class="rp-muted">Se a rota foi criada no BO, confirma a atribuição a este utilizador.</p>';
  }

  // Google Maps Directions URL. Never promote edge PDVs to missing endpoints.
  function buildGMapsUrlFromRoute(route,stops){
    if (!route || !Array.isArray(stops) || !stops.length) return null;
    const toPoint = (s) => {
      const lat = parseFloat(s?.lat);
      const lng = parseFloat(s?.lng);
      if (Number.isFinite(lat) && Number.isFinite(lng)) return lat + ',' + lng;
      return String(s?.address||s?.formatted_address||s?.location_name||s?.name||'').trim();
    };
    const meta=route.meta||{};
    const start=route.start_point||meta.start_point||{};
    const end=route.end_point||meta.end_point||{};
    const origin=toPoint(start), destination=toPoint(end);
    if(!origin||!destination) return null;
    const mids = stops.map(toPoint).filter(Boolean).slice(0, 9);
    let url = 'https://www.google.com/maps/dir/?api=1&travelmode=driving&dir_action=navigate'
      + '&origin=' + encodeURIComponent(origin)
      + '&destination=' + encodeURIComponent(destination);
    if (mids.length) url += '&waypoints=' + encodeURIComponent(mids.join('|'));
    return url;
  }

  // assinatura (canvas)
  function initSigPad(canvas){
    const ctx = canvas.getContext('2d');
    let drawing = false, last = null;
    const getPos = (e) => {
      const rect = canvas.getBoundingClientRect();
      const touch = e.touches ? e.touches[0] : null;
      const x = (touch ? touch.clientX : e.clientX) - rect.left;
      const y = (touch ? touch.clientY : e.clientY) - rect.top;
      return { x: x * (canvas.width/rect.width), y: y * (canvas.height/rect.height) };
    };
    const start = (e)=>{ drawing = true; last = getPos(e); e.preventDefault(); };
    const move  = (e)=>{ if(!drawing) return; const p=getPos(e); ctx.beginPath(); ctx.moveTo(last.x,last.y); ctx.lineTo(p.x,p.y); ctx.lineWidth=2; ctx.lineCap='round'; ctx.stroke(); last = p; e.preventDefault(); };
    const end   = ()=>{ drawing = false; };
    canvas.addEventListener('mousedown', start); canvas.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, {passive:false}); canvas.addEventListener('touchmove', move, {passive:false}); canvas.addEventListener('touchend', end);
    const dpr = window.devicePixelRatio || 1;
    canvas.width  = Math.floor(canvas.clientWidth * dpr);
    canvas.height = Math.floor(canvas.clientHeight * dpr);
    ctx.scale(dpr, dpr);
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    return ()=>{ ctx.clearRect(0,0,canvas.width,canvas.height); };
  }

  function renderTabs(routes){
    const hasMultipleRoutes = Array.isArray(routes) && routes.length > 1;
    tabsBox.classList.toggle('is-single', !hasMultipleRoutes);
    selRoute.closest('.rp-route-picker')?.classList.toggle('is-single', !hasMultipleRoutes);

    // desktop tabs
    tabsBox.innerHTML = routes.map((r,idx)=>{
      const label = '#'+r.id + (r.project_id ? ' · P'+r.project_id : '') + (r.status ? ' · '+r.status : '');
      const sel = (r.id===activeRouteId) ? 'true' : 'false';
      return `<button role="tab" class="rp-tab" data-route="${r.id}" aria-selected="${sel}" aria-controls="rp-panel-${r.id}">${esc(label)}</button>`;
    }).join('');

    tabsBox.querySelectorAll('.rp-tab').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const rid = parseInt(btn.dataset.route,10);
        if (rid && rid!==activeRouteId){ activeRouteId = rid; updateActiveTab(); refetchAndRender(activeRouteId); syncMobilePicker(); }
      });
    });

    // mobile select
    selRoute.innerHTML = routes.map(r=>{
      const label = '#'+r.id + (r.project_id ? ' · P'+r.project_id : '') + (r.status ? ' · '+r.status : '');
      const sel = (r.id===activeRouteId) ? 'selected' : '';
      return `<option value="${r.id}" ${sel}>${esc(label)}</option>`;
    }).join('');
    selRoute.disabled = !hasMultipleRoutes;

    selRoute.onchange = ()=>{
      const rid = parseInt(selRoute.value,10);
      if (rid && rid!==activeRouteId){ activeRouteId = rid; updateActiveTab(); refetchAndRender(activeRouteId); }
    };
  }
  function updateActiveTab(){
    tabsBox.querySelectorAll('.rp-tab').forEach(b=> b.setAttribute('aria-selected', String(parseInt(b.dataset.route,10)===activeRouteId)) );
  }
  function syncMobilePicker(){
    selRoute.value = activeRouteId ? String(activeRouteId) : '';
  }

  function renderRoute(det){
    const route = det;
    const stops = det.stops || [];
    const metrics = route.route_metrics || {};
    route.meta = route.meta || {};
    const isOperationalStop = stop=>!!String(stop?.arrived_at||'')||!!String(stop?.departed_at||'')||!['pending','planned','draft'].includes(String(stop?.status||'pending').toLowerCase());
    const routeOperationalStarted = stops.some(isOperationalStop);
    const routeStatus = String(route.status||'').toLowerCase();
    const routeWorkspaceFullEditable = ['draft','planned'].includes(routeStatus) && !routeOperationalStarted;
    const routeWorkspaceLiveAdjustable = routeStatus === 'in_progress' || (routeOperationalStarted && ['planned','completed'].includes(routeStatus));
    const routeWorkspaceEditable = routeWorkspaceFullEditable || routeWorkspaceLiveAdjustable;
    const isWorkspaceStopMutable = stop=>routeWorkspaceFullEditable || (routeWorkspaceLiveAdjustable && !isOperationalStop(stop));
    const lastProtectedWorkspaceIndex = ()=>{let last=-1;stops.forEach((stop,index)=>{if(isOperationalStop(stop))last=index;});return last;};
    const workspaceLockedLocations = new Set((route.meta.position_locked_location_ids||[]).map(value=>parseInt(value,10)).filter(Boolean));
    const workspaceHistory = Array.isArray(route.meta.route_workspace_history) ? route.meta.route_workspace_history.slice().reverse().slice(0,5) : [];
    const distanceComplete = String(metrics.distance_status||route.distance_status||'').toLowerCase()==='complete' && (metrics.endpoints_complete==null || Number(metrics.endpoints_complete)!==0);
    const kmRaw = distanceComplete ? (route.distance_km ?? metrics.distance_km ?? null) : (metrics.known_distance_km ?? route.known_distance_km ?? null);
    const kmValue = kmRaw===null||kmRaw===''||Number.isNaN(Number(kmRaw)) ? null : Number(kmRaw);
    const kmPart = value => value===null||value===undefined||value===''||Number.isNaN(Number(value)) ? 'Por calcular' : Number(value).toLocaleString('pt-PT',{maximumFractionDigits:1})+' km';
    const euroPart = value => value===null||value===undefined||value===''||Number.isNaN(Number(value)) ? 'Por calcular' : Number(value).toLocaleString('pt-PT',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';
    const minPart = value => { if(value===null||value===undefined||value===''||Number.isNaN(Number(value))) return 'Por calcular'; const total=Math.max(0,Math.round(Number(value))); const h=Math.floor(total/60), m=total%60; return h>0 ? `${h}h ${m}m` : `${m} min`; };
    const tollRaw = route.toll_cost_eur ?? metrics.toll_cost_eur ?? null;
    const travelRaw = route.travel_min ?? metrics.travel_min ?? null;
    const totalTimeRaw = route.total_min ?? metrics.total_min ?? null;
    const endpointStatus = String(metrics.endpoint_status||route.endpoint_status||'');
    const integrity = distanceComplete ? 'Completa' : (endpointStatus==='missing_start'?'Incompleta, falta partida':(endpointStatus==='missing_end'?'Incompleta, falta chegada':(endpointStatus==='missing_both'?'Incompleta, faltam partida e chegada':'Incompleta')));
    const sourceLabel = String(metrics.matrix_source_label||metrics.matrix_provider||metrics.distance_source||route.matrix_provider||'Estimador interno').replaceAll('_',' ');

    let html = '';
    html += '<div class="rp-route-main"'+(isAppMode?' style="grid-template-columns:1fr"':'')+'>';
    html += '  <div>';
    html += '    <h3 style="margin:0">Rota #'+esc(route.id)+' · '+esc(route.date)+'</h3>';
    html += '    <p class="rp-muted" style="margin:.25rem 0">Estado: <strong>'+esc(route.status||'')+'</strong> · Paragens: <strong>'+stops.length+'</strong></p>';
    html += '    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(125px,1fr));gap:8px;margin:10px 0 14px">';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">'+(distanceComplete?'Total':'Km conhecidos')+'</small><strong style="display:block">'+kmPart(kmValue)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Acesso</small><strong style="display:block">'+kmPart(metrics.access_distance_km)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Entre PDVs</small><strong style="display:block">'+kmPart(metrics.inter_stop_distance_km)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Regresso</small><strong style="display:block">'+kmPart(metrics.return_distance_km)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Portagens</small><strong style="display:block">'+euroPart(tollRaw)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Tempo viagem</small><strong style="display:block">'+minPart(travelRaw)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Tempo total</small><strong style="display:block">'+minPart(totalTimeRaw)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Integridade</small><strong style="display:block">'+esc(integrity)+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Direção</small><strong style="display:block">'+esc(metrics.territory_dominant_direction_label||'Por calcular')+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Coerência territorial</small><strong style="display:block">'+esc(metrics.territory_coherence_label||'Por calcular')+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Arco territorial</small><strong style="display:block">'+esc((metrics.territory_bearing_span_deg??0)+'° · '+(metrics.territory_route_cone_label||'Por calcular'))+'</strong></div>';
    html += '      <div class="rp-panel" style="padding:10px"><small class="rp-muted">Fonte</small><strong style="display:block;font-size:12px">'+esc(sourceLabel)+'</strong></div>';
    html += '    </div>';
    if (routeWorkspaceFullEditable) {
      html += '    <div class="rp-workspace-toolbar">';
      html += '      <label class="rp-workspace-meta"><input type="checkbox" id="rp-workspace-select-all"> Selecionar lojas</label>';
      html += '      <button data-action="workspace-remove-selected" class="rp-btn ghost" disabled>Remover selecionadas</button>';
      html += '      <button data-action="workspace-optimize" class="rp-btn secondary" '+(stops.length<2?'disabled':'')+'>Comparar e otimizar</button>';
      html += '      <button data-action="workspace-save-draft" class="rp-btn secondary">Guardar rascunho</button>';
      html += '      <button data-action="workspace-publish" class="rp-btn">Publicar rota</button>';
      html += '    </div>';
      if (workspaceHistory.length) {
        html += '    <details class="rp-workspace-history"><summary><strong>Histórico recuperável</strong> · '+workspaceHistory.length+' versão(ões)</summary>';
        workspaceHistory.forEach(function(revision){ html += '<div class="rp-workspace-history-row"><span><strong>'+esc(revision.label||revision.action||'Versão')+'</strong><small class="rp-muted" style="display:block">'+esc(revision.created_at||'')+' · '+esc(revision.stop_count||0)+' loja(s)</small></span><button data-action="workspace-restore" data-revision="'+esc(revision.id||'')+'" class="rp-btn ghost">Restaurar</button></div>'; });
        html += '    </details>';
      }
    } else if (routeWorkspaceLiveAdjustable) {
      html += '    <div class="rp-workspace-toolbar">';
      html += '      <label class="rp-workspace-meta"><input type="checkbox" id="rp-workspace-select-all"> Ajustar lojas pendentes</label>';
      html += '      <button data-action="workspace-remove-selected" class="rp-btn ghost" disabled>Remover pendentes</button>';
      html += '      <button data-action="workspace-save-live" class="rp-btn">Guardar ajuste</button>';
      html += '    </div>';
      html += '    <div class="rp-workspace-toolbar"><span class="rp-workspace-meta">Rota ativa: visitas executadas e em curso estão protegidas. As lojas pendentes podem ser removidas e reordenadas.</span></div>';
    } else if (routeOperationalStarted || ['completed','canceled'].includes(routeStatus)) {
      html += '    <div class="rp-workspace-toolbar"><span class="rp-workspace-meta">A rota já não permite alterações de planeamento.</span></div>';
    }
    if (!isAppMode) {
      html += '    <button id="rp-edit" class="rp-btn">Editar rota</button> ';
      if (routeWorkspaceEditable) html += '    <button id="rp-add-stop" class="rp-btn secondary">Adicionar paragem</button> ';
    }
    html += '    <button id="rp-open-gmaps" class="rp-btn secondary" title="Abrir no Google Maps">'+(isAppMode?'Navegar rota':'Ver no Google Maps')+'</button>';
    if (!isAppMode) html += '    <div id="rp-add-inline" style="display:none" class="rp-row"></div>';
    html += '  </div>';
    html += '  <div>';
    html += '    <h4 style="margin:.25rem 0">Itinerário</h4><ol class="rp-list">';

    for (let i=0;i<stops.length;i++){
      const s = stops[i];
      const status = String(s.status||'').toLowerCase();
      const isDone = (status === 'done' || status === 'completed');
      const isFailed = (status === 'failed');
      const liClass = isDone ? 'rp-done' : (isFailed ? 'rp-failed' : '');

      const workspaceStopMutable = isWorkspaceStopMutable(s);
      html += '<li data-stop="'+s.id+'" data-location="'+esc(s.location_id||0)+'" data-workspace-index="'+i+'" draggable="'+(workspaceStopMutable?'true':'false')+'" class="'+liClass+'">';
      if (workspaceStopMutable) html += '<label style="display:inline-flex;align-items:center;gap:6px;margin-right:6px"><input type="checkbox" data-workspace-select="'+s.id+'"> <span class="rp-muted">Mover</span></label>';
      html +=   '<strong>'+(i+1)+'. '+esc(s.location_name||'')+'</strong>';
      if (s.address) html += '<br><small class="rp-muted">'+esc(s.address)+'</small>';

      const chipClass = isDone ? 'rp-chip done' : (isFailed ? 'rp-chip failed' : 'rp-chip pending');
      const chipText  = isDone ? 'executado' : (isFailed ? 'não realizada' : (status==='in_progress'?'em curso':'pendente'));

      html += '<div class="rp-actions">';
      html +=   '<span class="'+chipClass+'">'+chipText+'</span>';
      if (workspaceStopMutable) html += '<button data-action="workspace-pin" data-location="'+esc(s.location_id||0)+'" class="rp-btn ghost">'+(workspaceLockedLocations.has(parseInt(s.location_id||0,10))?'Desfixar posição':'Fixar posição')+'</button>';
      if (isAppMode) {
        if (!isDone && !isFailed) html += '<button data-action="open-report" class="rp-btn">Preencher formulário</button>';
        if (workspaceStopMutable) html += '<button data-action="delete-stop" class="rp-btn ghost">Remover da rota</button>';
      } else {
        if (!isDone) {
          html += '<button data-action="mark-done" class="rp-btn">Concluir</button>';
          html += '<button data-action="mark-failed" class="rp-btn secondary">Falhou</button>';
        }
        if (workspaceStopMutable) html += '<button data-action="delete-stop" class="rp-btn ghost">Apagar</button>';
      }
      html += '</div>';

      if (!isAppMode) {
        // Painel de reporte legado, mantido fora da experiência simplificada da app.
        html += '<div class="rp-panel">';
        html +=   '<div class="rp-grid">';
        html +=     '<div><label class="rp-muted" for="note-'+s.id+'">Notas/observações</label><textarea id="note-'+s.id+'" class="rp-textarea" placeholder="Escreve observações...">'+esc(s.note||'')+'</textarea></div>';
        html +=     '<div>';
        html +=       '<div class="rp-kv"><label for="fail-'+s.id+'">Motivo de falha</label>';
        html +=       '<select id="fail-'+s.id+'" class="rp-select">';
        const options = ['','ausente','morada_errada','acesso_negado','condicoes','outro'];
        for (const val of options){
          const label = val ? val.replace('_',' ') : '(nenhum)';
          html += `<option value="${val}" ${val=== (s.fail_reason||'') ? 'selected':''}>${label}</option>`;
        }
        html +=       '</select><span></span></div>';
        html +=       '<div class="rp-kv" style="margin-top:6px"><label for="photo-'+s.id+'">URL foto/prova</label><input id="photo-'+s.id+'" class="rp-input" type="url" placeholder="https://..." value="'+esc(s.photo_url||'')+'">';
        html +=       '<button class="rp-btn ghost" data-action="upload-photo" data-stop="'+s.id+'">Tirar foto/Upload</button></div>';
        html +=     '</div>';

        const arrTime = s.arrived_at ? new Date(s.arrived_at).toTimeString().slice(0,5) : '';
        const depTime = s.departed_at ? new Date(s.departed_at).toTimeString().slice(0,5) : '';
        const dur = parseInt(s.duration_s||0,10) || 0;
        html +=     '<div>';
        html +=       '<div class="rp-kv"><label>Chegada</label><button data-action="set-arr" data-stop="'+s.id+'" class="rp-btn ghost">Agora</button><input id="arr-'+s.id+'" class="rp-input" type="time" value="'+esc(arrTime)+'"></div>';
        html +=       '<div class="rp-kv" style="margin-top:6px"><label>Partida</label><button data-action="set-dep" data-stop="'+s.id+'" class="rp-btn ghost">Agora</button><input id="dep-'+s.id+'" class="rp-input" type="time" value="'+esc(depTime)+'"></div>';
        html +=       '<div class="rp-kv" style="margin-top:6px"><label>Duração</label><span id="dur-'+s.id+'" class="rp-muted">'+(dur?Math.round(dur/60)+' min':'·')+'</span><span></span></div>';
        html +=     '</div>';
        html +=     '<div class="rp-sig-wrap">';
        html +=       '<label class="rp-muted">Assinatura do cliente</label>';
        html +=       '<canvas id="sig-'+s.id+'" class="rp-sig-canvas"></canvas>';
        html +=       '<div class="rp-actions"><button data-action="sig-clear" data-stop="'+s.id+'" class="rp-btn ghost">Limpar</button></div>';
        html +=     '</div>';
        html +=   '</div>';
        html +=   '<div class="rp-actions" style="justify-content:flex-end">';
        html +=     '<button data-action="save-report" class="rp-btn secondary">Guardar reporte</button>';
        html +=   '</div>';
        html += '</div>';
      }

      html += '</li>';
    }
    html += '    </ol>';
    html += '  </div>';
    html += '</div>';

    if (!isAppMode) {
      html += '<div id="rp-edit-form" style="display:none;margin-top:12px" class="rp-wrap">';
      html += '  <h4 style="margin:.25rem 0">Editar Rota</h4>';
      html += '  <div class="rp-grid">';
      html += '    <label>Data <input class="rp-input" id="f-date" type="date" value="'+esc(route.date)+'"></label>';
      html += '    <label>Status <select class="rp-input" id="f-status">'+['draft','planned','in_progress','completed','canceled'].map(s=>'<option '+(s===route.status?'selected':'')+'>'+s+'</option>').join('')+'</select></label>';
      html += '  </div>';
      html += '  <p style="margin-top:8px"><button id="f-save" class="rp-btn">Guardar</button> <button id="f-cancel" class="rp-btn secondary">Cancelar</button></p>';
      html += '</div>';
    }

    content.innerHTML = html;

    function workspaceSelectedStopIds(){ return Array.from(content.querySelectorAll('[data-workspace-select]:checked')).map(input=>parseInt(input.dataset.workspaceSelect||'0',10)).filter(Boolean); }
    function updateWorkspaceSelection(){ const ids=workspaceSelectedStopIds(); const button=content.querySelector('[data-action="workspace-remove-selected"]'); if(button) button.disabled=ids.length===0; const all=content.querySelector('#rp-workspace-select-all'); const boxes=Array.from(content.querySelectorAll('[data-workspace-select]')); if(all){all.checked=boxes.length>0&&ids.length===boxes.length;all.indeterminate=ids.length>0&&ids.length<boxes.length;} }
    const workspaceSelectAll=content.querySelector('#rp-workspace-select-all');
    if(workspaceSelectAll) workspaceSelectAll.addEventListener('change',()=>{content.querySelectorAll('[data-workspace-select]').forEach(input=>{input.checked=workspaceSelectAll.checked;});updateWorkspaceSelection();});
    content.querySelectorAll('[data-workspace-select]').forEach(input=>input.addEventListener('change',updateWorkspaceSelection));
    if(routeWorkspaceEditable){
      let workspaceDragIndex=null;
      content.querySelectorAll('[data-workspace-index]').forEach(row=>{
        row.addEventListener('dragstart',event=>{if(event.target.closest('button,input,select,textarea,a')){event.preventDefault();return;}const index=parseInt(row.dataset.workspaceIndex||'-1',10);if(index<0||!isWorkspaceStopMutable(stops[index])){event.preventDefault();return;}workspaceDragIndex=index;row.classList.add('rp-workspace-dragging');if(event.dataTransfer)event.dataTransfer.effectAllowed='move';});
        row.addEventListener('dragend',()=>{row.classList.remove('rp-workspace-dragging');workspaceDragIndex=null;});
        row.addEventListener('dragover',event=>event.preventDefault());
        row.addEventListener('drop',event=>{event.preventDefault();const target=parseInt(row.dataset.workspaceIndex||'-1',10);if(!Number.isFinite(workspaceDragIndex)||workspaceDragIndex<0||target<0||workspaceDragIndex===target)return;if(routeWorkspaceLiveAdjustable&&target<=lastProtectedWorkspaceIndex()){alert('As lojas pendentes só podem ser reorganizadas depois da última visita executada ou em curso.');return;}const moved=stops.splice(workspaceDragIndex,1)[0];stops.splice(target,0,moved);stops.forEach((stop,index)=>{stop.seq=index+1;});route.meta.manual_order_locked=1;renderRoute(route);});
      });
    }

    // Iniciar canvases assinatura
    const clearers = {};
    for (const s of stops){
      const cnv = document.getElementById('sig-'+s.id);
      if (cnv) clearers[s.id] = initSigPad(cnv);
    }

    // abrir Directions
    const openBtn = document.getElementById('rp-open-gmaps');
    if (openBtn) openBtn.addEventListener('click', ()=> {
      const url = route.google_maps_url || buildGMapsUrlFromRoute(route,stops);
      if (!url) { alert('Define uma partida e uma chegada reais antes de navegar.'); return; }
      if (window.FieldFlowNavigation && typeof window.FieldFlowNavigation.open === 'function') { window.FieldFlowNavigation.open(url, {source:'my_daily_route'}); } else { window.location.assign(url); }
    });

    // toggle editor
    const editBtn = document.getElementById('rp-edit');
    if (editBtn) editBtn.onclick = ()=>{ const form=document.getElementById('rp-edit-form'); if(form) form.style.display='block'; };
    const cancelBtn = document.getElementById('f-cancel');
    if (cancelBtn) cancelBtn.onclick = ()=>{ document.getElementById('rp-edit-form').style.display='none'; };

    // Inline Add Stop
    const addBtn = document.getElementById('rp-add-stop');
    const addWrap= document.getElementById('rp-add-inline');
    if (addBtn && addWrap) addBtn.addEventListener('click', () => {
      addWrap.style.display = '';
      addWrap.innerHTML =
        '<input id="rp-new-stop" class="rp-input" placeholder="Morada ou ponto">'+
        '<button id="rp-save-stop" class="rp-btn">Adicionar</button>'+
        '<button id="rp-cancel-stop" class="rp-btn secondary">Cancelar</button>';

      loadGoogle(()=> {
        const input = document.getElementById('rp-new-stop');
        const ac = makeAutocomplete(input);
        if (ac) {
          ac.addListener('place_changed', () => {
            const plc = ac.getPlace();
            input.dataset.lat = '';
            input.dataset.lng = '';
            input.dataset.addr = '';
            if (plc && plc.geometry) {
              input.dataset.lat  = plc.geometry.location.lat();
              input.dataset.lng  = plc.geometry.location.lng();
              input.dataset.addr = plc.formatted_address || plc.name || input.value;
            }
          });
        }
      });
    });

    // handlers
    content.onclick = async (e)=>{
      const b = e.target.closest('button'); if(!b) return;
      const li  = b.closest('li');
      const sid = li ? li.getAttribute('data-stop') : null;

      if (b.dataset.action === 'workspace-pin') {
        const locationId=parseInt(b.dataset.location||'0',10); const locked=new Set((route.meta.position_locked_location_ids||[]).map(value=>parseInt(value,10)).filter(Boolean));
        locked.has(locationId)?locked.delete(locationId):locked.add(locationId); route.meta.position_locked_location_ids=Array.from(locked); renderRoute(route); return;
      }
      if (b.dataset.action === 'workspace-remove-selected') {
        const stopIds=workspaceSelectedStopIds(); if(!stopIds.length)return;
        if(!confirm('Remover '+stopIds.length+' loja(s) desta rota? A versão atual ficará no histórico recuperável.'))return;
        b.disabled=true;
        try{await j(apiBase+'routes/'+(route.id||det.id)+'/stops/bulk-remove',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({stop_ids:stopIds})});document.dispatchEvent(new CustomEvent('fieldflow:route-updated',{detail:{route_id:route.id||det.id,route_date:route.date||''}}));await refetchAndRender(route.id||det.id);}catch(error){alert(error?.message||'Não foi possível remover as lojas selecionadas.');b.disabled=false;} return;
      }
      if (b.dataset.action === 'workspace-save-draft' || b.dataset.action === 'workspace-publish' || b.dataset.action === 'workspace-save-live') {
        const live=b.dataset.action==='workspace-save-live';
        const status=live?(routeStatus==='completed'?'in_progress':routeStatus):(b.dataset.action==='workspace-publish'?'planned':'draft');
        if(status==='planned'&&Number(totalTimeRaw||travelRaw||0)>480&&!confirm('A duração estimada ultrapassa 8 horas. Publicar na mesma?'))return;
        b.disabled=true;
        try{await j(apiBase+'routes/'+(route.id||det.id)+'/workspace',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({status,stop_ids:stops.map(stop=>parseInt(stop.id||0,10)).filter(Boolean),manual_order_locked:route.meta.manual_order_locked?1:0,locked_location_ids:(route.meta.position_locked_location_ids||[])})});document.dispatchEvent(new CustomEvent('fieldflow:route-updated',{detail:{route_id:route.id||det.id,route_date:route.date||''}}));await refetchAndRender(route.id||det.id);alert(live?'Ajuste guardado na rota ativa.':(status==='draft'?'Rascunho guardado.':'Rota publicada.'));}catch(error){alert(error?.message||'Não foi possível guardar o workspace.');b.disabled=false;} return;
      }
      if (b.dataset.action === 'workspace-optimize') {
        b.disabled=true;
        try{const locked=(route.meta.position_locked_location_ids||[]).map(value=>parseInt(value,10)).filter(Boolean);const preview=await j(apiBase+'route-optimization/preview',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({route_id:parseInt(route.id||det.id||0,10),location_ids:stops.map(stop=>parseInt(stop.location_id||0,10)).filter(Boolean),start_point:route.meta.start_point||{},end_point:route.meta.end_point||{},return_to_start:route.meta.return_to_start?1:0,manual_order_locked:0,locked_location_ids:locked,mode:'internal'})});const currentKm=Number(preview?.baseline?.distance_km||0),optimizedKm=Number(preview?.optimized?.distance_km||0),savingKm=Number(preview?.saving_km||0),savingMin=Math.round(Number(preview?.saving_minutes||0));const apply=confirm('Rota atual: '+currentKm.toFixed(1)+' km. Alternativa: '+optimizedKm.toFixed(1)+' km. Poupança estimada: '+savingKm.toFixed(1)+' km e '+savingMin+' min. Aplicar a nova ordem?');if(apply){await j(apiBase+'routes/'+(route.id||det.id)+'/optimize',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({mode:'internal',manual_order_locked:0,locked_location_ids:locked})});document.dispatchEvent(new CustomEvent('fieldflow:route-updated',{detail:{route_id:route.id||det.id,route_date:route.date||''}}));await refetchAndRender(route.id||det.id);}}catch(error){alert(error?.message||'Não foi possível comparar a rota.');}finally{b.disabled=false;} return;
      }
      if (b.dataset.action === 'workspace-restore') {
        const revisionId=String(b.dataset.revision||''); if(!revisionId||!confirm('Restaurar esta versão? O estado atual também ficará guardado.'))return;
        b.disabled=true;
        try{await j(apiBase+'routes/'+(route.id||det.id)+'/workspace/restore',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({revision_id:revisionId})});document.dispatchEvent(new CustomEvent('fieldflow:route-updated',{detail:{route_id:route.id||det.id,route_date:route.date||''}}));await refetchAndRender(route.id||det.id);}catch(error){alert(error?.message||'Não foi possível restaurar a versão.');b.disabled=false;} return;
      }

      // Guardar alterações básicas (rota)
      if (b.id === 'f-save'){
        const d = document.getElementById('f-date').value;
        const status = document.getElementById('f-status').value;
        await j(apiBase+'routes/'+(route.id||det.id), {
          method:'PATCH',
          headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
          body: JSON.stringify({ date: d, status: status })
        });
        alert('Rota atualizada.');
        await refetchAndRender(route.id||det.id);
        return;
      }

      if (b.id === 'f-cancel'){ return; }

      if (b.dataset.action === 'open-report' && sid){
        const stop=stops.find(item=>String(item.id)===String(sid))||{};
        document.dispatchEvent(new CustomEvent('fieldflow:open-report',{detail:{
          date:String(route.date||''),
          route_id:parseInt(route.id||det.id||0,10),
          stop_id:parseInt(sid||0,10),
          location_id:parseInt(stop.location_id||0,10),
          client_id:parseInt(route.client_id||0,10),
          project_id:parseInt(route.project_id||0,10)
        }}));
        return;
      }

      // Hora = agora
      if (b.dataset.action === 'set-arr' && sid){
        const input = document.getElementById('arr-'+sid);
        input.value = localTimeHHMM();
        return;
      }
      if (b.dataset.action === 'set-dep' && sid){
        const input = document.getElementById('dep-'+sid);
        input.value = localTimeHHMM();
        return;
      }

      // Assinatura: limpar
      if (b.dataset.action === 'sig-clear' && sid){
        if (clearers[sid]) clearers[sid]();
        return;
      }

      // Upload foto (câmara/ficheiro)
      if (b.dataset.action === 'upload-photo' && sid){
        try{
          const picker = document.createElement('input');
          picker.type = 'file';
          picker.accept = 'image/*';
          picker.capture = 'environment';
          picker.onchange = async () => {
            const file = picker.files && picker.files[0];
            if (!file) return;
            const url = await uploadMedia(file);
            const urlEl = document.getElementById('photo-'+sid);
            if (urlEl) urlEl.value = url;
          };
          picker.click();
        }catch(err){ alert('Falha no upload: '+(err?.message||err)); }
        return;
      }

      // Marcar como executado
      if (b.dataset.action === 'mark-done' && sid){
        const payload = collectReportPayload(sid, 'done', route.date);
        const geo = await getGeo(); if (geo){ payload.real_lat = geo.lat; payload.real_lng = geo.lng; }
        if (!payload.departed_at) {
          const hhmm = localTimeHHMM();
          payload.departed_at = joinDateTimeToISO(route.date, hhmm);
        }
        await patchStop(sid, payload);
        await refetchAndRender(route.id||det.id);
        return;
      }

      // Marcar como falhou
      if (b.dataset.action === 'mark-failed' && sid){
        const failEl = document.getElementById('fail-'+sid);
        const reason = (failEl?.value || '').trim();
        if (!reason){ alert('Seleciona um motivo de falha.'); return; }
        const payload = collectReportPayload(sid, 'failed', route.date);
        await patchStop(sid, payload);
        await refetchAndRender(route.id||det.id);
        return;
      }

      // Remover stop, preservando reportes e histórico operacional.
      if (b.dataset.action === 'delete-stop' && sid){
        const stop = (stops||[]).find(item=>String(item.id||'')===String(sid)) || {};
        const stopStatus = String(stop.status||'').toLowerCase();
        const hasOperationalHistory = ['done','completed','failed','in_progress','active','started'].includes(stopStatus)||String(stop.arrived_at||'')||String(stop.departed_at||'');
        if(hasOperationalHistory){ alert('Esta loja já foi executada ou está em curso e não pode ser removida da rota ativa.'); return; }
        const message = 'Retirar esta loja pendente da rota?';
        if (!confirm(message)) return;
        b.disabled = true;
        try {
          await j(apiBase+'routes/'+(route.id||det.id)+'/stops/bulk-remove', {
            method:'POST', headers:{'Content-Type':'application/json','X-WP-Nonce':nonce}, body:JSON.stringify({stop_ids:[parseInt(sid,10)]})
          });
          document.dispatchEvent(new CustomEvent('fieldflow:route-updated', {detail:{route_id:route.id||det.id,route_date:route.date||''}}));
          await refetchAndRender(route.id||det.id);
        } catch(error) {
          alert(error?.message || 'Não foi possível remover a loja da rota.');
          b.disabled = false;
        }
        return;
      }

      // Cancelar inline add
      if (b.id === 'rp-cancel-stop'){
        document.getElementById('rp-add-inline').style.display='none';
        document.getElementById('rp-add-inline').innerHTML='';
        return;
      }

      // Adicionar stop
      if (b.id === 'rp-save-stop'){
        const btn = b;
        try{
          btn.disabled = true;
          const input = document.getElementById('rp-new-stop');
          let addr = (input.dataset.addr || '').trim();
          let lat  = parseFloat(input.dataset.lat || 'NaN');
          let lng  = parseFloat(input.dataset.lng || 'NaN');

          if (!addr) addr = (input.value || '').trim();
          if ((!isNum(lat) || !isNum(lng)) && addr && googleReady) {
            const g = await geocodeAddress(addr);
            if (g) { lat = g.lat; lng = g.lng; addr = g.formatted || addr; }
          }
          if (!addr || !isNum(lat) || !isNum(lng)) { alert('Escolhe um ponto válido do dropdown (Google) ou usa uma morada completa.'); return; }

          const routeId   = route.id || det.id;
          const client_id = route.client_id ?? det.client_id ?? null;
          const project_id= route.project_id ?? det.project_id ?? null;

          const locId = await ensureLoc(addr, lat, lng, client_id, project_id);

          const lastSeq = Math.max(0, ...Array.from(content.querySelectorAll('.rp-list li')).map(li => parseInt(li.querySelector('strong')?.textContent?.split('.')[0]) || 0));
          const nextSeq = Number.isFinite(lastSeq) ? lastSeq + 1 : 1;

          await createStop(routeId, locId, nextSeq);

          document.getElementById('rp-add-inline').style.display='none';
          document.getElementById('rp-add-inline').innerHTML='';
          await refetchAndRender(routeId);
        } catch(err){
          console.error('[routespro] add stop fail', err);
          let msg = err?.message || String(err);
          try { const jj = JSON.parse(msg); msg = jj.message || msg; } catch(_){}
          alert('Falha ao adicionar paragem: ' + msg);
        } finally {
          btn.disabled = false;
        }
        return;
      }

      // Guardar reporte manualmente
      if (b.dataset.action === 'save-report' && sid){
        const payload = collectReportPayload(sid, null, route.date);
        await patchStop(sid, payload);
        alert('Reporte guardado.');
        await refetchAndRender(route.id||det.id);
        return;
      }
    };

    function collectReportPayload(sid, setStatus, dateStr){
      const note  = (document.getElementById('note-'+sid)?.value || '').trim();
      const fail  = (document.getElementById('fail-'+sid)?.value || '').trim();
      const photo = (document.getElementById('photo-'+sid)?.value || '').trim();

      const arrEl = document.getElementById('arr-'+sid);
      const depEl = document.getElementById('dep-'+sid);
      const arrISO = arrEl && arrEl.value ? joinDateTimeToISO(dateStr, arrEl.value) : null;
      const depISO = depEl && depEl.value ? joinDateTimeToISO(dateStr, depEl.value) : null;

      const cnv = document.getElementById('sig-'+sid);
      let signature_data = null;
      if (cnv){ try { signature_data = cnv.toDataURL('image/png'); } catch(_){ } }

      let duration_s = null;
      if (arrISO && depISO){
        duration_s = Math.max(0, Math.floor((new Date(depISO).getTime() - new Date(arrISO).getTime())/1000));
      }

      const payload = {};
      if (setStatus) payload.status = setStatus;
      payload.note = note;
      if (fail) payload.fail_reason = fail;
      if (photo) payload.photo_url = photo;
      if (signature_data) payload.signature_data = signature_data;
      if (arrISO) payload.arrived_at = arrISO;
      if (depISO) payload.departed_at = depISO;
      if (duration_s !== null) payload.duration_s = duration_s;

      return payload;
    }
  }

  async function loadRoutesForDay(dateStr, options={}){
    const data = await j(apiBase+'routes?date='+encodeURIComponent(dateStr)+'&user_id=me');
    routesCache = Array.isArray(data.routes) ? data.routes : [];
    if (!routesCache.length){
      activeRouteProjectId=0;
      renderEmpty(dateStr);
      loadPeriodicity(dateStr,0,!!options.forcePeriodicity).catch(()=>{});
      loadDailyFocus(dateStr,0,!!options.forcePeriodicity).catch(()=>{});
      return false;
    }
    // escolher ativa: mantém anterior se existir, senão a 1ª
    if (!activeRouteId || !routesCache.some(r=>r.id===activeRouteId)){
      activeRouteId = routesCache[0].id;
    }
    renderTabs(routesCache);
    await refetchAndRender(activeRouteId,options);
    return true;
  }

  // Export CSV (resumo simples da rota ativa)
  const exportBtn=document.getElementById('btn-export');
  if(exportBtn) exportBtn.addEventListener('click', async function(){
    if (!activeRouteId) return;
    const det = await j(apiBase+'routes/'+activeRouteId+'?v='+Date.now());
    let csv = 'route_id,date,status,stops\n';
    csv += [det.id, det.date||'', det.status||'', (det.stops||[]).length].join(',') + '\n';
    const blob = new Blob([csv], {type:'text/csv'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'routespro-'+(det.date||'')+'.csv'; a.click();
  });

  // Navegação de datas
  document.getElementById('rp-prev-day').addEventListener('click', async ()=>{
    const d = new Date(inpDate.value || new Date());
    d.setDate(d.getDate()-1); inpDate.value = fmtDate(d);
    await loadRoutesForDay(inpDate.value);
  });
  document.getElementById('rp-next-day').addEventListener('click', async ()=>{
    const d = new Date(inpDate.value || new Date());
    d.setDate(d.getDate()+1); inpDate.value = fmtDate(d);
    await loadRoutesForDay(inpDate.value);
  });
  inpDate.addEventListener('change', ()=> loadRoutesForDay(inpDate.value));

  let externalRefreshTimer = null;
  const refreshDailyRoute = (detail={}) => {
    const eventDate = String(detail.route_date || detail.report_day || '');
    if (eventDate && eventDate !== String(inpDate.value || '')) return;
    const eventRouteId = parseInt(detail.route_id || 0, 10);
    if (eventRouteId && routesCache.length && !routesCache.some(route => parseInt(route.id || 0, 10) === eventRouteId)) return;
    window.clearTimeout(externalRefreshTimer);
    periodicityCache.clear();
    priorityCache.clear();
    externalRefreshTimer = window.setTimeout(() => loadRoutesForDay(inpDate.value,{forcePeriodicity:true}).catch(()=>{}), 90);
  };
  document.addEventListener('fieldflow:visit-completed', event => refreshDailyRoute(event.detail || {}));
  document.addEventListener('fieldflow:self-routes-created', event => refreshDailyRoute((event&&event.detail)||{}));
  root.addEventListener('routespro:panel-open', () => refreshDailyRoute({}));

  // 9CI Route First Paint (fieldflow-route-first-paint-v1): a rota do dia já não espera pelo Google Maps.
  // O Google só é carregado quando o operativo abre "Adicionar paragem" (loadGoogle no próprio fluxo).
  Promise.resolve().then(()=>loadRoutesForDay(inpDate.value)).catch(()=>{});
})();
</script>
<?php
        return ob_get_clean();
    }

    /**
     * DASHBOARD
     * - Filtros: Projeto, Função (implementação/merchandising/comercial), Funcionário
     * - Resumo + tabela por dia
     * - Exporta toda a tabela
     */


    public static function front_guard() {
        if (!is_user_logged_in()) return '<p>Por favor, inicia sessão.</p>';
        if (!\RoutesPro\Support\Permissions::can_access_front()) {
            return '<p>Sem permissões.</p>';
        }
        return '';
    }

    public static function front_theme(int $client_id = 0, int $project_id = 0): array {
        if (!$client_id) $client_id = absint($_GET['client_id'] ?? 0);
        if (!$project_id) $project_id = absint($_GET['project_id'] ?? 0);
        [$client_id, $project_id] = \RoutesPro\Support\Permissions::resolve_front_context($client_id, $project_id);
        return \RoutesPro\Support\Theme::resolve($client_id, $project_id);
    }

    public static function front_hub($atts = []) {
        return self::app($atts);
    }

    public static function front_commercial($atts = []) {
        $guard = self::front_guard();
        if ($guard) return $guard;
        $theme = self::front_theme();
        $opts = \RoutesPro\Admin\Settings::get();
        $gmKey = trim((string)($opts['google_maps_key'] ?? ''));
        $nonce = wp_create_nonce('wp_rest');
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $clients = \RoutesPro\Support\Permissions::filter_clients($wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: []);
        $projects = \RoutesPro\Support\Permissions::filter_projects($wpdb->get_results("SELECT id,name,client_id FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: []);
        $initial_client_id = absint($atts['client_id'] ?? ($_GET['client_id'] ?? 0));
        $initial_project_id = absint($atts['project_id'] ?? ($_GET['project_id'] ?? 0));
        $scope = \RoutesPro\Support\Permissions::get_scope();
        ob_start(); ?>
<style>
.rp-front-commercial{--rp-primary:<?php echo esc_attr($theme['primary']); ?>;--rp-accent:<?php echo esc_attr($theme['accent']); ?>;--rp-bg:<?php echo esc_attr($theme['bg']); ?>;font-family:<?php echo esc_attr($theme['font']); ?>;font-size:<?php echo esc_attr($theme['size']); ?>px}
.rp-front-commercial .rp-card{background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 16px 40px rgba(15,23,42,.08);padding:18px}
.rp-front-commercial .rp-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:16px}
.rp-front-commercial .rp-toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.rp-front-commercial .rp-toolbar input,.rp-front-commercial .rp-toolbar select{min-width:160px;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px}
.pac-container{z-index:99999 !important;border-radius:14px;box-shadow:0 18px 40px rgba(15,23,42,.18)}
.rp-front-commercial .rp-btn{border:0;border-radius:12px;padding:10px 14px;background:var(--rp-primary);color:#fff;font-weight:700;cursor:pointer}
.rp-front-commercial .rp-btn.alt{background:var(--rp-accent)}
.rp-front-commercial .rp-btn.ghost{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
.rp-front-commercial .rp-map{height:360px;min-height:360px;border-radius:18px;overflow:hidden;border:1px solid #dbeafe;background:#eff6ff;position:relative;z-index:1}
.rp-front-commercial .rp-list{max-height:420px;overflow:auto;margin-top:12px}
.rp-front-commercial .rp-item{border:1px solid #e2e8f0;border-radius:16px;padding:12px;background:#fff;margin-bottom:10px}
.rp-front-commercial .rp-item h4{margin:0 0 6px}
.rp-front-commercial .rp-meta{color:#64748b;font-size:13px}
.rp-front-commercial .rp-inline{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.rp-front-commercial .rp-inline .full{grid-column:1/-1}
.rp-front-commercial .rp-inline input,.rp-front-commercial .rp-inline select{border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;width:100%}
.rp-front-commercial .rp-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
.rp-front-commercial .rp-summary div{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px}
.rp-front-commercial .rp-summary strong{display:block;font-size:24px}
@media (max-width:1080px){.rp-front-commercial .rp-grid,.rp-front-commercial .rp-inline,.rp-front-commercial .rp-summary{grid-template-columns:1fr}}
</style>
<div class="rp-front-commercial" data-nonce="<?php echo esc_attr($nonce); ?>" data-gmkey="<?php echo esc_attr($gmKey); ?>" data-clients='<?php echo esc_attr(wp_json_encode($clients)); ?>' data-projects='<?php echo esc_attr(wp_json_encode($projects)); ?>' data-endpoint-profiles='<?php echo esc_attr(wp_json_encode($endpoint_profiles ?? [])); ?>' data-client-id="<?php echo esc_attr($initial_client_id); ?>" data-project-id="<?php echo esc_attr($initial_project_id); ?>" data-scope='<?php echo esc_attr(wp_json_encode($scope)); ?>'>
  <div class="rp-card" style="margin-bottom:16px">
    <h3 style="margin:0 0 6px">Base Comercial Front</h3>
    <div class="rp-meta">Mesma lógica da Base Comercial do backoffice, mas pensada para operação no front, com pesquisa interna, novos PDVs via Google e gravação imediata.</div>
    <div class="rp-toolbar" style="margin-top:14px">
      <select id="rpc-client"><option value="">Cliente</option></select>
      <select id="rpc-project"><option value="">Campanha</option></select>
      <select id="rpc-owner"><option value="">Owner operativo</option></select>
      <select id="rpc-role"><option value="">Função operacional</option></select>
      <select id="rpc-district"><option value="">Distrito</option></select>
      <select id="rpc-county"><option value="">Concelho</option></select>
      <select id="rpc-city"><option value="">Cidade</option></select>
      <select id="rpc-category"><option value="">Categoria</option></select>
      <select id="rpc-subcategory"><option value="">Subcategoria</option></select>
      <input type="search" id="rpc-q" placeholder="Nome, morada ou contacto">
      <button type="button" class="rp-btn" id="rpc-run">Filtrar</button>
      <button type="button" class="rp-btn alt" id="rpc-google">Google</button>
    </div>
    <div class="rp-summary">
      <div><span>Internos</span><strong id="rpc-count-internal">0</strong></div>
      <div><span>Google</span><strong id="rpc-count-google">0</strong></div>
      <div><span>No mapa</span><strong id="rpc-count-map">0</strong></div>
    </div>
  </div>
  <div class="rp-grid">
    <div class="rp-card">
      <div id="rpc-map" class="rp-map"></div>
      <div class="rp-list" id="rpc-results"></div>
    </div>
    <div class="rp-card">
      <h4 style="margin-top:0">Novo / editar PDV</h4>
      <div class="rp-inline">
        <input class="full" id="rpc-name" placeholder="Nome do estabelecimento">
        <input class="full" id="rpc-address" placeholder="Morada">
        <input id="rpc-phone" placeholder="Telefone">
        <input id="rpc-email" placeholder="Email">
        <input id="rpc-contact" placeholder="Contacto">
        <input id="rpc-website" placeholder="Website">
        <input id="rpc-lat" placeholder="Lat">
        <input id="rpc-lng" placeholder="Lng">
        <input type="hidden" id="rpc-place-id">
        <button type="button" class="rp-btn full" id="rpc-save">Guardar PDV</button>
      </div>
      <p class="rp-meta" id="rpc-status" style="margin-top:10px">Seleciona um PDV da lista ou do mapa para preencher automaticamente. Também podes criar manualmente.</p>
    </div>
  </div>
</div>
<script>
(async function(){
  const root = document.currentScript.previousElementSibling; if(!root || !root.classList.contains('rp-front-commercial')) return;
  const nonce = root.dataset.nonce;
  const gmKey = root.dataset.gmkey || '';
  const googlePlacesContactEnrichment = <?php echo !empty(\RoutesPro\Support\Config::get('google_places_contact_enrichment_enabled', 0)) ? 'true' : 'false'; ?>;
  const api = '<?php echo esc_url(rest_url('routespro/v1/')); ?>';
  const clients = JSON.parse(root.dataset.clients || '[]');
  const projects = JSON.parse(root.dataset.projects || '[]');
  const initialClientId = String(root.dataset.clientId || '');
  const initialProjectId = String(root.dataset.projectId || '');
  const currentUserId = <?php echo (int) get_current_user_id(); ?>;
  const adminAjax = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
  const isPortalContext = !!root.closest('.rp-client-portal');
  const isAppContext = !!root.closest('.rp-app');
  const scope = JSON.parse(root.dataset.scope || '{}');
  let map, markers=[], previewMarker=null, googleMap, placesSvc, geocoder, googleReady=false, addressAutocomplete=null;
  let categories = [];
  let selected = null;
  let lastResolvedAddress = '';
  let scopeFilters = {districts:[],countiesByDistrict:{},citiesByDistrict:{},categories:[]};
  let autoRunTimer = null;
  let scopeReqId = 0;
  const els = {
    client: root.querySelector('#rpc-client'), project: root.querySelector('#rpc-project'), owner: root.querySelector('#rpc-owner'), role: root.querySelector('#rpc-role'),
    district: root.querySelector('#rpc-district'), county: root.querySelector('#rpc-county'), city: root.querySelector('#rpc-city'),
    category: root.querySelector('#rpc-category'), subcategory: root.querySelector('#rpc-subcategory'), q: root.querySelector('#rpc-q'),
    results: root.querySelector('#rpc-results'), status: root.querySelector('#rpc-status'),
    countInternal: root.querySelector('#rpc-count-internal'), countGoogle: root.querySelector('#rpc-count-google'), countMap: root.querySelector('#rpc-count-map'),
    name: root.querySelector('#rpc-name'), address: root.querySelector('#rpc-address'), phone: root.querySelector('#rpc-phone'), email: root.querySelector('#rpc-email'), contact: root.querySelector('#rpc-contact'), website: root.querySelector('#rpc-website'), lat: root.querySelector('#rpc-lat'), lng: root.querySelector('#rpc-lng'), placeId: root.querySelector('#rpc-place-id')
  };
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
  function setStatus(msg){ els.status.textContent = msg || ''; }
  async function j(url, opts){ const r=await fetch(url,Object.assign({credentials:'same-origin',headers:{'X-WP-Nonce':nonce}},opts||{})); const t=await r.text(); let d={}; try{d=t?JSON.parse(t):{}}catch(_){d={message:t}} if(!r.ok) throw new Error(d.message||r.statusText); return d; }
  function fillClients(){ if(!els.client) return; const current = String(els.client.value || initialClientId || ''); els.client.innerHTML='<option value="">Cliente</option>'+clients.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join(''); if(current) els.client.value=current; }
  function syncProjects(){ if(!els.project) return; const cid=parseInt(els.client?.value||'0',10); const filtered=cid ? projects.filter(x=>parseInt(x.client_id||0,10)===cid) : projects; const preferred = String(els.project.value || initialProjectId || ''); els.project.innerHTML='<option value="">Campanha</option>'+filtered.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join(''); if(preferred && Array.from(els.project.options).some(o=>String(o.value)===preferred)) els.project.value=preferred; }
  function allowedClientIds(){ return Array.isArray(scope?.client_ids) ? scope.client_ids.map(v=>String(parseInt(v,10))).filter(Boolean) : []; }
  function allowedProjectIds(){ return Array.isArray(scope?.project_ids) ? scope.project_ids.map(v=>String(parseInt(v,10))).filter(Boolean) : []; }
  function effectiveOwnerId(){ return (isAppContext && !scope?.is_manager) ? String(currentUserId || '') : String(els.owner?.value || ''); }
  function ensureAppOwner(){
    if(!isAppContext || scope?.is_manager || !els.owner) return;
    const forced = String(currentUserId || '');
    if(!forced) return;
    if(!Array.from(els.owner.options).some(o=>String(o.value)===forced)){
      const opt=document.createElement('option'); opt.value=forced; opt.textContent='Meu utilizador'; els.owner.appendChild(opt);
    }
    els.owner.value = forced;
    els.owner.disabled = true;
  }
  function lockAppScope(){
    if(!isAppContext || scope?.is_manager) return;
    const clientIds = allowedClientIds();
    const projectIds = allowedProjectIds();
    if(els.client){
      if(!els.client.value && initialClientId && clientIds.includes(String(initialClientId))) els.client.value=String(initialClientId);
      if(!els.client.value && clientIds.length===1) els.client.value=clientIds[0];
      els.client.disabled = clientIds.length <= 1;
    }
    syncProjects();
    if(els.project){
      if(!els.project.value && initialProjectId && projectIds.includes(String(initialProjectId)) && Array.from(els.project.options).some(o=>String(o.value)===String(initialProjectId))) els.project.value=String(initialProjectId);
      if(!els.project.value && projectIds.length===1 && Array.from(els.project.options).some(o=>String(o.value)===projectIds[0])) els.project.value=projectIds[0];
      els.project.disabled = projectIds.length <= 1 && projectIds.length > 0;
    }
  }
  function appScopeReady(){
    if(!isAppContext || scope?.is_manager) return true;
    return !!(els.client?.value && els.project?.value && effectiveOwnerId());
  }
  async function syncOwnersAndRoles(){
    const currentOwner = String(els.owner?.value || '');
    const currentRole = String(els.role?.value || '');
    const urlUsers = new URL(adminAjax, window.location.origin);
    urlUsers.searchParams.set('action', 'routespro_users');
    if(els.client?.value) urlUsers.searchParams.set('client_id', els.client.value);
    if(els.project?.value) urlUsers.searchParams.set('project_id', els.project.value);
    const urlRoles = new URL(adminAjax, window.location.origin);
    urlRoles.searchParams.set('action', 'routespro_roles');
    if(els.client?.value) urlRoles.searchParams.set('client_id', els.client.value);
    if(els.project?.value) urlRoles.searchParams.set('project_id', els.project.value);
    try {
      const [usersRes, rolesRes] = await Promise.all([
        fetch(urlUsers.toString(), {credentials:'same-origin'}).then(r=>r.json()).catch(()=>[]),
        fetch(urlRoles.toString(), {credentials:'same-origin'}).then(r=>r.json()).catch(()=>[])
      ]);
      const users = Array.isArray(usersRes) ? usersRes : [];
      const roles = Array.isArray(rolesRes) ? rolesRes : [];
      if(els.owner){
        els.owner.innerHTML = '<option value="">Owner operativo</option>' + users.map(u=>`<option value="${parseInt(u.ID||0,10)}">${esc(u.label || u.displayName || u.username || ('User '+(u.ID||'')))}</option>`).join('');
        if(currentOwner && Array.from(els.owner.options).some(o=>String(o.value)===currentOwner)) {
          els.owner.value = currentOwner;
        } else if(isAppContext && users.some(u=>String(parseInt(u.ID||0,10))===String(currentUserId))) {
          els.owner.value = String(currentUserId);
        }
        if(isAppContext && !scope?.is_manager){
          ensureAppOwner();
        } else if(els.owner){
          els.owner.disabled = false;
        }
      }
      if(els.role){
        els.role.innerHTML = '<option value="">Função operacional</option>' + roles.map(r=>`<option value="${esc(r.id||'')}">${esc(r.name||r.id||'')}</option>`).join('');
        if(currentRole && Array.from(els.role.options).some(o=>String(o.value)===currentRole)) els.role.value = currentRole;
      }
    } catch(_){ }
  }
  function syncFromParentScope(){
    const portal = root.closest('.rp-client-portal');
    const app = root.closest('.rp-app');
    const parentClient = portal?.querySelector('#rpcp-client') || app?.querySelector('[data-scope-client]');
    const parentProject = portal?.querySelector('#rpcp-project') || app?.querySelector('[data-scope-project]');
    if(parentClient && els.client && parentClient.value && els.client.value !== parentClient.value){ els.client.value = parentClient.value; }
    if(parentProject && els.project){ syncProjects(); if(parentProject.value && Array.from(els.project.options).some(o=>o.value===parentProject.value)){ els.project.value = parentProject.value; } }
  }
  function parsePlaceComponents(place){ const out={district:'',county:'',city:''}; const comps=Array.isArray(place?.address_components)?place.address_components:[]; comps.forEach(c=>{ const types=Array.isArray(c.types)?c.types:[]; if(types.includes('administrative_area_level_1')) out.district=c.long_name||out.district; if(types.includes('administrative_area_level_2')) out.county=(c.long_name||'').replace(/^Área Metropolitana do /i,'')||out.county; if(types.includes('locality')) out.city=c.long_name||out.city; if(!out.city && types.includes('postal_town')) out.city=c.long_name||out.city; if(!out.city && types.includes('administrative_area_level_3')) out.city=c.long_name||out.city; }); return out; }
  function normalizedAddressQuery(value){ const raw=String(value||'').trim(); if(!raw) return ''; return /(?:^|,|\s)portugal\s*$/i.test(raw)?raw:(raw+', Portugal'); }
  function showSelectionMarker(item){ ensureGoogleMap(); if(!map || !(window.google&&google.maps)) return; const lat=parseFloat(item?.lat),lng=parseFloat(item?.lng); if(!Number.isFinite(lat)||!Number.isFinite(lng)) return; if(previewMarker){ try{previewMarker.setMap(null);}catch(_){} } previewMarker=new google.maps.Marker({map,position:{lat,lng},title:item?.name||item?.address||'PDV selecionado',zIndex:9999,animation:google.maps.Animation?google.maps.Animation.DROP:null}); map.setCenter({lat,lng}); map.setZoom(16); els.countMap.textContent=String(Math.max(1,markers.length+1)); }
  function applyPlaceToForm(place, origin){ if(!place) return; const loc=place.geometry?.location; const meta=parsePlaceComponents(place); const payload={name:(place.name&&place.name!==place.formatted_address)?place.name:els.name.value,address:place.formatted_address||els.address.value,lat:loc?.lat?.(),lng:loc?.lng?.(),place_id:place.place_id||'',website:place.website||'',phone:place.formatted_phone_number||'',contact_person:place.name||els.contact.value||'',district:meta.district||'',county:meta.county||'',city:meta.city||'',source:'google'}; lastResolvedAddress=String(payload.address||'').trim(); applySelected(payload, origin||'autocomplete'); if(meta.district && !els.district.value){ els.district.value=meta.district; fillDependent(); } else if(meta.district && els.district.value!==meta.district && (scopeFilters?.countiesByDistrict?.[meta.district] || scopeFilters?.citiesByDistrict?.[meta.district])){ els.district.value=meta.district; fillDependent(); }
    if(meta.county && Array.from(els.county.options).some(o=>o.value===meta.county)) els.county.value=meta.county;
    if(meta.city && Array.from(els.city.options).some(o=>o.value===meta.city)) els.city.value=meta.city;
    setStatus('Morada validada com autocomplete. Coordenadas preenchidas automaticamente.'); }
  async function geocodeFreeAddress(){ const raw=(els.address.value||'').trim(); if(!raw || !googleReady || !geocoder) return null; return new Promise((resolve)=>{ geocoder.geocode({address:normalizedAddressQuery(raw),region:'pt',componentRestrictions:{country:'PT'}}, (res,status)=>{ if(status==='OK' && Array.isArray(res) && res[0]){ applyPlaceToForm(res[0],'geocode'); return resolve(res[0]); } resolve(null); }); }); }
  function bindAddressAutocomplete(){
    if(!googleReady || !(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete) || !els.address) return false;
    if(!addressAutocomplete){
      const addressFields=['formatted_address','geometry','name','place_id','address_components'];
      if(googlePlacesContactEnrichment){addressFields.push('website','formatted_phone_number');}
      addressAutocomplete = new google.maps.places.Autocomplete(els.address,{componentRestrictions:{country:'pt'}, fields:addressFields});
      addressAutocomplete.addListener('place_changed',()=>{ const p=addressAutocomplete.getPlace(); if(!p) return; applyPlaceToForm(p,'autocomplete'); });
    }
    if(!els.address.dataset.rpAutocompleteBound){
      els.address.dataset.rpAutocompleteBound='1';
      els.address.addEventListener('focus',()=>{ loadGoogle().catch(()=>{}); bindAddressAutocomplete(); });
      els.address.addEventListener('click',()=>{ loadGoogle().catch(()=>{}); bindAddressAutocomplete(); });
      els.address.addEventListener('keydown',()=>{ loadGoogle().catch(()=>{}); bindAddressAutocomplete(); }, {once:true});
      els.address.addEventListener('input',()=>{ const current=String(els.address.value||'').trim(); if(lastResolvedAddress && current!==lastResolvedAddress){ lastResolvedAddress=''; els.lat.value=''; els.lng.value=''; els.placeId.value=''; selected=null; if(previewMarker){ try{previewMarker.setMap(null);}catch(_){} previewMarker=null; } } });
      els.address.addEventListener('blur',()=>{ setTimeout(()=>{ if((!els.lat.value || !els.lng.value) && els.address.value.trim()){ geocodeFreeAddress().catch(()=>{}); } },220); });
      els.address.addEventListener('keydown',(ev)=>{ if(ev.key==='Enter'){ ev.preventDefault(); geocodeFreeAddress().catch(()=>{}); } });
    }
    return true;
  }
  function setupGoogleHelpers(){
    if(!(window.google && google.maps)) return false;
    googleReady=true;
    if(!geocoder){ geocoder=new google.maps.Geocoder(); }
    if(!(google.maps.places && google.maps.places.PlacesService)){ return false; }
    // 9CK: PlacesService não precisa de um mapa. Um div simples evita um carregamento de mapa faturável.
    if(!placesSvc){ placesSvc=new google.maps.places.PlacesService(document.createElement('div')); }
    bindAddressAutocomplete();
    if(google.maps.places && google.maps.places.Autocomplete){
      if(els.q && !els.q.dataset.rpAutocomplete){
        const acQ = new google.maps.places.Autocomplete(els.q,{ componentRestrictions:{country:'pt'}, fields:['formatted_address','geometry','name','place_id','address_components']});
        els.q.dataset.rpAutocomplete='1';
        acQ.addListener('place_changed',()=>{ const p=acQ.getPlace(); if(!p) return; const item={name:p.name||'',address:p.formatted_address||'',lat:p.geometry?.location?.lat?.(),lng:p.geometry?.location?.lng?.(),place_id:p.place_id||'',contact_person:p.name||'',source:'google'}; renderList([], [item]); renderMarkers([item]); setStatus('Sugestão Google carregada no mapa.'); });
      }
      if(els.q && !els.q.dataset.rpAutocompleteFocus){
        els.q.dataset.rpAutocompleteFocus='1';
        els.q.addEventListener('focus',()=>{ loadGoogle().catch(()=>{}); });
        els.q.addEventListener('click',()=>{ loadGoogle().catch(()=>{}); });
      }
    }
    return true;
  }
  async function loadGoogle(){
    const hasPlaces = !!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete && google.maps.places.PlacesService);
    if(hasPlaces){ setupGoogleHelpers(); return true; }
    if(window.FieldFlowGoogleMaps && typeof window.FieldFlowGoogleMaps.load === 'function'){
      const loaded = await window.FieldFlowGoogleMaps.load({key:gmKey,libraries:['places'],language:'pt-PT',region:'PT'}).catch(()=>false);
      if(loaded && window.google && google.maps && google.maps.places){ return setupGoogleHelpers(); }
    }
    if(window.google && google.maps && google.maps.importLibrary){
      try { await google.maps.importLibrary('maps'); await google.maps.importLibrary('places'); } catch(_) {}
      if(window.google && google.maps && google.maps.places){ return setupGoogleHelpers(); }
    }
    if(!gmKey){ return false; }
    const promiseKey = '__routesProGooglePromise';
    if(!window[promiseKey]){
      window[promiseKey] = new Promise((res,rej)=>{
        const callbackName = '__routesProGoogleReady_' + Math.random().toString(36).slice(2);
        window[callbackName] = ()=>{ try{ delete window[callbackName]; }catch(_){ window[callbackName]=undefined; } res(); };
        const existing=document.querySelector('script[data-routespro-google="1"],script[src*="maps.googleapis.com/maps/api/js"]');
        if(existing){
          let tries=0;
          const wait=()=>{
            tries++;
            if(window.google && google.maps && google.maps.places){ return res(); }
            if(window.google && google.maps && google.maps.importLibrary){ google.maps.importLibrary('places').then(res).catch(rej); return; }
            if(tries>=100){ rej(new Error('Tempo esgotado ao carregar Google Places.')); return; }
            setTimeout(wait,120);
          };
          wait();
          return;
        }
        const script=document.createElement('script');
        script.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(gmKey)+'&libraries=places&loading=async&callback='+callbackName;
        script.async=true; script.defer=true; script.dataset.routesproGoogle='1';
        script.onerror=(err)=>{ try{ delete window[callbackName]; }catch(_){ window[callbackName]=undefined; } rej(err); };
        document.head.appendChild(script);
      });
    }
    try { await window[promiseKey]; } catch(_) { return false; }
    return setupGoogleHelpers();
  }
  function ensureGoogleMap(){ const mapEl=root.querySelector('#rpc-map'); if(!mapEl || !(window.google && google.maps)) return; const visible = mapEl.offsetWidth > 40 && mapEl.offsetHeight > 40; if(!visible){ setTimeout(ensureGoogleMap, 180); return; } if(!map){ map=new google.maps.Map(mapEl,{center:{lat:38.7223,lng:-9.1393},zoom:7,mapTypeId:google.maps.MapTypeId.ROADMAP,gestureHandling:'greedy',streetViewControl:false,fullscreenControl:true,mapTypeControl:false}); }
    setTimeout(()=>{ try{ google.maps.event.trigger(map,'resize'); }catch(_){ } },80); }
  function applyOptions(selectEl, placeholder, items, currentValue){
    if(!selectEl) return;
    const wanted = String(currentValue ?? selectEl.value ?? '');
    selectEl.innerHTML = `<option value="">${placeholder}</option>` + items.map(item=>`<option value="${esc(item.value)}">${esc(item.label)}</option>`).join('');
    if(wanted && Array.from(selectEl.options).some(o=>String(o.value)===wanted)) selectEl.value = wanted;
  }
  function buildFilterScopeParams(){
    const params = new URLSearchParams();
    if(els.client?.value) params.set('client_id', els.client.value);
    if(els.project?.value) params.set('project_id', els.project.value);
    const activeOwnerId = effectiveOwnerId(); if(activeOwnerId){ params.set('owner_user_id', activeOwnerId); if(isAppContext) params.set('include_unassigned','1'); }
    if(!isAppContext && els.role?.value){ params.set('role', String(els.role.value)); }
    if(els.district?.value) params.set('district', els.district.value);
    if(els.county?.value) params.set('county', els.county.value);
    if(els.city?.value) params.set('city', els.city.value);
    if(els.category?.value) params.set('category_id', els.category.value);
    if(els.subcategory?.value) params.set('subcategory_id', els.subcategory.value);
    return params;
  }
  async function loadGeo(force){
    const reqId = ++scopeReqId;
    const params = buildFilterScopeParams();
    const data = await j(api+'commercial-filters?'+params.toString());
    if(reqId !== scopeReqId) return;
    scopeFilters = {
      districts: Array.isArray(data.districts) ? data.districts : [],
      countiesByDistrict: data.countiesByDistrict || {},
      citiesByDistrict: data.citiesByDistrict || {},
      categories: Array.isArray(data.categories) ? data.categories : []
    };
    categories = scopeFilters.categories.slice();
    applyOptions(els.district, 'Distrito', scopeFilters.districts.map(x=>({value:x,label:x})), els.district.value);
    fillDependent();
    fillCategories();
  }
  function fillDependent(){
    const d=els.district.value||'';
    const currentCounty = els.county.value || '';
    const currentCity = els.city.value || '';
    const counties=(scopeFilters?.countiesByDistrict?.[d]||[]);
    const cities=(scopeFilters?.citiesByDistrict?.[d]||[]);
    applyOptions(els.county,'Concelho',counties.map(x=>({value:x,label:x})),currentCounty);
    applyOptions(els.city,'Cidade',cities.map(x=>({value:x,label:x})),currentCity);
  }
  function fillCategories(){
    const seen={};
    const roots=categories.filter(x=>!parseInt(x.parent_id||0,10)).filter(x=>{ const k=(x.slug||x.name||'').toString().trim().toLowerCase(); if(!k || seen[k]) return false; seen[k]=1; return true; });
    applyOptions(els.category,'Categoria',roots.map(x=>({value:x.id,label:x.name})),els.category.value);
    fillSubcategories();
  }
  function fillSubcategories(){
    const pid=parseInt(els.category.value||'0',10);
    const seen={};
    const subs=categories.filter(x=>parseInt(x.parent_id||0,10)===pid).filter(x=>{ const k=(x.name||'').trim().toLowerCase(); if(seen[k]) return false; seen[k]=1; return true; });
    applyOptions(els.subcategory,'Subcategoria',subs.map(x=>({value:x.id,label:x.name})),els.subcategory.value);
  }
  async function runInternalAndRender(message){
    if(!appScopeReady()){
      els.results.innerHTML = '<div class="rp-item">Seleciona cliente e campanha válidos para carregar a Base.</div>';
      els.countInternal.textContent='0';
      els.countGoogle.textContent='0';
      els.countMap.textContent='0';
      setStatus('Base bloqueada ao teu cliente, campanha e owner operativo.');
      return [];
    }
    setStatus(message || 'A carregar PDVs internos...');
    const internal = await runInternal();
    renderList(internal, []);
    renderMarkers(internal);
    setStatus('Pesquisa interna concluída.');
    return internal;
  }
  function scheduleInternalRun(delay){
    clearTimeout(autoRunTimer);
    autoRunTimer = setTimeout(()=>{ runInternalAndRender('A atualizar filtros da Base...').catch(err=>setStatus(err.message||'Falha na atualização da Base.')); }, typeof delay === 'number' ? delay : 220);
  }
  async function refreshFilterOptionsAndResults(message, delay){
    try{
      await loadGeo(true);
      if(!appScopeReady()){
        els.results.innerHTML = '<div class="rp-item">Seleciona cliente e campanha válidos para carregar a Base.</div>';
        els.countInternal.textContent='0';
        els.countGoogle.textContent='0';
        els.countMap.textContent='0';
        setStatus('Base bloqueada ao teu cliente, campanha e owner operativo.');
        return;
      }
      if(typeof delay === 'number') scheduleInternalRun(delay);
      else await runInternalAndRender(message || 'A atualizar filtros da Base...');
    }catch(err){
      setStatus(err?.message || 'Falha na atualização da Base.');
    }
  }
  function markerColor(src){ return src==='google' ? '#dc2626' : '#2563eb'; }
  function renderMarkers(items){ if(!map || !(window.google && google.maps)) return; markers.forEach(m=>m.setMap(null)); markers=[]; if(previewMarker){ try{previewMarker.setMap(null);}catch(_){} previewMarker=null; } const bounds=[]; items.forEach(item=>{ const lat=parseFloat(item.lat), lng=parseFloat(item.lng); if(!Number.isFinite(lat)||!Number.isFinite(lng)) return; const marker=new google.maps.Marker({map, position:{lat,lng}, title:item.name||item.address||'PDV'}); marker.addListener('click',()=>applySelected(item,'map')); markers.push(marker); bounds.push({lat,lng}); }); els.countMap.textContent=String(bounds.length); if(!bounds.length){ map.setCenter({lat:38.7223,lng:-9.1393}); map.setZoom(7); return; } if(bounds.length===1){ map.setCenter(bounds[0]); map.setZoom(14); return; } const gb=new google.maps.LatLngBounds(); bounds.forEach(pt=>gb.extend(pt)); map.fitBounds(gb,30); }
  function applySelected(item, origin){ selected=item||null; els.name.value=item?.name||''; els.address.value=item?.address||''; els.phone.value=item?.phone||''; els.email.value=item?.email||''; els.contact.value=item?.contact_person||item?.name||''; els.website.value=item?.website||''; els.lat.value=(item?.lat ?? '')!==''?String(item.lat):''; els.lng.value=(item?.lng ?? '')!==''?String(item.lng):''; els.placeId.value=item?.place_id||''; if(item?.address && Number.isFinite(parseFloat(item?.lat)) && Number.isFinite(parseFloat(item?.lng))) lastResolvedAddress=String(item.address).trim(); if(origin==='autocomplete'||origin==='geocode') showSelectionMarker(item); setStatus((origin==='map'?'PDV selecionado no mapa.':'PDV carregado.') + ' Podes guardar ou ajustar antes de submeter.'); }
  function renderList(internal, googleItems){ const all=[]; if(internal.length){ all.push('<div class="rp-item" style="background:#eff6ff;font-weight:700">PDVs existentes</div>'); internal.forEach(item=> all.push(`<div class="rp-item"><h4>${esc(item.name||'PDV')}</h4><div class="rp-meta">${esc(item.address||'')}</div><div class="rp-meta">${esc(item.phone||'')} ${item.city?(' · '+esc(item.city)):''}</div><div style="margin-top:10px"><button class="rp-btn ghost" data-kind="internal" data-id="${item.id||''}">Usar</button></div></div>`)); }
    if(googleItems.length){ all.push('<div class="rp-item" style="background:#fef2f2;font-weight:700">Novos PDVs Google</div>'); googleItems.forEach((item,idx)=> all.push(`<div class="rp-item"><h4>${esc(item.name||'PDV')}</h4><div class="rp-meta">${esc(item.address||'')}</div><div style="margin-top:10px"><button class="rp-btn ghost" data-kind="google" data-idx="${idx}">Usar</button></div></div>`)); }
    els.results.innerHTML=all.join('') || '<div class="rp-item">Sem resultados.</div>';
    els.results.querySelectorAll('button[data-kind="internal"]').forEach(btn=>btn.addEventListener('click',()=>{ const id=parseInt(btn.dataset.id,10); const item=internal.find(x=>parseInt(x.id,10)===id); if(item) applySelected(item,'list'); }));
    els.results.querySelectorAll('button[data-kind="google"]').forEach(btn=>btn.addEventListener('click',()=>{ const item=googleItems[parseInt(btn.dataset.idx,10)]; if(item) applySelected(item,'list'); }));
  }
  function buildParams(){ const p=new URLSearchParams(); if(els.client?.value) p.set('client_id',els.client.value); if(els.project?.value) p.set('project_id',els.project.value); const ownerId = effectiveOwnerId(); if(ownerId){ p.set('owner_user_id', ownerId); if(isAppContext) p.set('include_unassigned','1'); } if(!isAppContext && els.role?.value) p.set('role', String(els.role.value)); if(els.district.value) p.set('district',els.district.value); if(els.county.value) p.set('county',els.county.value); if(els.city.value) p.set('city',els.city.value); if(els.category.value) p.set('category_id',els.category.value); if(els.subcategory.value) p.set('subcategory_id',els.subcategory.value); if(els.q.value) p.set('q',els.q.value); p.set('per_page','200'); return p; }
  async function fetchCommercialPages(params, fetchAll){ const merged=[]; let page=1, total=0; const wantsAll=!!fetchAll; while(true){ const req=new URLSearchParams(params.toString()); req.set('page', String(page)); const data=await j(api+'commercial-search?'+req.toString()); const items=Array.isArray(data.items)?data.items:[]; total=parseInt(data.total||items.length||0,10)||0; merged.push(...items); if(!wantsAll) break; if(!items.length || merged.length>=total || page>=50) break; page++; } return {items:merged,total}; }
  async function runInternal(){ const params=buildParams(); const fetchAll=isPortalContext || !!(els.project?.value); const data=await fetchCommercialPages(params, fetchAll); const items=Array.isArray(data.items)?data.items:[]; els.countInternal.textContent=String(data.total||items.length); return items; }
  function geocodeArea(){ return new Promise((resolve)=>{ if(!googleReady || !geocoder) return resolve(null); const pieces=[els.city.value,els.county.value,els.district.value,'Portugal'].filter(Boolean); if(!pieces.length) return resolve(null); geocoder.geocode({address:pieces.join(', ')}, (res,status)=>{ if(status==='OK' && res && res[0]){ const g=res[0].geometry.location; resolve({lat:g.lat(),lng:g.lng()}); } else resolve(null); }); }); }
  function findPlaceDetails(placeId){ return new Promise((resolve)=>{ if(!placesSvc || !placeId) return resolve({}); placesSvc.getDetails({placeId, fields:['name','formatted_address','formatted_phone_number','website','geometry','place_id']}, (res,status)=>{ if(status===google.maps.places.PlacesServiceStatus.OK && res){ resolve({phone:res.formatted_phone_number||'',website:res.website||'',lat:res.geometry?.location?.lat?.(),lng:res.geometry?.location?.lng?.()}); } else resolve({}); }); }); }
  async function runGoogle(){ if(!googleReady || !placesSvc){ els.countGoogle.textContent='0'; return []; } const center=await geocodeArea(); const keyword=[els.subcategory.options[els.subcategory.selectedIndex]?.text||'', els.category.options[els.category.selectedIndex]?.text||'', els.q.value||''].filter(Boolean).join(' ').trim() || 'estabelecimento comercial'; return new Promise((resolve)=>{ const req={query:[keyword, els.city.value, els.county.value, els.district.value, 'Portugal'].filter(Boolean).join(', '), language:'pt-PT'}; if(center){ req.location=new google.maps.LatLng(center.lat,center.lng); req.radius=els.city.value?12000:(els.county.value?25000:50000); } placesSvc.textSearch(req, async (results,status)=>{ if(status!==google.maps.places.PlacesServiceStatus.OK || !Array.isArray(results)){ els.countGoogle.textContent='0'; return resolve([]); } const items=[]; for(const r of results.slice(0,20)){ const extra=googlePlacesContactEnrichment?await findPlaceDetails(r.place_id):{}; items.push({name:r.name||'',address:r.formatted_address||r.vicinity||'',lat:extra.lat ?? r.geometry?.location?.lat?.(),lng:extra.lng ?? r.geometry?.location?.lng?.(),place_id:r.place_id||'',phone:extra.phone||'',website:extra.website||'',source:'google'}); } els.countGoogle.textContent=String(items.length); resolve(items); }); }); }
  root.querySelector('#rpc-run').addEventListener('click', async ()=>{ try{ await runInternalAndRender('A carregar PDVs internos...'); }catch(err){ setStatus(err.message||'Falha na pesquisa interna.'); }});
  root.querySelector('#rpc-google').addEventListener('click', async ()=>{ try{ setStatus('A combinar BD interna e Google...'); const internal=await runInternal(); const googleItems=await runGoogle(); const merged=[...internal, ...googleItems]; renderList(internal,googleItems); renderMarkers(merged); setStatus('Pesquisa concluída.'); }catch(err){ setStatus(err.message||'Falha na pesquisa Google.'); }});
  root.querySelector('#rpc-save').addEventListener('click', async ()=>{ try{ if((!els.lat.value || !els.lng.value) && els.address.value.trim()){ setStatus('A validar morada antes de guardar...'); await geocodeFreeAddress(); } if(els.address.value.trim() && (!els.lat.value || !els.lng.value)){ throw new Error('A morada foi reconhecida, mas não foi possível obter coordenadas. Seleciona uma sugestão do Google Autocomplete.'); } const payload={name:els.name.value,address:els.address.value,phone:els.phone.value,email:els.email.value,contact_person:els.contact.value,website:els.website.value,lat:els.lat.value,lng:els.lng.value,place_id:els.placeId.value,client_id:parseInt(els.client?.value||'0',10)||null,project_id:parseInt(els.project?.value||'0',10)||null,category_id:parseInt(els.category.value||'0',10)||null,subcategory_id:parseInt(els.subcategory.value||'0',10)||null,district:els.district.value,county:els.county.value,city:els.city.value,source:selected?.source||((els.placeId.value||els.lat.value||els.lng.value)?'google':'manual'),location_type:'pdv',replace_existing:1}; const res=await j(api+'locations',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify(payload)}); await loadGeo(true); scheduleInternalRun(80); showSelectionMarker(payload); setStatus(res.existing?'PDV existente atualizado e sincronizado com a campanha.':'PDV guardado e sincronizado com a campanha.'); }catch(err){ setStatus(err.message||'Falha ao guardar PDV.'); }});
  els.client?.addEventListener('change', async ()=>{ syncProjects(); await syncOwnersAndRoles().catch(()=>{}); await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 60); });
  els.project?.addEventListener('change', async ()=>{ await syncOwnersAndRoles().catch(()=>{}); await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 60); });
  els.owner?.addEventListener('change', async ()=>{ await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 60); });
  els.role?.addEventListener('change', async ()=>{ await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 60); });
  els.district.addEventListener('change', async ()=>{ fillDependent(); await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 80); });
  els.county.addEventListener('change', async ()=>{ await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 80); });
  els.city.addEventListener('change', async ()=>{ await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 80); });
  els.category.addEventListener('change', async ()=>{ fillSubcategories(); await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 80); });
  els.subcategory.addEventListener('change', async ()=>{ await refreshFilterOptionsAndResults('A atualizar filtros da Base...', 80); });
  els.q.addEventListener('input', ()=>{ scheduleInternalRun(260); });
  if(isAppContext && els.role){ els.role.value=''; els.role.disabled=true; els.role.setAttribute('aria-hidden','true'); }
  fillClients(); syncProjects(); syncFromParentScope(); lockAppScope(); await syncOwnersAndRoles().catch(()=>{}); ensureAppOwner(); await loadGeo(); await loadGoogle().catch(()=>{}); bindAddressAutocomplete(); setTimeout(()=>{ loadGoogle().catch(()=>{}); bindAddressAutocomplete(); },300); setTimeout(()=>{ loadGoogle().catch(()=>{}); bindAddressAutocomplete(); },900); if(els.q){ els.q.dispatchEvent(new Event('focus')); } ensureGoogleMap(); await runInternalAndRender('A carregar Base Comercial...'); root.addEventListener('routespro:scope-change', async (ev)=>{ const d=ev.detail||{}; if(els.client && d.client_id!==undefined){ els.client.value=String(d.client_id||''); syncProjects(); } if(els.project && d.project_id!==undefined && Array.from(els.project.options).some(o=>o.value===String(d.project_id||''))){ els.project.value=String(d.project_id||''); } lockAppScope(); await syncOwnersAndRoles().catch(()=>{}); await loadGeo(true); scheduleInternalRun(40); }); root.addEventListener('routespro:panel-open', ()=>{ syncFromParentScope(); lockAppScope(); syncOwnersAndRoles().then(()=>{ ensureAppOwner(); }).catch(()=>{}); ensureGoogleMap(); bindAddressAutocomplete(); loadGoogle().catch(()=>{}); setTimeout(()=>{ ensureGoogleMap(); bindAddressAutocomplete(); loadGoogle().catch(()=>{}); scheduleInternalRun(40); },220); }); window.addEventListener('resize', ()=>{ ensureGoogleMap(); if(map && window.google && google.maps && google.maps.event){ google.maps.event.trigger(map,'resize'); } });
  if(window.MutationObserver){
    const mo=new MutationObserver(()=>{
      if(root.offsetParent!==null){
        ensureGoogleMap();
        bindAddressAutocomplete();
        if(map && window.google && google.maps && google.maps.event){ google.maps.event.trigger(map,'resize'); }
      }
    });
    mo.observe(document.body,{attributes:true,childList:true,subtree:true});
  }
})();
</script>
<?php
        return ob_get_clean();
    }

    public static function front_routes($atts = []) {
        $guard = self::front_guard();
        if ($guard) return $guard;
        if (!\RoutesPro\Support\Permissions::is_manager() && class_exists('\RoutesPro\Front\SelfRoutePlannerShortcodeRenderer')) {
            return \RoutesPro\Front\SelfRoutePlannerShortcodeRenderer::render(is_array($atts) ? $atts : []);
        }
        $atts = shortcode_atts(['client_id'=>0, 'project_id'=>0], is_array($atts) ? $atts : [], 'fieldflow_front_routes');
        $initial_client_id = absint($atts['client_id'] ?? 0);
        $initial_project_id = absint($atts['project_id'] ?? 0);
        [$initial_client_id, $initial_project_id] = \RoutesPro\Support\Permissions::resolve_front_context($initial_client_id, $initial_project_id);
        $theme = self::front_theme($initial_client_id, $initial_project_id);
        $opts = \RoutesPro\Admin\Settings::get();
        $gmKey = trim((string)($opts['google_maps_key'] ?? ''));
        $nonce = wp_create_nonce('wp_rest');
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $clients = \RoutesPro\Support\Permissions::filter_clients($wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: []);
        $projects = \RoutesPro\Support\Permissions::filter_projects($wpdb->get_results("SELECT id,name,client_id FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: []);
        $endpoint_profiles = [];
        if (class_exists('\\RoutesPro\\Services\\RouteEndpointProfileService')) {
            foreach ($projects as $project_row) {
                $project_id = absint($project_row['id'] ?? 0);
                if ($project_id <= 0) continue;
                $cfg = \RoutesPro\Services\RouteEndpointProfileService::getProjectConfig($project_id);
                $profile = is_array($cfg['default'] ?? null) ? (array)$cfg['default'] : [];
                if (empty($profile['enabled'])) continue;
                $endpoint_profiles[$project_id] = [
                    'start_point'=>(array)($profile['start_point'] ?? []),
                    'end_point'=>(array)($profile['end_point'] ?? []),
                    'return_to_start'=>!empty($profile['return_to_start']) ? 1 : 0,
                    'source'=>'campaign_default',
                ];
            }
        }
        $scope = \RoutesPro\Support\Permissions::get_scope();
        ob_start(); ?>
<style>
.rp-front-routes{--rp-primary:<?php echo esc_attr($theme['primary']); ?>;--rp-accent:<?php echo esc_attr($theme['accent']); ?>;font-family:<?php echo esc_attr($theme['font']); ?>;font-size:<?php echo esc_attr($theme['size']); ?>px;color:#0f172a}
.rp-front-routes .rp-card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 18px 48px rgba(15,23,42,.08);padding:18px}
.rp-front-routes .rp-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(340px,.8fr);gap:16px}
.rp-front-routes .rp-form,.rp-front-routes .rp-toolbar{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.rp-front-routes .rp-toolbar{grid-template-columns:repeat(4,minmax(0,1fr));margin-top:10px}
.rp-front-routes .rp-full{grid-column:1/-1}
.rp-front-routes input,.rp-front-routes select{border:1px solid #cbd5e1;border-radius:14px;padding:11px 12px;width:100%;min-width:0;background:#fff}
.rp-front-routes .rp-btn{border:0;border-radius:14px;padding:12px 14px;background:var(--rp-primary);color:#fff;font-weight:700;cursor:pointer}
.rp-front-routes .rp-btn.alt{background:var(--rp-accent)}
.rp-front-routes .rp-btn.ghost{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
.rp-front-routes .rp-map{height:430px;min-height:430px;border-radius:20px;overflow:hidden;border:1px solid #dbeafe;background:#e2e8f0;position:relative;z-index:1}
.pac-container{z-index:99999 !important;border-radius:14px;box-shadow:0 18px 40px rgba(15,23,42,.18)}
.rp-front-routes .rp-list,.rp-front-routes .rp-queue{max-height:460px;overflow:auto;margin-top:12px;padding-right:4px}
.rp-front-routes .rp-item,.rp-front-routes .rp-qitem{border:1px solid #e2e8f0;border-radius:18px;padding:14px;background:#fff;margin-bottom:10px}
.rp-front-routes .rp-item h4,.rp-front-routes .rp-qitem h4{margin:0 0 6px;font-size:18px;line-height:1.15;color:#0f172a}
.rp-front-routes .rp-meta{color:#64748b;font-size:13px;line-height:1.45}
.rp-front-routes .rp-qitem{display:grid;grid-template-columns:42px 1fr auto;gap:12px;align-items:center}
.rp-front-routes .rp-no{width:38px;height:38px;border-radius:999px;background:#111827;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
.rp-front-routes .rp-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0}
.rp-front-routes .rp-summary div{background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px}
.rp-front-routes .rp-summary strong{display:block;font-size:24px;color:#0f172a}
.rp-front-routes .rp-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:700;font-size:12px;margin-bottom:8px}
.rp-front-routes .rp-section-title{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.rp-front-routes .rp-note{margin-top:12px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:16px;padding:12px;color:#475569}
.rp-front-routes .rp-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
@media (max-width:1080px){.rp-front-routes .rp-grid,.rp-front-routes .rp-form,.rp-front-routes .rp-toolbar,.rp-front-routes .rp-summary{grid-template-columns:1fr}.rp-front-routes .rp-map{height:360px;min-height:360px}}
</style>
<div class="rp-front-routes" data-nonce="<?php echo esc_attr($nonce); ?>" data-gmkey="<?php echo esc_attr($gmKey); ?>" data-google-waypoint-optimization="<?php echo !empty(\RoutesPro\Support\Config::get('google_routes_waypoint_optimization_enabled', 0)) ? '1' : '0'; ?>" data-can-manage="<?php echo \RoutesPro\Support\Permissions::can_access_front() ? '1' : '0'; ?>" data-projects='<?php echo esc_attr(wp_json_encode($projects)); ?>' data-client-id="<?php echo esc_attr($initial_client_id); ?>" data-project-id="<?php echo esc_attr($initial_project_id); ?>" data-scope='<?php echo esc_attr(wp_json_encode($scope)); ?>' data-geopt='<?php echo esc_attr(wp_json_encode([
            'districts' => \RoutesPro\Support\GeoPT::districts(),
            'countiesByDistrict' => \RoutesPro\Support\GeoPT::counties_by_district(),
            'citiesByDistrict' => \RoutesPro\Support\GeoPT::cities_by_district(),
        ])); ?>'>
  <div class="rp-card" style="margin-bottom:16px">
    <div class="rp-section-title">
      <div>
        <div class="rp-chip">Rotas Front</div>
        <h3 style="margin:0 0 6px;color:#0f172a">Descobrir PDVs e construir rota</h3>
        <div class="rp-meta">Filtra por campanha, geografia, categoria e subcategoria, mistura base interna com Google, adiciona à fila e associa a rota à campanha.</div>
      </div>
    </div>
      <div class="rp-form" style="margin-top:14px">
        <select id="rpf-client" class="rp-full"><option value="">Cliente</option><?php foreach($clients as $c): ?><option value="<?php echo intval($c['id']); ?>"><?php echo esc_html($c['name']); ?></option><?php endforeach; ?></select>
        <select id="rpf-project"><option value="">Campanha</option></select>
        <input type="date" id="rpf-date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
        <select id="rpf-route-status"><option value="draft">Rascunho</option><option value="planned">Planeada</option></select>
        <input type="text" id="rpf-start-point" placeholder="Ponto de partida, morada ou coordenadas">
        <input type="text" id="rpf-end-point" placeholder="Ponto de chegada, morada ou coordenadas">
      </div>
    <div class="rp-toolbar" style="margin-top:12px">
      <select id="rpf-district"><option value="">Distrito</option></select>
      <select id="rpf-county"><option value="">Concelho</option></select>
      <select id="rpf-city"><option value="">Cidade</option></select>
      <select id="rpf-category"><option value="">Categoria</option></select>
      <select id="rpf-subcategory"><option value="">Subcategoria</option></select>
      <input type="search" id="rpf-q" placeholder="Nome, morada ou contacto" autocomplete="off">
      <button type="button" class="rp-btn" id="rpf-search">PDVs existentes</button>
      <button type="button" class="rp-btn alt" id="rpf-google">Google + Internos</button>
    </div>
    <div class="rp-actions">
      <button type="button" class="rp-btn ghost" id="rpf-openmaps">Abrir no Google Maps</button>
      <button type="button" class="rp-btn ghost" id="rpf-recalc" title="Uma chamada Google com a ordem atual">Calcular percurso real</button>
      <button type="button" class="rp-btn ghost" id="rpf-clear">Limpar</button>
<button type="button" class="rp-btn alt" id="rpf-save-draft">Guardar rascunho</button><button type="button" class="rp-btn" id="rpf-save-route">Publicar rota</button>
    </div>
  </div>
  <div class="rp-grid">
    <div class="rp-card">
      <div id="rpf-map" class="rp-map"></div>
      <div class="rp-list" id="rpf-results"></div>
    </div>
    <div class="rp-card">
      <h4 style="margin:0;color:#0f172a">PDVs adicionados à rota</h4>
      <div class="rp-summary">
        <div><span>Paragens</span><strong id="rpf-total">0</strong></div>
        <div><span>Kms</span><strong id="rpf-km">0</strong></div>
        <div><span>Viagem</span><strong id="rpf-travel">0m</strong></div>
        <div><span>Total</span><strong id="rpf-total-time">0m</strong></div>
      </div>
      <div class="rp-queue" id="rpf-queue"></div>
      <p class="rp-note" id="rpf-status-msg">Adiciona PDVs da lista para construir a rota. Podes misturar resultados internos e Google sem duplicar paragens.</p>
    </div>
  </div>
</div>
<script>
(async function(){
  const root=document.currentScript.previousElementSibling; if(!root || !root.classList.contains('rp-front-routes')) return;
  const nonce=root.dataset.nonce, gmKey=root.dataset.gmkey||'', canManage=root.dataset.canManage==='1';
  const api='<?php echo esc_url(rest_url('routespro/v1/')); ?>';
  const projects=JSON.parse(root.dataset.projects||'[]');
  const endpointProfiles=JSON.parse(root.dataset.endpointProfiles||'{}');
  const initialClientId = String(root.dataset.clientId || '');
  const initialProjectId = String(root.dataset.projectId || '');
  const scope=JSON.parse(root.dataset.scope||'{}');
  const geoPT=JSON.parse(root.dataset.geopt||'{}');
  const currentUserId = <?php echo (int) get_current_user_id(); ?>;
  const isAppContext = !!root.closest('.rp-app');
  let map, markers=[], routeLine=null, googleReady=false, googleMap=null, placesSvc=null, geocoder=null, directionsService=null, infoWindow=null, routeRenderer=null, mapObserver=null, discoveryAutocomplete=null, startPointAutocomplete=null, endPointAutocomplete=null;
  let queue=[]; let resultItems=[]; let categories=[]; let routeCalcToken=0, routeCalcTimer=null; let manualOrderLocked=false;
  const els={ q:root.querySelector('#rpf-q'), results:root.querySelector('#rpf-results'), queue:root.querySelector('#rpf-queue'), total:root.querySelector('#rpf-total'), km:root.querySelector('#rpf-km'), travel:root.querySelector('#rpf-travel'), totalTime:root.querySelector('#rpf-total-time'), status:root.querySelector('#rpf-status-msg'), client:root.querySelector('#rpf-client'), project:root.querySelector('#rpf-project'), date:root.querySelector('#rpf-date'), routeStatus:root.querySelector('#rpf-route-status'), startPoint:root.querySelector('#rpf-start-point'), endPoint:root.querySelector('#rpf-end-point'), district:root.querySelector('#rpf-district'), county:root.querySelector('#rpf-county'), city:root.querySelector('#rpf-city'), category:root.querySelector('#rpf-category'), subcategory:root.querySelector('#rpf-subcategory') };
  function esc(s){ return (s==null?'':String(s)).replace(/[&<>]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
  function setStatus(msg){ if(els.status) els.status.textContent=msg||''; }
  function setLocked(el, locked){ if(!el) return; el.disabled=!!locked; el.classList.toggle('ff-locked', !!locked); }
  function lockIfSingle(el){ if(!el) return false; const opts=Array.from(el.options||[]).filter(o=>String(o.value||'')!==''); const one=opts.length===1; if(one) el.value=String(opts[0].value||''); setLocked(el, one); return one; }
  function enforcePortalScopeLocks(){ lockIfSingle(els.client); syncProjects(); lockIfSingle(els.project); }
  function lockCommercialPanel(){ const panel=root.querySelector('#rpcp-commercial-panel .rp-front-commercial'); if(!panel) return; const c=panel.querySelector('#rpc-client'); const p=panel.querySelector('#rpc-project'); const o=panel.querySelector('#rpc-owner'); const r=panel.querySelector('#rpc-role'); if(r){ const wrap=r.closest('label,div')||r; wrap.style.display='none'; }
    const apply=()=>{ if(c){ lockIfSingle(c); }
      if(p){ lockIfSingle(p); }
      if(o){ lockIfSingle(o); }
    };
    setTimeout(apply,50); setTimeout(apply,400); setTimeout(apply,1200);
  }
  async function j(url, opts){ const r=await fetch(url,Object.assign({credentials:'same-origin',headers:{'X-WP-Nonce':nonce}},opts||{})); const t=await r.text(); let d={}; try{d=t?JSON.parse(t):{}}catch(_){d={message:t}} if(!r.ok) throw new Error(d.message||r.statusText); return d; }
  function allowedClientIds(){ return Array.isArray(scope?.client_ids) ? scope.client_ids.map(v=>String(parseInt(v,10))).filter(Boolean) : []; }
  function allowedProjectIds(){ return Array.isArray(scope?.project_ids) ? scope.project_ids.map(v=>String(parseInt(v,10))).filter(Boolean) : []; }
  function setLockedField(el, locked){ if(!el) return; el.disabled = !!locked; el.classList.toggle('ff-locked', !!locked); }
  function maybeLockSingleSelect(el){ if(!el) return false; const choices = Array.from(el.options||[]).filter(o=>String(o.value||'')!==''); const shouldLock = choices.length === 1; if(shouldLock){ el.value = String(choices[0].value||''); } setLockedField(el, shouldLock); return shouldLock; }
  function syncProjects(selected){ const cid=parseInt(els.client?.value||'0',10); const filtered=cid ? projects.filter(x=>parseInt(x.client_id||0,10)===cid) : projects; const wanted=String(selected||els.project?.value||''); els.project.innerHTML='<option value="">Campanha</option>'+filtered.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join(''); if(wanted && Array.from(els.project.options).some(o=>String(o.value)===wanted)) els.project.value=wanted; maybeLockSingleSelect(els.project); }
  function applyEndpointProfile(force=false){ const pid=String(els.project?.value||''); const profile=endpointProfiles[pid]||null; if(!profile)return false; const start=profile.start_point||{}, end=profile.end_point||{}; const canWrite=(input)=>force||!String(input?.value||'').trim()||String(input?.dataset?.endpointProvenance||'')==='campaign_default'; if(els.startPoint&&canWrite(els.startPoint)&&String(start.address||'').trim()){els.startPoint.value=String(start.address||'');els.startPoint.dataset.endpointProvenance='campaign_default';setEndpointCoords(els.startPoint,parseFloat(start.lat),parseFloat(start.lng));} if(els.endPoint&&canWrite(els.endPoint)&&String(end.address||'').trim()){els.endPoint.value=String(end.address||'');els.endPoint.dataset.endpointProvenance=profile.return_to_start?'return_to_start':'campaign_default';setEndpointCoords(els.endPoint,parseFloat((profile.return_to_start?start:end).lat),parseFloat((profile.return_to_start?start:end).lng));} return !!(String(els.startPoint?.value||'').trim()&&String(els.endPoint?.value||'').trim()); }
  function lockRestrictedScope(){
    const clientIds=allowedClientIds();
    const projectIds=allowedProjectIds();
    if(!scope?.is_manager){
      if(els.client && !els.client.value && clientIds.length===1) els.client.value=clientIds[0];
      syncProjects(projectIds.length===1 ? projectIds[0] : '');
      if(els.project && !els.project.value && projectIds.length===1 && Array.from(els.project.options).some(o=>String(o.value)===projectIds[0])) els.project.value=projectIds[0];
      if(els.client) setLockedField(els.client, clientIds.length <= 1 && clientIds.length > 0);
      if(els.project) setLockedField(els.project, projectIds.length <= 1 && projectIds.length > 0);
      return;
    }
    maybeLockSingleSelect(els.client);
    syncProjects('');
    maybeLockSingleSelect(els.project);
  }
  function discoveryScopeReady(){ return scope?.is_manager || (!!els.client?.value && !!els.project?.value); }
  function effectiveDiscoveryOwnerId(){ return (isAppContext && !scope?.is_manager) ? String(currentUserId || '') : ''; }
  function updateDiscoverySearchPlaceholder(){ if(!els.q) return; const projectLabel = els.project && els.project.value ? (els.project.options[els.project.selectedIndex]?.text || 'campanha ativa') : 'campanha selecionada'; els.q.placeholder = 'Nome, morada ou contacto' + (els.project && els.project.value ? ' em ' + projectLabel : ''); }
  function bindDiscoveryAutocomplete(forceRebind=false){ if(!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete) || !els.q) return false; updateDiscoverySearchPlaceholder(); const panel=root.closest('.rp-panel'); const isVisible = !!(root.offsetParent !== null && els.q.offsetParent !== null && (!panel || panel.classList.contains('active'))); if(!els.q.dataset.rpAutocompleteFocus){ els.q.dataset.rpAutocompleteFocus='1'; els.q.addEventListener('focus',()=>{ loadGoogle().catch(()=>{}); bindDiscoveryAutocomplete(true); }); els.q.addEventListener('click',()=>{ loadGoogle().catch(()=>{}); bindDiscoveryAutocomplete(true); }); els.q.addEventListener('keydown',()=>{ loadGoogle().catch(()=>{}); bindDiscoveryAutocomplete(true); }, {once:true}); } if(!isVisible) return false; if(forceRebind && discoveryAutocomplete){ try{ google.maps.event.clearInstanceListeners(discoveryAutocomplete); }catch(_){ } discoveryAutocomplete=null; delete els.q.dataset.rpAutocomplete; } if(discoveryAutocomplete && els.q.dataset.rpAutocomplete==='1') return true; discoveryAutocomplete = new google.maps.places.Autocomplete(els.q,{componentRestrictions:{country:'pt'}, fields:['formatted_address','geometry','name','place_id','address_components']}); els.q.dataset.rpAutocomplete='1'; discoveryAutocomplete.addListener('place_changed',()=>{ const p=discoveryAutocomplete.getPlace(); if(!p) return; const item={name:p.name||'',address:p.formatted_address||'',lat:p.geometry?.location?.lat?.(),lng:p.geometry?.location?.lng?.(),place_id:p.place_id||'',contact_person:p.name||'',source:'google',client_id:parseInt(els.client?.value||'0',10)||null,project_id:parseInt(els.project?.value||'0',10)||null}; renderResults([item]); renderDiscoveryMarkers([item]); setStatus('Sugestão Google carregada no contexto atual.'); }); return true; }
  function bindRouteEndpointAutocomplete(){
    if(!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete)) return false;
    const bind=(input,current,setCurrent)=>{
      if(!input || current) return current;
      const autocomplete=new google.maps.places.Autocomplete(input,{componentRestrictions:{country:'pt'},fields:['formatted_address','geometry','name','place_id']});
      autocomplete.addListener('place_changed',()=>{ const place=autocomplete.getPlace(); if(place){ input.value=place.formatted_address||place.name||input.value||''; input.dataset.endpointProvenance='front_discovery_manual'; setEndpointCoords(input,place.geometry?.location?.lat?.(),place.geometry?.location?.lng?.()); scheduleRouteRecalc(100); } });
      setCurrent(autocomplete);
      return autocomplete;
    };
    startPointAutocomplete=bind(els.startPoint,startPointAutocomplete,value=>{startPointAutocomplete=value;});
    endPointAutocomplete=bind(els.endPoint,endPointAutocomplete,value=>{endPointAutocomplete=value;});
    return !!(startPointAutocomplete||endPointAutocomplete);
  }
  function setupGoogleHelpers(){ if(!(window.google && google.maps)) return false; googleReady=true; if(!placesSvc && google.maps.places && google.maps.places.PlacesService){ placesSvc=new google.maps.places.PlacesService(document.createElement('div')); } if(!geocoder){ geocoder=new google.maps.Geocoder(); } if(!directionsService){ directionsService=new google.maps.DirectionsService(); } bindDiscoveryAutocomplete(); bindRouteEndpointAutocomplete(); return !!placesSvc; }
  async function loadGoogle(){
    const hasPlaces = !!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete && google.maps.places.PlacesService);
    if(hasPlaces){ setupGoogleHelpers(); return true; }
    if(window.FieldFlowGoogleMaps && typeof window.FieldFlowGoogleMaps.load === 'function'){
      const loaded = await window.FieldFlowGoogleMaps.load({key:gmKey,libraries:['places'],language:'pt-PT',region:'PT'}).catch(()=>false);
      if(loaded && window.google && google.maps && google.maps.places){ return setupGoogleHelpers(); }
    }
    if(window.google && google.maps && google.maps.importLibrary){
      try { await google.maps.importLibrary('maps'); await google.maps.importLibrary('places'); } catch(_) {}
      if(window.google && google.maps && google.maps.places){ return setupGoogleHelpers(); }
    }
    if(!gmKey) return false;
    if(!window.__routesProGooglePromise){
      window.__routesProGooglePromise = new Promise((res,rej)=>{
        const callbackName='__routesProGoogleReady_'+Math.random().toString(36).slice(2);
        window[callbackName]=()=>{ try{ delete window[callbackName]; }catch(_){ window[callbackName]=undefined; } res(); };
        const existing=document.querySelector('script[data-routespro-google="1"],script[src*="maps.googleapis.com/maps/api/js"]');
        if(existing){
          let tries=0;
          const wait=()=>{
            tries++;
            if(window.google && google.maps && google.maps.places){ return res(); }
            if(window.google && google.maps && google.maps.importLibrary){ google.maps.importLibrary('places').then(res).catch(rej); return; }
            if(tries>=100){ rej(new Error('Tempo esgotado ao carregar Google Places.')); return; }
            setTimeout(wait,120);
          };
          wait();
          return;
        }
        const script=document.createElement('script');
        script.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(gmKey)+'&libraries=places&loading=async&callback='+callbackName;
        script.async=true; script.defer=true; script.dataset.routesproGoogle='1';
        script.onerror=(err)=>{ try{ delete window[callbackName]; }catch(_){ window[callbackName]=undefined; } rej(err); };
        document.head.appendChild(script);
      });
    }
    try{ await window.__routesProGooglePromise; }catch(_){ return false; }
    return setupGoogleHelpers();
  }
  function fixMapLayout(){ if(!map || !(window.google && google.maps)) return; setTimeout(()=>{ try{ google.maps.event.trigger(map,'resize'); }catch(_){ } }, 80); setTimeout(()=>{ try{ google.maps.event.trigger(map,'resize'); }catch(_){ } }, 320); setTimeout(()=>{ try{ google.maps.event.trigger(map,'resize'); }catch(_){ } }, 900); }
  function initMap(){ const mapEl=root.querySelector('#rpf-map'); if(!mapEl || !(window.google && google.maps)) return; mapEl.style.height = '430px'; mapEl.style.minHeight = '430px'; const visible = mapEl.offsetParent !== null && mapEl.getBoundingClientRect().width > 120; if(!visible){ setTimeout(initMap, 260); return; } if(!map){ map=new google.maps.Map(mapEl,{center:{lat:38.7223,lng:-9.1393},zoom:7,mapTypeId:google.maps.MapTypeId.ROADMAP,gestureHandling:'greedy',streetViewControl:false,fullscreenControl:true,mapTypeControl:false}); infoWindow = new google.maps.InfoWindow(); } fixMapLayout(); }
  function clearMarkers(){ markers.forEach(m=>m.setMap(null)); markers=[]; }
  function fitMarkerBounds(points){ if(!map) return; if(!points.length){ map.setCenter({lat:38.7223,lng:-9.1393}); map.setZoom(7); return; } if(points.length===1){ map.setCenter(points[0]); map.setZoom(14); return; } const bounds=new google.maps.LatLngBounds(); points.forEach(pt=>bounds.extend(pt)); map.fitBounds(bounds,30); }
  function dedupe(items){ const seen=new Map(); (items||[]).forEach((item)=>{ const key=(item.place_id||'') || (((item.name||'').trim().toLowerCase())+'|'+((item.address||'').trim().toLowerCase())); if(!key) return; const prev=seen.get(key); const score=(x)=>['phone','email','contact_person','website','lat','lng'].reduce((n,k)=>n+(x&&x[k]?1:0),0); if(!prev || score(item)>=score(prev)){ seen.set(key,item); } }); return Array.from(seen.values()); }
  function populateDistricts(){ const list=Array.isArray(geoPT.districts)?geoPT.districts:[]; els.district.innerHTML='<option value="">Distrito</option>'+list.map(x=>`<option value="${x}">${x}</option>`).join(''); }
  function populateDependent(){ const d=els.district.value||''; const counties=(geoPT.countiesByDistrict&&geoPT.countiesByDistrict[d])||[]; const cities=(geoPT.citiesByDistrict&&geoPT.citiesByDistrict[d])||[]; els.county.innerHTML='<option value="">Concelho</option>'+counties.map(x=>`<option value="${x}">${x}</option>`).join(''); els.city.innerHTML='<option value="">Cidade</option>'+cities.map(x=>`<option value="${x}">${x}</option>`).join(''); }
  async function loadCategories(){ const data=await j(api+'categories'); categories=Array.isArray(data.items)?data.items:[]; const seen={}; const roots=categories.filter(x=>!x.parent_id).filter(x=>{ const k=(x.slug||x.name||'').toString().trim().toLowerCase(); if(seen[k]) return false; seen[k]=1; return true; }); els.category.innerHTML='<option value="">Categoria</option>'+roots.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join(''); fillSubcategories(); }
  function fillSubcategories(){ const pid=parseInt(els.category.value||'0',10); const seen={}; const subs=categories.filter(x=>parseInt(x.parent_id||0,10)===pid).filter(x=>{ const k=(x.name||'').trim().toLowerCase(); if(seen[k]) return false; seen[k]=1; return true; }); els.subcategory.innerHTML='<option value="">Subcategoria</option>'+subs.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join(''); }
  function renderResults(items){ resultItems=dedupe(items); els.results.innerHTML=(resultItems.map((item,idx)=>`<div class="rp-item"><div class="rp-chip">${item.source==='google'?'Google':'Interno'}</div><h4>${esc(item.name||'PDV')}</h4><div class="rp-meta">${esc(item.address||'')}</div><div class="rp-meta">${esc(item.phone||'')} ${item.city?(' · '+esc(item.city)):''}</div><div class="rp-actions"><button type="button" class="rp-btn ghost" data-idx="${idx}">Adicionar à rota</button></div></div>`).join('')) || '<div class="rp-item">Sem resultados.</div>';
    els.results.querySelectorAll('button[data-idx]').forEach(btn=>btn.addEventListener('click',()=>{ const item=resultItems[parseInt(btn.dataset.idx,10)]; if(item) addToQueue(item); })); renderDiscoveryMarkers(resultItems); }
  function renderDiscoveryMarkers(items){ if(!map || !(window.google && google.maps)) return; clearMarkers(); const bounds=[]; (items||[]).forEach(item=>{ const lat=parseFloat(item.lat), lng=parseFloat(item.lng); if(!Number.isFinite(lat)||!Number.isFinite(lng)) return; const isGoogle=item.source==='google'; const marker=new google.maps.Marker({map, position:{lat,lng}, title:item.name||item.address||'PDV', icon:{path:google.maps.SymbolPath.CIRCLE, scale:isGoogle?8:7, fillColor:isGoogle?'#f97316':'#2563eb', fillOpacity:.95, strokeColor:isGoogle?'#c2410c':'#1d4ed8', strokeWeight:2}}); marker.addListener('click',()=>{ infoWindow.setContent('<div style="max-width:260px"><strong>'+esc(item.name||'PDV')+'</strong><br>'+esc(item.address||'')+'</div>'); infoWindow.open({anchor:marker,map}); addToQueue(item); }); markers.push(marker); bounds.push({lat,lng}); }); fitMarkerBounds(bounds); fixMapLayout(); }
  function buildInternalParams(){ const p=new URLSearchParams(); if(els.client?.value) p.set('client_id',els.client.value); if(els.project?.value) p.set('project_id',els.project.value); const ownerId = effectiveDiscoveryOwnerId(); if(ownerId){ p.set('owner_user_id', ownerId); if(isAppContext) p.set('include_unassigned','1'); } if(els.district.value) p.set('district',els.district.value); if(els.county.value) p.set('county',els.county.value); if(els.city.value) p.set('city',els.city.value); if(els.category.value) p.set('category_id',els.category.value); if(els.subcategory.value) p.set('subcategory_id',els.subcategory.value); if(els.q.value.trim()) p.set('q',els.q.value.trim()); p.set('per_page','200'); return p; }
  async function fetchInternalPages(params){ const merged=[]; let page=1, total=0; while(true){ const req=new URLSearchParams(params.toString()); req.set('page', String(page)); const data=await j(api+'commercial-search?'+req.toString()); const items=Array.isArray(data.items)?data.items:[]; total=parseInt(data.total||items.length||0,10)||0; merged.push(...items.map(x=>Object.assign({source:'internal'},x))); if(!items.length || merged.length>=total || page>=50) break; page++; } return dedupe(merged); }
  async function searchInternal(){ return fetchInternalPages(buildInternalParams()); }
  function geocodeArea(){ return new Promise((resolve)=>{ if(!googleReady || !geocoder) return resolve(null); const pieces=[els.city.value,els.county.value,els.district.value,'Portugal'].filter(Boolean); if(!pieces.length && !els.q.value.trim()) return resolve(null); geocoder.geocode({address:(pieces.length?pieces.join(', '):els.q.value.trim())+', Portugal'}, (res,status)=>{ if(status==='OK' && res && res[0]){ const g=res[0].geometry.location; resolve({lat:g.lat(),lng:g.lng()}); } else resolve(null); }); }); }
  function textSearch(req){ return new Promise((resolve)=>{ if(!placesSvc) return resolve([]); placesSvc.textSearch(req, (results,status)=>{ if(status!==google.maps.places.PlacesServiceStatus.OK || !Array.isArray(results)) return resolve([]); resolve(results); }); }); }
  function nearbySearch(req){ return new Promise((resolve)=>{ if(!placesSvc) return resolve([]); placesSvc.nearbySearch(req, (results,status)=>{ if(status!==google.maps.places.PlacesServiceStatus.OK || !Array.isArray(results)) return resolve([]); resolve(results); }); }); }
  function placeToItem(r){ return {name:r.name||'',address:r.formatted_address||r.vicinity||'',lat:r.geometry?.location?.lat?.(),lng:r.geometry?.location?.lng?.(),place_id:r.place_id||'',contact_person:r.contact_person||r.name||'',phone:r.formatted_phone_number||r.phone||'',email:r.email||'',source:'google'}; }
  async function googleSearch(){ if(!googleReady || !placesSvc){ return []; } const center=await geocodeArea(); const terms=[els.subcategory.options[els.subcategory.selectedIndex]?.text||'', els.category.options[els.category.selectedIndex]?.text||'', els.q.value.trim()].filter(Boolean); const query=(terms.join(' ').trim() || 'estabelecimento comercial') + ', ' + [els.city.value, els.county.value, els.district.value, 'Portugal'].filter(Boolean).join(', ');
    const textResults=await textSearch({query, language:'pt-PT'});
    let nearbyResults=[];
    if(center&&(textResults||[]).length<8){ nearbyResults=await nearbySearch({location:new google.maps.LatLng(center.lat,center.lng), radius: els.city.value?12000:(els.county.value?25000:50000), keyword:terms.join(' ').trim()||undefined, language:'pt-PT'}); }
    return dedupe([...(textResults||[]), ...(nearbyResults||[])].map(placeToItem));
  }
  function queueKey(item){ return item.place_id || (((item.name||'').trim().toLowerCase())+'|'+((item.address||'').trim().toLowerCase())); }
  function scheduleRouteRecalc(delay=220){ clearTimeout(routeCalcTimer); routeCalcTimer=setTimeout(()=>localRoutePreview(),Math.max(0,delay)); }
  function addToQueue(item){ const key=queueKey(item); if(queue.some(x=>queueKey(x)===key)){ setStatus('Esse PDV já está na rota.'); return; } if(queue.length>=8){ setStatus('Cada rota pode ter no máximo 8 PDVs.'); return; } queue.push(item); renderQueue(); scheduleRouteRecalc(100); }
  function renderQueue(){
    els.total.textContent=String(queue.length);
    els.queue.innerHTML=queue.map((item,idx)=>`<div class="rp-qitem" draggable="true" data-index="${idx}" style="${item.position_locked?'border-color:#f59e0b;background:#fffbeb':''}"><div class="rp-no">${idx+1}</div><div><h4>${esc(item.name||'PDV')}</h4><div class="rp-meta">${esc(item.address||'')}</div><div class="rp-meta">${item.position_locked?'Posição fixa · ':''}Arrasta para reordenar</div></div><div class="rp-actions"><button type="button" class="rp-btn ghost" data-lock="${idx}">${item.position_locked?'Desbloquear':'Fixar'}</button><button type="button" class="rp-btn ghost" data-remove="${idx}">Remover</button></div></div>`).join('') || '<div class="rp-item">Sem PDVs adicionados.</div>';
    els.queue.querySelectorAll('button[data-remove]').forEach(btn=>btn.addEventListener('click',()=>{ queue.splice(parseInt(btn.dataset.remove,10),1); manualOrderLocked=true; renderQueue(); scheduleRouteRecalc(100); }));
    els.queue.querySelectorAll('button[data-lock]').forEach(btn=>btn.addEventListener('click',()=>{ const idx=parseInt(btn.dataset.lock,10); if(!queue[idx])return; queue[idx].position_locked=!queue[idx].position_locked; renderQueue(); scheduleRouteRecalc(100); }));
    let dragIndex=null;
    els.queue.querySelectorAll('[data-index]').forEach(row=>{ row.addEventListener('dragstart',()=>{dragIndex=parseInt(row.dataset.index,10);}); row.addEventListener('dragover',e=>e.preventDefault()); row.addEventListener('drop',e=>{e.preventDefault(); const target=parseInt(row.dataset.index,10); if(!Number.isFinite(dragIndex)||!Number.isFinite(target)||dragIndex===target)return; const moved=queue.splice(dragIndex,1)[0]; queue.splice(target,0,moved); manualOrderLocked=true; renderQueue(); scheduleRouteRecalc(100);}); });
  }
  function drawQueueLine(){ if(!map || !(window.google && google.maps)) return; if(routeRenderer){ routeRenderer.setMap(null); routeRenderer=null; } const pts=queue.map(x=>({lat:parseFloat(x.lat),lng:parseFloat(x.lng)})).filter(x=>Number.isFinite(x.lat)&&Number.isFinite(x.lng)); if(pts.length>=2){ const bounds=new google.maps.LatLngBounds(); pts.forEach(pt=>bounds.extend(pt)); map.fitBounds(bounds,30); } }
  // 9CK Planner Local Preview & Google Cost Authority (fieldflow-planner-local-preview-cost-authority-v1).
  // Adicionar, remover, arrastar, fixar ou escrever a partida/chegada já não chama o Google.
  // A ordem e os km/tempo são estimados localmente com o mesmo estimador rodoviário calibrado do
  // servidor (RoutingMatrixService). O Google só é chamado em "Calcular percurso real", com a
  // ordem atual; a otimização de waypoints Google só corre se a opção premium estiver ligada.
  const plannerCostContract='fieldflow-planner-local-preview-cost-authority-v1';
  const googleWaypointOptimizationEnabled=root.dataset.googleWaypointOptimization==='1';
  function setEndpointCoords(input,lat,lng){ if(!input) return; if(Number.isFinite(lat)&&Number.isFinite(lng)&&Math.abs(lat)<=90&&Math.abs(lng)<=180&&!(lat===0&&lng===0)){ input.dataset.endpointLat=String(lat); input.dataset.endpointLng=String(lng); } else { delete input.dataset.endpointLat; delete input.dataset.endpointLng; } }
  function endpointCoords(input){ if(!input) return null; const lat=parseFloat(input.dataset.endpointLat||''), lng=parseFloat(input.dataset.endpointLng||''); return Number.isFinite(lat)&&Number.isFinite(lng)?{lat,lng}:null; }
  function itemCoords(item){ const lat=parseFloat(item&&item.lat), lng=parseFloat(item&&item.lng); return Number.isFinite(lat)&&Number.isFinite(lng)?{lat,lng}:null; }
  function haversineKm(a,b){ const r=6371, rad=Math.PI/180, dLat=(b.lat-a.lat)*rad, dLng=(b.lng-a.lng)*rad; const h=Math.sin(dLat/2)**2+Math.cos(a.lat*rad)*Math.cos(b.lat*rad)*Math.sin(dLng/2)**2; return 2*r*Math.asin(Math.min(1,Math.sqrt(h))); }
  function internalRoadEdge(a,b){ const air=haversineKm(a,b); const factor=air<8?1.38:(air<30?1.30:(air<100?1.23:1.17)); const road=Math.max(0,air*factor); const speed=road<8?28:(road<35?38:(road<120?55:72)); let minutes=(road/Math.max(1,speed))*60; if(road>0.15) minutes+=Math.min(5,1.5+road*0.025); return {km:road,min:minutes}; }
  function localOrder(items,start,end){
    const pts=items.map(itemCoords); if(pts.some(p=>!p)||items.length<3) return items.slice();
    const cost=(a,b)=>internalRoadEdge(a,b).km;
    const remaining=items.map((_,i)=>i); const order=[]; let current=start||null;
    if(!current){ order.push(remaining.shift()); current=pts[order[0]]; }
    while(remaining.length){ let best=0,bestCost=Infinity; remaining.forEach((idx,k)=>{ const c=cost(current,pts[idx]); if(c<bestCost){bestCost=c;best=k;} }); const next=remaining.splice(best,1)[0]; order.push(next); current=pts[next]; }
    const total=seq=>{ let km=0,prev=start||null; seq.forEach(i=>{ if(prev) km+=cost(prev,pts[i]); prev=pts[i]; }); if(end&&prev) km+=cost(prev,end); return km; };
    let improved=true,guard=0;
    while(improved&&guard++<40){ improved=false; for(let i=0;i<order.length-1;i++){ for(let k=i+1;k<order.length;k++){ const candidate=order.slice(0,i).concat(order.slice(i,k+1).reverse(),order.slice(k+1)); if(total(candidate)+0.01<total(order)){ order.splice(0,order.length,...candidate); improved=true; } } } }
    return order.map(i=>items[i]);
  }
  let lastPreviewSignature='';
  function previewSignature(){ return JSON.stringify([queue.map(queueKey),!!manualOrderLocked,queue.map(item=>!!item.position_locked),String(els.startPoint?.value||''),String(els.endPoint?.value||''),String(els.startPoint?.dataset?.endpointLat||''),String(els.endPoint?.dataset?.endpointLat||'')]); }
  function localRoutePreview(){
    // Um blur/change sem alterações reais não pode cancelar um "Calcular percurso real" em curso.
    if(queue.length&&previewSignature()===lastPreviewSignature) return;
    routeCalcToken++;
    if(!queue.length){ lastPreviewSignature=''; els.km.textContent='0'; els.travel.textContent='0m'; els.totalTime.textContent='0m'; drawQueueLine(); return; }
    const start=endpointCoords(els.startPoint), end=endpointCoords(els.endPoint);
    const canReorder=!manualOrderLocked&&!queue.some(item=>item.position_locked);
    if(canReorder){ const ordered=localOrder(queue,start,end); if(ordered.length===queue.length&&ordered.some((item,i)=>item!==queue[i])){ queue=ordered; renderQueue(); } }
    let km=0,minutes=0,prev=start,missing=0;
    queue.forEach(item=>{ const p=itemCoords(item); if(!p){ missing++; return; } if(prev){ const e=internalRoadEdge(prev,p); km+=e.km; minutes+=e.min; } prev=p; });
    if(end&&prev){ const e=internalRoadEdge(prev,end); km+=e.km; minutes+=e.min; }
    els.km.textContent=km.toFixed(1); const mins=Math.round(minutes); els.travel.textContent=mins+'m'; els.totalTime.textContent=mins+'m';
    drawQueueLine();
    lastPreviewSignature=previewSignature();
    const endpointsNote=(!start||!end)?' Partida/chegada sem coordenadas não entram na estimativa.':'';
    setStatus((canReorder&&queue.length>=3?'Ordem sugerida localmente. ':'')+'Estimativa sem custo Google: '+km.toFixed(1)+' km · '+mins+' min.'+(missing?' '+missing+' PDV(s) sem coordenadas.':'')+endpointsNote+' Usa "Calcular percurso real" para confirmar com o Google.');
    try{ root.dispatchEvent(new CustomEvent('fieldflow:planner-local-preview',{bubbles:true,detail:{contract:plannerCostContract,km:Number(km.toFixed(2)),minutes:mins,stops:queue.length}})); }catch(_){}
  }
  async function recalcRoute(){
    if(queue.length&&(!googleReady||!directionsService)){ setStatus('A preparar o cálculo real do percurso...'); await loadGoogle().catch(()=>{}); if(window.google&&google.maps&&!directionsService){ try{ directionsService=new google.maps.DirectionsService(); }catch(_){ } } }
    const calcToken=++routeCalcToken;
    drawQueueLine();
    if(!queue.length || !googleReady || !directionsService){ els.km.textContent='0'; els.travel.textContent='0m'; els.totalTime.textContent='0m'; return; }
    const located=queue.map((item,index)=>({item,index,location:Number.isFinite(parseFloat(item.lat))&&Number.isFinite(parseFloat(item.lng))?{lat:parseFloat(item.lat),lng:parseFloat(item.lng)}:(item.address||item.name||'')})).filter(row=>row.location);
    if(!located.length) return;
    const startText=String(els.startPoint?.value||'').trim(), endText=String(els.endPoint?.value||'').trim();
    if(!startText||!endText){ els.km.textContent='0'; els.travel.textContent='0m'; els.totalTime.textContent='0m'; setStatus('Define partida e chegada reais ou configura a Base da rota no Centro de Atribuições.'); return; }
    const origin=startText, destination=endText, waypointRows=located;
    const waypoints=waypointRows.slice(0,23).map(row=>({location:row.location,stopover:true}));
    directionsService.route({origin,destination,waypoints,travelMode:'DRIVING',optimizeWaypoints:googleWaypointOptimizationEnabled&&!manualOrderLocked&&!queue.some(item=>item.position_locked)}, (res,status)=>{
      if(calcToken!==routeCalcToken) return;
      if(status!=='OK' || !res.routes?.[0]?.legs){ setStatus('Google não conseguiu calcular a rota completa.'); return; }
      const order=Array.isArray(res.routes[0].waypoint_order)?res.routes[0].waypoint_order:waypointRows.map((_,i)=>i);
      const orderedRows=order.map(i=>waypointRows[i]).filter(Boolean);
      const reordered=orderedRows.map(row=>row.item);
      if(!manualOrderLocked&&!queue.some(item=>item.position_locked)&&reordered.length===queue.length) queue=reordered;
      renderQueue();
      if(routeRenderer){ routeRenderer.setMap(null); }
      routeRenderer=new google.maps.DirectionsRenderer({map,suppressMarkers:false,preserveViewport:false});
      routeRenderer.setDirections(res);
      let meters=0, secs=0; res.routes[0].legs.forEach(leg=>{ meters += leg.distance?.value||0; secs += leg.duration?.value||0; });
      els.km.textContent=(meters/1000).toFixed(1); const mins=Math.round(secs/60); els.travel.textContent=mins+'m'; els.totalTime.textContent=mins+'m';
      setStatus(googleWaypointOptimizationEnabled&&!manualOrderLocked?'Ordem otimizada pelo Google por distância e tempo.':'Percurso real calculado pelo Google com a ordem atual.');
    });
  }
  function openMaps(){ if(!queue.length){ setStatus('Adiciona pelo menos um PDV.'); return; } const start=String(els.startPoint?.value||'').trim(), end=String(els.endPoint?.value||'').trim(); if(!start||!end){ setStatus('Indica partida e chegada reais antes de abrir a navegação.'); return; } const toPoint=s=> Number.isFinite(parseFloat(s.lat))&&Number.isFinite(parseFloat(s.lng)) ? `${s.lat},${s.lng}` : (s.address||s.name||''); const points=[start,...queue.map(toPoint),end].filter(Boolean).filter((v,i,a)=>i===0||v.toLowerCase()!==a[i-1].toLowerCase()); const origin=points.shift(), destination=points.pop(), mids=points.slice(0,9); let url='https://www.google.com/maps/dir/?api=1&travelmode=driving&dir_action=navigate&origin='+encodeURIComponent(origin)+'&destination='+encodeURIComponent(destination); if(mids.length) url+='&waypoints='+encodeURIComponent(mids.join('|')); if(window.FieldFlowNavigation&&typeof window.FieldFlowNavigation.open==='function'){window.FieldFlowNavigation.open(url,{source:'front_routes'});}else{window.location.assign(url);} }
  async function saveRoute(forcedStatus){
    if(!queue.length){ return setStatus('Adiciona pelo menos um PDV.'); }
    if(!els.client.value){ return setStatus('Seleciona um cliente.'); }
    if(!els.project.value){ return setStatus('Seleciona uma campanha.'); }
    const startAddress=String(els.startPoint?.value||'').trim(), endAddress=String(els.endPoint?.value||'').trim();
    if(!startAddress || !endAddress){ return setStatus('Indica o ponto de partida e o ponto de chegada.'); }
    const selectedStatus=forcedStatus||((els.routeStatus&&els.routeStatus.value)||'planned'); if(els.routeStatus) els.routeStatus.value=selectedStatus;
    setStatus('A guardar e otimizar a rota...');
    const route = await j(api+'routes',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({client_id:parseInt(els.client.value,10),project_id:parseInt((els.project?.value)||'0',10)||null,date:els.date.value,status:selectedStatus,meta:{start_point:{address:startAddress,provenance:String(els.startPoint?.dataset?.endpointProvenance||'front_discovery_manual')},end_point:{address:endAddress,provenance:String(els.endPoint?.dataset?.endpointProvenance||'front_discovery_manual')},return_to_start:startAddress.toLowerCase()===endAddress.toLowerCase()?1:0,manual_order_locked:manualOrderLocked?1:0,position_locked_location_ids:[],route_workspace_version:'fieldflow_route_workspace_v1',planning_source:'front_discovery'}})});
    const routeId=parseInt(route.id||route.route_id||0,10); if(!routeId) return setStatus('Rota criada sem ID.');
    let seq=0;
    for(const item of queue){
      let locationId=parseInt(item.id||0,10)||0;
      if(!locationId){
        const loc=await j(api+'locations',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({name:item.name,address:item.address,phone:item.phone||'',email:item.email||'',contact_person:item.contact_person||'',place_id:item.place_id||'',lat:item.lat,lng:item.lng,source:item.source||'manual',client_id:parseInt(els.client.value,10),project_id:parseInt((els.project?.value)||'0',10)||null,location_type:'pdv',replace_existing:1})});
        locationId=parseInt(loc.id,10);
      }
      if(locationId){ item.id=locationId; item.location_id=locationId; await j(api+'stops',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({route_id:routeId,location_id:locationId,seq:seq++})}); }
    }
    const lockedLocationIds=queue.filter(item=>item.position_locked).map(item=>parseInt(item.location_id||item.id||0,10)).filter(Boolean);
    try{ await j(api+'routes/'+routeId,{method:'PATCH',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({meta:{start_point:{address:startAddress,provenance:String(els.startPoint?.dataset?.endpointProvenance||'front_discovery_manual')},end_point:{address:endAddress,provenance:String(els.endPoint?.dataset?.endpointProvenance||'front_discovery_manual')},return_to_start:startAddress.toLowerCase()===endAddress.toLowerCase()?1:0,manual_order_locked:manualOrderLocked?1:0,position_locked_location_ids:lockedLocationIds,route_workspace_version:'fieldflow_route_workspace_v1',route_workspace_last_saved_at:new Date().toISOString()}})}); }catch(_){ }
    if(['draft','planned'].includes(String(selectedStatus))){
      if(manualOrderLocked){
        setStatus(selectedStatus==='draft'?'Rascunho guardado com a ordem manual protegida.':'Rota publicada com a ordem manual protegida.');
        return;
      }
      try{
        const optimized=await j(api+'routes/'+routeId+'/optimize',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({mode:'hybrid',manual_order_locked:manualOrderLocked?1:0,locked_location_ids:lockedLocationIds})});
        const lookup=new Map(queue.map(item=>[parseInt(item.location_id||item.id||0,10),item]));
        const ordered=(optimized.ordered_location_ids||[]).map(id=>lookup.get(parseInt(id,10))).filter(Boolean);
        if(ordered.length===queue.length) queue=ordered;
        renderQueue();
        const km=Number(optimized.optimized&&optimized.optimized.distance_km||0), saved=Number(optimized.saving_km||0);
        setStatus('Rota gravada e otimizada'+(km?' · '+km.toFixed(1)+' km':'')+(saved>0.05?' · poupança '+saved.toFixed(1)+' km':'.'));
        return;
      }catch(error){ setStatus('Rota gravada, mas a otimização automática ficou pendente: '+(error.message||'erro desconhecido')+'.'); return; }
    }
    setStatus('Rota gravada. O estado selecionado não permite reordenação automática.');
  }
  async function fitToZone(){ const center = await geocodeArea(); if(center && map){ map.setCenter(center); map.setZoom(els.city.value?12:(els.county.value?10:8)); fixMapLayout(); } }
  async function runCombined(withGoogle){ try{ if(!discoveryScopeReady()){ setStatus('Seleciona cliente e campanha válidos para descobrir PDVs.'); renderResults([]); return; } await loadGoogle().catch(()=>{}); initMap(); bindDiscoveryAutocomplete(); setStatus(withGoogle?'A combinar internos e Google...':'A pesquisar PDVs existentes...'); const internal=await searchInternal(); let items=internal; if(withGoogle){ const googleItems=await googleSearch(); items=dedupe([...internal, ...googleItems]); } renderResults(items); if(!items.length){ await fitToZone(); } setStatus('Pesquisa concluída.'); }catch(err){ setStatus(err.message||'Falha na pesquisa.'); } }
  root.querySelector('#rpf-search').addEventListener('click', ()=>runCombined(false));
  root.querySelector('#rpf-google').addEventListener('click', ()=>runCombined(true));
  root.querySelector('#rpf-openmaps').addEventListener('click', openMaps); root.querySelector('#rpf-recalc').addEventListener('click', ()=>recalcRoute()); root.querySelector('#rpf-clear').addEventListener('click', ()=>{ routeCalcToken++; clearTimeout(routeCalcTimer); queue=[]; manualOrderLocked=false; renderQueue(); clearMarkers(); if(routeRenderer){ routeRenderer.setMap(null); routeRenderer=null; } if(map){ map.setCenter({lat:38.7223,lng:-9.1393}); map.setZoom(7); } els.km.textContent='0'; els.travel.textContent='0m'; els.totalTime.textContent='0m'; setStatus('Fila limpa.'); });
  [els.startPoint,els.endPoint].filter(Boolean).forEach(input=>{ input.addEventListener('input',()=>{input.dataset.endpointProvenance='front_discovery_manual';setEndpointCoords(input,NaN,NaN);scheduleRouteRecalc(650);}); input.addEventListener('change',()=>{input.dataset.endpointProvenance='front_discovery_manual';scheduleRouteRecalc(120);}); input.addEventListener('focus',()=>{ loadGoogle().then(()=>bindRouteEndpointAutocomplete()).catch(()=>{}); }); });
  els.district.addEventListener('change', populateDependent); els.category.addEventListener('change', fillSubcategories); els.q.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); runCombined(true); } });
  root.querySelector('#rpf-save-route').addEventListener('click', ()=>saveRoute('planned')); const saveDraftBtn=root.querySelector('#rpf-save-draft'); if(saveDraftBtn) saveDraftBtn.addEventListener('click',()=>saveRoute('draft'));
  root.querySelector('#rpf-client').addEventListener('change', ()=>{ syncProjects(''); lockRestrictedScope(); applyEndpointProfile(true); scheduleRouteRecalc(100); updateDiscoverySearchPlaceholder(); if(discoveryScopeReady()){ runCombined(false); } });
  root.querySelector('#rpf-project').addEventListener('change', ()=>{ applyEndpointProfile(true); scheduleRouteRecalc(100); updateDiscoverySearchPlaceholder(); if(discoveryScopeReady()){ runCombined(false); } else { setStatus('Seleciona campanha válida para descobrir PDVs.'); } });
  root.addEventListener('routespro:panel-open', ()=>{ lockRestrictedScope(); loadGoogle().then(()=>{ bindDiscoveryAutocomplete(true); if(els.q){ try{ els.q.dispatchEvent(new Event('focus')); }catch(_){ } } }).catch(()=>{}); initMap(); bindDiscoveryAutocomplete(true); fixMapLayout(); setTimeout(()=>{ loadGoogle().then(()=>{ bindDiscoveryAutocomplete(true); if(els.q){ try{ els.q.dispatchEvent(new Event('focus')); }catch(_){ } } }).catch(()=>{}); },260); setTimeout(()=>{ loadGoogle().then(()=>{ bindDiscoveryAutocomplete(true); if(els.q){ try{ els.q.dispatchEvent(new Event('focus')); }catch(_){ } } }).catch(()=>{}); if(discoveryScopeReady()){ runCombined(false); } },900); });
  window.addEventListener('resize', ()=>{ initMap(); fixMapLayout(); });
  if('IntersectionObserver' in window){ mapObserver = new IntersectionObserver((entries)=>{ entries.forEach(entry=>{ if(entry.isIntersecting){ loadGoogle().catch(()=>{}); initMap(); bindDiscoveryAutocomplete(true); fixMapLayout(); } }); }, {threshold:0.2}); const mapEl=root.querySelector('#rpf-map'); if(mapEl) mapObserver.observe(mapEl); }
  lockRestrictedScope();
  applyEndpointProfile(false);
  updateDiscoverySearchPlaceholder();
  await loadGoogle().catch(()=>{ setStatus('Google Maps indisponível. A pesquisa interna continua ativa.'); }); initMap(); bindDiscoveryAutocomplete(true); if(els.q){ try{ els.q.dispatchEvent(new Event('focus')); }catch(_){ } } setTimeout(()=>{ initMap(); bindDiscoveryAutocomplete(true); loadGoogle().then(()=>{ bindDiscoveryAutocomplete(true); if(els.q){ try{ els.q.dispatchEvent(new Event('focus')); }catch(_){ } } }).catch(()=>{}); },300); setTimeout(()=>{ initMap(); bindDiscoveryAutocomplete(true); loadGoogle().then(()=>{ bindDiscoveryAutocomplete(true); if(els.q){ try{ els.q.dispatchEvent(new Event('focus')); }catch(_){ } } }).catch(()=>{}); },900); populateDistricts(); await loadCategories().catch(()=>{}); renderQueue(); if(discoveryScopeReady()){ setTimeout(()=>{ runCombined(false); },120); }
})();
</script>
<?php
        return ob_get_clean();
    }


    public static function dashboard($atts = []) {
        if (!current_user_can('routespro_manage') && !\RoutesPro\Support\Permissions::can_access_front()) return '<p>Sem permissões.</p>';
        $atts = shortcode_atts([
            'client_id' => 0,
            'project_id' => 0,
            'mode' => 'team',
            'user_id' => 0,
            'title' => '',
        ], $atts, 'fieldflow_dashboard');
        $preset_client_id = absint($atts['client_id'] ?? 0);
        $preset_project_id = absint($atts['project_id'] ?? 0);
        $self_mode = sanitize_key((string)($atts['mode'] ?? 'team')) === 'self' || in_array((string)($atts['user_id'] ?? ''), ['me','current'], true);
        $forced_user_id = $self_mode ? get_current_user_id() : absint($atts['user_id'] ?? 0);
        $current_user = wp_get_current_user();
        $current_user_label = $current_user->display_name ?: $current_user->user_login ?: ('Utilizador #' . get_current_user_id());
        $scope = \RoutesPro\Support\Permissions::get_scope();
        if (!$preset_client_id && empty($scope['is_manager']) && count((array)($scope['client_ids'] ?? [])) === 1) $preset_client_id = (int)$scope['client_ids'][0];
        if (!$preset_project_id && empty($scope['is_manager']) && count((array)($scope['project_ids'] ?? [])) === 1) $preset_project_id = (int)$scope['project_ids'][0];

        $nonce = wp_create_nonce('wp_rest');
        $export_nonce = wp_create_nonce('routespro_export_dashboard');
        $ap = \RoutesPro\Support\Theme::resolve($preset_client_id, $preset_project_id);
        $primary = $ap['primary_solid'] ?? '#2563eb';
        $accent = $ap['accent_solid'] ?? '#06b6d4';
        $ff = $ap['font_family'] ?? 'inherit';
        $fz = intval($ap['font_size_px'] ?? 15);
        $dashboard_classes = \RoutesPro\Support\Theme::root_classes($ap);
        $dashboard_style = \RoutesPro\Support\Theme::inline_style($ap);
        $title = trim((string)($atts['title'] ?? '')) ?: ($self_mode ? 'O meu desempenho' : 'Analytics operacional');
        $dashboard_audience = $self_mode ? 'merchandiser' : (current_user_can('routespro_manage') ? 'manager' : 'client');

        ob_start(); ?>
<style>
.routespro-dashboard{--ffad-primary:<?php echo esc_attr($primary); ?>;--ffad-accent:<?php echo esc_attr($accent); ?>;font-family:<?php echo esc_attr($ff); ?>;font-size:<?php echo esc_attr($fz); ?>px;color:#0f172a}
.routespro-dashboard *{box-sizing:border-box}.routespro-dashboard .ffad-shell{display:flex;flex-direction:column;gap:16px}
.routespro-dashboard .ffad-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;padding:20px;border-radius:22px;background:linear-gradient(135deg,var(--ff-header-start,#0f172a),var(--ff-header-end,var(--ffad-primary)));color:var(--ff-header-title-text,var(--ff-hero-text,#fff));box-shadow:0 18px 42px rgba(15,23,42,.16)}
.routespro-dashboard .ffad-head h4{margin:0 0 6px;color:var(--ff-header-title-text,var(--ff-hero-text,#fff))!important;font-size:24px;letter-spacing:-.02em}.routespro-dashboard .ffad-head p{margin:0;color:var(--ff-header-body-text,var(--ff-hero-muted,rgba(255,255,255,.78)))!important;max-width:720px;line-height:1.5}
.routespro-dashboard .ffad-scope{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.10);border-radius:999px;color:#fff;font-size:12px;font-weight:800}
.routespro-dashboard .ffad-filters{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:10px;padding:14px;border:1px solid #e2e8f0;border-radius:20px;background:#f8fafc}
.routespro-dashboard .ffad-filter{display:flex;flex-direction:column;gap:6px;min-width:0}.routespro-dashboard .ffad-filter span{font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:800;color:#64748b}
.routespro-dashboard select,.routespro-dashboard input{width:100%;min-height:44px;padding:10px 12px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;color:#0f172a}.routespro-dashboard .ffad-filter.is-hidden{display:none}
.routespro-dashboard .ffad-reportbar{grid-column:1/-1;display:flex;justify-content:space-between;gap:14px;align-items:center;padding:14px 15px;border:1px solid #dbeafe;border-radius:17px;background:linear-gradient(135deg,#eff6ff,#fff 62%,#ecfeff)}.routespro-dashboard .ffad-export-copy span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.08em;font-weight:900;color:#1d4ed8}.routespro-dashboard .ffad-export-copy strong{display:block;margin-top:4px;font-size:15px}.routespro-dashboard .ffad-export-copy small{display:block;margin-top:3px;color:#64748b}.routespro-dashboard .ffad-report-actions{display:flex;gap:10px;align-items:center;justify-content:flex-end;flex-wrap:wrap}.routespro-dashboard .ffad-presets,.routespro-dashboard .ffad-actions{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}.routespro-dashboard .ffad-preset{border:1px solid #bfdbfe;background:#fff;color:#1e3a8a;border-radius:999px;padding:8px 10px;font-size:11px;font-weight:800;cursor:pointer}.routespro-dashboard .ffad-preset:hover{background:#dbeafe}.routespro-dashboard .ffad-btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:9px 13px;border-radius:11px;border:1px solid #cbd5e1;background:#fff;color:#0f172a!important;text-decoration:none!important;font-weight:800;white-space:nowrap}.routespro-dashboard .ffad-btn.primary{background:var(--ffad-primary);border-color:var(--ffad-primary);color:#fff!important}.routespro-dashboard .ffad-btn.alt{background:var(--ffad-accent);border-color:var(--ffad-accent);color:#fff!important}
.routespro-dashboard .ffad-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.routespro-dashboard .ffad-kpi{position:relative;overflow:hidden;min-height:132px;padding:16px;border:1px solid #e2e8f0;border-radius:18px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.05)}
.routespro-dashboard .ffad-kpi:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--ffad-primary)}.routespro-dashboard .ffad-kpi span{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:800}.routespro-dashboard .ffad-kpi strong{display:block;margin:8px 0 6px;font-size:28px;line-height:1;color:#0f172a}.routespro-dashboard .ffad-kpi small{display:block;color:#64748b;line-height:1.35}.routespro-dashboard .ffad-progress{height:6px;margin-top:12px;border-radius:999px;background:#e2e8f0;overflow:hidden}.routespro-dashboard .ffad-progress i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--ffad-primary),var(--ffad-accent))}
.routespro-dashboard .ffad-main{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(260px,.5fr);gap:14px}.routespro-dashboard .ffad-card{border:1px solid #e2e8f0;border-radius:20px;background:#fff;padding:16px;box-shadow:0 10px 28px rgba(15,23,42,.05);min-width:0}.routespro-dashboard .ffad-card h5{margin:0 0 4px;font-size:16px}.routespro-dashboard .ffad-card p{margin:0 0 12px;color:#64748b}
.routespro-dashboard .ffad-chart{overflow:auto;min-height:280px;padding:8px;border-radius:16px;background:linear-gradient(180deg,#fbfdff,#fff)}.routespro-dashboard .ffad-chart svg{display:block;min-width:620px;width:100%;height:240px}.routespro-dashboard .ffad-insights{display:flex;flex-direction:column;gap:10px}.routespro-dashboard .ffad-insight{padding:12px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.routespro-dashboard .ffad-insight span{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}.routespro-dashboard .ffad-insight strong{display:block;margin-top:5px;font-size:17px}.routespro-dashboard .ffad-insight small{display:block;margin-top:3px;color:#64748b}
.routespro-dashboard .ffad-ai{position:relative;overflow:hidden;border:1px solid #c7d2fe;border-radius:22px;background:linear-gradient(145deg,#eef2ff,#fff 54%,#ecfeff);padding:18px;box-shadow:0 12px 32px rgba(37,99,235,.07)}.routespro-dashboard .ffad-ai:after{content:"AI";position:absolute;right:14px;top:-7px;color:rgba(37,99,235,.06);font-size:84px;font-weight:900;pointer-events:none}.routespro-dashboard .ffad-ai-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;position:relative;z-index:1}.routespro-dashboard .ffad-ai-head h5{margin:0 0 5px;font-size:18px}.routespro-dashboard .ffad-ai-head p{margin:0;color:#64748b}.routespro-dashboard .ffad-ai-body{position:relative;z-index:1;margin-top:14px}.routespro-dashboard .ffad-ai-summary{padding:14px;border:1px solid #dbeafe;border-radius:15px;background:rgba(255,255,255,.9)}.routespro-dashboard .ffad-ai-summary strong{display:block;font-size:18px;margin-bottom:5px}.routespro-dashboard .ffad-ai-summary p{margin:0;color:#475569;line-height:1.5}.routespro-dashboard .ffad-ai-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px}.routespro-dashboard .ffad-ai-group{padding:12px;border:1px solid #e2e8f0;border-radius:15px;background:#fff;min-width:0}.routespro-dashboard .ffad-ai-group h6{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#475569}.routespro-dashboard .ffad-ai-item{padding:9px 0;border-top:1px solid #eef2f7}.routespro-dashboard .ffad-ai-item:first-of-type{border-top:0;padding-top:0}.routespro-dashboard .ffad-ai-item strong{display:block;font-size:13px}.routespro-dashboard .ffad-ai-item small{display:block;margin-top:3px;color:#64748b;line-height:1.4}.routespro-dashboard .ffad-ai-dot{display:inline-block;width:8px;height:8px;border-radius:999px;margin-right:6px;background:#3b82f6}.routespro-dashboard .ffad-ai-group.risks .ffad-ai-dot{background:#f59e0b}.routespro-dashboard .ffad-ai-group.actions .ffad-ai-dot{background:#10b981}.routespro-dashboard .ffad-ai-explain{margin-top:10px;border:1px solid #dbeafe;border-radius:15px;background:#fff;overflow:hidden}.routespro-dashboard .ffad-ai-explain summary{cursor:pointer;list-style:none;padding:12px 14px;font-weight:800;color:#1e3a8a}.routespro-dashboard .ffad-ai-explain summary::-webkit-details-marker{display:none}.routespro-dashboard .ffad-ai-explain-body{padding:0 14px 14px;color:#475569}.routespro-dashboard .ffad-ai-explain-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.routespro-dashboard .ffad-ai-explain-grid>div{padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.routespro-dashboard .ffad-ai-explain-grid h6{margin:0 0 5px;font-size:11px;text-transform:uppercase;color:#475569}.routespro-dashboard .ffad-ai-explain-grid ul{margin:0;padding-left:17px}.routespro-dashboard .ffad-ai-explain-grid li{margin:3px 0}.routespro-dashboard .ffad-ai-meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;color:#64748b;font-size:11px}.routespro-dashboard .ffad-ai-meta span{padding:5px 8px;border-radius:999px;background:#f8fafc;border:1px solid #e2e8f0}.routespro-dashboard .ffad-ai-refresh{position:relative;z-index:2;white-space:nowrap}.routespro-dashboard .ffad-ai-refresh[disabled]{opacity:.55;cursor:wait}
.routespro-dashboard .ffad-custom-dashboards{display:flex;flex-direction:column;gap:14px}.routespro-dashboard .ffad-custom-dashboard{padding:16px;border:1px solid #e2e8f0;border-radius:20px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.05)}.routespro-dashboard .ffad-custom-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px}.routespro-dashboard .ffad-custom-head h5{margin:0;font-size:17px}.routespro-dashboard .ffad-custom-head p{margin:4px 0 0;color:#64748b}.routespro-dashboard .ffad-custom-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}.routespro-dashboard .ffad-metric{padding:16px;border:1px solid #e2e8f0;border-radius:18px;background:#fbfdff;min-width:0}.routespro-dashboard .ffad-metric.kpi{min-height:126px}.routespro-dashboard .ffad-metric-label{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}.routespro-dashboard .ffad-metric-label strong{font-size:15px}.routespro-dashboard .ffad-metric-label span,.routespro-dashboard .ffad-ai-badge{display:inline-flex;padding:4px 7px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:800;white-space:nowrap}.routespro-dashboard .ffad-question{margin-top:5px;color:#1d4ed8;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.routespro-dashboard .ffad-metric-value{font-size:28px;font-weight:900;margin:10px 0 4px}.routespro-dashboard .ffad-metric-note{font-size:12px;color:#64748b}.routespro-dashboard .ffad-series{display:grid;gap:7px;margin-top:11px}.routespro-dashboard .ffad-series-row{display:grid;grid-template-columns:minmax(90px,1fr) minmax(80px,1.5fr) auto;gap:8px;align-items:center;font-size:12px}.routespro-dashboard .ffad-series-row>span:first-child{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.routespro-dashboard .ffad-series-track{height:7px;border-radius:999px;background:#e2e8f0;overflow:hidden}.routespro-dashboard .ffad-series-track i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--ffad-primary),var(--ffad-accent))}.routespro-dashboard .ffad-series-row b{font-size:11px}.routespro-dashboard .ffad-share-space{display:grid;gap:12px}.routespro-dashboard .ffad-share-summary{display:grid;grid-template-columns:repeat(6,minmax(110px,1fr));gap:8px}.routespro-dashboard .ffad-share-summary div{padding:11px;border:1px solid #dbeafe;border-radius:13px;background:#f8fbff}.routespro-dashboard .ffad-share-summary span{display:block;color:#64748b;font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.05em}.routespro-dashboard .ffad-share-summary strong{display:block;margin-top:4px;font-size:18px;color:#0f172a}.routespro-dashboard .ffad-share-bars{display:grid;gap:8px}.routespro-dashboard .ffad-share-row{display:grid;grid-template-columns:minmax(150px,1.4fr) minmax(170px,2fr) auto;gap:10px;align-items:center;padding:10px 11px;border:1px solid #e2e8f0;border-radius:13px;background:#fff}.routespro-dashboard .ffad-share-row>strong{font-size:12px;overflow-wrap:anywhere}.routespro-dashboard .ffad-share-track{position:relative;height:13px;border-radius:999px;background:#fee2e2;overflow:hidden}.routespro-dashboard .ffad-share-track i{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,var(--ffad-primary),var(--ffad-accent))}.routespro-dashboard .ffad-share-track b{position:absolute;top:-3px;bottom:-3px;width:2px;background:#0f172a;opacity:.55}.routespro-dashboard .ffad-share-row em{font-style:normal;font-size:11px;font-weight:850;white-space:nowrap}.routespro-dashboard .ffad-share-row em.is-below{color:#b91c1c}.routespro-dashboard .ffad-share-row em.is-ok{color:#166534}.routespro-dashboard .ffad-metric.has-share{grid-column:1/-1}.routespro-dashboard .ffad-metric.has-share .ffad-metric-label>span{background:#ede9fe;color:#5b21b6}@media(max-width:980px){.routespro-dashboard .ffad-share-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.routespro-dashboard .ffad-share-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.routespro-dashboard .ffad-share-row{grid-template-columns:1fr}.routespro-dashboard .ffad-share-row em{justify-self:start}}
.routespro-dashboard .ffad-fronts{display:grid;grid-template-columns:repeat(5,minmax(110px,1fr));gap:8px;margin-top:13px}.routespro-dashboard .ffad-fronts div{padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.routespro-dashboard .ffad-fronts span{display:block;color:#64748b;font-size:10px;text-transform:uppercase;font-weight:800}.routespro-dashboard .ffad-fronts strong{display:block;margin-top:4px;font-size:18px}.routespro-dashboard .ffad-analytics-table{margin-top:14px;max-height:480px;overflow:auto;scrollbar-gutter:stable both-edges}.routespro-dashboard .ffad-analytics-table table{min-width:1040px}.routespro-dashboard .ffad-analytics-hint{margin:7px 2px 0;color:#64748b;font-size:11px}.routespro-dashboard .ffad-evidence{margin-top:14px;border:1px solid #dbeafe;border-radius:15px;background:#f8fbff;overflow:hidden}.routespro-dashboard .ffad-evidence summary{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:11px 12px;cursor:pointer;font-weight:800}.routespro-dashboard .ffad-evidence-list{display:grid;gap:8px;padding:0 12px 12px}.routespro-dashboard .ffad-evidence-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.routespro-dashboard .ffad-evidence-row strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.routespro-dashboard .ffad-evidence-row small{display:block;margin-top:3px;color:#64748b}.routespro-dashboard .ffad-evidence-row a{display:inline-flex;padding:8px 11px;border-radius:10px;background:#0f172a;color:#fff!important;text-decoration:none!important;font-size:11px;font-weight:800}.routespro-dashboard .ffad-custom-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:16px;color:#64748b;background:#f8fafc;text-align:center}
.routespro-dashboard .ffad-table-wrap{width:100%;overflow:auto;border:1px solid #e2e8f0;border-radius:18px;background:#fff}.routespro-dashboard table{width:100%;min-width:860px;border-collapse:separate;border-spacing:0}.routespro-dashboard th,.routespro-dashboard td{padding:12px 13px;border-bottom:1px solid #eef2f7;text-align:left;vertical-align:middle}.routespro-dashboard th{position:sticky;top:0;background:#f8fafc;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.05em;z-index:1}.routespro-dashboard tbody tr{transition:background .15s ease}.routespro-dashboard tbody tr:hover{background:#f8fafc}.routespro-dashboard tbody tr:last-child td{border-bottom:0}.routespro-dashboard .ffad-status{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:11px;font-weight:800}.routespro-dashboard .ffad-status.completed,.routespro-dashboard .ffad-status.done{background:#dcfce7;color:#166534}.routespro-dashboard .ffad-status.in_progress{background:#dbeafe;color:#1d4ed8}
.routespro-dashboard .ffad-mobile-list{display:none;flex-direction:column;gap:10px}.routespro-dashboard .ffad-route-card{padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#fff}.routespro-dashboard .ffad-route-card-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.routespro-dashboard .ffad-route-card h5{margin:0;font-size:15px}.routespro-dashboard .ffad-route-card p{margin:5px 0;color:#64748b}.routespro-dashboard .ffad-route-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:10px}.routespro-dashboard .ffad-route-meta div{padding:8px;background:#f8fafc;border-radius:10px;text-align:center}.routespro-dashboard .ffad-route-meta strong{display:block}.routespro-dashboard .ffad-empty{padding:24px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#64748b;text-align:center}
@media(max-width:1180px){.routespro-dashboard .ffad-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.routespro-dashboard .ffad-filters{grid-template-columns:repeat(3,minmax(150px,1fr))}}
@media(max-width:760px){.routespro-dashboard .ffad-ai-explain-grid{grid-template-columns:1fr}.routespro-dashboard .ffad-reportbar{align-items:flex-start;flex-direction:column}.routespro-dashboard .ffad-report-actions{width:100%;justify-content:flex-start}.routespro-dashboard .ffad-presets,.routespro-dashboard .ffad-actions{justify-content:flex-start}.routespro-dashboard .ffad-custom-grid,.routespro-dashboard .ffad-ai-grid{grid-template-columns:1fr}.routespro-dashboard .ffad-head{padding:18px}.routespro-dashboard .ffad-filters{grid-template-columns:1fr 1fr}.routespro-dashboard .ffad-actions{justify-content:stretch}.routespro-dashboard .ffad-btn{flex:1 1 132px;white-space:nowrap}.routespro-dashboard .ffad-kpis{grid-template-columns:1fr 1fr}.routespro-dashboard .ffad-main{grid-template-columns:1fr}.routespro-dashboard .ffad-route-table-wrap{display:none}.routespro-dashboard .ffad-mobile-list{display:flex}}
@media(max-width:900px){.routespro-dashboard .ffad-fronts{grid-template-columns:repeat(3,minmax(100px,1fr))}}
@media(max-width:480px){.routespro-dashboard .ffad-fronts{grid-template-columns:repeat(2,minmax(90px,1fr))}.routespro-dashboard .ffad-evidence-row{grid-template-columns:1fr}.routespro-dashboard .ffad-evidence-row a{width:100%;justify-content:center}.routespro-dashboard .ffad-series-row{grid-template-columns:minmax(78px,1fr) minmax(60px,1fr) auto}.routespro-dashboard .ffad-ai-head{flex-direction:column}.routespro-dashboard .ffad-ai-refresh{width:100%}.routespro-dashboard .ffad-filters,.routespro-dashboard .ffad-kpis{grid-template-columns:1fr}.routespro-dashboard .ffad-route-meta{grid-template-columns:1fr 1fr}}

/* 2.2.168, Analytics responsive reading for merchandisers */
.routespro-dashboard .ffad-custom-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start}
.routespro-dashboard .ffad-metric{position:relative;overflow:hidden;border-color:#dbeafe;background:linear-gradient(180deg,#fff,#fbfdff);box-shadow:0 12px 30px rgba(15,23,42,.05)}
.routespro-dashboard .ffad-metric:before{content:"";position:absolute;left:0;right:0;top:0;height:4px;background:linear-gradient(90deg,var(--ffad-primary),var(--ffad-accent))}
.routespro-dashboard .ffad-metric.is-wide{grid-column:1/-1}
.routespro-dashboard .ffad-metric-label{padding-top:4px}
.routespro-dashboard .ffad-metric-label strong{font-size:17px;line-height:1.3;overflow-wrap:anywhere}
.routespro-dashboard .ffad-question{line-height:1.45;overflow-wrap:anywhere}
.routespro-dashboard .ffad-series-row>span:first-child{white-space:normal;overflow:visible;text-overflow:clip;overflow-wrap:anywhere}
.routespro-dashboard .ffad-analytics-table{max-height:clamp(360px,55vh,580px);overflow:auto;scrollbar-width:thin;scrollbar-gutter:stable both-edges;border-radius:16px}
.routespro-dashboard .ffad-analytics-table table{width:100%!important;min-width:940px!important}
.routespro-dashboard .ffad-analytics-table.is-compact table{min-width:100%!important;table-layout:fixed}
.routespro-dashboard .ffad-analytics-table.is-standard table{min-width:720px!important}
.routespro-dashboard .ffad-analytics-table th,.routespro-dashboard .ffad-analytics-table td{padding:12px 14px;line-height:1.42;white-space:normal;overflow-wrap:anywhere}
.routespro-dashboard .ffad-analytics-table .col-location{min-width:210px;max-width:330px}
.routespro-dashboard .ffad-analytics-table .col-category{min-width:170px;max-width:250px}
.routespro-dashboard .ffad-analytics-table .col-product{min-width:230px;max-width:360px}
.routespro-dashboard .ffad-analytics-table .col-date{min-width:118px;white-space:nowrap}
.routespro-dashboard .ffad-analytics-table .col-route{min-width:82px;white-space:nowrap}
.routespro-dashboard .ffad-analytics-table .col-number{min-width:92px;white-space:nowrap}
.routespro-dashboard .ffad-analytics-table .col-status{min-width:125px}
.routespro-dashboard .ffad-analytics-table.is-compact .col-location{max-width:none;width:100%}
.routespro-dashboard .ffad-fronts{gap:10px}
.routespro-dashboard .ffad-fronts div{background:linear-gradient(180deg,#fff,#f8fafc)}
.routespro-dashboard .ffad-evidence-row strong{white-space:normal;overflow:visible;text-overflow:clip;overflow-wrap:anywhere}
.routespro-dashboard .ffad-analytics-hint{line-height:1.4}
@media(max-width:1120px){
  .routespro-dashboard .ffad-custom-grid{grid-template-columns:1fr}
  .routespro-dashboard .ffad-metric.is-wide{grid-column:auto}
}
@media(max-width:700px){
  .routespro-dashboard .ffad-custom-dashboard{padding:12px;border-radius:18px}
  .routespro-dashboard .ffad-custom-head{padding:2px}
  .routespro-dashboard .ffad-metric{padding:14px;border-radius:16px}
  .routespro-dashboard .ffad-metric-label{flex-direction:column}
  .routespro-dashboard .ffad-metric-label>span{align-self:flex-start}
  .routespro-dashboard .ffad-fronts{grid-template-columns:repeat(2,minmax(0,1fr))}
  .routespro-dashboard .ffad-analytics-table{max-height:620px;overflow:auto;border:0;background:transparent;padding:0}
  .routespro-dashboard .ffad-analytics-table table,.routespro-dashboard .ffad-analytics-table.is-compact table,.routespro-dashboard .ffad-analytics-table.is-standard table,.routespro-dashboard .ffad-analytics-table.is-wide table{display:block;width:100%!important;min-width:0!important;table-layout:auto}
  .routespro-dashboard .ffad-analytics-table thead{display:none}
  .routespro-dashboard .ffad-analytics-table tbody{display:block}
  .routespro-dashboard .ffad-analytics-table tbody tr{display:block;margin:10px 0;border:1px solid #dbe6f2;border-radius:14px;background:#fff;box-shadow:0 8px 20px rgba(15,23,42,.045);overflow:hidden}
  .routespro-dashboard .ffad-analytics-table tbody td{display:grid;grid-template-columns:minmax(100px,38%) minmax(0,1fr);gap:10px;align-items:start;width:100%!important;min-width:0!important;max-width:none!important;padding:9px 11px;border-bottom:1px solid #eef2f7;background:#fff;white-space:normal!important}
  .routespro-dashboard .ffad-analytics-table tbody td:last-child{border-bottom:0}
  .routespro-dashboard .ffad-analytics-table tbody td:before{content:attr(data-label);color:#64748b;font-size:10px;font-weight:850;text-transform:uppercase;letter-spacing:.055em}
  .routespro-dashboard .ffad-analytics-hint{margin-top:8px}
}
@media(max-width:440px){
  .routespro-dashboard .ffad-fronts{grid-template-columns:1fr}
  .routespro-dashboard .ffad-analytics-table tbody td{grid-template-columns:1fr;gap:4px}
}

/* 2.2.169, Analytics anti-overlap layout for merchandisers */
.routespro-dashboard .ffad-custom-dashboard{overflow:visible}
.routespro-dashboard .ffad-metric-body{display:grid;grid-template-columns:minmax(0,1fr);gap:14px;min-width:0;margin-top:12px}
.routespro-dashboard .ffad-metric-body>.ffad-metric-note,.routespro-dashboard .ffad-metric-body>.ffad-series,.routespro-dashboard .ffad-metric-body>.ffad-fronts,.routespro-dashboard .ffad-metric-body>.ffad-evidence,.routespro-dashboard .ffad-metric-body>.ffad-analytics-table{margin-top:0!important}
.routespro-dashboard .ffad-metric.has-table,.routespro-dashboard .ffad-metric.has-fronts{overflow:visible}
.routespro-dashboard .ffad-metric.has-table:before,.routespro-dashboard .ffad-metric.has-fronts:before{border-radius:18px 18px 0 0}
.routespro-dashboard .ffad-table-preview-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:2px;padding:10px 12px;border:1px solid #dbeafe;border-bottom:0;border-radius:14px 14px 0 0;background:#eff6ff}
.routespro-dashboard .ffad-table-preview-head strong{display:block;color:#0f172a;font-size:12px}
.routespro-dashboard .ffad-table-preview-head span{display:inline-flex;padding:5px 8px;border-radius:999px;background:#fff;color:#1d4ed8;font-size:10px;font-weight:850;white-space:nowrap}
.routespro-dashboard .ffad-table-preview-head+.ffad-analytics-table{border-radius:0 0 14px 14px}
.routespro-dashboard .ffad-analytics-table{width:100%;max-height:none!important;overflow-x:auto!important;overflow-y:hidden!important;scrollbar-gutter:auto!important}
.routespro-dashboard .ffad-analytics-table table{min-width:940px!important}
.routespro-dashboard .ffad-analytics-hint{margin:0!important;padding:0 2px;line-height:1.45}
@media(max-width:700px){
  .routespro-dashboard .ffad-metric-body{gap:11px;margin-top:10px}
  .routespro-dashboard .ffad-table-preview-head{border-radius:12px 12px 0 0}
  .routespro-dashboard .ffad-table-preview-head+.ffad-analytics-table{border-radius:0 0 12px 12px}
  .routespro-dashboard .ffad-analytics-table{overflow:visible!important}
}


/* 2.2.170, premium analytics reading system for merchandisers */
.routespro-dashboard .ffad-custom-dashboards{gap:18px}
.routespro-dashboard .ffad-custom-dashboard{padding:0!important;border:1px solid #d9e4f0;border-radius:24px;background:#fff;overflow:hidden;box-shadow:0 18px 46px rgba(15,23,42,.075)}
.routespro-dashboard .ffad-custom-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin:0!important;padding:18px 20px;border-bottom:1px solid #e8eef5;background:radial-gradient(circle at 100% 0,rgba(37,99,235,.09),transparent 34%),linear-gradient(135deg,#fbfdff,#f4f8fd)}
.routespro-dashboard .ffad-custom-head-copy{min-width:0}.routespro-dashboard .ffad-custom-head-copy>span{display:block;margin-bottom:5px;color:#2563eb;font-size:9px;font-weight:900;letter-spacing:.11em;text-transform:uppercase}.routespro-dashboard .ffad-custom-head h5{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin:0;font-size:19px;line-height:1.25;letter-spacing:-.02em}.routespro-dashboard .ffad-custom-head p{max-width:760px;margin:6px 0 0;line-height:1.5}.routespro-dashboard .ffad-custom-head>em{display:inline-flex;align-items:center;min-height:32px;padding:6px 10px;border:1px solid #d8e6fb;border-radius:999px;background:#fff;color:#1d4ed8;font-size:10px;font-style:normal;font-weight:850;white-space:nowrap}
.routespro-dashboard .ffad-custom-grid{padding:16px;gap:16px;background:#f8fafc}
.routespro-dashboard .ffad-metric{padding:0!important;border:1px solid #dbe5f0!important;border-radius:20px!important;background:#fff!important;box-shadow:0 12px 30px rgba(15,23,42,.05)!important;overflow:hidden}
.routespro-dashboard .ffad-metric:before{height:3px;border-radius:20px 20px 0 0}
.routespro-dashboard .ffad-metric-label{padding:17px 18px 14px!important;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#fff,#fbfdff)}.routespro-dashboard .ffad-metric-copy{min-width:0;max-width:820px}.routespro-dashboard .ffad-metric-eyebrow{display:block!important;margin-bottom:5px;padding:0!important;background:transparent!important;color:#2563eb!important;font-size:9px!important;font-weight:900!important;letter-spacing:.1em;text-transform:uppercase}.routespro-dashboard .ffad-metric-label strong{display:block;font-size:18px!important;line-height:1.25;letter-spacing:-.015em}.routespro-dashboard .ffad-metric-label>span{min-height:28px;padding:6px 9px!important;border:1px solid #dce7f4;background:#f7faff!important;color:#334155!important;text-transform:uppercase;letter-spacing:.035em}.routespro-dashboard .ffad-question{margin-top:6px!important;color:#64748b!important;font-size:10px!important;line-height:1.4;text-transform:none!important;letter-spacing:0!important;font-weight:700!important}
.routespro-dashboard .ffad-metric-body{gap:13px!important;margin:0!important;padding:16px 18px 18px;background:#fff}.routespro-dashboard .ffad-metric-note{padding:9px 11px;border:1px solid #e3e9f0;border-radius:11px;background:#f8fafc;color:#526174;line-height:1.45}.routespro-dashboard .ffad-metric-value{margin:0;padding:14px 15px;border:1px solid #dbeafe;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#fff);font-size:32px;letter-spacing:-.035em}
.routespro-dashboard .ffad-series{gap:0;margin:0;padding:5px 13px;border:1px solid #e3eaf2;border-radius:15px;background:linear-gradient(180deg,#fbfdff,#fff)}.routespro-dashboard .ffad-series-row{grid-template-columns:24px minmax(120px,1.1fr) minmax(100px,1.7fr) auto;gap:9px;min-height:42px;padding:7px 0;border-bottom:1px solid #edf2f7}.routespro-dashboard .ffad-series-row:last-child{border-bottom:0}.routespro-dashboard .ffad-series-row>em{display:grid;place-items:center;width:22px;height:22px;border-radius:7px;background:#eef4fb;color:#475569;font-size:10px;font-style:normal;font-weight:850}.routespro-dashboard .ffad-series-row>span:first-of-type{font-weight:700;line-height:1.35}.routespro-dashboard .ffad-series-track{height:8px;background:#e7edf4;box-shadow:inset 0 1px 2px rgba(15,23,42,.05)}.routespro-dashboard .ffad-series-row b{min-width:38px;text-align:right;font-size:11px}
.routespro-dashboard .ffad-fronts{grid-template-columns:repeat(5,minmax(0,1fr));gap:9px;margin:0}.routespro-dashboard .ffad-fronts div{min-height:104px;padding:13px;border-color:#dfe7f0;border-radius:14px;background:linear-gradient(180deg,#fff,#f8fafc);box-shadow:0 5px 14px rgba(15,23,42,.03)}.routespro-dashboard .ffad-fronts span{line-height:1.3}.routespro-dashboard .ffad-fronts strong{margin-top:7px;font-size:22px;letter-spacing:-.025em}
.routespro-dashboard .ffad-widget-detail{margin:0;border:1px solid #dfe7f0;border-radius:15px;background:#fff;overflow:hidden}.routespro-dashboard .ffad-widget-detail>summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;cursor:pointer;list-style:none;background:linear-gradient(180deg,#fff,#f7f9fc)}.routespro-dashboard .ffad-widget-detail>summary::-webkit-details-marker{display:none}.routespro-dashboard .ffad-widget-detail>summary>div{min-width:0}.routespro-dashboard .ffad-widget-detail>summary span{display:block;color:#2563eb;font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.routespro-dashboard .ffad-widget-detail>summary strong{display:block;margin-top:3px;font-size:12px}.routespro-dashboard .ffad-widget-detail>summary em{display:inline-flex;padding:5px 8px;border:1px solid #dbeafe;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-style:normal;font-weight:850;white-space:nowrap}.routespro-dashboard .ffad-widget-detail>summary:after{content:'+';display:grid;place-items:center;width:27px;height:27px;border:1px solid #dbe5f0;border-radius:999px;background:#fff;color:#334155;font-size:17px}.routespro-dashboard .ffad-widget-detail[open]>summary:after{content:'−'}.routespro-dashboard .ffad-widget-detail-body{padding:0 11px 11px}.routespro-dashboard .ffad-widget-detail .ffad-analytics-table{margin:0!important;border:1px solid #e3e9f0!important;border-radius:12px!important}.routespro-dashboard .ffad-widget-detail .ffad-analytics-hint{padding:8px 2px 0!important}.routespro-dashboard .ffad-widget-detail table th{background:#f3f6fa;color:#526174}.routespro-dashboard .ffad-widget-detail table tbody tr:nth-child(even){background:#fbfcfe}
.routespro-dashboard .ffad-evidence{margin:0;border-color:#dfe7f0;border-radius:15px;background:#f8fbff}.routespro-dashboard .ffad-evidence summary{padding:13px 14px}.routespro-dashboard .ffad-evidence-list{gap:7px;padding:0 11px 11px}.routespro-dashboard .ffad-evidence-row{min-height:58px;border-color:#e1e8f0;box-shadow:0 4px 12px rgba(15,23,42,.03)}.routespro-dashboard .ffad-evidence-row a{min-height:36px;align-items:center;border-radius:10px}
@media(max-width:1000px){.routespro-dashboard .ffad-fronts{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:700px){.routespro-dashboard .ffad-custom-dashboard{border-radius:19px}.routespro-dashboard .ffad-custom-head{flex-direction:column;gap:10px;padding:15px}.routespro-dashboard .ffad-custom-head h5{font-size:18px}.routespro-dashboard .ffad-custom-grid{padding:9px;gap:11px}.routespro-dashboard .ffad-metric{border-radius:16px!important}.routespro-dashboard .ffad-metric-label{padding:15px!important}.routespro-dashboard .ffad-metric-label strong{font-size:17px!important}.routespro-dashboard .ffad-metric-body{padding:12px!important}.routespro-dashboard .ffad-series-row{grid-template-columns:24px minmax(0,1fr) auto;gap:8px}.routespro-dashboard .ffad-series-track{grid-column:2/-1;width:100%}.routespro-dashboard .ffad-fronts{grid-template-columns:repeat(2,minmax(0,1fr))}.routespro-dashboard .ffad-widget-detail>summary{padding:12px}.routespro-dashboard .ffad-widget-detail-body{padding:0 8px 9px}.routespro-dashboard .ffad-widget-detail .ffad-analytics-table{border:0!important;background:transparent}.routespro-dashboard .ffad-evidence-row a{width:100%;justify-content:center}}
@media(max-width:440px){.routespro-dashboard .ffad-fronts{grid-template-columns:1fr}.routespro-dashboard .ffad-widget-detail>summary{display:grid;grid-template-columns:minmax(0,1fr) auto}.routespro-dashboard .ffad-widget-detail>summary:after{grid-column:2;grid-row:1}.routespro-dashboard .ffad-widget-detail>summary em{grid-column:1/-1;justify-self:start}}



/* 2.2.183, compact premium evidence tabs for shared analytics */
.routespro-dashboard{color:var(--ff-text,#0f172a)!important;background:transparent}
.routespro-dashboard.routespro-dashboard h1,.routespro-dashboard.routespro-dashboard h2,.routespro-dashboard.routespro-dashboard h3,.routespro-dashboard.routespro-dashboard h4,.routespro-dashboard.routespro-dashboard h5,.routespro-dashboard.routespro-dashboard h6,.routespro-dashboard.routespro-dashboard strong,.routespro-dashboard.routespro-dashboard b{color:var(--ff-heading,#0f172a)!important}
.routespro-dashboard.routespro-dashboard p,.routespro-dashboard.routespro-dashboard small,.routespro-dashboard.routespro-dashboard .ffad-metric-note,.routespro-dashboard.routespro-dashboard .ffad-analytics-hint{color:var(--ff-muted,#64748b)!important}
.routespro-dashboard.routespro-dashboard td,.routespro-dashboard.routespro-dashboard li,.routespro-dashboard.routespro-dashboard dd{color:var(--ff-text,#0f172a)!important}
.routespro-dashboard .ffad-head,.routespro-dashboard .ffad-head h4,.routespro-dashboard .ffad-head strong,.routespro-dashboard .ffad-scope{color:var(--ff-header-title-text,var(--ff-hero-text,#fff))!important}
.routespro-dashboard .ffad-head p,.routespro-dashboard .ffad-head span,.routespro-dashboard .ffad-head small{color:var(--ff-header-body-text,var(--ff-hero-muted,rgba(255,255,255,.78)))!important}
.routespro-dashboard .ffad-filter span,.routespro-dashboard .ffad-export-copy span,.routespro-dashboard .ffad-custom-head-copy>span,.routespro-dashboard .ffad-metric-eyebrow,.routespro-dashboard .ffad-question,.routespro-dashboard label{color:var(--ff-label,#2563eb)!important}
.routespro-dashboard a:not(.ffad-btn):not(.ffad-evidence-action){color:var(--ff-link,#2563eb)}
.routespro-dashboard a:not(.ffad-btn):not(.ffad-evidence-action):hover{color:var(--ff-link-hover,#1d4ed8)}
.routespro-dashboard select,.routespro-dashboard input,.routespro-dashboard textarea{color:var(--ff-input-text,#0f172a)!important}
.routespro-dashboard input::placeholder,.routespro-dashboard textarea::placeholder{color:var(--ff-placeholder,#94a3b8)!important;opacity:1}
.routespro-dashboard ::selection{color:var(--ff-selection-text,#fff)!important;background:var(--ff-selection-bg,#2563eb)!important}.routespro-dashboard ::-moz-selection{color:var(--ff-selection-text,#fff)!important;background:var(--ff-selection-bg,#2563eb)!important}
.routespro-dashboard .ffad-btn.primary,.routespro-dashboard .ffad-btn.alt,.routespro-dashboard .ffad-evidence-action,.routespro-dashboard .ffad-evidence-row a{color:var(--ff-button-text,#fff)!important}.routespro-dashboard .ffad-btn.primary:hover,.routespro-dashboard .ffad-btn.primary:focus,.routespro-dashboard .ffad-btn.alt:hover,.routespro-dashboard .ffad-btn.alt:focus,.routespro-dashboard .ffad-evidence-action:hover,.routespro-dashboard .ffad-evidence-action:focus,.routespro-dashboard .ffad-evidence-row a:hover,.routespro-dashboard .ffad-evidence-row a:focus{color:var(--ff-button-hover-text,#fff)!important}
.routespro-dashboard .ffad-btn:not(.primary):not(.alt),.routespro-dashboard .ffad-preset{color:var(--ff-button-secondary-text,#0f172a)!important}.routespro-dashboard .ffad-btn:not(.primary):not(.alt):hover,.routespro-dashboard .ffad-btn:not(.primary):not(.alt):focus,.routespro-dashboard .ffad-preset:hover,.routespro-dashboard .ffad-preset:focus{color:var(--ff-button-secondary-hover-text,#0f172a)!important}
.routespro-dashboard th{color:var(--ff-table-header-text,#64748b)!important}
.routespro-dashboard .ffad-ai-badge,.routespro-dashboard .ffad-status{color:var(--ff-badge-text,#334155)!important}
.routespro-dashboard .ffad-status.completed,.routespro-dashboard .ffad-status.done{color:var(--ff-success-text,#166534)!important}
.routespro-dashboard .ffad-status.pending,.routespro-dashboard .ffad-status.warning{color:var(--ff-warning-text,#92400e)!important}
.routespro-dashboard .ffad-status.cancelled,.routespro-dashboard .ffad-status.error{color:var(--ff-danger-text,#991b1b)!important}
.routespro-dashboard .ffad-custom-dashboard{border-color:var(--ff-border,#d9e4f0);background:var(--ff-surface,#fff)}
.routespro-dashboard .ffad-custom-head{border-color:var(--ff-border,#e8eef5);background:linear-gradient(135deg,color-mix(in srgb,var(--ff-canvas,#f8fafc) 82%,var(--ff-surface,#fff)),var(--ff-surface,#fff))}
.routespro-dashboard .ffad-custom-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 72%,var(--ff-surface,#fff))}
.routespro-dashboard .ffad-metric{border-color:var(--ff-border,#dbe5f0)!important;background:var(--ff-surface,#fff)!important}
.routespro-dashboard .ffad-metric-label{border-color:var(--ff-border,#edf2f7);background:linear-gradient(180deg,var(--ff-surface,#fff),color-mix(in srgb,var(--ff-canvas,#f8fafc) 40%,var(--ff-surface,#fff)))}
.routespro-dashboard .ffad-metric-body{background:var(--ff-surface,#fff)}
.routespro-dashboard .ffad-metric-label>span,.routespro-dashboard .ffad-ai-badge{border:1px solid color-mix(in srgb,var(--ff-border,#dbe5f0) 80%,transparent);background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 72%,var(--ff-surface,#fff))!important;color:var(--ff-badge-text,#334155)!important}
.routespro-dashboard .ffad-question{margin-top:7px!important;font-size:11px!important;text-transform:none!important;letter-spacing:0!important;font-weight:700!important}
.routespro-dashboard .ffad-metric-note{border-color:var(--ff-border,#e3e9f0);background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 62%,var(--ff-surface,#fff))}
.routespro-dashboard .ffad-evidence-surface{display:grid;gap:14px;padding:2px;background:transparent}
.routespro-dashboard .ffad-evidence-store-list,.routespro-dashboard .ffad-evidence-date-first{display:grid;gap:14px;width:100%}
.routespro-dashboard .ffad-evidence-store-card,.routespro-dashboard .ffad-evidence-date-card{overflow:hidden;border:1px solid var(--ff-border,#dbe5f0);border-radius:18px;background:var(--ff-surface,#fff);box-shadow:0 9px 24px rgba(15,23,42,.045)}
.routespro-dashboard .ffad-evidence-store-head,.routespro-dashboard .ffad-evidence-date-first-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;border-bottom:1px solid var(--ff-border,#dbe5f0);background:linear-gradient(180deg,color-mix(in srgb,var(--ff-surface,#fff) 96%,var(--ff-canvas,#f8fafc)),var(--ff-surface,#fff))}
.routespro-dashboard .ffad-evidence-store-copy,.routespro-dashboard .ffad-evidence-date-copy{min-width:0}
.routespro-dashboard .ffad-evidence-store-copy strong,.routespro-dashboard .ffad-evidence-date-copy strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px}
.routespro-dashboard .ffad-evidence-store-copy span,.routespro-dashboard .ffad-evidence-date-copy span{display:block;margin-top:3px;color:var(--ff-muted,#64748b);font-size:11px}
.routespro-dashboard .ffad-evidence-date-list{display:grid;gap:10px;padding:12px}
.routespro-dashboard .ffad-evidence-date-group{overflow:hidden;border:1px solid color-mix(in srgb,var(--ff-border,#dbe5f0) 82%,transparent);border-radius:14px;background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 48%,var(--ff-surface,#fff))}
.routespro-dashboard .ffad-evidence-date-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 11px;border-bottom:1px solid color-mix(in srgb,var(--ff-border,#dbe5f0) 75%,transparent)}
.routespro-dashboard .ffad-evidence-date-head>div{display:flex;align-items:center;gap:9px;min-width:0;flex-wrap:wrap}
.routespro-dashboard .ffad-evidence-date-head strong{font-size:12px}.routespro-dashboard .ffad-evidence-date-head span{color:var(--ff-muted,#64748b);font-size:11px;white-space:nowrap}
.routespro-dashboard .ffad-evidence-action{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 11px;border:1px solid color-mix(in srgb,var(--ff-heading,#0f172a) 80%,transparent)!important;border-radius:10px;background:var(--ff-heading,#0f172a)!important;text-decoration:none!important;font-size:11px;font-weight:850;white-space:nowrap}
.routespro-dashboard .ffad-evidence-action.is-disabled{opacity:.5;cursor:not-allowed}.routespro-dashboard .ffad-evidence-date-head .ffad-evidence-action{min-height:31px;padding:6px 9px;font-size:10px}
.routespro-dashboard .ffad-evidence-shell{display:grid;gap:12px}
.routespro-dashboard .ffad-evidence-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:2px 2px 0}
.routespro-dashboard .ffad-evidence-tabs{display:inline-flex;align-items:center;gap:4px;padding:4px;border:1px solid color-mix(in srgb,var(--ff-border,#dbe5f0) 80%,transparent);border-radius:999px;background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 74%,var(--ff-surface,#fff))}
.routespro-dashboard .ffad-evidence-tab{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:8px 14px;border:0;border-radius:999px;background:transparent;color:var(--ff-muted,#64748b);font-size:11px;font-weight:850;letter-spacing:.01em;cursor:pointer;transition:all .18s ease}
.routespro-dashboard .ffad-evidence-tab:hover,.routespro-dashboard .ffad-evidence-tab:focus-visible{background:color-mix(in srgb,var(--ff-surface,#fff) 85%,transparent);color:var(--ff-heading,#0f172a);outline:none}
.routespro-dashboard .ffad-evidence-tab.is-active{background:var(--ff-heading,#0f172a);color:#fff;box-shadow:0 8px 20px rgba(15,23,42,.12)}
.routespro-dashboard .ffad-evidence-toolbar-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;color:var(--ff-muted,#64748b);font-size:11px;font-weight:700}
.routespro-dashboard .ffad-evidence-toolbar-meta .ffad-evidence-pill{display:inline-flex;align-items:center;min-height:28px;padding:6px 10px;border:1px solid color-mix(in srgb,var(--ff-border,#dbe5f0) 78%,transparent);border-radius:999px;background:#fff;color:var(--ff-heading,#0f172a);font-size:10px;font-weight:850;white-space:nowrap}
.routespro-dashboard .ffad-evidence-panel{display:none}.routespro-dashboard .ffad-evidence-panel.is-active{display:block}
.routespro-dashboard .ffad-evidence-thumbs{display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,96px));justify-content:flex-start;gap:9px;padding:10px}
.routespro-dashboard .ffad-evidence-thumb{position:relative;display:block;min-height:88px;overflow:hidden;border:1px solid var(--ff-border,#dbe5f0);border-radius:11px;background:var(--ff-surface,#fff);text-decoration:none!important}
.routespro-dashboard .ffad-evidence-thumb img{display:block;width:100%;height:72px;object-fit:cover;transition:transform .18s ease}.routespro-dashboard .ffad-evidence-thumb:hover img{transform:scale(1.035)}
.routespro-dashboard .ffad-evidence-thumb span{display:block;padding:6px 7px;color:var(--ff-muted,#64748b);font-size:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.routespro-dashboard .ffad-evidence-file{display:grid;place-items:center;min-height:72px;padding:12px;color:var(--ff-link,#2563eb)!important;font-weight:800;text-align:center;font-size:10px}
.routespro-dashboard .ffad-evidence-date-first-body{display:grid;gap:9px;padding:12px}.routespro-dashboard .ffad-evidence-date-first-store{overflow:hidden;border:1px solid color-mix(in srgb,var(--ff-border,#dbe5f0) 82%,transparent);border-radius:13px;background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 48%,var(--ff-surface,#fff))}.routespro-dashboard .ffad-evidence-date-first-store-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 11px}.routespro-dashboard .ffad-evidence-date-first-store-head strong{font-size:12px}
.routespro-dashboard .ffad-analytics-table,.routespro-dashboard .ffad-widget-detail{border-color:var(--ff-border,#dfe7f0)!important;background:var(--ff-surface,#fff)}
.routespro-dashboard .ffad-widget-detail>summary,.routespro-dashboard th{background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 68%,var(--ff-surface,#fff))!important}
.routespro-dashboard tbody tr:hover{background:color-mix(in srgb,var(--ffad-primary) 7%,var(--ff-surface,#fff))!important}
@media(max-width:700px){.routespro-dashboard .ffad-custom-grid{grid-template-columns:1fr}.routespro-dashboard .ffad-evidence-store-head,.routespro-dashboard .ffad-evidence-date-first-head,.routespro-dashboard .ffad-evidence-date-head,.routespro-dashboard .ffad-evidence-toolbar{align-items:flex-start;flex-direction:column}.routespro-dashboard .ffad-evidence-action{width:100%}.routespro-dashboard .ffad-evidence-tabs{width:100%;overflow:auto}.routespro-dashboard .ffad-evidence-tab{flex:1 0 auto}.routespro-dashboard .ffad-evidence-thumbs{grid-template-columns:repeat(2,minmax(0,1fr))}.routespro-dashboard .ffad-evidence-thumb img{height:112px}}

/* 2.2.213, camada final de headers e reflow real das tabelas analytics */
.routespro-dashboard.routespro-dashboard .ffad-head :is(h1,h2,h3,h4,h5,h6,strong,b),.routespro-dashboard.routespro-dashboard .ffad-head .ffad-scope{color:var(--ff-header-title-text,var(--ff-hero-text,#fff))!important}
.routespro-dashboard.routespro-dashboard .ffad-head :is(p,small),.routespro-dashboard.routespro-dashboard .ffad-head span:not(.ffad-scope){color:var(--ff-header-body-text,var(--ff-hero-muted,#dbeafe))!important}
.routespro-dashboard .ffad-custom-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,430px),1fr))!important;grid-auto-flow:row;align-items:start!important}
.routespro-dashboard .ffad-metric.is-wide,.routespro-dashboard .ffad-metric.has-table,.routespro-dashboard .ffad-metric.has-fronts,.routespro-dashboard .ffad-metric.has-share,.routespro-dashboard .ffad-metric.has-evidence{grid-column:1/-1!important;width:100%;min-width:0}
.routespro-dashboard .ffad-metric-body,.routespro-dashboard .ffad-widget-detail,.routespro-dashboard .ffad-widget-detail-body{min-width:0}
.routespro-dashboard .ffad-analytics-table{width:100%!important;max-width:100%;max-height:min(620px,62vh)!important;overflow:auto!important;border:1px solid var(--ff-border,#dfe7f0)!important;border-radius:13px!important;background:var(--ff-surface,#fff)!important;scrollbar-gutter:stable}
.routespro-dashboard .ffad-analytics-table table{width:100%!important;min-width:820px!important;border-collapse:separate;border-spacing:0;table-layout:auto}
.routespro-dashboard .ffad-analytics-table.is-standard table{min-width:700px!important}.routespro-dashboard .ffad-analytics-table.is-compact table{min-width:100%!important}
.routespro-dashboard .ffad-analytics-table th{position:sticky;top:0;z-index:2;padding:11px 12px!important;background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 78%,var(--ff-surface,#fff))!important;box-shadow:0 1px 0 var(--ff-border,#e2e8f0)}
.routespro-dashboard .ffad-analytics-table td{padding:11px 12px!important;vertical-align:top}.routespro-dashboard .ffad-analytics-table tbody tr:nth-child(even){background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 48%,var(--ff-surface,#fff))}
@media(max-width:1180px){
  .routespro-dashboard .ffad-custom-grid{grid-template-columns:1fr!important}
  .routespro-dashboard .ffad-metric.is-wide,.routespro-dashboard .ffad-metric.has-table,.routespro-dashboard .ffad-metric.has-fronts,.routespro-dashboard .ffad-metric.has-share,.routespro-dashboard .ffad-metric.has-evidence{grid-column:1!important}
  .routespro-dashboard .ffad-analytics-table{max-height:none!important;overflow:visible!important;border:0!important;background:transparent!important}
  .routespro-dashboard .ffad-analytics-table table,.routespro-dashboard .ffad-analytics-table.is-compact table,.routespro-dashboard .ffad-analytics-table.is-standard table,.routespro-dashboard .ffad-analytics-table.is-wide table{display:block!important;width:100%!important;min-width:0!important;max-width:100%!important;table-layout:auto!important}
  .routespro-dashboard .ffad-analytics-table thead{display:none!important}.routespro-dashboard .ffad-analytics-table tbody{display:grid!important;gap:11px;width:100%}
  .routespro-dashboard .ffad-analytics-table tbody tr{display:block!important;width:100%;margin:0!important;border:1px solid var(--ff-border,#dbe6f2)!important;border-radius:15px!important;background:var(--ff-surface,#fff)!important;box-shadow:0 8px 20px rgba(15,23,42,.045);overflow:hidden}
  .routespro-dashboard .ffad-analytics-table tbody td{display:grid!important;grid-template-columns:minmax(116px,34%) minmax(0,1fr)!important;gap:10px;align-items:start;width:100%!important;min-width:0!important;max-width:none!important;padding:9px 11px!important;border:0!important;border-bottom:1px solid color-mix(in srgb,var(--ff-border,#e2e8f0) 72%,transparent)!important;background:transparent!important;white-space:normal!important;overflow-wrap:anywhere}
  .routespro-dashboard .ffad-analytics-table tbody td:last-child{border-bottom:0!important}.routespro-dashboard .ffad-analytics-table tbody td:before{content:attr(data-label);display:block;color:var(--ff-muted,#64748b);font-size:10px;font-weight:850;text-transform:uppercase;letter-spacing:.055em}
  .routespro-dashboard .ffad-widget-detail-body{padding:0 10px 11px!important}
}
@media(max-width:560px){.routespro-dashboard .ffad-analytics-table tbody td{grid-template-columns:1fr!important;gap:4px}.routespro-dashboard .ffad-fronts{grid-template-columns:1fr!important}}

/* 2.2.257, ordem canónica e layouts do Intelligence Center */
.routespro-dashboard .ffad-custom-dashboard.layout-executive .ffad-custom-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}
.routespro-dashboard .ffad-custom-dashboard.layout-table .ffad-custom-grid{grid-template-columns:minmax(0,1fr)!important}
.routespro-dashboard .ffad-custom-dashboard.layout-table .ffad-metric{grid-column:1/-1!important}
@media(max-width:1180px){.routespro-dashboard .ffad-custom-dashboard.layout-executive .ffad-custom-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media(max-width:760px){.routespro-dashboard .ffad-custom-dashboard.layout-executive .ffad-custom-grid{grid-template-columns:1fr!important}}

/* 2.2.337, mobile merchandiser Analytics header reflow. */
@media(max-width:760px){
  .routespro-dashboard .ffad-metric-label{display:grid!important;grid-template-columns:minmax(0,1fr)!important;gap:8px!important;width:100%!important;max-width:100%!important}
  .routespro-dashboard .ffad-metric-copy,.routespro-dashboard .ffad-metric-label>*{min-width:0!important;max-width:100%!important}
  .routespro-dashboard .ffad-metric-eyebrow{display:none!important}
  .routespro-dashboard .ffad-metric-label strong{width:100%!important;max-width:100%!important;writing-mode:horizontal-tb!important;text-orientation:mixed!important;white-space:normal!important;word-break:normal!important;overflow-wrap:break-word!important;hyphens:auto!important}
  .routespro-dashboard .ffad-metric-label>span{justify-self:start!important;width:auto!important;max-width:100%!important;white-space:normal!important}
}


/* 2.2.448, Front Analytics Parity for Client and Merchandiser */
.routespro-dashboard .ffad-command-view,.routespro-dashboard .ffad-priority-surface,.routespro-dashboard .ffad-ask-fieldflow{border:1px solid #dbe3ef;border-radius:20px;background:#fff;padding:16px;box-shadow:0 10px 28px rgba(15,23,42,.05);min-width:0}
.routespro-dashboard .ffad-command-head,.routespro-dashboard .ffad-priority-head,.routespro-dashboard .ffad-ask-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px}.routespro-dashboard .ffad-command-head h5,.routespro-dashboard .ffad-priority-head h5,.routespro-dashboard .ffad-ask-head h5{margin:0;font-size:18px;color:#0f172a}.routespro-dashboard .ffad-command-head p,.routespro-dashboard .ffad-priority-head p,.routespro-dashboard .ffad-ask-head p{margin:4px 0 0;color:#64748b;line-height:1.45}.routespro-dashboard .ffad-parity-badge{display:inline-flex;padding:6px 9px;border:1px solid #c7d2fe;border-radius:999px;background:#eef2ff;color:#4338ca;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap}
.routespro-dashboard .ffad-score-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px}.routespro-dashboard .ffad-score-card{position:relative;overflow:hidden;min-height:112px;padding:12px;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc}.routespro-dashboard .ffad-score-card:before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:#94a3b8}.routespro-dashboard .ffad-score-card.is-good:before{background:#16a34a}.routespro-dashboard .ffad-score-card.is-watch:before{background:#f59e0b}.routespro-dashboard .ffad-score-card.is-critical:before{background:#dc2626}.routespro-dashboard .ffad-score-card.is-opportunity:before{background:#7c3aed}.routespro-dashboard .ffad-score-card span{display:block;color:#64748b;font-size:10px;font-weight:850;text-transform:uppercase;letter-spacing:.055em}.routespro-dashboard .ffad-score-card strong{display:block;margin:7px 0 4px;font-size:23px;line-height:1;color:#0f172a}.routespro-dashboard .ffad-score-card b{display:block;color:#334155;font-size:11px}.routespro-dashboard .ffad-score-card small{display:block;margin-top:5px;color:#64748b;line-height:1.3;font-size:10px}
.routespro-dashboard .ffad-priority-surface{background:linear-gradient(145deg,#fff,#f8fafc 55%,#ecfeff);border-color:#bae6fd}
.routespro-dashboard .ffad-audience-performance{padding:14px;border:1px solid #dbeafe;border-radius:18px;background:linear-gradient(145deg,#fff,#f8fafc 58%,#eff6ff)}.routespro-dashboard .ffad-performance-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:10px}.routespro-dashboard .ffad-performance-head h5{margin:0}.routespro-dashboard .ffad-performance-head p{margin:4px 0 0;color:#64748b}.routespro-dashboard .ffad-performance-layout{display:grid;grid-template-columns:minmax(240px,.8fr) minmax(0,1.5fr);gap:10px}.routespro-dashboard .ffad-performance-focus{padding:15px;border-radius:15px;background:#172554;color:#fff}.routespro-dashboard .ffad-performance-focus span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.07em;font-weight:900;opacity:.78}.routespro-dashboard .ffad-performance-focus h6{margin:6px 0 2px;font-size:18px;color:#fff}.routespro-dashboard .ffad-performance-focus strong{display:block;font-size:30px;color:#fff}.routespro-dashboard .ffad-performance-focus p{margin:6px 0 0;color:rgba(255,255,255,.82);font-size:11px;line-height:1.45}.routespro-dashboard .ffad-performance-list{display:grid;gap:7px}.routespro-dashboard .ffad-performance-row{display:grid;grid-template-columns:auto minmax(0,1fr) repeat(3,auto);gap:8px;align-items:center;padding:9px 10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.routespro-dashboard .ffad-performance-row>span{display:grid;place-items:center;width:25px;height:25px;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:900}.routespro-dashboard .ffad-performance-row div:first-of-type{min-width:0}.routespro-dashboard .ffad-performance-row div:first-of-type strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.routespro-dashboard .ffad-performance-row div:first-of-type small{display:block;color:#64748b;font-size:9px}.routespro-dashboard .ffad-performance-metric{text-align:right}.routespro-dashboard .ffad-performance-metric strong{display:block;font-size:11px}.routespro-dashboard .ffad-performance-metric small{display:block;color:#64748b;font-size:8px;text-transform:uppercase}.routespro-dashboard .ffad-performance-note{margin-top:8px;color:#64748b;font-size:10px;line-height:1.4}@media(max-width:760px){.routespro-dashboard .ffad-performance-layout{grid-template-columns:1fr}.routespro-dashboard .ffad-performance-row{grid-template-columns:auto minmax(0,1fr) auto}.routespro-dashboard .ffad-performance-metric:nth-last-child(-n+2){display:none}}
.routespro-dashboard .ffad-priority-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.routespro-dashboard .ffad-priority-card{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:10px;align-items:flex-start;padding:12px;border:1px solid #dbeafe;border-radius:15px;background:rgba(255,255,255,.94)}.routespro-dashboard .ffad-priority-index{display:grid;place-items:center;width:27px;height:27px;border-radius:9px;background:#172554;color:#fff;font-size:11px;font-weight:900}.routespro-dashboard .ffad-priority-copy{min-width:0}.routespro-dashboard .ffad-priority-copy strong{display:block;font-size:13px;color:#0f172a}.routespro-dashboard .ffad-priority-copy p{margin:3px 0 0;color:#334155;font-size:12px;line-height:1.4}.routespro-dashboard .ffad-priority-copy small{display:block;margin-top:5px;color:#64748b;font-size:10px;line-height:1.35}.routespro-dashboard .ffad-priority-score{display:inline-flex;padding:5px 7px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-weight:900;white-space:nowrap}
.routespro-dashboard .ffad-ask-fieldflow{border-color:#c7d2fe;background:linear-gradient(145deg,#fff,#f8fafc 58%,#eef2ff)}.routespro-dashboard .ffad-ask-suggestions{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}.routespro-dashboard .ffad-ask-suggestions button{padding:7px 10px;border:1px solid #cbd5e1;border-radius:999px;background:#fff;color:#334155;font-size:11px;font-weight:800;cursor:pointer}.routespro-dashboard .ffad-ask-suggestions button:hover{border-color:#818cf8;background:#eef2ff;color:#3730a3}.routespro-dashboard .ffad-ask-thread{display:grid;gap:10px;max-height:430px;overflow:auto;margin-top:12px;padding:12px;border:1px solid #dbe3ee;border-radius:15px;background:#f8fafc}.routespro-dashboard .ffad-ask-empty{display:grid;gap:4px;padding:16px;text-align:center;color:#64748b}.routespro-dashboard .ffad-ask-empty strong{color:#334155}.routespro-dashboard .ffad-ask-message{display:grid;gap:7px;max-width:90%;padding:11px 13px;border:1px solid #e2e8f0;border-radius:15px;background:#fff}.routespro-dashboard .ffad-ask-message.is-user{justify-self:end;background:#172554;border-color:#172554;color:#fff}.routespro-dashboard .ffad-ask-message.is-user strong,.routespro-dashboard .ffad-ask-message.is-user p{color:#fff}.routespro-dashboard .ffad-ask-message strong{font-size:11px;color:#1e3a8a}.routespro-dashboard .ffad-ask-message p{margin:0;color:#334155;line-height:1.5;white-space:pre-wrap}.routespro-dashboard .ffad-ask-points{display:grid;gap:5px;margin:0;padding-left:18px;color:#334155}.routespro-dashboard .ffad-ask-evidence{display:flex;gap:6px;flex-wrap:wrap}.routespro-dashboard .ffad-ask-evidence span{display:inline-flex;align-items:center;gap:4px;padding:5px 7px;border-radius:999px;background:#eff6ff;color:#1e40af;font-size:10px;font-weight:750}.routespro-dashboard .ffad-ask-evidence span.is-projected{background:#fef3c7;color:#92400e}.routespro-dashboard .ffad-ask-evidence span.is-learned{background:#f3e8ff;color:#7e22ce}.routespro-dashboard .ffad-ask-evidence span.is-advisory{background:#dcfce7;color:#166534}.routespro-dashboard .ffad-ask-evidence b{font-weight:900}.routespro-dashboard .ffad-ask-meta{display:flex;gap:6px;flex-wrap:wrap;color:#64748b;font-size:10px}.routespro-dashboard .ffad-ask-meta span{display:inline-flex;padding:4px 7px;border:1px solid #e2e8f0;border-radius:999px;background:#fff}.routespro-dashboard .ffad-ask-followups{display:flex;gap:6px;flex-wrap:wrap}.routespro-dashboard .ffad-ask-followups button{padding:5px 8px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#334155;font-size:10px;cursor:pointer}.routespro-dashboard .ffad-ask-form{display:grid;gap:8px;margin-top:10px}.routespro-dashboard .ffad-ask-form textarea{width:100%;min-height:72px;resize:vertical;border:1px solid #cbd5e1;border-radius:13px;padding:10px 12px;background:#fff;color:#0f172a}.routespro-dashboard .ffad-ask-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.routespro-dashboard .ffad-ask-actions small{margin-right:auto;color:#64748b}.routespro-dashboard .ffad-ask-loading{display:flex;gap:8px;align-items:center;color:#64748b}.routespro-dashboard .ffad-ask-loading:before{content:"";width:12px;height:12px;border:2px solid #94a3b8;border-right-color:transparent;border-radius:50%;animation:ffad-ask-spin .75s linear infinite}@keyframes ffad-ask-spin{to{transform:rotate(360deg)}}
@media(max-width:1180px){.routespro-dashboard .ffad-score-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:760px){.routespro-dashboard .ffad-score-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.routespro-dashboard .ffad-priority-list{grid-template-columns:1fr}.routespro-dashboard .ffad-ask-message{max-width:100%}.routespro-dashboard .ffad-ask-actions small{width:100%;margin-right:0}}
@media(max-width:460px){.routespro-dashboard .ffad-score-grid{grid-template-columns:1fr}.routespro-dashboard .ffad-priority-card{grid-template-columns:auto minmax(0,1fr)}.routespro-dashboard .ffad-priority-score{grid-column:2;justify-self:start}}

/* 2.2.472, mobile Analytics full-width and uninterrupted vertical reading. */
@media(max-width:760px){
  .routespro-dashboard,.routespro-dashboard .ffad-shell,.routespro-dashboard .ffad-custom-dashboards,.routespro-dashboard .ffad-custom-dashboard,.routespro-dashboard .ffad-custom-grid,.routespro-dashboard .ffad-metric,.routespro-dashboard .ffad-card,.routespro-dashboard .ffad-ai,.routespro-dashboard .ffad-command-view,.routespro-dashboard .ffad-priority-surface,.routespro-dashboard .ffad-ask-fieldflow{width:100%!important;max-width:none!important;min-width:0!important}
  .routespro-dashboard{overflow:visible!important}
  .routespro-dashboard .ffad-shell{gap:12px!important;overflow:visible!important}
  .routespro-dashboard .ffad-head{padding:16px 14px!important;border-radius:18px!important}
  .routespro-dashboard .ffad-filters{grid-template-columns:1fr!important;padding:10px!important;border-radius:16px!important}
  .routespro-dashboard .ffad-reportbar{padding:12px!important;border-radius:15px!important}
  .routespro-dashboard .ffad-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important}
  .routespro-dashboard .ffad-kpi{min-height:112px!important;padding:13px!important;border-radius:15px!important}
  .routespro-dashboard .ffad-custom-dashboard{border-radius:18px!important}
  .routespro-dashboard .ffad-custom-head{padding:14px 12px!important}
  .routespro-dashboard .ffad-custom-grid{grid-template-columns:1fr!important;padding:8px 6px 10px!important;gap:10px!important}
  .routespro-dashboard .ffad-metric{grid-column:1!important;border-radius:15px!important}
  .routespro-dashboard .ffad-metric-label{padding:14px 12px 12px!important}
  .routespro-dashboard .ffad-metric-body{padding:12px!important;gap:10px!important}
  .routespro-dashboard .ffad-card,.routespro-dashboard .ffad-ai,.routespro-dashboard .ffad-command-view,.routespro-dashboard .ffad-priority-surface,.routespro-dashboard .ffad-ask-fieldflow{padding:13px!important;border-radius:17px!important}
  .routespro-dashboard .ffad-ai-grid,.routespro-dashboard .ffad-priority-list{grid-template-columns:1fr!important}
  .routespro-dashboard .ffad-score-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:8px!important}
  .routespro-dashboard .ffad-chart{width:100%!important;max-width:100%!important;min-height:0!important;overflow-x:auto!important;overflow-y:hidden!important;-webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;overscroll-behavior-y:auto;touch-action:pan-x pan-y}
  .routespro-dashboard .ffad-chart svg{min-width:560px!important;height:230px!important}
  .routespro-dashboard .ffad-ask-thread{max-height:none!important;overflow:visible!important}
  .routespro-dashboard .ffad-analytics-table{max-height:none!important;overflow:visible!important;scrollbar-gutter:auto!important}
  .routespro-dashboard .ffad-evidence-tabs{overflow-x:auto!important;overflow-y:hidden!important;-webkit-overflow-scrolling:touch;overscroll-behavior-y:auto}
  .routespro-dashboard .ffad-evidence-thumbs{grid-template-columns:repeat(2,minmax(0,1fr))!important;padding:8px!important}
  .routespro-dashboard .ffad-widget-detail,.routespro-dashboard .ffad-widget-detail-body,.routespro-dashboard .ffad-evidence-shell,.routespro-dashboard .ffad-evidence-panel{overflow:visible!important;max-height:none!important}
}
@media(max-width:430px){
  .routespro-dashboard .ffad-kpis,.routespro-dashboard .ffad-score-grid{grid-template-columns:1fr!important}
  .routespro-dashboard .ffad-chart svg{min-width:520px!important}
}


/* 2.2.591, Mobile Analytics Readability & Evidence Density 9BT.
 * Contract: fieldflow-mobile-analytics-readability-evidence-density-v1 */
.routespro-dashboard .ffad-performance-focus,.routespro-dashboard .ffad-performance-focus :is(span,h6,strong,p,small){color:#fff!important}
.routespro-dashboard .ffad-performance-focus p{color:rgba(255,255,255,.88)!important}
.routespro-dashboard .ffad-evidence-tab.is-active{color:#fff!important}
.routespro-dashboard .ffad-evidence-section{overflow:hidden;border:1px solid var(--ff-border,#dbe5f0);border-radius:16px;background:var(--ff-surface,#fff)}
.routespro-dashboard .ffad-evidence-section>summary{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:10px;padding:12px 13px;cursor:pointer;list-style:none;background:color-mix(in srgb,var(--ff-canvas,#f8fafc) 58%,var(--ff-surface,#fff));color:var(--ff-heading,#0f172a)}
.routespro-dashboard .ffad-evidence-section>summary::-webkit-details-marker{display:none}.routespro-dashboard .ffad-evidence-section>summary span{display:block;color:var(--ff-label,#2563eb);font-size:9px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.routespro-dashboard .ffad-evidence-section>summary strong{display:block;margin-top:2px;font-size:13px;line-height:1.3;color:var(--ff-heading,#0f172a)!important}.routespro-dashboard .ffad-evidence-section>summary em{font-size:10px;font-style:normal;font-weight:800;color:var(--ff-muted,#64748b);white-space:nowrap}.routespro-dashboard .ffad-evidence-section>summary:after{content:'+';display:grid;place-items:center;width:28px;height:28px;border:1px solid var(--ff-border,#dbe5f0);border-radius:999px;background:var(--ff-surface,#fff);color:var(--ff-heading,#0f172a);font-size:17px;line-height:1}.routespro-dashboard .ffad-evidence-section[open]>summary:after{content:'−'}.routespro-dashboard .ffad-evidence-section-body{padding:10px}
.routespro-dashboard .ffad-evidence-store-copy strong,.routespro-dashboard .ffad-evidence-date-copy strong,.routespro-dashboard .ffad-evidence-date-first-store-head strong{white-space:normal!important;overflow:visible!important;text-overflow:clip!important;overflow-wrap:anywhere!important;word-break:normal!important;line-height:1.28!important}
@media(max-width:760px){
  #rp-app-analytics{width:100vw!important;max-width:100vw!important;margin-left:calc(50% - 50vw)!important;margin-right:calc(50% - 50vw)!important;padding:0 6px calc(194px + env(safe-area-inset-bottom,0px))!important;scroll-padding-bottom:calc(206px + env(safe-area-inset-bottom,0px))!important}
  #rp-app-analytics>.rp-premium-card{width:100%!important;max-width:none!important;margin:0!important;padding:0!important;border-left:0!important;border-right:0!important;border-radius:0!important;box-shadow:none!important}
  #rp-app-analytics .routespro-dashboard{width:100%!important;max-width:none!important;margin:0!important;padding:0!important}
  #rp-app-analytics .routespro-dashboard .ffad-shell{padding:0!important;gap:9px!important}
  #rp-app-analytics .routespro-dashboard :is(.ffad-head,.ffad-filters,.ffad-card,.ffad-ai,.ffad-command-view,.ffad-priority-surface,.ffad-ask-fieldflow,.ffad-audience-performance,.ffad-custom-dashboard){width:100%!important;max-width:none!important;margin-left:0!important;margin-right:0!important;border-radius:16px!important}
  #rp-app-analytics .routespro-dashboard :is(.ffad-card,.ffad-ai,.ffad-command-view,.ffad-priority-surface,.ffad-ask-fieldflow,.ffad-audience-performance){padding:11px!important}
  #rp-app-analytics .routespro-dashboard .ffad-custom-grid{padding:6px!important;gap:8px!important}
  #rp-app-analytics .routespro-dashboard .ffad-metric{border-radius:14px!important}
  #rp-app-analytics .routespro-dashboard .ffad-metric-label{padding:12px 10px!important}
  #rp-app-analytics .routespro-dashboard .ffad-metric-body{padding:9px!important}
  #rp-app-analytics .routespro-dashboard .ffad-evidence-section-body{padding:7px!important}
  #rp-app-analytics .routespro-dashboard .ffad-evidence-store-head,#rp-app-analytics .routespro-dashboard .ffad-evidence-date-first-head,#rp-app-analytics .routespro-dashboard .ffad-evidence-date-head{align-items:center!important;flex-direction:row!important}
  #rp-app-analytics .routespro-dashboard .ffad-evidence-action{width:100%!important;min-height:40px!important}
  #rp-app-analytics .routespro-dashboard .ffad-evidence-thumbs{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:7px!important;padding:8px!important}
  #rp-app-analytics .routespro-dashboard .ffad-evidence-thumb img{height:108px!important}
  #rp-app-analytics .routespro-dashboard .ffad-ask-form textarea{height:50px!important;min-height:50px!important;max-height:96px!important;resize:vertical!important;padding:10px 11px!important;line-height:1.35!important}
  #rp-app-analytics .routespro-dashboard .ffad-ask-thread{margin-top:8px!important;padding:9px!important}
  #rp-app-analytics .routespro-dashboard .ffad-performance-focus{padding:14px!important}
  #rp-app-analytics .routespro-dashboard .ffad-performance-focus h6{font-size:19px!important}
  #rp-app-analytics .routespro-dashboard .ffad-performance-focus strong{font-size:29px!important}
  #rp-app-analytics .routespro-dashboard .ffad-performance-row div:first-of-type strong{white-space:normal!important;overflow:visible!important;text-overflow:clip!important;line-height:1.25!important}
}

</style>

<div class="routespro-dashboard <?php echo esc_attr($dashboard_classes); ?>" style="<?php echo esc_attr($dashboard_style); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-export-nonce="<?php echo esc_attr($export_nonce); ?>" data-client-id="<?php echo esc_attr($preset_client_id); ?>" data-project-id="<?php echo esc_attr($preset_project_id); ?>" data-self-mode="<?php echo $self_mode ? '1' : '0'; ?>" data-user-id="<?php echo esc_attr($forced_user_id); ?>" data-audience="<?php echo esc_attr($dashboard_audience); ?>">
  <div class="ffad-shell">
    <div class="ffad-head">
      <div><h4><?php echo esc_html($title); ?></h4><p><?php echo esc_html($self_mode ? 'Execução, produtividade e evolução calculadas apenas com as rotas e reportes do utilizador autenticado.' : 'Visão consolidada de execução, produtividade e analytics de formulários no âmbito selecionado.'); ?></p></div>
      <span class="ffad-scope"><?php echo esc_html($self_mode ? $current_user_label : 'Visão de equipa'); ?></span>
    </div>
    <div class="ffad-filters">
      <label class="ffad-filter"><span>Projeto</span><select data-role="project"><option value="">Todos os projetos</option></select></label>
      <label class="ffad-filter <?php echo $self_mode ? 'is-hidden' : ''; ?>"><span>Função</span><select data-role="role"><option value="">Todas as funções</option></select></label>
      <label class="ffad-filter <?php echo $self_mode ? 'is-hidden' : ''; ?>"><span>Operativo</span><select data-role="user"><option value="">Todos os operativos</option></select></label>
      <label class="ffad-filter"><span>De</span><input type="date" data-role="from"></label>
      <label class="ffad-filter"><span>Até</span><input type="date" data-role="to"></label>
      <div class="ffad-reportbar"><div class="ffad-export-copy"><span>Relatório semanal</span><strong data-role="report-period">Período selecionado</strong><small>O Excel usa sempre as datas De e Até acima. Os atalhos apenas alteram o período.</small></div><div class="ffad-report-actions"><div class="ffad-presets"><button type="button" class="ffad-preset" data-period-preset="current_week">Esta semana</button><button type="button" class="ffad-preset" data-period-preset="previous_week">Semana anterior</button><button type="button" class="ffad-preset" data-period-preset="month">Este mês</button></div><div class="ffad-actions"><a data-role="export-csv" class="ffad-btn primary" href="#" rel="noopener">CSV dados</a><a data-role="export-xls" class="ffad-btn" href="#" rel="noopener">Excel completo</a><a data-role="export-xls-mobile" class="ffad-btn" href="#" rel="noopener">Excel mobile</a><a data-role="export-pdf" class="ffad-btn alt" href="#" rel="noopener">PDF executivo</a></div></div></div>
    </div>
    <div class="ffad-kpis" data-role="stats"></div>
    <section class="ffad-audience-performance"><div class="ffad-performance-head"><div><h5><?php echo esc_html($self_mode ? 'A minha performance' : 'Performance da equipa'); ?></h5><p><?php echo esc_html($self_mode ? 'Execução e reportes apenas no teu âmbito pessoal.' : 'Comparação transparente por elemento quando a vista está em equipa. Ao escolher um operativo, a leitura torna-se individual.'); ?></p></div><span class="ffad-parity-badge"><?php echo esc_html($self_mode ? 'Pessoal' : 'Equipa / individual'); ?></span></div><div data-role="audience-performance"><div class="ffad-empty">A preparar leitura de performance...</div></div></section>
    <section class="ffad-command-view"><div class="ffad-command-head"><div><h5><?php echo esc_html($self_mode ? 'Command View pessoal' : 'Command View'); ?></h5><p><?php echo esc_html($self_mode ? 'Os seis sinais de decisão são calculados apenas com as tuas rotas, lojas e reportes.' : 'Os seis sinais de decisão sintetizam execução, risco, oportunidade, stock e confiança no âmbito selecionado.'); ?></p></div><span class="ffad-parity-badge">Analytics 2.0 · Front</span></div><div class="ffad-score-grid" data-role="decision-scores"><div class="ffad-empty">A preparar os sinais de decisão...</div></div></section>
    <section class="ffad-priority-surface" data-role="priority-actions" hidden><div class="ffad-priority-head"><div><h5><?php echo esc_html($self_mode ? 'Prioridades de hoje / próximo ciclo' : 'Prioridades operacionais do próximo ciclo'); ?></h5><p><?php echo esc_html($self_mode ? 'Ações explicáveis apenas para as lojas do teu âmbito. Observado e projetado permanecem separados.' : 'Next Best Actions explicáveis por loja, produto e problema no âmbito selecionado.'); ?></p></div><span class="ffad-parity-badge">Next Best Action</span></div><div class="ffad-priority-list" data-role="priority-list"></div></section>
    <section class="ffad-ai"><div class="ffad-ai-head"><div><h5><?php echo esc_html($self_mode ? 'Copiloto operacional' : 'Análise inteligente'); ?></h5><p><?php echo esc_html($self_mode ? 'Prioridades pessoais calculadas a partir das tuas rotas e reportes.' : 'Leitura automática das métricas reais do período, com riscos e ações recomendadas.'); ?></p></div><button type="button" class="ffad-btn ffad-ai-refresh" data-role="ai-refresh">Atualizar análise</button></div><div class="ffad-ai-body" data-role="ai-insights"><div class="ffad-empty">A preparar análise inteligente...</div></div></section>
    <section class="ffad-ask-fieldflow" data-role="ask-fieldflow"><div class="ffad-ask-head"><div><h5>Perguntar ao FieldFlow</h5><p><?php echo esc_html($self_mode ? 'Pergunta pelas tuas prioridades, riscos, lojas, produtos ou evolução. O teu âmbito pessoal é imposto no servidor.' : 'Faz perguntas sobre o âmbito e período selecionados. As respostas usam apenas evidência FieldFlow.'); ?></p></div><span class="ffad-parity-badge">Grounded · Read-only</span></div><div class="ffad-ask-suggestions"><?php if ($self_mode): ?><button type="button" data-ffad-ask-question="Como está a minha performance neste período?">A minha performance</button><button type="button" data-ffad-ask-question="O que devo fazer hoje?">O que fazer hoje?</button><button type="button" data-ffad-ask-question="Que lojas minhas estão em risco?">Lojas em risco</button><button type="button" data-ffad-ask-question="Onde estou a perder Share Space?">Share Space</button><button type="button" data-ffad-ask-question="Qual é a minha prioridade seguinte?">Próxima prioridade</button><button type="button" data-ffad-ask-question="Como funciona o FieldFlow?">Como funciona?</button><?php else: ?><button type="button" data-ffad-ask-question="Quem tem a melhor performance da equipa neste período?">Melhor performance</button><button type="button" data-ffad-ask-question="Quais são as lojas que devo priorizar a seguir?">Lojas a priorizar</button><button type="button" data-ffad-ask-question="O que está previsto para hoje e para os próximos dias com rota?">Rotas próximas</button><button type="button" data-ffad-ask-question="Que riscos exigem acompanhamento?">Riscos</button><button type="button" data-ffad-ask-question="O que devemos fazer a seguir?">Próximas ações</button><button type="button" data-ffad-ask-question="Como funciona o FieldFlow?">Como funciona?</button><?php endif; ?></div><div class="ffad-ask-thread" data-role="ask-thread"><div class="ffad-ask-empty"><strong>Pergunta pelo que interessa.</strong><span><?php echo esc_html($self_mode ? 'Só são usados dados das tuas lojas e rotas. A conversa não altera a operação.' : 'A conversa respeita o âmbito atual e não altera dados operacionais.'); ?></span></div></div><form class="ffad-ask-form" data-role="ask-form"><textarea data-role="ask-input" rows="2" maxlength="700" placeholder="<?php echo esc_attr($self_mode ? 'Ex.: Que lojas devo priorizar amanhã e porquê?' : 'Ex.: Que lojas estão a deteriorar-se e qual é a ação recomendada?'); ?>"></textarea><div class="ffad-ask-actions"><small>O contexto atual é aplicado automaticamente.</small><button type="button" class="ffad-btn" data-role="ask-clear">Limpar</button><button type="submit" class="ffad-btn primary" data-role="ask-submit">Perguntar</button></div></form></section>
    <div class="ffad-custom-dashboards" data-role="custom-analytics"></div>
    <div class="ffad-main">
      <section class="ffad-card"><h5>Execução no período</h5><p>Percentagem de paragens concluídas por rota e por dia.</p><div class="ffad-chart" data-role="chart"></div></section>
      <aside class="ffad-card"><h5>Leitura rápida</h5><p>Comparação com o período anterior e destaques operacionais.</p><div class="ffad-insights" data-role="insights"></div></aside>
    </div>
    <section class="ffad-card"><h5>Rotas e execução</h5><p data-role="table-note">Detalhe das rotas no período selecionado.</p><div class="ffad-mobile-list" data-role="mobile-list"></div><div class="ffad-table-wrap ffad-route-table-wrap"><table><thead><tr><th>Data</th><th>Projeto</th><th>Operativo</th><th>Rota</th><th>Estado</th><th>Paragens</th><th>Realizadas</th><th>Execução</th><th>Fecho</th><th>Percurso</th><th>Combustível / energia</th><th>Custo deslocação</th></tr></thead><tbody data-role="table-body"></tbody></table></div></section>
  </div>
</div>

<script>
(function(){
  const box=document.currentScript.previousElementSibling; if(!box||!box.classList.contains('routespro-dashboard')) return;
  const nonce=box.dataset.nonce||'', exportNonce=box.dataset.exportNonce||'', presetClientId=box.dataset.clientId||'', presetProjectId=box.dataset.projectId||'';
  const selfMode=box.dataset.selfMode==='1', forcedUserId=box.dataset.userId||'', audience=box.dataset.audience||(selfMode?'merchandiser':'manager');
  const api='<?php echo esc_url(rest_url('routespro/v1/')); ?>', ajax='<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
  const q=r=>box.querySelector('[data-role="'+r+'"]');
  const els={project:q('project'),role:q('role'),user:q('user'),from:q('from'),to:q('to'),stats:q('stats'),audiencePerformance:q('audience-performance'),chart:q('chart'),insights:q('insights'),ai:q('ai-insights'),aiRefresh:q('ai-refresh'),decisionScores:q('decision-scores'),priorityActions:q('priority-actions'),priorityList:q('priority-list'),askBox:q('ask-fieldflow'),askThread:q('ask-thread'),askForm:q('ask-form'),askInput:q('ask-input'),askSubmit:q('ask-submit'),askClear:q('ask-clear'),customAnalytics:q('custom-analytics'),table:q('table-body'),mobile:q('mobile-list'),note:q('table-note'),reportPeriod:q('report-period'),csv:q('export-csv'),xls:q('export-xls'),xlsMobile:q('export-xls-mobile'),pdf:q('export-pdf')};
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const analyticsUi={pages:{},sizes:{},tabs:{}}; let currentAnalyticsData=null, analyticsEvidenceGroups={}, analyticsEvidenceSeq=0;
  function analyticsDefaultPageSize(){const raw=parseInt(getComputedStyle(box).getPropertyValue('--ff-analytics-rows-per-page'),10);return Math.max(5,Math.min(30,raw||10));}
  function analyticsPageOptions(selected){const values=[5,10,15,20,25,30],current=parseInt(selected||analyticsDefaultPageSize(),10)||10;return values.map(v=>`<option value="${v}" ${v===current?'selected':''}>${v} linhas</option>`).join('');}
  function analyticsMetricKey(metric){return String(metric?.binding_id||metric?.metric_key||metric?.question_key||'metric').replace(/[^a-z0-9_-]/gi,'-');}
  function frontsRowKind(row){const kind=String(row?.growth_kind||'').toLowerCase(),diff=parseFloat(row?.difference??((parseFloat(row?.after||0)||0)-(parseFloat(row?.before||0)||0)))||0;if(['up','new','growth','increase'].includes(kind)||diff>0)return'up';if(['down','removed','reduction','decrease'].includes(kind)||diff<0)return'down';return'same';}
  function analyticsPager(key,page,total){if(total<=1)return'';const start=Math.max(1,Math.min(total-4,page-2)),end=Math.min(total,start+4);let html=`<button type="button" data-ffad-page="${Math.max(1,page-1)}" ${page<=1?'disabled':''}>Anterior</button>`;for(let i=start;i<=end;i++)html+=`<button type="button" data-ffad-page="${i}" class="${i===page?'is-active':''}">${i}</button>`;html+=`<button type="button" data-ffad-page="${Math.min(total,page+1)}" ${page>=total?'disabled':''}>Seguinte</button>`;return `<div class="ffad-pagination" data-ffad-page-key="${esc(key)}">${html}</div>`;}
  const today=new Date(); const iso=d=>{ const y=d.getFullYear(),m=String(d.getMonth()+1).padStart(2,'0'),day=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${day}`; };
  const start=new Date(today.getFullYear(),today.getMonth(),1); els.from.value=iso(start); els.to.value=iso(today);
  async function safeFetch(url){ try{ const r=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce}}); if(!r.ok) throw new Error('Pedido falhou'); return await r.json(); }catch(e){ return null; } }
  function statusBadge(status){ const raw=String(status||'planned'),key=raw.toLowerCase().replace(/[^a-z0-9_]/g,'_'),labels={completed:'Concluída',done:'Concluída',in_progress:'Em curso',pending:'Pendente',planned:'Planeada',draft:'Rascunho',cancelled:'Cancelada',failed:'Falhada'}; return `<span class="ffad-status ${esc(key)}">${esc(labels[key]||raw)}</span>`; }
  function kpi(label,value,note,progress){ const pct=Math.max(0,Math.min(100,parseFloat(progress||0)||0)); return `<div class="ffad-kpi"><span>${esc(label)}</span><strong>${esc(value)}</strong><small>${esc(note||'')}</small>${progress!==undefined?`<div class="ffad-progress"><i style="width:${pct}%"></i></div>`:''}</div>`; }
  function delta(current,previous,suffix=''){ const d=+((parseFloat(current||0)||0)-(parseFloat(previous||0)||0)).toFixed(1); return d>0?`+${d}${suffix}`:`${d}${suffix}`; }
  const fmtEur=value=>Number(value||0).toLocaleString('pt-PT',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';
  /* fieldflow-analytics-header-date-export-authority-v1 */
  function analyticsHeaderDateScope(){const from=String(els.from?.value||'').trim(),to=String(els.to?.value||'').trim();return {from,to,explicit:!!(from||to)};}
  function params(){ const p=new URLSearchParams(); if(presetClientId)p.set('client_id',presetClientId); if(els.project.value)p.set('project_id',els.project.value); else if(presetProjectId)p.set('project_id',presetProjectId); if(els.role.value&&!selfMode)p.set('role',els.role.value); if(selfMode)p.set('user_id','me'); else if(els.user.value)p.set('user_id',els.user.value); const dateScope=analyticsHeaderDateScope(); if(dateScope.from)p.set('from',dateScope.from); if(dateScope.to)p.set('to',dateScope.to); p.set('analytics_audience',audience); return p; }
  function syncAnalyticsExportDateAuthority(){refreshExportLinks();const scope=analyticsHeaderDateScope();try{box.dispatchEvent(new CustomEvent('fieldflow:analytics-date-scope-change',{bubbles:true,detail:{portal:selfMode?'operative':'manager',from:scope.from,to:scope.to,source:'header'}}));}catch(_){}}
  async function loadProjects(){ const p=new URLSearchParams({action:'routespro_projects_for_user'}); if(presetClientId)p.set('client_id',presetClientId); const data=await safeFetch(ajax+'?'+p.toString())||[]; els.project.innerHTML='<option value="">Todos os projetos</option>'+data.map(x=>`<option value="${esc(x.id)}">${esc(x.name)}</option>`).join(''); if(presetProjectId&&Array.from(els.project.options).some(o=>o.value===String(presetProjectId)))els.project.value=String(presetProjectId); }
  async function loadRoles(){ if(selfMode)return; const p=new URLSearchParams({action:'routespro_roles'}); if(presetClientId)p.set('client_id',presetClientId); if(els.project.value)p.set('project_id',els.project.value); const data=await safeFetch(ajax+'?'+p.toString())||[]; const old=els.role.value||''; els.role.innerHTML='<option value="">Todas as funções</option>'+data.map(x=>`<option value="${esc(x.id)}">${esc(x.name||x.id)}</option>`).join(''); if(old&&Array.from(els.role.options).some(o=>o.value===old))els.role.value=old; }
  async function loadUsers(){ if(selfMode){ els.user.innerHTML=`<option value="${esc(forcedUserId)}"><?php echo esc_js($current_user_label); ?></option>`; els.user.value=forcedUserId; return; } const p=new URLSearchParams({action:'routespro_users'}); if(presetClientId)p.set('client_id',presetClientId); if(els.project.value)p.set('project_id',els.project.value); if(els.role.value)p.set('role',els.role.value); const data=await safeFetch(ajax+'?'+p.toString())||[]; const old=els.user.value||''; els.user.innerHTML='<option value="">Todos os operativos</option>'+data.map(u=>`<option value="${esc(u.ID)}">${esc(u.displayName||u.username||('#'+u.ID))}</option>`).join(''); if(old&&Array.from(els.user.options).some(o=>o.value===old))els.user.value=old; }
  function aiItems(items,kind,empty){const rows=Array.isArray(items)?items:[];return `<div class="ffad-ai-group ${kind}"><h6>${kind==='highlights'?'Destaques':(kind==='risks'?'Riscos':'Próximas ações')}</h6>${rows.length?rows.map(x=>`<div class="ffad-ai-item"><strong><i class="ffad-ai-dot"></i>${esc(x.title||'')}</strong><small>${esc(x.detail||'')}</small></div>`).join(''):`<div class="ffad-ai-item"><small>${esc(empty)}</small></div>`}</div>`;}
  function intelValue(v,format){const n=Number(v||0);return format==='percent'?new Intl.NumberFormat('pt-PT',{maximumFractionDigits:1}).format(n)+'%':new Intl.NumberFormat('pt-PT',{maximumFractionDigits:1}).format(n);}
  function intelHtml(data){const ex=data?.executive||{},cmp=Array.isArray(data?.comparison?.metrics)?data.comparison.metrics:[],an=Array.isArray(data?.anomalies)?data.anomalies:[],acts=Array.isArray(data?.controlled_actions)?data.controlled_actions:[],recs=Array.isArray(data?.recommendations)?data.recommendations:[],stores=Array.isArray(data?.store_intelligence?.rankings?.priority)?data.store_intelligence.rankings.priority:[],pred=data?.predictive_intelligence||{},ctx=data?.contextual_predictive_intelligence||data?.store_intelligence?.contextual_predictive||{},ctxActions=Array.isArray(ctx?.next_best_actions)?ctx.next_best_actions:[],src=Array.isArray(data?.sources)?data.sources:[],conf=data?.confidence_detail||{},score=Math.max(0,Math.min(100,Number(ex.score||0))),delta=Number(ex.execution_delta_pp||0);return `<div class="ff-intel-executive"><div class="ff-intel-score is-${esc(ex.status||'watch')}"><span class="ff-intel-score-ring" style="--score:${score}"><strong>${score}</strong></span><div class="ff-intel-score-copy"><span>Índice operacional</span><strong>${esc(ex.label||'Operação em análise')}</strong><small>${esc(ex.open_anomalies||0)} alerta(s) ativo(s)</small></div></div><div class="ff-intel-kpis"><div class="ff-intel-kpi"><span>Execução</span><strong>${intelValue(ex.done_rate,'percent')}</strong><small>Período atual</small></div><div class="ff-intel-kpi"><span>Variação</span><strong>${delta>0?'+':''}${intelValue(delta,'')} pp</strong><small>Período anterior</small></div><div class="ff-intel-kpi"><span>Pendentes</span><strong>${esc(ex.pending_stops||0)}</strong><small>Paragens</small></div><div class="ff-intel-kpi"><span>Risco elevado</span><strong>${esc(ex.high_anomalies||0)}</strong><small>Alertas prioritários</small></div></div></div>${cmp.length?`<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Comparação temporal</h5><p>Leitura homogénea face ao intervalo anterior.</p></div><em>${esc(data?.comparison?.previous_period?.from||'')} a ${esc(data?.comparison?.previous_period?.to||'')}</em></div><div class="ff-intel-comparison">${cmp.slice(0,6).map(m=>`<div class="ff-intel-compare"><span>${esc(m.label||m.key)}</span><strong>${intelValue(m.current,m.format)}</strong><b class="ff-intel-delta ${esc(m.direction||'stable')}">${Number(m.delta||0)>0?'+':''}${intelValue(m.delta,m.format)}</b><span>Anterior ${intelValue(m.previous,m.format)}</span></div>`).join('')}</div></div>` :''}${pred.available?`<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Predictive Intelligence</h5><p>Projeção determinística da próxima janela, não uma probabilidade.</p></div><em>${esc(pred?.horizon?.days||0)} dias</em></div><div class="ff-intel-comparison"><div class="ff-intel-compare"><span>Execução projetada</span><strong>${intelValue(pred?.forecasts?.execution?.forecast||0,'percent')}</strong><b class="ff-intel-delta ${esc(pred?.forecasts?.execution?.direction||'stable')}">${Number(pred?.forecasts?.execution?.delta||0)>0?'+':''}${intelValue(pred?.forecasts?.execution?.delta||0,'')} pp</b><span>Atual ${intelValue(pred?.forecasts?.execution?.current||0,'percent')}</span></div><div class="ff-intel-compare"><span>Risco projetado</span><strong>${intelValue(pred?.forecasts?.risk?.forecast||0,'')}/100</strong><b class="ff-intel-delta ${esc(pred?.forecasts?.risk?.direction||'stable')}">${Number(pred?.forecasts?.risk?.delta||0)>0?'+':''}${intelValue(pred?.forecasts?.risk?.delta||0,'')}</b><span>Confiança ${intelValue(pred?.summary?.confidence||0,'')}/100</span></div><div class="ff-intel-compare"><span>Oportunidade projetada</span><strong>${intelValue(pred?.forecasts?.opportunity?.forecast||0,'')}/100</strong><b class="ff-intel-delta ${esc(pred?.forecasts?.opportunity?.direction||'stable')}">${Number(pred?.forecasts?.opportunity?.delta||0)>0?'+':''}${intelValue(pred?.forecasts?.opportunity?.delta||0,'')}</b><span>${esc(pred?.state||'stable')}</span></div></div></div>`:''}${ctxActions.length?`<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Next Best Actions</h5><p>Prioridades assistidas por loja, produto e problema. Evidência observada e projeções mantêm-se separadas.</p></div><em>${ctxActions.length}</em></div><div class="ff-intel-anomalies">${ctxActions.slice(0,6).map(a=>{const ev=Array.isArray(a.evidence)?a.evidence:[];const observed=ev.filter(e=>String(e?.kind||'')==='observed').slice(0,2).map(e=>`${esc(e.label||'Sinal')}: ${esc(e.value||'')}`).join(' · ');const projected=ev.filter(e=>String(e?.kind||'')==='projected').slice(0,2).map(e=>`${esc(e.label||'Projeção')}: ${esc(e.value||'')}`).join(' · ');return `<div class="ff-intel-anomaly ${esc(a.urgency||'medium')}"><i class="ff-intel-anomaly-dot"></i><div><strong>${esc(a.location_name||'PDV')}${a.product?` · ${esc(a.product)}`:''}</strong><small>${esc(a.issue_label||a.issue_type||'Acompanhamento')} · ${esc(a.action||'Rever na próxima intervenção.')}${observed?`<br>Observado: ${observed}`:''}${projected?`<br>Projetado: ${projected}`:''}</small></div><b>${esc(Math.round(Number(a.rank_score||0)))}/100</b></div>`}).join('')}</div><div class="ff-intel-policy">Estas ações são sugestões assistidas. Não alteram rotas, stock, objetivos ou dados operacionais automaticamente.</div></div>`:''}<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Anomalias detetadas</h5><p>Sinais calculados, não causas presumidas.</p></div><em>${an.length}</em></div><div class="ff-intel-anomalies">${an.length?an.slice(0,8).map(a=>`<div class="ff-intel-anomaly ${esc(a.severity||'medium')}"><i class="ff-intel-anomaly-dot"></i><div><strong>${esc(a.title||'Alerta')}</strong><small>${esc(a.detail||'')}</small></div><b>${esc(a.severity||'medium')}</b></div>`).join(''):'<div class="ffad-empty">Não foram detetadas anomalias relevantes.</div>'}</div></div>${stores.length?`<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Store Intelligence</h5><p>Prioridade por loja com risco, oportunidade e confiança explicáveis.</p></div><em>${stores.length}</em></div><div class="ff-intel-anomalies">${stores.slice(0,5).map(r=>`<div class="ff-intel-anomaly ${esc(r.severity||'medium')}"><i class="ff-intel-anomaly-dot"></i><div><strong>${esc(r.location_name||'PDV')}</strong><small>Risco ${esc(Math.round(Number(r.risk_score||0)))} · Oportunidade ${esc(Math.round(Number(r.opportunity_score||0)))} · Execução ${esc(Math.round(Number(r.execution_score||r.done_rate||0)))}${r.stock_risk==null?'':` · Stock ${esc(Math.round(Number(r.stock_risk)))}`} · ${esc(r.reason||'')}</small></div><b>${esc(Math.round(Number(r.adaptive_rank_score!=null?r.adaptive_rank_score:r.priority_score||0)))}/100</b></div>`).join('')}</div></div>`:''}${recs.length?`<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Recomendações Intelligence</h5><p>Prioridades explicáveis. O feedback é tratado no Intelligence Center.</p></div><em>${recs.length}</em></div><div class="ff-intel-anomalies">${recs.slice(0,6).map(r=>`<div class="ff-intel-anomaly ${esc(r.severity||'medium')}"><i class="ff-intel-anomaly-dot"></i><div><strong>${esc(r.title||'Recomendação')}</strong><small>${esc(r.suggested_action||r.detail||'')}</small></div><b>${esc(Math.round(Number(r.adaptive_confidence_score!=null?r.adaptive_confidence_score:r.confidence_score||0)))}%</b></div>`).join('')}</div></div>`:''}${acts.length?`<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Ações controladas</h5><p>Orientações para revisão, sem mutações automáticas.</p></div></div><div class="ff-intel-actions">${acts.map(a=>`<button type="button" class="ff-intel-action" data-ff-intel-action="${esc(a.id||'')}" data-ff-intel-target="${esc(a.target||'analytics')}" data-ff-intel-kind="${esc(a.kind||'filter')}"><strong>${esc(a.title||'Ação')}</strong><small>${esc(a.detail||'')}</small><span>${esc(a.priority||'next')}</span></button>`).join('')}</div></div>`:''}<div class="ff-intel-section"><div class="ff-intel-section-head"><div><h5>Fontes e confiança</h5><p>${esc(conf.explanation||'Leitura baseada nos dados disponíveis.')}</p></div><em>${esc(conf.score||0)}/100</em></div><div class="ff-intel-sources">${src.map(x=>`<span class="ff-intel-source ${x.available?'available':''}" title="${esc(x.detail||'')}">${esc(x.label||x.id)} · ${esc(x.records||0)}</span>`).join('')}</div><div class="ff-intel-confidence"><span>Confiança ${esc(conf.label||data.confidence||'n.d.')}</span><meter min="0" max="100" value="${esc(conf.score||0)}"></meter></div><div class="ff-intel-policy">A análise nunca publica, corrige ou elimina dados sem revisão humana.</div></div>`;}
  function openIntelTarget(target){const shell=box.closest('#routespro-app,#routespro-client-portal')||document;const safe=String(target||'').replace(/[^a-z0-9_-]/gi,'');if(!safe)return false;const trigger=shell.querySelector('[data-panel="'+safe+'"],[data-target="'+safe+'"],[data-rp-tab="'+safe+'"],[data-panel-target="'+safe+'"]');if(trigger){trigger.click();return true;}const panel=shell.querySelector('.rp-panel[data-panel="'+safe+'"]');if(panel){panel.scrollIntoView({behavior:'smooth',block:'start'});return true;}return false;}function bindIntelActions(){if(!els.ai)return;els.ai.querySelectorAll('[data-ff-intel-action]').forEach(btn=>btn.addEventListener('click',()=>{const target=String(btn.dataset.ffIntelTarget||'analytics'),kind=String(btn.dataset.ffIntelKind||'filter');window.dispatchEvent(new CustomEvent('fieldflow:intelligence-action',{detail:{id:btn.dataset.ffIntelAction||'',target,kind,source:'dashboard'}}));if(kind==='export'&&els.xls){els.xls.click();return;}if(target==='analytics'&&els.customAnalytics){els.customAnalytics.scrollIntoView({behavior:'smooth',block:'start'});return;}if(target==='routes'&&els.table){els.table.closest('section,article,div')?.scrollIntoView({behavior:'smooth',block:'start'});return;}openIntelTarget(target);}));}
  function decisionScoreTone(key,item){const score=item?.score==null?null:Number(item.score);if(score==null||!Number.isFinite(score))return'';if(key==='opportunity')return score>=45?'is-opportunity':'';if(String(item?.direction||'')==='higher_worse')return score>=70?'is-critical':(score>=40?'is-watch':'is-good');return score>=80?'is-good':(score>=55?'is-watch':'is-critical');}
  function renderDecisionScores(data){if(!els.decisionScores)return;const ds=data?.decision_scores||{};const defs=[['operational_health','Operational Health'],['execution','Execution'],['risk','Risk'],['opportunity','Opportunity'],['stock_risk','Stock Risk'],['confidence','Confidence']];els.decisionScores.innerHTML=defs.map(([key,label])=>{const item=ds?.[key]||{},raw=item.score,has=raw!==null&&raw!==undefined&&raw!==''&&Number.isFinite(Number(raw)),value=has?Math.round(Number(raw)*10)/10:'n.d.',suffix=has?'/100':'',tone=decisionScoreTone(key,item);return `<article class="ffad-score-card ${tone}"><span>${esc(label)}</span><strong>${esc(value)}${suffix}</strong><b>${esc(item.label||'Sem dados')}</b><small>${esc(item.explanation||'Sem evidência suficiente neste âmbito.')}</small></article>`;}).join('');}
  function renderPriorityActions(data){if(!els.priorityActions||!els.priorityList)return;const ctx=data?.contextual_predictive_intelligence||data?.store_intelligence?.contextual_predictive||{},actions=Array.isArray(ctx?.next_best_actions)?ctx.next_best_actions:[];const limit=selfMode?4:6;if(!actions.length){els.priorityActions.hidden=true;els.priorityList.innerHTML='';return;}els.priorityActions.hidden=false;els.priorityList.innerHTML=actions.slice(0,limit).map((a,index)=>{const ev=Array.isArray(a?.evidence)?a.evidence:[],observed=ev.filter(x=>String(x?.kind||'')==='observed').slice(0,2).map(x=>`${esc(x.label||'Sinal')}: ${esc(x.value||'')}`).join(' · '),projected=ev.filter(x=>String(x?.kind||'')==='projected').slice(0,1).map(x=>`${esc(x.label||'Projeção')}: ${esc(x.value||'')}`).join(' · ');return `<article class="ffad-priority-card"><span class="ffad-priority-index">${index+1}</span><div class="ffad-priority-copy"><strong>${esc(a.location_name||'PDV')}${a.product?` · ${esc(a.product)}`:''}</strong><p>${esc(a.action||'Rever na próxima intervenção.')}</p><small>${esc(a.issue_label||a.issue_type||'Acompanhamento')}${observed?` · Observado: ${observed}`:''}${projected?` · Projetado: ${projected}`:''}</small></div><span class="ffad-priority-score">${esc(Math.round(Number(a.rank_score||0)))}/100</span></article>`;}).join('');}
  let askHistory=[],askContextKey='';
  function askScopeKey(){const body=Object.fromEntries(params().entries());return JSON.stringify({audience,client_id:body.client_id||'',project_id:body.project_id||'',route_id:body.route_id||'',user_id:selfMode?'me':(body.user_id||''),location_id:body.location_id||'',from:body.from||'',to:body.to||'',category:body.category||'',product:body.product||''});}
  function askScroll(){if(els.askThread)els.askThread.scrollTop=els.askThread.scrollHeight;}
  function askReset(){askHistory=[];askContextKey='';if(els.askInput)els.askInput.value='';if(els.askThread)els.askThread.innerHTML=`<div class="ffad-ask-empty"><strong>Pergunta pelo que interessa.</strong><span>${selfMode?'Só são usados dados das tuas lojas e rotas. A conversa não altera a operação.':'A conversa respeita o âmbito atual e não altera dados operacionais.'}</span></div>`;}
  function askSyncScope(){const key=askScopeKey();if(askContextKey&&askContextKey!==key)askReset();}
  function askUser(question){if(!els.askThread)return;if(els.askThread.querySelector('.ffad-ask-empty'))els.askThread.innerHTML='';}
  function askLoading(){if(!els.askThread)return null;const id='ffad-ask-loading-'+Date.now();els.askThread.insertAdjacentHTML('beforeend',`<article class="ffad-ask-message" id="${id}"><div class="ffad-ask-loading">A cruzar o contexto FieldFlow...</div></article>`);askScroll();return box.querySelector('#'+id);}
  function askAnswer(data,placeholder){if(!els.askThread)return;if(placeholder&&placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);const points=Array.isArray(data?.key_points)?data.key_points:[],evidence=Array.isArray(data?.evidence)?data.evidence:[],followups=Array.isArray(data?.followups)?data.followups:[],kindLabels={observed:'Observado',projected:'Projetado',learned:'Aprendido',advisory:'Advisory'};const pointHtml=points.length?`<ul class="ffad-ask-points">${points.slice(0,5).map(x=>`<li>${esc(x)}</li>`).join('')}</ul>`:'',evidenceHtml=evidence.length?`<div class="ffad-ask-evidence">${evidence.slice(0,8).map(x=>{const kind=String(x?.kind||'observed');return `<span class="is-${esc(kind)}" title="${esc(x?.source||'FieldFlow')}"><b>${esc(kindLabels[kind]||'Evidência')}</b>${esc(x?.label||'Fonte')}: ${esc(x?.value||'')}</span>`;}).join('')}</div>`:'',followHtml=followups.length?`<div class="ffad-ask-followups">${followups.slice(0,4).map(x=>`<button type="button" data-ffad-ask-question="${esc(x)}">${esc(x)}</button>`).join('')}</div>`:'',confidence=data?.confidence_score!=null?`${esc(data.confidence_score)}/100`:esc(data?.confidence||'n.d.');els.askThread.insertAdjacentHTML('beforeend',`<article class="ffad-ask-message"><strong>FieldFlow</strong><p>${esc(data?.answer||'Não foi possível produzir uma resposta segura.')}</p>${pointHtml}${evidenceHtml}<div class="ffad-ask-meta"><span>${esc(data?.provider||'Motor FieldFlow')}</span><span>Confiança ${confidence}</span><span>${data?.source==='ai'?'IA fundamentada':'Resposta determinística'}</span><span>${selfMode?'Âmbito pessoal':'Read-only'}</span></div>${followHtml}</article>`);askScroll();}
  function askError(message,placeholder){if(!els.askThread)return;if(placeholder&&placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);els.askThread.insertAdjacentHTML('beforeend',`<article class="ffad-ask-message"><strong>FieldFlow</strong><p>${esc(message||'Não foi possível responder neste momento.')}</p><div class="ffad-ask-meta"><span>O Analytics base continua operacional</span></div></article>`);askScroll();}
  async function askFieldFlow(question){question=String(question||'').trim();if(!question||!els.askThread)return;const body=Object.fromEntries(params().entries()),contextKey=askScopeKey();if(askContextKey&&askContextKey!==contextKey)askReset();askContextKey=contextKey;askUser(question);const placeholder=askLoading();if(els.askInput)els.askInput.value='';if(els.askSubmit){els.askSubmit.disabled=true;els.askSubmit.textContent='A responder...';}try{body.audience=audience;body.question=question;body.history=askHistory.slice(-6);const r=await fetch(api+'analytics/ask',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce,'Content-Type':'application/json'},body:JSON.stringify(body)});const data=await r.json().catch(()=>({}));if(!r.ok)throw new Error(data?.message||'Não foi possível responder neste momento.');askAnswer(data,placeholder);askHistory.push({role:'user',content:question},{role:'assistant',content:String(data?.answer||'')});if(askHistory.length>12)askHistory=askHistory.slice(-12);}catch(err){askError(err?.message||'Não foi possível responder neste momento.',placeholder);}finally{if(els.askSubmit){els.askSubmit.disabled=false;els.askSubmit.textContent='Perguntar';}}}
  function ffadExplainabilityHtml(data){const x=data?.explainability||{},uses=Array.isArray(x.uses)?x.uses:[],rules=Array.isArray(x.rules)?x.rules:[],no=Array.isArray(x.does_not_do)?x.does_not_do:[],confidence=x.confidence||{};if(!uses.length&&!rules.length)return'';const list=items=>items.slice(0,6).map(i=>`<li>${typeof i==='object'?`<strong>${esc(i.label||'')}</strong>${i.detail?' · '+esc(i.detail):''}`:esc(i)}</li>`).join('');return `<details class="ffad-ai-explain"><summary>Como funciona esta análise?</summary><div class="ffad-ai-explain-body"><p>${esc(x.summary||'A leitura usa apenas dados FieldFlow do âmbito atual.')}</p><div class="ffad-ai-explain-grid"><div><h6>Dados utilizados</h6><ul>${list(uses)}</ul></div><div><h6>Regras de interpretação</h6><ul>${list(rules)}</ul></div><div><h6>O que não faz</h6><ul>${list(no)}</ul></div><div><h6>Confiança</h6><p>${esc(confidence.detail||'A confiança depende da cobertura e consistência dos dados disponíveis.')}</p></div></div></div></details>`;}
  function renderAI(data){renderDecisionScores(data);renderPriorityActions(data);if(!els.ai)return;els.ai.innerHTML=intelHtml(data)+`<div class="ffad-ai-summary"><strong>${esc(data.headline||'Análise do período')}</strong><p>${esc(data.summary||'Ainda não existem dados suficientes para uma leitura detalhada.')}</p></div><div class="ffad-ai-grid">${aiItems(data.highlights,'highlights','Sem destaques relevantes.')}${aiItems(data.risks,'risks','Sem riscos relevantes detetados.')}${aiItems(data.actions,'actions','Continua a acompanhar a execução.')}</div>${ffadExplainabilityHtml(data)}<div class="ffad-ai-meta"><span>Confiança: ${esc(data.confidence||'n.d.')}</span><span>${esc(data.provider||'Motor FieldFlow')}</span><span>${esc(data.generated_at||'')}</span></div>`;bindIntelActions();}
  async function loadAI(refresh=false){if(!els.ai)return;if(els.aiRefresh){els.aiRefresh.disabled=true;els.aiRefresh.textContent='A analisar...';}els.ai.innerHTML='<div class="ffad-empty">A cruzar execução e reportes...</div>';try{const body=Object.fromEntries(params().entries());body.audience=audience;body.refresh=refresh?1:0;const r=await fetch(api+'analytics/ai-insights',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce,'Content-Type':'application/json'},body:JSON.stringify(body)});if(!r.ok)throw new Error('Pedido falhou');const data=await r.json();renderAI(data);try{const sr=await fetch(api+'analytics/store-intelligence',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce,'Content-Type':'application/json'},body:JSON.stringify(body)});if(sr.ok){const store=await sr.json();if(store&&store.available){data.store_intelligence=store;data.contextual_predictive_intelligence=store.contextual_predictive||{};renderAI(data);}}}catch(_){/* Store Intelligence remains fail-open in front Analytics. */}}catch(e){if(els.decisionScores)els.decisionScores.innerHTML='<div class="ffad-empty">Sinais de decisão temporariamente indisponíveis.</div>';if(els.priorityActions)els.priorityActions.hidden=true;els.ai.innerHTML='<div class="ffad-empty">Não foi possível gerar a análise inteligente neste momento.</div>';}finally{if(els.aiRefresh){els.aiRefresh.disabled=false;els.aiRefresh.textContent='Atualizar análise';}}}
  function metricSeries(metric){let series=Array.isArray(metric.series)?metric.series.slice(0,10):[];if(!series.length&&Array.isArray(metric.rows))series=metric.rows.slice(0,10).map(r=>({label:r.product||r.location||r.category||r.date||'Registo',value:r.value??r.after??r.growth??0}));if(!series.length)return '<div class="ffad-metric-note">Sem série disponível no período.</div>';const vals=series.map(x=>Math.abs(parseFloat(x.value||0)||0)),max=Math.max(1,...vals);return `<div class="ffad-series">${series.map((x,index)=>{const v=parseFloat(x.value||0)||0,pct=Math.max(2,Math.min(100,Math.abs(v)/max*100));return `<div class="ffad-series-row"><em>${index+1}</em><span title="${esc(x.label||'')}">${esc(x.label||'Sem label')}</span><span class="ffad-series-track"><i style="width:${pct}%"></i></span><b>${esc(x.value??0)}</b></div>`;}).join('')}</div>`;}
  function metricFronts(metric){const s=metric.fronts_summary;if(!s)return'';const growth=s.growth_label||'0,0%';return `<div class="ffad-fronts"><div><span>Frentes antes</span><strong>${esc(s.before_total??0)}</strong></div><div><span>Frentes depois</span><strong>${esc(s.after_total??0)}</strong></div><div><span>Evolução</span><strong>${esc(growth)}</strong></div><div><span>Com crescimento</span><strong>${esc(s.up??0)}</strong></div><div><span>Com redução</span><strong>${esc(s.down??0)}</strong></div></div>`;}
  function isShareSpaceMetric(metric){return String(metric?.is_share_space_metric||'0')==='1'||String(metric?.question_type||'')==='share_space_matrix'||!!metric?.share_space_summary;}
  function metricShareSpace(metric){if(!isShareSpaceMetric(metric))return'';const s=metric.share_space_summary||{},rows=Array.isArray(metric.rows)?metric.rows:[],series=Array.isArray(metric.series)?metric.series:[];const fmt=v=>new Intl.NumberFormat('pt-PT',{maximumFractionDigits:1}).format(Number(v||0));const summary=`<div class="ffad-share-summary"><div><span>Quota média PR</span><strong>${fmt(s.avg_share??metric.value)}%</strong></div><div><span>Desvio médio</span><strong>${Number(s.avg_gap||0)>0?'+':''}${fmt(s.avg_gap)} pp</strong></div><div><span>Objetivo atingido</span><strong>${esc(s.on_target??0)}</strong></div><div><span>Abaixo objetivo</span><strong>${esc(s.below_target??0)}</strong></div><div><span>Frentes PR</span><strong>${fmt(s.pr_fronts_total)}</strong></div><div><span>Frentes concorrência</span><strong>${fmt(s.competitor_fronts_total)}</strong></div></div>`;const source=series.length?series:rows.slice(0,12).map(r=>({label:r.metric||r.product||'Share Space',value:r.pr_share||0,target:r.target||0,gap:r.gap||0}));const bars=source.slice(0,12).map(x=>{const row=rows.find(r=>(r.metric||r.product)===x.label)||{},value=Math.max(0,Math.min(100,Number(x.value??row.pr_share??0))),target=Math.max(0,Math.min(100,Number(x.target??row.target??0))),gap=Number(x.gap??row.gap??(value-target));return `<div class="ffad-share-row"><strong>${esc(x.label||'Comparação')}</strong><span class="ffad-share-track" title="Quota PR ${fmt(value)}%, objetivo ${fmt(target)}%"><i style="width:${value}%"></i><b style="left:${target}%"></b></span><em class="${gap<0?'is-below':'is-ok'}">${fmt(value)}% · ${gap>0?'+':''}${fmt(gap)} pp</em></div>`;}).join('');return `<div class="ffad-share-space">${summary}${bars?`<div class="ffad-share-bars">${bars}</div>`:''}</div>`;}
  function analyticsMetaLabel(value){const key=String(value||'');const labels={evidence:'Todas as evidências',count:'Contagem',sum:'Soma',avg:'Média',average:'Média',location_id:'Loja / local',submitted_at:'Data',date:'Data',category:'Categoria',product:'Produto',answer:'Resposta',value:'Valor'};return labels[key]||key.replace(/_/g,' ');}
  function evidenceFiles(value){return (Array.isArray(value)?value:[]).filter(f=>f&&(f.url||f.attachment_id));}
  function evidenceIsImage(file){const type=String(file?.type||file?.mime_type||'').toLowerCase(),url=String(file?.url||'').split('?')[0].toLowerCase();return type.indexOf('image/')===0||/\.(jpg|jpeg|png|webp|gif|avif|bmp)$/i.test(url);}
  function evidencePrepareToken(raw){try{const value=new URL(String(raw||''),location.href).searchParams.get('token')||'';return /^[a-f0-9]{40}$/i.test(value)?value.toLowerCase():'';}catch(_){return'';}}
  function evidenceAction(entity,label){const files=evidenceFiles(entity?.files);if(!files.length)return `<span class="ffad-evidence-action is-disabled">Sem imagens</span>`;const token=evidencePrepareToken(entity?.download_url);if(!token)return `<span class="ffad-evidence-action is-disabled" title="Atualiza o Analytics para renovar o acesso às evidências">ZIP indisponível</span>`;const key='ffad-evidence-'+(++analyticsEvidenceSeq);analyticsEvidenceGroups[key]={token,location:entity?.location||entity?.date_label||'evidencias',count:files.length};return `<button type="button" class="ffad-evidence-action" data-ffad-evidence-download="${esc(key)}">${esc(label||'Descarregar ZIP')}</button>`;}
  function evidenceThumbs(files,show=true){if(!show)return'';const valid=evidenceFiles(files);if(!valid.length)return'';const placeholder='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';return `<div class="ffad-evidence-thumbs">${valid.map((f,i)=>{const url=String(f.url||''),name=String(f.name||('Imagem '+(i+1)));if(url&&evidenceIsImage(f))return `<a class="ffad-evidence-thumb" href="${esc(url)}" target="_blank" rel="noopener"><img src="${placeholder}" data-src="${esc(url)}" alt="${esc(name)}" loading="lazy" decoding="async" width="240" height="160"><span>${esc(name)}</span></a>`;return url?`<a class="ffad-evidence-thumb ffad-evidence-file" href="${esc(url)}" target="_blank" rel="noopener">${esc(name)}</a>`:`<span class="ffad-evidence-thumb ffad-evidence-file">${esc(name)}</span>`;}).join('')}</div>`;}
  function evidenceGallery(metric,groups){const showThumbs=String(metric.evidence_show_thumbnails??'1')!=='0';if(!showThumbs)return '<div class="ffad-metric-note">As miniaturas estão desativadas neste widget.</div>';const flat=[];groups.forEach(g=>{const location=String(g.location||('Loja #'+(g.location_id||'')));const dates=Array.isArray(g.dates)&&g.dates.length?g.dates:[{date:'',date_label:'Sem data',files:g.files||[]}];dates.forEach(d=>{evidenceFiles(d.files).forEach((file,idx)=>{flat.push({...file,_location:location,_date:String(d.date_label||d.date||'Sem data'),_caption:`${location} · ${String(d.date_label||d.date||'Sem data')}`,_index:idx});});});});if(!flat.length)return '<div class="ffad-metric-note">Sem imagens no período selecionado.</div>';return `<div class="ffad-evidence-thumbs ffad-evidence-gallery">${flat.map((f,i)=>{const url=String(f.url||''),name=String(f.name||('Imagem '+(i+1))),caption=String(f._caption||name);if(url&&evidenceIsImage(f))return `<a class="ffad-evidence-thumb" href="${esc(url)}" target="_blank" rel="noopener"><img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="${esc(url)}" alt="${esc(name)}" loading="lazy" decoding="async" width="240" height="160"><span title="${esc(caption)}">${esc(caption)}</span></a>`;return url?`<a class="ffad-evidence-thumb ffad-evidence-file" href="${esc(url)}" target="_blank" rel="noopener">${esc(name)}</a>`:`<span class="ffad-evidence-thumb ffad-evidence-file">${esc(name)}</span>`;}).join('')}</div>`;}
  function evidenceShell(metric,summary,groups){const total=groups.reduce((n,g)=>n+evidenceFiles(g.files).length,0),stores=groups.length,gallery=evidenceGallery(metric,groups);return `<div class="ffad-evidence-shell" data-ffad-evidence-shell><div class="ffad-evidence-toolbar"><div class="ffad-evidence-tabs" role="tablist" aria-label="Visualização das evidências"><button type="button" class="ffad-evidence-tab is-active" data-ffad-evidence-tab="summary" role="tab" aria-selected="true">Resumo</button><button type="button" class="ffad-evidence-tab" data-ffad-evidence-tab="gallery" role="tab" aria-selected="false">Galeria</button></div><div class="ffad-evidence-toolbar-meta"><span class="ffad-evidence-pill">${stores} ${stores===1?'loja':'lojas'}</span><span class="ffad-evidence-pill">${total} ${total===1?'imagem':'imagens'}</span></div></div><div class="ffad-evidence-panel is-active" data-ffad-evidence-panel="summary">${summary}</div><div class="ffad-evidence-panel" data-ffad-evidence-panel="gallery">${gallery}</div></div>`;}
  function evidenceLocationDate(metric,groups){const showThumbs=String(metric.evidence_show_thumbnails??'1')!=='0';return `<div class="ffad-evidence-store-list" data-ff-evidence-list>${groups.map((g,storeIndex)=>{const files=evidenceFiles(g.files),dates=Array.isArray(g.dates)&&g.dates.length?g.dates:[{date:'',date_label:'Sem data',files,download_url:'',count:files.length}];return `<details class="ffad-evidence-store-card" data-ff-evidence-store data-ff-index="${storeIndex}"><summary class="ffad-evidence-store-head"><div class="ffad-evidence-store-copy"><strong>${esc(g.location||('Loja #'+(g.location_id||'')))}</strong><span>${files.length} ${files.length===1?'imagem':'imagens'} · ${dates.length} ${dates.length===1?'data':'datas'}</span></div><span class="ffad-evidence-chevron" aria-hidden="true"></span></summary><div class="ffad-evidence-store-body"><div class="ffad-evidence-action-row">${evidenceAction({...g,files},'ZIP da loja')}</div><div class="ffad-evidence-date-list">${dates.map((d,dateIndex)=>{const dateFiles=evidenceFiles(d.files);return `<details class="ffad-evidence-date-group" data-ff-evidence-date ${dateIndex===0?'data-ff-first-date="1"':''}><summary class="ffad-evidence-date-head"><div><strong>${esc(d.date_label||d.date||'Sem data')}</strong><span>${dateFiles.length} ${dateFiles.length===1?'imagem':'imagens'}${d.route_id?' · Rota #'+esc(d.route_id):(Number(d.route_count||0)>1?' · '+esc(d.route_count)+' rotas':'')}</span></div><span class="ffad-evidence-chevron" aria-hidden="true"></span></summary><div class="ffad-evidence-date-body"><div class="ffad-evidence-action-row">${evidenceAction({...d,files:dateFiles},'ZIP da data')}</div>${evidenceThumbs(dateFiles,showThumbs)}</div></details>`;}).join('')}</div></div></details>`;}).join('')}</div>`;}
  function evidenceDateLocation(metric,groups){const showThumbs=String(metric.evidence_show_thumbnails??'1')!=='0',byDate={};groups.forEach(g=>(Array.isArray(g.dates)?g.dates:[]).forEach(d=>{const key=String(d.date||'sem-data');if(!byDate[key])byDate[key]={date:d.date||'',date_label:d.date_label||d.date||'Sem data',stores:[]};byDate[key].stores.push({group:g,date:d});}));const dates=Object.values(byDate).sort((a,b)=>String(b.date).localeCompare(String(a.date)));return `<div class="ffad-evidence-date-first" data-ff-evidence-list>${dates.map((entry,dateIndex)=>{const total=entry.stores.reduce((n,x)=>n+evidenceFiles(x.date.files).length,0);return `<details class="ffad-evidence-date-card" data-ff-evidence-store data-ff-index="${dateIndex}"><summary class="ffad-evidence-date-first-head"><div class="ffad-evidence-date-copy"><strong>${esc(entry.date_label)}</strong><span>${total} ${total===1?'imagem':'imagens'} · ${entry.stores.length} ${entry.stores.length===1?'loja':'lojas'}</span></div><span class="ffad-evidence-chevron" aria-hidden="true"></span></summary><div class="ffad-evidence-date-first-body">${entry.stores.map((x,storeIndex)=>{const files=evidenceFiles(x.date.files);return `<details class="ffad-evidence-date-first-store" data-ff-evidence-date ${storeIndex===0?'data-ff-first-date="1"':''}><summary class="ffad-evidence-date-first-store-head"><strong>${esc(x.group.location||'Sem loja')}</strong><span class="ffad-evidence-chevron" aria-hidden="true"></span></summary><div class="ffad-evidence-date-body"><div class="ffad-evidence-action-row">${evidenceAction({...x.date,files},'ZIP')}</div>${evidenceThumbs(files,showThumbs)}</div></details>`;}).join('')}</div></details>`;}).join('')}</div>`;}
  function metricEvidence(metric){const groups=Array.isArray(metric.evidence_groups)?metric.evidence_groups:[];if(!groups.length)return '<div class="ffad-metric-note">Sem imagens no período selecionado.</div>';const grouping=String(metric.evidence_grouping||'location_date');let content='';if(grouping==='date_location')content=evidenceDateLocation(metric,groups);else if(grouping==='location'){const simple=groups.map(g=>({...g,dates:[{date:'',date_label:'Todas as datas',files:g.files,download_url:g.download_url,count:g.count}]}));content=evidenceLocationDate(metric,simple);}else content=evidenceLocationDate(metric,groups);const total=groups.reduce((n,g)=>n+evidenceFiles(g.files).length,0);return `<details class="ffad-evidence-surface ffad-evidence-section" data-ffad-detail><summary class="ffad-evidence-section-summary"><div><span>Evidências</span><strong>Fotografias e galerias</strong></div><em>${groups.length} grupo${groups.length===1?'':'s'} · ${total} imagem${total===1?'':'s'}</em></summary><div class="ffad-evidence-section-body">${evidenceShell(metric,content,groups)}</div></details>`;}
  function metricTableColumns(metric){const rows=Array.isArray(metric.rows)?metric.rows:[];const has=(field)=>rows.some(r=>r&&r[field]!==undefined&&r[field]!==null&&String(r[field]).trim()!=='');const isOrder=String(metric.question_type||'')==='order_suggestion'||has('order_qty'),isShare=isShareSpaceMetric(metric);const cols=[];const add=(field,label,className,render)=>cols.push({field,label,className,render});add('location','Loja / Local','col-location',r=>`<strong>${esc(r.location||'Sem loja')}</strong>`);if(has('date'))add('date','Data','col-date',r=>esc(r.date||''));if(isShare){add('metric','Comparação','col-product',r=>`<strong>${esc(r.metric||r.product||'')}</strong>`);add('pr_label','Produto PR','col-product',r=>esc(r.pr_label||''));add('competitor_label','Concorrência','col-product',r=>esc(r.competitor_label||''));add('pr_fronts','Frentes PR','col-number',r=>`<strong>${esc(r.pr_fronts??0)}</strong>`);add('competitor_fronts','Frentes concorrência','col-number',r=>esc(r.competitor_fronts??0));add('pr_share','Quota PR','col-number',r=>`${esc(r.pr_share??0)}%`);add('target','Objetivo','col-number',r=>`${esc(r.target??0)}%`);add('gap','Desvio','col-status',r=>`<strong class="${Number(r.gap||0)<0?'ffad-negative':'ffad-positive'}">${Number(r.gap||0)>0?'+':''}${esc(r.gap??0)} pp</strong>`);}else{if(has('category'))add('category','Categoria','col-category',r=>esc(r.category||''));if(has('product'))add('product','Produto','col-product',r=>esc(r.product||''));if(isOrder){add('order_qty','Caixas','col-number',r=>`<strong>${esc(r.order_qty??'')}</strong>`);if(has('units_per_case'))add('units_per_case','UN/CX','col-number',r=>esc(r.units_per_case??''));if(has('units_total'))add('units_total','Unidades','col-number',r=>esc(r.units_total??''));if(has('notes'))add('notes','Observações','col-value',r=>esc(r.notes||''));}else{if(has('image_count'))add('image_count','Imagens','col-number',r=>`<strong>${esc(r.image_count??0)}</strong>`);else if(has('value'))add('value','Valor','col-value',r=>esc(r.value??''));if(has('before'))add('before','Antes','col-number',r=>esc(r.before??''));if(has('after'))add('after','Depois','col-number',r=>esc(r.after??''));if(has('difference'))add('difference','Diferença','col-number',r=>esc(r.difference??''));if(has('growth'))add('growth','Evolução','col-status',r=>esc(r.growth||''));}}if(has('route'))add('route','Rota','col-route',r=>r.route?('#'+esc(r.route)):'');if(has('status'))add('status','Estado','col-status',r=>statusBadge(r.status||''));return cols;}
  function metricTable(metric,columns){
    const allRows=Array.isArray(metric.rows)?metric.rows:[],show=String(metric.show_table||'0')==='1'||metric.chart_type==='table'||!!metric.fronts_summary||isShareSpaceMetric(metric);if(!show||!allRows.length)return'';
    const cols=Array.isArray(columns)&&columns.length?columns:metricTableColumns(metric),key=analyticsMetricKey(metric),hasFronts=!!metric.fronts_summary&&box.classList.contains('fft-fronts-tabs');
    const groups={all:allRows,up:allRows.filter(r=>frontsRowKind(r)==='up'),down:allRows.filter(r=>frontsRowKind(r)==='down'),same:allRows.filter(r=>frontsRowKind(r)==='same')};
    const active=hasFronts&&['all','up','down','same'].includes(analyticsUi.tabs[key])?analyticsUi.tabs[key]:'all';analyticsUi.tabs[key]=active;
    const rows=hasFronts?(groups[active]||allRows):allRows,pageSize=Math.max(5,Math.min(30,parseInt(analyticsUi.sizes[key]||analyticsDefaultPageSize(),10)||10));analyticsUi.sizes[key]=pageSize;
    const total=Math.max(1,Math.ceil(rows.length/pageSize)),page=Math.max(1,Math.min(total,parseInt(analyticsUi.pages[key]||1,10)||1));analyticsUi.pages[key]=page;
    const visible=rows.slice((page-1)*pageSize,page*pageSize),size=cols.length<=3?'is-compact':(cols.length<=6?'is-standard':'is-wide'),body=visible.map(r=>`<tr>${cols.map(c=>`<td class="${c.className}" data-label="${esc(c.label)}">${c.render(r)}</td>`).join('')}</tr>`).join('')||`<tr><td colspan="${cols.length}">Sem registos nesta vista.</td></tr>`,head=`<tr>${cols.map(c=>`<th class="${c.className}">${esc(c.label)}</th>`).join('')}</tr>`;
    const tabs=hasFronts?`<div class="ffad-fronts-tabs" role="tablist" aria-label="Filtrar frentes atuais"><button type="button" data-ffad-fronts-tab="all" data-ffad-fronts-key="${esc(key)}" class="${active==='all'?'is-active':''}">Todas <span>${allRows.length}</span></button><button type="button" data-ffad-fronts-tab="up" data-ffad-fronts-key="${esc(key)}" class="${active==='up'?'is-active':''}">Crescimento <span>${groups.up.length}</span></button><button type="button" data-ffad-fronts-tab="down" data-ffad-fronts-key="${esc(key)}" class="${active==='down'?'is-active':''}">Redução <span>${groups.down.length}</span></button><button type="button" data-ffad-fronts-tab="same" data-ffad-fronts-key="${esc(key)}" class="${active==='same'?'is-active':''}">Sem alteração <span>${groups.same.length}</span></button></div>`:'';
    return `<details class="ffad-widget-detail" data-ffad-detail><summary><div><span>Dados detalhados</span><strong>Detalhe operacional</strong></div><em>${visible.length} de ${rows.length}</em></summary><div class="ffad-widget-detail-body">${tabs}<div class="ffad-table-controls"><span>${rows.length} linhas · página ${page}/${total}</span><label>Mostrar <select data-ffad-page-size="${esc(key)}">${analyticsPageOptions(pageSize)}</select></label></div><div class="ffad-table-wrap ffad-analytics-table ${size}" data-column-count="${cols.length}"><table><thead>${head}</thead><tbody>${body}</tbody></table></div>${analyticsPager(key,page,total)}<p class="ffad-analytics-hint">A paginação afeta apenas a visualização. As exportações mantêm todas as ${allRows.length} linhas.</p></div></details>`;
  }
  function metricQuestionBreakdown(metric){const items=Array.isArray(metric.question_breakdown)?metric.question_breakdown:[];if(items.length<2)return'';return `<div class="ffad-smart-breakdown"><div class="ffad-smart-section-title"><strong>Leitura por pergunta</strong><small>${items.length} indicadores dentro desta métrica</small></div>${items.map(item=>{const dist=Array.isArray(item.distribution)?item.distribution:[];return `<details class="ffad-smart-question"><summary><span>${esc(item.label||item.question_key||'Pergunta')}</span><small>${esc(item.responses||0)} respostas · ${esc(item.location_count||0)} lojas</small></summary>${dist.length?`<div class="ffad-smart-dist">${dist.map(x=>`<span><b>${esc(x.label||'')}</b><em>${esc(x.value||0)}</em></span>`).join('')}</div>`:''}</details>`;}).join('')}</div>`;}
  function metricBriefingRollup(metric){const items=Array.isArray(metric.briefing_questions)?metric.briefing_questions:[];const coverage=Number(metric.coverage_percent||0);return `<article class="ffad-metric briefing_rollup is-wide is-briefing-widget ffad-smart-card"><div class="ffad-metric-label"><div class="ffad-metric-copy"><span class="ffad-metric-eyebrow">Dashboard evergreen</span><strong>${esc(metric.metric_label||'Briefing operacional')}</strong><div class="ffad-question">As perguntas variáveis entram aqui automaticamente, sem criar novos widgets.</div></div><span>briefing</span></div><div class="ffad-metric-body"><div class="ffad-smart-summary"><div><span>Perguntas</span><strong>${esc(metric.question_count||items.length)}</strong></div><div><span>Cobertura</span><strong>${esc(coverage.toLocaleString('pt-PT',{maximumFractionDigits:1}))}%</strong></div><div><span>Respostas</span><strong>${esc(metric.answered_assignments||0)}</strong></div><div><span>Pendentes</span><strong>${esc(metric.pending_assignments||0)}</strong></div><div><span>Lojas alvo</span><strong>${esc(metric.target_store_count||0)}</strong></div></div><div class="ffad-smart-questions">${items.map(item=>{const dist=Array.isArray(item.distribution)?item.distribution:[],samples=Array.isArray(item.samples)?item.samples:[];const conformity=item.conformity_percent==null?'':`<span class="ffad-smart-chip"><b>Conformidade</b><em>${esc(item.conformity_percent)}%</em></span>`;return `<details class="ffad-smart-question" data-ffad-detail><summary><span>${esc(item.label||'Pergunta')}</span><small>${esc(item.answered_assignments??item.answered_stores??0)}/${esc(item.target_assignments??item.target_stores??0)} execuções · ${esc(item.answered_stores||0)}/${esc(item.target_stores||0)} lojas · ${esc(item.coverage_percent||0)}%</small></summary><div class="ffad-smart-question-body"><div class="ffad-smart-chips"><span class="ffad-smart-chip"><b>Sim</b><em>${esc(item.yes||0)}</em></span><span class="ffad-smart-chip"><b>Não</b><em>${esc(item.no||0)}</em></span><span class="ffad-smart-chip"><b>N/A</b><em>${esc(item.na||0)}</em></span>${conformity}</div>${dist.length?`<div class="ffad-smart-dist">${dist.map(x=>`<span><b>${esc(x.label||'')}</b><em>${esc(x.value||0)}</em></span>`).join('')}</div>`:''}${samples.length?`<div class="ffad-smart-samples">${samples.map(x=>`<p><strong>${esc(x.location||'Loja')}</strong>${esc(x.value||'')}</p>`).join('')}</div>`:''}</div></details>`;}).join('')||'<div class="ffad-metric-note">Sem perguntas variáveis no período selecionado.</div>'}</div></div></article>`;}
  function metricBriefingEvidence(metric){const items=Array.isArray(metric.briefing_evidence_questions)?metric.briefing_evidence_questions:[],operational=String(metric.operational_evidence_rollup||'0')==='1',title=metric.metric_label||(operational?'Evidências operacionais':'Evidências do briefing'),description=operational?'Fotografias operacionais permanentes, reunidas por tipo e loja.':'Galerias das perguntas variáveis, organizadas por pergunta e loja.',countLabel=operational?'Tipos de evidência':'Perguntas com evidência';return `<article class="ffad-metric briefing_evidence_rollup is-wide ${operational?'is-operational-evidence':'is-briefing-widget'} ffad-smart-card"><div class="ffad-metric-label"><div class="ffad-metric-copy"><span class="ffad-metric-eyebrow">Dashboard evergreen</span><strong>${esc(title)}</strong><div class="ffad-question">${esc(description)}</div></div><span>evidências</span></div><div class="ffad-metric-body"><div class="ffad-smart-summary"><div><span>${esc(countLabel)}</span><strong>${esc(metric.question_count||items.length)}</strong></div><div><span>Ficheiros</span><strong>${esc(metric.record_count||0)}</strong></div><div><span>Lojas alvo</span><strong>${esc(metric.target_store_count||0)}</strong></div></div><div class="ffad-smart-questions">${items.map(item=>{const temp={...metric,evidence_groups:item.evidence_groups||[],files:item.files||[],metric_key:item.key||metric.metric_key,evidence_grouping:'location_date'};return `<details class="ffad-smart-question" data-ffad-detail><summary><span>${esc(item.label||'Evidência')}</span><small>${esc(item.image_count||0)} ficheiros · ${esc(item.location_count||0)} lojas</small></summary><div class="ffad-smart-question-body">${metricEvidence(temp)}</div></details>`;}).join('')||'<div class="ffad-metric-note">Sem evidências no período selecionado.</div>'}</div></div></article>`;}
  function metricCard(metric){const type=String(metric.chart_type||'kpi');if(type==='briefing_rollup')return metricBriefingRollup(metric);if(type==='briefing_evidence_rollup')return metricBriefingEvidence(metric);const value=metric.value==null?'Sem dados':metric.value;const question=metric.question_label||metric.metric_label||metric.metric_key||'Métrica';const title=metric.metric_label||metric.metric_key||question;const columns=metricTableColumns(metric),rows=Array.isArray(metric.rows)?metric.rows:[],hasShare=isShareSpaceMetric(metric),hasTable=(String(metric.show_table||'0')==='1'||type==='table'||!!metric.fronts_summary||hasShare)&&rows.length>0,hasFronts=!!metric.fronts_summary,hasEvidence=Array.isArray(metric.evidence_groups)&&metric.evidence_groups.length>0,wide=hasShare||hasTable||hasFronts||hasEvidence||['line','pie','image_gallery','table'].includes(type);const typeLabel=hasShare?'share space':(type==='image_gallery'?'evidências':type);const aggregationLabel=metric.aggregation_label||analyticsMetaLabel(metric.aggregation),dimensionLabel=metric.dimension_label||analyticsMetaLabel(metric.dimension),secondaryLabel=metric.secondary_dimension_label||analyticsMetaLabel(metric.secondary_dimension);const subtitle=[];if(question&&question!==title)subtitle.push(question);if(aggregationLabel)subtitle.push(aggregationLabel);if(dimensionLabel)subtitle.push(secondaryLabel?dimensionLabel+' + '+secondaryLabel:dimensionLabel);if(String(metric.evergreen_briefing||'0')==='1'&&Number(metric.source_question_count||0)>1)subtitle.push('Histórico consolidado · '+Number(metric.source_question_count||0)+' versões');const note=String(metric.note||'').trim();let visual='';if(hasShare)visual=metricShareSpace(metric);else if(type==='kpi')visual=`<div class="ffad-metric-value">${esc(value)}</div>`;else if(type==='image_gallery')visual=metricEvidence(metric);else visual=metricSeries(metric);const extraEvidence=type!=='image_gallery'&&hasEvidence?metricEvidence(metric):'';const classes=['ffad-metric',type,wide?'is-wide':'is-compact-widget',hasTable?'has-table':'',hasFronts?'has-fronts':'',hasShare?'has-share':'',hasEvidence?'has-evidence':'',String(metric.briefing_priority||'0')==='1'?'is-briefing-widget':''].filter(Boolean).join(' ');const eyebrow=String(metric.briefing_priority||'0')==='1'?'Briefing operacional':'Widget analytics';return `<article class="${classes}"><div class="ffad-metric-label"><div class="ffad-metric-copy"><span class="ffad-metric-eyebrow">${esc(eyebrow)}</span><strong>${esc(title)}</strong>${subtitle.length?`<div class="ffad-question">${esc(subtitle.join(' · '))}</div>`:''}</div><span>${esc(typeLabel)}</span></div><div class="ffad-metric-body">${note?`<div class="ffad-metric-note">${esc(note)}</div>`:''}${visual}${metricFronts(metric)}${extraEvidence}${metricQuestionBreakdown(metric)}${metricTable(metric,columns)}</div></article>`;}
  function renderCustomAnalytics(data){currentAnalyticsData=data;analyticsEvidenceGroups={};analyticsEvidenceSeq=0;if(!els.customAnalytics)return;const analytics=data?.form_analytics||{},dashboards=Array.isArray(analytics.dashboards)?analytics.dashboards:[],metrics=Array.isArray(analytics.metrics)?analytics.metrics:[],orderNumber=(value,fallback=10)=>{const parsed=parseInt(value,10);return Number.isFinite(parsed)?parsed:fallback;};const map=new Map();dashboards.forEach((d,index)=>{const id=String(d.id||0);if(!map.has(id))map.set(id,{...d,_canonical_index:index});});metrics.forEach((m,index)=>{const id=String(m.dashboard_id||0);if(!map.has(id))map.set(id,{id:m.dashboard_id||0,title:m.dashboard_title||'Analytics operacional',description:'',layout_type:'mixed',sort_order:m.dashboard_sort_order??999,ai_enabled:0,_canonical_index:dashboards.length+index});});const list=Array.from(map.values()).sort((a,b)=>{const byOrder=orderNumber(a.sort_order,999)-orderNumber(b.sort_order,999);if(byOrder)return byOrder;const byIndex=orderNumber(a._canonical_index,0)-orderNumber(b._canonical_index,0);if(byIndex)return byIndex;return orderNumber(a.id,0)-orderNumber(b.id,0);});if(!list.length){els.customAnalytics.innerHTML='';return;}els.customAnalytics.innerHTML=list.map(d=>{const layout=['executive','table'].includes(String(d.layout_type||''))?String(d.layout_type):'mixed',group=metrics.filter(m=>String(m.dashboard_id||0)===String(d.id||0)).slice().sort((a,b)=>{const byOrder=orderNumber(a.sort_order,10)-orderNumber(b.sort_order,10);return byOrder||orderNumber(a.binding_id,0)-orderNumber(b.binding_id,0);});return `<section class="ffad-custom-dashboard layout-${esc(layout)}" data-dashboard-layout="${esc(layout)}" data-dashboard-order="${esc(orderNumber(d.sort_order,999))}"><div class="ffad-custom-head"><div class="ffad-custom-head-copy"><span>Dashboard analítico</span><h5>${esc(d.title||'Dashboard')} ${String(d.ai_enabled||0)==='1'?'<b class="ffad-ai-badge">IA</b>':''}</h5><p>${esc(d.description||'Métricas configuradas para este âmbito.')}</p></div><em>${group.length} widget${group.length===1?'':'s'}</em></div><div class="ffad-custom-grid">${group.map(metricCard).join('')||'<div class="ffad-custom-empty">Dashboard configurado, ainda sem dados no período.</div>'}</div></section>`;}).join('');const compact=window.matchMedia('(max-width:700px)').matches;els.customAnalytics.querySelectorAll('[data-ffad-detail]').forEach(node=>{node.open=!compact;});}
  function renderChart(rows){ if(!rows.length){els.chart.innerHTML='<div class="ffad-empty">Sem rotas no período selecionado.</div>';return;} const max=100,bw=rows.length>12?24:38,gap=13,w=Math.max(680,rows.length*(bw+gap)+54),h=260; const points=[]; const bars=rows.map((r,i)=>{const v=Math.max(0,Math.min(100,parseFloat(r.done_rate||0)||0)),x=30+i*(bw+gap),bh=Math.max(5,(v/max)*165),y=198-bh;points.push(`${x+bw/2},${y}`);return `<g><title>${esc(r.date||'')}: ${esc(v)}%</title><rect x="${x}" y="${y}" width="${bw}" height="${bh}" rx="9" fill="url(#ffadBar)"></rect><text x="${x+bw/2}" y="220" text-anchor="middle" font-size="10" fill="var(--ff-muted,#64748b)">${esc(String(r.date||'').slice(5))}</text><text x="${x+bw/2}" y="${Math.max(17,y-9)}" text-anchor="middle" font-size="10" font-weight="700" fill="var(--ff-heading,#0f172a)">${esc(v)}%</text></g>`;}).join(''); const grids=[25,50,75,100].map(v=>{const y=198-(v/100)*165;return `<g><line x1="22" y1="${y}" x2="${w-18}" y2="${y}" stroke="var(--ff-border,#e2e8f0)" stroke-dasharray="4 5"></line><text x="4" y="${y+4}" font-size="9" fill="var(--ff-muted,#94a3b8)">${v}</text></g>`;}).join(''); els.chart.innerHTML=`<svg viewBox="0 0 ${w} ${h}" role="img" aria-label="Execução no período"><defs><linearGradient id="ffadBar" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--ffad-primary)"></stop><stop offset="100%" stop-color="var(--ffad-accent)"></stop></linearGradient></defs>${grids}<line x1="22" y1="198" x2="${w-18}" y2="198" stroke="var(--ff-border,#cbd5e1)"></line>${bars}<polyline points="${points.join(' ')}" fill="none" stroke="var(--ff-heading,#0f172a)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity=".42"></polyline></svg>`; }
  function renderAudiencePerformance(data){
    if(!els.audiencePerformance)return;
    const perf=data&&data.team_performance&&typeof data.team_performance==='object'?data.team_performance:{};
    const members=Array.isArray(perf.members)?perf.members:[],mode=String(perf.mode||'team'),summary=perf.summary||{},leader=perf.leader&&typeof perf.leader==='object'?perf.leader:null,focus=perf.focus&&typeof perf.focus==='object'?perf.focus:null;
    if(!members.length){els.audiencePerformance.innerHTML='<div class="ffad-empty">Sem dados suficientes para calcular performance neste período.</div>';return;}
    const primary=mode==='team'?(leader||members[0]):(focus||members[0]),pct=v=>Number(v||0).toLocaleString('pt-PT',{maximumFractionDigits:1})+'%';
    const focusHtml=`<div class="ffad-performance-focus"><span>${esc(mode==='team'?'Melhor performance no período':'Leitura individual')}</span><h6>${esc(primary.name||'Operativo')}</h6><strong>${esc(Number(primary.performance_index||0).toLocaleString('pt-PT',{maximumFractionDigits:1}))}/100</strong><p>${esc(primary.completed_visits||0)}/${esc(primary.planned_visits||0)} visitas · ${esc(pct(primary.execution_rate))} execução · ${esc(primary.reports||0)} reportes · ${esc(primary.question_responses||0)} respostas</p></div>`;
    const list=(mode==='team'?members.slice(0,8):[primary]).map(m=>`<div class="ffad-performance-row"><span>${mode==='team'?esc(m.position||'•'):'•'}</span><div><strong>${esc(m.name||'Operativo')}</strong><small>${esc(m.completed_visits||0)}/${esc(m.planned_visits||0)} visitas</small></div><div class="ffad-performance-metric"><strong>${esc(Number(m.performance_index||0).toLocaleString('pt-PT',{maximumFractionDigits:1}))}</strong><small>índice</small></div><div class="ffad-performance-metric"><strong>${esc(pct(m.execution_rate))}</strong><small>execução</small></div><div class="ffad-performance-metric"><strong>${esc(m.question_responses||0)}</strong><small>respostas</small></div></div>`).join('');
    const note=mode==='team'?`${Number(summary.members||members.length)} elementos · ${Number(summary.reports||0)} reportes · ${Number(summary.question_responses||0)} respostas de perguntas. ${perf.score_basis||''}`:`${Number(primary.reports||0)} reportes · ${Number(primary.question_responses||0)} respostas. Este modo não expõe nem compara outros membros da equipa.`;
    els.audiencePerformance.innerHTML=`<div class="ffad-performance-layout">${focusHtml}<div class="ffad-performance-list">${list}</div></div><div class="ffad-performance-note">${esc(note)}</div>`;
  }
  function render(data){
    const prev=data.previous_summary||{},rank=data.rankings||{},rows=Array.isArray(data.by_day)?data.by_day:[],mobility=data.mobility_summary||{},perf=data.team_performance||{};
    renderAudiencePerformance(data);
    const canonical=data.canonical_operational_report||{},ck=canonical.kpis||{};
    const mobilityConfigured=Number(mobility.configured_routes||0),mobilityCosted=Number(mobility.costed_routes||0),mobilityIncomplete=Number(mobility.incomplete_distance_routes||0);
    const mobilityAggregate=(key)=>mobilityConfigured>0&&mobilityCosted===0?'Por calcular':(mobilityIncomplete>0?('Parcial '+fmtEur(mobility[key]||0)):fmtEur(mobility[key]||0));
    if(els.reportPeriod)els.reportPeriod.textContent=`${els.from.value||'...'} a ${els.to.value||'...'}`;
    renderCustomAnalytics(data);
    const signed=value=>{const n=Number(value||0);return `${n>0?'+':''}${n.toLocaleString('pt-PT',{maximumFractionDigits:1})}`;};
    const signedPct=value=>{if(value===null||value===undefined||value==='')return'n.d.';const n=Number(value||0);return `${n>0?'+':''}${n.toLocaleString('pt-PT',{maximumFractionDigits:1})}%`;};
    if(canonical&&canonical.contract){
      const visitsPlanned=Number(ck.visits_planned||0),visitsCompleted=Number(ck.visits_completed||0),visitsJustified=Number(ck.visits_justified||0);
      els.stats.innerHTML=[
        kpi('Execucao',(ck.execution_rate||0)+'%',`${visitsCompleted} realizadas / ${visitsPlanned} planeadas · fecho ${ck.operational_closure_rate||0}%`,ck.execution_rate||0),
        kpi('Visitas',`${visitsCompleted}/${visitsPlanned}`,`${visitsJustified} justificadas · ${Number(ck.visits_remaining||0)} por resolver`,ck.execution_rate||0),
        kpi('Ganho de frentes',signed(ck.fronts_gain),`${signedPct(ck.fronts_growth_percent)} / ${ck.fronts_locations_gain||0} lojas com ganho`),
        kpi('Espacos extra',ck.extra_spaces||0,`${ck.extra_space_locations||0} lojas`),
        kpi('Evidencias',ck.evidence_files||0,`${ck.evidence_locations||0} lojas`),
        kpi('Encomendas',ck.order_suggestions||0,`${ck.order_lines||0} linhas / ${Number(ck.order_boxes||0).toLocaleString('pt-PT')} caixas`),
        kpi('Briefing',(ck.briefing_coverage_percent||0)+'%',`${ck.briefing_answered||0}/${ck.briefing_questions||0} perguntas com dados`,ck.briefing_coverage_percent||0),
        kpi('Rotas',`${ck.routes_completed||0}/${ck.routes_total||0}`,'Rotas concluidas',ck.routes_completion_rate||0)
      ].join('');
      els.insights.innerHTML=`<div class="ffad-insight"><span>Routing</span><strong>${esc(ck.execution_rate||0)}%</strong><small>${esc(ck.visits_remaining||0)} visitas por resolver</small></div><div class="ffad-insight"><span>Frentes</span><strong>${esc(signed(ck.fronts_gain))}</strong><small>${esc(signedPct(ck.fronts_growth_percent))} no periodo</small></div><div class="ffad-insight"><span>Espacos extra</span><strong>${esc(ck.extra_spaces||0)}</strong><small>${esc(ck.extra_space_locations||0)} lojas</small></div><div class="ffad-insight"><span>Briefing</span><strong>${esc(ck.briefing_coverage_percent||0)}%</strong><small>Cobertura das perguntas prioritarias</small></div>`;
    }else{
      els.stats.innerHTML=[kpi('Rotas',data.total_routes||0,'Total no periodo'),kpi('Rotas concluidas',data.completed_routes||0,delta(data.completed_routes,prev.completed_routes)+' vs. periodo anterior',data.completion_rate||0),kpi('Paragens',data.total_stops||0,'Visitas planeadas'),kpi('Paragens concluidas',data.done_stops||0,delta(data.done_stops,prev.done_stops)+' vs. periodo anterior',data.done_rate||0),kpi('Execucao',(data.done_rate||0)+'%','Taxa de conclusao',data.done_rate||0)].join('');
      els.insights.innerHTML=`<div class="ffad-insight"><span>Variacao de execucao</span><strong>${esc(delta(data.done_rate,prev.done_rate,'%'))}</strong></div><div class="ffad-insight"><span>Top campanha</span><strong>${esc(rank.top_project||'Sem dados')}</strong></div><div class="ffad-insight"><span>${esc(selfMode?'Âmbito':'Melhor performance')}</span><strong>${esc(selfMode?(perf.focus?.name||'Pessoal'):(perf.leader?.name||rank.top_owner||'Sem dados'))}</strong></div>`;
    }
    const canonicalRoutes=Array.isArray(canonical?.routing?.routes)?canonical.routing.routes:[];
    const routeKey=r=>String(r?.route_id||'')+'|'+String(r?.date||'');
    const canonicalRouteMap=new Map(canonicalRoutes.map(r=>[routeKey(r),r]));
    const chartRows=(canonical&&canonical.contract&&canonicalRoutes.length)?canonicalRoutes.map(r=>({date:r.date||'',done_rate:r.execution_rate||0})):rows;
    renderChart(chartRows);
    const body=rows.map(r=>{const cr=canonicalRouteMap.get(routeKey(r))||{};const execution=canonical&&canonical.contract?(cr.execution_rate??r.done_rate??0):(r.done_rate||0);const closure=canonical&&canonical.contract?(cr.operational_closure_rate??execution):execution;const completed=canonical&&canonical.contract?(cr.completed_stops??r.stops_done??0):(r.stops_done||0);const planned=canonical&&canonical.contract?(cr.planned_stops??r.stops??0):(r.stops||0);return `<tr><td>${esc(r.date||'')}</td><td>${esc(r.project_name||canonical?.scope?.project_name||'')}</td><td>${esc(r.user_name||cr.user_name||'')}</td><td>#${esc(r.route_id||'')}</td><td>${statusBadge(r.route_status||cr.status||'')}</td><td>${esc(planned)}</td><td>${esc(completed)}</td><td><strong>${esc(execution)}%</strong></td><td>${esc(closure)}%</td><td>${esc(cr.path||'')}</td><td>${r.mobility_cost&&Number(r.mobility_cost.enabled||0)===1?(Number(r.mobility_cost.costs_available??1)===1?fmtEur(r.mobility_cost.energy_cost_eur||0):'Por calcular'):'--'}</td><td>${r.mobility_cost&&Number(r.mobility_cost.enabled||0)===1?(Number(r.mobility_cost.costs_available??1)===1?fmtEur(r.mobility_cost.total_mobility_cost_eur||0):'Por calcular'):(Number(r.mobility_cost?.costs_available??1)===1?fmtEur(r.mobility_cost?.toll_cost_eur||0):'Por calcular')}</td></tr>`;}).join('');
    els.table.innerHTML=body||'<tr><td colspan="12">Sem rotas no periodo selecionado.</td></tr>';
    els.mobile.innerHTML=rows.map(r=>{const cr=canonicalRouteMap.get(routeKey(r))||{};const execution=canonical&&canonical.contract?(cr.execution_rate??r.done_rate??0):(r.done_rate||0);const closure=canonical&&canonical.contract?(cr.operational_closure_rate??execution):execution;const completed=canonical&&canonical.contract?(cr.completed_stops??r.stops_done??0):(r.stops_done||0);const planned=canonical&&canonical.contract?(cr.planned_stops??r.stops??0):(r.stops||0);return `<article class="ffad-route-card"><div class="ffad-route-card-head"><div><h5>${esc(r.project_name||canonical?.scope?.project_name||'Rota')} · #${esc(r.route_id||'')}</h5><p>${esc(r.date||'')} · ${esc(r.user_name||cr.user_name||'')}</p></div>${statusBadge(r.route_status||cr.status||'')}</div><div class="ffad-route-meta"><div><strong>${esc(planned)}</strong><small>Planeadas</small></div><div><strong>${esc(completed)}</strong><small>Realizadas</small></div><div><strong>${esc(execution)}%</strong><small>Execucao</small></div><div><strong>${esc(closure)}%</strong><small>Fecho</small></div></div>${cr.path?`<p class="ffad-route-path"><strong>Percurso:</strong> ${esc(cr.path)}</p>`:''}</article>`;}).join('')||'<div class="ffad-empty">Sem rotas no periodo selecionado.</div>';
    els.note.textContent=`${rows.length} rota${rows.length===1?'':'s'} entre ${els.from.value} e ${els.to.value}.`;
    refreshExportLinks();
  }
  async function load(){ askSyncScope(); els.stats.innerHTML='<div class="ffad-empty">A carregar analytics...</div>'; const data=await safeFetch(api+'stats?'+params().toString()); if(!data){els.stats.innerHTML='<div class="ffad-empty">Não foi possível carregar os dados.</div>';return;} render(data); box.dispatchEvent(new CustomEvent('fieldflow:analytics-rendered',{bubbles:true,detail:{contract:'fieldflow-operational-modernization-wave2-v1'}})); loadAI(false); }
  function exportUrl(format){ const p=params(); const scope=analyticsHeaderDateScope(); if(scope.from)p.set('from',scope.from); else p.delete('from'); if(scope.to)p.set('to',scope.to); else p.delete('to'); p.set('action','routespro_export_dashboard'); p.set('_wpnonce',exportNonce); p.set('format',format); if(selfMode){p.delete('user_id');p.set('self_mode','1');} return '<?php echo esc_url(admin_url('admin-post.php')); ?>?'+p.toString(); }
  function refreshExportLinks(){const links=[[els.csv,'csv'],[els.xls,'xlsx'],[els.xlsMobile,'xlsx_mobile'],[els.pdf,'pdf']];links.forEach(([link,format])=>{if(!link)return;link.href=exportUrl(format);link.removeAttribute('target');link.setAttribute('rel','noopener');});}
  function applyPeriodPreset(key){const now=new Date(),day=(now.getDay()+6)%7;let a=new Date(now),b=new Date(now);if(key==='current_week'){a.setDate(now.getDate()-day);b=new Date(now);}else if(key==='previous_week'){b.setDate(now.getDate()-day-1);a=new Date(b);a.setDate(b.getDate()-6);}else{a=new Date(now.getFullYear(),now.getMonth(),1);b=new Date(now);}els.from.value=iso(a);els.to.value=iso(b);syncAnalyticsExportDateAuthority();load();}
  box.querySelectorAll('[data-period-preset]').forEach(btn=>btn.addEventListener('click',()=>applyPeriodPreset(btn.dataset.periodPreset||'current_week')));
  box.addEventListener('click',async ev=>{const btn=ev.target.closest('[data-ffad-evidence-download]');if(!btn||!box.contains(btn))return;ev.preventDefault();ev.stopPropagation();const group=analyticsEvidenceGroups[String(btn.dataset.ffadEvidenceDownload||'')];if(!group||!group.token)return;const original=btn.textContent;btn.disabled=true;btn.textContent='A preparar ZIP...';try{const response=await fetch(api+'analytics/evidence-archive',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce,'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({token:group.token})});const text=await response.text();let data={};try{data=text?JSON.parse(text):{};}catch(_){data={message:text};}if(!response.ok||!data||!data.url)throw new Error(String(data?.message||data?.code||('HTTP '+response.status)));const filename=String(data.filename||'evidencias-fieldflow.zip');let handedOff=false;if(window.FieldFlowNativeBridge&&typeof window.FieldFlowNativeBridge.downloadPrepared==='function'){handedOff=await window.FieldFlowNativeBridge.downloadPrepared(String(data.url),filename,{kind:'evidence_zip'});if(!handedOff)throw new Error('O dispositivo nao confirmou a abertura do ZIP.');}else{const link=document.createElement('a');link.href=String(data.url);link.download=filename;link.className='ffad-evidence-prepared-download';link.setAttribute('data-fieldflow-download-fallback','1');link.style.display='none';document.body.appendChild(link);link.click();handedOff=true;setTimeout(()=>link.remove(),5000);}btn.textContent=Number(data.skipped||0)>0?'ZIP aberto · '+Number(data.count||0)+' ficheiro(s)':'ZIP aberto para download';setTimeout(()=>{btn.textContent=original;btn.disabled=false;},2200);}catch(err){const detail=String(err?.message||'Não foi possível preparar o ZIP.').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();btn.textContent='ZIP indisponível · tenta novamente';btn.setAttribute('title',detail);setTimeout(()=>{btn.textContent=original;btn.disabled=false;btn.removeAttribute('title');},3600);}});
  box.addEventListener('click',ev=>{const btn=ev.target.closest('[data-ffad-evidence-tab]');if(!btn)return;const shell=btn.closest('[data-ffad-evidence-shell]');if(!shell)return;const tab=String(btn.dataset.ffadEvidenceTab||'summary');shell.querySelectorAll('[data-ffad-evidence-tab]').forEach(node=>{const active=node===btn;node.classList.toggle('is-active',active);node.setAttribute('aria-selected',active?'true':'false');});shell.querySelectorAll('[data-ffad-evidence-panel]').forEach(panel=>panel.classList.toggle('is-active',String(panel.dataset.ffadEvidencePanel||'')===tab));});
  box.addEventListener('click',ev=>{const tab=ev.target.closest('[data-ffad-fronts-tab]');if(tab){const key=String(tab.dataset.ffadFrontsKey||''),value=String(tab.dataset.ffadFrontsTab||'all');analyticsUi.tabs[key]=value;analyticsUi.pages[key]=1;renderCustomAnalytics(currentAnalyticsData||{});return;}const pageBtn=ev.target.closest('[data-ffad-page]');if(pageBtn&&!pageBtn.disabled){const pager=pageBtn.closest('[data-ffad-page-key]'),key=String(pager?.dataset.ffadPageKey||'');analyticsUi.pages[key]=parseInt(pageBtn.dataset.ffadPage||'1',10)||1;renderCustomAnalytics(currentAnalyticsData||{});}});
  box.addEventListener('change',ev=>{const select=ev.target.closest('[data-ffad-page-size]');if(!select)return;const key=String(select.dataset.ffadPageSize||'');analyticsUi.sizes[key]=parseInt(select.value||'10',10)||10;analyticsUi.pages[key]=1;renderCustomAnalytics(currentAnalyticsData||{});});
  if(els.aiRefresh)els.aiRefresh.addEventListener('click',()=>loadAI(true));
  if(els.askForm)els.askForm.addEventListener('submit',ev=>{ev.preventDefault();askFieldFlow(els.askInput?.value||'');});
  if(els.askClear)els.askClear.addEventListener('click',askReset);
  if(els.askBox)els.askBox.addEventListener('click',ev=>{const btn=ev.target.closest('[data-ffad-ask-question]');if(!btn)return;ev.preventDefault();const value=String(btn.dataset.ffadAskQuestion||'');if(els.askInput)els.askInput.value=value;askFieldFlow(value);});
  if(els.askInput)els.askInput.addEventListener('keydown',ev=>{if((ev.ctrlKey||ev.metaKey)&&ev.key==='Enter'){ev.preventDefault();askFieldFlow(els.askInput.value);}});
  [[els.csv,'csv'],[els.xls,'xlsx'],[els.xlsMobile,'xlsx_mobile'],[els.pdf,'pdf']].forEach(([link])=>{if(!link)return;link.addEventListener('pointerdown',syncAnalyticsExportDateAuthority,{passive:true});link.addEventListener('touchstart',syncAnalyticsExportDateAuthority,{passive:true});});
  els.project.addEventListener('change',async()=>{await loadRoles();await loadUsers();await load();}); els.role.addEventListener('change',async()=>{await loadUsers();await load();}); els.user.addEventListener('change',load); els.from.addEventListener('input',syncAnalyticsExportDateAuthority); els.to.addEventListener('input',syncAnalyticsExportDateAuthority); els.from.addEventListener('change',()=>{syncAnalyticsExportDateAuthority();load();}); els.to.addEventListener('change',()=>{syncAnalyticsExportDateAuthority();load();});
  box.addEventListener('routespro:panel-open',load); box.addEventListener('routespro:scope-change',load);
  loadProjects().then(loadRoles).then(loadUsers).then(load);
})();
</script>
        <?php
        return ob_get_clean();
    }

    public static function route_change_form($atts = []) {
        if (!is_user_logged_in()) return '<p>Inicia sessão.</p>';
        $nonce    = wp_create_nonce('wp_rest');
        $route_id = intval($atts['route_id'] ?? 0);
        if (!$route_id) return '<p>Falta route_id.</p>';

        $ap  = \RoutesPro\Admin\Appearance::get();
        $c1  = !empty($ap['primary_transparent']) ? '#1d4ed8' : ($ap['primary_color'] ?? '#1d4ed8');
        $c2  = !empty($ap['accent_transparent'])  ? '#0f766e' : ($ap['accent_color'] ?? '#0f766e');
        $cbg = !empty($ap['bg_transparent'])      ? '#f8fafc' : ($ap['bg_color'] ?? '#f8fafc');
        $ff  = $ap['font_family'] ?? 'inherit';
        $fz  = intval($ap['font_size_px'] ?? 16);

        ob_start(); ?>
<style>
#rp-change{--rp-primary:<?php echo esc_html($c1); ?>;--rp-accent:<?php echo esc_html($c2); ?>;--rp-bg:<?php echo esc_html($cbg); ?>;font-family:<?php echo esc_html($ff); ?>;font-size:<?php echo esc_html($fz); ?>px;color:#0f172a}
#rp-change *{box-sizing:border-box}
#rp-change .rpw-shell{display:grid;gap:16px}
#rp-change .rpw-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 10px 28px rgba(15,23,42,.06)}
#rp-change .rpw-head{display:flex;gap:14px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
#rp-change h3,#rp-change h4{margin:0;color:#0f172a}
#rp-change .rpw-sub{margin:5px 0 0;color:#64748b}
#rp-change .rpw-badge{display:inline-flex;align-items:center;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:800;background:#dbeafe;color:#1e40af}
#rp-change .rpw-badge.is-draft{background:#fef3c7;color:#92400e}
#rp-change .rpw-badge.is-locked{background:#fee2e2;color:#991b1b}
#rp-change .rpw-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:16px}
#rp-change .rpw-stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:12px}
#rp-change .rpw-stat span{display:block;color:#64748b;font-size:12px}
#rp-change .rpw-stat strong{display:block;margin-top:4px;font-size:18px}
#rp-change .rpw-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
#rp-change button{border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:10px;padding:9px 12px;font-weight:750;cursor:pointer}
#rp-change button:hover:not(:disabled){border-color:var(--rp-primary);color:var(--rp-primary)}
#rp-change button.rpw-primary{background:var(--rp-primary);border-color:var(--rp-primary);color:#fff}
#rp-change button.rpw-accent{background:var(--rp-accent);border-color:var(--rp-accent);color:#fff}
#rp-change button.rpw-danger{border-color:#fecaca;color:#b91c1c;background:#fff7f7}
#rp-change button:disabled{opacity:.48;cursor:not-allowed}
#rp-change .rpw-select-all{display:inline-flex;gap:7px;align-items:center;margin-right:auto;font-weight:700;color:#475569}
#rp-change .rpw-list{list-style:none;margin:0;padding:0;display:grid;gap:9px}
#rp-change .rpw-stop{display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;align-items:center;gap:11px;border:1px solid #e2e8f0;background:#fff;border-radius:14px;padding:12px;transition:.15s ease}
#rp-change .rpw-stop[draggable=true]{cursor:grab}
#rp-change .rpw-stop.is-dragging{opacity:.45;border-color:var(--rp-primary)}
#rp-change .rpw-handle{font-size:19px;color:#94a3b8;user-select:none}
#rp-change .rpw-index{width:28px;height:28px;border-radius:9px;background:#eff6ff;color:#1d4ed8;display:grid;place-items:center;font-weight:900}
#rp-change .rpw-stop strong{display:block}
#rp-change .rpw-stop small{display:block;color:#64748b;margin-top:3px}
#rp-change .rpw-pin.is-active{background:#ecfeff;border-color:#5eead4;color:#0f766e}
#rp-change .rpw-message{border-radius:12px;padding:11px 13px;background:#eff6ff;color:#1e3a8a;margin-top:12px}
#rp-change .rpw-message.is-error{background:#fef2f2;color:#991b1b}
#rp-change .rpw-message.is-success{background:#ecfdf5;color:#065f46}
#rp-change .rpw-compare{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:12px}
#rp-change .rpw-compare>div{border:1px solid #dbeafe;background:#f8fbff;border-radius:13px;padding:12px}
#rp-change .rpw-history{display:grid;gap:8px;margin-top:12px}
#rp-change .rpw-revision{display:flex;gap:10px;align-items:center;justify-content:space-between;padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px}
#rp-change .rpw-revision small{color:#64748b}
@media(max-width:720px){#rp-change .rpw-stats{grid-template-columns:repeat(2,minmax(0,1fr))}#rp-change .rpw-compare{grid-template-columns:1fr}#rp-change .rpw-stop{grid-template-columns:auto auto minmax(0,1fr)}#rp-change .rpw-pin{grid-column:2/4;width:100%}}
</style>
<div id="rp-change" data-route="<?php echo esc_attr($route_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
  <div class="rpw-card">A carregar o workspace da rota…</div>
</div>
<script>
(async function(){
  const root=document.getElementById('rp-change'); if(!root)return;
  const routeId=parseInt(root.dataset.route||'0',10), nonce=root.dataset.nonce||'';
  const api='<?php echo esc_url(rest_url('routespro/v1/')); ?>';
  let det=null, stops=[], selected=new Set(), lockedLocations=new Set(), manualOrderLocked=false, preview=null, dragId=0;
  const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  async function request(path,options={}){const response=await fetch(api+path,{credentials:'same-origin',...options,headers:{'X-WP-Nonce':nonce,...(options.headers||{})}});const text=await response.text();let data={};try{data=text?JSON.parse(text):{}}catch(_){data={message:text}}if(!response.ok)throw new Error(data.message||'O pedido não foi concluído.');return data;}
  function routeEditable(){if(!det||!['draft','planned'].includes(String(det.status||'')))return false;return !stops.some(stop=>String(stop.arrived_at||'')||String(stop.departed_at||'')||!['pending','planned','draft'].includes(String(stop.status||'pending')));}
  function statusLabel(){if(!routeEditable())return 'Planeamento bloqueado';return String(det?.status||'draft')==='draft'?'Rascunho':'Planeada';}
  function fmtKm(value){const number=Number(value||0);return Number.isFinite(number)?number.toLocaleString('pt-PT',{maximumFractionDigits:1})+' km':'0 km';}
  function fmtMin(value){const total=Math.max(0,Math.round(Number(value||0)));const h=Math.floor(total/60),m=total%60;return h?`${h}h ${m}m`:`${m}m`;}
  function message(text,tone=''){const el=root.querySelector('[data-rpw-message]');if(!el)return;el.textContent=text||'';el.className='rpw-message'+(tone?' is-'+tone:'');}
  function history(){const rows=Array.isArray(det?.meta?.route_workspace_history)?det.meta.route_workspace_history.slice().reverse().slice(0,6):[];if(!rows.length)return '<p class="rpw-sub">O histórico começa a ser criado quando guardares alterações neste workspace.</p>';return `<div class="rpw-history">${rows.map(row=>`<div class="rpw-revision"><div><strong>${esc(row.label||row.action||'Versão da rota')}</strong><small>${esc(row.created_at||'')} · ${parseInt(row.stop_count||0,10)} loja(s)</small></div><button type="button" data-restore="${esc(row.id||'')}" ${routeEditable()?'':'disabled'}>Restaurar</button></div>`).join('')}</div>`;}
  function compareHtml(){if(!preview)return '<p class="rpw-sub">Compara a ordem atual com uma alternativa otimizada antes de aplicares alterações.</p>';const base=preview.baseline||{},opt=preview.optimized||{};return `<div class="rpw-compare"><div><span>Rota atual</span><strong>${fmtKm(base.distance_km)}</strong><small>${fmtMin(base.travel_min)}</small></div><div><span>Rota otimizada</span><strong>${fmtKm(opt.distance_km)}</strong><small>${fmtMin(opt.travel_min)}</small></div><div><span>Poupança estimada</span><strong>${fmtKm(preview.saving_km)}</strong><small>${fmtMin(preview.saving_minutes)}</small></div></div>`;}
  function render(){
    const editable=routeEditable(), allSelected=stops.length>0&&stops.every(stop=>selected.has(parseInt(stop.id,10)));
    const distance=preview?.optimized?.distance_km??det?.distance_km??det?.route_metrics?.distance_km??0;
    const travel=preview?.optimized?.travel_min??det?.travel_min??det?.route_metrics?.travel_min??0;
    root.innerHTML=`<div class="rpw-shell">
      <section class="rpw-card"><div class="rpw-head"><div><h3>Workspace da rota</h3><p class="rpw-sub">${esc(det?.project_name||det?.client_name||'Rota')} · ${esc(det?.date||'')}</p></div><span class="rpw-badge ${!editable?'is-locked':(String(det?.status)==='draft'?'is-draft':'')}">${statusLabel()}</span></div><div class="rpw-stats"><div class="rpw-stat"><span>Lojas</span><strong>${stops.length}</strong></div><div class="rpw-stat"><span>Selecionadas</span><strong>${selected.size}</strong></div><div class="rpw-stat"><span>Distância</span><strong>${fmtKm(distance)}</strong></div><div class="rpw-stat"><span>Viagem</span><strong>${fmtMin(travel)}</strong></div></div></section>
      <section class="rpw-card"><div class="rpw-toolbar"><label class="rpw-select-all"><input type="checkbox" data-select-all ${allSelected?'checked':''} ${editable?'':'disabled'}> Selecionar todas</label><button type="button" class="rpw-danger" data-remove-selected ${selected.size&&editable?'':'disabled'}>Remover selecionadas</button><button type="button" data-compare ${stops.length>1&&editable?'':'disabled'}>Comparar otimização</button><button type="button" class="rpw-accent" data-apply ${preview&&editable?'':'disabled'}>Aplicar otimização</button><button type="button" data-save-draft ${editable?'':'disabled'}>Guardar rascunho</button><button type="button" class="rpw-primary" data-publish ${editable?'':'disabled'}>Publicar rota</button></div>
      <ol class="rpw-list">${stops.map((stop,index)=>{const id=parseInt(stop.id,10),locationId=parseInt(stop.location_id||0,10),pinned=lockedLocations.has(locationId);return `<li class="rpw-stop" data-stop="${id}" data-location="${locationId}" draggable="${editable?'true':'false'}"><input type="checkbox" data-select="${id}" ${selected.has(id)?'checked':''} ${editable?'':'disabled'}><span class="rpw-handle" title="Arrastar">⋮⋮</span><div><span class="rpw-index">${index+1}</span><strong>${esc(stop.location_name||'Loja')}</strong><small>${esc(stop.address||'Sem morada')} · ${esc(stop.status||'pending')}</small></div><button type="button" class="rpw-pin ${pinned?'is-active':''}" data-pin="${locationId}" ${editable?'':'disabled'}>${pinned?'Posição fixa':'Fixar posição'}</button></li>`;}).join('')}</ol>
      <div class="rpw-message" data-rpw-message>${editable?'Arrasta as lojas, fixa posições ou compara uma alternativa otimizada.':'A rota já começou. A ordem e as lojas ficam protegidas para preservar a operação.'}</div></section>
      <section class="rpw-card"><h4>Comparação</h4>${compareHtml()}</section>
      <section class="rpw-card"><h4>Histórico recuperável</h4>${history()}</section>
    </div>`;
    bind();
  }
  function bind(){
    root.querySelector('[data-select-all]')?.addEventListener('change',event=>{selected.clear();if(event.target.checked)stops.forEach(stop=>selected.add(parseInt(stop.id,10)));render();});
    root.querySelectorAll('[data-select]').forEach(input=>input.addEventListener('change',()=>{const id=parseInt(input.dataset.select||'0',10);input.checked?selected.add(id):selected.delete(id);render();}));
    root.querySelectorAll('[data-pin]').forEach(button=>button.addEventListener('click',()=>{const id=parseInt(button.dataset.pin||'0',10);lockedLocations.has(id)?lockedLocations.delete(id):lockedLocations.add(id);preview=null;render();message('Posições fixas atualizadas. Guarda o workspace para confirmar.');}));
    root.querySelectorAll('.rpw-stop').forEach(row=>{row.addEventListener('dragstart',()=>{dragId=parseInt(row.dataset.stop||'0',10);row.classList.add('is-dragging');});row.addEventListener('dragend',()=>row.classList.remove('is-dragging'));row.addEventListener('dragover',event=>event.preventDefault());row.addEventListener('drop',event=>{event.preventDefault();const target=parseInt(row.dataset.stop||'0',10);if(!dragId||!target||dragId===target)return;const from=stops.findIndex(stop=>parseInt(stop.id,10)===dragId),to=stops.findIndex(stop=>parseInt(stop.id,10)===target);if(from<0||to<0)return;const moved=stops.splice(from,1)[0];stops.splice(to,0,moved);manualOrderLocked=true;preview=null;render();message('Ordem manual alterada e protegida. Guarda o workspace para confirmar.');});});
    root.querySelector('[data-remove-selected]')?.addEventListener('click',removeSelected);
    root.querySelector('[data-compare]')?.addEventListener('click',compareRoute);
    root.querySelector('[data-apply]')?.addEventListener('click',applyOptimization);
    root.querySelector('[data-save-draft]')?.addEventListener('click',()=>saveWorkspace('draft'));
    root.querySelector('[data-publish]')?.addEventListener('click',()=>saveWorkspace('planned'));
    root.querySelectorAll('[data-restore]').forEach(button=>button.addEventListener('click',()=>restoreRevision(button.dataset.restore||'')));
  }
  async function reload(){det=await request('routes/'+routeId+'?v='+Date.now());stops=Array.isArray(det.stops)?det.stops.slice():[];lockedLocations=new Set((det.meta?.position_locked_location_ids||[]).map(value=>parseInt(value,10)).filter(Boolean));manualOrderLocked=!!det.meta?.manual_order_locked;selected.clear();preview=null;render();}
  async function saveWorkspace(status){try{message(status==='draft'?'A guardar rascunho…':'A publicar a rota…');if(status==='planned'&&preview&&Number(preview?.optimized?.travel_min||0)>480&&!window.confirm('A estimativa ultrapassa 8 horas de viagem. Queres publicar na mesma?'))return message('Publicação cancelada.');const result=await request('routes/'+routeId+'/workspace',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({status,stop_ids:stops.map(stop=>parseInt(stop.id,10)),manual_order_locked:manualOrderLocked?1:0,locked_location_ids:Array.from(lockedLocations)})});det.status=result.status||status;det.meta=det.meta||{};det.meta.manual_order_locked=result.manual_order_locked||0;det.meta.position_locked_location_ids=result.locked_location_ids||[];await reload();message(status==='draft'?'Rascunho guardado com sucesso.':'Rota publicada com sucesso.','success');}catch(error){message(error.message,'error');}}
  async function removeSelected(){if(!selected.size)return;if(!window.confirm(`Remover ${selected.size} loja(s) desta rota? O histórico permitirá recuperar a versão anterior.`))return;try{message('A remover lojas selecionadas…');const ids=Array.from(selected);const removedLocations=stops.filter(stop=>ids.includes(parseInt(stop.id,10))).map(stop=>parseInt(stop.location_id||0,10));await request('routes/'+routeId+'/stops/bulk-remove',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({stop_ids:ids})});stops=stops.filter(stop=>!selected.has(parseInt(stop.id,10)));removedLocations.forEach(id=>lockedLocations.delete(id));selected.clear();manualOrderLocked=true;preview=null;await reload();message('Lojas removidas e versão anterior guardada no histórico.','success');}catch(error){message(error.message,'error');}}
  async function compareRoute(){try{message('A comparar a rota atual com uma alternativa otimizada…');preview=await request('route-optimization/preview',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({route_id:routeId,location_ids:stops.map(stop=>parseInt(stop.location_id||0,10)),start_point:det.meta?.start_point||{},end_point:det.meta?.end_point||{},return_to_start:det.meta?.return_to_start?1:0,manual_order_locked:0,locked_location_ids:Array.from(lockedLocations),mode:'internal'})});render();message('Comparação concluída. A rota atual ainda não foi alterada.','success');}catch(error){preview=null;render();message(error.message,'error');}}
  async function applyOptimization(){if(!preview)return;try{message('A aplicar a ordem otimizada…');const result=await request('routes/'+routeId+'/optimize',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({mode:'internal',manual_order_locked:0,locked_location_ids:Array.from(lockedLocations)})});const lookup=new Map(stops.map(stop=>[parseInt(stop.location_id||0,10),stop]));const ordered=(result.ordered_location_ids||[]).map(id=>lookup.get(parseInt(id,10))).filter(Boolean);if(ordered.length===stops.length)stops=ordered;manualOrderLocked=false;preview=result;render();message('Otimização aplicada e guardada.','success');}catch(error){message(error.message,'error');}}
  async function restoreRevision(revisionId){if(!revisionId||!window.confirm('Restaurar esta versão da rota? O estado atual também ficará guardado no histórico.'))return;try{message('A restaurar a versão selecionada…');await request('routes/'+routeId+'/workspace/restore',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({revision_id:revisionId})});await reload();message('Versão restaurada com sucesso.','success');}catch(error){message(error.message,'error');}}
  try{await reload();}catch(error){root.innerHTML=`<div class="rpw-card"><div class="rpw-message is-error">${esc(error.message)}</div></div>`;}
})();
</script>
<?php
        return ob_get_clean();
    }

    public static function app($atts = []) {
        return \RoutesPro\Front\AppShortcodeRenderer::app($atts);
    }
    public static function expenses($atts = []) {
        return \RoutesPro\Front\ExpensesShortcodeRenderer::render($atts);
    }
    public static function briefings($atts = []) {
        return \RoutesPro\Front\BriefingsShortcodeRenderer::team((array)$atts);
    }
    public static function client_briefings($atts = []) {
        return \RoutesPro\Front\BriefingsShortcodeRenderer::client((array)$atts);
    }
    public static function implementations($atts = []) {
        return \RoutesPro\Front\ImplementationsShortcodeRenderer::team((array)$atts);
    }
    public static function client_implementations($atts = []) {
        return \RoutesPro\Front\ImplementationsShortcodeRenderer::client((array)$atts);
    }
    public static function stocks($atts = []) {
        return \RoutesPro\Front\StocksShortcodeRenderer::team((array)$atts);
    }
    public static function client_stocks($atts = []) {
        return \RoutesPro\Front\StocksShortcodeRenderer::client((array)$atts);
    }
    public static function shopping($atts = []) {
        return \RoutesPro\Front\ShoppingShortcodeRenderer::render((array)$atts);
    }
    public static function shopping_results($atts = []) {
        return \RoutesPro\Front\ShoppingResultsShortcodeRenderer::render((array)$atts);
    }

    public static function route_today($atts = []) {
        return self::my_daily_route($atts);
    }

    public static function discovery($atts = []) {
        return self::front_routes($atts);
    }

    public static function checkin($atts = []) {
        $atts['mode'] = 'checkin';
        return self::report_visit($atts);
    }


    public static function client_team_mail($atts = []) {
        $guard = self::front_guard();
        if ($guard) return $guard;
        $theme = self::front_theme();
        $nonce = wp_create_nonce('wp_rest');
        $scope = \RoutesPro\Support\Permissions::get_scope();
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $clients = \RoutesPro\Support\Permissions::filter_clients($wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: []);
        $projects = \RoutesPro\Support\Permissions::filter_projects($wpdb->get_results("SELECT id,client_id,name FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: []);
        $client_id = absint($atts['client_id'] ?? ($_GET['client_id'] ?? 0));
        $project_id = absint($atts['project_id'] ?? ($_GET['project_id'] ?? 0));
        [$client_id, $project_id] = \RoutesPro\Support\Permissions::sanitize_scope_selection($client_id, $project_id);
        ob_start(); ?>
<style>
.rp-team-mail{font-family:<?php echo esc_attr($theme['font']); ?>;font-size:<?php echo esc_attr($theme['size']); ?>px}
.rp-team-mail .rp-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.rp-team-mail .rp-card{background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:18px;box-shadow:0 12px 26px rgba(15,23,42,.06)}
.rp-team-mail label{display:block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:6px}
.rp-team-mail input,.rp-team-mail select,.rp-team-mail textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:12px;background:#fff}.rp-team-mail .rp-attachments a{display:inline-flex;margin:4px 8px 4px 0}
.rp-team-mail textarea{min-height:180px;resize:vertical}
.rp-team-mail .rp-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:16px}
.rp-team-mail .rp-btn{border:0;border-radius:14px;padding:12px 16px;font-weight:800;cursor:pointer;background:<?php echo esc_attr($theme['primary']); ?>;color:#fff;transition:all .18s ease}
.rp-team-mail .rp-btn.alt{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
.rp-team-mail .rp-btn:hover,.rp-team-mail .rp-btn:focus,.rp-team-mail .rp-btn:active{background:<?php echo esc_attr($theme['primary']); ?> !important;color:#fff !important;outline:none;box-shadow:0 0 0 3px rgba(15,23,42,.10)}
.rp-team-mail .rp-btn.alt:hover,.rp-team-mail .rp-btn.alt:focus,.rp-team-mail .rp-btn.alt:active{background:#fff !important;color:#0f172a !important;border-color:#cbd5e1 !important;outline:none;box-shadow:0 0 0 3px rgba(15,23,42,.08)}
.rp-team-mail .rp-note{padding:12px 14px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;min-height:52px;display:flex;align-items:center}
.rp-team-mail .rp-note.is-success{background:#ecfdf5;border-color:#86efac;color:#166534}
.rp-team-mail .rp-note.is-error{background:#fef2f2;border-color:#fca5a5;color:#991b1b}
.rp-team-mail .rp-note.is-info{background:#eff6ff;border-color:#93c5fd;color:#1d4ed8}
.rp-team-mail .rp-mini{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.rp-team-mail .rp-mini button{border:1px solid <?php echo esc_attr($theme['primary']); ?>;background:<?php echo esc_attr($theme['primary']); ?>;color:#fff;border-radius:999px;padding:8px 12px;cursor:pointer;font-weight:700}.rp-team-mail .rp-mini button:nth-child(even){background:<?php echo esc_attr($theme['accent']); ?>;border-color:<?php echo esc_attr($theme['accent']); ?>}.rp-team-mail .rp-mini button:hover{opacity:.92}
.rp-team-mail .rp-history{display:flex;flex-direction:column;gap:10px;max-height:420px;overflow:auto}.rp-team-mail .rp-history-item{border:1px solid #e2e8f0;border-radius:16px;padding:14px;background:#fff}
.rp-team-mail .rp-pill{display:inline-flex;padding:5px 10px;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;font-size:12px;font-weight:700}
.rp-team-mail select.ff-locked{background:#f8fafc!important;color:#475569!important;cursor:not-allowed!important;opacity:1!important}
@media (max-width: 860px){.rp-team-mail .rp-grid{grid-template-columns:1fr}}
</style>
<div class="rp-team-mail" id="routespro-client-team-mail" data-nonce="<?php echo esc_attr($nonce); ?>" data-client-id="<?php echo esc_attr($client_id); ?>" data-project-id="<?php echo esc_attr($project_id); ?>" data-projects='<?php echo esc_attr(wp_json_encode($projects)); ?>' data-current-user="<?php echo esc_attr(get_current_user_id()); ?>" data-current-user-label="<?php echo esc_attr(wp_get_current_user()->display_name ?: wp_get_current_user()->user_login ?: 'Meu utilizador'); ?>">
  <div class="rp-grid">
    <div class="rp-card">
      <h3 style="margin:0 0 6px">Contacto operacional com a equipa</h3>
      <p style="margin:0 0 16px;color:#64748b">Envia medidas corretivas, preventivas, informação geral ou pedidos operacionais diretamente ao owner certo da campanha, sem expor a equipa toda.</p>
      <div class="rp-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
        <div>
          <label for="rpctm-client">Cliente</label>
          <select id="rpctm-client"><option value="">Selecionar</option><?php foreach($clients as $c): ?><option value="<?php echo intval($c['id']); ?>" <?php selected($client_id, intval($c['id'])); ?>><?php echo esc_html($c['name']); ?></option><?php endforeach; ?></select>
        </div>
        <div>
          <label for="rpctm-project">Campanha</label>
          <select id="rpctm-project"><option value="">Selecionar</option></select>
        </div>
        <div>
          <label for="rpctm-sender">Enviar como</label>
          <select id="rpctm-sender"><option value="">Selecionar utilizador cliente</option></select>
        </div>
        <div>
          <label for="rpctm-owner">Enviar para</label>
          <select id="rpctm-owner"><option value="">Selecionar operativo da campanha</option></select>
        </div>
        <div>
          <label for="rpctm-kind">Tipo de mensagem</label>
          <select id="rpctm-kind">
            <option value="corretiva">Medida corretiva</option>
            <option value="preventiva">Medida preventiva</option>
            <option value="informacao">Informação</option>
            <option value="geral">Geral</option>
          </select>
        </div>
        <div>
          <label for="rpctm-priority">Prioridade</label>
          <select id="rpctm-priority"><option value="normal">Normal</option><option value="alta">Alta</option><option value="critica">Crítica</option></select>
        </div>
        <div>
          <label>&nbsp;</label>
          <label style="display:flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:14px"><input type="checkbox" id="rpctm-copy" checked style="width:auto"> Receber cópia no meu email</label>
        </div>
        <div style="grid-column:1/-1">
          <label for="rpctm-subject">Assunto</label>
          <input type="text" id="rpctm-subject" placeholder="Ex.: Ajuste preventivo de rota na zona norte">
        </div>
        <div style="grid-column:1/-1">
          <label for="rpctm-message">Mensagem</label>
          <textarea id="rpctm-message" placeholder="Explica o contexto, a medida desejada e a urgência."></textarea>
        </div>
        <div style="grid-column:1/-1">
          <label for="rpctm-files">Anexar fotos ou ficheiros</label>
          <input type="file" id="rpctm-files" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
        </div>
      </div>
      <div class="rp-mini">
        <button type="button" data-template="corretiva">Template corretivo</button>
        <button type="button" data-template="preventiva">Template preventivo</button>
        <button type="button" data-template="informacao">Template informativo</button>
        <button type="button" data-template="geral">Template geral</button>
      </div>
      <div class="rp-actions">
        <button type="button" class="rp-btn" id="rpctm-send">Enviar email</button>
        <button type="button" class="rp-btn alt" id="rpctm-clear">Limpar</button>
      </div>
      <div class="rp-note" id="rpctm-status" style="margin-top:14px">Seleciona primeiro a campanha para veres apenas os owners relevantes.</div>
    </div>
    <div class="rp-card">
      <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px">
        <div><h3 style="margin:0 0 4px">Histórico recente</h3><p style="margin:0;color:#64748b">Últimas mensagens enviadas pelo portal, para o cliente manter contexto sem sair da página.</p></div>
        <span class="rp-pill">Email + rastreio</span>
      </div>
      <div class="rp-history" id="rpctm-history"><div class="rp-note">O histórico fica disponível assim que houver emails registados.</div></div>
    </div>
  </div>
</div>
<script>
(function(){
  const root=document.getElementById('routespro-client-team-mail'); if(!root) return;
  const ajax='<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
  const projects=JSON.parse(root.dataset.projects||'[]');
  let isSending=false;
  const els={client:root.querySelector('#rpctm-client'),project:root.querySelector('#rpctm-project'),sender:root.querySelector('#rpctm-sender'),owner:root.querySelector('#rpctm-owner'),kind:root.querySelector('#rpctm-kind'),priority:root.querySelector('#rpctm-priority'),subject:root.querySelector('#rpctm-subject'),message:root.querySelector('#rpctm-message'),copy:root.querySelector('#rpctm-copy'),send:root.querySelector('#rpctm-send'),clear:root.querySelector('#rpctm-clear'),status:root.querySelector('#rpctm-status'),history:root.querySelector('#rpctm-history'),files:root.querySelector('#rpctm-files')};
  const templates={corretiva:{subject:'Medida corretiva para campanha',body:'Olá,\n\nPrecisamos de uma ação corretiva na campanha/rota indicada.\n\nContexto:\n- \n\nAção pretendida:\n- \n\nObrigado.'},preventiva:{subject:'Medida preventiva para campanha',body:'Olá,\n\nQuero antecipar um ajuste preventivo nesta campanha.\n\nRisco identificado:\n- \n\nSugestão:\n- \n\nObrigado.'},informacao:{subject:'Informação operacional de campanha',body:'Olá,\n\nPartilho a seguinte informação relevante para a campanha.\n\nDetalhe:\n- \n\nObrigado.'},geral:{subject:'Pedido geral de acompanhamento',body:'Olá,\n\nSegue mensagem geral sobre a campanha.\n\nDetalhe:\n- \n\nObrigado.'}};
  const esc=s=>(s==null?'':String(s)).replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
  function setStatus(msg, type){
    if(!els.status) return;
    els.status.textContent = msg || '';
    els.status.classList.remove('is-success','is-error','is-info');
    if(type === 'success') els.status.classList.add('is-success');
    else if(type === 'error') els.status.classList.add('is-error');
    else if(type === 'info') els.status.classList.add('is-info');
  }
  const setLocked=(el,locked)=>{ if(!el) return; el.disabled=!!locked; el.classList.toggle('ff-locked',!!locked); };
  const lockIfSingle=(el)=>{ if(!el) return false; const opts=Array.from(el.options||[]).filter(o=>String(o.value||'')!==''); const one=opts.length===1; if(one) el.value=String(opts[0].value||''); setLocked(el,one); return one; };
  function syncProjects(preferred){ const cid=parseInt(els.client.value||'0',10); const filtered=cid?projects.filter(p=>parseInt(p.client_id||0,10)===cid):projects; const keep=preferred||els.project.value||root.dataset.projectId||''; els.project.innerHTML='<option value="">Selecionar</option>'+filtered.map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join(''); if(keep) els.project.value=String(keep); lockIfSingle(els.project); }
  async function loadOwners(){ const cid=els.client.value||''; const pid=els.project.value||''; const currentUserId=String(root.dataset.currentUser||''); els.owner.innerHTML='<option value="">A carregar...</option>'; if(els.sender) els.sender.innerHTML='<option value="">A carregar...</option>'; lockIfSingle(els.client); if(!pid){ els.owner.innerHTML='<option value="">Seleciona campanha</option>'; if(els.sender) els.sender.innerHTML='<option value="">Seleciona campanha</option>'; lockIfSingle(els.project); return; } const recipientUrl=new URL(ajax, window.location.origin); recipientUrl.searchParams.set('action','routespro_team_recipients'); recipientUrl.searchParams.set('_wpnonce', root.dataset.nonce||''); if(cid) recipientUrl.searchParams.set('client_id',cid); if(pid) recipientUrl.searchParams.set('project_id',pid); const senderUrl=new URL(ajax, window.location.origin); senderUrl.searchParams.set('action','routespro_get_client_team_senders'); senderUrl.searchParams.set('_wpnonce', root.dataset.nonce||''); if(cid) senderUrl.searchParams.set('client_id',cid); if(pid) senderUrl.searchParams.set('project_id',pid); const [recipientRes,senderRes]=await Promise.all([fetch(recipientUrl.toString(),{credentials:'same-origin'}),fetch(senderUrl.toString(),{credentials:'same-origin'})]); const recipientPayload=await recipientRes.json().catch(()=>[]); const senderPayload=await senderRes.json().catch(()=>[]); const users=recipientRes.ok&&Array.isArray(recipientPayload)?recipientPayload:[]; const senders=senderRes.ok&&Array.isArray(senderPayload)?senderPayload:[]; const senderError=!senderRes.ok&&senderPayload&&typeof senderPayload.message==='string'?senderPayload.message:''; const recipientError=!recipientRes.ok&&recipientPayload&&typeof recipientPayload.message==='string'?recipientPayload.message:''; const recipientCount=Array.isArray(users)?users.length:0; const recipientOptions=Array.isArray(users)?users.map(u=>`<option value="${u.ID}">${esc(u.label||u.display_name||('#'+u.ID))}</option>`).join(''):''; const allOption=recipientCount>0?`<option value="all">Todos os membros da equipa (${recipientCount})</option>`:''; els.owner.innerHTML='<option value="">Selecionar operativo da campanha</option>'+allOption+recipientOptions; els.owner.dataset.recipientCount=String(recipientCount); if(Array.isArray(users)&&users.length===1){ els.owner.value=String(users[0].ID||''); }
      if(els.sender){
        const currentSender=senders.find(u=>String(u.ID||'')===currentUserId) || (senders.length===1 ? senders[0] : null);
        if(currentSender){
          els.sender.innerHTML=`<option value="${esc(String(currentSender.ID||''))}">${esc(currentSender.label||currentSender.display_name||('#'+currentSender.ID))}</option>`;
          els.sender.value=String(currentSender.ID||'');
          setLocked(els.sender,true);
        } else {
          els.sender.innerHTML='<option value="">Sem utilizador cliente disponível</option>';
          setLocked(els.sender,true);
        }
      }
      lockIfSingle(els.project);
      lockIfSingle(els.owner);
      const hasRecipients=Array.isArray(users)&&users.length; const hasSenders=Array.isArray(senders)&&senders.length; const failureMessage=recipientError||senderError||(!hasRecipients?'Sem operativos disponíveis para esta campanha.':'O utilizador autenticado não tem acesso de envio nesta campanha.'); setStatus(hasRecipients&&hasSenders?'Contactos filtrados pela campanha ativa.':failureMessage, hasRecipients&&hasSenders ? 'info' : 'error'); if(els.send) els.send.disabled=!(hasRecipients&&hasSenders); }
  function wireTemplates(){ root.querySelectorAll('[data-template]').forEach(btn=>btn.addEventListener('click',()=>{ const key=btn.dataset.template||'geral'; const tpl=templates[key]||templates.geral; els.kind.value=key; if(!els.subject.value) els.subject.value=tpl.subject; if(!els.message.value) els.message.value=tpl.body; })); }
  function renderHistoryItems(items){
    if(!els.history) return;
    const rows=Array.isArray(items)?items:[];
    if(!rows.length){ els.history.innerHTML='<div class="rp-note">Sem histórico para o filtro atual.</div>'; return; }
    const grouped=[]; const broadcastMap=new Map();
    rows.forEach(item=>{ const bid=String(item?.meta?.broadcast_id||''); if(!bid){ grouped.push(item); return; } if(!broadcastMap.has(bid)){ const clone=Object.assign({},item,{meta:Object.assign({},item.meta||{})}); clone._broadcastRecipients=[]; broadcastMap.set(bid,clone); grouped.push(clone); } const group=broadcastMap.get(bid); group._broadcastRecipients.push(item.recipient_user_name||item.recipient_name||item.recipient_email||'Membro'); });
    els.history.innerHTML=grouped.map(item=>{
      const kindLabel=item.message_kind?String(item.message_kind).replace(/_/g,' '):'geral';
      const projectLabel=item.project_name||els.project?.options[els.project.selectedIndex]?.text||'Sem campanha';
      const senderLabel=item.sender_name||item.meta?.selected_sender_email||item.meta?.submitted_by_email||'Sem remetente';
      const isBroadcast=Array.isArray(item._broadcastRecipients)&&item._broadcastRecipients.length>0; const recipientLabel=isBroadcast?`Todos (${item._broadcastRecipients.length})`:(item.recipient_user_name||item.recipient_name||item.recipient_email||'Sem destinatário');
      const note=(item.body||'').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
      const direction=(String(item.sender_user_id||'')===String(els.owner?.value||''))?'Recebida':'Enviada';
      const statusLabel=item.effective_workflow_status||item.workflow_status||'novo';
      const atts=Array.isArray(item.meta?.attachments)?item.meta.attachments:[];
      const isImageAtt=a=>{ const type=String(a?.type||'').toLowerCase(); const name=String(a?.name||a?.download_name||a?.url||'').toLowerCase().split('?')[0].split('#')[0]; return type.indexOf('image/')===0||/\.(?:avif|bmp|gif|jpe?g|png|webp)$/.test(name); };
      const attHtml=atts.length?`<div class="rp-attachments" style="margin-top:10px"><strong>Anexos:</strong> ${atts.map(a=>{ if(!a?.url&&!a?.download_url&&!a?.view_url)return ''; const image=isImageAtt(a); const downloadUrl=a.download_url||a.url||''; if(image){ const viewUrl=a.view_url||a.url||a.download_url||''; const viewLink=viewUrl?`<a href="${esc(viewUrl)}" data-rp-image-viewer="1" data-fieldflow-team-image-viewer="1" data-role="attachment image">${esc(a.name||'Imagem')}</a>`:''; const downloadLink=downloadUrl?`<a href="${esc(downloadUrl)}" download="${esc(a.download_name||a.name||'imagem')}" data-fieldflow-team-attachment="1" data-fieldflow-team-attachment-kind="image" data-role="download attachment image-download">Descarregar</a>`:''; return viewLink+downloadLink; } return `<a href="${esc(downloadUrl)}" download="${esc(a.download_name||a.name||'anexo')}" data-fieldflow-team-attachment="1" data-fieldflow-team-attachment-kind="document" data-role="download attachment">${esc(a.name||'Anexo')}</a>`; }).join('')}</div>`:'';
      return `<div class="rp-history-item"><div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><strong>${esc(item.subject||'Mensagem')}</strong><span class="rp-pill">${esc(kindLabel)}</span></div><div style="margin-top:8px;color:#64748b">${esc(projectLabel)} · ${esc(direction)} · De: ${esc(senderLabel)} · Para: ${esc(recipientLabel)} · ${esc(item.created_at||'')}</div><div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap"><span class="rp-pill">Estado: ${esc(String(statusLabel).replace(/_/g,' '))}</span></div><div style="margin-top:10px">${esc(note||'Sem conteúdo disponível.')}</div>${attHtml}</div>`;
    }).join('');
  }
  async function loadHistory(){
    if(!els.history) return;
    const pid=els.project?.value||'';
    if(!pid){ els.history.innerHTML='<div class="rp-note">Seleciona a campanha para carregar o histórico.</div>'; return; }
    const url=new URL(ajax, window.location.origin);
    url.searchParams.set('action','routespro_get_team_messages');
    url.searchParams.set('_wpnonce', root.dataset.nonce||'');
    if(els.client?.value) url.searchParams.set('client_id', els.client.value);
    url.searchParams.set('project_id', pid);
    if(els.owner?.value) url.searchParams.set('participant_user_id', els.owner.value);
    els.history.innerHTML='<div class="rp-note">A carregar histórico...</div>';
    try{
      const res=await fetch(url.toString(),{credentials:'same-origin'});
      const payload=await res.json().catch(()=>({items:[]}));
      const items=Array.isArray(payload.items)?payload.items:[];
      renderHistoryItems(items.slice(0,20));
    }catch(_){
      els.history.innerHTML='<div class="rp-note is-error">Falha ao carregar o histórico.</div>';
    }
  }
  async function sendMail(){ if(isSending){ return; } const isBroadcast=String(els.owner?.value||'')==='all'; const recipientCount=parseInt(els.owner?.dataset?.recipientCount||'0',10)||0; if(isBroadcast){ const ok=window.confirm(`Enviar esta mensagem para todos os ${recipientCount} membros ativos da equipa desta campanha?`); if(!ok) return; } isSending=true; if(els.send){ els.send.disabled=true; els.send.style.opacity='.65'; } try{ const subjectSnapshot=els.subject.value||''; const messageSnapshot=els.message.value||''; const fd=new FormData(); fd.append('action','routespro_send_client_team_mail'); fd.append('_wpnonce', root.dataset.nonce||''); fd.append('client_id', els.client.value||''); fd.append('project_id', els.project.value||''); fd.append('sender_user_id', els.sender?.value||''); fd.append('recipient_user_id', els.owner.value||''); fd.append('message_kind', els.kind.value||'geral'); fd.append('priority', els.priority.value||'normal'); fd.append('subject', subjectSnapshot); fd.append('message', messageSnapshot); if(els.copy.checked) fd.append('send_copy','1'); Array.from(els.files?.files||[]).forEach(file=>fd.append('attachments[]', file)); setStatus(isBroadcast?'A preparar envio para a equipa...':'A enviar email...', 'info'); const res=await fetch(ajax,{method:'POST',credentials:'same-origin',body:fd}); const raw=await res.text(); let data={}; try{ data=raw?JSON.parse(raw):{}; }catch(_){ data={message:`Erro técnico no envio (HTTP ${res.status||500}).`}; } if(!res.ok||!data.ok){ throw new Error(data.message||`Falha ao enviar (HTTP ${res.status||500}).`); } renderLocalHistory(data.message||'Mensagem enviada com sucesso.', subjectSnapshot); setStatus(data.message||'Mensagem enviada.', 'success'); els.subject.value=''; els.message.value=''; if(els.files) els.files.value=''; } finally { isSending=false; if(els.send){ els.send.disabled=false; els.send.style.opacity=''; } } }
  function renderLocalHistory(note, subjectText){ const now=new Date(); const item=document.createElement('div'); item.className='rp-history-item'; item.innerHTML=`<div style="display:flex;justify-content:space-between;gap:10px;align-items:center"><strong>${esc(subjectText||els.subject.value||'Mensagem enviada')}</strong><span class="rp-pill">${esc(els.kind.options[els.kind.selectedIndex]?.text||'Geral')}</span></div><div style="margin-top:8px;color:#64748b">${esc(els.project.options[els.project.selectedIndex]?.text||'Sem campanha')} · De: ${esc(els.sender?.options[els.sender.selectedIndex]?.text||'Sem owner')} · Para: ${esc(els.owner.options[els.owner.selectedIndex]?.text||'Sem owner')} · ${now.toLocaleString()}</div><div style="margin-top:10px">${esc(note)}</div>`;
    if(els.history){ if(els.history.firstElementChild && els.history.firstElementChild.classList.contains('rp-note')) els.history.innerHTML=''; els.history.prepend(item); } }
  els.client?.addEventListener('change', async()=>{ syncProjects(''); await loadOwners(); await loadHistory(); });
  els.project?.addEventListener('change', async()=>{ await loadOwners(); await loadHistory(); });
  els.owner?.addEventListener('change', loadHistory);
  els.clear?.addEventListener('click',()=>{ els.subject.value=''; els.message.value=''; if(els.files) els.files.value=''; setStatus('Formulário limpo.', 'info'); });
  els.send?.addEventListener('click', async()=>{ try{ await sendMail(); }catch(err){ setStatus(err.message||'Falha ao enviar email.', 'error'); } });
  wireTemplates();
  syncProjects(root.dataset.projectId||'');
  if(root.dataset.clientId && !els.client.value) els.client.value=root.dataset.clientId;
  if(root.dataset.projectId) els.project.value=root.dataset.projectId;
  lockIfSingle(els.client); lockIfSingle(els.project);
  loadOwners().then(loadHistory);
  document.addEventListener('routespro:scope-change', ev=>{ const d=ev.detail||{}; if(typeof d.client_id!=='undefined'){ els.client.value=String(d.client_id||''); } syncProjects(String(d.project_id||'')); if(typeof d.project_id!=='undefined'){ els.project.value=String(d.project_id||''); } loadOwners().then(loadHistory); });
})();
</script>
<?php
        return ob_get_clean();
    }



    public static function team_inbox($atts = []) {
        return \RoutesPro\Front\TeamInboxShortcodeRenderer::team_inbox($atts);
    }
    public static function client_portal($atts = []) {
        return \RoutesPro\Front\ClientPortalShortcodeRenderer::client_portal($atts);
    }
    public static function report_visit($atts = []) {
        $guard = self::front_guard();
        if ($guard) return $guard;
        $requested_mode = sanitize_key($atts['mode'] ?? 'full');
        if ($requested_mode === 'app') return self::report_visit_app($atts);
        $theme = self::front_theme();
        $nonce = wp_create_nonce('wp_rest');
        $scope = \RoutesPro\Support\Permissions::get_scope();
        $current_user_id = get_current_user_id();
        $route_id = absint($atts['route_id'] ?? ($_GET['route_id'] ?? 0));
        $stop_id  = absint($atts['stop_id'] ?? ($_GET['stop_id'] ?? 0));
        $mode     = sanitize_text_field($atts['mode'] ?? 'full');
        $binding = class_exists('\RoutesPro\Forms\BindingResolver') ? \RoutesPro\Forms\BindingResolver::resolve([
            'route_id' => $route_id,
            'stop_id' => $stop_id,
        ]) : null;
        $binding_mode = sanitize_key($binding['mode'] ?? '');
        $context = class_exists('\RoutesPro\Forms\BindingResolver') ? \RoutesPro\Forms\BindingResolver::get_context([
            'route_id' => $route_id,
            'stop_id' => $stop_id,
        ]) : ['route_id'=>$route_id,'stop_id'=>$stop_id];
        $route_id = (int) ($context['route_id'] ?? $route_id);
        $stop_id = (int) ($context['stop_id'] ?? $stop_id);
        if ($binding && $binding_mode === 'form_only' && !empty($binding['form_id']) && class_exists('\RoutesPro\Forms\Forms')) {
            return \RoutesPro\Forms\Forms::render_form_with_context((int) $binding['form_id'], $context, $binding, ['show_title' => true]);
        }
        global $wpdb;
        $users = [];
        $px = $wpdb->prefix . 'routespro_';
        $clients = \RoutesPro\Support\Permissions::filter_clients($wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: []);
        $projects = \RoutesPro\Support\Permissions::filter_projects($wpdb->get_results("SELECT id,client_id,name FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: []);
        ob_start(); ?>
<style>
.rp-report-quick{font-family:<?php echo esc_attr($theme['font']); ?>;font-size:<?php echo esc_attr($theme['size']); ?>px}
.rp-report-quick .rp-card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 16px 40px rgba(15,23,42,.08);padding:18px}
.rp-report-quick .rp-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.rp-report-quick .rp-row .full{grid-column:1/-1}
.rp-report-quick input,.rp-report-quick select,.rp-report-quick textarea{width:100%;min-height:52px;border:1px solid #cbd5e1;border-radius:14px;padding:13px 14px;font-size:16px;line-height:1.35;color:#0f172a;background:#fff}
.rp-report-quick textarea{min-height:110px}
.rp-report-quick .rp-btn{border:0;border-radius:14px;padding:12px 16px;background:<?php echo esc_attr($theme['primary']); ?>;color:#fff;font-weight:700;cursor:pointer}
.rp-report-quick .rp-btn.alt{background:<?php echo esc_attr($theme['accent']); ?>}
.rp-report-quick .rp-meta{color:#64748b;font-size:14px;line-height:1.5}
.rp-report-quick .rp-status{margin-top:14px;background:#f8fafc;border:1px solid #cbd5e1;border-radius:16px;padding:14px 16px;font-size:15px;line-height:1.5;scroll-margin-top:18px}.rp-report-quick .rp-status.is-success{background:#ecfdf3;border-color:#86efac;color:#166534;font-weight:800}.rp-report-quick .rp-status.is-error{background:#fef2f2;border-color:#fecaca;color:#991b1b;font-weight:700}.rp-report-quick .rp-status.is-info{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.rp-report-quick .rp-toast{position:fixed;left:50%;bottom:calc(24px + env(safe-area-inset-bottom,0px));transform:translateX(-50%) translateY(18px);z-index:99999;max-width:min(560px,calc(100vw - 28px));padding:14px 18px;border-radius:16px;background:#0f172a;color:#fff;font-size:15px;font-weight:800;box-shadow:0 22px 54px rgba(15,23,42,.3);opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease}.rp-report-quick .rp-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}.rp-report-quick .rp-action-stack{display:flex;flex-direction:column;gap:12px;margin-top:14px}.rp-report-quick .rp-action-stack .rp-btn{width:100%;justify-content:center;display:inline-flex;align-items:center}.rp-report-quick .rp-submit-wrap{margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0}
@media (max-width:780px){.rp-report-quick .rp-row{grid-template-columns:1fr}}
</style>
<div class="rp-report-quick" id="routespro-report-quick" data-route-id="<?php echo esc_attr($route_id); ?>" data-stop-id="<?php echo esc_attr($stop_id); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-mode="<?php echo esc_attr($mode); ?>" data-scope='<?php echo esc_attr(wp_json_encode($scope)); ?>' data-current-user="<?php echo esc_attr((string) $current_user_id); ?>" data-today="<?php echo esc_attr(current_time('Y-m-d')); ?>">
  <div class="rp-card">
    <h3 style="margin:0 0 8px">Reporte rápido de visita</h3>
    <div class="rp-meta">Pensado para mobile. Faz check-in, regista resultado e fecha a visita sem sair da rota.</div>
    <div class="rp-row" style="margin-top:14px">
      <select id="rpr-client" data-scope-client="1"><option value="">Cliente</option><?php foreach($clients as $c): ?><option value="<?php echo intval($c['id']); ?>"><?php echo esc_html($c['name']); ?></option><?php endforeach; ?></select>
      <select id="rpr-project" data-scope-project="1"><option value="">Campanha</option></select>
      <select id="rpr-owner"><option value="">Owner</option><?php foreach($users as $u): ?><option value="<?php echo intval($u->ID); ?>"><?php echo esc_html($u->display_name ?: $u->user_login); ?></option><?php endforeach; ?></select>
      <select id="rpr-route-select"><option value="">Rota disponível</option></select>
      <select id="rpr-stop-select" class="full"><option value="">Paragem disponível</option></select>
      <?php if ($route_id || $stop_id): ?>
      <div class="full rp-meta" id="rpr-context-meta">Contexto automático detetado<?php echo $route_id ? ' · route_id #' . esc_html((string) $route_id) : ''; ?><?php echo $stop_id ? ' · stop_id #' . esc_html((string) $stop_id) : ''; ?></div>
      <input id="rpr-route" type="hidden" value="<?php echo esc_attr($route_id ?: ''); ?>">
      <input id="rpr-stop" type="hidden" value="<?php echo esc_attr($stop_id ?: ''); ?>">
      <input id="rpr-location" type="hidden" value="<?php echo esc_attr((string) ((int) ($context['location_id'] ?? 0))); ?>">
      <?php else: ?>
      <input id="rpr-route" type="number" placeholder="Route ID" value="<?php echo esc_attr($route_id ?: ''); ?>">
      <input id="rpr-stop" type="number" placeholder="Stop ID" value="<?php echo esc_attr($stop_id ?: ''); ?>">
      <input id="rpr-location" type="hidden" value="<?php echo esc_attr((string) ((int) ($context['location_id'] ?? 0))); ?>">
      <?php endif; ?>
      <select id="rpr-status" class="full">
        <option value="done">Visitado</option>
        <option value="failed">Fechado / sem visita</option>
        <option value="in_progress">Em curso</option>
      </select>
      <select id="rpr-fail" class="full">
        <option value="">Motivo de falha, opcional</option>
        <option value="Fechado">Fechado</option>
        <option value="Sem interesse">Sem interesse</option>
        <option value="Sem contacto">Sem contacto</option>
        <option value="Reagendar">Reagendar</option>
      </select>
      <textarea id="rpr-note" class="full" placeholder="Notas rápidas da visita"></textarea>
      <div class="full rp-action-stack">
        <button type="button" class="rp-btn" id="rpr-checkin">Check-in agora</button>
      </div>
    </div>
    <div class="rp-status is-info" id="rpr-status-box" role="status" aria-live="polite">Usa o reporte rápido abaixo. Quando o contexto da rota já existe, route_id e stop_id são preenchidos automaticamente.</div>
    <div id="rpr-embedded-form-slot" style="margin-top:18px">
      <?php if ($binding && ($binding_mode === 'route_and_form') && !empty($binding['form_id']) && class_exists('\RoutesPro\Forms\Forms')): ?>
        <div class="rp-meta" style="margin-bottom:10px">Formulário dinâmico associado a este contexto.</div>
        <?php echo \RoutesPro\Forms\Forms::render_form_with_context((int) $binding['form_id'], $context, $binding, ['show_title' => true, 'hide_actions' => true]); ?>
      <?php endif; ?>
    </div>
    <div class="rp-submit-wrap">
      <button type="button" class="rp-btn alt" id="rpr-submit">Guardar reporte</button>
    </div>
    <div class="rp-toast" id="rpr-toast" role="status" aria-live="assertive"></div>
  </div>
</div>
<script>
(function(){
  const root = document.getElementById('routespro-report-quick'); if(!root) return;
  const api = '<?php echo esc_url(rest_url('routespro/v1/')); ?>';
  const nonce = root.dataset.nonce;
  const projects = <?php echo wp_json_encode($projects); ?>;
  const ownerAjaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
  const scope = JSON.parse(root.dataset.scope || '{}');
  const currentUserId = String(root.dataset.currentUser || <?php echo (int)get_current_user_id(); ?>);
  const localToday = String(root.dataset.today || '');
  const isAppContext = !!root.closest('.rp-app');
  const els = {
    client: root.querySelector('#rpr-client'), project: root.querySelector('#rpr-project'), owner: root.querySelector('#rpr-owner'), routeSel: root.querySelector('#rpr-route-select'), stopSel: root.querySelector('#rpr-stop-select'),
    route: root.querySelector('#rpr-route'), stop: root.querySelector('#rpr-stop'), location: root.querySelector('#rpr-location'), status: root.querySelector('#rpr-status'), fail: root.querySelector('#rpr-fail'), note: root.querySelector('#rpr-note'), box: root.querySelector('#rpr-status-box'), slot: root.querySelector('#rpr-embedded-form-slot'), submit: root.querySelector('#rpr-submit'), toast: root.querySelector('#rpr-toast')
  };
  let routesCache = [];
  const esc = (s) => s == null ? '' : String(s);
  // 9CV fieldflow-report-context-recovery-9cv-v1: WordPress can return plain SQL errors before the AJAX handler runs.
  const reportErrorMessage=(raw,fallback='O servidor não respondeu. Tenta novamente.')=>{
    let value=raw;
    if(value&&typeof value==='object')value=value.message||(value.data&&value.data.message)||value.code||'';
    value=String(value||'').trim();
    if(/error establishing a database connection|erro ao estabelecer.*(?:base de dados|banco de dados)|wp-config\.php|mysqli|mariadb|SQLSTATE|database server.*down|database connection.*(?:failed|lost)/i.test(value))return 'A base de dados está temporariamente indisponível. Mantém os dados guardados e tenta novamente dentro de momentos.';
    if(/<!doctype|<html|fatal error|stack trace|critical error|erro crítico neste site|wordpress database error/i.test(value))return 'O servidor encontrou uma dificuldade temporária. Tenta novamente dentro de momentos.';
    return value?value.slice(0,240):fallback;
  };
  let toastTimer=null;
  const setStatus=(msg,type='info',scroll=false)=>{els.box.textContent=type==='error'?reportErrorMessage(msg,'Não foi possível carregar o reporte.'):msg;els.box.classList.remove('is-success','is-error','is-info');els.box.classList.add('is-'+type);if(scroll)els.box.scrollIntoView({behavior:'smooth',block:'center'});};
  const showToast=(msg,type='success')=>{if(!els.toast)return;els.toast.textContent=msg;els.toast.dataset.type=type;els.toast.classList.add('show');window.clearTimeout(toastTimer);toastTimer=window.setTimeout(()=>els.toast.classList.remove('show'),4200);};
  const syncSubmitAvailability=()=>{if(!els.submit)return;const activeForm=root.querySelector('.routespro-dyn-form');const dailyExisting=!!(activeForm&&activeForm.dataset.rpDailyExisting==='1');const dailyLocked=!!(dailyExisting&&activeForm.dataset.rpDailyEditMode!=='1');if(dailyLocked){els.submit.disabled=true;els.submit.textContent='Reporte já efetuado';els.submit.dataset.rpDailyLocked='1';}else{els.submit.disabled=false;els.submit.textContent=dailyExisting?'Guardar alterações':'Guardar reporte';els.submit.dataset.rpDailyLocked='0';}delete els.submit.dataset.originalLabel;};
  const setSubmitWorking=(working)=>{if(!els.submit)return;if(working){els.submit.dataset.originalLabel=els.submit.textContent||'Guardar reporte';els.submit.textContent='A guardar...';els.submit.disabled=true;}else{syncSubmitAvailability();}};
  root.addEventListener('fieldflow:daily-edit-state',event=>{if(event.detail)syncSubmitAvailability();});
  const formFeedback=(type,msg,scroll=true)=>{const form=root.querySelector('.routespro-dyn-form');if(form&&window.routesproSetFormFeedback)window.routesproSetFormFeedback(form,type,msg,scroll);};
  const flushActiveFormDraft=()=>{const form=root.querySelector('.routespro-dyn-form');if(form&&typeof form.routesproPrepareFinalSubmit==='function')form.routesproPrepareFinalSubmit();else if(form&&typeof form.routesproFlushDraft==='function')form.routesproFlushDraft({force:true,silent:true,localOnly:true,immediateServer:false});};
  const nowIso = () => new Date().toISOString();
  const getGeo = () => new Promise(resolve => {
    if(!('geolocation' in navigator)) return resolve(null);
    navigator.geolocation.getCurrentPosition(pos => resolve({lat: pos.coords.latitude, lng: pos.coords.longitude}), () => resolve(null), {enableHighAccuracy:true, timeout:8000, maximumAge:60000});
  });

  async function j(url, opts={}){ const requestOpts=Object.assign({credentials:'same-origin', cache:'no-store', headers:{'X-WP-Nonce':nonce}}, opts); if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.fetchJson==='function'&&String(requestOpts.method||'GET').toUpperCase()==='GET')return window.FieldFlowOffline.fetchJson(url,requestOpts); const res = await fetch(url, requestOpts); const text = await res.text(); let json={}; try{ json = text ? JSON.parse(text) : {}; }catch(_){ json={message:text}; } if(!res.ok) throw new Error(reportErrorMessage(json,'Falha no pedido.')); return json; }
  function syncProjects(){ const cid=parseInt((els.client?.value)||'0',10); const filtered=cid ? projects.filter(x=>parseInt(x.client_id||0,10)===cid) : projects; const current=String(els.project?.value||''); els.project.innerHTML='<option value="">Campanha</option>'+filtered.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join(''); if(current && Array.from(els.project.options).some(o=>String(o.value)===current)) els.project.value=current; }
  function allowedClientIds(){ return Array.isArray(scope?.client_ids) ? scope.client_ids.map(v=>String(parseInt(v,10))).filter(Boolean) : []; }
  function allowedProjectIds(){ return Array.isArray(scope?.project_ids) ? scope.project_ids.map(v=>String(parseInt(v,10))).filter(Boolean) : []; }
  function lockReportScope(){
    if(!isAppContext || scope?.is_manager) return;
    const clientIds=allowedClientIds();
    const projectIds=allowedProjectIds();
    if(els.client){
      if(!els.client.value && clientIds.length===1) els.client.value=clientIds[0];
      els.client.disabled=true;
    }
    syncProjects();
    if(els.project){
      if(!els.project.value && projectIds.length===1 && Array.from(els.project.options).some(o=>String(o.value)===projectIds[0])) els.project.value=projectIds[0];
      els.project.disabled=true;
    }
  }
  async function syncOwners(){
    const current = els.owner?.value || '';
    const url = new URL(ownerAjaxUrl, window.location.origin);
    url.searchParams.set('action', 'routespro_users');
    if(els.client?.value) url.searchParams.set('client_id', els.client.value);
    if(els.project?.value) url.searchParams.set('project_id', els.project.value);
    try {
      const res = await fetch(url.toString(), { credentials:'same-origin' });
      const rows = await res.json();
      const users = Array.isArray(rows) ? rows : [];
      els.owner.innerHTML = '<option value="">Owner</option>' + users.map(u=>`<option value="${parseInt(u.ID||0,10)}">${esc(u.label || u.displayName || u.username || ('User '+(u.ID||'')))}</option>`).join('');
      if(current && Array.from(els.owner.options).some(o=>String(o.value)===String(current))) els.owner.value = current;
      if(isAppContext && !scope?.is_manager && currentUserId){
        if(!Array.from(els.owner.options).some(o=>String(o.value)===currentUserId)){
          const opt=document.createElement('option'); opt.value=currentUserId; opt.textContent='Meu utilizador'; els.owner.appendChild(opt);
        }
        els.owner.value = currentUserId;
        els.owner.disabled = true;
      }
    } catch(_){ }
  }
  async function loadRoutes(){ const selectedRoute=String(els.route?.value||els.routeSel?.value||''); const p=new URLSearchParams(); p.set('date', localToday || new Date().toLocaleDateString('sv-SE')); p.set('_ff', String(Date.now())); if(els.client?.value) p.set('client_id', els.client.value); if(els.project?.value) p.set('project_id', els.project.value); const data = await j(api+'routes?'+p.toString()); let rows = Array.isArray(data.routes) ? data.routes : []; const ownerId=parseInt((els.owner?.value)||'0',10); if(ownerId) rows = rows.filter(r=>parseInt(r.owner_user_id||0,10)===ownerId); routesCache = rows; els.routeSel.innerHTML='<option value="">Rota disponível</option>'+rows.map(r=>`<option value="${r.id}">#${r.id} · ${esc(r.date||'')} · ${esc(r.status||'')}</option>`).join(''); if(selectedRoute && Array.from(els.routeSel.options).some(o=>String(o.value)===selectedRoute)){ els.routeSel.value=selectedRoute; } if(rows.length===1 && !els.routeSel.value){ els.routeSel.value = String(rows[0].id); } els.route.value=els.routeSel.value||''; if(els.routeSel.value){ await loadStops(); }
  }
  async function loadStops(){ const selectedStop=String(els.stop?.value||els.stopSel?.value||''); const rid=parseInt((els.routeSel?.value)||els.route.value||'0',10); els.route.value = rid ? String(rid) : ''; if(!rid){ els.stopSel.innerHTML='<option value="">Paragem disponível</option>'; if(els.stop) els.stop.value=''; if(els.location) els.location.value=''; if(els.slot){ flushActiveFormDraft(); els.slot.innerHTML=''; } return; } const route = await j(api+'routes/'+rid+'?_ff='+Date.now()); const stops = Array.isArray(route.stops) ? route.stops : []; els.stopSel.innerHTML='<option value="">Paragem disponível</option>'+stops.map(s=>{ const status=String(s.status||'pending').toLowerCase(); const statusLabel=['done','completed'].includes(status)?'executado':(status==='failed'?'falhou':'pendente'); return `<option value="${s.id}" data-location-id="${parseInt(s.location_id||0,10)||''}" data-status="${esc(status)}">#${s.id} · ${esc(s.location_name||'PDV')} · ${statusLabel}</option>`; }).join(''); if(selectedStop && Array.from(els.stopSel.options).some(o=>String(o.value)===selectedStop)){ els.stopSel.value=selectedStop; } if(stops.length===1 && !els.stopSel.value){ els.stopSel.value = String(stops[0].id); } if(els.stopSel.value){ els.stop.value = els.stopSel.value; } await refreshEmbeddedForm();
  }
  function selectedStopOption(){ return els.stopSel ? els.stopSel.options[els.stopSel.selectedIndex] : null; }
  function syncResolvedContextToForm(){
    const form = root.querySelector('.routespro-dyn-form');
    if(!form) return null;
    const stopOpt = selectedStopOption();
    const locationId = (stopOpt && stopOpt.dataset && stopOpt.dataset.locationId) ? String(stopOpt.dataset.locationId) : (els.location?.value || '');
    if(els.location) els.location.value = locationId || '';
    const map = {
      routespro_client_id: els.client?.value || '',
      routespro_project_id: els.project?.value || '',
      routespro_route_id: els.route?.value || '',
      routespro_stop_id: els.stop?.value || '',
      routespro_location_id: locationId || ''
    };
    Object.keys(map).forEach(function(name){
      const input = form.querySelector('[name="'+name+'"]');
      if(input) input.value = map[name];
    });
    return form;
  }
  async function fetchOperationalNetwork(url,options={},timeoutMs=8000){
    if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.probeConnectivity==='function'){
      const connectivity=await window.FieldFlowOffline.probeConnectivity(false);
      if(!['online','degraded'].includes(String(connectivity.state||''))){const error=new Error(connectivity.state==='degraded'?'Rede fraca, a usar os dados protegidos no dispositivo.':'Sem ligação utilizável ao FieldFlow.');error.code='fieldflow_network_'+connectivity.state;error.retryable=true;throw error;}
    }
    const controller=('AbortController' in window)?new AbortController():null;
    const timer=controller?window.setTimeout(()=>controller.abort(),Math.max(2000,parseInt(timeoutMs||8000,10))):null;
    try{return await fetch(url,Object.assign({},options,controller?{signal:controller.signal}:{}));}
    catch(error){if(error&&error.name==='AbortError'){const wrapped=new Error('A ligação está demasiado lenta. Os dados locais foram mantidos.');wrapped.code='fieldflow_network_timeout';wrapped.retryable=true;throw wrapped;}throw error;}
    finally{if(timer)window.clearTimeout(timer);}
  }
  let embeddedFormSequence=0; // 9BH: ignore responses for a previously selected store.
  let embeddedFormVisitKey='';
  async function refreshEmbeddedForm(){
    const requestId=++embeddedFormSequence;
    if(!els.slot) return;
    flushActiveFormDraft();
    els.slot.innerHTML='';
    syncSubmitAvailability();
    const stopId = parseInt((els.stop?.value)||'0',10);
    const routeId = parseInt((els.route?.value)||'0',10);
    if(!stopId && !routeId){
      if(window.routesproClearPreviousVisitModal) window.routesproClearPreviousVisitModal();
      embeddedFormVisitKey='';
      flushActiveFormDraft();
      els.slot.innerHTML = '';
      syncSubmitAvailability();
      return;
    }
    const stopOpt = selectedStopOption();
    const locationId = (stopOpt && stopOpt.dataset && stopOpt.dataset.locationId) ? String(stopOpt.dataset.locationId) : (els.location?.value || '');
    const memoryVisitKey=[routeId,stopId,locationId,localToday].join(':');
    if(memoryVisitKey!==embeddedFormVisitKey&&window.routesproClearPreviousVisitModal) window.routesproClearPreviousVisitModal();
    embeddedFormVisitKey=memoryVisitKey;
    if(els.location) els.location.value = locationId || '';
    const fd = new FormData();
    fd.set('action', 'routespro_context_form');
    fd.set('nonce', nonce);
    fd.set('client_id', els.client?.value || '');
    fd.set('project_id', els.project?.value || '');
    fd.set('route_id', routeId ? String(routeId) : '');
    fd.set('stop_id', stopId ? String(stopId) : '');
    fd.set('location_id', locationId || '');
    fd.set('date', localToday || '');
    fd.set('return_url', window.location.href || '');
    const formCacheKey='form-html:<?php echo esc_js(FIELDFLOW_VERSION); ?>:u'+currentUserId+':c'+String(els.client?.value||0)+':p'+String(els.project?.value||0)+':r'+routeId+':s'+stopId+':l'+String(locationId||0)+':d'+String(localToday||'');
    const isOwnCachedForm=cached=>{
      if(!cached||!cached.has_form||!cached.context||typeof cached.html!=='string')return false;
      const context=cached.context;
      const selected={client_id:els.client?.value,project_id:els.project?.value,route_id:routeId,stop_id:stopId,location_id:locationId};
      return ['client_id','project_id','route_id','stop_id','location_id'].every(key=>Number(context[key]||0)===Number(selected[key]||0))&&String(context.date||'')===String(localToday||'');
    };
    let data=null;
    try{
      const res = await fetchOperationalNetwork(ownerAjaxUrl, { method:'POST', credentials:'same-origin', body: fd }, 8000);
      const text = await res.text();
      let json = {};
      try { json = text ? JSON.parse(text) : {}; } catch(_){ json = { success:false, data:{message:reportErrorMessage(text,'Falha ao carregar o formulário.')} }; }
      if(!res.ok || !json.success) throw new Error(reportErrorMessage(json.data||json,'Falha ao carregar o formulário.'));
      data = json.data || {};
      if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.setMeta==='function'&&isOwnCachedForm(data))window.FieldFlowOffline.setMeta(formCacheKey,data).catch(()=>{});
    }catch(error){
      if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.getMeta==='function'){
        const cached=await window.FieldFlowOffline.getMeta(formCacheKey).catch(()=>null);
        if(isOwnCachedForm(cached))data=cached;
      }
      if(!data)throw error;
      data=Object.assign({},data,{_fieldflow_offline_cached:true});
    }
    if(requestId!==embeddedFormSequence
      || String(els.route?.value||'0')!==String(routeId||'0')
      || String(els.stop?.value||'0')!==String(stopId||'0')
      || String(els.location?.value||'0')!==String(locationId||'0')) return;
    els.slot.innerHTML = data.html || '';
    syncResolvedContextToForm();
    const injectedForm = els.slot.querySelector('.routespro-dyn-form');
    if(!injectedForm&&window.routesproClearPreviousVisitModal) window.routesproClearPreviousVisitModal();
    if(injectedForm && window.routesproInitDynamicForm){ window.routesproInitDynamicForm(injectedForm); }
    if(injectedForm && window.jQuery){ window.jQuery(document).trigger('routespro:form-rendered', [injectedForm]); }
    // O botão exterior pertence ao reporte rápido e sobrevive à troca de loja.
    // Recalcula sempre o estado para não herdar o bloqueio de uma loja já reportada.
    syncSubmitAvailability();
  }
  async function submitEmbeddedForm(){
    const form = syncResolvedContextToForm();
    if(!form) return null;
    if(typeof form.routesproPrepareFinalSubmit==='function')await Promise.resolve(form.routesproPrepareFinalSubmit());else if(typeof form.routesproFlushDraft==='function')form.routesproFlushDraft({force:true,silent:true,localOnly:true,immediateServer:false});
    const offlineMeta={
      route_id:parseInt(els.route?.value||0,10),route_stop_id:parseInt(els.stop?.value||0,10),
      location_id:parseInt(els.location?.value||0,10),report_day:localToday||''
    };
    let data=null;
    if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.submitForm==='function'){
      data=await window.FieldFlowOffline.submitForm(form,offlineMeta,{nonce:nonce});
    }else{
      try{
        if(typeof form.routesproSubmitAjax==='function'){
          data=await form.routesproSubmitAjax({nonce:nonce,maxRetries:1,timeout:120000});
        }else{
          const fd = new FormData(form);fd.set('routespro_ajax', '1');
          const res=await fetch(form.getAttribute('action'), { method:'POST', credentials:'same-origin', headers:{'X-WP-Nonce':nonce,'X-Requested-With':'XMLHttpRequest'}, body: fd });
          const text = await res.text();let json = {};
          try { json = text ? JSON.parse(text) : {}; } catch(_){ json = { success:false, data:{message:reportErrorMessage(text,'Falha ao guardar formulário.')} }; }
          if(!res.ok || !json.success){ const payload=(json&&json.data)||{}; const error=new Error(reportErrorMessage(payload,'Falha ao guardar formulário.')); error.code=payload.code||''; error.data=payload; throw error; }
          data=json.data||json;
        }
      }catch(error){
        if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.queueForm==='function'&&((navigator.onLine===false&&error.safeToRetry!==false)||error.safeToRetry===true)) return window.FieldFlowOffline.queueForm(form,offlineMeta);
        throw error;
      }
    }
    const recordInput=form.querySelector('[name="routespro_record_id"]');
    const versionInput=form.querySelector('[name="routespro_record_version_id"]');
    if(recordInput&&data.record_id)recordInput.value=String(data.record_id);
    if(versionInput&&data.record_version_id)versionInput.value=String(data.record_version_id);
    if(data&&!data.queued_offline&&typeof form.routesproMarkSaved==='function')form.routesproMarkSaved(data);
    return data;
  }
  async function refreshAndPublishVisit(formResult=null, operationalResult=null){
    const completion=(formResult&&formResult.visit_completion)||{};
    const routeProgress=(operationalResult&&operationalResult.route_progress)||{};
    const detail={
      route_id:parseInt(completion.route_id||els.route?.value||0,10),
      route_stop_id:parseInt(completion.route_stop_id||els.stop?.value||0,10),
      location_id:parseInt(completion.location_id||els.location?.value||0,10),
      stop_status:String(completion.stop_status||'done'),
      route_status:String(routeProgress.route_status||completion.route_status||''),
      route_date:localToday||'',
      report_day:String(formResult?.report_day||localToday||''),
      route_completed_now:!!(routeProgress.route_completed_now||completion.route_completed_now)
    };
    try{ await loadRoutes(); }catch(_){ }
    root.dispatchEvent(new CustomEvent('fieldflow:visit-completed',{detail}));
    document.dispatchEvent(new CustomEvent('fieldflow:visit-completed',{detail}));
    return detail;
  }

  async function sendEvent(eventType, payload){
    const stopId = parseInt(els.stop.value || '0', 10);
    if(!stopId){ setStatus('O stop_id deve ser resolvido automaticamente neste contexto.'); return null; }
    const offlineType=eventType==='failure'?'stop_failure':(eventType==='checkin'?'stop_checkin':'stop_checkout');
    const offlinePayload={route_stop_id:stopId,payload:payload||{}};
    const offlineMeta={route_id:parseInt(els.route?.value||0,10),location_id:parseInt(els.location?.value||0,10)};
    if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.enqueueOperation==='function'){
      const connectivity=typeof window.FieldFlowOffline.probeConnectivity==='function'?await window.FieldFlowOffline.probeConnectivity(false):{state:navigator.onLine===false?'offline':'online'};
      if(!['online','degraded'].includes(String(connectivity.state||'')))return window.FieldFlowOffline.enqueueOperation(offlineType,offlinePayload,offlineMeta);
    }
    try{
      const res=await fetchOperationalNetwork(api+'events', {
        method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},
        body: JSON.stringify({ route_stop_id: stopId, event_type: eventType, payload })
      }, 8000);
      const text = await res.text();
      let json = null;
      try { json = text ? JSON.parse(text) : {}; } catch(_){
        const error=new Error('O servidor não confirmou o evento FieldFlow.');error.code='fieldflow_invalid_json_response';error.status=res.status||0;throw error;
      }
      if(!res.ok){const error=new Error(json.message || 'Falha ao guardar evento.');error.status=res.status;error.code=json.code||'';throw error;}
      return json;
    }catch(error){
      const status=parseInt(error&&error.status||0,10);const retryable=!status||status>=500||[401,408,425,429].includes(status)||error.code==='fieldflow_invalid_json_response';
      if(retryable&&window.FieldFlowOffline&&typeof window.FieldFlowOffline.enqueueOperation==='function') return window.FieldFlowOffline.enqueueOperation(offlineType,offlinePayload,offlineMeta);
      throw error;
    }
  }
  const refreshQuickReportAfterOfflineSync=event=>{
    const item=event&&event.detail&&event.detail.item||{};
    const itemStop=parseInt(item.meta&&item.meta.route_stop_id||item.payload&&item.payload.route_stop_id||item.payload&&item.payload.stop_id||0,10)||0;
    const currentStop=parseInt(els.stop&&els.stop.value||0,10)||0;
    if(!itemStop||!currentStop||itemStop!==currentStop)return;
    const type=String(item.type||'');
    if(item.kind!=='form'&&!['stop_checkout','stop_failure'].includes(type))return;
    window.setTimeout(()=>loadRoutes().catch(()=>{}),120);
  };
  document.addEventListener('fieldflow:offline-form-synced',refreshQuickReportAfterOfflineSync);
  document.addEventListener('fieldflow:offline-operation-synced',refreshQuickReportAfterOfflineSync);
  els.client?.addEventListener('change', ()=>{ flushActiveFormDraft(); syncProjects(); lockReportScope(); syncOwners().then(()=>loadRoutes()).catch(err=>setStatus(err.message||'Falha ao carregar rotas.')); });
  els.project?.addEventListener('change', ()=>{ flushActiveFormDraft(); lockReportScope(); syncOwners().then(()=>loadRoutes()).catch(err=>setStatus(err.message||'Falha ao carregar rotas.')); });
  els.owner?.addEventListener('change', ()=>{ flushActiveFormDraft(); loadRoutes().catch(err=>setStatus(err.message||'Falha ao carregar rotas.')); });
  els.routeSel?.addEventListener('change', ()=>{ flushActiveFormDraft(); loadStops().catch(err=>setStatus(err.message||'Falha ao carregar paragens.')); });
  els.stopSel?.addEventListener('change', ()=>{ flushActiveFormDraft(); els.stop.value = els.stopSel.value || ''; refreshEmbeddedForm().catch(err=>setStatus(err.message||'Falha ao atualizar o formulário.')); });
  lockReportScope();
  syncOwners().then(()=>loadRoutes()).catch(()=>{});
  root.querySelector('#rpr-checkin').addEventListener('click', async ()=>{
    try{
      setStatus('A fazer check-in...');
      const geo = await getGeo();
      const result=await sendEvent('checkin', { arrived_at: nowIso(), real_lat: geo?.lat || null, real_lng: geo?.lng || null, note: els.note.value || '' });
      setStatus(result&&result.queued_offline?'Check-in guardado no dispositivo. Será sincronizado quando houver ligação.':'Check-in registado com sucesso.');
    }catch(err){ setStatus(err.message || 'Falha no check-in.'); }
  });
  root.querySelector('#rpr-submit').addEventListener('click', async ()=>{
    const form=root.querySelector('.routespro-dyn-form');
    if(form&&form.dataset.rpDailyExisting==='1'&&form.dataset.rpDailyEditMode!=='1'){
      const message=form.dataset.rpDailyContextConflict==='1'?'Este reporte pertence a outra visita e só pode ser editado no contexto original.':'O reporte desta loja já foi efetuado. Escolhe “Editar reporte” para corrigir respostas.';
      formFeedback('warning',message,true);setStatus(message,'info',false);showToast(message,'error');syncSubmitAvailability();return;
    }
    if(form&&window.routesproValidateDynamicForm&&!window.routesproValidateDynamicForm(form,{announce:true})){
      setStatus('Revê as respostas obrigatórias antes de guardar.','error',false);
      showToast('Faltam respostas obrigatórias.','error');
      return;
    }
    if(!els.stop.value){
      setStatus('Seleciona uma paragem válida antes de guardar.','error',true);
      showToast('Falta selecionar a paragem.','error');
      return;
    }
    const intendedStatus=String(els.status.value||'done').toLowerCase();
    const editingExisting=!!(form&&form.dataset.rpDailyExisting==='1'&&form.dataset.rpDailyEditMode==='1');
    if(intendedStatus==='done'&&!editingExisting&&!window.confirm('Queres mesmo concluir esta visita?\n\nConfirma apenas quando terminares o reporte. Depois de fechar, a loja fica concluída.')) return;
    try{
      setSubmitWorking(true);
      setStatus(form?'A guardar reporte e formulário...':'A guardar reporte...','info',false);
      formFeedback('info','A guardar respostas e a atualizar a visita...',false);
      let formResult=null;
      if(form)formResult=await submitEmbeddedForm();
      if(formResult?.queued_offline){
        const geo=await getGeo().catch(()=>null);
        const status=els.status.value||'done';
        const eventType=status==='failed'?'failure':(status==='in_progress'?'checkin':'checkout');
        await sendEvent(eventType,{note:els.note.value||'',fail_reason:els.fail.value||'',departed_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null,form_mutation_id:formResult.mutation_id||''});
        const pendingMessage='Reporte guardado no dispositivo. A visita será sincronizada automaticamente quando a ligação regressar.';
        formFeedback('info',pendingMessage,true);
        setStatus(pendingMessage,'info',false);
        showToast('Reporte guardado offline.','success');
        document.dispatchEvent(new CustomEvent('fieldflow:offline-queued',{detail:{route_id:parseInt(els.route?.value||0,10),route_stop_id:parseInt(els.stop?.value||0,10),location_id:parseInt(els.location?.value||0,10),mutation_id:formResult.mutation_id||''}}));
        return;
      }
      const status=els.status.value||'done';
      const formCompletion=formResult&&formResult.visit_completion||{};
      const formAlreadyCompleted=!!(formCompletion.applied&&['done','completed'].includes(String(formCompletion.stop_status||'done').toLowerCase())&&status==='done');
      if(!formResult?.updated_existing&&!formAlreadyCompleted){
        const geo=await getGeo();
        const payload={note:els.note.value||'',fail_reason:els.fail.value||'',departed_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null};
        const eventType=status==='failed'?'failure':(status==='in_progress'?'checkin':'checkout');
        let operationalResult=null;
        try{
          const eventResult=await sendEvent(eventType,payload);
          if(eventResult&&eventResult.queued_offline){
            const pendingMessage='Visita guardada no dispositivo. Será sincronizada automaticamente quando a ligação regressar.';
            formFeedback('info',pendingMessage,true);
            setStatus(pendingMessage,'info',false);
            showToast('Visita guardada offline.','success');
            document.dispatchEvent(new CustomEvent('fieldflow:offline-queued',{detail:{route_id:parseInt(els.route?.value||0,10),route_stop_id:parseInt(els.stop?.value||0,10),location_id:parseInt(els.location?.value||0,10),mutation_id:eventResult.mutation_id||''}}));
            return;
          }
          const stopRes=await fetch(api+'stops/'+encodeURIComponent(els.stop.value),{method:'PATCH',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({status,note:els.note.value||'',fail_reason:els.fail.value||'',departed_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null})});
          const stopText=await stopRes.text();
          let stopJson={};
          try{stopJson=stopText?JSON.parse(stopText):{};}catch(_){stopJson={message:stopText};}
          if(!stopRes.ok)throw new Error(stopJson.message||'Falha ao atualizar a paragem.');
          operationalResult=stopJson;
        }catch(operationalError){
          if(!formResult)throw operationalError;
          await refreshAndPublishVisit(formResult,null);
          const savedMessage=formResult?.message||'Reporte guardado com sucesso.';
          const completedByForm=!!(formResult?.visit_completion?.applied);
          const warning=completedByForm
            ? savedMessage+' A visita ficou concluída pelo formulário, mas as notas rápidas não foram totalmente sincronizadas.'
            : savedMessage+' A visita ficou pendente de sincronização com a rota e será reconciliada automaticamente.';
          formFeedback('success',savedMessage,true);
          setStatus(warning,'info',false);
          showToast(completedByForm?'Reporte guardado e visita concluída.':'Reporte guardado, sincronização pendente.','success');
          return;
        }
        await refreshAndPublishVisit(formResult,operationalResult);
      }else if(formResult){
        await refreshAndPublishVisit(formResult,null);
      }
      const message=form?(formResult?.message||'Reporte e formulário gravados com sucesso. O histórico da loja foi atualizado.'):'Reporte gravado com sucesso.';
      formFeedback('success',message,true);
      setStatus(message,'success',false);
      showToast(formResult?.updated_existing?'Reporte atualizado com sucesso.':'Gravação concluída e visita atualizada na rota.','success');
    }catch(err){
      if(err&&err.code==='daily_report_exists'){
        try{ await refreshEmbeddedForm(); }catch(_){ }
      }
      if(form&&err&&err.data&&err.data.field&&typeof form.routesproRevealField==='function'){
        form.routesproRevealField(err.data.field,err.data.message||err.message||'Revê o campo obrigatório assinalado.');
      }
      const message=err.message||'Falha ao guardar reporte.';
      formFeedback(err&&err.code==='daily_report_exists'?'warning':'error',message,true);
      setStatus(message,err&&err.code==='daily_report_exists'?'info':'error',false);
      showToast(message,err&&err.code==='daily_report_exists'?'success':'error');
    }finally{
      setSubmitWorking(false);
    }
  });
})();
</script>
<?php
        return ob_get_clean();
    }


    private static function report_visit_app($atts = []) {
        $theme = self::front_theme();
        $nonce = wp_create_nonce('wp_rest');
        $today = current_time('Y-m-d');
        $route_id = absint($atts['route_id'] ?? ($_GET['route_id'] ?? 0));
        $stop_id = absint($atts['stop_id'] ?? ($_GET['stop_id'] ?? 0));
        $date_label = date_i18n('l, j \d\e F \d\e Y', current_time('timestamp'));
        ob_start(); ?>
<style>
.rp-report-focus{font-family:<?php echo esc_attr($theme['font']); ?>;font-size:<?php echo esc_attr($theme['size']); ?>px;color:#0f172a}
.rp-report-focus *{box-sizing:border-box}.rp-report-focus .rpf-context{display:grid;grid-template-columns:minmax(180px,.7fr) minmax(260px,1.3fr);gap:12px;padding:16px;border:1px solid #e2e8f0;border-radius:20px;background:#f8fafc}.rp-report-focus .rpf-field{min-width:0}.rp-report-focus .rpf-label{display:block;margin-bottom:6px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b}.rp-report-focus .rpf-date{display:flex;align-items:center;min-height:54px;padding:12px 14px;border:1px solid #dbe4ee;border-radius:14px;background:#fff;font-size:17px;font-weight:800}.rp-report-focus select,.rp-report-focus textarea{width:100%;min-height:54px;border:1px solid #cbd5e1;border-radius:14px;padding:13px 14px;background:#fff;color:#0f172a;font-size:16px;line-height:1.35}.rp-report-focus textarea{min-height:104px;resize:vertical}.rp-report-focus .rpf-status{margin-top:12px;padding:12px 14px;border:1px solid #bfdbfe;border-radius:14px;background:#eff6ff;color:#1d4ed8;font-size:14px;line-height:1.5}.rp-report-focus .rpf-status.success{border-color:#86efac;background:#ecfdf3;color:#166534;font-weight:800}.rp-report-focus .rpf-status.error{border-color:#fecaca;background:#fef2f2;color:#991b1b}.rp-report-focus .rpf-retry{margin-top:10px;min-height:44px;max-width:100%;width:auto}.rp-report-focus .rpf-retry[hidden]{display:none!important}.rp-report-focus .rpf-form-slot{margin-top:16px}.rp-report-focus .rpf-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.rp-report-focus .rpf-btn{display:inline-flex;align-items:center;justify-content:center;min-height:50px;border:0;border-radius:14px;padding:12px 18px;background:<?php echo esc_attr($theme['accent']); ?>;color:#fff!important;font-weight:800;cursor:pointer;text-decoration:none}.rp-report-focus .rpf-btn.primary{background:<?php echo esc_attr($theme['primary']); ?>}.rp-report-focus .rpf-btn.ghost{background:#fff;color:#0f172a!important;border:1px solid #cbd5e1}.rp-report-focus .rpf-btn:disabled{opacity:.55;cursor:not-allowed}.rp-report-focus .rpf-submit{width:100%;margin-top:18px}.rp-report-focus .rpf-failure{display:none;margin-top:12px;padding:14px;border:1px solid #fecaca;border-radius:16px;background:#fff7f7}.rp-report-focus .rpf-failure.open{display:block}.rp-report-focus .rpf-failure-grid{display:grid;gap:10px}.rp-report-focus .rpf-empty{padding:16px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc}.rp-report-focus .rpf-toast{position:fixed;left:50%;bottom:calc(24px + env(safe-area-inset-bottom,0px));transform:translateX(-50%) translateY(18px);z-index:99999;max-width:min(560px,calc(100vw - 28px));padding:14px 18px;border-radius:16px;background:#0f172a;color:#fff;font-size:15px;font-weight:800;box-shadow:0 22px 54px rgba(15,23,42,.3);opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease}.rp-report-focus .rpf-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
@media(max-width:760px){.rp-report-focus .rpf-context{grid-template-columns:1fr;padding:12px}.rp-report-focus .rpf-actions{flex-direction:column}.rp-report-focus .rpf-actions .rpf-btn{width:100%}}
/* 9CI fieldflow-visit-closure-selector-return-v1: regresso ao seletor de lojas depois de fechar a visita. */
.rp-report-focus{--rpf-closure-accent:<?php echo esc_attr((string)($theme['primary_solid'] ?? (($theme['primary'] ?? '') === 'transparent' || empty($theme['primary']) ? '#2563eb' : $theme['primary']))); ?>}
.rp-report-focus .rpf-context{scroll-margin-top:calc(16px + env(safe-area-inset-top,0px));transition:box-shadow .35s ease,border-color .35s ease}
.rp-report-focus .rpf-context.is-attention{border-color:var(--rpf-closure-accent);box-shadow:0 0 0 4px rgba(37,99,235,.18);box-shadow:0 0 0 4px color-mix(in srgb,var(--rpf-closure-accent) 22%,transparent)}
.rp-report-focus .rpf-closure{display:grid;gap:12px;margin-top:12px;padding:14px 16px;border:1px solid #86efac;border-radius:18px;background:#ecfdf3;color:#14532d}
.rp-report-focus .rpf-closure[hidden]{display:none!important}
.rp-report-focus.is-closure-return #rpf-submit,.rp-report-focus.is-closure-return #rpf-secondary-actions,.rp-report-focus.is-closure-return #rpf-failure-panel{display:none!important}
.rp-report-focus .rpf-closure.is-offline{border-color:#fcd34d;background:#fffbeb;color:#78350f}
.rp-report-focus .rpf-closure.is-failed{border-color:#cbd5e1;background:#f8fafc;color:#1e293b}
.rp-report-focus .rpf-closure-head{display:flex;align-items:flex-start;gap:12px}
.rp-report-focus .rpf-closure-icon{display:inline-flex;flex:0 0 30px;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#16a34a;color:#fff;font-size:15px;font-weight:900;line-height:1}
.rp-report-focus .rpf-closure.is-offline .rpf-closure-icon{background:#d97706}
.rp-report-focus .rpf-closure.is-failed .rpf-closure-icon{background:#475569}
.rp-report-focus .rpf-closure-title{font-size:15px;font-weight:800;line-height:1.3}
.rp-report-focus .rpf-closure-meta{margin-top:2px;font-size:13px;line-height:1.45;opacity:.9}
.rp-report-focus .rpf-next{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;min-height:56px;padding:12px 16px;border:1px solid var(--rpf-closure-accent);border-radius:14px;background:#fff;color:#0f172a;font:inherit;font-weight:800;text-align:left;cursor:pointer}
.rp-report-focus .rpf-next small{display:block;margin-bottom:2px;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#64748b}
.rp-report-focus .rpf-next-arrow{display:inline-flex;flex:0 0 34px;align-items:center;justify-content:center;width:34px;height:34px;border-radius:999px;background:var(--rpf-closure-accent);color:#fff;font-size:18px}
.rp-report-focus .rpf-next:focus-visible{outline:3px solid var(--rpf-closure-accent);outline-offset:2px}
@media (prefers-reduced-motion:reduce){.rp-report-focus .rpf-context{transition:none}}
</style>
<div class="rp-report-focus" id="routespro-report-focus" data-nonce="<?php echo esc_attr($nonce); ?>" data-date="<?php echo esc_attr($today); ?>" data-route-id="<?php echo esc_attr((string)$route_id); ?>" data-stop-id="<?php echo esc_attr((string)$stop_id); ?>">
  <div class="rpf-context">
    <div class="rpf-field">
      <span class="rpf-label">Dia</span>
      <div class="rpf-date" id="rpf-date-label"><?php echo esc_html($date_label); ?></div>
    </div>
    <div class="rpf-field">
      <label class="rpf-label" for="rpf-store">Loja a visitar</label>
      <select id="rpf-store"><option value="">A carregar lojas...</option></select>
    </div>
  </div>
  <div class="rpf-closure" id="rpf-closure" role="status" aria-live="polite" data-fieldflow-visit-closure="fieldflow-visit-closure-selector-return-v1" hidden></div>
  <input type="hidden" id="rpf-client" value="">
  <input type="hidden" id="rpf-project" value="">
  <input type="hidden" id="rpf-route" value="<?php echo esc_attr((string)$route_id); ?>">
  <input type="hidden" id="rpf-stop" value="<?php echo esc_attr((string)$stop_id); ?>">
  <input type="hidden" id="rpf-location" value="">
  <div class="rpf-status" id="rpf-status" role="status" aria-live="polite">A preparar as visitas atribuídas.</div>
  <button type="button" class="rpf-btn ghost rpf-retry" id="rpf-retry" data-retry-action="form" hidden>Tentar novamente</button>
  <div class="rpf-form-slot" id="rpf-form-slot"></div>
  <div class="rpf-actions" id="rpf-secondary-actions" hidden>
    <button type="button" class="rpf-btn ghost" id="rpf-toggle-failure">Não foi possível realizar a visita</button>
  </div>
  <div class="rpf-failure" id="rpf-failure-panel">
    <div class="rpf-failure-grid">
      <select id="rpf-fail-reason">
        <option value="">Seleciona o motivo</option>
        <option value="Fechado">Loja fechada</option>
        <option value="Sem acesso">Sem acesso à loja</option>
        <option value="Responsável ausente">Responsável ausente</option>
        <option value="Reagendar">Necessário reagendar</option>
        <option value="Outro">Outro motivo</option>
      </select>
      <textarea id="rpf-fail-note" placeholder="Observação, opcional"></textarea>
      <button type="button" class="rpf-btn" id="rpf-save-failure">Registar visita não realizada</button>
    </div>
  </div>
  <button type="button" class="rpf-btn primary rpf-submit" id="rpf-submit" hidden>Concluir visita</button>
  <div class="rpf-toast" id="rpf-toast" role="status" aria-live="assertive"></div>
</div>
<script>
(function(){
  const root=document.getElementById('routespro-report-focus');if(!root)return;
  if(root.dataset.fieldflowReportRuntime==='1')return;
  root.dataset.fieldflowReportRuntime='1';
  const api=<?php echo wp_json_encode(rest_url('routespro/v1/')); ?>;
  const ajaxUrl=<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
  const nonce=root.dataset.nonce||'';
  const els={date:root.querySelector('#rpf-date-label'),store:root.querySelector('#rpf-store'),client:root.querySelector('#rpf-client'),project:root.querySelector('#rpf-project'),route:root.querySelector('#rpf-route'),stop:root.querySelector('#rpf-stop'),location:root.querySelector('#rpf-location'),status:root.querySelector('#rpf-status'),retry:root.querySelector('#rpf-retry'),slot:root.querySelector('#rpf-form-slot'),secondary:root.querySelector('#rpf-secondary-actions'),toggleFailure:root.querySelector('#rpf-toggle-failure'),failurePanel:root.querySelector('#rpf-failure-panel'),failReason:root.querySelector('#rpf-fail-reason'),failNote:root.querySelector('#rpf-fail-note'),saveFailure:root.querySelector('#rpf-save-failure'),submit:root.querySelector('#rpf-submit'),toast:root.querySelector('#rpf-toast')};
  let currentDate=root.dataset.date||new Date().toLocaleDateString('sv-SE');
  let visits=[];let toastTimer=null;let formAvailable=false;let checkinPromise=null;
  let visitLoadSequence=0;let formRenderSequence=0;let activeVisitKey='';let submitting=false;
  const reportUserId=<?php echo (int)get_current_user_id(); ?>;
  let reportRetryInFlight=false,reportRetryLastAt=0;
  const hideReportRetry=()=>{if(els.retry)els.retry.hidden=true;};
  const showReportRetry=(action='form')=>{if(!els.retry)return;els.retry.dataset.retryAction=action;els.retry.disabled=false;els.retry.hidden=false;};
  let dailyFormState={existing:false,can_edit:false,editing:false,locked:false,context_conflict:false};
  const reportContextStorageKey='fieldflow:last-report-visit';
  const readStoredReportContext=()=>{try{const raw=localStorage.getItem(reportContextStorageKey);const data=raw?JSON.parse(raw):{};return data&&typeof data==='object'?data:{};}catch(_){return {};}};
  const writeStoredReportContext=visit=>{if(!visit)return;try{localStorage.setItem(reportContextStorageKey,JSON.stringify({date:currentDate,route_id:parseInt(visit.route_id||0,10)||0,stop_id:parseInt(visit.stop_id||0,10)||0,route_stop_id:parseInt(visit.stop_id||0,10)||0,location_id:parseInt(visit.location_id||0,10)||0,client_id:parseInt(visit.client_id||0,10)||0,project_id:parseInt(visit.project_id||0,10)||0,updated_at:Date.now()}));}catch(_){}};
  const clearStoredReportContext=()=>{try{localStorage.removeItem(reportContextStorageKey);}catch(_){}};
  const storedInitial=readStoredReportContext();
  const initial={route_id:parseInt(root.dataset.routeId||storedInitial.route_id||0,10),stop_id:parseInt(root.dataset.stopId||storedInitial.stop_id||storedInitial.route_stop_id||0,10),location_id:parseInt(storedInitial.location_id||0,10),date:String(storedInitial.date||currentDate||'')};
  if(!root.dataset.routeId&&initial.date)currentDate=initial.date;
  const esc=s=>s==null?'':String(s).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const reportStatusSyncContract='fieldflow-report-status-canonical-sync-v1';
  const reportStatusStorageKey='fieldflow:report-canonical-state:v1';
  const readRecentVisitStates=()=>{try{const raw=localStorage.getItem(reportStatusStorageKey);const parsed=raw?JSON.parse(raw):{};return parsed&&typeof parsed==='object'?new Map(Object.entries(parsed)):new Map();}catch(_){return new Map();}};
  const recentVisitStates=readRecentVisitStates();
  const persistRecentVisitStates=()=>{try{const out={};recentVisitStates.forEach((value,key)=>{out[key]=value;});localStorage.setItem(reportStatusStorageKey,JSON.stringify(out));}catch(_){}};
  const visitStateKey=(routeId,stopId,date)=>String(parseInt(routeId||0,10)||0)+':'+String(parseInt(stopId||0,10)||0)+':'+String(date||currentDate||'');
  const canonicalVisitStatus=value=>{const status=String(value||'pending').toLowerCase();if(['done','completed'].includes(status))return 'done';if(status==='failed')return 'failed';if(['in_progress','active','started'].includes(status))return 'in_progress';return 'pending';};
  const visitStateLabel=status=>{status=canonicalVisitStatus(status);return status==='done'?'concluída':(status==='failed'?'não realizada':(status==='in_progress'?'em curso':'pendente'));};
  const visitOptionLabel=visit=>{const time=visit&&visit.planned_arrival?String(visit.planned_arrival).slice(0,5):'';return String(visit&&visit.location_name||'Loja')+(time?' · '+time:'')+' · '+visitStateLabel(visit&&visit.status);};
  const rememberVisitState=(detail,status)=>{const routeId=parseInt(detail&&detail.route_id||0,10)||0,stopId=parseInt(detail&&(detail.route_stop_id||detail.stop_id)||0,10)||0,date=String(detail&&(detail.route_date||detail.report_day||detail.date)||currentDate||'');if(!routeId||!stopId)return;recentVisitStates.set(visitStateKey(routeId,stopId,date),{status:canonicalVisitStatus(status),at:Date.now()});persistRecentVisitStates();};
  const recentVisitState=visit=>{if(!visit)return null;const row=recentVisitStates.get(visitStateKey(visit.route_id,visit.stop_id,currentDate));if(!row)return null;if(Date.now()-Number(row.at||0)>604800000){recentVisitStates.delete(visitStateKey(visit.route_id,visit.stop_id,currentDate));persistRecentVisitStates();return null;}return row;};
  const updateVisitOption=visit=>{if(!visit||!els.store)return;const option=Array.from(els.store.options||[]).find(item=>String(item.value||'')===String(visit.stop_id||''));if(option){option.textContent=visitOptionLabel(visit);option.dataset.status=canonicalVisitStatus(visit.status);}};
  const applyVisitState=(detail,status,options={})=>{const routeId=parseInt(detail&&detail.route_id||0,10)||0,stopId=parseInt(detail&&(detail.route_stop_id||detail.stop_id)||0,10)||0,date=String(detail&&(detail.route_date||detail.report_day||detail.date)||currentDate||'');if(date&&String(currentDate)&&date!==String(currentDate))return false;const next=canonicalVisitStatus(status||detail&&detail.stop_status||'pending');if(options.remember!==false)rememberVisitState({route_id:routeId,route_stop_id:stopId,route_date:date},next);const visit=visits.find(item=>(!routeId||parseInt(item.route_id||0,10)===routeId)&&parseInt(item.stop_id||0,10)===stopId);if(!visit)return false;visit.status=next;visit.canonical_status=next==='done'?'completed':next;updateVisitOption(visit);if(String(els.store.value||'')===String(stopId)){if(next==='done'&&options.announce)setStatus('Esta visita já foi concluída. Podes consultar ou atualizar o reporte, quando permitido.','success');else if(next==='failed'&&options.announce)setStatus('Esta visita está marcada como não realizada.','success');els.secondary.hidden=['done','failed'].includes(next);}return true;};
  // 9CV fieldflow-report-context-recovery-9cv-v1: never expose MySQL credentials, SQL or wp-config.php from an interrupted WordPress response.
  const isReportDatabaseError=raw=>{
    let value=raw;
    if(value&&typeof value==='object')value=value.message||(value.data&&value.data.message)||value.code||'';
    return /error establishing a database connection|erro ao estabelecer.*(?:base de dados|banco de dados)|wp-config\.php|mysqli|mariadb|SQLSTATE|database server.*down|database connection.*(?:failed|lost)|wordpress database error|\bwpdb\b/i.test(String(value||''));
  };
  const serverErrorMessage=(raw,fallback='Não foi possível concluir o pedido.')=>{
    let value=raw;
    if(value&&typeof value==='object')value=value.message||(value.data&&value.data.message)||value.code||'';
    value=String(value||'').trim();
    if(!value)return fallback;
    if(isReportDatabaseError(value))return 'A base de dados está temporariamente indisponível. A visita não foi alterada. Se já tinhas respostas, mantêm-se no dispositivo quando guardadas localmente. Tenta novamente dentro de momentos.';
    if(/<!doctype|<html|erro crítico neste site|critical error|fatal error|stack trace|\bwarning:|\bnotice:|\bSQL\s+(?:select|update|insert|delete)\b/i.test(value))return 'O servidor encontrou um problema temporário. Conserva as respostas já preenchidas e tenta novamente dentro de momentos.';
    // Use inert plain text only: never inject server HTML into an active DOM node.
    value=value.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();
    return value.length>300?value.slice(0,297)+'...':value;
  };
  const nowIso=()=>new Date().toISOString();
  const setStatus=(message,type='info')=>{els.status.textContent=type==='error'?serverErrorMessage(message,'Não foi possível concluir o pedido.'):message;els.status.classList.remove('success','error');if(type!=='info')els.status.classList.add(type);hideReportRetry();};
  const showToast=message=>{els.toast.textContent=message;els.toast.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>els.toast.classList.remove('show'),3800);};
  const applyDailyFormState=detail=>{
    const form=els.slot.querySelector('.routespro-dyn-form');
    const fallback={existing:!!(form&&form.dataset.rpDailyExisting==='1'),can_edit:!!(form&&form.dataset.rpDailyCanEdit==='1'),editing:!!(form&&form.dataset.rpDailyEditMode==='1'),context_conflict:!!(form&&form.dataset.rpDailyContextConflict==='1')};
    dailyFormState=Object.assign({},fallback,detail||{});dailyFormState.locked=!!(dailyFormState.existing&&!dailyFormState.editing);
    if(!els.submit)return;
    els.submit.dataset.rpDailyLocked=dailyFormState.locked?'1':'0';
    els.submit.disabled=dailyFormState.locked||submitting;
    els.submit.textContent=dailyFormState.locked?'Reporte já efetuado':(dailyFormState.existing?'Guardar alterações':(formAvailable?'Concluir visita':'Concluir sem formulário'));
  };
  const setWorking=(working,label='A guardar...')=>{[els.submit,els.saveFailure].forEach(button=>{if(!button)return;if(working){button.dataset.label=button.textContent;button.textContent=label;button.disabled=true;}else{if(button===els.submit){applyDailyFormState();}else{button.textContent=button.dataset.label||button.textContent;button.disabled=false;}}});};
  const releaseSubmitUi=()=>{submitting=false;setWorking(false);};
  const syncFocusedWizardActions=detail=>{
    const form=els.slot.querySelector('.routespro-dyn-form');
    const wizard=form?form.querySelector('.routespro-form-wizard'):null;
    if(!form||!wizard)return;
    // O renderer é o único proprietário de Anterior/Seguinte. O shortcode
    // apenas sincroniza a ação final da visita e elimina qualquer controlo
    // legado que tenha sobrevivido a cache de página ou reparação do browser.
    form.querySelectorAll('[data-rp-focused-navigation="1"]').forEach(button=>button.remove());
    const groups=Array.from(form.querySelectorAll('[data-rp-wizard-actions]'));
    let canonical=groups.find(group=>group.parentElement===wizard&&group.dataset.rpNavigationOwner==='form-renderer')||groups.find(group=>group.parentElement===wizard)||null;
    if(canonical){
      canonical.dataset.rpNavigationOwner='form-renderer';
      canonical.dataset.rpNavigationRole='canonical';
      groups.forEach(group=>{if(group!==canonical)group.remove();});
      form.querySelectorAll('[data-rp-next],[data-rp-prev],[data-rp-submit]').forEach(control=>{if(!canonical.contains(control))control.remove();});
    }
    const attrLast=wizard.dataset.rpIsLastVisibleStep==='1';
    const isLast=detail&&typeof detail.is_last==='boolean'?detail.is_last:attrLast;
    els.submit.hidden=!isLast;
    if(isLast)applyDailyFormState();
  };
  root.addEventListener('routespro:wizard-step',event=>syncFocusedWizardActions(event.detail||{}));
  root.addEventListener('fieldflow:daily-edit-state',event=>{if(event.detail)applyDailyFormState(event.detail);syncFocusedWizardActions();});
  const getGeo=()=>new Promise(resolve=>{if(!('geolocation' in navigator))return resolve(null);navigator.geolocation.getCurrentPosition(pos=>resolve({lat:pos.coords.latitude,lng:pos.coords.longitude}),()=>resolve(null),{enableHighAccuracy:true,timeout:8000,maximumAge:60000});});
  const request=async(url,opts={})=>{
    const localOpts=Object.assign({},opts||{}),preferLive=localOpts.fieldflowLive===true;delete localOpts.fieldflowLive;
    const requestOpts=Object.assign({credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce}},localOpts);const method=String(requestOpts.method||'GET').toUpperCase();
    const networkRead=async()=>{const res=await fetch(url,requestOpts);const text=await res.text();let json={};try{json=text?JSON.parse(text):{};}catch(_){json={message:serverErrorMessage(text,'Falha no pedido.')};}if(!res.ok){const error=new Error(serverErrorMessage(json,'Falha no pedido.'));error.status=res.status||0;throw error;}if(json&&typeof json==='object')json._fieldflow_live=true;return json;};
    if(preferLive&&method==='GET'&&navigator.onLine!==false){try{return await networkRead();}catch(error){const status=parseInt(error&&error.status||0,10);if(status>=400&&status<500&&![408,425,429].includes(status))throw error;}}
    if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.fetchJson==='function'&&method==='GET')return window.FieldFlowOffline.fetchJson(url,requestOpts);
    return networkRead();
  };
  const fetchOperationalNetwork=async(url,options={},timeoutMs=8000)=>{
    if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.probeConnectivity==='function'){
      const connectivity=await window.FieldFlowOffline.probeConnectivity(false);
      if(!['online','degraded'].includes(String(connectivity.state||''))){const error=new Error(connectivity.state==='degraded'?'Rede fraca, a usar os dados protegidos no dispositivo.':'Sem ligação utilizável ao FieldFlow.');error.code='fieldflow_network_'+connectivity.state;error.retryable=true;throw error;}
    }
    const controller=('AbortController' in window)?new AbortController():null;const timer=controller?window.setTimeout(()=>controller.abort(),Math.max(2000,parseInt(timeoutMs||8000,10))):null;
    try{return await fetch(url,Object.assign({},options,controller?{signal:controller.signal}:{}));}
    catch(error){if(error&&error.name==='AbortError'){const wrapped=new Error('A ligação está demasiado lenta. Os dados locais foram mantidos.');wrapped.code='fieldflow_network_timeout';wrapped.retryable=true;throw wrapped;}throw error;}
    finally{if(timer)window.clearTimeout(timer);}
  };
  const formatDate=date=>{const parsed=new Date(String(date)+'T12:00:00');return Number.isNaN(parsed.getTime())?String(date):new Intl.DateTimeFormat('pt-PT',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(parsed);};
  let reportViewportContract='fieldflow-form-viewport-shopping-delivery-integrity-v1';reportViewportContract='fieldflow-form-viewport-continuity-v2';
  const reportLegacyViewportLockContract='fieldflow-form-viewport-lock-v1';
  const reportScrollContainer=node=>{
    let current=node&&node.parentElement?node.parentElement:null;
    while(current&&current!==document.body&&current!==document.documentElement){
      const style=window.getComputedStyle?window.getComputedStyle(current):null;
      const overflow=style?String(style.overflowY||''):'';
      if(/auto|scroll|overlay/.test(overflow)&&current.scrollHeight>current.clientHeight+2)return current;
      current=current.parentElement;
    }
    return document.scrollingElement||document.documentElement;
  };
  // 2.2.551 uses passive viewport continuity. The 2.2.550 paint-mask names remain
  // as inert predecessor-validation markers only and are never executed:
  // fieldflow-viewport-paint-mask-v1 | data-fieldflow-viewport-snapshot | stripReportSnapshotRuntime | guardReportViewport | reportSelectionViewportState | fieldflow:form-visual-freeze
  const reportPassiveViewportContract='fieldflow-passive-viewport-continuity-v3'; // Passive continuity deliberately performs no scrollTop/window.scrollTo write. Historical 2.2.551 marker.
  const reportSingleAnchorViewportContract='fieldflow-single-anchor-viewport-continuity-v4';
  let reportViewportLock=null;
  const reportScrollTop=container=>{
    if(!container||container===document.scrollingElement||container===document.documentElement||container===document.body)return Math.max(0,window.pageYOffset||document.documentElement.scrollTop||document.body.scrollTop||0);
    return Math.max(0,container.scrollTop||0);
  };
  const reportSetScrollTop=(container,top)=>{
    top=Math.max(0,Number(top)||0);
    if(!container||container===document.scrollingElement||container===document.documentElement||container===document.body){const style=document.documentElement?.style,previous=style?.scrollBehavior||'';if(style)style.scrollBehavior='auto';window.scrollTo(0,top);if(style)style.scrollBehavior=previous;}
    else{const previous=container.style?.scrollBehavior||'';if(container.style)container.style.scrollBehavior='auto';container.scrollTop=top;if(container.style)container.style.scrollBehavior=previous;}
  };
  const beginReportViewportLock=checkpoint=>{
    if(reportViewportLock)return reportViewportLock;
    const container=checkpoint?.container&&document.documentElement.contains(checkpoint.container)?checkpoint.container:reportScrollContainer(els.slot);
    const lock={container,top:reportScrollTop(container),contract:reportSingleAnchorViewportContract,single_anchor:true};
    reportViewportLock=lock;
    try{root.dispatchEvent(new CustomEvent('fieldflow:viewport-freeze-start',{detail:{contract:lock.contract,scroll_top:lock.top,passive:true}}));}catch(_){}
    return lock;
  };
  const finishReportViewportLock=(checkpoint,lock,restore=true)=>{
    lock=lock||reportViewportLock;if(!lock)return;
    // Single-anchor continuity finalises geometry and performs at most one correction in this task.
    els.slot.style.minHeight='';
    if(restore!==false)restoreReportViewport(checkpoint);
    if(reportViewportLock===lock)reportViewportLock=null;
    try{root.dispatchEvent(new CustomEvent('fieldflow:viewport-freeze-end',{detail:{contract:reportSingleAnchorViewportContract,single_anchor:true}}));}catch(_){}
  };
  let externalMediaViewportLock=null,externalMediaUnlockTimer=0;
  document.addEventListener('fieldflow:form-external-handoff',()=>{
    if(externalMediaUnlockTimer){clearTimeout(externalMediaUnlockTimer);externalMediaUnlockTimer=0;}
    if(!externalMediaViewportLock){const checkpoint=captureReportViewport();externalMediaViewportLock={checkpoint,lock:beginReportViewportLock(checkpoint)};}
    externalMediaUnlockTimer=window.setTimeout(()=>{if(externalMediaViewportLock){finishReportViewportLock(externalMediaViewportLock.checkpoint,externalMediaViewportLock.lock,false);externalMediaViewportLock=null;}externalMediaUnlockTimer=0;},8000);
  });
  document.addEventListener('fieldflow:form-viewport-unlocked',()=>{
    if(!externalMediaViewportLock)return;
    if(externalMediaUnlockTimer){clearTimeout(externalMediaUnlockTimer);externalMediaUnlockTimer=0;}
    const state=externalMediaViewportLock;externalMediaViewportLock=null;finishReportViewportLock(state.checkpoint,state.lock,false);
  });
  const captureReportViewport=()=>{
    const safeTop=80,container=reportScrollContainer(els.slot);
    let anchor=null,questionKey='';
    const active=document.activeElement;
    if(active&&els.slot.contains(active))anchor=active.closest?.('[data-rp-question-wrap]')||null;
    if(!anchor){
      let best=null,bestDistance=Number.POSITIVE_INFINITY;
      els.slot.querySelectorAll('[data-rp-question-wrap]').forEach(item=>{const rect=item.getBoundingClientRect();if(rect.width<=0||rect.height<=0||rect.bottom<safeTop)return;const distance=Math.abs(rect.top-safeTop);if(distance<bestDistance){best=item;bestDistance=distance;}});
      anchor=best;
    }
    if(anchor&&anchor.getAttribute)questionKey=String(anchor.getAttribute('data-rp-question-wrap')||'');
    const rect=anchor?.getBoundingClientRect?.();
    return {question_key:questionKey,anchor_top:rect?rect.top:null,scroll_top:reportScrollTop(container),container,contract:reportViewportContract};
  };
  const reserveReportSlotHeight=()=>{const height=Math.ceil(els.slot.getBoundingClientRect().height||0);if(height>0)els.slot.style.minHeight=height+'px';return height;};
  const restoreReportViewport=checkpoint=>{
    if(!checkpoint)return null;
    const container=checkpoint.container&&document.documentElement.contains(checkpoint.container)?checkpoint.container:reportScrollContainer(els.slot);
    let target=null;
    if(checkpoint.question_key){els.slot.querySelectorAll('[data-rp-question-wrap]').forEach(item=>{if(!target&&String(item.getAttribute('data-rp-question-wrap')||'')===String(checkpoint.question_key))target=item;});}
    if(target&&target.getBoundingClientRect().height>0&&checkpoint.anchor_top!==null){const delta=target.getBoundingClientRect().top-Number(checkpoint.anchor_top);if(Math.abs(delta)>1)reportSetScrollTop(container,reportScrollTop(container)+delta);}
    else if(Number.isFinite(Number(checkpoint.scroll_top))){const desired=Number(checkpoint.scroll_top),current=reportScrollTop(container);if(Math.abs(current-desired)>1)reportSetScrollTop(container,desired);}
    return checkpoint;
  };
  const selectedVisit=()=>visits.find(item=>String(item.stop_id)===String(els.store.value||''))||null;
  const visitKey=visit=>visit?(String(visit.route_id||0)+':'+String(visit.stop_id||0)+':'+String(visit.location_id||0)+':'+String(currentDate||'')):'';
  const syncContext=visit=>{els.client.value=visit?String(visit.client_id||''):'';els.project.value=visit?String(visit.project_id||''):'';els.route.value=visit?String(visit.route_id||''):'';els.stop.value=visit?String(visit.stop_id||''):'';els.location.value=visit?String(visit.location_id||''):'';root.dataset.selectedStopId=visit?String(visit.stop_id||''):'';root.dataset.selectedRouteId=visit?String(visit.route_id||''):'';if(visit)writeStoredReportContext(visit);};
  const syncFormContext=()=>{const form=els.slot.querySelector('.routespro-dyn-form');if(!form)return null;const fields={routespro_client_id:els.client.value,routespro_project_id:els.project.value,routespro_route_id:els.route.value,routespro_stop_id:els.stop.value,routespro_location_id:els.location.value};Object.entries(fields).forEach(([name,value])=>{const input=form.querySelector('[name="'+name+'"]');if(input)input.value=value;});return form;};
  const flushFormDraft=()=>{const form=els.slot.querySelector('.routespro-dyn-form');if(form&&typeof form.routesproFlushDraft==='function')form.routesproFlushDraft({force:true,silent:true});};
  const publish=(status='done',extra={})=>{const detail=Object.assign({contract:reportStatusSyncContract,route_id:parseInt(els.route.value||0,10),route_stop_id:parseInt(els.stop.value||0,10),location_id:parseInt(els.location.value||0,10),stop_status:canonicalVisitStatus(status),route_date:currentDate,report_day:currentDate},extra||{});applyVisitState(detail,detail.stop_status,{remember:true,announce:false});document.dispatchEvent(new CustomEvent('fieldflow:visit-completed',{detail}));};
  async function sendEvent(eventType,payload,contextOverride={}){
    const stopId=parseInt(contextOverride.stop_id||contextOverride.route_stop_id||els.stop.value||0,10);if(!stopId)throw new Error('Seleciona uma loja válida.');
    const routeId=parseInt(contextOverride.route_id||els.route.value||0,10),locationId=parseInt(contextOverride.location_id||els.location.value||0,10);
    const offlineType=eventType==='failure'?'stop_failure':(eventType==='checkin'?'stop_checkin':'stop_checkout');const offlinePayload={route_stop_id:stopId,payload:payload||{}},offlineMeta={route_id:routeId,location_id:locationId};
    if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.enqueueOperation==='function'){
      const connectivity=typeof window.FieldFlowOffline.probeConnectivity==='function'?await window.FieldFlowOffline.probeConnectivity(false):{state:navigator.onLine===false?'offline':'online'};
      if(!['online','degraded'].includes(String(connectivity.state||'')))return window.FieldFlowOffline.enqueueOperation(offlineType,offlinePayload,offlineMeta);
    }
    try{
      const res=await fetchOperationalNetwork(api+'events',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({route_stop_id:stopId,event_type:eventType,payload:payload||{}})},8000);
      const text=await res.text();let json=null;try{json=text?JSON.parse(text):{};}catch(_){const error=new Error('O servidor não confirmou a atualização da visita.');error.code='fieldflow_invalid_json_response';error.status=res.status||0;throw error;}
      if(!res.ok){const error=new Error(serverErrorMessage(json,'Falha ao atualizar a visita.'));error.status=res.status;error.code=json&&json.code||'';throw error;}return json;
    }catch(error){
      const status=parseInt(error&&error.status||0,10);const retryable=!status||status>=500||[401,408,425,429].includes(status)||error.code==='fieldflow_invalid_json_response';
      if(retryable&&window.FieldFlowOffline&&typeof window.FieldFlowOffline.enqueueOperation==='function')return window.FieldFlowOffline.enqueueOperation(offlineType,offlinePayload,offlineMeta);throw error;
    }
  }
  async function ensureVisitDone(formResult,contextOverride={}){
    const stopId=parseInt(contextOverride.stop_id||contextOverride.route_stop_id||els.stop.value||0,10),routeId=parseInt(contextOverride.route_id||els.route.value||0,10);
    if(!stopId||!routeId)throw new Error('Não foi possível confirmar a paragem concluída.');
    const completion=formResult&&formResult.visit_completion||{};
    const completionStatus=String(completion.stop_status||'').toLowerCase();
    if(completion.applied&&['done','completed'].includes(completionStatus||'done')){
      return {verified:true,route_progress:{route_status:String(completion.route_status||'')},visit_completion:completion};
    }
    let routeSnapshot=null,stopSnapshot=null;
    try{
      routeSnapshot=await request(api+'routes/'+routeId+'?_ff='+Date.now());
      stopSnapshot=(Array.isArray(routeSnapshot.stops)?routeSnapshot.stops:[]).find(item=>parseInt(item.id||0,10)===stopId)||null;
    }catch(_){ }
    if(stopSnapshot&&['done','completed'].includes(String(stopSnapshot.status||'').toLowerCase())){
      return {verified:true,route_progress:{route_status:String(routeSnapshot.status||completion.route_status||'')}};
    }
    if(!stopSnapshot&&completion.applied&&['done','completed'].includes(completionStatus)){
      return {verified:true,route_progress:{route_status:String(completion.route_status||'')}};
    }
    const geo=await getGeo();
    const result=await sendEvent('checkout',{departed_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null,source:'form_completion_reconciliation',submission_id:parseInt(formResult&&formResult.submission_id||0,10)||0},contextOverride);
    const verifiedRoute=await request(api+'routes/'+routeId+'?_ff='+Date.now());
    const verifiedStop=(Array.isArray(verifiedRoute.stops)?verifiedRoute.stops:[]).find(item=>parseInt(item.id||0,10)===stopId)||null;
    if(!verifiedStop||!['done','completed'].includes(String(verifiedStop.status||'').toLowerCase())){
      throw new Error('O reporte foi guardado, mas a visita não ficou concluída. Tenta novamente ou atualiza a página.');
    }
    result.verified=true;
    result.route_progress=result.route_progress||{route_status:String(verifiedRoute.status||'')};
    return result;
  }
  async function ensureCheckin(){
    const visit=selectedVisit();if(!visit)return null;
    const status=String(visit.status||'').toLowerCase();
    if(visit.arrived_at||['in_progress','done','completed','failed'].includes(status))return null;
    if(checkinPromise)return checkinPromise;
    checkinPromise=(async()=>{try{const geo=await getGeo();await sendEvent('checkin',{arrived_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null,source:'form_interaction'});visit.arrived_at=nowIso();visit.status='in_progress';setStatus('Visita iniciada automaticamente. Continua o preenchimento do formulário.');}catch(_){setStatus('Podes continuar o formulário. O check-in automático não ficou disponível neste momento.');}finally{checkinPromise=null;}})();
    return checkinPromise;
  }
  function bindAutomaticCheckin(){['focusin','pointerdown','input','change'].forEach(name=>els.slot.addEventListener(name,event=>{if(event.isTrusted&&event.target.closest('.routespro-dyn-form,.rpf-empty'))ensureCheckin();},{passive:true}));}
  async function renderForm(options={}){
    const renderId=++formRenderSequence;
    const visit=selectedVisit();
    syncContext(visit);
    const nextVisitKey=visitKey(visit);
    const existingForm=els.slot.querySelector('.routespro-dyn-form');
    const priorActiveKey=activeVisitKey;
    const sameLiveForm=!!(visit&&nextVisitKey===priorActiveKey&&existingForm);
    const priorSubmitHidden=els.submit.hidden,priorSecondaryHidden=els.secondary.hidden;
    if(sameLiveForm&&!options.force){
      syncFormContext();
      els.submit.hidden=false;
      applyDailyFormState();
      return;
    }
    const viewportCheckpoint=options.viewportCheckpoint||captureReportViewport();
    if((!visit||nextVisitKey!==activeVisitKey)&&window.routesproClearPreviousVisitModal) window.routesproClearPreviousVisitModal();
    reserveReportSlotHeight();
    const viewportLock=beginReportViewportLock(viewportCheckpoint);
    flushFormDraft();
    // A form from shop A must never stay interactive after the selector has switched to shop B.
    if(!sameLiveForm)els.slot.innerHTML='';
    hideReportRetry();
    els.secondary.hidden=true;els.submit.hidden=true;els.failurePanel.classList.remove('open');formAvailable=false;activeVisitKey='';dailyFormState={existing:false,can_edit:false,editing:false,locked:false,context_conflict:false};delete els.submit.dataset.rpDailyLocked;
    if(!visit){els.slot.innerHTML='';setStatus(visits.length?'Seleciona a loja que vais visitar.':'Não tens lojas atribuídas para este dia.');finishReportViewportLock(viewportCheckpoint,viewportLock);return;}
    const status=String(visit.status||'').toLowerCase();
    if(['done','completed'].includes(status))setStatus('Esta visita já foi concluída. Podes consultar ou atualizar o reporte, quando permitido.');else if(status==='failed')setStatus('Esta visita está marcada como não realizada.');else setStatus('A carregar o formulário da loja.');
    const fd=new FormData();fd.set('action','routespro_context_form');fd.set('nonce',nonce);fd.set('client_id',String(visit.client_id||''));fd.set('project_id',String(visit.project_id||''));fd.set('route_id',String(visit.route_id||''));fd.set('stop_id',String(visit.stop_id||''));fd.set('location_id',String(visit.location_id||''));fd.set('date',currentDate);fd.set('return_url',window.location.href||'');
    try{
      const formCacheKey='focused-form-html:<?php echo esc_js(FIELDFLOW_VERSION); ?>:u'+reportUserId+':c'+String(visit.client_id||0)+':p'+String(visit.project_id||0)+':r'+String(visit.route_id||0)+':s'+String(visit.stop_id||0)+':l'+String(visit.location_id||0)+':d'+String(currentDate||'');
      const matchesCachedContext=cached=>{
        if(!cached||!cached.context||!cached.has_form||typeof cached.html!=='string')return false;
        const c=cached.context;
        return ['client_id','project_id','route_id','stop_id','location_id'].every(key=>Number(c[key]||0)===Number(visit[key]||0))&&String(c.date||'')===String(currentDate||'');
      };
      let data=null;
      try{
        const res=await fetchOperationalNetwork(ajaxUrl,{method:'POST',credentials:'same-origin',body:fd},8000);
        const responseText=await res.text();let json={};
        try{json=responseText?JSON.parse(responseText):{};}catch(_){json={success:false,data:{message:serverErrorMessage(responseText,'Falha ao carregar o formulário.')}};}
        if(!res.ok||!json.success){const error=new Error(serverErrorMessage(json&&json.data?json.data:json,'Falha ao carregar o formulário.'));error.status=res.status||0;throw error;}
        data=json.data||{};
        if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.setMeta==='function'&&matchesCachedContext(data))window.FieldFlowOffline.setMeta(formCacheKey,data).catch(()=>{});
      }catch(fetchError){
        if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.getMeta==='function'){
          const cached=await window.FieldFlowOffline.getMeta(formCacheKey).catch(()=>null);
          if(matchesCachedContext(cached))data=cached;
        }
        if(!data)throw fetchError;
        data=Object.assign({},data,{_fieldflow_offline_cached:true});
      }
      if(renderId!==formRenderSequence||visitKey(selectedVisit())!==nextVisitKey){finishReportViewportLock(viewportCheckpoint,viewportLock);return;}
      formAvailable=!!data.has_form;els.slot.innerHTML=formAvailable?(data.html||''):'<div class="rpf-empty"><strong>Sem formulário associado a esta visita.</strong><p>Podes concluir a visita com uma observação operacional.</p><textarea id="rpf-fallback-note" placeholder="Observação da visita, opcional"></textarea></div>';activeVisitKey=nextVisitKey;els.submit.hidden=!formAvailable;els.submit.textContent=formAvailable?'Concluir visita':'Concluir sem formulário';const form=syncFormContext();if(form&&window.routesproInitDynamicForm)window.routesproInitDynamicForm(form);if(form&&window.jQuery)window.jQuery(document).trigger('routespro:form-rendered',[form]);applyDailyFormState();if(form)syncFocusedWizardActions();else els.submit.hidden=false;els.secondary.hidden=['done','completed','failed'].includes(status);if(data._fieldflow_offline_cached)setStatus('Formulário guardado no dispositivo. As respostas ficarão pendentes até regressar a ligação.');finishReportViewportLock(viewportCheckpoint,viewportLock);
    }catch(error){
      if(renderId===formRenderSequence&&visitKey(selectedVisit())===nextVisitKey){
        // Forced reload may fail while an already editable form is on screen: keep it and its draft.
        if(sameLiveForm&&els.slot.contains(existingForm)){
          activeVisitKey=priorActiveKey;formAvailable=true;
          els.submit.hidden=priorSubmitHidden;els.secondary.hidden=priorSecondaryHidden;
          applyDailyFormState();syncFocusedWizardActions();
          setStatus('O servidor está temporariamente indisponível. O formulário e as respostas já preenchidas continuam disponíveis.','error');
        }else setStatus(error,'error');
        // Never retry automatically against a saturated MySQL server.
        const status=Number(error&&error.status||0);
        if(!status||status===200||status>=500||[408,425,429].includes(status))showReportRetry('form');
      }
      finishReportViewportLock(viewportCheckpoint,viewportLock);
    }
  }
  async function loadVisits(date,preferred={}){
    const loadId=++visitLoadSequence;
    const previousDate=currentDate,previousVisits=visits.slice(),previousOptions=els.store.innerHTML,previousSelected=els.store.value;
    hideReportRetry();
    const previouslySelectedStop=parseInt(els.store.value||els.stop.value||root.dataset.selectedStopId||0,10)||0;
    const previouslySelectedRoute=parseInt(els.route.value||root.dataset.selectedRouteId||0,10)||0;
    currentDate=String(date||currentDate);root.dataset.date=currentDate;els.date.textContent=formatDate(currentDate);els.store.innerHTML='<option value="">A carregar lojas...</option>';setStatus('A preparar as visitas atribuídas.');
    try{
      const data=await request(api+'routes?user_id=me&date='+encodeURIComponent(currentDate)+'&_ff='+Date.now(),{fieldflowLive:true});const routes=Array.isArray(data.routes)?data.routes:[];const details=await Promise.all(routes.map(route=>request(api+'routes/'+route.id+'?_ff='+Date.now(),{fieldflowLive:true}).catch(()=>null)));
      if(loadId!==visitLoadSequence)return;
      visits=[];details.filter(Boolean).forEach(route=>{const cached=!!(route&&route._fieldflow_offline&&route._fieldflow_offline.cached);(Array.isArray(route.stops)?route.stops:[]).forEach(stop=>{let visit={route_id:parseInt(route.id||0,10),stop_id:parseInt(stop.id||0,10),location_id:parseInt(stop.location_id||0,10),client_id:parseInt(route.client_id||0,10),project_id:parseInt(route.project_id||0,10),project_name:route.project_name||'',location_name:stop.location_name||'Loja',address:stop.address||'',planned_arrival:stop.planned_arrival||'',status:canonicalVisitStatus(stop.canonical_status||stop.status||'pending'),canonical_status:String(stop.canonical_status||''),status_source:String(stop.status_source||''),arrived_at:stop.arrived_at||''};const remembered=recentVisitState(visit);if(cached&&remembered)visit.status=remembered.status;else if(!cached&&recentVisitStates.delete(visitStateKey(visit.route_id,visit.stop_id,currentDate)))persistRecentVisitStates();visits.push(visit);});});visits.sort((a,b)=>String(a.planned_arrival||'').localeCompare(String(b.planned_arrival||''))||String(a.location_name).localeCompare(String(b.location_name),'pt'));
      const labels=visits.map(visit=>'<option value="'+visit.stop_id+'" data-status="'+canonicalVisitStatus(visit.status)+'">'+esc(visitOptionLabel(visit))+'</option>');els.store.innerHTML='<option value="">Seleciona a loja</option>'+labels.join('');const preferredStop=parseInt(preferred.stop_id||preferred.route_stop_id||previouslySelectedStop||initial.stop_id||0,10);const preferredRoute=parseInt(preferred.route_id||previouslySelectedRoute||initial.route_id||0,10);let match=visits.find(v=>preferredStop&&v.stop_id===preferredStop);if(!match&&preferredRoute)match=visits.find(v=>v.route_id===preferredRoute);if(!match&&preferred.location_id)match=visits.find(v=>v.location_id===parseInt(preferred.location_id||0,10));if(!match&&visits.length===1)match=visits[0];if(match)els.store.value=String(match.stop_id);
      // 9CI: depois de fechar uma visita o seletor fica livre. Só uma escolha feita pelo operativo entretanto é preservada.
      if(preferred.selection==='none'){const liveSelectedStop=parseInt(root.dataset.selectedStopId||0,10)||0;match=liveSelectedStop?(visits.find(v=>v.stop_id===liveSelectedStop)||null):null;els.store.value=match?String(match.stop_id):'';}
      await renderForm({force:!!preferred.force});
    }catch(error){
      if(loadId!==visitLoadSequence)return;
      if(previousDate===currentDate&&previousVisits.length){
        visits=previousVisits;els.store.innerHTML=previousOptions;els.store.value=previousSelected;
        setStatus('Não foi possível atualizar as lojas. Mantivemos a lista anteriormente carregada neste dispositivo.','error');
      }else{
        // A list from another date must not remain interactive after a failed day switch.
        flushFormDraft();els.slot.innerHTML='';activeVisitKey='';formAvailable=false;
        els.submit.hidden=true;els.secondary.hidden=true;
        visits=[];els.store.innerHTML='<option value="">Não foi possível carregar as lojas</option>';
        setStatus(error,'error');
      }
      const status=Number(error&&error.status||0);
      if(!status||status>=500||[408,425,429].includes(status))showReportRetry('visits');
    }
  }
  if(els.retry)els.retry.addEventListener('click',async()=>{
    if(reportRetryInFlight||Date.now()-reportRetryLastAt<4000)return;
    reportRetryInFlight=true;reportRetryLastAt=Date.now();els.retry.disabled=true;
    const action=String(els.retry.dataset.retryAction||'form');
    try{
      if(action==='form'&&selectedVisit())await renderForm({force:true,source:'manual-server-retry'});
      else await loadVisits(currentDate,{stop_id:parseInt(els.store.value||0,10)||0});
    }finally{reportRetryInFlight=false;if(!els.retry.hidden)els.retry.disabled=false;}
  });
  // 9CI Visit Closure Selector Return (fieldflow-visit-closure-selector-return-v1).
  // Depois de uma visita terminal (concluída, guardada offline, não realizada ou
  // reporte editado), o Report deixa de reabrir a loja fechada: limpa a seleção,
  // mostra o cartão de fecho com a próxima loja pendente e faz um único scroll
  // até ao seletor "Loja a visitar". Não é um guard contínuo de viewport (9AH).
  const visitClosureSelectorContract='fieldflow-visit-closure-selector-return-v1';
  const closureEl=root.querySelector('#rpf-closure');
  const storeContextEl=root.querySelector('.rpf-context');
  let lastVisitClosure=null,closureAttentionTimer=0;
  const closureTimeLabel=()=>{try{return new Intl.DateTimeFormat('pt-PT',{hour:'2-digit',minute:'2-digit'}).format(new Date());}catch(_){return '';}};
  const closureContextFromSelection=()=>({route_id:parseInt(els.route.value||0,10)||0,stop_id:parseInt(els.stop.value||0,10)||0,location_id:parseInt(els.location.value||0,10)||0,date:currentDate});
  const nextPendingVisit=excludeStopId=>visits.find(visit=>String(visit.stop_id)!==String(excludeStopId||'')&&!['done','failed'].includes(canonicalVisitStatus(visit.status)))||null;
  const syncClosureIdleState=()=>{root.classList.toggle('is-closure-return',!!(lastVisitClosure&&!root.dataset.selectedStopId&&!els.store.value));};
  const hideClosureCard=()=>{lastVisitClosure=null;if(closureEl){closureEl.hidden=true;closureEl.innerHTML='';}syncClosureIdleState();};
  const renderClosureCard=(closed,state)=>{
    if(!closureEl)return;
    const titles={done:'Visita concluída',updated:'Alterações guardadas',offline:'Visita guardada no dispositivo',failed:'Visita não realizada registada',failed_offline:'Visita não realizada guardada no dispositivo'};
    const offline=state==='offline'||state==='failed_offline';
    const failed=state==='failed'||state==='failed_offline';
    const closedName=String(closed&&closed.location_name||'Loja');
    const closedAt=String(lastVisitClosure&&lastVisitClosure.at||closureTimeLabel());
    const meta=closedName+(closedAt?' · '+closedAt:'')+(offline?' · será sincronizada quando a rede voltar':'');
    const next=nextPendingVisit(closed&&closed.stop_id);
    let html='<div class="rpf-closure-head"><span class="rpf-closure-icon" aria-hidden="true">'+(failed?'!':'✓')+'</span><div><div class="rpf-closure-title">'+esc(titles[state]||titles.done)+'</div><div class="rpf-closure-meta">'+esc(meta)+'</div></div></div>';
    if(next){const time=next.planned_arrival?String(next.planned_arrival).slice(0,5):'';html+='<button type="button" class="rpf-next" data-rpf-next-stop="'+esc(next.stop_id)+'"><span><small>Próxima loja</small>'+esc(next.location_name||'Loja')+(time?' · '+esc(time):'')+'</span><span class="rpf-next-arrow" aria-hidden="true">→</span></button>';}
    else if(visits.length)html+='<div class="rpf-closure-meta">Todas as lojas deste dia estão fechadas.</div>';
    closureEl.className='rpf-closure'+(offline?' is-offline':(failed?' is-failed':''));
    closureEl.innerHTML=html;closureEl.hidden=false;
  };
  const scrollToStoreSelector=()=>{
    const target=storeContextEl||els.store;if(!target||!document.documentElement.contains(target))return;
    const container=reportScrollContainer(target);
    const isDocument=!container||container===document.scrollingElement||container===document.documentElement||container===document.body;
    const reduce=!!(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const adminBar=document.getElementById('wpadminbar');
    const offset=Math.max(12,(adminBar&&adminBar.offsetParent!==null?adminBar.getBoundingClientRect().height:0)+12);
    const base=isDocument?0:container.getBoundingClientRect().top;
    const current=reportScrollTop(container);
    const top=Math.max(0,current+target.getBoundingClientRect().top-base-offset);
    if(Math.abs(current-top)>4){
      try{if(isDocument)window.scrollTo({top,behavior:reduce?'auto':'smooth'});else container.scrollTo({top,behavior:reduce?'auto':'smooth'});}
      catch(_){reportSetScrollTop(container,top);}
    }
    if(storeContextEl){storeContextEl.classList.add('is-attention');clearTimeout(closureAttentionTimer);closureAttentionTimer=window.setTimeout(()=>storeContextEl.classList.remove('is-attention'),1800);}
    if(window.matchMedia&&window.matchMedia('(hover: hover) and (pointer: fine)').matches){try{els.store.focus({preventScroll:true});}catch(_){}}
  };
  async function returnToStoreSelector(closedContext,state){
    const stopId=parseInt(closedContext&&(closedContext.stop_id||closedContext.route_stop_id)||0,10)||0;
    const closed=visits.find(visit=>String(visit.stop_id)===String(stopId))||closedContext||null;
    if(state==='failed'||state==='failed_offline'){if(closed&&closed.status!==undefined)closed.status='failed';}else if(closed&&closed.status!==undefined)closed.status='done';
    if(closed&&closed.stop_id)updateVisitOption(closed);
    lastVisitClosure={closed,state,at:closureTimeLabel()};
    clearStoredReportContext();
    // O formulário fechado sai do DOM antes da troca, para nenhum flush voltar a criar rascunho da visita concluída.
    els.slot.innerHTML='';
    els.store.value='';
    try{els.store.blur();}catch(_){}
    await renderForm({force:true,source:'visit-closure-return'});
    renderClosureCard(closed,state);
    syncClosureIdleState();
    // O shell da app limpa o checkpoint de resume: um resume não reabre a loja fechada.
    try{document.dispatchEvent(new CustomEvent('fieldflow:report-selection-changed',{detail:{date:currentDate,source:'visit-closure-return',contract:visitClosureSelectorContract}}));}catch(_){}
    window.requestAnimationFrame(()=>window.requestAnimationFrame(scrollToStoreSelector));
    try{root.dispatchEvent(new CustomEvent('fieldflow:visit-closure-return',{bubbles:true,detail:{contract:visitClosureSelectorContract,stop_id:stopId,state:String(state||'done'),next_stop_id:parseInt((nextPendingVisit(stopId)||{}).stop_id||0,10)||0}}));}catch(_){}
  }
  async function refreshVisitsAfterClosure(date){
    const keepStop=parseInt(els.store.value||0,10)||0;
    await loadVisits(date||currentDate,keepStop?{stop_id:keepStop}:{selection:'none'});
    if(!els.store.value&&lastVisitClosure&&closureEl&&!closureEl.hidden)renderClosureCard(lastVisitClosure.closed&&visits.find(visit=>String(visit.stop_id)===String(lastVisitClosure.closed.stop_id))||lastVisitClosure.closed,lastVisitClosure.state);
    syncClosureIdleState();
  }
  if(closureEl)closureEl.addEventListener('click',event=>{
    const button=event.target&&event.target.closest?event.target.closest('[data-rpf-next-stop]'):null;if(!button)return;
    const nextStop=String(button.getAttribute('data-rpf-next-stop')||'');if(!nextStop||!visits.some(visit=>String(visit.stop_id)===nextStop))return;
    event.preventDefault();
    els.store.value=nextStop;
    els.store.dispatchEvent(new Event('change',{bubbles:true}));
  });
  els.store.addEventListener('change',()=>{if(els.store.value)hideClosureCard();});
  // Qualquer seleção posterior (dropdown, Próxima loja, resume ou fieldflow:select-visit) fecha o cartão.
  if('MutationObserver' in window)new MutationObserver(()=>{if(root.dataset.selectedStopId&&lastVisitClosure)hideClosureCard();else syncClosureIdleState();}).observe(root,{attributes:true,attributeFilter:['data-selected-stop-id']});
  async function submitForm(){
    if(submitting)return;
    const form=syncFormContext();
    if(form&&form.dataset.rpDailyExisting==='1'&&form.dataset.rpDailyEditMode!=='1'){
      const message=form.dataset.rpDailyContextConflict==='1'?'Este reporte pertence a outra visita e só pode ser editado no contexto original.':'O reporte desta loja já foi efetuado. Escolhe “Editar reporte” para corrigir respostas.';
      setStatus(message,'error');submitting=false;applyDailyFormState();return;
    }
    if(form&&window.routesproValidateDynamicForm&&!window.routesproValidateDynamicForm(form,{announce:true})){
      setStatus('Revê as respostas obrigatórias antes de concluir.','error');
      return;
    }
    const editingExisting=!!(form&&form.dataset.rpDailyExisting==='1'&&form.dataset.rpDailyEditMode==='1');
    if(!editingExisting&&!window.confirm('Queres mesmo concluir esta visita?\n\nConfirma apenas quando terminares o reporte. Depois de fechar, a loja fica concluída.')) return;
    submitting=true;
    try{
      if(form){try{form.dispatchEvent(new CustomEvent('fieldflow:form-submit-intent',{bubbles:true,detail:{channel:'focused',runtime_owner:String(form.dataset.rpRuntimeOwner||'legacy'),external_submit:true,step_index:parseInt(form.querySelector('.routespro-form-wizard')?.dataset.rpCurrentStep||'0',10)||0}}));}catch(_){}}
      if(form&&typeof form.routesproPrepareFinalSubmit==='function')await Promise.resolve(form.routesproPrepareFinalSubmit());else if(form&&typeof form.routesproFlushDraft==='function')form.routesproFlushDraft({force:true,silent:true,localOnly:true,immediateServer:false});
      setWorking(true);
      setStatus('A guardar o formulário e a concluir a visita.');
      // O check-in já é tentado durante a interação. A conclusão do formulário é a autoridade final e não deve esperar por um segundo pedido.
      let formResult=null;
      let operationalResult=null;
      if(form){
        const offlineMeta={route_id:parseInt(els.route.value||0,10),route_stop_id:parseInt(els.stop.value||0,10),location_id:parseInt(els.location.value||0,10),report_day:currentDate||''};
        if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.submitForm==='function'){
          formResult=await window.FieldFlowOffline.submitForm(form,offlineMeta,{nonce:nonce});
        }else{
          try{
            if(typeof form.routesproSubmitAjax==='function'){
              formResult=await form.routesproSubmitAjax({nonce:nonce,maxRetries:1,timeout:120000});
            }else{
              const fd=new FormData(form);fd.set('routespro_ajax','1');
              const submitEndpoint=(window.FieldFlowMobile&&window.FieldFlowMobile.native&&form.dataset.rpSubmitAjaxEndpoint)?form.dataset.rpSubmitAjaxEndpoint:form.getAttribute('action');
              const res=await fetch(submitEndpoint,{method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':nonce,'X-Requested-With':'XMLHttpRequest'},body:fd});
              const text=await res.text();let json={};try{json=text?JSON.parse(text):{};}catch(_){json={success:false,data:{message:serverErrorMessage(text,'Falha ao guardar o formulário.')}};}if(!res.ok||!json.success)throw new Error(serverErrorMessage(json&&json.data?json.data:json,'Falha ao guardar o formulário.'));formResult=json.data||{};
            }
          }catch(error){
            if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.queueForm==='function'&&((navigator.onLine===false&&error.safeToRetry!==false)||error.safeToRetry===true))formResult=await window.FieldFlowOffline.queueForm(form,offlineMeta);else throw error;
          }
        }
        if(formResult&&!formResult.queued_offline&&typeof form.routesproMarkSaved==='function')form.routesproMarkSaved(formResult);
        if(formResult&&formResult.queued_offline){
          const geo=await getGeo().catch(()=>null);
          operationalResult=await sendEvent('checkout',{departed_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null,source:'offline_form_completion',form_mutation_id:formResult.mutation_id||''});
          const visit=selectedVisit();if(visit)visit.status='done';publish('done');
          setStatus('Reporte guardado no dispositivo. A visita será sincronizada quando a ligação regressar.','success');
          showToast('Reporte guardado offline.');
          document.dispatchEvent(new CustomEvent('fieldflow:offline-queued',{detail:{route_id:parseInt(els.route.value||0,10),route_stop_id:parseInt(els.stop.value||0,10),location_id:parseInt(els.location.value||0,10),mutation_id:formResult.mutation_id||''}}));
          await returnToStoreSelector(closureContextFromSelection(),'offline');
          return;
        }
        const visit=selectedVisit();if(visit)visit.status='done';publish('done');
        const completedContext={route_id:parseInt(els.route.value||0,10),stop_id:parseInt(els.stop.value||0,10),location_id:parseInt(els.location.value||0,10),date:currentDate};
        setStatus(formResult.message||'Reporte guardado com sucesso. A rota está a ser atualizada.','success');
        showToast(form&&form.dataset.rpDailyExisting==='1'?'Alterações guardadas.':'Visita concluída.');
        releaseSubmitUi();
        await returnToStoreSelector(completedContext,editingExisting?'updated':'done');
        window.setTimeout(async()=>{
          try{
            operationalResult=await ensureVisitDone(formResult,completedContext);
            if(String(currentDate)===String(completedContext.date))await refreshVisitsAfterClosure(completedContext.date);
          }catch(reconcileError){
            console.warn('FieldFlow route reconciliation pending',reconcileError);
            setStatus('O reporte ficou guardado. A atualização da rota será repetida automaticamente.','success');
          }
        },0);
        return;
      }else{
        const note=els.slot.querySelector('#rpf-fallback-note')?.value||'';
        const geo=await getGeo();
        operationalResult=await sendEvent('checkout',{note,departed_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null,source:'fallback_report'});
        if(operationalResult&&operationalResult.queued_offline){
          const visit=selectedVisit();if(visit)visit.status='done';publish('done');
          setStatus('Visita guardada no dispositivo. Será sincronizada quando a ligação regressar.','success');
          showToast('Visita guardada offline.');
          await returnToStoreSelector(closureContextFromSelection(),'offline');
          return;
        }
        setStatus('Visita concluída com sucesso.','success');
      }
      const visit=selectedVisit();
      if(visit)visit.status='done';
      publish('done');
      showToast('Visita concluída.');
      releaseSubmitUi();
      const fallbackClosedContext=closureContextFromSelection();
      await returnToStoreSelector(fallbackClosedContext,'done');
      window.setTimeout(()=>refreshVisitsAfterClosure(fallbackClosedContext.date).catch(()=>{}),0);
    }catch(error){
      if(form&&error&&error.data&&error.data.field&&typeof form.routesproRevealField==='function'){
        form.routesproRevealField(error.data.field,error.data.message||error.message||'Revê o campo obrigatório assinalado.');
      }
      setStatus(serverErrorMessage(error&&error.message?error.message:error,'Falha ao concluir a visita.'),'error');
    }finally{
      submitting=false;
      setWorking(false);
    }
  }
  async function saveFailure(){
    const reason=String(els.failReason.value||'').trim();if(!reason){setStatus('Seleciona o motivo da visita não realizada.','error');return;}
    try{
      setWorking(true,'A registar...');
      const geo=await getGeo();
      const result=await sendEvent('failure',{fail_reason:reason,note:els.failNote.value||'',departed_at:nowIso(),real_lat:geo?.lat||null,real_lng:geo?.lng||null,source:'app_failure'});
      const visit=selectedVisit();if(visit)visit.status='failed';els.failurePanel.classList.remove('open');publish('failed');
      const failureClosedContext=closureContextFromSelection();
      els.failReason.value='';if(els.failNote)els.failNote.value='';
      if(result&&result.queued_offline){setStatus('Ocorrência guardada no dispositivo. Será sincronizada quando a ligação regressar.','success');showToast('Ocorrência guardada offline.');await returnToStoreSelector(failureClosedContext,'failed_offline');return;}
      setStatus('Visita não realizada registada com sucesso.','success');showToast('Ocorrência registada.');await returnToStoreSelector(failureClosedContext,'failed');refreshVisitsAfterClosure(failureClosedContext.date).catch(()=>{});
    }catch(error){setStatus(error.message||'Falha ao registar a ocorrência.','error');}finally{setWorking(false);}
  }
  document.addEventListener('fieldflow:visit-completed',event=>{
    const detail=event&&event.detail||{};
    const status=canonicalVisitStatus(detail.stop_status||'done');
    applyVisitState(detail,status,{remember:true,announce:true});
  });
  const refreshAfterOfflineSync=event=>{
    const item=event&&event.detail&&event.detail.item||{};
    const itemStop=parseInt(item.meta&&item.meta.route_stop_id||item.payload&&item.payload.route_stop_id||item.payload&&item.payload.stop_id||0,10)||0;
    const currentStop=parseInt(els.stop.value||0,10)||0;
    if(!itemStop||!currentStop||itemStop!==currentStop||submitting)return;
    const type=String(item.type||'');
    if(item.kind!=='form'&&!['stop_checkout','stop_failure'].includes(type))return;
    window.setTimeout(()=>loadVisits(currentDate,{stop_id:currentStop,force:true}).catch(()=>{}),120);
  };
  document.addEventListener('fieldflow:offline-form-synced',refreshAfterOfflineSync);
  document.addEventListener('fieldflow:offline-operation-synced',refreshAfterOfflineSync);
  const reportSelectionDetail=visit=>visit?{date:currentDate,route_id:parseInt(visit.route_id||0,10)||0,stop_id:parseInt(visit.stop_id||0,10)||0,route_stop_id:parseInt(visit.stop_id||0,10)||0,location_id:parseInt(visit.location_id||0,10)||0,client_id:parseInt(visit.client_id||0,10)||0,project_id:parseInt(visit.project_id||0,10)||0,source:'manual-store-dropdown'}:{date:currentDate,source:'manual-store-dropdown'};
  // 2.2.552 pre-picker anchor: capture before the native selector can alter focus/scroll.
  let reportSelectionAnchor=null;
  els.store.addEventListener('pointerdown',event=>{if(!event.isTrusted)return;reportSelectionAnchor=captureReportViewport();try{document.dispatchEvent(new CustomEvent('fieldflow:report-selection-intent',{detail:{source:'manual-store-dropdown',viewport_contract:reportSingleAnchorViewportContract}}));}catch(_){ }});
  els.store.addEventListener('change',event=>{
    const viewportCheckpoint=reportSelectionAnchor||captureReportViewport();reportSelectionAnchor=null;
    try{els.store.blur({preventScroll:true});}catch(_){try{els.store.blur();}catch(__){ }}
    restoreReportViewport(viewportCheckpoint);
    const visit=selectedVisit();
    if(visit)writeStoredReportContext(visit);else clearStoredReportContext();
    try{document.dispatchEvent(new CustomEvent('fieldflow:report-selection-changed',{detail:reportSelectionDetail(visit)}));}catch(_){ }
    renderForm({force:false,source:event&&event.isTrusted?'manual-store-dropdown':'programmatic-store-selection',viewportCheckpoint});
  });
  els.slot.addEventListener('submit',event=>{const form=event.target&&event.target.closest?event.target.closest('.routespro-dyn-form'):null;if(!form)return;event.preventDefault();event.stopImmediatePropagation();submitForm();},true);
  els.slot.addEventListener('click',event=>{if(event.target.closest('[data-rp-next],[data-rp-prev],[data-rp-submit]')){const visit=selectedVisit();if(visit){syncContext(visit);syncFormContext();}}},true);
  els.toggleFailure.addEventListener('click',()=>els.failurePanel.classList.toggle('open'));els.saveFailure.addEventListener('click',saveFailure);els.submit.addEventListener('click',submitForm);root.addEventListener('fieldflow:select-visit',event=>{const detail=event.detail||{};loadVisits(detail.date||currentDate,detail);});bindAutomaticCheckin();loadVisits(currentDate,initial);
})();
</script>
<?php
        return ob_get_clean();
    }


    public static function performance_dashboard($atts = []) {
        if (class_exists('\\RoutesPro\\Front\\PerformanceShortcodeRenderer')) {
            return \RoutesPro\Front\PerformanceShortcodeRenderer::dashboard($atts);
        }
        return '<p>FieldFlow Performance não está disponível.</p>';
    }

    public static function academy($atts = []) {
        if (class_exists('\\RoutesPro\\Front\\PerformanceShortcodeRenderer')) {
            return \RoutesPro\Front\PerformanceShortcodeRenderer::academy($atts);
        }
        return '<p>FieldFlow Academy não está disponível.</p>';
    }

    public static function billing_portal($atts = []) {
        return \RoutesPro\Front\BillingShortcodeRenderer::render(is_array($atts) ? $atts : []);
    }

    public static function subscription_signup($atts = []) {
        return \RoutesPro\Front\SignupShortcodeRenderer::render(is_array($atts) ? $atts : []);
    }

}
