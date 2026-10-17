import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import {fileURLToPath} from 'node:url';

const plugin=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const source=fs.readFileSync(path.join(plugin,'assets/fieldflow-operative-dock-parity-9d6.js'),'utf8');
const css=fs.readFileSync(path.join(plugin,'assets/fieldflow-operative-dock-parity-9d6.css'),'utf8');

const classes=()=>{
  const values=new Set();
  return {
    add(s){values.add(s);},remove(s){values.delete(s);},
    contains(s){return values.has(s);},
  };
};
const emitter=()=>{
  const handlers=new Map();
  return {
    addEventListener(type,handler){
      const list=handlers.get(type)||[];list.push(handler);handlers.set(type,list);
    },
    fire(type,event={}){for(const callback of handlers.get(type)||[])callback(event);},
  };
};
function harness({mobile=true}={}){
  const tabbar={};
  const dock={classList:classes(),contains(target){return target===tabbar;},querySelector(selector){return selector==='.rp-tabbar'?tabbar:null;}};
  const root={id:'routespro-app',dataset:{},classList:classes(),...emitter(),
    querySelector(selector){return selector==='.rp-tabs'?dock:null;},
    contains(target){return target!==null&&target!==undefined&&target!==outside;},
  };
  const outside={};
  root.classList.add('ffv-visual-9d5');
  const document={readyState:'complete',hidden:false,body:{classList:classes()},activeElement:null,
    ...emitter(),getElementById(id){return id==='routespro-app'?root:null;},
  };
  const window={scrollY:0,...emitter(),
    matchMedia(){return {matches:mobile};},
    requestAnimationFrame(callback){callback();return 1;},
  };
  vm.runInNewContext(source,{window,document,WeakMap});
  return {root,dock,document,window,outside};
}

test('9D6 only scopes Operative, leaves 9D5 client dock and original data untouched',()=>{
  assert.match(source,/fieldflow-operative-dock-parity-9d6-v1/);
  assert.match(source,/ffvDockAuthority='9d6'/);
  assert.match(css,/#routespro-app\.ffv-visual-9d5\.ffv-operative-parity-9d6/);
  assert.doesNotMatch(css,/#routespro-client-portal/);
  assert.doesNotMatch(source,/\b(?:fetch|XMLHttpRequest|indexedDB|localStorage|sessionStorage)\s*\(/);
  assert.match(source,/document\.addEventListener\('scroll',onDocumentScroll,true\)/);
  const {root}=harness();
  assert.equal(root.dataset.ffvDockAuthority,'9d6');
  assert.ok(root.classList.contains('ffv-operative-parity-9d6'));
});

test('9D6 scroll at window root hides after sustained descent, restores upwards and at top',()=>{
  const {dock,window}=harness();
  window.scrollY=12;window.fire('scroll');
  assert.equal(dock.classList.contains('ffv-dock-hidden'),false,'no early hide');
  window.scrollY=95;window.fire('scroll');
  assert.ok(dock.classList.contains('ffv-dock-hidden'));
  window.scrollY=88;window.fire('scroll');
  assert.ok(!dock.classList.contains('ffv-dock-hidden'),'top reveals dock');
  window.scrollY=190;window.fire('scroll');
  assert.ok(dock.classList.contains('ffv-dock-hidden'));
  window.scrollY=174;window.fire('scroll');
  assert.ok(!dock.classList.contains('ffv-dock-hidden'),'upwards reveals dock');
});

test('9D6 captures vertical scroll in nested portal panels and ignores horizontal carousels',()=>{
  const {dock,document}=harness();
  const panel={scrollTop:0,scrollHeight:1600,clientHeight:500,closest(){return null;}};
  document.fire('scroll',{target:panel});
  panel.scrollTop=28;document.fire('scroll',{target:panel});
  assert.ok(!dock.classList.contains('ffv-dock-hidden'));
  panel.scrollTop=102;document.fire('scroll',{target:panel});
  assert.ok(dock.classList.contains('ffv-dock-hidden'));
  panel.scrollTop=80;document.fire('scroll',{target:panel});
  assert.ok(!dock.classList.contains('ffv-dock-hidden'));
  panel.scrollTop=172;document.fire('scroll',{target:panel});
  assert.ok(dock.classList.contains('ffv-dock-hidden'));
  const horizontal={scrollTop:0,scrollHeight:250,clientHeight:250,closest(){return null;}};
  document.fire('scroll',{target:horizontal});
  assert.ok(dock.classList.contains('ffv-dock-hidden'),'horizontal carousel does not affect dock');
});

test('9D6 preserves nav while keyboard is open and restores when using a tab',()=>{
  const {dock,document,window,root}=harness();
  window.scrollY=170;window.fire('scroll');
  assert.ok(dock.classList.contains('ffv-dock-hidden'));
  document.body.classList.add('ff-keyboard-open');
  window.scrollY=260;window.fire('scroll');
  assert.ok(dock.classList.contains('ffv-dock-hidden'),'keyboard must not toggle scroll state');
  document.body.classList.remove('ff-keyboard-open');
  const control={closest(){return {name:'tab'};}};
  root.fire('click',{target:control});
  assert.ok(!dock.classList.contains('ffv-dock-hidden'));
  const select={matches(selector){return selector.includes('select');}};
  root.fire('focusin',{target:select});
  assert.ok(dock.classList.contains('ffv-dock-hidden'),'native select popover is not covered');
  root.fire('focusout',{target:select});
  assert.ok(!dock.classList.contains('ffv-dock-hidden'));
});

test('9D6 does not hide desktop dock',()=>{
  const {dock,window}=harness({mobile:false});
  window.scrollY=800;window.fire('scroll');
  assert.ok(!dock.classList.contains('ffv-dock-hidden'));
});

test('9D6 status polish changes labels, not canonical values or event identifiers',()=>{
  const client=fs.readFileSync(path.join(plugin,'src/Front/ClientPortalShortcodeRenderer.php'),'utf8');
  assert.match(client,/function statusLabel\(status\)/);
  assert.match(client,/in_progress:'Em curso'/);
  assert.match(client,/done:'Concluída'/);
  assert.match(client,/data-stop-status="done"/);
  assert.match(client,/\$\{currentStatus===s\?'selected':''\}>\$\{esc\(statusLabel\(s\)\)\}/);
});
