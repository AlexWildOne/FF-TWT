<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\Config;
use RoutesPro\Support\Request;

class Settings {
    const OPT_KEY = 'routespro_settings';

    /**
     * Defaults globais das opções
     */
    private static function defaults(): array {
        return Config::defaults();
    }

    /**
     * Recupera todas as opções (com defaults) ou uma chave específica.
     */
    public static function get($key = null, $default = null) {
        return Config::get($key, $default);
    }

    private static function moneyFromGoogleRoutesTollInfo(array $tollInfo): array {
        $amount = 0.0;
        $currency = 'EUR';
        $prices = $tollInfo['estimatedPrice'] ?? [];
        if (!is_array($prices)) {
            return ['amount' => 0.0, 'currency' => $currency, 'has_price' => false];
        }
        foreach ($prices as $price) {
            if (!is_array($price)) continue;
            $currency = (string)($price['currencyCode'] ?? $currency);
            $units = (float)($price['units'] ?? 0);
            $nanos = (float)($price['nanos'] ?? 0) / 1000000000;
            $amount += $units + $nanos;
        }
        return ['amount' => round(max(0.0, $amount), 2), 'currency' => $currency, 'has_price' => $amount > 0];
    }

    private static function secondsFromGoogleDuration($duration): int {
        if (is_numeric($duration)) return max(0, (int)$duration);
        if (is_string($duration) && preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $m)) {
            return max(0, (int)round((float)$m[1]));
        }
        return 0;
    }

    private static function testGoogleRoutesApi(string $origin, string $destination): array {
        if (!class_exists('\\RoutesPro\\Services\\GoogleRoutes')) {
            return ['ok' => false, 'message' => 'Serviço Google Routes indisponível no plugin.'];
        }
        return \RoutesPro\Services\GoogleRoutes::diagnostic($origin, $destination);
    }

    /**
     * Render da página de Settings (BO)
     */
    public static function render() {
        if (!current_user_can('routespro_manage')) return;

        if (Request::verifyNonce('routespro_settings_categories_nonce', 'routespro_settings_categories')) {
            global $wpdb; $px = $wpdb->prefix . 'routespro_';
            $cat_name = Request::postString('settings_category_name');
            $cat_parent = Request::postInt('settings_category_parent_id') ?: null;
            $cat_type = Request::postString('settings_category_type');
            if ($cat_name !== '') {
                $wpdb->insert($px.'categories', [
                    'parent_id' => $cat_parent,
                    'name' => $cat_name,
                    'slug' => sanitize_title($cat_name),
                    'type' => $cat_type,
                    'is_active' => 1,
                ]);
                echo '<div class="updated notice"><p>Categoria guardada em Settings.</p></div>';
            }
        }

        if (Request::verifyNonce('routespro_license_nonce', 'routespro_license')) {
            $license_action = Request::postKey('routespro_license_action');
            if ($license_action === 'generate_local') {
                $license_plan = Request::postString('routespro_license_plan', 'pro');
                $license_customer = Request::postString('routespro_license_customer', 'LOCAL');
                $license_max = max(1, Request::postInt('routespro_license_max_activations'));
                $generated = \RoutesPro\Support\LicenseManager::generateRequested($license_plan, $license_customer, $license_max);
                if (!empty($generated['success']) && !empty($generated['key'])) {
                    \RoutesPro\Support\LicenseManager::update([
                        'key' => $generated['key'],
                        'plan' => $license_plan,
                        'customer' => strtoupper($license_customer),
                        'max_activations' => $license_max,
                        'source' => \RoutesPro\Support\LicenseManager::isRemoteMode() ? 'remote_seed' : 'local_generated',
                        'last_checked_at' => current_time('mysql'),
                        'notes' => \RoutesPro\Support\LicenseManager::isRemoteMode() ? 'Chave remota gerada no backoffice.' : 'Chave local gerada no backoffice.',
                    ]);
                    if (\RoutesPro\Support\LicenseManager::storageError() !== '') {
                        echo '<div class="notice notice-error"><p>' . esc_html(\RoutesPro\Support\LicenseManager::storageError()) . '</p></div>';
                    } else {
                        echo '<div class="updated notice"><p>' . esc_html(\RoutesPro\Support\LicenseManager::isRemoteMode() ? 'Chave remota gerada e guardada no cofre.' : 'Chave local gerada e guardada no cofre.') . '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html((string) ($generated['message'] ?? 'Falha ao gerar chave.')) . '</p></div>';
                }
            } elseif ($license_action === 'deactivate') {
                \RoutesPro\Support\LicenseManager::deactivate();
                echo '<div class="updated notice"><p>Licença desativada.</p></div>';
            } elseif ($license_action === 'validate_now') {
                \RoutesPro\Support\LicenseManager::validateCurrent(true);
                $hb = class_exists('\\RoutesPro\\Cloud\\LicenseInstallationLifecycle') ? \RoutesPro\Cloud\LicenseInstallationLifecycle::heartbeatState() : [];
                if (!empty($hb['accepted'])) echo '<div class="updated notice"><p>Licença confirmada remotamente e heartbeat 6B.2 aceite.</p></div>';
                else echo '<div class="notice notice-warning"><p>A licença local foi preservada, mas o heartbeat remoto ainda não ficou verde. Consulta Licenciamento &amp; Tenants.</p></div>';
            } else {
                $license_key = Request::postString('routespro_license_key');
                if ($license_key === '') $license_key = (string)\RoutesPro\Support\LicenseManager::get('key', '');
                $license_plan = Request::postString('routespro_license_plan', 'pro');
                \RoutesPro\Support\LicenseManager::activate($license_key, $license_plan);
                if (\RoutesPro\Support\LicenseManager::storageError() !== '') echo '<div class="notice notice-error"><p>' . esc_html(\RoutesPro\Support\LicenseManager::storageError()) . '</p></div>';
                else echo '<div class="updated notice"><p>Licença atualizada.</p></div>';
            }
        }

        if (Request::verifyNonce('routespro_settings_nonce', 'routespro_settings')) {

            $currentSettings = Config::all();
            $postedSecret = static function(string $key) use ($currentSettings): string {
                $value = trim((string)wp_unslash($_POST[$key] ?? ''));
                return $value !== '' ? sanitize_text_field($value) : (string)($currentSettings[$key] ?? '');
            };

            // Whitelists
            $maps_allowed = ['leaflet','google','azure'];
            $license_allowed = ['local','remote'];
            $routing_allowed = ['internal','google_routes'];
            $routes_pref_allowed = ['TRAFFIC_AWARE','TRAFFIC_UNAWARE','TRAFFIC_AWARE_OPTIMAL'];
            $route_mode_allowed = ['fastest_tolls','fastest_no_tolls','shortest'];
            $vehicle_profile_allowed = ['car_class1','light_van','commercial'];

            // Monta opções saneadas
            $opts = [
                // Otimizador
                'optimizer_url'         => esc_url_raw($_POST['optimizer_url'] ?? ''),
                'optimizer_api_key'     => $postedSecret('optimizer_api_key'),

                // Mapas
                'maps_provider'         => in_array(($_POST['maps_provider'] ?? 'leaflet'), $maps_allowed, true)
                                            ? $_POST['maps_provider'] : 'leaflet',
                'google_maps_key'       => $postedSecret('google_maps_key'),
                'azure_maps_key'        => $postedSecret('azure_maps_key'),

                // IA é gerida no Centro de Inteligência Artificial.
                // As chaves antigas são preservadas por Config::mergeAndSave().

                'maps_test_address'      => sanitize_text_field($_POST['maps_test_address'] ?? 'Praça do Comércio, Lisboa'),
                'ai_test_task'           => sanitize_text_field($_POST['ai_test_task'] ?? 'route_notes'),
                'license_mode'           => in_array(($_POST['license_mode'] ?? 'remote'), $license_allowed, true) ? $_POST['license_mode'] : 'remote',
                'license_remote_api_base' => esc_url_raw($_POST['license_remote_api_base'] ?? 'https://func-fieldflow-licensing-prod-eb978f11.azurewebsites.net/api'),
                'license_remote_shared_secret' => $postedSecret('license_remote_shared_secret'),
                'license_remote_admin_secret' => $postedSecret('license_remote_admin_secret'),
                'license_remote_product_id' => sanitize_text_field($_POST['license_remote_product_id'] ?? 'fieldflow'),
                'license_remote_timeout' => max(5, absint($_POST['license_remote_timeout'] ?? 15)),
                'license_remote_validate_interval' => max(1, absint($_POST['license_remote_validate_interval'] ?? 12)),
                'license_remote_heartbeat_interval' => max(1, min(72, absint($_POST['license_remote_heartbeat_interval'] ?? 6))),

                // Routing e portagens
                'routing_provider' => in_array(($_POST['routing_provider'] ?? 'internal'), $routing_allowed, true) ? $_POST['routing_provider'] : 'internal',
                'google_routes_api_key' => $postedSecret('google_routes_api_key'),
                'google_routes_preference' => in_array(($_POST['google_routes_preference'] ?? 'TRAFFIC_UNAWARE'), $routes_pref_allowed, true) ? $_POST['google_routes_preference'] : 'TRAFFIC_UNAWARE',
                'google_routes_route_mode' => in_array(($_POST['google_routes_route_mode'] ?? 'fastest_tolls'), $route_mode_allowed, true) ? $_POST['google_routes_route_mode'] : 'fastest_tolls',
                'google_routes_vehicle_profile' => in_array(($_POST['google_routes_vehicle_profile'] ?? 'car_class1'), $vehicle_profile_allowed, true) ? $_POST['google_routes_vehicle_profile'] : 'car_class1',
                'google_route_matrix_enabled' => !empty($_POST['google_route_matrix_enabled']) ? 1 : 0,
                'google_routes_waypoint_optimization_enabled' => !empty($_POST['google_routes_waypoint_optimization_enabled']) ? 1 : 0,
                'google_routes_tolls_enabled' => !empty($_POST['google_routes_tolls_enabled']) ? 1 : 0,
                'toll_intelligence_enabled' => !empty($_POST['toll_intelligence_enabled']) ? 1 : 0,
                'toll_vehicle_class' => max(1, min(4, absint($_POST['toll_vehicle_class'] ?? 1))),
                'places_cost_authority_enabled' => !empty($_POST['places_cost_authority_enabled']) ? 1 : 0,
                'google_places_contact_enrichment_enabled' => !empty($_POST['google_places_contact_enrichment_enabled']) ? 1 : 0,
                'routing_fallback_internal' => !empty($_POST['routing_fallback_internal']) ? 1 : 0,

                // Verificação de uploads (hash contra malware conhecido, VirusTotal)
                'virustotal_api_key' => $postedSecret('virustotal_api_key'),
                'upload_scan_enabled' => !empty($_POST['upload_scan_enabled']) ? 1 : 0,
                'routing_cache_days' => max(0, min(365, absint($_POST['routing_cache_days'] ?? 30))),
                'google_routes_test_origin' => sanitize_text_field($_POST['google_routes_test_origin'] ?? 'Lisboa, Portugal'),
                'google_routes_test_destination' => sanitize_text_field($_POST['google_routes_test_destination'] ?? 'Porto, Portugal'),
            ];

            // Persiste (mantém quaisquer chaves antigas que não estejam no form)
            $merged = Config::mergeAndSave($opts);
            Config::clearRoutingCache();

            $settings_notice = 'Settings guardadas. Cache de routing limpo; rotas persistidas só voltam a consultar Google quando a assinatura operacional mudar.';
            $settings_notice_type = 'updated';
            if (Config::storageError() !== '') {
                $settings_notice = Config::storageError();
                $settings_notice_type = 'error';
            }

            if (Request::postKey('routespro_settings_action') === 'save_test_google_routes') {
                $res = self::testGoogleRoutesApi((string) $merged['google_routes_test_origin'], (string) $merged['google_routes_test_destination']);
                if (!empty($res['ok'])) {
                    Config::mergeAndSave(['routing_provider' => 'google_routes']);
                    Config::clearRoutingCache();
                    $settings_notice = ((string)($res['message'] ?? 'Teste Google Routes OK.')) . ' Provider Google Routes API ativado e cache limpo.';
                    $settings_notice_type = 'updated';
                } else {
                    $settings_notice = (string)($res['message'] ?? 'Teste Google Routes falhou.');
                    $settings_notice_type = 'error';
                }
            }
        }


        $settings_notice = isset($settings_notice) ? $settings_notice : '';
        $settings_notice_type = isset($settings_notice_type) ? $settings_notice_type : 'updated';
        if (!empty($_POST['routespro_settings_action']) && !empty($_POST['routespro_settings_tools_nonce']) && wp_verify_nonce($_POST['routespro_settings_tools_nonce'], 'routespro_settings_tools')) {
            $action = Request::postKey('routespro_settings_action');
            $test_address = Request::postString('settings_test_address', self::get('maps_test_address', 'Praça do Comércio, Lisboa'));
            $test_context = wp_strip_all_tags((string)($_POST['settings_ai_context'] ?? 'Resumo da visita: loja com boa visibilidade, stock baixo e necessidade de reposição até sexta.'));
            $test_task = Request::postString('settings_ai_task', self::get('ai_test_task', 'route_notes'));
            $google_routes_origin = Request::postString('google_routes_test_origin', self::get('google_routes_test_origin', 'Lisboa, Portugal'));
            $google_routes_destination = Request::postString('google_routes_test_destination', self::get('google_routes_test_destination', 'Porto, Portugal'));
            if ($action === 'test_maps') {
                $provider = class_exists('\RoutesPro\Services\MapsFactory') ? \RoutesPro\Services\MapsFactory::make() : null;
                if (!$provider) {
                    $settings_notice = 'Fornecedor de mapas não configurado.';
                    $settings_notice_type = 'error';
                } else {
                    $res = $provider->geocode($test_address);
                    if (!empty($res['lat']) && !empty($res['lng'])) {
                        $settings_notice = sprintf('Teste de mapas OK. %s => %s, %s', $test_address, $res['lat'], $res['lng']);
                    } else {
                        $settings_notice = 'Teste de mapas sem resposta válida. Confirma a chave e as APIs ativadas.';
                        $settings_notice_type = 'error';
                    }
                }
            } elseif ($action === 'test_google_routes') {
                $res = self::testGoogleRoutesApi($google_routes_origin, $google_routes_destination);
                $settings_notice = (string)($res['message'] ?? 'Teste Google Routes concluído.');
                if (!empty($res['ok'])) {
                    Config::mergeAndSave(['routing_provider' => 'google_routes', 'google_routes_test_origin' => $google_routes_origin, 'google_routes_test_destination' => $google_routes_destination]);
                    Config::clearRoutingCache();
                    $settings_notice .= ' Provider Google Routes API ativado e cache limpo.';
                }
                $settings_notice_type = !empty($res['ok']) ? 'updated' : 'error';
            } elseif ($action === 'test_ai') {
                $settings_notice = 'Os testes de IA foram movidos para FieldFlow > Configuração de IA.';
                $settings_notice_type = 'updated';
            }
        }

        $o = self::get();
        $maps_diag = [
            'Google Maps' => ['ready' => Config::providerReady('google_maps', $o), 'detail' => 'Autocomplete, Places, Geocoding, Directions e Distance Matrix.'],
            'Azure Maps' => ['ready' => Config::providerReady('azure_maps', $o), 'detail' => 'Geocode, directions e matrix no backend.'],
        ];
        $routing_diag = [
            'Google Routes API' => ['ready' => Config::providerReady('google_routes', $o), 'detail' => ((string)($o['routing_provider'] ?? 'internal') === 'google_routes') ? 'Chave pronta e provider ativo para rotas novas, refresh de rotas existentes, portal e exportação.' : 'Chave pronta, mas o provider ativo ainda é o estimador interno. Seleciona Google Routes API e guarda, ou usa Guardar e testar Google Routes.'],
        ];
        $ai_effective = Config::aiProviderForModule('general', $o);
        $ai_diag = [
            'Centro de IA' => [
                'ready' => $ai_effective !== 'none' && Config::aiProviderReady($ai_effective, $o),
                'detail' => 'Motor principal: ' . \RoutesPro\Services\AIFactory::providerLabel($ai_effective) . '. Configuração separada de licenciamento e mapas.',
            ],
        ];
        global $wpdb; $px = $wpdb->prefix . 'routespro_';
        $settings_categories = $wpdb->get_results("SELECT id,parent_id,name,type,is_active FROM {$px}categories ORDER BY COALESCE(parent_id,0), sort_order, name", ARRAY_A);
        $settings_category_roots = array_values(array_filter($settings_categories, fn($r) => empty($r['parent_id'])));
        ?>
        <div class="wrap">
          <?php \RoutesPro\Admin\Branding::render_header('Settings', 'Configura integrações, mapas e categorias da operação num único ecrã.'); ?>
          <?php if (!empty($settings_notice)): ?><div class="<?php echo esc_attr($settings_notice_type); ?> notice"><p><?php echo esc_html($settings_notice); ?></p></div><?php endif; ?>
          <style>
            .routespro-settings-grid{display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:20px;align-items:start}
            .routespro-settings-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.06);padding:22px;margin-top:16px}
            .routespro-settings-card h2{margin-top:0}
            .routespro-settings-status{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
            .routespro-settings-pill{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:700}
            .routespro-settings-pill.ok{background:#dcfce7;color:#166534}.routespro-settings-pill.no{background:#fee2e2;color:#991b1b}
            .routespro-settings-help{font-size:13px;color:#475569}
            @media (max-width: 1180px){.routespro-settings-grid{grid-template-columns:1fr}}
          </style>
          <div class="routespro-settings-grid"><div>
          <div class="routespro-settings-card">
            <h2>Licenciamento</h2>
            <p class="routespro-settings-help">Agora podes correr licenciamento local ou remoto via Azure. O plugin fala com a tua API para gerar, ativar, validar e desativar licenças.</p>
            <?php $license = \RoutesPro\Support\LicenseManager::all(); $license_summary = \RoutesPro\Support\LicenseManager::summary(); ?>
            <form method="post" style="margin-bottom:18px;">
              <?php wp_nonce_field('routespro_license', 'routespro_license_nonce'); ?>
              <input type="hidden" name="routespro_license_action" value="activate">
              <table class="form-table">
                <tr><th scope="row"><label for="routespro_license_key">Chave</label></th><td><input id="routespro_license_key" type="password" name="routespro_license_key" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr($license_summary['masked_key'] ?: 'Introduzir chave'); ?>"><p class="description">Deixa vazio para manter a chave atual. Máscara: <code><?php echo esc_html($license_summary['masked_key'] ?: 'sem chave'); ?></code></p></td></tr>
                <tr><th scope="row"><label for="routespro_license_plan">Plano</label></th><td><input id="routespro_license_plan" name="routespro_license_plan" class="regular-text" value="<?php echo esc_attr(\RoutesPro\Support\LicenseManager::get('plan', 'starter')); ?>"> <span class="routespro-settings-pill <?php echo \RoutesPro\Support\LicenseManager::isActive() ? 'ok' : 'no'; ?>"><?php echo esc_html(\RoutesPro\Support\LicenseManager::statusLabel()); ?></span></td></tr>
                <tr><th scope="row">Domínio ativo</th><td><code><?php echo esc_html($license_summary['domain']); ?></code><br><span class="routespro-settings-help">Fingerprint: <code><?php echo esc_html($license_summary['fingerprint']); ?></code></span></td></tr>
                <tr><th scope="row">Fonte</th><td><?php echo esc_html((string) ($license['source'] ?? 'remote')); ?><?php if (!empty($license['activated_at'])): ?><br><span class="routespro-settings-help">Ativada em: <?php echo esc_html((string) $license['activated_at']); ?></span><?php endif; ?></td></tr>
                <tr><th scope="row">Modo</th><td><code><?php echo esc_html((string) ($license['mode'] ?? 'remote')); ?></code><?php if (!empty($license['remote_activation_id'])): ?><br><span class="routespro-settings-help">Activation ID: <code><?php echo esc_html((string) $license['remote_activation_id']); ?></code></span><?php endif; ?></td></tr>
                <?php if (class_exists('\\RoutesPro\\Cloud\\LicenseInstallationLifecycle')): $ff6b2 = \RoutesPro\Cloud\LicenseInstallationLifecycle::health(); ?>
                <tr><th scope="row">Installation ID</th><td><code><?php echo esc_html((string)($ff6b2['installation_uuid'] ?? '')); ?></code><br><span class="routespro-settings-help">Binding obrigatório: OFF, apenas observação 6B.2.</span></td></tr>
                <tr><th scope="row">Heartbeat 6B.2</th><td><span class="routespro-settings-pill <?php echo !empty($ff6b2['heartbeat_accepted']) ? 'ok' : 'no'; ?>"><?php echo !empty($ff6b2['heartbeat_accepted']) ? 'ACEITE' : 'PENDENTE'; ?></span><?php if (!empty($ff6b2['last_heartbeat_success_at'])): ?><br><span class="routespro-settings-help">Último sucesso: <?php echo esc_html((string)$ff6b2['last_heartbeat_success_at']); ?> UTC</span><?php endif; ?></td></tr>
                <?php endif; ?>
                <?php
                  $license_active_for_ui = \RoutesPro\Support\LicenseManager::isActive();
                  $license_source_for_ui = (string) ($license['source'] ?? '');
                  $show_remote_error_for_ui = !empty($license['remote_last_error']) && !$license_active_for_ui;
                ?>
                <tr><th scope="row">Observações</th><td><span class="routespro-settings-help"><?php echo esc_html((string) ($license['notes'] ?? '')); ?></span><?php if ($show_remote_error_for_ui): ?><br><span class="routespro-settings-help" style="color:#991b1b">Erro remoto: <?php echo esc_html((string) $license['remote_last_error']); ?></span><?php elseif (!empty($license['remote_last_error']) && $license_active_for_ui): ?><br><span class="routespro-settings-help" style="color:#64748b">Última validação remota devolveu erro técnico, mas a licença mantém-se ativa pela última ativação válida.</span><?php endif; ?></td></tr>
              </table>
              <p>
                <button class="button button-secondary">Guardar licença</button>
                <?php if (\RoutesPro\Support\LicenseManager::isRemoteMode()): ?><button class="button" name="routespro_license_action" value="validate_now">Confirmar estado</button><?php endif; ?>
                <button class="button" name="routespro_license_action" value="deactivate">Desativar</button>
              </p>
            </form>
            <hr>
            <form method="post">
              <?php wp_nonce_field('routespro_license', 'routespro_license_nonce'); ?>
              <input type="hidden" name="routespro_license_action" value="generate_local">
              <table class="form-table">
                <tr><th scope="row"><label for="routespro_license_customer">Cliente</label></th><td><input id="routespro_license_customer" name="routespro_license_customer" class="regular-text" value="<?php echo esc_attr((string) ($license['customer'] ?? 'LOCAL')); ?>"><p class="description">Usa um código curto, por exemplo TWT, DEMO ou CLIENTEA.</p></td></tr>
                <tr><th scope="row"><label for="routespro_license_max_activations">Ativações</label></th><td><input id="routespro_license_max_activations" type="number" min="1" max="99" name="routespro_license_max_activations" class="small-text" value="<?php echo esc_attr((string) ($license['max_activations'] ?? 1)); ?>"></td></tr>
              </table>
              <p><button class="button button-primary"><?php echo \RoutesPro\Support\LicenseManager::isRemoteMode() ? 'Gerar chave remota' : 'Gerar chave local'; ?></button></p>
            </form>
          </div>
          <div class="routespro-settings-card">
            <h2>Diagnóstico rápido</h2>
            <div class="routespro-settings-status">
              <?php foreach ($maps_diag as $label => $diag): ?>
                <div><strong><?php echo esc_html($label); ?></strong><br><span class="routespro-settings-pill <?php echo !empty($diag['ready']) ? 'ok' : 'no'; ?>"><?php echo !empty($diag['ready']) ? 'Pronto' : 'Por configurar'; ?></span><p class="routespro-settings-help"><?php echo esc_html($diag['detail']); ?></p></div>
              <?php endforeach; ?>
              <?php foreach ($routing_diag as $label => $diag): ?>
                <div><strong><?php echo esc_html($label); ?></strong><br><span class="routespro-settings-pill <?php echo !empty($diag['ready']) ? 'ok' : 'no'; ?>"><?php echo !empty($diag['ready']) ? 'Pronto' : 'Por configurar'; ?></span><p class="routespro-settings-help"><?php echo esc_html($diag['detail']); ?></p></div>
              <?php endforeach; ?>
              <?php foreach ($ai_diag as $label => $diag): ?>
                <div><strong><?php echo esc_html($label); ?></strong><br><span class="routespro-settings-pill <?php echo !empty($diag['ready']) ? 'ok' : 'no'; ?>"><?php echo !empty($diag['ready']) ? 'Pronto' : 'Por configurar'; ?></span><p class="routespro-settings-help"><?php echo esc_html($diag['detail']); ?></p></div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="routespro-settings-card">
            <h2>Testes técnicos</h2>
            <p class="routespro-settings-help">Serve para validar chaves e endpoints sem mexer nas rotinas que já tens em produção.</p>
            <form method="post" style="margin-bottom:18px">
              <?php wp_nonce_field('routespro_settings_tools', 'routespro_settings_tools_nonce'); ?>
              <input type="hidden" name="routespro_settings_action" value="test_maps">
              <table class="form-table"><tr><th scope="row"><label for="settings_test_address">Morada teste</label></th><td><input id="settings_test_address" name="settings_test_address" class="regular-text" value="<?php echo esc_attr($o['maps_test_address']); ?>"> <button class="button">Testar mapas</button></td></tr></table>
            </form>
            <form method="post" style="margin-bottom:18px">
              <?php wp_nonce_field('routespro_settings_tools', 'routespro_settings_tools_nonce'); ?>
              <input type="hidden" name="routespro_settings_action" value="test_google_routes">
              <table class="form-table">
                <tr><th scope="row"><label for="google_routes_test_origin">Teste Google Routes</label></th><td><input id="google_routes_test_origin" name="google_routes_test_origin" class="regular-text" value="<?php echo esc_attr($o['google_routes_test_origin']); ?>" placeholder="Lisboa, Portugal"> <input name="google_routes_test_destination" class="regular-text" value="<?php echo esc_attr($o['google_routes_test_destination']); ?>" placeholder="Porto, Portugal"> <button class="button">Testar Routes API</button><p class="description">Testa distância, duração e portagens devolvidas pela Google Routes API.</p></td></tr>
              </table>
            </form>
            <div style="padding:16px;border:1px solid #c7d2fe;border-radius:16px;background:#eef2ff">
              <strong>Testes de Inteligência Artificial</strong>
              <p class="routespro-settings-help">As APIs, deployments, modelos e diagnósticos de IA são agora geridos numa área própria, sem interferir com Azure Maps ou licenciamento.</p>
              <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=routespro-ai')); ?>">Abrir Centro de IA</a>
            </div>
          </div>
          <form method="post">
            <?php wp_nonce_field('routespro_settings','routespro_settings_nonce'); ?>

            <h2 style="margin-top:1em">Licenciamento remoto Azure</h2>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="license_mode">Modo</label></th>
                <td>
                  <select id="license_mode" name="license_mode">
                    <option value="local" <?php selected($o['license_mode'],'local'); ?>>Local</option>
                    <option value="remote" <?php selected($o['license_mode'],'remote'); ?>>Remoto Azure</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="license_remote_api_base">API Base</label></th>
                <td><input id="license_remote_api_base" name="license_remote_api_base" class="regular-text" placeholder="https://teu-servico.azurewebsites.net/api" value="<?php echo esc_attr($o['license_remote_api_base']); ?>"/><p class="description">Origem efetiva: <strong><?php echo esc_html(Config::licenseApiBaseSource()); ?></strong>. Constante opcional: <code>ROUTESPRO_LICENSE_API_BASE</code>. Uma constante antiga com o endpoint retirado é ignorada automaticamente desde a 2.2.381.</p></td>
              </tr>
              <tr>
                <th scope="row"><label for="license_remote_shared_secret">Shared Secret</label></th>
                <td><input id="license_remote_shared_secret" type="password" name="license_remote_shared_secret" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr(!empty($o['license_remote_shared_secret']) ? Config::maskSecret((string)$o['license_remote_shared_secret']) : 'Introduzir segredo'); ?>"/><p class="description">Assina requests HMAC. Deixa vazio para manter. Constante opcional: <code>ROUTESPRO_LICENSE_API_SECRET</code>.</p></td>
              </tr>
              <tr>
                <th scope="row"><label for="license_remote_admin_secret">Admin Secret</label></th>
                <td><input id="license_remote_admin_secret" type="password" name="license_remote_admin_secret" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr(!empty($o['license_remote_admin_secret']) ? Config::maskSecret((string)$o['license_remote_admin_secret']) : 'Introduzir segredo'); ?>"/><p class="description">Usado apenas para gerar chaves remotas. Deixa vazio para manter. Constante opcional: <code>ROUTESPRO_LICENSE_ADMIN_SECRET</code>.</p></td>
              </tr>
              <tr>
                <th scope="row"><label for="license_remote_product_id">Product ID</label></th>
                <td><input id="license_remote_product_id" name="license_remote_product_id" class="regular-text" value="<?php echo esc_attr($o['license_remote_product_id']); ?>"/><p class="description">Ex.: fieldflow-pro.</p></td>
              </tr>
              <tr>
                <th scope="row"><label for="license_remote_timeout">Timeout API</label></th>
                <td><input id="license_remote_timeout" type="number" min="5" max="60" name="license_remote_timeout" class="small-text" value="<?php echo esc_attr((string) $o['license_remote_timeout']); ?>"/> segundos</td>
              </tr>
              <tr>
                <th scope="row"><label for="license_remote_validate_interval">Intervalo de validação</label></th>
                <td><input id="license_remote_validate_interval" type="number" min="1" max="72" name="license_remote_validate_interval" class="small-text" value="<?php echo esc_attr((string) $o['license_remote_validate_interval']); ?>"/> horas</td>
              </tr>
              <tr>
                <th scope="row"><label for="license_remote_heartbeat_interval">Heartbeat de licença</label></th>
                <td><input id="license_remote_heartbeat_interval" type="number" min="1" max="72" name="license_remote_heartbeat_interval" class="small-text" value="<?php echo esc_attr((string) $o['license_remote_heartbeat_interval']); ?>"/> horas<p class="description">Fase 6B.2. Atualiza presença e binding técnico sem bloquear o FieldFlow se a API estiver indisponível.</p></td>
              </tr>
            </table>

            <h2 style="margin-top:1em">Otimizador (VRP/TSP)</h2>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="optimizer_url">Optimizer URL</label></th>
                <td><input id="optimizer_url" name="optimizer_url" class="regular-text" placeholder="https://teu-servico/vrp" value="<?php echo esc_attr($o['optimizer_url']); ?>"/></td>
              </tr>
              <tr>
                <th scope="row"><label for="optimizer_api_key">Optimizer API Key</label></th>
                <td><input id="optimizer_api_key" type="password" name="optimizer_api_key" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr(!empty($o['optimizer_api_key']) ? Config::maskSecret((string)$o['optimizer_api_key']) : 'Introduzir chave'); ?>"/><p class="description">Deixa vazio para manter.</p></td>
              </tr>
            </table>

            <h2 style="margin-top:1em">Routing & Portagens</h2>
            <p class="routespro-settings-help">Configuração para a próxima camada de cálculo real de rotas e portagens. Mantém o estimador interno como fallback para não partir operação.</p>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="routing_provider">Provider de routing</label></th>
                <td>
                  <select id="routing_provider" name="routing_provider">
                    <option value="internal" <?php selected($o['routing_provider'],'internal'); ?>>Estimador interno</option>
                    <option value="google_routes" <?php selected($o['routing_provider'],'google_routes'); ?>>Google Routes API</option>
                  </select>
                  <p class="description">Para portagens reais, usa Google Routes API. O estimador interno continua disponível como plano B.</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="google_routes_api_key">Google Routes API Key</label></th>
                <td><input id="google_routes_api_key" type="password" name="google_routes_api_key" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo esc_attr(!empty($o['google_routes_api_key']) ? Config::maskSecret((string)$o['google_routes_api_key']) : 'Introduzir chave'); ?>"/><p class="description">Chave server-side. Deixa vazio para manter. Se o teste indicar referrer vazio bloqueado, a chave está restringida por domínio. Usa restrição por IP público de saída do servidor e apenas à Routes API. Constante opcional: <code>ROUTESPRO_GOOGLE_ROUTES_API_KEY</code>.</p></td>
              </tr>
              <tr>
                <th scope="row"><label for="upload_scan_enabled">Verificação de uploads (malware conhecido)</label></th>
                <td>
                  <label><input id="upload_scan_enabled" type="checkbox" name="upload_scan_enabled" value="1" <?php checked(!empty($o['upload_scan_enabled'])); ?>> Ativar verificação por hash contra a base de dados da VirusTotal</label>
                  <p class="description">Compara o SHA-256 de cada documento/arquivo (PDF, CSV, XLSX, XLS, DOCX, ZIP) com malware já conhecido pela VirusTotal — não envia o conteúdo do ficheiro, só o hash. Fotos e imagens nunca são verificadas: não podem ser executadas e são a maioria do volume, verificá-las tornaria os envios de campo visivelmente mais lentos sem reduzir risco real. Falha sempre em aberto: erro de rede, limite de pedidos ou hash desconhecido nunca bloqueiam o upload — só uma correspondência confirmada com malware conhecido é bloqueada.</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="virustotal_api_key">VirusTotal API Key</label></th>
                <td><input id="virustotal_api_key" type="password" name="virustotal_api_key" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo esc_attr(!empty($o['virustotal_api_key']) ? Config::maskSecret((string)$o['virustotal_api_key']) : 'Introduzir chave'); ?>"/><p class="description">Necessária para a verificação acima. Chave gratuita disponível em virustotal.com (limite: 4 pedidos/minuto, 500/dia). Deixa vazio para manter. Constante opcional: <code>ROUTESPRO_VIRUSTOTAL_API_KEY</code>.</p></td>
              </tr>
              <tr>
                <th scope="row"><label for="google_routes_route_mode">Modo de rota</label></th>
                <td>
                  <select id="google_routes_route_mode" name="google_routes_route_mode">
                    <option value="fastest_tolls" <?php selected($o['google_routes_route_mode'],'fastest_tolls'); ?>>Mais rápida com portagens</option>
                    <option value="fastest_no_tolls" <?php selected($o['google_routes_route_mode'],'fastest_no_tolls'); ?>>Mais rápida evitando portagens</option>
                    <option value="shortest" <?php selected($o['google_routes_route_mode'],'shortest'); ?>>Mais curta, quando suportado</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="google_routes_preference">Preferência Google</label></th>
                <td>
                  <select id="google_routes_preference" name="google_routes_preference">
                    <option value="TRAFFIC_AWARE" <?php selected($o['google_routes_preference'],'TRAFFIC_AWARE'); ?>>Traffic aware</option>
                    <option value="TRAFFIC_AWARE_OPTIMAL" <?php selected($o['google_routes_preference'],'TRAFFIC_AWARE_OPTIMAL'); ?>>Traffic aware optimal</option>
                    <option value="TRAFFIC_UNAWARE" <?php selected($o['google_routes_preference'],'TRAFFIC_UNAWARE'); ?>>Traffic unaware</option>
                  </select>
                  <p class="description">Para FieldFlow sem necessidade de trânsito em tempo real, usa <strong>Traffic unaware</strong>: mantém a rede rodoviária Google sem pagar inteligência de trânsito atual.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Custos Google avançados</th>
                <td>
                  <label style="display:block;margin-bottom:6px"><input type="checkbox" name="google_route_matrix_enabled" value="1" <?php checked(!empty($o['google_route_matrix_enabled'])); ?>> Ativar Google Route Matrix no planeamento, normalmente desnecessário e faturado por origem × destino.</label>
                  <label style="display:block;margin-bottom:6px"><input type="checkbox" name="google_routes_waypoint_optimization_enabled" value="1" <?php checked(!empty($o['google_routes_waypoint_optimization_enabled'])); ?>> Permitir otimização de ordem de waypoints pela Google. O motor territorial FieldFlow continua ativo quando esta opção está desligada.</label>
                  <label style="display:block"><input type="checkbox" name="google_routes_tolls_enabled" value="1" <?php checked(!empty($o['google_routes_tolls_enabled'])); ?>> Pedir portagens reais à Google. Desligado usa o estimador interno de portagens e mantém Google apenas para distância/tempo rodoviário.</label>
                  <p class="description"><strong>Google Cost Control Center:</strong> quando ativo, os bloqueios financeiros têm prioridade sobre estas opções e impedem Route Matrix, Pro ou Enterprise antes de qualquer pedido HTTP.</p>
                  <p class="description">Na configuração recomendada de custo, as três opções ficam desligadas. O FieldFlow calcula a ordem internamente e faz apenas a confirmação rodoviária final quando a rota muda.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Portagens estimadas FieldFlow</th>
                <td>
                  <label style="display:block;margin-bottom:6px"><input type="checkbox" name="toll_intelligence_enabled" value="1" <?php checked(!empty($o['toll_intelligence_enabled'])); ?>> Ativar Toll Cost Intelligence, usa os passos rodoviários da mesma chamada normal de rota e não ativa Google TOLLS.</label>
                  <label for="toll_vehicle_class">Classe de portagem </label>
                  <select id="toll_vehicle_class" name="toll_vehicle_class">
                    <?php for ($tc=1; $tc<=4; $tc++): ?><option value="<?php echo esc_attr((string)$tc); ?>" <?php selected((int)($o['toll_vehicle_class'] ?? 1), $tc); ?>>Classe <?php echo esc_html((string)$tc); ?></option><?php endfor; ?>
                  </select>
                  <p class="description">Recomendado: ativo com Google TOLLS desligado. O valor é uma estimativa operacional baseada em corredores e tarifário Portugal 2026, com nível de confiança.</p>
                </td>
              </tr>
              <tr>
                <th scope="row">Places &amp; Geocoding low cost</th>
                <td>
                  <label style="display:block;margin-bottom:6px"><input type="checkbox" name="places_cost_authority_enabled" value="1" <?php checked(!empty($o['places_cost_authority_enabled'])); ?>> Reutilizar identidade e coordenadas já validadas quando a morada e o país não mudaram, evitando geocodificação repetida.</label>
                  <label style="display:block"><input type="checkbox" name="google_places_contact_enrichment_enabled" value="1" <?php checked(!empty($o['google_places_contact_enrichment_enabled'])); ?>> Enriquecer automaticamente lojas Google com telefone e website. Mantém desligado para reduzir Place Details; pode ser ativado quando esses contactos forem realmente necessários.</label>
                  <p class="description">Recomendado: autoridade low cost ativa e enriquecimento de contactos desligado. A pesquisa e seleção de moradas continuam disponíveis.</p>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="google_routes_vehicle_profile">Perfil da viatura</label></th>
                <td>
                  <select id="google_routes_vehicle_profile" name="google_routes_vehicle_profile">
                    <option value="car_class1" <?php selected($o['google_routes_vehicle_profile'],'car_class1'); ?>>Classe 1, ligeiro</option>
                    <option value="light_van" <?php selected($o['google_routes_vehicle_profile'],'light_van'); ?>>Carrinha ligeira</option>
                    <option value="commercial" <?php selected($o['google_routes_vehicle_profile'],'commercial'); ?>>Comercial</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="routing_cache_days">Cache de rotas</label></th>
                <td><input id="routing_cache_days" type="number" min="0" max="365" name="routing_cache_days" class="small-text" value="<?php echo esc_attr((string) $o['routing_cache_days']); ?>"/> dias <p class="description">Cache HTTP de segurança. A autoridade principal é agora a assinatura persistida da rota: uma rota sem alterações não é recalculada apenas por envelhecer.</p></td>
              </tr>
              <tr>
                <th scope="row">Fallback</th>
                <td><label><input type="checkbox" name="routing_fallback_internal" value="1" <?php checked(!empty($o['routing_fallback_internal'])); ?>> Usar estimador interno se a API falhar ou não devolver portagem.</label></td>
              </tr>
            </table>
            <p>
              <button class="button button-secondary" name="routespro_settings_action" value="save_test_google_routes">Guardar e testar Google Routes</button>
              <span class="description">Este botão guarda o provider, testa Lisboa → Porto e limpa a cache para o BO, portal e exportações passarem a recalcular com Google.</span>
            </p>

            <h2 style="margin-top:1em">Mapas</h2>
            <table class="form-table">
              <tr>
                <th scope="row"><label for="maps_provider">Fornecedor</label></th>
                <td>
                  <select id="maps_provider" name="maps_provider">
                    <option value="leaflet" <?php selected($o['maps_provider'],'leaflet'); ?>>Leaflet (OSM)</option>
                    <option value="google"  <?php selected($o['maps_provider'],'google'); ?>>Google Maps</option>
                    <option value="azure"   <?php selected($o['maps_provider'],'azure'); ?>>Microsoft Azure Maps</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th scope="row"><label for="google_maps_key">Google Maps Key</label></th>
                <td><input id="google_maps_key" type="password" name="google_maps_key" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr(!empty($o['google_maps_key']) ? Config::maskSecret((string)$o['google_maps_key']) : 'Introduzir chave'); ?>"/><p class="description">Deixa vazio para manter. Também podes definir <code>ROUTESPRO_GOOGLE_MAPS_KEY</code> no wp-config.php.</p></td>
              </tr>
              <tr>
                <th scope="row"><label for="azure_maps_key">Azure Maps Key</label></th>
                <td><input id="azure_maps_key" type="password" name="azure_maps_key" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo esc_attr(!empty($o['azure_maps_key']) ? Config::maskSecret((string)$o['azure_maps_key']) : 'Introduzir chave'); ?>"/><p class="description">Deixa vazio para manter. Também podes definir <code>ROUTESPRO_AZURE_MAPS_KEY</code> no wp-config.php.</p></td>
              </tr>
            </table>

            <h2 style="margin-top:1em">Inteligência Artificial</h2>
            <div style="padding:16px;border:1px solid #c7d2fe;border-radius:16px;background:#eef2ff">
              <strong>Configuração movida para o Centro de IA</strong>
              <p class="description">A IA generativa está agora separada de Azure Maps, Google Routes e licenciamento. As credenciais já guardadas foram preservadas.</p>
              <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-ai')); ?>">Configurar IA operacional</a>
            </div>

            <p><button class="button button-primary">Guardar</button></p>
          </form>
          </div>
          <aside>
            <div class="routespro-settings-card" id="routespro-settings-categories">
              <h2>Categorias rápidas</h2>
              <p style="color:#64748b">Cria novas categorias e subcategorias sem sair de Settings.</p>
              <form method="post">
                <?php wp_nonce_field('routespro_settings_categories','routespro_settings_categories_nonce'); ?>
                <table class="form-table"><tbody>
                  <tr><th>Nome</th><td><input type="text" name="settings_category_name" class="regular-text" required></td></tr>
                  <tr><th>Categoria pai</th><td><select name="settings_category_parent_id"><option value="">-- raiz --</option><?php foreach($settings_category_roots as $root): ?><option value="<?php echo intval($root['id']); ?>"><?php echo esc_html($root['name']); ?></option><?php endforeach; ?></select></td></tr>
                  <tr><th>Tipo</th><td><input type="text" name="settings_category_type" class="regular-text" placeholder="horeca ou retalho"></td></tr>
                </tbody></table>
                <p><button class="button button-secondary">Adicionar categoria</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-categories')); ?>">Abrir gestão completa</a></p>
              </form>
            </div>
            <div class="routespro-settings-card">
              <h2>Resumo de categorias</h2>
              <div style="max-height:420px;overflow:auto">
                <table class="widefat striped"><thead><tr><th>Nome</th><th>Pai</th><th>Tipo</th></tr></thead><tbody>
                <?php foreach($settings_categories as $row): $parent=''; foreach($settings_category_roots as $root){ if((int)$root['id']===(int)$row['parent_id']){$parent=$root['name']; break;} } ?>
                  <tr><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html($parent); ?></td><td><?php echo esc_html((string)$row['type']); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
              </div>
            </div>
          </aside></div>
        </div>
        <?php
    }
}
