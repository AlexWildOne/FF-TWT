const FIELD_FLOW_TYPED_OFFLINE_CONTRACT = 'fieldflow-typed-offline-core-v1';

interface FieldFlowTypedOfflineApi {
  contract: string;
  buildVersion: string;
  connectivity: FieldFlowConnectivityApi;
  syncLock: FieldFlowSyncLockApi;
  outbox: FieldFlowOutboxApi;
}

const fieldFlowTypedOfflineApi: FieldFlowTypedOfflineApi = Object.freeze({
  contract: FIELD_FLOW_TYPED_OFFLINE_CONTRACT,
  buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
  connectivity: Object.freeze(fieldFlowConnectivityApi),
  syncLock: Object.freeze(fieldFlowSyncLockApi),
  outbox: Object.freeze(fieldFlowOutboxApi)
});
