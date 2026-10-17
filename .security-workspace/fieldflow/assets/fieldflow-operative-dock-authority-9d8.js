/* FieldFlow 2.2.633 | fieldflow-operative-inline-dock-authority-9d8-v1
 * Shortcode-owned, self-contained presentation enhancement. Runs even when
 * wp_enqueue_scripts misses an Elementor/PWA shortcode page. No network,
 * offline, storage, route, form or tenant mutations. 9D5 Client is unchanged.
 */
(function(){
  'use strict';
  const CONTRACT='fieldflow-operative-inline-dock-authority-9d8-v1';
  const PHONE='(max-width:760px)';
  const SKIP='input,textarea,select,[contenteditable="true"],[role="combobox"],canvas,video,[role="slider"],[data-ff-horizontal-pan="1"],.rp-tabs,.rp-tabbar';
  const root=document.getElementById('routespro-app');
  if(!root||root.dataset.ffvOperativeDock9d8===CONTRACT) return;
  const currentDock=()=>root.querySelector('.rp-tabs');
  if(!currentDock()||!currentDock().querySelector('.rp-tabbar')) return;
  root.dataset.ffvOperativeDock9d8=CONTRACT;
  root.dataset.ffvDockAuthority='9d8';
  root.dataset.ffvDockState='visible';
  root.classList.add('ffv-dock-authority-9d8','ffv-dock-owner-9d8','ffv-dock-ready-9d8');
  root.classList.remove('ffv-dock-collapsed-9d8');
  const phone=()=>!window.matchMedia||window.matchMedia(PHONE).matches;
  const keyboard=()=>!!document.body?.classList.contains('ff-keyboard-open');
  const element=node=>!!node&&node.nodeType===1;
  const pageY=()=>Math.max(0,Number(window.scrollY)||0,Number(document.scrollingElement?.scrollTop)||0,Number(document.documentElement?.scrollTop)||0,Number(document.body?.scrollTop)||0);
  const positions=new WeakMap();
  positions.set(window,pageY());
  let touch=null,travel=0,direction=0,resizeTarget=null;
  function setVisible(visible,source){
    if(!root.isConnected||root.dataset.ffvDockAuthority!=='9d8') return;
    const show=!!visible||!phone();
    root.classList.toggle('ffv-dock-collapsed-9d8',!show);
    const dock=currentDock();
    if(dock) dock.classList.remove('ffv-dock-hidden');
    root.dataset.ffvDockState=show?'visible':'hidden';
    root.dataset.ffvDockSource=String(source||'init');
  }
  function movement(delta,source,atTop=false){
    if(!phone()){setVisible(true,'desktop');return;}
    if(keyboard()||!Number.isFinite(delta)||Math.abs(delta)<2) return;
    if(atTop){travel=0;direction=0;setVisible(true,source);return;}
    const next=delta>0?1:-1;
    if(next!==direction){direction=next;travel=0;}
    travel+=Math.abs(delta);
    if(next>0&&travel>=30){setVisible(false,source);travel=0;}
    else if(next<0&&travel>=16){setVisible(true,source);travel=0;}
  }
  function isRelevantScroller(target){
    if(target===window||target===document||target===document.body||target===document.documentElement) return true;
    return element(target)&&(root.contains(target)||target.contains(root));
  }
  function onScroll(target){
    if(!isRelevantScroller(target))return;
    const host=(target===document||target===document.body||target===document.documentElement)?window:target;
    const now=host===window?pageY():Math.max(0,Number(host.scrollTop)||0);
    if(!positions.has(host)){positions.set(host,now);return;}
    const before=positions.get(host);
    positions.set(host,now);
    movement(now-before,'scroll',host===window&&now<16);
  }
  function skipTarget(node){return !element(node)||!root.contains(node)||!!node.closest(SKIP);}
  function onTouchStart(event){
    touch=null;
    if(!phone()||keyboard()||event.touches?.length!==1||skipTarget(event.target))return;
    const point=event.touches[0];
    touch={id:point.identifier,x:point.clientX,y:point.clientY,axis:null};
  }
  function onTouchMove(event){
    if(!touch||!phone()||keyboard())return;
    const point=Array.from(event.touches||[]).find(p=>p.identifier===touch.id);
    if(!point){touch=null;return;}
    const dx=point.clientX-touch.x,dy=point.clientY-touch.y;
    if(!touch.axis){
      if(Math.max(Math.abs(dx),Math.abs(dy))<7)return;
      touch.axis=Math.abs(dy)>Math.abs(dx)*1.3?'vertical':'horizontal';
    }
    touch.x=point.clientX;touch.y=point.clientY;
    if(touch.axis==='vertical')movement(-dy,'touch');
  }
  function measure(){
    const dock=currentDock();
    const bar=dock?.querySelector('.rp-tabbar');
    if(!bar)return;
    root.style.setProperty('--ffv-dock-height',Math.max(55,Math.ceil(bar.getBoundingClientRect().height||0))+'px');
    if(typeof ResizeObserver==='function'&&resizeTarget!==bar){
      resizeTarget=bar;
      new ResizeObserver(measure).observe(bar);
    }
  }
  function navigation(event){
    const node=event.target;
    if(element(node)&&root.contains(node)&&node.closest('.rp-tabs button,[data-panel],[data-target],.ff-ope-command')){
      travel=0;direction=0;setVisible(true,'navigation');
    }
  }
  document.addEventListener('scroll',event=>onScroll(event.target),true);
  window.addEventListener('scroll',()=>onScroll(window),{passive:true});
  document.addEventListener('touchstart',onTouchStart,{passive:true,capture:true});
  document.addEventListener('touchmove',onTouchMove,{passive:true,capture:true});
  for(const type of ['touchend','touchcancel'])document.addEventListener(type,()=>{touch=null;},{passive:true,capture:true});
  document.addEventListener('wheel',event=>{
    if(element(event.target)&&root.contains(event.target)&&!skipTarget(event.target)&&Math.abs(event.deltaY)>3)movement(event.deltaY,'wheel');
  },{passive:true,capture:true});
  root.addEventListener('focusin',event=>{
    if(event.target?.matches?.('select,[role="combobox"],[aria-haspopup="listbox"]'))setVisible(false,'select');
    else if(event.target?.closest?.('.rp-tabs'))setVisible(true,'focus');
  },true);
  root.addEventListener('focusout',event=>{
    if(event.target?.matches?.('select,[role="combobox"],[aria-haspopup="listbox"]')){
      window.requestAnimationFrame(()=>{if(!keyboard())setVisible(true,'select-close');});
    }
  },true);
  root.addEventListener('click',navigation,true);
  root.addEventListener('keydown',event=>{
    if(event.key==='Tab'||event.key==='Escape')setVisible(true,'keyboard');
  },true);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)setVisible(true,'resume');});
  window.addEventListener('pageshow',()=>setVisible(true,'pageshow'));
  window.addEventListener('resize',()=>{measure();if(!phone())setVisible(true,'resize');},{passive:true});
  if(window.visualViewport)window.visualViewport.addEventListener('resize',measure,{passive:true});
  document.addEventListener('fieldflow:responsive-ready',measure);
  document.addEventListener('routespro:panel-open',()=>{measure();setVisible(true,'panel-open');});
  measure();setVisible(true,'boot');
})();
