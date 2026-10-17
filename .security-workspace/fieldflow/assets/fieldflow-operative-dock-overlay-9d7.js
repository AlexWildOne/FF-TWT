/* FieldFlow 2.2.632 | fieldflow-operative-dock-overlay-9d7-v1
 * Presentation-only Android/iOS WebView fix. Use the actual scroll stream and
 * passive touch gestures as a fallback when an embedded scroll host doesn't
 * emit window/document scroll events. No REST, storage or business mutations.
 */
(function(){
  'use strict';
  const CONTRACT='fieldflow-operative-dock-overlay-9d7-v1';
  const PHONE='(max-width:760px)';
  const SCROLL_TOP_MARGIN=32;
  const HIDE_TRAVEL=38;
  const SHOW_TRAVEL=18;
  const EXCLUDE='input,textarea,select,[contenteditable="true"],[role="combobox"],canvas,video,[role="slider"],[data-ff-horizontal-pan="1"],.rp-tabs,.rp-tabbar';
  const phone=()=>!window.matchMedia||window.matchMedia(PHONE).matches;
  const keyboard=()=>!!document.body?.classList.contains('ff-keyboard-open');
  const scrollY=()=>Math.max(0,Number(window.scrollY)||0,Number(document.scrollingElement?.scrollTop)||0,Number(document.documentElement?.scrollTop)||0,Number(document.body?.scrollTop)||0);
  const isElement=(node)=>!!node&&node.nodeType===1;
  function boot(){
    const root=document.getElementById('routespro-app');
    if(!root||root.dataset.ffvOperativeOverlay===CONTRACT||root.dataset.ffvDockAuthority==='9d8') return;
    const dock=root.querySelector('.rp-tabs');
    const bar=dock?.querySelector('.rp-tabbar');
    if(!dock||!bar||!root.classList.contains('ffv-visual-9d5')) return;
    root.dataset.ffvOperativeOverlay=CONTRACT;
    root.dataset.ffvDockAuthority='9d7';
    root.classList.add('ffv-operative-overlay-9d7');
    const positions=new WeakMap();
    let lastDirection=0;
    let distance=0;
    let touch=null;
    positions.set(window,scrollY());

    function setVisible(show){
      if(!root.isConnected||root.dataset.ffvDockAuthority!=='9d7') return;
      const visible=show||!phone();
      dock.classList.toggle('ffv-dock-hidden',!visible);
      root.dataset.ffvDockState=visible?'visible':'hidden';
    }
    function editorActive(){
      const active=document.activeElement;
      return isElement(active)&&root.contains(active)&&active.matches('input,textarea,select,[contenteditable="true"],[role="combobox"]');
    }
    function movement(delta,atTop){
      if(!phone()){setVisible(true);return;}
      if(keyboard()||editorActive()||Math.abs(delta)<2) return;
      if(atTop){setVisible(true);lastDirection=0;distance=0;return;}
      const direction=delta>0?1:-1;
      if(direction!==lastDirection){lastDirection=direction;distance=0;}
      distance+=Math.abs(delta);
      if(direction>0&&distance>=HIDE_TRAVEL){setVisible(false);distance=0;}
      else if(direction<0&&distance>=SHOW_TRAVEL){setVisible(true);distance=0;}
    }
    function onScroll(source){
      if(source!==window){
        if(!isElement(source)||!root.contains(source)||dock.contains(source)||source===dock) return;
        // Only vertical scroll hosts, never horizontal tables and swipers.
        if(!(source.scrollHeight>source.clientHeight+3)) return;
      }
      const now=source===window?scrollY():Math.max(0,Number(source.scrollTop)||0);
      if(!positions.has(source)){positions.set(source,now);return;}
      const previous=positions.get(source);
      positions.set(source,now);
      movement(now-previous,now<SCROLL_TOP_MARGIN);
    }
    function onDocumentScroll(event){
      const target=event.target;
      if(target===document||target===document.documentElement||target===document.body){onScroll(window);return;}
      onScroll(target);
    }
    function canScrollVertically(target){
      for(let node=target;isElement(node);node=node.parentElement){
        if(node===dock||dock.contains(node)) return false;
        if(node.scrollHeight>node.clientHeight+4 && node.clientHeight>0){
          const style=window.getComputedStyle?window.getComputedStyle(node):null;
          if(style&&/(auto|scroll|overlay)/.test(style.overflowY||'')) return true;
        }
        if(node===root) break;
      }
      const scrolling=document.scrollingElement||document.documentElement||document.body;
      return !!(scrolling&&scrolling.scrollHeight>scrolling.clientHeight+4);
    }
    function excludedTarget(node){
      return !isElement(node)||!root.contains(node)||!!node.closest(EXCLUDE);
    }
    function onTouchStart(event){
      touch=null;
      if(!phone()||keyboard()||event.touches?.length!==1||excludedTarget(event.target)) return;
      if(!canScrollVertically(event.target)) return;
      const point=event.touches[0];
      touch={id:point.identifier,x:point.clientX,y:point.clientY,axis:null};
    }
    function onTouchMove(event){
      if(!touch||!phone()||keyboard()) return;
      const point=Array.from(event.touches||[]).find(t=>t.identifier===touch.id);
      if(!point){touch=null;return;}
      const dx=point.clientX-touch.x;
      const dy=point.clientY-touch.y;
      if(!touch.axis){
        if(Math.max(Math.abs(dx),Math.abs(dy))<7) return;
        touch.axis=Math.abs(dy)>Math.abs(dx)*1.35?'vertical':'horizontal';
      }
      touch.x=point.clientX;touch.y=point.clientY;
      if(touch.axis!=='vertical') return;
      // Finger moves upwards as the content moves downwards.
      // This works even when Android WebView scroll events target another host.
      movement(-dy,false);
    }
    function measure(){
      if(!root.isConnected) return;
      const rect=bar.getBoundingClientRect();
      root.style.setProperty('--ffv-dock-height',Math.max(55,Math.ceil(rect.height||0))+'px');
    }
    function navigation(event){
      const target=event.target;
      if(!isElement(target)||!root.contains(target)) return;
      if(target.closest('.rp-tabbar button,[data-ff-panel],[data-target],.ff-ope-command')){
        distance=0;lastDirection=0;positions.set(window,scrollY());setVisible(true);
      }
    }
    function onFocus(event){
      if(!phone()||!isElement(event.target)||!root.contains(event.target)) return;
      if(event.target.matches('select,[role="combobox"],[aria-haspopup="listbox"]')) setVisible(false);
    }
    function onBlur(event){
      if(!isElement(event.target)||!root.contains(event.target)) return;
      if(event.target.matches('select,[role="combobox"],[aria-haspopup="listbox"]')){
        window.requestAnimationFrame(()=>{if(!keyboard()&&!editorActive())setVisible(true);});
      }
    }
    window.addEventListener('scroll',()=>onScroll(window),{passive:true});
    document.addEventListener('scroll',onDocumentScroll,true);
    document.addEventListener('touchstart',onTouchStart,{capture:true,passive:true});
    document.addEventListener('touchmove',onTouchMove,{capture:true,passive:true});
    document.addEventListener('touchend',()=>{touch=null;},{capture:true,passive:true});
    document.addEventListener('touchcancel',()=>{touch=null;},{capture:true,passive:true});
    root.addEventListener('focusin',onFocus,true);
    root.addEventListener('focusout',onBlur,true);
    root.addEventListener('click',navigation,true);
    window.addEventListener('pageshow',()=>setVisible(true));
    window.addEventListener('resize',()=>{measure();if(!phone())setVisible(true);},{passive:true});
    if(window.visualViewport) window.visualViewport.addEventListener('resize',measure,{passive:true});
    if(typeof ResizeObserver==='function')new ResizeObserver(measure).observe(bar);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)setVisible(true);});
    measure();setVisible(true);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
  document.addEventListener('fieldflow:responsive-ready',boot);
})();
