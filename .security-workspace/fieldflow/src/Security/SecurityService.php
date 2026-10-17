<?php
namespace RoutesPro\Security;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use WP_REST_Request;
use WP_HTTP_Response;

if (!defined('ABSPATH')) exit;

class SecurityService {
    private static array $restStart = [];
    private static bool $registered = false;
    private static bool $webDenialRecorded = false;

    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_security_events';
    }

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        UploadGuard::registerHooks();
        add_filter('rest_pre_dispatch', [self::class, 'beforeRest'], 8, 3);
        add_filter('rest_post_dispatch', [self::class, 'afterRest'], 20, 3);
        add_action('admin_post_fieldflow_security_save', [self::class, 'saveSettings']);
        add_action('admin_init', [self::class, 'guardAdminActions'], 1);
        add_filter('authenticate', [self::class, 'guardWordPressLogin'], 5, 3);
        add_action('wp_login_failed', [self::class, 'recordLoginFailure']);
        add_action('wp_login', [self::class, 'clearLoginFailures'], 10, 2);

        // ASVS v5.0.0 V16.3.2: observe denied FieldFlow actions beyond REST without
        // changing handler output. The wrapper only records explicit HTTP 401/403/429.
        add_filter('wp_die_ajax_handler', [self::class, 'wrapAjaxDieHandler'], PHP_INT_MAX);
        add_filter('wp_die_handler', [self::class, 'wrapDefaultDieHandler'], PHP_INT_MAX);
        add_filter('wp_die_json_handler', [self::class, 'wrapJsonDieHandler'], PHP_INT_MAX);
        add_action('shutdown', [self::class, 'captureDeniedWebAction'], PHP_INT_MAX);
    }

    public static function beforeRest($result, $server, WP_REST_Request $request) {
        if ($result !== null) return $result;
        $route = (string)$request->get_route();
        if (!self::isFieldFlowRoute($route)) return $result;
        $deviceId = substr(preg_replace('/[^A-Za-z0-9._:-]/','',(string)$request->get_header('x-fieldflow-device-id')) ?: '', 0, 191);
        if ($deviceId !== '' && class_exists('RoutesPro\\Mobile\\Settings') && \RoutesPro\Mobile\Settings::is_device_blocked($deviceId)) {
            self::record('device.blocked_request', 'high', ['route'=>$route,'method'=>$request->get_method(),'device_id'=>$deviceId]);
            return new \WP_Error('fieldflow_device_blocked', 'O acesso deste dispositivo foi bloqueado por um administrador.', ['status'=>403]);
        }
        if ($deviceId !== '' && is_user_logged_in() && $route !== '/fieldflow-mobile/v1/device/policy' && class_exists('RoutesPro\\Mobile\\DeviceManager')) {
            $policy = \RoutesPro\Mobile\DeviceManager::policy(get_current_user_id(), $deviceId);
            if (!empty($policy['force_logout'])) {
                self::record('device.forced_logout_request', 'high', ['route'=>$route,'method'=>$request->get_method(),'device_id'=>$deviceId]);
                return new \WP_Error('fieldflow_device_session_revoked', 'A sessão deste dispositivo foi terminada por um administrador.', ['status'=>403]);
            }
        }
        global $wpdb;
        self::$restStart[spl_object_id($request)] = ['time'=>microtime(true),'queries'=>(int)($wpdb->num_queries ?? 0),'memory'=>memory_get_usage(true)];
        $settings = Settings::get()['rate_limits'];
        $bucket = is_user_logged_in() ? 'authenticated' : 'public';
        $limit = is_user_logged_in() ? (int)$settings['authenticated_per_minute'] : (int)$settings['public_per_minute'];
        if (preg_match('#/(ai|insights|analyse|analyze|copilot)#i', $route)) { $bucket = 'ai'; $limit = (int)$settings['ai_per_minute']; }
        elseif (preg_match('#/(export|download|zip|pdf|excel|csv)#i', $route)) { $bucket = 'export'; $limit = (int)$settings['export_per_minute']; }
        elseif ($request->get_method() !== 'GET' && preg_match('#/(upload|media|evidence|draft-file)#i', $route)) { $bucket = 'upload'; $limit = (int)$settings['upload_per_minute']; }
        $check = RateLimiter::enforce('rest:' . $bucket, $limit, 60);
        if (is_wp_error($check)) return $check;
        return $result;
    }

    public static function afterRest($response, $server, WP_REST_Request $request) {
        $route = (string)$request->get_route();
        if (!self::isFieldFlowRoute($route)) return $response;
        $id = spl_object_id($request);
        global $wpdb;
        $started = self::$restStart[$id] ?? ['time'=>microtime(true),'queries'=>(int)($wpdb->num_queries ?? 0),'memory'=>memory_get_usage(true)];
        unset(self::$restStart[$id]);
        $durationMs = (int)round((microtime(true) - (float)$started['time']) * 1000);
        $queryCount = max(0, (int)($wpdb->num_queries ?? 0) - (int)($started['queries'] ?? 0));
        $status = $response instanceof WP_HTTP_Response ? $response->get_status() : 200;
        if (class_exists('RoutesPro\\Observability\\Monitor')) {
            $monitorContext = [];
            if (strpos($route, '/fieldflow-mobile/v1/') === 0 && class_exists('RoutesPro\\Mobile\\ContractRegistry')) {
                $monitorContext['correlation_id'] = \RoutesPro\Mobile\ContractRegistry::correlationId($request);
                $monitorContext['device_id'] = substr(sanitize_text_field((string)$request->get_header('x-fieldflow-device-id')), 0, 191);
                $monitorContext['app_version'] = substr(sanitize_text_field((string)$request->get_header('x-fieldflow-app-version')), 0, 48);
            }
            \RoutesPro\Observability\Monitor::recordRest($route, $request->get_method(), $status, $durationMs, $queryCount, $monitorContext);
        }
        if ($status === 401 || $status === 403 || $status === 429) {
            $reasonCode = self::restDenialReasonCode($response);
            $classification = self::classifyDenial('rest', $status, $route, $reasonCode);
            self::record('rest.denied', self::denialSeverity($classification), [
                'route' => $route,
                'method' => $request->get_method(),
                'status' => $status,
                'classification' => $classification,
                'reason_code' => $reasonCode,
            ]);
        }
        return $response;
    }

    public static function record(string $eventType, string $severity, array $context = []): int {
        global $wpdb;
        $table = self::tableName();
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return 0;
        $eventType = substr(sanitize_key(str_replace('.', '_', $eventType)), 0, 96);
        $severity = in_array($severity, ['low','medium','high','critical','warning'], true) ? $severity : 'low';
        $route = substr(sanitize_text_field((string)($context['route'] ?? '')), 0, 191);
        $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $deviceId = sanitize_text_field((string)($_SERVER['HTTP_X_FIELDFLOW_DEVICE_ID'] ?? ($context['device_id'] ?? '')));
        $fingerprintParts = [$eventType, $route, (string)get_current_user_id(), $deviceId, $ip];
        // Keep different denial classes/statuses in separate aggregation buckets.
        // Non-denial events retain the historical fingerprint shape.
        if (isset($context['classification']) && !is_array($context['classification'])) $fingerprintParts[] = sanitize_key((string)$context['classification']);
        if (isset($context['status']) && is_numeric($context['status'])) $fingerprintParts[] = (string)absint($context['status']);
        $fingerprint = hash('sha256', implode('|', $fingerprintParts));
        $now = current_time('mysql', true);
        $existing = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE fingerprint=%s AND last_seen_at >= %s LIMIT 1", $fingerprint, gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS)));
        $payload = self::sanitizeContext($context);
        if ($existing) {
            $wpdb->query($wpdb->prepare("UPDATE {$table} SET occurrences=occurrences+1, severity=%s, payload_json=%s, last_seen_at=%s, updated_at=%s WHERE id=%d", $severity, wp_json_encode($payload), $now, $now, $existing));
            return $existing;
        }
        $wpdb->insert($table, [
            'event_uuid' => wp_generate_uuid4(),
            'fingerprint' => $fingerprint,
            'event_type' => $eventType,
            'severity' => $severity,
            'status' => 'open',
            'user_id' => get_current_user_id() ?: null,
            'client_id' => absint($context['client_id'] ?? 0) ?: null,
            'project_id' => absint($context['project_id'] ?? 0) ?: null,
            'device_id' => substr($deviceId, 0, 191) ?: null,
            'ip_hash' => $ip !== '' ? hash('sha256', $ip . wp_salt('auth')) : null,
            'route' => $route ?: null,
            'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'occurrences' => 1,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int)$wpdb->insert_id;
        if ($id > 0) {
            EventBus::emit('security.event_detected', ['event_type' => $eventType, 'severity' => $severity, 'security_event_id' => $id], ['entity_type' => 'security_event', 'entity_id' => $id], 'security');
        }
        return $id;
    }

    public static function recent(int $limit = 50, string $status = ''): array {
        global $wpdb;
        $table = self::tableName();
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $limit = max(1, min(250, $limit));
        if ($status !== '') return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status=%s ORDER BY last_seen_at DESC LIMIT %d", sanitize_key($status), $limit), ARRAY_A) ?: [];
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY last_seen_at DESC LIMIT %d", $limit), ARRAY_A) ?: [];
    }

    /**
     * Sanitized read model for JSON exports from Security & Observability.
     * Never exposes user IDs, device IDs, IP hashes, request payloads or stored payload_json.
     */
    public static function exportSafeEvents(int $days = 30, int $limit = 2000): array {
        global $wpdb;
        $table = self::tableName();
        $days = max(1, min(365, $days));
        $limit = max(1, min(5000, $limit));
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, severity, status, user_id, route, payload_json, occurrences, first_seen_at, last_seen_at FROM {$table} WHERE last_seen_at >= %s ORDER BY last_seen_at DESC LIMIT %d",
            $since,
            $limit
        ), ARRAY_A) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) $payload = [];
            $eventType = substr(sanitize_key((string)($row['event_type'] ?? '')), 0, 96);
            $surface = self::surfaceFromEventType($eventType);
            $classification = $surface !== '' ? self::denialClassificationForRow($row) : ['key'=>'','label'=>'—'];
            $route = substr(sanitize_text_field((string)($row['route'] ?? '')), 0, 191);
            $method = strtoupper(substr(sanitize_key((string)($payload['method'] ?? '')), 0, 12));
            $reasonCode = substr(sanitize_key((string)($payload['reason_code'] ?? '')), 0, 96);
            $out[] = [
                'event_type' => $eventType,
                'classification_key' => (string)($classification['key'] ?? ''),
                'classification_label' => (string)($classification['label'] ?? '—'),
                'surface' => $surface,
                'route' => $route !== '' ? $route : 'system',
                'method' => $method !== '' ? $method : '—',
                'http_status' => absint($payload['status'] ?? 0),
                'reason_code' => $reasonCode,
                'legacy_reason' => ($surface !== '' && $reasonCode === ''),
                'severity' => sanitize_key((string)($row['severity'] ?? '')),
                'record_status' => sanitize_key((string)($row['status'] ?? '')),
                'occurrences' => max(0, (int)($row['occurrences'] ?? 0)),
                'actor_type' => absint($row['user_id'] ?? 0) > 0 ? 'authenticated' : 'anonymous',
                'first_seen_at_utc' => sanitize_text_field((string)($row['first_seen_at'] ?? '')),
                'last_seen_at_utc' => sanitize_text_field((string)($row['last_seen_at'] ?? '')),
            ];
        }
        return $out;
    }

    public static function counts(): array {
        global $wpdb;
        $table = self::tableName();
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return ['open'=>0,'high'=>0,'critical'=>0,'total'=>0];
        $row = $wpdb->get_row("SELECT COUNT(*) total, SUM(status='open') open, SUM(status='open' AND severity='high') high, SUM(status='open' AND severity='critical') critical FROM {$table}", ARRAY_A) ?: [];
        return ['open'=>(int)($row['open']??0),'high'=>(int)($row['high']??0),'critical'=>(int)($row['critical']??0),'total'=>(int)($row['total']??0)];
    }

    /**
     * Aggregated denied HTTP events for the backoffice dashboard.
     * Occurrences are already coalesced by record() for one hour per actor/action/IP.
     */
    public static function authorizationDenialSummary(int $days = 30): array {
        global $wpdb;
        $table = self::tableName();
        $empty = ['rest'=>0,'ajax'=>0,'admin_post'=>0,'total'=>0,'days'=>max(1,$days)];
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return $empty;
        $days = max(1, min(365, $days));
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COALESCE(SUM(occurrences),0) AS occurrences FROM {$table} WHERE event_type IN ('rest_denied','ajax_denied','admin_post_denied') AND last_seen_at >= %s GROUP BY event_type",
            $since
        ), ARRAY_A) ?: [];
        $out = ['rest'=>0,'ajax'=>0,'admin_post'=>0,'total'=>0,'days'=>$days];
        foreach ($rows as $row) {
            $type = (string)($row['event_type'] ?? '');
            $count = max(0, (int)($row['occurrences'] ?? 0));
            if ($type === 'rest_denied') $out['rest'] += $count;
            elseif ($type === 'ajax_denied') $out['ajax'] += $count;
            elseif ($type === 'admin_post_denied') $out['admin_post'] += $count;
        }
        $out['total'] = $out['rest'] + $out['ajax'] + $out['admin_post'];
        return $out;
    }

    /**
     * Human-readable denial classification for the security dashboard.
     * Legacy 2.2.320 rows are classified at read time, so no migration or
     * destructive rewrite of the evidence table is required.
     */
    public static function denialClassificationSummary(int $days = 30): array {
        global $wpdb;
        $table = self::tableName();
        $days = max(1, min(365, $days));
        $classes = [
            'session_expired'=>0,
            'authentication_failed'=>0,
            'authorization_denied'=>0,
            'cross_scope_denied'=>0,
            'rate_limited'=>0,
            'csrf_nonce_denied'=>0,
            'security_policy_denied'=>0,
            'other_denied'=>0,
        ];
        $out = $classes + [
            'total'=>0,
            'days'=>$days,
            'surfaces'=>['rest'=>0,'ajax'=>0,'admin_post'=>0],
        ];
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return $out;
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, route, payload_json, occurrences FROM {$table} WHERE event_type IN ('rest_denied','ajax_denied','admin_post_denied') AND last_seen_at >= %s",
            $since
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $occurrences = max(0, (int)($row['occurrences'] ?? 0));
            if ($occurrences < 1) continue;
            $payload = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) $payload = [];
            $surface = self::surfaceFromEventType((string)($row['event_type'] ?? ''));
            $status = absint($payload['status'] ?? 0);
            $reasonCode = sanitize_key((string)($payload['reason_code'] ?? ''));
            $hint = sanitize_key((string)($payload['classification'] ?? ''));
            $classification = self::classifyDenial(
                $surface,
                $status,
                (string)($row['route'] ?? ''),
                $reasonCode,
                $hint
            );
            if (!array_key_exists($classification, $classes)) $classification = 'other_denied';
            $out[$classification] += $occurrences;
            if (isset($out['surfaces'][$surface])) $out['surfaces'][$surface] += $occurrences;
            $out['total'] += $occurrences;
        }
        return $out;
    }

    /**
     * Drill-down read model for denial telemetry. This never rewrites historical rows
     * and never exposes request payloads. It groups only the technical metadata that
     * SecurityService already stores for certification evidence.
     */
    public static function denialDrilldown(int $days = 30, string $classification = 'authorization_denied', int $limit = 30): array {
        global $wpdb;
        $table = self::tableName();
        $days = max(1, min(365, $days));
        $limit = max(1, min(1000, $limit));
        $allowedClasses = [
            'session_expired','authentication_failed','authorization_denied','cross_scope_denied',
            'rate_limited','csrf_nonce_denied','security_policy_denied','other_denied',
        ];
        $classification = sanitize_key($classification);
        if (!in_array($classification, $allowedClasses, true)) $classification = 'authorization_denied';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];

        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, route, payload_json, occurrences, user_id, first_seen_at, last_seen_at FROM {$table} WHERE event_type IN ('rest_denied','ajax_denied','admin_post_denied') AND last_seen_at >= %s ORDER BY last_seen_at DESC",
            $since
        ), ARRAY_A) ?: [];

        $groups = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? ''), true);
            if (!is_array($payload)) $payload = [];
            $surface = self::surfaceFromEventType((string)($row['event_type'] ?? ''));
            if ($surface === '') continue;
            $status = absint($payload['status'] ?? 0);
            $reasonCode = substr(sanitize_key((string)($payload['reason_code'] ?? '')), 0, 96);
            $hint = sanitize_key((string)($payload['classification'] ?? ''));
            $resolvedClass = self::classifyDenial(
                $surface,
                $status,
                (string)($row['route'] ?? ''),
                $reasonCode,
                $hint
            );
            if ($resolvedClass !== $classification) continue;

            $route = substr(sanitize_text_field((string)($row['route'] ?? '')), 0, 191);
            if ($route === '') $route = 'Sem âmbito técnico';
            $method = strtoupper(substr(sanitize_key((string)($payload['method'] ?? '')), 0, 12));
            if ($method === '') $method = '—';
            $occurrences = max(1, (int)($row['occurrences'] ?? 1));
            $key = implode('|', [$surface, $route, (string)$status, $reasonCode, $method]);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'surface'=>$surface,
                    'route'=>$route,
                    'method'=>$method,
                    'status'=>$status,
                    'reason_code'=>$reasonCode,
                    'occurrences'=>0,
                    'authenticated_occurrences'=>0,
                    'anonymous_occurrences'=>0,
                    'actors'=>[],
                    'buckets'=>0,
                    'first_seen_at'=>(string)($row['first_seen_at'] ?? ''),
                    'last_seen_at'=>(string)($row['last_seen_at'] ?? ''),
                ];
            }
            $groups[$key]['occurrences'] += $occurrences;
            $groups[$key]['buckets']++;
            $userId = absint($row['user_id'] ?? 0);
            if ($userId > 0) {
                $groups[$key]['authenticated_occurrences'] += $occurrences;
                $groups[$key]['actors'][$userId] = true;
            } else {
                $groups[$key]['anonymous_occurrences'] += $occurrences;
            }
            $first = (string)($row['first_seen_at'] ?? '');
            $last = (string)($row['last_seen_at'] ?? '');
            if ($first !== '' && ($groups[$key]['first_seen_at'] === '' || strcmp($first, $groups[$key]['first_seen_at']) < 0)) $groups[$key]['first_seen_at'] = $first;
            if ($last !== '' && ($groups[$key]['last_seen_at'] === '' || strcmp($last, $groups[$key]['last_seen_at']) > 0)) $groups[$key]['last_seen_at'] = $last;
        }

        $out = [];
        foreach ($groups as $group) {
            $group['unique_actors'] = count($group['actors']);
            unset($group['actors']);
            $group['legacy_reason'] = ($group['reason_code'] === '');
            $out[] = $group;
        }
        usort($out, static function(array $a, array $b): int {
            $byOccurrences = (int)$b['occurrences'] <=> (int)$a['occurrences'];
            if ($byOccurrences !== 0) return $byOccurrences;
            return strcmp((string)$b['last_seen_at'], (string)$a['last_seen_at']);
        });
        return array_slice($out, 0, $limit);
    }

    public static function denialDrilldownTotals(array $rows): array {
        $totals = ['occurrences'=>0,'authenticated'=>0,'anonymous'=>0,'endpoints'=>count($rows)];
        foreach ($rows as $row) {
            $totals['occurrences'] += max(0, (int)($row['occurrences'] ?? 0));
            $totals['authenticated'] += max(0, (int)($row['authenticated_occurrences'] ?? 0));
            $totals['anonymous'] += max(0, (int)($row['anonymous_occurrences'] ?? 0));
        }
        return $totals;
    }

    public static function denialClassificationForRow(array $row): array {
        $surface = self::surfaceFromEventType((string)($row['event_type'] ?? ''));
        if ($surface === '') return ['key'=>'','label'=>'—'];
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) $payload = [];
        $classification = self::classifyDenial(
            $surface,
            absint($payload['status'] ?? 0),
            (string)($row['route'] ?? ''),
            sanitize_key((string)($payload['reason_code'] ?? '')),
            sanitize_key((string)($payload['classification'] ?? ''))
        );
        return ['key'=>$classification, 'label'=>self::denialClassificationLabel($classification)];
    }

    public static function denialClassificationLabel(string $classification): string {
        $labels = [
            'session_expired'=>'Sessão ausente/expirada',
            'authentication_failed'=>'Falha de autenticação',
            'authorization_denied'=>'Autorização negada',
            'cross_scope_denied'=>'Cross-scope negado',
            'rate_limited'=>'Rate limited',
            'csrf_nonce_denied'=>'CSRF / nonce recusado',
            'security_policy_denied'=>'Política / entitlement',
            'other_denied'=>'Outra recusa',
        ];
        return $labels[$classification] ?? 'Outra recusa';
    }

    public static function wrapAjaxDieHandler($handler) {
        return self::wrapDieHandler($handler);
    }

    public static function wrapDefaultDieHandler($handler) {
        return self::wrapDieHandler($handler);
    }

    public static function wrapJsonDieHandler($handler) {
        return self::wrapDieHandler($handler);
    }

    /**
     * Wrap WordPress' selected wp_die handler, preserving the exact original handler.
     * This is observation-only: response content, status and exit semantics are untouched.
     */
    private static function wrapDieHandler($handler) {
        if (!is_callable($handler)) return $handler;
        return static function($message, $title = '', $args = []) use ($handler) {
            $args = is_array($args) ? $args : [];
            $status = isset($args['response']) && is_numeric($args['response']) ? (int)$args['response'] : (int)http_response_code();
            if (in_array($status, [401,403,429], true)) {
                $hint = ($status === 403 && self::looksLikeCsrfNonceFailure($message)) ? 'csrf_nonce_denied' : '';
                self::recordDeniedWebAction($status, $hint);
            }
            return call_user_func($handler, $message, $title, $args);
        };
    }

    /**
     * Catches denied responses produced without wp_die(), for example wp_send_json_error(..., 403).
     */
    public static function captureDeniedWebAction(): void {
        if (self::$webDenialRecorded) return;
        $status = (int)http_response_code();
        if (!in_array($status, [401,403,429], true)) return;
        self::recordDeniedWebAction($status);
    }

    private static function recordDeniedWebAction(int $status, string $classificationHint = ''): void {
        if (self::$webDenialRecorded || !in_array($status, [401,403,429], true)) return;
        $surface = self::currentWebActionSurface();
        if ($surface === '') return;
        $action = self::currentFieldFlowAction();
        if ($action === '') return;

        self::$webDenialRecorded = true;
        try {
            $context = self::deniedWebActionContext($surface, $action, $status);
            $classification = self::classifyDenial($surface, $status, $context['route'], '', $classificationHint);
            $context['classification'] = $classification;
            self::record($surface . '.denied', self::denialSeverity($classification), $context);
        } catch (\Throwable $e) {
            // Observability must never change the authorization response or availability.
        }
    }

    private static function currentWebActionSurface(): string {
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) return 'ajax';
        global $pagenow;
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === 'admin-post.php' || (string)$pagenow === 'admin-post.php') return 'admin_post';
        return '';
    }

    private static function currentFieldFlowAction(): string {
        if (!isset($_REQUEST['action']) || is_array($_REQUEST['action'])) return '';
        $action = substr(sanitize_key((string)wp_unslash($_REQUEST['action'])), 0, 96);
        if ($action === '') return '';
        if (strpos($action, 'fieldflow_') !== 0 && strpos($action, 'routespro_') !== 0) return '';
        return $action;
    }

    private static function deniedWebActionContext(string $surface, string $action, int $status): array {
        $context = [
            'route' => $surface . ':' . $action,
            'surface' => $surface,
            'action' => $action,
            'method' => substr(sanitize_key(strtolower((string)($_SERVER['REQUEST_METHOD'] ?? 'post'))), 0, 12),
            'status' => $status,
        ];

        // Strict numeric allowlist only. Never persist the request payload, nonce, token, filename or message body.
        $idMap = [
            'client_id'=>'client_id', 'project_id'=>'project_id', 'route_id'=>'route_id',
            'route_stop_id'=>'route_stop_id', 'stop_id'=>'stop_id', 'form_id'=>'form_id',
            'submission_id'=>'submission_id', 'location_id'=>'location_id', 'campaign_id'=>'campaign_id',
            'briefing_id'=>'briefing_id', 'implementation_id'=>'implementation_id', 'message_id'=>'message_id',
            'batch_id'=>'batch_id', 'expense_id'=>'expense_id', 'stock_id'=>'stock_id',
            'automation_id'=>'automation_id', 'report_id'=>'report_id', 'id'=>'object_id', 'user_id'=>'target_user_id',
        ];
        foreach ($idMap as $requestKey => $contextKey) {
            if (!isset($_REQUEST[$requestKey]) || is_array($_REQUEST[$requestKey])) continue;
            $value = absint(wp_unslash($_REQUEST[$requestKey]));
            if ($value > 0) $context[$contextKey] = $value;
        }
        return $context;
    }

    private static function restDenialReasonCode($response): string {
        if (is_wp_error($response)) {
            $codes = $response->get_error_codes();
            return isset($codes[0]) ? substr(sanitize_key((string)$codes[0]), 0, 96) : '';
        }
        if ($response instanceof WP_HTTP_Response) {
            $data = $response->get_data();
            if (is_array($data) && isset($data['code']) && !is_array($data['code'])) {
                return substr(sanitize_key((string)$data['code']), 0, 96);
            }
        }
        return '';
    }

    private static function surfaceFromEventType(string $eventType): string {
        $eventType = sanitize_key($eventType);
        if ($eventType === 'rest_denied') return 'rest';
        if ($eventType === 'ajax_denied') return 'ajax';
        if ($eventType === 'admin_post_denied') return 'admin_post';
        return '';
    }

    private static function classifyDenial(string $surface, int $status, string $route = '', string $reasonCode = '', string $hint = ''): string {
        $allowed = [
            'session_expired','authentication_failed','authorization_denied','cross_scope_denied',
            'rate_limited','csrf_nonce_denied','security_policy_denied','other_denied',
        ];
        $hint = sanitize_key($hint);
        if (in_array($hint, $allowed, true)) return $hint;
        if ($status === 429) return 'rate_limited';

        $surface = sanitize_key($surface);
        $route = strtolower((string)$route);
        $reasonCode = strtolower(sanitize_key($reasonCode));

        if ($status === 401) {
            if ($surface === 'rest' && preg_match('#/auth/login/?$#', $route)) return 'authentication_failed';
            if ($reasonCode !== '' && preg_match('/(?:invalid_credentials|login_failed|authentication_failed|api_key_missing|api_key_revoked|api_key_expired|api_key_invalid)/', $reasonCode)) {
                return 'authentication_failed';
            }
            return 'session_expired';
        }

        if ($status === 403) {
            if ($reasonCode !== '' && preg_match('/(?:scope|tenant|cross_client|cross_project|client_scope|project_scope)/', $reasonCode)) {
                return 'cross_scope_denied';
            }
            if ($reasonCode !== '' && preg_match('/(?:device_blocked|device_session_revoked|forced_logout|diagnostics_disabled|plan_required|portal_forbidden|billing_forbidden|api_plan_required|entitlement)/', $reasonCode)) {
                return 'security_policy_denied';
            }
            return 'authorization_denied';
        }
        return 'other_denied';
    }

    private static function denialSeverity(string $classification): string {
        if ($classification === 'session_expired') return 'low';
        if ($classification === 'authentication_failed' || $classification === 'rate_limited') return 'warning';
        if ($classification === 'cross_scope_denied' || $classification === 'csrf_nonce_denied') return 'high';
        return 'medium';
    }

    private static function looksLikeCsrfNonceFailure($message): bool {
        if (!is_scalar($message)) return false;
        $raw = trim((string)$message);
        // check_ajax_referer() uses wp_die(-1, 403) for nonce failure.
        if ($raw === '-1') return true;
        $text = strtolower(wp_strip_all_tags($raw));
        if ($text === '') return false;
        foreach (['nonce', 'security check', 'security verification', 'are you sure you want to do this', 'tem a certeza de que pretende fazer isto', 'link you followed has expired', 'ligação que seguiu expirou'] as $needle) {
            if (strpos($text, $needle) !== false) return true;
        }
        return false;
    }

    public static function guardAdminActions(): void {
        if (empty($_REQUEST['action'])) return;
        $action = sanitize_key((string)wp_unslash($_REQUEST['action']));
        if (strpos($action, 'fieldflow') === false && strpos($action, 'routespro') === false) return;
        if (!preg_match('/export|download|zip|pdf|excel|csv/i', $action)) return;
        $limit = (int)Settings::get()['rate_limits']['export_per_minute'];
        $check = RateLimiter::enforce('admin_export', $limit, 60);
        if (is_wp_error($check)) wp_die($check->get_error_message(), 'FieldFlow', ['response'=>429,'back_link'=>true]);
    }

    public static function guardWordPressLogin($user, string $username, string $password) {
        if ($username === '') return $user;
        $key = self::loginKey($username);
        $attempts = (int)get_transient($key);
        $limit = max(5, (int)Settings::get()['rate_limits']['public_per_minute']);
        $limit = min(20, $limit);
        if ($attempts >= $limit) {
            self::record('login.temporarily_blocked', 'warning', ['username_hash'=>hash('sha256',strtolower($username))]);
            return new \WP_Error('fieldflow_login_temporarily_blocked', 'Demasiadas tentativas de acesso. Tenta novamente dentro de 15 minutos.');
        }
        return $user;
    }

    public static function recordLoginFailure(string $username): void {
        if ($username === '') return;
        $key=self::loginKey($username);
        $attempts=(int)get_transient($key)+1;
        set_transient($key,$attempts,15*MINUTE_IN_SECONDS);
        self::record('login.failed','low',['username_hash'=>hash('sha256',strtolower($username)),'attempts'=>$attempts]);
    }

    public static function clearLoginFailures(string $userLogin, $user): void {
        delete_transient(self::loginKey($userLogin));
    }

    private static function loginKey(string $username): string {
        $ip=sanitize_text_field((string)($_SERVER['REMOTE_ADDR']??'unknown'));
        return 'ff_wp_login_'.md5(strtolower(trim($username)).'|'.$ip);
    }

    public static function saveSettings(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_security_settings');
        Settings::save(is_array($_POST['fieldflow_security'] ?? null) ? wp_unslash($_POST['fieldflow_security']) : []);
        AuditLog::record('security.settings_updated', 'system', 0, null, Settings::get(), ['source' => 'backoffice']);
        wp_safe_redirect(add_query_arg(['page'=>'routespro-security-observability','tab'=>'settings','updated'=>1], admin_url('admin.php')));
        exit;
    }

    private static function isFieldFlowRoute(string $route): bool {
        return strpos($route, '/fieldflow') === 0 || strpos($route, '/routespro') === 0;
    }

    private static function sanitizeContext(array $context): array {
        $blocked = ['password','token','access_token','refresh_token','authorization','nonce','secret','api_key','key'];
        $walk = static function($value, $key = '') use (&$walk, $blocked) {
            $lower = strtolower((string)$key);
            foreach ($blocked as $needle) if ($lower !== '' && strpos($lower, $needle) !== false) return '[redacted]';
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) $out[$k] = $walk($v, (string)$k);
                return $out;
            }
            if (is_object($value)) return '[object]';
            if (is_string($value)) return substr(sanitize_textarea_field($value), 0, 1000);
            if (is_scalar($value) || $value === null) return $value;
            return '[unsupported]';
        };
        return $walk($context);
    }
}
