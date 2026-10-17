<?php
namespace RoutesPro\Admin;

use RoutesPro\Cloud\PrivatePluginUpdater;
use RoutesPro\Cloud\PrivateUpdateLifecycle;
use RoutesPro\Cloud\ReleasePublisherClient;
use RoutesPro\Cloud\ReleasePublicationReceipt;
use RoutesPro\Cloud\Phase6StableRelease;
use RoutesPro\Core\AuditLog;
use RoutesPro\Support\AdminPage;
use RoutesPro\Support\ReleasePackageValidator;
use RoutesPro\Support\ReleasePreflight;
use RoutesPro\Support\ReleaseStagingWorkflow;
use RoutesPro\Support\ProductionManifest;

if (!defined('ABSPATH')) exit;

/** WordPress control-plane for private FieldFlow releases. */
final class ReleaseManager {
    public const SLUG = 'routespro-release-manager';
    public const LAST_STATE_OPTION = 'fieldflow_release_manager_last_state';

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');

        $notice = '';
        $error = '';
        $packageResult = [];
        $preflight = [];
        $publishResult = [];
        $certificationResult = [];
        $assistedState = [];

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST['ff_release_action'])) {
            check_admin_referer('fieldflow_release_manager', 'fieldflow_release_nonce');
            $action = sanitize_key((string)$_POST['ff_release_action']);
            try {
                if ($action === 'save_endpoint') {
                    $url = ReleasePublisherClient::setBaseUrl((string)wp_unslash($_POST['publisher_base'] ?? ''));
                    $notice = 'Endpoint do Release Publisher guardado: ' . $url;
                } elseif ($action === 'phase6_revalidate') {
                    $certificationResult = Phase6StableRelease::revalidateCertificationChain(get_current_user_id());
                    if (empty($certificationResult['ok'])) throw new \RuntimeException(sanitize_text_field((string)($certificationResult['message'] ?? 'A cadeia 6B não passou a revalidação.')));
                    $notice = sanitize_text_field((string)($certificationResult['message'] ?? 'Cadeia 6B revalidada.'));
                } elseif ($action === 'phase6_probe') {
                    $certificationResult = Phase6StableRelease::localProbe(true);
                    if (empty($certificationResult['ok'])) throw new \RuntimeException('O teste de fecho da Fase 6 ainda tem verificações por concluir.');
                    $notice = 'Teste de fecho da Fase 6 concluído com sucesso.';
                } elseif ($action === 'phase6_certify') {
                    $certificationResult = Phase6StableRelease::certify(get_current_user_id());
                    if (empty($certificationResult['ok'])) throw new \RuntimeException(sanitize_text_field((string)($certificationResult['message'] ?? 'A Stable ainda não reúne todas as condições de certificação.')));
                    $notice = sanitize_text_field((string)($certificationResult['message'] ?? 'Fase 6 Stable certificada.'));
                } elseif ($action === 'repair_install') {
                    $message = self::repairCanonicalInstall();
                    wp_safe_redirect(add_query_arg(['page'=>self::SLUG,'ff_release_notice'=>$message], admin_url('admin.php')));
                    exit;
                } elseif (in_array($action, ['publish_staged','reconcile_staged','resume_staged_catalog','discard_staged'], true)) {
                    $user = get_current_user_id();
                    $token = sanitize_text_field((string)wp_unslash($_POST['staged_token'] ?? ''));
                    if ($action === 'discard_staged') {
                        if (empty($_POST['confirm_discard'])) throw new \RuntimeException('Confirma explicitamente o descarte do ZIP e do registo de recuperação.');
                        ReleaseStagingWorkflow::discard($user, $token);
                        $notice = 'O ZIP temporário foi eliminado do armazenamento privado. O catálogo remoto não foi modificado.';
                    } elseif ($action === 'reconcile_staged') {
                        $result = ReleaseStagingWorkflow::reconcile($user, $token);
                        if (empty($result['ok'])) $error = (string)($result['message'] ?? 'Publicação ainda não confirmada.');
                        else $notice = (string)$result['message'];
                    } elseif ($action === 'resume_staged_catalog') {
                        if (empty($_POST['confirm_publish'])) throw new \RuntimeException('Confirma explicitamente a retoma da publicação no catálogo Azure.');
                        $result = ReleaseStagingWorkflow::resumeCatalog($user, $token);
                        $notice = (string)($result['message'] ?? 'Operação concluída.');
                    } else {
                        if (empty($_POST['confirm_publish'])) throw new \RuntimeException('Confirma explicitamente a publicação assistida.');
                        $channel = sanitize_key((string)($_POST['release_channel'] ?? 'stable'));
                        $result = ReleaseStagingWorkflow::publish($user, $token, $channel, [
                            'requires_wp'=>sanitize_text_field((string)($_POST['requires_wp'] ?? '6.4')),
                            'requires_php'=>sanitize_text_field((string)($_POST['requires_php'] ?? '7.4')),
                            'tested_wp'=>sanitize_text_field((string)($_POST['tested_wp'] ?? get_bloginfo('version'))),
                            'notes'=>sanitize_text_field((string)wp_unslash($_POST['release_notes'] ?? '')),
                        ]);
                        $notice = (string)($result['message'] ?? 'Publicação assistida concluída.');
                    }
                } elseif (in_array($action, ['validate_package','publish_package','stage_package'], true)) {
                    $file = $_FILES['release_zip'] ?? [];
                    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Seleciona um ZIP FieldFlow para validar.');
                    if (!empty($file['error'])) throw new \RuntimeException('O upload do ZIP falhou.');
                    if ((int)($file['size'] ?? 0) > ReleasePackageValidator::MAX_BYTES) throw new \RuntimeException('O ZIP excede o limite de 64 MB.');
                    if (strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'zip') throw new \RuntimeException('O Release Manager aceita apenas ficheiros ZIP.');

                    $packageResult = ReleasePackageValidator::validate((string)$file['tmp_name']);
                    $runtime = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
                    $candidateVersion = (string)($packageResult['version'] ?? '');
                    // 9DA: derive a read-only explanation only from manifest metadata
                    // already authenticated by ReleasePackageValidator.
                    $baselineManifestPath = ROUTESPRO_PATH . 'production-manifest.json';
                    $baselineManifest = is_readable($baselineManifestPath)
                        ? json_decode((string)file_get_contents($baselineManifestPath), true) : [];
                    if (!is_array($baselineManifest)) $baselineManifest = [];
                    $baselineVerification = ProductionManifest::verify(true);
                    $preflight = ReleasePreflight::evaluate(
                        $packageResult, $runtime, $baselineManifest, !empty($baselineVerification['ok'])
                    );
                    if (empty($packageResult['ok'])) {
                        throw new \RuntimeException((string)($packageResult['error'] ?? 'O ZIP não passou a validação de release.'));
                    }
                    if (empty($preflight['can_publish'])) {
                        throw new \RuntimeException('Pré-validação bloqueada: consulta o diagnóstico de sucessão e integridade abaixo.');
                    }

                    if ($action === 'stage_package') {
                        if (empty($baselineVerification['ok'])) throw new \RuntimeException('Manifesto da instalação atual não confirmado. Armazenamento assistido bloqueado até revalidar a Stable.');
                        $assistedState = ReleaseStagingWorkflow::stage($file, get_current_user_id(), $packageResult);
                        $notice = 'ZIP validado e guardado numa pasta temporária privada por 24 horas. Podes continuar sem voltar a selecionar o ficheiro.';
                    } elseif ($action === 'validate_package') {
                        $notice = 'Pré-validação concluída. Verifica o SHA-256, as diferenças de ficheiros e os avisos antes de publicar. Nenhum ficheiro foi enviado ao Azure.';
                    } else {
                        if (empty($_POST['confirm_publish'])) throw new \RuntimeException('Confirma explicitamente a publicação da release.');
                        $channel = sanitize_key((string)($_POST['release_channel'] ?? 'stable'));
                        if (!in_array($channel, ['stable','beta'], true)) $channel = 'stable';
                        $health = ReleasePublisherClient::health();
                        if (empty($health['ok'])) throw new \RuntimeException('Release Publisher indisponível: ' . sanitize_text_field((string)($health['message'] ?? 'health check falhou.')));

                        $uploaded = ReleasePublisherClient::uploadPackage((string)$file['tmp_name'], $packageResult, $channel);
                        if (empty($uploaded['success']) && empty($uploaded['ok'])) {
                            throw new \RuntimeException('Upload privado falhou: ' . sanitize_text_field((string)($uploaded['message'] ?? 'resposta inválida.')));
                        }
                        $publishResult = ReleasePublisherClient::publishCatalog($uploaded, $packageResult, $channel, [
                            'requires_wp'=>sanitize_text_field((string)($_POST['requires_wp'] ?? '6.4')),
                            'requires_php'=>sanitize_text_field((string)($_POST['requires_php'] ?? '7.4')),
                            'tested_wp'=>sanitize_text_field((string)($_POST['tested_wp'] ?? get_bloginfo('version'))),
                            'notes'=>sanitize_text_field((string)wp_unslash($_POST['release_notes'] ?? '')),
                        ]);
                        if (empty($publishResult['success']) && empty($publishResult['ok'])) {
                            throw new \RuntimeException('O ZIP chegou ao Blob privado, mas o catálogo recusou a publicação: ' . sanitize_text_field((string)($publishResult['message'] ?? 'erro remoto.')));
                        }

                        $inspect = ReleasePublisherClient::inspect($candidateVersion, $channel);
                        $release = ReleasePublisherClient::findRelease($inspect, $candidateVersion, $channel);
                        if (!$release) throw new \RuntimeException('A publicação respondeu OK, mas a release ainda não foi confirmada pelo catálogo privado.');
                        $remoteSha = strtolower(sanitize_text_field((string)($release['sha256'] ?? $release['package_sha256'] ?? '')));
                        $remoteSize = (int)($release['package_size'] ?? 0);
                        if ($remoteSha !== '' && !hash_equals((string)$packageResult['sha256'], $remoteSha)) throw new \RuntimeException('O catálogo devolveu um SHA-256 diferente do ZIP publicado.');
                        if ($remoteSize > 0 && $remoteSize !== (int)$packageResult['bytes']) throw new \RuntimeException('O catálogo devolveu um tamanho diferente do ZIP publicado.');

                        $state = [
                            'version'=>$candidateVersion,
                            'channel'=>$channel,
                            'sha256'=>(string)$packageResult['sha256'],
                            'bytes'=>(int)$packageResult['bytes'],
                            'release_id'=>sanitize_text_field((string)($release['release_id'] ?? '')),
                            'published_at'=>current_time('mysql', true),
                            'published_by'=>get_current_user_id(),
                            'publisher_service'=>sanitize_text_field((string)($health['service'] ?? 'fieldflow-release-publisher')),
                        ];
                        $persistedReceipt = ReleasePublicationReceipt::persist($state);
                        if (!$persistedReceipt) throw new \RuntimeException('A publicação foi confirmada remotamente, mas o recibo local do Release Manager não pôde ser persistido.');
                        PrivatePluginUpdater::clearCache();
                        delete_site_transient('update_plugins');
                        if (function_exists('wp_clean_plugins_cache')) wp_clean_plugins_cache(true);
                        if (function_exists('wp_update_plugins')) wp_update_plugins();
                        PrivateUpdateLifecycle::resetHealthCache();
                        AuditLog::record('release.published', 'fieldflow_release', 0, null, $state, ['source'=>'backoffice'], ['manifest_verified'=>true,'canonical_root'=>true]);
                        $publishResult['confirmed_release'] = $release;
                        $notice = 'Release ' . $candidateVersion . ' publicada e confirmada no canal ' . strtoupper($channel) . '. A cache de updates do WordPress foi limpa.';
                    }
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $assistedHistory = [];
        try {
            $assistedState = ReleaseStagingWorkflow::current(get_current_user_id());
            $assistedHistory = ReleaseStagingWorkflow::history();
        } catch (\Throwable $e) { if (!$error) $error = $e->getMessage(); }
        if (!$notice && !empty($_GET['ff_release_notice'])) $notice = sanitize_text_field((string)wp_unslash($_GET['ff_release_notice']));
        $install = self::canonicalInstallState();
        $publisher = ReleasePublisherClient::health();
        $last = ReleasePublicationReceipt::current(defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '');
        if (!is_array($last) || empty($last['version'])) $last = ReleasePublicationReceipt::current();
        if (!is_array($last)) $last = [];
        $updateHealth = PrivateUpdateLifecycle::health();
        $phase6 = Phase6StableRelease::health();
        $pipeline = self::pipelineState($install, $publisher, $last, $updateHealth, $phase6);

        AdminPage::open('Release Manager', 'Publicação privada FieldFlow diretamente a partir do WordPress, com validação de raiz, manifesto, SHA-256 e confirmação no catálogo Azure.');
        ?>
        <style>
        .ffrm-wrap{max-width:1450px}.ffrm-hero{margin:18px 0;padding:24px;border-radius:24px;background:linear-gradient(135deg,#0f172a,#1e3a5f 58%,#0f766e);color:#fff;box-shadow:0 18px 44px rgba(15,23,42,.16)}.ffrm-hero h2{margin:0 0 7px!important;color:#fff!important;font-size:27px}.ffrm-hero p{margin:0;color:#dbeafe!important;max-width:980px;line-height:1.55}.ffrm-grid{display:grid;grid-template-columns:minmax(300px,.72fr) minmax(0,1.28fr);gap:18px}.ffrm-card{background:#fff;border:1px solid #dbe5ef;border-radius:18px;padding:18px;box-shadow:0 10px 28px rgba(15,23,42,.05);margin-bottom:18px}.ffrm-card h3{margin-top:0}.ffrm-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.ffrm-kpi{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:13px}.ffrm-kpi span{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.06em}.ffrm-kpi strong,.ffrm-kpi code{display:block;margin-top:5px;overflow-wrap:anywhere}.ffrm-ok{color:#166534}.ffrm-bad{color:#b91c1c}.ffrm-warn{color:#92400e}.ffrm-form{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.ffrm-form label{display:grid;gap:6px;font-weight:700}.ffrm-form .wide{grid-column:1/-1}.ffrm-form input,.ffrm-form select,.ffrm-form textarea{width:100%;max-width:none}.ffrm-check{display:flex!important;gap:10px;align-items:flex-start;padding:12px;border:1px solid #fde68a;background:#fffbeb;border-radius:12px}.ffrm-check input{width:auto;margin-top:3px;flex:0 0 auto}.ffrm-check span{display:grid;gap:3px;min-width:0}.ffrm-check small{display:block;font-weight:400;line-height:1.45;color:#713f12}.ffrm-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.ffrm-code{font-size:12px;background:#0f172a;color:#e2e8f0;padding:11px;border-radius:11px;overflow:auto}.ffrm-note{padding:12px;border-radius:12px;background:#eff6ff;color:#1e3a8a;line-height:1.5}.ffrm-danger{padding:12px;border-radius:12px;background:#fff7ed;color:#9a3412;line-height:1.5}.ffrm-table{width:100%;border-collapse:collapse}.ffrm-table td,.ffrm-table th{padding:9px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}.ffrm-table th{color:#64748b;font-size:11px;text-transform:uppercase}.ffrm-pill{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800;background:#e2e8f0}.ffrm-pill.ok{background:#dcfce7;color:#166534}.ffrm-pill.bad{background:#fee2e2;color:#991b1b}.ffrm-pill.warn{background:#fef3c7;color:#92400e}.ffrm-pipeline{display:grid;gap:8px}.ffrm-pipeline-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid #eef2f7}.ffrm-pipeline-row:last-child{border-bottom:0}.ffrm-pipeline-row code{overflow-wrap:anywhere}@media(max-width:1000px){.ffrm-grid{grid-template-columns:1fr}.ffrm-kpis{grid-template-columns:repeat(2,1fr)}}@media(max-width:700px){.ffrm-form{grid-template-columns:1fr}.ffrm-form .wide{grid-column:auto}.ffrm-kpis{grid-template-columns:1fr}}
        /* 9DA assisted preflight: progressive enhancement to the manual flow. */
        .ffrm-assisted-9da{margin:0 0 20px;padding:22px;border:1px solid #d7e3ef;border-radius:22px;background:linear-gradient(135deg,#f8fbff,#ffffff);box-shadow:0 8px 26px rgba(20,40,70,.055)}
        .ffrm-assisted-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .ffrm-assisted-top h3{font-size:20px;margin:0 0 5px}.ffrm-assisted-top p{margin:0;color:#516277}
        .ffrm-assisted-chip{border-radius:999px;background:#e7eef9;color:#1f426d;padding:8px 12px;font-weight:750;display:inline-block}
        .ffrm-assisted-chip.ok{background:#dff7ea;color:#136343}.ffrm-assisted-chip.bad{background:#fff0e9;color:#9a3412}
        .ffrm-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-top:16px}
        .ffrm-step{border:1px solid #e1e8f2;border-radius:14px;padding:12px;background:#fff;min-width:0}
        .ffrm-step-number{display:inline-flex;align-items:center;justify-content:center;width:23px;height:23px;margin-right:8px;border-radius:50%;background:#e9edf4;color:#263b59;font-size:12px;font-weight:800}
        .ffrm-step strong{font-size:13px;display:block;margin-top:6px}.ffrm-step small{color:#64748b;display:block;margin-top:4px;line-height:1.5}
        .ffrm-step.done{border-color:#bce6d0;background:#f4fcf8}.ffrm-step.done .ffrm-step-number{background:#187449;color:#fff}
        .ffrm-assisted-result{margin-top:16px;border-top:1px solid #e1e8f2;padding-top:16px}
        .ffrm-assisted-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:12px 0}
        .ffrm-assisted-metric{border-radius:12px;background:#fff;border:1px solid #e4eaf2;padding:12px}
        .ffrm-assisted-metric b{display:block;font-size:22px;line-height:1.2}.ffrm-assisted-metric span{font-size:12px;color:#64748b}
        .ffrm-assisted-diagnostics{margin:12px 0;padding:12px 15px;border-left:4px solid #dc512f;background:#fff7f2;border-radius:8px}
        .ffrm-assisted-diagnostics strong{color:#8f2711}.ffrm-assisted-diagnostics p{margin:5px 0;color:#334155}
        .ffrm-assisted-warning{background:#fff9e8;border:1px solid #f6e0ad;border-radius:10px;padding:10px 13px;margin:9px 0;color:#694c11}
        .ffrm-assisted-files{max-height:270px;overflow:auto;border-radius:10px;border:1px solid #dae2ec;background:#fff}
        .ffrm-assisted-files table{width:100%;border-collapse:collapse}.ffrm-assisted-files td,.ffrm-assisted-files th{padding:8px 10px;text-align:left;border-bottom:1px solid #e9eef4;font-size:12px;overflow-wrap:anywhere}
        .ffrm-assisted-files th{background:#f4f7fa;position:sticky;top:0;color:#42546d}
        .ffrm-assisted-note{font-size:12px;color:#5a697d;line-height:1.55;margin-top:11px}
        @media(max-width:782px){.ffrm-steps,.ffrm-assisted-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.ffrm-assisted-9da{padding:15px}}
        /* 9DB assisted workflow: a private staged ZIP is never exposed in a URL. */
        .ffrm-9db-stage{margin:15px 0;border:1px solid #cbdce8;border-radius:16px;background:#fff;padding:18px}
        .ffrm-9db-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
        .ffrm-9db-title{margin:0;font-size:16px;color:#17304c}.ffrm-9db-meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:14px 0}
        .ffrm-9db-meta>div{background:#f3f7fa;border-radius:11px;padding:10px;min-width:0;overflow-wrap:anywhere}
        .ffrm-9db-meta small{display:block;color:#586b83}.ffrm-9db-meta strong{display:block;margin-top:3px}
        .ffrm-9db-history{margin-top:13px;max-height:180px;overflow:auto;background:#f8fafc;border-radius:12px;padding:12px}
        .ffrm-9db-history li{margin:0 0 7px;line-height:1.45;color:#34465b}
        .ffrm-9db-control{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}
        .ffrm-9db-control label{display:grid;gap:5px}.ffrm-9db-control input,.ffrm-9db-control select,.ffrm-9db-control textarea{width:100%;max-width:100%}
        .ffrm-9db-control .full{grid-column:1/-1}.ffrm-9db-control button{width:auto}
        .ffrm-9db-alert{border-left:4px solid #e69b2d;background:#fffaea;padding:12px;border-radius:8px;margin:12px 0}
        @media(max-width:780px){.ffrm-9db-meta,.ffrm-9db-control{grid-template-columns:1fr}}
        </style>
        <div class="ffrm-wrap">
          <section class="ffrm-hero"><h2>FieldFlow Release Pipeline</h2><p>Valida, envia e publica releases privadas sem Azure Cloud Shell. O Storage continua privado, os segredos nunca são enviados para o browser e o catálogo continua a validar o pacote antes de o oferecer ao WordPress.</p></section>
          <?php if ($notice): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
          <?php if ($error): ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

          <section class="ffrm-assisted-9da" aria-label="Release Manager Assistido">
            <div class="ffrm-assisted-top">
              <div><h3>Release Manager Assistido</h3><p>9DB: carregar uma vez, revalidar antes de publicar e reconciliar interrupções, sem contornar o processo manual nem os gates.</p></div>
              <span class="ffrm-assisted-chip <?php echo $preflight ? (!empty($preflight['can_publish']) ? 'ok' : 'bad') : ''; ?>">
                <?php echo $preflight ? (!empty($preflight['can_publish']) ? (!empty($preflight['warnings']) ? 'Aprovada com avisos' : 'Pré-validação aprovada') : 'Revisão necessária') : 'Aguarda ZIP'; ?>
              </span>
            </div>
            <div class="ffrm-steps" aria-label="Etapas da release">
              <div class="ffrm-step <?php echo $preflight ? 'done' : ''; ?>"><span class="ffrm-step-number">1</span><strong>Selecionar</strong><small>ZIP com raiz fieldflow/</small></div>
              <div class="ffrm-step <?php echo !empty($preflight['can_publish']) ? 'done' : ''; ?>"><span class="ffrm-step-number">2</span><strong>Pré-validar</strong><small>Manifesto, SHA, contratos, ficheiros</small></div>
              <div class="ffrm-step <?php echo !empty($publishResult['confirmed_release']) ? 'done' : ''; ?>"><span class="ffrm-step-number">3</span><strong>Publicar</strong><small>Confirmação humana e catálogo privado</small></div>
              <div class="ffrm-step <?php echo !empty($phase6['stable_certified']) && !$preflight ? 'done' : ''; ?>"><span class="ffrm-step-number">4</span><strong>Certificar</strong><small>Instalação, verificações 6B, Fase 6 Stable</small></div>
            </div>
            <?php if ($preflight): ?>
              <div class="ffrm-assisted-result">
                <p><strong>Instalada:</strong> <?php echo esc_html((string)$preflight['runtime']); ?>
                  &nbsp; <strong>Esperada:</strong> <?php echo esc_html((string)$preflight['expected_next']); ?>
                  &nbsp; <strong>Candidata:</strong> <?php echo esc_html((string)$preflight['candidate']); ?></p>
                <?php if (!empty($preflight['sha256'])): ?><p><strong>SHA-256 do ZIP:</strong> <code style="overflow-wrap:anywhere"><?php echo esc_html((string)$preflight['sha256']); ?></code></p><?php endif; ?>
                <?php foreach ((array)$preflight['checks'] as $check): ?>
                  <p style="margin:6px 0"><strong><?php echo !empty($check['ok']) ? '✓' : '✕'; ?> <?php echo esc_html((string)$check['label']); ?></strong>
                    <span style="color:#5f6b7a">, <?php echo esc_html((string)$check['detail']); ?></span></p>
                <?php endforeach; ?>
                <?php if (!empty($preflight['diagnostics'])): ?>
                  <div class="ffrm-assisted-diagnostics" role="alert">
                    <strong>Bloqueios identificados</strong>
                    <?php foreach ((array)$preflight['diagnostics'] as $diag): ?>
                      <p><code><?php echo esc_html((string)$diag['code']); ?></code> <?php echo esc_html((string)$diag['detail']); ?></p>
                      <p><strong>Como corrigir:</strong> <?php echo esc_html((string)$diag['action']); ?></p>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php foreach ((array)$preflight['warnings'] as $warning): ?><div class="ffrm-assisted-warning"><?php echo esc_html((string)$warning); ?></div><?php endforeach; ?>
                <?php if (!empty($preflight['diff_available'])): ?>
                  <h4>Diferenças face ao manifesto instalado</h4>
                  <div class="ffrm-assisted-metrics">
                    <?php foreach (['added'=>'Adicionados','modified'=>'Alterados','removed'=>'Removidos','unchanged'=>'Sem alterações'] as $key=>$label): ?>
                      <div class="ffrm-assisted-metric"><b><?php echo esc_html((string)($preflight['counts'][$key] ?? 0)); ?></b><span><?php echo esc_html($label); ?></span></div>
                    <?php endforeach; ?>
                  </div>
                  <?php if (!empty($preflight['changed'])): ?>
                    <div class="ffrm-assisted-files"><table><thead><tr><th>Ficheiro</th><th>Alteração</th><th>Revisão</th></tr></thead><tbody>
                      <?php foreach ((array)$preflight['changed'] as $item): ?>
                        <tr><td><code><?php echo esc_html((string)$item['path']); ?></code></td>
                          <td><?php echo esc_html(['added'=>'Novo','modified'=>'Alterado','removed'=>'Removido'][(string)$item['type']] ?? ''); ?></td>
                          <td><?php echo !empty($item['sensitive']) ? 'Sensível' : 'Normal'; ?></td></tr>
                      <?php endforeach; ?>
                    </tbody></table></div>
                    <?php if (!empty($preflight['omitted_changes'])): ?><p class="ffrm-assisted-note">Mais <?php echo esc_html((string)$preflight['omitted_changes']); ?> alterações não apresentadas nesta vista compacta.</p><?php endif; ?>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="ffrm-9db-stage" aria-label="Fluxo de publicação assistida 9DB">
              <div class="ffrm-9db-head">
                <h4 class="ffrm-9db-title">Upload único e recuperação de publicação</h4>
                <span class="ffrm-assisted-chip <?php echo $assistedState ? (!empty($assistedState['integrity_ok']) ? 'ok' : 'bad') : ''; ?>">
                  <?php echo esc_html($assistedState ? strtoupper(str_replace('_', ' ', (string)$assistedState['status'])) : 'SEM ZIP GUARDADO'); ?>
                </span>
              </div>
              <?php if (!$assistedState): ?>
                <p class="ffrm-assisted-note">Carrega e valida uma candidata N+1. O ZIP fica numa pasta temporária privada por um máximo de 24 horas. Este processo pode estar indisponível se o alojamento não disponibilizar uma pasta temporária fora da web. O formulário manual abaixo continua operacional.</p>
                <form method="post" enctype="multipart/form-data" class="ffrm-form">
                  <?php wp_nonce_field('fieldflow_release_manager', 'fieldflow_release_nonce'); ?>
                  <label class="wide">ZIP de release<input type="file" name="release_zip" accept=".zip,application/zip" required></label>
                  <div class="wide ffrm-actions"><button class="button button-primary" name="ff_release_action" value="stage_package">1. Pré-validar e guardar ZIP privado</button></div>
                </form>
              <?php else: ?>
                <div class="ffrm-9db-meta">
                  <div><small>Versão</small><strong><?php echo esc_html((string)$assistedState['version']); ?></strong></div>
                  <div><small>Integridade do ZIP</small><strong><?php echo !empty($assistedState['integrity_ok']) ? 'SHA-256 verificado' : 'NÃO CONFIRMADA'; ?></strong></div>
                  <div><small>Expira em UTC</small><strong><?php echo esc_html(gmdate('d/m/Y H:i', (int)$assistedState['expires'])); ?></strong></div>
                </div>
                <p class="ffrm-assisted-note"><strong>SHA-256:</strong> <code style="overflow-wrap:anywhere"><?php echo esc_html((string)$assistedState['sha256']); ?></code></p>
                <?php if (empty($assistedState['integrity_ok'])): ?>
                  <div class="ffrm-9db-alert"><?php echo esc_html((string)($assistedState['integrity_message'] ?? 'Ficheiro não verificável.')); ?> Nenhuma publicação assistida será executada.</div>
                <?php elseif (($assistedState['status'] ?? '') === 'staged'): ?>
                  <form method="post" class="ffrm-9db-control">
                    <?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?>
                    <input type="hidden" name="staged_token" value="<?php echo esc_attr((string)$assistedState['token']); ?>">
                    <label>Canal<select name="release_channel"><option value="stable">Stable</option><option value="beta">Beta</option></select></label>
                    <label>Requires WordPress<input name="requires_wp" value="6.4"></label>
                    <label>Requires PHP<input name="requires_php" value="7.4"></label>
                    <label>Tested WordPress<input name="tested_wp" value="<?php echo esc_attr(get_bloginfo('version')); ?>"></label>
                    <label class="full">Notas da release<textarea name="release_notes" rows="2" placeholder="Conteúdo da atualização"></textarea></label>
                    <label class="full ffrm-check"><input type="checkbox" name="confirm_publish" value="1"><span><strong>Autorizo explicitamente esta publicação no Azure</strong><small>O ZIP será novamente validado, incluindo versão, manifesto, contratos e SHA-256. Nenhuma certificação é automática.</small></span></label>
                    <div class="full ffrm-actions"><button class="button button-primary" name="ff_release_action" value="publish_staged" <?php disabled(empty($publisher['ok'])); ?>>2. Publicar ZIP guardado</button></div>
                  </form>
                <?php elseif (($assistedState['status'] ?? '') === 'blob_ready'): ?>
                  <div class="ffrm-9db-alert">O Blob privado foi confirmado. O pedido de publicação no catálogo ainda não começou, e pode ser retomado com aprovação explícita.</div>
                  <form method="post" class="ffrm-actions">
                    <?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?>
                    <input type="hidden" name="staged_token" value="<?php echo esc_attr((string)$assistedState['token']); ?>">
                    <label class="ffrm-check"><input type="checkbox" name="confirm_publish" value="1"><span>Confirmo a retoma do catálogo</span></label>
                    <button class="button button-primary" name="ff_release_action" value="resume_staged_catalog">Retomar etapa do catálogo</button>
                  </form>
                <?php elseif (($assistedState['status'] ?? '') !== 'completed'): ?>
                  <div class="ffrm-9db-alert">A operação pode ter sido recebida pelo serviço remoto. O assistente não repete pedidos cuja conclusão seja incerta. Usa Verificar no catálogo, sem publicar novamente.</div>
                <?php else: ?>
                  <div class="ffrm-note"><strong>Publicação confirmada.</strong> Continua a instalação pelo WordPress e as verificações 6B, Fase 6 e certificação Stable. O assistente não as executa.</div>
                <?php endif; ?>
                <?php if (in_array((string)$assistedState['status'], ['upload_unknown','catalog_unknown','catalog_confirmed','blob_ready'], true)): ?>
                  <form method="post" class="ffrm-actions" style="margin-top:13px">
                    <?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?>
                    <input type="hidden" name="staged_token" value="<?php echo esc_attr((string)$assistedState['token']); ?>">
                    <button class="button" name="ff_release_action" value="reconcile_staged">Verificar no catálogo, sem repetir publicação</button>
                  </form>
                <?php endif; ?>
                <details style="margin-top:13px"><summary>Histórico desta operação</summary>
                  <ol class="ffrm-9db-history">
                    <?php foreach (array_reverse((array)($assistedState['events'] ?? [])) as $event): ?>
                      <li><strong><?php echo esc_html((string)($event['at'] ?? '')); ?> UTC</strong>, <?php echo esc_html((string)($event['message'] ?? '')); ?></li>
                    <?php endforeach; ?>
                  </ol>
                </details>
                <form method="post" class="ffrm-actions" style="margin-top:12px">
                  <?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?>
                  <input type="hidden" name="staged_token" value="<?php echo esc_attr((string)$assistedState['token']); ?>">
                  <label class="ffrm-check"><input type="checkbox" name="confirm_discard" value="1"><span>Confirmo que pretendo descartar este ZIP e o seu registo de recuperação</span></label>
                  <button class="button" name="ff_release_action" value="discard_staged">Descartar ZIP privado</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($assistedHistory): ?>
              <details class="ffrm-9db-stage"><summary>Histórico de operações anteriores</summary>
                <table class="ffrm-table"><thead><tr><th>Versão</th><th>Canal</th><th>Estado</th><th>Data UTC</th></tr></thead><tbody>
                  <?php foreach ($assistedHistory as $event): ?><tr>
                    <td><?php echo esc_html((string)($event['version'] ?? '')); ?></td>
                    <td><?php echo esc_html((string)($event['channel'] ?? '')); ?></td>
                    <td><?php echo esc_html((string)($event['status'] ?? '')); ?></td>
                    <td><?php echo esc_html((string)($event['at'] ?? '')); ?></td>
                  </tr><?php endforeach; ?>
                </tbody></table>
              </details>
            <?php endif; ?>
            <p class="ffrm-assisted-note">O upload persistente é privado e temporário, limitado a 24 horas. Falhas ambíguas de rede exigem reconciliação, nunca um reenvio automático. O processo manual continua disponível. O Release Gate, Azure e a certificação Stable permanecem obrigatórios.</p>
          </section>

          <div class="ffrm-kpis" style="margin-bottom:18px">
            <div class="ffrm-kpi"><span>Instalação</span><strong class="<?php echo $install['canonical']?'ffrm-ok':'ffrm-warn'; ?>"><?php echo $install['canonical']?'CANÓNICA':'A NORMALIZAR'; ?></strong><code><?php echo esc_html($install['basename']); ?></code></div>
            <div class="ffrm-kpi"><span>Release Publisher</span><strong class="<?php echo !empty($publisher['ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($publisher['ok'])?'ONLINE':'OFFLINE'; ?></strong><code><?php echo esc_html((string)($publisher['service'] ?? '')); ?></code></div>
            <div class="ffrm-kpi"><span>Runtime</span><strong><?php echo esc_html(defined('FIELDFLOW_VERSION')?FIELDFLOW_VERSION:''); ?></strong><code><?php echo esc_html((string)($updateHealth['current_channel'] ?? PrivateUpdateLifecycle::channel())); ?></code></div>
            <div class="ffrm-kpi"><span>Catálogo</span><strong><?php echo esc_html((string)($updateHealth['latest_version'] ?? 'n/d')); ?></strong><code><?php echo !empty($updateHealth['package_hash_verified'])?'SHA-256 verificado':'aguarda validação'; ?></code></div>
            <div class="ffrm-kpi"><span>Certificação Azure</span><strong class="<?php echo !empty($phase6['stable_certified'])?'ffrm-ok':'ffrm-warn'; ?>"><?php echo !empty($phase6['stable_certified'])?'CERTIFICADA':'PENDENTE'; ?></strong><code><?php echo esc_html((string)($phase6['release'] ?? '')); ?></code></div>
          </div>

          <div class="ffrm-grid">
            <div>
              <section class="ffrm-card">
                <h3>1. Instalação canónica</h3>
                <?php if($install['canonical']): ?>
                  <p class="ffrm-note"><strong>OK.</strong> O plugin está em <code>fieldflow/fieldflow.php</code>. Os próximos ZIPs podem substituir a instalação diretamente.</p>
                <?php else: ?>
                  <p class="ffrm-danger"><strong>Instalação legada detetada.</strong> O plugin está em <code><?php echo esc_html($install['basename']); ?></code>. Isto explica as instalações duplicadas.</p>
                  <?php if($install['target_exists']): ?>
                    <p class="ffrm-danger">Já existe uma pasta <code>wp-content/plugins/fieldflow</code>. A reparação automática está bloqueada até essa cópia ser revista.</p>
                  <?php else: ?>
                    <form method="post" onsubmit="return confirm('Normalizar a pasta do plugin para fieldflow/? O FieldFlow continuará ativo.');">
                      <?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?><input type="hidden" name="ff_release_action" value="repair_install">
                      <button class="button button-primary">Normalizar instalação para fieldflow/</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </section>

              <section class="ffrm-card">
                <h3>2. Release Publisher</h3>
                <p>Endpoint servidor-a-servidor que recebe o ZIP validado e o grava no Blob privado usando Managed Identity.</p>
                <form method="post">
                  <?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?><input type="hidden" name="ff_release_action" value="save_endpoint">
                  <p><label><strong>Endpoint HTTPS</strong><input type="url" name="publisher_base" class="regular-text" style="width:100%;margin-top:6px" value="<?php echo esc_attr(ReleasePublisherClient::baseUrl()); ?>"></label></p>
                  <div class="ffrm-actions"><button class="button">Guardar endpoint</button><span class="ffrm-pill <?php echo !empty($publisher['ok'])?'ok':'bad'; ?>"><?php echo !empty($publisher['ok'])?'Health OK':'Health por resolver'; ?></span></div>
                </form>
              </section>

              <?php if($last): ?>
              <section class="ffrm-card"><h3>Última publicação WordPress</h3><table class="ffrm-table"><tbody>
                <tr><th>Versão</th><td><?php echo esc_html((string)($last['version']??'')); ?></td></tr>
                <tr><th>Canal</th><td><?php echo esc_html(strtoupper((string)($last['channel']??''))); ?></td></tr>
                <tr><th>SHA-256</th><td><code><?php echo esc_html((string)($last['sha256']??'')); ?></code></td></tr>
                <tr><th>Release ID</th><td><?php echo esc_html((string)($last['release_id']??'')); ?></td></tr>
                <tr><th>Data</th><td><?php echo esc_html((string)($last['published_at']??'')); ?> UTC</td></tr>
              </tbody></table></section>
              <?php endif; ?>

              <section class="ffrm-card">
                <h3>4. Autoteste do pipeline</h3>
                <p class="<?php echo !empty($pipeline['complete'])?'ffrm-note':(!empty($pipeline['update_ready'])?'ffrm-note':'ffrm-danger'); ?>"><strong><?php echo esc_html((string)$pipeline['headline']); ?></strong> <?php echo esc_html((string)$pipeline['detail']); ?></p>
                <div class="ffrm-pipeline">
                  <?php foreach ((array)$pipeline['checks'] as $label=>$check): ?>
                    <div class="ffrm-pipeline-row"><span><?php echo esc_html((string)$label); ?></span><span class="ffrm-pill <?php echo !empty($check['ok'])?'ok':'warn'; ?>"><?php echo esc_html((string)$check['text']); ?></span></div>
                  <?php endforeach; ?>
                </div>
              </section>

              <section class="ffrm-card">
                <h3>5. Certification Gate Azure</h3>
                <p class="<?php echo !empty($phase6['stable_certified'])?'ffrm-note':'ffrm-danger'; ?>"><strong><?php echo !empty($phase6['stable_certified'])?'RELEASE CERTIFICADA.':'Release ainda não certificada.'; ?></strong> Uma build nova só fica fechada depois da revalidação 6B, teste de fecho e certificação Stable da própria versão instalada.</p>
                <table class="ffrm-table"><tbody>
                  <tr><th>Release alvo</th><td><?php echo esc_html((string)($phase6['release'] ?? '')); ?></td></tr>
                  <tr><th>Matriz</th><td><?php echo !empty($phase6['matrix_ready'])?'<span class="ffrm-pill ok">READY</span>':'<span class="ffrm-pill warn">REVER</span>'; ?></td></tr>
                  <tr><th>Baseline</th><td><?php echo !empty($phase6['baseline_match'])?'<span class="ffrm-pill ok">MATCH</span>':'<span class="ffrm-pill warn">PENDENTE</span>'; ?></td></tr>
                  <tr><th>Certificada em</th><td><?php echo esc_html((string)($phase6['certified_at'] ?: 'ainda não')); ?></td></tr>
                </tbody></table>
                <div class="ffrm-actions" style="margin-top:14px">
                  <form method="post" style="margin:0"><?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?><input type="hidden" name="ff_release_action" value="phase6_revalidate"><button class="button">1. Revalidar cadeia 6B</button></form>
                  <form method="post" style="margin:0"><?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?><input type="hidden" name="ff_release_action" value="phase6_probe"><button class="button">2. Testar fecho Fase 6</button></form>
                  <form method="post" style="margin:0"><?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?><input type="hidden" name="ff_release_action" value="phase6_certify"><button class="button button-primary">3. Certificar Stable</button></form>
                </div>
                <?php if($certificationResult): ?><div class="ffrm-code" style="margin-top:14px"><?php echo esc_html(wp_json_encode(['ok'=>!empty($certificationResult['ok']),'stage'=>$certificationResult['stage'] ?? '','reason'=>$certificationResult['reason'] ?? '','message'=>$certificationResult['message'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div><?php endif; ?>
              </section>
            </div>

            <div>
              <section class="ffrm-card">
                <h3>Modo manual, Pré-validar ou publicar ZIP</h3>
                <p class="ffrm-note"><strong>Contrato obrigatório:</strong> o ZIP tem de conter apenas uma raiz <code>fieldflow/</code>, com <code>fieldflow/fieldflow.php</code> e um <code>production-manifest.json</code> integralmente válido.</p>
                <form method="post" enctype="multipart/form-data" class="ffrm-form">
                  <?php wp_nonce_field('fieldflow_release_manager','fieldflow_release_nonce'); ?>
                  <label class="wide">ZIP FieldFlow<input type="file" name="release_zip" accept=".zip,application/zip" required></label>
                  <label>Canal<select name="release_channel"><option value="stable">Stable</option><option value="beta">Beta</option></select></label>
                  <label>Requires WordPress<input type="text" name="requires_wp" value="6.4"></label>
                  <label>Requires PHP<input type="text" name="requires_php" value="7.4"></label>
                  <label>Tested WordPress<input type="text" name="tested_wp" value="<?php echo esc_attr(get_bloginfo('version')); ?>"></label>
                  <label class="wide">Notas da release<textarea name="release_notes" rows="3" placeholder="Resumo técnico da release"></textarea></label>
                  <label class="wide ffrm-check"><input type="checkbox" name="confirm_publish" value="1"><span><strong>Confirmo a publicação</strong><small>Necessário apenas para Publicar. A operação envia o ZIP para o Blob privado e regista a release no catálogo licenciado.</small></span></label>
                  <div class="wide ffrm-actions">
                    <button class="button" name="ff_release_action" value="validate_package">Pré-validar ZIP</button>
                    <button class="button button-primary" name="ff_release_action" value="publish_package" <?php disabled(empty($publisher['ok'])); ?>>Validar e publicar</button>
                  </div>
                </form>
              </section>

              <?php if(!empty($packageResult['ok'])): ?>
              <section class="ffrm-card"><h3>Resultado da validação</h3><div class="ffrm-kpis">
                <div class="ffrm-kpi"><span>Versão</span><strong><?php echo esc_html((string)($packageResult['version']??'')); ?></strong></div>
                <div class="ffrm-kpi"><span>Raiz</span><strong class="<?php echo !empty($packageResult['canonical_root'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['canonical_root'])?'fieldflow/ OK':'INVÁLIDA'; ?></strong></div>
                <div class="ffrm-kpi"><span>Manifest</span><strong class="<?php echo !empty($packageResult['manifest_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['manifest_ok'])?'ÍNTEGRO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Offline runtime</span><strong class="<?php echo (!empty($packageResult['offline_runtime_identity_ok']) && !empty($packageResult['offline_runtime_reference_ok']))?'ffrm-ok':'ffrm-bad'; ?>"><?php echo (!empty($packageResult['offline_runtime_identity_ok']) && !empty($packageResult['offline_runtime_reference_ok']))?'ALINHADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Frontend TypeScript</span><strong class="<?php echo (!empty($packageResult['frontend_build_identity_ok']) && !empty($packageResult['frontend_build_reference_ok']))?'ffrm-ok':'ffrm-bad'; ?>"><?php echo (!empty($packageResult['frontend_build_identity_ok']) && !empty($packageResult['frontend_build_reference_ok']))?'ALINHADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Release Continuity</span><strong class="<?php echo !empty($packageResult['release_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['release_continuity_ok'])?'N→N+1 OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Backoffice</span><strong class="<?php echo !empty($packageResult['react_backoffice_foundation_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_backoffice_foundation_ok'])?'PILOTO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React BO Shared</span><strong class="<?php echo !empty($packageResult['react_backoffice_shared_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_backoffice_shared_ok'])?'PARTILHADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Devices</span><strong class="<?php echo !empty($packageResult['react_devices_control_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_devices_control_ok'])?'OPERACIONAL':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Analytics</span><strong class="<?php echo !empty($packageResult['react_analytics_foundation_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_analytics_foundation_ok'])?'SEM REFRESH':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Front</span><strong class="<?php echo !empty($packageResult['react_front_form_wizard_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_front_form_wizard_ok'])?'FORM PILOT':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Front Wave</span><strong class="<?php echo !empty($packageResult['react_front_operational_wave_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_front_operational_wave_ok'])?'OPERAÇÃO REACT':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Simple Fields</span><strong class="<?php echo !empty($packageResult['react_simple_field_controls_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_simple_field_controls_ok'])?'CONTROLOS REACT':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Choice + Special</span><strong class="<?php echo !empty($packageResult['react_choice_special_wave_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_choice_special_wave_ok'])?'FORM AVANÇADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Modernization Closure</span><strong class="<?php echo !empty($packageResult['modernization_stable_closure_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['modernization_stable_closure_ok'])?'FECHADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Front Full Width</span><strong class="<?php echo !empty($packageResult['front_fullwidth_navigation_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['front_fullwidth_navigation_ok'])?'CONSOLIDADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Operational Modules</span><strong class="<?php echo !empty($packageResult['react_operational_modules_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_operational_modules_ok'])?'7 MÓDULOS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Operational Action Hardening</span><strong class="<?php echo !empty($packageResult['operational_action_hardening_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['operational_action_hardening_ok'])?'AÇÕES OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Briefing Tabs Hardening</span><strong class="<?php echo !empty($packageResult['briefing_progress_tabs_hardening_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['briefing_progress_tabs_hardening_ok'])?'ABAS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Form Completion</span><strong class="<?php echo !empty($packageResult['react_form_completion_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_form_completion_ok'])?'FORM COMPLETO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Runtime Identity</span><strong class="<?php echo !empty($packageResult['certification_runtime_identity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['certification_runtime_identity_ok'])?'ALINHADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Historical Gate Markers</span><strong class="<?php echo !empty($packageResult['release_gate_historical_markers_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['release_gate_historical_markers_ok'])?'DESACOPLADOS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Front Transition Stability</span><strong class="<?php echo !empty($packageResult['front_transition_stability_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['front_transition_stability_ok'])?'SEM FLASH':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Portal Stability</span><strong class="<?php echo !empty($packageResult['client_portal_shell_stability_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_portal_shell_stability_ok'])?'SHELL ESTÁVEL':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Portal Continuity</span><strong class="<?php echo !empty($packageResult['client_portal_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_portal_continuity_ok'])?'CONTINUIDADE OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Client Wave 1</span><strong class="<?php echo !empty($packageResult['react_client_wave1_analytics_stability_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_client_wave1_analytics_stability_ok'])?'3 MÓDULOS + ANALYTICS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>React Client Wave 2</span><strong class="<?php echo !empty($packageResult['react_client_wave2_cross_portal_isolation_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['react_client_wave2_cross_portal_isolation_ok'])?'4 MÓDULOS + ISOLAMENTO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Dual Portal Full Width</span><strong class="<?php echo !empty($packageResult['app_resume_dual_portal_fullwidth_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['app_resume_dual_portal_fullwidth_ok'])?'RESUME + FULL WIDTH':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Dual Portal Parity v2</span><strong class="<?php echo !empty($packageResult['dual_portal_fullwidth_parity_resume_v2_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['dual_portal_fullwidth_parity_resume_v2_ok'])?'FULL WIDTH + RESUME V2':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>UI Composition &amp; Resume Diagnostics</span><strong class="<?php echo !empty($packageResult['ui_composition_resume_diagnostics_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['ui_composition_resume_diagnostics_ok'])?'SURFACES + DIAGNOSTICS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Shared Portal Paint Stability</span><strong class="<?php echo !empty($packageResult['shared_portal_paint_stability_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['shared_portal_paint_stability_ok'])?'FIRST PAINT + TABS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Portal Entry Sync Visual</span><strong class="<?php echo !empty($packageResult['portal_entry_sync_visual_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['portal_entry_sync_visual_continuity_ok'])?'QUIET SYNC + CONTINUITY':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Mail Dispatch Foundation</span><strong class="<?php echo !empty($packageResult['mail_dispatch_certification_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['mail_dispatch_certification_continuity_ok'])?'TRANSPORTE ÚNICO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Mail Reliability Outbox</span><strong class="<?php echo !empty($packageResult['mail_reliability_outbox_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['mail_reliability_outbox_ok'])?'FIÁVEL':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Asset Successor Continuity</span><strong class="<?php echo !empty($packageResult['certification_asset_successor_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['certification_asset_successor_continuity_ok'])?'SUCESSÃO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Runtime &amp; Database Performance</span><strong class="<?php echo !empty($packageResult['runtime_database_performance_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['runtime_database_performance_ok'])?'BATCH + DB READ-ONLY':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Security &amp; Storage Hardening</span><strong class="<?php echo !empty($packageResult['security_storage_hardening_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['security_storage_hardening_ok'])?'SCOPE + STORAGE + WEBVIEW':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>AI Governance &amp; Modernization</span><strong class="<?php echo !empty($packageResult['ai_governance_modernization_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['ai_governance_modernization_ok'])?'FINOPS + REACT V2':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>AI Certification Continuity</span><strong class="<?php echo !empty($packageResult['ai_governance_certification_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['ai_governance_certification_continuity_ok'])?'OWNERSHIP OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Operational Modernization</span><strong class="<?php echo !empty($packageResult['operational_modernization_wave_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['operational_modernization_wave_ok'])?'4 MÓDULOS + EVENT SHELL':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Frontend Runtime Continuity</span><strong class="<?php echo !empty($packageResult['frontend_runtime_certification_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['frontend_runtime_certification_continuity_ok'])?'IDENTIDADE + PARIDADE':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Operational Modernization Wave 2</span><strong class="<?php echo !empty($packageResult['operational_modernization_wave2_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['operational_modernization_wave2_ok'])?'ACADEMY + DESPESAS + ANALYTICS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Field Form Terrain Continuity</span><strong class="<?php echo !empty($packageResult['field_form_terrain_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['field_form_terrain_continuity_ok'])?'CARDEX + DRAFT + RESUME':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Field Session &amp; Form Continuity</span><strong class="<?php echo !empty($packageResult['field_session_form_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['field_session_form_continuity_ok'])?'SESSÃO + RESUME + ENCOMENDA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Certification Gate Continuity 9T</span><strong class="<?php echo !empty($packageResult['certification_gate_continuity_9t_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['certification_gate_continuity_9t_ok'])?'GATES SUCCESSOR-SAFE':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Order Source &amp; Cardex Consistency 9U</span><strong class="<?php echo !empty($packageResult['order_source_cardex_consistency_9u_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['order_source_cardex_consistency_9u_ok'])?'MANUAL + CARDEX + FRENTES':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Canonical Answer Semantics 9V</span><strong class="<?php echo !empty($packageResult['canonical_answer_semantics_9v_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['canonical_answer_semantics_9v_ok'])?'WEB + MOBILE + OFFLINE':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Visit Closure &amp; Daily Completion Mail 9W</span><strong class="<?php echo !empty($packageResult['visit_closure_daily_mail_9w_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['visit_closure_daily_mail_9w_ok'])?'VISITA + FECHO + EMAIL':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Certification Continuity Visit Closure 9X</span><strong class="<?php echo !empty($packageResult['certification_continuity_visit_closure_9x_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['certification_continuity_visit_closure_9x_ok'])?'GATE + PACKAGE PARITY':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Submission Status &amp; Operational Day 9Y</span><strong class="<?php echo !empty($packageResult['submission_status_operational_day_9y_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['submission_status_operational_day_9y_ok'])?'STATUS + REPORT DAY':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Submission Corrections &amp; Audit Trail 9Z</span><strong class="<?php echo !empty($packageResult['submission_corrections_audit_9z_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['submission_corrections_audit_9z_ok'])?'AUDIT + VOID + RECONCILE':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Field Resume Navigation Continuity 9AA</span><strong class="<?php echo !empty($packageResult['field_resume_navigation_9aa_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['field_resume_navigation_9aa_ok'])?'CAMERA + RESUME + CONTEXTO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Report Selection &amp; Media Continuity 9AB</span><strong class="<?php echo !empty($packageResult['report_selection_media_9ab_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['report_selection_media_9ab_ok'])?'LOJA + FOTO + PASSO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Resume Certification &amp; Media Integrity 9AC</span><strong class="<?php echo !empty($packageResult['resume_certification_media_9ac_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['resume_certification_media_9ac_ok'])?'GATE + ORIGINAL + DATA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Form Viewport &amp; Shopping Delivery Integrity 9AD</span><strong class="<?php echo !empty($packageResult['form_viewport_shopping_delivery_9ad_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['form_viewport_shopping_delivery_9ad_ok'])?'SCROLL + XLSX/PDF':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Canonical Reporting &amp; Delivery Reliability 9AE</span><strong class="<?php echo !empty($packageResult['canonical_reporting_delivery_9ae_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['canonical_reporting_delivery_9ae_ok'])?'CANONICAL + MAIL + VIEWPORT':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Certification, Viewport Lock &amp; Mail Recovery 9AF</span><strong class="<?php echo !empty($packageResult['certification_viewport_mail_9af_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['certification_viewport_mail_9af_ok'])?'GATE + SCROLL + TEMPLATE':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Mail Flow Finalization &amp; True Viewport Freeze 9AG</span><strong class="<?php echo !empty($packageResult['mail_flow_viewport_9ag_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['mail_flow_viewport_9ag_ok'])?'MAILS + ANEXOS + VIEWPORT':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Single Anchor Viewport Continuity 9AH</span><strong class="<?php echo !empty($packageResult['single_anchor_viewport_9ah_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['single_anchor_viewport_9ah_ok'])?'ÂNCORA ÚNICA + RESUME':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Mail Intelligence &amp; Daily Commercial Reporting 9AI</span><strong class="<?php echo !empty($packageResult['mail_intelligence_daily_reporting_9ai_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['mail_intelligence_daily_reporting_9ai_ok'])?'CLIENTE + MERCH + EXCEL':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Briefing Store Scope Reporting Continuity 9AJ</span><strong class="<?php echo !empty($packageResult['briefing_store_scope_reporting_9aj_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['briefing_store_scope_reporting_9aj_ok'])?'MAPPING + ROTA + ALERTAS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Daily Mobility Cost Reporting Continuity 9AK</span><strong class="<?php echo !empty($packageResult['daily_mobility_cost_reporting_9ak_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['daily_mobility_cost_reporting_9ak_ok'])?'KM + CUSTOS + ACUMULADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Known Mobility Reporting Recovery 9AL</span><strong class="<?php echo !empty($packageResult['known_mobility_reporting_recovery_9al_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['known_mobility_reporting_recovery_9al_ok'])?'KM CONHECIDOS + ESTIMATIVA PARCIAL':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Full Route Mobility Reporting Reconciliation 9AM</span><strong class="<?php echo !empty($packageResult['full_route_mobility_reporting_9am_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['full_route_mobility_reporting_9am_ok'])?'ACESSO + PDVS + REGRESSO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Routing Endpoint Geographic Integrity 9AN</span><strong class="<?php echo !empty($packageResult['routing_endpoint_geographic_integrity_9an_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['routing_endpoint_geographic_integrity_9an_ok'])?'PAÍS + ENDPOINTS + PDVS':'FALHOU'; ?></strong></div>
                <!-- Route & Mobility Dashboard Widgets 9AO -->
                <div class="ffrm-kpi"><span>Route &amp; Mobility Dashboard Widgets 9AO</span><strong class="<?php echo !empty($packageResult['route_mobility_dashboard_widgets_9ao_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['route_mobility_dashboard_widgets_9ao_ok'])?'ROTAS + KM + CUSTOS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Report Status Canonical Sync 9AP</span><strong class="<?php echo !empty($packageResult['report_status_canonical_sync_9ap_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['report_status_canonical_sync_9ap_ok'])?'REPORTAR + MINHA ROTA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Condition Graph Integrity 9AQ</span><strong class="<?php echo !empty($packageResult['condition_graph_integrity_9aq_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['condition_graph_integrity_9aq_ok'])?'CONTROLADA POR + AUDITORIA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>WordPress Media Isolation 9AR</span><strong class="<?php echo !empty($packageResult['wordpress_media_isolation_9ar_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['wordpress_media_isolation_9ar_ok'])?'MEDIA WP ISOLADA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>My Route Periodicity Control 9AS</span><strong class="<?php echo !empty($packageResult['my_route_periodicity_9as_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['my_route_periodicity_9as_ok'])?'P1-P4 + COBERTURA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>My Route Periodicity Action UX 9AT</span><strong class="<?php echo !empty($packageResult['my_route_periodicity_action_9at_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['my_route_periodicity_action_9at_ok'])?'RISCO + PLANEAR':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Release Manager Publication Receipt Continuity 9AU</span><strong class="<?php echo !empty($packageResult['release_manager_publication_receipt_9au_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['release_manager_publication_receipt_9au_ok'])?'RECIBO + SHA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Cost Date Scope &amp; Route Totals 9AV</span><strong class="<?php echo !empty($packageResult['client_cost_date_scope_route_totals_9av_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_cost_date_scope_route_totals_9av_ok'])?'DATAS + TOTAIS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Route Financial Breakdown &amp; Audience Analytics 9AW</span><strong class="<?php echo !empty($packageResult['route_financial_audience_analytics_9aw_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['route_financial_audience_analytics_9aw_ok'])?'CUSTOS + AUDIÊNCIA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Portal Runtime Scope Continuity 9AX</span><strong class="<?php echo !empty($packageResult['client_portal_runtime_scope_9ax_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_portal_runtime_scope_9ax_ok'])?'SCOPE + MOUNT':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Portal Filter Authority Rebase 9AY</span><strong class="<?php echo !empty($packageResult['client_portal_filter_authority_9ay_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_portal_filter_authority_9ay_ok'])?'DATAS LIVRES + REBASE':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Route Calendar Daily Total &amp; Mobile Portal Features 9AZ</span><strong class="<?php echo !empty($packageResult['route_calendar_mobile_portal_features_9az_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['route_calendar_mobile_portal_features_9az_ok'])?'TOTAL DIA + 2 PORTAIS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Route Hero Date Authority 9BA</span><strong class="<?php echo !empty($packageResult['client_route_hero_date_authority_9ba_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_route_hero_date_authority_9ba_ok'])?'DE/ATÉ ÚNICO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Planner Assigned Store Scope Continuity 9BB</span><strong class="<?php echo !empty($packageResult['planner_assigned_store_scope_9bb_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['planner_assigned_store_scope_9bb_ok'])?'LOJAS + SCOPE':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Planner Canonical Assignment Catalog 9BC</span><strong class="<?php echo !empty($packageResult['planner_canonical_assignment_9bc_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['planner_canonical_assignment_9bc_ok'])?'TODAS + REPETIÇÃO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Visit Memory Refresh &amp; Version Ownership 9BI</span><strong class="<?php echo !empty($packageResult['visit_memory_refresh_9bi_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['visit_memory_refresh_9bi_ok'])?'MEMÓRIA ATUALIZADA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Store Identity Authority &amp; Historical Media Isolation 9BJ</span><strong class="<?php echo !empty($packageResult['store_identity_authority_9bj_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['store_identity_authority_9bj_ok'])?'LOJA CANÓNICA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Static Routing &amp; Cost Authority 9BK</span><strong class="<?php echo !empty($packageResult['static_routing_cost_authority_9bk_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['static_routing_cost_authority_9bk_ok'])?'GOOGLE SOB DEMANDA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Toll Cost Intelligence 9BL</span><strong class="<?php echo !empty($packageResult['toll_cost_intelligence_9bl_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['toll_cost_intelligence_9bl_ok'])?'PORTAGENS LOW COST':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Places &amp; Geocoding Cost Authority 9BM</span><strong class="<?php echo !empty($packageResult['places_geocoding_cost_authority_9bm_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['places_geocoding_cost_authority_9bm_ok'])?'PLACES LOW COST':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Google Cost Control Center 9BN</span><strong class="<?php echo !empty($packageResult['google_cost_control_center_9bn_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['google_cost_control_center_9bn_ok'])?'CUSTOS PROTEGIDOS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Daily Operational Priority 9BO</span><strong class="<?php echo !empty($packageResult['daily_operational_priority_9bo_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['daily_operational_priority_9bo_ok'])?'PRIORIDADES OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Daily Execution &amp; Exception Control 9BP</span><strong class="<?php echo !empty($packageResult['daily_execution_exception_9bp_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['daily_execution_exception_9bp_ok'])?'EXECUÇÃO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Order Mail &amp; Visit Reopen Safety 9BQ</span><strong class="<?php echo !empty($packageResult['order_mail_visit_reopen_9bq_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['order_mail_visit_reopen_9bq_ok'])?'MAIL + REABRIR OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Certification Foundation Continuity 9BR</span><strong class="<?php echo !empty($packageResult['certification_foundation_continuity_9br_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['certification_foundation_continuity_9br_ok'])?'6B.1 CONTÍNUA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Executive Analytics &amp; Route Financial Visibility 9BS</span><strong class="<?php echo !empty($packageResult['executive_analytics_route_financial_9bs_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['executive_analytics_route_financial_9bs_ok'])?'EXECUTIVO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Mobile Analytics Readability &amp; Evidence Density 9BT</span><strong class="<?php echo !empty($packageResult['mobile_analytics_readability_evidence_9bt_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['mobile_analytics_readability_evidence_9bt_ok'])?'MOBILE OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Reports Runtime Continuity 9BU</span><strong class="<?php echo !empty($packageResult['client_reports_runtime_9bu_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_reports_runtime_9bu_ok'])?'REPORTS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Portal Export Runtime &amp; Certification Parity 9BV</span><strong class="<?php echo !empty($packageResult['portal_export_runtime_cert_parity_9bv_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['portal_export_runtime_cert_parity_9bv_ok'])?'EXPORTS + GATE OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Client Report Header Scope &amp; Briefing Assignment 9BW</span><strong class="<?php echo !empty($packageResult['client_report_scope_briefing_assignment_9bw_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['client_report_scope_briefing_assignment_9bw_ok'])?'SCOPE + BRIEFING OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Visit-Centric Reporting &amp; Briefing Continuity 9BX</span><strong class="<?php echo !empty($packageResult['visit_centric_reporting_briefing_9bx_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['visit_centric_reporting_briefing_9bx_ok'])?'VISITAS + BRIEFING OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Multilingual Client Locale Authority 9BY</span><strong class="<?php echo !empty($packageResult['multilingual_client_locale_authority_9by_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['multilingual_client_locale_authority_9by_ok'])?'MULTI IDIOMA OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Premium Locale &amp; Visit Memory Continuity 9BZ</span><strong class="<?php echo !empty($packageResult['premium_locale_visit_memory_9bz_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['premium_locale_visit_memory_9bz_ok'])?'GLOBE 3D + HISTÓRICO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Certification Marker Continuity 9CA</span><strong class="<?php echo !empty($packageResult['certification_marker_continuity_9ca_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['certification_marker_continuity_9ca_ok'])?'9BD + 9BZ OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Locale Brand &amp; Associated Client Scope Continuity 9CB</span><strong class="<?php echo !empty($packageResult['locale_brand_associated_client_scope_9cb_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['locale_brand_associated_client_scope_9cb_ok'])?'MARCA + CLIENTES OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>App Shell &amp; Browser-like Translation Continuity 9CC</span><strong class="<?php echo !empty($packageResult['app_shell_browserlike_translation_9cc_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['app_shell_browserlike_translation_9cc_ok'])?'APP + TRADUÇÃO TOTAL OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Adaptive Locale Dock Continuity 9CD</span><strong class="<?php echo !empty($packageResult['adaptive_locale_dock_9cd_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['adaptive_locale_dock_9cd_ok'])?'AUTO-HIDE PREMIUM OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Analytics Header Date Export Authority 9CE</span><strong class="<?php echo !empty($packageResult['analytics_header_date_export_authority_9ce_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['analytics_header_date_export_authority_9ce_ok'])?'DATAS EXPORT OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Expense Statement PDF &amp; Receipt Authority 9CF</span><strong class="<?php echo !empty($packageResult['expense_statement_pdf_receipt_authority_9cf_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['expense_statement_pdf_receipt_authority_9cf_ok'])?'FOLHA DESPESAS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Expense Statement Mobile Ticket Delivery 9CG</span><strong class="<?php echo !empty($packageResult['expense_statement_mobile_ticket_delivery_9cg_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['expense_statement_mobile_ticket_delivery_9cg_ok'])?'PDF APP OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Expense Settlement, Payment &amp; Signature Authority 9CH</span><strong class="<?php echo !empty($packageResult['expense_settlement_signature_9ch_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['expense_settlement_signature_9ch_ok'])?'PAGAMENTO + ASSINATURA OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Visit Closure Selector Return &amp; Route First Paint 9CI</span><strong class="<?php echo !empty($packageResult['visit_closure_selector_return_9ci_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['visit_closure_selector_return_9ci_ok'])?'FECHO → SELETOR OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Security &amp; Hygiene Authority 9CJ</span><strong class="<?php echo !empty($packageResult['security_hygiene_authority_9cj_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['security_hygiene_authority_9cj_ok'])?'SCOPE + TOOLS + IA OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Planner &amp; Commercial Google Cost Authority 9CK</span><strong class="<?php echo !empty($packageResult['planner_google_cost_authority_9ck_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['planner_google_cost_authority_9ck_ok'])?'GOOGLE SÓ A PEDIDO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Visual Product Catalog 9CL</span><strong class="<?php echo !empty($packageResult['visual_product_catalog_9cl_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['visual_product_catalog_9cl_ok'])?'CATÁLOGO VISUAL OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Fronts Mobile Layout &amp; Catalog Visuals 9CM</span><strong class="<?php echo !empty($packageResult['fronts_mobile_catalog_visual_9cm_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_mobile_catalog_visual_9cm_ok'])?'FRENTES COMPACTAS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Catalog Cross-Client Reuse 9CN</span><strong class="<?php echo !empty($packageResult['catalog_cross_client_reuse_9cn_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['catalog_cross_client_reuse_9cn_ok'])?'CATÁLOGO REUTILIZÁVEL OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Fronts Camera Reading 9CO</span><strong class="<?php echo !empty($packageResult['fronts_camera_reading_9co_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_camera_reading_9co_ok'])?'LEITURA POR CÂMARA OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Catalog General Identity 9CP</span><strong class="<?php echo !empty($packageResult['catalog_general_identity_9cp_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['catalog_general_identity_9cp_ok'])?'CATÁLOGO GERAL OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Front Theme Camera Live 9CQ</span><strong class="<?php echo !empty($packageResult['front_theme_camera_live_9cq_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['front_theme_camera_live_9cq_ok'])?'FRONT E CÂMARA AO VIVO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Media Capture Resilience 9CR</span><strong class="<?php echo !empty($packageResult['media_capture_resilience_9cr_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['media_capture_resilience_9cr_ok'])?'FOTOGRAFIAS RESILIENTES OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Contagem multimodal e custos 9CS</span><strong class="<?php echo !empty($packageResult['fronts_safe_multimodal_9cs_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_safe_multimodal_9cs_ok'])?'CONTAGEM + QUOTA OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Rever fotografia e custos IA 9CU</span><strong class="<?php echo !empty($packageResult['fronts_capture_review_9cu_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_capture_review_9cu_ok'])?'CÂMARA CONTROLADA OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Reconhecimento de produtos com prova visual 9CW</span><strong class="<?php echo !empty($packageResult['fronts_visual_evidence_9cw_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_visual_evidence_9cw_ok'])?'IDENTIDADE DE SKU OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Identidade de SKU e sugestão assistida 9CX</span><strong class="<?php echo !empty($packageResult['fronts_sku_identity_9cx_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_sku_identity_9cx_ok'])?'CARDEx OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Leitura independente do catalogo 9CY</span><strong class="<?php echo !empty($packageResult['fronts_blind_inventory_9cy_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_blind_inventory_9cy_ok'])?'SKU OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Consumo de IA e FinOps por modelo 9CZ</span><strong class="<?php echo !empty($packageResult['fronts_ai_cost_metering_9cz_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['fronts_ai_cost_metering_9cz_ok'])?'TOKENS + TARIFAS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Proteção de eliminação de dados 9CT</span><strong class="<?php echo !empty($packageResult['form_deletion_confirmation_9ct_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['form_deletion_confirmation_9ct_ok'])?'CONFIRMAÇÃO SEGURA OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Latest Visit History &amp; Media/Order Isolation 9BH</span><strong class="<?php echo !empty($packageResult['latest_visit_history_isolation_9bh_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['latest_visit_history_isolation_9bh_ok'])?'HISTÓRICO ISOLADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Form Visit Memory &amp; Fronts Continuity 9BD</span><strong class="<?php echo !empty($packageResult['form_visit_memory_fronts_9bd_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['form_visit_memory_fronts_9bd_ok'])?'HISTÓRICO + FRENTES':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Planner Free Warning Continuity 9BE</span><strong class="<?php echo !empty($packageResult['planner_free_warning_9be_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['planner_free_warning_9be_ok'])?'LIVRE + ALERTAS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Visit Exception &amp; Justified Non Visit Continuity 9BF</span><strong class="<?php echo !empty($packageResult['visit_exception_justified_nonvisit_9bf_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['visit_exception_justified_nonvisit_9bf_ok'])?'JUSTIFICADAS + AUDIT':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Open Day Planning &amp; Visit Completion Sync 9BG</span><strong class="<?php echo !empty($packageResult['open_day_planning_visit_completion_9bg_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['open_day_planning_visit_completion_9bg_ok'])?'DIA ABERTO + CONCLUÍDA':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Mobility Cost Intelligence</span><strong class="<?php echo !empty($packageResult['mobility_cost_intelligence_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['mobility_cost_intelligence_ok'])?'MOBILIDADE OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Mobility Integrity</span><strong class="<?php echo !empty($packageResult['mobility_integrity_canonical_metrics_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['mobility_integrity_canonical_metrics_ok'])?'MÉTRICAS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Route Distance Integrity</span><strong class="<?php echo !empty($packageResult['route_endpoint_distance_integrity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['route_endpoint_distance_integrity_ok'])?'PERCURSO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Endpoint Provenance</span><strong class="<?php echo !empty($packageResult['route_endpoint_provenance_reconciliation_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['route_endpoint_provenance_reconciliation_ok'])?'ENDPOINTS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Financial Continuity</span><strong class="<?php echo !empty($packageResult['route_financial_continuity_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['route_financial_continuity_ok'])?'CUSTOS OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Typed Offline Core</span><strong class="<?php echo !empty($packageResult['typed_offline_core_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['typed_offline_core_ok'])?'LIGADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Typed Draft & Media</span><strong class="<?php echo !empty($packageResult['typed_draft_media_core_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['typed_draft_media_core_ok'])?'LIGADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Typed Form State</span><strong class="<?php echo !empty($packageResult['typed_form_conditional_core_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['typed_form_conditional_core_ok'])?'PARIDADE OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Typed Submission</span><strong class="<?php echo !empty($packageResult['typed_submission_validation_core_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['typed_submission_validation_core_ok'])?'PARIDADE OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Typed Specialized Validation</span><strong class="<?php echo !empty($packageResult['typed_specialized_validation_core_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['typed_specialized_validation_core_ok'])?'PARIDADE OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Submission Application Service</span><strong class="<?php echo !empty($packageResult['submission_application_service_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['submission_application_service_ok'])?'UNIFICADO':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Submission Side Effects</span><strong class="<?php echo !empty($packageResult['submission_side_effects_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['submission_side_effects_ok'])?'ISOLADOS':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Sugestão email guard</span><strong class="<?php echo !empty($packageResult['order_suggestion_mail_eligibility_ok'])?'ffrm-ok':'ffrm-bad'; ?>"><?php echo !empty($packageResult['order_suggestion_mail_eligibility_ok'])?'BLOQUEIO OK':'FALHOU'; ?></strong></div>
                <div class="ffrm-kpi"><span>Ficheiros</span><strong><?php echo number_format_i18n((int)($packageResult['file_count']??0)); ?></strong></div>
              </div><p><strong>SHA-256</strong></p><div class="ffrm-code"><?php echo esc_html((string)($packageResult['sha256']??'')); ?></div></section>
              <?php endif; ?>

              <section class="ffrm-card"><h3>Fluxo daqui para a frente</h3><p><strong>Gerar ZIP → Release Manager → Validar e publicar → WordPress recebe o update → Revalidar 6B → Testar Fase 6 → Certificar Stable.</strong></p><p>O Cloud Shell fica apenas como recuperação de emergência. O browser nunca recebe Storage keys, Shared Secret nem Admin Secret.</p></section>
            </div>
          </div>
        </div>
        <?php
        AdminPage::close();
    }

    private static function pipelineState(array $install, array $publisher, array $last, array $updateHealth, array $phase6): array {
        $runtime = defined('FIELDFLOW_VERSION') ? sanitize_text_field((string)FIELDFLOW_VERSION) : '';
        $latest = sanitize_text_field((string)($updateHealth['latest_version'] ?? ''));
        $lastVersion = sanitize_text_field((string)($last['version'] ?? ''));
        $publishedAt = sanitize_text_field((string)($last['published_at'] ?? ''));
        $publishedFromWordPress = $lastVersion !== '' && $publishedAt !== '';
        $catalogHasLast = $publishedFromWordPress && $latest !== '' && version_compare($latest, $lastVersion, '>=');
        $runtimeHasLast = $publishedFromWordPress && $runtime !== '' && version_compare($runtime, $lastVersion, '>=');
        $lastSha = strtolower(sanitize_text_field((string)($last['sha256'] ?? '')));
        $publicationVerified = $publishedFromWordPress && preg_match('/^[a-f0-9]{64}$/', $lastSha) === 1;
        $phase6Release = sanitize_text_field((string)($phase6['release'] ?? ''));
        $phase6Certified = !empty($phase6['stable_certified']) && $runtime !== '' && hash_equals($runtime, $phase6Release);
        $phase6Baseline = !empty($phase6['baseline_match']);
        $complete = !empty($install['canonical']) && !empty($publisher['ok']) && $publishedFromWordPress && $catalogHasLast && $runtimeHasLast && $publicationVerified && $phase6Certified && $phase6Baseline;
        $updateReady = !$complete && !empty($install['canonical']) && !empty($publisher['ok']) && $publishedFromWordPress && $catalogHasLast && !$runtimeHasLast;
        $certificationPending = !$complete && $runtimeHasLast && !$phase6Certified;

        if ($complete) {
            $headline = 'Release publicada, instalada e certificada.';
            $detail = 'O ciclo só é considerado concluído porque a Fase 6 Stable da versão ativa também está certificada.';
        } elseif ($updateReady) {
            $headline = 'Release publicada, update pendente no WordPress.';
            $detail = 'O catálogo já contém a release publicada pelo WordPress. Instala o update e depois executa o Certification Gate Azure.';
        } elseif ($certificationPending) {
            $headline = 'Runtime atualizado, certificação Azure pendente.';
            $detail = 'A versão publicada já está ativa. Revalida 6B, testa o fecho da Fase 6 e certifica a Stable antes de considerar a release fechada.';
        } elseif (!$publishedFromWordPress) {
            $headline = 'Pronto para a primeira publicação WordPress.';
            $detail = 'Publica uma versão superior pelo Release Manager para criar a primeira evidência end-to-end.';
        } else {
            $headline = 'Pipeline parcialmente validado.';
            $detail = 'Revê os checks abaixo antes de considerar o ciclo de release concluído.';
        }

        return [
            'complete'=>$complete,
            'update_ready'=>$updateReady,
            'certification_pending'=>$certificationPending,
            'headline'=>$headline,
            'detail'=>$detail,
            'checks'=>[
                'Instalação canónica'=>['ok'=>!empty($install['canonical']),'text'=>!empty($install['canonical'])?'OK':'REVER'],
                'Release Publisher'=>['ok'=>!empty($publisher['ok']),'text'=>!empty($publisher['ok'])?'HEALTH OK':'REVER'],
                'Publicação via WordPress'=>['ok'=>$publishedFromWordPress,'text'=>$publishedFromWordPress?($lastVersion . ' REGISTADA'):'AINDA NÃO'],
                'Catálogo contém a publicação'=>['ok'=>$catalogHasLast,'text'=>$catalogHasLast?($latest . ' OK'):'AGUARDA'],
                'SHA-256 da publicação'=>['ok'=>$publicationVerified,'text'=>$publicationVerified?'CONFIRMADO':'AGUARDA'],
                'Runtime recebeu a publicação'=>['ok'=>$runtimeHasLast,'text'=>$runtimeHasLast?($runtime . ' ATIVO'):'UPDATE PENDENTE'],
                'Phase 6 baseline'=>['ok'=>$phase6Baseline,'text'=>$phase6Baseline?'FINGERPRINT MATCH':'REVALIDAR'],
                'Stable da versão ativa'=>['ok'=>$phase6Certified,'text'=>$phase6Certified?'CERTIFICADA':'CERTIFICAÇÃO PENDENTE'],
            ],
        ];
    }

    private static function canonicalInstallState(): array {
        $main = defined('FIELDFLOW_PATH') ? FIELDFLOW_PATH . 'fieldflow.php' : dirname(__DIR__, 2) . '/fieldflow.php';
        $basename = plugin_basename($main);
        $target = trailingslashit(WP_PLUGIN_DIR) . 'fieldflow';
        $current = rtrim(dirname($main), '/\\');
        $canonical = hash_equals('fieldflow/fieldflow.php', str_replace('\\','/',$basename));
        return [
            'canonical'=>$canonical,
            'basename'=>$basename,
            'current_dir'=>$current,
            'target_dir'=>$target,
            'target_exists'=>!$canonical && file_exists($target),
        ];
    }

    private static function repairCanonicalInstall(): string {
        $state = self::canonicalInstallState();
        if (!empty($state['canonical'])) return 'A instalação FieldFlow já está na pasta canónica.';
        $current = (string)$state['current_dir'];
        $target = (string)$state['target_dir'];
        if (!is_dir($current) || !is_file($current . '/fieldflow.php')) throw new \RuntimeException('A pasta atual do FieldFlow não pôde ser validada.');
        if (file_exists($target)) throw new \RuntimeException('Já existe wp-content/plugins/fieldflow. Remove ou revê essa cópia antes de normalizar.');
        if (!is_writable(WP_PLUGIN_DIR)) throw new \RuntimeException('A pasta de plugins não permite renomear a instalação.');

        $oldBasename = str_replace('\\','/',(string)$state['basename']);
        if (!@rename($current, $target)) throw new \RuntimeException('O WordPress não conseguiu renomear a pasta do plugin para fieldflow/.');
        $newBasename = 'fieldflow/fieldflow.php';

        $active = (array)get_option('active_plugins', []);
        $changed = false;
        foreach ($active as $idx => $plugin) {
            if (str_replace('\\','/',(string)$plugin) === $oldBasename) {
                $active[$idx] = $newBasename;
                $changed = true;
            }
        }
        if ($changed) update_option('active_plugins', array_values(array_unique($active)));

        if (is_multisite()) {
            $network = (array)get_site_option('active_sitewide_plugins', []);
            if (isset($network[$oldBasename])) {
                $stamp = $network[$oldBasename];
                unset($network[$oldBasename]);
                $network[$newBasename] = $stamp;
                update_site_option('active_sitewide_plugins', $network);
            }
        }
        if (function_exists('wp_clean_plugins_cache')) wp_clean_plugins_cache(true);
        delete_site_transient('update_plugins');
        AuditLog::record('plugin.installation_canonicalized', 'fieldflow_release', 0, ['plugin_basename'=>$oldBasename], ['plugin_basename'=>$newBasename], ['source'=>'backoffice']);
        return 'Instalação normalizada para fieldflow/fieldflow.php. Os próximos updates passam a substituir o mesmo plugin.';
    }
}
