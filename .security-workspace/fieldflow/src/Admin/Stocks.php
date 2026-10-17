<?php
namespace RoutesPro\Admin;

use RoutesPro\Stocks\StockImporter;
use RoutesPro\Stocks\StockService;
use RoutesPro\Stocks\StockStoreMatcher;

if (!defined('ABSPATH')) exit;

class Stocks {
    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_stock_import', [self::class, 'handleImport']);
        add_action('admin_post_fieldflow_stock_map', [self::class, 'handleMap']);
        add_action('admin_post_fieldflow_stock_suggest', [self::class, 'handleSuggest']);
        add_action('admin_post_fieldflow_stock_apply_suggestions', [self::class, 'handleApplySuggestions']);
        add_action('admin_post_fieldflow_stock_bulk_map', [self::class, 'handleBulkMap']);
        add_action('admin_post_fieldflow_stock_reset_mappings', [self::class, 'handleResetMappings']);
        add_action('wp_ajax_fieldflow_stock_ai_prepare', [self::class, 'handleAiPrepare']);
        add_action('wp_ajax_fieldflow_stock_ai_step', [self::class, 'handleAiStep']);
        add_action('admin_post_fieldflow_stock_status', [self::class, 'handleStatus']);
        add_action('admin_post_fieldflow_stock_delete', [self::class, 'handleDelete']);
        add_action('admin_post_fieldflow_stock_bulk_delete', [self::class, 'handleBulkDelete']);
        add_action('admin_post_fieldflow_stock_template', [self::class, 'handleTemplate']);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $clients = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT id,client_id,name,status FROM {$px}projects ORDER BY name ASC", ARRAY_A) ?: [];
        $batchId = absint($_GET['batch_id'] ?? 0);
        $batch = $batchId ? StockService::getBatch($batchId) : null;
        $batches = StockService::listBatches();
        $notice = sanitize_text_field((string)($_GET['ff_stock_notice'] ?? ''));
        $error = sanitize_text_field((string)($_GET['ff_stock_error'] ?? ''));
        ?>
        <div class="wrap ff-stock-admin">
          <style>
            .ff-stock-admin{max-width:1500px}.ff-stock-admin *{box-sizing:border-box}.ff-stock-hero{background:linear-gradient(135deg,#081120,#19324f 58%,#0f766e);color:#fff;border-radius:24px;padding:24px;margin:18px 0;box-shadow:0 18px 45px rgba(15,23,42,.18)}.ff-stock-hero h1{color:#fff;margin:0 0 8px;font-size:30px}.ff-stock-hero p{margin:0;max-width:960px;font-size:15px;line-height:1.6;color:#dbeafe}.ff-stock-grid{display:grid;grid-template-columns:minmax(320px,.8fr) minmax(0,1.2fr);gap:18px;align-items:start}.ff-stock-card{background:#fff;border:1px solid #dbe3ec;border-radius:18px;padding:18px;box-shadow:0 10px 28px rgba(15,23,42,.06);margin-bottom:18px}.ff-stock-card h2,.ff-stock-card h3{margin-top:0}.ff-stock-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ff-stock-form label{display:flex;flex-direction:column;gap:6px;font-weight:700}.ff-stock-form label.full{grid-column:1/-1}.ff-stock-form input,.ff-stock-form select{width:100%}.ff-stock-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:14px 0}.ff-stock-kpi{border:1px solid #e2e8f0;border-radius:14px;padding:12px;background:#f8fafc}.ff-stock-kpi span{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.06em}.ff-stock-kpi strong{display:block;font-size:22px;margin-top:4px}.ff-stock-table-wrap{overflow:auto;width:100%}.ff-stock-table{border-collapse:collapse;width:100%;min-width:920px}.ff-stock-table th,.ff-stock-table td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}.ff-stock-table th{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.ff-stock-badge{display:inline-flex;border-radius:999px;padding:5px 9px;font-size:11px;font-weight:800;background:#e2e8f0}.ff-stock-badge.published{background:#dcfce7;color:#166534}.ff-stock-badge.draft{background:#fef3c7;color:#92400e}.ff-stock-badge.archived{background:#f1f5f9;color:#475569}.ff-stock-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.ff-stock-map{display:grid;grid-template-columns:38px minmax(240px,1fr) minmax(300px,1.25fr) auto;gap:12px;align-items:end;padding:14px 0;border-bottom:1px solid #e2e8f0}.ff-stock-map:last-child{border-bottom:0}.ff-stock-note{padding:12px;border-radius:12px;background:#eff6ff;color:#1e3a8a}.ff-stock-warning{padding:12px;border-radius:12px;background:#fff7ed;color:#9a3412}.ff-stock-success{padding:12px;border-radius:12px;background:#ecfdf5;color:#166534}.ff-stock-ai-panel{border:1px solid #bfdbfe;border-radius:16px;padding:15px;margin:14px 0;background:linear-gradient(135deg,#eff6ff,#f8fafc)}.ff-stock-ai-panel h4{margin:0 0 6px;font-size:16px}.ff-stock-ai-panel p{margin:4px 0 10px;color:#334155}.ff-stock-project-option[hidden]{display:none}.ff-stock-meta{color:#64748b;font-size:12px}.ff-stock-toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}.ff-stock-suggestion{margin-top:7px;padding:8px 10px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0}.ff-stock-suggestion strong{font-size:12px}.ff-stock-confidence{display:inline-flex;padding:3px 7px;border-radius:999px;font-size:11px;font-weight:800;margin-left:5px;background:#e2e8f0;color:#334155}.ff-stock-confidence.high{background:#dcfce7;color:#166534}.ff-stock-confidence.medium{background:#fef3c7;color:#92400e}.ff-stock-confidence.low{background:#fee2e2;color:#991b1b}.ff-stock-ai-tag{display:inline-flex;align-items:center;padding:3px 7px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.ff-stock-candidates{display:block;margin-top:4px;color:#64748b;font-size:11px;line-height:1.5}.ff-stock-threshold{width:auto!important;min-width:76px}.ff-stock-empty-suggestion{color:#94a3b8;font-size:12px;margin-top:6px}.ff-stock-select-cell{align-self:center}.ff-stock-bulk-check{width:18px;height:18px}.ff-stock-progress{display:none;margin-top:12px;padding:12px;border-radius:12px;background:#fff;border:1px solid #bfdbfe}.ff-stock-progress.is-active{display:block}.ff-stock-progress-track{height:10px;background:#dbeafe;border-radius:999px;overflow:hidden}.ff-stock-progress-bar{height:100%;width:0;background:#2563eb;transition:width .2s ease}.ff-stock-progress-copy{display:flex;justify-content:space-between;gap:12px;margin-top:7px;font-size:12px;color:#334155}.ff-stock-bulk-panel{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:14px 0;padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.ff-stock-bulk-panel select{width:auto}.ff-stock-map.is-selected{background:#eff6ff}@media(max-width:1000px){.ff-stock-grid{grid-template-columns:1fr}.ff-stock-kpis{grid-template-columns:repeat(3,1fr)}}@media(max-width:680px){.ff-stock-form{grid-template-columns:1fr}.ff-stock-form label.full{grid-column:auto}.ff-stock-kpis{grid-template-columns:repeat(2,1fr)}.ff-stock-map{grid-template-columns:34px 1fr}.ff-stock-map label.ff-stock-select-cell,.ff-stock-map .button{grid-column:2;width:100%}}
  
            .ff-stock-review-tools{display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(2,minmax(150px,.55fr)) auto;gap:10px;align-items:end;margin:14px 0;padding:14px;border:1px solid #cbd5e1;border-radius:14px;background:#fff}.ff-stock-review-tools label{display:flex;flex-direction:column;gap:5px;font-weight:700}.ff-stock-review-tools input,.ff-stock-review-tools select{width:100%}.ff-stock-review-summary{font-size:12px;color:#475569;white-space:nowrap;padding-bottom:7px}.ff-stock-pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:14px 0;flex-wrap:wrap}.ff-stock-pagination-actions{display:flex;gap:7px;align-items:center}.ff-stock-map[hidden]{display:none!important}.ff-stock-brand-lock{display:inline-flex;align-items:center;gap:6px;margin-top:7px;padding:5px 8px;border-radius:999px;background:#ecfdf5;color:#166534;font-size:11px;font-weight:800}@media(max-width:900px){.ff-stock-review-tools{grid-template-columns:1fr 1fr}.ff-stock-review-summary{grid-column:1/-1}}@media(max-width:600px){.ff-stock-review-tools{grid-template-columns:1fr}}

            .ff-stock-history-tools{display:grid;grid-template-columns:minmax(240px,1.2fr) minmax(150px,.5fr) minmax(140px,.45fr);gap:10px;margin:14px 0}.ff-stock-history-tools label{display:flex;flex-direction:column;gap:5px;font-weight:700}.ff-stock-history-tools input,.ff-stock-history-tools select{width:100%}.ff-stock-history-bulk{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 12px;margin-bottom:10px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.ff-stock-history-bulk label{display:flex;align-items:center;gap:7px;font-weight:700}.ff-stock-history-row[hidden]{display:none!important}@media(max-width:800px){.ff-stock-history-tools{grid-template-columns:1fr}}
        </style>
          <section class="ff-stock-hero"><h1>Stocks Operacionais</h1><p>Importa snapshots de stock por cliente, projeto e insígnia. Cada publicação é uma fotografia completa e substitui integralmente o snapshot ativo anterior no mesmo âmbito, nunca soma semanas. O histórico fica arquivado para auditoria e rollback.</p></section>
          <?php if ($notice): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div><?php endif; ?>
          <?php if ($error): ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
          <div class="ff-stock-grid">
            <div>
              <section class="ff-stock-card">
                <h2>Importar novo snapshot</h2><p class="ff-stock-success"><strong>Regra de stock:</strong> cada ficheiro é um update geral. Ao publicar, o novo lote substitui o stock ativo anterior deste Cliente + Projeto + Insígnia. O histórico é preservado, mas nunca entra na soma operacional.</p>
                <p class="ff-stock-note">O formato recebido da Pernod é reconhecido diretamente: Centro, Descr. Centro, Localidade, Artigo, EAN, Descrição Artigo, UM, FC e Stock Total. O formato Continente com STORE, STORE_NAME, ARTIGO, DESC_ARTIGO, EAN e SOH também é reconhecido nativamente. São aceites XLSX ou CSV.</p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ff-stock-form" id="ff-stock-import-form">
                  <input type="hidden" name="action" value="fieldflow_stock_import">
                  <?php wp_nonce_field('fieldflow_stock_import'); ?>
                  <label>Cliente<select name="client_id" id="ff-stock-client" required><option value="">Selecionar</option><?php foreach($clients as $client): ?><option value="<?php echo (int)$client['id']; ?>"><?php echo esc_html($client['name']); ?></option><?php endforeach; ?></select></label>
                  <label>Projeto<select name="project_id" id="ff-stock-project" required><option value="">Selecionar</option><?php foreach($projects as $project): ?><option class="ff-stock-project-option" data-client="<?php echo (int)$project['client_id']; ?>" value="<?php echo (int)$project['id']; ?>"><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select></label>
                  <label>Insígnia<input type="text" name="insignia" placeholder="Ex.: Pingo Doce" required></label>
                  <label>Data de referência<input type="date" name="snapshot_date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required></label>
                  <label class="full">Ficheiro XLSX ou CSV<input type="file" name="stock_file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
                  <label class="full" style="flex-direction:row;align-items:flex-start;font-weight:600"><input type="checkbox" name="publish_now" value="1" checked style="width:auto;margin-top:3px"><span>Publicar imediatamente para a equipa e para o portal do cliente.</span></label>
                  <div class="full ff-stock-actions"><button class="button button-primary button-hero">Importar stock</button><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_stock_template'),'fieldflow_stock_template')); ?>">Descarregar modelo CSV</a></div>
                </form>
              </section>
              <section class="ff-stock-card">
                <h3>Motor de associação</h3>
                <p><strong>Primeira camada:</strong> código Centro, referência externa, nome normalizado e localidade.</p>
                <p><strong>Segunda camada:</strong> cria uma lista curta com os três PDVs mais plausíveis.</p>
                <p><strong>Validação IA:</strong> quando está disponível, a IA escolhe apenas dentro dessa lista segura. Não pode inventar IDs nem lojas.</p>
                <p><strong>Aplicação em massa:</strong> apenas sugestões acima do limite de confiança escolhido são associadas automaticamente.</p>
              </section>
            </div>
            <div>
              <?php if ($batch): self::renderBatch($batch); endif; ?>
              <section class="ff-stock-card">
                <div class="ff-stock-toolbar"><div><h2 style="margin-bottom:4px">Histórico de importações</h2><span class="ff-stock-meta"><?php echo count($batches); ?> lotes registados. Eliminar um lote remove o snapshot e as respetivas linhas, mas mantém as associações de lojas aprendidas para futuras importações.</span></div></div>
                <div class="ff-stock-history-tools">
                  <label>Pesquisar<input type="search" id="ff-stock-history-search" placeholder="Insígnia, cliente, projeto ou ficheiro"></label>
                  <label>Insígnia<select id="ff-stock-history-insignia"><option value="">Todas</option><?php foreach(array_values(array_unique(array_filter(array_map(static fn($r)=>(string)$r['insignia'],$batches)))) as $ins): ?><option value="<?php echo esc_attr($ins); ?>"><?php echo esc_html($ins); ?></option><?php endforeach; ?></select></label>
                  <label>Estado<select id="ff-stock-history-status"><option value="">Todos</option><option value="published">Publicado</option><option value="draft">Rascunho</option><option value="archived">Arquivado</option></select></label>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ff-stock-history-form">
                  <input type="hidden" name="action" value="fieldflow_stock_bulk_delete">
                  <?php wp_nonce_field('fieldflow_stock_bulk_delete'); ?>
                  <div class="ff-stock-history-bulk"><label><input type="checkbox" id="ff-stock-history-select-all"> Selecionar visíveis</label><button type="submit" class="button button-link-delete" id="ff-stock-history-delete-selected" onclick="return confirm('Eliminar definitivamente os lotes selecionados e todas as respetivas linhas de stock?');">Eliminar selecionados</button><span class="ff-stock-meta" id="ff-stock-history-visible"></span></div>
                  <div class="ff-stock-table-wrap"><table class="ff-stock-table"><thead><tr><th style="width:42px"></th><th>Data</th><th>Cliente e projeto</th><th>Insígnia</th><th>Cobertura</th><th>Alertas</th><th>Estado</th><th></th></tr></thead><tbody>
                    <?php if(!$batches): ?><tr><td colspan="8">Ainda não existem importações.</td></tr><?php endif; ?>
                    <?php foreach($batches as $row): $coverage=(int)$row['store_count']?round(((int)$row['matched_store_count']/(int)$row['store_count'])*100):0; $historySearch=strtolower(trim($row['insignia'].' '.$row['client_name'].' '.$row['project_name'].' '.$row['source_filename'])); ?>
                      <tr class="ff-stock-history-row" data-search="<?php echo esc_attr($historySearch); ?>" data-insignia="<?php echo esc_attr(strtolower((string)$row['insignia'])); ?>" data-status="<?php echo esc_attr($row['status']); ?>"><td><input type="checkbox" class="ff-stock-history-check" name="batch_ids[]" value="<?php echo (int)$row['id']; ?>"></td><td><strong><?php echo esc_html(date_i18n('d/m/Y',strtotime($row['snapshot_date']))); ?></strong><br><span class="ff-stock-meta"><?php echo esc_html($row['source_filename']); ?></span></td><td><?php echo esc_html($row['client_name']); ?><br><strong><?php echo esc_html($row['project_name']); ?></strong></td><td><?php echo esc_html($row['insignia']); ?><br><span class="ff-stock-meta"><?php echo (int)$row['product_count']; ?> produtos</span></td><td><?php echo (int)$row['matched_store_count']; ?> / <?php echo (int)$row['store_count']; ?> lojas<br><span class="ff-stock-meta"><?php echo (int)$coverage; ?>% associadas</span></td><td><?php echo (int)$row['negative_count']; ?> negativos<br><?php echo (int)$row['zero_count']; ?> ruturas, <?php echo (int)$row['low_count']; ?> baixos</td><td><span class="ff-stock-badge <?php echo esc_attr($row['status']); ?>"><?php echo esc_html($row['status']); ?></span></td><td><div class="ff-stock-actions"><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=routespro-stocks&batch_id='.(int)$row['id'])); ?>">Abrir</a><button type="submit" name="single_batch_id" value="<?php echo (int)$row['id']; ?>" class="button button-small button-link-delete" onclick="return confirm('Eliminar definitivamente este lote e todas as respetivas linhas de stock?');">Eliminar</button></div></td></tr>
                    <?php endforeach; ?>
                  </tbody></table></div>
                </form>
              </section>
            </div>
          </div>
        </div>
        <script>
        (function(){const client=document.getElementById('ff-stock-client'),project=document.getElementById('ff-stock-project');if(client&&project){function sync(){const id=client.value;Array.from(project.options).forEach((o,i)=>{if(i===0)return;const show=!id||o.dataset.client===id;o.hidden=!show;o.disabled=!show;if(!show&&o.selected)project.value='';});}client.addEventListener('change',sync);sync();}
          const q=document.getElementById('ff-stock-history-search'),ins=document.getElementById('ff-stock-history-insignia'),status=document.getElementById('ff-stock-history-status'),all=document.getElementById('ff-stock-history-select-all'),visible=document.getElementById('ff-stock-history-visible'),form=document.getElementById('ff-stock-history-form');
          function filterHistory(){if(!form)return;const query=(q?.value||'').trim().toLowerCase(),brand=(ins?.value||'').toLowerCase(),state=status?.value||'';let count=0;form.querySelectorAll('.ff-stock-history-row').forEach(row=>{const show=(!query||(row.dataset.search||'').includes(query))&&(!brand||row.dataset.insignia===brand)&&(!state||row.dataset.status===state);row.hidden=!show;if(show)count++;else row.querySelector('.ff-stock-history-check').checked=false;});if(visible)visible.textContent=count+' lotes visíveis';if(all)all.checked=false;}
          q?.addEventListener('input',filterHistory);ins?.addEventListener('change',filterHistory);status?.addEventListener('change',filterHistory);all?.addEventListener('change',()=>{form?.querySelectorAll('.ff-stock-history-row:not([hidden]) .ff-stock-history-check').forEach(c=>c.checked=all.checked);});filterHistory();
        })();
        </script>
        <?php
    }

    private static function renderBatch(array $batch): void {
        $batchId = (int)$batch['id'];
        $repair = StockService::repairBatchScope($batchId);
        // 9DM: legacy mappings remain stored, and are reviewed rather than deleted on GET.
        $unmatched = StockService::unmatchedStores($batchId);
        $locations = StockService::locationIndex((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia'])['rows'];
        $suggestionData = StockStoreMatcher::suggestions($batchId);
        $suggestions = $suggestionData['items'];
        $suggestionMeta = $suggestionData['meta'];
        $aiProgress = is_array($suggestionMeta['ai_progress'] ?? null) ? $suggestionMeta['ai_progress'] : [];
        $aiProgressTotal = max(0, (int)($aiProgress['total'] ?? count((array)($aiProgress['queue'] ?? []))));
        $aiProgressProcessed = max(0, min($aiProgressTotal, (int)($aiProgress['processed'] ?? $aiProgress['offset'] ?? 0)));
        $aiProgressReviewed = max(0, (int)($aiProgress['reviewed'] ?? 0));
        $aiProgressPercent = $aiProgressTotal > 0 ? (int)round(($aiProgressProcessed / $aiProgressTotal) * 100) : 0;
        $aiProgressActive = $aiProgressTotal > 0;
        $aiProgressDone = $aiProgressActive && ($aiProgressProcessed >= $aiProgressTotal || (string)($aiProgress['status'] ?? '') === 'completed');
        $coverage = (int)$batch['store_count'] ? round(((int)$batch['matched_store_count']/(int)$batch['store_count'])*100) : 0;
        $mappingPayload = json_decode((string)($batch['mapping_json'] ?? ''), true);
        if (!is_array($mappingPayload)) $mappingPayload = [];
        $importDiagnostics = is_array($mappingPayload['_import_intelligence'] ?? null) ? $mappingPayload['_import_intelligence'] : [];
        $mappingConfidence = is_array($mappingPayload['_mapping_confidence'] ?? null) ? $mappingPayload['_mapping_confidence'] : [];
        $productMapping = is_array($mappingPayload['_product_mapping'] ?? null) ? $mappingPayload['_product_mapping'] : [];
        $snapshotPolicy = is_array($mappingPayload['_snapshot_policy'] ?? null) ? $mappingPayload['_snapshot_policy'] : [];
        $activeScopeBatch = StockService::activeSnapshotForScope((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia'], $batchId);
        ?>
        <section class="ff-stock-card">
          <div class="ff-stock-toolbar"><div><h2 style="margin-bottom:4px"><?php echo esc_html($batch['insignia']); ?>, <?php echo esc_html(date_i18n('d/m/Y',strtotime($batch['snapshot_date']))); ?></h2><span class="ff-stock-meta"><?php echo esc_html($batch['client_name'].' · '.$batch['project_name'].' · '.$batch['source_filename']); ?></span></div><span class="ff-stock-badge <?php echo esc_attr($batch['status']); ?>"><?php echo esc_html($batch['status']); ?></span></div>
          <div class="ff-stock-kpis">
            <div class="ff-stock-kpi"><span>Linhas</span><strong><?php echo number_format_i18n((int)$batch['row_count']); ?></strong></div>
            <div class="ff-stock-kpi"><span>Lojas</span><strong><?php echo (int)$batch['store_count']; ?></strong></div>
            <div class="ff-stock-kpi"><span>Cobertura</span><strong><?php echo (int)$coverage; ?>%</strong></div>
            <div class="ff-stock-kpi"><span>Ruturas</span><strong><?php echo (int)$batch['zero_count']; ?></strong></div>
            <div class="ff-stock-kpi"><span>Stock baixo</span><strong><?php echo (int)$batch['low_count']; ?></strong></div>
            <div class="ff-stock-kpi"><span>Negativos</span><strong><?php echo (int)$batch['negative_count']; ?></strong></div>
          </div>
          <?php if($batch['status']!=='published' && $activeScopeBatch): ?>
          <p class="ff-stock-note"><strong>Substituição prevista:</strong> o snapshot ativo atual #<?php echo (int)$activeScopeBatch['id']; ?> tem <?php echo esc_html(number_format_i18n((float)$activeScopeBatch['total_stock'],3)); ?> unidades. Ao publicar este lote, o total operacional passa para <strong><?php echo esc_html(number_format_i18n((float)$batch['total_stock'],3)); ?></strong> unidades. A diferença é <?php echo esc_html(number_format_i18n((float)$batch['total_stock']-(float)$activeScopeBatch['total_stock'],3)); ?>, nunca a soma dos dois lotes.</p>
          <?php elseif($batch['status']==='published'): ?>
          <p class="ff-stock-success"><strong>Snapshot ativo:</strong> este é o único lote que entra no stock operacional deste Cliente + Projeto + Insígnia. <?php if(!empty($snapshotPolicy['replaced_count'])): ?>Foram arquivados <?php echo (int)$snapshotPolicy['replaced_count']; ?> snapshot(s) anterior(es) quando este lote foi ativado.<?php endif; ?></p>
          <?php endif; ?>
          <div class="ff-stock-actions">
            <?php if($batch['status']!=='published'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_stock_status"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><input type="hidden" name="status" value="published"><?php wp_nonce_field('fieldflow_stock_status_'.$batchId); ?><button class="button button-primary">Publicar como novo snapshot geral</button></form><?php endif; ?>
            <?php if($batch['status']==='published'): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_stock_status"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><input type="hidden" name="status" value="archived"><?php wp_nonce_field('fieldflow_stock_status_'.$batchId); ?><button class="button">Arquivar</button></form><?php endif; ?>
            <span class="ff-stock-meta"><strong>Snapshot replace:</strong> só este lote entra no stock operacional quando publicado. Lotes anteriores do mesmo Cliente + Projeto + Insígnia ficam arquivados, sem somar valores.</span>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Limpar todas as associações deste lote e recomeçar? Esta ação não elimina o stock importado.');"><input type="hidden" name="action" value="fieldflow_stock_reset_mappings"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><?php wp_nonce_field('fieldflow_stock_reset_mappings_'.$batchId); ?><button class="button">Repor associações</button></form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Eliminar definitivamente este lote e todas as linhas de stock?');"><input type="hidden" name="action" value="fieldflow_stock_delete"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><?php wp_nonce_field('fieldflow_stock_delete_'.$batchId); ?><button class="button button-link-delete">Eliminar lote</button></form>
          </div>
        </section>
        <?php if($importDiagnostics):
          $ignoredRows=(int)($importDiagnostics['skipped_blank']??0)+(int)($importDiagnostics['skipped_total']??0)+(int)($importDiagnostics['skipped_missing_identity']??0)+(int)($importDiagnostics['skipped_invalid_stock']??0);
          $rawTotal=(float)($importDiagnostics['raw_stock_total']??0);
          $dbTotal=(float)($batch['total_stock']??0);
          $totalDelta=abs($rawTotal-$dbTotal);
          $candidateSheets=is_array($importDiagnostics['candidate_sheets']??null)?$importDiagnostics['candidate_sheets']:[];
        ?>
        <section class="ff-stock-card">
          <div class="ff-stock-toolbar"><div><h3 style="margin-bottom:4px">Auditoria de leitura do Excel</h3><span class="ff-stock-meta">O FieldFlow mostra agora como interpretou o ficheiro antes de o stock chegar à operação.</span></div><span class="ff-stock-badge <?php echo $totalDelta<0.01?'published':'draft'; ?>"><?php echo $totalDelta<0.01?'Totais coerentes':'Rever total'; ?></span></div>
          <div class="ff-stock-kpis">
            <div class="ff-stock-kpi"><span>Folha escolhida</span><strong style="font-size:15px"><?php echo esc_html((string)($importDiagnostics['selected_sheet']??$batch['sheet_name'])); ?></strong></div>
            <div class="ff-stock-kpi"><span>Cabeçalho</span><strong><?php echo (int)($batch['header_row']??0); ?><?php $hend=(int)($mappingPayload['_header_end_row']??0); if($hend>(int)$batch['header_row']): ?>-<?php echo $hend; ?><?php endif; ?></strong></div>
            <div class="ff-stock-kpi"><span>Linhas fonte</span><strong><?php echo number_format_i18n((int)($importDiagnostics['source_data_rows']??0)); ?></strong></div>
            <div class="ff-stock-kpi"><span>Aceites</span><strong><?php echo number_format_i18n((int)($importDiagnostics['accepted_source_rows']??0)); ?></strong></div>
            <div class="ff-stock-kpi"><span>Ignoradas</span><strong><?php echo number_format_i18n($ignoredRows); ?></strong></div>
            <div class="ff-stock-kpi"><span>Confiança colunas</span><strong><?php echo (int)($importDiagnostics['mapping_average_confidence']??($mappingConfidence?round(array_sum($mappingConfidence)/count($mappingConfidence)):0)); ?>%</strong></div>
          </div>
          <p class="<?php echo $totalDelta<0.01?'ff-stock-success':'ff-stock-warning'; ?>"><strong>Reconciliação:</strong> total lido no Excel <?php echo esc_html(number_format_i18n($rawTotal,3)); ?>, total gravado <?php echo esc_html(number_format_i18n($dbTotal,3)); ?>. <?php if(!empty($importDiagnostics['duplicates_collapsed'])): ?>Foram consolidadas <?php echo (int)$importDiagnostics['duplicates_collapsed']; ?> linha(s) repetidas da mesma loja/produto sem alterar o total.<?php endif; ?></p>
          <?php if($productMapping): ?>
          <div class="ff-stock-kpis" style="margin-top:12px">
            <div class="ff-stock-kpi"><span>Cardex reconhecido</span><strong><?php echo esc_html(number_format_i18n((float)($productMapping['coverage_percent']??0),1)); ?>%</strong></div>
            <div class="ff-stock-kpi"><span>Linhas com produto</span><strong><?php echo number_format_i18n((int)($productMapping['mapped_rows']??0)); ?></strong></div>
            <div class="ff-stock-kpi"><span>Por rever</span><strong><?php echo number_format_i18n((int)($productMapping['unmatched_rows']??0)); ?></strong></div>
            <div class="ff-stock-kpi"><span>EAN exato</span><strong><?php echo number_format_i18n((int)($productMapping['exact_ean']??0)); ?></strong></div>
            <div class="ff-stock-kpi"><span>Ref. exata</span><strong><?php echo number_format_i18n((int)($productMapping['exact_reference']??0)); ?></strong></div>
            <div class="ff-stock-kpi"><span>Nome exato</span><strong><?php echo number_format_i18n((int)($productMapping['exact_name']??0)); ?></strong></div>
          </div>
          <p class="ff-stock-meta"><strong>Reconciliação produto ↔ Cardex:</strong> o stock fica ligado ao produto apenas quando a referência, EAN ou correspondência atingem confiança elevada. Linhas sem correspondência continuam importadas, mas ficam identificadas para revisão em vez de serem atribuídas ao produto errado.</p>
          <?php endif; ?>
          <?php if($candidateSheets): ?><details><summary><strong>Ver folhas candidatas e decisão automática</strong></summary><div class="ff-stock-table-wrap" style="margin-top:10px"><table class="ff-stock-table"><thead><tr><th>Folha</th><th>Cabeçalho</th><th>Linhas válidas</th><th>Qualidade</th><th>Estado</th></tr></thead><tbody><?php foreach($candidateSheets as $sheet): ?><tr><td><?php echo esc_html((string)($sheet['sheet']??'')); ?></td><td><?php echo (int)($sheet['header_row']??0); ?><?php if((int)($sheet['header_end_row']??0)>(int)($sheet['header_row']??0)): ?>-<?php echo (int)$sheet['header_end_row']; ?><?php endif; ?></td><td><?php echo number_format_i18n((int)($sheet['accepted_rows']??0)); ?></td><td><?php echo (int)($sheet['quality']??0); ?></td><td><?php echo !empty($sheet['selected'])?'<span class="ff-stock-badge published">Selecionada</span>':'Candidata'; ?></td></tr><?php endforeach; ?></tbody></table></div></details><?php endif; ?>
        </section>
        <?php endif; ?>
        <section class="ff-stock-card">
          <h3>Associação de lojas</h3>
          <?php if(!empty($repair['review_count'])): ?><p class="ff-stock-warning"><strong>Revisao de insígnias:</strong> <?php echo (int)$repair['review_count']; ?> loja(s) com associacao antiga incompatível ou sem identidade comprovada. Nenhum stock nem mapeamento foi apagado automaticamente. Estas lojas estao ocultas no portal até serem revistas no painel abaixo.</p><?php endif; ?>
          <?php if(!$unmatched): ?>
            <p class="ff-stock-success"><strong>100% operacional.</strong> Todas as lojas do ficheiro estão associadas a PDVs do FieldFlow.</p>
          <?php else: ?>
            <p class="ff-stock-warning"><strong><?php echo count($unmatched); ?> lojas por validar.</strong> Estas linhas não aparecem na app até serem associadas, evitando stocks na loja errada.</p>
            <div class="ff-stock-ai-panel">
              <h4>Assistente inteligente de correspondência</h4>
              <p>O motor compara códigos, nomes e localidades. A IA, quando ativa, apenas valida candidatos já existentes no FieldFlow.</p>
              <div class="ff-stock-actions">
                <?php if(StockStoreMatcher::aiAvailable()): ?>
                  <button type="button" class="button button-primary" id="ff-stock-ai-start" data-batch="<?php echo $batchId; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('fieldflow_stock_ai_'.$batchId)); ?>"><?php echo $aiProgressActive && !$aiProgressDone ? 'Continuar validação com IA' : ($aiProgressDone ? 'Revalidar pendentes com IA' : 'Gerar e validar com IA'); ?></button>
                  <button type="button" class="button" id="ff-stock-ai-stop" style="display:none">Parar após este bloco</button>
                  <span class="ff-stock-meta">Fornecedor: <?php echo esc_html(StockStoreMatcher::providerLabel()); ?>. Processamento faseado, seis lojas por pedido.</span>
                <?php else: ?>
                  <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="fieldflow_stock_suggest"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><?php wp_nonce_field('fieldflow_stock_suggest_'.$batchId); ?><button class="button button-primary">Gerar sugestões inteligentes</button></form>
                  <span class="ff-stock-meta">A IA não está ativa. O motor local continua disponível.</span>
                <?php endif; ?>
              </div>
              <div class="ff-stock-progress<?php echo $aiProgressActive ? ' is-active' : ''; ?>" id="ff-stock-ai-progress"><div class="ff-stock-progress-track"><div class="ff-stock-progress-bar" id="ff-stock-ai-bar" style="width:<?php echo $aiProgressPercent; ?>%"></div></div><div class="ff-stock-progress-copy"><span id="ff-stock-ai-copy"><?php echo $aiProgressActive ? esc_html(sprintf('%d de %d lojas analisadas, %d validadas pela IA%s', $aiProgressProcessed, $aiProgressTotal, $aiProgressReviewed, $aiProgressDone ? '. Processo concluído.' : '. Progresso guardado.')) : 'A preparar validação...'; ?></span><strong id="ff-stock-ai-percent"><?php echo $aiProgressPercent; ?>%</strong></div></div>
              <?php if($suggestions): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ff-stock-actions" style="margin-top:12px" onsubmit="return confirm('Aplicar automaticamente apenas sugestões acima do limite escolhido?');">
                  <input type="hidden" name="action" value="fieldflow_stock_apply_suggestions"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><?php wp_nonce_field('fieldflow_stock_apply_suggestions_'.$batchId); ?>
                  <label style="display:flex;gap:7px;align-items:center;font-weight:700">Confiança mínima <select class="ff-stock-threshold" name="threshold"><option value="95">95%</option><option value="90" selected>90%</option><option value="85">85%</option></select></label>
                  <button class="button">Associar automaticamente</button>
                  <span class="ff-stock-meta"><?php echo count($suggestions); ?> sugestões disponíveis<?php if(!empty($suggestionMeta['reviewed_by_ai'])): ?>, <?php echo (int)$suggestionMeta['reviewed_by_ai']; ?> revistas pela IA<?php endif; ?>.</span>
                </form>
                <?php if(!empty($suggestionMeta['ai_error'])): ?><p class="ff-stock-meta">A IA não respondeu, foram mantidas as sugestões do motor local: <?php echo esc_html((string)$suggestionMeta['ai_error']); ?></p><?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if($unmatched): ?>
            <div class="ff-stock-review-tools">
              <label>Pesquisar loja<input type="search" id="ff-stock-review-search" placeholder="Nome, centro, localidade ou PDV sugerido"></label>
              <label>Estado da sugestão<select id="ff-stock-review-confidence"><option value="all">Todas</option><option value="high">Alta, 90% ou mais</option><option value="medium">Média, 75% a 89%</option><option value="low">Baixa, menos de 75%</option><option value="none">Sem sugestão</option></select></label>
              <label>Lojas por página<select id="ff-stock-review-page-size"><option value="25">25</option><option value="50" selected>50</option><option value="100">100</option></select></label>
              <div class="ff-stock-review-summary" id="ff-stock-review-summary"></div>
            </div>
            <div class="ff-stock-brand-lock">Âmbito protegido: <?php echo esc_html($batch['insignia']); ?>. Candidatos de outras insígnias são excluídos.</div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="ff-stock-bulk-form" class="ff-stock-bulk-panel">
              <input type="hidden" name="action" value="fieldflow_stock_bulk_map"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><input type="hidden" name="bulk_payload" id="ff-stock-bulk-payload" value=""><?php wp_nonce_field('fieldflow_stock_bulk_map_'.$batchId); ?>
              <strong>Associação múltipla</strong>
              <button type="button" class="button" id="ff-stock-select-all">Selecionar todas</button>
              <label style="display:flex;align-items:center;gap:6px;font-weight:700">Sugestões a partir de <select id="ff-stock-select-threshold"><option value="95">95%</option><option value="90" selected>90%</option><option value="85">85%</option></select></label>
              <button type="button" class="button" id="ff-stock-select-suggested">Selecionar sugestões</button>
              <button type="submit" class="button button-primary" id="ff-stock-bulk-submit">Associar selecionadas (0)</button>
              <span class="ff-stock-meta">Cada linha pode ter um PDV diferente. Duplicados são bloqueados.</span>
            </form>
          <?php endif; ?>

          <?php foreach($unmatched as $store):
              $key = (string)$store['source_store_key'];
              $suggestion = $suggestions[$key] ?? [];
              $suggestedLocation = (int)($suggestion['location_id'] ?? 0);
              $confidence = (int)($suggestion['confidence'] ?? 0);
              $confidenceClass = $confidence >= 90 ? 'high' : ($confidence >= 75 ? 'medium' : 'low');
          ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ff-stock-map" data-store-ref="<?php echo esc_attr($store['store_external_ref']); ?>" data-store-name="<?php echo esc_attr($store['store_name']); ?>" data-confidence="<?php echo $confidence; ?>" data-search="<?php echo esc_attr(strtolower(remove_accents(trim((string)$store['store_name'].' '.(string)$store['store_external_ref'].' '.(string)$store['locality'].' '.(string)($suggestion['location_name']??''))))); ?>">
              <input type="hidden" name="action" value="fieldflow_stock_map"><input type="hidden" name="batch_id" value="<?php echo $batchId; ?>"><input type="hidden" name="store_ref" value="<?php echo esc_attr($store['store_external_ref']); ?>"><input type="hidden" name="store_name" value="<?php echo esc_attr($store['store_name']); ?>"><?php wp_nonce_field('fieldflow_stock_map_'.$batchId); ?>
              <div class="ff-stock-check-cell"><input type="checkbox" class="ff-stock-bulk-check" aria-label="Selecionar <?php echo esc_attr($store['store_name']); ?>"></div>
              <div>
                <strong><?php echo esc_html($store['store_name'] ?: 'Loja sem nome'); ?></strong><?php if(!empty($store['review_reason'])): ?> <span class="ff-stock-badge draft">PDV anterior: <?php echo esc_html((string)($store['linked_location_name'] ?? 'nao identificado')); ?>. Rever insígnia</span><?php endif; ?><br><span class="ff-stock-meta">Centro <?php echo esc_html($store['store_external_ref'] ?: 'n.d.'); ?> · <?php echo esc_html($store['locality']); ?> · <?php echo (int)$store['product_count']; ?> produtos</span>
                <?php if($suggestion): ?>
                  <div class="ff-stock-suggestion"><strong>Sugestão: <?php echo esc_html((string)$suggestion['location_name']); ?></strong><span class="ff-stock-confidence <?php echo esc_attr($confidenceClass); ?>"><?php echo $confidence; ?>%</span><?php if(!empty($suggestion['ai_reviewed'])): ?> <span class="ff-stock-ai-tag">IA validada</span><?php endif; ?><br><span class="ff-stock-meta"><?php echo esc_html((string)$suggestion['reason']); ?></span>
                  <?php $candidateLabels=[]; foreach((array)($suggestion['candidates']??[]) as $candidate){$candidateLabels[]=sanitize_text_field((string)$candidate['name']).' '.(int)$candidate['score'].'%';} if($candidateLabels): ?><span class="ff-stock-candidates">Candidatos: <?php echo esc_html(implode(' · ', $candidateLabels)); ?></span><?php endif; ?></div>
                <?php else: ?><div class="ff-stock-empty-suggestion">Ainda sem sugestão. Usa o assistente acima.</div><?php endif; ?>
              </div>
              <label class="ff-stock-select-cell"><span class="screen-reader-text">Associar loja</span><select name="location_id" class="ff-stock-location-select" required><option value="">Selecionar PDV FieldFlow</option><?php foreach($locations as $location): ?><option value="<?php echo (int)$location['id']; ?>" <?php selected($suggestedLocation,(int)$location['id']); ?>><?php echo esc_html($location['name'] . (!empty($location['external_ref'])?' · Ref. '.$location['external_ref']:'') . (!empty($location['city'])?' · '.$location['city']:'')); ?></option><?php endforeach; ?></select></label>
              <button class="button button-primary">Associar</button>
            </form>
          <?php endforeach; ?>
          <?php if($unmatched): ?><div class="ff-stock-pagination"><span class="ff-stock-meta" id="ff-stock-page-copy"></span><div class="ff-stock-pagination-actions"><button type="button" class="button" id="ff-stock-page-prev">Anterior</button><button type="button" class="button" id="ff-stock-page-next">Seguinte</button></div></div><?php endif; ?>
        </section>
        <script>
        (function(){
          const ajaxUrl=<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
          const start=document.getElementById('ff-stock-ai-start'),stop=document.getElementById('ff-stock-ai-stop'),box=document.getElementById('ff-stock-ai-progress'),bar=document.getElementById('ff-stock-ai-bar'),copy=document.getElementById('ff-stock-ai-copy'),pct=document.getElementById('ff-stock-ai-percent');
          let stopped=false,running=false;
          function paint(data){const percent=Math.max(0,Math.min(100,Number(data.percent||0)));if(box)box.classList.add('is-active');if(bar)bar.style.width=percent+'%';if(pct)pct.textContent=percent+'%';if(copy){let msg=(data.processed||0)+' de '+(data.total||0)+' lojas analisadas, '+(data.reviewed||0)+' validadas pela IA.';if(data.last_error)msg='Validação pausada: '+data.last_error;copy.textContent=msg;}}
          async function call(action,batch,nonce){const body=new URLSearchParams({action,batch_id:String(batch),nonce});const response=await fetch(ajaxUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()});let json=null;try{json=await response.json();}catch(e){}if(!response.ok||!json||!json.success)throw new Error((json&&json.data&&json.data.message)||'O servidor não concluiu este bloco.');return json.data;}
          async function run(){if(!start||running)return;running=true;stopped=false;start.disabled=true;start.textContent='Validação IA em curso';if(stop)stop.style.display='inline-flex';try{let data=await call('fieldflow_stock_ai_prepare',start.dataset.batch,start.dataset.nonce);paint(data);while(!data.done&&!stopped){data=await call('fieldflow_stock_ai_step',start.dataset.batch,start.dataset.nonce);paint(data);if(data.error)throw new Error(data.error);}if(data.done){if(copy)copy.textContent='Validação IA concluída. A página vai atualizar as sugestões.';setTimeout(()=>window.location.reload(),700);}else if(copy){copy.textContent='Validação pausada. Podes continuar quando quiseres.';}}catch(error){if(box)box.classList.add('is-active');if(copy)copy.textContent=error.message||'A validação foi interrompida.';}finally{running=false;start.disabled=false;start.textContent='Continuar validação com IA';if(stop)stop.style.display='none';}}
          if(start)start.addEventListener('click',run);if(stop)stop.addEventListener('click',()=>{stopped=true;stop.disabled=true;if(copy)copy.textContent='A terminar o bloco atual...';setTimeout(()=>{stop.disabled=false;},1200);});

          const bulkForm=document.getElementById('ff-stock-bulk-form'),payload=document.getElementById('ff-stock-bulk-payload'),submit=document.getElementById('ff-stock-bulk-submit'),checks=Array.from(document.querySelectorAll('.ff-stock-bulk-check'));
          function selectedRows(){return checks.filter(c=>c.checked).map(c=>c.closest('.ff-stock-map')).filter(Boolean);}
          function updateBulk(){checks.forEach(c=>{const row=c.closest('.ff-stock-map');if(row)row.classList.toggle('is-selected',c.checked);});if(submit)submit.textContent='Associar selecionadas ('+selectedRows().length+')';}
          checks.forEach(c=>c.addEventListener('change',updateBulk));
          const all=document.getElementById('ff-stock-select-all');if(all)all.addEventListener('click',()=>{const visible=checks.filter(c=>{const row=c.closest('.ff-stock-map');return row&&!row.hidden;});const shouldCheck=visible.some(c=>!c.checked);visible.forEach(c=>c.checked=shouldCheck);all.textContent=shouldCheck?'Limpar página':'Selecionar página';updateBulk();});
          const suggested=document.getElementById('ff-stock-select-suggested');if(suggested)suggested.addEventListener('click',()=>{const threshold=Number((document.getElementById('ff-stock-select-threshold')||{}).value||90);checks.forEach(c=>{const row=c.closest('.ff-stock-map'),select=row&&row.querySelector('.ff-stock-location-select');c.checked=!!(row&&select&&select.value&&Number(row.dataset.confidence||0)>=threshold);});updateBulk();});
          if(bulkForm)bulkForm.addEventListener('submit',event=>{const rows=selectedRows();if(!rows.length){event.preventDefault();alert('Seleciona pelo menos uma loja.');return;}const data=[];for(const row of rows){const select=row.querySelector('.ff-stock-location-select');if(!select||!select.value){event.preventDefault();alert('Seleciona um PDV para todas as lojas marcadas.');return;}data.push({store_ref:row.dataset.storeRef||'',store_name:row.dataset.storeName||'',location_id:Number(select.value)});}payload.value=JSON.stringify(data);if(!confirm('Associar '+data.length+' lojas aos PDVs selecionados?'))event.preventDefault();});

          const maps=Array.from(document.querySelectorAll('.ff-stock-map')),search=document.getElementById('ff-stock-review-search'),confidenceFilter=document.getElementById('ff-stock-review-confidence'),pageSize=document.getElementById('ff-stock-review-page-size'),summary=document.getElementById('ff-stock-review-summary'),pageCopy=document.getElementById('ff-stock-page-copy'),prev=document.getElementById('ff-stock-page-prev'),next=document.getElementById('ff-stock-page-next');let page=1;
          function normalise(value){return String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();}
          function filteredMaps(){const query=normalise(search&&search.value),mode=confidenceFilter?confidenceFilter.value:'all';return maps.filter(row=>{const score=Number(row.dataset.confidence||0);let confidenceOk=mode==='all'||(mode==='high'&&score>=90)||(mode==='medium'&&score>=75&&score<90)||(mode==='low'&&score>0&&score<75)||(mode==='none'&&score===0);return confidenceOk&&(!query||normalise(row.dataset.search).includes(query));});}
          function renderPage(){const filtered=filteredMaps(),size=Math.max(1,Number(pageSize&&pageSize.value||50)),pages=Math.max(1,Math.ceil(filtered.length/size));page=Math.min(Math.max(1,page),pages);const visible=new Set(filtered.slice((page-1)*size,page*size));maps.forEach(row=>row.hidden=!visible.has(row));if(summary)summary.textContent=filtered.length+' de '+maps.length+' lojas';if(pageCopy)pageCopy.textContent=filtered.length?'Página '+page+' de '+pages+', '+visible.size+' lojas visíveis':'Sem resultados para estes filtros';if(prev)prev.disabled=page<=1;if(next)next.disabled=page>=pages;if(all)all.textContent='Selecionar página';updateBulk();}
          [search,confidenceFilter,pageSize].filter(Boolean).forEach(el=>el.addEventListener(el===search?'input':'change',()=>{page=1;renderPage();}));if(prev)prev.addEventListener('click',()=>{page--;renderPage();window.scrollTo({top:document.querySelector('.ff-stock-review-tools').offsetTop-50,behavior:'smooth'});});if(next)next.addEventListener('click',()=>{page++;renderPage();window.scrollTo({top:document.querySelector('.ff-stock-review-tools').offsetTop-50,behavior:'smooth'});});
          renderPage();
        })();
        </script>
        <?php
    }

    public static function handleResetMappings(): void {
        self::assertManager();
        $batchId=absint($_POST['batch_id']??0);
        check_admin_referer('fieldflow_stock_reset_mappings_'.$batchId);
        $count=StockService::resetBatchMappings($batchId);
        try { StockStoreMatcher::generate($batchId,false); } catch (\Throwable $ignored) {}
        self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>sprintf('%d associações foram repostas. O lote está pronto para nova validação por insígnia.', $count)]);
    }

    public static function handleImport(): void {
        self::assertManager();
        check_admin_referer('fieldflow_stock_import');
        try {
            $file = $_FILES['stock_file'] ?? [];
            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new \RuntimeException('Seleciona um ficheiro XLSX ou CSV válido.');
            if (!empty($file['error'])) throw new \RuntimeException('O upload do ficheiro falhou.');
            if ((int)($file['size'] ?? 0) > (int)apply_filters('fieldflow_stock_upload_max_bytes', 50 * 1024 * 1024)) throw new \RuntimeException('O ficheiro excede o limite de 50 MB.');
            $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx','csv'], true)) throw new \RuntimeException('Formato inválido. Usa XLSX ou CSV.');
            $result = StockImporter::import($file['tmp_name'], [
                'client_id'=>absint($_POST['client_id'] ?? 0),
                'project_id'=>absint($_POST['project_id'] ?? 0),
                'insignia'=>sanitize_text_field((string)($_POST['insignia'] ?? '')),
                'snapshot_date'=>sanitize_text_field((string)($_POST['snapshot_date'] ?? '')),
                'source_filename'=>sanitize_file_name((string)($file['name'] ?? 'stock.xlsx')),
                'publish'=>!empty($_POST['publish_now']),
            ]);
            $batchId = (int)$result['batch_id'];
            StockService::repairBatchScope($batchId);
            $autoApplied = 0;
            try {
                StockStoreMatcher::generate($batchId, false);
                $automatic = StockStoreMatcher::apply($batchId, 97);
                $autoApplied = (int)($automatic['applied'] ?? 0);
                if ($autoApplied > 0) StockStoreMatcher::generate($batchId, false);
            } catch (\Throwable $ignored) {}
            $publishError = trim((string)($result['publish_error'] ?? ''));
            if ($publishError !== '') {
                self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>'O ficheiro foi importado e preservado como Rascunho, mas a publicação do novo snapshot falhou: '.$publishError.' O stock anterior continua ativo e não foi alterado.']);
            }
            $message = 'Stock importado com sucesso. A leitura inteligente do Excel validou folha, cabeçalhos e totais. Este lote é tratado como snapshot completo: quando publicado substitui o stock ativo anterior da mesma insígnia, nunca o soma.';
            $replacedCount = count((array)($result['replaced_batch_ids'] ?? []));
            if (!empty($_POST['publish_now'])) $message .= $replacedCount > 0 ? ' O snapshot anterior foi arquivado automaticamente e permanece no histórico.' : ' Este é o snapshot ativo deste âmbito.';
            if ($autoApplied > 0) $message .= ' ' . $autoApplied . ' loja(s) de confiança muito alta foram associadas automaticamente.';
            $message .= ' Os casos ambíguos continuam disponíveis para validação.';
            self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>$message]);
        } catch (\Throwable $e) {
            self::redirect(['ff_stock_error'=>$e->getMessage()]);
        }
    }

    public static function handleSuggest(): void {
        self::assertManager();
        $batchId = absint($_POST['batch_id'] ?? 0);
        check_admin_referer('fieldflow_stock_suggest_'.$batchId);
        try {
            $result = StockStoreMatcher::generate($batchId, !empty($_POST['use_ai']));
            $message = (string)($result['message'] ?? 'Sugestões atualizadas.');
            if (!empty($result['ai_error'])) $message .= ' A IA não respondeu, por isso foram mantidas as sugestões seguras do motor local.';
            self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>$message]);
        } catch (\Throwable $e) {
            self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>$e->getMessage()]);
        }
    }

    public static function handleApplySuggestions(): void {
        self::assertManager();
        $batchId = absint($_POST['batch_id'] ?? 0);
        check_admin_referer('fieldflow_stock_apply_suggestions_'.$batchId);
        try {
            $result = StockStoreMatcher::apply($batchId, absint($_POST['threshold'] ?? 90));
            self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>sprintf('%d lojas associadas automaticamente. %d sugestões ficaram para validação manual.', (int)$result['applied'], (int)$result['skipped'])]);
        } catch (\Throwable $e) {
            self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>$e->getMessage()]);
        }
    }

    public static function handleMap(): void {
        self::assertManager();
        $batchId = absint($_POST['batch_id'] ?? 0);
        check_admin_referer('fieldflow_stock_map_'.$batchId);
        $batch = StockService::getBatch($batchId);
        if (!$batch) self::redirect(['ff_stock_error'=>'Lote não encontrado.']);
        $locationId = absint($_POST['location_id'] ?? 0);
        if (!$locationId) self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>'Seleciona uma loja FieldFlow.']);
        $allowedLocations = array_map('intval', array_column(StockService::locationIndex((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia'])['rows'], 'id'));
        if (!in_array($locationId, $allowedLocations, true)) self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>'A loja selecionada não pertence ao âmbito deste lote.']);
        try {
            StockService::saveMapping((int)$batch['client_id'],(int)$batch['project_id'],(string)$batch['insignia'],sanitize_text_field((string)($_POST['store_ref']??'')),sanitize_text_field((string)($_POST['store_name']??'')),$locationId,100,'manual');
        } catch (\Throwable $e) {
            // 9DL: a PDV with a conflicting retailer identity is an actionable mapping error, not a fatal 500.
            self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>$e->getMessage()]);
        }
        self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>'Loja associada. O histórico e o frontoffice foram atualizados.']);
    }

    public static function handleAiPrepare(): void {
        self::assertManager();
        $batchId = absint($_POST['batch_id'] ?? 0);
        check_ajax_referer('fieldflow_stock_ai_'.$batchId, 'nonce');
        try {
            wp_send_json_success(StockStoreMatcher::prepareAI($batchId, false));
        } catch (\Throwable $e) {
            wp_send_json_error(['message'=>$e->getMessage()], 500);
        }
    }

    public static function handleAiStep(): void {
        self::assertManager();
        $batchId = absint($_POST['batch_id'] ?? 0);
        check_ajax_referer('fieldflow_stock_ai_'.$batchId, 'nonce');
        try {
            wp_send_json_success(StockStoreMatcher::reviewNextAIChunk($batchId));
        } catch (\Throwable $e) {
            wp_send_json_error(['message'=>$e->getMessage()], 500);
        }
    }

    public static function handleBulkMap(): void {
        self::assertManager();
        $batchId = absint($_POST['batch_id'] ?? 0);
        check_admin_referer('fieldflow_stock_bulk_map_'.$batchId);
        $batch = StockService::getBatch($batchId);
        if (!$batch) self::redirect(['ff_stock_error'=>'Lote não encontrado.']);
        $rows = json_decode((string)wp_unslash($_POST['bulk_payload'] ?? ''), true);
        if (!is_array($rows) || !$rows) self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>'Não foram recebidas lojas para associação múltipla.']);
        $rows = array_slice($rows, 0, 300);
        $locationIndex = StockService::locationIndex((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia']);
        $allowedLocations = array_fill_keys(array_map('intval', array_keys($locationIndex['rows'])), true);
        global $wpdb;
        $used = array_fill_keys(array_map('intval', $wpdb->get_col($wpdb->prepare('SELECT DISTINCT location_id FROM '.StockService::itemTable().' WHERE batch_id=%d AND location_id>0', $batchId)) ?: []), true);
        $applied = 0; $skipped = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) { $skipped++; continue; }
            $locationId = absint($row['location_id'] ?? 0);
            $storeRef = sanitize_text_field((string)($row['store_ref'] ?? ''));
            $storeName = sanitize_text_field((string)($row['store_name'] ?? ''));
            if (!$locationId || empty($allowedLocations[$locationId]) || (!$storeRef && !$storeName) || !empty($used[$locationId])) { $skipped++; continue; }
            StockService::saveMapping((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia'], $storeRef, $storeName, $locationId, 100, 'manual_bulk', false);
            $used[$locationId] = true;
            $applied++;
        }
        if ($applied) StockService::refreshBatchStatsForScope((int)$batch['client_id'], (int)$batch['project_id'], (string)$batch['insignia']);
        self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>sprintf('%d lojas associadas em massa. %d foram ignoradas por falta de seleção válida ou por duplicação do PDV.', $applied, $skipped)]);
    }

    public static function handleStatus(): void {
        self::assertManager();
        global $wpdb;
        $batchId=absint($_POST['batch_id']??0);check_admin_referer('fieldflow_stock_status_'.$batchId);
        $status=sanitize_key((string)($_POST['status']??''));if(!in_array($status,['draft','published','archived'],true))$status='draft';
        if($status==='published'){
            try {
                $result=StockService::publishSnapshot($batchId,get_current_user_id(),'status_change');
                $replaced=(int)($result['replaced_count']??0);
                $message='Novo snapshot de stock publicado. Este lote substitui integralmente o stock ativo anterior desta insígnia; os valores não são somados.';
                if($replaced>0)$message.=' '.$replaced.' snapshot(s) anterior(es) foram arquivados e permanecem no histórico.';
                self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>$message]);
            } catch (\Throwable $e) {
                self::redirect(['batch_id'=>$batchId,'ff_stock_error'=>$e->getMessage()]);
            }
        }
        $wpdb->update(StockService::batchTable(),['status'=>$status,'updated_at'=>current_time('mysql')],['id'=>$batchId]);
        self::redirect(['batch_id'=>$batchId,'ff_stock_notice'=>'Estado do lote atualizado.']);
    }

    public static function handleDelete(): void {
        self::assertManager();
        $batchId=absint($_POST['batch_id']??0);check_admin_referer('fieldflow_stock_delete_'.$batchId);StockService::deleteBatch($batchId);
        self::redirect(['ff_stock_notice'=>'Lote de stock eliminado.']);
    }

    public static function handleBulkDelete(): void {
        self::assertManager();
        check_admin_referer('fieldflow_stock_bulk_delete');
        $ids=[];
        $single=absint($_POST['single_batch_id']??0);
        if($single){$ids[]=$single;}else{foreach((array)($_POST['batch_ids']??[]) as $id){$id=absint($id);if($id)$ids[]=$id;}}
        $ids=array_slice(array_values(array_unique($ids)),0,200);
        if(!$ids)self::redirect(['ff_stock_error'=>'Seleciona pelo menos um lote para eliminar.']);
        $deleted=0;
        foreach($ids as $id){if(StockService::getBatch($id)){StockService::deleteBatch($id);$deleted++;}}
        self::redirect(['ff_stock_notice'=>sprintf('%d lote(s) de stock eliminado(s). As associações de lojas aprendidas foram preservadas.', $deleted)]);
    }

    public static function handleTemplate(): void {
        self::assertManager();check_admin_referer('fieldflow_stock_template');
        nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="fieldflow-stock-template.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Centro','Descr. Centro','Localidade','Artigo','EAN','Descrição Artigo','UM','FC','Stock Total']);fputcsv($out,['301','POMBAL - SHOPPING','POMBAL','8813','5000329002209','GIN BEEFEATER 70CL','UN','1','15']);fclose($out);exit;
    }

    private static function assertManager(): void { if(!current_user_can('routespro_manage'))wp_die('Sem permissões.'); }
    private static function redirect(array $args): void { wp_safe_redirect(add_query_arg(array_merge(['page'=>'routespro-stocks'],$args),admin_url('admin.php')));exit; }
}
