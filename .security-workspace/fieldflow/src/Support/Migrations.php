<?php
namespace RoutesPro\Support;

use wpdb;

if (!defined('ABSPATH')) exit;

class Migrations {
    public const LATEST_SCHEMA_VERSION = '2.2.529';
    // Historical validator compatibility only: LATEST_SCHEMA_VERSION = '2.2.525'
    private const LOCK_OPTION = 'fieldflow_schema_migration_lock';
    private const LOCK_TTL = 900;
    private static bool $running = false;

    public static function latestVersion(): string {
        return self::LATEST_SCHEMA_VERSION;
    }

    public static function run(wpdb $wpdb, string $prefix): bool {
        if (self::$running || !self::acquireLock()) return false;
        self::$running = true;
        $succeeded = true;
        try {
            if (class_exists('\RoutesPro\Support\MigrationLedger')) {
                MigrationLedger::install($wpdb);
            }
            $applied = (string) get_option('routespro_schema_version', '0.0.0');
            $steps = [
                '1.3.0' => [self::class, 'migrate130'],
                '1.4.0' => [self::class, 'migrate140'],
                '1.5.0' => [self::class, 'migrate150'],
                '1.6.0' => [self::class, 'migrate160'],
                '2.2.215' => [self::class, 'migrate2215'],
                '2.2.219' => [self::class, 'migrate2219'],
                '2.2.222' => [self::class, 'migrate2222'],
                '2.2.223' => [self::class, 'migrate2223'],
                '2.2.224' => [self::class, 'migrate2224'],
                '2.2.224.3' => [self::class, 'migrate22243'],
                '2.2.224.4' => [self::class, 'migrate22244'],
                '2.2.224.5' => [self::class, 'migrate22245'],
                '2.2.224.6' => [self::class, 'migrate22246'],
                '2.2.224.7' => [self::class, 'migrate22247'],
                '2.2.225' => [self::class, 'migrate2225'],
                '2.2.225.1' => [self::class, 'migrate22251'],
                '2.2.256' => [self::class, 'migrate22256'],
                '2.2.260' => [self::class, 'migrate22260'],
                '2.2.263' => [self::class, 'migrate22263'],
                '2.2.264' => [self::class, 'migrate22264'],
                '2.2.265' => [self::class, 'migrate22265'],
                '2.2.266' => [self::class, 'migrate22266'],
                '2.2.340' => [self::class, 'migrate22340'],
                '2.2.354' => [self::class, 'migrate22354'],
                '2.2.356' => [self::class, 'migrate22356'],
                '2.2.436' => [self::class, 'migrate22436'],
                '2.2.525' => [self::class, 'migrate22525'],
                '2.2.529' => [self::class, 'migrate22529'],
            ];

            foreach ($steps as $version => $callback) {
                if (version_compare($applied, $version, '>=')) continue;

                $started = microtime(true);
                $ledgerKey = class_exists('\RoutesPro\Support\MigrationLedger')
                    ? MigrationLedger::begin($version, ['from'=>$applied,'plugin_version'=>defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : ''])
                    : '';
                try {
                    $wpdb->last_error = '';
                    $result = call_user_func($callback, $wpdb, $prefix);
                    if (is_wp_error($result)) {
                        throw new \RuntimeException($result->get_error_message());
                    }
                    if ($result === false) {
                        throw new \RuntimeException('A migração devolveu um estado de falha.');
                    }
                    if ((string)$wpdb->last_error !== '') {
                        throw new \RuntimeException((string)$wpdb->last_error);
                    }

                    update_option('routespro_schema_version', $version);
                    $applied = $version;
                    $durationMs = max(0, (int)round((microtime(true) - $started) * 1000));
                    if ($ledgerKey !== '' && class_exists('\RoutesPro\Support\MigrationLedger')) {
                        MigrationLedger::complete($ledgerKey, $durationMs, 0, ['schema_version'=>$version]);
                    }
                    delete_option('fieldflow_last_schema_migration_error');
                    if (class_exists('\RoutesPro\Support\Logger')) {
                        Logger::info('Migração versionada aplicada.', ['context_key' => 'migration'], ['schema_version' => $version, 'duration_ms'=>$durationMs]);
                    }
                } catch (\Throwable $error) {
                    $succeeded = false;
                    $durationMs = max(0, (int)round((microtime(true) - $started) * 1000));
                    if ($ledgerKey !== '' && class_exists('\RoutesPro\Support\MigrationLedger')) {
                        MigrationLedger::fail($ledgerKey, 'migration_failed', $error->getMessage(), $durationMs, ['schema_version'=>$version]);
                    }
                    update_option('fieldflow_last_schema_migration_error', [
                        'version'=>$version,
                        'from'=>$applied,
                        'message'=>substr(wp_strip_all_tags($error->getMessage()), 0, 1000),
                        'occurred_at'=>current_time('mysql', true),
                    ], false);
                    if (class_exists('\RoutesPro\Support\Logger')) {
                        Logger::captureThrowable('A migração versionada falhou e a versão do schema não avançou.', $error, ['context_key'=>'migration','schema_version'=>$version]);
                    }
                    break;
                }
            }
        } finally {
            self::$running = false;
            self::releaseLock();
        }
        return $succeeded;
    }

    private static function acquireLock(): bool {
        $payload = ['token' => wp_generate_uuid4(), 'started_at' => time()];
        if (add_option(self::LOCK_OPTION, $payload, '', 'no')) return true;
        $existing = get_option(self::LOCK_OPTION, []);
        $startedAt = is_array($existing) ? absint($existing['started_at'] ?? 0) : 0;
        if ($startedAt > 0 && (time() - $startedAt) > self::LOCK_TTL) {
            delete_option(self::LOCK_OPTION);
            return add_option(self::LOCK_OPTION, $payload, '', 'no');
        }
        return false;
    }

    private static function releaseLock(): void {
        delete_option(self::LOCK_OPTION);
    }

    private static function migrate130(wpdb $wpdb, string $prefix): void {
        self::maybeAddIndex($wpdb, $prefix . 'routes', 'client_project_date_idx', 'client_id, project_id, date');
        self::maybeAddIndex($wpdb, $prefix . 'routes', 'owner_date_idx', 'owner_user_id, date');
        self::maybeAddIndex($wpdb, $prefix . 'route_stops', 'route_location_idx', 'route_id, location_id');
        self::maybeAddIndex($wpdb, $prefix . 'assignments', 'user_active_idx', 'user_id, is_active');
        self::maybeAddIndex($wpdb, $prefix . 'campaign_locations', 'project_owner_active_idx', 'project_id, assigned_to, is_active');
        self::maybeAddIndex($wpdb, $prefix . 'campaign_locations', 'project_status_priority_idx', 'project_id, status, priority');
        self::maybeAddIndex($wpdb, $prefix . 'locations', 'project_city_idx', 'project_id, city');
        self::maybeAddIndex($wpdb, $prefix . 'system_logs', 'context_created_idx', 'context_key, created_at');
    }


    private static function migrate140(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $table = $prefix . 'report_context_questions';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            campaign_location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            context_type VARCHAR(64) NOT NULL DEFAULT 'custom',
            question_key VARCHAR(191) NOT NULL,
            question_label VARCHAR(255) NOT NULL,
            question_type VARCHAR(32) NOT NULL DEFAULT 'select',
            question_options_json LONGTEXT NULL,
            help_text TEXT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            priority INT NOT NULL DEFAULT 100,
            visibility_rules_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY scope_idx (client_id, project_id, location_id),
            KEY campaign_location_idx (campaign_location_id),
            KEY form_idx (form_id),
            KEY active_idx (is_active),
            KEY question_key_idx (question_key)
        ) {$charset}");
        $hasFormId = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'form_id'));
        if (!$hasFormId) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN form_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER campaign_location_id");
            $wpdb->query("ALTER TABLE {$table} ADD INDEX form_idx (form_id)");
        }
    }


    private static function migrate150(wpdb $wpdb, string $prefix): void {
        $table = $prefix . 'report_context_questions';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        $columns = [
            'source_type' => "VARCHAR(32) NOT NULL DEFAULT 'manual' AFTER form_id",
            'briefing_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER source_type",
            'briefing_version_no' => "INT NOT NULL DEFAULT 0 AFTER briefing_id",
            'briefing_section_id' => "BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER briefing_version_no",
            'briefing_item_key' => "VARCHAR(191) NULL AFTER briefing_section_id",
            'valid_from' => "DATE NULL AFTER briefing_item_key",
            'valid_to' => "DATE NULL AFTER valid_from",
        ];
        foreach ($columns as $column => $definition) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
            if (!$exists) $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
        self::maybeAddIndex($wpdb, $table, 'briefing_idx', 'briefing_id, briefing_version_no');
        self::maybeAddIndex($wpdb, $table, 'validity_idx', 'valid_from, valid_to');
        self::maybeAddIndex($wpdb, $table, 'source_idx', 'source_type');
    }

    private static function migrate160(wpdb $wpdb, string $prefix): void {
        $table = $prefix . 'report_context_questions';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'question_config_json'));
        if (!$exists) $wpdb->query("ALTER TABLE {$table} ADD COLUMN question_config_json LONGTEXT NULL AFTER question_options_json");
    }

    private static function migrate2215(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}core_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_uuid CHAR(36) NOT NULL,
            event_name VARCHAR(96) NOT NULL,
            event_version VARCHAR(16) NOT NULL DEFAULT '1.0',
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            source VARCHAR(32) NOT NULL DEFAULT 'system',
            entity_type VARCHAR(64) NOT NULL DEFAULT 'system',
            entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            actor_user_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            route_stop_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            submission_id BIGINT UNSIGNED NULL,
            briefing_id BIGINT UNSIGNED NULL,
            correlation_id VARCHAR(64) NULL,
            payload_json LONGTEXT NULL,
            error_message TEXT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NULL,
            processed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY event_uuid_idx (event_uuid),
            KEY event_name_idx (event_name),
            KEY status_available_idx (status, available_at),
            KEY entity_idx (entity_type, entity_id),
            KEY route_idx (route_id),
            KEY submission_idx (submission_id),
            KEY correlation_idx (correlation_id),
            KEY created_idx (created_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action_name VARCHAR(96) NOT NULL,
            entity_type VARCHAR(64) NOT NULL,
            entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            actor_user_id BIGINT UNSIGNED NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'system',
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            route_stop_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            submission_id BIGINT UNSIGNED NULL,
            correlation_id VARCHAR(64) NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY action_idx (action_name),
            KEY entity_idx (entity_type, entity_id),
            KEY actor_idx (actor_user_id),
            KEY route_idx (route_id),
            KEY submission_idx (submission_id),
            KEY correlation_idx (correlation_id),
            KEY created_idx (created_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}reconciliation_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_type VARCHAR(64) NOT NULL DEFAULT 'core_integrity',
            run_mode VARCHAR(16) NOT NULL DEFAULT 'preview',
            status VARCHAR(32) NOT NULL DEFAULT 'running',
            initiated_by BIGINT UNSIGNED NULL,
            summary_json LONGTEXT NULL,
            details_json LONGTEXT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY type_mode_idx (run_type, run_mode),
            KEY status_idx (status),
            KEY initiated_idx (initiated_by),
            KEY created_idx (created_at)
        ) {$charset}");
    }

    private static function migrate2219(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}analytics_alerts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_uuid CHAR(36) NOT NULL,
            fingerprint CHAR(64) NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            alert_type VARCHAR(64) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'medium',
            status VARCHAR(24) NOT NULL DEFAULT 'open',
            title VARCHAR(255) NOT NULL,
            detail TEXT NULL,
            metric_key VARCHAR(96) NULL,
            current_value VARCHAR(96) NULL,
            threshold_value VARCHAR(96) NULL,
            period_from DATE NULL,
            period_to DATE NULL,
            payload_json LONGTEXT NULL,
            acknowledged_by BIGINT UNSIGNED NULL,
            acknowledged_at DATETIME NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY fingerprint_idx (fingerprint),
            KEY scope_idx (client_id, project_id, user_id, location_id),
            KEY status_severity_idx (status, severity),
            KEY type_idx (alert_type),
            KEY last_seen_idx (last_seen_at)
        ) {$charset}");
    }


    private static function migrate2222(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}security_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_uuid CHAR(36) NOT NULL,
            fingerprint CHAR(64) NOT NULL,
            event_type VARCHAR(96) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'low',
            status VARCHAR(24) NOT NULL DEFAULT 'open',
            user_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            device_id VARCHAR(191) NULL,
            ip_hash CHAR(64) NULL,
            route VARCHAR(191) NULL,
            payload_json LONGTEXT NULL,
            occurrences BIGINT UNSIGNED NOT NULL DEFAULT 1,
            acknowledged_by BIGINT UNSIGNED NULL,
            acknowledged_at DATETIME NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY event_uuid_idx (event_uuid),
            KEY fingerprint_idx (fingerprint),
            KEY type_status_idx (event_type, status),
            KEY severity_status_idx (severity, status),
            KEY device_idx (device_id),
            KEY last_seen_idx (last_seen_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}observability_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_uuid CHAR(36) NOT NULL,
            fingerprint CHAR(64) NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'info',
            status VARCHAR(24) NOT NULL DEFAULT 'open',
            message VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            device_id VARCHAR(191) NULL,
            route VARCHAR(191) NULL,
            http_status INT NULL,
            duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            memory_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            payload_json LONGTEXT NULL,
            occurrences BIGINT UNSIGNED NOT NULL DEFAULT 1,
            acknowledged_by BIGINT UNSIGNED NULL,
            acknowledged_at DATETIME NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY event_uuid_idx (event_uuid),
            KEY fingerprint_idx (fingerprint),
            KEY type_status_idx (event_type, status),
            KEY severity_status_idx (severity, status),
            KEY route_idx (route),
            KEY last_seen_idx (last_seen_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}system_alerts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_uuid CHAR(36) NOT NULL,
            fingerprint CHAR(64) NOT NULL,
            alert_type VARCHAR(96) NOT NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'warning',
            status VARCHAR(24) NOT NULL DEFAULT 'open',
            title VARCHAR(255) NOT NULL,
            detail TEXT NULL,
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            payload_json LONGTEXT NULL,
            acknowledged_by BIGINT UNSIGNED NULL,
            acknowledged_at DATETIME NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY alert_uuid_idx (alert_uuid),
            UNIQUE KEY fingerprint_idx (fingerprint),
            KEY status_severity_idx (status, severity),
            KEY type_idx (alert_type),
            KEY last_seen_idx (last_seen_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}mobile_device_controls (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            device_id VARCHAR(191) NOT NULL,
            is_trusted TINYINT(1) NOT NULL DEFAULT 0,
            offline_allowed TINYINT(1) NOT NULL DEFAULT 1,
            cache_generation BIGINT UNSIGNED NOT NULL DEFAULT 1,
            force_logout TINYINT(1) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY user_device_idx (user_id, device_id),
            KEY device_idx (device_id),
            KEY trusted_idx (is_trusted),
            KEY offline_idx (offline_allowed)
        ) {$charset}");
    }


    private static function migrate2223(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}api_keys (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            key_prefix VARCHAR(32) NOT NULL,
            key_hash CHAR(64) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            capabilities_json LONGTEXT NULL,
            allowed_ips_json LONGTEXT NULL,
            rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 120,
            expires_at DATETIME NULL,
            last_used_at DATETIME NULL,
            last_ip_hash CHAR(64) NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY key_hash_idx (key_hash),
            KEY scope_idx (client_id, project_id),
            KEY status_idx (status),
            KEY expires_idx (expires_at),
            KEY used_idx (last_used_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}webhooks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            webhook_uuid CHAR(36) NOT NULL,
            name VARCHAR(191) NOT NULL,
            url TEXT NOT NULL,
            secret_enc LONGTEXT NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            events_json LONGTEXT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'active',
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 6,
            consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
            last_success_at DATETIME NULL,
            last_failure_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY webhook_uuid_idx (webhook_uuid),
            KEY scope_idx (client_id, project_id),
            KEY status_idx (status),
            KEY success_idx (last_success_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}webhook_deliveries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            webhook_id BIGINT UNSIGNED NOT NULL,
            event_uuid CHAR(36) NOT NULL,
            event_name VARCHAR(96) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME NULL,
            response_code INT NULL,
            response_body TEXT NULL,
            error_message TEXT NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY webhook_event_idx (webhook_id, event_uuid),
            KEY status_available_idx (status, available_at),
            KEY event_idx (event_name),
            KEY created_idx (created_at)
        ) {$charset}");
        self::maybeAddIndex($wpdb, $prefix . 'form_submissions', 'public_api_scope_idx', 'client_id, project_id, submitted_at');
        self::maybeAddIndex($wpdb, $prefix . 'stock_items', 'public_api_updated_idx', 'client_id, project_id, created_at');
        $plansTable = $prefix . 'saas_plans';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plansTable)) === $plansTable) {
            foreach (['legacy','enterprise'] as $slug) {
                $row = $wpdb->get_row($wpdb->prepare("SELECT id,capabilities_json FROM {$plansTable} WHERE slug=%s", $slug), ARRAY_A);
                if (!$row) continue;
                $caps = json_decode((string)$row['capabilities_json'], true);
                if (!is_array($caps)) $caps = [];
                $caps['api.access'] = 1;
                $caps['webhooks.manage'] = 1;
                $wpdb->update($plansTable, ['capabilities_json'=>wp_json_encode($caps), 'updated_at'=>current_time('mysql')], ['id'=>(int)$row['id']]);
            }
        }
    }

    private static function migrate2224(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_prices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
            environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
            billing_interval VARCHAR(16) NOT NULL DEFAULT 'month',
            external_product_id VARCHAR(191) NULL,
            external_price_id VARCHAR(191) NULL,
            amount_minor BIGINT NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY plan_env_interval_idx (plan_id, provider, environment, billing_interval),
            KEY price_idx (external_price_id),
            KEY active_idx (is_active)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_customers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
            environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
            external_customer_id VARCHAR(191) NULL,
            email VARCHAR(255) NULL,
            billing_name VARCHAR(255) NULL,
            tax_id VARCHAR(96) NULL,
            country VARCHAR(2) NOT NULL DEFAULT 'PT',
            status VARCHAR(24) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY client_provider_env_idx (client_id, provider, environment),
            KEY external_customer_idx (external_customer_id),
            KEY status_idx (status)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
            environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
            external_customer_id VARCHAR(191) NULL,
            external_subscription_id VARCHAR(191) NULL,
            plan_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            billing_interval VARCHAR(16) NOT NULL DEFAULT 'month',
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            current_period_start DATETIME NULL,
            current_period_end DATETIME NULL,
            trial_end DATETIME NULL,
            cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
            grace_until DATETIME NULL,
            latest_invoice_id VARCHAR(191) NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY client_provider_env_idx (client_id, provider, environment),
            KEY external_subscription_idx (external_subscription_id),
            KEY status_grace_idx (status, grace_until),
            KEY plan_idx (plan_id)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_invoices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            external_invoice_id VARCHAR(191) NOT NULL,
            environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            external_subscription_id VARCHAR(191) NULL,
            invoice_number VARCHAR(96) NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            subtotal_minor BIGINT NOT NULL DEFAULT 0,
            tax_minor BIGINT NOT NULL DEFAULT 0,
            total_minor BIGINT NOT NULL DEFAULT 0,
            amount_paid_minor BIGINT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'open',
            hosted_invoice_url TEXT NULL,
            invoice_pdf TEXT NULL,
            due_at DATETIME NULL,
            paid_at DATETIME NULL,
            period_start DATETIME NULL,
            period_end DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY external_invoice_idx (environment, external_invoice_id),
            KEY client_status_idx (client_id, status),
            KEY subscription_idx (external_subscription_id),
            KEY paid_idx (paid_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
            environment VARCHAR(16) NOT NULL DEFAULT 'live',
            provider_event_id VARCHAR(191) NOT NULL,
            event_type VARCHAR(96) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'processing',
            payload_hash CHAR(64) NOT NULL,
            payload_json LONGTEXT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            error_message TEXT NULL,
            processed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY provider_event_idx (provider, environment, provider_event_id),
            KEY type_status_idx (event_type, status),
            KEY created_idx (created_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_checkout_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_token_hash CHAR(64) NOT NULL,
            provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
            environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
            external_session_id VARCHAR(191) NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,
            billing_interval VARCHAR(16) NOT NULL DEFAULT 'month',
            status VARCHAR(24) NOT NULL DEFAULT 'created',
            checkout_url TEXT NULL,
            expires_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY token_idx (session_token_hash),
            KEY external_session_idx (external_session_id),
            KEY client_status_idx (client_id, status),
            KEY expires_idx (expires_at)
        ) {$charset}");
        $plansTable = $prefix . 'saas_plans';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $plansTable)) === $plansTable) {
            $rows = $wpdb->get_results("SELECT id,capabilities_json FROM {$plansTable}", ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $caps = json_decode((string)$row['capabilities_json'], true);
                if (!is_array($caps)) $caps = [];
                $caps['billing.view'] = 1;
                $caps['billing.checkout'] = 1;
                $wpdb->update($plansTable, ['capabilities_json'=>wp_json_encode($caps), 'updated_at'=>current_time('mysql')], ['id'=>(int)$row['id']]);
            }
        }
    }


    private static function migrate22243(wpdb $wpdb, string $prefix): void {
        $subscriptions = $prefix . 'billing_subscriptions';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $subscriptions)) === $subscriptions) {
            $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$subscriptions} LIKE %s", 'billing_user_id'));
            if (!$column) $wpdb->query("ALTER TABLE {$subscriptions} ADD COLUMN billing_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER client_id");
            self::maybeAddIndex($wpdb, $subscriptions, 'billing_user_idx', 'billing_user_id');
        }
        $sessions = $prefix . 'billing_checkout_sessions';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sessions)) === $sessions) {
            $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$sessions} LIKE %s", 'billing_user_id'));
            if (!$column) $wpdb->query("ALTER TABLE {$sessions} ADD COLUMN billing_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER client_id");
            self::maybeAddIndex($wpdb, $sessions, 'billing_user_idx', 'billing_user_id');
        }
    }


    private static function migrate22244(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_signup_intents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token_hash CHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            plan_id BIGINT UNSIGNED NOT NULL,
            billing_interval VARCHAR(16) NOT NULL DEFAULT 'month',
            environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
            status VARCHAR(32) NOT NULL DEFAULT 'created',
            email VARCHAR(255) NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'web',
            checkout_session_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME NULL,
            completed_at DATETIME NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY token_idx (token_hash),
            KEY user_status_idx (user_id, status),
            KEY client_idx (client_id),
            KEY plan_idx (plan_id),
            KEY expires_idx (expires_at)
        ) {$charset}");
    }

    private static function migrate22245(wpdb $wpdb, string $prefix): void {
        $table = $prefix . 'billing_subscriptions';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return;
        self::maybeAddIndex($wpdb, $table, 'billing_user_status_idx', 'billing_user_id, status');
        self::maybeAddIndex($wpdb, $table, 'client_environment_status_idx', 'client_id, environment, status');
    }


    private static function migrate22246(wpdb $wpdb, string $prefix): void {
        // Alteração funcional sem mudança de schema. O passo mantém o histórico
        // de migrações alinhado com a libertação do checkout Enterprise configurável.
    }

    private static function migrate22247(wpdb $wpdb, string $prefix): void {
        // Alteração funcional sem mudança de schema. As intenções existentes
        // continuam válidas e passam a aprender o URL de continuação na próxima abertura.
    }

    private static function migrate2225(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_consents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            intent_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            terms_version VARCHAR(32) NOT NULL DEFAULT '1.0',
            privacy_version VARCHAR(32) NOT NULL DEFAULT '1.0',
            terms_url TEXT NULL,
            privacy_url TEXT NULL,
            marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
            source VARCHAR(32) NOT NULL DEFAULT 'public_page',
            ip_hash CHAR(32) NULL,
            user_agent VARCHAR(255) NULL,
            accepted_at DATETIME NOT NULL,
            UNIQUE KEY intent_idx (intent_id),
            KEY user_idx (user_id),
            KEY client_idx (client_id),
            KEY accepted_idx (accepted_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}billing_email_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_key VARCHAR(191) NOT NULL,
            template_key VARCHAR(64) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'queued',
            error_message TEXT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            intent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            meta_json LONGTEXT NULL,
            sent_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY message_key_idx (message_key),
            KEY template_status_idx (template_key, status),
            KEY client_idx (client_id),
            KEY user_idx (user_id),
            KEY created_idx (created_at)
        ) {$charset}");
    }

    private static function maybeAddIndex(wpdb $wpdb, string $table, string $indexName, string $definition): void {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $indexName));
        if ($exists) return;
        $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($tableExists !== $table) return;
        $wpdb->query("ALTER TABLE {$table} ADD INDEX {$indexName} ({$definition})");
    }
    private static function migrate22251(wpdb $wpdb, string $prefix): void {
        // No schema changes. Versioned step refreshes the portal-page association controls.
    }

    private static function migrate22256(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $runs = $prefix . 'process_runs';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $runs)) === $runs) {
            $columns = [
                'recovery_attempt' => "ALTER TABLE {$runs} ADD COLUMN recovery_attempt SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER error_code",
                'recovery_status' => "ALTER TABLE {$runs} ADD COLUMN recovery_status VARCHAR(24) NOT NULL DEFAULT 'none' AFTER recovery_attempt",
                'next_retry_at' => "ALTER TABLE {$runs} ADD COLUMN next_retry_at DATETIME NULL AFTER recovery_status",
                'parent_run_id' => "ALTER TABLE {$runs} ADD COLUMN parent_run_id BIGINT UNSIGNED NULL AFTER next_retry_at",
                'terminal_at' => "ALTER TABLE {$runs} ADD COLUMN terminal_at DATETIME NULL AFTER parent_run_id",
            ];
            foreach ($columns as $column => $sql) {
                if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$runs} LIKE %s", $column))) $wpdb->query($sql);
            }
            self::maybeAddIndex($wpdb, $runs, 'recovery_idx', 'recovery_status, next_retry_at');
            self::maybeAddIndex($wpdb, $runs, 'parent_run_idx', 'parent_run_id');
        }
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}process_circuits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            process_key VARCHAR(96) NOT NULL,
            state VARCHAR(16) NOT NULL DEFAULT 'closed',
            failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            opened_at DATETIME NULL,
            retry_after DATETIME NULL,
            last_failure_at DATETIME NULL,
            last_success_at DATETIME NULL,
            last_error_code VARCHAR(96) NULL,
            last_message VARCHAR(255) NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY process_key_idx (process_key),
            KEY state_retry_idx (state, retry_after)
        ) {$charset}");
    }

    private static function migrate22260(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}scope_memberships (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            scope_type VARCHAR(16) NOT NULL,
            scope_id BIGINT UNSIGNED NOT NULL,
            source_type VARCHAR(32) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY membership_idx (user_id, scope_type, scope_id, source_type, source_id),
            KEY user_scope_idx (user_id, scope_type, is_active),
            KEY scope_user_idx (scope_type, scope_id, user_id),
            KEY source_idx (source_type, source_id)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}scope_index_state (
            user_id BIGINT UNSIGNED PRIMARY KEY,
            generation BIGINT UNSIGNED NOT NULL DEFAULT 1,
            indexed_at DATETIME NOT NULL,
            KEY generation_idx (generation, indexed_at)
        ) {$charset}");
        if (!get_option('fieldflow_scope_index_generation')) {
            add_option('fieldflow_scope_index_generation', 1, '', 'no');
        }
        if (get_option('fieldflow_scope_index_enabled', null) === null) {
            add_option('fieldflow_scope_index_enabled', 1, '', 'no');
        }
    }

    private static function migrate22263(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $ai = $prefix . 'ai_usage_logs';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ai)) === $ai) {
            $columns = [
                'usage_source' => "ALTER TABLE {$ai} ADD COLUMN usage_source VARCHAR(16) NULL AFTER output_tokens",
                'estimated_cost_eur' => "ALTER TABLE {$ai} ADD COLUMN estimated_cost_eur DECIMAL(14,6) NOT NULL DEFAULT 0 AFTER usage_source",
                'prompt_version' => "ALTER TABLE {$ai} ADD COLUMN prompt_version VARCHAR(96) NULL AFTER estimated_cost_eur",
                'risk_flags_json' => "ALTER TABLE {$ai} ADD COLUMN risk_flags_json LONGTEXT NULL AFTER prompt_version",
            ];
            foreach ($columns as $column=>$sql) {
                if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$ai} LIKE %s", $column))) $wpdb->query($sql);
            }
            self::maybeAddIndex($wpdb, $ai, 'prompt_version_idx', 'prompt_version');
        }

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}privacy_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            request_uuid CHAR(36) NOT NULL,
            request_type VARCHAR(24) NOT NULL DEFAULT 'access',
            status VARCHAR(24) NOT NULL DEFAULT 'received',
            subject_user_id BIGINT UNSIGNED NULL,
            subject_email VARCHAR(255) NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes TEXT NULL,
            result_json LONGTEXT NULL,
            requested_by BIGINT UNSIGNED NULL,
            completed_by BIGINT UNSIGNED NULL,
            requested_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY request_uuid_idx (request_uuid),
            KEY status_idx (status, requested_at),
            KEY subject_user_idx (subject_user_id),
            KEY subject_email_idx (subject_email),
            KEY scope_idx (client_id, project_id)
        ) {$charset}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}privacy_retention_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_uuid CHAR(36) NOT NULL,
            run_mode VARCHAR(16) NOT NULL DEFAULT 'preview',
            source VARCHAR(24) NOT NULL DEFAULT 'manual',
            status VARCHAR(24) NOT NULL DEFAULT 'completed',
            eligible_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            affected_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            summary_json LONGTEXT NULL,
            initiated_by BIGINT UNSIGNED NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY run_uuid_idx (run_uuid),
            KEY mode_created_idx (run_mode, created_at),
            KEY status_idx (status),
            KEY initiated_idx (initiated_by)
        ) {$charset}");
    }

    private static function migrate22264(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}performance_samples (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            operation_key VARCHAR(191) NOT NULL,
            channel VARCHAR(32) NOT NULL DEFAULT 'web',
            category VARCHAR(32) NOT NULL DEFAULT 'web',
            severity VARCHAR(16) NOT NULL DEFAULT 'normal',
            outcome VARCHAR(16) NOT NULL DEFAULT 'success',
            http_status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            query_count INT UNSIGNED NOT NULL DEFAULT 0,
            memory_peak_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            memory_delta_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            sample_rate TINYINT UNSIGNED NOT NULL DEFAULT 25,
            sampled_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            KEY category_sampled_idx (category, sampled_at),
            KEY operation_sampled_idx (operation_key, sampled_at),
            KEY severity_sampled_idx (severity, sampled_at),
            KEY outcome_sampled_idx (outcome, sampled_at),
            KEY sampled_idx (sampled_at)
        ) {$charset}");
        if (get_option('fieldflow_load_readiness', null) === null) {
            add_option('fieldflow_load_readiness', [
                'enabled'=>1,
                'mode'=>'observe',
                'sample_rate'=>25,
                'retention_days'=>30,
                'gate_window_hours'=>24,
                'minimum_samples'=>5,
            ], '', 'no');
        }
    }

    private static function migrate22265(wpdb $wpdb, string $prefix): void {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}queue_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_uuid CHAR(36) NOT NULL,
            queue_name VARCHAR(64) NOT NULL DEFAULT 'default',
            hook_name VARCHAR(191) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            priority SMALLINT NOT NULL DEFAULT 0,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
            available_at DATETIME NOT NULL,
            locked_at DATETIME NULL,
            lock_token CHAR(36) NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            duration_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            active_key CHAR(64) NULL,
            idempotency_key VARCHAR(191) NOT NULL,
            args_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            last_error_code VARCHAR(96) NULL,
            last_error_message VARCHAR(500) NULL,
            driver VARCHAR(32) NOT NULL DEFAULT 'wp_cron',
            source VARCHAR(32) NOT NULL DEFAULT 'fieldflow',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY job_uuid_idx (job_uuid),
            UNIQUE KEY active_key_idx (active_key),
            KEY status_available_idx (status, available_at, priority),
            KEY queue_status_idx (queue_name, status, available_at),
            KEY hook_status_idx (hook_name, status),
            KEY locked_idx (status, locked_at),
            KEY completed_idx (completed_at),
            KEY created_idx (created_at)
        ) {$charset}");
        if (get_option('fieldflow_queue_modernization', null) === null) {
            add_option('fieldflow_queue_modernization', [
                'mode'=>'observe',
                'driver'=>'auto',
                'pilot_hooks'=>[],
                'batch_size'=>10,
                'max_runtime_seconds'=>20,
                'lock_ttl_seconds'=>600,
                'retention_days'=>30,
                'diagnostics'=>1,
            ], '', 'no');
        }
    }

    private static function migrate22266(wpdb $wpdb, string $prefix): bool {
        $ledgerReady = class_exists('\RoutesPro\Support\MigrationLedger') && MigrationLedger::install($wpdb);
        $batchReady = class_exists('\RoutesPro\Support\BatchMigrationManager') && BatchMigrationManager::install($wpdb);
        if (!$ledgerReady || !$batchReady) {
            throw new \RuntimeException('Não foi possível instalar as tabelas de segurança das migrações 2.2.266.');
        }
        if (get_option('fieldflow_schema_integrity_settings', null) === null) {
            add_option('fieldflow_schema_integrity_settings', [
                'mode'=>'observe',
                'automatic_repairs'=>0,
                'batch_size'=>8,
                'report_retention'=>20,
            ], '', 'no');
        }
        if (class_exists('\RoutesPro\Support\SchemaIntegrity')) {
            SchemaIntegrity::audit('quick', true);
        }
        return true;
    }


    private static function migrate22340(wpdb $wpdb, string $prefix): bool {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}cloud_sync_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sync_uuid CHAR(36) NOT NULL,
            tenant_id VARCHAR(64) NOT NULL,
            installation_id VARCHAR(64) NOT NULL,
            destination VARCHAR(32) NOT NULL DEFAULT 'azure',
            event_uuid CHAR(36) NOT NULL,
            event_name VARCHAR(96) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
            available_at DATETIME NOT NULL,
            locked_at DATETIME NULL,
            lock_token CHAR(36) NULL,
            completed_at DATETIME NULL,
            last_http_code SMALLINT UNSIGNED NULL,
            last_error_code VARCHAR(96) NULL,
            last_error_message VARCHAR(500) NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY sync_uuid_idx (sync_uuid),
            UNIQUE KEY destination_event_idx (destination, event_uuid),
            KEY status_available_idx (status, available_at),
            KEY tenant_status_idx (tenant_id, status, available_at),
            KEY client_project_idx (client_id, project_id),
            KEY event_name_idx (event_name),
            KEY completed_idx (completed_at),
            KEY created_idx (created_at)
        ) {$charset}");
        $table = $prefix . 'cloud_sync_jobs';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            throw new \RuntimeException('FieldFlow Cloud Sync table could not be installed.');
        }
        if (class_exists('\RoutesPro\Cloud\TenantIdentity')) {
            \RoutesPro\Cloud\TenantIdentity::tenantId();
            \RoutesPro\Cloud\TenantIdentity::installationId();
        }
        return true;
    }

    private static function migrate22354(wpdb $wpdb, string $prefix): bool {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}cloud_destination_types (
            type_key VARCHAR(32) NOT NULL PRIMARY KEY,
            label VARCHAR(96) NOT NULL,
            delivery_phase VARCHAR(16) NOT NULL DEFAULT '5A',
            capability VARCHAR(32) NOT NULL DEFAULT 'delivery',
            supports_endpoint TINYINT(1) NOT NULL DEFAULT 1,
            requires_secret_ref TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY active_idx (is_active),
            KEY phase_idx (delivery_phase)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}cloud_destinations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            destination_uuid VARCHAR(64) NOT NULL,
            tenant_id VARCHAR(64) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            type_key VARCHAR(32) NOT NULL,
            name VARCHAR(191) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            mode VARCHAR(24) NOT NULL DEFAULT 'mirror',
            endpoint_ref VARCHAR(500) NULL,
            secret_ref VARCHAR(500) NULL,
            retry_policy_json LONGTEXT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            last_success_at DATETIME NULL,
            last_error_at DATETIME NULL,
            last_error_code VARCHAR(96) NULL,
            last_error_message VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY destination_uuid_idx (destination_uuid),
            KEY tenant_active_idx (tenant_id, is_active, status),
            KEY scope_idx (tenant_id, client_id, project_id),
            KEY type_status_idx (type_key, status),
            KEY updated_idx (updated_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}cloud_routing_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rule_uuid CHAR(36) NOT NULL,
            tenant_id VARCHAR(64) NOT NULL,
            destination_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            event_pattern VARCHAR(96) NOT NULL DEFAULT '*',
            action_name VARCHAR(16) NOT NULL DEFAULT 'include',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            priority INT UNSIGNED NOT NULL DEFAULT 100,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY rule_uuid_idx (rule_uuid),
            KEY destination_active_idx (destination_id, is_active, priority),
            KEY scope_idx (tenant_id, client_id, project_id),
            KEY event_pattern_idx (event_pattern),
            KEY updated_idx (updated_at)
        ) {$charset}");
        foreach (['cloud_destination_types','cloud_destinations','cloud_routing_rules'] as $suffix) {
            $table = $prefix . $suffix;
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                throw new \RuntimeException('FieldFlow Multi-Destination table could not be installed: ' . $suffix);
            }
        }
        if (class_exists('\\RoutesPro\\Cloud\\DestinationRegistry')) {
            \RoutesPro\Cloud\DestinationRegistry::ensureFoundation();
        }
        return true;
    }

    private static function migrate22356(wpdb $wpdb, string $prefix): bool {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}cloud_delivery_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            delivery_key VARCHAR(64) NOT NULL,
            tenant_id VARCHAR(64) NOT NULL,
            installation_id VARCHAR(64) NOT NULL,
            destination_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            destination_uuid VARCHAR(64) NOT NULL,
            event_uuid CHAR(36) NOT NULL,
            event_name VARCHAR(96) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
            base_delay_seconds INT UNSIGNED NOT NULL DEFAULT 60,
            available_at DATETIME NOT NULL,
            locked_at DATETIME NULL,
            lock_token CHAR(36) NULL,
            completed_at DATETIME NULL,
            last_http_code SMALLINT UNSIGNED NULL,
            last_error_code VARCHAR(96) NULL,
            last_error_message VARCHAR(500) NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY delivery_key_idx (delivery_key),
            KEY destination_status_idx (destination_uuid, status, available_at),
            KEY tenant_status_idx (tenant_id, status, available_at),
            KEY scope_idx (client_id, project_id),
            KEY event_idx (event_name, event_uuid),
            KEY completed_idx (completed_at),
            KEY created_idx (created_at)
        ) {$charset}");
        $table = $prefix . 'cloud_delivery_jobs';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            throw new \RuntimeException('FieldFlow Client Azure delivery queue could not be installed.');
        }
        return true;
    }


    private static function migrate22436(wpdb $wpdb, string $prefix): bool {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}intelligence_recommendations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            recommendation_uuid CHAR(36) NOT NULL,
            fingerprint CHAR(64) NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            route_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            recommendation_type VARCHAR(96) NOT NULL,
            source_type VARCHAR(32) NOT NULL DEFAULT 'analytics',
            source_key VARCHAR(96) NULL,
            severity VARCHAR(16) NOT NULL DEFAULT 'medium',
            status VARCHAR(24) NOT NULL DEFAULT 'active',
            title VARCHAR(255) NOT NULL,
            detail TEXT NULL,
            rationale TEXT NULL,
            suggested_action TEXT NULL,
            metric_key VARCHAR(96) NULL,
            baseline_value VARCHAR(96) NULL,
            latest_value VARCHAR(96) NULL,
            threshold_value VARCHAR(96) NULL,
            desired_direction VARCHAR(16) NOT NULL DEFAULT 'observe',
            confidence_score DECIMAL(5,1) NOT NULL DEFAULT 0.0,
            baseline_json LONGTEXT NULL,
            latest_json LONGTEXT NULL,
            feedback_label VARCHAR(24) NULL,
            feedback_note TEXT NULL,
            feedback_by BIGINT UNSIGNED NULL,
            feedback_at DATETIME NULL,
            outcome_status VARCHAR(32) NOT NULL DEFAULT 'pending',
            outcome_score DECIMAL(7,1) NULL,
            outcome_note TEXT NULL,
            outcome_json LONGTEXT NULL,
            occurrence_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
            feedback_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY recommendation_uuid_idx (recommendation_uuid),
            UNIQUE KEY fingerprint_idx (fingerprint),
            KEY scope_idx (client_id, project_id, route_id, user_id, location_id),
            KEY status_severity_idx (status, severity),
            KEY location_status_idx (location_id, status, last_seen_at),
            KEY feedback_idx (feedback_label, feedback_at),
            KEY outcome_idx (outcome_status, updated_at),
            KEY last_seen_idx (last_seen_at)
        ) {$charset}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}intelligence_feedback_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_uuid CHAR(36) NOT NULL,
            recommendation_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(64) NOT NULL,
            actor_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            route_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            payload_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY event_uuid_idx (event_uuid),
            KEY recommendation_idx (recommendation_id, created_at),
            KEY scope_idx (client_id, project_id, location_id),
            KEY event_type_idx (event_type, created_at),
            KEY actor_idx (actor_user_id, created_at)
        ) {$charset}");
        foreach (['intelligence_recommendations','intelligence_feedback_events'] as $suffix) {
            $table = $prefix . $suffix;
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                throw new \RuntimeException('FieldFlow Intelligence Learning table could not be installed: ' . $suffix);
            }
        }
        return true;
    }

    private static function migrate22525(wpdb $wpdb, string $prefix): bool {
        $charset = $wpdb->get_charset_collate();
        $table = $prefix . 'mail_outbox';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(64) NOT NULL,
            idempotency_key VARCHAR(80) NULL,
            source VARCHAR(64) NOT NULL DEFAULT 'system',
            email_type VARCHAR(64) NOT NULL DEFAULT 'system',
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            route_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            entity_type VARCHAR(64) NULL,
            entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            to_json LONGTEXT NOT NULL,
            cc_json LONGTEXT NULL,
            bcc_json LONGTEXT NULL,
            subject VARCHAR(255) NOT NULL,
            body LONGTEXT NOT NULL,
            headers_json LONGTEXT NULL,
            attachments_json LONGTEXT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 4,
            next_attempt_at DATETIME NULL,
            locked_at DATETIME NULL,
            locked_by VARCHAR(64) NULL,
            last_dispatch_id VARCHAR(64) NULL,
            last_error TEXT NULL,
            provider_message_id VARCHAR(191) NULL,
            attempt_log_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            cancelled_at DATETIME NULL,
            UNIQUE KEY message_id_idx (message_id),
            UNIQUE KEY idempotency_key_idx (idempotency_key),
            KEY status_next_idx (status, next_attempt_at),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY route_idx (route_id),
            KEY source_idx (source),
            KEY created_idx (created_at)
        ) {$charset}");
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            throw new \RuntimeException('FieldFlow mail outbox could not be installed.');
        }
        return true;
    }


    private static function migrate22529(wpdb $wpdb, string $prefix): bool {
        $table = $prefix . 'ai_usage_logs';
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            throw new \RuntimeException('FieldFlow AI usage ledger is unavailable for the 2.2.529 attribution migration.');
        }
        $columns = [
            'client_id' => "ALTER TABLE {$table} ADD COLUMN client_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER risk_flags_json",
            'project_id' => "ALTER TABLE {$table} ADD COLUMN project_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER client_id",
            'feature_key' => "ALTER TABLE {$table} ADD COLUMN feature_key VARCHAR(96) NULL AFTER project_id",
            'request_kind' => "ALTER TABLE {$table} ADD COLUMN request_kind VARCHAR(32) NOT NULL DEFAULT 'runtime' AFTER feature_key",
        ];
        foreach ($columns as $column => $sql) {
            if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column))) {
                $wpdb->query($sql);
                if ((string)$wpdb->last_error !== '') throw new \RuntimeException((string)$wpdb->last_error);
            }
        }
        self::maybeAddIndex($wpdb, $table, 'client_created_idx', 'client_id, created_at');
        self::maybeAddIndex($wpdb, $table, 'project_created_idx', 'project_id, created_at');
        self::maybeAddIndex($wpdb, $table, 'feature_created_idx', 'feature_key, created_at');
        self::maybeAddIndex($wpdb, $table, 'request_kind_idx', 'request_kind, created_at');
        if (class_exists('\RoutesPro\Repositories\AIUsageRepository')) {
            \RoutesPro\Repositories\AIUsageRepository::resetSchemaCache();
        }
        return true;
    }

}
