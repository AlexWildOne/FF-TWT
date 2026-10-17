<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

final class ShoppingShortcodeRenderer {
    public static function render($atts = []): string {
        $guard = \RoutesPro\Shortcodes::front_guard();
        if ($guard) return $guard;
        $theme = \RoutesPro\Shortcodes::front_theme(absint($atts['client_id'] ?? 0), absint($atts['project_id'] ?? 0));
        $id = 'ff-shopping-' . wp_generate_password(8, false, false);
        $api = rest_url('fieldflow/v1/shopping');
        $nonce = wp_create_nonce('wp_rest');
        $clientId = absint($atts['client_id'] ?? 0);
        $projectId = absint($atts['project_id'] ?? 0);
        ob_start();
        ?>
        <style>
        #<?php echo esc_attr($id); ?>{--ffs-primary:<?php echo esc_attr($theme['primary_solid'] ?? '#2563eb'); ?>;--ffs-accent:<?php echo esc_attr($theme['accent_solid'] ?? '#06b6d4'); ?>;font-family:<?php echo esc_attr($theme['font']); ?>;color:#0f172a;padding-bottom:82px;width:100%;max-width:100%;overflow-x:hidden}
        #<?php echo esc_attr($id); ?> *{box-sizing:border-box}
        #<?php echo esc_attr($id); ?> .ffs-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:15px}
        #<?php echo esc_attr($id); ?> h4{font-size:21px;margin:0 0 5px}
        #<?php echo esc_attr($id); ?> .ffs-muted{color:#64748b;font-size:13px;line-height:1.5}
        #<?php echo esc_attr($id); ?> .ffs-toolbar{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:14px}
        #<?php echo esc_attr($id); ?> input,#<?php echo esc_attr($id); ?> textarea,#<?php echo esc_attr($id); ?> select{width:100%;border:1px solid #cbd5e1;border-radius:13px;padding:11px 12px;background:#fff;color:#0f172a;font:inherit}
        #<?php echo esc_attr($id); ?> input:focus,#<?php echo esc_attr($id); ?> textarea:focus,#<?php echo esc_attr($id); ?> select:focus{outline:none;border-color:var(--ffs-primary);box-shadow:0 0 0 3px rgba(37,99,235,.14)}
        #<?php echo esc_attr($id); ?> .ffs-search{max-width:420px}
        #<?php echo esc_attr($id); ?> .ffs-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        #<?php echo esc_attr($id); ?> .ffs-card{border:1px solid #e2e8f0;border-radius:18px;padding:15px;background:#fff;box-shadow:0 10px 28px rgba(15,23,42,.05)}
        #<?php echo esc_attr($id); ?> .ffs-card-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
        #<?php echo esc_attr($id); ?> .ffs-card h5{margin:0;font-size:16px}
        #<?php echo esc_attr($id); ?> .ffs-pills{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
        #<?php echo esc_attr($id); ?> .ffs-pill{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:11px;font-weight:800;white-space:nowrap;flex-shrink:0}
        #<?php echo esc_attr($id); ?> .ffs-pill.done{background:#dcfce7;color:#166534}
        #<?php echo esc_attr($id); ?> .ffs-btn{border:0;border-radius:13px;padding:10px 14px;background:var(--ffs-primary);color:#fff;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
        #<?php echo esc_attr($id); ?> .ffs-btn.ghost{background:#fff;color:#0f172a;border:1px solid #cbd5e1}
        #<?php echo esc_attr($id); ?> .ffs-btn:disabled{opacity:.55;cursor:not-allowed}
        #<?php echo esc_attr($id); ?> .ffs-empty{padding:28px 16px;text-align:center;border:1px dashed #cbd5e1;border-radius:18px;color:#64748b;background:#f8fafc}
        #<?php echo esc_attr($id); ?> .ffs-form-view[hidden],#<?php echo esc_attr($id); ?> .ffs-list-view[hidden]{display:none!important}
        #<?php echo esc_attr($id); ?> .ffs-store-card{margin-bottom:14px;border:1px solid #cbd5e1;background:linear-gradient(135deg,#fff,#f8fafc)}
        #<?php echo esc_attr($id); ?> .ffs-store-card label{display:block;font-weight:900;margin-bottom:7px;font-size:14px}
        #<?php echo esc_attr($id); ?> .ffs-store-card select{min-height:48px;font-weight:700}
        #<?php echo esc_attr($id); ?> .ffs-store-help{margin-top:8px}
        #<?php echo esc_attr($id); ?> .ffs-workspace[hidden]{display:none!important}
        #<?php echo esc_attr($id); ?> .ffs-progress{height:9px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin:12px 0}
        #<?php echo esc_attr($id); ?> .ffs-progress i{display:block;height:100%;background:linear-gradient(90deg,var(--ffs-primary),var(--ffs-accent));width:0;transition:width .2s}
        #<?php echo esc_attr($id); ?> .ffs-group{border:1px solid #e2e8f0;border-radius:17px;background:#fff;margin-bottom:13px;overflow:hidden}
        #<?php echo esc_attr($id); ?> .ffs-group-title{padding:13px 15px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;gap:10px;align-items:center}
        #<?php echo esc_attr($id); ?> .ffs-group-title strong{font-size:15px}
        #<?php echo esc_attr($id); ?> .ffs-price-row{display:grid;grid-template-columns:minmax(220px,1.5fr) minmax(120px,.5fr) minmax(120px,.5fr);gap:10px;align-items:center;padding:11px 15px;border-bottom:1px solid #eef2f7}
        #<?php echo esc_attr($id); ?> .ffs-price-row:last-child{border-bottom:0}
        #<?php echo esc_attr($id); ?> .ffs-product-name{font-weight:700;line-height:1.35}
        #<?php echo esc_attr($id); ?> .ffs-product-block{min-width:0}
        #<?php echo esc_attr($id); ?> .ffs-product-head{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:6px}
        #<?php echo esc_attr($id); ?> .ffs-sequence{display:inline-flex;min-width:26px;height:26px;padding:0 7px;align-items:center;justify-content:center;border-radius:999px;background:#0f172a;color:#fff;font-size:11px;font-weight:900}
        #<?php echo esc_attr($id); ?> .ffs-type-tag{background:#f1f5f9;color:#334155;border:1px solid #e2e8f0}
        #<?php echo esc_attr($id); ?> .ffs-viewbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;margin:-2px 0 14px;padding:11px 12px;border:1px solid #e2e8f0;border-radius:15px;background:#f8fafc}
        #<?php echo esc_attr($id); ?> .ffs-view-switch{display:flex;gap:6px;flex-wrap:wrap}
        #<?php echo esc_attr($id); ?> .ffs-view-btn{border:1px solid #cbd5e1;border-radius:999px;padding:8px 11px;background:#fff;color:#334155;font-size:12px;font-weight:800;cursor:pointer}
        #<?php echo esc_attr($id); ?> .ffs-view-btn.active{background:#0f172a;color:#fff;border-color:#0f172a}
        #<?php echo esc_attr($id); ?> .ffs-flow-note{font-size:12px;color:#475569;line-height:1.45;max-width:610px}
        #<?php echo esc_attr($id); ?> .ffs-route-group .ffs-group-title{background:linear-gradient(90deg,#f8fafc,#eff6ff)}
        #<?php echo esc_attr($id); ?> .ffs-input-wrap label{display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:4px}
        #<?php echo esc_attr($id); ?> .ffs-input-wrap input{padding:9px 10px}
        #<?php echo esc_attr($id); ?> .ffs-form-footer{position:sticky;bottom:72px;z-index:12;display:flex;gap:10px;align-items:center;justify-content:space-between;padding:13px;margin-top:15px;border:1px solid #cbd5e1;border-radius:17px;background:rgba(255,255,255,.96);box-shadow:0 16px 35px rgba(15,23,42,.16);backdrop-filter:blur(12px)}
        #<?php echo esc_attr($id); ?> .ffs-alert{display:none;padding:11px 13px;border-radius:13px;font-weight:700;font-size:13px;margin:12px 0}
        #<?php echo esc_attr($id); ?> .ffs-alert.show{display:block}
        #<?php echo esc_attr($id); ?> .ffs-alert.ok{background:#ecfdf5;color:#166534;border:1px solid #86efac}
        #<?php echo esc_attr($id); ?> .ffs-alert.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
        #<?php echo esc_attr($id); ?> .ffs-alert.warn{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
        @media(max-width:760px){
          #<?php echo esc_attr($id); ?> .ffs-list{grid-template-columns:1fr}
          #<?php echo esc_attr($id); ?> .ffs-price-row{grid-template-columns:1fr 1fr;padding:14px 12px;gap:9px}
          #<?php echo esc_attr($id); ?> .ffs-product-block{grid-column:1/-1}
          #<?php echo esc_attr($id); ?> .ffs-form-footer{bottom:82px;flex-direction:column;align-items:stretch}
          #<?php echo esc_attr($id); ?> .ffs-form-footer .ffs-btn{width:100%}
          #<?php echo esc_attr($id); ?> .ffs-viewbar{align-items:stretch}
          #<?php echo esc_attr($id); ?> .ffs-view-switch{width:100%}
          #<?php echo esc_attr($id); ?> .ffs-view-btn{flex:1;justify-content:center}
          #<?php echo esc_attr($id); ?> .ffs-input-wrap input,#<?php echo esc_attr($id); ?> .ffs-store-card select{min-height:48px;font-size:16px}
        }
        </style>
        <div id="<?php echo esc_attr($id); ?>" class="ff-shopping-hub ff-operational-surface ff-operational-surface-shopping" data-user-id="<?php echo esc_attr(get_current_user_id()); ?>" data-api="<?php echo esc_url($api); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-client-id="<?php echo (int)$clientId; ?>" data-project-id="<?php echo (int)$projectId; ?>">
          <?php echo ReactFrontFoundation::operationalModuleMount($id, 'shopping', 'Shopping'); ?>
          <section class="ffs-list-view">
            <div class="ffs-head">
              <div>
                <h4>Shopping, Verificação de Preços</h4>
                <div class="ffs-muted">Escolhe a insígnia, depois seleciona a loja onde estás. O mesmo Shopping serve todas as lojas dessa cadeia.</div>
              </div>
              <button type="button" class="ffs-btn ghost ffs-refresh">Atualizar</button>
            </div>
            <div class="ffs-alert"></div>
            <div class="ffs-list"><div class="ffs-empty">A carregar insígnias...</div></div>
          </section>

          <section class="ffs-form-view" hidden>
            <div class="ffs-head">
              <div>
                <button type="button" class="ffs-btn ghost ffs-back" style="margin-bottom:10px">Voltar às insígnias</button>
                <h4 class="ffs-form-title">Verificação de preços</h4>
                <div class="ffs-muted ffs-form-meta"></div>
              </div>
              <span class="ffs-pill ffs-form-status">Seleciona a loja</span>
            </div>
            <div class="ffs-alert"></div>

            <div class="ffs-card ffs-store-card">
              <label for="<?php echo esc_attr($id); ?>-store">Loja desta insígnia</label>
              <select id="<?php echo esc_attr($id); ?>-store" class="ffs-store-select">
                <option value="">Selecionar loja...</option>
              </select>
              <div class="ffs-muted ffs-store-help">Seleciona primeiro a loja onde estás. Se uma loja ainda não estiver classificada, podes associá-la à insígnia e o FieldFlow memoriza essa escolha.</div>
            </div>

            <div class="ffs-workspace" hidden>
              <div class="ffs-toolbar"><input type="search" class="ffs-search" placeholder="Pesquisar artigo ou tipo"><button type="button" class="ffs-btn ghost ffs-clear-search">Limpar pesquisa</button></div>
              <div class="ffs-viewbar"><div class="ffs-flow-note"><strong>Percurso do linear.</strong> Segue a ordem importada do ficheiro, mantendo Pernod e concorrência na sequência real para fazeres a loja numa só passagem.</div><div class="ffs-view-switch"><button type="button" class="ffs-view-btn active" data-view-mode="route">Percurso do linear</button><button type="button" class="ffs-view-btn" data-view-mode="type">Agrupar por tipo</button></div></div>
              <div class="ffs-progress"><i></i></div><div class="ffs-muted ffs-progress-label">0 preços preenchidos</div>
              <div class="ffs-groups"></div>
              <div class="ffs-card"><label style="display:block;font-weight:800;margin-bottom:7px">Observações</label><textarea class="ffs-notes" rows="4" placeholder="Notas relevantes sobre preços, ruturas ou indisponibilidades"></textarea></div>
              <div class="ffs-form-footer"><div class="ffs-muted ffs-draft-note">Os dados ficam guardados localmente neste dispositivo até à submissão.</div><button type="button" class="ffs-btn ffs-submit">Guardar e enviar</button></div>
            </div>
          </section>
        </div>
        <script>
        (function(){
          const root=document.getElementById(<?php echo wp_json_encode($id); ?>);if(!root)return;
          const api=root.dataset.api,nonce=root.dataset.nonce,clientId=root.dataset.clientId||'',projectId=root.dataset.projectId||'';
          const listView=root.querySelector('.ffs-list-view'),formView=root.querySelector('.ffs-form-view'),list=root.querySelector('.ffs-list'),groups=root.querySelector('.ffs-groups');
          const storeSelect=root.querySelector('.ffs-store-select'),storeHelp=root.querySelector('.ffs-store-help'),workspace=root.querySelector('.ffs-workspace');
          let assignments=[],active=null,products=[],prices={},locations=[],unmappedLocations=[],selectedLocationId=0,viewMode='route';
          const esc=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
          const alert=(scope,message,type='warn')=>{const box=scope.querySelector('.ffs-alert');box.textContent=message;box.className='ffs-alert show '+type;};
          const clearAlert=scope=>{const box=scope.querySelector('.ffs-alert');box.textContent='';box.className='ffs-alert';};
          const request=async(path,options={})=>{const res=await fetch(api+path,{credentials:'same-origin',headers:Object.assign({'X-WP-Nonce':nonce},options.body?{'Content-Type':'application/json'}:{},options.headers||{}),...options});let data={};try{data=await res.json();}catch(e){}if(!res.ok)throw new Error(data.message||'Não foi possível concluir o pedido.');return data;};
          const query=()=>{const p=new URLSearchParams();if(clientId)p.set('client_id',clientId);if(projectId)p.set('project_id',projectId);return p.toString()?'?'+p.toString():'';};
          const userId=String(root.dataset.userId||'0');
          const legacyLocalKey=(assignmentId,locationId)=>'fieldflow:shopping:draft:'+assignmentId+':'+locationId;
          const localKey=(assignmentId,locationId)=>'fieldflow:shopping:draft:v2:'+userId+':'+assignmentId+':'+locationId;
          const notifyReact=()=>window.dispatchEvent(new CustomEvent('fieldflow:operational-module-refresh',{detail:{rootId:root.id,module:'shopping'}}));
          const storeLabel=location=>String(location.name||'Loja')+(location.city?' · '+String(location.city):'');
          const selectedStore=()=>locations.concat(unmappedLocations).find(location=>Number(location.id)===Number(selectedLocationId))||null;

          const applyReactListSearch=()=>{const input=root.querySelector('[data-fieldflow-react-module-search="shopping"]'),q=String(input?.value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim().toLowerCase();list.querySelectorAll('.ffs-card').forEach(card=>{const hay=String(card.textContent||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();card.classList.toggle('ff-react-module-search-hidden',!!q&&!hay.includes(q));});};
          const renderList=()=>{
            if(!assignments.length){list.innerHTML='<div class="ffs-empty">Não existem insígnias Shopping atribuídas neste momento.</div>';notifyReact();return;}
            list.innerHTML=assignments.map(item=>{
              const count=Number(item.submitted_store_count||0);
              const last=item.last_location_name?`<div class="ffs-muted" style="margin-top:7px">Última loja: ${esc(item.last_location_name)}${item.last_location_city?' · '+esc(item.last_location_city):''}</div>`:'';
              return `<article class="ffs-card"><div class="ffs-card-top"><div><h5>${esc(item.insignia||item.source_store_name||'Insígnia')}</h5><div class="ffs-muted">${esc(item.project_name||item.title||'Shopping')}</div></div><span class="ffs-pill ${count>0?'done':''}">${count} ${count===1?'loja':'lojas'}</span></div><div class="ffs-pills"><span class="ffs-pill">Semana ${esc(item.week_no||'n.d.')}${item.year_no?' / '+esc(item.year_no):''}</span><span class="ffs-pill">${esc(item.client_name||'Cliente')}</span></div>${last}<div style="margin-top:13px"><button type="button" class="ffs-btn" data-open-assignment="${Number(item.id)}">Abrir insígnia</button></div></article>`;
            }).join('');
            list.querySelectorAll('[data-open-assignment]').forEach(button=>button.addEventListener('click',()=>openAssignment(Number(button.dataset.openAssignment))));
            applyReactListSearch();
            notifyReact();
          };

          const loadList=async()=>{list.innerHTML='<div class="ffs-empty">A carregar insígnias...</div>';clearAlert(listView);try{const data=await request('/assignments'+query());assignments=Array.isArray(data.items)?data.items:[];renderList();}catch(error){list.innerHTML='<div class="ffs-empty">Não foi possível carregar o Shopping.</div>';alert(listView,error.message,'err');notifyReact();}};
          const collectPrices=()=>{groups.querySelectorAll('[data-product-key]').forEach(row=>{const key=row.dataset.productKey;prices[key]={regular:row.querySelector('[data-price="regular"]').value.trim()||null,promo:row.querySelector('[data-price="promo"]').value.trim()||null};});updateProgress();};
          const saveDraft=()=>{if(!active||!selectedLocationId)return;collectPrices();try{localStorage.setItem(localKey(active.id,selectedLocationId),JSON.stringify({prices,notes:root.querySelector('.ffs-notes').value,updated_at:Date.now()}));root.querySelector('.ffs-draft-note').textContent='Rascunho guardado neste dispositivo para esta loja.';}catch(e){}};
          const restoreDraft=()=>{if(!active||!selectedLocationId)return;try{const key=localKey(active.id,selectedLocationId),legacyKey=legacyLocalKey(active.id,selectedLocationId);let raw=localStorage.getItem(key),migrated=false;if(!raw){raw=localStorage.getItem(legacyKey);migrated=!!raw;}const draft=JSON.parse(raw||'null');if(draft&&draft.prices){prices=Object.assign({},prices,draft.prices);root.querySelector('.ffs-notes').value=draft.notes||root.querySelector('.ffs-notes').value;root.querySelector('.ffs-draft-note').textContent='Rascunho local recuperado para esta loja.';if(migrated){localStorage.setItem(key,JSON.stringify(draft));localStorage.removeItem(legacyKey);}}}catch(e){}};
          const updateProgress=()=>{let fields=0;Object.values(prices).forEach(row=>{if(row&&row.regular!==null&&row.regular!=='')fields++;if(row&&row.promo!==null&&row.promo!=='')fields++;});const total=Math.max(1,products.length*2),percent=Math.min(100,Math.round(fields/total*100));root.querySelector('.ffs-progress i').style.width=percent+'%';root.querySelector('.ffs-progress-label').textContent=fields+' de '+(products.length*2)+' campos de preço preenchidos, '+percent+'%';};
          const orderedProducts=()=>products.map((product,index)=>({product,index})).sort((a,b)=>{const ar=Number(a.product.source_row)||100000+a.index,br=Number(b.product.source_row)||100000+b.index;return ar-br||a.index-b.index;}).map(item=>item.product);
          const productRow=(product,position,type)=>{const value=prices[product.key]||{},kind=type||product.type||'Outros';return `<div class="ffs-price-row" data-product-key="${esc(product.key)}" data-search="${esc((kind+' '+product.name).toLowerCase())}"><div class="ffs-product-block"><div class="ffs-product-head"><span class="ffs-sequence">${position}</span><span class="ffs-pill ffs-type-tag">${esc(kind)}</span></div><div class="ffs-product-name">${esc(product.name)}</div></div><div class="ffs-input-wrap"><label>PVP regular</label><input type="number" min="0" max="999999" step="0.01" inputmode="decimal" data-price="regular" value="${value.regular===null||value.regular===undefined?'':esc(value.regular)}"></div><div class="ffs-input-wrap"><label>PVP promo</label><input type="number" min="0" max="999999" step="0.01" inputmode="decimal" data-price="promo" value="${value.promo===null||value.promo===undefined?'':esc(value.promo)}"></div></div>`;};
          const renderGroups=()=>{
            if(!selectedLocationId){groups.innerHTML='';return;}
            if(viewMode==='route'){
              const ordered=orderedProducts();
              groups.innerHTML=`<section class="ffs-group ffs-route-group" data-group="percurso"><div class="ffs-group-title"><strong>Percurso do linear</strong><span class="ffs-pill">${ordered.length} artigos</span></div>${ordered.map((product,index)=>productRow(product,index+1,product.type||'Outros')).join('')}</section>`;
            }else{
              const grouped={};orderedProducts().forEach(product=>{const type=product.type||'Outros';(grouped[type]||(grouped[type]=[])).push(product);});let position=0;
              groups.innerHTML=Object.entries(grouped).map(([type,items])=>`<section class="ffs-group" data-group="${esc(type.toLowerCase())}"><div class="ffs-group-title"><strong>${esc(type)}</strong><span class="ffs-pill">${items.length} artigos</span></div>${items.map(product=>productRow(product,++position,type)).join('')}</section>`).join('');
            }
            groups.querySelectorAll('input').forEach(input=>input.addEventListener('input',()=>{collectPrices();window.clearTimeout(input.__ffsDraftTimer);input.__ffsDraftTimer=window.setTimeout(saveDraft,450);}));
            updateProgress();
          };
          const setViewMode=mode=>{if(mode!=='route'&&mode!=='type')return;if(groups.querySelector('[data-product-key]'))collectPrices();viewMode=mode;root.querySelectorAll('[data-view-mode]').forEach(button=>button.classList.toggle('active',button.dataset.viewMode===mode));const note=root.querySelector('.ffs-flow-note');note.innerHTML=mode==='route'?'<strong>Percurso do linear.</strong> Segue a ordem importada do ficheiro, mantendo Pernod e concorrência na sequência real para fazeres a loja numa só passagem.':'<strong>Agrupamento por tipo.</strong> Vista alternativa para consultar todos os artigos Pernod ou de concorrência em bloco.';renderGroups();filterProducts(root.querySelector('.ffs-search').value);};
          const updateHeader=hasSubmission=>{const store=selectedStore(),insignia=active&&active.insignia?active.insignia:'Insígnia';root.querySelector('.ffs-form-title').textContent=store?insignia+' · '+store.name:insignia+' · escolher loja';root.querySelector('.ffs-form-meta').textContent=(active&&active.project_name?active.project_name:'')+' · Semana '+(active&&active.week_no?active.week_no:'n.d.')+(active&&active.year_no?' / '+active.year_no:'')+(active&&active.price_note?' · '+active.price_note:'');const badge=root.querySelector('.ffs-form-status');badge.textContent=!store?'Seleciona a loja':(hasSubmission?'Já submetida':'Por preencher');badge.className='ffs-pill ffs-form-status '+(hasSubmission?'done':'');};
          const populateStores=()=>{const mapped=locations.length?`<optgroup label="Lojas desta insígnia">${locations.map(location=>`<option value="${Number(location.id)}">${esc(storeLabel(location))}</option>`).join('')}</optgroup>`:'';const pending=unmappedLocations.length?`<optgroup label="Lojas por associar à insígnia">${unmappedLocations.map(location=>`<option value="${Number(location.id)}" data-associate="1">${esc(storeLabel(location))} · associar</option>`).join('')}</optgroup>`:'';storeSelect.innerHTML='<option value="">Selecionar loja...</option>'+mapped+pending;storeSelect.value=selectedLocationId?String(selectedLocationId):'';const total=locations.length+unmappedLocations.length;if(!total){storeSelect.disabled=true;storeHelp.textContent='Não encontrei lojas elegíveis neste projeto. Confirma a Base Comercial e a associação das lojas ao projeto.';}else{storeSelect.disabled=false;storeHelp.textContent=locations.length+' loja(s) reconhecida(s) nesta insígnia'+(unmappedLocations.length?' e '+unmappedLocations.length+' loja(s) ainda por classificar. Ao confirmares uma associação, fica memorizada.':'.');}};
          const applyAssignmentPayload=data=>{active=data.assignment||active;products=Array.isArray(data.products)?data.products:[];prices=data.prices&&typeof data.prices==='object'?data.prices:{};locations=Array.isArray(data.locations)?data.locations:locations;unmappedLocations=Array.isArray(data.unmapped_locations)?data.unmapped_locations:[];selectedLocationId=Number(data.location_state&&data.location_state.selected_location_id||0);root.querySelector('.ffs-notes').value=data.notes||'';viewMode='route';root.querySelectorAll('[data-view-mode]').forEach(button=>button.classList.toggle('active',button.dataset.viewMode==='route'));root.querySelector('.ffs-flow-note').innerHTML='<strong>Percurso do linear.</strong> Segue a ordem importada do ficheiro, mantendo Pernod e concorrência na sequência real para fazeres a loja numa só passagem.';populateStores();workspace.hidden=!selectedLocationId;if(selectedLocationId){restoreDraft();renderGroups();}else{groups.innerHTML='';}updateHeader(!!(data.location_state&&data.location_state.has_submission));notifyReact();};
          const openAssignment=async id=>{clearAlert(formView);formView.hidden=false;listView.hidden=true;workspace.hidden=true;storeSelect.innerHTML='<option value="">A carregar lojas...</option>';storeSelect.disabled=true;try{const data=await request('/assignments/'+id);applyAssignmentPayload(data);formView.scrollIntoView({behavior:'smooth',block:'start'});}catch(error){storeSelect.innerHTML='<option value="">Sem lojas disponíveis</option>';alert(formView,error.message,'err');}};
          const selectStore=async()=>{if(!active)return;const nextId=Number(storeSelect.value||0),selectedOption=storeSelect.options[storeSelect.selectedIndex],needsAssociation=!!(selectedOption&&selectedOption.dataset.associate==='1');clearAlert(formView);workspace.hidden=true;selectedLocationId=0;prices={};products=[];root.querySelector('.ffs-notes').value='';updateHeader(false);if(!nextId){storeHelp.textContent='Seleciona primeiro a loja onde estás. Os preços e observações ficam separados por loja.';return;}if(needsAssociation){const label=selectedOption?selectedOption.textContent.replace(/ · associar$/,''):'esta loja';if(!window.confirm('Associar '+label+' à insígnia '+String(active.insignia||'selecionada')+'? Esta escolha ficará memorizada para os próximos Shoppings.')){storeSelect.value='';populateStores();return;}}storeSelect.disabled=true;storeHelp.textContent=needsAssociation?'A associar a loja à insígnia...':'A carregar dados desta loja...';try{const data=needsAssociation?await request('/assignments/'+active.id+'/associate-location',{method:'POST',body:JSON.stringify({location_id:nextId})}):await request('/assignments/'+active.id+'?location_id='+encodeURIComponent(nextId));applyAssignmentPayload(data);storeHelp.textContent=needsAssociation?'Loja associada e memorizada. Podes começar o percurso do linear.':(data.location_state&&data.location_state.has_submission?'Esta loja já tinha uma submissão. Carreguei os últimos preços para poderes rever ou atualizar.':'Loja selecionada. Podes começar o percurso do linear.');}catch(error){storeSelect.disabled=false;alert(formView,error.message,'err');}};
          const back=()=>{formView.hidden=true;listView.hidden=false;active=null;products=[];prices={};locations=[];unmappedLocations=[];selectedLocationId=0;workspace.hidden=true;loadList();listView.scrollIntoView({behavior:'smooth',block:'start'});};
          const submit=async()=>{if(!active)return;if(!selectedLocationId){alert(formView,'Seleciona a loja desta insígnia antes de submeter.','warn');storeSelect.focus();return;}collectPrices();const button=root.querySelector('.ffs-submit');button.disabled=true;clearAlert(formView);try{const data=await request('/submissions',{method:'POST',body:JSON.stringify({assignment_id:active.id,location_id:selectedLocationId,prices,notes:root.querySelector('.ffs-notes').value})});try{localStorage.removeItem(localKey(active.id,selectedLocationId));localStorage.removeItem(legacyLocalKey(active.id,selectedLocationId));}catch(e){}const mailStatus=data.mail&&data.mail.status?String(data.mail.status):'sent';alert(formView,data.message||'Verificação submetida.',mailStatus==='sent'?'ok':'warn');updateHeader(true);root.querySelector('.ffs-draft-note').textContent='Dados sincronizados com o FieldFlow para esta loja.';document.dispatchEvent(new CustomEvent('fieldflow:shopping-submitted',{detail:{assignment_id:active.id,location_id:selectedLocationId,submission_id:data.id||0}}));}catch(error){alert(formView,error.message,'err');}finally{button.disabled=false;}};
          const filterProducts=value=>{value=String(value||'').trim().toLowerCase();groups.querySelectorAll('.ffs-price-row').forEach(row=>row.hidden=!!value&&!String(row.dataset.search||'').includes(value));groups.querySelectorAll('.ffs-group').forEach(group=>{group.hidden=!Array.from(group.querySelectorAll('.ffs-price-row')).some(row=>!row.hidden);});notifyReact();};

          root.querySelector('.ffs-refresh').addEventListener('click',loadList);
          root.querySelector('.ffs-back').addEventListener('click',back);
          root.querySelector('.ffs-submit').addEventListener('click',submit);
          storeSelect.addEventListener('change',selectStore);
          root.querySelector('.ffs-search').addEventListener('input',event=>filterProducts(event.target.value));
          root.querySelector('.ffs-clear-search').addEventListener('click',()=>{root.querySelector('.ffs-search').value='';filterProducts('');});
          root.querySelectorAll('[data-view-mode]').forEach(button=>button.addEventListener('click',()=>setViewMode(button.dataset.viewMode)));
          root.querySelector('.ffs-notes').addEventListener('input',()=>{window.clearTimeout(root.__ffsNotesTimer);root.__ffsNotesTimer=window.setTimeout(saveDraft,450);});
          document.addEventListener('routespro:panel-open',event=>{if(event.detail&&event.detail.panel==='shopping')loadList();});
          loadList();
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}
