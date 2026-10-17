function fieldFlowMyRouteSnapshot(props: FieldFlowMyRouteToolbarProps): FieldFlowMyRouteSnapshot {
  const routeRoot = document.getElementById(props.routeRootId);
  const dateInput = routeRoot?.querySelector('#rp-date') as HTMLInputElement | null;
  const routeSelect = routeRoot?.querySelector('#rp-route-select') as HTMLSelectElement | null;
  const routes: FieldFlowRouteOption[] = routeSelect
    ? Array.from(routeSelect.options).filter((option) => option.value !== '').map((option) => ({ value: option.value, label: option.textContent || option.value }))
    : [];
  const content = routeRoot?.querySelector('#rp-content');
  return {
    date: String(dateInput?.value || ''),
    routeId: String(routeSelect?.value || ''),
    routes,
    loading: Boolean(content && /A carregar/i.test(String(content.textContent || ''))),
  };
}

function FieldFlowMyRouteToolbarIsland(props: FieldFlowMyRouteToolbarProps & { root: Element }): unknown {
  const { root } = props;
  const element = window.wp?.element;
  if (!element) return null;
  const [snapshot, setSnapshot] = element.useState<FieldFlowMyRouteSnapshot>(fieldFlowMyRouteSnapshot(props));

  element.useEffect(() => {
    const routeRoot = document.getElementById(props.routeRootId);
    const fallback = document.getElementById(props.fallbackId);
    if (fallback) { fallback.removeAttribute('data-fieldflow-react-front-fallback-pending'); fallback.setAttribute('data-fieldflow-react-front-fallback-hidden', '1'); }
    const refresh = (): void => setSnapshot(fieldFlowMyRouteSnapshot(props));
    const observer = new MutationObserver(refresh);
    if (routeRoot) observer.observe(routeRoot, { subtree: true, childList: true, attributes: true, characterData: true, attributeFilter: ['value', 'class', 'aria-selected'] });
    routeRoot?.addEventListener('change', refresh, true);
    routeRoot?.addEventListener('click', refresh, true);
    document.addEventListener('fieldflow:route-updated', refresh as EventListener);
    return () => {
      observer.disconnect();
      routeRoot?.removeEventListener('change', refresh, true);
      routeRoot?.removeEventListener('click', refresh, true);
      document.removeEventListener('fieldflow:route-updated', refresh as EventListener);
      if (fallback) { fallback.removeAttribute('data-fieldflow-react-front-fallback-hidden'); fallback.removeAttribute('data-fieldflow-react-front-fallback-pending'); }
    };
  }, [props.routeRootId, props.fallbackId]);

  const setDate = (value: string): void => {
    const routeRoot = document.getElementById(props.routeRootId);
    const input = routeRoot?.querySelector('#rp-date') as HTMLInputElement | null;
    if (!input) return;
    input.value = value;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    setSnapshot(fieldFlowMyRouteSnapshot(props));
  };
  const shiftDay = (delta: number): void => {
    const base = snapshot.date ? new Date(snapshot.date + 'T12:00:00') : new Date();
    base.setDate(base.getDate() + delta);
    setDate(`${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}-${String(base.getDate()).padStart(2, '0')}`);
  };
  const setRoute = (value: string): void => {
    const routeRoot = document.getElementById(props.routeRootId);
    const select = routeRoot?.querySelector('#rp-route-select') as HTMLSelectElement | null;
    if (!select) return;
    select.value = value;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    setSnapshot(fieldFlowMyRouteSnapshot(props));
  };

  return element.createElement('section', { className: 'ff-react-front-route-toolbar', 'data-react-contract': FIELD_FLOW_REACT_MY_ROUTE_CONTRACT },
    element.createElement('div', { className: 'ff-react-front-route-date' },
      element.createElement('button', { type: 'button', onClick: () => shiftDay(-1), 'aria-label': 'Dia anterior' }, '‹'),
      element.createElement('input', { type: 'date', value: snapshot.date, onChange: (event: unknown) => setDate(String((event as { target?: { value?: string } }).target?.value || '')) }),
      element.createElement('button', { type: 'button', onClick: () => shiftDay(1), 'aria-label': 'Dia seguinte' }, '›')
    ),
    element.createElement('div', { className: 'ff-react-front-route-picker' },
      element.createElement('span', null, snapshot.loading ? 'A atualizar…' : (snapshot.routes.length ? snapshot.routes.length + ' rota' + (snapshot.routes.length === 1 ? '' : 's') : 'Sem rotas')),
      element.createElement('select', { value: snapshot.routeId, disabled: snapshot.routes.length < 2, onChange: (event: unknown) => setRoute(String((event as { target?: { value?: string } }).target?.value || '')) },
        ...snapshot.routes.map((route) => element.createElement('option', { key: route.value, value: route.value }, route.label))
      )
    )
  );
}
