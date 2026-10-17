<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$fieldflow_cron_hooks = [
    'fieldflow_report_automation_run',
    'fieldflow_form_draft_cleanup',
    'fieldflow_delete_submitted_draft',
    'fieldflow_webhook_worker',
    'fieldflow_billing_maintenance',
    'fieldflow_observability_hourly',
    'fieldflow_observability_cleanup',
    'fieldflow_api_usage_cleanup',
    'fieldflow_media_telemetry_cleanup',
    'fieldflow_mobile_session_cleanup',
    'fieldflow_mobile_webview_cleanup',
    'fieldflow_briefing_ai_process',
    'fieldflow_reconcile_submission_cardex',
    'fieldflow_reconcile_submission_history',
    'fieldflow_reconcile_route_after_submission',
    'fieldflow_submission_post_commit',
    'fieldflow_process_monitor_cleanup',
    'fieldflow_privacy_retention_daily',
    'fieldflow_load_readiness_cleanup',
    'fieldflow_queue_dispatch',
    'fieldflow_queue_watchdog',
    'fieldflow_queue_cleanup',
    'fieldflow_azure_sync_dispatch',
    'fieldflow_azure_sync_cleanup',
    'fieldflow_schema_batch_run',
    'fieldflow_license_heartbeat',
];

if (function_exists('wp_clear_scheduled_hook')) {
    foreach ($fieldflow_cron_hooks as $hook) {
        wp_clear_scheduled_hook($hook);
    }
}
if (function_exists('as_unschedule_all_actions')) {
    as_unschedule_all_actions('fieldflow_queue_dispatch', [], 'fieldflow');
    as_unschedule_all_actions('fieldflow_azure_sync_dispatch', [], 'fieldflow');
}

// Safe mode is the default. It removes only runtime/version markers and leaves
// all operational and billing data intact.
if (!defined('FIELDFLOW_DELETE_DATA_ON_UNINSTALL') || !FIELDFLOW_DELETE_DATA_ON_UNINSTALL) {
    foreach ([
        'routespro_version',
        'fieldflow_upgrade_lock',
        'fieldflow_schema_migration_lock',
        'fieldflow_last_upgrade_error',
        'fieldflow_load_readiness',
        'fieldflow_load_readiness_last_probe',
        'fieldflow_queue_modernization',
        'fieldflow_queue_last_rollback',
        'fieldflow_queue_last_probe',
        'fieldflow_queue_worker_lock',
        'fieldflow_queue_worker_heartbeat',
        'fieldflow_last_schema_migration_error',
        'fieldflow_schema_integrity_settings',
        'fieldflow_schema_integrity_last_report',
        'fieldflow_schema_integrity_quick_report',
        'fieldflow_release_gate_last_report',
        'fieldflow_release_gate_history',
        'fieldflow_phase6b2_installation_identity',
        'fieldflow_phase6b2_heartbeat_state',
        'fieldflow_phase6b2_certification_state',
    ] as $option_name) {
        delete_option($option_name);
    }
    return;
}

$fieldflow_drop_tables = static function () use ($wpdb): void {
    $like = $wpdb->esc_like($wpdb->prefix . 'routespro_') . '%';
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $like)) ?: [];
    foreach ($tables as $table) {
        $table = (string) $table;
        if (!preg_match('/^' . preg_quote($wpdb->prefix, '/') . 'routespro_[A-Za-z0-9_]+$/', $table)) {
            continue;
        }
        $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '', $table) . '`');
    }
};
$fieldflow_drop_tables();

$fieldflow_delete_prefixed_rows = static function (string $table, string $column) use ($wpdb): void {
    if ($table === '' || $column === '') return;
    $patterns = [
        'routespro\_%',
        'fieldflow\_%',
        '_transient_routespro\_%',
        '_transient_timeout_routespro\_%',
        '_transient_fieldflow\_%',
        '_transient_timeout_fieldflow\_%',
        '_site_transient_routespro\_%',
        '_site_transient_timeout_routespro\_%',
        '_site_transient_fieldflow\_%',
        '_site_transient_timeout_fieldflow\_%',
    ];
    $clauses = [];
    $args = [];
    foreach ($patterns as $pattern) {
        $clauses[] = "{$column} LIKE %s";
        $args[] = $pattern;
    }
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE " . implode(' OR ', $clauses),
        ...$args
    ));
};

$fieldflow_delete_prefixed_rows($wpdb->options, 'option_name');
$fieldflow_delete_prefixed_rows($wpdb->usermeta, 'meta_key');
$fieldflow_delete_prefixed_rows($wpdb->postmeta, 'meta_key');

if (is_multisite()) {
    $fieldflow_delete_prefixed_rows($wpdb->sitemeta, 'meta_key');
}

foreach (wp_roles()->roles as $role_name => $_role_data) {
    $role = get_role($role_name);
    if (!$role) continue;
    $role->remove_cap('routespro_manage');
    $role->remove_cap('routespro_execute');
}
remove_role('route_agent');

$fieldflow_remove_tree = static function (string $path) use (&$fieldflow_remove_tree): void {
    if ($path === '' || !file_exists($path)) return;
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if (!is_array($items)) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fieldflow_remove_tree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
};

$uploads = wp_upload_dir();
$base_dir = (string)($uploads['basedir'] ?? '');
if ($base_dir !== '' && is_dir($base_dir)) {
    foreach ([
        'fieldflow-private-drafts',
        'fieldflow-private-expenses',
        'fieldflow-evidence-exports',
        'fieldflow-diagnostics',
        'fieldflow-exports',
        'routespro-exports',
    ] as $directory) {
        $fieldflow_remove_tree(trailingslashit($base_dir) . $directory);
    }
}
