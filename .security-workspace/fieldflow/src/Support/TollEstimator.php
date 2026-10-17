<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Low-cost Portuguese toll intelligence.
 *
 * The primary 2026 model estimates tolls from the road references already
 * returned by the standard Google Compute Routes response. It never requires
 * extraComputations=TOLLS. The older distance-only model remains as a safe
 * fallback when road context is unavailable.
 */
class TollEstimator {
    public const INTELLIGENCE_CONTRACT = 'fieldflow-toll-cost-intelligence-v1';
    public const TARIFF_VERSION = 'PT-2026-IMT-IP-ASCENDI-v1';

    public static function options(): array {
        $defaults = [
            'enabled' => true,
            'eur_per_toll_km' => 0.075,
            'tollable_share' => 0.58,
            'min_segment_km' => 3.0,
            'round_to' => 0.05,
            'model' => 'Estimativa classe 1, rota rápida com portagens permitidas',
        ];
        $stored = function_exists('get_option') ? get_option('routespro_toll_estimator', []) : [];
        if (!is_array($stored)) $stored = [];
        $opts = array_merge($defaults, $stored);
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('routespro_toll_estimator_options', $opts);
            if (is_array($filtered)) $opts = array_merge($opts, $filtered);
        }
        $opts['enabled'] = !empty($opts['enabled']);
        $opts['eur_per_toll_km'] = max(0.0, (float)($opts['eur_per_toll_km'] ?? $defaults['eur_per_toll_km']));
        $opts['tollable_share'] = max(0.0, min(1.0, (float)($opts['tollable_share'] ?? $defaults['tollable_share'])));
        $opts['min_segment_km'] = max(0.0, (float)($opts['min_segment_km'] ?? $defaults['min_segment_km']));
        $opts['round_to'] = max(0.01, (float)($opts['round_to'] ?? $defaults['round_to']));
        $opts['model'] = trim((string)($opts['model'] ?? $defaults['model'])) ?: $defaults['model'];
        return $opts;
    }

    public static function tariffRegistry(): array {
        // Average effective Class 1 rates derived from official 2026 Portuguese
        // tariffs. The objective is operational budgeting, not invoice-grade tolling.
        return [
            'A1'=>['rate'=>0.087,'status'=>'tolled','source'=>'IMT 2026'],
            'A2'=>['rate'=>0.114,'status'=>'tolled','source'=>'IMT 2026'],
            'A3'=>['rate'=>0.104,'status'=>'tolled','source'=>'IMT 2026'],
            'A4'=>['rate'=>0.098,'status'=>'mixed','source'=>'IMT/IP 2026'],
            'A5'=>['rate'=>0.105,'status'=>'tolled','source'=>'IMT 2026'],
            'A6'=>['rate'=>0.109,'status'=>'tolled','source'=>'IMT 2026'],
            'A8'=>['rate'=>0.101,'status'=>'tolled','source'=>'IMT 2026'],
            'A9'=>['rate'=>0.109,'status'=>'tolled','source'=>'IMT 2026'],
            'A10'=>['rate'=>0.108,'status'=>'tolled','source'=>'IMT 2026'],
            'A12'=>['rate'=>0.099,'status'=>'tolled','source'=>'IMT 2026'],
            'A13'=>['rate'=>0.107,'status'=>'mixed','source'=>'IMT/IP 2026'],
            'A14'=>['rate'=>0.108,'status'=>'tolled','source'=>'IMT 2026'],
            'A15'=>['rate'=>0.107,'status'=>'tolled','source'=>'IMT 2026'],
            'A16'=>['rate'=>0.108,'status'=>'tolled','source'=>'IMT 2026'],
            'A17'=>['rate'=>0.108,'status'=>'tolled','source'=>'IMT 2026'],
            'A21'=>['rate'=>0.106,'status'=>'tolled','source'=>'IMT 2026'],
            'A22'=>['rate'=>0.000,'status'=>'free','source'=>'Lei 37/2024'],
            'A23'=>['rate'=>0.000,'status'=>'free','source'=>'Lei 37/2024'],
            'A24'=>['rate'=>0.000,'status'=>'free','source'=>'Lei 37/2024'],
            'A25'=>['rate'=>0.000,'status'=>'free','source'=>'Lei 37/2024'],
            'A28'=>['rate'=>0.045,'status'=>'mixed','source'=>'Lei 37/2024 / concessionaria'],
            'A29'=>['rate'=>0.040,'status'=>'tolled','source'=>'Ascendi 2026'],
            'A32'=>['rate'=>0.106,'status'=>'tolled','source'=>'IMT 2026'],
            'A41'=>['rate'=>0.085,'status'=>'tolled','source'=>'IMT/Ascendi 2026'],
            'A42'=>['rate'=>0.053,'status'=>'tolled','source'=>'Ascendi 2026'],
            'A43'=>['rate'=>0.108,'status'=>'tolled','source'=>'IMT 2026'],
        ];
    }

    public static function estimateFromGoogleRoute(array $route, float $distanceKm, int $vehicleClass = 1): array {
        $class = max(1, min(4, $vehicleClass));
        $total = 0.0;
        $recognizedTolledKm = 0.0;
        $recognizedFreeKm = 0.0;
        $motorwayUnknownKm = 0.0;
        $evidenceSteps = 0;
        $breakdown = [];

        foreach ((array)($route['legs'] ?? []) as $leg) {
            if (!is_array($leg)) continue;
            foreach ((array)($leg['steps'] ?? []) as $step) {
                if (!is_array($step)) continue;
                $km = max(0.0, ((float)($step['distanceMeters'] ?? 0)) / 1000);
                if ($km <= 0.0) continue;
                $instruction = self::instructionText($step);
                $road = self::roadReference($instruction);
                $coords = self::stepCoordinates($step);
                if ($road === '') {
                    if (self::looksLikeMotorway($instruction)) $motorwayUnknownKm += $km;
                    continue;
                }

                $evaluation = self::roadCost($road, $km, $class, $coords);
                if ($evaluation === null) {
                    $motorwayUnknownKm += $km;
                    continue;
                }
                $evidenceSteps++;
                $cost = (float)$evaluation['cost_eur'];
                $total += $cost;
                if (!empty($evaluation['free'])) $recognizedFreeKm += $km;
                else $recognizedTolledKm += $km;

                $key = $road . '|' . ($evaluation['free'] ? 'free' : 'tolled');
                if (!isset($breakdown[$key])) {
                    $breakdown[$key] = [
                        'road'=>$road,
                        'distance_km'=>0.0,
                        'cost_eur'=>0.0,
                        'status'=>$evaluation['free'] ? 'gratuito' : 'portajado',
                        'source'=>(string)$evaluation['source'],
                    ];
                }
                $breakdown[$key]['distance_km'] += $km;
                $breakdown[$key]['cost_eur'] += $cost;
            }
        }

        $bridge = self::bridgeEstimate($route, $class);
        if ($bridge['cost_eur'] > 0) {
            $total += (float)$bridge['cost_eur'];
            $breakdown['bridge|' . $bridge['road']] = $bridge;
            $evidenceSteps++;
        }

        $hasEvidence = $evidenceSteps > 0;
        $usedGenericFallback = false;
        if (!$hasEvidence && $distanceKm >= 25.0) {
            $fallback = self::estimateFromKm($distanceKm, 'route');
            $total = (float)($fallback['cost_eur'] ?? 0.0);
            $usedGenericFallback = true;
        }

        $total = self::roundCurrency($total, 0.05);
        if ($usedGenericFallback) $confidence = 'low';
        elseif ($motorwayUnknownKm > max(5.0, $recognizedTolledKm * 0.35)) $confidence = 'medium';
        elseif ($hasEvidence) $confidence = 'high';
        elseif ($distanceKm < 25.0) $confidence = 'medium';
        else $confidence = 'low';

        $spread = $confidence === 'high' ? 0.10 : ($confidence === 'medium' ? 0.20 : 0.35);
        $min = self::roundCurrency(max(0.0, $total * (1.0 - $spread)), 0.05);
        $max = self::roundCurrency($total * (1.0 + $spread), 0.05);
        if ($total <= 0.0 && !$usedGenericFallback) { $min = 0.0; $max = 0.0; }

        foreach ($breakdown as &$row) {
            $row['distance_km'] = round((float)$row['distance_km'], 2);
            $row['cost_eur'] = round((float)$row['cost_eur'], 2);
        }
        unset($row);

        return [
            'distance_km'=>round(max(0.0, $distanceKm), 2),
            'cost_eur'=>round($total, 2),
            'min_eur'=>round($min, 2),
            'max_eur'=>round($max, 2),
            'confidence'=>$confidence,
            'vehicle_class'=>$class,
            'tariff_version'=>self::TARIFF_VERSION,
            'model'=>'FieldFlow Toll Intelligence 2026, Classe '.$class.', sem Google TOLLS',
            'estimated'=>true,
            'used_generic_fallback'=>$usedGenericFallback,
            'recognized_tolled_km'=>round($recognizedTolledKm, 2),
            'recognized_free_km'=>round($recognizedFreeKm, 2),
            'unknown_motorway_km'=>round($motorwayUnknownKm, 2),
            'breakdown'=>array_values($breakdown),
        ];
    }

    public static function estimateFromKm(float $distanceKm, string $context = 'route'): array {
        $opts = self::options();
        $km = max(0.0, $distanceKm);
        $cost = 0.0;
        if (!empty($opts['enabled']) && $km > 0.0) {
            if ($context !== 'segment' || $km >= (float)$opts['min_segment_km']) {
                $raw = $km * (float)$opts['tollable_share'] * (float)$opts['eur_per_toll_km'];
                $cost = self::roundCurrency($raw, (float)$opts['round_to']);
            }
        }
        return [
            'distance_km' => round($km, 2),
            'cost_eur' => round($cost, 2),
            'min_eur' => round(self::roundCurrency($cost * 0.65, 0.05), 2),
            'max_eur' => round(self::roundCurrency($cost * 1.35, 0.05), 2),
            'confidence' => 'low',
            'tariff_version' => self::TARIFF_VERSION,
            'model' => (string)$opts['model'],
            'tollable_share' => (float)$opts['tollable_share'],
            'eur_per_toll_km' => (float)$opts['eur_per_toll_km'],
            'estimated' => true,
            'used_generic_fallback' => true,
            'breakdown' => [],
        ];
    }

    public static function costFromKm(float $distanceKm, string $context = 'route'): float {
        $estimate = self::estimateFromKm($distanceKm, $context);
        return (float)($estimate['cost_eur'] ?? 0.0);
    }

    public static function formatEuro(float $value): string {
        return number_format(max(0.0, $value), 2, ',', ' ') . ' €';
    }

    private static function instructionText(array $step): string {
        $instruction = $step['navigationInstruction']['instructions'] ?? '';
        return is_string($instruction) ? trim($instruction) : '';
    }

    private static function roadReference(string $instruction): string {
        $text = strtoupper(str_replace(['‑','–','—'], '-', $instruction));
        if (preg_match('/\bA\s*[- ]?\s*(\d{1,2})\b/u', $text, $m)) return 'A' . (int)$m[1];
        $aliases = [
            'AUTOESTRADA DO NORTE'=>'A1', 'AUTOESTRADA DO SUL'=>'A2', 'PORTO-VALENCA'=>'A3',
            'PORTO VALENCA'=>'A3', 'CREL'=>'A9', 'CREP'=>'A41', 'RADIAL DE SINTRA'=>'A16',
        ];
        foreach ($aliases as $needle=>$road) if (strpos(self::fold($text), self::fold($needle)) !== false) return $road;
        return '';
    }

    private static function looksLikeMotorway(string $instruction): bool {
        $text = strtoupper($instruction);
        return strpos($text, 'AUTOESTRADA') !== false || strpos($text, 'MOTORWAY') !== false || preg_match('/\bA\s*[- ]?\s*\d{1,2}\b/u', $text) === 1;
    }

    private static function roadCost(string $road, float $km, int $class, array $coords): ?array {
        $registry = self::tariffRegistry();
        if (!isset($registry[$road])) return null;
        $row = $registry[$road];
        $status = (string)$row['status'];
        $rate = (float)$row['rate'];

        // Free former-SCUT corridors from 2025 onwards.
        if (in_array($road, ['A22','A23','A24','A25'], true)) $rate = 0.0;

        // A4: Transmontana / Tunel do Marão is free. Porto-Amarante remains tolled.
        if ($road === 'A4' && $coords['mid_lng'] !== null && (float)$coords['mid_lng'] > -8.00) $rate = 0.0;

        // A13: Pinhal Interior is free, Santarem-Marateca remains tolled.
        if ($road === 'A13' && $coords['mid_lng'] !== null && (float)$coords['mid_lng'] > -8.45) $rate = 0.0;

        // A28 has selected free segments. Treat the northern exempt corridor conservatively.
        if ($road === 'A28' && $coords['mid_lat'] !== null && (float)$coords['mid_lat'] >= 41.52) $rate = 0.0;

        // 2026 A41 heavy-vehicle exemption applies to classes 3 and 4 where eligible.
        if ($road === 'A41' && in_array($class, [3,4], true)) $rate = 0.0;

        $multiplier = self::classMultiplier($class);
        $cost = self::roundCurrency($km * $rate * $multiplier, 0.05);
        return [
            'cost_eur'=>$cost,
            'free'=>$rate <= 0.0,
            'source'=>(string)$row['source'],
            'status'=>$status,
        ];
    }

    private static function bridgeEstimate(array $route, int $class): array {
        $bridges = [
            'PONTE 25 DE ABRIL'=>['road'=>'Ponte 25 de Abril','classes'=>[1=>2.25,2=>4.85,3=>6.55,4=>8.45]],
            'PONTE VASCO DA GAMA'=>['road'=>'Ponte Vasco da Gama','classes'=>[1=>3.40,2=>7.55,3=>11.10,4=>14.20]],
            'VASCO DA GAMA BRIDGE'=>['road'=>'Ponte Vasco da Gama','classes'=>[1=>3.40,2=>7.55,3=>11.10,4=>14.20]],
            '25 DE ABRIL BRIDGE'=>['road'=>'Ponte 25 de Abril','classes'=>[1=>2.25,2=>4.85,3=>6.55,4=>8.45]],
        ];
        foreach ((array)($route['legs'] ?? []) as $leg) {
            foreach ((array)($leg['steps'] ?? []) as $step) {
                if (!is_array($step)) continue;
                $text = self::fold(self::instructionText($step));
                foreach ($bridges as $needle=>$bridge) {
                    if (strpos($text, self::fold($needle)) === false) continue;
                    $coords = self::stepCoordinates($step);
                    // Lisbon crossings are charged towards Lisbon/northbound. If
                    // direction is known and southbound, do not estimate a toll.
                    if ($coords['start_lat'] !== null && $coords['end_lat'] !== null && (float)$coords['end_lat'] < (float)$coords['start_lat']) continue;
                    $cost = self::roundCurrency((float)($bridge['classes'][$class] ?? $bridge['classes'][1] ?? 0.0), 0.05);
                    return ['road'=>$bridge['road'],'distance_km'=>0.0,'cost_eur'=>$cost,'status'=>'portajado','source'=>'IMT 2026'];
                }
            }
        }
        return ['road'=>'','distance_km'=>0.0,'cost_eur'=>0.0,'status'=>'','source'=>''];
    }

    private static function stepCoordinates(array $step): array {
        $start = $step['startLocation']['latLng'] ?? [];
        $end = $step['endLocation']['latLng'] ?? [];
        $slat = is_numeric($start['latitude'] ?? null) ? (float)$start['latitude'] : null;
        $slng = is_numeric($start['longitude'] ?? null) ? (float)$start['longitude'] : null;
        $elat = is_numeric($end['latitude'] ?? null) ? (float)$end['latitude'] : null;
        $elng = is_numeric($end['longitude'] ?? null) ? (float)$end['longitude'] : null;
        return [
            'start_lat'=>$slat,'start_lng'=>$slng,'end_lat'=>$elat,'end_lng'=>$elng,
            'mid_lat'=>$slat !== null && $elat !== null ? (($slat + $elat) / 2) : null,
            'mid_lng'=>$slng !== null && $elng !== null ? (($slng + $elng) / 2) : null,
        ];
    }

    private static function classMultiplier(int $class): float {
        $multipliers = [1=>1.00, 2=>1.76, 3=>2.25, 4=>2.50];
        return (float)($multipliers[$class] ?? 1.00);
    }

    private static function fold(string $value): string {
        $value = strtoupper($value);
        return strtr($value, ['Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C']);
    }

    private static function roundCurrency(float $value, float $step): float {
        if ($step <= 0.0) return round($value, 2);
        return round(round($value / $step) * $step, 2);
    }
}
