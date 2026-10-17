import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=fs.readFileSync(new URL('../src/Shortcodes.php',import.meta.url),'utf8');
const start=source.indexOf('  let embeddedFormSequence=0;');
const end=source.indexOf('  async function submitEmbeddedForm(){',start);
assert.ok(start>=0&&end>start);
const els={slot:{innerHTML:'previous-form',querySelector:()=>null},stop:{value:'1'},route:{value:'10'},location:{value:'55'},client:{value:'12'},project:{value:'34'}};
const pending=[];
let flushes=0;
const context={els,nonce:'n',localToday:'2026-10-01',ownerAjaxUrl:'/ajax',window:{location:{href:'https://example.test'},FieldFlowOffline:null},
  FormData,selectedStopOption:()=>({dataset:{locationId:els.location.value}}),
  flushActiveFormDraft:()=>{flushes++;},syncSubmitAvailability:()=>{},syncResolvedContextToForm:()=>{},
  fetchOperationalNetwork:()=>new Promise(resolve=>pending.push(resolve)),
};
vm.createContext(context);
vm.runInContext(source.slice(start,end).replace(/<\?php[\s\S]*?\?>/g,'2.2.579')+'\nglobalThis.refresh=refreshEmbeddedForm;',context);
const first=context.refresh();
assert.equal(els.slot.innerHTML,'');
els.stop.value='2';els.location.value='56';
const second=context.refresh();
pending[1]({ok:true,text:async()=>JSON.stringify({success:true,data:{html:'STORE 56 latest history'}})});
await second;
pending[0]({ok:true,text:async()=>JSON.stringify({success:true,data:{html:'STORE 55 wrong history'}})});
await first;
assert.equal(els.slot.innerHTML,'STORE 56 latest history');
const third=context.refresh();
els.stop.value='';els.route.value='';els.location.value='';
await context.refresh();
pending[2]({ok:true,text:async()=>JSON.stringify({success:true,data:{html:'late old-store response'}})});
await third;
assert.equal(els.slot.innerHTML,'');
assert.ok(flushes>=4);
console.log('QUICK REPORT SELECTION 9BH RUNTIME OK: reversed responses, selection clear, draft flushed before swap');
