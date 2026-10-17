function fieldFlowSpecialActive(field: Element): boolean {
  const wrap = typeof (field as Element).closest === 'function' ? field.closest('[data-rp-question-wrap]') : null;
  if (wrap) {
    if (wrap.hasAttribute('hidden')) return false;
    if (wrap.getAttribute('data-rp-condition-hidden') === '1') return false;
  }
  if (field instanceof HTMLElement && field.hidden) return false;
  return true;
}

function fieldFlowSpecialMediaCount(scope: Element): { saved: number; pending: number; removing: number; active: number } {
  const saved = scope.querySelectorAll('.routespro-evidence-existing img, .routespro-form-help a[href]').length;
  const pending = scope.querySelectorAll('[data-rp-upload-preview] img, [data-rp-upload-preview] .routespro-upload-preview-file').length;
  const removing = scope.querySelectorAll('[data-rp-existing-media].is-pending-removal').length;
  return { saved, pending, removing, active: Math.max(0, saved - removing) + pending };
}

function fieldFlowSpecialCategoryName(group: Element, fallback: string): string {
  return String(group.querySelector('.routespro-category-name')?.textContent || fallback).trim() || fallback;
}

function fieldFlowSpecialSnapshot(root: Element, props: FieldFlowSpecialFieldProps): FieldFlowSpecialSnapshot {
  const field = root.closest('.routespro-form-field');
  if (!field) return { total: 0, complete: 0, incomplete: 0, media: 0, selected: 0, savedMedia: 0, pendingMedia: 0, pendingRemoval: 0, online: navigator.onLine, active: false, status: 'Sem dados', categories: [], goalMet: 0, goalBelow: 0, autoSuggested: 0, totalOrderQty: 0 };
  let total = 0;
  let complete = 0;
  let media = 0;
  let selected = 0;
  let savedMedia = 0;
  let pendingMedia = 0;
  let pendingRemoval = 0;
  let goalMet = 0;
  let goalBelow = 0;
  let autoSuggested = 0;
  let totalOrderQty = 0;
  const categories: FieldFlowSpecialCategorySnapshot[] = [];
  const active = fieldFlowSpecialActive(field);

  if (props.fieldType === 'share_space_matrix') {
    const cards = Array.from(field.querySelectorAll('[data-share-space-card]'));
    total = cards.length;
    cards.forEach((card, index) => {
      const a = card.querySelector('[data-share-space-pr]') as HTMLInputElement | null;
      const b = card.querySelector('[data-share-space-competitor]') as HTMLInputElement | null;
      const aRaw = String(a?.value || '').trim();
      const bRaw = String(b?.value || '').trim();
      const done = aRaw !== '' && bRaw !== '';
      if (done) complete += 1;
      const pr = Number.parseFloat(aRaw.replace(',', '.'));
      const competitor = Number.parseFloat(bRaw.replace(',', '.'));
      const target = Number.parseFloat(String(card.getAttribute('data-target') || '0')) || 0;
      if (done && Number.isFinite(pr) && Number.isFinite(competitor) && (pr + competitor) > 0) {
        const share = (Math.max(0, pr) / (Math.max(0, pr) + Math.max(0, competitor))) * 100;
        if (share >= target) goalMet += 1;
        else goalBelow += 1;
      }
      categories.push({ index, label: String(card.querySelector('h4')?.textContent || `Comparação ${index + 1}`).trim(), total: 1, complete: done ? 1 : 0, incomplete: done ? 0 : 1 });
    });
  } else if (props.fieldType === 'product_matrix') {
    const rows = Array.from(field.querySelectorAll('[data-product-row]'));
    total = rows.length;
    complete = rows.filter((row) => String((row.querySelector('[data-fronts-input]') as HTMLInputElement | null)?.value || '').trim() !== '').length;
    Array.from(field.querySelectorAll('[data-category-group]')).forEach((group, index) => {
      if (group.hasAttribute('hidden')) return;
      const groupRows = Array.from(group.querySelectorAll('[data-product-row]'));
      if (!groupRows.length) return;
      const groupComplete = groupRows.filter((row) => String((row.querySelector('[data-fronts-input]') as HTMLInputElement | null)?.value || '').trim() !== '').length;
      categories.push({ index, label: fieldFlowSpecialCategoryName(group, `Categoria ${index + 1}`), total: groupRows.length, complete: groupComplete, incomplete: Math.max(0, groupRows.length - groupComplete) });
    });
  } else if (props.fieldType === 'order_suggestion') {
    const rows = Array.from(field.querySelectorAll('[data-order-row]'));
    total = rows.length;
    rows.forEach((row) => {
      const input = row.querySelector('[data-order-qty]') as HTMLInputElement | null;
      const qty = Number.parseFloat(String(input?.value || '0').replace(',', '.')) || 0;
      if (qty > 0) {
        complete += 1;
        totalOrderQty += qty;
      }
      if (row.classList.contains('is-auto-suggested') || input?.getAttribute('data-rp-auto-fronts') === '1') autoSuggested += 1;
    });
    Array.from(field.querySelectorAll('[data-category-group]')).forEach((group, index) => {
      if (group.hasAttribute('hidden')) return;
      const groupRows = Array.from(group.querySelectorAll('[data-order-row]'));
      if (!groupRows.length) return;
      const groupComplete = groupRows.filter((row) => (Number.parseFloat(String((row.querySelector('[data-order-qty]') as HTMLInputElement | null)?.value || '0').replace(',', '.')) || 0) > 0).length;
      categories.push({ index, label: fieldFlowSpecialCategoryName(group, `Categoria ${index + 1}`), total: groupRows.length, complete: groupComplete, incomplete: Math.max(0, groupRows.length - groupComplete) });
    });
  } else if (props.fieldType === 'categorized_image_upload') {
    const categoryNodes = Array.from(field.querySelectorAll('[data-rp-evidence-category]'));
    total = categoryNodes.length;
    categoryNodes.forEach((category, index) => {
      const checked = Boolean((category.querySelector('[data-rp-evidence-toggle]') as HTMLInputElement | null)?.checked);
      const counts = fieldFlowSpecialMediaCount(category);
      if (checked) selected += 1;
      if (checked && counts.active > 0) complete += 1;
      savedMedia += counts.saved;
      pendingMedia += counts.pending;
      pendingRemoval += counts.removing;
      media += counts.active;
      categories.push({
        index,
        label: String(category.querySelector('.routespro-evidence-category-toggle strong')?.textContent || `Categoria ${index + 1}`).trim(),
        total: checked ? 1 : 0,
        complete: checked && counts.active > 0 ? 1 : 0,
        incomplete: checked && counts.active === 0 ? 1 : 0,
      });
    });
  } else if (props.fieldType === 'image_upload' || props.fieldType === 'file_upload') {
    total = 1;
    const counts = fieldFlowSpecialMediaCount(field);
    const inputs = Array.from(field.querySelectorAll('[data-rp-upload-file]')).filter((node): node is HTMLInputElement => node instanceof HTMLInputElement);
    const selectedFiles = inputs.reduce((count, input) => count + Number(input.files?.length || 0), 0);
    savedMedia = counts.saved;
    pendingMedia = Math.max(selectedFiles, counts.pending);
    pendingRemoval = counts.removing;
    media = Math.max(0, savedMedia - pendingRemoval) + pendingMedia;
    complete = media > 0 ? 1 : 0;
  }

  if (!active && props.fieldType === 'order_suggestion') {
    complete = 0;
    autoSuggested = 0;
    totalOrderQty = 0;
  }
  const incomplete = props.fieldType === 'categorized_image_upload'
    ? categories.reduce((sum, category) => sum + category.incomplete, 0)
    : Math.max(0, total - complete);

  let status = 'Por preencher';
  if (!active) status = 'Inativo pela condição anterior';
  else if (props.fieldType === 'order_suggestion') status = complete > 0 ? `${complete} referência(s) · ${Math.round(totalOrderQty * 100) / 100} CXS` : 'Sem sugestão ativa';
  else if (props.fieldType === 'categorized_image_upload') {
    if (selected === 0) status = 'Sem categorias selecionadas';
    else if (incomplete > 0) status = `${complete}/${selected} categorias com evidência`;
    else status = `${selected} categoria(s) com evidência`;
  } else if (props.fieldType === 'product_matrix') {
    status = total > 0 ? `${complete} com valor · ${incomplete} zero automático` : 'Sem referências';
  } else if (props.fieldType === 'image_upload' || props.fieldType === 'file_upload') status = media > 0 ? `${media} ficheiro(s)` : 'Sem ficheiros';
  else if (total > 0 && complete >= total) status = 'Completo';
  else if (complete > 0) status = `${complete}/${total} preenchido`;
  else if (!props.required) status = 'Opcional';

  return { total, complete, incomplete, media, selected, savedMedia, pendingMedia, pendingRemoval, online: navigator.onLine, active, status, categories, goalMet, goalBelow, autoSuggested, totalOrderQty };
}

function fieldFlowMediaNativeActions(field: Element | null): HTMLElement[] {
  if (!field) return [];
  return Array.from(field.querySelectorAll('[data-rp-upload-source-actions]')).filter((node): node is HTMLElement => node instanceof HTMLElement);
}

function fieldFlowMediaClick(field: Element | null, source: 'camera' | 'gallery' | 'file'): void {
  if (!field) return;
  if (source === 'camera' || source === 'gallery') {
    const trigger = field.querySelector(`[data-rp-upload-trigger="${source}"]`) as HTMLElement | null;
    trigger?.click();
    return;
  }
  const input = field.querySelector('[data-rp-upload-file]') as HTMLInputElement | null;
  input?.click();
}

function fieldFlowSpecialScrollTo(node: Element | null): void {
  if (!node) return;
  if (node instanceof HTMLElement) node.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
}

function fieldFlowSpecialOpenCategory(field: Element | null, index: number): void {
  if (!field) return;
  const groups = Array.from(field.querySelectorAll('[data-category-group]')).filter((group) => !group.hasAttribute('hidden'));
  const group = groups[index] || null;
  if (!group) return;
  const toggle = group.querySelector('[data-category-toggle]') as HTMLElement | null;
  if (toggle && toggle.getAttribute('aria-expanded') !== 'true') toggle.click();
  fieldFlowSpecialScrollTo(group);
}

function fieldFlowSpecialGoToIncomplete(field: Element | null, props: FieldFlowSpecialFieldProps): void {
  if (!field) return;
  let target: Element | null = null;
  if (props.fieldType === 'share_space_matrix') {
    target = Array.from(field.querySelectorAll('[data-share-space-card]')).find((card) => {
      const a = String((card.querySelector('[data-share-space-pr]') as HTMLInputElement | null)?.value || '').trim();
      const b = String((card.querySelector('[data-share-space-competitor]') as HTMLInputElement | null)?.value || '').trim();
      return a === '' || b === '';
    }) || null;
  } else if (props.fieldType === 'categorized_image_upload') {
    target = Array.from(field.querySelectorAll('[data-rp-evidence-category]')).find((category) => {
      const checked = Boolean((category.querySelector('[data-rp-evidence-toggle]') as HTMLInputElement | null)?.checked);
      return checked && fieldFlowSpecialMediaCount(category).active === 0;
    }) || null;
  }
  if (!target) return;
  const parentGroup = target.closest('[data-category-group]');
  const toggle = parentGroup?.querySelector('[data-category-toggle]') as HTMLElement | null;
  if (toggle && toggle.getAttribute('aria-expanded') !== 'true') toggle.click();
  fieldFlowSpecialScrollTo(target);
  const focusable = target.querySelector('input:not([type="hidden"]), select, textarea, button') as HTMLElement | null;
  window.setTimeout(() => focusable?.focus(), 180);
}

function FieldFlowSpecialFieldIsland(props: FieldFlowSpecialFieldProps & { root: Element }): unknown {
  const { root } = props;
  const element = window.wp?.element;
  if (!element) return null;
  const [snapshot, setSnapshot] = element.useState<FieldFlowSpecialSnapshot>(fieldFlowSpecialSnapshot(root, props));
  element.useEffect(() => {
    const field = root.closest('.routespro-form-field');
    if (!field) return undefined;
    let scheduled = false;
    const refresh = (): void => {
      if (scheduled) return;
      scheduled = true;
      queueMicrotask(() => {
        scheduled = false;
        setSnapshot(fieldFlowSpecialSnapshot(root, props));
      });
    };
    const observer = new MutationObserver(refresh);
    const wrap = typeof (field as Element).closest === 'function' ? field.closest('[data-rp-question-wrap]') : null;
    observer.observe(field, { childList: true, subtree: true, attributes: true, attributeFilter: ['checked', 'hidden', 'class', 'value', 'disabled', 'data-rp-auto-fronts'] });
    if (wrap && wrap !== field) observer.observe(wrap, { attributes: true, attributeFilter: ['hidden', 'data-rp-condition-hidden', 'class'] });
    field.addEventListener('input', refresh, true);
    field.addEventListener('change', refresh, true);
    document.addEventListener('fieldflow:offline-form-synced', refresh as EventListener);
    document.addEventListener('fieldflow:sync-completed', refresh as EventListener);
    window.addEventListener('online', refresh);
    window.addEventListener('offline', refresh);
    const nativeMediaActions = (props.fieldType === 'image_upload') ? fieldFlowMediaNativeActions(field) : [];
    nativeMediaActions.forEach((node) => node.setAttribute('data-fieldflow-react-media-native', '1'));
    if (['image_upload', 'categorized_image_upload', 'file_upload'].includes(props.fieldType)) field.setAttribute('data-fieldflow-react-media-control', '1');
    field.setAttribute('data-fieldflow-react-form-completion', '1');
    return () => {
      observer.disconnect();
      field.removeEventListener('input', refresh, true);
      field.removeEventListener('change', refresh, true);
      document.removeEventListener('fieldflow:offline-form-synced', refresh as EventListener);
      document.removeEventListener('fieldflow:sync-completed', refresh as EventListener);
      window.removeEventListener('online', refresh);
      window.removeEventListener('offline', refresh);
      nativeMediaActions.forEach((node) => node.removeAttribute('data-fieldflow-react-media-native'));
      field.removeAttribute('data-fieldflow-react-media-control');
      field.removeAttribute('data-fieldflow-react-form-completion');
    };
  }, [props.fieldKey, props.fieldType]);

  const field = root.closest('.routespro-form-field');
  const hasCategories = Boolean(field?.querySelector('[data-category-accordion]'));
  const openAll = (): void => (field?.querySelector('[data-category-open-all]') as HTMLElement | null)?.click();
  const closeAll = (): void => (field?.querySelector('[data-category-close-all]') as HTMLElement | null)?.click();
  const percentBase = props.fieldType === 'categorized_image_upload' && snapshot.selected > 0 ? snapshot.selected : snapshot.total;
  const percent = percentBase > 0 ? Math.round((Math.min(snapshot.complete, percentBase) / percentBase) * 100) : (snapshot.complete > 0 ? 100 : 0);
  const isMedia = ['image_upload','categorized_image_upload','file_upload'].includes(props.fieldType);
  const mediaContract = props.mediaControlContract || FIELD_FLOW_REACT_MEDIA_CONTROL_CONTRACT;
  const completionContract = props.formCompletionContract || FIELD_FLOW_REACT_FORM_COMPLETION_CONTRACT;
  const showProgress = snapshot.active && percentBase > 0 && !['image_upload','file_upload'].includes(props.fieldType);
  const showIncompleteAction = snapshot.active && snapshot.incomplete > 0 && ['share_space_matrix','categorized_image_upload'].includes(props.fieldType);

  const summaryChildren: unknown[] = [];
  if (props.fieldType === 'share_space_matrix') {
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.goalMet)), element.createElement('small', null, 'Objetivo atingido')));
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.goalBelow)), element.createElement('small', null, 'Abaixo do objetivo')));
  } else if (props.fieldType === 'order_suggestion') {
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.complete)), element.createElement('small', null, 'Referências')));
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(Math.round(snapshot.totalOrderQty * 100) / 100)), element.createElement('small', null, 'Caixas')));
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.autoSuggested)), element.createElement('small', null, 'Automáticas')));
  } else if (props.fieldType === 'product_matrix') {
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.complete)), element.createElement('small', null, 'Com valor')));
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.incomplete)), element.createElement('small', null, 'Zero automático')));
  } else if (props.fieldType === 'categorized_image_upload') {
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.selected)), element.createElement('small', null, 'Selecionadas')));
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.complete)), element.createElement('small', null, 'Com evidência')));
    summaryChildren.push(element.createElement('span', null, element.createElement('strong', null, String(snapshot.incomplete)), element.createElement('small', null, 'Sem evidência')));
  }

  return element.createElement('section', {
    className: 'ff-react-special-field ' + (!snapshot.active ? 'is-inactive' : ''),
    'data-react-contract': FIELD_FLOW_REACT_SPECIAL_FIELD_CONTRACT,
    'data-form-completion-contract': completionContract,
    'data-special-type': props.fieldType,
  },
  element.createElement('div', { className: 'ff-react-special-field-head' },
    element.createElement('div', null,
      element.createElement('span', null, props.badge || 'Campo avançado'),
      element.createElement('strong', null, props.label)
    ),
    element.createElement('span', { className: 'ff-react-special-status ' + (snapshot.active && percent === 100 ? 'is-complete' : '') + (!snapshot.active ? ' is-inactive' : '') }, snapshot.status)
  ),
  summaryChildren.length ? element.createElement('div', { className: 'ff-react-special-summary' }, ...summaryChildren) : null,
  showProgress ? element.createElement('div', { className: 'ff-react-special-progress' },
    element.createElement('span', { style: { width: percent + '%' } }),
    element.createElement('small', null, percent + '%')
  ) : null,
  snapshot.categories.length > 0 && ['product_matrix','order_suggestion'].includes(props.fieldType) ? element.createElement('div', { className: 'ff-react-special-category-nav', 'aria-label': 'Navegação por categorias' },
    ...snapshot.categories.map((category) => element.createElement('button', {
      type: 'button',
      className: category.incomplete === 0 ? 'is-complete' : '',
      onClick: () => fieldFlowSpecialOpenCategory(field, category.index),
      'data-fieldflow-react-category-index': String(category.index),
    }, category.label, element.createElement('small', null, `${category.complete}/${category.total}`)))
  ) : null,
  (hasCategories || showIncompleteAction) ? element.createElement('div', { className: 'ff-react-special-actions' },
    hasCategories ? element.createElement('button', { type: 'button', onClick: openAll }, 'Abrir categorias') : null,
    hasCategories ? element.createElement('button', { type: 'button', onClick: closeAll }, 'Fechar categorias') : null,
    showIncompleteAction ? element.createElement('button', { type: 'button', className: 'is-primary', onClick: () => fieldFlowSpecialGoToIncomplete(field, props) }, 'Próximo pendente') : null
  ) : null,
  isMedia ? element.createElement('div', {
    className: 'ff-react-media-control',
    'data-react-contract': mediaContract,
    'data-modernization-contract': FIELD_FLOW_MODERNIZATION_CLOSURE_CONTRACT,
  },
    element.createElement('div', { className: 'ff-react-media-stats' },
      element.createElement('span', null, element.createElement('strong', null, String(snapshot.media)), element.createElement('small', null, 'Ativas')),
      element.createElement('span', null, element.createElement('strong', null, String(snapshot.pendingMedia)), element.createElement('small', null, 'Pendentes')),
      element.createElement('span', null, element.createElement('strong', null, String(snapshot.pendingRemoval)), element.createElement('small', null, 'A remover')),
      element.createElement('span', { className: snapshot.online ? 'is-online' : 'is-offline' }, element.createElement('strong', null, snapshot.online ? 'Online' : 'Offline'), element.createElement('small', null, snapshot.online ? 'Sincronização disponível' : 'Protegido localmente'))
    ),
    props.fieldType === 'image_upload' ? element.createElement('div', { className: 'ff-react-media-actions' },
      element.createElement('button', { type: 'button', onClick: () => fieldFlowMediaClick(field, 'camera') }, 'Tirar fotografia'),
      element.createElement('button', { type: 'button', onClick: () => fieldFlowMediaClick(field, 'gallery') }, 'Escolher imagens')
    ) : null,
    props.fieldType === 'file_upload' ? element.createElement('div', { className: 'ff-react-media-actions is-secondary' },
      element.createElement('button', { type: 'button', onClick: () => fieldFlowMediaClick(field, 'file') }, 'Adicionar ficheiro')
    ) : null,
    element.createElement('small', { className: 'ff-react-media-owner' }, 'Captura, recuperação e envio continuam no runtime FieldFlow certificado.')
  ) : null);
}
