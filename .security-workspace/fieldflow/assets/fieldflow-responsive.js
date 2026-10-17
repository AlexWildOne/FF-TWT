/* FieldFlow shared responsive runtime, build 2.2.211 */
(function(){
  'use strict';

  const cfg=window.FieldFlowResponsive||{};
  const rootSelector=cfg.rootSelector||'.rp-premium-shell,.rp-client-premium,.routespro-dashboard,.rp-front-routes,.rp-front-commercial,.rp-report-quick,.rp-report-focus,.rp-team-mail,.rp-team-inbox,.rp-wrap,.ff-expenses,.ff-briefings,.ff-implementations,.ff-stock-hub,.ffp-front,.ffp-academy,.rp-form-ui,.routespro-form';
  const phoneMax=Number(cfg.phoneMax||767);
  const tabletMax=Number(cfg.tabletMax||1180);
  const docEl=document.documentElement;
  const body=()=>document.body;
  let viewportBaseline=Math.max(window.innerHeight||0,(window.visualViewport&&window.visualViewport.height)||0);
  let mutationTimer=0;

  function platform(){
    const ua=String(navigator.userAgent||'').toLowerCase();
    if(/ipad|iphone|ipod/.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1)) return 'ios';
    if(/android/.test(ua)) return 'android';
    return 'web';
  }

  function displayMode(){
    if(cfg.native||window.FieldFlowMobile&&window.FieldFlowMobile.native) return 'native';
    if(window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches) return 'standalone';
    if(window.navigator.standalone===true) return 'standalone';
    return 'browser';
  }

  function device(){
    const width=Math.min(window.innerWidth||9999,docEl.clientWidth||9999);
    if(width<=phoneMax) return 'phone';
    if(width<=tabletMax) return 'tablet';
    return 'desktop';
  }

  function syncEnvironment(){
    const currentDevice=device();
    const mode=displayMode();
    docEl.dataset.fieldflowPlatform=platform();
    docEl.dataset.fieldflowDevice=currentDevice;
    docEl.dataset.fieldflowDisplayMode=mode;
    docEl.style.setProperty('--ff-runtime-vh',`${Math.max(1,(window.visualViewport&&window.visualViewport.height)||window.innerHeight||1)}px`);
    const b=body();
    if(b){
      b.classList.toggle('ff-device-phone',currentDevice==='phone');
      b.classList.toggle('ff-device-tablet',currentDevice==='tablet');
      b.classList.toggle('ff-device-desktop',currentDevice==='desktop');
      const visualHeight=(window.visualViewport&&window.visualViewport.height)||window.innerHeight||viewportBaseline;
      const active=document.activeElement;
      const editable=!!(active&&active.matches&&active.matches('input:not([type="checkbox"]):not([type="radio"]),select,textarea,[contenteditable="true"]'));
      if(!editable) viewportBaseline=Math.max(visualHeight,window.innerHeight||0);
      const keyboardOpen=currentDevice!=='desktop'&&editable&&visualHeight<Math.max(1,viewportBaseline)*.86;
      b.classList.toggle('ff-keyboard-open',keyboardOpen);
    }
    document.querySelectorAll(rootSelector).forEach(root=>{
      root.dataset.ffDevice=currentDevice;
      root.dataset.ffDisplayMode=mode;
    });
  }

  function tableHeaders(table){
    return Array.from(table.querySelectorAll('thead th')).map(th=>(th.textContent||'').trim());
  }

  function updateScrollState(scroller){
    const max=Math.max(0,scroller.scrollWidth-scroller.clientWidth);
    scroller.dataset.scrollStart=scroller.scrollLeft<=2?'1':'0';
    scroller.dataset.scrollEnd=scroller.scrollLeft>=max-2?'1':'0';
  }

  function makeScroller(table){
    let scroller=table.closest('.rp-table-wrap,.rp-table-scroll,.ff-table-scroll,.ffb-table-wrap,.routespro-product-matrix,.routespro-order-table');
    if(!scroller&&table.parentElement){
      scroller=document.createElement('div');
      scroller.className='ff-table-scroll';
      table.parentElement.insertBefore(scroller,table);
      scroller.appendChild(table);
    }
    if(!scroller) return;
    if(!scroller.hasAttribute('tabindex')) scroller.tabIndex=0;
    if(!scroller.hasAttribute('role')) scroller.setAttribute('role','region');
    if(!scroller.hasAttribute('aria-label')) scroller.setAttribute('aria-label',table.getAttribute('aria-label')||'Tabela com deslocação horizontal');
    if(scroller.dataset.ffScrollBound!=='1'){
      scroller.dataset.ffScrollBound='1';
      scroller.addEventListener('scroll',()=>updateScrollState(scroller),{passive:true});
    }
    updateScrollState(scroller);
  }

  function enhanceTable(table){
    if(table.dataset.ffResponsive==='1'){
      makeScroller(table);
      return;
    }
    table.dataset.ffResponsive='1';
    table.classList.add('ff-responsive-table');
    const headers=tableHeaders(table);
    if(headers.length&&headers.length<=9){
      table.querySelectorAll('tbody tr').forEach(row=>{
        Array.from(row.children).forEach((cell,index)=>{
          if(cell.tagName==='TD'&&!cell.hasAttribute('colspan')&&!cell.dataset.label){
            cell.dataset.label=headers[index]||`Campo ${index+1}`;
          }
        });
      });
    }
    makeScroller(table);
  }

  function enhanceRoot(root){
    if(!(root instanceof Element)) return;
    root.classList.add('ff-responsive-runtime');
    root.dataset.ffRuntime=String(cfg.version||'1');
    root.querySelectorAll('table').forEach(enhanceTable);
    root.querySelectorAll('img').forEach(img=>{
      if(!img.hasAttribute('loading')&&!img.closest('.rp-hero,.rp-brandline')) img.loading='lazy';
      if(!img.hasAttribute('decoding')) img.decoding='async';
    });
  }

  function enhanceAll(scope){
    const base=scope&&scope.querySelectorAll?scope:document;
    if(base.matches&&base.matches(rootSelector)) enhanceRoot(base);
    base.querySelectorAll(rootSelector).forEach(enhanceRoot);
    syncEnvironment();
  }

  function scheduleEnhance(scope){
    window.clearTimeout(mutationTimer);
    mutationTimer=window.setTimeout(()=>enhanceAll(scope||document),40);
  }

  function boot(){
    enhanceAll(document);
    const observer=new MutationObserver(records=>{
      let relevant=false;
      for(const record of records){
        if(record.addedNodes&&record.addedNodes.length){relevant=true;break;}
      }
      if(relevant) scheduleEnhance(document);
    });
    observer.observe(document.body,{childList:true,subtree:true});

    window.addEventListener('resize',syncEnvironment,{passive:true});
    window.addEventListener('orientationchange',()=>window.setTimeout(()=>{viewportBaseline=Math.max(window.innerHeight||0,(window.visualViewport&&window.visualViewport.height)||0);syncEnvironment();},80),{passive:true});
    document.addEventListener('focusin',()=>window.setTimeout(syncEnvironment,50),true);
    document.addEventListener('focusout',()=>window.setTimeout(syncEnvironment,140),true);
    if(window.visualViewport){
      window.visualViewport.addEventListener('resize',syncEnvironment,{passive:true});
      window.visualViewport.addEventListener('scroll',syncEnvironment,{passive:true});
    }
    if('ResizeObserver' in window){
      const resizeObserver=new ResizeObserver(entries=>entries.forEach(entry=>{
        if(entry.target&&entry.target.matches&&entry.target.matches('.rp-table-wrap,.rp-table-scroll,.ff-table-scroll,.ffb-table-wrap,.routespro-product-matrix,.routespro-order-table')) updateScrollState(entry.target);
      }));
      document.querySelectorAll('.rp-table-wrap,.rp-table-scroll,.ff-table-scroll,.ffb-table-wrap,.routespro-product-matrix,.routespro-order-table').forEach(el=>resizeObserver.observe(el));
    }
    document.dispatchEvent(new CustomEvent('fieldflow:responsive-ready',{detail:{device:device(),platform:platform(),displayMode:displayMode(),version:String(cfg.version||'')}}));
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
})();
