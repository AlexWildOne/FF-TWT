(function(){
  'use strict';

  var config=window.FieldFlowAdminNavigationConfig||{};
  if(config.enabled===false) return;
  var menu=document.querySelector('#toplevel_page_routespro .wp-submenu');
  if(!menu||menu.querySelector('.ff-admin-menu-section')) return;

  var definitions=[
    {key:'overview',label:'Visão geral',pages:['routespro']},
    {key:'management',label:'Gestão',pages:['routespro-clients','routespro-client-onboarding','routespro-subscriptions','routespro-license-tenants','routespro-billing','routespro-projects']},
    {key:'operation',label:'Operação',pages:['routespro-routes','routespro-assignments-hub','routespro-performance','routespro-expenses','routespro-briefings','routespro-implementations','routespro-stocks','routespro-shopping','routespro-mapping-center','routespro-commercial','routespro-stores','routespro-form-history','routespro-commercial-import','routespro-categories','routespro-campaign-locations']},
    {key:'forms',label:'Formulários',pages:['routespro-context-questions','routespro-forms','routespro-form-bindings','routespro-product-cardex','routespro-form-edit','routespro-form-submissions']},
    {key:'platform',label:'Plataforma',pages:['routespro-integrations','routespro-data-destinations','routespro-public-integrations','routespro-ai','routespro-import','routespro-emails','routespro-email-log','routespro-appearance','routespro-campaign-branding','routespro-mobile','routespro-devices','routespro-pwa']},
    {key:'system',label:'Sistema e Governação',pages:['routespro-privacy','routespro-security-observability','routespro-processes','routespro-settings','routespro-system-health']}
  ];

  function pageFromLink(link){
    try{return new URL(link.href,window.location.href).searchParams.get('page')||'';}catch(error){return '';}
  }

  var rows=Array.prototype.slice.call(menu.children).filter(function(row){return row.querySelector('a');});
  var groupedRows=[];
  var currentPage=String(config.page||'');
  definitions.forEach(function(group){
    var items=rows.filter(function(row){
      var link=row.querySelector('a');
      return link&&group.pages.indexOf(pageFromLink(link))!==-1;
    });
    if(!items.length) return;

    items.forEach(function(row){row.dataset.ffMenuGroup=group.key;groupedRows.push(row);});
    var header=document.createElement('li');
    header.className='ff-admin-menu-section';
    header.setAttribute('role','presentation');
    var button=document.createElement('button');
    button.type='button';
    button.textContent=group.label;
    var containsCurrent=group.pages.indexOf(currentPage)!==-1||items.some(function(row){return row.classList.contains('current');});
    var storageKey='fieldflow.admin.menu.v2.'+group.key;
    var stored='';
    try{stored=window.localStorage.getItem(storageKey)||'';}catch(error){}
    var expanded=containsCurrent||stored==='expanded'||(stored===''&&group.key==='system');
    button.setAttribute('aria-expanded',expanded?'true':'false');
    button.setAttribute('aria-label',(expanded?'Fechar ':'Abrir ')+group.label);
    header.appendChild(button);
    menu.appendChild(header);
    items.forEach(function(row){menu.appendChild(row);});

    function render(){
      items.forEach(function(row){row.classList.toggle('ff-admin-menu-item-collapsed',!expanded);});
      button.setAttribute('aria-expanded',expanded?'true':'false');
      button.setAttribute('aria-label',(expanded?'Fechar ':'Abrir ')+group.label);
    }
    button.addEventListener('click',function(){
      expanded=!expanded;
      try{window.localStorage.setItem(storageKey,expanded?'expanded':'collapsed');}catch(error){}
      render();
    });
    render();
  });

  // Preserve extension rows while keeping the FieldFlow catalogue contiguous.
  rows.forEach(function(row){
    if(groupedRows.indexOf(row)===-1) menu.appendChild(row);
  });
})();
