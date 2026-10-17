<?php
/** 9DB: deterministic recovery, SHA, tenant isolation and retry control. No Azure calls. */
namespace {
    define('ABSPATH', '/var/www/fieldflow-test/public/');
    define('WP_CONTENT_DIR', '/var/www/fieldflow-test/public/wp-content/');
    define('ROUTESPRO_PATH', dirname(__DIR__) . '/');
    $pluginSource = (string)file_get_contents(dirname(__DIR__) . '/fieldflow.php');
    if (!preg_match("/define\('FIELDFLOW_VERSION', '([^']+)'\)/", $pluginSource, $match)) exit(1);
    define('FIELDFLOW_VERSION', $match[1]);
    $parts = explode('.', FIELDFLOW_VERSION);
    define('FF_NEXT_VERSION', $parts[0] . '.' . $parts[1] . '.' . ((int)$parts[2] + 1));
    $GLOBALS['ff_options'] = [];
    $GLOBALS['ff_admin'] = 7;
    $GLOBALS['ff_allowed'] = true;
    $GLOBALS['ff_remote'] = [];
    $GLOBALS['ff_uploads'] = 0;
    $GLOBALS['ff_catalog_calls'] = 0;
    $GLOBALS['ff_receipts'] = [];
    $GLOBALS['ff_audit_calls'] = 0;
    $GLOBALS['ff_catalog_ok'] = true;
    $GLOBALS['ff_fail_upload'] = false;
    $GLOBALS['ff_inspect_bad'] = false;
    function wp_salt($scheme='auth') { return '9db-private-test-secret'; }
    function current_user_can($key) { return (bool)$GLOBALS['ff_allowed']; }
    function get_current_user_id() { return $GLOBALS['ff_admin']; }
    function get_current_blog_id() { return 1; }
    function get_option($key, $default=[]) { return $GLOBALS['ff_options'][$key] ?? $default; }
    function update_option($key, $value, $autoload=false) { $GLOBALS['ff_options'][$key]=$value; return true; }
    function delete_option($key) { unset($GLOBALS['ff_options'][$key]); return true; }
    function sanitize_text_field($s) { return trim(strip_tags((string)$s)); }
    function wp_strip_all_tags($s) { return strip_tags((string)$s); }
    function sanitize_key($s) { return strtolower(preg_replace('/[^a-z0-9_-]/', '', (string)$s)); }
    function get_bloginfo($key='version') { return '6.9'; }
    function current_time($format='mysql', $gmt=true) { return gmdate('Y-m-d H:i:s'); }
    function delete_site_transient($key) { return true; }
    function wp_clean_plugins_cache($x=true) { return true; }
    function wp_update_plugins() { return true; }
}
namespace RoutesPro\Support {
    final class ProductionManifest { public static function verify(bool $fresh=false): array { return ['ok'=>true]; } }
    final class ReleasePackageValidator {
        public const MAX_BYTES = 67108864;
        public static function validate(string $path): array {
            return ['ok'=>true,'version'=>FF_NEXT_VERSION,'bytes'=>filesize($path),'sha256'=>hash_file('sha256',$path)];
        }
    }
    final class ReleasePreflight {
        public static function evaluate(array $package, string $runtime): array {
            return ['can_publish'=>!empty($package['ok']) && $runtime===FIELDFLOW_VERSION && $package['version']===FF_NEXT_VERSION];
        }
    }
}
namespace RoutesPro\Cloud {
    final class ReleasePublisherClient {
        public static function health(): array { return ['ok'=>true,'service'=>'fieldflow-release-publisher']; }
        public static function inspect(string $version, string $channel): array {
            if ($GLOBALS['ff_inspect_bad']) return ['success'=>false,'message'=>'request timed out'];
            return ['success'=>true,'releases'=>array_values($GLOBALS['ff_remote'])];
        }
        public static function findRelease(array $response, string $version, string $channel): array {
            foreach ((array)($response['releases']??[]) as $row) if ($row['version']===$version && $row['channel']===$channel) return $row;
            return [];
        }
        public static function uploadPackage(string $path, array $p, string $channel): array {
            $GLOBALS['ff_uploads']++;
            if ($GLOBALS['ff_fail_upload']) return ['success'=>false,'message'=>'timeout'];
            return [
                'success'=>true, 'blob_name'=>'fieldflow/'.$channel.'/'.FF_NEXT_VERSION.'/fieldflow-'.FF_NEXT_VERSION.'.zip',
                'blob_container'=>'fieldflow-private-releases',
            ];
        }
        public static function publishCatalog(array $uploaded, array $p, string $channel, array $options=[]): array {
            $GLOBALS['ff_catalog_calls']++;
            if (!$GLOBALS['ff_catalog_ok']) return ['success'=>false,'message'=>'timeout'];
            $GLOBALS['ff_remote'][] = [
                'version'=>$p['version'], 'channel'=>$channel, 'sha256'=>$p['sha256'],
                'package_size'=>$p['bytes'], 'release_id'=>'release-test',
            ];
            return ['success'=>true];
        }
    }
    final class ReleasePublicationReceipt {
        public static function current(string $version=''): array { return $GLOBALS['ff_receipts'][$version]??['version'=>'']; }
        public static function persist(array $receipt): array { $GLOBALS['ff_receipts'][$receipt['version']]=$receipt; return $receipt; }
    }
    final class PrivatePluginUpdater { public static function clearCache(): void {} }
    final class PrivateUpdateLifecycle { public static function resetHealthCache(): void {} }
}
namespace RoutesPro\Core {
    final class AuditLog {
        public static function record($action, $entity, $id, $before, $after, $context=[], $evidence=[]): void { $GLOBALS['ff_audit_calls']++; }
    }
}
namespace {
    require_once ROUTESPRO_PATH.'src/Support/ReleaseStagingWorkflow.php';
    require_once ROUTESPRO_PATH.'src/Support/ReleaseGate.php';
    use RoutesPro\Support\ReleaseStagingWorkflow as Flow;
    $n = 0;
    $check = static function($ok, $reason) use (&$n) {
        $n++;
        if (!$ok) { fwrite(STDERR, "9DB FAIL #$n: $reason\n"); exit(1); }
    };
    $ref = new \ReflectionClass(Flow::class);
    $dir = $ref->getMethod('privateDirectory')->invoke(null);
    $save = $ref->getMethod('save');
    $name = str_repeat('c',48).'.zip';
    $testFile = $dir.'/'.$name;
    file_put_contents($testFile, 'fieldflow-9db-verified-fixture');
    chmod($testFile,0600);
    $sha = hash_file('sha256',$testFile);
    $base = [
        'contract'=>Flow::CONTRACT,'token'=>str_repeat('a',48),'filename'=>$name,
        'user_id'=>7,'blog_id'=>1,'version'=>FF_NEXT_VERSION,'sha256'=>$sha,
        'bytes'=>filesize($testFile),'status'=>'staged','channel'=>'','blob'=>[],
        'created'=>time(),'updated'=>time(),'expires'=>time()+3600,'events'=>[],
    ];
    $make = static function(array $changes=[]) use ($base,$testFile,$save): array {
        if (!file_exists($testFile)) file_put_contents($testFile, 'fieldflow-9db-verified-fixture');
        $record=array_replace($base,$changes);
        $save->invoke(null,$record);
        return $record;
    };
    $expectFailure = static function(callable $run, string $message) use($check): void {
        $failed=false; try{$run();}catch(\Throwable $e){$failed=true;}
        $check($failed,$message);
    };
    $make();
    $shown=Flow::current(7);
    $check($shown['integrity_ok'] && !array_key_exists('filename',$shown),'private ZIP and SHA visible only as metadata');
    $expectFailure(static function(){Flow::publish(7, str_repeat('0',48), 'stable', []);},'token mismatch must refuse');
    $GLOBALS['ff_admin']=8;
    $expectFailure(static function(){Flow::current(7);},'user isolation');
    $GLOBALS['ff_admin']=7;
    $GLOBALS['ff_allowed']=false;
    $expectFailure(static function(){Flow::current(7);},'capability isolation');
    $GLOBALS['ff_allowed']=true;
    $lockMethod=$ref->getMethod('acquireLock');
    $unlockMethod=$ref->getMethod('unlock');
    $existingLock=$lockMethod->invoke(null, 'stage-7');
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'concurrent stage lock blocks publication');
    $unlockMethod->invoke(null, $existingLock);
    file_put_contents($testFile,'tampered');
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'tampered digest blocks before upload');
    $check($GLOBALS['ff_uploads']===0,'no upload on tampered digest');
    file_put_contents($testFile,'fieldflow-9db-verified-fixture');
    $make();
    $result=Flow::publish(7,$base['token'],'stable',['notes'=>'release']);
    $check($result['ok'] && Flow::current(7)['status']==='completed','publish once and remote confirmation');
    $check($GLOBALS['ff_uploads']===1 && $GLOBALS['ff_catalog_calls']===1,'exactly one blob and catalog POST');
    $check($GLOBALS['ff_audit_calls']===1 && count($GLOBALS['ff_receipts'])===1,'receipt and audit only once');
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'no duplicate POST from completed stage');
    $GLOBALS['ff_remote']=[];
    $GLOBALS['ff_receipts']=[];
    $GLOBALS['ff_catalog_ok']=false;
    $GLOBALS['ff_audit_calls']=0;
    $make();
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'catalog timeout must not auto retry');
    $check(Flow::current(7)['status']==='catalog_unknown','catalog result is uncertain');
    $before=$GLOBALS['ff_catalog_calls'];
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'no second publish on uncertain catalog');
    $out=Flow::reconcile(7,$base['token']);
    $check(!$out['ok'] && $before===$GLOBALS['ff_catalog_calls'],'read-only reconciliation on missing release');
    $GLOBALS['ff_remote'][] = [
        'version'=>FF_NEXT_VERSION,'channel'=>'stable','sha256'=>$sha,
        'package_size'=>filesize($testFile),'release_id'=>'release-late'
    ];
    $result=Flow::reconcile(7,$base['token']);
    $check($result['ok'] && Flow::current(7)['status']==='completed','late remote publication reconciled');
    $check($before===$GLOBALS['ff_catalog_calls'] && $GLOBALS['ff_audit_calls']===1,'reconciliation does not resend catalog and records once');
    $make();
    $GLOBALS['ff_remote']=[];
    $GLOBALS['ff_fail_upload']=true;
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'upload timeout must stay unknown');
    $check(Flow::current(7)['status']==='upload_unknown','upload unknown state retained');
    $GLOBALS['ff_fail_upload']=false;
    $make([
        'channel'=>'stable','status'=>'blob_ready',
        'publish_options'=>['requires_wp'=>'6.4','requires_php'=>'7.4','tested_wp'=>'6.9','notes'=>'recover'],
        'blob'=>['blob_name'=>'fieldflow/stable/'.FF_NEXT_VERSION.'/fieldflow-'.FF_NEXT_VERSION.'.zip','blob_container'=>'fieldflow-private-releases'],
    ]);
    $before=$GLOBALS['ff_uploads'];
    $GLOBALS['ff_catalog_ok']=true;
    $GLOBALS['ff_receipts']=[];
    $GLOBALS['ff_remote']=[];
    $result=Flow::resumeCatalog(7,$base['token']);
    $check($result['ok'] && $GLOBALS['ff_uploads']===$before,'resume only catalog for acknowledged blob');
    $make();
    $GLOBALS['ff_remote']=[['version'=>FF_NEXT_VERSION,'channel'=>'stable','sha256'=>str_repeat('0',64),'package_size'=>filesize($testFile)]];
    $before=$GLOBALS['ff_uploads'];
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'foreign remote SHA must reject');
    $check($before===$GLOBALS['ff_uploads'],'remote conflict before upload');
    $make();
    $GLOBALS['ff_remote']=[['version'=>FF_NEXT_VERSION,'channel'=>'stable','sha256'=>$sha,'package_size'=>filesize($testFile),'status'=>'unpublished']];
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'unpublished release cannot complete receipt');
    $make(['created'=>time()-86400,'expires'=>time()-2,'status'=>'catalog_unknown','channel'=>'stable']);
    $GLOBALS['ff_remote']=[['version'=>FF_NEXT_VERSION,'channel'=>'stable','sha256'=>$sha,'package_size'=>filesize($testFile)]];
    $state=Flow::current(7);
    $check(!$state['integrity_ok'] && !file_exists($testFile),'24h ZIP expiry purges bytes, journal remains');
    $GLOBALS['ff_receipts']=[];
    $check(Flow::reconcile(7,$base['token'])['ok'],'reconcile remote after ZIP deleted');
    $make();
    $GLOBALS['ff_inspect_bad']=true;
    $before=$GLOBALS['ff_uploads'];
    $expectFailure(static function()use($base){Flow::publish(7,$base['token'],'stable',[]);},'unavailable remote inspect must fail closed');
    $check($before===$GLOBALS['ff_uploads'],'unavailable inspect cannot authorise upload');
    $GLOBALS['ff_inspect_bad']=false;
    $make();
    $before=$GLOBALS['ff_uploads'];
    $expectFailure(static function()use($testFile,$sha){Flow::stage(['tmp_name'=>$testFile,'size'=>filesize($testFile),'name'=>'mock.zip'],7,['ok'=>true,'sha256'=>$sha,'bytes'=>filesize($testFile),'version'=>FF_NEXT_VERSION]);},'non-HTTP uploaded file rejected');
    $check($before===$GLOBALS['ff_uploads'],'local file injection does not publish');
    Flow::discard(7,$base['token']);
    $check(!Flow::current(7) && !is_file($testFile),'explicit discard removes temp ZIP and option');
    $gate=(new ReflectionMethod(\RoutesPro\Support\ReleaseGate::class,'checkReleaseManagerAssistedWorkflow9DBContract'))->invoke(null);
    $check($gate['status']==='pass' && empty($gate['meta']['missing']),'9DB runtime gate intact');
    echo "RELEASE MANAGER ASSISTED WORKFLOW 9DB PASS ($n assertions)\n";
}
