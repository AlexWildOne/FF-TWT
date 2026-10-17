const FIELD_FLOW_REACT_BACKOFFICE_CONTRACT = 'fieldflow-react-backoffice-foundation-v1';
const FIELD_FLOW_MOBILE_RELEASE_ISLAND = 'mobile-release-status';
const FIELD_FLOW_DEVICE_CONTROL_ISLAND = 'device-control-table';
const FIELD_FLOW_ANALYTICS_CONTROL_ISLAND = 'analytics-context-kpis';
const FIELD_FLOW_REACT_ANALYTICS_CONTRACT = 'fieldflow-react-analytics-foundation-v1';
const FIELD_FLOW_REACT_N1_COMPATIBILITY = "credentials: 'same-origin'";


function fieldFlowReactParseMobileProps(root: Element): FieldFlowMobileReleaseStatusProps | null {
  const parsed = fieldFlowReactRawProps(root) as FieldFlowMobileReleaseStatusProps | null;
  if (!parsed || parsed.contract !== FIELD_FLOW_REACT_BACKOFFICE_CONTRACT) return null;
  if (parsed.island !== FIELD_FLOW_MOBILE_RELEASE_ISLAND || !Array.isArray(parsed.platforms)) return null;
  return parsed;
}

function fieldFlowReactParseDeviceProps(root: Element): FieldFlowDeviceControlProps | null {
  const parsed = fieldFlowReactRawProps(root) as FieldFlowDeviceControlProps | null;
  if (!parsed || parsed.contract !== FIELD_FLOW_REACT_BACKOFFICE_CONTRACT) return null;
  if (parsed.island !== FIELD_FLOW_DEVICE_CONTROL_ISLAND || !parsed.listing || !Array.isArray(parsed.listing.rows)) return null;
  if (!parsed.ajaxUrl || !parsed.nonce) return null;
  return parsed;
}


function fieldFlowReactParseAnalyticsProps(root: Element): FieldFlowAnalyticsControlProps | null {
  const parsed = fieldFlowReactRawProps(root) as FieldFlowAnalyticsControlProps | null;
  if (!parsed || parsed.contract !== FIELD_FLOW_REACT_BACKOFFICE_CONTRACT) return null;
  if (parsed.sharedContract !== FIELD_FLOW_REACT_SHARED_CONTRACT) return null;
  if (parsed.island !== FIELD_FLOW_ANALYTICS_CONTROL_ISLAND || !parsed.values || !parsed.defaultValues) return null;
  if (!Array.isArray(parsed.clients) || !Array.isArray(parsed.projects) || !Array.isArray(parsed.routes) || !Array.isArray(parsed.users) || !Array.isArray(parsed.locations)) return null;
  return parsed;
}

function FieldFlowMobileReleaseStatusIsland(props: FieldFlowMobileReleaseStatusProps): unknown {
  const element = window.wp?.element;
  if (!element) return null;
  const platforms = props.platforms.length ? props.platforms : [];
  const [activeKey, setActiveKey] = element.useState<string>(platforms[0]?.key || 'android');
  const active = platforms.find((platform) => platform.key === activeKey) || platforms[0];

  const platformButtons = platforms.map((platform) => element.createElement(
    'button',
    {
      key: platform.key,
      type: 'button',
      className: 'ff-react-platform-tab' + (active?.key === platform.key ? ' is-active' : ''),
      'aria-pressed': active?.key === platform.key ? 'true' : 'false',
      onClick: () => setActiveKey(platform.key),
    },
    platform.label
  ));

  const statusPill = active
    ? element.createElement('span', { className: 'pill ' + (active.enabled ? 'good' : 'muted') }, active.enabled ? 'Ativo' : 'Desativado')
    : null;

  const detail = active ? element.createElement(
    'div',
    { className: 'ff-react-release-panel', 'data-platform': active.key },
    element.createElement('div', { className: 'ff-react-release-heading' },
      element.createElement('strong', null, active.label),
      statusPill
    ),
    element.createElement('dl', { className: 'ff-react-release-grid' },
      element.createElement('dt', null, 'Mínima'),
      element.createElement('dd', null, active.minimumVersion || 'n/d'),
      element.createElement('dt', null, 'Mais recente'),
      element.createElement('dd', null, active.latestVersion || 'n/d'),
      element.createElement('dt', null, 'Build'),
      element.createElement('dd', null, active.buildNumber || 'n/d')
    )
  ) : element.createElement('p', { className: 'description' }, 'Sem plataformas configuradas.');

  return element.createElement(
    'section',
    { className: 'ff-mobile-card ff-react-mobile-release-status', 'data-react-contract': FIELD_FLOW_REACT_BACKOFFICE_CONTRACT },
    element.createElement('div', { className: 'ff-react-card-heading' },
      element.createElement('div', null,
        element.createElement('h2', null, props.title),
        element.createElement('p', { className: 'description' }, props.description)
      ),
      element.createElement('span', { className: 'ff-react-badge' }, 'React island')
    ),
    element.createElement('div', { className: 'ff-react-runtime-strip' },
      element.createElement('span', null, 'Bridge ', element.createElement('strong', null, props.bridgeStatus)),
      element.createElement('span', null, 'Manutenção ', element.createElement('strong', null, props.maintenanceStatus))
    ),
    element.createElement('div', { className: 'ff-react-platform-tabs', role: 'group', 'aria-label': 'Plataforma móvel' }, ...platformButtons),
    detail
  );
}

function fieldFlowReactDuration(seconds: number): string {
  if (!Number.isFinite(seconds) || seconds <= 0) return '0 min';
  if (seconds < 3600) return Math.max(1, Math.floor(seconds / 60)) + ' min';
  if (seconds < 86400) return (Math.round((seconds / 3600) * 10) / 10) + ' h';
  return (Math.round((seconds / 86400) * 10) / 10) + ' d';
}

function fieldFlowReactBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let value = bytes;
  let index = 0;
  while (value >= 1024 && index < units.length - 1) {
    value /= 1024;
    index += 1;
  }
  const digits = index === 0 ? 0 : (value >= 10 ? 1 : 2);
  return value.toFixed(digits) + ' ' + units[index];
}

function fieldFlowReactLocale(): string {
  const runtime = (window as unknown as { FieldFlowI18n?: { getLocale?: () => string } }).FieldFlowI18n;
  return runtime && typeof runtime.getLocale === 'function' ? runtime.getLocale() : 'pt-PT';
}

function fieldFlowReactDate(value: string): string {
  if (!value) return 'Sem registo';
  const normalized = value.includes('T') ? value : value.replace(' ', 'T') + 'Z';
  const date = new Date(normalized);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(fieldFlowReactLocale(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
}

function fieldFlowReactLeaseDate(milliseconds: number): string {
  if (!Number.isFinite(milliseconds) || milliseconds <= 0) return '';
  const date = new Date(milliseconds);
  if (Number.isNaN(date.getTime())) return '';
  return new Intl.DateTimeFormat(fieldFlowReactLocale(), { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
}


function FieldFlowDeviceControlIsland(props: FieldFlowDeviceControlProps): unknown {
  const element = window.wp?.element;
  if (!element) return null;
  const [listing, setListing] = element.useState<FieldFlowReactDeviceListing>(props.listing);
  const [search, setSearch] = element.useState<string>(props.listing.search || '');
  const [platform, setPlatform] = element.useState<string>(props.listing.platform || '');
  const [perPage, setPerPage] = element.useState<number>(props.listing.perPage || 25);
  const [loading, setLoading] = element.useState<boolean>(false);
  const [busyKey, setBusyKey] = element.useState<string>('');
  const [notice, setNotice] = element.useState<string>('');
  const [error, setError] = element.useState<string>('');
  const [requests] = element.useState<{
    sequence: number; active: boolean; list: AbortController | null; action: AbortController | null;
    query: { page: number; search: string; platform: string; perPage: number };
  }>({ sequence: 0, active: true, list: null, action: null, query: props.listing });

  element.useEffect(() => {
    requests.active = true;
    return () => {
      requests.active = false;
      requests.sequence += 1;
      requests.list?.abort();
      requests.action?.abort();
    };
  }, [requests]);

  const loadPage = async (page: number, nextSearch = search, nextPlatform = platform, nextPerPage = perPage): Promise<void> => {
    if (!requests.active) return;
    requests.list?.abort();
    const controller = new AbortController();
    requests.list = controller;
    const sequence = ++requests.sequence;
    requests.query = { page, search: nextSearch, platform: nextPlatform, perPage: nextPerPage };
    setLoading(true);
    setError('');
    try {
      const response = await fieldFlowReactPost<FieldFlowReactDeviceListing>(props.ajaxUrl, {
        action: 'fieldflow_react_devices_list',
        nonce: props.nonce,
        page: String(Math.max(1, page)),
        per_page: String(nextPerPage),
        search: nextSearch,
        platform: nextPlatform,
      }, controller.signal);
      if (requests.active && sequence === requests.sequence && response.data) setListing(response.data);
    } catch (_requestError) {
      if (requests.active && sequence === requests.sequence && !controller.signal.aborted) setError('Não foi possível atualizar a lista. O fallback PHP continua disponível ao recarregar a página.');
    } finally {
      if (requests.active && sequence === requests.sequence) {
        requests.list = null;
        setLoading(false);
      }
    }
  };

  const runAction = async (row: FieldFlowReactDeviceRow, action: string, danger = false): Promise<void> => {
    if (!requests.active || requests.action) return;
    if (danger && !window.confirm('Confirmar esta ação?')) return;
    const key = row.deviceId + ':' + action;
    const controller = new AbortController();
    requests.action = controller;
    setBusyKey(key);
    setError('');
    setNotice('');
    try {
      await fieldFlowReactPost<Record<string, unknown>>(props.ajaxUrl, {
        action: 'fieldflow_react_device_action',
        nonce: props.nonce,
        device_action: action,
        user_id: String(row.userId),
        session_id: String(row.sessionId),
        device_id: row.deviceId,
      }, controller.signal);
      if (!requests.active) return;
      setNotice('Dispositivo atualizado sem recarregar a página.');
      const query = requests.query;
      await loadPage(query.page, query.search, query.platform, query.perPage);
    } catch (_requestError) {
      if (requests.active && !controller.signal.aborted) setError('Não foi possível executar a ação no dispositivo.');
    } finally {
      requests.action = null;
      if (requests.active) setBusyKey('');
    }
  };

  const button = (row: FieldFlowReactDeviceRow, action: string, label: string, danger = false): unknown => {
    const key = row.deviceId + ':' + action;
    const busy = busyKey === key;
    return element.createElement('button', {
      key: action,
      type: 'button',
      className: 'button button-small' + (danger ? ' button-link-delete' : ''),
      disabled: loading || busyKey !== '',
      onClick: () => { void runAction(row, action, danger); },
    }, busy ? 'A executar…' : label);
  };

  const rows = listing.rows.map((row) => {
    const healthLabel = row.healthStatus === 'healthy' ? 'Saudável' : (row.healthStatus === 'attention' ? 'Atenção' : 'Observar');
    const healthClass = row.healthStatus === 'healthy' ? 'good' : (row.healthStatus === 'attention' ? 'bad' : 'warn');
    const networkLabels: Record<string, string> = { online: 'Online', degraded: 'Rede fraca', offline: 'Offline', auth_required: 'Sessão necessária', revoked: 'Revogado', unknown: 'A verificar' };
    const trustLabels: Record<string, string> = { valid: 'Lease 6B válida', pending: 'Lease pendente', expired: 'Lease expirada', missing: 'Sem lease', not_supplied: 'Sem lease', stable_revalidation_pending: 'Revalidação 6B pendente' };
    const trustClass = row.trustLeaseState === 'valid' ? 'good' : (row.trustLeaseState === 'expired' ? 'bad' : 'warn');
    const actions: unknown[] = [];
    actions.push(row.trusted ? button(row, 'untrust', 'Retirar confiança') : button(row, 'trust', 'Confiar'));
    actions.push(row.offlineAllowed ? button(row, 'disable_offline', 'Desativar offline') : button(row, 'enable_offline', 'Ativar offline'));
    actions.push(button(row, 'clear_cache', 'Limpar cache'));
    actions.push(row.blocked ? button(row, 'unblock', 'Desbloquear') : button(row, 'block', 'Bloquear', true));
    if (row.active) actions.push(button(row, 'force_logout', 'Forçar logout', true));
    const leaseUntil = fieldFlowReactLeaseDate(row.trustLeaseExpiresAtMs);

    return element.createElement('tr', { key: String(row.sessionId) + ':' + row.deviceId },
      element.createElement('td', null,
        element.createElement('strong', null, row.displayName),
        element.createElement('br'),
        element.createElement('small', null, row.email || 'Sem email'),
        element.createElement('hr'),
        element.createElement('strong', null, row.deviceName),
        element.createElement('br'),
        element.createElement('code', null, row.deviceId),
        element.createElement('br'),
        element.createElement('small', null, row.platform.toUpperCase() + ' · App ' + row.appVersion),
        element.createElement('br'),
        element.createElement('small', null, 'Runtime ' + row.runtimeBuild + ' · visto ' + fieldFlowReactDate(row.lastSeen))
      ),
      element.createElement('td', null,
        element.createElement('span', { className: 'ff-pill ' + healthClass }, healthLabel + ' · ' + row.healthScore + '/100'),
        element.createElement('br'),
        element.createElement('small', null, 'ACK: ' + row.ackErrors + ' · recuperações: ' + row.recoveries),
        element.createElement('br'),
        element.createElement('small', null, 'Retomas da app: ' + row.appResumes)
      ),
      element.createElement('td', null,
        element.createElement('strong', null, row.queuePending + ' pendentes'),
        element.createElement('br'),
        element.createElement('span', null, row.queueFailed + ' falhas · ' + row.queueConflicts + ' conflitos'),
        element.createElement('br'),
        element.createElement('small', null, 'Mais antigo: ' + (row.oldestPendingAgeSeconds > 0 ? fieldFlowReactDuration(row.oldestPendingAgeSeconds) : 'Sem pendentes')),
        element.createElement('br'),
        element.createElement('small', null, 'Media pendente: ' + row.mediaPendingFiles + ' ficheiros · ' + fieldFlowReactBytes(row.mediaPendingBytes)),
        element.createElement('br'),
        element.createElement('small', null, 'Última sync: ' + fieldFlowReactDate(row.lastSync))
      ),
      element.createElement('td', null,
        element.createElement('span', { className: 'ff-pill ' + (row.networkState === 'online' ? 'good' : (row.networkState === 'degraded' ? 'warn' : '')) }, networkLabels[row.networkState] || row.networkState),
        element.createElement('br'),
        element.createElement('strong', null, 'Storage ' + row.storagePercent + '%'),
        element.createElement('br'),
        element.createElement('small', null, row.storagePersistence || 'unknown')
      ),
      element.createElement('td', null,
        element.createElement('span', { className: 'ff-pill ' + (row.trusted ? 'good' : '') }, row.trusted ? 'Confiável' : 'Normal'),
        ' ',
        element.createElement('span', { className: 'ff-pill ' + (row.offlineAllowed ? 'good' : 'bad') }, row.offlineAllowed ? 'Offline ativo' : 'Offline bloqueado'),
        element.createElement('br'),
        element.createElement('span', { className: 'ff-pill ' + trustClass }, trustLabels[row.trustLeaseState] || row.trustLeaseState),
        leaseUntil ? element.createElement('small', { className: 'ff-react-device-line' }, 'Lease até ' + leaseUntil) : null,
        row.trustLeaseRelease ? element.createElement('small', { className: 'ff-react-device-line' }, 'Release ' + row.trustLeaseRelease) : null,
        element.createElement('small', { className: 'ff-react-device-line' }, 'Cache v' + row.cacheGeneration),
        element.createElement('small', { className: 'ff-react-device-line' }, row.blocked ? 'Bloqueado' : (row.active ? 'Sessão ativa' : 'Revogado ou expirado'))
      ),
      element.createElement('td', null, element.createElement('div', { className: 'ff-dev-actions ff-react-device-actions' }, ...actions))
    );
  });

  const start = listing.total > 0 ? ((listing.page - 1) * listing.perPage) + 1 : 0;
  const end = Math.min(listing.total, listing.page * listing.perPage);
  const pagination = listing.pages > 1 ? element.createElement('div', { className: 'ff-react-device-pagination' },
    element.createElement('button', { type: 'button', className: 'button', disabled: loading || listing.page <= 1, onClick: () => { void loadPage(1); } }, '« Primeira'),
    element.createElement('button', { type: 'button', className: 'button', disabled: loading || listing.page <= 1, onClick: () => { void loadPage(listing.page - 1); } }, '‹ Anterior'),
    element.createElement('span', null, 'Página ', element.createElement('strong', null, String(listing.page)), ' de ', element.createElement('strong', null, String(listing.pages))),
    element.createElement('button', { type: 'button', className: 'button', disabled: loading || listing.page >= listing.pages, onClick: () => { void loadPage(listing.page + 1); } }, 'Seguinte ›'),
    element.createElement('button', { type: 'button', className: 'button', disabled: loading || listing.page >= listing.pages, onClick: () => { void loadPage(listing.pages); } }, 'Última »')
  ) : null;

  return element.createElement('section', { className: 'ff-react-device-control', 'data-react-contract': FIELD_FLOW_REACT_BACKOFFICE_CONTRACT },
    element.createElement('div', { className: 'ff-react-device-heading' },
      element.createElement('div', null,
        element.createElement('div', { className: 'ff-react-device-title-row' }, element.createElement('h2', null, props.title), element.createElement('span', { className: 'ff-react-badge' }, 'React island')),
        element.createElement('p', { className: 'description' }, props.description)
      ),
      element.createElement('div', { className: 'ff-react-device-count' }, element.createElement('strong', null, String(listing.total)), ' dispositivos')
    ),
    element.createElement('div', { role: 'status', 'aria-live': 'polite', 'aria-atomic': 'true', className: notice ? 'notice notice-success inline ff-react-device-notice' : undefined }, notice ? element.createElement('p', null, notice) : null),
    element.createElement('div', { role: 'alert', 'aria-atomic': 'true', className: error ? 'notice notice-error inline ff-react-device-notice' : undefined }, error ? element.createElement('p', null, error) : null),
    element.createElement('form', {
      className: 'ff-react-device-toolbar',
      onSubmit: (event: Event) => { event.preventDefault(); void loadPage(1); },
    },
      element.createElement('input', {
        type: 'search',
        value: search,
        placeholder: 'Utilizador, email, dispositivo, plataforma ou versão',
        'aria-label': 'Pesquisar dispositivos',
        onInput: (event: Event) => setSearch((event.target as HTMLInputElement).value),
      }),
      element.createElement('select', {
        value: platform,
        'aria-label': 'Filtrar por plataforma',
        onChange: (event: Event) => setPlatform((event.target as HTMLSelectElement).value),
      }, ...props.platformOptions.map((option) => element.createElement('option', { key: option.value || 'all', value: option.value }, option.label))),
      element.createElement('select', {
        value: String(perPage),
        'aria-label': 'Dispositivos por página',
        onChange: (event: Event) => setPerPage(Number((event.target as HTMLSelectElement).value) || 25),
      }, ...[25, 50, 100].map((value) => element.createElement('option', { key: String(value), value: String(value) }, String(value) + ' / página'))),
      element.createElement('button', { className: 'button button-primary', type: 'submit', disabled: loading }, loading ? 'A atualizar…' : 'Filtrar'),
      element.createElement('button', { className: 'button', type: 'button', disabled: loading, onClick: () => { setSearch(''); setPlatform(''); setPerPage(25); void loadPage(1, '', '', 25); } }, 'Limpar')
    ),
    element.createElement('div', { className: 'ff-react-device-summary', role: 'status', 'aria-live': 'polite', 'aria-busy': loading },
      element.createElement('span', null, loading ? 'A atualizar…' : (listing.total > 0 ? ('A mostrar ' + start + ' a ' + end) : 'Sem resultados')),
      loading ? element.createElement('span', { className: 'spinner is-active' }) : null
    ),
    pagination,
    element.createElement('div', { className: 'ff-dev-table ff-react-device-table-wrap' },
      element.createElement('table', { className: 'widefat striped' },
        element.createElement('thead', null, element.createElement('tr', null,
          element.createElement('th', null, 'Utilizador / dispositivo'),
          element.createElement('th', null, 'Saúde offline'),
          element.createElement('th', null, 'Fila / confirmação'),
          element.createElement('th', null, 'Rede / armazenamento'),
          element.createElement('th', null, 'Política'),
          element.createElement('th', null, 'Ações')
        )),
        element.createElement('tbody', null, ...(rows.length ? rows : [element.createElement('tr', { key: 'empty' }, element.createElement('td', { colSpan: 6 }, listing.search || listing.platform ? 'Não foram encontrados dispositivos para estes filtros.' : 'Ainda não existem dispositivos registados.'))]))
      )
    ),
    pagination
  );
}


function FieldFlowAnalyticsControlIsland(props: FieldFlowAnalyticsControlProps): unknown {
  const element = window.wp?.element;
  if (!element) return null;
  const [values, setValues] = element.useState<FieldFlowReactAnalyticsValues>({ ...props.values });
  const [status, setStatus] = element.useState<FieldFlowAnalyticsStateEventDetail>({ kind: 'loading', title: 'A preparar contexto', detail: 'A cruzar as fontes disponíveis.' });
  const [scores, setScores] = element.useState<Record<string, FieldFlowReactAnalyticsScore | null>>({ health: null, execution: null, risk: null, opportunity: null, stock: null, confidence: null });

  element.useEffect(() => {
    const fallback = document.getElementById(props.fallbackId);
    if (fallback) fallback.classList.add('is-react-hidden');
    const stateHandler = (event: Event): void => {
      const detail = (event as CustomEvent<FieldFlowAnalyticsStateEventDetail>).detail || {};
      setStatus({ kind: detail.kind || '', title: detail.title || '', detail: detail.detail || '' });
    };
    const dataHandler = (event: Event): void => {
      const detail = (event as CustomEvent<FieldFlowAnalyticsDataEventDetail>).detail || {};
      const insights = detail.insights || {};
      const decision = insights.decision_scores || {};
      setScores({
        health: decision.operational_health || { score: insights.executive?.score, label: insights.executive?.label, explanation: 'Índice operacional do motor FieldFlow.' },
        execution: decision.execution || null,
        risk: decision.risk || null,
        opportunity: decision.opportunity || null,
        stock: decision.stock_risk || null,
        confidence: decision.confidence || { score: insights.confidence_detail?.score, label: insights.confidence || '', explanation: insights.confidence_detail?.explanation },
      });
    };
    window.addEventListener('fieldflow:analytics2-state', stateHandler as EventListener);
    window.addEventListener('fieldflow:analytics2-data', dataHandler as EventListener);
    const snapshot = window.FieldFlowAnalytics2?.snapshot();
    if (snapshot?.state) setStatus(snapshot.state);
    if (snapshot?.data) dataHandler(new CustomEvent<FieldFlowAnalyticsDataEventDetail>('fieldflow:analytics2-data', { detail: snapshot.data }));
    return () => {
      if (fallback) fallback.classList.remove('is-react-hidden');
      window.removeEventListener('fieldflow:analytics2-state', stateHandler as EventListener);
      window.removeEventListener('fieldflow:analytics2-data', dataHandler as EventListener);
    };
  }, [props.fallbackId]);

  const normalize = (candidate: FieldFlowReactAnalyticsValues): FieldFlowReactAnalyticsValues => {
    const next = { ...candidate };
    const project = props.projects.find((option) => option.value === next.project_id);
    if (project && next.client_id !== '0' && project.clientId && project.clientId !== next.client_id) next.project_id = '0';
    const route = props.routes.find((option) => option.value === next.route_id);
    if (route && ((next.client_id !== '0' && route.clientId && route.clientId !== next.client_id) || (next.project_id !== '0' && route.projectId && route.projectId !== next.project_id))) next.route_id = '0';
    const location = props.locations.find((option) => option.value === next.location_id);
    if (location && ((next.client_id !== '0' && location.clientId && location.clientId !== next.client_id) || (next.project_id !== '0' && location.projectId && location.projectId !== next.project_id))) next.location_id = '0';
    return next;
  };

  const updateValue = (key: keyof FieldFlowReactAnalyticsValues, value: string): void => {
    const next = normalize({ ...values, [key]: value });
    setValues(next);
  };

  const apply = (nextValues: FieldFlowReactAnalyticsValues): void => {
    const normalized = normalize(nextValues);
    setValues(normalized);
    if (window.FieldFlowAnalytics2) {
      window.FieldFlowAnalytics2.applyContext(normalized);
      return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('page', 'routespro-assignments-hub');
    url.searchParams.set('tab', 'analytics');
    Object.entries(normalized).forEach(([key, value]) => {
      if (!value || value === '0') url.searchParams.delete(key);
      else url.searchParams.set(key, value);
    });
    url.hash = 'ff-analytics2-workspace';
    window.location.assign(url.toString());
  };

  const range = (days: number): void => {
    const dates = fieldFlowReactTodayRange(days, values.to);
    apply({ ...values, ...dates });
  };

  const optionNodes = (items: FieldFlowReactAnalyticsOption[], placeholder: string): unknown[] => [
    element.createElement('option', { key: '0', value: '0' }, placeholder),
    ...items.map((option) => element.createElement('option', { key: option.value, value: option.value }, option.label)),
  ];
  const projects = props.projects.filter((option) => values.client_id === '0' || !option.clientId || option.clientId === values.client_id);
  const routes = props.routes.filter((option) => (values.client_id === '0' || !option.clientId || option.clientId === values.client_id) && (values.project_id === '0' || !option.projectId || option.projectId === values.project_id));
  const locations = props.locations.filter((option) => (values.client_id === '0' || !option.clientId || option.clientId === values.client_id) && (values.project_id === '0' || !option.projectId || option.projectId === values.project_id));
  const scoreLabels: Record<string, [string, string]> = {
    health: ['Operational Health', 'Saúde integrada do contexto'],
    execution: ['Execution Score', 'Execução e pontualidade'],
    risk: ['Risk Score', 'Alertas determinísticos'],
    opportunity: ['Opportunity Score', 'Margem operacional observável'],
    stock: ['Stock Risk', 'Risco do snapshot atual'],
    confidence: ['Confidence Score', 'Cobertura e confiança das fontes'],
  };
  const nf = new Intl.NumberFormat(fieldFlowReactLocale(), { maximumFractionDigits: 1 });
  const scoreCards = Object.entries(scoreLabels).map(([key, labels]) => {
    const score = scores[key];
    const hasScore = score && score.score !== null && score.score !== undefined && Number.isFinite(Number(score.score));
    return element.createElement('article', { key, className: 'ff-a2-score ff-react-analytics-score' + (hasScore ? '' : ' is-empty'), 'data-direction': score?.direction || '' },
      element.createElement('span', null, labels[0]),
      element.createElement('strong', null, hasScore ? nf.format(Number(score?.score)) : 'n.d.'),
      element.createElement('b', null, score?.label || (status.kind === 'loading' ? 'A calcular' : 'Sem dados')),
      element.createElement('small', null, score?.explanation || labels[1])
    );
  });

  const field = (label: string, key: keyof FieldFlowReactAnalyticsValues, node: unknown): unknown => element.createElement('label', { key }, label, node);
  return element.createElement('section', { className: 'ff-react-analytics-control', 'data-react-contract': FIELD_FLOW_REACT_ANALYTICS_CONTRACT },
    element.createElement('div', { className: 'ff-react-analytics-heading' },
      element.createElement('div', null, element.createElement('h4', null, props.title), element.createElement('p', null, props.description)),
      element.createElement('div', { className: 'ff-react-analytics-badges' }, element.createElement('span', { className: 'ff-react-badge' }, 'React'), element.createElement('span', { className: 'ff-react-badge is-live' }, 'Sem refresh'))
    ),
    element.createElement('div', { className: 'ff-react-analytics-state is-' + (status.kind || 'ready') }, element.createElement('strong', null, status.title || 'Analytics pronto'), element.createElement('span', null, status.detail || 'Contexto sincronizado.')),
    element.createElement('form', { className: 'ff-a2-filter ff-react-analytics-filter', onSubmit: (event: Event) => { event.preventDefault(); apply(values); } },
      element.createElement('div', { className: 'ff-a2-filter-head' },
        element.createElement('div', null, element.createElement('h4', null, 'Contexto universal'), element.createElement('p', null, 'Filtros React ligados ao mesmo motor Analytics 2.0.')),
        element.createElement('div', { className: 'ff-a2-quick' },
          element.createElement('button', { type: 'button', onClick: () => range(7) }, '7 dias'),
          element.createElement('button', { type: 'button', onClick: () => range(30) }, '30 dias'),
          element.createElement('button', { type: 'button', onClick: () => range(90) }, '90 dias')
        )
      ),
      element.createElement('div', { className: 'ff-a2-filter-grid' },
        field('De', 'from', element.createElement('input', { type: 'date', value: values.from, onChange: (event: Event) => updateValue('from', (event.target as HTMLInputElement).value) })),
        field('Até', 'to', element.createElement('input', { type: 'date', value: values.to, onChange: (event: Event) => updateValue('to', (event.target as HTMLInputElement).value) })),
        field('Cliente', 'client_id', element.createElement('select', { value: values.client_id, onChange: (event: Event) => updateValue('client_id', (event.target as HTMLSelectElement).value) }, ...optionNodes(props.clients, 'Todos os clientes'))),
        field('Campanha / Projeto', 'project_id', element.createElement('select', { value: values.project_id, onChange: (event: Event) => updateValue('project_id', (event.target as HTMLSelectElement).value) }, ...optionNodes(projects, 'Todas as campanhas'))),
        field('Rota', 'route_id', element.createElement('select', { value: values.route_id, onChange: (event: Event) => updateValue('route_id', (event.target as HTMLSelectElement).value) }, ...optionNodes(routes, 'Todas as rotas'))),
        field('Operativo / Owner', 'user_id', element.createElement('select', { value: values.user_id, onChange: (event: Event) => updateValue('user_id', (event.target as HTMLSelectElement).value) }, ...optionNodes(props.users, 'Toda a equipa'))),
        field('Loja / PDV', 'location_id', element.createElement('select', { value: values.location_id, onChange: (event: Event) => updateValue('location_id', (event.target as HTMLSelectElement).value) }, ...optionNodes(locations, 'Todas as lojas')))
      ),
      element.createElement('div', { className: 'ff-a2-filter-actions' },
        element.createElement('button', { type: 'submit', className: 'button button-primary' }, status.kind === 'loading' ? 'A atualizar…' : 'Aplicar contexto'),
        element.createElement('button', { type: 'button', className: 'button', onClick: () => apply({ ...props.defaultValues }) }, 'Limpar filtros'),
        element.createElement('span', { className: 'ff-a2-filter-note' }, 'O URL e os restantes painéis ficam sincronizados sem recarregar a página.')
      )
    ),
    element.createElement('div', { className: 'ff-a2-score-grid ff-react-analytics-score-grid' }, ...scoreCards)
  );
}

const fieldFlowReactRoots = new Map<Element, () => void>();

function fieldFlowReactRender(root: Element, node: unknown): boolean {
  const element = window.wp?.element;
  if (!element || fieldFlowReactRoots.has(root)) return false;
  if (typeof element.createRoot === 'function') {
    const reactRoot = element.createRoot(root);
    fieldFlowReactRoots.set(root, () => reactRoot.unmount());
    try { reactRoot.render(node); }
    catch (_error) {
      reactRoot.unmount();
      fieldFlowReactRoots.delete(root);
      return false;
    }
  } else if (typeof element.render === 'function' && typeof element.unmountComponentAtNode === 'function') {
    fieldFlowReactRoots.set(root, () => { element.unmountComponentAtNode!(root); });
    try { element.render(node, root); }
    catch (_error) {
      element.unmountComponentAtNode(root);
      fieldFlowReactRoots.delete(root);
      return false;
    }
  } else {
    return false;
  }
  root.setAttribute('data-fieldflow-react-mounted', '1');
  return true;
}

function fieldFlowReactMountRoot(root: Element): boolean {
  const element = window.wp?.element;
  if (!element) return false;
  const island = String(root.getAttribute('data-fieldflow-react-island') || '');
  if (island === FIELD_FLOW_MOBILE_RELEASE_ISLAND) {
    const props = fieldFlowReactParseMobileProps(root);
    return props ? fieldFlowReactRender(root, element.createElement(FieldFlowMobileReleaseStatusIsland, props as unknown as Record<string, unknown>)) : false;
  }
  if (island === FIELD_FLOW_DEVICE_CONTROL_ISLAND) {
    const props = fieldFlowReactParseDeviceProps(root);
    return props ? fieldFlowReactRender(root, element.createElement(FieldFlowDeviceControlIsland, props as unknown as Record<string, unknown>)) : false;
  }
  if (island === FIELD_FLOW_ANALYTICS_CONTROL_ISLAND) {
    const props = fieldFlowReactParseAnalyticsProps(root);
    return props ? fieldFlowReactRender(root, element.createElement(FieldFlowAnalyticsControlIsland, props as unknown as Record<string, unknown>)) : false;
  }
  return false;
}

function fieldFlowReactMountAll(): number {
  fieldFlowReactRoots.forEach((unmount, root) => {
    if (root.isConnected !== false) return;
    unmount();
    fieldFlowReactRoots.delete(root);
    root.removeAttribute('data-fieldflow-react-mounted');
  });
  const roots = document.querySelectorAll('[data-fieldflow-react-island]');
  let mounted = 0;
  roots.forEach((root) => {
    if (fieldFlowReactRoots.has(root)) return;
    if (fieldFlowReactMountRoot(root)) mounted += 1;
  });
  return mounted;
}
