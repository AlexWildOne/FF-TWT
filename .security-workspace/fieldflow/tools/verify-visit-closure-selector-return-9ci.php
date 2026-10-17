<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => [
        'Version: 2.2.606',
        "define('FIELDFLOW_VERSION', '2.2.606')",
        "9CI predecessor marker: define('FIELDFLOW_VERSION', '2.2.605')",
        'fieldflow-legacy-pdv-autocomplete-retirement-v1',
        "apply_filters('fieldflow_legacy_pdv_autocomplete_enabled', false)",
    ],
    'src/Shortcodes.php' => [
        'fieldflow-visit-closure-selector-return-v1',
        'id="rpf-closure"',
        'async function returnToStoreSelector(closedContext,state)',
        'async function refreshVisitsAfterClosure(date)',
        "if(preferred.selection==='none')",
        'data-rpf-next-stop',
        'Próxima loja',
        'const scrollToStoreSelector=()=>',
        "await returnToStoreSelector(completedContext,editingExisting?'updated':'done');",
        "await returnToStoreSelector(closureContextFromSelection(),'offline');",
        "await returnToStoreSelector(failureClosedContext,'failed_offline');",
        "await returnToStoreSelector(failureClosedContext,'failed');",
        "await returnToStoreSelector(fallbackClosedContext,'done');",
        'Queres mesmo concluir esta visita?',
        'fieldflow-route-first-paint-v1',
        'Promise.resolve().then(()=>loadRoutesForDay(inpDate.value))',
        "source:'visit-closure-return'",
        '.rp-report-focus.is-closure-return #rpf-submit',
    ],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.605'", "public const STABLE_RELEASE = '2.2.606'", "// public const SOURCE_RELEASE = '2.2.604'", "// public const STABLE_RELEASE = '2.2.605'"],
    'src/Support/ReleaseGate.php' => ['VISIT_CLOSURE_SELECTOR_RETURN_9CI_CONTRACT', 'checkVisitClosureSelectorReturn9CIContract'],
    'src/Support/ReleasePackageValidator.php' => ['visit_closure_selector_return_9ci_ok', 'Visit Closure Selector Return & Route First Paint 9CI'],
    'src/Admin/ReleaseManager.php' => ["packageResult['visit_closure_selector_return_9ci_ok']", 'FECHO → SELETOR OK'],
    'PHASE-9CI-VISIT-CLOSURE-SELECTOR-RETURN.md' => [
        'fieldflow-visit-closure-selector-return-v1',
        '`SOURCE_RELEASE` is `2.2.605` and `STABLE_RELEASE` is `2.2.606`',
        'returns to the store selector',
        'No database schema changes',
        'No FlutterFlow source modification is required',
    ],
];
$forbidden = [
    'src/Shortcodes.php' => [
        'await loadVisits(completedContext.date,{stop_id:completedContext.stop_id,force:true})',
        'loadVisits(currentDate,{stop_id:parseInt(els.stop.value||0,10),force:true})',
        'loadGoogle(()=>{ loadRoutesForDay(inpDate.value); });',
    ],
];
foreach ($need as $relative => $markers) {
    $path = $root . '/' . $relative;
    $raw = is_readable($path) ? (string)file_get_contents($path) : '';
    if ($raw === '') { $errors[] = $relative . ':file'; continue; }
    foreach ($markers as $marker) if (strpos($raw, $marker) === false) $errors[] = $relative . ':' . $marker;
}
foreach ($forbidden as $relative => $markers) {
    $raw = (string)@file_get_contents($root . '/' . $relative);
    foreach ($markers as $marker) if (strpos($raw, $marker) !== false) $errors[] = $relative . ':forbidden:' . $marker;
}
if ($errors) {
    fwrite(STDERR, "VISIT CLOSURE SELECTOR RETURN 9CI CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "VISIT CLOSURE SELECTOR RETURN 9CI CONTRACT OK successor=>=2.2.606\n";
