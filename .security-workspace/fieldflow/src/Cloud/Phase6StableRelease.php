<?php
namespace RoutesPro\Cloud;

use RoutesPro\Support\ProductionManifest;
use RoutesPro\Support\ReleaseGate;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow Phase 6 stable closure.
 *
 * Freezes the certified Phase 6 baseline after the private updater has been
 * proven end to end. It does not enable runtime license enforcement or any
 * operational production delivery switch.
 */
final class Phase6StableRelease {
    public const VERSION = '1.0.200';
    public const PHASE = '6-STABLE';
    // 9DA: active release identity is the only authority. History is inert.
    public const SOURCE_RELEASE = '2.2.658';
    public const STABLE_RELEASE = '2.2.659';
    // 9DX predecessor: public const SOURCE_RELEASE = '2.2.656';
    // 9DX predecessor: public const STABLE_RELEASE = '2.2.657';
    // 9DW predecessor: public const SOURCE_RELEASE = '2.2.655';
    // 9DW predecessor: public const STABLE_RELEASE = '2.2.656';
    // 9DV predecessor: public const SOURCE_RELEASE = '2.2.654';
    // 9DV predecessor: public const STABLE_RELEASE = '2.2.655';
    // 9DU predecessor: public const SOURCE_RELEASE = '2.2.653';
    // 9DU predecessor: public const STABLE_RELEASE = '2.2.654';
    // 9DT predecessor: public const SOURCE_RELEASE = '2.2.652';
    // 9DT predecessor: public const STABLE_RELEASE = '2.2.653';
    // 9DS predecessor: public const SOURCE_RELEASE = '2.2.651';
    // 9DS predecessor: public const STABLE_RELEASE = '2.2.652';
    // 9DR predecessor: public const SOURCE_RELEASE = '2.2.650';
    // 9DR predecessor: public const STABLE_RELEASE = '2.2.651';
    // 9DQ predecessor: public const SOURCE_RELEASE = '2.2.649';
    // 9DQ predecessor: public const STABLE_RELEASE = '2.2.650';
    // 9DP predecessor: public const SOURCE_RELEASE = '2.2.648';
    // 9DP predecessor: public const STABLE_RELEASE = '2.2.649';
    // 9DO predecessor: public const SOURCE_RELEASE = '2.2.647';
    // 9DO predecessor: public const STABLE_RELEASE = '2.2.648';
    // 9DN predecessor: public const SOURCE_RELEASE = '2.2.646';
    // 9DN predecessor: public const STABLE_RELEASE = '2.2.647';
    // 9DM predecessor: public const SOURCE_RELEASE = '2.2.645';
    // 9DM predecessor: public const STABLE_RELEASE = '2.2.646';
    // 9DL predecessor: public const SOURCE_RELEASE = '2.2.644';
    // 9DL predecessor: public const STABLE_RELEASE = '2.2.645';
    // 9DK predecessor: public const SOURCE_RELEASE = '2.2.643';
    // 9DK predecessor: public const STABLE_RELEASE = '2.2.644';
    // 9DJ predecessor: public const SOURCE_RELEASE = '2.2.642';
    // 9DJ predecessor: public const STABLE_RELEASE = '2.2.643';
    // 9DI predecessor: public const SOURCE_RELEASE = '2.2.641';
    // 9DI predecessor: public const STABLE_RELEASE = '2.2.642';
    // 9DG predecessor: public const SOURCE_RELEASE = '2.2.639';
    // 9DG predecessor: public const STABLE_RELEASE = '2.2.640';
    // 9DF predecessor: public const SOURCE_RELEASE = '2.2.638';
    // 9DF predecessor: public const STABLE_RELEASE = '2.2.639';
    // 9DD predecessor: public const SOURCE_RELEASE = '2.2.637';
    // 9DD predecessor: public const STABLE_RELEASE = '2.2.638';
    // 9DC predecessor: public const SOURCE_RELEASE = '2.2.636';
    // 9DC predecessor: public const STABLE_RELEASE = '2.2.637';
    // 9DB predecessor: public const SOURCE_RELEASE = '2.2.635';
    // 9DB predecessor: public const STABLE_RELEASE = '2.2.636';
    // 9DA predecessor: public const SOURCE_RELEASE = '2.2.634';
    // 9DA predecessor: public const STABLE_RELEASE = '2.2.635';
    // 9D9 predecessor: public const SOURCE_RELEASE = '2.2.633';
    // 9D9 predecessor: public const STABLE_RELEASE = '2.2.634';
    // 9D8 predecessor: public const SOURCE_RELEASE = '2.2.632';
    // 9D8 predecessor: public const STABLE_RELEASE = '2.2.633';
    // 9D7 predecessor: public const SOURCE_RELEASE = '2.2.631';
    // 9D7 predecessor: public const STABLE_RELEASE = '2.2.632';
    // 9D6 predecessor: public const SOURCE_RELEASE = '2.2.630';
    // 9D6 predecessor: public const STABLE_RELEASE = '2.2.631';
    // 9D5 predecessor: public const SOURCE_RELEASE = '2.2.629';
    // 9D5 predecessor: public const STABLE_RELEASE = '2.2.630';
    // 9D4 predecessor: public const SOURCE_RELEASE = '2.2.628';
    // 9D4 predecessor: public const STABLE_RELEASE = '2.2.629';
    // 9D3 predecessor marker: 2.2.627 certified Stable; 2.2.628 independent certification.
    // 9D2 predecessor: public const SOURCE_RELEASE = '2.2.626';
    // 9D2 predecessor: public const STABLE_RELEASE = '2.2.627';
    // 9D1 predecessor: public const SOURCE_RELEASE = '2.2.625';
    // 9D1 predecessor: public const STABLE_RELEASE = '2.2.626';

    // 9D0B historic: public const SOURCE_RELEASE = '2.2.624';
    // 9D0B historic: public const STABLE_RELEASE = '2.2.625';

    // 9CY predecessor marker: public const SOURCE_RELEASE = '2.2.620';
    // 9CX predecessor marker: public const STABLE_RELEASE = '2.2.620';

    // 9CW predecessor marker: public const SOURCE_RELEASE = '2.2.618';
    // 9CW predecessor marker: public const STABLE_RELEASE = '2.2.619';

    // 9CV predecessor marker: public const SOURCE_RELEASE = '2.2.617';
    // 9CV predecessor marker: public const STABLE_RELEASE = '2.2.618';

    // 9CU predecessor markers: preserve the certified 9CT build.
    // public const SOURCE_RELEASE = '2.2.616';
    // public const STABLE_RELEASE = '2.2.617';

    // 9CT historical predecessor markers retained for validated 2.2.616.
    // public const SOURCE_RELEASE = '2.2.615';
    // public const STABLE_RELEASE = '2.2.616';

    // 9CS historical predecessor markers retained for validated 2.2.615.
    // public const SOURCE_RELEASE = '2.2.614';
    // public const STABLE_RELEASE = '2.2.615';

    // 9CQ historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.613';
    // public const STABLE_RELEASE = '2.2.614';

    // 9CP historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.612';
    // public const STABLE_RELEASE = '2.2.613';

    // 9CO historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.611';
    // public const STABLE_RELEASE = '2.2.612';

    // 9CN historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.610';
    // public const STABLE_RELEASE = '2.2.611';

    // 9CM historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.609';
    // public const STABLE_RELEASE = '2.2.610';

    // 9CL historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.608';
    // public const STABLE_RELEASE = '2.2.609';

    // 9CK historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.607';
    // public const STABLE_RELEASE = '2.2.608';

    // 9CJ historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.606';
    // public const STABLE_RELEASE = '2.2.607';

    // 9CI historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.605';
    // public const STABLE_RELEASE = '2.2.606';

    // 9CH historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.604';
    // public const STABLE_RELEASE = '2.2.605';

    // 9CG historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.603';
    // public const STABLE_RELEASE = '2.2.604';

    // 9CF historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.602';
    // public const STABLE_RELEASE = '2.2.603';

    // 9CE historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.601';
    // public const STABLE_RELEASE = '2.2.602';

    // 9CD historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.600';
    // public const STABLE_RELEASE = '2.2.601';

    // 9CC historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.599';
    // public const STABLE_RELEASE = '2.2.600';

    // public const SOURCE_RELEASE = '2.2.598';
    // public const STABLE_RELEASE = '2.2.599';
    // 9CA historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.597';
    // public const STABLE_RELEASE = '2.2.598';
    // 9BZ historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.596';
    // public const STABLE_RELEASE = '2.2.597';
    // 9BY historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.595';
    // public const STABLE_RELEASE = '2.2.596';
    // 9BX historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.594';
    // public const STABLE_RELEASE = '2.2.595';
    // 9BW historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.593';
    // public const STABLE_RELEASE = '2.2.594';
    // 9BV historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.592';
    // public const STABLE_RELEASE = '2.2.593';
    // 9BU historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.591';
    // public const STABLE_RELEASE = '2.2.592';
    // 9BT historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.590';
    // public const STABLE_RELEASE = '2.2.591';
    // 9BS historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.589';
    // public const STABLE_RELEASE = '2.2.590';
    // 9BR historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.588';
    // public const STABLE_RELEASE = '2.2.589';
    // 9BQ historical predecessor markers retained for successor validation.
    // public const SOURCE_RELEASE = '2.2.587';
    // public const STABLE_RELEASE = '2.2.588';
    // 9BP historical predecessor markers retained for the immutable 2.2.587 package.
    // public const SOURCE_RELEASE = '2.2.586';
    // public const STABLE_RELEASE = '2.2.587';
    // 9BQ predecessor marker retained for the 2.2.587 candidate contract.
    // public const STABLE_RELEASE = '2.2.587';
    // 9BP predecessor markers retained for installed 2.2.586 certification.
    // public const SOURCE_RELEASE = '2.2.585';
    // public const STABLE_RELEASE = '2.2.586';
    // 9BO predecessor markers retained for installed 2.2.585 certification.
    // public const SOURCE_RELEASE = '2.2.584';
    // public const STABLE_RELEASE = '2.2.585';
    // 9BN predecessor markers retained for installed 2.2.584 certification.
    // public const SOURCE_RELEASE = '2.2.583';
    // public const STABLE_RELEASE = '2.2.584';
    // 9BM predecessor markers retained for installed 2.2.583 certification.
    // public const SOURCE_RELEASE = '2.2.582';
    // public const STABLE_RELEASE = '2.2.583';
    // 9BL predecessor markers retained for installed 2.2.582 certification.
    // public const SOURCE_RELEASE = '2.2.581';
    // public const STABLE_RELEASE = '2.2.582';
    // 9BK predecessor markers retained for installed 2.2.581 certification.
    // public const SOURCE_RELEASE = '2.2.580';
    // public const STABLE_RELEASE = '2.2.581';
    // 9BJ predecessor markers retained for installed 2.2.580 certification.
    // public const SOURCE_RELEASE = '2.2.579';
    // public const STABLE_RELEASE = '2.2.580';
    // 9BI predecessor markers retained for installed 2.2.579 certification.
    // public const SOURCE_RELEASE = '2.2.578';
    // public const STABLE_RELEASE = '2.2.579';
    // 9BH predecessor markers retained for the installed 2.2.578 Release Manager.
    // SOURCE_RELEASE = '2.2.577'
    // STABLE_RELEASE = '2.2.578'
    // 9BG historical successor marker retained for the installed 2.2.577 package validator.
    // SOURCE_RELEASE = '2.2.576'
    // STABLE_RELEASE = '2.2.577'
    // 9BF historical successor marker retained for the installed 2.2.576 package validator.
    // SOURCE_RELEASE = '2.2.575'
    // STABLE_RELEASE = '2.2.576'
    // 9BE historical successor marker retained for the installed 2.2.575 package validator.
    // SOURCE_RELEASE = '2.2.574'
    // STABLE_RELEASE = '2.2.575'
    // 9BD historical successor marker retained for the installed 2.2.574 package validator.
    // SOURCE_RELEASE = '2.2.573'
    // STABLE_RELEASE = '2.2.574'
    // 9BC historical successor marker retained for the installed 2.2.573 package validator.
    // SOURCE_RELEASE = '2.2.572'
    // STABLE_RELEASE = '2.2.573'
    // 9BB historical successor marker retained for the installed 2.2.572 package validator.
    // SOURCE_RELEASE = '2.2.571'
    // STABLE_RELEASE = '2.2.572'
    // 9BA historical successor marker retained for the installed 2.2.571 package validator.
    // SOURCE_RELEASE = '2.2.570'
    // STABLE_RELEASE = '2.2.571'
    // 9AZ historical successor marker retained for the installed 2.2.570 package validator.
    // SOURCE_RELEASE = '2.2.569'
    // STABLE_RELEASE = '2.2.570'
    // 9AY historical successor marker retained for the installed 2.2.569 package validator.
    // SOURCE_RELEASE = '2.2.568'
    // STABLE_RELEASE = '2.2.569'
    // 9AX historical successor marker retained for the installed 2.2.568 package validator.
    // public const SOURCE_RELEASE = '2.2.567'
    // public const STABLE_RELEASE = '2.2.568'
    // 9AW historical successor marker retained for the installed 2.2.567 package validator.
    // SOURCE_RELEASE = '2.2.566'
    // STABLE_RELEASE = '2.2.567'
    // 9AV historical successor marker retained for the installed 2.2.566 package validator.
    // SOURCE_RELEASE = '2.2.565'
    // STABLE_RELEASE = '2.2.566'
    // 9AU historical successor marker retained for the installed 2.2.565 package validator.
    // SOURCE_RELEASE = '2.2.564'
    // STABLE_RELEASE = '2.2.565'
    // Successor-validation markers retained for installed predecessor validators.
    // SOURCE_RELEASE = '2.2.563'
    // STABLE_RELEASE = '2.2.564'
    // SOURCE_RELEASE = '2.2.562'
    // STABLE_RELEASE = '2.2.563'
    public const STATE_OPTION = 'fieldflow_phase6_stable_release_state';
    public const SNAPSHOT_OPTION = 'fieldflow_phase6_stable_release_snapshot';
    public const GENERAL_PRODUCTION_ENABLED = false;
    public const FULL_OPERATIONAL_PAYLOAD_ENABLED = false;

    public static function matrix(): array {
        $phase5 = Phase5StableRelease::health();
        $phase6a = ClientOnboardingCertification::health();
        $phase6b1 = LicenseTenantFoundation::health();
        $phase6b2 = LicenseInstallationLifecycle::health();
        $phase6b3 = LicenseGovernanceLifecycle::health();
        $phase6b4 = PrivateUpdateLifecycle::health();
        $manifest = ProductionManifest::verify();
        $publication = self::releasePublicationState();

        return [
            '5-STABLE'=>[
                'label'=>'Phase 5 Stable baseline',
                'ready'=>!empty($phase5['stable_certified']) && !empty($phase5['baseline_match']),
                'detail'=>'A baseline de dados, destinos, credenciais e recovery da Fase 5 permanece certificada.',
            ],
            '6A'=>[
                'label'=>'Client Onboarding & Provisioning',
                'ready'=>!empty($phase6a['stable_certified']) && !empty($phase6a['baseline_match']),
                'detail'=>'Onboarding de clientes permanece formalmente fechado e ancorado na Fase 5.',
            ],
            '6B.1'=>[
                'label'=>'Licensing & Tenant Foundation',
                'ready'=>!empty($phase6b1['foundation_certified']) && !empty($phase6b1['foundation_fingerprint_match']),
                'detail'=>'Registry, emissão segura e conectividade da API de licensing certificados.',
            ],
            '6B.2'=>[
                'label'=>'Activation, Identity & Heartbeat',
                'ready'=>!empty($phase6b2['certified'])
                    && !empty($phase6b2['heartbeat_accepted'])
                    && !empty($phase6b2['heartbeat_recent'])
                    && !empty($phase6b2['remote_identity_acknowledged'])
                    && !empty($phase6b2['remote_fingerprint_acknowledged'])
                    && !empty($phase6b2['remote_heartbeat_recorded']),
                'detail'=>'Activation ID, Installation UUID, fingerprint e heartbeat permanecem ligados no Azure.',
            ],
            '6B.3'=>[
                'label'=>'Governance, Rotation & Limits',
                'ready'=>!empty($phase6b3['certified'])
                    && (string)($phase6b3['enforcement_mode'] ?? '') === 'shadow'
                    && empty($phase6b3['runtime_blocking_enabled'])
                    && empty($phase6b3['remote_revocation_enforced']),
                'detail'=>'Governance central certificada com enforcement ainda em SHADOW e runtime sem bloqueio.',
            ],
            '6B.4'=>[
                'label'=>'Private Distribution & Updates',
                'ready'=>!empty($phase6b4['certified'])
                    && (string)($phase6b4['current_channel'] ?? '') === 'stable'
                    && !empty($phase6b4['package_hash_verified'])
                    && empty($phase6b4['forced_auto_updates'])
                    && empty($phase6b4['downgrades_allowed']),
                'detail'=>'Catálogo privado, entitlement, HMAC, SHA-256 e updater WordPress permanecem certificados.',
            ],
            'RELEASE'=>[
                'label'=>'Stable release integrity',
                'ready'=>!empty($manifest['ok'])
                    && defined('FIELDFLOW_VERSION')
                    && FIELDFLOW_VERSION === self::STABLE_RELEASE,
                'detail'=>'Runtime, Stable tag e production manifest correspondem à release final ' . self::STABLE_RELEASE . '.',
            ],
            'PUBLISH'=>[
                'label'=>'WordPress Release Manager publication',
                'ready'=>!empty($publication['matches_release'])
                    && !empty($publication['sha256_valid'])
                    && !empty($publication['wordpress_evidence'])
                    && !empty($publication['catalog_sha_match']),
                'detail'=>'A release final tem recibo durável do Release Manager WordPress e o SHA-256 coincide com o catálogo privado.',
            ],
        ];
    }

    public static function health(): array {
        $state = self::state();
        $matrix = self::matrix();
        $allReady = !in_array(false, array_map(static fn($row)=>!empty($row['ready']), $matrix), true);
        $fingerprint = self::currentFingerprint();
        $certified = strtolower((string)($state['baseline_fingerprint_sha256'] ?? ''));
        $match = preg_match('/^[a-f0-9]{64}$/', $certified) === 1 && hash_equals($certified, $fingerprint);
        $stableCertified = !empty($state['stable_certified']) && $match && $allReady;
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'release'=>self::STABLE_RELEASE,
            'source_release'=>self::SOURCE_RELEASE,
            'status'=>$stableCertified ? 'ready' : ($allReady ? 'eligible' : 'attention'),
            'matrix_ready'=>$allReady,
            'stable_certified'=>$stableCertified,
            'certification_state_present'=>!empty($state['stable_certified']) || preg_match('/^[a-f0-9]{64}$/', $certified) === 1,
            'certification_requires_revalidation'=>!empty($state['stable_certified']) && (!$match || !$allReady),
            'baseline_match'=>$match,
            'baseline_fingerprint_sha256'=>$fingerprint,
            'certified_fingerprint_sha256'=>$certified,
            'certified_at'=>(string)($state['certified_at'] ?? ''),
            'certified_by'=>absint($state['certified_by'] ?? 0),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
            'governance_enforcement_mode'=>'shadow',
            'runtime_blocking_enabled'=>false,
            'forced_auto_updates'=>false,
        ];
    }

    public static function localProbe(bool $refreshPrivateDistribution = false, bool $forceFreshCertification = true): array {
        if ($refreshPrivateDistribution) {
            LicenseInstallationLifecycle::heartbeat(true, 'phase6_stable_probe');
            LicenseGovernanceLifecycle::testCurrentGovernance();
            PrivateUpdateLifecycle::testCurrentDistribution();
        }
        // 9DJ: read-only BO rendering reuses the existing request snapshot.
        // Certification and explicit test actions retain a *forced* fresh manifest
        // and reset all dependent health caches. Never cache a certification gate.
        if ($forceFreshCertification) {
            ProductionManifest::invalidateCache();
            $manifest = ProductionManifest::verify(true);
            LicenseTenantFoundation::resetHealthCache();
            LicenseInstallationLifecycle::resetHealthCache();
            LicenseGovernanceLifecycle::resetHealthCache();
            PrivateUpdateLifecycle::resetHealthCache();
        } else {
            $manifest = ProductionManifest::verify(); // 9DJ read-only admin snapshot
        }

        $matrix = self::matrix();
        $phase6b2 = LicenseInstallationLifecycle::health();
        $phase6b3 = LicenseGovernanceLifecycle::health();
        $phase6b4 = PrivateUpdateLifecycle::health();
        $clientAzure = ClientAzureDelivery::health();
        $prod = ProductionDeliveryGate::health();
        $gov = GovernanceRecovery::health();

        $current = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        $latest = sanitize_text_field((string)($phase6b4['latest_version'] ?? ''));
        $publication = self::releasePublicationState();
        $checks = [
            'phase6_matrix_all_ready'=>!in_array(false, array_map(static fn($row)=>!empty($row['ready']), $matrix), true),
            'production_manifest_valid'=>!empty($manifest['ok']),
            'stable_release_version_match'=>$current === self::STABLE_RELEASE,
            'private_catalog_at_stable_release'=>$latest !== '' && version_compare($latest, self::STABLE_RELEASE, '>='),
            'release_manager_publication_matches_runtime'=>!empty($publication['matches_release'])
                && !empty($publication['sha256_valid'])
                && !empty($publication['wordpress_evidence'])
                && !empty($publication['catalog_sha_match']),
            'installation_identity_preserved'=>!empty($phase6b2['heartbeat_identity_match']) && !empty($phase6b2['heartbeat_fingerprint_match']) && !empty($phase6b2['remote_identity_acknowledged']) && !empty($phase6b2['remote_fingerprint_acknowledged']),
            'activation_binding_preserved'=>!empty($phase6b2['activation_verified']) && !empty($phase6b2['heartbeat_activation_match']) && !empty($phase6b2['domain_binding_match']),
            'governance_shadow_preserved'=>(string)($phase6b3['enforcement_mode'] ?? '') === 'shadow' && empty($phase6b3['runtime_blocking_enabled']) && empty($phase6b3['remote_revocation_enforced']),
            'runtime_blocking_off'=>empty($phase6b3['runtime_blocking_enabled']),
            'remote_revocation_not_runtime_enforced'=>empty($phase6b3['remote_revocation_enforced']),
            'forced_auto_updates_off'=>empty($phase6b4['forced_auto_updates']),
            'downgrade_protection_on'=>empty($phase6b4['downgrades_allowed']),
            'production_enabled_false'=>empty($gov['production_enabled']) && empty($prod['general_production_activation_enabled']),
            'full_operational_payload_off'=>empty($gov['full_operational_payload_enabled']) && empty($prod['full_operational_payload_delivery_enabled']),
            'client_azure_shadow_preserved'=>(string)($clientAzure['mode'] ?? '') === 'shadow',
            'client_azure_running_zero'=>(int)($clientAzure['stats']['running'] ?? 0) === 0,
            'client_azure_dead_letters_zero'=>(int)($clientAzure['stats']['dead'] ?? 0) === 0,
        ];

        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'matrix'=>$matrix,
            'manifest'=>[
                'ok'=>!empty($manifest['ok']),
                'version'=>(string)($manifest['manifest_version'] ?? ''),
                'aggregate_sha256'=>(string)($manifest['actual_aggregate_sha256'] ?? ''),
                'file_count'=>(int)($manifest['actual_file_count'] ?? 0),
            ],
            'client_azure'=>[
                'mode'=>(string)($clientAzure['mode'] ?? ''),
                'pending'=>(int)($clientAzure['stats']['pending'] ?? 0),
                'running'=>(int)($clientAzure['stats']['running'] ?? 0),
                'dead'=>(int)($clientAzure['stats']['dead'] ?? 0),
            ],
            'health'=>self::health(),
        ];
    }

    public static function revalidateCertificationChain(int $actorId = 0): array {
        ProductionManifest::invalidateCache();
        $manifest = ProductionManifest::verify(true);
        if (empty($manifest['ok'])) {
            return ['ok'=>false,'stage'=>'manifest','message'=>'O manifesto da release instalada não está íntegro.'];
        }
        $runtime = defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '';
        if ($runtime !== self::STABLE_RELEASE) {
            return ['ok'=>false,'stage'=>'version','message'=>'A revalidação exige a release ' . self::STABLE_RELEASE . '.'];
        }

        // 9BR: 6B.1 is part of the certification chain. Historically this method
        // started at 6B.2, so any Foundation drift could leave the whole chain
        // apparently uncertified with no automatic recovery path.
        LicenseTenantFoundation::resetHealthCache();
        $b1Probe = LicenseTenantFoundation::localProbe();
        if (empty($b1Probe['ok'])) {
            $remoteChecks = ['remote_connectivity_probe_ok','remote_probe_host_matches','remote_probe_https','remote_probe_outbound_policy_ok','remote_probe_http_reachable','remote_probe_route_reachable','remote_probe_shared_secret_accepted'];
            $failedBefore = self::failedChecks((array)($b1Probe['checks'] ?? []));
            if (array_intersect($remoteChecks, $failedBefore)) {
                $remote = LicenseTenantFoundation::probeRemoteApi();
                if (empty($remote['ok'])) {
                    return ['ok'=>false,'stage'=>'6B.1-remote','message'=>'A 6B.1 precisa de revalidar a API remota: ' . sanitize_text_field((string)($remote['message'] ?? 'probe falhou.')),'details'=>$remote,'failed_checks'=>$failedBefore];
                }
                LicenseTenantFoundation::resetHealthCache();
                $b1Probe = LicenseTenantFoundation::localProbe();
            }
        }
        if (empty($b1Probe['ok'])) {
            $failed = self::failedChecks((array)($b1Probe['checks'] ?? []));
            return ['ok'=>false,'stage'=>'6B.1-revalidate','message'=>'A 6B.1 não passou a revalidação: ' . implode(', ', $failed) . '.','details'=>$b1Probe,'failed_checks'=>$failed];
        }
        $b1 = LicenseTenantFoundation::certifyFoundation($actorId);
        if (empty($b1['ok'])) {
            $failed = self::failedChecks((array)($b1['checks'] ?? []));
            return ['ok'=>false,'stage'=>'6B.1-certify','message'=>'A 6B.1 não pôde ser recertificada: ' . implode(', ', $failed) . '.','details'=>$b1,'failed_checks'=>$failed];
        }

        $heartbeat = LicenseInstallationLifecycle::heartbeat(true, 'phase6_chain_revalidation');
        if (empty($heartbeat['accepted'])) {
            return ['ok'=>false,'stage'=>'6B.2-heartbeat','message'=>sanitize_text_field((string)($heartbeat['message'] ?? 'Heartbeat 6B.2 não aceite.'))];
        }
        $b2 = LicenseInstallationLifecycle::certify($actorId);
        if (empty($b2['ok'])) {
            return ['ok'=>false,'stage'=>'6B.2-certify','message'=>'A 6B.2 não passou a revalidação pós-update.','details'=>$b2];
        }

        $govTest = LicenseGovernanceLifecycle::testCurrentGovernance();
        if (empty($govTest['ok'])) {
            return ['ok'=>false,'stage'=>'6B.3-test','message'=>sanitize_text_field((string)($govTest['message'] ?? 'Governance 6B.3 não validada.'))];
        }
        $b3 = LicenseGovernanceLifecycle::certify($actorId);
        if (empty($b3['ok'])) {
            return ['ok'=>false,'stage'=>'6B.3-certify','message'=>'A 6B.3 não passou a revalidação pós-update.','details'=>$b3];
        }

        $updateTest = PrivateUpdateLifecycle::testCurrentDistribution();
        if (empty($updateTest['ok'])) {
            return ['ok'=>false,'stage'=>'6B.4-test','message'=>sanitize_text_field((string)($updateTest['message'] ?? 'Distribuição privada 6B.4 não validada.'))];
        }
        $b4 = PrivateUpdateLifecycle::certify($actorId);
        if (empty($b4['ok'])) {
            return ['ok'=>false,'stage'=>'6B.4-certify','message'=>'A 6B.4 não passou a revalidação pós-update.','details'=>$b4];
        }

        LicenseInstallationLifecycle::resetHealthCache();
        LicenseGovernanceLifecycle::resetHealthCache();
        PrivateUpdateLifecycle::resetHealthCache();
        $probe = self::localProbe(false);
        GovernanceRecovery::appendLedger('phase6.certification_chain.revalidated', [
            'entity_type'=>'release',
            'entity_ref'=>self::STABLE_RELEASE,
        ], [
            'phase'=>self::PHASE,
            'release'=>self::STABLE_RELEASE,
            'phase6b2_fingerprint'=>(string)(LicenseInstallationLifecycle::health()['certified_fingerprint_sha256'] ?? ''),
            'phase6b3_fingerprint'=>(string)(LicenseGovernanceLifecycle::health()['certified_fingerprint_sha256'] ?? ''),
            'phase6b4_fingerprint'=>(string)(PrivateUpdateLifecycle::health()['certified_fingerprint_sha256'] ?? ''),
            'production_enabled'=>false,
            'runtime_blocking_enabled'=>false,
        ], 'Phase 6 certification chain explicitly revalidated after a licensed private plugin update. No license, activation, governance status or queue item was modified.');
        return ['ok'=>!empty($probe['ok']),'stage'=>'complete','message'=>!empty($probe['ok']) ? 'Cadeia 6B revalidada após o update privado.' : 'A cadeia 6B foi revalidada, mas o fecho Stable ainda tem verificações por concluir.','probe'=>$probe];
    }

    private static function failedChecks(array $checks): array {
        $failed = [];
        foreach ($checks as $key => $ok) {
            if ($ok) continue;
            $failed[] = sanitize_key((string)$key);
        }
        return $failed ?: ['unknown_precondition'];
    }

    public static function certify(int $actorId = 0): array {
        $probe = self::localProbe(true);
        if (empty($probe['ok'])) {
            return ['ok'=>false,'reason'=>'phase6_stable_preconditions_failed','checks'=>$probe['checks'] ?? [],'matrix'=>$probe['matrix'] ?? []];
        }

        $releaseGate = ReleaseGate::run($actorId);
        $releaseNotBlocked = (string)($releaseGate['status'] ?? '') !== 'blocked';
        $checks = array_merge((array)$probe['checks'], [
            'platform_release_gate_not_blocked'=>$releaseNotBlocked,
        ]);
        if (in_array(false, $checks, true)) {
            return ['ok'=>false,'reason'=>'phase6_stable_release_gate_failed','checks'=>$checks,'release_gate'=>$releaseGate,'matrix'=>$probe['matrix'] ?? []];
        }

        $snapshot = self::buildSnapshot((array)($probe['matrix'] ?? []), $releaseGate, (array)($probe['client_azure'] ?? []));
        $fingerprint = self::currentFingerprint();
        $snapshot['baseline_fingerprint_sha256'] = $fingerprint;
        $encoded = wp_json_encode(self::canonicalize($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') return ['ok'=>false,'reason'=>'phase6_stable_snapshot_encode_failed','checks'=>$checks];
        $snapshot['snapshot_sha256'] = hash('sha256', $encoded);

        update_option(self::SNAPSHOT_OPTION, $snapshot, false);
        update_option(self::STATE_OPTION, [
            'stable_certified'=>true,
            'certified_at'=>current_time('mysql', true),
            'certified_by'=>max(0,$actorId),
            'baseline_fingerprint_sha256'=>$fingerprint,
            'snapshot_sha256'=>(string)$snapshot['snapshot_sha256'],
            'release_gate_status'=>(string)($releaseGate['status'] ?? ''),
            'release_gate_score'=>(int)($releaseGate['score'] ?? 0),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ], false);

        GovernanceRecovery::appendLedger('phase6.stable_release.certified', [
            'entity_type'=>'release',
            'entity_ref'=>self::STABLE_RELEASE,
        ], [
            'phase'=>self::PHASE,
            'baseline_fingerprint_sha256'=>$fingerprint,
            'snapshot_sha256'=>(string)$snapshot['snapshot_sha256'],
            'release_gate_status'=>(string)($releaseGate['status'] ?? ''),
            'release_gate_score'=>(int)($releaseGate['score'] ?? 0),
            'client_azure_pending'=>(int)($probe['client_azure']['pending'] ?? 0),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
            'license_enforcement_mode'=>'shadow',
            'runtime_blocking_enabled'=>false,
        ], 'Phase 6 stable baseline certified after private update distribution validation. Production and runtime enforcement remain disabled.');

        return [
            'ok'=>true,
            'reason'=>'phase6_stable_certified',
            'message'=>'FASE 6 STABLE CERTIFICADA. Private updates ficam operacionais; production e runtime enforcement permanecem desligados.',
            'checks'=>$checks,
            'matrix'=>$probe['matrix'] ?? [],
            'snapshot'=>$snapshot,
            'release_gate'=>$releaseGate,
            'health'=>self::health(),
        ];
    }

    public static function reset(): array {
        $before = self::state();
        update_option(self::STATE_OPTION, [
            'stable_certified'=>false,
            'reset_at'=>current_time('mysql', true),
            'reset_by'=>get_current_user_id(),
            'previous_snapshot_sha256'=>(string)($before['snapshot_sha256'] ?? ''),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ], false);
        GovernanceRecovery::appendLedger('phase6.stable_release.reset', [
            'entity_type'=>'release',
            'entity_ref'=>self::STABLE_RELEASE,
        ], [
            'previous_snapshot_sha256'=>(string)($before['snapshot_sha256'] ?? ''),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ], 'Phase 6 stable certification reset. Existing licenses, update releases and installations were preserved.');
        return ['ok'=>true,'reason'=>'phase6_stable_reset'];
    }

    public static function snapshot(): array {
        $snapshot = get_option(self::SNAPSHOT_OPTION, []);
        return is_array($snapshot) ? $snapshot : [];
    }

    public static function currentFingerprint(): string {
        $phase5 = Phase5StableRelease::health();
        $phase6a = ClientOnboardingCertification::health();
        $phase6b1 = LicenseTenantFoundation::health();
        $phase6b2 = LicenseInstallationLifecycle::health();
        $phase6b3 = LicenseGovernanceLifecycle::health();
        $phase6b4 = PrivateUpdateLifecycle::health();
        $manifest = ProductionManifest::verify();
        $publication = self::releasePublicationState();
        $payload = [
            'release'=>self::STABLE_RELEASE,
            'phase5_fingerprint'=>(string)($phase5['certified_fingerprint_sha256'] ?? ''),
            'phase6a_fingerprint'=>(string)($phase6a['certified_fingerprint_sha256'] ?? ''),
            'phase6b1_fingerprint'=>(string)($phase6b1['certified_fingerprint_sha256'] ?? ''),
            'phase6b2_fingerprint'=>(string)($phase6b2['certified_fingerprint_sha256'] ?? ''),
            'phase6b3_fingerprint'=>(string)($phase6b3['certified_fingerprint_sha256'] ?? ''),
            'phase6b4_fingerprint'=>(string)($phase6b4['certified_fingerprint_sha256'] ?? ''),
            'manifest_aggregate_sha256'=>(string)($manifest['actual_aggregate_sha256'] ?? ''),
            'release_package_sha256'=>(string)($publication['sha256'] ?? ''),
            'release_id'=>(string)($publication['release_id'] ?? ''),
            'update_channel'=>(string)($phase6b4['current_channel'] ?? ''),
            'governance_enforcement_mode'=>(string)($phase6b3['enforcement_mode'] ?? ''),
            'runtime_blocking_enabled'=>!empty($phase6b3['runtime_blocking_enabled']),
            'forced_auto_updates'=>!empty($phase6b4['forced_auto_updates']),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ];
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }


    private static function releasePublicationState(): array {
        $state = ReleasePublicationReceipt::current(self::STABLE_RELEASE);
        $version = sanitize_text_field((string)($state['version'] ?? ''));
        $channel = sanitize_key((string)($state['channel'] ?? ''));
        $sha = strtolower(sanitize_text_field((string)($state['sha256'] ?? '')));
        $source = sanitize_key((string)($state['receipt_source'] ?? 'none'));
        $shaValid = preg_match('/^[a-f0-9]{64}$/', $sha) === 1;
        $matchesRelease = $version !== '' && hash_equals(self::STABLE_RELEASE, $version);
        $wordpressEvidence = in_array($source, ['option','network_option','audit_log'], true);

        $distribution = PrivateUpdateLifecycle::health();
        $catalogVersion = sanitize_text_field((string)($distribution['latest_version'] ?? ''));
        $catalogSha = strtolower(sanitize_text_field((string)($distribution['release_sha256'] ?? '')));
        $catalogShaValid = preg_match('/^[a-f0-9]{64}$/', $catalogSha) === 1;
        $catalogVersionMatch = $catalogVersion !== '' && hash_equals(self::STABLE_RELEASE, $catalogVersion);
        $catalogShaMatch = $shaValid && $catalogShaValid && $catalogVersionMatch && hash_equals($sha, $catalogSha);

        return [
            'version'=>$version,
            'channel'=>$channel,
            'sha256'=>$sha,
            'sha256_valid'=>$shaValid,
            'matches_release'=>$matchesRelease,
            'wordpress_evidence'=>$wordpressEvidence,
            'receipt_source'=>$source,
            'catalog_version'=>$catalogVersion,
            'catalog_sha256'=>$catalogSha,
            'catalog_sha_match'=>$catalogShaMatch,
            'release_id'=>sanitize_text_field((string)($state['release_id'] ?? '')),
            'published_at'=>sanitize_text_field((string)($state['published_at'] ?? '')),
            'published_by'=>absint($state['published_by'] ?? 0),
            'publisher_service'=>sanitize_text_field((string)($state['publisher_service'] ?? '')),
        ];
    }

    private static function state(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function buildSnapshot(array $matrix, array $releaseGate, array $clientAzure): array {
        $phase6b2 = LicenseInstallationLifecycle::health();
        $phase6b3 = LicenseGovernanceLifecycle::health();
        $phase6b4 = PrivateUpdateLifecycle::health();
        $manifest = ProductionManifest::verify();
        $publication = self::releasePublicationState();
        return [
            'schema'=>'fieldflow.phase6.stable-baseline.v2',
            'product'=>'FieldFlow',
            'release'=>self::STABLE_RELEASE,
            'source_release'=>self::SOURCE_RELEASE,
            'created_at_utc'=>gmdate('c'),
            'actor_user_id'=>get_current_user_id(),
            'matrix'=>$matrix,
            'installation'=>[
                'installation_uuid'=>(string)($phase6b2['installation_uuid'] ?? ''),
                'installation_fingerprint_sha256'=>(string)($phase6b2['installation_fingerprint_sha256'] ?? ''),
                'domain'=>(string)($phase6b2['current_domain'] ?? ''),
                'heartbeat_last_success_at'=>(string)($phase6b2['last_heartbeat_success_at'] ?? ''),
            ],
            'governance'=>[
                'enforcement_mode'=>(string)($phase6b3['enforcement_mode'] ?? ''),
                'runtime_blocking_enabled'=>!empty($phase6b3['runtime_blocking_enabled']),
                'remote_revocation_enforced'=>!empty($phase6b3['remote_revocation_enforced']),
                'policy_fingerprint_sha256'=>(string)($phase6b3['remote_policy_fingerprint_sha256'] ?? ''),
            ],
            'release_publication'=>[
                'matches_release'=>!empty($publication['matches_release']),
                'channel'=>(string)($publication['channel'] ?? ''),
                'package_sha256'=>(string)($publication['sha256'] ?? ''),
                'release_id'=>(string)($publication['release_id'] ?? ''),
                'published_at'=>(string)($publication['published_at'] ?? ''),
                'published_by'=>(int)($publication['published_by'] ?? 0),
                'publisher_service'=>(string)($publication['publisher_service'] ?? ''),
                'receipt_source'=>(string)($publication['receipt_source'] ?? ''),
                'catalog_sha_match'=>!empty($publication['catalog_sha_match']),
            ],
            'private_updates'=>[
                'channel'=>(string)($phase6b4['current_channel'] ?? ''),
                'latest_version'=>(string)($phase6b4['latest_version'] ?? ''),
                'release_sha256'=>(string)($phase6b4['release_sha256'] ?? ''),
                'package_hash_verified'=>!empty($phase6b4['package_hash_verified']),
                'forced_auto_updates'=>false,
                'downgrades_allowed'=>false,
                'rollback_mode'=>(string)($phase6b4['rollback_mode'] ?? PrivateUpdateLifecycle::ROLLBACK_MODE),
            ],
            'client_azure_queue'=>$clientAzure,
            'release_integrity'=>[
                'manifest_ok'=>!empty($manifest['ok']),
                'manifest_version'=>(string)($manifest['manifest_version'] ?? ''),
                'aggregate_sha256'=>(string)($manifest['actual_aggregate_sha256'] ?? ''),
                'file_count'=>(int)($manifest['actual_file_count'] ?? 0),
                'release_gate_status'=>(string)($releaseGate['status'] ?? ''),
                'release_gate_score'=>(int)($releaseGate['score'] ?? 0),
            ],
            'safety'=>[
                'production_enabled'=>false,
                'full_operational_payload_enabled'=>false,
                'license_enforcement_mode'=>'shadow',
                'runtime_blocking_enabled'=>false,
                'remote_revocation_runtime_enforced'=>false,
                'forced_auto_updates'=>false,
            ],
        ];
    }

    private static function canonicalize($value) {
        if (is_array($value)) {
            if (array_keys($value) !== range(0, count($value)-1)) ksort($value, SORT_STRING);
            foreach ($value as $key=>$item) $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
}
