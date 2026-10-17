<?php
// PHASE 4E 9DJ PASS
// Pure contract regression; no WordPress boot, DB, network or runtime writes.
$root = dirname(__DIR__);
$source = static fn(string $relative): string => (string)file_get_contents($root . '/' . $relative);
$assert = static function(bool $ready, string $name): void {
    if (!$ready) { fwrite(STDERR, "PHASE 4E 9DJ FAIL: {$name}\n"); exit(1); }
};
$phase = $source('src/Cloud/Phase6StableRelease.php');
$tenants = $source('src/Admin/LicenseTenants.php');
$screen = $source('src/Admin/SecurityObservability.php');
$css = $source('assets/fieldflow-admin-components.css');
$assert(strpos($phase, 'bool $forceFreshCertification = true') !== false,
    'fresh scan stays default for certification');
$assert((bool)preg_match('/if \(\$forceFreshCertification\)\s*\{(?<fresh>.*?)\}\s*else\s*\{(?<readonly>.*?)\}/s', $phase, $parts),
    'explicit fresh vs read-only code paths');
$assert(strpos($parts['fresh'], 'ProductionManifest::invalidateCache()') !== false
    && strpos($parts['fresh'], 'ProductionManifest::verify(true)') !== false
    && strpos($parts['fresh'], 'resetHealthCache()') !== false,
    'forced certification path invalidates manifest and resets health caches');
$assert(strpos($parts['readonly'], 'ProductionManifest::verify();') !== false
    && strpos($parts['readonly'], 'invalidateCache(') === false
    && strpos($parts['readonly'], 'resetHealthCache(') === false,
    'read-only view reuses existing snapshot without invalidation');
$assert(strpos($tenants, 'Phase6StableRelease::localProbe(false,') !== false
    && strpos($tenants, "strtoupper((string)(\$_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET'") !== false,
    'GET rendering read-only, POST always fresh');
$assert(strpos($tenants, 'Phase6StableRelease::localProbe(true)') !== false,
    'explicit Phase 6 test remains fresh');
$assert(strpos($screen, 'ff-ops-performance-9dj') !== false
    && strpos($screen, 'ff-ops-nav-9dj') !== false
    && substr_count($screen, 'ff-ops-responsive-table-9dj') >= 5,
    'performance-only table and navigation scopes');
$assert(substr_count($screen, 'data-label=') >= 25,
    'all key performance mobile table rows carry explicit labels');
$assert(strpos($css, 'content:attr(data-label)') !== false
    && strpos($css, 'grid-template-columns:repeat(auto-fit,minmax(min(100%,250px),1fr))') !== false
    && strpos($css, '.ff-ops-process-grid-9dj') !== false,
    'mobile cards and adaptive 4 KPI columns are scoped');
$assert(strpos($screen, 'n/d (sem tarifas)') !== false
    && strpos($screen, '$unpricedApiRequests > 0') !== false
    && strpos($screen, 'Gate de performance') !== false,
    'unknown API costs are not represented as free and observational gate is labelled');
$assert(strpos($screen, 'fieldflow_load_readiness_save') !== false
    && strpos($screen, 'fieldflow_load_readiness_probe') !== false
    && strpos($screen, 'fieldflow_load_readiness[thresholds]') !== false,
    'save names, nonces and limits were preserved');
$assert(strpos($screen, 'LoadReadiness::gate((int)$settings[\'gate_window_hours\'])') !== false,
    'no changes to Load Readiness thresholds or gate logic');
$assert(strpos($css, 'body.ff-fieldflow-admin .ff-ops-performance-9dj') !== false,
    'visual changes cannot leak to operational front');
echo "PHASE 4E 9DJ PASS | read-only GET / fresh POST, mobile table labels, cost uncertainty, scope\n";
