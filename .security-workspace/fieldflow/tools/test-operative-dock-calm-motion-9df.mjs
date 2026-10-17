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
// OPERATIVE DOCK CALM MOTION 9DF JS PASS
const a=fixture();
const c=a.win.FieldFlowOperativeDock9DE;
assert.equal(c.motionContract,'fieldflow-operative-dock-calm-motion-9df-v1');
assert.equal(c.snapshot().motion.hideDistance,54);
assert.equal(c.snapshot().motion.visibleHoldMs,900);
const tap=(x,y)=>start(a.content,x,y);
assert.equal(a.root.dataset.ffvDockState,'visible');
a.doc.fire('touchstart',tap(120,280));
a.doc.fire('touchmove',tap(120,242));
assert.equal(a.root.dataset.ffvDockState,'visible','under 54px down stays visible');
a.doc.fire('touchmove',tap(120,221));
assert.equal(a.root.dataset.ffvDockState,'hidden','deliberate 59px down hides');
assert.equal(a.dock.style.getPropertyValue('transform'),'translate3d(0,14px,0)','fade with small translate rather than full screen slide');
assert.equal(c.snapshot().movementCount,1);
a.doc.fire('touchstart',tap(120,210));
a.doc.fire('touchmove',tap(120,260));
assert.equal(a.root.dataset.ffvDockState,'hidden','quick reverse during hidden hold ignored');
a.advance(600);
a.doc.fire('touchstart',tap(120,190));
a.doc.fire('touchmove',tap(120,222));
assert.equal(a.root.dataset.ffvDockState,'visible','up gesture after hidden hold reveals');
assert.equal(c.snapshot().movementCount,2);
// Scroll after the touch is a second signal for the same physical movement.
a.content.scrollTop=100;a.doc.fire('scroll',{target:a.content});
assert.equal(a.root.dataset.ffvDockState,'visible');
assert.equal(c.snapshot().suppressedScroll,1);
// Reverse bounce during visible hold cannot hide again.
a.doc.fire('touchstart',tap(120,280));
a.doc.fire('touchmove',tap(120,205));
assert.equal(a.root.dataset.ffvDockState,'visible');
a.advance(1050);
a.doc.fire('touchstart',tap(120,280));
a.doc.fire('touchmove',tap(120,215));
assert.equal(a.root.dataset.ffvDockState,'hidden','fresh deliberate down hides after hold');
assert.equal(c.snapshot().movementCount,3);
// A sideways gesture and an editable input never change visibility.
a.advance(750);
a.doc.fire('touchstart',tap(250,260));a.doc.fire('touchmove',tap(120,255));
assert.equal(a.root.dataset.ffvDockState,'hidden');
const input=el('input');input.parentElement=a.root;
a.doc.fire('touchstart',start(input,120,260));a.doc.fire('touchmove',start(input,121,170));
assert.equal(a.root.dataset.ffvDockState,'hidden');
// Desktop-like actual scroll continues after the grace window.
a.advance(1000);a.content.scrollTop=10;a.doc.fire('scroll',{target:a.content});
assert.equal(a.root.dataset.ffvDockState,'visible','real upward container scroll reveals');
const css=fs.readFileSync(new URL('../assets/fieldflow-operative-dock-bootstrap-9de.css',import.meta.url),'utf8');
assert.match(css,/opacity \.36s ease/);
assert.match(css,/visibility 0s linear \.36s/);
assert.match(css,/prefers-reduced-motion:reduce/);
assert.ok(!css.includes('calc(100% + 96px)'),'no full height jump');
console.log('OPERATIVE DOCK CALM MOTION 9DF JS PASS | hysteresis, fade, duplicate scroll suppression, hold, input, horizontal, scroll host');
