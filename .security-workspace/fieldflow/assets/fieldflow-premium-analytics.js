(function(){
  'use strict';

  var config=window.FieldFlowPremiumAnalyticsConfig||{};
  var PAGE_SIZE=Math.max(4,Math.min(20,parseInt(config.seriesPageSize||8,10)||8));
  var ROOTS='.routespro-dashboard,.rp-client-premium';
  var CROSS_PORTAL_ISOLATION_CONTRACT='fieldflow-cross-portal-render-isolation-v1';

  function widgetKind(node){
    if(!node) return 'generic';
    var cls=node.classList;
    if(cls.contains('image_gallery')||cls.contains('has-evidence')||cls.contains('is-evidence')) return 'evidence';
    if(cls.contains('has-share')||cls.contains('is-share-space')) return 'share-space';
    if(cls.contains('has-fronts')||cls.contains('is-fronts')) return 'fronts';
    if(cls.contains('is-order')) return 'order';
    if(cls.contains('table')||cls.contains('has-table')||cls.contains('rp-analytics-table-card')) return 'table';
    if(cls.contains('kpi')||cls.contains('is-kpi')) return 'kpi';
    if(cls.contains('pie')||cls.contains('is-pie')) return 'pie';
    if(cls.contains('line')||cls.contains('is-line')) return 'line';
    if(cls.contains('bar')||cls.contains('is-bar')) return 'bar';
    if(node.querySelector('.ffad-series-row.is-text-value')) return 'text';
    return 'generic';
  }

  function classifyWidgets(scope){
    var root=scope&&scope.querySelectorAll?scope:document;
    root.querySelectorAll('.ffad-metric,.rp-analytics-visual-card,.rp-analytics-table-card').forEach(function(node){
      var kind=widgetKind(node);
      node.dataset.ffWidgetKind=kind;
      node.classList.add('ff-widget-premium');
      var heading=node.querySelector('.ffad-metric-label strong,.rp-analytics-card-copy h4,.rp-panel-head h4');
      if(heading&&!node.getAttribute('aria-label')) node.setAttribute('aria-label',(heading.textContent||'Widget analytics').trim());
    });
  }

  function markSeriesRanks(scope){
    var root=scope&&scope.querySelectorAll?scope:document;
    root.querySelectorAll('.ffad-series').forEach(function(series){
      var rows=Array.from(series.querySelectorAll(':scope > .ffad-series-row'));
      rows.forEach(function(row,index){
        var rank=row.querySelector(':scope > em');
        if(rank&&!rank.textContent.trim()) rank.textContent=String(index+1);
      });
      if(series.dataset.ffPremiumPaged==='1'||rows.length<=PAGE_SIZE) return;
      series.dataset.ffPremiumPaged='1';
      rows.slice(PAGE_SIZE).forEach(function(row){row.hidden=true;});
      var button=document.createElement('button');
      button.type='button';
      button.className='ff-analytics-more';
      button.textContent='Mostrar mais '+String(rows.length-PAGE_SIZE);
      button.setAttribute('aria-expanded','false');
      button.addEventListener('click',function(){
        var expanded=button.getAttribute('aria-expanded')==='true';
        rows.slice(PAGE_SIZE).forEach(function(row){row.hidden=expanded;});
        button.setAttribute('aria-expanded',expanded?'false':'true');
        button.textContent=expanded?'Mostrar mais '+String(rows.length-PAGE_SIZE):'Mostrar menos';
      });
      series.insertAdjacentElement('afterend',button);
    });
  }

  function normalizeStates(scope){
    var root=scope&&scope.querySelectorAll?scope:document;
    root.querySelectorAll('.ffad-empty,.ffad-custom-empty,.rp-chart-empty,.ff-lazy-error').forEach(function(node){
      node.classList.add('ff-analytics-state');
      var text=(node.textContent||'').toLowerCase();
      node.classList.toggle('is-loading',text.indexOf('carregar')!==-1||text.indexOf('loading')!==-1);
      node.classList.toggle('is-error',text.indexOf('erro')!==-1||text.indexOf('não foi possível')!==-1||text.indexOf('falha')!==-1);
    });
  }

  function improveTables(scope){
    var root=scope&&scope.querySelectorAll?scope:document;
    root.querySelectorAll('.routespro-dashboard table,.rp-client-premium .rp-analytics-workspace table').forEach(function(table){
      table.classList.add('ff-table','ff-table-cards');
      var headers=Array.from(table.querySelectorAll('thead th')).map(function(th){return (th.textContent||'').trim();});
      if(headers.length>5) table.classList.add('ff-table-wide');
      table.querySelectorAll('tbody tr').forEach(function(row){
        Array.from(row.children).forEach(function(cell,index){
          if(cell.tagName==='TD'&&!cell.hasAttribute('colspan')&&!cell.dataset.label) cell.dataset.label=headers[index]||('Campo '+String(index+1));
        });
      });
    });
  }

  function improveCharts(scope){
    var root=scope&&scope.querySelectorAll?scope:document;
    root.querySelectorAll('.ffad-chart,.rp-chart-stage,.rp-chart').forEach(function(chart){
      chart.classList.add('ff-chart-premium');
      var svg=chart.querySelector('svg');
      if(svg&&!svg.getAttribute('role')) svg.setAttribute('role','img');
    });
  }

  function improveEvidence(scope){
    var root=scope&&scope.querySelectorAll?scope:document;
    root.querySelectorAll('.ffad-evidence-thumb,.rp-evidence-thumb').forEach(function(thumb){
      thumb.classList.add('ff-evidence-premium');
      var caption=thumb.querySelector('span');
      if(caption&&!caption.getAttribute('title')) caption.setAttribute('title',(caption.textContent||'').trim());
    });
  }

  function dashboardMetadata(scope){
    var root=scope&&scope.querySelectorAll?scope:document;
    root.querySelectorAll('.ffad-custom-dashboard,.rp-analytics-dashboard').forEach(function(dashboard){
      if(dashboard.dataset.ffPremiumDashboard==='1') return;
      dashboard.dataset.ffPremiumDashboard='1';
      var widgets=dashboard.querySelectorAll('.ffad-metric,.rp-analytics-visual-card,.rp-analytics-table-card').length;
      dashboard.style.setProperty('--ff-dashboard-widget-count',String(widgets));
    });
  }

  function enhance(scope){
    classifyWidgets(scope);
    markSeriesRanks(scope);
    normalizeStates(scope);
    improveTables(scope);
    improveCharts(scope);
    improveEvidence(scope);
    dashboardMetadata(scope);
  }

  function eventScope(event){
    var target=event&&event.target&&event.target!==document?event.target:null;
    if(target&&target.querySelectorAll) return target;
    var portal=String(event&&event.detail&&event.detail.portal||'');
    if(portal==='client') return document.querySelector('#routespro-client-portal[data-fieldflow-portal="client"]');
    if(portal==='operative') return document.querySelector('#routespro-app[data-fieldflow-portal="operative"]');
    return null;
  }

  function boot(){
    enhance(document);
    document.addEventListener('fieldflow:lazy-panel-loaded',function(event){var scope=eventScope(event);if(scope)enhance(scope);});
    document.addEventListener('routespro:panel-open',function(event){var scope=eventScope(event);if(scope)enhance(scope);});
    document.addEventListener('fieldflow:analytics-rendered',function(event){var scope=eventScope(event);if(scope)enhance(scope);});
    document.documentElement.dataset.ffCrossPortalAnalytics=CROSS_PORTAL_ISOLATION_CONTRACT;
  }

  window.FieldFlowPremiumAnalytics={enhance:enhance};
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true}); else boot();
})();
