<?php
namespace RoutesPro\SaaS;

if (!defined('ABSPATH')) exit;

class CapabilityCatalog {
    public static function all(): array {
        return [
            'portal.operational' => ['label'=>'Portal operacional','group'=>'Portais'],
            'portal.client' => ['label'=>'Portal de cliente','group'=>'Portais'],
            'billing.view' => ['label'=>'Consultar plano e faturação','group'=>'Portais'],
            'billing.checkout' => ['label'=>'Alterar plano por checkout','group'=>'Portais'],
            'routes.view' => ['label'=>'Consultar rotas','group'=>'Operação'],
            'routes.create' => ['label'=>'Criar e editar rotas','group'=>'Operação'],
            'routes.optimise' => ['label'=>'Otimização de rotas','group'=>'Operação'],
            'forms.execute' => ['label'=>'Executar formulários','group'=>'Execução'],
            'forms.manage' => ['label'=>'Gerir formulários','group'=>'Execução'],
            'reporting.view' => ['label'=>'Consultar reportes','group'=>'Execução'],
            'reporting.submit' => ['label'=>'Submeter reportes','group'=>'Execução'],
            'media.upload' => ['label'=>'Carregar fotografias e ficheiros','group'=>'Execução'],
            'briefings.view' => ['label'=>'Consultar briefings','group'=>'Operação'],
            'briefings.manage' => ['label'=>'Gerir briefings','group'=>'Operação'],
            'implementations.view' => ['label'=>'Consultar implementações','group'=>'Operação'],
            'implementations.manage' => ['label'=>'Gerir implementações','group'=>'Operação'],
            'stocks.view' => ['label'=>'Consultar stocks','group'=>'Stocks e Cardex'],
            'stocks.manage' => ['label'=>'Gerir stocks','group'=>'Stocks e Cardex'],
            'cardex.manage' => ['label'=>'Gerir cardex','group'=>'Stocks e Cardex'],
            'mapping.manage' => ['label'=>'Centro de Mapping','group'=>'Dados'],
            'commercial.view' => ['label'=>'Consultar base comercial','group'=>'Dados'],
            'commercial.manage' => ['label'=>'Gerir base comercial','group'=>'Dados'],
            'campaign_pdvs.manage' => ['label'=>'Gerir campanhas PDV','group'=>'Dados'],
            'expenses.use' => ['label'=>'Despesas operacionais','group'=>'Operação'],
            'messages.use' => ['label'=>'Mensagens de equipa','group'=>'Colaboração'],
            'team.view' => ['label'=>'Consultar equipa','group'=>'Colaboração'],
            'academy.view' => ['label'=>'Academy','group'=>'Growth Hub'],
            'growth_hub.view' => ['label'=>'Growth Hub','group'=>'Growth Hub'],
            'analytics.basic' => ['label'=>'Analytics base','group'=>'Intelligence'],
            'analytics.advanced' => ['label'=>'Analytics avançado','group'=>'Intelligence'],
            'ai.use' => ['label'=>'Inteligência artificial','group'=>'Intelligence'],
            'alerts.use' => ['label'=>'Alertas operacionais','group'=>'Intelligence'],
            'reports.schedule' => ['label'=>'Relatórios automáticos','group'=>'Reporting'],
            'exports.csv' => ['label'=>'Exportação CSV','group'=>'Reporting'],
            'exports.excel' => ['label'=>'Exportação Excel','group'=>'Reporting'],
            'exports.pdf' => ['label'=>'Exportação PDF','group'=>'Reporting'],
            'mobile.webview' => ['label'=>'Aplicação WebView','group'=>'Mobile'],
            'mobile.offline' => ['label'=>'Modo offline','group'=>'Mobile'],
            'api.access' => ['label'=>'API externa','group'=>'Enterprise'],
            'webhooks.manage' => ['label'=>'Webhooks externos','group'=>'Enterprise'],
            'white_label' => ['label'=>'White-label','group'=>'Enterprise'],
            'audit.advanced' => ['label'=>'Auditoria avançada','group'=>'Enterprise'],
            'sso' => ['label'=>'SSO','group'=>'Enterprise'],
        ];
    }

    public static function keys(): array { return array_keys(self::all()); }

    public static function groups(): array {
        $out = [];
        foreach (self::all() as $key => $item) $out[(string)$item['group']][$key] = (string)$item['label'];
        return $out;
    }

    public static function shortcodeMap(): array {
        return [
            'my_daily_route'=>'routes.view','dashboard'=>'analytics.basic','route_change_form'=>'routes.view',
            'front_hub'=>'portal.operational','front_routes'=>'routes.view','front_commercial'=>'commercial.view',
            'app'=>'portal.operational','expenses'=>'expenses.use','briefings'=>'briefings.view',
            'client_briefings'=>'briefings.view','implementations'=>'implementations.view',
            'client_implementations'=>'implementations.view','stocks'=>'stocks.view','client_stocks'=>'stocks.view','shopping'=>'reporting.submit','shopping_results'=>'reporting.view',
            'route_today'=>'routes.view','discovery'=>'commercial.view','report_visit'=>'forms.execute',
            'route_form'=>'forms.execute','checkin'=>'routes.view','client_portal'=>'portal.client',
            'client_team_mail'=>'messages.use','team_inbox'=>'messages.use','performance_dashboard'=>'growth_hub.view',
            'academy'=>'academy.view','billing_portal'=>'billing.view',
        ];
    }

    public static function adminPageMap(): array {
        return [
            'routespro-routes'=>'routes.create','routespro-assignments-hub'=>'routes.create','routespro-assign'=>'routes.create',
            'routespro-briefings'=>'briefings.manage','routespro-implementations'=>'implementations.manage','routespro-stocks'=>'stocks.manage','routespro-shopping'=>'forms.manage',
            'routespro-mapping-center'=>'mapping.manage','routespro-commercial'=>'commercial.manage',
            'routespro-commercial-import'=>'commercial.manage','routespro-import'=>'commercial.manage','routespro-categories'=>'commercial.manage',
            'routespro-campaign-locations'=>'campaign_pdvs.manage','routespro-context-questions'=>'forms.manage',
            'routespro-forms'=>'forms.manage','routespro-form-edit'=>'forms.manage','routespro-form-bindings'=>'forms.manage',
            'routespro-product-cardex'=>'cardex.manage','routespro-form-submissions'=>'reporting.view','routespro-form-history'=>'reporting.view',
            'routespro-ai'=>'ai.use','routespro-performance'=>'growth_hub.view','routespro-expenses'=>'expenses.use',
            'routespro-mobile'=>'mobile.webview','routespro-pwa'=>'mobile.webview','routespro-campaign-branding'=>'white_label',
            'routespro-public-integrations'=>'api.access',
        ];
    }

    public static function restPatterns(): array {
        return [
            '#/routespro/v1/routes(?:/|$)#'=>'routes.view',
            '#/routespro/v1/optimi#'=>'routes.optimise',
            '#/routespro/v1/stops(?:/|$)#'=>'routes.view',
            '#/fieldflow/v1/briefings#'=>'briefings.view',
            '#/fieldflow/v1/implementations#'=>'implementations.view',
            '#/fieldflow/v1/stocks#'=>'stocks.view',
            '#/fieldflow/v1/shopping/results#'=>'reporting.view',
            '#/fieldflow/v1/shopping#'=>'reporting.submit',
            '#/fieldflow/v1/mapping#'=>'mapping.manage',
            '#/fieldflow-mobile/v1/offline#'=>'mobile.offline',
        ];
    }

    public static function featureMap(): array {
        return [
            'routes'=>'routes.view','reporting'=>'reporting.view','media_upload'=>'media.upload',
            'briefings'=>'briefings.view','implementations'=>'implementations.view','stocks'=>'stocks.view','shopping'=>'reporting.submit','shopping_results'=>'reporting.view',
            'expenses'=>'expenses.use','analytics'=>'analytics.basic','commercial_base'=>'commercial.view',
            'campaign_pdvs'=>'campaign_pdvs.manage','team'=>'team.view','messages'=>'messages.use',
            'academy'=>'academy.view','growth_hub'=>'growth_hub.view',
        ];
    }

    public static function limitCatalog(): array {
        return [
            'users_active'=>['label'=>'Utilizadores ativos','default'=>0],
            'locations_active'=>['label'=>'Lojas ativas','default'=>0],
            'routes_monthly'=>['label'=>'Rotas por mês','default'=>0],
            'submissions_monthly'=>['label'=>'Submissões por mês','default'=>0],
            'ai_requests_monthly'=>['label'=>'Pedidos de IA por mês','default'=>0],
            'exports_monthly'=>['label'=>'Exportações por mês','default'=>0],
            'scheduled_reports'=>['label'=>'Relatórios automáticos','default'=>0],
            'offline_devices'=>['label'=>'Dispositivos offline','default'=>0],
            'storage_mb'=>['label'=>'Armazenamento de evidências, MB','default'=>0],
        ];
    }
}
