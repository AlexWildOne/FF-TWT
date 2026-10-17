<?php
namespace RoutesPro\Security;

use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

/**
 * Protected Media Shadow Mode.
 *
 * Observe-only and fail-open by design. This component never blocks, rewrites,
 * moves or deletes a file. It evaluates the access decision that a future
 * enforcement layer would make and stores only aggregate security telemetry.
 */
final class MediaShadow {
    public const VERSION = '1.0';
    public const MODE = 'shadow';

    private static $tableReady = null;
    private static array $requestCache = [];

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_media_shadow_access';
    }

    public static function tableReady(): bool {
        if (self::$tableReady !== null) return (bool) self::$tableReady;
        global $wpdb;
        $table = self::table();
        self::$tableReady = ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        return (bool) self::$tableReady;
    }

    /**
     * Evaluate a local FieldFlow media URL and record the shadow decision.
     * Context may include user_id and explicit_allow / explicit_reason for a
     * server-validated access path such as a signed team-message attachment.
     */
    public static function observeUrl(string $url, string $surface, array $context = []): array {
        try {
            $url = esc_url_raw(trim($url));
            $surface = sanitize_key($surface ?: 'unknown');
            $userId = absint($context['user_id'] ?? get_current_user_id());
            if ($url === '') return self::result('unknown', 'empty_url', 0, $userId, $surface);

            $cacheKey = hash('sha256', $userId . '|' . $surface . '|' . $url . '|' . (!empty($context['explicit_allow']) ? '1' : '0'));
            if (isset(self::$requestCache[$cacheKey])) return self::$requestCache[$cacheKey];

            $row = self::registryRow($url);
            $decision = self::decide($row, $userId, $context);
            $registryId = absint($row['id'] ?? 0);
            self::record($registryId, $userId, $surface, $decision['decision'], $decision['reason']);

            return self::$requestCache[$cacheKey] = self::result(
                $decision['decision'],
                $decision['reason'],
                $registryId,
                $userId,
                $surface
            );
        } catch (\Throwable $e) {
            return self::result('unknown', 'shadow_error', 0, absint($context['user_id'] ?? get_current_user_id()), sanitize_key($surface ?: 'unknown'));
        }
    }

    public static function diagnostics(int $days = 30): array {
        $days = max(1, min(365, $days));
        $base = [
            'version' => self::VERSION,
            'mode' => self::MODE,
            'table_ready' => false,
            'days' => $days,
            'events' => 0,
            'allow' => 0,
            'deny' => 0,
            'unknown' => 0,
            'unique_media' => 0,
            'last_seen_at' => '',
        ];
        if (!self::tableReady()) return $base;

        global $wpdb;
        $table = self::table();
        $cutoff = gmdate('Y-m-d', current_time('timestamp', true) - (($days - 1) * DAY_IN_SECONDS));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT decision, SUM(event_count) AS total FROM {$table} WHERE observed_date >= %s GROUP BY decision",
            $cutoff
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            $key = sanitize_key((string)($row['decision'] ?? 'unknown'));
            if (in_array($key, ['allow','deny','unknown'], true)) $base[$key] = (int)($row['total'] ?? 0);
        }
        $base['events'] = (int)$base['allow'] + (int)$base['deny'] + (int)$base['unknown'];
        $base['unique_media'] = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT media_registry_id) FROM {$table} WHERE observed_date >= %s AND media_registry_id > 0",
            $cutoff
        ));
        $base['last_seen_at'] = (string)$wpdb->get_var("SELECT MAX(last_seen_at) FROM {$table}");
        $base['table_ready'] = true;
        return $base;
    }

    private static function registryRow(string $url): array {
        if (!class_exists(MediaRegistry::class) || !MediaRegistry::tableReady()) return [];
        global $wpdb;
        $table = MediaRegistry::table();
        $hash = hash('sha256', $url);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE url_hash=%s LIMIT 1", $hash), ARRAY_A);
        if ($row) return $row;

        // Historical values may contain a harmless query string. The registry
        // normally stores the canonical upload URL, so try the queryless form.
        $parts = wp_parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) return [];
        $canonical = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
        $hash = hash('sha256', $canonical);
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE url_hash=%s LIMIT 1", $hash), ARRAY_A) ?: [];
    }

    private static function decide(array $row, int $userId, array $context): array {
        if (!empty($context['explicit_allow'])) {
            $reason = sanitize_key((string)($context['explicit_reason'] ?? 'server_validated')) ?: 'server_validated';
            return ['decision'=>'allow', 'reason'=>$reason];
        }
        if ($userId <= 0) return ['decision'=>'deny', 'reason'=>'unauthenticated'];
        if (Permissions::is_manager($userId)) return ['decision'=>'allow', 'reason'=>'manager'];
        if (!$row) return ['decision'=>'unknown', 'reason'=>'unregistered_media'];

        $owner = absint($row['owner_user_id'] ?? 0);
        $clientId = absint($row['client_id'] ?? 0);
        $projectId = absint($row['project_id'] ?? 0);
        $routeId = absint($row['route_id'] ?? 0);
        $submissionId = absint($row['submission_id'] ?? 0);

        if ($owner > 0 && $owner === $userId) return ['decision'=>'allow', 'reason'=>'media_owner'];

        // Operational users get strict route ownership/assignment semantics.
        // Broad project scope is not enough to conclude that another field
        // worker's evidence should be readable.
        if (user_can($userId, 'routespro_execute')) {
            if ($routeId > 0) {
                return Permissions::can_edit_route($routeId, $userId)
                    ? ['decision'=>'allow', 'reason'=>'assigned_route']
                    : ['decision'=>'deny', 'reason'=>'route_not_assigned'];
            }
            if ($submissionId > 0 && self::submissionBelongsToUser($submissionId, $userId)) {
                return ['decision'=>'allow', 'reason'=>'submission_owner'];
            }
            if ($clientId > 0 || $projectId > 0) {
                return ['decision'=>'unknown', 'reason'=>'operational_context_insufficient'];
            }
            return ['decision'=>'unknown', 'reason'=>'missing_object_context'];
        }

        // Client/portal users retain the read-oriented scope model.
        if ($projectId > 0 && Permissions::is_allowed_project($projectId, $userId)) {
            return ['decision'=>'allow', 'reason'=>'project_scope'];
        }
        if ($clientId > 0 && Permissions::is_allowed_client($clientId, $userId)) {
            return ['decision'=>'allow', 'reason'=>'client_scope'];
        }
        if ($routeId > 0 && Permissions::can_access_route($routeId, $userId)) {
            return ['decision'=>'allow', 'reason'=>'route_scope'];
        }
        if ($clientId > 0 || $projectId > 0 || $routeId > 0 || $submissionId > 0) {
            return ['decision'=>'deny', 'reason'=>'scope_mismatch'];
        }
        return ['decision'=>'unknown', 'reason'=>'missing_object_context'];
    }

    private static function submissionBelongsToUser(int $submissionId, int $userId): bool {
        if ($submissionId <= 0 || $userId <= 0) return false;
        global $wpdb;
        $table = $wpdb->prefix . 'routespro_form_submissions';
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table} WHERE id=%d LIMIT 1",
            $submissionId
        )) === $userId;
    }

    private static function record(int $registryId, int $userId, string $surface, string $decision, string $reason): void {
        if (!self::tableReady()) return;
        global $wpdb;
        $table = self::table();
        $decision = in_array($decision, ['allow','deny','unknown'], true) ? $decision : 'unknown';
        $reason = sanitize_key($reason ?: 'unknown');
        $surface = sanitize_key($surface ?: 'unknown');
        $date = current_time('Y-m-d', true);
        $now = current_time('mysql', true);

        $sql = "INSERT INTO {$table}
            (media_registry_id,user_id,surface,decision,reason_code,observed_date,event_count,first_seen_at,last_seen_at,created_at,updated_at)
            VALUES (%d,%d,%s,%s,%s,%s,1,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE event_count=event_count+1,last_seen_at=VALUES(last_seen_at),updated_at=VALUES(updated_at)";
        $wpdb->query($wpdb->prepare(
            $sql,
            $registryId,
            $userId,
            $surface,
            $decision,
            $reason,
            $date,
            $now,
            $now,
            $now,
            $now
        ));
    }

    private static function result(string $decision, string $reason, int $registryId, int $userId, string $surface): array {
        return [
            'mode' => self::MODE,
            'decision' => $decision,
            'reason' => $reason,
            'media_registry_id' => $registryId,
            'user_id' => $userId,
            'surface' => $surface,
            'enforced' => false,
        ];
    }
}
