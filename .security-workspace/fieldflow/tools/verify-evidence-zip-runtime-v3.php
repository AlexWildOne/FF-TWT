<?php
/**
 * Build-time verifier for FieldFlow 2.2.473 Evidence ZIP Runtime v3.
 * Runs without WordPress and executes the portable ZIP writer directly.
 */
$root = dirname(__DIR__);
$failures = [];
$passes = [];
$assert = static function (bool $ok, string $name, string $detail = '') use (&$failures, &$passes): void {
    if ($ok) { $passes[] = $name; return; }
    $failures[] = $name . ($detail !== '' ? ': ' . $detail : '');
};
$source = static function (string $relative) use ($root): string {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$containsAll = static function (string $text, array $markers): bool {
    foreach ($markers as $marker) if (strpos($text, $marker) === false) return false;
    return true;
};

$stats = $source('src/Rest/StatsController.php');
$front = $source('src/Shortcodes.php');
$portal = $source('src/Front/ClientPortalShortcodeRenderer.php');
$gate = $source('src/Support/ReleaseGate.php');
$fieldflow = $source('fieldflow.php');
$readme = $source('readme.txt');
$phase = $source('src/Cloud/Phase6StableRelease.php');
$responsive = $source('src/Front/ResponsiveAssets.php');
$registry = $source('src/Architecture/RuntimeRegistry.php');

$assert($containsAll($stats, [
    'write_analytics_evidence_zip_portable',
    'analytics_zip_write_all',
    'analytics_zip_dos_time',
    "engine'=>'portable-store'",
    "pack('VvvvvvVVVvv'",
    "VvvvvvvVVVvvvvvVV",
    "pack('VvvvvVVv'",
]), 'portable_store_writer');
$assert($containsAll($stats, [
    'evidence_request_invalid',
    "'ff_evidence_' . get_current_user_id() . '_' . \$token",
    'analytics_prepared_evidence_download_url',
    'ff_evidence_ready_',
    'handlePreparedEvidenceDownload',
    'routespro_download_prepared_analytics_evidence',
    "'response' => 410",
]), 'server_bound_prepare_and_temporary_download');
$assert($containsAll($stats, [
    'central_offset',
    'entries_total',
    'strrpos($tail, "PK\\x05\\x06")',
    '$centralSignature === "PK\\x01\\x02"',
]), 'independent_zip_validation');
$assert($containsAll($stats, [
    'MediaRegistry::tableReady()',
    'storage_path',
    "source' => 'media_registry'",
]), 'media_registry_local_path_recovery');
$assert($containsAll($front, [
    'data-ffad-evidence-download',
    "api+'analytics/evidence-archive'",
    'evidencias-fieldflow.zip',
    'analyticsEvidenceGroups',
    'evidencePrepareToken',
    'JSON.stringify({token:group.token})',
]), 'operational_front_rest_prepare');
$assert($containsAll($portal, [
    'data-evidence-download',
    "api+'analytics/evidence-archive'",
    'evidencias-fieldflow.zip',
    'evidencePrepareToken',
    'JSON.stringify({token:group.token})',
]), 'client_portal_rest_prepare');
$assert(strpos($front, "function evidenceAction(entity,label){const files=evidenceFiles(entity?.files),url=String(entity?.download_url||'')") === false, 'operational_front_no_legacy_direct_path');
$assert(strpos($portal, "evidenceArchiveAction(m,entity,suffix,label){const files=evidenceFiles(entity?.files),direct=String(entity?.download_url||'')") === false, 'client_portal_no_legacy_direct_path');
$assert(strpos($front, "body:JSON.stringify({location:group.location||'loja',files:group.files})") === false && strpos($portal, "body:JSON.stringify({location:group.location||'loja',files:group.files})") === false, 'front_never_submits_arbitrary_evidence_urls');
$assert($containsAll($registry, ['admin_post_routespro_download_prepared_analytics_evidence', 'handlePreparedEvidenceDownload']), 'prepared_download_runtime_hook');
$assert($containsAll($gate, ["VERSION = '1.79.0'", 'checkEvidenceZipRuntimeV3Contract', 'fieldflow-evidence-zip-runtime-v3']), 'release_gate_contract');
$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.530.js') !== false, 'offline_runtime_cache_version');

if (!defined('ABSPATH')) define('ABSPATH', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($name) { return preg_replace('/[^A-Za-z0-9._-]+/', '-', basename((string)$name)); }
}
require_once $root . '/src/Rest/StatsController.php';
$tmpDir = sys_get_temp_dir() . '/ff-zip-v3-' . bin2hex(random_bytes(4));
@mkdir($tmpDir, 0777, true);
$one = $tmpDir . '/one.jpg';
$two = $tmpDir . '/two.png';
file_put_contents($one, str_repeat("FIELD-FLOW-JPEG\n", 64));
file_put_contents($two, str_repeat("FIELD-FLOW-PNG\n", 73));
$zipPath = $tmpDir . '/evidence.zip';
try {
    $ref = new ReflectionClass('RoutesPro\\Rest\\StatsController');
    $obj = $ref->newInstanceWithoutConstructor();
    $writer = $ref->getMethod('write_analytics_evidence_zip_portable');
    $writer->setAccessible(true);
    $result = $writer->invoke($obj, $zipPath, [
        ['path'=>$one,'name'=>'captura-1.jpg','index'=>1],
        ['path'=>$two,'name'=>'captura-2.png','index'=>2],
    ]);
    $assert(is_array($result) && !empty($result['created']) && (int)($result['added'] ?? 0) === 2 && is_file($zipPath), 'portable_writer_executes');
    $validator = $ref->getMethod('validate_analytics_evidence_zip');
    $validator->setAccessible(true);
    $assert((bool)$validator->invoke($obj, $zipPath, 2), 'portable_zip_validates');
    $bytes = is_file($zipPath) ? (string)file_get_contents($zipPath) : '';
    $assert(strpos($bytes, "PK\x03\x04") === 0 && strrpos($bytes, "PK\x05\x06") !== false, 'portable_zip_signatures');
} catch (Throwable $e) {
    $failures[] = 'portable_writer_runtime: ' . $e->getMessage();
}
foreach ([$zipPath,$one,$two] as $file) @unlink($file);
@rmdir($tmpDir);

if ($failures) {
    fwrite(STDERR, "EVIDENCE ZIP RUNTIME V3 CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}
echo "EVIDENCE ZIP RUNTIME V3 CONTRACT OK\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
