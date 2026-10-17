/* FieldFlow 2.2.631 | fieldflow-operative-dock-parity-9d6-v1
 * Presentation only. Detects real vertical scrolling inside the Operative
 * portal as well as window scrolling. It does not modify data, REST requests,
 * tabs, offline queues, files, user state, or browser storage.
 */
(function(){
  'use strict';
  const CONTRACT='fieldflow-operative-dock-parity-9d6-v1';
  const MOBILE='(max-width:760px)';
  function phone(){return !window.matchMedia||window.matchMedia(MOBILE).matches;}
  function keyboard(){return !!document.body?.classList.contains('ff-keyboard-open');}
  function boot(){
    const root=document.getElementById('routespro-app');
    if(!root||root.dataset.ffvOperativeDock===CONTRACT||root.dataset.ffvDockAuthority==='9d8') return;
    const dock=root.querySelector('.rp-tabs');
    const tabbar=dock?.querySelector('.rp-tabbar');
    if(!dock||!tabbar||!root.classList.contains('ffv-visual-9d5')) return;
    root.dataset.ffvOperativeDock=CONTRACT;
    root.dataset.ffvDockAuthority='9d6';
    root.classList.add('ffv-operative-parity-9d6');
    const positions=new WeakMap();
    const currentScrollY=()=>Math.max(0,Number(window.scrollY ?? document.scrollingElement?.scrollTop ?? 0)||0);
    positions.set(window,{last:currentScrollY(),direction:0,travel:0});
    // 9D7 only supersedes the Operative scroll authority, not the 9D6 contract.
    function show(){if(root.dataset.ffvDockAuthority==='9d7'||root.dataset.ffvDockAuthority==='9d8')return;dock.classList.remove('ffv-dock-hidden');}
    function hide(){if(root.dataset.ffvDockAuthority==='9d7'||root.dataset.ffvDockAuthority==='9d8')return;dock.classList.add('ffv-dock-hidden');}
    function activeEditor(){
      const el=document.activeElement;
      return !!(el&&root.contains(el)&&el.matches&&el.matches('input,textarea,select,[contenteditable="true"],[role="combobox"]'));
    }
    function handle(source){
      if(root.dataset.ffvDockAuthority==='9d7'||root.dataset.ffvDockAuthority==='9d8') return;
      if(!phone()){show();return;}
      if(keyboard()) return;
      if(source!==window){
        if(!root.contains(source)) return;
        if(source===dock||dock.contains(source)||source.closest?.('.rp-tabs')) return;
        // Ignore horizontal-only carousels and tables; only vertical travel
        // should affect primary application navigation.
        if(!(source.scrollHeight>source.clientHeight+3)) return;
      }
      const now=source===window?currentScrollY():Math.max(0,Number(source.scrollTop)||0);
      let state=positions.get(source);
      if(!state){positions.set(source,{last:now,direction:0,travel:0});return;}
      const diff=now-state.last;
      state.last=now;
      if(Math.abs(diff)<2) return;
      const direction=diff>0?1:-1;
      if(direction!==state.direction){state.direction=direction;state.travel=0;}
      state.travel+=Math.abs(diff);
      if(now<90){show();return;}
      if(activeEditor()) return;
      if(direction<0&&state.travel>=12){show();state.travel=0;}
      else if(direction>0&&state.travel>=30){hide();state.travel=0;}
    }
    function onDocumentScroll(event){
      const target=event.target;
      if(target===document||target===document.documentElement||target===document.body){handle(window);return;}
      if(target&&root.contains(target)) handle(target);
    }
    function onFocus(event){
      if(!phone()||!root.contains(event.target)) return;
      const input=event.target;
      // Native dropdowns can overlay the bottom dock without triggering the
      // keyboard detection used by the existing responsive runtime.
      if(input?.matches?.('select,[role="combobox"],[aria-haspopup="listbox"]')) hide();
    }
    function onBlur(event){
      if(!root.contains(event.target)) return;
      if(event.target?.matches?.('select,[role="combobox"],[aria-haspopup="listbox"]')){
        window.requestAnimationFrame(()=>{if(!keyboard()&&!activeEditor()) show();});
      }
    }
    function onNavigation(event){
      const control=event.target?.closest?.('.rp-tabbar button,.ff-ope-command,[data-ff-panel],[data-target]');
      if(!control||!root.contains(control)) return;
      show();
      positions.set(window,{last:currentScrollY(),direction:0,travel:0});
    }
    window.addEventListener('scroll',()=>handle(window),{passive:true});
    document.addEventListener('scroll',onDocumentScroll,true);
    root.addEventListener('focusin',onFocus,true);
    root.addEventListener('focusout',onBlur,true);
    root.addEventListener('click',onNavigation,true);
    window.addEventListener('resize',()=>{if(!phone()) show();},{passive:true});
    window.addEventListener('pageshow',show);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)show();});
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
  document.addEventListener('fieldflow:responsive-ready',boot);
})();
