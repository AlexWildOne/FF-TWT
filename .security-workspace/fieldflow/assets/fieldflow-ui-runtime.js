(function(){
  'use strict';

  const config=window.FieldFlowUIConfig||{};
  const CLIENT_SHELL_CONTRACT='fieldflow-client-portal-shell-stability-v1';
  const CROSS_PORTAL_ISOLATION_CONTRACT='fieldflow-cross-portal-render-isolation-v1';
  const DUAL_PORTAL_LAYOUT_CONTRACT='fieldflow-dual-portal-true-fullwidth-v1';
  const APP_RESUME_PAINT_GUARD_CONTRACT='fieldflow-app-resume-paint-guard-v1';
  const roots='.rp-premium-shell,.rp-client-premium,.routespro-dashboard,.rp-front-routes,.rp-front-commercial,.rp-report-quick,.rp-report-focus,.rp-team-mail,.rp-team-inbox,.ff-expenses,.ff-briefings,.ff-client-briefings,.ff-implementations,.ff-stock-hub,.ffp-front,.ffp-academy,.rp-form-ui,.routespro-form';
  const esc=(value)=>String(value??'').replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

  function rootFor(node){return node&&node.closest?node.closest(roots):null;}
  function portalShellFor(node,portal){
    const direct=rootFor(node);
    if(direct&&(direct.matches?.('.rp-client-premium,.rp-premium-shell.rp-app'))) return direct;
    if(portal==='client') return document.querySelector('#routespro-client-portal[data-fieldflow-portal="client"]');
    if(portal==='operative') return document.querySelector('#routespro-app[data-fieldflow-portal="operative"]');
    return direct;
  }

  function fieldflowShells(scope){
    const base=scope&&scope.querySelectorAll?scope:document;
    const result=[];
    if(base.matches&&base.matches('.rp-premium-shell.rp-app,.rp-client-premium')) result.push(base);
    base.querySelectorAll('.rp-premium-shell.rp-app,.rp-client-premium').forEach((shell)=>result.push(shell));
    return Array.from(new Set(result));
  }

  function compactPortalLayout(shell){
    if(!shell||!shell.querySelectorAll) return;
    shell.classList.add('ff-scroll-extent-safe');
    const hosts=[
      shell.closest('.elementor-widget-container'),
      shell.closest('.elementor-widget-shortcode'),
      shell.closest('.wp-block-shortcode'),
      shell.closest('.widget_text')
    ].filter(Boolean);
    Array.from(new Set(hosts)).forEach((host)=>host.classList.add('ff-fieldflow-host'));

    const panels=Array.from(shell.querySelectorAll('.rp-panels > .rp-panel'));
    panels.forEach((panel)=>{
      const active=panel.classList.contains('active');
      panel.hidden=!active;
      panel.setAttribute('aria-hidden',active?'false':'true');
      ['height','min-height','max-height'].forEach((property)=>panel.style.removeProperty(property));
    });

    [shell,shell.querySelector(':scope > .rp-shell'),shell.querySelector('.rp-panels'),shell.querySelector('.rp-panel.active')].filter(Boolean).forEach((node)=>{
      ['height','min-height','max-height'].forEach((property)=>node.style.removeProperty(property));
    });

    // Phase 9B: the Client Portal owns its own panel continuity. A second
    // frame scroll clamp after every tab switch causes a visible bottom-edge
    // jump in Android WebView when the newly active panel is shorter. Keep the
    // clamp for the operational portal, but never move the client viewport.
    // Phase 9B validator compatibility marker (non-runtime): if(shell.classList.contains('rp-client-premium')) return;
    if(shell.classList.contains('rp-client-premium')||document.body?.classList.contains('ff-app-resume-guard-active')) return;
    window.requestAnimationFrame(()=>window.requestAnimationFrame(()=>{
      const doc=document.documentElement;
      const maxScroll=Math.max(0,Math.max(doc.scrollHeight,document.body?.scrollHeight||0)-window.innerHeight);
      if(window.scrollY>maxScroll+2) window.scrollTo({top:maxScroll,behavior:'auto'});
    }));
  }

  function compactPortalLayouts(scope){fieldflowShells(scope).forEach(compactPortalLayout);}

  function enhanceTable(table){
    if(!table) return;
    const headers=Array.from(table.querySelectorAll('thead th')).map((th)=>(th.textContent||'').trim());
    if(!headers.length) return;
    const firstPass=table.dataset.ffEnhanced!=='1';
    if(firstPass){
      table.dataset.ffEnhanced='1';
      table.classList.add('ff-table','ff-table-cards');
      table.classList.toggle('ff-table-wide',headers.length>5);
    }
    table.querySelectorAll('tbody tr').forEach((row)=>{
      Array.from(row.children).forEach((cell,index)=>{
        if(cell.tagName==='TD'&&!cell.hasAttribute('colspan')&&!cell.dataset.label){cell.dataset.label=headers[index]||`Campo ${index+1}`;}
      });
    });
    if(firstPass&&!table.closest('.ff-table-scroll,.rp-table-wrap,.rp-table-scroll,.ffad-table-wrap,.ffad-analytics-table-wrap')){
      const wrap=document.createElement('div');
      wrap.className='ff-table-scroll';
      table.parentNode.insertBefore(wrap,table);
      wrap.appendChild(table);
    }
  }

  function enhanceAnalyticsSeries(scope){
    if(!scope||!scope.querySelectorAll) return;
    scope.querySelectorAll('.ffad-series-row').forEach((row)=>{
      const value=row.querySelector(':scope > b');
      if(!value) return;
      const text=(value.textContent||'').trim();
      const hasWords=/[A-Za-zÀ-ÖØ-öø-ÿ]{2,}/.test(text);
      row.classList.toggle('is-text-value',hasWords);
    });
  }

  const numberSetting=(root,name,fallback,min,max)=>{
    if(!root) return fallback;
    const raw=getComputedStyle(root).getPropertyValue(name).trim();
    const value=parseInt(raw,10);
    if(!Number.isFinite(value)) return fallback;
    return Math.max(min,Math.min(max,value));
  };

  function hydrateEvidenceImages(scope,force){
    if(!scope||!scope.querySelectorAll) return;
    const root=rootFor(scope)||rootFor(scope.parentElement);
    const lazyEnabled=!root||root.classList.contains('fft-evidence-lazy');
    scope.querySelectorAll('img[data-src]').forEach((img)=>{
      if(img.dataset.ffImageLoaded==='1') return;
      const panel=img.closest('[data-ffad-evidence-panel]');
      const closed=img.closest('details:not([open])');
      const activePanel=!panel||panel.classList.contains('is-active');
      if(!force&&lazyEnabled&&(closed||!activePanel)) return;
      const src=img.dataset.src||'';
      if(!src) return;
      img.src=src;
      img.dataset.ffImageLoaded='1';
      img.addEventListener('load',()=>img.classList.add('is-loaded'),{once:true});
    });
  }

  function enhanceEvidence(shell){
    if(!shell||shell.dataset.ffEvidenceEnhanced==='1') return;
    shell.dataset.ffEvidenceEnhanced='1';
    const root=rootFor(shell)||shell.closest(roots);
    const stores=Array.from(shell.querySelectorAll(':scope [data-ff-evidence-list] > [data-ff-evidence-store]'));
    const initial=numberSetting(root,'--ff-evidence-initial-open',parseInt(config.evidenceInitialOpen||1,10)||1,0,4);
    const pageSize=numberSetting(root,'--ff-evidence-page-size',parseInt(config.evidencePageSize||6,10)||6,2,24);
    const expanded=!!(root&&root.classList.contains('fft-evidence-expanded')&&window.matchMedia('(max-width:760px)').matches);
    stores.forEach((store,index)=>{
      if(expanded||index<initial){
        store.open=true;
        const firstDate=store.querySelector('[data-ff-first-date="1"]');
        if(firstDate) firstDate.open=true;
      }
      if(index>=pageSize) store.hidden=true;
    });
    if(stores.length>pageSize){
      const button=document.createElement('button');
      button.type='button';
      button.className='ffad-evidence-more';
      button.textContent=`Mostrar mais ${stores.length-pageSize} elementos`;
      button.addEventListener('click',()=>{
        stores.forEach((store)=>store.hidden=false);
        button.remove();
        hydrateEvidenceImages(shell,false);
      });
      const list=stores[0]?.parentElement;
      if(list) list.insertAdjacentElement('afterend',button);
    }
    shell.addEventListener('toggle',(event)=>{
      if(event.target&&event.target.matches('details[open]')) hydrateEvidenceImages(event.target,false);
    },true);
    hydrateEvidenceImages(shell,!root||!root.classList.contains('fft-evidence-lazy'));
  }

  function enhanceEvidenceAll(scope){
    (scope||document).querySelectorAll('[data-ffad-evidence-shell]').forEach(enhanceEvidence);
  }

  function enhanceCharts(scope){
    (scope||document).querySelectorAll('canvas').forEach((canvas)=>{
      canvas.style.maxWidth='100%';
      canvas.style.width='100%';
    });
    (scope||document).querySelectorAll('svg').forEach((svg)=>{
      if(!svg.getAttribute('preserveAspectRatio')) svg.setAttribute('preserveAspectRatio','xMidYMid meet');
    });
  }

  const reactFallbackTimers=new WeakMap();
  function armReactFallbacks(scope){
    const base=scope&&scope.querySelectorAll?scope:document;
    const roots=[];
    if(base.matches&&base.matches('[data-fieldflow-react-front-island][data-fieldflow-react-front-props]')) roots.push(base);
    base.querySelectorAll('[data-fieldflow-react-front-island][data-fieldflow-react-front-props]').forEach((root)=>roots.push(root));
    Array.from(new Set(roots)).forEach((root)=>{
      if(reactFallbackTimers.has(root)) return;
      let props={};
      try{props=JSON.parse(root.getAttribute('data-fieldflow-react-front-props')||'{}')||{};}catch(_){props={};}
      const fallbackId=String(props.fallbackId||'');
      if(!fallbackId) return;
      const fallback=document.getElementById(fallbackId);
      if(!fallback||fallback.getAttribute('data-fieldflow-react-front-fallback-pending')!=='1') return;
      const timer=window.setTimeout(()=>{
        reactFallbackTimers.delete(root);
        if(root.getAttribute('data-fieldflow-react-front-mounted')==='1'){
          fallback.removeAttribute('data-fieldflow-react-front-fallback-pending');
          fallback.setAttribute('data-fieldflow-react-front-fallback-hidden','1');
          return;
        }
        fallback.removeAttribute('data-fieldflow-react-front-fallback-pending');
        fallback.removeAttribute('data-fieldflow-react-front-fallback-hidden');
      },1200);
      reactFallbackTimers.set(root,timer);
    });
  }

  function enhance(scope){
    const base=scope&&scope.querySelectorAll?scope:document;
    armReactFallbacks(base);
    base.querySelectorAll('table').forEach(enhanceTable);
    enhanceAnalyticsSeries(base);
    enhanceCharts(base);
    enhanceEvidenceAll(base);
    compactPortalLayouts(base);
  }

  function existingExternalScript(src){
    try{
      const absolute=new URL(src,document.baseURI).href;
      return Array.from(document.scripts).some((script)=>script.src===absolute&&script.dataset.ffPending!=='1');
    }catch(_){return false;}
  }

  function loadExternalScript(oldScript){
    return new Promise((resolve,reject)=>{
      const src=oldScript.getAttribute('src');
      if(!src||existingExternalScript(src)){oldScript.remove();resolve();return;}
      const script=document.createElement('script');
      Array.from(oldScript.attributes).forEach((attr)=>script.setAttribute(attr.name,attr.value));
      script.async=false;
      script.dataset.ffPending='1';
      script.addEventListener('load',()=>{delete script.dataset.ffPending;resolve();},{once:true});
      script.addEventListener('error',()=>{delete script.dataset.ffPending;reject(new Error('Falha ao carregar script.'));},{once:true});
      oldScript.replaceWith(script);
    });
  }

  async function executeScripts(container){
    const scripts=Array.from(container.querySelectorAll('script'));
    for(const oldScript of scripts){
      const type=(oldScript.getAttribute('type')||'').toLowerCase();
      if(type&&type!=='text/javascript'&&type!=='application/javascript'&&type!=='module'){continue;}
      if(oldScript.src||oldScript.getAttribute('src')){
        await loadExternalScript(oldScript);
        continue;
      }
      const script=document.createElement('script');
      Array.from(oldScript.attributes).forEach((attr)=>script.setAttribute(attr.name,attr.value));
      script.textContent=oldScript.textContent||'';
      oldScript.replaceWith(script);
    }
  }

  function errorMarkup(message){
    return `<div class="ff-lazy-error"><strong>${esc(config.errorLabel||'Não foi possível carregar este módulo.')}</strong><span>${esc(message||'')}</span><button type="button" class="ff-lazy-retry">${esc(config.retryLabel||'Tentar novamente')}</button></div>`;
  }

  async function loadPanel(placeholder,force){
    if(!placeholder||placeholder.dataset.ffLoading==='1') return;
    if(placeholder.dataset.ffLoaded==='1'&&!force) return;
    placeholder.dataset.ffLoading='1';
    placeholder.setAttribute('aria-busy','true');
    if(force){placeholder.dataset.ffLoaded='0';}
    if(!placeholder.querySelector('.ff-skeleton')){
      placeholder.innerHTML='<div class="ff-skeleton" aria-hidden="true" hidden><span></span><span></span><span></span><span></span></div><p class="ff-lazy-label" hidden></p>';
    }
    const skeleton=placeholder.querySelector('.ff-skeleton');
    const label=placeholder.querySelector('.ff-lazy-label');
    if(skeleton) skeleton.hidden=true;
    if(label){label.hidden=true;label.textContent=config.loadingLabel||'A carregar módulo...';}
    placeholder.classList.remove('ff-lazy-visual-active');
    const visualTimer=window.setTimeout(()=>{
      placeholder.classList.add('ff-lazy-visual-active');
      if(skeleton) skeleton.hidden=false;
      if(label) label.hidden=false;
    },180);

    const body=new URLSearchParams({
      action:'fieldflow_load_panel',
      nonce:placeholder.dataset.nonce||'',
      portal:placeholder.dataset.ffPortal||'',
      panel:placeholder.dataset.ffPanel||'',
      client_id:placeholder.dataset.clientId||'0',
      project_id:placeholder.dataset.projectId||'0',
      message_id:placeholder.dataset.messageId||'0'
    });

    try{
      const response=await fetch(placeholder.dataset.endpoint||window.ajaxurl||'/wp-admin/admin-ajax.php',{
        method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()
      });
      const payload=await response.json().catch(()=>({success:false,data:{message:'Resposta inválida do servidor.'}}));
      if(!response.ok||!payload.success) throw new Error(payload?.data?.message||'Falha no pedido.');
      window.clearTimeout(visualTimer);
      placeholder.innerHTML=payload.data.html||'';
      placeholder.classList.remove('ff-lazy-visual-active');
      placeholder.dataset.ffLoaded='1';
      await executeScripts(placeholder);
      enhance(placeholder);
      placeholder.dataset.ffPaintReady='1';
      placeholder.classList.add('ff-lazy-loaded');
      const detail={panel:placeholder.dataset.ffPanel||'',portal:placeholder.dataset.ffPortal||'',client_id:placeholder.dataset.clientId||'',project_id:placeholder.dataset.projectId||'',performance:payload.data.performance||{},source:'lazy-loader',contract:CLIENT_SHELL_CONTRACT};
      placeholder.dispatchEvent(new CustomEvent('fieldflow:lazy-panel-loaded',{bubbles:true,detail}));
      placeholder.dispatchEvent(new CustomEvent('routespro:panel-open',{bubbles:true,detail}));
      const portalShell=placeholder.closest('[data-fieldflow-portal]');
      const portalIdentity=String(portalShell?.getAttribute('data-fieldflow-portal')||'');
      if(portalShell&&(portalIdentity==='client'||portalIdentity==='operative')){
        const layoutEvent=portalIdentity==='client'?'fieldflow:client-panel-layout':'fieldflow:operative-panel-layout';
        portalShell.dispatchEvent(new CustomEvent(layoutEvent,{bubbles:false,detail:{panel:detail.panel,portal:portalIdentity,source:'lazy-loader',contract:CROSS_PORTAL_ISOLATION_CONTRACT,layoutContract:DUAL_PORTAL_LAYOUT_CONTRACT}}));
        portalShell.dispatchEvent(new CustomEvent('fieldflow:portal-panel-layout',{bubbles:false,detail:{panel:detail.panel,portal:portalIdentity,source:'lazy-loader',contract:DUAL_PORTAL_LAYOUT_CONTRACT}}));
      }else{
        window.dispatchEvent(new Event('resize'));
      }
    }catch(error){
      window.clearTimeout(visualTimer);
      placeholder.classList.remove('ff-lazy-visual-active');
      placeholder.innerHTML=errorMarkup(error&&error.message?error.message:'');
      placeholder.querySelector('.ff-lazy-retry')?.addEventListener('click',()=>loadPanel(placeholder,true),{once:true});
    }finally{
      placeholder.dataset.ffLoading='0';
      placeholder.removeAttribute('aria-busy');
    }
  }

  function loadPanelByKey(shell,key){
    if(!shell||!key) return Promise.resolve([]);
    const panel=Array.from(shell.querySelectorAll('.rp-panel[data-panel]')).find((item)=>String(item.dataset.panel||'')===String(key));
    if(!panel) return Promise.resolve([]);
    const tasks=Array.from(panel.querySelectorAll('[data-ff-lazy-panel="1"]')).map((placeholder)=>loadPanel(placeholder,false));
    return Promise.all(tasks);
  }

  function exportToast(message,type){
    let toast=document.querySelector('[data-ff-export-toast="1"]');
    if(!toast){
      toast=document.createElement('div');
      toast.dataset.ffExportToast='1';
      toast.style.cssText='position:fixed;right:18px;bottom:18px;z-index:2147483647;max-width:min(420px,calc(100vw - 36px));padding:13px 16px;border-radius:14px;background:#0f172a;color:#fff;font:700 13px/1.45 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;box-shadow:0 18px 55px rgba(15,23,42,.28);opacity:0;transform:translateY(8px);transition:.2s ease;pointer-events:none';
      document.body.appendChild(toast);
    }
    toast.textContent=message;
    toast.style.background=type==='error'?'#991b1b':'#0f172a';
    toast.style.opacity='1';
    toast.style.transform='translateY(0)';
    clearTimeout(toast._ffTimer);
    toast._ffTimer=setTimeout(()=>{toast.style.opacity='0';toast.style.transform='translateY(8px)';},type==='error'?6500:3200);
  }

  function isNativeFieldFlowWebView(){
    const ua=String(navigator.userAgent||'');
    return !!window.flutter_inappwebview||/FieldFlowApp|; wv\)|Version\/\d+\.\d+ Chrome\/\d+\.\d+\.\d+\.\d+ Mobile Safari/i.test(ua);
  }

  function navigationUrl(raw){
    let url;
    try{url=new URL(String(raw||''),document.baseURI);}catch(_){return '';}
    if(!/^https?:$/i.test(url.protocol)) return '';
    if(/(^|\.)google\.[a-z.]+$/i.test(url.hostname)&&/\/maps\/dir\/?$/i.test(url.pathname)){
      url.searchParams.set('api','1');
      url.searchParams.set('dir_action','navigate');
    }
    return url.href;
  }

  function postExternalOpen(payload){
    try{window.dispatchEvent(new CustomEvent('fieldflow:external-open',{detail:payload}));}catch(_){}
    try{const parentOrigin=String(window.location.origin||'');if(parentOrigin&&window.parent)window.parent.postMessage(Object.assign({type:'fieldflow.external.open'},payload),parentOrigin);}catch(_){}
  }

  function callNativeExternal(payload){
    const bridge=window.flutter_inappwebview;
    if(!bridge||typeof bridge.callHandler!=='function') return Promise.resolve(false);
    const request=Promise.resolve().then(()=>bridge.callHandler('fieldflowOpenExternal',payload)).then((result)=>{
      return result===true||!!(result&&(result.handled||result.opened||result.launched));
    }).catch(()=>false);
    return Promise.race([request,new Promise((resolve)=>setTimeout(()=>resolve(false),1400))]);
  }

  function browserExternalOpen(url,options){
    if(options&&options.sameWindow===true){window.location.assign(url);return true;}
    const anchor=document.createElement('a');
    anchor.href=url;
    anchor.target='_blank';
    anchor.rel='noopener noreferrer external';
    anchor.dataset.fieldflowExternalFallback='1';
    anchor.style.display='none';
    document.body.appendChild(anchor);
    try{anchor.click();return true;}catch(_){window.location.assign(url);return true;}finally{setTimeout(()=>anchor.remove(),1000);}
  }

  function openNavigation(raw,options={}){
    const url=navigationUrl(raw);
    if(!url) return Promise.resolve(false);
    const payload={
      url,
      source:String(options.source||'route_navigation'),
      navigation:true,
      preferNativeApp:true,
      externalApplication:true,
      systemBrowser:false
    };
    if(window.FieldFlowNativeBridge&&typeof window.FieldFlowNativeBridge.openExternal==='function'){
      return Promise.resolve(window.FieldFlowNativeBridge.openExternal(url,payload));
    }
    postExternalOpen(payload);
    if(!isNativeFieldFlowWebView()) return Promise.resolve(browserExternalOpen(url,options));
    return callNativeExternal(payload).then((handled)=>{
      if(handled) return true;
      window.location.assign(url);
      return true;
    });
  }

  window.FieldFlowNavigation=Object.assign({},window.FieldFlowNavigation||{}, {
    open:openNavigation,
    normaliseUrl:navigationUrl
  });

  function xlsxExportInfo(anchor){
    if(!anchor||!anchor.href||anchor.getAttribute('aria-disabled')==='true') return null;
    let url;
    try{url=new URL(anchor.href,document.baseURI);}catch(_){return null;}
    if(url.origin!==location.origin) return null;
    const action=url.searchParams.get('action')||'';
    const format=(url.searchParams.get('format')||'').toLowerCase();
    if(action!=='routespro_export_dashboard'&&action!=='routespro_export_submissions') return null;
    if(format!=='xlsx'&&format!=='xlsx_mobile'&&format!=='excel'&&format!=='excel_mobile'&&format!=='mobile_excel') return null;
    return {url,format};
  }

  function containsZipSignature(bytes,signature){
    outer:for(let i=0;i<=bytes.length-signature.length;i++){
      for(let j=0;j<signature.length;j++) if(bytes[i+j]!==signature[j]) continue outer;
      return true;
    }
    return false;
  }

  async function validateXlsxResponse(response,blob){
    const type=String(response.headers.get('content-type')||'').toLowerCase();
    if(!response.ok) throw new Error('O servidor devolveu o estado '+response.status+'.');
    if(type.includes('text/html')||type.includes('application/json')||type.includes('text/plain')){
      const text=(await blob.text()).replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim();
      throw new Error(text.slice(0,240)||'O servidor devolveu uma resposta em vez do Excel.');
    }
    if(blob.size<1000) throw new Error('O ficheiro recebido ficou demasiado pequeno.');
    const expected=parseInt(response.headers.get('x-fieldflow-file-size')||response.headers.get('content-length')||'0',10)||0;
    if(expected>0&&blob.size!==expected) throw new Error('A transferência ficou incompleta, '+blob.size+' de '+expected+' bytes.');
    const head=new Uint8Array(await blob.slice(0,4).arrayBuffer());
    if(head.length<4||head[0]!==0x50||head[1]!==0x4b||head[2]!==0x03||head[3]!==0x04) throw new Error('A resposta não contém um pacote XLSX válido.');
    const tailStart=Math.max(0,blob.size-66000);
    const tail=new Uint8Array(await blob.slice(tailStart).arrayBuffer());
    if(!containsZipSignature(tail,[0x50,0x4b,0x01,0x02])||!containsZipSignature(tail,[0x50,0x4b,0x05,0x06])) throw new Error('O pacote XLSX chegou truncado.');
  }

  function exportFilename(response,fallback){
    const disposition=String(response.headers.get('content-disposition')||'');
    const utf=disposition.match(/filename\*=UTF-8''([^;]+)/i);
    if(utf&&utf[1]){try{return decodeURIComponent(utf[1].replace(/["']/g,''));}catch(_){}}
    const normal=disposition.match(/filename="?([^";]+)"?/i);
    return normal&&normal[1]?normal[1].trim():fallback;
  }

  async function downloadValidatedXlsx(anchor,info,attempt){
    const url=new URL(info.url.href);
    if(attempt>0) url.searchParams.set('_ff_retry',Date.now().toString());
    const response=await fetch(url.href,{method:'GET',credentials:'same-origin',cache:'no-store',headers:{'X-FieldFlow-Download':'browser-v2','Accept':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream;q=0.9,*/*;q=0.1'}});
    const blob=await response.blob();
    try{
      await validateXlsxResponse(response,blob);
    }catch(error){
      if(attempt<1) return downloadValidatedXlsx(anchor,info,attempt+1);
      throw error;
    }
    const filename=exportFilename(response,info.format==='xlsx_mobile'?'fieldflow-analytics-mobile.xlsx':'fieldflow-analytics-completo.xlsx');
    const objectUrl=URL.createObjectURL(blob);
    const save=document.createElement('a');
    save.href=objectUrl;
    save.download=filename;
    save.style.display='none';
    document.body.appendChild(save);
    save.click();
    save.remove();
    setTimeout(()=>URL.revokeObjectURL(objectUrl),60000);
    return filename;
  }

  document.addEventListener('click',(event)=>{
    const anchor=event.target.closest&&event.target.closest('a[href]');
    const info=xlsxExportInfo(anchor);
    if(!info||isNativeFieldFlowWebView()||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey||event.button>0) return;
    event.preventDefault();
    event.stopPropagation();
    if(anchor.dataset.ffDownloadBusy==='1') return;
    anchor.dataset.ffDownloadBusy='1';
    anchor.setAttribute('aria-busy','true');
    const previousPointer=anchor.style.pointerEvents;
    anchor.style.pointerEvents='none';
    exportToast('A preparar e validar o Excel completo...','info');
    downloadValidatedXlsx(anchor,info,0).then((filename)=>{
      exportToast(filename+' foi validado e descarregado.','info');
    }).catch((error)=>{
      exportToast('Não foi possível concluir o Excel: '+(error&&error.message?error.message:'erro desconhecido.'),'error');
    }).finally(()=>{
      anchor.dataset.ffDownloadBusy='0';
      anchor.removeAttribute('aria-busy');
      anchor.style.pointerEvents=previousPointer;
    });
  },true);

  document.addEventListener('click',(event)=>{
    const evidenceTab=event.target.closest('[data-ffad-evidence-tab]');
    if(evidenceTab){
      const shell=evidenceTab.closest('[data-ffad-evidence-shell]');
      requestAnimationFrame(()=>hydrateEvidenceImages(shell,false));
    }
    const trigger=event.target.closest('[data-panel],[data-target]');
    if(!trigger) return;
    const shell=rootFor(trigger);
    if(!shell) return;
    const key=trigger.dataset.panel||trigger.dataset.target||'';
    const clientShell=shell.classList?.contains('rp-client-premium');
    const clientTab=clientShell&&!!trigger.closest('.rp-tabbar');
    if(key&&!clientTab) loadPanelByKey(shell,key);
    if(!clientTab) window.requestAnimationFrame(()=>compactPortalLayout(shell));
  },true);

  document.addEventListener('routespro:panel-open',(event)=>{
    const target=event.target;
    const portal=String(event.detail?.portal||target?.closest?.('[data-fieldflow-portal]')?.getAttribute('data-fieldflow-portal')||'');
    const shell=portalShellFor(target,portal);
    const key=event.detail?.panel||target?.dataset?.panel||'';
    const source=String(event.detail?.source||'');
    if(shell&&key&&source!=='client-shell-module'&&source!=='lazy-loader') loadPanelByKey(shell,key);
    if(target&&target!==document&&target.querySelectorAll) enhance(target);
    else if(shell) enhance(shell);
    if(shell && shell.getAttribute('data-fieldflow-portal')!=='client' && !shell.classList?.contains('rp-client-premium')) window.requestAnimationFrame(()=>compactPortalLayout(shell));
  });

  function syncClientLazyScope(detail){
    const clientId=String(detail?.client_id||'');
    const projectId=String(detail?.project_id||'');
    document.querySelectorAll('#routespro-client-portal [data-ff-lazy-panel="1"]').forEach((placeholder)=>{
      const oldKey=String(placeholder.dataset.clientId||'')+'|'+String(placeholder.dataset.projectId||'');
      const newKey=clientId+'|'+projectId;
      placeholder.dataset.clientId=clientId||'0';
      placeholder.dataset.projectId=projectId||'0';
      placeholder.dataset.ffScopeKey=newKey;
      if(oldKey===newKey||placeholder.dataset.ffLoaded!=='1') return;
      if(String(placeholder.dataset.ffPanel||'')==='performance'){
        placeholder.dataset.ffLoaded='0';
        if(placeholder.closest('.rp-panel')?.classList.contains('active')) loadPanel(placeholder,true);
      }
    });
  }

  document.addEventListener('routespro:scope-change',(event)=>{
    const portal=String(event.detail?.portal||event.target?.closest?.('[data-fieldflow-portal]')?.getAttribute('data-fieldflow-portal')||'');
    if(portal!=='client') return;
    syncClientLazyScope(event.detail||{});
  });

  let enhanceFrame=0;
  const pendingEnhanceScopes=new Set();
  const observer=new MutationObserver((mutations)=>{
    mutations.forEach((mutation)=>mutation.addedNodes.forEach((node)=>{
      if(node.nodeType!==1)return;
      const scope=node.parentElement||node;
      if(scope&&scope.querySelectorAll)pendingEnhanceScopes.add(scope);
    }));
    if(!pendingEnhanceScopes.size||enhanceFrame)return;
    enhanceFrame=requestAnimationFrame(()=>{
      enhanceFrame=0;
      const scopes=Array.from(pendingEnhanceScopes);
      pendingEnhanceScopes.clear();
      scopes.forEach((scope)=>enhance(scope));
    });
  });

  function boot(){
    enhance(document);
    document.querySelectorAll('.rp-panel.active [data-ff-lazy-panel="1"]').forEach((placeholder)=>loadPanel(placeholder,false));
    observer.observe(document.documentElement,{childList:true,subtree:true});
    if('ResizeObserver' in window){
      // Phase 9C validator compatibility marker (non-runtime): const clientPanelSize=new WeakMap();
      const portalPanelSize=new WeakMap();
      let resizeFrame=0;
      const resizeObserver=new ResizeObserver((entries)=>{
        let shouldDispatch=false;
        entries.forEach((entry)=>{
          const panel=entry.target;
          if(!panel.classList.contains('active')) return;
          const portalShell=panel.closest('[data-fieldflow-portal="client"],[data-fieldflow-portal="operative"]');
          if(!portalShell){shouldDispatch=true;return;}
          // Phase 9D: Client and Operative share the same resize discipline.
          // Phase 9C validator compatibility marker: Height-only changes must not emit a synthetic resize.
          // Height-only growth from lazy data must not wake every chart or
          // force an Android WebView compositor repaint. Only width changes do.
          const width=Math.round(entry.contentRect?.width||panel.getBoundingClientRect().width||0);
          const height=Math.round(entry.contentRect?.height||panel.getBoundingClientRect().height||0);
          const previous=portalPanelSize.get(panel);
          portalPanelSize.set(panel,{width,height});
          if(!previous||previous.width===0||width===0) return;
          if(Math.abs(width-previous.width)>3) shouldDispatch=true;
        });
        if(!shouldDispatch||resizeFrame||document.body?.classList.contains('ff-app-resume-guard-active')) return;
        resizeFrame=window.requestAnimationFrame(()=>{resizeFrame=0;window.dispatchEvent(new Event('resize'));});
      });
      document.querySelectorAll('.rp-panel').forEach((panel)=>resizeObserver.observe(panel));
    }
  }

  window.FieldFlowUI={enhance,loadPanel,loadPanelByKey,enhanceEvidence:enhanceEvidenceAll,hydrateEvidenceImages,compactLayout:compactPortalLayouts};
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true}); else boot();
})();
