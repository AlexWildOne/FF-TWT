import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';
const base=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const code=fs.readFileSync(path.join(base,'assets/fieldflow-operative-dock-authority-9d8.js'),'utf8');
const css=fs.readFileSync(path.join(base,'assets/fieldflow-operative-dock-authority-9d8.css'),'utf8');
const shortcode=fs.readFileSync(path.join(base,'src/Front/AppShortcodeRenderer.php'),'utf8');
function classes(initial=[]){const set=new Set(initial);return {add(...v){v.forEach(x=>set.add(x));},remove(...v){v.forEach(x=>set.delete(x));},contains(v){return set.has(v);},toggle(v,yes){if(yes)set.add(v);else set.delete(v);}};}
function emitter(){const m=new Map();return {addEventListener(type,fn){m.set(type,[...(m.get(type)||[]),fn]);},fire(type,event={}){for(const fn of m.get(type)||[])fn(event);},count(type){return (m.get(type)||[]).length;}};}
function harness({mobile=true,rootExists=true}={}){
 const bar={nodeType:1,getBoundingClientRect(){return {height:62};}};
 const dock={nodeType:1,classList:classes(),querySelector(k){return k==='.rp-tabbar'?bar:null;}};
 const root={nodeType:1,isConnected:true,dataset:{},classList:classes(['ffv-visual-9d5']),style:{vars:{},setProperty(k,v){this.vars[k]=v;}},...emitter(),querySelector(k){return k==='.rp-tabs'?dock:null;},contains(node){return [panel,area,dock,bar,select].includes(node);}};
 const panel={nodeType:1,scrollTop:0,clientHeight:500,scrollHeight:500,contains(node){return node===area;},closest(){return null;}};
 const area={nodeType:1,closest(){return null;}};
 const select={nodeType:1,matches(q){return q.includes('select');},closest(){return null;}};
 const body={nodeType:1,classList:classes(),scrollTop:0};
 const html={nodeType:1,scrollTop:0,scrollHeight:500,clientHeight:500};
 const document={hidden:false,body,documentElement:html,scrollingElement:html,...emitter(),getElementById(k){return rootExists&&k==='routespro-app'?root:null;}};
 const window={scrollY:0,...emitter(),matchMedia(){return {matches:mobile};},requestAnimationFrame(fn){fn();}};
 vm.runInNewContext(code,{document,window,WeakMap,Array,Number});
 const gesture=(x0,y0,x1,y1,target=area)=>{
   document.fire('touchstart',{target,touches:[{identifier:1,clientX:x0,clientY:y0}]});
   document.fire('touchmove',{target,touches:[{identifier:1,clientX:x1,clientY:y1}]});
   document.fire('touchend',{target,touches:[]});
 };
 return {root,bar,dock,window,document,panel,area,select,gesture};
}
test('9D8 runs directly in the shortcode and does not depend on enqueued assets',()=>{
 const {root,dock}=harness();
 assert.equal(root.dataset.ffvDockAuthority,'9d8');
 assert.equal(root.dataset.ffvDockState,'visible');
 assert.equal(root.style.vars['--ffv-dock-height'],'62px');
 assert.ok(root.classList.contains('ffv-dock-owner-9d8'));
 assert.ok(!dock.classList.contains('ffv-dock-hidden'));
 assert.match(shortcode,/file_get_contents\(ROUTESPRO_PATH \. 'assets\/fieldflow-operative-dock-authority-9d8\.js'/);
 assert.match(shortcode,/file_get_contents\(ROUTESPRO_PATH \. 'assets\/fieldflow-operative-dock-authority-9d8\.css'/);
 assert.match(css,/visibility:hidden!important/);
});
test('9D8 touch gestures toggle the dock even with no detectable scroll host or scroll events',()=>{
 const {root,gesture,window,document}=harness();
 assert.equal(document.scrollingElement.scrollHeight,document.scrollingElement.clientHeight);
 gesture(100,540,100,470);
 assert.equal(window.scrollY,0);
 assert.equal(root.dataset.ffvDockState,'hidden');
 gesture(100,410,100,460);
 assert.equal(root.dataset.ffvDockState,'visible');
});
test('9D8 horizontal gestures and keyboard do not hide the dock',()=>{
 const {root,gesture,document}=harness();
 gesture(90,510,150,511);
 assert.equal(root.dataset.ffvDockState,'visible');
 document.body.classList.add('ff-keyboard-open');
 gesture(90,510,90,420);
 assert.equal(root.dataset.ffvDockState,'visible');
});
test('9D8 captive scroll of ancestor wrapping the shortcode is detected',()=>{
 const {root,document}=harness();
 const host={nodeType:1,scrollTop:0,contains(el){return el===root;}};
 document.fire('scroll',{target:host});
 host.scrollTop=100;document.fire('scroll',{target:host});
 assert.equal(root.dataset.ffvDockState,'hidden');
 host.scrollTop=50;document.fire('scroll',{target:host});
 assert.equal(root.dataset.ffvDockState,'visible');
});
test('9D8 does not activate for desktop or for absent Operative root',()=>{
 const desktop=harness({mobile:false});
 desktop.gesture(90,530,90,420);
 assert.equal(desktop.root.dataset.ffvDockState,'visible');
 const missing=harness({rootExists:false});
 assert.equal(missing.root.dataset.ffvDockAuthority,undefined);
});
test('9D8 is idempotent and only writes scoped Operative presentation state',()=>{
 const {root,document}=harness();
 const listeners=document.count('touchstart');
 vm.runInNewContext(code,{document,window:{matchMedia(){return {matches:true};}},WeakMap,Array,Number});
 assert.equal(document.count('touchstart'),listeners);
 assert.equal(root.dataset.ffvDockAuthority,'9d8');
 assert.doesNotMatch(code,/\b(?:fetch|XMLHttpRequest|indexedDB|localStorage|sessionStorage)\s*\(/);
 assert.doesNotMatch(css,/#routespro-client-portal/);
});
