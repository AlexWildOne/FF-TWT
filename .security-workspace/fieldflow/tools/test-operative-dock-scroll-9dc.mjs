/* Browser-like DOM state tests, no dependency on Chrome or WordPress. */
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const code=fs.readFileSync(new URL('../assets/fieldflow-operative-dock-scroll-9dc.js',import.meta.url),'utf8');
const legacy=fs.readFileSync(new URL('../assets/fieldflow-visual-consolidation-9d5.js',import.meta.url),'utf8');
function events(){
 const listeners=new Map();
 return {
   addEventListener(key,fn){if(!listeners.has(key))listeners.set(key,[]);listeners.get(key).push(fn);},
   fire(key,e={}){for(const fn of listeners.get(key)||[])fn(e);},
   count(key){return (listeners.get(key)||[]).length;},
 };
}
function classes(initial=[]){const set=new Set(initial);return{
 add(...items){items.forEach(x=>set.add(x));},remove(...items){items.forEach(x=>set.delete(x));},
 toggle(item,enabled){if(enabled===undefined)enabled=!set.has(item);if(enabled)set.add(item);else set.delete(item);return enabled;},
 contains(item){return set.has(item);},
 toString(){return [...set].join(' ');},
};}
let parentRoot;
function makeElement(name,initial=[],more={}){
 const node={nodeType:1,tagName:name.toUpperCase(),className:initial.join(' '),classList:classes(initial),
   parentElement:null,scrollTop:0,scrollHeight:200,clientHeight:200,overflowY:'visible',
   contains(other){let n=other;while(n){if(n===this)return true;n=n.parentElement;}return false;},
   closest(){return null;},matches(selector){return selector.split(',').some(s=>s.trim()===name);},
   getBoundingClientRect(){return{height:64,left:0,width:390};},
   ...events(),...more,
 };
 return node;
}
function setup({withRoot=true,withLegacy=false}={}){
 const root=makeElement('div',['rp-premium-shell','ffv-visual-9d5'],{id:'routespro-app',
   dataset:{fieldflowPortal:'operative'},isConnected:true,style:{values:{},setProperty(name,v){this.values[name]=v;}},});
 const dock=makeElement('div',['rp-tabs']);dock.parentElement=root;
 const bar=makeElement('div',['rp-tabbar'],{clientWidth:390,scrollWidth:750,scrollLeft:0});bar.parentElement=dock;
 const active=makeElement('button',['active'],{offsetLeft:0,offsetWidth:90,getBoundingClientRect(){return {left:20,width:90}}});active.parentElement=bar;
 bar.querySelector=key=>key==='button.active'?active:null;
 dock.querySelector=key=>key==='.rp-tabbar'?bar:null;
 root.querySelector=key=>key==='.rp-tabs'?root.dock:null;
 root.dock=dock;
 const nested=makeElement('div',['rp-panels'],{scrollHeight:1600,clientHeight:330,overflowY:'auto'});nested.parentElement=root;
 const child=makeElement('div',['rp-panel'],{scrollHeight:200,clientHeight:200});child.parentElement=nested;
 const body=makeElement('body',[],{classList:classes(),scrollHeight:1200,clientHeight:800});root.parentElement=body;
 const docRoot=makeElement('html',[],{scrollHeight:1200,clientHeight:800});body.parentElement=docRoot;
 const doc={readyState:'complete',body,documentElement:docRoot,scrollingElement:docRoot,hidden:false,activeElement:null,
   querySelector(selector){return withRoot&&selector.startsWith('#routespro-app')?root:null;},
   querySelectorAll(selector){return withRoot&&selector.includes('#routespro-app')?[root]:[];},
   ...events()};
 const win={scrollY:0,matchMedia(){return{matches:true}},requestAnimationFrame(fn){fn();return 1},
   setTimeout(fn){fn();return 1},getComputedStyle(node){return{overflowY:node.overflowY}},...events()};
 let notifyMutations=null;
 class MutationObserverMock {constructor(fn){notifyMutations=fn;}observe(){}disconnect(){}}
 const context={document:doc,window:win,ResizeObserver:undefined,MutationObserver:MutationObserverMock};
 if(withLegacy)vm.runInNewContext(legacy,context,{timeout:2500});
 vm.runInNewContext(code,context,{timeout:2500});
 return {doc,win,root,dock,bar,nested,child,rerender(){notifyMutations?.();}};
}
function evTouch(target,type,x,y){return {target,touches:type==='touchend'?[]:[{identifier:1,clientX:x,clientY:y}]};}
const a=setup({withLegacy:true});
assert.equal(a.root.dataset.ffvDockControllerActive,'operative-9dc');
assert.equal(a.root.dataset.ffvDockAuthority,'9d8');
assert.equal(a.doc.count('scroll'),1,'document capture is installed');
assert.equal(a.doc.count('touchmove'),1,'vertical touch fallback installed');
assert.equal(a.root.dataset.ffvDockState,'visible');
a.nested.scrollTop=95;a.doc.fire('scroll',{target:a.nested});
a.nested.scrollTop=190;a.doc.fire('scroll',{target:a.nested});
assert.equal(a.root.dataset.ffvDockState,'hidden','nested scroll down hides real dock');
assert(a.dock.classList.contains('ffv-dock-hidden'),'real dock receives hidden class');
a.nested.scrollTop=130;a.doc.fire('scroll',{target:a.nested});
assert.equal(a.root.dataset.ffvDockState,'visible','nested scroll up shows dock');
// Horizontal swipes on a scrollable vertical container must not hide the dock.
a.doc.fire('touchstart',evTouch(a.child,'touchstart',200,350));
a.doc.fire('touchmove',evTouch(a.child,'touchmove',90,348));
assert.equal(a.root.dataset.ffvDockState,'visible','horizontal touch ignored');
// Valid vertical gesture on a scroll host hides even when window.scrollY stays zero.
a.doc.fire('touchstart',evTouch(a.child,'touchstart',210,350));
a.doc.fire('touchmove',evTouch(a.child,'touchmove',208,310));
a.doc.fire('touchmove',evTouch(a.child,'touchmove',207,270));
assert.equal(a.root.dataset.ffvDockState,'hidden','vertical gesture fallback');
// The selected tab becomes available after activating navigation.
a.root.fire('click',{target:{nodeType:1,parentElement:a.bar,closest(selector){return selector.includes('.rp-tabbar button')?this:null;}}});
assert.equal(a.root.dataset.ffvDockState,'visible','nav interaction restores dock');
// Focused select hides and on blur restores, without changing business state.
const select=makeElement('select');select.parentElement=a.root;
a.doc.activeElement=select;a.root.fire('focusin',{target:select});
assert.equal(a.root.dataset.ffvDockState,'hidden');
a.doc.activeElement=null;a.root.fire('focusout',{target:select});
assert.equal(a.root.dataset.ffvDockState,'visible');
// External ancestor scroll (e.g. Elementor scroll container) is observable.
const outer=makeElement('section',['elementor-scroll'],{scrollHeight:1800,clientHeight:480,overflowY:'auto'});
outer.parentElement=a.doc.body;a.root.parentElement=outer;
a.doc.fire('scroll',{target:outer});outer.scrollTop=120;a.doc.fire('scroll',{target:outer});
assert.equal(a.root.dataset.ffvDockState,'hidden','ancestor scroll owner');
// Recreate the real dock and run the shortcode's MutationObserver reconciliation.
assert.equal(a.root.classList.contains('ffv-operative-dock-collapsed-9dc'),true);
const freshDock=makeElement('div',['rp-tabs']);freshDock.parentElement=a.root;
const freshBar=makeElement('div',['rp-tabbar'],{clientWidth:390,scrollWidth:800,scrollLeft:0});freshBar.parentElement=freshDock;
freshBar.querySelector=()=>null;freshDock.querySelector=key=>key==='.rp-tabbar'?freshBar:null;
a.root.dock=freshDock;a.rerender();
assert.equal(freshDock.classList.contains('ffv-dock-hidden'),true,'DOM rerender retains hidden state');
const b=setup();b.win.scrollY=90;b.doc.documentElement.scrollTop=90;b.win.fire('scroll');
b.win.scrollY=170;b.doc.documentElement.scrollTop=170;b.win.fire('scroll');
assert.equal(b.root.dataset.ffvDockState,'hidden','window scroll down');
b.win.scrollY=130;b.doc.documentElement.scrollTop=130;b.win.fire('scroll');
assert.equal(b.root.dataset.ffvDockState,'visible','window scroll up');
const c=setup({withRoot:false});
assert.equal(c.doc.count('scroll'),0,'Client portal not modified');
console.log('OPERATIVE DOCK SCROLL 9DC JS PASS | inner scroll, window, ancestor, touch axes, tabs, select, Client isolation');
