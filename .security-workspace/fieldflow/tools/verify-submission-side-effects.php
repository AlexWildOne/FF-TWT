<?php
namespace {
    if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
    if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);
    if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
    if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
    if (!function_exists('sanitize_key')) { function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v)) ?: ''; } }
    if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return trim(strip_tags((string)$v)); } }
    if (!function_exists('wp_json_encode')) { function wp_json_encode($v,$flags=0){ return json_encode($v,$flags); } }
    if (!function_exists('is_wp_error')) { function is_wp_error($v){ return false; } }

    $GLOBALS['ff_transients'] = [];
    $GLOBALS['ff_scheduled'] = [];
    $GLOBALS['ff_calls'] = [];
    $GLOBALS['ff_fail_order'] = false;
    $GLOBALS['ff_fail_public_submission'] = false;

    function set_transient($k,$v,$ttl){ $GLOBALS['ff_transients'][$k]=$v; return true; }
    function get_transient($k){ return $GLOBALS['ff_transients'][$k] ?? false; }
    function delete_transient($k){ unset($GLOBALS['ff_transients'][$k]); return true; }
    function wp_next_scheduled($hook,$args=[]){ foreach($GLOBALS['ff_scheduled'] as $e){ if($e['hook']===$hook && $e['args']===$args) return $e['time']; } return false; }
    function wp_schedule_single_event($time,$hook,$args=[]){ $GLOBALS['ff_scheduled'][]=['time'=>$time,'hook'=>$hook,'args'=>$args]; return true; }
    function add_action($hook,$callback,$priority=10,$accepted=1){ $GLOBALS['ff_calls'][]='registered:'.$hook; return true; }
    function do_action($hook,...$args){
        $GLOBALS['ff_calls'][]='hook:'.$hook;
        if ($hook === 'fieldflow_form_submitted' || $hook === 'fieldflow_form_updated') {
            if (!empty($GLOBALS['ff_fail_public_submission'])) throw new \RuntimeException('public hook failure');
        }
    }
}

namespace RoutesPro\Core {
    final class EventBus {
        public static function onVisitCompleted(int $id,array $completion=[],array $answers=[]): void { $GLOBALS['ff_calls'][]='eventbus:visit'; }
        public static function onSubmissionCreated(int $id,array $payload=[],array $answers=[]): void { $GLOBALS['ff_calls'][]='eventbus:create'; }
        public static function onSubmissionUpdated(int $id,array $payload=[],array $answers=[]): void { $GLOBALS['ff_calls'][]='eventbus:update'; }
    }
}
namespace RoutesPro\Admin {
    final class ReportAutomations {
        public static function handleVisitCompleted(int $id,array $completion=[],array $answers=[]): void { $GLOBALS['ff_calls'][]='report:visit'; }
        public static function handleOrderSuggestionSubmission(int $id,array $payload=[],array $answers=[]): void {
            $GLOBALS['ff_calls'][]='report:order';
            if (!empty($GLOBALS['ff_fail_order'])) throw new \RuntimeException('order failure');
        }
    }
}
namespace RoutesPro\Performance {
    final class Module {
        public static function handle_form_submission_automation(int $id,array $payload,array $answers): void { $GLOBALS['ff_calls'][]='performance:submission'; }
    }
}
namespace RoutesPro\Services {
    final class Notify {
        public static function route_event(int $route,string $state,string $message): void { $GLOBALS['ff_calls'][]='notify:route'; }
    }
}
namespace RoutesPro\Support {
    final class Logger {
        public static function captureThrowable(string $message,\Throwable $error,array $context=[]): void { $GLOBALS['ff_calls'][]='logger:'.($context['lane']??'generic'); }
    }
}

namespace {
    $root=dirname(__DIR__);
    require_once $root.'/src/Forms/SubmissionSideEffectDispatcher.php';
    $class='\\RoutesPro\\Forms\\SubmissionSideEffectDispatcher';
    $fail=[];
    $assert=static function($ok,$name)use(&$fail){echo($ok?'PASS ':'FAIL ').$name."\n";if(!$ok)$fail[]=$name;};
    $read=static fn($r)=>is_readable($root.'/'.$r)?(string)file_get_contents($root.'/'.$r):'';
    $contains=static function($c,$m){foreach($m as$x)if(strpos($c,$x)===false)return false;return true;};

    $assert($class::CONTRACT==='fieldflow-submission-side-effects-v1' && $class::VERSION===1,'side_effect_contract');
    $descriptor=$class::descriptor();
    $assert(($descriptor['idempotency']??'')==='per_revision_per_lane' && ($descriptor['retry_hook']??'')==='fieldflow_submission_side_effect_retry','lane_descriptor');

    $job=[
        'submission_id'=>77,
        'is_update'=>false,
        'source'=>'test',
        'revision_token'=>'77:c:0:2026-09-18 20:00:00',
        'submission_payload'=>['submission_id'=>77,'route_id'=>9,'user_id'=>4],
        'answers'=>['q'=>['type'=>'text','label'=>'Q','value'=>'x']],
        'visit_completion'=>['completed_now'=>true,'route_completed_now'=>true,'route_id'=>9,'route_stop_id'=>12],
    ];
    $GLOBALS['ff_fail_order']=true;
    $result=$class::dispatchJob($job);
    $assert(($result['lanes']['order_suggestion_mail']['status']??'')==='failed' && !empty($result['lanes']['order_suggestion_mail']['retry_scheduled']),'failed_lane_schedules_own_retry');
    $assert(in_array('eventbus:create',$GLOBALS['ff_calls'],true) && in_array('performance:submission',$GLOBALS['ff_calls'],true) && in_array('hook:fieldflow_form_submitted',$GLOBALS['ff_calls'],true),'failure_does_not_block_later_lanes');
    $assert(in_array('notify:route',$GLOBALS['ff_calls'],true) && in_array('eventbus:visit',$GLOBALS['ff_calls'],true) && in_array('report:visit',$GLOBALS['ff_calls'],true),'visit_lanes_isolated');
    $scheduled=array_values(array_filter($GLOBALS['ff_scheduled'],static fn($e)=>$e['hook']==='fieldflow_submission_side_effect_retry'));
    $assert(count($scheduled)===1 && ($scheduled[0]['args'][1]??'')==='order_suggestion_mail','retry_is_lane_specific');

    $eventKey=(string)($result['event_key']??'');
    $GLOBALS['ff_fail_order']=false;
    $class::retryLane($eventKey,'order_suggestion_mail',1);
    $orderCalls=count(array_filter($GLOBALS['ff_calls'],static fn($v)=>$v==='report:order'));
    $assert($orderCalls===2,'lane_retry_recovers_without_full_replay');

    $before=count($GLOBALS['ff_calls']);
    $again=$class::dispatchJob($job);
    $after=count($GLOBALS['ff_calls']);
    $assert($after===$before && empty($again['failed']),'same_revision_is_idempotent');

    $GLOBALS['ff_fail_public_submission']=true;
    $update=$job;
    $update['is_update']=true;
    $update['revision_token']='77:u:1:2026-09-18 20:05:00';
    $update['visit_completion']=[];
    $resultUpdate=$class::dispatchJob($update);
    $assert(in_array('eventbus:update',$GLOBALS['ff_calls'],true) && !in_array('performance:update',$GLOBALS['ff_calls'],true),'update_uses_update_core_event_without_performance_create_lane');
    $assert(($resultUpdate['lanes']['submission_public_hook']['status']??'')==='failed' && ($resultUpdate['lanes']['submission_core_event']['status']??'')==='processed','public_extension_failure_isolated');

    $forms=$read('src/Forms/Forms.php');
    $mobile=$read('src/Mobile/SubmissionService.php');
    $eventBus=$read('src/Core/EventBus.php');
    $reports=$read('src/Admin/ReportAutomations.php');
    $performance=$read('src/Performance/Module.php');
    $queue=$read('src/Operations/Queue/QueueSettings.php');
    $monitor=$read('src/Operations/ProcessMonitor.php');
    $loader=$read('src/Support/Loader.php');
    $pipeline=$read('src/Forms/SubmissionPipeline.php');

    $assert($contains($forms,['SubmissionSideEffectDispatcher::registerHooks()','SubmissionSideEffectDispatcher::dispatchFromSubmission(']),'web_dispatcher_integration');
    $assert($contains($mobile,['use RoutesPro\\Forms\\SubmissionSideEffectDispatcher','SubmissionSideEffectDispatcher::dispatchFromSubmission(','observe_media_non_blocking','Mobile submission audit failed after commit.']),'mobile_dispatcher_and_post_commit_safety');
    $assert(strpos($eventBus,"add_action('fieldflow_form_submitted'")===false && strpos($reports,"add_action('fieldflow_form_submitted'")===false && strpos($performance,"add_action('fieldflow_form_submitted'")===false,'internal_legacy_hook_ownership_removed');
    $assert($contains($queue,['fieldflow_submission_side_effect_retry',"'max_attempts'=>4"]) && $contains($monitor,['submission_side_effect_retry','fieldflow_submission_side_effect_retry']),'retry_queue_observability');
    $assert(strpos($loader,'src/Forms/SubmissionSideEffectDispatcher.php')!==false,'loader_registration');
    $assert($contains($pipeline,["PIPELINE_VERSION = '1.1.0'","BUILD = '2.2.485'",'SubmissionSideEffectDispatcher']),'pipeline_handoff_contract');

    if($fail){fwrite(STDERR,"SUBMISSION SIDE EFFECTS CONTRACT FAILED\n".implode("\n",$fail)."\n");exit(1);}
    echo "SUBMISSION SIDE EFFECTS CONTRACT OK\n";
    echo "contract=fieldflow-submission-side-effects-v1\n";
    echo "durable_commit_boundary=preserved\n";
    echo "internal_lanes=isolated_idempotent\n";
    echo "retry_scope=per_lane\n";
    echo "legacy_public_hooks=preserved\n";
}
