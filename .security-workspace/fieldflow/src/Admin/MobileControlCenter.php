<?php
namespace RoutesPro\Admin;

use RoutesPro\Mobile\Auth;
use RoutesPro\Mobile\Settings;
use RoutesPro\Mobile\WebViewBridge;
use RoutesPro\Support\AdminPage;
use RoutesPro\Forms\RuntimeV3;
use RoutesPro\Forms\SubmissionPipeline;

if (!defined('ABSPATH')) exit;

class MobileControlCenter {
    const SLUG = 'routespro-mobile';

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        $notice = self::handle_post();
        $tab = sanitize_key((string)($_GET['tab'] ?? 'overview'));
        if (!in_array($tab, ['overview','general','versions','bridge','devices','features','diagnostics'], true)) $tab = 'overview';
        $settings = Settings::get();

        AdminPage::open('Aplicação Mobile', 'Centro de controlo da bridge Flutter, Android, iOS, sessões e versões.');
        self::styles();
        if ($notice) AdminPage::notice($notice['message'], $notice['type']);
        self::nav($tab);

        echo '<div class="ff-mobile-shell">';
        switch ($tab) {
            case 'general': self::general($settings); break;
            case 'versions': self::versions($settings); break;
            case 'bridge': self::bridge($settings); break;
            case 'devices': self::devices(); break;
            case 'features': self::features($settings); break;
            case 'diagnostics': self::diagnostics($settings); break;
            default: self::overview($settings); break;
        }
        echo '</div>';
        AdminPage::close();
    }

    private static function handle_post(): ?array {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') return null;
        check_admin_referer('fieldflow_mobile_control_center', 'fieldflow_mobile_nonce');
        $action = sanitize_key((string)($_POST['fieldflow_mobile_action'] ?? 'save'));

        if ($action === 'save') {
            $input = isset($_POST['fieldflow_mobile']) && is_array($_POST['fieldflow_mobile'])
                ? wp_unslash($_POST['fieldflow_mobile']) : [];
            Settings::save($input);
            if (isset($_POST['fieldflow_forms_runtime_v3']) && is_array($_POST['fieldflow_forms_runtime_v3'])) {
                RuntimeV3::save(wp_unslash($_POST['fieldflow_forms_runtime_v3']));
            }
            if (isset($_POST['fieldflow_submission_pipeline']) && is_array($_POST['fieldflow_submission_pipeline'])) {
                SubmissionPipeline::save(wp_unslash($_POST['fieldflow_submission_pipeline']));
            }
            return ['message' => 'Configuração móvel e rollout técnico guardados com sucesso.', 'type' => 'updated'];
        }
        if ($action === 'runtime_v3_rollback') {
            RuntimeV3::emergencyRollback(get_current_user_id());
            return ['message' => 'Forms Runtime 3.0 desativado. Todos os novos formulários regressaram ao motor estável.', 'type' => 'updated'];
        }
        if ($action === 'submission_pipeline_rollback') {
            SubmissionPipeline::emergencyRollback(get_current_user_id());
            return ['message' => 'Submission Pipeline desativada. Web e mobile regressaram imediatamente aos fluxos estáveis.', 'type' => 'updated'];
        }

        $session_id = absint($_POST['session_id'] ?? 0);
        $user_id = absint($_POST['user_id'] ?? 0);
        $device_id = sanitize_text_field((string)($_POST['device_id'] ?? ''));

        if ($action === 'revoke_session' && $session_id > 0) {
            WebViewBridge::revoke_by_mobile_session($session_id);
            Auth::revoke_by_id($session_id);
            return ['message' => 'Sessão revogada. O dispositivo terá de iniciar sessão novamente.', 'type' => 'updated'];
        }
        if ($action === 'revoke_user' && $user_id > 0) {
            WebViewBridge::revoke_all_for_user($user_id);
            Auth::revoke_all($user_id);
            return ['message' => 'Todas as sessões deste utilizador foram revogadas.', 'type' => 'updated'];
        }
        if ($action === 'block_device' && $device_id !== '') {
            Settings::block_device($device_id, get_current_user_id());
            Auth::revoke_by_device($device_id);
            return ['message' => 'Dispositivo bloqueado e respetivas sessões revogadas.', 'type' => 'updated'];
        }
        if ($action === 'unblock_device' && $device_id !== '') {
            Settings::unblock_device($device_id);
            return ['message' => 'Dispositivo desbloqueado.', 'type' => 'updated'];
        }
        if ($action === 'cleanup') {
            Auth::cleanup();
            WebViewBridge::cleanup();
            return ['message' => 'Sessões expiradas e registos antigos foram limpos.', 'type' => 'updated'];
        }
        return ['message' => 'A ação solicitada não foi reconhecida.', 'type' => 'error'];
    }

    private static function nav(string $active): void {
        $tabs = [
            'overview' => 'Visão geral',
            'general' => 'Configuração',
            'versions' => 'Versões e manutenção',
            'bridge' => 'Bridge WebView',
            'devices' => 'Sessões e dispositivos',
            'features' => 'Funcionalidades',
            'diagnostics' => 'Diagnóstico',
        ];
        echo '<nav class="ff-mobile-tabs">';
        foreach ($tabs as $key => $label) {
            $url = admin_url('admin.php?page=' . self::SLUG . '&tab=' . $key);
            echo '<a class="' . ($active === $key ? 'is-active' : '') . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private static function overview(array $s): void {
        global $wpdb;
        $table = Auth::table();
        $web = WebViewBridge::table();
        $now = current_time('mysql', true);
        $stats = [
            'active' => self::count("SELECT COUNT(*) FROM {$table} WHERE revoked_at IS NULL AND refresh_expires_at>%s", [$now]),
            'devices' => self::count("SELECT COUNT(DISTINCT device_id) FROM {$table} WHERE device_id<>''"),
            'android' => self::count("SELECT COUNT(*) FROM {$table} WHERE platform='android' AND revoked_at IS NULL AND refresh_expires_at>%s", [$now]),
            'ios' => self::count("SELECT COUNT(*) FROM {$table} WHERE platform='ios' AND revoked_at IS NULL AND refresh_expires_at>%s", [$now]),
            'web' => self::count("SELECT COUNT(*) FROM {$web} WHERE revoked_at IS NULL AND web_expires_at>%s", [$now]),
        ];
        $maintenance = Settings::maintenance_enabled() ? 'Ativa' : 'Desligada';
        $bridge = Settings::bridge_enabled() ? 'Operacional' : 'Desativada';

        echo '<div class="ff-mobile-hero"><div><span class="ff-mobile-eyebrow">Mobile Bridge Control Center</span><h2>Flutter e WordPress na mesma torre de controlo</h2><p>Gere versões, manutenção, sessões, dispositivos e capacidades sem recompilar a aplicação.</p></div><div class="ff-mobile-status ' . (Settings::bridge_enabled() ? 'ok' : 'off') . '"><span></span>' . esc_html($bridge) . '</div></div>';
        echo '<div class="ff-mobile-metrics">';
        self::metric('Sessões ativas', $stats['active'], 'Tokens móveis válidos');
        self::metric('Dispositivos', $stats['devices'], 'Device IDs conhecidos');
        self::metric('Android', $stats['android'], 'Sessões Android ativas');
        self::metric('iOS', $stats['ios'], 'Sessões iOS ativas');
        self::metric('WebViews', $stats['web'], 'Portais móveis ativos');
        self::metric('Manutenção', $maintenance, 'Estado global');
        echo '</div>';

        echo '<div class="ff-mobile-grid two">';
        self::publication_status_island($s, $bridge, $maintenance);

        self::card_open('Endpoints da bridge', 'Contratos usados pelo FlutterFlow e pelas futuras builds iOS.');
        $items = [
            'Configuração' => rest_url('fieldflow-mobile/v1/app-config'),
            'Login' => rest_url('fieldflow-mobile/v1/auth/login'),
            'Refresh' => rest_url('fieldflow-mobile/v1/auth/refresh'),
            'Web session' => rest_url('fieldflow-mobile/v1/auth/web-session'),
            'Heartbeat' => rest_url('fieldflow-mobile/v1/auth/web-session/heartbeat'),
            'Contexto' => rest_url('fieldflow-mobile/v1/me'),
            'Experiência de subscrição' => rest_url('fieldflow-mobile/v1/billing/experience'),
        ];
        echo '<div class="ff-mobile-endpoints">';
        foreach ($items as $label => $url) echo '<div><span>' . esc_html($label) . '</span><code>' . esc_html($url) . '</code></div>';
        echo '</div>';
        self::card_close();
        echo '</div>';
    }

    private static function publication_status_island(array $s, string $bridge, string $maintenance): void {
        $platforms = [];
        foreach (['android' => 'Android', 'ios' => 'iOS'] as $key => $label) {
            $p = (array)($s['platforms'][$key] ?? []);
            $platforms[] = [
                'key' => $key,
                'label' => $label,
                'enabled' => !empty($p['enabled']),
                'minimumVersion' => (string)($p['minimum_version'] ?? ''),
                'latestVersion' => (string)($p['latest_version'] ?? ''),
                'buildNumber' => (string)($p['build_number'] ?? ''),
            ];
        }
        $props = [
            'contract' => class_exists(ReactBackofficeFoundation::class) ? ReactBackofficeFoundation::CONTRACT : 'fieldflow-react-backoffice-foundation-v1',
            'island' => 'mobile-release-status',
            'title' => 'Estado de publicação',
            'description' => 'Versões que o endpoint mobileAppConfig entrega às apps.',
            'bridgeStatus' => $bridge,
            'maintenanceStatus' => $maintenance,
            'platforms' => $platforms,
        ];
        $enabled = class_exists(ReactBackofficeFoundation::class) && ReactBackofficeFoundation::islandEnabled('mobile-release-status');
        $attrs = $enabled
            ? ' data-fieldflow-react-island="mobile-release-status" data-fieldflow-react-props="' . esc_attr((string)wp_json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '"'
            : '';
        echo '<div' . $attrs . '>';
        self::card_open('Estado de publicação', 'Versões que o endpoint mobileAppConfig entrega às apps.');
        foreach (['android' => 'Android', 'ios' => 'iOS'] as $key => $label) {
            $p = $s['platforms'][$key];
            echo '<div class="ff-mobile-platform"><strong>' . esc_html($label) . '</strong><span class="pill ' . (!empty($p['enabled']) ? 'good' : 'muted') . '">' . (!empty($p['enabled']) ? 'Ativo' : 'Desativado') . '</span><dl><dt>Mínima</dt><dd>' . esc_html($p['minimum_version']) . '</dd><dt>Mais recente</dt><dd>' . esc_html($p['latest_version']) . '</dd><dt>Build</dt><dd>' . esc_html($p['build_number']) . '</dd></dl></div>';
        }
        self::card_close();
        echo '</div>';
    }

    private static function general(array $s): void {
        self::form_open('general');
        echo '<div class="ff-mobile-grid two">';
        self::card_open('Identidade da aplicação', 'Informação operacional partilhada entre Android, iOS e o backoffice.');
        self::check('fieldflow_mobile[general][enabled]', 'Plataforma móvel ativa', $s['general']['enabled']);
        self::input('Nome do produto', 'fieldflow_mobile[general][app_name]', $s['general']['app_name']);
        self::input('Nome apresentado', 'fieldflow_mobile[general][display_name]', $s['general']['display_name']);
        self::select('Ambiente', 'fieldflow_mobile[general][environment]', $s['general']['environment'], ['production'=>'Produção','staging'=>'Staging','development'=>'Desenvolvimento']);
        self::card_close();

        self::card_open('Suporte e conformidade', 'URLs devolvidos à app e usados nas lojas.');
        self::input('Email de suporte', 'fieldflow_mobile[general][support_email]', $s['general']['support_email'], 'email');
        self::input('URL de suporte', 'fieldflow_mobile[general][support_url]', $s['general']['support_url'], 'url');
        self::input('Política de privacidade', 'fieldflow_mobile[general][privacy_url]', $s['general']['privacy_url'], 'url');
        self::input('Termos de utilização', 'fieldflow_mobile[general][terms_url]', $s['general']['terms_url'], 'url');
        self::card_close();
        echo '</div>';
        self::save_button(); self::form_close();
    }

    private static function versions(array $s): void {
        self::form_open('versions');
        echo '<div class="ff-mobile-grid two">';
        foreach (['android'=>'Android / Google Play','ios'=>'iOS / App Store'] as $key => $title) {
            $p = $s['platforms'][$key];
            self::card_open($title, 'Configuração independente por plataforma.');
            self::check("fieldflow_mobile[platforms][{$key}][enabled]", 'Plataforma permitida', $p['enabled']);
            self::input('Package / Bundle ID', "fieldflow_mobile[platforms][{$key}][package_id]", $p['package_id']);
            self::input('Versão mínima', "fieldflow_mobile[platforms][{$key}][minimum_version]", $p['minimum_version']);
            self::input('Versão mais recente', "fieldflow_mobile[platforms][{$key}][latest_version]", $p['latest_version']);
            self::input('Build publicada', "fieldflow_mobile[platforms][{$key}][build_number]", $p['build_number'], 'number');
            self::input('URL da loja', "fieldflow_mobile[platforms][{$key}][store_url]", $p['store_url'], 'url');
            self::check("fieldflow_mobile[platforms][{$key}][force_update]", 'Forçar atualização', $p['force_update']);
            self::textarea('Notas da versão', "fieldflow_mobile[platforms][{$key}][release_notes]", $p['release_notes']);
            self::card_close();
        }
        echo '</div>';

        $maintenance_status = Settings::maintenance_status('android');
        self::card_open('Modo de manutenção', 'Pode ser ativado ou programado sem publicar uma nova APK ou build iOS.');
        echo '<div class="ff-mobile-grid two compact">';
        echo '<div>';
        self::check('fieldflow_mobile[maintenance][enabled]', 'Ativar manutenção', $s['maintenance']['enabled']);
        self::check('fieldflow_mobile[maintenance][android]', 'Aplicar a Android', $s['maintenance']['android']);
        self::check('fieldflow_mobile[maintenance][ios]', 'Aplicar a iOS', $s['maintenance']['ios']);
        self::check('fieldflow_mobile[maintenance][web]', 'Aplicar a Web/PWA', $s['maintenance']['web']);
        self::check('fieldflow_mobile[maintenance][auto_disable]', 'Desativar automaticamente na conclusão prevista', $s['maintenance']['auto_disable']);
        echo '<p class="description">Estado Android: <strong>' . esc_html($maintenance_status['enabled'] ? 'Ativo' : ($maintenance_status['scheduled'] ? 'Programado' : ($maintenance_status['expired'] ? 'Terminado automaticamente' : 'Desligado'))) . '</strong></p>';
        echo '</div><div>';
        self::input('Título', 'fieldflow_mobile[maintenance][title]', $s['maintenance']['title']);
        self::textarea('Mensagem', 'fieldflow_mobile[maintenance][message]', $s['maintenance']['message']);
        self::input('Início programado', 'fieldflow_mobile[maintenance][starts_at]', $s['maintenance']['starts_at'], 'datetime-local');
        self::input('Conclusão prevista', 'fieldflow_mobile[maintenance][expected_at]', $s['maintenance']['expected_at'], 'datetime-local');
        self::input('Tentar novamente após, segundos', 'fieldflow_mobile[maintenance][retry_after_seconds]', $s['maintenance']['retry_after_seconds'], 'number');
        echo '</div></div>';
        echo '<p class="description">Sem data de início, a manutenção começa assim que for ativada. O intervalo de tentativa é devolvido ao Flutter no app-config.</p>';
        self::card_close();
        self::save_button(); self::form_close();
    }

    private static function bridge(array $s): void {
        self::form_open('bridge');
        echo '<div class="ff-mobile-grid two">';
        self::card_open('Sessões e renovação', 'Controlos que afetam imediatamente a bridge WebView.');
        self::check('fieldflow_mobile[bridge][enabled]', 'Bridge WebView ativa', $s['bridge']['enabled']);
        self::check('fieldflow_mobile[bridge][operational_enabled]', 'Portal operacional permitido', $s['bridge']['operational_enabled']);
        self::check('fieldflow_mobile[bridge][client_enabled]', 'Portal de cliente permitido', $s['bridge']['client_enabled']);
        self::check('fieldflow_mobile[bridge][billing_enabled]', 'Gestão de faturação permitida na app', $s['bridge']['billing_enabled']);
        self::check('fieldflow_mobile[bridge][subscriptions_enabled]', 'Planos e subscrições permitidos na app', $s['bridge']['subscriptions_enabled']);
        self::check('fieldflow_mobile[bridge][auto_renew]', 'Renovar automaticamente a sessão WebView enquanto a app está ativa', $s['bridge']['auto_renew']);
        self::input('Validade do ticket, segundos', 'fieldflow_mobile[bridge][ticket_ttl_seconds]', $s['bridge']['ticket_ttl_seconds'], 'number');
        self::input('Sessão WebView, horas', 'fieldflow_mobile[bridge][web_session_hours]', $s['bridge']['web_session_hours'], 'number');
        self::input('Renovar quando faltarem, minutos', 'fieldflow_mobile[bridge][renew_window_minutes]', $s['bridge']['renew_window_minutes'], 'number');
        self::input('Heartbeat da sessão, segundos', 'fieldflow_mobile[bridge][heartbeat_seconds]', $s['bridge']['heartbeat_seconds'], 'number');
        self::input('Sincronização de contexto, segundos', 'fieldflow_mobile[bridge][context_sync_seconds]', $s['bridge']['context_sync_seconds'], 'number');
        echo '<p class="description">A renovação da WebView nunca ultrapassa a validade do refresh token da sessão móvel.</p>';
        self::card_close();

        self::card_open('Conteúdo e navegação', 'Valores entregues ao Flutter e políticas aplicadas pela shell móvel.');
        foreach ([
            'javascript_enabled'=>'JavaScript da bridge ativo',
            'allow_downloads'=>'Permitir downloads',
            'allow_external_links'=>'Permitir links externos',
            'open_external_in_system_browser'=>'Pedir abertura de links externos no browser do sistema',
            'allow_file_uploads'=>'Permitir uploads de ficheiros',
            'allow_multiple_uploads'=>'Permitir uploads múltiplos',
            'allow_camera'=>'Permitir câmara',
            'allow_gallery'=>'Permitir galeria',
            'allow_location'=>'Permitir localização',
            'network_status_banner'=>'Mostrar aviso de falta de ligação',
            'native_image_viewer'=>'Visualizador de imagens em ecrã inteiro',
            'enable_zoom'=>'Permitir zoom na WebView',
            'show_portal_header'=>'Mostrar cabeçalho do portal',
            'show_logout'=>'Mostrar logout',
        ] as $key => $label) self::check("fieldflow_mobile[bridge][{$key}]", $label, $s['bridge'][$key]);
        echo '<p class="description"><strong>Nota:</strong> a app atual necessita de JavaScript para formulários, rotas e analytics. Desativar esta opção remove apenas a camada JavaScript da bridge, não os scripts internos do portal.</p>';
        self::card_close();
        echo '</div>';

        self::card_open('Domínios autorizados', 'Um domínio por linha. O domínio principal do FieldFlow deve permanecer nesta lista.');
        self::textarea('Domínios autorizados', 'fieldflow_mobile[bridge][allowed_domains]', $s['bridge']['allowed_domains']);
        self::card_close();

        self::card_open('Segurança da sessão', 'Limites aplicados a novos logins e renovações.');
        echo '<div class="ff-mobile-grid four compact">';
        self::input('Access token, minutos', 'fieldflow_mobile[security][access_ttl_minutes]', $s['security']['access_ttl_minutes'], 'number');
        self::input('Refresh token, dias', 'fieldflow_mobile[security][refresh_ttl_days]', $s['security']['refresh_ttl_days'], 'number');
        self::input('Tentativas de login', 'fieldflow_mobile[security][login_attempts]', $s['security']['login_attempts'], 'number');
        self::input('Janela de bloqueio, minutos', 'fieldflow_mobile[security][login_window_minutes]', $s['security']['login_window_minutes'], 'number');
        echo '</div>';
        self::card_close();
        self::save_button(); self::form_close();
    }

    private static function features(array $s): void {
        self::form_open('features');
        self::card_open('Funcionalidades móveis por portal', 'Define, de forma independente, as abas e capacidades expostas no portal Cliente e no portal Operativo. Permissões e subscrições continuam a ser respeitadas.');
        $labels = [
            'routes'=>'Rotas', 'reporting'=>'Reportes e formulários', 'media_upload'=>'Evidências e uploads',
            'briefings'=>'Briefings', 'implementations'=>'Implementações', 'stocks'=>'Stocks',
            'expenses'=>'Despesas', 'analytics'=>'Analytics', 'commercial_base'=>'Base comercial',
            'campaign_pdvs'=>'Campanhas PDV', 'team'=>'Equipa', 'messages'=>'Mensagens',
            'academy'=>'Academy', 'growth_hub'=>'Growth Hub',
        ];
        $portalMeta = [
            'operational' => [
                'title' => 'Portal Operativo',
                'description' => 'O que o merchandiser / operativo pode ver na experiência móvel.',
                'applicable' => ['routes','reporting','media_upload','briefings','implementations','stocks','expenses','analytics','commercial_base','messages','academy'],
            ],
            'client' => [
                'title' => 'Portal Cliente',
                'description' => 'O que o cliente / supervisor pode ver na experiência móvel.',
                'applicable' => ['routes','reporting','briefings','implementations','stocks','analytics','commercial_base','campaign_pdvs','team','messages','growth_hub'],
            ],
        ];
        echo '<div class="ff-mobile-portal-feature-grid">';
        foreach ($portalMeta as $target => $meta) {
            echo '<section class="ff-mobile-portal-feature-card">';
            echo '<div class="ff-mobile-portal-feature-head"><div><span class="ff-mobile-eyebrow">' . esc_html($target === 'client' ? 'CLIENTE' : 'OPERATIVO') . '</span><h3>' . esc_html($meta['title']) . '</h3><p>' . esc_html($meta['description']) . '</p></div><span class="pill good">Configuração independente</span></div>';
            echo '<div class="ff-mobile-feature-grid">';
            foreach ($labels as $key => $label) {
                if (!in_array($key, $meta['applicable'], true)) continue;
                $value = !empty($s['feature_visibility'][$target][$key]) ? 1 : 0;
                echo '<label class="ff-mobile-feature"><input type="hidden" name="fieldflow_mobile[feature_visibility][' . esc_attr($target) . '][' . esc_attr($key) . ']" value="0"><input type="checkbox" name="fieldflow_mobile[feature_visibility][' . esc_attr($target) . '][' . esc_attr($key) . ']" value="1" ' . checked($value, 1, false) . '><span><strong>' . esc_html($label) . '</strong><small>Visível neste portal quando o utilizador tem permissão</small></span></label>';
            }
            echo '</div></section>';
        }
        echo '</div>';
        echo '<p class="description"><strong>Compatibilidade:</strong> o FieldFlow mantém internamente uma união das duas matrizes para os endpoints e builds antigas da app. A visibilidade das abas na WebView e o contexto de navegação passam a respeitar o portal escolhido.</p>';
        self::card_close();

        $runtime = RuntimeV3::get();
        $rollback = get_option(RuntimeV3::ROLLBACK_OPTION, []);
        self::card_open('Forms Runtime 3.0, piloto controlado', 'O modo shadow continua disponível. O modo piloto entrega ao Runtime 3.0 apenas condições, passos e navegação, mantendo uploads, rascunhos, validação, submissão e conclusão da visita no motor estável.');
        self::check('fieldflow_forms_runtime_v3[enabled]', 'Ativar Forms Runtime 3.0 para o universo autorizado', $runtime['enabled']);
        self::select('Modo de rollout', 'fieldflow_forms_runtime_v3[mode]', $runtime['mode'], [
            'shadow' => 'Shadow, observar sem alterar',
            'pilot' => 'Piloto, controlar condições e navegação',
        ]);
        self::check('fieldflow_forms_runtime_v3[native_only]', 'Executar apenas dentro da WebView Flutter', $runtime['native_only']);
        self::check('fieldflow_forms_runtime_v3[diagnostics]', 'Registar automaticamente incompatibilidades e fallbacks', $runtime['diagnostics']);
        self::check('fieldflow_forms_runtime_v3[debug_ui]', 'Mostrar indicador técnico nos formulários de teste', $runtime['debug_ui']);
        echo '<div class="ff-mobile-grid three compact">';
        self::input('IDs de utilizador autorizados', 'fieldflow_forms_runtime_v3[user_ids]', implode(',', $runtime['user_ids']));
        self::input('IDs de projeto autorizados', 'fieldflow_forms_runtime_v3[project_ids]', implode(',', $runtime['project_ids']));
        self::input('Amostragem, percentagem', 'fieldflow_forms_runtime_v3[sample_percent]', $runtime['sample_percent'], 'number');
        echo '</div>';
        echo '<p class="description"><strong>Proteção do piloto:</strong> o modo piloto exige sempre IDs de utilizador explícitos. Sem essa lista, a configuração é guardada automaticamente em shadow. Qualquer exceção ou divergência de cálculo faz fallback apenas nesse formulário, sem perder respostas.</p>';
        if (is_array($rollback) && !empty($rollback['at'])) {
            echo '<p class="description">Última reversão imediata: <strong>' . esc_html((string)$rollback['at']) . '</strong>.</p>';
        }
        echo '<p class="ff-mobile-save"><button class="button button-secondary" type="submit" onclick="if(!confirm(\'Desativar agora o Runtime 3.0 para todos os novos formulários?\')) return false; this.form.querySelector(\'[name=fieldflow_mobile_action]\').value=\'runtime_v3_rollback\';">Reversão imediata para o motor estável</button></p>';
        self::card_close();

        $pipeline = SubmissionPipeline::get();
        $pipelineRollback = get_option(SubmissionPipeline::ROLLBACK_OPTION, []);
        self::card_open('Submission Pipeline 1.0, rollout protegido', 'Unifica as tarefas posteriores à gravação entre web e mobile. A submissão e as respostas são sempre confirmadas primeiro. Shadow observa sem assumir o fluxo, e piloto exige utilizadores explícitos.');
        self::check('fieldflow_submission_pipeline[enabled]', 'Disponibilizar a nova pipeline ao universo autorizado', $pipeline['enabled']);
        self::select('Modo de rollout', 'fieldflow_submission_pipeline[mode]', $pipeline['mode'], [
            'shadow' => 'Shadow, manter o fluxo atual',
            'pilot' => 'Piloto, usar pipeline pós-commit',
        ]);
        echo '<div class="ff-mobile-grid two compact">';
        self::check('fieldflow_submission_pipeline[channels][web]', 'Aplicar ao portal web', in_array('web', $pipeline['channels'], true));
        self::check('fieldflow_submission_pipeline[channels][mobile]', 'Aplicar à API mobile e sincronização offline', in_array('mobile', $pipeline['channels'], true));
        echo '</div>';
        self::check('fieldflow_submission_pipeline[diagnostics]', 'Registar alertas quando uma tarefa pós-submissão fica pendente', $pipeline['diagnostics']);
        self::check('fieldflow_submission_pipeline[inline_mobile_history]', 'Preservar IDs de histórico na resposta mobile, sincronizando-os após o commit', $pipeline['inline_mobile_history']);
        echo '<div class="ff-mobile-grid three compact">';
        self::input('IDs de utilizador autorizados', 'fieldflow_submission_pipeline[user_ids]', implode(',', $pipeline['user_ids']));
        self::input('IDs de projeto autorizados', 'fieldflow_submission_pipeline[project_ids]', implode(',', $pipeline['project_ids']));
        self::input('Amostragem, percentagem', 'fieldflow_submission_pipeline[sample_percent]', $pipeline['sample_percent'], 'number');
        echo '</div>';
        echo '<p class="description"><strong>Proteção operacional:</strong> o piloto só assume submissões de utilizadores explicitamente autorizados. Se a lista estiver vazia, a configuração regressa automaticamente a shadow. Falhas posteriores ao commit geram reconciliação, nunca perda do reporte.</p>';
        if (is_array($pipelineRollback) && !empty($pipelineRollback['at'])) {
            echo '<p class="description">Última reversão imediata: <strong>' . esc_html((string)$pipelineRollback['at']) . '</strong>.</p>';
        }
        echo '<p class="ff-mobile-save"><button class="button button-secondary" type="submit" onclick="if(!confirm(\'Desativar agora a Submission Pipeline para web e mobile?\')) return false; this.form.querySelector(\'[name=fieldflow_mobile_action]\').value=\'submission_pipeline_rollback\';">Reversão imediata da Submission Pipeline</button></p>';
        self::card_close();

        self::save_button(); self::form_close();
    }

    private static function devices(): void {
        global $wpdb;
        $table = Auth::table();
        $rows = $wpdb->get_results("SELECT s.*, u.display_name, u.user_login, u.user_email FROM {$table} s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id ORDER BY COALESCE(s.last_seen_at,s.created_at) DESC LIMIT 250", ARRAY_A) ?: [];
        $blocked = Settings::blocked_devices();

        echo '<div class="ff-mobile-toolbar"><div><strong>' . count($rows) . '</strong> sessões recentes</div><form method="post">';
        wp_nonce_field('fieldflow_mobile_control_center', 'fieldflow_mobile_nonce');
        echo '<input type="hidden" name="fieldflow_mobile_action" value="cleanup"><button class="button">Limpar expiradas</button></form></div>';
        echo '<div class="ff-mobile-card table-card"><div class="ff-mobile-table-wrap"><table class="widefat striped ff-mobile-table"><thead><tr><th>Utilizador</th><th>Dispositivo</th><th>Plataforma</th><th>App</th><th>Última atividade</th><th>Estado</th><th>Ações</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="7">Ainda não existem sessões móveis.</td></tr>';
        foreach ($rows as $row) {
            $is_blocked = isset($blocked[(string)$row['device_id']]);
            $active = empty($row['revoked_at']) && strtotime((string)$row['refresh_expires_at']) > time() && !$is_blocked;
            $user = trim((string)($row['display_name'] ?: $row['user_login']));
            echo '<tr><td><strong>' . esc_html($user ?: ('#' . $row['user_id'])) . '</strong><br><small>' . esc_html((string)$row['user_email']) . '</small></td>';
            echo '<td><strong>' . esc_html((string)($row['device_name'] ?: 'Dispositivo')) . '</strong><br><code>' . esc_html((string)$row['device_id']) . '</code></td>';
            echo '<td>' . esc_html(strtoupper((string)($row['platform'] ?: 'n/d'))) . '<br><small>' . esc_html(self::os_from_agent((string)$row['user_agent'])) . '</small></td>';
            echo '<td>' . esc_html((string)($row['app_version'] ?: 'n/d')) . '</td>';
            echo '<td>' . esc_html(self::date((string)($row['last_seen_at'] ?: $row['created_at']))) . '<br><small>IP ' . esc_html((string)$row['last_ip']) . '</small></td>';
            echo '<td><span class="pill ' . ($active ? 'good' : ($is_blocked ? 'bad' : 'muted')) . '">' . esc_html($is_blocked ? 'Bloqueado' : ($active ? 'Ativo' : (!empty($row['revoked_at']) ? 'Revogado' : 'Expirado'))) . '</span></td>';
            echo '<td><div class="ff-mobile-actions">';
            if ($active) self::action_button('revoke_session', 'Revogar', ['session_id'=>$row['id']]);
            if ($is_blocked) self::action_button('unblock_device', 'Desbloquear', ['device_id'=>$row['device_id']]);
            else self::action_button('block_device', 'Bloquear', ['device_id'=>$row['device_id']], true);
            self::action_button('revoke_user', 'Revogar utilizador', ['user_id'=>$row['user_id']], true);
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function diagnostics(array $s): void {
        global $wpdb, $wp_rewrite;
        $tables = [Auth::table(), WebViewBridge::table(), $wpdb->prefix . 'routespro_mobile_mutations'];
        $checks = [];
        foreach ($tables as $table) {
            $checks[] = ['Base de dados: ' . preg_replace('/^' . preg_quote($wpdb->prefix, '/') . '/', '', $table), $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table, $table];
        }
        $rules = get_option('rewrite_rules', []);
        $has_rewrite = is_array($rules) && (isset($rules['^fieldflow-mobile/launch/?$']) || array_filter(array_keys($rules), function($k){ return strpos($k, 'fieldflow-mobile/launch') !== false; }));
        $checks[] = ['HTTPS', is_ssl() || strpos(home_url('/'), 'https://') === 0, home_url('/')];
        $checks[] = ['Rewrite da bridge', (bool)$has_rewrite, home_url('/fieldflow-mobile/launch/')];
        $checks[] = ['Cron de sessões', (bool)wp_next_scheduled(Auth::CLEANUP_HOOK), Auth::CLEANUP_HOOK];
        $checks[] = ['Cron da WebView', (bool)wp_next_scheduled(WebViewBridge::CLEANUP_HOOK), WebViewBridge::CLEANUP_HOOK];
        $checks[] = ['Bridge ativa', Settings::bridge_enabled(), rest_url('fieldflow-mobile/v1/auth/web-session')];
        $checks[] = ['Android permitido', Settings::platform_enabled('android'), $s['platforms']['android']['latest_version']];
        $checks[] = ['iOS permitido', Settings::platform_enabled('ios'), $s['platforms']['ios']['latest_version']];
        $checks[] = ['Política de privacidade', !empty($s['general']['privacy_url']), $s['general']['privacy_url'] ?: 'Por configurar'];
        $checks[] = ['JavaScript da bridge', !empty($s['bridge']['javascript_enabled']), !empty($s['bridge']['javascript_enabled']) ? 'Ativo' : 'Desativado: contexto, heartbeat e viewer não serão injetados'];
        $checks[] = ['Renovação automática', !empty($s['bridge']['auto_renew']), !empty($s['bridge']['auto_renew']) ? 'Heartbeat a cada ' . (int)$s['bridge']['heartbeat_seconds'] . ' segundos' : 'Desativada'];
        $checks[] = ['Domínios autorizados', !empty(Settings::allowed_domains()), implode(', ', Settings::allowed_domains()) ?: 'Nenhum domínio configurado'];
        $upload_max = (string)ini_get('upload_max_filesize');
        $post_max = (string)ini_get('post_max_size');
        $max_files = max(0, (int)ini_get('max_file_uploads'));
        $checks[] = ['Uploads PHP', filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN), ini_get('file_uploads') ? 'Ativos para fallback e documentos' : 'Desativados no servidor'];
        $checks[] = ['Fotografias FieldFlow', true, 'Sem limite artificial de MB; transporte retomável em blocos de 512 KB'];
        $checks[] = ['Limites PHP tradicionais', true, $upload_max . ' por ficheiro / ' . $post_max . ' por pedido; não limitam o fluxo retomável normal'];
        $checks[] = ['Numero maximo PHP no fallback', $max_files >= 10, (string)$max_files . ' (o transporte retomável envia cada fotografia separadamente)'];
        $checks[] = ['Uploads multiplos na WebView', Settings::bridge_flag('allow_multiple_uploads', true), Settings::bridge_flag('allow_multiple_uploads', true) ? 'Ativos' : 'Desativados'];
        $checks[] = ['Camara na WebView', Settings::bridge_flag('allow_camera', true), Settings::bridge_flag('allow_camera', true) ? 'Ativa' : 'Desativada'];
        $checks[] = ['Galeria na WebView', Settings::bridge_flag('allow_gallery', true), Settings::bridge_flag('allow_gallery', true) ? 'Ativa' : 'Desativada'];
        $checks[] = ['Execucao de tarefas posteriores', !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON), (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) ? 'WP-Cron desativado: confirmar cron real do servidor' : 'WP-Cron ativo'];

        self::card_open('Diagnóstico técnico', 'Verificações locais, sem expor tokens nem dados de formulários.');
        echo '<div class="ff-mobile-diagnostics">';
        foreach ($checks as [$label,$ok,$detail]) {
            echo '<div class="diag ' . ($ok ? 'ok' : 'warn') . '"><span>' . ($ok ? '✓' : '!') . '</span><div><strong>' . esc_html($label) . '</strong><small>' . esc_html((string)$detail) . '</small></div></div>';
        }
        echo '</div>';
        self::card_close();

        self::card_open('Resposta esperada do app-config', 'Use platform e app_version para obter regras específicas da plataforma.');
        echo '<pre class="ff-mobile-json">' . esc_html(wp_json_encode([
            'endpoint' => rest_url('fieldflow-mobile/v1/app-config?platform=android&app_version=1.0.1'),
            'maintenance_mode' => Settings::maintenance_enabled('android'),
            'minimum_app_version' => $s['platforms']['android']['minimum_version'],
            'latest_app_version' => $s['platforms']['android']['latest_version'],
            'bridge_enabled' => Settings::bridge_enabled(),
            'javascript_enabled' => Settings::bridge_flag('javascript_enabled', true),
            'auto_renew' => Settings::bridge_flag('auto_renew', true),
            'heartbeat_seconds' => Settings::heartbeat_seconds(),
            'allow_downloads' => Settings::bridge_flag('allow_downloads', true),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>';
        self::card_close();
    }

    private static function styles(): void {
        echo '<style>
        .ff-mobile-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.ff-mobile-tabs a{padding:10px 14px;border:1px solid #dbe3ee;border-radius:999px;text-decoration:none;background:#fff;color:#334155;font-weight:700}.ff-mobile-tabs a.is-active{background:#0f172a;color:#fff;border-color:#0f172a}.ff-mobile-shell{max-width:1500px}.ff-mobile-hero{display:flex;justify-content:space-between;gap:24px;align-items:center;padding:28px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;box-shadow:0 18px 50px rgba(15,23,42,.16)}.ff-mobile-hero h2{font-size:28px;margin:5px 0 8px;color:#fff}.ff-mobile-hero p{margin:0;color:#dbeafe}.ff-mobile-eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:11px;font-weight:800;color:#93c5fd}.ff-mobile-status{display:flex;align-items:center;gap:9px;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.12);font-weight:800;white-space:nowrap}.ff-mobile-status span{width:9px;height:9px;border-radius:50%;background:#ef4444}.ff-mobile-status.ok span{background:#22c55e}.ff-mobile-metrics{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px;margin:18px 0}.ff-mobile-metric,.ff-mobile-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 9px 30px rgba(15,23,42,.05)}.ff-mobile-metric{padding:18px}.ff-mobile-metric strong{display:block;font-size:24px;color:#0f172a}.ff-mobile-metric span{display:block;font-weight:800;color:#334155;margin-top:5px}.ff-mobile-metric small{color:#64748b}.ff-mobile-grid{display:grid;gap:18px;margin:18px 0}.ff-mobile-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-mobile-grid.four{grid-template-columns:repeat(4,minmax(0,1fr))}.ff-mobile-card{padding:22px}.ff-mobile-card h2{margin:0;color:#0f172a}.ff-mobile-card>.description{margin:5px 0 18px;color:#64748b}.ff-mobile-platform{padding:14px 0;border-top:1px solid #eef2f7}.ff-mobile-platform:first-of-type{border-top:0}.ff-mobile-platform .pill{float:right}.ff-mobile-platform dl{display:grid;grid-template-columns:1fr auto;gap:8px;margin:12px 0 0}.ff-mobile-platform dt{color:#64748b}.ff-mobile-platform dd{margin:0;font-weight:800}.pill{display:inline-block;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}.pill.good{background:#dcfce7;color:#166534}.pill.bad{background:#fee2e2;color:#991b1b}.pill.muted{background:#f1f5f9;color:#64748b}.ff-mobile-endpoints div{display:grid;grid-template-columns:120px 1fr;gap:10px;padding:8px 0;border-top:1px solid #eef2f7}.ff-mobile-endpoints div:first-child{border-top:0}.ff-mobile-endpoints code{overflow-wrap:anywhere}.ff-mobile-field{margin:0 0 16px}.ff-mobile-field label{display:block;font-weight:800;color:#334155;margin-bottom:6px}.ff-mobile-field input:not([type=checkbox]),.ff-mobile-field select,.ff-mobile-field textarea{width:100%;max-width:none;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px}.ff-mobile-check{display:flex;gap:10px;align-items:center;padding:9px 0;font-weight:700;color:#334155}.ff-mobile-check input{width:18px;height:18px}.ff-mobile-save{margin:18px 0}.ff-mobile-portal-feature-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.ff-mobile-portal-feature-card{border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#fff}.ff-mobile-portal-feature-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:14px}.ff-mobile-portal-feature-head h3{margin:3px 0 4px;color:#0f172a;font-size:18px}.ff-mobile-portal-feature-head p{margin:0;color:#64748b}.ff-mobile-feature-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ff-mobile-feature{display:flex;gap:12px;align-items:center;border:1px solid #e2e8f0;border-radius:15px;padding:14px;background:#f8fafc}.ff-mobile-feature.is-not-applicable{opacity:.5;background:#f8fafc}.ff-mobile-feature.is-not-applicable input{cursor:not-allowed}.ff-mobile-feature input{width:18px;height:18px}.ff-mobile-feature span,.ff-mobile-feature small{display:block}.ff-mobile-feature small{color:#64748b;margin-top:3px}.ff-mobile-toolbar{display:flex;justify-content:space-between;align-items:center;margin:15px 0}.table-card{padding:0;overflow:hidden}.ff-mobile-table-wrap{overflow:auto}.ff-mobile-table td{vertical-align:middle}.ff-mobile-table code{font-size:10px;word-break:break-all}.ff-mobile-actions{display:flex;gap:5px;flex-wrap:wrap}.ff-mobile-actions form{margin:0}.ff-mobile-actions .button.danger{color:#b91c1c;border-color:#fecaca}.ff-mobile-diagnostics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.diag{display:flex;gap:12px;align-items:center;padding:14px;border-radius:15px;background:#f8fafc;border:1px solid #e2e8f0}.diag>span{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:#fef3c7;color:#92400e;font-weight:900}.diag.ok>span{background:#dcfce7;color:#166534}.diag strong,.diag small{display:block}.diag small{margin-top:3px;color:#64748b;overflow-wrap:anywhere}.ff-mobile-json{padding:16px;border-radius:14px;background:#0f172a;color:#dbeafe;overflow:auto}.compact{margin:0}@media(max-width:1100px){.ff-mobile-metrics{grid-template-columns:repeat(3,1fr)}.ff-mobile-grid.four{grid-template-columns:repeat(2,1fr)}.ff-mobile-portal-feature-grid{grid-template-columns:1fr}}@media(max-width:782px){.ff-mobile-hero{align-items:flex-start;flex-direction:column}.ff-mobile-metrics,.ff-mobile-grid.two,.ff-mobile-grid.four,.ff-mobile-feature-grid,.ff-mobile-diagnostics{grid-template-columns:1fr}.ff-mobile-tabs{overflow:auto;flex-wrap:nowrap}.ff-mobile-tabs a{white-space:nowrap}}
        </style>';
    }

    private static function form_open(string $tab): void {
        echo '<form method="post">';
        wp_nonce_field('fieldflow_mobile_control_center', 'fieldflow_mobile_nonce');
        echo '<input type="hidden" name="fieldflow_mobile_action" value="save"><input type="hidden" name="fieldflow_mobile_tab" value="' . esc_attr($tab) . '">';
    }
    private static function form_close(): void { echo '</form>'; }
    private static function save_button(): void { echo '<p class="ff-mobile-save"><button class="button button-primary button-hero">Guardar configuração móvel</button></p>'; }
    private static function card_open(string $title, string $description = ''): void { echo '<section class="ff-mobile-card"><h2>' . esc_html($title) . '</h2>' . ($description ? '<p class="description">' . esc_html($description) . '</p>' : ''); }
    private static function card_close(): void { echo '</section>'; }
    private static function metric(string $label, $value, string $description): void { echo '<div class="ff-mobile-metric"><strong>' . esc_html((string)$value) . '</strong><span>' . esc_html($label) . '</span><small>' . esc_html($description) . '</small></div>'; }
    private static function input(string $label, string $name, $value, string $type = 'text'): void { echo '<div class="ff-mobile-field"><label>' . esc_html($label) . '</label><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string)$value) . '"></div>'; }
    private static function textarea(string $label, string $name, $value): void { echo '<div class="ff-mobile-field"><label>' . esc_html($label) . '</label><textarea rows="4" name="' . esc_attr($name) . '">' . esc_textarea((string)$value) . '</textarea></div>'; }
    private static function select(string $label, string $name, $value, array $options): void { echo '<div class="ff-mobile-field"><label>' . esc_html($label) . '</label><select name="' . esc_attr($name) . '">'; foreach ($options as $k=>$v) echo '<option value="' . esc_attr($k) . '" ' . selected($value, $k, false) . '>' . esc_html($v) . '</option>'; echo '</select></div>'; }
    private static function check(string $name, string $label, $checked): void { echo '<label class="ff-mobile-check"><input type="hidden" name="' . esc_attr($name) . '" value="0"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, 1, false) . '> ' . esc_html($label) . '</label>'; }
    private static function action_button(string $action, string $label, array $fields, bool $danger = false): void { echo '<form method="post">'; wp_nonce_field('fieldflow_mobile_control_center', 'fieldflow_mobile_nonce'); echo '<input type="hidden" name="fieldflow_mobile_action" value="' . esc_attr($action) . '">'; foreach ($fields as $k=>$v) echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string)$v) . '">'; echo '<button class="button button-small ' . ($danger ? 'danger' : '') . '" onclick="return confirm(\'Confirmar esta ação?\')">' . esc_html($label) . '</button></form>'; }
    private static function count(string $sql, array $args = []): int { global $wpdb; return (int)$wpdb->get_var($args ? $wpdb->prepare($sql, ...$args) : $sql); }
    private static function date(string $value): string { if (!$value) return 'n/d'; $ts = strtotime($value . ' UTC'); return $ts ? wp_date('d/m/Y H:i', $ts) : $value; }
    private static function os_from_agent(string $ua): string { if (preg_match('/Android\s+([0-9.]+)/i', $ua, $m)) return 'Android ' . $m[1]; if (preg_match('/OS\s+([0-9_]+)/i', $ua, $m)) return 'iOS ' . str_replace('_', '.', $m[1]); return ''; }
}
