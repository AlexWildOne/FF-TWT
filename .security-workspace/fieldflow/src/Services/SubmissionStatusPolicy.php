<?php
namespace RoutesPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Canonical submission status semantics for operational reporting.
 *
 * Final operational submissions are the only records that may close visits,
 * feed operational analytics, or represent the latest completed visit.
 * Draft/rejected records remain editable/recoverable but are not operational
 * facts. Void/cancelled/deleted/trash records are inactive.
 */
final class SubmissionStatusPolicy {
    public const CONTRACT = 'fieldflow-submission-status-policy-v1';
    public const VERSION = 1;

    private const OPERATIONAL = ['submitted','reviewed','approved'];
    private const INACTIVE = ['void','cancelled','deleted','trash'];

    public static function normalise($status): string {
        $status = sanitize_key((string)$status);
        return $status !== '' ? $status : 'submitted';
    }

    public static function isOperational($status): bool {
        return in_array(self::normalise($status), self::OPERATIONAL, true);
    }

    public static function isEditableExisting($status): bool {
        return !in_array(self::normalise($status), self::INACTIVE, true);
    }

    public static function isInactive($status): bool {
        return in_array(self::normalise($status), self::INACTIVE, true);
    }

    public static function operationalStatuses(): array {
        return self::OPERATIONAL;
    }

    public static function operationalSql(string $alias = ''): string {
        $prefix = self::sqlPrefix($alias);
        return "COALESCE({$prefix}status,'submitted') IN ('submitted','reviewed','approved')";
    }

    public static function editableExistingSql(string $alias = ''): string {
        $prefix = self::sqlPrefix($alias);
        return "COALESCE({$prefix}status,'submitted') NOT IN ('deleted','trash','void','cancelled')";
    }

    private static function sqlPrefix(string $alias): string {
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: '';
        return $alias !== '' ? $alias . '.' : '';
    }
}
