(function(){
  'use strict';
  const previousApi=(window.FieldFlowOffline&&typeof window.FieldFlowOffline==='object')?window.FieldFlowOffline:{};
  const RUNTIME_BUILD='2.2.498';
  if(previousApi.__runtimeBuild===RUNTIME_BUILD) return;

  const config=window.FieldFlowOfflineConfig||{};
  const USER_ID=Math.max(0,parseInt(config.userId||0,10));
  const DB_NAME='fieldflow-offline-v1-u'+String(USER_ID||'guest');
  const DB_VERSION=2;
  const CACHE_STORE='cache';
  const QUEUE_STORE='queue';
  const META_STORE='meta';
  const TAB_ID='tab-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,10);
  const SYNC_LOCK_KEY='fieldflow:offline:sync-lock:'+String(USER_ID||'guest');
  const SYNC_WEB_LOCK_NAME='fieldflow-offline-sync-'+String(USER_ID||'guest');
  const SYNC_LOCK_TTL=Math.max(120000,parseInt(config.syncLockTtlMs||120000,10)||120000);
  const SYNC_LOCK_HEARTBEAT=Math.min(30000,Math.max(5000,parseInt(config.syncLockHeartbeatMs||10000,10)||10000));
  const FORM_ACK_PROTOCOL='fieldflow-form-ack-v1';
  const OFFLINE_TRUST_PROTOCOL='fieldflow-offline-trust-v1';
  const MEDIA_INTEGRITY_PROTOCOL='fieldflow-media-integrity-v1';
  const RESUMABLE_MEDIA_PROTOCOL='fieldflow-resumable-media-v1';
  const MAX_MEDIA_FILES=300;
  const TRUST_LEASE_META='offline-trust-lease';
  let dbPromise=null;
  let syncPromise=null;
  let banner=null;
  let center=null;
  let deviceRevoked=false;
  let policyTimer=null;
  let lastEndpoint='';
  let lastErrorCode='';
  let lastHttpStatus=0;
  let lastSyncResult='idle';
  let lastSyncAt=0;
  let connectivityState=navigator.onLine===false?'offline':'unknown';
  let lastProbeAt=0;
  let lastProbeLatency=0;
  let consecutiveProbeFailures=0;
  let trustLeaseCache=null;
  let currentOperationalDate=String(config.operationalDate||'');
  let syncLeaseActive=false;
  let syncLeaseToken='';
  let syncLockHeartbeatTimer=null;
  let webLockRelease=null;
  let webLockRequestPromise=null;
  const HISTORY_LIMIT=24;
  let telemetry={
    degraded_total_ms:0,degraded_events:0,degraded_since:0,
    ack_error_count:0,last_ack_error_at_ms:0,
    auth_required_count:0,app_resume_count:0,recovery_count:0,last_recovery_at_ms:0,
    sync_confirmed_count:0,last_confirmed_at_ms:0,
    resumable_uploaded_chunks:0,resumable_resumed_chunks:0,resumable_completed_files:0
  };

  function persistTelemetry(){setMeta('operations-intelligence',telemetry).catch(()=>{});}
  function typedOfflineCore(){
    const frontend=window.FieldFlowFrontend&&typeof window.FieldFlowFrontend==='object'?window.FieldFlowFrontend:null;
    const core=frontend&&frontend.offlineCore&&typeof frontend.offlineCore==='object'?frontend.offlineCore:null;
    return core&&core.contract==='fieldflow-typed-offline-core-v1'?core:null;
  }
  function noteTelemetry(counterKey,timeKey){
    telemetry[counterKey]=Math.max(0,parseInt(telemetry[counterKey]||0,10))+1;
    if(timeKey)telemetry[timeKey]=now();
    persistTelemetry();
  }
  function observeConnectivity(next){
    next=String(next||'unknown');
    const previous=connectivityState;
    if(previous==='degraded'&&next!=='degraded'&&telemetry.degraded_since){
      telemetry.degraded_total_ms=Math.max(0,parseInt(telemetry.degraded_total_ms||0,10)) + Math.max(0,now()-parseInt(telemetry.degraded_since||0,10));
      telemetry.degraded_since=0;
    }
    if(next==='degraded'&&previous!=='degraded'){telemetry.degraded_events=Math.max(0,parseInt(telemetry.degraded_events||0,10))+1;telemetry.degraded_since=now();}
    if(next==='auth_required'&&previous!=='auth_required')noteTelemetry('auth_required_count');
    connectivityState=next;persistTelemetry();return connectivityState;
  }
  function uuid(prefix){
    if(window.crypto&&typeof window.crypto.randomUUID==='function') return (prefix||'ff')+'-'+window.crypto.randomUUID();
    return (prefix||'ff')+'-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,12);
  }
  function now(){return Date.now();}
  function sleep(ms){return new Promise(resolve=>window.setTimeout(resolve,Math.max(0,parseInt(ms||0,10)||0)));}
  function isUsableConnectivity(state){
    const core=typedOfflineCore();
    if(core&&core.connectivity&&typeof core.connectivity.isUsable==='function')return !!core.connectivity.isUsable(state||connectivityState,navigator.onLine!==false);
    return ['online','degraded'].includes(String(state||connectivityState));
  }
  function validYmd(value){return /^\d{4}-\d{2}-\d{2}$/.test(String(value||''));}
  function deviceLocalDate(){
    const local=new Date();
    const value=String(local.getFullYear()).padStart(4,'0')+'-'+String(local.getMonth()+1).padStart(2,'0')+'-'+String(local.getDate()).padStart(2,'0');
    return validYmd(value)?value:'';
  }
  function siteOperationalDate(){
    const timezone=String(config.siteTimezone||'');
    if(timezone&&window.Intl&&typeof Intl.DateTimeFormat==='function'){
      try{
        const parts=new Intl.DateTimeFormat('en-CA',{timeZone:timezone,year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());
        const map={};parts.forEach(part=>{if(part&&part.type)map[part.type]=part.value;});
        const value=String(map.year||'')+'-'+String(map.month||'')+'-'+String(map.day||'');
        if(validYmd(value))return value;
      }catch(_){return '';}
    }
    return timezone?'':deviceLocalDate();
  }
  function operationalDate(){
    const siteDate=siteOperationalDate();
    if(validYmd(siteDate)){currentOperationalDate=siteDate;return currentOperationalDate;}
    if(validYmd(currentOperationalDate))return currentOperationalDate;
    if(validYmd(config.operationalDate))return String(config.operationalDate);
    return deviceLocalDate();
  }
  function addOperationalDays(value,days){
    const source=validYmd(value)?String(value):operationalDate();
    const match=/^(\d{4})-(\d{2})-(\d{2})$/.exec(source||'');
    if(!match)return source;
    const date=new Date(Date.UTC(parseInt(match[1],10),parseInt(match[2],10)-1,parseInt(match[3],10)+parseInt(days||0,10)));
    return String(date.getUTCFullYear()).padStart(4,'0')+'-'+String(date.getUTCMonth()+1).padStart(2,'0')+'-'+String(date.getUTCDate()).padStart(2,'0');
  }
  function deviceId(){
    const key='fieldflow:offline:device-id';
    try{
      let value=localStorage.getItem(key)||'';
      if(!value){value=uuid('web');localStorage.setItem(key,value);}
      return value;
    }catch(_){return uuid('web');}
  }
  const DEVICE_ID=deviceId();

  function openDb(){
    if(dbPromise) return dbPromise;
    dbPromise=new Promise((resolve,reject)=>{
      if(!window.indexedDB){reject(new Error('indexeddb_unavailable'));return;}
      const request=indexedDB.open(DB_NAME,DB_VERSION);
      request.onupgradeneeded=()=>{
        const db=request.result;
        if(!db.objectStoreNames.contains(CACHE_STORE)) db.createObjectStore(CACHE_STORE,{keyPath:'key'});
        if(!db.objectStoreNames.contains(META_STORE)) db.createObjectStore(META_STORE,{keyPath:'key'});
        const queue=db.objectStoreNames.contains(QUEUE_STORE)?request.transaction.objectStore(QUEUE_STORE):db.createObjectStore(QUEUE_STORE,{keyPath:'id'});
        [['status','status'],['createdAt','createdAt'],['kind','kind'],['nextAttemptAt','nextAttemptAt'],['entityKey','entityKey']].forEach(pair=>{
          if(!queue.indexNames.contains(pair[0])) queue.createIndex(pair[0],pair[1],{unique:false});
        });
      };
      request.onsuccess=()=>resolve(request.result);
      request.onerror=()=>reject(request.error||new Error('indexeddb_open_failed'));
    });
    return dbPromise;
  }
  function tx(store,mode,work){
    return openDb().then(db=>new Promise((resolve,reject)=>{
      const transaction=db.transaction(store,mode);
      const objectStore=transaction.objectStore(store);
      let result;
      try{result=work(objectStore,transaction);}catch(error){reject(error);return;}
      transaction.oncomplete=()=>resolve(result);
      transaction.onerror=()=>reject(transaction.error||new Error('indexeddb_transaction_failed'));
      transaction.onabort=()=>reject(transaction.error||new Error('indexeddb_transaction_aborted'));
    }));
  }
  function requestResult(request){return new Promise((resolve,reject)=>{request.onsuccess=()=>resolve(request.result);request.onerror=()=>reject(request.error||new Error('indexeddb_request_failed'));});}
  function put(store,value){return tx(store,'readwrite',s=>s.put(value));}
  function remove(store,key){return tx(store,'readwrite',s=>s.delete(key));}
  function get(store,key){return openDb().then(db=>requestResult(db.transaction(store,'readonly').objectStore(store).get(key)));}
  function all(store){return openDb().then(db=>requestResult(db.transaction(store,'readonly').objectStore(store).getAll()));}
  function clearStore(store){return tx(store,'readwrite',s=>s.clear());}

  function normaliseUrl(input){
    try{
      const url=new URL(String(input),window.location.href);url.hash='';
      ['_ff','_','cache_bust','cacheBust','ts','timestamp'].forEach(key=>url.searchParams.delete(key));
      url.searchParams.sort();
      return url.toString();
    }catch(_){return String(input);}
  }
  function cacheKey(input){return 'GET:'+normaliseUrl(input);}
  async function cacheResponse(url,data,headers){
    const ttl=Math.max(60,parseInt(config.cacheTtlSeconds||43200,10))*1000;
    await put(CACHE_STORE,{key:cacheKey(url),url:normaliseUrl(url),data:data,headers:headers||{},storedAt:now(),expiresAt:now()+ttl});
    return data;
  }
  async function cachedResponse(url,allowExpired){
    const row=await get(CACHE_STORE,cacheKey(url)).catch(()=>null);
    if(!row) return null;
    if(!allowExpired&&row.expiresAt&&row.expiresAt<now()) return null;
    return row;
  }
  async function fetchWithTimeout(url,opts,timeoutMs){
    opts=Object.assign({},opts||{});
    const timeout=Math.max(1000,parseInt(timeoutMs||0,10)||0);
    if(!timeout||!('AbortController' in window)) return window.fetch(url,opts);
    const controller=new AbortController();
    const timer=window.setTimeout(()=>controller.abort(),timeout);
    opts.signal=controller.signal;
    try{return await window.fetch(url,opts);}catch(error){
      if(error&&error.name==='AbortError'){
        const wrapped=new Error('A ligacao ao FieldFlow excedeu o tempo de resposta seguro.');
        wrapped.code='fieldflow_network_timeout';wrapped.status=0;wrapped.retryable=true;wrapped.cause=error;throw wrapped;
      }
      if(error&&typeof error==='object'){error.retryable=true;if(!error.code)error.code='fieldflow_network_error';}
      throw error;
    }finally{window.clearTimeout(timer);}
  }
  async function parseNetworkResponse(response,options){
    options=options||{};
    const text=await response.text();
    lastHttpStatus=response.status||0;
    let data=null;
    let parsed=false;
    if(text===''){data={};parsed=true;}
    else{try{data=JSON.parse(text);parsed=true;}catch(_){parsed=false;}}
    if(!parsed){
      const error=new Error(response.ok?'O servidor respondeu sem uma confirmacao FieldFlow valida.':'O servidor devolveu uma resposta invalida.');
      error.status=response.status||0;error.code='fieldflow_invalid_json_response';error.retryable=true;error.responseText=String(text||'').slice(0,300);
      lastErrorCode=error.code;throw error;
    }
    if(options.requireObject&&(!data||typeof data!=='object')){
      const error=new Error('A resposta do FieldFlow nao tem o formato esperado.');
      error.status=response.status||0;error.code='fieldflow_invalid_json_payload';error.retryable=true;lastErrorCode=error.code;throw error;
    }
    if(!response.ok){
      const error=new Error((data&&data.message)||(data&&data.data&&data.data.message)||'Falha no pedido.');
      error.status=response.status;error.payload=data;error.code=(data&&data.code)||(data&&data.data&&data.data.code)||'';
      error.retryable=!error.status||error.status>=500||[408,425,429].includes(error.status);
      lastErrorCode=error.code||'http_'+String(response.status||0);
      throw error;
    }
    lastErrorCode='';
    return {data,response};
  }
  function slowNetworkHint(){
    const info=networkInfo();
    return ['slow-2g','2g'].includes(String(info.effective_type||'').toLowerCase())||info.save_data===true;
  }
  async function probeConnectivity(force){
    if(navigator.onLine===false){observeConnectivity('offline');lastProbeAt=now();lastProbeLatency=0;return {state:connectivityState,latency:0};}
    const freshFor=Math.max(3000,parseInt(config.probeFreshMs||15000,10)||15000);
    if(!force&&lastProbeAt&&now()-lastProbeAt<freshFor&&connectivityState!=='unknown') return {state:connectivityState,latency:lastProbeLatency};
    if(!config.probeEndpoint){observeConnectivity(slowNetworkHint()?'degraded':'online');lastProbeAt=now();return {state:connectivityState,latency:lastProbeLatency};}
    const started=now();
    try{
      lastEndpoint=config.probeEndpoint;
      const response=await fetchWithTimeout(config.probeEndpoint,{method:'GET',credentials:'same-origin',headers:{'X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID},cache:'no-store'},Math.max(2000,parseInt(config.probeTimeoutMs||4500,10)||4500));
      const parsed=await parseNetworkResponse(response,{requireObject:true});
      const data=parsed.data||{};
      if(data.ok!==true){const error=new Error('O probe de conectividade nao foi confirmado.');error.code='fieldflow_probe_invalid';error.status=response.status||0;error.retryable=true;throw error;}
      if(validYmd(data.operational_date))currentOperationalDate=String(data.operational_date);
      lastProbeLatency=Math.max(0,now()-started);lastProbeAt=now();consecutiveProbeFailures=0;
      if(data.policy&&typeof data.policy==='object')await applyDevicePolicy(data.policy);
      const degradedAfter=Math.max(1200,parseInt(config.degradedLatencyMs||2800,10)||2800);
      observeConnectivity((lastProbeLatency>=degradedAfter||slowNetworkHint())?'degraded':'online');
    }catch(error){
      lastProbeLatency=Math.max(0,now()-started);lastProbeAt=now();consecutiveProbeFailures++;
      const code=String(error&&error.code||error&&error.payload&&error.payload.code||'');
      if((error&&error.status)===401){
        observeConnectivity('auth_required');
        document.dispatchEvent(new CustomEvent('fieldflow:auth-required',{detail:{status:401,code:code||'fieldflow_auth_required'}}));
      }else if((error&&error.status)===403&&code==='fieldflow_device_revoked'){
        observeConnectivity('revoked');showDeviceRevoked((error&&error.payload&&error.payload.policy)||{blocked:true,status:403});
      }else{
        observeConnectivity(navigator.onLine===false?'offline':'degraded');
      }
      lastErrorCode=code||String(error&&error.code||'fieldflow_probe_failed');
    }
    return {state:connectivityState,latency:lastProbeLatency,failures:consecutiveProbeFailures};
  }
  async function fetchJson(url,opts){
    if(deviceRevoked){const error=new Error('A sess\u00e3o deste dispositivo foi terminada.');error.code='fieldflow_device_revoked';error.status=403;throw error;}
    opts=Object.assign({},opts||{});
    const method=String(opts.method||'GET').toUpperCase();
    const useCache=method==='GET';
    const headers=Object.assign({},opts.headers||{});
    if(config.nonce&&!headers['X-WP-Nonce']) headers['X-WP-Nonce']=config.nonce;
    headers['X-FieldFlow-Device-ID']=DEVICE_ID;
    opts.headers=headers;
    opts.credentials=opts.credentials||'same-origin';
    lastEndpoint=normaliseUrl(url);
    if(navigator.onLine!==false){
      try{
        const parsed=await parseNetworkResponse(await fetchWithTimeout(url,opts,Math.max(2500,parseInt(config.requestTimeoutMs||7000,10)||7000)),{requireObject:true});
        if(useCache) await cacheResponse(url,parsed.data,{}).catch(()=>{});
        return parsed.data;
      }catch(error){
        const status=parseInt(error&&error.status||0,10);
        const terminalClient=status>=400&&status<500&&![408,425,429].includes(status);
        if(!useCache||terminalClient) throw error;
      }
    }
    if(useCache){
      const row=await cachedResponse(url,true);
      if(row){
        const data=row.data&&typeof row.data==='object'?Object.assign({},row.data):row.data;
        if(data&&typeof data==='object') data._fieldflow_offline={cached:true,stored_at:row.storedAt,expired:!!(row.expiresAt&&row.expiresAt<now())};
        return data;
      }
    }
    const error=new Error('Sem liga\u00e7\u00e3o e sem dados guardados para este ecr\u00e3.');error.code='fieldflow_offline_cache_miss';lastErrorCode=error.code;throw error;
  }
  async function offlineFetch(url,opts){
    const method=String((opts&&opts.method)||'GET').toUpperCase();
    if(method!=='GET') return window.fetch(url,opts);
    try{
      const data=await fetchJson(url,opts);
      return new Response(JSON.stringify(data),{status:200,headers:{'Content-Type':'application/json','X-FieldFlow-Offline':data&&data._fieldflow_offline?'1':'0'}});
    }catch(error){
      return new Response(JSON.stringify({code:error.code||'fieldflow_offline_failed',message:error.message}),{status:error.status||503,headers:{'Content-Type':'application/json'}});
    }
  }

  const ZERO_BYTE_MEDIA_PLACEHOLDER_POLICY='fieldflow-zero-byte-file-placeholder-filter-v1';
  const ZERO_BYTE_MEDIA_RECOVERY_POLICY='fieldflow-zero-byte-media-draft-recovery-v1';
  function isUploadableBlob(value){return value instanceof Blob&&Math.max(0,Number(value.size)||0)>0;}
  function isUploadableBlobEntry(entry){return !!(entry&&entry.type==='blob'&&isUploadableBlob(entry.value));}
  function scrubZeroByteMediaPlaceholders(entries){
    let removed=0;
    const cleaned=(Array.isArray(entries)?entries:[]).filter(entry=>{
      if(entry&&entry.type==='blob'&&entry.value instanceof Blob&&!isUploadableBlob(entry.value)){removed++;return false;}
      return true;
    });
    return {entries:cleaned,removed};
  }
  function zeroByteMediaFields(entries){
    const fields=[];
    (Array.isArray(entries)?entries:[]).forEach(entry=>{if(entry&&entry.type==='blob'&&entry.value instanceof Blob&&!isUploadableBlob(entry.value)&&entry.key)fields.push(String(entry.key));});
    return Array.from(new Set(fields));
  }
  function queueFormDraftKey(item){
    const entries=item&&Array.isArray(item.entries)?item.entries:[];
    const value=key=>entryValue(entries,key)||'';
    const parts=[String(USER_ID||0),value('routespro_form_id')||String(item&&item.formId||0),value('routespro_client_id')||'0',value('routespro_project_id')||'0',value('routespro_route_id')||'0',value('routespro_stop_id')||value('routespro_route_stop_id')||'0',value('routespro_location_id')||String(item&&item.meta&&item.meta.location_id||0),value('routespro_report_day')||String(item&&item.meta&&item.meta.report_day||'sem-data')];
    return 'fieldflow:draft:v2:'+parts.map(part=>encodeURIComponent(String(part))).join(':');
  }
  async function readDurableDraftMedia(draftKey){
    if(!window.indexedDB||!draftKey)return [];
    return new Promise(resolve=>{
      let request;try{request=indexedDB.open('fieldflow-form-drafts');}catch(_){resolve([]);return;}
      request.onupgradeneeded=()=>{try{request.transaction.abort();}catch(_){ }};
      request.onerror=()=>resolve([]);
      request.onsuccess=()=>{
        const db=request.result;if(!db.objectStoreNames.contains('files')){try{db.close();}catch(_){}resolve([]);return;}
        const rows=[];let tx;try{tx=db.transaction('files','readonly');}catch(_){try{db.close();}catch(__){}resolve([]);return;}
        const store=tx.objectStore('files');if(!store.indexNames.contains('draftKey')){try{db.close();}catch(_){}resolve([]);return;}
        const cursor=store.index('draftKey').openCursor(IDBKeyRange.only(draftKey));
        cursor.onsuccess=()=>{const row=cursor.result;if(row){rows.push(row.value);row.continue();}};
        cursor.onerror=()=>{};tx.oncomplete=()=>{try{db.close();}catch(_){}resolve(rows);};tx.onerror=()=>{try{db.close();}catch(_){}resolve([]);};tx.onabort=()=>{try{db.close();}catch(_){}resolve([]);};
      };
    });
  }
  function mediaBlobSignature(key,name,blob,lastModified){return [String(key||''),String(name||''),Math.max(0,Number(blob&&blob.size)||0),Math.max(0,Number(lastModified)||0),String(blob&&blob.type||'')].join('|');}
  async function recoverDraftMediaForQueueItem(item,fieldNames){
    if(!item||item.kind!=='form')return 0;
    const fields=new Set((Array.isArray(fieldNames)?fieldNames:[]).map(String).filter(Boolean));
    const rows=await readDurableDraftMedia(queueFormDraftKey(item));if(!rows.length)return 0;
    const entries=Array.isArray(item.entries)?item.entries:[];const signatures=new Set();const fieldsWithMedia=new Set();
    entries.forEach(entry=>{if(isUploadableBlobEntry(entry)){signatures.add(mediaBlobSignature(entry.key,entry.name,entry.value,entry.lastModified));fieldsWithMedia.add(String(entry.key||''));}});
    let recovered=0;
    rows.sort((a,b)=>String(a.inputName||'').localeCompare(String(b.inputName||''))||(parseInt(a.ordinal||0,10)-parseInt(b.ordinal||0,10))||(parseInt(a.fileIndex||0,10)-parseInt(b.fileIndex||0,10)));
    for(const row of rows){
      const key=String(row&&row.inputName||'');const blob=row&&row.blob;
      if(!key||!isUploadableBlob(blob))continue;
      if(fields.size&& !fields.has(key))continue;
      const sig=mediaBlobSignature(key,row.name,blob,row.lastModified);if(signatures.has(sig))continue;
      entries.push({key,type:'blob',value:blob,name:String(row.name||'ficheiro'),mime:String(row.type||blob.type||''),lastModified:Math.max(0,parseInt(row.lastModified||0,10)||0)});signatures.add(sig);fieldsWithMedia.add(key);recovered++;
    }
    item.entries=entries;return recovered;
  }
  async function serialiseForm(form){
    const fd=form instanceof FormData?form:new FormData(form);
    const entries=[];
    const managedFields=new Set(['routespro_ajax','routespro_offline_replay','routespro_offline_mutation_id','routespro_offline_trust_lease','routespro_offline_media_manifest','routespro_resumable_media_refs']);
    for(const pair of fd.entries()){
      const key=pair[0],value=pair[1];
      if(managedFields.has(key)) continue;
      if(value instanceof Blob){
        // Browsers, especially Android WebView/Chrome, can expose an empty File
        // placeholder for every unselected <input type=file>. It is not media.
        if(!isUploadableBlob(value))continue;
        entries.push({key,type:'blob',value:value,name:value.name||'ficheiro',mime:value.type||'',lastModified:value.lastModified||now()});
      }else entries.push({key,type:'text',value:String(value)});
    }
    return entries;
  }
  function formDataFromEntries(entries,options){
    options=options||{};
    const skipBlobIndexes=new Set(Array.isArray(options.skipBlobIndexes)?options.skipBlobIndexes:[]);
    const fd=new FormData();let blobIndex=0;
    (entries||[]).forEach(entry=>{
      if(entry.type==='blob'){
        if(!isUploadableBlob(entry.value))return;
        const currentBlobIndex=blobIndex++;
        if(skipBlobIndexes.has(currentBlobIndex))return;
        let file=entry.value;
        if(!(file instanceof File)) file=new File([file],entry.name||'ficheiro',{type:entry.mime||'',lastModified:entry.lastModified||now()});
        fd.append(entry.key,file,file.name);
      }else fd.append(entry.key,String(entry.value==null?'':entry.value));
    });
    return fd;
  }
  function serialisedBytes(entries){
    return (entries||[]).reduce((total,entry)=>{
      if(isUploadableBlobEntry(entry)) return total+(entry.value.size||0);
      return total+String(entry&&entry.value||'').length*2;
    },0);
  }
  function trustLeasePayload(token){
    try{
      const part=String(token||'').split('.')[0]||'';if(!part)return null;
      const padded=part.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-part.length%4)%4);
      const json=decodeURIComponent(Array.prototype.map.call(atob(padded),ch=>'%'+('00'+ch.charCodeAt(0).toString(16)).slice(-2)).join(''));
      const payload=JSON.parse(json);return payload&&typeof payload==='object'?payload:null;
    }catch(_){return null;}
  }
  async function trustLeaseState(){
    let row=trustLeaseCache;if(!row){row=await getMeta(TRUST_LEASE_META).catch(()=>null);trustLeaseCache=row&&typeof row==='object'?row:null;}
    row=trustLeaseCache||{state:'missing',token:'',expires_at_ms:0,release:''};
    const exp=parseInt(row.expires_at_ms||0,10)||0;
    let state=String(row.state||'missing');
    if(row.token&&exp>0&&exp<=now())state='expired';
    else if(row.token&&exp>now()&&state!=='pending')state='valid';
    return Object.assign({},row,{state,expires_at_ms:exp});
  }
  async function refreshTrustLease(force){
    const current=await trustLeaseState();
    if(!config.trustLeaseEndpoint||!isUsableConnectivity(connectivityState))return current;
    const refreshAt=parseInt(current.refresh_after_ms||0,10)||0;
    if(!force&&current.state==='valid'&&refreshAt>now())return current;
    try{
      lastEndpoint=config.trustLeaseEndpoint;
      const response=await fetchWithTimeout(config.trustLeaseEndpoint,{method:'GET',credentials:'same-origin',headers:{'X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID},cache:'no-store'},Math.max(3000,parseInt(config.requestTimeoutMs||7000,10)||7000));
      const parsed=await parseNetworkResponse(response,{requireObject:true});const data=parsed.data||{};
      if(data.ok!==true){return current;}
      if(data.eligible===true&&String(data.lease||'')!==''){
        const token=String(data.lease||'');const claims=trustLeasePayload(token)||{};
        const next={state:'valid',protocol:String(data.protocol||OFFLINE_TRUST_PROTOCOL),token,issued_at_ms:(parseInt(data.issued_at||claims.iat||0,10)||0)*1000,expires_at_ms:(parseInt(data.expires_at||claims.exp||0,10)||0)*1000,refresh_after_ms:(parseInt(data.refresh_after||claims.refresh_after||0,10)||0)*1000,release:String(data.release||claims.release||''),certified_at:String(data.certified_at||claims.certified_at||''),certification_fingerprint_sha256:String(data.certification_fingerprint_sha256||claims.cert_fp||''),refreshed_at_ms:now()};
        trustLeaseCache=next;await setMeta(TRUST_LEASE_META,next).catch(()=>{});return next;
      }
      const pending={state:'pending',protocol:String(data.protocol||OFFLINE_TRUST_PROTOCOL),token:'',expires_at_ms:0,refresh_after_ms:now()+15*60*1000,release:String(data.release||config.version||''),reason:String(data.reason||'stable_revalidation_pending'),refreshed_at_ms:now()};
      trustLeaseCache=pending;await setMeta(TRUST_LEASE_META,pending).catch(()=>{});return pending;
    }catch(_){return current;}
  }
  async function sha256Blob(blob){
    if(!blob||!window.crypto||!window.crypto.subtle||typeof blob.arrayBuffer!=='function')return '';
    try{const digest=await window.crypto.subtle.digest('SHA-256',await blob.arrayBuffer());return Array.from(new Uint8Array(digest)).map(v=>v.toString(16).padStart(2,'0')).join('');}catch(_){return '';}
  }
  async function buildMediaManifest(entries,withHashes){
    const files=(entries||[]).filter(isUploadableBlobEntry).slice(0,MAX_MEDIA_FILES);
    const manifest=[];let totalBytes=0,hashedFiles=0;const hashFiles=withHashes!==false;
    for(let fileIndex=0;fileIndex<files.length;fileIndex++){
      const entry=files[fileIndex];
      const size=Math.max(0,parseInt(entry.value.size||0,10)||0);totalBytes+=size;
      const sha=hashFiles?await sha256Blob(entry.value):'';if(sha)hashedFiles++;
      manifest.push({field_name:String(entry.key||'').slice(0,190),file_index:fileIndex,name:String(entry.name||'ficheiro').slice(0,180),size,type:String(entry.mime||entry.value.type||'').slice(0,120),last_modified:Math.max(0,parseInt(entry.lastModified||0,10)||0),sha256:sha});
    }
    return {protocol:MEDIA_INTEGRITY_PROTOCOL,file_count:manifest.length,total_bytes:totalBytes,hashed_files:hashedFiles,files:manifest};
  }

  function resumableMediaBase(){return String(config.resumableMediaEndpoint||'').replace(/\/$/,'');}
  async function resumableJson(url,opts,timeout){
    const response=await fetchWithTimeout(url,opts,timeout);
    const parsed=await parseNetworkResponse(response,{requireObject:true});
    return parsed.data||{};
  }
  async function prepareResumableMedia(item){
    const base=resumableMediaBase();
    const manifest=item&&item.mediaManifest&&Array.isArray(item.mediaManifest.files)?item.mediaManifest:null;
    const blobs=(item&&Array.isArray(item.entries)?item.entries:[]).filter(isUploadableBlobEntry).slice(0,MAX_MEDIA_FILES);
    if(!base||!manifest||!blobs.length||manifest.files.length!==blobs.length)return null;
    if(!window.crypto||!window.crypto.subtle||manifest.files.some(file=>!file||!/^[a-f0-9]{64}$/.test(String(file.sha256||''))))return null;
    const chunkBytes=Math.max(131072,Math.min(1048576,parseInt(config.resumableChunkBytes||524288,10)||524288));
    const refs=[];const skipBlobIndexes=[];let uploadedBytes=0;let totalBytes=0;
    item.resumableMediaState='uploading';item.resumableMediaProtocol=RESUMABLE_MEDIA_PROTOCOL;await put(QUEUE_STORE,item).catch(()=>{});
    try{
      for(let i=0;i<blobs.length;i++){
        const entry=blobs[i],meta=manifest.files[i]||{},blob=entry.value;totalBytes+=blob.size||0;
        let start;
        try{
          start=await resumableJson(base+'/start',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID},body:JSON.stringify({protocol:RESUMABLE_MEDIA_PROTOCOL,mutation_id:item.mutationId,field_name:entry.key,file_index:i,name:entry.name||'ficheiro',mime_type:entry.mime||blob.type||'',size:blob.size||0,sha256:String(meta.sha256||''),chunk_size:chunkBytes})},Math.max(7000,parseInt(config.syncRequestTimeoutMs||15000,10)||15000));
        }catch(error){
          const status=parseInt(error&&error.status||0,10)||0;if([404,405,501].includes(status)){item.resumableMediaState='fallback';await put(QUEUE_STORE,item).catch(()=>{});return null;}throw error;
        }
        const token=String(start.upload_token||'');const uploadId=String(start.upload_id||'');
        if(!token||!uploadId)throw Object.assign(new Error('O servidor não iniciou corretamente o upload retomável.'),{code:'fieldflow_resumable_media_start_invalid',status:502});
        const serverChunkBytes=Math.max(131072,Math.min(1048576,parseInt(start.chunk_size||chunkBytes,10)||chunkBytes));
        const totalChunks=Math.max(1,parseInt(start.total_chunks||Math.ceil((blob.size||0)/serverChunkBytes),10)||1);
        const received=Array.isArray(start.received_chunks)?start.received_chunks.map(v=>parseInt(v,10)).filter(Number.isFinite):[];
        if(received.length){telemetry.resumable_resumed_chunks=Math.max(0,parseInt(telemetry.resumable_resumed_chunks||0,10))+received.length;persistTelemetry();}
        const missing=Array.isArray(start.missing_chunks)?start.missing_chunks.map(v=>parseInt(v,10)).filter(v=>Number.isFinite(v)&&v>=0&&v<totalChunks):Array.from({length:totalChunks},(_,idx)=>idx).filter(idx=>!received.includes(idx));
        let receivedBytes=Math.max(0,parseInt(start.received_bytes||0,10)||0);const previouslyReceived=receivedBytes;uploadedBytes+=previouslyReceived;
        for(const chunkIndex of missing){
          const from=chunkIndex*serverChunkBytes,to=Math.min(blob.size,from+serverChunkBytes),chunk=blob.slice(from,to);
          const chunkSha=await sha256Blob(chunk);
          if(!chunkSha)throw Object.assign(new Error('Não foi possível calcular a integridade de um bloco da fotografia.'),{code:'fieldflow_resumable_media_chunk_hash_failed',status:0});
          const chunkResult=await resumableJson(base+'/'+encodeURIComponent(uploadId)+'/chunk',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/octet-stream','X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID,'X-FieldFlow-Upload-Token':token,'X-FieldFlow-Chunk-Index':String(chunkIndex),'X-FieldFlow-Chunk-Sha256':chunkSha},body:chunk},Math.max(20000,Math.min(90000,Math.ceil((chunk.size||0)/64000*1000)+12000)));
          receivedBytes=Math.max(receivedBytes,parseInt(chunkResult.received_bytes||0,10)||0);
          telemetry.resumable_uploaded_chunks=Math.max(0,parseInt(telemetry.resumable_uploaded_chunks||0,10))+1;persistTelemetry();
          item.resumableMediaUploadedBytes=Math.max(0,uploadedBytes-previouslyReceived+receivedBytes);item.resumableMediaTotalBytes=Math.max(totalBytes,item.mediaBytes||0);item.resumableMediaCurrentFile=i+1;await put(QUEUE_STORE,item).catch(()=>{});
        }
        const completed=await resumableJson(base+'/'+encodeURIComponent(uploadId)+'/complete',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID,'X-FieldFlow-Upload-Token':token},body:'{}'},Math.max(10000,parseInt(config.syncRequestTimeoutMs||15000,10)||15000));
        if(completed.complete!==true||!completed.media_ref)throw Object.assign(new Error('O servidor ainda não concluiu a fotografia retomável.'),{code:'fieldflow_resumable_media_complete_missing',status:502});
        uploadedBytes=uploadedBytes-previouslyReceived+(blob.size||0);
        refs.push({token:String(completed.media_ref),field_name:String(entry.key||''),file_index:i,name:String(entry.name||'ficheiro'),size:blob.size||0,sha256:String(meta.sha256||'')});skipBlobIndexes.push(i);
        telemetry.resumable_completed_files=Math.max(0,parseInt(telemetry.resumable_completed_files||0,10))+1;persistTelemetry();
      }
      item.resumableMediaState='ready';item.resumableMediaRefs=refs;item.resumableMediaUploadedBytes=item.mediaBytes||totalBytes;item.resumableMediaTotalBytes=item.mediaBytes||totalBytes;await put(QUEUE_STORE,item).catch(()=>{});
      return {protocol:RESUMABLE_MEDIA_PROTOCOL,refs,skipBlobIndexes};
    }catch(error){
      item.resumableMediaState='pending';item.resumableMediaLastError=String(error&&error.code||error&&error.message||'resumable_upload_failed').slice(0,180);await put(QUEUE_STORE,item).catch(()=>{});throw error;
    }
  }
  function mediaPendingStats(items){
    let files=0,bytes=0;
    (items||[]).forEach(item=>{if(item&&item.kind==='form'&&['pending','syncing','failed','conflict'].includes(String(item.status||''))){files+=Math.max(0,parseInt(item.mediaFileCount||0,10)||0);bytes+=Math.max(0,parseInt(item.mediaBytes||0,10)||0);}});
    return {files,bytes};
  }
  function formSyncTimeout(item){
    const base=Math.max(30000,parseInt(config.formSyncTimeoutMs||90000,10)||90000);
    const bytes=Math.max(0,parseInt(item&&item.mediaBytes||0,10)||0);
    if(!bytes)return base;
    const network=networkInfo();let bytesPerSecond=220000;
    if(String(network.effective_type||'').toLowerCase()==='3g')bytesPerSecond=140000;
    if(String(network.effective_type||'').toLowerCase()==='4g')bytesPerSecond=420000;
    const estimate=Math.ceil((bytes/Math.max(64000,bytesPerSecond))*1000)+30000;
    return Math.max(base,Math.min(300000,estimate));
  }
  async function storageEstimate(){
    if(!navigator.storage||typeof navigator.storage.estimate!=='function') return null;
    try{return await navigator.storage.estimate();}catch(_){return null;}
  }
  async function requestPersistentStorage(){
    if(!navigator.storage||typeof navigator.storage.persist!=='function') return false;
    try{
      const already=typeof navigator.storage.persisted==='function'?await navigator.storage.persisted():false;
      const granted=already||await navigator.storage.persist();
      await setMeta('storage-persisted',{granted:!!granted,checked_at:now()}).catch(()=>{});
      return !!granted;
    }catch(_){return false;}
  }
  function offlineStorageError(error,estimate,requiredBytes){
    const name=String(error&&error.name||'');
    const code=String(error&&error.code||'');
    let message='N\u00e3o foi poss\u00edvel guardar este reporte offline. Mant\u00e9m a app aberta, confirma a liga\u00e7\u00e3o e tenta novamente.';
    let publicCode='fieldflow_offline_storage_failed';
    const available=estimate&&Number.isFinite(estimate.quota)&&Number.isFinite(estimate.usage)?Math.max(0,estimate.quota-estimate.usage):null;
    if(name==='QuotaExceededError'||code==='22'||(available!==null&&requiredBytes>available)){
      message='O armazenamento reservado \u00e0 app n\u00e3o tem espa\u00e7o suficiente. Remove rascunhos antigos ou fotografias desnecess\u00e1rias e tenta novamente.';
      publicCode='fieldflow_offline_storage_full';
    }else if(name==='DataCloneError'){
      message='Uma fotografia n\u00e3o p\u00f4de ser preparada para utiliza\u00e7\u00e3o offline. Volta a selecionar essa imagem.';
      publicCode='fieldflow_offline_file_clone_failed';
    }else if(name==='AbortError'){
      message='O armazenamento local interrompeu a grava\u00e7\u00e3o. Mant\u00e9m a app aberta durante alguns segundos e tenta novamente.';
      publicCode='fieldflow_offline_storage_aborted';
    }else if(['InvalidStateError','NotFoundError','SecurityError'].includes(name)||String(error&&error.message||'').includes('indexeddb')){
      message='O armazenamento offline n\u00e3o est\u00e1 dispon\u00edvel neste dispositivo.';
      publicCode='fieldflow_offline_storage_unavailable';
    }
    const wrapped=new Error(message);wrapped.code=publicCode;wrapped.cause=error;wrapped.storage=estimate||null;wrapped.requiredBytes=requiredBytes||0;return wrapped;
  }
  function entryValue(entries,key){const row=(entries||[]).find(entry=>entry.key===key&&entry.type==='text');return row?String(row.value||''):'';}
  function setEntry(entries,key,value){
    const row=(entries||[]).find(entry=>entry.key===key&&entry.type==='text');
    if(row) row.value=String(value==null?'':value); else entries.push({key,type:'text',value:String(value==null?'':value)});
  }
  function entityKey(type,meta,payload){
    const stopId=parseInt((payload&&payload.route_stop_id)||(payload&&payload.stop_id)||(meta&&meta.route_stop_id)||(meta&&meta.stop_id)||0,10)||0;
    const routeId=parseInt((payload&&payload.route_id)||(meta&&meta.route_id)||0,10)||0;
    if(stopId) return 'stop:'+String(stopId);
    if(routeId) return 'route:'+String(routeId);
    return String(type||'operation')+':'+String((meta&&meta.location_id)||0);
  }
  async function resolveRevision(type,payload,meta){
    const bundle=await operationalBundle().catch(()=>null);
    if(!bundle) return {baseRevision:'',entityType:'',entityId:0};
    const stopId=parseInt((payload&&payload.route_stop_id)||(payload&&payload.stop_id)||(meta&&meta.route_stop_id)||(meta&&meta.stop_id)||0,10)||0;
    if(stopId&&bundle.stops&&bundle.stops[String(stopId)]){
      return {baseRevision:String(bundle.stops[String(stopId)]._sync_revision||''),entityType:'stop',entityId:stopId};
    }
    const routeId=parseInt((payload&&payload.route_id)||(meta&&meta.route_id)||0,10)||0;
    if(routeId){
      const route=(bundle.route_details&&bundle.route_details[String(routeId)])||((bundle.routes||[]).find(row=>String(row.id)===String(routeId)));
      if(route) return {baseRevision:String(route._sync_revision||''),entityType:'route',entityId:routeId};
    }
    return {baseRevision:'',entityType:'',entityId:0};
  }
  function clearFormDraft(form){
    try{if(form&&typeof form.routesproClearDraft==='function')form.routesproClearDraft({silent:true,localOnly:true});}catch(_){ }
  }
  async function clearQueuedDraft(item){
    const draftKey=queueFormDraftKey(item);
    if(!draftKey)return;
    try{window.localStorage.removeItem(draftKey);}catch(_){ }
    if(!window.indexedDB)return;
    await new Promise(resolve=>{
      let request;try{request=indexedDB.open('fieldflow-form-drafts');}catch(_){resolve();return;}
      request.onerror=()=>resolve();
      request.onsuccess=()=>{
        const db=request.result;
        const stores=[];
        if(db.objectStoreNames.contains('snapshots'))stores.push('snapshots');
        if(db.objectStoreNames.contains('files'))stores.push('files');
        if(!stores.length){try{db.close();}catch(_){}resolve();return;}
        let tx;try{tx=db.transaction(stores,'readwrite');}catch(_){try{db.close();}catch(__){}resolve();return;}
        if(stores.includes('snapshots')){try{tx.objectStore('snapshots').delete(draftKey);}catch(_){} }
        if(stores.includes('files')){
          try{
            const store=tx.objectStore('files');
            if(store.indexNames.contains('draftKey')){
              const cursor=store.index('draftKey').openCursor(IDBKeyRange.only(draftKey));
              cursor.onsuccess=()=>{const row=cursor.result;if(row){row.delete();row.continue();}};
            }
          }catch(_){ }
        }
        tx.oncomplete=()=>{try{db.close();}catch(_){}resolve();};
        tx.onerror=()=>{try{db.close();}catch(_){}resolve();};
        tx.onabort=()=>{try{db.close();}catch(_){}resolve();};
      };
    });
  }
  async function queueForm(form,meta,options){
    options=options||{};
    const mutationId=String(options.mutationId||uuid('form'));
    const entries=await serialiseForm(form);
    const formId=parseInt(entryValue(entries,'routespro_form_id')||form&&form.dataset&&form.dataset.routesproFormId||0,10)||0;
    const locationId=parseInt(entryValue(entries,'routespro_location_id')||(meta&&meta.location_id)||0,10)||0;
    const reportDay=entryValue(entries,'routespro_report_day')||(meta&&meta.report_day)||'';
    // The DOM file input is not authoritative. Android/WebView may be unable to
    // reconstruct it after suspension even while the original Blob is still
    // protected in IndexedDB. Merge every durable draft photo before creating
    // the outbox row, with signature deduplication.
    const draftMediaProbe={kind:'form',entries,formId,meta:Object.assign({},meta||{},{location_id:locationId,report_day:reportDay})};
    const recoveredDraftMedia=await recoverDraftMediaForQueueItem(draftMediaProbe,[]).catch(()=>0);
    // Persist a lightweight media manifest first. Cryptographic hashing is done only after the outbox row is durable.
    const mediaManifest=await buildMediaManifest(entries,false);
    const trust=await trustLeaseState();
    setEntry(entries,'routespro_ajax','1');
    setEntry(entries,'routespro_offline_replay','1');
    setEntry(entries,'routespro_offline_mutation_id',mutationId);
    setEntry(entries,'routespro_offline_media_manifest',JSON.stringify(mediaManifest));
    if(trust&&trust.token)setEntry(entries,'routespro_offline_trust_lease',trust.token);
    const item={
      id:mutationId,mutationId,kind:'form',type:'web_form_submission',status:'pending',
      url:form.getAttribute('action')||config.formEndpoint||window.location.href,
      method:'POST',entries,meta:Object.assign({},meta||{},{outbox_first:true,runtime_build:RUNTIME_BUILD,media_integrity_protocol:MEDIA_INTEGRITY_PROTOCOL}),attempts:0,createdAt:now(),updatedAt:now(),nextAttemptAt:now(),
      entityKey:'form:'+String(formId)+':'+String(locationId)+':'+String(reportDay),
      baseRevision:entryValue(entries,'routespro_offline_base_revision'),entityType:'form',entityId:formId,
      formVersion:entryValue(entries,'routespro_form_version'),schemaHash:entryValue(entries,'routespro_schema_hash'),downloadedAt:entryValue(entries,'routespro_form_downloaded_at'),mediaManifest,mediaFileCount:mediaManifest.file_count||0,mediaBytes:mediaManifest.total_bytes||0,mediaHashedFiles:mediaManifest.hashed_files||0,recoveredDraftMedia:Math.max(0,parseInt(recoveredDraftMedia||0,10)||0),trustLeaseState:String(trust&&trust.state||'missing'),trustLeaseExpiresAt:parseInt(trust&&trust.expires_at_ms||0,10)||0
    };
    const requiredBytes=serialisedBytes(entries);
    const estimate=await storageEstimate();
    const available=estimate&&Number.isFinite(estimate.quota)&&Number.isFinite(estimate.usage)?Math.max(0,estimate.quota-estimate.usage):null;
    if(available!==null&&requiredBytes>available*0.92) throw offlineStorageError({name:'QuotaExceededError'},estimate,requiredBytes);
    try{await put(QUEUE_STORE,item);}catch(error){throw offlineStorageError(error,estimate,requiredBytes);}
    await recordSyncEvent('protected',item,{message:'Reporte protegido no dispositivo antes da tentativa de rede.'});
    if(options.clearDraft===true)clearFormDraft(form);
    await emitStatus();
    if(options.schedule!==false)scheduleSync(1200);
    return {queued_offline:true,outbox_persisted:true,mutation_id:mutationId,report_day:reportDay,network_state:connectivityState,message:'Reporte guardado no dispositivo. Sera sincronizado automaticamente quando houver uma ligacao utilizavel.'};
  }
  async function enqueueOperation(type,payload,meta){
    payload=payload||{};meta=meta||{};
    const mutationId=payload.mutation_id||uuid(type||'operation');
    const revision=await resolveRevision(type,payload,meta);
    const item={
      id:mutationId,mutationId,kind:'operation',type:String(type||''),payload,meta,status:'pending',attempts:0,
      createdAt:now(),updatedAt:now(),nextAttemptAt:now(),entityKey:entityKey(type,meta,payload),
      baseRevision:revision.baseRevision,entityType:revision.entityType,entityId:revision.entityId,
      dependsOn:String(payload&&payload.payload&&payload.payload.form_mutation_id||'')
    };
    await put(QUEUE_STORE,item);await recordSyncEvent('protected',item,{message:'Operação protegida no dispositivo.'});emitStatus();scheduleSync(800);
    return {queued_offline:true,mutation_id:mutationId};
  }
  function retryDelay(attempts){
    const core=typedOfflineCore();
    if(core&&core.outbox&&typeof core.outbox.retryDelay==='function')return core.outbox.retryDelay(attempts);
    return Math.min(3600000,Math.max(5000,Math.pow(2,Math.min(attempts,8))*3000));
  }
  async function updateQueue(item,changes){await put(QUEUE_STORE,Object.assign({},item,changes,{updatedAt:now()}));}
  async function queueItems(){
    const items=await all(QUEUE_STORE).catch(()=>[]);
    const core=typedOfflineCore();
    const stale=now()-300000;
    for(const item of items){
      const typedRecover=core&&core.outbox&&typeof core.outbox.shouldRecoverStaleSyncing==='function'
        ? !!core.outbox.shouldRecoverStaleSyncing(item,now(),300000)
        : item.status==='syncing'&&(item.updatedAt||0)<stale;
      if(typedRecover){item.status='pending';item.nextAttemptAt=now();await put(QUEUE_STORE,item).catch(()=>{});noteTelemetry('recovery_count','last_recovery_at_ms');await recordSyncEvent('recovered',item,{message:'Sincronização interrompida recuperada automaticamente.'}).catch(()=>{});}
    }
    return items.sort((a,b)=>core&&core.outbox&&typeof core.outbox.compareCreatedAt==='function'?core.outbox.compareCreatedAt(a,b):(a.createdAt||0)-(b.createdAt||0));
  }
  function validateFormAck(json,item){
    const payload=json&&typeof json==='object'&&json.data&&typeof json.data==='object'?json.data:{};
    const ack=payload&&payload.ack&&typeof payload.ack==='object'?payload.ack:{};
    const submissionId=parseInt(payload.submission_id||0,10)||0;
    const ackSubmissionId=parseInt(ack.submission_id||0,10)||0;
    const mutationId=String(item&&item.mutationId||'');
    const ackMutationId=String(ack.mutation_id||'');
    const valid=!!(json&&json.success===true&&payload.persisted===true&&submissionId>0&&ack.protocol===FORM_ACK_PROTOCOL&&ack.persisted===true&&ackSubmissionId===submissionId&&mutationId!==''&&ackMutationId===mutationId);
    if(!valid){
      const error=new Error('O servidor ainda nao confirmou de forma verificavel que o reporte ficou persistido. A copia local foi mantida.');
      error.status=502;error.code='fieldflow_form_ack_missing';error.retryable=true;error.payload=payload;lastErrorCode=error.code;noteTelemetry('ack_error_count','last_ack_error_at_ms');throw error;
    }
    lastErrorCode='';
    return Object.assign({},payload,{mutation_id:mutationId,_fieldflow_ack_verified:true});
  }
  async function syncForm(item){
    const emptyFields=zeroByteMediaFields(item&&item.entries);const mediaCleanup=scrubZeroByteMediaPlaceholders(item&&item.entries);
    if(item&&mediaCleanup.removed>0){
      item.entries=mediaCleanup.entries;
      const recovered=await recoverDraftMediaForQueueItem(item,emptyFields).catch(()=>0);
      item.zeroByteMediaRecoveredFromDraft=Math.max(0,parseInt(item.zeroByteMediaRecoveredFromDraft||0,10)||0)+recovered;
      item.zeroByteMediaPlaceholdersRemoved=Math.max(0,parseInt(item.zeroByteMediaPlaceholdersRemoved||0,10)||0)+mediaCleanup.removed;
    }
    const uploadableMediaCount=item&&Array.isArray(item.entries)?item.entries.filter(isUploadableBlobEntry).length:0;
    if(item&&item.kind==='form'&&(uploadableMediaCount>0||mediaCleanup.removed>0||Math.max(0,parseInt(item.mediaFileCount||0,10)||0)>0)){
      const mediaManifest=await buildMediaManifest(item.entries,true);
      setEntry(item.entries,'routespro_offline_media_manifest',JSON.stringify(mediaManifest));
      item.mediaManifest=mediaManifest;item.mediaFileCount=mediaManifest.file_count||0;item.mediaBytes=mediaManifest.total_bytes||0;item.mediaHashedFiles=mediaManifest.hashed_files||0;
      await put(QUEUE_STORE,item).catch(()=>{});
    }
    const trust=await refreshTrustLease(false).catch(()=>trustLeaseState());
    if(trust&&trust.token){setEntry(item.entries,'routespro_offline_trust_lease',trust.token);item.trustLeaseState=String(trust.state||'valid');item.trustLeaseExpiresAt=parseInt(trust.expires_at_ms||0,10)||0;await put(QUEUE_STORE,item).catch(()=>{});}
    const resumable=await prepareResumableMedia(item);
    const fd=formDataFromEntries(item.entries,{skipBlobIndexes:resumable&&resumable.skipBlobIndexes||[]});
    if(resumable&&Array.isArray(resumable.refs)&&resumable.refs.length){fd.set('routespro_resumable_media_refs',JSON.stringify({protocol:RESUMABLE_MEDIA_PROTOCOL,refs:resumable.refs}));}
    lastEndpoint=item.url;
    const response=await fetchWithTimeout(item.url,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-WP-Nonce':config.nonce||'','X-Requested-With':'XMLHttpRequest','X-FieldFlow-Device-ID':DEVICE_ID,'X-FieldFlow-Mutation-Id':item.mutationId}},formSyncTimeout(item));
    const parsed=await parseNetworkResponse(response,{requireObject:true});
    return validateFormAck(parsed.data,item);
  }
  async function submitForm(form,meta,options){
    options=options||{};
    const queued=await queueForm(form,meta,{clearDraft:false,schedule:false});
    const item=await get(QUEUE_STORE,queued.mutation_id).catch(()=>null);
    if(!item)throw offlineStorageError(new Error('indexeddb_queue_missing'),await storageEstimate(),0);
    const probe=await probeConnectivity(false);
    if(!isUsableConnectivity(probe.state)){
      scheduleSync(1200);await emitStatus();
      return Object.assign({},queued,{network_state:probe.state,message:probe.state==='degraded'?'Reporte protegido no dispositivo. A rede esta fraca e a sincronizacao sera retomada automaticamente.':queued.message});
    }
    if(!await acquireSyncLock()){
      scheduleSync(800);return Object.assign({},queued,{network_state:'pending'});
    }
    try{
      await updateQueue(item,{status:'syncing',attempts:(item.attempts||0)+1,nextAttemptAt:0});
      await recordSyncEvent('syncing',Object.assign({},item,{attempts:(item.attempts||0)+1}),{message:'A enviar para o servidor.'});
      const response=await syncForm(item);
      telemetry.sync_confirmed_count=Math.max(0,parseInt(telemetry.sync_confirmed_count||0,10))+1;telemetry.last_confirmed_at_ms=now();persistTelemetry();
      await recordSyncEvent('confirmed',item,{submission_id:response.submission_id||0,message:'Persistência confirmada pelo servidor.'});
      await remove(QUEUE_STORE,item.id);
      await clearQueuedDraft(item);
      clearFormDraft(form);
      lastSyncAt=now();lastSyncResult='success';
      await setMeta('last-sync-at',lastSyncAt).catch(()=>{});await setMeta('last-sync-result',lastSyncResult).catch(()=>{});
      document.dispatchEvent(new CustomEvent('fieldflow:offline-form-synced',{detail:{item,response,immediate:true}}));
      await emitStatus();
      return Object.assign({},response,{queued_offline:false,outbox_persisted:true,outbox_synced:true,mutation_id:item.mutationId});
    }catch(error){
      const attempts=(item.attempts||0)+1;
      const status=parseInt(error&&error.status||0,10)||0;
      const code=String(error&&error.code||error&&error.payload&&error.payload.code||'');
      const conflict=status===409&&code==='fieldflow_offline_conflict';
      const policyBlocked=status===403&&['fieldflow_device_revoked','fieldflow_device_offline_disabled'].includes(code);
      const terminal=status>=400&&status<500&&![401,408,409,425,429].includes(status)&&!policyBlocked;
      if(conflict){
        await updateQueue(item,{status:'conflict',attempts,lastError:error.message,conflict:error.payload||{},nextAttemptAt:0});
        await recordSyncEvent('conflict',item,{code:code,message:error.message});await emitStatus();throw error;
      }
      if(policyBlocked){
        await updateQueue(item,{status:'failed',attempts,lastError:error.message,nextAttemptAt:0});
        await recordSyncEvent('attention',item,{code:code,message:error.message});await emitStatus();throw error;
      }
      if(terminal){
        await updateQueue(item,{status:'failed',attempts,lastError:error.message||code||'Erro que requer correção antes de reenviar',nextAttemptAt:0});
        await recordSyncEvent('attention',item,{code:code,message:error.message||'O servidor rejeitou o reporte. A cópia local foi mantida.'});
        await emitStatus();throw error;
      }
      await updateQueue(item,{status:'pending',attempts,lastError:error.message||code||'Falha temporaria de sincronizacao',nextAttemptAt:now()+retryDelay(attempts)});
      await recordSyncEvent('pending',item,{code:code,message:error.message||'Confirmação pendente, será repetida automaticamente.'});
      scheduleSync(1200);await emitStatus();
      return Object.assign({},queued,{network_state:status===401?'auth_required':(connectivityState==='online'?'degraded':connectivityState),last_error_code:code||'fieldflow_sync_pending',message:'Reporte protegido no dispositivo. A confirmacao do servidor ficou pendente e sera repetida automaticamente.'});
    }finally{releaseSyncLock();}
  }
  async function syncOperations(items){
    if(!items.length) return [];
    const body={
      device_id:DEVICE_ID,platform:config.platform||'web',app_version:config.version||'',queue_pending:items.length,
      operations:items.map(item=>({mutation_id:item.mutationId,type:item.type,payload:item.payload||{},base_revision:item.baseRevision||'',entity_type:item.entityType||'',entity_id:item.entityId||0,created_at_ms:parseInt(item.createdAt||0,10)||0}))
    };
    lastEndpoint=config.syncEndpoint||'';
    const response=await fetchWithTimeout(config.syncEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID},body:JSON.stringify(body)},Math.max(5000,parseInt(config.syncRequestTimeoutMs||15000,10)||15000));
    const parsed=await parseNetworkResponse(response,{requireObject:true});
    return parsed.data.results||[];
  }

  async function acquireWebSyncLock(){
    if(!(navigator.locks&&typeof navigator.locks.request==='function'))return true;
    let resolveAcquired;
    const acquired=new Promise(resolve=>{resolveAcquired=resolve;});
    let releaseHold;
    const hold=new Promise(resolve=>{releaseHold=resolve;});
    webLockRelease=null;
    webLockRequestPromise=navigator.locks.request(SYNC_WEB_LOCK_NAME,{mode:'exclusive',ifAvailable:true},async lock=>{
      if(!lock){resolveAcquired(false);return;}
      webLockRelease=releaseHold;
      resolveAcquired(true);
      await hold;
    }).catch(()=>{resolveAcquired(false);});
    const ok=await acquired;
    if(!ok){webLockRelease=null;webLockRequestPromise=null;}
    return ok;
  }
  async function acquireSyncLock(){
    if(syncLeaseActive)return false;
    const webLocked=await acquireWebSyncLock();
    if(!webLocked)return false;
    const token=uuid('lease');
    const core=typedOfflineCore();
    try{
      const raw=localStorage.getItem(SYNC_LOCK_KEY);
      const current=core&&core.syncLock&&typeof core.syncLock.parse==='function'?core.syncLock.parse(raw):(raw?JSON.parse(raw):null);
      const foreignActive=core&&core.syncLock&&typeof core.syncLock.isForeignActive==='function'
        ? !!core.syncLock.isForeignActive(current,TAB_ID,now())
        : !!(current&&current.owner!==TAB_ID&&parseInt(current.expiresAt||0,10)>now());
      if(foreignActive){if(webLockRelease){webLockRelease();webLockRelease=null;}return false;}
      const lease=core&&core.syncLock&&typeof core.syncLock.create==='function'
        ? core.syncLock.create(TAB_ID,token,now(),SYNC_LOCK_TTL,RUNTIME_BUILD)
        : {owner:TAB_ID,token:token,expiresAt:now()+SYNC_LOCK_TTL,runtime:RUNTIME_BUILD};
      localStorage.setItem(SYNC_LOCK_KEY,JSON.stringify(lease));
      await sleep(35);
      const verifyRaw=localStorage.getItem(SYNC_LOCK_KEY);
      const verify=core&&core.syncLock&&typeof core.syncLock.parse==='function'?core.syncLock.parse(verifyRaw):JSON.parse(verifyRaw||'{}');
      const owns=core&&core.syncLock&&typeof core.syncLock.owns==='function'?!!core.syncLock.owns(verify,TAB_ID,token):!!(verify&&verify.owner===TAB_ID&&verify.token===token);
      if(!owns){if(webLockRelease){webLockRelease();webLockRelease=null;}return false;}
    }catch(_){ }
    syncLeaseActive=true;syncLeaseToken=token;startSyncLockHeartbeat();return true;
  }
  function refreshSyncLock(){
    if(!syncLeaseActive)return false;
    const core=typedOfflineCore();
    try{
      const raw=localStorage.getItem(SYNC_LOCK_KEY);
      const current=core&&core.syncLock&&typeof core.syncLock.parse==='function'?core.syncLock.parse(raw):JSON.parse(raw||'{}');
      const owns=core&&core.syncLock&&typeof core.syncLock.owns==='function'?!!core.syncLock.owns(current,TAB_ID,syncLeaseToken):!!(current&&current.owner===TAB_ID&&current.token===syncLeaseToken);
      if(!owns)return false;
      const renewed=core&&core.syncLock&&typeof core.syncLock.renew==='function'
        ? core.syncLock.renew(current,TAB_ID,syncLeaseToken,now(),SYNC_LOCK_TTL,RUNTIME_BUILD)
        : {owner:TAB_ID,token:syncLeaseToken,expiresAt:now()+SYNC_LOCK_TTL,runtime:RUNTIME_BUILD};
      if(!renewed)return false;
      localStorage.setItem(SYNC_LOCK_KEY,JSON.stringify(renewed));
      return true;
    }catch(_){return true;}
  }
  function startSyncLockHeartbeat(){
    window.clearInterval(syncLockHeartbeatTimer);
    syncLockHeartbeatTimer=window.setInterval(()=>{if(syncLeaseActive&&!refreshSyncLock()){window.clearInterval(syncLockHeartbeatTimer);syncLockHeartbeatTimer=null;}},SYNC_LOCK_HEARTBEAT);
  }
  function releaseSyncLock(){
    window.clearInterval(syncLockHeartbeatTimer);syncLockHeartbeatTimer=null;
    try{
      const core=typedOfflineCore();const raw=localStorage.getItem(SYNC_LOCK_KEY);
      const current=core&&core.syncLock&&typeof core.syncLock.parse==='function'?core.syncLock.parse(raw):JSON.parse(raw||'{}');
      const owns=core&&core.syncLock&&typeof core.syncLock.owns==='function'?!!core.syncLock.owns(current,TAB_ID,syncLeaseToken):!!(current&&current.owner===TAB_ID&&current.token===syncLeaseToken);
      if(owns)localStorage.removeItem(SYNC_LOCK_KEY);
    }catch(_){ }
    syncLeaseActive=false;syncLeaseToken='';
    if(webLockRelease){try{webLockRelease();}catch(_){ }webLockRelease=null;}
    webLockRequestPromise=null;
  }

  async function handleOperationResult(item,result){
    if(result&&result.status==='completed'){
      telemetry.sync_confirmed_count=Math.max(0,parseInt(telemetry.sync_confirmed_count||0,10))+1;telemetry.last_confirmed_at_ms=now();persistTelemetry();
      await recordSyncEvent('confirmed',item,{message:'Operação confirmada pelo servidor.'});
      await remove(QUEUE_STORE,item.id);
      document.dispatchEvent(new CustomEvent('fieldflow:offline-operation-synced',{detail:{item,result}}));
      return 'completed';
    }
    const response=(result&&result.response)||{};
    const code=String((response&&response.code)||(result&&result.error)||'');
    const conflict=code==='fieldflow_offline_conflict'||(result&&result.http_status===409&&response&&response.data&&response.data.conflict);
    const attempts=(item.attempts||0)+1;
    if(conflict){
      await updateQueue(item,{status:'conflict',attempts,lastError:(response&&response.message)||'Conflito com uma altera\u00e7\u00e3o mais recente.',conflict:(response&&response.data&&response.data.conflict)||response.conflict||{},nextAttemptAt:0});
      await recordSyncEvent('conflict',item,{code:code,message:(response&&response.message)||'Conflito com uma alteração mais recente.'});
      return 'conflict';
    }
    const retryable=!result||!!result.retryable;
    const message=(response&&response.message)||(result&&result.error)||'Falha na sincroniza\u00e7\u00e3o';
    await updateQueue(item,{status:retryable?'pending':'failed',attempts,lastError:message,nextAttemptAt:retryable?now()+retryDelay(attempts):0});
    await recordSyncEvent(retryable?'pending':'attention',item,{code:code,message:message});
    return retryable?'retryable':'failed';
  }
  async function syncOperationBatch(ready){
    const summary={completed:0,failed:0,conflicts:0,processed:ready.length};
    if(!ready.length||navigator.onLine===false)return summary;
    refreshSyncLock();
    for(const item of ready){await updateQueue(item,{status:'syncing',attempts:(item.attempts||0)+1});await recordSyncEvent('syncing',Object.assign({},item,{attempts:(item.attempts||0)+1}),{message:'Operação a sincronizar.'});}
    try{
      const results=await syncOperations(ready);
      for(const item of ready){
        const result=results.find(candidate=>candidate.mutation_id===item.mutationId)||null;
        const state=await handleOperationResult(item,result);
        if(state==='completed')summary.completed++;else if(state==='conflict')summary.conflicts++;else summary.failed++;
      }
    }catch(error){
      for(const item of ready){
        const attempts=(item.attempts||0)+1;
        await updateQueue(item,{status:'pending',attempts,lastError:error.message,nextAttemptAt:now()+retryDelay(attempts)});
        await recordSyncEvent('pending',item,{code:String(error&&error.code||''),message:error.message||'Falha temporária de sincronização.'});
        summary.failed++;
      }
    }
    return summary;
  }
  async function readyOperations(items){
    const ready=[];
    for(const item of items){
      const dependency=String(item.dependsOn||item.payload&&item.payload.payload&&item.payload.payload.form_mutation_id||'');
      if(dependency&&await get(QUEUE_STORE,dependency).catch(()=>null))continue;
      ready.push(item);
    }
    return ready;
  }
  async function repairOperationalConflicts(){
    const generation='2.2.454';
    const operationalTypes=['stop_checkin','stop_checkout','stop_failure'];
    const items=await all(QUEUE_STORE).catch(()=>[]);
    let repaired=0;
    for(const item of items){
      if(!item||item.status!=='conflict'||!operationalTypes.includes(String(item.type||'')))continue;
      if(String(item.repairGeneration||'')===generation)continue;
      const revision=await resolveRevision(item.type,item.payload||{},item.meta||{});
      await put(QUEUE_STORE,Object.assign({},item,{
        status:'pending',attempts:0,lastError:'',conflict:null,nextAttemptAt:now(),updatedAt:now(),repairGeneration:generation,
        baseRevision:revision.baseRevision,entityType:revision.entityType,entityId:revision.entityId
      }));
      repaired++;
    }
    return repaired;
  }
  async function repairFailedOperationalItems(){
    const generation='2.2.454';
    const operationalTypes=['stop_checkin','stop_checkout','stop_failure'];
    const items=await all(QUEUE_STORE).catch(()=>[]);
    let repaired=0;
    for(const item of items){
      if(!item||item.kind!=='operation'||!['failed','conflict'].includes(String(item.status||'')))continue;
      if(!operationalTypes.includes(String(item.type||'')))continue;
      if(String(item.repairGeneration||'')===generation)continue;
      const nextId=uuid(item.type||'operation');
      const revision=await resolveRevision(item.type,item.payload||{},item.meta||{});
      const clone=Object.assign({},item,{
        id:nextId,mutationId:nextId,status:'pending',attempts:0,lastError:'',conflict:null,nextAttemptAt:now(),updatedAt:now(),
        createdAt:parseInt(item.createdAt||0,10)||now(),repairGeneration:generation,
        baseRevision:revision.baseRevision,entityType:revision.entityType,entityId:revision.entityId
      });
      await tx(QUEUE_STORE,'readwrite',store=>{store.put(clone);store.delete(item.id);});
      repaired++;
    }
    return repaired;
  }

  async function repairZeroByteMediaFormItems(){
    const generation='2.2.463-durable-draft-ack-recovery';
    const items=await all(QUEUE_STORE).catch(()=>[]);
    let repaired=0;
    for(const item of items){
      if(!item||item.kind!=='form'||String(item.zeroByteMediaRepairGeneration||'')===generation)continue;
      const priorStatus=String(item.status||'pending');const priorError=String(item.lastError||'').toLowerCase();
      const sizeRejected=priorError.includes('fotografia')&&priorError.includes('dados para upload');
      const emptyFields=zeroByteMediaFields(item.entries);const cleanup=scrubZeroByteMediaPlaceholders(item.entries);
      if(cleanup.removed<1&&!sizeRejected)continue;
      item.entries=cleanup.entries;
      const recovered=await recoverDraftMediaForQueueItem(item,emptyFields).catch(()=>0);
      const manifest=await buildMediaManifest(item.entries,false);
      setEntry(item.entries,'routespro_offline_media_manifest',JSON.stringify(manifest));
      const recoverableStatus=priorStatus==='syncing'||(priorStatus==='failed'&&sizeRejected);
      await put(QUEUE_STORE,Object.assign({},item,{
        mediaManifest:manifest,mediaFileCount:manifest.file_count||0,mediaBytes:manifest.total_bytes||0,mediaHashedFiles:manifest.hashed_files||0,
        zeroByteMediaPlaceholdersRemoved:Math.max(0,parseInt(item.zeroByteMediaPlaceholdersRemoved||0,10)||0)+cleanup.removed,
        zeroByteMediaRecoveredFromDraft:Math.max(0,parseInt(item.zeroByteMediaRecoveredFromDraft||0,10)||0)+recovered,
        zeroByteMediaRecoveryPolicy:ZERO_BYTE_MEDIA_RECOVERY_POLICY,zeroByteMediaRepairGeneration:generation,status:recoverableStatus?'pending':priorStatus,attempts:recoverableStatus?0:(item.attempts||0),
        lastError:recoverableStatus?'':item.lastError,conflict:recoverableStatus?null:item.conflict,nextAttemptAt:recoverableStatus?now():item.nextAttemptAt,updatedAt:now()
      }));
      repaired++;
    }
    return repaired;
  }
  async function sync(options){
    if(deviceRevoked)return {ok:false,revoked:true};
    if(syncPromise)return syncPromise;
    const connectivity=await probeConnectivity(false);
    if(!isUsableConnectivity(connectivity.state)){
      await emitStatus();
      return {ok:false,offline:connectivity.state==='offline',degraded:connectivity.state==='degraded',auth_required:connectivity.state==='auth_required',network_state:connectivity.state};
    }
    if(!await acquireSyncLock())return {ok:false,locked:true};
    syncPromise=(async()=>{
      lastSyncResult='syncing';setBanner('syncing',0);
      const batchLimit=Math.max(1,parseInt(config.syncBatchSize||25,10));
      const includeFailed=!!(options&&options.includeFailed);
      const eligible=item=>{
        const core=typedOfflineCore();
        if(core&&core.outbox&&typeof core.outbox.isEligible==='function')return !!core.outbox.isEligible(item,now(),includeFailed);
        return (item.status==='pending'||item.status==='syncing'||(includeFailed&&item.status==='failed'))&&(!item.nextAttemptAt||item.nextAttemptAt<=now());
      };
      let completed=0,failed=0,conflicts=0,processed=0;
      const add=summary=>{completed+=summary.completed||0;failed+=summary.failed||0;conflicts+=summary.conflicts||0;processed+=summary.processed||0;};

      // Preserve the real visit sequence. A check-in created before a form is
      // synchronised first. Checkout operations that depend on the form remain
      // blocked until the form has been stored.
      let source=(await queueItems()).filter(eligible).slice(0,batchLimit);
      const formTimes=source.filter(item=>item.kind==='form').map(item=>item.createdAt||0);
      const firstFormAt=formTimes.length?Math.min.apply(Math,formTimes):Infinity;
      const preCandidates=source.filter(item=>item.kind==='operation'&&!String(item.dependsOn||item.payload&&item.payload.payload&&item.payload.payload.form_mutation_id||'')&&(item.createdAt||0)<=firstFormAt);
      const preReady=await readyOperations(preCandidates.slice(0,Math.max(0,batchLimit-processed)));
      add(await syncOperationBatch(preReady));

      source=(await queueItems()).filter(eligible);
      const forms=source.filter(item=>item.kind==='form').slice(0,Math.max(0,batchLimit-processed));
      for(const item of forms){
        const loopConnectivity=await probeConnectivity(false);if(!isUsableConnectivity(loopConnectivity.state))break;
        const nextAttempts=(item.attempts||0)+1;
        processed++;refreshSyncLock();await updateQueue(item,{status:'syncing',attempts:nextAttempts});
        await recordSyncEvent('syncing',Object.assign({},item,{attempts:nextAttempts}),{message:'Reporte a sincronizar em segundo plano.'});
        try{
          const response=await syncForm(item);
          telemetry.sync_confirmed_count=Math.max(0,parseInt(telemetry.sync_confirmed_count||0,10))+1;telemetry.last_confirmed_at_ms=now();persistTelemetry();
          await recordSyncEvent('confirmed',item,{submission_id:response.submission_id||0,message:'Persistência confirmada pelo servidor.'});
          await remove(QUEUE_STORE,item.id);await clearQueuedDraft(item);completed++;
          document.dispatchEvent(new CustomEvent('fieldflow:offline-form-synced',{detail:{item,response}}));
        }catch(error){
          const attempts=nextAttempts;
          const code=String(error&&error.code||error&&error.payload&&error.payload.code||error&&error.payload&&error.payload.data&&error.payload.data.code||'');
          const conflict=error.status===409&&code==='fieldflow_offline_conflict';
          const policyBlocked=error.status===403&&['fieldflow_device_revoked','fieldflow_device_offline_disabled'].includes(code);
          const terminal=error.status&&error.status>=400&&error.status<500&&![401,408,409,425,429].includes(error.status)&&!policyBlocked;
          if(conflict){await updateQueue(item,{status:'conflict',attempts,lastError:error.message,conflict:error.payload&&error.payload.conflict||error.payload&&error.payload.data&&error.payload.data.conflict||{},nextAttemptAt:0});await recordSyncEvent('conflict',item,{code:code,message:error.message});conflicts++;}
          else if(policyBlocked){await updateQueue(item,{status:'failed',attempts,lastError:error.message,nextAttemptAt:0});await recordSyncEvent('attention',item,{code:code,message:error.message});failed++;break;}
          else{await updateQueue(item,{status:terminal?'failed':'pending',attempts,lastError:error.message,nextAttemptAt:terminal?0:now()+retryDelay(attempts)});await recordSyncEvent(terminal?'attention':'pending',item,{code:code,message:error.message||'Falha temporária de sincronização.'});failed++;if(!terminal)break;}
        }
      }

      const remainingSlots=Math.max(0,batchLimit-processed);
      const remaining=(await queueItems()).filter(item=>item.kind==='operation'&&eligible(item)).slice(0,remainingSlots);
      add(await syncOperationBatch(await readyOperations(remaining)));
      lastSyncAt=now();lastSyncResult=failed?'errors':(conflicts?'conflicts':'success');
      await setMeta('last-sync-at',lastSyncAt).catch(()=>{});await setMeta('last-sync-result',lastSyncResult).catch(()=>{});
      await emitStatus();
      if(completed)document.dispatchEvent(new CustomEvent('fieldflow:sync-completed',{detail:{completed,failed,conflicts,processed}}));
      return {ok:failed===0&&conflicts===0,completed,failed,conflicts,processed};
    })().finally(()=>{syncPromise=null;releaseSyncLock();});
    return syncPromise;
  }

  function showDeviceRevoked(policy){
    deviceRevoked=true;
    let overlay=document.getElementById('fieldflow-device-revoked');
    if(!overlay){
      overlay=document.createElement('div');overlay.id='fieldflow-device-revoked';overlay.setAttribute('role','alertdialog');overlay.setAttribute('aria-modal','true');
      overlay.style.cssText='position:fixed;inset:0;z-index:2147483647;background:rgba(15,23,42,.92);display:flex;align-items:center;justify-content:center;padding:24px;font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif';
      const card=document.createElement('div');card.style.cssText='width:min(480px,100%);background:#fff;border-radius:22px;padding:28px;box-shadow:0 24px 70px rgba(0,0,0,.35);text-align:center;color:#0f172a';
      card.innerHTML='<h2 style="margin:0 0 12px;font-size:24px">Sess\u00e3o terminada</h2><p style="margin:0 0 18px;line-height:1.55">O acesso deste dispositivo foi revogado por um administrador. Fecha e volta a abrir a app, ou inicia sess\u00e3o novamente.</p><button type="button" style="border:0;border-radius:12px;background:#172033;color:#fff;font-weight:700;padding:12px 18px;min-width:160px">Atualizar</button>';
      card.querySelector('button').addEventListener('click',()=>window.location.reload());overlay.appendChild(card);document.body.appendChild(overlay);
    }
    setBanner('failed',0);document.dispatchEvent(new CustomEvent('fieldflow:device-revoked',{detail:policy||{}}));
  }
  async function applyDevicePolicy(policy){
    if(!policy||typeof policy!=='object') return;
    if(policy.blocked||policy.force_logout) showDeviceRevoked(policy);
    const generation=Math.max(1,parseInt(policy.cache_generation||1,10));
    const previous=parseInt(await getMeta('device-cache-generation').catch(()=>0)||0,10);
    if(previous>0&&generation>previous){
      await clearStore(CACHE_STORE).catch(()=>{});await remove(META_STORE,'operational-bundle').catch(()=>{});
      document.dispatchEvent(new CustomEvent('fieldflow:remote-cache-cleared',{detail:{previous,generation}}));
    }
    await setMeta('device-cache-generation',generation).catch(()=>{});await setMeta('device-policy',policy).catch(()=>{});
    if(policy.offline_allowed===0||policy.offline_allowed===false){setBanner('failed',0);document.dispatchEvent(new CustomEvent('fieldflow:offline-disabled',{detail:policy}));}
  }
  async function refreshDevicePolicy(){
    if(!config.policyEndpoint||navigator.onLine===false) return null;
    try{
      lastEndpoint=config.policyEndpoint;
      const response=await fetchWithTimeout(config.policyEndpoint,{method:'GET',credentials:'same-origin',headers:{'X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID},cache:'no-store'},Math.max(2500,parseInt(config.requestTimeoutMs||7000,10)||7000));
      const parsed=await parseNetworkResponse(response,{requireObject:true});
      const data=parsed.data||{};
      const policy=(data&&data.policy)||data||null;await applyDevicePolicy(policy);return policy;
    }catch(error){
      const code=String(error&&error.code||error&&error.payload&&error.payload.code||error&&error.payload&&error.payload.data&&error.payload.data.code||'');
      if((error&&error.status)===401){
        observeConnectivity('auth_required');
        document.dispatchEvent(new CustomEvent('fieldflow:auth-required',{detail:{status:401,code:code||'fieldflow_auth_required'}}));
        await emitStatus().catch(()=>{});
        return null;
      }
      if((error&&error.status)===403&&code==='fieldflow_device_revoked'){
        observeConnectivity('revoked');showDeviceRevoked((error&&error.payload&&error.payload.policy)||{blocked:true,status:403});return null;
      }
      if((error&&error.status)===403&&code==='fieldflow_device_offline_disabled'){
        document.dispatchEvent(new CustomEvent('fieldflow:offline-disabled',{detail:(error&&error.payload&&error.payload.policy)||{status:403}}));
        await emitStatus().catch(()=>{});return null;
      }
      return null;
    }
  }
  function mergeGroup(current,changed,deleted,isArray){
    const map={};
    if(isArray)(current||[]).forEach(row=>{if(row&&row.id!=null)map[String(row.id)]=row;});
    else Object.keys(current||{}).forEach(key=>{map[String(key)]=current[key];});
    if(isArray)(changed||[]).forEach(row=>{if(row&&row.id!=null)map[String(row.id)]=row;});
    else Object.keys(changed||{}).forEach(key=>{map[String(key)]=changed[key];});
    (deleted||[]).forEach(id=>{delete map[String(id)];});
    return isArray?Object.values(map):map;
  }
  function applyDelta(bundle,delta){
    const next=Object.assign({},bundle||{});
    const changes=delta.changes||{},deleted=delta.deleted||{};
    next.routes=mergeGroup(next.routes,changes.routes,deleted.routes,true);
    ['route_details','stops','locations','forms'].forEach(group=>{next[group]=mergeGroup(next[group],changes[group],deleted[group],false);});
    ['briefings','user','device_policy'].forEach(group=>{if(Object.prototype.hasOwnProperty.call(changes,group))next[group]=changes[group];});
    next.version=delta.version||next.version;next.cursor=delta.cursor||next.cursor;next.generated_at=delta.generated_at||next.generated_at;
    next.range=delta.range||next.range;next.scope=delta.scope||next.scope;next.limits=delta.limits||next.limits;next.route_cache_generation=delta.route_cache_generation||next.route_cache_generation;
    next._fieldflow_delta={applied:true,summary:delta.summary||{},applied_at:now()};
    return next;
  }
  async function fullPrefetch(force){
    const start=operationalDate();const end=addOperationalDays(start,6);
    const url=config.bootstrapEndpoint+'?date_from='+encodeURIComponent(start)+'&date_to='+encodeURIComponent(end)+(force?'&refresh=1':'');
    const data=await fetchJson(url,{cache:'no-store'});
    await applyDevicePolicy(data&&data.device_policy||null);await put(META_STORE,{key:'operational-bundle',value:data,updatedAt:now()});
    document.dispatchEvent(new CustomEvent('fieldflow:offline-cache-updated',{detail:data}));return data;
  }
  async function prefetch(force){
    if(!config.bootstrapEndpoint||navigator.onLine===false) return null;
    try{
      const current=await operationalBundle();
      if(!force&&current&&current.cursor&&config.deltaEndpoint){
        const start=operationalDate();const end=addOperationalDays(start,6);
        const url=config.deltaEndpoint+'?cursor='+encodeURIComponent(current.cursor)+'&date_from='+encodeURIComponent(start)+'&date_to='+encodeURIComponent(end);
        const delta=await fetchJson(url,{cache:'no-store'});
        if(delta&&delta.mode==='delta'){
          const merged=applyDelta(current,delta);await applyDevicePolicy(merged.device_policy||null);await put(META_STORE,{key:'operational-bundle',value:merged,updatedAt:now()});
          document.dispatchEvent(new CustomEvent('fieldflow:offline-cache-updated',{detail:merged}));return merged;
        }
      }
      return await fullPrefetch(!!force);
    }catch(_){return null;}
  }
  async function operationalBundle(){const row=await get(META_STORE,'operational-bundle').catch(()=>null);return row&&row.value||null;}
  async function setMeta(key,value){await put(META_STORE,{key:String(key),value,updatedAt:now()});return value;}
  async function getMeta(key){const row=await get(META_STORE,String(key)).catch(()=>null);return row?row.value:null;}
  async function syncHistory(){const history=await getMeta('sync-history').catch(()=>[]);return Array.isArray(history)?history:[];}
  async function recordSyncEvent(state,item,details){
    details=details||{};item=item||{};
    const history=await syncHistory();
    const entry={
      at:now(),state:String(state||''),id:String(item.id||item.mutationId||'').slice(0,64),kind:String(item.kind||''),type:String(item.type||''),
      attempts:Math.max(0,parseInt(item.attempts||0,10)),submission_id:Math.max(0,parseInt(details.submission_id||0,10)),
      code:String(details.code||'').slice(0,80),message:String(details.message||'').slice(0,180),
      form_version:String(item.formVersion||'').slice(0,64),schema_hash:String(item.schemaHash||'').slice(0,64),downloaded_at:String(item.downloadedAt||'').slice(0,64)
    };
    history.unshift(entry);
    await setMeta('sync-history',history.slice(0,HISTORY_LIMIT)).catch(()=>{});
    return entry;
  }

  function ensureBanner(){
    if(banner||!config.showStatus) return banner;
    banner=document.createElement('button');banner.type='button';banner.className='fieldflow-offline-status';banner.setAttribute('aria-live','polite');banner.setAttribute('aria-haspopup','dialog');banner.innerHTML='<span class="fieldflow-offline-dot"></span><strong data-ff-offline-label>Sincronizado</strong><small data-ff-offline-count></small>';
    banner.addEventListener('click',showSyncCenter);document.body.appendChild(banner);return banner;
  }
  function setBanner(state,count){
    const el=ensureBanner();if(!el)return;el.dataset.state=state||'online';
    const label=el.querySelector('[data-ff-offline-label]'),counter=el.querySelector('[data-ff-offline-count]');
    const labels={online:'Sincronizado',offline:'Sem liga\u00e7\u00e3o',degraded:'Rede fraca',auth_required:'Sess\u00e3o necess\u00e1ria',revoked:'Acesso revogado',pending:'Pendente',syncing:'A sincronizar',failed:'Requer aten\u00e7\u00e3o',conflict:'Conflito'};
    label.textContent=labels[state]||labels.online;counter.textContent=count?String(count)+' item'+(count===1?'':'s'):'';el.hidden=state==='online'&&!count;
  }
  async function status(){
    const items=await queueItems();
    const networkState=navigator.onLine===false?'offline':connectivityState;
    const pendingItems=items.filter(i=>i.status==='pending'||i.status==='syncing');
    const oldest=pendingItems.length?Math.min(...pendingItems.map(i=>parseInt(i.createdAt||now(),10)||now())):0;
    const maxAttempts=items.reduce((max,item)=>Math.max(max,parseInt(item.attempts||0,10)||0),0);
    const history=await syncHistory();
    const degradedLive=connectivityState==='degraded'&&telemetry.degraded_since?Math.max(0,now()-parseInt(telemetry.degraded_since||0,10)):0;
    const trust=await trustLeaseState();const mediaPending=mediaPendingStats(items);
    return {online:networkState==='online',network_state:networkState,probe_latency_ms:lastProbeLatency,pending:pendingItems.length,failed:items.filter(i=>i.status==='failed').length,conflicts:items.filter(i=>i.status==='conflict').length,items,history,oldestPendingAt:oldest,oldestPendingAgeMs:oldest?Math.max(0,now()-oldest):0,maxAttempts,trustLease:trust,mediaPendingFiles:mediaPending.files,mediaPendingBytes:mediaPending.bytes,telemetry:Object.assign({},telemetry,{degraded_total_ms:Math.max(0,parseInt(telemetry.degraded_total_ms||0,10))+degradedLive}),lastSyncAt:parseInt(await getMeta('last-sync-at').catch(()=>0)||0,10),lastSyncResult:String(await getMeta('last-sync-result').catch(()=>lastSyncResult)||lastSyncResult)};
  }
  function networkInfo(){
    const connection=navigator.connection||navigator.mozConnection||navigator.webkitConnection||{};
    return {network_type:String(connection.type||''),effective_type:String(connection.effectiveType||''),save_data:!!connection.saveData};
  }
  let statusReportTimer=null;
  function reportStatus(current){
    if(!config.statusEndpoint||!isUsableConnectivity(connectivityState))return;
    window.clearTimeout(statusReportTimer);
    statusReportTimer=window.setTimeout(async()=>{
      const estimate=await storageEstimate();const network=networkInfo();const persisted=await getMeta('storage-persisted').catch(()=>null);const t=current.telemetry||{};
      const trust=current.trustLease||{};const diagnostics={runtime_build:RUNTIME_BUILD,online:current.online,network_state:current.network_state,probe_latency_ms:current.probe_latency_ms,network_type:network.network_type,effective_type:network.effective_type,save_data:network.save_data,last_endpoint:lastEndpoint,last_error_code:lastErrorCode,last_http_status:lastHttpStatus,last_sync_result:current.lastSyncResult||lastSyncResult,queue_pending:current.pending||0,queue_failed:current.failed||0,queue_conflicts:current.conflicts||0,oldest_pending_at_ms:current.oldestPendingAt||0,oldest_pending_age_ms:current.oldestPendingAgeMs||0,max_queue_attempts:current.maxAttempts||0,ack_error_count:t.ack_error_count||0,last_ack_error_at_ms:t.last_ack_error_at_ms||0,degraded_total_ms:t.degraded_total_ms||0,degraded_events:t.degraded_events||0,auth_required_count:t.auth_required_count||0,app_resume_count:t.app_resume_count||0,recovery_count:t.recovery_count||0,last_recovery_at_ms:t.last_recovery_at_ms||0,sync_confirmed_count:t.sync_confirmed_count||0,last_confirmed_at_ms:t.last_confirmed_at_ms||0,storage_usage:estimate&&estimate.usage||0,storage_quota:estimate&&estimate.quota||0,storage_available:estimate&&estimate.quota&&Number.isFinite(estimate.usage)?Math.max(0,estimate.quota-estimate.usage):0,storage_persistence:persisted&&persisted.granted?'persistent':'best_effort',media_pending_files:current.mediaPendingFiles||0,media_pending_bytes:current.mediaPendingBytes||0,resumable_uploaded_chunks:t.resumable_uploaded_chunks||0,resumable_resumed_chunks:t.resumable_resumed_chunks||0,resumable_completed_files:t.resumable_completed_files||0,trust_lease_state:String(trust.state||'missing'),trust_lease_expires_at_ms:parseInt(trust.expires_at_ms||0,10)||0,trust_lease_release:String(trust.release||''),trust_lease_certified_at:String(trust.certified_at||''),sync_history:(current.history||[]).slice(0,12)};
      window.fetch(config.statusEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':config.nonce||'','X-FieldFlow-Device-ID':DEVICE_ID},body:JSON.stringify({device_id:DEVICE_ID,platform:config.platform||'web',app_version:config.version||'',queue_pending:current.pending||0,queue_failed:current.failed||0,last_error:lastErrorCode||'',diagnostics})}).catch(()=>{});
    },500);
  }
  async function emitStatus(){
    const current=await status();
    const networkState=current.network_state||(!current.online?'offline':'online');
    const state=['offline','degraded','auth_required','revoked'].includes(networkState)?networkState:(current.conflicts?'conflict':(current.failed?'failed':(current.pending?'pending':'online')));
    setBanner(state,current.pending+current.failed+current.conflicts);document.dispatchEvent(new CustomEvent('fieldflow:offline-status',{detail:current}));reportStatus(current);
    if(center&&!center.hidden) renderSyncCenter(current);return current;
  }

  function formatDate(ts){if(!ts)return 'Ainda n\u00e3o sincronizado';try{return new Intl.DateTimeFormat('pt-PT',{dateStyle:'short',timeStyle:'short'}).format(new Date(ts));}catch(_){return new Date(ts).toLocaleString();}}
  function itemLabel(item){
    const labels={form_submission:'Formul\u00e1rio',web_form_submission:'Formul\u00e1rio',stop_checkin:'Check-in',stop_checkout:'Check-out',stop_failure:'Ocorr\u00eancia',briefing_view:'Briefing visto',briefing_ack:'Briefing confirmado',stocks_viewed:'Stocks vistos'};
    return labels[item.type]||item.type||'Opera\u00e7\u00e3o';
  }
  function escapeHtml(value){return String(value==null?'':value).replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));}
  function ensureSyncCenter(){
    if(center)return center;
    center=document.createElement('div');center.className='fieldflow-sync-center';center.hidden=true;center.innerHTML='<div class="fieldflow-sync-backdrop" data-ff-sync-close></div><section class="fieldflow-sync-dialog" role="dialog" aria-modal="true" aria-labelledby="fieldflow-sync-title"><header><div><small>FieldFlow Mobile</small><h2 id="fieldflow-sync-title">Centro de sincroniza\u00e7\u00e3o</h2></div><button type="button" class="fieldflow-sync-close" data-ff-sync-close aria-label="Fechar">\u00d7</button></header><div class="fieldflow-sync-body" data-ff-sync-body></div><footer><button type="button" class="fieldflow-sync-secondary" data-ff-copy>Copiar diagn\u00f3stico</button><button type="button" class="fieldflow-sync-primary" data-ff-sync-now>Sincronizar agora</button></footer></section>';
    center.querySelectorAll('[data-ff-sync-close]').forEach(el=>el.addEventListener('click',hideSyncCenter));
    center.querySelector('[data-ff-sync-now]').addEventListener('click',async()=>{await sync({includeFailed:true});await emitStatus();});
    center.querySelector('[data-ff-copy]').addEventListener('click',copyDiagnostics);
    center.addEventListener('click',async event=>{
      const retry=event.target.closest('[data-ff-retry]');if(retry){await retryItem(retry.getAttribute('data-ff-retry'));return;}
      const discard=event.target.closest('[data-ff-discard]');if(discard){await discardItem(discard.getAttribute('data-ff-discard'));return;}
      const refresh=event.target.closest('[data-ff-refresh]');if(refresh){await prefetch(true);await emitStatus();return;}
    });
    document.body.appendChild(center);return center;
  }
  async function renderSyncCenter(current){
    const el=ensureSyncCenter();const body=el.querySelector('[data-ff-sync-body]');current=current||await status();
    const networkLabels={online:'Online',degraded:'Rede fraca',offline:'Sem liga\u00e7\u00e3o',auth_required:'Sess\u00e3o necess\u00e1ria',revoked:'Acesso revogado',unknown:'A verificar'};
    const network=networkLabels[current.network_state]||networkLabels.unknown;
    const cards='<div class="fieldflow-sync-kpis"><article><span>Estado</span><strong>'+escapeHtml(network)+'</strong></article><article><span>Pendentes</span><strong>'+current.pending+'</strong></article><article><span>Falhas</span><strong>'+current.failed+'</strong></article><article><span>Conflitos</span><strong>'+current.conflicts+'</strong></article></div>';
    const info='<div class="fieldflow-sync-info"><span>Dispositivo <code>'+escapeHtml(DEVICE_ID.slice(0,18))+'</code></span><span>\u00daltima sincroniza\u00e7\u00e3o <strong>'+escapeHtml(formatDate(current.lastSyncAt))+'</strong></span>'+(current.oldestPendingAt?'<span>Mais antigo <strong>'+escapeHtml(formatDate(current.oldestPendingAt))+'</strong></span>':'')+'<span>Tentativas máximas <strong>'+escapeHtml(current.maxAttempts||0)+'</strong></span></div>';
    let list='';
    if(!current.items.length) list='<div class="fieldflow-sync-empty"><strong>Tudo sincronizado.</strong><p>N\u00e3o existem opera\u00e7\u00f5es pendentes neste dispositivo.</p></div>';
    else list='<div class="fieldflow-sync-list">'+current.items.map(item=>{
      const statusLabels={pending:'Pendente',syncing:'A sincronizar',failed:'Falhou',conflict:'Conflito'};
      const canRetry=item.status==='failed';
      const conflict=item.status==='conflict';
      const protectedForm=item.kind==='form'&&['pending','syncing'].includes(String(item.status||''));
      return '<article class="fieldflow-sync-item" data-state="'+escapeHtml(item.status)+'"><div class="fieldflow-sync-item-main"><strong>'+escapeHtml(itemLabel(item))+'</strong><span>'+escapeHtml(statusLabels[item.status]||item.status)+'</span><small>'+escapeHtml(item.lastError||('Criado em '+formatDate(item.createdAt)))+'</small></div><div class="fieldflow-sync-item-actions">'+(canRetry?'<button type="button" data-ff-retry="'+escapeHtml(item.id)+'">Tentar novamente</button>':'')+(conflict?'<button type="button" data-ff-refresh="1">Atualizar dados</button>':'')+(protectedForm?'<small>Protegido até confirmação do servidor</small>':'<button type="button" data-ff-discard="'+escapeHtml(item.id)+'">Remover</button>')+'</div></article>';
    }).join('')+'</div>';
    const stateLabels={protected:'Guardado no dispositivo',syncing:'A sincronizar',confirmed:'Confirmado pelo servidor',pending:'Confirmação pendente',attention:'Requer atenção',conflict:'Conflito',recovered:'Recuperado após interrupção'};
    const history=(current.history||[]).slice(0,10);
    const historyHtml=history.length?'<section class="fieldflow-sync-history"><h3>Histórico recente</h3><div>'+history.map(event=>'<article data-state="'+escapeHtml(event.state||'')+'"><span>'+escapeHtml(stateLabels[event.state]||event.state||'Evento')+'</span><strong>'+escapeHtml(itemLabel(event))+'</strong><small>'+escapeHtml(formatDate(event.at))+(event.submission_id?' · #'+escapeHtml(event.submission_id):'')+(event.message?' · '+escapeHtml(event.message):'')+'</small></article>').join('')+'</div></section>':'';
    body.innerHTML=cards+info+list+historyHtml;
  }
  async function showSyncCenter(){const el=ensureSyncCenter();el.hidden=false;document.documentElement.classList.add('fieldflow-sync-center-open');await renderSyncCenter();}
  function hideSyncCenter(){if(center)center.hidden=true;document.documentElement.classList.remove('fieldflow-sync-center-open');}
  async function retryItem(id){
    const item=await get(QUEUE_STORE,id).catch(()=>null);if(!item)return;
    const nextId=uuid(item.kind==='form'?'form':(item.type||'operation'));
    const clone=Object.assign({},item,{id:nextId,mutationId:nextId,status:'pending',attempts:0,lastError:'',conflict:null,nextAttemptAt:now(),createdAt:parseInt(item.createdAt||0,10)||now(),updatedAt:now()});
    if(clone.kind==='form')setEntry(clone.entries,'routespro_offline_mutation_id',nextId);
    else{const revision=await resolveRevision(clone.type,clone.payload,clone.meta);clone.baseRevision=revision.baseRevision;clone.entityType=revision.entityType;clone.entityId=revision.entityId;}
    await tx(QUEUE_STORE,'readwrite',store=>{store.put(clone);store.delete(id);});await emitStatus();scheduleSync(100);
  }
  async function discardItem(id){
    const item=await get(QUEUE_STORE,id).catch(()=>null);
    if(item&&item.kind==='form'&&['pending','syncing'].includes(String(item.status||''))){
      try{window.alert('Este reporte ainda não foi confirmado pelo servidor e está protegido contra remoção.');}catch(_){}
      return false;
    }
    let allowed=true;try{allowed=window.confirm('Remover esta operação do dispositivo?');}catch(_){allowed=true;}
    if(!allowed)return false;await remove(QUEUE_STORE,id);await emitStatus();return true;
  }
  async function diagnostics(){
    const current=await status();const estimate=await storageEstimate();const network=networkInfo();
    return {fieldflow_version:config.version||'',runtime_build:RUNTIME_BUILD,operational_date:operationalDate(),site_timezone:String(config.siteTimezone||''),sync_lease_active:syncLeaseActive,user_id:USER_ID,device_id:DEVICE_ID,platform:config.platform||'web',online:current.online,network_state:current.network_state,probe_latency_ms:current.probe_latency_ms,network,queue:{pending:current.pending,failed:current.failed,conflicts:current.conflicts,total:current.items.length,oldest_pending_at:current.oldestPendingAt||0,oldest_pending_age_ms:current.oldestPendingAgeMs||0,max_attempts:current.maxAttempts||0,items:current.items.map(item=>({id:String(item.id||'').slice(0,20),kind:item.kind,type:item.type,status:item.status,attempts:item.attempts||0,created_at:item.createdAt||0,last_error:item.lastError||'',entity_key:item.entityKey||'',form_version:item.formVersion||'',schema_hash:item.schemaHash||'',downloaded_at:item.downloadedAt||''}))},trust_lease:current.trustLease||{},media_pending:{files:current.mediaPendingFiles||0,bytes:current.mediaPendingBytes||0},telemetry:current.telemetry||{},history:(current.history||[]).slice(0,12),storage:estimate||null,last_endpoint:lastEndpoint,last_error_code:lastErrorCode,last_http_status:lastHttpStatus,last_sync_at:current.lastSyncAt||0,last_sync_result:current.lastSyncResult||lastSyncResult,user_agent:navigator.userAgent};
  }
  async function copyDiagnostics(){
    const text=JSON.stringify(await diagnostics(),null,2);let copied=false;
    try{if(navigator.clipboard&&navigator.clipboard.writeText){await navigator.clipboard.writeText(text);copied=true;}}catch(_){copied=false;}
    if(!copied){const area=document.createElement('textarea');area.value=text;area.style.position='fixed';area.style.opacity='0';document.body.appendChild(area);area.select();try{copied=document.execCommand('copy');}catch(_){copied=false;}area.remove();}
    const button=center&&center.querySelector('[data-ff-copy]');if(button){const old=button.textContent;button.textContent=copied?'Diagn\u00f3stico copiado':'N\u00e3o foi poss\u00edvel copiar';setTimeout(()=>{button.textContent=old;},1800);}return text;
  }

  let syncTimer=null;
  function scheduleSync(delay){window.clearTimeout(syncTimer);syncTimer=window.setTimeout(()=>sync(),delay||500);}
  function init(){
    openDb().then(async()=>{
      const storedTelemetry=await getMeta('operations-intelligence').catch(()=>null);if(storedTelemetry&&typeof storedTelemetry==='object')telemetry=Object.assign({},telemetry,storedTelemetry,{degraded_since:0});
      lastSyncAt=parseInt(await getMeta('last-sync-at').catch(()=>0)||0,10);
      lastSyncResult=String(await getMeta('last-sync-result').catch(()=>'idle')||'idle');
      await requestPersistentStorage();
      const repairedConflicts=await repairOperationalConflicts();const repairedFailed=await repairFailedOperationalItems();const repairedMedia=await repairZeroByteMediaFormItems();
      if(navigator.onLine!==false)await probeConnectivity(true);else observeConnectivity('offline');
      await emitStatus();
      if(isUsableConnectivity(connectivityState)){refreshDevicePolicy();refreshTrustLease(false);prefetch(false);scheduleSync((repairedConflicts||repairedFailed||repairedMedia)?120:1000);}
    }).catch(()=>{});
    window.addEventListener('online',async()=>{observeConnectivity('unknown');await probeConnectivity(true);await emitStatus();if(isUsableConnectivity(connectivityState)){refreshDevicePolicy();refreshTrustLease(false);prefetch(false);scheduleSync(250);}});
    window.addEventListener('offline',()=>{observeConnectivity('offline');lastProbeAt=now();emitStatus();});
    window.addEventListener('storage',event=>{if(event.key===SYNC_LOCK_KEY&&navigator.onLine!==false)scheduleSync(1200);});
    document.addEventListener('visibilitychange',async()=>{if(document.visibilityState==='visible'&&navigator.onLine!==false){await probeConnectivity(true);await emitStatus();if(isUsableConnectivity(connectivityState)){refreshDevicePolicy();refreshTrustLease(false);scheduleSync(400);}}});
    window.addEventListener('fieldflow:app-resume',async()=>{noteTelemetry('app_resume_count');const resumedItems=await queueItems().catch(()=>[]);if(resumedItems.some(item=>['pending','syncing'].includes(String(item.status||''))))noteTelemetry('recovery_count','last_recovery_at_ms');if(navigator.onLine!==false){await probeConnectivity(true);await emitStatus();if(isUsableConnectivity(connectivityState)){refreshDevicePolicy();refreshTrustLease(false);prefetch(false);scheduleSync(120);}}});
    window.setInterval(async()=>{if(navigator.onLine!==false){await probeConnectivity(true);if(isUsableConnectivity(connectivityState))sync();else emitStatus();}},Math.max(30000,parseInt(config.syncIntervalSeconds||60,10)*1000));
    policyTimer=window.setInterval(()=>{if(navigator.onLine!==false&&isUsableConnectivity(connectivityState)){refreshDevicePolicy();refreshTrustLease(false);}},60000);
  }

  window.FieldFlowOffline=Object.assign(previousApi,{
    version:RUNTIME_BUILD,__runtimeBuild:RUNTIME_BUILD,deviceId:DEVICE_ID,init,
    fetchJson,fetch:offlineFetch,prefetch,refreshDevicePolicy,refreshTrustLease,trustLeaseState,probeConnectivity,operationalBundle,setMeta,getMeta,
    queueForm,submitForm,enqueueOperation,sync,status,queueItems,showSyncCenter,hideSyncCenter,retryItem,discardItem,diagnostics,copyDiagnostics,
    isOnline:()=>connectivityState==='online',canSync:()=>isUsableConnectivity(connectivityState),networkState:()=>connectivityState,operationalDate
  });
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true}); else init();
})();
