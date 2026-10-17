<?php
namespace RoutesPro\SaaS;

use RoutesPro\Support\AssignmentResolver;

if (!defined('ABSPATH')) exit;

class UsageService {
    private static bool $registered = false;
    private static array $columnCache = [];

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('fieldflow_core_event', [self::class, 'onEvent'], 10, 4);
    }

    public static function period(): string { return wp_date('Y-m'); }

    public static function increment(int $clientId, string $metric, int $amount = 1, string $period = ''): int {
        global $wpdb;
        if (!$clientId || $amount === 0 || !PlanRepository::tablesReady()) return 0;
        $metric = sanitize_key($metric);
        $period = $period ?: self::period();
        $table = PlanRepository::usageTable();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (client_id,metric,period_key,quantity,updated_at) VALUES (%d,%s,%s,%d,%s) ON DUPLICATE KEY UPDATE quantity=GREATEST(0,quantity+VALUES(quantity)),updated_at=VALUES(updated_at)",
            $clientId,
            $metric,
            $period,
            $amount,
            current_time('mysql')
        ));
        return self::current($clientId, $metric, $period);
    }

    public static function current(int $clientId, string $metric, string $period = ''): int {
        global $wpdb;
        if (!$clientId) return 0;
        $metric = sanitize_key($metric);
        $period = $period ?: self::period();
        $stored = 0;
        if (PlanRepository::tablesReady()) {
            $stored = (int)$wpdb->get_var($wpdb->prepare(
                'SELECT quantity FROM ' . PlanRepository::usageTable() . ' WHERE client_id=%d AND metric=%s AND period_key=%s',
                $clientId,
                $metric,
                $period
            ));
        }
        $live = self::liveCurrent($clientId, $metric, $period);
        return $live === null ? $stored : max($stored, $live);
    }

    public static function limit(int $clientId, string $metric): int {
        $sub = PlanRepository::subscription($clientId);
        return (int)($sub['limits'][$metric] ?? 0);
    }

    public static function canConsume(int $clientId, string $metric, int $amount = 1): array {
        $limit = self::limit($clientId, $metric);
        $used = self::current($clientId, $metric);
        return [
            'allowed' => $limit <= 0 || ($used + $amount) <= $limit,
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
        ];
    }

    public static function snapshot(int $clientId): array {
        $out = [];
        foreach (CapabilityCatalog::limitCatalog() as $key => $def) {
            $out[$key] = self::canConsume($clientId, $key, 0);
            $out[$key]['label'] = $def['label'];
        }
        return $out;
    }

    public static function quotaError(int $clientId, string $metric, string $message = ''): \WP_Error {
        $state = self::canConsume($clientId, $metric, 1);
        $label = (string)(CapabilityCatalog::limitCatalog()[$metric]['label'] ?? $metric);
        $labelLower = function_exists('mb_strtolower') ? mb_strtolower($label, 'UTF-8') : strtolower($label);
        return new \WP_Error('fieldflow_plan_limit_reached', $message ?: ('O limite de ' . $labelLower . ' foi atingido.'), [
            'status' => 429,
            'metric' => $metric,
            'client_id' => $clientId,
            'used' => (int)$state['used'],
            'limit' => (int)$state['limit'],
            'remaining' => $state['remaining'],
        ]);
    }

    public static function onEvent(string $eventName, array $payload, array $context, string $uuid): void {
        $clientId = absint($context['client_id'] ?? ($payload['client_id'] ?? 0));
        if (!$clientId) return;
        $map = ['route.created' => 'routes_monthly', 'submission.created' => 'submissions_monthly'];
        if (isset($map[$eventName])) self::increment($clientId, $map[$eventName], 1);
    }

    private static function liveCurrent(int $clientId, string $metric, string $period): ?int {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        [$start, $end] = self::periodBounds($period);

        if ($metric === 'users_active') {
            $ids = [];
            foreach ((array)AssignmentResolver::get_client_user_ids($clientId) as $id) {
                $id = absint($id); if ($id) $ids[$id] = $id;
            }
            $projectIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$px}projects WHERE client_id=%d", $clientId)) ?: [];
            if ($projectIds) {
                $placeholders = implode(',', array_fill(0, count($projectIds), '%d'));
                $sql = $wpdb->prepare("SELECT DISTINCT user_id FROM {$px}project_assignments WHERE is_active=1 AND project_id IN ({$placeholders})", array_map('absint', $projectIds));
                foreach ((array)$wpdb->get_col($sql) as $id) { $id = absint($id); if ($id) $ids[$id] = $id; }
            }
            foreach ((array)$wpdb->get_col($wpdb->prepare("SELECT DISTINCT owner_user_id FROM {$px}routes WHERE client_id=%d AND owner_user_id IS NOT NULL", $clientId)) as $id) {
                $id = absint($id); if ($id) $ids[$id] = $id;
            }
            return count($ids);
        }

        if ($metric === 'locations_active') {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}locations WHERE client_id=%d", $clientId));
        }

        if ($metric === 'routes_monthly') {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$px}routes WHERE client_id=%d AND created_at >= %s AND created_at < %s", $clientId, $start, $end));
        }

        if ($metric === 'submissions_monthly') {
            $table = $px . 'form_submissions';
            if (!self::tableExists($table) || !self::hasColumn($table, 'client_id')) return null;
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE client_id=%d AND submitted_at >= %s AND submitted_at < %s", $clientId, $start, $end));
        }

        if ($metric === 'scheduled_reports') {
            $table = $px . 'report_schedules';
            if (!self::tableExists($table)) return 0;
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE client_id=%d AND is_active=1", $clientId));
        }

        if ($metric === 'offline_devices') {
            $table = $px . 'mobile_sync_devices';
            if (!self::tableExists($table)) return 0;
            $userIds = array_values(array_filter(array_map('absint', AssignmentResolver::get_client_user_ids($clientId))));
            if (!$userIds) return 0;
            $placeholders = implode(',', array_fill(0, count($userIds), '%d'));
            $sql = $wpdb->prepare("SELECT COUNT(DISTINCT device_id) FROM {$table} WHERE user_id IN ({$placeholders})", $userIds);
            return (int)$wpdb->get_var($sql);
        }

        return null;
    }

    private static function periodBounds(string $period): array {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m', $period, wp_timezone());
        if (!$dt) $dt = new \DateTimeImmutable('first day of this month 00:00:00', wp_timezone());
        return [$dt->format('Y-m-d H:i:s'), $dt->modify('+1 month')->format('Y-m-d H:i:s')];
    }

    private static function tableExists(string $table): bool {
        global $wpdb;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function hasColumn(string $table, string $column): bool {
        global $wpdb;
        $key = $table . '|' . $column;
        if (array_key_exists($key, self::$columnCache)) return self::$columnCache[$key];
        return self::$columnCache[$key] = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }
}
