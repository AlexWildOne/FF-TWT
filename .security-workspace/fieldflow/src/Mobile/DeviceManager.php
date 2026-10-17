<?php
namespace RoutesPro\Mobile;

use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

class DeviceManager {
    public static function tableName(): string {
        global $wpdb;
        return $wpdb->prefix . 'routespro_mobile_device_controls';
    }

    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_device_action', [self::class, 'handleAdminAction']);
        add_action('wp_ajax_fieldflow_react_devices_list', [self::class, 'ajaxListDevices']);
        add_action('wp_ajax_fieldflow_react_device_action', [self::class, 'ajaxDeviceAction']);
    }

    public static function listDevices(int $limit = 250, int $offset = 0, string $search = '', array $filters = []): array {
        global $wpdb;
        $sessions = Auth::table();
        $sync = OfflineSyncService::deviceTable();
        $controls = self::tableName();
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $search = trim(sanitize_text_field($search));
        $platform = sanitize_key((string)($filters['platform'] ?? ''));
        if (!in_array($platform, ['', 'android', 'ios', 'web'], true)) $platform = '';
        $where = '1=1';
        $args = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (s.device_id LIKE %s OR s.device_name LIKE %s OR s.platform LIKE %s OR s.app_version LIKE %s OR u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)';
            $args = array_fill(0, 7, $like);
        }
        if ($platform !== '') {
            $where .= ' AND s.platform=%s';
            $args[] = $platform;
        }
        $sql = "SELECT s.id session_id,s.user_id,s.device_id,s.device_name,s.platform,s.app_version,s.refresh_expires_at,s.revoked_at,s.last_seen_at session_seen_at,s.last_ip,s.user_agent,
                d.app_version sync_app_version,d.queue_pending,d.queue_failed,d.last_cache_at,d.last_sync_at,d.last_error,d.last_seen_at sync_seen_at,d.meta_json sync_meta_json,
                c.is_trusted,c.offline_allowed,c.cache_generation,c.force_logout,c.notes,c.updated_at control_updated_at,
                u.display_name,u.user_login,u.user_email
            FROM {$sessions} s
            LEFT JOIN {$sync} d ON d.user_id=s.user_id AND d.device_id=s.device_id
            LEFT JOIN {$controls} c ON c.user_id=s.user_id AND c.device_id=s.device_id
            LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id
            WHERE {$where}
            ORDER BY COALESCE(s.last_seen_at,d.last_seen_at,s.created_at) DESC
            LIMIT {$limit} OFFSET {$offset}";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $meta = json_decode((string)($row['sync_meta_json'] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $conflicts = array_key_exists('queue_conflicts', $meta) ? max(0, (int)$meta['queue_conflicts']) : 0;
            $row['queue_pending'] = array_key_exists('queue_pending', $meta) ? max(0, (int)$meta['queue_pending']) : max(0, (int)($row['queue_pending'] ?? 0));
            $row['queue_failed'] = array_key_exists('queue_failed', $meta) ? max(0, (int)$meta['queue_failed']) : max(0, (int)($row['queue_failed'] ?? 0) - $conflicts);
            $row['queue_conflicts'] = $conflicts;
            if (!empty($row['sync_app_version'])) $row['app_version'] = (string)$row['sync_app_version'];
            self::decorateOperationsIntelligence($row, $meta);
        }
        unset($row);
        return $rows;
    }

    public static function countDevices(string $search = '', array $filters = []): int {
        global $wpdb;
        $sessions = Auth::table();
        $search = trim(sanitize_text_field($search));
        $platform = sanitize_key((string)($filters['platform'] ?? ''));
        if (!in_array($platform, ['', 'android', 'ios', 'web'], true)) $platform = '';
        $where = '1=1';
        $args = [];
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (s.device_id LIKE %s OR s.device_name LIKE %s OR s.platform LIKE %s OR s.app_version LIKE %s OR u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)';
            $args = array_fill(0, 7, $like);
        }
        if ($platform !== '') {
            $where .= ' AND s.platform=%s';
            $args[] = $platform;
        }
        $sql = "SELECT COUNT(*) FROM {$sessions} s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE {$where}";
        if ($args) $sql = $wpdb->prepare($sql, ...$args);
        return max(0, (int)$wpdb->get_var($sql));
    }

    public static function listDevicesPage(int $page = 1, int $perPage = 25, string $search = '', array $filters = []): array {
        $page = max(1, $page);
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 25;
        $platform = sanitize_key((string)($filters['platform'] ?? ''));
        if (!in_array($platform, ['', 'android', 'ios', 'web'], true)) $platform = '';
        $filters['platform'] = $platform;
        $total = self::countDevices($search, $filters);
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $perPage;
        return [
            'rows' => self::listDevices($perPage, $offset, $search, $filters),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'search' => $search,
            'platform' => $platform,
        ];
    }

    public static function policy(int $userId, string $deviceId): array {
        global $wpdb;
        $deviceId = self::sanitizeDeviceId($deviceId);
        $defaults = ['is_trusted'=>0,'offline_allowed'=>1,'cache_generation'=>1,'force_logout'=>0,'notes'=>''];
        if ($userId <= 0 || $deviceId === '') return $defaults;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::tableName().' WHERE user_id=%d AND device_id=%s LIMIT 1',$userId,$deviceId),ARRAY_A);
        if (!$row) return $defaults;
        return [
            'is_trusted'=>(int)($row['is_trusted']??0),
            'offline_allowed'=>(int)($row['offline_allowed']??1),
            'cache_generation'=>max(1,(int)($row['cache_generation']??1)),
            'force_logout'=>(int)($row['force_logout']??0),
            'notes'=>(string)($row['notes']??''),
            'updated_at'=>(string)($row['updated_at']??''),
        ];
    }

    public static function currentPolicy(WP_REST_Request $request): array {
        $session=Auth::current_session();
        $body=$request->get_json_params()?:[];
        $deviceId=self::sanitizeDeviceId((string)($request->get_header('x-fieldflow-device-id') ?: ($body['device_id']??'') ?: ($session['device_id']??'')));
        $policy=self::policy(get_current_user_id(),$deviceId);
        $policy['device_id']=$deviceId;
        $policy['blocked']=Settings::is_device_blocked($deviceId);
        return $policy;
    }

    public static function restPolicy(WP_REST_Request $request) {
        if(!is_user_logged_in()) return new WP_Error('fieldflow_device_auth_required','Authentication required.',['status'=>401]);
        $policy=self::currentPolicy($request);
        if(!empty($policy['force_logout'])||!empty($policy['blocked'])){
            Auth::revoke_by_device((string)$policy['device_id']);
            WebViewBridge::revoke_all_for_user(get_current_user_id());
            if(!empty($policy['force_logout'])&&!empty($policy['device_id'])) self::setControl(get_current_user_id(),(string)$policy['device_id'],['force_logout'=>0]);
        }
        return new WP_REST_Response(['policy'=>$policy],200);
    }

    public static function assertOfflineAllowed(WP_REST_Request $request) {
        $policy=self::currentPolicy($request);
        if(!empty($policy['blocked'])||!empty($policy['force_logout'])) return new WP_Error('fieldflow_device_revoked','O acesso deste dispositivo foi revogado.',['status'=>403]);
        if(empty($policy['offline_allowed'])) return new WP_Error('fieldflow_device_offline_disabled','A sincronização offline foi desativada para este dispositivo.',['status'=>403]);
        return $policy;
    }

    public static function setControl(int $userId,string $deviceId,array $changes): bool {
        global $wpdb;
        $deviceId=self::sanitizeDeviceId($deviceId);
        if($userId<=0||$deviceId==='') return false;
        $current=self::policy($userId,$deviceId);
        $data=[
            'user_id'=>$userId,
            'device_id'=>$deviceId,
            'is_trusted'=>array_key_exists('is_trusted',$changes)?(empty($changes['is_trusted'])?0:1):(int)$current['is_trusted'],
            'offline_allowed'=>array_key_exists('offline_allowed',$changes)?(empty($changes['offline_allowed'])?0:1):(int)$current['offline_allowed'],
            'cache_generation'=>array_key_exists('cache_generation',$changes)?max(1,absint($changes['cache_generation'])):(int)$current['cache_generation'],
            'force_logout'=>array_key_exists('force_logout',$changes)?(empty($changes['force_logout'])?0:1):(int)$current['force_logout'],
            'notes'=>array_key_exists('notes',$changes)?sanitize_textarea_field((string)$changes['notes']):(string)$current['notes'],
            'updated_by'=>get_current_user_id()?:null,
            'updated_at'=>current_time('mysql',true),
        ];
        $exists=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.self::tableName().' WHERE user_id=%d AND device_id=%s',$userId,$deviceId));
        if($exists) return $wpdb->update(self::tableName(),$data,['id'=>$exists])!==false;
        $data['created_at']=current_time('mysql',true);
        return $wpdb->insert(self::tableName(),$data)!==false;
    }

    public static function performAdminAction(string $action, int $userId, string $deviceId, int $sessionId = 0): array {
        $action=sanitize_key($action);
        $userId=absint($userId);
        $deviceId=self::sanitizeDeviceId($deviceId);
        $sessionId=absint($sessionId);
        $allowed=['trust','untrust','disable_offline','enable_offline','clear_cache','force_logout','block','unblock'];
        if(!in_array($action,$allowed,true)||$userId<=0||$deviceId==='') return ['ok'=>false,'action'=>$action];
        $before=self::policy($userId,$deviceId);
        $ok=false;
        switch($action){
            case 'trust': $ok=self::setControl($userId,$deviceId,['is_trusted'=>1]); break;
            case 'untrust': $ok=self::setControl($userId,$deviceId,['is_trusted'=>0]); break;
            case 'disable_offline': $ok=self::setControl($userId,$deviceId,['offline_allowed'=>0]); break;
            case 'enable_offline': $ok=self::setControl($userId,$deviceId,['offline_allowed'=>1]); break;
            case 'clear_cache': $ok=self::setControl($userId,$deviceId,['cache_generation'=>(int)$before['cache_generation']+1]); break;
            case 'force_logout':
                $ok=self::setControl($userId,$deviceId,['force_logout'=>1,'cache_generation'=>(int)$before['cache_generation']+1]);
                if($sessionId) Auth::revoke_by_id($sessionId); else Auth::revoke_by_device($deviceId);
                WebViewBridge::revoke_all_for_user($userId);
                break;
            case 'block':
                $ok=Settings::block_device($deviceId,get_current_user_id());
                Auth::revoke_by_device($deviceId);
                WebViewBridge::revoke_all_for_user($userId);
                break;
            case 'unblock': $ok=Settings::unblock_device($deviceId); if($ok) self::setControl($userId,$deviceId,['force_logout'=>0]); break;
        }
        $after=self::policy($userId,$deviceId);
        AuditLog::record('device.'.$action,'device',$sessionId,$before,$after,['source'=>'backoffice','actor_user_id'=>get_current_user_id()],['device_id'=>$deviceId,'ok'=>$ok]);
        EventBus::emit('device.'.$action,['device_id'=>$deviceId,'user_id'=>$userId,'ok'=>$ok],['entity_type'=>'device','entity_id'=>$sessionId,'actor_user_id'=>get_current_user_id()],'backoffice');
        return ['ok'=>$ok,'action'=>$action,'policy'=>$after];
    }

    public static function handleAdminAction(): void {
        if(!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_device_action');
        $action=sanitize_key((string)($_POST['device_action']??''));
        $userId=absint($_POST['user_id']??0);
        $deviceId=self::sanitizeDeviceId((string)($_POST['device_id']??''));
        $sessionId=absint($_POST['session_id']??0);
        $result=self::performAdminAction($action,$userId,$deviceId,$sessionId);
        $ok=!empty($result['ok']);
        $redirectArgs=['page'=>'routespro-devices','updated'=>$ok?1:0];
        $returnPage=max(1,absint($_POST['return_paged']??1));
        $returnPerPage=absint($_POST['return_per_page']??25);
        $returnPerPage=in_array($returnPerPage,[25,50,100],true)?$returnPerPage:25;
        $returnSearch=sanitize_text_field(wp_unslash((string)($_POST['return_search']??'')));
        $returnPlatform=sanitize_key((string)($_POST['return_platform']??''));
        if($returnPage>1)$redirectArgs['ffdev_paged']=$returnPage;
        if($returnPerPage!==25)$redirectArgs['ffdev_per_page']=$returnPerPage;
        if($returnSearch!=='')$redirectArgs['ffdev_search']=$returnSearch;
        if(in_array($returnPlatform,['android','ios','web'],true))$redirectArgs['ffdev_platform']=$returnPlatform;
        wp_safe_redirect(add_query_arg($redirectArgs,admin_url('admin.php')));
        exit;
    }

    public static function ajaxListDevices(): void {
        if(!current_user_can('routespro_manage')) wp_send_json_error(['message'=>'Sem permissões.'],403);
        check_ajax_referer('fieldflow_react_devices','nonce');
        $page=max(1,absint($_POST['page']??1));
        $perPage=absint($_POST['per_page']??25);
        $perPage=in_array($perPage,[25,50,100],true)?$perPage:25;
        $search=sanitize_text_field(wp_unslash((string)($_POST['search']??'')));
        $platform=sanitize_key((string)($_POST['platform']??''));
        $listing=self::listDevicesPage($page,$perPage,$search,['platform'=>$platform]);
        wp_send_json_success(self::adminListPayload($listing));
    }

    public static function ajaxDeviceAction(): void {
        if(!current_user_can('routespro_manage')) wp_send_json_error(['message'=>'Sem permissões.'],403);
        check_ajax_referer('fieldflow_react_devices','nonce');
        $result=self::performAdminAction(
            sanitize_key((string)($_POST['device_action']??'')),
            absint($_POST['user_id']??0),
            self::sanitizeDeviceId((string)($_POST['device_id']??'')),
            absint($_POST['session_id']??0)
        );
        if(empty($result['ok'])) wp_send_json_error(['message'=>'Não foi possível atualizar o dispositivo.','result'=>$result],400);
        wp_send_json_success(['message'=>'Dispositivo atualizado.','result'=>$result]);
    }

    public static function adminListPayload(array $listing): array {
        $blocked=Settings::blocked_devices();
        $rows=[];
        foreach((array)($listing['rows']??[]) as $row){
            $deviceId=(string)($row['device_id']??'');
            $isBlocked=isset($blocked[$deviceId]);
            $isActive=empty($row['revoked_at'])&&!$isBlocked&&strtotime((string)($row['refresh_expires_at']??''))>time();
            $rows[]=[
                'sessionId'=>(int)($row['session_id']??0),
                'userId'=>(int)($row['user_id']??0),
                'deviceId'=>$deviceId,
                'deviceName'=>(string)($row['device_name']?:'Dispositivo'),
                'displayName'=>(string)($row['display_name']?:$row['user_login']?:('#'.(int)($row['user_id']??0))),
                'email'=>(string)($row['user_email']??''),
                'platform'=>(string)($row['platform']?:'n/d'),
                'appVersion'=>(string)($row['app_version']?:'n/d'),
                'runtimeBuild'=>(string)($row['runtime_build']?:'n/d'),
                'lastSeen'=>(string)($row['session_seen_at']?:$row['sync_seen_at']?:''),
                'lastSync'=>(string)($row['last_sync_at']?:$row['sync_seen_at']?:''),
                'active'=>$isActive,
                'blocked'=>$isBlocked,
                'trusted'=>!empty($row['is_trusted']),
                'offlineAllowed'=>isset($row['offline_allowed'])?(bool)$row['offline_allowed']:true,
                'cacheGeneration'=>max(1,(int)($row['cache_generation']??1)),
                'healthStatus'=>(string)($row['offline_health_status']??'watch'),
                'healthScore'=>(int)($row['offline_health_score']??0),
                'networkState'=>(string)($row['network_state']??'unknown'),
                'queuePending'=>(int)($row['queue_pending']??0),
                'queueFailed'=>(int)($row['queue_failed']??0),
                'queueConflicts'=>(int)($row['queue_conflicts']??0),
                'ackErrors'=>(int)($row['ack_error_count']??0),
                'recoveries'=>(int)($row['recovery_count']??0),
                'appResumes'=>(int)($row['app_resume_count']??0),
                'oldestPendingAgeSeconds'=>(int)($row['oldest_pending_age_seconds']??0),
                'storagePercent'=>(int)($row['storage_percent']??0),
                'storagePersistence'=>(string)($row['storage_persistence']??'unknown'),
                'mediaPendingFiles'=>(int)($row['media_pending_files']??0),
                'mediaPendingBytes'=>(int)($row['media_pending_bytes']??0),
                'trustLeaseState'=>(string)($row['trust_lease_state']??'missing'),
                'trustLeaseRelease'=>(string)($row['trust_lease_release']??''),
                'trustLeaseExpiresAtMs'=>(int)($row['trust_lease_expires_at_ms']??0),
            ];
        }
        return [
            'rows'=>$rows,
            'total'=>(int)($listing['total']??0),
            'page'=>(int)($listing['page']??1),
            'perPage'=>(int)($listing['per_page']??25),
            'pages'=>(int)($listing['pages']??1),
            'search'=>(string)($listing['search']??''),
            'platform'=>(string)($listing['platform']??''),
        ];
    }

    public static function diagnosticSnapshot(): array {
        $rows=self::listDevices(100);
        return array_map(static function($row){
            return [
                'user_id'=>(int)$row['user_id'],
                'device_id_hash'=>hash('sha256',(string)$row['device_id']),
                'platform'=>(string)$row['platform'],
                'app_version'=>(string)$row['app_version'],
                'last_seen_at'=>(string)($row['session_seen_at']?:$row['sync_seen_at']),
                'last_sync_at'=>(string)$row['last_sync_at'],
                'queue_pending'=>(int)$row['queue_pending'],
                'queue_failed'=>(int)$row['queue_failed'],
                'queue_conflicts'=>(int)($row['queue_conflicts'] ?? 0),
                'offline_health_score'=>(int)($row['offline_health_score'] ?? 0),
                'offline_health_status'=>(string)($row['offline_health_status'] ?? ''),
                'network_state'=>(string)($row['network_state'] ?? ''),
                'oldest_pending_age_seconds'=>(int)($row['oldest_pending_age_seconds'] ?? 0),
                'ack_error_count'=>(int)($row['ack_error_count'] ?? 0),
                'storage_percent'=>(int)($row['storage_percent'] ?? 0),
                'media_pending_files'=>(int)($row['media_pending_files'] ?? 0),
                'media_pending_bytes'=>(int)($row['media_pending_bytes'] ?? 0),
                'trust_lease_state'=>(string)($row['trust_lease_state'] ?? ''),
                'trust_lease_release'=>(string)($row['trust_lease_release'] ?? ''),
                'trust_lease_expires_at_ms'=>(int)($row['trust_lease_expires_at_ms'] ?? 0),
                'trusted'=>(int)$row['is_trusted'],
                'offline_allowed'=>isset($row['offline_allowed'])?(int)$row['offline_allowed']:1,
                'revoked'=>empty($row['revoked_at'])?0:1,
                'blocked'=>Settings::is_device_blocked((string)$row['device_id'])?1:0,
            ];
        },$rows);
    }

    private static function decorateOperationsIntelligence(array &$row, array $meta): void {
        $row['runtime_build'] = sanitize_text_field((string)($meta['runtime_build'] ?? ''));
        $row['network_state'] = sanitize_key((string)($meta['network_state'] ?? 'unknown'));
        $row['probe_latency_ms'] = max(0, (int)($meta['probe_latency_ms'] ?? 0));
        $row['oldest_pending_at_ms'] = max(0, (int)($meta['oldest_pending_at_ms'] ?? 0));
        $row['oldest_pending_age_seconds'] = max(0, (int)floor(max(0, (int)($meta['oldest_pending_age_ms'] ?? 0)) / 1000));
        $row['max_queue_attempts'] = max(0, (int)($meta['max_queue_attempts'] ?? 0));
        $row['ack_error_count'] = max(0, (int)($meta['ack_error_count'] ?? 0));
        $row['last_ack_error_at_ms'] = max(0, (int)($meta['last_ack_error_at_ms'] ?? 0));
        $row['degraded_total_ms'] = max(0, (int)($meta['degraded_total_ms'] ?? 0));
        $row['degraded_events'] = max(0, (int)($meta['degraded_events'] ?? 0));
        $row['auth_required_count'] = max(0, (int)($meta['auth_required_count'] ?? 0));
        $row['app_resume_count'] = max(0, (int)($meta['app_resume_count'] ?? 0));
        $row['recovery_count'] = max(0, (int)($meta['recovery_count'] ?? 0));
        $row['last_recovery_at_ms'] = max(0, (int)($meta['last_recovery_at_ms'] ?? 0));
        $row['sync_confirmed_count'] = max(0, (int)($meta['sync_confirmed_count'] ?? 0));
        $row['last_confirmed_at_ms'] = max(0, (int)($meta['last_confirmed_at_ms'] ?? 0));
        $row['storage_usage'] = max(0, (int)($meta['storage_usage'] ?? 0));
        $row['storage_quota'] = max(0, (int)($meta['storage_quota'] ?? 0));
        $row['storage_persistence'] = sanitize_key((string)($meta['storage_persistence'] ?? 'unknown'));
        $row['media_pending_files'] = max(0, (int)($meta['media_pending_files'] ?? 0));
        $row['media_pending_bytes'] = max(0, (int)($meta['media_pending_bytes'] ?? 0));
        $row['resumable_uploaded_chunks'] = max(0, (int)($meta['resumable_uploaded_chunks'] ?? 0));
        $row['resumable_resumed_chunks'] = max(0, (int)($meta['resumable_resumed_chunks'] ?? 0));
        $row['resumable_completed_files'] = max(0, (int)($meta['resumable_completed_files'] ?? 0));
        $row['trust_lease_state'] = sanitize_key((string)($meta['trust_lease_state'] ?? 'missing'));
        $row['trust_lease_release'] = sanitize_text_field((string)($meta['trust_lease_release'] ?? ''));
        $row['trust_lease_certified_at'] = sanitize_text_field((string)($meta['trust_lease_certified_at'] ?? ''));
        $row['trust_lease_expires_at_ms'] = max(0, (int)($meta['trust_lease_expires_at_ms'] ?? 0));
        $row['storage_percent'] = $row['storage_quota'] > 0 ? min(100, (int)round(($row['storage_usage'] / $row['storage_quota']) * 100)) : 0;
        $row['sync_history'] = is_array($meta['sync_history'] ?? null) ? array_slice($meta['sync_history'], 0, 8) : [];

        $score = 100;
        $pending = max(0, (int)($row['queue_pending'] ?? 0));
        $failed = max(0, (int)($row['queue_failed'] ?? 0));
        $conflicts = max(0, (int)($row['queue_conflicts'] ?? 0));
        if ($failed > 0) $score -= min(35, 15 + ($failed * 5));
        if ($conflicts > 0) $score -= min(30, 15 + ($conflicts * 5));
        if ($pending > 0 && $row['oldest_pending_age_seconds'] > 2 * HOUR_IN_SECONDS) $score -= 20;
        elseif ($pending > 0 && $row['oldest_pending_age_seconds'] > 30 * MINUTE_IN_SECONDS) $score -= 10;
        if ($row['ack_error_count'] > 0) $score -= min(20, 8 + $row['ack_error_count']);
        if ($row['network_state'] === 'auth_required' || $row['network_state'] === 'revoked') $score -= 30;
        elseif ($row['network_state'] === 'degraded') $score -= 8;
        if ($row['storage_percent'] >= 90) $score -= 20; elseif ($row['storage_percent'] >= 80) $score -= 10;
        if (defined('FIELDFLOW_VERSION') && $row['runtime_build'] !== '' && $row['runtime_build'] !== FIELDFLOW_VERSION) $score -= 12;
        $score = max(0, min(100, $score));
        $row['offline_health_score'] = $score;
        $row['offline_health_status'] = $score >= 85 ? 'healthy' : ($score >= 65 ? 'watch' : 'attention');
    }

    private static function sanitizeDeviceId(string $value): string {
        return substr(preg_replace('/[^A-Za-z0-9._:-]/','',$value)?:'',0,191);
    }
}
