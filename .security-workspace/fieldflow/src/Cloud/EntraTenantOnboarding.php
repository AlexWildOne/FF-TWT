<?php
namespace RoutesPro\Cloud;

if (!defined('ABSPATH')) exit;

/**
 * Phase 5C.2 external Microsoft Entra tenant onboarding control plane.
 *
 * Security model:
 * - WordPress never stores client secrets, certificates or access tokens.
 * - Connection state is HMAC signed with the already-certified TWT trust.
 * - The Microsoft browser flow instantiates the Enterprise Application in the target tenant.
 * - Microsoft returns to the dedicated Azure Broker callback.
 * - The Broker verifies the signed state, performs the authoritative token probe,
 *   then posts a tiny HMAC-signed receipt back to this WordPress REST endpoint.
 * - Operational connector delivery remains disabled/shadow.
 */
final class EntraTenantOnboarding {
    public const VERSION = '1.2.0';
    public const STATE_PURPOSE = 'fieldflow.entra.external_tenant.onboarding';
    public const RECEIPT_CONTRACT = 'fieldflow.entra.external_tenant.receipt';
    public const RECEIPT_CONTRACT_VERSION = '1.0';
    public const REST_NAMESPACE = 'fieldflow/v1';
    public const REST_ROUTE = '/entra-onboarding/receipt';
    public const CONSENT_CALLBACK_PATH = '/api/v1/admin-consent/callback';
    public const CONSENT_TTL_SECONDS = 3600;
    public const MAX_CLOCK_SKEW_SECONDS = 300;

    private const STATE_SIGNING_CONTEXT = 'fieldflow-entra-onboarding-state-v1';

    public static function registerHooks(): void {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => 'POST',
            'callback' => [self::class, 'receiveReceipt'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function statusForDestination(array $destination): array {
        $policy = self::policy($destination);
        $status = sanitize_key((string)($policy['entra_onboarding_status'] ?? 'draft'));
        $allowed = ['draft','consent_pending','consent_received','token_probe_failed','consent_denied','tenant_mismatch','ready'];
        if (!in_array($status, $allowed, true)) $status = 'draft';
        $expires = absint($policy['entra_onboarding_expires_at'] ?? 0);
        if ($status === 'consent_pending' && $expires > 0 && time() > $expires) $status = 'draft';
        return [
            'version' => sanitize_text_field((string)($policy['entra_onboarding_version'] ?? self::VERSION)),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'ready' => $status === 'ready' && !empty($policy['entra_onboarding_token_verified']),
            'token_verified' => !empty($policy['entra_onboarding_token_verified']),
            'issued_at' => absint($policy['entra_onboarding_issued_at'] ?? 0),
            'expires_at' => $expires,
            'consent_received_at' => absint($policy['entra_onboarding_consent_received_at'] ?? 0),
            'token_verified_at' => absint($policy['entra_onboarding_token_verified_at'] ?? 0),
            'callback_delivery' => sanitize_key((string)($policy['entra_onboarding_callback_delivery'] ?? 'none')),
            'entra_code' => sanitize_text_field((string)($policy['entra_onboarding_entra_code'] ?? '')),
            'error' => sanitize_text_field((string)($policy['entra_onboarding_error'] ?? '')),
            'delivery_enabled' => false,
            'identity_only' => true,
        ];
    }

    public static function summary(?array $destinations = null): array {
        if ($destinations === null) $destinations = DestinationRegistry::listDestinations();
        $summary = [
            'version'=>self::VERSION,
            'control_plane_ready'=>EntraCrossTenantFoundation::configured(),
            'delivery_enabled'=>false,
            'total'=>0,
            'configured'=>0,
            'pending'=>0,
            'ready'=>0,
            'failed'=>0,
        ];
        foreach ((array)$destinations as $destination) {
            if (!self::isOnboardableDestination($destination)) continue;
            $summary['total']++;
            $validation = EntraCrossTenantFoundation::validateDestinationIdentity($destination, true);
            if (!empty($validation['ok'])) $summary['configured']++;
            $status = self::statusForDestination($destination);
            if ($status['status'] === 'consent_pending' || $status['status'] === 'consent_received') $summary['pending']++;
            elseif (!empty($status['ready'])) $summary['ready']++;
            elseif (in_array($status['status'], ['token_probe_failed','consent_denied','tenant_mismatch'], true)) $summary['failed']++;
        }
        return $summary;
    }

    public static function start(array $destination) {
        if (!EntraCrossTenantFoundation::configured()) {
            return new \WP_Error('fieldflow_5c2_onboarding_broker_not_ready', 'A federação 5C.2 TWT tem de estar configurada antes de iniciar onboarding externo.');
        }
        if (!self::isOnboardableDestination($destination)) {
            return new \WP_Error('fieldflow_5c2_onboarding_destination_invalid', 'Este destino não usa Microsoft Entra cross-tenant.');
        }
        $validation = EntraCrossTenantFoundation::validateDestinationIdentity($destination, true);
        if (empty($validation['ok'])) {
            return new \WP_Error('fieldflow_5c2_onboarding_identity_invalid', (string)($validation['error'] ?? 'Configura tenant e resource scope do destino antes do onboarding.'));
        }
        $identity = (array)($validation['identity'] ?? []);
        $targetTenantId = EntraCrossTenantFoundation::normaliseGuid((string)($identity['target_tenant_id'] ?? ''));
        $resourceScope = trim((string)($identity['resource_scope'] ?? ''));
        $settings = EntraCrossTenantFoundation::settings();
        if ($targetTenantId === (string)$settings['home_tenant_id']) {
            return new \WP_Error('fieldflow_5c2_onboarding_home_tenant', 'O tenant indicado é o tenant TWT. Usa o probe TWT já certificado; o onboarding externo destina-se a outro tenant.');
        }

        $secret = self::hmacSecret();
        if (is_wp_error($secret)) return $secret;
        $callbackUrl = self::wordpressReceiptUrl();
        if (is_wp_error($callbackUrl)) return $callbackUrl;
        $brokerCallback = self::brokerConsentCallbackUrl();
        if (is_wp_error($brokerCallback)) return $brokerCallback;

        try {
            $nonce = self::base64UrlEncode(random_bytes(32));
        } catch (\Throwable $error) {
            return new \WP_Error('fieldflow_5c2_onboarding_nonce_failed', 'Não foi possível gerar o nonce seguro do onboarding.');
        }
        $now = time();
        $statePayload = [
            'v'=>1,
            'purpose'=>self::STATE_PURPOSE,
            'destination_uuid'=>sanitize_text_field((string)($destination['destination_uuid'] ?? '')),
            'fieldflow_tenant_id'=>TenantIdentity::tenantId(),
            'installation_id'=>TenantIdentity::installationId(),
            'site_host'=>strtolower((string)(wp_parse_url(home_url('/'), PHP_URL_HOST) ?: '')),
            'target_tenant_id'=>$targetTenantId,
            'resource_scope'=>$resourceScope,
            'callback_url'=>$callbackUrl,
            'issued_at'=>$now,
            'expires_at'=>$now + self::CONSENT_TTL_SECONDS,
            'nonce'=>$nonce,
        ];
        $stateToken = self::signState($statePayload, (string)$secret);
        if (is_wp_error($stateToken)) return $stateToken;

        $clientId = EntraCrossTenantFoundation::normaliseGuid((string)($settings['broker_client_id'] ?? ''));
        if ($clientId === '') return new \WP_Error('fieldflow_5c2_onboarding_client_id_missing', 'O Application client ID do Broker não está configurado.');
        // Browser-only Enterprise Application bootstrap using the current Microsoft
        // identity platform v2 authorization endpoint. Only the OIDC `openid` scope is
        // requested here. The operational resource scope (ARM/SQL/Power BI/custom API)
        // is deliberately NOT requested from the interactive user and is used only by
        // the authoritative workload-identity token probe after the browser callback.
        // This avoids legacy v1 default-resource behaviour and does not add artificial
        // Graph or client API permissions to the broker application.
        $consentUrl = 'https://login.microsoftonline.com/' . rawurlencode($targetTenantId) . '/oauth2/v2.0/authorize?' . http_build_query([
            'response_type'=>'code',
            'client_id'=>$clientId,
            'redirect_uri'=>$brokerCallback,
            'response_mode'=>'query',
            'scope'=>'openid',
            'state'=>$stateToken,
            'prompt'=>'consent',
        ], '', '&', PHP_QUERY_RFC3986);

        $updated = self::updatePolicy($destination, [
            'entra_onboarding_version'=>self::VERSION,
            'entra_onboarding_status'=>'consent_pending',
            'entra_onboarding_nonce_hash'=>hash('sha256', $nonce),
            'entra_onboarding_issued_at'=>$now,
            'entra_onboarding_expires_at'=>$now + self::CONSENT_TTL_SECONDS,
            'entra_onboarding_consent_received_at'=>0,
            'entra_onboarding_token_verified'=>false,
            'entra_onboarding_token_verified_at'=>0,
            'entra_onboarding_callback_delivery'=>'pending',
            'entra_onboarding_entra_code'=>'',
            'entra_onboarding_error'=>'',
            'entra_onboarding_delivery_enabled'=>false,
            'resource_auth_version'=>class_exists('\RoutesPro\Cloud\ResourceAuthorizationFoundation') ? ResourceAuthorizationFoundation::VERSION : '1.0.0',
            'resource_auth_status'=>'draft',
            'resource_auth_verified'=>false,
            'resource_auth_verified_at'=>0,
            'resource_auth_last_probe_at'=>0,
            'resource_auth_last_http_status'=>0,
            'resource_auth_last_remote_http_status'=>0,
            'resource_auth_last_probe_code'=>'',
            'resource_auth_error'=>'',
            'resource_auth_delivery_enabled'=>false,
        ]);
        if (is_wp_error($updated)) return $updated;

        return [
            'ok'=>true,
            'destination_id'=>(int)($destination['id'] ?? 0),
            'destination_uuid'=>(string)($destination['destination_uuid'] ?? ''),
            'target_tenant_id'=>$targetTenantId,
            'resource_scope'=>$resourceScope,
            'consent_url'=>$consentUrl,
            'expires_at'=>$now + self::CONSENT_TTL_SECONDS,
            'state_exposed_secret'=>false,
            'browser_only'=>true,
            'client_subscription_required'=>false,
            'delivery_enabled'=>false,
        ];
    }

    public static function manualVerify(array $destination) {
        if (!self::isOnboardableDestination($destination)) {
            return new \WP_Error('fieldflow_5c2_onboarding_destination_invalid', 'Este destino não usa Microsoft Entra cross-tenant.');
        }
        $response = EntraCrossTenantFoundation::probeDestination($destination);
        if (is_wp_error($response)) {
            self::updatePolicy($destination, [
                'entra_onboarding_status'=>'token_probe_failed',
                'entra_onboarding_error'=>sanitize_text_field($response->get_error_code()),
                'entra_onboarding_entra_code'=>'',
                'entra_onboarding_token_verified'=>false,
                'entra_onboarding_delivery_enabled'=>false,
            ]);
            return $response;
        }
        $code = (int)wp_remote_retrieve_response_code($response);
        $body = json_decode((string)wp_remote_retrieve_body($response), true);
        if (!is_array($body)) $body = [];
        $ok = $code >= 200 && $code < 300 && !empty($body['ok']) && !empty($body['federated']);
        if ($ok) {
            self::updatePolicy($destination, [
                'entra_onboarding_status'=>'ready',
                'entra_onboarding_consent_received_at'=>absint(self::policy($destination)['entra_onboarding_consent_received_at'] ?? 0) ?: time(),
                'entra_onboarding_token_verified'=>true,
                'entra_onboarding_token_verified_at'=>time(),
                'entra_onboarding_callback_delivery'=>'manual_verification',
                'entra_onboarding_entra_code'=>'',
                'entra_onboarding_error'=>'',
                'entra_onboarding_delivery_enabled'=>false,
            ]);
            return ['ok'=>true,'http_code'=>$code,'target_tenant_id'=>sanitize_text_field((string)($body['target_tenant_id'] ?? '')),'scope'=>sanitize_text_field((string)($body['scope'] ?? ''))];
        }
        $entraCode = sanitize_text_field((string)($body['entra_code'] ?? ''));
        $error = sanitize_text_field((string)($body['error'] ?? 'token_probe_failed'));
        self::updatePolicy($destination, [
            'entra_onboarding_status'=>'token_probe_failed',
            'entra_onboarding_token_verified'=>false,
            'entra_onboarding_entra_code'=>$entraCode,
            'entra_onboarding_error'=>$error,
            'entra_onboarding_delivery_enabled'=>false,
        ]);
        return new \WP_Error('fieldflow_5c2_onboarding_probe_failed', 'O tenant ainda não validou a federação. ' . ($entraCode !== '' ? 'Entra ' . $entraCode . '.' : ''));
    }

    public static function reset(array $destination) {
        if (!self::isOnboardableDestination($destination)) return new \WP_Error('fieldflow_5c2_onboarding_destination_invalid', 'Destino Entra inválido.');
        return self::updatePolicy($destination, [
            'entra_onboarding_version'=>self::VERSION,
            'entra_onboarding_status'=>'draft',
            'entra_onboarding_nonce_hash'=>'',
            'entra_onboarding_issued_at'=>0,
            'entra_onboarding_expires_at'=>0,
            'entra_onboarding_consent_received_at'=>0,
            'entra_onboarding_token_verified'=>false,
            'entra_onboarding_token_verified_at'=>0,
            'entra_onboarding_callback_delivery'=>'none',
            'entra_onboarding_entra_code'=>'',
            'entra_onboarding_error'=>'',
            'entra_onboarding_delivery_enabled'=>false,
            'resource_auth_version'=>class_exists('\RoutesPro\Cloud\ResourceAuthorizationFoundation') ? ResourceAuthorizationFoundation::VERSION : '1.0.0',
            'resource_auth_status'=>'draft',
            'resource_auth_verified'=>false,
            'resource_auth_verified_at'=>0,
            'resource_auth_last_probe_at'=>0,
            'resource_auth_last_http_status'=>0,
            'resource_auth_last_remote_http_status'=>0,
            'resource_auth_last_probe_code'=>'',
            'resource_auth_error'=>'',
            'resource_auth_delivery_enabled'=>false,
        ]);
    }

    public static function receiveReceipt(\WP_REST_Request $request) {
        $raw = (string)$request->get_body();
        if ($raw === '' || strlen($raw) > 131072) return new \WP_REST_Response(['ok'=>false,'error'=>'invalid_body'], 400);
        $payload = json_decode($raw, true);
        if (!is_array($payload) || (string)($payload['contract'] ?? '') !== self::RECEIPT_CONTRACT || (string)($payload['contract_version'] ?? '') !== self::RECEIPT_CONTRACT_VERSION) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'invalid_contract'], 400);
        }
        $verified = self::verifySignedReceipt($request, $payload, $raw);
        if (is_wp_error($verified)) return new \WP_REST_Response(['ok'=>false,'error'=>$verified->get_error_code()], 401);

        $uuid = sanitize_text_field((string)($payload['destination_uuid'] ?? ''));
        if (!preg_match('/^ff_d_[a-f0-9]{24}$/', $uuid)) return new \WP_REST_Response(['ok'=>false,'error'=>'invalid_destination_uuid'], 400);
        $destination = self::destinationByUuid($uuid);
        if (!$destination || !self::isOnboardableDestination($destination)) return new \WP_REST_Response(['ok'=>false,'error'=>'destination_not_found'], 404);

        $identity = EntraCrossTenantFoundation::identityProfileFromDestination($destination);
        $targetTenant = EntraCrossTenantFoundation::normaliseGuid((string)($payload['onboarding']['target_tenant_id'] ?? ''));
        $scope = sanitize_text_field((string)($payload['onboarding']['resource_scope'] ?? ''));
        if ($targetTenant === '' || $targetTenant !== (string)$identity['target_tenant_id'] || $scope !== (string)$identity['resource_scope']) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'destination_identity_mismatch'], 409);
        }

        $policy = self::policy($destination);
        $expectedNonceHash = strtolower(trim((string)($policy['entra_onboarding_nonce_hash'] ?? '')));
        $receiptNonceHash = strtolower(trim((string)($payload['onboarding']['nonce_hash'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedNonceHash) || !hash_equals($expectedNonceHash, $receiptNonceHash)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'onboarding_nonce_mismatch'], 409);
        }

        $current = self::statusForDestination($destination);
        if (!empty($current['ready'])) return new \WP_REST_Response(['ok'=>true,'idempotent'=>true,'status'=>'ready'], 200);
        if (!in_array($current['status'], ['consent_pending','consent_received','token_probe_failed'], true)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'onboarding_state_not_receivable'], 409);
        }

        $incoming = sanitize_key((string)($payload['onboarding']['status'] ?? ''));
        $allowed = ['consent_received','token_probe_failed','consent_denied','tenant_mismatch','ready'];
        if (!in_array($incoming, $allowed, true)) $incoming = 'token_probe_failed';
        $tokenVerified = $incoming === 'ready' && !empty($payload['onboarding']['token_verified']);
        $now = time();
        $changes = [
            'entra_onboarding_status'=>$tokenVerified ? 'ready' : $incoming,
            'entra_onboarding_consent_received_at'=>$now,
            'entra_onboarding_token_verified'=>$tokenVerified,
            'entra_onboarding_token_verified_at'=>$tokenVerified ? $now : 0,
            'entra_onboarding_callback_delivery'=>'received',
            'entra_onboarding_entra_code'=>sanitize_text_field((string)($payload['onboarding']['entra_code'] ?? '')),
            'entra_onboarding_error'=>sanitize_text_field((string)($payload['onboarding']['error'] ?? '')),
            'entra_onboarding_delivery_enabled'=>false,
        ];
        $updated = self::updatePolicy($destination, $changes);
        if (is_wp_error($updated)) return new \WP_REST_Response(['ok'=>false,'error'=>'registry_update_failed'], 500);

        return new \WP_REST_Response([
            'ok'=>true,
            'status'=>$tokenVerified ? 'ready' : $incoming,
            'token_verified'=>$tokenVerified,
            'delivery_enabled'=>false,
        ], 200);
    }

    public static function statusLabel(string $status): string {
        $labels = [
            'draft'=>'DRAFT',
            'consent_pending'=>'LIGAÇÃO PENDENTE',
            'consent_received'=>'LIGAÇÃO RECEBIDA',
            'token_probe_failed'=>'TOKEN PROBE FAILED',
            'consent_denied'=>'LIGAÇÃO NÃO CONCLUÍDA',
            'tenant_mismatch'=>'TENANT MISMATCH',
            'ready'=>'READY, IDENTIDADE',
        ];
        return $labels[$status] ?? 'DRAFT';
    }

    private static function isOnboardableDestination(array $destination): bool {
        if (!ConnectorFoundation::is5cType((string)($destination['type_key'] ?? ''))) return false;
        $profile = ConnectorFoundation::profileFromDestination($destination);
        return (string)($profile['auth_strategy'] ?? '') === 'entra_cross_tenant';
    }

    private static function wordpressReceiptUrl() {
        $url = esc_url_raw(rest_url(ltrim(self::REST_NAMESPACE . self::REST_ROUTE, '/')), ['https']);
        if ($url === '' || strtolower((string)(wp_parse_url($url, PHP_URL_SCHEME) ?: '')) !== 'https') {
            return new \WP_Error('fieldflow_5c2_onboarding_rest_url_invalid', 'O callback WordPress do onboarding tem de estar disponível por HTTPS.');
        }
        return $url;
    }

    private static function brokerConsentCallbackUrl() {
        $settings = EntraCrossTenantFoundation::settings();
        $endpoint = (string)($settings['broker_endpoint'] ?? '');
        $host = strtolower((string)(wp_parse_url($endpoint, PHP_URL_HOST) ?: ''));
        if ($host !== EntraCrossTenantFoundation::BROKER_HOST) return new \WP_Error('fieldflow_5c2_onboarding_broker_host_invalid', 'O Entra Broker configurado não corresponde ao host dedicado TWT.');
        return 'https://' . $host . self::CONSENT_CALLBACK_PATH;
    }

    private static function signState(array $payload, string $secret) {
        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') return new \WP_Error('fieldflow_5c2_onboarding_state_encode', 'Não foi possível codificar o estado de ligação Microsoft.');
        $encoded = self::base64UrlEncode($json);
        $signature = hash_hmac('sha256', self::STATE_SIGNING_CONTEXT . "\n" . $encoded, $secret);
        return $encoded . '.' . $signature;
    }

    private static function verifySignedReceipt(\WP_REST_Request $request, array $payload, string $raw) {
        $secret = self::hmacSecret();
        if (is_wp_error($secret)) return $secret;
        $timestampRaw = trim((string)$request->get_header('x-fieldflow-timestamp'));
        $timestamp = ctype_digit($timestampRaw) ? (int)$timestampRaw : 0;
        if ($timestamp <= 0 || abs(time() - $timestamp) > self::MAX_CLOCK_SKEW_SECONDS) return new \WP_Error('invalid_timestamp', 'Timestamp inválido.');
        $tenant = sanitize_text_field((string)$request->get_header('x-fieldflow-tenant'));
        $installation = sanitize_text_field((string)$request->get_header('x-fieldflow-installation'));
        $eventId = sanitize_text_field((string)$request->get_header('x-fieldflow-event-id'));
        $bodyHashHeader = strtolower(trim((string)$request->get_header('x-fieldflow-body-sha256')));
        $signatureHeader = trim((string)$request->get_header('x-fieldflow-signature'));
        if (!preg_match('/^(?:v1|hmac-v1)=([a-f0-9]{64})$/i', $signatureHeader, $match)) return new \WP_Error('invalid_signature_header', 'Assinatura em falta.');
        $signature = strtolower((string)$match[1]);
        if ($tenant !== TenantIdentity::tenantId() || $installation !== TenantIdentity::installationId()) return new \WP_Error('signed_context_mismatch', 'Contexto de tenant inválido.');
        if ($eventId === '' || !hash_equals(strtolower($eventId), strtolower(sanitize_text_field((string)($payload['event']['uuid'] ?? ''))))) return new \WP_Error('event_mismatch', 'Event ID inválido.');
        $bodyHash = hash('sha256', $raw);
        if (!preg_match('/^[a-f0-9]{64}$/', $bodyHashHeader) || !hash_equals($bodyHash, $bodyHashHeader)) return new \WP_Error('body_hash_mismatch', 'Hash do corpo inválido.');
        if ((string)($payload['tenant']['tenant_id'] ?? '') !== $tenant || (string)($payload['tenant']['installation_id'] ?? '') !== $installation) return new \WP_Error('payload_context_mismatch', 'Payload de tenant inválido.');
        $canonical = implode("\n", ['fieldflow-hmac-v1',$timestampRaw,$tenant,$installation,$eventId,$bodyHash]);
        $expected = hash_hmac('sha256', $canonical, (string)$secret);
        if (!hash_equals($expected, $signature)) return new \WP_Error('invalid_signature', 'Assinatura inválida.');
        return true;
    }

    private static function hmacSecret() {
        $azure = AzureConnectionManager::settings();
        $secret = trim((string)($azure['azure_cloud_hmac_secret'] ?? ''));
        if (strlen($secret) < 32) return new \WP_Error('fieldflow_5c2_onboarding_hmac_missing', 'O HMAC TWT certificado não está disponível.');
        return $secret;
    }

    private static function destinationByUuid(string $uuid): array {
        global $wpdb;
        if (!DestinationRegistry::tableReady(DestinationRegistry::destinationsTable())) return [];
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DestinationRegistry::destinationsTable() . ' WHERE destination_uuid=%s LIMIT 1', $uuid), ARRAY_A) ?: [];
    }

    private static function policy(array $destination): array {
        $policy = json_decode((string)($destination['retry_policy_json'] ?? ''), true);
        return is_array($policy) ? $policy : [];
    }

    private static function updatePolicy(array $destination, array $changes) {
        global $wpdb;
        $id = absint($destination['id'] ?? 0);
        if ($id <= 0 || !DestinationRegistry::tableReady(DestinationRegistry::destinationsTable())) return new \WP_Error('fieldflow_5c2_onboarding_registry_missing', 'Destination Registry indisponível.');
        $policy = self::policy($destination);
        foreach ($changes as $key=>$value) $policy[sanitize_key((string)$key)] = $value;
        $ok = $wpdb->update(DestinationRegistry::destinationsTable(), [
            'retry_policy_json'=>wp_json_encode($policy, JSON_UNESCAPED_SLASHES),
            'status'=>!empty($destination['is_active']) ? 'shadow_ready' : ((string)($destination['status'] ?? '') === 'draft' ? 'draft' : 'disabled'),
            'updated_at'=>current_time('mysql', true),
        ], ['id'=>$id]);
        if ($ok === false) return new \WP_Error('fieldflow_5c2_onboarding_registry_update_failed', 'Não foi possível atualizar o estado do onboarding no registry.');
        return true;
    }

    private static function base64UrlEncode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
