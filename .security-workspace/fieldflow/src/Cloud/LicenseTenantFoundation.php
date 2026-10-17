<?php
namespace RoutesPro\Cloud;

use RoutesPro\SaaS\PlanRepository;
use RoutesPro\Support\RemoteLicenseClient;
use RoutesPro\Support\LicenseManager;
use RoutesPro\Support\ProductionManifest;

if (!defined('ABSPATH')) exit;

/**
 * Phase 6B.1, Licensing & Tenant Management foundation.
 *
 * Establishes the master-side tenant registry and safe remote key issuance.
 * It does not enforce installation blocking, remote revocation or production
 * activation. Raw license keys are displayed once and never persisted here.
 */
final class LicenseTenantFoundation {
    public const VERSION = '1.0.2';
    public const PHASE = '6B.1';
    public const STATE_OPTION = 'fieldflow_phase6b1_foundation_state';
    public const REMOTE_PROBE_OPTION = 'fieldflow_phase6b1_remote_probe_state';
    public const LICENSE_ENFORCEMENT_ENABLED = false;
    public const INSTALLATION_BINDING_ENFORCED = false;
    public const REMOTE_REVOCATION_ENABLED = false;
    private static array $oneTimeKeys = [];
    private static ?array $healthCache = null;

    public static function tenantsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_license_tenants';
    }

    public static function installationsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_license_installations';
    }

    public static function tablesReady(): bool {
        global $wpdb;
        foreach ([self::tenantsTable(), self::installationsTable()] as $table) {
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return false;
        }
        return true;
    }

    public static function health(): array {
        if (self::$healthCache !== null) return self::$healthCache;
        $summary = self::summary();
        $phase6a = class_exists(ClientOnboardingCertification::class) ? ClientOnboardingCertification::health() : [];
        $state = self::state();
        $remoteProbe = self::remoteProbeState();
        $baseHost = strtolower((string)(wp_parse_url((string)RemoteLicenseClient::baseUrl(), PHP_URL_HOST) ?: ''));
        $remoteProbeHost = strtolower((string)($remoteProbe['host'] ?? ''));
        $remoteProbeMatches = $baseHost !== '' && $remoteProbeHost !== '' && hash_equals($baseHost, $remoteProbeHost);
        $remoteConnectivityReady = !empty($remoteProbe['ok']) && $remoteProbeMatches;
        $releaseManifest = ProductionManifest::verify();
        $releaseManifestOk = !empty($releaseManifest['ok']);
        $releaseVersionConsistent = self::releaseVersionConsistent();
        $fingerprint = self::foundationFingerprint();
        $certified = strtolower((string)($state['fingerprint_sha256'] ?? ''));
        $match = preg_match('/^[a-f0-9]{64}$/', $certified) === 1 && hash_equals($certified, $fingerprint);
        // 9BR: keep the historical Foundation certification independent from the
        // currently installed release integrity. Release manifest/version are
        // still mandatory revalidation checks, but must not cascade 6B.1 into
        // an apparently uncertified state after every plugin update.
        $releaseIntegrityReady = $releaseManifestOk && $releaseVersionConsistent;
        $foundationCertified = !empty($state['certified']) && $match && $remoteConnectivityReady;

        return self::$healthCache = [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'status'=>$foundationCertified ? ($releaseIntegrityReady ? 'ready' : 'release_attention') : (self::tablesReady() ? 'foundation' : 'attention'),
            'phase6a_certified'=>!empty($phase6a['stable_certified']),
            'phase6a_baseline_match'=>!empty($phase6a['baseline_match']),
            'tables_ready'=>self::tablesReady(),
            'saas_tables_ready'=>PlanRepository::tablesReady(),
            'remote_api_base_configured'=>trim((string)RemoteLicenseClient::baseUrl()) !== '',
            'remote_shared_secret_configured'=>trim((string)RemoteLicenseClient::sharedSecret()) !== '',
            'remote_admin_secret_configured'=>trim((string)RemoteLicenseClient::adminSecret()) !== '',
            'remote_connectivity_ready'=>$remoteConnectivityReady,
            'remote_probe_matches_current_host'=>$remoteProbeMatches,
            'remote_probe_checked_at'=>(string)($remoteProbe['checked_at'] ?? ''),
            'remote_probe_host'=>$remoteProbeHost,
            'remote_probe_http_code'=>(int)($remoteProbe['http_code'] ?? 0),
            'remote_probe_dns_resolved'=>!empty($remoteProbe['dns_resolved']),
            'remote_probe_dns_public'=>!empty($remoteProbe['dns_public']),
            'remote_probe_wordpress_url_validation'=>!empty($remoteProbe['wordpress_url_validation']),
            'remote_probe_trusted_wordpress_bypass'=>!empty($remoteProbe['trusted_wordpress_bypass']),
            'remote_probe_http_reachable'=>!empty($remoteProbe['http_reachable']),
            'remote_probe_route_reachable'=>!empty($remoteProbe['route_reachable']),
            'remote_probe_shared_secret_accepted'=>!empty($remoteProbe['shared_secret_accepted']),
            'remote_probe_message'=>(string)($remoteProbe['message'] ?? ''),
            'remote_ready'=>RemoteLicenseClient::isReady() && trim((string)RemoteLicenseClient::adminSecret()) !== '' && $remoteConnectivityReady,
            'release_manifest_ok'=>$releaseManifestOk,
            'release_manifest_version'=>(string)($releaseManifest['manifest_version'] ?? ''),
            'release_version_consistent'=>$releaseVersionConsistent,
            'release_integrity_ready'=>$releaseIntegrityReady,
            'raw_license_keys_stored'=>false,
            'license_enforcement_enabled'=>self::LICENSE_ENFORCEMENT_ENABLED,
            'installation_binding_enforced'=>self::INSTALLATION_BINDING_ENFORCED,
            'remote_revocation_enabled'=>self::REMOTE_REVOCATION_ENABLED,
            'foundation_certified'=>$foundationCertified,
            'certification_state_present'=>!empty($state['certified']) || preg_match('/^[a-f0-9]{64}$/', $certified) === 1,
            'certification_requires_revalidation'=>!empty($state['certified']) && (!$match || !$remoteConnectivityReady),
            'foundation_fingerprint_match'=>$match,
            'foundation_fingerprint_sha256'=>$fingerprint,
            'certified_fingerprint_sha256'=>$certified,
            'certified_at'=>(string)($state['certified_at'] ?? ''),
            'tenants_total'=>(int)$summary['total'],
            'tenants_draft'=>(int)$summary['draft'],
            'tenants_issued'=>(int)$summary['issued'],
            'tenants_suspended'=>(int)$summary['suspended'],
            'installations_total'=>(int)$summary['installations_total'],
            'installations_active'=>(int)$summary['installations_active'],
        ];
    }

    public static function resetHealthCache(): void {
        self::$healthCache = null;
    }

    public static function localProbe(): array {
        $health = self::health();
        $checks = [
            'phase6a_stable_certified'=>!empty($health['phase6a_certified']),
            'phase6a_baseline_match'=>!empty($health['phase6a_baseline_match']),
            'license_tenant_tables_ready'=>!empty($health['tables_ready']),
            'saas_plan_tables_ready'=>!empty($health['saas_tables_ready']),
            'remote_license_api_base_configured'=>!empty($health['remote_api_base_configured']),
            'remote_shared_secret_configured'=>!empty($health['remote_shared_secret_configured']),
            'remote_admin_secret_configured'=>!empty($health['remote_admin_secret_configured']),
            'remote_connectivity_probe_ok'=>!empty($health['remote_connectivity_ready']),
            'remote_probe_host_matches'=>!empty($health['remote_probe_matches_current_host']),
            'remote_probe_https'=>!empty($health['remote_connectivity_ready']) || !empty(self::remoteProbeState()['https']),
            'remote_probe_outbound_policy_ok'=>!empty($health['remote_probe_wordpress_url_validation']) || !empty($health['remote_probe_trusted_wordpress_bypass']),
            'remote_probe_http_reachable'=>!empty($health['remote_probe_http_reachable']),
            'remote_probe_route_reachable'=>!empty($health['remote_probe_route_reachable']),
            'remote_probe_shared_secret_accepted'=>!empty($health['remote_probe_shared_secret_accepted']),
            'release_manifest_integrity'=>!empty($health['release_manifest_ok']),
            'release_version_consistency'=>!empty($health['release_version_consistent']),
            'raw_license_keys_not_stored'=>empty($health['raw_license_keys_stored']),
            'license_enforcement_off'=>empty($health['license_enforcement_enabled']),
            'installation_binding_not_enforced'=>empty($health['installation_binding_enforced']),
            'remote_revocation_not_enabled'=>empty($health['remote_revocation_enabled']),
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    public static function probeRemoteApi(): array {
        if (!current_user_can('routespro_manage')) {
            return ['ok'=>false,'reason'=>'permission_denied','message'=>'Sem permissões para testar a API remota de licenciamento.'];
        }
        $probe = RemoteLicenseClient::connectivityProbe();
        $safe = [
            'ok'=>!empty($probe['ok']),
            'host'=>sanitize_text_field((string)($probe['host'] ?? '')),
            'https'=>!empty($probe['https']),
            'dns_resolved'=>!empty($probe['dns_resolved']),
            'dns_public'=>!empty($probe['dns_public']),
            'wordpress_url_validation'=>!empty($probe['wordpress_url_validation']),
            'trusted_wordpress_bypass'=>!empty($probe['trusted_wordpress_bypass']),
            'http_reachable'=>!empty($probe['http_reachable']),
            'route_reachable'=>!empty($probe['route_reachable']),
            'shared_secret_accepted'=>!empty($probe['shared_secret_accepted']),
            'http_code'=>(int)($probe['http_code'] ?? 0),
            'message'=>sanitize_text_field((string)($probe['message'] ?? '')),
            'checked_at'=>current_time('mysql', true),
        ];
        update_option(self::REMOTE_PROBE_OPTION, $safe, false);
        self::resetHealthCache();
        self::audit('phase6b1.remote_api.probed', 0, [
            'host'=>$safe['host'],
            'ok'=>$safe['ok'],
            'https'=>$safe['https'],
            'dns_resolved'=>$safe['dns_resolved'],
            'dns_public'=>$safe['dns_public'],
            'wordpress_url_validation'=>$safe['wordpress_url_validation'],
            'trusted_wordpress_bypass'=>$safe['trusted_wordpress_bypass'],
            'http_reachable'=>$safe['http_reachable'],
            'route_reachable'=>$safe['route_reachable'],
            'shared_secret_accepted'=>$safe['shared_secret_accepted'],
            'http_code'=>$safe['http_code'],
        ], 'Probe read-only da API de licenciamento 6B.1. Nenhuma licença foi criada ou alterada.');
        return $safe;
    }

    public static function certifyFoundation(int $actorId = 0): array {
        ProductionManifest::verify(true);
        self::resetHealthCache();
        $probe = self::localProbe();
        if (empty($probe['ok'])) return ['ok'=>false,'reason'=>'phase6b1_preconditions_failed','checks'=>$probe['checks']];
        $fingerprint = self::foundationFingerprint();
        update_option(self::STATE_OPTION, [
            'certified'=>true,
            'certified_at'=>current_time('mysql', true),
            'certified_by'=>max(0, $actorId),
            'fingerprint_sha256'=>$fingerprint,
            'license_enforcement_enabled'=>false,
            'remote_revocation_enabled'=>false,
        ], false);
        self::resetHealthCache();
        self::audit('phase6b1.foundation.certified', 0, [
            'fingerprint_sha256'=>$fingerprint,
            'raw_license_keys_stored'=>false,
            'license_enforcement_enabled'=>false,
            'remote_revocation_enabled'=>false,
        ], 'Phase 6B.1 licensing and tenant foundation certified. Enforcement remains disabled.');
        return ['ok'=>true,'reason'=>'phase6b1_foundation_certified','checks'=>$probe['checks'],'health'=>self::health()];
    }

    public static function resetFoundation(): array {
        $before = self::state();
        update_option(self::STATE_OPTION, [
            'certified'=>false,
            'reset_at'=>current_time('mysql', true),
            'reset_by'=>get_current_user_id(),
            'previous_fingerprint_sha256'=>(string)($before['fingerprint_sha256'] ?? ''),
            'license_enforcement_enabled'=>false,
        ], false);
        self::resetHealthCache();
        self::audit('phase6b1.foundation.reset', 0, [
            'previous_fingerprint_sha256'=>(string)($before['fingerprint_sha256'] ?? ''),
            'license_enforcement_enabled'=>false,
        ], 'Phase 6B.1 foundation certification reset. No license enforcement was enabled.');
        return ['ok'=>true,'reason'=>'phase6b1_foundation_reset'];
    }

    public static function createTenant(array $input) {
        global $wpdb;
        if (!current_user_can('routespro_manage')) return new \WP_Error('fieldflow_6b_permission_denied', 'Sem permissões para gerir tenants de licenciamento.');
        if (!self::tablesReady()) return new \WP_Error('fieldflow_6b_tables_missing', 'As tabelas de licenciamento 6B.1 ainda não estão disponíveis.');
        $foundation = self::health();
        if (empty($foundation['foundation_certified'])) {
            return new \WP_Error('fieldflow_6b_foundation_not_certified', 'Certifica primeiro a Foundation 6B.1 antes de criar tenants de licenciamento.');
        }
        $phase6a = ClientOnboardingCertification::health();
        if (empty($phase6a['stable_certified']) || empty($phase6a['baseline_match'])) {
            return new \WP_Error('fieldflow_6b_phase6a_not_certified', 'Fecha e certifica formalmente a Fase 6A antes de criar tenants 6B.');
        }

        $clientId = absint($input['client_id'] ?? 0);
        $client = self::client($clientId);
        if (!$client) return new \WP_Error('fieldflow_6b_client_invalid', 'Seleciona um cliente válido.');
        $existing = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . self::tenantsTable() . ' WHERE client_id=%d LIMIT 1', $clientId));
        if ($existing) return new \WP_Error('fieldflow_6b_tenant_exists', 'Este cliente já tem um tenant de licenciamento 6B.');
        $planSlug = sanitize_key((string)($input['plan_slug'] ?? 'enterprise'));
        $plan = PlanRepository::planBySlug($planSlug);
        if (!$plan) return new \WP_Error('fieldflow_6b_plan_invalid', 'Seleciona um plano SaaS válido.');
        $maxInstallations = max(1, min(99, absint($input['max_installations'] ?? 1)));
        $expiresAt = self::normaliseExpiry((string)($input['expires_at'] ?? ''));
        if (is_wp_error($expiresAt)) return $expiresAt;
        $customerCode = self::customerCode((string)($client['name'] ?? ''), $clientId);
        $tenantUuid = wp_generate_uuid4();
        $binding = self::bindingFingerprint([
            'tenant_uuid'=>$tenantUuid,
            'client_id'=>$clientId,
            'plan_slug'=>$planSlug,
            'customer_code'=>$customerCode,
            'max_installations'=>$maxInstallations,
            'expires_at'=>$expiresAt,
        ]);
        $now = current_time('mysql', true);
        $ok = $wpdb->insert(self::tenantsTable(), [
            'tenant_uuid'=>$tenantUuid,
            'client_id'=>$clientId,
            'plan_slug'=>$planSlug,
            'customer_code'=>$customerCode,
            'status'=>'draft',
            'max_installations'=>$maxInstallations,
            'expires_at'=>$expiresAt !== '' ? $expiresAt : null,
            'remote_license_id'=>null,
            'key_fingerprint_sha256'=>null,
            'masked_key'=>null,
            'binding_fingerprint_sha256'=>$binding,
            'capabilities_json'=>wp_json_encode((array)($plan['capabilities'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'limits_json'=>wp_json_encode((array)($plan['limits'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'notes'=>sanitize_textarea_field((string)($input['notes'] ?? '')) ?: null,
            'created_by'=>get_current_user_id() ?: null,
            'updated_by'=>get_current_user_id() ?: null,
            'issued_at'=>null,
            'revoked_at'=>null,
            'last_sync_at'=>null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        if ($ok === false) return new \WP_Error('fieldflow_6b_tenant_insert_failed', 'Não foi possível criar o tenant de licenciamento.');
        $id = (int)$wpdb->insert_id;
        self::resetHealthCache();
        self::audit('phase6b1.tenant.created', $id, [
            'tenant_uuid'=>$tenantUuid,
            'client_id'=>$clientId,
            'plan_slug'=>$planSlug,
            'max_installations'=>$maxInstallations,
            'binding_fingerprint_sha256'=>$binding,
        ], 'Tenant de licenciamento criado em draft, sem chave emitida e sem enforcement.');
        return $id;
    }

    public static function issueRemoteLicense(int $tenantId) {
        global $wpdb;
        if (!current_user_can('routespro_manage')) return new \WP_Error('fieldflow_6b_permission_denied', 'Sem permissões para emitir licenças.');
        $foundation = self::health();
        if (empty($foundation['foundation_certified'])) {
            return new \WP_Error('fieldflow_6b_foundation_not_certified', 'A Foundation 6B.1 tem de estar certificada antes de emitir uma chave remota.');
        }
        $tenant = self::getTenant($tenantId);
        if (!$tenant) return new \WP_Error('fieldflow_6b_tenant_missing', 'Tenant de licenciamento não encontrado.');
        if ((string)($tenant['status'] ?? '') === 'suspended') return new \WP_Error('fieldflow_6b_tenant_suspended', 'Este tenant está suspenso localmente.');
        if (trim((string)RemoteLicenseClient::adminSecret()) === '' || !RemoteLicenseClient::isReady()) {
            return new \WP_Error('fieldflow_6b_remote_not_ready', 'A API remota de licenciamento ainda não tem shared secret e admin secret configurados.');
        }
        if (empty($foundation['remote_connectivity_ready'])) {
            return new \WP_Error('fieldflow_6b_remote_probe_required', 'Testa novamente a API remota 6B.1 e certifica a Foundation antes de emitir a chave.');
        }

        $response = RemoteLicenseClient::post('/license/generate', [
            'plan'=>(string)$tenant['plan_slug'],
            'customer'=>(string)$tenant['customer_code'],
            'customer_code'=>(string)$tenant['customer_code'],
            'max_activations'=>(int)$tenant['max_installations'],
            'expires_at'=>(string)($tenant['expires_at'] ?? ''),
            'tenant_uuid'=>(string)$tenant['tenant_uuid'],
            'client_id'=>(int)$tenant['client_id'],
            'capabilities'=>is_array($tenant['capabilities'] ?? null) ? $tenant['capabilities'] : [],
            'limits'=>is_array($tenant['limits'] ?? null) ? $tenant['limits'] : [],
            'site_context'=>RemoteLicenseClient::siteContext(),
        ], true);
        if (empty($response['success'])) {
            return new \WP_Error('fieldflow_6b_remote_issue_failed', sanitize_text_field((string)($response['message'] ?? 'A API remota não conseguiu emitir a licença.')));
        }

        $license = is_array($response['license'] ?? null) ? $response['license'] : [];
        $key = trim((string)($response['key'] ?? ($response['license_key'] ?? ($license['key'] ?? ($license['license_key'] ?? '')))));
        if ($key === '') return new \WP_Error('fieldflow_6b_remote_key_missing', 'A API confirmou a emissão, mas não devolveu a chave de ativação.');
        $remoteId = sanitize_text_field((string)($response['license_id'] ?? ($license['license_id'] ?? ($license['id'] ?? ''))));
        $keyFingerprint = hash('sha256', $key);
        $masked = self::maskKey($key);
        $now = current_time('mysql', true);
        $wpdb->update(self::tenantsTable(), [
            'status'=>'issued',
            'remote_license_id'=>$remoteId !== '' ? $remoteId : null,
            'key_fingerprint_sha256'=>$keyFingerprint,
            'masked_key'=>$masked,
            'issued_at'=>$now,
            'last_sync_at'=>$now,
            'updated_by'=>get_current_user_id() ?: null,
            'updated_at'=>$now,
        ], ['id'=>$tenantId]);

        self::resetHealthCache();
        $runtimeIndex = self::oneTimeKeyRuntimeIndex($tenantId, get_current_user_id());
        self::$oneTimeKeys[$runtimeIndex] = $key;
        self::audit('phase6b1.license.issued', $tenantId, [
            'tenant_uuid'=>(string)$tenant['tenant_uuid'],
            'client_id'=>(int)$tenant['client_id'],
            'plan_slug'=>(string)$tenant['plan_slug'],
            'max_installations'=>(int)$tenant['max_installations'],
            'remote_license_id'=>$remoteId,
            'key_fingerprint_sha256'=>$keyFingerprint,
            'masked_key'=>$masked,
            'raw_key_stored'=>false,
        ], 'Chave remota emitida. O valor bruto fica apenas em memoria durante o request de apresentacao unica e nao e persistido.');
        return ['ok'=>true,'tenant_id'=>$tenantId,'masked_key'=>$masked,'remote_license_id'=>$remoteId];
    }

    public static function consumeOneTimeKey(int $tenantId): string {
        $runtimeIndex = self::oneTimeKeyRuntimeIndex($tenantId, get_current_user_id());
        $key = (string)(self::$oneTimeKeys[$runtimeIndex] ?? '');
        unset(self::$oneTimeKeys[$runtimeIndex]);
        return $key;
    }

    public static function reconcileCurrentInstallation(array $observation = []): array {
        global $wpdb;
        if (!self::tablesReady()) return ['ok'=>false,'reason'=>'tables_missing'];
        if (!class_exists(LicenseInstallationLifecycle::class)) return ['ok'=>false,'reason'=>'lifecycle_missing'];
        $tenant = self::tenantForCurrentLicense();
        if (!$tenant) return ['ok'=>false,'reason'=>'tenant_not_local'];
        $identity = LicenseInstallationLifecycle::identity();
        $context = LicenseInstallationLifecycle::context();
        $uuid = sanitize_text_field((string)($identity['installation_uuid'] ?? ''));
        $fingerprint = sanitize_text_field((string)($identity['fingerprint_sha256'] ?? ''));
        if ($uuid === '' || $fingerprint === '') return ['ok'=>false,'reason'=>'identity_missing'];
        $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::installationsTable() . ' WHERE installation_uuid=%s OR (tenant_id=%d AND domain_fingerprint=%s) ORDER BY id ASC LIMIT 1', $uuid, (int)$tenant['id'], $fingerprint), ARRAY_A);
        if (is_array($existing) && (int)($existing['tenant_id'] ?? 0) !== (int)$tenant['id']) {
            self::audit('phase6b2.installation.identity_conflict', (int)$tenant['id'], ['installation_uuid_sha256'=>hash('sha256', $uuid)], 'Installation identity already belongs to another local tenant. No reassignment performed.');
            return ['ok'=>false,'reason'=>'identity_conflict'];
        }
        $now = current_time('mysql', true);
        $accepted = !empty($observation['accepted']);
        $activationId = sanitize_text_field((string)($observation['remote_activation_id'] ?? LicenseManager::get('remote_activation_id', '')));
        $meta = [
            'phase'=>'6B.2',
            'binding_mode'=>'observed_not_enforced',
            'remote_license_id'=>sanitize_text_field((string)($observation['remote_license_id'] ?? LicenseManager::get('remote_license_id', ''))),
            'remote_status'=>sanitize_key((string)($observation['remote_status'] ?? '')),
            'heartbeat_http_code'=>(int)($observation['http_code'] ?? 0),
            'wp_version'=>sanitize_text_field((string)($context['wp_version'] ?? '')),
            'php_version'=>sanitize_text_field((string)($context['php_version'] ?? '')),
            'environment_hash'=>sanitize_text_field((string)($context['environment_hash'] ?? '')),
            'raw_license_key_stored'=>false,
        ];
        $row = [
            'installation_uuid'=>$uuid,
            'tenant_id'=>(int)$tenant['id'],
            'domain'=>substr(LicenseManager::currentDomain(), 0, 191),
            'domain_fingerprint'=>substr($fingerprint, 0, 128),
            'site_url'=>substr(esc_url_raw(home_url('/')), 0, 500),
            'activation_id'=>$activationId !== '' ? substr($activationId, 0, 128) : null,
            'status'=>'active',
            'plugin_version'=>defined('ROUTESPRO_VERSION') ? substr((string)ROUTESPRO_VERSION, 0, 32) : null,
            'last_seen_at'=>$accepted ? $now : (is_array($existing) ? ($existing['last_seen_at'] ?? null) : null),
            'activated_at'=>is_array($existing) && !empty($existing['activated_at']) ? $existing['activated_at'] : $now,
            'revoked_at'=>null,
            'meta_json'=>wp_json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at'=>$now,
        ];
        if (is_array($existing)) {
            $ok = $wpdb->update(self::installationsTable(), $row, ['id'=>(int)$existing['id']]);
            $id = (int)$existing['id'];
        } else {
            $row['created_at'] = $now;
            $ok = $wpdb->insert(self::installationsTable(), $row);
            $id = (int)$wpdb->insert_id;
        }
        if ($ok === false) return ['ok'=>false,'reason'=>'db_write_failed'];
        self::resetHealthCache();
        self::audit('phase6b2.installation.reconciled', (int)$tenant['id'], ['installation_id'=>$id,'status'=>'active','heartbeat_accepted'=>$accepted], 'Current installation reconciled into the local 6B registry. Binding remains observational only.');
        return ['ok'=>true,'installation_id'=>$id,'tenant_id'=>(int)$tenant['id']];
    }

    public static function markCurrentInstallationInactive(array $observation = []): array {
        global $wpdb;
        if (!self::tablesReady() || !class_exists(LicenseInstallationLifecycle::class)) return ['ok'=>false,'reason'=>'not_ready'];
        $identity = LicenseInstallationLifecycle::identity();
        $uuid = sanitize_text_field((string)($identity['installation_uuid'] ?? ''));
        $activationId = sanitize_text_field((string)($observation['activation_id'] ?? ''));
        if ($uuid === '' && $activationId === '') return ['ok'=>false,'reason'=>'identity_missing'];
        $whereSql = $uuid !== '' ? 'installation_uuid=%s' : 'activation_id=%s';
        $whereValue = $uuid !== '' ? $uuid : $activationId;
        $row = $wpdb->get_row($wpdb->prepare('SELECT id,tenant_id FROM ' . self::installationsTable() . ' WHERE ' . $whereSql . ' ORDER BY id ASC LIMIT 1', $whereValue), ARRAY_A);
        if (!is_array($row)) return ['ok'=>false,'reason'=>'installation_not_local'];
        $now = current_time('mysql', true);
        $wpdb->update(self::installationsTable(), ['status'=>'inactive','updated_at'=>$now], ['id'=>(int)$row['id']]);
        self::resetHealthCache();
        self::audit('phase6b2.installation.deactivated', (int)$row['tenant_id'], ['installation_id'=>(int)$row['id']], 'Installation marked inactive after explicit remote deactivation. No automatic revocation applied.');
        return ['ok'=>true,'installation_id'=>(int)$row['id']];
    }

    public static function listInstallations(int $tenantId = 0): array {
        global $wpdb;
        if (!self::tablesReady()) return [];
        $sql = 'SELECT i.*,t.tenant_uuid,t.plan_slug,t.client_id,c.name AS client_name FROM ' . self::installationsTable() . ' i LEFT JOIN ' . self::tenantsTable() . ' t ON t.id=i.tenant_id LEFT JOIN ' . $wpdb->prefix . 'routespro_clients c ON c.id=t.client_id';
        if ($tenantId > 0) {
            $sql .= $wpdb->prepare(' WHERE i.tenant_id=%d', $tenantId);
        }
        $sql .= ' ORDER BY i.updated_at DESC,i.id DESC LIMIT 500';
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private static function tenantForCurrentLicense(): array {
        global $wpdb;
        if (!self::tablesReady()) return [];
        $remoteId = sanitize_text_field((string)LicenseManager::get('remote_license_id', ''));
        if ($remoteId !== '') {
            $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::tenantsTable() . ' WHERE remote_license_id=%s LIMIT 1', $remoteId), ARRAY_A);
            if (is_array($row)) return self::hydrateTenant($row);
        }
        $key = LicenseManager::normalizeRemoteKey((string)LicenseManager::get('key', ''));
        if ($key === '') return [];
        $hash = hash('sha256', $key);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::tenantsTable() . ' WHERE key_fingerprint_sha256=%s LIMIT 1', $hash), ARRAY_A);
        return is_array($row) ? self::hydrateTenant($row) : [];
    }

    public static function getTenant(int $tenantId): array {
        global $wpdb;
        if ($tenantId <= 0 || !self::tablesReady()) return [];
        $row = $wpdb->get_row($wpdb->prepare('SELECT t.*,c.name AS client_name FROM ' . self::tenantsTable() . ' t LEFT JOIN ' . $wpdb->prefix . 'routespro_clients c ON c.id=t.client_id WHERE t.id=%d LIMIT 1', $tenantId), ARRAY_A);
        return is_array($row) ? self::hydrateTenant($row) : [];
    }

    public static function listTenants(): array {
        global $wpdb;
        if (!self::tablesReady()) return [];
        $rows = $wpdb->get_results('SELECT t.*,c.name AS client_name FROM ' . self::tenantsTable() . ' t LEFT JOIN ' . $wpdb->prefix . 'routespro_clients c ON c.id=t.client_id ORDER BY t.updated_at DESC,t.id DESC LIMIT 500', ARRAY_A) ?: [];
        return array_map([self::class, 'hydrateTenant'], $rows);
    }

    public static function summary(): array {
        global $wpdb;
        $out = ['total'=>0,'draft'=>0,'issued'=>0,'suspended'=>0,'installations_total'=>0,'installations_active'=>0];
        if (!self::tablesReady()) return $out;
        $rows = $wpdb->get_results('SELECT status,COUNT(*) n FROM ' . self::tenantsTable() . ' GROUP BY status', ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $status = sanitize_key((string)($row['status'] ?? 'draft'));
            $count = (int)($row['n'] ?? 0);
            $out['total'] += $count;
            if (isset($out[$status])) $out[$status] += $count;
        }
        $out['installations_total'] = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . self::installationsTable());
        $out['installations_active'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . self::installationsTable() . " WHERE status='active'");
        return $out;
    }

    public static function foundationFingerprint(): string {
        $phase6a = class_exists(ClientOnboardingCertification::class) ? ClientOnboardingCertification::health() : [];
        $base = (string)RemoteLicenseClient::baseUrl();
        $host = strtolower((string)(wp_parse_url($base, PHP_URL_HOST) ?: ''));
        $remoteProbe = self::remoteProbeState();
        $probeHost = strtolower((string)($remoteProbe['host'] ?? ''));
        $payload = [
            'phase'=>self::PHASE,
            'foundation_version'=>self::VERSION,
            'release_integrity_gate_required'=>true,
            'phase6a_certified_fingerprint'=>(string)($phase6a['certified_fingerprint_sha256'] ?? ''),
            'phase6a_baseline_match'=>!empty($phase6a['baseline_match']),
            'tables_ready'=>self::tablesReady(),
            'saas_tables_ready'=>PlanRepository::tablesReady(),
            'remote_api_host'=>$host,
            'remote_shared_secret_configured'=>trim((string)RemoteLicenseClient::sharedSecret()) !== '',
            'remote_admin_secret_configured'=>trim((string)RemoteLicenseClient::adminSecret()) !== '',
            'remote_probe_host'=>$probeHost,
            'remote_probe_ok'=>!empty($remoteProbe['ok']) && $host !== '' && $probeHost !== '' && hash_equals($host, $probeHost),
            'remote_probe_https'=>!empty($remoteProbe['https']),
            'remote_probe_policy_ok'=>!empty($remoteProbe['wordpress_url_validation']) || !empty($remoteProbe['trusted_wordpress_bypass']),
            'remote_probe_route_reachable'=>!empty($remoteProbe['route_reachable']),
            'remote_probe_shared_secret_accepted'=>!empty($remoteProbe['shared_secret_accepted']),
            'license_enforcement_enabled'=>false,
            'installation_binding_enforced'=>false,
            'remote_revocation_enabled'=>false,
            'raw_license_keys_stored'=>false,
        ];
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function hydrateTenant(array $row): array {
        $caps = json_decode((string)($row['capabilities_json'] ?? ''), true);
        $limits = json_decode((string)($row['limits_json'] ?? ''), true);
        $row['capabilities'] = is_array($caps) ? $caps : [];
        $row['limits'] = is_array($limits) ? $limits : [];
        return $row;
    }

    private static function client(int $clientId): array {
        global $wpdb;
        if ($clientId <= 0) return [];
        $row = $wpdb->get_row($wpdb->prepare('SELECT id,name,email FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d LIMIT 1', $clientId), ARRAY_A);
        return is_array($row) ? $row : [];
    }

    private static function normaliseExpiry(string $value) {
        $value = trim($value);
        if ($value === '') return '';
        $ts = strtotime($value . ' 23:59:59 UTC');
        if (!$ts) return new \WP_Error('fieldflow_6b_expiry_invalid', 'A data de expiração não é válida.');
        if ($ts <= time() + DAY_IN_SECONDS) return new \WP_Error('fieldflow_6b_expiry_too_soon', 'A expiração deve ficar pelo menos dois dias no futuro.');
        return gmdate('Y-m-d H:i:s', $ts);
    }

    private static function customerCode(string $name, int $clientId): string {
        $base = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', remove_accents($name)));
        if ($base === '') $base = 'CLIENT';
        $base = substr($base, 0, 18);
        return substr($base . $clientId, 0, 24);
    }

    private static function bindingFingerprint(array $payload): string {
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function maskKey(string $key): string {
        $length = strlen($key);
        if ($length <= 8) return str_repeat('*', max(0, $length - 4)) . substr($key, -4);
        return substr($key, 0, 6) . str_repeat('*', max(4, $length - 10)) . substr($key, -4);
    }

    private static function oneTimeKeyRuntimeIndex(int $tenantId, int $userId): string {
        return max(0, $userId) . ':' . max(0, $tenantId);
    }

    private static function releaseVersionConsistent(): bool {
        $constant = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $root = defined('FIELDFLOW_PATH') ? (string)FIELDFLOW_PATH : (defined('ROUTESPRO_PATH') ? (string)ROUTESPRO_PATH : '');
        if ($constant === '' || $root === '') return false;
        $root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
        $pluginFile = $root . 'fieldflow.php';
        $readme = $root . 'readme.txt';
        if (!is_readable($pluginFile) || !is_readable($readme)) return false;
        $header = '';
        $stable = '';
        $pluginContent = (string)file_get_contents($pluginFile);
        $readmeContent = (string)file_get_contents($readme);
        if (preg_match('/^\s*\*\s*Version:\s*([^\r\n]+)/mi', $pluginContent, $m)) $header = trim($m[1]);
        if (preg_match('/^Stable tag:\s*([^\r\n]+)/mi', $readmeContent, $m)) $stable = trim($m[1]);
        return $constant === $header && $constant === $stable;
    }

    private static function remoteProbeState(): array {
        $state = get_option(self::REMOTE_PROBE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function state(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function audit(string $event, int $tenantId, array $details, string $reason): void {
        if (!class_exists(GovernanceRecovery::class)) return;
        $tenant = $tenantId > 0 ? self::getTenant($tenantId) : [];
        GovernanceRecovery::appendLedger($event, [
            'entity_type'=>'license_tenant',
            'entity_ref'=>(string)($tenant['tenant_uuid'] ?? ($tenantId > 0 ? 'tenant:' . $tenantId : self::PHASE)),
            'client_id'=>(int)($tenant['client_id'] ?? 0),
            'project_id'=>0,
            'destination_uuid'=>'',
        ], array_merge($details, [
            'phase'=>self::PHASE,
            'license_enforcement_enabled'=>false,
            'remote_revocation_enabled'=>false,
            'raw_license_keys_stored'=>false,
        ]), $reason);
    }

    private static function canonicalize($value) {
        if (is_array($value)) {
            if (array_keys($value) !== range(0, count($value) - 1)) ksort($value, SORT_STRING);
            foreach ($value as $key=>$item) $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
}
