<?php
$root = dirname(__DIR__);
$errors = [];
$need = [
    'fieldflow.php' => ['Version: 2.2.608', "define('FIELDFLOW_VERSION', '2.2.608')", "9CK predecessor marker: define('FIELDFLOW_VERSION', '2.2.607')"],
    'src/Shortcodes.php' => [
        'fieldflow-planner-local-preview-cost-authority-v1',
        'function localRoutePreview(){',
        'function internalRoadEdge(a,b){',
        'routeCalcTimer=setTimeout(()=>localRoutePreview(),Math.max(0,delay));',
        'optimizeWaypoints:googleWaypointOptimizationEnabled&&!manualOrderLocked',
        'Calcular percurso real',
        'const extra=googlePlacesContactEnrichment?await findPlaceDetails(r.place_id):{};',
        "new google.maps.places.PlacesService(document.createElement('div'))",
        'wp_json_encode($endpoint_profiles ?? [])',
    ],
    'src/Cloud/Phase6StableRelease.php' => ["public const SOURCE_RELEASE = '2.2.607'", "public const STABLE_RELEASE = '2.2.608'", "// public const SOURCE_RELEASE = '2.2.606'", "// public const STABLE_RELEASE = '2.2.607'"],
    'src/Support/ReleaseGate.php' => ['PLANNER_GOOGLE_COST_AUTHORITY_9CK_CONTRACT', 'checkPlannerGoogleCostAuthority9CKContract'],
    'src/Support/ReleasePackageValidator.php' => ['planner_google_cost_authority_9ck_ok', 'Planner & Commercial Google Cost Authority 9CK'],
    'src/Admin/ReleaseManager.php' => ["packageResult['planner_google_cost_authority_9ck_ok']", 'GOOGLE SÓ A PEDIDO OK'],
    'PHASE-9CK-PLANNER-GOOGLE-COST-AUTHORITY.md' => ['fieldflow-planner-google-cost-authority-v1', '`SOURCE_RELEASE` is `2.2.607` and `STABLE_RELEASE` is `2.2.608`', 'zero-cost local preview', 'No database schema changes', 'No FlutterFlow source modification is required'],
];
$forbidden = [
    'src/Shortcodes.php' => [
        'routeCalcTimer=setTimeout(()=>recalcRoute(),Math.max(0,delay));',
        "optimizeWaypoints:!manualOrderLocked&&!queue.some(item=>item.position_locked)",
        'const extra=await findPlaceDetails(r.place_id);',
        "const dummy=document.createElement('div'); googleMap=new google.maps.Map(dummy",
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
    fwrite(STDERR, "PLANNER GOOGLE COST AUTHORITY 9CK CONTRACT FAIL\n" . implode("\n", $errors) . "\n");
    exit(1);
}
echo "PLANNER GOOGLE COST AUTHORITY 9CK CONTRACT OK successor=>=2.2.608\n";
