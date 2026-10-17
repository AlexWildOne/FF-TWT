<?php
namespace RoutesPro\Cloud;

use RoutesPro\Support\ProductionManifest;

if (!defined('ABSPATH')) exit;

/**
 * Phase 6A formal closure.
 *
 * Freezes evidence that the guided onboarding can take a real client from
 * context to Production Ready without enabling production delivery.
 */
final class ClientOnboardingCertification {
    public const VERSION = '1.0.0';
    public const PHASE = '6A-STABLE';
    public const SOURCE_RELEASE = '2.2.378';
    public const STABLE_RELEASE = '2.2.379';
    public const STATE_OPTION = 'fieldflow_phase6a_stable_state';
    public const SNAPSHOT_OPTION = 'fieldflow_phase6a_stable_snapshot';
    public const GENERAL_PRODUCTION_ENABLED = false;
    public const FULL_OPERATIONAL_PAYLOAD_ENABLED = false;

    public static function matrix(): array {
        $foundation = ClientOnboardingFoundation::health();
        $stable = Phase5StableRelease::health();
        $plans = ClientOnboardingFoundation::listPlans();

        $preflightReady = false;
        $shadowProvisioned = false;
        $productionReady = false;
        $twtProductionReady = false;
        $productionReadyCount = 0;

        foreach ($plans as $plan) {
            $readiness = ClientOnboardingFoundation::readiness($plan, false);
            $saved = is_array($plan['readiness'] ?? null) ? $plan['readiness'] : [];
            if (!empty($saved['preflight_ok'])) $preflightReady = true;
            if ((int)($plan['destination_id'] ?? 0) > 0 && (string)($plan['safe_mode'] ?? '') === 'shadow') $shadowProvisioned = true;
            if (!empty($readiness['production_ready'])) {
                $productionReady = true;
                $productionReadyCount++;
                if ((string)($plan['template_key'] ?? '') === 'twt_managed') $twtProductionReady = true;
            }
        }

        $gov = GovernanceRecovery::health();
        return [
            '6A.1'=>[
                'label'=>'Onboarding Foundation',
                'ready'=>(string)($foundation['status'] ?? '') === 'ready' && !empty($foundation['table_ready']) && (int)($foundation['templates'] ?? 0) >= 7,
                'detail'=>'Tabela, catálogo de templates e baseline da Fase 5 disponíveis.',
            ],
            '6A.2'=>[
                'label'=>'Pre-flight seguro',
                'ready'=>$preflightReady,
                'detail'=>'Existe pelo menos um onboarding com pré-flight validado sem abrir produção.',
            ],
            '6A.3'=>[
                'label'=>'Provisioning em Shadow',
                'ready'=>$shadowProvisioned,
                'detail'=>'Existe pelo menos um plano provisionado em Shadow com destino associado.',
            ],
            '6A.4'=>[
                'label'=>'Readiness por cliente',
                'ready'=>$productionReady && $twtProductionReady && $productionReadyCount > 0,
                'detail'=>'Existe um cliente TWT Cloud Production Ready, com identidade, recurso, credencial e delivery consolidados.',
            ],
            '6A.5'=>[
                'label'=>'Safety & activation separation',
                'ready'=>!empty($foundation['connector_shadow_preserved'])
                    && !empty($foundation['connector_kill_switch_on'])
                    && !empty($foundation['governance_kill_switch_on'])
                    && empty($foundation['production_enabled'])
                    && empty($foundation['full_operational_payload_enabled'])
                    && !empty($gov['production_eligible'])
                    && empty($gov['production_enabled']),
                'detail'=>'Production Ready continua separado de Production Enabled, com Shadow e kill switches preservados.',
            ],
            '5-STABLE'=>[
                'label'=>'Fase 5 Stable baseline',
                'ready'=>!empty($stable['stable_certified']) && !empty($stable['baseline_match']),
                'detail'=>'A 6A permanece ancorada na baseline certificada da Fase 5.',
            ],
        ];
    }

    public static function health(): array {
        $matrix = self::matrix();
        $allReady = !in_array(false, array_map(static fn($row)=>!empty($row['ready']), $matrix), true);
        $state = self::state();
        $fingerprint = self::currentFingerprint();
        $certified = strtolower((string)($state['baseline_fingerprint_sha256'] ?? ''));
        $match = preg_match('/^[a-f0-9]{64}$/', $certified) === 1 && hash_equals($certified, $fingerprint);
        $certifiedOk = $allReady && $match && !empty($state['stable_certified']);
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'release'=>self::STABLE_RELEASE,
            'status'=>$certifiedOk ? 'ready' : ($allReady ? 'eligible' : 'attention'),
            'matrix_ready'=>$allReady,
            'stable_certified'=>$certifiedOk,
            'baseline_match'=>$match,
            'baseline_fingerprint_sha256'=>$fingerprint,
            'certified_fingerprint_sha256'=>$certified,
            'certified_at'=>(string)($state['certified_at'] ?? ''),
            'certified_by'=>absint($state['certified_by'] ?? 0),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ];
    }

    public static function localProbe(): array {
        $matrix = self::matrix();
        $manifest = ProductionManifest::verify();
        $foundation = ClientOnboardingFoundation::health();
        $checks = [
            'phase6a_matrix_all_ready'=>!in_array(false, array_map(static fn($row)=>!empty($row['ready']), $matrix), true),
            'production_manifest_valid'=>!empty($manifest['ok']),
            'phase5_stable_certified'=>!empty($foundation['phase5_stable_certified']),
            'phase5_baseline_match'=>!empty($foundation['phase5_baseline_match']),
            'connector_shadow_preserved'=>!empty($foundation['connector_shadow_preserved']),
            'connector_kill_switch_on'=>!empty($foundation['connector_kill_switch_on']),
            'governance_kill_switch_on'=>!empty($foundation['governance_kill_switch_on']),
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

    public static function certify(int $actorId = 0): array {
        $probe = self::localProbe();
        $checks = (array)($probe['checks'] ?? []);
        $checks['stable_release_version_match'] = defined('FIELDFLOW_VERSION') && FIELDFLOW_VERSION === self::STABLE_RELEASE;
        if (in_array(false, $checks, true)) {
            return ['ok'=>false,'reason'=>'phase6a_stable_preconditions_failed','checks'=>$checks,'matrix'=>$probe['matrix'] ?? []];
        }

        $evidencePlan = self::evidencePlan();
        if (!$evidencePlan) {
            $checks['evidence_plan_present'] = false;
            return ['ok'=>false,'reason'=>'phase6a_evidence_plan_missing','checks'=>$checks,'matrix'=>$probe['matrix'] ?? []];
        }
        $checks['evidence_plan_present'] = true;
        $fingerprint = self::currentFingerprint($evidencePlan);
        $snapshot = self::buildSnapshot((array)$probe['matrix'], $evidencePlan);
        $snapshot['baseline_fingerprint_sha256'] = $fingerprint;
        $encoded = wp_json_encode(self::canonicalize($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || $encoded === '') return ['ok'=>false,'reason'=>'phase6a_snapshot_encode_failed','checks'=>$checks];
        $snapshot['snapshot_sha256'] = hash('sha256', $encoded);

        update_option(self::SNAPSHOT_OPTION, $snapshot, false);
        update_option(self::STATE_OPTION, [
            'stable_certified'=>true,
            'certified_at'=>current_time('mysql', true),
            'certified_by'=>max(0, $actorId),
            'baseline_fingerprint_sha256'=>$fingerprint,
            'evidence_plan_uuid'=>(string)($evidencePlan['plan_uuid'] ?? ''),
            'snapshot_sha256'=>(string)$snapshot['snapshot_sha256'],
            'production_enabled'=>false,
        ], false);

        GovernanceRecovery::appendLedger('phase6a.stable.certified', [
            'entity_type'=>'release',
            'entity_ref'=>self::STABLE_RELEASE,
        ], [
            'phase'=>self::PHASE,
            'baseline_fingerprint_sha256'=>$fingerprint,
            'evidence_plan_uuid'=>(string)($evidencePlan['plan_uuid'] ?? ''),
            'snapshot_sha256'=>(string)$snapshot['snapshot_sha256'],
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ], 'Phase 6A formal closure certified. Production activation remains explicitly disabled.');

        return [
            'ok'=>true,
            'reason'=>'phase6a_stable_certified',
            'message'=>'Fase 6A formalmente certificada. Production Ready permanece separado de Production Enabled.',
            'checks'=>$checks,
            'matrix'=>$probe['matrix'] ?? [],
            'snapshot'=>$snapshot,
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
        GovernanceRecovery::appendLedger('phase6a.stable.reset', [
            'entity_type'=>'release',
            'entity_ref'=>self::STABLE_RELEASE,
        ], [
            'previous_snapshot_sha256'=>(string)($before['snapshot_sha256'] ?? ''),
            'production_enabled'=>false,
        ], 'Phase 6A formal certification reset. Snapshot retained for audit.');
        return ['ok'=>true,'reason'=>'phase6a_stable_reset'];
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
            'safety'=>[
                'production_enabled'=>false,
                'full_operational_payload_enabled'=>false,
                'activation_gate_separate'=>true,
            ],
            'next_phase'=>[
                'phase'=>'6B',
                'name'=>'Licensing & Tenant Management',
                'enforcement_enabled'=>false,
            ],
        ];
    }

    public static function currentFingerprint(array $evidencePlan = []): string {
        $stable = Phase5StableRelease::health();
        $foundation = ClientOnboardingFoundation::health();
        if (!$evidencePlan) $evidencePlan = self::evidencePlan();
        $evidence = [];
        if ($evidencePlan) {
            $ready = ClientOnboardingFoundation::readiness($evidencePlan, false);
            $evidence = [
                'plan_uuid'=>(string)($evidencePlan['plan_uuid'] ?? ''),
                'client_id'=>(int)($evidencePlan['client_id'] ?? 0),
                'project_id'=>(int)($evidencePlan['project_id'] ?? 0),
                'template_key'=>(string)($evidencePlan['template_key'] ?? ''),
                'destination_id'=>(int)($evidencePlan['destination_id'] ?? 0),
                'destination_uuid'=>(string)($ready['destination_uuid'] ?? ''),
                'event_pattern'=>(string)($evidencePlan['event_pattern'] ?? ''),
                'hourly_limit'=>(int)($evidencePlan['hourly_limit'] ?? 0),
                'safe_mode'=>(string)($evidencePlan['safe_mode'] ?? ''),
                'identity_ready'=>!empty($ready['identity_ready']),
                'resource_ready'=>!empty($ready['resource_ready']),
                'credential_ready'=>!empty($ready['credential_ready']),
                'delivery_ready'=>!empty($ready['delivery_ready']),
                'production_ready'=>!empty($ready['production_ready']),
            ];
        }
        $payload = [
            'release'=>self::STABLE_RELEASE,
            'phase5_certified_fingerprint'=>(string)($stable['certified_fingerprint_sha256'] ?? ''),
            'phase5_baseline_match'=>!empty($stable['baseline_match']),
            'templates'=>(int)($foundation['templates'] ?? 0),
            'evidence_plan'=>$evidence,
            'connector_shadow_preserved'=>!empty($foundation['connector_shadow_preserved']),
            'connector_kill_switch_on'=>!empty($foundation['connector_kill_switch_on']),
            'governance_kill_switch_on'=>!empty($foundation['governance_kill_switch_on']),
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ];
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function evidencePlan(): array {
        $state = self::state();
        $pinnedUuid = trim((string)($state['evidence_plan_uuid'] ?? ''));
        $eligible = [];
        foreach (ClientOnboardingFoundation::listPlans() as $plan) {
            if ((string)($plan['template_key'] ?? '') !== 'twt_managed') continue;
            $ready = ClientOnboardingFoundation::readiness($plan, false);
            if (empty($ready['production_ready'])) continue;
            if ($pinnedUuid !== '' && hash_equals($pinnedUuid, (string)($plan['plan_uuid'] ?? ''))) return $plan;
            $eligible[] = $plan;
        }
        if (!$eligible) return [];
        usort($eligible, static function($a, $b): int {
            $idCmp = ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
            if ($idCmp !== 0) return $idCmp;
            return strcmp((string)($a['plan_uuid'] ?? ''), (string)($b['plan_uuid'] ?? ''));
        });
        return $eligible[0];
    }

    private static function buildSnapshot(array $matrix, array $evidencePlan): array {
        $foundation = ClientOnboardingFoundation::health();
        $manifest = ProductionManifest::verify();
        $plans = [];
        foreach (ClientOnboardingFoundation::listPlans() as $plan) {
            $ready = ClientOnboardingFoundation::readiness($plan, false);
            if (empty($ready['production_ready'])) continue;
            $plans[] = [
                'plan_uuid'=>(string)($plan['plan_uuid'] ?? ''),
                'client_id'=>(int)($plan['client_id'] ?? 0),
                'project_id'=>(int)($plan['project_id'] ?? 0),
                'template_key'=>(string)($plan['template_key'] ?? ''),
                'destination_uuid'=>(string)($ready['destination_uuid'] ?? ''),
                'production_ready'=>true,
                'production_enabled'=>false,
            ];
        }
        return [
            'schema'=>'fieldflow.phase6a.stable-baseline.v1',
            'product'=>'FieldFlow',
            'release'=>self::STABLE_RELEASE,
            'source_release'=>self::SOURCE_RELEASE,
            'created_at_utc'=>gmdate('c'),
            'actor_user_id'=>get_current_user_id(),
            'matrix'=>$matrix,
            'summary'=>[
                'plans_total'=>(int)($foundation['plans_total'] ?? 0),
                'plans_staged'=>(int)($foundation['plans_staged'] ?? 0),
                'plans_production_ready'=>(int)($foundation['plans_production_ready'] ?? 0),
                'templates'=>(int)($foundation['templates'] ?? 0),
            ],
            'evidence_plan_uuid'=>(string)($evidencePlan['plan_uuid'] ?? ''),
            'production_ready_plans'=>$plans,
            'release_integrity'=>[
                'manifest_ok'=>!empty($manifest['ok']),
                'manifest_version'=>(string)($manifest['manifest_version'] ?? ''),
                'aggregate_sha256'=>(string)($manifest['actual_aggregate_sha256'] ?? ''),
                'file_count'=>(int)($manifest['actual_file_count'] ?? 0),
            ],
            'safety'=>[
                'connector_shadow_preserved'=>!empty($foundation['connector_shadow_preserved']),
                'connector_kill_switch_on'=>!empty($foundation['connector_kill_switch_on']),
                'governance_kill_switch_on'=>!empty($foundation['governance_kill_switch_on']),
                'production_enabled'=>false,
                'full_operational_payload_enabled'=>false,
            ],
        ];
    }

    private static function state(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function canonicalize($value) {
        if (is_array($value)) {
            if (array_keys($value) !== range(0, count($value) - 1)) ksort($value, SORT_STRING);
            foreach ($value as $key=>$item) $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
}
