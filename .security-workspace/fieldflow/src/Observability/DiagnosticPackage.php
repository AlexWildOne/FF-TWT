<?php
namespace RoutesPro\Observability;

use RoutesPro\Security\SecurityService;
use RoutesPro\Support\SystemHealth;

if (!defined('ABSPATH')) exit;

class DiagnosticPackage {
    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_generate_diagnostic_package',[self::class,'download']);
    }

    public static function download(): void {
        if(!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_diagnostic_package');
        if(!class_exists('ZipArchive')) wp_die('A extensão ZipArchive não está disponível neste servidor.');
        $uploads=wp_upload_dir();
        $dir=trailingslashit((string)$uploads['basedir']).'fieldflow-diagnostics';
        wp_mkdir_p($dir);
        $stamp=gmdate('Ymd-His');
        $zipPath=$dir.'/fieldflow-diagnostic-'.$stamp.'.zip';
        $zip=new \ZipArchive();
        if($zip->open($zipPath,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true) wp_die('Não foi possível criar o pacote de diagnóstico.');
        $data=self::build();
        foreach($data as $name=>$value){
            $zip->addFromString($name,is_string($value)?$value:wp_json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        }
        $zip->close();
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($zipPath).'"');
        header('Content-Length: '.filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    public static function build(): array {
        global $wpdb,$wp_version;
        $cron=[];
        foreach((_get_cron_array()?:[]) as $timestamp=>$hooks){
            foreach((array)$hooks as $hook=>$instances) $cron[]=['hook'=>$hook,'next_run_gmt'=>gmdate('c',(int)$timestamp),'instances'=>is_array($instances)?count($instances):1,'overdue'=>(int)$timestamp<time()-300];
            if(count($cron)>=250) break;
        }
        $plugins=get_option('active_plugins',[]);
        $active=[];
        foreach((array)$plugins as $plugin) $active[]=sanitize_text_field((string)$plugin);
        $health=SystemHealth::status();
        $health['constants']=array_map(static fn($v)=>$v==='wp-config.php'?'configured_in_wp_config':'database_or_missing',(array)($health['constants']??[]));
        return [
            'README.txt'=>"FieldFlow diagnostic package\nGenerated: ".gmdate('c')."\nNo passwords, tokens or API keys are included.\n",
            'environment.json'=>[
                'fieldflow_version'=>defined('FIELDFLOW_VERSION')?FIELDFLOW_VERSION:'',
                'wordpress_version'=>$wp_version,
                'php_version'=>PHP_VERSION,
                'database_version'=>$wpdb->db_version(),
                'site_host'=>wp_parse_url(home_url('/'),PHP_URL_HOST),
                'is_multisite'=>is_multisite(),
                'memory_limit'=>ini_get('memory_limit'),
                'max_upload_size'=>wp_max_upload_size(),
                'timezone'=>wp_timezone_string(),
            ],
            'health.json'=>$health,
            'observability.json'=>['summary'=>Monitor::summary(),'recent'=>Monitor::recent(100)],
            'security.json'=>['counts'=>SecurityService::counts(),'recent'=>SecurityService::recent(100)],
            'alerts.json'=>['counts'=>AlertService::counts(),'recent'=>AlertService::recent(100,'open')],
            'devices.json'=>class_exists('RoutesPro\\Mobile\\DeviceManager')?\RoutesPro\Mobile\DeviceManager::diagnosticSnapshot():[],
            'mobile-contract.json'=>class_exists('RoutesPro\Mobile\ContractRegistry')?[
                'summary'=>\RoutesPro\Mobile\ContractRegistry::summary(),
                'manifest'=>\RoutesPro\Mobile\ContractRegistry::manifest(),
            ]:[],
            'stability-flags.json'=>class_exists('RoutesPro\Support\StabilityFlags')?\RoutesPro\Support\StabilityFlags::publicState():[],
            'forms-runtime-v3.json'=>class_exists('RoutesPro\Forms\RuntimeV3')?\RoutesPro\Forms\RuntimeV3::publicState():[],
            'process-recovery.json'=>class_exists('RoutesPro\Operations\ProcessMonitor')?\RoutesPro\Operations\ProcessMonitor::diagnosticSnapshot():[],
            'queue-modernization.json'=>class_exists('RoutesPro\Operations\Queue\QueueManager')?\RoutesPro\Operations\Queue\QueueManager::diagnosticSnapshot():[],
            'architecture-runtime.json'=>class_exists('RoutesPro\Architecture\RuntimeRegistry')?\RoutesPro\Architecture\RuntimeRegistry::status():[],
            'schema-integrity.json'=>class_exists('RoutesPro\Support\SchemaIntegrity')?[
                'latest'=>\RoutesPro\Support\SchemaIntegrity::latest(),
                'quick'=>get_option('fieldflow_schema_integrity_quick_report', []),
            ]:[],
            'migration-ledger.json'=>class_exists('RoutesPro\Support\MigrationLedger')?[
                'status'=>\RoutesPro\Support\MigrationLedger::status(),
                'recent'=>\RoutesPro\Support\MigrationLedger::recent(50),
                'stale_running'=>\RoutesPro\Support\MigrationLedger::staleRunning(),
            ]:[],
            'migration-batches.json'=>class_exists('RoutesPro\Support\BatchMigrationManager')?[
                'status'=>\RoutesPro\Support\BatchMigrationManager::status(),
                'recent'=>\RoutesPro\Support\BatchMigrationManager::recent(50),
            ]:[],
            'release-gate.json'=>class_exists('RoutesPro\Support\ReleaseGate')?[
                'latest'=>\RoutesPro\Support\ReleaseGate::latest(),
                'history'=>\RoutesPro\Support\ReleaseGate::history(),
            ]:[],
            'performance-readiness.json'=>class_exists('RoutesPro\Performance\LoadReadiness')?[
                'status'=>\RoutesPro\Performance\LoadReadiness::status(),
                'gate'=>\RoutesPro\Performance\LoadReadiness::gate(),
            ]:[],
            'cron.json'=>$cron,
            'active_plugins.json'=>$active,
            'tables.json'=>self::tableSizes(),
        ];
    }

    private static function tableSizes(): array {
        global $wpdb;
        $like=$wpdb->esc_like($wpdb->prefix.'routespro_').'%';
        $rows=$wpdb->get_results($wpdb->prepare("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s ORDER BY DATA_LENGTH+INDEX_LENGTH DESC",$like),ARRAY_A)?:[];
        return array_map(static function($row){return ['table'=>preg_replace('/^'.preg_quote($GLOBALS['wpdb']->prefix,'/').'/','',(string)$row['TABLE_NAME']),'rows'=>(int)$row['TABLE_ROWS'],'data_bytes'=>(int)$row['DATA_LENGTH'],'index_bytes'=>(int)$row['INDEX_LENGTH']];},$rows);
    }
}
