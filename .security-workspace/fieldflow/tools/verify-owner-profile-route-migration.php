<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__);
$checks = [];
$read = static function(string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_readable($p) ? (string)file_get_contents($p) : '';
};
$assert = static function(bool $ok, string $name) use (&$checks): void { $checks[$name] = $ok; };
if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
require_once $root . '/src/Services/RouteMetricsService.php';
$metrics = $read('src/Services/RouteMetricsService.php');
$assignments = $read('src/Admin/Assignments.php');
$phase = $read('PHASE-8Z-OWNER-PROFILE-ROUTE-MIGRATION.md');
$submission = $read('src/Forms/SubmissionPipeline.php');
$legacy = new ReflectionMethod(\RoutesPro\Services\RouteMetricsService::class, 'repairPlannedRouteIntegrity');
$legacyParams = $legacy->getParameters();
$legacyNames = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $legacyParams);
$assert(count($legacyParams) === 5 && $legacyNames === ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 'legacy_repair_signature_preserved');
$migration = new ReflectionMethod(\RoutesPro\Services\RouteMetricsService::class, 'repairPlannedRouteIntegrityWithOwnerProfile');
$migrationParams = $migration->getParameters();
$migrationNames = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $migrationParams);
$assert(count($migrationParams) === 5 && $migrationNames === ['projectId','ownerUserId','dateFrom','dateTo','actorUserId'], 'dedicated_owner_profile_entrypoint');
$assert(strpos($metrics, '$forceProfileForRoute') !== false, 'owner_scoped_force_gate');
$assert(strpos($metrics, 'operator_profile_forced') !== false, 'forced_profile_provenance');
$assert(strpos($metrics, '!$forceProfileForRoute') !== false, 'complete_endpoint_short_circuit_bypass');
$assert(strpos($metrics, 'route_endpoint_forced_migration_at') !== false, 'migration_audit_marker');
$assert(strpos($assignments, 'force_owner_profile') !== false, 'admin_force_control');
$assert(strpos($assignments, 'repairPlannedRouteIntegrityWithOwnerProfile') !== false, 'dedicated_admin_dispatch');
$assert(strpos($assignments, 'Aplicar base do operativo e reconciliar km') !== false, 'admin_action_label');
$assert(strpos($phase, 'fieldflow-owner-profile-route-migration-v1') !== false, 'phase_contract');
$assert(strpos($phase, '`SOURCE_RELEASE` is `2.2.510` and `STABLE_RELEASE` is `2.2.511`') !== false, 'release_continuity_doc');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$failed = array_keys(array_filter($checks, static fn($v) => !$v));
if ($failed) { fwrite(STDERR, 'OWNER PROFILE ROUTE MIGRATION CONTRACT FAIL: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo 'OWNER PROFILE ROUTE MIGRATION CONTRACT OK' . PHP_EOL;
