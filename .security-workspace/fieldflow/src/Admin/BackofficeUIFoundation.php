<?php
namespace RoutesPro\Admin;

if (!defined('ABSPATH')) exit;

/**
 * Controlled rollout for the consolidated FieldFlow backoffice design system.
 *
 * The legacy 2.2.260 presentation remains available at all times. The
 * foundation option name and value are intentionally preserved so existing
 * installations and server constants continue to work without migration.
 */
class BackofficeUIFoundation {
    public const OPTION = 'routespro_backoffice_ui_mode';
    public const MODE_FOUNDATION = 'foundation';
    public const MODE_LEGACY = 'legacy';

    private const PAGE_SCOPES = [
        'routespro' => 'overview',
        'routespro-clients' => 'management',
        'routespro-client-onboarding' => 'management',
        'routespro-subscriptions' => 'management',
        'routespro-license-tenants' => 'management',
        'routespro-release-manager' => 'management',
        'routespro-billing' => 'management',
        'routespro-projects' => 'management',
        'routespro-assignments-hub' => 'assignments',
        'routespro-assign' => 'assignments',
        'routespro-performance' => 'operation',
        'routespro-expenses' => 'operation',
        'routespro-briefings' => 'operation',
        'routespro-implementations' => 'operation',
        'routespro-stocks' => 'operation',
        'routespro-shopping' => 'operation',
        'routespro-mapping-center' => 'operation',
        'routespro-commercial' => 'operation',
        'routespro-stores' => 'operation',
        'routespro-commercial-import' => 'operation',
        'routespro-categories' => 'operation',
        'routespro-context-questions' => 'forms',
        'routespro-forms' => 'forms',
        'routespro-form-edit' => 'forms',
        'routespro-form-submissions' => 'forms',
        'routespro-form-submission-edit' => 'forms',
        'routespro-form-bindings' => 'forms',
        'routespro-form-history' => 'forms',
        'routespro-product-cardex' => 'forms',
        'routespro-routes' => 'routing',
        'routespro-campaign-locations' => 'routing',
        'routespro-integrations' => 'platform',
        'routespro-public-integrations' => 'platform',
        'routespro-ai' => 'platform',
        'routespro-privacy' => 'system',
        'routespro-operational-purge' => 'system',
        'routespro-import' => 'platform',
        'routespro-emails' => 'platform',
        'routespro-email-log' => 'platform',
        'routespro-appearance' => 'platform',
        'routespro-mobile' => 'platform',
        'routespro-devices' => 'platform',
        'routespro-security-observability' => 'system',
        'routespro-processes' => 'system',
        'routespro-pwa' => 'platform',
        'routespro-campaign-branding' => 'platform',
        'routespro-settings' => 'system',
        'routespro-system-health' => 'system',
    ];

    public static function registerHooks(): void {
        add_action('admin_post_routespro_backoffice_ui_mode', [self::class, 'handleModeChange']);
    }

    public static function mode(): string {
        if (defined('FIELDFLOW_BACKOFFICE_UI_MODE')) {
            $constant = sanitize_key((string)constant('FIELDFLOW_BACKOFFICE_UI_MODE'));
            if (in_array($constant, [self::MODE_FOUNDATION, self::MODE_LEGACY], true)) return $constant;
        }
        $stored = sanitize_key((string)get_option(self::OPTION, self::MODE_FOUNDATION));
        return in_array($stored, [self::MODE_FOUNDATION, self::MODE_LEGACY], true) ? $stored : self::MODE_FOUNDATION;
    }

    public static function enabled(): bool {
        return self::mode() === self::MODE_FOUNDATION;
    }

    public static function currentPage(): string {
        return isset($_GET['page']) ? sanitize_key((string)wp_unslash($_GET['page'])) : '';
    }

    public static function scope(?string $page = null): string {
        $page = $page === null ? self::currentPage() : sanitize_key($page);
        if ($page === 'routespro-assignments-hub') {
            $tab = isset($_GET['tab']) ? sanitize_key((string)wp_unslash($_GET['tab'])) : '';
            if ($tab === 'analytics') return 'analytics';
        }
        return self::PAGE_SCOPES[$page] ?? '';
    }

    public static function isManagedPage(?string $page = null): bool {
        return self::scope($page) !== '';
    }

    /** @deprecated Kept for extensions compiled against FieldFlow 2.2.261. */
    public static function isPriorityPage(?string $page = null): bool {
        return self::isManagedPage($page);
    }

    public static function status(): array {
        $style = ROUTESPRO_PATH . 'assets/fieldflow-backoffice-ui.css';
        $script = ROUTESPRO_PATH . 'assets/fieldflow-backoffice-ui.js';
        $navigationStyle = ROUTESPRO_PATH . 'assets/fieldflow-admin-navigation.css';
        $navigationScript = ROUTESPRO_PATH . 'assets/fieldflow-admin-navigation.js';
        $componentsStyle = ROUTESPRO_PATH . 'assets/fieldflow-admin-components.css';
        $componentsScript = ROUTESPRO_PATH . 'assets/fieldflow-admin-components.js';
        $assets = [$style, $script, $navigationStyle, $navigationScript, $componentsStyle, $componentsScript];
        $assetBytes = 0;
        foreach ($assets as $asset) if (is_file($asset)) $assetBytes += (int)filesize($asset);
        return [
            'version' => '2.2.262',
            'mode' => self::mode(),
            'enabled' => self::enabled(),
            'assets_ready' => count(array_filter($assets, 'is_file')) === count($assets),
            'legacy_fallback' => true,
            'locked' => defined('FIELDFLOW_BACKOFFICE_UI_MODE'),
            'managed_pages' => count(self::PAGE_SCOPES),
            'managed_scopes' => count(array_unique(array_values(self::PAGE_SCOPES))),
            'priority_pages' => count(self::PAGE_SCOPES),
            'current_scope' => self::scope(),
            'asset_bytes' => $assetBytes,
            'extracted_style_blocks' => 7,
            'extracted_script_blocks' => 3,
        ];
    }

    public static function handleModeChange(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.', 'FieldFlow', ['response' => 403]);
        check_admin_referer('routespro_backoffice_ui_mode');
        if (defined('FIELDFLOW_BACKOFFICE_UI_MODE')) {
            wp_safe_redirect(admin_url('admin.php?page=routespro-system-health&fieldflow_ui_mode=locked'));
            exit;
        }
        $mode = isset($_POST['mode']) ? sanitize_key((string)wp_unslash($_POST['mode'])) : '';
        if (!in_array($mode, [self::MODE_FOUNDATION, self::MODE_LEGACY], true)) {
            wp_safe_redirect(admin_url('admin.php?page=routespro-system-health&fieldflow_ui_mode=invalid'));
            exit;
        }
        update_option(self::OPTION, $mode, false);
        wp_safe_redirect(admin_url('admin.php?page=routespro-system-health&fieldflow_ui_mode=' . rawurlencode($mode)));
        exit;
    }
}
