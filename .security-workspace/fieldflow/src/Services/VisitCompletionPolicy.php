<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Canonical operational completion policy for visits and submissions.
 *
 * A visit is executed only when its stop is done/completed. A failed stop is
 * terminal only when it carries an explicit justification, so it may close the
 * operational day without being counted as executed. Submission states are
 * intentionally conservative: only final operational reports may close a stop.
 */
final class VisitCompletionPolicy {
    public const CONTRACT = 'fieldflow-visit-closure-daily-mail-v1';
    public const VERSION = 1;

    // N->N+1 compatibility for the certified 2.2.540 package gate.
    // SubmissionStatusPolicy remains the runtime authority and is equivalent to ['submitted','reviewed','approved'].

    public static function normaliseSubmissionStatus($status): string {
        return SubmissionStatusPolicy::normalise($status);
    }

    public static function submissionClosesVisit($status): bool {
        return SubmissionStatusPolicy::isOperational($status);
    }

    public static function stopIsExecuted($status): bool {
        return in_array(sanitize_key((string)$status), ['done','completed'], true);
    }

    public static function stopIsJustifiedNonVisit($status, $reason): bool {
        return sanitize_key((string)$status) === 'failed' && trim((string)$reason) !== '';
    }

    public static function stopIsTerminal($status, $reason = ''): bool {
        return self::stopIsExecuted($status) || self::stopIsJustifiedNonVisit($status, $reason);
    }

    public static function executedSql(string $alias = ''): string {
        $prefix = self::sqlPrefix($alias);
        return $prefix . "status IN ('done','completed')";
    }

    public static function justifiedNonVisitSql(string $alias = ''): string {
        $prefix = self::sqlPrefix($alias);
        return $prefix . "status='failed' AND TRIM(COALESCE(" . $prefix . "fail_reason,''))<>''";
    }

    public static function terminalSql(string $alias = ''): string {
        return '(' . self::executedSql($alias) . ' OR (' . self::justifiedNonVisitSql($alias) . '))';
    }

    public static function remainingSql(string $alias = ''): string {
        $prefix = self::sqlPrefix($alias);
        return '(' . $prefix . 'status IS NULL OR NOT ' . self::terminalSql($alias) . ')';
    }

    private static function sqlPrefix(string $alias): string {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: '';
        return $alias !== '' ? $alias . '.' : '';
    }
}
