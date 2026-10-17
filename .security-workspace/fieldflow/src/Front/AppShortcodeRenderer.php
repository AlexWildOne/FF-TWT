<?php
namespace RoutesPro\Front;

class AppShortcodeRenderer {
    public static function app($atts = []) {
        $guard = \RoutesPro\Shortcodes::front_guard();
        if ($guard) return $guard;
        if (class_exists('\\RoutesPro\\PWA\\InstallPrompt')) { \RoutesPro\PWA\InstallPrompt::markAppRendered(); }
        $user = wp_get_current_user();
        $today = current_time('Y-m-d');
        $today_label = date_i18n('d/m/Y', current_time('timestamp'));
        $can_manage = current_user_can('routespro_manage');
        $can_scope = \RoutesPro\Support\Permissions::can_access_front();
        $scope = \RoutesPro\Support\Permissions::get_scope();
        $scope_client_ids = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
        $scope_project_ids = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));
        $initial_client_id = absint($atts['client_id'] ?? ($_GET['client_id'] ?? 0));
        $initial_project_id = absint($atts['project_id'] ?? ($_GET['project_id'] ?? 0));
        [$initial_client_id, $initial_project_id] = \RoutesPro\Support\Permissions::resolve_front_context($initial_client_id, $initial_project_id);
        $saas_has = static function(string $capability) use ($initial_client_id): bool {
            if (!class_exists('\RoutesPro\SaaS\EntitlementService')) return true;
            return $initial_client_id > 0
                ? \RoutesPro\SaaS\EntitlementService::clientHas($initial_client_id, $capability)
                : \RoutesPro\SaaS\EntitlementService::userHasAny($capability);
        };
        $allow_briefings = $saas_has('briefings.view');
        $allow_implementations = $saas_has('implementations.view');
        $allow_stocks = $saas_has('stocks.view');
        $allow_shopping = $saas_has('reporting.submit');
        $allow_discovery = $can_manage ? $saas_has('commercial.view') : $saas_has('routes.create');
        $allow_reporting = $saas_has('reporting.submit');
        $allow_academy = $saas_has('academy.view');
        $allow_expenses = $saas_has('expenses.use');
        $allow_messages = $saas_has('messages.use');
        $allow_analytics = $saas_has('analytics.basic');
        $theme = \RoutesPro\Shortcodes::front_theme($initial_client_id, $initial_project_id);
        $visible_primary = $theme['primary_solid'] ?? (($theme['primary'] === 'transparent') ? '#2563eb' : $theme['primary']);
        $visible_accent = $theme['accent_solid'] ?? (($theme['accent'] === 'transparent') ? '#06b6d4' : $theme['accent']);
        $theme_classes = \RoutesPro\Support\Theme::root_classes($theme);
        $theme_style = \RoutesPro\Support\Theme::inline_style($theme);
        $campaign_logo = (string)($theme['logo_url'] ?? '');
        $message_id = absint($_GET['routespro_message'] ?? 0);
        $ui_composition_enabled = (bool)apply_filters('fieldflow_ui_composition_enabled', true, 'operative');
        $paint_stability_enabled = (bool)apply_filters('fieldflow_portal_paint_stability_enabled', true, 'operative');
        // 9D9: Operative uses the exact 9D5 controller proven in the Client portal.
        // Both inline and footer delivery are idempotent; legacy 9D6/9D7/9D8 do not run.
        $shared_dock_enabled = (!defined('FIELDFLOW_VISUAL_CONSOLIDATION_9D5') || FIELDFLOW_VISUAL_CONSOLIDATION_9D5)
            && (!defined('FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9') || FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9);
        // Retain explicit 9D8 rollback, without combining two competing controllers.
        // 9D8 is emitted by the shortcode itself: Elementor/PWA page detection cannot suppress it.
        $dock9d8_enabled = !$shared_dock_enabled && (!defined('FIELDFLOW_OPERATIVE_DOCK_AUTHORITY_9D8') || FIELDFLOW_OPERATIVE_DOCK_AUTHORITY_9D8);
        // 9DC: dedicated scroll controller for the actual Operative tabbar. Client remains 9D5.
        // Reversible without touching the certified 9D9 fallback.
        $dock9dc_enabled = $shared_dock_enabled && (!defined('FIELDFLOW_OPERATIVE_SCROLL_AUTHORITY_9DC') || FIELDFLOW_OPERATIVE_SCROLL_AUTHORITY_9DC);
        // 9DE is independent from legacy 9D9/9DC gates: a real 2.2.638
        // device showed the rendered bar with controller=absent.
        // Explicit false restores the 9DC / 9D9 workflow without changing data.
        $dock9de_enabled = (!defined('FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE') || FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE);
        $dock9d8_enabled = $dock9d8_enabled && !$dock9de_enabled;
        // 9DD: diagnostic-only, explicitly requested. No data or external telemetry.
        $dock_diag_requested = isset($_GET['ff_dock_diag']) && is_scalar($_GET['ff_dock_diag']) && (string)$_GET['ff_dock_diag'] === '1';
        if ($shared_dock_enabled && function_exists('wp_enqueue_script')) {
            wp_enqueue_script('fieldflow-visual-consolidation-9d5', ROUTESPRO_URL . 'assets/fieldflow-visual-consolidation-9d5.js', [], ROUTESPRO_VERSION, true);
        }
        // Phase 8I: React mirrors the complete canonical tab order. The PHP tabbar
        // remains the fallback and sole panel authority, React only proxies navigation.
        $react_front_panels = [['key'=>'route','label'=>'Minha Rota']];
        if ($allow_briefings) $react_front_panels[] = ['key'=>'briefings','label'=>'Briefing'];
        if ($allow_implementations) $react_front_panels[] = ['key'=>'implementations','label'=>'Implementações'];
        if ($allow_stocks) $react_front_panels[] = ['key'=>'stocks','label'=>'Stocks'];
        if ($allow_shopping) $react_front_panels[] = ['key'=>'shopping','label'=>'Shopping'];
        if ($allow_discovery) $react_front_panels[] = ['key'=>'discovery','label'=>$can_manage ? 'Descobrir' : 'Planear'];
        if ($allow_reporting) $react_front_panels[] = ['key'=>'report','label'=>'Reportar'];
        if ($allow_academy) $react_front_panels[] = ['key'=>'academy','label'=>'Academy'];
        if ($allow_expenses) $react_front_panels[] = ['key'=>'expenses','label'=>'Despesas'];
        if ($allow_messages) $react_front_panels[] = ['key'=>'messages','label'=>'Mensagens'];
        if (($can_manage || $can_scope) && $allow_analytics) $react_front_panels[] = ['key'=>'analytics','label'=>'Analytics'];
        $react_shell_enabled = class_exists('\RoutesPro\Front\ReactFrontFoundation')
            && \RoutesPro\Front\ReactFrontFoundation::islandEnabled('operational-shell');
        $react_shell_fallback_id = 'ff-react-operational-shell-fallback';
        $react_shell_props = $react_shell_enabled ? [
            'contract' => \RoutesPro\Front\ReactFrontFoundation::CONTRACT,
            'shellContract' => \RoutesPro\Front\ReactFrontFoundation::OPERATIONAL_SHELL_CONTRACT,
            'fullwidthNavigationContract' => \RoutesPro\Front\ReactFrontFoundation::FULLWIDTH_NAVIGATION_CONTRACT,
            'island' => \RoutesPro\Front\ReactFrontFoundation::OPERATIONAL_SHELL_ISLAND,
            'fallbackId' => $react_shell_fallback_id,
            'rootId' => 'routespro-app',
            'panels' => $react_front_panels,
        ] : [];
        ob_start(); ?>
    <style>
<?php if ($dock9d8_enabled && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-authority-9d8.css')) {
    echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-authority-9d8.css');
} ?>
<?php if ($shared_dock_enabled): ?>
/* 9D9: identical Client 9D5 dock-hidden CSS applied to Operative; no 9D6/9D7/9D8 owner. */
@media (max-width:760px) {
  #routespro-app[data-fieldflow-dock-controller="client-9d5-9d9"].ffv-visual-9d5 .rp-tabs.ffv-dock-hidden {
    transform:translate3d(0,calc(100% + 36px + env(safe-area-inset-bottom,0px)),0)!important;
    opacity:0!important;pointer-events:none!important;
  }
  #routespro-app[data-fieldflow-dock-controller="client-9d5-9d9"].ff-scroll-extent-safe .rp-shell {
    padding-bottom:calc(var(--ffv-dock-height,66px) + 42px + env(safe-area-inset-bottom,0px))!important;
  }
  #routespro-app[data-fieldflow-dock-controller="client-9d5-9d9"] .rp-panels {
    scroll-padding-bottom:calc(var(--ffv-dock-height,66px) + 18px)!important;
  }
}
<?php endif; ?>
<?php if ($dock9dc_enabled && !$dock9de_enabled && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-scroll-9dc.css')) {
    // 9DC: inline CSS survives Elementor pages that do not reach the standard enqueue.
    echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-scroll-9dc.css');
} ?>
<?php if ($dock9de_enabled && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-bootstrap-9de.css')) {
    echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-bootstrap-9de.css');
} ?>
<?php if ($dock_diag_requested && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-audit-9dd.css')) {
    echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-audit-9dd.css');
} ?>
    .rp-premium-shell{--rp-primary:<?php echo esc_attr($theme['primary']); ?>;--rp-accent:<?php echo esc_attr($theme['accent']); ?>;--rp-primary-solid:<?php echo esc_attr($visible_primary); ?>;--rp-accent-solid:<?php echo esc_attr($visible_accent); ?>;--rp-font:<?php echo esc_attr($theme['font']); ?>;--rp-size:<?php echo esc_attr($theme['size']); ?>px;font-family:var(--rp-font);font-size:var(--rp-size);color:var(--ff-text,#0f172a)}
    .rp-premium-shell *{box-sizing:border-box}
    .rp-premium-shell .rp-shell{width:100%;max-width:none;margin:0;padding:18px 16px 104px}
    .rp-premium-shell .rp-hero{position:relative;overflow:hidden;background:radial-gradient(circle at top right,rgba(255,255,255,.18),transparent 28%),linear-gradient(135deg,#081120 0%,#162338 52%,<?php echo esc_attr($theme['primary']); ?> 120%);color:#fff;border-radius:30px;padding:28px;box-shadow:0 28px 70px rgba(15,23,42,.2)}
    .rp-premium-shell .rp-hero-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:18px;align-items:end}
    .rp-premium-shell .rp-eyebrow{font-size:12px;text-transform:uppercase;letter-spacing:.09em;opacity:.82;margin-bottom:8px}
    .rp-premium-shell .rp-hero h2{margin:0 0 10px;font-size:36px;line-height:1.02;font-weight:800;color:#fff}
    .rp-premium-shell .rp-hero p{margin:0;max-width:760px;opacity:.92;font-size:16px;line-height:1.6;color:#fff}
    .rp-premium-shell .rp-mini{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
    .rp-premium-shell .rp-mini span{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.12);color:#fff;border-radius:999px;padding:8px 12px;font-weight:700;border:1px solid rgba(255,255,255,.14)}
    .rp-premium-shell .ff-react-front-operational-fallback{align-items:center;padding:8px 0}.rp-premium-shell .ff-react-front-operational-fallback span{background:#f8fafc;color:#334155;border-color:#e2e8f0;box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .rp-premium-shell .rp-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}
    .rp-premium-shell .rp-stat{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.14);border-radius:20px;padding:16px;min-height:108px}
    .rp-premium-shell .rp-stat span,.rp-premium-shell .rp-stat small{color:rgba(255,255,255,.8)}
    .rp-premium-shell .rp-stat strong{display:block;font-size:26px;line-height:1.04;margin:6px 0;color:#fff}
    .rp-premium-shell .rp-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:16px}
    .rp-premium-shell .rp-card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 16px 40px rgba(15,23,42,.08);padding:20px}
    .rp-premium-shell .rp-kicker{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:8px}
    .rp-premium-shell .rp-cta{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:14px;padding:12px 16px;background:var(--rp-primary);color:#fff !important;font-weight:700;text-decoration:none;cursor:pointer}
    .rp-premium-shell .rp-cta.alt{background:var(--rp-accent)}
    .rp-premium-shell .rp-cta.ghost{background:#fff;color:#0f172a !important;border:1px solid #cbd5e1}
    .rp-premium-shell .rp-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:#eff6ff;color:#0f172a;padding:7px 12px;font-size:12px;font-weight:700;border:1px solid #dbeafe}
    .rp-premium-shell .rp-panels{margin-top:18px;padding-bottom:108px}
    .rp-premium-shell .rp-panel{display:none}
    .rp-premium-shell .rp-panel.active{display:block}
    .rp-premium-shell .rp-panel .rp-premium-card{background:#fff;border:1px solid #e2e8f0;border-radius:26px;box-shadow:0 16px 40px rgba(15,23,42,.08);padding:16px}
    .rp-premium-shell .rp-panel-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
    .rp-premium-shell .rp-panel-head h3{margin:0;font-family:var(--ff-font-headings,var(--rp-font));font-size:24px;color:var(--ff-heading,#0f172a)}
    .rp-premium-shell .rp-panel-head p{margin:6px 0 0;color:var(--ff-muted,#64748b);max-width:720px}
    .rp-premium-shell .rp-tabs{position:sticky;bottom:14px;z-index:25;margin-top:18px;display:block;padding-bottom:max(0px, env(safe-area-inset-bottom))}
    .rp-premium-shell .rp-tabbar{display:flex;gap:8px;background:rgba(15,23,42,.94);padding:10px;border-radius:22px;box-shadow:0 20px 48px rgba(15,23,42,.3);overflow:auto;-webkit-overflow-scrolling:touch}
    .rp-premium-shell .rp-tabbar button{flex:1 1 0;min-width:118px;border:0;border-radius:16px;padding:12px 10px;background:transparent;color:#cbd5e1;font-weight:700;cursor:pointer;white-space:nowrap}
    .rp-premium-shell .rp-tabbar button.active{background:#fff;color:#0f172a}
    .rp-premium-shell .rp-shell .rp-panel[data-panel="route"] #routespro-my-daily-route{max-width:none;width:100%}
    .rp-premium-shell .rp-shell .rp-panel[data-panel="route"] .rp-wrap{border-radius:22px;box-shadow:none;border-color:#e2e8f0;background:#fff}
    .rp-premium-shell .rp-shell .rp-panel[data-panel="discovery"] .rp-front-routes .rp-card,
    .rp-premium-shell .rp-shell .rp-panel[data-panel="report"] .rp-report-quick .rp-card,
    .rp-premium-shell .rp-shell .rp-panel[data-panel="analytics"] .routespro-dashboard .card{box-shadow:none}
    .rp-premium-shell .rp-panel[data-panel="academy"] .ffp-academy{margin:0;background:transparent;padding:0}.rp-premium-shell .rp-panel[data-panel="academy"] .ffp-academy-head{box-shadow:none}
    .rp-premium-shell .rp-cta,.rp-premium-shell .rp-shell .rp-btn{background:var(--rp-primary-solid);color:#fff !important;border-color:var(--rp-primary-solid)}
    .rp-premium-shell .rp-cta.alt,.rp-premium-shell .rp-shell .rp-btn.alt,.rp-premium-shell .rp-shell .rp-btn.secondary{background:var(--rp-accent-solid);border-color:var(--rp-accent-solid);color:#fff !important}
    .rp-premium-shell .rp-cta.ghost,.rp-premium-shell .rp-shell .rp-btn.ghost{background:#fff;color:#0f172a !important;border:1px solid #cbd5e1}
    .rp-premium-shell .rp-route-switch{display:flex;gap:8px;flex-wrap:wrap;padding:6px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:16px;margin-bottom:14px;width:max-content;max-width:100%}
    .rp-premium-shell .rp-route-switch button{border:0;border-radius:12px;padding:10px 14px;background:transparent;color:#475569;font-weight:800;cursor:pointer}
    .rp-premium-shell .rp-route-switch button.active{background:#fff;color:#0f172a;box-shadow:0 6px 18px rgba(15,23,42,.08)}
    .rp-premium-shell .rp-route-view-panel{display:none}.rp-premium-shell .rp-route-view-panel.active{display:block}
    .rp-premium-shell .rpa-month-shell{border:1px solid #e2e8f0;border-radius:22px;background:#fff;padding:16px}
    .rp-premium-shell .rpa-month-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px}
    .rp-premium-shell .rpa-month-head h4{margin:0;font-size:18px}.rp-premium-shell .rpa-month-controls{display:flex;gap:8px;align-items:center}.rp-premium-shell .rpa-month-controls button{border:1px solid #cbd5e1;background:#fff;border-radius:10px;padding:8px 11px;cursor:pointer;font-weight:800}.rp-premium-shell .rpa-month-label{min-width:150px;text-align:center;font-weight:900;text-transform:capitalize}
    .rp-premium-shell .rpa-weekdays,.rp-premium-shell .rpa-month-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:7px}.rp-premium-shell .rpa-weekdays{margin-bottom:7px}.rp-premium-shell .rpa-weekdays span{text-align:center;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:800}
    .rp-premium-shell .rpa-day{min-height:96px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;padding:9px;text-align:left;cursor:pointer;color:#0f172a}.rp-premium-shell .rpa-day:hover,.rp-premium-shell .rpa-day:focus{border-color:var(--rp-primary-solid);box-shadow:0 0 0 3px rgba(15,23,42,.06)}.rp-premium-shell .rpa-day.empty{background:#f8fafc;border-style:dashed;cursor:default}.rp-premium-shell .rpa-day.today{border-color:var(--rp-primary-solid)}.rp-premium-shell .rpa-day strong{display:block;font-size:14px}.rp-premium-shell .rpa-day small{display:block;margin-top:8px;color:#64748b;line-height:1.35}.rp-premium-shell .rpa-day .rpa-progress{height:5px;margin-top:8px;border-radius:999px;background:#e2e8f0;overflow:hidden}.rp-premium-shell .rpa-day .rpa-progress i{display:block;height:100%;background:linear-gradient(90deg,var(--rp-primary-solid),var(--rp-accent-solid))}
    .rp-premium-shell .rpa-month-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.rp-premium-shell .rpa-month-summary div{padding:12px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.rp-premium-shell .rpa-month-summary span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:800}.rp-premium-shell .rpa-month-summary strong{display:block;font-size:22px;margin-top:5px}
    .rp-premium-shell .rpa-month-agenda{display:flex;flex-direction:column;gap:8px;margin-top:14px;width:100%;max-width:100%;min-width:0}.rp-premium-shell .rpa-agenda-item{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;width:100%;max-width:100%;min-width:0;overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;padding:12px;background:#fff;cursor:pointer;text-align:left;color:#0f172a!important;appearance:none;-webkit-appearance:none}.rp-premium-shell .rpa-agenda-item>div{min-width:0}.rp-premium-shell .rpa-agenda-item h5{margin:0 0 4px;font-size:16px;line-height:1.25;font-weight:850;color:#0f172a;white-space:normal;overflow-wrap:anywhere;word-break:break-word}.rp-premium-shell .rpa-agenda-item p{margin:0;color:#64748b;font-size:13px;line-height:1.4;white-space:normal;overflow-wrap:anywhere}.rp-premium-shell .rpa-agenda-item .rp-pill{flex:0 0 auto;white-space:nowrap;justify-self:end}.rp-premium-shell .rpa-empty{padding:22px;text-align:center;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b}
    .rp-premium-shell .rp-panel[data-panel="analytics"] .rp-premium-card{padding:0;border:0;background:transparent;box-shadow:none}.rp-premium-shell .rp-panel[data-panel="analytics"]>.rp-premium-card>.rp-panel-head{padding:18px 18px 0}.rp-premium-shell .rp-panel[data-panel="analytics"] .routespro-dashboard{margin-top:0}
    @media (max-width:1100px){.rp-premium-shell .rp-hero-grid,.rp-premium-shell .rp-grid,.rp-premium-shell .rp-stats{grid-template-columns:1fr 1fr}}
    @media (max-width:760px){.rp-premium-shell .rp-shell{padding:14px 12px 108px}.rp-premium-shell .rp-hero h2{font-size:28px}.rp-premium-shell .rp-hero-grid,.rp-premium-shell .rp-grid,.rp-premium-shell .rp-stats{grid-template-columns:1fr}.rp-premium-shell .rpa-month-shell{padding:10px;overflow:hidden}.rp-premium-shell .rpa-month-head{align-items:stretch}.rp-premium-shell .rpa-month-controls{width:100%;justify-content:space-between}.rp-premium-shell .rpa-month-label{min-width:0;flex:1}.rp-premium-shell .rpa-month-summary{grid-template-columns:1fr 1fr}.rp-premium-shell .rpa-weekdays{display:none}.rp-premium-shell .rpa-month-grid{grid-template-columns:1fr}.rp-premium-shell .rpa-day.empty{display:none}.rp-premium-shell .rpa-day{min-height:0;width:100%;max-width:100%}.rp-premium-shell .rpa-agenda-item{grid-template-columns:minmax(0,1fr) auto;padding:12px 10px;gap:8px}.rp-premium-shell .rpa-agenda-item h5{font-size:15px}.rp-premium-shell .rpa-agenda-item p{font-size:12px}.rp-premium-shell .rpa-agenda-item .rp-pill{padding:6px 9px;font-size:12px}.rp-premium-shell .routespro-form-wizard-actions{position:static!important;bottom:auto!important;background:#fff!important;margin-bottom:22px!important;padding:14px 0 0!important;border-top:1px solid #eef2f7!important}.rp-premium-shell .rp-report-quick .rp-submit-wrap{margin-bottom:96px}}
    
/* 2.2.122 premium front layout polish */
.rp-premium-shell{background:linear-gradient(180deg,#f8fafc 0%,#eef4fb 100%);border-radius:34px;padding:clamp(6px,1vw,14px);width:100%;max-width:100%}
.rp-premium-shell .rp-shell{width:100%;max-width:none;margin:0;padding:clamp(16px,2vw,28px) clamp(10px,1.6vw,22px) calc(118px + env(safe-area-inset-bottom,0px))}
.rp-premium-shell .rp-hero{border-radius:34px;padding:clamp(22px,2.4vw,34px);box-shadow:0 30px 80px rgba(15,23,42,.20)}
.rp-premium-shell .rp-card,.rp-premium-shell .rp-panel .rp-premium-card{border-color:#e5edf6;box-shadow:0 18px 42px rgba(15,23,42,.07), inset 0 1px 0 rgba(255,255,255,.75);backdrop-filter:blur(8px)}
.rp-premium-shell .rp-panel .rp-premium-card{padding:clamp(16px,2vw,24px)}
.rp-premium-shell .rp-panel-head h3{font-size:clamp(22px,2vw,28px);letter-spacing:-.03em}
.rp-premium-shell .rp-tabs{padding-inline:4px}
.rp-premium-shell .rp-panel .rp-table-wrap,.rp-premium-shell .rp-panel .ffp-table-wrap,.rp-premium-shell .rp-panel .ffb-table-wrap{display:block;width:100%;max-width:100%;overflow:auto;-webkit-overflow-scrolling:touch;border:1px solid #e2e8f0;border-radius:22px;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);box-shadow:0 14px 34px rgba(15,23,42,.05), inset 0 1px 0 rgba(255,255,255,.75)}
.rp-premium-shell .rp-panel table,.rp-premium-shell .rp-panel .ffp-table,.rp-premium-shell .rp-panel .ffb-table-wrap table{width:max-content;min-width:840px;border-collapse:separate;border-spacing:0}
.rp-premium-shell .rp-panel thead th{position:sticky;top:0;z-index:2;background:rgba(248,250,252,.96);backdrop-filter:blur(10px)}
.rp-premium-shell .rp-panel th,.rp-premium-shell .rp-panel td{padding:12px 14px;vertical-align:top}
.rp-premium-shell .rp-panel tbody tr:nth-child(even){background:#fbfdff}.rp-premium-shell .rp-panel tbody tr:hover{background:#fff7ed}
.rp-premium-shell .rp-panel .rp-report-quick .rp-card,.rp-premium-shell .rp-panel .rp-team-inbox .rp-mail-list,.rp-premium-shell .rp-panel .rp-team-inbox .rp-mail-detail,.rp-premium-shell .rp-panel .ffb-briefing,.rp-premium-shell .rp-panel .ffp-card{border-color:#e5edf6;box-shadow:0 14px 34px rgba(15,23,42,.06)}
.rp-premium-shell .rp-panel .ffp-front,.rp-premium-shell .rp-panel .ff-briefings,.rp-premium-shell .rp-panel .ff-implementations{border-radius:28px}
@media (max-width:760px){.rp-premium-shell{border-radius:24px;padding:6px}.rp-premium-shell .rp-shell{max-width:100%;padding:14px 10px calc(116px + env(safe-area-inset-bottom,0px))}.rp-premium-shell .rp-hero{border-radius:24px;padding:20px}.rp-premium-shell .rp-card,.rp-premium-shell .rp-panel .rp-premium-card{border-radius:20px;padding:14px}.rp-premium-shell .rp-panel table,.rp-premium-shell .rp-panel .ffp-table,.rp-premium-shell .rp-panel .ffb-table-wrap table{min-width:720px}}


/* 2.2.181, sistema visual premium configurável */
.rp-premium-shell{background:var(--ff-canvas)!important;color:var(--ff-text)!important;font-family:var(--ff-font)!important;font-size:var(--ff-font-size)!important;border-radius:calc(var(--ff-radius) + 10px)!important}
.rp-premium-shell h1,.rp-premium-shell h2,.rp-premium-shell h3,.rp-premium-shell h4,.rp-premium-shell h5,.rp-premium-shell h6{font-family:var(--ff-font-headings)!important;color:var(--ff-text)}
.rp-premium-shell .rp-brandline{display:flex;align-items:center;gap:12px;min-height:34px;margin-bottom:16px}.rp-premium-shell .rp-brand-logo{display:block;width:auto;object-fit:contain}.rp-premium-shell .rp-brand-logo-core{height:30px;filter:brightness(0) invert(1);opacity:.92}.rp-premium-shell .rp-brand-logo-campaign{max-height:38px;max-width:180px;padding:7px 10px;border-radius:10px;background:rgba(255,255,255,.94)}
.rp-premium-shell .rp-hero{background:radial-gradient(circle at 92% 8%,rgba(255,255,255,.18),transparent 30%),linear-gradient(135deg,var(--ff-header-start),var(--ff-header-end))!important;border-radius:var(--ff-radius)!important;box-shadow:var(--ff-shadow)!important}
.rp-premium-shell.fft-header-aurora .rp-hero{background:radial-gradient(circle at 84% 10%,rgba(34,211,238,.45),transparent 22%),radial-gradient(circle at 12% 92%,rgba(168,85,247,.38),transparent 30%),linear-gradient(135deg,var(--ff-header-start),var(--ff-header-end))!important}
.rp-premium-shell.fft-header-solid .rp-hero{background:var(--ff-header-start)!important}.rp-premium-shell.fft-header-light .rp-hero{background:linear-gradient(135deg,var(--ff-header-start),var(--ff-header-end))!important;color:var(--ff-text)!important;border:1px solid var(--ff-border)}
.rp-premium-shell.fft-header-light .rp-hero h2,.rp-premium-shell.fft-header-light .rp-hero p,.rp-premium-shell.fft-header-light .rp-eyebrow,.rp-premium-shell.fft-header-light .rp-stat strong,.rp-premium-shell.fft-header-light .rp-stat span,.rp-premium-shell.fft-header-light .rp-stat small{color:var(--ff-text)!important}.rp-premium-shell.fft-header-light .rp-brand-logo-core{filter:none}.rp-premium-shell.fft-header-light .rp-stat{background:rgba(255,255,255,.72);border-color:var(--ff-border)}
.rp-premium-shell .rp-card,.rp-premium-shell .rp-panel .rp-premium-card,.rp-premium-shell .rpa-month-shell,.rp-premium-shell .routespro-dashboard .ffad-card,.rp-premium-shell .routespro-dashboard .ffad-kpi,.rp-premium-shell .routespro-dashboard .ffad-custom-dashboard{background:var(--ff-surface)!important;border-color:var(--ff-border)!important;border-radius:var(--ff-radius)!important;box-shadow:var(--ff-shadow)!important}
.rp-premium-shell.fft-card-outline .rp-card,.rp-premium-shell.fft-card-outline .rp-panel .rp-premium-card,.rp-premium-shell.fft-card-outline .routespro-dashboard .ffad-card,.rp-premium-shell.fft-card-outline .routespro-dashboard .ffad-kpi{box-shadow:none!important}
.rp-premium-shell.fft-card-glass .rp-card,.rp-premium-shell.fft-card-glass .rp-panel .rp-premium-card{background:color-mix(in srgb,var(--ff-surface) 82%,transparent)!important;backdrop-filter:blur(18px)}
.rp-premium-shell .rp-panel-head{padding-bottom:calc(8px * var(--ff-density));border-bottom:1px solid color-mix(in srgb,var(--ff-border) 72%,transparent)}.rp-premium-shell .rp-panel-head h3{letter-spacing:-.035em}.rp-premium-shell .rp-panel-head p,.rp-premium-shell .rp-kicker,.rp-premium-shell .rp-meta{color:var(--ff-muted)!important}
.rp-premium-shell.fft-compact-headers .rp-panel-head{margin-bottom:12px}.rp-premium-shell.fft-compact-headers .rp-panel-head h3{font-size:clamp(19px,1.7vw,24px)!important}.rp-premium-shell.fft-compact-headers .rp-panel-head p{font-size:12px;line-height:1.45;margin-top:4px}
.rp-premium-shell .rp-cta,.rp-premium-shell button,.rp-premium-shell .rp-btn,.rp-premium-shell input,.rp-premium-shell select,.rp-premium-shell textarea{border-radius:var(--ff-button-radius)}.rp-premium-shell input,.rp-premium-shell select,.rp-premium-shell textarea{border-color:var(--ff-border)!important;color:var(--ff-text)!important;background:var(--ff-surface)!important}.rp-premium-shell .rp-cta,.rp-premium-shell .rp-btn{transition:transform .16s ease,box-shadow .16s ease,filter .16s ease}.rp-premium-shell .rp-cta:hover,.rp-premium-shell .rp-btn:hover{transform:translateY(-1px);filter:saturate(1.08);box-shadow:0 8px 20px color-mix(in srgb,var(--rp-primary-solid) 25%,transparent)}
.rp-premium-shell.fft-button-pill .rp-cta,.rp-premium-shell.fft-button-pill .rp-btn,.rp-premium-shell.fft-button-pill button{border-radius:999px!important}.rp-premium-shell.fft-button-square .rp-cta,.rp-premium-shell.fft-button-square .rp-btn,.rp-premium-shell.fft-button-square button{border-radius:min(8px,var(--ff-button-radius))!important}
.rp-premium-shell .rp-tabbar{border:1px solid transparent}.rp-premium-shell.fft-nav-floating-light .rp-tabbar{background:color-mix(in srgb,var(--ff-surface) 94%,transparent)!important;border-color:var(--ff-border)!important;box-shadow:var(--ff-shadow)!important}.rp-premium-shell.fft-nav-floating-light .rp-tabbar button{color:var(--ff-muted)!important}.rp-premium-shell.fft-nav-floating-light .rp-tabbar button.active{background:var(--ff-text)!important;color:var(--ff-surface)!important}.rp-premium-shell.fft-nav-underline .rp-tabs{position:static!important}.rp-premium-shell.fft-nav-underline .rp-tabbar{background:transparent!important;border:0!important;border-bottom:1px solid var(--ff-border)!important;border-radius:0!important;box-shadow:none!important;padding:0!important}.rp-premium-shell.fft-nav-underline .rp-tabbar button{color:var(--ff-muted)!important;border-radius:0!important;border-bottom:3px solid transparent!important}.rp-premium-shell.fft-nav-underline .rp-tabbar button.active{background:transparent!important;color:var(--ff-text)!important;border-bottom-color:var(--rp-primary-solid)!important}
.rp-premium-shell .rp-pill{background:color-mix(in srgb,var(--rp-primary-solid) 9%,var(--ff-surface))!important;border-color:color-mix(in srgb,var(--rp-primary-solid) 20%,var(--ff-border))!important;color:var(--ff-text)!important}.rp-premium-shell .rp-stats.is-hidden{display:none!important}
.rp-premium-shell .rp-front-routes,.rp-premium-shell .rp-client-portal,.rp-premium-shell #routespro-my-daily-route,.rp-premium-shell .routespro-dashboard,.rp-premium-shell .rp-team-inbox,.rp-premium-shell .ff-expenses,.rp-premium-shell .ff-briefings,.rp-premium-shell .ff-implementations,.rp-premium-shell .ff-stock-hub,.rp-premium-shell .ff-shopping-hub{--rp-primary:var(--rp-primary-solid)!important;--rp-accent:var(--rp-accent-solid)!important;--ffad-primary:var(--rp-primary-solid)!important;--ffad-accent:var(--rp-accent-solid)!important}
.rp-premium-shell .rp-panel[data-panel="analytics"]>.rp-premium-card>.rp-panel-head{display:none!important}.rp-premium-shell .rp-panel[data-panel="analytics"]>.rp-premium-card{padding-top:0!important}
.rp-premium-shell .routespro-dashboard{--ffad-primary:var(--rp-primary-solid)!important;--ffad-accent:var(--rp-accent-solid)!important;color:var(--ff-text)!important}.rp-premium-shell .routespro-dashboard .ffad-head{background:linear-gradient(135deg,var(--ff-header-start),var(--ff-header-end))!important;border-radius:var(--ff-radius)!important;box-shadow:var(--ff-shadow)!important}.rp-premium-shell .routespro-dashboard .ffad-filters,.rp-premium-shell .routespro-dashboard .ffad-reportbar,.rp-premium-shell .routespro-dashboard .ffad-insight{background:color-mix(in srgb,var(--ff-surface) 86%,var(--ff-canvas))!important;border-color:var(--ff-border)!important}.rp-premium-shell .routespro-dashboard .ffad-kpis{grid-template-columns:repeat(auto-fit,minmax(155px,1fr))!important}.rp-premium-shell .routespro-dashboard .ffad-main{grid-template-columns:minmax(0,1.7fr) minmax(240px,.55fr)!important}.rp-premium-shell .routespro-dashboard .ffad-chart{background:var(--ff-surface)!important}.rp-premium-shell .routespro-dashboard table{border-collapse:separate;border-spacing:0}.rp-premium-shell .routespro-dashboard th{background:color-mix(in srgb,var(--ff-canvas) 72%,var(--ff-surface))!important;color:var(--ff-muted)!important}
.rp-premium-shell.fft-table-compact table th,.rp-premium-shell.fft-table-compact table td{padding:8px 10px!important;font-size:12px}.rp-premium-shell.fft-table-zebra tbody tr:nth-child(even){background:color-mix(in srgb,var(--ff-canvas) 55%,var(--ff-surface))!important}.rp-premium-shell table tbody tr:hover{background:color-mix(in srgb,var(--rp-primary-solid) 7%,var(--ff-surface))!important}
@media(max-width:760px){.rp-premium-shell .rp-brandline{margin-bottom:12px}.rp-premium-shell .rp-brand-logo-campaign{max-width:145px}.rp-premium-shell table.rp-responsive-table{display:block!important;width:100%!important;min-width:0!important;border:0!important;background:transparent!important}.rp-premium-shell table.rp-responsive-table thead{display:none!important}.rp-premium-shell table.rp-responsive-table tbody{display:grid!important;gap:10px!important}.rp-premium-shell table.rp-responsive-table tbody tr{display:block!important;margin:0!important;padding:8px 12px!important;border:1px solid var(--ff-border)!important;border-radius:calc(var(--ff-radius) * .72)!important;background:var(--ff-surface)!important;box-shadow:0 7px 20px rgba(15,23,42,.045)!important}.rp-premium-shell table.rp-responsive-table tbody td{display:grid!important;grid-template-columns:minmax(136px,44%) minmax(0,1fr)!important;gap:10px!important;width:100%!important;padding:8px 0!important;border:0!important;border-top:1px solid color-mix(in srgb,var(--ff-border) 68%,transparent)!important;white-space:normal!important;overflow-wrap:anywhere}.rp-premium-shell table.rp-responsive-table tbody td:first-child{border-top:0!important}.rp-premium-shell table.rp-responsive-table tbody td:before{content:attr(data-label);font-size:10px;text-transform:uppercase;letter-spacing:.03em;font-weight:900;color:var(--ff-muted)}.rp-premium-shell .routespro-dashboard .ffad-main{grid-template-columns:1fr!important}}

/* 2.2.519, Analytics follows the same true-full-width shell as every operative panel. */
@media(max-width:760px){
  .rp-premium-shell.rp-app .rp-panel[data-panel="analytics"]{position:relative!important;width:100%!important;max-width:none!important;margin:0!important}
  .rp-premium-shell.rp-app .rp-panel[data-panel="analytics"]>.rp-premium-card{width:100%!important;max-width:none!important;padding:0!important;margin:0!important;border:0!important;box-shadow:none!important;background:transparent!important}
  .rp-premium-shell.rp-app .rp-panel[data-panel="analytics"] .routespro-dashboard{width:100%!important;max-width:none!important;margin:0!important;padding:0!important}
}


/* 2.2.182, complete text token propagation */
.rp-premium-shell.rp-app h1,.rp-premium-shell.rp-app h2,.rp-premium-shell.rp-app h3,.rp-premium-shell.rp-app h4,.rp-premium-shell.rp-app h5,.rp-premium-shell.rp-app h6,.rp-premium-shell.rp-app strong,.rp-premium-shell.rp-app b{color:var(--ff-heading)!important}
.rp-premium-shell.rp-app p,.rp-premium-shell.rp-app small,.rp-premium-shell.rp-app .rp-meta,.rp-premium-shell.rp-app .ffad-metric-note,.rp-premium-shell.rp-app .ffad-analytics-hint{color:var(--ff-muted)!important}
.rp-premium-shell.rp-app td,.rp-premium-shell.rp-app li,.rp-premium-shell.rp-app dd,.rp-premium-shell.rp-app .rpa-agenda-item{color:var(--ff-text)!important}
.rp-premium-shell .rp-kicker,.rp-premium-shell .rp-eyebrow,.rp-premium-shell label,.rp-premium-shell .ffad-metric-eyebrow,.rp-premium-shell .ffad-custom-head-copy>span{color:var(--ff-label)!important}
.rp-premium-shell .rp-hero,.rp-premium-shell .rp-hero h1,.rp-premium-shell .rp-hero h2,.rp-premium-shell .rp-hero h3,.rp-premium-shell .rp-hero strong,.rp-premium-shell .rp-hero .rp-eyebrow{color:var(--ff-hero-text)!important}.rp-premium-shell .rp-hero p,.rp-premium-shell .rp-hero span,.rp-premium-shell .rp-hero small{color:var(--ff-hero-muted)!important}
.rp-premium-shell .rp-hero .rp-scope-title{color:var(--ff-scope-title)!important}
.rp-premium-shell .rp-hero .rp-scope-text{color:var(--ff-scope-text)!important}
.rp-premium-shell.rp-app :is(.ffb-briefing-head,.ff-stock-banner,.ffp-hero,.ffp-academy-head,.ffi-hero,.ffad-head) :is(h1,h2,h3,h4,h5,h6,strong,.ffb-read-state>span,.ff-stock-live,.ff-stock-eyebrow){color:var(--ff-header-title-text,var(--ff-hero-text,#fff))!important}
.rp-premium-shell.rp-app :is(.ffb-briefing-head,.ff-stock-banner,.ffp-hero,.ffp-academy-head,.ffi-hero,.ffad-head) :is(p,small){color:var(--ff-header-body-text,var(--ff-hero-muted,#dbeafe))!important}
.rp-premium-shell a:not(.rp-cta):not(.rp-btn):not(.ffad-btn):not(.ffad-evidence-action):not(.ffad-evidence-thumb){color:var(--ff-link)}.rp-premium-shell a:not(.rp-cta):not(.rp-btn):not(.ffad-btn):not(.ffad-evidence-action):not(.ffad-evidence-thumb):hover{color:var(--ff-link-hover)}
.rp-premium-shell input,.rp-premium-shell select,.rp-premium-shell textarea{color:var(--ff-input-text)!important}.rp-premium-shell input::placeholder,.rp-premium-shell textarea::placeholder{color:var(--ff-placeholder)!important;opacity:1}
.rp-premium-shell ::selection{color:var(--ff-selection-text)!important;background:var(--ff-selection-bg)!important}.rp-premium-shell ::-moz-selection{color:var(--ff-selection-text)!important;background:var(--ff-selection-bg)!important}
.rp-premium-shell.rp-app .rp-tabbar button{color:var(--ff-nav-text)!important}.rp-premium-shell.rp-app .rp-tabbar button:hover,.rp-premium-shell.rp-app .rp-tabbar button:focus-visible{color:var(--ff-nav-hover-text)!important}.rp-premium-shell.rp-app .rp-tabbar button.active{color:var(--ff-nav-active-text)!important}
.rp-premium-shell .rp-cta:not(.ghost),.rp-premium-shell .rp-btn.primary,.rp-premium-shell .ffad-btn.primary,.rp-premium-shell .ffad-btn.alt,.rp-premium-shell .ffad-evidence-action{color:var(--ff-button-text)!important}.rp-premium-shell .rp-cta:not(.ghost):hover,.rp-premium-shell .rp-cta:not(.ghost):focus,.rp-premium-shell .rp-btn.primary:hover,.rp-premium-shell .rp-btn.primary:focus,.rp-premium-shell .ffad-btn.primary:hover,.rp-premium-shell .ffad-btn.alt:hover,.rp-premium-shell .ffad-evidence-action:hover{color:var(--ff-button-hover-text)!important}
.rp-premium-shell .rp-cta.ghost,.rp-premium-shell .rp-btn:not(.primary),.rp-premium-shell .ffad-btn:not(.primary):not(.alt){color:var(--ff-button-secondary-text)!important}.rp-premium-shell .rp-cta.ghost:hover,.rp-premium-shell .rp-cta.ghost:focus,.rp-premium-shell .rp-btn:not(.primary):hover,.rp-premium-shell .rp-btn:not(.primary):focus,.rp-premium-shell .ffad-btn:not(.primary):not(.alt):hover{color:var(--ff-button-secondary-hover-text)!important}
.rp-premium-shell th,.rp-premium-shell .routespro-dashboard th{color:var(--ff-table-header-text)!important}.rp-premium-shell .rp-pill,.rp-premium-shell .ffad-ai-badge,.rp-premium-shell .ffad-status{color:var(--ff-badge-text)!important}
.rp-premium-shell .ffad-status.completed,.rp-premium-shell .ffad-status.done{color:var(--ff-success-text)!important}.rp-premium-shell .ffad-status.pending,.rp-premium-shell .ffad-status.warning{color:var(--ff-warning-text)!important}.rp-premium-shell .ffad-status.cancelled,.rp-premium-shell .ffad-status.error{color:var(--ff-danger-text)!important}

</style>
    <div class="rp-premium-shell rp-app ffv-visual-9d5 ff-cross-portal-isolated ff-portal-layout-aligned ff-app-resume-guard ff-dual-portal-parity-v2 ff-app-resume-guard-v2<?php echo $ui_composition_enabled ? ' ff-ui-composition-v1' : ''; ?><?php echo $paint_stability_enabled ? ' ff-scroll-extent-safe ff-responsive-runtime ff-ope ff-paint-stable-v1' : ''; ?> <?php echo esc_attr($theme_classes); ?>" id="routespro-app" data-fieldflow-portal="operative" data-fieldflow-plugin-version="<?php echo esc_attr(FIELDFLOW_VERSION); ?>" data-fieldflow-dock-delivery-9de="<?php echo !$dock9de_enabled ? 'disabled' : (is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-bootstrap-9de.js') ? 'inline' : 'missing'); ?>" data-fieldflow-dock-controller="<?php echo $shared_dock_enabled ? 'client-9d5-9d9' : 'legacy-9d8'; ?>" data-fieldflow-locale-host="1" data-fieldflow-locale-shell="operative" data-fieldflow-ui-composition="<?php echo esc_attr($ui_composition_enabled ? 'fieldflow-ui-composition-ownership-v1' : 'disabled'); ?>" data-fieldflow-paint-stability="<?php echo esc_attr($paint_stability_enabled ? 'fieldflow-shared-portal-paint-stability-v1' : 'disabled'); ?>" data-fieldflow-cross-portal-isolation="fieldflow-cross-portal-render-isolation-v1" data-fieldflow-layout-contract="fieldflow-dual-portal-true-fullwidth-v1" data-fieldflow-resume-contract="fieldflow-app-resume-paint-guard-v1" data-fieldflow-fullwidth-parity="fieldflow-dual-portal-fullwidth-parity-v2" data-fieldflow-resume-hardening="fieldflow-app-resume-paint-guard-v2" data-fieldflow-resume-diagnostics="fieldflow-resume-diagnostics-v1" style="<?php echo esc_attr($theme_style); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
         data-user-id="<?php echo esc_attr((string)$user->ID); ?>"
         data-fieldflow-navigation-resume="fieldflow-field-resume-navigation-continuity-v1"
         data-today="<?php echo esc_attr($today); ?>"
         data-client-id="<?php echo esc_attr($initial_client_id); ?>"
         data-project-id="<?php echo esc_attr($initial_project_id); ?>"
         data-message-id="<?php echo esc_attr($message_id); ?>">
      <div class="rp-shell">
        <section class="rp-hero">
          <div class="rp-hero-grid">
            <div>
              <div class="rp-brandline">
                <img src="<?php echo esc_url(ROUTESPRO_URL . 'assets/logo-twt.png'); ?>" alt="FieldFlow" class="rp-brand-logo rp-brand-logo-core">
                <?php if ($campaign_logo): ?><img src="<?php echo esc_url($campaign_logo); ?>" alt="Logo da campanha" class="rp-brand-logo rp-brand-logo-campaign"><?php endif; ?>
              </div>
              <div class="rp-eyebrow"><?php echo esc_html((string)$theme['header_eyebrow']); ?></div>
              <h2><?php echo esc_html((string)$theme['app_title']); ?></h2>
              <p><?php echo esc_html((string)$theme['app_subtitle']); ?></p>
              <div class="rp-mini">
                <span>Rotas e execução</span>
                <span>Reportes e resultados</span>
              </div>
            </div>
            <div class="rp-card" style="background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.18);color:#fff">
              <div class="rp-kicker" style="color:rgba(255,255,255,.75)">Utilizador</div>
              <div class="rp-scope-title" style="font-size:22px;font-weight:800;margin-bottom:6px"><?php echo esc_html($user->display_name ?: $user->user_login); ?></div>
              <div class="rp-scope-text" style="opacity:.85;margin-bottom:16px"><?php echo esc_html($today_label); ?></div>
              <div class="rp-mini" style="margin-top:0">
                <a class="rp-cta" href="#rp-app-route">Começar rota</a>
                <button type="button" class="rp-cta alt" data-target="route" data-route-view="month">Ver o mês</button>
              </div>
            </div>
          </div>
          <div class="rp-stats<?php echo empty($theme['show_hero_metrics']) ? ' is-hidden' : ''; ?>" id="rpa-kpis">
            <div class="rp-stat"><span>Rotas de hoje</span><strong id="rpa-routes"><?php echo esc_html($today); ?></strong><small id="rpa-routes-note">A carregar operação do dia.</small></div>
            <div class="rp-stat"><span>Paragens</span><strong id="rpa-stops">...</strong><small id="rpa-stops-note">Resumo da execução diária.</small></div>
            <div class="rp-stat"><span>Rotas no mês</span><strong id="rpa-month">...</strong><small id="rpa-month-note">Agenda mensal do teu âmbito pessoal.</small></div>
            <div class="rp-stat"><span>Modo</span><strong id="rpa-mode"><?php echo $can_manage ? 'Gestão e execução' : ($can_scope ? 'Âmbito restrito' : 'Execução'); ?></strong><small id="rpa-mode-note">Tabs prontas para frontoffice moderno.</small></div>
          </div>
        </section>

        <?php if ($paint_stability_enabled): ?>
        <section class="ff-ope-command-deck" aria-label="Centro operacional" data-ff-server-command-deck="1">
          <div class="ff-ope-command-copy"><span>FieldFlow</span><strong>Centro operacional</strong><small>Acesso rápido às tarefas essenciais do terreno.</small></div>
          <div class="ff-ope-command-list">
            <button type="button" class="ff-ope-command is-active" data-ff-panel="route"><span class="ff-ope-command-icon" aria-hidden="true">R</span><span class="ff-ope-command-label"><strong>Rota</strong><small>Agenda e execução</small></span></button>
            <?php if ($allow_reporting): ?><button type="button" class="ff-ope-command" data-ff-panel="report"><span class="ff-ope-command-icon" aria-hidden="true">F</span><span class="ff-ope-command-label"><strong>Reportar</strong><small>Formulário e evidências</small></span></button><?php endif; ?>
            <?php if ($allow_stocks): ?><button type="button" class="ff-ope-command" data-ff-panel="stocks"><span class="ff-ope-command-icon" aria-hidden="true">S</span><span class="ff-ope-command-label"><strong>Stocks</strong><small>Disponibilidade atual</small></span></button><?php endif; ?>
            <?php if ($allow_briefings): ?><button type="button" class="ff-ope-command" data-ff-panel="briefings"><span class="ff-ope-command-icon" aria-hidden="true">B</span><span class="ff-ope-command-label"><strong>Briefing</strong><small>Orientações da campanha</small></span></button><?php endif; ?>
            <?php if ($allow_expenses): ?><button type="button" class="ff-ope-command" data-ff-panel="expenses"><span class="ff-ope-command-icon" aria-hidden="true">€</span><span class="ff-ope-command-label"><strong>Despesas</strong><small>Registo e histórico</small></span></button><?php endif; ?>
            <?php if (($can_manage || $can_scope) && $allow_analytics): ?><button type="button" class="ff-ope-command" data-ff-panel="analytics"><span class="ff-ope-command-icon" aria-hidden="true">A</span><span class="ff-ope-command-label"><strong>Analytics</strong><small>Desempenho pessoal</small></span></button><?php endif; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php if ($react_shell_enabled): ?>
        <div class="ff-react-front-operational-shell-root" data-fieldflow-react-front-island="operational-shell" data-fieldflow-react-front-props="<?php echo esc_attr((string)wp_json_encode($react_shell_props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>"></div>
        <?php endif; ?>
        <div id="<?php echo esc_attr($react_shell_fallback_id); ?>" class="rp-mini ff-react-front-operational-fallback" data-fieldflow-react-front-fallback="operational-shell"<?php echo $react_shell_enabled ? ' data-fieldflow-react-front-fallback-pending="1"' : ''; ?>>
          <span>Operação pronta</span><span>Navegação clássica disponível</span>
        </div>

        <div class="rp-panels">
          <div class="rp-panel active" data-panel="route" id="rp-app-route">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Minha Rota</h3><p>Alterna entre a execução do dia e o calendário mensal das tuas rotas atribuídas.</p></div><span class="rp-pill">Agenda pessoal</span></div>
              <div class="rp-route-switch" role="tablist" aria-label="Vista da rota">
                <button type="button" class="active" data-route-view="day">Dia</button>
                <button type="button" data-route-view="month">Mês</button>
              </div>
              <div class="rp-route-view-panel active" data-route-view-panel="day"><?php echo do_shortcode('[fieldflow_route_today mode="app"]'); ?></div>
              <div class="rp-route-view-panel" data-route-view-panel="month">
                <div class="rpa-month-shell">
                  <div class="rpa-month-head"><div><h4>Calendário de rotas</h4><p style="margin:4px 0 0;color:#64748b">Seleciona um dia para abrir diretamente a rota diária.</p></div><div class="rpa-month-controls"><button type="button" data-month-prev aria-label="Mês anterior">‹</button><span class="rpa-month-label" data-month-label></span><button type="button" data-month-next aria-label="Mês seguinte">›</button></div></div>
                  <div class="rpa-month-summary" data-month-summary></div>
                  <div class="rpa-weekdays"><span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span><span>Dom</span></div>
                  <div class="rpa-month-grid" data-month-grid><div class="rpa-empty">A carregar calendário...</div></div>
                  <div class="rpa-month-agenda" data-month-agenda></div>
                </div>
              </div>
            </div>
          </div>
          <?php if ($allow_briefings): ?>
          <div class="rp-panel" data-panel="briefings" id="rp-app-briefings">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Briefings Operacionais</h3><p>Informação semanal filtrada pelas campanhas, insígnias e lojas atribuídas ao utilizador.</p></div><span class="rp-pill">Briefing Hub</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'briefings', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_implementations): ?>
          <div class="rp-panel" data-panel="implementations" id="rp-app-implementations">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Implementações de Materiais</h3><p>Acompanha materiais, previsões, bloqueios e estados das lojas atribuídas à tua operação.</p></div><span class="rp-pill">Material Hub</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'implementations', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_stocks): ?>
          <div class="rp-panel" data-panel="stocks" id="rp-app-stocks">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Stocks</h3><p>Consulta o stock mais recente das lojas da tua rota e identifica ruturas antes de entrares no ponto de venda.</p></div><span class="rp-pill">Disponibilidade</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'stocks', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_shopping): ?>
          <div class="rp-panel" data-panel="shopping" id="rp-app-shopping">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Shopping, Verificação de Preços</h3><p>Confirma os preços regulares e promocionais das lojas que te estão atribuídas e envia o reporte ao cliente.</p></div><span class="rp-pill">PVP por loja</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'shopping', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_discovery): ?>
          <div class="rp-panel" data-panel="discovery" id="rp-app-discovery">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3><?php echo $can_manage ? 'Descobrir' : 'Planear Rotas'; ?></h3><p><?php echo $can_manage ? 'Construção de rota, descoberta de novos PDVs e análise de cobertura, dentro da mesma shell visual.' : 'Cria a tua rota do dia, da semana ou do mês, usando apenas as lojas que te estão atribuídas.'; ?></p></div><span class="rp-pill"><?php echo $can_manage ? 'Prospecção' : 'Planeamento pessoal'; ?></span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'discovery', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_reporting): ?>
          <div class="rp-panel" data-panel="report" id="rp-app-report">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Reportar</h3><p>Seleciona a loja e preenche o formulário. O contexto e o check-in são tratados automaticamente.</p></div><span class="rp-pill">Dia e loja</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'report', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_academy): ?>
          <div class="rp-panel" data-panel="academy" id="rp-app-academy">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Academy e Missões</h3><p>Formação prática, cursos curtos e missões operacionais para a equipa de terreno.</p></div><span class="rp-pill">Academy</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'academy', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_expenses): ?>
          <div class="rp-panel" data-panel="expenses" id="rp-app-expenses">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Despesas de Operação</h3><p>Regista despesas com fotografia, leitura do QR fiscal e histórico pessoal protegido.</p></div><span class="rp-pill">Gestão interna</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'expenses', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($allow_messages): ?>
          <div class="rp-panel" data-panel="messages" id="rp-app-messages">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Mensagens</h3><p>Inbox operacional para owners, com resposta, estados e ligação direta ao histórico no sistema.</p></div><span class="rp-pill">Workflow</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'messages', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if (($can_manage || $can_scope) && $allow_analytics): ?>
          <div class="rp-panel" data-panel="analytics" id="rp-app-analytics">
            <div class="rp-premium-card">
              <div class="rp-panel-head"><div><h3>Analytics</h3><p>Desempenho filtrado automaticamente pelas tuas rotas e reportes.</p></div><span class="rp-pill">Âmbito pessoal</span></div>
              <?php echo \RoutesPro\Front\PanelLoader::placeholder('app', 'analytics', ['client_id' => $initial_client_id, 'project_id' => $initial_project_id, 'message_id' => $message_id]); ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
    
        <div class="rp-tabs">
          <div class="rp-tabbar">
            <button type="button" class="active" data-panel="route">Minha Rota</button>
            <?php if ($allow_briefings): ?><button type="button" data-panel="briefings">Briefing</button><?php endif; ?>
            <?php if ($allow_implementations): ?><button type="button" data-panel="implementations">Implementações</button><?php endif; ?>
            <?php if ($allow_stocks): ?><button type="button" data-panel="stocks">Stocks</button><?php endif; ?>
            <?php if ($allow_shopping): ?><button type="button" data-panel="shopping">Shopping</button><?php endif; ?>
            <?php if ($allow_discovery): ?><button type="button" data-panel="discovery"><?php echo $can_manage ? 'Descobrir' : 'Planear'; ?></button><?php endif; ?>
            <?php if ($allow_reporting): ?><button type="button" data-panel="report">Reportar</button><?php endif; ?>
            <?php if ($allow_academy): ?><button type="button" data-panel="academy">Academy</button><?php endif; ?>
            <?php if ($allow_expenses): ?><button type="button" data-panel="expenses">Despesas</button><?php endif; ?>
            <?php if ($allow_messages): ?><button type="button" data-panel="messages">Mensagens</button><?php endif; ?>
            <?php if (($can_manage || $can_scope) && $allow_analytics): ?><button type="button" data-panel="analytics">Analytics</button><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php if ($dock_diag_requested): ?>
    <!-- 9DD: visible PHP marker means the operative shortcode response is fresh. -->
    <aside id="ff-dock-audit-9dd" data-fieldflow-contract="fieldflow-operative-dock-real-runtime-audit-9dd-v1" aria-label="Diagnostico tecnico da barra inferior">
      <details>
        <summary>Dock 9DD, PHP <?php echo esc_html(FIELDFLOW_VERSION); ?></summary>
        <div data-ffdd-status>PHP ativo. A aguardar controlador JavaScript.</div>
        <div class="ffdd-actions"><button type="button" data-ffdd-action="toggle" data-ffdd-toggle>Testar recolha</button><button type="button" data-ffdd-action="copy">Copiar diagnostico</button></div>
        <pre data-ffdd-output>JavaScript de diagnostico ainda nao executado.</pre>
      </details>
    </aside>
    <?php endif; ?>
    <?php if ($shared_dock_enabled && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-visual-consolidation-9d5.js')): ?>
    <!-- 9D9: Operative executes the exact Client navigation logic, byte-for-byte. -->
    <script data-fieldflow-contract="fieldflow-operative-client-dock-parity-9d9-v1">
    <?php echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-visual-consolidation-9d5.js'); ?>
    </script>
    <?php endif; ?>
    <?php if ($dock9dc_enabled && !$dock9de_enabled && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-scroll-9dc.js')): ?>
    <!-- 9DC: operative-only, nested scroll and gesture authority; Client 9D5 unchanged. -->
    <script data-fieldflow-contract="fieldflow-operative-dock-scroll-authority-9dc-v1">
    <?php echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-scroll-9dc.js'); ?>
    </script>
    <?php endif; ?>
    <?php if ($dock9de_enabled && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-bootstrap-9de.js')): ?>
    <!-- 9DE: a single independent owner of the real Operative tabbar. -->
    <script data-fieldflow-contract="fieldflow-operative-dock-bootstrap-recovery-9de-v1">
    <?php echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-bootstrap-9de.js'); ?>
    </script>
    <?php endif; ?>
    <?php if ($dock_diag_requested && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-audit-9dd.js')): ?>
    <script data-fieldflow-contract="fieldflow-operative-dock-real-runtime-audit-9dd-v1">
    <?php echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-audit-9dd.js'); ?>
    </script>
    <?php endif; ?>
    <?php if ($dock9d8_enabled && is_readable(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-authority-9d8.js')): ?>
    <script data-fieldflow-contract="fieldflow-operative-inline-dock-authority-9d8-v1">
    <?php echo (string)file_get_contents(ROUTESPRO_PATH . 'assets/fieldflow-operative-dock-authority-9d8.js'); ?>
    </script>
    <?php endif; ?>
    <script>
    (async function(){
      const root=document.getElementById('routespro-app');if(!root)return;
      const nonce=root.dataset.nonce||'';
      const api='<?php echo esc_url(rest_url('routespro/v1/')); ?>';
      const today=root.dataset.today||'<?php echo esc_js($today); ?>';
      const initialClientId=root.dataset.clientId||'';
      const initialProjectId=root.dataset.projectId||'';
      const initialMessageId=root.dataset.messageId||'';
      const $=sel=>root.querySelector(sel);
      const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
      const RESUME_NAV_CONTRACT='fieldflow-field-resume-navigation-continuity-v1';
      const REPORT_SELECTION_MEDIA_CONTRACT='fieldflow-report-selection-media-continuity-v1';
      const resumeUserId=String(root.dataset.userId||'0');
      const resumeScope=[resumeUserId,String(initialClientId||'0'),String(initialProjectId||'0')].join(':');
      const resumeStorageKey='fieldflow:resume:operational:v1:'+resumeScope;
      const lastReportVisitStorageKey='fieldflow:last-report-visit:v2:'+resumeScope;
      const resumeTtlMs=12*60*60*1000;
      const normaliseResumeVisit=(detail={})=>({
        date:String(detail.date||today||''),
        route_id:parseInt(detail.route_id||0,10)||0,
        stop_id:parseInt(detail.stop_id||detail.route_stop_id||0,10)||0,
        location_id:parseInt(detail.location_id||0,10)||0,
        client_id:parseInt(detail.client_id||0,10)||0,
        project_id:parseInt(detail.project_id||0,10)||0
      });
      const readResumeCheckpoint=()=>{
        try{
          const raw=localStorage.getItem(resumeStorageKey);
          const data=raw?JSON.parse(raw):null;
          if(!data||data.contract!==RESUME_NAV_CONTRACT||String(data.user_id||'')!==resumeUserId)return null;
          const updated=parseInt(data.updated_at||0,10)||0;
          if(!updated||Date.now()-updated>resumeTtlMs){localStorage.removeItem(resumeStorageKey);return null;}
          return data;
        }catch(_){return null;}
      };
      const writeResumeCheckpoint=(patch={})=>{
        try{
          const current=readResumeCheckpoint()||{};
          const next=Object.assign({},current,patch,{contract:RESUME_NAV_CONTRACT,user_id:resumeUserId,scope:resumeScope,updated_at:Date.now()});
          if(next.panel&&!validPanel(next.panel))next.panel='route';
          if(next.reportVisit)next.reportVisit=normaliseResumeVisit(next.reportVisit);
          localStorage.setItem(resumeStorageKey,JSON.stringify(next));
          return next;
        }catch(_){return null;}
      };
      const ensureTableScroll=(scope)=>{
        (scope||root).querySelectorAll('table').forEach(table=>{
          const headers=Array.from(table.querySelectorAll('thead th')).map(th=>(th.textContent||'').trim());
          if(headers.length && headers.length<=9){
            table.classList.add('rp-responsive-table');
            table.querySelectorAll('tbody tr').forEach(row=>Array.from(row.children).forEach((cell,index)=>{if(cell.tagName==='TD'&&!cell.hasAttribute('colspan'))cell.dataset.label=headers[index]||`Campo ${index+1}`;}));
          }
          if(table.closest('.rp-table-wrap,.rp-table-scroll')) return;
          const wrap=document.createElement('div');
          wrap.className='rp-table-scroll';
          wrap.style.display='block';
          wrap.style.width='100%';
          wrap.style.maxWidth='100%';
          wrap.style.overflowX='auto';
          wrap.style.overflowY='hidden';
          wrap.style.webkitOverflowScrolling='touch';
          table.parentNode.insertBefore(wrap, table);
          wrap.appendChild(table);
          if(!table.style.minWidth) table.style.minWidth='980px';
        });
      };
      let tableEnhanceTimer=null;
      const tableObserver=new MutationObserver(()=>{window.clearTimeout(tableEnhanceTimer);tableEnhanceTimer=window.setTimeout(()=>ensureTableScroll(root),60);});
      tableObserver.observe(root,{childList:true,subtree:true});
      const panelScopeDetail=()=>({portal:'operative',client_id:initialClientId||'',project_id:initialProjectId||'',message_id:initialMessageId||''});
const pingPanel=(target,key)=>{
  if(!target) return;

  const detail = Object.assign({panel:key}, panelScopeDetail());

  const ev = ()=>new CustomEvent('routespro:panel-open',{detail});

  // dispara no próprio painel
  target.dispatchEvent(ev());

  // dispara em filhos relevantes
  target.querySelectorAll('.rp-front-routes,.rp-client-portal,#routespro-my-daily-route,.routespro-dashboard,.rp-team-inbox,.ff-expenses,.ff-briefings,.ff-implementations,.ff-stock-hub,.ff-shopping-hub,.ffp-academy').forEach(el=>{
    el.dispatchEvent(ev());
    el.dispatchEvent(new CustomEvent('routespro:scope-change',{detail}));
  });

  // fallback global
  document.dispatchEvent(new CustomEvent('routespro:panel-open',{detail}));
  document.dispatchEvent(new CustomEvent('routespro:scope-change',{detail}));

  ensureTableScroll(target);
};
      const validPanel=(key)=>!!root.querySelector('.rp-panel[data-panel="'+key+'"]');
      const rememberPanel=(key)=>{
        try{sessionStorage.setItem('fieldflow_active_panel',key);}catch(e){}
        writeResumeCheckpoint({panel:key,reason:'panel-change'});
      };
      let panelSwitchToken=0;
      const openPanel=async(key,opts={})=>{
        if(!validPanel(key)) key='route';
        const target=root.querySelector('.rp-panel[data-panel="'+key+'"]');
        if(!target) return;
        const token=++panelSwitchToken;
        const paintStable=root.classList.contains('ff-paint-stable-v1');
        const needsLazy=paintStable&&opts.prepare!==false&&Array.from(target.querySelectorAll('[data-ff-lazy-panel="1"]')).some(node=>node.dataset.ffLoaded!=='1');
        if(needsLazy&&window.FieldFlowUI&&typeof window.FieldFlowUI.loadPanelByKey==='function'){
          root.dataset.ffPendingPanel=key;
          target.dataset.ffPanelPending='1';
          await Promise.resolve(window.FieldFlowUI.loadPanelByKey(root,key));
          target.removeAttribute('data-ff-panel-pending');
          if(token!==panelSwitchToken) return;
          delete root.dataset.ffPendingPanel;
        }
        root.querySelectorAll('.rp-tabbar button').forEach(btn=>btn.classList.toggle('active',btn.dataset.panel===key));
        root.querySelectorAll('.rp-panel').forEach(panel=>panel.classList.toggle('active',panel.dataset.panel===key));
        if(opts.remember!==false) rememberPanel(key);
        pingPanel(target,key);
        if(opts.scroll!==false) setTimeout(()=>target.scrollIntoView({behavior:'smooth',block:'start'}),10);
      };
      const setMetric=(base,strongText,smallText)=>{
        const strong=$('#'+base);
        const note=$('#'+base+'-note');
        if(strong&&strongText!=null) strong.textContent=String(strongText);
        if(note&&smallText!=null) note.textContent=String(smallText);
      };
      const j=async(url,opts={})=>{
        const requestOpts=Object.assign({credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce}},opts);
        if(window.FieldFlowOffline&&typeof window.FieldFlowOffline.fetchJson==='function'){
          return window.FieldFlowOffline.fetchJson(url,requestOpts);
        }
        const res=await fetch(url,requestOpts);
        const text=await res.text();
        let data={};
        try{data=text?JSON.parse(text):{}}catch(_){data={message:text}}
        if(!res.ok) throw new Error(data.message||'Falha no pedido.');
        return data;
      };
      let routeView='day';
      let monthCursor=new Date((today||new Date().toLocaleDateString('sv-SE'))+'T12:00:00');
      let monthRoutes=[];
      const monthEls={label:$('[data-month-label]'),grid:$('[data-month-grid]'),summary:$('[data-month-summary]'),agenda:$('[data-month-agenda]')};
      const isoLocal=d=>`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
      const monthName=d=>new Intl.DateTimeFormat('pt-PT',{month:'long',year:'numeric'}).format(d);
      const setRouteView=(view)=>{
        routeView=view==='month'?'month':'day';
        root.querySelectorAll('[data-route-view]').forEach(btn=>btn.classList.toggle('active',btn.dataset.routeView===routeView));
        root.querySelectorAll('[data-route-view-panel]').forEach(panel=>panel.classList.toggle('active',panel.dataset.routeViewPanel===routeView));
        writeResumeCheckpoint({routeView:routeView,reason:'route-view'});
        if(routeView==='month') loadMonthRoutes();
      };
      const openRouteDay=(date)=>{
        const input=root.querySelector('#rp-date');
        if(input){ input.value=date; input.dispatchEvent(new Event('change',{bubbles:true})); }
        setRouteView('day');
        const routePanel=root.querySelector('[data-route-view-panel="day"]');
        if(routePanel) setTimeout(()=>routePanel.scrollIntoView({behavior:'smooth',block:'start'}),50);
      };
      const renderMonth=()=>{
        if(!monthEls.grid)return;
        monthEls.label.textContent=monthName(monthCursor);
        const y=monthCursor.getFullYear(),m=monthCursor.getMonth(),days=new Date(y,m+1,0).getDate(),offset=(new Date(y,m,1).getDay()+6)%7;
        const prefix=`${y}-${String(m+1).padStart(2,'0')}`;
        const grouped={}; monthRoutes.forEach(r=>{const d=String(r.date||'');(grouped[d]||(grouped[d]=[])).push(r);});
        const cells=[]; for(let i=0;i<offset;i++)cells.push('<div class="rpa-day empty"></div>');
        for(let day=1;day<=days;day++){
          const date=`${prefix}-${String(day).padStart(2,'0')}`,items=grouped[date]||[];
          if(!items.length){cells.push(`<div class="rpa-day empty"><strong>${day}</strong></div>`);continue;}
          const stops=items.reduce((n,r)=>n+(parseInt(r.stops_count||0,10)||0),0),done=items.reduce((n,r)=>n+(parseInt(r.stops_done||0,10)||0),0),rate=stops?Math.round(done/stops*100):0;
          cells.push(`<button type="button" class="rpa-day ${date===today?'today':''}" data-route-date="${date}"><strong>${day}</strong><small>${items.length} rota${items.length===1?'':'s'} · ${stops} PDVs<br>${done}/${stops} concluídas</small><span class="rpa-progress"><i style="width:${rate}%"></i></span></button>`);
        }
        monthEls.grid.innerHTML=cells.join('');
        const totalStops=monthRoutes.reduce((n,r)=>n+(parseInt(r.stops_count||0,10)||0),0),doneStops=monthRoutes.reduce((n,r)=>n+(parseInt(r.stops_done||0,10)||0),0),km=monthRoutes.reduce((n,r)=>n+(parseFloat(r.distance_km||0)||0),0),rate=totalStops?Math.round(doneStops/totalStops*100):0;
        monthEls.summary.innerHTML=`<div><span>Rotas</span><strong>${monthRoutes.length}</strong></div><div><span>Paragens</span><strong>${totalStops}</strong></div><div><span>Execução</span><strong>${rate}%</strong></div><div><span>Km estimados</span><strong>${km.toFixed(1)}</strong></div>`;
        monthEls.agenda.innerHTML=monthRoutes.length?monthRoutes.slice().sort((a,b)=>String(a.date).localeCompare(String(b.date))).map(r=>`<button type="button" class="rpa-agenda-item" data-route-date="${String(r.date||'')}"><div><h5>${esc(r.project_name||'Rota')} · #${esc(r.id||'')}</h5><p>${esc(r.date||'')} · ${parseInt(r.stops_done||0,10)||0}/${parseInt(r.stops_count||0,10)||0} paragens concluídas</p></div><span class="rp-pill">${parseFloat(r.done_rate||0)||0}%</span></button>`).join(''):'<div class="rpa-empty">Sem rotas atribuídas neste mês.</div>';
        root.querySelectorAll('[data-route-date]').forEach(btn=>btn.addEventListener('click',()=>openRouteDay(btn.dataset.routeDate)));
      };
      const loadMonthRoutes=async()=>{
        if(!monthEls.grid)return;
        const y=monthCursor.getFullYear(),m=monthCursor.getMonth(),from=`${y}-${String(m+1).padStart(2,'0')}-01`,to=`${y}-${String(m+1).padStart(2,'0')}-${String(new Date(y,m+1,0).getDate()).padStart(2,'0')}`;
        monthEls.grid.innerHTML='<div class="rpa-empty">A carregar calendário...</div>';
        const data=await j(api+'routes?user_id=me&date_from='+encodeURIComponent(from)+'&date_to='+encodeURIComponent(to)+'&limit=500').catch(()=>({routes:[]}));
        monthRoutes=Array.isArray(data.routes)?data.routes:[]; renderMonth();
      };
      root.querySelector('[data-month-prev]')?.addEventListener('click',()=>{monthCursor=new Date(monthCursor.getFullYear(),monthCursor.getMonth()-1,1);loadMonthRoutes();});
      root.querySelector('[data-month-next]')?.addEventListener('click',()=>{monthCursor=new Date(monthCursor.getFullYear(),monthCursor.getMonth()+1,1);loadMonthRoutes();});
      root.querySelectorAll('[data-route-view]').forEach(btn=>btn.addEventListener('click',()=>setRouteView(btn.dataset.routeView)));
      const loadOverview=async()=>{
        try{
          const nowDate=new Date(today+'T12:00:00');
          const monthStart=`${nowDate.getFullYear()}-${String(nowDate.getMonth()+1).padStart(2,'0')}-01`;
          const monthEnd=`${nowDate.getFullYear()}-${String(nowDate.getMonth()+1).padStart(2,'0')}-${String(new Date(nowDate.getFullYear(),nowDate.getMonth()+1,0).getDate()).padStart(2,'0')}`;
          const [routesData,statsData,monthData]=await Promise.all([
            j(api+'routes?user_id=me&date='+encodeURIComponent(today)).catch(()=>({routes:[]})),
            j(api+'stats?user_id=me&from='+encodeURIComponent(today)+'&to='+encodeURIComponent(today)).catch(()=>({})),
            j(api+'routes?user_id=me&date_from='+encodeURIComponent(monthStart)+'&date_to='+encodeURIComponent(monthEnd)+'&limit=500').catch(()=>({routes:[]}))
          ]);
          const routes=Array.isArray(routesData.routes)?routesData.routes:[];
          let totalStops=0,doneStops=0;
          const details=await Promise.all(routes.slice(0,12).map(route=>j(api+'routes/'+route.id).catch(()=>null)));
          details.forEach(route=>{
            const stops=Array.isArray(route&&route.stops)?route.stops:[];
            totalStops+=stops.length;
            doneStops+=stops.filter(stop=>['done','completed'].includes(String(stop.status||'').toLowerCase())).length;
          });
          setMetric('rpa-routes',routes.length,routes.length?('Rotas atribuídas para '+today+'.'):('Sem rotas atribuídas para hoje.'));
          setMetric('rpa-stops',totalStops?`${doneStops}/${totalStops}`:'0/0',totalStops?('Paragens concluídas dentro da tua operação diária.'):('Sem paragens carregadas neste momento.'));
          const monthRoutes=Array.isArray(monthData.routes)?monthData.routes:[];
          const monthStops=monthRoutes.reduce((n,r)=>n+(parseInt(r.stops_count||0,10)||0),0);
          setMetric('rpa-month',monthRoutes.length,`${monthStops} paragens planeadas no mês atual.`);
          const completion=(statsData&&typeof statsData.done_rate!=='undefined')?Number(statsData.done_rate||0):null;
          setMetric('rpa-mode',$('#rpa-mode')?$('#rpa-mode').textContent:'<?php echo $can_manage ? 'Gestão e execução' : ($can_scope ? 'Âmbito restrito' : 'Execução'); ?>',completion===null?'Tabs prontas para frontoffice moderno.':('Execução do dia em '+completion+'% no âmbito atual.'));
        }catch(_){
          setMetric('rpa-routes','n.d.','Não foi possível carregar o resumo operacional.');
          setMetric('rpa-stops','n.d.','Tenta atualizar a página para recarregar os dados.');
          setMetric('rpa-month','n.d.','Agenda mensal indisponível de momento.');
        }
      };
      root.querySelectorAll('.rp-tabbar button,[data-target]').forEach(btn=>btn.addEventListener('click',()=>{ openPanel(btn.dataset.panel||btn.dataset.target||'route'); if(btn.dataset.routeView) setRouteView(btn.dataset.routeView); }));
      let pendingPlannerIntent=null;
      const normalisePlannerIntent=(detail={})=>({contract:'fieldflow-my-route-periodicity-action-ux-v1',location_id:parseInt(detail.location_id||0,10)||0,project_id:parseInt(detail.project_id||0,10)||0,client_id:parseInt(detail.client_id||0,10)||0,month:String(detail.month||'').slice(0,7),source:String(detail.source||'my-route-periodicity'),created_at:parseInt(detail.created_at||Date.now(),10)||Date.now()});
      const dispatchPlannerIntent=()=>{
        if(!pendingPlannerIntent)return false;
        const planner=root.querySelector('.ff-operational-surface-planner');
        if(!planner)return false;
        const detail=pendingPlannerIntent;
        planner.dispatchEvent(new CustomEvent('fieldflow:planner-focus-store',{detail,bubbles:false}));
        return true;
      };
      document.addEventListener('fieldflow:open-planner',async event=>{
        pendingPlannerIntent=normalisePlannerIntent((event&&event.detail)||{});
        try{sessionStorage.setItem('fieldflow:planner-intent:v1',JSON.stringify(pendingPlannerIntent));}catch(_){}
        await openPanel('discovery');
        window.setTimeout(()=>dispatchPlannerIntent(),40);
      });
      let pendingReportVisit=null;
      let reportVisitRetryTimer=null;
      let manualReportSelectionLockUntil=0;
      const normaliseReportVisit=normaliseResumeVisit;
      const liveReportVisit=()=>{
        const report=root.querySelector('#routespro-report-focus');
        if(!report)return null;
        const stopId=parseInt(report.querySelector('#rpf-stop')?.value||report.querySelector('#rpf-store')?.value||report.dataset.stopId||0,10)||0;
        if(!stopId)return null;
        return normaliseReportVisit({
          date:String(report.dataset.date||today||''),
          route_id:parseInt(report.querySelector('#rpf-route')?.value||report.dataset.routeId||0,10)||0,
          stop_id:stopId,
          location_id:parseInt(report.querySelector('#rpf-location')?.value||0,10)||0,
          client_id:parseInt(report.querySelector('#rpf-client')?.value||0,10)||0,
          project_id:parseInt(report.querySelector('#rpf-project')?.value||0,10)||0
        });
      };
      const cancelPendingReportRestore=(reason)=>{
        manualReportSelectionLockUntil=Date.now()+2500;
        pendingReportVisit=null;
        window.clearTimeout(reportVisitRetryTimer);
        reportVisitRetryTimer=null;
        writeResumeCheckpoint({panel:'report',reason:String(reason||'manual-report-selection')});
      };
      const dispatchPendingReportVisit=()=>{
        if(Date.now()<manualReportSelectionLockUntil)return false;
        if(!pendingReportVisit) return false;
        const report=root.querySelector('#routespro-report-focus');
        if(!report) return false;
        const detail=pendingReportVisit;
        pendingReportVisit=null;
        window.clearTimeout(reportVisitRetryTimer);
        report.dispatchEvent(new CustomEvent('fieldflow:select-visit',{detail}));
        return true;
      };
      const schedulePendingReportVisit=()=>{
        window.clearTimeout(reportVisitRetryTimer);
        let attempts=0;
        const retry=()=>{
          attempts++;
          if(dispatchPendingReportVisit()||attempts>=40) return;
          reportVisitRetryTimer=window.setTimeout(retry,75);
        };
        retry();
      };
      document.addEventListener('fieldflow:open-report',(event)=>{
        pendingReportVisit=normaliseReportVisit((event&&event.detail)||{});
        try{ localStorage.setItem(lastReportVisitStorageKey,JSON.stringify(Object.assign({},pendingReportVisit,{updated_at:Date.now()}))); }catch(e){}
        writeResumeCheckpoint({panel:'report',reportVisit:pendingReportVisit,reason:'open-report'});
        openPanel('report');
        schedulePendingReportVisit();
      });
      document.addEventListener('fieldflow:report-selection-intent',()=>{
        cancelPendingReportRestore('manual-report-selection-intent');
      });
      document.addEventListener('fieldflow:report-selection-changed',(event)=>{
        const visit=normaliseReportVisit((event&&event.detail)||{});
        cancelPendingReportRestore('manual-report-selection-changed');
        if(visit.route_id||visit.stop_id||visit.location_id){
          try{localStorage.setItem(lastReportVisitStorageKey,JSON.stringify(Object.assign({},visit,{updated_at:Date.now()})));}catch(_){ }
          writeResumeCheckpoint({panel:'report',reportVisit:visit,reason:'manual-report-selection-changed'});
        }else{
          pendingReportVisit=null;
          try{localStorage.removeItem(lastReportVisitStorageKey);localStorage.removeItem('fieldflow:last-report-visit');}catch(_){ }
          writeResumeCheckpoint({panel:'report',reportVisit:null,formStep:0,formId:0,reason:'manual-report-selection-cleared'});
        }
      });
      document.addEventListener('fieldflow:form-external-handoff',(event)=>{
        const detail=(event&&event.detail)||{};
        const visit=normaliseReportVisit(detail);
        if(visit.route_id||visit.stop_id||visit.location_id)pendingReportVisit=visit;
        writeResumeCheckpoint({panel:'report',reportVisit:pendingReportVisit||visit,reason:String(detail.source||'external-handoff'),formStep:parseInt(detail.step_index||0,10)||0,formId:parseInt(detail.form_id||0,10)||0});
      });
      document.addEventListener('fieldflow:form-media-checkpointed',(event)=>{
        const detail=(event&&event.detail)||{};
        const visit=normaliseReportVisit(detail);
        if(visit.route_id||visit.stop_id||visit.location_id){
          pendingReportVisit=null;
          writeResumeCheckpoint({panel:'report',reportVisit:visit,reason:'media-checkpointed',formStep:parseInt(detail.step_index||0,10)||0,formId:parseInt(detail.form_id||0,10)||0});
        }
      });
      document.addEventListener('fieldflow:lazy-panel-loaded',(event)=>{
        const detail=(event&&event.detail)||{};
        if(String(detail.panel||'')==='report') dispatchPendingReportVisit();
        if(String(detail.panel||'')==='discovery') window.setTimeout(()=>dispatchPlannerIntent(),20);
      });
      const qs=new URLSearchParams(window.location.search);
      const panelFromQuery=qs.get('ffp_panel')||qs.get('panel')||'';
      const panelFromHash=(window.location.hash==='#rp-app-academy')?'academy':((window.location.hash==='#rp-app-messages')?'messages':((window.location.hash||'').replace('#rp-app-','')));
      const resumeCheckpoint=readResumeCheckpoint();
      let panelFromStorage='';
      try{ panelFromStorage=sessionStorage.getItem('fieldflow_active_panel')||''; }catch(e){}
      const panelFromResume=resumeCheckpoint&&validPanel(String(resumeCheckpoint.panel||''))?String(resumeCheckpoint.panel||''):'';
      if(panelFromResume==='report'&&resumeCheckpoint&&resumeCheckpoint.reportVisit)pendingReportVisit=normaliseReportVisit(resumeCheckpoint.reportVisit);
      const initialPanel=(qs.get('routespro_form_ok')==='1'||qs.has('routespro_form_err'))?'report':(panelFromQuery||panelFromHash||panelFromResume||panelFromStorage||((initialMessageId)?'messages':'route'));
      let initialPanelApplied=false;
      const applyInitial=()=>{
        if(initialPanelApplied) return;
        initialPanelApplied=true;
        openPanel(initialPanel,{remember:false,scroll:false});
      };
      // Phase 9F: choose the canonical panel synchronously so the browser never paints
      // the default Route tab for one frame before restoring the real session panel.
      applyInitial();
      const reportNeedsSelection=(visit)=>{
        if(!visit)return false;
        const report=root.querySelector('#routespro-report-focus');
        if(!report)return true;
        const liveStop=parseInt(report.querySelector('#rpf-stop')?.value||report.dataset.stopId||0,10)||0;
        const liveRoute=parseInt(report.querySelector('#rpf-route')?.value||report.dataset.routeId||0,10)||0;
        const wantedStop=parseInt(visit.stop_id||visit.route_stop_id||0,10)||0;
        const wantedRoute=parseInt(visit.route_id||0,10)||0;
        if(!liveStop)return true;
        if(wantedStop&&liveStop!==wantedStop)return true;
        if(wantedRoute&&liveRoute&&liveRoute!==wantedRoute)return true;
        return false;
      };
      if(resumeCheckpoint&&String(resumeCheckpoint.routeView||'')==='month')setRouteView('month');
      if(pendingReportVisit&&initialPanel==='report'&&reportNeedsSelection(pendingReportVisit))schedulePendingReportVisit();
      const snapshotResumeContext=(reason)=>{
        const active=root.querySelector('.rp-panel.active');
        let reportVisit=liveReportVisit()||pendingReportVisit;
        if(!reportVisit){try{const raw=localStorage.getItem(lastReportVisitStorageKey);const parsed=raw?JSON.parse(raw):null;if(parsed&&Date.now()-(parseInt(parsed.updated_at||0,10)||0)<=resumeTtlMs&&parseInt(parsed.updated_at||0,10)>0)reportVisit=normaliseReportVisit(parsed);}catch(_){}}
        writeResumeCheckpoint({panel:String(active&&active.dataset.panel||initialPanel||'route'),routeView:routeView,reportVisit:reportVisit||null,reason:String(reason||'checkpoint')});
      };
      const restoreResumeContext=(reason)=>{
        const checkpoint=readResumeCheckpoint();
        if(!checkpoint)return false;
        const panel=validPanel(String(checkpoint.panel||''))?String(checkpoint.panel):'route';
        const active=root.querySelector('.rp-panel.active');
        const liveVisit=(active&&String(active.dataset.panel||'')==='report')?liveReportVisit():null;
        // A live reporting form is more recent than a stored checkpoint. Never
        // navigate upward or replace the operator's current store/step on focus.
        if(liveVisit){
          pendingReportVisit=null;
          try{localStorage.setItem(lastReportVisitStorageKey,JSON.stringify(Object.assign({},liveVisit,{updated_at:Date.now()})));}catch(_){ }
          writeResumeCheckpoint({panel:'report',reportVisit:liveVisit,reason:String(reason||'live-report-authority')});
          return true;
        }
        if(panel==='report'&&checkpoint.reportVisit&&Date.now()>=manualReportSelectionLockUntil){pendingReportVisit=normaliseReportVisit(checkpoint.reportVisit);}
        if(!active||String(active.dataset.panel||'')!==panel)openPanel(panel,{remember:false,scroll:false});
        if(panel==='report'&&pendingReportVisit&&Date.now()>=manualReportSelectionLockUntil&&reportNeedsSelection(pendingReportVisit))schedulePendingReportVisit();
        if(panel==='route'&&String(checkpoint.routeView||'')==='month')setRouteView('month');
        writeResumeCheckpoint({reason:String(reason||'resume-restored')});
        return true;
      };
      document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')snapshotResumeContext('visibility-hidden');});
      window.addEventListener('pagehide',()=>snapshotResumeContext('pagehide'));
      window.addEventListener('beforeunload',()=>snapshotResumeContext('beforeunload'));
      document.addEventListener('freeze',()=>snapshotResumeContext('freeze'));
      document.addEventListener('fieldflow:native-resume-sync',event=>restoreResumeContext(event&&event.detail&&event.detail.reason||'native-resume'));
      window.addEventListener('pageshow',()=>restoreResumeContext('pageshow'));
      let overviewRefreshTimer=null;
      document.addEventListener('fieldflow:visit-completed',()=>{
        window.clearTimeout(overviewRefreshTimer);
        overviewRefreshTimer=window.setTimeout(()=>{loadOverview();if(routeView==='month')loadMonthRoutes();},120);
      });
      await loadOverview();
      loadMonthRoutes();
    })();
    </script>
    <?php
        return ob_get_clean();
    }


}
