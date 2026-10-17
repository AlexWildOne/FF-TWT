// OPERATIVE DOCK BOOTSTRAP 9DE JS PASS
// Reproduces the 9DD finding (visible .rp-tabs, absent 9DC owner) and tests
// the new independent boot, gestures with zero scroll, DOM remount and Client isolation.
import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const js=fs.readFileSync(new URL('../assets/fieldflow-operative-dock-bootstrap-9de.js',import.meta.url),'utf8');
function bus(){const m=new Map();return{
 addEventListener(name,fn){m.set(name,[...(m.get(name)||[]),fn]);},
 fire(name,event={}){for(const f of m.get(name)||[])f(event);},
 count(name){return(m.get(name)||[]).length}
};}
function classes(init=[]){const s=new Set(init);return {
 add(...keys){keys.forEach(x=>s.add(x));},remove(...keys){keys.forEach(x=>s.delete(x));},
 contains(x){return s.has(x);},toggle(x,on){if(on===undefined)on=!s.has(x);if(on)s.add(x);else s.delete(x);return on;}
};}
function style(){const map=new Map();return{
 setProperty(n,v,priority=''){map.set(n,[v,priority]);},removeProperty(n){map.delete(n);},
 getPropertyValue(n){return map.get(n)?.[0]||'';},getPropertyPriority(n){return map.get(n)?.[1]||'';}
};}
function el(tag,initial=[]){return{nodeType:1,tagName:tag.toUpperCase(),id:'',className:initial.join(' '),classList:classes(initial),
 parentElement:null,isConnected:true,dataset:{},style:style(),scrollTop:0,scrollHeight:1600,clientHeight:500,
 getBoundingClientRect(){return {width:395,height:60,left:0,top:0};},
 contains(target){for(let n=target;n;n=n.parentElement)if(n===this)return true;return false;},
 closest(selector){if(this.tagName==='INPUT'&&selector.includes('input'))return this;
 if(this.classList.contains('rp-tabs')&&selector.includes('.rp-tabs'))return this;
 if(this.classList.contains('rp-tabbar')&&selector.includes('.rp-tabbar'))return this;
 if(this.tagName==='BUTTON'&&selector.includes('.rp-tabbar button'))return this;
 return null;},
 matches(selector){return this.tagName==='INPUT'&&selector.includes('input');},
 ...bus()
};}
let callbacks=[];
class MutationMock{constructor(fn){callbacks.push(fn);}observe(){}disconnect(){}}
function fixture({operative=true,attached=true}={}){
 callbacks=[];
 let now=1000000;
 const FakeDate={now:()=>now};
 const html=el('html'),body=el('body');body.parentElement=html;
 const content=el('article'),root=el('div',['rp-premium-shell','ffv-visual-9d5']),dock=el('div',['rp-tabs']),bar=el('nav',['rp-tabbar']);
 root.id=operative?'routespro-app':'routespro-client-portal';root.dataset.fieldflowPortal=operative?'operative':'client';
 root.parentElement=body;dock.parentElement=root;bar.parentElement=dock;content.parentElement=root;
 root.querySelectorAll=key=>key==='.rp-tabs'?[dock]:[];
 root.querySelector=key=>key==='.rp-tabs .rp-tabbar'?bar:key==='.rp-tabs'?dock:null;
 dock.querySelector=key=>key==='.rp-tabbar'?bar:null;
 dock.getBoundingClientRect=()=>({width:380,height:60,top:720});
 bar.getBoundingClientRect=()=>({width:365,height:56,top:722});
 const doc={readyState:'complete',documentElement:html,body,scrollingElement:html,hidden:false,activeElement:null,...bus(),
 querySelectorAll:selector=>selector.includes('data-fieldflow-portal="operative"')&&operative&&attached?[doc.activeRoot]:[],
 };
 doc.activeRoot=root;
 const win={scrollY:0,matchMedia:()=>({matches:true}),requestAnimationFrame:cb=>{cb();return 1},setTimeout:cb=>{cb();return 1},...bus()};
 const ctx={window:win,document:doc,MutationObserver:MutationMock,Date:FakeDate};
 vm.runInNewContext(js,ctx,{timeout:3500});
 return{doc,win,root,dock,bar,content,body,ctx,advance(ms){now+=ms;},mount(){attached=true;},remove(){attached=false;},
 rerender(next){doc.activeRoot=next;callbacks.forEach(f=>f());}};
}
function start(target,x,y,id=2){return{target,touches:[{identifier:id,clientX:x,clientY:y}]};}
const a=fixture();
const ctrl=a.win.FieldFlowOperativeDock9DE;
assert.equal(ctrl.contract,'fieldflow-operative-dock-bootstrap-recovery-9de-v1');
assert.equal(a.root.dataset.ffvDockControllerActive,'operative-9de','visible root is explicitly claimed');
assert.equal(a.root.dataset.ffvDockState,'visible');
assert.equal(a.root.dataset.ffvDockAuthority,'9d8','legacy 9D5 yields');
assert.equal(a.doc.count('touchmove'),1);
// No scroll host, window.scrollY=0, but a vertical gesture must still collapse.
a.doc.fire('touchstart',start(a.content,200,270));
a.doc.fire('touchmove',start(a.content,198,225));
a.doc.fire('touchmove',start(a.content,197,178));
assert.equal(a.root.dataset.ffvDockState,'hidden');
assert.equal(a.dock.style.getPropertyValue('opacity'),'0');
assert.equal(a.dock.style.getPropertyPriority('opacity'),'important');
// Momentum from the same gesture must not reopen the hidden dock immediately.
a.doc.fire('touchstart',start(a.content,197,178));
a.doc.fire('touchmove',start(a.content,197,215));
assert.equal(a.root.dataset.ffvDockState,'hidden','quick reverse stays hidden during fade hold');
a.advance(950);
a.doc.fire('touchstart',start(a.content,197,160));
a.doc.fire('touchmove',start(a.content,196,195));
assert.equal(a.root.dataset.ffvDockState,'visible','upward content movement reveals dock');
assert.equal(a.dock.style.getPropertyValue('opacity'),'','show clears inline overrides');
// Native scroll generated by the same touch must not count a second time.
a.content.scrollTop=55;a.doc.fire('scroll',{target:a.content});
assert.equal(a.root.dataset.ffvDockState,'visible','native follow-up scroll does not hide after upward touch');
assert.ok(a.win.FieldFlowOperativeDock9DE.snapshot().suppressedScroll >= 1);
// Horizontal gesture does not change visibility.
a.doc.fire('touchstart',start(a.content,200,265));
a.doc.fire('touchmove',start(a.content,78,262));
assert.equal(a.root.dataset.ffvDockState,'visible','horizontal gesture stays isolated');
// Input gesture does not hide the dock.
const input=el('input');input.parentElement=a.root;
a.doc.fire('touchstart',start(input,200,260));
a.doc.fire('touchmove',start(input,201,180));
assert.equal(a.root.dataset.ffvDockState,'visible','input gesture remains accessible');
// Any related scrolling element can drive the owner, no CSS-overflow guess.
a.advance(1200);
a.content.scrollTop=120;a.doc.fire('scroll',{target:a.content});
assert.equal(a.root.dataset.ffvDockState,'hidden','real internal scroll hides');
a.advance(500);
a.content.scrollTop=40;a.doc.fire('scroll',{target:a.content});
assert.equal(a.root.dataset.ffvDockState,'visible','reverse scroll reveals');
// The next root must get an owner even if React replaces the original.
const b=el('div',['rp-premium-shell']),newDock=el('div',['rp-tabs']),newBar=el('nav',['rp-tabbar']);
b.dataset.fieldflowPortal='operative';b.parentElement=a.body;newDock.parentElement=b;newBar.parentElement=newDock;
b.querySelector=key=>key==='.rp-tabs .rp-tabbar'?newBar:key==='.rp-tabs'?newDock:null;
b.querySelectorAll=key=>key==='.rp-tabs'?[newDock]:[];
newDock.querySelector=key=>key==='.rp-tabbar'?newBar:null;
a.root.isConnected=false;
a.rerender(b);
assert.equal(b.dataset.ffvDockControllerActive,'operative-9de','fresh DOM root reclaimed');
assert.equal(a.win.FieldFlowOperativeDock9DE.snapshot().mounted,true);
assert.equal(a.doc.count('touchmove'),1,'no competing second global listener');
vm.runInNewContext(js,a.ctx,{timeout:3500});
assert.equal(a.doc.count('touchmove'),1,'idempotent script injection');
const c=fixture({operative:false});
assert.equal(c.win.FieldFlowOperativeDock9DE.snapshot().mounted,false,'Client never mounted');
assert.equal(c.root.dataset.ffvDockControllerActive,undefined,'Client root unchanged');
const d=fixture({attached:false});
assert.equal(d.win.FieldFlowOperativeDock9DE.snapshot().mounted,false);
d.mount();callbacks.forEach(f=>f());
assert.equal(d.root.dataset.ffvDockControllerActive,'operative-9de','late Elementor render recovered');
console.log('OPERATIVE DOCK BOOTSTRAP 9DE JS PASS | absent owner recovery, zero-scroll touch, internal scroll, horizontal/input guards, DOM replacement, late mount, Client isolation');
