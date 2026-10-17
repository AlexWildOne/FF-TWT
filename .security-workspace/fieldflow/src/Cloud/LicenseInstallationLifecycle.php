<?php
namespace RoutesPro\Cloud;

use RoutesPro\Support\Config;
use RoutesPro\Support\LicenseManager;
use RoutesPro\Support\ProductionManifest;
use RoutesPro\Support\RemoteLicenseClient;

if (!defined('ABSPATH')) exit;

/**
 * Phase 6B.2, Activation, Installation Binding & Heartbeat.
 *
 * Safe-state guarantees:
 * - does not enforce a licence at runtime;
 * - does not block FieldFlow when Azure is unavailable;
 * - does not remotely revoke licences;
 * - never persists the raw licence key outside the existing encrypted licence store;
 * - uses /license/validate as the heartbeat contract for backwards compatibility.
 */
final class LicenseInstallationLifecycle {
    public const VERSION = '1.0.1';
    public const PHASE = '6B.2';
    public const IDENTITY_OPTION = 'fieldflow_phase6b2_installation_identity';
    public const HEARTBEAT_OPTION = 'fieldflow_phase6b2_heartbeat_state';
    public const STATE_OPTION = 'fieldflow_phase6b2_certification_state';
    public const CRON_HOOK = 'fieldflow_license_heartbeat';
    public const CRON_SCHEDULE = 'fieldflow_license_hourly_sweep';
    public const HEARTBEAT_LOCK = 'fieldflow_phase6b2_heartbeat_lock';
    public const LICENSE_ENFORCEMENT_ENABLED = false;
    public const INSTALLATION_BINDING_ENFORCED = false;
    public const REMOTE_REVOCATION_ENABLED = false;
    private static ?array $healthCache = null;

    public static function registerHooks(): void {
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        add_action('init', [self::class, 'ensureSchedule'], 30);
        add_action(self::CRON_HOOK, [self::class, 'cronHeartbeat']);
        add_action('fieldflow_license_remote_activated', [self::class, 'onRemoteActivated'], 10, 2);
        add_action('fieldflow_license_remote_deactivated', [self::class, 'onRemoteDeactivated'], 10, 2);
    }

    public static function cronSchedules(array $schedules): array {
        if (!isset($schedules[self::CRON_SCHEDULE])) {
            // The scheduler wakes hourly, while heartbeatDue() enforces the
            // configured 1..72 hour outbound cadence. This keeps settings
            // changes effective without rescheduling or duplicating events.
            $schedules[self::CRON_SCHEDULE] = [
                'interval' => HOUR_IN_SECONDS,
                'display' => 'FieldFlow License Heartbeat cadence sweep',
            ];
        }
        return $schedules;
    }

    public static function ensureSchedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 10 * MINUTE_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
        self::identity();
    }

    public static function cronHeartbeat(): void {
        self::heartbeat(false, 'cron');
    }

    public static function deactivateSchedule(): void {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        delete_transient(self::HEARTBEAT_LOCK);
    }

    public static function identity(): array {
        $saved = get_option(self::IDENTITY_OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $domain = LicenseManager::currentDomain();
        $now = current_time('mysql', true);
        $uuid = sanitize_text_field((string)($saved['installation_uuid'] ?? ''));
        $originDomain = self::normaliseDomain((string)($saved['origin_domain'] ?? ''));
        $needsNew = !self::isUuid($uuid);
        $rotated = false;
        $previousHash = '';

        // If the dedicated identity option is unexpectedly missing, prefer the
        // last installation identity that was accepted and persisted with the
        // current licence. This avoids creating a new identity during a normal
        // plugin upgrade or a transient option-cache anomaly.
        if ($needsNew && $domain !== '') {
            $acceptedUuid = strtolower(sanitize_text_field((string)LicenseManager::get('installation_id', '')));
            $acceptedFingerprint = strtolower(sanitize_text_field((string)LicenseManager::get('installation_fingerprint', '')));
            $expectedFingerprint = self::isUuid($acceptedUuid)
                ? self::installationFingerprint($acceptedUuid, $domain)
                : '';
            if (self::isUuid($acceptedUuid)
                && preg_match('/^[a-f0-9]{64}$/', $acceptedFingerprint) === 1
                && $expectedFingerprint !== ''
                && hash_equals($expectedFingerprint, $acceptedFingerprint)
                && LicenseManager::isBoundToCurrentDomain()) {
                $uuid = $acceptedUuid;
                $saved = [
                    'installation_uuid' => $uuid,
                    'origin_domain' => $domain,
                    'created_at' => $now,
                    'rotated_at' => '',
                    'previous_installation_sha256' => '',
                    'rotation_reason' => 'recovered_from_accepted_license_state',
                ];
                update_option(self::IDENTITY_OPTION, $saved, false);
                $originDomain = $domain;
                $needsNew = false;
            }
        }

        // A cloned database on a different domain is a different installation.
        // Rotating the local UUID here prevents two domains from sharing one installation identity.
        if (!$needsNew && $originDomain !== '' && $domain !== '' && !hash_equals($originDomain, $domain)) {
            $previousHash = hash('sha256', $uuid);
            $needsNew = true;
            $rotated = true;
        }

        if ($needsNew) {
            $uuid = wp_generate_uuid4();
            $saved = [
                'installation_uuid' => $uuid,
                'origin_domain' => $domain,
                'created_at' => $now,
                'rotated_at' => $rotated ? $now : '',
                'previous_installation_sha256' => $previousHash,
                'rotation_reason' => $rotated ? 'domain_changed' : 'initialised',
            ];
            update_option(self::IDENTITY_OPTION, $saved, false);
        } elseif ($originDomain === '' && $domain !== '') {
            $saved['origin_domain'] = $domain;
            if (empty($saved['created_at'])) $saved['created_at'] = $now;
            update_option(self::IDENTITY_OPTION, $saved, false);
        }

        return [
            'installation_uuid' => $uuid,
            'origin_domain' => self::normaliseDomain((string)($saved['origin_domain'] ?? $domain)),
            'current_domain' => $domain,
            'created_at' => sanitize_text_field((string)($saved['created_at'] ?? '')),
            'rotated_at' => sanitize_text_field((string)($saved['rotated_at'] ?? '')),
            'rotation_reason' => sanitize_key((string)($saved['rotation_reason'] ?? '')),
            'fingerprint_sha256' => self::installationFingerprint($uuid, $domain),
        ];
    }

    public static function installationFingerprint(string $uuid = '', string $domain = ''): string {
        if ($uuid === '') $uuid = (string)(self::identity()['installation_uuid'] ?? '');
        if ($domain === '') $domain = LicenseManager::currentDomain();
        $seed = RemoteLicenseClient::productId() . '|' . $uuid . '|' . self::normaliseDomain($domain);
        return hash('sha256', $seed);
    }

    public static function context(): array {
        $identity = self::identity();
        $wpVersion = (string)get_bloginfo('version');
        $pluginVersion = defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '';
        $environmentSeed = implode('|', [
            $pluginVersion,
            $wpVersion,
            PHP_VERSION,
            PHP_SAPI,
            is_multisite() ? 'multisite' : 'single',
        ]);
        return [
            'installation_id' => (string)$identity['installation_uuid'],
            'installation_uuid' => (string)$identity['installation_uuid'],
            'installation_fingerprint' => (string)$identity['fingerprint_sha256'],
            'domain' => LicenseManager::currentDomain(),
            'site_url' => home_url('/'),
            'fingerprint' => LicenseManager::currentFingerprint(),
            'plugin_version' => $pluginVersion,
            'wp_version' => $wpVersion,
            'php_version' => PHP_VERSION,
            'environment_hash' => hash('sha256', $environmentSeed),
            'multisite' => is_multisite(),
        ];
    }

    public static function heartbeat(bool $force = false, string $source = 'manual'): array {
        $source = sanitize_key($source) ?: 'manual';
        $identity = self::identity();
        $state = self::heartbeatState();
        $now = current_time('mysql', true);
        $licenseKey = LicenseManager::normalizeRemoteKey((string)LicenseManager::get('key', ''));

        if (!LicenseManager::isRemoteMode()) {
            return self::storeHeartbeatFailure('local_mode', 'O licenciamento remoto não está ativo.', 0, $source, $identity);
        }
        if (!RemoteLicenseClient::isReady()) {
            return self::storeHeartbeatFailure('remote_not_ready', 'A API remota de licenciamento não está configurada.', 0, $source, $identity);
        }
        if ($licenseKey === '') {
            return self::storeHeartbeatFailure('license_key_missing', 'Não existe uma chave de licença local para validar.', 0, $source, $identity);
        }
        if (!$force && !self::heartbeatDue($state)) {
            $state['skipped'] = true;
            $state['reason'] = 'not_due';
            return $state;
        }
        // Avoid duplicate network calls when admin bootstrap, cron and a manual
        // check happen at the same time. The lock contains no licence material.
        if (get_transient(self::HEARTBEAT_LOCK)) {
            $state['skipped'] = true;
            $state['reason'] = 'heartbeat_in_progress';
            return $state;
        }
        set_transient(self::HEARTBEAT_LOCK, '1', 2 * MINUTE_IN_SECONDS);

        $attempt = array_merge($state, [
            'last_attempt_at' => $now,
            'source' => $source,
            'skipped' => false,
            'reason' => '',
            'installation_uuid' => (string)$identity['installation_uuid'],
            'installation_fingerprint_sha256' => (string)$identity['fingerprint_sha256'],
            'domain' => LicenseManager::currentDomain(),
            'plugin_version' => defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
        ]);
        update_option(self::HEARTBEAT_OPTION, $attempt, false);

        $payload = array_merge(self::context(), [
            'license_key' => $licenseKey,
            'activation_id' => (string)LicenseManager::get('remote_activation_id', ''),
            'heartbeat' => true,
            'heartbeat_phase' => self::PHASE,
            'heartbeat_version' => self::VERSION,
        ]);
        $response = RemoteLicenseClient::post('/license/validate', $payload);
        $details = self::heartbeatResponseDetails($response, $identity);
        $identityRecovered = false;

        // Azure is authoritative for an activation that has already been bound.
        // If the local identity was regenerated unexpectedly, recover the local
        // UUID only when the signed request reached the trusted service and the
        // returned license, activation, domain and fingerprint all prove that the
        // remote binding belongs to this exact installation. The server binding is
        // never changed here.
        if (self::canRecoverIdentityFromRemoteBinding($details, $identity)) {
            $recovered = self::recoverIdentityFromRemoteBinding($details, $identity);
            if (is_array($recovered) && !empty($recovered['installation_uuid'])) {
                $identity = $recovered;
                $identityRecovered = true;
                $payload = array_merge(self::context(), [
                    'license_key' => $licenseKey,
                    'activation_id' => (string)LicenseManager::get('remote_activation_id', ''),
                    'heartbeat' => true,
                    'heartbeat_phase' => self::PHASE,
                    'heartbeat_version' => self::VERSION,
                ]);
                $response = RemoteLicenseClient::post('/license/validate', $payload);
                $details = self::heartbeatResponseDetails($response, $identity);
            }
        }
        delete_transient(self::HEARTBEAT_LOCK);

        $httpCode = (int)$details['http_code'];
        $remoteStatus = (string)$details['remote_status'];
        $remoteDomain = (string)$details['remote_domain'];
        $currentDomain = (string)$details['current_domain'];
        $remoteActivation = (string)$details['remote_activation_id'];
        $remoteLicenseId = (string)$details['remote_license_id'];
        $remoteInstallation = (string)$details['remote_installation_uuid'];
        $remoteInstallationFingerprint = (string)$details['remote_installation_fingerprint'];
        $heartbeatRecorded = !empty($details['heartbeat_recorded']);
        $domainMatch = !empty($details['domain_match']);
        $activationMatch = !empty($details['activation_match']);
        $identityAcknowledged = !empty($details['identity_acknowledged']);
        $fingerprintAcknowledged = !empty($details['fingerprint_acknowledged']);
        $statusAccepted = !empty($details['status_accepted']);
        $accepted = !empty($details['accepted']);
        $message = (string)$details['message'];
        if ($accepted && $identityRecovered) {
            $message = 'Heartbeat aceite após recuperação segura da identidade ligada no Azure.';
        }

        $next = [
            'ok' => $accepted,
            'accepted' => $accepted,
            'source' => $source,
            'reason' => $accepted ? 'heartbeat_accepted' : 'heartbeat_rejected',
            'last_attempt_at' => $now,
            'last_success_at' => $accepted ? $now : sanitize_text_field((string)($state['last_success_at'] ?? '')),
            'http_code' => $httpCode,
            'message' => $message,
            'remote_status' => $remoteStatus,
            'remote_domain' => $remoteDomain,
            'remote_license_id' => $remoteLicenseId,
            'remote_activation_id' => $remoteActivation,
            'remote_installation_uuid' => $remoteInstallation,
            'remote_installation_fingerprint_sha256' => $remoteInstallationFingerprint,
            'remote_identity_acknowledged' => $identityAcknowledged,
            'remote_fingerprint_acknowledged' => $fingerprintAcknowledged,
            'remote_heartbeat_recorded' => $heartbeatRecorded,
            'domain_match' => $domainMatch,
            'activation_match' => $activationMatch,
            'status_accepted' => $statusAccepted,
            'installation_uuid' => (string)$identity['installation_uuid'],
            'installation_fingerprint_sha256' => (string)$identity['fingerprint_sha256'],
            'domain' => $currentDomain,
            'plugin_version' => defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'raw_license_key_stored' => false,
            'identity_recovered_from_remote' => $identityRecovered,
            'identity_recovery_source' => $identityRecovered ? 'azure_activation_binding' : '',
            'skipped' => false,
        ];
        update_option(self::HEARTBEAT_OPTION, $next, false);

        LicenseManager::recordHeartbeatObservation([
            'accepted' => $accepted,
            'http_code' => $httpCode,
            'message' => $message,
            'remote_license_id' => $remoteLicenseId,
            'remote_activation_id' => $remoteActivation,
            'last_heartbeat_at' => $accepted ? $now : '',
            'installation_id' => (string)$identity['installation_uuid'],
            'installation_fingerprint' => (string)$identity['fingerprint_sha256'],
        ]);

        if ($accepted && class_exists(LicenseTenantFoundation::class)) {
            LicenseTenantFoundation::reconcileCurrentInstallation($next);
        }
        self::resetHealthCache();
        if (class_exists(LicenseTenantFoundation::class)) LicenseTenantFoundation::resetHealthCache();
        do_action('fieldflow_license_heartbeat_observed', $next);

        self::audit($accepted ? 'phase6b2.heartbeat.accepted' : 'phase6b2.heartbeat.rejected', [
            'source' => $source,
            'http_code' => $httpCode,
            'remote_status' => $remoteStatus,
            'domain_match' => $domainMatch,
            'activation_match' => $activationMatch,
            'installation_uuid_sha256' => hash('sha256', (string)$identity['installation_uuid']),
        ], $accepted ? 'Heartbeat remoto 6B.2 aceite sem ativar enforcement.' : 'Heartbeat remoto 6B.2 não aceite. A licença local e o FieldFlow não foram bloqueados.');

        return $next;
    }

    private static function heartbeatResponseDetails(array $response, array $identity): array {
        $httpCode = (int)($response['http_code'] ?? 0);
        $remoteLicense = is_array($response['license'] ?? null) ? $response['license'] : $response;
        $remoteStatus = sanitize_key((string)($remoteLicense['status'] ?? ''));
        $remoteDomain = self::normaliseDomain((string)($remoteLicense['domain'] ?? ''));
        $currentDomain = LicenseManager::currentDomain();
        $storedActivation = sanitize_text_field((string)LicenseManager::get('remote_activation_id', ''));
        $storedLicenseId = sanitize_text_field((string)LicenseManager::get('remote_license_id', ''));
        $remoteActivation = sanitize_text_field((string)($remoteLicense['activation_id'] ?? ''));
        $remoteLicenseId = sanitize_text_field((string)($remoteLicense['license_id'] ?? ''));
        $remoteInstallation = strtolower(sanitize_text_field((string)($remoteLicense['installation_uuid'] ?? ($remoteLicense['installation_id'] ?? ($response['installation_uuid'] ?? ($response['installation_id'] ?? ''))))));
        $remoteInstallationFingerprint = strtolower(sanitize_text_field((string)($remoteLicense['installation_fingerprint'] ?? ($response['installation_fingerprint'] ?? ''))));
        $heartbeatRecordedRaw = $remoteLicense['heartbeat_recorded'] ?? ($response['heartbeat_recorded'] ?? false);
        $heartbeatRecorded = filter_var($heartbeatRecordedRaw, FILTER_VALIDATE_BOOLEAN) === true;
        $domainMatch = $remoteDomain === '' || ($currentDomain !== '' && hash_equals($currentDomain, $remoteDomain));
        $activationMatch = $storedActivation === '' || $remoteActivation === '' || hash_equals($storedActivation, $remoteActivation);
        $licenseMatch = $storedLicenseId === '' || $remoteLicenseId === '' || hash_equals($storedLicenseId, $remoteLicenseId);
        $identityAcknowledged = $remoteInstallation !== '' && hash_equals(strtolower((string)$identity['installation_uuid']), $remoteInstallation);
        $fingerprintAcknowledged = preg_match('/^[a-f0-9]{64}$/', $remoteInstallationFingerprint) === 1
            && hash_equals(strtolower((string)$identity['fingerprint_sha256']), $remoteInstallationFingerprint);
        $statusAccepted = $remoteStatus === '' || in_array($remoteStatus, ['active', 'trial'], true);
        $accepted = !empty($response['success']) && $httpCode >= 200 && $httpCode < 300 && $domainMatch && $activationMatch && $statusAccepted;
        $message = sanitize_text_field((string)($response['message'] ?? ($accepted ? 'Heartbeat aceite.' : 'Heartbeat recusado.')));

        return [
            'http_code' => $httpCode,
            'remote_status' => $remoteStatus,
            'remote_domain' => $remoteDomain,
            'current_domain' => $currentDomain,
            'stored_activation_id' => $storedActivation,
            'stored_license_id' => $storedLicenseId,
            'remote_activation_id' => $remoteActivation,
            'remote_license_id' => $remoteLicenseId,
            'remote_installation_uuid' => $remoteInstallation,
            'remote_installation_fingerprint' => $remoteInstallationFingerprint,
            'heartbeat_recorded' => $heartbeatRecorded,
            'domain_match' => $domainMatch,
            'activation_match' => $activationMatch,
            'license_match' => $licenseMatch,
            'identity_acknowledged' => $identityAcknowledged,
            'fingerprint_acknowledged' => $fingerprintAcknowledged,
            'status_accepted' => $statusAccepted,
            'accepted' => $accepted,
            'message' => $message,
        ];
    }

    private static function canRecoverIdentityFromRemoteBinding(array $details, array $localIdentity): bool {
        if ((int)($details['http_code'] ?? 0) !== 409) return false;
        if ((string)($details['message'] ?? '') !== 'installation identity mismatch') return false;
        if (empty($details['domain_match']) || empty($details['activation_match']) || empty($details['license_match'])) return false;
        if (!in_array((string)($details['remote_status'] ?? ''), ['active', 'trial'], true)) return false;

        $remoteUuid = strtolower((string)($details['remote_installation_uuid'] ?? ''));
        $remoteFingerprint = strtolower((string)($details['remote_installation_fingerprint'] ?? ''));
        $currentDomain = self::normaliseDomain((string)($details['current_domain'] ?? ''));
        $storedActivation = (string)($details['stored_activation_id'] ?? '');
        $storedLicenseId = (string)($details['stored_license_id'] ?? '');
        $remoteActivation = (string)($details['remote_activation_id'] ?? '');
        $remoteLicenseId = (string)($details['remote_license_id'] ?? '');

        if ($storedActivation === '' || $remoteActivation === '' || !hash_equals($storedActivation, $remoteActivation)) return false;
        if ($storedLicenseId === '' || $remoteLicenseId === '' || !hash_equals($storedLicenseId, $remoteLicenseId)) return false;
        if ($currentDomain === '' || (string)($details['remote_domain'] ?? '') === '') return false;
        if (!self::isUuid($remoteUuid) || !self::isUuid((string)($localIdentity['installation_uuid'] ?? ''))) return false;
        if (hash_equals(strtolower((string)$localIdentity['installation_uuid']), $remoteUuid)) return false;
        if (preg_match('/^[a-f0-9]{64}$/', $remoteFingerprint) !== 1) return false;

        $expectedFingerprint = self::installationFingerprint($remoteUuid, $currentDomain);
        return $expectedFingerprint !== '' && hash_equals($expectedFingerprint, $remoteFingerprint);
    }

    private static function recoverIdentityFromRemoteBinding(array $details, array $localIdentity): ?array {
        if (!self::canRecoverIdentityFromRemoteBinding($details, $localIdentity)) return null;

        $remoteUuid = strtolower((string)$details['remote_installation_uuid']);
        $domain = self::normaliseDomain((string)$details['current_domain']);
        $now = current_time('mysql', true);
        $saved = get_option(self::IDENTITY_OPTION, []);
        $saved = is_array($saved) ? $saved : [];
        $previousUuid = strtolower(sanitize_text_field((string)($localIdentity['installation_uuid'] ?? '')));

        update_option(self::IDENTITY_OPTION, [
            'installation_uuid' => $remoteUuid,
            'origin_domain' => $domain,
            'created_at' => sanitize_text_field((string)($saved['created_at'] ?? '')) ?: $now,
            'rotated_at' => $now,
            'previous_installation_sha256' => self::isUuid($previousUuid) ? hash('sha256', $previousUuid) : '',
            'rotation_reason' => 'remote_binding_recovered',
            'recovered_at' => $now,
        ], false);

        self::resetHealthCache();
        self::audit('phase6b2.identity.recovered_from_remote', [
            'previous_installation_sha256' => self::isUuid($previousUuid) ? hash('sha256', $previousUuid) : '',
            'recovered_installation_sha256' => hash('sha256', $remoteUuid),
            'activation_id_sha256' => hash('sha256', (string)$details['remote_activation_id']),
            'license_id_sha256' => hash('sha256', (string)$details['remote_license_id']),
            'domain' => $domain,
        ], 'Local installation identity restored from the existing Azure activation binding. No remote rebind was performed.');

        return self::identity();
    }

    public static function heartbeatState(): array {
        $state = get_option(self::HEARTBEAT_OPTION, []);
        return is_array($state) ? $state : [];
    }

    public static function health(): array {
        if (self::$healthCache !== null) return self::$healthCache;
        $foundation = class_exists(LicenseTenantFoundation::class) ? LicenseTenantFoundation::health() : [];
        $identity = self::identity();
        $heartbeat = self::heartbeatState();
        $manifest = ProductionManifest::verify();
        $state = self::certificationState();
        $currentActivation = sanitize_text_field((string)LicenseManager::get('remote_activation_id', ''));
        $currentLicenseId = sanitize_text_field((string)LicenseManager::get('remote_license_id', ''));
        $heartbeatRecent = self::heartbeatRecent($heartbeat);
        $identityMatch = (string)($heartbeat['installation_uuid'] ?? '') !== ''
            && hash_equals((string)$identity['installation_uuid'], (string)$heartbeat['installation_uuid']);
        $fingerprintMatch = (string)($heartbeat['installation_fingerprint_sha256'] ?? '') !== ''
            && hash_equals((string)$identity['fingerprint_sha256'], (string)$heartbeat['installation_fingerprint_sha256']);
        $activationVerified = LicenseManager::isActive() && $currentActivation !== '' && $currentLicenseId !== '';
        $fingerprint = self::certificationFingerprint();
        $certifiedFingerprint = strtolower((string)($state['fingerprint_sha256'] ?? ''));
        $certFingerprintMatch = preg_match('/^[a-f0-9]{64}$/', $certifiedFingerprint) === 1 && hash_equals($certifiedFingerprint, $fingerprint);
        $checksReady = !empty($foundation['foundation_certified'])
            && !empty($manifest['ok'])
            && self::isUuid((string)$identity['installation_uuid'])
            && preg_match('/^[a-f0-9]{64}$/', (string)$identity['fingerprint_sha256']) === 1
            && LicenseManager::isRemoteMode()
            && RemoteLicenseClient::isReady()
            && $activationVerified
            && LicenseManager::isBoundToCurrentDomain()
            && !empty($heartbeat['accepted'])
            && $heartbeatRecent
            && $identityMatch
            && $fingerprintMatch
            && !empty($heartbeat['domain_match'])
            && !empty($heartbeat['activation_match'])
            && !empty($heartbeat['remote_identity_acknowledged'])
            && !empty($heartbeat['remote_fingerprint_acknowledged'])
            && !empty($heartbeat['remote_heartbeat_recorded'])
            && empty($heartbeat['raw_license_key_stored']);
        $certified = !empty($state['certified']) && $certFingerprintMatch && $checksReady;

        return self::$healthCache = [
            'version' => self::VERSION,
            'phase' => self::PHASE,
            'status' => $certified ? 'ready' : ($checksReady ? 'pilot_ready' : 'attention'),
            'phase6b1_certified' => !empty($foundation['foundation_certified']),
            'release_manifest_ok' => !empty($manifest['ok']),
            'installation_identity_ready' => self::isUuid((string)$identity['installation_uuid']),
            'installation_uuid' => (string)$identity['installation_uuid'],
            'installation_fingerprint_sha256' => (string)$identity['fingerprint_sha256'],
            'current_domain' => (string)$identity['current_domain'],
            'remote_mode' => LicenseManager::isRemoteMode(),
            'remote_ready' => RemoteLicenseClient::isReady(),
            'license_active' => LicenseManager::isActive(),
            'remote_license_id_present' => $currentLicenseId !== '',
            'remote_activation_id_present' => $currentActivation !== '',
            'activation_verified' => $activationVerified,
            'domain_binding_match' => LicenseManager::isBoundToCurrentDomain(),
            'heartbeat_accepted' => !empty($heartbeat['accepted']),
            'heartbeat_recent' => $heartbeatRecent,
            'heartbeat_identity_match' => $identityMatch,
            'heartbeat_fingerprint_match' => $fingerprintMatch,
            'heartbeat_domain_match' => !empty($heartbeat['domain_match']),
            'heartbeat_activation_match' => !empty($heartbeat['activation_match']),
            'remote_identity_acknowledged' => !empty($heartbeat['remote_identity_acknowledged']),
            'remote_fingerprint_acknowledged' => !empty($heartbeat['remote_fingerprint_acknowledged']),
            'remote_heartbeat_recorded' => !empty($heartbeat['remote_heartbeat_recorded']),
            'heartbeat_http_code' => (int)($heartbeat['http_code'] ?? 0),
            'heartbeat_message' => sanitize_text_field((string)($heartbeat['message'] ?? '')),
            'last_heartbeat_attempt_at' => sanitize_text_field((string)($heartbeat['last_attempt_at'] ?? '')),
            'last_heartbeat_success_at' => sanitize_text_field((string)($heartbeat['last_success_at'] ?? '')),
            'raw_license_key_stored_by_6b2' => !empty($heartbeat['raw_license_key_stored']),
            'license_enforcement_enabled' => self::LICENSE_ENFORCEMENT_ENABLED,
            'installation_binding_enforced' => self::INSTALLATION_BINDING_ENFORCED,
            'remote_revocation_enabled' => self::REMOTE_REVOCATION_ENABLED,
            'certified' => $certified,
            'certification_state_present' => !empty($state['certified']) || preg_match('/^[a-f0-9]{64}$/', $certifiedFingerprint) === 1,
            'certification_requires_revalidation' => !empty($state['certified']) && !$certFingerprintMatch,
            'certification_fingerprint_match' => $certFingerprintMatch,
            'certification_fingerprint_sha256' => $fingerprint,
            'certified_fingerprint_sha256' => $certifiedFingerprint,
            'certified_at' => sanitize_text_field((string)($state['certified_at'] ?? '')),
        ];
    }

    public static function resetHealthCache(): void {
        self::$healthCache = null;
    }

    public static function localProbe(): array {
        $health = self::health();
        $checks = [
            'phase6b1_certified' => !empty($health['phase6b1_certified']),
            'release_manifest_integrity' => !empty($health['release_manifest_ok']),
            'installation_identity_ready' => !empty($health['installation_identity_ready']),
            'remote_license_mode' => !empty($health['remote_mode']),
            'remote_api_ready' => !empty($health['remote_ready']),
            'remote_activation_verified' => !empty($health['activation_verified']),
            'domain_binding_matches' => !empty($health['domain_binding_match']),
            'heartbeat_accepted' => !empty($health['heartbeat_accepted']),
            'heartbeat_recent' => !empty($health['heartbeat_recent']),
            'heartbeat_identity_matches' => !empty($health['heartbeat_identity_match']),
            'heartbeat_fingerprint_matches' => !empty($health['heartbeat_fingerprint_match']),
            'heartbeat_domain_matches' => !empty($health['heartbeat_domain_match']),
            'heartbeat_activation_matches' => !empty($health['heartbeat_activation_match']),
            'remote_installation_identity_acknowledged' => !empty($health['remote_identity_acknowledged']),
            'remote_installation_fingerprint_acknowledged' => !empty($health['remote_fingerprint_acknowledged']),
            'remote_heartbeat_persisted' => !empty($health['remote_heartbeat_recorded']),
            'raw_license_key_not_stored_by_6b2' => empty($health['raw_license_key_stored_by_6b2']),
            'license_enforcement_off' => empty($health['license_enforcement_enabled']),
            'installation_binding_not_enforced' => empty($health['installation_binding_enforced']),
            'remote_revocation_not_enabled' => empty($health['remote_revocation_enabled']),
        ];
        return ['ok' => !in_array(false, $checks, true), 'checks' => $checks, 'health' => $health];
    }

    public static function certify(int $actorId = 0): array {
        ProductionManifest::verify(true);
        self::resetHealthCache();
        $probe = self::localProbe();
        if (empty($probe['ok'])) {
            return ['ok' => false, 'reason' => 'phase6b2_preconditions_failed', 'checks' => $probe['checks'], 'health' => $probe['health']];
        }
        $fingerprint = self::certificationFingerprint();
        update_option(self::STATE_OPTION, [
            'certified' => true,
            'certified_at' => current_time('mysql', true),
            'certified_by' => max(0, $actorId),
            'fingerprint_sha256' => $fingerprint,
            'certified_on_plugin_version' => defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'foundation_fingerprint_sha256' => class_exists(LicenseTenantFoundation::class) ? (string)(LicenseTenantFoundation::health()['certified_fingerprint_sha256'] ?? '') : '',
            'installation_uuid_sha256' => hash('sha256', (string)($probe['health']['installation_uuid'] ?? '')),
            'installation_fingerprint_sha256' => (string)($probe['health']['installation_fingerprint_sha256'] ?? ''),
            'domain_sha256' => hash('sha256', (string)($probe['health']['current_domain'] ?? '')),
            'remote_license_id_sha256' => hash('sha256', (string)LicenseManager::get('remote_license_id', '')),
            'remote_activation_id_sha256' => hash('sha256', (string)LicenseManager::get('remote_activation_id', '')),
            'remote_api_host' => strtolower((string)(wp_parse_url(RemoteLicenseClient::baseUrl(), PHP_URL_HOST) ?: '')),
            'license_enforcement_enabled' => false,
            'installation_binding_enforced' => false,
            'remote_revocation_enabled' => false,
        ], false);
        self::resetHealthCache();
        self::audit('phase6b2.certified', [
            'fingerprint_sha256' => $fingerprint,
            'license_enforcement_enabled' => false,
            'installation_binding_enforced' => false,
            'remote_revocation_enabled' => false,
        ], 'Phase 6B.2 activation, binding identity and heartbeat certified. Enforcement and remote revocation remain disabled.');
        return ['ok' => true, 'reason' => 'phase6b2_certified', 'checks' => $probe['checks'], 'health' => self::health()];
    }

    public static function resetCertification(): array {
        $before = self::certificationState();
        update_option(self::STATE_OPTION, [
            'certified' => false,
            'certified_at' => '',
            'certified_by' => 0,
            'fingerprint_sha256' => '',
            'license_enforcement_enabled' => false,
            'installation_binding_enforced' => false,
            'remote_revocation_enabled' => false,
        ], false);
        self::resetHealthCache();
        self::audit('phase6b2.certification.reset', [
            'previous_fingerprint_sha256' => sanitize_text_field((string)($before['fingerprint_sha256'] ?? '')),
        ], 'Phase 6B.2 certification reset without revoking or blocking any installation.');
        return ['ok' => true, 'reason' => 'phase6b2_certification_reset'];
    }

    public static function onRemoteActivated(array $response, array $license): void {
        self::resetHealthCache();
        if (class_exists(LicenseTenantFoundation::class)) LicenseTenantFoundation::resetHealthCache();
        $identity = self::identity();
        $remoteLicense = is_array($response['license'] ?? null) ? $response['license'] : [];
        $observation = [
            'accepted' => true,
            'remote_status' => sanitize_key((string)($remoteLicense['status'] ?? 'active')),
            'remote_domain' => self::normaliseDomain((string)($remoteLicense['domain'] ?? LicenseManager::currentDomain())),
            'remote_license_id' => sanitize_text_field((string)($remoteLicense['license_id'] ?? ($license['remote_license_id'] ?? ''))),
            'remote_activation_id' => sanitize_text_field((string)($remoteLicense['activation_id'] ?? ($license['remote_activation_id'] ?? ''))),
            'domain_match' => LicenseManager::isBoundToCurrentDomain(),
            'activation_match' => true,
            'installation_uuid' => (string)$identity['installation_uuid'],
            'installation_fingerprint_sha256' => (string)$identity['fingerprint_sha256'],
            'domain' => LicenseManager::currentDomain(),
            'plugin_version' => defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'last_success_at' => '',
            'http_code' => (int)($response['http_code'] ?? 0),
            'message' => sanitize_text_field((string)($response['message'] ?? 'Ativação remota aceite.')),
        ];
        if (class_exists(LicenseTenantFoundation::class)) {
            LicenseTenantFoundation::reconcileCurrentInstallation($observation);
        }
        self::audit('phase6b2.activation.observed', [
            'http_code' => (int)($response['http_code'] ?? 0),
            'installation_uuid_sha256' => hash('sha256', (string)$identity['installation_uuid']),
            'activation_id_present' => !empty($observation['remote_activation_id']),
        ], 'Ativação remota observada pela 6B.2. O heartbeat continua separado e o enforcement permanece desligado.');
    }

    public static function onRemoteDeactivated(array $response, array $previousLicense): void {
        self::resetHealthCache();
        if (class_exists(LicenseTenantFoundation::class)) LicenseTenantFoundation::resetHealthCache();
        if (class_exists(LicenseTenantFoundation::class)) {
            LicenseTenantFoundation::markCurrentInstallationInactive([
                'activation_id' => sanitize_text_field((string)($previousLicense['remote_activation_id'] ?? '')),
                'message' => sanitize_text_field((string)($response['message'] ?? 'Desativação remota explícita.')),
            ]);
        }
        self::audit('phase6b2.deactivation.observed', [
            'http_code' => (int)($response['http_code'] ?? 0),
            'explicit_user_action' => true,
        ], 'Desativação remota explícita observada. Isto não é revogação automática 6B.3.');
    }

    private static function heartbeatDue(array $state): bool {
        $hours = max(1, min(72, (int)Config::get('license_remote_heartbeat_interval', 6)));
        $last = sanitize_text_field((string)($state['last_attempt_at'] ?? ''));
        if ($last === '') return true;
        $ts = strtotime($last . (stripos($last, 'UTC') === false ? ' UTC' : ''));
        return !$ts || (time() - $ts) >= $hours * HOUR_IN_SECONDS;
    }

    private static function heartbeatRecent(array $state): bool {
        $last = sanitize_text_field((string)($state['last_success_at'] ?? ''));
        if ($last === '') return false;
        $ts = strtotime($last . (stripos($last, 'UTC') === false ? ' UTC' : ''));
        if (!$ts) return false;
        $hours = max(1, min(72, (int)Config::get('license_remote_heartbeat_interval', 6)));
        $window = max(24, $hours * 3);
        return (time() - $ts) <= $window * HOUR_IN_SECONDS;
    }

    private static function storeHeartbeatFailure(string $reason, string $message, int $httpCode, string $source, array $identity): array {
        $previous = self::heartbeatState();
        $next = [
            'ok' => false,
            'accepted' => false,
            'source' => $source,
            'reason' => sanitize_key($reason),
            'last_attempt_at' => current_time('mysql', true),
            'last_success_at' => sanitize_text_field((string)($previous['last_success_at'] ?? '')),
            'http_code' => $httpCode,
            'message' => sanitize_text_field($message),
            'remote_status' => '',
            'remote_domain' => '',
            'remote_license_id' => '',
            'remote_activation_id' => '',
            'remote_installation_uuid' => '',
            'remote_installation_fingerprint_sha256' => '',
            'remote_identity_acknowledged' => false,
            'remote_fingerprint_acknowledged' => false,
            'remote_heartbeat_recorded' => false,
            'domain_match' => false,
            'activation_match' => false,
            'status_accepted' => false,
            'installation_uuid' => (string)($identity['installation_uuid'] ?? ''),
            'installation_fingerprint_sha256' => (string)($identity['fingerprint_sha256'] ?? ''),
            'domain' => LicenseManager::currentDomain(),
            'plugin_version' => defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'raw_license_key_stored' => false,
            'skipped' => false,
        ];
        update_option(self::HEARTBEAT_OPTION, $next, false);
        return $next;
    }

    private static function certificationFingerprint(): string {
        $foundation = class_exists(LicenseTenantFoundation::class) ? LicenseTenantFoundation::health() : [];
        $identity = self::identity();
        $payload = [
            'phase' => self::PHASE,
            'version' => self::VERSION,
            'phase6b1_certified_fingerprint' => (string)($foundation['certified_fingerprint_sha256'] ?? ''),
            'installation_uuid' => (string)$identity['installation_uuid'],
            'installation_fingerprint_sha256' => (string)$identity['fingerprint_sha256'],
            'domain' => LicenseManager::currentDomain(),
            'remote_license_id' => (string)LicenseManager::get('remote_license_id', ''),
            'remote_activation_id' => (string)LicenseManager::get('remote_activation_id', ''),
            'remote_api_host' => strtolower((string)(wp_parse_url(RemoteLicenseClient::baseUrl(), PHP_URL_HOST) ?: '')),
            'license_enforcement_enabled' => false,
            'installation_binding_enforced' => false,
            'remote_revocation_enabled' => false,
        ];
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function certificationState(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function normaliseDomain(string $domain): string {
        $domain = strtolower(trim($domain));
        if (strpos($domain, '://') !== false) {
            $domain = (string)(wp_parse_url($domain, PHP_URL_HOST) ?: '');
        }
        $domain = preg_replace('/^www\./', '', $domain);
        return sanitize_text_field((string)$domain);
    }

    private static function isUuid(string $value): bool {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value) === 1;
    }

    private static function audit(string $event, array $details, string $reason): void {
        if (!class_exists(GovernanceRecovery::class)) return;
        GovernanceRecovery::appendLedger($event, [
            'entity_type' => 'license_installation',
            'entity_ref' => self::PHASE,
            'client_id' => 0,
            'project_id' => 0,
            'destination_uuid' => '',
        ], array_merge($details, [
            'phase' => self::PHASE,
            'license_enforcement_enabled' => false,
            'installation_binding_enforced' => false,
            'remote_revocation_enabled' => false,
            'raw_license_key_stored' => false,
        ]), $reason);
    }

    private static function canonicalize($value) {
        if (is_array($value)) {
            if (array_keys($value) !== range(0, count($value) - 1)) ksort($value, SORT_STRING);
            foreach ($value as $key => $item) $value[$key] = self::canonicalize($item);
        }
        return $value;
    }
}
