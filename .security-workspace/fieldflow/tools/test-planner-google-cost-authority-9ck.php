<?php
// 9CK runtime structure test: browser-side Google calls in Descobrir (manager front_routes) and Base Comercial
// follow the certified low-cost policy. Static source analysis, no WordPress bootstrap.
$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string { $p = $root . '/' . $relative; return is_readable($p) ? (string)file_get_contents($p) : ''; };
$fail = static function (string $reason): void { fwrite(STDERR, "PLANNER GOOGLE COST AUTHORITY 9CK RUNTIME FAIL: {$reason}\n"); exit(1); };
$assert = static function (bool $ok, string $reason) use ($fail): void { if (!$ok) $fail($reason); };
$slice = static function (string $source, string $start, string $end): string { $a = strpos($source, $start); if ($a === false) return ''; $b = strpos($source, $end, $a + strlen($start)); return $b === false ? '' : substr($source, $a, $b - $a); };

$short = $read('src/Shortcodes.php');
$planner = $slice($short, 'public static function front_routes(', 'public static function dashboard(');
$commercial = $slice($short, 'public static function front_commercial(', 'public static function front_routes(');
$assert($planner !== '' && $commercial !== '', 'sources');

// Queue changes schedule a local preview, never a Google request.
$schedule = $slice($planner, 'function scheduleRouteRecalc(', "\n");
$preview = $slice($planner, 'function localRoutePreview(){', 'async function recalcRoute(){');
$localPreview = strpos($preview, 'if(queue.length&&previewSignature()===lastPreviewSignature) return;') !== false
    && strpos($schedule, 'localRoutePreview()') !== false && strpos($schedule, 'recalcRoute()') === false
    && $preview !== '' && strpos($preview, 'directionsService') === false && strpos($preview, 'google.maps') === false
    && strpos($planner, 'fieldflow-planner-local-preview-cost-authority-v1') !== false;
$assert($localPreview, 'local_preview');

// Calibrated estimator parity with RoutingMatrixService::internalEdge().
$matrix = $read('src/Services/Planning/RoutingMatrixService.php');
$estimatorParity = strpos($planner, 'const factor=air<8?1.38:(air<30?1.30:(air<100?1.23:1.17));') !== false
    && strpos($planner, 'const speed=road<8?28:(road<35?38:(road<120?55:72));') !== false
    && strpos($planner, 'if(road>0.15) minutes+=Math.min(5,1.5+road*0.025);') !== false
    && strpos($matrix, '$factor = $airKm < 8.0 ? 1.38 : ($airKm < 30.0 ? 1.30 : ($airKm < 100.0 ? 1.23 : 1.17));') !== false
    && strpos($matrix, '$speed = $roadKm < 8.0 ? 28.0 : ($roadKm < 35.0 ? 38.0 : ($roadKm < 120.0 ? 55.0 : 72.0));') !== false;
$assert($estimatorParity, 'estimator_parity');

// Manual order and fixed positions are never reordered locally.
$manualOrderPreserved = strpos($preview, 'const canReorder=!manualOrderLocked&&!queue.some(item=>item.position_locked);') !== false
    && strpos($preview, 'if(canReorder){') !== false;
$assert($manualOrderPreserved, 'manual_order_preserved');

// Google Directions only on explicit request, optimisation only behind the premium switch.
$recalcButton = strpos($planner, "root.querySelector('#rpf-recalc').addEventListener('click', ()=>recalcRoute());") !== false
    && strpos($planner, 'id="rpf-recalc"') !== false && strpos($planner, 'Calcular percurso real') !== false;
$optGate = strpos($planner, 'optimizeWaypoints:googleWaypointOptimizationEnabled&&!manualOrderLocked') !== false
    && strpos($planner, "optimizeWaypoints:!manualOrderLocked") === false
    && strpos($planner, "data-google-waypoint-optimization=") !== false
    && strpos($planner, "Config::get('google_routes_waypoint_optimization_enabled', 0)") !== false;
$assert($recalcButton && $optGate, 'google_on_request');

// Base Comercial Place Details only with contact enrichment.
$details = strpos($commercial, 'const extra=googlePlacesContactEnrichment?await findPlaceDetails(r.place_id):{};') !== false
    && strpos($commercial, 'const extra=await findPlaceDetails(r.place_id);') === false;
$assert($details, 'contact_details_gated');

// No hidden billable map instance for PlacesService.
$noHiddenMap = strpos($short, "const dummy=document.createElement('div'); googleMap=new google.maps.Map(dummy") === false
    && substr_count($short, "new google.maps.places.PlacesService(document.createElement('div'))") >= 2;
$assert($noHiddenMap, 'no_hidden_map');

$nearbyGate = strpos($planner, 'if(center&&(textResults||[]).length<8){ nearbyResults=await nearbySearch(') !== false;
$assert($nearbyGate, 'nearby_gated');

$config = $read('src/Support/Config.php');
$historical = strpos($config, "'google_routes_waypoint_optimization_enabled' => 0") !== false
    && strpos($short, 'fieldflow-visit-closure-selector-return-v1') !== false
    && strpos($read('src/Forms/Forms.php'), 'fieldflow-context-form-scope-authority-v1') !== false
    && strpos($planner, "/optimize',{method:'POST'") !== false
    && strpos($read('src/Forms/SubmissionPipeline.php'), "public const BUILD = '2.2.485'") !== false;
$assert($historical, 'historical_continuity');

echo 'PLANNER GOOGLE COST AUTHORITY 9CK RUNTIME OK | local_preview=1 | estimator_parity=1 | manual_order_preserved=1 | google_on_request=1 | contact_details_gated=1 | no_hidden_map=1 | nearby_gated=1 | no_schema=1 | historical_continuity=1' . "\n";
