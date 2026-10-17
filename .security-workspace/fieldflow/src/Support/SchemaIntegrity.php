<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Read-only schema drift detector. It never executes ALTER, CREATE or DELETE.
 */
final class SchemaIntegrity {
    public const VERSION = '1.0.0';
    public const REPORT_OPTION = 'fieldflow_schema_integrity_last_report';
    public const QUICK_REPORT_OPTION = 'fieldflow_schema_integrity_quick_report';
    private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    public static function audit(string $mode = 'full', bool $store = true): array {
        $mode = $mode === 'quick' ? 'quick' : 'full';
        $suffixes = $mode === 'quick' ? SchemaManifest::criticalSuffixes() : array_keys(SchemaManifest::tables());
        $started = microtime(true);
        $report = self::auditSuffixes($suffixes, $mode);
        $report['duration_ms'] = max(0, (int)round((microtime(true) - $started) * 1000));
        $report['generated_at'] = current_time('mysql', true);
        $report['plugin_version'] = defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : '';
        $report['manifest_version'] = SchemaManifest::VERSION;
        $report['audit_version'] = self::VERSION;
        if ($store) {
            update_option($mode === 'quick' ? self::QUICK_REPORT_OPTION : self::REPORT_OPTION, $report, false);
        }
        return $report;
    }

    public static function auditSuffixes(array $suffixes, string $mode = 'batch'): array {
        global $wpdb;
        $manifest = SchemaManifest::tables();
        $tables = [];
        $counts = [
            'checked' => 0,
            'healthy' => 0,
            'warning' => 0,
            'critical' => 0,
            'missing_tables' => 0,
            'missing_columns' => 0,
            'type_mismatches' => 0,
            'nullability_mismatches' => 0,
            'missing_indexes' => 0,
            'index_mismatches' => 0,
        ];

        foreach (array_values(array_unique(array_map('sanitize_key', $suffixes))) as $suffix) {
            if ($suffix === '' || !isset($manifest[$suffix])) continue;
            $definition = $manifest[$suffix];
            $tableName = $wpdb->prefix . 'routespro_' . $suffix;
            $result = self::auditTable($tableName, $suffix, $definition);
            $tables[$suffix] = $result;
            $counts['checked']++;
            $counts[$result['status']]++;
            foreach (['missing_tables','missing_columns','type_mismatches','nullability_mismatches','missing_indexes','index_mismatches'] as $key) {
                $counts[$key] += (int)($result['counts'][$key] ?? 0);
            }
        }

        $status = $counts['critical'] > 0 ? 'critical' : ($counts['warning'] > 0 ? 'warning' : 'healthy');
        return [
            'mode' => sanitize_key($mode),
            'status' => $status,
            'summary' => self::summaryText($status, $counts),
            'counts' => $counts,
            'tables' => $tables,
        ];
    }

    public static function latest(bool $full = true): array {
        $report = get_option($full ? self::REPORT_OPTION : self::QUICK_REPORT_OPTION, []);
        return is_array($report) ? $report : [];
    }

    public static function latestOrQuick(int $maxAgeSeconds = DAY_IN_SECONDS): array {
        $full = self::latest(true);
        $ts = !empty($full['generated_at']) ? strtotime((string)$full['generated_at'] . ' UTC') : 0;
        if ($ts > 0 && $ts >= time() - max(60, $maxAgeSeconds)) return $full;
        return self::audit('quick', true);
    }

    public static function status(): array {
        $report = self::latest(true);
        if (!$report) $report = self::latest(false);
        if (!$report) {
            return [
                'status' => 'unknown',
                'summary' => 'Ainda não foi executada uma auditoria do schema.',
                'counts' => ['checked'=>0,'healthy'=>0,'warning'=>0,'critical'=>0],
                'generated_at' => '',
                'mode' => 'none',
            ];
        }
        return $report;
    }

    private static function auditTable(string $tableName, string $suffix, array $definition): array {
        global $wpdb;
        $required = !empty($definition['required']);
        $exists = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName)) === $tableName;
        $issues = [];
        $counts = [
            'missing_tables'=>0,
            'missing_columns'=>0,
            'type_mismatches'=>0,
            'nullability_mismatches'=>0,
            'missing_indexes'=>0,
            'index_mismatches'=>0,
        ];
        if (!$exists) {
            $counts['missing_tables'] = 1;
            $issues[] = [
                'code'=>'table_missing',
                'severity'=>$required ? 'critical' : 'warning',
                'message'=>'Tabela em falta.',
            ];
            return [
                'suffix'=>$suffix,
                'table'=>$tableName,
                'required'=>$required,
                'exists'=>false,
                'status'=>$required ? 'critical' : 'warning',
                'counts'=>$counts,
                'issues'=>$issues,
                'columns_checked'=>0,
                'indexes_checked'=>0,
                'collation'=>'',
            ];
        }

        $actualColumnsRows = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$tableName}`", ARRAY_A) ?: [];
        $actualColumns = [];
        foreach ($actualColumnsRows as $row) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') $actualColumns[$field] = $row;
        }

        foreach ((array)($definition['columns'] ?? []) as $column => $expected) {
            if (!isset($actualColumns[$column])) {
                $counts['missing_columns']++;
                $issues[] = ['code'=>'column_missing','severity'=>'critical','column'=>$column,'message'=>'Coluna em falta: ' . $column . '.'];
                continue;
            }
            $actual = $actualColumns[$column];
            $expectedType = self::normaliseType((string)($expected['type'] ?? ''));
            $actualType = self::normaliseType((string)($actual['Type'] ?? ''));
            if ($expectedType !== '' && $actualType !== '' && $expectedType !== $actualType) {
                $counts['type_mismatches']++;
                $issues[] = [
                    'code'=>'column_type_mismatch','severity'=>'warning','column'=>$column,
                    'message'=>'Tipo divergente em ' . $column . ': esperado ' . $expectedType . ', encontrado ' . $actualType . '.',
                ];
            }
            $expectedNullable = !empty($expected['nullable']);
            $actualNullable = strtoupper((string)($actual['Null'] ?? 'NO')) === 'YES';
            if ($expectedNullable !== $actualNullable) {
                $counts['nullability_mismatches']++;
                $issues[] = [
                    'code'=>'column_nullability_mismatch','severity'=>'warning','column'=>$column,
                    'message'=>'Nulabilidade divergente em ' . $column . '.',
                ];
            }
            if (!empty($expected['auto_increment']) && stripos((string)($actual['Extra'] ?? ''), 'auto_increment') === false) {
                $issues[] = [
                    'code'=>'auto_increment_missing','severity'=>'critical','column'=>$column,
                    'message'=>'AUTO_INCREMENT em falta na coluna ' . $column . '.',
                ];
            }
        }

        $actualIndexRows = $wpdb->get_results("SHOW INDEX FROM `{$tableName}`", ARRAY_A) ?: [];
        $actualIndexes = [];
        foreach ($actualIndexRows as $row) {
            $name = (string)($row['Key_name'] ?? '');
            if ($name === '') continue;
            $seq = max(1, (int)($row['Seq_in_index'] ?? 1));
            $actualIndexes[$name]['unique'] = (int)($row['Non_unique'] ?? 1) === 0;
            $actualIndexes[$name]['columns'][$seq] = (string)($row['Column_name'] ?? '');
        }
        foreach ($actualIndexes as &$index) {
            if (!empty($index['columns'])) {
                ksort($index['columns']);
                $index['columns'] = array_values($index['columns']);
            }
        }
        unset($index);

        foreach ((array)($definition['indexes'] ?? []) as $indexName => $expectedIndex) {
            if (!isset($actualIndexes[$indexName])) {
                $counts['missing_indexes']++;
                $issues[] = ['code'=>'index_missing','severity'=>'warning','index'=>$indexName,'message'=>'Índice em falta: ' . $indexName . '.'];
                continue;
            }
            $expectedColumns = array_values((array)($expectedIndex['columns'] ?? []));
            $actualColumnsList = array_values((array)($actualIndexes[$indexName]['columns'] ?? []));
            $expectedUnique = !empty($expectedIndex['unique']);
            $actualUnique = !empty($actualIndexes[$indexName]['unique']);
            if ($expectedColumns !== $actualColumnsList || $expectedUnique !== $actualUnique) {
                $counts['index_mismatches']++;
                $issues[] = ['code'=>'index_mismatch','severity'=>'warning','index'=>$indexName,'message'=>'Definição divergente no índice ' . $indexName . '.'];
            }
        }

        $criticalIssues = 0;
        $warningIssues = 0;
        foreach ($issues as $issue) {
            if (($issue['severity'] ?? '') === 'critical') $criticalIssues++;
            else $warningIssues++;
        }
        $status = $criticalIssues > 0 ? 'critical' : ($warningIssues > 0 ? 'warning' : 'healthy');
        $collation = '';
        if ($actualColumnsRows) $collation = (string)($actualColumnsRows[0]['Collation'] ?? '');

        return [
            'suffix'=>$suffix,
            'table'=>$tableName,
            'required'=>$required,
            'exists'=>true,
            'status'=>$status,
            'counts'=>$counts,
            'issues'=>$issues,
            'columns_checked'=>count((array)($definition['columns'] ?? [])),
            'indexes_checked'=>count((array)($definition['indexes'] ?? [])),
            'collation'=>$collation,
        ];
    }

    private static function normaliseType(string $type): string {
        $type = strtolower(trim(preg_replace('/\s+/', ' ', $type)));
        $type = preg_replace('/\b(tinyint|smallint|mediumint|int|integer|bigint)\(\d+\)/', '$1', $type);
        $type = str_replace('integer', 'int', $type);
        $type = preg_replace('/\s+/', ' ', (string)$type);
        return trim((string)$type);
    }

    private static function summaryText(string $status, array $counts): string {
        if ($status === 'healthy') return 'Schema saudável nas tabelas verificadas.';
        if ($status === 'critical') {
            return 'Foram encontradas divergências críticas em ' . (int)$counts['critical'] . ' tabelas.';
        }
        return 'Foram encontrados avisos de schema em ' . (int)$counts['warning'] . ' tabelas.';
    }
}
