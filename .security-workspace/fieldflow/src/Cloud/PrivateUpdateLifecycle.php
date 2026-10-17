<?php
namespace RoutesPro\Cloud;

use RoutesPro\Support\LicenseManager;
use RoutesPro\Support\ProductionManifest;
use RoutesPro\Support\RemoteLicenseClient;
use RoutesPro\Support\ReleasePackageValidator;

if (!defined('ABSPATH')) exit;

/**
 * Phase 6B.4, Private Distribution & Updates.
 *
 * Safety guarantees:
 * - update discovery is licensed and HMAC protected;
 * - packages are delivered only through short-lived signed URLs;
 * - the manifest is HMAC-SHA256 authenticated and the ZIP is SHA-256 verified;
 * - private update integration never offers a downgrade;
 * - auto-updates are not forced by FieldFlow;
 * - rollback remains explicit/manual and runtime licensing remains SHADOW/fail-open.
 */
final class PrivateUpdateLifecycle {
    public const VERSION = '1.0.2';
    public const PHASE = '6B.4';
    public const STATE_OPTION = 'fieldflow_phase6b4_update_state';
    public const CERT_OPTION = 'fieldflow_phase6b4_certification_state';
    public const CHANNEL_OPTION = 'fieldflow_private_update_channel';
    public const DEFAULT_CHANNEL = 'stable';
    public const FORCED_AUTO_UPDATES = false;
    public const DOWNGRADES_ALLOWED = false;
    public const UPDATE_ENFORCEMENT = 'manual';
    public const ROLLBACK_MODE = 'manual_explicit';
    private static ?array $healthCache = null;

    public static function registerHooks(): void {
        add_action('fieldflow_private_update_checked', [self::class, 'observeCheck'], 10, 1);
    }

    public static function channel(): string {
        $channel = sanitize_key((string)get_option(self::CHANNEL_OPTION, self::DEFAULT_CHANNEL));
        return in_array($channel, ['stable','beta'], true) ? $channel : self::DEFAULT_CHANNEL;
    }

    public static function setChannel(string $channel): string {
        $channel = sanitize_key($channel);
        if (!in_array($channel, ['stable','beta'], true)) $channel = self::DEFAULT_CHANNEL;
        update_option(self::CHANNEL_OPTION, $channel, false);
        if (class_exists(PrivatePluginUpdater::class)) PrivatePluginUpdater::clearCache();
        self::resetHealthCache();
        return $channel;
    }

    public static function remoteCheck(bool $includePackage = false): array {
        $licenseKey = LicenseManager::normalizeRemoteKey((string)LicenseManager::get('key', ''));
        $activationId = sanitize_text_field((string)LicenseManager::get('remote_activation_id', ''));
        if (!LicenseManager::isRemoteMode()) return ['success'=>false,'message'=>'Licenciamento remoto não está ativo.'];
        if (!RemoteLicenseClient::isReady()) return ['success'=>false,'message'=>'API remota de licenciamento por configurar.'];
        if ($licenseKey === '' || $activationId === '') return ['success'=>false,'message'=>'Licença ou Activation ID em falta.'];

        $payload = array_merge(LicenseInstallationLifecycle::context(), [
            'operation'=>'check',
            'license_key'=>$licenseKey,
            'activation_id'=>$activationId,
            'current_version'=>defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'channel'=>self::channel(),
            'include_package_probe'=>$includePackage,
            'include_package_url'=>$includePackage,
        ]);
        return RemoteLicenseClient::post('/license/updates', $payload);
    }

    public static function observeCheck(array $response): void {
        if (empty($response['success'])) return;
        $state = self::state();
        $state['last_background_check_at'] = current_time('mysql', true);
        $state['latest_version'] = sanitize_text_field((string)($response['latest_version'] ?? ''));
        $state['channel'] = sanitize_key((string)($response['channel'] ?? self::channel()));
        $state['update_available'] = !empty($response['update_available']);
        update_option(self::STATE_OPTION, $state, false);
        self::resetHealthCache();
    }

    public static function testCurrentDistribution(): array {
        $response = self::remoteCheck(true);
        if (empty($response['success'])) return self::storeFailure('remote_update_check_failed', sanitize_text_field((string)($response['message'] ?? 'Falha no teste da distribuição privada.')), (int)($response['http_code'] ?? 0));

        $release = is_array($response['release'] ?? null) ? $response['release'] : [];
        if (empty($response['phase_6b4'])) return self::storeFailure('phase6b4_not_acknowledged', 'O backend ainda não confirmou a Fase 6B.4.');
        if (!$release) return self::storeFailure('release_catalog_empty', 'Não existe uma release privada publicada no canal selecionado.');

        $manifestSignatureOk = self::verifyManifestSignature($release, (string)($response['manifest_signature_hmac_sha256'] ?? ''));
        if (!$manifestSignatureOk) return self::storeFailure('manifest_signature_mismatch', 'A assinatura HMAC do manifesto privado não coincide.');

        $packageUrl = esc_url_raw((string)($response['package_url'] ?? ''));
        $urlOk = self::trustedPackageUrl($packageUrl);
        if (!$urlOk) return self::storeFailure('package_url_untrusted', 'O URL temporário do pacote não pertence ao endpoint FieldFlow autorizado.');

        $download = self::downloadAndVerifyPackage($packageUrl, $release);
        if (is_wp_error($download)) return self::storeFailure('package_verification_failed', $download->get_error_message());

        $latest = sanitize_text_field((string)($response['latest_version'] ?? ($release['version'] ?? '')));
        $current = defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '';
        if ($latest === '' || ($current !== '' && version_compare($latest, $current, '<'))) {
            return self::storeFailure('catalog_behind_runtime', 'O catálogo remoto está atrás da versão FieldFlow instalada.');
        }

        $caps = is_array($response['update_capabilities'] ?? null) ? $response['update_capabilities'] : [];
        $previous = is_array($response['previous_release'] ?? null) ? $response['previous_release'] : [];
        if (!empty($download['file']) && is_string($download['file'])) @unlink($download['file']);
        $state = [
            'ok'=>true,
            'message'=>'Distribuição privada validada ponta a ponta, sem instalar nem alterar a release atual.',
            'http_code'=>(int)($response['http_code'] ?? 0),
            'phase_6b4_acknowledged'=>true,
            'entitlement'=>sanitize_key((string)($response['entitlement'] ?? '')),
            'channel'=>sanitize_key((string)($response['channel'] ?? self::channel())),
            'current_version'=>sanitize_text_field((string)($response['current_version'] ?? $current)),
            'latest_version'=>$latest,
            'newer_available'=>!empty($response['newer_available']),
            'update_available'=>!empty($response['update_available']),
            'compatible'=>!empty($response['compatible']),
            'wp_compatible'=>!empty($response['wp_compatible']),
            'php_compatible'=>!empty($response['php_compatible']),
            'downgrade_blocked'=>!empty($response['downgrade_blocked']),
            'release_id'=>sanitize_text_field((string)($release['release_id'] ?? '')),
            'release_sha256'=>strtolower(sanitize_text_field((string)($release['sha256'] ?? ''))),
            'release_package_size'=>(int)($release['package_size'] ?? 0),
            'release_published_at'=>sanitize_text_field((string)($release['published_at'] ?? '')),
            'release_allowed_plans'=>array_values(array_filter(array_map('sanitize_key', (array)($release['allowed_plans'] ?? [])))),
            'manifest_signature_verified'=>true,
            'package_https'=>true,
            'package_host'=>strtolower((string)(wp_parse_url($packageUrl, PHP_URL_HOST) ?: '')),
            'package_hash_verified'=>true,
            'package_zip_valid'=>!empty($download['zip_valid']),
            'package_bytes'=>(int)($download['bytes'] ?? 0),
            'package_fieldflow_version'=>sanitize_text_field((string)($download['fieldflow_version'] ?? '')),
            'private_catalog_ready'=>!empty($caps['private_catalog']),
            'licensed_downloads_ready'=>!empty($caps['licensed_downloads']),
            'manifest_hmac_ready'=>!empty($caps['manifest_hmac_sha256']),
            'package_sha256_ready'=>!empty($caps['package_sha256']),
            'short_lived_download_tokens'=>!empty($caps['short_lived_download_tokens']),
            'wordpress_update_transient'=>!empty($caps['wordpress_update_transient']),
            'forced_auto_updates'=>!empty($caps['forced_auto_updates']),
            'rollback_mode'=>sanitize_key((string)($caps['rollback_mode'] ?? '')),
            'previous_release_version'=>sanitize_text_field((string)($previous['version'] ?? '')),
            'last_checked_at'=>current_time('mysql', true),
            'last_success_at'=>current_time('mysql', true),
        ];
        update_option(self::STATE_OPTION, $state, false);
        self::resetHealthCache();
        self::audit('phase6b4.private_distribution.tested', [
            'channel'=>$state['channel'],
            'release_id'=>$state['release_id'],
            'latest_version'=>$state['latest_version'],
            'package_sha256'=>$state['release_sha256'],
            'package_bytes'=>$state['package_bytes'],
            'update_available'=>$state['update_available'],
        ], 'Private update catalog, manifest authentication and package SHA-256 verified end to end without installation.');
        return $state;
    }

    public static function health(): array {
        if (self::$healthCache !== null) return self::$healthCache;
        $state = self::state();
        $phase6b3 = LicenseGovernanceLifecycle::health();
        $manifest = ProductionManifest::verify();
        $cert = self::certState();
        $capsReady = !empty($state['private_catalog_ready'])
            && !empty($state['licensed_downloads_ready'])
            && !empty($state['manifest_hmac_ready'])
            && !empty($state['package_sha256_ready'])
            && !empty($state['short_lived_download_tokens'])
            && !empty($state['wordpress_update_transient']);
        $channel = self::channel();
        $latest = sanitize_text_field((string)($state['latest_version'] ?? ''));
        $current = defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '';
        $catalogNotBehind = $latest !== '' && ($current === '' || version_compare($latest, $current, '>='));
        $packageHostTrusted = hash_equals(RemoteLicenseClient::TRUSTED_DEFAULT_HOST, strtolower((string)($state['package_host'] ?? '')));
        $checksReady = !empty($phase6b3['certified'])
            && !empty($manifest['ok'])
            && LicenseManager::isRemoteMode()
            && RemoteLicenseClient::isReady()
            && (string)LicenseManager::get('remote_activation_id', '') !== ''
            && class_exists(PrivatePluginUpdater::class)
            && PrivatePluginUpdater::isRegistered()
            && in_array($channel, ['stable','beta'], true)
            && !empty($state['phase_6b4_acknowledged'])
            && $capsReady
            && !empty($state['manifest_signature_verified'])
            && !empty($state['package_hash_verified'])
            && !empty($state['package_https'])
            && $packageHostTrusted
            && !empty($state['package_zip_valid'])
            && $catalogNotBehind
            && !empty($state['downgrade_blocked'])
            && self::FORCED_AUTO_UPDATES === false
            && empty($state['forced_auto_updates'])
            && sanitize_key((string)($state['rollback_mode'] ?? '')) === self::ROLLBACK_MODE
            && empty($phase6b3['runtime_blocking_enabled'])
            && (string)($phase6b3['enforcement_mode'] ?? '') === 'shadow';
        $fingerprint = self::certificationFingerprint();
        $storedFingerprint = strtolower(sanitize_text_field((string)($cert['fingerprint_sha256'] ?? '')));
        $certFpMatch = preg_match('/^[a-f0-9]{64}$/', $storedFingerprint) === 1 && hash_equals($storedFingerprint, $fingerprint);
        $certified = !empty($cert['certified']) && $certFpMatch && $checksReady;
        return self::$healthCache = array_merge($state, [
            'phase'=>self::PHASE,
            'version'=>self::VERSION,
            'phase6b3_certified'=>!empty($phase6b3['certified']),
            'release_manifest_integrity'=>!empty($manifest['ok']),
            'remote_license_mode'=>LicenseManager::isRemoteMode(),
            'remote_api_ready'=>RemoteLicenseClient::isReady(),
            'activation_ready'=>(string)LicenseManager::get('remote_activation_id', '') !== '',
            'current_channel'=>$channel,
            'update_client_registered'=>class_exists(PrivatePluginUpdater::class) && PrivatePluginUpdater::isRegistered(),
            'capabilities_ready'=>$capsReady,
            'catalog_not_behind_runtime'=>$catalogNotBehind,
            'package_host_trusted'=>$packageHostTrusted,
            'forced_auto_updates'=>self::FORCED_AUTO_UPDATES,
            'downgrades_allowed'=>self::DOWNGRADES_ALLOWED,
            'update_enforcement'=>self::UPDATE_ENFORCEMENT,
            'rollback_mode_expected'=>self::ROLLBACK_MODE,
            'runtime_blocking_off'=>empty($phase6b3['runtime_blocking_enabled']),
            'license_enforcement_shadow'=>((string)($phase6b3['enforcement_mode'] ?? '')) === 'shadow',
            'certification_state_present'=>!empty($cert['certified']) || preg_match('/^[a-f0-9]{64}$/', $storedFingerprint) === 1,
            'certification_fingerprint_match'=>$certFpMatch,
            'certification_requires_revalidation'=>!empty($cert['certified']) && (!$certFpMatch || !$checksReady),
            'certified'=>$certified,
            'certified_at'=>$certified ? sanitize_text_field((string)($cert['certified_at'] ?? '')) : '',
            'certification_fingerprint_sha256'=>$fingerprint,
            'certified_fingerprint_sha256'=>$storedFingerprint,
        ]);
    }

    public static function localProbe(): array {
        $h = self::health();
        $latest = sanitize_text_field((string)($h['latest_version'] ?? ''));
        $current = defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '';
        $catalogNotBehind = $latest !== '' && ($current === '' || version_compare($latest, $current, '>='));
        $checks = [
            'phase6b3_certified'=>!empty($h['phase6b3_certified']),
            'release_manifest_integrity'=>!empty($h['release_manifest_integrity']),
            'remote_license_mode'=>!empty($h['remote_license_mode']),
            'remote_api_ready'=>!empty($h['remote_api_ready']),
            'current_activation_ready'=>!empty($h['activation_ready']),
            'update_client_registered'=>!empty($h['update_client_registered']),
            'update_channel_supported'=>in_array((string)($h['current_channel'] ?? ''), ['stable','beta'], true),
            'remote_phase6b4_acknowledged'=>!empty($h['phase_6b4_acknowledged']),
            'private_catalog_ready'=>!empty($h['private_catalog_ready']),
            'licensed_downloads_ready'=>!empty($h['licensed_downloads_ready']),
            'manifest_hmac_ready'=>!empty($h['manifest_hmac_ready']),
            'manifest_signature_verified'=>!empty($h['manifest_signature_verified']),
            'package_sha256_ready'=>!empty($h['package_sha256_ready']),
            'package_hash_verified'=>!empty($h['package_hash_verified']),
            'package_https'=>!empty($h['package_https']),
            'package_host_trusted'=>hash_equals(RemoteLicenseClient::TRUSTED_DEFAULT_HOST, strtolower((string)($h['package_host'] ?? ''))),
            'package_zip_valid'=>!empty($h['package_zip_valid']),
            'catalog_not_behind_runtime'=>$catalogNotBehind,
            'downgrade_protection_on'=>!self::DOWNGRADES_ALLOWED && !empty($h['downgrade_blocked']),
            'forced_auto_updates_off'=>self::FORCED_AUTO_UPDATES === false && empty($h['forced_auto_updates']),
            'runtime_blocking_off'=>!empty($h['runtime_blocking_off']),
            'license_enforcement_shadow'=>!empty($h['license_enforcement_shadow']),
            'rollback_manual_explicit'=>sanitize_key((string)($h['rollback_mode'] ?? '')) === self::ROLLBACK_MODE,
        ];
        return ['ok'=>!in_array(false, $checks, true),'checks'=>$checks,'health'=>$h];
    }

    public static function certify(int $actorId = 0): array {
        ProductionManifest::invalidateCache();
        ProductionManifest::verify(true);
        self::resetHealthCache();
        $probe = self::localProbe();
        if (empty($probe['ok'])) return ['ok'=>false,'message'=>'Ainda existem pré-condições da 6B.4 por concluir.','checks'=>$probe['checks'] ?? []];
        $fingerprint = self::certificationFingerprint();
        $cert = [
            'certified'=>true,
            'phase'=>self::PHASE,
            'version'=>self::VERSION,
            'fingerprint_sha256'=>$fingerprint,
            'certified_on_plugin_version'=>defined('ROUTESPRO_VERSION') ? (string)ROUTESPRO_VERSION : '',
            'upstream_6b3_fingerprint_sha256'=>(string)($probe['health']['certified_fingerprint_sha256'] ?? ''),
            'release_sha256'=>(string)($probe['health']['release_sha256'] ?? ''),
            'latest_version'=>(string)($probe['health']['latest_version'] ?? ''),
            'certified_at'=>current_time('mysql', true),
            'certified_by'=>max(0,$actorId),
            'channel'=>self::channel(),
            'forced_auto_updates'=>false,
            'downgrades_allowed'=>false,
            'rollback_mode'=>self::ROLLBACK_MODE,
        ];
        update_option(self::CERT_OPTION, $cert, false);
        self::resetHealthCache();
        self::audit('phase6b4.certified', ['fingerprint_sha256'=>$fingerprint,'channel'=>self::channel()], 'Private distribution and licensed WordPress update integration certified with runtime enforcement unchanged.');
        return ['ok'=>true,'fingerprint_sha256'=>$fingerprint,'health'=>self::health()];
    }

    public static function resetCertification(): void {
        delete_option(self::CERT_OPTION);
        self::resetHealthCache();
    }

    public static function state(): array {
        $state = get_option(self::STATE_OPTION, []);
        return is_array($state) ? $state : [];
    }

    public static function resetHealthCache(): void {
        self::$healthCache = null;
    }

    public static function verifyManifestSignature(array $release, string $signature): bool {
        $signature = strtolower(trim($signature));
        if (!preg_match('/^[a-f0-9]{64}$/', $signature)) return false;
        $secret = (string)RemoteLicenseClient::sharedSecret();
        if ($secret === '') return false;
        $manifest = self::normaliseReleaseManifest($release);
        $json = wp_json_encode(self::canonicalize($manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) return false;
        $expected = hash_hmac('sha256', $json, $secret);
        return hash_equals($expected, $signature);
    }

    public static function trustedPackageUrl(string $url): bool {
        if ($url === '') return false;
        $scheme = strtolower((string)(wp_parse_url($url, PHP_URL_SCHEME) ?: ''));
        $host = strtolower((string)(wp_parse_url($url, PHP_URL_HOST) ?: ''));
        $path = (string)(wp_parse_url($url, PHP_URL_PATH) ?: '');
        return $scheme === 'https'
            && hash_equals(RemoteLicenseClient::TRUSTED_DEFAULT_HOST, $host)
            && strpos($path, '/api/updates/package') !== false;
    }

    public static function downloadAndVerifyPackage(string $url, array $release) {
        if (!self::trustedPackageUrl($url)) return new \WP_Error('fieldflow_update_url_untrusted', 'URL privado de atualização não autorizado.');
        $expectedSha = strtolower(sanitize_text_field((string)($release['sha256'] ?? '')));
        $expectedBytes = max(0,(int)($release['package_size'] ?? 0));
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedSha) || $expectedBytes <= 0) return new \WP_Error('fieldflow_update_manifest_invalid', 'Manifesto do pacote privado incompleto.');
        if ($expectedBytes > 64 * 1024 * 1024) return new \WP_Error('fieldflow_update_package_too_large', 'O pacote privado excede o limite de segurança.');

        if (!function_exists('wp_tempnam')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $tmp = wp_tempnam('fieldflow-private-update.zip');
        if (!$tmp) return new \WP_Error('fieldflow_update_temp_failed', 'Não foi possível criar ficheiro temporário para validar o update.');

        $response = wp_safe_remote_get($url, [
            'timeout'=>60,
            'redirection'=>0,
            'stream'=>true,
            'filename'=>$tmp,
            'limit_response_size'=>64 * 1024 * 1024,
            'headers'=>['Accept'=>'application/zip, application/octet-stream'],
        ]);
        if (is_wp_error($response)) {
            @unlink($tmp);
            return $response;
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        if ($code !== 200 || !is_file($tmp)) {
            @unlink($tmp);
            return new \WP_Error('fieldflow_update_download_failed', 'O serviço privado não devolveu o pacote de atualização esperado.');
        }
        $bytes = (int)@filesize($tmp);
        $actualSha = strtolower((string)@hash_file('sha256', $tmp));
        if ($bytes !== $expectedBytes || $actualSha === '' || !hash_equals($expectedSha, $actualSha)) {
            @unlink($tmp);
            return new \WP_Error('fieldflow_update_hash_mismatch', 'O SHA-256 do pacote privado não coincide com o manifesto assinado.');
        }
        $headerSha = strtolower(trim((string)wp_remote_retrieve_header($response, 'x-fieldflow-release-sha256')));
        if ($headerSha !== '' && (!preg_match('/^[a-f0-9]{64}$/', $headerSha) || !hash_equals($expectedSha, $headerSha))) {
            @unlink($tmp);
            return new \WP_Error('fieldflow_update_header_hash_mismatch', 'O serviço de download devolveu um fingerprint diferente do manifesto.');
        }

        // 2.2.396+: remote updates are accepted only in the canonical WordPress
        // package shape. Flat ZIPs used by older release tooling are deliberately
        // rejected because WordPress can install them into a second plugin folder.
        $validated = ReleasePackageValidator::validate($tmp);
        if (empty($validated['ok'])) {
            @unlink($tmp);
            return new \WP_Error(
                'fieldflow_update_zip_invalid',
                'O ZIP privado não cumpre o contrato canónico fieldflow/: ' . sanitize_text_field((string)($validated['error'] ?? 'estrutura inválida.'))
            );
        }
        $fieldflowVersion = sanitize_text_field((string)($validated['version'] ?? ''));
        $releaseVersion = sanitize_text_field((string)($release['version'] ?? ''));
        if ($fieldflowVersion === '' || $releaseVersion === '' || !hash_equals($releaseVersion, $fieldflowVersion)) {
            @unlink($tmp);
            return new \WP_Error('fieldflow_update_zip_version_mismatch', 'A versão interna do ZIP privado não coincide com a release autorizada.');
        }
        return [
            'ok'=>true,
            'file'=>$tmp,
            'bytes'=>$bytes,
            'sha256'=>$actualSha,
            'zip_valid'=>true,
            'canonical_root'=>true,
            'manifest_ok'=>!empty($validated['manifest_ok']),
            'fieldflow_version'=>$fieldflowVersion,
        ];
    }

    public static function releaseForWordPress(array $response): array {
        $release = is_array($response['release'] ?? null) ? $response['release'] : [];
        if (!$release || !self::verifyManifestSignature($release, (string)($response['manifest_signature_hmac_sha256'] ?? ''))) return [];
        return self::normaliseReleaseManifest($release);
    }

    private static function normaliseReleaseManifest(array $release): array {
        $plans = array_values(array_filter(array_map('sanitize_key', (array)($release['allowed_plans'] ?? []))));
        sort($plans, SORT_STRING);
        return [
            'release_id'=>sanitize_text_field((string)($release['release_id'] ?? '')),
            'version'=>sanitize_text_field((string)($release['version'] ?? '')),
            'channel'=>sanitize_key((string)($release['channel'] ?? self::DEFAULT_CHANNEL)),
            'sha256'=>strtolower(sanitize_text_field((string)($release['sha256'] ?? ''))),
            'package_size'=>(int)($release['package_size'] ?? 0),
            'requires_wp'=>sanitize_text_field((string)($release['requires_wp'] ?? '')),
            'requires_php'=>sanitize_text_field((string)($release['requires_php'] ?? '')),
            'tested_wp'=>sanitize_text_field((string)($release['tested_wp'] ?? '')),
            'published_at'=>sanitize_text_field((string)($release['published_at'] ?? '')),
            'notes'=>sanitize_text_field((string)($release['notes'] ?? '')),
            'allowed_plans'=>$plans,
        ];
    }

    private static function storeFailure(string $reason, string $message, int $httpCode = 0): array {
        $state = self::state();
        $state = array_merge($state, [
            'ok'=>false,
            'reason'=>sanitize_key($reason),
            'message'=>sanitize_text_field($message),
            'http_code'=>$httpCode,
            'last_checked_at'=>current_time('mysql', true),
        ]);
        update_option(self::STATE_OPTION, $state, false);
        self::resetHealthCache();
        return $state;
    }

    private static function certificationFingerprint(): string {
        $phase6b3 = LicenseGovernanceLifecycle::health();
        $state = self::state();
        $payload = [
            'phase'=>self::PHASE,
            'version'=>self::VERSION,
            'phase6b3_certified_fingerprint'=>(string)($phase6b3['certified_fingerprint_sha256'] ?? ''),
            'remote_api_host'=>strtolower((string)(wp_parse_url(RemoteLicenseClient::baseUrl(), PHP_URL_HOST) ?: '')),
            'channel'=>self::channel(),
            'private_catalog_ready'=>!empty($state['private_catalog_ready']),
            'licensed_downloads_ready'=>!empty($state['licensed_downloads_ready']),
            'manifest_hmac_ready'=>!empty($state['manifest_hmac_ready']),
            'package_sha256_ready'=>!empty($state['package_sha256_ready']),
            'short_lived_download_tokens'=>!empty($state['short_lived_download_tokens']),
            'wordpress_update_transient'=>!empty($state['wordpress_update_transient']),
            'forced_auto_updates'=>false,
            'downgrades_allowed'=>false,
            'update_enforcement'=>self::UPDATE_ENFORCEMENT,
            'rollback_mode'=>self::ROLLBACK_MODE,
        ];
        $json = wp_json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash('sha256', is_string($json) ? $json : '{}');
    }

    private static function certState(): array {
        $state = get_option(self::CERT_OPTION, []);
        return is_array($state) ? $state : [];
    }

    private static function canonicalize($value) {
        if (is_array($value)) {
            if (array_keys($value) !== range(0, count($value)-1)) ksort($value, SORT_STRING);
            foreach ($value as $key=>$item) $value[$key] = self::canonicalize($item);
        }
        return $value;
    }

    private static function audit(string $event, array $details, string $reason): void {
        if (!class_exists(GovernanceRecovery::class)) return;
        GovernanceRecovery::appendLedger($event, [
            'entity_type'=>'private_update_distribution',
            'entity_ref'=>self::PHASE,
            'client_id'=>0,
            'project_id'=>0,
            'destination_uuid'=>'',
        ], array_merge($details, [
            'phase'=>self::PHASE,
            'forced_auto_updates'=>false,
            'downgrades_allowed'=>false,
            'runtime_enforcement'=>'shadow',
        ]), $reason);
    }
}
