<?php
namespace RoutesPro\Desktop;

if (!defined('ABSPATH')) exit;

final class DesktopPortal {
    private const QUERY_FLAG = 'fieldflow_desktop';
    private const FRAME_FLAG = 'ff_desktop_frame';
    private const PING_FLAG = 'fieldflow_desktop_ping';
    private const ACTIVITY_FLAG = 'fieldflow_desktop_activity';
    private const BRIDGE_SCHEMA = 1;
    private const DESKTOP_RELEASE = '1.0 Preview';

    public static function registerHooks(): void {
        add_action('init', [self::class, 'registerRewrite'], 5);
        add_action('init', [self::class, 'maybeFlushRewrite'], 99);
        add_filter('query_vars', [self::class, 'queryVars']);
        add_action('template_redirect', [self::class, 'maybeRender'], 0);
        add_action('admin_head', [self::class, 'adminFrameStyles'], 999);
        add_action('admin_footer', [self::class, 'adminFrameScript'], 999);
        add_action('wp_head', [self::class, 'frontFrameStyles'], 999);
        add_action('wp_footer', [self::class, 'frontFrameScript'], 999);
        add_filter('show_admin_bar', [self::class, 'showAdminBar']);
    }

    public static function registerRewrite(): void {
        add_rewrite_rule('^fieldflow-desktop/?$', 'index.php?' . self::QUERY_FLAG . '=1', 'top');
    }

    public static function maybeFlushRewrite(): void {
        $flag = 'fieldflow_desktop_rewrite_' . str_replace('.', '_', (string)ROUTESPRO_VERSION);
        if (get_option($flag)) return;
        flush_rewrite_rules(false);
        update_option($flag, 1, false);
    }

    public static function queryVars(array $vars): array {
        $vars[] = self::QUERY_FLAG;
        $vars[] = self::PING_FLAG;
        $vars[] = self::ACTIVITY_FLAG;
        return $vars;
    }

    public static function showAdminBar(bool $show): bool {
        if (self::isFrameRequest()) return false;
        return $show;
    }

    public static function adminFrameStyles(): void {
        if (!self::isFrameRequest()) return;
        echo '<style id="fieldflow-desktop-admin-frame">'
            . 'html.wp-toolbar{padding-top:0!important}'
            . 'body.ff-desktop-admin-frame{background:#f5f7fb!important}'
            . '#wpadminbar,#adminmenumain,#adminmenuwrap,#adminmenuback{display:none!important}'
            . '#wpcontent,#wpfooter{margin-left:0!important}'
            . '#wpcontent{padding-left:0!important}'
            . '#wpbody{padding-top:0!important}'
            . '#wpbody-content{padding-bottom:28px!important;min-height:calc(100vh - 28px)!important}'
            . '.wrap{margin:18px 22px 0 22px!important;max-width:none!important}'
            . '#screen-meta-links{right:18px!important}'
            . '.update-nag,.notice{max-width:none}'
            . '@media(max-width:782px){#wpcontent{padding-left:0!important}.wrap{margin:14px!important}}'
            . '</style>';
    }

    public static function adminFrameScript(): void {
        if (!self::isFrameRequest()) return;
        ?>
<script id="fieldflow-desktop-admin-frame-script">
(function(){
  var FLAG='ff_desktop_frame';
  function sameOriginUrl(raw){try{var u=new URL(raw,location.href);return u.origin===location.origin?u:null;}catch(e){return null;}}
  function apply(){
    document.documentElement.classList.remove('wp-toolbar');
    if(document.body)document.body.classList.add('ff-desktop-admin-frame');
    ['wpadminbar','adminmenumain','adminmenuwrap','adminmenuback'].forEach(function(id){var el=document.getElementById(id);if(el)el.style.setProperty('display','none','important');});
    var content=document.getElementById('wpcontent');if(content){content.style.setProperty('margin-left','0','important');content.style.setProperty('padding-left','0','important');}
    var footer=document.getElementById('wpfooter');if(footer)footer.style.setProperty('margin-left','0','important');
  }
  function keepFlagOnLink(a){
    if(!a||!a.href||a.target==='_blank'||a.hasAttribute('download'))return;
    var u=sameOriginUrl(a.href);if(!u||u.pathname.indexOf('/wp-admin/')===-1)return;
    if(/admin-(post|ajax)\.php$/i.test(u.pathname))return;
    u.searchParams.set(FLAG,'1');a.href=u.toString();
  }
  function keepFlagOnForm(form){
    if(!form)return;
    var method=(form.getAttribute('method')||'get').toLowerCase();
    if(method!=='get')return;
    var action=sameOriginUrl(form.getAttribute('action')||location.href);if(!action||action.pathname.indexOf('/wp-admin/')===-1)return;
    if(!form.querySelector('input[name="'+FLAG+'"]')){var input=document.createElement('input');input.type='hidden';input.name=FLAG;input.value='1';form.appendChild(input);}
  }
  apply();
  document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href]');if(a)keepFlagOnLink(a);},true);
  document.addEventListener('submit',function(e){keepFlagOnForm(e.target);},true);
  if(window.MutationObserver){new MutationObserver(apply).observe(document.documentElement,{childList:true,subtree:true});}
})();
</script>
        <?php
    }

    public static function frontFrameStyles(): void {
        if (!self::isFrameRequest()) return;
        echo '<style id="fieldflow-desktop-front-frame">'
            . 'html{background:#f5f7fb!important}'
            . 'body{min-height:100vh!important;background:#f5f7fb!important}'
            . '.ff-pwa-prompt,.ff-pwa-help,[data-ff-pwa-prompt],[data-ff-pwa-help]{display:none!important}'
            . 'body>header,body>footer,#masthead,#colophon,.site-header,.site-footer,.elementor-location-header,.elementor-location-footer{display:none!important}'
            . '#page,.site,.site-content,#content,.content-area,.site-main{margin:0!important;padding:0!important;max-width:none!important;width:100%!important}'
            . '.rp-premium-shell,.rp-client-premium{border-radius:0!important;min-height:100vh!important}'
            . '.rp-premium-shell .rp-shell,.rp-client-premium .rp-shell{max-width:none!important;width:100%!important;padding-left:22px!important;padding-right:22px!important}'
            . '.rp-premium-shell .rp-tabs,.rp-client-premium .rp-tabs{position:sticky!important;top:0!important;bottom:auto!important;z-index:70!important;margin:0 0 18px!important;padding:10px 0!important;background:rgba(245,247,251,.94)!important;backdrop-filter:blur(14px)}'
            . '.rp-premium-shell .rp-tabbar,.rp-client-premium .rp-tabbar{border-radius:16px!important;box-shadow:0 10px 30px rgba(15,23,42,.10)!important}'
            . '.rp-premium-shell .rp-panels{padding-bottom:28px!important}'
            . '@media(max-width:980px){.rp-premium-shell .rp-shell,.rp-client-premium .rp-shell{padding-left:12px!important;padding-right:12px!important}}'
            . '</style>';
    }

    public static function frontFrameScript(): void {
        if (!self::isFrameRequest()) return;
        ?>
<script id="fieldflow-desktop-front-frame-script">
(function(){
  function enhance(){
    var roots=document.querySelectorAll('#routespro-app,#routespro-client-portal');
    roots.forEach(function(root){
      var tabs=root.querySelector('.rp-tabs');
      var panels=root.querySelector('.rp-panels');
      if(tabs&&panels&&tabs.nextElementSibling!==panels){
        panels.parentNode.insertBefore(tabs,panels);
      }
      root.setAttribute('data-fieldflow-desktop','1');
    });
    try{
      if(window.parent&&window.parent!==window){
        var h=document.querySelector('.rp-panel.active h3,.rp-hero h2,.rp-panel-head h3');
        if(h&&h.textContent){window.parent.postMessage({type:'fieldflow.desktop.title',title:h.textContent.trim()},location.origin);}
      }
    }catch(e){}
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',enhance);else enhance();
  document.addEventListener('click',function(e){
    var b=e.target.closest&&e.target.closest('.rp-tabbar button[data-panel]');
    if(!b)return;
    setTimeout(function(){
      try{if(window.parent&&window.parent!==window)window.parent.postMessage({type:'fieldflow.desktop.title',title:(b.textContent||'FieldFlow').trim()},location.origin);}catch(err){}
    },30);
  });
})();
</script>
        <?php
    }

    public static function maybeRender(): void {
        if (self::isPingRequest()) {
            self::renderPing();
        }
        if (self::isActivityRequest()) {
            self::renderActivity();
        }

        if (!self::isDesktopRequest()) return;

        if (!is_user_logged_in()) {
            $return = self::desktopUrl();
            wp_safe_redirect(self::loginUrl($return));
            exit;
        }

        $userId = get_current_user_id();
        $profile = self::desktopProfile($userId);
        if (($profile['key'] ?? 'restricted') === 'restricted') {
            self::renderDenied();
        }

        self::renderShell($userId, $profile);
    }

    private static function roleTokens(int $userId): array {
        $user = get_userdata($userId);
        if (!$user) return [];
        return array_values(array_filter(array_map(static function($role): string {
            return strtolower((string)$role);
        }, (array)$user->roles)));
    }

    private static function roleMatches(array $roles, string $pattern): bool {
        foreach ($roles as $role) {
            if (preg_match($pattern, (string)$role)) return true;
        }
        return false;
    }

    private static function portalAccess(int $userId): array {
        $user = get_userdata($userId);
        if (!$user) return ['operational' => false, 'client' => false];

        $roles = self::roleTokens($userId);
        $canManage = user_can($user, 'routespro_manage');
        $canExecute = user_can($user, 'routespro_execute');
        $scope = class_exists('\\RoutesPro\\Support\\Permissions')
            ? \RoutesPro\Support\Permissions::get_scope($userId)
            : ['client_ids' => [], 'project_ids' => [], 'route_ids' => []];

        $isClientRole = self::roleMatches($roles, '/(^|_)(client|cliente|customer|brand|account)(_|$)/');
        $isOperationalRole = self::roleMatches($roles, '/(^|_)(merchandiser|merchan|promotor|promoter|route_agent|field|operational|operacional)(_|$)/');
        $isSupervisorRole = self::roleMatches($roles, '/(^|_)(supervisor|coordinator|coordenador|gestor|team_lead|teamlead|field_lead|responsavel)(_|$)/');
        $hasRoutes = !empty($scope['route_ids']);
        $hasScope = !empty($scope['client_ids']) || !empty($scope['project_ids']);

        $operational = $canManage || $canExecute || $hasRoutes || $isOperationalRole || $isSupervisorRole;
        $client = $canManage || $isClientRole || (!$operational && $hasScope);

        if (class_exists('\\RoutesPro\\Mobile\\WebViewBridge')) {
            $bridge = \RoutesPro\Mobile\WebViewBridge::portal_access($userId);
            if (is_array($bridge)) {
                $operational = $operational || !empty($bridge['operational']);
                $client = $client || !empty($bridge['client']);
            }
        }

        return [
            'operational' => (bool)$operational,
            'client' => (bool)$client,
            'client_role' => $isClientRole,
            'operational_role' => $isOperationalRole,
            'supervisor_role' => $isSupervisorRole,
        ];
    }

    private static function desktopProfile(int $userId): array {
        $user = get_userdata($userId);
        if (!$user) return ['key' => 'restricted', 'label' => 'Sem acesso', 'workspace_label' => 'FieldFlow'];

        $canManage = user_can($user, 'routespro_manage');
        $access = self::portalAccess($userId);
        if ($canManage) {
            return [
                'key' => 'admin',
                'label' => 'Administrador',
                'workspace_label' => 'Admin Console',
                'can_manage' => true,
                'access' => $access,
            ];
        }

        if (!empty($access['client_role']) && !empty($access['client'])) {
            return [
                'key' => 'client',
                'label' => 'Cliente',
                'workspace_label' => 'Client Workspace',
                'can_manage' => false,
                'access' => $access,
            ];
        }

        if (!empty($access['supervisor_role']) && !empty($access['operational'])) {
            return [
                'key' => 'supervisor',
                'label' => 'Supervisor',
                'workspace_label' => 'Operations Workspace',
                'can_manage' => false,
                'access' => $access,
            ];
        }

        if (!empty($access['operational'])) {
            return [
                'key' => 'operational',
                'label' => 'Operacional',
                'workspace_label' => 'Field Workspace',
                'can_manage' => false,
                'access' => $access,
            ];
        }

        if (!empty($access['client'])) {
            return [
                'key' => 'client',
                'label' => 'Cliente',
                'workspace_label' => 'Client Workspace',
                'can_manage' => false,
                'access' => $access,
            ];
        }

        return ['key' => 'restricted', 'label' => 'Sem acesso', 'workspace_label' => 'FieldFlow', 'can_manage' => false, 'access' => $access];
    }

    private static function isDesktopRequest(): bool {
        if ((string)get_query_var(self::QUERY_FLAG) === '1') return true;
        if (isset($_GET[self::QUERY_FLAG]) && (string)$_GET[self::QUERY_FLAG] === '1') return true;
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $desktopPath = parse_url((string)home_url('/fieldflow-desktop/'), PHP_URL_PATH);
        if (!is_string($path) || !is_string($desktopPath)) return false;
        return rtrim($path, '/') === rtrim($desktopPath, '/');
    }

    private static function isFrameRequest(): bool {
        return isset($_GET[self::FRAME_FLAG]) && (string)$_GET[self::FRAME_FLAG] === '1';
    }

    private static function isPingRequest(): bool {
        return isset($_GET[self::PING_FLAG]) && (string)$_GET[self::PING_FLAG] === '1';
    }

    private static function isActivityRequest(): bool {
        return isset($_GET[self::ACTIVITY_FLAG]) && (string)$_GET[self::ACTIVITY_FLAG] === '1';
    }

    private static function pingUrl(): string {
        return add_query_arg(self::PING_FLAG, '1', home_url('/'));
    }

    private static function activityUrl(): string {
        return add_query_arg(self::ACTIVITY_FLAG, '1', home_url('/'));
    }

    private static function desktopUrl(): string {
        // Stable entry point that does not depend on permalink rewrite rules.
        return add_query_arg(self::QUERY_FLAG, '1', home_url('/'));
    }

    private static function loginUrl(string $redirect = ''): string {
        $redirect = $redirect !== '' ? $redirect : self::desktopUrl();

        // Prefer the site's configured Ultimate Member login page when available.
        if (function_exists('um_get_core_page')) {
            $url = um_get_core_page('login');
            if (is_string($url) && $url !== '') {
                return add_query_arg('redirect_to', $redirect, $url);
            }
        }

        // Fall back to the public WordPress page whose slug is "login".
        $page = get_page_by_path('login');
        if ($page instanceof \WP_Post) {
            $url = get_permalink($page);
            if (is_string($url) && $url !== '') {
                return add_query_arg('redirect_to', $redirect, $url);
            }
        }

        // The FieldFlow installation uses /login/ as the public authentication entry point.
        return add_query_arg('redirect_to', $redirect, home_url('/login/'));
    }

    private static function withFrame(string $url): string {
        if ($url === '') return '';
        return add_query_arg(self::FRAME_FLAG, 1, $url);
    }

    private static function menuItems(int $userId, array $profile): array {
        $items = [];
        $access = is_array($profile['access'] ?? null) ? $profile['access'] : self::portalAccess($userId);
        $profileKey = (string)($profile['key'] ?? 'restricted');
        $canManage = !empty($profile['can_manage']);

        $pwa = class_exists('\\RoutesPro\\PWA\\Settings') ? \RoutesPro\PWA\Settings::get() : [];
        $operativePageId = absint($pwa['app_page_id'] ?? 0);
        $clientPageId = absint($pwa['client_app_page_id'] ?? 0);
        $operativeUrl = '';
        $clientUrl = '';
        if ($operativePageId > 0) {
            $candidate = get_permalink($operativePageId);
            if ($candidate) $operativeUrl = self::withFrame((string)$candidate);
        }
        if ($clientPageId > 0) {
            $candidate = get_permalink($clientPageId);
            if ($candidate) $clientUrl = self::withFrame((string)$candidate);
        }

        $admin = static function(string $key, string $label, string $icon, string $slug): array {
            $url = $slug === 'users.php' ? admin_url('users.php') : admin_url('admin.php?page=' . rawurlencode($slug));
            return self::item($key, $label, $icon, self::withFrame($url), 'admin');
        };

        if ($canManage) {
            $items[] = self::divider('Workspace');
            $items[] = $admin('dashboard', 'Dashboard', 'dashboard', 'routespro');
            if (!empty($access['operational']) && $operativeUrl !== '') $items[] = self::item('operational', 'Operação', 'field', $operativeUrl, 'front');
            if (!empty($access['client']) && $clientUrl !== '') $items[] = self::item('client', 'Portal Cliente', 'client', $clientUrl, 'front');

            $items[] = self::divider('Operação');
            $items[] = $admin('routes', 'Rotas', 'routes', 'routespro-routes');
            $items[] = $admin('assignments', 'Intelligence Center', 'assignments', 'routespro-assignments-hub');
            $items[] = $admin('briefings', 'Briefings', 'briefings', 'routespro-briefings');
            $items[] = $admin('implementations', 'Implementações', 'implementations', 'routespro-implementations');
            $items[] = $admin('stocks', 'Stocks', 'stocks', 'routespro-stocks');
            $items[] = $admin('shopping', 'Shopping e Preços', 'shopping', 'routespro-shopping');
            $items[] = $admin('expenses', 'Despesas', 'expenses', 'routespro-expenses');
            $items[] = $admin('mapping', 'Centro de Mapping', 'mapping', 'routespro-mapping-center');

            $items[] = self::divider('Dados e Conteúdo');
            $items[] = $admin('commercial', 'Base Comercial', 'commercial', 'routespro-commercial');
            $items[] = $admin('stores', 'Lojas', 'stores', 'routespro-stores');
            $items[] = $admin('commercial-import', 'Importar PDVs', 'import', 'routespro-commercial-import');
            $items[] = $admin('categories', 'Categorias', 'categories', 'routespro-categories');
            $items[] = $admin('campaign-pdvs', 'Campanhas PDV', 'stores', 'routespro-campaign-locations');
            $items[] = $admin('context', 'Perguntas Contextuais', 'context', 'routespro-context-questions');
            $items[] = $admin('forms', 'Formulários', 'forms', 'routespro-forms');
            $items[] = $admin('form-new', 'Novo Formulário', 'formnew', 'routespro-form-edit');
            $items[] = $admin('form-bindings', 'Formulários por Contexto', 'bindings', 'routespro-form-bindings');
            $items[] = $admin('submissions', 'Submissões', 'submissions', 'routespro-form-submissions');
            $items[] = $admin('history', 'Histórico de Formulários', 'history', 'routespro-form-history');
            $items[] = $admin('cardex', 'Cardex e Catálogo', 'cardex', 'routespro-product-cardex');
            $items[] = $admin('legacy-import', 'Importar Locais', 'import', 'routespro-import');

            $items[] = self::divider('Gestão');
            $items[] = $admin('clients', 'Clientes', 'clients', 'routespro-clients');
            $items[] = $admin('client-onboarding', 'Onboarding de Clientes', 'onboarding', 'routespro-client-onboarding');
            $items[] = $admin('projects', 'Projetos', 'projects', 'routespro-projects');
            if (user_can($userId, 'list_users')) $items[] = $admin('users', 'Utilizadores', 'users', 'users.php');
            $items[] = $admin('subscriptions', 'Subscrições', 'subscriptions', 'routespro-subscriptions');
            $items[] = $admin('license-tenants', 'Licenciamento & Tenants', 'license', 'routespro-license-tenants');
            $items[] = $admin('release-manager', 'Release Manager', 'import', 'routespro-release-manager');
            $items[] = $admin('billing', 'Faturação', 'billing', 'routespro-billing');

            $items[] = self::divider('Análise e IA');
            $items[] = $admin('analytics', 'Growth Hub', 'analytics', 'routespro-performance');
            $items[] = $admin('ai', 'Configuração de IA', 'ai', 'routespro-ai');

            $items[] = self::divider('Comunicação');
            $items[] = $admin('emails', 'Mail Center', 'email', 'routespro-emails');
            $items[] = $admin('email-log', 'E-mails Enviados', 'emaillog', 'routespro-email-log');

            $items[] = self::divider('Integrações');
            $items[] = $admin('integrations', 'Integrações', 'integrations', 'routespro-integrations');
            $items[] = $admin('data-destinations', 'Destinos de Dados', 'data', 'routespro-data-destinations');
            $items[] = $admin('public-api', 'API Pública e Webhooks', 'api', 'routespro-public-integrations');

            $items[] = self::divider('Experiência');
            $items[] = $admin('appearance', 'Personalização', 'appearance', 'routespro-appearance');
            $items[] = $admin('campaign-branding', 'Temas por Campanha', 'theme', 'routespro-campaign-branding');
            $items[] = $admin('mobile', 'Aplicação Mobile', 'mobile', 'routespro-mobile');
            $items[] = $admin('devices', 'Dispositivos', 'devices', 'routespro-devices');
            $items[] = $admin('pwa', 'PWA Web', 'pwa', 'routespro-pwa');

            $items[] = self::divider('Sistema e Governação');
            $items[] = $admin('privacy', 'Privacidade e Governação', 'privacy', 'routespro-privacy');
            $items[] = $admin('operational-purge', 'Purga Operacional', 'processes', 'routespro-operational-purge');
            $items[] = $admin('security', 'Segurança e Observabilidade', 'security', 'routespro-security-observability');
            $items[] = $admin('processes', 'Centro de Processos', 'processes', 'routespro-processes');
            $items[] = $admin('settings', 'Configurações', 'settings', 'routespro-settings');
            $items[] = $admin('health', 'Saúde do Sistema', 'health', 'routespro-system-health');

            if (user_can($userId, 'manage_options')) {
                $items[] = self::divider('Administração técnica');
                $items[] = self::item('wordpress', 'WordPress completo', 'wordpress', admin_url(), 'technical', true);
            }
            return $items;
        }

        if ($profileKey === 'client') {
            $items[] = self::divider('Cliente');
            if (!empty($access['client']) && $clientUrl !== '') $items[] = self::item('client', 'Portal Cliente', 'client', $clientUrl, 'front');
            if (!empty($access['operational']) && $operativeUrl !== '') $items[] = self::item('operational', 'Operação', 'field', $operativeUrl, 'front');
            return $items;
        }

        $items[] = self::divider($profileKey === 'supervisor' ? 'Supervisão' : 'Operação');
        if (!empty($access['operational']) && $operativeUrl !== '') $items[] = self::item('operational', $profileKey === 'supervisor' ? 'Operação e Planeamento' : 'Minha Operação', 'field', $operativeUrl, 'front');
        if (!empty($access['client']) && $clientUrl !== '') $items[] = self::item('client', 'Portal Cliente', 'client', $clientUrl, 'front');
        return $items;
    }

    private static function permissionStamp(int $userId, ?array $profile = null): string {
        $profile = is_array($profile) ? $profile : self::desktopProfile($userId);
        $items = self::menuItems($userId, $profile);
        $normalized = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'item') continue;
            $normalized[] = [
                'key' => (string)($item['key'] ?? ''),
                'url' => (string)($item['url'] ?? ''),
                'technical' => !empty($item['technical']),
            ];
        }
        $access = is_array($profile['access'] ?? null) ? $profile['access'] : [];
        return hash('sha256', (string)wp_json_encode([
            'profile' => (string)($profile['key'] ?? 'restricted'),
            'can_manage' => !empty($profile['can_manage']),
            'operational' => !empty($access['operational']),
            'client' => !empty($access['client']),
            'items' => $normalized,
        ]));
    }

    private static function item(string $key, string $label, string $icon, string $url, string $group, bool $technical = false): array {
        return [
            'type' => 'item',
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'url' => $url,
            'group' => $group,
            'technical' => $technical,
        ];
    }

    private static function divider(string $label): array {
        return ['type' => 'divider', 'label' => $label];
    }

    private static function initialUrl(array $items, array $profile): string {
        $profileKey = (string)($profile['key'] ?? 'restricted');
        $preferred = $profileKey === 'admin' ? 'dashboard' : ($profileKey === 'client' ? 'client' : 'operational');
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'item' && ($item['key'] ?? '') === $preferred && !empty($item['url'])) {
                return (string)$item['url'];
            }
        }
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'item' && empty($item['technical']) && !empty($item['url'])) return (string)$item['url'];
        }
        return self::withFrame(home_url('/'));
    }

    private static function commandIndex(array $items, array $profile): array {
        global $wpdb;
        $commands = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') !== 'item' || empty($item['url'])) continue;
            $commands[] = [
                'label' => (string)($item['label'] ?? 'FieldFlow'),
                'meta' => !empty($item['technical']) ? 'Administracao tecnica' : 'Modulo FieldFlow',
                'url' => (string)$item['url'],
                'activate_key' => (string)($item['key'] ?? ''),
                'technical' => !empty($item['technical']),
            ];
        }
        if (empty($profile['can_manage'])) return $commands;

        $clientTable = $wpdb->prefix . 'routespro_clients';
        $projectTable = $wpdb->prefix . 'routespro_projects';
        $tableExists = static function(string $table) use ($wpdb): bool {
            return $table !== '' && (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        };
        if ($tableExists($clientTable)) {
            $rows = $wpdb->get_results("SELECT id,name FROM {$clientTable} ORDER BY name ASC LIMIT 200", ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $commands[] = [
                    'label' => (string)($row['name'] ?? ('Cliente #' . (int)$row['id'])),
                    'meta' => 'Cliente',
                    'url' => self::withFrame(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'clients','client_id'=>(int)$row['id']], admin_url('admin.php'))),
                    'activate_key' => 'assignments',
                    'technical' => false,
                ];
            }
        }
        if ($tableExists($projectTable)) {
            $rows = $wpdb->get_results("SELECT id,client_id,name FROM {$projectTable} ORDER BY name ASC LIMIT 250", ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $commands[] = [
                    'label' => (string)($row['name'] ?? ('Campanha #' . (int)$row['id'])),
                    'meta' => 'Campanha / Projeto',
                    'url' => self::withFrame(add_query_arg(['page'=>'routespro-assignments-hub','tab'=>'projects','client_id'=>(int)($row['client_id']??0),'project_id'=>(int)$row['id']], admin_url('admin.php'))),
                    'activate_key' => 'assignments',
                    'technical' => false,
                ];
            }
        }
        return $commands;
    }

    private static function renderPing(): void {
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        if (!is_user_logged_in()) {
            status_header(401);
            echo wp_json_encode(['ok' => false, 'authenticated' => false]);
            exit;
        }
        $userId = get_current_user_id();
        $profile = self::desktopProfile($userId);
        status_header(200);
        echo wp_json_encode([
            'ok' => true,
            'authenticated' => true,
            'user_id' => $userId,
            'profile_key' => sanitize_key((string)($profile['key'] ?? 'restricted')),
            'permission_stamp' => self::permissionStamp($userId, $profile),
            'server_time' => current_time('mysql'),
            'version' => defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'desktop_release' => self::DESKTOP_RELEASE,
            'bridge_schema' => self::BRIDGE_SCHEMA,
        ]);
        exit;
    }

    private static function renderActivity(): void {
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (!is_user_logged_in()) {
            status_header(401);
            echo wp_json_encode(['ok' => false, 'authenticated' => false, 'events' => []]);
            exit;
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        $userEmail = strtolower((string)($user->user_email ?? ''));
        $events = [];
        $incomingNew = 0;

        try {
            global $wpdb;
            $table = $wpdb->prefix . 'routespro_email_logs';
            $exists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
            if ($exists) {
                if ($userEmail !== '') {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT el.id,el.email_type,el.sender_user_id,el.recipient_user_id,el.recipient_email,el.subject,el.body,el.meta_json,el.created_at,su.display_name AS sender_name,su.user_email AS sender_email_joined
                         FROM {$table} el
                         LEFT JOIN {$wpdb->users} su ON su.ID=el.sender_user_id
                         WHERE el.email_type IN ('client_team_message','client_team_message_reply')
                           AND (el.recipient_user_id=%d OR LOWER(el.recipient_email)=%s)
                         ORDER BY el.created_at DESC,el.id DESC
                         LIMIT 30",
                        $userId,
                        $userEmail
                    ), ARRAY_A) ?: [];
                } else {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT el.id,el.email_type,el.sender_user_id,el.recipient_user_id,el.recipient_email,el.subject,el.body,el.meta_json,el.created_at,su.display_name AS sender_name,su.user_email AS sender_email_joined
                         FROM {$table} el
                         LEFT JOIN {$wpdb->users} su ON su.ID=el.sender_user_id
                         WHERE el.email_type IN ('client_team_message','client_team_message_reply')
                           AND el.recipient_user_id=%d
                         ORDER BY el.created_at DESC,el.id DESC
                         LIMIT 30",
                        $userId
                    ), ARRAY_A) ?: [];
                }

                foreach ((array)$rows as $row) {
                    $senderUserId = absint($row['sender_user_id'] ?? 0);
                    if ($senderUserId === $userId) continue;
                    $id = absint($row['id'] ?? 0);
                    if ($id <= 0) continue;
                    $meta = json_decode((string)($row['meta_json'] ?? ''), true);
                    if (!is_array($meta)) $meta = [];
                    $status = sanitize_key((string)($meta['workflow_status'] ?? 'novo')) ?: 'novo';
                    if ($status === 'novo') $incomingNew++;
                    $subject = sanitize_text_field((string)($row['subject'] ?? ''));
                    $sender = sanitize_text_field((string)($row['sender_name'] ?? $row['sender_email_joined'] ?? 'FieldFlow'));
                    $plainBody = trim(wp_strip_all_tags((string)($row['body'] ?? '')));
                    $body = $plainBody !== '' ? wp_trim_words($plainBody, 28, '...') : 'Recebeste uma nova mensagem no FieldFlow.';
                    $url = class_exists('\\RoutesPro\\Admin\\Emails')
                        ? \RoutesPro\Admin\Emails::get_message_app_url($id)
                        : add_query_arg('routespro_message', $id, home_url('/')) . '#rp-app-messages';
                    $events[] = [
                        'id' => $id,
                        'type' => 'message',
                        'title' => $subject !== '' ? $subject : 'Nova mensagem FieldFlow',
                        'body' => $body,
                        'sender' => $sender,
                        'status' => $status,
                        'created_at' => sanitize_text_field((string)($row['created_at'] ?? '')),
                        'url' => esc_url_raw(self::withFrame($url)),
                    ];
                    if (count($events) >= 20) break;
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::captureThrowable('FieldFlow Desktop activity feed failed.', $e, ['user_id' => $userId]);
            }
        }

        $events = apply_filters('fieldflow_desktop_activity_events', $events, $userId);
        if (!is_array($events)) $events = [];
        $normalized = [];
        foreach (array_slice($events, 0, 40) as $event) {
            if (!is_array($event)) continue;
            $id = absint($event['id'] ?? 0);
            if ($id <= 0) continue;
            $normalized[] = [
                'id' => $id,
                'type' => sanitize_key((string)($event['type'] ?? 'event')) ?: 'event',
                'title' => sanitize_text_field((string)($event['title'] ?? 'FieldFlow')),
                'body' => sanitize_text_field((string)($event['body'] ?? '')),
                'sender' => sanitize_text_field((string)($event['sender'] ?? '')),
                'status' => sanitize_key((string)($event['status'] ?? '')),
                'created_at' => sanitize_text_field((string)($event['created_at'] ?? '')),
                'url' => esc_url_raw((string)($event['url'] ?? '')),
            ];
        }
        usort($normalized, static function(array $a, array $b): int {
            return ((int)$b['id']) <=> ((int)$a['id']);
        });

        status_header(200);
        echo wp_json_encode([
            'ok' => true,
            'authenticated' => true,
            'events' => $normalized,
            'summary' => [
                'incoming_new' => max(0, $incomingNew),
                'event_count' => count($normalized),
            ],
            'server_time' => current_time('mysql'),
            'version' => defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
        ]);
        exit;
    }

    private static function icon(string $name): string {
        $icons = [
            'field' => 'M4 19h16M6 17V8l6-4 6 4v9M9 17v-5h6v5',
            'client' => 'M4 20v-9h16v9M7 11V6h10v5M9 20v-5h6v5',
            'dashboard' => 'M4 4h6v6H4zM14 4h6v4h-6zM14 12h6v8h-6zM4 14h6v6H4z',
            'clients' => 'M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 11a4 4 0 0 1 4 4v5M16 3.2a4 4 0 0 1 0 7.6',
            'projects' => 'M4 7h6l2 2h8v10H4zM4 7V5h6l2 2',
            'routes' => 'M5 19c0-4 4-4 4-8s-4-4-4-8M19 5c0 4-4 4-4 8s4 4 4 8M5 3h.01M19 21h.01',
            'assignments' => 'M4 6h16M4 12h16M4 18h10M7 3v6M17 9v6M10 15v6',
            'forms' => 'M6 3h9l3 3v15H6zM9 11h6M9 15h6M9 7h3',
            'submissions' => 'M5 12l4 4L19 6M4 20h16',
            'briefings' => 'M6 4h12v16H6zM9 8h6M9 12h6M9 16h4',
            'implementations' => 'M4 20h16M6 17l3-10 3 6 2-4 4 8',
            'stocks' => 'M4 7l8-4 8 4-8 4zM4 7v10l8 4 8-4V7M12 11v10',
            'expenses' => 'M4 7h16v12H4zM7 7V5h10v2M8 13h8M12 10v6',
            'commercial' => 'M4 20V9l8-5 8 5v11M8 20v-6h8v6M9 10h.01M15 10h.01',
            'cardex' => 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5',
            'stores' => 'M3 9l2-5h14l2 5M5 9v11h14V9M8 20v-6h8v6M3 9h18',
            'analytics' => 'M5 20V10M12 20V4M19 20v-7',
            'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
            'settings' => 'M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06-2 3.46-.09-.03a1.7 1.7 0 0 0-1.95.21l-.06.05a1.7 1.7 0 0 0-.57 1.79V22h-4v-.1a1.7 1.7 0 0 0-.57-1.79l-.06-.05a1.7 1.7 0 0 0-1.95-.21l-.09.03-2-3.46.06-.06A1.7 1.7 0 0 0 6.6 15l-.02-.08a1.7 1.7 0 0 0-1.38-1.58H5v-4h.2a1.7 1.7 0 0 0 1.38-1.58l.02-.08a1.7 1.7 0 0 0-.34-1.87l-.06-.06 2-3.46.09.03a1.7 1.7 0 0 0 1.95-.21l.06-.05A1.7 1.7 0 0 0 10.87.3V0h4v.3a1.7 1.7 0 0 0 .57 1.79l.06.05a1.7 1.7 0 0 0 1.95.21l.09-.03 2 3.46-.06.06a1.7 1.7 0 0 0-.34 1.87l.02.08a1.7 1.7 0 0 0 1.38 1.58H21v4h-.2a1.7 1.7 0 0 0-1.38 1.58z',
            'health' => 'M3 12h4l2-7 4 14 2-7h6',
            'shopping' => 'M4 7h16l-1 13H5L4 7M8 7a4 4 0 0 1 8 0M8 11h.01M16 11h.01',
            'context' => 'M4 5h16v11H8l-4 4zM8 9h8M8 13h5',
            'history' => 'M4 12a8 8 0 1 0 2.34-5.66L4 8M4 4v4h4M12 8v5l3 2',
            'onboarding' => 'M4 5h16v14H4zM8 9h8M8 13h5M16.5 15.5l1.5 1.5 3-3',
            'integrations' => 'M8 3v4M16 3v4M5 7h14v5a7 7 0 0 1-14 0zM12 19v3',
            'data' => 'M4 6c0-2 3.6-3 8-3s8 1 8 3-3.6 3-8 3-8-1-8-3zM4 6v6c0 2 3.6 3 8 3s8-1 8-3V6M4 12v6c0 2 3.6 3 8 3s8-1 8-3v-6',
            'mobile' => 'M8 2h8a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2M10 18h4',
            'pwa' => 'M4 5h16v14H4zM4 9h16M8 7h.01M11 7h.01M14 7h.01',
            'mapping' => 'M5 19l4-4 4 2 6-7M5 5h14v14H5zM9 9h.01',
            'import' => 'M12 3v12M7 10l5 5 5-5M5 21h14',
            'categories' => 'M4 6h6v6H4zM14 6h6v6h-6zM4 16h6v4H4zM14 16h6v4h-6z',
            'formnew' => 'M6 3h9l3 3v15H6zM12 10v7M8.5 13.5h7',
            'bindings' => 'M7 7h4v4H7zM13 13h4v4h-4zM11 9h3a3 3 0 0 1 3 3v1M13 15h-3a3 3 0 0 1-3-3v-1',
            'subscriptions' => 'M4 6h16v12H4zM7 10h10M7 14h6',
            'license' => 'M12 3a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm-2 10v8l2-1.5 2 1.5v-8',
            'billing' => 'M6 3h12v18H6zM9 8h6M9 12h6M9 16h4',
            'ai' => 'M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1M9 12a3 3 0 1 0 6 0 3 3 0 0 0-6 0',
            'email' => 'M3 6h18v12H3zM3 7l9 7 9-7',
            'emaillog' => 'M3 6h18v12H3zM3 7l9 7 9-7M17 15h3',
            'api' => 'M8 9l-4 3 4 3M16 9l4 3-4 3M14 5l-4 14',
            'appearance' => 'M12 3a9 9 0 1 0 0 18h1a2 2 0 0 0 0-4h-1a2 2 0 0 1 0-4h5a4 4 0 0 0 0-8zM7.5 10h.01M9.5 6.5h.01M14.5 6.5h.01M16.5 10h.01',
            'theme' => 'M4 5h16v14H4zM4 9h16M8 5v4',
            'devices' => 'M7 3h10v18H7zM10 17h4',
            'privacy' => 'M12 3l7 3v5c0 4.5-2.8 8.2-7 10-4.2-1.8-7-5.5-7-10V6zM9 12l2 2 4-4',
            'security' => 'M12 3l7 3v5c0 4.5-2.8 8.2-7 10-4.2-1.8-7-5.5-7-10V6zM9 12h6',
            'processes' => 'M5 6h14M5 12h14M5 18h14M8 3v6M16 9v6M10 15v6',
            'wordpress' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20M4.5 8h15M7 4.5c2 4.5 3.4 8.9 5 15M17 4.5c-2 4.5-3.4 8.9-5 15',
        ];
        $path = $icons[$name] ?? $icons['dashboard'];
        return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="' . esc_attr($path) . '"></path></svg>';
    }

    private static function renderShell(int $userId, array $profile): void {
        $user = get_userdata($userId);
        $items = self::menuItems($userId, $profile);
        $initial = self::initialUrl($items, $profile);
        $commands = self::commandIndex($items, $profile);
        $pingUrl = self::pingUrl();
        $activityUrl = self::activityUrl();
        $ajaxUrl = admin_url('admin-ajax.php');
        $logo = defined('ROUTESPRO_URL') ? ROUTESPRO_URL . 'assets/logo-twt.png' : '';
        $display = $user ? ($user->display_name ?: $user->user_login) : 'Utilizador';
        $profileKey = sanitize_key((string)($profile['key'] ?? 'restricted'));
        $profileLabel = (string)($profile['label'] ?? 'FieldFlow');
        $workspaceLabel = (string)($profile['workspace_label'] ?? 'Workspace');
        $canManage = !empty($profile['can_manage']);
        $loginUrl = self::loginUrl(self::desktopUrl());
        $logout = wp_logout_url($loginUrl);
        $version = defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : (defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '');
        $permissionStamp = self::permissionStamp($userId, $profile);
        $maxUpload = function_exists('wp_max_upload_size') ? (int)wp_max_upload_size() : 0;
        $loginPath = (string)(parse_url($loginUrl, PHP_URL_PATH) ?: '/login/');
        $desktopRelease = self::DESKTOP_RELEASE;
        $bridgeSchema = self::BRIDGE_SCHEMA;

        nocache_headers();
        status_header(200);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Robots-Tag: noindex, nofollow', true);
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
        header('Permissions-Policy: camera=(self), microphone=(self), geolocation=(self)');
        ?>
<!doctype html>
<html lang="pt-PT">
<head>
<meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FieldFlow Desktop</title>
<style>
:root{--ff-bg:#eef2f7;--ff-nav:#0a1020;--ff-nav-2:#111a2e;--ff-text:#0f172a;--ff-muted:#64748b;--ff-line:#dbe3ee;--ff-accent:#10bcd4;--ff-card:#fff;--ff-side:276px}
*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;overflow:hidden;font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--ff-bg);color:var(--ff-text)}button{font-family:inherit}
.ffd-app{display:grid;grid-template-columns:var(--ff-side) minmax(0,1fr);height:100vh;min-width:900px;transition:grid-template-columns .18s ease}.ffd-side{background:linear-gradient(180deg,var(--ff-nav),var(--ff-nav-2));color:#d8e1ee;display:flex;flex-direction:column;min-width:0;border-right:1px solid rgba(255,255,255,.06);overflow:hidden}
.ffd-brand{height:84px;padding:18px 18px 15px;display:flex;align-items:center;gap:12px;border-bottom:1px solid rgba(255,255,255,.08);min-width:0}.ffd-brand-mark{display:grid;place-items:center;width:43px;height:43px;border-radius:13px;background:#fff;overflow:hidden;flex:0 0 auto}.ffd-brand-mark img{width:35px;height:35px;object-fit:contain}.ffd-brand-copy{min-width:0}.ffd-brand-copy strong{display:block;color:#fff;font-family:Georgia,serif;font-size:23px;line-height:1;white-space:nowrap}.ffd-brand-copy span{display:block;margin-top:5px;color:#8290a7;font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;white-space:nowrap}.ffd-profile-badge{margin-left:auto;flex:0 0 auto;padding:5px 7px;border-radius:999px;background:rgba(16,188,212,.12);border:1px solid rgba(16,188,212,.2);color:#9debf5;font-size:9px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;max-width:92px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ffd-search-wrap{padding:10px 10px 0}.ffd-search{width:100%;height:36px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.06);color:#fff;border-radius:10px;padding:0 11px;font:inherit;font-size:11px;outline:none;box-sizing:border-box}.ffd-search::placeholder{color:#708198}.ffd-search:focus{border-color:rgba(16,188,212,.55);background:rgba(255,255,255,.09)}.ffd-nav{padding:8px 10px 18px;overflow:auto;flex:1;scrollbar-width:thin;scrollbar-color:#334155 transparent}.ffd-divider{padding:16px 12px 7px;color:#6f7e95;font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;white-space:nowrap;overflow:hidden}.ffd-link{width:100%;border:0;background:transparent;color:#b7c3d5;display:flex;align-items:center;gap:11px;padding:10px 12px;margin:2px 0;border-radius:11px;font:inherit;font-size:13px;font-weight:750;text-align:left;cursor:pointer;transition:.16s;min-width:0}.ffd-link:hover{background:rgba(255,255,255,.07);color:#fff}.ffd-link.active{background:rgba(16,188,212,.16);color:#fff;box-shadow:inset 3px 0 0 var(--ff-accent)}.ffd-link.technical{margin-top:4px;border:1px dashed rgba(255,255,255,.12);color:#9ba9bc}.ffd-link.technical.active{border-color:rgba(251,146,60,.55);box-shadow:inset 3px 0 0 #fb923c;background:rgba(251,146,60,.11)}.ffd-link svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}.ffd-link span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ffd-tech-tag{margin-left:auto;font-size:8px;line-height:1;padding:4px 5px;border-radius:999px;background:rgba(251,146,60,.13);color:#fdba74;font-weight:900;text-transform:uppercase;letter-spacing:.05em;flex:0 0 auto}
.ffd-user{padding:12px 13px 14px;border-top:1px solid rgba(255,255,255,.08)}.ffd-user-card{display:grid;grid-template-columns:36px minmax(0,1fr) 30px;gap:9px;align-items:center;padding:8px;border-radius:12px;background:rgba(255,255,255,.05)}.ffd-avatar{width:36px;height:36px;border-radius:10px;background:linear-gradient(145deg,#15c4da,#4f72ff);color:#fff;display:grid;place-items:center;font-weight:900}.ffd-user-name{overflow:hidden}.ffd-user-name strong,.ffd-user-name span{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ffd-user-name strong{font-size:12px;color:#fff}.ffd-user-name span{font-size:10px;color:#8290a7;margin-top:2px}.ffd-logout{display:grid;place-items:center;width:30px;height:30px;border-radius:9px;color:#97a4b8;text-decoration:none}.ffd-logout:hover{background:rgba(255,255,255,.08);color:#fff}.ffd-logout svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ffd-main{display:grid;grid-template-rows:58px minmax(0,1fr);min-width:0;background:#f5f7fb}.ffd-top{background:rgba(255,255,255,.96);border-bottom:1px solid var(--ff-line);display:flex;align-items:center;justify-content:space-between;padding:0 18px 0 12px;box-shadow:0 2px 14px rgba(15,23,42,.04);z-index:2;gap:12px}.ffd-title-wrap{display:flex;align-items:center;min-width:0;gap:8px}.ffd-collapse{width:36px;height:36px;border:1px solid var(--ff-line);background:#fff;border-radius:10px;display:grid;place-items:center;cursor:pointer;color:#475569;flex:0 0 auto}.ffd-collapse:hover{background:#f8fafc;border-color:#cbd5e1}.ffd-collapse svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}.ffd-title{display:flex;align-items:center;gap:10px;min-width:0}.ffd-title strong{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:min(42vw,520px)}.ffd-title span{font-size:11px;color:var(--ff-muted);padding:5px 8px;border:1px solid var(--ff-line);border-radius:999px;background:#f8fafc;white-space:nowrap}.ffd-actions{display:flex;align-items:center;gap:8px}.ffd-action{height:34px;border:1px solid var(--ff-line);background:#fff;border-radius:10px;padding:0 11px;display:inline-flex;align-items:center;gap:7px;color:#334155;font:inherit;font-size:11px;font-weight:800;cursor:pointer}.ffd-action:hover{border-color:#b9c6d6;background:#f8fafc}.ffd-action svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ffd-stage{position:relative;min-height:0}.ffd-frame{width:100%;height:100%;border:0;background:#fff;display:block}.ffd-loading{position:absolute;inset:0;display:grid;place-items:center;background:#f5f7fb;z-index:1;transition:opacity .2s}.ffd-loading[hidden]{opacity:0;pointer-events:none}.ffd-loader-card{display:flex;align-items:center;gap:12px;padding:13px 16px;border:1px solid var(--ff-line);background:#fff;border-radius:14px;box-shadow:0 18px 42px rgba(15,23,42,.08);font-size:12px;font-weight:800;color:#475569}.ffd-spinner{width:18px;height:18px;border:2px solid #dce6ef;border-top-color:var(--ff-accent);border-radius:50%;animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
.ffd-app.is-collapsed{grid-template-columns:74px minmax(0,1fr)}.ffd-app.is-collapsed .ffd-brand{justify-content:center;padding-inline:10px}.ffd-app.is-collapsed .ffd-brand-copy,.ffd-app.is-collapsed .ffd-profile-badge,.ffd-app.is-collapsed .ffd-search-wrap,.ffd-app.is-collapsed .ffd-divider,.ffd-app.is-collapsed .ffd-link span,.ffd-app.is-collapsed .ffd-tech-tag,.ffd-app.is-collapsed .ffd-user-name{display:none}.ffd-app.is-collapsed .ffd-nav{padding-inline:8px}.ffd-app.is-collapsed .ffd-link{justify-content:center;padding:11px 8px}.ffd-app.is-collapsed .ffd-user-card{grid-template-columns:36px;justify-content:center}.ffd-app.is-collapsed .ffd-logout{display:none}
@media(max-width:1120px){:root{--ff-side:236px}.ffd-link{font-size:12px}.ffd-brand-copy strong{font-size:20px}.ffd-profile-badge{display:none}}
.ffd-connection{height:30px;display:inline-flex;align-items:center;gap:7px;padding:0 10px;border:1px solid #d7e2ec;border-radius:999px;background:#fff;color:#475569;font-size:10px;font-weight:850;white-space:nowrap}.ffd-connection i{width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.12)}.ffd-connection.offline i{background:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.12)}.ffd-connection.server i{background:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,.12)}.ffd-connection.checking i{background:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.12)}
.ffd-command-btn{height:34px;border:1px solid var(--ff-line);background:#f8fafc;border-radius:10px;padding:0 11px;display:inline-flex;align-items:center;gap:8px;color:#334155;font:inherit;font-size:11px;font-weight:800;cursor:pointer}.ffd-command-btn kbd{border:1px solid #cbd5e1;background:#fff;border-radius:6px;padding:2px 5px;font-size:9px;color:#64748b}.ffd-command-btn:hover{background:#fff;border-color:#b7c5d4}.ffd-icon-action{width:34px;padding:0;justify-content:center;position:relative}.ffd-icon-action .ffd-badge{position:absolute;right:-4px;top:-5px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:#0f172a;color:#fff;font-size:8px;display:grid;place-items:center}
.ffd-icon-action.is-active{border-color:#86efac;background:#f0fdf4;color:#166534}.ffd-icon-action.is-denied{border-color:#fecaca;background:#fef2f2;color:#991b1b}.ffd-notify-badge{position:absolute;right:-5px;top:-5px;min-width:17px;height:17px;padding:0 4px;border-radius:999px;background:#ef4444;color:#fff;font-size:8px;font-weight:900;display:grid;place-items:center}.ffd-notify-badge[hidden]{display:none}
.ffd-command-layer{position:fixed;inset:0;z-index:200;display:grid;place-items:start center;padding-top:min(12vh,110px);background:rgba(7,13,25,.45);backdrop-filter:blur(5px)}.ffd-command-layer[hidden]{display:none}.ffd-command-card{width:min(720px,calc(100vw - 44px));max-height:min(650px,76vh);display:grid;grid-template-rows:auto minmax(0,1fr);overflow:hidden;border:1px solid #dce5ef;border-radius:20px;background:#fff;box-shadow:0 30px 90px rgba(15,23,42,.28)}.ffd-command-head{padding:13px;border-bottom:1px solid #e8eef5}.ffd-command-input{width:100%;height:48px;border:0;outline:0;padding:0 13px;font:inherit;font-size:15px;color:#0f172a;background:#f8fafc;border-radius:12px}.ffd-command-list{overflow:auto;padding:8px}.ffd-command-item{width:100%;border:0;background:transparent;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:11px 12px;border-radius:12px;text-align:left;cursor:pointer;color:#0f172a}.ffd-command-item:hover,.ffd-command-item.is-selected{background:#effafe}.ffd-command-item strong{display:block;font-size:12px}.ffd-command-item small{display:block;margin-top:3px;color:#64748b;font-size:10px}.ffd-command-item em{font-style:normal;color:#94a3b8;font-size:9px;font-weight:800}.ffd-command-empty{padding:26px;text-align:center;color:#64748b;font-size:12px}
.ffd-recent-panel{position:fixed;top:0;right:0;bottom:0;width:min(390px,92vw);z-index:190;background:#fff;border-left:1px solid #dce5ef;box-shadow:-20px 0 60px rgba(15,23,42,.16);transform:translateX(102%);transition:transform .2s ease;display:grid;grid-template-rows:auto minmax(0,1fr)}.ffd-recent-panel.open{transform:translateX(0)}.ffd-recent-head{height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid #e8eef5}.ffd-recent-head strong{font-size:13px}.ffd-recent-close{width:32px;height:32px;border:1px solid #dbe3ec;border-radius:9px;background:#fff;cursor:pointer}.ffd-recent-list{overflow:auto;padding:10px}.ffd-recent-item{width:100%;border:0;background:#fff;border-bottom:1px solid #eef2f7;padding:12px 10px;text-align:left;cursor:pointer}.ffd-recent-item:hover{background:#f8fafc}.ffd-recent-item strong{display:block;font-size:12px}.ffd-recent-item span{display:block;margin-top:4px;color:#94a3b8;font-size:10px}
.ffd-drop{position:absolute;inset:12px;z-index:40;border:2px dashed #22bdd2;border-radius:20px;background:rgba(236,254,255,.94);display:grid;place-items:center;color:#155e75;font-size:14px;font-weight:900;pointer-events:none}.ffd-drop[hidden]{display:none}.ffd-toast{position:fixed;right:18px;bottom:18px;z-index:240;max-width:420px;padding:11px 14px;border-radius:12px;background:#0f172a;color:#fff;box-shadow:0 16px 44px rgba(15,23,42,.24);font-size:11px;font-weight:750;opacity:0;transform:translateY(10px);pointer-events:none;transition:.18s}.ffd-toast.show{opacity:1;transform:none}
.ffd-offline{position:absolute;inset:0;z-index:35;display:grid;place-items:center;padding:24px;background:rgba(245,247,251,.96);backdrop-filter:blur(5px)}.ffd-offline[hidden]{display:none}.ffd-offline-card{width:min(430px,calc(100% - 30px));padding:24px;border:1px solid #dce5ef;border-radius:20px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.14);text-align:center}.ffd-offline-icon{width:52px;height:52px;margin:0 auto 13px;border-radius:16px;background:#fff7ed;color:#c2410c;display:grid;place-items:center}.ffd-offline-icon svg{width:25px;height:25px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.ffd-offline-card strong{display:block;font-size:16px}.ffd-offline-card p{margin:8px 0 17px;color:#64748b;font-size:12px;line-height:1.55}.ffd-primary{height:38px;border:0;border-radius:11px;padding:0 15px;background:#0f172a;color:#fff;font:inherit;font-size:11px;font-weight:850;cursor:pointer}.ffd-primary:hover{background:#1e293b}
.ffd-about-layer{position:fixed;inset:0;z-index:230;display:grid;place-items:center;padding:24px;background:rgba(7,13,25,.45);backdrop-filter:blur(5px)}.ffd-about-layer[hidden]{display:none}.ffd-about-card{width:min(520px,calc(100vw - 40px));border:1px solid #dce5ef;border-radius:22px;background:#fff;box-shadow:0 30px 90px rgba(15,23,42,.28);overflow:hidden}.ffd-about-head{display:flex;align-items:center;justify-content:space-between;padding:17px 19px;border-bottom:1px solid #e8eef5}.ffd-about-head strong{font-size:14px}.ffd-about-close{width:32px;height:32px;border:1px solid #dbe3ec;border-radius:9px;background:#fff;cursor:pointer}.ffd-about-body{padding:18px 19px 20px}.ffd-about-brand{display:flex;align-items:center;gap:12px;margin-bottom:17px}.ffd-about-brand img{width:46px;height:46px;object-fit:contain;border:1px solid #e2e8f0;border-radius:13px;padding:4px}.ffd-about-brand h2{font-size:18px;margin:0}.ffd-about-brand p{margin:3px 0 0;color:#64748b;font-size:11px}.ffd-about-grid{display:grid;grid-template-columns:1fr 1fr;gap:9px}.ffd-about-kv{padding:11px 12px;border:1px solid #e8eef5;border-radius:12px;background:#f8fafc}.ffd-about-kv span,.ffd-about-kv strong{display:block}.ffd-about-kv span{font-size:9px;font-weight:850;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8}.ffd-about-kv strong{margin-top:4px;font-size:11px;color:#0f172a;word-break:break-word}.ffd-release-chip{display:inline-flex;margin-top:14px;padding:6px 9px;border-radius:999px;background:#ecfeff;color:#155e75;border:1px solid #cffafe;font-size:9px;font-weight:900;letter-spacing:.05em;text-transform:uppercase}
@media(max-height:800px){.ffd-brand{height:68px;padding-top:12px;padding-bottom:11px}.ffd-brand-mark{width:38px;height:38px}.ffd-brand-mark img{width:31px;height:31px}.ffd-user{padding-top:8px;padding-bottom:9px}.ffd-link{padding-top:8px;padding-bottom:8px}.ffd-divider{padding-top:11px}.ffd-nav{padding-bottom:10px}}
@media(max-width:1050px){.ffd-title span{display:none}.ffd-actions{gap:4px}.ffd-icon-action{width:32px;height:32px}.ffd-top{padding-right:10px}.ffd-about-grid{grid-template-columns:1fr}}
@media(max-width:1250px){.ffd-command-btn span,.ffd-connection span{display:none}.ffd-command-btn kbd{display:none}.ffd-command-btn{width:34px;padding:0;justify-content:center}.ffd-connection{width:30px;padding:0;justify-content:center}.ffd-actions{gap:5px}.ffd-action{padding:0 8px}}
</style>
</head>
<body>
<div class="ffd-app" id="ffd-app" data-profile="<?php echo esc_attr($profileKey); ?>" data-user="<?php echo esc_attr((string)$userId); ?>">
<aside class="ffd-side">
  <div class="ffd-brand"><div class="ffd-brand-mark"><img src="<?php echo esc_url($logo); ?>" alt="TWT"></div><div class="ffd-brand-copy"><strong>FieldFlow</strong><span>Desktop<?php echo $version !== '' ? ' ' . esc_html($version) : ''; ?></span></div><div class="ffd-profile-badge"><?php echo esc_html($profileLabel); ?></div></div>
  <div class="ffd-search-wrap"><input type="search" class="ffd-search" id="ffd-search" placeholder="Pesquisar módulo..." autocomplete="off"></div>
  <nav class="ffd-nav" aria-label="Navegação FieldFlow">
    <?php foreach ($items as $item): ?>
      <?php if (($item['type'] ?? '') === 'divider'): ?><div class="ffd-divider"><?php echo esc_html((string)$item['label']); ?></div><?php continue; endif; ?>
      <?php $isActive = ((string)($item['url'] ?? '') === $initial); $technical = !empty($item['technical']); ?>
      <button type="button" class="ffd-link<?php echo $isActive ? ' active' : ''; ?><?php echo $technical ? ' technical' : ''; ?>" data-ff-key="<?php echo esc_attr((string)($item['key'] ?? '')); ?>" data-ff-url="<?php echo esc_url((string)$item['url']); ?>" data-ff-label="<?php echo esc_attr((string)$item['label']); ?>" data-ff-technical="<?php echo $technical ? '1' : '0'; ?>"><?php echo self::icon((string)$item['icon']); ?><span><?php echo esc_html((string)$item['label']); ?></span><?php if ($technical): ?><small class="ffd-tech-tag">Técnico</small><?php endif; ?></button>
    <?php endforeach; ?>
  </nav>
  <div class="ffd-user"><div class="ffd-user-card"><div class="ffd-avatar"><?php echo esc_html(strtoupper(substr($display, 0, 1))); ?></div><div class="ffd-user-name"><strong><?php echo esc_html($display); ?></strong><span><?php echo esc_html($profileLabel); ?></span></div><a class="ffd-logout" href="<?php echo esc_url($logout); ?>" title="Terminar sessão"><svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M14 4h5a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-5"></path></svg></a></div></div>
</aside>
<main class="ffd-main">
  <header class="ffd-top"><div class="ffd-title-wrap"><button type="button" class="ffd-collapse" id="ffd-collapse" aria-label="Recolher menu"><svg viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"></path></svg></button><div class="ffd-title"><strong id="ffd-current">FieldFlow</strong><span id="ffd-workspace"><?php echo esc_html($workspaceLabel); ?></span></div></div><div class="ffd-actions"><button type="button" class="ffd-command-btn" id="ffd-command-open" title="Pesquisa rápida"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-4-4"></path></svg><span>Pesquisar</span><kbd>Ctrl K</kbd></button><span class="ffd-connection checking" id="ffd-connection"><i></i><span>A verificar</span></span><button class="ffd-action ffd-icon-action" id="ffd-back" title="Voltar"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6"></path></svg></button><button class="ffd-action ffd-icon-action" id="ffd-forward" title="Avançar"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg></button><button class="ffd-action ffd-icon-action" id="ffd-reload" title="Atualizar"><svg viewBox="0 0 24 24"><path d="M20 6v6h-6M4 18v-6h6M6.5 8a7 7 0 0 1 11-2l2.5 2M17.5 16a7 7 0 0 1-11 2L4 16"></path></svg></button><button class="ffd-action ffd-icon-action" id="ffd-copy-link" title="Copiar link do módulo"><svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.1.1l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1M14 11a5 5 0 0 0-7.1-.1l-2 2A5 5 0 0 0 12 20l1.1-1.1"></path></svg></button><button class="ffd-action ffd-icon-action" id="ffd-print" title="Imprimir módulo"><svg viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"></path></svg></button><button class="ffd-action ffd-icon-action" id="ffd-notify" title="Ativar notificações"><svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path></svg><span class="ffd-notify-badge" id="ffd-notify-count" hidden>0</span></button><button class="ffd-action ffd-icon-action" id="ffd-recent-open" title="Recentes"><svg viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8M4 4v4h4M12 8v5l3 2"></path></svg><span class="ffd-badge" id="ffd-recent-count">0</span></button><button class="ffd-action ffd-icon-action" id="ffd-about-open" title="Acerca do FieldFlow Desktop"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v6M12 7h.01"></path></svg></button></div></header>
  <section class="ffd-stage" id="ffd-stage"><div class="ffd-loading" id="ffd-loading"><div class="ffd-loader-card"><span class="ffd-spinner"></span>A carregar FieldFlow...</div></div><div class="ffd-drop" id="ffd-drop" hidden>Larga aqui os ficheiros para anexar ao formulário aberto</div><div class="ffd-offline" id="ffd-offline" hidden><div class="ffd-offline-card"><div class="ffd-offline-icon"><svg viewBox="0 0 24 24"><path d="M2 8.8a15 15 0 0 1 20 0M5 12.4a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 20h.01M3 3l18 18"></path></svg></div><strong>Sem ligação ao FieldFlow</strong><p id="ffd-offline-text">Verifica a ligação à Internet. O Desktop volta a ligar automaticamente quando a rede regressar.</p><button type="button" class="ffd-primary" id="ffd-offline-retry">Tentar novamente</button></div></div><iframe class="ffd-frame" id="ffd-frame" src="about:blank" data-initial="<?php echo esc_url($initial); ?>" title="FieldFlow" allow="camera; microphone; geolocation; clipboard-read; clipboard-write"></iframe></section>
</main>
</div>
<div class="ffd-command-layer" id="ffd-command-layer" hidden><div class="ffd-command-card" role="dialog" aria-modal="true" aria-label="Pesquisa rápida"><div class="ffd-command-head"><input type="search" class="ffd-command-input" id="ffd-command-input" placeholder="Procurar módulo, cliente ou campanha..." autocomplete="off"></div><div class="ffd-command-list" id="ffd-command-list"><?php foreach ($commands as $index => $command): ?><button type="button" class="ffd-command-item" data-command-index="<?php echo (int)$index; ?>" data-command-label="<?php echo esc_attr((string)$command['label']); ?>" data-command-meta="<?php echo esc_attr((string)$command['meta']); ?>" data-command-url="<?php echo esc_url((string)$command['url']); ?>" data-command-key="<?php echo esc_attr((string)$command['activate_key']); ?>" data-command-technical="<?php echo !empty($command['technical']) ? '1' : '0'; ?>"><span><strong><?php echo esc_html((string)$command['label']); ?></strong><small><?php echo esc_html((string)$command['meta']); ?></small></span><em>Abrir</em></button><?php endforeach; ?><div class="ffd-command-empty" id="ffd-command-empty" hidden>Sem resultados.</div></div></div></div>
<aside class="ffd-recent-panel" id="ffd-recent-panel"><div class="ffd-recent-head"><strong>Recentes no Desktop</strong><button type="button" class="ffd-recent-close" id="ffd-recent-close">x</button></div><div class="ffd-recent-list" id="ffd-recent-list"></div></aside>
<div class="ffd-about-layer" id="ffd-about-layer" hidden><div class="ffd-about-card" role="dialog" aria-modal="true" aria-label="Acerca do FieldFlow Desktop"><div class="ffd-about-head"><strong>Acerca do FieldFlow Desktop</strong><button type="button" class="ffd-about-close" id="ffd-about-close">x</button></div><div class="ffd-about-body"><div class="ffd-about-brand"><img src="<?php echo esc_url($logo); ?>" alt="FieldFlow"><div><h2>FieldFlow Desktop</h2><p>The Wild Theory</p></div></div><div class="ffd-about-grid"><div class="ffd-about-kv"><span>Desktop</span><strong><?php echo esc_html($desktopRelease); ?></strong></div><div class="ffd-about-kv"><span>FieldFlow Core</span><strong><?php echo esc_html($version); ?></strong></div><div class="ffd-about-kv"><span>Native Bridge</span><strong>Schema <?php echo (int)$bridgeSchema; ?></strong></div><div class="ffd-about-kv"><span>Perfil</span><strong><?php echo esc_html($profileLabel); ?></strong></div><div class="ffd-about-kv"><span>Host</span><strong id="ffd-about-host">A detetar...</strong></div><div class="ffd-about-kv"><span>Upload máximo</span><strong><?php echo esc_html(size_format($maxUpload > 0 ? $maxUpload : 0)); ?></strong></div></div><span class="ffd-release-chip">Release Gate <?php echo esc_html($version); ?></span></div></div></div>
<div class="ffd-toast" id="ffd-toast"></div>
<script>
(function(){
  const app=document.getElementById('ffd-app'),frame=document.getElementById('ffd-frame'),loading=document.getElementById('ffd-loading'),title=document.getElementById('ffd-current'),workspace=document.getElementById('ffd-workspace'),search=document.getElementById('ffd-search'),stage=document.getElementById('ffd-stage'),drop=document.getElementById('ffd-drop'),toast=document.getElementById('ffd-toast'),connection=document.getElementById('ffd-connection');
  const commandLayer=document.getElementById('ffd-command-layer'),commandInput=document.getElementById('ffd-command-input'),commandItems=[...document.querySelectorAll('.ffd-command-item')],commandEmpty=document.getElementById('ffd-command-empty');
  const recentPanel=document.getElementById('ffd-recent-panel'),recentList=document.getElementById('ffd-recent-list'),recentCount=document.getElementById('ffd-recent-count'),notifyBtn=document.getElementById('ffd-notify'),notifyCount=document.getElementById('ffd-notify-count');
  const offlineLayer=document.getElementById('ffd-offline'),offlineText=document.getElementById('ffd-offline-text'),offlineRetry=document.getElementById('ffd-offline-retry'),aboutLayer=document.getElementById('ffd-about-layer'),aboutHost=document.getElementById('ffd-about-host');
  const links=[...document.querySelectorAll('.ffd-link[data-ff-url]')],dividers=[...document.querySelectorAll('.ffd-divider')];
  const workspaceDefault=<?php echo wp_json_encode($workspaceLabel); ?>,pingUrl=<?php echo wp_json_encode($pingUrl); ?>,activityUrl=<?php echo wp_json_encode($activityUrl); ?>,ajaxUrl=<?php echo wp_json_encode($ajaxUrl); ?>,loginUrl=<?php echo wp_json_encode($loginUrl); ?>,loginPath=<?php echo wp_json_encode($loginPath); ?>,pluginVersion=<?php echo wp_json_encode($version); ?>,desktopRelease=<?php echo wp_json_encode($desktopRelease); ?>,permissionStamp=<?php echo wp_json_encode($permissionStamp); ?>,maxUploadBytes=<?php echo (int)$maxUpload; ?>,bridgeSchema=<?php echo (int)$bridgeSchema; ?>,logoUrl=<?php echo wp_json_encode($logo); ?>,userKey=(app.dataset.user||'0'),profileKey=(app.dataset.profile||'user');
  const collapseKey='fieldflow.desktop.sidebar.'+userKey,lastKey='fieldflow.desktop.last.'+userKey+'.'+profileKey,recentKey='fieldflow.desktop.recent.'+userKey+'.'+profileKey,notifyKey='fieldflow.desktop.notifications.'+userKey,lastEventKey='fieldflow.desktop.activity.last.'+userKey;
  const nativeHost=!!(window.chrome&&window.chrome.webview&&typeof window.chrome.webview.postMessage==='function');
  let technicalMode=false,toastTimer=null,commandSelected=0,dragDepth=0,connectionState='',notificationsEnabled=false,activityBusy=false;
  function showToast(message){if(!toast)return;toast.textContent=String(message||'');toast.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.remove('show'),2600);}
  function safeWebUrl(raw,allowExternal=false){try{const u=new URL(String(raw||''),location.origin);const protocol=(u.protocol||'').toLowerCase();if(protocol!=='http:'&&protocol!=='https:')return null;if(!allowExternal&&u.origin!==location.origin)return null;return u;}catch(e){return null;}}
  function safeFrameUrl(raw){const u=safeWebUrl(raw,false);return u?u.toString():'';}
  function sameLoginPath(path){const a=String(path||'').replace(/\/+$/,'')||'/';const b=String(loginPath||'/login/').replace(/\/+$/,'')||'/';return a===b;}
  function bridgeSend(type,payload){const message={source:'fieldflow.desktop',schema:bridgeSchema,type:String(type||''),version:pluginVersion||'',release:desktopRelease||'',user_id:Number(userKey||0),profile:profileKey||'user',payload:payload&&typeof payload==='object'?payload:{}};try{window.dispatchEvent(new CustomEvent('fieldflow:desktop-bridge',{detail:message}));}catch(e){}if(nativeHost){try{window.chrome.webview.postMessage(message);return true;}catch(e){}}return false;}
  function currentFrameUrl(){try{return frame.contentWindow.location.href||frame.src||'';}catch(e){return frame.src||'';}}
  function currentModulePayload(extra){return Object.assign({key:(links.find(x=>x.classList.contains('active'))?.dataset.ffKey||''),label:title.textContent||'FieldFlow',url:currentFrameUrl(),technical:technicalMode===true},extra||{});}
  function navigateFrame(raw){const url=safeFrameUrl(raw);if(!url){showToast('Navegação bloqueada por segurança.');bridgeSend('bridge.rejected',{reason:'unsafe_navigation'});return false;}loading.hidden=false;frame.src=url;return true;}
  window.FieldFlowDesktopBridge={schema:bridgeSchema,version:pluginVersion||'',release:desktopRelease||'',native:nativeHost,send:bridgeSend,current:()=>currentModulePayload(),navigate:navigateFrame,reload:()=>{try{frame.contentWindow.location.reload();}catch(e){frame.src=frame.src;}}};
  function readRecent(){try{const rows=JSON.parse(localStorage.getItem(recentKey)||'[]');return Array.isArray(rows)?rows:[];}catch(e){return [];}}
  function saveRecent(btn){if(!btn||btn.dataset.ffTechnical==='1')return;const key=btn.dataset.ffKey||'',label=btn.dataset.ffLabel||'FieldFlow',url=btn.dataset.ffUrl||'';if(!key||!url)return;let rows=readRecent().filter(x=>x&&x.key!==key);rows.unshift({key,label,url,time:Date.now()});rows=rows.slice(0,8);try{localStorage.setItem(recentKey,JSON.stringify(rows));localStorage.setItem(lastKey,key);}catch(e){}renderRecent();}
  function renderRecent(){if(!recentList||!recentCount)return;const rows=readRecent();recentCount.textContent=String(rows.length);recentList.innerHTML='';if(!rows.length){recentList.innerHTML='<div class="ffd-command-empty">Ainda n\u00e3o existem m\u00f3dulos recentes.</div>';return;}rows.forEach(row=>{const b=document.createElement('button');b.type='button';b.className='ffd-recent-item';const d=row.time?new Date(row.time):null;b.innerHTML='<strong></strong><span></span>';b.querySelector('strong').textContent=row.label||'FieldFlow';b.querySelector('span').textContent=d?('Aberto '+d.toLocaleString('pt-PT',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})):'Recente';b.addEventListener('click',()=>{const match=links.find(x=>x.dataset.ffKey===row.key);if(match)openModule(match,true);recentPanel.classList.remove('open');});recentList.appendChild(b);});}
  function setActive(btn){links.forEach(x=>x.classList.toggle('active',x===btn));if(!btn)return;technicalMode=btn.dataset.ffTechnical==='1';title.textContent=btn.dataset.ffLabel||'FieldFlow';workspace.textContent=technicalMode?'Administra\u00e7\u00e3o t\u00e9cnica':workspaceDefault;bridgeSend('module.selected',currentModulePayload({key:btn.dataset.ffKey||'',label:btn.dataset.ffLabel||'FieldFlow',url:btn.dataset.ffUrl||''}));}
  function openModule(btn,remember=true){if(!btn)return;const url=safeFrameUrl(btn.dataset.ffUrl||'');if(!url){showToast('Módulo bloqueado por segurança.');return;}setActive(btn);if(remember)saveRecent(btn);loading.hidden=false;frame.src=url;}
  function openUrl(url,key,technical,label){const safe=safeFrameUrl(url);if(!safe){showToast('Destino bloqueado por segurança.');return;}const match=links.find(x=>x.dataset.ffKey===key);if(match)setActive(match);else{technicalMode=technical===true;title.textContent=label||'FieldFlow';workspace.textContent=technicalMode?'Administra\u00e7\u00e3o t\u00e9cnica':workspaceDefault;}if(match&&!technical)saveRecent(match);loading.hidden=false;frame.src=safe;}
  function isTicketedExportUrl(u){try{if(!u||u.origin!==location.origin||!/\/admin-post\.php$/i.test(u.pathname||''))return false;const action=String(u.searchParams.get('action')||'');return action==='routespro_export_dashboard'||action==='routespro_export_routes';}catch(e){return false;}}
  async function createDesktopDashboardExportTicket(u){
    const body=new URLSearchParams(u.search||'');
    const sourceAction=String(u.searchParams.get('action')||'');body.set('action',sourceAction==='routespro_export_routes'?'routespro_create_route_export_ticket':'routespro_create_dashboard_export_ticket');
    const response=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:body.toString()});
    const text=await response.text();
    let data=null;try{data=JSON.parse(text);}catch(e){}
    if(!response.ok||!data||data.success!==true||!data.data||!data.data.url){const message=data&&data.data&&data.data.message?String(data.data.message):('HTTP '+response.status);throw new Error(message);}
    const ticketUrl=safeWebUrl(String(data.data.url||''),false);
    if(!ticketUrl)throw new Error('Ticket de exportação inválido.');
    return {url:ticketUrl.toString(),filename:String(data.data.filename||''),format:String(data.data.format||''),expires:Number(data.data.expires||0)};
  }
  function sendNativeDownload(url,filename,extra){const payload=Object.assign({url:String(url||''),filename:String(filename||''),module:currentModulePayload()},extra||{});const sent=bridgeSend('download.request',payload);if(sent)showToast('Download enviado para o Windows.');return sent;}
  function bindFrameInteractions(doc){if(!doc||!doc.documentElement||doc.documentElement.dataset.ffDesktopBound==='1')return;doc.documentElement.dataset.ffDesktopBound='1';doc.addEventListener('click',function(e){const a=e.target.closest&&e.target.closest('a[href]');if(!a)return;const raw=a.getAttribute('href')||'';if(!raw||raw[0]==='#'||/^javascript:/i.test(raw))return;try{const u=new URL(a.href,frame.contentWindow.location.href);const protocol=(u.protocol||'').toLowerCase();if(protocol==='mailto:'||protocol==='tel:'){if(nativeHost){e.preventDefault();bridgeSend('external.open',{url:u.toString(),kind:protocol.slice(0,-1)});}return;}if(protocol!=='http:'&&protocol!=='https:'){e.preventDefault();showToast('Protocolo de ligação bloqueado por segurança.');bridgeSend('bridge.rejected',{reason:'unsafe_protocol',protocol:protocol});return;}const isDownload=a.hasAttribute('download')||/\.(csv|xlsx|xls|xlsm|pdf|zip|doc|docx|ppt|pptx|jpg|jpeg|png|webp)(?:$|\?)/i.test(u.pathname+u.search)||/(?:download|export)/i.test(u.search);if(isDownload){const filename=a.getAttribute('download')||u.pathname.split('/').pop()||'';if(nativeHost){e.preventDefault();if(isTicketedExportUrl(u)){showToast('A preparar a exportação...');createDesktopDashboardExportTicket(u).then(function(ticket){sendNativeDownload(ticket.url,ticket.filename||filename,{source:String(u.searchParams.get('action')||'')==='routespro_export_routes'?'route_export_ticket':'analytics_export_ticket',format:ticket.format||'',ticket:true,expires:ticket.expires||0});}).catch(function(err){showToast('Não foi possível preparar a exportação.');bridgeSend('download.failed',{url:u.toString(),filename:filename,error:String(err&&err.message||err||'ticket_failed'),module:currentModulePayload()});});}else{sendNativeDownload(u.toString(),filename);} }else{bridgeSend('download.detected',{url:u.toString(),filename:filename,module:currentModulePayload()});showToast('Download iniciado.');}return;}if(u.origin!==location.origin){e.preventDefault();if(!bridgeSend('external.open',{url:u.toString(),kind:'web'})){window.open(u.toString(),'_blank','noopener,noreferrer');}showToast('Link externo aberto no browser.');}}catch(err){}},true);}

  function cleanAdminFrame(){try{const doc=frame.contentDocument;if(!doc)return;bindFrameInteractions(doc);if(technicalMode)return;let style=doc.getElementById('fieldflow-desktop-parent-frame-style');if(!style){style=doc.createElement('style');style.id='fieldflow-desktop-parent-frame-style';style.textContent='html.wp-toolbar{padding-top:0!important}body{overflow-x:auto!important}#wpadminbar,#adminmenumain,#adminmenuwrap,#adminmenuback{display:none!important}#wpcontent,#wpfooter{margin-left:0!important}#wpcontent{padding-left:0!important;min-width:0!important}#wpbody{padding-top:0!important;min-width:0!important}#wpbody-content{min-width:0!important;box-sizing:border-box!important}.wrap{margin:18px 22px 0!important;max-width:none!important;min-width:0!important}.wrap>h1,.wrap>h2{max-width:100%!important}';(doc.head||doc.documentElement).appendChild(style);}doc.documentElement.classList.remove('wp-toolbar');['wpadminbar','adminmenumain','adminmenuwrap','adminmenuback'].forEach(id=>{const el=doc.getElementById(id);if(el)el.style.setProperty('display','none','important');});const content=doc.getElementById('wpcontent');if(content){content.style.setProperty('margin-left','0','important');content.style.setProperty('padding-left','0','important');}const footer=doc.getElementById('wpfooter');if(footer)footer.style.setProperty('margin-left','0','important');doc.querySelectorAll('a[href]').forEach(a=>{try{if(a.target==='_blank'||a.hasAttribute('download'))return;const u=new URL(a.href,frame.contentWindow.location.href);if(u.origin!==location.origin||u.pathname.indexOf('/wp-admin/')===-1||/admin-(post|ajax)\.php$/i.test(u.pathname))return;u.searchParams.set('ff_desktop_frame','1');a.href=u.toString();}catch(e){}});doc.querySelectorAll('form[method="get"],form:not([method])').forEach(form=>{try{const u=new URL(form.action||frame.contentWindow.location.href,frame.contentWindow.location.href);if(u.origin!==location.origin||u.pathname.indexOf('/wp-admin/')===-1)return;if(!form.querySelector('input[name="ff_desktop_frame"]')){const input=doc.createElement('input');input.type='hidden';input.name='ff_desktop_frame';input.value='1';form.appendChild(input);}}catch(e){}});}catch(e){}}
  function updateActiveFromFrame(){try{const u=new URL(frame.contentWindow.location.href,location.origin),currentPage=u.searchParams.get('page')||'';if(technicalMode&&u.searchParams.get('ff_desktop_frame')!=='1')return;const match=links.find(btn=>{try{const b=new URL(btn.dataset.ffUrl,location.origin),bp=b.searchParams.get('page')||'';if(bp&&currentPage)return bp===currentPage;return b.pathname===u.pathname&&b.searchParams.get('ff_desktop_frame')===u.searchParams.get('ff_desktop_frame');}catch(e){return false;}});if(match)setActive(match);}catch(e){}}
  function setOfflineLayer(state,label){if(!offlineLayer)return;const show=state==='offline';offlineLayer.hidden=!show;if(offlineText&&show)offlineText.textContent=label==='Offline'?'Verifica a ligação à Internet. O Desktop volta a ligar automaticamente quando a rede regressar.':'Não foi possível chegar ao FieldFlow. Tenta novamente dentro de alguns segundos.';}
  function setConnection(state,label){if(!connection)return;connection.classList.remove('offline','checking','server');if(state==='offline')connection.classList.add('offline');if(state==='server')connection.classList.add('server');if(state==='checking')connection.classList.add('checking');const text=connection.querySelector('span');if(text)text.textContent=label;setOfflineLayer(state,label);if(connectionState!==state){connectionState=state;bridgeSend('network.status',{state:state,label:label,online:navigator.onLine});}}
  function goToPublicLogin(){if(!loginUrl)return;try{window.location.replace(loginUrl);}catch(e){window.location.href=loginUrl;}}
  async function heartbeat(){if(!navigator.onLine){setConnection('offline','Offline');return false;}setConnection('checking','A verificar');const controller='AbortController' in window?new AbortController():null;const timer=controller?setTimeout(()=>controller.abort(),7000):null;try{const res=await fetch(pingUrl+(pingUrl.includes('?')?'&':'?')+'_ffts='+Date.now(),{credentials:'same-origin',cache:'no-store',signal:controller?controller.signal:undefined});if(timer)clearTimeout(timer);if(res.status===401){setConnection('offline','Sess\u00e3o');goToPublicLogin();return false;}if(!res.ok)throw new Error('server');const data=await res.json();if(!data||data.ok!==true)throw new Error('payload');if(Number(data.user_id||0)!==Number(userKey||0)){location.reload();return false;}if(String(data.profile_key||'restricted')==='restricted'){location.reload();return false;}if(data.permission_stamp&&String(data.permission_stamp)!==String(permissionStamp)){showToast('Permissões atualizadas. A recarregar o Desktop...');setTimeout(()=>location.reload(),450);return false;}if(data.version&&pluginVersion&&String(data.version)!==String(pluginVersion)){showToast('Nova versão FieldFlow detetada. A atualizar...');setTimeout(()=>location.reload(),450);return false;}setConnection('online','Online');return true;}catch(e){if(timer)clearTimeout(timer);setConnection(navigator.onLine?'server':'offline',navigator.onLine?'Servidor':'Offline');return false;}}
  function updateNotificationButton(count){if(!notifyBtn)return;notifyBtn.classList.remove('is-active','is-denied');let enabled=notificationsEnabled;if(!nativeHost&&'Notification' in window){if(Notification.permission==='denied')notifyBtn.classList.add('is-denied');if(Notification.permission!=='granted')enabled=false;}if(enabled)notifyBtn.classList.add('is-active');notifyBtn.title=enabled?'Desativar notificacoes FieldFlow':'Ativar notificacoes FieldFlow';const n=Math.max(0,Number(count||0));if(notifyCount){notifyCount.textContent=String(Math.min(n,99));notifyCount.hidden=n<1;}}
  async function requestNotifications(){if(notificationsEnabled){notificationsEnabled=false;try{localStorage.setItem(notifyKey,'0');}catch(e){}updateNotificationButton(0);bridgeSend('notification.subscription',{enabled:false});showToast('Notificacoes do FieldFlow desativadas.');return;}if(nativeHost){notificationsEnabled=true;try{localStorage.setItem(notifyKey,'1');}catch(e){}updateNotificationButton(0);bridgeSend('notification.permission.request',{});bridgeSend('notification.subscription',{enabled:true});showToast('Notificacoes Windows ativadas para o FieldFlow.');await pollActivity(true);return;}if(!('Notification' in window)){showToast('Este browser nao suporta notificacoes do sistema.');return;}let permission=Notification.permission;if(permission!=='granted'){try{permission=await Notification.requestPermission();}catch(e){permission='denied';}}notificationsEnabled=permission==='granted';try{localStorage.setItem(notifyKey,notificationsEnabled?'1':'0');}catch(e){}updateNotificationButton(0);bridgeSend('notification.subscription',{enabled:notificationsEnabled,permission:permission});if(notificationsEnabled){showToast('Notificacoes Windows ativadas.');await pollActivity(true);}else{showToast('Permissao de notificacoes nao concedida.');}}
  function showSystemNotification(event){if(!event||!notificationsEnabled)return;const payload={id:Number(event.id||0),title:String(event.title||'FieldFlow'),body:String(event.body||''),url:String(event.url||''),type:String(event.type||'event'),sender:String(event.sender||'')};if(nativeHost){bridgeSend('notification.show',payload);return;}if(!('Notification' in window)||Notification.permission!=='granted')return;try{const note=new Notification(payload.title,{body:payload.body,tag:'fieldflow-'+payload.type+'-'+payload.id,icon:logoUrl||undefined,renotify:false});note.onclick=function(){try{window.focus();}catch(e){}if(payload.url)openUrl(payload.url,'',false,payload.title);note.close();};}catch(e){}}
  async function pollActivity(initializeOnly){if(activityBusy||!navigator.onLine)return;activityBusy=true;try{const res=await fetch(activityUrl+(activityUrl.includes('?')?'&':'?')+'_ffts='+Date.now(),{credentials:'same-origin',cache:'no-store'});if(res.status===401){goToPublicLogin();return;}if(!res.ok)throw new Error('activity');const data=await res.json();if(!data||!data.ok)return;const events=Array.isArray(data.events)?data.events:[];const maxId=events.reduce((m,e)=>Math.max(m,Number(e&&e.id||0)),0);let lastId=0;try{lastId=Number(localStorage.getItem(lastEventKey)||0);}catch(e){}if(lastId<=0||initializeOnly){if(maxId>0){try{localStorage.setItem(lastEventKey,String(maxId));}catch(e){}}}else if(maxId>lastId){const fresh=events.filter(e=>Number(e&&e.id||0)>lastId).sort((a,b)=>Number(a.id||0)-Number(b.id||0)).slice(-4);fresh.forEach(showSystemNotification);try{localStorage.setItem(lastEventKey,String(maxId));}catch(e){}}const pending=Number(data.summary&&data.summary.incoming_new||0);updateNotificationButton(pending);bridgeSend('activity.snapshot',{summary:data.summary||{},events:events.slice(0,10),server_time:data.server_time||''});}catch(e){}finally{activityBusy=false;}}
  async function copyCurrentLink(){const text=currentFrameUrl();if(!text)return;if(nativeHost){bridgeSend('clipboard.write',{text:text,kind:'module-link'});showToast('Link enviado para a area de transferencia do Windows.');return;}try{await navigator.clipboard.writeText(text);showToast('Link do modulo copiado.');bridgeSend('clipboard.write.fallback',{ok:true,text:text});}catch(e){try{const ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();document.execCommand('copy');ta.remove();showToast('Link do modulo copiado.');}catch(err){showToast('Nao foi possivel copiar o link.');}}}
  function printCurrent(){const payload=currentModulePayload();if(bridgeSend('print.request',payload)){showToast('Pedido de impressao enviado ao Windows.');return;}try{frame.contentWindow.focus();frame.contentWindow.print();}catch(e){window.print();}}
  function filterCommands(){const q=(commandInput.value||'').trim().toLocaleLowerCase('pt-PT');let visible=0;commandItems.forEach(item=>{const hay=((item.dataset.commandLabel||'')+' '+(item.dataset.commandMeta||'')).toLocaleLowerCase('pt-PT');item.hidden=!!q&&!hay.includes(q);item.classList.remove('is-selected');if(!item.hidden)visible++;});commandEmpty.hidden=visible>0;commandSelected=0;selectCommand(0);}
  function visibleCommands(){return commandItems.filter(x=>!x.hidden);}
  function selectCommand(index){const visible=visibleCommands();if(!visible.length)return;commandSelected=Math.max(0,Math.min(index,visible.length-1));visible.forEach((x,i)=>x.classList.toggle('is-selected',i===commandSelected));visible[commandSelected].scrollIntoView({block:'nearest'});}
  function openCommandLayer(){commandLayer.hidden=false;commandInput.value='';filterCommands();setTimeout(()=>commandInput.focus(),10);}
  function closeCommandLayer(){commandLayer.hidden=true;commandInput.value='';}
  function runCommand(item){if(!item)return;closeCommandLayer();openUrl(item.dataset.commandUrl||'',item.dataset.commandKey||'',item.dataset.commandTechnical==='1',item.dataset.commandLabel||'FieldFlow');}
  links.forEach(btn=>btn.addEventListener('click',()=>openModule(btn,true)));
  frame.addEventListener('load',()=>{try{const u=new URL(frame.contentWindow.location.href,location.origin);if(u.origin===location.origin&&(/\/wp-login\.php$/i.test(u.pathname)||sameLoginPath(u.pathname))){goToPublicLogin();return;}}catch(e){}loading.hidden=true;updateActiveFromFrame();cleanAdminFrame();setTimeout(cleanAdminFrame,120);setTimeout(cleanAdminFrame,600);bridgeSend('module.loaded',currentModulePayload());});
  document.getElementById('ffd-reload').addEventListener('click',()=>{loading.hidden=false;try{frame.contentWindow.location.reload();}catch(e){frame.src=frame.src;}});
  document.getElementById('ffd-back').addEventListener('click',()=>{try{frame.contentWindow.history.back();}catch(e){}});
  document.getElementById('ffd-forward').addEventListener('click',()=>{try{frame.contentWindow.history.forward();}catch(e){}});
  document.getElementById('ffd-copy-link').addEventListener('click',copyCurrentLink);
  document.getElementById('ffd-print').addEventListener('click',printCurrent);
  if(notifyBtn)notifyBtn.addEventListener('click',requestNotifications);
  document.getElementById('ffd-collapse').addEventListener('click',()=>{app.classList.toggle('is-collapsed');try{localStorage.setItem(collapseKey,app.classList.contains('is-collapsed')?'1':'0');}catch(e){}});
  document.getElementById('ffd-command-open').addEventListener('click',openCommandLayer);
  document.getElementById('ffd-recent-open').addEventListener('click',()=>recentPanel.classList.add('open'));
  document.getElementById('ffd-recent-close').addEventListener('click',()=>recentPanel.classList.remove('open'));
  document.getElementById('ffd-about-open').addEventListener('click',()=>{if(aboutHost)aboutHost.textContent=nativeHost?'Windows WebView2 nativo':'Edge / browser app';aboutLayer.hidden=false;});
  document.getElementById('ffd-about-close').addEventListener('click',()=>aboutLayer.hidden=true);
  aboutLayer.addEventListener('click',e=>{if(e.target===aboutLayer)aboutLayer.hidden=true;});
  if(offlineRetry)offlineRetry.addEventListener('click',async()=>{const ok=await heartbeat();if(ok){loading.hidden=false;try{frame.contentWindow.location.reload();}catch(e){frame.src=frame.src;}}});
  commandLayer.addEventListener('click',e=>{if(e.target===commandLayer)closeCommandLayer();});
  commandInput.addEventListener('input',filterCommands);
  commandInput.addEventListener('keydown',e=>{if(e.key==='ArrowDown'){e.preventDefault();selectCommand(commandSelected+1);}else if(e.key==='ArrowUp'){e.preventDefault();selectCommand(commandSelected-1);}else if(e.key==='Enter'){e.preventDefault();runCommand(visibleCommands()[commandSelected]);}else if(e.key==='Escape'){e.preventDefault();closeCommandLayer();}});
  commandItems.forEach(item=>item.addEventListener('click',()=>runCommand(item)));
  document.addEventListener('keydown',e=>{const key=String(e.key||'').toLowerCase();if((e.ctrlKey||e.metaKey)&&key==='k'){e.preventDefault();openCommandLayer();}else if((e.ctrlKey||e.metaKey)&&key==='p'){e.preventDefault();printCurrent();}else if((e.ctrlKey||e.metaKey)&&e.shiftKey&&key==='c'){e.preventDefault();copyCurrentLink();}else if((e.ctrlKey||e.metaKey)&&key==='r'){e.preventDefault();loading.hidden=false;try{frame.contentWindow.location.reload();}catch(err){frame.src=frame.src;}}else if(e.altKey&&e.key==='ArrowLeft'){e.preventDefault();try{frame.contentWindow.history.back();}catch(err){}}else if(e.altKey&&e.key==='ArrowRight'){e.preventDefault();try{frame.contentWindow.history.forward();}catch(err){}}else if(e.key==='Escape'){closeCommandLayer();recentPanel.classList.remove('open');aboutLayer.hidden=true;}});
  if(search){search.addEventListener('input',()=>{const q=(search.value||'').trim().toLocaleLowerCase('pt-PT');links.forEach(btn=>{btn.hidden=!!q&&!(btn.dataset.ffLabel||'').toLocaleLowerCase('pt-PT').includes(q);});dividers.forEach(div=>{let n=div.nextElementSibling,any=false;while(n&&!n.classList.contains('ffd-divider')){if(n.classList.contains('ffd-link')&&!n.hidden){any=true;break;}n=n.nextElementSibling;}div.hidden=!any;});});search.addEventListener('keydown',e=>{if(e.key==='Enter'){const first=links.find(x=>!x.hidden);if(first){e.preventDefault();openModule(first,true);search.value='';search.dispatchEvent(new Event('input'));}}});}
  function inputAcceptsFile(input,file){const accept=String(input.getAttribute('accept')||'').trim();if(!accept)return true;const name=String(file.name||'').toLowerCase(),type=String(file.type||'').toLowerCase();return accept.split(',').map(x=>x.trim().toLowerCase()).filter(Boolean).some(rule=>{if(rule.startsWith('.'))return name.endsWith(rule);if(rule.endsWith('/*'))return type.startsWith(rule.slice(0,-1));return type===rule;});}
  if(stage){stage.addEventListener('dragenter',e=>{if(e.dataTransfer&&Array.from(e.dataTransfer.types||[]).includes('Files')){e.preventDefault();dragDepth++;drop.hidden=false;}});stage.addEventListener('dragover',e=>{if(e.dataTransfer&&Array.from(e.dataTransfer.types||[]).includes('Files')){e.preventDefault();e.dataTransfer.dropEffect='copy';drop.hidden=false;}});stage.addEventListener('dragleave',()=>{dragDepth=Math.max(0,dragDepth-1);if(!dragDepth)drop.hidden=true;});stage.addEventListener('drop',e=>{e.preventDefault();dragDepth=0;drop.hidden=true;const files=Array.from(e.dataTransfer?.files||[]).slice(0,20);if(!files.length)return;try{const doc=frame.contentDocument;if(!doc)throw new Error('frame');const inputs=[...doc.querySelectorAll('input[type="file"]')].filter(x=>!x.disabled&&x.offsetParent!==null);if(!inputs.length){showToast('Abre primeiro uma \u00e1rea com campo de upload.');return;}const input=inputs[0];let picked=(input.multiple?files:[files[0]]).filter(file=>inputAcceptsFile(input,file));const oversize=maxUploadBytes>0?picked.filter(file=>Number(file.size||0)>maxUploadBytes):[];if(oversize.length){showToast('Há ficheiros acima do limite de upload do servidor.');picked=picked.filter(file=>Number(file.size||0)<=maxUploadBytes);}if(!picked.length){showToast('Nenhum ficheiro compatível com este campo de upload.');return;}const dt=new DataTransfer();picked.forEach(file=>dt.items.add(file));input.files=dt.files;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));showToast(picked.length===1?'Ficheiro preparado para upload.':picked.length+' ficheiros preparados para upload.');}catch(err){showToast('N\u00e3o foi poss\u00edvel anexar o ficheiro nesta \u00e1rea.');}});}
  window.addEventListener('online',()=>{heartbeat().then(ok=>{if(ok){try{frame.contentWindow.location.reload();}catch(e){}}});pollActivity(false);});window.addEventListener('offline',()=>setConnection('offline','Offline'));setInterval(()=>heartbeat(),60000);setInterval(()=>pollActivity(false),60000);heartbeat();
  try{if(localStorage.getItem(collapseKey)==='1')app.classList.add('is-collapsed');notificationsEnabled=localStorage.getItem(notifyKey)==='1';}catch(e){}
  if(!nativeHost&&'Notification' in window&&Notification.permission!=='granted')notificationsEnabled=false;
  updateNotificationButton(0);
  if(nativeHost&&window.chrome.webview&&typeof window.chrome.webview.addEventListener==='function'){window.chrome.webview.addEventListener('message',function(ev){let data=ev.data;try{if(typeof data==='string')data=JSON.parse(data);}catch(e){}if(!data||typeof data!=='object')return;const type=String(data.type||'');const payload=data.payload&&typeof data.payload==='object'?data.payload:{};if(type==='navigate'&&payload.url){navigateFrame(payload.url);}else if(type==='reload'){loading.hidden=false;try{frame.contentWindow.location.reload();}catch(e){frame.src=frame.src;}}else if(type==='notification.permission'){notificationsEnabled=payload.granted!==false;try{localStorage.setItem(notifyKey,notificationsEnabled?'1':'0');}catch(e){}updateNotificationButton(0);}else if(type==='download.completed'){showToast(payload.filename?'Download concluido: '+payload.filename:'Download concluido.');}else if(type==='download.failed'){showToast('Falha no download do Windows.');}else if(type==='clipboard.written'){showToast('Copiado para a area de transferencia.');}});}
  bridgeSend('bridge.ready',{native:nativeHost,capabilities:['navigation','downloads','print','clipboard','notifications','network','activity','drag-drop'],workspace:workspaceDefault,release:desktopRelease,bridge_schema:bridgeSchema,max_upload_bytes:maxUploadBytes,same_origin_navigation:true});
  renderRecent();
  pollActivity(true);
  let startBtn=null;try{const saved=localStorage.getItem(lastKey)||'';startBtn=links.find(x=>x.dataset.ffKey===saved&&x.dataset.ffTechnical!=='1')||null;}catch(e){}if(!startBtn)startBtn=links.find(x=>x.classList.contains('active'))||links.find(x=>x.dataset.ffTechnical!=='1')||links[0];if(startBtn)openModule(startBtn,false);else{loading.hidden=false;frame.src=frame.dataset.initial||'about:blank';}
  window.addEventListener('message',ev=>{if(ev.origin!==location.origin||!ev.data)return;if(ev.data.type==='fieldflow.desktop.title'&&ev.data.title)title.textContent=String(ev.data.title).slice(0,80);if(ev.data.type==='fieldflow.desktop.open'&&ev.data.key){const btn=links.find(x=>x.dataset.ffKey===String(ev.data.key));if(btn)openModule(btn,true);}});
})();
</script>
</body>
</html>
        <?php
        exit;
    }

    private static function renderDenied(): void {
        nocache_headers();
        status_header(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FieldFlow Desktop</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;font-family:Segoe UI,Arial,sans-serif;color:#0f172a}.card{max-width:560px;padding:30px;border-radius:22px;background:#fff;border:1px solid #e2e8f0;box-shadow:0 24px 70px rgba(15,23,42,.12)}h1{margin:0 0 10px}p{color:#64748b;line-height:1.6}</style></head><body><main class="card"><h1>Acesso indisponível</h1><p>Este utilizador não tem permissões para abrir o FieldFlow Desktop. Contacta um administrador FieldFlow.</p></main></body></html>';
        exit;
    }
}
