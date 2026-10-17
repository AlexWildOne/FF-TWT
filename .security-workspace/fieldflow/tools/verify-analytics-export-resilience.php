<?php
/**
 * Build-time, non-destructive verifier for FieldFlow 2.2.473 Analytics Export Resilience continuity.
 * Runs without WordPress and complements the in-app Release Gate 1.21.0.
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
$exports = $source('src/Admin/FormSubmissions.php');
$gate = $source('src/Support/ReleaseGate.php');
$fieldflow = $source('fieldflow.php');
$readme = $source('readme.txt');
$phase = $source('src/Cloud/Phase6StableRelease.php');
$responsive = $source('src/Front/ResponsiveAssets.php');
$partMarker = <<<'TXT'
$part_path = $zip_path . '.part';
TXT;
$addFileMarker = <<<'TXT'
$zip->addFile((string)$entry['path'], $archive_name)
TXT;
$readableMarker = <<<'TXT'
!is_readable((string)$resolved['path'])
TXT;

$assert($containsAll($stats, [
    'prepare_analytics_evidence_export_runtime',
    'fieldflow_evidence_export_time_limit',
    'fieldflow_evidence_export_max_files',
    'fieldflow_evidence_export_max_bytes',
]), 'evidence_runtime_budget');

$assert($containsAll($stats, [
    $partMarker,
    'write_analytics_evidence_zip_portable',
    'ZipArchive::CREATE | \\ZipArchive::OVERWRITE',
    $addFileMarker,
    'ZipArchive::CM_STORE',
    'validate_analytics_evidence_zip',
]), 'direct_atomic_zip_pipeline');

$assert(strpos($stats, 'wp_mkdir_p($stage_dir)') !== false && strpos($stats, 'Compatibility fallback') !== false, 'pclzip_staging_is_fallback_only');
$assert(strpos($stats, 'large photo export into an HTTP 500') !== false, 'http500_regression_marker');
$assert($containsAll($stats, [
    '$size === false || (int)$size <= 0',
    $readableMarker,
    'evidence_zip_invalid',
    'evidence_zip_finalize',
]), 'invalid_media_and_partial_zip_guard');
$assert($containsAll($stats, [
    'X-FieldFlow-Download-Integrity: evidence-zip-v2',
    'fread($handle, 1048576)',
    'FieldFlow evidence ZIP stream mismatch',
]), 'chunked_binary_stream');

$assert($containsAll($stats, [
    '$shadow_budget = $export_full ? 0 : 12',
    '$shadow_budget = 8',
    '$shadow_budget--',
]), 'bounded_media_shadow_telemetry');
$assert($containsAll($exports, [
    'Long Analytics exports must not keep the PHP session lock',
    'session_status() === PHP_SESSION_ACTIVE',
    "apply_filters('fieldflow_export_time_limit', 600)",
]), 'dashboard_export_runtime_hardening');

$assert($containsAll($gate, [
    "VERSION = '1.79.0'",
    'checkAnalyticsExportResilienceContract',
    'fieldflow-analytics-export-resilience-v1',
]), 'release_gate_contract');
$assert($containsAll($fieldflow, ['Version: 2.2.530', "FIELDFLOW_VERSION', '2.2.530'"]) && strpos($readme, 'Stable tag: 2.2.530') !== false, 'release_version_alignment');
$assert($containsAll($phase, ["SOURCE_RELEASE = '2.2.529'", "STABLE_RELEASE = '2.2.530'"]), 'phase6_release_continuity');
$assert(strpos($responsive, 'fieldflow-offline-runtime-2.2.530.js') !== false, 'offline_runtime_cache_version');

if ($failures) {
    fwrite(STDERR, "ANALYTICS EXPORT RESILIENCE CONTRACT FAILED\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}

echo "ANALYTICS EXPORT RESILIENCE CONTRACT OK\n";
echo 'Checks: ' . count($passes) . "\n";
foreach ($passes as $pass) echo " - {$pass}\n";
