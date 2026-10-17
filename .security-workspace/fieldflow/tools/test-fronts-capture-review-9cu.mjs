// 9CU contract: zero AI calls until the operative explicitly approves a captured image.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
const root=path.resolve(import.meta.dirname,'..');
const renderer=fs.readFileSync(path.join(root,'src/Forms/FormRenderer.php'),'utf8');
const a=renderer.indexOf("return <<<'JS'"),b=renderer.indexOf('\nJS;',a);
assert(a>=0&&b>a,'inline JS must exist');
const js=renderer.slice(a+"return <<<'JS'".length,b);
new vm.Script(js);
const getFunction=(name,nextName)=>{
  const begin=js.indexOf('  function '+name+'(');
  const end=js.indexOf('  function '+nextName+'(',begin+1);
  assert(begin>=0&&end>begin,`cannot isolate ${name}`);
  return js.slice(begin,end);
};
assert.match(js,/fieldflow-fronts-capture-review-9cu-v1/);
assert(js.includes('data-vision-analyze'),'explicit analyze button must be present');
assert(js.includes('rpVisionReviewCapture(st,blob,capture)'),'photo must enter preview state');
assert(js.includes("rpVisionReviewCapture(st,blob,'live')"),'live capture must enter preview');
assert(js.includes("fd.append('capture_id',String(captureId))"),'replay receipt missing');
assert(js.includes('st.values[it.key]=it.needs_review||'),'uncertain values must not pre-fill a form');
assert(js.includes('data-vision-select-product'),'product subset selection must be available');
assert(js.includes('selected.length<12'),'product subset limit must be enforced locally');
assert(js.includes('st.pendingAttempted=true'),'once a request is launched, repeat attempts must be explicit');
assert(js.includes('Esta fotografia já teve uma tentativa'),'the UI must disclose possible repeat charges');

const live=js.slice(js.indexOf('  function rpVisionLiveCapture('),js.indexOf('  function rpVisionRetryLabel('));
assert(live.includes("rpVisionReviewCapture(st,blob,'live')"),'live preview needed');
assert(!live.includes('rpVisionSend('),'live must never call paid provider directly');
const fileChange=js.slice(js.indexOf("  $(document).on('change','.routespro-vision-sheet [data-vision-camera]"),js.indexOf("  $(document).on('click','.routespro-vision-sheet [data-vision-step]"));
assert(fileChange.includes('rpVisionReviewCapture(st,blob,capture)'),'camera/gallery must require review');
assert(!fileChange.includes('rpVisionSend('),'file change must never auto-bill');

// Only an explicit user action can send, and a second press is ignored.
let callCount=0,readingCount=0,errors=[];
const ctx=vm.createContext({
  rpVisionState:null, navigator:{onLine:true},
  rpVisionCurrentItems:(st)=>st.selectedKeys.map(key=>({key})),
  rpVisionRenderCaptureReview:()=>{},rpVisionRenderError:(msg)=>errors.push(msg),
  rpVisionRenderReading:()=>{readingCount++;},
  rpVisionSend:(blob,capture,token)=>{callCount++;assert.equal(capture,'camera');assert.equal(token,'unique-capture-id');},
});
vm.runInContext(getFunction('rpVisionApproveAnalysis','rpVisionLiveCapture'),ctx);
ctx.rpVisionState={pendingBlob:{data:1},pendingCapture:'camera',pendingId:'unique-capture-id',analyzing:false,xhr:null,selectedKeys:['r0','r1']};
ctx.rpVisionApproveAnalysis();ctx.rpVisionApproveAnalysis();
assert.equal(callCount,1,'double click must not trigger another paid call');
assert.equal(readingCount,1);
ctx.rpVisionState.analyzing=false;ctx.rpVisionState.pendingBlob=null;
ctx.rpVisionApproveAnalysis();assert.equal(callCount,1,'no image, no paid call');
ctx.rpVisionState.pendingBlob={data:2};ctx.navigator.onLine=false;
ctx.rpVisionApproveAnalysis();assert.equal(callCount,1,'offline must not trigger a paid call');
assert.equal(errors.length,1);

// Selection is sent as a subset of the official Cardex, never an invented SKU.
const selectorCtx=vm.createContext({});
vm.runInContext(js.slice(js.indexOf('  function rpVisionCurrentItems('),js.indexOf('  function rpVisionRenderChoose(')),selectorCtx);
const products={type:'product_matrix',group:0,groups:[{rows:[{key:'r0',name:'Jameson',cardex_id:2},{key:'r1',name:'Absolut',cardex_id:3},{key:'r2',name:'Ballantines',cardex_id:4}]}],selectedKeys:['r0','r2']};
const current=selectorCtx.rpVisionCurrentItems(products);
assert.equal(current.length,2);assert.equal(current[0].key,'r0');assert.equal(current[1].key,'r2');

// Quality gate is LOCAL (no network), and warns on low-resolution and dark shots.
const qualityCode=js.slice(js.indexOf('  function rpVisionAssessCapture('),js.indexOf('  function rpVisionCaptureId('));
const qualityContext=vm.createContext({
  Blob,Promise,Uint8Array,Math,Number,
  URL:{createObjectURL:()=> 'blob:preview',revokeObjectURL:()=>{}},
  Image:class {naturalWidth=600;naturalHeight=450;set src(_) {this.onload();}},
  document:{createElement:()=>({width:0,height:0,getContext:()=>({drawImage:()=>{},getImageData:()=>({data:new Uint8Array(128*96*4).fill(10)})})})},
});
vm.runInContext(qualityCode,qualityContext);
const quality=await qualityContext.rpVisionAssessCapture(new Blob(['photo'],{type:'image/jpeg'}));
assert(quality.warnings.some(w=>/resolução/i.test(w)),'resolution warning missing');
assert(quality.warnings.some(w=>/escura/i.test(w)),'lighting warning missing');
assert.equal(callCount,1,'quality analysis must never call network');
console.log('FRONTS CAPTURE REVIEW 9CU JS OK | explicit opt-in | photo preview | live preview | 12-SKU subset | local quality | no silent paid calls');
