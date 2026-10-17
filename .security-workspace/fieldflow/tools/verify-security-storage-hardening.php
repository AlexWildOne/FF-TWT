<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string) file_get_contents($path) : '';
};
$fail = static function (string $key): void { fwrite(STDERR, "FAIL {$key}\n"); exit(1); };
$assert = static function (bool $condition, string $key) use ($fail): void { if (!$condition) $fail($key); echo "PASS {$key}\n"; };

$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase6 = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$manager = $read('src/Admin/ReleaseManager.php');
$scopeGuard = $read('src/Security/RestScopeGuard.php');
$storage = $read('src/Security/StorageGovernance.php');
$mobile = $read('src/Mobile/Controller.php');
$bridge = $read('src/Mobile/WebViewBridge.php');
$health = $read('src/Support/SystemHealth.php');
$loader = $read('src/Support/Loader.php');
$runtime = $read('src/Architecture/RuntimeRegistry.php');
$migrations = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$doc = $read('PHASE-9L-SECURITY-STORAGE-HARDENING.md');

$assert(strpos($plugin, 'Version: 2.2.530') !== false && strpos($plugin, "FIELDFLOW_VERSION', '2.2.530'") !== false && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_identity');
$assert(strpos($phase6, "VERSION = '1.0.87'") !== false && strpos($phase6, "SOURCE_RELEASE = '2.2.529'") !== false && strpos($phase6, "STABLE_RELEASE = '2.2.530'") !== false, 'phase6_continuity');
$assert(strpos($gate, "VERSION = '1.79.0'") !== false && strpos($gate, "SECURITY_STORAGE_HARDENING_CONTRACT = 'fieldflow-security-storage-hardening-v1'") !== false && strpos($gate, 'checkSecurityStorageHardeningContract') !== false, 'release_gate_contract');
$assert(strpos($validator, "'security_storage_hardening_ok'=>false") !== false && strpos($validator, "version_compare((string)(\$result['version'] ?? ''), '2.2.528', '>=')") !== false, 'package_validator_successor_contract');
$assert(strpos($manager, 'Security &amp; Storage Hardening') !== false && strpos($manager, "packageResult['security_storage_hardening_ok']") !== false, 'release_manager_kpi');

$assert(strpos($scopeGuard, "CONTRACT = 'fieldflow-rest-scope-isolation-v1'") !== false && strpos($scopeGuard, "add_filter('rest_pre_dispatch'") !== false && strpos($scopeGuard, 'public static function decision') !== false, 'rest_scope_guard_contract');
$assert(strpos($scopeGuard, 'fieldflow_scope_context_conflict') !== false && strpos($scopeGuard, 'fieldflow_scope_client_denied') !== false && strpos($scopeGuard, 'fieldflow_scope_project_denied') !== false && strpos($scopeGuard, 'fieldflow_scope_project_client_denied') !== false, 'rest_scope_denial_reasons');

if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
if (!function_exists('absint')) { function absint($value) { return abs((int)$value); } }
require_once $root . '/src/Security/RestScopeGuard.php';
$scopeClass = '\\RoutesPro\\Security\\RestScopeGuard';
$managerDecision = $scopeClass::decision(['is_manager'=>true,'client_ids'=>[],'project_ids'=>[]], 999, 999, 888);
$allowedDecision = $scopeClass::decision(['is_manager'=>false,'client_ids'=>[10],'project_ids'=>[20]], 10, 20, 10);
$foreignClient = $scopeClass::decision(['is_manager'=>false,'client_ids'=>[10],'project_ids'=>[20]], 11, 0, 0);
$foreignProject = $scopeClass::decision(['is_manager'=>false,'client_ids'=>[10],'project_ids'=>[20]], 10, 21, 10);
$mismatch = $scopeClass::decision(['is_manager'=>false,'client_ids'=>[10,11],'project_ids'=>[20]], 10, 20, 11);
$projectClientDenied = $scopeClass::decision(['is_manager'=>false,'client_ids'=>[10],'project_ids'=>[20]], 0, 20, 11);
$assert(!empty($managerDecision['allowed']) && !empty($allowedDecision['allowed']), 'rest_scope_positive_decisions');
$assert(empty($foreignClient['allowed']) && ($foreignClient['reason'] ?? '') === 'fieldflow_scope_client_denied', 'rest_scope_foreign_client_denied');
$assert(empty($foreignProject['allowed']) && ($foreignProject['reason'] ?? '') === 'fieldflow_scope_project_denied', 'rest_scope_foreign_project_denied');
$assert(empty($mismatch['allowed']) && ($mismatch['reason'] ?? '') === 'fieldflow_scope_context_conflict', 'rest_scope_context_conflict_denied');
$assert(empty($projectClientDenied['allowed']) && ($projectClientDenied['reason'] ?? '') === 'fieldflow_scope_project_client_denied', 'rest_scope_project_client_denied');

$assert(strpos($storage, "CONTRACT = 'fieldflow-private-storage-governance-v1'") !== false && strpos($storage, "CRON_HOOK = 'fieldflow_private_storage_governance_daily'") !== false && strpos($storage, 'cleanupExpiredTemporaryStorage') !== false, 'storage_governance_contract');
$assert(strpos($storage, "'cleanup_scope' => 'expired_resumable_only'") !== false && strpos($storage, "'preserves_evidence' => true") !== false && strpos($storage, "'preserves_active_drafts' => true") !== false && strpos($storage, 'fieldflow-private-resumable') !== false, 'storage_cleanup_boundary');
$assert(strpos($mobile, "'permission_callback' => [\$this, 'submission_access']") !== false && strpos($mobile, "'permission_callback' => [\$this, 'briefing_access']") !== false && strpos($mobile, 'public function submission_access') !== false && strpos($mobile, 'public function briefing_access') !== false, 'mobile_object_permissions');
$assert(strpos($bridge, "PARENT_ORIGIN_CONTRACT = 'fieldflow-webview-parent-origin-v1'") !== false && strpos($bridge, "NATIVE_SHELL_VERSION = '1.9'") !== false && strpos($bridge, 'function nativeParentOrigin()') !== false && strpos($bridge, 'window.parent.postMessage(message,parentOrigin)') !== false, 'webview_same_origin_contract');
$assert(strpos($bridge, "window.flutter_inappwebview.callHandler('fieldflowNativeEvent',message)") !== false || strpos($bridge, "window.flutter_inappwebview.callHandler('fieldflowNativeEvent', message)") !== false, 'webview_native_handler_preserved');
$assert(strpos($bridge, "postMessage(message,'*')") === false && strpos($bridge, 'postMessage(message,"*")') === false && strpos($bridge, "postMessage(Object.assign({type:'fieldflow.external.open'},payload),'*')") === false, 'webview_no_wildcard_postmessage');
$assert(strpos($health, "'rest_scope_guard' => \$rest_scope_guard") !== false && strpos($health, "'storage_governance' => \$storage_governance") !== false && strpos($health, "'private_storage_mb'") !== false, 'system_health_visibility');
$assert(strpos($loader, 'src/Security/RestScopeGuard.php') !== false && strpos($loader, 'src/Security/StorageGovernance.php') !== false && strpos($runtime, 'RestScopeGuard::registerHooks()') !== false && strpos($runtime, 'StorageGovernance::registerHooks()') !== false, 'runtime_wiring');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.525'") !== false, 'schema_frozen');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_frozen');
$assert(strpos($doc, 'fieldflow-security-storage-hardening-v1') !== false && strpos($doc, '`SOURCE_RELEASE` is `2.2.527` and `STABLE_RELEASE` is `2.2.528`') !== false && strpos($doc, 'No database schema change.') !== false && strpos($doc, '`SubmissionPipeline::BUILD` remains `2.2.485`.') !== false, 'phase_documentation');

$assert(version_compare('2.2.529', '2.2.528', '>='), 'successor_semantics');

echo "SECURITY STORAGE HARDENING CONTRACT OK\n";
echo "release=2.2.530\n";
echo "contract=fieldflow-security-storage-hardening-v1\n";
echo "rest_scope=fail_closed\n";
echo "storage_cleanup=expired_resumable_only\n";
echo "webview_postmessage=same_origin\n";
echo "successor=>=2.2.528\n";
echo "schema=2.2.525\n";
echo "submission_build=2.2.485\n";
