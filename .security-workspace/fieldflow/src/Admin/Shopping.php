<?php
namespace RoutesPro\Admin;

use RoutesPro\Shopping\ShoppingService;
use RoutesPro\Shopping\ShoppingResultsService;
use RoutesPro\Shopping\ShoppingExportService;
use RoutesPro\Support\AdminPage;

if (!defined('ABSPATH')) exit;

final class Shopping {
    private const NOTICE_PREFIX = 'fieldflow_shopping_notice_';

    public static function register_hooks(): void {
        add_action('admin_post_fieldflow_shopping_import', [self::class, 'handleImport']);
        add_action('wp_ajax_fieldflow_shopping_import', [self::class, 'handleImportAjax']);
        add_action('admin_post_fieldflow_shopping_save_batch', [self::class, 'handleSaveBatch']);
        add_action('admin_post_fieldflow_shopping_save_template', [self::class, 'handleSaveTemplate']);
        add_action('admin_post_fieldflow_shopping_delete_batch', [self::class, 'handleDeleteBatch']);
        add_action('admin_post_fieldflow_shopping_export', [self::class, 'handleExport']);
        add_action('admin_post_fieldflow_shopping_resend_submission', [self::class, 'handleResendSubmission']);
    }

    public static function render(): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        ShoppingService::ensureSchema();
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        $clients = $wpdb->get_results("SELECT id,name FROM {$px}clients ORDER BY name ASC", ARRAY_A) ?: [];
        $projects = $wpdb->get_results("SELECT id,client_id,name FROM {$px}projects WHERE status<>'archived' ORDER BY name ASC", ARRAY_A) ?: [];
        $batches = ShoppingService::listBatches();
        $template = ShoppingService::getTemplate();
        $batchId = absint($_GET['batch_id'] ?? 0);
        $batch = $batchId ? ShoppingService::getBatch($batchId) : [];
        $resultFilters = [
            'client_id' => absint($_GET['results_client_id'] ?? 0),
            'project_id' => absint($_GET['results_project_id'] ?? 0),
            'from' => sanitize_text_field((string)($_GET['results_from'] ?? wp_date('Y-m-01'))),
            'to' => sanitize_text_field((string)($_GET['results_to'] ?? wp_date('Y-m-d'))),
            'q' => sanitize_text_field((string)($_GET['results_q'] ?? '')),
            'page' => max(1, absint($_GET['results_page'] ?? 1)),
            'per_page' => 20,
        ];
        $results = ShoppingResultsService::query($resultFilters, get_current_user_id());

        AdminPage::open('Shopping, Verificação de Preços', 'Importa o Excel por insígnia, disponibiliza-o a toda a equipa do projeto e deixa a loja concreta ser escolhida no front no momento da execução.');
        self::renderNotice();
        ?>
        <style>
        .ffs-admin-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;margin-top:18px}.ffs-card{background:#fff;border:1px solid #dfe5ec;border-radius:18px;padding:20px;box-shadow:0 10px 30px rgba(15,23,42,.05)}.ffs-card h2,.ffs-card h3{margin-top:0}.ffs-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.ffs-field.full{grid-column:1/-1}.ffs-field label{display:block;font-weight:700;margin-bottom:6px}.ffs-field input,.ffs-field select,.ffs-field textarea{width:100%;max-width:none}.ffs-muted{color:#64748b;line-height:1.5}.ffs-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:14px 0}.ffs-kpi{padding:12px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}.ffs-kpi span{display:block;color:#64748b;font-size:12px}.ffs-kpi strong{font-size:22px}.ffs-table-wrap{overflow:auto}.ffs-table{width:100%;border-collapse:collapse}.ffs-table th,.ffs-table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:middle}.ffs-table th{background:#f8fafc;position:sticky;top:0}.ffs-pill{display:inline-flex;padding:4px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:800}.ffs-pill.done{background:#dcfce7;color:#166534}.ffs-pill.warn{background:#ffedd5;color:#9a3412}.ffs-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.ffs-import-status{margin-top:12px;padding:12px 14px;border-radius:12px;font-weight:700;line-height:1.45}.ffs-import-status.working{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.ffs-import-status.success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}.ffs-import-status.error{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.ffs-danger{color:#b91c1c!important}.ffs-recipient-box{border:1px solid #dbe4ef;border-radius:14px;padding:14px;background:#f8fafc}.ffs-recipient-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:10px}.ffs-recipient-item{display:flex!important;gap:9px;align-items:flex-start;padding:10px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;font-weight:600!important;margin:0!important}.ffs-recipient-item input{width:auto!important;margin-top:2px}.ffs-recipient-item span{min-width:0}.ffs-recipient-item small{display:block;color:#64748b;font-weight:400;word-break:break-all}.ffs-resend{margin-top:9px}.ffs-resend summary{cursor:pointer;display:inline-flex;align-items:center}.ffs-resend-panel{margin-top:10px;padding:12px;border:1px solid #dbe4ef;border-radius:12px;background:#f8fafc;min-width:320px}.ffs-resend-panel .ffs-recipient-grid{grid-template-columns:1fr}.ffs-mail-meta{margin-top:5px;color:#64748b;font-size:11px;line-height:1.45}.ffs-token{display:inline-block;padding:3px 7px;border-radius:7px;background:#f1f5f9;margin:2px;font-family:ui-monospace,monospace;font-size:11px}@media(max-width:980px){.ffs-admin-grid,.ffs-fields,.ffs-recipient-grid{grid-template-columns:1fr}.ffs-field.full{grid-column:auto}.ffs-resend-panel{min-width:0}}
        </style>
        <div class="ffs-admin-grid">
          <section class="ffs-card">
            <h2>1. Importar novo ficheiro</h2>
            <p class="ffs-muted">O FieldFlow deteta automaticamente a semana, os artigos e cada par de colunas PVP Regular e PVP Promo. Os cabeçalhos são normalizados por insígnia, e cada insígnia origina um único formulário reutilizável nas lojas dessa cadeia.</p>
            <form id="ffs-shopping-import-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
              <input type="hidden" name="action" value="fieldflow_shopping_import">
              <?php wp_nonce_field('fieldflow_shopping_import'); ?>
              <div class="ffs-fields">
                <div class="ffs-field"><label>Cliente</label><select name="client_id" required><option value="">Selecionar</option><?php foreach($clients as $client): ?><option value="<?php echo (int)$client['id']; ?>"><?php echo esc_html($client['name']); ?></option><?php endforeach; ?></select></div>
                <div class="ffs-field"><label>Projeto ou campanha</label><select name="project_id" required><option value="">Selecionar</option><?php foreach($projects as $project): ?><option value="<?php echo (int)$project['id']; ?>" data-client-id="<?php echo (int)$project['client_id']; ?>"><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select></div>
                <div class="ffs-field full"><label>Título opcional</label><input type="text" name="title" placeholder="Exemplo, Shopping, Semana 22"></div>
                <div class="ffs-field full"><label>Ficheiro Excel</label><input type="file" name="shopping_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required><p class="description">Formato suportado: XLSX. Mantém a estrutura com Artigos, PVP Regular e PVP Promo.</p></div>
              </div>
              <p><button type="submit" class="button button-primary button-hero" data-ffs-import-button>Importar e criar formulários</button></p>
              <div class="ffs-import-status" data-ffs-import-status hidden aria-live="polite"></div>
            </form>
          </section>

          <section class="ffs-card">
            <h2>Template de email por defeito</h2>
            <p class="ffs-muted">É copiado para cada novo lote. Depois podes personalizar o email de um lote específico na área de edição. O envio final usa um layout newsletter otimizado para Outlook, com HTML por tabelas e estilos inline.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="fieldflow_shopping_save_template">
              <?php wp_nonce_field('fieldflow_shopping_save_template'); ?>
              <div class="ffs-fields">
                <div class="ffs-field full"><label>Assunto</label><input type="text" name="subject" value="<?php echo esc_attr($template['subject']); ?>" required></div>
                <div class="ffs-field full"><label>Cor principal</label><input type="text" name="brand_primary" value="<?php echo esc_attr($template['brand_primary']); ?>" class="regular-text"></div>
                <div class="ffs-field full"><label>Corpo do email</label><textarea name="body" rows="10" required><?php echo esc_textarea($template['body']); ?></textarea></div>
                <div class="ffs-field"><label>Emails adicionais</label><input type="text" name="extra_emails" value="<?php echo esc_attr($template['extra_emails']); ?>" placeholder="email1@empresa.pt, email2@empresa.pt"></div>
                <div class="ffs-field"><label>Rodapé</label><input type="text" name="footer_text" value="<?php echo esc_attr($template['footer_text']); ?>"></div>
                <div class="ffs-field full"><label><input type="checkbox" name="notify_client" value="1" <?php checked(!empty($template['notify_client'])); ?>> Pré-selecionar o email principal do cliente nos novos lotes</label></div>
                <div class="ffs-field full"><label><input type="checkbox" name="notify_client_users" value="1" <?php checked(!empty($template['notify_client_users'])); ?>> Pré-selecionar também os utilizadores associados ao cliente nos novos lotes</label></div>
                <div class="ffs-field full"><label><input type="checkbox" name="attach_xlsx" value="1" <?php checked(!empty($template['attach_xlsx'])); ?>> Anexar Excel da submissão aos emails Shopping</label></div>
                <div class="ffs-field full"><label><input type="checkbox" name="attach_pdf" value="1" <?php checked(!empty($template['attach_pdf'])); ?>> Anexar PDF da submissão aos emails Shopping</label><p class="description">Os anexos são gerados por loja e submissão, e são incluídos também nos reenvios.</p></div>
              </div>
              <p class="ffs-muted">Tokens disponíveis:<br><?php foreach(['{client_name}','{project_name}','{insignia}','{store_name}','{merchandiser_name}','{merchandiser_email}','{week}','{year}','{submitted_at}','{prices_table}','{notes_block}','{notes}'] as $token): ?><span class="ffs-token"><?php echo esc_html($token); ?></span><?php endforeach; ?></p>
              <p><button class="button button-primary">Guardar template</button></p>
            </form>
          </section>
        </div>

        <section class="ffs-card" style="margin-top:18px">
          <h2>Lotes Shopping</h2>
          <?php if (!$batches): ?>
            <p class="ffs-muted">Ainda não existem importações Shopping.</p>
          <?php else: ?>
          <div class="ffs-table-wrap"><table class="ffs-table"><thead><tr><th>Lote</th><th>Cliente e projeto</th><th>Período</th><th>Progresso</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
          <?php foreach($batches as $row): $total=(int)$row['store_count'];$submittedStores=(int)($row['submitted_store_count'] ?? 0); ?>
            <tr>
              <td><strong><?php echo esc_html($row['title']); ?></strong><br><small><?php echo esc_html($row['source_file_name']); ?></small></td>
              <td><?php echo esc_html($row['client_name']); ?><br><small><?php echo esc_html($row['project_name']); ?></small></td>
              <td><?php echo $row['week_no'] ? 'Semana '.(int)$row['week_no'] : 'Sem semana'; ?><?php echo $row['year_no'] ? ' / '.(int)$row['year_no'] : ''; ?></td>
              <td><strong><?php echo $submittedStores; ?></strong> loja(s) submetida(s)<br><small><?php echo $total; ?> insígnia(s) no lote</small></td>
              <td><span class="ffs-pill <?php echo $row['status']==='active'?'':'warn'; ?>"><?php echo esc_html($row['status']); ?></span></td>
              <td><div class="ffs-actions"><a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'routespro-shopping','batch_id'=>(int)$row['id']], admin_url('admin.php'))); ?>">Editar lote e destinatários</a><a class="button ffs-danger" onclick="return confirm('Eliminar este lote e todas as submissões?');" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fieldflow_shopping_delete_batch&batch_id='.(int)$row['id']), 'fieldflow_shopping_delete_batch')); ?>">Eliminar</a></div></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <?php endif; ?>
        </section>

        <section class="ffs-card" style="margin-top:18px" id="ffs-shopping-results">
          <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
            <div><h2 style="margin-bottom:5px">Submissões e resultados</h2><p class="ffs-muted" style="margin-top:0">As respostas ficam guardadas na base de dados do FieldFlow e podem ser consultadas ou exportadas aqui.</p></div>
            <div class="ffs-actions">
              <a class="button" href="<?php echo esc_url(ShoppingResultsService::exportUrl('csv', $resultFilters)); ?>">CSV</a>
              <a class="button button-primary" href="<?php echo esc_url(ShoppingResultsService::exportUrl('xlsx', $resultFilters)); ?>">Excel</a>
              <a class="button" href="<?php echo esc_url(ShoppingResultsService::exportUrl('pdf', $resultFilters)); ?>">PDF</a>
            </div>
          </div>
          <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="ffs-fields" style="margin:14px 0">
            <input type="hidden" name="page" value="routespro-shopping">
            <div class="ffs-field"><label>Cliente</label><select name="results_client_id"><option value="0">Todos</option><?php foreach($clients as $client): ?><option value="<?php echo (int)$client['id']; ?>" <?php selected((int)$resultFilters['client_id'],(int)$client['id']); ?>><?php echo esc_html($client['name']); ?></option><?php endforeach; ?></select></div>
            <div class="ffs-field"><label>Projeto</label><select name="results_project_id"><option value="0">Todos</option><?php foreach($projects as $project): ?><option value="<?php echo (int)$project['id']; ?>" <?php selected((int)$resultFilters['project_id'],(int)$project['id']); ?>><?php echo esc_html($project['name']); ?></option><?php endforeach; ?></select></div>
            <div class="ffs-field"><label>De</label><input type="date" name="results_from" value="<?php echo esc_attr($resultFilters['from']); ?>"></div>
            <div class="ffs-field"><label>Até</label><input type="date" name="results_to" value="<?php echo esc_attr($resultFilters['to']); ?>"></div>
            <div class="ffs-field full"><label>Pesquisa</label><div class="ffs-actions"><input type="search" name="results_q" value="<?php echo esc_attr($resultFilters['q']); ?>" placeholder="Loja, lote, projeto ou merchandiser"><button class="button button-primary">Aplicar filtros</button></div></div>
          </form>
          <div class="ffs-kpis"><div class="ffs-kpi"><span>Submissões</span><strong><?php echo (int)$results['summary']['submissions']; ?></strong></div><div class="ffs-kpi"><span>Lojas</span><strong><?php echo (int)$results['summary']['stores']; ?></strong></div><div class="ffs-kpi"><span>Merchandisers</span><strong><?php echo (int)$results['summary']['merchandisers']; ?></strong></div></div>
          <div class="ffs-table-wrap"><table class="ffs-table"><thead><tr><th>Data</th><th>Loja</th><th>Projeto</th><th>Merchandiser</th><th>Preenchimento</th><th>Email e reenvio</th></tr></thead><tbody>
          <?php if(empty($results['items'])): ?><tr><td colspan="6" class="ffs-muted">Ainda não existem submissões para os filtros selecionados.</td></tr><?php endif; ?>
          <?php foreach((array)$results['items'] as $result): ?>
            <tr>
              <td><?php echo esc_html($result['submitted_at']); ?></td>
              <td><strong><?php echo esc_html($result['location_name']); ?></strong><br><small><?php echo esc_html((string)($result['insignia'] ?? '')); ?><?php echo !empty($result['city']) ? ' · ' . esc_html($result['city']) : ''; ?></small></td>
              <td><?php echo esc_html($result['project_name']); ?><br><small>Semana <?php echo (int)$result['week_no']; ?> / <?php echo (int)$result['year_no']; ?></small></td>
              <td><?php echo esc_html($result['merchandiser_name']); ?><br><small><?php echo esc_html($result['merchandiser_email']); ?></small></td>
              <td><?php echo (int)$result['filled_fields']; ?> campos</td>
              <td><?php self::renderResultMailCell($result); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
        </section>
        <?php
        if ($batch) self::renderBatchEditor($batch);
        ?>
        <script>
        (function(){
          const client=document.querySelector('[name="client_id"]'),project=document.querySelector('[name="project_id"]');
          if(client&&project){
            const sync=()=>{const id=client.value;Array.from(project.options).forEach((option,index)=>{if(index===0)return;option.hidden=!!id&&option.dataset.clientId!==id;});if(project.selectedOptions[0]&&project.selectedOptions[0].hidden)project.value='';};
            client.addEventListener('change',sync);sync();
          }

          const form=document.getElementById('ffs-shopping-import-form');
          if(!form||!window.fetch||!window.FormData)return;
          const button=form.querySelector('[data-ffs-import-button]');
          const status=form.querySelector('[data-ffs-import-status]');
          const setStatus=(message,type)=>{
            if(!status)return;
            status.hidden=false;
            status.className='ffs-import-status '+(type||'working');
            status.textContent=message;
          };
          form.addEventListener('submit',async event=>{
            event.preventDefault();
            if(button&&button.disabled)return;
            const file=form.querySelector('input[type="file"]');
            if(!file||!file.files||!file.files.length){setStatus('Seleciona primeiro o ficheiro XLSX.','error');return;}
            const data=new FormData(form);
            data.set('action','fieldflow_shopping_import');
            if(button){button.disabled=true;button.dataset.originalText=button.textContent;button.textContent='A importar...';}
            setStatus('A validar o Excel e a criar os formulários por insígnia. Não feches esta página.','working');
            try{
              const response=await fetch(form.dataset.ajaxUrl||window.ajaxurl,{method:'POST',body:data,credentials:'same-origin'});
              let payload=null;
              try{payload=await response.json();}catch(parseError){throw new Error('O servidor devolveu uma resposta inválida. Consulta o log de erros do WordPress se o problema persistir.');}
              if(!response.ok||!payload||payload.success!==true){
                const message=payload&&payload.data&&payload.data.message?payload.data.message:'Não foi possível importar o ficheiro.';
                throw new Error(message);
              }
              setStatus(payload.data.message||'Importação concluída. A abrir as insígnias...','success');
              window.location.assign(payload.data.redirect);
            }catch(error){
              setStatus(error&&error.message?error.message:'Falha de comunicação durante a importação.','error');
              if(button){button.disabled=false;button.textContent=button.dataset.originalText||'Importar e criar formulários';}
            }
          });
        })();
        </script>
        <?php
        AdminPage::close();
    }

    private static function renderBatchEditor(array $batch): void {
        ShoppingService::reconcileBatchAssignments((int)$batch['id']);
        $assignments = ShoppingService::assignmentsForBatch((int)$batch['id']);
        $users = ShoppingService::projectMerchandisers((int)$batch['project_id']);
        $recipientDirectory = ShoppingService::recipientDirectory((int)$batch['client_id']);
        $selectedRecipientUserIds = array_fill_keys(ShoppingService::selectedRecipientUserIds($batch), true);
        ?>
        <section class="ffs-card" style="margin-top:18px" id="ffs-batch-editor">
          <h2>Editar lote #<?php echo (int)$batch['id']; ?></h2>
          <div class="ffs-kpis"><div class="ffs-kpi"><span>Insígnias</span><strong><?php echo count($assignments); ?></strong></div><div class="ffs-kpi"><span>Artigos</span><strong><?php echo count(json_decode((string)$batch['products_json'], true) ?: []); ?></strong></div><div class="ffs-kpi"><span>Folha</span><strong style="font-size:14px"><?php echo esc_html($batch['source_sheet']); ?></strong></div></div>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="fieldflow_shopping_save_batch"><input type="hidden" name="batch_id" value="<?php echo (int)$batch['id']; ?>">
            <?php wp_nonce_field('fieldflow_shopping_save_batch_' . (int)$batch['id']); ?>
            <div class="ffs-fields">
              <div class="ffs-field full"><label>Título</label><input type="text" name="title" value="<?php echo esc_attr($batch['title']); ?>" required></div>
              <div class="ffs-field"><label>Semana</label><input type="number" min="0" max="53" name="week_no" value="<?php echo (int)$batch['week_no']; ?>"></div>
              <div class="ffs-field"><label>Ano</label><input type="number" min="0" max="9999" name="year_no" value="<?php echo (int)$batch['year_no']; ?>"></div>
              <div class="ffs-field"><label>Estado</label><select name="status"><option value="active" <?php selected($batch['status'],'active'); ?>>Ativo</option><option value="draft" <?php selected($batch['status'],'draft'); ?>>Rascunho</option><option value="archived" <?php selected($batch['status'],'archived'); ?>>Arquivado</option></select></div>
              <div class="ffs-field full">
                <label>Destinatários automáticos deste lote</label>
                <div class="ffs-recipient-box">
                  <p class="ffs-muted" style="margin-top:0">Cada nova submissão desta importação será enviada apenas para os destinatários assinalados abaixo. Os utilizadores listados vêm da associação ao cliente no Intelligence Center.</p>
                  <label class="ffs-recipient-item">
                    <input type="checkbox" name="notify_client" value="1" <?php checked(!empty($batch['notify_client'])); ?> <?php disabled(empty($recipientDirectory['client']['email'])); ?>>
                    <span><strong>Email principal do cliente</strong><small><?php echo esc_html((string)($recipientDirectory['client']['email'] ?? 'Não configurado no cliente')); ?></small></span>
                  </label>
                  <?php if (!empty($recipientDirectory['users'])): ?>
                    <div class="ffs-actions" style="margin-top:10px"><button type="button" class="button" data-ffs-select-client-users>Selecionar todos os utilizadores cliente</button><button type="button" class="button" data-ffs-clear-client-users>Limpar seleção</button></div>
                    <div class="ffs-recipient-grid">
                      <?php foreach((array)$recipientDirectory['users'] as $recipientUser): ?>
                        <label class="ffs-recipient-item"><input type="checkbox" name="recipient_user_ids[]" value="<?php echo (int)$recipientUser['id']; ?>" <?php checked(isset($selectedRecipientUserIds[(int)$recipientUser['id']])); ?>><span><?php echo esc_html($recipientUser['name']); ?><small><?php echo esc_html($recipientUser['login'] . ' · ' . $recipientUser['email']); ?></small></span></label>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <p class="ffs-muted" style="margin-bottom:0">Não existem utilizadores com email associados diretamente a este cliente. Se esperavas ver uma conta cliente específica, confirma primeiro a associação dessa conta ao cliente.</p>
                  <?php endif; ?>
                </div>
              </div>
              <div class="ffs-field full"><label>Emails adicionais</label><input type="text" name="extra_emails" value="<?php echo esc_attr($batch['extra_emails']); ?>" placeholder="email1@empresa.pt, email2@empresa.pt"><p class="description">Útil para contactos externos que não têm conta no FieldFlow.</p></div>
              <div class="ffs-field"><label><input type="checkbox" name="attach_xlsx" value="1" <?php checked(!empty($batch['attach_xlsx'])); ?>> Anexar Excel</label><p class="description">Resumo e detalhe de preços da loja.</p></div>
              <div class="ffs-field"><label><input type="checkbox" name="attach_pdf" value="1" <?php checked(!empty($batch['attach_pdf'])); ?>> Anexar PDF</label><p class="description">Relatório compacto da submissão.</p></div>
              <div class="ffs-field full"><label>Assunto do email</label><input type="text" name="email_subject" value="<?php echo esc_attr($batch['email_subject']); ?>" required></div>
              <div class="ffs-field full"><label>Corpo do email</label><textarea name="email_body" rows="10" required><?php echo esc_textarea($batch['email_body']); ?></textarea><p class="description">Este conteúdo é inserido automaticamente num layout newsletter compatível com Outlook. Evita colar HTML externo com CSS complexo.</p></div>
            </div>
            <h3 style="margin-top:24px">Insígnias e disponibilidade operacional</h3>
            <p class="ffs-muted"><strong>Disponível para toda a equipa operacional do projeto.</strong> O Shopping é configurado por insígnia e nunca fica associado a um merchandiser ou a uma rota. No front, qualquer operacional com acesso ao projeto abre a insígnia e escolhe qualquer loja válida dessa cadeia antes de preencher preços.</p>
            <?php if (!$users): ?><div class="notice notice-warning inline"><p><strong>Este projeto não tem merchandisers associados.</strong> Abre Intelligence Center, Projetos, e adiciona os utilizadores em "Utilizadores com acesso ao projeto".</p></div><?php endif; ?>
            <div class="ffs-table-wrap"><table class="ffs-table"><thead><tr><th>Insígnia</th><th>Disponibilidade</th><th>Lojas já submetidas</th><th>Última loja</th><th>Atividade</th></tr></thead><tbody>
              <?php foreach($assignments as $assignment): ?>
              <tr>
                <td><strong><?php echo esc_html((string)($assignment['insignia_label'] ?? $assignment['source_store_name'])); ?></strong></td>
                <td><span class="ffs-pill done">Toda a equipa do projeto</span><br><small class="ffs-muted"><?php echo esc_html(count($users) . ' operacional(ais) com acesso'); ?></small></td>
                <td><strong><?php echo (int)($assignment['submitted_store_count'] ?? 0); ?></strong></td>
                <td><?php if (!empty($assignment['last_location_name'])): ?><strong><?php echo esc_html($assignment['last_location_name']); ?></strong><?php if (!empty($assignment['last_location_city'])): ?><br><small><?php echo esc_html($assignment['last_location_city']); ?></small><?php endif; ?><?php else: ?><span class="ffs-muted">Ainda sem submissões</span><?php endif; ?></td>
                <td><?php $submittedCount=(int)($assignment['submitted_store_count'] ?? 0); ?><span class="ffs-pill <?php echo $submittedCount>0?'done':''; ?>"><?php echo $submittedCount>0 ? esc_html($submittedCount . ' loja(s)') : 'Sem submissões'; ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody></table></div>
            <p style="margin-top:18px"><button class="button button-primary button-hero">Guardar lote e email</button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=routespro-shopping')); ?>">Fechar edição</a></p>
          </form>
          <script>
          (function(){
            const editor=document.getElementById('ffs-batch-editor');if(!editor)return;
            const recipientChecks=()=>Array.from(editor.querySelectorAll('input[name="recipient_user_ids[]"]'));
            const selectAll=editor.querySelector('[data-ffs-select-client-users]');
            const clearAll=editor.querySelector('[data-ffs-clear-client-users]');
            if(selectAll)selectAll.addEventListener('click',()=>recipientChecks().forEach(input=>input.checked=true));
            if(clearAll)clearAll.addEventListener('click',()=>recipientChecks().forEach(input=>input.checked=false));
          })();
          </script>
        </section>
        <?php
    }

    private static function renderResultMailCell(array $result): void {
        $status = (string)($result['notification_status'] ?? 'pending');
        $labels = [
            'sent'=>'Aceite para envio',
            'partial'=>'Parcial',
            'failed'=>'Falhou',
            'no_recipients'=>'Sem destinatários',
            'pending'=>'Pendente',
        ];
        $directory = ShoppingService::recipientDirectory((int)($result['client_id'] ?? 0));
        $recipients = array_values(array_filter(array_map('trim', explode(',', (string)($result['notification_recipients'] ?? '')))));
        // O histórico mostra quem recebeu anteriormente, mas o reenvio parte sempre
        // da configuração ATUAL do lote. Isto permite adicionar um cliente hoje e
        // reenviar uma submissão antiga sem ficar preso ao snapshot do primeiro envio.
        $selectedIds = array_fill_keys(ShoppingService::selectedRecipientUserIds($result), true);
        $notifyPrimary = !empty($result['notify_client']);
        $extraEmailSeed = (string)($result['extra_emails'] ?? '');
        $currentRecipients = [];
        if ($notifyPrimary && !empty($directory['client']['email'])) $currentRecipients[] = (string)$directory['client']['email'];
        foreach ((array)($directory['users'] ?? []) as $recipientUser) {
            if (isset($selectedIds[(int)($recipientUser['id'] ?? 0)]) && !empty($recipientUser['email'])) $currentRecipients[] = (string)$recipientUser['email'];
        }
        foreach (explode(',', $extraEmailSeed) as $extraEmail) {
            $extraEmail = sanitize_email(trim($extraEmail));
            if ($extraEmail !== '') $currentRecipients[] = $extraEmail;
        }
        $currentRecipients = array_values(array_unique(array_filter($currentRecipients)));
        $attempts = max(0, (int)($result['notification_attempts'] ?? 0));
        $lastSent = trim((string)($result['notification_last_sent_at'] ?? ''));
        ?>
        <span class="ffs-pill <?php echo $status === 'sent' ? 'done' : 'warn'; ?>"><?php echo esc_html($labels[$status] ?? $status); ?></span>
        <div class="ffs-mail-meta">
          <strong>Último envio:</strong> <?php echo $recipients ? esc_html(implode(', ', $recipients)) : 'Nenhum destinatário registado'; ?><br>
          <strong>Configuração atual:</strong> <?php echo $currentRecipients ? esc_html(implode(', ', $currentRecipients)) : 'Sem destinatários selecionados'; ?><br>
          <?php echo (int)$attempts; ?> tentativa(s)<?php echo $lastSent !== '' ? ' · último envio aceite ' . esc_html($lastSent) : ''; ?>
        </div>
        <details class="ffs-resend">
          <summary class="button">Reenviar email</summary>
          <div class="ffs-resend-panel">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="fieldflow_shopping_resend_submission">
              <input type="hidden" name="submission_id" value="<?php echo (int)$result['id']; ?>">
              <?php wp_nonce_field('fieldflow_shopping_resend_submission_' . (int)$result['id']); ?>
              <p style="margin-top:0"><strong>Escolher destinatários para este reenvio</strong></p>
              <p class="description">A seleção abaixo vem da configuração atual do lote, não do envio antigo. Podes alterar livremente quem recebe este reenvio.</p>
              <div class="ffs-actions" style="margin:8px 0"><button type="button" class="button" data-ffs-resend-select-all>Selecionar todos</button><button type="button" class="button" data-ffs-resend-clear>Limpar seleção</button></div>
              <label class="ffs-recipient-item">
                <input type="checkbox" name="notify_client" value="1" <?php checked($notifyPrimary); ?> <?php disabled(empty($directory['client']['email'])); ?>>
                <span>Email principal do cliente<small><?php echo esc_html((string)($directory['client']['email'] ?? 'Não configurado')); ?></small></span>
              </label>
              <?php if (!empty($directory['users'])): ?><div class="ffs-recipient-grid">
                <?php foreach((array)$directory['users'] as $recipientUser): ?>
                  <label class="ffs-recipient-item"><input type="checkbox" name="recipient_user_ids[]" value="<?php echo (int)$recipientUser['id']; ?>" <?php checked(isset($selectedIds[(int)$recipientUser['id']])); ?>><span><?php echo esc_html($recipientUser['name']); ?><small><?php echo esc_html($recipientUser['login'] . ' · ' . $recipientUser['email']); ?></small></span></label>
                <?php endforeach; ?>
              </div><?php endif; ?>
              <p><label><strong>Emails adicionais</strong></label><input type="text" name="extra_emails" value="<?php echo esc_attr($extraEmailSeed); ?>" placeholder="email@empresa.pt"></p>
              <p class="description">O reenvio fica registado no Mail Center e inclui os anexos ativos no lote, Excel e/ou PDF. “Aceite para envio” significa que o WordPress entregou a mensagem ao mailer, não confirma receção na caixa do destinatário.</p>
              <p style="margin-bottom:0"><button class="button button-primary">Reenviar agora</button></p>
            </form>
            <script>
            (function(){
              const panel=document.currentScript.closest('.ffs-resend-panel');if(!panel)return;
              const form=panel.querySelector('form');if(!form)return;
              const toggles=()=>Array.from(form.querySelectorAll('input[type="checkbox"]:not(:disabled)'));
              const all=panel.querySelector('[data-ffs-resend-select-all]'),clear=panel.querySelector('[data-ffs-resend-clear]');
              if(all)all.addEventListener('click',()=>toggles().forEach(input=>input.checked=true));
              if(clear)clear.addEventListener('click',()=>toggles().forEach(input=>input.checked=false));
            })();
            </script>
          </div>
        </details>
        <?php
    }

    public static function handleImport(): void {
        self::guard('fieldflow_shopping_import');
        try {
            $batchId = self::processImportRequest();
            self::setNotice('success', 'Ficheiro importado. Revê agora as insígnias, destinatários e anexos do lote.');
            wp_safe_redirect(self::batchEditorUrl($batchId));
        } catch (\Throwable $error) {
            self::setNotice('error', self::friendlyImportError($error));
            wp_safe_redirect(admin_url('admin.php?page=routespro-shopping'));
        }
        exit;
    }

    public static function handleImportAjax(): void {
        if (!current_user_can('routespro_manage')) {
            wp_send_json_error(['message'=>'Sem permissões para importar verificações Shopping.'], 403);
        }
        check_ajax_referer('fieldflow_shopping_import');
        try {
            $batchId = self::processImportRequest();
            wp_send_json_success([
                'batch_id'=>$batchId,
                'redirect'=>self::batchEditorUrl($batchId),
                'message'=>'Importação concluída. Foram criados os formulários por insígnia.',
            ]);
        } catch (\Throwable $error) {
            wp_send_json_error(['message'=>self::friendlyImportError($error)], 422);
        }
    }

    private static function processImportRequest(): int {
        if (function_exists('set_time_limit')) @set_time_limit(180);
        if (function_exists('wp_raise_memory_limit')) wp_raise_memory_limit('admin');
        if (empty($_FILES['shopping_file']) || (int)($_FILES['shopping_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage((int)($_FILES['shopping_file']['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        $file = $_FILES['shopping_file'];
        if (empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) throw new \RuntimeException('O upload do ficheiro não foi validado pelo servidor.');
        if ((int)($file['size'] ?? 0) <= 0) throw new \RuntimeException('O ficheiro enviado está vazio.');
        if ((int)($file['size'] ?? 0) > 20 * 1024 * 1024) throw new \RuntimeException('O ficheiro excede o limite de 20 MB.');
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') throw new \RuntimeException('O formato deve ser XLSX.');
        return ShoppingService::importWorkbook(
            absint($_POST['client_id'] ?? 0),
            absint($_POST['project_id'] ?? 0),
            (string)$file['tmp_name'],
            (string)$file['name'],
            ['title'=>sanitize_text_field((string)($_POST['title'] ?? ''))]
        );
    }

    private static function batchEditorUrl(int $batchId): string {
        return add_query_arg(['page'=>'routespro-shopping','batch_id'=>$batchId], admin_url('admin.php')) . '#ffs-batch-editor';
    }

    private static function uploadErrorMessage(int $code): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE=>'O ficheiro ultrapassa o limite de upload definido no servidor.',
            UPLOAD_ERR_FORM_SIZE=>'O ficheiro ultrapassa o limite permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL=>'O upload ficou incompleto. Tenta novamente.',
            UPLOAD_ERR_NO_FILE=>'Seleciona um ficheiro XLSX válido.',
            UPLOAD_ERR_NO_TMP_DIR=>'O servidor não tem uma pasta temporária disponível.',
            UPLOAD_ERR_CANT_WRITE=>'O servidor não conseguiu gravar o ficheiro temporário.',
            UPLOAD_ERR_EXTENSION=>'Uma extensão do servidor bloqueou o upload.',
        ];
        return $messages[$code] ?? 'Ocorreu um erro durante o upload do ficheiro.';
    }

    private static function friendlyImportError(\Throwable $error): string {
        $message = trim((string)$error->getMessage());
        if ($message === '') $message = 'Não foi possível concluir a importação.';
        if (defined('WP_DEBUG') && WP_DEBUG) error_log('[FieldFlow Shopping] ' . $message . "\n" . $error->getTraceAsString());
        return $message;
    }

    public static function handleSaveBatch(): void {
        $batchId = absint($_POST['batch_id'] ?? 0);
        self::guard('fieldflow_shopping_save_batch_' . $batchId);
        try {
            ShoppingService::saveBatch($batchId, wp_unslash($_POST));
            self::setNotice('success', 'Lote Shopping, destinatários, anexos e disponibilidade por insígnia atualizados.');
        } catch (\Throwable $error) { self::setNotice('error', $error->getMessage()); }
        wp_safe_redirect(add_query_arg(['page'=>'routespro-shopping','batch_id'=>$batchId], admin_url('admin.php')) . '#ffs-batch-editor');
        exit;
    }

    public static function handleSaveTemplate(): void {
        self::guard('fieldflow_shopping_save_template');
        ShoppingService::saveTemplate(wp_unslash($_POST));
        self::setNotice('success', 'Template de email Shopping guardado.');
        wp_safe_redirect(admin_url('admin.php?page=routespro-shopping'));
        exit;
    }

    public static function handleDeleteBatch(): void {
        self::guard('fieldflow_shopping_delete_batch');
        ShoppingService::deleteBatch(absint($_GET['batch_id'] ?? 0));
        self::setNotice('success', 'Lote Shopping eliminado.');
        wp_safe_redirect(admin_url('admin.php?page=routespro-shopping'));
        exit;
    }

    public static function handleResendSubmission(): void {
        $submissionId = absint($_POST['submission_id'] ?? 0);
        self::guard('fieldflow_shopping_resend_submission_' . $submissionId);
        try {
            $result = ShoppingService::resendSubmission($submissionId, wp_unslash($_POST), get_current_user_id());
            self::setNotice((string)($result['status'] ?? '') === 'sent' ? 'success' : 'error', (string)($result['message'] ?? 'Reenvio processado.'));
        } catch (\Throwable $error) {
            self::setNotice('error', $error->getMessage());
        }
        $referer = wp_get_referer();
        $fallback = admin_url('admin.php?page=routespro-shopping');
        $redirect = $referer ? wp_validate_redirect($referer, $fallback) : $fallback;
        wp_safe_redirect($redirect . '#ffs-shopping-results');
        exit;
    }

    public static function handleExport(): void {
        if (!is_user_logged_in()) wp_die('Inicia sessão para exportar resultados Shopping.');
        check_admin_referer('fieldflow_shopping_export');
        $format = sanitize_key((string)($_GET['format'] ?? 'csv'));
        $filters = [
            'client_id'=>absint($_GET['client_id'] ?? 0),
            'project_id'=>absint($_GET['project_id'] ?? 0),
            'location_id'=>absint($_GET['location_id'] ?? 0),
            'user_id'=>absint($_GET['user_id'] ?? 0),
            'batch_id'=>absint($_GET['batch_id'] ?? 0),
            'from'=>sanitize_text_field((string)($_GET['from'] ?? '')),
            'to'=>sanitize_text_field((string)($_GET['to'] ?? '')),
            'q'=>sanitize_text_field((string)($_GET['q'] ?? '')),
        ];
        $clientId = (int)$filters['client_id'];
        if (!$clientId && !empty($filters['project_id'])) {
            global $wpdb;
            $clientId = (int)$wpdb->get_var($wpdb->prepare('SELECT client_id FROM ' . $wpdb->prefix . 'routespro_projects WHERE id=%d', (int)$filters['project_id']));
        }
        if (class_exists('\RoutesPro\SaaS\EntitlementService')) {
            \RoutesPro\SaaS\EntitlementService::assertExportOrDie($format, $clientId, true);
        }
        ShoppingExportService::stream($format, $filters, get_current_user_id());
    }

    private static function guard(string $nonceAction): void {
        if (!current_user_can('routespro_manage')) wp_die('Sem permissões.');
        check_admin_referer($nonceAction);
    }

    private static function setNotice(string $type, string $message): void {
        set_transient(self::NOTICE_PREFIX . get_current_user_id(), ['type'=>$type,'message'=>sanitize_text_field($message)], 90);
    }

    private static function renderNotice(): void {
        $key = self::NOTICE_PREFIX . get_current_user_id();
        $notice = get_transient($key);
        delete_transient($key);
        if (!is_array($notice) || empty($notice['message'])) return;
        $class = ($notice['type'] ?? '') === 'error' ? 'notice notice-error' : 'notice notice-success';
        echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }
}
