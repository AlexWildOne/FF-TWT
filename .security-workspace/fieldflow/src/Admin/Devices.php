<?php
namespace RoutesPro\Admin;

use RoutesPro\Mobile\DeviceManager;
use RoutesPro\Mobile\Settings;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

class Devices {
    public const SLUG='routespro-devices';

    public static function render(): void {
        if(!current_user_can('routespro_manage')) wp_die('Sem permissões.');

        $search=sanitize_text_field(wp_unslash((string)($_GET['ffdev_search']??'')));
        $perPage=absint($_GET['ffdev_per_page']??25);
        $perPage=in_array($perPage,[25,50,100],true)?$perPage:25;
        $page=max(1,absint($_GET['ffdev_paged']??1));
        $platform=sanitize_key((string)($_GET['ffdev_platform']??''));
        if(!in_array($platform,['','android','ios','web'],true))$platform='';
        $listing=DeviceManager::listDevicesPage($page,$perPage,$search,['platform'=>$platform]);
        $rows=(array)($listing['rows']??[]);
        $total=(int)($listing['total']??0);
        $page=(int)($listing['page']??1);
        $pages=(int)($listing['pages']??1);
        $blocked=Settings::blocked_devices();

        // KPI cards use a bounded summary sample while pagination stays exact.
        $summaryRows=$total>count($rows)?DeviceManager::listDevices(min(500,max(1,$total)),0,$search,['platform'=>$platform]):$rows;

        AdminPage::open('Dispositivos','Offline Operations Intelligence, saúde de sincronização, rede, armazenamento, confiança e comandos por equipamento.');
        $certified=false;
        if(class_exists('\\RoutesPro\\Cloud\\Phase6StableRelease')){
            $state=get_option(\RoutesPro\Cloud\Phase6StableRelease::STATE_OPTION,[]);
            $snapshot=get_option(\RoutesPro\Cloud\Phase6StableRelease::SNAPSHOT_OPTION,[]);
            $certified=is_array($state)&&is_array($snapshot)
                &&!empty($state['stable_certified'])
                &&hash_equals(\RoutesPro\Cloud\Phase6StableRelease::STABLE_RELEASE,(string)($snapshot['release']??''))
                &&defined('FIELDFLOW_VERSION')
                &&hash_equals(\RoutesPro\Cloud\Phase6StableRelease::STABLE_RELEASE,(string)FIELDFLOW_VERSION);
        }
        if(isset($_GET['updated'])) AdminPage::notice(!empty($_GET['updated'])?'Dispositivo atualizado.':'Não foi possível atualizar o dispositivo.',!empty($_GET['updated'])?'updated':'error');

        $reactEnabled=class_exists(ReactBackofficeFoundation::class)&&ReactBackofficeFoundation::islandEnabled('device-control-table',self::SLUG);
        $reactProps=[];
        if($reactEnabled){
            $reactProps=[
                'contract'=>ReactBackofficeFoundation::CONTRACT,
                'island'=>'device-control-table',
                'title'=>'Dispositivos',
                'description'=>'Pesquisa, paginação, saúde offline e comandos sem recarregar o Backoffice.',
                'ajaxUrl'=>admin_url('admin-ajax.php'),
                'nonce'=>wp_create_nonce('fieldflow_react_devices'),
                'listing'=>DeviceManager::adminListPayload($listing),
                'platformOptions'=>[
                    ['value'=>'','label'=>'Todas as plataformas'],
                    ['value'=>'android','label'=>'Android'],
                    ['value'=>'ios','label'=>'iOS'],
                    ['value'=>'web','label'=>'Web'],
                ],
            ];
        }

        $active=$trusted=$pending=$failed=$conflicts=$healthy=$watch=$attention=$degraded=$ackErrors=$authEvents=0;
        foreach($summaryRows as $row){
            $isBlocked=isset($blocked[(string)$row['device_id']]);
            $isActive=empty($row['revoked_at'])&&!$isBlocked&&strtotime((string)$row['refresh_expires_at'])>time();
            if($isActive)$active++;
            if(!empty($row['is_trusted']))$trusted++;
            $pending+=(int)$row['queue_pending'];
            $failed+=(int)$row['queue_failed'];
            $conflicts+=(int)($row['queue_conflicts']??0);
            $health=(string)($row['offline_health_status']??'watch');
            if($health==='healthy')$healthy++; elseif($health==='attention')$attention++; else $watch++;
            if((string)($row['network_state']??'')==='degraded')$degraded++;
            $ackErrors+=(int)($row['ack_error_count']??0);
            $authEvents+=(int)($row['auth_required_count']??0);
        }

        echo '<div class="ff-dev-intro"><div><strong>Offline Operations Intelligence</strong><span>Telemetria reportada pelos dispositivos. Os dados locais permanecem a fonte de verdade até existir ACK do servidor.</span></div><span class="ff-pill '.($certified?'good':'warn').'">Azure 6B · '.esc_html($certified?'Certified':'Revalidação pendente').' · '.esc_html(defined('FIELDFLOW_VERSION')?FIELDFLOW_VERSION:'n/d').'</span></div>';
        echo '<div class="ff-dev-grid ff-dev-grid-6"><div class="ff-dev-kpi"><span>Dispositivos</span><strong>'.$total.'</strong><small>'.$active.' ativos'.($total>500?' na amostra':'').'</small></div><div class="ff-dev-kpi"><span>Saudáveis</span><strong>'.$healthy.'</strong><small>'.$watch.' sob observação</small></div><div class="ff-dev-kpi"><span>Atenção</span><strong>'.$attention.'</strong><small>'.$degraded.' em rede fraca</small></div><div class="ff-dev-kpi"><span>Fila local</span><strong>'.$pending.'</strong><small>'.$failed.' falhas · '.$conflicts.' conflitos</small></div><div class="ff-dev-kpi"><span>ACK</span><strong>'.$ackErrors.'</strong><small>erros acumulados</small></div><div class="ff-dev-kpi"><span>Sessões</span><strong>'.$authEvents.'</strong><small>pedidos de reautenticação</small></div></div>';

        if($reactEnabled) echo '<div class="ff-react-device-control-root" data-fieldflow-react-island="device-control-table" data-fieldflow-react-props="'.esc_attr((string)wp_json_encode($reactProps,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)).'">';
        self::filters($search,$platform,$perPage,$page,$total,$pages);
        echo '<div class="ff-dev-table"><table class="widefat striped"><thead><tr><th>Utilizador / dispositivo</th><th>Saúde offline</th><th>Fila / confirmação</th><th>Rede / armazenamento</th><th>Política</th><th>Ações</th></tr></thead><tbody>';
        if(!$rows) echo '<tr><td colspan="6">'.($search!==''?'Não foram encontrados dispositivos para esta pesquisa.':'Ainda não existem dispositivos registados.').'</td></tr>';

        foreach($rows as $row){
            $deviceId=(string)$row['device_id'];
            $isBlocked=isset($blocked[$deviceId]);
            $isActive=empty($row['revoked_at'])&&strtotime((string)$row['refresh_expires_at'])>time()&&!$isBlocked;
            $offline=isset($row['offline_allowed'])?(int)$row['offline_allowed']:1;
            $health=(string)($row['offline_health_status']??'watch');
            $score=(int)($row['offline_health_score']??0);
            $healthLabel=$health==='healthy'?'Saudável':($health==='attention'?'Atenção':'Observar');
            $healthClass=$health==='healthy'?'good':($health==='attention'?'bad':'warn');
            $network=(string)($row['network_state']??'unknown');
            $networkLabels=['online'=>'Online','degraded'=>'Rede fraca','offline'=>'Offline','auth_required'=>'Sessão necessária','revoked'=>'Revogado','unknown'=>'A verificar'];
            $oldest=(int)($row['oldest_pending_age_seconds']??0);
            $oldestText=$oldest>0?self::duration($oldest):'Sem pendentes';
            $storage=(int)($row['storage_percent']??0);
            $trustState=(string)($row['trust_lease_state']??'missing');
            $trustLabels=['valid'=>'Lease 6B válida','pending'=>'Lease pendente','expired'=>'Lease expirada','missing'=>'Sem lease','not_supplied'=>'Sem lease','stable_revalidation_pending'=>'Revalidação 6B pendente'];
            $trustLabel=$trustLabels[$trustState]??ucfirst(str_replace('_',' ',$trustState));
            $trustClass=$trustState==='valid'?'good':($trustState==='expired'?'bad':'warn');
            $mediaFiles=(int)($row['media_pending_files']??0);
            $mediaBytes=(int)($row['media_pending_bytes']??0);
            $resumableChunks=(int)($row['resumable_uploaded_chunks']??0);
            $resumedChunks=(int)($row['resumable_resumed_chunks']??0);
            $resumableFiles=(int)($row['resumable_completed_files']??0);

            echo '<tr><td><strong>'.esc_html((string)($row['display_name']?:$row['user_login']?:('#'.$row['user_id']))).'</strong><br><small>'.esc_html((string)$row['user_email']).'</small><hr><strong>'.esc_html((string)($row['device_name']?:'Dispositivo')).'</strong><br><code>'.esc_html($deviceId).'</code><br><small>'.esc_html(strtoupper((string)($row['platform']?:'n/d'))).' · App '.esc_html((string)($row['app_version']?:'n/d')).'</small><br><small>Runtime '.esc_html((string)($row['runtime_build']?:'n/d')).' · visto '.esc_html(self::date((string)($row['session_seen_at']?:$row['sync_seen_at']))).'</small></td>';
            echo '<td><span class="ff-pill '.$healthClass.'">'.esc_html($healthLabel).' · '.$score.'/100</span><br><small>ACK: '.(int)($row['ack_error_count']??0).' · recuperações: '.(int)($row['recovery_count']??0).'</small><br><small>Retomas da app: '.(int)($row['app_resume_count']??0).'</small>';
            if(!empty($row['sync_history'])){
                echo '<details class="ff-dev-history"><summary>Histórico recente</summary>';
                foreach(array_slice((array)$row['sync_history'],0,5) as $event){
                    $state=sanitize_key((string)($event['state']??''));
                    $labels=['protected'=>'Protegido','syncing'=>'A sincronizar','confirmed'=>'Confirmado','pending'=>'Pendente','attention'=>'Atenção','conflict'=>'Conflito','recovered'=>'Recuperado'];
                    echo '<div><strong>'.esc_html($labels[$state]??$state).'</strong><span>'.esc_html(self::epoch((int)($event['at']??0))).'</span><small>'.esc_html((string)($event['message']??'')).'</small></div>';
                }
                echo '</details>';
            }
            echo '</td>';
            echo '<td><strong>'.(int)$row['queue_pending'].' pendentes</strong><br><span>'.(int)$row['queue_failed'].' falhas · '.(int)($row['queue_conflicts']??0).' conflitos</span><br><small>Mais antigo: '.esc_html($oldestText).'</small><br><small>Máx. tentativas: '.(int)($row['max_queue_attempts']??0).'</small><br><small>Media pendente: '.$mediaFiles.' ficheiros · '.esc_html(self::bytes($mediaBytes)).'</small><br><small>Retomável: '.$resumableFiles.' ficheiros · '.$resumableChunks.' blocos enviados · '.$resumedChunks.' retomados</small><br><small>Última confirmação: '.esc_html(self::epoch((int)($row['last_confirmed_at_ms']??0))).'</small><br><small>Última sync: '.esc_html(self::date((string)($row['last_sync_at']?:$row['sync_seen_at']))).'</small></td>';
            echo '<td><span class="ff-pill '.($network==='online'?'good':($network==='degraded'?'warn':($network==='auth_required'||$network==='revoked'?'bad':''))).'">'.esc_html($networkLabels[$network]??$network).'</span><br><small>Probe: '.(int)($row['probe_latency_ms']??0).' ms</small><br><small>Rede fraca acumulada: '.esc_html(self::duration((int)floor(((int)($row['degraded_total_ms']??0))/1000))).'</small><br><strong>Storage '.$storage.'%</strong><br><small>'.esc_html((string)($row['storage_persistence']??'unknown')).'</small></td>';
            echo '<td><span class="ff-pill '.(!empty($row['is_trusted'])?'good':'').'">'.(!empty($row['is_trusted'])?'Confiável':'Normal').'</span> <span class="ff-pill '.($offline?'good':'bad').'">'.($offline?'Offline ativo':'Offline bloqueado').'</span><br><span class="ff-pill '.$trustClass.'">'.esc_html($trustLabel).'</span>';
            if((int)($row['trust_lease_expires_at_ms']??0)>0) echo '<br><small>Lease até '.esc_html(self::epoch((int)$row['trust_lease_expires_at_ms'])).'</small>';
            if((string)($row['trust_lease_release']??'')!=='') echo '<br><small>Release '.esc_html((string)$row['trust_lease_release']).'</small>';
            echo '<br><small>Cache v'.max(1,(int)($row['cache_generation']??1)).'</small><br><small>'.esc_html($isBlocked?'Bloqueado':($isActive?'Sessão ativa':'Revogado ou expirado')).'</small></td>';
            echo '<td><div class="ff-dev-actions">';
            if(!empty($row['is_trusted'])) self::button('untrust','Retirar confiança',$row,$page,$perPage,$search,$platform); else self::button('trust','Confiar',$row,$page,$perPage,$search,$platform);
            if($offline) self::button('disable_offline','Desativar offline',$row,$page,$perPage,$search,$platform); else self::button('enable_offline','Ativar offline',$row,$page,$perPage,$search,$platform);
            self::button('clear_cache','Limpar cache',$row,$page,$perPage,$search,$platform);
            if($isBlocked) self::button('unblock','Desbloquear',$row,$page,$perPage,$search,$platform); else self::button('block','Bloquear',$row,$page,$perPage,$search,$platform,true);
            if($isActive) self::button('force_logout','Forçar logout',$row,$page,$perPage,$search,$platform,true);
            echo '</div></td></tr>';
        }
        echo '</tbody></table></div>';
        self::pagination($search,$platform,$perPage,$page,$total,$pages);
        if($reactEnabled) echo '</div>';
        AdminPage::close();
    }

    private static function filters(string $search,string $platform,int $perPage,int $page,int $total,int $pages): void {
        $start=$total>0?(($page-1)*$perPage)+1:0;
        $end=min($total,$page*$perPage);
        echo '<div class="ff-dev-toolbar" style="display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin:16px 0">';
        echo '<form method="get" action="'.esc_url(admin_url('admin.php')).'" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
        echo '<input type="hidden" name="page" value="'.esc_attr(self::SLUG).'">';
        echo '<label class="screen-reader-text" for="ffdev-search">Pesquisar dispositivos</label><input id="ffdev-search" type="search" name="ffdev_search" value="'.esc_attr($search).'" placeholder="Utilizador, email, dispositivo, plataforma ou versão" style="min-width:320px">';
        echo '<label for="ffdev-platform">Plataforma</label><select id="ffdev-platform" name="ffdev_platform"><option value=""'.selected($platform,'',false).'>Todas</option><option value="android"'.selected($platform,'android',false).'>Android</option><option value="ios"'.selected($platform,'ios',false).'>iOS</option><option value="web"'.selected($platform,'web',false).'>Web</option></select>';
        echo '<label for="ffdev-per-page">Por página</label><select id="ffdev-per-page" name="ffdev_per_page">';
        foreach([25,50,100] as $option) echo '<option value="'.$option.'"'.selected($perPage,$option,false).'>'.$option.'</option>';
        echo '</select><button class="button button-primary" type="submit">Filtrar</button>';
        if($search!==''||$platform!==''||$perPage!==25) echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>self::SLUG],admin_url('admin.php'))).'">Limpar</a>';
        echo '</form><div><strong>'.number_format_i18n($total).'</strong> dispositivos';
        if($total>0) echo ' · a mostrar '.number_format_i18n($start).' a '.number_format_i18n($end).' · página '.number_format_i18n($page).' de '.number_format_i18n(max(1,$pages));
        echo '</div></div>';
        self::pagination($search,$platform,$perPage,$page,$total,$pages);
    }

    private static function pagination(string $search,string $platform,int $perPage,int $page,int $total,int $pages): void {
        if($total<=0||$pages<=1) return;
        $baseArgs=['page'=>self::SLUG,'ffdev_per_page'=>$perPage];
        if($search!=='')$baseArgs['ffdev_search']=$search;
        if($platform!=='')$baseArgs['ffdev_platform']=$platform;
        $placeholder=999999999;
        $base=str_replace((string)$placeholder,'%#%',add_query_arg(array_merge($baseArgs,['ffdev_paged'=>$placeholder]),admin_url('admin.php')));
        $links=paginate_links([
            'base'=>$base,
            'format'=>'',
            'current'=>$page,
            'total'=>$pages,
            'type'=>'list',
            'prev_text'=>'‹ Anterior',
            'next_text'=>'Seguinte ›',
        ]);
        if($links) echo '<nav class="ff-dev-pagination" aria-label="Paginação de dispositivos" style="margin:14px 0">'.$links.'</nav>';
    }

    private static function duration(int $seconds): string {
        if($seconds<=0) return '0 min';
        if($seconds<3600) return max(1,(int)floor($seconds/60)).' min';
        if($seconds<86400) return round($seconds/3600,1).' h';
        return round($seconds/86400,1).' d';
    }

    private static function bytes(int $bytes): string {
        if($bytes<=0) return '0 B';
        $units=['B','KB','MB','GB'];
        $value=(float)$bytes;
        $i=0;
        while($value>=1024&&$i<count($units)-1){$value/=1024;$i++;}
        return ($i===0?(string)(int)$value:number_format_i18n($value,$value>=10?1:2)).' '.$units[$i];
    }

    private static function epoch(int $milliseconds): string {
        if($milliseconds<=0) return 'Sem registo';
        return wp_date('d/m/Y H:i',(int)floor($milliseconds/1000));
    }

    private static function button(string $action,string $label,array $row,int $page,int $perPage,string $search,string $platform,bool $danger=false): void {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline">';
        wp_nonce_field('fieldflow_device_action');
        echo '<input type="hidden" name="action" value="fieldflow_device_action"><input type="hidden" name="device_action" value="'.esc_attr($action).'"><input type="hidden" name="user_id" value="'.(int)$row['user_id'].'"><input type="hidden" name="session_id" value="'.(int)$row['session_id'].'"><input type="hidden" name="device_id" value="'.esc_attr((string)$row['device_id']).'">';
        echo '<input type="hidden" name="return_paged" value="'.(int)$page.'"><input type="hidden" name="return_per_page" value="'.(int)$perPage.'"><input type="hidden" name="return_search" value="'.esc_attr($search).'"><input type="hidden" name="return_platform" value="'.esc_attr($platform).'">';
        echo '<button type="submit" class="button button-small'.($danger?' button-link-delete':'').'"'.($danger?' onclick="return confirm(\'Confirmar esta ação?\')"':'').'>'.esc_html($label).'</button></form>';
    }

    private static function date(string $value): string { return $value?mysql2date('d/m/Y H:i',$value):'Sem registo'; }
}
