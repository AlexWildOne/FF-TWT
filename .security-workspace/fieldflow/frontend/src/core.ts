const FIELD_FLOW_FRONTEND_BUILD_CONTRACT = 'fieldflow-frontend-build-foundation-v1';

function fieldFlowFrontendConfig(): FieldFlowFrontendConfig {
  return window.FieldFlowFrontendConfig && typeof window.FieldFlowFrontendConfig === 'object'
    ? window.FieldFlowFrontendConfig
    : {};
}

function fieldFlowFrontendSnapshot(): FieldFlowFrontendSnapshot {
  const config = fieldFlowFrontendConfig();
  const offline = window.FieldFlowOffline;
  return {
    buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
    pluginVersion: String(config.version || ''),
    buildContract: FIELD_FLOW_FRONTEND_BUILD_CONTRACT,
    source: 'typescript',
    context: String(config.context || 'unknown'),
    native: Boolean(config.native),
    offlineRuntimeBuild: String(offline && (offline.__runtimeBuild || offline.version) || ''),
    offlineRuntimeAvailable: Boolean(offline),
    typedOfflineContract: FIELD_FLOW_TYPED_OFFLINE_CONTRACT,
    typedDraftContract: FIELD_FLOW_TYPED_DRAFT_CONTRACT,
    typedFormContract: FIELD_FLOW_TYPED_FORM_CONTRACT,
    typedSubmissionContract: FIELD_FLOW_TYPED_SUBMISSION_CONTRACT,
    canonicalAnswerSemanticsContract: FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT,
    typedSpecializedValidationContract: FIELD_FLOW_TYPED_SPECIALIZED_VALIDATION_CONTRACT,
    generatedAtMs: Date.now()
  };
}

function fieldFlowFrontendEventName(eventName: string): string {
  const clean = String(eventName || '').trim();
  if (!clean) return 'fieldflow:frontend:event';
  return clean.startsWith('fieldflow:') ? clean : 'fieldflow:' + clean;
}

function fieldFlowFrontendOn(eventName: string, listener: EventListenerOrEventListenerObject): () => void {
  const name = fieldFlowFrontendEventName(eventName);
  document.addEventListener(name, listener);
  return function unsubscribe(): void {
    document.removeEventListener(name, listener);
  };
}

function fieldFlowFrontendEmit(eventName: string, detail?: unknown): void {
  const name = fieldFlowFrontendEventName(eventName);
  document.dispatchEvent(new CustomEvent(name, { detail: detail }));
}
