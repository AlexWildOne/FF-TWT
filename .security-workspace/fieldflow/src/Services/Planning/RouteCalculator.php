<?php
namespace RoutesPro\Services\Planning;

if (!defined('ABSPATH')) exit;

class RouteCalculator {
    private array $matrix = [];
    private string $territorialMode = 'strong';

    public function __construct(array $matrix = [], string $territorialMode = 'strong') {
        $this->matrix = $matrix;
        $this->territorialMode = in_array($territorialMode, ['strong', 'balanced', 'distance_only'], true) ? $territorialMode : 'strong';
    }

    public function reorderStops(array $items, array $startPoint = [], array $endPoint = [], array $matrix = []): array {
        if ($matrix) $this->matrix = $matrix;
        $resolved = array_values($items);
        if (count($resolved) <= 1) return $resolved;

        $start = $this->normalizePoint($startPoint, '__start__');
        $end = $this->normalizePoint($endPoint, '__end__');
        $candidates = [$resolved];

        $nearest = $this->nearestNeighbor($resolved, $start, $end);
        if ($nearest) $candidates[] = $this->twoOpt($nearest, $start, $end);

        $regret = $this->regretInsertion($resolved, $start, $end);
        if ($regret) $candidates[] = $this->twoOpt($regret, $start, $end);

        // When departure and arrival are the same base, a linear corridor has no
        // axis. Territorial sweep becomes the primary candidate and closes one
        // direction/locality block before entering another.
        $territorial = $this->territorialSweepOrder($resolved, $start, $end, false);
        if ($territorial) $candidates[] = $this->twoOpt($territorial, $start, $end);
        $territorialReverse = $this->territorialSweepOrder($resolved, $start, $end, true);
        if ($territorialReverse) $candidates[] = $this->twoOpt($territorialReverse, $start, $end);

        $projection = $this->projectionOrder($resolved, $start, $end, false);
        if ($projection) $candidates[] = $this->twoOpt($projection, $start, $end);
        $projectionReverse = $this->projectionOrder($resolved, $start, $end, true);
        if ($projectionReverse) $candidates[] = $this->twoOpt($projectionReverse, $start, $end);

        $best = $candidates[0];
        $bestScore = $this->routeScore($best, $start, $end);
        $bestTravel = $this->pathCost($best, $start, $end);
        foreach ($candidates as $candidate) {
            $score = $this->routeScore($candidate, $start, $end);
            $travel = $this->pathCost($candidate, $start, $end);
            if ($score + 0.0001 < $bestScore || (abs($score - $bestScore) <= 0.0001 && $travel + 0.0001 < $bestTravel)) {
                $best = $candidate;
                $bestScore = $score;
                $bestTravel = $travel;
            }
        }
        return array_values($best);
    }

    public function pathMetrics(array $items, array $startPoint = [], array $endPoint = [], array $matrix = []): array {
        if ($matrix) $this->matrix = $matrix;
        if (class_exists('\\RoutesPro\\Services\\Planning\\RoutingMatrixService')) {
            return RoutingMatrixService::pathMetrics($items, $startPoint, $endPoint, $this->matrix);
        }
        return ['distance_km'=>$this->pathCost($items, $startPoint, $endPoint),'travel_min'=>null];
    }

    public function scoreRoute(array $items, array $startPoint = [], array $endPoint = [], array $matrix = []): float {
        if ($matrix) $this->matrix = $matrix;
        $start = $this->normalizePoint($startPoint, '__start__');
        $end = $this->normalizePoint($endPoint, '__end__');
        return $this->routeScore(array_values($items), $start, $end);
    }

    /**
     * Territorial diagnostic shared by planning, BO, front and exports.
     */
    public static function territoryMetrics(array $items, array $startPoint = [], array $endPoint = []): array {
        $items = array_values($items);
        $sameBase = self::sameBase($startPoint, $endPoint);
        $directionSequence = [];
        $localitySequence = [];
        $directionCounts = [];
        $localityCounts = [];
        $farBearings = [];
        $farRadii = [];
        $neutralRadiusKm = 4.5;

        foreach ($items as $item) {
            $direction = self::directionKey((array)$item, $startPoint);
            $locality = self::localityKey((array)$item);
            if ($direction !== 'base' && $direction !== '') {
                $directionSequence[] = $direction;
                $directionCounts[$direction] = ($directionCounts[$direction] ?? 0) + 1;
            }
            if ($locality !== '') {
                $localitySequence[] = $locality;
                $localityCounts[$locality] = ($localityCounts[$locality] ?? 0) + 1;
            }
            if (is_numeric($item['lat'] ?? null) && is_numeric($item['lng'] ?? null) && is_numeric($startPoint['lat'] ?? null) && is_numeric($startPoint['lng'] ?? null)) {
                $radiusKm = self::geoDistance((float)$startPoint['lat'], (float)$startPoint['lng'], (float)$item['lat'], (float)$item['lng']);
                if ($radiusKm > $neutralRadiusKm) {
                    $farBearings[] = self::geoBearing((float)$startPoint['lat'], (float)$startPoint['lng'], (float)$item['lat'], (float)$item['lng']);
                    $farRadii[] = $radiusKm;
                }
            }
        }

        $directionRuns = self::sequenceRuns($directionSequence);
        $localityRuns = self::sequenceRuns($localitySequence);
        $directionSwitches = max(0, count($directionRuns) - 1);
        $directionReentries = max(0, count($directionRuns) - count(array_unique($directionRuns)));
        $localityReentries = max(0, count($localityRuns) - count(array_unique($localityRuns)));
        $bearingSpan = self::circularBearingSpan($farBearings);
        $baseCrossingRisk = $sameBase && count($farBearings) >= 2 && $bearingSpan >= 145.0;
        $oppositeMix = (isset($directionCounts['norte']) && isset($directionCounts['sul']))
            || (isset($directionCounts['este']) && isset($directionCounts['oeste']))
            || $baseCrossingRisk;
        $oppositeSwitches = 0;
        for ($i = 1; $i < count($directionRuns); $i++) {
            if (self::oppositeDirections((string)$directionRuns[$i - 1], (string)$directionRuns[$i])) $oppositeSwitches++;
        }
        arsort($directionCounts);
        $dominant = $directionCounts ? (string)array_key_first($directionCounts) : 'base';
        $dominantCount = $directionCounts ? (int)reset($directionCounts) : 0;
        $directionTotal = array_sum($directionCounts);
        $dominantShare = $directionTotal > 0 ? round(($dominantCount / $directionTotal) * 100, 1) : 100.0;

        $penalty = ($directionReentries * 30.0) + ($localityReentries * 24.0) + ($oppositeSwitches * 20.0);
        if ($oppositeMix) $penalty += $sameBase ? 38.0 : 12.0;
        if ($sameBase && $bearingSpan > 110.0) $penalty += min(42.0, ($bearingSpan - 110.0) * 0.55);
        if ($baseCrossingRisk) $penalty += 18.0;
        if (count($directionCounts) > 2) $penalty += (count($directionCounts) - 2) * 9.0;
        $score = max(0.0, min(100.0, 100.0 - $penalty));
        $status = $baseCrossingRisk ? 'fragmented' : ($score >= 86.0 ? 'coherent' : ($score >= 66.0 ? 'acceptable' : 'fragmented'));

        return [
            'same_base' => $sameBase ? 1 : 0,
            'dominant_direction' => $dominant,
            'dominant_direction_label' => self::directionLabel($dominant),
            'dominant_direction_share_pct' => $dominantShare,
            'direction_count' => count($directionCounts),
            'direction_switches' => $directionSwitches,
            'direction_reentries' => $directionReentries,
            'opposite_direction_mix' => $oppositeMix ? 1 : 0,
            'opposite_direction_switches' => $oppositeSwitches,
            'locality_count' => count($localityCounts),
            'locality_reentries' => $localityReentries,
            'bearing_span_deg' => round($bearingSpan, 1),
            'far_stop_count' => count($farBearings),
            'neutral_radius_km' => $neutralRadiusKm,
            'max_radial_km' => $farRadii ? round(max($farRadii), 1) : 0.0,
            'base_crossing_risk' => $baseCrossingRisk ? 1 : 0,
            'route_cone_label' => $baseCrossingRisk ? 'Cruza a base' : ($bearingSpan <= 110.0 ? 'Corredor focado' : 'Corredor amplo'),
            'coherence_score' => round($score, 1),
            'coherence_status' => $status,
            'coherence_label' => $status === 'coherent' ? 'Coerente' : ($status === 'acceptable' ? 'Aceitável' : 'Fragmentada'),
            'direction_counts' => $directionCounts,
            'locality_counts' => $localityCounts,
            'direction_sequence' => $directionRuns,
            'locality_sequence' => $localityRuns,
        ];
    }

    private function projectionOrder(array $items, array $start, array $end, bool $reverse = false): array {
        if (!$items) return [];
        if (!is_numeric($start['lat'] ?? null) || !is_numeric($start['lng'] ?? null) || !is_numeric($end['lat'] ?? null) || !is_numeric($end['lng'] ?? null)) return [];
        $sx = (float)$start['lng'];
        $sy = (float)$start['lat'];
        $ex = (float)$end['lng'];
        $ey = (float)$end['lat'];
        $vx = $ex - $sx;
        $vy = $ey - $sy;
        $len2 = ($vx * $vx) + ($vy * $vy);
        if ($len2 <= 0.000001) return [];
        $ordered = array_values($items);
        usort($ordered, function($a, $b) use ($sx, $sy, $vx, $vy, $len2, $reverse) {
            $alng = is_numeric($a['lng'] ?? null) ? (float)$a['lng'] : $sx;
            $alat = is_numeric($a['lat'] ?? null) ? (float)$a['lat'] : $sy;
            $blng = is_numeric($b['lng'] ?? null) ? (float)$b['lng'] : $sx;
            $blat = is_numeric($b['lat'] ?? null) ? (float)$b['lat'] : $sy;
            $ta = (($alng - $sx) * $vx + ($alat - $sy) * $vy) / $len2;
            $tb = (($blng - $sx) * $vx + ($blat - $sy) * $vy) / $len2;
            if (abs($ta - $tb) > 0.0001) return $reverse ? ($tb <=> $ta) : ($ta <=> $tb);
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return $ordered;
    }

    /**
     * Builds a route in territorial blocks. Once a direction and locality are
     * entered, every store in that block is completed before the next block.
     */
    private function territorialSweepOrder(array $items, array $start, array $end, bool $reverse = false): array {
        if (!$items || !is_numeric($start['lat'] ?? null) || !is_numeric($start['lng'] ?? null)) return [];
        $groups = [];
        $unknownIndex = 0;
        foreach ($items as $item) {
            $direction = self::directionKey((array)$item, $start);
            $locality = self::localityKey((array)$item);
            if ($locality === '') $locality = 'sem_localidade_' . (++$unknownIndex);
            $groups[$direction][$locality][] = (array)$item;
        }
        if (!$groups) return [];

        $directionOrder = array_keys($groups);
        usort($directionOrder, function($a, $b) use ($groups, $start) {
            $aCost = $this->minimumEdgeToGroup($start, (array)$groups[$a]);
            $bCost = $this->minimumEdgeToGroup($start, (array)$groups[$b]);
            return $aCost <=> $bCost;
        });
        if ($reverse) $directionOrder = array_reverse($directionOrder);

        $route = [];
        $current = $start;
        foreach ($directionOrder as $direction) {
            $localityGroups = (array)$groups[$direction];
            while ($localityGroups) {
                $bestLocality = null;
                $bestCost = PHP_FLOAT_MAX;
                foreach ($localityGroups as $locality => $block) {
                    foreach ((array)$block as $item) {
                        $cost = $this->edgeCost($current, (array)$item);
                        if ($cost < $bestCost) {
                            $bestCost = $cost;
                            $bestLocality = (string)$locality;
                        }
                    }
                }
                if ($bestLocality === null) break;
                $block = array_values((array)$localityGroups[$bestLocality]);
                unset($localityGroups[$bestLocality]);
                while ($block) {
                    $bestIndex = 0;
                    $bestItemCost = PHP_FLOAT_MAX;
                    foreach ($block as $idx => $item) {
                        $cost = $this->edgeCost($current, (array)$item);
                        if ($cost < $bestItemCost) {
                            $bestItemCost = $cost;
                            $bestIndex = $idx;
                        }
                    }
                    $chosen = (array)$block[$bestIndex];
                    $route[] = $chosen;
                    $current = $chosen;
                    array_splice($block, $bestIndex, 1);
                }
            }
        }
        return $route;
    }

    private function minimumEdgeToGroup(array $from, array $localityGroups): float {
        $best = PHP_FLOAT_MAX;
        foreach ($localityGroups as $block) {
            foreach ((array)$block as $item) $best = min($best, $this->edgeCost($from, (array)$item));
        }
        return $best;
    }

    private function nearestNeighbor(array $items, array $start, array $end): array {
        if (!$items) return [];
        $pool = array_values($items);
        $route = [];
        $current = $start;
        while ($pool) {
            $closestIndex = 0;
            $closestCost = PHP_FLOAT_MAX;
            foreach ($pool as $i => $item) {
                $cost = $this->edgeCost($current, $item);
                if (count($pool) === 1) $cost += $this->edgeCost($item, $end);
                if ($cost < $closestCost) {
                    $closestCost = $cost;
                    $closestIndex = $i;
                }
            }
            $route[] = $pool[$closestIndex];
            $current = $pool[$closestIndex];
            array_splice($pool, $closestIndex, 1);
        }
        return $route;
    }

    private function regretInsertion(array $items, array $start, array $end): array {
        $remaining = array_values($items);
        $route = [];
        while ($remaining) {
            $chosenItemIndex = 0;
            $chosenPosition = 0;
            $chosenRegret = -PHP_FLOAT_MAX;
            $chosenDelta = PHP_FLOAT_MAX;
            foreach ($remaining as $itemIndex => $item) {
                $deltas = [];
                $positions = count($route) + 1;
                for ($position = 0; $position < $positions; $position++) {
                    $prev = $position === 0 ? $start : $route[$position - 1];
                    $next = $position === count($route) ? $end : $route[$position];
                    $candidate = $route;
                    array_splice($candidate, $position, 0, [$item]);
                    $delta = $this->edgeCost($prev, $item) + $this->edgeCost($item, $next) - $this->edgeCost($prev, $next);
                    $delta += $this->sequencePenalty($candidate, $start, $end) - $this->sequencePenalty($route, $start, $end);
                    $deltas[] = ['position'=>$position,'delta'=>$delta];
                }
                usort($deltas, static fn($a, $b) => $a['delta'] <=> $b['delta']);
                $best = $deltas[0] ?? ['position'=>0,'delta'=>PHP_FLOAT_MAX];
                $second = $deltas[1] ?? ['delta'=>$best['delta']];
                $regret = (float)$second['delta'] - (float)$best['delta'];
                if ($regret > $chosenRegret + 0.0001 || (abs($regret - $chosenRegret) <= 0.0001 && (float)$best['delta'] < $chosenDelta)) {
                    $chosenRegret = $regret;
                    $chosenDelta = (float)$best['delta'];
                    $chosenItemIndex = $itemIndex;
                    $chosenPosition = (int)$best['position'];
                }
            }
            $item = $remaining[$chosenItemIndex];
            array_splice($route, $chosenPosition, 0, [$item]);
            array_splice($remaining, $chosenItemIndex, 1);
        }
        return $route;
    }

    private function twoOpt(array $route, array $start, array $end): array {
        $n = count($route);
        if ($n < 4) return $route;
        $guardLimit = $n > 14 ? 4 : 8;
        $improved = true;
        $guard = 0;
        $currentCost = $this->routeScore($route, $start, $end);
        while ($improved && $guard < $guardLimit) {
            $improved = false;
            $guard++;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidate = $route;
                    $segment = array_reverse(array_slice($candidate, $i, $j - $i + 1));
                    array_splice($candidate, $i, $j - $i + 1, $segment);
                    $candidateCost = $this->routeScore($candidate, $start, $end);
                    if ($candidateCost + 0.0001 < $currentCost) {
                        $route = $candidate;
                        $currentCost = $candidateCost;
                        $improved = true;
                    }
                }
            }
        }
        return $route;
    }

    private function routeScore(array $route, array $start, array $end): float {
        return $this->pathCost($route, $start, $end) + $this->sequencePenalty($route, $start, $end);
    }

    private function sequencePenalty(array $route, array $start, array $end): float {
        if (!$route || $this->territorialMode === 'distance_only') return 0.0;
        $metrics = self::territoryMetrics($route, $start, $end);
        $sameBase = !empty($metrics['same_base']);
        $penalty = 0.0;
        $penalty += (float)($metrics['locality_reentries'] ?? 0) * ($sameBase ? 170.0 : 85.0);
        $penalty += (float)($metrics['direction_reentries'] ?? 0) * ($sameBase ? 250.0 : 125.0);
        $penalty += (float)($metrics['opposite_direction_switches'] ?? 0) * ($sameBase ? 85.0 : 40.0);
        $penalty += max(0, (int)($metrics['direction_switches'] ?? 0) - 1) * ($sameBase ? 45.0 : 20.0);
        if (!empty($metrics['opposite_direction_mix'])) $penalty += $sameBase ? 260.0 : 35.0;
        if (!empty($metrics['base_crossing_risk'])) $penalty += 900.0;
        if ($sameBase && (float)($metrics['bearing_span_deg'] ?? 0) > 110.0) $penalty += ((float)$metrics['bearing_span_deg'] - 110.0) * 8.0;
        return $penalty * ($this->territorialMode === 'balanced' ? 0.58 : 1.0);
    }

    private function pathCost(array $route, array $start, array $end): float {
        if (!$route) return 0.0;
        $total = 0.0;
        $prev = $this->normalizePoint($start, '__start__');
        if (!$this->hasRoutingData($prev)) $prev = $this->normalizePoint((array)$route[0], 'first');
        foreach ($route as $item) {
            $total += $this->edgeCost($prev, (array)$item);
            $prev = (array)$item;
        }
        $end = $this->normalizePoint($end, '__end__');
        if ($this->hasRoutingData($end)) $total += $this->edgeCost($prev, $end);
        return $total;
    }

    private function edgeCost(array $a, array $b): float {
        if (class_exists('\\RoutesPro\\Services\\Planning\\RoutingMatrixService')) {
            $edge = RoutingMatrixService::edge($this->matrix, $a, $b);
            if (($edge['status'] ?? '') === 'impossible') return 1.0e12;
            if (is_numeric($edge['travel_min'] ?? null)) return (float)$edge['travel_min'];
            if (is_numeric($edge['distance_km'] ?? null)) return (float)$edge['distance_km'] * 1.7;
        }
        $distance = $this->haversine($a, $b);
        return $distance >= 999999.0 ? $distance : $distance * 1.7;
    }

    private function normalizePoint(array $point, string $fallback): array {
        if (class_exists('\\RoutesPro\\Services\\Planning\\RoutingMatrixService')) {
            if (in_array($fallback, ['__start__', '__end__'], true)) $point['_matrix_key'] = $fallback;
            else $point['_matrix_key'] = RoutingMatrixService::pointKey($point, $fallback);
        }
        $point['lat'] = is_numeric($point['lat'] ?? null) ? (float)$point['lat'] : null;
        $point['lng'] = is_numeric($point['lng'] ?? null) ? (float)$point['lng'] : null;
        return $point;
    }

    private function hasRoutingData(array $point): bool {
        return (is_numeric($point['lat'] ?? null) && is_numeric($point['lng'] ?? null)) || trim((string)($point['address'] ?? '')) !== '';
    }

    public static function sameBase(array $start, array $end): bool {
        $startAddress = self::normalizeText((string)($start['address'] ?? ''));
        $endAddress = self::normalizeText((string)($end['address'] ?? ''));
        if ($startAddress !== '' && $endAddress !== '' && $startAddress === $endAddress) return true;
        if (!is_numeric($start['lat'] ?? null) || !is_numeric($start['lng'] ?? null) || !is_numeric($end['lat'] ?? null) || !is_numeric($end['lng'] ?? null)) return false;
        return self::geoDistance((float)$start['lat'], (float)$start['lng'], (float)$end['lat'], (float)$end['lng']) <= 2.0;
    }

    private static function directionKey(array $item, array $base): string {
        if (!is_numeric($item['lat'] ?? null) || !is_numeric($item['lng'] ?? null) || !is_numeric($base['lat'] ?? null) || !is_numeric($base['lng'] ?? null)) return 'sem_direcao';
        $baseLat = (float)$base['lat'];
        $dy = ((float)$item['lat'] - $baseLat) * 111.2;
        $dx = ((float)$item['lng'] - (float)$base['lng']) * 111.2 * max(0.2, cos(deg2rad($baseLat)));
        $radius = sqrt(($dx * $dx) + ($dy * $dy));
        if ($radius <= 2.0) return 'base';
        // Broad north/south sectors fit the operational use case better than
        // strict quadrants and stop nearby diagonal stores splitting the day.
        if (abs($dy) >= abs($dx) * 0.55) return $dy >= 0 ? 'norte' : 'sul';
        return $dx >= 0 ? 'este' : 'oeste';
    }

    private static function localityKey(array $item): string {
        foreach (['city', 'locality', 'county', 'municipality', 'concelho', 'district'] as $field) {
            $value = self::normalizeText((string)($item[$field] ?? ''));
            if ($value !== '') return $field . '|' . $value;
        }
        if (is_numeric($item['lat'] ?? null) && is_numeric($item['lng'] ?? null)) {
            return 'geo|' . number_format((float)$item['lat'], 2, '.', '') . '|' . number_format((float)$item['lng'], 2, '.', '');
        }
        $address = self::normalizeText((string)($item['address'] ?? ''));
        return $address !== '' ? 'address|' . substr($address, 0, 48) : '';
    }

    private static function normalizeText(string $value): string {
        $value = strtolower(trim($value));
        if ($value === '') return '';
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') $value = strtolower($converted);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    private static function sequenceRuns(array $sequence): array {
        $runs = [];
        $last = null;
        foreach ($sequence as $value) {
            $value = (string)$value;
            if ($value === '' || $value === 'base') continue;
            if ($last !== $value) {
                $runs[] = $value;
                $last = $value;
            }
        }
        return $runs;
    }

    private static function oppositeDirections(string $a, string $b): bool {
        return ($a === 'norte' && $b === 'sul') || ($a === 'sul' && $b === 'norte')
            || ($a === 'este' && $b === 'oeste') || ($a === 'oeste' && $b === 'este');
    }

    private static function directionLabel(string $direction): string {
        return [
            'norte' => 'Norte',
            'sul' => 'Sul',
            'este' => 'Este',
            'oeste' => 'Oeste',
            'base' => 'Zona da base',
            'sem_direcao' => 'Sem direção',
        ][$direction] ?? ucfirst(str_replace('_', ' ', $direction));
    }


    private static function geoBearing(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $a = deg2rad($lat1);
        $b = deg2rad($lat2);
        $dLng = deg2rad($lng2 - $lng1);
        $y = sin($dLng) * cos($b);
        $x = cos($a) * sin($b) - sin($a) * cos($b) * cos($dLng);
        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    private static function circularBearingSpan(array $angles): float {
        $angles = array_values(array_filter(array_map(static fn($v) => is_numeric($v) ? fmod(((float)$v) + 360.0, 360.0) : null, $angles), static fn($v) => $v !== null));
        $n = count($angles);
        if ($n <= 1) return 0.0;
        sort($angles, SORT_NUMERIC);
        $largestGap = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $next = $i === $n - 1 ? $angles[0] + 360.0 : $angles[$i + 1];
            $largestGap = max($largestGap, $next - $angles[$i]);
        }
        return max(0.0, 360.0 - $largestGap);
    }

    private static function geoDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $earth * asin(min(1.0, sqrt($a)));
    }

    private function haversine(array $a, array $b): float {
        $alat = is_numeric($a['lat'] ?? null) ? (float)$a['lat'] : null;
        $alng = is_numeric($a['lng'] ?? null) ? (float)$a['lng'] : null;
        $blat = is_numeric($b['lat'] ?? null) ? (float)$b['lat'] : null;
        $blng = is_numeric($b['lng'] ?? null) ? (float)$b['lng'] : null;
        if ($alat === null || $alng === null || $blat === null || $blng === null) return 999999.0;
        return self::geoDistance($alat, $alng, $blat, $blng);
    }
}
