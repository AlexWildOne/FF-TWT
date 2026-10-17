/* FieldFlow 2.2.638 | fieldflow-operative-dock-real-runtime-audit-9dd-v1
 * Diagnostic runs only when the PHP opt-in panel exists. No network or storage.
 * No automatic change to navigation. Manual temporary test restores original styles.
 */
(function(){
 'use strict';
 const CONTRACT='fieldflow-operative-dock-real-runtime-audit-9dd-v1';
 const ID='ff-dock-audit-9dd';
 const events={windowScroll:0,documentScroll:0,touchStart:0,touchMove:0,wheel:0,lastEvent:'none',lastSource:'none',lastDeltaY:0};
 let booted=false,forced=null,prior=null,queued=false;
 const val=v=>Number.isFinite(Number(v))?Math.round(Number(v)):null;
 const label=node=>{
   if(!node||!node.tagName)return 'none';
   const name=String(node.className&&typeof node.className==='string'?node.className:'').trim().split(/\s+/).filter(Boolean).slice(0,3);
   return node.tagName.toLowerCase()+(name.length?'.'+name.join('.'):'');
 };
 const root=()=>{
   const all=Array.from(document.querySelectorAll('#routespro-app[data-fieldflow-portal="operative"]'));
   return all.find(el=>el.getBoundingClientRect().width>1)||all[0]||null;
 };
 const dock=el=>{
   if(!el)return null;
   const tabs=Array.from(el.querySelectorAll('.rp-tabs')).filter(n=>n.querySelector('.rp-tabbar'));
   return tabs.find(n=>n.getBoundingClientRect().width>1)||tabs[0]||null;
 };
 const host=el=>{
   let node=el?.querySelector('.rp-panel.active')||el;
   while(node&&node!==document.body){
     if(node.clientHeight>0&&node.scrollHeight>node.clientHeight+4){
       let y='';try{y=window.getComputedStyle(node).overflowY||'';}catch(_){}
       if(/^(auto|scroll|overlay)$/.test(y))return node;
     }
     node=node.parentElement;
   }
   return null;
 };
 function bottom(){
   if(!document.elementFromPoint)return [];
   return [25,70,110].map(offset=>{
     const hit=document.elementFromPoint(Math.max(1,window.innerWidth/2),Math.max(1,window.innerHeight-offset));
     return label(hit?.closest?.('.rp-tabs,.rp-tabbar,.ff-react-front-operational-nav,.ff-ope-command-deck')||hit);
   });
 }
 function snapshot(){
   const el=root(),bar=dock(el),css=bar?window.getComputedStyle(bar):null,rect=bar?.getBoundingClientRect(),scrollHost=host(el);
   return {
     version:el?.dataset.fieldflowPluginVersion||'2.2.638',contract:CONTRACT,jsExecuted:true,rootCount:document.querySelectorAll('#routespro-app').length,
     operativeRoot:!!el,tabbars:el?.querySelectorAll('.rp-tabbar').length||0,
     controller:el?.dataset.ffvDockControllerActive||'ausente',owner:el?.dataset.ffvDockAuthority||'ausente',
     dockState:el?.dataset.ffvDockState||'ausente',dockSource:el?.dataset.ffvDockSource||'ausente',
     dockScrollHost:el?.dataset.ffvDockScrollHost||'ausente',hiddenClass:!!bar?.classList.contains('ffv-dock-hidden'),
     dock9de:{delivery:el?.dataset.fieldflowDockDelivery9de||'absent',scriptTags:document.querySelectorAll('script[data-fieldflow-contract="fieldflow-operative-dock-bootstrap-recovery-9de-v1"]').length,
       runtime:window.FieldFlowOperativeDock9DE?.snapshot?.()||null},
     dockFound:!!bar,dockBox:rect?{top:val(rect.top),bottom:val(rect.bottom),height:val(rect.height)}:null,
     computed:css?{position:css.position,transform:css.transform,opacity:css.opacity,visibility:css.visibility,display:css.display}:null,
     forcedTest:!!forced,
     viewport:{width:val(window.innerWidth),height:val(window.innerHeight),mobile760:!!window.matchMedia?.('(max-width:760px)').matches},
     scroll:{window:val(window.scrollY),html:val(document.documentElement?.scrollTop),body:val(document.body?.scrollTop),
       internal:scrollHost?{element:label(scrollHost),top:val(scrollHost.scrollTop)}:null},
     bottomElements:bottom(),events:Object.assign({},events)
   };
 }
 function describe(s){return [
   'PHP '+s.version+' | JS ativo | raiz '+(s.operativeRoot?'SIM':'NAO')+' | barras '+s.tabbars,
   'Controlador: '+s.controller+' | estado: '+s.dockState+' | origem: '+s.dockSource,
   '9DE entrega: '+(s.dock9de?.delivery||'absent')+' | scripts: '+(s.dock9de?.scriptTags||0)+' | montado: '+(s.dock9de?.runtime?.mounted?'SIM':'NAO'),
   'Mobile <=760: '+(s.viewport.mobile760?'SIM':'NAO')+' | visor '+s.viewport.width+'x'+s.viewport.height,
   'Scroll pagina '+s.scroll.window+' | interno '+(s.scroll.internal?s.scroll.internal.element+':'+s.scroll.internal.top:'nao identificado'),
   'Eventos: janela '+s.events.windowScroll+', documento '+s.events.documentScroll+', toque '+s.events.touchMove,
   'Ultimo: '+s.events.lastEvent+' ('+s.events.lastSource+')',
   'Barra real: '+(s.dockFound?'SIM':'NAO')+' | classe oculto: '+(s.hiddenClass?'SIM':'NAO'),
   'Teste manual: '+(s.forcedTest?'OCULTO':'NORMAL')+' | rect '+(s.dockBox?s.dockBox.top+' a '+s.dockBox.bottom:'ausente'),
   'CSS: '+(s.computed?s.computed.position+' / '+s.computed.opacity+' / '+s.computed.visibility:'ausente'),
   'Ao fundo: '+s.bottomElements.join(' | ')
 ].join('\n');}
 function restore(){
   if(!forced)return;
   Object.keys(prior||{}).forEach(key=>{
     const previous=prior[key];
     if(previous.value)forced.style.setProperty(key,previous.value,previous.priority);
     else forced.style.removeProperty(key);
   });
   forced=null;prior=null;
 }
 function force(){
   if(forced){restore();return;}
   const el=dock(root());if(!el)return;
   prior={};
   ['transform','opacity','visibility','pointer-events','transition'].forEach(key=>{
     prior[key]={value:el.style.getPropertyValue(key),priority:el.style.getPropertyPriority(key)};
   });
   el.style.setProperty('transform','translate3d(0,calc(100% + 160px),0)','important');
   el.style.setProperty('opacity','0','important');
   el.style.setProperty('visibility','hidden','important');
   el.style.setProperty('pointer-events','none','important');
   el.style.setProperty('transition','none','important');
   forced=el;
 }
 function render(){
   const panel=document.getElementById(ID);if(!panel)return;
   const s=snapshot();
   const status=panel.querySelector('[data-ffdd-status]');if(status)status.textContent='JS ativo | '+(s.dockFound?'barra encontrada':'barra NAO encontrada');
   const text=panel.querySelector('[data-ffdd-output]');if(text)text.textContent=describe(s);
   const toggle=panel.querySelector('[data-ffdd-toggle]');if(toggle)toggle.textContent=forced?'Repor barra':'Testar recolha';
   panel.dataset.ffddScript=CONTRACT;
   window.FieldFlowDockAudit9DD={snapshot,contract:CONTRACT};
 }
 function schedule(){
   if(queued)return;queued=true;
   (window.requestAnimationFrame||function(fn){return window.setTimeout(fn,16);})(()=>{queued=false;render();});
 }
 function note(type,target,delta){
   events.lastEvent=type;events.lastSource=label(target);events.lastDeltaY=val(delta)||0;schedule();
 }
 function boot(){
   const panel=document.getElementById(ID);
   if(!panel||booted)return;booted=true;
   panel.addEventListener('click',event=>{
     const button=event.target.closest?.('button[data-ffdd-action]');if(!button)return;
     if(button.dataset.ffddAction==='toggle')force();
     if(button.dataset.ffddAction==='copy'){
       const content=JSON.stringify(snapshot(),null,2);
       if(navigator.clipboard?.writeText)navigator.clipboard.writeText(content).then(()=>{button.textContent='Copiado';}).catch(()=>{
         const pre=panel.querySelector('[data-ffdd-output]');if(pre)pre.textContent=content;
       });
       else{const pre=panel.querySelector('[data-ffdd-output]');if(pre)pre.textContent=content;}
     }
     render();
   });
   window.addEventListener('scroll',e=>{events.windowScroll++;note('scroll janela',e.target,0);},{passive:true});
   document.addEventListener('scroll',e=>{events.documentScroll++;note('scroll documento',e.target,0);},true);
   let touchY=null;
   document.addEventListener('touchstart',e=>{events.touchStart++;touchY=e.touches?.[0]?.clientY??null;note('touchstart',e.target,0);},{passive:true,capture:true});
   document.addEventListener('touchmove',e=>{
     events.touchMove++;const y=e.touches?.[0]?.clientY;const dy=typeof y==='number'&&typeof touchY==='number'?y-touchY:0;
     touchY=y;note('touchmove',e.target,dy);
   },{passive:true,capture:true});
   document.addEventListener('touchend',()=>{touchY=null;},{passive:true,capture:true});
   document.addEventListener('wheel',e=>{events.wheel++;note('wheel',e.target,e.deltaY);},{passive:true,capture:true});
   window.addEventListener('pagehide',restore);
   document.addEventListener('visibilitychange',()=>{if(document.hidden)restore();});
   panel.addEventListener('toggle',schedule,true);
   render();window.setInterval(()=>{if(panel.querySelector('details[open]'))render();},1250);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
 else boot();
})();
