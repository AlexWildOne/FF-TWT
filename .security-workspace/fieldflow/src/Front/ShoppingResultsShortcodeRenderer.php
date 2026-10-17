<?php
namespace RoutesPro\Front;

use RoutesPro\Shopping\ShoppingResultsService;

if (!defined('ABSPATH')) exit;

final class ShoppingResultsShortcodeRenderer {
    public static function render(array $atts = []): string {
        $embedded = !empty($atts['embedded']);
        if (!$embedded) {
            $guard = \RoutesPro\Shortcodes::front_guard();
            if ($guard) return $guard;
        }
        $clientId = absint($atts['client_id'] ?? 0);
        $projectId = absint($atts['project_id'] ?? 0);
        $allowCsv = array_key_exists('allow_csv', $atts) ? !empty($atts['allow_csv']) : true;
        $allowExcel = array_key_exists('allow_excel', $atts) ? !empty($atts['allow_excel']) : true;
        $allowPdf = array_key_exists('allow_pdf', $atts) ? !empty($atts['allow_pdf']) : true;
        $id = 'ff-shopping-results-' . wp_generate_uuid4();
        $today = wp_date('Y-m-d');
        $from = wp_date('Y-m-01');
        ob_start();
        ?>
        <div class="ff-shopping-results" id="<?php echo esc_attr($id); ?>" data-api="<?php echo esc_url(rest_url('fieldflow/v1/shopping')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>" data-export-base="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-export-nonce="<?php echo esc_attr(wp_create_nonce('fieldflow_shopping_export')); ?>" data-client-id="<?php echo (int)$clientId; ?>" data-project-id="<?php echo (int)$projectId; ?>">
          <style>
          #<?php echo esc_attr($id); ?>{--ffs-primary:var(--rp-primary,#2563eb);--ffs-accent:var(--rp-accent,#f97316);color:#0f172a;font-family:inherit;width:100%;max-width:100%;overflow-x:hidden}
          #<?php echo esc_attr($id); ?> *{box-sizing:border-box}
          #<?php echo esc_attr($id); ?> .ffsr-toolbar{display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(2,minmax(150px,.65fr)) auto;gap:10px;align-items:end;margin-bottom:14px}
          #<?php echo esc_attr($id); ?> label{display:block;font-size:11px;font-weight:850;text-transform:uppercase;letter-spacing:.055em;color:#64748b}
          #<?php echo esc_attr($id); ?> input{width:100%;min-height:44px;margin-top:6px;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;background:#fff;color:#0f172a}
          #<?php echo esc_attr($id); ?> .ffsr-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
          #<?php echo esc_attr($id); ?> button,#<?php echo esc_attr($id); ?> a.ffsr-btn{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 14px;border:1px solid #cbd5e1;border-radius:12px;background:#fff;color:#0f172a;text-decoration:none;font-weight:800;cursor:pointer}
          #<?php echo esc_attr($id); ?> button.primary,#<?php echo esc_attr($id); ?> a.primary{background:var(--ffs-primary);border-color:var(--ffs-primary);color:#fff}
          #<?php echo esc_attr($id); ?> a.alt{background:var(--ffs-accent);border-color:var(--ffs-accent);color:#fff}
          #<?php echo esc_attr($id); ?> .ffsr-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0 16px}
          #<?php echo esc_attr($id); ?> .ffsr-kpi{padding:14px;border:1px solid #e2e8f0;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.05)}
          #<?php echo esc_attr($id); ?> .ffsr-kpi span{display:block;color:#64748b;font-size:11px;text-transform:uppercase;letter-spacing:.055em;font-weight:850}
          #<?php echo esc_attr($id); ?> .ffsr-kpi strong{display:block;margin-top:7px;font-size:25px;line-height:1}
          #<?php echo esc_attr($id); ?> .ffsr-table-wrap{overflow:auto;border:1px solid #e2e8f0;border-radius:16px;background:#fff}
          #<?php echo esc_attr($id); ?> table{width:100%;min-width:930px;border-collapse:collapse}
          #<?php echo esc_attr($id); ?> th,#<?php echo esc_attr($id); ?> td{padding:11px 12px;border-bottom:1px solid #edf2f7;text-align:left;vertical-align:top;font-size:13px}
          #<?php echo esc_attr($id); ?> th{position:sticky;top:0;z-index:2;background:#f8fafc;color:#64748b;font-size:10px;text-transform:uppercase;letter-spacing:.055em}
          #<?php echo esc_attr($id); ?> .ffsr-pill{display:inline-flex;padding:5px 9px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:850}
          #<?php echo esc_attr($id); ?> .ffsr-pill.sent{background:#dcfce7;color:#166534}#<?php echo esc_attr($id); ?> .ffsr-pill.failed{background:#ffe4e6;color:#be123c}
          #<?php echo esc_attr($id); ?> .ffsr-meta{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin-top:12px;color:#64748b;font-size:12px}
          #<?php echo esc_attr($id); ?> .ffsr-pagination{display:flex;gap:7px;align-items:center}
          #<?php echo esc_attr($id); ?> .ffsr-pagination button{min-width:38px;padding:7px 10px;min-height:34px}
          #<?php echo esc_attr($id); ?> .ffsr-alert{display:none;margin:10px 0;padding:12px 14px;border-radius:12px;font-weight:750}.ffsr-alert.show{display:block}.ffsr-alert.err{background:#fff1f2;color:#be123c;border:1px solid #fecdd3}.ffsr-alert.ok{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
          #<?php echo esc_attr($id); ?> .ffsr-empty{padding:24px;text-align:center;color:#64748b}
          #<?php echo esc_attr($id); ?> .ffsr-detail{margin-top:16px;border:1px solid #dbeafe;border-radius:18px;background:#f8fbff;padding:16px}
          #<?php echo esc_attr($id); ?> .ffsr-detail-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:12px}
          #<?php echo esc_attr($id); ?> .ffsr-detail h4{margin:0;font-size:20px}#<?php echo esc_attr($id); ?> .ffsr-detail p{margin:5px 0 0;color:#64748b}
          #<?php echo esc_attr($id); ?> .ffsr-price-table{min-width:720px}#<?php echo esc_attr($id); ?> .ffsr-price-table td:nth-child(3),#<?php echo esc_attr($id); ?> .ffsr-price-table td:nth-child(4){text-align:right;font-variant-numeric:tabular-nums}
          #<?php echo esc_attr($id); ?> .ffsr-notes{margin-top:12px;padding:12px;border:1px solid #e2e8f0;border-radius:13px;background:#fff;white-space:pre-wrap}
          @media(max-width:900px){#<?php echo esc_attr($id); ?> .ffsr-toolbar{grid-template-columns:1fr 1fr}#<?php echo esc_attr($id); ?> .ffsr-actions{grid-column:1/-1}#<?php echo esc_attr($id); ?> .ffsr-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
          @media(max-width:620px){#<?php echo esc_attr($id); ?> .ffsr-toolbar{grid-template-columns:1fr}#<?php echo esc_attr($id); ?> .ffsr-actions{grid-column:auto}#<?php echo esc_attr($id); ?> .ffsr-kpis{grid-template-columns:1fr 1fr}#<?php echo esc_attr($id); ?> .ffsr-actions a,#<?php echo esc_attr($id); ?> .ffsr-actions button{flex:1 1 calc(50% - 8px)}}
          </style>
          <div class="ffsr-toolbar">
            <label>Pesquisar<input type="search" class="ffsr-q" placeholder="Loja, merchandiser ou lote"></label>
            <label>De<input type="date" class="ffsr-from" value="<?php echo esc_attr($from); ?>"></label>
            <label>Até<input type="date" class="ffsr-to" value="<?php echo esc_attr($today); ?>"></label>
            <div class="ffsr-actions">
              <button type="button" class="primary ffsr-refresh">Atualizar</button>
              <?php if ($allowCsv): ?><a href="#" class="ffsr-btn ffsr-export" data-format="csv">CSV</a><?php endif; ?>
              <?php if ($allowExcel): ?><a href="#" class="ffsr-btn ffsr-export" data-format="xlsx">Excel</a><?php endif; ?>
              <?php if ($allowPdf): ?><a href="#" class="ffsr-btn alt ffsr-export" data-format="pdf">PDF</a><?php endif; ?>
            </div>
          </div>
          <div class="ffsr-alert" aria-live="polite"></div>
          <div class="ffsr-kpis">
            <div class="ffsr-kpi"><span>Submissões</span><strong data-kpi="submissions">0</strong></div>
            <div class="ffsr-kpi"><span>Lojas</span><strong data-kpi="stores">0</strong></div>
            <div class="ffsr-kpi"><span>Merchandisers</span><strong data-kpi="merchandisers">0</strong></div>
            <div class="ffsr-kpi"><span>Emails enviados</span><strong data-kpi="emails_sent">0</strong></div>
          </div>
          <div class="ffsr-table-wrap"><table><thead><tr><th>Data</th><th>Loja</th><th>Projeto</th><th>Merchandiser</th><th>Período</th><th>Preenchimento</th><th>Email</th><th>Ação</th></tr></thead><tbody class="ffsr-body"><tr><td colspan="8" class="ffsr-empty">A carregar resultados...</td></tr></tbody></table></div>
          <div class="ffsr-meta"><span class="ffsr-count">0 resultados</span><div class="ffsr-pagination"></div></div>
          <section class="ffsr-detail" hidden><div class="ffsr-detail-head"><div><h4 class="ffsr-detail-title">Detalhe</h4><p class="ffsr-detail-meta"></p></div><button type="button" class="ffsr-close">Fechar</button></div><div class="ffsr-detail-content"></div></section>
        </div>
        <script>
        (function(){
          const root=document.getElementById(<?php echo wp_json_encode($id); ?>);if(!root)return;
          const api=root.dataset.api,nonce=root.dataset.nonce,exportBase=root.dataset.exportBase,exportNonce=root.dataset.exportNonce;
          const body=root.querySelector('.ffsr-body'),pagination=root.querySelector('.ffsr-pagination'),detail=root.querySelector('.ffsr-detail');
          let page=1,totalPages=1,clientId=root.dataset.clientId||'',projectId=root.dataset.projectId||'';
          const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
          const money=v=>v===null||v===''||typeof v==='undefined'?'n.d.':Number(v).toLocaleString('pt-PT',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';
          const alert=(message,type='err')=>{const box=root.querySelector('.ffsr-alert');box.textContent=message||'';box.className='ffsr-alert '+(message?'show ':'')+type;};
          const params=(includePage=true)=>{const p=new URLSearchParams();if(clientId)p.set('client_id',clientId);if(projectId)p.set('project_id',projectId);const q=root.querySelector('.ffsr-q').value.trim(),from=root.querySelector('.ffsr-from').value,to=root.querySelector('.ffsr-to').value;if(q)p.set('q',q);if(from)p.set('from',from);if(to)p.set('to',to);if(includePage){p.set('page',String(page));p.set('per_page','20');}return p;};
          const request=async path=>{const res=await fetch(api+path,{credentials:'same-origin',headers:{'X-WP-Nonce':nonce}});let data={};try{data=await res.json();}catch(e){}if(!res.ok)throw new Error(data.message||'Não foi possível carregar os resultados.');return data;};
          const statusLabel=s=>s==='sent'?'Enviado':(s==='failed'?'Falhou':(s==='no_recipients'?'Sem destinatários':'Pendente'));
          const renderPagination=()=>{pagination.innerHTML='';if(totalPages<=1)return;const prev=document.createElement('button');prev.type='button';prev.textContent='‹';prev.disabled=page<=1;prev.onclick=()=>{page--;load();};const label=document.createElement('span');label.textContent=page+' / '+totalPages;const next=document.createElement('button');next.type='button';next.textContent='›';next.disabled=page>=totalPages;next.onclick=()=>{page++;load();};pagination.append(prev,label,next);};
          const renderRows=items=>{if(!items.length){body.innerHTML='<tr><td colspan="8" class="ffsr-empty">Ainda não existem submissões Shopping neste âmbito.</td></tr>';return;}body.innerHTML=items.map(item=>`<tr><td>${esc(item.submitted_at||'')}</td><td><strong>${esc(item.location_name||item.source_store_name||'')}</strong>${item.city?'<br><small>'+esc(item.city)+'</small>':''}</td><td>${esc(item.project_name||item.title||'')}</td><td>${esc(item.merchandiser_name||'')}<br><small>${esc(item.merchandiser_email||'')}</small></td><td>Semana ${esc(item.week_no||'n.d.')} ${item.year_no?'/ '+esc(item.year_no):''}</td><td>${esc(item.filled_fields||0)} campos</td><td><span class="ffsr-pill ${esc(item.notification_status||'')}">${esc(statusLabel(item.notification_status))}</span></td><td><button type="button" data-detail="${Number(item.id)}">Ver resultado</button></td></tr>`).join('');body.querySelectorAll('[data-detail]').forEach(btn=>btn.addEventListener('click',()=>openDetail(Number(btn.dataset.detail))));};
          const updateExports=()=>{root.querySelectorAll('.ffsr-export').forEach(link=>{const p=params(false);p.set('action','fieldflow_shopping_export');p.set('format',link.dataset.format||'csv');p.set('_wpnonce',exportNonce);link.href=exportBase+'?'+p.toString();});};
          const load=async()=>{alert('');body.innerHTML='<tr><td colspan="8" class="ffsr-empty">A carregar resultados...</td></tr>';try{const data=await request('/results?'+params(true).toString());const summary=data.summary||{};root.querySelectorAll('[data-kpi]').forEach(el=>el.textContent=Number(summary[el.dataset.kpi]||0).toLocaleString('pt-PT'));const pg=data.pagination||{};page=Number(pg.page||1);totalPages=Number(pg.total_pages||1);root.querySelector('.ffsr-count').textContent=Number(pg.total||0).toLocaleString('pt-PT')+' resultados';renderRows(Array.isArray(data.items)?data.items:[]);renderPagination();updateExports();}catch(error){body.innerHTML='<tr><td colspan="8" class="ffsr-empty">Não foi possível carregar os resultados.</td></tr>';alert(error.message,'err');}};
          const openDetail=async id=>{detail.hidden=false;detail.querySelector('.ffsr-detail-content').innerHTML='<div class="ffsr-empty">A carregar detalhe...</div>';try{const item=await request('/results/'+id);detail.querySelector('.ffsr-detail-title').textContent=(item.location_name||'Loja')+' · '+(item.title||'Shopping');detail.querySelector('.ffsr-detail-meta').textContent=(item.project_name||'')+' · '+(item.merchandiser_name||'')+' · '+(item.submitted_at||'');const prices=Array.isArray(item.prices)?item.prices:[];detail.querySelector('.ffsr-detail-content').innerHTML=`<div class="ffsr-table-wrap"><table class="ffsr-price-table"><thead><tr><th>Categoria</th><th>Artigo</th><th>PVP regular</th><th>PVP promoção</th></tr></thead><tbody>${prices.map(p=>`<tr><td>${esc(p.type||'')}</td><td><strong>${esc(p.name||'')}</strong></td><td>${esc(money(p.regular))}</td><td>${esc(money(p.promo))}</td></tr>`).join('')||'<tr><td colspan="4" class="ffsr-empty">Sem preços registados.</td></tr>'}</tbody></table></div>${item.notes?'<div class="ffsr-notes"><strong>Observações</strong><br>'+esc(item.notes)+'</div>':''}`;detail.scrollIntoView({behavior:'smooth',block:'start'});}catch(error){detail.querySelector('.ffsr-detail-content').innerHTML='<div class="ffsr-empty">'+esc(error.message)+'</div>';}};
          root.querySelector('.ffsr-refresh').addEventListener('click',()=>{page=1;load();});root.querySelector('.ffsr-q').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();page=1;load();}});root.querySelector('.ffsr-from').addEventListener('change',()=>{page=1;load();});root.querySelector('.ffsr-to').addEventListener('change',()=>{page=1;load();});root.querySelector('.ffsr-close').addEventListener('click',()=>{detail.hidden=true;});
          root.addEventListener('routespro:panel-open',()=>load());document.addEventListener('routespro:scope-change',event=>{const d=event.detail||{};if(typeof d.client_id!=='undefined')clientId=d.client_id||'';if(typeof d.project_id!=='undefined')projectId=d.project_id||'';root.dataset.clientId=clientId;root.dataset.projectId=projectId;page=1;load();});
          load();
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
