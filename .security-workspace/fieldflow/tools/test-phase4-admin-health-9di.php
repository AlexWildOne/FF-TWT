<?php
// PHASE 4 ADMIN HEALTH 9DI PASS
namespace RoutesPro\Integrations {
    final class ApiKeyService {
        public static function table(): string { return 'wp_routespro_api_keys'; }
        public static function tableReady(): bool {
            global $wpdb;
            $table = self::table();
            return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        }
    }
    final class WebhookService {
        public static function hooksTable(): string { return 'wp_routespro_webhooks'; }
        public static function deliveriesTable(): string { return 'wp_routespro_webhook_deliveries'; }
        public static function tablesReady(): bool {
            global $wpdb;
            foreach ([self::hooksTable(), self::deliveriesTable()] as $table) {
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return false;
            }
            return true;
        }
    }
}
namespace {
    if (!defined('ABSPATH')) define('ABSPATH', '/tmp/');
    final class Phase4DIStubDatabase {
        public string $prefix = 'wp_';
        public array $seen = [];
        public array $missing = [];
        public function prepare(string $sql, string $value): string { return str_replace('%s', "'" . $value . "'", $sql); }
        public function get_var(string $sql) {
            $this->seen[] = $sql;
            if (preg_match("/^SHOW TABLES LIKE '([^']+)'$/", $sql, $m)) {
                return in_array($m[1], $this->missing, true) ? null : $m[1];
            }
            throw new \RuntimeException('unexpected get_var: ' . $sql);
        }
        public function get_col(string $sql): array {
            $this->seen[] = $sql;
            if ($sql === 'SELECT id FROM wp_routespro_api_keys ORDER BY id DESC LIMIT 500') return range(1, 500);
            if ($sql === 'SELECT id FROM wp_routespro_webhooks ORDER BY id DESC LIMIT 200') return range(1, 200);
            if ($sql === 'SELECT status FROM wp_routespro_webhook_deliveries ORDER BY id DESC LIMIT 500') {
                return array_merge(array_fill(0, 490, 'sent'), array_fill(0, 10, 'failed'));
            }
            throw new \RuntimeException('unexpected get_col: ' . $sql);
        }
    }
    require_once dirname(__DIR__) . '/src/Performance/Phase4AdminHealth.php';
    $check = static function ($condition, string $label): void {
        if (!$condition) { fwrite(STDERR, "9DI FAIL: {$label}\n"); exit(1); }
    };
    $wpdb = new Phase4DIStubDatabase();
    $counts = \RoutesPro\Performance\Phase4AdminHealth::integrationCounts();
    $check($counts === [
        'api_keys'=>500,'webhooks'=>200,'deliveries'=>500,
        'failed_deliveries'=>10,'read_only'=>true,
    ], 'bounded counts agree with former 500/200/500 UI windows');
    $checks = array_values(array_filter($wpdb->seen, static fn($s) => strpos($s, 'SHOW TABLES LIKE') === 0));
    $reads = array_values(array_filter($wpdb->seen, static fn($s) => strpos($s, 'SELECT ') === 0));
    $check(count($checks) === 3 && count($reads) === 3, 'three readiness and three lightweight data reads');
    foreach ($reads as $sql) {
        $check(strpos($sql, 'SELECT *') === false && strpos($sql, 'LIMIT ') !== false, 'bounded narrow projection');
        $check(!preg_match('/(?:key_hash|secret|payload|user_id|ip_hash)/i', $sql), 'no sensitive columns');
    }
    $wpdb = new Phase4DIStubDatabase();
    $wpdb->missing = ['wp_routespro_api_keys','wp_routespro_webhook_deliveries'];
    $empty = \RoutesPro\Performance\Phase4AdminHealth::integrationCounts();
    $check($empty['api_keys'] === 0 && $empty['webhooks'] === 0 && $empty['deliveries'] === 0, 'missing-table path returns zeros');
    $check(count(array_filter($wpdb->seen, static fn($s)=>strpos($s,'SELECT ')===0)) === 0, 'no selects if tables missing');
    $admin = file_get_contents(dirname(__DIR__).'/src/Admin/SystemHealth.php');
    $health = file_get_contents(dirname(__DIR__).'/src/Support/SystemHealth.php');
    $check(substr_count($admin, 'OfflineSyncService::stats()') === 1 && strpos($admin, "is_array(\$report['offline'] ?? null)") !== false,
        'offline stats reused from existing health scan');
    $check(strpos($admin, "is_array(\$report['backoffice_ui'] ?? null)") !== false, 'UI status reused');
    $check(strpos($health, "'offline' => \$offline, // 9DI") !== false, 'aggregate exposed safely');
    $check(strpos($admin, 'Phase4AdminHealth::integrationCounts()') !== false && strpos($admin,'$plansReady = PlanRepository::tablesReady()') !== false, 'lightweight integration counts and single SaaS readiness');
    echo "PHASE 4 ADMIN HEALTH 9DI PASS | counts 500/200/500, no sensitive reads, missing tables, reused snapshots\n";
}
