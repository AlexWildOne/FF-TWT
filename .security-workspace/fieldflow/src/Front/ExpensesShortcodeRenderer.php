<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

class ExpensesShortcodeRenderer {
    public static function render($atts = []): string {
        $guard = \RoutesPro\Shortcodes::front_guard();
        if ($guard) return $guard;
        $theme = \RoutesPro\Shortcodes::front_theme();
        $categories = \RoutesPro\Expenses\ExpenseService::categories();
        $payments = \RoutesPro\Expenses\ExpenseService::paymentMethods();
        $today = current_time('Y-m-d');
        $nonce = wp_create_nonce('wp_rest');
        $rest = rest_url('routespro/v1/expenses');
        $statement_url = add_query_arg([
            'action' => 'fieldflow_expense_statement_export',
            '_wpnonce' => wp_create_nonce('fieldflow_expense_statement_export'),
        ], admin_url('admin-post.php'));
        [$initial_client_id, $initial_project_id] = \RoutesPro\Support\Permissions::resolve_front_context();
        $runtimeUploadMax = function_exists('wp_max_upload_size') ? (int) wp_max_upload_size() : 0;
        $effectiveUploadMax = $runtimeUploadMax > 0 ? min(\RoutesPro\Expenses\ExpenseService::MAX_FILE_SIZE, $runtimeUploadMax) : \RoutesPro\Expenses\ExpenseService::MAX_FILE_SIZE;
        $clientUploadTarget = min(921600, max(262144, (int) floor($effectiveUploadMax * 0.72)));
        $jsqr = ROUTESPRO_URL . 'assets/vendor/jsqr/jsQR.js';
        $id = 'ff-expenses-' . wp_generate_password(8, false, false);
        ob_start();
        ?>
        <script src="<?php echo esc_url($jsqr); ?>"></script>
        <style>
        #<?php echo esc_attr($id); ?>{--ff-primary:<?php echo esc_attr($theme['primary'] === 'transparent' ? '#7c3aed' : $theme['primary']); ?>;--ff-accent:<?php echo esc_attr($theme['accent'] === 'transparent' ? '#0ea5e9' : $theme['accent']); ?>;font-family:<?php echo esc_attr($theme['font']); ?>;font-size:<?php echo esc_attr($theme['size']); ?>px;color:#0f172a;width:100%;max-width:100%;overflow-x:hidden}
        #<?php echo esc_attr($id); ?> *{box-sizing:border-box}
        #<?php echo esc_attr($id); ?> .ffe-grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:16px}
        #<?php echo esc_attr($id); ?> .ffe-card{background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:18px;box-shadow:0 12px 30px rgba(15,23,42,.06)}
        #<?php echo esc_attr($id); ?> .ffe-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:14px}
        #<?php echo esc_attr($id); ?> h4{margin:0;color:#0f172a;font-size:20px}
        #<?php echo esc_attr($id); ?> .ffe-muted{color:#64748b;font-size:13px;line-height:1.5}
        #<?php echo esc_attr($id); ?> .ffe-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        #<?php echo esc_attr($id); ?> .ffe-field.full{grid-column:1/-1}
        #<?php echo esc_attr($id); ?> label{display:block;font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:.055em;color:#475569;margin-bottom:6px}
        #<?php echo esc_attr($id); ?> input,#<?php echo esc_attr($id); ?> select,#<?php echo esc_attr($id); ?> textarea{width:100%;border:1px solid #cbd5e1;border-radius:13px;padding:11px 12px;background:#fff;color:#0f172a}
        #<?php echo esc_attr($id); ?> textarea{min-height:84px;resize:vertical}
        #<?php echo esc_attr($id); ?> input:focus,#<?php echo esc_attr($id); ?> select:focus,#<?php echo esc_attr($id); ?> textarea:focus{outline:none;border-color:var(--ff-primary);box-shadow:0 0 0 3px rgba(124,58,237,.1)}
        #<?php echo esc_attr($id); ?> .ffe-actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center;margin-top:14px}
        #<?php echo esc_attr($id); ?> .ffe-btn{border:0;border-radius:13px;padding:11px 14px;background:var(--ff-primary);color:#fff;font-weight:800;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px}
        #<?php echo esc_attr($id); ?> .ffe-btn.alt{background:var(--ff-accent)}
        #<?php echo esc_attr($id); ?> .ffe-btn.ghost{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
        #<?php echo esc_attr($id); ?> .ffe-btn:disabled{opacity:.55;cursor:not-allowed}
        #<?php echo esc_attr($id); ?> .ffe-upload{border:1px dashed #94a3b8;background:#f8fafc;border-radius:17px;padding:14px;text-align:center}
        #<?php echo esc_attr($id); ?> .ffe-preview{display:none;margin-top:10px;max-height:240px;width:100%;object-fit:contain;border-radius:14px;background:#0f172a}
        #<?php echo esc_attr($id); ?> .ffe-alert{display:none;margin-top:12px;padding:11px 13px;border-radius:13px;font-weight:700;font-size:13px}
        #<?php echo esc_attr($id); ?> .ffe-alert.show{display:block}
        #<?php echo esc_attr($id); ?> .ffe-alert.ok{background:#ecfdf5;color:#166534;border:1px solid #86efac}
        #<?php echo esc_attr($id); ?> .ffe-alert.warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
        #<?php echo esc_attr($id); ?> .ffe-alert.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        #<?php echo esc_attr($id); ?> .ffe-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:12px}
        #<?php echo esc_attr($id); ?> .ffe-kpi{padding:14px;border-radius:17px;background:#f8fafc;border:1px solid #e2e8f0}
        #<?php echo esc_attr($id); ?> .ffe-kpi span{display:block;color:#64748b;font-size:12px;font-weight:700}
        #<?php echo esc_attr($id); ?> .ffe-kpi strong{display:block;font-size:24px;margin-top:4px;color:#0f172a}
        #<?php echo esc_attr($id); ?> .ffe-filters{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:12px}
        #<?php echo esc_attr($id); ?> .ffe-list{display:grid;gap:10px}
        #<?php echo esc_attr($id); ?> .ffe-item{border:1px solid #e2e8f0;border-radius:17px;padding:13px;background:#fff}
        #<?php echo esc_attr($id); ?> .ffe-item-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
        #<?php echo esc_attr($id); ?> .ffe-item-title{font-weight:900;color:#0f172a}
        #<?php echo esc_attr($id); ?> .ffe-price{font-weight:900;font-size:19px;white-space:nowrap}
        #<?php echo esc_attr($id); ?> .ffe-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}
        #<?php echo esc_attr($id); ?> .ffe-tag{font-size:11px;font-weight:800;border-radius:999px;padding:5px 8px;background:#f1f5f9;color:#334155}
        #<?php echo esc_attr($id); ?> .ffe-tag.qr{background:#ecfeff;color:#155e75}
        #<?php echo esc_attr($id); ?> .ffe-tag.dup{background:#fff7ed;color:#9a3412}
        #<?php echo esc_attr($id); ?> .ffe-tag.approved{background:#ecfdf5;color:#166534}
        #<?php echo esc_attr($id); ?> .ffe-tag.paid{background:#dbeafe;color:#1d4ed8}
        #<?php echo esc_attr($id); ?> .ffe-tag.rejected{background:#fef2f2;color:#991b1b}
        #<?php echo esc_attr($id); ?> .ffe-pagination{display:flex;gap:8px;justify-content:center;align-items:center;margin-top:14px}
        #<?php echo esc_attr($id); ?> .ffe-empty{text-align:center;padding:28px 14px;color:#64748b;border:1px dashed #cbd5e1;border-radius:17px}
        #<?php echo esc_attr($id); ?> .ffe-modal{position:fixed;inset:0;background:rgba(15,23,42,.82);display:none;z-index:999999;padding:18px;align-items:center;justify-content:center}
        #<?php echo esc_attr($id); ?> .ffe-modal.show{display:flex}
        #<?php echo esc_attr($id); ?> .ffe-modal-card{width:min(720px,96vw);background:#fff;border-radius:22px;padding:16px;box-shadow:0 30px 80px rgba(0,0,0,.35)}
        #<?php echo esc_attr($id); ?> video{width:100%;max-height:62vh;border-radius:16px;background:#020617}
        #<?php echo esc_attr($id); ?> .ffe-privacy{margin-top:10px;padding:10px 12px;border-radius:13px;background:#f8fafc;color:#475569;font-size:12px;line-height:1.45}
        #<?php echo esc_attr($id); ?> .ffe-settlement{margin:14px 0;border:1px solid #dbeafe;background:linear-gradient(135deg,#f8fbff,#fff);border-radius:18px;padding:14px}
        #<?php echo esc_attr($id); ?> .ffe-settlement-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
        #<?php echo esc_attr($id); ?> .ffe-settlement-panel{display:none;margin-top:12px;border-top:1px solid #dbeafe;padding-top:12px}
        #<?php echo esc_attr($id); ?> .ffe-settlement-panel.show{display:block}
        #<?php echo esc_attr($id); ?> .ffe-settlement-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        #<?php echo esc_attr($id); ?> .ffe-settlement-option{border:1px solid #e2e8f0;border-radius:15px;padding:12px;background:#fff}
        #<?php echo esc_attr($id); ?> .ffe-signature{width:100%;height:150px;border:1px dashed #94a3b8;border-radius:13px;background:#fff;touch-action:none;display:block}
        #<?php echo esc_attr($id); ?> .ffe-settlement-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:10px 0}
        #<?php echo esc_attr($id); ?> .ffe-settlement-summary div{background:#eff6ff;border-radius:12px;padding:9px}
        #<?php echo esc_attr($id); ?> .ffe-settlement-summary span{display:block;font-size:11px;color:#64748b;font-weight:700}
        #<?php echo esc_attr($id); ?> .ffe-settlement-summary strong{display:block;margin-top:3px}
        @media(max-width:980px){#<?php echo esc_attr($id); ?> .ffe-grid{grid-template-columns:1fr}}
        @media(max-width:640px){#<?php echo esc_attr($id); ?> .ffe-fields,#<?php echo esc_attr($id); ?> .ffe-filters,#<?php echo esc_attr($id); ?> .ffe-settlement-grid,#<?php echo esc_attr($id); ?> .ffe-settlement-summary{grid-template-columns:1fr}#<?php echo esc_attr($id); ?> .ffe-field.full{grid-column:auto}}
        </style>
        <div id="<?php echo esc_attr($id); ?>" class="ff-expenses ff-operational-surface ff-operational-surface-expenses" data-api="<?php echo esc_url($rest); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-statement-url="<?php echo esc_url($statement_url); ?>" data-client-id="<?php echo esc_attr((string)$initial_client_id); ?>" data-project-id="<?php echo esc_attr((string)$initial_project_id); ?>" data-max-upload="<?php echo esc_attr((string)$effectiveUploadMax); ?>" data-upload-target="<?php echo esc_attr((string)$clientUploadTarget); ?>">
          <?php echo ReactFrontFoundation::operationalModuleMount($id, 'expenses', 'Despesas'); ?>
          <div class="ffe-grid">
            <section class="ffe-card">
              <div class="ffe-head">
                <div><h4>Nova despesa</h4><div class="ffe-muted">Lê o QR da fatura, confirma os dados e guarda uma fotografia do comprovativo.</div></div>
                <span class="ffe-tag">Registo interno</span>
              </div>
              <form class="ffe-form" enctype="multipart/form-data">
                <div class="ffe-upload">
                  <strong>Fotografia do comprovativo</strong>
                  <div class="ffe-muted" style="margin:5px 0 10px">Tira uma fotografia nítida, com o total e o QR visíveis.</div>
                  <input class="ffe-receipt" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" hidden>
                  <input class="ffe-receipt-camera" type="file" accept="image/*" capture="environment" hidden>
                  <div class="ffe-actions" style="justify-content:center;margin-top:8px">
                    <button type="button" class="ffe-btn alt ffe-pick-camera">📷 Tirar fotografia</button>
                    <button type="button" class="ffe-btn ghost ffe-pick-gallery">🖼️ Escolher imagem</button>
                  </div>
                  <img class="ffe-preview" alt="Pré-visualização do comprovativo">
                  <div class="ffe-actions" style="justify-content:center">
                    <button type="button" class="ffe-btn alt ffe-analyse">Analisar fotografia</button>
                    <button type="button" class="ffe-btn ghost ffe-camera">Ler QR com câmara</button>
                  </div>
                </div>
                <input type="hidden" name="qr_payload" class="ffe-qr-payload">
                <input type="hidden" name="capture_source" class="ffe-source" value="manual">
                <input type="hidden" name="client_ocr_used" class="ffe-ocr-used" value="0">
                <div class="ffe-alert ffe-scan-status"></div>
                <div class="ffe-fields" style="margin-top:14px">
                  <div class="ffe-field"><label>Data</label><input type="date" name="expense_date" value="<?php echo esc_attr($today); ?>" required></div>
                  <div class="ffe-field"><label>Categoria</label><select name="category"><?php foreach ($categories as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
                  <div class="ffe-field"><label>Fornecedor</label><input type="text" name="merchant_name" maxlength="255" placeholder="Exemplo, Galp ou restaurante"></div>
                  <div class="ffe-field"><label>NIF do emitente</label><input type="text" name="issuer_tax_id" maxlength="30" inputmode="numeric"></div>
                  <div class="ffe-field"><label>Número do documento</label><input type="text" name="document_no" maxlength="120"></div>
                  <div class="ffe-field"><label>ATCUD</label><input type="text" name="atcud" maxlength="120"></div>
                  <div class="ffe-field"><label>Valor total, EUR</label><input type="number" name="amount_total" min="0.01" step="0.01" inputmode="decimal" required></div>
                  <div class="ffe-field"><label>Impostos, EUR</label><input type="number" name="tax_amount" min="0" step="0.01" inputmode="decimal"></div>
                  <div class="ffe-field"><label>Pagamento</label><select name="payment_method"><?php foreach ($payments as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
                  <div class="ffe-field full"><label>Descrição ou motivo</label><textarea name="description" maxlength="1500" placeholder="Contexto da despesa, campanha, deslocação ou observação"></textarea></div>
                </div>
                <div class="ffe-privacy">A fotografia fica num arquivo privado e só pode ser aberta pelo próprio utilizador ou por gestores autorizados no FieldFlow.</div>
                <div class="ffe-actions"><button type="submit" class="ffe-btn ffe-submit">Guardar despesa</button><span class="ffe-muted ffe-save-note"></span></div>
              </form>
            </section>

            <section class="ffe-card">
              <div class="ffe-head"><div><h4>As minhas despesas</h4><div class="ffe-muted">Consulta pessoal, sempre limitada ao utilizador autenticado.</div></div></div>
              <div class="ffe-kpis"><div class="ffe-kpi"><span>Registos filtrados</span><strong class="ffe-count">0</strong></div><div class="ffe-kpi"><span>Total filtrado</span><strong class="ffe-total">0,00 €</strong></div></div>
              <div class="ffe-filters">
                <input type="month" class="ffe-filter-month" aria-label="Mês">
                <select class="ffe-filter-category" aria-label="Categoria"><option value="">Todas as categorias</option><?php foreach ($categories as $key => $label): ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                <select class="ffe-filter-status" aria-label="Estado"><option value="">Todos os estados</option><option value="pending">Pendente</option><option value="approved">Aprovada</option><option value="paid">Paga / Reembolsada</option><option value="rejected">Rejeitada</option></select>
                <input type="date" class="ffe-filter-from" aria-label="Data inicial">
                <input type="date" class="ffe-filter-to" aria-label="Data final">
                <input type="search" class="ffe-filter-search" placeholder="Pesquisar fornecedor ou documento">
              </div>
              <div class="ffe-actions" style="margin:0 0 12px"><button type="button" class="ffe-btn ghost ffe-filter-apply">Aplicar filtros</button><button type="button" class="ffe-btn ghost ffe-filter-clear">Limpar</button><a class="ffe-btn alt ffe-statement-pdf" data-role="expense export download" data-format="pdf" download="fieldflow-folha-despesas.pdf" href="<?php echo esc_url($statement_url); ?>">📄 Folha de despesas PDF</a></div><div class="ffe-privacy" style="margin:-4px 0 12px">A folha PDF respeita o período e os restantes filtros ativos. Inclui totais por dia, logos, identificação do operativo e espaço para data e assinatura.</div>
              <div class="ffe-settlement">
                <div class="ffe-settlement-head"><div><strong>Fecho e comprovativo de recebimento</strong><div class="ffe-muted">Depois de o BO marcar o período como Paga / Reembolsada, envia a folha assinada ou assina diretamente aqui.</div></div><button type="button" class="ffe-btn ghost ffe-settlement-open">Assinar / enviar folha</button></div>
                <div class="ffe-settlement-panel">
                  <div class="ffe-alert ffe-settlement-alert"></div>
                  <div class="ffe-settlement-summary"><div><span>Período pago</span><strong class="ffe-settlement-period">-</strong></div><div><span>Despesas pagas</span><strong class="ffe-settlement-count">0</strong></div><div><span>Total recebido</span><strong class="ffe-settlement-total">0,00 €</strong></div></div>
                  <div class="ffe-muted ffe-settlement-latest" style="margin-bottom:10px"></div>
                  <div class="ffe-settlement-grid">
                    <div class="ffe-settlement-option"><strong>📎 Enviar folha já assinada</strong><div class="ffe-muted" style="margin:5px 0 9px">Para quem imprime ou assina noutro sistema. Aceita PDF, JPG ou PNG.</div><input class="ffe-signed-file" type="file" accept="application/pdf,image/jpeg,image/png"><label style="margin-top:10px;text-transform:none;letter-spacing:0"><input type="checkbox" class="ffe-upload-confirm" style="width:auto;margin-right:6px">Confirmo que recebi o valor indicado para este período.</label><button type="button" class="ffe-btn alt ffe-upload-signed" style="margin-top:9px">Submeter folha assinada</button></div>
                    <div class="ffe-settlement-option"><strong>✍️ Assinar diretamente no FieldFlow</strong><div class="ffe-muted" style="margin:5px 0 9px">Assina com o dedo ou rato. O FieldFlow gera e arquiva a folha PDF já com esta assinatura.</div><canvas class="ffe-signature" aria-label="Campo de assinatura"></canvas><div class="ffe-actions" style="margin-top:8px"><button type="button" class="ffe-btn ghost ffe-signature-clear">Limpar</button></div><label style="margin-top:8px;text-transform:none;letter-spacing:0"><input type="checkbox" class="ffe-signature-confirm" style="width:auto;margin-right:6px">Confirmo que recebi o valor indicado para este período.</label><button type="button" class="ffe-btn alt ffe-signature-submit" style="margin-top:9px">Assinar e submeter</button></div>
                  </div>
                </div>
              </div>
              <div class="ffe-list"><div class="ffe-empty">A carregar despesas...</div></div>
              <div class="ffe-pagination"><button type="button" class="ffe-btn ghost ffe-prev">Anterior</button><span class="ffe-page">Página 1</span><button type="button" class="ffe-btn ghost ffe-next">Seguinte</button></div>
            </section>
          </div>

          <div class="ffe-modal" role="dialog" aria-modal="true" aria-label="Leitor de QR">
            <div class="ffe-modal-card">
              <div class="ffe-head"><div><h4>Ler QR da fatura</h4><div class="ffe-muted">Aponta a câmara para o QR e mantém o telemóvel estável.</div></div><button type="button" class="ffe-btn ghost ffe-camera-close">Fechar</button></div>
              <video class="ffe-video" playsinline muted></video>
              <canvas class="ffe-canvas" hidden></canvas>
              <div class="ffe-alert warn show ffe-camera-status">A preparar a câmara...</div>
            </div>
          </div>
        </div>
        <script>
        (function(){
          const root=document.getElementById(<?php echo wp_json_encode($id); ?>); if(!root) return;
          const api=root.dataset.api, nonce=root.dataset.nonce, statementBase=root.dataset.statementUrl||'';
          const settlementContextApi=api+'/statement-context', settlementEvidenceApi=api+'/statement-evidence';
          const q=s=>root.querySelector(s), qa=s=>Array.from(root.querySelectorAll(s));
          const form=q('.ffe-form'), fileInput=q('.ffe-receipt'), cameraFileInput=q('.ffe-receipt-camera'), preview=q('.ffe-preview');
          const scanStatus=q('.ffe-scan-status'), saveNote=q('.ffe-save-note');
          let page=1,pages=1,stream=null,scanFrame=0,lastScanAt=0;
          const maxUploadBytes=Math.max(0,Number(root.dataset.maxUpload||0));
          const targetUploadBytes=Math.max(262144,Number(root.dataset.uploadTarget||921600));
          const euro=v=>new Intl.NumberFormat('pt-PT',{style:'currency',currency:'EUR'}).format(Number(v||0));
          const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
          const alertBox=(el,msg,type='warn')=>{el.textContent=msg;el.className='ffe-alert show '+type;};
          const clearAlert=el=>{el.textContent='';el.className='ffe-alert';};
          const field=name=>form.elements.namedItem(name);
          const setField=(name,value,force=false)=>{const el=field(name);if(el&&(force||!el.value))el.value=value??'';};
          const selectedReceipt=()=>{const gallery=fileInput.files&&fileInput.files[0];const camera=cameraFileInput.files&&cameraFileInput.files[0];return camera||gallery||null;};
          const humanBytes=bytes=>{const value=Math.max(0,Number(bytes||0));if(value<1024)return Math.round(value)+' B';if(value<1048576)return (value/1024).toFixed(value<10240?1:0)+' KB';return (value/1048576).toFixed(1)+' MB';};
          const responseMessage=(res,text,data)=>{
            if(data&&typeof data==='object'&&data.message){
              const raw=String(data.message||''),clean=raw.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim(),code=String(data.code||'');
              if(code==='internal_server_error'||/erro cr[ií]tico|critical error/i.test(clean))return 'O WordPress encontrou um erro interno ao processar a despesa. Tenta novamente. Se persistir, informa o administrador.';
              return clean||'O servidor devolveu um erro ao processar a despesa.';
            }
            const body=String(text||''),lower=body.toLowerCase(),status=Number(res&&res.status||0);
            if(status===413||/request entity too large|payload too large|content too large|request body too large|413 request/.test(lower))return 'A fotografia ultrapassou o limite aceite pelo servidor. O FieldFlow reduz as imagens automaticamente, volta a selecionar o comprovativo e tenta novamente.';
            if((res&&res.redirected)||/wp-login\.php|id=["']loginform|name=["']log["']/.test(lower))return 'A sessão da aplicação expirou. Volta a abrir o FieldFlow e tenta guardar novamente.';
            if(status===401)return 'A sessão deixou de ser válida. Volta a abrir o FieldFlow e tenta novamente.';
            if(status===403)return 'O servidor recusou este pedido. Confirma a sessão e tenta novamente.';
            if(status===404)return 'O serviço de Despesas não está disponível neste momento. Atualiza a aplicação e tenta novamente.';
            if(status>=500)return 'O servidor encontrou um erro ao processar a despesa. Tenta novamente dentro de momentos.';
            if(/<!doctype|<html[\s>]/i.test(body))return 'O servidor devolveu uma página em vez dos dados esperados. A despesa não foi confirmada. Atualiza a sessão e tenta novamente.';
            return 'O servidor devolveu uma resposta inválida. A despesa não foi confirmada.';
          };
          const requestJson=async(url,options={})=>{
            const opts=Object.assign({credentials:'same-origin',cache:'no-store'},options||{});opts.headers=Object.assign({'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},opts.headers||{});
            const res=await fetch(url,opts),text=await res.text();let data=null;
            if(text){try{data=JSON.parse(text);}catch(_){data=null;}}else data={};
            if(!res.ok||!data||typeof data!=='object'){
              const error=new Error(responseMessage(res,text,data));error.status=Number(res.status||0);error.contentType=String(res.headers&&res.headers.get?res.headers.get('content-type')||'':'');error.redirected=!!res.redirected;
              try{console.warn('[FieldFlow Despesas] resposta REST inválida',{status:error.status,contentType:error.contentType,redirected:error.redirected,url:String(res.url||url)});}catch(_){}
              throw error;
            }
            return data;
          };
          const decodeReceiptImage=file=>{
            if(typeof createImageBitmap==='function')return createImageBitmap(file).then(bitmap=>({image:bitmap,width:bitmap.width,height:bitmap.height,close:()=>{try{bitmap.close();}catch(_){}}}));
            return new Promise((resolve,reject)=>{const url=URL.createObjectURL(file),img=new Image();img.onload=()=>resolve({image:img,width:img.naturalWidth||img.width,height:img.naturalHeight||img.height,close:()=>{try{URL.revokeObjectURL(url);}catch(_){}}});img.onerror=()=>{try{URL.revokeObjectURL(url);}catch(_){}reject(new Error('image_decode_failed'));};img.src=url;});
          };
          const canvasBlob=(decoded,maxSide,quality)=>new Promise(resolve=>{
            const scale=Math.min(1,maxSide/Math.max(decoded.width||1,decoded.height||1)),width=Math.max(1,Math.round((decoded.width||1)*scale)),height=Math.max(1,Math.round((decoded.height||1)*scale));
            const canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;const ctx=canvas.getContext('2d');if(!ctx){resolve(null);return;}ctx.fillStyle='#fff';ctx.fillRect(0,0,width,height);ctx.drawImage(decoded.image,0,0,width,height);canvas.toBlob(resolve,'image/jpeg',quality);
          });
          const optimiseReceiptFile=async file=>{
            if(!(file instanceof Blob))return file;
            const type=String(file.type||'').toLowerCase();if(!['image/jpeg','image/jpg','image/png','image/webp'].includes(type)||Number(file.size||0)<=targetUploadBytes)return file;
            let decoded=null,best=file;
            try{
              decoded=await decodeReceiptImage(file);
              for(const pass of [{side:1800,quality:.82},{side:1600,quality:.77},{side:1400,quality:.72}]){
                const blob=await canvasBlob(decoded,pass.side,pass.quality);if(!blob)continue;
                if(Number(blob.size||0)<Number(best.size||Infinity)){
                  const base=String(file.name||'comprovativo').replace(/\.[^.]+$/,'')||'comprovativo';try{best=new File([blob],base+'.jpg',{type:'image/jpeg',lastModified:file.lastModified||Date.now()});}catch(_){best=blob;}
                }
                if(Number(best.size||0)<=targetUploadBytes)break;
              }
            }catch(_){best=file;}finally{if(decoded&&decoded.close)decoded.close();}
            return best;
          };
          const openNativePicker=input=>{try{if(typeof input.showPicker==='function'){input.showPicker();return;}input.click();}catch(e){try{input.click();}catch(_){}}};
          const stopCaptureStream=mediaStream=>{try{(mediaStream&&mediaStream.getTracks?mediaStream.getTracks():[]).forEach(track=>{try{track.stop();}catch(e){}});}catch(e){}};
          const assignCapturedReceipt=blob=>{
            const file=new File([blob],'fieldflow-despesa-'+Date.now()+'.jpg',{type:'image/jpeg',lastModified:Date.now()});
            const transfer=new DataTransfer();transfer.items.add(file);cameraFileInput.files=transfer.files;cameraFileInput.dispatchEvent(new Event('change',{bubbles:true}));
          };
          const openReceiptCamera=()=>new Promise((resolve,reject)=>{
            if(!navigator.mediaDevices||typeof navigator.mediaDevices.getUserMedia!=='function'){reject(new Error('camera_api_unavailable'));return;}
            let captureStream=null,settled=false;
            const overlay=document.createElement('div');overlay.className='ffe-modal show ffe-capture-modal';overlay.setAttribute('role','dialog');overlay.setAttribute('aria-modal','true');overlay.innerHTML='<div class="ffe-modal-card"><div class="ffe-head"><div><h4>Tirar fotografia</h4><div class="ffe-muted">Enquadra o comprovativo e confirma a captura.</div></div><button type="button" class="ffe-btn ghost ffe-capture-close">Fechar</button></div><video class="ffe-capture-video" autoplay playsinline muted></video><div class="ffe-alert warn show ffe-capture-status">A iniciar a câmara...</div><div class="ffe-actions"><button type="button" class="ffe-btn ghost ffe-capture-cancel">Cancelar</button><button type="button" class="ffe-btn alt ffe-capture-shot" disabled>Capturar fotografia</button></div></div>';
            root.appendChild(overlay);
            const video=overlay.querySelector('video'),shot=overlay.querySelector('.ffe-capture-shot'),status=overlay.querySelector('.ffe-capture-status');
            const finish=(ok,value)=>{if(settled)return;settled=true;stopCaptureStream(captureStream);overlay.remove();ok?resolve(value):reject(value instanceof Error?value:new Error(String(value||'camera_cancelled')));};
            overlay.querySelector('.ffe-capture-close').addEventListener('click',()=>finish(false,new Error('camera_cancelled')));
            overlay.querySelector('.ffe-capture-cancel').addEventListener('click',()=>finish(false,new Error('camera_cancelled')));
            overlay.addEventListener('click',event=>{if(event.target===overlay)finish(false,new Error('camera_cancelled'));});
            shot.addEventListener('click',()=>{
              try{
                const sourceWidth=video.videoWidth||1280,sourceHeight=video.videoHeight||960,maxSide=1800,scale=Math.min(1,maxSide/Math.max(sourceWidth,sourceHeight));
                const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(sourceWidth*scale));canvas.height=Math.max(1,Math.round(sourceHeight*scale));
                const ctx=canvas.getContext('2d');if(!ctx)throw new Error('camera_canvas_unavailable');ctx.drawImage(video,0,0,canvas.width,canvas.height);
                canvas.toBlob(blob=>{if(!blob){finish(false,new Error('camera_capture_failed'));return;}try{assignCapturedReceipt(blob);finish(true,true);}catch(error){finish(false,error);}},'image/jpeg',0.82);
              }catch(error){finish(false,error);}
            });
            navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}).then(mediaStream=>{
              captureStream=mediaStream;video.srcObject=mediaStream;
              const ready=()=>{status.textContent='Câmara pronta.';status.className='ffe-alert ok show ffe-capture-status';shot.disabled=false;};
              if(video.readyState>=2)ready();else video.addEventListener('loadedmetadata',ready,{once:true});
              const playResult=video.play();if(playResult&&typeof playResult.catch==='function')playResult.catch(()=>{});
            }).catch(error=>finish(false,error));
          });
          const cameraErrorMessage=error=>{
            const code=String(error&&error.name||'')+' '+String(error&&error.message||error||'');
            if(/camera_cancelled/i.test(code))return '';
            if(/NotAllowedError|PermissionDeniedError|SecurityError|permission/i.test(code))return 'Não autorizaste o acesso à câmara. Podes tentar novamente ou escolher uma imagem.';
            if(/NotFoundError|DevicesNotFoundError/i.test(code))return 'Não foi encontrada uma câmara disponível neste dispositivo.';
            if(/NotReadableError|TrackStartError/i.test(code))return 'A câmara está a ser utilizada por outra aplicação. Fecha-a e tenta novamente.';
            return 'Não foi possível iniciar a câmara. Tenta novamente ou escolhe uma imagem.';
          };
          const requestMedia=(source,input)=>{
            const payload={source,multiple:false,context:'expenses_receipt'};
            try{var ffParentOrigin=String(window.location.origin||'');if(ffParentOrigin&&window.parent)window.parent.postMessage(Object.assign({type:source==='camera'?'fieldflow.media.camera_requested':'fieldflow.media.gallery_requested'},payload),ffParentOrigin);}catch(e){}
            if(source!=='camera'){openNativePicker(input);return;}
            alertBox(scanStatus,'A preparar a câmara...','warn');
            // 2.2.339: o pedido de acesso nasce da própria utilização da câmara.
            // Não existe preflight nativo nem qualquer encaminhamento automático para Definições.
            openReceiptCamera().then(()=>clearAlert(scanStatus)).catch(error=>{
              const code=String(error&&error.name||'')+' '+String(error&&error.message||error||'');
              if(/camera_api_unavailable|NotSupportedError/i.test(code)){openNativePicker(input);return;}
              const message=cameraErrorMessage(error);if(message)alertBox(scanStatus,message,'err');else clearAlert(scanStatus);
            });
          };
          const showReceiptPreview=()=>{
            const file=selectedReceipt();clearAlert(scanStatus);
            if(!file){preview.removeAttribute('src');preview.style.display='none';return;}
            const previous=preview.dataset.objectUrl||'';if(previous)try{URL.revokeObjectURL(previous);}catch(e){}
            const url=URL.createObjectURL(file);preview.dataset.objectUrl=url;preview.src=url;preview.style.display='block';
          };
          const parseAtQr=payload=>{
            const fields={}; String(payload||'').split('*').forEach(part=>{const i=part.indexOf(':');if(i>0)fields[part.slice(0,i).trim().toUpperCase()]=part.slice(i+1).trim();});
            const amount=parseFloat(String(fields.O||'').replace(',','.'))||0;
            const tax=parseFloat(String(fields.N||'').replace(',','.'))||0;
            let date=''; if(/^\d{8}$/.test(fields.F||''))date=fields.F.slice(0,4)+'-'+fields.F.slice(4,6)+'-'+fields.F.slice(6,8);
            return {fields,amount,tax,date,looksValid:!!(fields.A&&fields.D&&fields.F&&fields.G&&fields.O)};
          };
          const applyQr=payload=>{
            const data=parseAtQr(payload); field('qr_payload').value=payload; field('capture_source').value='qr_at';
            setField('issuer_tax_id',data.fields.A,true); setField('document_no',data.fields.G,true); setField('atcud',data.fields.H,true);
            if(data.date)setField('expense_date',data.date,true); if(data.amount)setField('amount_total',data.amount.toFixed(2),true); if(data.tax||data.fields.N)setField('tax_amount',data.tax.toFixed(2),true);
            alertBox(scanStatus,data.looksValid?'QR fiscal lido. Confirma o fornecedor e guarda a despesa.':'QR lido parcialmente. Confirma os campos antes de guardar.',data.looksValid?'ok':'warn');
            return data.looksValid;
          };
          const canvasImageData=async file=>{
            const bmp=await createImageBitmap(file); const max=1900, scale=Math.min(1,max/Math.max(bmp.width,bmp.height));
            const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bmp.width*scale));canvas.height=Math.max(1,Math.round(bmp.height*scale));
            const ctx=canvas.getContext('2d',{willReadFrequently:true});ctx.drawImage(bmp,0,0,canvas.width,canvas.height);return {canvas,ctx,data:ctx.getImageData(0,0,canvas.width,canvas.height)};
          };
          const detectQrInFile=async file=>{
            const img=await canvasImageData(file);
            if('BarcodeDetector' in window){try{const detector=new BarcodeDetector({formats:['qr_code']});const found=await detector.detect(img.canvas);if(found&&found[0]&&found[0].rawValue)return found[0].rawValue;}catch(e){}}
            if(typeof window.jsQR==='function'){const code=window.jsQR(img.data.data,img.data.width,img.data.height,{inversionAttempts:'attemptBoth'});if(code&&code.data)return code.data;}
            return '';
          };
          const detectTextTotal=async file=>{
            if(!('TextDetector' in window))return null;
            try{
              const img=await canvasImageData(file), detector=new TextDetector(), blocks=await detector.detect(img.canvas);
              const text=blocks.map(b=>b.rawValue||'').join('\n');
              const patterns=[/(?:TOTAL\s*(?:A\s*PAGAR)?|A\s*PAGAR|TOTAL\s*EUR)\s*[:€]?\s*(\d{1,7}[\.,]\d{2})/ig,/(\d{1,7}[\.,]\d{2})\s*€\s*(?:TOTAL|A\s*PAGAR)?/ig];
              const values=[];patterns.forEach(re=>{let m;while((m=re.exec(text))!==null)values.push(parseFloat(m[1].replace('.','').replace(',','.')));});
              return values.length?Math.max(...values.filter(Number.isFinite)):null;
            }catch(e){return null;}
          };
          fileInput.addEventListener('change',()=>{if(fileInput.files&&fileInput.files.length){try{cameraFileInput.value='';}catch(e){}field('capture_source').value='manual';}showReceiptPreview();});
          cameraFileInput.addEventListener('change',()=>{if(cameraFileInput.files&&cameraFileInput.files.length){try{fileInput.value='';}catch(e){}field('capture_source').value='camera';}showReceiptPreview();});
          q('.ffe-pick-camera').addEventListener('click',()=>requestMedia('camera',cameraFileInput));
          q('.ffe-pick-gallery').addEventListener('click',()=>requestMedia('gallery',fileInput));
          q('.ffe-analyse').addEventListener('click',async()=>{
            const file=selectedReceipt();if(!file){alertBox(scanStatus,'Escolhe ou tira primeiro uma fotografia do comprovativo.','warn');return;}
            const btn=q('.ffe-analyse');btn.disabled=true;alertBox(scanStatus,'A analisar o QR e o total da fotografia...','warn');
            try{
              const payload=await detectQrInFile(file);if(payload){applyQr(payload);return;}
              const total=await detectTextTotal(file);if(total){field('amount_total').value=total.toFixed(2);field('capture_source').value='photo_ocr';field('client_ocr_used').value='1';alertBox(scanStatus,'Total detetado na fotografia. Confirma o valor antes de guardar.','ok');}
              else{alertBox(scanStatus,'Não foi possível ler o QR nem detetar o total. Introduz o valor manualmente.','warn');}
            }catch(e){alertBox(scanStatus,'Não foi possível analisar a imagem. Podes continuar com preenchimento manual.','warn');}
            finally{btn.disabled=false;}
          });
          const stopCamera=()=>{cancelAnimationFrame(scanFrame);if(stream)stream.getTracks().forEach(t=>t.stop());stream=null;q('.ffe-video').srcObject=null;q('.ffe-modal').classList.remove('show');};
          const scanVideo=(timestamp=0)=>{
            const video=q('.ffe-video'), canvas=q('.ffe-canvas');if(!stream||video.readyState<2){scanFrame=requestAnimationFrame(scanVideo);return;}
            if(timestamp-lastScanAt<120){scanFrame=requestAnimationFrame(scanVideo);return;}lastScanAt=timestamp;
            const max=1100, scale=Math.min(1,max/Math.max(video.videoWidth,video.videoHeight));canvas.width=Math.round(video.videoWidth*scale);canvas.height=Math.round(video.videoHeight*scale);
            const ctx=canvas.getContext('2d',{willReadFrequently:true});ctx.drawImage(video,0,0,canvas.width,canvas.height);const data=ctx.getImageData(0,0,canvas.width,canvas.height);
            const code=typeof window.jsQR==='function'?window.jsQR(data.data,data.width,data.height,{inversionAttempts:'attemptBoth'}):null;
            if(code&&code.data){applyQr(code.data);alertBox(q('.ffe-camera-status'),'QR lido com sucesso.','ok');setTimeout(stopCamera,450);return;}
            scanFrame=requestAnimationFrame(scanVideo);
          };
          q('.ffe-camera').addEventListener('click',async()=>{
            q('.ffe-modal').classList.add('show');alertBox(q('.ffe-camera-status'),'A preparar a câmara...','warn');
            try{stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1920},height:{ideal:1080}},audio:false});const video=q('.ffe-video');video.srcObject=stream;await video.play();alertBox(q('.ffe-camera-status'),'À procura do QR...','warn');scanVideo();}
            catch(e){alertBox(q('.ffe-camera-status'),'Não foi possível abrir a câmara. Usa a fotografia do comprovativo.','err');}
          });
          q('.ffe-camera-close').addEventListener('click',stopCamera);q('.ffe-modal').addEventListener('click',e=>{if(e.target===q('.ffe-modal'))stopCamera();});
          form.addEventListener('submit',async e=>{
            e.preventDefault();const receipt=selectedReceipt();if(!receipt){alertBox(scanStatus,'Adiciona uma fotografia do comprovativo antes de guardar.','warn');return;}const btn=q('.ffe-submit');btn.disabled=true;saveNote.textContent='A preparar fotografia...';
            try{
              const uploadReceipt=await optimiseReceiptFile(receipt);if(uploadReceipt!==receipt)alertBox(scanStatus,'Fotografia otimizada de '+humanBytes(receipt.size)+' para '+humanBytes(uploadReceipt.size)+'. A guardar...','warn');
              if(maxUploadBytes>0&&Number(uploadReceipt.size||0)>maxUploadBytes)throw new Error('A fotografia continua demasiado grande para o limite deste servidor ('+humanBytes(maxUploadBytes)+'). Usa Tirar fotografia ou seleciona uma imagem mais pequena.');
              saveNote.textContent='A guardar...';const fd=new FormData(form);fd.delete('receipt');fd.append('receipt',uploadReceipt,uploadReceipt.name||('comprovativo-'+Date.now()+'.jpg'));
              const data=await requestJson(api,{method:'POST',headers:{'X-WP-Nonce':nonce},body:fd});alertBox(scanStatus,data.message||'Despesa guardada.','ok');form.reset();try{cameraFileInput.value='';fileInput.value='';}catch(e){}field('expense_date').value=<?php echo wp_json_encode($today); ?>;preview.removeAttribute('src');preview.style.display='none';page=1;await load();
            }
            catch(err){alertBox(scanStatus,err.message||'Não foi possível guardar a despesa.','err');}
            finally{btn.disabled=false;saveNote.textContent='';}
          });
          const filterMap=[['month','.ffe-filter-month'],['category','.ffe-filter-category'],['status','.ffe-filter-status'],['date_from','.ffe-filter-from'],['date_to','.ffe-filter-to'],['search','.ffe-filter-search']];
          const params=()=>{const p=new URLSearchParams({page:String(page),per_page:'8'});filterMap.forEach(([k,s])=>{const v=q(s).value.trim();if(v)p.set(k,v);});return p;};
          const liveScope=()=>{const shell=root.closest('[data-client-id],[data-project-id]');return {clientId:String(shell?.dataset?.clientId||root.dataset.clientId||'').trim(),projectId:String(shell?.dataset?.projectId||root.dataset.projectId||'').trim()};};
          const syncStatementUrl=()=>{const link=q('.ffe-statement-pdf');if(!link||!statementBase)return;let url;try{url=new URL(statementBase,window.location.href);}catch(_){return;}filterMap.forEach(([k,s])=>{const v=String(q(s)?.value||'').trim();if(v)url.searchParams.set(k,v);else url.searchParams.delete(k);});const scope=liveScope();if(scope.clientId&&scope.clientId!=='0')url.searchParams.set('client_id',scope.clientId);else url.searchParams.delete('client_id');if(scope.projectId&&scope.projectId!=='0')url.searchParams.set('project_id',scope.projectId);else url.searchParams.delete('project_id');link.href=url.toString();const from=String(q('.ffe-filter-from')?.value||'').replace(/-/g,''),to=String(q('.ffe-filter-to')?.value||'').replace(/-/g,''),month=String(q('.ffe-filter-month')?.value||'').replace(/-/g,'');link.download='fieldflow-folha-despesas-'+(from||month||'periodo')+(to&&to!==from?'-'+to:'')+'.pdf';};
          const settlementParams=()=>{const p=new URLSearchParams();filterMap.forEach(([k,s])=>{if(k==='status')return;const v=String(q(s)?.value||'').trim();if(v)p.set(k,v);});p.set('status','paid');const scope=liveScope();if(scope.clientId&&scope.clientId!=='0')p.set('client_id',scope.clientId);if(scope.projectId&&scope.projectId!=='0')p.set('project_id',scope.projectId);return p;};
          let settlementContext=null,signatureDirty=false;
          const settlementAlert=q('.ffe-settlement-alert');
          const paintSettlement=ctx=>{settlementContext=ctx||null;q('.ffe-settlement-period').textContent=ctx?.period_label||'-';q('.ffe-settlement-count').textContent=String(ctx?.count||0);q('.ffe-settlement-total').textContent=euro(ctx?.total||0);const latest=ctx?.latest_evidence;q('.ffe-settlement-latest').textContent=latest?('Último comprovativo: '+String(latest.method_label||'Submetido')+' · '+String(latest.submitted_at||'')):'Ainda não existe comprovativo assinado para este fecho.';};
          const loadSettlementContext=async()=>{clearAlert(settlementAlert);try{const data=await requestJson(settlementContextApi+'?'+settlementParams().toString(),{headers:{'X-WP-Nonce':nonce}});paintSettlement(data.context||{});if(!(data.context&&data.context.can_submit))alertBox(settlementAlert,'Ainda não existem despesas Paga / Reembolsada neste período. O BO deve fechar o pagamento primeiro.','warn');return data.context||{};}catch(e){paintSettlement(null);alertBox(settlementAlert,e.message||'Seleciona um período para consultar o fecho.','warn');return null;}};
          const appendSettlementFields=fd=>{settlementParams().forEach((v,k)=>fd.set(k,v));fd.set('declaration_confirmed','1');};
          const submitEvidence=async(fd,button)=>{button.disabled=true;clearAlert(settlementAlert);try{const data=await requestJson(settlementEvidenceApi,{method:'POST',headers:{'X-WP-Nonce':nonce},body:fd});alertBox(settlementAlert,data.message||'Comprovativo guardado.','ok');await loadSettlementContext();return true;}catch(e){alertBox(settlementAlert,e.message||'Não foi possível guardar o comprovativo.','err');return false;}finally{button.disabled=false;}};
          const signatureCanvas=q('.ffe-signature'),signatureCtx=signatureCanvas.getContext('2d');let signing=false,lastPoint=null;
          const resetSignature=()=>{const rect=signatureCanvas.getBoundingClientRect(),ratio=Math.max(1,Math.min(2,window.devicePixelRatio||1));signatureCanvas.width=Math.max(320,Math.round(rect.width*ratio));signatureCanvas.height=Math.max(150,Math.round(rect.height*ratio));signatureCtx.setTransform(1,0,0,1,0,0);signatureCtx.fillStyle='#ffffff';signatureCtx.fillRect(0,0,signatureCanvas.width,signatureCanvas.height);signatureCtx.strokeStyle='#111827';signatureCtx.lineWidth=Math.max(2,3*ratio);signatureCtx.lineCap='round';signatureCtx.lineJoin='round';signatureDirty=false;lastPoint=null;};
          const signaturePoint=e=>{const rect=signatureCanvas.getBoundingClientRect();return {x:(e.clientX-rect.left)*(signatureCanvas.width/Math.max(1,rect.width)),y:(e.clientY-rect.top)*(signatureCanvas.height/Math.max(1,rect.height))};};
          signatureCanvas.addEventListener('pointerdown',e=>{signing=true;lastPoint=signaturePoint(e);signatureCanvas.setPointerCapture?.(e.pointerId);e.preventDefault();});
          signatureCanvas.addEventListener('pointermove',e=>{if(!signing)return;const p=signaturePoint(e);signatureCtx.beginPath();signatureCtx.moveTo(lastPoint.x,lastPoint.y);signatureCtx.lineTo(p.x,p.y);signatureCtx.stroke();lastPoint=p;signatureDirty=true;e.preventDefault();});
          const endSignature=e=>{signing=false;lastPoint=null;try{signatureCanvas.releasePointerCapture?.(e.pointerId);}catch(_){}};signatureCanvas.addEventListener('pointerup',endSignature);signatureCanvas.addEventListener('pointercancel',endSignature);signatureCanvas.addEventListener('pointerleave',e=>{if(signing)endSignature(e);});
          q('.ffe-signature-clear').addEventListener('click',resetSignature);
          q('.ffe-settlement-open').addEventListener('click',async()=>{const panel=q('.ffe-settlement-panel');panel.classList.toggle('show');if(panel.classList.contains('show')){setTimeout(resetSignature,30);await loadSettlementContext();}});
          q('.ffe-upload-signed').addEventListener('click',async()=>{const file=q('.ffe-signed-file').files&&q('.ffe-signed-file').files[0];if(!file){alertBox(settlementAlert,'Seleciona primeiro a folha assinada em PDF, JPG ou PNG.','warn');return;}if(!q('.ffe-upload-confirm').checked){alertBox(settlementAlert,'Confirma a declaração de recebimento antes de submeter.','warn');return;}const fd=new FormData();appendSettlementFields(fd);fd.set('mode','upload');fd.set('signed_statement',file,file.name||'folha-assinada');if(await submitEvidence(fd,q('.ffe-upload-signed'))){q('.ffe-signed-file').value='';q('.ffe-upload-confirm').checked=false;}});
          q('.ffe-signature-submit').addEventListener('click',async()=>{if(!signatureDirty){alertBox(settlementAlert,'Assina primeiro no campo de assinatura.','warn');return;}if(!q('.ffe-signature-confirm').checked){alertBox(settlementAlert,'Confirma a declaração de recebimento antes de submeter.','warn');return;}const fd=new FormData();appendSettlementFields(fd);fd.set('mode','signature');fd.set('signature_data',signatureCanvas.toDataURL('image/jpeg',0.9));if(await submitEvidence(fd,q('.ffe-signature-submit'))){q('.ffe-signature-confirm').checked=false;resetSignature();}});
          const render=items=>{
            const list=q('.ffe-list');if(!items.length){list.innerHTML='<div class="ffe-empty">Ainda não existem despesas para estes filtros.</div>';return;}
            list.innerHTML=items.map(item=>`<article class="ffe-item"><div class="ffe-item-top"><div><div class="ffe-item-title">${esc(item.merchant_name||item.category_label)}</div><div class="ffe-muted">${esc(item.expense_date)}${item.document_no?' · '+esc(item.document_no):''}</div></div><div class="ffe-price">${euro(item.amount_total)}</div></div>${item.description?`<div class="ffe-muted" style="margin-top:8px">${esc(item.description)}</div>`:''}<div class="ffe-tags"><span class="ffe-tag">${esc(item.category_label)}</span><span class="ffe-tag ${esc(item.status)}">${esc(item.status_label)}</span>${item.capture_source==='qr_at'?'<span class="ffe-tag qr">QR fiscal</span>':''}${item.duplicate_of_id?'<span class="ffe-tag dup">Possível duplicado</span>':''}${item.receipt_url?`<a class="ffe-tag" target="_blank" rel="noopener" href="${esc(item.receipt_url)}">Ver comprovativo</a>`:''}</div></article>`).join('');
          };
          const notifyOperationalRefresh=()=>window.dispatchEvent(new CustomEvent('fieldflow:operational-module-refresh',{detail:{rootId:root.id,module:'expenses'}}));
          const load=async()=>{
            q('.ffe-list').innerHTML='<div class="ffe-empty">A carregar despesas...</div>';
            try{const data=await requestJson(api+'?'+params().toString(),{headers:{'X-WP-Nonce':nonce}});pages=data.pagination.pages||1;q('.ffe-count').textContent=data.summary.count||0;q('.ffe-total').textContent=euro(data.summary.total||0);q('.ffe-page').textContent='Página '+(data.pagination.page||1)+' de '+pages;q('.ffe-prev').disabled=page<=1;q('.ffe-next').disabled=page>=pages;render(data.items||[]);}
            catch(e){q('.ffe-list').innerHTML='<div class="ffe-empty">'+esc(e.message||'Não foi possível carregar as despesas.')+'</div>';}
            finally{notifyOperationalRefresh();}
          };
          qa('.ffe-filters input,.ffe-filters select').forEach(el=>{el.addEventListener('input',()=>{syncStatementUrl();settlementContext=null;});el.addEventListener('change',()=>{syncStatementUrl();settlementContext=null;});});
          q('.ffe-filter-apply').addEventListener('click',()=>{page=1;syncStatementUrl();load();});q('.ffe-filter-clear').addEventListener('click',()=>{qa('.ffe-filters input,.ffe-filters select').forEach(el=>el.value='');page=1;syncStatementUrl();load();});
          q('.ffe-prev').addEventListener('click',()=>{if(page>1){page--;load();}});q('.ffe-next').addEventListener('click',()=>{if(page<pages){page++;load();}});
          root.addEventListener('routespro:panel-open',e=>{if(e.detail&&e.detail.panel==='expenses'){syncStatementUrl();load();}});
          window.addEventListener('routespro:scope-change',()=>{syncStatementUrl();if(q('.ffe-settlement-panel').classList.contains('show'))loadSettlementContext();});
          syncStatementUrl();
          load();
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
