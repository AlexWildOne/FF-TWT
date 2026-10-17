<?php
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_readable($path) ? (string) file_get_contents($path) : '';
};
$plugin = $read('fieldflow.php');
$release = preg_match("/define\\('FIELDFLOW_VERSION',\\s*'([^']+)'\\)/", $plugin, $m) ? (string)$m[1] : '';
$fail = static function (string $reason): void { fwrite(STDERR, "FAIL: {$reason}\n"); exit(1); };
if ($release === '' || version_compare($release, '2.2.533', '<')) $fail('release must be >= 2.2.533');
$foundation = $read('src/Front/ReactFrontFoundation.php');
$source = $read('frontend/src/react-front/module-controls.ts');
$academy = $read('src/Front/PerformanceShortcodeRenderer.php');
$module = $read('src/Performance/Module.php');
$expenses = $read('src/Front/ExpensesShortcodeRenderer.php');
$analytics = $read('assets/fieldflow-premium-analytics.js');
$shortcodes = $read('src/Shortcodes.php');
$app = $read('src/Front/AppShortcodeRenderer.php');
$css = $read('assets/fieldflow-premium-shell-v2.css');
$schema = $read('src/Support/Migrations.php');
$submission = $read('src/Forms/SubmissionPipeline.php');
$checks = [
    strpos($foundation, "OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1'") !== false,
    strpos($source, 'fieldflow-operational-modernization-wave2-v1') !== false,
    strpos($source, 'react-event-shell-9q') !== false,
    strpos($source, "['academy', 'expenses'].includes(props.module)") !== false,
    strpos($academy, "operationalModuleMount(\$id, 'academy'") !== false,
    strpos($academy, 'academy_front_state') !== false,
    strpos($academy, '$wpdb->get_var') === false,
    strpos($module, "ACADEMY_FRONT_BATCH_CONTRACT = 'fieldflow-academy-front-batch-v2'") !== false,
    strpos($module, 'function academy_front_state') !== false,
    strpos($expenses, "module:'expenses'") !== false,
    strpos($expenses, "root.addEventListener('routespro:panel-open'") !== false,
    strpos($analytics, 'new MutationObserver') === false,
    strpos($analytics, 'fieldflow:analytics-rendered') !== false,
    strpos($shortcodes, 'fieldflow:analytics-rendered') !== false,
    strpos($app, '.ffp-academy') !== false,
    strpos($css, 'fieldflow-operational-modernization-wave2-v1') !== false,
    strpos($schema, "LATEST_SCHEMA_VERSION = '2.2.529'") !== false,
    strpos($submission, "public const BUILD = '2.2.485'") !== false,
];
foreach ($checks as $i => $ok) if (!$ok) $fail('contract marker #' . ($i + 1));
echo 'OPERATIONAL MODERNIZATION WAVE 2 CONTRACT OK | release=' . $release . ' | successor=>=2.2.533 | modules=academy,expenses,analytics | schema=2.2.529 | submission_build=2.2.485' . PHP_EOL;
