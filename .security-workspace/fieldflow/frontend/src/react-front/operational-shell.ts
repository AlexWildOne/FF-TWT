function fieldFlowOperationalShellSnapshot(props: FieldFlowOperationalShellProps): FieldFlowOperationalShellSnapshot {
  const shell = document.getElementById(props.rootId);
  const active = shell?.querySelector('.rp-panel.active') as HTMLElement | null;
  return {
    activePanel: String(active?.dataset.panel || 'route'),
    online: navigator.onLine !== false,
  };
}

function FieldFlowOperationalShellIsland(props: FieldFlowOperationalShellProps & { root: Element }): unknown {
  const { root } = props;
  const element = window.wp?.element;
  if (!element) return null;
  const [snapshot, setSnapshot] = element.useState<FieldFlowOperationalShellSnapshot>(fieldFlowOperationalShellSnapshot(props));

  element.useEffect(() => {
    const shell = document.getElementById(props.rootId);
    const fallback = document.getElementById(props.fallbackId);
    if (fallback) { fallback.removeAttribute('data-fieldflow-react-front-fallback-pending'); fallback.setAttribute('data-fieldflow-react-front-fallback-hidden', '1'); }
    const refresh = (): void => setSnapshot(fieldFlowOperationalShellSnapshot(props));
    const observer = new MutationObserver(refresh);
    if (shell) observer.observe(shell, { subtree: true, attributes: true, attributeFilter: ['class'] });
    window.addEventListener('online', refresh);
    window.addEventListener('offline', refresh);
    shell?.addEventListener('routespro:panel-open', refresh as EventListener);
    return () => {
      observer.disconnect();
      window.removeEventListener('online', refresh);
      window.removeEventListener('offline', refresh);
      shell?.removeEventListener('routespro:panel-open', refresh as EventListener);
      if (fallback) { fallback.removeAttribute('data-fieldflow-react-front-fallback-hidden'); fallback.removeAttribute('data-fieldflow-react-front-fallback-pending'); }
    };
  }, [props.rootId, props.fallbackId]);

  const activate = (panel: string): void => {
    const shell = document.getElementById(props.rootId);
    const trigger = (shell?.querySelector(`button[data-panel="${panel}"]`) || shell?.querySelector(`[data-target="${panel}"]`)) as HTMLElement | null;
    trigger?.click();
    queueMicrotask(() => setSnapshot(fieldFlowOperationalShellSnapshot(props)));
  };

  const buttons = (Array.isArray(props.panels) ? props.panels : []).map((panel) =>
    element.createElement('button', {
      type: 'button',
      className: panel.key === snapshot.activePanel ? 'is-active' : '',
      onClick: () => activate(panel.key),
      'aria-current': panel.key === snapshot.activePanel ? 'page' : undefined,
      key: panel.key,
    }, panel.label)
  );

  return element.createElement('section', { className: 'ff-react-front-operational-shell', 'data-react-contract': FIELD_FLOW_REACT_OPERATIONAL_SHELL_CONTRACT },
    element.createElement('div', { className: 'ff-react-front-operational-status' },
      element.createElement('span', { className: snapshot.online ? 'is-online' : 'is-offline' }, snapshot.online ? 'Online' : 'Offline'),
      element.createElement('strong', null, 'Operação · ' + (props.panels.find((panel) => panel.key === snapshot.activePanel)?.label || 'Minha Rota'))
    ),
    element.createElement('nav', { className: 'ff-react-front-operational-nav', 'aria-label': 'Atalhos operacionais' }, ...buttons)
  );
}
