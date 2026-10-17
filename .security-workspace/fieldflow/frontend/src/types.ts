interface FieldFlowFrontendConfig {
  version?: string;
  context?: 'front' | 'admin' | string;
  native?: boolean;
}

interface FieldFlowOfflineApi {
  __runtimeBuild?: string;
  version?: string;
  networkState?: () => string;
  canSync?: () => boolean;
}

interface FieldFlowFrontendSnapshot {
  buildVersion: string;
  pluginVersion: string;
  buildContract: string;
  source: 'typescript';
  context: string;
  native: boolean;
  offlineRuntimeBuild: string;
  offlineRuntimeAvailable: boolean;
  typedOfflineContract: string;
  typedDraftContract: string;
  typedFormContract: string;
  typedSubmissionContract: string;
  canonicalAnswerSemanticsContract: string;
  typedSpecializedValidationContract: string;
  generatedAtMs: number;
}

interface FieldFlowFrontendApi {
  version: string;
  __buildVersion: string;
  buildContract: string;
  source: 'typescript';
  ready: boolean;
  offlineCore: FieldFlowTypedOfflineApi;
  draftCore: FieldFlowTypedDraftCoreApi;
  formCore: FieldFlowTypedFormCoreApi;
  submissionCore: FieldFlowTypedSubmissionCoreApi;
  answerSemanticsCore: FieldFlowAnswerSemanticsApi;
  specializedValidationCore: FieldFlowTypedSpecializedValidationCoreApi;
  snapshot: () => FieldFlowFrontendSnapshot;
  on: (eventName: string, listener: EventListenerOrEventListenerObject) => () => void;
  emit: (eventName: string, detail?: unknown) => void;
}

interface Window {
  FieldFlowFrontendConfig?: FieldFlowFrontendConfig;
  FieldFlowFrontend?: FieldFlowFrontendApi;
  FieldFlowOffline?: FieldFlowOfflineApi;
}
