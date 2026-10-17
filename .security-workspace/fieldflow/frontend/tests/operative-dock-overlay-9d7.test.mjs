import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';
const dir=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const code=fs.readFileSync(path.join(dir,'assets/fieldflow-operative-dock-overlay-9d7.js'),'utf8');
const css=fs.readFileSync(path.join(dir,'assets/fieldflow-operative-dock-overlay-9d7.css'),'utf8');
function classes(initial=[]){
 const set=new Set(initial);
 return {add(v){set.add(v);},remove(v){set.delete(v);},contains(v){return set.has(v);},toggle(v,enabled){if(enabled)set.add(v);else set.delete(v);}};
}
function emitter(){const callbacks=new Map();return {addEventListener(k,fn){callbacks.set(k,[...(callbacks.get(k)||[]),fn]);},fire(k,event={}){for(const cb of callbacks.get(k)||[])cb(event);},listenerCount(k){return (callbacks.get(k)||[]).length;}};}
function harness({mobile=true}={}){
 const outside={nodeType:1,closest(){return null;}};
 const bar={nodeType:1,getBoundingClientRect(){return {height:65};}};
 const dock={nodeType:1,classList:classes(),contains(target){return target===bar;},querySelector(key){return key==='.rp-tabbar'?bar:null;}};
 const root={id:'routespro-app',nodeType:1,isConnected:true,style:{vars:{},setProperty(k,v){this.vars[k]=v;}},dataset:{ffvDockAuthority:'9d6'},
   classList:classes(['ffv-visual-9d5']),...emitter(),
   querySelector(key){return key==='.rp-tabs'?dock:null;},
   contains(target){return target!==null&&target!==undefined&&target!==outside;}};
 const panel={nodeType:1,parentElement:root,scrollTop:0,scrollHeight:1900,clientHeight:500,closest(){return null;}};
 const area={nodeType:1,parentElement:panel,scrollHeight:90,clientHeight:90,closest(){return null;}};
 const horizontal={nodeType:1,parentElement:root,scrollTop:0,scrollHeight:240,clientHeight:240,closest(){return null;}};
 const body={nodeType:1,classList:classes(),scrollTop:0};
 const html={nodeType:1,scrollTop:0,scrollHeight:3100,clientHeight:720};
 const document={readyState:'complete',hidden:false,body,documentElement:html,scrollingElement:html,activeElement:null,...emitter(),getElementById(id){return id==='routespro-app'?root:null;}};
 const window={scrollY:0,...emitter(),matchMedia(){return {matches:mobile};},requestAnimationFrame(fn){fn();},getComputedStyle(){return {overflowY:'auto'};}};
 vm.runInNewContext(code,{window,document,WeakMap,Array});
 const send=(target,positions)=>{
  for(const [type,x,y] of positions){const point={identifier:7,clientX:x,clientY:y};document.fire(type,{target,touches:type==='touchend'?[]:[point]});}
 };
 return {root,dock,bar,window,document,panel,area,horizontal,outside,send};
}
test('9D7 owns only Operative dock; no API, storage or Client portal styles',()=>{
 const {root}=harness();
 assert.equal(root.dataset.ffvDockAuthority,'9d7');
 assert.equal(root.dataset.ffvOperativeOverlay,'fieldflow-operative-dock-overlay-9d7-v1');
 assert.match(css,/#routespro-app\.ffv-visual-9d5\.ffv-operative-overlay-9d7/);
 assert.doesNotMatch(css,/#routespro-client-portal/);
 assert.doesNotMatch(code,/\b(?:fetch|XMLHttpRequest|indexedDB|localStorage|sessionStorage)\s*\(/);
});
test('9D7 touch gesture works even with zero window scroll events',()=>{
 const {dock,area,send,window}=harness();
 send(area,[['touchstart',100,580],['touchmove',100,520],['touchend',100,520]]);
 assert.equal(window.scrollY,0);
 assert.ok(dock.classList.contains('ffv-dock-hidden'),'downward content gesture hides');
 send(area,[['touchstart',100,400],['touchmove',100,450],['touchend',100,450]]);
 assert.ok(!dock.classList.contains('ffv-dock-hidden'),'upward content gesture reveals');
});
test('9D7 horizontal product carousel gesture does not hide',()=>{
 const {dock,area,send}=harness();
 send(area,[['touchstart',100,540],['touchmove',155,542],['touchmove',210,549],['touchend',210,549]]);
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
});
test('9D7 window scroll hides, shows on reverse and near top',()=>{
 const {dock,window}=harness();
 window.scrollY=140;window.fire('scroll');
 assert.ok(dock.classList.contains('ffv-dock-hidden'));
 window.scrollY=106;window.fire('scroll');
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
 window.scrollY=250;window.fire('scroll');
 assert.ok(dock.classList.contains('ffv-dock-hidden'));
 window.scrollY=12;window.fire('scroll');
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
});
test('9D7 captured nested scroll hides and restores',()=>{
 const {dock,document,panel}=harness();
 document.fire('scroll',{target:panel});
 panel.scrollTop=95;document.fire('scroll',{target:panel});
 assert.ok(dock.classList.contains('ffv-dock-hidden'));
 panel.scrollTop=61;document.fire('scroll',{target:panel});
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
});
test('9D7 ignores horizontal-only event sources and events from outside portal',()=>{
 const {dock,document,horizontal,outside}=harness();
 document.fire('scroll',{target:horizontal});
 horizontal.scrollTop=400;document.fire('scroll',{target:horizontal});
 document.fire('scroll',{target:outside});
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
});
test('9D7 does not toggle while keyboard is open; native selects hide dock',()=>{
 const {dock,document,area,send,root}=harness();
 document.body.classList.add('ff-keyboard-open');
 send(area,[['touchstart',100,500],['touchmove',100,410],['touchend',100,410]]);
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
 document.body.classList.remove('ff-keyboard-open');
 const select={nodeType:1,matches(txt){return txt.includes('select');}};
 root.fire('focusin',{target:select});
 assert.ok(dock.classList.contains('ffv-dock-hidden'));
 document.activeElement=null;
 root.fire('focusout',{target:select});
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
});
test('9D7 refuses desktop auto-hide and sets actual dock height for reserve',()=>{
 const {dock,root,window}=harness({mobile:false});
 window.scrollY=440;window.fire('scroll');
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
 assert.equal(root.style.vars['--ffv-dock-height'],'65px');
 assert.match(css,/padding-bottom:var\(--ffv-dock-reserve\)!important/);
 assert.match(css,/scroll-padding-bottom:var\(--ffv-dock-reserve\)!important/);
});
test('9D7 idempotent bootstrap on responsive-ready',()=>{
 const {document,root,window}=harness();
 const before=window.listenerCount('scroll');
 document.fire('fieldflow:responsive-ready',{});
 assert.equal(window.listenerCount('scroll'),before);
 assert.equal(root.dataset.ffvDockAuthority,'9d7');
});
