<?php
/** Isolated WordPress meta stub: query results are deliberately untrusted. */
define('ABSPATH','/tmp/');
function absint($value) { return abs((int)$value); }
function sanitize_key($value) {return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$value));}
function get_posts($args) {
 if (($args['meta_key'] ?? '') !== '_ff_date' || ($args['orderby'] ?? '') !== 'meta_value' || ($args['order'] ?? '') !== 'DESC') {
  fwrite(STDERR, '9D1 HISTORY FAIL: last closed report day ordering\n');exit(1);}
 return [102,103,104,105,101];
}
function get_post_meta($id,$key,$single=true) {return $GLOBALS['meta9d1'][$id][$key] ?? '';}
require dirname(__DIR__) . '/src/Vision/FrontsVisionService.php';
use RoutesPro\Vision\FrontsVisionService as V;
function f9d1check($x,$name){if(!$x){fwrite(STDERR,"9D1 HISTORY FAIL: $name\n");exit(1);}}
$base=['_ff_client_id'=>'9','_ff_project_id'=>'77','_ff_location_id'=>'345','_ff_form_id'=>'22',
 '_ff_question_key'=>'fronts','_ff_question_type'=>'product_matrix','_ff_status'=>'confirmed',
 '_ff_submission_id'=>'900','_ff_date'=>'2026-10-08',
 '_ff_detected'=>json_encode(['items'=>[['key'=>'r0','catalog_id'=>33,'name'=>'Jameson 70cl']]]),
 '_ff_confirmed'=>json_encode([['key'=>'r0','fronts'=>5]])];
$GLOBALS['meta9d1']=[
 102=>array_merge($base,['_ff_location_id'=>'999']),
 103=>array_merge($base,['_ff_status'=>'detected']),
 104=>array_merge($base,['_ff_submission_id'=>'0']),
 105=>array_merge($base,['_ff_date'=>'2026-10-11']),
 101=>$base,
];
$request=['client_id'=>9,'project_id'=>77,'location_id'=>345,'form_id'=>22,'question_key'=>'fronts','date'=>'2026-10-10'];
$current=[['key'=>'r17','catalog_id'=>33,'name'=>'Jameson 70cl']];
$previous=V::previousClosedVisit($request,$current,'product_matrix');
f9d1check(is_array($previous) && $previous['date']==='2026-10-08'
 && $previous['items']===['r17'=>5] && $previous['basis']==='confirmed_closed_visit',
 'same_store_previous_closed');
f9d1check(V::previousClosedVisit($request+['irrelevant'=>1],$current,'share_space_matrix')===null,
 'wrong_question_type_fails_closed');
f9d1check(V::previousClosedVisit(array_merge($request,['location_id'=>0]),$current,'product_matrix')===null,
 'missing_store_does_not_expose_history');
$GLOBALS['meta9d1'][101]['_ff_location_id']='999';
f9d1check(V::previousClosedVisit($request,$current,'product_matrix')===null,
 'another_store_not_reused');
echo 'FRONTS HISTORY SCOPE 9D1 PHP OK | same_store_previous_closed | unlinked_rejected | future_rejected | tenant_store_question_isolation' . PHP_EOL;
