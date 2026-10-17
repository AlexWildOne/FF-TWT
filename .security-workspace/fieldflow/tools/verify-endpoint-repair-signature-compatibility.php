<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
require_once $root . '/src/Services/RouteMetricsService.php';
$read = static function(string $rel) use ($root): string {
    $p = $root . '/' . ltrim($rel, '/');
    return is_readable($p) ? (string)file_get_contents($p) : '';
};
$assert = static function(bool $ok, string $name): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$name}\n"); exit(1); }
};
$legacy = new ReflectionMethod(\RoutesPro\Services\RouteMetricsService::class, 'repairPlannedRouteIntegrity');
$legacyParams = $legacy->getParameters();
$legacyNames = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $legacyParams);
$assert(count($legacyParams) === 5 && $legacy->getNumberOfRequiredParameters() === 1, 'legacy_five_parameter_signature');
$assert($legacyNames === ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 'legacy_parameter_order');
$migration = new ReflectionMethod(\RoutesPro\Services\RouteMetricsService::class, 'repairPlannedRouteIntegrityWithOwnerProfile');
$migrationParams = $migration->getParameters();
$migrationNames = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $migrationParams);
$assert(count($migrationParams) === 5 && $migration->getNumberOfRequiredParameters() === 1, 'dedicated_owner_profile_migration_entrypoint');
$assert($migrationNames === ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 'migration_parameter_order');
$metrics = $read('src/Services/RouteMetricsService.php');
$assignments = $read('src/Admin/Assignments.php');
$doc = $read('PHASE-8ZA-ENDPOINT-REPAIR-SIGNATURE-COMPATIBILITY.md');
$submission = $read('src/Forms/SubmissionPipeline.php');
$assert(strpos($metrics, 'repairPlannedRouteIntegrityInternal') !== false, 'shared_internal_implementation');
$assert(strpos($metrics, 'Historical 2.2.511 package compatibility marker only: bool $forceOwnerProfile = false') !== false, 'legacy_package_marker');
$assert(strpos($assignments, 'repairPlannedRouteIntegrityWithOwnerProfile') !== false, 'admin_migration_dispatch');
$assert(strpos($doc, 'fieldflow-endpoint-repair-signature-compatibility-v1') !== false, 'phase_contract');
$assert(strpos($doc, '`SOURCE_RELEASE` is `2.2.511` and `STABLE_RELEASE` is `2.2.512`') !== false, 'phase_release_continuity');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
echo "ENDPOINT REPAIR SIGNATURE COMPATIBILITY CONTRACT OK\n";
