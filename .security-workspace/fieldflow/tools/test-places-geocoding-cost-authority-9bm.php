<?php
namespace {
    define('ABSPATH', __DIR__ . '/');
    define('DAY_IN_SECONDS', 86400);
    function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
    function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; }
    function wp_strip_all_tags($v){ return strip_tags((string)$v); }
    function remove_accents($v){ return strtr((string)$v,['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c']); }
    function current_time($type,$gmt=false){ return $type==='mysql' ? '2026-10-02 01:00:00' : '2026-10-02'; }
}
namespace RoutesPro\Support {
    final class Config {
        public static function get(?string $key=null,$default=null){
            $values=['places_cost_authority_enabled'=>1,'google_places_contact_enrichment_enabled'=>0];
            return $key===null?$values:($values[$key]??$default);
        }
    }
}
namespace RoutesPro\Observability {
    final class ApiUsageLedger {
        public static array $rows=[];
        public static function record(string $provider,string $operation,array $data=[]):int { self::$rows[]=[$provider,$operation,$data]; return count(self::$rows); }
    }
}
namespace RoutesPro\Services {
    final class RoutingGeographyService {
        public static function normalizeCountryCode(string $v,string $fallback='PT'):string { $v=strtoupper(trim($v)); return $v!==''?$v:$fallback; }
        public static function coordinatesLikelyInCountry(float $lat,float $lng,string $country):bool {
            $country=strtoupper($country);
            if($country==='PT') return $lat>=36.8 && $lat<=42.3 && $lng>=-9.7 && $lng<=-6.0;
            if($country==='ES') return $lat>=35.5 && $lat<=44.2 && $lng>=-10.0 && $lng<=4.5;
            return true;
        }
        public static function resultMatchesCountry(array $result,string $country):bool { return true; }
        public static function geocodeOptions(string $country):array { return ['country_code'=>$country]; }
    }
}
namespace {
    require_once dirname(__DIR__).'/src/Services/PlacesCostAuthority.php';
    require_once dirname(__DIR__).'/src/Services/RouteEndpointResolverService.php';
    use RoutesPro\Services\PlacesCostAuthority;
    use RoutesPro\Services\RouteEndpointResolverService;
    use RoutesPro\Observability\ApiUsageLedger;

    $checks=0;
    $assert=static function(bool $ok,string $name) use (&$checks){ if(!$ok) throw new \RuntimeException('FAIL '.$name); $checks++; echo 'PASS '.$name.PHP_EOL; };

    $address='Rua do Comércio 10, Lisboa';
    $point=[
        'address'=>$address,'lat'=>38.7139,'lng'=>-9.1394,'country_code'=>'PT','place_id'=>'ChIJ-test',
        'endpoint_address_fingerprint'=>PlacesCostAuthority::addressFingerprint($address),
        'endpoint_geocode_verified'=>1,'endpoint_geocode_status'=>'verified','geocode_provider'=>'google_maps',
        'geocode_verified_at'=>'2026-09-30 10:00:00','provenance'=>'route_default',
    ];
    $resolved=RouteEndpointResolverService::resolve($point,'start','route_default',['country_code'=>'PT']);
    $assert(($resolved['endpoint_geocode_status']??'')==='signature_reuse','unchanged_endpoint_reuses_coordinates');
    $assert(abs((float)$resolved['lat']-38.7139)<0.000001 && abs((float)$resolved['lng']+9.1394)<0.000001,'reused_coordinates_unchanged');
    $assert(($resolved['geocode_verified_at']??'')==='2026-09-30 10:00:00','reuse_does_not_fake_reverification_time');
    $assert(count(ApiUsageLedger::$rows)===1 && ApiUsageLedger::$rows[0][1]==='geocode','avoided_geocode_is_metered');

    $changed=$point;
    $changed['address']='Avenida Nova 99, Lisboa';
    $changedResolved=RouteEndpointResolverService::resolve($changed,'start','route_default',['country_code'=>'PT']);
    $assert(($changedResolved['endpoint_geocode_status']??'')!=='signature_reuse','address_change_invalidates_reuse');

    $outside=$point; $outside['lat']=38.9; $outside['lng']=-5.0;
    $assert(!PlacesCostAuthority::canReuseEndpoint($outside,'PT'),'country_mismatch_invalidates_reuse');

    $unverified=$point; unset($unverified['endpoint_geocode_verified'],$unverified['place_id']);
    $assert(!PlacesCostAuthority::canReuseEndpoint($unverified,'PT'),'unverified_endpoint_requires_validation');

    $staleGoogle=$point; $staleGoogle['geocode_verified_at']='2026-08-01 10:00:00';
    $assert(!PlacesCostAuthority::canReuseEndpoint($staleGoogle,'PT'),'stale_google_coordinates_require_refresh');

    $owned=$point; $owned['geocode_provider']='csv'; $owned['geocode_verified_at']='2025-01-01 10:00:00';
    $assert(PlacesCostAuthority::canReuseEndpoint($owned,'PT'),'fieldflow_owned_coordinates_do_not_expire_by_google_window');

    $assert(PlacesCostAuthority::GOOGLE_COORDINATE_CACHE_MAX_DAYS===30,'google_coordinate_window_capped_30_days');
    echo 'PLACES GEOCODING COST AUTHORITY 9BM RUNTIME OK checks='.$checks.PHP_EOL;
}
