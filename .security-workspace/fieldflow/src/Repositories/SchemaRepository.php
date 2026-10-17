<?php
namespace RoutesPro\Repositories;

if (!defined('ABSPATH')) exit;

class SchemaRepository {
    public static function requiredTables(): array {
        return [
            'routespro_clients',
            'routespro_projects',
            'routespro_routes',
            'routespro_route_stops',
            'routespro_assignments',
            'routespro_scope_memberships',
            'routespro_scope_index_state',
            'routespro_categories',
            'routespro_locations',
            'routespro_campaign_locations',
            'routespro_forms',
            'routespro_form_submissions',
            'routespro_form_bindings',
            'routespro_email_logs',
            'routespro_system_logs',
            'routespro_process_runs',
            'routespro_process_circuits',
            'routespro_queue_jobs',
            'routespro_ai_usage_logs',
            'routespro_privacy_requests',
            'routespro_privacy_retention_runs',
            'routespro_core_events',
            'routespro_cloud_sync_jobs',
            'routespro_cloud_delivery_jobs',
            'routespro_governance_ledger',
            'routespro_audit_log',
            'routespro_reconciliation_runs',
            'routespro_saas_plans',
            'routespro_client_subscriptions',
            'routespro_usage_counters',
            'routespro_security_events',
            'routespro_observability_events',
            'routespro_performance_samples',
            'routespro_system_alerts',
            'routespro_mobile_device_controls',
            'routespro_api_keys',
            'routespro_webhooks',
            'routespro_webhook_deliveries',
            'routespro_billing_prices',
            'routespro_billing_customers',
            'routespro_billing_subscriptions',
            'routespro_billing_invoices',
            'routespro_billing_events',
            'routespro_billing_checkout_sessions',
            'routespro_billing_signup_intents',
        ];
    }

    public static function existingTables(): array {
        global $wpdb;
        $rows = $wpdb->get_col('SHOW TABLES');
        return is_array($rows) ? array_values($rows) : [];
    }

    public static function missingTables(): array {
        global $wpdb;
        $existing = array_flip(self::existingTables());
        $missing = [];
        foreach (self::requiredTables() as $table) {
            $full = $wpdb->prefix . $table;
            if (!isset($existing[$full])) {
                $missing[] = $full;
            }
        }
        return $missing;
    }

    public static function tableRowCount(string $suffix): int {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) return 0;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}
