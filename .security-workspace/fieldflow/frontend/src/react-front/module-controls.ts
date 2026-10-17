type FieldFlowOperationalModuleKey = 'briefings' | 'implementations' | 'stocks' | 'shopping' | 'planner' | 'expenses' | 'messages' | 'academy';

type FieldFlowOperationalModuleControlType = 'search' | 'select' | 'date' | 'month';

interface FieldFlowOperationalModuleOption {
  value: string;
  label: string;
}

interface FieldFlowOperationalModuleControl {
  key: string;
  label: string;
  type: FieldFlowOperationalModuleControlType;
  value: string;
  placeholder?: string;
  disabled?: boolean;
  options?: FieldFlowOperationalModuleOption[];
}

interface FieldFlowOperationalModuleChip {
  value: string;
  label: string;
  active: boolean;
}

interface FieldFlowOperationalModuleSnapshot {
  module: FieldFlowOperationalModuleKey;
  mode: string;
  title: string;
  summary: string;
  controls: FieldFlowOperationalModuleControl[];
  chips: FieldFlowOperationalModuleChip[];
  pageLabel: string;
  prevDisabled: boolean;
  nextDisabled: boolean;
  canRefresh: boolean;
  canApply: boolean;
  canClear: boolean;
  canBack: boolean;
}

const FIELD_FLOW_REACT_OPERATIONAL_MODULES_CONTRACT = 'fieldflow-react-operational-modules-v1';
const FIELD_FLOW_REACT_OPERATIONAL_MODULES_V2_CONTRACT = 'fieldflow-react-operational-modules-v2';
const FIELD_FLOW_OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1';
const FIELD_FLOW_OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1';
const FIELD_FLOW_REACT_OPERATIONAL_ACTIONS_CONTRACT = 'fieldflow-react-operational-actions-hardening-v1';
const FIELD_FLOW_REACT_CLIENT_WAVE1_CONTRACT = 'fieldflow-react-client-wave1-v1';
const FIELD_FLOW_REACT_CLIENT_WAVE2_CONTRACT = 'fieldflow-react-client-wave2-v1';
const FIELD_FLOW_CROSS_PORTAL_RENDER_ISOLATION_CONTRACT = 'fieldflow-cross-portal-render-isolation-v1';
const FIELD_FLOW_CLIENT_PANEL_LIFECYCLE_CONTRACT = 'fieldflow-client-panel-lifecycle-v1';

function fieldFlowOperationalModulePortalScope(moduleRoot: Element | null): 'client' | 'operative' | 'standalone' {
  if (!moduleRoot || typeof moduleRoot.closest !== 'function') return 'standalone';
  if (moduleRoot.closest('.rp-client-premium[data-fieldflow-portal="client"],.rp-client-premium')) return 'client';
  if (moduleRoot.closest('.rp-premium-shell[data-fieldflow-portal="operative"],.rp-premium-shell.rp-app')) return 'operative';
  return 'standalone';
}

function fieldFlowOperationalModuleText(element: Element | null): string {
  return String(element?.textContent || '').replace(/\s+/g, ' ').trim();
}

function fieldFlowOperationalModuleInput(root: Element, selector: string): HTMLInputElement | null {
  const element = root.querySelector(selector);
  return element instanceof HTMLInputElement ? element : null;
}

function fieldFlowOperationalModuleSelect(root: Element, selector: string): HTMLSelectElement | null {
  const element = root.querySelector(selector);
  return element instanceof HTMLSelectElement ? element : null;
}

function fieldFlowOperationalModuleOptions(select: HTMLSelectElement | null): FieldFlowOperationalModuleOption[] {
  if (!select) return [];
  return Array.from(select.options).map((option) => ({
    value: String(option.value || ''),
    label: String(option.textContent || option.value || ''),
  }));
}

function fieldFlowOperationalModuleSelectControl(root: Element, key: string, label: string, selector: string): FieldFlowOperationalModuleControl | null {
  const select = fieldFlowOperationalModuleSelect(root, selector);
  if (!select) return null;
  return {
    key,
    label,
    type: 'select',
    value: String(select.value || ''),
    disabled: Boolean(select.disabled),
    options: fieldFlowOperationalModuleOptions(select),
  };
}

function fieldFlowOperationalModuleInputControl(root: Element, key: string, label: string, selector: string, type: FieldFlowOperationalModuleControlType, placeholder = ''): FieldFlowOperationalModuleControl | null {
  const input = fieldFlowOperationalModuleInput(root, selector);
  if (!input) return null;
  return {
    key,
    label,
    type,
    value: String(input.value || ''),
    placeholder: String(input.placeholder || placeholder || ''),
    disabled: Boolean(input.disabled),
  };
}

function fieldFlowOperationalModuleVisibleCount(root: Element, selector: string): { visible: number; total: number } {
  const rows = Array.from(root.querySelectorAll(selector));
  const visible = rows.filter((row) => {
    const element = row as HTMLElement;
    return !element.hidden
      && !element.classList.contains('is-hidden')
      && !element.classList.contains('ff-react-module-search-hidden');
  }).length;
  return { visible, total: rows.length };
}

function fieldFlowOperationalModuleSnapshot(props: FieldFlowOperationalModuleProps): FieldFlowOperationalModuleSnapshot {
  const moduleRoot = document.getElementById(props.rootId);
  const empty: FieldFlowOperationalModuleSnapshot = {
    module: props.module,
    mode: 'default',
    title: props.label || 'Operação',
    summary: 'A preparar controlos…',
    controls: [],
    chips: [],
    pageLabel: '',
    prevDisabled: true,
    nextDisabled: true,
    canRefresh: false,
    canApply: false,
    canClear: false,
    canBack: false,
  };
  if (!moduleRoot) return empty;

  if (props.module === 'briefings') {
    const search = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="briefings"]');
    const count = fieldFlowOperationalModuleVisibleCount(moduleRoot, '.ffb-briefing');
    return {
      ...empty,
      title: 'Briefings',
      summary: count.total ? `${count.visible} de ${count.total} briefing(s) visíveis` : fieldFlowOperationalModuleText(moduleRoot.querySelector('[data-status]')) || 'Briefings operacionais',
      controls: [{ key: 'search', label: 'Pesquisar', type: 'search', value: String(search?.value || ''), placeholder: 'Título, semana, conteúdo ou loja' }],
      canRefresh: Boolean(moduleRoot.querySelector('[data-refresh]')),
      canClear: Boolean(search?.value),
    };
  }

  if (props.module === 'implementations') {
    const search = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="implementations"]');
    const nativeChips = Array.from(moduleRoot.querySelectorAll('[data-filter]')) as HTMLElement[];
    const count = fieldFlowOperationalModuleVisibleCount(moduleRoot, '[data-item]');
    return {
      ...empty,
      title: 'Implementações',
      summary: count.total ? `${count.visible} de ${count.total} loja(s) visíveis` : fieldFlowOperationalModuleText(moduleRoot.querySelector('[data-status]')) || 'Implementações operacionais',
      controls: [{ key: 'search', label: 'Pesquisar', type: 'search', value: String(search?.value || ''), placeholder: 'Loja, material, estado ou referência' }],
      chips: nativeChips.map((button) => ({
        value: String(button.dataset.filter || 'all'),
        label: fieldFlowOperationalModuleText(button),
        active: button.classList.contains('active'),
      })),
      canRefresh: Boolean(moduleRoot.querySelector('[data-refresh]')),
      canClear: Boolean(search?.value),
    };
  }

  if (props.module === 'stocks') {
    const controls: FieldFlowOperationalModuleControl[] = [];
    if (moduleRoot.getAttribute('data-mode') === 'client') {
      const riskSearch = fieldFlowOperationalModuleInputControl(moduleRoot, 'risk-search', 'Pesquisar risco', '[data-risk-search]', 'search', 'Loja, produto ou EAN');
      if (riskSearch) controls.push(riskSearch);
      const risks = fieldFlowOperationalModuleVisibleCount(moduleRoot, '.ff-stock-risk');
      return {
        ...empty,
        title: 'Stocks',
        summary: risks.total ? `${risks.visible} de ${risks.total} risco(s) visíveis` : fieldFlowOperationalModuleText(moduleRoot.querySelector('[data-state]')) || 'Visão de risco de stocks',
        controls,
        canClear: Boolean(riskSearch?.value),
      };
    }
    const insignia = fieldFlowOperationalModuleSelectControl(moduleRoot, 'insignia', 'Insígnia', '[data-insignia-filter]');
    const store = fieldFlowOperationalModuleSelectControl(moduleRoot, 'store', 'Loja', '[data-store-filter]');
    const search = fieldFlowOperationalModuleInputControl(moduleRoot, 'search', 'Produto', '[data-search]', 'search', 'Produto, EAN ou artigo');
    if (insignia) controls.push(insignia);
    if (store) controls.push(store);
    if (search) controls.push(search);
    const stores = fieldFlowOperationalModuleVisibleCount(moduleRoot, '.ff-stock-store');
    return {
      ...empty,
      title: 'Stocks',
      summary: stores.total ? `${stores.visible} de ${stores.total} loja(s) visíveis` : fieldFlowOperationalModuleText(moduleRoot.querySelector('[data-meta]')) || 'Último snapshot publicado',
      controls,
      canClear: controls.some((control) => control.value !== ''),
    };
  }

  if (props.module === 'shopping') {
    const formView = moduleRoot.querySelector('.ffs-form-view') as HTMLElement | null;
    const inForm = Boolean(formView && !formView.hidden);
    if (inForm) {
      const controls: FieldFlowOperationalModuleControl[] = [];
      const store = fieldFlowOperationalModuleSelectControl(moduleRoot, 'store', 'Loja', '.ffs-store-select');
      const search = fieldFlowOperationalModuleInputControl(moduleRoot, 'product-search', 'Produto', '.ffs-search', 'search', 'Pesquisar artigo ou tipo');
      if (store) controls.push(store);
      if (search) controls.push(search);
      const chips = Array.from(moduleRoot.querySelectorAll('[data-view-mode]')).map((button) => ({
        value: String((button as HTMLElement).dataset.viewMode || ''),
        label: fieldFlowOperationalModuleText(button),
        active: (button as HTMLElement).classList.contains('active'),
      }));
      const products = fieldFlowOperationalModuleVisibleCount(moduleRoot, '.ffs-price-row');
      return {
        ...empty,
        mode: 'form',
        title: 'Shopping',
        summary: products.total ? `${products.visible} de ${products.total} artigo(s) visíveis` : fieldFlowOperationalModuleText(moduleRoot.querySelector('.ffs-form-meta')) || 'Verificação de preços',
        controls,
        chips,
        canBack: Boolean(moduleRoot.querySelector('.ffs-back')),
        canClear: Boolean(search?.value),
      };
    }
    const search = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="shopping"]');
    const count = fieldFlowOperationalModuleVisibleCount(moduleRoot, '.ffs-list .ffs-card');
    return {
      ...empty,
      mode: 'list',
      title: 'Shopping',
      summary: count.total ? `${count.visible} de ${count.total} insígnia(s) visíveis` : 'Verificação de preços por insígnia',
      controls: [{ key: 'assignment-search', label: 'Pesquisar', type: 'search', value: String(search?.value || ''), placeholder: 'Insígnia, campanha ou cliente' }],
      canRefresh: Boolean(moduleRoot.querySelector('.ffs-refresh')),
      canClear: Boolean(search?.value),
    };
  }

  if (props.module === 'planner') {
    const controls: FieldFlowOperationalModuleControl[] = [];
    const client = fieldFlowOperationalModuleSelectControl(moduleRoot, 'client', 'Cliente', '[data-client]');
    const project = fieldFlowOperationalModuleSelectControl(moduleRoot, 'project', 'Campanha', '[data-project]');
    const period = fieldFlowOperationalModuleSelectControl(moduleRoot, 'period', 'Período', '[data-period]');
    const anchor = fieldFlowOperationalModuleInputControl(moduleRoot, 'anchor', 'Data de referência', '[data-anchor]', 'date');
    const storeSearch = fieldFlowOperationalModuleInputControl(moduleRoot, 'store-search', 'Pesquisar lojas', '[data-search]', 'search', 'Loja, localidade ou morada');
    const planSearch = fieldFlowOperationalModuleInputControl(moduleRoot, 'plan-search', 'Pesquisar plano', '[data-plan-search]', 'search', 'Dia ou loja planeada');
    if (client) controls.push(client);
    if (project) controls.push(project);
    if (period) controls.push(period);
    if (anchor) controls.push(anchor);
    if (storeSearch) controls.push(storeSearch);
    if (planSearch) controls.push(planSearch);
    const chips = Array.from(moduleRoot.querySelectorAll('[data-insignia-tabs] [data-insignia]')).map((button) => ({
      value: String((button as HTMLElement).dataset.insignia || 'all'),
      label: fieldFlowOperationalModuleText(button),
      active: (button as HTMLElement).classList.contains('active'),
    }));
    return {
      ...empty,
      title: 'Planear',
      summary: fieldFlowOperationalModuleText(moduleRoot.querySelector('[data-insignia-meta]')) || fieldFlowOperationalModuleText(moduleRoot.querySelector('[data-status]')) || 'Planeamento pessoal',
      controls,
      chips,
      canRefresh: Boolean(moduleRoot.querySelector('[data-load]')),
      canClear: controls.some((control) => control.key.includes('search') && control.value !== ''),
    };
  }

  if (props.module === 'academy') {
    const search = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="academy"]');
    const count = fieldFlowOperationalModuleVisibleCount(moduleRoot, '.ffp-course-card');
    const progress = fieldFlowOperationalModuleText(moduleRoot.querySelector('.ffp-academy-kpi:last-child strong'));
    return {
      ...empty,
      title: 'Academy',
      summary: count.total ? `${count.visible} de ${count.total} curso(s) visíveis${progress ? ` · progresso ${progress}` : ''}` : 'Formação e certificação operacional',
      controls: [{ key: 'search', label: 'Pesquisar', type: 'search', value: String(search?.value || ''), placeholder: 'Curso, conteúdo ou estado' }],
      canClear: Boolean(search?.value),
    };
  }

  if (props.module === 'expenses') {
    const controls: FieldFlowOperationalModuleControl[] = [];
    const month = fieldFlowOperationalModuleInputControl(moduleRoot, 'month', 'Mês', '.ffe-filter-month', 'month');
    const category = fieldFlowOperationalModuleSelectControl(moduleRoot, 'category', 'Categoria', '.ffe-filter-category');
    const status = fieldFlowOperationalModuleSelectControl(moduleRoot, 'status', 'Estado', '.ffe-filter-status');
    const from = fieldFlowOperationalModuleInputControl(moduleRoot, 'from', 'Desde', '.ffe-filter-from', 'date');
    const to = fieldFlowOperationalModuleInputControl(moduleRoot, 'to', 'Até', '.ffe-filter-to', 'date');
    const search = fieldFlowOperationalModuleInputControl(moduleRoot, 'search', 'Pesquisar', '.ffe-filter-search', 'search', 'Fornecedor ou documento');
    [month, category, status, from, to, search].forEach((control) => { if (control) controls.push(control); });
    const page = moduleRoot.querySelector('.ffe-page');
    const prev = moduleRoot.querySelector('.ffe-prev') as HTMLButtonElement | null;
    const next = moduleRoot.querySelector('.ffe-next') as HTMLButtonElement | null;
    return {
      ...empty,
      title: 'Despesas',
      summary: `${fieldFlowOperationalModuleText(moduleRoot.querySelector('.ffe-count')) || '0'} registo(s) · ${fieldFlowOperationalModuleText(moduleRoot.querySelector('.ffe-total')) || '0,00 €'}`,
      controls,
      pageLabel: fieldFlowOperationalModuleText(page),
      prevDisabled: Boolean(prev?.disabled),
      nextDisabled: Boolean(next?.disabled),
      canApply: Boolean(moduleRoot.querySelector('.ffe-filter-apply')),
      canClear: controls.some((control) => control.value !== ''),
    };
  }

  const search = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="messages"]');
  const controls: FieldFlowOperationalModuleControl[] = [];
  const client = fieldFlowOperationalModuleSelectControl(moduleRoot, 'client', 'Cliente', '#rpti-client');
  const project = fieldFlowOperationalModuleSelectControl(moduleRoot, 'project', 'Campanha', '#rpti-project');
  const status = fieldFlowOperationalModuleSelectControl(moduleRoot, 'status', 'Estado', '#rpti-filter-status');
  if (client) controls.push(client);
  if (project) controls.push(project);
  if (status) controls.push(status);
  controls.push({ key: 'search', label: 'Pesquisar', type: 'search', value: String(search?.value || ''), placeholder: 'Assunto, remetente ou conteúdo' });
  const count = fieldFlowOperationalModuleVisibleCount(moduleRoot, '.rp-mail-item');
  return {
    ...empty,
    title: 'Mensagens',
    summary: count.total ? `${count.visible} de ${count.total} mensagem(ns) visíveis` : fieldFlowOperationalModuleText(moduleRoot.querySelector('#rpti-status-note')) || 'Inbox operacional',
    controls,
    canRefresh: Boolean(moduleRoot.querySelector('#rpti-refresh')),
    canClear: controls.some((control) => control.value !== ''),
  };
}

function fieldFlowOperationalModuleDispatch(element: HTMLInputElement | HTMLSelectElement, eventName: 'input' | 'change'): void {
  element.dispatchEvent(new Event(eventName, { bubbles: true }));
}

function fieldFlowOperationalModuleNotify(props: FieldFlowOperationalModuleProps): void {
  window.dispatchEvent(new CustomEvent('fieldflow:operational-module-refresh', { detail: { rootId: props.rootId, module: props.module } }));
}

function fieldFlowOperationalModuleClientSearch(moduleRoot: Element, selector: string, value: string): void {
  const query = String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
  moduleRoot.querySelectorAll(selector).forEach((row) => {
    const hay = fieldFlowOperationalModuleText(row).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    row.classList.toggle('ff-react-module-search-hidden', Boolean(query) && !hay.includes(query));
  });
}

function fieldFlowOperationalModuleReconcileImplementationVisibility(moduleRoot: Element): void {
  moduleRoot.querySelectorAll('[data-section]').forEach((section) => {
    const items = Array.from(section.querySelectorAll('[data-item]')) as HTMLElement[];
    const visible = items.filter((item) => !item.hidden && !item.classList.contains('is-hidden') && !item.classList.contains('ff-react-module-search-hidden')).length;
    section.classList.toggle('is-empty', visible === 0);
    const count = section.querySelector('[data-visible-count]');
    if (count) count.textContent = `${visible} loja${visible === 1 ? '' : 's'}`;
  });
}

function fieldFlowOperationalModuleWrite(props: FieldFlowOperationalModuleProps, key: string, value: string): void {
  const moduleRoot = document.getElementById(props.rootId);
  if (!moduleRoot) return;

  const writeInput = (selector: string, eventName: 'input' | 'change'): boolean => {
    const input = moduleRoot.querySelector(selector);
    if (!(input instanceof HTMLInputElement) && !(input instanceof HTMLSelectElement)) return false;
    input.value = value;
    fieldFlowOperationalModuleDispatch(input, eventName);
    return true;
  };

  if (props.module === 'briefings' && key === 'search') {
    let input = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="briefings"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'search';
      input.hidden = true;
      input.setAttribute('data-fieldflow-react-module-search', 'briefings');
      moduleRoot.appendChild(input);
    }
    input.value = value;
    fieldFlowOperationalModuleClientSearch(moduleRoot, '.ffb-briefing', value);
    fieldFlowOperationalModuleNotify(props);
    return;
  }

  if (props.module === 'implementations' && key === 'search') {
    let input = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="implementations"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'search';
      input.hidden = true;
      input.setAttribute('data-fieldflow-react-module-search', 'implementations');
      moduleRoot.appendChild(input);
    }
    input.value = value;
    fieldFlowOperationalModuleClientSearch(moduleRoot, '[data-item]', value);
    fieldFlowOperationalModuleReconcileImplementationVisibility(moduleRoot);
    fieldFlowOperationalModuleNotify(props);
    return;
  }

  if (props.module === 'stocks') {
    if (key === 'risk-search') writeInput('[data-risk-search]', 'input');
    else if (key === 'insignia') writeInput('[data-insignia-filter]', 'change');
    else if (key === 'store') writeInput('[data-store-filter]', 'change');
    else if (key === 'search') writeInput('[data-search]', 'input');
    return;
  }

  if (props.module === 'shopping') {
    const formView = moduleRoot.querySelector('.ffs-form-view') as HTMLElement | null;
    const inForm = Boolean(formView && !formView.hidden);
    if (!inForm && key === 'assignment-search') {
      let input = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="shopping"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'search';
        input.hidden = true;
        input.setAttribute('data-fieldflow-react-module-search', 'shopping');
        moduleRoot.appendChild(input);
      }
      input.value = value;
      fieldFlowOperationalModuleClientSearch(moduleRoot, '.ffs-list .ffs-card', value);
      fieldFlowOperationalModuleNotify(props);
    } else if (inForm && key === 'store') writeInput('.ffs-store-select', 'change');
    else if (inForm && key === 'product-search') writeInput('.ffs-search', 'input');
    return;
  }

  if (props.module === 'planner') {
    if (key === 'client') writeInput('[data-client]', 'change');
    else if (key === 'project') writeInput('[data-project]', 'change');
    else if (key === 'period') writeInput('[data-period]', 'change');
    else if (key === 'anchor') writeInput('[data-anchor]', 'change');
    else if (key === 'store-search') writeInput('[data-search]', 'input');
    else if (key === 'plan-search') writeInput('[data-plan-search]', 'input');
    return;
  }

  if (props.module === 'academy' && key === 'search') {
    let input = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="academy"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'search';
      input.hidden = true;
      input.setAttribute('data-fieldflow-react-module-search', 'academy');
      moduleRoot.appendChild(input);
    }
    input.value = value;
    fieldFlowOperationalModuleClientSearch(moduleRoot, '.ffp-course-card', value);
    fieldFlowOperationalModuleNotify(props);
    return;
  }

  if (props.module === 'expenses') {
    const selectors: Record<string, string> = {
      month: '.ffe-filter-month', category: '.ffe-filter-category', status: '.ffe-filter-status', from: '.ffe-filter-from', to: '.ffe-filter-to', search: '.ffe-filter-search',
    };
    const selector = selectors[key];
    if (selector) writeInput(selector, key === 'category' || key === 'status' ? 'change' : 'input');
    return;
  }

  if (props.module === 'messages') {
    if (key === 'client') writeInput('#rpti-client', 'change');
    else if (key === 'project') writeInput('#rpti-project', 'change');
    else if (key === 'status') writeInput('#rpti-filter-status', 'change');
    else if (key === 'search') {
      let input = fieldFlowOperationalModuleInput(moduleRoot, '[data-fieldflow-react-module-search="messages"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'search';
        input.hidden = true;
        input.setAttribute('data-fieldflow-react-module-search', 'messages');
        moduleRoot.appendChild(input);
      }
      input.value = value;
      fieldFlowOperationalModuleClientSearch(moduleRoot, '.rp-mail-item', value);
      fieldFlowOperationalModuleNotify(props);
    }
  }
}

function fieldFlowOperationalModuleSelectorValue(value: string): string {
  return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function fieldFlowOperationalModuleClickChip(props: FieldFlowOperationalModuleProps, value: string): void {
  const moduleRoot = document.getElementById(props.rootId);
  if (!moduleRoot) return;
  const safeValue = fieldFlowOperationalModuleSelectorValue(value);
  let selector = '';
  if (props.module === 'implementations') selector = `[data-filter="${safeValue}"]`;
  else if (props.module === 'shopping') selector = `[data-view-mode="${safeValue}"]`;
  else if (props.module === 'planner') selector = `[data-insignia-tabs] [data-insignia="${safeValue}"]`;
  if (!selector) return;
  const button = moduleRoot.querySelector(selector) as HTMLElement | null;
  button?.click();
  if (props.module === 'implementations') {
    queueMicrotask(() => fieldFlowOperationalModuleReconcileImplementationVisibility(moduleRoot));
  }
}

function fieldFlowOperationalModuleClickAction(props: FieldFlowOperationalModuleProps, action: string): void {
  const moduleRoot = document.getElementById(props.rootId);
  if (!moduleRoot) return;
  const selectorMap: Partial<Record<FieldFlowOperationalModuleKey, Partial<Record<string, string>>>> = {
    briefings: { refresh: '[data-refresh]' },
    implementations: { refresh: '[data-refresh]' },
    shopping: { refresh: '.ffs-refresh', back: '.ffs-back' },
    planner: { refresh: '[data-load]' },
    expenses: { apply: '.ffe-filter-apply', clear: '.ffe-filter-clear', prev: '.ffe-prev', next: '.ffe-next' },
    messages: { refresh: '#rpti-refresh' },
  };
  const selector = selectorMap[props.module]?.[action];
  if (selector) (moduleRoot.querySelector(selector) as HTMLElement | null)?.click();
}

function fieldFlowOperationalModuleClear(props: FieldFlowOperationalModuleProps): void {
  const snapshot = fieldFlowOperationalModuleSnapshot(props);
  snapshot.controls.forEach((control) => {
    if (control.type === 'search' || props.module === 'expenses') fieldFlowOperationalModuleWrite(props, control.key, '');
  });
  if (props.module === 'stocks') {
    fieldFlowOperationalModuleWrite(props, 'insignia', '');
    fieldFlowOperationalModuleWrite(props, 'store', '');
    fieldFlowOperationalModuleWrite(props, 'search', '');
  }
  if (props.module === 'messages') {
    fieldFlowOperationalModuleWrite(props, 'client', '');
    fieldFlowOperationalModuleWrite(props, 'project', '');
    fieldFlowOperationalModuleWrite(props, 'status', '');
    fieldFlowOperationalModuleWrite(props, 'search', '');
  }
  if (props.module === 'shopping' && snapshot.mode === 'form') fieldFlowOperationalModuleWrite(props, 'product-search', '');
  if (props.module === 'expenses') fieldFlowOperationalModuleClickAction(props, 'clear');
}

function fieldFlowOperationalModuleNativeSelectors(module: FieldFlowOperationalModuleKey): string[] {
  if (module === 'briefings') return ['.ffb-front-toolbar'];
  if (module === 'implementations') return ['[data-refresh]', '[data-filters]'];
  if (module === 'stocks') return ['.ff-stock-toolbar', '.ff-stock-inline-search'];
  if (module === 'shopping') return ['.ffs-list-view .ffs-refresh', '.ffs-form-view .ffs-back', '.ffs-form-view .ffs-store-card', '.ffs-workspace .ffs-toolbar', '.ffs-workspace .ffs-view-switch'];
  if (module === 'planner') return ['.ffp-react-primary-filters', '.ffp-store-toolbar', '[data-load]'];
  if (module === 'expenses') return ['.ffe-filters', '.ffe-filter-apply', '.ffe-filter-clear', '.ffe-pagination'];
  if (module === 'academy') return [];
  return ['.rp-mail-toolbar'];
}

function FieldFlowOperationalModuleControlsIsland(props: FieldFlowOperationalModuleProps & { root: Element }): unknown {
  const { root } = props;
  const element = window.wp?.element;
  if (!element) return null;
  const initialModuleRoot = document.getElementById(props.rootId);
  const portalScope = fieldFlowOperationalModulePortalScope(initialModuleRoot);
  const clientWave1 = portalScope === 'client' && ['stocks', 'shopping', 'messages'].includes(props.module) && props.clientWave1Contract === FIELD_FLOW_REACT_CLIENT_WAVE1_CONTRACT;
  const clientWave2 = portalScope === 'client' && ['briefings', 'implementations', 'planner', 'expenses'].includes(props.module) && props.clientWave2Contract === FIELD_FLOW_REACT_CLIENT_WAVE2_CONTRACT;
  const clientWave = clientWave1 || clientWave2;
  const v2Contract = props.operationalModulesV2Contract === FIELD_FLOW_REACT_OPERATIONAL_MODULES_V2_CONTRACT;
  const wave1Modernized = ['briefings', 'stocks', 'shopping', 'messages'].includes(props.module) && props.operationalModernizationContract === FIELD_FLOW_OPERATIONAL_MODERNIZATION_CONTRACT;
  const wave2Modernized = ['academy', 'expenses'].includes(props.module) && props.operationalModernizationWave2Contract === FIELD_FLOW_OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT;
  const modernized = wave1Modernized || wave2Modernized;
  const waveLabel = clientWave2 ? 'React Client Wave 2' : (clientWave1 ? 'React Client Wave 1' : 'Área operacional');
  const [snapshot, setSnapshot] = element.useState<FieldFlowOperationalModuleSnapshot>(fieldFlowOperationalModuleSnapshot(props));
  const [waveStatus, setWaveStatus] = element.useState<string>(clientWave ? 'ready' : 'legacy');
  root.setAttribute('data-fieldflow-portal-scope', portalScope);
  if (props.crossPortalIsolationContract === FIELD_FLOW_CROSS_PORTAL_RENDER_ISOLATION_CONTRACT) root.setAttribute('data-fieldflow-cross-portal-isolation', FIELD_FLOW_CROSS_PORTAL_RENDER_ISOLATION_CONTRACT);

  element.useEffect(() => {
    const moduleRoot = document.getElementById(props.rootId);
    if (!moduleRoot) return;
    const clientPortal = portalScope === 'client' ? moduleRoot.closest('.rp-client-premium') : null;
    const nativeSelectors = fieldFlowOperationalModuleNativeSelectors(props.module);
    const markNative = (enabled: boolean): void => {
      nativeSelectors.forEach((selector) => moduleRoot.querySelectorAll(selector).forEach((node) => {
        if (enabled) node.setAttribute('data-fieldflow-react-operational-native', '1');
        else node.removeAttribute('data-fieldflow-react-operational-native');
      }));
    };
    markNative(true);
    if (v2Contract) {
      moduleRoot.setAttribute('data-fieldflow-react-module-owner', wave2Modernized ? 'react-event-shell-9q' : (wave1Modernized ? 'react-event-shell-9o' : 'react-controls-v2'));
      moduleRoot.setAttribute('data-fieldflow-react-module-contract', wave2Modernized ? FIELD_FLOW_OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT : (wave1Modernized ? FIELD_FLOW_OPERATIONAL_MODERNIZATION_CONTRACT : FIELD_FLOW_REACT_OPERATIONAL_MODULES_V2_CONTRACT));
      if (wave1Modernized) moduleRoot.setAttribute('data-fieldflow-operational-modernization', FIELD_FLOW_OPERATIONAL_MODERNIZATION_CONTRACT);
      if (wave2Modernized) moduleRoot.setAttribute('data-fieldflow-operational-modernization-wave2', FIELD_FLOW_OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT);
    }
    let signature = '';
    let scheduled = false;
    const refresh = (): void => {
      if (scheduled) return;
      scheduled = true;
      queueMicrotask(() => {
        scheduled = false;
        markNative(true);
        const next = fieldFlowOperationalModuleSnapshot(props);
        const nextSignature = JSON.stringify(next);
        if (nextSignature !== signature) {
          signature = nextSignature;
          setSnapshot(next);
        }
      });
    };
    signature = JSON.stringify(fieldFlowOperationalModuleSnapshot(props));
    let observer: MutationObserver | null = null;
    if (!modernized) {
      observer = new MutationObserver((records) => {
        if (records.every((record) => root.contains(record.target))) return;
        refresh();
      });
      observer.observe(moduleRoot, { subtree: true, childList: true, attributes: true, characterData: true, attributeFilter: ['class', 'hidden', 'disabled', 'value', 'aria-selected'] });
    }
    const eventRefresh = (event: Event): void => {
      if (event.target instanceof Node && root.contains(event.target)) return;
      refresh();
    };
    moduleRoot.addEventListener('input', eventRefresh, true);
    moduleRoot.addEventListener('change', eventRefresh, true);
    moduleRoot.addEventListener('click', eventRefresh, true);
    const panelLifecycle = (event: Event): void => {
      if (!clientWave) return;
      const detail = (event as CustomEvent<{ panel?: string; status?: string; contract?: string }>).detail || {};
      if (detail.contract && detail.contract !== FIELD_FLOW_CLIENT_PANEL_LIFECYCLE_CONTRACT) return;
      const panel = moduleRoot.closest('.rp-panel[data-panel]') as HTMLElement | null;
      if (!panel || String(detail.panel || '') !== String(panel.dataset.panel || '')) return;
      const status = String(detail.status || 'ready');
      setWaveStatus(status === 'loading' || status === 'mount' ? 'loading' : 'ready');
    };
    const nativeRefresh = (event: Event): void => {
      if (!clientWave) return;
      const target = event.target as HTMLElement | null;
      if (!target || !target.closest('button,.rp-btn,.rp-small,a')) return;
      const text = fieldFlowOperationalModuleText(target).toLowerCase();
      if (!/(atualizar|refresh|recarregar)/.test(text)) return;
      setWaveStatus('refreshing');
      window.setTimeout(() => setWaveStatus('ready'), 900);
    };
    const explicitRefresh = (event: Event): void => {
      if (!v2Contract) return;
      const detail = (event as CustomEvent<{ rootId?: string; module?: string }>).detail || {};
      if (detail.rootId && String(detail.rootId) !== String(props.rootId)) return;
      if (detail.module && String(detail.module) !== String(props.module)) return;
      refresh();
    };
    if (clientPortal) {
      clientPortal.addEventListener('fieldflow:lazy-panel-loaded', refresh as EventListener);
      clientPortal.addEventListener('fieldflow:client-panel-lifecycle', panelLifecycle as EventListener);
    }
    window.addEventListener('fieldflow:operational-module-refresh', explicitRefresh as EventListener);
    moduleRoot.addEventListener('click', nativeRefresh, true);
    refresh();
    return () => {
      observer?.disconnect();
      moduleRoot.removeEventListener('input', eventRefresh, true);
      moduleRoot.removeEventListener('change', eventRefresh, true);
      moduleRoot.removeEventListener('click', eventRefresh, true);
      moduleRoot.removeEventListener('click', nativeRefresh, true);
      if (clientPortal) {
        clientPortal.removeEventListener('fieldflow:lazy-panel-loaded', refresh as EventListener);
        clientPortal.removeEventListener('fieldflow:client-panel-lifecycle', panelLifecycle as EventListener);
      }
      window.removeEventListener('fieldflow:operational-module-refresh', explicitRefresh as EventListener);
      if (v2Contract) {
        moduleRoot.removeAttribute('data-fieldflow-react-module-owner');
        moduleRoot.removeAttribute('data-fieldflow-react-module-contract');
        moduleRoot.removeAttribute('data-fieldflow-operational-modernization');
        moduleRoot.removeAttribute('data-fieldflow-operational-modernization-wave2');
      }
      markNative(false);
    };
  }, [props.rootId, props.module, clientWave, portalScope, v2Contract, modernized, wave1Modernized, wave2Modernized]);

  const controlNodes = snapshot.controls.map((control) => {
    if (control.type === 'select') {
      return element.createElement('label', { className: 'ff-react-module-field', key: control.key },
        element.createElement('span', null, control.label),
        element.createElement('select', {
          value: control.value,
          disabled: control.disabled,
          onChange: (event: unknown) => fieldFlowOperationalModuleWrite(props, control.key, String((event as { target?: { value?: string } }).target?.value || '')),
        }, ...(control.options || []).map((option) => element.createElement('option', { key: option.value, value: option.value }, option.label)))
      );
    }
    return element.createElement('label', { className: 'ff-react-module-field', key: control.key },
      element.createElement('span', null, control.label),
      element.createElement('input', {
        type: control.type,
        value: control.value,
        placeholder: control.placeholder || '',
        disabled: control.disabled,
        onInput: (event: unknown) => fieldFlowOperationalModuleWrite(props, control.key, String((event as { target?: { value?: string } }).target?.value || '')),
      })
    );
  });

  const chipNodes = snapshot.chips.length ? element.createElement('div', { className: 'ff-react-module-chips', role: 'group', 'aria-label': 'Filtros rápidos' },
    ...snapshot.chips.map((chip) => element.createElement('button', {
      type: 'button',
      className: chip.active ? 'is-active' : '',
      'aria-pressed': chip.active ? 'true' : 'false',
      onClick: () => fieldFlowOperationalModuleClickChip(props, chip.value),
      key: chip.value,
    }, chip.label))
  ) : null;

  const actions: unknown[] = [];
  if (snapshot.canBack) actions.push(element.createElement('button', { type: 'button', className: 'is-ghost', onClick: () => fieldFlowOperationalModuleClickAction(props, 'back'), key: 'back' }, 'Voltar'));
  if (snapshot.canRefresh) actions.push(element.createElement('button', { type: 'button', className: 'is-ghost', onClick: () => fieldFlowOperationalModuleClickAction(props, 'refresh'), key: 'refresh' }, props.module === 'planner' ? 'Carregar lojas' : 'Atualizar'));
  if (snapshot.canClear) actions.push(element.createElement('button', { type: 'button', className: 'is-ghost', onClick: () => fieldFlowOperationalModuleClear(props), key: 'clear' }, 'Limpar'));
  if (snapshot.canApply) actions.push(element.createElement('button', { type: 'button', onClick: () => fieldFlowOperationalModuleClickAction(props, 'apply'), key: 'apply' }, 'Aplicar filtros'));

  const pagination = snapshot.pageLabel ? element.createElement('div', { className: 'ff-react-module-pagination' },
    element.createElement('button', { type: 'button', className: 'is-ghost', disabled: snapshot.prevDisabled, onClick: () => fieldFlowOperationalModuleClickAction(props, 'prev') }, 'Anterior'),
    element.createElement('span', null, snapshot.pageLabel),
    element.createElement('button', { type: 'button', className: 'is-ghost', disabled: snapshot.nextDisabled, onClick: () => fieldFlowOperationalModuleClickAction(props, 'next') }, 'Seguinte')
  ) : null;

  return element.createElement('section', {
    className: `ff-react-operational-module ff-react-operational-module-${props.module}`,
    'data-react-contract': FIELD_FLOW_REACT_OPERATIONAL_MODULES_CONTRACT,
    'data-react-contract-v2': v2Contract ? FIELD_FLOW_REACT_OPERATIONAL_MODULES_V2_CONTRACT : '',
    'data-operational-modernization': wave2Modernized ? FIELD_FLOW_OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT : (wave1Modernized ? FIELD_FLOW_OPERATIONAL_MODERNIZATION_CONTRACT : ''),
    'data-action-contract': FIELD_FLOW_REACT_OPERATIONAL_ACTIONS_CONTRACT,
    'data-client-wave1': clientWave1 ? FIELD_FLOW_REACT_CLIENT_WAVE1_CONTRACT : '',
    'data-client-wave1-status': clientWave1 ? waveStatus : 'legacy',
    'data-client-wave2': clientWave2 ? FIELD_FLOW_REACT_CLIENT_WAVE2_CONTRACT : '',
    'data-client-wave2-status': clientWave2 ? waveStatus : 'legacy',
    'data-portal-scope': portalScope,
    'data-cross-portal-isolation': props.crossPortalIsolationContract === FIELD_FLOW_CROSS_PORTAL_RENDER_ISOLATION_CONTRACT ? FIELD_FLOW_CROSS_PORTAL_RENDER_ISOLATION_CONTRACT : '',
  },
    element.createElement('header', { className: 'ff-react-module-head' },
      element.createElement('div', null,
        element.createElement('span', null, waveLabel),
        element.createElement('strong', null, snapshot.title)
      ),
      element.createElement('small', { 'aria-live': v2Contract ? 'polite' : undefined }, clientWave && waveStatus !== 'ready' ? `${waveStatus === 'refreshing' ? 'A atualizar' : 'A preparar'} · ${snapshot.summary}` : snapshot.summary)
    ),
    controlNodes.length ? element.createElement('div', { className: 'ff-react-module-controls' }, ...controlNodes) : null,
    chipNodes,
    actions.length ? element.createElement('div', { className: 'ff-react-module-actions' }, ...actions) : null,
    pagination
  );
}
