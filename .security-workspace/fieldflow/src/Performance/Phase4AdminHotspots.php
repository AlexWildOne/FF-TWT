<?php
namespace RoutesPro\Performance;

if (!defined('ABSPATH')) exit;

/**
 * Phase 4C (9DH): bounded, read-only attribution of existing admin latency.
 * No SQL statement bodies, request contents, identities, IPs or paid services.
 * Never turns performance sampling into a runtime blocking decision.
 */
final class Phase4AdminHotspots {
    public const CONTRACT = 'fieldflow-phase4-admin-hotspots-manifest-safety-9dh-v1';
    public const MAX_ROWS = 2500;

    /** One bounded, indexed read, only when the Performance tab is opened. */
    public static function collect(int $hours = 24): array {
        $hours = max(1, min(168, $hours));
        global $wpdb;
        $table = LoadReadiness::tableName();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ((string)$exists !== $table) return self::analyze([], $hours, false);
        $since = gmdate('Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT operation_key, duration_ms, query_count FROM ' . $table .
            ' WHERE category=%s AND sampled_at>=%s ORDER BY sampled_at DESC LIMIT ' . self::MAX_ROWS,
            'admin', $since
        ), ARRAY_A) ?: [];
        return self::analyze($rows, $hours, true);
    }

    /** Pure for CLI testing; percentiles are computed per page, never inferred from global P95. */
    public static function analyze(array $rows, int $hours = 24, bool $tableReady = true): array {
        $groups = [];
        $sampleCount = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $key = (string)($row['operation_key'] ?? '');
            if (strpos($key, 'ADMIN ') !== 0 || strlen($key) > 191) continue;
            $sampleCount++;
            if (!isset($groups[$key])) $groups[$key] = ['duration'=>[], 'queries'=>[]];
            $groups[$key]['duration'][] = max(0,(int)($row['duration_ms'] ?? 0));
            $groups[$key]['queries'][] = max(0,(int)($row['query_count'] ?? 0));
        }
        $ranked = [];
        foreach ($groups as $operation => $data) {
            $durations = $data['duration'];
            $queries = $data['queries'];
            sort($durations, SORT_NUMERIC);
            sort($queries, SORT_NUMERIC);
            $n = count($durations);
            if (!$n) continue;
            $rank = max(0,(int)ceil($n * .95) - 1);
            $p95 = $durations[$rank];
            $p95Queries = $queries[$rank];
            $sufficient = $n >= 5;
            $level = !$sufficient ? 'collecting' :
                ($p95 >= 6000 || $p95Queries >= 700 ? 'critical' :
                ($p95 >= 2500 || $p95Queries >= 180 ? 'warning' : 'pass'));
            $ranked[] = [
                'operation'=>$operation,
                'samples'=>$n,
                'p95_duration_ms'=>$p95,
                'p95_queries'=>$p95Queries,
                'max_duration_ms'=>max($durations),
                'max_queries'=>max($queries),
                'status'=>$level,
            ];
        }
        usort($ranked, static function (array $a,array $b): int {
            // Prioritize sufficiently sampled outliers, then measured latency/query volume.
            $aCount = $a['samples'] >= 5 ? 1 : 0;
            $bCount = $b['samples'] >= 5 ? 1 : 0;
            return ($bCount <=> $aCount)
                ?: ($b['p95_duration_ms'] <=> $a['p95_duration_ms'])
                ?: ($b['p95_queries'] <=> $a['p95_queries'])
                ?: strcmp($a['operation'],$b['operation']);
        });
        return [
            'contract'=>self::CONTRACT,
            'read_only'=>true,
            'table_ready'=>$tableReady,
            'window_hours'=>max(1,min(168,$hours)),
            'sample_count'=>$sampleCount,
            'sample_limit'=>self::MAX_ROWS,
            'truncated'=>count($rows)>=self::MAX_ROWS,
            'operation_count'=>count($ranked),
            'rows'=>array_slice($ranked,0,12),
        ];
    }
}
