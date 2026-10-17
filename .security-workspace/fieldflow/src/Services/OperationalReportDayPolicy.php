<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Canonical operational-day semantics.
 *
 * report_day is the primary business date. Route date is the authoritative
 * fallback when a route exists. submitted_at remains an audit timestamp and
 * is only the final fallback for legacy/unbound records.
 */
final class OperationalReportDayPolicy {
    public const CONTRACT = 'fieldflow-operational-report-day-policy-v1';
    public const VERSION = 1;

    public static function resolve($reportDay, $routeDate = '', $submittedAt = ''): string {
        foreach ([$reportDay, $routeDate] as $candidate) {
            $date = self::validDate((string)$candidate);
            if ($date !== '') return $date;
        }
        $submittedAt = trim((string)$submittedAt);
        if ($submittedAt !== '') {
            $ts = strtotime($submittedAt);
            if ($ts !== false) {
                return function_exists('wp_date') ? wp_date('Y-m-d', $ts, wp_timezone()) : date('Y-m-d', $ts);
            }
        }
        return '';
    }

    public static function sqlExpression(string $submissionAlias = 's', string $routeAlias = ''): string {
        $s = self::sqlAlias($submissionAlias, 's');
        $parts = ["NULLIF({$s}.report_day,'')"];
        if ($routeAlias !== '') {
            $r = self::sqlAlias($routeAlias, 'r');
            $parts[] = "NULLIF({$r}.date,'')";
        }
        $parts[] = "DATE({$s}.submitted_at)";
        return 'COALESCE(' . implode(',', $parts) . ')';
    }

    public static function versionSqlExpression(string $submissionAlias = 's', string $versionAlias = 'v'): string {
        $s = self::sqlAlias($submissionAlias, 's');
        $v = self::sqlAlias($versionAlias, 'v');
        return "COALESCE(NULLIF({$s}.report_day,''),DATE({$v}.submitted_at))";
    }

    private static function validDate(string $value): string {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return '';
        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    private static function sqlAlias(string $alias, string $fallback): string {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: '';
        return $alias !== '' ? $alias : $fallback;
    }
}
