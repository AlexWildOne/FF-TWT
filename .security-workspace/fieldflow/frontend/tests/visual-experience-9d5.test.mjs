import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const plugin=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../..');
const source=fs.readFileSync(path.join(plugin,'assets/fieldflow-visual-consolidation-9d5.js'),'utf8');
const css=fs.readFileSync(path.join(plugin,'assets/fieldflow-visual-consolidation-9d5.css'),'utf8');

test('9D5 visual layer stays scoped, retains branding tokens, no network or offline writes',()=>{
  assert.match(css,/#routespro-client-portal/);
  assert.match(css,/#routespro-app/);
  assert.match(css,/--ffv-primary:var\(--rp-primary-solid/);
  assert.match(css,/env\(safe-area-inset-bottom/);
  assert.match(css,/body\.ff-keyboard-open/);
  assert.doesNotMatch(source,/\b(?:fetch|localStorage|indexedDB|XMLHttpRequest)\s*\(/);
  assert.match(source,/fieldflow-visual-experience-consolidation-9d5-v1/);
});

test('9D5 preserves client filter identity and dynamically restores bottom navigation',()=>{
  const listeners=new Map();
  const frames=[];
  const events=(scope)=>({
    addEventListener(type,cb){scope.set(type,cb);},
    fire(type){const callback=scope.get(type);if(callback)callback();},
  });
  const classSet=()=>{
    const classes=new Set();
    return {add(c){classes.add(c);},remove(c){classes.delete(c);},contains(c){return classes.has(c);},toggle(c){if(classes.has(c)){classes.delete(c);return false;}classes.add(c);return true;}};
  };
  const from={value:'2026-10-01',...events(new Map())};
  const to={value:'2026-10-11',...events(new Map())};
  const apply={...events(new Map())};
  const toggleListeners=new Map();
  let filterButton=null;
  const toolbar={id:'',parentNode:{insertBefore(el){filterButton=el;}},...events(new Map())};
  const barListeners=new Map();
  const bar={
    clientWidth:320,scrollWidth:850,scrollLeft:0,
    ...events(barListeners),
    getBoundingClientRect(){return {height:58};},
    querySelector(sel){return sel==='button.active'?{offsetLeft:190,offsetWidth:95}:null;},
    scrollTo({left}){this.scrollLeft=left;},
  };
  const dock={classList:classSet(),contains(){return false;},querySelector(s){return s==='.rp-tabbar'?bar:null;}};
  const root={
    id:'routespro-client-portal',dataset:{},classList:classSet(),style:{vars:{},setProperty(k,v){this.vars[k]=v;}},isConnected:true,
    querySelector(sel){
      if(sel==='.rp-hero > .rp-toolbar')return toolbar;
      if(sel==='.ffv-filter-toggle')return filterButton;
      if(sel==='#rpcp-from')return from;
      if(sel==='#rpcp-to')return to;
      if(sel==='#rpcp-refresh')return apply;
      if(sel==='.rp-tabs')return dock;
      return null;
    },
  };
  const docListeners=new Map();
  const document={
    readyState:'complete',hidden:false,activeElement:null,body:{classList:classSet()},
    querySelectorAll(){return [root];},
    addEventListener(type,cb){docListeners.set(type,cb);},
    createElement(tag){
      assert.equal(tag,'button');
      const attributes={};
      const summary={textContent:''};
      return {type:'button',className:'',innerHTML:'',attributes,summary,
        setAttribute(k,v){attributes[k]=v;},
        querySelector(sel){return sel==='.ffv-filter-period'?summary:null;},
        ...events(toggleListeners)};
    },
  };
  const windowListeners=new Map();
  const window={
    scrollY:0,innerHeight:720,
    matchMedia(query){return {matches:true};},
    addEventListener(type,cb){windowListeners.set(type,cb);},
    requestAnimationFrame(cb){frames.push(cb);return frames.length;},
    setTimeout(cb){cb();},
  };
  vm.runInNewContext(source,{window,document,ResizeObserver:undefined});
  assert.equal(root.dataset.ffvVisual,'fieldflow-visual-experience-consolidation-9d5-v1');
  assert.ok(root.classList.contains('ffv-ready'));
  assert.ok(filterButton,'Filter button attached next to existing toolbar');
  assert.equal(filterButton.summary.textContent,'01/10 a 11/10');
  assert.equal(filterButton.attributes['aria-expanded'],'false');
  toggleListeners.get('click')();
  assert.equal(filterButton.attributes['aria-expanded'],'true');
  assert.ok(root.classList.contains('ffv-filters-open'));
  apply.fire('click');
  assert.ok(!root.classList.contains('ffv-filters-open'));
  window.scrollY=260;
  windowListeners.get('scroll')();frames.shift()?.();
  assert.ok(dock.classList.contains('ffv-dock-hidden'));
  window.scrollY=240;
  windowListeners.get('scroll')();frames.shift()?.();
  assert.ok(!dock.classList.contains('ffv-dock-hidden'));
  assert.equal(root.style.vars['--ffv-dock-height'],'58px');
});
