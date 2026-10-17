<?php
$root = dirname(__DIR__);
define('ABSPATH', $root . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$v)); } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }

require_once $root . '/src/Services/MailDispatchService.php';

$short=@file_get_contents($root.'/src/Shortcodes.php')?:'';
$form=@file_get_contents($root.'/src/Forms/FormRenderer.php')?:'';
$report=@file_get_contents($root.'/src/Admin/ReportAutomations.php')?:'';
$shopping=@file_get_contents($root.'/src/Shopping/ShoppingService.php')?:'';
$ok = strpos($short,'fieldflow-passive-viewport-continuity-v3')!==false
    && strpos($short,'reportPassiveViewportContract')!==false
    && strpos($short,'Passive continuity deliberately performs no scrollTop/window.scrollTo write.')!==false
    && strpos($short,'reportViewportGuardRaf=window.requestAnimationFrame')===false
    && strpos($short,"root.style.position='fixed';")===false
    && strpos($short,'fieldflow:form-external-handoff')!==false
    && strpos($form,'fieldflow-passive-viewport-continuity-v3')!==false
    && strpos($form,'Do not write scrollTop. The WebView/browser owns scrolling in 2.2.551.')!==false
    && strpos($form,'position:fixed!important;left:-10000px!important;top:0!important')!==false
    && strpos($form,'rpRequestFormVisualFreeze(form,')===false
    && strpos($form,"document.body.style.position==='fixed'")===false
    && strpos($report,'if ($automationType === \'daily_close\' && isset($ranges[\'daily\'])) $attachmentPeriodKeys = [\'daily\'];')!==false
    && strpos($report,"'audience' => 'manager'")!==false
    && strpos($report,'Excel and PDF are part of the order-suggestion delivery contract')!==false
    && strpos($shopping,'$requestedFormats = [\'xlsx\',\'pdf\'];')!==false;
if(!$ok){fwrite(STDERR,"9AG runtime source semantics failed\n");exit(1);}

// Transport guard: the same physical attachment can never be sent twice.
$tmp=tempnam(sys_get_temp_dir(),'ff9ag_');file_put_contents($tmp,'attachment');
$method=new ReflectionMethod(\RoutesPro\Services\MailDispatchService::class,'attachmentPaths');$method->setAccessible(true);
$paths=$method->invoke(null,[$tmp,$tmp]);@unlink($tmp);
if(!is_array($paths)||count($paths)!==1){fwrite(STDERR,"9AG transport attachment dedupe failed\n");exit(1);}

// Daily Close runtime model: two KPI periods in the body, one attachment period.
class FieldFlow9AGFormSubmissionsStub {
    public static array $filters=[];
    public static array $files=[];
    public static function prepareDashboardReport(array $filters): array {
        self::$filters[]=$filters;
        return ['data'=>['canonical_operational_report'=>['kpis'=>[]],'report_intelligence'=>[]]];
    }
    public static function createDashboardReportFileFromPrepared(array $prepared,string $format,string $label): array {
        $path=tempnam(sys_get_temp_dir(),'ff9ag_report_').'.'.$format;
        file_put_contents($path,$format.'-'.$label);
        self::$files[]=$path;
        return ['path'=>$path,'filename'=>basename($path)];
    }
}
class_alias(FieldFlow9AGFormSubmissionsStub::class,'RoutesPro\\Admin\\FormSubmissions');
require_once $root . '/src/Admin/ReportAutomations.php';
$bundleMethod=new ReflectionMethod(\RoutesPro\Admin\ReportAutomations::class,'buildReportBundle');$bundleMethod->setAccessible(true);
$ranges=[
    'daily'=>['from'=>'2026-09-22','to'=>'2026-09-22','label'=>'Dia'],
    'week_to_date'=>['from'=>'2026-09-21','to'=>'2026-09-22','label'=>'Acumulado semanal'],
];
$bundle=$bundleMethod->invoke(null,['automation_type'=>'daily_close','client_id'=>1,'project_id'=>2],$ranges,['xlsx','pdf'],['user_id'=>42,'self_mode'=>true,'file_suffix'=>'merchan-42']);
$dailyOk=is_array($bundle)
    && count((array)($bundle['attachments']??[]))===2
    && count((array)($bundle['summaries']??[]))===2
    && count(FieldFlow9AGFormSubmissionsStub::$filters)===2
    && (string)(FieldFlow9AGFormSubmissionsStub::$filters[0]['audience']??'')==='manager'
    && (int)(FieldFlow9AGFormSubmissionsStub::$filters[0]['user_id']??0)===42;
foreach(FieldFlow9AGFormSubmissionsStub::$files as $file){if(is_file($file))@unlink($file);}
if(!$dailyOk){fwrite(STDERR,"9AG daily-close canonical attachment model failed\n");exit(1);}

echo "MAIL FLOW VIEWPORT 9AG RUNTIME OK\n";
