(function(){
    'use strict';
    var cfg=window.FieldFlowAnalytics2Config||{};
    var root=document.querySelector('[data-ff-analytics2]');
    if(!root||!cfg.statsEndpoint||!cfg.insightsEndpoint)return;

    var form=root.querySelector('[data-ff-a2-filter]');
    var state=root.querySelector('[data-a2-state]');
    var stateStrong=root.querySelector('[data-a2-state-title]');
    var stateSmall=root.querySelector('[data-a2-state-detail]');
    var nf=new Intl.NumberFormat('pt-PT',{maximumFractionDigits:1});
    var loadGeneration=0,lastState=null,lastData=null;
    function emit(name,detail){try{window.dispatchEvent(new CustomEvent(name,{detail:detail||{}}));}catch(_e){}}

    function esc(value){
        var div=document.createElement('div');
        div.textContent=String(value==null?'':value);
        return div.innerHTML;
    }
    function escAttr(value){
        return esc(value).replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }
    function clamp(value){value=Number(value);if(!isFinite(value))return 0;return Math.max(0,Math.min(100,value));}
    function pct(value){return nf.format(Number(value||0))+'%';}
    function scoreText(value){return value==null||value===''?'n.d.':nf.format(Number(value));}
    function setState(kind,title,detail){
        if(!state)return;
        state.classList.toggle('is-loading',kind==='loading');
        state.classList.toggle('is-error',kind==='error');
        if(stateStrong)stateStrong.textContent=title||'';
        if(stateSmall)stateSmall.textContent=detail||'';
        lastState={kind:kind||'',title:title||'',detail:detail||''};
        emit('fieldflow:analytics2-state',lastState);
    }
    function currentParams(){
        var p={};
        if(!form){p.analytics_audience='manager';return p;}
        var fd=new FormData(form);
        fd.forEach(function(value,key){
            if(['page','tab'].indexOf(key)!==-1)return;
            value=String(value||'').trim();
            if(value===''||value==='0')return;
            p[key]=value;
        });
        p.analytics_audience='manager';
        return p;
    }
    function queryString(params){
        var qs=new URLSearchParams();
        Object.keys(params).forEach(function(k){if(params[k]!==''&&params[k]!=null)qs.set(k,params[k]);});
        return qs.toString();
    }
    async function fetchJson(url,options){
        var res=await fetch(url,options||{});
        var data;
        try{data=await res.json();}catch(e){data=null;}
        if(!res.ok||!data){
            var msg=(data&&data.message)||('HTTP '+res.status);
            throw new Error(msg);
        }
        return data;
    }
    function setScore(key,score){
        var card=root.querySelector('[data-a2-score="'+key+'"]');
        if(!card)return;
        var value=card.querySelector('[data-a2-score-value]'),label=card.querySelector('[data-a2-score-label]'),detail=card.querySelector('[data-a2-score-detail]');
        if(!score||score.score==null){
            card.classList.add('is-empty');
            if(value)value.textContent='n.d.';
            if(label)label.textContent=(score&&score.label)||'Sem dados';
            if(detail)detail.textContent=(score&&score.explanation)||'Sem dados suficientes para calcular.';
            return;
        }
        card.classList.remove('is-empty');
        if(score.direction)card.dataset.direction=score.direction;
        if(value)value.textContent=scoreText(score.score);
        if(label)label.textContent=score.label||'';
        if(detail)detail.textContent=score.explanation||'';
    }
    function renderScores(insights){
        var scores=insights.decision_scores||{};
        setScore('health',scores.operational_health||{score:insights.executive&&insights.executive.score,label:insights.executive&&insights.executive.label,direction:'higher_better',explanation:'Índice operacional do motor FieldFlow.'});
        setScore('execution',scores.execution);
        setScore('risk',scores.risk);
        setScore('opportunity',scores.opportunity);
        setScore('stock',scores.stock_risk);
        setScore('confidence',scores.confidence||{score:insights.confidence_detail&&insights.confidence_detail.score,label:insights.confidence,explanation:insights.confidence_detail&&insights.confidence_detail.explanation});
    }
    function renderTrend(stats,insights){
        var prev=stats.previous_summary||{};
        var current=Number(stats.done_rate||0),previous=Number(prev.done_rate||0),delta=current-previous;
        var comparison=insights.comparison||{};
        var available=comparison.available!==false;
        var trend=root.querySelector('[data-a2-trend]');
        if(!trend)return;
        if(!available){
            trend.innerHTML=''
                +'<div class="ff-a2-trend-card"><span>Execução da rota</span><strong>'+esc(pct(current))+'</strong><small>'+esc((stats.from||'')+' a '+(stats.to||''))+'</small><div class="ff-a2-bar"><i style="width:'+clamp(current)+'%"></i></div></div>'
                +'<div class="ff-a2-trend-card"><span>Comparação temporal</span><strong>n.d.</strong><small>Rota individual, sem equivalência direta por ID no período anterior.</small></div>'
                +'<div class="ff-a2-trend-card"><span>Próximo nível</span><strong>Projeto</strong><small>Remove o filtro de rota para comparar janelas temporais equivalentes.</small></div>';
        }else{
            trend.innerHTML=''
                +'<div class="ff-a2-trend-card"><span>Execução atual</span><strong>'+esc(pct(current))+'</strong><small>'+esc((stats.from||'')+' a '+(stats.to||''))+'</small><div class="ff-a2-bar"><i style="width:'+clamp(current)+'%"></i></div></div>'
                +'<div class="ff-a2-trend-card"><span>Período anterior</span><strong>'+esc(pct(previous))+'</strong><small>'+esc(((stats.previous_period||{}).from||'')+' a '+((stats.previous_period||{}).to||''))+'</small><div class="ff-a2-bar"><i style="width:'+clamp(previous)+'%"></i></div></div>'
                +'<div class="ff-a2-trend-card"><span>Variação</span><strong>'+(delta>0?'+':'')+esc(nf.format(delta))+' pp</strong><small>'+esc((insights.decision_scores&&insights.decision_scores.trend&&insights.decision_scores.trend.label)||'Comparação temporal')+'</small><div class="ff-a2-bar"><i style="width:'+clamp(50+delta*3)+'%"></i></div></div>';
        }
        var meta=root.querySelector('[data-a2-period-meta]');
        if(meta)meta.textContent=(stats.total_stops||0)+' visitas planeadas, '+(stats.done_stops||0)+' concluídas';
    }
    function renderStorePending(){
        var body=root.querySelector('[data-a2-store-body]');
        if(body)body.innerHTML='<tr><td colspan="9"><div class="ff-a2-empty">A calcular Store Intelligence em segundo plano...</div></td></tr>';
        var summary=root.querySelector('[data-a2-store-summary]');
        if(summary)summary.innerHTML='<span class="ff-a2-store-chip">Base Analytics já disponível</span><span class="ff-a2-store-chip">PDV Intelligence isolado</span>';
        var det=root.querySelector('[data-a2-deteriorating]');if(det)det.innerHTML='<div class="ff-a2-empty">A calcular...</div>';
        var rec=root.querySelector('[data-a2-recovered]');if(rec)rec.innerHTML='<div class="ff-a2-empty">A calcular...</div>';
        var ctx=root.querySelector('[data-a2-contextual]');if(ctx)ctx.innerHTML='<div class="ff-a2-empty">A cruzar loja, produto e tipo de problema...</div>';
    }
    function renderStoreError(message){
        var text=message||'Store Intelligence temporariamente indisponível.';
        var body=root.querySelector('[data-a2-store-body]');
        if(body)body.innerHTML='<tr><td colspan="9"><div class="ff-a2-error">'+esc(text)+'</div></td></tr>';
        var summary=root.querySelector('[data-a2-store-summary]');
        if(summary)summary.innerHTML='<span class="ff-a2-store-chip">Analytics base operacional</span><span class="ff-a2-store-chip">Store Intelligence isolado</span>';
        var det=root.querySelector('[data-a2-deteriorating]');if(det)det.innerHTML='<div class="ff-a2-error">'+esc(text)+'</div>';
        var rec=root.querySelector('[data-a2-recovered]');if(rec)rec.innerHTML='<div class="ff-a2-error">'+esc(text)+'</div>';
        var ctx=root.querySelector('[data-a2-contextual]');if(ctx)ctx.innerHTML='<div class="ff-a2-error">'+esc(text)+'</div>';
    }
    function renderStoreIntelligence(insights){
        var engine=insights&&insights.store_intelligence?insights.store_intelligence:{};
        var rankings=engine&&engine.rankings?engine.rankings:{};
        var rows=Array.isArray(rankings.priority)?rankings.priority:(Array.isArray(insights.priority_locations)?insights.priority_locations:[]);
        var body=root.querySelector('[data-a2-store-body]');
        if(body){
            if(!rows.length){body.innerHTML='<tr><td colspan="9"><div class="ff-a2-empty">Sem lojas com perfil calculável neste âmbito.</div></td></tr>';}
            else body.innerHTML=rows.slice(0,12).map(function(r){
                var trend=r.trend_delta_pp==null?'n.d.':((Number(r.trend_delta_pp)>0?'+':'')+nf.format(Number(r.trend_delta_pp))+' pp');
                return '<tr><td><strong>'+esc(r.location_name||('PDV #'+(r.location_id||'')))+'</strong><br><small>'+esc(r.city||'')+'</small></td>'
                    +'<td><strong class="ff-a2-store-score">'+esc(scoreText(r.operational_health))+'</strong></td>'
                    +'<td>'+esc(pct(r.execution_score!=null?r.execution_score:r.done_rate||0))+'</td>'
                    +'<td><strong>'+esc(scoreText(r.risk_score))+'</strong></td>'
                    +'<td><strong>'+esc(scoreText(r.opportunity_score))+'</strong></td>'
                    +'<td>'+esc(r.stock_risk==null?'n.d.':scoreText(r.stock_risk))+'</td>'
                    +'<td><span class="ff-a2-store-trend '+esc(r.trend||'stable')+'">'+esc(trend)+'</span></td>'
                    +'<td><span class="ff-a2-priority '+esc(r.priority||'monitor')+'">'+esc(scoreText(r.adaptive_rank_score!=null?r.adaptive_rank_score:r.priority_score))+' · '+esc(r.priority||'monitor')+'</span><br><small>base '+esc(scoreText(r.priority_score))+' · conf. '+esc(scoreText(r.adaptive_confidence_score!=null?r.adaptive_confidence_score:r.confidence_score))+'</small>'+(r.forecast_risk_score==null?'':'<br><small>risco futuro '+esc(scoreText(r.forecast_risk_score))+' · prioridade futura '+esc(scoreText(r.forecast_priority_score))+'</small>')+'</td>'
                    +'<td>'+(r.next_best_action?'<strong>'+esc(r.next_best_action.issue_label||'Next Best Action')+'</strong><br><small>'+esc((r.next_best_action.product?String(r.next_best_action.product)+' · ':'')+(r.next_best_action.action||''))+'</small><br>':'')+'<button type="button" class="ff-a2-drill" data-a2-location="'+esc(r.location_id||0)+'">Abrir loja</button></td></tr>';
            }).join('');
        }
        var sum=engine&&engine.summary?engine.summary:{};var summary=root.querySelector('[data-a2-store-summary]');
        if(summary)summary.innerHTML=['Lojas '+(sum.stores||0),'Prioridade alta '+(sum.high_priority||0),'Risco alto '+(sum.high_risk||0),'Risco futuro alto '+(sum.forecast_high_risk||0),'Adaptive '+(sum.adaptive_coverage||0)+'%','Predictive conf. '+(sum.forecast_confidence_avg||sum.confidence_avg||0)+'/100'].map(function(x){return '<span class="ff-a2-store-chip">'+esc(x)+'</span>';}).join('');
        function renderList(sel,list,kind){var box=root.querySelector(sel);if(!box)return;list=Array.isArray(list)?list:[];if(!list.length){box.innerHTML='<div class="ff-a2-empty">Sem lojas nesta classificação.</div>';return;}box.innerHTML=list.slice(0,8).map(function(r){var delta=r.trend_delta_pp==null?'n.d.':((Number(r.trend_delta_pp)>0?'+':'')+nf.format(Number(r.trend_delta_pp))+' pp');return '<button type="button" class="ff-a2-store-list-item" data-a2-location="'+esc(r.location_id||0)+'"><div><strong>'+esc(r.location_name||'PDV')+'</strong><small>'+esc(r.reason||'')+'</small></div><b class="'+esc(kind)+'">'+esc(delta)+'</b></button>';}).join('');}
        renderList('[data-a2-deteriorating]',rankings.deteriorating||[],'down');
        renderList('[data-a2-recovered]',rankings.recovered||[],'up');
        renderContextual(engine);
    }
    function renderContextual(engine){
        var box=root.querySelector('[data-a2-contextual]');if(!box)return;
        engine=engine||{};var ctx=engine.contextual_predictive||{},rankings=engine.rankings||{};
        var actions=Array.isArray(ctx.next_best_actions)?ctx.next_best_actions:(Array.isArray(rankings.next_best_actions)?rankings.next_best_actions:[]);
        if(!actions.length){box.innerHTML='<div class="ff-a2-empty">Sem Next Best Actions contextuais neste âmbito. O FieldFlow não inventa produto ou problema quando não existe evidência suficiente.</div>';return;}
        var sum=ctx.summary||{};
        var head='<div class="ff-a2-ai-meta"><span>Ações '+esc(sum.actions||actions.length)+'</span><span>Produtos '+esc(sum.products||0)+'</span><span>Loja + produto + problema</span><span>Sem automação autónoma</span></div>';
        var rows=actions.slice(0,8).map(function(a){
            var ev=Array.isArray(a.evidence)?a.evidence.slice(0,3).map(function(x){return (x.kind==='projected'?'Projetado: ':'Observado: ')+String(x.label||'')+' '+String(x.value||'');}).join(' · '):'';
            var label=(a.location_name||'PDV')+(a.product?' · '+a.product:'');
            return '<button type="button" class="ff-a2-store-list-item" data-a2-location="'+esc(a.location_id||0)+'"><div><strong>'+esc(label)+'</strong><small>'+esc(a.issue_label||'Ação recomendada')+' · '+esc(a.action||'')+'</small><small>'+esc(ev||a.reason||'')+'</small></div><b class="'+esc(a.urgency==='critical'||a.urgency==='high'?'down':'')+'">'+esc(scoreText(a.rank_score))+'/100</b></button>';
        }).join('');
        box.innerHTML=head+'<div class="ff-a2-store-list">'+rows+'</div>';
    }
    function renderRoutes(stats){
        var box=root.querySelector('[data-a2-routes]');
        if(!box)return;
        var rows=Array.isArray(stats.by_day)?stats.by_day.slice():[];
        rows.sort(function(a,b){var ar=Number(a.done_rate||0),br=Number(b.done_rate||0);if(ar!==br)return ar-br;return String(b.date||'').localeCompare(String(a.date||''));});
        if(!rows.length){box.innerHTML='<div class="ff-a2-empty">Sem rotas no período selecionado.</div>';return;}
        box.innerHTML=rows.slice(0,8).map(function(r){
            return '<div class="ff-a2-route"><div><strong>#'+esc(r.route_id||'')+' · '+esc(r.project_name||'Campanha')+'</strong><small>'+esc(r.date||'')+' · '+esc(r.user_name||'Sem owner')+' · '+esc(r.stops_done||0)+'/'+esc(r.stops||0)+' visitas</small></div><b>'+esc(pct(r.done_rate||0))+'</b><button type="button" class="ff-a2-drill" data-a2-route="'+esc(r.route_id||0)+'">Drill-down</button></div>';
        }).join('');
    }
    function renderAdaptive(insights){
        var box=root.querySelector('[data-a2-adaptive]');
        if(!box)return;
        var adaptive=insights&&insights.adaptive_intelligence?insights.adaptive_intelligence:{};
        if(!adaptive.available){box.innerHTML='<div class="ff-a2-empty">Ainda não existe histórico suficiente neste cliente/projeto para adaptar prioridades.</div>';return;}
        var summary=adaptive.summary||{},actions=Array.isArray(adaptive.next_best_actions)?adaptive.next_best_actions:[];
        var head='<div class="ff-a2-ai-meta"><span>Padrões '+esc(summary.patterns||0)+'</span><span>Eficácia '+esc(scoreText(summary.effectiveness_avg||50))+'%</span><span>Relevância '+esc(scoreText(summary.human_relevance_avg||50))+'%</span><span>Sem mutações automáticas</span></div>';
        var rows=actions.slice(0,5).map(function(a){
            return '<div class="ff-a2-list-item"><i></i><div><strong>'+esc(a.title||'Recomendação')+'</strong><small>'+esc(a.action||'')+'</small><small>'+esc(a.explanation||'')+'</small></div><b>'+esc(scoreText(a.rank_score))+'/100</b></div>';
        }).join('');
        box.innerHTML=head+'<div class="ff-a2-list">'+(rows||'<div class="ff-a2-empty">Sem Next Best Actions neste âmbito.</div>')+'</div>';
    }

    function renderPredictive(insights){
        var box=root.querySelector('[data-a2-predictive]');
        if(!box)return;
        var pred=insights&&insights.predictive_intelligence?insights.predictive_intelligence:{};
        if(!pred.available){box.innerHTML='<div class="ff-a2-empty">Sem dados suficientes para uma projeção futura neste âmbito.</div>';return;}
        var sum=pred.summary||{},forecasts=pred.forecasts||{},h=pred.horizon||{},signals=Array.isArray(pred.leading_signals)?pred.leading_signals:[];
        function card(key,label){var f=forecasts[key]||{},unit=String(f.unit||'');var current=f.current==null?'n.d.':scoreText(f.current)+(unit==='%'?'%':unit);var future=f.forecast==null?'n.d.':scoreText(f.forecast)+(unit==='%'?'%':unit);var delta=f.delta==null?'':((Number(f.delta)>0?'+':'')+scoreText(f.delta)+(key==='execution'?' pp':''));return '<article class="ff-a2-predict-card '+esc(f.direction||'stable')+'"><span>'+esc(label||f.label||key)+'</span><strong>'+esc(future)+'</strong><small>Atual '+esc(current)+(delta?' · Δ '+esc(delta):'')+'</small><p>'+esc(f.explanation||'')+'</p></article>';}
        var cards='<div class="ff-a2-predict-grid">'+card('execution','Execução projetada')+card('risk','Risco projetado')+card('opportunity','Oportunidade projetada')+'<article class="ff-a2-predict-card confidence"><span>Confiança preditiva</span><strong>'+esc(scoreText(sum.confidence||0))+'/100</strong><small>'+esc(h.days||0)+' dia(s) · '+esc(h.label||'próxima janela')+'</small><p>Confiança na projeção, não probabilidade do evento.</p></article></div>';
        var signalHtml=signals.slice(0,5).map(function(sig){return '<div class="ff-a2-list-item '+(sig.direction==='negative'?'is-risk':'')+'"><i></i><div><strong>'+esc(sig.label||'Sinal')+'</strong><small>'+esc(sig.detail||'')+'</small></div><b>'+esc(sig.value||'')+'</b></div>';}).join('');
        var stateLabel=pred.state==='deteriorating'?'Tendência de deterioração':(pred.state==='improving'?'Tendência de melhoria':'Tendência estável');
        box.innerHTML='<div class="ff-a2-ai-meta"><span>'+esc(stateLabel)+'</span><span>'+esc(pred.mode||'projection')+'</span><span>Não probabilístico</span><span>Human review</span></div>'+cards+'<div class="ff-a2-list">'+(signalHtml||'<div class="ff-a2-empty">Sem sinais adicionais relevantes.</div>')+'</div>';
    }

    function renderAI(insights){
        var box=root.querySelector('[data-a2-ai]');
        if(!box)return;
        var highlights=Array.isArray(insights.highlights)?insights.highlights:[];
        var risks=Array.isArray(insights.risks)?insights.risks:[];
        var items=[];
        highlights.slice(0,2).forEach(function(x){items.push('<div class="ff-a2-list-item"><i></i><div><strong>'+esc(x.title||'Destaque')+'</strong><small>'+esc(x.detail||'')+'</small></div><b>'+esc(x.impact||'')+'</b></div>');});
        risks.slice(0,2).forEach(function(x){items.push('<div class="ff-a2-list-item is-risk"><i></i><div><strong>'+esc(x.title||'Risco')+'</strong><small>'+esc(x.detail||'')+'</small></div><b>'+esc(x.severity||'')+'</b></div>');});
        box.innerHTML='<div class="ff-a2-ai"><strong>'+esc(insights.headline||'Leitura operacional')+'</strong><p>'+esc(insights.summary||'Sem resumo disponível.')+'</p><div class="ff-a2-ai-meta"><span>'+esc(insights.provider||'Motor FieldFlow')+'</span><span>Confiança '+esc((insights.confidence_detail&&insights.confidence_detail.score!=null)?scoreText(insights.confidence_detail.score)+'/100':(insights.confidence||'n.d.'))+'</span><span>'+esc(insights.generated_at||'')+'</span></div></div><div class="ff-a2-list">'+(items.join('')||'<div class="ff-a2-empty">Sem destaques ou riscos adicionais.</div>')+'</div>';
    }
    var askBox=root.querySelector('[data-a2-ask]');
    var askThread=root.querySelector('[data-a2-ask-thread]');
    var askForm=root.querySelector('[data-a2-ask-form]');
    var askInput=root.querySelector('[data-a2-ask-input]');
    var askSubmit=root.querySelector('[data-a2-ask-submit]');
    var askHistory=[];
    function askScroll(){if(askThread)askThread.scrollTop=askThread.scrollHeight;}
    function renderAskUser(question){
        if(!askThread)return;
        if(askThread.querySelector('.ff-a2-ask-empty'))askThread.innerHTML='';
    }
    function renderAskLoading(){
        if(!askThread)return null;
        var id='ff-a2-ask-loading-'+Date.now();
        askThread.insertAdjacentHTML('beforeend','<article class="ff-a2-ask-message" id="'+id+'"><div class="ff-a2-ask-loading">A cruzar o contexto FieldFlow...</div></article>');
        askScroll();return document.getElementById(id);
    }
    function renderAskAnswer(data,placeholder){
        if(!askThread)return;
        if(placeholder&&placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);
        var points=Array.isArray(data&&data.key_points)?data.key_points:[];
        var evidence=Array.isArray(data&&data.evidence)?data.evidence:[];
        var followups=Array.isArray(data&&data.followups)?data.followups:[];
        var pointsHtml=points.length?'<ul class="ff-a2-ask-points">'+points.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>':'';
        var evidenceHtml=evidence.length?'<div class="ff-a2-ask-evidence">'+evidence.slice(0,8).map(function(x){var kind=String(x.kind||'observed');return '<span class="is-'+esc(kind)+'" title="'+escAttr(x.source||'FieldFlow')+'">'+esc(x.label||'Fonte')+': '+esc(x.value||'')+'</span>';}).join('')+'</div>':'';
        var followHtml=followups.length?'<div class="ff-a2-ask-followups">'+followups.slice(0,4).map(function(x){return '<button type="button" data-a2-ask-question="'+escAttr(x)+'">'+esc(x)+'</button>';}).join('')+'</div>':'';
        var meta='<div class="ff-a2-ask-meta"><span>'+esc(data&&data.provider||'Motor FieldFlow')+'</span><span>Confiança '+esc(data&&data.confidence_score!=null?scoreText(data.confidence_score)+'/100':(data&&data.confidence||'n.d.'))+'</span><span>'+(data&&data.source==='ai'?'IA fundamentada':'Resposta determinística')+'</span><span>Read-only</span></div>';
        askThread.insertAdjacentHTML('beforeend','<article class="ff-a2-ask-message"><strong>FieldFlow</strong><p>'+esc(data&&data.answer||'Não foi possível produzir uma resposta segura.')+'</p>'+pointsHtml+evidenceHtml+meta+followHtml+'</article>');
        askScroll();
    }
    function renderAskError(message,placeholder){
        if(!askThread)return;
        if(placeholder&&placeholder.parentNode)placeholder.parentNode.removeChild(placeholder);
        askThread.insertAdjacentHTML('beforeend','<article class="ff-a2-ask-message"><strong>FieldFlow</strong><p>'+esc(message||'Não foi possível responder neste momento.')+'</p><div class="ff-a2-ask-meta"><span>Analytics base continua operacional</span></div></article>');
        askScroll();
    }
    async function askFieldFlow(question){
        question=String(question||'').trim();
        if(!question||!cfg.askEndpoint||!askThread)return;
        renderAskUser(question);
        var placeholder=renderAskLoading();
        if(askInput)askInput.value='';
        if(askSubmit){askSubmit.disabled=true;askSubmit.textContent='A responder...';}
        try{
            var payload=Object.assign({},currentParams(),{audience:'manager',question:question,history:askHistory.slice(-6)});
            var data=await fetchJson(cfg.askEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce||''},body:JSON.stringify(payload)});
            renderAskAnswer(data,placeholder);
            askHistory.push({role:'user',content:question},{role:'assistant',content:String(data.answer||'')});
            if(askHistory.length>12)askHistory=askHistory.slice(-12);
        }catch(err){renderAskError(err.message||'Não foi possível responder neste momento.',placeholder);}
        finally{if(askSubmit){askSubmit.disabled=false;askSubmit.textContent='Perguntar';}}
    }
    if(askForm){askForm.addEventListener('submit',function(ev){ev.preventDefault();askFieldFlow(askInput?askInput.value:'');});}
    if(askBox){
        askBox.addEventListener('click',function(ev){
            var q=ev.target.closest('[data-a2-ask-question]');if(q){ev.preventDefault();var text=String(q.dataset.a2AskQuestion||'');if(askInput)askInput.value=text;askFieldFlow(text);return;}
            var clear=ev.target.closest('[data-a2-ask-clear]');if(clear){ev.preventDefault();askHistory=[];if(askInput)askInput.value='';if(askThread)askThread.innerHTML='<div class="ff-a2-ask-empty"><strong>Pergunta pelo que interessa.</strong><span>A conversa usa os filtros desta página e não fica gravada como dado operacional.</span></div>';}
        });
    }
    if(askInput){askInput.addEventListener('keydown',function(ev){if((ev.ctrlKey||ev.metaKey)&&ev.key==='Enter'){ev.preventDefault();askFieldFlow(askInput.value);}});}

    async function load(){
        var generation=++loadGeneration;
        var params=currentParams();
        setState('loading',cfg.labels&&cfg.labels.loading?cfg.labels.loading:'A calcular Analytics 2.0...','A cruzar execução, comparação temporal, lojas, stocks e métricas configuradas.');
        try{
            var stats=await fetchJson(cfg.statsEndpoint+'?'+queryString(params),{credentials:'same-origin',headers:{'X-WP-Nonce':cfg.nonce||''}});
            var payload=Object.assign({},params,{audience:'manager'});
            var insights=await fetchJson(cfg.insightsEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce||''},body:JSON.stringify(payload)});
            if(generation!==loadGeneration)return;
            renderScores(insights);
            renderTrend(stats,insights);
            renderRoutes(stats);
            renderAI(insights);
            renderAdaptive(insights);
            renderPredictive(insights);
            renderStorePending();
            var source=(insights.source==='ai'?'IA + motor determinístico':'Motor determinístico FieldFlow');
            setState('ready','Leitura pronta',source+'. '+(stats.total_routes||0)+' rota(s), '+(stats.total_stops||0)+' visita(s) no âmbito atual.');
            lastData={params:params,stats:stats,insights:insights};
            emit('fieldflow:analytics2-data',lastData);

            // Store Intelligence is a second, isolated request. Failure here must
            // never revert the Command View to HTTP 500 or hide the base scores.
            if(cfg.storeEndpoint){
                try{
                    var storePayload=Object.assign({},params,{audience:'manager'});
                    var store=await fetchJson(cfg.storeEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce||''},body:JSON.stringify(storePayload)});
                    if(generation!==loadGeneration)return;
                    renderStoreIntelligence({store_intelligence:store});
                }catch(storeErr){
                    renderStoreError(storeErr.message||'Store Intelligence temporariamente indisponível.');
                }
            }else{
                renderStoreIntelligence(insights);
            }
        }catch(err){
            if(generation!==loadGeneration)return;
            setState('error','Analytics 2.0 indisponível',err.message||cfg.labels&&cfg.labels.error||'Falha ao carregar.');
            var boxes=root.querySelectorAll('[data-a2-ai],[data-a2-adaptive],[data-a2-predictive],[data-a2-contextual],[data-a2-routes],[data-a2-deteriorating],[data-a2-recovered]');
            boxes.forEach(function(box){box.innerHTML='<div class="ff-a2-error">'+esc(err.message||'Não foi possível carregar a leitura analítica.')+'</div>';});
        }
    }
    function filterContext(){
        if(!form)return;
        var client=form.querySelector('[name="client_id"]'),project=form.querySelector('[name="project_id"]'),route=form.querySelector('[name="route_id"]'),location=form.querySelector('[name="location_id"]');
        var clientId=client?String(client.value||'0'):'0',projectId=project?String(project.value||'0'):'0';
        if(project){Array.from(project.options).forEach(function(o){if(!o.value)return;o.hidden=clientId!=='0'&&String(o.dataset.clientId||'0')!==clientId;});if(project.selectedOptions[0]&&project.selectedOptions[0].hidden)project.value='0';projectId=String(project.value||'0');}
        if(route){Array.from(route.options).forEach(function(o){if(!o.value)return;var c=String(o.dataset.clientId||'0'),p=String(o.dataset.projectId||'0');o.hidden=(clientId!=='0'&&c!==clientId)||(projectId!=='0'&&p!==projectId);});if(route.selectedOptions[0]&&route.selectedOptions[0].hidden)route.value='0';}
        if(location){Array.from(location.options).forEach(function(o){if(!o.value)return;var c=String(o.dataset.clientId||'0'),p=String(o.dataset.projectId||'0');o.hidden=(clientId!=='0'&&c!=='0'&&c!==clientId)||(projectId!=='0'&&p!=='0'&&p!==projectId);});if(location.selectedOptions[0]&&location.selectedOptions[0].hidden)location.value='0';}
    }
    function syncUrl(params){
        try{
            var url=new URL(window.location.href);
            ['from','to','client_id','project_id','route_id','user_id','location_id'].forEach(function(key){url.searchParams.delete(key);});
            url.searchParams.set('page','routespro-assignments-hub');url.searchParams.set('tab','analytics');
            Object.keys(params||{}).forEach(function(key){if(key==='analytics_audience')return;var value=String(params[key]||'');if(value&&value!=='0')url.searchParams.set(key,value);});
            url.hash='ff-analytics2-workspace';window.history.replaceState({},'',url.toString());
        }catch(_e){}
    }
    function applyContext(next){
        if(!form)return;
        next=next||{};
        ['from','to','client_id','project_id','route_id','user_id','location_id'].forEach(function(key){var field=form.querySelector('[name="'+key+'"]');if(field&&Object.prototype.hasOwnProperty.call(next,key))field.value=String(next[key]==null?'':next[key]);});
        filterContext();
        var params=currentParams();syncUrl(params);load();
    }
    function setDateRange(days){
        var to=form.querySelector('[name="to"]'),from=form.querySelector('[name="from"]');if(!to||!from)return;
        var end=to.value?new Date(to.value+'T12:00:00'):new Date();
        if(isNaN(end.getTime()))end=new Date();
        var start=new Date(end.getTime());start.setDate(start.getDate()-(Math.max(1,days)-1));
        function iso(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');}
        to.value=iso(end);from.value=iso(start);applyContext({from:from.value,to:to.value});
    }
    root.addEventListener('click',function(ev){
        var range=ev.target.closest('[data-a2-range]');if(range){ev.preventDefault();setDateRange(parseInt(range.dataset.a2Range||'30',10));return;}
        var loc=ev.target.closest('[data-a2-location]');if(loc){var field=form.querySelector('[name="location_id"]');if(field){field.value=String(loc.dataset.a2Location||'0');applyContext({location_id:field.value});}return;}
        var route=ev.target.closest('[data-a2-route]');if(route){var rf=form.querySelector('[name="route_id"]');if(rf){rf.value=String(route.dataset.a2Route||'0');applyContext({route_id:rf.value});}return;}
    });
    if(form){
        form.addEventListener('submit',function(ev){ev.preventDefault();applyContext({});});
        var client=form.querySelector('[name="client_id"]'),project=form.querySelector('[name="project_id"]');
        if(client)client.addEventListener('change',filterContext);
        if(project)project.addEventListener('change',filterContext);
        filterContext();
    }
    window.FieldFlowAnalytics2={version:String(cfg.version||''),currentParams:currentParams,applyContext:applyContext,reload:load,snapshot:function(){return {state:lastState||undefined,data:lastData||undefined};}};
    load();
})();
