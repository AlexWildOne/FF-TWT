(function(){
  'use strict';
  const cfg=window.FieldFlowAutocomplete||{};
  const selector='input[data-ff-autocomplete="pdv"],input[placeholder="Nome, morada ou contacto"]';
  const escapeHtml=(value)=>String(value??'').replace(/[&<>"']/g,(c)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

  function init(input){
    if(!input||input.dataset.ffAutocompleteReady==='1') return;
    input.dataset.ffAutocompleteReady='1';
    let timer=null;
    let controller=null;
    const results=document.createElement('div');
    results.className='ff-autocomplete-results';
    results.setAttribute('role','listbox');
    input.parentNode?.appendChild(results);

    input.addEventListener('input',()=>{
      const query=String(input.value||'').trim();
      window.clearTimeout(timer);
      controller?.abort();
      if(query.length<(parseInt(cfg.minChars||3,10)||3)){results.innerHTML='';return;}
      timer=window.setTimeout(async()=>{
        controller=new AbortController();
        try{
          const endpoint=cfg.endpoint||'/wp-json/fieldflow/v1/pdvs-search';
          const url=new URL(endpoint,window.location.origin);
          url.searchParams.set('q',query);
          const response=await fetch(url.toString(),{credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:cfg.nonce?{'X-WP-Nonce':cfg.nonce}:{}});
          const data=await response.json();
          if(!response.ok||!Array.isArray(data)||!data.length){results.innerHTML='<div class="ff-no-results">Sem resultados</div>';return;}
          results.innerHTML=data.map((item)=>`<button type="button" class="ff-item" role="option" data-id="${escapeHtml(item.id)}" data-name="${escapeHtml(item.nome||'')}"><strong>${escapeHtml(item.nome||'')}</strong><small>${escapeHtml(item.cidade||'')}</small></button>`).join('');
        }catch(error){if(error?.name!=='AbortError')results.innerHTML='';}
      },280);
    });

    results.addEventListener('click',(event)=>{
      const item=event.target.closest('.ff-item');
      if(!item) return;
      input.value=item.dataset.name||item.textContent.trim();
      input.dispatchEvent(new Event('change',{bubbles:true}));
      results.innerHTML='';
    });
    document.addEventListener('click',(event)=>{if(event.target!==input&&!results.contains(event.target))results.innerHTML='';});
  }

  function scan(scope){(scope||document).querySelectorAll(selector).forEach(init);}
  const observer=new MutationObserver((mutations)=>mutations.forEach((mutation)=>mutation.addedNodes.forEach((node)=>{if(node.nodeType!==1)return;if(node.matches?.(selector))init(node);scan(node);}))); 
  function boot(){scan(document);observer.observe(document.documentElement,{childList:true,subtree:true});}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
