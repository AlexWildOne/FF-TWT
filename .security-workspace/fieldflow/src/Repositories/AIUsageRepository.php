<?php
namespace RoutesPro\Repositories;

if (!defined('ABSPATH')) exit;

/**
 * Canonical ledger for generative-AI usage and FinOps attribution.
 *
 * 2.2.529 keeps backwards compatibility with installations that have not yet
 * completed the additive attribution migration. Optional columns are feature
 * detected before reads/writes so the plugin remains safe during upgrades.
 */
class AIUsageRepository {
    private static ?array $columnCache = null;
    private static ?bool $existsCache = null;

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_ai_usage_logs';
    }

    public static function exists(): bool {
        if (self::$existsCache !== null) return self::$existsCache;
        global $wpdb;
        $table = self::table();
        self::$existsCache = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        return self::$existsCache;
    }

    public static function resetSchemaCache(): void {
        self::$columnCache = null;
        self::$existsCache = null;
    }

    public static function hasAttributionColumns(): bool {
        $columns = self::columns();
        foreach (['client_id','project_id','feature_key','request_kind'] as $column) {
            if (!isset($columns[$column])) return false;
        }
        return true;
    }

    public static function insert(array $data): int {
        global $wpdb;
        if (!self::exists()) return 0;
        $row = [
            'module_key' => sanitize_key((string)($data['module_key'] ?? 'general')) ?: 'general',
            'provider' => sanitize_key((string)($data['provider'] ?? 'none')) ?: 'none',
            'model_name' => sanitize_text_field((string)($data['model_name'] ?? '')),
            'status' => in_array((string)($data['status'] ?? 'success'), ['success','error','blocked'], true) ? (string)$data['status'] : 'error',
            'duration_ms' => max(0, (int)($data['duration_ms'] ?? 0)),
            'input_chars' => max(0, (int)($data['input_chars'] ?? 0)),
            'output_chars' => max(0, (int)($data['output_chars'] ?? 0)),
            'input_tokens' => isset($data['input_tokens']) ? max(0, (int)$data['input_tokens']) : null,
            'output_tokens' => isset($data['output_tokens']) ? max(0, (int)$data['output_tokens']) : null,
            'error_code' => sanitize_key((string)($data['error_code'] ?? '')),
            'error_message' => isset($data['error_message']) ? wp_strip_all_tags((string)$data['error_message']) : null,
            'user_id' => absint($data['user_id'] ?? get_current_user_id()),
            'meta_json' => !empty($data['meta']) ? wp_json_encode($data['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => current_time('mysql'),
        ];
        $optional = [
            'usage_source'=>sanitize_key((string)($data['usage_source'] ?? '')),
            'estimated_cost_eur'=>max(0, (float)($data['estimated_cost_eur'] ?? 0)),
            'prompt_version'=>sanitize_text_field((string)($data['prompt_version'] ?? '')),
            'risk_flags_json'=>!empty($data['risk_flags']) ? wp_json_encode(array_values(array_unique((array)$data['risk_flags']))) : null,
            'client_id'=>absint($data['client_id'] ?? 0),
            'project_id'=>absint($data['project_id'] ?? 0),
            'feature_key'=>sanitize_key((string)($data['feature_key'] ?? '')),
            'request_kind'=>sanitize_key((string)($data['request_kind'] ?? 'runtime')) ?: 'runtime',
        ];
        $columns = self::columns();
        foreach ($optional as $key=>$value) if (isset($columns[$key])) $row[$key] = $value;
        $wpdb->insert(self::table(), $row);
        return (int)$wpdb->insert_id;
    }

    public static function countSince(string $since, ?string $module = null): int {
        global $wpdb;
        if (!self::exists()) return 0;
        $table = self::table();
        if ($module) {
            return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND module_key=%s", $since, sanitize_key($module)));
        }
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $since));
    }

    public static function todayCount(): int {
        return self::countSince(wp_date('Y-m-d 00:00:00', current_time('timestamp')));
    }

    public static function summary(int $days = 7): array {
        global $wpdb;
        if (!self::exists()) return ['total'=>0,'success'=>0,'error'=>0,'blocked'=>0,'avg_ms'=>0,'input_tokens'=>0,'output_tokens'=>0,'estimated_cost_eur'=>0.0,'by_module'=>[],'by_provider'=>[]];
        $days = max(1, min(365, $days));
        $since = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        $table = self::table();
        $extra = isset(self::columns()['estimated_cost_eur'])
            ? ', COALESCE(SUM(input_tokens),0) input_tokens, COALESCE(SUM(output_tokens),0) output_tokens, COALESCE(SUM(estimated_cost_eur),0) estimated_cost_eur'
            : ', 0 input_tokens, 0 output_tokens, 0 estimated_cost_eur';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) total, SUM(status='success') success, SUM(status='error') error, SUM(status='blocked') blocked, AVG(duration_ms) avg_ms {$extra} FROM {$table} WHERE created_at >= %s",
            $since
        ), ARRAY_A) ?: [];
        $modules = $wpdb->get_results($wpdb->prepare(
            "SELECT module_key label, COUNT(*) total, SUM(status='success') success, SUM(status<>'success') failed FROM {$table} WHERE created_at >= %s GROUP BY module_key ORDER BY total DESC",
            $since
        ), ARRAY_A) ?: [];
        $providers = $wpdb->get_results($wpdb->prepare(
            "SELECT provider label, COUNT(*) total, SUM(status='success') success, SUM(status<>'success') failed FROM {$table} WHERE created_at >= %s GROUP BY provider ORDER BY total DESC",
            $since
        ), ARRAY_A) ?: [];
        return [
            'total'=>(int)($row['total'] ?? 0),
            'success'=>(int)($row['success'] ?? 0),
            'error'=>(int)($row['error'] ?? 0),
            'blocked'=>(int)($row['blocked'] ?? 0),
            'avg_ms'=>(int)round((float)($row['avg_ms'] ?? 0)),
            'input_tokens'=>(int)($row['input_tokens'] ?? 0),
            'output_tokens'=>(int)($row['output_tokens'] ?? 0),
            'estimated_cost_eur'=>(float)($row['estimated_cost_eur'] ?? 0),
            'by_module'=>$modules,
            'by_provider'=>$providers,
        ];
    }

    public static function recent(int $limit = 50): array {
        global $wpdb;
        if (!self::exists()) return [];
        $limit = max(1, min(500, $limit));
        $table = self::table();
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A) ?: [];
    }

    /**
     * Current-month cost with optional native attribution filters.
     * Supported scope keys: client_id, project_id, feature_key, provider, model_name, request_kind.
     */
    public static function monthCost(array $scope = []): float {
        global $wpdb;
        if (!self::exists() || !isset(self::columns()['estimated_cost_eur'])) return 0.0;
        $since = wp_date('Y-m-01 00:00:00', current_time('timestamp'));
        $where = ['created_at >= %s'];
        $args = [$since];
        self::appendScopeWhere($where, $args, $scope);
        $sql = 'SELECT COALESCE(SUM(estimated_cost_eur),0) FROM ' . self::table() . ' WHERE ' . implode(' AND ', $where);
        return (float)$wpdb->get_var($wpdb->prepare($sql, ...$args));
    }

    /**
     * Monthly FinOps breakdown. Returns only aggregated metadata, never prompts.
     */
    public static function monthBreakdown(int $limit = 12): array {
        global $wpdb;
        $empty = ['by_client'=>[],'by_project'=>[],'by_feature'=>[],'by_provider'=>[],'by_model'=>[],'by_kind'=>[]];
        if (!self::exists() || !isset(self::columns()['estimated_cost_eur'])) return $empty;
        $limit = max(1, min(50, $limit));
        $since = wp_date('Y-m-01 00:00:00', current_time('timestamp'));
        $table = self::table();
        $columns = self::columns();
        $aggregate = static function (string $column, string $labelExpr = '') use ($wpdb, $table, $since, $limit): array {
            $labelExpr = $labelExpr !== '' ? $labelExpr : $column;
            $sql = $wpdb->prepare(
                "SELECT {$column} id, {$labelExpr} label, COUNT(*) requests, COALESCE(SUM(input_tokens),0)+COALESCE(SUM(output_tokens),0) tokens, COALESCE(SUM(estimated_cost_eur),0) cost FROM {$table} WHERE created_at >= %s GROUP BY {$column} ORDER BY cost DESC, requests DESC LIMIT {$limit}",
                $since
            );
            return $wpdb->get_results($sql, ARRAY_A) ?: [];
        };
        $out = $empty;
        if (isset($columns['client_id'])) {
            $clients = $wpdb->prefix . 'routespro_clients';
            $sql = $wpdb->prepare(
                "SELECT a.client_id id, COALESCE(NULLIF(MAX(c.name),''),CONCAT('Cliente #',a.client_id)) label, COUNT(*) requests, COALESCE(SUM(a.input_tokens),0)+COALESCE(SUM(a.output_tokens),0) tokens, COALESCE(SUM(a.estimated_cost_eur),0) cost FROM {$table} a LEFT JOIN {$clients} c ON c.id=a.client_id WHERE a.created_at >= %s GROUP BY a.client_id ORDER BY cost DESC, requests DESC LIMIT {$limit}",
                $since
            );
            $out['by_client'] = $wpdb->get_results($sql, ARRAY_A) ?: [];
        }
        if (isset($columns['project_id'])) {
            $projects = $wpdb->prefix . 'routespro_projects';
            $sql = $wpdb->prepare(
                "SELECT a.project_id id, COALESCE(NULLIF(MAX(p.name),''),CONCAT('Projeto #',a.project_id)) label, COUNT(*) requests, COALESCE(SUM(a.input_tokens),0)+COALESCE(SUM(a.output_tokens),0) tokens, COALESCE(SUM(a.estimated_cost_eur),0) cost FROM {$table} a LEFT JOIN {$projects} p ON p.id=a.project_id WHERE a.created_at >= %s GROUP BY a.project_id ORDER BY cost DESC, requests DESC LIMIT {$limit}",
                $since
            );
            $out['by_project'] = $wpdb->get_results($sql, ARRAY_A) ?: [];
        }
        if (isset($columns['feature_key'])) $out['by_feature'] = $aggregate('feature_key');
        $out['by_provider'] = $aggregate('provider');
        $out['by_model'] = $aggregate('model_name');
        if (isset($columns['request_kind'])) $out['by_kind'] = $aggregate('request_kind');
        return $out;
    }

    public static function deleteOlderThan(int $days): int {
        global $wpdb;
        if (!self::exists()) return 0;
        $days = max(1, min(3650, $days));
        $cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - ($days * DAY_IN_SECONDS));
        $deleted = $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table() . ' WHERE created_at < %s', $cutoff));
        return is_numeric($deleted) ? (int)$deleted : 0;
    }

    public static function clear(): int {
        global $wpdb;
        if (!self::exists()) return 0;
        $deleted = $wpdb->query('TRUNCATE TABLE ' . self::table());
        return $deleted === false ? 0 : 1;
    }

    private static function appendScopeWhere(array &$where, array &$args, array $scope): void {
        $columns = self::columns();
        $intFields = ['client_id','project_id'];
        foreach ($intFields as $field) {
            $value = absint($scope[$field] ?? 0);
            if ($value > 0 && isset($columns[$field])) {
                $where[] = $field . '=%d';
                $args[] = $value;
            }
        }
        foreach (['feature_key','provider','model_name','request_kind'] as $field) {
            $raw = (string)($scope[$field] ?? '');
            if ($raw === '' || !isset($columns[$field])) continue;
            $value = $field === 'model_name' ? sanitize_text_field($raw) : sanitize_key($raw);
            if ($value === '') continue;
            $where[] = $field . '=%s';
            $args[] = $value;
        }
    }

    public static function columns(): array {
        global $wpdb;
        if (self::$columnCache !== null) return self::$columnCache;
        self::$columnCache = [];
        if (!self::exists()) return self::$columnCache;
        foreach (($wpdb->get_results('SHOW COLUMNS FROM '.self::table(), ARRAY_A) ?: []) as $row) {
            if (!empty($row['Field'])) self::$columnCache[(string)$row['Field']] = true;
        }
        return self::$columnCache;
    }
}
