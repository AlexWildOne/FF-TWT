<?php
/** Build-time verifier for AI Governance Certification Continuity. */
$root = dirname(__DIR__);
$read = static function(string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$failures = [];
$assert = static function(bool $ok, string $label) use (&$failures): void { if (!$ok) $failures[] = $label; };
$plugin = $read('fieldflow.php');
$readme = $read('readme.txt');
$phase = $read('src/Cloud/Phase6StableRelease.php');
$gate = $read('src/Support/ReleaseGate.php');
$validator = $read('src/Support/ReleasePackageValidator.php');
$usage = $read('src/Repositories/AIUsageRepository.php');
$migrations = $read('src/Support/Migrations.php');
$schema = $read('src/Support/SchemaManifest.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$manager = $read('src/Admin/ReleaseManager.php');
$doc = $read('PHASE-9N-AI-GOVERNANCE-CERTIFICATION-CONTINUITY.md');
$version = '';
if (preg_match("/FIELDFLOW_VERSION['\"]?\\s*,\\s*['\"]([^'\"]+)/", $plugin, $m)) $version = (string)$m[1];
$assert($version !== '' && version_compare($version, '2.2.530', '>=') && strpos($readme, 'Stable tag: ' . $version) !== false, 'release_identity');
$parts = array_map('intval', explode('.', $version));
$source = count($parts) === 3 && $parts[2] > 0 ? $parts[0] . '.' . $parts[1] . '.' . ($parts[2] - 1) : '';
$assert($source !== '' && strpos($phase, "SOURCE_RELEASE = '" . $source . "'") !== false && strpos($phase, "STABLE_RELEASE = '" . $version . "'") !== false, 'phase6_continuity');
$assert(strpos($gate, "AI_GOVERNANCE_CERTIFICATION_CONTINUITY_CONTRACT = 'fieldflow-ai-governance-certification-continuity-v1'") !== false, 'release_gate_contract');
$assert(strpos($gate, 'checkAiGovernanceCertificationContinuityContract') !== false, 'release_gate_check');
$assert(strpos($usage, 'monthBreakdown') !== false && strpos($usage, 'monthCost(array $scope = [])') !== false && strpos($usage, 'hasAttributionColumns') !== false, 'repository_runtime_contract');
$assert(strpos($migrations, 'client_created_idx') !== false && strpos($migrations, 'project_created_idx') !== false && strpos($migrations, 'feature_created_idx') !== false && strpos($migrations, 'request_kind_idx') !== false, 'migration_index_ownership');
$assert(strpos($schema, "'client_created_idx'") !== false && strpos($schema, "'project_created_idx'") !== false && strpos($schema, "'feature_created_idx'") !== false && strpos($schema, "'request_kind_idx'") !== false, 'schema_manifest_index_ownership');
if (preg_match("/'src\\/Repositories\\/AIUsageRepository\\.php'\\s*=>\\s*\\[(.*?)\\],\\s*'src\\/Services\\/AI\\.php'/s", $gate, $match)) {
    $repoGate = (string)$match[1];
    foreach (['client_created_idx','project_created_idx','feature_created_idx','request_kind_idx'] as $indexMarker) {
        $assert(strpos($repoGate, $indexMarker) === false, 'repository_gate_misplaced_' . $indexMarker);
    }
} else {
    $assert(false, 'repository_gate_scope');
}
$assert(strpos($validator, "version_compare((string)(\$result['version'] ?? ''), '2.2.530', '>=')") !== false, 'successor_validation');
$assert(strpos($validator, "'ai_governance_certification_continuity_ok'=>false") !== false, 'validator_result');
$assert(strpos($migrations, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false, 'schema_unchanged');
$assert(strpos($submission, "public const BUILD = '2.2.485'") !== false, 'submission_build_preserved');
$assert(strpos($manager, 'AI Certification Continuity') !== false, 'release_manager_visibility');
$assert(strpos($doc, 'fieldflow-ai-governance-certification-continuity-v1') !== false && strpos($doc, 'No new DDL') !== false, 'phase_documentation');
if ($failures) {
    fwrite(STDERR, "AI GOVERNANCE CERTIFICATION CONTINUITY CONTRACT FAILED\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}
echo "AI GOVERNANCE CERTIFICATION CONTINUITY CONTRACT OK\n";
echo "release={$version}\n";
echo "contract=fieldflow-ai-governance-certification-continuity-v1\n";
echo "repository_ownership=runtime_queries_only\n";
echo "index_ownership=migrations_schema_manifest\n";
echo "successor=>=2.2.530\n";
echo "schema=2.2.529\n";
echo "submission_build=2.2.485\n";
