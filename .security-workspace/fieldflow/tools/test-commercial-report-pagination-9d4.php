<?php
/** 9D4 report pagination contract: client-side stale response protection and REST 1001st visit. */
namespace {
    define('ABSPATH','/tmp/wp/');
    function current_user_can($cap) { return $cap==='routespro_manage'; }
    function get_current_user_id(){return 7;}
    function absint($x){return abs((int)$x);}
    function sanitize_text_field($x){return (string)$x;}
    function sanitize_key($x){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$x));}
    class WP_REST_Request {
        private array $params=[];
        public function set_param($k,$v){$this->params[$k]=$v;}
        public function get_param($k){return $this->params[$k]??null;}
    }
    class WP_REST_Response {public function __construct(public array $data,public int $status){}}
    function check9d4($v,$m){if(!$v){fwrite(STDERR,"9D4 REPORT FAIL $m\n");exit(1);}}
}
namespace RoutesPro\Support { class Permissions {public static function can_access_front(){return true;} } }
namespace RoutesPro\Admin {
    class FormSubmissions {
        public static array $requests=[];
        public static function get_visit_report_dataset(array $filters): array {
            self::$requests[]=$filters;
            $all=array_map(static fn($i)=>['route_stop_id'=>$i,'visit_report_id'=>$i],range(1,1001));
            $rows=array_slice($all,$filters['offset']??0,$filters['limit']??300);
            return ['rows'=>$rows,'total'=>!empty($filters['with_total'])?count($all):count($rows),'columns'=>[],'report_mode'=>'visits'];
        }
        public static function get_submission_dataset(array $filters): array {return self::get_visit_report_dataset($filters);}
    }
}
namespace {
    require dirname(__DIR__).'/src/Rest/EventsController.php';
    $c=new \RoutesPro\Rest\EventsController();
    $req=new WP_REST_Request();
    $req->set_param('report_mode','visits');$req->set_param('limit',10);
    $req->set_param('offset',1000);$req->set_param('with_total','1');
    $req->set_param('client_id',12);$req->set_param('date_from','2026-01-01');$req->set_param('date_to','2026-12-31');
    $resp=$c->list_form_submissions($req);
    check9d4($resp->status===200,'REST status 200');
    check9d4($resp->data['total']===1001 && count($resp->data['rows'])===1,'1001st visit accessible');
    check9d4($resp->data['rows'][0]['route_stop_id']===1001,'canonical stop ID');
    $params=\RoutesPro\Admin\FormSubmissions::$requests[0];
    check9d4($params['client_id']===12 && $params['date_from']==='2026-01-01' && $params['date_to']==='2026-12-31','unchanged canonical date and client filters');
    check9d4($params['limit']===10 && $params['offset']===1000 && $params['with_total']===true,'server-side pagination args');
    $front=file_get_contents(dirname(__DIR__).'/src/Front/ClientPortalShortcodeRenderer.php');
    check9d4(strpos($front,"scoped.set('offset',String((currentPage-1)*10))")!==false,'page offset');
    check9d4(strpos($front,"scoped.set('with_total','1')")!==false,'total count explicitly requested');
    check9d4(strpos($front,'requestSequence!==reportLoadSequence||paramsForReports().toString()!==scopeSignature')!==false,'ignore old response');
    check9d4(strpos($front,"p.set('report_mode','visits'); return p")!==false,'header dates canonical');
    $reportParamStart=strpos($front,'function paramsForReports()');
    $reportParamEnd=strpos($front,'function ', $reportParamStart+12);
    $reportParams=substr($front,$reportParamStart,$reportParamEnd-$reportParamStart);
    check9d4(strpos($reportParams,"p.set('limit','1000')")===false,'no 1000-limit in reports scope');
    $app=file_get_contents(dirname(__DIR__).'/src/Front/AppShortcodeRenderer.php');
    check9d4(strpos($app,"const lastReportVisitStorageKey='fieldflow:last-report-visit:v2:'+resumeScope")!==false,'scoped WebView fallback');
    check9d4(strpos($app,"localStorage.getItem('fieldflow:last-report-visit')")===false,'never read legacy unscoped visit');
    echo "COMMERCIAL REPORT PAGINATION 9D4 PHP PASS | 1001st visit | server total | stale request guard | scoped WebView\n";
}
