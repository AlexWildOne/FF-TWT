<?php
namespace {
    define('ABSPATH','/tmp');
    $GLOBALS['ff9cz_opts'] = [];
    function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$s));}
    function sanitize_text_field($s){return strip_tags((string)$s);}
    function absint($s){return abs((int)$s);}
    function get_option($k,$d=false){return $GLOBALS['ff9cz_opts'][$k]??$d;}
    function update_option($k,$v,$a=null){$GLOBALS['ff9cz_opts'][$k]=$v;return true;}
    function current_time($format){return $format==='mysql'?'2026-10-10 17:00:00':strtotime('2026-10-10 17:00:00');}
    function wp_json_encode($v,$f=0){return json_encode($v,$f);}
    function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
    function wp_remote_retrieve_response_code($r){return $r['status'];}
    function wp_remote_retrieve_body($r){return $r['body'];}
    function is_wp_error($r){return false;}
    function wp_strip_all_tags($s){return strip_tags((string)$s);}
    function get_current_user_id(){return 20;}
    function ff9cz_assert($value,$name){if (!$value){fwrite(STDERR,"FF9CZ RUNTIME FAIL: $name\n");exit(1);}}
}
namespace RoutesPro\Security {
    class OutboundHttp {
        public static $calls=[];
        public static function configuredPolicy($endpoint,$purpose,$args=[]){return [];}
        public static function post($url,$request,$policy=[]){
            self::$calls[]=['url'=>$url,'request'=>$request];
            return ['status'=>200,'body'=>json_encode([
                'choices'=>[['message'=>['content'=>'{"observations":[]}']]],
                'usage'=>['prompt_tokens'=>10000,'completion_tokens'=>2000],
            ])];
        }
    }
}
namespace RoutesPro\Support {
    class Config {
        public static function get($key,$default=null){
            $data=['ai_enabled'=>1,'azure_openai_key'=>'fake-not-real',
              'azure_openai_endpoint'=>'https://fakelab.openai.azure.com/',
              'azure_openai_deployment'=>'gpt-4o-mini',
              'azure_openai_api_version'=>'2024-10-21'];
            return $data[$key]??$default;
        }
        public static function aiProviderForModule($module){return 'azure';}
        public static function aiProviderReady($provider){return $provider==='azure';}
    }
}
namespace RoutesPro\AI {
    class Governance {
        public static function preflight($module,$prompt,$params){return [
            'prompt'=>$prompt,'mode'=>'shadow','blocked'=>false,'reason'=>'',
            'attribution'=>$params['fieldflow_attribution'],'budgets'=>[],
            'prompt_version'=>'9cz','risk_flags'=>[],
        ];}
        public static function usageFor($provider,$prompt,$output){
            $usage=$provider->getLastUsage();
            return ['input_tokens'=>$usage['input_tokens']??0,'output_tokens'=>$usage['output_tokens']??0,
                'usage_source'=>'provider','estimated_cost_eur'=>0];
        }
        public static function reconcileBudgetAlerts($attribution,$module) {}
    }
}
namespace RoutesPro\Repositories {
    class AIUsageRepository {
        public static $logs=[];
        public static function insert($data){self::$logs[]=$data;return count(self::$logs);}
        public static function todayCount(){return 0;}
        public static function deleteOlderThan($days){return 0;}
    }
}
namespace {
    $root=dirname(__DIR__);
    require $root.'/src/Vision/VisionCostMeter.php';
    require $root.'/src/Services/AI.php';
    $tariffs=\RoutesPro\Vision\VisionCostMeter::saveRate('azure','gpt-4o-mini-vision','0.12','0.48');
    $output=\RoutesPro\Services\AIManager::complete('fronts_vision','Look at picture',[
        'force_provider'=>'azure','deployment'=>'gpt-4o-mini-vision','max_attempts'=>1,
        'client_id'=>17,'project_id'=>22,'max_tokens'=>300,'task'=>'fronts_vision_reading',
    ]);
    ff9cz_assert($output==='{"observations":[]}', 'provider_responded');
    ff9cz_assert(count(\RoutesPro\Security\OutboundHttp::$calls)===1,'no_extra_paid_call');
    $log=\RoutesPro\Repositories\AIUsageRepository::$logs[0];
    $receipt=\RoutesPro\Services\AIManager::lastVisionReceipt();
    ff9cz_assert($log['model_name']==='gpt-4o-mini-vision','actual_deployment_used');
    ff9cz_assert($log['usage_source']==='provider' && $log['input_tokens']===10000 && $log['output_tokens']===2000,'image_tokens_from_azure');
    ff9cz_assert(abs($log['estimated_cost_eur']-0.00216)<.00000001,'logged_cost_math');
    ff9cz_assert($log['meta']['vision_cost_status']==='priced' && $log['meta']['attribution']['client_id']===17,'auditable_customer');
    ff9cz_assert($receipt['usage_log_id']===1 && $receipt['status']==='priced','readback_usage_receipt');
    $output=\RoutesPro\Services\AIManager::complete('fronts_vision','Look at picture',[
        'force_provider'=>'azure','deployment'=>'gpt-4o-unpriced','max_attempts'=>1,'client_id'=>17,
    ]);
    $unpriced=\RoutesPro\Repositories\AIUsageRepository::$logs[1];
    ff9cz_assert($unpriced['estimated_cost_eur']===0.0 && $unpriced['meta']['vision_cost_status']==='tariff_missing','unpriced_marked_not_free');
    ff9cz_assert(\RoutesPro\Services\AIManager::lastVisionReceipt()['status']==='tariff_missing','receipt_not_stale');
    echo 'FRONTS AI RUNTIME METERING 9CZ PHP OK | Azure response usage | deployment | log provenance | single call | no tariff != free' . PHP_EOL;
}
