<?php
namespace RoutesPro\Admin;

use RoutesPro\Cloud\ClientOnboardingCertification;
use RoutesPro\Cloud\LicenseTenantFoundation;
use RoutesPro\Cloud\LicenseInstallationLifecycle;
use RoutesPro\Cloud\LicenseGovernanceLifecycle;
use RoutesPro\Cloud\PrivateUpdateLifecycle;
use RoutesPro\Cloud\Phase6StableRelease;
use RoutesPro\SaaS\PlanRepository;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

final class LicenseTenants {
    public const SLUG = 'routespro-license-tenants';

    public static function render(): void {
        if (!current_user_can('routespro_manage')) return;
        $notice = '';
        $noticeType = 'updated';
        $oneTimeKey = '';
        $oneTimeTenant = 0;
        $rotationKey = '';
        $rotationTenant = 0;

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST['ff_6b_action'])) {
            check_admin_referer('fieldflow_phase6b_action', 'fieldflow_phase6b_nonce');
            $action = sanitize_key((string)$_POST['ff_6b_action']);
            $tenantId = absint($_POST['tenant_id'] ?? 0);
            if ($action === 'create_tenant') {
                $result = LicenseTenantFoundation::createTenant([
                    'client_id'=>$_POST['client_id'] ?? 0,
                    'plan_slug'=>$_POST['plan_slug'] ?? 'enterprise',
                    'max_installations'=>$_POST['max_installations'] ?? 1,
                    'expires_at'=>$_POST['expires_at'] ?? '',
                    'notes'=>$_POST['notes'] ?? '',
                ]);
                if (is_wp_error($result)) {
                    $notice = $result->get_error_message();
                    $noticeType = 'error';
                } else {
                    $tenantId = (int)$result;
                    $notice = 'Tenant de licenciamento criado em draft. Nenhuma instalação foi bloqueada ou ativada.';
                }
            } elseif ($action === 'issue_license') {
                $result = LicenseTenantFoundation::issueRemoteLicense($tenantId);
                if (is_wp_error($result)) {
                    $notice = $result->get_error_message();
                    $noticeType = 'error';
                } else {
                    $oneTimeTenant = $tenantId;
                    $oneTimeKey = LicenseTenantFoundation::consumeOneTimeKey($tenantId);
                    $notice = 'Chave remota emitida. Copia-a agora, o valor bruto não fica guardado no registry do FieldFlow.';
                }
            } elseif ($action === 'probe_remote_api') {
                $result = LicenseTenantFoundation::probeRemoteApi();
                if (!empty($result['ok'])) {
                    $notice = 'API remota 6B.1 validada por probe real, sem emitir nem alterar licenças.';
                    $noticeType = 'updated';
                } else {
                    $notice = 'Probe remoto 6B.1 falhou: ' . sanitize_text_field((string)($result['message'] ?? 'ver diagnóstico abaixo.'));
                    $noticeType = 'error';
                }
            } elseif ($action === 'certify_foundation') {
                $result = LicenseTenantFoundation::certifyFoundation(get_current_user_id());
                if (!empty($result['ok'])) {
                    $notice = 'Foundation 6B.1 certificada. O enforcement continua desligado.';
                    $noticeType = 'updated';
                } else {
                    $failed = self::failedCheckKeys((array)($result['checks'] ?? []));
                    $notice = 'Ainda existem pré-condições da 6B.1 por concluir: ' . implode(', ', $failed) . '.';
                    $noticeType = 'error';
                }
            } elseif ($action === 'reset_foundation') {
                LicenseTenantFoundation::resetFoundation();
                $notice = 'Certificação 6B.1 reiniciada. Nenhuma licença ou instalação foi revogada.';
            } elseif ($action === 'heartbeat_6b2') {
                $result = LicenseInstallationLifecycle::heartbeat(true, 'admin_manual');
                if (!empty($result['accepted'])) {
                    $notice = 'Heartbeat 6B.2 aceite pela API remota. A instalação continua em modo observacional, sem enforcement.';
                    $noticeType = 'updated';
                } else {
                    $notice = 'Heartbeat 6B.2 não ficou verde: ' . sanitize_text_field((string)($result['message'] ?? 'ver diagnóstico abaixo.'));
                    $noticeType = 'error';
                }
            } elseif ($action === 'certify_6b2') {
                $result = LicenseInstallationLifecycle::certify(get_current_user_id());
                $notice = !empty($result['ok']) ? 'Fase 6B.2 certificada. Binding obrigatório, revogação e enforcement continuam desligados.' : 'Ainda existem pré-condições da 6B.2 por concluir.';
                $noticeType = !empty($result['ok']) ? 'updated' : 'error';
            } elseif ($action === 'reset_6b2') {
                LicenseInstallationLifecycle::resetCertification();
                $notice = 'Certificação 6B.2 reiniciada. Nenhuma instalação foi bloqueada, desativada ou revogada.';
            } elseif ($action === 'test_6b3') {
                $result = LicenseGovernanceLifecycle::testCurrentGovernance();
                if (!empty($result['ok'])) {
                    $notice = 'Governance 6B.3 validada e policy sincronizada. O enforcement continua em SHADOW.';
                    $noticeType = 'updated';
                } else {
                    $notice = 'Governance 6B.3 ainda não ficou verde: ' . sanitize_text_field((string)($result['message'] ?? 'ver diagnóstico abaixo.'));
                    $noticeType = 'error';
                }
            } elseif ($action === 'certify_6b3') {
                $result = LicenseGovernanceLifecycle::certify(get_current_user_id());
                $notice = !empty($result['ok']) ? 'Fase 6B.3 certificada em SHADOW. Revogação e limites remotos estão disponíveis, mas o runtime continua sem bloqueio.' : 'Ainda existem pré-condições da 6B.3 por concluir.';
                $noticeType = !empty($result['ok']) ? 'updated' : 'error';
            } elseif ($action === 'reset_6b3') {
                LicenseGovernanceLifecycle::resetCertification();
                $notice = 'Certificação 6B.3 reiniciada. Nenhuma licença ou instalação foi alterada.';
            } elseif ($action === 'sync_policy_6b3') {
                $result = LicenseGovernanceLifecycle::syncTenantPolicy($tenantId);
                if (is_wp_error($result)) { $notice = $result->get_error_message(); $noticeType = 'error'; }
                else { $notice = 'Policy 6B.3 sincronizada com o serviço central.'; }
            } elseif (in_array($action, ['suspend_6b3','resume_6b3','revoke_6b3'], true)) {
                $operation = str_replace('_6b3', '', $action);
                $result = LicenseGovernanceLifecycle::governTenant($tenantId, $operation);
                if (is_wp_error($result)) { $notice = $result->get_error_message(); $noticeType = 'error'; }
                else { $notice = 'Governance remota executada: ' . strtoupper($operation) . '. O runtime local continua em SHADOW.'; }
            } elseif ($action === 'rotate_6b3') {
                $result = LicenseGovernanceLifecycle::rotateTenantLicense($tenantId);
                if (is_wp_error($result)) { $notice = $result->get_error_message(); $noticeType = 'error'; }
                else {
                    $rotationTenant = $tenantId;
                    $rotationKey = LicenseGovernanceLifecycle::consumeOneTimeRotationKey($tenantId);
                    $notice = 'Licença rodada. Copia a nova key agora e atualiza o site de destino. O valor bruto não fica persistido.';
                }
                       } elseif ($action === 'set_channel_6b4') {
                $channel = PrivateUpdateLifecycle::setChannel((string)($_POST['update_channel'] ?? 'stable'));
                $notice = 'Canal privado de updates definido como ' . strtoupper($channel) . '. Nenhuma atualização foi instalada.';
            } elseif ($action === 'test_6b4') {
                $result = PrivateUpdateLifecycle::testCurrentDistribution();
                if (!empty($result['ok'])) {
                    $notice = 'Distribuição privada 6B.4 validada ponta a ponta. O pacote foi descarregado apenas para verificação SHA-256 e não foi instalado.';
                    $noticeType = 'updated';
                } else {
                    $notice = 'Distribuição privada 6B.4 ainda não ficou verde: ' . sanitize_text_field((string)($result['message'] ?? 'ver diagnóstico abaixo.'));
                    $noticeType = 'error';
                }
            } elseif ($action === 'certify_6b4') {
                $result = PrivateUpdateLifecycle::certify(get_current_user_id());
                $notice = !empty($result['ok']) ? 'Fase 6B.4 certificada. Distribuição privada e updates WordPress licenciados estão prontos, sem auto-update forçado.' : 'Ainda existem pré-condições da 6B.4 por concluir.';
                $noticeType = !empty($result['ok']) ? 'updated' : 'error';
            } elseif ($action === 'reset_6b4') {
                PrivateUpdateLifecycle::resetCertification();
                $notice = 'Certificação 6B.4 reiniciada. Nenhuma release, licença ou instalação foi alterada.';
            } elseif ($action === 'revalidate_phase6_chain') {
                $result = Phase6StableRelease::revalidateCertificationChain(get_current_user_id());
                $notice = !empty($result['ok']) ? 'Cadeia 6B revalidada após o update privado. 6B.2, 6B.3 e 6B.4 voltaram a ser assinadas a partir do estado live atual.' : 'A revalidação da cadeia 6B parou em ' . sanitize_text_field((string)($result['stage'] ?? 'diagnóstico')) . ': ' . sanitize_text_field((string)($result['message'] ?? 'ver diagnóstico.'));
                $noticeType = !empty($result['ok']) ? 'updated' : 'error';
            } elseif ($action === 'test_phase6_stable') {
                $result = Phase6StableRelease::localProbe(true);
                if (!empty($result['ok'])) {
                    $notice = 'Fecho Stable da Fase 6 validado para a release ' . Phase6StableRelease::STABLE_RELEASE . '. Distribuição privada, identidade, governance e hard locks permanecem coerentes.';
                    $noticeType = 'updated';
                } else {
                    $notice = 'O fecho Stable da Fase 6 ainda tem pré-condições por rever: ' . self::phase6FailureSummary($result);
                    $noticeType = 'error';
                }
            } elseif ($action === 'certify_phase6_stable') {
                $result = Phase6StableRelease::certify(get_current_user_id());
                if (!empty($result['ok'])) {
                    $notice = 'Fase 6 Stable certificada. A baseline ' . Phase6StableRelease::STABLE_RELEASE . ' está fechada com production e runtime enforcement ainda desligados.';
                    $noticeType = 'updated';
                } else {
                    $notice = 'Não foi possível certificar a Fase 6 Stable: ' . self::phase6FailureSummary($result);
                    $noticeType = 'error';
                }
            } elseif ($action === 'reset_phase6_stable') {
                Phase6StableRelease::reset();
                $notice = 'Certificação Stable da Fase 6 reiniciada. Licenças, instalações e releases privadas foram preservadas.';
            }
        }

        $health = LicenseTenantFoundation::health();
        $probe = LicenseTenantFoundation::localProbe();
        $phase6a = ClientOnboardingCertification::health();
        $tenants = LicenseTenantFoundation::listTenants();
        $clients = self::clients();
        $plans = PlanRepository::plans(true);
        $lifecycleHealth = LicenseInstallationLifecycle::health();
        $lifecycleProbe = LicenseInstallationLifecycle::localProbe();
        $governanceHealth = LicenseGovernanceLifecycle::health();
        $governanceProbe = LicenseGovernanceLifecycle::localProbe();
        $updateHealth = PrivateUpdateLifecycle::health();
        $updateProbe = PrivateUpdateLifecycle::localProbe();
        $phase6StableHealth = Phase6StableRelease::health();
        // 9DJ: GET is display-only. Avoid a second forced 1630-file scan and
        // invalidation of all 6B health snapshots already obtained above.
        // POST certification/actions still force the authoritative fresh scan.
        $phase6StableProbe = Phase6StableRelease::localProbe(false,
            strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET');
        $installations = LicenseTenantFoundation::listInstallations();

        $foundationState = self::certificationVisualState(!empty($health['foundation_certified']), !empty($health['certification_state_present']));
        $lifecycleState = self::certificationVisualState(!empty($lifecycleHealth['certified']), !empty($lifecycleHealth['certification_state_present']));
        $governanceState = self::certificationVisualState(!empty($governanceHealth['certified']), !empty($governanceHealth['certification_state_present']));
        $updateState = self::certificationVisualState(!empty($updateHealth['certified']), !empty($updateHealth['certification_state_present']));
        $stableState = self::certificationVisualState(!empty($phase6StableHealth['stable_certified']), !empty($phase6StableHealth['certification_state_present']));

        AdminPage::open('Licenciamento & Tenants', 'Fases 6B.1 a 6B.4 e fecho Stable da Fase 6, licenças, instalações, heartbeat, governance e distribuição privada de updates.');
        self::styles();
        if ($notice !== '') AdminPage::notice($notice, $noticeType);

        if ($oneTimeKey !== '') {
            echo '<div class="notice notice-warning ff6b-once"><p><strong>Chave de ativação, apresentação única</strong></p>';
            echo '<div class="ff6b-keyrow"><input id="ff6b-once-key" type="text" readonly value="' . esc_attr($oneTimeKey) . '"><button type="button" class="button button-primary" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById(\'ff6b-once-key\').value)">Copiar chave</button></div>';
            echo '<p class="description">Tenant #' . (int)$oneTimeTenant . '. Depois de saíres desta página o valor bruto deixa de estar disponível no FieldFlow. O registry guarda apenas máscara e SHA-256.</p></div>';
        }

        if ($rotationKey !== '') {
            echo '<div class="notice notice-warning ff6b-once"><p><strong>Nova chave após rotação, apresentação única</strong></p>';
            echo '<div class="ff6b-keyrow"><input id="ff6b-rotation-key" type="text" readonly value="' . esc_attr($rotationKey) . '"><button type="button" class="button button-primary" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById(\'ff6b-rotation-key\').value)">Copiar nova chave</button></div>';
            echo '<p class="description">Tenant #' . (int)$rotationTenant . '. A key anterior foi marcada como substituída no serviço central. Atualiza a instalação de destino com esta nova key.</p></div>';
        }

        echo '<div class="ff6b-hero"><div><span class="ff6b-kicker">PHASE 6B.4</span><h2>Private Distribution & Updates</h2><p>A 6B.3 está certificada. A 6B.4 fecha a distribuição comercial: catálogo privado por licença, canais stable/beta, manifesto HMAC, pacote SHA-256 e integração nativa com os updates do WordPress. O FieldFlow não força auto-updates e nunca oferece downgrades automaticamente.</p></div><div class="ff6b-badges">';
        self::badge('6B.3 Governance', !empty($governanceHealth['certified']) ? 'CERTIFICADA' : 'REVER', !empty($governanceHealth['certified']));
        self::badge('Update API', !empty($updateHealth['phase_6b4_acknowledged']) ? 'READY' : 'TESTAR', !empty($updateHealth['phase_6b4_acknowledged']));
        self::badge('Package', !empty($updateHealth['package_hash_verified']) ? 'SHA-256 OK' : 'REVER', !empty($updateHealth['package_hash_verified']));
        self::badge('Updates', 'MANUAL SAFE', empty($updateHealth['forced_auto_updates']));
        echo '</div></div>';

        if ((!empty($health['certification_state_present']) && empty($health['foundation_certified']))
            || (!empty($lifecycleHealth['certification_state_present']) && empty($lifecycleHealth['certified']))
            || (!empty($governanceHealth['certification_state_present']) && empty($governanceHealth['certified']))
            || (!empty($updateHealth['certification_state_present']) && empty($updateHealth['certified']))
            || (!empty($phase6StableHealth['certification_state_present']) && empty($phase6StableHealth['stable_certified']))) {
            echo '<div class="notice notice-warning inline"><p><strong>Certificação histórica preservada.</strong> Esta release alterou o fingerprint técnico e requer revalidação pós-update. As fases já certificadas deixam de aparecer como primeira certificação pendente e passam a indicar <strong>CERTIFICADA, REVALIDAR</strong> até a cadeia ser revalidada.</p></div>';
        }

        echo '<div class="ff6b-metrics">';
        self::metric('Tenants', (string)$health['tenants_total'], 'Clientes registados na 6B');
        self::metric('Draft', (string)$health['tenants_draft'], 'Ainda sem chave emitida');
        self::metric('Licenças emitidas', (string)$health['tenants_issued'], 'Valor bruto não persistido');
        self::metric('Instalações', (string)$health['installations_total'], 'Ativas: ' . (int)$health['installations_active']);
        echo '</div>';

        echo '<section class="ff6b-card"><div class="ff6b-card-head"><div><span class="ff6b-kicker">FOUNDATION GATE</span><h3>Diagnóstico e certificação 6B.1</h3></div><span class="ff6b-state ' . esc_attr($foundationState['class']) . '">' . esc_html($foundationState['label']) . '</span></div><div class="ff6b-checks">';
        foreach ((array)($probe['checks'] ?? []) as $key=>$ok) self::check((string)$key, (bool)$ok);
        echo '</div>';
        if (empty($health['remote_api_base_configured']) || empty($health['remote_shared_secret_configured']) || empty($health['remote_admin_secret_configured'])) {
            echo '<div class="ff6b-config-hint"><strong>API remota por completar.</strong><span>Abre Configurações para validar API Base, Shared Secret e Admin Secret. Os valores secretos continuam tratados pelo cofre de credenciais existente.</span><a class="button" href="' . esc_url(admin_url('admin.php?page=routespro-settings')) . '">Abrir Configurações</a></div>';
        } else {
            self::remoteProbePanel($health);
        }
        echo '<div class="ff6b-actions">';
        self::action('probe_remote_api', 0, 'Testar API remota 6B.1', 'secondary');
        self::action('certify_foundation', 0, !empty($health['certification_state_present']) && empty($health['foundation_certified']) ? 'Revalidar Foundation 6B.1' : 'Certificar Foundation 6B.1', 'primary');
        self::action('reset_foundation', 0, 'Reiniciar gate 6B.1', 'secondary', 'Reiniciar apenas a certificação 6B.1? As licenças emitidas não são apagadas.');
        echo '</div><p class="description">A certificação valida a infraestrutura de licenciamento, não ativa enforcement, binding obrigatório nem revogação remota.</p></section>';

        echo '<section class="ff6b-card"><div class="ff6b-card-head"><div><span class="ff6b-kicker">PHASE 6B.2 GATE</span><h3>Ativação, identidade e heartbeat</h3></div><span class="ff6b-state ' . esc_attr($lifecycleState['class']) . '">' . esc_html($lifecycleState['label']) . '</span></div>';
        echo '<div class="ff6b-checks">';
        foreach ((array)($lifecycleProbe['checks'] ?? []) as $key=>$ok) self::check((string)$key, (bool)$ok);
        if (!empty($lifecycleHealth['certification_state_present']) && empty($lifecycleHealth['certified'])) self::check('certification_fingerprint_match', !empty($lifecycleHealth['certification_fingerprint_match']));
        echo '</div><div class="ff6b-install-now"><div><small>Installation UUID</small><code>' . esc_html((string)($lifecycleHealth['installation_uuid'] ?? '')) . '</code></div><div><small>Domínio</small><strong>' . esc_html((string)($lifecycleHealth['current_domain'] ?? '')) . '</strong></div><div><small>Activation ID</small><code>' . esc_html(((string)\RoutesPro\Support\LicenseManager::get('remote_activation_id', '')) ?: 'por ativar') . '</code></div><div><small>Último heartbeat</small><strong>' . esc_html((string)($lifecycleHealth['last_heartbeat_success_at'] ?? '') ?: 'Ainda sem sucesso') . '</strong></div></div>';
        echo '<div class="ff6b-actions">';
        self::action('heartbeat_6b2', 0, 'Executar heartbeat 6B.2', 'secondary');
        self::action('certify_6b2', 0, !empty($lifecycleHealth['certification_state_present']) && empty($lifecycleHealth['certified']) ? 'Revalidar Fase 6B.2' : 'Certificar Fase 6B.2', 'primary');
        self::action('reset_6b2', 0, 'Reiniciar gate 6B.2', 'secondary', 'Reiniciar apenas a certificação 6B.2? A licença e a instalação não serão desativadas.');
        echo '</div><p class="description">O heartbeat usa a rota de validação existente por retrocompatibilidade. Falhas remotas ficam visíveis no diagnóstico, mas não desativam nem bloqueiam o FieldFlow nesta fase.</p></section>';

        echo '<section class="ff6b-card"><div class="ff6b-card-head"><div><span class="ff6b-kicker">PHASE 6B.3 GATE</span><h3>Governance, rotação, limites e shadow enforcement</h3></div><span class="ff6b-state ' . esc_attr($governanceState['class']) . '">' . esc_html($governanceState['label']) . '</span></div>';
        echo '<div class="ff6b-checks">';
        foreach ((array)($governanceProbe['checks'] ?? []) as $key=>$ok) self::check((string)$key, (bool)$ok);
        if (!empty($governanceHealth['certification_state_present']) && empty($governanceHealth['certified'])) self::check('certification_fingerprint_match', !empty($governanceHealth['certification_fingerprint_match']));
        echo '</div><div class="ff6b-install-now"><div><small>Estado remoto</small><strong>' . esc_html(strtoupper((string)($governanceHealth['remote_status'] ?? 'por testar'))) . '</strong></div><div><small>Policy</small><code>' . esc_html(substr((string)($governanceHealth['remote_policy_fingerprint_sha256'] ?? ''), 0, 18) ?: 'por sincronizar') . '</code></div><div><small>Instalações</small><strong>' . (int)($governanceHealth['remote_active_activations'] ?? 0) . ' / ' . (int)($governanceHealth['max_installations'] ?? 0) . '</strong></div><div><small>Grace period futuro</small><strong>' . (int)($governanceHealth['grace_period_hours'] ?? 72) . 'h, SHADOW</strong></div></div>';
        echo '<div class="ff6b-actions">';
        self::action('test_6b3', 0, 'Sincronizar e testar 6B.3', 'secondary');
        self::action('certify_6b3', 0, !empty($governanceHealth['certification_state_present']) && empty($governanceHealth['certified']) ? 'Revalidar Fase 6B.3' : 'Certificar Fase 6B.3', 'primary');
        self::action('reset_6b3', 0, 'Reiniciar gate 6B.3', 'secondary', 'Reiniciar apenas a certificação 6B.3? Nenhuma licença será suspensa, revogada ou rodada.');
        echo '</div><p class="description">Suspensão, revogação e rotação existem no control plane, mas nesta release o runtime permanece fail-open e SHADOW. Uma decisão remota de bloqueio é apenas observada e auditada.</p></section>';

        echo '<section class="ff6b-card"><div class="ff6b-card-head"><div><span class="ff6b-kicker">PHASE 6B.4 GATE</span><h3>Distribuição privada e updates licenciados</h3></div><span class="ff6b-state ' . esc_attr($updateState['class']) . '">' . esc_html($updateState['label']) . '</span></div>';
        echo '<div class="ff6b-checks">';
        foreach ((array)($updateProbe['checks'] ?? []) as $key=>$ok) self::check((string)$key, (bool)$ok);
        if (!empty($updateHealth['certification_state_present']) && empty($updateHealth['certified'])) self::check('certification_fingerprint_match', !empty($updateHealth['certification_fingerprint_match']));
        echo '</div><div class="ff6b-install-now"><div><small>Canal</small><strong>' . esc_html(strtoupper((string)($updateHealth['current_channel'] ?? 'stable'))) . '</strong></div><div><small>Versão atual</small><code>' . esc_html(defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '') . '</code></div><div><small>Última release</small><code>' . esc_html((string)($updateHealth['latest_version'] ?? '') ?: 'por publicar/testar') . '</code></div><div><small>Pacote</small><strong>' . (!empty($updateHealth['package_hash_verified']) ? 'SHA-256 VERIFICADO' : 'POR TESTAR') . '</strong></div></div>';
        echo '<div class="ff6b-actions"><form method="post" style="display:inline-flex;gap:8px;align-items:center">';
        wp_nonce_field('fieldflow_phase6b_action', 'fieldflow_phase6b_nonce');
        echo '<input type="hidden" name="ff_6b_action" value="set_channel_6b4"><select name="update_channel"><option value="stable"' . selected(PrivateUpdateLifecycle::channel(), 'stable', false) . '>Stable</option><option value="beta"' . selected(PrivateUpdateLifecycle::channel(), 'beta', false) . '>Beta</option></select><button class="button">Guardar canal</button></form>';
        self::action('test_6b4', 0, 'Testar distribuição 6B.4', 'secondary');
        self::action('certify_6b4', 0, !empty($updateHealth['certification_state_present']) && empty($updateHealth['certified']) ? 'Revalidar Fase 6B.4' : 'Certificar Fase 6B.4', 'primary');
        self::action('reset_6b4', 0, 'Reiniciar gate 6B.4', 'secondary', 'Reiniciar apenas a certificação 6B.4? Nenhuma release privada será removida.');
        echo '</div><p class="description">O teste descarrega temporariamente o ZIP autorizado, confirma assinatura HMAC, SHA-256 e versão interna, e apaga o ficheiro sem instalar. O WordPress só recebe upgrades para versões superiores à instalada.</p></section>';

        echo '<section class="ff6b-card"><div class="ff6b-card-head"><div><span class="ff6b-kicker">PHASE 6 STABLE CLOSURE</span><h3>Fecho formal da Fase 6</h3></div><span class="ff6b-state ' . esc_attr($stableState['class']) . '">' . esc_html($stableState['label']) . '</span></div>';
        echo '<div class="ff6b-checks">';
        foreach ((array)($phase6StableProbe['checks'] ?? []) as $key=>$ok) self::check((string)$key, (bool)$ok);
        echo '</div><div class="ff6b-install-now"><div><small>Release</small><strong>' . esc_html((string)($phase6StableHealth['release'] ?? Phase6StableRelease::STABLE_RELEASE)) . '</strong></div><div><small>Baseline</small><code>' . esc_html(substr((string)($phase6StableHealth['baseline_fingerprint_sha256'] ?? ''), 0, 18)) . '</code></div><div><small>Production</small><strong>OFF</strong></div><div><small>Enforcement</small><strong>SHADOW</strong></div></div>';
        if (empty($phase6StableProbe['ok'])) {
            echo '<div class="ff6b-config-hint"><strong>Porque ainda não certifica.</strong><span>' . esc_html(self::phase6FailureSummary((array)$phase6StableProbe)) . '</span></div>';
        }
        echo '<div class="ff6b-actions">';
        self::action('revalidate_phase6_chain', 0, 'Revalidar cadeia 6B pós-update', 'secondary', 'Revalidar 6B.2, 6B.3 e 6B.4 a partir do estado live atual? Não revoga, não roda keys, não ativa production e não processa filas.');
        self::action('test_phase6_stable', 0, 'Testar fecho Fase 6', 'secondary');
        self::action('certify_phase6_stable', 0, !empty($phase6StableHealth['certification_state_present']) && empty($phase6StableHealth['stable_certified']) ? 'Revalidar Fase 6 Stable' : 'Certificar Fase 6 Stable', 'primary');
        self::action('reset_phase6_stable', 0, 'Reiniciar fecho Fase 6', 'secondary', 'Reiniciar apenas a certificação Stable da Fase 6? Licenças, instalações e releases privadas não serão alteradas.');
        echo '</div><p class="description">O teste final volta a validar a distribuição privada da release instalada e confirma que identidade, governance, manifestos e hard locks permanecem coerentes. Não instala, revoga ou ativa produção.</p></section>';

        echo '<div class="ff6b-layout"><section class="ff6b-card"><span class="ff6b-kicker">NOVO TENANT</span><h3>Criar tenant de licenciamento</h3><form method="post">';
        wp_nonce_field('fieldflow_phase6b_action', 'fieldflow_phase6b_nonce');
        echo '<input type="hidden" name="ff_6b_action" value="create_tenant"><div class="ff6b-fields">';
        echo '<label><span>Cliente</span><select name="client_id" required><option value="">Selecionar cliente</option>';
        foreach ($clients as $client) echo '<option value="' . (int)$client['id'] . '">' . esc_html((string)$client['name']) . '</option>';
        echo '</select></label>';
        echo '<label><span>Plano FieldFlow</span><select name="plan_slug" required>';
        foreach ($plans as $plan) echo '<option value="' . esc_attr((string)$plan['slug']) . '"' . selected((string)$plan['slug'], 'enterprise', false) . '>' . esc_html((string)$plan['name']) . '</option>';
        echo '</select></label>';
        echo '<label><span>Máx. instalações</span><input type="number" min="1" max="99" name="max_installations" value="1"></label>';
        echo '<label><span>Expira em, opcional</span><input type="date" name="expires_at" value=""></label>';
        echo '</div><label class="ff6b-notes"><span>Notas internas</span><textarea name="notes" rows="3" placeholder="Contrato, referência comercial, condições especiais..."></textarea></label><p><button class="button button-primary"' . (empty($health['foundation_certified']) ? ' disabled' : '') . '>Criar tenant 6B.1</button></p>' . (empty($health['foundation_certified']) ? '<p class="description">Certifica primeiro a Foundation 6B.1.</p>' : '') . '</form></section>';

        echo '<aside class="ff6b-card"><span class="ff6b-kicker">ROADMAP 6B</span><h3>Da key ao tenant</h3>';
        $steps = [
            ['6B.1','Registry + emissão segura','Fechada'],
            ['6B.2','Ativação, domínio e heartbeat','Fechada'],
            ['6B.3','Revogação, rotação e limites','Fechada'],
            ['6B.4','Distribuição privada e updates','Fechada'],
            ['6 Stable','Baseline final ' . Phase6StableRelease::STABLE_RELEASE,'Agora'],
        ];
        foreach ($steps as $step) echo '<div class="ff6b-flow"><b>' . esc_html($step[0]) . '</b><div><strong>' . esc_html($step[1]) . '</strong><span>' . esc_html($step[2]) . '</span></div></div>';
        echo '<div class="ff6b-safe"><strong>Nesta release</strong><span>A Fase 6 está funcionalmente fechada. A ' . esc_html(Phase6StableRelease::STABLE_RELEASE) . ' consolida a baseline final, mantendo catálogo privado, SHADOW, hard locks de produção e rollback manual explícito.</span></div></aside></div>';

        echo '<section class="ff6b-card"><div class="ff6b-card-head"><div><span class="ff6b-kicker">TENANT REGISTRY</span><h3>Licenças por cliente</h3></div><p>O valor da key nunca aparece nesta tabela. Guardamos apenas máscara, fingerprint e identificador remoto.</p></div>';
        self::table($tenants, !empty($health['remote_ready']) && !empty($health['foundation_certified']), $installations, !empty($governanceHealth['phase6b2_certified']) && !empty($governanceHealth['remote_admin_ready']));
        echo '</section>';

        echo '<section class="ff6b-card"><div class="ff6b-card-head"><div><span class="ff6b-kicker">INSTALLATION REGISTRY</span><h3>Instalações observadas</h3></div><p>Vista local do registry 6B.2. O heartbeat remoto continua no Azure e nenhuma linha desta tabela aplica enforcement.</p></div>';
        self::installationTable($installations);
        echo '</section>';

        AdminPage::close();
    }

    private static function table(array $tenants, bool $remoteReady, array $installations = [], bool $governanceReady = false): void {
        $counts = [];
        foreach ($installations as $installation) {
            $tenantId = (int)($installation['tenant_id'] ?? 0);
            if ($tenantId <= 0) continue;
            if (!isset($counts[$tenantId])) $counts[$tenantId] = ['total'=>0,'active'=>0];
            $counts[$tenantId]['total']++;
            if ((string)($installation['status'] ?? '') === 'active') $counts[$tenantId]['active']++;
        }
        if (!$tenants) { echo '<div class="ff6b-empty">Ainda não existem tenants 6B.1.</div>'; return; }
        echo '<div class="ff6b-table-wrap"><table class="widefat striped ff6b-table"><thead><tr><th>Cliente</th><th>Tenant</th><th>Plano</th><th>Estado</th><th>Instalações</th><th>Licença</th><th>Expiração</th><th>Ações</th></tr></thead><tbody>';
        foreach ($tenants as $tenant) {
            echo '<tr><td><strong>' . esc_html((string)($tenant['client_name'] ?? 'Cliente #' . (int)$tenant['client_id'])) . '</strong><br><small>' . esc_html((string)$tenant['customer_code']) . '</small></td>';
            echo '<td><code>' . esc_html(substr((string)$tenant['tenant_uuid'], 0, 18)) . '…</code></td>';
            echo '<td>' . esc_html((string)$tenant['plan_slug']) . '</td>';
            echo '<td><span class="ff6b-pill ' . ((string)$tenant['status'] === 'issued' ? 'ok' : 'warn') . '">' . esc_html(strtoupper((string)$tenant['status'])) . '</span></td>';
            $tenantCount = $counts[(int)$tenant['id']] ?? ['total'=>0,'active'=>0];
            echo '<td><strong>' . (int)$tenantCount['active'] . '</strong> ativas / ' . (int)$tenantCount['total'] . ' registadas<br><small>limite ' . (int)$tenant['max_installations'] . '</small></td>';
            echo '<td>' . ((string)($tenant['masked_key'] ?? '') !== '' ? '<code>' . esc_html((string)$tenant['masked_key']) . '</code>' : '<span class="description">não emitida</span>') . '</td>';
            echo '<td>' . esc_html((string)($tenant['expires_at'] ?? '') ?: 'Sem expiração') . '</td><td>';
            $status = sanitize_key((string)($tenant['status'] ?? 'draft'));
            if ($status === 'draft') {
                if ($remoteReady) self::action('issue_license', (int)$tenant['id'], 'Emitir chave remota', 'primary', 'Emitir uma chave real para este tenant? O valor bruto será mostrado uma única vez.');
                else echo '<span class="description">Configurar API remota</span>';
            } elseif ($governanceReady && in_array($status, ['issued','suspended'], true)) {
                self::action('sync_policy_6b3', (int)$tenant['id'], 'Sincronizar policy');
                if ($status === 'suspended') self::action('resume_6b3', (int)$tenant['id'], 'Retomar', 'secondary', 'Retomar esta licença remotamente?');
                else self::action('suspend_6b3', (int)$tenant['id'], 'Suspender', 'secondary', 'Suspender esta licença no serviço central? O FieldFlow 2.2.384 continuará em SHADOW e não bloqueará o runtime.');
                self::action('rotate_6b3', (int)$tenant['id'], 'Rodar key', 'secondary', 'Rodar esta licença? A key anterior ficará substituída e será apresentada uma nova key uma única vez.');
                self::action('revoke_6b3', (int)$tenant['id'], 'Revogar', 'secondary', 'Revogar definitivamente esta licença no serviço central? Esta ação não deve ser usada num cliente real durante o piloto.');
            } elseif ($status === 'revoked') {
                echo '<span class="description">Revogada, sem ações reversíveis</span>';
            } else {
                echo '<span class="description">Governance disponível após 6B.2 e Admin Secret</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function installationTable(array $installations): void {
        if (!$installations) {
            echo '<div class="ff6b-empty">Ainda não existem instalações reconciliadas no registry local 6B.2. Ativa uma licença remota neste site e executa o heartbeat para criar o primeiro registo piloto.</div>';
            return;
        }
        echo '<div class="ff6b-table-wrap"><table class="widefat striped ff6b-table"><thead><tr><th>Cliente</th><th>Installation ID</th><th>Domínio</th><th>Activation ID</th><th>Estado</th><th>Versão</th><th>Última presença</th></tr></thead><tbody>';
        foreach ($installations as $row) {
            $status = sanitize_key((string)($row['status'] ?? 'pending'));
            echo '<tr><td><strong>' . esc_html((string)($row['client_name'] ?? 'Cliente')) . '</strong><br><small>tenant #' . (int)($row['tenant_id'] ?? 0) . '</small></td>';
            echo '<td><code>' . esc_html((string)($row['installation_uuid'] ?? '')) . '</code></td>';
            echo '<td><strong>' . esc_html((string)($row['domain'] ?? '')) . '</strong><br><small>' . esc_html((string)($row['site_url'] ?? '')) . '</small></td>';
            echo '<td><code>' . esc_html((string)($row['activation_id'] ?? 'por atribuir')) . '</code></td>';
            echo '<td><span class="ff6b-pill ' . ($status === 'active' ? 'ok' : 'warn') . '">' . esc_html(strtoupper($status)) . '</span></td>';
            echo '<td>' . esc_html((string)($row['plugin_version'] ?? '')) . '</td>';
            echo '<td>' . esc_html((string)($row['last_seen_at'] ?? 'Ainda sem heartbeat')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function failedCheckKeys(array $checks): array {
        $failed = [];
        foreach ($checks as $key => $ok) {
            if ($ok) continue;
            $label = trim(str_replace('_', ' ', sanitize_key((string)$key)));
            if ($label !== '') $failed[] = $label;
        }
        return $failed ?: ['diagnóstico indisponível'];
    }

    private static function phase6FailureSummary(array $result): string {
        $labels = [
            'phase6_matrix_all_ready'=>'matriz Phase 6 incompleta',
            'production_manifest_valid'=>'production manifest da instalação não está íntegro',
            'stable_release_version_match'=>'versão instalada não coincide com a release Stable esperada',
            'private_catalog_at_stable_release'=>'catálogo privado ainda não contém a versão instalada',
            'release_manager_publication_matches_runtime'=>'Release Manager ainda não publicou esta versão Stable com SHA-256 válido',
            'installation_identity_preserved'=>'identidade da instalação ou fingerprint 6B.2 precisa de revalidação',
            'activation_binding_preserved'=>'binding de ativação 6B.2 precisa de revalidação',
            'governance_shadow_preserved'=>'governance 6B.3 não está no estado SHADOW certificado',
            'runtime_blocking_off'=>'runtime blocking tem de permanecer desligado',
            'remote_revocation_not_runtime_enforced'=>'revogação remota não pode estar aplicada ao runtime nesta fase',
            'forced_auto_updates_off'=>'auto-update forçado tem de permanecer desligado',
            'downgrade_protection_on'=>'proteção contra downgrade não está ativa',
            'production_enabled_false'=>'production delivery tem de permanecer desligado',
            'full_operational_payload_off'=>'full operational payload tem de permanecer desligado',
            'client_azure_shadow_preserved'=>'Client Azure tem de permanecer em SHADOW',
            'client_azure_running_zero'=>'existem operações Client Azure em execução',
            'client_azure_dead_letters_zero'=>'existem dead letters Client Azure',
            'platform_release_gate_not_blocked'=>'Release Gate da plataforma está bloqueado',
        ];
        $parts = [];
        foreach ((array)($result['checks'] ?? []) as $key => $ok) {
            if ($ok) continue;
            $parts[] = $labels[(string)$key] ?? str_replace('_', ' ', (string)$key);
        }
        foreach ((array)($result['matrix'] ?? []) as $key => $row) {
            if (!is_array($row) || !empty($row['ready'])) continue;
            $matrixLabel = trim((string)($row['label'] ?? $key));
            if ($matrixLabel !== '') $parts[] = 'gate ' . $matrixLabel;
        }
        $releaseGate = is_array($result['release_gate'] ?? null) ? $result['release_gate'] : [];
        foreach ((array)($releaseGate['checks'] ?? []) as $check) {
            if (!is_array($check) || (string)($check['status'] ?? '') !== 'fail') continue;
            $label = trim((string)($check['label'] ?? $check['id'] ?? 'Release Gate'));
            $missing = is_array($check['meta']['missing'] ?? null) ? array_values(array_filter(array_map('sanitize_text_field', $check['meta']['missing']))) : [];
            if ($label !== '') $parts[] = 'Release Gate: ' . $label . ($missing ? ' (' . implode(', ', array_slice($missing, 0, 2)) . (count($missing) > 2 ? ', +' . (count($missing) - 2) : '') . ')' : '');
        }
        $parts = array_values(array_unique(array_filter(array_map('sanitize_text_field', $parts))));
        if (!$parts) {
            $reason = sanitize_text_field((string)($result['reason'] ?? $result['message'] ?? ''));
            return $reason !== '' ? $reason : 'consulta o diagnóstico detalhado abaixo para identificar o gate pendente';
        }
        $visible = array_slice($parts, 0, 7);
        $summary = implode('; ', $visible);
        if (count($parts) > count($visible)) $summary .= '; e mais ' . (count($parts) - count($visible)) . ' verificação(ões)';
        return $summary . '.';
    }

    private static function certificationVisualState(bool $currentlyCertified, bool $historicalStatePresent): array {
        if ($currentlyCertified) return ['class'=>'ok','label'=>'CERTIFICADA'];
        if ($historicalStatePresent) return ['class'=>'warn','label'=>'CERTIFICADA, REVALIDAR'];
        return ['class'=>'warn','label'=>'POR CERTIFICAR'];
    }

    private static function clients(): array {
        global $wpdb;
        return $wpdb->get_results('SELECT id,name FROM ' . $wpdb->prefix . 'routespro_clients ORDER BY name ASC,id ASC', ARRAY_A) ?: [];
    }

    private static function action(string $action, int $tenantId, string $label, string $kind='secondary', string $confirm=''): void {
        echo '<form method="post" style="display:inline-block">';
        wp_nonce_field('fieldflow_phase6b_action', 'fieldflow_phase6b_nonce');
        echo '<input type="hidden" name="ff_6b_action" value="' . esc_attr($action) . '"><input type="hidden" name="tenant_id" value="' . (int)$tenantId . '">';
        $class = $kind === 'primary' ? 'button button-primary' : 'button';
        echo '<button class="' . esc_attr($class) . '"' . ($confirm !== '' ? ' onclick="return confirm(' . esc_attr(wp_json_encode($confirm)) . ')"' : '') . '>' . esc_html($label) . '</button></form>';
    }

    private static function remoteProbePanel(array $health): void {
        $checked = (string)($health['remote_probe_checked_at'] ?? '');
        $hasProbe = $checked !== '';
        echo '<div class="ff6b-probe"><div class="ff6b-probe-head"><strong>Probe real da API remota</strong><span>' . ($hasProbe ? esc_html($checked . ' UTC') : 'Ainda não executado') . '</span></div><div class="ff6b-probe-grid">';
        self::probeItem('Host', (string)($health['remote_probe_host'] ?? '') ?: 'por testar', !empty($health['remote_probe_matches_current_host']));
        self::probeItem('DNS', !empty($health['remote_probe_dns_resolved']) ? (!empty($health['remote_probe_dns_public']) ? 'PÚBLICO' : 'BLOQUEADO') : 'não exposto pelo PHP', !empty($health['remote_probe_dns_public']) || (!empty($health['remote_probe_trusted_wordpress_bypass']) && !empty($health['remote_probe_http_reachable'])));
        self::probeItem('WordPress URL', !empty($health['remote_probe_wordpress_url_validation']) ? 'ACEITE' : (!empty($health['remote_probe_trusted_wordpress_bypass']) ? 'BYPASS FIXO CONTROLADO' : 'REJEITADO'), !empty($health['remote_probe_wordpress_url_validation']) || !empty($health['remote_probe_trusted_wordpress_bypass']));
        self::probeItem('HTTPS', !empty($health['remote_probe_http_reachable']) ? 'RESPONDE' : 'POR VALIDAR', !empty($health['remote_probe_http_reachable']));
        self::probeItem('Rota Function', !empty($health['remote_probe_route_reachable']) ? 'JSON OK' : 'REVER', !empty($health['remote_probe_route_reachable']));
        self::probeItem('HMAC', !empty($health['remote_probe_shared_secret_accepted']) ? 'ACEITE' : 'REVER', !empty($health['remote_probe_shared_secret_accepted']));
        self::probeItem('HTTP', (int)($health['remote_probe_http_code'] ?? 0) > 0 ? (string)(int)$health['remote_probe_http_code'] : 'n/a', !empty($health['remote_probe_http_reachable']));
        echo '</div>';
        if ((string)($health['remote_probe_message'] ?? '') !== '') echo '<p class="description">' . esc_html((string)$health['remote_probe_message']) . '</p>';
        echo '</div>';
    }

    private static function probeItem(string $label, string $value, bool $ok): void {
        echo '<span class="ff6b-probe-item ' . ($ok ? 'ok' : 'bad') . '"><small>' . esc_html($label) . '</small><strong>' . esc_html($value) . '</strong></span>';
    }

    private static function badge(string $label, string $value, bool $ok): void { echo '<span class="ff6b-badge ' . ($ok ? 'ok' : 'bad') . '"><small>' . esc_html($label) . '</small><strong>' . esc_html($value) . '</strong></span>'; }
    private static function metric(string $label, string $value, string $caption): void { echo '<div class="ff6b-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong><small>' . esc_html($caption) . '</small></div>'; }
    private static function check(string $key, bool $ok): void { echo '<span class="ff6b-check ' . ($ok ? 'ok' : 'bad') . '">' . esc_html($key) . ': ' . ($ok ? 'OK' : 'REVER') . '</span>'; }

    private static function styles(): void {
        echo '<style>
        .ff6b-hero{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;padding:26px;border:1px solid #e2e8f0;border-radius:22px;background:linear-gradient(135deg,#fff,#f8fafc 65%,#eef8f7);margin:18px 0}.ff6b-kicker{font-size:11px;letter-spacing:.14em;font-weight:800;color:#0f766e}.ff6b-hero h2,.ff6b-card h3{margin:6px 0 8px}.ff6b-hero p{max-width:760px;color:#475569}.ff6b-badges{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}.ff6b-badge{display:flex;flex-direction:column;padding:9px 12px;border-radius:12px;border:1px solid}.ff6b-badge.ok{background:#ecfdf5;border-color:#a7f3d0;color:#166534}.ff6b-badge.bad{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.ff6b-badge small{font-size:10px;opacity:.75}.ff6b-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:14px}.ff6b-metric,.ff6b-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px}.ff6b-metric span{font-size:12px;color:#64748b}.ff6b-metric strong{display:block;font-size:28px;margin:6px 0}.ff6b-metric small{color:#64748b}.ff6b-card-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}.ff6b-card-head p{max-width:520px;color:#64748b;margin:0}.ff6b-checks{display:flex;flex-wrap:wrap;gap:8px}.ff6b-check{padding:7px 9px;border-radius:999px;font-size:11px}.ff6b-check.ok{background:#ecfdf5;color:#166534}.ff6b-check.bad{background:#fff7ed;color:#9a3412}.ff6b-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}.ff6b-state{padding:7px 10px;border-radius:999px;font-size:11px;font-weight:800}.ff6b-state.ok,.ff6b-pill.ok{background:#dcfce7;color:#166534}.ff6b-state.warn,.ff6b-pill.warn{background:#fef3c7;color:#92400e}.ff6b-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,.8fr);gap:14px;margin:14px 0}.ff6b-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:16px 0}.ff6b-fields label>span,.ff6b-notes>span{display:block;font-weight:700;margin-bottom:6px}.ff6b-fields input,.ff6b-fields select,.ff6b-notes textarea{width:100%}.ff6b-flow{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #eef2f7}.ff6b-flow>b{min-width:52px;height:28px;display:grid;place-items:center;border-radius:999px;background:#0f172a;color:#fff;font-size:11px}.ff6b-flow strong,.ff6b-flow span{display:block}.ff6b-flow span{font-size:12px;color:#64748b;margin-top:2px}.ff6b-safe{margin-top:18px;padding:14px;border-radius:14px;background:#0f172a;color:#fff}.ff6b-safe span{display:block;font-size:12px;opacity:.78;margin-top:4px}.ff6b-table-wrap{overflow:auto}.ff6b-table code{font-size:10px}.ff6b-pill{display:inline-block;padding:5px 8px;border-radius:999px;font-size:10px;font-weight:800}.ff6b-empty{padding:22px;text-align:center;color:#64748b}.ff6b-config-hint{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px;padding:12px 14px;border:1px solid #fed7aa;background:#fff7ed;border-radius:12px;color:#9a3412}.ff6b-config-hint span{flex:1;min-width:260px;color:#7c2d12}.ff6b-probe{margin-top:14px;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.ff6b-probe-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px}.ff6b-probe-head span{font-size:11px;color:#64748b}.ff6b-probe-grid{display:flex;flex-wrap:wrap;gap:8px}.ff6b-probe-item{display:flex;flex-direction:column;min-width:105px;padding:8px 10px;border-radius:10px;border:1px solid}.ff6b-probe-item.ok{background:#ecfdf5;border-color:#a7f3d0;color:#166534}.ff6b-probe-item.bad{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.ff6b-probe-item small{font-size:9px;opacity:.7}.ff6b-probe-item strong{font-size:11px;margin-top:2px}.ff6b-once{border-left-color:#f59e0b!important}.ff6b-keyrow{display:flex;gap:8px;max-width:900px}.ff6b-keyrow input{flex:1;font-family:monospace}.ff6b-install-now{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:14px;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.ff6b-install-now>div{min-width:0}.ff6b-install-now small{display:block;color:#64748b;font-size:10px;margin-bottom:4px}.ff6b-install-now code,.ff6b-install-now strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        @media(max-width:1100px){.ff6b-hero{display:block}.ff6b-badges{justify-content:flex-start;margin-top:16px}.ff6b-metrics{grid-template-columns:repeat(2,1fr)}.ff6b-layout{grid-template-columns:1fr}}@media(max-width:700px){.ff6b-metrics,.ff6b-fields,.ff6b-install-now{grid-template-columns:1fr}.ff6b-card-head{display:block}.ff6b-hero,.ff6b-card,.ff6b-metric{padding:16px}.ff6b-keyrow{display:block}.ff6b-keyrow .button{margin-top:8px}}
        </style>';
    }
}
