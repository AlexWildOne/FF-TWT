<?php
namespace RoutesPro\Integrations;

if (!defined('ABSPATH')) exit;

class WebhookService {
    private static bool $registered=false;
    public static function hooksTable():string{global$wpdb;return$wpdb->prefix.'routespro_webhooks';}
    public static function deliveriesTable():string{global$wpdb;return$wpdb->prefix.'routespro_webhook_deliveries';}
    public static function tablesReady():bool{global$wpdb;foreach([self::hooksTable(),self::deliveriesTable()]as$table)if((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table)return false;return true;}
    public static function eventCatalog():array{return['route.created'=>'Rota criada','route.updated'=>'Rota atualizada','route.completed'=>'Rota concluída','visit.started'=>'Visita iniciada','visit.completed'=>'Visita concluída','visit.failed'=>'Visita não realizada','submission.created'=>'Submissão criada','submission.updated'=>'Submissão atualizada','stock.imported'=>'Stock importado','briefing.published'=>'Briefing publicado','mapping.confirmed'=>'Mapping confirmado','system.alert_opened'=>'Alerta aberto'];}
    public static function registerHooks():void{
        if(self::$registered)return;self::$registered=true;
        add_action('fieldflow_core_event',[self::class,'queueEvent'],20,4);
        add_filter('cron_schedules',[self::class,'cronSchedules']);
        add_action('fieldflow_webhook_worker',[self::class,'processQueue']);
        if(!wp_next_scheduled('fieldflow_webhook_worker'))wp_schedule_event(time()+60,'fieldflow_minute','fieldflow_webhook_worker');
    }
    public static function cronSchedules(array$s):array{$s['fieldflow_minute']=['interval'=>60,'display'=>'FieldFlow a cada minuto'];return$s;}
    public static function create(array$input,int$actor=0):array{
        global$wpdb;if(!self::tablesReady())return['id'=>0,'secret'=>'','error'=>'As tabelas de webhooks ainda não estão disponíveis.'];$secret=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$events=array_values(array_intersect(array_keys(self::eventCatalog()),array_map('sanitize_text_field',(array)($input['events']??[]))));
        $clientId=absint($input['client_id']??0);$projectId=absint($input['project_id']??0);
        if($projectId){$projectClient=absint($wpdb->get_var($wpdb->prepare('SELECT client_id FROM '.$wpdb->prefix.'routespro_projects WHERE id=%d',$projectId)));if(!$projectClient)return['id'=>0,'secret'=>'','error'=>'Projeto inválido.'];if($clientId&&$clientId!==$projectClient)return['id'=>0,'secret'=>'','error'=>'O projeto não pertence ao cliente selecionado.'];$clientId=$projectClient;}
        if($clientId&&class_exists('RoutesPro\\SaaS\\EntitlementService')&&!\RoutesPro\SaaS\EntitlementService::clientHas($clientId,'webhooks.manage'))return['id'=>0,'secret'=>'','error'=>'Os webhooks não estão incluídos no plano deste cliente.'];
        if(!$events)return['id'=>0,'secret'=>'','error'=>'Seleciona pelo menos um evento.'];
        $webhookUrl=esc_url_raw((string)($input['url']??''),['https']);$webhookPolicy=\RoutesPro\Security\OutboundHttp::configuredPolicy($webhookUrl,'webhook_delivery',['max_timeout'=>30,'max_response_bytes'=>1048576]);$validatedUrl=\RoutesPro\Security\OutboundHttp::validate($webhookUrl,$webhookPolicy);if(is_wp_error($validatedUrl))return['id'=>0,'secret'=>'','error'=>'O webhook tem de usar HTTPS e um destino público autorizado.'];
        $data=['webhook_uuid'=>wp_generate_uuid4(),'name'=>sanitize_text_field((string)($input['name']??'Webhook')),'url'=>$validatedUrl,'secret_enc'=>SecretVault::encrypt($secret),'client_id'=>$clientId?:null,'project_id'=>$projectId?:null,'events_json'=>wp_json_encode($events),'status'=>'active','max_attempts'=>max(1,min(12,absint($input['max_attempts']??6))),'created_by'=>$actor?:get_current_user_id(),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')];
        $wpdb->insert(self::hooksTable(),$data);$id=(int)$wpdb->insert_id;if(!$id)return['id'=>0,'secret'=>'','error'=>'Não foi possível criar o webhook.'];
        if(class_exists('RoutesPro\\Core\\AuditLog'))\RoutesPro\Core\AuditLog::record('webhook.created','webhook',$id,null,array_merge($data,['secret_enc'=>'[redacted]']),['client_id'=>(int)($data['client_id']??0),'project_id'=>(int)($data['project_id']??0)]);
        return['id'=>$id,'secret'=>$secret,'uuid'=>$data['webhook_uuid']];
    }
    public static function queueEvent(string$eventName,array$payload,array$context,string$eventUuid):void{
        global$wpdb;if(!self::tablesReady())return;$hooks=$wpdb->get_results("SELECT * FROM ".self::hooksTable()." WHERE status='active'",ARRAY_A)?:[];
        foreach($hooks as$hook){$events=json_decode((string)$hook['events_json'],true);if(!is_array($events)||!in_array($eventName,$events,true))continue;$client=absint($context['client_id']??($payload['client_id']??0));$project=absint($context['project_id']??($payload['project_id']??0));if(!empty($hook['client_id'])&&(int)$hook['client_id']!==$client)continue;if(!empty($hook['project_id'])&&(int)$hook['project_id']!==$project)continue;
            $hookClient=absint($hook['client_id']??0);if($hookClient&&class_exists('RoutesPro\\SaaS\\EntitlementService')&&!\RoutesPro\SaaS\EntitlementService::clientHas($hookClient,'webhooks.manage'))continue;
            $envelope=['id'=>$eventUuid,'event'=>$eventName,'version'=>'1.0','occurred_at'=>current_time('mysql'),'context'=>$context,'data'=>$payload];
            $wpdb->query($wpdb->prepare("INSERT IGNORE INTO ".self::deliveriesTable()." (webhook_id,event_uuid,event_name,status,attempt_count,available_at,payload_json,created_at,updated_at) VALUES (%d,%s,%s,'pending',0,%s,%s,%s,%s)",(int)$hook['id'],$eventUuid,$eventName,current_time('mysql'),wp_json_encode($envelope,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),current_time('mysql'),current_time('mysql')));
        }
    }
    public static function processQueue(int$limit=20):array{
        global$wpdb;if(!self::tablesReady())return['processed'=>0,'delivered'=>0,'retry'=>0,'failed'=>0];$limit=max(1,min(100,$limit));$rows=$wpdb->get_results($wpdb->prepare("SELECT d.*,w.url,w.secret_enc,w.max_attempts,w.status AS webhook_status FROM ".self::deliveriesTable()." d INNER JOIN ".self::hooksTable()." w ON w.id=d.webhook_id WHERE d.status IN ('pending','retry') AND d.available_at<=%s AND w.status='active' ORDER BY d.id ASC LIMIT %d",current_time('mysql'),$limit),ARRAY_A)?:[];$summary=['processed'=>0,'delivered'=>0,'retry'=>0,'failed'=>0];
        foreach($rows as$row){$summary['processed']++;$result=self::deliver($row);$summary[$result]++;}return$summary;
    }
    public static function deliver(array$row):string{
        global$wpdb;$body=(string)$row['payload_json'];$secret=SecretVault::decrypt((string)$row['secret_enc']);$timestamp=(string)time();$signature=hash_hmac('sha256',$timestamp.'.'.$body,$secret);$attempt=(int)$row['attempt_count']+1;
        $webhookUrl=(string)$row['url'];$response=\RoutesPro\Security\OutboundHttp::post($webhookUrl,['timeout'=>20,'headers'=>['Content-Type'=>'application/json','User-Agent'=>'FieldFlow-Webhooks/'.FIELDFLOW_VERSION,'X-FieldFlow-Event'=>(string)$row['event_name'],'X-FieldFlow-Delivery'=>(string)$row['event_uuid'],'X-FieldFlow-Timestamp'=>$timestamp,'X-FieldFlow-Signature'=>'sha256='.$signature,'X-FieldFlow-Attempt'=>(string)$attempt],'body'=>$body],\RoutesPro\Security\OutboundHttp::configuredPolicy($webhookUrl,'webhook_delivery',['max_timeout'=>30,'max_response_bytes'=>1048576]));
        $now=current_time('mysql');if(is_wp_error($response)){$code=0;$responseBody='';$error=$response->get_error_message();$ok=false;}else{$code=(int)wp_remote_retrieve_response_code($response);$responseBody=substr((string)wp_remote_retrieve_body($response),0,2000);$error='';$ok=$code>=200&&$code<300;}
        if($ok){$wpdb->update(self::deliveriesTable(),['status'=>'delivered','attempt_count'=>$attempt,'response_code'=>$code,'response_body'=>$responseBody,'error_message'=>null,'delivered_at'=>$now,'updated_at'=>$now],['id'=>(int)$row['id']]);$wpdb->update(self::hooksTable(),['last_success_at'=>$now,'consecutive_failures'=>0,'updated_at'=>$now],['id'=>(int)$row['webhook_id']]);return'delivered';}
        $max=max(1,(int)$row['max_attempts']);$terminal=$attempt>=$max;$delay=min(3600,(int)pow(2,min(10,$attempt))*30);$wpdb->update(self::deliveriesTable(),['status'=>$terminal?'failed':'retry','attempt_count'=>$attempt,'response_code'=>$code?:null,'response_body'=>$responseBody,'error_message'=>substr($error?:('HTTP '.$code),0,1000),'available_at'=>wp_date('Y-m-d H:i:s',time()+$delay),'updated_at'=>$now],['id'=>(int)$row['id']]);$wpdb->query($wpdb->prepare("UPDATE ".self::hooksTable()." SET last_failure_at=%s,consecutive_failures=consecutive_failures+1,updated_at=%s WHERE id=%d",$now,$now,(int)$row['webhook_id']));
        $failures=(int)$wpdb->get_var($wpdb->prepare('SELECT consecutive_failures FROM '.self::hooksTable().' WHERE id=%d',(int)$row['webhook_id']));
        if($failures>=10)$wpdb->update(self::hooksTable(),['status'=>'paused','updated_at'=>$now],['id'=>(int)$row['webhook_id']]);
        if($terminal&&class_exists('RoutesPro\\Observability\\Monitor'))\RoutesPro\Observability\Monitor::record('webhook_failed','warning','Webhook sem entrega após várias tentativas.',['webhook_id'=>(int)$row['webhook_id'],'event'=>(string)$row['event_name']]);return$terminal?'failed':'retry';
    }
    public static function test(int$id):array{global$wpdb;$hook=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::hooksTable().' WHERE id=%d',$id),ARRAY_A);if(!$hook)return['ok'=>false,'message'=>'Webhook inexistente.'];$uuid=wp_generate_uuid4();$payload=['id'=>$uuid,'event'=>'fieldflow.test','version'=>'1.0','occurred_at'=>current_time('mysql'),'context'=>['test'=>true],'data'=>['message'=>'Teste de integração FieldFlow']];$wpdb->insert(self::deliveriesTable(),['webhook_id'=>$id,'event_uuid'=>$uuid,'event_name'=>'fieldflow.test','status'=>'pending','attempt_count'=>0,'available_at'=>current_time('mysql'),'payload_json'=>wp_json_encode($payload),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')]);$row=$wpdb->get_row($wpdb->prepare("SELECT d.*,w.url,w.secret_enc,w.max_attempts,w.status AS webhook_status FROM ".self::deliveriesTable()." d INNER JOIN ".self::hooksTable()." w ON w.id=d.webhook_id WHERE d.id=%d",(int)$wpdb->insert_id),ARRAY_A);$status=self::deliver($row);return['ok'=>$status==='delivered','message'=>$status];}
    public static function retry(int$id):bool{global$wpdb;return$wpdb->update(self::deliveriesTable(),['status'=>'retry','available_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')],['id'=>$id])!==false;}
    public static function setStatus(int$id,string$status):bool{global$wpdb;$status=in_array($status,['active','paused','revoked'],true)?$status:'paused';return$wpdb->update(self::hooksTable(),['status'=>$status,'updated_at'=>current_time('mysql')],['id'=>$id])!==false;}
    public static function hooks():array{global$wpdb;if(!self::tablesReady())return[];return$wpdb->get_results('SELECT * FROM '.self::hooksTable().' ORDER BY id DESC LIMIT 200',ARRAY_A)?:[];}
    public static function deliveries(int$limit=100):array{global$wpdb;if(!self::tablesReady())return[];return$wpdb->get_results('SELECT d.*,w.name AS webhook_name FROM '.self::deliveriesTable().' d LEFT JOIN '.self::hooksTable().' w ON w.id=d.webhook_id ORDER BY d.id DESC LIMIT '.max(1,min(500,$limit)),ARRAY_A)?:[];}
}
