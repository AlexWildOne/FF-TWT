(function () {
  'use strict';

  var config = window.FieldFlowFormsRuntimeV3Config || {};
  var VERSION = String(config.version || '3.0.0-pilot');
  var BUILD = String(config.build || '2.2.245');
  var observers = new WeakMap();
  var timers = new WeakMap();
  var lastSignatures = new WeakMap();
  var reconciling = new WeakSet();

  function toArray(value) {
    return Array.prototype.slice.call(value || []);
  }

  function formMode(form) {
    return String(form && form.getAttribute('data-rp-runtime-mode') || 'legacy');
  }

  function formOwner(form) {
    return String(form && form.getAttribute('data-rp-runtime-owner') || 'legacy');
  }

  function isPilot(form) {
    return formMode(form) === 'pilot' && formOwner(form) === 'v3';
  }

  function formValue(form, name) {
    var field = form.querySelector('[name="' + name + '"]');
    return field ? String(field.value || '') : '';
  }

  function visible(element) {
    if (!element || element.hidden) return false;
    var style = window.getComputedStyle ? window.getComputedStyle(element) : null;
    return !style || (style.display !== 'none' && style.visibility !== 'hidden');
  }

  function normalise(value) {
    return String(value === undefined || value === null ? '' : value).toLowerCase().trim();
  }

  function typedFormCore() {
    var frontend = window.FieldFlowFrontend;
    var core = frontend && frontend.formCore;
    return core && core.contract === 'fieldflow-typed-form-state-conditional-core-v1' ? core : null;
  }

  function conditionMatches(actual, expected) {
    var core = typedFormCore();
    if (core && core.conditions && typeof core.conditions.matches === 'function') {
      try { return !!core.conditions.matches(actual, expected); } catch (error) {}
    }
    if (Array.isArray(actual)) {
      var wanted = normalise(expected);
      return actual.some(function (value) { return normalise(value) === wanted; });
    }
    return normalise(actual) === normalise(expected);
  }

  function questionKey(wrap) {
    return String(wrap.getAttribute('data-rp-question-wrap') || '').trim();
  }

  function fieldValue(question) {
    var type = String(question.getAttribute('data-rp-type') || '');
    if (type === 'checkbox') {
      var checkbox = question.querySelector('input[type="checkbox"]');
      return checkbox && checkbox.checked ? '1' : '0';
    }
    if (type === 'categorized_image_upload') {
      return toArray(question.querySelectorAll('[data-rp-evidence-toggle]:checked')).map(function (input) {
        return input.value;
      });
    }
    if (question.getAttribute('data-rp-multiple-choice') === '1') {
      return toArray(question.querySelectorAll('input[type="checkbox"]:checked')).map(function (input) {
        return input.value;
      });
    }
    if (type === 'radio' || type === 'visual_choice') {
      var selected = question.querySelector('input[type="radio"]:checked,input[type="checkbox"]:checked');
      return selected ? selected.value : '';
    }
    var input = question.querySelector('select,textarea,input:not([type="hidden"]):not([type="button"]):not([type="submit"])');
    return input ? input.value || '' : '';
  }

  function parseRules(question, issues) {
    var raw = String(question.getAttribute('data-rp-condition-rules') || '').trim();
    if (!raw) {
      var goTo = String(question.getAttribute('data-rp-condition-goto') || '').trim();
      if (!goTo) return [];
      return [{ value: String(question.getAttribute('data-rp-condition-value') || ''), go_to: goTo }];
    }
    try {
      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) throw new Error('rules_not_array');
      return parsed.filter(function (rule) {
        return rule && String(rule.go_to || '').trim() !== '' && rule.value !== undefined && rule.value !== null && String(rule.value) !== '';
      });
    } catch (error) {
      issues.push({ code: 'condition_rules_invalid_json' });
      return [];
    }
  }

  function detectCycles(keys, rules) {
    var adjacency = {};
    var colour = {};
    var cycles = [];
    keys.forEach(function (key) { adjacency[key] = []; colour[key] = 0; });
    rules.forEach(function (rule) {
      if (adjacency[rule.source] && adjacency[rule.target]) adjacency[rule.source].push(rule.target);
    });
    function visit(key, path) {
      colour[key] = 1;
      adjacency[key].forEach(function (target) {
        if (colour[target] === 0) visit(target, path.concat([target]));
        else if (colour[target] === 1) cycles.push(path.concat([target]));
      });
      colour[key] = 2;
    }
    keys.forEach(function (key) { if (colour[key] === 0) visit(key, [key]); });
    return cycles;
  }

  function buildModel(form) {
    var issues = [];
    var wraps = toArray(form.querySelectorAll('[data-rp-question-wrap]'));
    var keys = [];
    var keyMap = {};
    var questionMap = {};
    var rules = [];
    var targets = {};

    wraps.forEach(function (wrap) {
      var key = questionKey(wrap);
      if (!key) {
        issues.push({ code: 'question_key_missing' });
        return;
      }
      if (keyMap[key]) issues.push({ code: 'question_key_duplicate', key: key });
      keys.push(key);
      keyMap[key] = wrap;
      var question = wrap.querySelector('[data-rp-question="' + cssEscape(key) + '"]') || wrap.querySelector('[data-rp-question]');
      if (question) questionMap[key] = question;
    });

    toArray(form.querySelectorAll('[data-rp-question]')).forEach(function (question) {
      var source = String(question.getAttribute('data-rp-question') || '').trim();
      if (!source || !keyMap[source]) return;
      questionMap[source] = question;
      parseRules(question, issues).forEach(function (rule) {
        var target = String(rule.go_to || '').trim();
        if (!target || target === source) return;
        if (!keyMap[target]) {
          issues.push({ code: 'condition_target_missing', key: target, source: source });
          return;
        }
        rules.push({ source: source, target: target, value: rule.value });
        targets[target] = true;
      });
    });

    detectCycles(keys, rules).forEach(function (cycle) {
      issues.push({ code: 'condition_cycle', path: cycle.slice(0, 8) });
    });

    return {
      keys: keys,
      keyMap: keyMap,
      questionMap: questionMap,
      rules: rules,
      targets: targets,
      issues: issues
    };
  }

  function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(value);
    return String(value).replace(/(["\\])/g, '\\$1');
  }

  function valuesForModel(model) {
    var values = {};
    model.keys.forEach(function (key) {
      values[key] = model.questionMap[key] ? fieldValue(model.questionMap[key]) : '';
    });
    return values;
  }

  function computeVisibility(keys, rules, values) {
    var core = typedFormCore();
    if (core && core.conditions && typeof core.conditions.evaluate === 'function') {
      try {
        var typed = core.conditions.evaluate(keys, rules, values);
        if (typed && typed.visibility && typeof typed.visibility === 'object') return typed.visibility;
      } catch (error) {}
    }
    var targets = {};
    var visibility = {};
    var adjacency = {};
    var queue = [];
    var queued = {};

    keys.forEach(function (key) { adjacency[key] = []; });
    rules.forEach(function (rule) {
      if (adjacency[rule.source] && adjacency[rule.target]) {
        adjacency[rule.source].push(rule);
        targets[rule.target] = true;
      }
    });
    keys.forEach(function (key) {
      visibility[key] = !targets[key];
      if (visibility[key]) {
        queue.push(key);
        queued[key] = true;
      }
    });

    while (queue.length) {
      var source = queue.shift();
      adjacency[source].forEach(function (rule) {
        if (visibility[rule.target]) return;
        if (conditionMatches(values[source], rule.value)) {
          visibility[rule.target] = true;
          if (!queued[rule.target]) {
            queue.push(rule.target);
            queued[rule.target] = true;
          }
        }
      });
    }
    return visibility;
  }

  function computeLegacyProjection(keys, rules, values) {
    var targets = {};
    var visibility = {};
    rules.forEach(function (rule) { targets[rule.target] = true; });
    keys.forEach(function (key) { visibility[key] = !targets[key]; });
    var maxPass = Math.max(1, keys.length + rules.length + 1);
    for (var pass = 0; pass < maxPass; pass += 1) {
      var changed = false;
      rules.forEach(function (rule) {
        if (!visibility[rule.source] || visibility[rule.target]) return;
        if (conditionMatches(values[rule.source], rule.value)) {
          visibility[rule.target] = true;
          changed = true;
        }
      });
      if (!changed) break;
    }
    return visibility;
  }

  function equalVisibility(a, b, keys) {
    return keys.every(function (key) { return !!a[key] === !!b[key]; });
  }

  function setAttribute(element, name, value) {
    value = String(value);
    if (element.getAttribute(name) !== value) element.setAttribute(name, value);
  }

  function setHidden(element, hidden) {
    hidden = !!hidden;
    if (element.hidden !== hidden) element.hidden = hidden;
  }

  function setDisabled(element, disabled) {
    disabled = !!disabled;
    if (element.disabled !== disabled) element.disabled = disabled;
  }

  function setConditionalVisibility(form, wrap, show) {
    if (!wrap) return;
    show = !!show;
    setHidden(wrap, !show);
    setAttribute(wrap, 'data-rp-condition-hidden', show ? '0' : '1');
    var dailyExisting = form.getAttribute('data-rp-daily-existing') === '1';
    var dailyEditing = form.getAttribute('data-rp-daily-edit-mode') === '1';
    toArray(wrap.querySelectorAll('input,select,textarea,button')).forEach(function (control) {
      if (!show) {
        if (!control.disabled) setAttribute(control, 'data-rp-condition-disabled', '1');
        setDisabled(control, true);
      } else if (control.getAttribute('data-rp-condition-disabled') === '1') {
        if (!dailyExisting || dailyEditing) setDisabled(control, false);
        control.removeAttribute('data-rp-condition-disabled');
      }
    });
  }

  function severeModelIssues(issues) {
    var severe = {
      question_key_missing: true,
      question_key_duplicate: true,
      condition_rules_invalid_json: true,
      condition_target_missing: true,
      condition_cycle: true
    };
    return issues.filter(function (issue) { return !!severe[issue.code]; });
  }

  function applyConditions(form, options) {
    options = options || {};
    if (!form || !isPilot(form)) return null;
    try {
      var model = buildModel(form);
      var severe = severeModelIssues(model.issues);
      if (severe.length) return fallback(form, severe[0].code, severe);
      var values = valuesForModel(model);
      var visibility = computeVisibility(model.keys, model.rules, values);
      var legacyProjection = computeLegacyProjection(model.keys, model.rules, values);
      if (!equalVisibility(visibility, legacyProjection, model.keys)) {
        return fallback(form, 'projection_mismatch', [{ code: 'projection_mismatch' }]);
      }
      model.keys.forEach(function (key) {
        setConditionalVisibility(form, model.keyMap[key], visibility[key] !== false);
      });
      setAttribute(form, 'data-rp-conditional-flow-version', '3');
      if (typedFormCore()) setAttribute(form, 'data-rp-conditional-core-contract', 'fieldflow-typed-form-state-conditional-core-v1');
      setAttribute(form, 'data-rp-runtime-v3-last-source', String(options.source || 'pilot'));
      return { visibility: visibility, model: model, legacyProjection: legacyProjection };
    } catch (error) {
      return fallback(form, 'condition_runtime_exception', error);
    }
  }

  function wizardFor(form) {
    return form ? form.querySelector('.routespro-form-wizard') : null;
  }

  function directWizardGroups(wizard) {
    return toArray(wizard ? wizard.children : []).filter(function (node) {
      return node && node.nodeType === 1 && node.hasAttribute('data-rp-wizard-actions');
    });
  }

  function createButton(label, attr, secondary) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'routespro-form-btn' + (secondary ? ' secondary' : '');
    button.setAttribute(attr, '');
    button.textContent = label;
    return button;
  }

  function normaliseNavigation(form, options) {
    options = options || {};
    if (!form || !isPilot(form)) return null;
    try {
      var wizard = wizardFor(form);
      if (!wizard) return null;
      var externalSubmit = options.externalSubmit !== undefined
        ? !!options.externalSubmit
        : form.getAttribute('data-rp-external-submit') === '1';
      var allGroups = toArray(form.querySelectorAll('[data-rp-wizard-actions]'));
      var groups = directWizardGroups(wizard);
      var canonical = groups.filter(function (group) {
        return group.getAttribute('data-rp-navigation-role') === 'canonical';
      })[0] || groups.filter(function (group) {
        return group.getAttribute('data-rp-navigation-owner') === 'form-renderer';
      })[0] || groups[0] || null;

      if (!canonical) {
        canonical = document.createElement('div');
        canonical.className = 'routespro-form-wizard-actions';
        canonical.setAttribute('data-rp-wizard-actions', '');
        wizard.appendChild(canonical);
      } else if (canonical.parentNode !== wizard) {
        wizard.appendChild(canonical);
      }
      setAttribute(canonical, 'data-rp-navigation-role', 'canonical');
      setAttribute(canonical, 'data-rp-navigation-owner', 'runtime-v3');

      allGroups.forEach(function (group) {
        if (group !== canonical && group.parentNode) group.parentNode.removeChild(group);
      });
      toArray(form.querySelectorAll('[data-rp-next],[data-rp-prev],[data-rp-submit]')).forEach(function (button) {
        if (!canonical.contains(button) && button.parentNode) button.parentNode.removeChild(button);
      });

      var previous = canonical.querySelector('[data-rp-prev]');
      var next = canonical.querySelector('[data-rp-next]');
      var submit = canonical.querySelector('[data-rp-submit]');
      toArray(canonical.querySelectorAll('[data-rp-prev]')).slice(1).forEach(function (node) { node.remove(); });
      toArray(canonical.querySelectorAll('[data-rp-next]')).slice(1).forEach(function (node) { node.remove(); });
      toArray(canonical.querySelectorAll('[data-rp-submit]')).slice(1).forEach(function (node) { node.remove(); });

      if (!previous) {
        previous = createButton('Anterior', 'data-rp-prev', true);
        canonical.appendChild(previous);
      }
      if (!next) {
        next = createButton('Seguinte', 'data-rp-next', false);
        canonical.appendChild(next);
      }
      if (externalSubmit) {
        if (submit) submit.remove();
      } else if (!submit) {
        submit = createButton('Submeter', 'data-rp-submit', false);
        canonical.appendChild(submit);
      }
      return canonical;
    } catch (error) {
      fallback(form, 'navigation_runtime_exception', error);
      return null;
    }
  }

  function visibleStepIndexes(form) {
    var wizard = wizardFor(form);
    if (!wizard) return [];
    var steps = toArray(wizard.querySelectorAll('[data-rp-step]'));
    var indexes = [];
    steps.forEach(function (step) {
      var stepIndex = parseInt(step.getAttribute('data-step-index') || '0', 10) || 0;
      if (!stepIndex) return;
      var hasVisibleQuestion = toArray(step.querySelectorAll('[data-rp-question-wrap]')).some(function (wrap) {
        return !wrap.hidden && wrap.getAttribute('data-rp-condition-hidden') !== '1';
      });
      if (hasVisibleQuestion) indexes.push(stepIndex);
    });
    if (!indexes.length && steps.length) indexes.push(parseInt(steps[0].getAttribute('data-step-index') || '1', 10) || 1);
    return indexes;
  }

  function renderNavigation(form, state) {
    state = state || {};
    if (!form || !isPilot(form)) return null;
    var canonical = normaliseNavigation(form, { externalSubmit: !!state.externalSubmit });
    if (!canonical) return null;
    var visibleTotal = Math.max(1, parseInt(state.visibleTotal || 1, 10) || 1);
    var isFirst = !!state.isFirst;
    var isLast = !!state.isLast;
    var externalSubmit = !!state.externalSubmit;
    var showPrevious = visibleTotal > 1 && !isFirst;
    var showNext = visibleTotal > 1 && !isLast;
    var showSubmit = !externalSubmit && isLast;
    var previous = canonical.querySelector('[data-rp-prev]');
    var next = canonical.querySelector('[data-rp-next]');
    var submit = canonical.querySelector('[data-rp-submit]');
    var dailyLocked = form.getAttribute('data-rp-daily-existing') === '1' && form.getAttribute('data-rp-daily-edit-mode') !== '1';

    if (previous) { setHidden(previous, !showPrevious); setDisabled(previous, !showPrevious); }
    if (next) { setHidden(next, !showNext); setDisabled(next, !showNext); }
    if (submit) { setHidden(submit, !showSubmit); setDisabled(submit, !showSubmit || dailyLocked); }
    setHidden(canonical, !(showPrevious || showNext || showSubmit));
    setAttribute(canonical, 'data-rp-navigation-signature', [visibleTotal, isFirst ? 1 : 0, isLast ? 1 : 0, externalSubmit ? 1 : 0].join(':'));
    return canonical;
  }

  function actualVisibility(model) {
    var result = {};
    model.keys.forEach(function (key) { result[key] = !model.keyMap[key].hidden; });
    return result;
  }

  function stableSignature(payload) {
    var text = JSON.stringify(payload);
    var hash = 2166136261;
    for (var i = 0; i < text.length; i += 1) {
      hash ^= text.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }
    return (hash >>> 0).toString(16);
  }

  function inspect(form) {
    var model = buildModel(form);
    var issues = model.issues.slice();
    var values = valuesForModel(model);
    var expected = computeVisibility(model.keys, model.rules, values);
    var compatible = computeLegacyProjection(model.keys, model.rules, values);
    var actual = actualVisibility(model);

    if (!equalVisibility(expected, compatible, model.keys)) issues.push({ code: 'projection_mismatch' });
    if (!equalVisibility(expected, actual, model.keys)) {
      issues.push({ code: formMode(form) === 'shadow' ? 'shadow_visibility_mismatch' : 'pilot_visibility_mismatch' });
    }

    var wizard = wizardFor(form);
    var steps = wizard ? toArray(wizard.querySelectorAll('[data-rp-step]')) : [];
    var activeSteps = steps.filter(visible);
    var visibleIndexes = visibleStepIndexes(form);
    var navigationGroups = toArray(form.querySelectorAll('[data-rp-wizard-actions]'));
    var canonicalGroups = navigationGroups.filter(function (group) {
      return group.getAttribute('data-rp-navigation-role') === 'canonical';
    });
    var previousButtons = toArray(form.querySelectorAll('[data-rp-prev]'));
    var nextButtons = toArray(form.querySelectorAll('[data-rp-next]'));
    var submitButtons = toArray(form.querySelectorAll('[data-rp-submit]'));
    var externalSubmit = form.getAttribute('data-rp-external-submit') === '1';

    if (wizard) {
      if (canonicalGroups.length !== 1) issues.push({ code: 'canonical_navigation_count', count: canonicalGroups.length });
      if (navigationGroups.length !== 1) issues.push({ code: 'navigation_group_count', count: navigationGroups.length });
      if (previousButtons.length > 1) issues.push({ code: 'previous_button_duplicate', count: previousButtons.length });
      if (nextButtons.length > 1) issues.push({ code: 'next_button_duplicate', count: nextButtons.length });
      if (submitButtons.length > 1) issues.push({ code: 'submit_button_duplicate', count: submitButtons.length });
      if (externalSubmit && submitButtons.length) issues.push({ code: 'internal_submit_present_external', count: submitButtons.length });
      if (!externalSubmit && submitButtons.length !== 1) issues.push({ code: 'internal_submit_count', count: submitButtons.length });
      if (steps.length && activeSteps.length !== 1) issues.push({ code: 'active_step_count', count: activeSteps.length });

      var declaredIndex = parseInt(wizard.getAttribute('data-rp-current-step') || '0', 10) || 0;
      var domIndex = activeSteps.length === 1 ? (parseInt(activeSteps[0].getAttribute('data-step-index') || '0', 10) || 0) : 0;
      if (declaredIndex && domIndex && declaredIndex !== domIndex) issues.push({ code: 'current_step_mismatch', legacy: declaredIndex, dom: domIndex });
      if (domIndex && visibleIndexes.indexOf(domIndex) === -1) issues.push({ code: 'active_step_without_visible_questions', step: domIndex });
    }

    var snapshot = {
      runtime_version: VERSION,
      build: BUILD,
      mode: formMode(form),
      owner: formOwner(form),
      form_id: parseInt(form.getAttribute('data-routespro-form-id') || formValue(form, 'routespro_form_id') || '0', 10) || 0,
      project_id: parseInt(formValue(form, 'routespro_project_id') || '0', 10) || 0,
      route_id: parseInt(formValue(form, 'routespro_route_id') || '0', 10) || 0,
      stop_id: parseInt(formValue(form, 'routespro_stop_id') || '0', 10) || 0,
      location_id: parseInt(formValue(form, 'routespro_location_id') || '0', 10) || 0,
      question_count: model.keys.length,
      visible_question_count: model.keys.filter(function (key) { return actual[key]; }).length,
      condition_rule_count: model.rules.length,
      step_count: steps.length,
      visible_step_indexes: visibleIndexes,
      active_step_indexes: activeSteps.map(function (step) {
        return parseInt(step.getAttribute('data-step-index') || '0', 10) || 0;
      }).filter(Boolean),
      navigation_group_count: navigationGroups.length,
      canonical_navigation_count: canonicalGroups.length,
      previous_button_count: previousButtons.length,
      next_button_count: nextButtons.length,
      submit_button_count: submitButtons.length,
      external_submit: externalSubmit,
      issues: issues
    };
    snapshot.signature = stableSignature(snapshot);
    return snapshot;
  }

  function debugState(form, snapshot) {
    if (form.getAttribute('data-rp-runtime-debug-ui') !== '1') return;
    form.classList.toggle('fieldflow-runtime-v3-has-issues', snapshot.issues.length > 0);
    form.classList.toggle('fieldflow-runtime-v3-fallback', formMode(form) === 'fallback');
    setAttribute(form, 'data-rp-runtime-v3-status', snapshot.issues.length ? 'issues' : 'ok');
  }

  function diagnosticBody(form, snapshot, action, severity, errorCode) {
    var issueCodes = snapshot.issues.map(function (issue) { return issue.code; }).slice(0, 12);
    return {
      event_type: action === 'runtime_v3_pilot_fallback' ? 'form_navigation_error' : 'form_navigation_error',
      severity: severity || 'warning',
      message: 'Forms Runtime V3 ' + formMode(form) + ' detetou: ' + issueCodes.join(', '),
      platform: String(config.platform || 'web') === 'native_webview' ? 'unknown' : 'web',
      app_version: '',
      screen: 'fieldflow_form',
      action: action,
      error_code: errorCode || issueCodes[0] || 'runtime_v3_invariant',
      form_id: snapshot.form_id,
      project_id: snapshot.project_id,
      route_id: snapshot.route_id,
      stop_id: snapshot.stop_id,
      location_id: snapshot.location_id,
      step_index: snapshot.active_step_indexes[0] || 0,
      online: navigator.onLine !== false
    };
  }

  function sendDiagnostic(form, snapshot, action, severity, errorCode) {
    if ((!snapshot.issues.length && action !== 'runtime_v3_pilot_fallback') || form.getAttribute('data-rp-runtime-diagnostics') !== '1') return;
    if (!config.diagnosticsEndpoint || !window.fetch) return;
    action = action || (formMode(form) === 'pilot' ? 'runtime_v3_pilot_validation' : 'runtime_v3_shadow_validation');
    var storageKey = 'fieldflow-runtime-v3:' + action + ':' + snapshot.form_id + ':' + snapshot.signature;
    try {
      if (window.sessionStorage && window.sessionStorage.getItem(storageKey)) return;
      if (window.sessionStorage) window.sessionStorage.setItem(storageKey, '1');
    } catch (error) {}

    window.fetch(String(config.diagnosticsEndpoint), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': String(config.nonce || ''),
        'X-FieldFlow-Correlation-ID': 'ff-runtime-v3-' + snapshot.signature
      },
      body: JSON.stringify(diagnosticBody(form, snapshot, action, severity, errorCode)),
      keepalive: true
    }).catch(function () {});
  }

  function publish(form, action) {
    if (!form || ['shadow', 'pilot', 'fallback'].indexOf(formMode(form)) === -1) return null;
    var snapshot = inspect(form);
    var previous = lastSignatures.get(form);
    if (previous === snapshot.signature && !action) return snapshot;
    lastSignatures.set(form, snapshot.signature);
    setAttribute(form, 'data-rp-runtime-v3-signature', snapshot.signature);
    setAttribute(form, 'data-rp-runtime-v3-issues', String(snapshot.issues.length));
    debugState(form, snapshot);
    if (snapshot.issues.length) sendDiagnostic(form, snapshot, action || null);
    try {
      form.dispatchEvent(new CustomEvent('fieldflow:forms-runtime-v3-snapshot', {
        bubbles: true,
        detail: snapshot
      }));
    } catch (error) {}
    return snapshot;
  }

  function fallback(form, code, details) {
    if (!form) return null;
    if (formOwner(form) !== 'v3') return null;
    setAttribute(form, 'data-rp-runtime-owner', 'legacy');
    setAttribute(form, 'data-rp-runtime-mode', 'fallback');
    setAttribute(form, 'data-rp-runtime-fallback-code', String(code || 'runtime_v3_fallback'));
    form.classList.add('fieldflow-runtime-v3-fallback');
    var snapshot;
    try {
      snapshot = inspect(form);
      snapshot.issues.unshift({ code: String(code || 'runtime_v3_fallback') });
      snapshot.signature = stableSignature(snapshot);
      sendDiagnostic(form, snapshot, 'runtime_v3_pilot_fallback', 'error', String(code || 'runtime_v3_fallback'));
    } catch (error) {}
    try {
      form.dispatchEvent(new CustomEvent('fieldflow:runtime-v3-fallback', {
        bubbles: true,
        detail: { code: String(code || 'runtime_v3_fallback'), details: Array.isArray(details) ? details.slice(0, 6) : [] }
      }));
    } catch (error) {}
    return null;
  }

  function reconcile(form) {
    if (!form || !isPilot(form) || reconciling.has(form)) return publish(form);
    reconciling.add(form);
    try {
      var result = applyConditions(form, { source: 'pilot_reconcile' });
      if (!result || !isPilot(form)) return publish(form, 'runtime_v3_pilot_fallback');
      var wizard = wizardFor(form);
      if (wizard) {
        normaliseNavigation(form, { externalSubmit: form.getAttribute('data-rp-external-submit') === '1' });
        var indexes = visibleStepIndexes(form);
        var current = parseInt(wizard.getAttribute('data-rp-current-step') || '0', 10) || indexes[0] || 1;
        var position = Math.max(0, indexes.indexOf(current));
        if (indexes.indexOf(current) === -1) position = 0;
        renderNavigation(form, {
          visibleTotal: indexes.length || 1,
          isFirst: position <= 0,
          isLast: position >= Math.max(0, indexes.length - 1),
          externalSubmit: form.getAttribute('data-rp-external-submit') === '1'
        });
      }
      return publish(form);
    } catch (error) {
      fallback(form, 'pilot_reconcile_exception', error);
      return publish(form, 'runtime_v3_pilot_fallback');
    } finally {
      reconciling.delete(form);
    }
  }

  function schedule(form) {
    window.clearTimeout(timers.get(form));
    timers.set(form, window.setTimeout(function () {
      if (isPilot(form)) reconcile(form);
      else publish(form);
    }, 50));
  }

  function init(form) {
    if (!form || form.nodeType !== 1 || ['shadow', 'pilot'].indexOf(formMode(form)) === -1) return;
    if (observers.has(form)) {
      schedule(form);
      return;
    }
    setAttribute(form, 'data-rp-runtime-v3-initialized', '1');
    setAttribute(form, 'data-rp-runtime-v3-owner', formOwner(form));

    var observer = new MutationObserver(function () { schedule(form); });
    observer.observe(form, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ['hidden', 'disabled', 'data-rp-condition-hidden', 'data-rp-current-step', 'data-rp-navigation-signature', 'data-rp-runtime-owner', 'data-rp-runtime-mode']
    });
    observers.set(form, observer);
    form.addEventListener('input', function () { schedule(form); }, true);
    form.addEventListener('change', function () { schedule(form); }, true);
    form.addEventListener('routespro:wizard-step', function () { schedule(form); }, true);
    form.addEventListener('fieldflow:form-submit-intent', function () {
      publish(form, isPilot(form) ? 'runtime_v3_pilot_submit_intent' : 'runtime_v3_shadow_submit_intent');
    }, true);
    form.addEventListener('submit', function () {
      publish(form, isPilot(form) ? 'runtime_v3_pilot_submit_event' : 'runtime_v3_shadow_submit_event');
    }, true);
    schedule(form);
  }

  function scan(scope) {
    var root = scope && scope.querySelectorAll ? scope : document;
    if (root.matches && root.matches('.routespro-dyn-form[data-rp-runtime-mode="shadow"],.routespro-dyn-form[data-rp-runtime-mode="pilot"]')) init(root);
    toArray(root.querySelectorAll('.routespro-dyn-form[data-rp-runtime-mode="shadow"],.routespro-dyn-form[data-rp-runtime-mode="pilot"]')).forEach(init);
  }

  window.FieldFlowFormsRuntimeV3 = {
    version: VERSION,
    build: BUILD,
    mode: String(config.configuredMode || 'shadow'),
    owner: String(config.configuredMode || 'shadow') === 'pilot' ? 'v3' : 'legacy',
    inspect: inspect,
    snapshot: publish,
    scan: scan,
    reconcile: reconcile,
    applyConditions: applyConditions,
    normaliseNavigation: normaliseNavigation,
    visibleStepIndexes: visibleStepIndexes,
    renderNavigation: renderNavigation,
    fallback: fallback,
    computeVisibility: computeVisibility,
    computeLegacyProjection: computeLegacyProjection
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(document); });
  } else {
    scan(document);
  }

  document.addEventListener('routespro:form-rendered', function (event) {
    scan(event.detail && event.detail.form ? event.detail.form : document);
  });

  if (document.documentElement && window.MutationObserver) {
    var documentObserver = new MutationObserver(function (records) {
      records.forEach(function (record) {
        toArray(record.addedNodes).forEach(function (node) {
          if (node && node.nodeType === 1) scan(node);
        });
      });
    });
    documentObserver.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
