<?php
namespace RoutesPro\Integrations;

use WP_Error;
use RoutesPro\Services\OperationalReportDayPolicy;

if (!defined('ABSPATH')) exit;

class PublicQueryService {
    public static function resourceCapabilities(): array {
        return [
            'clients'=>'api.clients.read','projects'=>'api.projects.read','locations'=>'api.locations.read','routes'=>'api.routes.read',
            'route-stops'=>'api.visits.read','submissions'=>'api.submissions.read','submission-answers'=>'api.submissions.read',
            'stocks'=>'api.stocks.read','briefings'=>'api.briefings.read','implementations'=>'api.implementations.read','analytics-execution'=>'api.analytics.read',
        ];
    }

    public static function schema(): array {
        return [
            'version'=>'v1', 'authentication'=>['header'=>'X-FieldFlow-Key','alternative'=>'Authorization: Bearer ffpk_...'],
            'pagination'=>['limit'=>'1-500','cursor'=>'último ID recebido','updated_since'=>'ISO 8601 ou Y-m-d H:i:s'],
            'resources'=>self::resourceCapabilities(),
            'power_bi'=>['execution','routes','visits','submissions','stocks'],
        ];
    }

    public static function fetch(string $resource, array $params, array $key) {
        $caps=self::resourceCapabilities();
        if (!isset($caps[$resource])) return new WP_Error('fieldflow_api_resource_invalid','Recurso inválido.',['status'=>404]);
        $auth=ApiKeyService::require($caps[$resource]); if (is_wp_error($auth)) return $auth;
        global $wpdb; $px=$wpdb->prefix.'routespro_';
        $limit=max(1,min(500,absint($params['limit']??100))); $cursor=max(0,absint($params['cursor']??0));
        $client=self::scopedClient($params,$key); if (is_wp_error($client)) return $client;
        $project=self::scopedProject($params,$key); if (is_wp_error($project)) return $project;
        $updated=self::dateTime((string)($params['updated_since']??''));
        $where=['1=1']; $args=[]; $sql=''; $idExpr='id'; $updatedExpr='updated_at';
        switch($resource){
            case 'clients':
                $sql="SELECT id,name,taxid,email,phone,created_at,updated_at FROM {$px}clients";
                if($client){$where[]='id=%d';$args[]=$client;} break;
            case 'projects':
                $sql="SELECT id,client_id,name,status,created_at,updated_at FROM {$px}projects";
                if($client){$where[]='client_id=%d';$args[]=$client;} if($project){$where[]='id=%d';$args[]=$project;} break;
            case 'locations':
                $sql="SELECT id,client_id,project_id,name,address,district,county,city,parish,postal_code,country,lat,lng,external_ref,source,is_active,is_validated,created_at,updated_at FROM {$px}locations";
                if($client){$where[]='client_id=%d';$args[]=$client;} if($project){$where[]='project_id=%d';$args[]=$project;} break;
            case 'routes':
                $sql="SELECT id,client_id,project_id,date,status,owner_user_id,created_at,updated_at FROM {$px}routes";
                if($client){$where[]='client_id=%d';$args[]=$client;} if($project){$where[]='project_id=%d';$args[]=$project;} break;
            case 'route-stops':
                $sql="SELECT s.id,s.route_id,s.location_id,s.seq,s.planned_arrival,s.planned_departure,s.arrived_at,s.departed_at,s.duration_s,s.status,s.fail_reason,s.qty,s.real_lat,s.real_lng,r.client_id,r.project_id,r.date,l.name AS location_name FROM {$px}route_stops s INNER JOIN {$px}routes r ON r.id=s.route_id LEFT JOIN {$px}locations l ON l.id=s.location_id";
                $idExpr='s.id'; $updatedExpr='COALESCE(s.departed_at,s.arrived_at,r.updated_at,r.created_at)';
                if($client){$where[]='r.client_id=%d';$args[]=$client;} if($project){$where[]='r.project_id=%d';$args[]=$project;} break;
            case 'submissions':
                $sql="SELECT s.id,s.form_id,s.user_id,s.client_id,s.project_id,s.route_id,s.route_stop_id,s.binding_id,s.report_day,s.submitted_at,s.status FROM {$px}form_submissions s";
                $idExpr='s.id';$updatedExpr='s.submitted_at'; if($client){$where[]='s.client_id=%d';$args[]=$client;} if($project){$where[]='s.project_id=%d';$args[]=$project;} break;
            case 'submission-answers':
                $sql="SELECT a.id,a.submission_id,a.question_key,a.question_label,a.value_text,a.value_number,a.value_json,a.created_at,s.client_id,s.project_id FROM {$px}form_submission_answers a INNER JOIN {$px}form_submissions s ON s.id=a.submission_id";
                $idExpr='a.id';$updatedExpr='a.created_at'; if($client){$where[]='s.client_id=%d';$args[]=$client;} if($project){$where[]='s.project_id=%d';$args[]=$project;} break;
            case 'stocks':
                $sql="SELECT i.id,i.batch_id,i.client_id,i.project_id,i.location_id,i.store_external_ref,i.store_name,i.locality,i.article_ref,i.ean,i.product_name,i.unit,i.stock_total,i.created_at,b.snapshot_date,b.insignia,b.status AS batch_status FROM {$px}stock_items i INNER JOIN {$px}stock_batches b ON b.id=i.batch_id";
                $idExpr='i.id';$updatedExpr='i.created_at'; if($client){$where[]='i.client_id=%d';$args[]=$client;} if($project){$where[]='i.project_id=%d';$args[]=$project;} break;
            case 'briefings':
                $sql="SELECT id,client_id,project_id,title,year_no,week_no,valid_from,valid_to,status,version_no,published_at,created_at,updated_at FROM {$px}briefings";
                if($client){$where[]='client_id=%d';$args[]=$client;} if($project){$where[]='project_id=%d';$args[]=$project;} break;
            case 'implementations':
                $sql="SELECT id,client_id,project_id,title,status,version_no,valid_from,valid_to,published_at,created_at,updated_at FROM {$px}implementation_documents";
                if($client){$where[]='client_id=%d';$args[]=$client;} if($project){$where[]='project_id=%d';$args[]=$project;} break;
            case 'analytics-execution': return self::executionAnalytics($client,$project,$params);
        }
        if($cursor){$where[]=$idExpr.'>%d';$args[]=$cursor;}
        if($updated){$where[]=$updatedExpr.'>=%s';$args[]=$updated;}
        foreach(['date_from'=>'>=','date_to'=>'<='] as $keyName=>$op){$d=self::date((string)($params[$keyName]??''));if($d){
            if($resource==='routes'){$where[]="date {$op} %s";$args[]=$d;}
            elseif($resource==='route-stops'){$where[]="r.date {$op} %s";$args[]=$d;}
            elseif(in_array($resource,['submissions','submission-answers'],true)){$where[]=OperationalReportDayPolicy::sqlExpression('s') . " {$op} %s";$args[]=$d;}
            elseif($resource==='stocks'){$where[]="b.snapshot_date {$op} %s";$args[]=$d;}
        }}
        $query=$sql.' WHERE '.implode(' AND ',$where).' ORDER BY '.$idExpr.' ASC LIMIT %d';$args[]=$limit+1;
        $rows=$wpdb->get_results($wpdb->prepare($query,...$args),ARRAY_A)?:[];$hasMore=count($rows)>$limit;if($hasMore)array_pop($rows);
        foreach($rows as &$row) foreach(['value_json'] as $field) if(isset($row[$field])&&is_string($row[$field])){$d=json_decode($row[$field],true);if(json_last_error()===JSON_ERROR_NONE)$row[$field]=$d;}
        $next=$rows?(int)end($rows)['id']:null;
        return ['resource'=>$resource,'items'=>$rows,'count'=>count($rows),'has_more'=>$hasMore,'next_cursor'=>$hasMore?$next:null,'generated_at'=>current_time('mysql'),'scope'=>['client_id'=>$client,'project_id'=>$project]];
    }

    private static function executionAnalytics(int $client,int $project,array $params): array {
        global $wpdb;$px=$wpdb->prefix.'routespro_';$from=self::date((string)($params['date_from']??''))?:wp_date('Y-m-01');$to=self::date((string)($params['date_to']??''))?:wp_date('Y-m-d');
        $where=['r.date BETWEEN %s AND %s'];$args=[$from,$to];if($client){$where[]='r.client_id=%d';$args[]=$client;}if($project){$where[]='r.project_id=%d';$args[]=$project;}
        $sql="SELECT r.date,r.client_id,r.project_id,COUNT(DISTINCT r.id) AS routes_total,SUM(CASE WHEN r.status IN ('completed','done') THEN 1 ELSE 0 END) AS routes_completed,COUNT(s.id) AS visits_total,SUM(CASE WHEN s.status IN ('completed','done','visited') THEN 1 ELSE 0 END) AS visits_completed,SUM(COALESCE(s.duration_s,0)) AS visit_duration_seconds FROM {$px}routes r LEFT JOIN {$px}route_stops s ON s.route_id=r.id WHERE ".implode(' AND ',$where)." GROUP BY r.date,r.client_id,r.project_id ORDER BY r.date ASC";
        $rows=$wpdb->get_results($wpdb->prepare($sql,...$args),ARRAY_A)?:[];foreach($rows as &$r){$total=(int)$r['visits_total'];$done=(int)$r['visits_completed'];$r['execution_rate']=$total?round(($done/$total)*100,2):0;}
        return ['resource'=>'analytics-execution','items'=>$rows,'count'=>count($rows),'has_more'=>false,'next_cursor'=>null,'generated_at'=>current_time('mysql'),'scope'=>['client_id'=>$client,'project_id'=>$project],'period'=>['from'=>$from,'to'=>$to]];
    }
    private static function scopedClient(array $p,array $k){$fixed=absint($k['client_id']??0);$asked=absint($p['client_id']??0);if($fixed&&$asked&&$fixed!==$asked)return new WP_Error('fieldflow_api_scope_client','Cliente fora do âmbito da chave.',['status'=>403]);return $fixed?:$asked;}
    private static function scopedProject(array $p,array $k){$fixed=absint($k['project_id']??0);$asked=absint($p['project_id']??0);if($fixed&&$asked&&$fixed!==$asked)return new WP_Error('fieldflow_api_scope_project','Projeto fora do âmbito da chave.',['status'=>403]);return $fixed?:$asked;}
    private static function date(string $v):string{return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:'';}
    private static function dateTime(string $v):string{$v=trim($v);if($v==='')return'';$ts=strtotime($v);return $ts?gmdate('Y-m-d H:i:s',$ts):'';}
}
