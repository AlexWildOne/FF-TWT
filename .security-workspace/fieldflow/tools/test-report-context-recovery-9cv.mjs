// 9CV live JavaScript classification + offline context protection tests.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const dir=path.resolve(import.meta.dirname,'..');
const source=fs.readFileSync(path.join(dir,'src/Shortcodes.php'),'utf8');
const forms=fs.readFileSync(path.join(dir,'src/Forms/Forms.php'),'utf8');
const focus=source.slice(source.indexOf('private static function report_visit_app('));
const get=(s,start,end)=>{
  const a=s.indexOf(start);assert(a>=0,'missing start: '+start);
  const b=s.indexOf(end,a+start.length);assert(b>a,'missing end: '+end);
  return s.slice(a,b);
};
const classification=get(focus,'  // 9CV fieldflow-report-context-recovery-9cv-v1: never expose','  const nowIso=');
const ctx=vm.createContext({});
vm.runInContext(classification+'\nthis.safeError=serverErrorMessage;this.databaseError=isReportDatabaseError;',ctx);
const samples=[
  'Error establishing a database connection This either means that the username and password information in your wp-config.php file is incorrect or that contact with the database server at 127.0.0.1 could not be established',
  '<!DOCTYPE html><html><body><h1>Error establishing a database connection</h1></body></html>',
  {data:{message:'Error establishing a database connection'}} ,
  'mysqli_real_connect(): (HY000/2002): Connection refused',
  'WordPress database error Deadlock in wpdb query',
  'SQLSTATE[HY000] [2002] No such file or directory',
];
for(const sample of samples){
  assert(ctx.databaseError(sample),'must identify database problem');
  const msg=ctx.safeError(sample);
  assert.match(msg,/base de dados está temporariamente indisponível/i);
  assert(!/127\.0\.0\.1|wp-config|username|mysqli|SQLSTATE|HY000|Database connection|WordPress database error/i.test(msg),'must hide details');
}
const html=ctx.safeError('<html><body><div>PHP Fatal error: on /home/secret/abc.php</div></body></html>');
assert(!html.includes('/home/secret'),'must never leak raw server HTML');
assert.equal(ctx.safeError({message:'Seleciona o motivo.'}),'Seleciona o motivo.','ordinary validation should remain');

const quick=get(source,"  const reportErrorMessage=(raw,fallback=",'  let toastTimer=null;');
const quickCtx=vm.createContext({});
vm.runInContext(quick+'\nthis.safeQuick=reportErrorMessage;',quickCtx);
for(const sample of samples){
  const msg=quickCtx.safeQuick(sample);
  assert(!/127\.0\.0\.1|wp-config|SQLSTATE|username|mysqli|WordPress database error/i.test(msg),'quick reporting must hide DB details');
}

const cache=get(focus,'      const matchesCachedContext=cached=>{','      let data=null;');
const cacheCtx=vm.createContext({visit:{client_id:8,project_id:9,route_id:10,stop_id:11,location_id:12},currentDate:'2026-10-10'});
vm.runInContext(cache+'\nthis.matches=matchesCachedContext;',cacheCtx);
const cached={has_form:true,html:'<form></form>',context:{client_id:8,project_id:9,route_id:10,stop_id:11,location_id:12,date:'2026-10-10'}};
assert.equal(cacheCtx.matches(cached),true,'exact same visit should recover');
for(const key of ['client_id','project_id','route_id','stop_id','location_id']){
  const bad=structuredClone(cached);bad.context[key]+=1;
  assert.equal(cacheCtx.matches(bad),false,key+' must isolate cached form');
}
const diffDay=structuredClone(cached);diffDay.context.date='2026-10-09';assert.equal(cacheCtx.matches(diffDay),false,'date isolation');
const noForm=structuredClone(cached);noForm.html=null;assert.equal(cacheCtx.matches(noForm),false,'HTML integrity');

assert(source.includes('const isOwnCachedForm=cached=>'),'quick report cache must be scoped');
assert(source.includes('reportErrorMessage(payload'),'quick report must hide SQL errors during submissions');
assert.match(focus,/reportUserId=<\?php echo \(int\)get_current_user_id\(\); \?>/);
assert(focus.includes("if(!sameLiveForm)els.slot.innerHTML=''"),'old form must disappear before new store');
assert(focus.includes('if(sameLiveForm&&els.slot.contains(existingForm))'),'existing draft must survive failed refresh');
assert(focus.includes('els.submit.hidden=priorSubmitHidden'),'retry failure must restore action buttons');
assert(focus.includes("if(previousDate===currentDate&&previousVisits.length)"),'stale different-day list must not be used');
assert(focus.includes("showReportRetry('form')")&&focus.includes("showReportRetry('visits')"),'recovery options');
assert(focus.includes("reportRetryLastAt<4000"),'avoid rapid retries');
assert(focus.includes("source:'manual-server-retry'"),'user-initiated retry');
assert(!focus.includes('setInterval(()=>renderForm'),'no automatic report request loop');
assert.match(forms,/fieldflow_report_context_unavailable/);
assert.match(forms, /wp_send_json_error\(\[\s*'code'\s*=>\s*'fieldflow_report_context_unavailable'/);
assert.match(forms,/\], 503\);/);
console.log('REPORT CONTEXT RECOVERY 9CV JS OK');
