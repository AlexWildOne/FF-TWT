import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';
const base=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const source=fs.readFileSync(path.join(base,'assets/fieldflow-visual-consolidation-9d5.js'),'utf8');
const operative=fs.readFileSync(path.join(base,'src/Front/AppShortcodeRenderer.php'),'utf8');
const responsive=fs.readFileSync(path.join(base,'src/Front/ResponsiveAssets.php'),'utf8');
const client=fs.readFileSync(path.join(base,'src/Front/ClientPortalShortcodeRenderer.php'),'utf8');
function emitter(){const m=new Map();return {addEventListener(t,f){m.set(t,[...(m.get(t)||[]),f]);},emit(t,ev={}){for(const f of m.get(t)||[])f(ev);},count(t){return (m.get(t)||[]).length;}};}
function classes(list=[]){const s=new Set(list);return {contains(k){return s.has(k);},add(...keys){for(const k of keys)s.add(k);},remove(...keys){for(const k of keys)s.delete(k);},toggle(k){if(s.has(k)){s.delete(k);return false;}s.add(k);return true;}};}
function harness(id){
 const bar={...emitter(),clientWidth:360,scrollWidth:360,scrollLeft:0,getBoundingClientRect(){return {height:60};},querySelector(){return null;}};
 const dock={classList:classes(),contains(){return false;},querySelector(s){return s==='.rp-tabbar'?bar:null;}};
 const root={id,isConnected:true,dataset:{},classList:classes(['ffv-visual-9d5']),style:{vars:{},setProperty(k,v){this.vars[k]=v;}},querySelector(k){if(k==='.rp-tabs')return dock;return null;}};
 const document={readyState:'complete',hidden:false,activeElement:null,body:{classList:classes()},querySelectorAll(k){assert.equal(k,'#routespro-client-portal,#routespro-app');return [root];},...emitter()};
 let pending=[];
 const window={scrollY:0,matchMedia(){return {matches:true};},requestAnimationFrame(cb){pending.push(cb);return pending.length;},...emitter()};
 const context={document,window,ResizeObserver:undefined};
 vm.runInNewContext(source,context);
 const flush=()=>{const callbacks=pending;pending=[];for(const cb of callbacks)cb();};
 return {dock,root,document,window,flush,context};
}

test('9D9 loads the IDENTICAL 9D5 Client JS inline for the operative, with no new controller',()=>{
 assert.match(operative,/file_get_contents\(ROUTESPRO_PATH \. 'assets\/fieldflow-visual-consolidation-9d5\.js'\)/);
 assert.match(operative,/fieldflow-operative-client-dock-parity-9d9-v1/);
 assert.match(operative,/\$dock9d8_enabled = !\$shared_dock_enabled/);
 assert.match(responsive,/\$legacyOperativeDock = defined\('FIELDFLOW_OPERATIVE_CLIENT_DOCK_9D9'\)/);
 assert.equal((responsive.match(/if \(\$legacyOperativeDock/g)||[]).length,2);
 assert.doesNotMatch(client,/client-9d5-9d9/);
});

test('9D9 has EXACTLY the same hide-on-down/show-on-up behaviour as 9D5 Client',()=>{
 const clientH=harness('routespro-client-portal');
 const opH=harness('routespro-app');
 for(const h of [clientH,opH]){
   assert.equal(h.document.count('scroll'),0);
   assert.equal(h.window.count('scroll'),1);
   assert.ok(!h.dock.classList.contains('ffv-dock-hidden'));
   h.window.scrollY=300;h.window.emit('scroll');h.flush();
   assert.ok(h.dock.classList.contains('ffv-dock-hidden'));
   h.window.scrollY=245;h.window.emit('scroll');h.flush();
   assert.ok(!h.dock.classList.contains('ffv-dock-hidden'));
   assert.equal(h.root.style.vars['--ffv-dock-height'],'60px');
   const listeners=h.window.count('scroll');
   vm.runInNewContext(source,h.context);
   assert.equal(h.window.count('scroll'),listeners,'same asset loaded a second time must not attach duplicate listeners');
 }
});

test('9D9 keeps Client labels, date filters, routing state and original JS unchanged',()=>{
 assert.doesNotMatch(source,/fetch\(|localStorage\.|indexedDB\./);
 assert.match(source,/const ROOTS='#routespro-client-portal,#routespro-app'/);
 assert.match(source,/dock.classList.add\('ffv-dock-hidden'\)/);
 assert.match(source,/root\.id==='routespro-app'/);
 assert.match(operative,/data-fieldflow-dock-controller=/);
});
