/* FieldFlow 2.2.637 | fieldflow-operative-dock-scroll-authority-9dc-v1
 * Operational portal only. Scroll host and touch authority for the actual
 * .rp-tabs DOM element; never rewrites routes, forms, stores or the Client dock.
 * Shortcode-delivered to avoid Elementor/WebView enqueue timing dependency.
 */
(function(){
  'use strict';
  const CONTRACT='fieldflow-operative-dock-scroll-authority-9dc-v1';
  const ROOT='#routespro-app[data-fieldflow-portal="operative"]';
  const PHONE='(max-width:760px)';
  const IGNORE_TOUCH='input,textarea,select,[contenteditable="true"],[role="combobox"],[role="slider"],canvas,video,.rp-tabs,.rp-tabbar,.rp-table-wrap,.ffp-table-wrap,.ffb-table-wrap,.ff-stock-table-wrap,.ff-ope-command-list,[data-ff-horizontal-pan="1"]';
  const phone=()=>!window.matchMedia||window.matchMedia(PHONE).matches;
  const pageY=()=>Math.max(0,Number(window.scrollY)||0,Number(document.scrollingElement?.scrollTop)||0,Number(document.documentElement?.scrollTop)||0,Number(document.body?.scrollTop)||0);
  const element=node=>!!node&&node.nodeType===1;
  const keyboard=()=>!!document.body?.classList.contains('ff-keyboard-open');
  function enhance(root){
    if(!root||root.dataset.ffvOperativeDock9dc===CONTRACT)return;
    let dock=root.querySelector('.rp-tabs');
    if(!dock||!dock.querySelector('.rp-tabbar'))return;
    root.dataset.ffvOperativeDock9dc=CONTRACT;
    root.dataset.ffvDockControllerActive='operative-9dc';
    // The historic Client 9D5 script is untouched. Its Operative onScroll
    // already yields for this compatibility sentinel; the 9D8 module is OFF.
    root.dataset.ffvDockAuthority='9d8';
    root.classList.add('ffv-operative-dock-9dc');
    const positions=new WeakMap();
    positions.set(window,pageY());
    let direction=0,travel=0,gesture=null,knownBar=null,observer=null;
    let measureQueued=false;
    let visible=true;
    const currentDock=()=>root.querySelector('.rp-tabs');
    const currentBar=()=>currentDock()?.querySelector('.rp-tabbar');
    const editorFocused=()=>{
      const active=document.activeElement;
      return element(active)&&root.contains(active)&&!!active.matches('input,textarea,select,[contenteditable="true"],[role="combobox"]');
    };
    function setVisible(next,source){
      if(!root.isConnected)return;
      visible=!!next||!phone();
      root.classList.toggle('ffv-operative-dock-collapsed-9dc',!visible);
      const actual=currentDock();
      if(actual)actual.classList.toggle('ffv-dock-hidden',!visible);
      root.dataset.ffvDockState=visible?'visible':'hidden';
      root.dataset.ffvDockSource=String(source||'init').slice(0,48);
    }
    function fitActive(){
      const bar=currentBar();
      const active=bar?.querySelector('button.active');
      if(!bar||!active||!phone())return;
      const view=bar.getBoundingClientRect();
      const selected=active.getBoundingClientRect();
      const delta=(selected.left+selected.width/2)-(view.left+view.width/2);
      const max=Math.max(0,bar.scrollWidth-bar.clientWidth);
      const next=Math.max(0,Math.min(max,bar.scrollLeft+delta));
      if(Math.abs(bar.scrollLeft-next)>3)bar.scrollLeft=next;
    }
    function measure(){
      measureQueued=false;
      if(!root.isConnected)return;
      const bar=currentBar();
      if(!bar)return;
      root.style.setProperty('--ffv-dock-height',Math.max(55,Math.ceil(bar.getBoundingClientRect().height||0))+'px');
      if(!phone())setVisible(true,'desktop');
    }
    function queueMeasure(){
      if(measureQueued)return;
      measureQueued=true;
      window.requestAnimationFrame(measure);
    }
    function syncDock(){
      if(!root.isConnected)return;
      const next=currentDock();
      const bar=next?.querySelector('.rp-tabbar');
      if(!next||!bar)return;
      if(next!==dock||bar!==knownBar){
        dock=next;
        knownBar=bar;
        dock.classList.toggle('ffv-dock-hidden',!visible);
        queueMeasure();
        fitActive();
      }
    }
    function movement(delta,source,top=false){
      if(!phone()){setVisible(true,'desktop');return;}
      if(keyboard()||editorFocused()||!Number.isFinite(delta))return;
      if(top){direction=0;travel=0;setVisible(true,source);return;}
      if(Math.abs(delta)<1.5)return;
      const next=delta>0?1:-1;
      if(next!==direction){direction=next;travel=0;}
      travel+=Math.abs(delta);
      if(next>0&&travel>=32){setVisible(false,source);travel=0;}
      else if(next<0&&travel>=16){setVisible(true,source);travel=0;}
    }
    function scrollSource(node){
      if(node===window||node===document||node===document.documentElement||node===document.body)return window;
      if(!element(node)||(!root.contains(node)&&!node.contains(root)))return null;
      if(currentDock()?.contains(node))return null;
      // Avoid false triggers from horizontal carousels and product tables.
      if(node.scrollHeight<=node.clientHeight+4||node.clientHeight<=0)return null;
      return node;
    }
    function sampleScroll(target){
      const host=scrollSource(target);
      if(!host)return;
      const now=host===window?pageY():Math.max(0,Number(host.scrollTop)||0);
      if(!positions.has(host)){
        positions.set(host,now);
        // First dynamic scroll host event can occur before touchstart.
        // Keep a baseline, and rely on following events or vertical touch.
        return;
      }
      const old=positions.get(host);
      positions.set(host,now);
      if(Math.abs(now-old)<1)return;
      root.dataset.ffvDockScrollHost=host===window?'page':String(host.className||host.id||host.tagName||'container').slice(0,60);
      const top=host===window?now<18:false;
      movement(now-old,'scroll',top);
    }
    function touchHosts(target){
      const hosts=[];
      let node=target;
      while(element(node)){
        if(node.scrollHeight>node.clientHeight+5&&node.clientHeight>0){
          const css=window.getComputedStyle?.(node);
          if(css&&/(auto|scroll|overlay)/.test(css.overflowY||''))hosts.push(node);
        }
        if(node===root)break;
        node=node.parentElement;
      }
      // Elementor shells may be vertically scrollable OUTSIDE the shortcode.
      node=root.parentElement;
      while(element(node)&&node!==document.body){
        if(node.scrollHeight>node.clientHeight+5&&node.clientHeight>0){
          const css=window.getComputedStyle?.(node);
          if(css&&/(auto|scroll|overlay)/.test(css.overflowY||''))hosts.push(node);
        }
        node=node.parentElement;
      }
      const page=document.scrollingElement||document.documentElement;
      if(page&&page.scrollHeight>page.clientHeight+5)hosts.push(window);
      return hosts;
    }
    function onTouchStart(event){
      gesture=null;
      const target=event.target;
      if(!phone()||keyboard()||event.touches?.length!==1||!element(target)||!root.contains(target)||target.closest(IGNORE_TOUCH))return;
      const hosts=touchHosts(target);
      if(!hosts.length)return;
      for(const host of hosts)positions.set(host,host===window?pageY():Math.max(0,Number(host.scrollTop)||0));
      const point=event.touches[0];
      gesture={id:point.identifier,x:point.clientX,y:point.clientY,axis:null,hosts};
    }
    function onTouchMove(event){
      if(!gesture||!phone()||keyboard())return;
      const point=Array.from(event.touches||[]).find(item=>item.identifier===gesture.id);
      if(!point){gesture=null;return;}
      const dx=point.clientX-gesture.x,dy=point.clientY-gesture.y;
      if(!gesture.axis){
        if(Math.max(Math.abs(dx),Math.abs(dy))<7)return;
        gesture.axis=Math.abs(dy)>Math.abs(dx)*1.35?'vertical':'horizontal';
      }
      gesture.x=point.clientX;gesture.y=point.clientY;
      if(gesture.axis!=='vertical')return;
      const delta=-dy;
      const canTravel=gesture.hosts.some(host=>{
        const node=host===window?(document.scrollingElement||document.documentElement):host;
        const top=host===window?pageY():Math.max(0,Number(node.scrollTop)||0);
        return delta>0 ? top<node.scrollHeight-node.clientHeight-2 : top>2;
      });
      if(!canTravel){if(delta<0)setVisible(true,'touch-top');return;}
      // Passive gesture fallback is needed on embedded WebViews whose scrolling
      // element does not expose a window.scrollY change.
      movement(delta,'touch');
    }
    function onDocumentScroll(event){sampleScroll(event.target);}
    function onNavigation(event){
      const target=event.target;
      if(!element(target)||!root.contains(target))return;
      if(target.closest('.rp-tabbar button,[data-ff-panel],[data-target],.ff-ope-command,.ff-react-front-operational-nav button')){
        direction=0;travel=0;
        setVisible(true,'navigation');
        window.requestAnimationFrame(fitActive);
        window.setTimeout(fitActive,50);
      }
    }
    function onFocus(event){
      const target=event.target;
      if(!element(target)||!root.contains(target))return;
      if(target.matches('select,[role="combobox"],[aria-haspopup="listbox"]'))setVisible(false,'select-focus');
    }
    function onBlur(event){
      const target=event.target;
      if(!element(target)||!root.contains(target))return;
      if(target.matches('select,[role="combobox"],[aria-haspopup="listbox"]')){
        window.requestAnimationFrame(()=>{if(!keyboard()&&!editorFocused())setVisible(true,'select-blur');});
      }
    }
    window.addEventListener('scroll',()=>sampleScroll(window),{passive:true});
    document.addEventListener('scroll',onDocumentScroll,true);
    document.addEventListener('touchstart',onTouchStart,{capture:true,passive:true});
    document.addEventListener('touchmove',onTouchMove,{capture:true,passive:true});
    document.addEventListener('touchend',()=>{gesture=null;},{capture:true,passive:true});
    document.addEventListener('touchcancel',()=>{gesture=null;},{capture:true,passive:true});
    root.addEventListener('click',onNavigation,true);
    root.addEventListener('focusin',onFocus,true);
    root.addEventListener('focusout',onBlur,true);
    window.addEventListener('resize',()=>{queueMeasure();if(!phone())setVisible(true,'desktop');},{passive:true});
    if(window.visualViewport)window.visualViewport.addEventListener('resize',queueMeasure,{passive:true});
    if(typeof ResizeObserver==='function')observer=new ResizeObserver(queueMeasure);
    const trackBar=()=>{
      const bar=currentBar();
      if(observer&&bar&&bar!==knownBar){observer.disconnect();observer.observe(bar);}
      syncDock();
    };
    if(typeof MutationObserver==='function')new MutationObserver(trackBar).observe(root,{childList:true,subtree:true});
    window.addEventListener('pageshow',()=>{trackBar();setVisible(true,'pageshow');});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden){trackBar();setVisible(true,'visible');}});
    document.addEventListener('fieldflow:responsive-ready',trackBar);
    trackBar();setVisible(true,'init');
  }
  function boot(){
    // Elementor may render a hidden shortcode preview alongside the live portal.
    // Do not attach only to the first matching id when multiple operative roots exist.
    document.querySelectorAll(ROOT).forEach(enhance);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
  document.addEventListener('fieldflow:responsive-ready',boot);
})();
