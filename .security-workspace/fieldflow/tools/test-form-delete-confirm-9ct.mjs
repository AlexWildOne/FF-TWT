// 9CT. Deterministic destructive-action confirmation contract, no WordPress or real browser required.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root=path.resolve(import.meta.dirname,'..');
const renderer=fs.readFileSync(path.join(root,'src/Forms/FormRenderer.php'),'utf8');
const start=renderer.indexOf("return <<<'JS'");
const finish=renderer.indexOf('\nJS;',start);
assert(start>=0&&finish>start,'form inline JavaScript missing');
const js=renderer.slice(start+"return <<<'JS'".length,finish);
new vm.Script(js);
const contract='rpConfirmFormDeletion';
const helperBegin=js.indexOf('  // 9CT: all user-initiated destructive form actions require explicit opt-in.');
const helperEnd=js.indexOf("  var RP_VIEWPORT_STORAGE_PREFIX=",helperBegin);
assert(helperBegin>=0&&helperEnd>helperBegin,'shared confirmation helper is missing');

// Confirm every destructive path before the corresponding mutation. Undos stay instant.
const actions={
  'preview photograph':'[data-rp-upload-preview-remove]',
  'uploaded file row':'[data-rp-upload-remove]',
  'existing photograph':'[data-rp-remove-existing-media]',
  'draft':'[data-rp-discard-draft]',
  'Cardex':' .routespro-product-remove',
};
for(const [label,selector] of Object.entries(actions)){
  const needle=selector===' .routespro-product-remove'?"$(document).on('click','.routespro-product-remove'":selector==='[data-rp-discard-draft]'?"$form.on('click','[data-rp-discard-draft]'":"$(document).on('click','"+selector+"'";
  const index=js.indexOf(needle);
  assert(index>=0,label+' removal event missing');
  const next=js.indexOf('  });',index);
  assert(next>index&&js.slice(index,next).includes(contract+'('),label+' not protected by confirmation');
}
assert(!js.includes("window.confirm('Queres mesmo eliminar"),'legacy Cardex dialog still used');
assert(!js.includes("window.confirm('Queres remover esta fotografia"),'legacy existing-media dialog still used');
assert(!js.includes("window.confirm('Descartar o rascunho"),'legacy draft dialog still used');
assert(js.includes('current.lastModified!==file.lastModified'),'preview stale index guard missing');
assert(js.includes("if(pending){toggleExisting();return;}"),'undo should be immediate');
assert(js.includes('form.isConnected'),'draft must reject after form teardown');
assert(renderer.includes('.routespro-delete-confirm-overlay{'),'confirmation visual styles missing');
assert(renderer.includes('@media(max-width:500px){.routespro-delete-confirm-dialog'),'mobile styles missing');

class FakeNode {
  constructor(type,document){this.type=type;this.doc=document;this.attrs={};this.style={};this.parentNode=null;this.children=[];this.handlers=new Map();this.textContent='';this.isConnected=false;this.focused=false;}
  setAttribute(key,value){this.attrs[key]=value;}
  getAttribute(key){return this.attrs[key]??null;}
  addEventListener(type,fn){this.handlers.set(type,fn);}
  removeEventListener(type,fn){if(this.handlers.get(type)===fn)this.handlers.delete(type);}
  appendChild(child){child.parentNode=this;child.isConnected=true;this.children.push(child);return child;}
  removeChild(child){child.parentNode=null;child.isConnected=false;this.children=this.children.filter(c=>c!==child);return child;}
  focus(){this.doc.activeElement=this;this.focused=true;}
  closest(selector){return this.selector===selector?this:null;}
  querySelector(selector){
    if(!this.fakeItems)this.fakeItems={};
    if(!this.fakeItems[selector]){const item=new FakeNode('button',this.doc);item.selector=selector;item.isConnected=true;this.fakeItems[selector]=item;}
    return this.fakeItems[selector];
  }
  fire(type,target){const event={target:target||this,preventDefault(){this.prevented=true;},stopPropagation(){this.stopped=true;},key:''};this.handlers.get(type)?.(event);return event;}
}
const doc={visibilityState:'visible',handlers:new Map(),addEventListener(name,handler){this.handlers.set(name,handler);},removeEventListener(name,handler){if(this.handlers.get(name)===handler)this.handlers.delete(name);},createElement(name){return new FakeNode(name,this);}};
doc.body=new FakeNode('body',doc);doc.body.style.overflow='';
doc.activeElement=new FakeNode('existing-button',doc);
doc.activeElement.isConnected=true;
const win={handlers:new Map(),addEventListener(name,fn){this.handlers.set(name,fn);},removeEventListener(name,fn){if(this.handlers.get(name)===fn)this.handlers.delete(name);}};
const context=vm.createContext({document:doc,window:win,Promise,console});
vm.runInContext(js.slice(helperBegin,helperEnd),context);
assert.equal(typeof context.rpConfirmFormDeletion,'function');

// Defaults are safe: focus cancel, no form mutation, reject on overlay or Escape.
let promise=context.rpConfirmFormDeletion({title:'Eliminar fotografia?',detail:'Teste'});
assert.equal(doc.body.children.length,1);
let overlay=doc.body.children[0];
assert.equal(overlay.attrs['aria-modal'],'true');
assert.equal(overlay.querySelector('[data-rp-confirm-cancel]'),doc.activeElement);
assert.equal(overlay.querySelector('#rp-delete-confirm-title').textContent,'Eliminar fotografia?');
assert.equal(await context.rpConfirmFormDeletion({title:'Blocked parallel tap'}),false);
overlay.fire('click',overlay.querySelector('[data-rp-confirm-cancel]'));
assert.equal(await promise,false);
assert.equal(doc.body.children.length,0);
assert.equal(doc.body.style.overflow,'');

promise=context.rpConfirmFormDeletion({title:'Eliminar produto?',confirm:'Sim, remover'});
overlay=doc.body.children[0];
const accept=overlay.querySelector('[data-rp-confirm-accept]');
assert.equal(accept.textContent,'Sim, remover');
overlay.fire('click',accept);
assert.equal(await promise,true);
assert.equal(doc.body.children.length,0);

promise=context.rpConfirmFormDeletion({title:'Eliminar ficheiro?'});
const keyEvent={key:'Escape',preventDefault(){this.prevented=true;},stopPropagation(){this.stopped=true;}};
doc.handlers.get('keydown')(keyEvent);
assert.equal(await promise,false);
assert(keyEvent.prevented&&keyEvent.stopped,'Escape should be consumed by dialog');

promise=context.rpConfirmFormDeletion({title:'Eliminar rascunho?'});
doc.visibilityState='hidden';doc.handlers.get('visibilitychange')();
assert.equal(await promise,false);
assert.equal(doc.body.children.length,0);
doc.visibilityState='visible';
console.log('FORM DELETION 9CT OK | photograph, Cardex, draft, media row | cancellation | opt-in | Escape | lifecycle | mobile CSS');
