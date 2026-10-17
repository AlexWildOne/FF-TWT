<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

class StocksShortcodeRenderer {
    private static int $instance = 0;

    public static function team(array $atts = []): string {
        $guard = \RoutesPro\Shortcodes::front_guard();
        if ($guard) return $guard;
        $id = 'ff-stock-team-' . (++self::$instance);
        $clientId = absint($atts['client_id'] ?? 0);
        $projectId = absint($atts['project_id'] ?? 0);
        ob_start(); self::styles(); ?>
        <div class="ff-stock-hub ff-stock-team ff-operational-surface ff-operational-surface-stocks" id="<?php echo esc_attr($id); ?>" data-mode="team" data-client-id="<?php echo (int)$clientId; ?>" data-project-id="<?php echo (int)$projectId; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
          <?php echo ReactFrontFoundation::operationalModuleMount($id, 'stocks', 'Stocks'); ?>
          <div class="ff-stock-banner"><div><span class="ff-stock-eyebrow">Stock operacional</span><h4>O que deves validar em loja</h4><p>Consulta o último snapshot apenas para as lojas que te estão atribuídas em campanhas e rotas.</p></div><span class="ff-stock-live">Dados publicados</span></div>
          <div class="ff-stock-state" data-state><div class="ff-stock-loading">A preparar stocks relevantes...</div></div>
          <div class="ff-stock-content" data-content hidden>
            <div class="ff-stock-kpis" data-kpis></div>
            <div class="ff-stock-toolbar"><label>Insígnia<select data-insignia-filter><option value="">Todas as insígnias</option></select></label><label>Loja<select data-store-filter><option value="">Todas as lojas</option></select></label><label>Pesquisar produto<input type="search" data-search placeholder="Produto, EAN ou artigo"></label><span class="ff-stock-meta" data-meta></span></div>
            <p class="ff-stock-filter-feedback" data-filter-feedback role="status" aria-live="polite"></p><div class="ff-stock-stores" data-stores></div>
          </div>
        </div>
        <?php self::teamScript($id); return ob_get_clean();
    }

    public static function client(array $atts = []): string {
        $guard = \RoutesPro\Shortcodes::front_guard();
        if ($guard) return $guard;
        $id = 'ff-stock-client-' . (++self::$instance);
        $clientId = absint($atts['client_id'] ?? 0);
        $projectId = absint($atts['project_id'] ?? 0);
        ob_start(); self::styles(); ?>
        <div class="ff-stock-hub ff-stock-client ff-operational-surface ff-operational-surface-stocks" id="<?php echo esc_attr($id); ?>" data-mode="client" data-client-id="<?php echo (int)$clientId; ?>" data-project-id="<?php echo (int)$projectId; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
          <?php echo ReactFrontFoundation::operationalModuleMount($id, 'stocks', 'Stocks'); ?>
          <div class="ff-stock-banner client"><div><span class="ff-stock-eyebrow">Distribuição de informação</span><h4>Stocks partilhados com a equipa</h4><p>Visibilidade sobre publicação, cobertura, leitura e riscos de disponibilidade.</p></div><span class="ff-stock-live">Sincronizado</span></div>
          <div class="ff-stock-state" data-state><div class="ff-stock-loading">A carregar o último snapshot...</div></div>
          <div class="ff-stock-content" data-content hidden>
            <div class="ff-stock-delivery" data-delivery></div>
            <div class="ff-stock-kpis" data-kpis></div>
            <div class="ff-stock-client-grid">
              <section class="ff-stock-section"><div class="ff-stock-section-head"><div><h5>Snapshots ativos</h5><p>Última informação publicada por projeto e insígnia.</p></div></div><div data-batches></div></section>
              <section class="ff-stock-section"><div class="ff-stock-section-head"><div><h5>Lojas prioritárias</h5><p>Concentração de ruturas, stock baixo e valores negativos.</p></div></div><div data-store-risks></div></section>
            </div>
            <section class="ff-stock-section"><div class="ff-stock-section-head"><div><h5>Produtos a acompanhar</h5><p>Linhas com stock igual ou inferior ao limiar operacional.</p></div><label class="ff-stock-inline-search">Pesquisar<input type="search" data-risk-search placeholder="Loja, produto ou EAN"></label></div><div class="ff-stock-risk-list" data-risks></div></section>
          </div>
        </div>
        <?php self::clientScript($id); return ob_get_clean();
    }

    private static function styles(): void { ?>
      <style>
      .ff-stock-hub{--ffs-ink:#0f172a;--ffs-muted:#64748b;--ffs-line:#e2e8f0;--ffs-soft:#f8fafc;--ffs-ok:#047857;--ffs-warn:#b45309;--ffs-risk:#b91c1c;--ffs-header-start:var(--ff-header-start,#122033);--ffs-header-end:var(--ff-header-end,#0f766e);--ffs-hero-text:var(--ff-header-title-text,var(--ff-hero-text,#fff));--ffs-hero-muted:var(--ff-header-body-text,var(--ff-hero-muted,#dbeafe));color:var(--ffs-ink);width:100%;max-width:100%;overflow-x:hidden}.ff-stock-hub *{box-sizing:border-box}.ff-stock-banner{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;padding:20px;border-radius:20px;background:radial-gradient(circle at top right,rgba(255,255,255,.18),transparent 34%),linear-gradient(135deg,var(--ffs-header-start),var(--ffs-header-end));color:var(--ffs-hero-text);margin-bottom:14px}.ff-stock-banner.client{background:radial-gradient(circle at top right,rgba(255,255,255,.16),transparent 34%),linear-gradient(135deg,var(--ffs-header-start),var(--ffs-header-end))}.ff-stock-banner h4{margin:3px 0 7px;color:var(--ffs-hero-text)!important;font-size:23px}.ff-stock-banner p{margin:0;color:var(--ffs-hero-muted)!important;line-height:1.5}.ff-stock-eyebrow{font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase;color:var(--ffs-hero-muted)!important}.ff-stock-live{display:inline-flex;color:var(--ffs-hero-text)!important;white-space:nowrap;padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);font-size:11px;font-weight:800}.ff-stock-state{border:1px solid var(--ffs-line);border-radius:16px;background:#fff}.ff-stock-loading,.ff-stock-empty,.ff-stock-error{padding:28px;text-align:center;color:var(--ffs-muted)}.ff-stock-error{color:var(--ffs-risk)}.ff-stock-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:14px 0}.ff-stock-kpi{padding:14px;border:1px solid var(--ffs-line);border-radius:16px;background:#fff;min-width:0}.ff-stock-kpi span{display:block;font-size:10px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;color:var(--ffs-muted)}.ff-stock-kpi strong{display:block;margin-top:5px;font-size:22px;line-height:1.1;overflow:hidden;text-overflow:ellipsis}.ff-stock-kpi small{display:block;margin-top:5px;color:var(--ffs-muted);line-height:1.35}.ff-stock-toolbar{display:grid;grid-template-columns:minmax(145px,.52fr) minmax(180px,.68fr) minmax(220px,1fr) auto;gap:10px;align-items:end;padding:14px;border:1px solid var(--ffs-line);border-radius:16px;background:var(--ffs-soft);margin-bottom:12px}.ff-stock-toolbar label,.ff-stock-inline-search{display:flex;flex-direction:column;gap:5px;font-size:11px;font-weight:800;color:var(--ffs-muted)}.ff-stock-toolbar input,.ff-stock-toolbar select,.ff-stock-inline-search input{width:100%;border:1px solid #cbd5e1;border-radius:11px;padding:10px 11px;background:#fff;color:var(--ffs-ink)}.ff-stock-meta{font-size:11px;color:var(--ffs-muted);padding-bottom:10px}.ff-stock-stores{display:grid;gap:12px}.ff-stock-store{border:1px solid var(--ffs-line);border-radius:18px;background:#fff;overflow:hidden}.ff-stock-store[hidden]{display:none}.ff-stock-store summary{list-style:none;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:15px 16px;cursor:pointer}.ff-stock-store summary::-webkit-details-marker{display:none}.ff-stock-store-title strong{display:block;font-size:15px}.ff-stock-store-title span{display:block;margin-top:4px;color:var(--ffs-muted);font-size:11px}.ff-stock-store-pulse{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.ff-stock-insignia-chip{display:inline-flex;align-items:center;margin-right:6px;border-radius:999px;padding:4px 8px;background:#e0f2fe;color:#075985;font-size:10px;font-weight:900}.ff-stock-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 8px;background:#f1f5f9;color:#475569;font-size:10px;font-weight:900}.ff-stock-chip.zero,.ff-stock-chip.negative{background:#fee2e2;color:#991b1b}.ff-stock-chip.low{background:#fef3c7;color:#92400e}.ff-stock-products{border-top:1px solid var(--ffs-line);padding:8px 14px 14px}.ff-stock-product{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;padding:10px 2px;border-bottom:1px solid #eef2f7}.ff-stock-product:last-child{border-bottom:0}.ff-stock-product[hidden]{display:none}.ff-stock-product strong{display:block;font-size:13px}.ff-stock-product small{display:block;margin-top:3px;color:var(--ffs-muted)}.ff-stock-value{min-width:70px;text-align:center;border-radius:12px;padding:8px 10px;background:#ecfdf5;color:#065f46;font-weight:900}.ff-stock-value.zero,.ff-stock-value.negative{background:#fee2e2;color:#991b1b}.ff-stock-value.low{background:#fef3c7;color:#92400e}.ff-stock-delivery{display:flex;justify-content:space-between;gap:14px;align-items:center;padding:14px 16px;border-radius:16px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}.ff-stock-delivery strong{display:block}.ff-stock-delivery span{font-size:11px}.ff-stock-progress{width:min(260px,38vw);height:9px;border-radius:999px;background:#d1fae5;overflow:hidden}.ff-stock-progress i{display:block;height:100%;background:#059669;border-radius:999px}.ff-stock-client-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ff-stock-section{border:1px solid var(--ffs-line);border-radius:18px;background:#fff;padding:15px;margin-top:12px}.ff-stock-section-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-end;margin-bottom:10px}.ff-stock-section h5{margin:0;font-size:16px}.ff-stock-section p{margin:4px 0 0;color:var(--ffs-muted);font-size:11px}.ff-stock-batch{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:11px 0;border-bottom:1px solid #eef2f7}.ff-stock-batch:last-child{border-bottom:0}.ff-stock-batch strong{display:block}.ff-stock-batch small{display:block;color:var(--ffs-muted);margin-top:3px}.ff-stock-batch-score{text-align:right;font-weight:900}.ff-stock-risk-store{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:9px 0;border-bottom:1px solid #eef2f7}.ff-stock-risk-store:last-child{border-bottom:0}.ff-stock-risk-store span{font-size:11px;color:var(--ffs-muted)}.ff-stock-risk-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.ff-stock-risk{border:1px solid #eef2f7;border-radius:13px;padding:11px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px}.ff-stock-risk[hidden]{display:none}.ff-stock-risk strong{display:block;font-size:12px}.ff-stock-risk small{display:block;color:var(--ffs-muted);margin-top:4px}.ff-stock-risk .ff-stock-value{align-self:center}.ff-stock-inline-search{min-width:230px}@media(max-width:980px){.ff-stock-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.ff-stock-client-grid{grid-template-columns:1fr}}@media(max-width:700px){.ff-stock-banner{flex-direction:column}.ff-stock-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.ff-stock-toolbar{grid-template-columns:1fr}.ff-stock-meta{padding-bottom:0}.ff-stock-store summary{grid-template-columns:1fr}.ff-stock-store-pulse{justify-content:flex-start}.ff-stock-risk-list{grid-template-columns:1fr}.ff-stock-section-head{align-items:flex-start;flex-direction:column}.ff-stock-inline-search{min-width:0;width:100%}.ff-stock-delivery{align-items:flex-start;flex-direction:column}.ff-stock-progress{width:100%}}
      /* 9DL: avoid clipping and keep stock filters legible on narrow WebViews. */
.ff-stock-filter-feedback{font-size:12px;color:#475569;margin:2px 0 14px;line-height:1.45}.ff-stock-store-title{min-width:0}.ff-stock-store-title strong{overflow-wrap:break-word}.ff-stock-store-title strong .ff-stock-insignia-chip{display:inline-flex;margin:0 8px 5px 0;vertical-align:middle}.ff-stock-store summary{min-width:0}
@media(max-width:700px){.ff-stock-store summary{padding:13px}.ff-stock-hub .ff-stock-toolbar{grid-template-columns:minmax(0,1fr)}.ff-stock-store-pulse{gap:5px}.ff-stock-store-pulse .ff-stock-chip{font-size:10px}.ff-stock-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style><?php }

    private static function teamScript(string $id): void { ?>
      <script>
      (function(){
        const root=document.getElementById(<?php echo wp_json_encode($id); ?>);if(!root)return;
        const stateEl=root.querySelector('[data-state]'),content=root.querySelector('[data-content]'),kpis=root.querySelector('[data-kpis]'),storesEl=root.querySelector('[data-stores]'),insigniaFilter=root.querySelector('[data-insignia-filter]'),storeFilter=root.querySelector('[data-store-filter]'),search=root.querySelector('[data-search]'),meta=root.querySelector('[data-meta]');
        const api=<?php echo wp_json_encode(rest_url('routespro/v1/stocks/')); ?>,nonce=root.dataset.nonce||'';let loading=false,lastScope='',lastLoadedAt=0,storeData=[],batchData=[],selectedInsignia='',selectedStore='',summaryData={};const filterFeedback=root.querySelector('[data-filter-feedback]'); // 9DL
        const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const fmt=n=>new Intl.NumberFormat('pt-PT',{maximumFractionDigits:2}).format(Number(n||0));
        const metric=(label,value,note)=>`<div class="ff-stock-kpi"><span>${esc(label)}</span><strong>${esc(value)}</strong><small>${esc(note||'')}</small></div>`;
        const notifyReact=()=>window.dispatchEvent(new CustomEvent('fieldflow:operational-module-refresh',{detail:{rootId:root.id,module:'stocks'}}));
        // 9DL: the native and React selectors have one owner; no reload/reset on each selection.
        function restoreSelect(select,value){if(!select)return '';const found=Array.from(select.options).some(o=>o.value===value);select.value=found?value:'';return select.value;}
        function rebuildStoreOptions(){
          const brand=String(insigniaFilter.value||'').toLowerCase();
          const eligible=storeData.filter(s=>!brand||String(s.insignia||'').toLowerCase()===brand);
          storeFilter.innerHTML='<option value="">Todas as lojas</option>'+eligible.map(s=>`<option value="${esc(s.store_key||s.location_id)}">${esc(s.name)}</option>`).join('');
          selectedStore=restoreSelect(storeFilter,selectedStore);
        }
        function applyFilters(){
          selectedInsignia=String(insigniaFilter.value||'');selectedStore=String(storeFilter.value||'');
          const brand=selectedInsignia.toLowerCase(),store=selectedStore,q=(search.value||'').trim().toLowerCase();
          let shown=0,productMatches=0;
          root.querySelectorAll('.ff-stock-store').forEach(card=>{
            const brandOk=!brand||String(card.dataset.insignia||'').toLowerCase()===brand;
            const storeOk=!store||card.dataset.storeKey===store;
            let matchCount=0;
            card.querySelectorAll('.ff-stock-product').forEach(row=>{const ok=!q||String(row.dataset.search||'').includes(q);row.hidden=!ok;if(ok)matchCount++;});
            card.hidden=!brandOk||!storeOk||(!!q&&matchCount===0);
            if(!card.hidden){shown++;productMatches+=matchCount;}
          });
          const visible=storeData.filter(s=>(!brand||String(s.insignia||'').toLowerCase()===brand)&&(!store||String(s.store_key||s.location_id)===store));
          const sum=(field)=>visible.reduce((n,s)=>n+Number(s[field]||0),0);
          kpis.innerHTML=[metric('Insígnias',new Set(visible.map(s=>s.insignia)).size,'No filtro atual'),metric('Lojas',visible.length,'No filtro atual'),metric('Produtos',sum('product_count'),'Linhas na seleção'),metric('Stock total',fmt(sum('stock_total')),'Snapshot publicado'),metric('Ruturas',sum('zero_count'),'Stock igual a zero'),metric('Stock baixo',sum('low_count'),'Até ao limiar'),metric('Negativos',sum('negative_count'),'Validar divergências')].join('');
          const batches=batchData.filter(b=>!brand||String(b.insignia||'').toLowerCase()===brand);
          meta.textContent=batches.map(b=>`${b.insignia} · ${b.snapshot_date}`).join(' | ');
          filterFeedback.textContent=(Number(summaryData.cross_insignia_rows||0)?'Atenção: '+summaryData.cross_insignia_rows+' linha(s) têm uma associação de insígnia incompatível e aguardam revisão no Backoffice. ':'')+(shown?`${shown} loja(s) visíveis${q?' · '+productMatches+' produto(s) encontrados':''}.`:(!visible.length?'Sem lojas associadas a esta insígnia na tua carteira.':'Nenhum produto corresponde à pesquisa.'));
          notifyReact();
        }
        function render(data){
          if(!data.available){stateEl.innerHTML=`<div class="ff-stock-empty">${esc(data.message||'Ainda não existem stocks publicados.')}</div>`;content.hidden=true;return;}
          stateEl.hidden=true;content.hidden=false;const s=data.summary||{};summaryData=s;
          kpis.innerHTML=[metric('Insígnias',s.insignias||0,'Snapshots ativos'),metric('Lojas',s.stores||0,s.scope_label||''),metric('Produtos',s.products||0,'Linhas disponíveis'),metric('Stock total',fmt(s.stock_total||0),'Valor agregado do ficheiro'),metric('Ruturas',s.zero_count||0,'Stock igual a zero'),metric('Stock baixo',s.low_count||0,`Até ${fmt(data.low_threshold||2)} unidades`),metric('Negativos',s.negative_count||0,'Validar divergências')].join('');
          const stores=Array.isArray(data.stores)?data.stores:[],insignias=Array.isArray(data.insignias)?data.insignias:[];storeData=stores;batchData=Array.isArray(data.batches)?data.batches:[];insigniaFilter.innerHTML='<option value="">Todas as insígnias</option>'+insignias.map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');selectedInsignia=restoreSelect(insigniaFilter,selectedInsignia);rebuildStoreOptions();
          meta.textContent=batchData.map(b=>`${b.insignia} · ${b.snapshot_date}`).join(' | ');
          storesEl.innerHTML=stores.length?stores.map((store,index)=>`<details class="ff-stock-store" data-location="${esc(store.location_id)}" data-store-key="${esc(store.store_key||store.location_id)}" data-insignia="${esc(store.insignia)}" ${index===0?'open':''}><summary><div class="ff-stock-store-title"><strong><span class="ff-stock-insignia-chip">${esc(store.insignia)}</span>${esc(store.name)}</strong><span>snapshot ${esc(store.snapshot_date)} · ${esc(store.product_count)} produtos</span></div><div class="ff-stock-store-pulse">${store.negative_count?`<span class="ff-stock-chip negative">${store.negative_count} negativos</span>`:''}${store.zero_count?`<span class="ff-stock-chip zero">${store.zero_count} ruturas</span>`:''}${store.low_count?`<span class="ff-stock-chip low">${store.low_count} baixos</span>`:''}<span class="ff-stock-chip">${fmt(store.stock_total)} total</span></div></summary><div class="ff-stock-products">${(store.products||[]).map(p=>{const hay=(p.name+' '+p.ean+' '+p.article_ref).toLowerCase();return `<div class="ff-stock-product" data-search="${esc(hay)}"><div><strong>${esc(p.name||'Produto')}</strong><small>${p.ean?'EAN '+esc(p.ean):''}${p.ean&&p.article_ref?' · ':''}${p.article_ref?'Artigo '+esc(p.article_ref):''}${p.unit?' · '+esc(p.unit):''}</small></div><span class="ff-stock-value ${esc(p.status)}">${fmt(p.stock)}</span></div>`;}).join('')}</div></details>`).join(''):'<div class="ff-stock-empty">Não existem lojas associadas à tua rota ou atribuições neste snapshot.</div>';
          insigniaFilter.onchange=()=>{selectedStore='';rebuildStoreOptions();applyFilters();};storeFilter.onchange=applyFilters;search.oninput=applyFilters;applyFilters(); // 9DL
          if(Array.isArray(data.batch_ids)&&data.batch_ids.length)fetch(api+'viewed',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({batch_ids:data.batch_ids})}).catch(()=>{});
          notifyReact();
        }
        async function load(detail={},force=false){const client=String(detail.client_id||root.dataset.clientId||''),project=String(detail.project_id||root.dataset.projectId||''),scope=client+'|'+project,now=Date.now();if(loading)return;if(!force&&scope===lastScope&&(now-lastLoadedAt)<15000)return;loading=true;lastScope=scope;stateEl.hidden=false;stateEl.innerHTML='<div class="ff-stock-loading">A atualizar stocks operacionais...</div>';try{const qs=new URLSearchParams();if(client)qs.set('client_id',client);if(project)qs.set('project_id',project);qs.set('_ff_refresh',String(now));const res=await fetch(api+'team?'+qs.toString(),{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce,'Cache-Control':'no-cache, no-store, max-age=0','Pragma':'no-cache'}});const data=await res.json();if(!res.ok)throw new Error(data.message||'Falha ao carregar stocks.');render(data);lastLoadedAt=Date.now();}catch(e){stateEl.hidden=false;stateEl.innerHTML=`<div class="ff-stock-error">${esc(e.message||'Falha ao carregar stocks.')}</div>`;content.hidden=true;notifyReact();}finally{loading=false;}}
        document.addEventListener('routespro:panel-open',e=>{if((e.detail?.panel||'')==='stocks')load(e.detail||{},true);});window.addEventListener('pageshow',()=>{if(root.closest('.rp-panel')?.classList.contains('active'))load({},true);});document.addEventListener('visibilitychange',()=>{if(!document.hidden&&root.closest('.rp-panel')?.classList.contains('active'))load({},true);});window.addEventListener('fieldflow:app-resume',()=>load({},true));
      })();
      </script>
    <?php }

    private static function clientScript(string $id): void { ?>
      <script>
      (function(){
        const root=document.getElementById(<?php echo wp_json_encode($id); ?>);if(!root)return;
        const stateEl=root.querySelector('[data-state]'),content=root.querySelector('[data-content]'),delivery=root.querySelector('[data-delivery]'),kpis=root.querySelector('[data-kpis]'),batchesEl=root.querySelector('[data-batches]'),storeRisks=root.querySelector('[data-store-risks]'),risksEl=root.querySelector('[data-risks]'),riskSearch=root.querySelector('[data-risk-search]');
        const notifyReact=()=>window.dispatchEvent(new CustomEvent('fieldflow:operational-module-refresh',{detail:{rootId:root.id,module:'stocks'}}));
        const api=<?php echo wp_json_encode(rest_url('routespro/v1/stocks/')); ?>,nonce=root.dataset.nonce||'';let loading=false,lastScope='';
        const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const fmt=n=>new Intl.NumberFormat('pt-PT',{maximumFractionDigits:2}).format(Number(n||0));
        const metric=(label,value,note)=>`<div class="ff-stock-kpi"><span>${esc(label)}</span><strong>${esc(value)}</strong><small>${esc(note||'')}</small></div>`;
        function scope(){const client=document.getElementById('rpcp-client'),project=document.getElementById('rpcp-project');return {client_id:client?.value||root.dataset.clientId||'',project_id:project?.value||root.dataset.projectId||''};}
        function filterRisks(){const q=(riskSearch.value||'').trim().toLowerCase();root.querySelectorAll('.ff-stock-risk').forEach(row=>row.hidden=!!q&&!(row.dataset.search||'').includes(q));notifyReact();}
        function render(data){
          if(!data.available){stateEl.innerHTML=`<div class="ff-stock-empty">${esc(data.message||'Ainda não existem stocks publicados.')}</div>`;content.hidden=true;return;}
          stateEl.hidden=true;content.hidden=false;const s=data.summary||{},aud=Number(s.audience_count||0),viewed=Number(s.viewed_count||0),pct=aud?Math.round((viewed/aud)*100):0;
          delivery.innerHTML=`<div><strong>${esc(data.message||'Informação publicada para a equipa.')}</strong><span>${viewed} de ${aud} utilizadores já consultaram o stock, ${s.pending_views||0} pendentes.</span></div><div class="ff-stock-progress" title="${pct}% consultado"><i style="width:${Math.max(0,Math.min(100,pct))}%"></i></div>`;
          kpis.innerHTML=[metric('Insígnias',s.insignias||0,'Snapshots ativos'),metric('Lojas fonte',s.stores||0,`${s.matched_locations||0} associadas`),metric('Produtos',s.products||0,'Referências únicas'),metric('Stock total',fmt(s.stock_total||0),'Agregado publicado'),metric('Ruturas',s.zero_count||0,'Stock igual a zero'),metric('Stock baixo',s.low_count||0,`Até ${fmt(data.low_threshold||2)} unidades`),metric('Negativos',s.negative_count||0,'Divergências a validar')].join('');
          const batches=Array.isArray(data.batches)?data.batches:[];batchesEl.innerHTML=batches.map(b=>{const cov=b.store_count?Math.round((b.matched_store_count/b.store_count)*100):0;return `<div class="ff-stock-batch"><div><strong>${esc(b.insignia)} · ${esc(b.snapshot_date)}</strong><small>${esc(b.project_name||'')} · ${esc(b.source_filename||'')} · ${esc(b.product_count)} produtos</small></div><div class="ff-stock-batch-score">${cov}%<small>${esc(b.viewed_users||0)} leituras</small></div></div>`;}).join('')||'<div class="ff-stock-empty">Sem snapshots ativos.</div>';
          const stores=Array.isArray(data.store_risks)?data.store_risks:[];storeRisks.innerHTML=stores.slice(0,12).map(r=>`<div class="ff-stock-risk-store"><div><strong><span class="ff-stock-insignia-chip">${esc(r.insignia||'')}</span>${esc(r.store_name)}</strong><span>${esc(r.product_count)} produtos no snapshot</span></div><div>${r.negative_count?`<span class="ff-stock-chip negative">${r.negative_count} neg.</span> `:''}${r.zero_count?`<span class="ff-stock-chip zero">${r.zero_count} zero</span> `:''}${r.low_count?`<span class="ff-stock-chip low">${r.low_count} baixos</span>`:''}</div></div>`).join('')||'<div class="ff-stock-empty">Sem lojas prioritárias.</div>';
          const risks=Array.isArray(data.risks)?data.risks:[];risksEl.innerHTML=risks.map(r=>{const hay=(r.store_name+' '+r.product_name+' '+r.ean+' '+r.article_ref).toLowerCase();return `<article class="ff-stock-risk" data-search="${esc(hay)}"><div><strong>${esc(r.product_name||'Produto')}</strong><small>${esc(r.store_name||'Loja')} · ${esc(r.insignia||'')} · ${r.ean?'EAN '+esc(r.ean):'Artigo '+esc(r.article_ref||'n.d.')}</small></div><span class="ff-stock-value ${esc(r.status)}">${fmt(r.stock_total)}</span></article>`;}).join('')||'<div class="ff-stock-empty">Não existem produtos abaixo do limiar.</div>';riskSearch.oninput=filterRisks;
          filterRisks();
        }
        async function load(){const sc=scope(),scopeKey=sc.client_id+'|'+sc.project_id;if(loading)return;loading=true;lastScope=scopeKey;stateEl.hidden=false;stateEl.innerHTML='<div class="ff-stock-loading">A carregar o último snapshot...</div>';try{const qs=new URLSearchParams();if(sc.client_id)qs.set('client_id',sc.client_id);if(sc.project_id)qs.set('project_id',sc.project_id);qs.set('_ff_refresh',String(Date.now()));const res=await fetch(api+'client?'+qs.toString(),{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce,'Cache-Control':'no-cache, no-store, max-age=0','Pragma':'no-cache'}});const data=await res.json();if(!res.ok)throw new Error(data.message||'Falha ao carregar stocks.');render(data);}catch(e){stateEl.hidden=false;stateEl.innerHTML=`<div class="ff-stock-error">${esc(e.message||'Falha ao carregar stocks.')}</div>`;content.hidden=true;notifyReact();}finally{loading=false;}}
        document.addEventListener('routespro:panel-open',e=>{if((e.detail?.panel||'')==='stocks'){lastScope='';load();}});
        document.getElementById('rpcp-client')?.addEventListener('change',()=>{lastScope='';if(root.closest('.rp-panel')?.classList.contains('active'))load();});document.getElementById('rpcp-project')?.addEventListener('change',()=>{lastScope='';if(root.closest('.rp-panel')?.classList.contains('active'))load();});
      })();
      </script>
    <?php }
}
