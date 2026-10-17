<?php
namespace RoutesPro;
use wpdb;

class Activator {
    public static function activate() {
        global $wpdb;
        $installedVersionOption = get_option('routespro_version', false);
        $hadInstalledVersion = $installedVersionOption !== false && (string)$installedVersionOption !== '';
        $previousVersion = $hadInstalledVersion ? (string)$installedVersionOption : '0.0.0';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $prefix = $wpdb->prefix . 'routespro_';

        // Evitar JSON nativo (dbDelta tem incompatibilidades) -> LONGTEXT
        $tables = [];

        $tables[] = "CREATE TABLE {$prefix}clients (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            taxid VARCHAR(64) NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(64) NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset;";

        
        $tables[] = "CREATE TABLE {$prefix}email_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email_type VARCHAR(64) NOT NULL DEFAULT 'system',
            context_key VARCHAR(64) NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            sender_user_id BIGINT UNSIGNED NULL,
            recipient_user_id BIGINT UNSIGNED NULL,
            recipient_email VARCHAR(255) NULL,
            recipient_name VARCHAR(255) NULL,
            message_kind VARCHAR(64) NULL,
            subject VARCHAR(255) NOT NULL,
            body LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            mail_result VARCHAR(32) NOT NULL DEFAULT 'sent',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY email_type (email_type),
            KEY context_key (context_key),
            KEY client (client_id),
            KEY project (project_id),
            KEY route (route_id),
            KEY sender (sender_user_id),
            KEY recipient_user (recipient_user_id),
            KEY created_at (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mail_outbox (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}report_schedules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            weekday TINYINT UNSIGNED NOT NULL DEFAULT 1,
            send_time TIME NOT NULL DEFAULT '08:00:00',
            start_date DATE NOT NULL,
            end_date DATE NULL,
            cumulative_start DATE NULL,
            periods_json LONGTEXT NULL,
            formats_json LONGTEXT NULL,
            to_team TINYINT(1) NOT NULL DEFAULT 1,
            to_clients TINYINT(1) NOT NULL DEFAULT 1,
            extra_emails TEXT NULL,
            test_email VARCHAR(190) NULL,
            test_scope VARCHAR(24) NOT NULL DEFAULT 'client',
            test_user_id BIGINT UNSIGNED NULL,
            subject_template VARCHAR(255) NOT NULL,
            intro_text TEXT NULL,
            brand_primary VARCHAR(16) NOT NULL DEFAULT '#172033',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            next_run_at DATETIME NULL,
            last_run_at DATETIME NULL,
            last_status VARCHAR(32) NULL,
            last_message VARCHAR(255) NULL,
            last_test_at DATETIME NULL,
            last_test_status VARCHAR(32) NULL,
            last_test_message VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY scope_idx (client_id, project_id),
            KEY active_next_idx (is_active, next_run_at),
            KEY created_by_idx (created_by)
        ) $charset;";

$tables[] = "CREATE TABLE {$prefix}projects (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(32) DEFAULT 'active',
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client (client_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}locations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            address TEXT NULL,
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            window_start TIME NULL,
            window_end TIME NULL,
            service_time_min INT DEFAULT 0,
            tags VARCHAR(255) NULL,
            external_ref VARCHAR(128) NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client (client_id),
            KEY project (project_id),
            KEY latlng (lat, lng)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}location_profiles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            location_id BIGINT UNSIGNED NOT NULL,
            operational_email VARCHAR(255) NULL,
            operational_phone VARCHAR(64) NULL,
            responsible_name VARCHAR(255) NULL,
            store_format VARCHAR(96) NULL,
            assortment_profile VARCHAR(64) NOT NULL DEFAULT 'general',
            assortment_profile_source VARCHAR(32) NOT NULL DEFAULT '',
            assortment_profile_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
            pending_status VARCHAR(24) NOT NULL DEFAULT 'none',
            pending_actions TEXT NULL,
            store_notes LONGTEXT NULL,
            revision_no INT UNSIGNED NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY location_unique (location_id),
            KEY pending_idx (pending_status),
            KEY assortment_profile_idx (assortment_profile),
            KEY store_format_idx (store_format),
            KEY updated_idx (updated_at),
            KEY updated_by_idx (updated_by)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}routes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NULL,
            date DATE NOT NULL,
            status VARCHAR(32) DEFAULT 'draft',
            owner_user_id BIGINT UNSIGNED NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY date_idx (date),
            KEY owner (owner_user_id),
            KEY client (client_id),
            KEY project (project_id)
        ) $charset;";

            // NOTA: esta tabela inclui todos os campos já usados nos controllers (arrived_at, departed_at, etc.)
        $tables[] = "CREATE TABLE {$prefix}route_stops (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            seq INT NOT NULL DEFAULT 0,
            planned_arrival DATETIME NULL,
            planned_departure DATETIME NULL,
            arrived_at DATETIME NULL,
            departed_at DATETIME NULL,
            duration_s INT NULL,
            status VARCHAR(32) DEFAULT 'pending',
            note TEXT NULL,
            fail_reason VARCHAR(255) NULL,
            photo_url TEXT NULL,
            signature_data LONGTEXT NULL,
            qty DOUBLE NULL,
            weight DOUBLE NULL,
            volume DOUBLE NULL,
            real_lat DECIMAL(10,7) NULL,
            real_lng DECIMAL(10,7) NULL,
            meta_json LONGTEXT NULL,
            KEY route (route_id),
            KEY location (location_id),
            KEY seq (seq),
            KEY status_idx (status)
        ) $charset;";


        $tables[] = "CREATE TABLE {$prefix}project_assignments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(32) DEFAULT 'owner',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_project_user (project_id, user_id),
            KEY project (project_id),
            KEY user (user_id),
            KEY role_idx (role)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}assignments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(32) DEFAULT 'driver',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_route_user (route_id, user_id),
            KEY route (route_id),
            KEY user (user_id),
            KEY role_idx (role)
        ) $charset;";


        $tables[] = "CREATE TABLE {$prefix}forms (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            schema_json LONGTEXT NULL,
            settings_json LONGTEXT NULL,
            theme_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status_idx (status),
            KEY updated_idx (updated_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_submissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(32) NOT NULL DEFAULT 'submitted',
            meta_json LONGTEXT NULL,
            KEY form_idx (form_id),
            KEY user_idx (user_id),
            KEY submitted_idx (submitted_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_submission_answers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_id BIGINT UNSIGNED NOT NULL,
            question_key VARCHAR(191) NOT NULL,
            question_label VARCHAR(255) NULL,
            value_text LONGTEXT NULL,
            value_number DOUBLE NULL,
            value_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY submission_idx (submission_id),
            KEY question_key (question_key)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_key VARCHAR(191) NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            current_version_id BIGINT UNSIGNED NULL,
            current_version_no INT NOT NULL DEFAULT 0,
            first_submission_id BIGINT UNSIGNED NULL,
            latest_submission_id BIGINT UNSIGNED NULL,
            latest_binding_id BIGINT UNSIGNED NULL,
            latest_route_id BIGINT UNSIGNED NULL,
            latest_route_stop_id BIGINT UNSIGNED NULL,
            last_user_id BIGINT UNSIGNED NULL,
            first_submitted_at DATETIME NULL,
            last_submitted_at DATETIME NULL,
            version_count INT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY record_key_idx (record_key),
            KEY form_idx (form_id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY location_idx (location_id),
            KEY current_version_idx (current_version_id),
            KEY latest_submission_idx (latest_submission_id),
            KEY submitted_idx (last_submitted_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_record_versions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_id BIGINT UNSIGNED NOT NULL,
            version_no INT NOT NULL,
            submission_id BIGINT UNSIGNED NULL,
            binding_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            route_stop_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NULL,
            owner_user_id BIGINT UNSIGNED NULL,
            submitted_at DATETIME NOT NULL,
            parent_version_id BIGINT UNSIGNED NULL,
            change_summary_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_record_version (record_id, version_no),
            KEY record_idx (record_id),
            KEY submission_idx (submission_id),
            KEY binding_idx (binding_id),
            KEY route_idx (route_id),
            KEY route_stop_idx (route_stop_id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY location_idx (location_id),
            KEY owner_idx (owner_user_id),
            KEY submitted_idx (submitted_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_record_values (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_id BIGINT UNSIGNED NOT NULL,
            version_id BIGINT UNSIGNED NOT NULL,
            question_key VARCHAR(191) NOT NULL,
            question_label VARCHAR(255) NULL,
            value_type VARCHAR(64) NULL,
            value_text LONGTEXT NULL,
            value_number DOUBLE NULL,
            value_json LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY record_idx (record_id),
            KEY version_idx (version_id),
            KEY question_idx (question_key),
            KEY record_question_idx (record_id, question_key)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_analytics_bindings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL,
            question_key VARCHAR(191) NOT NULL,
            metric_key VARCHAR(191) NOT NULL,
            metric_label VARCHAR(255) NOT NULL,
            chart_type VARCHAR(64) NOT NULL DEFAULT 'line',
            aggregation VARCHAR(64) NOT NULL DEFAULT 'latest',
            dimension VARCHAR(64) NOT NULL DEFAULT 'submitted_at',
            scope_mode VARCHAR(64) NOT NULL DEFAULT 'client_project_location',
            settings_json LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_metric (form_id, question_key, metric_key),
            KEY form_idx (form_id),
            KEY question_idx (question_key),
            KEY chart_idx (chart_type),
            KEY agg_idx (aggregation),
            KEY active_idx (is_active)
        ) $charset;";


        $tables[] = "CREATE TABLE {$prefix}analytics_dashboards (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            visibility VARCHAR(64) NOT NULL DEFAULT 'client_portal',
            layout_type VARCHAR(64) NOT NULL DEFAULT 'mixed',
            sort_order INT NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ai_objective VARCHAR(64) NULL,
            ai_settings_json LONGTEXT NULL,
            ai_last_insights_json LONGTEXT NULL,
            ai_last_generated_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY route_idx (route_id),
            KEY active_idx (is_active),
            KEY sort_idx (sort_order)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}analytics_store_groups (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            group_type VARCHAR(64) NOT NULL DEFAULT 'manual',
            rule_json LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY active_idx (is_active)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}analytics_store_group_items (
            group_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id, location_id),
            KEY location_idx (location_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_bindings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            stop_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            mode VARCHAR(32) NOT NULL DEFAULT 'route_and_form',
            priority INT NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY form_idx (form_id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY route_idx (route_id),
            KEY stop_idx (stop_id),
            KEY location_idx (location_id),
            KEY active_idx (is_active),
            KEY priority_idx (priority)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}system_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            log_level VARCHAR(16) NOT NULL DEFAULT 'info',
            context_key VARCHAR(64) NULL,
            user_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            message VARCHAR(255) NOT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY level_idx (log_level),
            KEY context_idx (context_key),
            KEY user_idx (user_id),
            KEY route_idx (route_id),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}process_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_uuid CHAR(36) NOT NULL,
            process_key VARCHAR(96) NOT NULL,
            hook_name VARCHAR(191) NOT NULL,
            instance_hash CHAR(64) NOT NULL,
            process_type VARCHAR(64) NOT NULL DEFAULT 'system',
            reference_id BIGINT UNSIGNED NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            attempt INT UNSIGNED NOT NULL DEFAULT 1,
            source VARCHAR(32) NOT NULL DEFAULT 'cron',
            scheduled_at DATETIME NULL,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            message VARCHAR(255) NULL,
            error_code VARCHAR(96) NULL,
            recovery_attempt SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            recovery_status VARCHAR(24) NOT NULL DEFAULT 'none',
            next_retry_at DATETIME NULL,
            parent_run_id BIGINT UNSIGNED NULL,
            terminal_at DATETIME NULL,
            args_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY run_uuid_idx (run_uuid),
            KEY process_status_idx (process_key, status),
            KEY hook_idx (hook_name),
            KEY instance_idx (instance_hash),
            KEY scheduled_idx (scheduled_at),
            KEY started_idx (started_at),
            KEY completed_idx (completed_at),
            KEY recovery_idx (recovery_status, next_retry_at),
            KEY parent_run_idx (parent_run_id),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}process_circuits (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}queue_jobs (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}core_events (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}cloud_sync_jobs (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}cloud_connector_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            delivery_key VARCHAR(64) NOT NULL,
            tenant_id VARCHAR(64) NOT NULL,
            installation_id VARCHAR(64) NOT NULL,
            destination_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            destination_uuid VARCHAR(64) NOT NULL,
            connector_type VARCHAR(32) NOT NULL,
            event_uuid CHAR(36) NOT NULL,
            event_name VARCHAR(96) NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            execution_mode VARCHAR(24) NOT NULL DEFAULT 'shadow',
            status VARCHAR(24) NOT NULL DEFAULT 'shadow',
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
            payload_sha256 CHAR(64) NOT NULL,
            payload_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY delivery_key_idx (delivery_key),
            KEY destination_status_idx (destination_uuid, status, available_at),
            KEY connector_status_idx (connector_type, status, available_at),
            KEY tenant_status_idx (tenant_id, status, available_at),
            KEY scope_idx (client_id, project_id),
            KEY event_idx (event_name, event_uuid),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}cloud_destination_types (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}cloud_destinations (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}cloud_routing_rules (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}cloud_delivery_jobs (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}governance_ledger (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_uuid CHAR(36) NOT NULL,
            event_name VARCHAR(96) NOT NULL,
            entity_type VARCHAR(64) NOT NULL DEFAULT 'governance',
            entity_ref VARCHAR(191) NULL,
            actor_user_id BIGINT UNSIGNED NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'system',
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            destination_uuid VARCHAR(64) NULL,
            correlation_id VARCHAR(64) NULL,
            reason VARCHAR(500) NULL,
            details_json LONGTEXT NULL,
            previous_hash CHAR(64) NOT NULL,
            entry_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY entry_uuid_idx (entry_uuid),
            UNIQUE KEY entry_hash_idx (entry_hash),
            KEY event_idx (event_name),
            KEY entity_idx (entity_type, entity_ref),
            KEY actor_idx (actor_user_id),
            KEY scope_idx (client_id, project_id),
            KEY destination_idx (destination_uuid),
            KEY correlation_idx (correlation_id),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}client_onboarding_plans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            plan_uuid CHAR(36) NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NULL,
            template_key VARCHAR(48) NOT NULL,
            destination_type VARCHAR(32) NOT NULL,
            destination_id BIGINT UNSIGNED NULL,
            routing_rule_id BIGINT UNSIGNED NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            current_step VARCHAR(32) NOT NULL DEFAULT 'context',
            target_tenant_id VARCHAR(64) NULL,
            resource_scope VARCHAR(500) NULL,
            endpoint_ref VARCHAR(500) NULL,
            secret_ref VARCHAR(500) NULL,
            auth_strategy VARCHAR(32) NULL,
            event_pattern VARCHAR(96) NOT NULL DEFAULT 'submission.created',
            hourly_limit INT UNSIGNED NOT NULL DEFAULT 10,
            safe_mode VARCHAR(24) NOT NULL DEFAULT 'shadow',
            config_json LONGTEXT NULL,
            readiness_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY plan_uuid_idx (plan_uuid),
            KEY client_status_idx (client_id, status),
            KEY project_status_idx (project_id, status),
            KEY destination_idx (destination_id),
            KEY template_idx (template_key),
            KEY updated_idx (updated_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}license_tenants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_uuid CHAR(36) NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            plan_slug VARCHAR(64) NOT NULL,
            customer_code VARCHAR(32) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'draft',
            max_installations SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            expires_at DATETIME NULL,
            remote_license_id VARCHAR(128) NULL,
            key_fingerprint_sha256 CHAR(64) NULL,
            masked_key VARCHAR(191) NULL,
            binding_fingerprint_sha256 CHAR(64) NOT NULL,
            capabilities_json LONGTEXT NULL,
            limits_json LONGTEXT NULL,
            notes TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            issued_at DATETIME NULL,
            revoked_at DATETIME NULL,
            last_sync_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY tenant_uuid_idx (tenant_uuid),
            UNIQUE KEY client_idx (client_id),
            KEY plan_status_idx (plan_slug, status),
            KEY remote_license_idx (remote_license_id),
            KEY updated_idx (updated_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}license_installations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            installation_uuid CHAR(36) NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            domain VARCHAR(191) NOT NULL,
            domain_fingerprint VARCHAR(128) NOT NULL,
            site_url VARCHAR(500) NULL,
            activation_id VARCHAR(128) NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'pending',
            plugin_version VARCHAR(32) NULL,
            last_seen_at DATETIME NULL,
            activated_at DATETIME NULL,
            revoked_at DATETIME NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY installation_uuid_idx (installation_uuid),
            UNIQUE KEY tenant_domain_idx (tenant_id, domain_fingerprint),
            KEY tenant_status_idx (tenant_id, status),
            KEY activation_idx (activation_id),
            KEY seen_idx (last_seen_at),
            KEY updated_idx (updated_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}audit_log (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}reconciliation_runs (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}ai_usage_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            module_key VARCHAR(64) NOT NULL DEFAULT 'general',
            provider VARCHAR(32) NOT NULL DEFAULT 'none',
            model_name VARCHAR(191) NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'success',
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            input_chars INT UNSIGNED NOT NULL DEFAULT 0,
            output_chars INT UNSIGNED NOT NULL DEFAULT 0,
            input_tokens INT UNSIGNED NULL,
            output_tokens INT UNSIGNED NULL,
            usage_source VARCHAR(16) NULL,
            estimated_cost_eur DECIMAL(14,6) NOT NULL DEFAULT 0,
            prompt_version VARCHAR(96) NULL,
            risk_flags_json LONGTEXT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            feature_key VARCHAR(96) NULL,
            request_kind VARCHAR(32) NOT NULL DEFAULT 'runtime',
            error_code VARCHAR(64) NULL,
            error_message TEXT NULL,
            user_id BIGINT UNSIGNED NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY module_idx (module_key),
            KEY provider_idx (provider),
            KEY status_idx (status),
            KEY prompt_version_idx (prompt_version),
            KEY client_created_idx (client_id, created_at),
            KEY project_created_idx (project_id, created_at),
            KEY feature_created_idx (feature_key, created_at),
            KEY request_kind_idx (request_kind, created_at),
            KEY user_idx (user_id),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}privacy_requests (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}privacy_retention_runs (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}briefings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            year_no SMALLINT UNSIGNED NOT NULL,
            week_no TINYINT UNSIGNED NOT NULL,
            valid_from DATE NOT NULL,
            valid_to DATE NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            version_no INT UNSIGNED NOT NULL DEFAULT 1,
            source_filename VARCHAR(255) NULL,
            source_hash CHAR(64) NULL,
            import_summary_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            published_by BIGINT UNSIGNED NULL,
            published_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY week_idx (year_no, week_no),
            KEY status_idx (status),
            KEY published_idx (published_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}briefing_sections (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            briefing_id BIGINT UNSIGNED NOT NULL,
            retailer_key VARCHAR(64) NOT NULL DEFAULT 'all',
            section_type VARCHAR(64) NOT NULL DEFAULT 'information',
            title VARCHAR(255) NOT NULL,
            content_json LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 50,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY briefing_idx (briefing_id),
            KEY retailer_idx (retailer_key),
            KEY type_idx (section_type),
            KEY sort_idx (sort_order)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}briefing_store_aliases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            retailer_key VARCHAR(64) NOT NULL,
            external_name VARCHAR(255) NOT NULL,
            normalized_name VARCHAR(191) NOT NULL,
            location_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_alias (client_id, project_id, retailer_key, normalized_name),
            KEY location_idx (location_id),
            KEY retailer_idx (retailer_key)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}briefing_store_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            briefing_id BIGINT UNSIGNED NOT NULL,
            retailer_key VARCHAR(64) NOT NULL,
            external_store_name VARCHAR(255) NOT NULL,
            normalized_store_name VARCHAR(191) NOT NULL,
            alias_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            item_type VARCHAR(64) NOT NULL DEFAULT 'information',
            title VARCHAR(255) NOT NULL,
            details_json LONGTEXT NULL,
            valid_from DATE NULL,
            valid_to DATE NULL,
            sort_order INT NOT NULL DEFAULT 50,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY briefing_idx (briefing_id),
            KEY alias_idx (alias_id),
            KEY location_idx (location_id),
            KEY retailer_idx (retailer_key),
            KEY type_idx (item_type)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}briefing_recipients (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            briefing_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            version_no INT UNSIGNED NOT NULL DEFAULT 1,
            location_ids_json LONGTEXT NULL,
            retailer_keys_json LONGTEXT NULL,
            first_viewed_at DATETIME NULL,
            last_viewed_at DATETIME NULL,
            acknowledged_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_recipient (briefing_id, user_id, version_no),
            KEY user_idx (user_id),
            KEY briefing_idx (briefing_id),
            KEY ack_idx (acknowledged_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}implementation_documents (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            version_no INT UNSIGNED NOT NULL DEFAULT 1,
            valid_from DATE NULL,
            valid_to DATE NULL,
            notes_text LONGTEXT NULL,
            source_filename VARCHAR(255) NULL,
            source_hash CHAR(64) NULL,
            import_summary_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            published_by BIGINT UNSIGNED NULL,
            published_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY status_idx (status),
            KEY validity_idx (valid_from, valid_to),
            KEY published_idx (published_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}implementation_sections (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            section_key VARCHAR(191) NOT NULL,
            title VARCHAR(255) NOT NULL,
            summary_text TEXT NULL,
            metadata_json LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 50,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY document_idx (document_id),
            KEY section_key_idx (section_key),
            KEY sort_idx (sort_order)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}implementation_store_aliases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            alias_key CHAR(64) NOT NULL,
            external_store_code VARCHAR(64) NOT NULL DEFAULT '',
            external_name VARCHAR(255) NOT NULL,
            normalized_name VARCHAR(191) NOT NULL,
            location_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_alias (client_id, project_id, alias_key),
            KEY location_idx (location_id),
            KEY code_idx (external_store_code),
            KEY name_idx (normalized_name)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}implementation_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            document_id BIGINT UNSIGNED NOT NULL,
            section_id BIGINT UNSIGNED NOT NULL,
            alias_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            external_store_code VARCHAR(64) NOT NULL DEFAULT '',
            external_store_name VARCHAR(255) NOT NULL,
            normalized_store_name VARCHAR(191) NOT NULL,
            sales_rep VARCHAR(128) NULL,
            implementation_type VARCHAR(255) NULL,
            planned_date DATE NULL,
            planned_date_text VARCHAR(191) NULL,
            status_key VARCHAR(64) NOT NULL DEFAULT 'pending',
            status_label VARCHAR(255) NOT NULL DEFAULT 'Por atualizar',
            observations TEXT NULL,
            materials_proposed INT NOT NULL DEFAULT 0,
            materials_missing INT NOT NULL DEFAULT 0,
            materials_implemented INT NOT NULL DEFAULT 0,
            details_json LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 50,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY document_idx (document_id),
            KEY section_idx (section_id),
            KEY alias_idx (alias_id),
            KEY location_idx (location_id),
            KEY status_idx (status_key),
            KEY code_idx (external_store_code),
            KEY sort_idx (sort_order)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}expenses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            expense_date DATE NOT NULL,
            category VARCHAR(64) NOT NULL DEFAULT 'outros',
            merchant_name VARCHAR(255) NULL,
            issuer_tax_id VARCHAR(32) NULL,
            document_no VARCHAR(191) NULL,
            atcud VARCHAR(191) NULL,
            description TEXT NULL,
            payment_method VARCHAR(64) NOT NULL DEFAULT 'other',
            amount_net DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            amount_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            capture_source VARCHAR(32) NOT NULL DEFAULT 'manual',
            verification_state VARCHAR(32) NOT NULL DEFAULT 'manual',
            qr_payload LONGTEXT NULL,
            qr_fields_json LONGTEXT NULL,
            receipt_path TEXT NULL,
            receipt_mime VARCHAR(128) NULL,
            receipt_original_name VARCHAR(255) NULL,
            receipt_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            duplicate_hash CHAR(64) NULL,
            duplicate_of_id BIGINT UNSIGNED NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY user_idx (user_id),
            KEY date_idx (expense_date),
            KEY status_idx (status),
            KEY category_idx (category),
            KEY duplicate_idx (duplicate_hash),
            KEY duplicate_of_idx (duplicate_of_id),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}stock_batches (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            insignia VARCHAR(191) NOT NULL,
            snapshot_date DATE NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            source_filename VARCHAR(255) NULL,
            sheet_name VARCHAR(191) NULL,
            header_row INT NOT NULL DEFAULT 1,
            row_count INT NOT NULL DEFAULT 0,
            store_count INT NOT NULL DEFAULT 0,
            product_count INT NOT NULL DEFAULT 0,
            matched_store_count INT NOT NULL DEFAULT 0,
            unmatched_row_count INT NOT NULL DEFAULT 0,
            total_stock DECIMAL(18,3) NOT NULL DEFAULT 0,
            zero_count INT NOT NULL DEFAULT 0,
            low_count INT NOT NULL DEFAULT 0,
            negative_count INT NOT NULL DEFAULT 0,
            import_hash CHAR(64) NOT NULL,
            mapping_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            published_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY import_hash_idx (import_hash),
            KEY scope_idx (client_id, project_id),
            KEY snapshot_idx (snapshot_date),
            KEY status_idx (status),
            KEY insignia_idx (insignia)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}stock_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_store_key CHAR(40) NOT NULL,
            store_external_ref VARCHAR(128) NULL,
            store_name VARCHAR(255) NOT NULL,
            locality VARCHAR(191) NULL,
            article_ref VARCHAR(128) NULL,
            ean VARCHAR(32) NULL,
            product_name VARCHAR(255) NOT NULL,
            unit VARCHAR(32) NULL,
            conversion_factor DECIMAL(14,4) NOT NULL DEFAULT 1,
            stock_total DECIMAL(18,3) NOT NULL DEFAULT 0,
            match_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
            match_method VARCHAR(64) NULL,
            cardex_item_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            product_match_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
            product_match_method VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY batch_idx (batch_id),
            KEY scope_idx (client_id, project_id),
            KEY location_idx (location_id),
            KEY store_key_idx (source_store_key),
            KEY ean_idx (ean),
            KEY article_idx (article_ref),
            KEY cardex_item_idx (cardex_item_id),
            KEY stock_idx (stock_total)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}stock_store_mappings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            insignia VARCHAR(191) NOT NULL,
            source_store_key CHAR(40) NOT NULL,
            source_store_ref VARCHAR(128) NULL,
            source_store_name VARCHAR(255) NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            confidence TINYINT UNSIGNED NOT NULL DEFAULT 100,
            match_method VARCHAR(64) NOT NULL DEFAULT 'manual',
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_scope_store (client_id, project_id, insignia, source_store_key),
            KEY location_idx (location_id),
            KEY project_idx (project_id)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}stock_views (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            batch_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            first_viewed_at DATETIME NOT NULL,
            last_viewed_at DATETIME NOT NULL,
            view_count INT NOT NULL DEFAULT 1,
            UNIQUE KEY uniq_batch_user (batch_id, user_id),
            KEY batch_idx (batch_id),
            KEY user_idx (user_id),
            KEY viewed_idx (last_viewed_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mapping_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mapping_uuid CHAR(36) NOT NULL,
            entity_type VARCHAR(32) NOT NULL DEFAULT 'store',
            source_module VARCHAR(32) NOT NULL,
            source_scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_record_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            source_key VARCHAR(191) NOT NULL,
            external_ref VARCHAR(191) NULL,
            source_label VARCHAR(255) NOT NULL,
            normalized_label VARCHAR(191) NOT NULL,
            target_type VARCHAR(32) NOT NULL DEFAULT 'location',
            target_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
            match_method VARCHAR(64) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            is_locked TINYINT(1) NOT NULL DEFAULT 0,
            candidate_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            last_synced_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY mapping_uuid_idx (mapping_uuid),
            UNIQUE KEY source_identity (source_module, client_id, project_id, source_key),
            KEY scope_idx (client_id, project_id),
            KEY entity_idx (entity_type),
            KEY module_idx (source_module),
            KEY status_idx (status),
            KEY target_idx (target_type, target_id),
            KEY confidence_idx (confidence)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mapping_aliases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(32) NOT NULL DEFAULT 'store',
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            alias_label VARCHAR(255) NOT NULL,
            normalized_alias VARCHAR(191) NOT NULL,
            target_type VARCHAR(32) NOT NULL DEFAULT 'location',
            target_id BIGINT UNSIGNED NOT NULL,
            confidence TINYINT UNSIGNED NOT NULL DEFAULT 100,
            source_module VARCHAR(32) NOT NULL DEFAULT 'manual',
            use_count INT UNSIGNED NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY alias_scope_idx (entity_type, client_id, project_id, normalized_alias),
            KEY target_idx (target_type, target_id),
            KEY use_idx (use_count)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mapping_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mapping_id BIGINT UNSIGNED NOT NULL,
            action_name VARCHAR(64) NOT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            reason_text TEXT NULL,
            batch_uuid CHAR(36) NULL,
            actor_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY mapping_idx (mapping_id),
            KEY action_idx (action_name),
            KEY batch_idx (batch_uuid),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_drafts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            binding_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            route_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            route_stop_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            report_day DATE NOT NULL,
            schema_revision VARCHAR(64) NOT NULL,
            revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
            client_instance_id VARCHAR(64) NULL,
            client_updated_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
            payload_json LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_draft_scope (user_id, form_id, binding_id, client_id, project_id, route_id, route_stop_id, location_id, report_day),
            KEY user_updated_idx (user_id, updated_at),
            KEY expires_idx (expires_at),
            KEY location_day_idx (location_id, report_day)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}form_draft_files (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            draft_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            token CHAR(48) NOT NULL,
            input_name VARCHAR(191) NOT NULL,
            input_ordinal INT UNSIGNED NOT NULL DEFAULT 0,
            file_index INT UNSIGNED NOT NULL DEFAULT 0,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(128) NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            file_signature CHAR(64) NULL,
            storage_path TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY token_idx (token),
            KEY draft_input_idx (draft_id, input_name, input_ordinal),
            KEY user_idx (user_id),
            KEY expires_idx (expires_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_stop_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            event_type VARCHAR(32) NOT NULL,
            payload_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY stop (route_stop_id),
            KEY user (user_id),
            KEY type (event_type),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mobile_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            device_id VARCHAR(191) NOT NULL,
            device_name VARCHAR(255) NULL,
            platform VARCHAR(32) NULL,
            app_version VARCHAR(64) NULL,
            access_token_hash CHAR(64) NOT NULL,
            refresh_token_hash CHAR(64) NOT NULL,
            access_expires_at DATETIME NOT NULL,
            refresh_expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            last_ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY access_hash_idx (access_token_hash),
            UNIQUE KEY refresh_hash_idx (refresh_token_hash),
            KEY user_idx (user_id),
            KEY device_idx (device_id),
            KEY refresh_expiry_idx (refresh_expires_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mobile_mutations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            mutation_id VARCHAR(64) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            device_id VARCHAR(191) NULL,
            mutation_type VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'completed',
            response_code INT NOT NULL DEFAULT 200,
            response_json LONGTEXT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY mutation_user_idx (mutation_id, user_id),
            KEY user_idx (user_id),
            KEY device_idx (device_id),
            KEY expires_idx (expires_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mobile_sync_devices (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            device_id VARCHAR(191) NOT NULL,
            platform VARCHAR(32) NULL,
            app_version VARCHAR(64) NULL,
            cache_version VARCHAR(128) NULL,
            queue_pending INT UNSIGNED NOT NULL DEFAULT 0,
            queue_failed INT UNSIGNED NOT NULL DEFAULT 0,
            last_cache_at DATETIME NULL,
            last_sync_at DATETIME NULL,
            last_error VARCHAR(191) NULL,
            last_seen_at DATETIME NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_device_idx (user_id, device_id),
            KEY user_idx (user_id),
            KEY device_idx (device_id),
            KEY seen_idx (last_seen_at),
            KEY sync_idx (last_sync_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}saas_plans (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL,
            name VARCHAR(191) NOT NULL,
            description TEXT NULL,
            monthly_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            annual_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'EUR',
            capabilities_json LONGTEXT NULL,
            limits_json LONGTEXT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY slug_idx (slug),
            KEY active_idx (is_active)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}client_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            starts_at DATETIME NULL,
            trial_ends_at DATETIME NULL,
            renews_at DATETIME NULL,
            expires_at DATETIME NULL,
            capability_overrides_json LONGTEXT NULL,
            limit_overrides_json LONGTEXT NULL,
            status_reason VARCHAR(255) NULL,
            notes TEXT NULL,
            updated_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY client_idx (client_id),
            KEY plan_idx (plan_id),
            KEY status_idx (status),
            KEY renew_idx (renews_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}usage_counters (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            metric VARCHAR(64) NOT NULL,
            period_key VARCHAR(16) NOT NULL,
            quantity BIGINT NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY client_metric_period (client_id, metric, period_key),
            KEY metric_idx (metric),
            KEY period_idx (period_key)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_prices (
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY plan_env_interval_idx (plan_id, provider, environment, billing_interval),
            KEY price_idx (external_price_id),
            KEY active_idx (is_active)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_customers (
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY client_provider_env_idx (client_id, provider, environment),
            KEY external_customer_idx (external_customer_id),
            KEY status_idx (status)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_subscriptions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NOT NULL,
            billing_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY client_provider_env_idx (client_id, provider, environment),
            KEY external_subscription_idx (external_subscription_id),
            KEY status_grace_idx (status, grace_until),
            KEY plan_idx (plan_id),
            KEY billing_user_idx (billing_user_id),
            KEY billing_user_status_idx (billing_user_id, status),
            KEY client_environment_status_idx (client_id, environment, status)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_invoices (
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY external_invoice_idx (environment, external_invoice_id),
            KEY client_status_idx (client_id, status),
            KEY subscription_idx (external_subscription_id),
            KEY paid_idx (paid_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_events (
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY provider_event_idx (provider, environment, provider_event_id),
            KEY type_status_idx (event_type, status),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_checkout_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_token_hash CHAR(64) NOT NULL,
            provider VARCHAR(32) NOT NULL DEFAULT 'stripe',
            environment VARCHAR(16) NOT NULL DEFAULT 'sandbox',
            external_session_id VARCHAR(191) NULL,
            client_id BIGINT UNSIGNED NOT NULL,
            billing_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            plan_id BIGINT UNSIGNED NOT NULL,
            billing_interval VARCHAR(16) NOT NULL DEFAULT 'month',
            status VARCHAR(24) NOT NULL DEFAULT 'created',
            checkout_url TEXT NULL,
            expires_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY token_idx (session_token_hash),
            KEY external_session_idx (external_session_id),
            KEY client_status_idx (client_id, status),
            KEY billing_user_idx (billing_user_id),
            KEY expires_idx (expires_at)
        ) $charset;";


        $tables[] = "CREATE TABLE {$prefix}billing_signup_intents (
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY token_idx (token_hash),
            KEY user_status_idx (user_id, status),
            KEY client_idx (client_id),
            KEY plan_idx (plan_id),
            KEY expires_idx (expires_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_consents (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}billing_email_log (
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
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}api_usage_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(48) NOT NULL,
            operation VARCHAR(64) NOT NULL,
            status VARCHAR(24) NOT NULL DEFAULT 'success',
            cache_status VARCHAR(24) NOT NULL DEFAULT 'unknown',
            request_count INT UNSIGNED NOT NULL DEFAULT 0,
            units DECIMAL(18,4) NOT NULL DEFAULT 0,
            estimated_cost_eur DECIMAL(18,8) NOT NULL DEFAULT 0,
            avoided_cost_eur DECIMAL(18,8) NOT NULL DEFAULT 0,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            http_status SMALLINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY provider_idx (provider),
            KEY operation_idx (operation),
            KEY status_idx (status),
            KEY cache_idx (cache_status),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY route_idx (route_id),
            KEY created_idx (created_at),
            KEY provider_created_idx (provider, created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}media_telemetry (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            status VARCHAR(24) NOT NULL DEFAULT 'success',
            error_code VARCHAR(64) NULL,
            form_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            file_count INT UNSIGNED NOT NULL DEFAULT 0,
            original_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            optimized_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            server_received_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            saved_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            optimized_files INT UNSIGNED NOT NULL DEFAULT 0,
            duplicate_files INT UNSIGNED NOT NULL DEFAULT 0,
            retry_count INT UNSIGNED NOT NULL DEFAULT 0,
            processing_ms INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY status_idx (status),
            KEY error_idx (error_code),
            KEY form_idx (form_id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY route_idx (route_id),
            KEY created_idx (created_at),
            KEY status_created_idx (status, created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}media_registry (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            url_hash CHAR(64) NOT NULL,
            public_url TEXT NOT NULL,
            storage_path TEXT NULL,
            owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            route_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            route_stop_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            submission_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            question_key VARCHAR(191) NULL,
            category VARCHAR(191) NULL,
            source VARCHAR(64) NOT NULL DEFAULT 'unknown',
            protection_status VARCHAR(32) NOT NULL DEFAULT 'observed',
            first_seen_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY url_hash_idx (url_hash),
            KEY attachment_idx (attachment_id),
            KEY owner_idx (owner_user_id),
            KEY client_project_idx (client_id, project_id),
            KEY route_idx (route_id),
            KEY stop_idx (route_stop_id),
            KEY location_idx (location_id),
            KEY submission_idx (submission_id),
            KEY protection_idx (protection_status),
            KEY created_idx (created_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}media_shadow_access (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            media_registry_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            surface VARCHAR(64) NOT NULL DEFAULT 'unknown',
            decision VARCHAR(16) NOT NULL DEFAULT 'unknown',
            reason_code VARCHAR(64) NOT NULL DEFAULT 'unknown',
            observed_date DATE NOT NULL,
            event_count BIGINT UNSIGNED NOT NULL DEFAULT 1,
            first_seen_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY aggregate_idx (media_registry_id, user_id, surface, decision, reason_code, observed_date),
            KEY media_idx (media_registry_id),
            KEY user_idx (user_id),
            KEY decision_idx (decision),
            KEY date_idx (observed_date),
            KEY last_seen_idx (last_seen_at)
        ) $charset;";

        $tables[] = "CREATE TABLE {$prefix}mobile_web_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ticket_hash CHAR(64) NOT NULL,
            mobile_session_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            device_id VARCHAR(191) NULL,
            target VARCHAR(32) NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            ticket_expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            web_token_hash CHAR(64) NULL,
            web_expires_at DATETIME NULL,
            revoked_at DATETIME NULL,
            last_seen_at DATETIME NULL,
            created_ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY ticket_hash_idx (ticket_hash),
            KEY web_token_idx (web_token_hash),
            KEY mobile_session_idx (mobile_session_id),
            KEY user_idx (user_id),
            KEY target_idx (target),
            KEY ticket_expiry_idx (ticket_expires_at),
            KEY web_expiry_idx (web_expires_at)
        ) $charset;";

        foreach ($tables as $sql) {
            if (preg_match('/CREATE TABLE\s+([^\s(]+)/i', $sql, $m)) {
                $table_name = trim($m[1], "` ");
                $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
                if ($exists === $table_name) {
                    continue;
                }
            }
            dbDelta($sql);
        }

        if (class_exists('\\RoutesPro\\Stocks\\StockSchema')) {
            \RoutesPro\Stocks\StockSchema::ensure(true);
        }
        if (class_exists('\\RoutesPro\\Admin\\ContextQuestions')) {
            \RoutesPro\Admin\ContextQuestions::ensure_table();
        }

        // Migrações suaves (para instalações existentes)
        self::migrate($wpdb, $prefix);
        $versionedMigrationsOk = true;
        if (class_exists('\\RoutesPro\\Support\\Migrations')) {
            $versionedMigrationsOk = \RoutesPro\Support\Migrations::run($wpdb, $prefix);
        }
        if (class_exists('\\RoutesPro\\SaaS\\PlanRepository')) {
            $subscriptionsTable = \RoutesPro\SaaS\PlanRepository::subscriptionsTable();
            if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $subscriptionsTable)) === $subscriptionsTable) {
                $hasReason = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$subscriptionsTable} LIKE %s", 'status_reason'));
                if (!$hasReason) $wpdb->query("ALTER TABLE {$subscriptionsTable} ADD COLUMN status_reason VARCHAR(255) NULL AFTER limit_overrides_json");
            }
            \RoutesPro\SaaS\PlanRepository::seed();
            \RoutesPro\SaaS\PlanRepository::migrateExistingClients($previousVersion);
        }

        // 2.2.332: consolida as perguntas recorrentes dos briefings em métricas
        // permanentes. A migração é não destrutiva, preserva perguntas, respostas,
        // submissões e evidências, e apenas desativa bindings automáticos duplicados.
        $evergreenAnalyticsFlag = 'routespro_evergreen_briefing_analytics_22332';
        if (version_compare($previousVersion, '2.2.332', '<') || !get_option($evergreenAnalyticsFlag)) {
            if (class_exists('\RoutesPro\Admin\ContextQuestions')) {
                \RoutesPro\Admin\ContextQuestions::ensure_table();
            }
            $evergreenResult = [
                'processed' => 0,
                'synced' => 0,
                'deactivated' => 0,
                'dashboards_assigned' => 0,
                'errors' => [],
            ];
            if (class_exists('\RoutesPro\Analytics\QuestionAnalyticsService')) {
                $evergreenResult = \RoutesPro\Analytics\QuestionAnalyticsService::reconcileEvergreenBriefingAnalytics(true);
            }
            update_option($evergreenAnalyticsFlag, [
                'version' => '2.2.332',
                'processed' => (int)($evergreenResult['processed'] ?? 0),
                'synced' => (int)($evergreenResult['synced'] ?? 0),
                'deactivated' => (int)($evergreenResult['deactivated'] ?? 0),
                'dashboards_assigned' => (int)($evergreenResult['dashboards_assigned'] ?? 0),
                'errors' => array_values(array_slice((array)($evergreenResult['errors'] ?? []), 0, 20)),
                'completed_at' => current_time('mysql'),
            ], false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Analytics permanente dos briefings reconciliado.',
                    ['context_key' => 'evergreen_briefing_analytics'],
                    [
                        'processed' => (int)($evergreenResult['processed'] ?? 0),
                        'synced' => (int)($evergreenResult['synced'] ?? 0),
                        'deactivated' => (int)($evergreenResult['deactivated'] ?? 0),
                        'dashboards_assigned' => (int)($evergreenResult['dashboards_assigned'] ?? 0),
                        'errors' => count((array)($evergreenResult['errors'] ?? [])),
                    ]
                );
            }
        }


        // 2.2.334: Smart Evergreen Dashboard. Perguntas de briefing pontuais passam
        // para dois widgets dinâmicos e apenas famílias estratégicas permanecem como
        // métricas individuais. A migração nunca apaga respostas, submissões ou ficheiros.
        $smartAnalyticsFlag = 'routespro_smart_briefing_dashboard_22334';
        if (version_compare($previousVersion, '2.2.334', '<') || !get_option($smartAnalyticsFlag)) {
            if (class_exists('\RoutesPro\Admin\ContextQuestions')) {
                \RoutesPro\Admin\ContextQuestions::ensure_table();
            }
            $smartResult = [
                'processed' => 0,
                'synced' => 0,
                'deactivated' => 0,
                'legacy_deactivated' => 0,
                'rollup' => 0,
                'permanent' => 0,
                'dashboards_assigned' => 0,
                'errors' => [],
            ];
            if (class_exists('\RoutesPro\Analytics\QuestionAnalyticsService')) {
                $smartResult = \RoutesPro\Analytics\QuestionAnalyticsService::reconcileSmartBriefingAnalytics(true);
            }
            update_option($smartAnalyticsFlag, [
                'version' => '2.2.334',
                'processed' => (int)($smartResult['processed'] ?? 0),
                'synced' => (int)($smartResult['synced'] ?? 0),
                'deactivated' => (int)($smartResult['deactivated'] ?? 0),
                'legacy_deactivated' => (int)($smartResult['legacy_deactivated'] ?? 0),
                'rollup' => (int)($smartResult['rollup'] ?? 0),
                'permanent' => (int)($smartResult['permanent'] ?? 0),
                'dashboards_assigned' => (int)($smartResult['dashboards_assigned'] ?? 0),
                'errors' => array_values(array_slice((array)($smartResult['errors'] ?? []), 0, 20)),
                'completed_at' => current_time('mysql'),
            ], false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Smart Evergreen Dashboard dos briefings reconciliado.',
                    ['context_key' => 'smart_evergreen_briefing_dashboard'],
                    [
                        'processed' => (int)($smartResult['processed'] ?? 0),
                        'synced' => (int)($smartResult['synced'] ?? 0),
                        'deactivated' => (int)($smartResult['deactivated'] ?? 0),
                        'legacy_deactivated' => (int)($smartResult['legacy_deactivated'] ?? 0),
                        'rollup' => (int)($smartResult['rollup'] ?? 0),
                        'permanent' => (int)($smartResult['permanent'] ?? 0),
                        'dashboards_assigned' => (int)($smartResult['dashboards_assigned'] ?? 0),
                        'errors' => count((array)($smartResult['errors'] ?? [])),
                    ]
                );
            }
        }


        // 2.2.335: Consolidação agressiva do dashboard evergreen. Widgets legados
        // cobertos por métricas permanentes são desativados, famílias operacionais
        // estáveis são agregadas e fotografias permanentes entram num único widget.
        // A migração é não destrutiva: nunca apaga respostas, ficheiros ou bindings.
        $aggressiveEvergreenFlag = 'routespro_aggressive_evergreen_consolidation_22335';
        if (version_compare($previousVersion, '2.2.335', '<') || !get_option($aggressiveEvergreenFlag)) {
            if (class_exists('\RoutesPro\Admin\ContextQuestions')) {
                \RoutesPro\Admin\ContextQuestions::ensure_table();
            }
            $aggressiveResult = [
                'processed' => 0,
                'synced' => 0,
                'deactivated' => 0,
                'legacy_deactivated' => 0,
                'covered_legacy_deactivated' => 0,
                'rollup' => 0,
                'permanent' => 0,
                'operational_families' => 0,
                'operational_evidence_rollups' => 0,
                'dashboards_assigned' => 0,
                'errors' => [],
            ];
            if (class_exists('\RoutesPro\Analytics\QuestionAnalyticsService')) {
                $aggressiveResult = \RoutesPro\Analytics\QuestionAnalyticsService::reconcileSmartBriefingAnalytics(true);
            }
            update_option($aggressiveEvergreenFlag, [
                'version' => '2.2.335',
                'processed' => (int)($aggressiveResult['processed'] ?? 0),
                'synced' => (int)($aggressiveResult['synced'] ?? 0),
                'deactivated' => (int)($aggressiveResult['deactivated'] ?? 0),
                'legacy_deactivated' => (int)($aggressiveResult['legacy_deactivated'] ?? 0),
                'covered_legacy_deactivated' => (int)($aggressiveResult['covered_legacy_deactivated'] ?? 0),
                'rollup' => (int)($aggressiveResult['rollup'] ?? 0),
                'permanent' => (int)($aggressiveResult['permanent'] ?? 0),
                'operational_families' => (int)($aggressiveResult['operational_families'] ?? 0),
                'operational_evidence_rollups' => (int)($aggressiveResult['operational_evidence_rollups'] ?? 0),
                'dashboards_assigned' => (int)($aggressiveResult['dashboards_assigned'] ?? 0),
                'errors' => array_values(array_slice((array)($aggressiveResult['errors'] ?? []), 0, 20)),
                'completed_at' => current_time('mysql'),
            ], false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Consolidação agressiva do Smart Evergreen Dashboard concluída.',
                    ['context_key' => 'aggressive_evergreen_consolidation'],
                    [
                        'processed' => (int)($aggressiveResult['processed'] ?? 0),
                        'synced' => (int)($aggressiveResult['synced'] ?? 0),
                        'deactivated' => (int)($aggressiveResult['deactivated'] ?? 0),
                        'covered_legacy_deactivated' => (int)($aggressiveResult['covered_legacy_deactivated'] ?? 0),
                        'operational_families' => (int)($aggressiveResult['operational_families'] ?? 0),
                        'operational_evidence_rollups' => (int)($aggressiveResult['operational_evidence_rollups'] ?? 0),
                        'errors' => count((array)($aggressiveResult['errors'] ?? [])),
                    ]
                );
            }
        }


        // 2.2.336: Semantic Dashboard Guardrails. Corrige classificações
        // automáticas que herdaram famílias apenas pelo tipo técnico da pergunta,
        // limpa duplicados semânticos e preserva escolhas manuais do administrador.
        // A reconciliação continua não destrutiva e apenas desativa bindings legado.
        $semanticGuardrailsFlag = 'routespro_semantic_dashboard_guardrails_22336';
        if (version_compare($previousVersion, '2.2.336', '<') || !get_option($semanticGuardrailsFlag)) {
            if (class_exists('\RoutesPro\Admin\ContextQuestions')) {
                \RoutesPro\Admin\ContextQuestions::ensure_table();
            }
            $semanticResult = [
                'processed' => 0,
                'synced' => 0,
                'deactivated' => 0,
                'legacy_deactivated' => 0,
                'covered_legacy_deactivated' => 0,
                'semantic_legacy_deactivated' => 0,
                'rollup' => 0,
                'permanent' => 0,
                'operational_families' => 0,
                'operational_evidence_rollups' => 0,
                'dashboards_assigned' => 0,
                'errors' => [],
            ];
            if (class_exists('\RoutesPro\Analytics\QuestionAnalyticsService')) {
                $semanticResult = \RoutesPro\Analytics\QuestionAnalyticsService::reconcileSmartBriefingAnalytics(true);
            }
            update_option($semanticGuardrailsFlag, [
                'version' => '2.2.336',
                'processed' => (int)($semanticResult['processed'] ?? 0),
                'synced' => (int)($semanticResult['synced'] ?? 0),
                'deactivated' => (int)($semanticResult['deactivated'] ?? 0),
                'legacy_deactivated' => (int)($semanticResult['legacy_deactivated'] ?? 0),
                'covered_legacy_deactivated' => (int)($semanticResult['covered_legacy_deactivated'] ?? 0),
                'semantic_legacy_deactivated' => (int)($semanticResult['semantic_legacy_deactivated'] ?? 0),
                'rollup' => (int)($semanticResult['rollup'] ?? 0),
                'permanent' => (int)($semanticResult['permanent'] ?? 0),
                'operational_families' => (int)($semanticResult['operational_families'] ?? 0),
                'operational_evidence_rollups' => (int)($semanticResult['operational_evidence_rollups'] ?? 0),
                'dashboards_assigned' => (int)($semanticResult['dashboards_assigned'] ?? 0),
                'errors' => array_values(array_slice((array)($semanticResult['errors'] ?? []), 0, 20)),
                'completed_at' => current_time('mysql'),
            ], false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Semantic Dashboard Guardrails reconciliado.',
                    ['context_key' => 'semantic_dashboard_guardrails'],
                    [
                        'processed' => (int)($semanticResult['processed'] ?? 0),
                        'synced' => (int)($semanticResult['synced'] ?? 0),
                        'deactivated' => (int)($semanticResult['deactivated'] ?? 0),
                        'semantic_legacy_deactivated' => (int)($semanticResult['semantic_legacy_deactivated'] ?? 0),
                        'rollup' => (int)($semanticResult['rollup'] ?? 0),
                        'permanent' => (int)($semanticResult['permanent'] ?? 0),
                        'errors' => count((array)($semanticResult['errors'] ?? [])),
                    ]
                );
            }
        }


        // 2.2.337: Explicit Analytics Ownership + Mobile Analytics Layout.
        // Repara falsos marcadores de propriedade criados por saves antigos,
        // volta a inferir perguntas nao confirmadas pelo significado de negocio
        // e reconcilia os bindings evergreen sem eliminar historico.
        $explicitOwnershipFlag = 'routespro_explicit_analytics_ownership_22337';
        if (version_compare($previousVersion, '2.2.337', '<') || !get_option($explicitOwnershipFlag)) {
            if (class_exists('\RoutesPro\Admin\ContextQuestions')) {
                \RoutesPro\Admin\ContextQuestions::ensure_table();
            }
            $ownershipResult = [
                'processed' => 0,
                'synced' => 0,
                'deactivated' => 0,
                'legacy_deactivated' => 0,
                'covered_legacy_deactivated' => 0,
                'semantic_legacy_deactivated' => 0,
                'rollup' => 0,
                'permanent' => 0,
                'operational_families' => 0,
                'operational_evidence_rollups' => 0,
                'dashboards_assigned' => 0,
                'ownership_repaired' => 0,
                'errors' => [],
            ];
            if (class_exists('\RoutesPro\Analytics\QuestionAnalyticsService')) {
                $ownershipResult = \RoutesPro\Analytics\QuestionAnalyticsService::reconcileSmartBriefingAnalytics(true);
            }
            update_option($explicitOwnershipFlag, [
                'version' => '2.2.337',
                'processed' => (int)($ownershipResult['processed'] ?? 0),
                'synced' => (int)($ownershipResult['synced'] ?? 0),
                'deactivated' => (int)($ownershipResult['deactivated'] ?? 0),
                'legacy_deactivated' => (int)($ownershipResult['legacy_deactivated'] ?? 0),
                'covered_legacy_deactivated' => (int)($ownershipResult['covered_legacy_deactivated'] ?? 0),
                'semantic_legacy_deactivated' => (int)($ownershipResult['semantic_legacy_deactivated'] ?? 0),
                'rollup' => (int)($ownershipResult['rollup'] ?? 0),
                'permanent' => (int)($ownershipResult['permanent'] ?? 0),
                'operational_families' => (int)($ownershipResult['operational_families'] ?? 0),
                'operational_evidence_rollups' => (int)($ownershipResult['operational_evidence_rollups'] ?? 0),
                'dashboards_assigned' => (int)($ownershipResult['dashboards_assigned'] ?? 0),
                'ownership_repaired' => (int)($ownershipResult['ownership_repaired'] ?? 0),
                'errors' => array_values(array_slice((array)($ownershipResult['errors'] ?? []), 0, 20)),
                'completed_at' => current_time('mysql'),
            ], false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Explicit Analytics Ownership reconciliado.',
                    ['context_key' => 'explicit_analytics_ownership'],
                    [
                        'processed' => (int)($ownershipResult['processed'] ?? 0),
                        'synced' => (int)($ownershipResult['synced'] ?? 0),
                        'deactivated' => (int)($ownershipResult['deactivated'] ?? 0),
                        'ownership_repaired' => (int)($ownershipResult['ownership_repaired'] ?? 0),
                        'rollup' => (int)($ownershipResult['rollup'] ?? 0),
                        'permanent' => (int)($ownershipResult['permanent'] ?? 0),
                        'errors' => count((array)($ownershipResult['errors'] ?? [])),
                    ]
                );
            }
        }


        // 2.2.338: Context-aware Analytics census + Comparativo duplicate cleanup.
        // The 2.2.337 release introduced the stable Comparativo family, but the
        // semantic duplicate cleaner did not yet include that family in its
        // known-family set. Re-run the evergreen reconciler once so the new
        // owner replaces the old legacy binding instead of increasing the
        // active widget count. No bindings are deleted, replaced rows become
        // inactive/legacy only.
        $analyticsCensusFlag = 'routespro_context_analytics_census_22338';
        if (version_compare($previousVersion, '2.2.338', '<') || !get_option($analyticsCensusFlag)) {
            $censusResult = [
                'processed' => 0,
                'synced' => 0,
                'deactivated' => 0,
                'semantic_legacy_deactivated' => 0,
                'errors' => [],
            ];
            if (class_exists('\RoutesPro\Analytics\QuestionAnalyticsService')) {
                $censusResult = \RoutesPro\Analytics\QuestionAnalyticsService::reconcileSmartBriefingAnalytics(true);
            }
            update_option($analyticsCensusFlag, [
                'version' => '2.2.338',
                'processed' => (int)($censusResult['processed'] ?? 0),
                'synced' => (int)($censusResult['synced'] ?? 0),
                'deactivated' => (int)($censusResult['deactivated'] ?? 0),
                'semantic_legacy_deactivated' => (int)($censusResult['semantic_legacy_deactivated'] ?? 0),
                'errors' => array_values(array_slice((array)($censusResult['errors'] ?? []), 0, 20)),
                'completed_at' => current_time('mysql'),
            ], false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Context-aware Analytics census reconciliado.',
                    ['context_key' => 'analytics_census_22338'],
                    [
                        'processed' => (int)($censusResult['processed'] ?? 0),
                        'synced' => (int)($censusResult['synced'] ?? 0),
                        'deactivated' => (int)($censusResult['deactivated'] ?? 0),
                        'semantic_legacy_deactivated' => (int)($censusResult['semantic_legacy_deactivated'] ?? 0),
                    ]
                );
            }
        }

        if (!wp_next_scheduled('fieldflow_form_draft_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'fieldflow_form_draft_cleanup');
        }

        if (class_exists('\\RoutesPro\\Support\\Logger')) {
            if ($versionedMigrationsOk) {
                \RoutesPro\Support\Logger::info('Schema principal validado com sucesso.', ['context_key' => 'migration'], ['version' => defined('ROUTESPRO_VERSION') ? ROUTESPRO_VERSION : 'unknown']);
            } else {
                \RoutesPro\Support\Logger::warning('A versão do plugin não foi marcada como concluída porque existe uma migração pendente ou falhada.', ['context_key'=>'migration'], ['target_version'=>defined('ROUTESPRO_VERSION') ? ROUTESPRO_VERSION : 'unknown']);
            }
        }

        // A versão instalada só avança quando as migrações versionadas terminam.
        if ($versionedMigrationsOk) {
            if (!get_option('routespro_version')) {
                add_option('routespro_version', defined('ROUTESPRO_VERSION') ? ROUTESPRO_VERSION : '1.3.0');
            } else {
                update_option('routespro_version', defined('ROUTESPRO_VERSION') ? ROUTESPRO_VERSION : get_option('routespro_version'));
            }
        }

        // Trabalhos históricos potencialmente pesados só são executados numa atualização real.
        $needsHistoricalMaintenance = $hadInstalledVersion
            && defined('ROUTESPRO_VERSION')
            && version_compare($previousVersion, '2.2.266', '<')
            && version_compare($previousVersion, ROUTESPRO_VERSION, '<');
        if ($versionedMigrationsOk && $needsHistoricalMaintenance) {
            if (class_exists('\RoutesPro\Support\BatchMigrationManager') && \RoutesPro\Support\BatchMigrationManager::tableReady()) {
                \RoutesPro\Support\BatchMigrationManager::start('location_deduplication', 0, 5, 0, ['trigger'=>'plugin_upgrade','from'=>$previousVersion]);
                \RoutesPro\Support\BatchMigrationManager::start('record_backfill', 0, 250, 0, ['trigger'=>'plugin_upgrade','from'=>$previousVersion]);
            } else {
                // Fallback legado apenas quando a migração terminou e a infraestrutura em lotes não pôde ser instalada.
                if (class_exists('\RoutesPro\Services\LocationDeduplicator')) {
                    \RoutesPro\Services\LocationDeduplicator::merge_all_groups();
                }
                if (class_exists('\RoutesPro\Forms\RecordService')) {
                    \RoutesPro\Forms\RecordService::backfill_existing_submissions();
                }
            }
        }

        if (version_compare($previousVersion, '2.2.142', '<') && class_exists('\\RoutesPro\\Forms\\RecordService')) {
            \RoutesPro\Forms\RecordService::cleanup_orphaned_versions();
        }

        // Capabilities
        $roles = ['administrator'];
        $caps = ['routespro_manage', 'routespro_execute'];
        foreach ($roles as $r) {
            if ($role = get_role($r)) {
                foreach ($caps as $c) { $role->add_cap($c); }
            }
        }
        if (!get_role('route_agent')) {
            add_role('route_agent', 'Route Agent', [
                'read' => true,
                'routespro_execute' => true
            ]);
        }


        // 2.2.189: normaliza a ordem legada das perguntas do briefing e
        // reconstrói destinatários ativos a partir das atribuições atuais.
        $briefingIntegrityFlag = 'routespro_briefing_integrity_fix_22189';
        if (version_compare($previousVersion, '2.2.189', '<') || !get_option($briefingIntegrityFlag)) {
            $normalisedQuestions = 0;
            $questionTable = $prefix . 'report_context_questions';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $questionTable)) === $questionTable) {
                $logicalRows = $wpdb->get_results(
                    "SELECT briefing_id,question_key,MIN(id) AS first_id,MIN(priority) AS min_priority,MAX(priority) AS max_priority
                     FROM {$questionTable}
                     WHERE (source_type='briefing' OR briefing_id>0) AND is_active=1 AND question_key<>''
                     GROUP BY briefing_id,question_key
                     ORDER BY briefing_id ASC,first_id ASC",
                    ARRAY_A
                ) ?: [];
                $byBriefing = [];
                foreach ($logicalRows as $row) {
                    $briefingId = (int)($row['briefing_id'] ?? 0);
                    if ($briefingId > 0) $byBriefing[$briefingId][] = $row;
                }
                foreach ($byBriefing as $briefingId => $rows) {
                    $allLegacyPriority = count($rows) >= 2;
                    foreach ($rows as $row) {
                        $minPriority = (int)($row['min_priority'] ?? 0);
                        $maxPriority = (int)($row['max_priority'] ?? 0);
                        if ($minPriority !== 25 || $maxPriority !== 25) $allLegacyPriority = false;
                        if ($minPriority === $maxPriority) continue;
                        $questionKey = sanitize_key((string)($row['question_key'] ?? ''));
                        if ($questionKey === '') continue;
                        $updated = $wpdb->query($wpdb->prepare(
                            "UPDATE {$questionTable} SET priority=%d,updated_at=%s WHERE briefing_id=%d AND question_key=%s",
                            max(1, $minPriority),
                            current_time('mysql'),
                            (int)$briefingId,
                            $questionKey
                        ));
                        if ($updated !== false) $normalisedQuestions++;
                    }
                    if (!$allLegacyPriority) continue;
                    $order = 10;
                    foreach ($rows as $row) {
                        $questionKey = sanitize_key((string)($row['question_key'] ?? ''));
                        if ($questionKey === '') continue;
                        $updated = $wpdb->query($wpdb->prepare(
                            "UPDATE {$questionTable} SET priority=%d,updated_at=%s WHERE briefing_id=%d AND question_key=%s",
                            $order,
                            current_time('mysql'),
                            (int)$briefingId,
                            $questionKey
                        ));
                        if ($updated !== false) $normalisedQuestions++;
                        $order += 10;
                    }
                }
            }

            $refreshedBriefings = 0;
            if (class_exists('\\RoutesPro\\Briefings\\BriefingService')) {
                $briefingTable = $prefix . 'briefings';
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $briefingTable)) === $briefingTable) {
                    $projectIds = $wpdb->get_col("SELECT DISTINCT project_id FROM {$briefingTable} WHERE status='published' AND project_id>0") ?: [];
                    foreach (array_values(array_unique(array_map('intval', $projectIds))) as $projectId) {
                        $refreshedBriefings += \RoutesPro\Briefings\BriefingService::refreshActiveRecipientsForProject($projectId);
                    }
                }
            }
            update_option($briefingIntegrityFlag, current_time('mysql'), false);
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::info(
                    'Integridade dos briefings operacionais atualizada.',
                    ['context_key' => 'briefing_integrity'],
                    ['questions' => $normalisedQuestions, 'briefings' => $refreshedBriefings]
                );
            }
        }


        // 2.2.114: corrige o universo dos briefings, restaura utilizadores operacionais
        // com acesso explícito ao projeto e reconstitui o contexto de submissões antigas.
        $scopeRepairFlag = 'routespro_scope_and_submission_repair_22114';
        if ((version_compare($previousVersion, '2.2.114', '<') || !get_option($scopeRepairFlag))) {
            $rebuiltBriefings = 0;
            if (class_exists('\RoutesPro\Briefings\BriefingService')) {
                $briefingTable = $prefix . 'briefings';
                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $briefingTable)) === $briefingTable) {
                    $briefingIds = $wpdb->get_col("SELECT id FROM {$briefingTable} WHERE status='published'") ?: [];
                    foreach ($briefingIds as $briefingId) {
                        \RoutesPro\Briefings\BriefingService::generateRecipients((int)$briefingId);
                        $rebuiltBriefings++;
                    }
                }
            }
            $repairedSubmissions = 0;
            if (class_exists('\RoutesPro\Admin\FormSubmissions')) {
                $repairedSubmissions = \RoutesPro\Admin\FormSubmissions::repair_missing_context(20000);
            }
            update_option($scopeRepairFlag, current_time('mysql'), false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info('Âmbitos de briefing e submissões reparados.', ['context_key' => 'scope_repair'], ['briefings' => $rebuiltBriefings, 'submissions' => $repairedSubmissions]);
            }
        }

        // 2.2.113: reconstrói os destinatários já publicados com o âmbito
        // estrito do projeto e remove contas do cliente das métricas de leitura.
        $recipientRepairFlag = 'routespro_briefing_recipient_scope_fix_22113';
        if ((version_compare($previousVersion, '2.2.113', '<') || !get_option($recipientRepairFlag)) && class_exists('\RoutesPro\Briefings\BriefingService')) {
            $briefingTable = $prefix . 'briefings';
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $briefingTable));
            $rebuilt = 0;
            if ($exists === $briefingTable) {
                $briefingIds = $wpdb->get_col("SELECT id FROM {$briefingTable} WHERE status='published'") ?: [];
                foreach ($briefingIds as $briefingId) {
                    \RoutesPro\Briefings\BriefingService::generateRecipients((int)$briefingId);
                    $rebuilt++;
                }
            }
            update_option($recipientRepairFlag, current_time('mysql'), false);
            if (class_exists('\RoutesPro\Support\Logger')) {
                \RoutesPro\Support\Logger::info('Destinatários dos briefings publicados reconstruídos.', ['context_key' => 'briefing_recipients'], ['briefings' => $rebuilt]);
            }
        }
    }

    private static function migrate(wpdb $wpdb, string $prefix){
        // Helpers
        $hasCol = function(string $table, string $col) use ($wpdb) {
            $row = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $col));
            return !empty($row);
        };
        $colType = function(string $table, string $col) use ($wpdb) {
            $row = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $col), ARRAY_A);
            return $row['Type'] ?? null;
        };
        $table = function(string $name) use ($prefix){ return $prefix.$name; };

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table('categories'))) === $table('categories')) {
            $wpdb->query("UPDATE {$table('categories')} SET name='Makro', slug='makro' WHERE LOWER(name)='macro'");
            $wpdb->query("UPDATE {$table('categories')} SET name='Marabuto', slug='marabuto' WHERE LOWER(name)='matarabuto'");
        }

        // locations.client_id -> permitir NULL (antigos podiam ter NOT NULL)
        if ($hasCol($table('locations'), 'client_id')) {
            $row = $wpdb->get_row("SHOW COLUMNS FROM {$table('locations')} LIKE 'client_id'", ARRAY_A);
            if (!empty($row) && stripos($row['Null'], 'NO') !== false) {
                $wpdb->query("ALTER TABLE {$table('locations')} MODIFY client_id BIGINT UNSIGNED NULL");
            }
        }

        // meta_json/payload_json -> LONGTEXT se estiver JSON
        $jsonCols = [
            ['table'=>'clients',      'col'=>'meta_json'],
            ['table'=>'projects',     'col'=>'meta_json'],
            ['table'=>'locations',    'col'=>'meta_json'],
            ['table'=>'routes',       'col'=>'meta_json'],
            ['table'=>'route_stops',  'col'=>'meta_json'],
            ['table'=>'events',       'col'=>'payload_json'],
        ];
        foreach ($jsonCols as $jc) {
            $t = $table($jc['table']);
            if ($hasCol($t, $jc['col'])) {
                $type = $colType($t, $jc['col']);
                if ($type && stripos($type, 'json') !== false) {
                    $wpdb->query("ALTER TABLE {$t} MODIFY {$jc['col']} LONGTEXT NULL");
                }
            }
        }

        // Índice único em assignments (route_id,user_id) — limpar duplicados antes
        $idx = $wpdb->get_var("SHOW INDEX FROM {$table('assignments')} WHERE Key_name='uniq_route_user'");
        if (!$idx) {
            $dupes = $wpdb->get_results("
                SELECT route_id, user_id, COUNT(*) c
                FROM {$table('assignments')}
                GROUP BY route_id, user_id
                HAVING c > 1
            ");
            if ($dupes) {
                foreach ($dupes as $d) {
                    $ids = $wpdb->get_col($wpdb->prepare("
                        SELECT id FROM {$table('assignments')}
                        WHERE route_id=%d AND user_id=%d
                        ORDER BY id ASC
                    ", $d->route_id, $d->user_id));
                    array_shift($ids);
                    if ($ids) {
                        $in = implode(',', array_map('intval', $ids));
                        $wpdb->query("DELETE FROM {$table('assignments')} WHERE id IN ($in)");
                    }
                }
            }
            $wpdb->query("ALTER TABLE {$table('assignments')} ADD UNIQUE KEY uniq_route_user (route_id, user_id)");
        }

        // Índice extra em locations lat/lng
        $latlngIdx = $wpdb->get_var("SHOW INDEX FROM {$table('locations')} WHERE Key_name='latlng'");
        if (!$latlngIdx) {
            $wpdb->query("ALTER TABLE {$table('locations')} ADD KEY latlng (lat,lng)");
        }

        // Índices que ajudam as queries do BO/stats (se faltarem)
        $routesProjectIdx = $wpdb->get_var("SHOW INDEX FROM {$table('routes')} WHERE Key_name='project'");
        if (!$routesProjectIdx) {
            $wpdb->query("ALTER TABLE {$table('routes')} ADD KEY project (project_id)");
        }
        $eventsCreatedIdx = $wpdb->get_var("SHOW INDEX FROM {$table('events')} WHERE Key_name='created_idx'");
        if (!$eventsCreatedIdx) {
            $wpdb->query("ALTER TABLE {$table('events')} ADD KEY created_idx (created_at)");
        }
        if (!$hasCol($table('assignments'), 'is_active')) {
            $wpdb->query("ALTER TABLE {$table('assignments')} ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role");
        }

        $assignRoleIdx = $wpdb->get_var("SHOW INDEX FROM {$table('assignments')} WHERE Key_name='role_idx'");
        if (!$assignRoleIdx) {
            $wpdb->query("ALTER TABLE {$table('assignments')} ADD KEY role_idx (role)");
        }
        $stopsStatusIdx = $wpdb->get_var("SHOW INDEX FROM {$table('route_stops')} WHERE Key_name='status_idx'");
        if (!$stopsStatusIdx) {
            $wpdb->query("ALTER TABLE {$table('route_stops')} ADD KEY status_idx (status)");
        }

        // ---- Campos recentes em route_stops (compatibilidade com controladores) ----
        $addCol = function(string $name, string $def) use ($wpdb, $table, $hasCol) {
            $t = $table('route_stops');
            if (!$hasCol($t, $name)) {
                $wpdb->query("ALTER TABLE {$t} ADD COLUMN {$def}");
            }
        };
        $addCol('arrived_at',      "arrived_at DATETIME NULL AFTER planned_departure");
        $addCol('departed_at',     "departed_at DATETIME NULL AFTER arrived_at");
        $addCol('duration_s',      "duration_s INT NULL AFTER departed_at");
        $addCol('fail_reason',     "fail_reason VARCHAR(255) NULL AFTER note");
        $addCol('photo_url',       "photo_url TEXT NULL AFTER fail_reason");
        $addCol('signature_data',  "signature_data LONGTEXT NULL AFTER photo_url");
        $addCol('qty',             "qty DOUBLE NULL AFTER signature_data");
        $addCol('weight',          "weight DOUBLE NULL AFTER qty");
        $addCol('volume',          "volume DOUBLE NULL AFTER weight");
        $addCol('real_lat',        "real_lat DECIMAL(10,7) NULL AFTER volume");
        $addCol('real_lng',        "real_lng DECIMAL(10,7) NULL AFTER real_lat");

        // ---- Base comercial v2 ----
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('form_bindings')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            stop_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            mode VARCHAR(32) NOT NULL DEFAULT 'route_and_form',
            priority INT NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY form_idx (form_id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY route_idx (route_id),
            KEY stop_idx (stop_id),
            KEY location_idx (location_id),
            KEY active_idx (is_active),
            KEY priority_idx (priority)
        ) {$wpdb->get_charset_collate()}");

        if (!$hasCol($table('form_submissions'), 'binding_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN binding_id BIGINT UNSIGNED NULL AFTER form_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY binding_idx (binding_id)");
        }
        if (!$hasCol($table('form_submissions'), 'client_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN client_id BIGINT UNSIGNED NULL AFTER binding_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY client_idx (client_id)");
        }
        if (!$hasCol($table('form_submissions'), 'project_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN project_id BIGINT UNSIGNED NULL AFTER client_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY project_idx (project_id)");
        }
        if (!$hasCol($table('form_submissions'), 'route_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN route_id BIGINT UNSIGNED NULL AFTER project_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY route_idx (route_id)");
        }
        if (!$hasCol($table('form_submissions'), 'route_stop_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN route_stop_id BIGINT UNSIGNED NULL AFTER route_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY route_stop_idx (route_stop_id)");
        }
        if (!$hasCol($table('form_submissions'), 'location_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN location_id BIGINT UNSIGNED NULL AFTER route_stop_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY location_idx (location_id)");
        }
        if (!$hasCol($table('form_submissions'), 'report_day')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN report_day DATE NULL AFTER submitted_at");
        }
        $dailyLookupIdx = $wpdb->get_var("SHOW INDEX FROM {$table('form_submissions')} WHERE Key_name='daily_lookup_idx'");
        if (!$dailyLookupIdx) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY daily_lookup_idx (form_id, location_id, report_day)");
        }
        $dailyUniqueIdx = $wpdb->get_var("SHOW INDEX FROM {$table('form_submissions')} WHERE Key_name='uniq_form_location_day'");
        if (!$dailyUniqueIdx) {
            // Registos antigos ficam com report_day NULL. A unicidade passa a ser
            // aplicada apenas aos novos reportes diários, sem apagar histórico.
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD UNIQUE KEY uniq_form_location_day (form_id, location_id, report_day)");
        }

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('form_records')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_key VARCHAR(191) NOT NULL,
            form_id BIGINT UNSIGNED NOT NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            current_version_id BIGINT UNSIGNED NULL,
            current_version_no INT NOT NULL DEFAULT 0,
            first_submission_id BIGINT UNSIGNED NULL,
            latest_submission_id BIGINT UNSIGNED NULL,
            latest_binding_id BIGINT UNSIGNED NULL,
            latest_route_id BIGINT UNSIGNED NULL,
            latest_route_stop_id BIGINT UNSIGNED NULL,
            last_user_id BIGINT UNSIGNED NULL,
            first_submitted_at DATETIME NULL,
            last_submitted_at DATETIME NULL,
            version_count INT NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY record_key_idx (record_key),
            KEY form_idx (form_id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY location_idx (location_id),
            KEY current_version_idx (current_version_id),
            KEY latest_submission_idx (latest_submission_id),
            KEY submitted_idx (last_submitted_at)
        ) {$wpdb->get_charset_collate()}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('form_record_versions')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_id BIGINT UNSIGNED NOT NULL,
            version_no INT NOT NULL,
            submission_id BIGINT UNSIGNED NULL,
            binding_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            route_stop_id BIGINT UNSIGNED NULL,
            client_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            project_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NULL,
            owner_user_id BIGINT UNSIGNED NULL,
            submitted_at DATETIME NOT NULL,
            parent_version_id BIGINT UNSIGNED NULL,
            change_summary_json LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_record_version (record_id, version_no),
            KEY record_idx (record_id),
            KEY submission_idx (submission_id),
            KEY binding_idx (binding_id),
            KEY route_idx (route_id),
            KEY route_stop_idx (route_stop_id),
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY location_idx (location_id),
            KEY owner_idx (owner_user_id),
            KEY submitted_idx (submitted_at)
        ) {$wpdb->get_charset_collate()}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('form_record_values')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            record_id BIGINT UNSIGNED NOT NULL,
            version_id BIGINT UNSIGNED NOT NULL,
            question_key VARCHAR(191) NOT NULL,
            question_label VARCHAR(255) NULL,
            value_type VARCHAR(64) NULL,
            value_text LONGTEXT NULL,
            value_number DOUBLE NULL,
            value_json LONGTEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY record_idx (record_id),
            KEY version_idx (version_id),
            KEY question_idx (question_key),
            KEY record_question_idx (record_id, question_key)
        ) {$wpdb->get_charset_collate()}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('form_analytics_bindings')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form_id BIGINT UNSIGNED NOT NULL,
            question_key VARCHAR(191) NOT NULL,
            metric_key VARCHAR(191) NOT NULL,
            metric_label VARCHAR(255) NOT NULL,
            chart_type VARCHAR(64) NOT NULL DEFAULT 'line',
            aggregation VARCHAR(64) NOT NULL DEFAULT 'latest',
            dimension VARCHAR(64) NOT NULL DEFAULT 'submitted_at',
            scope_mode VARCHAR(64) NOT NULL DEFAULT 'client_project_location',
            settings_json LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_metric (form_id, question_key, metric_key),
            KEY form_idx (form_id),
            KEY question_idx (question_key),
            KEY chart_idx (chart_type),
            KEY agg_idx (aggregation),
            KEY active_idx (is_active)
        ) {$wpdb->get_charset_collate()}");


        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('analytics_dashboards')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            route_id BIGINT UNSIGNED NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            visibility VARCHAR(64) NOT NULL DEFAULT 'client_portal',
            layout_type VARCHAR(64) NOT NULL DEFAULT 'mixed',
            sort_order INT NOT NULL DEFAULT 10,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ai_objective VARCHAR(64) NULL,
            ai_settings_json LONGTEXT NULL,
            ai_last_insights_json LONGTEXT NULL,
            ai_last_generated_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY route_idx (route_id),
            KEY active_idx (is_active),
            KEY sort_idx (sort_order)
        ) {$wpdb->get_charset_collate()}");

        $analyticsDashboards = $table('analytics_dashboards');
        $dashboardColumns = [
            'ai_enabled' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active",
            'ai_objective' => "VARCHAR(64) NULL AFTER ai_enabled",
            'ai_settings_json' => "LONGTEXT NULL AFTER ai_objective",
            'ai_last_insights_json' => "LONGTEXT NULL AFTER ai_settings_json",
            'ai_last_generated_at' => "DATETIME NULL AFTER ai_last_insights_json",
        ];
        foreach ($dashboardColumns as $column => $definition) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$analyticsDashboards} LIKE %s", $column));
            if (!$exists) $wpdb->query("ALTER TABLE {$analyticsDashboards} ADD COLUMN {$column} {$definition}");
        }


        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('analytics_store_groups')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            client_id BIGINT UNSIGNED NULL,
            project_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            group_type VARCHAR(64) NOT NULL DEFAULT 'manual',
            rule_json LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY client_idx (client_id),
            KEY project_idx (project_id),
            KEY active_idx (is_active)
        ) {$wpdb->get_charset_collate()}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('analytics_store_group_items')} (
            group_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (group_id, location_id),
            KEY location_idx (location_id)
        ) {$wpdb->get_charset_collate()}");

        if (!$hasCol($table('form_submissions'), 'record_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN record_id BIGINT UNSIGNED NULL AFTER location_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY record_idx (record_id)");
        }
        if (!$hasCol($table('form_submissions'), 'record_version_id')) {
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD COLUMN record_version_id BIGINT UNSIGNED NULL AFTER record_id");
            $wpdb->query("ALTER TABLE {$table('form_submissions')} ADD KEY record_version_idx (record_version_id)");
        }

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('categories')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            type VARCHAR(64) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY parent_idx (parent_id),
            KEY slug_idx (slug),
            KEY type_idx (type),
            KEY active_idx (is_active)
        ) {$wpdb->get_charset_collate()}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('route_location_snapshot')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            route_id BIGINT UNSIGNED NOT NULL,
            route_stop_id BIGINT UNSIGNED NULL,
            location_id BIGINT UNSIGNED NULL,
            name VARCHAR(255) NOT NULL,
            address TEXT NULL,
            district VARCHAR(120) NULL,
            county VARCHAR(120) NULL,
            city VARCHAR(120) NULL,
            parish VARCHAR(120) NULL,
            postal_code VARCHAR(32) NULL,
            country VARCHAR(80) NULL,
            category_id BIGINT UNSIGNED NULL,
            category_name VARCHAR(255) NULL,
            subcategory_id BIGINT UNSIGNED NULL,
            subcategory_name VARCHAR(255) NULL,
            contact_person VARCHAR(255) NULL,
            phone VARCHAR(64) NULL,
            email VARCHAR(255) NULL,
            website VARCHAR(255) NULL,
            lat DECIMAL(10,7) NULL,
            lng DECIMAL(10,7) NULL,
            place_id VARCHAR(128) NULL,
            source VARCHAR(32) NULL,
            captured_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            meta_json LONGTEXT NULL,
            KEY route_idx (route_id),
            KEY route_stop_idx (route_stop_id),
            KEY location_idx (location_id),
            KEY category_idx (category_id)
        ) {$wpdb->get_charset_collate()}");
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('location_import_batches')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            source VARCHAR(32) NULL DEFAULT 'csv',
            status VARCHAR(32) NOT NULL DEFAULT 'uploaded',
            total_rows INT NOT NULL DEFAULT 0,
            inserted_rows INT NOT NULL DEFAULT 0,
            updated_rows INT NOT NULL DEFAULT 0,
            skipped_rows INT NOT NULL DEFAULT 0,
            error_rows INT NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME NULL,
            summary_json LONGTEXT NULL,
            KEY status_idx (status),
            KEY created_by_idx (created_by)
        ) {$wpdb->get_charset_collate()}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('integration_logs')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            connector VARCHAR(64) NOT NULL,
            action VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'info',
            message TEXT NULL,
            payload_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY connector_idx (connector),
            KEY action_idx (action),
            KEY status_idx (status),
            KEY created_idx (created_at)
        ) {$wpdb->get_charset_collate()}");

        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table('campaign_locations')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            priority INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            assigned_to BIGINT UNSIGNED NULL,
            visit_frequency VARCHAR(32) NULL,
            frequency_count INT NOT NULL DEFAULT 1,
            visit_duration_min INT NOT NULL DEFAULT 45,
            min_gap_days INT NOT NULL DEFAULT 0,
            max_gap_days INT NOT NULL DEFAULT 0,
            preferred_weekdays VARCHAR(32) NULL,
            blocked_weekdays VARCHAR(32) NULL,
            time_window_start VARCHAR(8) NULL,
            time_window_end VARCHAR(8) NULL,
            allow_auto_reschedule TINYINT(1) NOT NULL DEFAULT 1,
            allow_overtime TINYINT(1) NOT NULL DEFAULT 0,
            rule_notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_project_location (project_id, location_id),
            KEY project_idx (project_id),
            KEY location_idx (location_id),
            KEY active_idx (is_active)
        ) {$wpdb->get_charset_collate()}");
        $addLocCol = function(string $name, string $def) use ($wpdb, $table, $hasCol) {
            $t = $table('locations');
            if (!$hasCol($t, $name)) {
                $wpdb->query("ALTER TABLE {$t} ADD COLUMN {$def}");
            }
        };
        $addLocCol('location_type', "location_type VARCHAR(32) NULL DEFAULT 'pdv' AFTER project_id");
        $addLocCol('district', "district VARCHAR(120) NULL AFTER address");
        $addLocCol('county', "county VARCHAR(120) NULL AFTER district");
        $addLocCol('city', "city VARCHAR(120) NULL AFTER county");
        $addLocCol('parish', "parish VARCHAR(120) NULL AFTER city");
        $addLocCol('postal_code', "postal_code VARCHAR(32) NULL AFTER parish");
        $addLocCol('country', "country VARCHAR(80) NULL DEFAULT 'Portugal' AFTER postal_code");
        $addLocCol('category_id', "category_id BIGINT UNSIGNED NULL AFTER country");
        $addLocCol('subcategory_id', "subcategory_id BIGINT UNSIGNED NULL AFTER category_id");
        $addLocCol('contact_person', "contact_person VARCHAR(255) NULL AFTER subcategory_id");
        $addLocCol('phone', "phone VARCHAR(64) NULL AFTER contact_person");
        $addLocCol('email', "email VARCHAR(255) NULL AFTER phone");
        $addLocCol('website', "website VARCHAR(255) NULL AFTER email");
        $addLocCol('place_id', "place_id VARCHAR(128) NULL AFTER website");
        $addLocCol('source', "source VARCHAR(32) NULL DEFAULT 'manual' AFTER place_id");
        $addLocCol('source_confidence', "source_confidence TINYINT UNSIGNED NULL DEFAULT 50 AFTER source");
        $addLocCol('is_active', "is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER source_confidence");
        $addLocCol('is_validated', "is_validated TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
        $addLocCol('last_seen_at', "last_seen_at DATETIME NULL AFTER is_validated");

        // 2.2.395: tipologia persistente da loja separada do master data.
        // A migração é estritamente aditiva e mantém todos os perfis 2.2.392+.
        $profileTable = $table('location_profiles');
        if ((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $profileTable)) === $profileTable) {
            $addProfileCol = function(string $name, string $def) use ($wpdb, $profileTable, $hasCol) {
                if (!$hasCol($profileTable, $name)) $wpdb->query("ALTER TABLE {$profileTable} ADD COLUMN {$def}");
            };
            $addProfileCol('store_format', "store_format VARCHAR(96) NULL AFTER responsible_name");
            $addProfileCol('assortment_profile', "assortment_profile VARCHAR(64) NOT NULL DEFAULT 'general' AFTER store_format");
            $addProfileCol('assortment_profile_source', "assortment_profile_source VARCHAR(32) NOT NULL DEFAULT '' AFTER assortment_profile");
            $addProfileCol('assortment_profile_confidence', "assortment_profile_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER assortment_profile_source");
            if (!$wpdb->get_var("SHOW INDEX FROM {$profileTable} WHERE Key_name='assortment_profile_idx'")) {
                $wpdb->query("ALTER TABLE {$profileTable} ADD KEY assortment_profile_idx (assortment_profile)");
            }
            if (!$wpdb->get_var("SHOW INDEX FROM {$profileTable} WHERE Key_name='store_format_idx'")) {
                $wpdb->query("ALTER TABLE {$profileTable} ADD KEY store_format_idx (store_format)");
            }
        }

        $addCampaignCol = function(string $name, string $def) use ($wpdb, $table, $hasCol) {
            $t = $table('campaign_locations');
            if (!$hasCol($t, $name)) {
                $wpdb->query("ALTER TABLE {$t} ADD COLUMN {$def}");
            }
        };
        $addCampaignCol('frequency_count', "frequency_count INT NOT NULL DEFAULT 1 AFTER visit_frequency");
        $addCampaignCol('visit_duration_min', "visit_duration_min INT NOT NULL DEFAULT 45 AFTER frequency_count");
        $addCampaignCol('min_gap_days', "min_gap_days INT NOT NULL DEFAULT 0 AFTER visit_duration_min");
        $addCampaignCol('max_gap_days', "max_gap_days INT NOT NULL DEFAULT 0 AFTER min_gap_days");
        $addCampaignCol('preferred_weekdays', "preferred_weekdays VARCHAR(32) NULL AFTER max_gap_days");
        $addCampaignCol('blocked_weekdays', "blocked_weekdays VARCHAR(32) NULL AFTER preferred_weekdays");
        $addCampaignCol('time_window_start', "time_window_start VARCHAR(8) NULL AFTER blocked_weekdays");
        $addCampaignCol('time_window_end', "time_window_end VARCHAR(8) NULL AFTER time_window_start");
        $addCampaignCol('allow_auto_reschedule', "allow_auto_reschedule TINYINT(1) NOT NULL DEFAULT 1 AFTER time_window_end");
        $addCampaignCol('allow_overtime', "allow_overtime TINYINT(1) NOT NULL DEFAULT 0 AFTER allow_auto_reschedule");
        $addCampaignCol('rule_notes', "rule_notes TEXT NULL AFTER allow_overtime");

        foreach ([
            ['client_idx', 'client_id'],
            ['project_idx', 'project_id'],
            ['location_type_idx', 'location_type'],
            ['district_idx', 'district'],
            ['county_idx', 'county'],
            ['city_idx', 'city'],
            ['category_idx', 'category_id'],
            ['subcategory_idx', 'subcategory_id'],
            ['place_id_idx', 'place_id'],
            ['email_idx', 'email'],
            ['phone_idx', 'phone'],
            ['validated_idx', 'is_validated'],
            ['active_idx', 'is_active']
        ] as $idxParts) {
            [$idxName, $colName] = $idxParts;
            if (!$wpdb->get_var("SHOW INDEX FROM {$table('locations')} WHERE Key_name='{$idxName}'")) {
                $wpdb->query("ALTER TABLE {$table('locations')} ADD KEY {$idxName} ({$colName})");
            }
        }
        if (!$wpdb->get_var("SHOW INDEX FROM {$table('locations')} WHERE Key_name='commercial_search_idx'")) {
            $wpdb->query("ALTER TABLE {$table('locations')} ADD KEY commercial_search_idx (client_id, project_id, district, county, city, category_id, subcategory_id, is_active)");
        }

        if (!$wpdb->get_var("SHOW INDEX FROM {$table('campaign_locations')} WHERE Key_name='project_location_active_idx'")) {
            $wpdb->query("ALTER TABLE {$table('campaign_locations')} ADD KEY project_location_active_idx (project_id, location_id, is_active)");
        }

        $categorySeeds = [
            ['Horeca', null, 'horeca'],
            ['Retalho', null, 'retalho'],
            ['Saúde', null, 'saude'],
            ['Especializado', null, 'especializado'],
            ['Outros', null, 'outros'],
            ['Restaurante', 'Horeca', 'horeca'],
            ['Café', 'Horeca', 'horeca'],
            ['Bar', 'Horeca', 'horeca'],
            ['Pastelaria', 'Horeca', 'horeca'],
            ['Hotel', 'Horeca', 'horeca'],
            ['Snack-bar', 'Horeca', 'horeca'],
            ['Beach club', 'Horeca', 'horeca'],
            ['Supermercado', 'Retalho', 'retalho'],
            ['Minimercado', 'Retalho', 'retalho'],
            ['Conveniência', 'Retalho', 'retalho'],
            ['Quiosque', 'Retalho', 'retalho'],
            ['Papelaria', 'Retalho', 'retalho'],
            ['Garrafeira', 'Retalho', 'retalho'],
            ['Loja gourmet', 'Retalho', 'retalho'],
            ['Tabacaria', 'Retalho', 'retalho'],
            ['Hipermercados', null, 'retalho'],
            ['Lojas tecnologia', null, 'retalho'],
            ['Cash & Carry', null, 'retalho'],
            ['Continente', 'Hipermercados', 'retalho'],
            ['Continente Bom Dia', 'Hipermercados', 'retalho'],
            ['Pingo Doce', 'Hipermercados', 'retalho'],
            ['Auchan', 'Hipermercados', 'retalho'],
            ['Mercadona', 'Hipermercados', 'retalho'],
            ['E.Leclerc', 'Hipermercados', 'retalho'],
            ['Minipreço', 'Hipermercados', 'retalho'],
            ['Lidl', 'Hipermercados', 'retalho'],
            ['Aldi', 'Hipermercados', 'retalho'],
            ['Intermarché', 'Hipermercados', 'retalho'],
            ['Continente Modelo', 'Hipermercados', 'retalho'],
            ['Super / Hiper Poupança', 'Hipermercados', 'retalho'],
            ['Worten', 'Lojas tecnologia', 'retalho'],
            ['FNAC', 'Lojas tecnologia', 'retalho'],
            ['Radio Popular', 'Lojas tecnologia', 'retalho'],
            ['Staples', 'Lojas tecnologia', 'retalho'],
            ['Darty', 'Lojas tecnologia', 'retalho'],
            ['Makro', 'Cash & Carry', 'retalho'],
            ['Recheio', 'Cash & Carry', 'retalho'],
            ['Mcunha', 'Cash & Carry', 'retalho'],
            ['Marabuto', 'Cash & Carry', 'retalho'],
            ['Malaquias', 'Cash & Carry', 'retalho'],
            ['Grossão', 'Cash & Carry', 'retalho'],
            ['Nortenho', 'Cash & Carry', 'retalho'],
            ['Pereira e Santos', 'Cash & Carry', 'retalho'],
            ['A. Ezequiel', 'Cash & Carry', 'retalho'],
            ['Garcias', 'Cash & Carry', 'retalho'],
            ['Arcol', 'Cash & Carry', 'retalho'],
            ['Farmácia', 'Saúde', 'saude'],
            ['Parafarmácia', 'Saúde', 'saude'],
            ['Posto de combustível', 'Especializado', 'especializado'],
            ['Estação de serviço', 'Especializado', 'especializado'],
            ['Ginásio', 'Especializado', 'especializado'],
            ['Clube', 'Especializado', 'especializado'],
            ['Vending', 'Especializado', 'especializado'],
            ['Banca', 'Outros', 'outros'],
            ['Mobile kiosk', 'Outros', 'outros'],
        ];
        $rootIds = [];
        foreach ($categorySeeds as $seed) {
            [$name, $parentName, $type] = $seed;
            $slug = sanitize_title($name);
            $parentId = null;
            if ($parentName !== null) {
                if (!isset($rootIds[$parentName])) {
                    $rootIds[$parentName] = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table('categories')} WHERE parent_id IS NULL AND slug=%s LIMIT 1", sanitize_title($parentName)));
                }
                $parentId = $rootIds[$parentName] ?: null;
            }
            $existingId = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table('categories')} WHERE slug=%s AND " . ($parentId ? 'parent_id=%d' : 'parent_id IS NULL') . " LIMIT 1", ...array_filter([$slug, $parentId], fn($v)=>$v!==null)));
            if ($existingId) {
                $wpdb->update($table('categories'), ['name'=>$name, 'type'=>$type, 'is_active'=>1], ['id'=>$existingId]);
                if ($parentName === null) $rootIds[$name] = $existingId;
            } else {
                $wpdb->insert($table('categories'), ['parent_id'=>$parentId, 'name'=>$name, 'slug'=>$slug, 'type'=>$type, 'is_active'=>1]);
                if ($parentName === null) $rootIds[$name] = (int)$wpdb->insert_id;
            }
        }

        if (class_exists('\\RoutesPro\\Forms\\ProductCardex')) {
            \RoutesPro\Forms\ProductCardex::install();
        }


        // 2.2.124: perguntas do briefing passam a usar uma chave comum entre lojas,
        // para reporting e exportações agregadas. Chaves com respostas existentes
        // são preservadas para não quebrar histórico.
        if (version_compare($previousVersion, '2.2.124', '<') && class_exists('\RoutesPro\Forms\ContextQuestions')) {
            if (class_exists('\RoutesPro\Admin\ContextQuestions')) \RoutesPro\Admin\ContextQuestions::ensure_table();
            $questionTable = $prefix . 'report_context_questions';
            $answerTable = $prefix . 'form_submission_answers';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $questionTable)) === $questionTable) {
                $briefingRows = $wpdb->get_results("SELECT * FROM {$questionTable} WHERE source_type='briefing' OR briefing_id>0 ORDER BY briefing_id,form_id,briefing_section_id,id", ARRAY_A) ?: [];
                $groups = [];
                foreach ($briefingRows as $row) {
                    $briefingId = (int)($row['briefing_id'] ?? 0);
                    if (!$briefingId) continue;
                    $groupKey = implode('|', [$briefingId, (int)($row['briefing_section_id'] ?? 0), (int)($row['form_id'] ?? 0), sanitize_key((string)($row['question_label'] ?? ''))]);
                    $groups[$groupKey][] = $row;
                }
                foreach ($groups as $groupRows) {
                    $first = $groupRows[0] ?? [];
                    $newKey = \RoutesPro\Forms\ContextQuestions::make_briefing_question_key(
                        (int)($first['briefing_id'] ?? 0),
                        (int)($first['briefing_section_id'] ?? 0),
                        (string)($first['question_label'] ?? ''),
                        (int)($first['form_id'] ?? 0)
                    );
                    $oldKeys = array_values(array_unique(array_filter(array_map(static fn($row) => sanitize_key((string)($row['question_key'] ?? '')), $groupRows))));
                    $hasAnswers = false;
                    if ($oldKeys && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $answerTable)) === $answerTable) {
                        $ph = implode(',', array_fill(0, count($oldKeys), '%s'));
                        $hasAnswers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$answerTable} WHERE question_key IN ({$ph})", ...$oldKeys)) > 0;
                    }
                    if (!$hasAnswers && $newKey) {
                        foreach ($groupRows as $row) $wpdb->update($questionTable, ['question_key' => $newKey, 'updated_at' => current_time('mysql')], ['id' => (int)$row['id']]);
                    }
                }

                // Repara regras globais criadas na 2.2.123, clonando-as apenas para lojas mapeadas.
                $globalRows = $wpdb->get_results("SELECT * FROM {$questionTable} WHERE (source_type='briefing' OR briefing_id>0) AND location_id=0", ARRAY_A) ?: [];
                foreach ($globalRows as $row) {
                    $briefingId = (int)($row['briefing_id'] ?? 0);
                    if (!$briefingId) continue;
                    $itemTable = $prefix . 'briefing_store_items';
                    $mappedIds = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT location_id FROM {$itemTable} WHERE briefing_id=%d AND location_id>0", $briefingId)) ?: [];
                    foreach (array_values(array_unique(array_map('intval', $mappedIds))) as $locationId) {
                        if ($locationId <= 0) continue;
                        $clone = $row;
                        unset($clone['id']);
                        $clone['location_id'] = $locationId;
                        $clone['campaign_location_id'] = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}campaign_locations WHERE project_id=%d AND location_id=%d LIMIT 1", (int)($row['project_id'] ?? 0), $locationId));
                        $clone['question_key'] = \RoutesPro\Forms\ContextQuestions::make_briefing_question_key($briefingId, (int)($row['briefing_section_id'] ?? 0), (string)($row['question_label'] ?? ''), (int)($row['form_id'] ?? 0));
                        $clone['created_at'] = current_time('mysql');
                        $clone['updated_at'] = current_time('mysql');
                        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$questionTable} WHERE briefing_id=%d AND location_id=%d AND form_id=%d AND question_key=%s LIMIT 1", $briefingId, $locationId, (int)($row['form_id'] ?? 0), $clone['question_key']));
                        if (!$exists) $wpdb->insert($questionTable, $clone);
                    }
                    $oldKey = sanitize_key((string)($row['question_key'] ?? ''));
                    $hasOldAnswers = false;
                    if ($oldKey && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $answerTable)) === $answerTable) {
                        $hasOldAnswers = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$answerTable} WHERE question_key=%s", $oldKey)) > 0;
                    }
                    if ($hasOldAnswers) $wpdb->update($questionTable, ['is_active' => 0, 'updated_at' => current_time('mysql')], ['id' => (int)$row['id']]);
                    else $wpdb->delete($questionTable, ['id' => (int)$row['id']]);
                }
            }
        }


        if (version_compare($previousVersion, '2.2.120', '<') && class_exists('\RoutesPro\Briefings\BriefingService')) {
            $briefingIds = $wpdb->get_col("SELECT id FROM {$table('briefings')} WHERE status='published'") ?: [];
            foreach ($briefingIds as $briefingId) {
                \RoutesPro\Briefings\BriefingService::generateRecipients((int)$briefingId);
                if (class_exists('\RoutesPro\Briefings\BriefingAIService')) {
                    \RoutesPro\Briefings\BriefingAIService::markStale((int)$briefingId, 'store_scope_engine_2_2_120');
                }
            }
        }

        if (class_exists('\RoutesPro\Shopping\ShoppingService')) {
            \RoutesPro\Shopping\ShoppingService::ensureSchema(true);
        }

    }
}
