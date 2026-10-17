(function fieldFlowReactFrontBootstrap(): void {
  'use strict';
  const previous = window.FieldFlowReactFront;
  if (previous && previous.version === FIELD_FLOW_FRONTEND_BUILD_VERSION) return;
  const api: FieldFlowReactFrontApi = {
    version: FIELD_FLOW_FRONTEND_BUILD_VERSION,
    contract: FIELD_FLOW_REACT_FRONT_CONTRACT,
    source: 'wordpress-wp-element',
    ready: false,
    mountAll: fieldFlowReactFrontMountAll,
  };
  window.FieldFlowReactFront = api;
  let mountScheduled = false;
  const scheduleMount = (): void => {
    if (mountScheduled) return;
    mountScheduled = true;
    queueMicrotask(() => {
      mountScheduled = false;
      fieldFlowReactFrontMountAll();
      fieldFlowReactLocaleSwitcherMount();
    });
  };
  const start = (): void => {
    if (!window.FieldFlowReactFront || window.FieldFlowReactFront.version !== FIELD_FLOW_FRONTEND_BUILD_VERSION) return;
    fieldFlowReactFrontMountAll();
    fieldFlowReactLocaleSwitcherMount();
    const reconcileLocaleSwitcher = (): void => {
      fieldFlowReactLocaleSwitcherMount();
      if (typeof window.FieldFlowI18n?.reconcile === 'function') window.FieldFlowI18n.reconcile('react-front-bootstrap');
    };
    if (typeof window.addEventListener === 'function') {
      window.addEventListener('fieldflow:i18n-ready', reconcileLocaleSwitcher as EventListener);
      window.addEventListener('fieldflow:i18n-context-changed', reconcileLocaleSwitcher as EventListener);
      window.addEventListener('pageshow', reconcileLocaleSwitcher as EventListener);
      window.addEventListener('focus', reconcileLocaleSwitcher as EventListener);
    }
    document.addEventListener('routespro:scope-change', reconcileLocaleSwitcher as EventListener);
    document.addEventListener('fieldflow:portal-ready', reconcileLocaleSwitcher as EventListener);
    document.addEventListener('visibilitychange', reconcileLocaleSwitcher as EventListener);
    window.FieldFlowReactFront.ready = true;
    document.addEventListener('fieldflow:lazy-panel-loaded', scheduleMount as EventListener);
    document.addEventListener('fieldflow:form-rendered', scheduleMount as EventListener);
    if (document.body) {
      const containsUnmountedIsland = (node: Node): boolean => {
        if (!(node instanceof Element)) return false;
        if (node.matches('[data-fieldflow-react-front-island]:not([data-fieldflow-react-front-mounted="1"])')) return true;
        return Boolean(node.querySelector('[data-fieldflow-react-front-island]:not([data-fieldflow-react-front-mounted="1"])'));
      };
      const observer = new MutationObserver((records) => {
        const shouldMount = records.some((record) => record.removedNodes.length > 0 || Array.from(record.addedNodes).some(containsUnmountedIsland));
        if (shouldMount) scheduleMount();
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
  else queueMicrotask(start);
})();
