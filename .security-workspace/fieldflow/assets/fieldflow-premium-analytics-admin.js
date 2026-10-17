(function(){
  'use strict';

  function esc(value){
    return String(value==null?'':value).replace(/[&<>"']/g,function(char){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];});
  }

  function previewVisual(type){
    if(type==='kpi') return '<div class="ff-analytics-widget-preview-kpi">92%</div>';
    if(type==='table') return '<div class="ff-analytics-widget-preview-table"><i></i><i></i><i></i><i></i></div>';
    if(type==='image_gallery') return '<div class="ff-analytics-widget-preview-images"><i></i><i></i><i></i></div>';
    if(type==='line') return '<div class="ff-analytics-widget-preview-line"><svg viewBox="0 0 180 60" aria-hidden="true"><polyline points="4,49 38,35 71,41 110,18 145,25 176,8" fill="none" stroke="var(--ff-primary,#2563eb)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>';
    return '<div class="ff-analytics-widget-preview-bars"><i style="height:36%"></i><i style="height:58%"></i><i style="height:44%"></i><i style="height:82%"></i><i style="height:67%"></i></div>';
  }

  function installWidgetPreview(){
    var grid=document.querySelector('.ff-form-grid[data-ff-context="analytics-form"]');
    if(!grid||grid.querySelector('.ff-analytics-widget-preview')) return;
    var form=grid.closest('form');
    if(!form) return;
    var preview=document.createElement('section');
    preview.className='ff-analytics-widget-preview';
    preview.setAttribute('aria-live','polite');
    grid.appendChild(preview);

    function value(name,fallback){
      var field=form.querySelector('[name="'+name+'"]');
      if(!field) return fallback||'';
      if(field.type==='checkbox') return field.checked?'1':'0';
      return field.value||fallback||'';
    }
    function labelFor(selectName){
      var select=form.querySelector('[name="'+selectName+'"]');
      return select&&select.selectedOptions&&select.selectedOptions[0]?(select.selectedOptions[0].textContent||'').trim():'';
    }
    function render(){
      var type=value('chart_type','line');
      var title=value('metric_label','Pré-visualização do widget');
      var question=form.querySelector('[data-ff-analytics-question]');
      var questionLabel=question&&question.selectedOptions&&question.selectedOptions[0]?(question.selectedOptions[0].textContent||'').trim():'';
      var aggregation=labelFor('aggregation');
      var dimension=labelFor('dimension');
      var subtitle=[questionLabel&&questionLabel!=='Seleciona um formulário primeiro'?questionLabel:'',aggregation,dimension].filter(Boolean).join(' · ');
      var typeLabels={kpi:'KPI',bar:'Barras',line:'Linha',pie:'Circular',table:'Tabela',image_gallery:'Galeria de evidências'};
      preview.innerHTML='<div class="ff-analytics-widget-preview-copy"><span>Pré-visualização no front</span><h4>'+esc(title)+'</h4><p>'+esc(subtitle||'A aparência final utiliza as cores e tipografia do template selecionado.')+'</p><span class="ff-analytics-preview-badge">'+esc(typeLabels[type]||type)+'</span></div><div class="ff-analytics-widget-preview-visual">'+previewVisual(type)+'</div>';
    }
    form.addEventListener('input',render);
    form.addEventListener('change',render);
    render();
  }

  function classifyRows(){
    document.querySelectorAll('.ff-analytics-admin table.widefat tbody tr').forEach(function(row){
      var badge=row.querySelector('.ff-widget-type');
      if(!badge) return;
      var text=(badge.textContent||'').toLowerCase();
      if(text.indexOf('evid')!==-1||text.indexOf('image')!==-1) row.dataset.ffWidgetType='evidence';
      else if(text.indexOf('frente')!==-1) row.dataset.ffWidgetType='fronts';
      else if(text.indexOf('encomenda')!==-1) row.dataset.ffWidgetType='order';
      else row.dataset.ffWidgetType=text.replace(/\s+/g,'-');
    });
  }

  function boot(){installWidgetPreview();classifyRows();}
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true}); else boot();
})();
