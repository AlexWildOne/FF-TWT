<?php
namespace RoutesPro\Admin;

use RoutesPro\Observability\AlertService;
use RoutesPro\Observability\DiagnosticPackage;
use RoutesPro\Observability\Monitor;
use RoutesPro\Observability\ApiUsageLedger;
use RoutesPro\Observability\MediaTelemetry;
use RoutesPro\Performance\LoadReadiness;
use RoutesPro\Performance\Phase4Baseline;
use RoutesPro\Performance\Phase4Drivers;
use RoutesPro\Performance\Phase4AdminHotspots;
use RoutesPro\Mobile\OfflineSyncService;
use RoutesPro\Security\SecurityService;
use RoutesPro\Security\Settings;
use RoutesPro\Security\SessionPolicy;
use RoutesPro\Security\Mfa;
use RoutesPro\Support\AdminPage;
use RoutesPro\Support\Config;
use RoutesPro\Services\GoogleCostControlCenter;

if (!defined('ABSPATH')) exit;

class SecurityObservability {
    public const SLUG='routespro-security-observability';

    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_observation_status',[self::class,'observationStatus']);
        add_action('admin_post_fieldflow_alert_status',[self::class,'alertStatus']);
        add_action('admin_post_fieldflow_api_cost_settings',[self::class,'saveApiCostSettings']);
        add_action('admin_post_fieldflow_media_telemetry_settings',[self::class,'saveMediaTelemetrySettings']);
        add_action('admin_post_fieldflow_security_export_json',[self::class,'exportSecurityJson']);
        add_action('admin_post_fieldflow_mfa_begin',[self::class,'mfaBegin']);
        add_action('admin_post_fieldflow_mfa_activate',[self::class,'mfaActivate']);
        add_action('admin_post_fieldflow_mfa_disable',[self::class,'mfaDisable']);
        add_action('admin_post_fieldflow_mfa_reset_user',[self::class,'mfaResetUser']);
        add_action('admin_post_fieldflow_session_policy_save',[self::class,'saveSessionPolicy']);
        DiagnosticPackage::registerHooks();
    }

    public static function render(): void {
        if(!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        $tab=sanitize_key((string)($_GET['tab']??'overview'));
        // 9DH: overview aggregates are expensive and unused on other tabs.
        // This removes three calls on each non-overview page, with no data mutation.
        $summary=[]; $security=[]; $alerts=[];
        if (self::overviewSnapshotsRequired($tab)) {
            $summary=Monitor::summary();
            $security=SecurityService::counts();
            $alerts=AlertService::counts();
        }
        AdminPage::open('Segurança e Observabilidade','Controlo de acessos, erros agrupados, desempenho, alertas e diagnóstico sanitizado.');
        echo '<div class="ff-ops-nav'.($tab === 'performance' ? ' ff-ops-nav-9dj' : '').'" aria-label="Separadores de Segurança e Observabilidade">';
        foreach(['overview'=>'Resumo','observability'=>'Observabilidade','api-costs'=>'Google Cost Center','media'=>'Media e Uploads','mobile-sync'=>'Sincronização Mobile','performance'=>'Performance e Carga','security'=>'Segurança','auth'=>'MFA e Sessões','alerts'=>'Alertas','settings'=>'Configuração','diagnostics'=>'Diagnóstico'] as $key=>$label){$url=add_query_arg(['page'=>self::SLUG,'tab'=>$key],admin_url('admin.php'));echo '<a class="'.($tab===$key?'active':'').'" href="'.esc_url($url).'">'.esc_html($label).'</a>';}
        echo '</div>';
        if($tab==='observability') self::observability();
        elseif($tab==='api-costs') self::apiCosts();
        elseif($tab==='media') self::mediaTelemetry();
        elseif($tab==='mobile-sync') self::mobileSync();
        elseif($tab==='performance') self::performance();
        elseif($tab==='security') self::security();
        elseif($tab==='auth') self::authPolicy();
        elseif($tab==='alerts') self::alerts();
        elseif($tab==='settings') self::settings();
        elseif($tab==='diagnostics') self::diagnostics();
        else self::overview($summary,$security,$alerts);
        AdminPage::close();
    }

    public static function overviewSnapshotsRequired(string $tab): bool {
        // Unknown tabs also use the overview fallback below.
        return !in_array($tab, [
            'observability','api-costs','media','mobile-sync','performance',
            'security','auth','alerts','settings','diagnostics',
        ], true);
    }

    private static function overview(array $summary,array $security,array $alerts): void {
        echo '<div class="ff-ops-grid"><div class="ff-ops-card ff-ops-kpi"><span>Eventos abertos</span><strong>'.(int)$summary['open'].'</strong></div><div class="ff-ops-card ff-ops-kpi"><span>Críticos</span><strong>'.((int)$summary['critical']+(int)$security['critical']).'</strong></div><div class="ff-ops-card ff-ops-kpi"><span>Alertas</span><strong>'.(int)$alerts['open'].'</strong></div><div class="ff-ops-card ff-ops-kpi"><span>Heartbeat</span><strong style="font-size:16px">'.esc_html($summary['last_heartbeat']?:'Ainda não executado').'</strong></div></div>';
        echo '<div class="ff-ops-card"><h2>Endpoints mais lentos</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Endpoint</th><th>Pior tempo</th><th>Ocorrências</th></tr></thead><tbody>';
        if(empty($summary['slowest']))echo '<tr><td colspan="3">Ainda não existem endpoints lentos registados.</td></tr>';
        foreach($summary['slowest'] as $row)echo '<tr><td><code>'.esc_html((string)$row['route']).'</code></td><td>'.number_format_i18n(((int)$row['max_duration_ms'])/1000,2).' s</td><td>'.(int)$row['occurrences'].'</td></tr>';
        echo '</tbody></table></div></div>';
    }

    private static function observability(): void {
        $rows=Monitor::recent(100);
        echo '<div class="ff-ops-card"><h2>Erros e lentidão agrupados</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Evento</th><th>Mensagem</th><th>Impacto</th><th>Ocorrências</th><th>Última deteção</th><th></th></tr></thead><tbody>';
        if(!$rows)echo '<tr><td colspan="6">Sem ocorrências registadas.</td></tr>';
        foreach($rows as $row){echo '<tr><td><code>'.esc_html((string)$row['event_type']).'</code><br><span class="ff-sev '.esc_attr((string)$row['severity']).'">'.esc_html(strtoupper((string)$row['severity'])).'</span></td><td><strong>'.esc_html((string)$row['message']).'</strong><br><small>'.esc_html((string)$row['route']).'</small></td><td>'.((int)$row['duration_ms']>0?number_format_i18n(((int)$row['duration_ms'])/1000,2).' s':'HTTP '.((int)$row['http_status']?:'n/d')).'</td><td>'.(int)$row['occurrences'].'</td><td>'.esc_html(mysql2date('d/m/Y H:i',(string)$row['last_seen_at'])).'</td><td>'.self::statusForm('fieldflow_observation_status',(int)$row['id'],(string)$row['status']).'</td></tr>';}
        echo '</tbody></table></div></div>';
    }

    private static function mediaTelemetry(): void {
        $summary = MediaTelemetry::summary(30);
        $daily = MediaTelemetry::daily(14);
        $failures = MediaTelemetry::recentFailures(50);
        $notice = sanitize_key((string)($_GET['fieldflow_media_notice'] ?? ''));
        if ($notice === 'saved') echo '<div class="notice notice-success is-dismissible"><p>Configuração de telemetria media guardada.</p></div>';
        if (empty($summary['table_ready'])) echo '<div class="notice notice-warning"><p>A tabela de telemetria media ainda não está disponível. Reativa o plugin para concluir a atualização de base de dados.</p></div>';

        echo '<div class="ff-ops-grid">';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Submissões, 30 dias</span><strong>'.number_format_i18n((int)$summary['submissions']).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Fotografias enviadas</span><strong>'.number_format_i18n((int)$summary['file_count']).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Dados poupados</span><strong style="font-size:22px">'.esc_html(self::bytes((int)$summary['saved_bytes'])).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Compressão média</span><strong>'.number_format_i18n((float)$summary['compression_pct'],1).'%</strong></div>';
        echo '</div>';

        echo '<div class="ff-ops-card"><div style="display:flex;gap:24px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap"><div><h2 style="margin-top:0">Media Reliability</h2><p>Mede apenas quantidades, tamanhos, compressão, repetições e falhas. Não guarda fotografias, nomes de ficheiro ou respostas do formulário.</p></div><div><strong>Taxa de falha</strong><div style="font-size:26px;font-weight:800">'.number_format_i18n((float)$summary['failure_rate_pct'],1).'%</div><small>'.number_format_i18n((int)$summary['retry_count']).' repetições automáticas, '.number_format_i18n((int)$summary['duplicate_files']).' duplicados removidos</small></div></div></div>';

        echo '<div class="ff-ops-card"><h2>Evolução diária, últimos 14 dias</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Dia</th><th>Submissões</th><th>Sucesso</th><th>Falhas</th><th>Fotografias</th><th>Poupança</th><th>Retries</th><th>Tempo médio</th></tr></thead><tbody>';
        if (!$daily) echo '<tr><td colspan="8">Ainda não existem submissões com telemetria.</td></tr>';
        foreach ($daily as $row) echo '<tr><td>'.esc_html(mysql2date('d/m/Y',(string)$row['usage_day'])).'</td><td>'.number_format_i18n((int)$row['submissions']).'</td><td>'.number_format_i18n((int)$row['successes']).'</td><td>'.number_format_i18n((int)$row['failures']).'</td><td>'.number_format_i18n((int)$row['file_count']).'</td><td>'.esc_html(self::bytes((int)$row['saved_bytes'])).'</td><td>'.number_format_i18n((int)$row['retry_count']).'</td><td>'.number_format_i18n((int)$row['avg_processing_ms']).' ms</td></tr>';
        echo '</tbody></table></div></div>';

        echo '<div class="ff-ops-card"><h2>Falhas recentes</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Data</th><th>Código</th><th>Formulário</th><th>Cliente</th><th>Projeto</th><th>Rota</th><th>Loja</th><th>Ficheiros</th><th>Recebido</th><th>Tempo</th></tr></thead><tbody>';
        if (!$failures) echo '<tr><td colspan="10">Sem falhas de media registadas.</td></tr>';
        foreach ($failures as $row) echo '<tr><td>'.esc_html(mysql2date('d/m/Y H:i',(string)$row['created_at'])).'</td><td><code>'.esc_html((string)($row['error_code'] ?: 'submission_error')).'</code></td><td>'.(int)$row['form_id'].'</td><td>'.(int)$row['client_id'].'</td><td>'.(int)$row['project_id'].'</td><td>'.(int)$row['route_id'].'</td><td>'.(int)$row['location_id'].'</td><td>'.(int)$row['file_count'].'</td><td>'.esc_html(self::bytes((int)$row['server_received_bytes'])).'</td><td>'.number_format_i18n((int)$row['processing_ms']).' ms</td></tr>';
        echo '</tbody></table></div></div>';

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ff-ops-card">';wp_nonce_field('fieldflow_media_telemetry_settings');
        echo '<input type="hidden" name="action" value="fieldflow_media_telemetry_settings"><h2>Configuração</h2><table class="form-table"><tr><th>Telemetria</th><td><label><input type="checkbox" name="fieldflow_media_telemetry[enabled]" value="1" '.checked(!empty(Config::get('media_telemetry_enabled',1)),true,false).'> Ativar métricas técnicas de media</label></td></tr><tr><th>Retenção</th><td><input type="number" min="7" max="3650" name="fieldflow_media_telemetry[retention_days]" value="'.(int)Config::get('media_telemetry_retention_days',90).'"> dias</td></tr></table><p><button class="button button-primary">Guardar configuração</button></p></form>';
    }

    private static function mobileSync(): void {
        $stats = OfflineSyncService::stats();
        $devices = OfflineSyncService::recentDevices(50);
        echo '<div class="ff-ops-grid">';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Dispositivos</span><strong>'.number_format_i18n((int)($stats['devices'] ?? 0)).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Ativos em 24 horas</span><strong>'.number_format_i18n((int)($stats['active_24h'] ?? 0)).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Pendentes</span><strong>'.number_format_i18n((int)($stats['pending'] ?? 0)).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Conflitos</span><strong>'.number_format_i18n((int)($stats['conflicts'] ?? 0)).'</strong></div>';
        echo '</div>';
        echo '<div class="ff-ops-card"><h2>Offline Sync &amp; Mobile Reliability</h2><p>Estado técnico da fila unificada, sincronização incremental e conflitos. Os diagnósticos não incluem respostas de formulários, fotografias, moradas ou coordenadas.</p>';
        echo '<p><strong>Última sincronização:</strong> '.esc_html(!empty($stats['last_sync_at']) ? mysql2date('d/m/Y H:i',(string)$stats['last_sync_at']) : 'Ainda sem registos').'. <strong>Falhas a rever:</strong> '.number_format_i18n((int)($stats['failed'] ?? 0)).'.</p></div>';
        echo '<div class="ff-ops-card"><h2>Dispositivos recentes</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Utilizador</th><th>Dispositivo</th><th>Plataforma</th><th>App</th><th>Rede</th><th>Fila</th><th>Conflitos</th><th>Último endpoint</th><th>Última atividade</th></tr></thead><tbody>';
        if (!$devices) echo '<tr><td colspan="9">Ainda não existem dispositivos registados.</td></tr>';
        foreach ($devices as $device) {
            $meta = json_decode((string)($device['meta_json'] ?? ''), true);
            if (!is_array($meta)) $meta = [];
            $name = (string)($device['display_name'] ?: $device['user_login'] ?: ('#'.(int)$device['user_id']));
            $network = trim((string)($meta['effective_type'] ?? $meta['network_type'] ?? ''));
            $queue = (int)($device['queue_pending'] ?? 0).' / '.(int)($device['queue_failed'] ?? 0);
            echo '<tr><td><strong>'.esc_html($name).'</strong></td><td><code>'.esc_html(self::shortDevice((string)($device['device_id'] ?? ''))).'</code></td><td>'.esc_html((string)($device['platform'] ?? 'web')).'</td><td>'.esc_html((string)($device['app_version'] ?? 'n/d')).'</td><td>'.esc_html($network ?: 'n/d').'</td><td>'.esc_html($queue).'</td><td>'.number_format_i18n((int)($meta['queue_conflicts'] ?? 0)).'</td><td><code>'.esc_html((string)($meta['last_endpoint'] ?? 'n/d')).'</code></td><td>'.esc_html(!empty($device['last_seen_at']) ? mysql2date('d/m/Y H:i',(string)$device['last_seen_at']) : 'n/d').'</td></tr>';
        }
        echo '</tbody></table></div><p class="description">Fila apresentada como pendentes / falhas. Os identificadores de dispositivo são abreviados no ecrã.</p></div>';
    }

    private static function shortDevice(string $value): string {
        if ($value === '') return 'n/d';
        if (strlen($value) <= 18) return $value;
        return substr($value, 0, 8).'...'.substr($value, -6);
    }

    private static function apiCosts(): void {
        $summary = ApiUsageLedger::summary(30);
        $providers = ApiUsageLedger::byProvider(30);
        $operations = ApiUsageLedger::byOperation(30, 30);
        $daily = ApiUsageLedger::daily(14);
        $control = class_exists(GoogleCostControlCenter::class) ? GoogleCostControlCenter::status(30) : [];
        $notice = sanitize_key((string)($_GET['fieldflow_api_cost_notice'] ?? ''));
        if ($notice === 'saved') echo '<div class="notice notice-success is-dismissible"><p>Configuração de custos API guardada.</p></div>';
        if (empty($summary['table_ready'])) echo '<div class="notice notice-warning"><p>A tabela do API Cost Ledger ainda não está disponível. Reativa o plugin para executar a atualização de base de dados.</p></div>';

        echo '<div class="ff-ops-grid">';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Pedidos externos, 30 dias</span><strong>'.number_format_i18n((int)$summary['requests']).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Chamadas evitadas</span><strong>'.number_format_i18n((int)($summary['avoided_events'] ?? 0)).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Custo estimado, 30 dias</span><strong style="font-size:22px">'.esc_html(self::euro((float)$summary['estimated_cost_eur'])).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Poupança estimada</span><strong style="font-size:22px">'.esc_html(self::euro((float)$summary['avoided_cost_eur'])).'</strong></div>';
        echo '</div>';

        $health = (string)($control['health'] ?? 'healthy');
        $healthLabel = (string)($control['health_label'] ?? 'POLÍTICA SAUDÁVEL');
        $healthColor = $health === 'critical' ? '#b91c1c' : ($health === 'warning' ? '#b45309' : '#15803d');
        echo '<div class="ff-ops-card"><div style="display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap"><div><h2 style="margin-top:0">Google Cost Control Center</h2><p>Controla Google Maps Platform antes do pedido HTTP. A política low cost permite Essentials e bloqueia por defeito Route Matrix, Pro e Enterprise.</p></div><div style="text-align:right"><strong style="display:inline-block;padding:7px 11px;border-radius:999px;background:'.esc_attr($healthColor).';color:#fff">'.esc_html($healthLabel).'</strong><div style="margin-top:8px"><small>Contrato 9BN, '.esc_html(GoogleCostControlCenter::CONTRACT).'</small></div></div></div>';
        echo '<div class="ff-ops-grid" style="margin-top:16px">';
        echo '<div class="ff-ops-kpi"><span>Routes Essentials</span><strong>'.number_format_i18n((int)($control['essentials_requests'] ?? 0)).'</strong></div>';
        echo '<div class="ff-ops-kpi"><span>Pro + Enterprise</span><strong>'.number_format_i18n((int)($control['premium_requests'] ?? 0)).'</strong></div>';
        echo '<div class="ff-ops-kpi"><span>Matrix, unidades</span><strong>'.number_format_i18n((float)($control['matrix_units'] ?? 0),0).'</strong></div>';
        echo '<div class="ff-ops-kpi"><span>Bloqueios antes do HTTP</span><strong>'.number_format_i18n((int)($control['blocked_events'] ?? 0)).'</strong></div>';
        echo '</div>';
        $drift = is_array($control['drift'] ?? null) ? $control['drift'] : [];
        if ($drift) {
            echo '<div class="notice notice-warning inline" style="margin:16px 0 0"><p><strong>Configuração fora da política low cost:</strong></p><ul style="list-style:disc;padding-left:20px">';
            foreach ($drift as $message) echo '<li>'.esc_html((string)$message).'</li>';
            echo '</ul></div>';
        } else {
            echo '<p style="margin:16px 0 0"><strong>Proteções ativas:</strong> Traffic Unaware, Matrix bloqueada, Pro bloqueado, Enterprise/TOLLS bloqueado e enriquecimento de contactos Places desligado.</p>';
        }
        echo '</div>';

        $budget = (float)$summary['monthly_budget_eur'];
        echo '<div class="ff-ops-card"><div style="display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap"><div><h2 style="margin-top:0">API Cost Ledger</h2><p>Contabiliza chamadas técnicas, unidades faturáveis, duração, erros, cache e custo estimado. Não guarda moradas, coordenadas, conteúdos, respostas nem chaves.</p></div><div style="min-width:240px"><strong>Mês atual</strong><div style="font-size:26px;font-weight:800">'.esc_html(self::euro((float)$summary['month_cost_eur'])).'</div><small>'.($budget > 0 ? esc_html(number_format_i18n((float)$summary['budget_usage_pct'],1).'% de '.self::euro($budget)) : 'Sem orçamento mensal definido').'</small></div></div></div>';

        echo '<div class="ff-ops-card"><h2>Consumo por fornecedor, últimos 30 dias</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Fornecedor</th><th>Pedidos</th><th>Unidades</th><th>Cache hits</th><th>Bloqueados</th><th>Erros</th><th>Custo estimado</th><th>Poupança</th><th>Última utilização</th></tr></thead><tbody>';
        if (!$providers) echo '<tr><td colspan="9">Ainda não existem chamadas registadas.</td></tr>';
        foreach ($providers as $row) echo '<tr><td><strong>'.esc_html(self::providerLabel((string)$row['provider'])).'</strong></td><td>'.number_format_i18n((int)$row['requests']).'</td><td>'.number_format_i18n((float)$row['units'],2).'</td><td>'.number_format_i18n((int)$row['cache_hits']).'</td><td>'.number_format_i18n((int)($row['blocked'] ?? 0)).'</td><td>'.number_format_i18n((int)$row['errors']).'</td><td>'.esc_html(self::euro((float)$row['estimated_cost_eur'])).'</td><td>'.esc_html(self::euro((float)$row['avoided_cost_eur'])).'</td><td>'.esc_html(!empty($row['last_used_at']) ? mysql2date('d/m/Y H:i',(string)$row['last_used_at']) : 'n/d').'</td></tr>';
        echo '</tbody></table></div></div>';

        echo '<div class="ff-ops-card"><h2>Operações com maior impacto</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Operação</th><th>Tier</th><th>Fornecedor</th><th>Pedidos</th><th>Unidades</th><th>Bloqueados</th><th>Erros</th><th>Custo</th><th>Evitado</th></tr></thead><tbody>';
        if (!$operations) echo '<tr><td colspan="9">Sem operações para apresentar.</td></tr>';
        foreach ($operations as $row) {
            $tier = class_exists(GoogleCostControlCenter::class) ? GoogleCostControlCenter::tier((string)$row['operation']) : 'standard';
            echo '<tr><td><code>'.esc_html((string)$row['operation']).'</code></td><td><strong>'.esc_html(strtoupper($tier)).'</strong></td><td>'.esc_html(self::providerLabel((string)$row['provider'])).'</td><td>'.number_format_i18n((int)$row['requests']).'</td><td>'.number_format_i18n((float)$row['units'],2).'</td><td>'.number_format_i18n((int)($row['blocked'] ?? 0)).'</td><td>'.number_format_i18n((int)$row['errors']).'</td><td>'.esc_html(self::euro((float)$row['estimated_cost_eur'])).'</td><td>'.esc_html(self::euro((float)$row['avoided_cost_eur'])).'</td></tr>';
        }
        echo '</tbody></table></div></div>';

        echo '<div class="ff-ops-card"><h2>Evolução diária, últimos 14 dias</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Dia</th><th>Pedidos</th><th>Cache hits</th><th>Bloqueados</th><th>Erros</th><th>Custo</th><th>Poupança</th></tr></thead><tbody>';
        if (!$daily) echo '<tr><td colspan="7">Sem histórico diário.</td></tr>';
        foreach ($daily as $row) echo '<tr><td>'.esc_html(mysql2date('d/m/Y',(string)$row['usage_day'])).'</td><td>'.number_format_i18n((int)$row['requests']).'</td><td>'.number_format_i18n((int)$row['cache_hits']).'</td><td>'.number_format_i18n((int)($row['blocked'] ?? 0)).'</td><td>'.number_format_i18n((int)$row['errors']).'</td><td>'.esc_html(self::euro((float)$row['estimated_cost_eur'])).'</td><td>'.esc_html(self::euro((float)$row['avoided_cost_eur'])).'</td></tr>';
        echo '</tbody></table></div></div>';

        $fields = [
            'api_rate_google_routes_compute_routes_essentials_per_1000_eur' => 'Google Routes, Compute Routes Essentials por 1.000',
            'api_rate_google_routes_compute_routes_pro_per_1000_eur' => 'Google Routes, Compute Routes Pro por 1.000',
            'api_rate_google_routes_compute_routes_enterprise_per_1000_eur' => 'Google Routes, Compute Routes Enterprise por 1.000',
            'api_rate_google_routes_compute_route_matrix_essentials_per_1000_eur' => 'Google Routes, Route Matrix Essentials por 1.000 elementos',
            'api_rate_google_routes_compute_route_matrix_pro_per_1000_eur' => 'Google Routes, Route Matrix Pro por 1.000 elementos',
            'api_rate_google_routes_compute_route_matrix_enterprise_per_1000_eur' => 'Google Routes, Route Matrix Enterprise por 1.000 elementos',
            'api_rate_google_routes_route_per_1000_eur' => 'Google Routes, fallback legado rotas por 1.000',
            'api_rate_google_routes_matrix_element_per_1000_eur' => 'Google Routes, fallback legado matriz por 1.000',
            'api_rate_google_maps_geocode_per_1000_eur' => 'Google Maps, geocoding por 1.000',
            'api_rate_google_maps_matrix_element_per_1000_eur' => 'Google Maps, elementos de matriz por 1.000',
            'api_rate_google_maps_directions_per_1000_eur' => 'Google Maps, directions por 1.000',
            'api_rate_google_maps_places_per_1000_eur' => 'Google Maps, Places por 1.000',
            'api_rate_azure_maps_geocode_per_1000_eur' => 'Azure Maps, geocoding por 1.000',
            'api_rate_azure_maps_matrix_element_per_1000_eur' => 'Azure Maps, elementos de matriz por 1.000',
            'api_rate_azure_maps_directions_per_1000_eur' => 'Azure Maps, directions por 1.000',
            'api_rate_azure_maps_places_per_1000_eur' => 'Azure Maps, Places por 1.000',
        ];
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ff-ops-card">';
        wp_nonce_field('fieldflow_api_cost_settings');
        echo '<input type="hidden" name="action" value="fieldflow_api_cost_settings"><h2>Orçamento e tarifas de referência</h2><p>As tarifas variam por contrato, região e escalão. Introduz os valores reais da tua conta. Um valor zero mantém a contagem ativa, mas não calcula custo monetário.</p><table class="form-table">';
        echo '<tr><th>Contabilização</th><td><label><input type="checkbox" name="fieldflow_api_cost[api_cost_ledger_enabled]" value="1" '.checked(!empty(Config::get('api_cost_ledger_enabled',1)),true,false).'> Ativar API Cost Ledger</label></td></tr>';
        echo '<tr><th>Google Cost Control</th><td><label style="display:block;margin-bottom:6px"><input type="checkbox" name="fieldflow_api_cost[google_cost_control_enabled]" value="1" '.checked(!empty(Config::get('google_cost_control_enabled',1)),true,false).'> Ativar circuit breaker financeiro</label><label style="display:block;margin-bottom:6px"><input type="checkbox" name="fieldflow_api_cost[google_cost_block_matrix]" value="1" '.checked(!empty(Config::get('google_cost_block_matrix',1)),true,false).'> Bloquear Google Route Matrix</label><label style="display:block;margin-bottom:6px"><input type="checkbox" name="fieldflow_api_cost[google_cost_block_pro]" value="1" '.checked(!empty(Config::get('google_cost_block_pro',1)),true,false).'> Bloquear Google Routes Pro</label><label style="display:block;margin-bottom:6px"><input type="checkbox" name="fieldflow_api_cost[google_cost_block_enterprise]" value="1" '.checked(!empty(Config::get('google_cost_block_enterprise',1)),true,false).'> Bloquear Google Routes Enterprise, incluindo TOLLS</label><label style="display:block"><input type="checkbox" name="fieldflow_api_cost[google_cost_alert_premium]" value="1" '.checked(!empty(Config::get('google_cost_alert_premium',1)),true,false).'> Criar alerta quando uma tentativa premium for bloqueada</label><p class="description">Os bloqueios acontecem antes de qualquer pedido HTTP. Desmarcar uma proteção é uma decisão financeira deliberada.</p></td></tr>';
        echo '<tr><th>Retenção</th><td><input type="number" min="7" max="3650" name="fieldflow_api_cost[api_usage_retention_days]" value="'.(int)Config::get('api_usage_retention_days',90).'"> dias</td></tr>';
        echo '<tr><th>Orçamento mensal</th><td><input type="number" min="0" step="0.01" name="fieldflow_api_cost[api_monthly_budget_eur]" value="'.esc_attr((string)Config::get('api_monthly_budget_eur',0)).'"> EUR <p class="description">Cria alerta aos 80% e aos 100%.</p></td></tr>';
        foreach ($fields as $key=>$label) echo '<tr><th>'.esc_html($label).'</th><td><input type="number" min="0" step="0.000001" name="fieldflow_api_cost['.esc_attr($key).']" value="'.esc_attr((string)Config::get($key,0)).'"> EUR</td></tr>';
        echo '</table><p><button class="button button-primary">Guardar custos e limites</button></p></form>';
    }

    private static function bytes(int $bytes): string {
        $bytes = max(0,$bytes);
        if ($bytes >= GB_IN_BYTES) return number_format_i18n($bytes / GB_IN_BYTES,2).' GB';
        if ($bytes >= MB_IN_BYTES) return number_format_i18n($bytes / MB_IN_BYTES,2).' MB';
        if ($bytes >= KB_IN_BYTES) return number_format_i18n($bytes / KB_IN_BYTES,1).' KB';
        return number_format_i18n($bytes).' B';
    }

    private static function euro(float $value): string {
        return number_format_i18n($value, 4).' €';
    }

    private static function providerLabel(string $provider): string {
        return ['google_routes'=>'Google Routes','google_maps'=>'Google Maps','azure_maps'=>'Azure Maps'][$provider] ?? ucwords(str_replace('_',' ',$provider));
    }

    private static function performance(): void {
        $settings = LoadReadiness::settings();
        $gate = LoadReadiness::gate((int)$settings['gate_window_hours']);
        $queue = is_array($gate['queue'] ?? null) ? $gate['queue'] : [];
        $cron = LoadReadiness::cronStatus();
        $status = (string)($gate['status'] ?? 'collecting');
        $statusLabels = ['pass'=>'APROVADO','warning'=>'ATENÇÃO','fail'=>'BLOQUEADO','blocked'=>'BLOQUEADO','collecting'=>'A RECOLHER'];
        $notice = sanitize_key((string)($_GET['fieldflow_performance_notice'] ?? ''));
        $phase4 = Phase4Baseline::collect($gate);
        $drivers = Phase4Drivers::collect($gate, $phase4);
        if ($notice === 'saved') echo '<div class="notice notice-success is-dismissible"><p>Configuração de desempenho guardada.</p></div>';
        elseif ($notice === 'probe') echo '<div class="notice notice-success is-dismissible"><p>Teste seguro de prontidão concluído.</p></div>';

        echo '<div class="ff-ops-performance-9dj" data-fieldflow-phase4e-admin-mobile-perf-9dj="v1">';

        // Phase 4A: strictly read-only snapshot across existing measurements.
        // Never calculate a live provider charge or issue a billable HTTP request.
        $p95Mobile = (array)($phase4['categories']['mobile'] ?? []);
        $p95Web = (array)($phase4['categories']['web'] ?? []);
        $costReady = !empty($phase4['api_ready']);
        // 9DJ: EUR 0.00 must not be presented as a priced total if calls are unpriced.
        $unpricedApiRequests = max(0, (int)($drivers['unpriced_requests'] ?? 0));
        $apiCost = !$costReady ? 'sem dados' : ($unpricedApiRequests > 0
            ? 'n/d (sem tarifas)' : self::euro((float)$phase4['total_estimated_eur']));
        $risk = !empty($phase4['budget_warning']) || (int)$phase4['google_drift_count']>0
            || (int)$phase4['critical_active']>0 || in_array((string)$phase4['google_health'], ['warning','critical'], true);
        echo '<div class="ff-ops-card" data-fieldflow-phase4-contract="'.esc_attr(Phase4Baseline::CONTRACT).'">';
        echo '<h2 style="margin-top:0">Fase 4, diagnóstico de performance e custos</h2>';
        echo '<p>Fotografia observacional da produção, sem novos pedidos à Google, Azure ou IA. Custos são <strong>estimativas técnicas</strong>, não substituem as faturas dos fornecedores.</p>';
        echo '<div class="ff-ops-grid ff-ops-kpi-grid-9dj">';
        echo '<div class="ff-ops-kpi"><span>P95 API mobile</span><strong>'.(!empty($p95Mobile['samples'])?number_format_i18n(((int)$p95Mobile['p95_ms'])/1000,2).' s':'a recolher').'</strong><small>'.(int)($p95Mobile['samples']??0).' amostras</small></div>';
        echo '<div class="ff-ops-kpi"><span>P95 Portal Web</span><strong>'.(!empty($p95Web['samples'])?number_format_i18n(((int)$p95Web['p95_ms'])/1000,2).' s':'a recolher').'</strong><small>'.(int)($p95Web['samples']??0).' amostras</small></div>';
        echo '<div class="ff-ops-kpi"><span>API estimado, 30 dias</span><strong>'.esc_html($apiCost).'</strong><small>'.(int)$phase4['api_requests'].' pedidos externos registados</small></div>';
        echo '<div class="ff-ops-kpi"><span>Proteção Google</span><strong>'.esc_html(!$costReady?'sem dados':($risk?'rever':'monitorizada')).'</strong><small>'.(int)$phase4['google_blocked_events'].' bloqueios, '.(int)$phase4['google_premium_requests'].' premium</small></div>';
        echo '</div>';
        echo '<p><strong>Subtotal estimado das operações com tarifa:</strong> Google '.esc_html(self::euro((float)$phase4['by_provider_eur']['google'])).', Azure '.esc_html(self::euro((float)$phase4['by_provider_eur']['azure'])).', outros '.esc_html(self::euro((float)$phase4['by_provider_eur']['other'])).'.';
        echo ' <strong>Críticos ativos:</strong> '.(int)$phase4['critical_active'].'. <strong>Desvios Google:</strong> '.(int)$phase4['google_drift_count'].'.</p>';
        if (!$costReady) echo '<p class="description">O API Cost Ledger ainda não tem tabela pronta. Não interpretar a falta de medição como custo zero.</p>';
        if ($unpricedApiRequests > 0) echo '<p class="ff-ops-finops-caution-9dj"><strong>Custo total indeterminado.</strong> Existem '.number_format_i18n($unpricedApiRequests).' chamadas sem tarifa configurada. O subtotal apresentado exclui essas operações; confirma SKUs e faturas Google/Azure.</p>';
        if (empty($phase4['samples'])) echo '<p class="description">Sem amostras suficientes para conclusões de performance. Os valores são recolhidos pela observabilidade existente.</p>';
        echo '<p class="description">Comparar os valores do ledger com a faturação real antes de definir poupanças. <a href="'.esc_url(add_query_arg(['page'=>self::SLUG,'tab'=>'api-costs'],admin_url('admin.php'))).'">Abrir controlo Google e API</a>.</p>';
        echo '</div>';

        // Phase 4B, 9DG: actionable diagnostics using existing aggregated measurements.
        // The card is read only and never calls Google, Azure or external APIs.
        echo '<div class="ff-ops-card" data-fieldflow-phase4-drivers="'.esc_attr(Phase4Drivers::CONTRACT).'">';
        echo '<h2 style="margin-top:0">Fase 4B, prioridades de custos e performance</h2>';
        echo '<p>Identifica operações para investigação, sem alterar políticas, rotas, cache, preços ou limites. <strong>Valores são estimativas do FieldFlow, não faturas.</strong></p>';
        if (empty($drivers['api_ready'])) {
            echo '<p class="description">Sem tabela de medições API disponível. Não interpretar a ausência de dados como ausência de custos.</p>';
        } else {
            echo '<div class="ff-ops-grid ff-ops-kpi-grid-9dj">';
            echo '<div class="ff-ops-kpi"><span>Operações analisadas, 30 dias</span><strong>'.(int)$drivers['operation_rows'].'</strong></div>';
            echo '<div class="ff-ops-kpi"><span>Tarifas de referência em falta</span><strong>'.(int)$drivers['unpriced_operations'].'</strong><small>'.number_format_i18n((int)$drivers['unpriced_requests']).' pedidos sem valorização fiável</small></div>';
            echo '<div class="ff-ops-kpi"><span>Fluxos a investigar</span><strong>'.count((array)$drivers['slow_flows']).'</strong><small>Mínimo de 10 amostras e estado de aviso/bloqueio</small></div>';
            echo '<div class="ff-ops-kpi"><span>Tendência, 7 dias</span><strong>'.($drivers['trend_comparable'] && $drivers['trend_change_pct'] !== null ? esc_html(number_format_i18n((float)$drivers['trend_change_pct'], 1).'%') : 'a recolher').'</strong><small>vs. 7 dias anteriores, não é faturação</small></div>';
            echo '</div>';
            if ((int)$drivers['unpriced_operations'] > 0) {
                echo '<p class="description"><strong>Atenção:</strong> há operações externas com chamadas registadas mas tarifa de referência zero. O custo apresentado pode estar subestimado. Confirma os SKUs e valores no fornecedor antes de tirar conclusões.</p>';
            }
            echo '<h3>Prioridades de investigação</h3><div class="ff-ops-table ff-ops-responsive-table-9dj"><table class="widefat striped"><thead><tr><th>Prioridade</th><th>Fornecedor / operação</th><th>Pedidos</th><th>Cache hits</th><th>Erros</th><th>Custo estimado</th><th>Próxima análise</th></tr></thead><tbody>';
            if (!$drivers['recommendations']) echo '<tr><td colspan="7">Sem operações registadas nesta janela.</td></tr>';
            $priorityLabels=['unknown_cost'=>'TARIFA EM FALTA','premium'=>'TIER PREMIUM','errors'=>'FALHAS','cache'=>'CACHE','cost'=>'CUSTO','blocked'=>'BLOQUEIO','inspect'=>'MONITORIZAR'];
            foreach ((array)$drivers['recommendations'] as $item) {
                $key=(string)$item['priority'];
                $money=!empty($item['cost_unknown']) ? 'n/d (sem tarifa)' : self::euro((float)$item['estimated_eur']);
                echo '<tr><td data-label="Prioridade"><strong>'.esc_html($priorityLabels[$key] ?? 'VERIFICAR').'</strong></td><td data-label="Fornecedor / operação">'.esc_html(self::providerLabel((string)$item['provider'])).'<br><code>'.esc_html((string)$item['operation']).'</code></td><td data-label="Pedidos">'.number_format_i18n((int)$item['requests']).'</td><td data-label="Cache hits">'.number_format_i18n((int)$item['cache_hits']).'</td><td data-label="Erros">'.number_format_i18n((int)$item['errors']).'</td><td data-label="Custo estimado">'.esc_html($money).'</td><td data-label="Próxima análise">'.esc_html((string)$item['reason']).'</td></tr>';
            }
            echo '</tbody></table></div>';
            if ($drivers['slow_flows']) {
                echo '<h3>Fluxos com indicadores de lentidão</h3><div class="ff-ops-table ff-ops-responsive-table-9dj"><table class="widefat striped"><thead><tr><th>Fluxo</th><th>Estado</th><th>Amostras</th><th>P95</th><th>Erros</th></tr></thead><tbody>';
                foreach ((array)$drivers['slow_flows'] as $item) {
                    echo '<tr><td data-label="Fluxo"><strong>'.esc_html((string)$item['label']).'</strong></td><td data-label="Estado">'.esc_html(strtoupper((string)$item['status'])).'</td><td data-label="Amostras">'.number_format_i18n((int)$item['samples']).'</td><td data-label="P95">'.number_format_i18n((int)$item['p95_ms']/1000,2).' s</td><td data-label="Erros">'.number_format_i18n((float)$item['error_rate_pct'],1).'%</td></tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '<p class="description">'.esc_html((string)$drivers['trend_note']).' A lista é indicativa e limitada às operações agregadas mais relevantes. Tarifas configuráveis em <a href="'.esc_url(add_query_arg(['page'=>self::SLUG,'tab'=>'api-costs'],admin_url('admin.php'))).'">Google Cost Center</a>.</p>';
        }
        echo '</div>';


        // 9DH: administrative P95 attributable to recorded page slugs, not a guessed cause.
        // One bounded SELECT only when the Performance tab is visited; no paid calls.
        $adminHotspots = Phase4AdminHotspots::collect((int)$settings['gate_window_hours']);
        echo '<div class="ff-ops-card" data-fieldflow-phase4-admin-hotspots="'.esc_attr(Phase4AdminHotspots::CONTRACT).'">';
        echo '<h2 style="margin-top:0">Fase 4C, gargalos por página do Backoffice</h2>';
        echo '<p>Leitura de amostras existentes, por página. P95 de duração e consultas SQL estimados por operação, sem SQL completo, dados pessoais, novas chamadas externas ou alteração das operações.</p>';
        if (!$adminHotspots['table_ready']) {
            echo '<p class="description">Ainda não existem métricas para esta instalação.</p>';
        } elseif (!$adminHotspots['sample_count']) {
            echo '<p class="description">Ainda não há pedidos administrativos amostrados nesta janela.</p>';
        } else {
            echo '<p><strong>Amostras administrativas:</strong> '.number_format_i18n((int)$adminHotspots['sample_count']).'. <strong>Páginas diferentes:</strong> '.number_format_i18n((int)$adminHotspots['operation_count']).'. <strong>Janela:</strong> '.(int)$adminHotspots['window_hours'].' h.</p>';
            if ($adminHotspots['truncated']) echo '<p class="description">Amostra limitada às 2500 entradas mais recentes. Comparações históricas não são totais.</p>';
            echo '<div class="ff-ops-table ff-ops-responsive-table-9dj"><table class="widefat striped"><thead><tr><th>Página / operação</th><th>Amostras</th><th>P95 tempo</th><th>P95 queries</th><th>Máximo</th><th>Diagnóstico</th></tr></thead><tbody>';
            foreach ($adminHotspots['rows'] as $item) {
                $labels=['critical'=>'INVESTIGAR','warning'=>'ATENÇÃO','collecting'=>'AMOSTRA CURTA','pass'=>'OK'];
                echo '<tr><td data-label="Página / operação"><code>'.esc_html((string)$item['operation']).'</code></td><td data-label="Amostras">'.(int)$item['samples'].'</td><td data-label="P95 tempo">'.esc_html(number_format_i18n(((int)$item['p95_duration_ms'])/1000,2)).' s</td><td data-label="P95 queries">'.number_format_i18n((int)$item['p95_queries']).'</td><td data-label="Máximo">'.esc_html(number_format_i18n(((int)$item['max_duration_ms'])/1000,2)).' s</td><td data-label="Diagnóstico">'.esc_html($labels[(string)$item['status']] ?? 'REVER').'</td></tr>';
            }
            echo '</tbody></table></div>';
            echo '<p class="description">Amostragem indicativa. Não equivale ao tempo de todas as visitas ou a uma medição antes/depois. O diagnóstico não bloqueia certificação.</p>';
        }
        echo '</div>';

        echo '<div class="ff-ops-grid ff-ops-kpi-grid-9dj">';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Gate de performance</span><strong style="font-size:20px">'.esc_html($statusLabels[$status] ?? strtoupper($status)).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Amostras</span><strong>'.number_format_i18n((int)($gate['samples'] ?? 0)).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Saúde das filas</span><strong>'.(int)($queue['score'] ?? 0).'%</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Runtime cron</span><strong style="font-size:16px">'.esc_html(self::cronLabel((string)($cron['driver'] ?? 'wp_cron'))).'</strong></div>';
        echo '</div>';

        echo '<div class="ff-ops-card"><div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap"><div><h2 style="margin-top:0">Performance & Load Readiness 1.0</h2><p>Recolha em modo de observação. Mede duração, número de consultas, memória, taxa de erro e saúde das filas, sem guardar SQL, payloads, utilizadores, IPs ou conteúdos.</p></div><span class="ff-sev '.esc_attr(in_array($status,['fail','blocked'],true)?'critical':($status==='warning'?'warning':'info')).'">'.esc_html($statusLabels[$status] ?? strtoupper($status)).'</span></div>';
        echo '<p><strong>Janela analisada:</strong> '.(int)$gate['window_hours'].' hora(s). <strong>Amostragem:</strong> '.(int)$settings['sample_rate'].'%. <strong>Retenção:</strong> '.(int)$settings['retention_days'].' dias.</p>';
        if ($status === 'collecting') echo '<p class="description">A build está operacional. O gate torna-se conclusivo quando cada fluxo tiver pelo menos '.(int)$settings['minimum_samples'].' amostras reais.</p>';
        echo '</div>';

        echo '<div class="ff-ops-card"><h2>Gate por fluxo</h2><p class="description">Estado de performance em observacao; nao altera os gates da certificacao Stable ou a operacao no terreno.</p><div class="ff-ops-table ff-ops-responsive-table-9dj"><table class="widefat striped"><thead><tr><th>Fluxo</th><th>Estado</th><th>Amostras</th><th>Média</th><th>P95</th><th>Máximo</th><th>Consultas máx.</th><th>Erros</th><th>Memória</th></tr></thead><tbody>';
        $labels = ['rest'=>'REST web','mobile'=>'API mobile','submission'=>'Submissões','export'=>'Exportações','admin'=>'Backoffice','web'=>'Portais web','cron'=>'Cron e filas','probe'=>'Teste seguro'];
        if (empty($gate['categories'])) echo '<tr><td colspan="9">Ainda não existem amostras. Usa o FieldFlow normalmente ou executa o teste seguro abaixo.</td></tr>';
        foreach ((array)($gate['categories'] ?? []) as $category=>$row) {
            $rowStatus = (string)($row['status'] ?? 'collecting');
            $memoryMb = ((int)($row['peak_memory_bytes'] ?? 0)) / MB_IN_BYTES;
            echo '<tr><td data-label="Fluxo"><strong>'.esc_html($labels[$category] ?? ucfirst((string)$category)).'</strong></td><td data-label="Estado"><span class="ff-sev '.esc_attr($rowStatus==='fail'?'critical':($rowStatus==='warning'?'warning':'info')).'">'.esc_html($statusLabels[$rowStatus] ?? strtoupper($rowStatus)).'</span></td><td data-label="Amostras">'.(int)$row['samples'].'</td><td data-label="Média">'.number_format_i18n(((int)$row['avg_duration_ms'])/1000,2).' s</td><td data-label="P95">'.number_format_i18n(((int)$row['p95_duration_ms'])/1000,2).' s</td><td data-label="Máximo">'.number_format_i18n(((int)$row['max_duration_ms'])/1000,2).' s</td><td data-label="Consultas máx.">'.(int)$row['max_queries'].'</td><td data-label="Erros">'.number_format_i18n((float)$row['error_rate_pct'],2).'%</td><td data-label="Memória">'.number_format_i18n($memoryMb,1).' MB</td></tr>';
        }
        echo '</tbody></table></div></div>';

        echo '<div class="ff-ops-grid ff-ops-process-grid-9dj">';
        $modernQueue = is_array($queue['modernization'] ?? null) ? $queue['modernization'] : [];
        $queueDriver = (string)($queue['driver'] ?? 'wp_cron');
        echo '<div class="ff-ops-card"><h2>Filas e processos</h2><p><strong>Driver efetivo:</strong> '.esc_html($queueDriver === 'action_scheduler' ? 'Action Scheduler' : 'WP-Cron').'. <strong>Modo:</strong> '.esc_html((string)($modernQueue['mode'] ?? 'legacy')).'.</p><p><strong>Processos em atraso:</strong> '.(int)($queue['overdue'] ?? 0).'. <strong>Falhas nas últimas 24h:</strong> '.(int)($queue['summary']['failed_24h'] ?? 0).'. <strong>Circuitos abertos:</strong> '.(int)($queue['summary']['circuits_open'] ?? 0).'. <strong>Dead letter:</strong> '.(int)($modernQueue['stats']['dead'] ?? 0).'.</p><p class="description">Action Scheduler '.(!empty($queue['action_scheduler_available'])?'está disponível.':'não está instalado.').' O fallback WP-Cron permanece sempre ativo.</p></div>';
        echo '<div class="ff-ops-card"><h2>Cron de produção</h2><p><strong>Estado:</strong> '.esc_html(self::cronLabel((string)($cron['driver'] ?? 'wp_cron'))).'.</p><p><strong>Último heartbeat:</strong> '.esc_html((string)($cron['last_heartbeat'] ?: 'Ainda não registado')).'.</p><p class="description">Para escala, recomenda-se um cron real do servidor a cada '.(int)$cron['recommended_interval_minutes'].' minutos. O FieldFlow continua compatível com WP-Cron.</p></div>';
        echo '</div>';

        $lastProbe = get_option(LoadReadiness::LAST_PROBE_OPTION, []);
        echo '<div class="ff-ops-card"><h2>Teste seguro de prontidão</h2><p>Executa apenas leituras agregadas nas tabelas centrais. Não cria submissões, não envia notificações e não altera rotas, fotografias, stocks ou despesas.</p>';
        if (is_array($lastProbe) && !empty($lastProbe)) {
            echo '<p><strong>Último teste:</strong> '.esc_html((string)($lastProbe['executed_at'] ?? '')).', '.number_format_i18n(((int)($lastProbe['duration_ms'] ?? 0))/1000,2).' s, '.(int)($lastProbe['query_count'] ?? 0).' consultas, '.(int)($lastProbe['checks'] ?? 0).' verificações.</p>';
            $dbProfile = is_array($lastProbe['database_profile'] ?? null) ? $lastProbe['database_profile'] : [];
            if ($dbProfile) {
                echo '<p><strong>Footprint FieldFlow:</strong> '.(int)($dbProfile['table_count'] ?? 0).' tabelas, '.esc_html(self::bytes((int)($dbProfile['total_bytes'] ?? 0))).' total, '.esc_html(self::bytes((int)($dbProfile['index_bytes'] ?? 0))).' em índices.</p>';
                $largest = is_array($dbProfile['largest_tables'] ?? null) ? $dbProfile['largest_tables'] : [];
                if ($largest) {
                    echo '<div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Tabela</th><th>Linhas estimadas</th><th>Dados</th><th>Índices</th><th>Total</th></tr></thead><tbody>';
                    foreach (array_slice($largest,0,8) as $table) {
                        echo '<tr><td><code>'.esc_html((string)($table['table'] ?? '')).'</code></td><td>'.number_format_i18n((int)($table['rows_estimate'] ?? 0)).'</td><td>'.esc_html(self::bytes((int)($table['data_bytes'] ?? 0))).'</td><td>'.esc_html(self::bytes((int)($table['index_bytes'] ?? 0))).'</td><td><strong>'.esc_html(self::bytes((int)($table['total_bytes'] ?? 0))).'</strong></td></tr>';
                    }
                    echo '</tbody></table></div>';
                }
            }
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_load_readiness_probe');echo '<input type="hidden" name="action" value="fieldflow_load_readiness_probe"><label>Iterações <input type="number" name="iterations" min="1" max="25" value="5" style="width:80px"></label> <button class="button button-secondary">Executar teste seguro</button></form></div>';

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ff-ops-card">';wp_nonce_field('fieldflow_load_readiness_save');echo '<input type="hidden" name="action" value="fieldflow_load_readiness_save"><h2>Observação e limites do release gate</h2><table class="form-table"><tr><th>Recolha</th><td><label><input type="checkbox" name="fieldflow_load_readiness[enabled]" value="1" '.checked(!empty($settings['enabled']),true,false).'> Ativar métricas sem conteúdo pessoal</label></td></tr><tr><th>Amostragem</th><td><input type="number" min="1" max="100" name="fieldflow_load_readiness[sample_rate]" value="'.(int)$settings['sample_rate'].'"> %</td></tr><tr><th>Retenção</th><td><input type="number" min="7" max="180" name="fieldflow_load_readiness[retention_days]" value="'.(int)$settings['retention_days'].'"> dias</td></tr><tr><th>Janela do gate</th><td><input type="number" min="1" max="720" name="fieldflow_load_readiness[gate_window_hours]" value="'.(int)$settings['gate_window_hours'].'"> horas</td></tr><tr><th>Amostra mínima</th><td><input type="number" min="1" max="1000" name="fieldflow_load_readiness[minimum_samples]" value="'.(int)$settings['minimum_samples'].'"></td></tr></table>';
        echo '<div class="ff-ops-table ff-ops-responsive-table-9dj ff-ops-limits-table-9dj"><table class="widefat striped"><thead><tr><th>Fluxo</th><th>Aviso, ms</th><th>Crítico, ms</th><th>Consultas aviso</th><th>Consultas críticas</th><th>Memória, MB</th></tr></thead><tbody>';
        foreach (['rest','mobile','submission','export','admin','web','cron'] as $category) {
            $threshold = $settings['thresholds'][$category];
            echo '<tr><td data-label="Fluxo"><strong>'.esc_html($labels[$category]).'</strong></td>';
            foreach (['warning_ms','critical_ms','warning_queries','critical_queries','memory_mb'] as $field) echo '<td data-label="'.esc_attr(['warning_ms'=>'Aviso, ms','critical_ms'=>'Crítico, ms','warning_queries'=>'Consultas aviso','critical_queries'=>'Consultas críticas','memory_mb'=>'Memória, MB'][$field]).'"><input type="number" min="1" name="fieldflow_load_readiness[thresholds]['.esc_attr($category).']['.esc_attr($field).']" value="'.(int)$threshold[$field].'" style="width:100px"></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div><p><button class="button button-primary">Guardar limites</button></p></form>';
        echo '</div>'; // 9DJ performance-only layout boundary
    }

    private static function cronLabel(string $driver): string {
        return ['wp_cron'=>'WP-Cron ativo','external_cron_detected'=>'Cron real detetado','external_cron_required'=>'Cron real necessário'][$driver] ?? ucfirst(str_replace('_',' ',$driver));
    }

    private static function security(): void {
        $rows=SecurityService::recent(100);
        $denied=SecurityService::authorizationDenialSummary(30);
        $classified=SecurityService::denialClassificationSummary(30);
        echo '<div class="ff-ops-grid">';
        foreach([
            'session_expired'=>'Sessão ausente/expirada',
            'authentication_failed'=>'Falhas de autenticação',
            'authorization_denied'=>'Autorização negada',
            'cross_scope_denied'=>'Cross-scope negado',
            'rate_limited'=>'Rate limited',
            'csrf_nonce_denied'=>'CSRF / nonce recusado',
            'security_policy_denied'=>'Política / entitlement',
            'other_denied'=>'Outras recusas',
        ] as $key=>$label){
            echo '<div class="ff-ops-card ff-ops-kpi"><span>'.esc_html($label).', 30 dias</span><strong>'.number_format_i18n((int)($classified[$key]??0)).'</strong></div>';
        }
        echo '</div>';
        echo '<div class="ff-ops-card"><h2>Leitura das recusas</h2><p>As recusas são classificadas sem alterar o comportamento dos endpoints. Sessões ausentes/expiradas deixam de ser confundidas com tentativas reais de autorização indevida.</p><p><strong>Origem técnica, 30 dias:</strong> REST '.number_format_i18n((int)$denied['rest']).' · AJAX '.number_format_i18n((int)$denied['ajax']).' · admin-post '.number_format_i18n((int)$denied['admin_post']).' · total '.number_format_i18n((int)$denied['total']).'.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:14px">';
        wp_nonce_field('fieldflow_security_export_json');
        echo '<input type="hidden" name="action" value="fieldflow_security_export_json"><input type="hidden" name="days" value="30"><button class="button button-primary">Exportar JSON, 30 dias</button><span class="description">Ficheiro sanitizado para auditoria: sem payloads, tokens, nonces, IPs, device IDs ou IDs individuais de utilizador.</span></form></div>';

        $drilldown=SecurityService::denialDrilldown(30,'authorization_denied',30);
        $drillTotals=SecurityService::denialDrilldownTotals($drilldown);
        echo '<div class="ff-ops-card"><h2>Drill-down · Autorização negada</h2><p>Top 30 combinações técnicas classificadas como autorização negada. A tabela usa apenas metadados de segurança já armazenados: endpoint/ação, método, HTTP status, reason code, contagens e número de atores autenticados. Não expõe payloads nem dados funcionais.</p>';
        if(!$drilldown){
            echo '<p><strong>Sem recusas de autorização para detalhar nos últimos 30 dias.</strong></p>';
        }else{
            echo '<p><strong>Visível nesta tabela:</strong> '.number_format_i18n((int)$drillTotals['occurrences']).' ocorrências em '.number_format_i18n((int)$drillTotals['endpoints']).' combinações · autenticadas '.number_format_i18n((int)$drillTotals['authenticated']).' · anónimas '.number_format_i18n((int)$drillTotals['anonymous']).'.</p>';
            echo '<div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Âmbito</th><th>Origem</th><th>HTTP</th><th>Reason code</th><th>Ocorrências</th><th>Atores</th><th>Anónimas</th><th>Primeira</th><th>Última</th></tr></thead><tbody>';
            foreach($drilldown as $row){
                $surfaceLabel=['rest'=>'REST','ajax'=>'AJAX','admin_post'=>'admin-post'][(string)$row['surface']]??(string)$row['surface'];
                $reason=(string)$row['reason_code'];
                $reasonHtml=$reason!==''?'<code>'.esc_html($reason).'</code>':'<span title="Registo sem reason code técnico, normalmente criado antes da 2.2.321.">— <small>(legado)</small></span>';
                echo '<tr>';
                echo '<td><code>'.esc_html((string)$row['route']).'</code></td>';
                echo '<td>'.esc_html($surfaceLabel).' · '.esc_html((string)$row['method']).'</td>';
                echo '<td>'.(int)$row['status'].'</td>';
                echo '<td>'.$reasonHtml.'</td>';
                echo '<td><strong>'.number_format_i18n((int)$row['occurrences']).'</strong></td>';
                echo '<td>'.number_format_i18n((int)$row['unique_actors']).'<br><small>'.number_format_i18n((int)$row['authenticated_occurrences']).' ocorr.</small></td>';
                echo '<td>'.number_format_i18n((int)$row['anonymous_occurrences']).'</td>';
                echo '<td>'.esc_html((string)($row['first_seen_at']?mysql2date('d/m/Y H:i',(string)$row['first_seen_at']):'—')).'</td>';
                echo '<td>'.esc_html((string)($row['last_seen_at']?mysql2date('d/m/Y H:i',(string)$row['last_seen_at']):'—')).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '<p><small>Leitura recomendada: reason code vazio significa apenas que o registo histórico não tinha detalhe técnico suficiente. Não é automaticamente um incidente.</small></p></div>';

        echo '<div class="ff-ops-card"><h2>Eventos de segurança</h2><p>Recusas 401/403/429 são agregadas por utilizador, ação e origem técnica. O payload do pedido, nonces, tokens, passwords e conteúdo funcional não são armazenados. Registos criados na 2.2.320 são classificados retroativamente em leitura, sem reescrever o histórico.</p><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Tipo</th><th>Classificação</th><th>Âmbito</th><th>Severidade</th><th>Ocorrências</th><th>Última deteção</th></tr></thead><tbody>';
        if(!$rows)echo '<tr><td colspan="6">Sem eventos de segurança.</td></tr>';
        foreach($rows as $row){
            $classification=SecurityService::denialClassificationForRow($row);
            echo '<tr><td><code>'.esc_html((string)$row['event_type']).'</code></td><td>'.esc_html((string)$classification['label']).'</td><td>'.esc_html((string)($row['route']?:$row['device_id']?:'Sistema')).'</td><td><span class="ff-sev '.esc_attr((string)$row['severity']).'">'.esc_html(strtoupper((string)$row['severity'])).'</span></td><td>'.(int)$row['occurrences'].'</td><td>'.esc_html(mysql2date('d/m/Y H:i',(string)$row['last_seen_at'])).'</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function alerts(): void {
        $rows=AlertService::recent(100,'open');
        echo '<div class="ff-ops-card"><h2>Alertas abertos</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Alerta</th><th>Severidade</th><th>Última deteção</th><th>Ação</th></tr></thead><tbody>';
        if(!$rows)echo '<tr><td colspan="4">Sem alertas abertos.</td></tr>';
        foreach($rows as $row)echo '<tr><td><strong>'.esc_html((string)$row['title']).'</strong><br><small>'.esc_html((string)$row['detail']).'</small></td><td><span class="ff-sev '.esc_attr((string)$row['severity']).'">'.esc_html(strtoupper((string)$row['severity'])).'</span></td><td>'.esc_html(mysql2date('d/m/Y H:i',(string)$row['last_seen_at'])).'</td><td>'.self::statusForm('fieldflow_alert_status',(int)$row['id'],(string)$row['status']).'</td></tr>';
        echo '</tbody></table></div></div>';
    }

    private static function settings(): void {
        $s=Settings::get();
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ff-ops-card">';wp_nonce_field('fieldflow_security_settings');echo '<input type="hidden" name="action" value="fieldflow_security_save"><h2>Limites e retenção</h2><table class="form-table">';
        foreach(['public_per_minute'=>'Pedidos públicos por minuto','authenticated_per_minute'=>'Pedidos autenticados por minuto','ai_per_minute'=>'Pedidos de IA por minuto','export_per_minute'=>'Exports por minuto','upload_per_minute'=>'Uploads por minuto'] as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><input type="number" min="5" name="fieldflow_security[rate_limits]['.esc_attr($key).']" value="'.(int)$s['rate_limits'][$key].'"></td></tr>';
        echo '<tr><th>Fotografias FieldFlow</th><td><strong>Sem limite artificial de tamanho</strong><p class="description">As evidências usam transporte retomável por blocos e continuam sujeitas a formato, MIME, autenticação, integridade e capacidade real de armazenamento.</p><input type="hidden" name="fieldflow_security[uploads][max_image_mb]" value="'.(int)$s['uploads']['max_image_mb'].'"></td></tr><tr><th>Limite de documento</th><td><input type="number" min="1" name="fieldflow_security[uploads][max_document_mb]" value="'.(int)$s['uploads']['max_document_mb'].'"> MB</td></tr><tr><th>Extensões permitidas</th><td><input class="large-text" name="fieldflow_security[uploads][allowed_extensions]" value="'.esc_attr((string)$s['uploads']['allowed_extensions']).'"></td></tr>';
        foreach(['slow_rest_ms'=>'Endpoint lento, ms','critical_rest_ms'=>'Endpoint crítico, ms','queue_warning'=>'Fila offline em aviso','queue_critical'=>'Fila offline crítica','storage_warning_mb'=>'Armazenamento em aviso, MB','retention_days'=>'Retenção técnica, dias','security_retention_days'=>'Retenção de segurança, dias'] as $key=>$label)echo '<tr><th>'.esc_html($label).'</th><td><input type="number" min="1" name="fieldflow_security[observability]['.esc_attr($key).']" value="'.(int)$s['observability'][$key].'"></td></tr>';
        echo '<tr><th>Alertas por email</th><td><label><input type="checkbox" name="fieldflow_security[alerts][email_enabled]" value="1" '.checked(!empty($s['alerts']['email_enabled']),true,false).'> Ativar</label><br><input type="email" name="fieldflow_security[alerts][admin_email]" value="'.esc_attr((string)$s['alerts']['admin_email']).'"></td></tr></table><p><button class="button button-primary">Guardar configuração</button></p></form>';
    }

    private static function diagnostics(): void {
        echo '<div class="ff-ops-card"><h2>Pacote de diagnóstico</h2><p>Gera um ZIP com versões, saúde, erros recentes, estado de filas, cron, dispositivos anonimizados e tamanhos de tabelas. Não inclui passwords, tokens, nonces nem chaves de API.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_diagnostic_package');echo '<input type="hidden" name="action" value="fieldflow_generate_diagnostic_package"><button class="button button-primary">Gerar pacote de diagnóstico</button></form></div>';
    }


    private static function authPolicy(): void {
        $uid = get_current_user_id();
        $policy = SessionPolicy::get();
        $status = SessionPolicy::status();
        $summary = Mfa::privilegedSummary();
        $enabled = Mfa::isEnabled($uid);
        $pending = Mfa::pendingSecret($uid);
        $recovery = Mfa::recoveryCodesOnce($uid);
        $notice = sanitize_key((string)($_GET['fieldflow_auth_notice'] ?? ''));
        if ($notice === 'started') echo '<div class="notice notice-info"><p>Chave MFA criada. Adiciona-a ao Authenticator e confirma com um código de 6 dígitos.</p></div>';
        elseif ($notice === 'activated') echo '<div class="notice notice-success"><p>MFA ativado nesta conta.</p></div>';
        elseif ($notice === 'disabled') echo '<div class="notice notice-success"><p>MFA desativado nesta conta.</p></div>';
        elseif ($notice === 'reset') echo '<div class="notice notice-success"><p>MFA da conta selecionada foi reposto. O utilizador deverá configurar novamente.</p></div>';
        elseif ($notice === 'saved') echo '<div class="notice notice-success"><p>Política MFA e de sessões guardada.</p></div>';
        elseif ($notice === 'error') echo '<div class="notice notice-error"><p>'.esc_html((string)($_GET['fieldflow_auth_message'] ?? 'Não foi possível concluir a operação.')).'</p></div>';

        echo '<div class="ff-ops-grid">';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Modo MFA</span><strong style="font-size:20px">'.esc_html(($policy['mfa_mode']??'observe')==='enforce'?'Enforcement':'Observação').'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Contas privilegiadas</span><strong>'.(int)($summary['privileged']??0).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>MFA ativo</span><strong>'.(int)($summary['enabled']??0).'</strong></div>';
        echo '<div class="ff-ops-card ff-ops-kpi"><span>Sessões</span><strong style="font-size:20px">'.esc_html(($policy['session_mode']??'observe')==='enforce'?'Enforcement':'Observação').'</strong></div>';
        echo '</div>';

        echo '<div class="ff-ops-card"><h2>A tua conta administrativa</h2>';
        echo '<p><strong>Estado:</strong> '.($enabled?'<span class="ff-sev low">MFA ATIVO</span>':($pending?'<span class="ff-sev medium">CONFIGURAÇÃO PENDENTE</span>':'<span class="ff-sev warning">MFA INATIVO</span>')).'.</p>';
        if (!$enabled && $pending === '') {
            echo '<p>O FieldFlow usa TOTP padrão, compatível com Microsoft Authenticator, Google Authenticator, 1Password e gestores equivalentes.</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_mfa_begin');echo '<input type="hidden" name="action" value="fieldflow_mfa_begin"><button class="button button-primary">Começar configuração MFA</button></form>';
        } elseif (!$enabled && $pending !== '') {
            $uri = Mfa::otpauthUri($uid, $pending);
            $spaced = trim(chunk_split($pending, 4, ' '));
            echo '<p><strong>1.</strong> No Authenticator escolhe adicionar conta por chave manual.</p>';
            echo '<p><strong>Nome:</strong> FieldFlow<br><strong>Chave secreta:</strong> <code style="font-size:15px;user-select:all">'.esc_html($spaced).'</code></p>';
            echo '<details><summary>URI otpauth técnico</summary><code style="word-break:break-all">'.esc_html($uri).'</code></details>';
            echo '<p><strong>2.</strong> Introduz abaixo o código de 6 dígitos para confirmar.</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_mfa_activate');echo '<input type="hidden" name="action" value="fieldflow_mfa_activate"><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required style="width:150px;font-size:20px;letter-spacing:.18em"> <button class="button button-primary">Ativar MFA</button></form>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="margin-top:10px">';wp_nonce_field('fieldflow_mfa_begin');echo '<input type="hidden" name="action" value="fieldflow_mfa_begin"><button class="button button-secondary">Gerar nova chave</button></form>';
        } else {
            echo '<p>Ativado em <strong>'.esc_html(Mfa::enabledAt($uid) ?: 'data não registada').'</strong>. Códigos de recuperação disponíveis: <strong>'.Mfa::recoveryRemaining($uid).'</strong>.</p>';
            if (($policy['mfa_mode']??'observe') !== 'enforce') {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_mfa_disable');echo '<input type="hidden" name="action" value="fieldflow_mfa_disable"><label>Código atual <input type="text" name="code" autocomplete="one-time-code" required></label> <button class="button button-secondary">Desativar MFA</button></form>';
            } else echo '<p class="description">O MFA não pode ser desativado enquanto o enforcement estiver ativo.</p>';
        }
        if ($recovery) {
            echo '<div class="notice notice-warning" style="margin:18px 0 0"><p><strong>Guarda estes códigos de recuperação agora.</strong> Só serão mostrados uma vez. Cada código funciona uma única vez.</p><p><code style="display:block;white-space:pre-wrap;line-height:1.8">'.esc_html(implode("\n", $recovery)).'</code></p></div>';
        }
        echo '</div>';

        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ff-ops-card">';wp_nonce_field('fieldflow_session_policy_save');echo '<input type="hidden" name="action" value="fieldflow_session_policy_save"><h2>Política de autenticação e sessões</h2><p>O rollout começa em observação para não terminar sessões existentes durante a instalação. Configura e testa o teu MFA e, depois, ativa os dois modos de enforcement.</p><table class="form-table">';
        echo '<tr><th>Modo MFA privilegiado</th><td><select name="fieldflow_session_policy[mfa_mode]"><option value="observe" '.selected((string)$policy['mfa_mode'],'observe',false).'>Observação</option><option value="enforce" '.selected((string)$policy['mfa_mode'],'enforce',false).'>Enforcement</option></select><p class="description">Aplica-se a contas com <code>routespro_manage</code> no WordPress/BO/Desktop.</p></td></tr>';
        echo '<tr><th>Período de adesão MFA</th><td><input type="number" min="0" max="30" name="fieldflow_session_policy[mfa_grace_days]" value="'.(int)$policy['mfa_grace_days'].'"> dias</td></tr>';
        echo '<tr><th>Limites de sessão</th><td><select name="fieldflow_session_policy[session_mode]"><option value="observe" '.selected((string)($policy['session_mode']??'observe'),'observe',false).'>Observação</option><option value="enforce" '.selected((string)($policy['session_mode']??'observe'),'enforce',false).'>Enforcement</option></select><p class="description">Em observação os limites são calculados mas não terminam sessões. Ao ativar, as sessões existentes recebem uma nova janela absoluta a partir desse momento, evitando logout em massa.</p></td></tr>';
        echo '<tr><th>MFA em login mobile privilegiado</th><td><label><input type="checkbox" name="fieldflow_session_policy[mobile_privileged_mfa]" value="1" '.checked(!empty($policy['mobile_privileged_mfa']),true,false).'> Exigir também na API mobile para managers</label><p class="description">Mantém desligado enquanto o FlutterFlow não tiver campo <code>mfa_code</code>. Merchandisers não são afetados.</p></td></tr>';
        echo '<tr><th>BO/Desktop privilegiado</th><td>Inatividade <input type="number" min="15" max="1440" name="fieldflow_session_policy[wp_privileged_idle_minutes]" value="'.(int)$policy['wp_privileged_idle_minutes'].'" style="width:90px"> min · Máximo <input type="number" min="1" max="168" name="fieldflow_session_policy[wp_privileged_absolute_hours]" value="'.(int)$policy['wp_privileged_absolute_hours'].'" style="width:90px"> h</td></tr>';
        echo '<tr><th>App mobile</th><td>Inatividade <input type="number" min="1" max="90" name="fieldflow_session_policy[mobile_idle_days]" value="'.(int)$policy['mobile_idle_days'].'" style="width:90px"> dias · Máximo <input type="number" min="1" max="180" name="fieldflow_session_policy[mobile_absolute_days]" value="'.(int)$policy['mobile_absolute_days'].'" style="width:90px"> dias</td></tr>';
        echo '<tr><th>WebView da app</th><td>Inatividade <input type="number" min="10" max="1440" name="fieldflow_session_policy[web_idle_minutes]" value="'.(int)$policy['web_idle_minutes'].'" style="width:90px"> min · Máximo <input type="number" min="1" max="168" name="fieldflow_session_policy[web_absolute_hours]" value="'.(int)$policy['web_absolute_hours'].'" style="width:90px"> h<p class="description">Quando expira, o portal reabre a partir da sessão mobile. Não força password ao merchandiser.</p></td></tr>';
        echo '</table><p><button class="button button-primary">Guardar política</button></p></form>';

        echo '<div class="ff-ops-card"><h2>Contas privilegiadas</h2><div class="ff-ops-table"><table class="widefat striped"><thead><tr><th>Utilizador</th><th>MFA</th><th>Ativado</th><th>Recuperação</th><th>Ação</th></tr></thead><tbody>';
        foreach ((array)($summary['rows']??[]) as $row) {
            $rid=(int)$row['id'];
            echo '<tr><td><strong>'.esc_html((string)$row['display_name']).'</strong><br><small>'.esc_html((string)$row['user_login']).'</small></td><td>'.(!empty($row['enabled'])?'<span class="ff-sev low">ATIVO</span>':(!empty($row['pending'])?'<span class="ff-sev medium">PENDENTE</span>':'<span class="ff-sev warning">INATIVO</span>')).'</td><td>'.esc_html((string)($row['enabled_at']?:'—')).'</td><td>'.(int)($row['recovery_remaining']??0).'</td><td>';
            if ($rid !== $uid && (!empty($row['enabled']) || !empty($row['pending']))) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('fieldflow_mfa_reset_user_'.$rid);echo '<input type="hidden" name="action" value="fieldflow_mfa_reset_user"><input type="hidden" name="user_id" value="'.$rid.'"><button class="button button-small" onclick="return confirm(\'Repor o MFA desta conta?\')">Repor MFA</button></form>'; }
            else echo '—';
            echo '</td></tr>';
        }
        if (empty($summary['rows'])) echo '<tr><td colspan="5">Sem contas privilegiadas encontradas.</td></tr>';
        echo '</tbody></table></div><p class="description">Não são mostradas chaves TOTP, códigos de recuperação nem tokens de sessão. O reset deixa uma janela de adesão controlada quando o enforcement está ativo.</p></div>';
    }

    public static function mfaBegin(): void {
        self::requireManager(); check_admin_referer('fieldflow_mfa_begin');
        $result=Mfa::beginEnrollment(get_current_user_id());
        self::authRedirect(is_wp_error($result)?'error':'started',is_wp_error($result)?$result->get_error_message():'');
    }

    public static function mfaActivate(): void {
        self::requireManager(); check_admin_referer('fieldflow_mfa_activate');
        $result=Mfa::activate(get_current_user_id(),sanitize_text_field((string)($_POST['code']??'')));
        self::authRedirect(is_wp_error($result)?'error':'activated',is_wp_error($result)?$result->get_error_message():'');
    }

    public static function mfaDisable(): void {
        self::requireManager(); check_admin_referer('fieldflow_mfa_disable');
        $result=Mfa::disable(get_current_user_id(),sanitize_text_field((string)($_POST['code']??'')));
        self::authRedirect(is_wp_error($result)?'error':'disabled',is_wp_error($result)?$result->get_error_message():'');
    }

    public static function mfaResetUser(): void {
        self::requireManager(); $target=absint($_POST['user_id']??0); check_admin_referer('fieldflow_mfa_reset_user_'.$target);
        if ($target<=0 || $target===get_current_user_id() || !SessionPolicy::isPrivilegedUser($target)) self::authRedirect('error','Conta privilegiada inválida para reset.');
        Mfa::resetUser($target); self::authRedirect('reset','');
    }

    public static function saveSessionPolicy(): void {
        self::requireManager(); check_admin_referer('fieldflow_session_policy_save');
        $raw=is_array($_POST['fieldflow_session_policy']??null)?wp_unslash($_POST['fieldflow_session_policy']):[];
        $raw['mobile_privileged_mfa']=empty($raw['mobile_privileged_mfa'])?0:1;
        $result=SessionPolicy::save($raw);
        self::authRedirect(is_wp_error($result)?'error':'saved',is_wp_error($result)?$result->get_error_message():'');
    }

    private static function requireManager(): void { if(!current_user_can('routespro_manage')) wp_die('Sem permissões.'); }
    private static function authRedirect(string $notice,string $message=''): void {
        $args=['page'=>self::SLUG,'tab'=>'auth','fieldflow_auth_notice'=>$notice];
        if($message!=='')$args['fieldflow_auth_message']=sanitize_text_field($message);
        wp_safe_redirect(add_query_arg($args,admin_url('admin.php'))); exit;
    }

    private static function statusForm(string $action,int $id,string $status): string {
        if($status!=='open') return esc_html(ucfirst($status));
        ob_start();echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field($action);echo '<input type="hidden" name="action" value="'.esc_attr($action).'"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="status" value="acknowledged"><button class="button button-small">Reconhecer</button></form>';return (string)ob_get_clean();
    }

    public static function exportSecurityJson(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_security_export_json');
        $days = max(1, min(365, absint($_POST['days'] ?? 30)));

        $classificationKeys = [
            'session_expired','authentication_failed','authorization_denied','cross_scope_denied',
            'rate_limited','csrf_nonce_denied','security_policy_denied','other_denied',
        ];
        $drilldowns = [];
        foreach ($classificationKeys as $key) {
            $rows = SecurityService::denialDrilldown($days, $key, 1000);
            $drilldowns[$key] = [
                'label' => SecurityService::denialClassificationLabel($key),
                'totals' => SecurityService::denialDrilldownTotals($rows),
                'rows' => $rows,
            ];
        }

        $now = time();
        $payload = [
            'schema' => 'fieldflow.security-observability.export',
            'schema_version' => '1.0',
            'generated_at_utc' => gmdate('c', $now),
            'fieldflow_version' => defined('FIELDFLOW_VERSION') ? (string)FIELDFLOW_VERSION : '',
            'period' => [
                'days' => $days,
                'from_utc' => gmdate('c', $now - ($days * DAY_IN_SECONDS)),
                'to_utc' => gmdate('c', $now),
            ],
            'privacy' => [
                'sanitized' => true,
                'request_payloads_included' => false,
                'passwords_included' => false,
                'tokens_included' => false,
                'nonces_included' => false,
                'cookies_included' => false,
                'ip_addresses_or_hashes_included' => false,
                'device_ids_included' => false,
                'individual_user_ids_included' => false,
                'functional_content_included' => false,
            ],
            'denials' => [
                'origin_by_surface' => SecurityService::authorizationDenialSummary($days),
                'classification_summary' => SecurityService::denialClassificationSummary($days),
                'drilldown_by_classification' => $drilldowns,
            ],
            'security_events' => SecurityService::exportSafeEvents($days, 5000),
        ];

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') wp_die('Não foi possível gerar a exportação JSON.');

        $filename = 'fieldflow-security-observability-' . gmdate('Ymd-His', $now) . 'Z.json';
        while (ob_get_level() > 0) { @ob_end_clean(); }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, private, max-age=0');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }

    public static function saveMediaTelemetrySettings(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_media_telemetry_settings');
        $raw = is_array($_POST['fieldflow_media_telemetry'] ?? null) ? wp_unslash($_POST['fieldflow_media_telemetry']) : [];
        Config::mergeAndSave([
            'media_telemetry_enabled' => empty($raw['enabled']) ? 0 : 1,
            'media_telemetry_retention_days' => max(7,min(3650,absint($raw['retention_days'] ?? 90))),
        ]);
        wp_safe_redirect(add_query_arg(['page'=>self::SLUG,'tab'=>'media','fieldflow_media_notice'=>'saved'],admin_url('admin.php')));
        exit;
    }

    public static function saveApiCostSettings(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer('fieldflow_api_cost_settings');
        $raw = is_array($_POST['fieldflow_api_cost'] ?? null) ? wp_unslash($_POST['fieldflow_api_cost']) : [];
        $allowed = [
            'api_cost_ledger_enabled','api_usage_retention_days','api_monthly_budget_eur',
            'google_cost_control_enabled','google_cost_block_matrix','google_cost_block_pro','google_cost_block_enterprise','google_cost_alert_premium',
            'api_rate_google_routes_compute_routes_essentials_per_1000_eur','api_rate_google_routes_compute_routes_pro_per_1000_eur','api_rate_google_routes_compute_routes_enterprise_per_1000_eur',
            'api_rate_google_routes_compute_route_matrix_essentials_per_1000_eur','api_rate_google_routes_compute_route_matrix_pro_per_1000_eur','api_rate_google_routes_compute_route_matrix_enterprise_per_1000_eur',
            'api_rate_google_routes_route_per_1000_eur','api_rate_google_routes_matrix_element_per_1000_eur',
            'api_rate_google_maps_geocode_per_1000_eur','api_rate_google_maps_matrix_element_per_1000_eur','api_rate_google_maps_directions_per_1000_eur','api_rate_google_maps_places_per_1000_eur',
            'api_rate_azure_maps_geocode_per_1000_eur','api_rate_azure_maps_matrix_element_per_1000_eur','api_rate_azure_maps_directions_per_1000_eur','api_rate_azure_maps_places_per_1000_eur',
        ];
        $checkboxes = ['api_cost_ledger_enabled','google_cost_control_enabled','google_cost_block_matrix','google_cost_block_pro','google_cost_block_enterprise','google_cost_alert_premium'];
        $clean = [];
        foreach ($checkboxes as $key) $clean[$key] = empty($raw[$key]) ? 0 : 1;
        foreach ($allowed as $key) {
            if (in_array($key,$checkboxes,true) || !array_key_exists($key,$raw)) continue;
            if ($key === 'api_usage_retention_days') $clean[$key] = max(7,min(3650,absint($raw[$key])));
            else $clean[$key] = max(0,(float)str_replace(',','.',(string)$raw[$key]));
        }
        Config::mergeAndSave($clean);
        wp_safe_redirect(add_query_arg(['page'=>self::SLUG,'tab'=>'api-costs','fieldflow_api_cost_notice'=>'saved'],admin_url('admin.php')));
        exit;
    }

    public static function observationStatus(): void {
        if(!current_user_can('routespro_manage'))wp_die('Sem permissões.');check_admin_referer('fieldflow_observation_status');global $wpdb;$id=absint($_POST['id']??0);$status=sanitize_key((string)($_POST['status']??'acknowledged'));if(!in_array($status,['open','acknowledged','resolved'],true))$status='acknowledged';$wpdb->update(Monitor::tableName(),['status'=>$status,'acknowledged_by'=>get_current_user_id(),'acknowledged_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)],['id'=>$id]);wp_safe_redirect(add_query_arg(['page'=>self::SLUG,'tab'=>'observability'],admin_url('admin.php')));exit;
    }
    public static function alertStatus(): void {
        if(!current_user_can('routespro_manage'))wp_die('Sem permissões.');check_admin_referer('fieldflow_alert_status');AlertService::setStatus(absint($_POST['id']??0),sanitize_key((string)($_POST['status']??'acknowledged')));wp_safe_redirect(add_query_arg(['page'=>self::SLUG,'tab'=>'alerts'],admin_url('admin.php')));exit;
    }
}
