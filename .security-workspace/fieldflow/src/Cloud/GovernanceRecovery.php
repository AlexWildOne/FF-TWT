<?php
namespace RoutesPro\Cloud;

use RoutesPro\Core\AuditLog;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5E Governance, Recovery & Final Eligibility Gate.
 *
 * This phase does not enable general production delivery. It adds an append-only
 * hash-chained governance ledger, scoped kill controls, controlled dead-letter
 * replay, stale-lock recovery and the final PRODUCTION ELIGIBLE decision.
 */
final class GovernanceRecovery {
    public const VERSION = '1.0.0';
    public const PHASE = '5E';
    public const OPTION = 'fieldflow_governance_recovery_5e';
    public const CERT_OPTION = 'fieldflow_governance_recovery_5e_certification';
    public const REPLAY_OPTION = 'fieldflow_governance_recovery_5e_replay_tickets';
    public const SNAPSHOT_OPTION = 'fieldflow_governance_recovery_5e_snapshot';
    public const GENERAL_PRODUCTION_ENABLED = false;
    public const FULL_OPERATIONAL_PAYLOAD_ENABLED = false;
    public const REPLAY_TTL_SECONDS = 900;
    public const DEFAULT_STALE_LOCK_SECONDS = 600;

    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_governance_ledger';
    }

    public static function tableReady(): bool {
        global $wpdb;
        $table = self::table();
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function settings(): array {
        $stored = get_option(self::OPTION, []);
        if (!is_array($stored)) $stored = [];
        $defaults = [
            'global_kill_switch'=>1,
            'blocked_client_ids'=>[],
            'blocked_destination_uuids'=>[],
            'blocked_event_patterns'=>[],
            'stale_lock_seconds'=>self::DEFAULT_STALE_LOCK_SECONDS,
        ];
        $settings = wp_parse_args($stored, $defaults);
        $settings['global_kill_switch'] = !empty($settings['global_kill_switch']) ? 1 : 0;
        $settings['blocked_client_ids'] = array_values(array_unique(array_filter(array_map('absint', (array)$settings['blocked_client_ids']))));
        $settings['blocked_destination_uuids'] = array_values(array_unique(array_filter(array_map([self::class, 'normaliseDestinationUuid'], (array)$settings['blocked_destination_uuids']))));
        $settings['blocked_event_patterns'] = array_values(array_unique(array_filter(array_map([self::class, 'normaliseEventPattern'], (array)$settings['blocked_event_patterns']))));
        sort($settings['blocked_client_ids'], SORT_NUMERIC);
        sort($settings['blocked_destination_uuids'], SORT_STRING);
        sort($settings['blocked_event_patterns'], SORT_STRING);
        $settings['stale_lock_seconds'] = max(120, min(86400, absint($settings['stale_lock_seconds'] ?? self::DEFAULT_STALE_LOCK_SECONDS)));
        return $settings;
    }

    public static function saveSettings(array $input): array {
        if (!current_user_can('routespro_manage')) return ['ok'=>false,'reason'=>'governance_permission_denied'];
        $before = self::settings();
        $clients = array_values(array_unique(array_filter(array_map('absint', (array)($input['blocked_client_ids'] ?? [])))));
        $destinations = array_values(array_unique(array_filter(array_map([self::class, 'normaliseDestinationUuid'], (array)($input['blocked_destination_uuids'] ?? [])))));
        $eventsRaw = $input['blocked_event_patterns'] ?? [];
        if (is_string($eventsRaw)) $eventsRaw = preg_split('/[\r\n,]+/', $eventsRaw) ?: [];
        $events = array_values(array_unique(array_filter(array_map([self::class, 'normaliseEventPattern'], (array)$eventsRaw))));
        sort($clients, SORT_NUMERIC);
        sort($destinations, SORT_STRING);
        sort($events, SORT_STRING);
        $settings = [
            'global_kill_switch'=>1,
            'blocked_client_ids'=>$clients,
            'blocked_destination_uuids'=>$destinations,
            'blocked_event_patterns'=>$events,
            'stale_lock_seconds'=>max(120, min(86400, absint($input['stale_lock_seconds'] ?? self::DEFAULT_STALE_LOCK_SECONDS))),
        ];
        update_option(self::OPTION, $settings, false);
        $changed = self::canonicalize($before) !== self::canonicalize($settings);
        if ($changed) {
            $state = self::certificationState();
            $state['final_gate_certified'] = false;
            $state['production_eligible'] = false;
            $state['invalidated_at'] = time();
            $state['invalidated_reason'] = 'governance_settings_changed';
            update_option(self::CERT_OPTION, $state, false);
        }
        self::appendLedger('governance.controls.updated', ['entity_type'=>'governance_controls','entity_ref'=>'5E'], [
            'global_kill_switch'=>true,
            'blocked_client_count'=>count($clients),
            'blocked_destination_count'=>count($destinations),
            'blocked_event_count'=>count($events),
            'stale_lock_seconds'=>$settings['stale_lock_seconds'],
            'final_gate_invalidated'=>$changed,
        ], 'Controlos 5E atualizados. O kill switch global permanece bloqueado em ON nesta release.');
        return ['ok'=>true,'settings'=>$settings,'changed'=>$changed,'final_gate_invalidated'=>$changed];
    }

    public static function health(): array {
        $settings = self::settings();
        $cert = self::certificationState();
        $production = class_exists('\\RoutesPro\\Cloud\\ProductionDeliveryGate') ? ProductionDeliveryGate::health() : [];
        $credentials = class_exists('\\RoutesPro\\Cloud\\CredentialLifecycle') ? CredentialLifecycle::health() : [];
        $ledger = self::verifyLedgerChain();
        $queueStats = self::queueStats();
        $currentFingerprint = self::eligibilityFingerprint();
        $certifiedFingerprint = strtolower((string)($cert['eligibility_fingerprint_sha256'] ?? ''));
        $fingerprintMatch = preg_match('/^[a-f0-9]{64}$/', $certifiedFingerprint) === 1
            && hash_equals($certifiedFingerprint, $currentFingerprint);
        $eligible = !empty($cert['final_gate_certified'])
            && $fingerprintMatch
            && !empty($ledger['ok'])
            && (int)($production['production_ready_gates'] ?? 0) > 0
            && (int)($credentials['ready_credentials'] ?? 0) > 0
            && !empty($settings['global_kill_switch'])
            && class_exists('\RoutesPro\Cloud\ConnectorDelivery')
            && ConnectorDelivery::mode() === 'shadow'
            && !ConnectorDelivery::networkDeliveryEnabled();

        return [
            'version'=>self::VERSION,
            'phase'=>self::PHASE,
            'status'=>self::tableReady() && !empty($ledger['ok']) ? 'ready' : 'attention',
            'ledger_ready'=>self::tableReady(),
            'ledger_chain_valid'=>!empty($ledger['ok']),
            'ledger_entries'=>(int)($ledger['entries'] ?? 0),
            'audit_log_available'=>class_exists('\\RoutesPro\\Core\\AuditLog') && AuditLog::exists(),
            'global_kill_switch'=>!empty($settings['global_kill_switch']),
            'client_kill_controls'=>count($settings['blocked_client_ids']),
            'destination_kill_controls'=>count($settings['blocked_destination_uuids']),
            'event_kill_controls'=>count($settings['blocked_event_patterns']),
            'dead_letter_count'=>(int)($queueStats['dead'] ?? 0),
            'stale_running_count'=>(int)($queueStats['stale_running'] ?? 0),
            'production_5c8_ready_gates'=>(int)($production['production_ready_gates'] ?? 0),
            'credential_5d_ready'=>(int)($credentials['ready_credentials'] ?? 0),
            'ledger_certified'=>!empty($cert['ledger_certified']),
            'replay_certified'=>!empty($cert['replay_certified']),
            'recovery_certified'=>!empty($cert['recovery_certified']),
            'final_gate_certified'=>!empty($cert['final_gate_certified']),
            'eligibility_fingerprint_match'=>$fingerprintMatch,
            'eligibility_fingerprint_sha256'=>$currentFingerprint,
            'certified_fingerprint_sha256'=>$certifiedFingerprint,
            'production_eligible'=>$eligible,
            'production_enabled'=>self::GENERAL_PRODUCTION_ENABLED,
            'full_operational_payload_enabled'=>self::FULL_OPERATIONAL_PAYLOAD_ENABLED,
        ];
    }

    public static function localProbe(): array {
        $health = self::health();
        $settings = self::settings();
        $checks = [
            'governance_ledger_table_ready'=>!empty($health['ledger_ready']),
            'governance_ledger_chain_valid'=>!empty($health['ledger_chain_valid']),
            'core_audit_log_available'=>!empty($health['audit_log_available']),
            'production_5c8_ready'=>(int)($health['production_5c8_ready_gates'] ?? 0) > 0,
            'credential_5d_ready'=>(int)($health['credential_5d_ready'] ?? 0) > 0,
            'connector_queue_ready'=>class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') && ConnectorDelivery::tableReady(),
            'operational_dispatcher_locked'=>class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') && !ConnectorDelivery::networkDeliveryEnabled(),
            'shadow_mode_preserved'=>class_exists('\\RoutesPro\\Cloud\\ConnectorDelivery') && ConnectorDelivery::mode() === 'shadow',
            'global_kill_switch_on'=>!empty($settings['global_kill_switch']),
            'controlled_replay_available'=>method_exists(self::class, 'authoriseReplay') && method_exists(self::class, 'executeReplay'),
            'stale_lock_recovery_available'=>method_exists(self::class, 'recoverStaleLocks'),
            'scoped_kill_controls_available'=>method_exists(self::class, 'isBlocked'),
            'eligibility_fingerprint_available'=>preg_match('/^[a-f0-9]{64}$/', self::eligibilityFingerprint()) === 1,
            'full_payload_still_blocked'=>self::FULL_OPERATIONAL_PAYLOAD_ENABLED === false,
            'general_production_still_disabled'=>self::GENERAL_PRODUCTION_ENABLED === false,
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$health];
    }

    public static function probeBroker() {
        if (!class_exists('\\RoutesPro\\Cloud\\CredentialLifecycle')) {
            return new \WP_Error('fieldflow_5e_5d_missing', 'A Foundation 5D não está disponível.');
        }
        $probe = CredentialLifecycle::probeBroker();
        if (is_wp_error($probe)) return $probe;
        $ok = !empty($probe['ok'])
            && version_compare((string)($probe['version'] ?? '0.0.0'), '1.8.0', '>=')
            && !empty($probe['key_vault_only'])
            && !empty($probe['test_secret_ready'])
            && empty($probe['general_activation_enabled'])
            && empty($probe['delivery_enabled']);
        $probe['ok'] = $ok;
        $probe['governance_recovery_requires_new_broker'] = false;
        $probe['broker_preserved_for_5e'] = true;
        return $probe;
    }

    public static function appendLedger(string $eventName, array $context = [], array $details = [], string $reason = ''): array {
        global $wpdb;
        if (!self::tableReady()) return ['ok'=>false,'reason'=>'governance_ledger_not_ready'];
        $eventName = self::normaliseKey($eventName, 96, 'governance.unknown');
        $entityType = self::normaliseKey((string)($context['entity_type'] ?? 'governance'), 64, 'governance');
        $entityRef = substr(sanitize_text_field((string)($context['entity_ref'] ?? '')), 0, 191);
        $destinationUuid = self::normaliseDestinationUuid((string)($context['destination_uuid'] ?? ''));
        $correlationId = substr(sanitize_text_field((string)($context['correlation_id'] ?? (class_exists('\\RoutesPro\\Core\\AuditLog') ? AuditLog::correlationId() : wp_generate_uuid4()))), 0, 64);
        $source = self::normaliseKey((string)($context['source'] ?? (class_exists('\\RoutesPro\\Core\\AuditLog') ? AuditLog::source() : 'system')), 32, 'system');
        $reason = substr(sanitize_text_field($reason), 0, 500);
        $details = self::sanitizeDetails($details);
        $detailsJson = wp_json_encode(self::canonicalize($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($detailsJson)) $detailsJson = '{}';
        $entryUuid = wp_generate_uuid4();
        $createdAt = current_time('mysql', true);

        $wpdb->query('START TRANSACTION');
        try {
            $previous = $wpdb->get_row('SELECT id,entry_hash FROM ' . self::table() . ' ORDER BY id DESC LIMIT 1 FOR UPDATE', ARRAY_A) ?: [];
            $previousHash = strtolower((string)($previous['entry_hash'] ?? str_repeat('0', 64)));
            if (!preg_match('/^[a-f0-9]{64}$/', $previousHash)) $previousHash = str_repeat('0', 64);
            $entryHash = self::ledgerHash([
                'entry_uuid'=>$entryUuid,
                'event_name'=>$eventName,
                'entity_type'=>$entityType,
                'entity_ref'=>$entityRef,
                'actor_user_id'=>get_current_user_id() ?: 0,
                'source'=>$source,
                'client_id'=>absint($context['client_id'] ?? 0),
                'project_id'=>absint($context['project_id'] ?? 0),
                'destination_uuid'=>$destinationUuid,
                'correlation_id'=>$correlationId,
                'reason'=>$reason,
                'details_json'=>$detailsJson,
                'previous_hash'=>$previousHash,
                'created_at'=>$createdAt,
            ]);
            $inserted = $wpdb->insert(self::table(), [
                'entry_uuid'=>$entryUuid,
                'event_name'=>$eventName,
                'entity_type'=>$entityType,
                'entity_ref'=>$entityRef !== '' ? $entityRef : null,
                'actor_user_id'=>get_current_user_id() ?: null,
                'source'=>$source,
                'client_id'=>!empty($context['client_id']) ? absint($context['client_id']) : null,
                'project_id'=>!empty($context['project_id']) ? absint($context['project_id']) : null,
                'destination_uuid'=>$destinationUuid !== '' ? $destinationUuid : null,
                'correlation_id'=>$correlationId,
                'reason'=>$reason !== '' ? $reason : null,
                'details_json'=>$detailsJson,
                'previous_hash'=>$previousHash,
                'entry_hash'=>$entryHash,
                'created_at'=>$createdAt,
            ]);
            if (!$inserted) throw new \RuntimeException('Não foi possível inserir o evento no governance ledger.');
            $id = (int)$wpdb->insert_id;
            $wpdb->query('COMMIT');
            return ['ok'=>true,'id'=>$id,'entry_uuid'=>$entryUuid,'entry_hash'=>$entryHash,'previous_hash'=>$previousHash];
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            return ['ok'=>false,'reason'=>'governance_ledger_insert_failed','message'=>$error->getMessage()];
        }
    }

    public static function verifyLedgerChain(): array {
        global $wpdb;
        if (!self::tableReady()) return ['ok'=>false,'entries'=>0,'reason'=>'table_not_ready'];
        $rows = $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY id ASC', ARRAY_A) ?: [];
        $expectedPrevious = str_repeat('0', 64);
        foreach ($rows as $index=>$row) {
            $previous = strtolower((string)($row['previous_hash'] ?? ''));
            $stored = strtolower((string)($row['entry_hash'] ?? ''));
            if (!hash_equals($expectedPrevious, $previous)) {
                return ['ok'=>false,'entries'=>count($rows),'broken_at'=>(int)($row['id'] ?? 0),'reason'=>'previous_hash_mismatch'];
            }
            $calculated = self::ledgerHash([
                'entry_uuid'=>(string)$row['entry_uuid'],
                'event_name'=>(string)$row['event_name'],
                'entity_type'=>(string)$row['entity_type'],
                'entity_ref'=>(string)($row['entity_ref'] ?? ''),
                'actor_user_id'=>absint($row['actor_user_id'] ?? 0),
                'source'=>(string)$row['source'],
                'client_id'=>absint($row['client_id'] ?? 0),
                'project_id'=>absint($row['project_id'] ?? 0),
                'destination_uuid'=>(string)($row['destination_uuid'] ?? ''),
                'correlation_id'=>(string)($row['correlation_id'] ?? ''),
                'reason'=>(string)($row['reason'] ?? ''),
                'details_json'=>(string)($row['details_json'] ?? '{}'),
                'previous_hash'=>$previous,
                'created_at'=>(string)$row['created_at'],
            ]);
            if (!preg_match('/^[a-f0-9]{64}$/', $stored) || !hash_equals($calculated, $stored)) {
                return ['ok'=>false,'entries'=>count($rows),'broken_at'=>(int)($row['id'] ?? 0),'reason'=>'entry_hash_mismatch'];
            }
            $expectedPrevious = $stored;
        }
        return ['ok'=>true,'entries'=>count($rows),'last_hash'=>$expectedPrevious,'reason'=>''];
    }

    public static function recentLedger(int $limit = 30): array {
        global $wpdb;
        if (!self::tableReady()) return [];
        $limit = max(1, min(100, $limit));
        return $wpdb->get_results('SELECT id,entry_uuid,event_name,entity_type,entity_ref,actor_user_id,source,client_id,project_id,destination_uuid,correlation_id,reason,previous_hash,entry_hash,created_at FROM ' . self::table() . ' ORDER BY id DESC LIMIT ' . $limit, ARRAY_A) ?: [];
    }

    public static function deadJobs(int $limit = 20): array {
        $rows = class_exists('\RoutesPro\Cloud\ConnectorDelivery') ? ConnectorDelivery::recent(max(20, min(100, $limit * 4))) : [];
        $out = [];
        foreach ($rows as $row) {
            if ((string)($row['status'] ?? '') !== 'dead') continue;
            $out[] = $row;
            if (count($out) >= max(1, min(50, $limit))) break;
        }
        return $out;
    }

    public static function recoverySnapshotState(): array {
        $stored = get_option(self::SNAPSHOT_OPTION, []);
        if (!is_array($stored)) return [];
        return [
            'sha256'=>preg_match('/^[a-f0-9]{64}$/', (string)($stored['sha256'] ?? '')) ? (string)$stored['sha256'] : '',
            'created_at'=>sanitize_text_field((string)($stored['created_at'] ?? '')),
            'created_by'=>absint($stored['created_by'] ?? 0),
            'counts'=>is_array($stored['snapshot']['counts'] ?? null) ? $stored['snapshot']['counts'] : [],
        ];
    }

    public static function isBlocked(int $clientId = 0, string $destinationUuid = '', string $eventName = ''): array {
        $settings = self::settings();
        $reasons = [];
        if (!empty($settings['global_kill_switch'])) $reasons[] = 'global_kill_switch';
        if ($clientId > 0 && in_array($clientId, (array)$settings['blocked_client_ids'], true)) $reasons[] = 'client_kill_switch';
        $destinationUuid = self::normaliseDestinationUuid($destinationUuid);
        if ($destinationUuid !== '' && in_array($destinationUuid, (array)$settings['blocked_destination_uuids'], true)) $reasons[] = 'destination_kill_switch';
        $eventName = self::normaliseEventPattern($eventName);
        if ($eventName !== '' && in_array($eventName, (array)$settings['blocked_event_patterns'], true)) $reasons[] = 'event_kill_switch';
        return ['blocked'=>!empty($reasons),'reasons'=>$reasons];
    }

    public static function certifyLedgerAndKillControls(): array {
        $pre = self::localProbe();
        $checks = (array)($pre['checks'] ?? []);
        if (empty($pre['ok'])) return ['ok'=>false,'checks'=>$checks,'reason'=>'5e_preflight_failed'];

        $before = self::settings();
        $syntheticDestination = 'ff_d_' . str_repeat('e', 24);
        $syntheticClient = 987654321;
        $syntheticEvent = 'governance.certification';
        $temp = $before;
        $temp['blocked_client_ids'] = array_values(array_unique(array_merge((array)$before['blocked_client_ids'], [$syntheticClient])));
        $temp['blocked_destination_uuids'] = array_values(array_unique(array_merge((array)$before['blocked_destination_uuids'], [$syntheticDestination])));
        $temp['blocked_event_patterns'] = array_values(array_unique(array_merge((array)$before['blocked_event_patterns'], [$syntheticEvent])));
        update_option(self::OPTION, $temp, false);
        $clientBlock = in_array('client_kill_switch', self::isBlocked($syntheticClient, '', '')['reasons'], true);
        $destinationBlock = in_array('destination_kill_switch', self::isBlocked(0, $syntheticDestination, '')['reasons'], true);
        $eventBlock = in_array('event_kill_switch', self::isBlocked(0, '', $syntheticEvent)['reasons'], true);
        update_option(self::OPTION, $before, false);

        $first = self::appendLedger('governance.certification.ledger_a', ['entity_type'=>'governance_certification','entity_ref'=>'ledger_a'], ['phase'=>self::PHASE,'synthetic'=>true], 'Certificação sintética do ledger 5E.');
        $second = self::appendLedger('governance.certification.ledger_b', ['entity_type'=>'governance_certification','entity_ref'=>'ledger_b'], ['phase'=>self::PHASE,'synthetic'=>true], 'Validação da cadeia hash 5E.');
        $chain = self::verifyLedgerChain();
        $checks = array_merge($checks, [
            'global_kill_switch_effective'=>in_array('global_kill_switch', self::isBlocked(0, '', '')['reasons'], true),
            'client_kill_switch_policy'=>$clientBlock,
            'destination_kill_switch_policy'=>$destinationBlock,
            'event_kill_switch_policy'=>$eventBlock,
            'ledger_append_first_ok'=>!empty($first['ok']),
            'ledger_append_second_ok'=>!empty($second['ok']),
            'ledger_hash_chain_valid'=>!empty($chain['ok']),
            'ledger_previous_hash_linked'=>!empty($first['entry_hash']) && !empty($second['previous_hash']) && hash_equals((string)$first['entry_hash'], (string)$second['previous_hash']),
            'production_still_disabled'=>self::GENERAL_PRODUCTION_ENABLED === false,
        ]);
        $ok = !in_array(false, $checks, true);
        self::setCertificationFlag('ledger_certified', $ok);
        self::appendLedger($ok ? 'governance.ledger.certified' : 'governance.ledger.failed', ['entity_type'=>'phase','entity_ref'=>'5E'], ['checks'=>$checks], $ok ? 'Ledger e kill controls certificados.' : 'Certificação de ledger e kill controls falhou.');
        return ['ok'=>$ok,'checks'=>$checks,'ledger'=>$chain,'reason'=>$ok ? 'ledger_kill_controls_certified' : 'ledger_kill_controls_failed'];
    }

    public static function authoriseReplay(int $jobId, string $reason): array {
        global $wpdb;
        if (!current_user_can('routespro_manage')) return ['ok'=>false,'reason'=>'replay_permission_denied'];
        $reason = trim(sanitize_text_field($reason));
        if (strlen($reason) < 10) return ['ok'=>false,'reason'=>'replay_reason_required','message'=>'O replay exige uma justificação com pelo menos 10 caracteres.'];
        if (!ConnectorDelivery::tableReady()) return ['ok'=>false,'reason'=>'connector_queue_not_ready'];
        $job = $wpdb->get_row($wpdb->prepare('SELECT id,delivery_key,destination_uuid,event_uuid,event_name,client_id,project_id,execution_mode,status,attempts,max_attempts FROM ' . ConnectorDelivery::table() . ' WHERE id=%d LIMIT 1', $jobId), ARRAY_A) ?: [];
        if (!$job || (string)($job['status'] ?? '') !== 'dead') return ['ok'=>false,'reason'=>'job_not_dead'];
        $ticketUuid = wp_generate_uuid4();
        $tickets = get_option(self::REPLAY_OPTION, []);
        if (!is_array($tickets)) $tickets = [];
        $tickets[$ticketUuid] = [
            'ticket_uuid'=>$ticketUuid,
            'job_id'=>$jobId,
            'delivery_key'=>(string)$job['delivery_key'],
            'reason'=>$reason,
            'authorised_by'=>get_current_user_id() ?: 0,
            'expires_at'=>time() + self::REPLAY_TTL_SECONDS,
            'used'=>false,
        ];
        update_option(self::REPLAY_OPTION, $tickets, false);
        self::appendLedger('governance.replay.authorised', [
            'entity_type'=>'connector_job','entity_ref'=>(string)$jobId,
            'client_id'=>absint($job['client_id'] ?? 0),'project_id'=>absint($job['project_id'] ?? 0),
            'destination_uuid'=>(string)($job['destination_uuid'] ?? ''),
        ], ['delivery_key'=>(string)$job['delivery_key'],'event_uuid'=>(string)$job['event_uuid'],'event_name'=>(string)$job['event_name'],'ticket_uuid'=>$ticketUuid], $reason);
        return ['ok'=>true,'ticket_uuid'=>$ticketUuid,'expires_at'=>$tickets[$ticketUuid]['expires_at'],'job'=>$job];
    }

    public static function executeReplay(string $ticketUuid): array {
        global $wpdb;
        if (!current_user_can('routespro_manage')) return ['ok'=>false,'reason'=>'replay_permission_denied'];
        $ticketUuid = strtolower(trim($ticketUuid));
        $tickets = get_option(self::REPLAY_OPTION, []);
        if (!is_array($tickets) || !is_array($tickets[$ticketUuid] ?? null)) return ['ok'=>false,'reason'=>'replay_ticket_missing'];
        $ticket = $tickets[$ticketUuid];
        if (!empty($ticket['used'])) return ['ok'=>false,'reason'=>'replay_ticket_used'];
        if ((int)($ticket['expires_at'] ?? 0) < time()) return ['ok'=>false,'reason'=>'replay_ticket_expired'];
        if ((int)($ticket['authorised_by'] ?? 0) !== (get_current_user_id() ?: 0)) return ['ok'=>false,'reason'=>'replay_actor_mismatch'];
        $jobId = absint($ticket['job_id'] ?? 0);
        $job = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ConnectorDelivery::table() . ' WHERE id=%d LIMIT 1', $jobId), ARRAY_A) ?: [];
        if (!$job || (string)($job['status'] ?? '') !== 'dead') return ['ok'=>false,'reason'=>'job_not_dead'];
        if (!hash_equals((string)$ticket['delivery_key'], (string)$job['delivery_key'])) return ['ok'=>false,'reason'=>'delivery_key_changed'];
        $now = current_time('mysql', true);
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE " . ConnectorDelivery::table() . " SET status='pending',attempts=0,available_at=%s,locked_at=NULL,lock_token=NULL,completed_at=NULL,last_http_code=NULL,last_error_code='governance_replay_authorised',last_error_message=%s,updated_at=%s WHERE id=%d AND status='dead'",
            $now, substr((string)$ticket['reason'], 0, 500), $now, $jobId
        ));
        if ($updated !== 1) return ['ok'=>false,'reason'=>'replay_update_failed'];
        $tickets[$ticketUuid]['used'] = true;
        $tickets[$ticketUuid]['used_at'] = time();
        update_option(self::REPLAY_OPTION, $tickets, false);
        self::appendLedger('governance.replay.executed', [
            'entity_type'=>'connector_job','entity_ref'=>(string)$jobId,
            'client_id'=>absint($job['client_id'] ?? 0),'project_id'=>absint($job['project_id'] ?? 0),
            'destination_uuid'=>(string)($job['destination_uuid'] ?? ''),
        ], ['delivery_key'=>(string)$job['delivery_key'],'ticket_uuid'=>$ticketUuid,'status'=>'pending'], (string)$ticket['reason']);
        return ['ok'=>true,'job_id'=>$jobId,'delivery_key'=>(string)$job['delivery_key'],'status'=>'pending'];
    }

    public static function certifyDeadLetterReplay(): array {
        global $wpdb;
        $pre = self::localProbe();
        $checks = (array)($pre['checks'] ?? []);
        if (empty($pre['ok'])) return ['ok'=>false,'checks'=>$checks,'reason'=>'5e_preflight_failed'];
        $job = self::createSyntheticJob('dead');
        if (empty($job['ok'])) return ['ok'=>false,'checks'=>$checks + ['synthetic_dead_job_created'=>false],'reason'=>'synthetic_dead_job_failed'];
        $shortReason = self::authoriseReplay((int)$job['job_id'], 'curta');
        $auth = self::authoriseReplay((int)$job['job_id'], 'Certificação 5E de replay controlado e idempotente.');
        $exec = !empty($auth['ok']) ? self::executeReplay((string)$auth['ticket_uuid']) : ['ok'=>false];
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ConnectorDelivery::table() . ' WHERE id=%d LIMIT 1', (int)$job['job_id']), ARRAY_A) ?: [];
        $count = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . ConnectorDelivery::table() . ' WHERE delivery_key=%s', (string)$job['delivery_key']));
        $checks = array_merge($checks, [
            'synthetic_dead_job_created'=>!empty($job['ok']),
            'replay_reason_required'=>empty($shortReason['ok']) && (string)($shortReason['reason'] ?? '') === 'replay_reason_required',
            'replay_ticket_authorised'=>!empty($auth['ok']),
            'replay_ticket_executed'=>!empty($exec['ok']),
            'job_returned_to_pending'=>(string)($row['status'] ?? '') === 'pending',
            'attempts_reset'=>(int)($row['attempts'] ?? -1) === 0,
            'single_delivery_key_row'=>$count === 1,
            'dispatcher_remains_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
            'global_kill_switch_still_on'=>!empty(self::settings()['global_kill_switch']),
        ]);
        $ok = !in_array(false, $checks, true);
        self::cleanupSyntheticJob((int)$job['job_id']);
        self::setCertificationFlag('replay_certified', $ok);
        self::appendLedger($ok ? 'governance.replay.certified' : 'governance.replay.failed', ['entity_type'=>'phase','entity_ref'=>'5E'], ['checks'=>$checks], $ok ? 'Dead letter e replay controlado certificados.' : 'Certificação de replay falhou.');
        return ['ok'=>$ok,'checks'=>$checks,'reason'=>$ok ? 'dead_letter_replay_certified' : 'dead_letter_replay_failed','job_cleaned_up'=>true];
    }

    public static function recoverStaleLocks(bool $syntheticOnly = false): array {
        global $wpdb;
        if (!ConnectorDelivery::tableReady()) return ['ok'=>false,'recovered'=>0,'dead'=>0,'reason'=>'connector_queue_not_ready'];
        $seconds = (int)self::settings()['stale_lock_seconds'];
        $cutoff = gmdate('Y-m-d H:i:s', time() - $seconds);
        $where = "status='running' AND locked_at IS NOT NULL AND locked_at<%s";
        if ($syntheticOnly) $where .= " AND execution_mode='governance_test'";
        $rows = $wpdb->get_results($wpdb->prepare('SELECT id,attempts,max_attempts,destination_uuid,delivery_key,client_id,project_id FROM ' . ConnectorDelivery::table() . ' WHERE ' . $where . ' ORDER BY id ASC LIMIT 200', $cutoff), ARRAY_A) ?: [];
        $recovered = 0; $dead = 0;
        $now = current_time('mysql', true);
        foreach ($rows as $row) {
            $terminal = (int)($row['attempts'] ?? 0) >= (int)($row['max_attempts'] ?? 1);
            $status = $terminal ? 'dead' : 'pending';
            $updated = $wpdb->update(ConnectorDelivery::table(), [
                'status'=>$status,
                'available_at'=>$now,
                'locked_at'=>null,
                'lock_token'=>null,
                'completed_at'=>$terminal ? $now : null,
                'last_error_code'=>'stale_lock_recovered_5e',
                'last_error_message'=>'Lock stale recuperado pela governance 5E.',
                'updated_at'=>$now,
            ], ['id'=>(int)$row['id']]);
            if ($updated !== false) {
                $recovered++;
                if ($terminal) $dead++;
                self::appendLedger('governance.recovery.stale_lock', [
                    'entity_type'=>'connector_job','entity_ref'=>(string)$row['id'],
                    'client_id'=>absint($row['client_id'] ?? 0),'project_id'=>absint($row['project_id'] ?? 0),
                    'destination_uuid'=>(string)($row['destination_uuid'] ?? ''),
                ], ['delivery_key'=>(string)$row['delivery_key'],'recovered_status'=>$status], 'Recuperação de lock stale 5E.');
            }
        }
        return ['ok'=>true,'recovered'=>$recovered,'dead'=>$dead,'cutoff'=>$cutoff];
    }

    public static function createRecoverySnapshot(): array {
        $snapshot = self::controlPlaneSnapshot();
        $hash = hash('sha256', (string)wp_json_encode(self::canonicalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $stored = ['snapshot'=>$snapshot,'sha256'=>$hash,'created_at'=>gmdate('c'),'created_by'=>get_current_user_id() ?: 0];
        update_option(self::SNAPSHOT_OPTION, $stored, false);
        self::appendLedger('governance.recovery.snapshot_created', ['entity_type'=>'control_plane','entity_ref'=>'fieldflow'], ['snapshot_sha256'=>$hash,'counts'=>$snapshot['counts'] ?? []], 'Snapshot de recovery 5E criado sem segredos.');
        return ['ok'=>true,'sha256'=>$hash,'snapshot'=>$snapshot];
    }

    public static function certifyRecovery(): array {
        global $wpdb;
        $pre = self::localProbe();
        $checks = (array)($pre['checks'] ?? []);
        if (empty($pre['ok'])) return ['ok'=>false,'checks'=>$checks,'reason'=>'5e_preflight_failed'];
        $job = self::createSyntheticJob('running_stale');
        if (empty($job['ok'])) return ['ok'=>false,'checks'=>$checks + ['synthetic_stale_job_created'=>false],'reason'=>'synthetic_stale_job_failed'];
        $snapshotA = self::controlPlaneSnapshot();
        $snapshotB = self::controlPlaneSnapshot();
        $hashA = hash('sha256', (string)wp_json_encode(self::canonicalize($snapshotA), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $hashB = hash('sha256', (string)wp_json_encode(self::canonicalize($snapshotB), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $storedSnapshot = self::createRecoverySnapshot();
        $recovery = self::recoverStaleLocks(true);
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . ConnectorDelivery::table() . ' WHERE id=%d LIMIT 1', (int)$job['job_id']), ARRAY_A) ?: [];
        $count = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . ConnectorDelivery::table() . ' WHERE delivery_key=%s', (string)$job['delivery_key']));

        $oldCircuit = get_option(ControlledExternalDelivery::CIRCUIT_OPTION, []);
        $syntheticUuid = (string)$job['destination_uuid'];
        $circuit = is_array($oldCircuit) ? $oldCircuit : [];
        $circuit[strtolower($syntheticUuid)] = ['consecutive_failures'=>9,'open_until'=>time()+900];
        update_option(ControlledExternalDelivery::CIRCUIT_OPTION, $circuit, false);
        ControlledExternalDelivery::resetCircuit($syntheticUuid);
        $afterCircuit = get_option(ControlledExternalDelivery::CIRCUIT_OPTION, []);
        $circuitReset = !is_array($afterCircuit) || !isset($afterCircuit[strtolower($syntheticUuid)]);
        update_option(ControlledExternalDelivery::CIRCUIT_OPTION, is_array($oldCircuit) ? $oldCircuit : [], false);

        $checks = array_merge($checks, [
            'synthetic_stale_job_created'=>!empty($job['ok']),
            'control_plane_snapshot_deterministic'=>hash_equals($hashA, $hashB),
            'recovery_snapshot_persisted'=>!empty($storedSnapshot['ok']) && preg_match('/^[a-f0-9]{64}$/', (string)($storedSnapshot['sha256'] ?? '')) === 1,
            'stale_lock_recovered'=>!empty($recovery['ok']) && (int)($recovery['recovered'] ?? 0) >= 1,
            'recovered_job_pending'=>(string)($row['status'] ?? '') === 'pending',
            'recovered_job_unlocked'=>empty($row['locked_at']) && empty($row['lock_token']),
            'single_delivery_key_after_recovery'=>$count === 1,
            'circuit_breaker_reset_control'=>$circuitReset,
            'dispatcher_still_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
            'global_kill_switch_still_on'=>!empty(self::settings()['global_kill_switch']),
        ]);
        $ok = !in_array(false, $checks, true);
        self::cleanupSyntheticJob((int)$job['job_id']);
        self::setCertificationFlag('recovery_certified', $ok);
        self::appendLedger($ok ? 'governance.recovery.certified' : 'governance.recovery.failed', ['entity_type'=>'phase','entity_ref'=>'5E'], ['checks'=>$checks,'snapshot_sha256'=>$storedSnapshot['sha256'] ?? ''], $ok ? 'Recovery, snapshot e circuit controls certificados.' : 'Certificação de recovery falhou.');
        return ['ok'=>$ok,'checks'=>$checks,'reason'=>$ok ? 'recovery_certified' : 'recovery_failed','snapshot_sha256'=>(string)($storedSnapshot['sha256'] ?? ''),'job_cleaned_up'=>true];
    }

    public static function certifyFinalGate(): array {
        $pre = self::localProbe();
        $cert = self::certificationState();
        $ledger = self::verifyLedgerChain();
        $production = ProductionDeliveryGate::health();
        $credentials = CredentialLifecycle::health();
        $checks = array_merge((array)($pre['checks'] ?? []), [
            'ledger_controls_certified'=>!empty($cert['ledger_certified']),
            'dead_letter_replay_certified'=>!empty($cert['replay_certified']),
            'recovery_controls_certified'=>!empty($cert['recovery_certified']),
            'ledger_chain_valid_final'=>!empty($ledger['ok']),
            '5c8_production_ready'=>(int)($production['production_ready_gates'] ?? 0) > 0,
            '5d_credential_ready'=>(int)($credentials['ready_credentials'] ?? 0) > 0,
            'shadow_mode_preserved'=>ConnectorDelivery::mode() === 'shadow',
            'global_kill_switch_on'=>!empty(self::settings()['global_kill_switch']),
            'operational_dispatcher_locked'=>!ConnectorDelivery::networkDeliveryEnabled(),
            'full_payload_blocked'=>self::FULL_OPERATIONAL_PAYLOAD_ENABLED === false,
            'production_enabled_false'=>self::GENERAL_PRODUCTION_ENABLED === false,
        ]);
        $ok = !in_array(false, $checks, true);
        self::setCertificationFlag('final_gate_certified', $ok);
        if ($ok) {
            $state = self::certificationState();
            $state['production_eligible'] = true;
            $state['production_eligible_at'] = time();
            $state['certified_by'] = get_current_user_id() ?: 0;
            $state['eligibility_fingerprint_sha256'] = self::eligibilityFingerprint();
            $snapshot = self::createRecoverySnapshot();
            $state['final_snapshot_sha256'] = (string)($snapshot['sha256'] ?? '');
            $ledgerAfter = self::verifyLedgerChain();
            $state['ledger_last_hash'] = (string)($ledgerAfter['last_hash'] ?? '');
            update_option(self::CERT_OPTION, $state, false);
        }
        self::appendLedger($ok ? 'governance.final_gate.certified' : 'governance.final_gate.failed', ['entity_type'=>'phase','entity_ref'=>'5E'], ['checks'=>$checks,'production_eligible'=>$ok,'production_enabled'=>false], $ok ? 'Fase 5E certificada como PRODUCTION ELIGIBLE, produção ainda desligada.' : 'A gate final 5E não passou.');
        return [
            'ok'=>$ok,
            'checks'=>$checks,
            'reason'=>$ok ? 'production_eligible_5e' : 'final_gate_failed',
            'production_eligible'=>$ok,
            'production_enabled'=>false,
            'message'=>$ok ? '5E READY. A plataforma está PRODUCTION ELIGIBLE. Na Stable 2.2.376 a produção continua explicitamente DESLIGADA por defeito e a baseline é certificada separadamente.' : 'A 5E ainda tem checks por fechar.',
        ];
    }

    public static function resetCertification(): array {
        delete_option(self::CERT_OPTION);
        delete_option(self::REPLAY_OPTION);
        self::appendLedger('governance.certification.reset', ['entity_type'=>'phase','entity_ref'=>'5E'], ['production_enabled'=>false], 'Estado de certificação 5E reiniciado. O ledger não foi apagado.');
        return ['ok'=>true,'reason'=>'5e_certification_reset'];
    }

    public static function summary(): array {
        $health = self::health();
        $cert = self::certificationState();
        return [
            'ledger_entries'=>(int)($health['ledger_entries'] ?? 0),
            'ledger_valid'=>!empty($health['ledger_chain_valid']),
            'ledger_certified'=>!empty($cert['ledger_certified']),
            'replay_certified'=>!empty($cert['replay_certified']),
            'recovery_certified'=>!empty($cert['recovery_certified']),
            'final_gate_certified'=>!empty($cert['final_gate_certified']),
            'production_eligible'=>!empty($health['production_eligible']),
            'eligibility_fingerprint_match'=>!empty($health['eligibility_fingerprint_match']),
            'production_enabled'=>false,
            '5c8_ready'=>(int)($health['production_5c8_ready_gates'] ?? 0),
            '5d_ready'=>(int)($health['credential_5d_ready'] ?? 0),
            'dead_letter_count'=>(int)($health['dead_letter_count'] ?? 0),
            'global_kill_switch'=>!empty($health['global_kill_switch']),
        ];
    }

    public static function recordAdminAction(string $action, $result, array $post = []): void {
        if (!self::tableReady() || $action === '') return;
        $destinationId = absint($post['destination_id'] ?? $post['connector_5d_destination_id'] ?? $post['connector_5c8_destination_id'] ?? $post['connector_5c7_destination_id'] ?? $post['connector_5c6_destination_id'] ?? $post['connector_5c5_destination_id'] ?? 0);
        $destination = $destinationId > 0 ? DestinationRegistry::getDestination($destinationId) : [];
        $details = [
            'action'=>sanitize_key($action),
            'result'=>is_wp_error($result) || $result === false ? 'error' : 'success',
        ];
        foreach (['rule_id','enable','type_key','client_id','project_id','event_pattern','auth_strategy'] as $key) {
            if (!array_key_exists($key, $post)) continue;
            $details[$key] = in_array($key, ['rule_id','client_id','project_id'], true) ? absint($post[$key]) : sanitize_text_field((string)$post[$key]);
        }
        if (!empty($post['connector_5d_secret_ref'])) {
            $details['secret_ref_sha256'] = hash('sha256', strtolower(trim((string)$post['connector_5d_secret_ref'])));
        }
        if (is_wp_error($result)) $details['error_code'] = sanitize_key($result->get_error_code());
        self::appendLedger('admin.data_destination.' . sanitize_key($action), [
            'entity_type'=>'data_destination_action',
            'entity_ref'=>$destinationId > 0 ? (string)$destinationId : '',
            'client_id'=>absint($destination['client_id'] ?? $post['client_id'] ?? 0),
            'project_id'=>absint($destination['project_id'] ?? $post['project_id'] ?? 0),
            'destination_uuid'=>(string)($destination['destination_uuid'] ?? ''),
        ], $details, 'Ação administrativa registada automaticamente pela governance 5E.');
    }

    private static function queueStats(): array {
        global $wpdb;
        if (!ConnectorDelivery::tableReady()) return ['dead'=>0,'stale_running'=>0];
        $seconds = (int)self::settings()['stale_lock_seconds'];
        $cutoff = gmdate('Y-m-d H:i:s', time() - $seconds);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(status='dead') AS dead,SUM(status='running' AND locked_at IS NOT NULL AND locked_at<%s) AS stale_running FROM " . ConnectorDelivery::table(),
            $cutoff
        ), ARRAY_A) ?: [];
        return ['dead'=>(int)($row['dead'] ?? 0),'stale_running'=>(int)($row['stale_running'] ?? 0)];
    }

    private static function createSyntheticJob(string $state): array {
        global $wpdb;
        if (!ConnectorDelivery::tableReady()) return ['ok'=>false,'reason'=>'connector_queue_not_ready'];
        $eventUuid = wp_generate_uuid4();
        $destinationUuid = 'ff_d_' . substr(hash('sha256', $eventUuid . '|5e'), 0, 24);
        $deliveryKey = 'ff_del_' . substr(hash('sha1', $eventUuid . '|' . $destinationUuid . '|5e'), 0, 40);
        $now = current_time('mysql', true);
        $lockedAt = null;
        $lockToken = null;
        $status = 'dead';
        $attempts = 2;
        $maxAttempts = 2;
        $completedAt = $now;
        if ($state === 'running_stale') {
            $status = 'running';
            $attempts = 1;
            $maxAttempts = 3;
            $lockedAt = gmdate('Y-m-d H:i:s', time() - max(self::DEFAULT_STALE_LOCK_SECONDS + 300, (int)self::settings()['stale_lock_seconds'] + 300));
            $lockToken = wp_generate_uuid4();
            $completedAt = null;
        }
        $payload = [
            'contract'=>'fieldflow.governance.recovery.test.v1',
            'phase'=>self::PHASE,
            'synthetic'=>true,
            'event_uuid'=>$eventUuid,
            'delivery_key'=>$deliveryKey,
        ];
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insert = $wpdb->insert(ConnectorDelivery::table(), [
            'delivery_key'=>$deliveryKey,
            'tenant_id'=>TenantIdentity::tenantId(),
            'installation_id'=>TenantIdentity::installationId(),
            'destination_id'=>0,
            'destination_uuid'=>$destinationUuid,
            'connector_type'=>'webhook',
            'event_uuid'=>$eventUuid,
            'event_name'=>'governance.recovery.certification',
            'client_id'=>0,
            'project_id'=>0,
            'execution_mode'=>'governance_test',
            'status'=>$status,
            'attempts'=>$attempts,
            'max_attempts'=>$maxAttempts,
            'base_delay_seconds'=>60,
            'available_at'=>$now,
            'locked_at'=>$lockedAt,
            'lock_token'=>$lockToken,
            'completed_at'=>$completedAt,
            'last_http_code'=>503,
            'last_error_code'=>$status === 'dead' ? 'governance_synthetic_dead' : 'governance_synthetic_stale_lock',
            'last_error_message'=>'Job sintético 5E, sem transmissão de rede.',
            'payload_sha256'=>hash('sha256', (string)$body),
            'payload_json'=>(string)$body,
            'created_at'=>$now,
            'updated_at'=>$now,
        ]);
        if (!$insert) return ['ok'=>false,'reason'=>'synthetic_job_insert_failed'];
        return ['ok'=>true,'job_id'=>(int)$wpdb->insert_id,'delivery_key'=>$deliveryKey,'destination_uuid'=>$destinationUuid,'event_uuid'=>$eventUuid];
    }

    private static function cleanupSyntheticJob(int $jobId): void {
        global $wpdb;
        if ($jobId <= 0 || !ConnectorDelivery::tableReady()) return;
        $wpdb->query($wpdb->prepare("DELETE FROM " . ConnectorDelivery::table() . " WHERE id=%d AND execution_mode='governance_test'", $jobId));
    }

    private static function controlPlaneSnapshot(): array {
        $destinations = [];
        foreach (DestinationRegistry::listDestinations() as $destination) {
            $destinations[] = [
                'uuid'=>(string)$destination['destination_uuid'],
                'type'=>sanitize_key((string)$destination['type_key']),
                'active'=>!empty($destination['is_active']),
                'client_id'=>absint($destination['client_id'] ?? 0),
                'project_id'=>absint($destination['project_id'] ?? 0),
                'endpoint_sha256'=>hash('sha256', strtolower(trim((string)($destination['endpoint_ref'] ?? '')))),
                'secret_ref_sha256'=>trim((string)($destination['secret_ref'] ?? '')) !== '' ? hash('sha256', strtolower(trim((string)$destination['secret_ref']))) : '',
            ];
        }
        usort($destinations, static fn($a,$b)=>strcmp((string)$a['uuid'], (string)$b['uuid']));
        $rules = [];
        foreach (DestinationRegistry::listRules() as $rule) {
            $rules[] = [
                'uuid'=>(string)($rule['rule_uuid'] ?? ''),
                'destination_id'=>absint($rule['destination_id'] ?? 0),
                'client_id'=>absint($rule['client_id'] ?? 0),
                'project_id'=>absint($rule['project_id'] ?? 0),
                'event_pattern'=>self::normaliseEventPattern((string)($rule['event_pattern'] ?? '')),
                'action'=>sanitize_key((string)($rule['action_name'] ?? '')),
                'active'=>!empty($rule['is_active']),
                'priority'=>absint($rule['priority'] ?? 0),
            ];
        }
        usort($rules, static fn($a,$b)=>strcmp((string)$a['uuid'], (string)$b['uuid']));
        $settings = self::settings();
        $productionGates = [];
        if (class_exists('\RoutesPro\Cloud\ProductionDeliveryGate')) {
            foreach (ProductionDeliveryGate::summary() as $row) {
                $productionGates[] = [
                    'destination_uuid'=>(string)($row['destination_uuid'] ?? ''),
                    'production_ready'=>!empty($row['production_ready']),
                    'event_pattern'=>self::normaliseEventPattern((string)($row['event_pattern'] ?? '')),
                    'max_per_hour'=>absint($row['max_per_hour'] ?? 0),
                    'target_certified'=>!empty($row['target_certified']),
                    'queue_certified'=>!empty($row['queue_certified']),
                ];
            }
            usort($productionGates, static fn($a,$b)=>strcmp((string)$a['destination_uuid'], (string)$b['destination_uuid']));
        }
        $credentials = [];
        if (class_exists('\RoutesPro\Cloud\CredentialLifecycle')) {
            foreach (CredentialLifecycle::summary() as $row) {
                $credentials[] = [
                    'destination_uuid'=>(string)($row['destination_uuid'] ?? ''),
                    'secret_ref_sha256'=>trim((string)($row['secret_ref'] ?? '')) !== '' ? hash('sha256', strtolower(trim((string)$row['secret_ref']))) : '',
                    'certified_version'=>(string)($row['certified_version'] ?? ''),
                    'credential_status'=>sanitize_key((string)($row['credential_status'] ?? '')),
                    'expires_at'=>sanitize_text_field((string)($row['expires_at'] ?? '')),
                    'ready'=>!empty($row['ready']),
                    'revoked'=>!empty($row['revoked']),
                    'rotation_detected'=>!empty($row['rotation_detected']),
                ];
            }
            usort($credentials, static fn($a,$b)=>strcmp((string)$a['destination_uuid'], (string)$b['destination_uuid']));
        }
        return [
            'phase'=>self::PHASE,
            'tenant_id'=>TenantIdentity::tenantId(),
            'installation_id'=>TenantIdentity::installationId(),
            'connector_mode'=>ConnectorDelivery::mode(),
            'connector_kill_switch'=>ConnectorDelivery::killSwitch(),
            'governance_kill_switch'=>!empty($settings['global_kill_switch']),
            'blocked_client_ids'=>(array)$settings['blocked_client_ids'],
            'blocked_destination_uuids'=>(array)$settings['blocked_destination_uuids'],
            'blocked_event_patterns'=>(array)$settings['blocked_event_patterns'],
            'stale_lock_seconds'=>(int)$settings['stale_lock_seconds'],
            'destinations'=>$destinations,
            'routing_rules'=>$rules,
            'production_gates'=>$productionGates,
            'credential_states'=>$credentials,
            'counts'=>[
                'destinations'=>count($destinations),
                'rules'=>count($rules),
                'production_gates'=>count($productionGates),
                'credential_states'=>count($credentials),
            ],
        ];
    }

    private static function eligibilityFingerprint(): string {
        $snapshot = self::controlPlaneSnapshot();
        $encoded = wp_json_encode(self::canonicalize($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', is_string($encoded) ? $encoded : '{}');
    }

    private static function certificationState(): array {
        $state = get_option(self::CERT_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function setCertificationFlag(string $key, bool $value): void {
        $state = self::certificationState();
        $state[$key] = $value;
        $state[$key . '_at'] = time();
        $state[$key . '_by'] = get_current_user_id() ?: 0;
        if (!$value && $key !== 'final_gate_certified') {
            $state['final_gate_certified'] = false;
            $state['production_eligible'] = false;
        }
        update_option(self::CERT_OPTION, $state, false);
    }

    private static function ledgerHash(array $row): string {
        $parts = [
            'fieldflow-governance-ledger-v1',
            (string)($row['entry_uuid'] ?? ''),
            (string)($row['event_name'] ?? ''),
            (string)($row['entity_type'] ?? ''),
            (string)($row['entity_ref'] ?? ''),
            (string)absint($row['actor_user_id'] ?? 0),
            (string)($row['source'] ?? ''),
            (string)absint($row['client_id'] ?? 0),
            (string)absint($row['project_id'] ?? 0),
            (string)($row['destination_uuid'] ?? ''),
            (string)($row['correlation_id'] ?? ''),
            (string)($row['reason'] ?? ''),
            hash('sha256', (string)($row['details_json'] ?? '{}')),
            (string)($row['previous_hash'] ?? ''),
            (string)($row['created_at'] ?? ''),
        ];
        return hash('sha256', implode("\n", $parts));
    }

    private static function sanitizeDetails($value, string $parentKey = '') {
        if (is_object($value)) $value = get_object_vars($value);
        if (!is_array($value)) {
            if (is_string($value) && self::isSensitiveKey($parentKey)) return '[redacted]';
            if (is_string($value)) return substr(sanitize_text_field($value), 0, 1000);
            return $value;
        }
        $out = [];
        foreach ($value as $key=>$item) {
            $keyString = is_string($key) ? $key : (string)$key;
            if (self::isSensitiveKey($keyString)) {
                $out[$key] = '[redacted]';
                continue;
            }
            $out[$key] = self::sanitizeDetails($item, $keyString);
        }
        return $out;
    }

    private static function canonicalize($value) {
        if (!is_array($value)) return $value;
        $isList = array_keys($value) === range(0, count($value) - 1);
        if ($isList) return array_map([self::class, 'canonicalize'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key=>$item) $value[$key] = self::canonicalize($item);
        return $value;
    }

    private static function isSensitiveKey(string $key): bool {
        $key = strtolower(preg_replace('/[^a-z0-9]+/', '_', trim($key)) ?: '');
        if ($key === '') return false;
        foreach (['password','secret','token','api_key','apikey','authorization','cookie','private_key','client_secret','payload_json','payload'] as $needle) {
            if ($key === $needle || strpos($key, $needle . '_') === 0 || substr($key, -strlen('_' . $needle)) === '_' . $needle) return true;
        }
        return false;
    }

    private static function normaliseKey(string $value, int $limit, string $fallback): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._:-]+/', '_', $value) ?: '';
        return substr($value !== '' ? $value : $fallback, 0, $limit);
    }

    private static function normaliseDestinationUuid(string $value): string {
        $value = strtolower(trim($value));
        return preg_match('/^ff_d_[a-f0-9]{24}$/', $value) ? $value : '';
    }

    private static function normaliseEventPattern(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._:-]+/', '', $value) ?: '';
        return substr($value, 0, 96);
    }
}
