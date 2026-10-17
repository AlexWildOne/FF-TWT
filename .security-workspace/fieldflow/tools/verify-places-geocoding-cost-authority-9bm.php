<?php
$root=dirname(__DIR__);
$requirements=[
    'src/Services/PlacesCostAuthority.php'=>['CONTRACT = \'fieldflow-places-geocoding-cost-authority-v1\'','canReuseEndpoint','findReusableLocation','GOOGLE_COORDINATE_CACHE_MAX_DAYS = 30','googleCoordinateWindowValid','recordAvoided'],
    'src/Services/RouteEndpointResolverService.php'=>['PlacesCostAuthority::canReuseEndpoint','endpoint_signature_reuse','force_external_geocode'],
    'src/Services/Maps.php'=>['google_geocode_v3','30 * DAY_IN_SECONDS'],
    'src/Rest/IntegrationsController.php'=>['known_location_reuse','PlacesCostAuthority::findReusableLocation','routespro_geocode_v3_'],
    'src/Support/Config.php'=>["'places_cost_authority_enabled' => 1","'google_places_contact_enrichment_enabled' => 0"],
    'src/Support/Plugin.php'=>['applyPlacesGeocodingCostAuthority','fieldflow_places_geocoding_cost_authority_migrated','fieldflow-places-geocoding-cost-authority-v1'],
    'src/Admin/Settings.php'=>['Places &amp; Geocoding low cost','places_cost_authority_enabled','google_places_contact_enrichment_enabled'],
    'src/Admin/Routes.php'=>['googlePlacesContactEnrichment','if (!googlePlacesContactEnrichment) { cb(null); return; }'],
    'src/Shortcodes.php'=>['googlePlacesContactEnrichment','addressFields.push(\'website\',\'formatted_phone_number\')'],
    'src/Support/ReleaseGate.php'=>['PLACES_GEOCODING_COST_AUTHORITY_9BM_CONTRACT','checkPlacesGeocodingCostAuthority9BMContract'],
    'src/Support/ReleasePackageValidator.php'=>['places_geocoding_cost_authority_9bm_ok'],
    'src/Cloud/Phase6StableRelease.php'=>["public const SOURCE_RELEASE = '2.2.583'","public const STABLE_RELEASE = '2.2.584'"],
    'PHASE-9BM-PLACES-GEOCODING-COST-AUTHORITY.md'=>['fieldflow-places-geocoding-cost-authority-v1'],
];
$missing=[];
foreach($requirements as $file=>$markers){
    $raw=(string)@file_get_contents($root.'/'.$file);
    foreach($markers as $marker) if(strpos($raw,$marker)===false) $missing[]=$file.':'.$marker;
}
if($missing){fwrite(STDERR,implode(PHP_EOL,$missing).PHP_EOL);exit(1);}
echo "PLACES GEOCODING COST AUTHORITY 9BM CONTRACT OK successor=>=2.2.584\n";
