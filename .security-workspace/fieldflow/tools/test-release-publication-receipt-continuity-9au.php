<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    $GLOBALS['ff_test_options'] = [
        'fieldflow_release_manager_last_state' => [
            'version'=>'2.2.564',
            'channel'=>'stable',
            'sha256'=>str_repeat('a', 64),
            'published_at'=>'2026-09-25 15:00:00',
        ],
    ];
    function sanitize_text_field($v){ return trim((string)$v); }
    function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string)$v)); }
    function absint($v){ return abs((int)$v); }
    function get_option($k,$d=[]){ return $GLOBALS['ff_test_options'][$k] ?? $d; }
    function update_option($k,$v,$autoload=false){ $GLOBALS['ff_test_options'][$k]=$v; return true; }
    function get_site_option($k,$d=[]){ return $d; }
    function update_site_option($k,$v){ return true; }
    function is_multisite(){ return false; }
    function wp_cache_delete($k,$g=''){ return true; }
}
namespace RoutesPro\Core {
    final class AuditLog {
        public static function exists(): bool { return true; }
        public static function recent(int $limit=50, array $filters=[]): array {
            return [[
                'after_json'=>json_encode([
                    'version'=>'2.2.565',
                    'channel'=>'stable',
                    'sha256'=>str_repeat('b',64),
                    'bytes'=>123,
                    'release_id'=>'rel-565',
                    'published_at'=>'2026-09-25 16:00:00',
                    'published_by'=>7,
                    'publisher_service'=>'fieldflow-release-publisher',
                ]),
                'created_at'=>'2026-09-25 16:00:00',
                'actor_user_id'=>7,
            ]];
        }
    }
}
namespace {
    require dirname(__DIR__) . '/src/Cloud/ReleasePublicationReceipt.php';
    $receipt = \RoutesPro\Cloud\ReleasePublicationReceipt::current('2.2.565');
    $ok = ($receipt['version'] ?? '') === '2.2.565'
        && ($receipt['receipt_source'] ?? '') === 'audit_log'
        && ($receipt['sha256'] ?? '') === str_repeat('b',64)
        && (($GLOBALS['ff_test_options']['fieldflow_release_manager_last_state']['version'] ?? '') === '2.2.565');
    if (!$ok) {
        fwrite(STDERR, 'FAILED publication receipt recovery' . PHP_EOL);
        exit(1);
    }
    echo "RELEASE MANAGER PUBLICATION RECEIPT 9AU RUNTIME OK\n";
}
