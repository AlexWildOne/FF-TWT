(function(){
  'use strict';

  var config=window.FieldFlowBackofficeUIConfig||{};
  var body=document.body;
  if(!body||config.enabled===false) return;
  var startedAt=window.performance&&performance.now?performance.now():Date.now();

  function markOverflowRegions(){
    var selector=['.ff-table-scroll','.routespro-table-scroll','.ff-health-table-wrap','.table-responsive'].join(',');
    document.querySelectorAll(selector).forEach(function(region,index){
      region.classList.add('ff-admin-overflow-region');
      if(!region.hasAttribute('tabindex')) region.tabIndex=0;
      if(!region.hasAttribute('aria-label')) region.setAttribute('aria-label','Tabela com deslocação horizontal '+(index+1));
    });
  }

  function wrapOverflowingTables(){
    var protectedSelector='.ff-table-scroll,.routespro-table-scroll,.ff-health-table-wrap,.table-responsive,.ff-admin-generated-table-scroll';
    var wrapped=0;
    document.querySelectorAll('table.widefat,table.wp-list-table').forEach(function(table){
      if(table.closest(protectedSelector)) return;
      var host=table.parentElement;
      if(!host||['TD','TH'].indexOf(host.tagName)!==-1||host.clientWidth<1) return;
      if(table.scrollWidth<=host.clientWidth+4) return;
      var region=document.createElement('div');
      region.className='ff-admin-generated-table-scroll ff-admin-overflow-region';
      region.tabIndex=0;
      region.setAttribute('aria-label','Tabela com deslocação horizontal');
      host.insertBefore(region,table);
      region.appendChild(table);
      wrapped++;
    });
    return wrapped;
  }

  function releaseClippedContent(){
    var released=0;
    var candidates=document.querySelectorAll('.ff-hub-main,.ff-analytics-step,.routespro-card,.rp-card,.postbox .inside,.ffp-card,.ff-b-card,.ff-int-card,.ff-ops-card');
    candidates.forEach(function(node){
      var style=window.getComputedStyle(node);
      if(node.scrollHeight>node.clientHeight+6&&['hidden','clip'].indexOf(style.overflowY)!==-1){
        node.classList.add('ff-admin-unclip');
        released++;
      }
    });
    return released;
  }

  function visualAudit(){
    var root=document.querySelector('.wrap')||document.getElementById('wpbody-content');
    var viewportWidth=Math.max(document.documentElement.clientWidth||0,window.innerWidth||0);
    var pageOverflow=Math.max(document.documentElement.scrollWidth,body.scrollWidth)>viewportWidth+4;
    var duplicateIds=0;
    var ids=Object.create(null);
    document.querySelectorAll('[id]').forEach(function(node){
      if(ids[node.id]) duplicateIds++;
      else ids[node.id]=true;
    });
    var clippedText=0;
    if(root){
      root.querySelectorAll('h1,h2,h3,p,label,td,th').forEach(function(node){
        var style=window.getComputedStyle(node);
        if(node.scrollWidth>node.clientWidth+4&&['hidden','clip'].indexOf(style.overflowX)!==-1) clippedText++;
      });
    }
    return {pageOverflow:pageOverflow,duplicateIds:duplicateIds,clippedTextNodes:clippedText};
  }

  function diagnostics(run){
    var regions=Array.prototype.slice.call(document.querySelectorAll('.ff-admin-overflow-region'));
    var audit=config.visualAudit===false?{pageOverflow:false,duplicateIds:0,clippedTextNodes:0}:visualAudit();
    var finishedAt=window.performance&&performance.now?performance.now():Date.now();
    var runStarted=run&&run.startedAt?run.startedAt:startedAt;
    var state={
      version:String(config.version||''),
      page:String(config.page||''),
      scope:String(config.scope||''),
      managed:true,
      overflowRegions:regions.length,
      activeOverflowRegions:regions.filter(function(node){return node.scrollWidth>node.clientWidth+4;}).length,
      generatedTableRegions:document.querySelectorAll('.ff-admin-generated-table-scroll').length,
      unclippedNodes:document.querySelectorAll('.ff-admin-unclip').length,
      wrappedThisRun:Number(run&&run.wrapped||0),
      releasedThisRun:Number(run&&run.released||0),
      pageOverflow:audit.pageOverflow,
      clippedTextNodes:audit.clippedTextNodes,
      duplicateIds:audit.duplicateIds,
      auditDurationMs:Math.max(0,Math.round((finishedAt-runStarted)*100)/100)
    };
    window.FieldFlowBackofficeUIState=state;
    window.dispatchEvent(new CustomEvent('fieldflow:backoffice-ui-ready',{detail:state}));
  }

  var resizeTimer=0;
  function inspect(){
    var runStarted=window.performance&&performance.now?performance.now():Date.now();
    var run={startedAt:runStarted,wrapped:wrapOverflowingTables(),released:0};
    markOverflowRegions();
    run.released=releaseClippedContent();
    diagnostics(run);
  }
  function onResize(){
    window.clearTimeout(resizeTimer);
    resizeTimer=window.setTimeout(inspect,120);
  }

  body.dataset.ffBackofficeUi=String(config.version||'2.2.262');
  window.FieldFlowBackofficeUI={inspect:inspect,version:String(config.version||'2.2.262')};
  inspect();
  window.requestAnimationFrame(inspect);
  window.addEventListener('resize',onResize,{passive:true});
  document.addEventListener('click',function(event){
    var trigger=event.target&&event.target.closest?event.target.closest('.nav-tab,[role=tab],[data-campaign-tab],.ffp-tabs a,.ff-health-tabs button'):null;
    if(trigger) window.setTimeout(inspect,40);
  });
  window.addEventListener('fieldflow:layout-change',onResize);
  window.addEventListener('fieldflow:analytics-rendered',onResize);
  var root=document.querySelector('.wrap');
  if(root&&window.MutationObserver){
    new MutationObserver(onResize).observe(root,{childList:true,subtree:true});
  }
})();
