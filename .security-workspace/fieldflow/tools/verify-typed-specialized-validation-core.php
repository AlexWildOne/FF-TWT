<?php
/** Build-time and behavioural verifier for FieldFlow 2.2.530 Typed Specialized Validation Core. */
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!function_exists('sanitize_key')) { function sanitize_key($key){ $key=strtolower((string)$key); return preg_replace('/[^a-z0-9_\-]/','',$key) ?: ''; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($v){ return trim(strip_tags((string)$v)); } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($v){ return strip_tags((string)$v); } }
if (!function_exists('absint')) { function absint($v){ return abs((int)$v); } }
if (!function_exists('is_wp_error')) { function is_wp_error($v){ return $v instanceof WP_Error; } }
if (!class_exists('WP_Error')) { class WP_Error { private $c,$m,$d; function __construct($c='',$m='',$d=null){$this->c=$c;$this->m=$m;$this->d=$d;} function get_error_code(){return $this->c;} function get_error_message(){return $this->m;} function get_error_data(){return $this->d;} } }
$root=dirname(__DIR__); $read=static fn($r)=>is_readable($root.'/'.ltrim($r,'/'))?(string)file_get_contents($root.'/'.ltrim($r,'/')):'';
$containsAll=static function($c,$m){foreach($m as $x)if(strpos($c,$x)===false)return false;return true;}; $fail=[];
$assert=static function($ok,$name)use(&$fail){echo ($ok?'PASS ':'FAIL ').$name."\n";if(!$ok)$fail[]=$name;};
$fieldflow=$read('fieldflow.php'); $phase=$read('src/Cloud/Phase6StableRelease.php'); $source=$read('frontend/src/forms/specialized-validation-core.ts'); $asset=$read('assets/fieldflow-frontend-core-2.2.530.js');
$renderer=$read('src/Forms/FormRenderer.php'); $web=$read('src/Forms/Forms.php'); $mobile=$read('src/Mobile/SubmissionService.php'); $manager=$read('src/Admin/ReleaseManager.php'); $gate=$read('src/Support/ReleaseGate.php');
$assert($containsAll($fieldflow,['Version: 2.2.530',"FIELDFLOW_VERSION', '2.2.530'"]),'release_version_alignment');
$assert($containsAll($phase,["SOURCE_RELEASE = '2.2.529'","STABLE_RELEASE = '2.2.530'"]),'phase6_release_continuity');
$assert($containsAll($source,['fieldflow-typed-specialized-validation-core-v1','fieldFlowSpecializedCategorized','fieldFlowSpecializedShareSpace','fieldFlowSpecializedEvidence','fieldFlowTypedSpecializedValidationCoreApi']),'typescript_specialized_contract');
$assert($containsAll($asset,['FieldFlow 2.2.530','fieldflow-typed-specialized-validation-core-v1','fieldFlowTypedSpecializedValidationCoreApi']),'compiled_specialized_contract');
$assert($containsAll($renderer,['rpTypedSpecializedValidationCore','firstTypedSpecializedInvalid','data-rp-specialized-validation-core-contract']),'form_renderer_specialized_integration');
$assert($containsAll($web,['SpecializedValidation::validate_schema','categorized_upload_has_missing_selection','ShareSpaceMatrix::isComplete']),'web_specialized_integration');
$assert($containsAll($mobile,['use RoutesPro\\Forms\\SpecializedValidation','SpecializedValidation::validate_schema']),'mobile_specialized_integration');
$assert($containsAll($manager,['Typed Specialized Validation',"packageResult['typed_specialized_validation_core_ok']"]),'release_manager_gate');
$assert($containsAll($gate,["VERSION = '1.79.0'",'checkTypedSpecializedValidationCoreContract','fieldflow-typed-specialized-validation-core-v1']),'release_gate_contract');
require_once $root.'/src/Briefings/ShareSpaceMatrix.php'; require_once $root.'/src/Forms/SubmissionValidation.php'; require_once $root.'/src/Forms/EvidenceFlow.php'; require_once $root.'/src/Forms/SpecializedValidation.php';
$fixtures=json_decode((string)file_get_contents($root.'/frontend/tests/specialized-validation-fixtures.json'),true);
use RoutesPro\Forms\SpecializedValidation;
$assert(SpecializedValidation::CONTRACT==='fieldflow-typed-specialized-validation-core-v1','php_specialized_contract');
foreach((array)($fixtures['categorized']??[]) as $i=>$f){ $rows=[]; foreach((array)($f['selected']??[]) as $k){$rows[]=['category_key'=>$k,'images'=>(array)(($f['media'][$k]??[]))];} $r=SpecializedValidation::categorized_state(!empty($f['required']),$rows); $assert((bool)$r['valid']===(bool)$f['valid'] && (string)$r['issue']===(string)$f['issue'],'categorized_fixture_'.($i+1)); }
foreach((array)($fixtures['share_space']??[]) as $i=>$f){ $config=['rows'=>array_map(static fn($k)=>['metric_key'=>$k,'metric'=>$k,'pr_label'=>'PR','competitor_label'=>'Comp'],(array)($f['expected']??[]))]; $r=SpecializedValidation::share_space_state(!empty($f['required']),(array)($f['rows']??[]),$config); $assert((bool)$r['valid']===(bool)$f['valid'] && (string)$r['issue']===(string)$f['issue'],'share_space_fixture_'.($i+1)); }
foreach((array)($fixtures['product_matrix']??[]) as $i=>$f){ $actual=!empty($f['required'])?SpecializedValidation::product_matrix_has_meaningful_row((array)($f['rows']??[])):true; $assert($actual===(bool)$f['valid'],'product_matrix_fixture_'.($i+1)); }
$assert(SpecializedValidation::has_media_value(['https://example.test/a.jpg']),'media_value_detected');
$assert(!SpecializedValidation::has_media_value([['size'=>0,'name'=>'empty.jpg']]),'zero_byte_media_rejected');
if($fail){fwrite(STDERR,"TYPED SPECIALIZED VALIDATION CORE CONTRACT FAILED\n".implode("\n",$fail)."\n");exit(1);} echo "TYPED SPECIALIZED VALIDATION CORE CONTRACT OK\ncontract=fieldflow-typed-specialized-validation-core-v1\nphp_typescript_parity=ok\nweb_mobile_specialized_parity=ok\nshare_space_partial_mobile_guard=ok\n";
