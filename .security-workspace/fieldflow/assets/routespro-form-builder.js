(function ($) {
  'use strict';

  var WIDTH_PRESETS = [25, 33, 50, 66, 75, 100];

  var FB = {
    state: {
      meta: { title: '', subtitle: '' },
      questions: [],
      layout: {
        mode: 'single',
        show_progress: false,
        steps: [],
        field_layout: {}
      }
    },
    els: {
      schema: null,
      list: null
    },
    flags: {
      sortableInit: false,
      stepsSortableInit: false,
      layoutBound: false
    }
  };

  function safeJsonParse(str, fallback) {
    try {
      var o = JSON.parse(str);
      return o && typeof o === 'object' ? o : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function escapeHtml(s) {
    s = (s === null || s === undefined) ? '' : String(s);
    return s
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function slugifyKey(str) {
    if (!str) return '';
    return String(str)
      .toLowerCase()
      .trim()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
      .replace(/_{2,}/g, '_');
  }

  function makeId(prefix) {
    return (prefix || 'id') + '_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
  }

  function existingKeysMap(exceptId) {
    var map = {};
    FB.state.questions.forEach(function (q) {
      if (!q) return;
      if (exceptId && q.id === exceptId) return;
      if (q.key) map[q.key] = true;
    });
    return map;
  }

  function uniqKey(base, exceptId) {
    base = slugifyKey(base || 'pergunta');
    if (!base) base = 'pergunta';

    var key = base;
    var i = 2;
    var existing = existingKeysMap(exceptId);

    while (existing[key]) {
      key = base + '_' + i;
      i++;
    }
    return key;
  }

  function normaliseVisualOptions(q) {
    q = q || {};
    var source = Array.isArray(q.visual_options) ? q.visual_options : [];
    if (!source.length && Array.isArray(q.options)) {
      source = q.options.map(function (item) { return { label: item, value: item }; });
    }
    return source.map(function (item) {
      if (typeof item === 'string') item = { label: item, value: item };
      item = item || {};
      var label = String(item.label || item.value || '').trim();
      var value = String(item.value || label).trim();
      return {
        id: item.id || item._id || makeId('opt'),
        label: label,
        value: value || label,
        image_id: parseInt(item.image_id || item.attachment_id || 0, 10) || 0,
        image_url: String(item.image_url || item.url || '').trim()
      };
    }).filter(function (item) { return !!item.label; });
  }

  function normaliseConditionRules(conditions) {
    conditions = conditions && typeof conditions === 'object' ? conditions : {};
    var source = Array.isArray(conditions.rules) ? conditions.rules : [];
    if (!source.length && conditions.go_to) {
      source = [{ value: conditions.value || '', go_to: conditions.go_to }];
    }
    return source.map(function (rule) {
      rule = rule || {};
      return {
        id: rule.id || rule._id || makeId('rule'),
        value: (rule.value === undefined || rule.value === null) ? '' : String(rule.value),
        go_to: slugifyKey(rule.go_to || '')
      };
    });
  }

  function normaliseQuestion(q) {
    q = q || {};
    var type = q.type || 'text';
    var allowed = [
      'text', 'textarea', 'number', 'currency', 'percent',
      'date', 'time', 'checkbox', 'select', 'radio',
      'visual_choice', 'categorized_image_upload', 'evidence_check', 'image_upload', 'file_upload', 'product_matrix', 'order_suggestion', 'briefing_block'
    ];
    if (allowed.indexOf(type) === -1) type = 'text';

    return {
      id: q.id || q._id || makeId('q'),
      label: q.label || '',
      key: q.key || '',
      type: type,
      required: !!q.required,
      help_text: q.help_text || '',
      show_previous_on_start: type === 'briefing_block' ? false : !!q.show_previous_on_start,
      options: Array.isArray(q.options) ? q.options : [],
      visual_options: normaliseVisualOptions(q),
      product_rows: Array.isArray(q.product_rows) ? q.product_rows.map(function(r){ r=r||{}; return { category: r.category || r.categoria || '', ref: r.ref || r.reference || r.code || '', item_type: r.item_type || r.type || '', name: r.name || r.product || '', capacity_cl: r.capacity_cl || r.capacity || '', units_per_case: r.units_per_case || r.units_case || '' }; }) : [],
      product_source: q.product_source || (q.cardex_id ? 'cardex_fixed' : 'manual'),
      cardex_id: parseInt(q.cardex_id || 0, 10) || 0,
      only_store_assortment: q.only_store_assortment !== false,
      catalog_images_disabled: !!q.catalog_images_disabled,
      camera_reading: !!q.camera_reading,
      order_quantity_decimals: !!q.order_quantity_decimals,
      order_zero_fronts_suggestion: (q.order_zero_fronts_suggestion !== undefined && q.order_zero_fronts_suggestion !== null && q.order_zero_fronts_suggestion !== '') ? Math.max(0, parseFloat(q.order_zero_fronts_suggestion) || 0) : 1,
      briefing_source: q.briefing_source === 'all_active' ? 'all_active' : 'active_latest',
      briefing_show_context: q.briefing_show_context !== false,
      briefing_question_limit: Math.max(1, Math.min(100, parseInt(q.briefing_question_limit || 30, 10) || 30)),
      briefing_only_mapped: true,
      min: (q.min !== undefined && q.min !== null && q.min !== '') ? q.min : '',
      max: (q.max !== undefined && q.max !== null && q.max !== '') ? q.max : '',
      unit: q.unit || '',
      selection_mode: (q.selection_mode === 'multiple' || ((type === 'select' || type === 'radio' || type === 'visual_choice') && q.multiple)) ? 'multiple' : 'single',
      multiple: type === 'categorized_image_upload' ? (q.multiple !== false) : !!q.multiple,
      conditions: {
        enabled: !!(q.conditions && q.conditions.enabled),
        rules: normaliseConditionRules(q.conditions)
      },
      collapsed: !!q.collapsed
    };
  }

  function normaliseLayout(layout) {
    layout = layout && typeof layout === 'object' ? layout : {};
    var mode = layout.mode === 'steps' ? 'steps' : 'single';
    var show_progress = !!layout.show_progress;

    var steps = Array.isArray(layout.steps) ? layout.steps : [];
    steps = steps.map(function (st) {
      st = st && typeof st === 'object' ? st : {};
      return {
        id: st.id || makeId('step'),
        title: st.title || '',
        description: st.description || '',
        fields: Array.isArray(st.fields) ? st.fields.map(slugifyKey).filter(Boolean) : []
      };
    });

    var field_layout = layout.field_layout && typeof layout.field_layout === 'object' ? layout.field_layout : {};
    var outFieldLayout = {};
    Object.keys(field_layout).forEach(function (k) {
      var key = slugifyKey(k);
      if (!key) return;
      var conf = field_layout[k] || {};
      var w = parseInt(conf.width || 100, 10);
      if (WIDTH_PRESETS.indexOf(w) === -1) w = 100;
      outFieldLayout[key] = { width: w };
    });

    return {
      mode: mode,
      show_progress: show_progress,
      steps: steps,
      field_layout: outFieldLayout
    };
  }

  function hydrateStateFromHidden() {
    var raw = FB.els.schema.val() || '';
    var data = safeJsonParse(raw, { meta: { title: '', subtitle: '' }, questions: [], layout: {} });

    if (!data.meta) data.meta = { title: '', subtitle: '' };
    if (!Array.isArray(data.questions)) data.questions = [];
    if (!data.layout) data.layout = {};

    FB.state.meta = {
      title: data.meta.title || '',
      subtitle: data.meta.subtitle || ''
    };

    FB.state.questions = data.questions.map(function (q) {
      return normaliseQuestion(q);
    });

    FB.state.questions.forEach(function (q) {
      q.key = slugifyKey(q.key);
      if (!q.key) q.key = uniqKey(q.label || 'pergunta', q.id);

      var existing = existingKeysMap(q.id);
      if (existing[q.key]) q.key = uniqKey(q.key, q.id);

      if (!q.label) q.label = q.key;
    });

    FB.state.layout = normaliseLayout(data.layout);

    if (FB.state.layout.mode === 'steps' && (!FB.state.layout.steps || !FB.state.layout.steps.length)) {
      FB.state.layout.steps = [{
        id: makeId('step'),
        title: 'Passo 1',
        description: '',
        fields: FB.state.questions.map(function (q) { return q.key; })
      }];
    }
  }

  function syncHidden() {
    if (!FB.els.schema || !FB.els.schema.length) return;

    var keyMap = {};
    FB.state.questions.forEach(function (q) { keyMap[q.key] = true; });

    if (FB.state.layout && Array.isArray(FB.state.layout.steps)) {
      FB.state.layout.steps.forEach(function (st) {
        st.fields = (st.fields || [])
          .map(slugifyKey)
          .filter(Boolean)
          .filter(function (k) { return !!keyMap[k]; });

        var seen = {};
        st.fields = st.fields.filter(function (k) {
          if (seen[k]) return false;
          seen[k] = true;
          return true;
        });
      });

      var keyOrder = FB.state.questions.map(function (q) { return q.key; });
      function keyPos(k) {
        var i = keyOrder.indexOf(k);
        return i === -1 ? 999999 : i;
      }

      // 2.2.473: every BO question in wizard mode belongs to a real step.
      // Older schemas could keep a newly added question in questions[] without
      // adding its key to layout.steps, forcing the front runtime to invent an
      // extra recovery step. Repair that omission using the nearest configured
      // neighbour so the saved layout follows the visible question order.
      if (FB.state.layout.mode === 'steps' && FB.state.layout.steps.length) {
        var assignedStep = {};
        FB.state.layout.steps.forEach(function (st, stepIndex) {
          (st.fields || []).forEach(function (key) { if (key) assignedStep[key] = stepIndex; });
        });
        keyOrder.forEach(function (key, index) {
          if (!key || assignedStep[key] !== undefined) return;
          var target = null;
          for (var p = index - 1; p >= 0; p--) {
            if (assignedStep[keyOrder[p]] !== undefined) { target = assignedStep[keyOrder[p]]; break; }
          }
          if (target === null) {
            for (var n = index + 1; n < keyOrder.length; n++) {
              if (assignedStep[keyOrder[n]] !== undefined) { target = assignedStep[keyOrder[n]]; break; }
            }
          }
          if (target === null) target = 0;
          FB.state.layout.steps[target].fields = FB.state.layout.steps[target].fields || [];
          FB.state.layout.steps[target].fields.push(key);
          assignedStep[key] = target;
        });
      }

      FB.state.layout.steps.forEach(function (st) {
        st.fields = Array.isArray(st.fields) ? st.fields : [];
        st.fields.sort(function (a, b) {
          return keyPos(a) - keyPos(b);
        });
      });
    }

    var fieldLayoutClean = {};
    Object.keys(FB.state.layout.field_layout || {}).forEach(function (k) {
      var key = slugifyKey(k);
      if (!key || !keyMap[key]) return;
      var w = parseInt((FB.state.layout.field_layout[key] || {}).width || 100, 10);
      if (WIDTH_PRESETS.indexOf(w) === -1) w = 100;
      fieldLayoutClean[key] = { width: w };
    });
    FB.state.layout.field_layout = fieldLayoutClean;

    var payload = {
      meta: {
        title: FB.state.meta.title || '',
        subtitle: FB.state.meta.subtitle || ''
      },
      layout: {
        mode: FB.state.layout.mode || 'single',
        show_progress: !!FB.state.layout.show_progress,
        steps: (FB.state.layout.steps || []).map(function (st) {
          return {
            id: st.id,
            title: st.title || '',
            description: st.description || '',
            fields: st.fields || []
          };
        }),
        field_layout: FB.state.layout.field_layout || {}
      },
      questions: FB.state.questions.map(function (q) {
        var item = {
          _id: q.id,
          key: slugifyKey(q.key || ''),
          label: q.label || q.key || '',
          type: q.type || 'text',
          required: !!q.required
        };

        if (q.help_text) item.help_text = q.help_text;
        if (q.show_previous_on_start && q.type !== 'briefing_block') item.show_previous_on_start = true;

        if (q.type === 'select' || q.type === 'radio' || q.type === 'categorized_image_upload') {
          var opts = (q.options || []).map(function (o) { return String(o || '').trim(); }).filter(Boolean);
          if (opts.length) item.options = opts;
          if (q.type === 'select' || q.type === 'radio') {
            item.selection_mode = q.selection_mode === 'multiple' ? 'multiple' : 'single';
            if (item.selection_mode === 'multiple') item.multiple = true;
          }
          if (q.type === 'categorized_image_upload') item.multiple = q.multiple !== false;
        }
        if (q.type === 'visual_choice') {
          var visualOpts = (q.visual_options || []).map(function (opt) {
            opt = opt || {};
            var label = String(opt.label || opt.value || '').trim();
            var value = String(opt.value || label).trim();
            return {
              _id: opt.id || makeId('opt'),
              label: label,
              value: value || label,
              image_id: parseInt(opt.image_id || 0, 10) || 0,
              image_url: String(opt.image_url || '').trim()
            };
          }).filter(function (opt) { return !!opt.label; });
          item.visual_options = visualOpts;
          item.options = visualOpts.map(function (opt) { return opt.value; });
          item.selection_mode = q.selection_mode === 'multiple' ? 'multiple' : 'single';
          if (item.selection_mode === 'multiple') item.multiple = true;
        }
        if (q.type === 'briefing_block') {
          item.required = false;
          item.briefing_source = q.briefing_source === 'all_active' ? 'all_active' : 'active_latest';
          item.briefing_show_context = q.briefing_show_context !== false;
          item.briefing_question_limit = Math.max(1, Math.min(100, parseInt(q.briefing_question_limit || 30, 10) || 30));
          item.briefing_only_mapped = true;
        }
        if (q.type === 'product_matrix' || q.type === 'order_suggestion') {
          item.product_source = q.product_source || 'manual';
          item.cardex_id = parseInt(q.cardex_id || 0, 10) || 0;
          var rows = (q.product_rows || []).map(function(r){ r=r||{}; return { category: String(r.category || '').trim(), ref: String(r.ref || '').trim(), item_type: String(r.item_type || '').trim(), name: String(r.name || '').trim(), capacity_cl: String(r.capacity_cl || '').trim(), units_per_case: String(r.units_per_case || '').trim() }; }).filter(function(r){ return r.category || r.ref || r.item_type || r.name; });
          if (rows.length) item.product_rows = rows;
          if (q.type === 'product_matrix' && q.catalog_images_disabled) item.catalog_images_disabled = true;
          if (q.type === 'product_matrix' && q.camera_reading) item.camera_reading = true;
          if (q.type === 'order_suggestion') {
            item.only_store_assortment = q.only_store_assortment !== false;
            item.order_quantity_decimals = !!q.order_quantity_decimals;
            item.order_zero_fronts_suggestion = Math.max(0, parseFloat(q.order_zero_fronts_suggestion) || 0);
          }
        }

        if (q.min !== '' && q.min !== null && q.min !== undefined) item.min = q.min;
        if (q.max !== '' && q.max !== null && q.max !== undefined) item.max = q.max;
        if (q.unit) item.unit = q.unit;
        if ((q.type === 'image_upload' || q.type === 'file_upload' || q.type === 'categorized_image_upload') && q.multiple) item.multiple = true;
        var conditionRules = (q.conditions && Array.isArray(q.conditions.rules) ? q.conditions.rules : []).map(function (rule) {
          rule = rule || {};
          return {
            _id: rule.id || makeId('rule'),
            value: (rule.value === undefined || rule.value === null) ? '' : String(rule.value),
            go_to: slugifyKey(rule.go_to || '')
          };
        }).filter(function (rule) { return rule.go_to && rule.value !== ''; });
        if (q.conditions && q.conditions.enabled && conditionRules.length) {
          item.conditions = {
            enabled: true,
            rules: conditionRules,
            value: conditionRules[0].value,
            go_to: conditionRules[0].go_to
          };
        }

        return item;
      })
    };

    FB.els.schema.val(JSON.stringify(payload));
    renderConditionalIntegrity();
  }

  function itemTemplateHTML() {
    return $('#twt-fb-item-tpl').html() || '';
  }

  function updateVisibilityForType($item, type) {
    var isChoice = (type === 'select' || type === 'radio');
    var isCategorizedUpload = (type === 'categorized_image_upload');
    var isEvidenceCheck = (type === 'evidence_check');
    var isVisualChoice = (type === 'visual_choice');
    var isNumber = (type === 'number' || type === 'currency' || type === 'percent');
    var isUpload = (type === 'image_upload' || type === 'file_upload' || type === 'categorized_image_upload');
    var isProductMatrix = (type === 'product_matrix' || type === 'order_suggestion');
    var isOrderSuggestion = (type === 'order_suggestion');
    var isConditional = (type === 'checkbox' || type === 'select' || type === 'radio' || type === 'visual_choice' || type === 'categorized_image_upload' || type === 'evidence_check');

    $item.find('.twt-fb-options').toggle(isChoice || isCategorizedUpload);
    $item.find('[data-fb-options-label]').text(isCategorizedUpload ? 'Categorias de evidência, uma por linha' : 'Opções, uma por linha');
    $item.find('[data-fb-options-help]').text(isCategorizedUpload ? 'Exemplo: Expositores, Arcas, Displays, Topos. Cada categoria abre o seu próprio upload no front.' : '');
    $item.find('.twt-fb-evidence-hint').remove();
    if (isEvidenceCheck) {
      $item.find('.twt-fb-body').append('<div class="twt-fb-small twt-fb-evidence-hint"><strong>Fluxo automático:</strong> Sim permite fotografias opcionais, Não permite indicar um motivo opcional, Não aplicável não abre campos adicionais. Este comportamento funciona também na app.</div>');
    }
    $item.find('.twt-fb-choice-settings').toggle(isChoice || isVisualChoice);
    $item.find('.twt-fb-visual-settings').toggle(isVisualChoice);
    $item.find('.twt-fb-products').toggle(isProductMatrix);
    $item.find('.twt-fb-order-settings').toggle(isOrderSuggestion);
    if (isProductMatrix) {
      var source = $item.find('select[data-fb="product_source"]').val() || 'manual';
      $item.find('.twt-fb-manual-products').toggle(source === 'manual');
      $item.find('.twt-fb-cardex-fixed-picker').toggle(source === 'cardex_fixed');
      $item.find('.twt-fb-auto-cardex-note').toggle(source === 'cardex_auto');
    }

    $item.find('input[data-fb="min"]').closest('.twt-fb-row').toggle(isNumber);
    $item.find('input[data-fb="max"]').closest('.twt-fb-row').toggle(isNumber);
    $item.find('input[data-fb="unit"]').closest('.twt-fb-row').toggle(isNumber);

    $item.find('.twt-fb-upload-settings').toggle(isUpload);
    $item.find('[data-fb-upload-multiple-label]').text(isCategorizedUpload ? 'Permitir várias imagens por categoria' : 'Permitir vários ficheiros');
    $item.find('[data-fb-upload-help]').text(isCategorizedUpload ? 'Cada categoria selecionada exige pelo menos uma imagem. As imagens existentes são preservadas ao editar o reporte.' : 'Disponível para uploads de imagem e ficheiro.');
    $item.find('.twt-fb-briefing-settings').toggle(type === 'briefing_block');
    $item.find('.twt-fb-conditions').toggle(isConditional && type !== 'briefing_block');
    $item.find('.twt-fb-condition-fields').toggle(isConditional && $item.find('input[data-fb="condition_enabled"]').is(':checked'));

    $item.find('.twt-fb-upload-hint').remove();
    if (isUpload) {
      var msg = type === 'categorized_image_upload'
        ? 'O utilizador seleciona as categorias aplicáveis e carrega fotografias separadas para cada uma.'
        : ((type === 'image_upload')
          ? 'Upload de imagem, no front usa ficheiro/imagem.'
          : 'Upload de ficheiro, no front usa ficheiro.');
      $item.find('.twt-fb-body').append('<div class="twt-fb-small twt-fb-upload-hint">' + escapeHtml(msg) + '</div>');
    }
  }

  function ensurePreviewBox($item) {
    var $prev = $item.find('.twt-fb-preview');
    if ($prev.length) return $prev;

    var html = '<div class="twt-fb-preview-wrap">' +
      '<div class="twt-fb-small" style="margin:10px 0 6px 0;">Preview</div>' +
      '<div class="twt-fb-preview"></div>' +
      '</div>';

    $item.find('.twt-fb-body').prepend(html);
    return $item.find('.twt-fb-preview');
  }

  function renderPreview($item, q) {
    var $prev = ensurePreviewBox($item);
    var type = q.type;

    var html = '';

    if (type === 'briefing_block') {
      html = '<div style="border:1px solid #bfdbfe;background:linear-gradient(135deg,#eff6ff,#fff);border-radius:16px;padding:14px"><strong>Bloco de Briefing</strong><div style="font-size:12px;color:#475569;margin-top:5px">No front, este campo é substituído pelas perguntas aprovadas do briefing ativo para a loja associada à pergunta.</div></div>';
    } else if (type === 'textarea') {
      html = '<textarea rows="3" style="width:100%;" disabled placeholder="Texto longo"></textarea>';
    } else if (type === 'date') {
      html = '<input type="date" style="width:100%;" readonly>';
    } else if (type === 'time') {
      html = '<input type="time" style="width:100%;" readonly>';
    } else if (type === 'checkbox') {
      html = '<label style="display:inline-flex;align-items:center;gap:10px;"><input type="checkbox" disabled> <span>Sim</span></label>';
    } else if (type === 'visual_choice') {
      var visualOpts = (q.visual_options || []).filter(function (opt) { return opt && opt.label; });
      html = '<div class="twt-fb-preview-visual-grid">' + visualOpts.slice(0, 8).map(function (opt) {
        var media = opt.image_url ? '<img src="' + escapeHtml(opt.image_url) + '" alt="">' : '<div class="twt-fb-preview-visual-media">Sem imagem</div>';
        var inputType = q.selection_mode === 'multiple' ? 'checkbox' : 'radio';
        return '<div class="twt-fb-preview-visual-card">' + media + '<div class="twt-fb-preview-visual-label"><input type="' + inputType + '" disabled> <span>' + escapeHtml(opt.label) + '</span></div></div>';
      }).join('') + '</div><small style="color:#64748b">' + (q.selection_mode === 'multiple' ? 'Permite selecionar várias opções.' : 'Permite selecionar uma opção.') + '</small>';
    } else if (type === 'select' || type === 'radio') {
      var choiceOpts = (q.options || []).filter(Boolean);
      var isMultipleChoice = q.selection_mode === 'multiple';
      if (isMultipleChoice) {
        html = '<div style="display:grid;gap:8px;">' +
          choiceOpts.slice(0, 6).map(function (o) {
            return '<label style="display:inline-flex;align-items:center;gap:10px;"><input type="checkbox" disabled> <span>' + escapeHtml(o) + '</span></label>';
          }).join('') +
          '<small style="color:#64748b">Permite selecionar várias opções.</small></div>';
      } else if (type === 'select') {
        html = '<select style="width:100%;" disabled>' +
          '<option>Selecionar</option>' +
          choiceOpts.slice(0, 6).map(function (o) { return '<option>' + escapeHtml(o) + '</option>'; }).join('') +
          '</select>';
      } else {
        html = '<div style="display:grid;gap:8px;">' +
          choiceOpts.slice(0, 6).map(function (o) {
            return '<label style="display:inline-flex;align-items:center;gap:10px;"><input type="radio" disabled> <span>' + escapeHtml(o) + '</span></label>';
          }).join('') +
          '</div>';
      }
    } else if (type === 'product_matrix') {
      var rows = (q.product_rows || []).slice(0, 4);
      if (!rows.length) rows = [{category:'Categoria',ref:'REF',name:'Produto exemplo'}];
      html = '<div style="display:grid;gap:6px;overflow:auto">' + rows.map(function(r){return '<div style="display:grid;grid-template-columns:1.2fr 1fr 2fr 80px;gap:6px;min-width:620px"><input type="text" disabled value="'+escapeHtml(r.category||'')+'"><input type="text" disabled value="'+escapeHtml(r.ref||'')+'"><input type="text" disabled value="'+escapeHtml(r.name||'')+'"><input type="number" disabled placeholder="Qtd"></div>';}).join('') + '</div>';
    } else if (type === 'order_suggestion') {
      var orows = (q.product_rows || []).slice(0, 3);
      if (!orows.length) orows = [{category:'GIN',ref:'2050501',item_type:'PBL',name:'GIN BEEFEATER 70CL',capacity_cl:'70',units_per_case:'12'}];
      html = '<div style="display:grid;gap:6px;overflow:auto">' + orows.map(function(r){return '<div style="display:grid;grid-template-columns:1fr .8fr .65fr 2fr .55fr .55fr 1fr 1.2fr;gap:5px;min-width:900px"><input disabled value="'+escapeHtml(r.category||'')+'"><input disabled value="'+escapeHtml(r.ref||'')+'"><input disabled value="'+escapeHtml(r.item_type||'')+'"><input disabled value="'+escapeHtml(r.name||'')+'"><input disabled value="'+escapeHtml(r.capacity_cl||'')+'"><input disabled value="'+escapeHtml(r.units_per_case||'')+'"><input type="number" disabled placeholder="CXS"><input disabled placeholder="Observações"></div>';}).join('') + '</div>';
    } else if (type === 'evidence_check') {
      html = '<div style="display:grid;gap:10px">' +
        '<div style="display:flex;gap:16px;flex-wrap:wrap"><label><input type="radio" disabled> Sim</label><label><input type="radio" disabled> Não</label><label><input type="radio" disabled> Não aplicável</label></div>' +
        '<div style="border:1px dashed #cbd5e1;border-radius:10px;padding:9px 11px;color:#64748b"><strong>Se Sim:</strong> fotografias de evidência, múltiplas.</div>' +
        '<div style="border:1px dashed #cbd5e1;border-radius:10px;padding:9px 11px;color:#64748b"><strong>Se Não:</strong> motivo da não conformidade, opcional.</div>' +
      '</div>';
    } else if (type === 'categorized_image_upload') {
      var evidenceCategories = (q.options || []).filter(Boolean);
      html = '<div style="display:grid;gap:9px;">' + evidenceCategories.slice(0, 6).map(function (o) {
        return '<div style="border:1px solid #dbe3ee;border-radius:12px;padding:10px 12px;background:#fff"><label style="display:flex;align-items:center;gap:9px;font-weight:700"><input type="checkbox" disabled> <span>' + escapeHtml(o) + '</span></label><input type="file" accept="image/*" disabled' + (q.multiple ? ' multiple' : '') + ' style="width:100%;margin-top:8px"></div>';
      }).join('') + '</div><small style="color:#64748b">Cada categoria selecionada recebe o seu próprio conjunto de imagens.</small>';
    } else if (type === 'image_upload') {
      html = '<input type="file" accept="image/*" style="width:100%;" disabled' + (q.multiple ? ' multiple' : '') + '>';
    } else if (type === 'file_upload') {
      html = '<input type="file" style="width:100%;" disabled' + (q.multiple ? ' multiple' : '') + '>'; 
    } else if (type === 'number' || type === 'currency' || type === 'percent') {
      var suffix = '';
      if (type === 'currency') suffix = '€';
      if (type === 'percent') suffix = '%';
      var min = (q.min !== '' && q.min !== null && q.min !== undefined) ? ' min="' + escapeHtml(q.min) + '"' : '';
      var max = (q.max !== '' && q.max !== null && q.max !== undefined) ? ' max="' + escapeHtml(q.max) + '"' : '';
      html = '<div style="display:flex;gap:10px;align-items:center;">' +
        '<input type="number" step="0.01" style="width:100%;" disabled' + min + max + '>' +
        (suffix ? '<span style="white-space:nowrap;opacity:.75;">' + suffix + '</span>' : '') +
        '</div>';
    } else {
      html = '<input type="text" style="width:100%;" disabled placeholder="Texto">';
    }

    $prev.html(html);
  }

  function conditionWouldCreateCycle(currentId, targetKey) {
    var current = FB.state.questions.find(function (item) { return item && item.id === currentId; });
    var sourceKey = current && current.key ? String(current.key) : '';
    targetKey = String(targetKey || '');
    if (!sourceKey || !targetKey || sourceKey === targetKey) return true;
    var graph = {};
    FB.state.questions.forEach(function (item) {
      if (!item || !item.key || !item.conditions || !item.conditions.enabled || !Array.isArray(item.conditions.rules)) return;
      graph[item.key] = graph[item.key] || [];
      item.conditions.rules.forEach(function (rule) {
        if (rule && rule.go_to && rule.go_to !== item.key) graph[item.key].push(String(rule.go_to));
      });
    });
    graph[sourceKey] = graph[sourceKey] || [];
    if (graph[sourceKey].indexOf(targetKey) === -1) graph[sourceKey].push(targetKey);
    var stack = [targetKey], visited = {};
    while (stack.length) {
      var node = stack.pop();
      if (node === sourceKey) return true;
      if (visited[node]) continue;
      visited[node] = true;
      (graph[node] || []).forEach(function (next) { if (!visited[next]) stack.push(next); });
    }
    return false;
  }

  function questionTargetOptions(currentId) {
    return FB.state.questions
      .filter(function (item) { return item && item.id !== currentId && item.key && !conditionWouldCreateCycle(currentId, item.key); })
      .map(function (item) { return { key: item.key, label: item.label || item.key }; });
  }

  function findVisualOption(q, optionId) {
    var options = q && Array.isArray(q.visual_options) ? q.visual_options : [];
    for (var i = 0; i < options.length; i++) {
      if (options[i].id === optionId) return options[i];
    }
    return null;
  }

  function renderVisualOptionsEditor($item, q) {
    var $list = $item.find('[data-fb-visual-options-list]');
    if (!$list.length) return;
    var rows = (q.visual_options || []).map(function (opt) {
      var media = opt.image_url ? '<img src="' + escapeHtml(opt.image_url) + '" alt="">' : '<span>Sem imagem</span>';
      return '<div class="twt-fb-visual-option-row" data-fb-visual-option="' + escapeHtml(opt.id) + '">' +
        '<div class="twt-fb-visual-thumb">' + media + '</div>' +
        '<div class="twt-fb-visual-option-main"><label>Nome da opção<input type="text" data-fb-visual-label value="' + escapeHtml(opt.label) + '" placeholder="Ex, Expositor"></label><div class="twt-fb-small">Valor guardado, ' + escapeHtml(opt.value || opt.label) + '</div></div>' +
        '<div class="twt-fb-visual-option-actions"><button type="button" class="button" data-fb-visual-image>' + (opt.image_url ? 'Trocar imagem' : 'Escolher imagem') + '</button><button type="button" class="button" data-fb-visual-up title="Subir">↑</button><button type="button" class="button" data-fb-visual-down title="Descer">↓</button><button type="button" class="button-link-delete" data-fb-visual-remove>Remover</button></div>' +
      '</div>';
    });
    $list.html(rows.join(''));
  }

  function conditionSourceOptions(q) {
    if (!q) return [];
    if (q.type === 'checkbox') {
      return [
        { value: '1', label: 'Sim, selecionado' },
        { value: '0', label: 'Não, por selecionar' }
      ];
    }
    if (q.type === 'visual_choice') {
      return (q.visual_options || []).map(function (opt) {
        opt = opt || {};
        var label = String(opt.label || opt.value || '').trim();
        var value = String(opt.value || label).trim();
        return { value: value, label: label || value };
      }).filter(function (opt) { return !!opt.value; });
    }
    if (q.type === 'evidence_check') {
      return [
        { value: 'Sim', label: 'Sim' },
        { value: 'Não', label: 'Não' },
        { value: 'Não aplicável', label: 'Não aplicável' }
      ];
    }
    if (q.type === 'select' || q.type === 'radio' || q.type === 'categorized_image_upload') {
      return (q.options || []).map(function (opt) {
        var value = String(opt || '').trim();
        return { value: q.type === 'categorized_image_upload' ? slugifyKey(value) : value, label: value };
      }).filter(function (opt) { return !!opt.value; });
    }
    return [];
  }

  function renderConditionRules($item, q) {
    var $list = $item.find('[data-fb-condition-rules]');
    if (!$list.length) return;
    var rules = q.conditions && Array.isArray(q.conditions.rules) ? q.conditions.rules : [];
    var targets = questionTargetOptions(q.id);
    var sourceOptions = conditionSourceOptions(q);
    var html = rules.map(function (rule) {
      var targetOptions = ['<option value="">Selecionar pergunta</option>'];
      targets.forEach(function (target) {
        targetOptions.push('<option value="' + escapeHtml(target.key) + '"' + (target.key === rule.go_to ? ' selected' : '') + '>' + escapeHtml(target.label) + ' (' + escapeHtml(target.key) + ')</option>');
      });
      var valueOptions = ['<option value="">Selecionar resposta</option>'];
      var valueExists = false;
      sourceOptions.forEach(function (option) {
        var selected = String(option.value) === String(rule.value);
        if (selected) valueExists = true;
        valueOptions.push('<option value="' + escapeHtml(option.value) + '"' + (selected ? ' selected' : '') + '>' + escapeHtml(option.label) + '</option>');
      });
      if (rule.value !== '' && !valueExists) {
        valueOptions.push('<option value="' + escapeHtml(rule.value) + '" selected>' + escapeHtml(rule.value) + ' (valor anterior)</option>');
      }
      return '<div class="twt-fb-condition-rule" data-fb-condition-rule="' + escapeHtml(rule.id) + '">' +
        '<label>Resposta que ativa<select data-fb-condition-value>' + valueOptions.join('') + '</select></label>' +
        '<label>Mostrar a pergunta<select data-fb-condition-goto>' + targetOptions.join('') + '</select></label>' +
        '<div class="twt-fb-condition-actions"><button type="button" class="button-link-delete" data-fb-remove-condition>Remover</button></div>' +
      '</div>';
    });
    $list.html(html.join(''));
  }

  function syncConditionTargets($item, q) {
    renderConditionRules($item, q);
  }

  function syncAllConditionEditors() {
    FB.els.list.find('.twt-fb-item').each(function () {
      var $item = $(this);
      var q = findQuestion($item.attr('data-id'));
      if (q) renderConditionRules($item, q);
    });
  }

  var CONDITION_GRAPH_INTEGRITY_CONTRACT = 'fieldflow-condition-graph-integrity-v1';

  function integrityToken(value) {
    return String(value || '').toLowerCase().trim()
      .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function conditionDomain(q) {
    q = q || {};
    var type = String(q.type || '');
    var text = integrityToken(String(q.key || '') + ' ' + String(q.label || ''));
    function hasAny(words) {
      for (var i = 0; i < words.length; i++) if (text.indexOf(words[i]) !== -1) return true;
      return false;
    }
    if (type === 'order_suggestion' || hasAny(['encomenda', 'order', 'pedido', 'sugestao de encomenda', 'sugestaoencomenda', 'sugestaodeencomenda'])) return 'order';
    if (hasAny(['conquista', 'implementacao', 'implementacoes', 'expositor', 'arca', 'display', 'topo', 'orelha', 'planograma'])) return 'conquest';
    if (type === 'categorized_image_upload' || type === 'evidence_check' || type === 'image_upload' || hasAny(['evidencia', 'foto', 'fotografia', 'imagem', 'prova'])) return 'evidence';
    if (type === 'briefing_block' || hasAny(['briefing'])) return 'briefing';
    return 'general';
  }

  function isSuspiciousCrossDomain(sourceDomain, targetDomain) {
    if (!sourceDomain || !targetDomain || sourceDomain === targetDomain) return false;
    return (sourceDomain === 'conquest' && targetDomain === 'order') ||
      (sourceDomain === 'order' && targetDomain === 'conquest');
  }

  function buildConditionGraphIntegrity() {
    var byKey = {};
    var incoming = {};
    var edges = [];
    FB.state.questions.forEach(function (q) {
      if (!q || !q.key) return;
      byKey[q.key] = q;
    });
    FB.state.questions.forEach(function (q) {
      if (!q || !q.key || !q.conditions || !q.conditions.enabled || !Array.isArray(q.conditions.rules)) return;
      q.conditions.rules.forEach(function (rule) {
        if (!rule || !rule.go_to || rule.value === '' || !byKey[rule.go_to] || rule.go_to === q.key) return;
        var target = byKey[rule.go_to];
        var edge = {
          source_key: q.key,
          source_label: q.label || q.key,
          source_domain: conditionDomain(q),
          value: String(rule.value),
          target_key: target.key,
          target_label: target.label || target.key,
          target_domain: conditionDomain(target),
          rule_id: rule.id || ''
        };
        edges.push(edge);
        incoming[target.key] = incoming[target.key] || [];
        incoming[target.key].push(edge);
      });
    });
    var warnings = edges.filter(function (edge) {
      return isSuspiciousCrossDomain(edge.source_domain, edge.target_domain);
    }).map(function (edge) {
      edge.code = 'cross_domain_gate';
      edge.message = 'Este ramo liga áreas funcionais diferentes. Confirma que a exclusividade é intencional.';
      return edge;
    });
    return {
      contract: CONDITION_GRAPH_INTEGRITY_CONTRACT,
      semantics: 'non_blocking_graph_audit',
      combination: 'or',
      rules: edges,
      incoming: incoming,
      warnings: warnings,
      controlled_count: Object.keys(incoming).length
    };
  }

  function renderConditionalIntegrity() {
    if (!FB.els.list || !FB.els.list.length) return;
    var audit = buildConditionGraphIntegrity();
    var $box = $('[data-fb-integrity]');
    if (!$box.length) {
      $box = $('<div class="twt-fb-integrity" data-fb-integrity></div>');
      FB.els.list.before($box);
    }
    var html = '';
    if (audit.warnings.length) {
      html += '<div class="twt-fb-integrity-head is-warning"><strong>Auditoria condicional, rever ' + audit.warnings.length + ' ligação(ões)</strong><span>O formulário continua gravável, mas estas dependências podem criar ramos exclusivos inesperados.</span></div>';
      html += '<div class="twt-fb-integrity-warnings">';
      audit.warnings.forEach(function (warning) {
        html += '<div class="twt-fb-integrity-warning"><strong>' + escapeHtml(warning.source_label) + '</strong> = <code>' + escapeHtml(warning.value) + '</code> → <strong>' + escapeHtml(warning.target_label) + '</strong><span>' + escapeHtml(warning.message) + '</span></div>';
      });
      html += '</div>';
    } else {
      html = '<div class="twt-fb-integrity-head is-ok"><strong>Grafo condicional coerente</strong><span>' + audit.rules.length + ' regra(s), ' + audit.controlled_count + ' pergunta(s) controlada(s). Sem colisões funcionais suspeitas.</span></div>';
    }
    $box.attr('data-contract', audit.contract).html(html);

    FB.els.list.find('.twt-fb-item').each(function () {
      var $item = $(this);
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      var incoming = audit.incoming[q.key] || [];
      var $ownership = $item.find('[data-fb-controlled-by]');
      if (!$ownership.length) {
        $ownership = $('<div class="twt-fb-controlled-by" data-fb-controlled-by></div>');
        $item.find('.twt-fb-grid').first().after($ownership);
      }
      if (!incoming.length) {
        $ownership.empty().hide();
      } else {
        var chips = incoming.map(function (edge) {
          return '<span class="twt-fb-control-chip"><strong>' + escapeHtml(edge.source_label) + '</strong> = ' + escapeHtml(edge.value) + '</span>';
        }).join('');
        var note = incoming.length > 1 ? '<span class="twt-fb-control-note">Aparece se qualquer uma destas condições for verdadeira, lógica OR.</span>' : '';
        $ownership.html('<span class="twt-fb-control-title">Controlada por</span>' + chips + note).show();
      }
      var itemWarnings = audit.warnings.filter(function (warning) { return warning.source_key === q.key || warning.target_key === q.key; });
      $item.toggleClass('has-condition-integrity-warning', itemWarnings.length > 0);
      $item.find('[data-fb-condition-rule]').removeClass('has-integrity-warning');
      itemWarnings.forEach(function (warning) {
        if (warning.source_key !== q.key) return;
        $item.find('[data-fb-condition-rule="' + warning.rule_id + '"]').addClass('has-integrity-warning');
      });
    });
  }

  function renderItem(q) {
    var tpl = itemTemplateHTML();
    var optionsText = (q.options || []).join('\n');
    var productsText = (q.product_rows || []).map(function(r){ r=r||{}; return q.type === 'order_suggestion' ? [r.category||'', r.ref||'', r.item_type||'', r.name||'', r.capacity_cl||'', r.units_per_case||''].join(';') : [r.category||'', r.ref||'', r.name||''].join(';'); }).join('\n');

    function rep(key, val) {
      var rx = new RegExp('{{' + key + '}}', 'g');
      tpl = tpl.replace(rx, val);
    }

    rep('id', q.id);
    rep('label', escapeHtml(q.label || 'Pergunta'));
    rep('key', escapeHtml(q.key || ''));
    rep('type', escapeHtml(q.type || 'text'));
    rep('help_text', escapeHtml(q.help_text || ''));
    rep('options_text', escapeHtml(optionsText));
    rep('products_text', escapeHtml(productsText));
    rep('min', escapeHtml(q.min === '' ? '' : String(q.min)));
    rep('max', escapeHtml(q.max === '' ? '' : String(q.max)));
    rep('unit', escapeHtml(q.unit || ''));
    rep('required', q.required ? 'checked' : '');
    rep('show_previous_on_start', q.show_previous_on_start ? 'checked' : '');
    rep('multiple', q.multiple ? 'checked' : '');
    rep('condition_enabled', (q.conditions && q.conditions.enabled) ? 'checked' : '');
    rep('only_store_assortment', q.only_store_assortment !== false ? 'checked' : '');
    rep('catalog_images_disabled', q.catalog_images_disabled ? 'checked' : '');
    rep('camera_reading', q.camera_reading ? 'checked' : '');
    rep('order_quantity_decimals', q.order_quantity_decimals ? 'checked' : '');
    rep('order_zero_fronts_suggestion', escapeHtml(String(q.order_zero_fronts_suggestion === undefined ? 1 : q.order_zero_fronts_suggestion)));

    var $el = $(tpl);
    $el.find('select[data-fb="type"]').val(q.type);
    $el.find('select[data-fb="selection_mode"]').val(q.selection_mode === 'multiple' ? 'multiple' : 'single');
    $el.find('select[data-fb="product_source"]').val(q.product_source || 'manual');
    var cardexOptions = ['<option value="0">Sem cardex</option>'];
    var cardexItems = (window.RoutesProCardex && Array.isArray(window.RoutesProCardex.items)) ? window.RoutesProCardex.items : [];
    cardexItems.forEach(function(c){ var id=parseInt(c.id||0,10)||0; cardexOptions.push('<option value="'+id+'"'+(id===(parseInt(q.cardex_id||0,10)||0)?' selected':'')+'>'+escapeHtml(c.name || ('Cardex #'+id))+'</option>'); });
    $el.find('select[data-fb="cardex_id"]').html(cardexOptions.join(''));
    $el.find('select[data-fb="briefing_source"]').val(q.briefing_source || 'active_latest');
    $el.find('input[data-fb="briefing_show_context"]').prop('checked', q.briefing_show_context !== false);
    $el.find('input[data-fb="briefing_question_limit"]').val(q.briefing_question_limit || 30);
    renderVisualOptionsEditor($el, q);
    renderConditionRules($el, q);

    var currentWidth = ((FB.state.layout.field_layout[q.key] || {}).width) || 100;
    if (WIDTH_PRESETS.indexOf(currentWidth) === -1) currentWidth = 100;

    var widthOptions = WIDTH_PRESETS.map(function (w) {
      return '<option value="' + w + '"' + (w === currentWidth ? ' selected' : '') + '>' + w + '%</option>';
    }).join('');

    var widthRow = '' +
      '<div class="twt-fb-row twt-fb-width-row">' +
      '<label>Largura (front)</label>' +
      '<select data-fb-layout-width="' + escapeHtml(q.key) + '">' +
      widthOptions +
      '</select>' +
      '<div class="twt-fb-small">Define a largura do campo no front (preset).</div>' +
      '</div>';

    $el.find('.twt-fb-body').append(widthRow);

    updateVisibilityForType($el, q.type);
    renderPreview($el, q);

    return $el;
  }

  function ensureSortableQuestions() {
    if (FB.flags.sortableInit) return;
    FB.flags.sortableInit = true;

    FB.els.list.sortable({
      handle: '.twt-fb-drag',
      placeholder: 'twt-fb-placeholder',
      update: function () {
        var order = [];
        FB.els.list.find('.twt-fb-item').each(function () {
          order.push($(this).attr('data-id'));
        });
        FB.state.questions.sort(function (a, b) {
          return order.indexOf(a.id) - order.indexOf(b.id);
        });
        syncHidden();
        renderStepsUI();
      }
    });
  }

  function ensureSortableSteps() {
    if (FB.flags.stepsSortableInit) return;

    var $layout = $('.twt-tcrm-form-builder .twt-fb-layout');
    if (!$layout.length) return;

    var $stepsWrap = $layout.find('.twt-fb-steps');
    if (!$stepsWrap.length) return;

    FB.flags.stepsSortableInit = true;

    $stepsWrap.sortable({
      handle: '.twt-fb-drag-step',
      placeholder: 'twt-fb-placeholder',
      update: function () {
        var order = [];
        $stepsWrap.find('.twt-fb-step').each(function () {
          order.push($(this).attr('data-step-id'));
        });

        FB.state.layout.steps.sort(function (a, b) {
          return order.indexOf(a.id) - order.indexOf(b.id);
        });

        syncHidden();
        renderStepsUI();
      }
    });
  }

  function findStep(stepId) {
    for (var i = 0; i < (FB.state.layout.steps || []).length; i++) {
      if (FB.state.layout.steps[i].id === stepId) return FB.state.layout.steps[i];
    }
    return null;
  }

  function renderStepsUI() {
    var $layout = $('.twt-tcrm-form-builder .twt-fb-layout');
    if (!$layout.length) return;

    var $steps = $layout.find('.twt-fb-steps');

    $layout.find('input[data-fb-layout-mode]').prop('checked', FB.state.layout.mode === 'steps');
    $layout.find('input[data-fb-layout-progress]').prop('checked', !!FB.state.layout.show_progress);

    $steps.empty();

    if (FB.state.layout.mode !== 'steps') {
      $steps.append('<div class="twt-fb-small">Modo single: as perguntas aparecem todas seguidas no front. Ativa "Wizard (Steps)" para organizar por passos.</div>');
      return;
    }

    if (!FB.state.layout.steps.length) {
      FB.state.layout.steps.push({
        id: makeId('step'),
        title: 'Passo 1',
        description: '',
        fields: []
      });
    }

    var allQuestions = FB.state.questions.slice();

    FB.state.layout.steps.forEach(function (st, idx) {
      var stepNo = idx + 1;

      var block = '' +
        '<div class="twt-fb-step" data-step-id="' + escapeHtml(st.id) + '">' +
        '<div style="display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;">' +
        '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">' +
        '<span class="twt-fb-drag-step" title="Arrastar">::</span>' +
        '<strong>Step ' + stepNo + '</strong>' +
        '</div>' +
        '<div>' +
        '<button type="button" class="button-link-delete" data-fb-del-step>Apagar</button>' +
        '</div>' +
        '</div>' +

        '<div class="twt-fb-grid" style="margin-top:10px;">' +
        '<div class="twt-fb-row">' +
        '<label>Título</label>' +
        '<input type="text" data-fb-step-title value="' + escapeHtml(st.title || '') + '">' +
        '</div>' +
        '<div class="twt-fb-row">' +
        '<label>Descrição</label>' +
        '<input type="text" data-fb-step-desc value="' + escapeHtml(st.description || '') + '" placeholder="Opcional">' +
        '</div>' +
        '</div>' +

        '<div class="twt-fb-small" style="margin:10px 0 6px 0;">Perguntas neste step</div>' +
        '<div class="twt-fb-step-fields">' +
        allQuestions.map(function (q) {
          var checked = (st.fields || []).indexOf(q.key) !== -1 ? ' checked' : '';
          return '' +
            '<label style="display:flex;align-items:center;gap:10px;">' +
            '<input type="checkbox" data-fb-step-field="' + escapeHtml(q.key) + '"' + checked + '>' +
            '<span><strong>' + escapeHtml(q.label) + '</strong> <span style="opacity:.6;">(' + escapeHtml(q.key) + ')</span></span>' +
            '</label>';
        }).join('') +
        '</div>' +
        '</div>';

      $steps.append(block);
    });

    ensureSortableSteps();
  }

  function addStep() {
    FB.state.layout.mode = 'steps';
    FB.state.layout.steps.push({
      id: makeId('step'),
      title: 'Passo ' + String(FB.state.layout.steps.length + 1),
      description: '',
      fields: []
    });
    syncHidden();
    renderStepsUI();
  }

  function deleteStep(stepId) {
    FB.state.layout.steps = FB.state.layout.steps.filter(function (s) { return s.id !== stepId; });
    if (!FB.state.layout.steps.length) {
      FB.state.layout.steps.push({
        id: makeId('step'),
        title: 'Passo 1',
        description: '',
        fields: []
      });
    }
    syncHidden();
    renderStepsUI();
  }

  function bindLayoutUI() {
    if (FB.flags.layoutBound) return;
    FB.flags.layoutBound = true;

    $(document).on('change.twtfbl', '.twt-tcrm-form-builder .twt-fb-layout input[data-fb-layout-mode]', function () {
      FB.state.layout.mode = $(this).is(':checked') ? 'steps' : 'single';

      if (FB.state.layout.mode === 'steps' && (!FB.state.layout.steps || !FB.state.layout.steps.length)) {
        FB.state.layout.steps = [{
          id: makeId('step'),
          title: 'Passo 1',
          description: '',
          fields: FB.state.questions.map(function (q) { return q.key; })
        }];
      }

      syncHidden();
      renderStepsUI();
    });

    $(document).on('change.twtfbl', '.twt-tcrm-form-builder .twt-fb-layout input[data-fb-layout-progress]', function () {
      FB.state.layout.show_progress = $(this).is(':checked');
      syncHidden();
    });

    $(document).on('click.twtfbl', '.twt-tcrm-form-builder .twt-fb-layout [data-fb-add-step]', function (e) {
      e.preventDefault();
      addStep();
    });

    $(document).on('click.twtfbl', '.twt-tcrm-form-builder .twt-fb-layout [data-fb-del-step]', function (e) {
      e.preventDefault();
      var $step = $(this).closest('.twt-fb-step');
      var stepId = $step.attr('data-step-id');
      if (!stepId) return;
      if (!confirm('Apagar este step?')) return;
      deleteStep(stepId);
    });

    $(document).on('input.twtfbl', '.twt-tcrm-form-builder .twt-fb-layout input[data-fb-step-title]', function () {
      var $step = $(this).closest('.twt-fb-step');
      var stepId = $step.attr('data-step-id');
      var st = findStep(stepId);
      if (!st) return;
      st.title = $(this).val();
      syncHidden();
    });

    $(document).on('input.twtfbl', '.twt-tcrm-form-builder .twt-fb-layout input[data-fb-step-desc]', function () {
      var $step = $(this).closest('.twt-fb-step');
      var stepId = $step.attr('data-step-id');
      var st = findStep(stepId);
      if (!st) return;
      st.description = $(this).val();
      syncHidden();
    });

    $(document).on('change.twtfbl', '.twt-tcrm-form-builder .twt-fb-layout input[data-fb-step-field]', function () {
      var $step = $(this).closest('.twt-fb-step');
      var stepId = $step.attr('data-step-id');
      var st = findStep(stepId);
      if (!st) return;

      var key = slugifyKey($(this).attr('data-fb-step-field'));
      if (!key) return;

      st.fields = Array.isArray(st.fields) ? st.fields : [];

      if ($(this).is(':checked')) {
        if (st.fields.indexOf(key) === -1) st.fields.push(key);
      } else {
        st.fields = st.fields.filter(function (k) { return k !== key; });
      }

      syncHidden();
    });
  }

  function bindItemUI($root) {
    $root.off('input.twtfb change.twtfb click.twtfb');

    $root.on('click.twtfb', '.twt-fb-toggle', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      var collapsed = !$item.hasClass('is-collapsed');
      $item.toggleClass('is-collapsed', collapsed);
      $(this).text(collapsed ? 'Abrir' : 'Fechar');
      if (q) q.collapsed = collapsed;
    });

    $root.on('click.twtfb', '.twt-fb-del', function () {
      var $item = $(this).closest('.twt-fb-item');
      var id = $item.attr('data-id');
      if (!id) return;
      if (!confirm('Apagar esta pergunta?')) return;

      var q = findQuestion(id);
      FB.state.questions = FB.state.questions.filter(function (qq) { return qq.id !== id; });

      if (q && q.key) {
        (FB.state.layout.steps || []).forEach(function (st) {
          st.fields = (st.fields || []).filter(function (k) { return k !== q.key; });
        });
        delete FB.state.layout.field_layout[q.key];
        FB.state.questions.forEach(function (other) {
          if (!other.conditions || !Array.isArray(other.conditions.rules)) return;
          other.conditions.rules = other.conditions.rules.filter(function (rule) { return rule.go_to !== q.key; });
        });
      }

      renderAll();
    });

    $root.on('input.twtfb', 'input[data-fb="label"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.label = $(this).val();

      if (!q.key) {
        q.key = uniqKey(q.label, q.id);
        $item.find('input[data-fb="key"]').val(q.key);
      }

      $item.find('.twt-fb-label').text(q.label || 'Pergunta');
      syncAllConditionEditors();
      syncHidden();
      renderStepsUI();
    });

    $root.on('change.twtfb', 'select[data-fb="briefing_source"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.briefing_source = $(this).val() === 'all_active' ? 'all_active' : 'active_latest';
      syncHidden();
      renderPreview($item, q);
    });

    $root.on('change.twtfb', 'input[data-fb="briefing_show_context"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.briefing_show_context = $(this).is(':checked');
      syncHidden();
    });

    $root.on('input.twtfb change.twtfb', 'input[data-fb="briefing_question_limit"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.briefing_question_limit = Math.max(1, Math.min(100, parseInt($(this).val() || 30, 10) || 30));
      $(this).val(q.briefing_question_limit);
      syncHidden();
    });

    $root.on('input.twtfb', 'input[data-fb="key"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      var oldKey = q.key;
      var newKey = slugifyKey($(this).val());
      if (!newKey) newKey = uniqKey(q.label || 'pergunta', q.id);

      var existing = existingKeysMap(q.id);
      if (existing[newKey]) newKey = uniqKey(newKey, q.id);

      q.key = newKey;
      $(this).val(q.key);

      if (oldKey && oldKey !== newKey) {
        if (FB.state.layout.field_layout[oldKey]) {
          FB.state.layout.field_layout[newKey] = FB.state.layout.field_layout[oldKey];
          delete FB.state.layout.field_layout[oldKey];
        }
        (FB.state.layout.steps || []).forEach(function (st) {
          st.fields = (st.fields || []).map(function (k) { return k === oldKey ? newKey : k; });
        });
        FB.state.questions.forEach(function (other) {
          if (!other.conditions || !Array.isArray(other.conditions.rules)) return;
          other.conditions.rules.forEach(function (rule) { if (rule.go_to === oldKey) rule.go_to = newKey; });
        });
      }

      // FIX CRÍTICO: manter o select da largura alinhado com a key actual
      $item.find('select[data-fb-layout-width]').attr('data-fb-layout-width', q.key);

      syncAllConditionEditors();
      syncHidden();
      renderStepsUI();
    });

    $root.on('change.twtfb', 'select[data-fb="type"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.type = $(this).val();
      if (q.type === 'evidence_check') {
        q.help_text = q.help_text || 'Se responderes Sim, podes adicionar fotografias. Se responderes Não, podes indicar o motivo da não conformidade.';
        q.selection_mode = 'single';
        q.multiple = false;
        $item.find('input[data-fb="help_text"]').val(q.help_text);
        $item.find('select[data-fb="selection_mode"]').val('single');
        $item.find('input[data-fb="multiple"]').prop('checked', false);
      }
      if (q.type === 'briefing_block') {
        q.required = false;
        q.show_previous_on_start = false;
        q.help_text = q.help_text || 'As perguntas aprovadas do briefing ativo aparecem aqui apenas nas lojas mapeadas.';
        q.briefing_source = q.briefing_source || 'active_latest';
        q.briefing_show_context = q.briefing_show_context !== false;
        q.briefing_question_limit = q.briefing_question_limit || 30;
        q.briefing_only_mapped = true;
        $item.find('input[data-fb="required"]').prop('checked', false);
        $item.find('input[data-fb="show_previous_on_start"]').prop('checked', false);
        $item.find('input[data-fb="help_text"]').val(q.help_text);
      }

      if ((q.type === 'select' || q.type === 'radio') && (!q.options || !q.options.length)) {
        q.options = ['Opção 1', 'Opção 2'];
        $item.find('textarea[data-fb="options"]').val(q.options.join('\n'));
      }
      if (q.type === 'categorized_image_upload' && (!q.options || !q.options.length)) {
        q.options = ['Expositores', 'Arcas', 'Displays', 'Topos'];
        q.multiple = true;
        $item.find('textarea[data-fb="options"]').val(q.options.join('\n'));
        $item.find('input[data-fb="multiple"]').prop('checked', true);
      }
      if (q.type === 'visual_choice' && (!q.visual_options || !q.visual_options.length)) {
        q.visual_options = ['Expositor', 'Arca', 'Display', 'Topo'].map(function (label) { return { id: makeId('opt'), label: label, value: label, image_id: 0, image_url: '' }; });
        renderVisualOptionsEditor($item, q);
      }

      if (!(q.type === 'select' || q.type === 'radio' || q.type === 'categorized_image_upload')) {
        q.options = [];
      }
      if (!(q.type === 'select' || q.type === 'radio' || q.type === 'visual_choice' || q.type === 'categorized_image_upload')) {
        q.selection_mode = 'single';
        $item.find('textarea[data-fb="options"]').val('');
        $item.find('select[data-fb="selection_mode"]').val('single');
      } else if (q.selection_mode !== 'multiple') {
        q.selection_mode = 'single';
        $item.find('select[data-fb="selection_mode"]').val('single');
      }
      if ((q.type === 'product_matrix' || q.type === 'order_suggestion') && (!q.product_rows || !q.product_rows.length)) {
        q.product_rows = q.type === 'order_suggestion' ? [{category:'',ref:'',item_type:'',name:'Produto 1',capacity_cl:'',units_per_case:''}] : [{category:'',ref:'',name:'Produto 1'}];
        $item.find('textarea[data-fb="products"]').val(q.type === 'order_suggestion' ? ';;;Produto 1;;' : ';;Produto 1');
      }
      if (!(q.type === 'product_matrix' || q.type === 'order_suggestion')) {
        q.product_rows = [];
        $item.find('textarea[data-fb="products"]').val('');
      }

      if (!(q.type === 'number' || q.type === 'currency' || q.type === 'percent')) {
        q.min = '';
        q.max = '';
        q.unit = '';
        $item.find('input[data-fb="min"]').val('');
        $item.find('input[data-fb="max"]').val('');
        $item.find('input[data-fb="unit"]').val('');
      }

      $item.find('.twt-fb-type').text(q.type);
      if (!(q.type === 'checkbox' || q.type === 'select' || q.type === 'radio' || q.type === 'visual_choice' || q.type === 'categorized_image_upload' || q.type === 'evidence_check')) {
        q.conditions = { enabled: false, rules: [] };
      }
      if (!(q.type === 'image_upload' || q.type === 'file_upload' || q.type === 'categorized_image_upload')) {
        q.multiple = false;
      } else if (q.type === 'categorized_image_upload' && q.multiple === undefined) {
        q.multiple = true;
      }
      renderVisualOptionsEditor($item, q);
      renderConditionRules($item, q);
      updateVisibilityForType($item, q.type);
      renderPreview($item, q);
      syncHidden();
    });



    $root.on('change.twtfb', 'select[data-fb="product_source"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.product_source = $(this).val() || 'manual';
      updateVisibilityForType($item, q.type);
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'select[data-fb="cardex_id"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.cardex_id = parseInt($(this).val() || 0, 10) || 0;
      syncHidden();
    });

    $root.on('change.twtfb input.twtfb', 'input[data-fb="only_store_assortment"], input[data-fb="order_quantity_decimals"], input[data-fb="order_zero_fronts_suggestion"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.only_store_assortment = $item.find('input[data-fb="only_store_assortment"]').is(':checked');
      q.order_quantity_decimals = $item.find('input[data-fb="order_quantity_decimals"]').is(':checked');
      q.order_zero_fronts_suggestion = Math.max(0, parseFloat($item.find('input[data-fb="order_zero_fronts_suggestion"]').val()) || 0);
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'input[data-fb="camera_reading"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.camera_reading = $(this).is(':checked');
      syncHidden();
    });

    $root.on('change.twtfb', 'input[data-fb="catalog_images_disabled"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.catalog_images_disabled = $(this).is(':checked');
      syncHidden();
    });

    $root.on('change.twtfb', 'input[data-fb="multiple"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.multiple = $(this).is(':checked');
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'select[data-fb="selection_mode"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.selection_mode = $(this).val() === 'multiple' ? 'multiple' : 'single';
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'input[data-fb="condition_enabled"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.conditions = q.conditions || { enabled: false, rules: [] };
      q.conditions.rules = Array.isArray(q.conditions.rules) ? q.conditions.rules : [];
      q.conditions.enabled = $(this).is(':checked');
      if (q.conditions.enabled && !q.conditions.rules.length) q.conditions.rules.push({ id: makeId('rule'), value: '', go_to: '' });
      renderConditionRules($item, q);
      updateVisibilityForType($item, q.type);
      syncHidden();
    });

    $root.on('click.twtfb', '[data-fb-add-condition]', function (e) {
      e.preventDefault();
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.conditions = q.conditions || { enabled: true, rules: [] };
      q.conditions.enabled = true;
      q.conditions.rules = Array.isArray(q.conditions.rules) ? q.conditions.rules : [];
      q.conditions.rules.push({ id: makeId('rule'), value: '', go_to: '' });
      $item.find('input[data-fb="condition_enabled"]').prop('checked', true);
      renderConditionRules($item, q);
      updateVisibilityForType($item, q.type);
      syncHidden();
    });

    $root.on('change.twtfb', '[data-fb-condition-value]', function () {
      var $row = $(this).closest('[data-fb-condition-rule]');
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q || !q.conditions) return;
      var id = $row.attr('data-fb-condition-rule');
      (q.conditions.rules || []).forEach(function (rule) { if (rule.id === id) rule.value = $row.find('[data-fb-condition-value]').val(); });
      syncHidden();
    });

    $root.on('change.twtfb', '[data-fb-condition-goto]', function () {
      var $row = $(this).closest('[data-fb-condition-rule]');
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q || !q.conditions) return;
      var id = $row.attr('data-fb-condition-rule');
      (q.conditions.rules || []).forEach(function (rule) { if (rule.id === id) rule.go_to = slugifyKey($row.find('[data-fb-condition-goto]').val()); });
      syncHidden();
    });

    $root.on('click.twtfb', '[data-fb-remove-condition]', function (e) {
      e.preventDefault();
      var $row = $(this).closest('[data-fb-condition-rule]');
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q || !q.conditions) return;
      var id = $row.attr('data-fb-condition-rule');
      q.conditions.rules = (q.conditions.rules || []).filter(function (rule) { return rule.id !== id; });
      renderConditionRules($item, q);
      syncHidden();
    });

    $root.on('click.twtfb', '[data-fb-add-visual-option]', function (e) {
      e.preventDefault();
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.visual_options = Array.isArray(q.visual_options) ? q.visual_options : [];
      var n = q.visual_options.length + 1;
      q.visual_options.push({ id: makeId('opt'), label: 'Opção ' + n, value: 'Opção ' + n, image_id: 0, image_url: '' });
      renderVisualOptionsEditor($item, q);
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('input.twtfb', '[data-fb-visual-label]', function () {
      var $row = $(this).closest('[data-fb-visual-option]');
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      var opt = findVisualOption(q, $row.attr('data-fb-visual-option'));
      if (!opt) return;
      var oldValue = opt.value || opt.label;
      opt.label = $(this).val();
      opt.value = opt.label;
      if (q.conditions && Array.isArray(q.conditions.rules)) {
        q.conditions.rules.forEach(function (rule) { if (rule.value === oldValue) rule.value = opt.value; });
      }
      renderConditionRules($item, q);
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('click.twtfb', '[data-fb-visual-image]', function (e) {
      e.preventDefault();
      var $row = $(this).closest('[data-fb-visual-option]');
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      var opt = findVisualOption(q, $row.attr('data-fb-visual-option'));
      if (!opt) return;
      if (!window.wp || !wp.media) return;
      var frame = wp.media({ title: 'Selecionar imagem da opção', button: { text: 'Usar esta imagem' }, library: { type: 'image' }, multiple: false });
      frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();
        opt.image_id = parseInt(attachment.id || 0, 10) || 0;
        opt.image_url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : (attachment.url || '');
        renderVisualOptionsEditor($item, q);
        renderPreview($item, q);
        syncHidden();
      });
      frame.open();
    });

    $root.on('click.twtfb', '[data-fb-visual-remove]', function (e) {
      e.preventDefault();
      var $row = $(this).closest('[data-fb-visual-option]');
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      var id = $row.attr('data-fb-visual-option');
      var removed = findVisualOption(q, id);
      q.visual_options = (q.visual_options || []).filter(function (opt) { return opt.id !== id; });
      if (removed && q.conditions && Array.isArray(q.conditions.rules)) {
        q.conditions.rules = q.conditions.rules.filter(function (rule) { return rule.value !== (removed.value || removed.label); });
      }
      renderVisualOptionsEditor($item, q);
      renderConditionRules($item, q);
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('click.twtfb', '[data-fb-visual-up], [data-fb-visual-down]', function (e) {
      e.preventDefault();
      var $row = $(this).closest('[data-fb-visual-option]');
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      var id = $row.attr('data-fb-visual-option');
      var idx = (q.visual_options || []).findIndex(function (opt) { return opt.id === id; });
      if (idx < 0) return;
      var next = $(this).is('[data-fb-visual-up]') ? idx - 1 : idx + 1;
      if (next < 0 || next >= q.visual_options.length) return;
      var tmp = q.visual_options[idx]; q.visual_options[idx] = q.visual_options[next]; q.visual_options[next] = tmp;
      renderVisualOptionsEditor($item, q);
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'input[data-fb="required"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.required = $(this).is(':checked');
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'input[data-fb="show_previous_on_start"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      q.show_previous_on_start = $(this).is(':checked') && q.type !== 'briefing_block';
      syncHidden();
    });

    $root.on('input.twtfb', 'input[data-fb="help_text"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.help_text = $(this).val();
      syncHidden();
    });

    $root.on('input.twtfb', 'textarea[data-fb="options"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      var lines = String($(this).val() || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
      q.options = lines;
      renderConditionRules($item, q);
      renderPreview($item, q);
      syncHidden();
    });



    $root.on('input.twtfb', 'textarea[data-fb="products"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;
      var lines = String($(this).val() || '').split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean);
      q.product_rows = lines.map(function(line){
        var parts = line.split(/[;	,]/).map(function(part){ return String(part || '').trim(); });
        if (q.type === 'order_suggestion') {
          return { category: parts[0] || '', ref: parts[1] || '', item_type: parts[2] || '', name: parts[3] || '', capacity_cl: parts[4] || '', units_per_case: parts[5] || '' };
        }
        if (parts.length === 1) return { category: '', ref: '', name: parts[0] };
        if (parts.length === 2) return { category: '', ref: parts[0], name: parts[1] };
        return { category: parts[0], ref: parts[1], name: parts.slice(2).join(' ').trim() };
      }).filter(function(r){ return r.category || r.ref || r.name; });
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('click.twtfb', '[data-fb-products-template]', function(e){
      e.preventDefault();
      var $item=$(this).closest('.twt-fb-item'); var q=findQuestion($item.attr('data-id')); var csv=(q&&q.type==='order_suggestion') ? 'categoria;codigo;tipo;produto;capacidade_cl;unidades_caixa\nGIN;2050501;PBL;GIN BEEFEATER 70CL;70;12\nVODKA;2050752;PBL;VODKA ABSOLUT 70CL;70;12\n' : 'categoria;referencia;produto\nWhisky;JAM001;Jameson\nGin;BEE001;Beefeater\nVodka;ABS001;Absolut vodka\n';
      var blob = new Blob([csv], {type:'text/csv;charset=utf-8'});
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'fieldflow-template-produtos.csv';
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      setTimeout(function(){ URL.revokeObjectURL(a.href); }, 1000);
    });

    $root.on('input.twtfb', 'input[data-fb="min"], input[data-fb="max"], input[data-fb="unit"]', function () {
      var $item = $(this).closest('.twt-fb-item');
      var q = findQuestion($item.attr('data-id'));
      if (!q) return;

      q.min = $item.find('input[data-fb="min"]').val();
      q.max = $item.find('input[data-fb="max"]').val();
      q.unit = $item.find('input[data-fb="unit"]').val();
      renderPreview($item, q);
      syncHidden();
    });

    $root.on('change.twtfb', 'select[data-fb-layout-width]', function () {
      var key = slugifyKey($(this).attr('data-fb-layout-width'));
      if (!key) return;

      var w = parseInt($(this).val() || '100', 10);
      if (WIDTH_PRESETS.indexOf(w) === -1) w = 100;

      FB.state.layout.field_layout[key] = { width: w };
      syncHidden();
    });
  }

  function findQuestion(id) {
    for (var i = 0; i < FB.state.questions.length; i++) {
      if (FB.state.questions[i].id === id) return FB.state.questions[i];
    }
    return null;
  }

  function renderAll() {
    FB.els.list.empty();

    FB.state.questions.forEach(function (q) {
      var $item = renderItem(q);
      if (q.collapsed) {
        $item.addClass('is-collapsed');
        $item.find('.twt-fb-toggle').text('Abrir');
      }
      FB.els.list.append($item);
    });

    ensureSortableQuestions();
    bindItemUI(FB.els.list);

    renderStepsUI();

    syncHidden();
    syncRawTextarea();
  }

  function syncRawTextarea() {
    var pretty = safeJsonParse(FB.els.schema.val() || '', null);
    if (!pretty) return;
    $('textarea[name="schema_json_raw"]').val(JSON.stringify(pretty, null, 2));
  }

  function bindMetaUI() {
    $(document).on('input.twtfbmeta change.twtfbmeta blur.twtfbmeta', 'input[data-fb-meta="title"]', function () {
      FB.state.meta.title = $(this).val();
      syncHidden();
      syncRawTextarea();
    });
    $(document).on('input.twtfbmeta change.twtfbmeta blur.twtfbmeta', 'input[data-fb-meta="subtitle"]', function () {
      FB.state.meta.subtitle = $(this).val();
      syncHidden();
      syncRawTextarea();
    });
  }

  function bindSubmitSync() {
    $(document).on('submit', 'form', function () {
      var $form = $(this);
      if (!$form.find('input[name="action"][value="routespro_save_form"]').length) return;
      syncHidden();
      syncRawTextarea();
    });
  }

  function init() {
    FB.els.schema = $('#twt_form_schema_json');
    FB.els.list = $('#twt-fb-list');

    if (!FB.els.schema.length || !FB.els.list.length) return;

    hydrateStateFromHidden();

    bindLayoutUI();

    renderAll();

    $('#twt-fb-add').on('click', function () {
      FB.state.questions.unshift(normaliseQuestion({ label: 'Nova pergunta', type: 'text', required: false }));
      FB.state.questions[0].key = uniqKey(FB.state.questions[0].label, FB.state.questions[0].id);
      renderAll();
    });

    bindMetaUI();
    bindSubmitSync();
  }

  $(init);

})(jQuery);
