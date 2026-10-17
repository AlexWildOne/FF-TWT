type FieldFlowNetworkState = 'unknown' | 'online' | 'degraded' | 'offline' | 'auth_required';

interface FieldFlowConnectivityApi {
  normalize: (state: unknown, navigatorOnline?: boolean) => FieldFlowNetworkState;
  isUsable: (state: unknown, navigatorOnline?: boolean) => boolean;
  isOnline: (state: unknown, navigatorOnline?: boolean) => boolean;
  shouldAttemptNetwork: (state: unknown, navigatorOnline?: boolean) => boolean;
}

function fieldFlowNormalizeNetworkState(state: unknown, navigatorOnline: boolean = true): FieldFlowNetworkState {
  if (!navigatorOnline) return 'offline';
  const clean = String(state || '').trim().toLowerCase();
  if (clean === 'online' || clean === 'degraded' || clean === 'offline' || clean === 'auth_required' || clean === 'unknown') {
    return clean as FieldFlowNetworkState;
  }
  return 'unknown';
}

function fieldFlowIsUsableConnectivity(state: unknown, navigatorOnline: boolean = true): boolean {
  const normalized = fieldFlowNormalizeNetworkState(state, navigatorOnline);
  return normalized === 'online' || normalized === 'degraded';
}

const fieldFlowConnectivityApi: FieldFlowConnectivityApi = {
  normalize: fieldFlowNormalizeNetworkState,
  isUsable: fieldFlowIsUsableConnectivity,
  isOnline: (state: unknown, navigatorOnline: boolean = true): boolean => fieldFlowNormalizeNetworkState(state, navigatorOnline) === 'online',
  shouldAttemptNetwork: fieldFlowIsUsableConnectivity
};
