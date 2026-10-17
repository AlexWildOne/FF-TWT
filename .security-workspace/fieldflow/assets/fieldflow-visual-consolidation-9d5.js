/* FieldFlow 2.2.630 | fieldflow-visual-experience-consolidation-9d5-v1
 * Progressive UX only. No REST calls, mutation of visit data, localStorage,
 * offline queue changes, or replacement of existing panel navigation.
 */
(function(){
  'use strict';
  const CONTRACT='fieldflow-visual-experience-consolidation-9d5-v1';
  const ROOTS='#routespro-client-portal,#routespro-app';
  const PHONE='(max-width:760px)';
  const reduceMotion=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)');
  const phone=()=>!window.matchMedia||window.matchMedia(PHONE).matches;
  const timeLabel=(raw)=>{
    const value=String(raw||'');
    return /^\d{4}-\d{2}-\d{2}$/.test(value)?value.slice(8,10)+'/'+value.slice(5,7):'';
  };
  function fitCurrentTab(tabbar){
    if(!phone()||!tabbar) return;
    const active=tabbar.querySelector('button.active');
    if(!active) return;
    const max=Math.max(0,tabbar.scrollWidth-tabbar.clientWidth);
    const left=Math.max(0,Math.min(max,active.offsetLeft-(tabbar.clientWidth-active.offsetWidth)/2));
    if(Math.abs(tabbar.scrollLeft-left)>10){
      if(reduceMotion&&reduceMotion.matches) tabbar.scrollLeft=left;
      else if(typeof tabbar.scrollTo==='function') tabbar.scrollTo({left,behavior:'smooth'});
      else tabbar.scrollLeft=left;
    }
  }
  function setupFilters(root){
    if(root.id!=='routespro-client-portal') return;
    const toolbar=root.querySelector('.rp-hero > .rp-toolbar');
    if(!toolbar||root.querySelector('.ffv-filter-toggle')) return;
    // The existing DOM and filter listeners remain the only source of truth.
    toolbar.id=toolbar.id||'ffv-filters-client-9d5';
    const button=document.createElement('button');
    button.type='button';
    button.className='ffv-filter-toggle';
    button.setAttribute('aria-expanded','false');
    button.setAttribute('aria-controls',toolbar.id);
    button.setAttribute('aria-label','Mostrar filtros e âmbito da consulta');
    button.innerHTML='<span>Filtros e âmbito <span class="ffv-filter-caret" aria-hidden="true">⌄</span></span><span class="ffv-filter-period" aria-hidden="true"></span>';
    toolbar.parentNode.insertBefore(button,toolbar);
    const summary=button.querySelector('.ffv-filter-period');
    const updateSummary=()=>{
      const from=timeLabel(root.querySelector('#rpcp-from')?.value);
      const to=timeLabel(root.querySelector('#rpcp-to')?.value);
      summary.textContent=from&&to?from+' a '+to:'';
    };
    ['#rpcp-from','#rpcp-to'].forEach(id=>{
      const input=root.querySelector(id);
      if(input){input.addEventListener('change',updateSummary);input.addEventListener('input',updateSummary);}
    });
    const apply=root.querySelector('#rpcp-refresh');
    if(apply) apply.addEventListener('click',()=>{
      root.classList.remove('ffv-filters-open');
      button.setAttribute('aria-expanded','false');
      button.setAttribute('aria-label','Mostrar filtros e âmbito da consulta');
      updateSummary();
    });
    button.addEventListener('click',()=>{
      const open=root.classList.toggle('ffv-filters-open');
      button.setAttribute('aria-expanded',String(open));
      button.setAttribute('aria-label',(open?'Ocultar':'Mostrar')+' filtros e âmbito da consulta');
      updateSummary();
    });
    updateSummary();
  }
  function setupDock(root){
    const dock=root.querySelector('.rp-tabs');
    const bar=dock&&dock.querySelector('.rp-tabbar');
    if(!dock||!bar) return;
    let lastY=Math.max(0,window.scrollY||0);
    let lastDirectionY=lastY;
    let animation=0;
    const show=()=>dock.classList.remove('ffv-dock-hidden');
    const measure=()=>{
      if(!root.isConnected) return;
      const height=Math.max(55,Math.ceil(bar.getBoundingClientRect().height||0));
      root.style.setProperty('--ffv-dock-height',height+'px');
    };
    const keyboard=()=>document.body?.classList.contains('ff-keyboard-open');
    function onScroll(){
      // 9D6 owns the Operative dock when its enhanced nested-scroll listener is active.
      if(root.id==='routespro-app'&&(root.dataset.ffvDockAuthority==='9d6'||root.dataset.ffvDockAuthority==='9d7'||root.dataset.ffvDockAuthority==='9d8')) return;
      if(animation) return;
      animation=window.requestAnimationFrame(()=>{
        animation=0;
        if(!phone()){show();lastY=Math.max(0,window.scrollY||0);return;}
        const now=Math.max(0,window.scrollY||0);
        const diff=now-lastY;
        lastY=now;
        if(Math.abs(diff)<7||keyboard()) return;
        if(diff<0||now<140){show();lastDirectionY=now;return;}
        if(now-lastDirectionY>18&&!dock.contains(document.activeElement)){
          dock.classList.add('ffv-dock-hidden');
          lastDirectionY=now;
        }
      });
    }
    bar.addEventListener('focusin',show);
    bar.addEventListener('pointerdown',show,{passive:true});
    bar.addEventListener('click',()=>{
      show();window.setTimeout(()=>fitCurrentTab(bar),0);
    });
    window.addEventListener('scroll',onScroll,{passive:true});
    window.addEventListener('resize',()=>{measure();if(!phone())show();},{passive:true});
    if(window.visualViewport) window.visualViewport.addEventListener('resize',measure,{passive:true});
    if(typeof ResizeObserver==='function'){
      const observer=new ResizeObserver(measure);
      observer.observe(bar);
    }
    document.addEventListener('visibilitychange',()=>{if(!document.hidden) show();});
    measure();fitCurrentTab(bar);
  }
  function enhance(root){
    if(!root||root.dataset.ffvVisual===CONTRACT) return;
    root.dataset.ffvVisual=CONTRACT;
    root.classList.add('ffv-visual-9d5');
    setupFilters(root);
    setupDock(root);
    root.classList.add('ffv-ready');
  }
  function boot(){document.querySelectorAll(ROOTS).forEach(enhance);}
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
  // Portals are normally server-rendered; support WebView navigation/re-render.
  document.addEventListener('fieldflow:responsive-ready',boot);
})();
