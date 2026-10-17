<?php
namespace RoutesPro\Services\Planning;

if (!defined('ABSPATH')) exit;

class PlanQualityScorer {
    public static function score(array $plan): array {
        $days = (array)($plan['days'] ?? []);
        $unassigned = (array)($plan['unassigned'] ?? []);
        $warnings = [];
        $totalStops = 0;
        $warningCount = 0;
        $overtimeMin = 0;
        $totalDistance = 0.0;
        $totalTravel = 0.0;
        $resolvedSegments = 0;
        $totalSegments = 0;
        $longLegDays = 0;
        $territoryScoreTotal = 0.0;
        $territoryDayCount = 0;
        $fragmentedTerritoryDays = 0;
        $oppositeMixDays = 0;
        $localityReentries = 0;
        $baseCrossingDays = 0;
        foreach ($days as $day) {
            $items = (array)($day['items'] ?? []);
            $totalStops += count($items);
            $overtimeMin += max(0, (int)($day['overtime_min'] ?? 0));
            $metrics = is_array($day['routing_metrics'] ?? null) ? (array)$day['routing_metrics'] : [];
            $dayDistance = is_numeric($metrics['known_distance_km'] ?? null) ? (float)$metrics['known_distance_km'] : (is_numeric($day['distance_km'] ?? null) ? (float)$day['distance_km'] : 0.0);
            $dayTravel = is_numeric($metrics['known_travel_min'] ?? null) ? (float)$metrics['known_travel_min'] : (is_numeric($day['travel_min'] ?? null) ? (float)$day['travel_min'] : 0.0);
            $totalDistance += max(0.0, $dayDistance);
            $totalTravel += max(0.0, $dayTravel);
            $segments = max(0, (int)($metrics['segment_count'] ?? 0));
            $unresolved = max(0, (int)($metrics['unresolved_leg_count'] ?? 0));
            $totalSegments += $segments;
            $resolvedSegments += max(0, $segments - $unresolved);
            if ($dayDistance > 220 || $dayTravel > 240) $longLegDays++;
            $territory = is_array($day['route_territory'] ?? null) ? (array)$day['route_territory'] : [];
            if ($items) {
                $territoryScoreTotal += is_numeric($territory['coherence_score'] ?? null) ? (float)$territory['coherence_score'] : 100.0;
                $territoryDayCount++;
                if ((string)($territory['coherence_status'] ?? '') === 'fragmented') $fragmentedTerritoryDays++;
                if (!empty($territory['opposite_direction_mix'])) $oppositeMixDays++;
                if (!empty($territory['base_crossing_risk'])) $baseCrossingDays++;
                $localityReentries += max(0, (int)($territory['locality_reentries'] ?? 0));
            }
            foreach ($items as $item) {
                if (!empty($item['periodicity_warning'])) { $warningCount++; $warnings[] = (string)$item['periodicity_warning']; }
                if (!empty($item['time_window_warning'])) { $warningCount++; $warnings[] = (string)$item['time_window_warning']; }
            }
        }
        $missing = count($unassigned);
        $integrityRate = $totalSegments > 0 ? round(($resolvedSegments / $totalSegments) * 100, 1) : 100.0;
        $score = 100;
        $score -= min(45, $missing * 8);
        $score -= min(20, $warningCount * 3);
        $score -= min(15, (int)ceil($overtimeMin / 30));
        $score -= min(15, (int)ceil((100 - $integrityRate) / 5));
        $score -= min(10, $longLegDays * 2);
        $score -= min(16, $fragmentedTerritoryDays * 4);
        $score -= min(18, $oppositeMixDays * 5);
        $score -= min(24, $baseCrossingDays * 8);
        $score -= min(10, $localityReentries * 2);
        $score = max(0, min(100, $score));
        $required = $totalStops + $missing;
        $coverage = $required > 0 ? round(($totalStops / $required) * 100, 1) : 100.0;
        return [
            'quality_score' => $score,
            'routing_quality_score' => $score,
            'coverage_rate' => $coverage,
            'distance_integrity_rate' => $integrityRate,
            'total_distance_km' => round($totalDistance, 2),
            'total_travel_min' => round($totalTravel, 1),
            'long_route_days' => $longLegDays,
            'territorial_coherence_average' => $territoryDayCount > 0 ? round($territoryScoreTotal / $territoryDayCount, 1) : 100.0,
            'fragmented_territory_days' => $fragmentedTerritoryDays,
            'opposite_direction_days' => $oppositeMixDays,
            'base_crossing_days' => $baseCrossingDays,
            'locality_reentries' => $localityReentries,
            'warnings' => array_values(array_unique(array_slice($warnings, 0, 25))),
            'hard_errors' => $missing > 0 ? [sprintf('%d visita(s) ficaram por atribuir.', $missing)] : [],
        ];
    }

    public static function attach(array $plan): array {
        $score = self::score($plan);
        $plan['quality_score'] = $score['quality_score'];
        $plan['coverage_rate'] = $score['coverage_rate'];
        $plan['warnings'] = $score['warnings'];
        $plan['hard_errors'] = $score['hard_errors'];
        $plan['routing_quality_score'] = $score['routing_quality_score'];
        $plan['distance_integrity_rate'] = $score['distance_integrity_rate'];
        $plan['routing_total_distance_km'] = $score['total_distance_km'];
        $plan['routing_total_travel_min'] = $score['total_travel_min'];
        $plan['routing_long_route_days'] = $score['long_route_days'];
        $plan['territorial_coherence_average'] = $score['territorial_coherence_average'];
        $plan['fragmented_territory_days'] = $score['fragmented_territory_days'];
        $plan['opposite_direction_days'] = $score['opposite_direction_days'];
        $plan['base_crossing_days'] = $score['base_crossing_days'];
        $plan['locality_reentries'] = $score['locality_reentries'];
        return $plan;
    }
}
