<?php
namespace RoutesPro\Front;

if (!defined('ABSPATH')) exit;

class ImplementationsShortcodeRenderer {
    public static function team(array $atts = []): string {
        return self::render($atts, 'team');
    }

    public static function client(array $atts = []): string {
        return self::render($atts, 'client');
    }

    private static function render(array $atts, string $mode): string {
        if (!is_user_logged_in()) return '<p>Por favor, inicia sessão.</p>';
        $id = function_exists('wp_unique_id') ? wp_unique_id('ff-implementations-') : 'ff-implementations-' . wp_rand(1000, 9999);
        $clientId = absint($atts['client_id'] ?? ($_GET['client_id'] ?? 0));
        $projectId = absint($atts['project_id'] ?? ($_GET['project_id'] ?? 0));
        ob_start();
        self::styles();
        ?>
        <div class="ff-implementations ff-operational-surface ff-operational-surface-implementations" id="<?php echo esc_attr($id); ?>" data-mode="<?php echo esc_attr($mode); ?>" data-client-id="<?php echo (int)$clientId; ?>" data-project-id="<?php echo (int)$projectId; ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">
          <?php if ($mode === 'team') echo ReactFrontFoundation::operationalModuleMount($id, 'implementations', 'Implementações'); ?>
          <div class="ffi-front-head">
            <div><span class="ffi-front-kicker">Material e execução</span><h3>Implementações</h3><p><?php echo $mode==='client'?'Visão completa das implementações publicadas por campanha.':'Informação filtrada pelas lojas atribuídas ao utilizador.'; ?></p></div>
            <button type="button" class="ffi-front-refresh" data-refresh>Atualizar</button>
          </div>
          <div class="ffi-front-metrics" data-metrics></div>
          <div class="ffi-front-filters" data-filters>
            <button type="button" class="active" data-filter="all">Todas</button>
            <button type="button" data-filter="implemented">Implementadas</button>
            <button type="button" data-filter="blocked">Bloqueadas</button>
            <button type="button" data-filter="order_pending">Pedido em falta</button>
            <button type="button" data-filter="awaiting">A aguardar</button>
            <button type="button" data-filter="scheduled">Agendadas</button>
            <button type="button" data-filter="no_feedback">Sem feedback</button>
          </div>
          <div class="ffi-front-status" data-status>A carregar implementações...</div>
          <div class="ffi-front-list" data-list></div>
        </div>
        <?php
        self::script($id);
        return ob_get_clean();
    }

    private static function styles(): void { ?>
<style>
.ff-implementations{
  --ffi-primary:var(--ff-primary,var(--rp-primary,#2563eb));
  --ffi-accent:var(--ff-accent,var(--rp-accent,#06b6d4));
  --ffi-canvas:var(--ff-canvas,#f8fbff);
  --ffi-surface:var(--ff-surface,#ffffff);
  --ffi-text:var(--ff-text,#0f172a);
  --ffi-heading:var(--ff-heading,#0f172a);
  --ffi-muted:var(--ff-muted,#64748b);
  --ffi-label:var(--ff-label,#475569);
  --ffi-border:var(--ff-border,#dce7f2);
  --ffi-header-start:var(--ff-header-start,#0f172a);
  --ffi-header-end:var(--ff-header-end,#1d4ed8);
  --ffi-header-title:var(--ff-header-title-text,var(--ff-hero-text,#ffffff));
  --ffi-header-subtitle:var(--ff-header-body-text,var(--ff-hero-muted,#dbeafe));
  --ffi-button-text:var(--ff-button-text,#ffffff);
  --ffi-button-hover-text:var(--ff-button-hover-text,#ffffff);
  --ffi-badge-text:var(--ff-badge-text,#0f172a);
  font-family:var(--ff-font,inherit);
  background:linear-gradient(180deg,color-mix(in srgb,var(--ffi-canvas) 76%,var(--ffi-surface)),var(--ffi-surface));
  border:1px solid var(--ffi-border);
  border-radius:var(--ff-radius,24px);
  padding:calc(18px * var(--ff-density,1));
  color:var(--ffi-text);
  max-width:100%;
  overflow-x:hidden;
}
.ff-implementations *{box-sizing:border-box}
.ffi-front-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
.ffi-front-head h3{margin:3px 0 5px;font-family:var(--ff-font-headings,inherit);font-size:24px;line-height:1.12;color:var(--ffi-heading)!important}
.ffi-front-head p{margin:0;color:var(--ffi-muted)!important;line-height:1.5}
.ffi-front-kicker{display:block;color:var(--ffi-label)!important;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase}
.ffi-front-refresh{border:1px solid var(--ffi-primary);border-radius:var(--ff-button-radius,999px);background:var(--ffi-primary);color:var(--ffi-button-text)!important;padding:9px 14px;font-weight:800;cursor:pointer;transition:transform .18s ease,filter .18s ease,box-shadow .18s ease}
.ffi-front-refresh:hover,.ffi-front-refresh:focus-visible{color:var(--ffi-button-hover-text)!important;filter:brightness(.94);transform:translateY(-1px);box-shadow:0 8px 20px color-mix(in srgb,var(--ffi-primary) 24%,transparent);outline:none}
.ffi-front-metrics{display:grid;grid-template-columns:repeat(5,minmax(150px,1fr));gap:9px;margin:16px 0}
.ffi-front-metric{border:1px solid var(--ffi-border);border-radius:calc(var(--ff-radius,22px) * .68);background:var(--ffi-surface);padding:13px;box-shadow:0 7px 20px rgba(15,23,42,.035)}
.ffi-front-metric span,.ffi-front-metric small{display:block;color:var(--ffi-muted)!important}
.ffi-front-metric span{display:block;font-size:11px;font-weight:700;letter-spacing:.005em;white-space:normal;overflow-wrap:normal;word-break:keep-all}.ffi-front-metric strong{display:block;font-size:23px;margin:3px 0;color:var(--ffi-heading)!important}
.ffi-front-filters{display:flex;gap:7px;overflow:auto;padding-bottom:6px;scrollbar-width:thin}
.ffi-front-filters button{border:1px solid var(--ffi-border);border-radius:999px;background:var(--ffi-surface);color:var(--ffi-text)!important;padding:8px 12px;font-weight:800;white-space:nowrap;cursor:pointer;transition:all .18s ease}
.ffi-front-filters button:hover,.ffi-front-filters button:focus-visible{border-color:var(--ffi-primary);color:var(--ffi-primary)!important;outline:none}
.ffi-front-filters button.active{background:var(--ffi-primary);border-color:var(--ffi-primary);color:var(--ffi-button-text)!important;box-shadow:0 8px 18px color-mix(in srgb,var(--ffi-primary) 20%,transparent)}
.ffi-front-status{margin:12px 0;color:var(--ffi-muted)!important}
.ffi-front-list{display:grid;gap:16px}
.ffi-front-document{border:1px solid var(--ffi-border);border-radius:var(--ff-radius,20px);background:var(--ffi-surface);overflow:hidden;box-shadow:var(--ff-shadow,0 16px 34px rgba(15,23,42,.06))}
.ffi-front-document-head{position:relative;isolation:isolate;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;padding:20px;background:radial-gradient(circle at top right,rgba(255,255,255,.16),transparent 34%),linear-gradient(135deg,var(--ffi-header-start),var(--ffi-header-end));color:var(--ffi-header-title)!important}
.ffi-front-document-head:after{content:"";position:absolute;inset:auto 0 0;height:1px;background:rgba(255,255,255,.16);pointer-events:none}
.ff-implementations .ffi-front-document-head h4{margin:5px 0 6px;font-family:var(--ff-font-headings,inherit);font-size:clamp(20px,2vw,25px);line-height:1.12;letter-spacing:-.02em;color:var(--ffi-header-title)!important}
.ff-implementations .ffi-front-document-head p,.ff-implementations .ffi-front-document-head small{margin:0;color:var(--ffi-header-subtitle)!important;line-height:1.45}
.ff-implementations .ffi-front-document-head>small{align-self:start;text-align:right;white-space:nowrap}
.ffi-front-version{display:inline-flex;border:1px solid color-mix(in srgb,var(--ffi-header-title) 32%,transparent);border-radius:999px;background:color-mix(in srgb,var(--ffi-header-title) 14%,transparent);color:var(--ffi-header-title)!important;padding:6px 9px;font-size:11px;font-weight:900}
.ffi-front-notes{padding:16px 20px;border-bottom:1px solid var(--ffi-border);background:color-mix(in srgb,var(--ffi-canvas) 72%,var(--ffi-surface));line-height:1.58;color:var(--ffi-text)!important}
.ffi-front-doc-metrics{display:flex;gap:8px;flex-wrap:wrap;margin-top:11px}
.ffi-front-doc-metrics span{display:inline-flex;border:1px solid color-mix(in srgb,var(--ffi-header-title) 22%,transparent);border-radius:999px;background:color-mix(in srgb,var(--ffi-header-title) 12%,transparent);color:var(--ffi-header-title)!important;padding:5px 8px;font-size:11px;font-weight:800}
.ffi-front-section{padding:18px;border-top:1px solid var(--ffi-border)}
.ffi-front-section:first-of-type{border-top:0}
.ffi-front-section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:12px}
.ffi-front-section-head h5{margin:0 0 4px;font-family:var(--ff-font-headings,inherit);font-size:17px;color:var(--ffi-heading)!important}
.ffi-front-section-head p{margin:0;color:var(--ffi-muted)!important}
.ffi-front-section-head b{border:1px solid color-mix(in srgb,var(--ffi-primary) 20%,var(--ffi-border));border-radius:999px;background:color-mix(in srgb,var(--ffi-primary) 10%,var(--ffi-surface));color:var(--ffi-primary)!important;padding:5px 9px;white-space:nowrap}
.ffi-front-items{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.ffi-front-item{border:1px solid var(--ffi-border);border-radius:calc(var(--ff-radius,22px) * .78);padding:14px;background:color-mix(in srgb,var(--ffi-canvas) 38%,var(--ffi-surface));color:var(--ffi-text)}
.ffi-front-item.is-hidden{display:none}
.ffi-front-item-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
.ffi-front-item h6{font-family:var(--ff-font-headings,inherit);font-size:16px;margin:3px 0 4px;color:var(--ffi-heading)!important}
.ffi-front-code,.ffi-front-location{display:block;color:var(--ffi-muted)!important;font-size:11px}
.ffi-front-state{display:inline-flex;border-radius:999px;padding:6px 9px;font-size:10px;font-weight:900;white-space:nowrap;background:#f1f5f9;color:#475569!important}
.ffi-front-state.implemented{background:#dcfce7;color:#166534!important}
.ffi-front-state.stock_blocked,.ffi-front-state.order_pending{background:#fee2e2;color:#991b1b!important}
.ffi-front-state.awaiting,.ffi-front-state.pending{background:#fef3c7;color:#92400e!important}
.ffi-front-state.scheduled,.ffi-front-state.sent{background:#dbeafe;color:#1d4ed8!important}
.ffi-front-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px}
.ffi-front-details div{border:1px solid color-mix(in srgb,var(--ffi-border) 74%,transparent);border-radius:var(--ff-input-radius,11px);background:color-mix(in srgb,var(--ffi-canvas) 66%,var(--ffi-surface));padding:9px}
.ffi-front-details span{display:block;color:var(--ffi-label)!important;font-size:10px;text-transform:uppercase;letter-spacing:.05em}
.ffi-front-details strong{display:block;margin-top:3px;font-size:13px;color:var(--ffi-heading)!important}
.ffi-front-observation{margin:10px 0 0;padding:10px;border-left:3px solid var(--ffi-accent);background:color-mix(in srgb,var(--ffi-canvas) 62%,var(--ffi-surface));border-radius:0 var(--ff-input-radius,10px) var(--ff-input-radius,10px) 0;color:var(--ffi-text)!important;line-height:1.45}
.ffi-front-empty{padding:24px;border:1px dashed var(--ffi-border);border-radius:calc(var(--ff-radius,22px) * .72);text-align:center;color:var(--ffi-muted)!important;background:var(--ffi-surface)}
.ffi-front-section.is-empty{display:none}
@media(max-width:900px){.ffi-front-metrics{grid-template-columns:repeat(3,minmax(116px,1fr))}.ffi-front-items{grid-template-columns:1fr}}
@media(max-width:620px){.ff-implementations{padding:14px;border-radius:18px}.ffi-front-head,.ffi-front-section-head{flex-direction:column}.ffi-front-document-head{grid-template-columns:1fr;padding:17px}.ff-implementations .ffi-front-document-head>small{text-align:left;white-space:normal}.ffi-front-metrics{grid-template-columns:repeat(2,minmax(140px,1fr))}.ffi-front-details{grid-template-columns:1fr}.ffi-front-refresh{align-self:flex-start}}
</style><?php }

    private static function script(string $id): void { ?>
<script>
(function(){
 const root=document.getElementById(<?php echo wp_json_encode($id); ?>);if(!root)return;
 const api=<?php echo wp_json_encode(rest_url('routespro/v1/')); ?>,nonce=root.dataset.nonce||'',mode=root.dataset.mode||'team';
 const list=root.querySelector('[data-list]'),status=root.querySelector('[data-status]'),metrics=root.querySelector('[data-metrics]');let documents=[],activeFilter='all';
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
 const fmtDate=v=>{if(!v)return'';const d=new Date(String(v).slice(0,10)+'T12:00:00');return Number.isNaN(d.getTime())?String(v):new Intl.DateTimeFormat('pt-PT').format(d)};
 const parentPortal=root.closest('.rp-client-portal');
 function currentScope(){let clientId=root.dataset.clientId||'',projectId=root.dataset.projectId||'';if(mode==='client'&&parentPortal){clientId=parentPortal.querySelector('#rpcp-client')?.value||clientId;projectId=parentPortal.querySelector('#rpcp-project')?.value||projectId;root.dataset.clientId=clientId;root.dataset.projectId=projectId;}return{client_id:clientId,project_id:projectId};}
 async function req(url){const res=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'X-WP-Nonce':nonce}});const text=await res.text();let data={};try{data=text?JSON.parse(text):{}}catch(e){data={message:text}}if(!res.ok)throw new Error(data.message||'Não foi possível carregar as implementações.');return data;}
 function overallMetrics(rows){const out={total:0,implemented:0,blocked:0,pending:0,materials_missing:0};rows.forEach(d=>{const m=d.metrics||{};Object.keys(out).forEach(k=>out[k]+=Number(m[k]||0));});return out;}
 function renderMetrics(){const m=overallMetrics(documents);metrics.innerHTML=[['Implementações',m.total],['Implementadas',m.implemented],['Pendentes',m.pending],['Bloqueadas',m.blocked],['Material em falta',m.materials_missing]].map(x=>`<div class="ffi-front-metric"><span>${x[0]}</span><strong>${x[1]}</strong><small>Âmbito atual</small></div>`).join('');}
 function statusMatches(key){if(activeFilter==='all')return true;if(activeFilter==='blocked')return ['stock_blocked','order_pending'].includes(key);return key===activeFilter;}
 function itemHtml(item){const date=item.planned_date?fmtDate(item.planned_date):(item.planned_date_text||'Sem data');const materialParts=[];if(Number(item.materials_proposed||0))materialParts.push(`${item.materials_proposed} proposto(s)`);if(Number(item.materials_implemented||0))materialParts.push(`${item.materials_implemented} implementado(s)`);if(Number(item.materials_missing||0))materialParts.push(`${item.materials_missing} em falta`);return `<article class="ffi-front-item" data-item data-status="${esc(item.status_key||'pending')}"><div class="ffi-front-item-head"><div><span class="ffi-front-code">${item.external_store_code?`Código ${esc(item.external_store_code)}`:'Sem código externo'}</span><h6>${esc(item.location_name||item.external_store_name||'Loja')}</h6><span class="ffi-front-location">${esc(item.external_store_name||'')}${item.location_city?` · ${esc(item.location_city)}`:''}</span></div><span class="ffi-front-state ${esc(item.status_key||'pending')}">${esc(item.status_label||'Por atualizar')}</span></div><div class="ffi-front-details"><div><span>Implementação</span><strong>${esc(item.implementation_type||'Material')}</strong></div><div><span>Previsão</span><strong>${esc(date)}</strong></div>${item.sales_rep?`<div><span>Sales Rep</span><strong>${esc(item.sales_rep)}</strong></div>`:''}${materialParts.length?`<div><span>Materiais</span><strong>${esc(materialParts.join(' · '))}</strong></div>`:''}</div>${item.observations?`<p class="ffi-front-observation">${esc(item.observations)}</p>`:''}</article>`;}
 function sectionHtml(section){const items=Array.isArray(section.items)?section.items:[];return `<section class="ffi-front-section" data-section><div class="ffi-front-section-head"><div><h5>${esc(section.title||'Implementações')}</h5>${section.summary?`<p>${esc(section.summary)}</p>`:''}</div><b data-visible-count>${items.length} lojas</b></div><div class="ffi-front-items">${items.map(itemHtml).join('')}</div></section>`;}
 function docHtml(doc){const m=doc.metrics||{};const validity=(doc.valid_from||doc.valid_to)?`${doc.valid_from?fmtDate(doc.valid_from):'Início livre'} a ${doc.valid_to?fmtDate(doc.valid_to):'sem fim'}`:'Sem janela de validade';return `<article class="ffi-front-document"><header class="ffi-front-document-head"><div><span class="ffi-front-version">v${esc(doc.version_no||1)}</span><h4>${esc(doc.title||'Implementações')}</h4><p>${esc(doc.project_name||'Campanha')} · ${esc(validity)}</p><div class="ffi-front-doc-metrics"><span>${esc(m.total||0)} lojas</span><span>${esc(m.implemented||0)} implementadas</span><span>${esc(m.blocked||0)} bloqueadas</span></div></div><small>${doc.published_at?`Publicado em ${esc(fmtDate(doc.published_at))}`:''}</small></header>${doc.notes?`<div class="ffi-front-notes">${esc(doc.notes).replace(/\n/g,'<br>')}</div>`:''}${(doc.sections||[]).map(sectionHtml).join('')}</article>`;}
 function applyFilter(){root.querySelectorAll('[data-item]').forEach(item=>item.classList.toggle('is-hidden',!statusMatches(item.dataset.status||'pending')));root.querySelectorAll('[data-section]').forEach(section=>{const visible=section.querySelectorAll('[data-item]:not(.is-hidden)').length;section.classList.toggle('is-empty',visible===0);const count=section.querySelector('[data-visible-count]');if(count)count.textContent=`${visible} loja${visible===1?'':'s'}`;});root.querySelectorAll('[data-filter]').forEach(btn=>btn.classList.toggle('active',btn.dataset.filter===activeFilter));}
 function render(){renderMetrics();list.innerHTML=documents.length?documents.map(docHtml).join(''):'<div class="ffi-front-empty">Não existem implementações publicadas para este âmbito.</div>';status.textContent=documents.length?`${documents.length} documento(s) disponível(eis).`:'Sem implementações publicadas.';applyFilter();}
 async function load(){try{status.textContent='A atualizar implementações...';const s=currentScope(),params=new URLSearchParams({client_id:s.client_id||'',project_id:s.project_id||''});const data=await req(api+(mode==='client'?'implementations/client?':'implementations/mine?')+params.toString());documents=Array.isArray(data.documents)?data.documents:[];render();}catch(e){status.textContent=e.message||'Não foi possível atualizar.';list.innerHTML='<div class="ffi-front-empty">Tenta novamente dentro de momentos.</div>';}}
 root.querySelector('[data-refresh]')?.addEventListener('click',load);root.querySelectorAll('[data-filter]').forEach(btn=>btn.addEventListener('click',()=>{activeFilter=btn.dataset.filter||'all';applyFilter();}));root.addEventListener('routespro:panel-open',load);document.addEventListener('routespro:scope-change',event=>{const d=event.detail||{};if(typeof d.client_id!=='undefined')root.dataset.clientId=d.client_id||'';if(typeof d.project_id!=='undefined')root.dataset.projectId=d.project_id||'';load();});if(mode==='client'&&parentPortal){parentPortal.querySelector('#rpcp-client')?.addEventListener('change',()=>setTimeout(load,30));parentPortal.querySelector('#rpcp-project')?.addEventListener('change',()=>setTimeout(load,30));}
 setTimeout(load,60);
})();
</script><?php }
}
