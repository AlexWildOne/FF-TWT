<?php
namespace RoutesPro\Cloud;

if (!defined('ABSPATH')) exit;

final class DestinationRegistry {
    public const VERSION = '1.2.0';
    private const SEED_OPTION = 'fieldflow_multi_destination_seed_version';
    private const ACTIVE_EXTERNAL_OPTION = 'fieldflow_multi_destination_has_active_external';
    private static bool $ensured = false;

    public static function typesTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_cloud_destination_types';
    }

    public static function destinationsTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_cloud_destinations';
    }

    public static function rulesTable(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_cloud_routing_rules';
    }

    public static function tableReady(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function ready(): bool {
        return self::tableReady(self::typesTable())
            && self::tableReady(self::destinationsTable())
            && self::tableReady(self::rulesTable());
    }

    public static function ensureFoundation(): bool {
        if (self::$ensured) return true;
        if (!self::ready()) return false;
        if ((string)get_option(self::SEED_OPTION, '') === self::VERSION) {
            self::$ensured = true;
            return true;
        }
        self::seedTypes();
        self::upgradeConnectorFoundationRows();
        $primaryId = self::ensurePrimaryTwtDestination();
        if ($primaryId <= 0) return false;
        update_option(self::SEED_OPTION, self::VERSION, false);
        self::refreshActiveExternalFlag();
        self::$ensured = true;
        return true;
    }

    public static function seedTypes(): void {
        global $wpdb;
        if (!self::tableReady(self::typesTable())) return;
        $now = current_time('mysql', true);
        foreach (DestinationTypes::definitions() as $typeKey => $definition) {
            $exists = (string)$wpdb->get_var($wpdb->prepare(
                'SELECT type_key FROM ' . self::typesTable() . ' WHERE type_key=%s LIMIT 1',
                $typeKey
            ));
            $data = [
                'label' => (string)$definition['label'],
                'delivery_phase' => (string)$definition['delivery_phase'],
                'capability' => (string)$definition['capability'],
                'supports_endpoint' => !empty($definition['supports_endpoint']) ? 1 : 0,
                'requires_secret_ref' => !empty($definition['requires_secret_ref']) ? 1 : 0,
                'is_active' => 1,
                'updated_at' => $now,
            ];
            if ($exists === '') {
                $data['type_key'] = $typeKey;
                $data['created_at'] = $now;
                $wpdb->insert(self::typesTable(), $data);
            } else {
                $wpdb->update(self::typesTable(), $data, ['type_key'=>$typeKey]);
            }
        }
    }

    private static function upgradeConnectorFoundationRows(): void {
        global $wpdb;
        if (!self::tableReady(self::destinationsTable())) return;
        $rows = $wpdb->get_results('SELECT id,type_key,is_active,status,retry_policy_json FROM ' . self::destinationsTable(), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $type = sanitize_key((string)($row['type_key'] ?? ''));
            if (!ConnectorFoundation::is5cType($type)) continue;
            $policy = json_decode((string)($row['retry_policy_json'] ?? ''), true);
            if (!is_array($policy)) $policy = self::normaliseRetryPolicy([]);
            $auth = (string)($policy['auth_strategy'] ?? '');
            $policy = ConnectorFoundation::decoratePolicy($type, $policy, $auth);
            $wpdb->update(self::destinationsTable(), [
                'retry_policy_json'=>wp_json_encode($policy, JSON_UNESCAPED_SLASHES),
                'status'=>!empty($row['is_active']) ? 'shadow_ready' : ((string)($row['status'] ?? '') === 'draft' ? 'draft' : 'disabled'),
                'updated_at'=>current_time('mysql', true),
            ], ['id'=>(int)$row['id']]);
        }
    }

    public static function ensurePrimaryTwtDestination(): int {
        global $wpdb;
        if (!self::tableReady(self::destinationsTable())) return 0;
        $identity = TenantIdentity::snapshot();
        $tenantId = (string)$identity['tenant_id'];
        $uuid = self::primaryDestinationUuid($tenantId);
        $settings = AzureConnectionManager::settings();
        $legacyEndpoint = esc_url_raw(trim((string)($settings['azure_cloud_endpoint'] ?? '')), ['https']);
        $legacySecretConfigured = strlen(trim((string)($settings['azure_cloud_hmac_secret'] ?? ''))) >= 32;
        $legacyConfigured = $legacyEndpoint !== '' && $legacySecretConfigured;
        $status = $legacyConfigured
            ? (AzureConnectionManager::syncEnabled() ? 'ready' : 'paused')
            : 'draft';
        $now = current_time('mysql', true);
        $existingId = (int)$wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . self::destinationsTable() . ' WHERE destination_uuid=%s LIMIT 1',
            $uuid
        ));
        $data = [
            'tenant_id' => $tenantId,
            'client_id' => null,
            'project_id' => null,
            'type_key' => 'twt_azure',
            'name' => 'Azure TWT, Data Hub principal',
            'is_active' => 1,
            'mode' => 'primary',
            'endpoint_ref' => 'legacy://azure-cloud-primary',
            'secret_ref' => null,
            'retry_policy_json' => wp_json_encode([
                'driver'=>'legacy_azure_sync',
                'max_attempts'=>max(1, min(12, absint((AzureConnectionManager::settings()['azure_cloud_sync_max_attempts'] ?? 5)))),
                'managed_by'=>'AzureSyncFoundation',
            ], JSON_UNESCAPED_SLASHES),
            'status' => $status,
            'last_error_code' => !$legacyConfigured ? 'legacy_not_configured' : null,
            'last_error_message' => !$legacyConfigured ? 'A configuração Azure TWT existente ainda não está completa.' : null,
            'updated_at' => $now,
        ];
        if ($existingId > 0) {
            $wpdb->update(self::destinationsTable(), $data, ['id'=>$existingId]);
            self::ensurePrimaryRule($existingId, $tenantId);
            return $existingId;
        }
        $data['destination_uuid'] = $uuid;
        $data['created_by'] = null;
        $data['created_at'] = $now;
        if ($wpdb->insert(self::destinationsTable(), $data) === false) return 0;
        $id = (int)$wpdb->insert_id;
        self::ensurePrimaryRule($id, $tenantId);
        return $id;
    }

    private static function ensurePrimaryRule(int $destinationId, string $tenantId): void {
        global $wpdb;
        if ($destinationId <= 0 || !self::tableReady(self::rulesTable())) return;
        $existing = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::rulesTable() . " WHERE destination_id=%d AND event_pattern='*' AND action_name='include' AND client_id IS NULL AND project_id IS NULL LIMIT 1",
            $destinationId
        ));
        if ($existing > 0) return;
        $now = current_time('mysql', true);
        $wpdb->insert(self::rulesTable(), [
            'rule_uuid' => wp_generate_uuid4(),
            'tenant_id' => $tenantId,
            'destination_id' => $destinationId,
            'client_id' => null,
            'project_id' => null,
            'event_pattern' => '*',
            'action_name' => 'include',
            'is_active' => 1,
            'priority' => 100000,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function primaryDestinationUuid(?string $tenantId = null): string {
        $tenantId = $tenantId !== null ? $tenantId : TenantIdentity::tenantId();
        return 'ff_d_' . substr(hash('sha256', $tenantId . '|twt_azure|primary'), 0, 24);
    }

    public static function newDestinationUuid(): string {
        try {
            return 'ff_d_' . bin2hex(random_bytes(12));
        } catch (\Throwable $error) {
            return 'ff_d_' . substr(hash('sha256', wp_generate_uuid4() . '|' . microtime(true)), 0, 24);
        }
    }

    public static function listDestinations(): array {
        global $wpdb;
        if (!self::tableReady(self::destinationsTable())) return [];
        $types = self::tableReady(self::typesTable()) ? self::typesTable() : '';
        if ($types !== '') {
            return $wpdb->get_results(
                'SELECT d.*,t.label AS type_label,t.delivery_phase,t.capability,t.requires_secret_ref FROM ' . self::destinationsTable() . ' d LEFT JOIN ' . $types . ' t ON t.type_key=d.type_key ORDER BY (d.mode="primary") DESC,d.client_id ASC,d.project_id ASC,d.id ASC',
                ARRAY_A
            ) ?: [];
        }
        return $wpdb->get_results('SELECT * FROM ' . self::destinationsTable() . ' ORDER BY id ASC', ARRAY_A) ?: [];
    }

    public static function getDestination(int $id): array {
        global $wpdb;
        if ($id <= 0 || !self::tableReady(self::destinationsTable())) return [];
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::destinationsTable() . ' WHERE id=%d LIMIT 1', $id), ARRAY_A) ?: [];
    }

    public static function createDestination(array $input) {
        global $wpdb;
        if (!self::ready()) return new \WP_Error('fieldflow_destination_tables_missing', 'As tabelas Multi-Destination ainda não estão disponíveis.');
        $type = sanitize_key((string)($input['type_key'] ?? ''));
        if (!DestinationTypes::exists($type) || $type === 'twt_azure') {
            return new \WP_Error('fieldflow_destination_type_invalid', 'Seleciona um tipo de destino externo válido.');
        }
        $clientId = absint($input['client_id'] ?? 0);
        $projectId = absint($input['project_id'] ?? 0);
        if ($projectId > 0 && $clientId <= 0) {
            return new \WP_Error('fieldflow_destination_scope_invalid', 'Um destino por projeto também precisa de cliente.');
        }
        $name = sanitize_text_field((string)($input['name'] ?? ''));
        if ($name === '') return new \WP_Error('fieldflow_destination_name_missing', 'Indica um nome para o destino.');
        $mode = self::normaliseMode((string)($input['mode'] ?? 'mirror'));
        $endpointRef = self::normaliseEndpointRef((string)($input['endpoint_ref'] ?? ''));
        if (is_wp_error($endpointRef)) return $endpointRef;
        $secretRef = self::normaliseSecretRef((string)($input['secret_ref'] ?? ''));
        if (is_wp_error($secretRef)) return $secretRef;
        $active = !empty($input['is_active']) ? 1 : 0;
        $typeDef = DestinationTypes::get($type);
        if (ConnectorFoundation::is5cType($type)) {
            $targetCheck = ConnectorFoundation::validateTargetRef($type, (string)$endpointRef, $active === 1);
            if (is_wp_error($targetCheck)) return $targetCheck;
            $authCheck = ConnectorFoundation::validateAuthStrategy($type, (string)($input['auth_strategy'] ?? ''));
            if (is_wp_error($authCheck)) return $authCheck;
        } elseif ($active && !empty($typeDef['requires_secret_ref']) && $secretRef === '') {
            return new \WP_Error('fieldflow_destination_secret_ref_missing', 'Para ativar este tipo, indica apenas a referência do segredo no Azure Key Vault.');
        }
        $now = current_time('mysql', true);
        $retry = self::normaliseRetryPolicy((array)($input['retry_policy'] ?? []));
        $retry = ConnectorFoundation::decoratePolicy($type, $retry, (string)($input['auth_strategy'] ?? ''));
        if (ConnectorFoundation::is5cType($type) && class_exists('\RoutesPro\Cloud\EntraCrossTenantFoundation')) {
            $retry = EntraCrossTenantFoundation::decoratePolicy(
                $retry,
                (string)($input['entra_target_tenant_id'] ?? ''),
                (string)($input['entra_resource_scope'] ?? '')
            );
        }
        $ok = $wpdb->insert(self::destinationsTable(), [
            'destination_uuid' => self::newDestinationUuid(),
            'tenant_id' => TenantIdentity::tenantId(),
            'client_id' => $clientId > 0 ? $clientId : null,
            'project_id' => $projectId > 0 ? $projectId : null,
            'type_key' => $type,
            'name' => $name,
            'is_active' => $active,
            'mode' => $mode,
            'endpoint_ref' => $endpointRef !== '' ? $endpointRef : null,
            'secret_ref' => $secretRef !== '' ? $secretRef : null,
            'retry_policy_json' => wp_json_encode($retry, JSON_UNESCAPED_SLASHES),
            'status' => $active ? (ConnectorFoundation::is5cType($type) ? 'shadow_ready' : 'ready') : 'draft',
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($ok === false) return new \WP_Error('fieldflow_destination_insert_failed', 'Não foi possível criar o destino.');
        self::refreshActiveExternalFlag();
        return (int)$wpdb->insert_id;
    }

    public static function updateEntraIdentity(int $id, string $targetTenantId, string $resourceScope) {
        global $wpdb;
        $row = self::getDestination($id);
        if (!$row) return new \WP_Error('fieldflow_destination_not_found', 'Destino não encontrado.');

        $type = sanitize_key((string)($row['type_key'] ?? ''));
        if (!ConnectorFoundation::is5cType($type)) {
            return new \WP_Error('fieldflow_5c2_identity_destination_invalid', 'Este destino não pertence à Connector Foundation 5C.');
        }
        $profile = ConnectorFoundation::profileFromDestination($row);
        if ((string)($profile['auth_strategy'] ?? '') !== 'entra_cross_tenant') {
            return new \WP_Error('fieldflow_5c2_identity_auth_invalid', 'Este destino não usa Microsoft Entra cross-tenant.');
        }

        $targetTenantId = EntraCrossTenantFoundation::normaliseGuid($targetTenantId);
        $tenantCheck = EntraCrossTenantFoundation::validateTenantId($targetTenantId, true);
        if (is_wp_error($tenantCheck)) return $tenantCheck;

        $resourceScope = trim($resourceScope);
        $scopeCheck = EntraCrossTenantFoundation::validateResourceScope($resourceScope, true);
        if (is_wp_error($scopeCheck)) return $scopeCheck;

        $settings = EntraCrossTenantFoundation::settings();
        if ($targetTenantId !== '' && $targetTenantId === (string)($settings['home_tenant_id'] ?? '')) {
            return new \WP_Error('fieldflow_5c2_identity_home_tenant', 'Este ecrã é para um tenant externo. Para o tenant TWT usa o probe TWT já certificado.');
        }

        $policy = json_decode((string)($row['retry_policy_json'] ?? ''), true);
        if (!is_array($policy)) $policy = [];
        $currentIdentity = EntraCrossTenantFoundation::identityProfileFromDestination($row);
        $identityChanged = (string)($currentIdentity['target_tenant_id'] ?? '') !== $targetTenantId
            || (string)($currentIdentity['resource_scope'] ?? '') !== $resourceScope;

        $policy = EntraCrossTenantFoundation::decoratePolicy($policy, $targetTenantId, $resourceScope);
        if ($identityChanged) {
            $policy['entra_onboarding_version'] = class_exists('\RoutesPro\Cloud\EntraTenantOnboarding') ? EntraTenantOnboarding::VERSION : '1.0.0';
            $policy['entra_onboarding_status'] = 'draft';
            $policy['entra_onboarding_nonce_hash'] = '';
            $policy['entra_onboarding_issued_at'] = 0;
            $policy['entra_onboarding_expires_at'] = 0;
            $policy['entra_onboarding_consent_received_at'] = 0;
            $policy['entra_onboarding_token_verified'] = false;
            $policy['entra_onboarding_token_verified_at'] = 0;
            $policy['entra_onboarding_callback_delivery'] = 'none';
            $policy['entra_onboarding_entra_code'] = '';
            $policy['entra_onboarding_error'] = '';
            $policy['entra_onboarding_delivery_enabled'] = false;
            $policy['resource_auth_version'] = class_exists('\RoutesPro\Cloud\ResourceAuthorizationFoundation') ? ResourceAuthorizationFoundation::VERSION : '1.0.0';
            $policy['resource_auth_status'] = 'draft';
            $policy['resource_auth_verified'] = false;
            $policy['resource_auth_verified_at'] = 0;
            $policy['resource_auth_last_probe_at'] = 0;
            $policy['resource_auth_last_http_status'] = 0;
            $policy['resource_auth_last_remote_http_status'] = 0;
            $policy['resource_auth_last_probe_code'] = '';
            $policy['resource_auth_error'] = '';
            $policy['resource_auth_delivery_enabled'] = false;
            // Tenant/scope changes invalidate the concrete resource target too.
            // Do not carry an old client's SQL/workspace/API identifier into a
            // newly selected tenant even though delivery is still disabled.
            $policy['resource_auth_kind'] = '';
            $policy['resource_auth_sql_server'] = '';
            $policy['resource_auth_sql_database'] = '';
            $policy['resource_auth_powerbi_workspace_id'] = '';
            $policy['resource_auth_custom_probe_url'] = '';
        }

        $ok = $wpdb->update(self::destinationsTable(), [
            'retry_policy_json'=>wp_json_encode($policy, JSON_UNESCAPED_SLASHES),
            'status'=>!empty($row['is_active']) ? 'shadow_ready' : ((string)($row['status'] ?? '') === 'disabled' ? 'disabled' : 'draft'),
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$id]);
        if ($ok === false) {
            return new \WP_Error('fieldflow_5c2_identity_update_failed', 'Não foi possível guardar o tenant e resource scope deste destino.');
        }
        return true;
    }

    public static function setDestinationActive(int $id, bool $active) {
        global $wpdb;
        $row = self::getDestination($id);
        if (!$row) return new \WP_Error('fieldflow_destination_not_found', 'Destino não encontrado.');
        if ((string)($row['type_key'] ?? '') === 'twt_azure') {
            return new \WP_Error('fieldflow_primary_locked', 'O Azure TWT é o destino principal e não pode ser desativado neste registo.');
        }
        $type = sanitize_key((string)($row['type_key'] ?? ''));
        if ($active) {
            $typeDef = DestinationTypes::get($type);
            if (ConnectorFoundation::is5cType($type)) {
                $targetCheck = ConnectorFoundation::validateTargetRef($type, (string)($row['endpoint_ref'] ?? ''), true);
                if (is_wp_error($targetCheck)) return $targetCheck;
                $profile = ConnectorFoundation::profileFromDestination($row);
                $authCheck = ConnectorFoundation::validateAuthStrategy($type, (string)($profile['auth_strategy'] ?? ''));
                if (is_wp_error($authCheck)) return $authCheck;
            } elseif (!empty($typeDef['requires_secret_ref']) && trim((string)($row['secret_ref'] ?? '')) === '') {
                return new \WP_Error('fieldflow_destination_secret_ref_missing', 'Este destino precisa de uma referência Key Vault antes de ser ativado.');
            }
        }
        $wpdb->update(self::destinationsTable(), [
            'is_active'=>$active ? 1 : 0,
            'status'=>$active ? (ConnectorFoundation::is5cType($type) ? 'shadow_ready' : 'ready') : 'disabled',
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$id]);
        self::refreshActiveExternalFlag();
        return true;
    }

    public static function deleteDestination(int $id) {
        global $wpdb;
        $row = self::getDestination($id);
        if (!$row) return new \WP_Error('fieldflow_destination_not_found', 'Destino não encontrado.');
        if ((string)($row['type_key'] ?? '') === 'twt_azure') {
            return new \WP_Error('fieldflow_primary_locked', 'O destino Azure TWT não pode ser removido.');
        }
        $wpdb->delete(self::rulesTable(), ['destination_id'=>$id], ['%d']);
        $wpdb->delete(self::destinationsTable(), ['id'=>$id], ['%d']);
        self::refreshActiveExternalFlag();
        return true;
    }

    public static function createRule(array $input) {
        global $wpdb;
        if (!self::ready()) return new \WP_Error('fieldflow_destination_tables_missing', 'As tabelas Multi-Destination ainda não estão disponíveis.');
        $destinationId = absint($input['destination_id'] ?? 0);
        $destination = self::getDestination($destinationId);
        if (!$destination) return new \WP_Error('fieldflow_destination_not_found', 'Destino não encontrado.');
        if ((string)($destination['type_key'] ?? '') === 'twt_azure') {
            return new \WP_Error('fieldflow_primary_rule_locked', 'O routing do Azure TWT é obrigatório e não é editável na Fase 5A.');
        }
        $clientId = absint($input['client_id'] ?? 0);
        $projectId = absint($input['project_id'] ?? 0);
        if ($projectId > 0 && $clientId <= 0) return new \WP_Error('fieldflow_rule_scope_invalid', 'Uma regra por projeto também precisa de cliente.');
        $eventPattern = self::normaliseEventPattern((string)($input['event_pattern'] ?? '*'));
        if ($eventPattern === '') return new \WP_Error('fieldflow_rule_event_invalid', 'Indica um padrão de evento válido.');
        $actionName = sanitize_key((string)($input['action_name'] ?? 'include'));
        if (!in_array($actionName, ['include','exclude'], true)) $actionName = 'include';
        $priority = max(0, min(9999, absint($input['priority'] ?? 100)));
        $now = current_time('mysql', true);
        $ok = $wpdb->insert(self::rulesTable(), [
            'rule_uuid'=>wp_generate_uuid4(),
            'tenant_id'=>TenantIdentity::tenantId(),
            'destination_id'=>$destinationId,
            'client_id'=>$clientId > 0 ? $clientId : null,
            'project_id'=>$projectId > 0 ? $projectId : null,
            'event_pattern'=>$eventPattern,
            'action_name'=>$actionName,
            'is_active'=>!empty($input['is_active']) ? 1 : 0,
            'priority'=>$priority,
            'created_by'=>get_current_user_id() ?: null,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        if ($ok === false) return new \WP_Error('fieldflow_rule_insert_failed', 'Não foi possível criar a regra de routing.');
        return (int)$wpdb->insert_id;
    }

    public static function listRules(): array {
        global $wpdb;
        if (!self::ready()) return [];
        return $wpdb->get_results(
            'SELECT r.*,d.name AS destination_name,d.type_key FROM ' . self::rulesTable() . ' r LEFT JOIN ' . self::destinationsTable() . ' d ON d.id=r.destination_id ORDER BY (d.type_key="twt_azure") DESC,r.destination_id ASC,r.priority DESC,r.id ASC',
            ARRAY_A
        ) ?: [];
    }

    public static function setRuleActive(int $id, bool $active) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT r.*,d.type_key FROM ' . self::rulesTable() . ' r LEFT JOIN ' . self::destinationsTable() . ' d ON d.id=r.destination_id WHERE r.id=%d LIMIT 1', $id), ARRAY_A) ?: [];
        if (!$row) return new \WP_Error('fieldflow_rule_not_found', 'Regra não encontrada.');
        if ((string)($row['type_key'] ?? '') === 'twt_azure') return new \WP_Error('fieldflow_primary_rule_locked', 'A regra principal TWT não pode ser desativada.');
        $wpdb->update(self::rulesTable(), ['is_active'=>$active ? 1 : 0,'updated_at'=>current_time('mysql', true)], ['id'=>$id]);
        return true;
    }

    public static function deleteRule(int $id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT r.*,d.type_key FROM ' . self::rulesTable() . ' r LEFT JOIN ' . self::destinationsTable() . ' d ON d.id=r.destination_id WHERE r.id=%d LIMIT 1', $id), ARRAY_A) ?: [];
        if (!$row) return new \WP_Error('fieldflow_rule_not_found', 'Regra não encontrada.');
        if ((string)($row['type_key'] ?? '') === 'twt_azure') return new \WP_Error('fieldflow_primary_rule_locked', 'A regra principal TWT não pode ser removida.');
        $wpdb->delete(self::rulesTable(), ['id'=>$id], ['%d']);
        return true;
    }


    public static function hasActiveExternalDestinations(): bool {
        return (int)get_option(self::ACTIVE_EXTERNAL_OPTION, 0) === 1;
    }

    public static function refreshActiveExternalFlag(): bool {
        global $wpdb;
        if (!self::tableReady(self::destinationsTable())) {
            update_option(self::ACTIVE_EXTERNAL_OPTION, 0, false);
            return false;
        }
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM " . self::destinationsTable() . " WHERE is_active=1 AND type_key<>'twt_azure'");
        update_option(self::ACTIVE_EXTERNAL_OPTION, $count > 0 ? 1 : 0, false);
        return $count > 0;
    }

    public static function health(): array {
        global $wpdb;
        $health = [
            'version'=>self::VERSION,
            'ready'=>self::ready(),
            'types'=>0,
            'destinations'=>0,
            'active_destinations'=>0,
            'rules'=>0,
            'primary_twt'=>false,
        ];
        if (!self::ready()) return $health;
        $health['types'] = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . self::typesTable());
        $health['destinations'] = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . self::destinationsTable());
        $health['active_destinations'] = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . self::destinationsTable() . ' WHERE is_active=1');
        $health['rules'] = (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . self::rulesTable());
        $health['primary_twt'] = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::destinationsTable() . ' WHERE destination_uuid=%s AND type_key=%s AND mode=%s AND is_active=1', self::primaryDestinationUuid(), 'twt_azure', 'primary')) === 1;
        return $health;
    }

    public static function normaliseMode(string $mode): string {
        $mode = sanitize_key($mode);
        return in_array($mode, ['primary','mirror','reporting','optional'], true) ? $mode : 'mirror';
    }

    public static function normaliseEventPattern(string $pattern): string {
        $pattern = strtolower(trim($pattern));
        if ($pattern === '') return '*';
        $pattern = preg_replace('/[^a-z0-9._:*\-]+/', '', $pattern) ?: '';
        return substr($pattern, 0, 96);
    }

    private static function normaliseEndpointRef(string $value) {
        $value = trim($value);
        if ($value === '') return '';
        if (preg_match('#^https://#i', $value)) {
            $url = esc_url_raw($value, ['https']);
            if ($url === '') return new \WP_Error('fieldflow_destination_endpoint_invalid', 'O endpoint deve usar HTTPS.');
            return substr($url, 0, 500);
        }
        if (preg_match('#^(azure|powerbi|warehouse|api|webhook|azure-sql|synapse|fabric)://[a-z0-9._:/?=&%+\-]+$#i', $value)) return substr(sanitize_text_field($value), 0, 500);
        return new \WP_Error('fieldflow_destination_endpoint_invalid', 'Usa um endpoint HTTPS ou uma referência técnica suportada.');
    }

    private static function normaliseSecretRef(string $value) {
        $value = trim($value);
        if ($value === '') return '';
        if (preg_match('#^kv://[a-z0-9._/\-]+$#i', $value)) return substr($value, 0, 500);
        $url = esc_url_raw($value, ['https']);
        $host = strtolower((string)(wp_parse_url($url, PHP_URL_HOST) ?: ''));
        $path = (string)(wp_parse_url($url, PHP_URL_PATH) ?: '');
        if ($url !== '' && substr($host, -16) === '.vault.azure.net' && strpos($path, '/secrets/') === 0) return substr($url, 0, 500);
        return new \WP_Error('fieldflow_destination_secret_ref_invalid', 'Não introduzas o segredo. Indica apenas uma referência Azure Key Vault, por exemplo kv://vault/secret ou o Secret Identifier HTTPS.');
    }

    private static function normaliseRetryPolicy(array $policy): array {
        return [
            'max_attempts'=>max(1, min(12, absint($policy['max_attempts'] ?? 5))),
            'base_delay_seconds'=>max(30, min(3600, absint($policy['base_delay_seconds'] ?? 60))),
            'strategy'=>'exponential',
            'delivery_state'=>'phase_5e',
        ];
    }
}
