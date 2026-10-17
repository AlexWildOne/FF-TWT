<?php
namespace RoutesPro\Reporting;

if (!defined('ABSPATH')) exit;

/**
 * Phase 4B.1 reporting contract.
 *
 * WordPress never stores Azure SQL or Power BI credentials here. The class
 * only publishes the contract expected by the external reporting layer so
 * the operational plugin remains decoupled from analytics credentials.
 */
final class ReportingFoundation {
    public const VERSION = '1.1.0';
    public const DATA_HUB_SCHEMA_VERSION = '1.5.0';
    public const SQL_SCHEMA = 'rpt';
    public const RECOMMENDED_MODE = 'directquery_entra_sso';

    public static function secureViews(): array {
        return [
            'rpt.clients',
            'rpt.projects',
            'rpt.locations',
            'rpt.operatives',
            'rpt.routes',
            'rpt.visits',
            'rpt.execution_daily',
            'rpt.operative_execution_daily',
            'rpt.route_execution',
            'rpt.submissions',
            'rpt.submission_answers',
            'rpt.submission_daily',
            'rpt.briefings',
            'rpt.briefing_questions',
            'rpt.briefing_execution',
            'rpt.stocks',
            'rpt.stock_latest',
            'rpt.stock_by_location',
            'rpt.execution_360',
            'rpt.model_health',
            'rpt.whoami',
            'rpt.my_scopes',
        ];
    }

    public static function status(): array {
        return [
            'version' => self::VERSION,
            'data_hub_schema_version' => self::DATA_HUB_SCHEMA_VERSION,
            'sql_schema' => self::SQL_SCHEMA,
            'recommended_mode' => self::RECOMMENDED_MODE,
            'secure_views' => self::secureViews(),
            'wordpress_credentials_required' => false,
            'supports_directquery_sso' => true,
            'supports_import_rls' => true,
        ];
    }
}
