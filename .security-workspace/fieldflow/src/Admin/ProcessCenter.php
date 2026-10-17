<?php
namespace RoutesPro\Admin;

use RoutesPro\Operations\ProcessMonitor;
use RoutesPro\Operations\Queue\QueueManager;
use RoutesPro\Operations\Queue\QueueRepository;
use RoutesPro\Operations\Queue\QueueSettings;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

final class ProcessCenter {
    public const SLUG = 'routespro-processes';

    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_process_run', [self::class, 'handleRun']);
        add_action('admin_post_fieldflow_process_retry', [self::class, 'handleRetry']);
        add_action('admin_post_fieldflow_process_cleanup', [self::class, 'handleCleanup']);
        add_action('admin_post_fieldflow_process_recover', [self::class, 'handleRecover']);
        add_action('admin_post_fieldflow_process_circuit_reset', [self::class, 'handleCircuitReset']);
        add_action('admin_post_fieldflow_queue_settings_save', [self::class, 'handleQueueSettings']);
        add_action('admin_post_fieldflow_queue_probe', [self::class, 'handleQueueProbe']);
        add_action('admin_post_fieldflow_queue_fallback', [self::class, 'handleQueueFallback']);
        add_action('admin_post_fieldflow_queue_retry_dead', [self::class, 'handleQueueRetryDead']);
    }

    public static function handleRun(): void {
        self::guard('fieldflow_process_run');
        $key = sanitize_key((string)($_POST['process_key'] ?? ''));
        $result = ProcessMonitor::queueNow($key);
        self::redirect(!empty($result['ok']) ? 'queued' : (!empty($result['duplicate']) ? 'duplicate' : 'error'), (string)($result['message'] ?? ''));
    }

    public static function handleRetry(): void {
        self::guard('fieldflow_process_retry');
        $result = ProcessMonitor::retry(absint($_POST['run_id'] ?? 0));
        self::redirect(!empty($result['ok']) ? 'retried' : (!empty($result['duplicate']) ? 'duplicate' : 'error'), (string)($result['message'] ?? ''));
    }

    public static function handleCleanup(): void {
        self::guard('fieldflow_process_cleanup');
        ProcessMonitor::cleanup();
        self::redirect('cleaned', 'Histórico antigo limpo e execuções bloqueadas verificadas.');
    }

    public static function handleRecover(): void {
        self::guard('fieldflow_process_recover');
        $result = ProcessMonitor::recoverBlocked();
        self::redirect('recovered', (int)$result['stale'] . ' bloqueio(s) identificados e ' . (int)$result['scheduled'] . ' recuperação(ões) agendadas.');
    }

    public static function handleCircuitReset(): void {
        self::guard('fieldflow_process_circuit_reset');
        $key = sanitize_key((string)($_POST['process_key'] ?? ''));
        $ok = ProcessMonitor::resetCircuit($key);
        self::redirect($ok ? 'circuit_reset' : 'error', $ok ? 'Circuito reposto. A próxima execução funcionará como teste controlado.' : 'Não foi possível repor o circuito.');
    }

    public static function handleQueueSettings(): void {
        self::guard('fieldflow_queue_settings_save');
        $previous = QueueSettings::get();
        $input = isset($_POST['fieldflow_queue']) && is_array($_POST['fieldflow_queue']) ? wp_unslash($_POST['fieldflow_queue']) : [];
        $settings = QueueSettings::save($input);
        $fallback = ['migrated'=>0,'failed'=>0];
        if ((string)$previous['mode'] === 'pilot' && (string)$settings['mode'] !== 'pilot') $fallback = QueueRepository::fallbackPendingToLegacy(500);
        self::redirect('queue_saved', 'Fila moderna guardada em modo ' . (string)$settings['mode'] . '. Jobs transferidos para WP-Cron: ' . (int)$fallback['migrated'] . '.');
    }

    public static function handleQueueProbe(): void {
        self::guard('fieldflow_queue_probe');
        $result = QueueManager::runProbe('backoffice');
        self::redirect(!empty($result['ok']) ? 'queue_probe' : 'error', (string)($result['message'] ?? 'Teste concluído.'));
    }

    public static function handleQueueFallback(): void {
        self::guard('fieldflow_queue_fallback');
        QueueSettings::emergencyFallback(get_current_user_id());
        $fallback = QueueRepository::fallbackPendingToLegacy(500);
        self::redirect('queue_fallback', 'O WP-Cron voltou a ser o proprietário. Jobs transferidos: ' . (int)$fallback['migrated'] . ', pendentes de nova tentativa: ' . (int)$fallback['failed'] . '.');
    }

    public static function handleQueueRetryDead(): void {
        self::guard('fieldflow_queue_retry_dead');
        $ok = QueueRepository::requeueDead(absint($_POST['job_id'] ?? 0));
        if ($ok) QueueManager::signalDispatcher(1);
        self::redirect($ok ? 'queue_retried' : 'error', $ok ? 'Job recolocado na fila com o contador de tentativas reiniciado.' : 'Não foi possível recolocar o job na fila.');
    }

    private static function guard(string $nonceAction): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer($nonceAction);
    }

    private static function redirect(string $notice, string $message = ''): void {
        $url = add_query_arg([
            'page' => self::SLUG,
            'ff_process_notice' => sanitize_key($notice),
            'ff_process_message' => substr(sanitize_text_field($message), 0, 180),
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        ProcessMonitor::markStale();

        $filters = [
            'status' => sanitize_key((string)($_GET['status'] ?? '')),
            'type' => sanitize_text_field((string)($_GET['type'] ?? '')),
            'process' => sanitize_key((string)($_GET['process'] ?? '')),
        ];
        $summary = ProcessMonitor::summary();
        $health = ProcessMonitor::operationalHealth();
        $overview = ProcessMonitor::overview();
        $circuits = ProcessMonitor::circuits();
        $terminal = ProcessMonitor::terminalFailures(50);
        $rows = ProcessMonitor::recent($filters, 150);
        $definitions = ProcessMonitor::definitions();
        $types = [];
        foreach ($definitions as $definition) $types[(string)$definition['type']] = (string)$definition['type'];
        ksort($types);

        AdminPage::open('Centro de Processos', 'Recuperação operacional, backoff progressivo, circuitos de proteção, histórico e diagnóstico.');
        self::styles();
        self::notice();

        echo '<div class="ffpc-guard"><strong>Compatibilidade preservada</strong><span>Este centro observa e agenda os mesmos hooks WordPress já usados pelo FieldFlow. Não altera contratos FlutterFlow, mobile v1.7, WebView, formulários, rotas, offline, exportações ou integrações existentes.</span></div>';
        echo '<div class="ffpc-kpis">';
        self::kpi('Saúde operacional', (int)$health['score'] . '%', (string)$health['status']);
        self::kpi('Pendentes', (int)$summary['pending'], 'pending');
        self::kpi('Em execução', (int)$summary['running'], 'running');
        self::kpi('Recuperados, 24 h', (int)$summary['recovered_24h'], 'completed');
        self::kpi('Falhados, 24 h', (int)$summary['failed_24h'], 'failed');
        self::kpi('Falhas definitivas', (int)$summary['terminal'], 'failed');
        echo '</div>';

        self::recoveryPanel($health, $circuits, $terminal, $definitions);
        self::queuePanel();
        SchemaIntegrityCenter::renderProcessPanel();
        RouteOptimizationReconciliation::renderProcessPanel();

        echo '<div class="ffpc-card"><div class="ffpc-card-head"><div><span class="ffpc-kicker">Operação atual</span><h2>Mapa de processos</h2><p>Última execução observada e próximo evento no WP-Cron.</p></div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fieldflow_process_cleanup">';
        wp_nonce_field('fieldflow_process_cleanup');
        echo '<button class="button">Verificar bloqueios e limpar histórico</button></form></div><div class="ffpc-table"><table class="widefat striped"><thead><tr><th>Processo</th><th>Estado</th><th>Última execução</th><th>Próxima execução</th><th>Duração</th><th>Ação segura</th></tr></thead><tbody>';
        foreach ($overview as $process) self::overviewRow($process);
        echo '</tbody></table></div></div>';

        echo '<div class="ffpc-card"><div class="ffpc-card-head"><div><span class="ffpc-kicker">Auditoria</span><h2>Histórico de execuções</h2><p>Argumentos são limitados e segredos são ocultados antes do registo.</p></div></div>';
        self::filters($filters, $definitions, $types);
        echo '<div class="ffpc-table"><table class="widefat striped"><thead><tr><th>ID</th><th>Processo</th><th>Estado</th><th>Origem</th><th>Tentativa</th><th>Agendado</th><th>Início</th><th>Fim</th><th>Resultado</th><th>Ação</th></tr></thead><tbody>';
        if (!$rows) echo '<tr><td colspan="10">Ainda não existem execuções para os filtros selecionados.</td></tr>';
        foreach ($rows as $row) self::historyRow($row, $definitions);
        echo '</tbody></table></div></div>';
        AdminPage::close();
    }

    private static function styles(): void {
        echo '<style>
        .ffpc-guard{display:grid;grid-template-columns:auto 1fr;gap:14px;align-items:center;margin:18px 0;padding:15px 17px;border:1px solid #bfdbfe;border-radius:16px;background:#eff6ff;color:#1e3a8a}.ffpc-guard strong{white-space:nowrap}.ffpc-guard span{color:#334155}
        .ffpc-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0}.ffpc-kpi,.ffpc-card{background:#fff;border:1px solid #dbe5ef;border-radius:18px;box-shadow:0 14px 34px rgba(15,23,42,.055)}.ffpc-kpi{padding:17px}.ffpc-kpi span{display:block;color:#64748b;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.ffpc-kpi strong{display:block;margin-top:5px;font-size:30px;color:#0f172a}.ffpc-kpi.failed strong,.ffpc-kpi.critical strong{color:#b91c1c}.ffpc-kpi.running strong,.ffpc-kpi.attention strong{color:#1d4ed8}.ffpc-kpi.healthy strong{color:#166534}
        .ffpc-card{padding:20px;margin:16px 0}.ffpc-card-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:15px}.ffpc-card-head h2{margin:3px 0 5px}.ffpc-card-head p{margin:0;color:#64748b}.ffpc-kicker{display:block;color:#2563eb;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}.ffpc-table{overflow:auto}.ffpc-table table{min-width:1020px}.ffpc-table td{vertical-align:top}.ffpc-process small,.ffpc-result small{display:block;color:#64748b;margin-top:4px}.ffpc-status{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:850;background:#e2e8f0;color:#475569}.ffpc-status.pending,.ffpc-status.paused,.ffpc-status.half_open{background:#fef3c7;color:#92400e}.ffpc-status.running{background:#dbeafe;color:#1d4ed8}.ffpc-status.completed,.ffpc-status.closed,.ffpc-status.recovered{background:#dcfce7;color:#166534}.ffpc-status.failed,.ffpc-status.cancelled,.ffpc-status.overdue,.ffpc-status.open,.ffpc-status.terminal{background:#fee2e2;color:#991b1b}.ffpc-error{display:block;margin-top:5px;color:#991b1b}.ffpc-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin:0 0 15px}.ffpc-filters label{display:grid;gap:5px;font-weight:700;color:#334155}.ffpc-filters select{min-width:170px}.ffpc-actions{display:flex;gap:7px;flex-wrap:wrap;align-items:center}.ffpc-actions form{margin:0}.ffpc-muted{color:#64748b}.ffpc-recovery-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.ffpc-subcard{padding:16px;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc}.ffpc-subcard h3{margin-top:0}.ffpc-policy{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}.ffpc-policy span{padding:4px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:800}
        @media(max-width:950px){.ffpc-kpis{grid-template-columns:repeat(2,1fr)}.ffpc-recovery-grid{grid-template-columns:1fr}.ffpc-guard{grid-template-columns:1fr}.ffpc-card-head{flex-direction:column}}@media(max-width:520px){.ffpc-kpis{grid-template-columns:1fr}}
        </style>';
    }

    private static function notice(): void {
        $notice = sanitize_key((string)($_GET['ff_process_notice'] ?? ''));
        if (!$notice) return;
        $messages = [
            'queued' => 'Processo agendado para execução imediata.',
            'retried' => 'Reexecução segura agendada.',
            'duplicate' => 'O pedido não foi duplicado porque já existe uma execução recente.',
            'cleaned' => 'Verificação operacional concluída.',
            'recovered' => 'Verificação e recuperação dos processos bloqueados concluída.',
            'circuit_reset' => 'Circuito de proteção reposto.',
            'queue_saved' => 'Configuração da fila moderna guardada.',
            'queue_probe' => 'Teste seguro da fila moderna concluído.',
            'queue_fallback' => 'Fallback integral para WP-Cron ativado.',
            'queue_retried' => 'Job recolocado na fila moderna.',
            'error' => 'Não foi possível agendar o processo.',
        ];
        $message = sanitize_text_field((string)($_GET['ff_process_message'] ?? '')) ?: ($messages[$notice] ?? 'Operação concluída.');
        AdminPage::notice($message, $notice === 'error' ? 'error' : ($notice === 'duplicate' ? 'notice notice-warning' : 'updated'));
    }

    private static function recoveryPanel(array $health, array $circuits, array $terminal, array $definitions): void {
        $circuitMap = [];
        foreach ($circuits as $circuit) $circuitMap[(string)$circuit['process_key']] = $circuit;
        $automatic = 0;
        foreach ($definitions as $definition) if (!empty($definition['autoRetry'])) $automatic++;
        echo '<div class="ffpc-card"><div class="ffpc-card-head"><div><span class="ffpc-kicker">Recuperação operacional</span><h2>Proteção automática</h2><p>Backoff progressivo, fila terminal e circuitos de proteção sem alterar o comportamento funcional dos jobs.</p></div><div class="ffpc-actions">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fieldflow_process_recover">';
        wp_nonce_field('fieldflow_process_recover');
        echo '<button class="button button-primary">Recuperar bloqueios seguros</button></form>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fieldflow_generate_diagnostic_package">';
        wp_nonce_field('fieldflow_diagnostic_package');
        echo '<button class="button">Descarregar diagnóstico</button></form></div></div>';
        echo '<div class="ffpc-recovery-grid"><div class="ffpc-subcard"><h3>Política ativa</h3><p><strong>' . (int)$automatic . '</strong> processos idempotentes podem recuperar automaticamente após erro fatal, interrupção ou bloqueio.</p><div class="ffpc-policy"><span>1.ª tentativa, 1 minuto</span><span>2.ª tentativa, 5 minutos</span><span>3.ª tentativa, 15 minutos</span><span>Sem retry de relatórios</span><span>Sem duplicar integrações pós-submissão</span></div><p class="ffpc-muted">Saúde atual: ' . (int)$health['score'] . '%. Processos em atraso: ' . (int)$health['overdue'] . '.</p></div>';
        echo '<div class="ffpc-subcard"><h3>Circuitos externos</h3><div class="ffpc-table"><table class="widefat striped"><thead><tr><th>Processo</th><th>Estado</th><th>Falhas</th><th>Proteção até</th><th></th></tr></thead><tbody>';
        $hasCircuit = false;
        foreach ($definitions as $key => $definition) {
            if (empty($definition['circuit'])) continue;
            $hasCircuit = true;
            $circuit = $circuitMap[$key] ?? ProcessMonitor::circuitState($key);
            $state = sanitize_key((string)($circuit['state'] ?? 'closed'));
            echo '<tr><td><strong>' . esc_html((string)$definition['label']) . '</strong></td><td><span class="ffpc-status ' . esc_attr($state) . '">' . esc_html(self::circuitLabel($state)) . '</span></td><td>' . (int)($circuit['failure_count'] ?? 0) . '</td><td>' . esc_html(self::date((string)($circuit['retry_after'] ?? ''))) . '</td><td>';
            if (in_array($state, ['open', 'half_open'], true)) self::circuitResetForm($key);
            else echo '<span class="ffpc-muted">Sem ação</span>';
            echo '</td></tr>';
        }
        if (!$hasCircuit) echo '<tr><td colspan="5">Não existem circuitos configurados.</td></tr>';
        echo '</tbody></table></div></div></div>';
        echo '<div class="ffpc-table" style="margin-top:16px"><h3>Fila de falhas definitivas</h3><table class="widefat striped"><thead><tr><th>ID</th><th>Processo</th><th>Tentativas de recuperação</th><th>Erro</th><th>Data</th><th>Ação</th></tr></thead><tbody>';
        if (!$terminal) echo '<tr><td colspan="6">Sem falhas definitivas. É exatamente o tipo de silêncio de que gostamos.</td></tr>';
        foreach (array_slice($terminal, 0, 20) as $row) {
            $definition = $definitions[(string)$row['process_key']] ?? [];
            echo '<tr><td>#' . (int)$row['id'] . '</td><td><strong>' . esc_html((string)($definition['label'] ?? $row['process_key'])) . '</strong></td><td>' . (int)$row['recovery_attempt'] . '</td><td>' . esc_html((string)($row['message'] ?: $row['error_code'])) . '</td><td>' . esc_html(self::date((string)$row['terminal_at'])) . '</td><td>';
            if (!empty($definition['retryable'])) self::retryForm((int)$row['id']);
            else echo '<span class="ffpc-muted">Revisão manual necessária</span>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function queuePanel(): void {
        $settings = QueueSettings::get();
        $status = QueueManager::status();
        $stats = (array)($status['stats'] ?? []);
        $hooks = QueueSettings::allowedHooks();
        unset($hooks['fieldflow_queue_health_probe']);
        $recent = QueueRepository::recent(30);
        $modeLabels = ['legacy'=>'Legado, WP-Cron','observe'=>'Observação','pilot'=>'Piloto controlado'];
        $driverLabels = ['auto'=>'Automático','wp_cron'=>'WP-Cron','action_scheduler'=>'Action Scheduler'];

        echo '<div class="ffpc-card"><div class="ffpc-card-head"><div><span class="ffpc-kicker">Architecture & Queue Modernization 1.0</span><h2>Fila de tarefas robusta</h2><p>Armazenamento próprio, deduplicação, locks atómicos, backoff e fila de falhas definitivas, com fallback integral para WP-Cron.</p></div><span class="ffpc-status '.esc_attr((string)$status['status']).'">'.esc_html(strtoupper((string)$status['status'])).'</span></div>';
        echo '<div class="ffpc-kpis">';
        self::kpi('Modo', (string)($modeLabels[$settings['mode']] ?? $settings['mode']), (string)$settings['mode']);
        self::kpi('Driver efetivo', (string)($driverLabels[$status['effective_driver']] ?? $status['effective_driver']), 'running');
        self::kpi('Pendentes', (int)($stats['pending'] ?? 0), 'pending');
        self::kpi('Em execução', (int)($stats['running'] ?? 0), 'running');
        self::kpi('Concluídos, 24 h', (int)($stats['completed_24h'] ?? 0), 'completed');
        self::kpi('Dead letter', (int)($stats['dead'] ?? 0), 'failed');
        echo '</div>';

        echo '<div class="ffpc-recovery-grid"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ffpc-subcard">';
        wp_nonce_field('fieldflow_queue_settings_save');
        echo '<input type="hidden" name="action" value="fieldflow_queue_settings_save"><h3>Rollout controlado</h3><table class="form-table"><tr><th>Modo</th><td><select name="fieldflow_queue[mode]">';
        foreach ($modeLabels as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($settings['mode'],$key,false).'>'.esc_html($label).'</option>';
        echo '</select><p class="description">Observação regista a oportunidade, mas mantém o WP-Cron como proprietário. Piloto exige pelo menos um hook selecionado.</p></td></tr><tr><th>Driver</th><td><select name="fieldflow_queue[driver]">';
        foreach ($driverLabels as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($settings['driver'],$key,false).'>'.esc_html($label).'</option>';
        echo '</select><p class="description">Action Scheduler '.(!empty($status['action_scheduler_available'])?'está disponível.':'não está disponível, será usado WP-Cron automaticamente.').'</p></td></tr><tr><th>Jobs do piloto</th><td>';
        foreach ($hooks as $hook=>$definition) {
            echo '<label style="display:block;margin:0 0 7px"><input type="checkbox" name="fieldflow_queue[pilot_hooks][]" value="'.esc_attr($hook).'" '.checked(in_array($hook,$settings['pilot_hooks'],true),true,false).'> '.esc_html((string)$definition['label']).' <code>'.esc_html($hook).'</code></label>';
        }
        echo '</td></tr><tr><th>Lote</th><td><input type="number" min="1" max="50" name="fieldflow_queue[batch_size]" value="'.(int)$settings['batch_size'].'"> jobs</td></tr><tr><th>Runtime máximo</th><td><input type="number" min="5" max="50" name="fieldflow_queue[max_runtime_seconds]" value="'.(int)$settings['max_runtime_seconds'].'"> segundos</td></tr><tr><th>Lock expirado</th><td><input type="number" min="120" max="3600" name="fieldflow_queue[lock_ttl_seconds]" value="'.(int)$settings['lock_ttl_seconds'].'"> segundos</td></tr><tr><th>Retenção</th><td><input type="number" min="7" max="180" name="fieldflow_queue[retention_days]" value="'.(int)$settings['retention_days'].'"> dias</td></tr><tr><th>Diagnóstico</th><td><label><input type="checkbox" name="fieldflow_queue[diagnostics]" value="1" '.checked(!empty($settings['diagnostics']),true,false).'> Manter métricas técnicas sem payloads no painel</label></td></tr></table><p><button class="button button-primary">Guardar rollout</button></p></form>';

        echo '<div class="ffpc-subcard"><h3>Validação e reversão</h3><p><strong>Tabela:</strong> '.(!empty($status['table_ready'])?'pronta':'em falta').'. <strong>Heartbeat:</strong> '.esc_html((string)($status['last_heartbeat'] ?: 'Ainda sem execução')).'.</p><p><strong>Jobs observados, 24 h:</strong> '.(int)($stats['observed_24h'] ?? 0).'. <strong>Job pendente mais antigo:</strong> '.(int)round(((int)($stats['oldest_pending_seconds'] ?? 0))/60).' min.</p><p class="description">O teste cria apenas um job interno que grava a hora de conclusão. Não altera formulários, rotas, stocks, fotografias, relatórios ou integrações.</p><div class="ffpc-actions">';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="fieldflow_queue_probe">';wp_nonce_field('fieldflow_queue_probe');echo '<button class="button button-secondary">Executar teste seguro</button></form>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" onsubmit="return confirm(\'Repor todos os jobs no fluxo WP-Cron?\')"><input type="hidden" name="action" value="fieldflow_queue_fallback">';wp_nonce_field('fieldflow_queue_fallback');echo '<button class="button">Fallback para WP-Cron</button></form></div>';
        $lastRollback = is_array($status['last_rollback'] ?? null) ? $status['last_rollback'] : [];
        if ($lastRollback) echo '<p class="ffpc-muted">Último fallback: '.esc_html((string)($lastRollback['at'] ?? '')).'.</p>';
        echo '</div></div>';

        echo '<div class="ffpc-table" style="margin-top:18px"><h3>Jobs recentes da fila moderna</h3><table class="widefat striped"><thead><tr><th>ID</th><th>Fila</th><th>Job</th><th>Estado</th><th>Tentativas</th><th>Disponível</th><th>Duração</th><th>Driver</th><th>Resultado</th><th>Ação</th></tr></thead><tbody>';
        if (!$recent) echo '<tr><td colspan="10">Ainda não existem jobs registados. O modo Observação começará a criar métricas quando a Submission Pipeline agendar tarefas.</td></tr>';
        foreach ($recent as $job) {
            $jobStatus = sanitize_key((string)$job['status']);
            echo '<tr><td>#'.(int)$job['id'].'</td><td>'.esc_html((string)$job['queue_name']).'</td><td><code>'.esc_html((string)$job['hook_name']).'</code></td><td><span class="ffpc-status '.esc_attr($jobStatus).'">'.esc_html(strtoupper($jobStatus)).'</span></td><td>'.(int)$job['attempts'].' / '.(int)$job['max_attempts'].'</td><td>'.esc_html(self::date((string)$job['available_at'])).'</td><td>'.esc_html(self::duration((int)$job['duration_ms'])).'</td><td>'.esc_html((string)$job['driver']).'</td><td>'.esc_html((string)($job['last_error_message'] ?: 'Sem erros')).'</td><td>';
            if ($jobStatus === 'dead') {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="fieldflow_queue_retry_dead"><input type="hidden" name="job_id" value="'.(int)$job['id'].'">';wp_nonce_field('fieldflow_queue_retry_dead');echo '<button class="button button-small">Recolocar</button></form>';
            } else echo '<span class="ffpc-muted">Sem ação</span>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function circuitLabel(string $state): string {
        return ['closed' => 'Fechado', 'open' => 'Aberto', 'half_open' => 'Teste controlado'][$state] ?? ucfirst($state);
    }

    private static function circuitResetForm(string $key): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fieldflow_process_circuit_reset"><input type="hidden" name="process_key" value="' . esc_attr($key) . '">';
        wp_nonce_field('fieldflow_process_circuit_reset');
        echo '<button class="button button-small">Repor circuito</button></form>';
    }

    private static function kpi(string $label, $value, string $class): void {
        $display = is_int($value) ? number_format_i18n($value) : (string)$value;
        echo '<div class="ffpc-kpi ' . esc_attr($class) . '"><span>' . esc_html($label) . '</span><strong>' . esc_html($display) . '</strong></div>';
    }

    private static function overviewRow(array $process): void {
        $latest = (array)($process['latest'] ?? []);
        $status = sanitize_key((string)($latest['status'] ?? ''));
        if (!empty($process['overdue'])) $status = 'overdue';
        if (!$status) $status = 'pending';
        $labels = self::statusLabels();
        $circuitState = sanitize_key((string)($process['circuit']['state'] ?? 'closed'));
        echo '<tr><td class="ffpc-process"><strong>' . esc_html((string)$process['label']) . '</strong><small>' . esc_html((string)$process['type']) . ' · <code>' . esc_html((string)$process['hook']) . '</code></small><div class="ffpc-policy">' . (!empty($process['autoRetry']) ? '<span>Auto recuperação, ' . (int)$process['maxAttempts'] . ' tentativas</span>' : '<span>Recuperação manual</span>') . (!empty($process['circuit']) ? '<span>Circuito: ' . esc_html(self::circuitLabel($circuitState)) . '</span>' : '') . '</div></td>';
        echo '<td><span class="ffpc-status ' . esc_attr($status) . '">' . esc_html($labels[$status] ?? ucfirst($status)) . '</span>' . (!empty($process['overdue']) ? '<span class="ffpc-error">Próxima execução em atraso.</span>' : '') . '</td>';
        echo '<td>' . esc_html(self::date((string)($latest['completed_at'] ?? $latest['started_at'] ?? ''))) . '</td>';
        echo '<td>' . esc_html(!empty($process['next_run']) ? wp_date('d/m/Y H:i', (int)$process['next_run']) : 'Não agendada') . '<small class="ffpc-muted">' . (int)$process['scheduled_count'] . ' evento(s)</small></td>';
        echo '<td>' . esc_html(self::duration((int)($latest['duration_ms'] ?? 0))) . '</td><td>';
        if (!empty($process['manual'])) self::runForm((string)$process['key']);
        else echo '<span class="ffpc-muted">A partir do processo de origem</span>';
        echo '</td></tr>';
    }

    private static function filters(array $filters, array $definitions, array $types): void {
        echo '<form class="ffpc-filters" method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<label>Estado<select name="status"><option value="">Todos</option>';
        foreach (self::statusLabels() as $key => $label) {
            if ($key === 'overdue') continue;
            echo '<option value="' . esc_attr($key) . '" ' . selected($filters['status'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label><label>Tipo<select name="type"><option value="">Todos</option>';
        foreach ($types as $type) echo '<option value="' . esc_attr($type) . '" ' . selected($filters['type'], $type, false) . '>' . esc_html(ucfirst($type)) . '</option>';
        echo '</select></label><label>Processo<select name="process"><option value="">Todos</option>';
        foreach ($definitions as $key => $definition) echo '<option value="' . esc_attr($key) . '" ' . selected($filters['process'], $key, false) . '>' . esc_html((string)$definition['label']) . '</option>';
        echo '</select></label><button class="button button-primary">Filtrar</button><a class="button" href="' . esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))) . '">Limpar</a></form>';
    }

    private static function historyRow(array $row, array $definitions): void {
        $key = (string)$row['process_key'];
        $definition = $definitions[$key] ?? [];
        $status = sanitize_key((string)$row['status']);
        $labels = self::statusLabels();
        echo '<tr><td>#' . (int)$row['id'] . '</td><td class="ffpc-process"><strong>' . esc_html((string)($definition['label'] ?? $key)) . '</strong>';
        if (!empty($row['reference_id'])) echo '<small>Referência #' . (int)$row['reference_id'] . '</small>';
        echo '</td><td><span class="ffpc-status ' . esc_attr($status) . '">' . esc_html($labels[$status] ?? ucfirst($status)) . '</span></td>';
        echo '<td>' . esc_html(self::sourceLabel((string)$row['source'])) . '</td><td>' . (int)$row['attempt'] . '</td>';
        echo '<td>' . esc_html(self::date((string)$row['scheduled_at'])) . '</td><td>' . esc_html(self::date((string)$row['started_at'])) . '</td><td>' . esc_html(self::date((string)$row['completed_at'])) . '</td>';
        echo '<td class="ffpc-result">' . esc_html((string)($row['message'] ?: 'Sem detalhe.'));
        if (!empty($row['error_code'])) echo '<small class="ffpc-error">' . esc_html((string)$row['error_code']) . '</small>';
        if (!empty($row['recovery_status']) && (string)$row['recovery_status'] !== 'none') echo '<small>Recuperação: ' . esc_html(self::recoveryLabel((string)$row['recovery_status'])) . '</small>';
        if (!empty($row['duration_ms'])) echo '<small>' . esc_html(self::duration((int)$row['duration_ms'])) . '</small>';
        echo '</td><td>';
        if ($status === 'failed' && !empty($definition['retryable'])) self::retryForm((int)$row['id']);
        else echo '<span class="ffpc-muted">Sem ação</span>';
        echo '</td></tr>';
    }

    private static function runForm(string $key): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fieldflow_process_run"><input type="hidden" name="process_key" value="' . esc_attr($key) . '">';
        wp_nonce_field('fieldflow_process_run');
        echo '<button class="button button-small">Executar agora</button></form>';
    }

    private static function retryForm(int $runId): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="fieldflow_process_retry"><input type="hidden" name="run_id" value="' . $runId . '">';
        wp_nonce_field('fieldflow_process_retry');
        echo '<button class="button button-small">Reexecutar com segurança</button></form>';
    }

    private static function statusLabels(): array {
        return ['pending' => 'Pendente', 'running' => 'Em execução', 'completed' => 'Concluído', 'failed' => 'Falhado', 'overdue' => 'Em atraso'];
    }

    private static function sourceLabel(string $source): string {
        return ['cron' => 'WP-Cron', 'manual' => 'Manual', 'retry' => 'Reexecução manual', 'auto_retry' => 'Recuperação automática', 'system' => 'Sistema'][$source] ?? ucfirst($source);
    }

    private static function recoveryLabel(string $status): string {
        return ['scheduled' => 'Agendada', 'retry_scheduled' => 'Nova tentativa agendada', 'recovered' => 'Recuperado', 'terminal' => 'Falha definitiva'][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private static function date(string $utc): string {
        if ($utc === '' || $utc === '0000-00-00 00:00:00') return 'n/d';
        return get_date_from_gmt($utc, 'd/m/Y H:i');
    }

    private static function duration(int $milliseconds): string {
        if ($milliseconds <= 0) return 'n/d';
        if ($milliseconds < 1000) return $milliseconds . ' ms';
        return number_format_i18n($milliseconds / 1000, 2) . ' s';
    }
}
