<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

class Plugin {
    private const UPGRADE_LOCK_OPTION = 'fieldflow_upgrade_lock';
    private const UPGRADE_LOCK_TTL = 900;
    private const RUNTIME_ONLY_BASELINE = '2.2.448';

    public static function bootstrapUtf8Runtime(): void {
        if (function_exists('mb_internal_encoding')) {
            @mb_internal_encoding('UTF-8');
            @mb_http_output('UTF-8');
        }
        if (function_exists('ini_set')) {
            @ini_set('default_charset', 'UTF-8');
        }
    }

    public static function maybeActivateOnVersionChange(): void {
        $installed = (string) get_option('routespro_version', '');
        if ($installed === ROUTESPRO_VERSION) return;

        // Desde 2.2.450, releases apenas de runtime deixam de executar o Activator
        // completo quando o schema versionado já está atual. Isto evita centenas
        // de probes SQL e ALTER/CREATE de compatibilidade em cada update sem DDL.
        if (self::canUseRuntimeOnlyUpgrade($installed)) {
            self::completeRuntimeOnlyUpgrade($installed);
            return;
        }

        if (!self::acquireUpgradeLock($installed, ROUTESPRO_VERSION)) {
            Logger::warning('Atualização adiada porque já existe outro processo de upgrade ativo.', ['context_key' => 'upgrade'], ['from' => $installed, 'to' => ROUTESPRO_VERSION]);
            return;
        }

        try {
            Logger::info('Atualização automática do schema iniciada.', ['context_key' => 'upgrade'], ['from' => $installed, 'to' => ROUTESPRO_VERSION]);
            \RoutesPro\Activator::activate();
            $installedAfter = (string)get_option('routespro_version', '');
            if ($installedAfter !== ROUTESPRO_VERSION) {
                $schemaFailure = get_option('fieldflow_last_schema_migration_error', []);
                $detail = is_array($schemaFailure) ? (string)($schemaFailure['message'] ?? '') : '';
                throw new \RuntimeException($detail !== '' ? $detail : 'A versão do schema não foi confirmada após a atualização.');
            }
            self::applyStaticRoutingCostAuthority($installed);
            self::applyTollCostIntelligence($installed);
            self::applyPlacesGeocodingCostAuthority($installed);
            self::applyGoogleCostControlCenter($installed);
            delete_option('fieldflow_last_upgrade_error');
            Logger::info('Atualização automática do schema concluída.', ['context_key' => 'upgrade'], ['version' => ROUTESPRO_VERSION]);
        } catch (\Throwable $error) {
            update_option('fieldflow_last_upgrade_error', [
                'from' => $installed,
                'to' => ROUTESPRO_VERSION,
                'message' => substr($error->getMessage(), 0, 1000),
                'occurred_at' => current_time('mysql', true),
            ], false);
            Logger::captureThrowable('A atualização automática do schema falhou.', $error, ['context_key' => 'upgrade']);
        } finally {
            self::releaseUpgradeLock();
        }
    }

    private static function canUseRuntimeOnlyUpgrade(string $installed): bool {
        if ($installed === '' || !defined('ROUTESPRO_VERSION')) return false;
        if (version_compare($installed, ROUTESPRO_VERSION, '>=')) return false;
        if (version_compare($installed, self::RUNTIME_ONLY_BASELINE, '<')) return false;
        if (!class_exists('\\RoutesPro\\Support\\Migrations')) return false;

        $requiredSchema = \RoutesPro\Support\Migrations::latestVersion();
        $installedSchema = (string) get_option('routespro_schema_version', '0.0.0');
        return $requiredSchema !== '' && version_compare($installedSchema, $requiredSchema, '>=');
    }

    private static function completeRuntimeOnlyUpgrade(string $installed): void {
        $schema = class_exists('\\RoutesPro\\Support\\Migrations')
            ? \RoutesPro\Support\Migrations::latestVersion()
            : '';

        // Um timeout anterior pode deixar o lock legado presente. Nesta via não
        // existe DDL, por isso o lock do Activator não deve bloquear o bootstrap.
        self::releaseUpgradeLock();
        update_option('routespro_version', ROUTESPRO_VERSION, false);
        self::applyStaticRoutingCostAuthority($installed);
        self::applyTollCostIntelligence($installed);
        self::applyPlacesGeocodingCostAuthority($installed);
        self::applyGoogleCostControlCenter($installed);
        update_option('fieldflow_last_runtime_upgrade', [
            'from' => $installed,
            'to' => ROUTESPRO_VERSION,
            'schema_version' => $schema,
            'mode' => 'runtime_only',
            'completed_at' => current_time('mysql', true),
        ], false);
        delete_option('fieldflow_last_upgrade_error');

        Logger::info('Atualização de runtime concluída sem reinstalação do schema.', ['context_key' => 'upgrade'], [
            'from' => $installed,
            'to' => ROUTESPRO_VERSION,
            'schema_version' => $schema,
            'mode' => 'runtime_only',
        ]);
    }

    private static function applyStaticRoutingCostAuthority(string $installed): void {
        if (!defined('ROUTESPRO_VERSION') || version_compare((string)ROUTESPRO_VERSION, '2.2.582', '<')) return;
        if ($installed !== '' && version_compare($installed, '2.2.582', '>=')) return;
        if (!class_exists('\RoutesPro\Support\Config')) return;
        Config::mergeAndSave([
            'google_routes_preference' => 'TRAFFIC_UNAWARE',
            'google_route_matrix_enabled' => 0,
            'google_routes_waypoint_optimization_enabled' => 0,
            'google_routes_tolls_enabled' => 0,
            'routing_fallback_internal' => 1,
        ]);
        Config::clearRoutingCache();
        update_option('fieldflow_static_routing_cost_authority_migrated', [
            'from' => $installed,
            'to' => (string)ROUTESPRO_VERSION,
            'contract' => 'fieldflow-static-routing-cost-authority-v1',
            'completed_at' => current_time('mysql', true),
        ], false);
    }

    private static function applyTollCostIntelligence(string $installed): void {
        if (!defined('ROUTESPRO_VERSION') || version_compare((string)ROUTESPRO_VERSION, '2.2.583', '<')) return;
        if ($installed !== '' && version_compare($installed, '2.2.583', '>=')) return;
        if (!class_exists('\\RoutesPro\\Support\\Config')) return;
        // No routing cache purge here. Existing static route snapshots remain valid
        // and are not mass-refreshed just to activate the cheaper toll estimator.
        Config::mergeAndSave([
            'toll_intelligence_enabled' => 1,
            'toll_vehicle_class' => 1,
            'google_routes_tolls_enabled' => 0,
        ]);
        update_option('fieldflow_toll_cost_intelligence_migrated', [
            'from' => $installed,
            'to' => (string)ROUTESPRO_VERSION,
            'contract' => 'fieldflow-toll-cost-intelligence-v1',
            'completed_at' => current_time('mysql', true),
        ], false);
    }

    private static function applyPlacesGeocodingCostAuthority(string $installed): void {
        if (!defined('ROUTESPRO_VERSION') || version_compare((string)ROUTESPRO_VERSION, '2.2.584', '<')) return;
        if ($installed !== '' && version_compare($installed, '2.2.584', '>=')) return;
        if (!class_exists('\RoutesPro\Support\Config')) return;
        Config::mergeAndSave([
            'places_cost_authority_enabled' => 1,
            'google_places_contact_enrichment_enabled' => 0,
        ]);
        update_option('fieldflow_places_geocoding_cost_authority_migrated', [
            'from' => $installed,
            'to' => (string)ROUTESPRO_VERSION,
            'contract' => 'fieldflow-places-geocoding-cost-authority-v1',
            'completed_at' => current_time('mysql', true),
        ], false);
    }

    private static function applyGoogleCostControlCenter(string $installed): void {
        if (!defined('ROUTESPRO_VERSION') || version_compare((string)ROUTESPRO_VERSION, '2.2.585', '<')) return;
        if ($installed !== '' && version_compare($installed, '2.2.585', '>=')) return;
        if (!class_exists('\RoutesPro\Support\Config')) return;
        // Fail closed on premium Google routing tiers. Existing stored route
        // snapshots are preserved, so the migration itself generates no Maps call.
        Config::mergeAndSave([
            'google_cost_control_enabled' => 1,
            'google_cost_block_matrix' => 1,
            'google_cost_block_pro' => 1,
            'google_cost_block_enterprise' => 1,
            'google_cost_alert_premium' => 1,
            'google_routes_preference' => 'TRAFFIC_UNAWARE',
            'google_route_matrix_enabled' => 0,
            'google_routes_waypoint_optimization_enabled' => 0,
            'google_routes_tolls_enabled' => 0,
        ]);
        update_option('fieldflow_google_cost_control_center_migrated', [
            'from' => $installed,
            'to' => (string)ROUTESPRO_VERSION,
            'contract' => 'fieldflow-google-cost-control-center-v1',
            'completed_at' => current_time('mysql', true),
        ], false);
    }

    private static function acquireUpgradeLock(string $from, string $to): bool {
        $payload = [
            'token' => wp_generate_uuid4(),
            'from' => $from,
            'to' => $to,
            'started_at' => time(),
        ];
        if (add_option(self::UPGRADE_LOCK_OPTION, $payload, '', 'no')) return true;

        $existing = get_option(self::UPGRADE_LOCK_OPTION, []);
        $startedAt = is_array($existing) ? absint($existing['started_at'] ?? 0) : 0;
        if ($startedAt > 0 && (time() - $startedAt) > self::UPGRADE_LOCK_TTL) {
            delete_option(self::UPGRADE_LOCK_OPTION);
            return add_option(self::UPGRADE_LOCK_OPTION, $payload, '', 'no');
        }
        return false;
    }

    private static function releaseUpgradeLock(): void {
        delete_option(self::UPGRADE_LOCK_OPTION);
    }

    public const WORDPRESS_MEDIA_ISOLATION_CONTRACT = 'fieldflow-wordpress-media-isolation-v1';

    /**
     * True only for WordPress-owned media processing surfaces that are unrelated
     * to FieldFlow evidence transport. These requests are deliberately kept on a
     * lightweight plugin path so WordPress can dedicate memory to GD/Imagick.
     */
    public static function isWordPressMediaProcessingRequest(): bool {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === 'async-upload.php') return true;

        $actionRaw = $_REQUEST['action'] ?? '';
        $action = is_scalar($actionRaw) ? (string)$actionRaw : '';
        if (function_exists('sanitize_key')) $action = sanitize_key($action);
        else $action = strtolower((string)preg_replace('/[^a-zA-Z0-9_\-]/', '', $action));
        if (in_array($action, ['upload-attachment','image-editor'], true)) return true;

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $restRouteRaw = $_REQUEST['rest_route'] ?? '';
        $restRoute = is_scalar($restRouteRaw) ? (string)$restRouteRaw : '';
        if (preg_match('#(?:/wp-json)?/wp/v2/media(?:/|$|\?)#', $uri)) return true;
        if (preg_match('#^/wp/v2/media(?:/|$)#', $restRoute)) return true;

        return false;
    }

    public static function registerRoutes(): void {
        \RoutesPro\Architecture\RestRouteRegistry::register();
    }

    public static function registerWordPressHooks(): void {
        \RoutesPro\Architecture\RuntimeRegistry::register(
            FIELDFLOW_PATH . 'fieldflow.php',
            [self::class, 'registerRoutes']
        );
    }
}
