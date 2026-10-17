<?php
namespace RoutesPro\Cloud;

use RoutesPro\Support\ProductionManifest;
use RoutesPro\Support\ReleaseGate;

if (!defined('ABSPATH')) exit;

/**
 * FieldFlow Phase 5 stable closure gate.
 *
 * This class freezes the certified 5A to 5E control-plane baseline and creates
 * a rollback snapshot. It deliberately does not enable operational delivery.
 * Production activation remains an explicit per-client/per-destination action
 * for the onboarding layer that follows this stable closure.
 */
final class Phase5StableRelease {
    public const VERSION = '1.0.0';
    public const PHASE = '5-STABLE';
    public const STATE_OPTION = 'fieldflow_phase5_stable_release_state';
    public const SNAPSHOT_OPTION = 'fieldflow_phase5_stable_release_snapshot';
    public const SOURCE_RELEASE = '2.2.375';
    public const STABLE_RELEASE = '2.2.376';
    public const GENERAL_PRODUCTION_ENABLED = false;
    public const FULL_OPERATIONAL_PAYLOAD_ENABLED = false;

    public static function matrix(): array {
        $multi = MultiDestinationFoundation::health();
        $clientAzure = ClientAzureDelivery::health();
        $connector = ConnectorFoundation::health();
        $entra = EntraCrossTenantFoundation::status();
        $resource = ResourceAuthorizationFoundation::summary();
        $queue = ConnectorDelivery::health();
        $controlled = ControlledExternalDelivery::health();
        $pilot = SafeLivePilot::health();
        $pilotRows = SafeLivePilot::summary();
        $prod = ProductionDeliveryGate::health();
        $prodRows = ProductionDeliveryGate::summary();
        $cred = CredentialLifecycle::health();
        $credRows = CredentialLifecycle::summary();
        $gov = GovernanceRecovery::health();

        $oneShotCertified = false;
        foreach ($pilotRows as $row) {
            if (!empty($row['one_shot_certified'])) { $oneShotCertified = true; break; }
        }
        $productionReady = false;
        foreach ($prodRows as $row) {
            if (!empty($row['production_ready'])) { $productionReady = true; break; }
        }
        $credentialReady = false;
        foreach ($credRows as $row) {
            if (!empty($row['ready'])) { $credentialReady = true; break; }
        }

        return [
            '5A'=>[
                'label'=>'Multi-Destination Foundation',
                'ready'=>(string)($multi['status'] ?? '') === 'ready' && !empty($multi['registry']['ready']) && !empty($multi['registry']['primary_twt']),
                'detail'=>'Registry, routing deterministico e Azure TWT principal.',
            ],
            '5B'=>[
                'label'=>'Client Azure Relay',
                'ready'=>!empty($clientAzure['table_ready']) && !empty($clientAzure['relay']['configured']) && (int)($clientAzure['stats']['dead'] ?? 0) === 0,
                'detail'=>'Fila dedicada e Relay TWT configurado, sem dead letters.',
            ],
            '5C.1'=>[
                'label'=>'Connector Foundation',
                'ready'=>(string)($connector['status'] ?? '') === 'ready' && (int)($connector['contracts_ready'] ?? 0) === (int)($connector['contracts_total'] ?? -1),
                'detail'=>'Contratos dos conectores externos completos.',
            ],
            '5C.2'=>[
                'label'=>'Entra Cross-Tenant Identity',
                'ready'=>!empty($entra['configured']) && empty($entra['wordpress_client_secret_stored']) && (string)($entra['credential_model'] ?? '') === 'workload_identity_federation',
                'detail'=>'Broker Entra configurado sem client secrets no WordPress.',
            ],
            '5C.3'=>[
                'label'=>'Resource Authorization',
                'ready'=>!empty($resource['control_plane_ready']) && !empty($resource['read_only_probe']) && (int)($resource['authorized'] ?? 0) > 0,
                'detail'=>'Autorizacao read-only de recursos validada; excecoes de visibilidade externas permanecem observaveis sem bloquear a baseline se existir recurso autorizado.',
            ],
            '5C.5'=>[
                'label'=>'External Delivery Queue Foundation',
                'ready'=>!empty($queue['table_ready']) && (string)($queue['mode'] ?? '') === 'shadow' && !empty($queue['kill_switch']) && empty($queue['network_delivery_enabled']) && (int)($queue['stats']['dead'] ?? 0) === 0,
                'detail'=>'Fila e idempotencia prontas, dispatcher operacional bloqueado.',
            ],
            '5C.6'=>[
                'label'=>'Controlled External Delivery',
                'ready'=>!empty($controlled['table_ready']) && !empty($controlled['broker_configured']) && !empty($controlled['certification_only']) && !empty($controlled['signed_receipts_required']) && (int)($controlled['receipt_count'] ?? 0) > 0,
                'detail'=>'Delivery controlado, receipts HMAC e resiliencia certificados.',
            ],
            '5C.7'=>[
                'label'=>'Safe Live Pilot',
                'ready'=>!empty($pilot['safe_projection_only']) && (int)($pilot['synthetic_certified_targets'] ?? 0) > 0 && $oneShotCertified && (int)($pilot['armed_one_shots'] ?? 0) === 0,
                'detail'=>'Target sintetico e one-shot real seguro certificados.',
            ],
            '5C.8'=>[
                'label'=>'Production Delivery Gate',
                'ready'=>$productionReady && (int)($prod['production_ready_gates'] ?? 0) > 0 && empty($prod['general_production_activation_enabled']) && empty($prod['full_operational_payload_delivery_enabled']),
                'detail'=>'Target, policy e queue certificados como Production Ready.',
            ],
            '5D'=>[
                'label'=>'Credential Lifecycle & Secret Governance',
                'ready'=>$credentialReady && (int)($cred['ready_credentials'] ?? 0) > 0 && !empty($cred['key_vault_reference_only']) && empty($cred['secret_values_stored_in_wordpress']) && empty($cred['operational_payload_delivery_enabled']),
                'detail'=>'Key Vault-only, rotacao, expiracao e revogacao certificados.',
            ],
            '5E'=>[
                'label'=>'Governance, Recovery & Final Eligibility',
                'ready'=>!empty($gov['production_eligible']) && !empty($gov['eligibility_fingerprint_match']) && !empty($gov['ledger_chain_valid']) && !empty($gov['global_kill_switch']) && empty($gov['production_enabled']) && empty($gov['full_operational_payload_enabled']),
                'detail'=>'Ledger, replay, recovery e gate final certificados.',
            ],
        ];
    }

    public static function health(): array {
        $state = self::state();
        $matrix = self::matrix();
        $allReady = true;
        foreach ($matrix as $row) if (empty($row['ready'])) { $allReady = false; break; }
        $gov = GovernanceRecovery::health();
        $fingerprint = self::currentFingerprint();
        $certified = strtolower((string)($state['baseline_fingerprint_sha256'] ?? ''));
        $match = preg_match('/^[a-f0-9]{64}$/', $certified) === 1 && hash_equals($certified, $fingerprint);
        $stableCertified = !empty($state['stable_certified']) && $match && $allReady && !empty($gov['production_eligible']);
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'release'=>self::STABLE_RELEASE,
            'status'=>$stableCertified ? 'ready' : ($allReady ? 'eligible' : 'attention'),
            'matrix_ready'=>$allReady,
            'stable_certified'=>$stableCertified,
            'baseline_match'=>$match,
            'baseline_fingerprint_sha256'=>$fingerprint,
            'certified_fingerprint_sha256'=>$certified,
            'certified_at'=>(string)($state['certified_at'] ?? ''),
            'certified_by'=>absint($state['certified_by'] ?? 0),
            'production_eligible'=>!empty($gov['production_eligible']),
            'production_enabled'=>self::GENERAL_PRODUCTION_ENABLED,
            'full_operational_payload_enabled'=>self::FULL_OPERATIONAL_PAYLOAD_ENABLED,
            'source_release'=>self::SOURCE_RELEASE,
            'rollback_package'=>'fieldflow-2.2.375-phase-5e-governance-recovery.zip',
        ];
    }

    public static function localProbe(): array {
        $matrix = self::matrix();
        $manifest = ProductionManifest::verify();
        $gov = GovernanceRecovery::health();
        $checks = [
            'phase5_matrix_all_ready'=>!in_array(false, array_map(static fn($row)=>!empty($row['ready']), $matrix), true),
            'production_manifest_valid'=>!empty($manifest['ok']),
            'phase5e_production_eligible'=>!empty($gov['production_eligible']),
            'phase5e_fingerprint_match'=>!empty($gov['eligibility_fingerprint_match']),
            'governance_ledger_valid'=>!empty($gov['ledger_chain_valid']),
            'connector_shadow_preserved'=>ConnectorDelivery::mode() === 'shadow',
            'connector_kill_switch_on'=>ConnectorDelivery::killSwitch(),
            'governance_kill_switch_on'=>!empty($gov['global_kill_switch']),
            'production_enabled_false'=>self::GENERAL_PRODUCTION_ENABLED === false,
            'full_payload_enabled_false'=>self::FULL_OPERATIONAL_PAYLOAD_ENABLED === false,
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
            'health'=>self::health(),
        ];
    }

    public static function certifyStable(int $actorId = 0): array {
        $probe = self::localProbe();
        if (empty($probe['ok'])) {
            return ['ok'=>false,'reason'=>'phase5_stable_preconditions_failed','checks'=>$probe['checks'],'matrix'=>$probe['matrix']];
        }
        $releaseGate = ReleaseGate::run($actorId);
        $releaseNotBlocked = (string)($releaseGate['status'] ?? '') !== 'blocked';
        $checks = array_merge((array)$probe['checks'], [
            'platform_release_gate_not_blocked'=>$releaseNotBlocked,
            'stable_release_version_match'=>defined('FIELDFLOW_VERSION') && FIELDFLOW_VERSION === self::STABLE_RELEASE,
        ]);
        if (in_array(false, $checks, true)) {
            return ['ok'=>false,'reason'=>'phase5_stable_release_gate_failed','checks'=>$checks,'release_gate'=>$releaseGate,'matrix'=>$probe['matrix']];
        }

        $snapshot = self::buildSnapshot($probe['matrix'], $releaseGate);
        $fingerprint = self::currentFingerprint();
        $snapshot['baseline_fingerprint_sha256'] = $fingerprint;
        $snapshotJson = wp_json_encode(self::canonicalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($snapshotJson) || $snapshotJson === '') {
            return ['ok'=>false,'reason'=>'phase5_stable_snapshot_encode_failed','checks'=>$checks];
        }
        $snapshot['snapshot_sha256'] = hash('sha256', $snapshotJson);
        update_option(self::SNAPSHOT_OPTION, $snapshot, false);
        $state = [
            'stable_certified'=>true,
            'certified_at'=>current_time('mysql', true),
            'certified_by'=>max(0, $actorId),
            'baseline_fingerprint_sha256'=>$fingerprint,
            'snapshot_sha256'=>(string)$snapshot['snapshot_sha256'],
            'release_gate_status'=>(string)($releaseGate['status'] ?? ''),
            'release_gate_score'=>(int)($releaseGate['score'] ?? 0),
            'production_enabled'=>false,
        ];
        update_option(self::STATE_OPTION, $state, false);
        GovernanceRecovery::appendLedger('phase5.stable_release.certified', [
            'entity_type'=>'release',
            'entity_ref'=>self::STABLE_RELEASE,
        ], [
            'phase'=>self::PHASE,
            'baseline_fingerprint_sha256'=>$fingerprint,
            'snapshot_sha256'=>(string)$snapshot['snapshot_sha256'],
            'release_gate_status'=>(string)($releaseGate['status'] ?? ''),
            'release_gate_score'=>(int)($releaseGate['score'] ?? 0),
            'production_enabled'=>false,
        ], 'Phase 5 stable baseline certified. Production activation remains disabled by default.');

        return [
            'ok'=>true,
            'reason'=>'phase5_stable_certified',
            'message'=>'FASE 5 STABLE CERTIFICADA. Production Eligible permanece SIM e Production Enabled permanece NAO.',
            'checks'=>$checks,
            'matrix'=>$probe['matrix'],
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
        ], false);
        GovernanceRecovery::appendLedger('phase5.stable_release.reset', [
            'entity_type'=>'release',
            'entity_ref'=>self::STABLE_RELEASE,
        ], [
            'previous_snapshot_sha256'=>(string)($before['snapshot_sha256'] ?? ''),
            'production_enabled'=>false,
        ], 'Phase 5 stable certification reset. Governance ledger and rollback snapshot were preserved.');
        return ['ok'=>true,'reason'=>'phase5_stable_reset'];
    }

    public static function snapshot(): array {
        $snapshot = get_option(self::SNAPSHOT_OPTION, []);
        return is_array($snapshot) ? $snapshot : [];
    }

    public static function exportPayload(): array {
        return [
            'product'=>'FieldFlow',
            'release'=>self::STABLE_RELEASE,
            'phase'=>self::PHASE,
            'generated_at_utc'=>gmdate('c'),
            'health'=>self::health(),
            'matrix'=>self::matrix(),
            'baseline_snapshot'=>self::snapshot(),
            'rollback'=>[
                'source_release'=>self::SOURCE_RELEASE,
                'package'=>'fieldflow-2.2.375-phase-5e-governance-recovery.zip',
                'preconditions'=>[
                    'ConnectorDelivery mode must be SHADOW',
                    'ConnectorDelivery kill switch must be ON',
                    'Governance global kill switch must be ON',
                    'Production Enabled must be false',
                ],
                'procedure'=>[
                    'Take a database backup and copy wp-content/uploads before rollback.',
                    'Install the 2.2.375 package over 2.2.376 without uninstalling FieldFlow.',
                    'Run the 5E local diagnostic and confirm the governance fingerprint state.',
                    'Keep all production activation disabled until a new stable certification is completed.',
                ],
            ],
        ];
    }

    public static function currentFingerprint(): string {
        $gov = GovernanceRecovery::health();
        $prod = ProductionDeliveryGate::health();
        $cred = CredentialLifecycle::health();
        $payload = [
            'release'=>self::STABLE_RELEASE,
            'phase5e_fingerprint'=>(string)($gov['eligibility_fingerprint_sha256'] ?? ''),
            'phase5e_certified_fingerprint'=>(string)($gov['certified_fingerprint_sha256'] ?? ''),
            'phase5e_match'=>!empty($gov['eligibility_fingerprint_match']),
            'production_eligible'=>!empty($gov['production_eligible']),
            '5c8_ready_gates'=>(int)($prod['production_ready_gates'] ?? 0),
            '5d_ready_credentials'=>(int)($cred['ready_credentials'] ?? 0),
            'connector_mode'=>ConnectorDelivery::mode(),
            'connector_kill_switch'=>ConnectorDelivery::killSwitch(),
            'governance_kill_switch'=>!empty($gov['global_kill_switch']),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ];
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function state(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function buildSnapshot(array $matrix, array $releaseGate): array {
        $gov = GovernanceRecovery::health();
        $prod = ProductionDeliveryGate::health();
        $cred = CredentialLifecycle::health();
        $manifest = ProductionManifest::verify();
        return [
            'schema'=>'fieldflow.phase5.stable-baseline.v1',
            'product'=>'FieldFlow',
            'release'=>self::STABLE_RELEASE,
            'source_release'=>self::SOURCE_RELEASE,
            'created_at_utc'=>gmdate('c'),
            'actor_user_id'=>get_current_user_id(),
            'matrix'=>$matrix,
            'phase5e'=>[
                'production_eligible'=>!empty($gov['production_eligible']),
                'fingerprint_sha256'=>(string)($gov['eligibility_fingerprint_sha256'] ?? ''),
                'certified_fingerprint_sha256'=>(string)($gov['certified_fingerprint_sha256'] ?? ''),
                'fingerprint_match'=>!empty($gov['eligibility_fingerprint_match']),
                'ledger_chain_valid'=>!empty($gov['ledger_chain_valid']),
                'ledger_entries'=>(int)($gov['ledger_entries'] ?? 0),
                'dead_letter_count'=>(int)($gov['dead_letter_count'] ?? 0),
                'stale_running_count'=>(int)($gov['stale_running_count'] ?? 0),
            ],
            'production_gate'=>[
                'ready_gates'=>(int)($prod['production_ready_gates'] ?? 0),
                'general_activation_enabled'=>false,
                'full_operational_payload_enabled'=>false,
            ],
            'credentials'=>[
                'ready_credentials'=>(int)($cred['ready_credentials'] ?? 0),
                'revoked_credentials'=>(int)($cred['revoked_credentials'] ?? 0),
                'rotation_detected_credentials'=>(int)($cred['rotation_detected_credentials'] ?? 0),
            ],
            'release_integrity'=>[
                'manifest_ok'=>!empty($manifest['ok']),
                'manifest_version'=>(string)($manifest['manifest_version'] ?? ''),
                'aggregate_sha256'=>(string)($manifest['actual_aggregate_sha256'] ?? ''),
                'file_count'=>(int)($manifest['actual_file_count'] ?? 0),
                'release_gate_status'=>(string)($releaseGate['status'] ?? ''),
                'release_gate_score'=>(int)($releaseGate['score'] ?? 0),
            ],
            'safety'=>[
                'connector_mode'=>ConnectorDelivery::mode(),
                'connector_kill_switch'=>ConnectorDelivery::killSwitch(),
                'governance_kill_switch'=>!empty($gov['global_kill_switch']),
                'production_enabled'=>false,
                'full_operational_payload_enabled'=>false,
            ],
        ];
    }

    private static function canonicalize($value) {
        if (is_array($value)) {
            if (array_keys($value) !== range(0, count($value) - 1)) ksort($value, SORT_STRING);
            foreach ($value as $key=>$item) $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
}
