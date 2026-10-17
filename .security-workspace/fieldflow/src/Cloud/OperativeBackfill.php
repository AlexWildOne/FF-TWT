<?php
namespace RoutesPro\Cloud;

use RoutesPro\Core\DataContract;

if (!defined('ABSPATH')) exit;

/**
 * Phase 4B.1, privacy-minimised operative dimension for Azure reporting.
 *
 * Only operationally relevant WordPress users are replicated. Email addresses,
 * phones and other profile/contact data are deliberately excluded. Live profile
 * and operational mutations enqueue an idempotent operative.snapshot through
 * the already-certified HMAC Azure queue.
 */
final class OperativeBackfill {
    public const VERSION = '1.0.0';
    private const UUID_NAMESPACE = 'fieldflow-cloud-operative-backfill-v1';
    private const OPTION_PREFIX = 'fieldflow_cloud_backfill_operatives_';
    private const EVENT_NAME = 'operative.snapshot';
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;

        add_action('fieldflow_core_event', [self::class, 'onCoreEvent'], 25, 4);
        add_action('user_register', [self::class, 'onUserRegister'], 20, 1);
        add_action('profile_update', [self::class, 'onProfileUpdate'], 20, 2);
        add_action('set_user_role', [self::class, 'onUserRoleChanged'], 20, 3);
        add_action('add_user_role', [self::class, 'onUserRoleChanged'], 20, 2);
        add_action('remove_user_role', [self::class, 'onUserRoleChanged'], 20, 2);
        add_action('delete_user', [self::class, 'onUserDelete'], 20, 3);
    }

    public static function onUserRegister(int $userId): void {
        self::enqueueLiveSnapshot($userId, 'user_register');
    }

    public static function onProfileUpdate(int $userId, $oldUserData = null): void {
        self::enqueueLiveSnapshot($userId, 'profile_update');
    }

    public static function onUserRoleChanged(int $userId, $role = '', $oldRoles = []): void {
        self::enqueueLiveSnapshot($userId, 'user_role_changed');
    }

    public static function onUserDelete(int $userId, $reassign = null, $user = null): void {
        if (!self::cloudEnabled()) return;
        $wpUser = $user instanceof \WP_User ? $user : get_userdata($userId);
        if (!$wpUser) return;
        $built = self::buildFromUser($wpUser, false, false, true);
        if (empty($built['ok'])) return;
        AzureSyncFoundation::enqueue(
            self::EVENT_NAME,
            (array)$built['contract'],
            (array)$built['context'],
            wp_generate_uuid4()
        );
    }

    /**
     * Operational events also refresh the operative dimension. This keeps names
     * and role flags current without coupling routes/forms to a new event bus.
     */
    public static function onCoreEvent(string $eventName, array $payload, array $context, string $eventUuid): void {
        if (!self::cloudEnabled()) return;

        $ids = [];
        foreach (['actor_user_id','owner_user_id','user_id'] as $key) {
            $id = absint($context[$key] ?? 0);
            if ($id > 0) $ids[$id] = $id;
        }
        $candidates = [
            $payload['owner_user_id'] ?? 0,
            $payload['user_id'] ?? 0,
            $payload['data']['owner_user_id'] ?? 0,
            $payload['data']['user_id'] ?? 0,
            $payload['route']['owner_user_id'] ?? 0,
            $payload['visit']['owner_user_id'] ?? 0,
        ];
        foreach ($candidates as $candidate) {
            $id = absint($candidate);
            if ($id > 0) $ids[$id] = $id;
        }

        foreach ($ids as $userId) {
            if (!self::isRelevantUser($userId)) continue;
            $built = self::buildOperativeContract($userId, false);
            if (empty($built['ok'])) continue;
            AzureSyncFoundation::enqueue(
                self::EVENT_NAME,
                (array)$built['contract'],
                (array)$built['context'],
                self::relatedEventUuid($eventUuid, $eventName, $userId)
            );
        }
    }

    public static function start(bool $reset = false): array {
        global $wpdb;
        $maxOption = self::option('max_id');
        $cursorOption = self::option('cursor');
        $startedOption = self::option('started_at');
        $existingMax = max(0, (int)get_option($maxOption, 0));
        if ($existingMax > 0 && !$reset) {
            return array_merge(['ok'=>true,'already_started'=>true], self::status());
        }

        $maxId = max(0, (int)$wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->users}"));
        update_option($cursorOption, 0, false);
        update_option($maxOption, $maxId, false);
        update_option($startedOption, current_time('mysql', true), false);
        return array_merge(['ok'=>true,'already_started'=>false], self::status());
    }

    public static function enqueueNext(int $limit = 50): array {
        $limit = max(1, min(100, $limit));
        $status = self::status();
        if (empty($status['started'])) $status = self::start(false);
        if (!AzureConnectionManager::ready()) return ['ok'=>false,'reason'=>'cloud_not_configured','enqueued'=>0];
        if (!AzureConnectionManager::syncEnabled()) return ['ok'=>false,'reason'=>'cloud_sync_disabled','enqueued'=>0];
        if (empty($status['operatives_enabled'])) return ['ok'=>false,'reason'=>'operatives_sync_disabled','enqueued'=>0];
        if (empty(AzureSyncFoundation::health()['table_ready'])) return ['ok'=>false,'reason'=>'cloud_queue_missing','enqueued'=>0];

        $kickLimit = max(1, min(25, $limit));
        $dispatchBefore = !empty($status['queue_pending'])
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];

        $cursor = max(0, (int)($status['cursor'] ?? 0));
        $maxId = max(0, (int)($status['max_id'] ?? 0));
        if ($maxId <= 0) {
            $after = self::status();
            return ['ok'=>true,'enqueued'=>0,'completed'=>!empty($after['fully_completed']),'status'=>$after];
        }

        $rows = self::candidateRows($cursor, $maxId, $limit);
        if (!$rows) {
            update_option(self::option('cursor'), $maxId, false);
            $after = self::status();
            return ['ok'=>true,'enqueued'=>0,'completed'=>!empty($after['fully_completed']),'dispatch_before'=>$dispatchBefore,'status'=>$after];
        }

        $enqueued = 0;
        $duplicates = 0;
        $failed = 0;
        $lastId = $cursor;
        foreach ($rows as $row) {
            $userId = absint($row['ID'] ?? 0);
            if ($userId <= 0) continue;
            $built = self::buildOperativeContract($userId, true);
            if (empty($built['ok'])) {
                $lastId = $userId;
                update_option(self::option('cursor'), $lastId, false);
                continue;
            }
            $result = AzureSyncFoundation::enqueue(
                self::EVENT_NAME,
                (array)$built['contract'],
                (array)$built['context'],
                self::snapshotEventUuid($userId)
            );
            if (empty($result['ok'])) {
                $failed++;
                break;
            }
            if (!empty($result['duplicate'])) $duplicates++; else $enqueued++;
            $lastId = $userId;
            update_option(self::option('cursor'), $lastId, false);
        }

        $queueNow = self::queueStats();
        $dispatchAfter = ($enqueued > 0 || $duplicates > 0 || (int)$queueNow['pending'] > 0)
            ? AzureSyncFoundation::dispatchNow($kickLimit)
            : ['ok'=>true,'claimed'=>0,'completed'=>0,'retried'=>0,'dead'=>0];
        $after = self::status();

        return [
            'ok'=>$failed === 0 && !empty($dispatchAfter['ok']),
            'enqueued'=>$enqueued,
            'duplicates'=>$duplicates,
            'failed'=>$failed,
            'cursor'=>$lastId,
            'completed'=>!empty($after['fully_completed']),
            'dispatch_before'=>$dispatchBefore,
            'dispatch_after'=>$dispatchAfter,
            'status'=>$after,
        ];
    }

    public static function processPendingNow(int $limit = 25): array {
        $limit = max(1, min(25, $limit));
        if (!AzureConnectionManager::ready()) return ['ok'=>false,'reason'=>'cloud_not_configured'];
        if (!AzureConnectionManager::syncEnabled()) return ['ok'=>false,'reason'=>'cloud_sync_disabled'];
        $dispatch = AzureSyncFoundation::dispatchNow($limit);
        $status = self::status();
        return [
            'ok'=>!empty($dispatch['ok']),
            'dispatch'=>$dispatch,
            'queue_pending'=>(int)($status['queue_pending'] ?? 0),
            'queue_running'=>(int)($status['queue_running'] ?? 0),
            'queue_dead'=>(int)($status['queue_dead'] ?? 0),
            'completed'=>!empty($status['fully_completed']),
            'status'=>$status,
        ];
    }

    public static function status(): array {
        $settings = AzureConnectionManager::settings();
        $cursor = max(0, (int)get_option(self::option('cursor'), 0));
        $maxId = max(0, (int)get_option(self::option('max_id'), 0));
        $startedAt = (string)get_option(self::option('started_at'), '');
        $started = $maxId > 0 || $startedAt !== '';
        $total = ($started && $maxId > 0) ? self::eligibleCount(0, $maxId) : 0;
        $remaining = ($started && $maxId > 0) ? self::eligibleCount($cursor, $maxId) : 0;
        $queue = self::queueStats();

        return [
            'available'=>true,
            'cloud_ready'=>AzureConnectionManager::ready(),
            'sync_enabled'=>AzureConnectionManager::syncEnabled(),
            'operatives_enabled'=>!empty($settings['azure_cloud_sync_operatives']),
            'event_name'=>self::EVENT_NAME,
            'started'=>$started,
            'started_at'=>$startedAt,
            'cursor'=>$cursor,
            'max_id'=>$maxId,
            'total'=>$total,
            'remaining'=>$remaining,
            'enqueued'=>max(0, $total - $remaining),
            'queue_pending'=>(int)$queue['pending'],
            'queue_running'=>(int)$queue['running'],
            'queue_dead'=>(int)$queue['dead'],
            'queue_completed'=>(int)$queue['completed'],
            'fully_completed'=>$started && $remaining === 0 && (int)$queue['pending'] === 0 && (int)$queue['running'] === 0 && (int)$queue['dead'] === 0,
        ];
    }

    public static function buildOperativeContract(int $userId, bool $backfill = false): array {
        $user = get_userdata($userId);
        if (!$user) return ['ok'=>false,'reason'=>'user_missing'];
        return self::buildFromUser($user, $backfill, true, false);
    }

    private static function buildFromUser(\WP_User $user, bool $backfill, bool $active, bool $force): array {
        $userId = absint($user->ID ?? 0);
        if ($userId <= 0) return ['ok'=>false,'reason'=>'invalid_user'];
        if (!$force && !self::isRelevantUser($userId)) return ['ok'=>false,'reason'=>'user_not_operational'];

        $roles = [];
        foreach ((array)$user->roles as $role) {
            $key = sanitize_key((string)$role);
            if ($key !== '') $roles[$key] = $key;
        }
        ksort($roles);

        $displayName = sanitize_text_field((string)$user->display_name);
        if ($displayName === '') $displayName = 'Operativo #' . $userId;

        $data = [
            'operative_id'=>$userId,
            'display_name'=>$displayName,
            'roles'=>array_values($roles),
            'is_active'=>$active,
            'can_execute'=>user_can($user, 'routespro_execute'),
            'can_manage'=>user_can($user, 'routespro_manage'),
            'cloud_backfill'=>$backfill,
            'cloud_backfill_version'=>$backfill ? self::VERSION : '',
        ];
        $contract = DataContract::state('operative', $data);
        $context = [
            'entity_type'=>'operative',
            'entity_id'=>$userId,
            'operative_id'=>$userId,
            'actor_user_id'=>$userId,
        ];
        return ['ok'=>true,'contract'=>$contract,'context'=>$context,'data'=>$data];
    }

    private static function enqueueLiveSnapshot(int $userId, string $reason): void {
        if (!self::cloudEnabled()) return;
        if (!self::isRelevantUser($userId)) return;
        $built = self::buildOperativeContract($userId, false);
        if (empty($built['ok'])) return;
        AzureSyncFoundation::enqueue(
            self::EVENT_NAME,
            (array)$built['contract'],
            (array)$built['context'],
            self::uuidFromSeed('live|' . sanitize_key($reason) . '|' . $userId . '|' . gmdate('YmdHi'))
        );
    }

    private static function cloudEnabled(): bool {
        if (!AzureConnectionManager::syncEnabled()) return false;
        $settings = AzureConnectionManager::settings();
        return !empty($settings['azure_cloud_sync_operatives']);
    }

    /** Operational relevance without exporting contact/profile fields. */
    private static function isRelevantUser(int $userId): bool {
        global $wpdb;
        $userId = absint($userId);
        if ($userId <= 0) return false;
        $px = $wpdb->prefix . 'routespro_';

        if ((int)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$px}routes WHERE owner_user_id=%d LIMIT 1", $userId)) === 1) return true;
        if ((int)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$px}assignments WHERE user_id=%d LIMIT 1", $userId)) === 1) return true;
        if ((int)$wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$px}form_submissions WHERE user_id=%d LIMIT 1", $userId)) === 1) return true;

        $user = get_userdata($userId);
        return $user ? (bool)user_can($user, 'routespro_execute') : false;
    }

    private static function candidateRows(int $cursor, int $maxId, int $limit): array {
        global $wpdb;
        $cursor = max(0, $cursor);
        $maxId = max(0, $maxId);
        $limit = max(1, min(100, $limit));
        $px = $wpdb->prefix . 'routespro_';
        $capKey = $wpdb->prefix . 'capabilities';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID
             FROM {$wpdb->users} u
             WHERE u.ID>%d AND u.ID<=%d
               AND (
                    EXISTS (SELECT 1 FROM {$px}routes r WHERE r.owner_user_id=u.ID)
                 OR EXISTS (SELECT 1 FROM {$px}assignments a WHERE a.user_id=u.ID)
                 OR EXISTS (SELECT 1 FROM {$px}form_submissions s WHERE s.user_id=u.ID)
                 OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=u.ID AND um.meta_key=%s AND (um.meta_value LIKE %s OR um.meta_value LIKE %s))
               )
             ORDER BY u.ID ASC
             LIMIT %d",
            $cursor,
            $maxId,
            $capKey,
            '%route_agent%',
            '%routespro_execute%',
            $limit
        ), ARRAY_A) ?: [];
    }

    private static function eligibleCount(int $cursor, int $maxId): int {
        global $wpdb;
        if ($maxId <= 0) return 0;
        $px = $wpdb->prefix . 'routespro_';
        $capKey = $wpdb->prefix . 'capabilities';
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->users} u
             WHERE u.ID>%d AND u.ID<=%d
               AND (
                    EXISTS (SELECT 1 FROM {$px}routes r WHERE r.owner_user_id=u.ID)
                 OR EXISTS (SELECT 1 FROM {$px}assignments a WHERE a.user_id=u.ID)
                 OR EXISTS (SELECT 1 FROM {$px}form_submissions s WHERE s.user_id=u.ID)
                 OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=u.ID AND um.meta_key=%s AND (um.meta_value LIKE %s OR um.meta_value LIKE %s))
               )",
            max(0, $cursor),
            max(0, $maxId),
            $capKey,
            '%route_agent%',
            '%routespro_execute%'
        ));
    }

    private static function queueStats(): array {
        global $wpdb;
        $stats = ['pending'=>0,'running'=>0,'dead'=>0,'completed'=>0];
        $table = AzureSyncFoundation::table();
        if (!self::tableExists($table)) return $stats;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(status='pending') AS pending,
                SUM(status='running') AS running,
                SUM(status='dead') AS dead,
                SUM(status='completed') AS completed
             FROM {$table}
             WHERE destination='azure' AND event_name=%s AND payload_json LIKE %s",
            self::EVENT_NAME,
            '%\"cloud_backfill\":true%'
        ), ARRAY_A) ?: [];
        foreach ($stats as $key=>$value) $stats[$key] = (int)($row[$key] ?? 0);
        return $stats;
    }

    private static function snapshotEventUuid(int $userId): string {
        return self::uuidFromSeed('snapshot|' . absint($userId));
    }

    private static function relatedEventUuid(string $sourceEventUuid, string $eventName, int $userId): string {
        return self::uuidFromSeed('event|' . sanitize_text_field($sourceEventUuid) . '|' . sanitize_key($eventName) . '|' . absint($userId));
    }

    private static function uuidFromSeed(string $seed): string {
        $tenant = TenantIdentity::tenantId();
        $hash = substr(sha1(self::UUID_NAMESPACE . '|' . $tenant . '|' . $seed), 0, 32);
        $hash[12] = '5';
        $variant = hexdec($hash[16]);
        $hash[16] = dechex(($variant & 0x3) | 0x8);
        return sprintf('%s-%s-%s-%s-%s', substr($hash,0,8), substr($hash,8,4), substr($hash,12,4), substr($hash,16,4), substr($hash,20,12));
    }

    private static function option(string $suffix): string {
        return self::OPTION_PREFIX . sanitize_key($suffix);
    }

    private static function tableExists(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
