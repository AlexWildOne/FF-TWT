
interface FieldFlowLocaleConfigItem {
  code: string;
  label: string;
  flag: string;
  html_lang?: string;
}

interface FieldFlowI18nConfigRuntime {
  contract?: string;
  version?: string;
  context?: string;
  clientId?: number;
  locale?: string;
  defaultLocale?: string;
  enabledLocales?: FieldFlowLocaleConfigItem[];
  allowUserSwitch?: boolean;
  native?: boolean;
  scopeContract?: string;
  appShellContract?: string;
  autoTranslationContract?: string;
  scopeClientIds?: number[];
  preferenceEndpoint?: string;
  translationEndpoint?: string;
  translationProviderAvailable?: boolean;
  translateProtectedNames?: boolean;
}

interface FieldFlowI18nRuntimeApi {
  contract: string;
  getLocale(): string;
  setLocale(locale: string, persist?: boolean): void;
  translate(value: string): string;
  refresh(): void;
  getClientId(): number;
  setClientContext(clientId: number): Promise<void>;
  ensureSwitcher?(reason?: string): void;
  reconcile?(reason?: string): void;
  prepareTranslator?(locale: string): void;
}

interface FieldFlowReactLocaleSwitcherApi {
  contract: string;
  version: string;
  source: 'typescript-react-wp-element';
}

interface FieldFlowFrontWpElementRoot {
  render(node: unknown): void;
  unmount(): void;
}

interface FieldFlowFrontWpElementRuntime {
  createElement(type: unknown, props?: Record<string, unknown> | null, ...children: unknown[]): unknown;
  useState<T>(initial: T): [T, (next: T) => void];
  useEffect(effect: () => void | (() => void), deps?: unknown[]): void;
  createRoot?: (element: Element) => FieldFlowFrontWpElementRoot;
  render?: (node: unknown, element: Element) => void;
  unmountComponentAtNode?: (element: Element) => boolean;
}

interface FieldFlowReactFrontBaseProps {
  contract: string;
  island: string;
  label?: string;
  fallbackId?: string;
}

interface FieldFlowFormWizardProgressProps extends FieldFlowReactFrontBaseProps {
  wizardContract: string;
  island: 'form-wizard-progress';
  fallbackId: string;
  totalSteps: number;
  label: string;
}

interface FieldFlowFormWizardSnapshot {
  current: number;
  total: number;
  percent: number;
  title: string;
  label: string;
}

interface FieldFlowOperationalShellProps extends FieldFlowReactFrontBaseProps {
  shellContract: string;
  fullwidthNavigationContract?: string;
  island: 'operational-shell';
  fallbackId: string;
  rootId: string;
  panels: Array<{ key: string; label: string }>;
}

interface FieldFlowOperationalShellSnapshot {
  activePanel: string;
  online: boolean;
}


interface FieldFlowOperationalModuleProps extends FieldFlowReactFrontBaseProps {
  operationalModulesContract: string;
  operationalModulesV2Contract?: string;
  operationalModernizationContract?: string;
  operationalModernizationWave2Contract?: string;
  clientWave1Contract?: string;
  clientWave2Contract?: string;
  crossPortalIsolationContract?: string;
  island: 'operational-module-controls';
  rootId: string;
  module: FieldFlowOperationalModuleKey;
}

interface FieldFlowMyRouteToolbarProps extends FieldFlowReactFrontBaseProps {
  routeContract: string;
  island: 'my-route-toolbar';
  fallbackId: string;
  routeRootId: string;
}

interface FieldFlowRouteOption {
  value: string;
  label: string;
}

interface FieldFlowMyRouteSnapshot {
  date: string;
  routeId: string;
  routes: FieldFlowRouteOption[];
  loading: boolean;
}

interface FieldFlowSimpleFieldProps extends FieldFlowReactFrontBaseProps {
  fieldContract: string;
  island: 'simple-field-assist';
  fieldKey: string;
  fieldType: string;
  inputId: string;
  required: boolean;
  unit?: string;
  controlContract?: string;
}

interface FieldFlowSimpleFieldOption {
  value: string;
  label: string;
}

interface FieldFlowSimpleFieldSnapshot {
  value: string;
  empty: boolean;
  valueLabel: string;
  disabled: boolean;
  readOnly: boolean;
  min: string;
  max: string;
  step: string;
  options: FieldFlowSimpleFieldOption[];
}


interface FieldFlowChoiceFieldOption {
  value: string;
  label: string;
  imageUrl?: string;
}

interface FieldFlowChoiceFieldProps extends FieldFlowReactFrontBaseProps {
  choiceContract: string;
  island: 'choice-field-control';
  fieldKey: string;
  fieldType: string;
  required: boolean;
  multiple: boolean;
  label: string;
  options: FieldFlowChoiceFieldOption[];
}

interface FieldFlowChoiceSnapshot {
  selected: string[];
  disabled: boolean;
}

interface FieldFlowSpecialFieldProps extends FieldFlowReactFrontBaseProps {
  specialContract: string;
  mediaControlContract?: string;
  formCompletionContract?: string;
  island: 'special-field-assist';
  fieldKey: string;
  fieldType: string;
  required: boolean;
  label: string;
  badge?: string;
}

interface FieldFlowSpecialCategorySnapshot {
  index: number;
  label: string;
  total: number;
  complete: number;
  incomplete: number;
}

interface FieldFlowSpecialSnapshot {
  total: number;
  complete: number;
  incomplete: number;
  media: number;
  selected: number;
  savedMedia: number;
  pendingMedia: number;
  pendingRemoval: number;
  online: boolean;
  active: boolean;
  status: string;
  categories: FieldFlowSpecialCategorySnapshot[];
  goalMet: number;
  goalBelow: number;
  autoSuggested: number;
  totalOrderQty: number;
}

type FieldFlowReactFrontProps =
  | FieldFlowFormWizardProgressProps
  | FieldFlowOperationalShellProps
  | FieldFlowOperationalModuleProps
  | FieldFlowMyRouteToolbarProps
  | FieldFlowSimpleFieldProps
  | FieldFlowChoiceFieldProps
  | FieldFlowSpecialFieldProps;

interface FieldFlowReactFrontApi {
  version: string;
  contract: string;
  source: 'wordpress-wp-element';
  ready: boolean;
  mountAll: () => number;
}

interface Window {
  wp?: {
    element?: FieldFlowFrontWpElementRuntime;
  };
  FieldFlowReactFront?: FieldFlowReactFrontApi;
  FieldFlowI18nConfig?: FieldFlowI18nConfigRuntime;
  FieldFlowI18n?: FieldFlowI18nRuntimeApi;
  FieldFlowReactLocaleSwitcher?: FieldFlowReactLocaleSwitcherApi;
}
