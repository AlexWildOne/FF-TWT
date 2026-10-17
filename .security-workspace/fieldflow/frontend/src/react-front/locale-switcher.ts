const FIELD_FLOW_REACT_LOCALE_SWITCHER_CONTRACT = 'fieldflow-react-locale-switcher-v1';
const FIELD_FLOW_LOCALE_BRAND_SCOPE_CONTRACT = 'fieldflow-locale-brand-scope-continuity-v1';
const FIELD_FLOW_APP_SHELL_LOCALE_CONTRACT = 'fieldflow-app-shell-locale-runtime-v1';
const FIELD_FLOW_BROWSERLIKE_TRANSLATION_CONTRACT = 'fieldflow-browserlike-auto-translation-v1';
const FIELD_FLOW_ADAPTIVE_LOCALE_DOCK_CONTRACT = 'fieldflow-adaptive-locale-dock-v1';

interface FieldFlowLocaleLabels {
  title: string;
  subtitle: string;
  current: string;
  close: string;
}

type FieldFlowLocaleBrandStyle = Record<string, string>;

function fieldFlowLocaleNormalise(value: string): string {
  const raw = String(value || '').replace('_', '-').trim();
  const aliases: Record<string, string> = {
    pt: 'pt-PT', 'pt-pt': 'pt-PT',
    en: 'en-GB', 'en-gb': 'en-GB', 'en-us': 'en-GB',
    es: 'es-ES', 'es-es': 'es-ES',
    fr: 'fr-FR', 'fr-fr': 'fr-FR',
    zh: 'zh-CN', 'zh-cn': 'zh-CN', 'zh-hans': 'zh-CN', 'zh-hans-cn': 'zh-CN',
  };
  return aliases[raw.toLowerCase()] || raw;
}

function fieldFlowLocaleLabels(locale: string): FieldFlowLocaleLabels {
  if (locale === 'en-GB') return { title: 'Language', subtitle: 'Choose your FieldFlow language', current: 'Current language', close: 'Close languages' };
  if (locale === 'es-ES') return { title: 'Idioma', subtitle: 'Elige el idioma de FieldFlow', current: 'Idioma actual', close: 'Cerrar idiomas' };
  if (locale === 'fr-FR') return { title: 'Langue', subtitle: 'Choisissez la langue de FieldFlow', current: 'Langue actuelle', close: 'Fermer les langues' };
  if (locale === 'zh-CN') return { title: '语言', subtitle: '选择 FieldFlow 语言', current: '当前语言', close: '关闭语言菜单' };
  return { title: 'Idioma', subtitle: 'Escolhe o idioma do FieldFlow', current: 'Idioma atual', close: 'Fechar idiomas' };
}

function fieldFlowLocaleItems(): FieldFlowLocaleConfigItem[] {
  const items = Array.isArray(window.FieldFlowI18nConfig?.enabledLocales)
    ? window.FieldFlowI18nConfig!.enabledLocales
    : [];
  return items
    .map((item) => ({
      code: fieldFlowLocaleNormalise(String(item?.code || '')),
      label: String(item?.label || item?.code || ''),
      flag: String(item?.flag || '🌐'),
      html_lang: String(item?.html_lang || item?.code || ''),
    }))
    .filter((item) => Boolean(item.code));
}

function fieldFlowLocalePortalRoot(): Element | null {
  if (typeof document.querySelector !== 'function') return null;
  const configClientId = Number(window.FieldFlowI18nConfig?.clientId || 0) || 0;
  if (configClientId > 0) {
    const exact = document.querySelector(`[data-fieldflow-portal][data-client-id="${configClientId}"], [data-fieldflow-locale-host="1"][data-client-id="${configClientId}"]`);
    if (exact) return exact;
  }
  return document.querySelector('#routespro-client-portal, #routespro-app, [data-fieldflow-locale-host="1"], [data-fieldflow-portal]');
}

function fieldFlowLocaleBrandStyle(): FieldFlowLocaleBrandStyle {
  const source = fieldFlowLocalePortalRoot();
  if (!source || typeof window.getComputedStyle !== 'function') return {};
  const computed = window.getComputedStyle(source);
  const read = (name: string, fallback = ''): string => String(computed.getPropertyValue(name) || '').trim() || fallback;
  const primary = read('--ff-primary', read('--rp-primary-solid', '#2563eb'));
  const accent = read('--ff-accent', read('--rp-accent-solid', '#06b6d4'));
  return {
    '--ff-lang-primary': primary,
    '--ff-lang-accent': accent,
    '--ff-lang-surface': read('--ff-surface', '#ffffff'),
    '--ff-lang-text': read('--ff-text', '#0f172a'),
    '--ff-lang-muted': read('--ff-muted', '#64748b'),
    '--ff-lang-border': read('--ff-border', '#dbe4ef'),
    '--ff-lang-button-text': read('--ff-button-text', '#ffffff'),
    '--ff-lang-button-hover-text': read('--ff-button-hover-text', '#ffffff'),
    '--ff-lang-link-hover': read('--ff-link-hover', primary),
    '--ff-lang-button-radius': read('--ff-button-radius', '12px'),
  };
}

function fieldFlowReactLocaleSwitcherComponent({ root }: { root: Element }): unknown {
  const element = window.wp?.element;
  const runtime = window.FieldFlowI18n;
  if (!element || !runtime) return null;
  const initialLocale = fieldFlowLocaleNormalise(runtime.getLocale());
  const [locale, setLocaleState] = element.useState<string>(initialLocale);
  const [open, setOpen] = element.useState<boolean>(false);
  const [docked, setDocked] = element.useState<boolean>(false);
  const [scrolling, setScrolling] = element.useState<boolean>(false);
  const [clientId, setClientIdState] = element.useState<number>(runtime.getClientId());
  const [brandStyle, setBrandStyle] = element.useState<FieldFlowLocaleBrandStyle>(fieldFlowLocaleBrandStyle());
  const [revision, setRevision] = element.useState<{ seq: number }>({ seq: 0 });
  void revision;
  const items = fieldFlowLocaleItems();
  const labels = fieldFlowLocaleLabels(locale);
  const active = items.find((item) => item.code === locale) || items[0];
  // If BO explicitly enables front/app language switching, keep the globe visible
  // even when only one locale is temporarily enabled. This makes client policy
  // visible and lets a later scope/config refresh populate options without remount.
  const canSwitch = Boolean(window.FieldFlowI18nConfig?.allowUserSwitch) && items.length >= 1;

  element.useEffect(() => {
    let scrollStopTimer: number | null = null;
    const syncBrand = (): void => setBrandStyle(fieldFlowLocaleBrandStyle());
    const forceConfigRefresh = (): void => setRevision({ seq: Date.now() });
    const onLocale = (event: Event): void => {
      const detail = (event as CustomEvent<{ locale?: string }>).detail || {};
      const next = fieldFlowLocaleNormalise(String(detail.locale || runtime.getLocale()));
      if (next) setLocaleState(next);
      forceConfigRefresh();
    };
    const onContext = (event: Event): void => {
      const detail = (event as CustomEvent<{ clientId?: number; locale?: string }>).detail || {};
      const nextClientId = Number(detail.clientId ?? runtime.getClientId()) || 0;
      setClientIdState(nextClientId);
      const nextLocale = fieldFlowLocaleNormalise(String(detail.locale || runtime.getLocale()));
      if (nextLocale) setLocaleState(nextLocale);
      forceConfigRefresh();
      syncBrand();
    };
    const onPointer = (event: Event): void => {
      const target = event.target instanceof Element ? event.target : null;
      if (target?.closest('#fieldflow-react-locale-switcher-root')) return;
      setOpen(false);
      setDocked(true);
    };
    const onScroll = (event: Event): void => {
      const target = event.target instanceof Element ? event.target : null;
      if (target?.closest('#fieldflow-react-locale-switcher-root')) return;
      setOpen(false);
      setDocked(true);
      setScrolling(true);
      if (scrollStopTimer !== null && typeof window.clearTimeout === 'function') window.clearTimeout(scrollStopTimer);
      if (typeof window.setTimeout === 'function') {
        scrollStopTimer = window.setTimeout(() => {
          setScrolling(false);
          scrollStopTimer = null;
        }, 180);
      }
    };
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape' && root.contains(document.activeElement)) {
        setOpen(false);
        root.querySelector<HTMLButtonElement>('.ff-language-trigger')?.focus();
      }
    };
    const onScope = (): void => {
      forceConfigRefresh();
      syncBrand();
    };
    const onResume = (): void => {
      forceConfigRefresh();
      syncBrand();
      setDocked(false);
      setScrolling(false);
      if (typeof runtime.reconcile === 'function') runtime.reconcile('react-resume');
    };
    if (typeof window.addEventListener === 'function') {
      window.addEventListener('fieldflow:locale-changed', onLocale as EventListener);
      window.addEventListener('fieldflow:i18n-context-changed', onContext as EventListener);
      window.addEventListener('pageshow', onResume as EventListener);
      window.addEventListener('focus', onResume as EventListener);
      window.addEventListener('scroll', onScroll as EventListener, { passive: true });
    }
    if (typeof document.addEventListener === 'function') {
      document.addEventListener('pointerdown', onPointer, true);
      document.addEventListener('keydown', onKey);
      document.addEventListener('routespro:scope-change', onScope as EventListener);
      document.addEventListener('fieldflow:portal-ready', onResume as EventListener);
      document.addEventListener('visibilitychange', onResume as EventListener);
      document.addEventListener('scroll', onScroll as EventListener, true);
    }
    syncBrand();
    const source = fieldFlowLocalePortalRoot();
    let brandObserver: MutationObserver | null = null;
    if (source && typeof MutationObserver !== 'undefined') {
      brandObserver = new MutationObserver(() => {
        forceConfigRefresh();
        syncBrand();
      });
      brandObserver.observe(source, { attributes: true, attributeFilter: ['style', 'class', 'data-client-id', 'data-project-id'] });
    }
    return () => {
      if (typeof window.removeEventListener === 'function') {
        window.removeEventListener('fieldflow:locale-changed', onLocale as EventListener);
        window.removeEventListener('fieldflow:i18n-context-changed', onContext as EventListener);
        window.removeEventListener('pageshow', onResume as EventListener);
        window.removeEventListener('focus', onResume as EventListener);
        window.removeEventListener('scroll', onScroll as EventListener);
      }
      if (typeof document.removeEventListener === 'function') {
        document.removeEventListener('pointerdown', onPointer, true);
        document.removeEventListener('keydown', onKey);
        document.removeEventListener('routespro:scope-change', onScope as EventListener);
        document.removeEventListener('fieldflow:portal-ready', onResume as EventListener);
        document.removeEventListener('visibilitychange', onResume as EventListener);
        document.removeEventListener('scroll', onScroll as EventListener, true);
      }
      if (scrollStopTimer !== null && typeof window.clearTimeout === 'function') window.clearTimeout(scrollStopTimer);
      if (brandObserver) brandObserver.disconnect();
    };
  }, []);

  element.useEffect(() => {
    if (open && canSwitch) root.querySelector<HTMLButtonElement>('.ff-language-option.is-active, .ff-language-option')?.focus();
  }, [open, canSwitch]);

  if (!canSwitch) return null;

  const close = (): void => {
    setOpen(false);
    setDocked(true);
    root.querySelector<HTMLButtonElement>('.ff-language-trigger')?.focus();
  };

  const choose = (code: string): void => {
    const next = fieldFlowLocaleNormalise(code);
    if (!next) return;
    if (typeof runtime.prepareTranslator === 'function') runtime.prepareTranslator(next);
    runtime.setLocale(next, true);
    setLocaleState(next);
    close();
  };

  const globe = element.createElement('span', { className: 'ff-language-globe3d', 'aria-hidden': 'true' },
    element.createElement('span', { className: 'ff-language-globe3d-grid' }),
    element.createElement('span', { className: 'ff-language-globe3d-shine' })
  );

  const options = items.map((item) => {
    const selected = item.code === locale;
    return element.createElement('button', {
      type: 'button',
      className: selected ? 'ff-language-option is-active' : 'ff-language-option',
      key: item.code,
      onClick: () => choose(item.code),
      'data-locale': item.code,
      'aria-current': selected ? 'true' : undefined,
    },
      element.createElement('span', { className: 'ff-language-flag', 'aria-hidden': 'true' }, item.flag),
      element.createElement('span', { className: 'ff-language-option-copy' },
        element.createElement('strong', null, item.label),
        element.createElement('small', null, item.code.toUpperCase())
      ),
      element.createElement('span', { className: 'ff-language-check', 'aria-hidden': 'true' }, selected ? '✓' : '')
    );
  });

  const switcherClasses = ['ff-language-switcher', 'ff-language-switcher-react'];
  if (open) switcherClasses.push('is-open');
  if (docked) switcherClasses.push('is-docked');
  if (scrolling) switcherClasses.push('is-scrolling');

  return element.createElement('div', {
    className: switcherClasses.join(' '),
    'data-fieldflow-i18n-ignore': '1',
    'data-react-contract': FIELD_FLOW_REACT_LOCALE_SWITCHER_CONTRACT,
    'data-scope-contract': FIELD_FLOW_LOCALE_BRAND_SCOPE_CONTRACT,
    'data-app-shell-contract': FIELD_FLOW_APP_SHELL_LOCALE_CONTRACT,
    'data-auto-translation-contract': FIELD_FLOW_BROWSERLIKE_TRANSLATION_CONTRACT,
    'data-adaptive-dock-contract': FIELD_FLOW_ADAPTIVE_LOCALE_DOCK_CONTRACT,
    'data-client-id': String(clientId || 0),
    style: brandStyle,
    onPointerEnter: () => { if (docked && !scrolling) setDocked(false); },
    onFocusCapture: () => { setDocked(false); setScrolling(false); },
    onBlur: (event: FocusEvent) => {
      if (!(event.relatedTarget instanceof Node) || !root.contains(event.relatedTarget)) setOpen(false);
    },
  },
    element.createElement('button', {
      type: 'button',
      className: 'ff-language-trigger ff-language-trigger-premium',
      onClick: () => {
        if (docked || scrolling) {
          setDocked(false);
          setScrolling(false);
          setOpen(true);
          return;
        }
        setOpen(!open);
      },
      'aria-expanded': open ? 'true' : 'false',
      'aria-controls': 'fieldflow-react-locale-options',
      'aria-label': labels.title,
      title: labels.title,
    },
      globe,
      element.createElement('span', { className: 'ff-language-code-badge' }, String(active?.code || locale).split('-')[0].toUpperCase())
    ),
    open ? element.createElement('section', { id: 'fieldflow-react-locale-options', className: 'ff-language-menu ff-language-menu-premium', role: 'group', 'aria-label': labels.title },
      element.createElement('header', { className: 'ff-language-menu-head' },
        element.createElement('span', { className: 'ff-language-menu-orb', 'aria-hidden': 'true' }, globe),
        element.createElement('span', null,
          element.createElement('strong', null, labels.title),
          element.createElement('small', null, labels.subtitle)
        ),
        element.createElement('button', { type: 'button', className: 'ff-language-close', onClick: close, 'aria-label': labels.close }, '×')
      ),
      element.createElement('div', { className: 'ff-language-options' }, ...options),
      element.createElement('footer', { className: 'ff-language-menu-foot' },
        element.createElement('span', null, labels.current),
        element.createElement('strong', null, active?.label || locale)
      )
    ) : null
  );
}

function fieldFlowReactLocaleSwitcherMount(): boolean {
  const element = window.wp?.element;
  const runtime = window.FieldFlowI18n;
  const config = window.FieldFlowI18nConfig;
  if (!element || !runtime || !config || !document.body) return false;
  const existing = document.getElementById('fieldflow-react-locale-switcher-root');
  if (existing && fieldFlowReactFrontRoots.has(existing)) return true;
  if (existing) existing.remove();

  const root = document.createElement('div');
  root.id = 'fieldflow-react-locale-switcher-root';
  root.setAttribute('data-fieldflow-i18n-ignore', '1');
  root.setAttribute('data-scope-contract', FIELD_FLOW_LOCALE_BRAND_SCOPE_CONTRACT);
  root.setAttribute('data-app-shell-contract', FIELD_FLOW_APP_SHELL_LOCALE_CONTRACT);
  root.setAttribute('data-auto-translation-contract', FIELD_FLOW_BROWSERLIKE_TRANSLATION_CONTRACT);
  document.body.appendChild(root);

  // Critical React correctness: pass the component type to createElement. Calling
  // fieldFlowReactLocaleSwitcherComponent() here would execute hooks outside a
  // React render and throws "Invalid hook call" in the real WordPress/WebView runtime.
  const node = element.createElement(fieldFlowReactLocaleSwitcherComponent, { root });
  try {
    if (!fieldFlowReactFrontRender(root, node)) { root.remove(); return false; }
  } catch (_error) {
    root.remove();
    if (typeof runtime.ensureSwitcher === 'function') runtime.ensureSwitcher('react-mount-error');
    return false;
  }

  const confirmRender = (): void => {
    const liveRoot = document.getElementById('fieldflow-react-locale-switcher-root');
    if (liveRoot !== root || !fieldFlowReactFrontRoots.has(root)) return;
    const rendered = liveRoot.querySelector('.ff-language-switcher-react');
    if (rendered) {
      liveRoot.setAttribute('data-fieldflow-react-mounted', '1');
      document.querySelectorAll('[data-fieldflow-locale-switcher-owner="legacy"]').forEach((legacy) => legacy.remove());
    } else if (Boolean(window.FieldFlowI18nConfig?.allowUserSwitch)) {
      // React may be delayed or unavailable in a specific WebView. Keep a live
      // legacy selector instead of leaving the app without any language control.
      if (typeof runtime.ensureSwitcher === 'function') runtime.ensureSwitcher('react-render-fallback');
    }
  };
  if (typeof window.setTimeout === 'function') {
    window.setTimeout(confirmRender, 0);
    window.setTimeout(confirmRender, 120);
  }

  window.FieldFlowReactLocaleSwitcher = {
    contract: FIELD_FLOW_REACT_LOCALE_SWITCHER_CONTRACT,
    version: FIELD_FLOW_FRONTEND_BUILD_VERSION,
    source: 'typescript-react-wp-element',
  };
  return true;
}
