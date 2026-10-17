<?php
namespace RoutesPro\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Progressive React islands for the FieldFlow backoffice.
 *
 * React is intentionally opt-in per managed page. PHP remains the canonical
 * renderer and every island must keep a usable server-rendered fallback.
 * WordPress' bundled wp-element runtime is used so FieldFlow does not ship a
 * second React copy or add a new production dependency.
 */
final class ReactBackofficeFoundation {
    public const CONTRACT = 'fieldflow-react-backoffice-foundation-v1';
    public const SHARED_CONTRACT = 'fieldflow-react-backoffice-shared-v1';
    public const PILOT_PAGE = 'routespro-mobile';
    public const PILOT_ISLAND = 'mobile-release-status';
    public const DEVICES_PAGE = 'routespro-devices';
    public const DEVICES_ISLAND = 'device-control-table';
    public const ANALYTICS_PAGE = 'routespro-assignments-hub';
    public const ANALYTICS_ISLAND = 'analytics-context-kpis';

    public static function enabled(): bool {
        if (defined('FIELDFLOW_REACT_BACKOFFICE') && !FIELDFLOW_REACT_BACKOFFICE) return false;
        return class_exists(BackofficeUIFoundation::class) && BackofficeUIFoundation::enabled();
    }

    public static function pageEnabled(?string $page = null): bool {
        if (!self::enabled()) return false;
        $page = $page === null ? BackofficeUIFoundation::currentPage() : sanitize_key($page);
        if (in_array($page, [self::PILOT_PAGE, self::DEVICES_PAGE], true)) return true;
        return $page === self::ANALYTICS_PAGE && BackofficeUIFoundation::scope($page) === 'analytics';
    }

    public static function islandEnabled(string $island, ?string $page = null): bool {
        if (!self::pageEnabled($page)) return false;
        $page = $page === null ? BackofficeUIFoundation::currentPage() : sanitize_key($page);
        $island = sanitize_key($island);
        if ($page === self::PILOT_PAGE) return $island === self::PILOT_ISLAND;
        if ($page === self::DEVICES_PAGE) return $island === self::DEVICES_ISLAND;
        if ($page === self::ANALYTICS_PAGE && BackofficeUIFoundation::scope($page) === 'analytics') return $island === self::ANALYTICS_ISLAND;
        return false;
    }

    public static function status(): array {
        $script = ROUTESPRO_PATH . 'assets/fieldflow-react-backoffice-' . FIELDFLOW_VERSION . '.js';
        $style = ROUTESPRO_PATH . 'assets/fieldflow-react-backoffice.css';
        return [
            'contract' => self::CONTRACT,
            'shared_contract' => self::SHARED_CONTRACT,
            'enabled' => self::enabled(),
            'pilot_page' => self::PILOT_PAGE,
            'pilot_island' => self::PILOT_ISLAND,
            'devices_page' => self::DEVICES_PAGE,
            'devices_island' => self::DEVICES_ISLAND,
            'analytics_page' => self::ANALYTICS_PAGE,
            'analytics_island' => self::ANALYTICS_ISLAND,
            'page_enabled' => self::pageEnabled(),
            'script_ready' => is_file($script),
            'style_ready' => is_file($style),
            'wordpress_react' => 'wp-element',
            'php_fallback' => true,
        ];
    }
}
