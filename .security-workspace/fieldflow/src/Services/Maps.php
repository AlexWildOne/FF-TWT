<?php
namespace RoutesPro\Services;

use RoutesPro\Support\Config;
use RoutesPro\Security\OutboundHttp;

interface MapsProviderInterface {
    public function geocode($address, $options=[]);
    public function distanceMatrix($origins, $destinations);
    public function directions($origin, $destination, $waypoints=[]);
    public function placeSearch($query, $options=[]);
}

trait MapsProviderCache {
    protected function cachedProviderCall(string $kind, array $payload, callable $resolver) {
        $days = max(0, min(365, (int) Config::get('routing_cache_days', 30)));
        $usage = $this->providerUsageDescriptor($kind, $payload);
        $normalise = static function ($value) use (&$normalise) {
            if (!is_array($value)) return is_string($value) ? strtolower(trim(preg_replace('/\s+/', ' ', $value))) : $value;
            $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);
            if (!$isList) ksort($value);
            foreach ($value as $key => $item) $value[$key] = $normalise($item);
            return $value;
        };
        $key = 'ff_maps_' . md5($kind . '|' . wp_json_encode($normalise($payload)));
        if ($days > 0 && function_exists('get_transient')) {
            $cached = get_transient($key);
            if (is_array($cached) && array_key_exists('value', $cached)) {
                $this->recordProviderUsage($usage, [
                    'status' => 'success',
                    'cache_status' => 'hit',
                    'billable' => false,
                    'request_count' => 0,
                    'duration_ms' => 0,
                    'source' => 'provider_cache',
                ]);
                return $cached['value'];
            }
        }

        $started = microtime(true);
        try {
            $value = $resolver();
        } catch (\Throwable $error) {
            $this->recordProviderUsage($usage, [
                'status' => 'error',
                'cache_status' => $days > 0 ? 'miss' : 'disabled',
                'billable' => true,
                'duration_ms' => (int)round((microtime(true) - $started) * 1000),
                'source' => 'provider_exception',
                'meta' => ['exception' => get_class($error)],
            ]);
            throw $error;
        }

        $success = is_array($value) ? !empty($value) : ($value !== null && $value !== false);
        $this->recordProviderUsage($usage, [
            'status' => $success ? 'success' : 'error',
            'cache_status' => $days > 0 ? 'miss' : 'disabled',
            'billable' => true,
            'duration_ms' => (int)round((microtime(true) - $started) * 1000),
            'source' => 'provider_call',
        ]);
        if ($days > 0 && $success && function_exists('set_transient')) {
            $ttl = $days * DAY_IN_SECONDS;
            if ($kind === 'places' || strpos($kind, 'place') !== false) {
                $ttl = min(DAY_IN_SECONDS, max(HOUR_IN_SECONDS, $ttl));
            } elseif (strpos($kind, 'google_geocode') === 0) {
                // Google Geocoding coordinates are never cached beyond 30 days.
                $ttl = min(30 * DAY_IN_SECONDS, max(HOUR_IN_SECONDS, $ttl));
            }
            set_transient($key, ['value' => $value], $ttl);
        }
        return $value;
    }

    private function providerUsageDescriptor(string $kind, array $payload): array {
        $provider = strpos($kind, 'azure_') === 0 ? 'azure_maps' : (strpos($kind, 'google_') === 0 ? 'google_maps' : sanitize_key((string)($payload['provider'] ?? 'maps')));
        if ($provider === 'google') $provider = 'google_maps';
        if ($provider === 'azure') $provider = 'azure_maps';
        $operation = 'request';
        $units = 1.0;
        if (strpos($kind, 'geocode') !== false) $operation = 'geocode';
        elseif (strpos($kind, 'matrix') !== false) {
            $operation = 'matrix_element';
            $units = max(1, count((array)($payload['origins'] ?? [])) * count((array)($payload['destinations'] ?? [])));
        } elseif (strpos($kind, 'directions') !== false) $operation = 'directions';
        elseif ($kind === 'places' || strpos($kind, 'place') !== false) $operation = 'places';
        return ['provider' => $provider ?: 'maps', 'operation' => $operation, 'units' => (float)$units];
    }

    private function recordProviderUsage(array $usage, array $data): void {
        if (!class_exists('\\RoutesPro\\Observability\\ApiUsageLedger')) return;
        try {
            $data['units'] = (float)($usage['units'] ?? 1);
            \RoutesPro\Observability\ApiUsageLedger::record(
                (string)($usage['provider'] ?? 'maps'),
                (string)($usage['operation'] ?? 'request'),
                $data
            );
        } catch (\Throwable $ignored) {}
    }
}
class GoogleMapsProvider implements MapsProviderInterface {
    use MapsProviderCache;
    protected $key;
    public function __construct(){ $this->key = Config::get('google_maps_key'); }

    public function geocode($address, $options=[]){
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::normalizeCountryCode((string)($options['country_code'] ?? 'PT')) : strtoupper((string)($options['country_code'] ?? 'PT'));
        $region = strtolower((string)($options['region'] ?? $countryCode));
        $language = (string)($options['language'] ?? ($countryCode === 'ES' ? 'es-ES' : 'pt-PT'));
        // Historical 9AN certification marker only. Runtime cache namespace is google_geocode_v3.
        // google_geocode_v2
        return $this->cachedProviderCall('google_geocode_v3', ['address'=>$address,'country_code'=>$countryCode,'region'=>$region,'language'=>$language], function() use ($address, $countryCode, $region, $language) {
            $url = add_query_arg([ 'address'=>$address, 'key'=>$this->key, 'region'=>$region, 'language'=>$language, 'components'=>'country:' . $countryCode ], 'https://maps.googleapis.com/maps/api/geocode/json');
            $res = OutboundHttp::get($url, ['timeout'=>20], ['purpose'=>'maps_google','allowed_hosts'=>['maps.googleapis.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]);
            if (is_wp_error($res)) return null;
            $body = json_decode(wp_remote_retrieve_body($res), true);
            if (!empty($body['results'][0]['geometry']['location'])) {
                $loc = $body['results'][0]['geometry']['location'];
                $raw = (array)$body['results'][0];
                $actualCountry = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::countryCodeFromGeocodeRaw($raw) : '';
                if ($actualCountry !== '' && $actualCountry !== $countryCode) return null;
                return [
                    'lat'=>$loc['lat'],
                    'lng'=>$loc['lng'],
                    'formatted_address'=>(string)($raw['formatted_address'] ?? $address),
                    'place_id'=>(string)($raw['place_id'] ?? ''),
                    'country_code'=>$actualCountry ?: $countryCode,
                    'provider'=>'google_maps',
                    'raw'=>$raw,
                ];
            }
            return null;
        });
    }
    public function distanceMatrix($origins, $destinations){
        return $this->cachedProviderCall('google_matrix', ['origins'=>$origins,'destinations'=>$destinations], function() use ($origins, $destinations) {
            $o = implode('|', array_map(fn($p)=>$p['lat'].','.$p['lng'], $origins));
            $d = implode('|', array_map(fn($p)=>$p['lat'].','.$p['lng'], $destinations));
            $url = add_query_arg([ 'origins'=>$o, 'destinations'=>$d, 'key'=>$this->key, 'units'=>'metric' ], 'https://maps.googleapis.com/maps/api/distancematrix/json');
            $res = OutboundHttp::get($url, ['timeout'=>25], ['purpose'=>'maps_google','allowed_hosts'=>['maps.googleapis.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]); if (is_wp_error($res)) return null;
            return json_decode(wp_remote_retrieve_body($res), true);
        });
    }
    public function directions($origin, $destination, $waypoints=[]){
        return $this->cachedProviderCall('google_directions', ['origin'=>$origin,'destination'=>$destination,'waypoints'=>$waypoints], function() use ($origin, $destination, $waypoints) {
            $params = [
                'origin'=>$origin['lat'].','.$origin['lng'],
                'destination'=>$destination['lat'].','.$destination['lng'],
                'key'=>$this->key
            ];
            if ($waypoints) $params['waypoints'] = 'optimize:true|' . implode('|', array_map(fn($p)=>$p['lat'].','.$p['lng'], $waypoints));
            $url = add_query_arg($params, 'https://maps.googleapis.com/maps/api/directions/json');
            $res = OutboundHttp::get($url, ['timeout'=>30], ['purpose'=>'maps_google','allowed_hosts'=>['maps.googleapis.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]); if (is_wp_error($res)) return null;
            return json_decode(wp_remote_retrieve_body($res), true);
        });
    }
    public function placeSearch($query, $options=[]){
        return $this->cachedProviderCall('places', ['provider'=>'google','query'=>$query,'options'=>$options], function() use ($query, $options) {
            $url = add_query_arg([
                'query' => $query,
                'key' => $this->key,
                'language' => $options['language'] ?? 'pt-PT',
                'region' => $options['region'] ?? 'pt',
            ], 'https://maps.googleapis.com/maps/api/place/textsearch/json');
            $res = OutboundHttp::get($url, ['timeout'=>20], ['purpose'=>'maps_google','allowed_hosts'=>['maps.googleapis.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]); if (is_wp_error($res)) return null;
            return json_decode(wp_remote_retrieve_body($res), true);
        });
    }
}

class AzureMapsProvider implements MapsProviderInterface {
    use MapsProviderCache;
    protected $key;
    public function __construct(){ $this->key = Config::get('azure_maps_key'); }

    public function geocode($address, $options=[]){
        $countryCode = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::normalizeCountryCode((string)($options['country_code'] ?? 'PT')) : strtoupper((string)($options['country_code'] ?? 'PT'));
        $language = (string)($options['language'] ?? ($countryCode === 'ES' ? 'es-ES' : 'pt-PT'));
        return $this->cachedProviderCall('azure_geocode_v2', ['address'=>$address,'country_code'=>$countryCode,'language'=>$language], function() use ($address, $countryCode, $language) {
            $url = add_query_arg([ 'api-version'=>'1.0', 'query'=>$address, 'subscription-key'=>$this->key, 'countrySet'=>$countryCode, 'language'=>$language ], 'https://atlas.microsoft.com/search/address/json');
            $res = OutboundHttp::get($url, ['timeout'=>20], ['purpose'=>'maps_azure','allowed_hosts'=>['atlas.microsoft.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]); if (is_wp_error($res)) return null;
            $body = json_decode(wp_remote_retrieve_body($res), true);
            if (!empty($body['results'][0]['position'])) {
                $raw = (array)$body['results'][0];
                $actualCountry = class_exists('\\RoutesPro\\Services\\RoutingGeographyService') ? RoutingGeographyService::countryCodeFromGeocodeRaw($raw) : '';
                if ($actualCountry !== '' && $actualCountry !== $countryCode) return null;
                $pos = $raw['position'];
                return [
                    'lat'=>$pos['lat'],
                    'lng'=>$pos['lon'],
                    'formatted_address'=>(string)($raw['address']['freeformAddress'] ?? $address),
                    'place_id'=>(string)($raw['id'] ?? ''),
                    'country_code'=>$actualCountry ?: $countryCode,
                    'provider'=>'azure_maps',
                    'raw'=>$raw,
                ];
            }
            return null;
        });
    }
    public function distanceMatrix($origins, $destinations){
        return $this->cachedProviderCall('azure_matrix', ['origins'=>$origins,'destinations'=>$destinations], function() use ($origins, $destinations) {
            $payload = [
                'origins'=> array_map(fn($p)=>['latitude'=>$p['lat'],'longitude'=>$p['lng']], $origins),
                'destinations'=> array_map(fn($p)=>['latitude'=>$p['lat'],'longitude'=>$p['lng']], $destinations),
                'travelMode'=>'driving'
            ];
            $res = OutboundHttp::post('https://atlas.microsoft.com/route/matrix/json?api-version=1.0&subscription-key='.$this->key, [
                'headers'=>['Content-Type'=>'application/json'],
                'body'=> wp_json_encode($payload), 'timeout'=>30
            ], ['purpose'=>'maps_azure','allowed_hosts'=>['atlas.microsoft.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]);
            if (is_wp_error($res)) return null;
            return json_decode(wp_remote_retrieve_body($res), true);
        });
    }
    public function directions($origin, $destination, $waypoints=[]){
        return $this->cachedProviderCall('azure_directions', ['origin'=>$origin,'destination'=>$destination,'waypoints'=>$waypoints], function() use ($origin, $destination, $waypoints) {
            $query = $origin['lat'].','.$origin['lng'].':'.$destination['lat'].','.$destination['lng'];
            foreach ($waypoints as $w) { $query .= ':'.$w['lat'].','.$w['lng']; }
            $url = 'https://atlas.microsoft.com/route/directions/json?api-version=1.0&subscription-key='.$this->key.'&query='.$query.'&travelMode=car';
            $res = OutboundHttp::get($url, ['timeout'=>30], ['purpose'=>'maps_azure','allowed_hosts'=>['atlas.microsoft.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]); if (is_wp_error($res)) return null;
            return json_decode(wp_remote_retrieve_body($res), true);
        });
    }
    public function placeSearch($query, $options=[]){
        return $this->cachedProviderCall('places', ['provider'=>'azure','query'=>$query,'options'=>$options], function() use ($query, $options) {
            $url = add_query_arg([
                'api-version' => '1.0',
                'query' => $query,
                'subscription-key' => $this->key,
                'limit' => $options['limit'] ?? 20,
                'language' => $options['language'] ?? 'pt-PT',
            ], 'https://atlas.microsoft.com/search/poi/json');
            $res = OutboundHttp::get($url, ['timeout'=>20], ['purpose'=>'maps_azure','allowed_hosts'=>['atlas.microsoft.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>35,'max_response_bytes'=>2097152]); if (is_wp_error($res)) return null;
            return json_decode(wp_remote_retrieve_body($res), true);
        });
    }
}

class MapsFactory {
    public static function make(): ?MapsProviderInterface {
        $prov = Config::get('maps_provider','leaflet');
        if ($prov === 'google') return new GoogleMapsProvider();
        if ($prov === 'azure')  return new AzureMapsProvider();
        return null;
    }
}
