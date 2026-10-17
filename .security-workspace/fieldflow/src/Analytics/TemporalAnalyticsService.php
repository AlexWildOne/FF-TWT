<?php
namespace RoutesPro\Analytics;

if (!defined('ABSPATH')) exit;

/**
 * Read-only temporal guard for Analytics.
 *
 * Raw dashboard metrics remain untouched. This service adds an operational
 * time model and can produce a closed-days-only copy for scoring, alerts and
 * AI evaluation so planned future work is never interpreted as underperformance.
 */
final class TemporalAnalyticsService {
    public const VERSION = '1.0.0';

    public static function enrich(array $stats, array $closedLocationRows = []): array {
        $today = current_time('Y-m-d');
        $from = self::date((string)($stats['from'] ?? ''));
        $to = self::date((string)($stats['to'] ?? ''));
        $rows = array_values(array_filter((array)($stats['by_day'] ?? []), 'is_array'));

        $closedRows = [];
        $todayRows = [];
        $futureRows = [];
        foreach ($rows as $row) {
            $date = self::date((string)($row['date'] ?? ''));
            if ($date === '') continue;
            if ($date < $today) $closedRows[] = $row;
            elseif ($date === $today) $todayRows[] = $row;
            else $futureRows[] = $row;
        }

        $closed = self::summarise($closedRows);
        $current = self::summarise($todayRows);
        $future = self::summarise($futureRows);
        $futureDays = [];
        foreach ($futureRows as $row) {
            $date = self::date((string)($row['date'] ?? ''));
            if ($date === '') continue;
            if (!isset($futureDays[$date])) $futureDays[$date] = ['date'=>$date,'routes'=>0,'stops'=>0];
            $futureDays[$date]['routes']++;
            $futureDays[$date]['stops'] += max(0, (int)($row['stops'] ?? 0));
        }
        ksort($futureDays, SORT_STRING);

        $openWindow = ($from !== '' && $to !== '' && $to >= $today);
        $stats['temporal_context'] = [
            'version'=>self::VERSION,
            'as_of_date'=>$today,
            'selected_from'=>$from,
            'selected_to'=>$to,
            'open_window'=>$openWindow,
            'evaluation_scope'=>$openWindow ? 'closed_days_only' : 'selected_period',
            'closed'=>$closed,
            'today'=>$current,
            'future'=>$future,
            'future_route_days'=>array_values($futureDays),
            'closed_by_day'=>$closedRows,
            'today_by_day'=>$todayRows,
            'future_by_day'=>$futureRows,
            'closed_by_location'=>array_values(array_filter($closedLocationRows, 'is_array')),
            'policy'=>[
                'past_closed_days_are_evaluable'=>true,
                'today_is_in_progress'=>true,
                'future_is_planned_not_overdue'=>true,
                'future_never_reduces_operational_scores'=>true,
                'justified_non_visits_are_terminal_not_executed'=>true,
            ],
        ];
        return $stats;
    }

    public static function forEvaluation(array $stats): array {
        $temporal = is_array($stats['temporal_context'] ?? null) ? $stats['temporal_context'] : [];
        if (empty($temporal['open_window'])) return $stats;

        $closed = is_array($temporal['closed'] ?? null) ? $temporal['closed'] : [];
        $stats['total_routes'] = (int)($closed['routes'] ?? 0);
        $stats['completed_routes'] = (int)($closed['completed_routes'] ?? 0);
        $stats['completion_rate'] = (float)($closed['completion_rate'] ?? 0);
        $stats['total_stops'] = (int)($closed['stops'] ?? 0);
        $stats['done_stops'] = (int)($closed['done_stops'] ?? 0);
        $stats['done_rate'] = (float)($closed['done_rate'] ?? 0);
        $stats['avg_stops_per_route'] = $stats['total_routes'] > 0
            ? round($stats['total_stops'] / $stats['total_routes'], 2)
            : 0;
        $stats['by_day'] = array_values(array_filter((array)($temporal['closed_by_day'] ?? []), 'is_array'));
        $stats['by_location'] = array_values(array_filter((array)($temporal['closed_by_location'] ?? []), 'is_array'));
        $stats['rankings'] = [];
        $stats['_temporal_evaluation'] = true;
        return $stats;
    }

    public static function isOpenWindow(array $stats): bool {
        return !empty($stats['temporal_context']['open_window']);
    }

    private static function summarise(array $rows): array {
        $routes = 0;
        $completedRoutes = 0;
        $stops = 0;
        $done = 0;
        $justified = 0;
        $terminal = 0;
        $unresolved = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $routes++;
            $rowStops = max(0, (int)($row['stops'] ?? 0));
            $rowDone = max(0, (int)($row['stops_done'] ?? 0));
            $rowJustified = max(0, (int)($row['stops_justified_failed'] ?? 0));
            $rowTerminal = array_key_exists('stops_terminal', $row)
                ? max(0, (int)$row['stops_terminal'])
                : min($rowStops, $rowDone + $rowJustified);
            $rowUnresolved = array_key_exists('stops_remaining', $row)
                ? max(0, (int)$row['stops_remaining'])
                : max(0, $rowStops - $rowTerminal);
            $status = sanitize_key((string)($row['route_status'] ?? ''));
            if (in_array($status, ['completed','done'], true) || ($rowStops > 0 && $rowUnresolved === 0)) $completedRoutes++;
            $stops += $rowStops;
            $done += min($rowStops, $rowDone);
            $justified += min($rowStops, $rowJustified);
            $terminal += min($rowStops, $rowTerminal);
            $unresolved += min($rowStops, $rowUnresolved);
        }
        return [
            'routes'=>$routes,
            'completed_routes'=>$completedRoutes,
            'completion_rate'=>$routes > 0 ? round(($completedRoutes / $routes) * 100, 1) : 0,
            'stops'=>$stops,
            'done_stops'=>$done,
            'justified_failed_stops'=>$justified,
            'terminal_stops'=>$terminal,
            'unresolved_stops'=>$unresolved,
            'done_rate'=>$stops > 0 ? round(($done / $stops) * 100, 1) : 0,
            'operational_completion_rate'=>$stops > 0 ? round(($terminal / $stops) * 100, 1) : 0,
        ];
    }

    private static function date(string $value): string {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
        return $value;
    }
}
