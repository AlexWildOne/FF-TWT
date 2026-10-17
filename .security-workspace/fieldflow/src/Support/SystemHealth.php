<?php
namespace RoutesPro\Support;

use RoutesPro\Repositories\SchemaRepository;
use RoutesPro\Repositories\SystemLogRepository;
use RoutesPro\Support\LicenseManager;

if (!defined('ABSPATH')) exit;

class SystemHealth {
    public static function status(): array {
        global $wp_version;
        $upload = wp_upload_dir();
        $opts = Config::all();
        $missing_tables = SchemaRepository::missingTables();
        $plugin_root = trailingslashit(ROUTESPRO_PATH);
        $backup_matches = [];
        $missing_runtime_files = class_exists('\RoutesPro\Support\Loader') ? Loader::missingFiles() : [];
        $release_files = ['readme.txt', 'uninstall.php', 'CHANGELOG.md', 'languages/routes-pro.pot', 'production-manifest.json'];
        $missing_release_files = [];
        foreach ($release_files as $release_file) {
            if (!file_exists($plugin_root . $release_file)) {
                $missing_release_files[] = $release_file;
            }
        }
        foreach (['*.bak', '*.bak_*', '*.before*'] as $pattern) {
            $found = glob($plugin_root . 'src/Admin/' . $pattern) ?: [];
            foreach ($found as $file) {
                $backup_matches[] = wp_basename($file);
            }
        }
        $integrity = class_exists('\\RoutesPro\\Core\\ReconciliationService')
            ? \RoutesPro\Core\ReconciliationService::quickOverview()
            : ['events' => ['total' => 0, 'pending' => 0, 'processed' => 0, 'failed' => 0], 'audit_last_24h' => 0, 'tables_ready' => false];

        $observability = class_exists('\RoutesPro\Observability\Monitor') ? \RoutesPro\Observability\Monitor::summary() : ['tables_ready'=>false,'open'=>0,'critical'=>0,'warning'=>0,'events_24h'=>0,'last_heartbeat'=>''];
        $security = class_exists('\RoutesPro\Security\SecurityService') ? \RoutesPro\Security\SecurityService::counts() : ['open'=>0,'high'=>0,'critical'=>0,'total'=>0];
        $rest_scope_guard = class_exists('\RoutesPro\Security\RestScopeGuard') ? \RoutesPro\Security\RestScopeGuard::status() : ['version'=>'','contract'=>'','registered'=>false,'explicit_scope_guard'=>false,'project_client_consistency'=>false,'object_permissions_preserved'=>false];
        $storage_governance = class_exists('\RoutesPro\Security\StorageGovernance') ? \RoutesPro\Security\StorageGovernance::diagnostics() : ['version'=>'','contract'=>'','scheduled'=>false,'cleanup_scope'=>'','preserves_evidence'=>true,'preserves_active_drafts'=>true,'total_bytes'=>0,'total_mb'=>0,'total_files'=>0,'roots'=>[],'last_cleanup'=>[]];
        $outbound_http = class_exists('\\RoutesPro\\Security\\OutboundHttp') ? \RoutesPro\Security\OutboundHttp::status() : ['version'=>'','https_only'=>false,'private_networks_blocked'=>false,'unsafe_url_rejection'=>false,'tls_verify_forced'=>false,'redirects_default'=>-1,'default_port'=>0];
        $session_policy = class_exists('\\RoutesPro\\Security\\SessionPolicy') ? \RoutesPro\Security\SessionPolicy::status() : ['version'=>'','mfa_mode'=>'observe','session_mode'=>'observe','privileged_users'=>0,'mfa_enabled_users'=>0,'mfa_grace_ends_at'=>'','mobile_privileged_mfa'=>false,'wp_privileged_idle_minutes'=>0,'wp_privileged_absolute_hours'=>0,'mobile_idle_days'=>0,'mobile_absolute_days'=>0,'web_idle_minutes'=>0,'web_absolute_hours'=>0];
        $billing = class_exists('\RoutesPro\Billing\BillingRepository') ? [
            'tables_ready'=>\RoutesPro\Billing\BillingRepository::tablesReady(),
            'settings'=>\RoutesPro\Billing\Settings::publicSummary(),
            'metrics'=>\RoutesPro\Billing\BillingRepository::tablesReady() ? \RoutesPro\Billing\BillingRepository::metrics() : ['active'=>0,'past_due'=>0,'paid_minor'=>0,'open_minor'=>0],
        ] : ['tables_ready'=>false,'settings'=>['enabled'=>false,'mode'=>'sandbox','secret_configured'=>false,'webhook_configured'=>false],'metrics'=>['active'=>0,'past_due'=>0,'paid_minor'=>0,'open_minor'=>0]];

        $offline = class_exists('\RoutesPro\Mobile\OfflineSyncService')
            ? \RoutesPro\Mobile\OfflineSyncService::stats()
            : ['tables_ready' => false, 'devices' => 0, 'pending' => 0, 'failed' => 0, 'active_24h' => 0, 'last_sync_at' => ''];
        $submission_pipeline = class_exists('\RoutesPro\Forms\SubmissionPipeline')
            ? \RoutesPro\Forms\SubmissionPipeline::publicState()
            : [];
        $credential_vault = class_exists('\RoutesPro\Integrations\CredentialStore')
            ? \RoutesPro\Integrations\CredentialStore::status()
            : ['can_encrypt'=>false,'configured_fields'=>0,'encrypted_fields'=>0,'legacy_fields'=>0,'unreadable_fields'=>0];
        $scope_index = class_exists('\RoutesPro\Support\ScopeIndex')
            ? \RoutesPro\Support\ScopeIndex::status()
            : ['tables_ready'=>false,'generation'=>0,'indexed_users'=>0,'memberships'=>0,'fallback_available'=>true];
        $backoffice_ui = class_exists('\RoutesPro\Admin\BackofficeUIFoundation')
            ? \RoutesPro\Admin\BackofficeUIFoundation::status()
            : ['version'=>'','mode'=>'legacy','enabled'=>false,'assets_ready'=>false,'legacy_fallback'=>true,'managed_pages'=>0,'priority_pages'=>0,'current_scope'=>'','asset_bytes'=>0];
        $privacy_governance = class_exists('\RoutesPro\Privacy\PrivacyGovernance')
            ? \RoutesPro\Privacy\PrivacyGovernance::status()
            : ['version'=>'','enabled'=>false,'mode'=>'shadow','legal_hold'=>false,'tables_ready'=>false,'open_requests'=>0,'last_run'=>[]];
        $ai_governance = class_exists('\RoutesPro\AI\Governance')
            ? \RoutesPro\AI\Governance::status()
            : ['version'=>'','mode'=>'off','redaction_enabled'=>false,'injection_blocking'=>false,'monthly_budget_eur'=>0,'month_cost_eur'=>0,'tracked_tokens'=>0];
        $load_readiness = class_exists('\RoutesPro\Performance\LoadReadiness')
            ? \RoutesPro\Performance\LoadReadiness::status()
            : ['version'=>'','enabled'=>false,'mode'=>'observe','table_ready'=>false,'sample_rate'=>0,'retention_days'=>0,'gate_status'=>'blocked','samples'=>0,'queue'=>[],'cron'=>[]];
        $queue_modernization = class_exists('\RoutesPro\Operations\Queue\QueueManager')
            ? \RoutesPro\Operations\Queue\QueueManager::status()
            : ['version'=>'','mode'=>'legacy','effective_driver'=>'wp_cron','table_ready'=>false,'status'=>'critical','stats'=>[],'legacy_fallback'=>true];
        $azure_cloud = class_exists('\\RoutesPro\\Cloud\\AzureSyncFoundation')
            ? \RoutesPro\Cloud\AzureSyncFoundation::health()
            : ['version'=>'','status'=>'critical','table_ready'=>false,'connection'=>['sync_enabled'=>false,'configured'=>false],'stats'=>[],'tenant_id'=>'','installation_id'=>''];
        $client_azure = class_exists('\RoutesPro\Cloud\ClientAzureDelivery')
            ? \RoutesPro\Cloud\ClientAzureDelivery::health()
            : ['version'=>'','status'=>'critical','table_ready'=>false,'mode'=>'shadow','relay'=>['configured'=>false],'stats'=>[]];
        $connector_5c = class_exists('\RoutesPro\Cloud\ConnectorFoundation')
            ? \RoutesPro\Cloud\ConnectorFoundation::health()
            : ['version'=>'','status'=>'attention','mode'=>'shadow','contracts_total'=>0,'contracts_ready'=>0,'destinations_total'=>0,'active_destinations'=>0,'network_delivery_enabled'=>false];
        $connector_delivery_5c5 = class_exists('\RoutesPro\Cloud\ConnectorDelivery')
            ? \RoutesPro\Cloud\ConnectorDelivery::health()
            : ['version'=>'','status'=>'critical','table_ready'=>false,'mode'=>'shadow','kill_switch'=>true,'network_delivery_enabled'=>false,'network_delivery_implemented'=>false,'eligible_destinations'=>0,'stats'=>[]];
        $controlled_delivery_5c6 = class_exists('\RoutesPro\Cloud\ControlledExternalDelivery')
            ? \RoutesPro\Cloud\ControlledExternalDelivery::health()
            : ['version'=>'','phase'=>'5C.6','status'=>'attention','table_ready'=>false,'broker_configured'=>false,'certification_only'=>true,'operational_delivery_enabled'=>false,'arbitrary_target_urls_allowed'=>false,'signed_receipts_required'=>true,'receipt_count'=>0];
        $safe_live_pilot_5c7 = class_exists('\RoutesPro\Cloud\SafeLivePilot')
            ? \RoutesPro\Cloud\SafeLivePilot::health()
            : ['version'=>'','phase'=>'5C.7','status'=>'attention','safe_projection_only'=>true,'full_operational_payload_delivery_enabled'=>false,'normal_5c5_dispatcher_enabled'=>false,'requires_shadow_mode'=>false,'requires_kill_switch'=>false,'prepared_targets'=>0,'synthetic_certified_targets'=>0,'armed_one_shots'=>0];
        $production_gate_5c8 = class_exists('\RoutesPro\Cloud\ProductionDeliveryGate')
            ? \RoutesPro\Cloud\ProductionDeliveryGate::health()
            : ['version'=>'','phase'=>'5C.8','status'=>'attention','safe_projection_only'=>true,'full_operational_payload_delivery_enabled'=>false,'general_production_activation_enabled'=>false,'requires_shadow_mode'=>false,'requires_kill_switch'=>false,'production_ready_gates'=>0,'prepared_gates'=>0,'target_certified_gates'=>0];
        $credential_lifecycle_5d = class_exists('\RoutesPro\Cloud\CredentialLifecycle')
            ? \RoutesPro\Cloud\CredentialLifecycle::health()
            : ['version'=>'','phase'=>'5D','status'=>'attention','key_vault_reference_only'=>false,'secret_values_stored_in_wordpress'=>false,'secret_values_returned_to_wordpress'=>false,'managed_identity_resolution'=>false,'rotation_detection'=>false,'expiry_enforcement'=>false,'revocation_guard'=>false,'signed_receipts_required'=>false,'general_production_activation_enabled'=>false,'operational_payload_delivery_enabled'=>false,'configured_credentials'=>0,'certified_credentials'=>0,'ready_credentials'=>0,'warning_credentials'=>0,'blocked_credentials'=>0,'revoked_credentials'=>0,'rotation_detected_credentials'=>0];
        $governance_recovery_5e = class_exists('\RoutesPro\Cloud\GovernanceRecovery')
            ? \RoutesPro\Cloud\GovernanceRecovery::health()
            : ['version'=>'','phase'=>'5E','status'=>'attention','ledger_ready'=>false,'ledger_chain_valid'=>false,'ledger_entries'=>0,'global_kill_switch'=>true,'dead_letter_count'=>0,'stale_running_count'=>0,'production_5c8_ready_gates'=>0,'credential_5d_ready'=>0,'ledger_certified'=>false,'replay_certified'=>false,'recovery_certified'=>false,'final_gate_certified'=>false,'eligibility_fingerprint_match'=>false,'production_eligible'=>false,'production_enabled'=>false,'full_operational_payload_enabled'=>false];
        $phase5_stable = class_exists('\RoutesPro\Cloud\Phase5StableRelease')
            ? \RoutesPro\Cloud\Phase5StableRelease::health()
            : ['version'=>'','phase'=>'5-STABLE','release'=>'','status'=>'attention','matrix_ready'=>false,'stable_certified'=>false,'baseline_match'=>false,'production_eligible'=>false,'production_enabled'=>false,'full_operational_payload_enabled'=>false];
        $client_onboarding_6a = class_exists('\RoutesPro\Cloud\ClientOnboardingFoundation')
            ? \RoutesPro\Cloud\ClientOnboardingFoundation::health()
            : ['version'=>'','phase'=>'6A','status'=>'attention','phase5_stable_certified'=>false,'phase5_baseline_match'=>false,'table_ready'=>false,'plans_total'=>0,'plans_staged'=>0,'plans_production_ready'=>0,'connector_shadow_preserved'=>false,'connector_kill_switch_on'=>true,'production_enabled'=>false,'full_operational_payload_enabled'=>false];
        $client_onboarding_cert_6a = class_exists('\RoutesPro\Cloud\ClientOnboardingCertification')
            ? \RoutesPro\Cloud\ClientOnboardingCertification::health()
            : ['version'=>'','phase'=>'6A-STABLE','release'=>'','status'=>'attention','matrix_ready'=>false,'stable_certified'=>false,'baseline_match'=>false,'production_enabled'=>false,'full_operational_payload_enabled'=>false];
        $license_tenants_6b = class_exists('\RoutesPro\Cloud\LicenseTenantFoundation')
            ? \RoutesPro\Cloud\LicenseTenantFoundation::health()
            : ['version'=>'','phase'=>'6B.1','status'=>'attention','phase6a_certified'=>false,'phase6a_baseline_match'=>false,'tables_ready'=>false,'saas_tables_ready'=>false,'remote_ready'=>false,'raw_license_keys_stored'=>false,'license_enforcement_enabled'=>false,'foundation_certified'=>false,'foundation_fingerprint_match'=>false,'tenants_total'=>0,'tenants_issued'=>0,'installations_total'=>0];
        $license_lifecycle_6b2 = class_exists('\\RoutesPro\\Cloud\\LicenseInstallationLifecycle')
            ? \RoutesPro\Cloud\LicenseInstallationLifecycle::health()
            : ['version'=>'','phase'=>'6B.2','status'=>'attention','phase6b1_certified'=>false,'installation_identity_ready'=>false,'activation_verified'=>false,'domain_binding_match'=>false,'heartbeat_accepted'=>false,'heartbeat_recent'=>false,'remote_identity_acknowledged'=>false,'remote_fingerprint_acknowledged'=>false,'remote_heartbeat_recorded'=>false,'license_enforcement_enabled'=>false,'installation_binding_enforced'=>false,'remote_revocation_enabled'=>false,'certified'=>false];
        $license_governance_6b3 = class_exists('\RoutesPro\Cloud\LicenseGovernanceLifecycle')
            ? \RoutesPro\Cloud\LicenseGovernanceLifecycle::health()
            : ['version'=>'','phase'=>'6B.3','status'=>'attention','phase6b2_certified'=>false,'remote_manage_ready'=>false,'policy_fingerprint_match'=>false,'installation_limit_healthy'=>false,'enforcement_mode'=>'shadow','runtime_blocking_enabled'=>false,'remote_revocation_enforced'=>false,'certified'=>false];
        $entra_5c2 = class_exists('\RoutesPro\Cloud\EntraCrossTenantFoundation')
            ? \RoutesPro\Cloud\EntraCrossTenantFoundation::status()
            : ['version'=>'','status'=>'attention','mode'=>'shadow','configured'=>false,'credential_model'=>'','wordpress_client_secret_stored'=>false,'network_delivery_enabled'=>false,'entra_destinations'=>0,'entra_destinations_ready'=>0];
        $entra_onboarding_5c2 = class_exists('\RoutesPro\Cloud\EntraTenantOnboarding')
            ? \RoutesPro\Cloud\EntraTenantOnboarding::summary()
            : ['version'=>'','control_plane_ready'=>false,'delivery_enabled'=>false,'total'=>0,'configured'=>0,'pending'=>0,'ready'=>0,'failed'=>0];
        $resource_authorization_5c3 = class_exists('\RoutesPro\Cloud\ResourceAuthorizationFoundation')
            ? \RoutesPro\Cloud\ResourceAuthorizationFoundation::summary()
            : ['version'=>'','mode'=>'shadow','control_plane_ready'=>false,'delivery_enabled'=>false,'read_only_probe'=>true,'eligible'=>0,'scope_ready'=>0,'configured'=>0,'authorized'=>0,'failed'=>0];
        $architecture_runtime = class_exists('\RoutesPro\Architecture\RuntimeRegistry')
            ? \RoutesPro\Architecture\RuntimeRegistry::status()
            : ['version'=>'','registered'=>false,'rest_controllers'=>0,'missing_controllers'=>['runtime_registry_missing'],'legacy_fallback'=>true];

        $schema_integrity = class_exists('\RoutesPro\Support\SchemaIntegrity')
            ? \RoutesPro\Support\SchemaIntegrity::status()
            : ['status'=>'unknown','summary'=>'Auditoria de schema indisponível.','counts'=>[],'mode'=>'none','generated_at'=>''];
        $migration_ledger = class_exists('\RoutesPro\Support\MigrationLedger')
            ? \RoutesPro\Support\MigrationLedger::status()
            : ['table_ready'=>false,'completed'=>0,'failed'=>0,'running'=>0,'pending'=>0];
        $migration_batches = class_exists('\RoutesPro\Support\BatchMigrationManager')
            ? \RoutesPro\Support\BatchMigrationManager::status()
            : ['table_ready'=>false,'completed'=>0,'failed'=>0,'running'=>0,'pending'=>0];
        $release_gate = class_exists('\RoutesPro\Support\ReleaseGate')
            ? \RoutesPro\Support\ReleaseGate::latest()
            : [];
        $media_registry = class_exists('\RoutesPro\Security\MediaRegistry')
            ? \RoutesPro\Security\MediaRegistry::diagnostics()
            : ['version'=>'','mode'=>'observe','table_ready'=>false,'total'=>0,'attachments'=>0,'direct_files'=>0,'linked_submissions'=>0,'protected'=>0];
        $media_shadow = class_exists('\RoutesPro\Security\MediaShadow')
            ? \RoutesPro\Security\MediaShadow::diagnostics(30)
            : ['version'=>'','mode'=>'shadow','table_ready'=>false,'days'=>30,'events'=>0,'allow'=>0,'deny'=>0,'unknown'=>0,'unique_media'=>0,'last_seen_at'=>''];

        $checks = [
            [
                'key' => 'php',
                'label' => 'PHP',
                'status' => version_compare(PHP_VERSION, '8.0', '>=') ? 'ok' : 'warning',
                'detail' => 'Versão atual: ' . PHP_VERSION . '. Recomendado 8.1 ou superior.',
            ],
            [
                'key' => 'wordpress',
                'label' => 'WordPress',
                'status' => version_compare((string) $wp_version, '6.4', '>=') ? 'ok' : 'warning',
                'detail' => 'Versão atual: ' . $wp_version . '. Recomendado 6.4 ou superior.',
            ],
            [
                'key' => 'uploads',
                'label' => 'Uploads',
                'status' => !empty($upload['basedir']) && wp_is_writable($upload['basedir']) ? 'ok' : 'critical',
                'detail' => !empty($upload['basedir']) ? 'Diretório: ' . $upload['basedir'] : 'Sem diretório de uploads resolvido.',
            ],
            [
                'key' => 'schema',
                'label' => 'Base de dados',
                'status' => empty($missing_tables) ? 'ok' : 'critical',
                'detail' => empty($missing_tables) ? 'Todas as tabelas nucleares existem.' : 'Tabelas em falta: ' . implode(', ', $missing_tables),
            ],
            [
                'key' => 'schema_integrity',
                'label' => 'Integridade do schema',
                'status' => ($schema_integrity['status'] ?? 'unknown') === 'critical' ? 'critical' : (($schema_integrity['status'] ?? 'unknown') === 'healthy' ? 'ok' : 'warning'),
                'detail' => (string)($schema_integrity['summary'] ?? 'Ainda não existe uma auditoria do schema.') . ' Modo: ' . (string)($schema_integrity['mode'] ?? 'none') . '.',
            ],
            [
                'key' => 'migration_safety',
                'label' => 'Segurança das migrações',
                'status' => empty($migration_ledger['table_ready']) || (int)($migration_ledger['failed'] ?? 0) > 0 ? 'critical' : ((int)($migration_ledger['running'] ?? 0) > 0 ? 'warning' : 'ok'),
                'detail' => !empty($migration_ledger['table_ready'])
                    ? 'Histórico ativo. Concluídas: ' . (int)($migration_ledger['completed'] ?? 0) . ', falhadas: ' . (int)($migration_ledger['failed'] ?? 0) . ', em execução: ' . (int)($migration_ledger['running'] ?? 0) . '.'
                    : 'A tabela do histórico de migrações ainda não está instalada.',
            ],
            [
                'key' => 'migration_batches',
                'label' => 'Migrações em lotes',
                'status' => empty($migration_batches['table_ready']) ? 'critical' : ((int)($migration_batches['failed'] ?? 0) > 0 ? 'warning' : 'ok'),
                'detail' => !empty($migration_batches['table_ready'])
                    ? 'Executor retomável ativo. Pendentes: ' . (int)($migration_batches['pending'] ?? 0) . ', em execução: ' . (int)($migration_batches['running'] ?? 0) . ', falhadas: ' . (int)($migration_batches['failed'] ?? 0) . '.'
                    : 'A tabela das tarefas em lotes ainda não está instalada.',
            ],
            [
                'key' => 'maps',
                'label' => 'Mapas',
                'status' => self::mapsStatus($opts),
                'detail' => 'Provider ativo: ' . ($opts['maps_provider'] ?? 'leaflet') . '.',
            ],
            [
                'key' => 'ai',
                'label' => 'IA',
                'status' => self::aiStatus($opts),
                'detail' => 'Motor principal: ' . \RoutesPro\Services\AIFactory::providerLabel(Config::aiProviderForModule('general', $opts)) . '. Configuração em FieldFlow > Inteligência Artificial.',
            ],
            [
                'key' => 'ai_governance',
                'label' => 'Governação da IA',
                'status' => ($ai_governance['mode'] ?? 'off') === 'off' ? 'warning' : 'ok',
                'detail' => 'Versão ' . (string)($ai_governance['version'] ?? '') . ', modo ' . (string)($ai_governance['mode'] ?? 'off') . ', attribution nativa: ' . (!empty($ai_governance['native_attribution']) ? 'sim' : 'não') . ', tokens acompanhados: ' . (int)($ai_governance['tracked_tokens'] ?? 0) . ', custo mensal estimado: ' . number_format((float)($ai_governance['month_cost_eur'] ?? 0), 4, ',', '.') . ' EUR, utilização do budget: ' . number_format((float)($ai_governance['budget_usage_percent'] ?? 0), 1, ',', '.') . '%.',
            ],
            [
                'key' => 'license',
                'label' => 'Licenciamento',
                'status' => LicenseManager::isRemoteMode() ? (LicenseManager::remoteReady() ? (LicenseManager::isActive() ? (LicenseManager::isBoundToCurrentDomain() ? 'ok' : 'critical') : 'warning') : 'critical') : (LicenseManager::isActive() ? (LicenseManager::isBoundToCurrentDomain() ? 'ok' : 'critical') : 'warning'),
                'detail' => 'Modo: ' . LicenseManager::mode() . '. Estado atual: ' . LicenseManager::statusLabel() . '. Domínio guardado: ' . (LicenseManager::get('domain', '') ?: 'por ligar') . '.',
            ],
            [
                'key' => 'logging',
                'label' => 'Logging técnico',
                'status' => SystemLogRepository::exists() ? 'ok' : 'warning',
                'detail' => SystemLogRepository::exists() ? 'Tabela de logs pronta para troubleshooting.' : 'Tabela de logs técnicos ainda não existe.',
            ],
            [
                'key' => 'core_integrity',
                'label' => 'Integridade central',
                'status' => !empty($integrity['tables_ready']) ? 'ok' : 'critical',
                'detail' => !empty($integrity['tables_ready'])
                    ? 'Estados canónicos, eventos, auditoria e reconciliação estão disponíveis.'
                    : 'A fundação de integridade ainda não está totalmente instalada.',
            ],
            [
                'key' => 'offline_first',
                'label' => 'App Offline First',
                'status' => !empty($offline['tables_ready']) ? ((int)($offline['active_failed_24h'] ?? $offline['failed'] ?? 0) > 0 ? 'warning' : 'ok') : 'critical',
                'detail' => !empty($offline['tables_ready'])
                    ? 'Cache operacional e fila idempotente ativos. Dispositivos: ' . (int)($offline['devices'] ?? 0)
                        . ', pendentes: ' . (int)($offline['pending'] ?? 0)
                        . ', falhas reais: ' . (int)($offline['failed'] ?? 0)
                        . ', conflitos: ' . (int)($offline['conflicts'] ?? 0)
                        . ', falhas ativas 24h: ' . (int)($offline['active_failed_24h'] ?? 0) . '.'
                    : 'A tabela de diagnóstico da sincronização offline ainda não está instalada.',
            ],
            [
                'key' => 'submission_pipeline',
                'label' => 'Pipeline de submissões',
                'status' => $submission_pipeline ? 'ok' : 'critical',
                'detail' => $submission_pipeline
                    ? 'Versão ' . (string)($submission_pipeline['pipeline_version'] ?? '') . ', modo ' . (string)($submission_pipeline['mode'] ?? 'shadow') . ', owner ' . (string)($submission_pipeline['owner'] ?? 'legacy') . '. Reversão automática disponível.'
                    : 'A camada pós-commit partilhada entre web e mobile não está disponível.',
            ],
            [
                'key' => 'observability',
                'label' => 'Observabilidade',
                'status' => !empty($observability['tables_ready']) ? ((int)($observability['critical_active'] ?? $observability['critical'] ?? 0) > 0 ? 'warning' : 'ok') : 'critical',
                'detail' => !empty($observability['tables_ready'])
                    ? 'Eventos abertos: ' . (int)($observability['open'] ?? 0)
                        . ', críticos ativos (' . (int)($observability['critical_active_window_hours'] ?? 6) . 'h): ' . (int)($observability['critical_active'] ?? 0)
                        . ', críticos históricos em aberto: ' . (int)($observability['critical_historical'] ?? 0)
                        . ', últimas 24h: ' . (int)($observability['events_24h'] ?? 0) . '.'
                    : 'As tabelas de observabilidade ainda não estão instaladas.',
            ],
            [
                'key' => 'load_readiness',
                'label' => 'Performance e carga',
                'status' => empty($load_readiness['table_ready']) ? 'critical' : (empty($load_readiness['enabled']) || in_array((string)($load_readiness['gate_status'] ?? ''), ['fail','blocked'], true) ? 'warning' : 'ok'),
                'detail' => !empty($load_readiness['table_ready'])
                    ? 'Load Readiness ' . (string)($load_readiness['version'] ?? '') . ' em observação a ' . (int)($load_readiness['sample_rate'] ?? 0) . '%. Gate: ' . (string)($load_readiness['gate_status'] ?? 'collecting') . ', amostras: ' . (int)($load_readiness['samples'] ?? 0) . ', saúde das filas: ' . (int)($load_readiness['queue']['score'] ?? 0) . '%. '
                    : 'A tabela de métricas de desempenho ainda não está instalada.',
            ],
            [
                'key' => 'queue_modernization',
                'label' => 'Architecture & Queue Modernization',
                'status' => empty($queue_modernization['table_ready']) ? 'critical' : ((string)($queue_modernization['status'] ?? '') === 'critical' ? 'warning' : 'ok'),
                'detail' => !empty($queue_modernization['table_ready'])
                    ? 'Queue ' . (string)($queue_modernization['version'] ?? '') . ', modo ' . (string)($queue_modernization['mode'] ?? 'observe') . ', driver ' . (string)($queue_modernization['effective_driver'] ?? 'wp_cron') . ', pendentes: ' . (int)($queue_modernization['stats']['pending'] ?? 0) . ', dead letter: ' . (int)($queue_modernization['stats']['dead'] ?? 0) . '. Fallback WP-Cron disponível.'
                    : 'A tabela da fila moderna ainda não foi instalada.',
            ],
            [
                'key' => 'azure_cloud_foundation',
                'label' => 'FieldFlow Cloud Foundation',
                'status' => empty($azure_cloud['table_ready']) ? 'critical' : ((!empty($azure_cloud['connection']['sync_enabled']) && empty($azure_cloud['connection']['configured'])) || (int)($azure_cloud['stats']['dead'] ?? 0) > 0 ? 'warning' : 'ok'),
                'detail' => !empty($azure_cloud['table_ready'])
                    ? 'Cloud Foundation ' . (string)($azure_cloud['version'] ?? '') . ', sync ' . (!empty($azure_cloud['connection']['sync_enabled']) ? 'ativo' : 'desligado') . ', pendentes: ' . (int)($azure_cloud['stats']['pending'] ?? 0) . ', dead: ' . (int)($azure_cloud['stats']['dead'] ?? 0) . '. Tenant: ' . (string)($azure_cloud['tenant_id'] ?? '') . '.'
                    : 'A tabela persistente FieldFlow Cloud Sync ainda nao esta instalada.',
            ],
            [
                'key' => 'client_azure_delivery',
                'label' => 'Multi-Destination, Azure do Cliente',
                'status' => empty($client_azure['table_ready']) ? 'critical' : (((string)($client_azure['mode'] ?? 'shadow') === 'live' && empty($client_azure['relay']['configured'])) || (int)($client_azure['stats']['dead'] ?? 0) > 0 ? 'warning' : 'ok'),
                'detail' => !empty($client_azure['table_ready'])
                    ? 'Fase 5B ' . (string)($client_azure['version'] ?? '') . ', modo ' . (string)($client_azure['mode'] ?? 'shadow') . ', Relay TWT ' . (!empty($client_azure['relay']['configured']) ? 'configurado' : 'por configurar') . ', pendentes: ' . (int)($client_azure['stats']['pending'] ?? 0) . ', dead: ' . (int)($client_azure['stats']['dead'] ?? 0) . '.'
                    : 'A fila independente Client Azure ainda não está instalada.',
            ],
            [
                'key' => 'connector_foundation_5c',
                'label' => 'Multi-Destination, Connector Foundation 5C.1',
                'status' => ((string)($connector_5c['status'] ?? '') === 'ready' && (string)($connector_5c['mode'] ?? '') === 'shadow' && empty($connector_5c['network_delivery_enabled'])) ? 'ok' : 'warning',
                'detail' => 'Fase 5C.1 ' . (string)($connector_5c['version'] ?? '') . ', modo ' . (string)($connector_5c['mode'] ?? 'shadow') . ', contratos: ' . (int)($connector_5c['contracts_ready'] ?? 0) . '/' . (int)($connector_5c['contracts_total'] ?? 0) . ', destinos ativos: ' . (int)($connector_5c['active_destinations'] ?? 0) . ', delivery externo: ' . (!empty($connector_5c['network_delivery_enabled']) ? 'ATIVO' : 'bloqueado') . '.',
            ],
            [
                'key' => 'entra_cross_tenant_5c2',
                'label' => 'Microsoft Entra cross-tenant, 5C.2',
                'status' => ((string)($entra_5c2['mode'] ?? '') === 'shadow' && empty($entra_5c2['network_delivery_enabled']) && empty($entra_5c2['wordpress_client_secret_stored']) && (string)($entra_5c2['credential_model'] ?? '') === 'workload_identity_federation') ? (!empty($entra_5c2['configured']) ? 'ok' : 'warning') : 'critical',
                'detail' => 'Fase 5C.2 ' . (string)($entra_5c2['version'] ?? '') . ', modo ' . (string)($entra_5c2['mode'] ?? 'shadow') . ', broker ' . (!empty($entra_5c2['configured']) ? 'configurado' : 'por configurar') . ', credencial ' . (string)($entra_5c2['credential_model'] ?? 'indisponível') . ', destinos Entra preparados: ' . (int)($entra_5c2['entra_destinations_ready'] ?? 0) . '/' . (int)($entra_5c2['entra_destinations'] ?? 0) . ', client secrets no WordPress: 0, delivery 5C: bloqueado.',
            ],
            [
                'key' => 'entra_external_onboarding_5c2',
                'label' => 'Microsoft Entra, onboarding de tenants externos',
                'status' => !empty($entra_onboarding_5c2['control_plane_ready']) && empty($entra_onboarding_5c2['delivery_enabled']) ? 'ok' : 'warning',
                'detail' => 'Onboarding 5C.2 ' . (string)($entra_onboarding_5c2['version'] ?? '') . ', destinos configurados: ' . (int)($entra_onboarding_5c2['configured'] ?? 0) . '/' . (int)($entra_onboarding_5c2['total'] ?? 0) . ', consentimentos pendentes: ' . (int)($entra_onboarding_5c2['pending'] ?? 0) . ', identidades READY: ' . (int)($entra_onboarding_5c2['ready'] ?? 0) . ', a rever: ' . (int)($entra_onboarding_5c2['failed'] ?? 0) . ', delivery 5C: bloqueado.',
            ],
            [
                'key' => 'resource_authorization_5c3',
                'label' => 'Autorização de recursos, 5C.3',
                'status' => !empty($resource_authorization_5c3['control_plane_ready']) && (string)($resource_authorization_5c3['mode'] ?? '') === 'shadow' && !empty($resource_authorization_5c3['read_only_probe']) && empty($resource_authorization_5c3['delivery_enabled']) ? ((int)($resource_authorization_5c3['failed'] ?? 0) > 0 ? 'warning' : 'ok') : 'warning',
                'detail' => 'Fase 5C.3 ' . (string)($resource_authorization_5c3['version'] ?? '') . ', modo ' . (string)($resource_authorization_5c3['mode'] ?? 'shadow') . ', identidades READY: ' . (int)($resource_authorization_5c3['eligible'] ?? 0) . ', scopes compatíveis: ' . (int)($resource_authorization_5c3['scope_ready'] ?? 0) . ', recursos configurados: ' . (int)($resource_authorization_5c3['configured'] ?? 0) . ', recursos READY: ' . (int)($resource_authorization_5c3['authorized'] ?? 0) . ', a rever: ' . (int)($resource_authorization_5c3['failed'] ?? 0) . ', probe read-only, delivery bloqueado.',
            ],
            [
                'key' => 'connector_delivery_5c5',
                'label' => 'APIs externas e Webhooks, Delivery Foundation 5C.5',
                'status' => empty($connector_delivery_5c5['table_ready'])
                    ? 'critical'
                    : ((int)($connector_delivery_5c5['stats']['dead'] ?? 0) > 0 ? 'warning' : 'ok'),
                'detail' => !empty($connector_delivery_5c5['table_ready'])
                    ? 'Fase 5C.5 ' . (string)($connector_delivery_5c5['version'] ?? '') . ', modo ' . (string)($connector_delivery_5c5['mode'] ?? 'shadow') . ', kill switch ' . (!empty($connector_delivery_5c5['kill_switch']) ? 'ON' : 'OFF') . ', destinos elegíveis: ' . (int)($connector_delivery_5c5['eligible_destinations'] ?? 0) . ', shadow: ' . (int)($connector_delivery_5c5['stats']['shadow'] ?? 0) . ', pending: ' . (int)($connector_delivery_5c5['stats']['pending'] ?? 0) . ', dead: ' . (int)($connector_delivery_5c5['stats']['dead'] ?? 0) . ', transmissão de rede hard-locked na 2.2.370.'
                    : 'A fila dedicada da Fase 5C.5 ainda não está instalada.',
            ],
            [
                'key' => 'controlled_delivery_5c6',
                'label' => 'APIs externas e Webhooks, Controlled Delivery 5C.6',
                'status' => !empty($controlled_delivery_5c6['table_ready']) && !empty($controlled_delivery_5c6['certification_only']) && empty($controlled_delivery_5c6['operational_delivery_enabled']) && empty($controlled_delivery_5c6['arbitrary_target_urls_allowed']) ? (!empty($controlled_delivery_5c6['broker_configured']) ? 'ok' : 'warning') : 'critical',
                'detail' => 'Fase 5C.6 ' . (string)($controlled_delivery_5c6['version'] ?? '') . ', certification-only: ' . (!empty($controlled_delivery_5c6['certification_only']) ? 'SIM' : 'NÃO') . ', Broker ' . (!empty($controlled_delivery_5c6['broker_configured']) ? 'configurado' : 'por validar') . ', target arbitrário: ' . (!empty($controlled_delivery_5c6['arbitrary_target_urls_allowed']) ? 'ATIVO' : 'bloqueado') . ', receipts HMAC: ' . (!empty($controlled_delivery_5c6['signed_receipts_required']) ? 'obrigatórios' : 'não obrigatórios') . ', delivery operacional: bloqueado, receipts guardados: ' . (int)($controlled_delivery_5c6['receipt_count'] ?? 0) . '.',
            ],
            [
                'key' => 'safe_live_pilot_5c7',
                'label' => 'APIs externas e Webhooks, Safe Live Pilot 5C.7',
                'status' => !empty($safe_live_pilot_5c7['safe_projection_only']) && empty($safe_live_pilot_5c7['full_operational_payload_delivery_enabled']) && empty($safe_live_pilot_5c7['normal_5c5_dispatcher_enabled']) && !empty($safe_live_pilot_5c7['requires_shadow_mode']) && !empty($safe_live_pilot_5c7['requires_kill_switch']) ? ((int)($safe_live_pilot_5c7['armed_one_shots'] ?? 0) > 0 ? 'warning' : 'ok') : 'warning',
                'detail' => 'Fase 5C.7 ' . (string)($safe_live_pilot_5c7['version'] ?? '') . ', safe projection only, full payload bloqueado, targets preparados: ' . (int)($safe_live_pilot_5c7['prepared_targets'] ?? 0) . ', targets sintéticos certificados: ' . (int)($safe_live_pilot_5c7['synthetic_certified_targets'] ?? 0) . ', one-shots armados: ' . (int)($safe_live_pilot_5c7['armed_one_shots'] ?? 0) . ', dispatcher 5C.5 continua bloqueado.',
            ],
            [
                'key' => 'production_delivery_gate_5c8',
                'label' => 'APIs externas e Webhooks, Production Delivery Gate 5C.8',
                'status' => !empty($production_gate_5c8['safe_projection_only']) && empty($production_gate_5c8['full_operational_payload_delivery_enabled']) && empty($production_gate_5c8['general_production_activation_enabled']) && !empty($production_gate_5c8['requires_shadow_mode']) && !empty($production_gate_5c8['requires_kill_switch']) ? 'ok' : 'warning',
                'detail' => 'Fase 5C.8 ' . (string)($production_gate_5c8['version'] ?? '') . ', gates preparados: ' . (int)($production_gate_5c8['prepared_gates'] ?? 0) . ', targets certificados: ' . (int)($production_gate_5c8['target_certified_gates'] ?? 0) . ', production READY: ' . (int)($production_gate_5c8['production_ready_gates'] ?? 0) . ', safe projection only, full payload bloqueado, na Stable 2.2.376 a ativação operacional permanece desligada por defeito.',
            ],
            [
                'key' => 'credential_lifecycle_5d',
                'label' => 'Credential Lifecycle & Secret Governance 5D',
                'status' => !empty($credential_lifecycle_5d['key_vault_reference_only']) && empty($credential_lifecycle_5d['secret_values_stored_in_wordpress']) && empty($credential_lifecycle_5d['secret_values_returned_to_wordpress']) && !empty($credential_lifecycle_5d['managed_identity_resolution']) && !empty($credential_lifecycle_5d['rotation_detection']) && !empty($credential_lifecycle_5d['expiry_enforcement']) && !empty($credential_lifecycle_5d['revocation_guard']) && empty($credential_lifecycle_5d['general_production_activation_enabled']) && empty($credential_lifecycle_5d['operational_payload_delivery_enabled']) ? ((int)($credential_lifecycle_5d['blocked_credentials'] ?? 0) > 0 || (int)($credential_lifecycle_5d['revoked_credentials'] ?? 0) > 0 || (int)($credential_lifecycle_5d['rotation_detected_credentials'] ?? 0) > 0 ? 'warning' : 'ok') : 'critical',
                'detail' => 'Fase 5D ' . (string)($credential_lifecycle_5d['version'] ?? '') . ', pré-condição 5C.8 READY: ' . (int)($credential_lifecycle_5d['production_5c8_ready_gates'] ?? 0) . ', Key Vault-only, referências configuradas: ' . (int)($credential_lifecycle_5d['configured_credentials'] ?? 0) . ', certificadas: ' . (int)($credential_lifecycle_5d['certified_credentials'] ?? 0) . ', READY: ' . (int)($credential_lifecycle_5d['ready_credentials'] ?? 0) . ', warning: ' . (int)($credential_lifecycle_5d['warning_credentials'] ?? 0) . ', bloqueadas: ' . (int)($credential_lifecycle_5d['blocked_credentials'] ?? 0) . ', revogadas: ' . (int)($credential_lifecycle_5d['revoked_credentials'] ?? 0) . ', rotação detetada: ' . (int)($credential_lifecycle_5d['rotation_detected_credentials'] ?? 0) . '. Valores secretos nunca entram no WordPress. A 5E fecha governance/recovery e a ativação real continua separada da readiness.',
            ],
            [
                'key' => 'governance_recovery_5e',
                'label' => 'Governance, Recovery & Final Eligibility Gate 5E',
                'status' => !empty($governance_recovery_5e['ledger_ready']) && !empty($governance_recovery_5e['ledger_chain_valid']) && !empty($governance_recovery_5e['global_kill_switch']) && empty($governance_recovery_5e['production_enabled']) && empty($governance_recovery_5e['full_operational_payload_enabled'])
                    ? (!empty($governance_recovery_5e['production_eligible']) ? 'ok' : 'warning')
                    : 'critical',
                'detail' => 'Fase 5E ' . (string)($governance_recovery_5e['version'] ?? '') . ', ledger entries: ' . (int)($governance_recovery_5e['ledger_entries'] ?? 0) . ', hash chain: ' . (!empty($governance_recovery_5e['ledger_chain_valid']) ? 'VALIDA' : 'REVER') . ', dead letters: ' . (int)($governance_recovery_5e['dead_letter_count'] ?? 0) . ', stale locks: ' . (int)($governance_recovery_5e['stale_running_count'] ?? 0) . ', 5C.8 READY: ' . (int)($governance_recovery_5e['production_5c8_ready_gates'] ?? 0) . ', 5D READY: ' . (int)($governance_recovery_5e['credential_5d_ready'] ?? 0) . ', ledger/replay/recovery/final: ' . (!empty($governance_recovery_5e['ledger_certified']) ? 'OK' : 'PEND') . '/' . (!empty($governance_recovery_5e['replay_certified']) ? 'OK' : 'PEND') . '/' . (!empty($governance_recovery_5e['recovery_certified']) ? 'OK' : 'PEND') . '/' . (!empty($governance_recovery_5e['final_gate_certified']) ? 'OK' : 'PEND') . ', fingerprint: ' . (!empty($governance_recovery_5e['eligibility_fingerprint_match']) ? 'MATCH' : 'PEND/ALTERADO') . ', Production Eligible: ' . (!empty($governance_recovery_5e['production_eligible']) ? 'SIM' : 'NÃO') . ', Production Enabled: NÃO.',
            ],
            [
                'key' => 'phase5_stable_release',
                'label' => 'Fase 5 Stable Release',
                'status' => !empty($phase5_stable['stable_certified']) && !empty($phase5_stable['baseline_match']) && !empty($phase5_stable['production_eligible']) && empty($phase5_stable['production_enabled']) && empty($phase5_stable['full_operational_payload_enabled']) ? 'ok' : 'warning',
                'detail' => 'Release ' . (string)($phase5_stable['release'] ?? '') . ', matriz 5A a 5E: ' . (!empty($phase5_stable['matrix_ready']) ? 'READY' : 'REVER') . ', Stable: ' . (!empty($phase5_stable['stable_certified']) ? 'CERTIFICADA' : 'PENDENTE') . ', baseline: ' . (!empty($phase5_stable['baseline_match']) ? 'MATCH' : 'PENDENTE/ALTERADA') . ', Production Eligible: ' . (!empty($phase5_stable['production_eligible']) ? 'SIM' : 'NAO') . ', Production Enabled: NAO.',
            ],
            [
                'key' => 'client_onboarding_6a',
                'label' => 'Fase 6A, Client Onboarding & Provisioning',
                'status' => !empty($client_onboarding_6a['phase5_stable_certified']) && !empty($client_onboarding_6a['phase5_baseline_match']) && !empty($client_onboarding_6a['table_ready']) && !empty($client_onboarding_6a['connector_shadow_preserved']) && !empty($client_onboarding_6a['connector_kill_switch_on']) && empty($client_onboarding_6a['production_enabled']) && empty($client_onboarding_6a['full_operational_payload_enabled']) ? 'ok' : 'warning',
                'detail' => 'Fase 6A ' . (string)($client_onboarding_6a['version'] ?? '') . ', planos: ' . (int)($client_onboarding_6a['plans_total'] ?? 0) . ', staged: ' . (int)($client_onboarding_6a['plans_staged'] ?? 0) . ', Production Ready: ' . (int)($client_onboarding_6a['plans_production_ready'] ?? 0) . '. A baseline 2.2.376 permanece obrigatória e Production Enabled continua NÃO.',
            ],
            [
                'key' => 'client_onboarding_cert_6a',
                'label' => 'Fase 6A Stable Closure',
                'status' => !empty($client_onboarding_cert_6a['stable_certified']) && !empty($client_onboarding_cert_6a['baseline_match']) && !empty($client_onboarding_cert_6a['matrix_ready']) && empty($client_onboarding_cert_6a['production_enabled']) ? 'ok' : 'warning',
                'detail' => 'Release ' . (string)($client_onboarding_cert_6a['release'] ?? '') . ', matriz 6A: ' . (!empty($client_onboarding_cert_6a['matrix_ready']) ? 'READY' : 'REVER') . ', certificação: ' . (!empty($client_onboarding_cert_6a['stable_certified']) ? 'OK' : 'PENDENTE') . ', baseline: ' . (!empty($client_onboarding_cert_6a['baseline_match']) ? 'MATCH' : 'PENDENTE/ALTERADA') . ', Production Enabled: NÃO.',
            ],
            [
                'key' => 'license_tenants_6b',
                'label' => 'Fase 6B.1, Licensing & Tenant Foundation',
                'status' => !empty($license_tenants_6b['tables_ready']) && !empty($license_tenants_6b['phase6a_certified']) && empty($license_tenants_6b['raw_license_keys_stored']) && empty($license_tenants_6b['license_enforcement_enabled']) ? (!empty($license_tenants_6b['remote_ready']) ? 'ok' : 'warning') : 'warning',
                'detail' => 'Fase 6B.1 ' . (string)($license_tenants_6b['version'] ?? '') . ', tenants: ' . (int)($license_tenants_6b['tenants_total'] ?? 0) . ', licenças emitidas: ' . (int)($license_tenants_6b['tenants_issued'] ?? 0) . ', instalações registadas: ' . (int)($license_tenants_6b['installations_total'] ?? 0) . ', API remota: ' . (!empty($license_tenants_6b['remote_ready']) ? 'READY' : 'REVER') . ', foundation: ' . (!empty($license_tenants_6b['foundation_certified']) ? 'CERTIFICADA' : 'PENDENTE') . ', enforcement: OFF.',
            ],
            [
                'key' => 'license_lifecycle_6b2',
                'label' => 'Fase 6B.2, Activation, Binding & Heartbeat',
                'status' => !empty($license_lifecycle_6b2['phase6b1_certified']) && !empty($license_lifecycle_6b2['installation_identity_ready']) && empty($license_lifecycle_6b2['license_enforcement_enabled']) && empty($license_lifecycle_6b2['installation_binding_enforced']) && empty($license_lifecycle_6b2['remote_revocation_enabled']) ? (!empty($license_lifecycle_6b2['certified']) ? 'ok' : 'warning') : 'warning',
                'detail' => 'Fase 6B.2 ' . (string)($license_lifecycle_6b2['version'] ?? '') . ', Installation ID: ' . (!empty($license_lifecycle_6b2['installation_identity_ready']) ? 'READY' : 'REVER') . ', ativação remota: ' . (!empty($license_lifecycle_6b2['activation_verified']) ? 'VERIFICADA' : 'PENDENTE') . ', domínio: ' . (!empty($license_lifecycle_6b2['domain_binding_match']) ? 'MATCH' : 'PENDENTE') . ', heartbeat: ' . (!empty($license_lifecycle_6b2['heartbeat_accepted']) ? (!empty($license_lifecycle_6b2['heartbeat_recent']) ? 'OK' : 'STALE') : 'PENDENTE') . ', Azure ACK: ' . (!empty($license_lifecycle_6b2['remote_identity_acknowledged']) && !empty($license_lifecycle_6b2['remote_fingerprint_acknowledged']) && !empty($license_lifecycle_6b2['remote_heartbeat_recorded']) ? 'OK' : 'PENDENTE') . ', certificação: ' . (!empty($license_lifecycle_6b2['certified']) ? 'OK' : 'PENDENTE') . '. Enforcement, binding obrigatório e revogação remota permanecem OFF.',
            ],
            [
                'key' => 'license_governance_6b3',
                'label' => 'Fase 6B.3, Governance, Rotation & Limits',
                'status' => !empty($license_governance_6b3['phase6b2_certified']) && ($license_governance_6b3['enforcement_mode'] ?? '') === 'shadow' && empty($license_governance_6b3['runtime_blocking_enabled']) && empty($license_governance_6b3['remote_revocation_enforced']) ? (!empty($license_governance_6b3['certified']) ? 'ok' : 'warning') : 'warning',
                'detail' => 'Fase 6B.3 ' . (string)($license_governance_6b3['version'] ?? '') . ', governance API: ' . (!empty($license_governance_6b3['remote_manage_ready']) ? 'READY' : 'PENDENTE') . ', policy: ' . (!empty($license_governance_6b3['policy_fingerprint_match']) ? 'MATCH' : 'PENDENTE') . ', instalações: ' . (int)($license_governance_6b3['remote_active_activations'] ?? 0) . '/' . (int)($license_governance_6b3['max_installations'] ?? 0) . ', enforcement: SHADOW, grace model: ' . (int)($license_governance_6b3['grace_period_hours'] ?? 72) . 'h, certificação: ' . (!empty($license_governance_6b3['certified']) ? 'OK' : 'PENDENTE') . '. O runtime continua fail-open.',
            ],
            [
                'key' => 'architecture_runtime',
                'label' => 'Composição modular do runtime',
                'status' => !empty($architecture_runtime['registered']) && empty($architecture_runtime['missing_controllers']) ? 'ok' : 'critical',
                'detail' => !empty($architecture_runtime['registered'])
                    ? 'Composition root ' . (string)($architecture_runtime['version'] ?? '') . ' ativo. Controllers REST registados: ' . (int)($architecture_runtime['rest_controllers'] ?? 0) . ', em falta: ' . count((array)($architecture_runtime['missing_controllers'] ?? [])) . '.'
                    : 'O registo modular do runtime não está ativo.',
            ],
            [
                'key' => 'security_runtime',
                'label' => 'Segurança operacional',
                'status' => ((int)($security['critical'] ?? 0) > 0 || (int)($security['high'] ?? 0) > 0) ? 'warning' : 'ok',
                'detail' => 'Eventos abertos: ' . (int)($security['open'] ?? 0) . ', severidade alta: ' . (int)($security['high'] ?? 0) . ', críticos: ' . (int)($security['critical'] ?? 0) . '.',
            ],
            [
                'key' => 'outbound_http_guard',
                'label' => 'Outbound HTTP / SSRF Guard',
                'status' => !empty($outbound_http['https_only']) && !empty($outbound_http['private_networks_blocked']) && !empty($outbound_http['tls_verify_forced']) ? 'ok' : 'critical',
                'detail' => !empty($outbound_http['https_only'])
                    ? 'Guard ' . (string)($outbound_http['version'] ?? '') . ' ativo: HTTPS obrigatório, redes privadas/reservadas bloqueadas, TLS verificado, porta padrão ' . (int)($outbound_http['default_port'] ?? 443) . ' e redirects desativados por defeito.'
                    : 'O guard central de ligações externas não está disponível.',
            ],
            [
                'key' => 'secret_vault',
                'label' => 'Cofre de credenciais',
                'status' => empty($credential_vault['can_encrypt']) ? 'critical' : (((int)($credential_vault['legacy_fields'] ?? 0) > 0 || (int)($credential_vault['unreadable_fields'] ?? 0) > 0) ? 'warning' : 'ok'),
                'detail' => !empty($credential_vault['can_encrypt'])
                    ? 'Cifragem autenticada disponível. Credenciais protegidas: ' . (int)($credential_vault['encrypted_fields'] ?? 0) . ', legadas: ' . (int)($credential_vault['legacy_fields'] ?? 0) . ', ilegíveis: ' . (int)($credential_vault['unreadable_fields'] ?? 0) . '.'
                    : 'O servidor não disponibiliza Sodium nem AES-256-GCM. Novas credenciais não serão guardadas sem proteção forte.',
            ],
            [
                'key' => 'scope_index',
                'label' => 'Índice de permissões',
                'status' => !empty($scope_index['tables_ready']) && !empty($scope_index['enabled']) ? 'ok' : 'warning',
                'detail' => !empty($scope_index['tables_ready']) && !empty($scope_index['enabled'])
                    ? 'Índice normalizado pronto. Utilizadores indexados: ' . (int)($scope_index['indexed_users'] ?? 0) . ', relações ativas: ' . (int)($scope_index['memberships'] ?? 0) . ', geração: ' . (int)($scope_index['generation'] ?? 0) . '.'
                    : 'O resolvedor legado continua disponível. O índice normalizado está desativado ou ainda não foi instalado.',
            ],
            [
                'key' => 'backoffice_ui',
                'label' => 'Backoffice UI Consolidation',
                'status' => empty($backoffice_ui['assets_ready']) ? 'critical' : (!empty($backoffice_ui['enabled']) ? 'ok' : 'warning'),
                'detail' => empty($backoffice_ui['assets_ready'])
                    ? 'Os assets da consolidação visual 2.2.262 estão incompletos.'
                    : (!empty($backoffice_ui['enabled'])
                        ? 'Design system ativo em ' . (int)($backoffice_ui['managed_pages'] ?? 0) . ' superfícies administrativas, com auditoria visual e reversão imediata.'
                        : 'Modo legado ativo. A interface 2.2.260 continua operacional e pode receber novamente a consolidação sem migração.'),
            ],
            [
                'key' => 'media_registry',
                'label' => 'Protected Media Registry',
                'status' => !empty($media_registry['table_ready']) ? 'ok' : 'warning',
                'detail' => !empty($media_registry['table_ready'])
                    ? 'Registry ' . (string)($media_registry['version'] ?? '') . ' em modo ' . (string)($media_registry['mode'] ?? 'observe') . '. Ficheiros identificados: ' . (int)($media_registry['total'] ?? 0) . ', attachments: ' . (int)($media_registry['attachments'] ?? 0) . ', ficheiros diretos: ' . (int)($media_registry['direct_files'] ?? 0) . ', ligados a submissões: ' . (int)($media_registry['linked_submissions'] ?? 0) . '. Proteção continua sem enforcement.'
                    : 'A tabela aditiva do Media Registry ainda não está disponível. O fluxo legado de media mantém-se inalterado.',
            ],
            [
                'key' => 'protected_media_shadow',
                'label' => 'Protected Media Shadow Mode',
                'status' => !empty($media_shadow['table_ready']) ? 'ok' : 'warning',
                'detail' => !empty($media_shadow['table_ready'])
                    ? 'Shadow ' . (string)($media_shadow['version'] ?? '') . ' ativo sem enforcement. Últimos ' . (int)($media_shadow['days'] ?? 30) . ' dias: ' . (int)($media_shadow['events'] ?? 0) . ' observações, ' . (int)($media_shadow['allow'] ?? 0) . ' allow, ' . (int)($media_shadow['deny'] ?? 0) . ' deny, ' . (int)($media_shadow['unknown'] ?? 0) . ' unknown, ' . (int)($media_shadow['unique_media'] ?? 0) . ' ficheiros identificados.'
                    : 'A tabela aditiva de Shadow Mode ainda não está disponível. Não existe qualquer enforcement de media.',
            ],
            [
                'key' => 'mfa_session_policy',
                'label' => 'MFA e política de sessão',
                'status' => ((string)($session_policy['mfa_mode'] ?? 'observe') === 'enforce' && (string)($session_policy['session_mode'] ?? 'observe') === 'enforce')
                    ? (((int)($session_policy['mfa_enabled_users'] ?? 0) >= (int)($session_policy['privileged_users'] ?? 0) || ((string)($session_policy['mfa_grace_ends_at'] ?? '') !== '' && strtotime((string)$session_policy['mfa_grace_ends_at'].' UTC') >= time())) ? 'ok' : 'warning')
                    : 'warning',
                'detail' => 'Policy ' . (string)($session_policy['version'] ?? '') . '. MFA privilegiado: ' . (string)($session_policy['mfa_mode'] ?? 'observe') . '; sessões: ' . (string)($session_policy['session_mode'] ?? 'observe') . '; contas protegidas: ' . (int)($session_policy['mfa_enabled_users'] ?? 0) . '/' . (int)($session_policy['privileged_users'] ?? 0) . '. BO/Desktop idle ' . (int)($session_policy['wp_privileged_idle_minutes'] ?? 0) . ' min, máximo ' . (int)($session_policy['wp_privileged_absolute_hours'] ?? 0) . ' h. Mobile idle ' . (int)($session_policy['mobile_idle_days'] ?? 0) . ' dias, máximo ' . (int)($session_policy['mobile_absolute_days'] ?? 0) . ' dias.',
            ],
            [
                'key' => 'privacy_governance',
                'label' => 'Privacidade e retenção',
                'status' => empty($privacy_governance['tables_ready']) ? 'critical' : (!empty($privacy_governance['enabled']) ? 'ok' : 'warning'),
                'detail' => !empty($privacy_governance['tables_ready'])
                    ? 'Privacy Center ' . (string)($privacy_governance['version'] ?? '') . ' em modo ' . (string)($privacy_governance['mode'] ?? 'shadow') . '. Pedidos em aberto: ' . (int)($privacy_governance['open_requests'] ?? 0) . '. Retenção legal: ' . (!empty($privacy_governance['legal_hold']) ? 'ativa' : 'inativa') . '.'
                    : 'As tabelas de privacidade e retenção ainda não estão instaladas.',
            ],
            [
                'key' => 'billing_runtime',
                'label' => 'Faturação e pagamentos',
                'status' => !empty($billing['tables_ready']) ? ((!empty($billing['settings']['enabled']) && empty($billing['settings']['secret_configured']) && (($billing['settings']['mode'] ?? 'sandbox') === 'live' || ($billing['settings']['sandbox_driver'] ?? 'local') === 'stripe')) ? 'warning' : 'ok') : 'critical',
                'detail' => !empty($billing['tables_ready'])
                    ? 'Modo: ' . (string)($billing['settings']['mode'] ?? 'sandbox') . '. Subscrições ativas: ' . (int)($billing['metrics']['active'] ?? 0) . ', pagamentos pendentes: ' . (int)($billing['metrics']['past_due'] ?? 0) . '.'
                    : 'As tabelas do motor de faturação 2.2.224 ainda não estão instaladas.',
            ],
            [
                'key' => 'distribution',
                'label' => 'Distribuição',
                'status' => empty($backup_matches) ? 'ok' : 'warning',
                'detail' => empty($backup_matches) ? 'Sem ficheiros temporários ou backups no pacote.' : 'Ficheiros a remover: ' . implode(', ', array_unique($backup_matches)),
            ],
            [
                'key' => 'runtime_files',
                'label' => 'Bootstrap interno',
                'status' => empty($missing_runtime_files) ? 'ok' : 'critical',
                'detail' => empty($missing_runtime_files) ? 'Todos os ficheiros obrigatórios do runtime existem.' : 'Ficheiros em falta: ' . implode(', ', $missing_runtime_files),
            ],
            [
                'key' => 'release_files',
                'label' => 'Pacote comercial',
                'status' => empty($missing_release_files) ? 'ok' : 'warning',
                'detail' => empty($missing_release_files) ? 'Documentação mínima de release presente.' : 'Faltam ficheiros: ' . implode(', ', $missing_release_files),
            ],
        ];

        $summary = 'ok';
        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $summary = 'critical';
                break;
            }
            if ($check['status'] === 'warning') {
                $summary = 'warning';
            }
        }

        return [
            'summary' => $summary,
            'checks' => $checks,
            'counts' => [
                'clients' => SchemaRepository::tableRowCount('routespro_clients'),
                'projects' => SchemaRepository::tableRowCount('routespro_projects'),
                'routes' => SchemaRepository::tableRowCount('routespro_routes'),
                'locations' => SchemaRepository::tableRowCount('routespro_locations'),
                'forms' => SchemaRepository::tableRowCount('routespro_forms'),
                'submissions' => SchemaRepository::tableRowCount('routespro_form_submissions'),
                'system_logs' => class_exists('\RoutesPro\Repositories\SystemLogRepository') ? SystemLogRepository::count() : 0,
                'ai_logs' => class_exists('\RoutesPro\Repositories\AIUsageRepository') ? \RoutesPro\Repositories\AIUsageRepository::countSince('1970-01-01 00:00:00') : 0,
                'core_events' => (int)($integrity['events']['total'] ?? 0),
                'failed_events' => (int)($integrity['events']['failed'] ?? 0),
                'audit_24h' => (int)($integrity['audit_last_24h'] ?? 0),
                'last_integrity_issues' => (int)($integrity['last_issue_count'] ?? 0),
                'missing_runtime_files' => count($missing_runtime_files),
                'missing_release_files' => count($missing_release_files),
                'billing_active' => (int)($billing['metrics']['active'] ?? 0),
                'billing_past_due' => (int)($billing['metrics']['past_due'] ?? 0),
                'license_active' => LicenseManager::isActive() ? 1 : 0,
                'license_domain_match' => LicenseManager::isBoundToCurrentDomain() ? 1 : 0,
                'observability_open' => (int)($observability['open'] ?? 0),
                'observability_critical' => (int)($observability['critical_active'] ?? 0),
                'observability_critical_historical' => (int)($observability['critical_historical'] ?? 0),
                'security_open' => (int)($security['open'] ?? 0),
                'security_critical' => (int)($security['critical'] ?? 0),
                'private_storage_mb' => (float)($storage_governance['total_mb'] ?? 0),
                'private_storage_files' => (int)($storage_governance['total_files'] ?? 0),
                'mfa_privileged_users' => (int)($session_policy['privileged_users'] ?? 0),
                'mfa_enabled_users' => (int)($session_policy['mfa_enabled_users'] ?? 0),
                'scope_indexed_users' => (int)($scope_index['indexed_users'] ?? 0),
                'scope_memberships' => (int)($scope_index['memberships'] ?? 0),
                'vault_encrypted_fields' => (int)($credential_vault['encrypted_fields'] ?? 0),
                'vault_legacy_fields' => (int)($credential_vault['legacy_fields'] ?? 0),
                'privacy_open_requests' => (int)($privacy_governance['open_requests'] ?? 0),
                'ai_tracked_tokens' => (int)($ai_governance['tracked_tokens'] ?? 0),
                'performance_samples' => (int)($load_readiness['samples'] ?? 0),
                'queue_health_score' => (int)($load_readiness['queue']['score'] ?? 0),
                'queue_pending' => (int)($queue_modernization['stats']['pending'] ?? 0),
                'queue_dead' => (int)($queue_modernization['stats']['dead'] ?? 0),
                'cloud_sync_pending' => (int)($azure_cloud['stats']['pending'] ?? 0),
                'cloud_sync_dead' => (int)($azure_cloud['stats']['dead'] ?? 0),
                'cloud_sync_completed_24h' => (int)($azure_cloud['stats']['completed_24h'] ?? 0),
                'client_azure_pending' => (int)($client_azure['stats']['pending'] ?? 0),
                'client_azure_dead' => (int)($client_azure['stats']['dead'] ?? 0),
                'client_azure_relayed_24h' => (int)($client_azure['stats']['relayed_24h'] ?? 0),
                'connector_5c_contracts_ready' => (int)($connector_5c['contracts_ready'] ?? 0),
                'connector_5c_active_destinations' => (int)($connector_5c['active_destinations'] ?? 0),
                'connector_5c5_shadow' => (int)($connector_delivery_5c5['stats']['shadow'] ?? 0),
                'connector_5c5_pending' => (int)($connector_delivery_5c5['stats']['pending'] ?? 0),
                'connector_5c5_dead' => (int)($connector_delivery_5c5['stats']['dead'] ?? 0),
                'schema_checked_tables' => (int)($schema_integrity['counts']['checked'] ?? 0),
                'schema_warning_tables' => (int)($schema_integrity['counts']['warning'] ?? 0),
                'schema_critical_tables' => (int)($schema_integrity['counts']['critical'] ?? 0),
                'migration_failed' => (int)($migration_ledger['failed'] ?? 0),
                'migration_batches_pending' => (int)($migration_batches['pending'] ?? 0),
                'release_gate_score' => (int)($release_gate['score'] ?? 0),
            ],
            'constants' => self::constantSources(),
            'integrity' => $integrity,
            'observability' => $observability,
            'offline' => $offline, // 9DI: reuse on System Health page, no second device-table scan.
            'security' => $security,
            'rest_scope_guard' => $rest_scope_guard,
            'storage_governance' => $storage_governance,
            'outbound_http' => $outbound_http,
            'session_policy' => $session_policy,
            'credential_vault' => $credential_vault,
            'scope_index' => $scope_index,
            'backoffice_ui' => $backoffice_ui,
            'privacy_governance' => $privacy_governance,
            'ai_governance' => $ai_governance,
            'load_readiness' => $load_readiness,
            'queue_modernization' => $queue_modernization,
            'azure_cloud' => $azure_cloud,
            'client_azure' => $client_azure,
            'connector_5c' => $connector_5c,
            'connector_delivery_5c5' => $connector_delivery_5c5,
            'controlled_delivery_5c6' => $controlled_delivery_5c6,
            'architecture_runtime' => $architecture_runtime,
            'schema_integrity' => $schema_integrity,
            'migration_ledger' => $migration_ledger,
            'migration_batches' => $migration_batches,
            'release_gate' => $release_gate,
        ];
    }

    private static function mapsStatus(array $opts): string {
        $provider = (string) ($opts['maps_provider'] ?? 'leaflet');
        if ($provider === 'leaflet') return 'ok';
        if ($provider === 'google') return Config::providerReady('google_maps', $opts) ? 'ok' : 'warning';
        if ($provider === 'azure') return Config::providerReady('azure_maps', $opts) ? 'ok' : 'warning';
        return 'warning';
    }

    private static function aiStatus(array $opts): string {
        if (empty($opts['ai_enabled'])) return 'warning';
        $provider = Config::aiProviderForModule('general', $opts);
        return $provider !== 'none' && Config::aiProviderReady($provider, $opts) ? 'ok' : 'warning';
    }

    private static function constantSources(): array {
        $out = [];
        foreach (Config::secretConstants() as $key => $constant) {
            $out[$key] = defined($constant) ? 'wp-config.php' : 'base de dados';
        }
        return $out;
    }
}
