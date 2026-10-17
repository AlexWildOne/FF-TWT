<?php
namespace RoutesPro\Admin;

use RoutesPro\Support\BatchMigrationManager;
use RoutesPro\Support\MigrationLedger;
use RoutesPro\Support\ReleaseGate;
use RoutesPro\Support\SchemaIntegrity;
use RoutesPro\Support\SchemaManifest;

if (!defined('ABSPATH')) exit;

/**
 * Internal controller and embedded renderers for schema and release safety.
 *
 * The UI is deliberately integrated into the existing System Health and
 * Process Center pages. No standalone FieldFlow menu is registered.
 */
final class SchemaIntegrityCenter {
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('admin_post_fieldflow_schema_quick_audit', [self::class, 'handleQuickAudit']);
        add_action('admin_post_fieldflow_schema_full_audit', [self::class, 'handleFullAudit']);
        add_action('admin_post_fieldflow_schema_batch_now', [self::class, 'handleBatchNow']);
        add_action('admin_post_fieldflow_schema_batch_resume', [self::class, 'handleBatchResume']);
        add_action('admin_post_fieldflow_release_gate_run', [self::class, 'handleReleaseGate']);
    }

    public static function handleQuickAudit(): void {
        self::guard('fieldflow_schema_quick_audit');
        SchemaIntegrity::audit('quick', true);
        self::redirectHealth('quick_completed');
    }

    public static function handleFullAudit(): void {
        self::guard('fieldflow_schema_full_audit');
        $result = BatchMigrationManager::startSchemaAudit(get_current_user_id(), 8);
        self::redirectHealth(!empty($result['ok']) ? 'full_started' : 'full_failed', [
            'batch_id' => (int)($result['batch_id'] ?? 0),
        ]);
    }

    public static function handleBatchNow(): void {
        self::guard('fieldflow_schema_batch_now');
        $batchId = absint($_POST['batch_id'] ?? 0);
        if ($batchId > 0) BatchMigrationManager::runNow($batchId);
        self::redirectProcesses('batch_processed', ['batch_id' => $batchId]);
    }

    public static function handleBatchResume(): void {
        self::guard('fieldflow_schema_batch_resume');
        $batchId = absint($_POST['batch_id'] ?? 0);
        if ($batchId > 0) BatchMigrationManager::resume($batchId);
        self::redirectProcesses('batch_resumed', ['batch_id' => $batchId]);
    }

    public static function handleReleaseGate(): void {
        self::guard('fieldflow_release_gate_run');
        ReleaseGate::run(get_current_user_id());
        self::redirectHealth('gate_completed');
    }

    /**
     * Embedded tab used by FieldFlow > Saúde do sistema.
     */
    public static function renderHealthPanel(): void {
        if (!current_user_can('routespro_manage')) return;

        $schema = SchemaIntegrity::status();
        $migrations = MigrationLedger::status();
        $migrationRows = MigrationLedger::recent(20);
        $batchStatus = BatchMigrationManager::status();
        $gate = ReleaseGate::latest();
        $notice = sanitize_key((string)($_GET['fieldflow_schema_notice'] ?? ''));
        $problemTables = [];
        foreach ((array)($schema['tables'] ?? []) as $suffix => $table) {
            if (($table['status'] ?? 'healthy') !== 'healthy') $problemTables[$suffix] = $table;
        }

        self::healthStyles();
        if ($notice !== '') {
            $class = in_array($notice, ['full_failed'], true) ? 'ff-si-note ff-si-warning' : 'ff-si-note';
            echo '<div class="' . esc_attr($class) . '">' . esc_html(self::noticeText($notice)) . '</div>';
        }
        ?>
        <div class="ff-si-card">
          <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap">
            <div>
              <span class="ff-si-muted" style="font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.08em">Schema Integrity &amp; Release Safety</span>
              <h2 style="margin:5px 0 7px">Base de dados e segurança de release</h2>
              <p style="margin:0;max-width:920px;color:#475569">Auditoria não destrutiva do schema, histórico determinístico das migrações e validação técnica antes de colocar uma build em produção.</p>
            </div>
            <span class="ff-si-chip <?php echo esc_attr((string)($schema['status'] ?? 'unknown')); ?>"><?php echo esc_html(strtoupper((string)($schema['status'] ?? 'unknown'))); ?></span>
          </div>
          <div class="ff-si-actions" style="margin-top:16px">
            <?php self::actionForm('fieldflow_schema_quick_audit', 'fieldflow_schema_quick_audit', 'Executar diagnóstico rápido', 'button button-primary'); ?>
            <?php self::actionForm('fieldflow_schema_full_audit', 'fieldflow_schema_full_audit', 'Iniciar auditoria integral', 'button'); ?>
            <?php self::actionForm('fieldflow_release_gate_run', 'fieldflow_release_gate_run', 'Executar release gate', 'button'); ?>
          </div>
        </div>

        <div class="ff-si-grid">
          <div class="ff-si-card ff-si-kpi"><span>Tabelas no manifesto</span><strong><?php echo count(SchemaManifest::tables()); ?></strong><small class="ff-si-muted">Estrutura esperada pela build atual.</small></div>
          <div class="ff-si-card ff-si-kpi"><span>Tabelas verificadas</span><strong><?php echo (int)($schema['counts']['checked'] ?? 0); ?></strong><small class="ff-si-muted">Modo <?php echo esc_html((string)($schema['mode'] ?? 'none')); ?>.</small></div>
          <div class="ff-si-card ff-si-kpi"><span>Avisos</span><strong><?php echo (int)($schema['counts']['warning'] ?? 0); ?></strong><small class="ff-si-muted">Tipos, nulabilidade ou índices.</small></div>
          <div class="ff-si-card ff-si-kpi"><span>Críticos</span><strong><?php echo (int)($schema['counts']['critical'] ?? 0); ?></strong><small class="ff-si-muted">Tabelas ou colunas essenciais.</small></div>
          <div class="ff-si-card ff-si-kpi"><span>Migrações falhadas</span><strong><?php echo (int)($migrations['failed'] ?? 0); ?></strong><small class="ff-si-muted">A versão não avança após falha.</small></div>
          <div class="ff-si-card ff-si-kpi"><span>Release gate</span><strong><?php echo isset($gate['score']) ? (int)$gate['score'] . '%' : 'N/D'; ?></strong><small class="ff-si-muted"><?php echo !empty($gate['generated_at']) ? esc_html(mysql2date('d/m/Y H:i', (string)$gate['generated_at'])) : 'Ainda não executado.'; ?></small></div>
        </div>

        <div class="ff-si-two">
          <div class="ff-si-card">
            <h2>Resultado do schema</h2>
            <p class="ff-si-muted"><?php echo esc_html((string)($schema['summary'] ?? 'Ainda não existem resultados.')); ?></p>
            <?php if (!empty($schema['generated_at'])): ?><p><strong>Última execução:</strong> <?php echo esc_html(mysql2date('d/m/Y H:i:s', (string)$schema['generated_at'])); ?>, <?php echo (int)($schema['duration_ms'] ?? 0); ?> ms.</p><?php endif; ?>
            <?php if ($problemTables): ?>
              <div class="ff-si-table"><table class="widefat striped"><thead><tr><th>Tabela</th><th>Estado</th><th>Problemas</th></tr></thead><tbody>
              <?php foreach ($problemTables as $suffix => $table): ?>
                <tr>
                  <td><code><?php echo esc_html((string)($table['table'] ?? $suffix)); ?></code></td>
                  <td><span class="ff-si-chip <?php echo esc_attr((string)($table['status'] ?? 'warning')); ?>"><?php echo esc_html(strtoupper((string)($table['status'] ?? 'warning'))); ?></span></td>
                  <td>
                    <?php $issues = array_slice((array)($table['issues'] ?? []), 0, 5); ?>
                    <?php foreach ($issues as $issue): ?><div><?php echo esc_html((string)($issue['message'] ?? 'Divergência detetada.')); ?></div><?php endforeach; ?>
                    <?php if (count((array)($table['issues'] ?? [])) > 5): ?><div class="ff-si-muted">Mais <?php echo count((array)$table['issues']) - 5; ?> ocorrências.</div><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody></table></div>
            <?php else: ?>
              <div class="ff-si-note">Não existem divergências no último conjunto de tabelas verificado.</div>
            <?php endif; ?>
          </div>

          <div class="ff-si-card">
            <h2>Auditoria integral</h2>
            <p class="ff-si-muted">O trabalho pesado é executado em lotes através da infraestrutura existente do Centro de Processos.</p>
            <?php $lastBatch = (array)($batchStatus['last'] ?? []); ?>
            <?php if ($lastBatch): ?>
              <?php
              $rawTotal = max(0, (int)($lastBatch['total_rows'] ?? 0));
              $rawProcessed = max(0, (int)($lastBatch['processed_rows'] ?? 0));
              $total = max(1, $rawTotal);
              $processed = $rawTotal > 0 ? min($total, $rawProcessed) : $rawProcessed;
              $pct = $rawTotal > 0 ? (int)round(($processed / $total) * 100) : ((string)($lastBatch['status'] ?? '') === 'completed' ? 100 : 0);
              ?>
              <p><span class="ff-si-chip <?php echo esc_attr((string)($lastBatch['status'] ?? 'pending')); ?>"><?php echo esc_html(strtoupper((string)($lastBatch['status'] ?? 'pending'))); ?></span></p>
              <div class="ff-si-progress"><span style="width:<?php echo $pct; ?>%"></span></div>
              <p><?php if ($rawTotal > 0): ?><strong><?php echo $processed; ?></strong> de <strong><?php echo $rawTotal; ?></strong> unidades, <?php echo $pct; ?>%.<?php else: ?><strong><?php echo $rawProcessed; ?></strong> registos processados.<?php endif; ?></p>
            <?php else: ?>
              <p>Ainda não foi iniciada uma auditoria integral.</p>
            <?php endif; ?>
            <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . ProcessCenter::SLUG)); ?>">Abrir Centro de Processos</a></p>
          </div>
        </div>

        <div class="ff-si-card" style="margin-top:18px">
          <h2>Release gate não destrutivo</h2>
          <?php if ($gate): ?>
            <p><span class="ff-si-chip <?php echo esc_attr((string)($gate['status'] ?? 'conditional')); ?>"><?php echo esc_html(strtoupper((string)($gate['status'] ?? 'conditional'))); ?></span> <strong style="margin-left:8px">Pontuação <?php echo (int)($gate['score'] ?? 0); ?>%</strong></p>
            <?php foreach ((array)($gate['checks'] ?? []) as $check): ?>
              <div class="ff-si-check"><div><strong><?php echo esc_html((string)($check['label'] ?? 'Verificação')); ?></strong><p><?php echo esc_html((string)($check['detail'] ?? '')); ?></p></div><span class="ff-si-chip <?php echo esc_attr((string)($check['status'] ?? 'warning')); ?>"><?php echo esc_html(strtoupper((string)($check['status'] ?? 'warning'))); ?></span></div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="ff-si-muted">Ainda não foi executado. O gate valida versão, runtime, REST, contrato Flutter, schema, migrações, fila, cron, uploads, assets e ambiente.</p>
          <?php endif; ?>
        </div>

        <div class="ff-si-card" style="margin-top:18px">
          <h2>Histórico de migrações</h2>
          <?php if ($migrationRows): ?>
            <div class="ff-si-table"><table class="widefat striped"><thead><tr><th>Versão</th><th>Estado</th><th>Tentativas</th><th>Duração</th><th>Início</th><th>Erro</th></tr></thead><tbody>
            <?php foreach ($migrationRows as $row): ?>
              <tr><td><code><?php echo esc_html((string)$row['version']); ?></code></td><td><span class="ff-si-chip <?php echo esc_attr((string)$row['status']); ?>"><?php echo esc_html(strtoupper((string)$row['status'])); ?></span></td><td><?php echo (int)$row['attempts']; ?></td><td><?php echo (int)$row['duration_ms']; ?> ms</td><td><?php echo !empty($row['started_at']) ? esc_html(mysql2date('d/m/Y H:i:s', (string)$row['started_at'])) : 'N/D'; ?></td><td><?php echo esc_html((string)($row['error_message'] ?? '')); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
          <?php else: ?><p class="ff-si-muted">O histórico começa a ser registado a partir da 2.2.266.</p><?php endif; ?>
        </div>
        <?php
    }

    /**
     * Embedded card used by FieldFlow > Centro de Processos.
     */
    public static function renderProcessPanel(): void {
        if (!current_user_can('routespro_manage')) return;

        $batchStatus = BatchMigrationManager::status();
        $batches = BatchMigrationManager::recent(20);
        $notice = sanitize_key((string)($_GET['fieldflow_schema_notice'] ?? ''));

        if ($notice !== '' && in_array($notice, ['batch_processed', 'batch_resumed'], true)) {
            \RoutesPro\Support\AdminPage::notice(self::noticeText($notice), 'updated');
        }

        echo '<div class="ffpc-card"><div class="ffpc-card-head"><div><span class="ffpc-kicker">Migrações e manutenção</span><h2>Execuções técnicas em lotes</h2><p>Auditorias integrais e futuras migrações pesadas usam lotes retomáveis, sem bloquear o backoffice.</p></div>';
        $last = (array)($batchStatus['last'] ?? []);
        if ($last) {
            $status = sanitize_key((string)($last['status'] ?? 'pending'));
            echo '<span class="ffpc-status ' . esc_attr($status) . '">' . esc_html(strtoupper($status)) . '</span>';
        }
        echo '</div>';

        if (!$batches) {
            echo '<p class="ffpc-muted">Ainda não existem execuções técnicas em lotes. A auditoria integral pode ser iniciada em Saúde do sistema, no separador Schema e releases.</p></div>';
            return;
        }

        echo '<div class="ffpc-table"><table class="widefat striped"><thead><tr><th>ID</th><th>Tarefa</th><th>Estado</th><th>Progresso</th><th>Tentativas</th><th>Atualização</th><th>Ação</th></tr></thead><tbody>';
        foreach ($batches as $row) {
            $rawTotal = max(0, (int)$row['total_rows']);
            $rawProcessed = max(0, (int)$row['processed_rows']);
            $progress = $rawTotal > 0 ? min($rawTotal, $rawProcessed) . ' / ' . $rawTotal : $rawProcessed . ' processados';
            $status = sanitize_key((string)$row['status']);
            echo '<tr><td>#' . (int)$row['id'] . '</td><td><code>' . esc_html((string)$row['task_key']) . '</code></td><td><span class="ffpc-status ' . esc_attr($status) . '">' . esc_html(strtoupper($status)) . '</span></td><td>' . esc_html($progress) . '</td><td>' . (int)$row['attempts'] . '</td><td>' . esc_html(mysql2date('d/m/Y H:i:s', (string)$row['updated_at'])) . '</td><td>';
            if (in_array($status, ['pending', 'running'], true)) {
                self::batchActionForm('fieldflow_schema_batch_now', 'fieldflow_schema_batch_now', (int)$row['id'], 'Processar próximo lote');
            } elseif (in_array($status, ['paused', 'failed'], true)) {
                self::batchActionForm('fieldflow_schema_batch_resume', 'fieldflow_schema_batch_resume', (int)$row['id'], 'Retomar');
            } else {
                echo '<span class="ffpc-muted">Sem ação</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div><p style="margin-bottom:0"><a class="button" href="' . esc_url(admin_url('admin.php?page=routespro-system-health&ff_health_tab=schema')) . '">Ver diagnóstico e release gate</a></p></div>';
    }

    private static function healthStyles(): void {
        echo '<style>
          .ff-si-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:18px 0}.ff-si-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 10px 28px rgba(15,23,42,.05)}.ff-si-card h2,.ff-si-card h3{margin-top:0}.ff-si-kpi span{display:block;color:#64748b;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}.ff-si-kpi strong{display:block;font-size:30px;margin-top:6px}.ff-si-chip{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:900}.ff-si-chip.healthy,.ff-si-chip.ready,.ff-si-chip.pass,.ff-si-chip.completed{background:#dcfce7;color:#166534}.ff-si-chip.warning,.ff-si-chip.conditional,.ff-si-chip.pending,.ff-si-chip.running,.ff-si-chip.paused{background:#fef3c7;color:#92400e}.ff-si-chip.critical,.ff-si-chip.blocked,.ff-si-chip.fail,.ff-si-chip.failed{background:#fee2e2;color:#991b1b}.ff-si-chip.unknown{background:#e2e8f0;color:#334155}.ff-si-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.ff-si-actions form{margin:0}.ff-si-note{padding:14px 16px;border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a8a;border-radius:14px;margin:14px 0}.ff-si-warning{border-color:#fed7aa;background:#fff7ed;color:#9a3412}.ff-si-table{overflow:auto}.ff-si-table table{min-width:820px}.ff-si-muted{color:#64748b}.ff-si-progress{height:9px;border-radius:99px;background:#e2e8f0;overflow:hidden}.ff-si-progress span{display:block;height:100%;background:#2563eb}.ff-si-check{display:flex;justify-content:space-between;gap:14px;padding:12px 0;border-bottom:1px solid #e2e8f0}.ff-si-check:last-child{border-bottom:0}.ff-si-check p{margin:4px 0 0;color:#64748b}.ff-si-two{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(300px,.8fr);gap:18px}@media(max-width:1080px){.ff-si-two{grid-template-columns:1fr}}@media(max-width:782px){.ff-si-card{padding:14px}.ff-si-actions form,.ff-si-actions .button{width:100%}.ff-si-actions .button{text-align:center}}
        </style>';
    }

    private static function guard(string $nonceAction): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.', 'FieldFlow', ['response' => 403]);
        check_admin_referer($nonceAction);
    }

    private static function redirectHealth(string $notice, array $extra = []): void {
        $args = array_merge([
            'page' => 'routespro-system-health',
            'ff_health_tab' => 'schema',
            'fieldflow_schema_notice' => $notice,
        ], $extra);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function redirectProcesses(string $notice, array $extra = []): void {
        $args = array_merge([
            'page' => ProcessCenter::SLUG,
            'fieldflow_schema_notice' => $notice,
        ], $extra);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function actionForm(string $action, string $nonce, string $label, string $class): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
        wp_nonce_field($nonce);
        echo '<button type="submit" class="' . esc_attr($class) . '">' . esc_html($label) . '</button></form>';
    }

    private static function batchActionForm(string $action, string $nonce, int $batchId, string $label): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '"><input type="hidden" name="batch_id" value="' . (int)$batchId . '">';
        wp_nonce_field($nonce);
        echo '<button type="submit" class="button button-small">' . esc_html($label) . '</button></form>';
    }

    private static function noticeText(string $notice): string {
        $map = [
            'quick_completed' => 'Diagnóstico rápido concluído. Nenhuma alteração foi aplicada à base de dados.',
            'full_started' => 'Auditoria integral iniciada em lotes. O progresso está disponível no Centro de Processos.',
            'full_failed' => 'Não foi possível iniciar a auditoria integral.',
            'batch_processed' => 'O próximo lote técnico foi processado.',
            'batch_resumed' => 'A execução técnica em lotes foi retomada.',
            'gate_completed' => 'Release gate concluído em modo não destrutivo.',
        ];
        return $map[$notice] ?? 'Operação concluída.';
    }
}
