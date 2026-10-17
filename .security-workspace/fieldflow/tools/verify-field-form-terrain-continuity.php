<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string)file_get_contents($path) : '';
};
$fail = static function (string $reason): void { fwrite(STDERR, "FAIL: {$reason}\n"); exit(1); };
$plugin = $read('fieldflow.php');
$release = preg_match("/define\\('FIELDFLOW_VERSION',\\s*'([^']+)'\\)/", $plugin, $m) ? (string)$m[1] : '';
if ($release === '' || version_compare($release, '2.2.534', '<')) $fail('release must be >= 2.2.534');
$form = $read('src/Forms/FormRenderer.php');
$draft = $read('src/Forms/DraftService.php');
$schema = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$markers = [
    strpos($form, "RP_TERRAIN_CONTINUITY_CONTRACT='fieldflow-field-form-terrain-continuity-v1'") !== false,
    strpos($form, 'var RP_DRAFT_SERVER_DELAY=900;') !== false,
    strpos($form, 'function draftRecoveryKey()') !== false,
    strpos($form, 'function draftServerBeacon(snapshot)') !== false,
    strpos($form, 'opts.lifecycle&&draftServerBeacon(snapshot)') !== false,
    strpos($form, 'lifecycle:true') !== false,
    strpos($form, "if(String(e.key||'')!=='Enter') return;") !== false,
    strpos($form, 'e.preventDefault();e.stopPropagation();') !== false,
    strpos($form, 'if(currentlyShown===show) return;') !== false,
    strpos($form, 'if(visibleIndexes.indexOf(index)===-1)') !== false,
    strpos($form, "scheduleDraftSave();\n      refreshConditionalFlow();") !== false,
    strpos($form, '_rpPrimaryKey') !== false,
    strpos($draft, "TERRAIN_CONTINUITY_CONTRACT = 'fieldflow-field-form-terrain-continuity-v1'") !== false,
    strpos($draft, 'find_recovery_scope_row') !== false,
    strpos($draft, 'count($rows) !== 1') !== false,
    strpos($draft, "'scope_rebased' => \$scope_rebased") !== false,
    strpos($draft, "'route_id' => \$scope['route_id']") !== false,
    strpos($draft, "'route_stop_id' => \$scope['route_stop_id']") !== false,
    strpos($schema, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false,
    strpos($submission, "public const BUILD = '2.2.485'") !== false,
];
foreach ($markers as $i => $ok) if (!$ok) $fail('contract marker #' . ($i + 1));
echo 'FIELD FORM TERRAIN CONTINUITY CONTRACT OK | release=' . $release . ' | successor=>=2.2.534 | draft_server_ms=900 | schema=2.2.529 | submission_build=2.2.485' . PHP_EOL;
