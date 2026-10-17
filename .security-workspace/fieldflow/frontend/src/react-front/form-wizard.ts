const FIELD_FLOW_REACT_FRONT_CONTRACT = 'fieldflow-react-front-foundation-v1';
const FIELD_FLOW_MODERNIZATION_CLOSURE_CONTRACT = 'fieldflow-modernization-stable-closure-v1';
const FIELD_FLOW_REACT_FORM_WIZARD_CONTRACT = 'fieldflow-react-form-wizard-progress-v1';
const FIELD_FLOW_FORM_WIZARD_ISLAND = 'form-wizard-progress';
const FIELD_FLOW_REACT_OPERATIONAL_SHELL_CONTRACT = 'fieldflow-react-operational-shell-v1';
const FIELD_FLOW_OPERATIONAL_SHELL_ISLAND = 'operational-shell';
const FIELD_FLOW_OPERATIONAL_MODULES_ISLAND = 'operational-module-controls';
const FIELD_FLOW_REACT_MY_ROUTE_CONTRACT = 'fieldflow-react-my-route-toolbar-v1';
const FIELD_FLOW_MY_ROUTE_ISLAND = 'my-route-toolbar';
const FIELD_FLOW_REACT_SIMPLE_FIELD_CONTRACT = 'fieldflow-react-simple-field-assist-v1';
const FIELD_FLOW_REACT_SIMPLE_FIELD_CONTROL_CONTRACT = 'fieldflow-react-simple-field-control-v1';
const FIELD_FLOW_SIMPLE_FIELD_ISLAND = 'simple-field-assist';
const FIELD_FLOW_REACT_CHOICE_FIELD_CONTRACT = 'fieldflow-react-choice-field-control-v1';
const FIELD_FLOW_CHOICE_FIELD_ISLAND = 'choice-field-control';
const FIELD_FLOW_REACT_SPECIAL_FIELD_CONTRACT = 'fieldflow-react-special-field-assist-v1';
const FIELD_FLOW_REACT_MEDIA_CONTROL_CONTRACT = 'fieldflow-react-media-control-v1';
const FIELD_FLOW_REACT_FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1';
const FIELD_FLOW_FRONT_TRANSITION_STABILITY_CONTRACT = 'fieldflow-front-transition-stability-v1';
const FIELD_FLOW_SPECIAL_FIELD_ISLAND = 'special-field-assist';

function fieldFlowReactFrontProps(root: Element): FieldFlowReactFrontProps | null {
  const raw = String(root.getAttribute('data-fieldflow-react-front-props') || '').trim();
  if (!raw) return null;
  try {
    const parsed = JSON.parse(raw) as FieldFlowReactFrontProps;
    if (!parsed || typeof parsed.island !== 'string' || parsed.contract !== FIELD_FLOW_REACT_FRONT_CONTRACT) return null;
    return parsed;
  } catch (_error) {
    return null;
  }
}

function fieldFlowReactFrontSnapshot(root: Element, props: FieldFlowFormWizardProgressProps): FieldFlowFormWizardSnapshot {
  const wizard = root.closest('.routespro-form-wizard');
  const fallback = document.getElementById(props.fallbackId);
  const legacyLabel = fallback?.querySelector('[data-rp-progress-label]')?.textContent || '';
  const labelMatch = legacyLabel.match(/Passo\s+(\d+)\s+de\s+(\d+).*?(\d+)%/i);
  let current = labelMatch ? Number(labelMatch[1]) : 1;
  let total = labelMatch ? Number(labelMatch[2]) : Math.max(1, Number(props.totalSteps) || 1);
  let percent = labelMatch ? Number(labelMatch[3]) : Math.round((current / total) * 100);
  const activeStep = wizard?.querySelector('[data-rp-step]:not([hidden])');
  const rawIndex = Number(activeStep?.getAttribute('data-step-index') || 0);
  if (rawIndex > 0 && !labelMatch) current = rawIndex;
  if (!Number.isFinite(total) || total < 1) total = Math.max(1, Number(props.totalSteps) || 1);
  if (!Number.isFinite(current) || current < 1) current = 1;
  if (current > total) current = total;
  if (!Number.isFinite(percent)) percent = Math.round((current / total) * 100);
  percent = Math.max(0, Math.min(100, percent));
  const title = String(activeStep?.querySelector('.routespro-form-step-title')?.textContent || '').trim();
  return {
    current,
    total,
    percent,
    title: title || 'Passo ' + current,
    label: legacyLabel || ('Passo ' + current + ' de ' + total + ' · ' + percent + '%'),
  };
}

function FieldFlowFormWizardProgressIsland(props: FieldFlowFormWizardProgressProps & { root: Element }): unknown {
  const { root } = props;
  const element = window.wp?.element;
  if (!element) return null;
  const [snapshot, setSnapshot] = element.useState<FieldFlowFormWizardSnapshot>(fieldFlowReactFrontSnapshot(root, props));

  element.useEffect(() => {
    const wizard = root.closest('.routespro-form-wizard');
    const fallback = document.getElementById(props.fallbackId);
    if (fallback) { fallback.removeAttribute('data-fieldflow-react-front-fallback-pending'); fallback.setAttribute('data-fieldflow-react-front-fallback-hidden', '1'); }
    if (!wizard) return () => {
      if (fallback) { fallback.removeAttribute('data-fieldflow-react-front-fallback-hidden'); fallback.removeAttribute('data-fieldflow-react-front-fallback-pending'); }
    };
    let scheduled = false;
    let disposed = false;
    const refresh = (): void => {
      if (scheduled) return;
      scheduled = true;
      queueMicrotask(() => {
        scheduled = false;
        if (!disposed) setSnapshot(fieldFlowReactFrontSnapshot(root, props));
      });
    };
    const observer = new MutationObserver(refresh);
    observer.observe(wizard, { subtree: true, childList: true, attributes: true, characterData: true, attributeFilter: ['hidden', 'style', 'class'] });
    wizard.addEventListener('click', refresh, true);
    wizard.addEventListener('change', refresh, true);
    refresh();
    return () => {
      disposed = true;
      observer.disconnect();
      wizard.removeEventListener('click', refresh, true);
      wizard.removeEventListener('change', refresh, true);
      if (fallback) { fallback.removeAttribute('data-fieldflow-react-front-fallback-hidden'); fallback.removeAttribute('data-fieldflow-react-front-fallback-pending'); }
    };
  }, [props.fallbackId]);

  return element.createElement(
    'section',
    { className: 'ff-react-front-form-progress', 'data-react-contract': FIELD_FLOW_REACT_FORM_WIZARD_CONTRACT },
    element.createElement('div', { className: 'ff-react-front-form-progress-head' },
      element.createElement('div', null,
        element.createElement('span', { className: 'ff-react-front-eyebrow' }, props.label || 'Progresso da visita'),
        element.createElement('strong', null, snapshot.title)
      ),
      element.createElement('span', { className: 'ff-react-front-step-pill' }, snapshot.current + '/' + snapshot.total)
    ),
    element.createElement('div', { className: 'ff-react-front-progress-track', role: 'progressbar', 'aria-label': props.label || 'Progresso da visita', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': snapshot.percent, 'aria-valuetext': snapshot.label },
      element.createElement('span', { style: { width: snapshot.percent + '%' } })
    ),
    element.createElement('div', { className: 'ff-react-front-progress-meta' },
      element.createElement('span', null, snapshot.label),
      element.createElement('strong', null, snapshot.percent + '%')
    )
  );
}


function fieldFlowReactFrontPortalScope(root: Element): 'client' | 'operative' | 'standalone' {
  const closest = typeof root.closest === 'function' ? root.closest.bind(root) : null;
  if (!closest) return 'standalone';
  if (closest('.rp-client-premium[data-fieldflow-portal="client"],.rp-client-premium')) return 'client';
  if (closest('.rp-premium-shell[data-fieldflow-portal="operative"],.rp-premium-shell.rp-app')) return 'operative';
  return 'standalone';
}

const fieldFlowReactFrontRoots = new Map<Element, () => void>();

function fieldFlowReactFrontRender(root: Element, node: unknown): boolean {
  const element = window.wp?.element;
  if (!element || fieldFlowReactFrontRoots.has(root)) return false;
  if (typeof element.createRoot === 'function') {
    const reactRoot = element.createRoot(root);
    fieldFlowReactFrontRoots.set(root, () => reactRoot.unmount());
    try { reactRoot.render(node); }
    catch (error) {
      reactRoot.unmount();
      fieldFlowReactFrontRoots.delete(root);
      throw error;
    }
  } else if (typeof element.render === 'function' && typeof element.unmountComponentAtNode === 'function') {
    fieldFlowReactFrontRoots.set(root, () => { element.unmountComponentAtNode!(root); });
    try { element.render(node, root); }
    catch (error) {
      element.unmountComponentAtNode(root);
      fieldFlowReactFrontRoots.delete(root);
      throw error;
    }
  } else return false;
  return true;
}

function fieldFlowReactFrontReconcileRoots(): void {
  fieldFlowReactFrontRoots.forEach((unmount, root) => {
    if (root.isConnected !== false) return;
    unmount();
    fieldFlowReactFrontRoots.delete(root);
    root.removeAttribute('data-fieldflow-react-front-mounted');
    root.removeAttribute('data-fieldflow-react-mounted');
  });
}

function fieldFlowReactFrontMount(root: Element): boolean {
  if (fieldFlowReactFrontRoots.has(root)) return false;
  const props = fieldFlowReactFrontProps(root);
  const portalScope = fieldFlowReactFrontPortalScope(root);
  root.setAttribute('data-fieldflow-portal-scope', portalScope);
  const element = window.wp?.element;
  if (!props || !element) return false;
  let node: unknown = null;
  if (props.island === FIELD_FLOW_FORM_WIZARD_ISLAND) node = element.createElement(FieldFlowFormWizardProgressIsland, { ...props, root });
  else if (props.island === FIELD_FLOW_OPERATIONAL_SHELL_ISLAND) node = element.createElement(FieldFlowOperationalShellIsland, { ...props, root });
  else if (props.island === FIELD_FLOW_OPERATIONAL_MODULES_ISLAND) node = element.createElement(FieldFlowOperationalModuleControlsIsland, { ...props, root });
  else if (props.island === FIELD_FLOW_MY_ROUTE_ISLAND) node = element.createElement(FieldFlowMyRouteToolbarIsland, { ...props, root });
  else if (props.island === FIELD_FLOW_SIMPLE_FIELD_ISLAND) node = element.createElement(FieldFlowSimpleFieldAssistIsland, { ...props, root });
  else if (props.island === FIELD_FLOW_CHOICE_FIELD_ISLAND) node = element.createElement(FieldFlowChoiceFieldIsland, { ...props, root });
  else if (props.island === FIELD_FLOW_SPECIAL_FIELD_ISLAND) node = element.createElement(FieldFlowSpecialFieldIsland, { ...props, root });
  if (!node) return false;
  try {
    if (!fieldFlowReactFrontRender(root, node)) return false;
  } catch (_error) { return false; }
  root.setAttribute('data-fieldflow-react-front-mounted', '1');
  return true;
}

function fieldFlowReactFrontMountAll(): number {
  fieldFlowReactFrontReconcileRoots();
  let mounted = 0;
  document.querySelectorAll('[data-fieldflow-react-front-island]').forEach((root) => {
    if (fieldFlowReactFrontMount(root)) mounted += 1;
  });
  return mounted;
}
