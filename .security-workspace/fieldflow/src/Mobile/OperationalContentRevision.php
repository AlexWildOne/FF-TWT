<?php
namespace RoutesPro\Mobile;

if (!defined('ABSPATH')) exit;

/**
 * Monotonic project revision for content that changes the operative form bundle.
 * Briefings, Cardex and form bindings can bump the same revision so cached
 * offline bundles are rebuilt deterministically after an operational change.
 */
final class OperationalContentRevision {
    private const OPTION_PREFIX = 'fieldflow_operational_content_revision_';

    public static function current(int $projectId): int {
        $projectId = absint($projectId);
        if ($projectId <= 0) return 1;
        return max(1, absint(get_option(self::OPTION_PREFIX . $projectId, 1)));
    }

    public static function bump(int $projectId, string $reason = ''): int {
        $projectId = absint($projectId);
        if ($projectId <= 0) return 1;
        $next = self::current($projectId) + 1;
        update_option(self::OPTION_PREFIX . $projectId, $next, false);
        do_action('fieldflow_operational_content_revision_bumped', $projectId, $next, sanitize_key($reason));
        return $next;
    }
}
