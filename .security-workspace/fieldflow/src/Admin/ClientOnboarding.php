<?php
namespace RoutesPro\Admin;

use RoutesPro\Cloud\ClientOnboardingFoundation;
use RoutesPro\Cloud\ClientOnboardingCertification;
use RoutesPro\Cloud\Phase5StableRelease;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

final class ClientOnboarding {
    public const SLUG = 'routespro-client-onboarding';

    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_phase6a_export', [self::class, 'exportCertification']);
    }


    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        $notice = '';
        $noticeType = 'updated';
        $microsoftLink = '';

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST['ff_6a_action'])) {
            check_admin_referer('fieldflow_phase6a_action', 'fieldflow_phase6a_nonce');
            $action = sanitize_key((string)$_POST['ff_6a_action']);
            $planId = absint($_POST['plan_id'] ?? 0);
            $result = true;

            if ($action === 'create_plan') {
                $result = ClientOnboardingFoundation::createPlan(self::inputFromPost());
                if (is_wp_error($result)) {
                    $notice = $result->get_error_message();
                    $noticeType = 'error';
                } else {
                    $planId = (int)$result;
                    $notice = 'Plano de onboarding 6A criado. A produção continua desligada.';
                }
            } elseif ($action === 'update_plan') {
                $result = ClientOnboardingFoundation::updatePlan($planId, self::inputFromPost());
                $notice = is_wp_error($result) ? $result->get_error_message() : 'Configuração do onboarding atualizada.';
                $noticeType = is_wp_error($result) ? 'error' : 'updated';
            } elseif ($action === 'preflight') {
                $result = ClientOnboardingFoundation::preflight($planId);
                $notice = !empty($result['ok']) ? 'Pré-flight 6A concluído com sucesso.' : (string)($result['message'] ?? 'Existem pontos a rever.');
                $noticeType = !empty($result['ok']) ? 'updated' : 'error';
            } elseif ($action === 'stage') {
                $result = ClientOnboardingFoundation::stage($planId);
                $notice = is_wp_error($result) ? $result->get_error_message() : 'Provisioning em Shadow concluído. Destino e regra ficaram sem ativação operacional.';
                $noticeType = is_wp_error($result) ? 'error' : 'updated';
            } elseif ($action === 'start_microsoft') {
                $result = ClientOnboardingFoundation::startMicrosoft($planId);
                if (is_wp_error($result)) {
                    $notice = $result->get_error_message();
                    $noticeType = 'error';
                } else {
                    $microsoftLink = esc_url_raw((string)($result['consent_url'] ?? ''));
                    $notice = 'Link Microsoft Entra criado. Abre-o numa sessão do administrador do tenant do cliente.';
                }
            } elseif ($action === 'verify_microsoft') {
                $result = ClientOnboardingFoundation::verifyMicrosoft($planId);
                $notice = is_wp_error($result) ? $result->get_error_message() : 'Identidade Microsoft Entra validada.';
                $noticeType = is_wp_error($result) ? 'error' : 'updated';
            } elseif ($action === 'probe_resource') {
                $result = ClientOnboardingFoundation::configureAndProbeResource($planId);
                $notice = is_wp_error($result) ? $result->get_error_message() : (!empty($result['ok']) ? 'Recurso validado em read-only.' : 'O recurso respondeu, mas ainda não ficou autorizado.');
                $noticeType = is_wp_error($result) || empty($result['ok']) ? 'error' : 'updated';
            } elseif ($action === 'certify_credential') {
                $result = ClientOnboardingFoundation::certifyCredential($planId);
                $notice = !empty($result['ok']) ? 'Credencial Key Vault certificada sem expor o valor ao WordPress.' : 'A credencial ainda não passou a certificação 5D.';
                $noticeType = !empty($result['ok']) ? 'updated' : 'error';
            } elseif ($action === 'reset_plan') {
                $result = ClientOnboardingFoundation::resetPlan($planId);
                $notice = is_wp_error($result) ? $result->get_error_message() : 'Plano reiniciado. O destino staged foi removido e a produção continuou desligada.';
                $noticeType = is_wp_error($result) ? 'error' : 'updated';
            }
            elseif ($action === 'certify_6a') {
                $result = ClientOnboardingCertification::certify(get_current_user_id());
                $notice = !empty($result['ok']) ? 'Fecho formal da Fase 6A certificado. Production Enabled continua NÃO.' : 'A matriz 6A ainda tem pré-condições por concluir.';
                $noticeType = !empty($result['ok']) ? 'updated' : 'error';
            } elseif ($action === 'reset_6a') {
                ClientOnboardingCertification::reset();
                $notice = 'Gate formal 6A reiniciada. Os planos e destinos não foram alterados.';
                $noticeType = 'updated';
            }

            if ($planId > 0 && $action !== 'create_plan') {
                ClientOnboardingFoundation::readiness($planId);
            }
            if ($action === 'create_plan' && $planId > 0 && !is_wp_error($result)) {
                echo '<script>window.history.replaceState({},"",' . wp_json_encode(admin_url('admin.php?page=' . self::SLUG . '&plan_id=' . $planId)) . ');</script>';
            }
            $_GET['plan_id'] = $planId;
        }

        $health = ClientOnboardingFoundation::health();
        $probe = ClientOnboardingFoundation::localProbe();
        $closure = ClientOnboardingCertification::health();
        $closureProbe = ClientOnboardingCertification::localProbe();
        $plans = ClientOnboardingFoundation::listPlans();
        $selectedId = absint($_GET['plan_id'] ?? 0);
        $selected = $selectedId > 0 ? ClientOnboardingFoundation::getPlan($selectedId) : [];
        $readiness = $selected ? ClientOnboardingFoundation::readiness($selected) : [];
        $clients = self::clients();
        $projects = self::projects();
        $templates = ClientOnboardingFoundation::templates();

        AdminPage::open('Onboarding de Clientes', 'Fase 6A, configuração guiada por cliente, sem ativar produção automaticamente.');
        self::styles();

        if ($notice !== '') AdminPage::notice($notice, $noticeType);
        if ($microsoftLink !== '') {
            echo '<div class="notice notice-info"><p><strong>Microsoft Entra:</strong> <a class="button button-primary" target="_blank" rel="noopener noreferrer" href="' . esc_url($microsoftLink) . '">Abrir autorização Microsoft</a></p><p class="description">O cliente só precisa de autenticar com uma conta administradora do tenant. Não recebe acesso ao WordPress.</p></div>';
        }

        echo '<div class="ff6a-hero">';
        echo '<div><span class="ff6a-kicker">PHASE 6A</span><h2>Client Onboarding & Provisioning</h2><p>Transforma a infraestrutura certificada da Fase 5 num fluxo simples por cliente. Tudo nasce em <strong>Shadow</strong>, com regras inativas e sem payload operacional.</p></div>';
        echo '<div class="ff6a-hero-badges">';
        self::badge('Fase 5 Stable', !empty($health['phase5_stable_certified']) ? 'CERTIFICADA' : 'REVER', !empty($health['phase5_stable_certified']));
        self::badge('Baseline', !empty($health['phase5_baseline_match']) ? 'MATCH' : 'REVER', !empty($health['phase5_baseline_match']));
        self::badge('Modo', 'SHADOW', !empty($health['connector_shadow_preserved']));
        self::badge('Kill switch', !empty($health['connector_kill_switch_on']) ? 'ON' : 'REVER', !empty($health['connector_kill_switch_on']));
        self::badge('Production Enabled', 'NÃO', empty($health['production_enabled']));
        echo '</div></div>';

        echo '<div class="ff6a-grid ff6a-metrics">';
        self::metric('Planos', (string)$health['plans_total'], 'Onboardings guardados');
        self::metric('Provisionados', (string)$health['plans_staged'], 'Staged em modo seguro');
        self::metric('Production Ready', (string)$health['plans_production_ready'], 'Elegíveis para gate futuro');
        self::metric('Templates', (string)$health['templates'], 'TWT, Azure, SQL, Power BI, Fabric, API, Webhook');
        echo '</div>';

        echo '<div class="ff6a-card"><h3>Diagnóstico 6A</h3><div class="ff6a-checks">';
        foreach ((array)($probe['checks'] ?? []) as $key=>$ok) self::check($key, (bool)$ok);
        echo '</div><p class="description">A 6A não abre production delivery. O objetivo é preparar contexto, identidade, recurso, credencial e destino por cliente.</p></div>';

        echo '<div class="ff6a-card ff6a-closure"><div class="ff6a-card-head"><div><span class="ff6a-step">FECHO FORMAL 6A</span><h3>Client Onboarding Stable Gate</h3></div><span class="ff6a-status-main">' . (!empty($closure['stable_certified']) ? 'CERTIFICADA' : (!empty($closure['matrix_ready']) ? 'READY PARA CERTIFICAR' : 'REVER')) . '</span></div>';
        echo '<div class="ff6a-checks">';
        foreach ((array)($closureProbe['checks'] ?? []) as $key=>$ok) self::check($key, (bool)$ok);
        echo '</div><div class="ff6a-matrix">';
        foreach ((array)($closureProbe['matrix'] ?? []) as $gate=>$row) {
            echo '<div class="ff6a-matrix-row"><b>' . esc_html((string)$gate) . '</b><strong>' . esc_html((string)($row['label'] ?? '')) . '</strong><span class="ff6a-pill ' . (!empty($row['ready']) ? 'ok' : 'warn') . '">' . (!empty($row['ready']) ? 'READY' : 'REVER') . '</span><small>' . esc_html((string)($row['detail'] ?? '')) . '</small></div>';
        }
        echo '</div><div class="ff6a-actions">';
        self::actionButton(0, 'certify_6a', 'Certificar fecho 6A', 'primary');
        if (!empty($closure['stable_certified'])) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=fieldflow_phase6a_export'), 'fieldflow_phase6a_export');
            echo '<a class="button" href="' . esc_url($url) . '">Descarregar baseline 6A JSON</a>';
        }
        self::actionButton(0, 'reset_6a', 'Reiniciar gate 6A', 'secondary', 'Reiniciar apenas a certificação formal 6A? Os planos existentes não serão apagados.');
        echo '</div><p class="description">A certificação congela uma baseline SHA-256 do onboarding testado. Production Ready continua separado de Production Enabled.</p></div>';

        echo '<div class="ff6a-layout">';
        echo '<section class="ff6a-card ff6a-main">';
        echo '<div class="ff6a-card-head"><div><span class="ff6a-step">NOVO</span><h3>Criar onboarding</h3></div><p>Escolhe o cliente, o projeto e um template. Os campos avançados aparecem apenas quando fazem sentido.</p></div>';
        self::planForm($selected ?: [], $clients, $projects, $templates, $selected ? 'update_plan' : 'create_plan');
        echo '</section>';

        echo '<aside class="ff6a-card ff6a-side">';
        echo '<h3>Fluxo 6A</h3>';
        $steps = [
            ['1','Contexto','Cliente, projeto e template'],
            ['2','Ligação','Endpoint, tenant ou Key Vault ref'],
            ['3','Pré-flight','Validação local, sem rede'],
            ['4','Provisioning','Destino e regra em Shadow'],
            ['5','Readiness','Identidade, recurso, credencial e delivery'],
        ];
        foreach ($steps as $step) echo '<div class="ff6a-flow"><b>' . esc_html($step[0]) . '</b><div><strong>' . esc_html($step[1]) . '</strong><span>' . esc_html($step[2]) . '</span></div></div>';
        echo '<div class="ff6a-safe"><strong>Regra de ouro</strong><span>A 6A prepara. A ativação de produção será uma gate separada e explícita.</span></div>';
        echo '</aside></div>';

        if ($selected) {
            self::renderPlanControl($selected, $readiness, $templates[(string)$selected['template_key']] ?? []);
        }

        echo '<div class="ff6a-card"><div class="ff6a-card-head"><div><span class="ff6a-step">REGISTRY</span><h3>Onboardings existentes</h3></div><p>Estado consolidado por cliente. O próximo passo é sempre calculado a partir das gates reais.</p></div>';
        self::plansTable($plans);
        echo '</div>';

        self::script();
        AdminPage::close();
    }

    public static function exportCertification(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_phase6a_export');
        $health = ClientOnboardingCertification::health();
        if (empty($health['stable_certified'])) wp_die('A Fase 6A ainda não está formalmente certificada.');
        $json = wp_json_encode(ClientOnboardingCertification::exportPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="fieldflow-phase6a-stable-baseline-2.2.379.json"');
        echo is_string($json) ? $json : '{}';
        exit;
    }

    private static function inputFromPost(): array {
        return [
            'client_id'=>$_POST['client_id'] ?? 0,
            'project_id'=>$_POST['project_id'] ?? 0,
            'template_key'=>$_POST['template_key'] ?? '',
            'auth_strategy'=>$_POST['auth_strategy'] ?? '',
            'target_tenant_id'=>$_POST['target_tenant_id'] ?? '',
            'resource_scope'=>$_POST['resource_scope'] ?? '',
            'endpoint_ref'=>$_POST['endpoint_ref'] ?? '',
            'secret_ref'=>$_POST['secret_ref'] ?? '',
            'event_pattern'=>$_POST['event_pattern'] ?? 'submission.created',
            'hourly_limit'=>$_POST['hourly_limit'] ?? 10,
            'sql_server'=>$_POST['sql_server'] ?? '',
            'sql_database'=>$_POST['sql_database'] ?? '',
            'workspace_id'=>$_POST['workspace_id'] ?? '',
            'credential_auth_mode'=>$_POST['credential_auth_mode'] ?? 'api_key_header',
            'credential_header_name'=>$_POST['credential_header_name'] ?? 'X-FieldFlow-Credential',
        ];
    }

    private static function planForm(array $plan, array $clients, array $projects, array $templates, string $action): void {
        $config = (array)($plan['config'] ?? []);
        $clientId = (int)($plan['client_id'] ?? 0);
        $projectId = (int)($plan['project_id'] ?? 0);
        $templateKey = (string)($plan['template_key'] ?? 'twt_managed');
        $auth = (string)($plan['auth_strategy'] ?? ($templates[$templateKey]['auth_strategy'] ?? 'none'));
        $staged = (int)($plan['destination_id'] ?? 0) > 0;
        echo '<form method="post" class="ff6a-form" id="ff6a-plan-form">';
        wp_nonce_field('fieldflow_phase6a_action', 'fieldflow_phase6a_nonce');
        echo '<input type="hidden" name="ff_6a_action" value="' . esc_attr($action) . '">';
        echo '<input type="hidden" name="plan_id" value="' . (int)($plan['id'] ?? 0) . '">';

        echo '<div class="ff6a-fields ff6a-cols-3">';
        echo '<label><span>Cliente</span><select name="client_id" id="ff6a-client" required ' . disabled($staged, true, false) . '><option value="">Selecionar cliente</option>';
        foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '" ' . selected($clientId, (int)$client['id'], false) . '>' . esc_html($client['name']) . '</option>';
        echo '</select></label>';
        echo '<label><span>Projeto / campanha</span><select name="project_id" id="ff6a-project" ' . disabled($staged, true, false) . '><option value="0">Todo o cliente</option>';
        foreach ($projects as $project) echo '<option data-client="' . (int)$project['client_id'] . '" value="' . (int)$project['id'] . '" ' . selected($projectId, (int)$project['id'], false) . '>' . esc_html($project['name']) . '</option>';
        echo '</select></label>';
        echo '<label><span>Template</span><select name="template_key" id="ff6a-template" required ' . disabled($staged, true, false) . '>';
        foreach ($templates as $key=>$template) echo '<option value="' . esc_attr($key) . '" ' . selected($templateKey, $key, false) . '>' . esc_html($template['label']) . '</option>';
        echo '</select></label>';
        echo '</div>';

        echo '<div class="ff6a-template-note" id="ff6a-template-note"></div>';
        echo '<div class="ff6a-fields ff6a-cols-2">';
        echo '<label class="ff6a-field ff6a-endpoint"><span>Endpoint ou referência técnica</span><input name="endpoint_ref" value="' . esc_attr((string)($plan['endpoint_ref'] ?? '')) . '" placeholder="https://..., azure-sql://..., powerbi://..." ' . disabled($staged, true, false) . '><small>Nunca coloques passwords, tokens ou chaves neste campo.</small></label>';
        echo '<label class="ff6a-field ff6a-auth"><span>Autenticação</span><select name="auth_strategy" id="ff6a-auth" ' . disabled($staged, true, false) . '>';
        $authOptions = ['none'=>'Sem credencial','key_vault_reference'=>'Key Vault reference','entra_cross_tenant'=>'Microsoft Entra, tenant do cliente','entra_home_tenant'=>'Microsoft Entra, tenant TWT','managed_identity'=>'Managed Identity'];
        foreach ($authOptions as $key=>$label) echo '<option value="' . esc_attr($key) . '" ' . selected($auth, $key, false) . '>' . esc_html($label) . '</option>';
        echo '</select></label>';
        echo '</div>';

        echo '<div class="ff6a-fields ff6a-cols-2 ff6a-tenant-block">';
        echo '<label><span>Tenant ID Microsoft Entra</span><input name="target_tenant_id" value="' . esc_attr((string)($plan['target_tenant_id'] ?? '')) . '" placeholder="00000000-0000-0000-0000-000000000000" ' . disabled($staged, true, false) . '></label>';
        echo '<label><span>Resource scope</span><input name="resource_scope" id="ff6a-scope" value="' . esc_attr((string)($plan['resource_scope'] ?? '')) . '" placeholder="https://database.windows.net/.default" ' . disabled($staged, true, false) . '></label>';
        echo '</div>';

        echo '<div class="ff6a-fields ff6a-cols-2 ff6a-secret-block">';
        echo '<label><span>Secret reference</span><input name="secret_ref" value="' . esc_attr((string)($plan['secret_ref'] ?? '')) . '" placeholder="kv://vault/secret" ' . disabled($staged, true, false) . '><small>Somente referência. O valor do segredo nunca entra no WordPress.</small></label>';
        echo '<label><span>Perfil da credencial</span><div class="ff6a-inline"><select name="credential_auth_mode" ' . disabled($staged, true, false) . '><option value="api_key_header" ' . selected((string)($config['credential_auth_mode'] ?? 'api_key_header'), 'api_key_header', false) . '>API key em header</option><option value="bearer" ' . selected((string)($config['credential_auth_mode'] ?? ''), 'bearer', false) . '>Bearer token</option></select><input name="credential_header_name" value="' . esc_attr((string)($config['credential_header_name'] ?? 'X-FieldFlow-Credential')) . '" placeholder="X-FieldFlow-Credential" ' . disabled($staged, true, false) . '></div></label>';
        echo '</div>';

        echo '<div class="ff6a-fields ff6a-cols-2 ff6a-sql-block">';
        echo '<label><span>Azure SQL Server</span><input name="sql_server" value="' . esc_attr((string)($config['sql_server'] ?? '')) . '" placeholder="cliente.database.windows.net" ' . disabled($staged, true, false) . '></label>';
        echo '<label><span>Base de dados</span><input name="sql_database" value="' . esc_attr((string)($config['sql_database'] ?? '')) . '" placeholder="FieldFlowClient" ' . disabled($staged, true, false) . '></label>';
        echo '</div>';

        echo '<div class="ff6a-fields ff6a-cols-2 ff6a-workspace-block">';
        echo '<label><span>Workspace ID</span><input name="workspace_id" value="' . esc_attr((string)($config['workspace_id'] ?? '')) . '" placeholder="00000000-0000-0000-0000-000000000000" ' . disabled($staged, true, false) . '></label>';
        echo '<div></div></div>';

        echo '<div class="ff6a-fields ff6a-cols-2">';
        echo '<label><span>Evento inicial permitido</span><input name="event_pattern" value="' . esc_attr((string)($plan['event_pattern'] ?? 'submission.created')) . '" placeholder="submission.created" ' . disabled($staged, true, false) . '><small>Sem wildcards nesta fase.</small></label>';
        echo '<label><span>Limite futuro por hora</span><input type="number" min="1" max="1000" name="hourly_limit" value="' . (int)($plan['hourly_limit'] ?? 10) . '" ' . disabled($staged, true, false) . '></label>';
        echo '</div>';

        if (!$staged) echo '<p class="submit"><button class="button button-primary button-hero">' . ($action === 'create_plan' ? 'Criar plano 6A' : 'Guardar configuração') . '</button></p>';
        else echo '<div class="ff6a-lock">Configuração congelada após provisioning. Para alterar o target, reinicia o plano e volta a provisionar.</div>';
        echo '</form>';
    }

    private static function renderPlanControl(array $plan, array $readiness, array $template): void {
        echo '<div class="ff6a-card ff6a-control">';
        echo '<div class="ff6a-card-head"><div><span class="ff6a-step">PLANO ' . (int)$plan['id'] . '</span><h3>' . esc_html((string)($plan['client_name'] ?? self::clientLabel((int)$plan['client_id']))) . '</h3></div><div class="ff6a-status-main">' . esc_html(strtoupper((string)($readiness['status'] ?? 'draft'))) . '</div></div>';
        echo '<div class="ff6a-readiness">';
        self::readinessItem('Contexto', true, 'Cliente e projeto');
        self::readinessItem('Provisioning', !empty($readiness['staged']), !empty($readiness['staged']) ? 'Staged' : 'Pendente');
        self::readinessItem('Identidade', !empty($readiness['identity_ready']), !empty($readiness['identity_ready']) ? 'Ready' : 'Pendente');
        self::readinessItem('Recurso', !empty($readiness['resource_ready']), !empty($readiness['resource_ready']) ? 'Ready' : 'Pendente');
        self::readinessItem('Credencial', !empty($readiness['credential_ready']), !empty($readiness['credential_ready']) ? 'Ready' : 'Pendente');
        self::readinessItem('Delivery', !empty($readiness['delivery_ready']), !empty($readiness['delivery_ready']) ? 'Ready' : 'Pendente');
        echo '</div>';

        echo '<div class="ff6a-next"><strong>Próxima ação</strong><span>' . esc_html((string)($readiness['next_action'] ?? 'Rever configuração')) . '</span></div>';
        echo '<div class="ff6a-actions">';
        self::actionButton((int)$plan['id'], 'preflight', '1. Validar pré-flight', 'secondary');
        if (empty($readiness['staged'])) self::actionButton((int)$plan['id'], 'stage', '2. Provisionar em Shadow', 'primary');
        if ((string)$plan['auth_strategy'] === 'entra_cross_tenant' && !empty($readiness['staged']) && empty($readiness['identity_ready'])) {
            self::actionButton((int)$plan['id'], 'start_microsoft', '3. Gerar link Microsoft', 'primary');
            self::actionButton((int)$plan['id'], 'verify_microsoft', 'Verificar identidade', 'secondary');
        }
        if (in_array((string)($template['resource_kind'] ?? ''), ['azure_sql','powerbi_workspace','fabric_workspace'], true) && !empty($readiness['identity_ready']) && empty($readiness['resource_ready'])) {
            self::actionButton((int)$plan['id'], 'probe_resource', '4. Validar recurso read-only', 'primary');
        }
        if ((string)$plan['auth_strategy'] === 'key_vault_reference' && in_array((string)$plan['destination_type'], ['external_api','webhook'], true) && !empty($readiness['staged']) && empty($readiness['credential_ready'])) {
            self::actionButton((int)$plan['id'], 'certify_credential', '4. Certificar credencial', 'primary');
        }
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=' . DataDestinations::SLUG)) . '">Abrir Destinos de Dados</a>';
        self::actionButton((int)$plan['id'], 'reset_plan', 'Reiniciar plano', 'danger', 'Isto remove o destino e a regra staged deste onboarding. Continuar?');
        echo '</div>';
        echo '<div class="ff6a-production"><strong>PRODUCTION ENABLED</strong><span>NÃO</span><small>A 6A nunca ativa produção. Mesmo um plano Production Ready precisa da gate explícita de ativação da Fase 6.</small></div>';
        echo '</div>';
    }

    private static function plansTable(array $plans): void {
        if (!$plans) {
            echo '<div class="ff6a-empty">Ainda não existem onboardings 6A. Cria o primeiro acima.</div>';
            return;
        }
        echo '<div class="ff6a-table-wrap"><table class="widefat striped ff6a-table"><thead><tr><th>Cliente</th><th>Projeto</th><th>Template</th><th>Destino</th><th>Estado</th><th>Próximo passo</th><th></th></tr></thead><tbody>';
        $templates = ClientOnboardingFoundation::templates();
        foreach ($plans as $plan) {
            $ready = ClientOnboardingFoundation::readiness($plan);
            $template = $templates[(string)$plan['template_key']] ?? ['label'=>$plan['template_key']];
            echo '<tr>';
            echo '<td><strong>' . esc_html((string)($plan['client_name'] ?? 'Cliente #' . (int)$plan['client_id'])) . '</strong><br><code>' . esc_html((string)$plan['plan_uuid']) . '</code></td>';
            echo '<td>' . esc_html((string)($plan['project_name'] ?? 'Todo o cliente')) . '</td>';
            echo '<td>' . esc_html((string)$template['label']) . '</td>';
            echo '<td>' . ((int)$plan['destination_id'] > 0 ? '#' . (int)$plan['destination_id'] : 'Ainda não criado') . '</td>';
            echo '<td><span class="ff6a-pill ' . (!empty($ready['production_ready']) ? 'ok' : (!empty($ready['staged']) ? 'warn' : 'neutral')) . '">' . esc_html(strtoupper((string)$ready['status'])) . '</span></td>';
            echo '<td>' . esc_html((string)$ready['next_action']) . '</td>';
            echo '<td><a class="button button-small" href="' . esc_url(admin_url('admin.php?page=' . self::SLUG . '&plan_id=' . (int)$plan['id'])) . '">Abrir</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function actionButton(int $planId, string $action, string $label, string $kind = 'secondary', string $confirm = ''): void {
        $class = $kind === 'primary' ? 'button button-primary' : 'button';
        if ($kind === 'danger') $class .= ' ff6a-danger';
        echo '<form method="post" style="display:inline-block">';
        wp_nonce_field('fieldflow_phase6a_action', 'fieldflow_phase6a_nonce');
        echo '<input type="hidden" name="ff_6a_action" value="' . esc_attr($action) . '"><input type="hidden" name="plan_id" value="' . $planId . '">';
        echo '<button class="' . esc_attr($class) . '"' . ($confirm !== '' ? ' onclick="return confirm(' . esc_attr(wp_json_encode($confirm)) . ')"' : '') . '>' . esc_html($label) . '</button></form>';
    }

    private static function clients(): array {
        global $wpdb;
        return $wpdb->get_results('SELECT id,name FROM ' . $wpdb->prefix . 'routespro_clients ORDER BY name ASC,id ASC', ARRAY_A) ?: [];
    }

    private static function projects(): array {
        global $wpdb;
        return $wpdb->get_results('SELECT id,client_id,name,status FROM ' . $wpdb->prefix . 'routespro_projects WHERE status IS NULL OR status<>\'archived\' ORDER BY name ASC,id ASC', ARRAY_A) ?: [];
    }

    private static function clientLabel(int $id): string {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', $id));
    }

    private static function badge(string $label, string $value, bool $ok): void {
        echo '<span class="ff6a-badge ' . ($ok ? 'ok' : 'bad') . '"><small>' . esc_html($label) . '</small><strong>' . esc_html($value) . '</strong></span>';
    }

    private static function metric(string $label, string $value, string $caption): void {
        echo '<div class="ff6a-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong><small>' . esc_html($caption) . '</small></div>';
    }

    private static function check(string $key, bool $ok): void {
        echo '<span class="ff6a-check ' . ($ok ? 'ok' : 'bad') . '">' . esc_html($key) . ': ' . ($ok ? 'OK' : 'REVER') . '</span>';
    }

    private static function readinessItem(string $label, bool $ok, string $caption): void {
        echo '<div class="ff6a-ready ' . ($ok ? 'ok' : 'pending') . '"><span>' . ($ok ? '✓' : '•') . '</span><div><strong>' . esc_html($label) . '</strong><small>' . esc_html($caption) . '</small></div></div>';
    }

    private static function styles(): void {
        echo '<style>
        .ff6a-hero{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;padding:26px;border:1px solid #e2e8f0;border-radius:22px;background:linear-gradient(135deg,#fff 0%,#f8fafc 65%,#eef8f7 100%);margin:18px 0}.ff6a-kicker,.ff6a-step{font-size:11px;letter-spacing:.14em;font-weight:800;color:#0f766e}.ff6a-hero h2,.ff6a-card h3{margin:6px 0 8px}.ff6a-hero p{max-width:760px;color:#475569}.ff6a-hero-badges{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;max-width:520px}.ff6a-badge{display:flex;flex-direction:column;padding:9px 12px;border-radius:12px;border:1px solid}.ff6a-badge.ok{background:#ecfdf5;border-color:#a7f3d0;color:#166534}.ff6a-badge.bad{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.ff6a-badge small{font-size:10px;opacity:.75}.ff6a-grid{display:grid;gap:14px}.ff6a-metrics{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:14px}.ff6a-metric,.ff6a-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px}.ff6a-metric span{font-size:12px;color:#64748b}.ff6a-metric strong{display:block;font-size:28px;margin:6px 0}.ff6a-metric small{color:#64748b}.ff6a-checks{display:flex;flex-wrap:wrap;gap:8px}.ff6a-check{padding:7px 9px;border-radius:999px;font-size:11px}.ff6a-check.ok{background:#ecfdf5;color:#166534}.ff6a-check.bad{background:#fff7ed;color:#9a3412}.ff6a-layout{display:grid;grid-template-columns:minmax(0,2.1fr) minmax(280px,.9fr);gap:14px;margin:14px 0}.ff6a-card-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.ff6a-card-head p{max-width:480px;color:#64748b;margin:0}.ff6a-fields{display:grid;gap:14px;margin-top:16px}.ff6a-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.ff6a-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.ff6a-form label>span{display:block;font-weight:700;margin-bottom:6px}.ff6a-form input,.ff6a-form select{width:100%;min-height:42px}.ff6a-form small{display:block;color:#64748b;margin-top:5px}.ff6a-template-note{margin-top:14px;padding:12px 14px;border-radius:12px;background:#f8fafc;color:#475569}.ff6a-inline{display:grid;grid-template-columns:180px 1fr;gap:8px}.ff6a-flow{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #eef2f7}.ff6a-flow>b{width:28px;height:28px;display:grid;place-items:center;border-radius:50%;background:#0f172a;color:#fff}.ff6a-flow strong,.ff6a-flow span{display:block}.ff6a-flow span{font-size:12px;color:#64748b;margin-top:2px}.ff6a-safe{margin-top:18px;padding:14px;border-radius:14px;background:#0f172a;color:#fff}.ff6a-safe span{display:block;font-size:12px;opacity:.75;margin-top:4px}.ff6a-control{margin:14px 0}.ff6a-status-main{padding:8px 12px;border-radius:999px;background:#ecfdf5;color:#166534;font-weight:800;font-size:12px}.ff6a-readiness{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px;margin:16px 0}.ff6a-ready{display:flex;gap:8px;align-items:center;padding:10px;border-radius:12px;border:1px solid}.ff6a-ready.ok{background:#ecfdf5;border-color:#bbf7d0}.ff6a-ready.pending{background:#f8fafc;border-color:#e2e8f0}.ff6a-ready>span{font-weight:900}.ff6a-ready strong,.ff6a-ready small{display:block}.ff6a-ready small{color:#64748b;font-size:10px}.ff6a-next{padding:13px 15px;border-radius:12px;background:#f8fafc}.ff6a-next strong,.ff6a-next span{display:block}.ff6a-next span{color:#475569;margin-top:3px}.ff6a-actions{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0}.ff6a-danger{color:#b91c1c!important;border-color:#fecaca!important}.ff6a-production{display:flex;align-items:center;gap:10px;padding:14px;border-left:4px solid #dc2626;background:#fff7f7}.ff6a-production strong{font-size:12px}.ff6a-production span{font-weight:900;color:#b91c1c}.ff6a-production small{color:#64748b}.ff6a-pill{display:inline-block;padding:5px 8px;border-radius:999px;font-size:10px;font-weight:800}.ff6a-pill.ok{background:#dcfce7;color:#166534}.ff6a-pill.warn{background:#fef3c7;color:#92400e}.ff6a-pill.neutral{background:#e2e8f0;color:#334155}.ff6a-table code{font-size:10px}.ff6a-table-wrap{overflow:auto}.ff6a-empty{padding:24px;text-align:center;color:#64748b}.ff6a-lock{padding:12px 14px;background:#fef3c7;color:#92400e;border-radius:12px;margin-top:16px}.ff6a-closure{margin-top:14px}.ff6a-matrix{display:grid;gap:8px;margin:14px 0}.ff6a-matrix-row{display:grid;grid-template-columns:72px minmax(190px,.7fr) 100px minmax(260px,1.3fr);gap:10px;align-items:center;padding:10px 12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.ff6a-matrix-row small{color:#64748b}
        @media(max-width:1100px){.ff6a-matrix-row{grid-template-columns:64px 1fr 100px}.ff6a-matrix-row small{grid-column:2/4}.ff6a-hero{display:block}.ff6a-hero-badges{justify-content:flex-start;max-width:none;margin-top:16px}.ff6a-metrics{grid-template-columns:repeat(2,1fr)}.ff6a-layout{grid-template-columns:1fr}.ff6a-readiness{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.ff6a-matrix-row{grid-template-columns:1fr}.ff6a-matrix-row small{grid-column:auto}.ff6a-metrics,.ff6a-cols-2,.ff6a-cols-3,.ff6a-readiness{grid-template-columns:1fr}.ff6a-inline{grid-template-columns:1fr}.ff6a-card-head{display:block}.ff6a-hero,.ff6a-card,.ff6a-metric{padding:16px}}
        </style>';
    }

    private static function script(): void {
        $templates = [];
        foreach (ClientOnboardingFoundation::templates() as $key=>$template) {
            $templates[$key] = [
                'description'=>(string)$template['description'],
                'auth_strategy'=>(string)$template['auth_strategy'],
                'default_scope'=>(string)$template['default_scope'],
                'needs_secret'=>!empty($template['needs_secret']),
                'needs_tenant'=>!empty($template['needs_tenant']),
                'resource_kind'=>(string)$template['resource_kind'],
            ];
        }
        echo '<script>(function(){const templates=' . wp_json_encode($templates) . ';const f=document.getElementById("ff6a-plan-form");if(!f)return;const t=document.getElementById("ff6a-template"),c=document.getElementById("ff6a-client"),p=document.getElementById("ff6a-project"),a=document.getElementById("ff6a-auth"),n=document.getElementById("ff6a-template-note"),scope=document.getElementById("ff6a-scope");function toggle(selector,on){document.querySelectorAll(selector).forEach(el=>el.style.display=on?"grid":"none")}function render(setDefaults){const x=templates[t.value]||{};n.textContent=x.description||"";if(setDefaults&&a&&!a.disabled)a.value=x.auth_strategy||"none";if(setDefaults&&scope&&!scope.disabled){scope.value=x.default_scope||"";scope.dataset.autofill="1"}toggle(".ff6a-tenant-block",!!x.needs_tenant||(a&&a.value==="entra_cross_tenant"));toggle(".ff6a-secret-block",!!x.needs_secret||(a&&a.value==="key_vault_reference"));toggle(".ff6a-sql-block",x.resource_kind==="azure_sql");toggle(".ff6a-workspace-block",x.resource_kind==="powerbi_workspace"||x.resource_kind==="fabric_workspace");}function projects(){const cid=c?c.value:"";if(!p)return;Array.from(p.options).forEach((o,i)=>{if(i===0)return;o.hidden=cid&&o.dataset.client!==cid;if(o.hidden&&o.selected)p.value="0"})}if(t)t.addEventListener("change",()=>{if(scope)scope.dataset.autofill="1";render(true)});if(a)a.addEventListener("change",()=>render(false));if(c)c.addEventListener("change",projects);if(scope)scope.addEventListener("input",()=>scope.dataset.autofill="0");projects();render(false);})();</script>';
    }
}
