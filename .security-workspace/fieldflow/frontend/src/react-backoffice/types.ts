interface FieldFlowWpElementRoot {
  render(node: unknown): void;
  unmount(): void;
}

interface FieldFlowWpElementRuntime {
  createElement(type: unknown, props?: Record<string, unknown> | null, ...children: unknown[]): unknown;
  useState<T>(initial: T): [T, (next: T) => void];
  useEffect(effect: () => void | (() => void), deps?: unknown[]): void;
  createRoot?: (element: Element) => FieldFlowWpElementRoot;
  render?: (node: unknown, element: Element) => void;
  unmountComponentAtNode?: (element: Element) => boolean;
}

interface FieldFlowReactPlatformStatus {
  key: 'android' | 'ios' | string;
  label: string;
  enabled: boolean;
  minimumVersion: string;
  latestVersion: string;
  buildNumber: string;
}

interface FieldFlowMobileReleaseStatusProps {
  contract: string;
  island: 'mobile-release-status' | string;
  title: string;
  description: string;
  bridgeStatus: string;
  maintenanceStatus: string;
  platforms: FieldFlowReactPlatformStatus[];
}

interface FieldFlowReactDeviceRow {
  sessionId: number;
  userId: number;
  deviceId: string;
  deviceName: string;
  displayName: string;
  email: string;
  platform: string;
  appVersion: string;
  runtimeBuild: string;
  lastSeen: string;
  lastSync: string;
  active: boolean;
  blocked: boolean;
  trusted: boolean;
  offlineAllowed: boolean;
  cacheGeneration: number;
  healthStatus: string;
  healthScore: number;
  networkState: string;
  queuePending: number;
  queueFailed: number;
  queueConflicts: number;
  ackErrors: number;
  recoveries: number;
  appResumes: number;
  oldestPendingAgeSeconds: number;
  storagePercent: number;
  storagePersistence: string;
  mediaPendingFiles: number;
  mediaPendingBytes: number;
  trustLeaseState: string;
  trustLeaseRelease: string;
  trustLeaseExpiresAtMs: number;
}

interface FieldFlowReactDeviceListing {
  rows: FieldFlowReactDeviceRow[];
  total: number;
  page: number;
  perPage: number;
  pages: number;
  search: string;
  platform: string;
}

interface FieldFlowReactOption {
  value: string;
  label: string;
}

interface FieldFlowDeviceControlProps {
  contract: string;
  island: 'device-control-table' | string;
  title: string;
  description: string;
  ajaxUrl: string;
  nonce: string;
  listing: FieldFlowReactDeviceListing;
  platformOptions: FieldFlowReactOption[];
}


interface FieldFlowReactAnalyticsValues {
  from: string;
  to: string;
  client_id: string;
  project_id: string;
  route_id: string;
  user_id: string;
  location_id: string;
}

interface FieldFlowReactAnalyticsOption {
  value: string;
  label: string;
  clientId?: string;
  projectId?: string;
}

interface FieldFlowAnalyticsControlProps {
  contract: string;
  sharedContract: string;
  island: 'analytics-context-kpis' | string;
  title: string;
  description: string;
  fallbackId: string;
  values: FieldFlowReactAnalyticsValues;
  defaultValues: FieldFlowReactAnalyticsValues;
  clients: FieldFlowReactAnalyticsOption[];
  projects: FieldFlowReactAnalyticsOption[];
  routes: FieldFlowReactAnalyticsOption[];
  users: FieldFlowReactAnalyticsOption[];
  locations: FieldFlowReactAnalyticsOption[];
}

interface FieldFlowReactAnalyticsScore {
  score?: number | null;
  label?: string;
  direction?: string;
  explanation?: string;
}

interface FieldFlowAnalyticsInsightsEvent {
  decision_scores?: Record<string, FieldFlowReactAnalyticsScore | undefined>;
  executive?: FieldFlowReactAnalyticsScore;
  confidence?: string;
  confidence_detail?: FieldFlowReactAnalyticsScore;
}

interface FieldFlowAnalyticsDataEventDetail {
  params?: Record<string, string>;
  insights?: FieldFlowAnalyticsInsightsEvent;
}

interface FieldFlowAnalyticsStateEventDetail {
  kind?: string;
  title?: string;
  detail?: string;
}

interface FieldFlowAnalytics2Api {
  version: string;
  currentParams: () => Record<string, string>;
  applyContext: (values: Partial<FieldFlowReactAnalyticsValues>) => void;
  reload: () => Promise<void>;
  snapshot: () => { state?: FieldFlowAnalyticsStateEventDetail; data?: FieldFlowAnalyticsDataEventDetail };
}

interface FieldFlowAjaxEnvelope<T> {
  success: boolean;
  data?: T;
}

interface FieldFlowReactBackofficeApi {
  version: string;
  contract: string;
  source: 'wordpress-wp-element';
  ready: boolean;
  mountAll: () => number;
}

interface Window {
  wp?: {
    element?: FieldFlowWpElementRuntime;
  };
  FieldFlowReactBackoffice?: FieldFlowReactBackofficeApi;
  FieldFlowAnalytics2?: FieldFlowAnalytics2Api;
}
