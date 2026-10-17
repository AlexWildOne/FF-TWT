<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

/**
 * Shared responsive runtime for every FieldFlow front-end shortcode.
 *
 * The existing renderers keep their visual identity and business logic. This
 * layer only normalises device behaviour, safe areas, touch targets, tables,
 * dynamic viewport sizing and native/standalone navigation.
 */
class ResponsiveAssets {
    private const STYLE_HANDLE = 'fieldflow-responsive-runtime';
    private const SCRIPT_HANDLE = 'fieldflow-responsive-runtime';
    private const FRONTEND_CORE_SCRIPT_HANDLE = 'fieldflow-frontend-core';
    private const DESIGN_STYLE_HANDLE = 'fieldflow-design-system';
    private const UI_SCRIPT_HANDLE = 'fieldflow-ui-runtime';
    private const OFFLINE_STYLE_HANDLE = 'fieldflow-offline-runtime';
    private const OFFLINE_SCRIPT_HANDLE = 'fieldflow-offline-runtime';
    private const INTELLIGENCE_STYLE_HANDLE = 'fieldflow-operational-intelligence';
    private const UNIFIED_STYLE_HANDLE = 'fieldflow-unified-layout';
    private const UNIFIED_ADMIN_STYLE_HANDLE = 'fieldflow-unified-layout-admin';
    private const PREMIUM_ANALYTICS_STYLE_HANDLE = 'fieldflow-premium-analytics';
    private const PREMIUM_ANALYTICS_SCRIPT_HANDLE = 'fieldflow-premium-analytics';
    private const PREMIUM_ANALYTICS_ADMIN_STYLE_HANDLE = 'fieldflow-premium-analytics-admin';
    private const PREMIUM_ANALYTICS_ADMIN_SCRIPT_HANDLE = 'fieldflow-premium-analytics-admin';
    private const BACKOFFICE_UI_STYLE_HANDLE = 'fieldflow-backoffice-ui';
    private const BACKOFFICE_UI_SCRIPT_HANDLE = 'fieldflow-backoffice-ui';
    private const REACT_BACKOFFICE_STYLE_HANDLE = 'fieldflow-react-backoffice';
    private const REACT_BACKOFFICE_SCRIPT_HANDLE = 'fieldflow-react-backoffice';
    private const REACT_FRONT_STYLE_HANDLE = 'fieldflow-react-front';
    private const REACT_FRONT_SCRIPT_HANDLE = 'fieldflow-react-front';
    private const ADMIN_COMPONENTS_STYLE_HANDLE = 'fieldflow-admin-components';
    private const ADMIN_COMPONENTS_SCRIPT_HANDLE = 'fieldflow-admin-components';
    private const ADMIN_NAVIGATION_STYLE_HANDLE = 'fieldflow-admin-navigation';
    private const ADMIN_NAVIGATION_SCRIPT_HANDLE = 'fieldflow-admin-navigation';
    private const OPERATIONAL_EXPERIENCE_STYLE_HANDLE = 'fieldflow-operational-experience';
    private const OPERATIONAL_EXPERIENCE_SCRIPT_HANDLE = 'fieldflow-operational-experience';
    private const PREMIUM_SHELL_V2_STYLE_HANDLE = 'fieldflow-premium-shell-v2';
    private const PREMIUM_SHELL_V2_SCRIPT_HANDLE = 'fieldflow-premium-shell-v2';
    private const ADMIN_PREMIUM_SHELL_V2_STYLE_HANDLE = 'fieldflow-admin-premium-shell-v2';
    private const ANALYTICS2_ADMIN_STYLE_HANDLE = 'fieldflow-analytics2-admin';
    private const ANALYTICS2_ADMIN_SCRIPT_HANDLE = 'fieldflow-analytics2-admin';
    private const GOOGLE_MAPS_LOADER_HANDLE = 'fieldflow-google-maps-loader';

    private static array $shortcodes = [
        'fieldflow_my_daily_route',
        'fieldflow_dashboard',
        'fieldflow_route_change_form',
        'fieldflow_front_hub',
        'fieldflow_front_routes',
        'fieldflow_front_commercial',
        'fieldflow_app',
        'fieldflow_expenses',
        'fieldflow_briefings',
        'fieldflow_client_briefings',
        'fieldflow_implementations',
        'fieldflow_client_implementations',
        'fieldflow_stocks',
        'fieldflow_client_stocks',
        'fieldflow_route_today',
        'fieldflow_discovery',
        'fieldflow_report_visit',
        'fieldflow_checkin',
        'fieldflow_client_portal',
        'fieldflow_client_team_mail',
        'fieldflow_team_inbox',
        'fieldflow_performance_dashboard',
        'fieldflow_academy',
        'fieldflow_form',
        'fieldflow_route_form',
    ];

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue'], 90);
        add_filter('body_class', [self::class, 'bodyClasses']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAdmin'], 90);
        add_filter('admin_body_class', [self::class, 'adminBodyClass']);
    }

    public static function enqueue(): void {
        if (!self::shouldLoadForRequest()) return;

        wp_enqueue_script(
            self::FRONTEND_CORE_SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-frontend-core-2.2.658.js',
            [],
            ROUTESPRO_VERSION,
            true
        );
        wp_localize_script(self::FRONTEND_CORE_SCRIPT_HANDLE, 'FieldFlowFrontendConfig', [
            'version' => ROUTESPRO_VERSION,
            'context' => 'front',
            'native' => self::isNative(),
        ]);
        wp_enqueue_script(
            self::GOOGLE_MAPS_LOADER_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-google-maps-loader.js',
            [],
            ROUTESPRO_VERSION,
            false
        );
        wp_enqueue_style(
            self::STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-responsive.css',
            [],
            ROUTESPRO_VERSION
        );
        wp_enqueue_style(
            self::DESIGN_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-design-system.css',
            [self::STYLE_HANDLE],
            ROUTESPRO_VERSION
        );
        wp_enqueue_style(
            self::INTELLIGENCE_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-operational-intelligence.css',
            [self::DESIGN_STYLE_HANDLE],
            ROUTESPRO_VERSION
        );
        wp_enqueue_style(
            self::UNIFIED_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-unified-layout.css',
            [self::INTELLIGENCE_STYLE_HANDLE],
            ROUTESPRO_VERSION
        );
        wp_enqueue_style(
            self::PREMIUM_ANALYTICS_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-premium-analytics.css',
            [self::UNIFIED_STYLE_HANDLE],
            ROUTESPRO_VERSION
        );
        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-responsive.js',
            [self::FRONTEND_CORE_SCRIPT_HANDLE],
            ROUTESPRO_VERSION,
            true
        );
        wp_enqueue_script(
            self::UI_SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-ui-runtime.js',
            [self::SCRIPT_HANDLE],
            ROUTESPRO_VERSION,
            true
        );
        wp_enqueue_script(
            self::PREMIUM_ANALYTICS_SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-premium-analytics.js',
            [self::UI_SCRIPT_HANDLE],
            ROUTESPRO_VERSION,
            true
        );
        wp_enqueue_style(
            self::OPERATIONAL_EXPERIENCE_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-operational-experience.css',
            [self::PREMIUM_ANALYTICS_STYLE_HANDLE],
            ROUTESPRO_VERSION
        );
        wp_enqueue_script(
            self::OPERATIONAL_EXPERIENCE_SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-operational-experience.js',
            [self::PREMIUM_ANALYTICS_SCRIPT_HANDLE],
            ROUTESPRO_VERSION,
            true
        );
        // Premium Shell v2 (2.2.399+): purely additive typography, spacing,
        // shape and motion refinements on top of the existing shortcode
        // markup, plus a progressive-enhancement rail nav for the Client
        // Portal (fieldflow-premium-shell-v2.js — only forwards clicks to
        // the existing tabbar buttons, never duplicates panel logic).
        // Touches no color tokens beyond WCAG contrast fixes in Theme.php
        // presets. Can be disabled instantly, with no code change, by
        // defining FIELDFLOW_PREMIUM_SHELL_V2 as false in wp-config.php.
        if (!defined('FIELDFLOW_PREMIUM_SHELL_V2') || FIELDFLOW_PREMIUM_SHELL_V2) {
            wp_enqueue_style(
                self::PREMIUM_SHELL_V2_STYLE_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-premium-shell-v2.css',
                [self::OPERATIONAL_EXPERIENCE_STYLE_HANDLE],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                self::PREMIUM_SHELL_V2_SCRIPT_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-premium-shell-v2.js',
                [],
                ROUTESPRO_VERSION,
                true
            );
        }
        $offlineAllowed = !class_exists('\RoutesPro\SaaS\EntitlementService') || \RoutesPro\SaaS\EntitlementService::userHasAny('mobile.offline');
        if (is_user_logged_in() && self::needsFormRuntime() && $offlineAllowed) {
            wp_enqueue_style(
                self::OFFLINE_STYLE_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-offline-runtime.css',
                [self::DESIGN_STYLE_HANDLE],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                self::OFFLINE_SCRIPT_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-offline-runtime-2.2.658.js',
                [],
                ROUTESPRO_VERSION,
                false
            );
            wp_localize_script(self::OFFLINE_SCRIPT_HANDLE, 'FieldFlowOfflineConfig', [
                'version' => ROUTESPRO_VERSION,
                'userId' => get_current_user_id(),
                'nonce' => wp_create_nonce('wp_rest'),
                'probeEndpoint' => rest_url('fieldflow-mobile/v1/offline/ping'),
                'bootstrapEndpoint' => rest_url('fieldflow-mobile/v1/offline/bootstrap'),
                'deltaEndpoint' => rest_url('fieldflow-mobile/v1/offline/delta'),
                'syncEndpoint' => rest_url('fieldflow-mobile/v1/offline/sync'),
                'statusEndpoint' => rest_url('fieldflow-mobile/v1/offline/status'),
                'trustLeaseEndpoint' => rest_url('fieldflow-mobile/v1/offline/trust-lease'),
                'resumableMediaEndpoint' => rest_url('fieldflow-mobile/v1/offline/media'),
                'resumableChunkBytes' => 524288,
                'policyEndpoint' => rest_url('fieldflow-mobile/v1/device/policy'),
                'formEndpoint' => admin_url('admin-post.php'),
                'platform' => self::isNative() ? 'native_webview' : 'web',
                'cacheTtlSeconds' => 43200,
                'syncIntervalSeconds' => 60,
                'syncBatchSize' => 25,
                'probeTimeoutMs' => 4500,
                'probeFreshMs' => 15000,
                'degradedLatencyMs' => 2800,
                'requestTimeoutMs' => 7000,
                'formSyncTimeoutMs' => 90000,
                'syncRequestTimeoutMs' => 15000,
                'syncLockTtlMs' => 120000,
                'syncLockHeartbeatMs' => 10000,
                'operationalDate' => current_time('Y-m-d'),
                'siteTimezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : '',
                'showStatus' => true,
            ]);
        }

        if (is_user_logged_in() && class_exists('\\RoutesPro\\Front\\ReactFrontFoundation') && \RoutesPro\Front\ReactFrontFoundation::enabled()) {
            wp_enqueue_style(
                self::REACT_FRONT_STYLE_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-react-front.css',
                [self::DESIGN_STYLE_HANDLE],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                self::REACT_FRONT_SCRIPT_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-react-front-2.2.658.js',
                [self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element'],
                ROUTESPRO_VERSION,
                true
            );
            wp_localize_script(self::REACT_FRONT_SCRIPT_HANDLE, 'FieldFlowReactFrontConfig', [
                'version' => ROUTESPRO_VERSION,
                'contract' => \RoutesPro\Front\ReactFrontFoundation::CONTRACT,
                'formWizardContract' => \RoutesPro\Front\ReactFrontFoundation::FORM_WIZARD_CONTRACT,
                'formWizardIsland' => \RoutesPro\Front\ReactFrontFoundation::FORM_WIZARD_ISLAND,
                'operationalShellContract' => \RoutesPro\Front\ReactFrontFoundation::OPERATIONAL_SHELL_CONTRACT,
                'fullwidthNavigationContract' => \RoutesPro\Front\ReactFrontFoundation::FULLWIDTH_NAVIGATION_CONTRACT,
                'frontTransitionStabilityContract' => \RoutesPro\Front\ReactFrontFoundation::FRONT_TRANSITION_STABILITY_CONTRACT,
                'operationalShellIsland' => \RoutesPro\Front\ReactFrontFoundation::OPERATIONAL_SHELL_ISLAND,
                'operationalModulesContract' => \RoutesPro\Front\ReactFrontFoundation::OPERATIONAL_MODULES_CONTRACT,
                'clientWave1Contract' => \RoutesPro\Front\ReactFrontFoundation::CLIENT_WAVE1_CONTRACT,
                'clientWave2Contract' => \RoutesPro\Front\ReactFrontFoundation::CLIENT_WAVE2_CONTRACT,
                'crossPortalIsolationContract' => \RoutesPro\Front\ReactFrontFoundation::CROSS_PORTAL_ISOLATION_CONTRACT,
                'dualPortalLayoutContract' => \RoutesPro\Front\ReactFrontFoundation::DUAL_PORTAL_LAYOUT_CONTRACT,
                'appResumePaintGuardContract' => \RoutesPro\Front\ReactFrontFoundation::APP_RESUME_PAINT_GUARD_CONTRACT,
                'dualPortalFullwidthParityContract' => \RoutesPro\Front\ReactFrontFoundation::DUAL_PORTAL_FULLWIDTH_PARITY_CONTRACT,
                'appResumePaintGuardV2Contract' => \RoutesPro\Front\ReactFrontFoundation::APP_RESUME_PAINT_GUARD_V2_CONTRACT,
                'uiCompositionContract' => \RoutesPro\Front\ReactFrontFoundation::UI_COMPOSITION_CONTRACT,
                'resumeDiagnosticsContract' => \RoutesPro\Front\ReactFrontFoundation::RESUME_DIAGNOSTICS_CONTRACT,
                'operationalModulesIsland' => \RoutesPro\Front\ReactFrontFoundation::OPERATIONAL_MODULES_ISLAND,
                'myRouteContract' => \RoutesPro\Front\ReactFrontFoundation::MY_ROUTE_CONTRACT,
                'myRouteIsland' => \RoutesPro\Front\ReactFrontFoundation::MY_ROUTE_ISLAND,
                'simpleFieldContract' => \RoutesPro\Front\ReactFrontFoundation::SIMPLE_FIELD_CONTRACT,
                'simpleFieldControlContract' => \RoutesPro\Front\ReactFrontFoundation::SIMPLE_FIELD_CONTROL_CONTRACT,
                'simpleFieldIsland' => \RoutesPro\Front\ReactFrontFoundation::SIMPLE_FIELD_ISLAND,
                'choiceFieldContract' => \RoutesPro\Front\ReactFrontFoundation::CHOICE_FIELD_CONTRACT,
                'choiceFieldIsland' => \RoutesPro\Front\ReactFrontFoundation::CHOICE_FIELD_ISLAND,
                'specialFieldContract' => \RoutesPro\Front\ReactFrontFoundation::SPECIAL_FIELD_CONTRACT,
                'specialFieldIsland' => \RoutesPro\Front\ReactFrontFoundation::SPECIAL_FIELD_ISLAND,
                'fallback' => true,
            ]);
        }

        // 9D5: scoped visual consolidation, no change to REST or business flows.
        // Instant rollback of visual layer: FIELDFLOW_VISUAL_CONSOLIDATION_9D5=false.
        if (!defined('FIELDFLOW_VISUAL_CONSOLIDATION_9D5') || FIELDFLOW_VISUAL_CONSOLIDATION_9D5) {
            wp_enqueue_style(
                'fieldflow-visual-consolidation-9d5',
                ROUTESPRO_URL . 'assets/fieldflow-visual-consolidation-9d5.css',
                [self::OPERATIONAL_EXPERIENCE_STYLE_HANDLE],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                'fieldflow-visual-consolidation-9d5',
                ROUTESPRO_URL . 'assets/fieldflow-visual-consolidation-9d5.js',
                [self::OPERATIONAL_EXPERIENCE_SCRIPT_HANDLE],
                ROUTESPRO_VERSION,
                true
            );
        }

        // 9D9: default Operative dock is now handled by the same 9D5 controller as Client.
        // These historical enqueues are fallback only when the 9D9 switch is off.
        $legacyOperativeDock = defined('FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9') && !FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9;
        // 9DE: historical 9D6/9D7 owners are permitted only if the new Operative owner is explicitly disabled.
        $legacyDockAllowed9de = defined('FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE') && !FIELDFLOW_OPERATIVE_DOCK_BOOTSTRAP_9DE;
        // 9D6: operative-only nested-scroll dock parity; depends on the stable 9D5 visual layer.
        // Kill switch: FIELDFLOW_OPERATIVE_PARITY_9D6=false, preserving the 2.2.630 UX.
        if ($legacyOperativeDock
            && $legacyDockAllowed9de
            && (!defined('FIELDFLOW_VISUAL_CONSOLIDATION_9D5') || FIELDFLOW_VISUAL_CONSOLIDATION_9D5)
            && (!defined('FIELDFLOW_OPERATIVE_PARITY_9D6') || FIELDFLOW_OPERATIVE_PARITY_9D6)) {
            wp_enqueue_style(
                'fieldflow-operative-dock-parity-9d6',
                ROUTESPRO_URL . 'assets/fieldflow-operative-dock-parity-9d6.css',
                ['fieldflow-visual-consolidation-9d5'],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                'fieldflow-operative-dock-parity-9d6',
                ROUTESPRO_URL . 'assets/fieldflow-operative-dock-parity-9d6.js',
                ['fieldflow-visual-consolidation-9d5'],
                ROUTESPRO_VERSION,
                true
            );
        }
        // 9D7: WebView gesture fallback and real bottom reserve on the operative portal.
        // Independent rollback: FIELDFLOW_OPERATIVE_OVERLAY_9D7=false restores 9D6.
        if ($legacyOperativeDock
            && $legacyDockAllowed9de
            && (!defined('FIELDFLOW_VISUAL_CONSOLIDATION_9D5') || FIELDFLOW_VISUAL_CONSOLIDATION_9D5)
            && (!defined('FIELDFLOW_OPERATIVE_PARITY_9D6') || FIELDFLOW_OPERATIVE_PARITY_9D6)
            && (!defined('FIELDFLOW_OPERATIVE_OVERLAY_9D7') || FIELDFLOW_OPERATIVE_OVERLAY_9D7)) {
            wp_enqueue_style(
                'fieldflow-operative-dock-overlay-9d7',
                ROUTESPRO_URL . 'assets/fieldflow-operative-dock-overlay-9d7.css',
                ['fieldflow-operative-dock-parity-9d6'],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                'fieldflow-operative-dock-overlay-9d7',
                ROUTESPRO_URL . 'assets/fieldflow-operative-dock-overlay-9d7.js',
                ['fieldflow-operative-dock-parity-9d6'],
                ROUTESPRO_VERSION,
                true
            );
        }
        // 9D6 historical frontend build preserved as a file in assets/.
        // fieldflow-frontend-core-2.2.631.js

        wp_localize_script(self::SCRIPT_HANDLE, 'FieldFlowResponsive', [
            'version' => ROUTESPRO_VERSION,
            'native' => self::isNative(),
            'phoneMax' => 767,
            'tabletMax' => 1180,
            'rootSelector' => self::rootSelector(),
        ]);
        $ui_theme = class_exists('\RoutesPro\Support\Theme') ? \RoutesPro\Support\Theme::global() : [];
        wp_localize_script(self::UI_SCRIPT_HANDLE, 'FieldFlowUIConfig', [
            'version' => ROUTESPRO_VERSION,
            'loadingLabel' => __('A carregar módulo...', 'routes-pro'),
            'retryLabel' => __('Tentar novamente', 'routes-pro'),
            'errorLabel' => __('Não foi possível carregar este módulo.', 'routes-pro'),
            'evidencePageSize' => (int)($ui_theme['evidence_page_size'] ?? 6),
            'evidenceInitialOpen' => (int)($ui_theme['evidence_initial_open'] ?? 1),
        ]);
        wp_localize_script(self::PREMIUM_ANALYTICS_SCRIPT_HANDLE, 'FieldFlowPremiumAnalyticsConfig', [
            'version' => ROUTESPRO_VERSION,
            'seriesPageSize' => 8,
        ]);
    }

    public static function bodyClasses(array $classes): array {
        if (!self::shouldLoadForRequest()) return $classes;
        $classes[] = 'fieldflow-responsive-runtime';
        $classes[] = 'fieldflow-unified-layout';
        $classes[] = 'fieldflow-premium-analytics';
        $classes[] = 'fieldflow-operational-experience';
        if (self::isNative()) $classes[] = 'fieldflow-responsive-native';
        return array_values(array_unique($classes));
    }

    public static function enqueueAdmin(string $hookSuffix = ''): void {
        if (!self::shouldLoadAdminRequest($hookSuffix)) return;
        wp_enqueue_script(
            self::FRONTEND_CORE_SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-frontend-core-2.2.658.js',
            [],
            ROUTESPRO_VERSION,
            true
        );
        wp_localize_script(self::FRONTEND_CORE_SCRIPT_HANDLE, 'FieldFlowFrontendConfig', [
            'version' => ROUTESPRO_VERSION,
            'context' => 'admin',
            'native' => false,
        ]);
        wp_enqueue_script(
            self::GOOGLE_MAPS_LOADER_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-google-maps-loader.js',
            [],
            ROUTESPRO_VERSION,
            false
        );
        wp_enqueue_style(
            self::UNIFIED_ADMIN_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-unified-layout-admin.css',
            [],
            ROUTESPRO_VERSION
        );
        wp_enqueue_style(
            self::PREMIUM_ANALYTICS_ADMIN_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-premium-analytics-admin.css',
            [self::UNIFIED_ADMIN_STYLE_HANDLE],
            ROUTESPRO_VERSION
        );
        wp_enqueue_script(
            self::PREMIUM_ANALYTICS_ADMIN_SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-premium-analytics-admin.js',
            [self::FRONTEND_CORE_SCRIPT_HANDLE],
            ROUTESPRO_VERSION,
            true
        );
        wp_enqueue_style(
            self::ADMIN_COMPONENTS_STYLE_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-admin-components.css',
            [self::UNIFIED_ADMIN_STYLE_HANDLE, self::PREMIUM_ANALYTICS_ADMIN_STYLE_HANDLE],
            ROUTESPRO_VERSION
        );
        wp_enqueue_script(
            self::ADMIN_COMPONENTS_SCRIPT_HANDLE,
            ROUTESPRO_URL . 'assets/fieldflow-admin-components.js',
            [self::FRONTEND_CORE_SCRIPT_HANDLE],
            ROUTESPRO_VERSION,
            true
        );
        $foundationAvailable = class_exists('\RoutesPro\Admin\BackofficeUIFoundation');
        $foundationEnabled = $foundationAvailable && \RoutesPro\Admin\BackofficeUIFoundation::enabled();
        if ($foundationEnabled) {
            wp_enqueue_style(self::ADMIN_NAVIGATION_STYLE_HANDLE, ROUTESPRO_URL . 'assets/fieldflow-admin-navigation.css', [], ROUTESPRO_VERSION);
            wp_enqueue_script(self::ADMIN_NAVIGATION_SCRIPT_HANDLE, ROUTESPRO_URL . 'assets/fieldflow-admin-navigation.js', [], ROUTESPRO_VERSION, true);
            wp_localize_script(self::ADMIN_NAVIGATION_SCRIPT_HANDLE, 'FieldFlowAdminNavigationConfig', [
                'enabled' => true,
                'page' => \RoutesPro\Admin\BackofficeUIFoundation::currentPage(),
            ]);
        }
        // Admin Premium Shell v2 (2.2.410): mesma camada aditiva de
        // tipografia/espaço/forma/motion do front, agora para o BO em modo
        // Foundation. Kill switch próprio, independente do do front.
        if ($foundationEnabled && (!defined('FIELDFLOW_ADMIN_PREMIUM_SHELL_V2') || FIELDFLOW_ADMIN_PREMIUM_SHELL_V2)) {
            wp_enqueue_style(
                self::ADMIN_PREMIUM_SHELL_V2_STYLE_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-admin-premium-shell-v2.css',
                [self::ADMIN_COMPONENTS_STYLE_HANDLE, self::ADMIN_NAVIGATION_STYLE_HANDLE],
                ROUTESPRO_VERSION
            );
        }
        if ($foundationEnabled && \RoutesPro\Admin\BackofficeUIFoundation::isManagedPage()) {
            wp_enqueue_style(self::BACKOFFICE_UI_STYLE_HANDLE, ROUTESPRO_URL . 'assets/fieldflow-backoffice-ui.css', [self::ADMIN_COMPONENTS_STYLE_HANDLE], ROUTESPRO_VERSION);
            wp_enqueue_script(self::BACKOFFICE_UI_SCRIPT_HANDLE, ROUTESPRO_URL . 'assets/fieldflow-backoffice-ui.js', [self::PREMIUM_ANALYTICS_ADMIN_SCRIPT_HANDLE, self::ADMIN_COMPONENTS_SCRIPT_HANDLE], ROUTESPRO_VERSION, true);
            wp_localize_script(self::BACKOFFICE_UI_SCRIPT_HANDLE, 'FieldFlowBackofficeUIConfig', [
                'enabled' => true,
                'version' => ROUTESPRO_VERSION,
                'page' => \RoutesPro\Admin\BackofficeUIFoundation::currentPage(),
                'scope' => \RoutesPro\Admin\BackofficeUIFoundation::scope(),
                'diagnostics' => true,
                'visualAudit' => true,
            ]);
        }

        if ($foundationEnabled && class_exists('\\RoutesPro\\Admin\\ReactBackofficeFoundation') && \RoutesPro\Admin\ReactBackofficeFoundation::pageEnabled()) {
            wp_enqueue_style(
                self::REACT_BACKOFFICE_STYLE_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-react-backoffice.css',
                [self::BACKOFFICE_UI_STYLE_HANDLE],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                self::REACT_BACKOFFICE_SCRIPT_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-react-backoffice-2.2.658.js',
                [self::FRONTEND_CORE_SCRIPT_HANDLE, 'wp-element'],
                ROUTESPRO_VERSION,
                true
            );
            wp_localize_script(self::REACT_BACKOFFICE_SCRIPT_HANDLE, 'FieldFlowReactBackofficeConfig', [
                'version' => ROUTESPRO_VERSION,
                'contract' => \RoutesPro\Admin\ReactBackofficeFoundation::CONTRACT,
                'sharedContract' => \RoutesPro\Admin\ReactBackofficeFoundation::SHARED_CONTRACT,
                'page' => \RoutesPro\Admin\BackofficeUIFoundation::currentPage(),
                'pilotIsland' => \RoutesPro\Admin\ReactBackofficeFoundation::PILOT_ISLAND,
                'deviceIsland' => \RoutesPro\Admin\ReactBackofficeFoundation::DEVICES_ISLAND,
                'analyticsIsland' => \RoutesPro\Admin\ReactBackofficeFoundation::ANALYTICS_ISLAND,
                'fallback' => true,
            ]);
        }

        if ($foundationEnabled && \RoutesPro\Admin\BackofficeUIFoundation::scope() === 'analytics') {
            wp_enqueue_style(
                self::ANALYTICS2_ADMIN_STYLE_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-analytics2-admin.css',
                [self::BACKOFFICE_UI_STYLE_HANDLE, self::PREMIUM_ANALYTICS_ADMIN_STYLE_HANDLE],
                ROUTESPRO_VERSION
            );
            wp_enqueue_script(
                self::ANALYTICS2_ADMIN_SCRIPT_HANDLE,
                ROUTESPRO_URL . 'assets/fieldflow-analytics2-admin.js',
                [self::BACKOFFICE_UI_SCRIPT_HANDLE],
                ROUTESPRO_VERSION,
                true
            );
            wp_localize_script(self::ANALYTICS2_ADMIN_SCRIPT_HANDLE, 'FieldFlowAnalytics2Config', [
                'version' => ROUTESPRO_VERSION,
                'statsEndpoint' => rest_url('routespro/v1/stats'),
                'insightsEndpoint' => rest_url('routespro/v1/analytics/ai-insights'),
                'askEndpoint' => rest_url('routespro/v1/analytics/ask'),
                'storeEndpoint' => rest_url('routespro/v1/analytics/store-intelligence'),
                'nonce' => wp_create_nonce('wp_rest'),
                'labels' => [
                    'loading' => __('A calcular Analytics 2.0...', 'routes-pro'),
                    'error' => __('Não foi possível carregar a leitura analítica.', 'routes-pro'),
                ],
            ]);
        }
        $theme = class_exists('\RoutesPro\Support\Theme') ? \RoutesPro\Support\Theme::global() : [];
        if ($theme && class_exists('\RoutesPro\Support\Theme')) {
            $vars = \RoutesPro\Support\Theme::css_variables($theme);
            $parts = [];
            foreach ($vars as $key => $value) $parts[] = $key . ':' . $value;
            wp_add_inline_style(self::UNIFIED_ADMIN_STYLE_HANDLE, 'body.ff-fieldflow-admin{' . implode(';', $parts) . '}');
        }
    }

    public static function adminBodyClass(string $classes): string {
        if (!self::shouldLoadAdminRequest()) return $classes;
        $classes = trim($classes . ' ff-fieldflow-admin fieldflow-unified-layout-admin fieldflow-premium-analytics-admin');
        if (class_exists('\RoutesPro\Admin\BackofficeUIFoundation') && \RoutesPro\Admin\BackofficeUIFoundation::enabled()) {
            $classes .= ' ff-admin-navigation-foundation';
            $scope = \RoutesPro\Admin\BackofficeUIFoundation::scope();
            if ($scope !== '') $classes .= ' ff-admin-ui-foundation ff-admin-ui-consolidated ff-admin-scope-' . sanitize_html_class($scope);
        }
        return trim($classes);
    }

    private static function shouldLoadAdminRequest(string $hookSuffix = ''): bool {
        if (!is_admin()) return false;
        $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
        if ($page !== '' && (strpos($page, 'routespro') === 0 || strpos($page, 'fieldflow') === 0)) return true;
        return $hookSuffix !== '' && (strpos($hookSuffix, 'routespro') !== false || strpos($hookSuffix, 'fieldflow') !== false);
    }

    public static function shouldLoadForRequest(): bool {
        if (is_admin()) return false;
        if (self::isNative()) return true;

        $post = get_post();
        $content = $post ? (string)($post->post_content ?? '') : '';
        if ($content !== '' && self::containsFieldFlowShortcode($content)) return true;

        // Elementor stores shortcode widgets in post meta instead of post_content.
        $post_id = $post ? (int)$post->ID : (int)get_queried_object_id();
        if ($post_id > 0) {
            $elementor = get_post_meta($post_id, '_elementor_data', true);
            if (is_string($elementor) && self::containsFieldFlowReference($elementor)) return true;

            if (class_exists('\RoutesPro\PWA\Settings')) {
                $settings = \RoutesPro\PWA\Settings::get();
                $app_pages = [absint($settings['app_page_id'] ?? 0), absint($settings['client_app_page_id'] ?? 0)];
                if (in_array($post_id, $app_pages, true)) return true;
            }
        }

        return (bool) apply_filters('fieldflow_force_responsive_runtime', false, $post);
    }


    public static function needsFormRuntime(): bool {
        if (is_admin()) return false;
        if (self::isNative()) return true;
        $formShortcodes = [
            'fieldflow_app',
            'fieldflow_report_visit',
            'fieldflow_route_today',
            'fieldflow_form',
            'fieldflow_route_form',
        ];
        $post = get_post();
        $content = $post ? (string)($post->post_content ?? '') : '';
        foreach ($formShortcodes as $shortcode) {
            if ($content !== '' && (has_shortcode($content, $shortcode) || has_shortcode($content, str_replace('fieldflow_', 'routespro_', $shortcode)))) return true;
        }
        $postId = $post ? (int)$post->ID : (int)get_queried_object_id();
        if ($postId > 0) {
            $elementor = get_post_meta($postId, '_elementor_data', true);
            if (is_string($elementor)) {
                foreach ($formShortcodes as $shortcode) {
                    if (strpos($elementor, $shortcode) !== false || strpos($elementor, str_replace('fieldflow_', 'routespro_', $shortcode)) !== false) return true;
                }
            }
            if (class_exists('\RoutesPro\PWA\Settings')) {
                $settings = \RoutesPro\PWA\Settings::get();
                if ((int)($settings['app_page_id'] ?? 0) === $postId) return true;
            }
        }
        return (bool)apply_filters('fieldflow_force_form_runtime', false, $post);
    }

    private static function containsFieldFlowShortcode(string $content): bool {
        foreach (self::$shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) return true;
            $legacy = str_replace('fieldflow_', 'routespro_', $shortcode);
            if ($legacy !== $shortcode && has_shortcode($content, $legacy)) return true;
        }
        return false;
    }

    private static function containsFieldFlowReference(string $content): bool {
        if (strpos($content, 'fieldflow_') === false && strpos($content, 'routespro_') === false) return false;
        foreach (self::$shortcodes as $shortcode) {
            if (strpos($content, $shortcode) !== false) return true;
            $legacy = str_replace('fieldflow_', 'routespro_', $shortcode);
            if ($legacy !== $shortcode && strpos($content, $legacy) !== false) return true;
        }
        return false;
    }

    private static function isNative(): bool {
        if (defined('FIELDFLOW_NATIVE_WEBVIEW') && FIELDFLOW_NATIVE_WEBVIEW) return true;
        return class_exists('\\RoutesPro\\Mobile\\WebViewBridge')
            && \RoutesPro\Mobile\WebViewBridge::is_native_context();
    }

    private static function rootSelector(): string {
        return implode(',', [
            '.rp-premium-shell',
            '.rp-client-premium',
            '.routespro-dashboard',
            '.rp-front-routes',
            '.rp-front-commercial',
            '.rp-report-quick',
            '.rp-report-focus',
            '.rp-team-mail',
            '.rp-team-inbox',
            '.rp-wrap',
            '.ff-expenses',
            '.ff-briefings',
            '.ff-implementations',
            '.ff-stock-hub',
            '.ffp-front',
            '.ffp-academy',
            '.rp-form-ui',
            '.routespro-form',
        ]);
    }
}
