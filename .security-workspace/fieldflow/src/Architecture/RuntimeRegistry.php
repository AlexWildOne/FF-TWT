<?php
namespace RoutesPro\Architecture;

if (!defined('ABSPATH')) exit;

/**
 * Stable composition root for the FieldFlow runtime.
 *
 * Registration order intentionally mirrors the legacy bootstrap. Moving the
 * declarations here reduces coupling in Support\Plugin without changing hook
 * names, priorities, callbacks, REST contracts or public behaviour.
 */
final class RuntimeRegistry {
    public const VERSION = '1.0.7';
    private static bool $registered = false;

    public static function register(string $pluginFile, callable $routeRegistrar): void {
        if (self::$registered) return;
        self::$registered = true;
        self::registerModules();
        self::registerWordPressBindings($pluginFile, $routeRegistrar);
    }

    private static function registerModules(): void {
        \RoutesPro\Security\SessionPolicy::registerHooks();
        \RoutesPro\Security\Mfa::registerHooks();
        \RoutesPro\Security\ProtectedMedia::registerHooks();
        \RoutesPro\Mobile\Auth::register_hooks();
        \RoutesPro\Mobile\ContractRegistry::registerHooks();
        \RoutesPro\Mobile\WebViewBridge::register_hooks();
        \RoutesPro\Core\EventBus::registerHooks();
        \RoutesPro\Core\ReconciliationService::registerHooks();
        \RoutesPro\Mapping\MappingService::registerHooks();
        \RoutesPro\Shopping\ShoppingService::registerHooks();
        \RoutesPro\SaaS\EntitlementService::registerHooks();
        \RoutesPro\Security\RestScopeGuard::registerHooks();
        \RoutesPro\Security\StorageGovernance::registerHooks();
        \RoutesPro\Security\SecurityService::registerHooks();
        \RoutesPro\Observability\Monitor::registerHooks();
        \RoutesPro\Observability\ApiUsageLedger::registerHooks();
        \RoutesPro\Observability\MediaTelemetry::registerHooks();
        \RoutesPro\Operations\ProcessMonitor::registerHooks();
        \RoutesPro\Operations\Queue\QueueManager::registerHooks();
        \RoutesPro\Services\MailDispatchService::registerHooks();
        \RoutesPro\Cloud\AzureSyncFoundation::registerHooks();
        \RoutesPro\Cloud\MultiDestinationFoundation::registerHooks();
        \RoutesPro\Cloud\ClientAzureDelivery::registerHooks();
        \RoutesPro\Cloud\ConnectorDelivery::registerHooks();
        \RoutesPro\Cloud\SafeLivePilot::registerHooks();
        \RoutesPro\Cloud\ProductionDeliveryGate::registerHooks();
        \RoutesPro\Cloud\CredentialLifecycle::registerHooks();
        \RoutesPro\Cloud\GovernanceRecovery::registerHooks();
        \RoutesPro\Cloud\BriefingStockBackfill::registerHooks();
        \RoutesPro\Cloud\LicenseInstallationLifecycle::registerHooks();
        \RoutesPro\Cloud\LicenseGovernanceLifecycle::registerHooks();
        \RoutesPro\Cloud\PrivateUpdateLifecycle::registerHooks();
        \RoutesPro\Cloud\PrivatePluginUpdater::registerHooks();
        \RoutesPro\Cloud\OperativeBackfill::registerHooks();
        \RoutesPro\Support\BatchMigrationManager::registerHooks();
        \RoutesPro\Performance\LoadReadiness::registerHooks();
        \RoutesPro\Mobile\DeviceManager::registerHooks();
        \RoutesPro\Admin\SecurityObservability::registerHooks();
        \RoutesPro\Admin\SystemHealth::registerHooks();
        \RoutesPro\Admin\DataDestinations::registerHooks();
        \RoutesPro\Admin\ClientOnboarding::registerHooks();
        \RoutesPro\Admin\ProcessCenter::registerHooks();
        \RoutesPro\Admin\SchemaIntegrityCenter::registerHooks();
        \RoutesPro\Admin\RouteOptimizationReconciliation::registerHooks();
        \RoutesPro\Integrations\WebhookService::registerHooks();
        \RoutesPro\Admin\PublicIntegrations::registerHooks();
        \RoutesPro\Billing\RoleMappingService::registerHooks();
        \RoutesPro\Billing\SignupService::registerHooks();
        \RoutesPro\Billing\ProductionHardening::registerHooks();
        \RoutesPro\Billing\BillingService::registerHooks();
        \RoutesPro\Admin\Billing::registerHooks();
        \RoutesPro\Admin\MappingCenter::register_hooks();
        \RoutesPro\Admin\BackofficeUIFoundation::registerHooks();
        \RoutesPro\Desktop\DesktopPortal::registerHooks();
        \RoutesPro\Privacy\PrivacyGovernance::registerHooks();
        \RoutesPro\Localization\LanguageService::registerHooks();
        \RoutesPro\Expenses\ExpenseSettlementService::registerHooks();
        \RoutesPro\Catalog\ProductCatalogService::registerHooks();
        \RoutesPro\Vision\FrontsVisionService::registerHooks();
        \RoutesPro\Front\ThemeIsolation::registerHooks();
    }

    private static function registerWordPressBindings(string $pluginFile, callable $routeRegistrar): void {
        add_action('admin_menu', ['RoutesPro\\Admin\\Menu', 'register']);
        add_action('admin_menu', ['RoutesPro\\Admin\\Menu', 'ensureSystemTools'], 2000);
        add_action('init', ['RoutesPro\\PWA\\Router', 'register']);
        add_action('init', ['RoutesPro\\PWA\\Assets', 'register']);
        add_action('init', ['RoutesPro\\PWA\\InstallPrompt', 'register']);
        add_action('init', ['RoutesPro\\PWA\\Push', 'register']);
        add_action('init', ['RoutesPro\\Front\\ResponsiveAssets', 'register']);
        add_action('init', ['RoutesPro\\Front\\PanelLoader', 'register']);
        add_action('init', ['RoutesPro\\Shortcodes', 'register']);
        add_action('init', ['RoutesPro\\Forms\\Forms', 'boot']);
        add_action('admin_init', ['RoutesPro\\Admin\\Forms', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Stores', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Routes', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\FormSubmissions', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\FormBindings', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\ProductCardex', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\ProductCatalog', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\FrontsVisionAdmin', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Expenses', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Briefings', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Implementations', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Stocks', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Shopping', 'register_hooks']);
        add_action('init', ['RoutesPro\\Admin\\ReportAutomations', 'register_hooks']);
        add_action('admin_init', ['RoutesPro\\Admin\\Branding', 'enqueue_menu_branding']);
        add_action('admin_init', ['RoutesPro\\Support\\LicenseManager', 'maybeValidateOnBootstrap']);
        add_action('plugins_loaded', ['RoutesPro\\Stocks\\StockSchema', 'ensureOnBootstrap'], 5);
        add_action('plugins_loaded', ['RoutesPro\\Support\\Plugin', 'maybeActivateOnVersionChange'], 10);
        add_action('plugins_loaded', ['RoutesPro\\Stocks\\StockService', 'ensureSnapshotPolicyMigration'], 11);
        add_action('plugins_loaded', ['RoutesPro\\Integrations\\CredentialStore', 'maybeMigrateKnownOptions'], 12);
        add_action('plugins_loaded', ['RoutesPro\\Performance\\Module', 'maybe_install']);
        add_action('init', ['RoutesPro\\Performance\\Module', 'register_hooks']);
        add_action('init', ['RoutesPro\\Analytics\\AnalyticsAIService', 'registerHooks']);
        add_action('init', ['RoutesPro\\Analytics\\OperationalIntelligenceService', 'registerHooks']);
        add_action('init', ['RoutesPro\\Analytics\\RecommendationLearningService', 'registerHooks']);
        add_action('init', ['RoutesPro\\Briefings\\BriefingAIService', 'registerHooks']);
        add_action('admin_post_routespro_download_analytics_evidence', ['RoutesPro\\Rest\\StatsController', 'handleEvidenceDownload']);
        add_action('admin_post_routespro_download_prepared_analytics_evidence', ['RoutesPro\\Rest\\StatsController', 'handlePreparedEvidenceDownload']);
        add_action('template_redirect', ['RoutesPro\\Rest\\StatsController', 'handlePreparedEvidencePublicDownload'], -5);
        add_action('wp_ajax_fieldflow_prepare_expense_statement_ticket', ['RoutesPro\\Admin\\Expenses', 'prepareStatementTicket']);
        add_action('template_redirect', ['RoutesPro\\Admin\\Expenses', 'handleStatementTicketDownload'], -6);
        add_action('fieldflow_expense_statement_ticket_cleanup', ['RoutesPro\\Admin\\Expenses', 'cleanupStatementTicketFile'], 10, 1);
        add_action('plugins_loaded', ['RoutesPro\\Support\\AdminNotices', 'register']);
        add_action('init', static function () use ($pluginFile): void {
            load_plugin_textdomain('routes-pro', false, dirname(plugin_basename($pluginFile)) . '/languages');
        });
        add_action('rest_api_init', $routeRegistrar);
        add_action('elementor/widgets/register', static function ($widgetsManager): void {
            if (class_exists('\\RoutesPro\\Elementor\\Register')) {
                \RoutesPro\Elementor\Register::register_widgets($widgetsManager);
            }
        });
    }

    public static function status(): array {
        $routeStatus = RestRouteRegistry::status();
        return [
            'version'=>self::VERSION,
            'registered'=>self::$registered,
            'composition_root'=>'RoutesPro\\Architecture\\RuntimeRegistry',
            'rest_controllers'=>(int)$routeStatus['registered_controllers'],
            'missing_controllers'=>(array)$routeStatus['missing_controllers'],
            'legacy_fallback'=>true,
        ];
    }
}
