import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source=fs.readFileSync(new URL('../src/Forms/FormRenderer.php',import.meta.url),'utf8');
const start=source.indexOf('  var rpPreviousVisitState=');
const end=source.indexOf('  function rpInitStoreProfile(form)',start);
assert.ok(start>=0&&end>start);
let checks=0;
const test=(name,fn)=>{fn();checks++;console.log('PASS '+name);};
class Modal {
  constructor(key,revision,content){this.attrs={'data-rp-previous-visit-key':key,'data-rp-previous-visit-revision':revision};this.content=content;this.hidden=true;this.parentNode=null;this.id=key;}
  getAttribute(key){return this.attrs[key]??null;}
  setAttribute(key,value){this.attrs[key]=value;}
  querySelector(){return null;}
  querySelectorAll(){return [];}
}
const children=[],liveForms=new Set(),timers=new Map(),classes=new Set();
let nextTimer=0;
const removeChild=child=>{const i=children.indexOf(child);if(i>=0)children.splice(i,1);child.parentNode=null;};
const body={appendChild(child){if(child.parentNode)child.parentNode.removeChild(child);children.push(child);child.parentNode=this;},removeChild};
const document={body,documentElement:{contains:form=>liveForms.has(form),classList:{add:s=>classes.add(s),remove:s=>classes.delete(s)}},querySelector:()=>children.find(child=>child.getAttribute('data-rp-global')==='1')??null,addEventListener(){},removeEventListener(){}};
const window={setTimeout(fn){const id=++nextTimer;timers.set(id,fn);return id;},clearTimeout(id){timers.delete(id);}};
const context={window,document,CustomEvent:class {constructor(type,init){this.type=type;this.detail=init.detail;}}};
vm.createContext(context);vm.runInContext(source.slice(start,end),context);
const init=window.routesproInitPreviousVisitModal;
const form=modal=>{const wrap={querySelector:()=>modal?.parentNode===wrap?modal:null,removeChild(child){child.parentNode=null;}};if(modal)modal.parentNode=wrap;const item={closest:()=>wrap,dispatchEvent(){}};liveForms.add(item);return item;};
const drain=()=>{const pending=[...timers.values()];timers.clear();pending.forEach(fn=>fn());};

const old=new Modal('store-55','v1','OLD VISIT · old-photo · old-order');
const first=form(old);init(first);drain();
test('initial_memory_opens',()=>assert.equal(document.querySelector(),old));
const latest=new Modal('store-55','v2','LATEST VISIT · notes only');
const second=form(latest);liveForms.delete(first);init(second);drain();
test('same_store_updated_memory_replaces_old_content',()=>assert.equal(document.querySelector(),latest));
test('old_images_and_order_are_not_left_in_global_modal',()=>assert.ok(!document.querySelector().content.includes('old-photo')&&!document.querySelector().content.includes('old-order')));
const duplicate=new Modal('store-55','v2','LATEST VISIT · notes only');
const third=form(duplicate);liveForms.delete(second);init(third);drain();
test('identical_repeat_preserves_modal_without_flash',()=>assert.equal(document.querySelector(),latest));
init(third);
test('reinitializing_same_form_preserves_owned_global_modal',()=>assert.equal(document.querySelector(),latest));
const empty=form(null);liveForms.delete(third);init(empty);drain();
test('new_store_without_memory_removes_old_popup',()=>assert.equal(document.querySelector(),null));
test('no_memory_releases_page_scroll',()=>assert.ok(!classes.has('routespro-previous-visit-open')));
const pendingModal=new Modal('store-56','v1','STORE 56');init(form(pendingModal));
window.routesproClearPreviousVisitModal();drain();
test('selection_clear_cancels_pending_popup',()=>assert.equal(document.querySelector(),null));
console.log('VISIT MEMORY REFRESH 9BI RUNTIME OK checks='+checks);
