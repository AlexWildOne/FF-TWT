<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\AdminPage;
use RoutesPro\Support\SystemHealth as Health;
use RoutesPro\Support\SystemHealthDiagnostics;
use RoutesPro\Observability\Monitor;
use RoutesPro\Core\AuditLog;
use RoutesPro\Core\EventBus;
use RoutesPro\Core\ReconciliationService;
use RoutesPro\Mapping\MappingRepository;
use RoutesPro\Mobile\OfflineSyncService;
use RoutesPro\Performance\Phase4AdminHealth;
use RoutesPro\Analytics\OperationalIntelligenceService;
use RoutesPro\SaaS\PlanRepository;
use RoutesPro\Integrations\ApiKeyService;
use RoutesPro\Integrations\WebhookService;

if (!defined('ABSPATH')) exit;

class SystemHealth {
    public static function registerHooks(): void {
        add_action('admin_post_fieldflow_system_health_export_json', [self::class, 'exportDiagnosticJson']);
    }

    public static function render() {
        if (!current_user_can('routespro_manage')) return;
        $report = Health::status();
        $diagnosticWarnings = array_values(array_filter((array)($report['checks'] ?? []), static function ($check): bool {
            return is_array($check) && (string)($check['status'] ?? 'ok') !== 'ok';
        }));
        $missedCronJobs = class_exists(Monitor::class) ? Monitor::missedCronCount() : 0;
        $events = EventBus::recent(20);
        $audits = AuditLog::recent(20);
        $runs = ReconciliationService::recentRuns(8);
        $runNotice = absint($_GET['fieldflow_integrity_run'] ?? 0);
        $runMode = sanitize_key((string)($_GET['fieldflow_integrity_mode'] ?? ''));
        $mapping = MappingRepository::tablesReady() ? MappingRepository::counts() : ['total'=>0,'pending'=>0,'suggested'=>0,'confirmed'=>0,'rejected'=>0,'store'=>0,'product'=>0,'assortment'=>0];
        $offline = is_array($report['offline'] ?? null) ? $report['offline'] : OfflineSyncService::stats(); // 9DI
        $offlineDevices = OfflineSyncService::recentDevices(12);
        $intelligenceCounts = OperationalIntelligenceService::alertCounts();
        $intelligenceAlerts = OperationalIntelligenceService::recentAlerts(12, ['status'=>'open']);
        $plansReady = PlanRepository::tablesReady(); // 9DI: one schema readiness check per render.
        $saasRows = $plansReady ? PlanRepository::clientRows() : [];
        $saasPlans = $plansReady ? PlanRepository::plans(false) : [];
        $saasActive = 0; $saasRestricted = 0;
        foreach ($saasRows as $saasRow) {
            $status = (string)($saasRow['status'] ?? '');
            if (in_array($status, ['active','trial','past_due'], true)) $saasActive++;
            if (in_array($status, ['suspended','expired','cancelled'], true)) $saasRestricted++;
        }
        // 9DI: dashboard uses counts only; avoid loading up to 1,200 full/sensitive rows.
        $integrationCounts = Phase4AdminHealth::integrationCounts();
        $apiKeyCount = (int)$integrationCounts['api_keys'];
        $webhookCount = (int)$integrationCounts['webhooks'];
        $webhookDeliveryCount = (int)$integrationCounts['deliveries'];
        $webhookFailures = (int)$integrationCounts['failed_deliveries'];
        $runtime = [
            'loader' => 'Autoload por necessidade',
            'bootstrap_files' => count(\RoutesPro\Support\Loader::bootstrapFiles()),
            'catalogued_files' => count(\RoutesPro\Support\Loader::files()),
            'lazy_panels' => 15,
            'design_assets' => (is_file(ROUTESPRO_PATH . 'assets/fieldflow-design-system.css') && is_file(ROUTESPRO_PATH . 'assets/fieldflow-ui-runtime.js')),
        ];
        $backofficeUI = is_array($report['backoffice_ui'] ?? null) ? $report['backoffice_ui'] : BackofficeUIFoundation::status(); // 9DI
        $uiModeNotice = isset($_GET['fieldflow_ui_mode']) ? sanitize_key((string)wp_unslash($_GET['fieldflow_ui_mode'])) : '';
        $healthTab = sanitize_key((string)($_GET['ff_health_tab'] ?? 'checks'));
        if (isset($_GET['fieldflow_schema_notice'])) $healthTab = 'schema';
        if (!in_array($healthTab, ['checks','diagnostics','schema','runs','events','audit','interface'], true)) $healthTab = 'checks';
        AdminPage::open('Saúde do sistema', 'Diagnóstico técnico, integridade dos dados e reconciliação segura da operação.');
        ?>
        <style>
          .ff-health-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:18px 0}
          .ff-health-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;box-shadow:0 10px 25px rgba(15,23,42,.05)}
          .ff-health-card h2,.ff-health-card h3{margin-top:0}.ff-health-chip{display:inline-flex;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:800;align-items:center;gap:5px}
          .ff-health-chip.ok,.ff-health-chip.processed,.ff-health-chip.completed{background:#dcfce7;color:#166534}.ff-health-chip.warning,.ff-health-chip.pending,.ff-health-chip.preview,.ff-health-chip.running{background:#fef3c7;color:#92400e}.ff-health-chip.critical,.ff-health-chip.failed,.ff-health-chip.completed_with_errors{background:#fee2e2;color:#991b1b}.ff-health-chip.apply{background:#dbeafe;color:#1d4ed8}
          .ff-health-columns{display:grid;grid-template-columns:minmax(0,2fr) minmax(300px,1fr);gap:18px;align-items:start}.ff-health-actions{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0}.ff-health-actions form{margin:0}
          .ff-health-note{padding:13px 15px;border-radius:14px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a;margin:14px 0}.ff-health-warning{background:#fff7ed;border-color:#fed7aa;color:#9a3412}
          .ff-health-table-wrap{overflow:auto}.ff-health-table-wrap table{min-width:760px}.ff-health-metric strong{font-size:30px;display:block;margin-top:7px}.ff-health-metric small{display:block;color:#64748b;margin-top:5px}
          .ff-health-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11px;color:#475569;word-break:break-word}.ff-runtime-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-top:14px}.ff-runtime-item{padding:13px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc}.ff-runtime-item span{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}.ff-runtime-item strong{display:block;margin-top:5px;font-size:18px}.ff-device-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.ff-device-toolbar button{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:8px 13px;font-weight:800;cursor:pointer}.ff-device-toolbar button.active{background:#0f172a;color:#fff}.ff-device-stage{overflow:auto;padding:18px;border-radius:18px;background:#e2e8f0}.ff-device-preview{width:100%;max-width:100%;margin:0 auto;background:#f8fafc;border:1px solid #cbd5e1;border-radius:22px;padding:14px;transition:max-width .2s ease}.ff-device-preview.tablet{max-width:820px}.ff-device-preview.phone{max-width:390px}.ff-preview-hero{padding:20px;border-radius:18px;background:linear-gradient(135deg,#0f172a,#2563eb);color:#fff}.ff-preview-hero h3{margin:4px 0 6px;color:#fff}.ff-preview-hero p{margin:0;color:#dbeafe}.ff-preview-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin:12px 0}.ff-preview-kpi{padding:13px;border:1px solid #e2e8f0;border-radius:14px;background:#fff}.ff-preview-kpi span{font-size:11px;color:#64748b}.ff-preview-kpi strong{display:block;font-size:22px;margin-top:4px}.ff-preview-table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden}.ff-preview-table th,.ff-preview-table td{padding:9px;border-bottom:1px solid #e2e8f0;text-align:left}.ff-health-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.ff-health-tabs button{border:1px solid #cbd5e1;background:#fff;border-radius:999px;padding:8px 13px;font-weight:800;cursor:pointer}.ff-health-tabs button.active{background:#0f172a;color:#fff;border-color:#0f172a}.ff-health-panel{display:none}.ff-health-panel.active{display:block}
          @media (max-width:1080px){.ff-health-columns{grid-template-columns:1fr}}@media (max-width:782px){.ff-health-card{padding:14px}.ff-health-actions .button{width:100%;text-align:center}.ff-health-actions form{width:100%}}
        </style>
        <?php if ($runNotice): ?>
          <div class="ff-health-note <?php echo $runMode === 'apply' ? 'ff-health-warning' : ''; ?>">
            Reconciliação <?php echo $runMode === 'apply' ? 'segura aplicada' : 'analisada em modo de pré-visualização'; ?>. Execução #<?php echo (int)$runNotice; ?> registada no histórico.
          </div>
        <?php endif; ?>

        <?php if ($uiModeNotice !== ''): ?>
          <div class="ff-health-note <?php echo $uiModeNotice === 'invalid' ? 'ff-health-warning' : ''; ?>">
            <?php
              if ($uiModeNotice === 'foundation') echo 'Backoffice UI Consolidation ativada. Todo o backoffice conhecido usa agora a camada visual 2.2.262.';
              elseif ($uiModeNotice === 'legacy') echo 'Modo legado ativado. O backoffice regressou imediatamente à apresentação 2.2.260.';
              elseif ($uiModeNotice === 'locked') echo 'O modo está definido pela constante FIELDFLOW_BACKOFFICE_UI_MODE no servidor e não pode ser alterado neste ecrã.';
              else echo 'O modo visual pedido não é válido e não foi aplicado.';
            ?>
          </div>
        <?php endif; ?>

        <div class="ff-health-card ff-backoffice-ui-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div>
              <span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">Backoffice UI Consolidation</span>
              <h2 style="margin:4px 0 6px">Design system consolidado, responsivo e reversível</h2>
              <p style="margin:0;color:#475569;max-width:920px">Aplica componentes, larguras, tabelas, filtros e regras responsivas consistentes a todo o backoffice FieldFlow conhecido. Não altera dados, permissões, URLs, formulários públicos ou integrações mobile.</p>
            </div>
            <span class="ff-health-chip <?php echo !empty($backofficeUI['assets_ready']) && !empty($backofficeUI['enabled']) ? 'ok' : (!empty($backofficeUI['assets_ready']) ? 'warning' : 'critical'); ?>">
              <?php echo !empty($backofficeUI['assets_ready']) ? (!empty($backofficeUI['enabled']) ? '2.2.262 ATIVA' : 'MODO LEGADO') : 'ASSETS EM FALTA'; ?>
            </span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Modo atual</span><strong><?php echo !empty($backofficeUI['enabled']) ? 'Consolidado' : 'Legado'; ?></strong></div>
            <div class="ff-runtime-item"><span>Famílias visuais</span><strong><?php echo (int)($backofficeUI['managed_scopes'] ?? 0); ?></strong></div>
            <div class="ff-runtime-item"><span>Páginas abrangidas</span><strong><?php echo (int)($backofficeUI['managed_pages'] ?? $backofficeUI['priority_pages']); ?></strong></div>
            <div class="ff-runtime-item"><span>Assets visuais</span><strong><?php echo esc_html(size_format((int)($backofficeUI['asset_bytes'] ?? 0))); ?></strong></div>
          </div>
          <div class="ff-backoffice-ui-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="routespro_backoffice_ui_mode">
              <input type="hidden" name="mode" value="foundation">
              <?php wp_nonce_field('routespro_backoffice_ui_mode'); ?>
              <button type="submit" class="button button-primary" <?php disabled(!empty($backofficeUI['enabled']) || !empty($backofficeUI['locked'])); ?>>Ativar layout consolidado</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="routespro_backoffice_ui_mode">
              <input type="hidden" name="mode" value="legacy">
              <?php wp_nonce_field('routespro_backoffice_ui_mode'); ?>
              <button type="submit" class="button" <?php disabled(empty($backofficeUI['enabled']) || !empty($backofficeUI['locked'])); ?>>Repor layout 2.2.260</button>
            </form>
            <?php if (!empty($backofficeUI['locked'])): ?><span class="description">Modo bloqueado pelo servidor.</span><?php endif; ?>
          </div>
        </div>

        <div class="ff-health-grid">
          <?php foreach ($report['counts'] as $label => $count): ?>
            <div class="ff-health-card ff-health-metric"><span><?php echo esc_html(self::countLabel((string)$label)); ?></span><strong><?php echo esc_html(number_format_i18n((int)$count)); ?></strong></div>
          <?php endforeach; ?>
        </div>

        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div>
              <span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">Core Integrity Foundation</span>
              <h2 style="margin:4px 0 6px">Estados canónicos, eventos e auditoria</h2>
              <p style="margin:0;color:#475569;max-width:900px">A pré-visualização apenas identifica divergências. A reconciliação segura corrige estados de rotas, liga submissões a paragens únicas e preserva todos os casos ambíguos para revisão manual.</p>
            </div>
            <span class="ff-health-chip <?php echo !empty($report['integrity']['tables_ready']) ? 'ok' : 'critical'; ?>"><?php echo !empty($report['integrity']['tables_ready']) ? 'FUNDAÇÃO ATIVA' : 'INSTALAÇÃO INCOMPLETA'; ?></span>
          </div>
          <div class="ff-health-actions">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="fieldflow_integrity_reconcile">
              <input type="hidden" name="mode" value="preview">
              <?php wp_nonce_field('fieldflow_integrity_reconcile'); ?>
              <button type="submit" class="button button-secondary">Analisar integridade</button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Aplicar apenas correções consideradas seguras pelo FieldFlow? Casos ambíguos não serão alterados.');">
              <input type="hidden" name="action" value="fieldflow_integrity_reconcile">
              <input type="hidden" name="mode" value="apply">
              <?php wp_nonce_field('fieldflow_integrity_reconcile'); ?>
              <button type="submit" class="button button-primary">Aplicar reconciliação segura</button>
            </form>
          </div>
          <div class="ff-health-grid" style="margin-bottom:0">
            <div class="ff-health-card ff-health-metric"><span>Eventos processados</span><strong><?php echo esc_html(number_format_i18n((int)($report['integrity']['events']['processed'] ?? 0))); ?></strong><small>Eventos internos concluídos.</small></div>
            <div class="ff-health-card ff-health-metric"><span>Eventos falhados</span><strong><?php echo esc_html(number_format_i18n((int)($report['integrity']['events']['failed'] ?? 0))); ?></strong><small>Devem permanecer próximos de zero.</small></div>
            <div class="ff-health-card ff-health-metric"><span>Auditoria em 24 horas</span><strong><?php echo esc_html(number_format_i18n((int)($report['integrity']['audit_last_24h'] ?? 0))); ?></strong><small>Alterações com origem e contexto.</small></div>
            <div class="ff-health-card ff-health-metric"><span>Últimas divergências</span><strong><?php echo esc_html(number_format_i18n((int)($report['integrity']['last_issue_count'] ?? 0))); ?></strong><small><?php echo !empty($report['integrity']['last_run_at']) ? esc_html('Última análise: ' . mysql2date('d/m/Y H:i', (string)$report['integrity']['last_run_at'])) : 'Ainda sem análise manual.'; ?></small></div>
          </div>
        </div>

        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">Subscrições e Capacidades</span><h2 style="margin:4px 0 6px">Controlo SaaS por cliente</h2><p style="margin:0;color:#475569;max-width:900px">Planos, capacidades, limites e estados são aplicados no BO, portais, app, API, IA, relatórios e exportações. A desativação de um módulo preserva os dados existentes.</p></div>
            <span class="ff-health-chip <?php echo PlanRepository::tablesReady() ? 'ok' : 'critical'; ?>"><?php echo PlanRepository::tablesReady() ? '2.2.221 ATIVA' : 'TABELAS EM FALTA'; ?></span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Planos configurados</span><strong><?php echo count($saasPlans); ?></strong></div>
            <div class="ff-runtime-item"><span>Clientes com plano</span><strong><?php echo count($saasRows); ?></strong></div>
            <div class="ff-runtime-item"><span>Ativos ou em teste</span><strong><?php echo (int)$saasActive; ?></strong></div>
            <div class="ff-runtime-item"><span>Acesso restrito</span><strong><?php echo (int)$saasRestricted; ?></strong></div>
          </div>
          <p style="margin:14px 0 0"><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . Subscriptions::SLUG)); ?>">Abrir Subscrições</a></p>
        </div>

        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">API Pública e Webhooks</span><h2 style="margin:4px 0 6px">Integrações empresariais controladas</h2><p style="margin:0;color:#475569;max-width:900px">Chaves por cliente e projeto, endpoints incrementais para Power BI e webhooks assinados, entregues em fila sem bloquear a operação.</p></div>
            <span class="ff-health-chip <?php echo ApiKeyService::tableReady() && WebhookService::tablesReady() ? 'ok' : 'critical'; ?>"><?php echo ApiKeyService::tableReady() && WebhookService::tablesReady() ? '2.2.223 ATIVA' : 'TABELAS EM FALTA'; ?></span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Chaves API</span><strong><?php echo $apiKeyCount; ?></strong></div>
            <div class="ff-runtime-item"><span>Webhooks</span><strong><?php echo $webhookCount; ?></strong></div>
            <div class="ff-runtime-item"><span>Entregas recentes</span><strong><?php echo $webhookDeliveryCount; ?></strong></div>
            <div class="ff-runtime-item"><span>Entregas falhadas</span><strong><?php echo (int)$webhookFailures; ?></strong></div>
          </div>
          <p style="margin:14px 0 0"><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . PublicIntegrations::SLUG)); ?>">Abrir API Pública e Webhooks</a></p>
        </div>

        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">Segurança e Observabilidade</span><h2 style="margin:4px 0 6px">Produção monitorizada e dispositivos controlados</h2><p style="margin:0;color:#475569;max-width:900px">Rate limiting, validação de uploads, erros agrupados, endpoints lentos, alertas, comandos remotos e pacotes de diagnóstico sanitizados.</p></div>
            <span class="ff-health-chip <?php echo !empty($report['observability']['tables_ready']) ? (((int)($report['observability']['critical'] ?? 0)>0)?'warning':'ok') : 'critical'; ?>"><?php echo !empty($report['observability']['tables_ready']) ? '2.2.222 ATIVA' : 'TABELAS EM FALTA'; ?></span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Eventos abertos</span><strong><?php echo (int)($report['observability']['open'] ?? 0); ?></strong></div>
            <div class="ff-runtime-item"><span>Críticos</span><strong><?php echo (int)($report['observability']['critical'] ?? 0); ?></strong></div>
            <div class="ff-runtime-item"><span>Segurança aberta</span><strong><?php echo (int)($report['security']['open'] ?? 0); ?></strong></div>
            <div class="ff-runtime-item"><span>Heartbeat</span><strong style="font-size:13px"><?php echo esc_html((string)($report['observability']['last_heartbeat'] ?? 'Pendente')); ?></strong></div>
          </div>
          <p style="margin:14px 0 0"><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . SecurityObservability::SLUG)); ?>">Abrir Segurança e Observabilidade</a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . Devices::SLUG)); ?>">Gerir dispositivos</a></p>
        </div>

        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">Performance e Design System</span><h2 style="margin:4px 0 6px">Runtime modular ativo</h2><p style="margin:0;color:#475569;max-width:900px">As classes são carregadas apenas quando necessárias e os módulos pesados dos portais abrem progressivamente. O design system normaliza KPI, tabelas, gráficos, estados e comportamento móvel.</p></div>
            <span class="ff-health-chip <?php echo $runtime['design_assets'] ? 'ok' : 'critical'; ?>"><?php echo $runtime['design_assets'] ? '2.2.216 ATIVA' : 'ASSETS EM FALTA'; ?></span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Carregador</span><strong><?php echo esc_html($runtime['loader']); ?></strong></div>
            <div class="ff-runtime-item"><span>Ficheiros globais obrigatórios</span><strong><?php echo (int)$runtime['bootstrap_files']; ?></strong></div>
            <div class="ff-runtime-item"><span>Classes catalogadas</span><strong><?php echo (int)$runtime['catalogued_files']; ?></strong></div>
            <div class="ff-runtime-item"><span>Painéis progressivos</span><strong><?php echo (int)$runtime['lazy_panels']; ?></strong></div>
          </div>
        </div>

        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">Centro Universal de Mapping</span><h2 style="margin:4px 0 6px">Correspondências centralizadas e reversíveis</h2><p style="margin:0;color:#475569;max-width:900px">Briefings, implementações, stocks, produtos e cardex partilham agora um centro de controlo com confiança, aliases aprendidos, auditoria e opção de desfazer.</p></div>
            <span class="ff-health-chip <?php echo MappingRepository::tablesReady() ? 'ok' : 'critical'; ?>"><?php echo MappingRepository::tablesReady() ? '2.2.217 ATIVA' : 'TABELAS EM FALTA'; ?></span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Mappings totais</span><strong><?php echo (int)$mapping['total']; ?></strong></div>
            <div class="ff-runtime-item"><span>Pendentes</span><strong><?php echo (int)$mapping['pending']; ?></strong></div>
            <div class="ff-runtime-item"><span>Sugeridos</span><strong><?php echo (int)$mapping['suggested']; ?></strong></div>
            <div class="ff-runtime-item"><span>Confirmados</span><strong><?php echo (int)$mapping['confirmed']; ?></strong></div>
          </div>
          <p style="margin:14px 0 0"><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=' . MappingCenter::SLUG)); ?>">Abrir Centro de Mapping</a></p>
        </div>

        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">App Offline First</span><h2 style="margin:4px 0 6px">Cache operacional, rascunhos e fila de sincronização</h2><p style="margin:0;color:#475569;max-width:900px">Rotas, lojas, briefings e formulários podem ser preparados no dispositivo. Submissões e eventos sem ligação entram numa fila idempotente e são retomados automaticamente quando a rede regressa.</p></div>
            <span class="ff-health-chip <?php echo !empty($offline['tables_ready']) ? 'ok' : 'critical'; ?>"><?php echo !empty($offline['tables_ready']) ? ((defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : 'FieldFlow') . ' ATIVA') : 'TABELA EM FALTA'; ?></span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Dispositivos registados</span><strong><?php echo (int)$offline['devices']; ?></strong></div>
            <div class="ff-runtime-item"><span>Ativos em 24 horas</span><strong><?php echo (int)$offline['active_24h']; ?></strong></div>
            <div class="ff-runtime-item"><span>Itens pendentes</span><strong><?php echo (int)$offline['pending']; ?></strong></div>
            <div class="ff-runtime-item"><span>Falhas reais</span><strong><?php echo (int)$offline['failed']; ?></strong></div>
            <div class="ff-runtime-item"><span>Conflitos offline</span><strong><?php echo (int)($offline['conflicts'] ?? 0); ?></strong></div>
          </div>
          <p style="margin:14px 0 0;color:#475569"><?php echo !empty($offline['last_sync_at']) ? esc_html('Última sincronização registada: ' . mysql2date('d/m/Y H:i', (string)$offline['last_sync_at'])) : 'Ainda não existem sincronizações offline registadas.'; ?></p>
          <p style="margin:12px 0 0"><a class="button button-secondary" href="<?php echo esc_url(add_query_arg(['page' => SecurityObservability::SLUG, 'tab' => 'mobile-sync'], admin_url('admin.php'))); ?>">Abrir Sincronização Mobile</a></p>
          <?php if ($offlineDevices): ?>
            <div class="ff-health-table-wrap" style="margin-top:16px"><table class="widefat striped"><thead><tr><th>Utilizador</th><th>Dispositivo</th><th>Plataforma</th><th>Versão</th><th>Pendentes</th><th>Falhas</th><th>Conflitos</th><th>Última atividade</th></tr></thead><tbody>
              <?php foreach ($offlineDevices as $device): ?>
                <tr><td><?php echo esc_html((string)($device['display_name'] ?: $device['user_login'] ?: ('#' . (int)$device['user_id']))); ?></td><td><code><?php echo esc_html(substr((string)$device['device_id'], 0, 18)); ?><?php echo strlen((string)$device['device_id']) > 18 ? '...' : ''; ?></code></td><td><?php echo esc_html((string)($device['platform'] ?: 'web')); ?></td><td><?php echo esc_html((string)($device['app_version'] ?: 'n/d')); ?></td><td><?php echo (int)$device['queue_pending']; ?></td><td><?php echo (int)$device['queue_failed']; ?></td><td><?php echo (int)($device['queue_conflicts'] ?? 0); ?></td><td><?php echo !empty($device['last_seen_at']) ? esc_html(mysql2date('d/m/Y H:i', (string)$device['last_seen_at'])) : 'n/d'; ?></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </div>


        <div class="ff-health-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div><span style="font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;font-weight:800">Analytics e IA Operacional</span><h2 style="margin:4px 0 6px">Anomalias, comparação temporal e ações controladas</h2><p style="margin:0;color:#475569;max-width:900px">O motor cruza execução, reportes, briefing e stocks, identifica sinais verificáveis e apresenta recomendações sem alterar dados automaticamente.</p></div>
            <span class="ff-health-chip <?php echo OperationalIntelligenceService::tableReady() ? 'ok' : 'critical'; ?>"><?php echo OperationalIntelligenceService::tableReady() ? '2.2.219 ATIVA' : 'TABELA EM FALTA'; ?></span>
          </div>
          <div class="ff-runtime-grid">
            <div class="ff-runtime-item"><span>Alertas abertos</span><strong><?php echo (int)$intelligenceCounts['open']; ?></strong></div>
            <div class="ff-runtime-item"><span>Críticos</span><strong><?php echo (int)$intelligenceCounts['critical']; ?></strong></div>
            <div class="ff-runtime-item"><span>Risco elevado</span><strong><?php echo (int)$intelligenceCounts['high']; ?></strong></div>
            <div class="ff-runtime-item"><span>Reconhecidos</span><strong><?php echo (int)$intelligenceCounts['acknowledged']; ?></strong></div>
          </div>
          <?php if ($intelligenceAlerts): ?>
            <div class="ff-health-table-wrap" style="margin-top:16px"><table class="widefat striped"><thead><tr><th>Alerta</th><th>Âmbito</th><th>Severidade</th><th>Última deteção</th><th>Ação</th></tr></thead><tbody>
            <?php foreach ($intelligenceAlerts as $alert): ?>
              <tr><td><strong><?php echo esc_html((string)$alert['title']); ?></strong><br><span style="color:#64748b"><?php echo esc_html((string)$alert['detail']); ?></span></td><td>Cliente #<?php echo (int)$alert['client_id']; ?> · Projeto #<?php echo (int)$alert['project_id']; ?></td><td><span class="ff-health-chip <?php echo in_array((string)$alert['severity'], ['critical','high'], true) ? 'critical' : 'warning'; ?>"><?php echo esc_html(strtoupper((string)$alert['severity'])); ?></span></td><td><?php echo esc_html(mysql2date('d/m/Y H:i', (string)$alert['last_seen_at'])); ?></td><td><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0"><input type="hidden" name="action" value="fieldflow_analytics_alert_ack"><input type="hidden" name="alert_id" value="<?php echo (int)$alert['id']; ?>"><?php wp_nonce_field('fieldflow_analytics_alert_status'); ?><button class="button button-small" type="submit">Reconhecer</button></form></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
          <?php else: ?>
            <p style="margin:14px 0 0;color:#475569">Ainda não existem alertas operacionais abertos. São gerados quando os analytics são consultados.</p>
          <?php endif; ?>
        </div>

        <div class="ff-health-tabs" data-ff-health-tabs>
          <button type="button" class="<?php echo $healthTab === 'checks' ? 'active' : ''; ?>" data-panel="checks">Checks</button>
          <button type="button" class="<?php echo $healthTab === 'diagnostics' ? 'active' : ''; ?>" data-panel="diagnostics">Diagnóstico warnings</button>
          <button type="button" class="<?php echo $healthTab === 'schema' ? 'active' : ''; ?>" data-panel="schema">Schema e releases</button>
          <button type="button" class="<?php echo $healthTab === 'runs' ? 'active' : ''; ?>" data-panel="runs">Reconciliações</button>
          <button type="button" class="<?php echo $healthTab === 'events' ? 'active' : ''; ?>" data-panel="events">Eventos</button>
          <button type="button" class="<?php echo $healthTab === 'audit' ? 'active' : ''; ?>" data-panel="audit">Auditoria</button>
          <button type="button" class="<?php echo $healthTab === 'interface' ? 'active' : ''; ?>" data-panel="interface">Interface</button>
        </div>

        <div class="ff-health-panel <?php echo $healthTab === 'checks' ? 'active' : ''; ?>" data-ff-health-panel="checks">
          <div class="ff-health-columns">
            <div class="ff-health-card">
              <h2>Checks críticos</h2>
              <div class="ff-health-table-wrap"><table class="widefat striped"><thead><tr><th>Área</th><th>Estado</th><th>Detalhe</th></tr></thead><tbody>
                <?php foreach ($report['checks'] as $check): ?>
                  <tr><td><strong><?php echo esc_html($check['label']); ?></strong></td><td><span class="ff-health-chip <?php echo esc_attr($check['status']); ?>"><?php echo esc_html(strtoupper($check['status'])); ?></span></td><td><?php echo esc_html($check['detail']); ?></td></tr>
                <?php endforeach; ?>
              </tbody></table></div>
            </div>
            <div class="ff-health-card">
              <h2>Licença e credenciais</h2>
              <p><strong>Licença:</strong> <?php echo esc_html(\RoutesPro\Support\LicenseManager::statusLabel()); ?></p>
              <p style="color:#475569">Para produto premium, os segredos sensíveis devem ficar preferencialmente no <code>wp-config.php</code>.</p>
              <table class="widefat striped"><thead><tr><th>Chave</th><th>Origem</th></tr></thead><tbody><?php foreach ($report['constants'] as $key => $origin): ?><tr><td><code><?php echo esc_html($key); ?></code></td><td><?php echo esc_html($origin); ?></td></tr><?php endforeach; ?></tbody></table>
            </div>
          </div>
        </div>

        <div class="ff-health-panel <?php echo $healthTab === 'diagnostics' ? 'active' : ''; ?>" data-ff-health-panel="diagnostics">
          <div class="ff-health-card">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
              <div>
                <h2 style="margin-top:0">Diagnóstico sanitizado dos avisos</h2>
                <p style="color:#475569;max-width:920px">Resume apenas metadados técnicos para explicar warnings de Offline First, Observabilidade, Performance, filas, cron e integridade de release. Não inclui payloads, respostas de formulários, credenciais, IPs, device IDs ou IDs individuais de utilizador.</p>
              </div>
              <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fieldflow_system_health_export_json">
                <?php wp_nonce_field('fieldflow_system_health_export_json'); ?>
                <button type="submit" class="button button-primary">Exportar diagnóstico JSON</button>
              </form>
            </div>
            <div class="ff-runtime-grid">
              <div class="ff-runtime-item"><span>Warnings/criticos ativos</span><strong><?php echo (int)count($diagnosticWarnings); ?></strong></div>
              <div class="ff-runtime-item"><span>Offline pendente</span><strong><?php echo (int)($offline['pending'] ?? 0); ?></strong></div>
              <div class="ff-runtime-item"><span>Offline falhado</span><strong><?php echo (int)($offline['failed'] ?? 0); ?></strong></div>
              <div class="ff-runtime-item"><span>Observabilidade critica ativa</span><strong><?php echo (int)($report['observability']['critical_active'] ?? 0); ?></strong></div>
              <div class="ff-runtime-item"><span>Performance gate</span><strong><?php echo esc_html(strtoupper((string)($report['load_readiness']['gate_status'] ?? 'n/d'))); ?></strong></div>
              <div class="ff-runtime-item"><span>Queue health</span><strong><?php echo (int)($report['load_readiness']['queue']['score'] ?? 0); ?>%</strong></div>
              <div class="ff-runtime-item"><span>Cron atrasado</span><strong><?php echo (int)$missedCronJobs; ?></strong></div>
              <div class="ff-runtime-item"><span>SSRF Guard</span><strong><?php echo !empty($report['outbound_http']['https_only']) ? 'OK' : 'CHECK'; ?></strong></div>
            </div>
          </div>
          <div class="ff-health-card">
            <h2>Avisos que alimentam o estado atual</h2>
            <div class="ff-health-table-wrap"><table class="widefat striped"><thead><tr><th>Area</th><th>Estado</th><th>Motivo atual</th></tr></thead><tbody>
              <?php if (!$diagnosticWarnings): ?><tr><td colspan="3">Sem warnings ou checks criticos ativos.</td></tr><?php endif; ?>
              <?php foreach ($diagnosticWarnings as $check): ?>
                <tr><td><strong><?php echo esc_html((string)($check['label'] ?? '')); ?></strong><br><code><?php echo esc_html((string)($check['key'] ?? '')); ?></code></td><td><span class="ff-health-chip <?php echo esc_attr((string)($check['status'] ?? 'warning')); ?>"><?php echo esc_html(strtoupper((string)($check['status'] ?? 'warning'))); ?></span></td><td><?php echo esc_html((string)($check['detail'] ?? '')); ?></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          </div>
        </div>

        <div class="ff-health-panel <?php echo $healthTab === 'schema' ? 'active' : ''; ?>" data-ff-health-panel="schema">
          <?php SchemaIntegrityCenter::renderHealthPanel(); ?>
        </div>

        <div class="ff-health-panel <?php echo $healthTab === 'runs' ? 'active' : ''; ?>" data-ff-health-panel="runs">
          <div class="ff-health-card"><h2>Histórico de reconciliação</h2><div class="ff-health-table-wrap"><table class="widefat striped"><thead><tr><th>Execução</th><th>Modo</th><th>Estado</th><th>Resultado</th><th>Data</th></tr></thead><tbody>
            <?php if (!$runs): ?><tr><td colspan="5">Ainda não existem execuções.</td></tr><?php endif; ?>
            <?php foreach ($runs as $run): $summary=json_decode((string)($run['summary_json']??''),true)?:[]; ?>
              <tr><td><strong>#<?php echo (int)$run['id']; ?></strong></td><td><span class="ff-health-chip <?php echo esc_attr((string)$run['run_mode']); ?>"><?php echo esc_html(strtoupper((string)$run['run_mode'])); ?></span></td><td><span class="ff-health-chip <?php echo esc_attr((string)$run['status']); ?>"><?php echo esc_html(strtoupper((string)$run['status'])); ?></span></td><td><?php echo esc_html(number_format_i18n((int)($summary['total_issues']??0))); ?> divergências, <?php echo esc_html(number_format_i18n((int)($summary['applied']??0))); ?> correções, <?php echo esc_html(number_format_i18n((int)($summary['errors']??0))); ?> erros</td><td><?php echo esc_html(mysql2date('d/m/Y H:i', (string)($run['completed_at']??$run['started_at']))); ?></td></tr>
            <?php endforeach; ?>
          </tbody></table></div></div>
        </div>

        <div class="ff-health-panel <?php echo $healthTab === 'events' ? 'active' : ''; ?>" data-ff-health-panel="events">
          <div class="ff-health-card"><h2>Eventos centrais recentes</h2><div class="ff-health-table-wrap"><table class="widefat striped"><thead><tr><th>Evento</th><th>Entidade</th><th>Estado</th><th>Origem</th><th>Data</th></tr></thead><tbody>
            <?php if (!$events): ?><tr><td colspan="5">Ainda não existem eventos centrais.</td></tr><?php endif; ?>
            <?php foreach ($events as $event): ?><tr><td><strong><?php echo esc_html((string)$event['event_name']); ?></strong><br><span class="ff-health-code"><?php echo esc_html((string)$event['event_uuid']); ?></span></td><td><?php echo esc_html((string)$event['entity_type']); ?> #<?php echo (int)$event['entity_id']; ?></td><td><span class="ff-health-chip <?php echo esc_attr((string)$event['status']); ?>"><?php echo esc_html(strtoupper((string)$event['status'])); ?></span></td><td><?php echo esc_html((string)$event['source']); ?></td><td><?php echo esc_html(mysql2date('d/m/Y H:i:s', (string)$event['created_at'])); ?></td></tr><?php endforeach; ?>
          </tbody></table></div></div>
        </div>

        <div class="ff-health-panel <?php echo $healthTab === 'audit' ? 'active' : ''; ?>" data-ff-health-panel="audit">
          <div class="ff-health-card"><h2>Auditoria recente</h2><div class="ff-health-table-wrap"><table class="widefat striped"><thead><tr><th>Ação</th><th>Entidade</th><th>Utilizador</th><th>Origem</th><th>Correlação</th><th>Data</th></tr></thead><tbody>
            <?php if (!$audits): ?><tr><td colspan="6">Ainda não existem entradas de auditoria.</td></tr><?php endif; ?>
            <?php foreach ($audits as $audit): ?><tr><td><strong><?php echo esc_html((string)$audit['action_name']); ?></strong></td><td><?php echo esc_html((string)$audit['entity_type']); ?> #<?php echo (int)$audit['entity_id']; ?></td><td><?php echo (int)($audit['actor_user_id']??0) ?: 'Sistema'; ?></td><td><?php echo esc_html((string)$audit['source']); ?></td><td><span class="ff-health-code"><?php echo esc_html((string)$audit['correlation_id']); ?></span></td><td><?php echo esc_html(mysql2date('d/m/Y H:i:s', (string)$audit['created_at'])); ?></td></tr><?php endforeach; ?>
          </tbody></table></div></div>
        </div>

        <div class="ff-health-panel <?php echo $healthTab === 'interface' ? 'active' : ''; ?>" data-ff-health-panel="interface">
          <div class="ff-health-card">
            <h2>Pré-visualização responsiva</h2>
            <p style="color:#475569">Confirma a hierarquia base de header, KPI e tabela antes de validares os portais em dispositivos físicos.</p>
            <div class="ff-device-toolbar" data-ff-device-toolbar><button type="button" class="active" data-size="desktop">Desktop</button><button type="button" data-size="tablet">Tablet</button><button type="button" data-size="phone">Telemóvel</button></div>
            <div class="ff-device-stage"><div class="ff-device-preview" data-ff-device-preview>
              <div class="ff-preview-hero"><small>FIELD OPERATIONS</small><h3>Resumo da operação</h3><p>Leitura executiva consistente em desktop, tablet, iOS e Android.</p></div>
              <div class="ff-preview-kpis"><div class="ff-preview-kpi"><span>Visitas</span><strong>128</strong></div><div class="ff-preview-kpi"><span>Execução</span><strong>94%</strong></div><div class="ff-preview-kpi"><span>Em risco</span><strong>7</strong></div></div>
              <div style="overflow:auto"><table class="ff-preview-table"><thead><tr><th>Loja</th><th>Estado</th><th>Execução</th></tr></thead><tbody><tr><td>PDV Norte</td><td>Concluído</td><td>100%</td></tr><tr><td>PDV Centro</td><td>Em curso</td><td>62%</td></tr></tbody></table></div>
            </div></div>
          </div>
        </div>

        <script>
        (function(){
          var root=document.querySelector('[data-ff-health-tabs]');if(!root)return;
          root.addEventListener('click',function(e){var button=e.target.closest('button[data-panel]');if(!button)return;var panel=button.getAttribute('data-panel');root.querySelectorAll('button').forEach(function(item){item.classList.toggle('active',item===button);});document.querySelectorAll('[data-ff-health-panel]').forEach(function(item){item.classList.toggle('active',item.getAttribute('data-ff-health-panel')===panel);});});
          var deviceToolbar=document.querySelector('[data-ff-device-toolbar]');var preview=document.querySelector('[data-ff-device-preview]');if(deviceToolbar&&preview){deviceToolbar.addEventListener('click',function(e){var button=e.target.closest('button[data-size]');if(!button)return;deviceToolbar.querySelectorAll('button').forEach(function(item){item.classList.toggle('active',item===button);});preview.classList.remove('tablet','phone');if(button.dataset.size!=='desktop')preview.classList.add(button.dataset.size);});}
        })();
        </script>
        <?php
        AdminPage::close();
    }

    public static function exportDiagnosticJson(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissoes.', 'FieldFlow', ['response'=>403]);
        check_admin_referer('fieldflow_system_health_export_json');
        $payload = SystemHealthDiagnostics::snapshot();
        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') wp_die('Nao foi possivel gerar o diagnostico JSON.');
        $now = time();
        $filename = 'fieldflow-system-health-diagnostic-' . gmdate('Ymd-His', $now) . 'Z.json';
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

    private static function countLabel(string $key): string {
        $labels = [
            'clients' => 'Clientes','projects' => 'Projetos','routes' => 'Rotas','locations' => 'Lojas','forms' => 'Formulários','submissions' => 'Submissões','system_logs' => 'Logs técnicos','ai_logs' => 'Registos de IA','core_events' => 'Eventos centrais','failed_events' => 'Eventos falhados','audit_24h' => 'Auditoria 24h','last_integrity_issues' => 'Divergências recentes','missing_runtime_files' => 'Ficheiros runtime em falta','missing_release_files' => 'Ficheiros release em falta','license_active' => 'Licença ativa','license_domain_match' => 'Domínio validado','privacy_open_requests'=>'Pedidos RGPD abertos','ai_tracked_tokens'=>'Tokens IA acompanhados','observability_critical'=>'Observability critical ativos','observability_critical_historical'=>'Observability critical históricos',
        ];
        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }
}
