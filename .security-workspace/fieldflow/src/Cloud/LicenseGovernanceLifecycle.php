<?php
namespace RoutesPro\Cloud;

use RoutesPro\Support\LicenseManager;
use RoutesPro\Support\ProductionManifest;
use RoutesPro\Support\RemoteLicenseClient;

if (!defined('ABSPATH')) exit;

/**
 * Phase 6B.3, Governance, Rotation, Limits & Shadow Enforcement.
 *
 * Safety guarantees for this release:
 * - remote governance is real and auditable, but runtime enforcement stays SHADOW;
 * - suspended/revoked/expired/rotated decisions are observed as would_block only;
 * - Azure outages remain fail-open and cannot disable FieldFlow;
 * - a 72h grace model is calculated now, but only becomes blocking in a later explicit gate;
 * - rotation keys are presented once from request memory and are never persisted in clear text.
 */
final class LicenseGovernanceLifecycle {
    public const VERSION = '1.0.2';
    public const PHASE = '6B.3';
    public const STATE_OPTION = 'fieldflow_phase6b3_governance_state';
    public const CERT_OPTION = 'fieldflow_phase6b3_certification_state';
    public const ENFORCEMENT_MODE = 'shadow';
    public const RUNTIME_BLOCKING_ENABLED = false;
    public const REMOTE_REVOCATION_ENFORCED = false;
    public const INSTALLATION_LIMIT_ENFORCED_REMOTELY = true;
    public const GRACE_PERIOD_HOURS = 72;

    private static array $oneTimeRotationKeys = [];
    private static ?array $healthCache = null;

    public static function registerHooks(): void {
        add_action('fieldflow_license_heartbeat_observed', [self::class, 'observeHeartbeat'], 10, 1);
    }

    public static function observeHeartbeat(array $heartbeat): void {
        $state = self::state();
        $remoteStatus = sanitize_key((string)($heartbeat['remote_status'] ?? ''));
        $httpCode = (int)($heartbeat['http_code'] ?? 0);
        $accepted = !empty($heartbeat['accepted']);
        $wouldBlock = in_array($remoteStatus, ['suspended','revoked','expired','rotated'], true)
            || (!$accepted && in_array($httpCode, [403,409], true));
        $state['last_heartbeat_observed_at'] = current_time('mysql', true);
        $state['last_heartbeat_http_code'] = $httpCode;
        $state['last_heartbeat_remote_status'] = $remoteStatus;
        $state['last_heartbeat_accepted'] = $accepted;
        $state['shadow_would_block'] = $wouldBlock;
        $state['runtime_blocked'] = false;
        $state['enforcement_mode'] = self::ENFORCEMENT_MODE;
        $state['grace_period_hours'] = self::GRACE_PERIOD_HOURS;
        update_option(self::STATE_OPTION, $state, false);
        self::resetHealthCache();
        self::audit('phase6b3.shadow.observed', [
            'http_code'=>$httpCode,
            'remote_status'=>$remoteStatus,
            'would_block'=>$wouldBlock,
            'runtime_blocked'=>false,
        ], $wouldBlock ? 'A remote licensing decision would block under future enforcement, but 6B.3 remains shadow.' : 'Remote licensing decision observed in shadow without runtime blocking.');
    }

    public static function testCurrentGovernance(): array {
        $tenant = self::currentTenant();
        if (!$tenant) return self::storeFailure('tenant_not_local', 'A licença ativa não corresponde a um tenant local 6B.');
        $sync = self::syncTenantPolicy((int)$tenant['id']);
        if (is_wp_error($sync)) return self::storeFailure('policy_sync_failed', $sync->get_error_message());
        return self::inspectTenant((int)$tenant['id'], true);
    }

    public static function inspectTenant(int $tenantId, bool $fromTest = false): array {
        $tenant = LicenseTenantFoundation::getTenant($tenantId);
        if (!$tenant) return self::storeFailure('tenant_missing', 'Tenant de licenciamento não encontrado.');
        $remoteId = sanitize_text_field((string)($tenant['remote_license_id'] ?? ''));
        if ($remoteId === '') return self::storeFailure('remote_license_missing', 'O tenant ainda não tem license_id remoto.');
        if (!self::remoteAdminReady()) return self::storeFailure('remote_admin_not_ready', 'API remota ou Admin Secret por configurar.');

        $response = RemoteLicenseClient::post('/license/manage', [
            'operation'=>'inspect',
            'license_id'=>$remoteId,
        ], true);
        if (empty($response['success'])) {
            return self::storeFailure('remote_inspect_failed', sanitize_text_field((string)($response['message'] ?? 'Falha no inspect remoto.')), (int)($response['http_code'] ?? 0));
        }
        return self::storeRemoteState($tenant, $response, $fromTest ? 'governance_test' : 'inspect');
    }

    public static function syncTenantPolicy(int $tenantId) {
        $tenant = LicenseTenantFoundation::getTenant($tenantId);
        if (!$tenant) return new \WP_Error('fieldflow_6b3_tenant_missing', 'Tenant de licenciamento não encontrado.');
        $remoteId = sanitize_text_field((string)($tenant['remote_license_id'] ?? ''));
        if ($remoteId === '') return new \WP_Error('fieldflow_6b3_remote_license_missing', 'Este tenant ainda não tem licença remota emitida.');
        if (!self::remoteAdminReady()) return new \WP_Error('fieldflow_6b3_remote_not_ready', 'A API remota e o Admin Secret têm de estar configurados.');
        $policy = self::policySnapshot($tenant);
        $response = RemoteLicenseClient::post('/license/manage', [
            'operation'=>'sync_policy',
            'license_id'=>$remoteId,
            'plan'=>$policy['plan'],
            'max_activations'=>$policy['max_activations'],
            'expires_at'=>$policy['expires_at'],
            'capabilities'=>$policy['capabilities'],
            'limits'=>$policy['limits'],
        ], true);
        if (empty($response['success'])) {
            return new \WP_Error('fieldflow_6b3_policy_sync_failed', sanitize_text_field((string)($response['message'] ?? 'A API não sincronizou a policy.')));
        }
        $state = self::storeRemoteState($tenant, $response, 'policy_sync');
        $remoteFingerprint = strtolower(sanitize_text_field((string)($state['remote_policy_fingerprint_sha256'] ?? '')));
        $localFingerprint = self::policyFingerprint($policy);
        if ($remoteFingerprint === '' || !hash_equals($localFingerprint, $remoteFingerprint)) {
            return new \WP_Error('fieldflow_6b3_policy_fingerprint_mismatch', 'A policy foi recebida, mas o fingerprint remoto não coincide com o snapshot local.');
        }
        self::audit('phase6b3.policy.synced', [
            'tenant_id'=>$tenantId,
            'remote_license_id'=>$remoteId,
            'policy_fingerprint_sha256'=>$localFingerprint,
            'max_installations'=>(int)$policy['max_activations'],
        ], 'Plan, capabilities, limits and installation ceiling synchronized to the central licensing service.');
        return ['ok'=>true,'state'=>$state,'policy_fingerprint_sha256'=>$localFingerprint];
    }

    public static function governTenant(int $tenantId, string $operation, string $reason = '') {
        $operation = sanitize_key($operation);
        if (!in_array($operation, ['suspend','resume','revoke'], true)) return new \WP_Error('fieldflow_6b3_operation_invalid', 'Operação de governance inválida.');
        $tenant = LicenseTenantFoundation::getTenant($tenantId);
        if (!$tenant) return new \WP_Error('fieldflow_6b3_tenant_missing', 'Tenant de licenciamento não encontrado.');
        $remoteId = sanitize_text_field((string)($tenant['remote_license_id'] ?? ''));
        if ($remoteId === '') return new \WP_Error('fieldflow_6b3_remote_license_missing', 'Este tenant não tem license_id remoto.');
        if (!self::remoteAdminReady()) return new \WP_Error('fieldflow_6b3_remote_not_ready', 'A API remota e o Admin Secret têm de estar configurados.');
        $response = RemoteLicenseClient::post('/license/manage', [
            'operation'=>$operation,
            'license_id'=>$remoteId,
            'reason'=>sanitize_text_field($reason !== '' ? $reason : ('fieldflow_admin_' . $operation)),
        ], true);
        if (empty($response['success'])) return new \WP_Error('fieldflow_6b3_governance_failed', sanitize_text_field((string)($response['message'] ?? 'A operação remota falhou.')));
        self::applyLocalGovernanceState($tenantId, $operation);
        $state = self::storeRemoteState(LicenseTenantFoundation::getTenant($tenantId) ?: $tenant, $response, $operation);
        self::audit('phase6b3.license.' . $operation, [
            'tenant_id'=>$tenantId,
            'remote_license_id'=>$remoteId,
            'remote_status'=>(string)($state['remote_status'] ?? ''),
            'runtime_blocking_enabled'=>false,
        ], 'Central governance action completed. FieldFlow runtime enforcement remains shadow in 6B.3.');
        return ['ok'=>true,'state'=>$state];
    }

    public static function rotateTenantLicense(int $tenantId) {
        global $wpdb;
        $tenant = LicenseTenantFoundation::getTenant($tenantId);
        if (!$tenant) return new \WP_Error('fieldflow_6b3_tenant_missing', 'Tenant de licenciamento não encontrado.');
        $remoteId = sanitize_text_field((string)($tenant['remote_license_id'] ?? ''));
        if ($remoteId === '') return new \WP_Error('fieldflow_6b3_remote_license_missing', 'Este tenant não tem license_id remoto.');
        if (!self::remoteAdminReady()) return new \WP_Error('fieldflow_6b3_remote_not_ready', 'A API remota e o Admin Secret têm de estar configurados.');
        $response = RemoteLicenseClient::post('/license/manage', [
            'operation'=>'rotate',
            'license_id'=>$remoteId,
            'reason'=>'fieldflow_admin_rotation',
        ], true);
        if (empty($response['success'])) return new \WP_Error('fieldflow_6b3_rotation_failed', sanitize_text_field((string)($response['message'] ?? 'A rotação remota falhou.')));
        $newKey = trim((string)($response['key'] ?? ''));
        $newRemoteId = sanitize_text_field((string)($response['license_id'] ?? (($response['license']['license_id'] ?? ''))));
        if ($newKey === '' || $newRemoteId === '') return new \WP_Error('fieldflow_6b3_rotation_contract_invalid', 'A API confirmou a rotação mas não devolveu a nova key/license_id.');
        $masked = self::maskKey($newKey);
        $now = current_time('mysql', true);
        $wpdb->update(LicenseTenantFoundation::tenantsTable(), [
            'status'=>'issued',
            'remote_license_id'=>$newRemoteId,
            'key_fingerprint_sha256'=>hash('sha256', $newKey),
            'masked_key'=>$masked,
            'issued_at'=>$now,
            'revoked_at'=>null,
            'last_sync_at'=>$now,
            'updated_by'=>get_current_user_id() ?: null,
            'updated_at'=>$now,
        ], ['id'=>$tenantId]);
        $wpdb->query($wpdb->prepare("UPDATE " . LicenseTenantFoundation::installationsTable() . " SET status='rotation_required',updated_at=%s WHERE tenant_id=%d AND status='active'", $now, $tenantId));
        self::$oneTimeRotationKeys[self::rotationIndex($tenantId, get_current_user_id())] = $newKey;
        self::audit('phase6b3.license.rotated', [
            'tenant_id'=>$tenantId,
            'previous_remote_license_id'=>$remoteId,
            'replacement_remote_license_id'=>$newRemoteId,
            'masked_key'=>$masked,
            'raw_key_stored'=>false,
        ], 'License rotated centrally. Replacement key is held only in request memory for one-time presentation.');
        return ['ok'=>true,'tenant_id'=>$tenantId,'remote_license_id'=>$newRemoteId,'masked_key'=>$masked];
    }

    public static function consumeOneTimeRotationKey(int $tenantId): string {
        $index = self::rotationIndex($tenantId, get_current_user_id());
        $key = (string)(self::$oneTimeRotationKeys[$index] ?? '');
        unset(self::$oneTimeRotationKeys[$index]);
        return $key;
    }

    public static function health(): array {
        if (self::$healthCache !== null) return self::$healthCache;
        $phase6b2 = LicenseInstallationLifecycle::health();
        $manifest = ProductionManifest::verify();
        $state = self::state();
        $cert = self::certState();
        $currentTenant = self::currentTenant();
        $currentPolicy = $currentTenant ? self::policySnapshot($currentTenant) : [];
        $localPolicyFingerprint = $currentPolicy ? self::policyFingerprint($currentPolicy) : '';
        $remotePolicyFingerprint = strtolower(sanitize_text_field((string)($state['remote_policy_fingerprint_sha256'] ?? '')));
        $policyMatch = $localPolicyFingerprint !== '' && preg_match('/^[a-f0-9]{64}$/', $remotePolicyFingerprint) === 1 && hash_equals($localPolicyFingerprint, $remotePolicyFingerprint);
        $caps = is_array($state['governance_capabilities'] ?? null) ? $state['governance_capabilities'] : [];
        $remoteStatus = sanitize_key((string)($state['remote_status'] ?? ''));
        $activeCount = (int)($state['remote_active_activations'] ?? 0);
        $maxInstallations = (int)($currentTenant['max_installations'] ?? 0);
        $limitHealthy = $maxInstallations > 0 && $activeCount <= $maxInstallations;
        $grace = self::graceState();
        $fingerprint = self::certificationFingerprint();
        $certFp = strtolower(sanitize_text_field((string)($cert['fingerprint_sha256'] ?? '')));
        $certFpMatch = preg_match('/^[a-f0-9]{64}$/', $certFp) === 1 && hash_equals($certFp, $fingerprint);
        $checksReady = !empty($phase6b2['certified'])
            && !empty($manifest['ok'])
            && self::remoteAdminReady()
            && !empty($currentTenant)
            && !empty($state['remote_manage_ready'])
            && $policyMatch
            && $limitHealthy
            && !empty($caps['inspect'])
            && !empty($caps['policy_sync'])
            && !empty($caps['suspend'])
            && !empty($caps['resume'])
            && !empty($caps['revoke'])
            && !empty($caps['rotate'])
            && !empty($caps['max_activation_limit'])
            && self::ENFORCEMENT_MODE === 'shadow'
            && self::RUNTIME_BLOCKING_ENABLED === false
            && self::REMOTE_REVOCATION_ENFORCED === false
            && self::GRACE_PERIOD_HOURS >= 24;
        $certified = !empty($cert['certified']) && $certFpMatch && $checksReady;
        return self::$healthCache = [
            'phase'=>self::PHASE,
            'version'=>self::VERSION,
            'status'=>$certified ? 'ready' : ($checksReady ? 'pilot_ready' : 'attention'),
            'phase6b2_certified'=>!empty($phase6b2['certified']),
            'release_manifest_ok'=>!empty($manifest['ok']),
            'remote_admin_ready'=>self::remoteAdminReady(),
            'current_tenant_ready'=>!empty($currentTenant),
            'current_tenant_id'=>(int)($currentTenant['id'] ?? 0),
            'remote_manage_ready'=>!empty($state['remote_manage_ready']),
            'remote_status'=>$remoteStatus,
            'remote_active_activations'=>$activeCount,
            'max_installations'=>$maxInstallations,
            'installation_limit_healthy'=>$limitHealthy,
            'local_policy_fingerprint_sha256'=>$localPolicyFingerprint,
            'remote_policy_fingerprint_sha256'=>$remotePolicyFingerprint,
            'policy_fingerprint_match'=>$policyMatch,
            'governance_capabilities'=>$caps,
            'enforcement_mode'=>self::ENFORCEMENT_MODE,
            'runtime_blocking_enabled'=>self::RUNTIME_BLOCKING_ENABLED,
            'remote_revocation_enforced'=>self::REMOTE_REVOCATION_ENFORCED,
            'installation_limit_enforced_remotely'=>self::INSTALLATION_LIMIT_ENFORCED_REMOTELY,
            'grace_period_hours'=>self::GRACE_PERIOD_HOURS,
            'grace_active'=>!empty($grace['active']),
            'grace_remaining_hours'=>(int)($grace['remaining_hours'] ?? 0),
            'shadow_would_block'=>!empty($state['shadow_would_block']),
            'last_checked_at'=>sanitize_text_field((string)($state['last_checked_at'] ?? '')),
            'last_message'=>sanitize_text_field((string)($state['message'] ?? '')),
            'raw_rotation_key_stored'=>false,
            'certified'=>$certified,
            'certification_state_present'=>!empty($cert['certified']) || preg_match('/^[a-f0-9]{64}$/', $certFp) === 1,
            'certification_requires_revalidation'=>!empty($cert['certified']) && !$certFpMatch,
            'certification_fingerprint_match'=>$certFpMatch,
            'certification_fingerprint_sha256'=>$fingerprint,
            'certified_fingerprint_sha256'=>$certFp,
            'certified_at'=>sanitize_text_field((string)($cert['certified_at'] ?? '')),
        ];
    }

    public static function resetHealthCache(): void {
        self::$healthCache = null;
    }

    public static function localProbe(): array {
        $h = self::health();
        $caps = (array)($h['governance_capabilities'] ?? []);
        $checks = [
            'phase6b2_certified'=>!empty($h['phase6b2_certified']),
            'release_manifest_integrity'=>!empty($h['release_manifest_ok']),
            'remote_admin_ready'=>!empty($h['remote_admin_ready']),
            'current_tenant_resolved'=>!empty($h['current_tenant_ready']),
            'remote_governance_manage_ready'=>!empty($h['remote_manage_ready']),
            'policy_snapshot_acknowledged'=>!empty($h['policy_fingerprint_match']),
            'installation_limit_healthy'=>!empty($h['installation_limit_healthy']),
            'governance_can_suspend'=>!empty($caps['suspend']),
            'governance_can_resume'=>!empty($caps['resume']),
            'governance_can_revoke'=>!empty($caps['revoke']),
            'governance_can_rotate'=>!empty($caps['rotate']),
            'max_activation_limit_remote'=>!empty($caps['max_activation_limit']),
            'enforcement_shadow_mode'=>($h['enforcement_mode'] ?? '') === 'shadow',
            'runtime_blocking_off'=>empty($h['runtime_blocking_enabled']),
            'remote_revocation_not_runtime_enforced'=>empty($h['remote_revocation_enforced']),
            'grace_period_defined'=>(int)($h['grace_period_hours'] ?? 0) >= 24,
            'raw_rotation_key_not_stored'=>empty($h['raw_rotation_key_stored']),
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$h];
    }

    public static function certify(int $actorId = 0): array {
        ProductionManifest::verify(true);
        self::resetHealthCache();
        $probe = self::localProbe();
        if (empty($probe['ok'])) return ['ok'=>false,'reason'=>'phase6b3_preconditions_failed','checks'=>$probe['checks'],'health'=>$probe['health']];
        $fingerprint = self::certificationFingerprint();
        update_option(self::CERT_OPTION, [
            'certified'=>true,
            'certified_at'=>current_time('mysql', true),
            'certified_by'=>max(0,$actorId),
            'fingerprint_sha256'=>$fingerprint,
            'certified_on_plugin_version'=>defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'upstream_6b2_fingerprint_sha256'=>(string)($probe['health']['certified_fingerprint_sha256'] ?? ''),
            'policy_fingerprint_sha256'=>(string)($probe['health']['remote_policy_fingerprint_sha256'] ?? ''),
            'enforcement_mode'=>self::ENFORCEMENT_MODE,
            'runtime_blocking_enabled'=>false,
            'remote_revocation_enforced'=>false,
            'grace_period_hours'=>self::GRACE_PERIOD_HOURS,
        ], false);
        self::resetHealthCache();
        self::audit('phase6b3.certified', ['fingerprint_sha256'=>$fingerprint,'enforcement_mode'=>self::ENFORCEMENT_MODE], 'Phase 6B.3 governance, rotation and limits certified in shadow enforcement mode.');
        return ['ok'=>true,'reason'=>'phase6b3_certified','checks'=>$probe['checks'],'health'=>self::health()];
    }

    public static function resetCertification(): array {
        $before = self::certState();
        update_option(self::CERT_OPTION, [
            'certified'=>false,'certified_at'=>'','certified_by'=>0,'fingerprint_sha256'=>'',
            'enforcement_mode'=>self::ENFORCEMENT_MODE,'runtime_blocking_enabled'=>false,
            'remote_revocation_enforced'=>false,'grace_period_hours'=>self::GRACE_PERIOD_HOURS,
        ], false);
        self::resetHealthCache();
        self::audit('phase6b3.certification.reset', ['previous_fingerprint_sha256'=>(string)($before['fingerprint_sha256'] ?? '')], 'Phase 6B.3 certification reset. No license or installation status was changed.');
        return ['ok'=>true,'reason'=>'phase6b3_certification_reset'];
    }

    public static function state(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function storeRemoteState(array $tenant, array $response, string $source): array {
        $license = is_array($response['license'] ?? null) ? $response['license'] : [];
        $caps = is_array($response['governance_capabilities'] ?? null) ? $response['governance_capabilities'] : [];
        $state = self::state();
        $state = array_merge($state, [
            'ok'=>true,
            'source'=>sanitize_key($source),
            'remote_manage_ready'=>true,
            'http_code'=>(int)($response['http_code'] ?? 0),
            'message'=>sanitize_text_field((string)($response['message'] ?? 'Governance remota disponível.')),
            'tenant_id'=>(int)($tenant['id'] ?? 0),
            'remote_license_id'=>sanitize_text_field((string)($license['license_id'] ?? ($tenant['remote_license_id'] ?? ''))),
            'remote_status'=>sanitize_key((string)($license['license_status'] ?? ($license['status'] ?? ''))),
            'remote_plan'=>sanitize_key((string)($license['plan'] ?? '')),
            'remote_max_activations'=>(int)($license['max_activations'] ?? 0),
            'remote_active_activations'=>(int)($response['active_activations'] ?? ($license['active_activations'] ?? 0)),
            'remote_policy_fingerprint_sha256'=>strtolower(sanitize_text_field((string)($license['policy_fingerprint_sha256'] ?? ''))),
            'governance_capabilities'=>$caps,
            'last_checked_at'=>current_time('mysql', true),
            'last_success_at'=>current_time('mysql', true),
            'enforcement_mode'=>self::ENFORCEMENT_MODE,
            'runtime_blocked'=>false,
            'grace_period_hours'=>self::GRACE_PERIOD_HOURS,
            'raw_rotation_key_stored'=>false,
        ]);
        update_option(self::STATE_OPTION, $state, false);
        self::resetHealthCache();
        return $state;
    }

    private static function storeFailure(string $reason, string $message, int $httpCode = 0): array {
        $state = self::state();
        $state = array_merge($state, [
            'ok'=>false,
            'reason'=>sanitize_key($reason),
            'message'=>sanitize_text_field($message),
            'http_code'=>$httpCode,
            'last_checked_at'=>current_time('mysql', true),
            'enforcement_mode'=>self::ENFORCEMENT_MODE,
            'runtime_blocked'=>false,
            'grace_period_hours'=>self::GRACE_PERIOD_HOURS,
            'raw_rotation_key_stored'=>false,
        ]);
        update_option(self::STATE_OPTION, $state, false);
        self::resetHealthCache();
        return $state;
    }

    private static function applyLocalGovernanceState(int $tenantId, string $operation): void {
        global $wpdb;
        $now = current_time('mysql', true);
        if ($operation === 'suspend') {
            $wpdb->update(LicenseTenantFoundation::tenantsTable(), ['status'=>'suspended','last_sync_at'=>$now,'updated_at'=>$now], ['id'=>$tenantId]);
            $wpdb->query($wpdb->prepare("UPDATE " . LicenseTenantFoundation::installationsTable() . " SET status='suspended',updated_at=%s WHERE tenant_id=%d AND status='active'", $now, $tenantId));
        } elseif ($operation === 'resume') {
            $wpdb->update(LicenseTenantFoundation::tenantsTable(), ['status'=>'issued','last_sync_at'=>$now,'updated_at'=>$now], ['id'=>$tenantId]);
            $wpdb->query($wpdb->prepare("UPDATE " . LicenseTenantFoundation::installationsTable() . " SET status='active',updated_at=%s WHERE tenant_id=%d AND status='suspended'", $now, $tenantId));
        } elseif ($operation === 'revoke') {
            $wpdb->update(LicenseTenantFoundation::tenantsTable(), ['status'=>'revoked','revoked_at'=>$now,'last_sync_at'=>$now,'updated_at'=>$now], ['id'=>$tenantId]);
            $wpdb->query($wpdb->prepare("UPDATE " . LicenseTenantFoundation::installationsTable() . " SET status='revoked',revoked_at=%s,updated_at=%s WHERE tenant_id=%d AND status NOT IN ('inactive','revoked')", $now, $now, $tenantId));
        }
        LicenseTenantFoundation::resetHealthCache();
        self::resetHealthCache();
    }

    private static function currentTenant(): array {
        $remoteId = sanitize_text_field((string)LicenseManager::get('remote_license_id', ''));
        $key = LicenseManager::normalizeRemoteKey((string)LicenseManager::get('key', ''));
        $keyHash = $key !== '' ? hash('sha256', $key) : '';
        foreach (LicenseTenantFoundation::listTenants() as $tenant) {
            if ($remoteId !== '' && hash_equals($remoteId, (string)($tenant['remote_license_id'] ?? ''))) return $tenant;
            if ($keyHash !== '' && hash_equals($keyHash, (string)($tenant['key_fingerprint_sha256'] ?? ''))) return $tenant;
        }
        return [];
    }

    private static function policySnapshot(array $tenant): array {
        $caps = is_array($tenant['capabilities'] ?? null) ? $tenant['capabilities'] : [];
        $limits = is_array($tenant['limits'] ?? null) ? $tenant['limits'] : [];
        $caps = self::normaliseNumericMap($caps);
        $limits = self::normaliseNumericMap($limits);
        return [
            'plan'=>sanitize_key((string)($tenant['plan_slug'] ?? 'enterprise')),
            'max_activations'=>max(1,(int)($tenant['max_installations'] ?? 1)),
            'expires_at'=>sanitize_text_field((string)($tenant['expires_at'] ?? '')),
            'capabilities'=>$caps,
            'limits'=>$limits,
        ];
    }

    private static function normaliseNumericMap(array $value): array {
        $out = [];
        foreach ($value as $key=>$item) {
            $key = sanitize_text_field((string)$key);
            if ($key === '') continue;
            if (is_bool($item)) $out[$key] = $item ? 1 : 0;
            elseif (is_numeric($item)) $out[$key] = max(0,(int)$item);
        }
        ksort($out, SORT_STRING);
        return $out;
    }

    private static function policyFingerprint(array $snapshot): string {
        // PHP encodes an empty array as [] while the Node licensing service
        // canonicalizes policy maps as JSON objects. Preserve map semantics
        // explicitly so empty capabilities/limits hash exactly like Azure.
        foreach (['capabilities', 'limits'] as $mapKey) {
            $map = is_array($snapshot[$mapKey] ?? null) ? $snapshot[$mapKey] : [];
            if ($map === []) $snapshot[$mapKey] = (object)[];
        }
        $json = wp_json_encode(self::canonicalize($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function graceState(): array {
        $state = self::state();
        $last = sanitize_text_field((string)($state['last_success_at'] ?? ''));
        if ($last === '') return ['active'=>false,'remaining_hours'=>0];
        $ts = strtotime($last . (stripos($last, 'UTC') === false ? ' UTC' : ''));
        if (!$ts) return ['active'=>false,'remaining_hours'=>0];
        $remaining = self::GRACE_PERIOD_HOURS - (int)floor(max(0,time()-$ts)/HOUR_IN_SECONDS);
        return ['active'=>$remaining > 0,'remaining_hours'=>max(0,$remaining)];
    }

    private static function remoteAdminReady(): bool {
        return RemoteLicenseClient::isReady() && trim((string)RemoteLicenseClient::adminSecret()) !== '';
    }

    private static function certificationFingerprint(): string {
        $phase6b2 = LicenseInstallationLifecycle::health();
        $state = self::state();
        $payload = [
            'phase'=>self::PHASE,
            'version'=>self::VERSION,
            'phase6b2_certified_fingerprint'=>(string)($phase6b2['certified_fingerprint_sha256'] ?? ''),
            'remote_api_host'=>strtolower((string)(wp_parse_url(RemoteLicenseClient::baseUrl(), PHP_URL_HOST) ?: '')),
            'remote_license_id'=>(string)($state['remote_license_id'] ?? ''),
            'policy_fingerprint_sha256'=>(string)($state['remote_policy_fingerprint_sha256'] ?? ''),
            'enforcement_mode'=>self::ENFORCEMENT_MODE,
            'runtime_blocking_enabled'=>false,
            'remote_revocation_enforced'=>false,
            'installation_limit_enforced_remotely'=>true,
            'grace_period_hours'=>self::GRACE_PERIOD_HOURS,
        ];
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function certState(): array {
        $state = get_option(self::CERT_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function rotationIndex(int $tenantId, int $userId): string {
        return max(0,$userId) . ':' . max(0,$tenantId);
    }

    private static function maskKey(string $key): string {
        $length = strlen($key);
        if ($length <= 8) return str_repeat('*', max(0,$length-4)) . substr($key,-4);
        return substr($key,0,6) . str_repeat('*', max(4,$length-10)) . substr($key,-4);
    }

    private static function audit(string $event, array $details, string $reason): void {
        if (!class_exists(GovernanceRecovery::class)) return;
        GovernanceRecovery::appendLedger($event, [
            'entity_type'=>'license_governance',
            'entity_ref'=>self::PHASE,
            'client_id'=>0,
            'project_id'=>0,
            'destination_uuid'=>'',
        ], array_merge($details, [
            'phase'=>self::PHASE,
            'enforcement_mode'=>self::ENFORCEMENT_MODE,
            'runtime_blocking_enabled'=>false,
            'remote_revocation_enforced'=>false,
            'raw_license_key_stored'=>false,
        ]), $reason);
    }

    private static function canonicalize($value) {
        if (is_array($value)) {
            if (array_keys($value) !== range(0, count($value)-1)) ksort($value, SORT_STRING);
            foreach ($value as $key=>$item) $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
}
