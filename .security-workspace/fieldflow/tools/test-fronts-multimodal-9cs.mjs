// FieldFlow 9CS. Deterministic text parsing, syntax and budget-gate wiring.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
const root=path.resolve(import.meta.dirname,'..');
const renderer=fs.readFileSync(path.join(root,'src/Forms/FormRenderer.php'),'utf8');
const a=renderer.indexOf("return <<<'JS'");
const b=renderer.indexOf('\nJS;',a);
assert(a>=0 && b>a,'inline_js missing');
const js=renderer.slice(a+"return <<<'JS'".length,b);
new vm.Script(js);
const begin=js.indexOf('function rpVisionNormalizeText(');
const end=js.indexOf('function rpVisionRenderText(',begin);
assert(begin>=0 && end>begin,'local parser missing');
const code=js.slice(begin,end);
const context=vm.createContext({console});
vm.runInContext(code,context);
const rows=[
 {key:'r0',name:'Jameson Original 70cl',ref:'',capacity:'70'},
 {key:'r1',name:'Jameson Black Barrel 70cl',ref:'',capacity:'70'},
 {key:'r2',name:'Absolut Original 70cl',ref:'',capacity:'70'},
];
let result=context.rpVisionParseLines('cinco Jameson Original 70cl, tres Absolut Original 70cl',rows);
assert.equal(result.length,2);
assert.equal(result[0].qty,5);
assert.equal(result[0].choice,'r0');
assert.equal(result[1].qty,3);
assert.equal(result[1].choice,'r2');
result=context.rpVisionParseLines('zero Absolut Original 70cl',rows);
assert.equal(result[0].qty,0);
assert.equal(result[0].choice,'r2');
result=context.rpVisionParseLines('5 Jameson',rows);
assert.equal(result[0].candidates.length,2);
assert.equal(result[0].choice,'');
result=context.rpVisionParseLines('3 Produto Não Catalogado',rows);
assert.equal(result[0].choice,'');
assert.match(result[0].error,/não encontrado/i);
assert(js.includes('data-vision-manual="select"'),'manual UI missing');
assert(js.includes('data-vision-manual="text"'),'text UI missing');
assert(js.includes('data-vision-manual="voice"'),'voice UI missing');
assert(js.includes('x.onload=function(){if(st!==rpVisionState)return;var response=null;'),'await server confirm missing');
assert(js.includes('var cameraReady=!!(st.cameraAllowed&&navigator.onLine!==false);'),'camera gating missing');
console.log('FRONTS 9CS TEXT AND MULTIMODAL OK | parser | ambiguities | explicit zero | UI | confirmation');
