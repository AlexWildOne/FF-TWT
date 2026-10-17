(function(){
  'use strict';

  function normalise(value){
    var text=String(value||'').toLowerCase();
    return text.normalize?text.normalize('NFD').replace(/[\u0300-\u036f]/g,''):text;
  }

  function initCommercialImport(){
    var client=document.getElementById('rp-commercial-import-client');
    var project=document.getElementById('rp-commercial-import-project');
    if(!client||!project||project.dataset.ffComponentReady==='1') return;
    project.dataset.ffComponentReady='1';
    function sync(){
      var value=client.value;
      Array.prototype.forEach.call(project.options,function(option,index){
        if(index===0) return;
        option.hidden=Boolean(value)&&option.dataset.client!==value;
      });
      if(project.selectedOptions&&project.selectedOptions[0]&&project.selectedOptions[0].hidden) project.value='';
    }
    client.addEventListener('change',sync);
    sync();
  }

  function initCardexLocations(){
    var select=document.getElementById('ff-cardex-location-ids');
    var count=document.getElementById('ff-cardex-location-count');
    var visible=document.getElementById('ff-cardex-location-visible');
    var search=document.getElementById('ff-cardex-location-search');
    if(!select||!count||select.dataset.ffComponentReady==='1') return;
    select.dataset.ffComponentReady='1';
    function updateCount(){
      count.textContent=String(Array.prototype.filter.call(select.options,function(option){return option.selected;}).length);
    }
    function filter(){
      var term=normalise(search&&search.value);
      var visibleCount=0;
      Array.prototype.forEach.call(select.options,function(option){
        var show=!term||normalise(option.textContent).indexOf(term)!==-1||option.selected;
        option.hidden=!show;
        if(show) visibleCount++;
      });
      if(visible) visible.textContent=String(visibleCount);
    }
    select.addEventListener('change',function(){updateCount();filter();});
    if(search) search.addEventListener('input',filter);
    updateCount();
    filter();
  }

  function initReportAutomations(){
    var client=document.getElementById('ff-report-client');
    var project=document.getElementById('ff-report-project');
    var testScope=document.getElementById('ff-report-test-scope');
    var testUserWrap=document.getElementById('ff-report-test-user-wrap');
    var testUser=document.getElementById('ff-report-test-user');
    if(!client||!project||project.dataset.ffComponentReady==='1') return;
    project.dataset.ffComponentReady='1';
    var projectOptions=Array.prototype.slice.call(project.options,1);
    function syncTestUsers(){
      if(!testUser) return;
      var projectId=String(project.value||'');
      Array.prototype.forEach.call(testUser.options,function(option,index){
        if(index===0) return;
        var projects=String(option.dataset.projects||'').split(',').filter(Boolean);
        var allowed=Boolean(projectId)&&projects.indexOf(projectId)!==-1;
        option.hidden=!allowed;
        option.disabled=!allowed;
      });
      var selected=testUser.selectedOptions&&testUser.selectedOptions[0];
      if(selected&&selected.disabled) testUser.value='0';
      if(testUser.options[0]) testUser.options[0].textContent=projectId?'Selecionar merchandiser':'Seleciona primeiro a campanha';
    }
    function syncTest(){
      var personal=Boolean(testScope)&&testScope.value==='merchandiser';
      if(testUserWrap) testUserWrap.style.display=personal?'block':'none';
      if(testUser) testUser.disabled=!personal;
      syncTestUsers();
    }
    function sync(){
      var value=client.value;
      projectOptions.forEach(function(option){option.hidden=Boolean(value)&&option.dataset.client!==value;});
      if(project.selectedOptions&&project.selectedOptions[0]&&project.selectedOptions[0].hidden) project.value='';
      syncTest();
    }
    client.addEventListener('change',sync);
    project.addEventListener('change',syncTest);
    if(testScope) testScope.addEventListener('change',syncTest);
    sync();
  }

  function init(){
    initCommercialImport();
    initCardexLocations();
    initReportAutomations();
  }

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
