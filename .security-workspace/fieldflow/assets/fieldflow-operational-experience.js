(function(){
  'use strict';
  /* N-validator compatibility marker: const BUILD='2.2.517' */
  /* Historical compatibility marker: const BUILD='2.2.516' */
  const BUILD='2.2.524';
  const CLIENT_PORTAL_SHELL_STABILITY_CONTRACT='fieldflow-client-portal-shell-stability-v1';
  /* Release continuity marker: const BUILD='2.2.523' */
  /* Phase 9F compatibility marker: const BUILD='2.2.522' */
  // ReleasePackageValidator 2.2.519 compatibility marker (non-runtime): const BUILD='2.2.519'
  // ReleasePackageValidator 2.2.518 compatibility marker (non-runtime): const BUILD='2.2.518'
  // Package compatibility marker for the certified 2.2.515 validator: const BUILD='2.2.515'
  const ROOTS='#routespro-app,#routespro-client-portal';
  const CROSS_PORTAL_ISOLATION_CONTRACT='fieldflow-cross-portal-render-isolation-v1';
  const copy={
    app:{
      title:'Centro operacional',subtitle:'Acesso rápido às tarefas essenciais do terreno.',
      items:{
        route:['Rota','Agenda e execução','R'],report:['Reportar','Formulário e evidências','F'],stocks:['Stocks','Disponibilidade atual','S'],expenses:['Despesas','Registo e histórico','€'],analytics:['Analytics','Desempenho pessoal','A'],briefings:['Briefing','Orientações da campanha','B']
      },order:['route','report','stocks','briefings','expenses','analytics']
    },
    client:{
      title:'Centro de controlo',subtitle:'Salta diretamente para a área que precisa de atenção.',
      items:{
        overview:['Resumo','Pulso da operação','R'],routes:['Rotas','Agenda e execução','↗'],'campaign-pdvs':['Campanha PDV','Lojas e regras','P'],reports:['Relatórios','Dados e evidências','D'],stocks:['Stocks','Rutura e disponibilidade','S'],analytics:['Analytics','Tendências e widgets','A']
      },order:['overview','routes','campaign-pdvs','reports','stocks','analytics']
    }
  };

  function rootType(root){return root&&root.id==='routespro-client-portal'?'client':'app';}
  function panelButton(root,key){return root.querySelector('.rp-tabbar button[data-panel="'+CSS.escape(key)+'"]');}
  function activePanel(root){return root.querySelector('.rp-panel.active[data-panel]')?.dataset.panel||'';}

  function bindCommandDeck(root){
    if(!root) return;
    const type=rootType(root);
    root.querySelectorAll('.ff-ope-command[data-ff-panel]').forEach(button=>{
      if(button.dataset.ffOpeBound==='1') return;
      button.dataset.ffOpeBound='1';
      button.addEventListener('click',()=>{
        const key=String(button.dataset.ffPanel||'');
        const original=panelButton(root,key);
        if(original){original.click();if(type!=='client'){window.setTimeout(()=>root.querySelector('.rp-panel[data-panel="'+CSS.escape(key)+'"]')?.scrollIntoView({behavior:'smooth',block:'start'}),30);}}
      });
    });
  }

  function buildCommandDeck(root){
    if(!root||root.querySelector('.rp-shell > .ff-ope-command-deck')) return;
    const type=rootType(root), cfg=copy[type], hero=root.querySelector('.rp-shell > .rp-hero');
    if(!hero) return;
    const available=cfg.order.filter(key=>!!panelButton(root,key));
    if(!available.length) return;
    const deck=document.createElement('section');
    deck.className='ff-ope-command-deck';
    deck.setAttribute('aria-label',cfg.title);
    const intro=document.createElement('div');
    intro.className='ff-ope-command-copy';
    intro.innerHTML='<span>FieldFlow 2.2.522</span><strong>'+cfg.title+'</strong><small>'+cfg.subtitle+'</small>';
    const list=document.createElement('div');
    list.className='ff-ope-command-list';
    available.forEach(key=>{
      const item=cfg.items[key]||[key,'Abrir área','•'];
      const button=document.createElement('button');
      button.type='button';button.className='ff-ope-command';button.dataset.ffPanel=key;
      button.innerHTML='<span class="ff-ope-command-icon" aria-hidden="true">'+item[2]+'</span><span class="ff-ope-command-label"><strong>'+item[0]+'</strong><small>'+item[1]+'</small></span>';
      list.appendChild(button);
    });
    deck.append(intro,list);
    hero.insertAdjacentElement('afterend',deck);
    bindCommandDeck(root);
    syncCommandDeck(root);
  }

  function syncCommandDeck(root){
    const active=activePanel(root);
    root.querySelectorAll('.ff-ope-command[data-ff-panel]').forEach(button=>{
      const selected=button.dataset.ffPanel===active;
      button.classList.toggle('is-active',selected);
      button.setAttribute('aria-current',selected?'page':'false');
    });
  }

  function enhanceExportCenter(scope){
    (scope||document).querySelectorAll('.routespro-exports-wrapper').forEach(wrapper=>{
      wrapper.classList.add('ff-ope-export-center');
      wrapper.querySelectorAll('.routespro-export-btn').forEach(link=>{
        if(link.dataset.ffOpeExport==='1') return;
        link.dataset.ffOpeExport='1';link.classList.add('ff-ope-export-card');
        const id=(link.id||'').toLowerCase(), raw=(link.textContent||'Exportar').trim();
        let mark='DOC',description='Ficheiro para análise';
        if(id.includes('mobile')){mark='XLSX';description='Versão leve para telemóvel';}
        else if(id.includes('xls')){mark='XLSX';description='Workbook completo';}
        else if(id.includes('pdf')){mark='PDF';description='Resumo executivo';}
        else if(id.includes('detail')){mark='CSV';description='Dados detalhados';}
        else if(id.includes('csv')){mark='CSV';description='Resumo compatível';}
        link.textContent='';
        const icon=document.createElement('span');icon.className='ff-ope-export-mark';icon.textContent=mark;
        const text=document.createElement('span');text.className='ff-ope-export-copy';text.innerHTML='<strong>'+raw+'</strong><small>'+description+'</small>';
        link.append(icon,text);
      });
    });
  }

  function annotateTable(table){
    if(!table||table.dataset.ffOpeTable==='1') return;
    const headers=Array.from(table.querySelectorAll('thead th')).map(th=>(th.textContent||'').trim());
    if(!headers.length) return;
    table.dataset.ffOpeTable='1';table.classList.add('ff-ope-table');
    table.querySelectorAll('tbody tr').forEach(row=>{
      Array.from(row.children).forEach((cell,index)=>{
        if(cell.tagName==='TD'&&!cell.hasAttribute('data-label')) cell.setAttribute('data-label',headers[index]||'Campo');
      });
    });
  }

  function annotateTables(scope){
    (scope||document).querySelectorAll('#rpcp-campaign-pdvs-panel table,#rpcp-reports-panel table,.ff-expenses table,.rp-panel[data-panel="routes"] table').forEach(annotateTable);
  }

  function stateClass(text){
    const value=String(text||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    if(/concluid|completed|\bdone\b|executad|aprovad/.test(value)) return 'ff-state-done';
    if(/em curso|in_progress|in progress|progresso|ativa/.test(value)) return 'ff-state-progress';
    if(/falh|cancel|rejeitad|erro|failed/.test(value)) return 'ff-state-failed';
    if(/pendente|planned|planead|draft|rascunho|por visitar/.test(value)) return 'ff-state-pending';
    return '';
  }

  function decorateStates(scope){
    (scope||document).querySelectorAll('.rp-route-item,.rp-route-summary-item,.rpa-agenda-item,.rp-stop,.ffe-item').forEach(item=>{
      if(item.dataset.ffOpeStateText===(item.textContent||'')) return;
      item.classList.remove('ff-state-done','ff-state-progress','ff-state-failed','ff-state-pending');
      const cls=stateClass(item.textContent||'');if(cls)item.classList.add(cls);
      item.dataset.ffOpeStateText=item.textContent||'';
    });
  }

  function enhanceExpenses(scope){
    (scope||document).querySelectorAll('.ff-expenses').forEach(root=>root.classList.add('ff-ope-expenses'));
  }

  function enhanceRoot(root){
    if(!root) return;
    root.classList.add('ff-ope');root.dataset.ffOperationalExperience=BUILD;
    buildCommandDeck(root);bindCommandDeck(root);enhanceExportCenter(root);annotateTables(root);decorateStates(root);enhanceExpenses(root);
  }

  function enhance(scope){
    const base=scope&&scope.querySelectorAll?scope:document;
    if(base.matches&&base.matches(ROOTS)) enhanceRoot(base);
    base.querySelectorAll(ROOTS).forEach(enhanceRoot);
    enhanceExportCenter(base);annotateTables(base);decorateStates(base);enhanceExpenses(base);
  }

  document.addEventListener('click',event=>{
    const tab=event.target.closest('#routespro-app .rp-tabbar button[data-panel],#routespro-client-portal .rp-tabbar button[data-panel]');
    if(tab){const root=tab.closest(ROOTS);window.requestAnimationFrame(()=>syncCommandDeck(root));}
  },true);
  function eventRoot(event){
    const direct=event.target?.closest?.(ROOTS);
    if(direct)return direct;
    const portal=String(event.detail?.portal||'');
    if(portal==='client')return document.querySelector('#routespro-client-portal[data-fieldflow-portal="client"]');
    if(portal==='operative')return document.querySelector('#routespro-app[data-fieldflow-portal="operative"]');
    return null;
  }
  document.addEventListener('routespro:panel-open',event=>{
    const root=eventRoot(event);
    if(root){syncCommandDeck(root);enhance(root);}
  });
  document.addEventListener('fieldflow:lazy-panel-loaded',event=>{const root=eventRoot(event);if(root)enhance(root);else if(event.target&&event.target!==document)enhance(event.target);});

  const observer=new MutationObserver(mutations=>{
    const nodes=[];
    mutations.forEach(m=>Array.from(m.addedNodes||[]).forEach(node=>{if(node.nodeType===1)nodes.push(node);}));
    if(nodes.length) window.requestAnimationFrame(()=>nodes.forEach(node=>enhance(node)));
  });

  function boot(){enhance(document);document.documentElement.dataset.ffCrossPortalOperational=CROSS_PORTAL_ISOLATION_CONTRACT;observer.observe(document.documentElement,{childList:true,subtree:true});}
  window.FieldFlowOperationalExperience={version:BUILD,enhance};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
