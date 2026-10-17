(function fieldFlowReactBackofficeBootstrap(): void {
  'use strict';

  const previous = window.FieldFlowReactBackoffice;
  if (previous && previous.version === FIELD_FLOW_FRONTEND_BUILD_VERSION) return;

  const api: FieldFlowReactBackofficeApi = {
    version: FIELD_FLOW_FRONTEND_BUILD_VERSION,
    contract: FIELD_FLOW_REACT_BACKOFFICE_CONTRACT,
    source: 'wordpress-wp-element',
    ready: false,
    mountAll: fieldFlowReactMountAll,
  };
  window.FieldFlowReactBackoffice = api;

  const start = (): void => {
    if (!window.FieldFlowReactBackoffice || window.FieldFlowReactBackoffice.version !== FIELD_FLOW_FRONTEND_BUILD_VERSION) return;
    fieldFlowReactMountAll();
    if (document.body) {
      let scheduled = false;
      const observer = new MutationObserver(() => {
        if (scheduled) return;
        scheduled = true;
        queueMicrotask(() => { scheduled = false; fieldFlowReactMountAll(); });
      });
      observer.observe(document.body, { childList: true, subtree: true });
    }
    window.FieldFlowReactBackoffice.ready = true;
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    queueMicrotask(start);
  }
})();
