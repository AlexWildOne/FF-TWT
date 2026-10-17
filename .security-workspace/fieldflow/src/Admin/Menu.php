<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\AdminPage;
use RoutesPro\Support\Request;

class Menu {
    public static function register() {
        add_menu_page(
            'FieldFlow',
            'FieldFlow',
            'routespro_manage',
            'routespro',
            [self::class,'render'],
            '',
            26
        );

        add_submenu_page('routespro', 'Clientes', 'Clientes', 'routespro_manage', 'routespro-clients', [Clients::class,'render']);
        add_submenu_page('routespro', 'Onboarding de Clientes', 'Onboarding de Clientes', 'routespro_manage', ClientOnboarding::SLUG, [ClientOnboarding::class,'render']);
        add_submenu_page('routespro', 'Subscrições e Capacidades', 'Subscrições', 'routespro_manage', Subscriptions::SLUG, [Subscriptions::class,'render']);
        add_submenu_page('routespro', 'Licenciamento & Tenants', 'Licenciamento & Tenants', 'routespro_manage', LicenseTenants::SLUG, [LicenseTenants::class,'render']);
        add_submenu_page('routespro', 'Release Manager', 'Release Manager', 'routespro_manage', ReleaseManager::SLUG, [ReleaseManager::class,'render']);
        add_submenu_page('routespro', 'Faturação e Pagamentos', 'Faturação', 'routespro_manage', Billing::SLUG, [Billing::class,'render']);
        add_submenu_page('routespro', 'Projetos', 'Projetos', 'routespro_manage', 'routespro-projects', [Projects::class,'render']);
        add_submenu_page('routespro', 'Rotas', 'Rotas', 'routespro_manage', 'routespro-routes', [Routes::class,'render']);
        add_submenu_page('routespro', 'FieldFlow Intelligence Center', 'Intelligence Center', 'routespro_manage', 'routespro-assignments-hub', [Assignments::class,'render_hub']);
        add_submenu_page('routespro', 'Growth Hub', 'Growth Hub', 'routespro_manage', 'routespro-performance', [Performance::class,'render']);
        add_submenu_page('routespro', 'Despesas de Operação', 'Despesas de Operação', 'routespro_manage', 'routespro-expenses', [Expenses::class,'render']);
        add_submenu_page('routespro', 'Briefings Operacionais', 'Briefings Operacionais', 'routespro_manage', 'routespro-briefings', [Briefings::class,'render']);
        add_submenu_page('routespro', 'Implementações de Materiais', 'Implementações de Materiais', 'routespro_manage', 'routespro-implementations', [Implementations::class,'render']);
        add_submenu_page('routespro', 'Stocks Operacionais', 'Stocks Operacionais', 'routespro_manage', 'routespro-stocks', [Stocks::class,'render']);
        add_submenu_page('routespro', 'Shopping, Verificação de Preços', 'Shopping e Preços', 'routespro_manage', 'routespro-shopping', [Shopping::class,'render']);
        add_submenu_page('routespro', 'Centro Universal de Mapping', 'Centro de Mapping', 'routespro_manage', MappingCenter::SLUG, [MappingCenter::class,'render']);
        add_submenu_page('routespro', 'Base Comercial', 'Base Comercial', 'routespro_manage', 'routespro-commercial', [Commercial::class,'render']);
        add_submenu_page('routespro', 'Lojas', 'Lojas', 'routespro_manage', Stores::SLUG, [Stores::class,'render']);
        add_submenu_page('routespro', 'Historico de Formularios', 'Historico Formulario', 'routespro_manage', 'routespro-form-history', [FormHistory::class,'render']);
        add_submenu_page('routespro', 'Importar PDVs', 'Importar PDVs', 'routespro_manage', 'routespro-commercial-import', [Commercial::class,'render_import']);
        add_submenu_page('routespro', 'Categorias', 'Categorias', 'routespro_manage', 'routespro-categories', [Categories::class,'render']);
        add_submenu_page('routespro', 'Campanhas PDVs', 'Campanhas PDVs', 'routespro_manage', 'routespro-campaign-locations', [CampaignLocations::class,'render']);
        add_submenu_page('routespro', 'Perguntas contextuais', 'Perguntas contextuais', 'routespro_manage', 'routespro-context-questions', [ContextQuestions::class,'render']);
        add_submenu_page('routespro', 'Formulários', 'Formulários', 'routespro_manage', 'routespro-forms', [Forms::class,'render']);
        add_submenu_page('routespro', 'Cardex e Catálogo', 'Cardex e Catálogo', 'routespro_manage', 'routespro-product-cardex', [ProductCardex::class,'render']);
        add_submenu_page('routespro', 'Novo formulário', 'Novo formulário', 'routespro_manage', 'routespro-form-edit', [Forms::class,'render_edit']);
        add_submenu_page('routespro', 'Submissões', 'Submissões', 'routespro_manage', 'routespro-form-submissions', [FormSubmissions::class,'render']);
        add_submenu_page('routespro', 'Formulários por contexto', 'Formulários por contexto', 'routespro_manage', 'routespro-form-bindings', [FormBindings::class,'render']);
        add_submenu_page(null, 'Operação & Atribuições', 'Operação & Atribuições', 'routespro_manage', 'routespro-assign', [Assignments::class,'render']);
        add_submenu_page(null, 'Editar submissão', 'Editar submissão', 'routespro_manage', 'routespro-form-submission-edit', [FormSubmissions::class,'render_edit']);
        add_submenu_page('routespro', 'Integrações', 'Integrações', 'routespro_manage', 'routespro-integrations', [Integrations::class,'render']);
        add_submenu_page('routespro', 'Destinos de Dados', 'Destinos de Dados', 'routespro_manage', DataDestinations::SLUG, [DataDestinations::class,'render']);
        add_submenu_page('routespro', 'API Pública e Webhooks', 'API Pública e Webhooks', 'routespro_manage', PublicIntegrations::SLUG, [PublicIntegrations::class,'render']);
        add_submenu_page('routespro', 'Configuração de Inteligência Artificial', 'Configuração de IA', 'routespro_manage', 'routespro-ai', [AIControlCenter::class,'render']);
        add_submenu_page('routespro', 'Privacidade e Governação', 'Privacidade e Governação', 'routespro_manage', PrivacyCenter::SLUG, [PrivacyCenter::class,'render']);
        add_submenu_page('routespro', 'Purga Operacional', 'Purga Operacional', 'routespro_manage', UserOperationalPurge::SLUG, [UserOperationalPurge::class,'render']);
        add_submenu_page('routespro', 'Importar Locais', 'Importar Locais', 'routespro_manage', 'routespro-import', [self::class,'render_import']);
        add_submenu_page('routespro', 'Mail Center', 'Mail Center', 'routespro_manage', 'routespro-emails', [Emails::class,'render']);
        add_submenu_page('routespro', 'E-mails enviados', 'E-mails enviados', 'routespro_manage', 'routespro-email-log', [Emails::class,'render_log']);
        add_submenu_page('routespro', 'Personalização', 'Personalização', 'routespro_manage', 'routespro-appearance', [Appearance::class,'render']);
        add_submenu_page('routespro', 'Aplicação Mobile', 'Aplicação Mobile', 'routespro_manage', MobileControlCenter::SLUG, [MobileControlCenter::class,'render']);
        add_submenu_page('routespro', 'Gestão de Dispositivos', 'Dispositivos', 'routespro_manage', Devices::SLUG, [Devices::class,'render']);
        add_submenu_page('routespro', 'Segurança e Observabilidade', 'Segurança e Observabilidade', 'routespro_manage', SecurityObservability::SLUG, [SecurityObservability::class,'render']);
        add_submenu_page('routespro', 'Centro de Processos', 'Centro de Processos', 'routespro_manage', ProcessCenter::SLUG, [ProcessCenter::class,'render']);
        add_submenu_page('routespro', 'PWA Web', 'PWA Web', 'routespro_manage', 'routespro-pwa', [PWASettingsPage::class,'render']);
        add_submenu_page('routespro', 'Temas por campanha', 'Temas por campanha', 'routespro_manage', 'routespro-campaign-branding', [CampaignBranding::class,'render']);
        add_submenu_page('routespro', 'Configurações', 'Configurações', 'routespro_manage', 'routespro-settings', [Settings::class,'render']);
        add_submenu_page('routespro', 'Saúde do sistema', 'Saúde do sistema', 'routespro_manage', 'routespro-system-health', [SystemHealth::class,'render']);
    }

    /**
     * Late integrity pass for critical governance/system tools.
     *
     * SaaS/entitlement filters run after the primary menu registration. These
     * pages are control-plane pages, not client-plan features, so a manager
     * must never lose their navigation entry because of grouping/filter order.
     */
    public static function ensureSystemTools(): void {
        if (!current_user_can('routespro_manage')) return;

        $tools = [
            ['Release Manager', 'Release Manager', ReleaseManager::SLUG, [ReleaseManager::class, 'render']],
            ['Privacidade e Governação', 'Privacidade e Governação', PrivacyCenter::SLUG, [PrivacyCenter::class, 'render']],
            ['Purga Operacional', 'Purga Operacional', UserOperationalPurge::SLUG, [UserOperationalPurge::class, 'render']],
            ['Segurança e Observabilidade', 'Segurança e Observabilidade', SecurityObservability::SLUG, [SecurityObservability::class, 'render']],
            ['Centro de Processos', 'Centro de Processos', ProcessCenter::SLUG, [ProcessCenter::class, 'render']],
            ['Configurações', 'Configurações', 'routespro-settings', [Settings::class, 'render']],
            ['Saúde do sistema', 'Saúde do sistema', 'routespro-system-health', [SystemHealth::class, 'render']],
        ];

        foreach ($tools as $tool) {
            [$pageTitle, $menuTitle, $slug, $callback] = $tool;
            if (self::submenuHasSlug((string)$slug)) continue;
            add_submenu_page('routespro', $pageTitle, $menuTitle, 'routespro_manage', $slug, $callback);
        }
    }

    private static function submenuHasSlug(string $slug): bool {
        global $submenu;
        foreach ((array)($submenu['routespro'] ?? []) as $row) {
            if ((string)($row[2] ?? '') === $slug) return true;
        }
        return false;
    }

    public static function render() {
        if (isset($_GET['ff_desktop_frame']) && (string)$_GET['ff_desktop_frame'] === '1') {
            self::render_dashboard();
            return;
        }
        AdminPage::open('FieldFlow', 'Resumo rápido de shortcodes e pontos de entrada do produto.');
        echo '<div class="routespro-card" style="margin-top:18px">';
        echo '<h2 style="margin-top:0">Shortcodes disponíveis</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Shortcode</th><th>Uso</th></tr></thead><tbody>';
        echo '<tr><td><code>[fieldflow_my_daily_route]</code></td><td>Mostra as rotas do utilizador no dia selecionado.</td></tr>';
        echo '<tr><td><code>[fieldflow_dashboard]</code></td><td>Dashboard operacional com métricas e visão geral.</td></tr>';
        echo '<tr><td><code>[fieldflow_route_change_form]</code></td><td>Formulário de pedidos de alteração de rota no front.</td></tr>';
        echo '<tr><td><code>[fieldflow_front_hub]</code></td><td>Hub front legado, agora alinhado com a app principal.</td></tr>';
        echo '<tr><td><code>[fieldflow_front_routes]</code></td><td>Fluxo front de rotas com descoberta de PDVs, fila de paragens e cálculo de distância/tempo.</td></tr>';
        echo '<tr><td><code>[fieldflow_front_commercial]</code></td><td>Base comercial no front com filtros, mapa, Google Places e gravação direta de PDVs.</td></tr>';
        echo '<tr><td><code>[fieldflow_app]</code></td><td>App principal mobile-first com rota, descoberta, reporte, base comercial, Academy, mensagens e despesas.</td></tr>';
        echo '<tr><td><code>[fieldflow_expenses]</code></td><td>Módulo pessoal de despesas operacionais, com QR fiscal, fotografia e histórico do utilizador autenticado.</td></tr>';
        echo '<tr><td><code>[fieldflow_shopping]</code></td><td>Verificação de preços Shopping por loja e merchandiser, com importação Excel e notificação automática ao cliente.</td></tr>';
        echo '<tr><td><code>[fieldflow_briefings]</code></td><td>Briefings semanais filtrados pelas lojas e campanhas atribuídas ao utilizador autenticado.</td></tr>';
        echo '<tr><td><code>[fieldflow_client_briefings]</code></td><td>Visão cliente dos briefings publicados, visualizações e confirmações de leitura.</td></tr>';
        echo '<tr><td><code>[fieldflow_implementations]</code></td><td>Implementações de materiais publicadas, filtradas pelas lojas atribuídas ao utilizador.</td></tr>';
        echo '<tr><td><code>[fieldflow_client_implementations]</code></td><td>Visão cliente das implementações de materiais por campanha, estado e loja.</td></tr>';
        echo '<tr><td><code>[fieldflow_route_today]</code></td><td>Vista focada na rota do dia para o utilizador autenticado.</td></tr>';
        echo '<tr><td><code>[fieldflow_discovery]</code></td><td>Descoberta de PDVs e construção de rota no front.</td></tr>';
        echo '<tr><td><code>[fieldflow_report_visit]</code></td><td>Widget rápido de reporte de visita para uma paragem específica.</td></tr>';
        echo '<tr><td><code>[fieldflow_route_form]</code></td><td>Resolve automaticamente o formulário activo por cliente, projeto, rota, paragem ou local.</td></tr>';
        echo '<tr><td><code>[fieldflow_checkin]</code></td><td>Widget ultra-rápido para check-in e início de visita.</td></tr>';
        echo '<tr><td><code>[fieldflow_client_portal]</code></td><td>Portal front focado no cliente para acompanhar campanhas, rotas, execução e analytics por campanha.</td></tr>';
        echo '<tr><td><code>[fieldflow_performance_dashboard]</code></td><td>Dashboard premium do Growth Hub por cliente e campanha.</td></tr>';
        echo '<tr><td><code>[fieldflow_academy]</code></td><td>Academy front para cursos práticos e missões de terreno.</td></tr>';
        echo '<tr><td><code>[fieldflow_client_team_mail]</code></td><td>Canal premium para o cliente contactar a equipa certa por campanha, com owners filtrados, tipologia da mensagem e histórico recente.</td></tr>';
        echo '</tbody></table>';
        echo '<p style="margin-top:12px;color:#64748b">Shortcodes front atualizados para suportar operação, descoberta comercial e experiência dinâmica fora do backoffice.</p>';
        $health = \RoutesPro\Support\SystemHealth::status();
        $label = $health['summary'] === 'ok' ? 'Sistema saudável' : ($health['summary'] === 'warning' ? 'Atenção necessária' : 'Intervenção crítica');
        $bg = $health['summary'] === 'ok' ? '#dcfce7' : ($health['summary'] === 'warning' ? '#fef3c7' : '#fee2e2');
        $fg = $health['summary'] === 'ok' ? '#166534' : ($health['summary'] === 'warning' ? '#92400e' : '#991b1b');
        echo '<div style="margin-top:16px;padding:16px;border-radius:16px;background:' . esc_attr($bg) . ';color:' . esc_attr($fg) . ';font-weight:700">Estado técnico do produto: ' . esc_html($label) . '.</div>';
        echo '</div>';
        AdminPage::close();
    }

    public static function render_dashboard() {
        if (!current_user_can('routespro_manage')) return;
        AdminPage::open('Dashboard');
        echo do_shortcode('[fieldflow_dashboard]');
        AdminPage::close();
    }

    public static function render_import() {
        if (!current_user_can('routespro_manage')) return;
        AdminPage::open('Importar Locais (legacy)', 'Fluxo legado de importação CSV, mantido por compatibilidade.');
        if (Request::verifyNonce('routespro_import_nonce', 'routespro_import')) {
            \RoutesPro\Import\CSVImporter::handle_upload();
        }
        ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('routespro_import', 'routespro_import_nonce'); ?>
            <p><input type="file" name="routespro_csv" accept=".csv" required /></p>
            <p>
              Cliente ID: <input type="number" name="client_id" required />
              Projeto ID: <input type="number" name="project_id" />
            </p>
            <p><button class="button button-primary">Importar</button></p>
        </form>
        <?php
        AdminPage::close();
    }
}
