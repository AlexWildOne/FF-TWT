<?php
namespace RoutesPro\Admin;

use RoutesPro\Integrations\SecretVault;
use RoutesPro\Reporting\ReportingFoundation;

class Integrations {
    const OPT_KEY = 'routespro_integrations';
    private static string $storage_error = '';
    private static array $unreadable_secret_keys = [];

    private static function secret_keys(): array {
        return [
            'api_token',
            'powerbi_token',
            'gcloud_auth_header',
            'gcloud_service_account_json',
            'azure_auth_header',
            'azure_cloud_hmac_secret',
        ];
    }

    private static function constant_overrides(): array {
        return [
            'api_token' => 'ROUTESPRO_INTEGRATIONS_API_TOKEN',
            'powerbi_push_url' => 'ROUTESPRO_POWERBI_PUSH_URL',
            'powerbi_token' => 'ROUTESPRO_POWERBI_TOKEN',
            'gcloud_push_url' => 'ROUTESPRO_GCLOUD_PUSH_URL',
            'gcloud_auth_header' => 'ROUTESPRO_GCLOUD_AUTH_HEADER',
            'gcloud_service_account_json' => 'ROUTESPRO_GCLOUD_SERVICE_ACCOUNT_JSON',
            'azure_push_url' => 'ROUTESPRO_AZURE_PUSH_URL',
            'azure_auth_header' => 'ROUTESPRO_AZURE_AUTH_HEADER',
            'azure_cloud_endpoint' => 'ROUTESPRO_AZURE_CLOUD_ENDPOINT',
            'azure_cloud_hmac_secret' => 'ROUTESPRO_AZURE_CLOUD_HMAC_SECRET',
        ];
    }

    private static function apply_constant_overrides(array $opts): array {
        foreach (self::constant_overrides() as $key => $constant) {
            if (defined($constant)) {
                $opts[$key] = constant($constant);
            }
        }
        return $opts;
    }

    private static function decode_saved_secrets(array $saved): array {
        self::$unreadable_secret_keys = [];
        foreach (self::secret_keys() as $key) {
            $stored = (string)($saved[$key] ?? '');
            if ($stored === '') continue;
            if (!SecretVault::isEncrypted($stored)) continue;
            $plain = SecretVault::decrypt($stored);
            if ($plain === '') self::$unreadable_secret_keys[$key] = true;
            $saved[$key] = $plain;
        }
        return $saved;
    }

    private static function saved_secrets_need_migration(array $saved): bool {
        foreach (self::secret_keys() as $key) {
            $stored = (string)($saved[$key] ?? '');
            if ($stored !== '' && SecretVault::needsMigration($stored)) return true;
        }
        return false;
    }

    private static function persist(array $plain): bool {
        self::$storage_error = '';
        $existing = get_option(self::OPT_KEY, []);
        if (!is_array($existing)) $existing = [];
        $stored = $plain;
        $overrides = self::constant_overrides();

        foreach ($overrides as $key => $constant) {
            if (!defined($constant)) continue;
            if (array_key_exists($key, $existing)) $stored[$key] = $existing[$key];
            else unset($stored[$key]);
        }

        foreach (self::secret_keys() as $key) {
            $constant = $overrides[$key] ?? '';
            if ($constant !== '' && defined($constant)) continue;
            $plainValue = (string)($plain[$key] ?? '');
            if ($plainValue === '' && isset(self::$unreadable_secret_keys[$key]) && array_key_exists($key, $existing)) {
                $stored[$key] = $existing[$key];
                continue;
            }
            if ($plainValue === '') {
                $stored[$key] = '';
                continue;
            }
            $encrypted = SecretVault::encrypt($plainValue);
            if ($encrypted === '') {
                self::$storage_error = 'O servidor não disponibiliza cifragem autenticada para guardar credenciais. Os valores anteriores foram preservados.';
                return false;
            }
            $stored[$key] = $encrypted;
        }

        return update_option(self::OPT_KEY, $stored, false) || get_option(self::OPT_KEY, []) === $stored;
    }

    public static function storage_error(): string {
        return self::$storage_error;
    }

    public static function defaults(): array {
        return [
            'api_enabled' => 1,
            'api_token' => '',
            'api_allow_clients' => 1,
            'api_allow_projects' => 1,
            'api_allow_locations' => 1,
            'api_allow_routes' => 1,
            'api_allow_route_stops' => 1,
            'api_allow_events' => 1,
            'powerbi_enabled' => 0,
            'powerbi_push_url' => '',
            'powerbi_auth_type' => 'bearer',
            'powerbi_token' => '',
            'powerbi_table' => 'routespro_export',
            'gcloud_enabled' => 0,
            'gcloud_push_url' => '',
            'gcloud_auth_header' => '',
            'gcloud_project' => '',
            'gcloud_dataset' => '',
            'gcloud_table' => '',
            'gcloud_service_account_json' => '',
            'azure_cloud_sync_enabled' => 0,
            'azure_cloud_destination_mode' => 'fieldflow_cloud',
            'azure_cloud_endpoint' => '',
            'azure_cloud_hmac_secret' => '',
            'azure_cloud_sync_submissions' => 1,
            'azure_cloud_sync_visits' => 0,
            'azure_cloud_sync_briefings' => 0,
            'azure_cloud_sync_stocks' => 0,
            'azure_cloud_sync_operatives' => 0,
            'azure_cloud_sync_intelligence' => 0,
            'azure_cloud_sync_max_attempts' => 5,
            'azure_cloud_sync_batch_size' => 20,
            'azure_enabled' => 0,
            'azure_push_url' => '',
            'azure_auth_header' => '',
            'azure_workspace' => '',
            'azure_dataset' => '',
            'sync_timeout' => 25,
            'batch_size' => 500,
        ];
    }

    public static function get(): array {
        $stored = get_option(self::OPT_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        $needsMigration = self::saved_secrets_need_migration($stored);
        $saved = self::decode_saved_secrets($stored);
        $opts = wp_parse_args($saved, self::defaults());
        $generatedToken = false;
        if (empty($opts['api_token']) && empty($stored['api_token'])) {
            $opts['api_token'] = wp_generate_password(40, false, false);
            $generatedToken = true;
        }
        if (($needsMigration || $generatedToken) && SecretVault::canEncrypt()) self::persist($opts);
        return self::apply_constant_overrides($opts);
    }

    public static function render() {
        if (!current_user_can('routespro_manage')) {
            wp_die('Sem permissões.');
        }

        $message = '';
        $test_result = null;
        $revealed_api_token = null;
        $reporting_status = class_exists('\\RoutesPro\\Reporting\\ReportingFoundation') ? ReportingFoundation::status() : [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['routespro_integrations_nonce']) && wp_verify_nonce($_POST['routespro_integrations_nonce'], 'routespro_integrations_save')) {
            $opts = self::get();
            $opts['api_enabled'] = empty($_POST['api_enabled']) ? 0 : 1;
            $apiToken = trim((string)wp_unslash($_POST['api_token'] ?? ''));
            if ($apiToken !== '') $opts['api_token'] = sanitize_text_field($apiToken);
            if ($opts['api_token'] === '') {
                $opts['api_token'] = wp_generate_password(40, false, false);
            }
            foreach (['clients','projects','locations','routes','route_stops','events'] as $resource) {
                $opts['api_allow_' . $resource] = empty($_POST['api_allow_' . $resource]) ? 0 : 1;
            }
            $opts['powerbi_enabled'] = empty($_POST['powerbi_enabled']) ? 0 : 1;
            $opts['powerbi_push_url'] = esc_url_raw($_POST['powerbi_push_url'] ?? '');
            $opts['powerbi_auth_type'] = sanitize_text_field($_POST['powerbi_auth_type'] ?? 'bearer');
            $powerBiToken = trim((string)wp_unslash($_POST['powerbi_token'] ?? ''));
            if ($powerBiToken !== '') $opts['powerbi_token'] = sanitize_text_field($powerBiToken);
            if (!empty($_POST['powerbi_token_clear'])) $opts['powerbi_token'] = '';
            $opts['powerbi_table'] = sanitize_text_field($_POST['powerbi_table'] ?? 'routespro_export');

            $opts['gcloud_enabled'] = empty($_POST['gcloud_enabled']) ? 0 : 1;
            $opts['gcloud_push_url'] = esc_url_raw($_POST['gcloud_push_url'] ?? '');
            $gcloudAuthHeader = trim((string)wp_unslash($_POST['gcloud_auth_header'] ?? ''));
            if ($gcloudAuthHeader !== '') $opts['gcloud_auth_header'] = sanitize_text_field($gcloudAuthHeader);
            if (!empty($_POST['gcloud_auth_header_clear'])) $opts['gcloud_auth_header'] = '';
            $opts['gcloud_project'] = sanitize_text_field($_POST['gcloud_project'] ?? '');
            $opts['gcloud_dataset'] = sanitize_text_field($_POST['gcloud_dataset'] ?? '');
            $opts['gcloud_table'] = sanitize_text_field($_POST['gcloud_table'] ?? '');
            $gcloudServiceAccount = trim((string)wp_unslash($_POST['gcloud_service_account_json'] ?? ''));
            if ($gcloudServiceAccount !== '') $opts['gcloud_service_account_json'] = $gcloudServiceAccount;
            if (!empty($_POST['gcloud_service_account_json_clear'])) $opts['gcloud_service_account_json'] = '';

            $opts['azure_cloud_sync_enabled'] = empty($_POST['azure_cloud_sync_enabled']) ? 0 : 1;
            $opts['azure_cloud_endpoint'] = esc_url_raw($_POST['azure_cloud_endpoint'] ?? ($opts['azure_cloud_endpoint'] ?? ''));
            $cloudSecret = trim((string)wp_unslash($_POST['azure_cloud_hmac_secret'] ?? ''));
            if ($cloudSecret !== '') $opts['azure_cloud_hmac_secret'] = sanitize_text_field($cloudSecret);
            if (!empty($_POST['azure_cloud_hmac_secret_clear'])) $opts['azure_cloud_hmac_secret'] = '';
            $mode = sanitize_key((string)($_POST['azure_cloud_destination_mode'] ?? 'fieldflow_cloud'));
            $opts['azure_cloud_destination_mode'] = in_array($mode, ['fieldflow_cloud','client_azure'], true) ? $mode : 'fieldflow_cloud';
            foreach (['submissions','visits','briefings','stocks','operatives','intelligence'] as $scope) {
                $opts['azure_cloud_sync_' . $scope] = empty($_POST['azure_cloud_sync_' . $scope]) ? 0 : 1;
            }
            $opts['azure_cloud_sync_max_attempts'] = max(1, min(12, absint($_POST['azure_cloud_sync_max_attempts'] ?? 5)));
            $opts['azure_cloud_sync_batch_size'] = max(1, min(100, absint($_POST['azure_cloud_sync_batch_size'] ?? 20)));

            $opts['azure_enabled'] = empty($_POST['azure_enabled']) ? 0 : 1;
            $opts['azure_push_url'] = esc_url_raw($_POST['azure_push_url'] ?? '');
            $azureAuthHeader = trim((string)wp_unslash($_POST['azure_auth_header'] ?? ''));
            if ($azureAuthHeader !== '') $opts['azure_auth_header'] = sanitize_text_field($azureAuthHeader);
            if (!empty($_POST['azure_auth_header_clear'])) $opts['azure_auth_header'] = '';
            $opts['azure_workspace'] = sanitize_text_field($_POST['azure_workspace'] ?? '');
            $opts['azure_dataset'] = sanitize_text_field($_POST['azure_dataset'] ?? '');
            $opts['sync_timeout'] = max(5, min(120, absint($_POST['sync_timeout'] ?? 25)));
            $opts['batch_size'] = max(50, min(5000, absint($_POST['batch_size'] ?? 500)));

            $saved = self::persist($opts);
            $message = $saved ? 'Integrações guardadas.' : (self::storage_error() ?: 'Não foi possível guardar as integrações.');
        }

        if (isset($_POST['routespro_regenerate_token']) && check_admin_referer('routespro_integrations_regenerate', 'routespro_integrations_regenerate_nonce')) {
            $opts = self::get();
            $newApiToken = wp_generate_password(40, false, false);
            $opts['api_token'] = $newApiToken;
            $saved = self::persist($opts);
            if ($saved) {
                $revealed_api_token = $newApiToken;
                $message = 'Token regenerado. Copia o novo valor abaixo, ele deixa de ser apresentado depois de saíres desta página.';
            } else {
                $message = self::storage_error() ?: 'Não foi possível regenerar o token.';
            }
        }

        if (!empty($_POST['routespro_test_connector']) && isset($_POST['routespro_integrations_tools_nonce']) && wp_verify_nonce($_POST['routespro_integrations_tools_nonce'], 'routespro_integrations_tools')) {
            $connector = sanitize_key((string)($_POST['routespro_test_connector'] ?? ''));
            $controller = class_exists('\RoutesPro\Rest\IntegrationsController') ? new \RoutesPro\Rest\IntegrationsController() : null;
            if ($controller) {
                $req = new \WP_REST_Request('POST', '/');
                $req->set_param('connector', $connector);
                $res = $controller->test_connector($req);
                if (is_wp_error($res)) {
                    $message = 'Teste falhou: ' . $res->get_error_message();
                } else {
                    $data = $res->get_data();
                    $message = !empty($data['ok']) ? 'Teste ao conector concluído com sucesso.' : 'Conector respondeu com erro HTTP.';
                    $test_result = $data;
                }
            }
        }

        $foundation_probe = null;
        if (!empty($_POST['routespro_test_azure_foundation']) && isset($_POST['routespro_azure_foundation_nonce']) && wp_verify_nonce($_POST['routespro_azure_foundation_nonce'], 'routespro_azure_foundation_tools')) {
            $foundation_probe = class_exists('\\RoutesPro\\Cloud\\AzureSyncFoundation') ? \RoutesPro\Cloud\AzureSyncFoundation::localProbe() : ['ok'=>false,'checks'=>[]];
            $message = !empty($foundation_probe['ok']) ? 'FieldFlow Cloud Foundation: diagnostico local concluido com sucesso.' : 'FieldFlow Cloud Foundation: o diagnostico local encontrou pontos a rever.';
        }
        if (!empty($_POST['routespro_retry_azure_sync']) && isset($_POST['routespro_azure_foundation_nonce']) && wp_verify_nonce($_POST['routespro_azure_foundation_nonce'], 'routespro_azure_foundation_tools')) {
            $retried = class_exists('\\RoutesPro\\Cloud\\AzureSyncFoundation') ? \RoutesPro\Cloud\AzureSyncFoundation::retryDead(0) : 0;
            $message = 'Azure Cloud Sync: ' . (int)$retried . ' job(s) recolocados na fila.';
        }

        $secure_cloud_probe = null;
        if (!empty($_POST['routespro_test_azure_secure']) && isset($_POST['routespro_azure_foundation_nonce']) && wp_verify_nonce($_POST['routespro_azure_foundation_nonce'], 'routespro_azure_foundation_tools')) {
            if (class_exists('\RoutesPro\Cloud\AzureConnectionManager')) {
                $probeResponse = \RoutesPro\Cloud\AzureConnectionManager::probe();
                if (is_wp_error($probeResponse)) {
                    $message = 'Azure Cloud seguro: ' . $probeResponse->get_error_message();
                } else {
                    $probeCode = (int)wp_remote_retrieve_response_code($probeResponse);
                    $probeBody = (string)wp_remote_retrieve_body($probeResponse);
                    $message = ($probeCode >= 200 && $probeCode < 300)
                        ? 'Azure Cloud seguro: assinatura HMAC e endpoint validados com sucesso.'
                        : 'Azure Cloud seguro: o endpoint respondeu HTTP ' . $probeCode . '.';
                    $secure_cloud_probe = ['http_code'=>$probeCode,'body_sample'=>substr($probeBody, 0, 500)];
                }
            }
        }

        $backfill_result = null;
        if (!empty($_POST['routespro_start_submission_backfill']) && isset($_POST['routespro_cloud_backfill_nonce']) && wp_verify_nonce($_POST['routespro_cloud_backfill_nonce'], 'routespro_cloud_backfill_tools')) {
            $backfill_result = class_exists('\RoutesPro\Cloud\SubmissionBackfill')
                ? \RoutesPro\Cloud\SubmissionBackfill::start(false)
                : ['ok'=>false,'reason'=>'backfill_runtime_missing'];
            $message = !empty($backfill_result['ok'])
                ? 'Data Hub 3A: snapshot histórico de submissões preparado.'
                : 'Data Hub 3A: não foi possível iniciar o backfill (' . sanitize_text_field((string)($backfill_result['reason'] ?? 'erro')) . ').';
        }
        if (!empty($_POST['routespro_enqueue_submission_backfill']) && isset($_POST['routespro_cloud_backfill_nonce']) && wp_verify_nonce($_POST['routespro_cloud_backfill_nonce'], 'routespro_cloud_backfill_tools')) {
            $backfillLimit = max(1, min(100, absint($_POST['routespro_backfill_limit'] ?? 25)));
            $backfill_result = class_exists('\RoutesPro\Cloud\SubmissionBackfill')
                ? \RoutesPro\Cloud\SubmissionBackfill::enqueueNext($backfillLimit)
                : ['ok'=>false,'reason'=>'backfill_runtime_missing','enqueued'=>0];
            if (!empty($backfill_result['ok'])) {
                $message = 'Data Hub 3A: ' . (int)($backfill_result['enqueued'] ?? 0) . ' submissão(ões) histórica(s) colocada(s) na fila Cloud.';
                if (!empty($backfill_result['duplicates'])) $message .= ' ' . (int)$backfill_result['duplicates'] . ' já estavam enfileiradas e foram ignoradas com segurança.';
                $syncDone = (int)($backfill_result['dispatch_before']['completed'] ?? 0) + (int)($backfill_result['dispatch_after']['completed'] ?? 0);
                if ($syncDone > 0) $message .= ' ' . $syncDone . ' job(s) enviados imediatamente ao Azure.';
                if ((int)($backfill_result['queue_pending'] ?? 0) > 0) $message .= ' Restam ' . (int)$backfill_result['queue_pending'] . ' job(s) Cloud pendentes.';
                if (!empty($backfill_result['completed'])) $message .= ' Snapshot histórico concluído.';
            } else {
                $message = 'Data Hub 3A: lote histórico não foi processado (' . sanitize_text_field((string)($backfill_result['reason'] ?? 'erro')) . ').';
            }
        }
        if (!empty($_POST['routespro_process_submission_backfill_queue']) && isset($_POST['routespro_cloud_backfill_nonce']) && wp_verify_nonce($_POST['routespro_cloud_backfill_nonce'], 'routespro_cloud_backfill_tools')) {
            $backfill_result = class_exists('\RoutesPro\Cloud\SubmissionBackfill')
                ? \RoutesPro\Cloud\SubmissionBackfill::processPendingNow(25)
                : ['ok'=>false,'reason'=>'backfill_runtime_missing'];
            if (!empty($backfill_result['ok'])) {
                $message = 'Data Hub 3A: fila Cloud processada agora. ' . (int)($backfill_result['dispatch']['completed'] ?? 0) . ' concluído(s), ' . (int)($backfill_result['queue_pending'] ?? 0) . ' pendente(s).';
            } else {
                $message = 'Data Hub 3A: não foi possível processar a fila Cloud agora (' . sanitize_text_field((string)($backfill_result['reason'] ?? 'erro')) . ').';
            }
        }

        $operational_backfill_result = null;
        if (!empty($_POST['routespro_start_operational_backfill']) && isset($_POST['routespro_cloud_operations_nonce']) && wp_verify_nonce($_POST['routespro_cloud_operations_nonce'], 'routespro_cloud_operations_tools')) {
            $operational_backfill_result = class_exists('\RoutesPro\Cloud\OperationalBackfill')
                ? \RoutesPro\Cloud\OperationalBackfill::startAll(false)
                : ['ok'=>false,'reason'=>'operations_backfill_runtime_missing'];
            $message = !empty($operational_backfill_result['ok'])
                ? 'Data Hub 3B: snapshots de Lojas, Rotas e Visitas preparados.'
                : 'Data Hub 3B: não foi possível preparar os snapshots operacionais.';
        }
        if (!empty($_POST['routespro_enqueue_operational_backfill']) && isset($_POST['routespro_cloud_operations_nonce']) && wp_verify_nonce($_POST['routespro_cloud_operations_nonce'], 'routespro_cloud_operations_tools')) {
            $entity = sanitize_key((string)($_POST['routespro_operational_entity'] ?? ''));
            $limit = max(1, min(100, absint($_POST['routespro_operational_limit'] ?? 25)));
            $operational_backfill_result = class_exists('\RoutesPro\Cloud\OperationalBackfill')
                ? \RoutesPro\Cloud\OperationalBackfill::enqueueNext($entity, $limit)
                : ['ok'=>false,'reason'=>'operations_backfill_runtime_missing','enqueued'=>0];
            if (!empty($operational_backfill_result['ok'])) {
                $message = 'Data Hub 3B: ' . (int)($operational_backfill_result['enqueued'] ?? 0) . ' registo(s) de ' . sanitize_text_field($entity) . ' colocado(s) na fila Cloud.';
                if (!empty($operational_backfill_result['duplicates'])) $message .= ' ' . (int)$operational_backfill_result['duplicates'] . ' já estavam enfileirados.';
                if (!empty($operational_backfill_result['skipped'])) $message .= ' ' . (int)$operational_backfill_result['skipped'] . ' registo(s) histórico(s) inválido(s) ignorado(s) sem bloquear o cursor.';
                $syncDone = (int)($operational_backfill_result['dispatch_before']['completed'] ?? 0) + (int)($operational_backfill_result['dispatch_after']['completed'] ?? 0);
                if ($syncDone > 0) $message .= ' ' . $syncDone . ' job(s) enviados imediatamente ao Azure.';
            } else {
                $message = 'Data Hub 3B: lote operacional não foi processado (' . sanitize_text_field((string)($operational_backfill_result['reason'] ?? 'erro')) . ').';
            }
        }
        if (!empty($_POST['routespro_process_operational_backfill_queue']) && isset($_POST['routespro_cloud_operations_nonce']) && wp_verify_nonce($_POST['routespro_cloud_operations_nonce'], 'routespro_cloud_operations_tools')) {
            $operational_backfill_result = class_exists('\RoutesPro\Cloud\OperationalBackfill')
                ? \RoutesPro\Cloud\OperationalBackfill::processPendingNow(25)
                : ['ok'=>false,'reason'=>'operations_backfill_runtime_missing'];
            if (!empty($operational_backfill_result['ok'])) {
                $message = 'Data Hub 3B: fila operacional processada agora. ' . (int)($operational_backfill_result['dispatch']['completed'] ?? 0) . ' concluído(s), ' . (int)($operational_backfill_result['queue_pending'] ?? 0) . ' pendente(s).';
            } else {
                $message = 'Data Hub 3B: não foi possível processar a fila operacional agora.';
            }
        }

        $phase3c_backfill_result = null;
        if (!empty($_POST['routespro_start_phase3c_backfill']) && isset($_POST['routespro_cloud_phase3c_nonce']) && wp_verify_nonce($_POST['routespro_cloud_phase3c_nonce'], 'routespro_cloud_phase3c_tools')) {
            $phase3c_backfill_result = class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')
                ? \RoutesPro\Cloud\BriefingStockBackfill::startAll(false)
                : ['ok'=>false,'reason'=>'phase3c_backfill_runtime_missing'];
            $message = !empty($phase3c_backfill_result['ok'])
                ? 'Data Hub 3C: snapshots de Briefings e Stocks preparados.'
                : 'Data Hub 3C: não foi possível preparar os snapshots.';
        }
        if (!empty($_POST['routespro_enqueue_phase3c_backfill']) && isset($_POST['routespro_cloud_phase3c_nonce']) && wp_verify_nonce($_POST['routespro_cloud_phase3c_nonce'], 'routespro_cloud_phase3c_tools')) {
            $entity = sanitize_key((string)($_POST['routespro_phase3c_entity'] ?? ''));
            $limit = max(1, min(100, absint($_POST['routespro_phase3c_limit'] ?? 25)));
            $phase3c_backfill_result = class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')
                ? \RoutesPro\Cloud\BriefingStockBackfill::enqueueNext($entity, $limit)
                : ['ok'=>false,'reason'=>'phase3c_backfill_runtime_missing','enqueued'=>0];
            if (!empty($phase3c_backfill_result['ok'])) {
                $message = 'Data Hub 3C: lote de ' . sanitize_text_field($entity) . ' colocado na fila Cloud.';
                if (isset($phase3c_backfill_result['source_rows'])) $message .= ' ' . (int)$phase3c_backfill_result['source_rows'] . ' registo(s) de origem processado(s).';
                if (!empty($phase3c_backfill_result['duplicates'])) $message .= ' ' . (int)$phase3c_backfill_result['duplicates'] . ' evento(s) já existiam e foram ignorados com segurança.';
                $syncDone = (int)($phase3c_backfill_result['dispatch_before']['completed'] ?? 0) + (int)($phase3c_backfill_result['dispatch_after']['completed'] ?? 0);
                if ($syncDone > 0) $message .= ' ' . $syncDone . ' job(s) enviados imediatamente ao Azure.';
            } else {
                $reason = sanitize_key((string)($phase3c_backfill_result['reason'] ?? 'erro'));
                $labels = [
                    'data_type_disabled' => 'ativa primeiro o respetivo tipo de dados nas opções Cloud',
                    'cloud_sync_disabled' => 'o Azure Cloud Sync está desligado',
                    'cloud_not_configured' => 'a ligação Azure Cloud não está configurada',
                ];
                $message = 'Data Hub 3C: lote não foi processado (' . sanitize_text_field($labels[$reason] ?? $reason) . ').';
            }
        }
        if (!empty($_POST['routespro_process_phase3c_backfill_queue']) && isset($_POST['routespro_cloud_phase3c_nonce']) && wp_verify_nonce($_POST['routespro_cloud_phase3c_nonce'], 'routespro_cloud_phase3c_tools')) {
            $phase3c_backfill_result = class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')
                ? \RoutesPro\Cloud\BriefingStockBackfill::processPendingNow(25)
                : ['ok'=>false,'reason'=>'phase3c_backfill_runtime_missing'];
            if (!empty($phase3c_backfill_result['ok'])) {
                $message = 'Data Hub 3C: fila processada agora. ' . (int)($phase3c_backfill_result['dispatch']['completed'] ?? 0) . ' concluído(s), ' . (int)($phase3c_backfill_result['queue_pending'] ?? 0) . ' pendente(s).';
            } else {
                $message = 'Data Hub 3C: não foi possível processar a fila agora.';
            }
        }


        if (!empty($_POST['routespro_start_phase3c_stock_auto']) && isset($_POST['routespro_cloud_phase3c_nonce']) && wp_verify_nonce($_POST['routespro_cloud_phase3c_nonce'], 'routespro_cloud_phase3c_tools')) {
            $phase3c_backfill_result = class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')
                ? \RoutesPro\Cloud\BriefingStockBackfill::startStockAuto()
                : ['ok'=>false,'reason'=>'phase3c_backfill_runtime_missing'];
            $message = !empty($phase3c_backfill_result['ok'])
                ? 'Data Hub 3C: sincronização automática das linhas de stock iniciada. O runner mantém chunks técnicos de 100 registos.'
                : 'Data Hub 3C: não foi possível iniciar o Auto Runner de stock (' . sanitize_text_field((string)($phase3c_backfill_result['reason'] ?? 'erro')) . ').';
        }
        if (!empty($_POST['routespro_pause_phase3c_stock_auto']) && isset($_POST['routespro_cloud_phase3c_nonce']) && wp_verify_nonce($_POST['routespro_cloud_phase3c_nonce'], 'routespro_cloud_phase3c_tools')) {
            $phase3c_backfill_result = class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')
                ? \RoutesPro\Cloud\BriefingStockBackfill::pauseStockAuto()
                : ['ok'=>false,'reason'=>'phase3c_backfill_runtime_missing'];
            $message = 'Data Hub 3C: Auto Runner de stock pausado. O cursor foi preservado e pode ser retomado sem duplicações.';
        }
        if (!empty($_POST['routespro_resume_phase3c_stock_auto']) && isset($_POST['routespro_cloud_phase3c_nonce']) && wp_verify_nonce($_POST['routespro_cloud_phase3c_nonce'], 'routespro_cloud_phase3c_tools')) {
            $phase3c_backfill_result = class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill')
                ? \RoutesPro\Cloud\BriefingStockBackfill::startStockAuto()
                : ['ok'=>false,'reason'=>'phase3c_backfill_runtime_missing'];
            $message = !empty($phase3c_backfill_result['ok'])
                ? 'Data Hub 3C: Auto Runner de stock retomado a partir do cursor atual.'
                : 'Data Hub 3C: não foi possível retomar o Auto Runner (' . sanitize_text_field((string)($phase3c_backfill_result['reason'] ?? 'erro')) . ').';
        }

        $operative_backfill_result = null;
        if (!empty($_POST['routespro_start_operative_backfill']) && isset($_POST['routespro_cloud_operatives_nonce']) && wp_verify_nonce($_POST['routespro_cloud_operatives_nonce'], 'routespro_cloud_operatives_tools')) {
            $operative_backfill_result = class_exists('\RoutesPro\Cloud\OperativeBackfill')
                ? \RoutesPro\Cloud\OperativeBackfill::start(false)
                : ['ok'=>false,'reason'=>'operative_backfill_runtime_missing'];
            $message = !empty($operative_backfill_result['ok'])
                ? 'Data Hub 4B.1: snapshot de operativos preparado.'
                : 'Data Hub 4B.1: não foi possível preparar o snapshot de operativos.';
        }
        if (!empty($_POST['routespro_enqueue_operative_backfill']) && isset($_POST['routespro_cloud_operatives_nonce']) && wp_verify_nonce($_POST['routespro_cloud_operatives_nonce'], 'routespro_cloud_operatives_tools')) {
            $limit = max(1, min(100, absint($_POST['routespro_operative_limit'] ?? 50)));
            $operative_backfill_result = class_exists('\RoutesPro\Cloud\OperativeBackfill')
                ? \RoutesPro\Cloud\OperativeBackfill::enqueueNext($limit)
                : ['ok'=>false,'reason'=>'operative_backfill_runtime_missing','enqueued'=>0];
            if (!empty($operative_backfill_result['ok'])) {
                $message = 'Data Hub 4B.1: ' . (int)($operative_backfill_result['enqueued'] ?? 0) . ' operativo(s) colocado(s) na fila Cloud.';
                if (!empty($operative_backfill_result['duplicates'])) $message .= ' ' . (int)$operative_backfill_result['duplicates'] . ' já estavam sincronizados.';
                $syncDone = (int)($operative_backfill_result['dispatch_before']['completed'] ?? 0) + (int)($operative_backfill_result['dispatch_after']['completed'] ?? 0);
                if ($syncDone > 0) $message .= ' ' . $syncDone . ' job(s) enviados imediatamente ao Azure.';
                if (!empty($operative_backfill_result['completed'])) $message .= ' Snapshot de operativos concluído.';
            } else {
                $message = 'Data Hub 4B.1: lote de operativos não foi processado (' . sanitize_text_field((string)($operative_backfill_result['reason'] ?? 'erro')) . ').';
            }
        }
        if (!empty($_POST['routespro_process_operative_backfill_queue']) && isset($_POST['routespro_cloud_operatives_nonce']) && wp_verify_nonce($_POST['routespro_cloud_operatives_nonce'], 'routespro_cloud_operatives_tools')) {
            $operative_backfill_result = class_exists('\RoutesPro\Cloud\OperativeBackfill')
                ? \RoutesPro\Cloud\OperativeBackfill::processPendingNow(25)
                : ['ok'=>false,'reason'=>'operative_backfill_runtime_missing'];
            $message = !empty($operative_backfill_result['ok'])
                ? 'Data Hub 4B.1: fila de operativos processada agora. ' . (int)($operative_backfill_result['dispatch']['completed'] ?? 0) . ' concluído(s).'
                : 'Data Hub 4B.1: não foi possível processar a fila de operativos agora.';
        }

        $o = self::get();
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $logs = $wpdb->get_results("SELECT * FROM {$px}integration_logs ORDER BY id DESC LIMIT 50", ARRAY_A) ?: [];
        $base = rest_url('routespro/v1/integrations/export');
        $azure_health = class_exists('\\RoutesPro\\Cloud\\AzureSyncFoundation') ? \RoutesPro\Cloud\AzureSyncFoundation::health() : [];
        $azure_recent = class_exists('\\RoutesPro\\Cloud\\AzureSyncFoundation') ? \RoutesPro\Cloud\AzureSyncFoundation::recent(20) : [];
        $backfill_status = class_exists('\\RoutesPro\\Cloud\\SubmissionBackfill') ? \RoutesPro\Cloud\SubmissionBackfill::status() : [];
        $operational_backfill_status = class_exists('\\RoutesPro\\Cloud\\OperationalBackfill') ? \RoutesPro\Cloud\OperationalBackfill::status() : [];
        $phase3c_backfill_status = class_exists('\\RoutesPro\\Cloud\\BriefingStockBackfill') ? \RoutesPro\Cloud\BriefingStockBackfill::status() : [];
        $operative_backfill_status = class_exists('\RoutesPro\Cloud\OperativeBackfill') ? \RoutesPro\Cloud\OperativeBackfill::status() : [];
        ?>
        <div class="wrap">
          <?php \RoutesPro\Admin\Branding::render_header('Integrações', 'Liga a operação a Power BI, Azure, Google Cloud e CRMs externos, com uma camada neutra de exportação e logs.'); ?>
          <?php if ($message): ?><div class="updated notice"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
          <?php if (is_string($revealed_api_token) && $revealed_api_token !== ''): ?><div class="notice notice-warning"><p><strong>Novo token API, visível apenas nesta resposta:</strong></p><p><code style="user-select:all"><?php echo esc_html($revealed_api_token); ?></code></p></div><?php endif; ?>
          <?php if (class_exists('\\RoutesPro\\Admin\\PublicIntegrations')) \RoutesPro\Admin\PublicIntegrations::renderSummary(); ?>
          <?php if (!empty($test_result)): ?><div class="notice notice-info"><p><?php echo esc_html('HTTP ' . ($test_result['http_code'] ?? '-') . ' | ' . substr((string)($test_result['body_sample'] ?? ''), 0, 180)); ?></p></div><?php endif; ?>
          <style>
            .routespro-int-grid{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:20px;align-items:start}
            .routespro-int-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.06);padding:22px;margin-top:18px}
            .routespro-int-card h2{margin-top:0}
            .routespro-int-mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;word-break:break-all;background:#0f172a;color:#e2e8f0;border-radius:12px;padding:14px}
            .routespro-int-badges span{display:inline-block;background:#eef2ff;color:#3730a3;padding:4px 10px;border-radius:999px;margin:0 8px 8px 0;font-size:12px;font-weight:600}
            @media (max-width:1180px){.routespro-int-grid{grid-template-columns:1fr}}
            .routespro-int-actions{display:flex;gap:8px;flex-wrap:wrap}
            .ff-stock-auto{margin-top:12px;padding:14px;border:1px solid #c7d2fe;border-radius:14px;background:#f8faff}
            .ff-stock-auto-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
            .ff-stock-auto-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;background:#e0e7ff;color:#3730a3;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
            .ff-stock-auto-progress{height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin:12px 0 8px}
            .ff-stock-auto-progress>span{display:block;height:100%;background:#4f46e5;width:0;transition:width .25s ease}
            .ff-stock-auto-stats{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:#475569}
            .ff-stock-auto-stats strong{color:#0f172a}
          </style>
          <div class="routespro-int-grid"><div>
            <form method="post">
              <?php wp_nonce_field('routespro_integrations_save', 'routespro_integrations_nonce'); ?>
              <div class="routespro-int-card">
                <h2>API de Integração</h2>
                <p>Camada neutra para Power BI, Azure Data Factory, BigQuery, CRMs e integrações próprias.</p>
                <table class="form-table"><tbody>
                  <tr><th>Ativa</th><td><label><input type="checkbox" name="api_enabled" value="1" <?php checked(!empty($o['api_enabled'])); ?>> Expor endpoints externos</label></td></tr>
                  <tr><th>Token</th><td><input type="password" autocomplete="new-password" class="regular-text code" name="api_token" value="" placeholder="<?php echo !empty($o['api_token']) ? 'Token guardado' : 'Gerado automaticamente'; ?>"> <p class="description">O valor nunca e devolvido ao HTML. Deixa em branco para manter. Envia em <code>X-RoutesPro-Token</code> ou <code>Authorization: Bearer TOKEN</code>.</p></td></tr>
                  <tr><th>Recursos</th><td>
                    <label><input type="checkbox" name="api_allow_clients" value="1" <?php checked(!empty($o['api_allow_clients'])); ?>> Clientes</label><br>
                    <label><input type="checkbox" name="api_allow_projects" value="1" <?php checked(!empty($o['api_allow_projects'])); ?>> Campanhas</label><br>
                    <label><input type="checkbox" name="api_allow_locations" value="1" <?php checked(!empty($o['api_allow_locations'])); ?>> PDVs</label><br>
                    <label><input type="checkbox" name="api_allow_routes" value="1" <?php checked(!empty($o['api_allow_routes'])); ?>> Rotas</label><br>
                    <label><input type="checkbox" name="api_allow_route_stops" value="1" <?php checked(!empty($o['api_allow_route_stops'])); ?>> Reporte por paragem</label><br>
                    <label><input type="checkbox" name="api_allow_events" value="1" <?php checked(!empty($o['api_allow_events'])); ?>> Eventos</label>
                  </td></tr>
                  <tr><th>Timeout</th><td><input type="number" min="5" max="120" name="sync_timeout" value="<?php echo esc_attr((string)$o['sync_timeout']); ?>"> segundos</td></tr>
                  <tr><th>Lote</th><td><input type="number" min="50" max="5000" name="batch_size" value="<?php echo esc_attr((string)$o['batch_size']); ?>"> linhas por sync</td></tr>
                </tbody></table>
                <p><button class="button button-primary">Guardar integrações</button></p>
              </div>

              <div class="routespro-int-card">
                <h2>FieldFlow Cloud Foundation</h2>
                <p><strong>Fase 3, fecho.</strong> O canal HMAC mantém-se intacto. Submissões, Visitas, Briefings e Stocks formam o Data Hub operacional. A camada Intelligence pode ser ativada separadamente para recomendações, feedback e outcomes.</p>
                <table class="form-table"><tbody>
                  <tr><th>Estado</th><td><strong><?php echo !empty($o['azure_cloud_sync_enabled']) ? 'SYNC ATIVO' : 'SYNC DESLIGADO'; ?></strong><p class="description">Os domínios operacionais podem permanecer ativos depois de validares o schema Azure. Intelligence é opt-in e não é enviado enquanto o contrato do receiver não estiver validado. O Auto Runner de stock usa chunks de 100 e mantém o cursor persistente.</p></td></tr>
                  <tr><th>Tenant ID</th><td><code><?php echo esc_html((string)($azure_health['tenant_id'] ?? '')); ?></code></td></tr>
                  <tr><th>Installation ID</th><td><code><?php echo esc_html((string)($azure_health['installation_id'] ?? '')); ?></code></td></tr>
                  <tr><th>Destino futuro</th><td><select name="azure_cloud_destination_mode"><option value="fieldflow_cloud" <?php selected($o['azure_cloud_destination_mode'], 'fieldflow_cloud'); ?>>FieldFlow Cloud</option><option value="client_azure" <?php selected($o['azure_cloud_destination_mode'], 'client_azure'); ?>>Azure do cliente</option></select></td></tr>
                  <tr><th>Endpoint Cloud</th><td><input type="url" class="regular-text" name="azure_cloud_endpoint" placeholder="https://func-fieldflow-...azurewebsites.net/api/v1/events" value="<?php echo esc_attr((string)($o['azure_cloud_endpoint'] ?? '')); ?>"><p class="description">Endpoint dedicado da Azure Function. Não uses aqui o conector Azure legado abaixo.</p></td></tr>
                  <tr><th>Segredo HMAC</th><td><input type="password" autocomplete="new-password" class="regular-text code" name="azure_cloud_hmac_secret" value="" placeholder="<?php echo !empty($o['azure_cloud_hmac_secret']) ? 'Segredo guardado' : 'Cole o segredo gerado no Azure'; ?>"><p class="description">Guardado cifrado pelo Secret Vault. Deixa em branco para manter o segredo atual.</p><?php if (!empty($o['azure_cloud_hmac_secret'])): ?><label><input type="checkbox" name="azure_cloud_hmac_secret_clear" value="1"> Apagar segredo guardado</label><?php endif; ?></td></tr>
                  <tr><th>Autenticação</th><td><strong>HMAC-SHA256 v1</strong><p class="description">Cada pedido assina tenant, instalação, timestamp, Event ID e hash SHA-256 do corpo.</p></td></tr>
                  <tr><th>Ativar sync</th><td><label><input type="checkbox" name="azure_cloud_sync_enabled" value="1" <?php checked(!empty($o['azure_cloud_sync_enabled'])); ?>> Ativar fila de sincronizacao Azure</label></td></tr>
                  <tr><th>Dados</th><td>
                    <label><input type="checkbox" name="azure_cloud_sync_submissions" value="1" <?php checked(!empty($o['azure_cloud_sync_submissions'])); ?>> Submissoes</label><br>
                    <label><input type="checkbox" name="azure_cloud_sync_visits" value="1" <?php checked(!empty($o['azure_cloud_sync_visits'])); ?>> Visitas concluidas</label><br>
                    <label><input type="checkbox" name="azure_cloud_sync_briefings" value="1" <?php checked(!empty($o['azure_cloud_sync_briefings'])); ?>> Briefings publicados</label><br>
                    <label><input type="checkbox" name="azure_cloud_sync_stocks" value="1" <?php checked(!empty($o['azure_cloud_sync_stocks'])); ?>> Imports de stock</label><br>
                    <label><input type="checkbox" name="azure_cloud_sync_operatives" value="1" <?php checked(!empty($o['azure_cloud_sync_operatives'])); ?>> Operativos / Merchandisers</label>
                    <br><label><input type="checkbox" name="azure_cloud_sync_intelligence" value="1" <?php checked(!empty($o['azure_cloud_sync_intelligence'])); ?>> Intelligence, recomendações e feedback <strong>(opt-in)</strong></label><p class="description" style="margin:5px 0 0">Desligado por defeito. Ativa apenas quando o receiver Azure do tenant aceitar o contrato <code>fieldflow.intelligence.learning</code>.</p>
                  </td></tr>
                  <tr><th>Retries</th><td><input type="number" min="1" max="12" name="azure_cloud_sync_max_attempts" value="<?php echo esc_attr((string)$o['azure_cloud_sync_max_attempts']); ?>"> tentativas por evento</td></tr>
                  <tr><th>Lote</th><td><input type="number" min="1" max="100" name="azure_cloud_sync_batch_size" value="<?php echo esc_attr((string)$o['azure_cloud_sync_batch_size']); ?>"> eventos por execucao</td></tr>
                </tbody></table>
                <div class="routespro-int-badges">
                  <span>Tabela: <?php echo !empty($azure_health['table_ready']) ? 'OK' : 'ERRO'; ?></span>
                  <span>Pendentes: <?php echo (int)($azure_health['stats']['pending'] ?? 0); ?></span>
                  <span>Falhados: <?php echo (int)($azure_health['stats']['dead'] ?? 0); ?></span>
                  <span>24h: <?php echo (int)($azure_health['stats']['completed_24h'] ?? 0); ?></span>
                </div>
                <p class="description">Os eventos sao gravados localmente com tenant_id, client_id e project_id. Se o Azure ficar indisponivel, o FieldFlow continua a gravar no WordPress e a fila faz retry sem bloquear o merchandiser.</p>
              </div>

              <div class="routespro-int-card">
                <h2>Power BI / Reporting 4A</h2>
                <p><strong>Camada recomendada:</strong> Azure SQL <code><?php echo esc_html((string)($reporting_status['sql_schema'] ?? 'rpt')); ?></code> + DirectQuery + Microsoft Entra SSO.</p>
                <p class="description">O WordPress não guarda utilizadores, passwords, client secrets ou connection strings da camada de reporting. O acesso é concedido no Azure SQL por identidade Entra e por scope tenant / cliente / projeto.</p>
                <div class="routespro-int-badges">
                  <span>Contrato: <?php echo esc_html((string)($reporting_status['version'] ?? '-')); ?></span>
                  <span>Data Hub: <?php echo esc_html((string)($reporting_status['data_hub_schema_version'] ?? '-')); ?></span>
                  <span>Schema SQL: <?php echo esc_html((string)($reporting_status['sql_schema'] ?? 'rpt')); ?></span>
                  <span>Credenciais WP: NÃO</span>
                </div>
                <p style="margin-top:12px"><strong>Views seguras previstas:</strong> <?php echo (int)count((array)($reporting_status['secure_views'] ?? [])); ?></p>
                <div class="routespro-int-mono" style="max-height:180px;overflow:auto"><?php foreach ((array)($reporting_status['secure_views'] ?? []) as $view) echo esc_html((string)$view) . '<br>'; ?></div>
                <p class="description" style="margin-top:10px">A identidade de ingestão da Azure Function não recebe permissões de reporting. Power BI usa uma identidade separada e apenas o schema <code>rpt</code>.</p>
              </div>

              <div class="routespro-int-card">
                <h2>Power BI legado, Push JSON</h2>
                <p class="description">Compatibilidade com o conector push anterior. Para novos deployments usa a camada segura Azure SQL / Power BI da Fase 4A, sem guardar credenciais SQL no WordPress.</p>
                <table class="form-table"><tbody>
                  <tr><th>Ativa</th><td><label><input type="checkbox" name="powerbi_enabled" value="1" <?php checked(!empty($o['powerbi_enabled'])); ?>> Ativar destino Power BI</label></td></tr>
                  <tr><th>Push URL</th><td><input type="url" class="regular-text" name="powerbi_push_url" placeholder="https://api.powerbi.com/..." value="<?php echo esc_attr($o['powerbi_push_url']); ?>"></td></tr>
                  <tr><th>Autenticação</th><td><select name="powerbi_auth_type"><option value="bearer" <?php selected($o['powerbi_auth_type'], 'bearer'); ?>>Bearer</option><option value="none" <?php selected($o['powerbi_auth_type'], 'none'); ?>>Nenhuma</option></select></td></tr>
                  <tr><th>Token</th><td><input type="password" autocomplete="new-password" class="regular-text code" name="powerbi_token" value="" placeholder="<?php echo !empty($o['powerbi_token']) ? 'Token guardado' : 'Sem token'; ?>"><p class="description">Deixa em branco para manter o valor cifrado. O token nao e devolvido ao browser.</p><?php if (!empty($o['powerbi_token'])): ?><label><input type="checkbox" name="powerbi_token_clear" value="1"> Apagar token guardado</label><?php endif; ?></td></tr>
                  <tr><th>Tabela lógica</th><td><input type="text" class="regular-text" name="powerbi_table" value="<?php echo esc_attr($o['powerbi_table']); ?>"></td></tr>
                </tbody></table>
              </div>

              <div class="routespro-int-card">
                <h2>Google Cloud</h2>
                <p>Preparado para pipelines de ingestão, Cloud Run, Functions ou proxy para BigQuery.</p>
                <table class="form-table"><tbody>
                  <tr><th>Ativa</th><td><label><input type="checkbox" name="gcloud_enabled" value="1" <?php checked(!empty($o['gcloud_enabled'])); ?>> Ativar destino Google Cloud</label></td></tr>
                  <tr><th>Endpoint</th><td><input type="url" class="regular-text" name="gcloud_push_url" placeholder="https://...run.app/..." value="<?php echo esc_attr($o['gcloud_push_url']); ?>"></td></tr>
                  <tr><th>Header auth</th><td><input type="password" autocomplete="new-password" class="regular-text code" name="gcloud_auth_header" placeholder="<?php echo !empty($o['gcloud_auth_header']) ? 'Credencial guardada' : 'Authorization: Bearer XXX'; ?>" value=""><p class="description">Deixa em branco para manter. O valor nao e devolvido ao browser.</p><?php if (!empty($o['gcloud_auth_header'])): ?><label><input type="checkbox" name="gcloud_auth_header_clear" value="1"> Apagar credencial guardada</label><?php endif; ?></td></tr>
                  <tr><th>Projeto</th><td><input type="text" class="regular-text" name="gcloud_project" value="<?php echo esc_attr($o['gcloud_project']); ?>"></td></tr>
                  <tr><th>Dataset</th><td><input type="text" class="regular-text" name="gcloud_dataset" value="<?php echo esc_attr($o['gcloud_dataset']); ?>"></td></tr>
                  <tr><th>Tabela</th><td><input type="text" class="regular-text" name="gcloud_table" value="<?php echo esc_attr($o['gcloud_table']); ?>"></td></tr>
                  <tr><th>JSON service account</th><td><textarea name="gcloud_service_account_json" class="large-text code" rows="6" placeholder="<?php echo !empty($o['gcloud_service_account_json']) ? 'Service account guardada, deixa vazio para manter' : 'Cole o JSON apenas se necessario'; ?>"></textarea><p class="description">O JSON guardado nao e devolvido ao browser.</p><?php if (!empty($o['gcloud_service_account_json'])): ?><label><input type="checkbox" name="gcloud_service_account_json_clear" value="1"> Apagar service account guardada</label><?php endif; ?></td></tr>
                </tbody></table>
              </div>

              <div class="routespro-int-card">
                <h2>Microsoft Azure, conector atual</h2>
                <p>Mantido apenas por compatibilidade com integracoes existentes. O novo Cloud Sync seguro usa o Endpoint Cloud e o segredo HMAC acima, sem depender deste conector legado.</p>
                <table class="form-table"><tbody>
                  <tr><th>Ativa</th><td><label><input type="checkbox" name="azure_enabled" value="1" <?php checked(!empty($o['azure_enabled'])); ?>> Ativar destino Azure</label></td></tr>
                  <tr><th>Endpoint</th><td><input type="url" class="regular-text" name="azure_push_url" placeholder="https://prod-xx.westeurope.logic.azure.com/..." value="<?php echo esc_attr($o['azure_push_url']); ?>"></td></tr>
                  <tr><th>Header auth</th><td><input type="password" autocomplete="new-password" class="regular-text code" name="azure_auth_header" placeholder="<?php echo !empty($o['azure_auth_header']) ? 'Credencial guardada' : 'x-functions-key: XXX'; ?>" value=""><p class="description">Deixa em branco para manter. O valor nao e devolvido ao browser.</p><?php if (!empty($o['azure_auth_header'])): ?><label><input type="checkbox" name="azure_auth_header_clear" value="1"> Apagar credencial guardada</label><?php endif; ?></td></tr>
                  <tr><th>Workspace</th><td><input type="text" class="regular-text" name="azure_workspace" value="<?php echo esc_attr($o['azure_workspace']); ?>"></td></tr>
                  <tr><th>Dataset</th><td><input type="text" class="regular-text" name="azure_dataset" value="<?php echo esc_attr($o['azure_dataset']); ?>"></td></tr>
                </tbody></table>
              </div>
            </form>
          </div><aside>
            <div class="routespro-int-card">
              <h2>Endpoints</h2>
              <div class="routespro-int-badges"><span>/schema</span><span>/export</span><span>/push</span><span>/test</span></div>
              <p>Exemplo rápido para Power BI, Azure Data Factory, BigQuery proxy ou CRM externo:</p><p class="description">Nesta fase o push segue num envelope com <code>connector</code>, <code>resource</code>, <code>meta</code> e <code>export</code>, para o teu middleware usar tabela, dataset ou workspace.</p>
              <div class="routespro-int-mono"><?php echo esc_html($base . '?resource=locations&client_id=0&project_id=0&limit=500'); ?></div>
              <p style="margin-top:12px">Headers:</p>
              <div class="routespro-int-mono">X-RoutesPro-Token: <?php echo esc_html('********' . substr((string)$o['api_token'], -4)); ?></div>
              <form method="post" style="margin-top:14px">
                <?php wp_nonce_field('routespro_integrations_regenerate', 'routespro_integrations_regenerate_nonce'); ?>
                <input type="hidden" name="routespro_regenerate_token" value="1">
                <button class="button">Regenerar token</button>
              </form>
            </div>
            <div class="routespro-int-card">
              <h2>Diagnostico Cloud Foundation</h2>
              <p>Teste exclusivamente local. Nao envia dados para Azure.</p>
              <ul style="margin-left:18px;list-style:disc">
                <li>Identidade multi-tenant: <?php echo !empty($azure_health['tenant_id']) ? 'OK' : 'ERRO'; ?></li>
                <li>Fila persistente: <?php echo !empty($azure_health['table_ready']) ? 'OK' : 'ERRO'; ?></li>
                <li>Endpoint HMAC: <?php echo !empty($azure_health['connection']['configured']) ? 'CONFIGURADO' : 'POR CONFIGURAR'; ?></li>
                <li>Segredo HMAC: <?php echo !empty($azure_health['connection']['secret_configured']) ? 'OK' : 'POR CONFIGURAR'; ?></li>
                <li>Azure Sync: <?php echo !empty($azure_health['connection']['sync_enabled']) ? 'ATIVO' : 'DESLIGADO'; ?></li>
              </ul>
              <form method="post" style="margin-top:12px">
                <?php wp_nonce_field('routespro_azure_foundation_tools', 'routespro_azure_foundation_nonce'); ?>
                <input type="hidden" name="routespro_test_azure_foundation" value="1">
                <button class="button">Testar fundacao local</button>
              </form>
              <form method="post" style="margin-top:8px">
                <?php wp_nonce_field('routespro_azure_foundation_tools', 'routespro_azure_foundation_nonce'); ?>
                <input type="hidden" name="routespro_test_azure_secure" value="1">
                <button class="button" <?php disabled(empty($azure_health['connection']['configured'])); ?>>Testar ligação Azure assinada</button>
              </form>
              <?php if (is_array($secure_cloud_probe)): ?><div class="routespro-int-mono" style="margin-top:12px"><?php echo esc_html('HTTP ' . ($secure_cloud_probe['http_code'] ?? '-') . ' | ' . ($secure_cloud_probe['body_sample'] ?? '')); ?></div><?php endif; ?>
              <?php if ((int)($azure_health['stats']['dead'] ?? 0) > 0): ?>
              <form method="post" style="margin-top:8px">
                <?php wp_nonce_field('routespro_azure_foundation_tools', 'routespro_azure_foundation_nonce'); ?>
                <input type="hidden" name="routespro_retry_azure_sync" value="1">
                <button class="button">Recolocar falhados na fila</button>
              </form>
              <?php endif; ?>
              <?php if (is_array($foundation_probe) && !empty($foundation_probe['checks'])): ?>
                <div class="routespro-int-mono" style="margin-top:12px"><?php foreach ($foundation_probe['checks'] as $check => $ok) echo esc_html($check . ': ' . ($ok ? 'OK' : 'ERRO')) . '<br>'; ?></div>
              <?php endif; ?>
            </div>
            <div class="routespro-int-card">
              <h2>Data Hub 3A, Submissões</h2>
              <p>Backfill histórico controlado para o novo modelo analítico Azure. O snapshot fixa o maior ID existente no momento do arranque, por isso novas submissões continuam a seguir apenas pelo sync normal.</p>
              <ul style="margin-left:18px;list-style:disc">
                <li>Runtime backfill: <?php echo !empty($backfill_status['available']) ? 'OK' : 'INDISPONÍVEL'; ?></li>
                <li>Snapshot: <?php echo !empty($backfill_status['started']) ? 'INICIADO' : 'POR INICIAR'; ?></li>
                <li>Enfileiradas no snapshot: <?php echo (int)($backfill_status['enqueued'] ?? $backfill_status['processed'] ?? 0); ?> / <?php echo (int)($backfill_status['total'] ?? 0); ?></li>
                <li>Restantes por enfileirar: <?php echo (int)($backfill_status['remaining'] ?? 0); ?></li>
                <li>Fila histórica pendente: <?php echo (int)($backfill_status['queue_pending'] ?? 0); ?></li>
                <li>Fila histórica em curso: <?php echo (int)($backfill_status['queue_running'] ?? 0); ?></li>
                <li>Falhas definitivas: <?php echo (int)($backfill_status['queue_dead'] ?? 0); ?></li>
                <li>Último ID: <?php echo (int)($backfill_status['cursor'] ?? 0); ?> / <?php echo (int)($backfill_status['max_id'] ?? 0); ?></li>
              </ul>
              <?php if (empty($backfill_status['started'])): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_backfill_tools', 'routespro_cloud_backfill_nonce'); ?>
                <input type="hidden" name="routespro_start_submission_backfill" value="1">
                <button class="button" <?php disabled(empty($backfill_status['available'])); ?>>Preparar snapshot histórico</button>
              </form>
              <?php elseif ((int)($backfill_status['remaining'] ?? 0) > 0): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_backfill_tools', 'routespro_cloud_backfill_nonce'); ?>
                <input type="hidden" name="routespro_enqueue_submission_backfill" value="1">
                <label>Lote <input type="number" name="routespro_backfill_limit" min="1" max="100" value="10" style="width:80px"></label>
                <button class="button button-primary" <?php disabled(empty($backfill_status['cloud_ready']) || empty($backfill_status['sync_enabled']) || empty($backfill_status['submissions_enabled'])); ?>>Enviar próximo lote</button>
              </form>
              <?php elseif (empty($backfill_status['fully_completed'])): ?>
                <p><strong>Todos os registos do snapshot já foram enfileirados, mas ainda existem jobs Cloud por concluir.</strong></p>
              <?php else: ?>
                <p><strong>Snapshot histórico concluído e entregue ao Azure.</strong></p>
              <?php endif; ?>
              <?php if ((int)($backfill_status['queue_pending'] ?? 0) > 0 || (int)($backfill_status['queue_running'] ?? 0) > 0): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_backfill_tools', 'routespro_cloud_backfill_nonce'); ?>
                <input type="hidden" name="routespro_process_submission_backfill_queue" value="1">
                <button class="button">Processar fila Cloud agora</button>
              </form>
              <?php endif; ?>
              <p class="description">O backfill envia diretamente para a fila Cloud e não volta a disparar webhooks, faturação ou integrações normais do formulário. Os Event UUID são determinísticos para permitir repetição segura.</p>
              <?php if (is_array($backfill_result)): ?><div class="routespro-int-mono" style="margin-top:10px"><?php echo esc_html(wp_json_encode($backfill_result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div><?php endif; ?>
            </div>

            <div class="routespro-int-card">
              <h2>Data Hub 3B, Lojas + Rotas + Visitas</h2>
              <p>Snapshot operacional controlado para popular as dimensões e factos de execução no Azure. As Lojas e Rotas são também atualizadas automaticamente no Cloud quando uma nova Visita concluída for sincronizada.</p>
              <?php $opEntities = (array)($operational_backfill_status['entities'] ?? []); ?>
              <ul style="margin-left:18px;list-style:disc">
                <li>Runtime operacional: <?php echo !empty($operational_backfill_status['available']) ? 'OK' : 'INDISPONÍVEL'; ?></li>
                <li>Fila operacional pendente: <?php echo (int)($operational_backfill_status['queue_pending'] ?? 0); ?></li>
                <li>Fila operacional em curso: <?php echo (int)($operational_backfill_status['queue_running'] ?? 0); ?></li>
                <li>Falhas definitivas: <?php echo (int)($operational_backfill_status['queue_dead'] ?? 0); ?></li>
              </ul>
              <?php if (empty($opEntities['locations']['started']) && empty($opEntities['routes']['started']) && empty($opEntities['visits']['started'])): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_operations_tools', 'routespro_cloud_operations_nonce'); ?>
                <input type="hidden" name="routespro_start_operational_backfill" value="1">
                <button class="button" <?php disabled(empty($operational_backfill_status['available'])); ?>>Preparar snapshots 3B</button>
              </form>
              <?php else: ?>
                <?php foreach (['locations','routes','visits'] as $opEntity): $op = (array)($opEntities[$opEntity] ?? []); ?>
                  <div style="border-top:1px solid #e5e7eb;margin-top:14px;padding-top:12px">
                    <strong><?php echo esc_html((string)($op['label'] ?? $opEntity)); ?></strong>
                    <div style="margin-top:6px">Enfileirados: <?php echo (int)($op['enqueued'] ?? 0); ?> / <?php echo (int)($op['total'] ?? 0); ?> · Restantes: <?php echo (int)($op['remaining'] ?? 0); ?> · Pendentes: <?php echo (int)($op['queue_pending'] ?? 0); ?> · Falhados: <?php echo (int)($op['queue_dead'] ?? 0); ?><?php if ((int)($op['ignored_integrity'] ?? 0) > 0): ?> · Ignorados por integridade: <?php echo (int)$op['ignored_integrity']; ?><?php endif; ?></div>
                    <?php if ((int)($op['remaining'] ?? 0) > 0): ?>
                    <form method="post" style="margin-top:8px">
                      <?php wp_nonce_field('routespro_cloud_operations_tools', 'routespro_cloud_operations_nonce'); ?>
                      <input type="hidden" name="routespro_enqueue_operational_backfill" value="1">
                      <input type="hidden" name="routespro_operational_entity" value="<?php echo esc_attr($opEntity); ?>">
                      <label>Lote <input type="number" name="routespro_operational_limit" min="1" max="100" value="10" style="width:80px"></label>
                      <button class="button button-primary" <?php disabled(empty($operational_backfill_status['cloud_ready']) || empty($operational_backfill_status['sync_enabled'])); ?>>Enviar próximo lote</button>
                    </form>
                    <?php elseif (!empty($op['fully_completed'])): ?>
                      <div style="margin-top:8px"><strong>Snapshot concluído.</strong></div>
                    <?php else: ?>
                      <div style="margin-top:8px">Todos os registos já foram enfileirados. Aguarda a conclusão da fila.</div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php if ((int)($operational_backfill_status['queue_pending'] ?? 0) > 0 || (int)($operational_backfill_status['queue_running'] ?? 0) > 0): ?>
              <form method="post" style="margin-top:12px">
                <?php wp_nonce_field('routespro_cloud_operations_tools', 'routespro_cloud_operations_nonce'); ?>
                <input type="hidden" name="routespro_process_operational_backfill_queue" value="1">
                <button class="button">Processar fila operacional agora</button>
              </form>
              <?php endif; ?>
              <p class="description">Ordem recomendada: Lojas, Rotas, Visitas. O snapshot usa Event UUID determinístico e não altera rotas, visitas ou lojas no WordPress.</p>
              <?php if (is_array($operational_backfill_result)): ?><div class="routespro-int-mono" style="margin-top:10px"><?php echo esc_html(wp_json_encode($operational_backfill_result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div><?php endif; ?>
            </div>

            <div class="routespro-int-card">
              <h2>Data Hub 3C, Briefings + Stocks</h2>
              <p>Fecha a Fase 3 com o contexto comercial da execução. Briefings são materializados com secções, perguntas e alocação a lojas. Stocks são divididos em lote + linhas para suportar ficheiros grandes sem exceder o limite de payload Cloud.</p>
              <?php $phase3cEntities = (array)($phase3c_backfill_status['entities'] ?? []); $stockAuto = (array)($phase3c_backfill_status['stock_auto'] ?? []); ?>
              <ul style="margin-left:18px;list-style:disc">
                <li>Runtime 3C: <?php echo !empty($phase3c_backfill_status['available']) ? 'OK' : 'INDISPONÍVEL'; ?></li>
                <li>Briefings Cloud: <?php echo !empty($phase3c_backfill_status['briefings_enabled']) ? 'ATIVO' : 'DESLIGADO'; ?></li>
                <li>Stocks Cloud: <?php echo !empty($phase3c_backfill_status['stocks_enabled']) ? 'ATIVO' : 'DESLIGADO'; ?></li>
                <li>Fila 3C pendente: <?php echo (int)($phase3c_backfill_status['queue_pending'] ?? 0); ?></li>
                <li>Fila 3C em curso: <?php echo (int)($phase3c_backfill_status['queue_running'] ?? 0); ?></li>
                <li>Falhas definitivas: <?php echo (int)($phase3c_backfill_status['queue_dead'] ?? 0); ?></li>
              </ul>
              <?php if (empty($phase3cEntities['briefings']['started']) && empty($phase3cEntities['stock_batches']['started']) && empty($phase3cEntities['stock_items']['started'])): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_phase3c_tools', 'routespro_cloud_phase3c_nonce'); ?>
                <input type="hidden" name="routespro_start_phase3c_backfill" value="1">
                <button class="button" <?php disabled(empty($phase3c_backfill_status['available'])); ?>>Preparar snapshots 3C</button>
              </form>
              <?php else: ?>
                <?php foreach (['briefings','stock_batches','stock_items'] as $phase3cEntity): $c3 = (array)($phase3cEntities[$phase3cEntity] ?? []); ?>
                  <div style="border-top:1px solid #e5e7eb;margin-top:14px;padding-top:12px">
                    <strong><?php echo esc_html((string)($c3['label'] ?? $phase3cEntity)); ?></strong>
                    <div style="margin-top:6px">Origem percorrida: <?php echo (int)($c3['enqueued'] ?? 0); ?> / <?php echo (int)($c3['total'] ?? 0); ?> · Restantes: <?php echo (int)($c3['remaining'] ?? 0); ?> · Pendentes: <?php echo (int)($c3['queue_pending'] ?? 0); ?> · Falhados: <?php echo (int)($c3['queue_dead'] ?? 0); ?> · Tipo Cloud: <?php echo !empty($c3['enabled']) ? 'ATIVO' : 'DESLIGADO'; ?></div>
                    <?php if ($phase3cEntity === 'stock_items'): ?>
                    <div class="ff-stock-auto" id="routespro-stock-auto-runner" data-active="<?php echo !empty($stockAuto['active']) ? '1' : '0'; ?>">
                      <div class="ff-stock-auto-head">
                        <div><strong>Stock Bulk Backfill Auto Runner</strong><div class="description">Um clique, chunks técnicos fixos de 100, cursor persistente e Event UUID determinístico.</div></div>
                        <span class="ff-stock-auto-badge" data-ff-auto-mode><?php echo esc_html(strtoupper((string)($stockAuto['mode'] ?? 'idle'))); ?></span>
                      </div>
                      <div class="ff-stock-auto-progress"><span data-ff-auto-bar style="width:<?php echo esc_attr((string)min(100, max(0, (float)($stockAuto['progress_percent'] ?? 0)))); ?>%"></span></div>
                      <div class="ff-stock-auto-stats">
                        <span>Progresso <strong data-ff-auto-progress><?php echo esc_html(number_format_i18n((float)($stockAuto['progress_percent'] ?? 0), 1)); ?>%</strong></span>
                        <span>Sincronizado <strong data-ff-auto-enqueued><?php echo (int)($stockAuto['enqueued'] ?? 0); ?></strong> / <strong data-ff-auto-total><?php echo (int)($stockAuto['total'] ?? 0); ?></strong></span>
                        <span>Restantes <strong data-ff-auto-remaining><?php echo (int)($stockAuto['remaining'] ?? 0); ?></strong></span>
                        <span>Velocidade <strong data-ff-auto-speed><?php echo esc_html(number_format_i18n((float)($stockAuto['rows_per_minute'] ?? 0), 0)); ?></strong> linhas/min</span>
                        <span>ETA <strong data-ff-auto-eta><?php $eta=(int)($stockAuto['eta_seconds'] ?? 0); echo $eta>0 ? esc_html(human_time_diff(time(), time()+$eta)) : '—'; ?></strong></span>
                        <span>Fila <strong data-ff-auto-queue><?php echo (int)($stockAuto['queue_pending'] ?? 0); ?></strong></span>
                      </div>
                      <?php if (!empty($stockAuto['last_error'])): ?><p class="description" data-ff-auto-error style="color:#b91c1c;margin-top:8px">Último erro: <?php echo esc_html((string)$stockAuto['last_error']); ?></p><?php else: ?><p class="description" data-ff-auto-error style="margin-top:8px"></p><?php endif; ?>
                      <div class="routespro-int-actions" style="margin-top:10px">
                        <?php if (!empty($stockAuto['active'])): ?>
                        <form method="post"><?php wp_nonce_field('routespro_cloud_phase3c_tools', 'routespro_cloud_phase3c_nonce'); ?><input type="hidden" name="routespro_pause_phase3c_stock_auto" value="1"><button class="button">Pausar Auto Runner</button></form>
                        <?php elseif ((int)($c3['remaining'] ?? 0) > 0): ?>
                        <form method="post"><?php wp_nonce_field('routespro_cloud_phase3c_tools', 'routespro_cloud_phase3c_nonce'); ?><input type="hidden" name="<?php echo in_array((string)($stockAuto['mode'] ?? ''), ['paused','error'], true) ? 'routespro_resume_phase3c_stock_auto' : 'routespro_start_phase3c_stock_auto'; ?>" value="1"><button class="button button-primary" <?php disabled(empty($phase3c_backfill_status['cloud_ready']) || empty($phase3c_backfill_status['sync_enabled']) || empty($c3['enabled']) || (int)($c3['queue_dead'] ?? 0) > 0); ?>><?php echo in_array((string)($stockAuto['mode'] ?? ''), ['paused','error'], true) ? 'Retomar sincronização automática' : 'Sincronizar todo o stock restante'; ?></button></form>
                        <?php elseif (!empty($c3['fully_completed'])): ?><strong>Auto Runner concluído.</strong><?php endif; ?>
                      </div>
                      <p class="description" style="margin-top:8px">Mantém esta página aberta para velocidade máxima. O scheduler do WordPress fica como fallback em background. Pausar não apaga nem reinicia o cursor.</p>
                    </div>
                    <?php endif; ?>
                    <?php if ((int)($c3['remaining'] ?? 0) > 0): ?>
                    <form method="post" style="margin-top:8px">
                      <?php wp_nonce_field('routespro_cloud_phase3c_tools', 'routespro_cloud_phase3c_nonce'); ?>
                      <input type="hidden" name="routespro_enqueue_phase3c_backfill" value="1">
                      <input type="hidden" name="routespro_phase3c_entity" value="<?php echo esc_attr($phase3cEntity); ?>">
                      <label>Lote <input type="number" name="routespro_phase3c_limit" min="1" max="100" value="<?php echo $phase3cEntity === 'stock_items' ? 100 : 10; ?>" style="width:80px"></label>
                      <button class="button button-primary" <?php disabled(empty($phase3c_backfill_status['cloud_ready']) || empty($phase3c_backfill_status['sync_enabled']) || empty($c3['enabled']) || ($phase3cEntity === 'stock_items' && !empty($stockAuto['active']))); ?>>Enviar próximo lote</button>
                    </form>
                    <?php elseif (!empty($c3['fully_completed'])): ?>
                      <div style="margin-top:8px"><strong>Snapshot concluído.</strong></div>
                    <?php else: ?>
                      <div style="margin-top:8px">Todos os registos já foram enfileirados. Aguarda a conclusão da fila.</div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php if ((int)($phase3c_backfill_status['queue_pending'] ?? 0) > 0 || (int)($phase3c_backfill_status['queue_running'] ?? 0) > 0): ?>
              <form method="post" style="margin-top:12px">
                <?php wp_nonce_field('routespro_cloud_phase3c_tools', 'routespro_cloud_phase3c_nonce'); ?>
                <input type="hidden" name="routespro_process_phase3c_backfill_queue" value="1">
                <button class="button">Processar fila 3C agora</button>
              </form>
              <?php endif; ?>
              <p class="description">Ordem recomendada: Briefings, Lotes de stock, Linhas de stock. Para enviar histórico, ativa primeiro “Briefings publicados” e “Imports de stock” na secção Dados. Linhas de stock são agrupadas em chunks e mantêm Event UUID determinístico.</p>
              <?php if (!empty($stockAuto['active'])): ?>
              <script>
              (function(){
                const root=document.getElementById('routespro-stock-auto-runner');
                if(!root)return;
                let running=root.dataset.active==='1',busy=false;
                const nonce=<?php echo wp_json_encode(wp_create_nonce('routespro_cloud_phase3c_stock_auto_ajax')); ?>;
                const get=(name)=>root.querySelector('[data-ff-auto-'+name+']');
                const fmt=(n)=>Number(n||0).toLocaleString('pt-PT');
                const eta=(seconds)=>{seconds=Number(seconds||0);if(!seconds)return '—';const m=Math.ceil(seconds/60);if(m<60)return m+' min';const h=Math.floor(m/60),r=m%60;return h+' h'+(r?' '+r+' min':'');};
                function paint(r){
                  if(!r)return;
                  const mode=get('mode'),bar=get('bar'),progress=get('progress'),enqueued=get('enqueued'),total=get('total'),remaining=get('remaining'),speed=get('speed'),etaEl=get('eta'),queue=get('queue'),error=get('error');
                  if(mode)mode.textContent=String(r.mode||'idle').toUpperCase();
                  if(bar)bar.style.width=Math.max(0,Math.min(100,Number(r.progress_percent||0)))+'%';
                  if(progress)progress.textContent=Number(r.progress_percent||0).toLocaleString('pt-PT',{minimumFractionDigits:1,maximumFractionDigits:1})+'%';
                  if(enqueued)enqueued.textContent=fmt(r.enqueued);if(total)total.textContent=fmt(r.total);if(remaining)remaining.textContent=fmt(r.remaining);
                  if(speed)speed.textContent=fmt(Math.round(Number(r.rows_per_minute||0)));if(etaEl)etaEl.textContent=eta(r.eta_seconds);if(queue)queue.textContent=fmt(r.queue_pending);
                  if(error)error.textContent=r.last_error?'Último erro: '+r.last_error:'';
                  running=!!r.active;
                }
                async function tick(){
                  if(!running||busy)return;busy=true;
                  try{
                    const body=new URLSearchParams({action:'fieldflow_phase3c_stock_auto_tick',nonce});
                    const response=await fetch(window.ajaxurl||ajaxurl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()});
                    const json=await response.json();
                    if(json&&json.data&&json.data.runner)paint(json.data.runner);
                    if(json&&json.success&&json.data&&json.data.runner&&json.data.runner.active){setTimeout(tick,Number(json.data.runner.poll_delay_ms||650));return;}
                    running=false;setTimeout(()=>window.location.reload(),900);
                  }catch(e){
                    const error=get('error');if(error)error.textContent='Ligação ao Auto Runner interrompida. Nova tentativa automática em 3 segundos.';
                    setTimeout(tick,3000);
                  }finally{busy=false;}
                }
                setTimeout(tick,450);
              })();
              </script>
              <?php endif; ?>
              <?php if (is_array($phase3c_backfill_result)): ?><div class="routespro-int-mono" style="margin-top:10px"><?php echo esc_html(wp_json_encode($phase3c_backfill_result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div><?php endif; ?>
            </div>

            <div class="routespro-int-card">
              <h2>Data Hub 4B.1, Operativos / Merchandisers</h2>
              <p>Dimensão de reporting para filtrar Power BI por operativo. Só replica utilizadores operacionalmente relevantes e exclui email, telefone e outros dados de contacto.</p>
              <ul style="margin-left:18px;list-style:disc">
                <li>Sync de operativos: <?php echo !empty($operative_backfill_status['operatives_enabled']) ? 'ATIVO' : 'DESLIGADO'; ?></li>
                <li>Snapshot: <?php echo !empty($operative_backfill_status['started']) ? 'INICIADO' : 'POR INICIAR'; ?></li>
                <li>Enfileirados: <?php echo (int)($operative_backfill_status['enqueued'] ?? 0); ?> / <?php echo (int)($operative_backfill_status['total'] ?? 0); ?></li>
                <li>Restantes: <?php echo (int)($operative_backfill_status['remaining'] ?? 0); ?></li>
                <li>Pendentes: <?php echo (int)($operative_backfill_status['queue_pending'] ?? 0); ?> · Falhados: <?php echo (int)($operative_backfill_status['queue_dead'] ?? 0); ?></li>
              </ul>
              <?php if (empty($operative_backfill_status['started'])): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_operatives_tools', 'routespro_cloud_operatives_nonce'); ?>
                <input type="hidden" name="routespro_start_operative_backfill" value="1">
                <button class="button" <?php disabled(empty($operative_backfill_status['available'])); ?>>Preparar snapshot de operativos</button>
              </form>
              <?php elseif ((int)($operative_backfill_status['remaining'] ?? 0) > 0): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_operatives_tools', 'routespro_cloud_operatives_nonce'); ?>
                <input type="hidden" name="routespro_enqueue_operative_backfill" value="1">
                <label>Lote <input type="number" name="routespro_operative_limit" min="1" max="100" value="50" style="width:80px"></label>
                <button class="button button-primary" <?php disabled(empty($operative_backfill_status['cloud_ready']) || empty($operative_backfill_status['sync_enabled']) || empty($operative_backfill_status['operatives_enabled'])); ?>>Sincronizar próximo lote</button>
              </form>
              <?php elseif (!empty($operative_backfill_status['fully_completed'])): ?>
                <p><strong>Snapshot de operativos concluído e entregue ao Azure.</strong></p>
              <?php else: ?>
                <p>Todos os operativos já foram enfileirados. Aguarda a conclusão da fila.</p>
              <?php endif; ?>
              <?php if ((int)($operative_backfill_status['queue_pending'] ?? 0) > 0 || (int)($operative_backfill_status['queue_running'] ?? 0) > 0): ?>
              <form method="post" style="margin-top:10px">
                <?php wp_nonce_field('routespro_cloud_operatives_tools', 'routespro_cloud_operatives_nonce'); ?>
                <input type="hidden" name="routespro_process_operative_backfill_queue" value="1">
                <button class="button">Processar fila de operativos agora</button>
              </form>
              <?php endif; ?>
              <p class="description">O snapshot usa IDs determinísticos e passa a manter o nome do operativo atualizado quando existem alterações de perfil, função ou eventos operacionais. Não sincroniza email.</p>
              <?php if (is_array($operative_backfill_result)): ?><div class="routespro-int-mono" style="margin-top:10px"><?php echo esc_html(wp_json_encode($operative_backfill_result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></div><?php endif; ?>
            </div>

            <div class="routespro-int-card">
              <h2>Testes rápidos</h2>
              <p>Valida cada destino antes de ligares automações externas.</p>
              <div class="routespro-int-actions">
                <form method="post"><?php wp_nonce_field('routespro_integrations_tools', 'routespro_integrations_tools_nonce'); ?><input type="hidden" name="routespro_test_connector" value="powerbi"><button class="button">Testar Power BI</button></form>
                <form method="post"><?php wp_nonce_field('routespro_integrations_tools', 'routespro_integrations_tools_nonce'); ?><input type="hidden" name="routespro_test_connector" value="gcloud"><button class="button">Testar Google Cloud</button></form>
                <form method="post"><?php wp_nonce_field('routespro_integrations_tools', 'routespro_integrations_tools_nonce'); ?><input type="hidden" name="routespro_test_connector" value="azure"><button class="button">Testar Azure</button></form>
              </div>
            </div>
            <div class="routespro-int-card">
              <h2>Modelo de dados exportável</h2>
              <ul style="margin-left:18px;list-style:disc">
                <li>Clientes</li>
                <li>Campanhas</li>
                <li>PDVs</li>
                <li>Rotas</li>
                <li>Paragens com reporte</li>
                <li>Eventos operacionais</li>
              </ul>
              <p class="description">Fase 4A: o Data Hub permanece a origem operacional e o Power BI passa a consumir apenas views seguras do schema rpt, filtradas por identidade Entra e scope tenant / cliente / projeto.</p>
            </div>
            <div class="routespro-int-card">
              <h2>Fila Cloud recente</h2>
              <div style="max-height:360px;overflow:auto">
                <table class="widefat striped"><thead><tr><th>Evento</th><th>Estado</th><th>Tent.</th><th>Quando</th></tr></thead><tbody>
                <?php if (!$azure_recent): ?><tr><td colspan="4">Sem eventos Cloud. E o comportamento esperado enquanto o sync estiver desligado.</td></tr>
                <?php else: foreach ($azure_recent as $job): ?><tr><td><?php echo esc_html((string)$job['event_name']); ?></td><td><?php echo esc_html((string)$job['status']); ?></td><td><?php echo (int)$job['attempts']; ?>/<?php echo (int)$job['max_attempts']; ?></td><td><?php echo esc_html((string)$job['created_at']); ?></td></tr><?php endforeach; endif; ?>
                </tbody></table>
              </div>
            </div>
            <div class="routespro-int-card">
              <h2>Logs recentes</h2>
              <div style="max-height:500px;overflow:auto">
                <table class="widefat striped"><thead><tr><th>Quando</th><th>Destino</th><th>Ação</th><th>Estado</th></tr></thead><tbody>
                  <?php if (!$logs): ?>
                    <tr><td colspan="4">Sem logs ainda.</td></tr>
                  <?php else: foreach ($logs as $log): ?>
                    <tr>
                      <td><?php echo esc_html((string)$log['created_at']); ?></td>
                      <td><?php echo esc_html((string)$log['connector']); ?></td>
                      <td><?php echo esc_html((string)$log['action']); ?></td>
                      <td><?php echo esc_html((string)$log['status']); ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody></table>
              </div>
            </div>
          </aside></div>
        </div>
        <?php
    }
}
