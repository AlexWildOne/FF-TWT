<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Canonical route status vocabulary.
 *
 * Historical aliases remain accepted at the boundaries, while new writes use
 * one stable representation so backfills, snapshots and UI state agree.
 */
final class RouteStatus {
    public const DRAFT = 'draft';
    public const PLANNED = 'planned';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const CANCELED = 'canceled';

    public static function normalize(string $status): string {
        $status = sanitize_key($status);
        if ($status === 'done') return self::COMPLETED;
        if ($status === 'cancelled') return self::CANCELED;
        return $status;
    }

    public static function isValid(string $status): bool {
        return in_array(self::normalize($status), [
            self::DRAFT,
            self::PLANNED,
            self::IN_PROGRESS,
            self::COMPLETED,
            self::CANCELED,
        ], true);
    }

    public static function isSnapshotMutable(string $status): bool {
        return in_array(self::normalize($status), [self::DRAFT, self::PLANNED], true);
    }

    public static function isTerminal(string $status): bool {
        return in_array(self::normalize($status), [self::COMPLETED, self::CANCELED], true);
    }

    public static function terminalAliases(): array {
        return ['completed', 'done', 'canceled', 'cancelled'];
    }
}
