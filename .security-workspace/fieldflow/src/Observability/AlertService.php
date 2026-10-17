<?php
namespace RoutesPro\Observability;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\Security\Settings;

if (!defined('ABSPATH')) exit;

class AlertService {
    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_system_alerts';
    }

    public static function fromObservation(int $observationId, string $type, string $title, array $payload, string $severity): int {
        return self::upsert('observability:' . $type, $severity, $title, 'Detetado automaticamente pelo motor de observabilidade.', $payload, $observationId);
    }

    public static function upsert(string $alertType, string $severity, string $title, string $detail, array $payload = [], int $sourceId = 0): int {
        global $wpdb;
        $table=self::tableName();
        if((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) return 0;
        $fingerprint=hash('sha256',$alertType.'|'.$title.'|'.($payload['route']??'').'|'.($payload['device_id']??''));
        $now=current_time('mysql',true);
        $existing=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE fingerprint=%s LIMIT 1",$fingerprint));
        if($existing){
            $wpdb->update($table,['severity'=>$severity,'status'=>'open','title'=>substr(wp_strip_all_tags($title),0,255),'detail'=>substr(wp_strip_all_tags($detail),0,1000),'source_id'=>$sourceId,'payload_json'=>wp_json_encode($payload),'last_seen_at'=>$now,'updated_at'=>$now],['id'=>$existing]);
            return $existing;
        }
        $wpdb->insert($table,['alert_uuid'=>wp_generate_uuid4(),'fingerprint'=>$fingerprint,'alert_type'=>substr(sanitize_key(str_replace(':','_',$alertType)),0,96),'severity'=>$severity,'status'=>'open','title'=>substr(wp_strip_all_tags($title),0,255),'detail'=>substr(wp_strip_all_tags($detail),0,1000),'source_id'=>$sourceId,'payload_json'=>wp_json_encode($payload),'first_seen_at'=>$now,'last_seen_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        $id=(int)$wpdb->insert_id;
        if($id>0){
            EventBus::emit('system.alert_opened',['alert_id'=>$id,'alert_type'=>$alertType,'severity'=>$severity],['entity_type'=>'system_alert','entity_id'=>$id],'observability');
            self::maybeEmail($id,$title,$detail,$severity);
        }
        return $id;
    }

    public static function recent(int $limit=50,string $status='open'): array {
        global $wpdb; $table=self::tableName();
        if((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) return [];
        $limit=max(1,min(250,$limit));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status=%s ORDER BY last_seen_at DESC LIMIT %d",sanitize_key($status),$limit),ARRAY_A)?:[];
    }

    public static function setStatus(int $id,string $status): bool {
        global $wpdb; $status=in_array($status,['open','acknowledged','resolved'],true)?$status:'acknowledged';
        $before=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::tableName().' WHERE id=%d',$id),ARRAY_A);
        if(!$before) return false;
        $ok=$wpdb->update(self::tableName(),['status'=>$status,'acknowledged_by'=>get_current_user_id()?:null,'acknowledged_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)],['id'=>$id])!==false;
        if($ok) AuditLog::record('system_alert.'.$status,'system_alert',$id,$before,['status'=>$status],['source'=>'backoffice']);
        return $ok;
    }

    public static function counts(): array {
        global $wpdb; $table=self::tableName();
        if((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$table))!==$table) return ['open'=>0,'critical'=>0,'warning'=>0];
        $row=$wpdb->get_row("SELECT SUM(status='open') open,SUM(status='open' AND severity='critical') critical,SUM(status='open' AND severity='warning') warning FROM {$table}",ARRAY_A)?:[];
        return ['open'=>(int)($row['open']??0),'critical'=>(int)($row['critical']??0),'warning'=>(int)($row['warning']??0)];
    }

    private static function maybeEmail(int $id,string $title,string $detail,string $severity): void {
        $settings=Settings::get()['alerts'];
        if(empty($settings['email_enabled'])||empty($settings['admin_email'])) return;
        $key='ff_alert_mail_'.md5($title.'|'.$severity);
        if(get_transient($key)) return;
        set_transient($key,1,(int)$settings['cooldown_minutes']*MINUTE_IN_SECONDS);
        \RoutesPro\Services\MailDispatchService::send((string)$settings['admin_email'],'[FieldFlow] '.strtoupper($severity).' · '.$title,"Alerta #{$id}\n\n{$detail}\n\nConsulta FieldFlow > Segurança e Observabilidade.", [], [], ['source'=>'observability_alert','alert_id'=>$id,'severity'=>$severity]);
    }
}
