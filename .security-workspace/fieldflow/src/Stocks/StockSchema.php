<?php
namespace RoutesPro\Stocks;

if (!defined('ABSPATH')) exit;

class StockSchema {
    public const VERSION = '2.2.217';

    public static function tableNames(): array {
        global $wpdb;
        $prefix = $wpdb->prefix . 'routespro_';
        return [
            $prefix . 'stock_batches',
            $prefix . 'stock_items',
            $prefix . 'stock_store_mappings',
            $prefix . 'stock_views',
        ];
    }

    public static function missingTables(): array {
        global $wpdb;
        $missing = [];
        foreach (self::tableNames() as $table) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($exists !== $table) $missing[] = $table;
        }
        return $missing;
    }

    public static function ensureOnBootstrap(): void {
        self::ensure(false);
    }

    public static function ensureOrThrow(): void {
        if (self::ensure(false)) return;
        $missing = self::missingTables();
        $detail = $missing ? implode(', ', $missing) : 'unknown database error';
        throw new \RuntimeException('Stock database schema is unavailable: ' . $detail);
    }

    public static function ensure(bool $force = false): bool {
        global $wpdb;
        $installed = (string)get_option('routespro_stock_schema_version', '0.0.0');
        $missing = self::missingTables();
        if (!$force && !$missing && version_compare($installed, self::VERSION, '>=')) return true;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'routespro_';
        $sql = self::schemaSql($prefix, $charset);

        foreach ($sql as $statement) dbDelta($statement);

        $missing = self::missingTables();
        if ($missing) {
            foreach ($sql as $table => $statement) {
                if (!in_array($table, $missing, true)) continue;
                $fallback = preg_replace('/^CREATE TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $statement);
                $wpdb->query($fallback);
            }
            $missing = self::missingTables();
        }

        if ($missing) {
            if (class_exists('\\RoutesPro\\Support\\Logger')) {
                \RoutesPro\Support\Logger::error('Stock schema repair failed.', ['context_key'=>'stock_schema'], ['missing_tables'=>$missing, 'database_error'=>$wpdb->last_error]);
            }
            return false;
        }

        update_option('routespro_stock_schema_version', self::VERSION, false);
        if (class_exists('\\RoutesPro\\Support\\Logger')) {
            \RoutesPro\Support\Logger::info('Stock schema validated.', ['context_key'=>'stock_schema'], ['version'=>self::VERSION]);
        }
        return true;
    }

    private static function schemaSql(string $prefix, string $charset): array {
        $tables = [];
        $table = $prefix . 'stock_batches';
        $tables[$table] = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned NOT NULL,
            insignia varchar(191) NOT NULL,
            snapshot_date date NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'draft',
            source_filename varchar(255) DEFAULT NULL,
            sheet_name varchar(191) DEFAULT NULL,
            header_row int(11) NOT NULL DEFAULT 1,
            row_count int(11) NOT NULL DEFAULT 0,
            store_count int(11) NOT NULL DEFAULT 0,
            product_count int(11) NOT NULL DEFAULT 0,
            matched_store_count int(11) NOT NULL DEFAULT 0,
            unmatched_row_count int(11) NOT NULL DEFAULT 0,
            total_stock decimal(18,3) NOT NULL DEFAULT 0,
            zero_count int(11) NOT NULL DEFAULT 0,
            low_count int(11) NOT NULL DEFAULT 0,
            negative_count int(11) NOT NULL DEFAULT 0,
            import_hash char(64) NOT NULL,
            mapping_json longtext DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            published_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY import_hash_idx (import_hash),
            KEY scope_idx (client_id,project_id),
            KEY snapshot_idx (snapshot_date),
            KEY status_idx (status),
            KEY insignia_idx (insignia)
        ) {$charset};";

        $table = $prefix . 'stock_items';
        $tables[$table] = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            client_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned NOT NULL,
            location_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source_store_key char(40) NOT NULL,
            store_external_ref varchar(128) DEFAULT NULL,
            store_name varchar(255) NOT NULL,
            locality varchar(191) DEFAULT NULL,
            article_ref varchar(128) DEFAULT NULL,
            ean varchar(32) DEFAULT NULL,
            product_name varchar(255) NOT NULL,
            unit varchar(32) DEFAULT NULL,
            conversion_factor decimal(14,4) NOT NULL DEFAULT 1,
            stock_total decimal(18,3) NOT NULL DEFAULT 0,
            match_confidence tinyint(3) unsigned NOT NULL DEFAULT 0,
            match_method varchar(64) DEFAULT NULL,
            cardex_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
            product_match_confidence tinyint(3) unsigned NOT NULL DEFAULT 0,
            product_match_method varchar(64) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY batch_idx (batch_id),
            KEY scope_idx (client_id,project_id),
            KEY location_idx (location_id),
            KEY store_key_idx (source_store_key),
            KEY ean_idx (ean),
            KEY article_idx (article_ref),
            KEY cardex_item_idx (cardex_item_id),
            KEY stock_idx (stock_total)
        ) {$charset};";

        $table = $prefix . 'stock_store_mappings';
        $tables[$table] = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id bigint(20) unsigned NOT NULL,
            project_id bigint(20) unsigned NOT NULL,
            insignia varchar(191) NOT NULL,
            source_store_key char(40) NOT NULL,
            source_store_ref varchar(128) DEFAULT NULL,
            source_store_name varchar(255) DEFAULT NULL,
            location_id bigint(20) unsigned NOT NULL,
            confidence tinyint(3) unsigned NOT NULL DEFAULT 100,
            match_method varchar(64) NOT NULL DEFAULT 'manual',
            updated_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_scope_store (client_id,project_id,insignia,source_store_key),
            KEY location_idx (location_id),
            KEY project_idx (project_id)
        ) {$charset};";

        $table = $prefix . 'stock_views';
        $tables[$table] = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            batch_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            first_viewed_at datetime NOT NULL,
            last_viewed_at datetime NOT NULL,
            view_count int(11) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_batch_user (batch_id,user_id),
            KEY batch_idx (batch_id),
            KEY user_idx (user_id),
            KEY viewed_idx (last_viewed_at)
        ) {$charset};";
        return $tables;
    }
}
