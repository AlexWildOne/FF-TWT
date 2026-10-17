<?php
namespace RoutesPro\Services;

use RoutesPro\Support\Config;
use RoutesPro\Security\OutboundHttp;
use RoutesPro\Support\TollEstimator;

if (!defined('ABSPATH')) exit;

class GoogleRoutes {
    public const STATIC_ROUTING_COST_CONTRACT = 'fieldflow-static-routing-cost-authority-v1';
    public static function enabled(): bool {
        return (string) Config::get('routing_provider', 'internal') === 'google_routes' && Config::providerReady('google_routes');
    }

    public static function configured(): bool {
        return Config::providerReady('google_routes');
    }

    public const TOLL_COST_INTELLIGENCE_CONTRACT = 'fieldflow-toll-cost-intelligence-v1';

    public static function calculateRoute(array $points, array $options = []): array {
        $points = self::normalizePoints($points);
        if (count($points) < 2) return ['ok' => false, 'error' => 'São necessários pelo menos dois pontos para calcular rota.'];
        $ignoreProvider = !empty($options['ignore_provider']);
        $key = trim((string) Config::get('google_routes_api_key', ''));
        if ($key === '') return ['ok' => false, 'error' => 'Google Routes API Key por configurar.'];
        if (!$ignoreProvider && !self::enabled()) {
            $provider = (string) Config::get('routing_provider', 'internal');
            return ['ok' => false, 'error' => $provider === 'google_routes'
                ? 'Google Routes API não está pronta. Confirma se a API key está preenchida.'
                : 'Google Routes API está configurada, mas o provider ativo ainda é o estimador interno. Guarda as definições com provider Google Routes API ou usa o botão Guardar e testar Google Routes.'];
        }

        if (count($points) > 27 && empty($options['single_leg_call'])) return self::calculatePairwise($points, $options);

        $routeMode = (string) Config::get('google_routes_route_mode', 'fastest_tolls');
        $preference = (string) Config::get('google_routes_preference', 'TRAFFIC_UNAWARE');
        if (!in_array($preference, ['TRAFFIC_AWARE', 'TRAFFIC_AWARE_OPTIMAL', 'TRAFFIC_UNAWARE'], true)) $preference = 'TRAFFIC_UNAWARE';
        $tollsEnabled = !empty(Config::get('google_routes_tolls_enabled', 0));
        $tollIntelligenceEnabled = !empty(Config::get('toll_intelligence_enabled', 1));
        $tollVehicleClass = max(1, min(4, (int)Config::get('toll_vehicle_class', 1)));
        $waypointOptimizationEnabled = !empty(Config::get('google_routes_waypoint_optimization_enabled', 0));
        $optimizeWaypointOrderRequested = !empty($options['optimize_waypoint_order']) && $waypointOptimizationEnabled;
        if ($optimizeWaypointOrderRequested && $preference === 'TRAFFIC_AWARE_OPTIMAL') $preference = 'TRAFFIC_AWARE';
        $avoidTolls = $routeMode === 'fastest_no_tolls';
        $cacheDays = max(0, (int) Config::get('routing_cache_days', 30));
        $forceRefresh = !empty($options['force_refresh']);
        $routeSignature = self::routeSignature($points, ['optimize_waypoint_order'=>$optimizeWaypointOrderRequested]);
        $cacheKey = 'ff_google_routes_' . md5(wp_json_encode(['signature'=>$routeSignature,'v'=>7]));

        $origin = self::waypoint($points[0]);
        $destination = self::waypoint($points[count($points)-1]);
        if (!$origin || !$destination) return ['ok'=>false,'error'=>'Origem ou destino sem morada/coordenadas válidas.'];
        $intermediates = [];
        for ($i=1; $i<count($points)-1; $i++) { $wp = self::waypoint($points[$i]); if ($wp) $intermediates[] = $wp; }
        $optimizeWaypointOrder = $optimizeWaypointOrderRequested && !empty($intermediates);
        $billingOperation = self::routeBillingOperation($tollsEnabled, $optimizeWaypointOrder, $preference, count($intermediates));

        if (!$forceRefresh && $cacheDays > 0 && function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached) && !empty($cached['ok'])) {
                self::recordUsage($billingOperation, ['status'=>'success','cache_status'=>'hit','request_count'=>0,'units'=>1,'billable'=>false,'source'=>'route_cache']);
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        if (class_exists(GoogleCostControlCenter::class)) {
            $costGate = GoogleCostControlCenter::authorizeOperation($billingOperation, ['units'=>1]);
            if (empty($costGate['allowed'])) {
                return ['ok'=>false,'skipped'=>true,'reason'=>'google_cost_control_blocked','billing_operation'=>$billingOperation,'error'=>'Google Cost Control Center bloqueou uma chamada '.strtoupper((string)($costGate['tier'] ?? 'premium')).' antes do HTTP.'];
            }
        }

        $body = [
            'origin' => $origin,
            'destination' => $destination,
            'travelMode' => 'DRIVE',
            'routingPreference' => $preference,
            'routeModifiers' => ['avoidTolls' => $avoidTolls],
            'languageCode' => 'pt-PT',
        ];
        if ($tollsEnabled) {
            $body['extraComputations'] = ['TOLLS'];
            $body['routeModifiers']['vehicleInfo'] = ['emissionType' => 'GASOLINE'];
        }
        if ($intermediates) $body['intermediates'] = $intermediates;
        if ($optimizeWaypointOrder) $body['optimizeWaypointOrder'] = true;

        $fieldMask = 'routes.distanceMeters,routes.duration,routes.legs.distanceMeters,routes.legs.duration';
        if ($tollsEnabled) {
            $fieldMask .= ',routes.travelAdvisory.tollInfo,routes.legs.travelAdvisory.tollInfo';
        } elseif ($tollIntelligenceEnabled && !$avoidTolls) {
            // Basic route steps stay inside the normal Compute Routes request. They
            // are used transiently to identify Portuguese toll corridors and are
            // not persisted as raw Google navigation data.
            $fieldMask .= ',routes.legs.steps.distanceMeters,routes.legs.steps.navigationInstruction.instructions,routes.legs.steps.startLocation.latLng.latitude,routes.legs.steps.startLocation.latLng.longitude,routes.legs.steps.endLocation.latLng.latitude,routes.legs.steps.endLocation.latLng.longitude';
        }
        if ($optimizeWaypointOrder) $fieldMask .= ',routes.optimizedIntermediateWaypointIndex';

        $apiStartedAt = microtime(true);
        $response = OutboundHttp::post('https://routes.googleapis.com/directions/v2:computeRoutes', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => $fieldMask,
            ],
            'body' => wp_json_encode($body),
        ], ['purpose'=>'google_routes','allowed_hosts'=>['routes.googleapis.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>60,'max_response_bytes'=>4194304]);
        $routeCacheStatus = $cacheDays <= 0 ? 'disabled' : ($forceRefresh ? 'bypass' : 'miss');
        if (is_wp_error($response)) {
            self::recordUsage($billingOperation, ['status'=>'error','cache_status'=>$routeCacheStatus,'request_count'=>1,'units'=>1,'billable'=>true,'duration_ms'=>(int)round((microtime(true)-$apiStartedAt)*1000),'source'=>'compute_routes']);
            return ['ok'=>false,'error'=>'Erro Google Routes: '.$response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        self::recordUsage($billingOperation, [
            'status'=>($code >= 200 && $code < 300) ? 'success' : 'error',
            'cache_status'=>$routeCacheStatus,
            'request_count'=>1,
            'units'=>1,
            'billable'=>true,
            'duration_ms'=>(int)round((microtime(true)-$apiStartedAt)*1000),
            'http_status'=>$code,
            'source'=>'compute_routes',
            'meta'=>['toll_intelligence'=>$tollIntelligenceEnabled ? 1 : 0,'google_tolls'=>$tollsEnabled ? 1 : 0],
        ]);
        if ($code < 200 || $code >= 300) {
            $message = is_array($payload) ? (string)($payload['error']['message'] ?? 'Resposta inválida da Google Routes API.') : 'Resposta inválida da Google Routes API.';
            return ['ok'=>false,'error'=>self::friendlyError($message),'raw_error'=>$message,'status'=>$code];
        }
        $route = is_array($payload) ? ($payload['routes'][0] ?? []) : [];
        if (!is_array($route) || !$route) return ['ok'=>false,'error'=>'Google Routes API não devolveu rota.'];

        $distanceKm = round(((float)($route['distanceMeters'] ?? 0)) / 1000, 3);
        $travelMin = round(self::durationSeconds($route['duration'] ?? '') / 60, 1);
        $routeToll = self::moneyFromTollInfo($route['travelAdvisory']['tollInfo'] ?? []);
        $routeTollAmount = round((float)($routeToll['amount'] ?? 0), 2);
        $routeHasTollPrice = !empty($routeToll['has_price']) && $routeTollAmount > 0;

        $routeEstimate = null;
        if (!$routeHasTollPrice && !$avoidTolls && $tollIntelligenceEnabled) {
            $routeEstimate = TollEstimator::estimateFromGoogleRoute($route, $distanceKm, $tollVehicleClass);
        }
        if (!$routeHasTollPrice && !$avoidTolls && (!$routeEstimate || !empty($routeEstimate['used_generic_fallback'])) && !empty(Config::get('routing_fallback_internal', 1))) {
            if (!$routeEstimate) $routeEstimate = TollEstimator::estimateFromKm($distanceKm, 'route');
        }

        $legs = [];
        $legTollTotal = 0.0;
        foreach ((array)($route['legs'] ?? []) as $leg) {
            if (!is_array($leg)) continue;
            $legDistanceKm = round(((float)($leg['distanceMeters'] ?? 0)) / 1000, 3);
            $legToll = self::moneyFromTollInfo($leg['travelAdvisory']['tollInfo'] ?? []);
            $legHasReal = !empty($legToll['has_price']) && (float)($legToll['amount'] ?? 0) > 0;
            $legEstimate = null;
            if (!$legHasReal && !$avoidTolls && $tollIntelligenceEnabled) {
                $legEstimate = TollEstimator::estimateFromGoogleRoute(['legs'=>[$leg]], $legDistanceKm, $tollVehicleClass);
            }
            if (!$legHasReal && !$avoidTolls && !$legEstimate && !empty(Config::get('routing_fallback_internal', 1))) {
                $legEstimate = TollEstimator::estimateFromKm($legDistanceKm, 'segment');
            }
            $legCost = $legHasReal ? round((float)($legToll['amount'] ?? 0), 2) : round((float)($legEstimate['cost_eur'] ?? 0), 2);
            $legRow = [
                'distance_km'=>$legDistanceKm,
                'travel_min'=>round(self::durationSeconds($leg['duration'] ?? '') / 60, 1),
                'toll_cost_eur'=>$legCost,
                'toll_estimated_eur'=>$legCost,
                'toll_currency'=>(string)($legToll['currency'] ?? 'EUR'),
                'toll_has_price'=>$legHasReal,
                'toll_is_real_api'=>$legHasReal ? 1 : 0,
                'toll_fallback_internal'=>$legHasReal ? 0 : 1,
                'toll_confidence'=>$legHasReal ? 'real' : (string)($legEstimate['confidence'] ?? 'low'),
                'toll_min_eur'=>$legHasReal ? $legCost : round((float)($legEstimate['min_eur'] ?? $legCost), 2),
                'toll_max_eur'=>$legHasReal ? $legCost : round((float)($legEstimate['max_eur'] ?? $legCost), 2),
                'toll_tariff_version'=>$legHasReal ? 'google_tolls_api' : (string)($legEstimate['tariff_version'] ?? TollEstimator::TARIFF_VERSION),
                'routing_provider'=>'google_routes',
            ];
            $legTollTotal += $legCost;
            $legs[] = $legRow;
        }
        if (!$legs && count($points) === 2) {
            $fallbackCost = $routeHasTollPrice ? $routeTollAmount : (float)($routeEstimate['cost_eur'] ?? 0);
            $legs[] = ['distance_km'=>$distanceKm,'travel_min'=>$travelMin,'toll_cost_eur'=>round($fallbackCost,2),'toll_estimated_eur'=>round($fallbackCost,2),'toll_currency'=>(string)($routeToll['currency'] ?? 'EUR'),'toll_has_price'=>$routeHasTollPrice,'toll_is_real_api'=>$routeHasTollPrice?1:0,'toll_fallback_internal'=>$routeHasTollPrice?0:1,'toll_confidence'=>$routeHasTollPrice?'real':(string)($routeEstimate['confidence']??'low'),'routing_provider'=>'google_routes'];
            $legTollTotal = $fallbackCost;
        }
        if ($routeHasTollPrice && $legTollTotal <= 0 && $legs) {
            $sumDistance = array_sum(array_map(static function($leg){ return max(0.0, (float)($leg['distance_km'] ?? 0)); }, $legs));
            $remaining = $routeTollAmount; $last = count($legs)-1;
            foreach ($legs as $idx => &$legRow) {
                if ($idx === $last) $value = round(max(0.0, $remaining), 2);
                else { $share = $sumDistance > 0 ? ((float)$legRow['distance_km'] / $sumDistance) : (1 / count($legs)); $value = round($routeTollAmount * $share, 2); $remaining -= $value; }
                $legRow['toll_cost_eur'] = $value; $legRow['toll_estimated_eur'] = $value; $legRow['toll_has_price'] = true; $legRow['toll_is_real_api'] = 1; $legRow['toll_fallback_internal'] = 0; $legRow['toll_confidence'] = 'real'; $legRow['toll_min_eur'] = $value; $legRow['toll_max_eur'] = $value; $legRow['toll_allocated_from_route_total'] = true;
            }
            unset($legRow);
        }

        if ($avoidTolls) {
            $tollAmount = 0.0;
            $model = 'Google Routes API, rota rápida evitando portagens';
            $confidence = 'high';
            $minToll = 0.0; $maxToll = 0.0; $breakdown = [];
            $fallbackUsed = false;
        } elseif ($routeHasTollPrice) {
            $tollAmount = $routeTollAmount;
            $model = 'Google Routes API TOLLS, portagens devolvidas pela API';
            $confidence = 'real';
            $minToll = $tollAmount; $maxToll = $tollAmount; $breakdown = [];
            $fallbackUsed = false;
        } else {
            $tollAmount = round((float)($routeEstimate['cost_eur'] ?? $legTollTotal), 2);
            $model = (string)($routeEstimate['model'] ?? 'Google Routes API para distância/tempo, portagens estimadas internamente');
            $confidence = (string)($routeEstimate['confidence'] ?? 'low');
            $minToll = round((float)($routeEstimate['min_eur'] ?? $tollAmount), 2);
            $maxToll = round((float)($routeEstimate['max_eur'] ?? $tollAmount), 2);
            $breakdown = (array)($routeEstimate['breakdown'] ?? []);
            $fallbackUsed = true;
        }

        $result = [
            'ok'=>true,'provider'=>'google_routes','routing_provider'=>'google_routes','route_mode'=>$routeMode,'routing_preference'=>$preference,
            'distance_km'=>$distanceKm,'travel_min'=>$travelMin,'duration_seconds'=>self::durationSeconds($route['duration'] ?? ''),
            'toll_cost_eur'=>round($tollAmount,2),'toll_estimated_eur'=>round($tollAmount,2),'toll_currency'=>(string)($routeToll['currency'] ?? 'EUR'),
            'toll_has_price'=>$routeHasTollPrice,'toll_is_real_api'=>$routeHasTollPrice ? 1 : 0,'toll_fallback_internal'=>$fallbackUsed ? 1 : 0,
            'toll_model'=>$model,'toll_confidence'=>$confidence,'toll_min_eur'=>$minToll,'toll_max_eur'=>$maxToll,'toll_tariff_version'=>$routeHasTollPrice ? 'google_tolls_api' : TollEstimator::TARIFF_VERSION,'toll_vehicle_class'=>$tollVehicleClass,'toll_breakdown'=>$breakdown,
            'legs'=>$legs,'optimized_intermediate_waypoint_index'=>array_values(array_map('intval', (array)($route['optimizedIntermediateWaypointIndex'] ?? []))),'waypoint_order_optimized'=>$optimizeWaypointOrder ? 1 : 0,'route_signature'=>$routeSignature,'routing_cost_contract'=>self::STATIC_ROUTING_COST_CONTRACT,'toll_cost_intelligence_contract'=>self::TOLL_COST_INTELLIGENCE_CONTRACT,'google_billing_operation'=>$billingOperation,'google_tolls_enabled'=>$tollsEnabled ? 1 : 0,'toll_intelligence_enabled'=>$tollIntelligenceEnabled ? 1 : 0,'google_waypoint_optimization_enabled'=>$waypointOptimizationEnabled ? 1 : 0,'calculated_at'=>function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),'from_cache'=>false,
        ];
        if (!$forceRefresh && $cacheDays > 0 && function_exists('set_transient')) set_transient($cacheKey, $result, $cacheDays * DAY_IN_SECONDS);
        return $result;
    }

    public static function routeSignature(array $points, array $options = []): string {
        $points = self::normalizePoints($points);
        $routeMode = (string) Config::get('google_routes_route_mode', 'fastest_tolls');
        $preference = (string) Config::get('google_routes_preference', 'TRAFFIC_UNAWARE');
        if (!in_array($preference, ['TRAFFIC_AWARE', 'TRAFFIC_AWARE_OPTIMAL', 'TRAFFIC_UNAWARE'], true)) $preference = 'TRAFFIC_UNAWARE';
        $tollsEnabled = !empty(Config::get('google_routes_tolls_enabled', 0));
        $tollIntelligenceEnabled = !empty(Config::get('toll_intelligence_enabled', 1));
        $tollVehicleClass = max(1, min(4, (int)Config::get('toll_vehicle_class', 1)));
        $waypointOptimizationEnabled = !empty(Config::get('google_routes_waypoint_optimization_enabled', 0));
        $optimize = !empty($options['optimize_waypoint_order']) && $waypointOptimizationEnabled;
        $identity = array_map(static function(array $point): array {
            $lat = is_numeric($point['lat'] ?? null) ? round((float)$point['lat'], 6) : null;
            $lng = is_numeric($point['lng'] ?? null) ? round((float)$point['lng'], 6) : null;
            // When coordinates exist they are the routing authority. Display names
            // and address formatting must not create a paid refresh for the same point.
            if ($lat !== null && $lng !== null) return ['lat'=>$lat,'lng'=>$lng];
            return ['address'=>strtolower(trim((string)($point['address'] ?? '')))];
        }, $points);
        return hash('sha256', wp_json_encode([
            'contract'=>self::STATIC_ROUTING_COST_CONTRACT,
            'points'=>$identity,
            'route_mode'=>$routeMode,
            'preference'=>$preference,
            'tolls'=>$tollsEnabled ? 1 : 0,
            'toll_intelligence'=>$tollIntelligenceEnabled ? 1 : 0,
            'toll_vehicle_class'=>$tollVehicleClass,
            'optimize_waypoints'=>$optimize ? 1 : 0,
        ]));
    }

    public static function calculateMatrix(array $points, array $options = []): array {
        $points = self::normalizePoints($points);
        if (count($points) < 2) return ['ok'=>false,'error'=>'São necessários pelo menos dois pontos para calcular a matriz.'];
        $key = trim((string) Config::get('google_routes_api_key', ''));
        if ($key === '') return ['ok'=>false,'error'=>'Google Routes API Key por configurar.'];
        if (empty($options['ignore_provider']) && !self::enabled()) return ['ok'=>false,'error'=>'Google Routes API não está ativa como provider de routing.'];
        if (empty(Config::get('google_route_matrix_enabled', 0)) && empty($options['allow_costly_matrix'])) {
            return ['ok'=>false,'skipped'=>true,'reason'=>'matrix_disabled_by_cost_policy','error'=>'Google Route Matrix desativada pela política de custos FieldFlow.'];
        }

        $preference = (string) Config::get('google_routes_preference', 'TRAFFIC_UNAWARE');
        if (!in_array($preference, ['TRAFFIC_AWARE', 'TRAFFIC_AWARE_OPTIMAL', 'TRAFFIC_UNAWARE'], true)) $preference = 'TRAFFIC_UNAWARE';
        $matrixBillingOperation = self::matrixBillingOperation($preference);
        $limit = $preference === 'TRAFFIC_AWARE_OPTIMAL' ? 10 : 25;
        if (count($points) > $limit) return ['ok'=>false,'error'=>sprintf('A matriz Google suporta no máximo %d pontos neste modo de tráfego.', $limit)];
        $routeMode = (string) Config::get('google_routes_route_mode', 'fastest_tolls');
        $avoidTolls = $routeMode === 'fastest_no_tolls';
        $cacheDays = max(0, (int) Config::get('routing_cache_days', 30));
        $forceRefresh = !empty($options['force_refresh']);
        $cacheKey = 'ff_google_route_matrix_' . md5(wp_json_encode(['points'=>$points,'mode'=>$routeMode,'pref'=>$preference,'avoid_tolls'=>$avoidTolls,'v'=>2]));
        if (!$forceRefresh && $cacheDays > 0 && function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached) && !empty($cached['ok'])) {
                $matrixUnits = count($points) * count($points);
                self::recordUsage($matrixBillingOperation, ['status'=>'success','cache_status'=>'hit','request_count'=>0,'units'=>$matrixUnits,'billable'=>false,'source'=>'matrix_cache']);
                $cached['from_cache'] = true;
                return $cached;
            }
        }

        if (class_exists(GoogleCostControlCenter::class)) {
            $matrixUnits = count($points) * count($points);
            $costGate = GoogleCostControlCenter::authorizeOperation($matrixBillingOperation, ['units'=>$matrixUnits]);
            if (empty($costGate['allowed'])) {
                return ['ok'=>false,'skipped'=>true,'reason'=>'google_cost_control_blocked','billing_operation'=>$matrixBillingOperation,'error'=>'Google Cost Control Center bloqueou Route Matrix antes do HTTP.'];
            }
        }

        $origins = [];
        $destinations = [];
        $keys = [];
        foreach ($points as $idx => $point) {
            $wp = self::waypoint($point);
            if (!$wp) return ['ok'=>false,'error'=>'Existem pontos sem morada ou coordenadas válidas para a matriz.'];
            $keys[] = (string)($point['key'] ?? $point['_matrix_key'] ?? $point['matrix_key'] ?? ('p_'.$idx));
            $origins[] = ['waypoint'=>$wp,'routeModifiers'=>['avoidTolls'=>$avoidTolls]];
            $destinations[] = ['waypoint'=>$wp];
        }
        $body = [
            'origins' => $origins,
            'destinations' => $destinations,
            'travelMode' => 'DRIVE',
            'routingPreference' => $preference,
        ];
        $matrixUnits = count($points) * count($points);
        $apiStartedAt = microtime(true);
        $response = OutboundHttp::post('https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix', [
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'originIndex,destinationIndex,duration,distanceMeters,status,condition',
            ],
            'body' => wp_json_encode($body),
        ], ['purpose'=>'google_routes','allowed_hosts'=>['routes.googleapis.com'],'allowed_ports'=>[443],'max_redirects'=>0,'max_timeout'=>60,'max_response_bytes'=>4194304]);
        $matrixCacheStatus = $cacheDays <= 0 ? 'disabled' : ($forceRefresh ? 'bypass' : 'miss');
        if (is_wp_error($response)) {
            self::recordUsage($matrixBillingOperation, ['status'=>'error','cache_status'=>$matrixCacheStatus,'request_count'=>1,'units'=>$matrixUnits,'billable'=>true,'duration_ms'=>(int)round((microtime(true)-$apiStartedAt)*1000),'source'=>'compute_route_matrix']);
            return ['ok'=>false,'error'=>'Erro Google Route Matrix: '.$response->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($rawBody, true);
        self::recordUsage($matrixBillingOperation, [
            'status'=>($code >= 200 && $code < 300) ? 'success' : 'error',
            'cache_status'=>$matrixCacheStatus,
            'request_count'=>1,
            'units'=>$matrixUnits,
            'billable'=>true,
            'duration_ms'=>(int)round((microtime(true)-$apiStartedAt)*1000),
            'http_status'=>$code,
            'source'=>'compute_route_matrix',
        ]);
        if (!is_array($payload) && trim($rawBody) !== '') {
            $payload = [];
            foreach (preg_split('/\r?\n/', trim($rawBody)) as $line) {
                $element = json_decode(trim($line), true);
                if (is_array($element)) $payload[] = $element;
            }
        }
        if ($code < 200 || $code >= 300) {
            $message = is_array($payload) ? (string)($payload['error']['message'] ?? 'Resposta inválida da Google Route Matrix.') : 'Resposta inválida da Google Route Matrix.';
            return ['ok'=>false,'error'=>self::friendlyError($message),'raw_error'=>$message,'status'=>$code];
        }
        if (!is_array($payload)) return ['ok'=>false,'error'=>'A Google Route Matrix devolveu uma resposta inválida.'];
        $edges = [];
        $resolved = 0;
        foreach ($payload as $element) {
            if (!is_array($element)) continue;
            $oi = isset($element['originIndex']) ? (int)$element['originIndex'] : -1;
            $di = isset($element['destinationIndex']) ? (int)$element['destinationIndex'] : -1;
            if (!isset($keys[$oi], $keys[$di])) continue;
            $condition = (string)($element['condition'] ?? '');
            $statusCode = (int)($element['status']['code'] ?? 0);
            if ($condition === 'ROUTE_NOT_FOUND' || $condition === 'NO_ROUTE') {
                $edges[$keys[$oi]][$keys[$di]] = [
                    'distance_km' => null, 'travel_min' => null,
                    'source' => 'google_route_matrix', 'status' => 'impossible',
                ];
                continue;
            }
            if ($condition !== 'ROUTE_EXISTS' || $statusCode !== 0 || !is_numeric($element['distanceMeters'] ?? null)
                || !preg_match('/^\d+(?:\.\d+)?s$/', (string)($element['duration'] ?? ''))) continue;
            $edges[$keys[$oi]][$keys[$di]] = [
                'distance_km' => round(((float)$element['distanceMeters']) / 1000, 3),
                'travel_min' => round(self::durationSeconds($element['duration'] ?? '') / 60, 1),
                'source' => 'google_route_matrix',
                'status' => 'complete',
            ];
            if ($oi !== $di) $resolved++;
        }
        $result = [
            'ok'=>true,
            'provider'=>'google_route_matrix',
            'routing_provider'=>'google_routes',
            'edges'=>$edges,
            'point_keys'=>$keys,
            'resolved_pairs'=>$resolved,
            'pair_count'=>count($points) * max(0, count($points)-1),
            'calculated_at'=>function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'from_cache'=>false,
        ];
        if ($cacheDays > 0 && function_exists('set_transient')) set_transient($cacheKey, $result, $cacheDays * DAY_IN_SECONDS);
        return $result;
    }

    public static function diagnostic(string $origin, string $destination): array {
        $result = self::calculateRoute([['address'=>$origin], ['address'=>$destination]], ['single_leg_call'=>true, 'ignore_provider'=>true, 'force_refresh'=>true]);
        if (empty($result['ok'])) return ['ok'=>false,'message'=>'Teste Google Routes falhou: '.(string)($result['error'] ?? 'erro desconhecido')];
        $toll = (float)($result['toll_cost_eur'] ?? 0);
        $tollLabel = $toll > 0 ? TollEstimator::formatEuro($toll) : 'sem valor de portagem devolvido';
        $real = !empty($result['toll_is_real_api']) ? 'portagem Google' : 'FieldFlow Toll Intelligence, confiança '.(string)($result['toll_confidence'] ?? 'low');
        return ['ok'=>true,'message'=>sprintf('Teste Google Routes OK. %s para %s: %.1f km, %d min, portagens: %s (%s).', $origin, $destination, (float)($result['distance_km'] ?? 0), (int)round((float)($result['travel_min'] ?? 0)), $tollLabel, $real),'result'=>$result];
    }

    private static function calculatePairwise(array $points, array $options = []): array {
        $legs=[]; $distance=0.0; $travel=0.0; $toll=0.0; $minToll=0.0; $maxToll=0.0; $allReal=true; $confidence='high';
        $rank = ['real'=>4,'high'=>3,'medium'=>2,'low'=>1];
        for ($i=0; $i<count($points)-1; $i++) {
            $res = self::calculateRoute([$points[$i], $points[$i+1]], array_merge($options, ['single_leg_call'=>true]));
            if (empty($res['ok'])) return $res;
            $leg = $res['legs'][0] ?? ['distance_km'=>(float)($res['distance_km'] ?? 0),'travel_min'=>(float)($res['travel_min'] ?? 0),'toll_cost_eur'=>(float)($res['toll_cost_eur'] ?? 0),'toll_has_price'=>!empty($res['toll_is_real_api']),'routing_provider'=>'google_routes'];
            $legs[]=$leg;
            $distance+=(float)($leg['distance_km'] ?? 0);
            $travel+=(float)($leg['travel_min'] ?? 0);
            $toll+=(float)($leg['toll_cost_eur'] ?? 0);
            $minToll+=(float)($leg['toll_min_eur'] ?? $leg['toll_cost_eur'] ?? 0);
            $maxToll+=(float)($leg['toll_max_eur'] ?? $leg['toll_cost_eur'] ?? 0);
            if (empty($res['toll_is_real_api'])) $allReal=false;
            $legConfidence=(string)($leg['toll_confidence'] ?? 'low');
            if (($rank[$legConfidence] ?? 1) < ($rank[$confidence] ?? 3)) $confidence=$legConfidence;
        }
        if ($allReal) $confidence='real';
        return [
            'ok'=>true,'provider'=>'google_routes','routing_provider'=>'google_routes','route_mode'=>(string)Config::get('google_routes_route_mode','fastest_tolls'),'routing_preference'=>(string)Config::get('google_routes_preference','TRAFFIC_UNAWARE'),
            'distance_km'=>round($distance,3),'travel_min'=>round($travel,1),'duration_seconds'=>(int)round($travel*60),
            'toll_cost_eur'=>round($toll,2),'toll_estimated_eur'=>round($toll,2),'toll_min_eur'=>round($minToll,2),'toll_max_eur'=>round($maxToll,2),'toll_confidence'=>$confidence,'toll_tariff_version'=>$allReal?'google_tolls_api':TollEstimator::TARIFF_VERSION,'toll_vehicle_class'=>max(1,min(4,(int)Config::get('toll_vehicle_class',1))),
            'toll_currency'=>'EUR','toll_has_price'=>$allReal,'toll_is_real_api'=>$allReal ? 1 : 0,'toll_fallback_internal'=>$allReal ? 0 : 1,'toll_model'=>$allReal ? 'Google Routes API TOLLS, portagens por segmento' : 'FieldFlow Toll Intelligence 2026, estimativa por segmento sem Google TOLLS','toll_cost_intelligence_contract'=>self::TOLL_COST_INTELLIGENCE_CONTRACT,
            'legs'=>$legs,'calculated_at'=>function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),'from_cache'=>false
        ];
    }

    private static function routeBillingOperation(bool $tollsEnabled, bool $optimizeWaypointOrder, string $preference, int $intermediateCount): string {
        if ($tollsEnabled) return 'compute_routes_enterprise';
        if ($optimizeWaypointOrder || in_array($preference, ['TRAFFIC_AWARE','TRAFFIC_AWARE_OPTIMAL'], true) || $intermediateCount > 10) return 'compute_routes_pro';
        return 'compute_routes_essentials';
    }

    private static function matrixBillingOperation(string $preference): string {
        if (in_array($preference, ['TRAFFIC_AWARE','TRAFFIC_AWARE_OPTIMAL'], true)) return 'compute_route_matrix_pro';
        return 'compute_route_matrix_essentials';
    }

    private static function recordUsage(string $operation, array $data): void {
        if (!class_exists('\\RoutesPro\\Observability\\ApiUsageLedger')) return;
        \RoutesPro\Observability\ApiUsageLedger::record('google_routes', $operation, $data);
    }

    private static function normalizePoints(array $points): array {
        $out=[];
        foreach ($points as $point) {
            if (!is_array($point)) continue;
            $lat = is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null;
            $lng = is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null;
            if ($lat === null || $lng === null || !is_finite($lat) || !is_finite($lng) || abs($lat) > 90 || abs($lng) > 180) {
                $lat = null;
                $lng = null;
            }
            $address = trim((string)($point['address'] ?? ''));
            $name = trim((string)($point['name'] ?? ''));
            if ($lat === null && $lng === null && $address === '' && $name === '') continue;
            $matrixKey = sanitize_key((string)($point['_matrix_key'] ?? $point['matrix_key'] ?? $point['key'] ?? ''));
            $out[] = ['name'=>$name,'address'=>$address,'lat'=>$lat,'lng'=>$lng,'key'=>$matrixKey,'_matrix_key'=>$matrixKey];
        }
        return $out;
    }

    private static function waypoint(array $point): ?array {
        $lat = is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null;
        $lng = is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null;
        if ($lat !== null && $lng !== null) return ['location'=>['latLng'=>['latitude'=>$lat,'longitude'=>$lng]]];
        $address = trim((string)($point['address'] ?? ''));
        if ($address !== '') return ['address'=>$address];
        $name = trim((string)($point['name'] ?? ''));
        if ($name !== '') return ['address'=>$name];
        return null;
    }

    private static function moneyFromTollInfo($tollInfo): array {
        $amount=0.0; $currency='EUR'; $prices = is_array($tollInfo) ? ($tollInfo['estimatedPrice'] ?? []) : [];
        if (!is_array($prices)) return ['amount'=>0.0,'currency'=>$currency,'has_price'=>false];
        foreach ($prices as $price) { if (!is_array($price)) continue; $currency=(string)($price['currencyCode'] ?? $currency); $amount += (float)($price['units'] ?? 0) + ((float)($price['nanos'] ?? 0) / 1000000000); }
        return ['amount'=>round(max(0.0,$amount),2),'currency'=>$currency,'has_price'=>$amount > 0];
    }

    private static function durationSeconds($duration): int {
        if (is_numeric($duration)) return max(0, (int)$duration);
        if (is_string($duration) && preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $m)) return max(0, (int)round((float)$m[1]));
        return 0;
    }

    private static function friendlyError(string $message): string {
        if (stripos($message, 'referer') !== false || stripos($message, 'referrer') !== false) return $message . ' A chave está restringida por HTTP referrer. Para chamadas feitas pelo WordPress/backend, usa uma API key server-side restrita ao IP público de saída do servidor e limitada apenas à Routes API.';
        if (stripos($message, 'API key not valid') !== false || stripos($message, 'invalid api key') !== false) return $message . ' Confirma se a chave está correta e se a Routes API está ativa no mesmo projeto.';
        if (stripos($message, 'billing') !== false) return $message . ' Confirma se o projeto Google Cloud tem billing ativo.';
        return $message;
    }
}
