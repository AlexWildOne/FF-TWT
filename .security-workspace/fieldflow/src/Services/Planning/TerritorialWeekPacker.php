<?php
namespace RoutesPro\Services\Planning;

if (!defined('ABSPATH')) exit;

/**
 * Constrói primeiro rotas territoriais dentro de cada semana e só depois
 * atribui essas rotas aos dias úteis. A periodicidade semanal/mensal mantém-se,
 * mas o weekday automático deixa de ser uma prisão criada loja a loja.
 */
class TerritorialWeekPacker {
    public const VERSION = 'territorial_week_packer_v3';

    public static function pack(array $tasksByWeek, array $weekBuckets, array $options = []): array {
        $stableWeekdayByStore = [];
        $packed = [];
        $diagnostics = [
            'version' => self::VERSION,
            'weeks_processed' => 0,
            'routes_created' => 0,
            'tasks_reassigned' => 0,
            'locality_bundles' => 0,
            'overflow_routes' => 0,
            'base_crossing_routes' => 0,
            'widest_bearing_span_deg' => 0.0,
            'week_details' => [],
        ];

        foreach ($weekBuckets as $weekKey => $bucket) {
            $weekKey = (string)$weekKey;
            $tasks = array_values((array)($tasksByWeek[$weekKey] ?? []));
            if (!$tasks) continue;
            $dates = array_values(array_filter(array_map('strval', (array)($bucket['dates'] ?? []))));
            sort($dates);
            if (!$dates) {
                $packed[$weekKey] = $tasks;
                continue;
            }

            $week = self::packWeek($tasks, $dates, $options, $stableWeekdayByStore);
            $packed[$weekKey] = array_values((array)($week['tasks'] ?? $tasks));
            $diag = (array)($week['diagnostics'] ?? []);
            $diagnostics['weeks_processed']++;
            $diagnostics['routes_created'] += (int)($diag['routes_created'] ?? 0);
            $diagnostics['tasks_reassigned'] += (int)($diag['tasks_reassigned'] ?? 0);
            $diagnostics['locality_bundles'] += (int)($diag['locality_bundles'] ?? 0);
            $diagnostics['overflow_routes'] += (int)($diag['overflow_routes'] ?? 0);
            $diagnostics['base_crossing_routes'] += (int)($diag['base_crossing_routes'] ?? 0);
            $diagnostics['widest_bearing_span_deg'] = max((float)$diagnostics['widest_bearing_span_deg'], (float)($diag['widest_bearing_span_deg'] ?? 0));
            $diagnostics['week_details'][$weekKey] = $diag;
        }

        foreach ($tasksByWeek as $weekKey => $tasks) {
            if (!isset($packed[$weekKey])) $packed[$weekKey] = array_values((array)$tasks);
        }

        return ['tasks_by_week' => $packed, 'diagnostics' => $diagnostics];
    }

    private static function packWeek(array $tasks, array $dates, array $options, array &$stableWeekdayByStore): array {
        $maxStops = max(1, min(20, (int)($options['max_stops_per_day'] ?? 12)));
        $targetStops = (int)($options['target_stops_per_day'] ?? 0);
        if ($targetStops <= 0) $targetStops = min($maxStops, 6);
        $targetStops = max(1, min($maxStops, $targetStops));
        $workMinutes = max(60, (int)($options['work_minutes'] ?? 480));
        $lunchMinutes = max(0, (int)($options['lunch_minutes'] ?? 60));
        $extraMinutes = !empty($options['allow_overtime']) ? max(0, min(150, (int)($options['overtime_extra_minutes'] ?? 0))) : 0;
        $hardLimit = min(720, $workMinutes + $extraMinutes);
        // Reserva operacional para trânsito, acessos, estacionamento e diferenças entre
        // o estimador rápido do packing e a matriz rodoviária final.
        $routingBuffer = max(25, (int)round($workMinutes * 0.08));
        $planningLimit = max(120, $hardLimit - $routingBuffer);
        $start = is_array($options['start_point'] ?? null) ? (array)$options['start_point'] : [];
        $end = is_array($options['end_point'] ?? null) ? (array)$options['end_point'] : $start;
        if (!self::hasCoordinates($start)) $start = self::centroid($tasks);
        if (!self::hasCoordinates($end)) $end = $start;
        $requestedTerritorialMode = (string)($options['territorial_coherence'] ?? 'strong');
        $territorialMode = in_array($requestedTerritorialMode, ['strong','balanced','distance_only'], true) ? $requestedTerritorialMode : 'strong';
        $sameBase = self::sameBase($start, $end);
        $neutralRadiusKm = 4.5;
        $maxBearingSpan = $territorialMode === 'distance_only' ? 360.0 : ($territorialMode === 'balanced' ? 155.0 : 118.0);

        $bundles = self::buildLocalityBundles($tasks, $start, $maxStops, $hardLimit, $lunchMinutes);
        $dateCount = max(1, count($dates));
        $bestRoutes = self::bestSweepPacking($bundles, $dateCount, [
            'max_stops' => $maxStops,
            'target_stops' => $targetStops,
            'hard_limit' => $planningLimit,
            'absolute_hard_limit' => $hardLimit,
            'routing_buffer_minutes' => $routingBuffer,
            'lunch_minutes' => $lunchMinutes,
            'start_point' => $start,
            'end_point' => $end,
            'available_dates' => $dates,
            'fast_mode' => !empty($options['_planning_fast']),
            'territorial_mode' => $territorialMode,
            'same_base' => $sameBase ? 1 : 0,
            'neutral_radius_km' => $neutralRadiusKm,
            'max_bearing_span_deg' => $maxBearingSpan,
        ]);

        $mapping = self::assignRoutesToDates($bestRoutes, $dates, $stableWeekdayByStore);
        $resultTasks = [];
        $reassigned = 0;
        $overflowRoutes = 0;
        $baseCrossingRoutes = 0;
        $widestBearingSpan = 0.0;
        foreach ($bestRoutes as $routeIndex => $route) {
            $date = (string)($mapping[$routeIndex] ?? ($dates[min($routeIndex, count($dates) - 1)] ?? ''));
            $weekday = $date !== '' && strtotime($date) ? (int)date('N', strtotime($date)) : 1;
            $metrics = self::routeMetrics((array)($route['items'] ?? []), $start, $end, $lunchMinutes);
            $isOverflow = (int)($metrics['stops'] ?? 0) > $maxStops || (float)($metrics['total_min'] ?? 0) > ($hardLimit + 0.5);
            if ($isOverflow) $overflowRoutes++;
            $bearingStats = self::routeBearingStats((array)($route['bundles'] ?? []), $neutralRadiusKm);
            $widestBearingSpan = max($widestBearingSpan, (float)$bearingStats['span_deg']);
            $baseCrossing = $sameBase && (int)$bearingStats['far_bundle_count'] >= 2 && (float)$bearingStats['span_deg'] >= 145.0;
            if ($baseCrossing) $baseCrossingRoutes++;
            foreach ((array)($route['items'] ?? []) as $task) {
                $task = (array)$task;
                $oldDate = (string)($task['target_date'] ?? '');
                if ($date !== '' && $oldDate !== $date) $reassigned++;
                $task['target_date'] = $date;
                $task['preferred_weekday'] = $weekday;
                $task['territorial_repacked'] = 1;
                $task['territorial_pack_version'] = self::VERSION;
                $task['territorial_route_index'] = $routeIndex + 1;
                $task['territorial_bearing_span_deg'] = round((float)$bearingStats['span_deg'], 1);
                $task['territorial_base_crossing'] = $baseCrossing ? 1 : 0;
                $task['cadence_label'] = self::weekdayLabel($weekday) . ' · rota territorial ' . ($routeIndex + 1);
                $storeId = (int)($task['id'] ?? 0);
                if ($storeId > 0) $stableWeekdayByStore[$storeId] = $weekday;
                $resultTasks[] = $task;
            }
        }

        usort($resultTasks, static function($a, $b) {
            $da = (string)($a['target_date'] ?? '');
            $db = (string)($b['target_date'] ?? '');
            if ($da !== $db) return strcmp($da, $db);
            $ra = (int)($a['territorial_route_index'] ?? 0);
            $rb = (int)($b['territorial_route_index'] ?? 0);
            if ($ra !== $rb) return $ra <=> $rb;
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return [
            'tasks' => $resultTasks,
            'diagnostics' => [
                'routes_created' => count($bestRoutes),
                'tasks_reassigned' => $reassigned,
                'locality_bundles' => count($bundles),
                'overflow_routes' => $overflowRoutes,
                'dates_available' => count($dates),
                'stops' => count($tasks),
                'routing_buffer_minutes' => $routingBuffer,
                'planning_limit_minutes' => $planningLimit,
                'hard_limit_minutes' => $hardLimit,
                'same_base' => $sameBase ? 1 : 0,
                'territorial_mode' => $territorialMode,
                'max_bearing_span_deg' => $maxBearingSpan,
                'widest_bearing_span_deg' => round($widestBearingSpan, 1),
                'base_crossing_routes' => $baseCrossingRoutes,
            ],
        ];
    }

    private static function buildLocalityBundles(array $tasks, array $base, int $maxStops, int $hardLimit, int $lunchMinutes): array {
        $groups = [];
        foreach ($tasks as $task) {
            $task = (array)$task;
            $key = self::localityKey($task);
            $fixedDate = (!empty($task['hard_fixed_date']) || !empty($task['locked_date'])) ? (string)($task['target_date'] ?? '') : '';
            if ($fixedDate !== '') $key .= '|fixed:' . $fixedDate;
            $groups[$key][] = $task;
        }

        $bundles = [];
        foreach ($groups as $key => $items) {
            usort($items, static function($a, $b) use ($base) {
                $da = self::distance($base, (array)$a);
                $db = self::distance($base, (array)$b);
                return $db <=> $da;
            });
            $chunk = [];
            $visit = 0;
            foreach ($items as $item) {
                $itemVisit = max(0, (int)($item['visit_duration_min'] ?? 45));
                $wouldStops = count($chunk) + 1;
                $wouldVisit = $visit + $itemVisit;
                $visitBudget = max(60, $hardLimit - $lunchMinutes - 70);
                if ($chunk && ($wouldStops > $maxStops || $wouldVisit > $visitBudget)) {
                    $bundles[] = self::makeBundle($key, $chunk, $base);
                    $chunk = [];
                    $visit = 0;
                }
                $chunk[] = $item;
                $visit += $itemVisit;
            }
            if ($chunk) $bundles[] = self::makeBundle($key, $chunk, $base);
        }
        return $bundles;
    }

    private static function makeBundle(string $key, array $items, array $base): array {
        $center = self::centroid($items);
        $fixedDates = [];
        foreach ($items as $item) {
            if ((!empty($item['hard_fixed_date']) || !empty($item['locked_date'])) && !empty($item['target_date'])) {
                $fixedDates[(string)$item['target_date']] = true;
            }
        }
        return [
            'key' => $key,
            'items' => array_values($items),
            'center' => $center,
            'bearing' => self::bearing($base, $center),
            'radial_km' => self::distance($base, $center),
            'stops' => count($items),
            'visit_min' => array_sum(array_map(static fn($it) => max(0, (int)($it['visit_duration_min'] ?? 45)), $items)),
            'fixed_dates' => array_keys($fixedDates),
        ];
    }

    private static function bestSweepPacking(array $bundles, int $dateCount, array $config): array {
        if (!$bundles) return [];
        usort($bundles, static function($a, $b) {
            $bearing = ((float)($a['bearing'] ?? 0)) <=> ((float)($b['bearing'] ?? 0));
            if ($bearing !== 0) return $bearing;
            return ((float)($b['radial_km'] ?? 0)) <=> ((float)($a['radial_km'] ?? 0));
        });

        $n = count($bundles);
        $maxStarts = !empty($config['fast_mode']) ? 6 : 12;
        if ($n <= $maxStarts) {
            $offsets = range(0, $n - 1);
        } else {
            $offsets = [];
            for ($i = 0; $i < $maxStarts; $i++) $offsets[] = (int)floor(($i * $n) / $maxStarts);
            $offsets = array_values(array_unique($offsets));
        }
        $best = [];
        $bestScore = PHP_FLOAT_MAX;
        foreach ([false, true] as $reverse) {
            $baseOrder = $reverse ? array_reverse($bundles) : $bundles;
            foreach ($offsets as $offset) {
                $order = array_merge(array_slice($baseOrder, $offset), array_slice($baseOrder, 0, $offset));
                $routes = self::packSweepOrder($order, $dateCount, $config);
                $score = self::packingScore($routes, $config);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = $routes;
                }
            }
        }
        return $best ?: self::packSweepOrder($bundles, $dateCount, $config);
    }

    private static function packSweepOrder(array $order, int $dateCount, array $config): array {
        $routes = [];
        foreach ($order as $bundle) {
            if (!$routes) {
                $routes[] = self::routeFromBundles([$bundle]);
                continue;
            }
            $last = count($routes) - 1;
            $proposedBundles = array_merge((array)($routes[$last]['bundles'] ?? []), [$bundle]);
            $proposed = self::routeFromBundles($proposedBundles);
            $metrics = self::routeMetrics((array)$proposed['items'], (array)$config['start_point'], (array)$config['end_point'], (int)$config['lunch_minutes']);
            $fixedDateConflict = count((array)($proposed['fixed_dates'] ?? [])) > 1;
            $dateFeasible = self::routeHasFeasibleDate($proposed, (array)($config['available_dates'] ?? []));
            $territorialFits = self::territorialRouteFits($proposed, $config);
            $hardFits = !$fixedDateConflict && $dateFeasible && $territorialFits && (int)$metrics['stops'] <= (int)$config['max_stops'] && (float)$metrics['total_min'] <= ((float)$config['hard_limit'] + 0.5);
            $targetExceeded = (int)$metrics['stops'] > (int)$config['target_stops'];
            $canOpen = count($routes) < $dateCount;
            $currentStops = count((array)($routes[$last]['items'] ?? []));
            if ($hardFits && (!$targetExceeded || !$canOpen || $currentStops < 2)) {
                $routes[$last] = $proposed;
            } elseif ($canOpen) {
                $routes[] = self::routeFromBundles([$bundle]);
            } else {
                $bestIdx = 0;
                $bestPenalty = PHP_FLOAT_MAX;
                foreach ($routes as $idx => $route) {
                    $candidate = self::routeFromBundles(array_merge((array)($route['bundles'] ?? []), [$bundle]));
                    $candidateMetrics = self::routeMetrics((array)$candidate['items'], (array)$config['start_point'], (array)$config['end_point'], (int)$config['lunch_minutes']);
                    $penalty = self::routePenalty($candidateMetrics, $candidate, $config);
                    if ($penalty < $bestPenalty) {
                        $bestPenalty = $penalty;
                        $bestIdx = $idx;
                    }
                }
                $routes[$bestIdx] = self::routeFromBundles(array_merge((array)($routes[$bestIdx]['bundles'] ?? []), [$bundle]));
            }
        }
        return array_values($routes);
    }

    private static function routeFromBundles(array $bundles): array {
        $items = [];
        foreach ($bundles as $bundle) {
            foreach ((array)($bundle['items'] ?? []) as $item) $items[] = (array)$item;
        }
        $fixedDates = [];
        foreach ($bundles as $bundle) {
            foreach ((array)($bundle['fixed_dates'] ?? []) as $date) $fixedDates[(string)$date] = true;
        }
        return ['bundles' => array_values($bundles), 'items' => $items, 'fixed_dates' => array_keys($fixedDates)];
    }

    private static function packingScore(array $routes, array $config): float {
        $score = 0.0;
        $loads = [];
        foreach ($routes as $route) {
            $metrics = self::routeMetrics((array)($route['items'] ?? []), (array)$config['start_point'], (array)$config['end_point'], (int)$config['lunch_minutes']);
            $score += self::routePenalty($metrics, $route, $config);
            $loads[] = (float)$metrics['total_min'];
        }
        if ($loads) {
            $avg = array_sum($loads) / count($loads);
            foreach ($loads as $load) $score += abs($load - $avg) * 0.8;
        }
        return $score;
    }

    private static function routePenalty(array $metrics, array $route, array $config): float {
        $overStops = max(0, (int)$metrics['stops'] - (int)$config['max_stops']);
        $overMinutes = max(0.0, (float)$metrics['total_min'] - (float)$config['hard_limit']);
        $targetOver = max(0, (int)$metrics['stops'] - (int)$config['target_stops']);
        $bearingStats = self::routeBearingStats((array)($route['bundles'] ?? []), (float)($config['neutral_radius_km'] ?? 4.5));
        $bearingSpan = (float)$bearingStats['span_deg'];
        $maxBearingSpan = (float)($config['max_bearing_span_deg'] ?? 360.0);
        $territorialExcess = max(0.0, $bearingSpan - $maxBearingSpan);
        $oppositePenalty = $territorialExcess > 0.0 ? 50000.0 + ($territorialExcess * 4500.0) : 0.0;
        if (!empty($config['same_base']) && (int)$bearingStats['far_bundle_count'] >= 2 && $bearingSpan >= 145.0) $oppositePenalty += 350000.0;
        $datePenalty = self::routeHasFeasibleDate($route, (array)($config['available_dates'] ?? [])) ? 0.0 : 100000000.0;
        return $datePenalty
            + $overStops * 100000.0
            + $overMinutes * 12000.0
            + $targetOver * 35.0
            + (float)$metrics['travel_min'] * 2.0
            + (float)$metrics['distance_km']
            + $oppositePenalty;
    }

    private static function routeHasFeasibleDate(array $route, array $dates): bool {
        if (!$dates) return false;
        foreach ($dates as $date) {
            $date = (string)$date;
            $ts = $date !== '' ? strtotime($date) : false;
            if (!$ts) continue;
            $weekday = (int)date('N', $ts);
            $valid = true;
            foreach ((array)($route['items'] ?? []) as $task) {
                $task = (array)$task;
                $fixedDate = (!empty($task['hard_fixed_date']) || !empty($task['locked_date'])) ? (string)($task['target_date'] ?? '') : '';
                if ($fixedDate !== '' && $fixedDate !== $date) { $valid = false; break; }
                if (in_array($weekday, self::weekdayList($task['blocked_weekdays'] ?? []), true)) { $valid = false; break; }
            }
            if ($valid) return true;
        }
        return false;
    }

    private static function assignRoutesToDates(array $routes, array $dates, array $stableWeekdayByStore): array {
        $routeCount = count($routes);
        if ($routeCount <= 0) return [];
        $permutations = [];
        self::datePermutations($dates, $routeCount, [], $permutations);
        $best = [];
        $bestScore = PHP_FLOAT_MAX;
        foreach ($permutations as $candidateDates) {
            $score = 0.0;
            foreach ($routes as $routeIndex => $route) {
                $date = (string)($candidateDates[$routeIndex] ?? '');
                $weekday = $date !== '' && strtotime($date) ? (int)date('N', strtotime($date)) : 1;
                foreach ((array)($route['items'] ?? []) as $task) {
                    $task = (array)$task;
                    $oldDate = (string)($task['target_date'] ?? '');
                    if ((!empty($task['hard_fixed_date']) || !empty($task['locked_date'])) && $oldDate !== '' && $oldDate !== $date) $score += 10000000.0;
                    $blocked = self::weekdayList($task['blocked_weekdays'] ?? []);
                    if (in_array($weekday, $blocked, true)) $score += 1000000.0;
                    $preferred = self::weekdayList($task['preferred_weekdays'] ?? []);
                    if ($preferred) $score += in_array($weekday, $preferred, true) ? -80.0 : 45.0;
                    $storeId = (int)($task['id'] ?? 0);
                    if ($storeId > 0 && isset($stableWeekdayByStore[$storeId])) {
                        $score += abs((int)$stableWeekdayByStore[$storeId] - $weekday) * 18.0;
                        if ((int)$stableWeekdayByStore[$storeId] === $weekday) $score -= 65.0;
                    }
                    $generatedWeekday = (int)($task['preferred_weekday'] ?? 0);
                    if ($generatedWeekday >= 1 && $generatedWeekday <= 5) $score += abs($generatedWeekday - $weekday) * 1.5;
                }
            }
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $candidateDates;
            }
        }
        return $best ?: array_slice($dates, 0, $routeCount);
    }

    private static function datePermutations(array $dates, int $needed, array $prefix, array &$out): void {
        if (count($prefix) === $needed) {
            $out[] = $prefix;
            return;
        }
        foreach ($dates as $idx => $date) {
            $remaining = $dates;
            array_splice($remaining, $idx, 1);
            $next = $prefix;
            $next[] = $date;
            self::datePermutations($remaining, $needed, $next, $out);
        }
    }

    private static function routeMetrics(array $items, array $start, array $end, int $lunchMinutes = 60): array {
        static $memo = [];
        $parts = [];
        foreach ($items as $item) {
            $item = (array)$item;
            $parts[] = [(int)($item['id'] ?? 0), (string)($item['uid'] ?? ''), $item['lat'] ?? null, $item['lng'] ?? null, (int)($item['visit_duration_min'] ?? 45)];
        }
        sort($parts);
        $encodedMemo = function_exists('wp_json_encode') ? wp_json_encode([$parts, $start['lat'] ?? null, $start['lng'] ?? null, $end['lat'] ?? null, $end['lng'] ?? null, $lunchMinutes]) : json_encode([$parts, $start['lat'] ?? null, $start['lng'] ?? null, $end['lat'] ?? null, $end['lng'] ?? null, $lunchMinutes]);
        $memoKey = md5((string)$encodedMemo);
        if (isset($memo[$memoKey])) return $memo[$memoKey];
        $items = self::nearestNeighbour($items, $start);
        $distance = 0.0;
        $previous = self::hasCoordinates($start) ? $start : ($items[0] ?? []);
        $segments = 0;
        foreach ($items as $item) {
            if (self::hasCoordinates($previous) && self::hasCoordinates($item)) {
                $distance += self::distance($previous, $item) * 1.22;
                $segments++;
            }
            $previous = $item;
        }
        if ($items && self::hasCoordinates($previous) && self::hasCoordinates($end)) {
            $distance += self::distance($previous, $end) * 1.22;
            $segments++;
        }
        $travel = ($distance / 58.0) * 60.0 + ($segments * 3.5);
        $visit = array_sum(array_map(static fn($it) => max(0, (int)($it['visit_duration_min'] ?? 45)), $items));
        $lunch = $items ? max(0, $lunchMinutes) : 0;
        $result = [
            'stops' => count($items),
            'distance_km' => round($distance, 2),
            'travel_min' => round($travel, 1),
            'visit_min' => $visit,
            'total_min' => round($travel + $visit + $lunch, 1),
        ];
        if (count($memo) > 2400) array_shift($memo);
        $memo[$memoKey] = $result;
        return $result;
    }

    private static function nearestNeighbour(array $items, array $start): array {
        $remaining = array_values($items);
        $ordered = [];
        $current = $start;
        while ($remaining) {
            $bestIndex = 0;
            $best = PHP_FLOAT_MAX;
            foreach ($remaining as $idx => $item) {
                $km = self::distance($current, (array)$item);
                if ($km < $best) {
                    $best = $km;
                    $bestIndex = $idx;
                }
            }
            $current = (array)$remaining[$bestIndex];
            $ordered[] = $current;
            array_splice($remaining, $bestIndex, 1);
        }
        return $ordered;
    }

    private static function bundleBearingSpan(array $bundles, float $neutralRadiusKm = 0.0): float {
        $angles = [];
        foreach ($bundles as $bundle) {
            if ((float)($bundle['radial_km'] ?? 0.0) <= $neutralRadiusKm) continue;
            $angles[] = fmod(((float)($bundle['bearing'] ?? 0)) + 360.0, 360.0);
        }
        $n = count($angles);
        if ($n <= 1) return 0.0;
        sort($angles);
        $largestGap = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $next = $i === $n - 1 ? $angles[0] + 360.0 : $angles[$i + 1];
            $largestGap = max($largestGap, $next - $angles[$i]);
        }
        return 360.0 - $largestGap;
    }


    private static function routeBearingStats(array $bundles, float $neutralRadiusKm = 4.5): array {
        $far = [];
        foreach ($bundles as $bundle) {
            if ((float)($bundle['radial_km'] ?? 0.0) <= $neutralRadiusKm) continue;
            $far[] = $bundle;
        }
        return [
            'span_deg' => self::bundleBearingSpan($far, 0.0),
            'far_bundle_count' => count($far),
        ];
    }

    private static function territorialRouteFits(array $route, array $config): bool {
        if ((string)($config['territorial_mode'] ?? 'strong') === 'distance_only' || empty($config['same_base'])) return true;
        $stats = self::routeBearingStats((array)($route['bundles'] ?? []), (float)($config['neutral_radius_km'] ?? 4.5));
        if ((int)$stats['far_bundle_count'] <= 1) return true;
        return (float)$stats['span_deg'] <= ((float)($config['max_bearing_span_deg'] ?? 118.0) + 0.01);
    }

    private static function sameBase(array $start, array $end): bool {
        if (class_exists('\RoutesPro\Services\Planning\RouteCalculator')) return RouteCalculator::sameBase($start, $end);
        $startAddress = self::normalizeText((string)($start['address'] ?? ''));
        $endAddress = self::normalizeText((string)($end['address'] ?? ''));
        if ($startAddress !== '' && $startAddress === $endAddress) return true;
        return self::hasCoordinates($start) && self::hasCoordinates($end) && self::distance($start, $end) <= 2.0;
    }

    private static function localityKey(array $task): string {
        foreach (['city', 'county', 'parish', 'district'] as $field) {
            $value = self::normalizeText((string)($task[$field] ?? ''));
            if ($value !== '') return $field . ':' . $value;
        }
        if (self::hasCoordinates($task)) {
            return 'geo:' . round((float)$task['lat'], 1) . ':' . round((float)$task['lng'], 1);
        }
        return 'store:' . (int)($task['id'] ?? 0);
    }

    private static function normalizeText(string $value): string {
        $value = trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
        if (function_exists('remove_accents')) $value = remove_accents($value);
        elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted)) $value = $converted;
        }
        return trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-');
    }

    private static function weekdayList($value): array {
        if (is_array($value)) $parts = $value;
        else $parts = preg_split('/[^0-9]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter(array_map('intval', $parts), static fn($n) => $n >= 1 && $n <= 7)));
    }

    private static function weekdayLabel(int $weekday): string {
        $labels = [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira'];
        return $labels[max(1, min(5, $weekday))] ?? 'Dia útil';
    }

    private static function centroid(array $points): array {
        $lat = 0.0;
        $lng = 0.0;
        $count = 0;
        foreach ($points as $point) {
            $point = (array)$point;
            if (!self::hasCoordinates($point)) continue;
            $lat += (float)$point['lat'];
            $lng += (float)$point['lng'];
            $count++;
        }
        return $count > 0 ? ['lat' => $lat / $count, 'lng' => $lng / $count] : [];
    }

    private static function hasCoordinates(array $point): bool {
        return isset($point['lat'], $point['lng']) && is_numeric($point['lat']) && is_numeric($point['lng'])
            && is_finite((float)$point['lat']) && is_finite((float)$point['lng']);
    }

    private static function distance(array $a, array $b): float {
        if (!self::hasCoordinates($a) || !self::hasCoordinates($b)) return 9999.0;
        $earth = 6371.0088;
        $lat1 = deg2rad((float)$a['lat']);
        $lat2 = deg2rad((float)$b['lat']);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float)$b['lng'] - (float)$a['lng']);
        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($h), sqrt(max(0.0, 1.0 - $h)));
    }

    private static function bearing(array $a, array $b): float {
        if (!self::hasCoordinates($a) || !self::hasCoordinates($b)) return 0.0;
        $lat1 = deg2rad((float)$a['lat']);
        $lat2 = deg2rad((float)$b['lat']);
        $dLng = deg2rad((float)$b['lng'] - (float)$a['lng']);
        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);
        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }
}
