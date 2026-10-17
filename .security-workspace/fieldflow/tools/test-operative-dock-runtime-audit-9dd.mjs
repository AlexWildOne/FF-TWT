import assert from 'node:assert/strict';
import vm from 'node:vm';
import fs from 'node:fs';
const script=fs.readFileSync(new URL('../assets/fieldflow-operative-dock-audit-9dd.js',import.meta.url),'utf8');
function style(){
 const values=new Map();
 return {setProperty:(key,v,priority='')=>values.set(key,[v,priority]),removeProperty:key=>values.delete(key),
   getPropertyValue:key=>values.get(key)?.[0]||'',getPropertyPriority:key=>values.get(key)?.[1]||''};
}
const listeners={window:{},document:{}};
const listen=who=>(ev,cb)=>{(listeners[who][ev]??=[]).push(cb);};
const bClass={contains:key=>key==='ffv-dock-hidden' ? false:false};
const bar={tagName:'NAV',className:'rp-tabbar',parentElement:null,getBoundingClientRect:()=>({width:360,top:720,bottom:785,height:65})};
const nav={tagName:'DIV',className:'rp-tabs',classList:bClass,style:style(),getBoundingClientRect:()=>({width:370,top:720,bottom:785,height:65}),querySelector:key=>key==='.rp-tabbar'?bar:null};
const active={tagName:'SECTION',className:'rp-panel active',scrollHeight:0,clientHeight:0,parentElement:null};
const operative={tagName:'DIV',className:'rp-app',dataset:{ffvDockControllerActive:'operative-9dc',ffvDockAuthority:'9d8',ffvDockState:'visible'},
   getBoundingClientRect:()=>({width:400,height:1200}),querySelectorAll:key=>key==='.rp-tabs'?[nav]:key==='.rp-tabbar'?[bar]:[],querySelector:key=>key==='.rp-panel.active'?active:null};
active.parentElement=operative;nav.parentElement=operative;
const parts={
 '[data-ffdd-status]':{textContent:''},'[data-ffdd-output]':{textContent:''},'[data-ffdd-toggle]':{textContent:''},
 'details[open]':null
};
const panelHandlers={};
const panel={dataset:{},querySelector:key=>parts[key],addEventListener(name,fn){panelHandlers[name]=fn;}};
const doc={readyState:'complete',body:{tagName:'BODY',scrollTop:0},documentElement:{scrollTop:0},hidden:false,
  querySelectorAll:key=>key.startsWith('#routespro-app')?[operative]:[],
  getElementById:key=>key==='ff-dock-audit-9dd'?panel:null,
  elementFromPoint:()=>nav,
  addEventListener:listen('document')};
const win={innerWidth:390,innerHeight:840,scrollY:0,getComputedStyle:()=>({position:'fixed',transform:'none',opacity:'1',visibility:'visible',display:'block',overflowY:'visible'}),
  matchMedia:()=>({matches:true}),requestAnimationFrame:callback=>{callback();return 1;},setTimeout:callback=>{callback();return 1;},setInterval:()=>1,
  addEventListener:listen('window')};
const ctx={window:win,document:doc,console,navigator:{},Date};
vm.runInNewContext(script,ctx);
assert.match(parts['[data-ffdd-output]'].textContent,/JS ativo/);
assert.match(parts['[data-ffdd-output]'].textContent,/operative-9dc/);
let s=win.FieldFlowDockAudit9DD.snapshot();
assert.equal(s.dockState,'visible');
assert.equal(s.forcedTest,false);
listeners.window.scroll[0]({target:doc});
listeners.document.scroll[0]({target:active});
listeners.document.touchstart[0]({target:active,touches:[{clientY:100}]});
listeners.document.touchmove[0]({target:active,touches:[{clientY:70}]});
s=win.FieldFlowDockAudit9DD.snapshot();
assert.equal(s.events.windowScroll,1);
assert.equal(s.events.documentScroll,1);
assert.equal(s.events.touchMove,1);
assert.equal(s.events.lastDeltaY,-30);
// The only mutation is on explicit click, and the second click restores it.
assert.equal(nav.style.getPropertyValue('opacity'),'');
const clickAction=action=>panelHandlers.click({target:{closest:()=>({dataset:{ffddAction:action},textContent:''})}});
clickAction('toggle');
assert.equal(nav.style.getPropertyValue('opacity'),'0');
assert.equal(nav.style.getPropertyPriority('opacity'),'important');
assert.equal(win.FieldFlowDockAudit9DD.snapshot().forcedTest,true);
clickAction('toggle');
assert.equal(nav.style.getPropertyValue('opacity'),'');
assert.equal(win.FieldFlowDockAudit9DD.snapshot().forcedTest,false);
assert.equal(win.FieldFlowDockAudit9DD.snapshot().dockState,'visible');
console.log('OPERATIVE DOCK REAL RUNTIME AUDIT 9DD DOM PASS');
