<?php
namespace RoutesPro\Cloud;

if (!defined('ABSPATH')) exit;

/**
 * Phase 6A, Client Onboarding & Provisioning Foundation.
 *
 * Turns the certified Phase 5 control plane into a guided, per-client setup
 * workflow. It only stages configuration and safe probes. General production
 * delivery remains disabled and no secret values are accepted by WordPress.
 */
final class ClientOnboardingFoundation {
    public const VERSION = '1.0.0';
    public const PHASE = '6A';
    public const GENERAL_PRODUCTION_ENABLED = false;
    public const FULL_OPERATIONAL_PAYLOAD_ENABLED = false;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_client_onboarding_plans';
    }

    public static function tableReady(): bool {
        global $wpdb;
        $table = self::table();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function templates(): array {
        return [
            'twt_managed' => [
                'label'=>'TWT Cloud, recomendado',
                'description'=>'Usa a infraestrutura Azure TWT já certificada. Sem configuração Azure do cliente.',
                'type_key'=>'twt_azure',
                'auth_strategy'=>'none',
                'needs_endpoint'=>false,
                'needs_secret'=>false,
                'needs_tenant'=>false,
                'resource_kind'=>'twt_managed',
                'default_scope'=>'',
            ],
            'azure_sql_external' => [
                'label'=>'Azure SQL do cliente',
                'description'=>'Identidade Microsoft Entra cross-tenant, probe read-only e preparação para data delivery.',
                'type_key'=>'data_warehouse',
                'auth_strategy'=>'entra_cross_tenant',
                'needs_endpoint'=>true,
                'needs_secret'=>false,
                'needs_tenant'=>true,
                'resource_kind'=>'azure_sql',
                'default_scope'=>'https://database.windows.net/.default',
            ],
            'powerbi_external' => [
                'label'=>'Power BI do cliente',
                'description'=>'Workspace Power BI com consentimento Microsoft Entra e autorização read-only.',
                'type_key'=>'client_powerbi',
                'auth_strategy'=>'entra_cross_tenant',
                'needs_endpoint'=>true,
                'needs_secret'=>false,
                'needs_tenant'=>true,
                'resource_kind'=>'powerbi_workspace',
                'default_scope'=>'https://analysis.windows.net/powerbi/api/.default',
            ],
            'fabric_external' => [
                'label'=>'Microsoft Fabric do cliente',
                'description'=>'Workspace Fabric com consentimento Microsoft Entra e probe REST read-only.',
                'type_key'=>'client_powerbi',
                'auth_strategy'=>'entra_cross_tenant',
                'needs_endpoint'=>true,
                'needs_secret'=>false,
                'needs_tenant'=>true,
                'resource_kind'=>'fabric_workspace',
                'default_scope'=>'https://api.fabric.microsoft.com/.default',
            ],
            'external_api' => [
                'label'=>'API externa',
                'description'=>'Endpoint HTTPS com autenticação Key Vault ou Microsoft Entra, preparado para a gate 5C.8.',
                'type_key'=>'external_api',
                'auth_strategy'=>'key_vault_reference',
                'needs_endpoint'=>true,
                'needs_secret'=>false,
                'needs_tenant'=>false,
                'resource_kind'=>'https_api',
                'default_scope'=>'',
            ],
            'webhook' => [
                'label'=>'Webhook',
                'description'=>'Webhook HTTPS com credencial exclusivamente por referência Key Vault.',
                'type_key'=>'webhook',
                'auth_strategy'=>'key_vault_reference',
                'needs_endpoint'=>true,
                'needs_secret'=>false,
                'needs_tenant'=>false,
                'resource_kind'=>'https_webhook',
                'default_scope'=>'',
            ],
            'client_azure_relay' => [
                'label'=>'Azure do cliente, Relay',
                'description'=>'Replica via Relay TWT 5B, sem ligação direta WordPress para o Azure do cliente.',
                'type_key'=>'client_azure',
                'auth_strategy'=>'key_vault_reference',
                'needs_endpoint'=>true,
                'needs_secret'=>true,
                'needs_tenant'=>false,
                'resource_kind'=>'client_azure_relay',
                'default_scope'=>'',
            ],
        ];
    }

    public static function health(): array {
        $stable = class_exists('\\RoutesPro\\Cloud\\Phase5StableRelease') ? Phase5StableRelease::health() : [];
        $delivery = class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') ? ConnectorDelivery::health() : [];
        $gov = class_exists('\\RoutesPro\\Cloud\\GovernanceRecovery') ? GovernanceRecovery::health() : [];
        $summary = self::summary();
        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'status'=>(!empty($stable['stable_certified']) && self::tableReady()) ? 'ready' : 'attention',
            'phase5_stable_certified'=>!empty($stable['stable_certified']),
            'phase5_baseline_match'=>!empty($stable['baseline_match']),
            'table_ready'=>self::tableReady(),
            'templates'=>count(self::templates()),
            'plans_total'=>(int)$summary['total'],
            'plans_staged'=>(int)$summary['staged'],
            'plans_attention'=>(int)$summary['attention'],
            'plans_production_ready'=>(int)$summary['production_ready'],
            'connector_shadow_preserved'=>(string)($delivery['mode'] ?? '') === 'shadow',
            'connector_kill_switch_on'=>!empty($delivery['kill_switch']),
            'governance_kill_switch_on'=>!empty($gov['global_kill_switch']),
            'production_enabled'=>self::GENERAL_PRODUCTION_ENABLED,
            'full_operational_payload_enabled'=>self::FULL_OPERATIONAL_PAYLOAD_ENABLED,
            'secret_values_accepted_by_wordpress'=>false,
        ];
    }

    public static function localProbe(): array {
        $health = self::health();
        $checks = [
            'phase5_stable_certified'=>!empty($health['phase5_stable_certified']),
            'phase5_baseline_match'=>!empty($health['phase5_baseline_match']),
            'onboarding_table_ready'=>!empty($health['table_ready']),
            'connector_shadow_preserved'=>!empty($health['connector_shadow_preserved']),
            'connector_kill_switch_on'=>!empty($health['connector_kill_switch_on']),
            'governance_kill_switch_on'=>!empty($health['governance_kill_switch_on']),
            'production_enabled_false'=>empty($health['production_enabled']),
            'full_payload_enabled_false'=>empty($health['full_operational_payload_enabled']),
            'secret_values_rejected'=>empty($health['secret_values_accepted_by_wordpress']),
            'template_catalog_ready'=>(int)$health['templates'] >= 7,
        ];
        return ['ok'=>!in_array(false, $checks, true), 'checks'=>$checks, 'health'=>$health];
    }

    public static function createPlan(array $input) {
        global $wpdb;
        if (!current_user_can('routespro_manage')) return new \WP_Error('fieldflow_6a_permission_denied', 'Sem permissões para criar onboarding.');
        if (!self::tableReady()) return new \WP_Error('fieldflow_6a_table_missing', 'A tabela de onboarding 6A ainda não está disponível.');
        $stable = Phase5StableRelease::health();
        if (empty($stable['stable_certified']) || empty($stable['baseline_match'])) {
            return new \WP_Error('fieldflow_6a_phase5_not_stable', 'A baseline Stable da Fase 5 tem de estar certificada e MATCH antes de iniciar a Fase 6A.');
        }

        $normalised = self::normaliseInput($input);
        if (is_wp_error($normalised)) return $normalised;
        $now = current_time('mysql', true);
        $ok = $wpdb->insert(self::table(), [
            'plan_uuid'=>wp_generate_uuid4(),
            'client_id'=>(int)$normalised['client_id'],
            'project_id'=>(int)$normalised['project_id'] > 0 ? (int)$normalised['project_id'] : null,
            'template_key'=>(string)$normalised['template_key'],
            'destination_type'=>(string)$normalised['type_key'],
            'destination_id'=>null,
            'routing_rule_id'=>null,
            'status'=>'draft',
            'current_step'=>'context',
            'target_tenant_id'=>(string)$normalised['target_tenant_id'] ?: null,
            'resource_scope'=>(string)$normalised['resource_scope'] ?: null,
            'endpoint_ref'=>(string)$normalised['endpoint_ref'] ?: null,
            'secret_ref'=>(string)$normalised['secret_ref'] ?: null,
            'auth_strategy'=>(string)$normalised['auth_strategy'],
            'event_pattern'=>(string)$normalised['event_pattern'],
            'hourly_limit'=>(int)$normalised['hourly_limit'],
            'safe_mode'=>'shadow',
            'config_json'=>wp_json_encode($normalised['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'readiness_json'=>null,
            'created_by'=>get_current_user_id() ?: null,
            'updated_by'=>get_current_user_id() ?: null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        if ($ok === false) return new \WP_Error('fieldflow_6a_plan_insert_failed', 'Não foi possível criar o plano de onboarding.');
        $id = (int)$wpdb->insert_id;
        self::audit('phase6a.onboarding.plan_created', $id, $normalised, 'Plano 6A criado em modo seguro.');
        return $id;
    }

    public static function updatePlan(int $planId, array $input) {
        global $wpdb;
        $plan = self::getPlan($planId);
        if (!$plan) return new \WP_Error('fieldflow_6a_plan_not_found', 'Plano de onboarding não encontrado.');
        if ((int)($plan['destination_id'] ?? 0) > 0) {
            return new \WP_Error('fieldflow_6a_plan_already_staged', 'Este plano já foi provisionado. Reinicia-o antes de alterar a ligação.');
        }
        $merged = array_merge($plan, $input);
        $normalised = self::normaliseInput($merged);
        if (is_wp_error($normalised)) return $normalised;
        $ok = $wpdb->update(self::table(), [
            'client_id'=>(int)$normalised['client_id'],
            'project_id'=>(int)$normalised['project_id'] > 0 ? (int)$normalised['project_id'] : null,
            'template_key'=>(string)$normalised['template_key'],
            'destination_type'=>(string)$normalised['type_key'],
            'target_tenant_id'=>(string)$normalised['target_tenant_id'] ?: null,
            'resource_scope'=>(string)$normalised['resource_scope'] ?: null,
            'endpoint_ref'=>(string)$normalised['endpoint_ref'] ?: null,
            'secret_ref'=>(string)$normalised['secret_ref'] ?: null,
            'auth_strategy'=>(string)$normalised['auth_strategy'],
            'event_pattern'=>(string)$normalised['event_pattern'],
            'hourly_limit'=>(int)$normalised['hourly_limit'],
            'config_json'=>wp_json_encode($normalised['config'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status'=>'draft',
            'current_step'=>'connection',
            'readiness_json'=>null,
            'updated_by'=>get_current_user_id() ?: null,
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$planId]);
        if ($ok === false) return new \WP_Error('fieldflow_6a_plan_update_failed', 'Não foi possível atualizar o plano 6A.');
        self::audit('phase6a.onboarding.plan_updated', $planId, $normalised, 'Configuração do plano 6A atualizada.');
        return true;
    }

    public static function getPlan(int $planId): array {
        global $wpdb;
        if ($planId <= 0 || !self::tableReady()) return [];
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id=%d LIMIT 1', $planId), ARRAY_A);
        return is_array($row) ? self::hydrate($row) : [];
    }

    public static function listPlans(int $clientId = 0): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $sql = 'SELECT p.*,c.name AS client_name,pr.name AS project_name FROM ' . self::table() . ' p LEFT JOIN ' . $wpdb->prefix . 'routespro_clients c ON c.id=p.client_id LEFT JOIN ' . $wpdb->prefix . 'routespro_projects pr ON pr.id=p.project_id';
        if ($clientId > 0) {
            $rows = $wpdb->get_results($wpdb->prepare($sql . ' WHERE p.client_id=%d ORDER BY p.updated_at DESC,p.id DESC', $clientId), ARRAY_A) ?: [];
        } else {
            $rows = $wpdb->get_results($sql . ' ORDER BY p.updated_at DESC,p.id DESC LIMIT 500', ARRAY_A) ?: [];
        }
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function summary(): array {
        $out = ['total'=>0,'draft'=>0,'staged'=>0,'attention'=>0,'production_ready'=>0];
        foreach (self::listPlans() as $plan) {
            $out['total']++;
            $readiness = self::readiness($plan, false);
            if (!empty($readiness['production_ready'])) $out['production_ready']++;
            elseif ((string)($plan['status'] ?? '') === 'attention') $out['attention']++;
            elseif ((int)($plan['destination_id'] ?? 0) > 0 || (string)($plan['template_key'] ?? '') === 'twt_managed') $out['staged']++;
            else $out['draft']++;
        }
        return $out;
    }

    public static function preflight(int $planId): array {
        $plan = self::getPlan($planId);
        if (!$plan) return ['ok'=>false,'checks'=>['plan_exists'=>false],'message'=>'Plano não encontrado.'];
        $template = self::templates()[(string)$plan['template_key']] ?? [];
        $secretRequired = (string)($plan['auth_strategy'] ?? '') === 'key_vault_reference' || (string)($plan['template_key'] ?? '') === 'client_azure_relay';
        $checks = [
            'plan_exists'=>true,
            'phase5_stable_certified'=>!empty(Phase5StableRelease::health()['stable_certified']),
            'phase5_baseline_match'=>!empty(Phase5StableRelease::health()['baseline_match']),
            'client_valid'=>self::clientExists((int)$plan['client_id']),
            'project_scope_valid'=>self::projectBelongsToClient((int)$plan['project_id'], (int)$plan['client_id']),
            'template_valid'=>!empty($template),
            'shadow_only'=>(string)$plan['safe_mode'] === 'shadow',
            'production_disabled'=>self::GENERAL_PRODUCTION_ENABLED === false,
            'secret_is_reference_only'=>self::secretRefSafe((string)$plan['secret_ref'], $secretRequired),
            'endpoint_valid'=>self::endpointSafe((string)$plan['endpoint_ref'], !empty($template['needs_endpoint']), (string)$plan['template_key']),
            'identity_fields_valid'=>self::identityFieldsSafe($plan, $template),
            'event_pattern_explicit'=>self::eventPatternSafe((string)$plan['event_pattern']),
        ];
        $ok = !in_array(false, $checks, true);
        self::saveReadiness($planId, ['preflight'=>$checks,'preflight_ok'=>$ok]);
        if ($ok) self::setStatus($planId, 'preflight_ready', 'preflight');
        return ['ok'=>$ok,'checks'=>$checks,'plan'=>$plan,'message'=>$ok ? 'Pré-condições 6A validadas. Nenhuma rede ou produção foi ativada.' : 'Existem campos do onboarding a rever antes do provisioning.'];
    }

    public static function stage(int $planId) {
        global $wpdb;
        $preflight = self::preflight($planId);
        if (empty($preflight['ok'])) return new \WP_Error('fieldflow_6a_preflight_failed', 'Corrige o pré-flight antes de provisionar.');
        $plan = self::getPlan($planId);
        if (!$plan) return new \WP_Error('fieldflow_6a_plan_not_found', 'Plano não encontrado.');
        if ((int)($plan['destination_id'] ?? 0) > 0) return (int)$plan['destination_id'];
        $template = self::templates()[(string)$plan['template_key']] ?? [];

        if ((string)$plan['template_key'] === 'twt_managed') {
            $primaryId = self::primaryTwtDestinationId();
            if ($primaryId <= 0) return new \WP_Error('fieldflow_6a_primary_twt_missing', 'O destino Azure TWT principal não está disponível.');
            $wpdb->update(self::table(), [
                'destination_id'=>$primaryId,
                'status'=>'staged',
                'current_step'=>'provisioned',
                'updated_by'=>get_current_user_id() ?: null,
                'updated_at'=>current_time('mysql', true),
            ], ['id'=>$planId]);
            self::audit('phase6a.onboarding.twt_managed_staged', $planId, ['destination_id'=>$primaryId], 'Cliente associado ao Data Hub TWT certificado.');
            return $primaryId;
        }

        $client = self::clientName((int)$plan['client_id']);
        $project = self::projectName((int)$plan['project_id']);
        $name = trim($client . ($project !== '' ? ' / ' . $project : '') . ' / ' . (string)($template['label'] ?? 'Destino'));
        $created = DestinationRegistry::createDestination([
            'name'=>$name,
            'type_key'=>(string)$plan['destination_type'],
            'client_id'=>(int)$plan['client_id'],
            'project_id'=>(int)$plan['project_id'],
            'mode'=>in_array((string)$plan['destination_type'], ['client_powerbi','data_warehouse'], true) ? 'reporting' : 'mirror',
            'endpoint_ref'=>(string)$plan['endpoint_ref'],
            'secret_ref'=>(string)$plan['secret_ref'],
            'is_active'=>0,
            'auth_strategy'=>(string)$plan['auth_strategy'],
            'retry_policy'=>['max_attempts'=>5,'base_delay_seconds'=>60],
            'entra_target_tenant_id'=>(string)$plan['target_tenant_id'],
            'entra_resource_scope'=>(string)$plan['resource_scope'],
        ]);
        if (is_wp_error($created)) return $created;
        $destinationId = (int)$created;

        $rule = DestinationRegistry::createRule([
            'destination_id'=>$destinationId,
            'client_id'=>(int)$plan['client_id'],
            'project_id'=>(int)$plan['project_id'],
            'event_pattern'=>(string)$plan['event_pattern'],
            'action_name'=>'include',
            'priority'=>200,
            'is_active'=>0,
        ]);
        $ruleId = is_wp_error($rule) ? 0 : (int)$rule;

        $config = (array)$plan['config'];
        if ((string)$plan['auth_strategy'] === 'key_vault_reference' && in_array((string)$plan['destination_type'], ['external_api','webhook'], true)) {
            $authMode = sanitize_key((string)($config['credential_auth_mode'] ?? 'api_key_header'));
            $header = sanitize_text_field((string)($config['credential_header_name'] ?? 'X-FieldFlow-Credential'));
            $configured = CredentialLifecycle::configureDestination($destinationId, (string)$plan['secret_ref'], $authMode, $header, 30);
            if (is_wp_error($configured)) {
                DestinationRegistry::deleteDestination($destinationId);
                return $configured;
            }
        }

        $wpdb->update(self::table(), [
            'destination_id'=>$destinationId,
            'routing_rule_id'=>$ruleId > 0 ? $ruleId : null,
            'status'=>'staged',
            'current_step'=>'provisioned',
            'updated_by'=>get_current_user_id() ?: null,
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$planId]);
        self::audit('phase6a.onboarding.destination_staged', $planId, [
            'destination_id'=>$destinationId,
            'routing_rule_id'=>$ruleId,
            'destination_type'=>(string)$plan['destination_type'],
            'active'=>false,
            'rule_active'=>false,
        ], 'Destino e regra criados desativados, em preparação 6A.');
        return $destinationId;
    }

    public static function startMicrosoft(int $planId) {
        $plan = self::getPlan($planId);
        if (!$plan || (int)$plan['destination_id'] <= 0) return new \WP_Error('fieldflow_6a_destination_missing', 'Provisiona primeiro o destino.');
        $destination = DestinationRegistry::getDestination((int)$plan['destination_id']);
        if (!$destination) return new \WP_Error('fieldflow_6a_destination_missing', 'Destino provisionado não encontrado.');
        $result = EntraTenantOnboarding::start($destination);
        if (!is_wp_error($result)) {
            self::setStatus($planId, 'identity_pending', 'identity');
            self::audit('phase6a.onboarding.microsoft_link_created', $planId, [
                'destination_uuid'=>(string)$destination['destination_uuid'],
                'target_tenant_id'=>(string)$plan['target_tenant_id'],
                'resource_scope'=>(string)$plan['resource_scope'],
            ], 'Link Microsoft Entra browser-only criado.');
        }
        return $result;
    }

    public static function verifyMicrosoft(int $planId) {
        $plan = self::getPlan($planId);
        if (!$plan || (int)$plan['destination_id'] <= 0) return new \WP_Error('fieldflow_6a_destination_missing', 'Destino não encontrado.');
        $destination = DestinationRegistry::getDestination((int)$plan['destination_id']);
        $result = EntraTenantOnboarding::manualVerify($destination);
        if (!is_wp_error($result) && !empty($result['ok'])) {
            self::setStatus($planId, 'identity_ready', 'resource');
            self::audit('phase6a.onboarding.identity_ready', $planId, ['destination_uuid'=>(string)$destination['destination_uuid']], 'Identidade Microsoft Entra validada.');
        }
        return $result;
    }

    public static function configureAndProbeResource(int $planId) {
        $plan = self::getPlan($planId);
        if (!$plan || (int)$plan['destination_id'] <= 0) return new \WP_Error('fieldflow_6a_destination_missing', 'Destino não encontrado.');
        $template = self::templates()[(string)$plan['template_key']] ?? [];
        if (!in_array((string)($template['resource_kind'] ?? ''), ['azure_sql','powerbi_workspace','fabric_workspace'], true)) {
            return new \WP_Error('fieldflow_6a_resource_not_applicable', 'Este template não usa o probe de recurso Microsoft 5C.3.');
        }
        $destinationId = (int)$plan['destination_id'];
        $config = (array)$plan['config'];
        $configured = ResourceAuthorizationFoundation::configureDestination($destinationId, [
            'sql_server'=>(string)($config['sql_server'] ?? ''),
            'sql_database'=>(string)($config['sql_database'] ?? ''),
            'powerbi_workspace_id'=>(string)($config['workspace_id'] ?? ''),
        ]);
        if (is_wp_error($configured)) return $configured;
        $destination = DestinationRegistry::getDestination($destinationId);
        $response = ResourceAuthorizationFoundation::probeDestination($destination);
        if (is_wp_error($response)) return $response;
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) $body = [];
        $profile = ResourceAuthorizationFoundation::profileFromDestination(DestinationRegistry::getDestination($destinationId));
        $ok = !empty($profile['authorized']);
        if ($ok) self::setStatus($planId, 'resource_ready', 'credential');
        self::audit('phase6a.onboarding.resource_probe', $planId, [
            'http_code'=>$code,
            'authorized'=>$ok,
            'kind'=>(string)($profile['kind'] ?? ''),
            'probe_code'=>sanitize_key((string)($profile['last_probe_code'] ?? ($body['probe_code'] ?? ''))),
        ], 'Probe read-only de recurso executado no onboarding 6A.');
        return ['ok'=>$ok,'http_code'=>$code,'profile'=>$profile,'body'=>$body];
    }

    public static function certifyCredential(int $planId): array {
        $plan = self::getPlan($planId);
        if (!$plan || (int)$plan['destination_id'] <= 0) return ['ok'=>false,'reason'=>'destination_missing'];
        if ((string)$plan['auth_strategy'] !== 'key_vault_reference' || !in_array((string)$plan['destination_type'], ['external_api','webhook'], true)) {
            return ['ok'=>true,'reason'=>'credential_not_required_for_template','skipped'=>true];
        }
        $probe = CredentialLifecycle::probeCredential((int)$plan['destination_id']);
        if (empty($probe['ok'])) return $probe;
        $cert = CredentialLifecycle::certifyCredential((int)$plan['destination_id']);
        if (!empty($cert['ok'])) self::setStatus($planId, 'credential_ready', 'delivery');
        self::audit('phase6a.onboarding.credential_certified', $planId, [
            'ok'=>!empty($cert['ok']),
            'secret_value_returned'=>false,
        ], 'Credencial Key Vault certificada sem expor o valor ao WordPress.');
        return $cert;
    }

    public static function readiness($planOrId, bool $persist = true): array {
        $plan = is_array($planOrId) ? $planOrId : self::getPlan((int)$planOrId);
        if (!$plan) return ['ok'=>false,'status'=>'missing','production_ready'=>false];
        $stable = Phase5StableRelease::health();
        $template = self::templates()[(string)$plan['template_key']] ?? [];
        $destination = (int)$plan['destination_id'] > 0 ? DestinationRegistry::getDestination((int)$plan['destination_id']) : [];
        $auth = sanitize_key((string)$plan['auth_strategy']);
        $identityReady = true;
        if ($auth === 'entra_cross_tenant' && $destination) {
            $identityReady = !empty(EntraTenantOnboarding::statusForDestination($destination)['ready']);
        }
        $resourceReady = true;
        if (in_array((string)($template['resource_kind'] ?? ''), ['azure_sql','powerbi_workspace','fabric_workspace'], true)) {
            $resourceReady = $destination ? !empty(ResourceAuthorizationFoundation::profileFromDestination($destination)['authorized']) : false;
        }
        $credentialReady = true;
        if ($auth === 'key_vault_reference') {
            if (in_array((string)$plan['destination_type'], ['external_api','webhook'], true)) {
                $credentialReady = $destination ? CredentialLifecycle::isDestinationCredentialReady((int)$plan['destination_id']) : false;
            } else {
                $credentialReady = self::secretRefSafe((string)$plan['secret_ref'], true);
            }
        }
        $deliveryReady = false;
        if ((string)$plan['template_key'] === 'twt_managed') {
            $deliveryReady = !empty($destination) && (string)($destination['type_key'] ?? '') === 'twt_azure';
        } elseif (in_array((string)$plan['destination_type'], ['external_api','webhook'], true)) {
            foreach (ProductionDeliveryGate::summary() as $row) {
                if ((int)($row['destination_id'] ?? 0) === (int)$plan['destination_id']) {
                    $deliveryReady = !empty($row['production_ready']);
                    break;
                }
            }
        } elseif ((string)$plan['destination_type'] === 'client_azure') {
            $health = ClientAzureDelivery::health();
            $deliveryReady = !empty($destination['is_active']) && !empty($health['table_ready']) && !empty($health['relay']['configured']) && (int)($health['stats']['dead'] ?? 0) === 0;
        } else {
            // Azure SQL and Power BI/Fabric are resource-authorized in 6A, but
            // their write/publish adapters remain outside this foundation.
            $deliveryReady = false;
        }
        $staged = !empty($destination);
        $productionReady = !empty($stable['stable_certified']) && $staged && $identityReady && $resourceReady && $credentialReady && $deliveryReady;
        $status = $productionReady ? 'production_ready' : ($staged ? 'staged' : 'draft');
        $next = self::nextAction($plan, $template, $identityReady, $resourceReady, $credentialReady, $deliveryReady);
        $result = [
            'ok'=>true,
            'status'=>$status,
            'phase5_stable'=>!empty($stable['stable_certified']),
            'staged'=>$staged,
            'identity_ready'=>$identityReady,
            'resource_ready'=>$resourceReady,
            'credential_ready'=>$credentialReady,
            'delivery_ready'=>$deliveryReady,
            'production_ready'=>$productionReady,
            'production_enabled'=>false,
            'next_action'=>$next,
            'destination_id'=>(int)($plan['destination_id'] ?? 0),
            'destination_uuid'=>(string)($destination['destination_uuid'] ?? ''),
        ];
        if ($persist && (int)$plan['id'] > 0) self::saveReadiness((int)$plan['id'], $result);
        return $result;
    }

    public static function resetPlan(int $planId) {
        global $wpdb;
        $plan = self::getPlan($planId);
        if (!$plan) return new \WP_Error('fieldflow_6a_plan_not_found', 'Plano não encontrado.');
        $destinationId = (int)($plan['destination_id'] ?? 0);
        $ruleId = (int)($plan['routing_rule_id'] ?? 0);
        if ((string)$plan['template_key'] !== 'twt_managed') {
            if ($ruleId > 0) DestinationRegistry::deleteRule($ruleId);
            if ($destinationId > 0) DestinationRegistry::deleteDestination($destinationId);
        }
        $wpdb->update(self::table(), [
            'destination_id'=>null,
            'routing_rule_id'=>null,
            'status'=>'draft',
            'current_step'=>'context',
            'readiness_json'=>null,
            'updated_by'=>get_current_user_id() ?: null,
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$planId]);
        self::audit('phase6a.onboarding.plan_reset', $planId, ['removed_destination_id'=>$destinationId,'removed_rule_id'=>$ruleId], 'Plano 6A reiniciado. Produção permaneceu desligada.');
        return true;
    }

    private static function normaliseInput(array $input) {
        $templates = self::templates();
        $templateKey = sanitize_key((string)($input['template_key'] ?? ''));
        if (!isset($templates[$templateKey])) return new \WP_Error('fieldflow_6a_template_invalid', 'Escolhe um template de onboarding válido.');
        $template = $templates[$templateKey];
        $clientId = absint($input['client_id'] ?? 0);
        $projectId = absint($input['project_id'] ?? 0);
        if (!self::clientExists($clientId)) return new \WP_Error('fieldflow_6a_client_invalid', 'Seleciona um cliente válido.');
        if (!self::projectBelongsToClient($projectId, $clientId)) return new \WP_Error('fieldflow_6a_project_invalid', 'O projeto selecionado não pertence ao cliente.');

        $auth = sanitize_key((string)($input['auth_strategy'] ?? $template['auth_strategy']));
        if ($templateKey === 'twt_managed') $auth = 'none';
        $allowedAuth = ['none','key_vault_reference','entra_cross_tenant','entra_home_tenant','managed_identity'];
        if (!in_array($auth, $allowedAuth, true)) $auth = (string)$template['auth_strategy'];

        $targetTenant = EntraCrossTenantFoundation::normaliseGuid((string)($input['target_tenant_id'] ?? ''));
        $resourceScope = trim(sanitize_text_field((string)($input['resource_scope'] ?? '')));
        if ($resourceScope === '' && $auth === 'entra_cross_tenant') $resourceScope = (string)$template['default_scope'];

        $configInput = is_array($input['config'] ?? null) ? (array)$input['config'] : [];
        foreach (['sql_server','sql_database','workspace_id','credential_auth_mode','credential_header_name'] as $key) {
            if (array_key_exists($key, $input)) $configInput[$key] = $input[$key];
        }
        $config = [
            'sql_server'=>ResourceAuthorizationFoundation::normaliseSqlServer((string)($configInput['sql_server'] ?? '')),
            'sql_database'=>ResourceAuthorizationFoundation::normaliseSqlDatabase((string)($configInput['sql_database'] ?? '')),
            'workspace_id'=>EntraCrossTenantFoundation::normaliseGuid((string)($configInput['workspace_id'] ?? '')),
            'credential_auth_mode'=>sanitize_key((string)($configInput['credential_auth_mode'] ?? 'api_key_header')),
            'credential_header_name'=>sanitize_text_field((string)($configInput['credential_header_name'] ?? 'X-FieldFlow-Credential')),
        ];

        $endpoint = trim((string)($input['endpoint_ref'] ?? ''));
        if ($templateKey === 'azure_sql_external' && $config['sql_server'] !== '' && $config['sql_database'] !== '') {
            $endpoint = 'azure-sql://' . $config['sql_server'] . '/' . rawurlencode($config['sql_database']);
        } elseif ($templateKey === 'powerbi_external' && $config['workspace_id'] !== '') {
            $endpoint = 'powerbi://workspace/' . $config['workspace_id'];
        } elseif ($templateKey === 'fabric_external' && $config['workspace_id'] !== '') {
            $endpoint = 'powerbi://workspace/' . $config['workspace_id'];
        }
        $endpoint = sanitize_text_field($endpoint);
        $secretRef = trim((string)($input['secret_ref'] ?? ''));
        $secretRef = sanitize_text_field($secretRef);
        if ($secretRef !== '' && !self::secretRefSafe($secretRef, false)) {
            return new \WP_Error('fieldflow_6a_secret_value_rejected', 'Neste campo só é aceite uma referência Key Vault no formato kv://vault/secret. Nunca coloques o valor da credencial.');
        }
        if (strlen($endpoint) > 500) {
            return new \WP_Error('fieldflow_6a_endpoint_too_long', 'O endpoint ou referência técnica excede o limite permitido.');
        }
        $event = DestinationRegistry::normaliseEventPattern((string)($input['event_pattern'] ?? 'submission.created'));
        if ($event === '*' || $event === '') $event = 'submission.created';
        $hourly = max(1, min(1000, absint($input['hourly_limit'] ?? 10)));

        return [
            'client_id'=>$clientId,
            'project_id'=>$projectId,
            'template_key'=>$templateKey,
            'type_key'=>(string)$template['type_key'],
            'auth_strategy'=>$auth,
            'target_tenant_id'=>$targetTenant,
            'resource_scope'=>$resourceScope,
            'endpoint_ref'=>$endpoint,
            'secret_ref'=>$secretRef,
            'event_pattern'=>$event,
            'hourly_limit'=>$hourly,
            'config'=>$config,
        ];
    }

    private static function hydrate(array $row): array {
        $config = json_decode((string)($row['config_json'] ?? ''), true);
        $readiness = json_decode((string)($row['readiness_json'] ?? ''), true);
        $row['config'] = is_array($config) ? $config : [];
        $row['readiness'] = is_array($readiness) ? $readiness : [];
        return $row;
    }

    private static function setStatus(int $planId, string $status, string $step): void {
        global $wpdb;
        if (!self::tableReady()) return;
        $wpdb->update(self::table(), [
            'status'=>sanitize_key($status),
            'current_step'=>sanitize_key($step),
            'updated_by'=>get_current_user_id() ?: null,
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$planId]);
    }

    private static function saveReadiness(int $planId, array $payload): void {
        global $wpdb;
        if (!self::tableReady()) return;
        $current = self::getPlan($planId);
        $old = is_array($current['readiness'] ?? null) ? $current['readiness'] : [];
        $merged = array_merge($old, $payload, ['updated_at_utc'=>gmdate('c')]);
        $wpdb->update(self::table(), [
            'readiness_json'=>wp_json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$planId]);
    }

    private static function nextAction(array $plan, array $template, bool $identityReady, bool $resourceReady, bool $credentialReady, bool $deliveryReady): string {
        if ((int)($plan['destination_id'] ?? 0) <= 0) return 'Provisionar configuração em Shadow';
        if ((string)$plan['auth_strategy'] === 'entra_cross_tenant' && !$identityReady) return 'Autorizar Microsoft Entra';
        if (in_array((string)($template['resource_kind'] ?? ''), ['azure_sql','powerbi_workspace','fabric_workspace'], true) && !$resourceReady) return 'Validar recurso read-only';
        if ((string)$plan['auth_strategy'] === 'key_vault_reference' && !$credentialReady) return 'Certificar credencial Key Vault';
        if (!$deliveryReady && in_array((string)$plan['destination_type'], ['external_api','webhook'], true)) return 'Certificar Safe Pilot e Production Gate em Destinos de Dados';
        if (!$deliveryReady && (string)$plan['destination_type'] === 'client_azure') return 'Validar e ativar o destino 5B numa gate explícita da Fase 6';
        if (!$deliveryReady && in_array((string)$plan['destination_type'], ['data_warehouse','client_powerbi'], true)) return 'Aguardar adapter de delivery da Fase 6';
        return 'Pronto para gate de ativação explícita';
    }

    private static function clientExists(int $clientId): bool {
        global $wpdb;
        if ($clientId <= 0) return false;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', $clientId)) === 1;
    }

    private static function projectBelongsToClient(int $projectId, int $clientId): bool {
        global $wpdb;
        if ($projectId <= 0) return true;
        return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d AND client_id=%d', $projectId, $clientId)) === 1;
    }

    private static function clientName(int $id): string {
        global $wpdb;
        return sanitize_text_field((string)$wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_clients WHERE id=%d', $id)));
    }

    private static function projectName(int $id): string {
        global $wpdb;
        if ($id <= 0) return '';
        return sanitize_text_field((string)$wpdb->get_var($wpdb->prepare('SELECT name FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', $id)));
    }

    private static function primaryTwtDestinationId(): int {
        foreach (DestinationRegistry::listDestinations() as $destination) {
            if ((string)($destination['type_key'] ?? '') === 'twt_azure' && !empty($destination['is_active']) && (string)($destination['mode'] ?? '') === 'primary') return (int)$destination['id'];
        }
        return 0;
    }

    private static function secretRefSafe(string $secretRef, bool $required): bool {
        $secretRef = trim($secretRef);
        if ($secretRef === '') return !$required;
        if (strlen($secretRef) > 500) return false;
        if (!preg_match('#^kv://[a-z0-9._\-/]+$#i', $secretRef)) return false;
        return stripos($secretRef, 'Bearer ') === false && stripos($secretRef, 'sk-') === false;
    }

    private static function endpointSafe(string $endpoint, bool $required, string $templateKey): bool {
        $endpoint = trim($endpoint);
        if ($endpoint === '') return !$required;
        if ($templateKey === 'azure_sql_external') return preg_match('#^azure-sql://[a-z0-9.-]+/[a-z0-9._%\-]+$#i', $endpoint) === 1;
        if ($templateKey === 'powerbi_external') return preg_match('#^powerbi://workspace/[a-f0-9\-]{36}$#i', $endpoint) === 1;
        if ($templateKey === 'fabric_external') return preg_match('#^powerbi://workspace/[a-f0-9\-]{36}$#i', $endpoint) === 1;
        if (in_array($templateKey, ['external_api','webhook','client_azure_relay'], true)) {
            $url = esc_url_raw($endpoint, ['https']);
            return $url !== '' && strtolower((string)(wp_parse_url($url, PHP_URL_SCHEME) ?: '')) === 'https';
        }
        return true;
    }

    private static function identityFieldsSafe(array $plan, array $template): bool {
        $auth = sanitize_key((string)($plan['auth_strategy'] ?? ''));
        if ($auth !== 'entra_cross_tenant') return true;
        $tenant = EntraCrossTenantFoundation::normaliseGuid((string)($plan['target_tenant_id'] ?? ''));
        $scope = trim((string)($plan['resource_scope'] ?? ''));
        if ($tenant === '' || $scope === '') return false;
        $expected = (string)($template['default_scope'] ?? '');
        return $expected === '' || hash_equals($expected, $scope);
    }

    private static function eventPatternSafe(string $pattern): bool {
        $pattern = DestinationRegistry::normaliseEventPattern($pattern);
        return $pattern !== '' && $pattern !== '*' && preg_match('/^[a-z0-9][a-z0-9._:\-]{2,95}$/', $pattern) === 1;
    }

    private static function audit(string $event, int $planId, array $details, string $reason): void {
        if (!class_exists('\\RoutesPro\\Cloud\\GovernanceRecovery')) return;
        $plan = self::getPlan($planId);
        GovernanceRecovery::appendLedger($event, [
            'entity_type'=>'client_onboarding_plan',
            'entity_ref'=>(string)($plan['plan_uuid'] ?? ('plan:' . $planId)),
            'client_id'=>(int)($plan['client_id'] ?? 0),
            'project_id'=>(int)($plan['project_id'] ?? 0),
            'destination_uuid'=>'',
        ], array_merge($details, [
            'phase'=>self::PHASE,
            'production_enabled'=>false,
            'full_operational_payload_enabled'=>false,
        ]), $reason);
    }
}
