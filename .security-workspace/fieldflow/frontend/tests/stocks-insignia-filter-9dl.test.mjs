import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Exercise the script the WordPress shortcode actually emits, not a duplicate filter function.
const source = fs.readFileSync(new URL('../../src/Front/StocksShortcodeRenderer.php', import.meta.url), 'utf8');
const script = source.match(/<script>\s*([\s\S]*?)<\/script>/)?.[1]
  .replace(/<\?php echo wp_json_encode\(\$id\); \?>/g, '"ff-stock-test"')
  .replace(/<\?php echo wp_json_encode\(rest_url\('routespro\/v1\/stocks\/'\)\); \?>/g, '"https://example.test/api/stocks/"');
assert.ok(script?.includes('rebuildStoreOptions('));

const snapshot={
  available:true, insignias:['Sonae','Pingo Doce'], summary:{insignias:2,stores:2,products:3,stock_total:20,cross_insignia_rows:2},
  batches:[{insignia:'Sonae',snapshot_date:'2026-10-12'},{insignia:'Pingo Doce',snapshot_date:'2026-10-12'}],
  stores:[
    {store_key:'SONAE|1',insignia:'Sonae',location_id:1,name:'Continente Modelo Maia',product_count:1,stock_total:12,zero_count:1,low_count:0,negative_count:0,products:[{name:'Jameson',ean:'1',article_ref:'1',stock:12,status:'ok'}]},
    {store_key:'PINGO DOCE|2',insignia:'Pingo Doce',location_id:2,name:'Pingo Doce Gaia',product_count:2,stock_total:8,zero_count:0,low_count:1,negative_count:0,products:[{name:'Martini',ean:'2',article_ref:'2',stock:8,status:'ok'}]}
  ]
};

class FakeSelect {
  constructor(){this.value='';this.options=[];this.onchange=null;}
  set innerHTML(html){this.options=[...html.matchAll(/<option value="([^"]*)"/g)].map(m=>({value:m[1]}));this.value=this.options[0]?.value||'';}
  get innerHTML(){return '';}
}
const stateEl={hidden:false,innerHTML:''},content={hidden:true},kpis={innerHTML:''},meta={textContent:''},feedback={textContent:''},search={value:'',oninput:null};
const brand=new FakeSelect(),store=new FakeSelect();
let renderedCards=[];
const storesEl={set innerHTML(value){
  renderedCards=snapshot.stores.filter(x=>value.includes(`data-store-key="${x.store_key}"`)).map(s=>({
    hidden:false,dataset:{insignia:s.insignia,storeKey:s.store_key},
    querySelectorAll:()=>s.products.map(p=>({hidden:false,dataset:{search:(p.name+' '+p.ean+' '+p.article_ref).toLowerCase()}}))
  }));
},get innerHTML(){return '';}};
const nodes={
 '[data-state]':stateEl,'[data-content]':content,'[data-kpis]':kpis,'[data-stores]':storesEl,
 '[data-insignia-filter]':brand,'[data-store-filter]':store,'[data-search]':search,'[data-meta]':meta,'[data-filter-feedback]':feedback
};
const root={dataset:{nonce:'test'},id:'ff-stock-test',querySelector:(s)=>nodes[s],querySelectorAll:(s)=>s==='.ff-stock-store'?renderedCards:[],closest:()=>({classList:{contains:()=>true}})};
const documentListeners={};
const document={hidden:false,getElementById:(id)=>id==='ff-stock-test'?root:null,addEventListener:(key,fn)=>{documentListeners[key]=fn;}};
const window={addEventListener:()=>{},dispatchEvent:()=>{}};
const context=vm.createContext({document,window,CustomEvent:class{constructor(type,config){this.type=type;this.detail=config?.detail;}},URLSearchParams,Date,Intl,fetch:async(_url,options)=>({ok:true,json:async()=>options?.method==='POST'?{}:snapshot}),console});

test('9DL: switching Sonae / Pingo Doce filters stores, store dropdown, totals, and survives refresh',async()=>{
 vm.runInContext(script,context,{timeout:5000});
 documentListeners['routespro:panel-open']({detail:{panel:'stocks'}});
 await new Promise(r=>setTimeout(r,10));
 assert.equal(content.hidden,false,'snapshot loaded');
 assert.equal(renderedCards.filter(c=>!c.hidden).length,2);
 assert.equal(brand.options.length,3);
 brand.value='Pingo Doce';brand.onchange();
 assert.deepEqual(renderedCards.filter(c=>!c.hidden).map(c=>c.dataset.insignia),['Pingo Doce']);
 assert.equal(store.options.length,2,'only PD store in store dropdown');
 assert.match(kpis.innerHTML,/>8<\/strong>/,'PD stock total recomputed');
 assert.equal(meta.textContent,'Pingo Doce · 2026-10-12');
 brand.value='Sonae';brand.onchange();
 assert.deepEqual(renderedCards.filter(c=>!c.hidden).map(c=>c.dataset.insignia),['Sonae']);
 assert.equal(store.options.length,2,'only Sonae store');
 assert.match(kpis.innerHTML,/>12<\/strong>/,'Sonae stock total recomputed');
 documentListeners['routespro:panel-open']({detail:{panel:'stocks'}});
 await new Promise(r=>setTimeout(r,10));
 assert.equal(brand.value,'Sonae','selection survives refresh');
 assert.deepEqual(renderedCards.filter(c=>!c.hidden).map(c=>c.dataset.insignia),['Sonae']);
 assert.match(feedback.textContent,/2 linha\(s\).*incompatível/);
});
