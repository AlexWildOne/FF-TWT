(function fieldFlowFrontendBootstrap(): void {
  'use strict';

  const previous = window.FieldFlowFrontend && typeof window.FieldFlowFrontend === 'object'
    ? window.FieldFlowFrontend
    : undefined;

  if (previous && previous.__buildVersion === FIELD_FLOW_FRONTEND_BUILD_VERSION) return;

  const api: FieldFlowFrontendApi = {
    version: FIELD_FLOW_FRONTEND_BUILD_VERSION,
    __buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
    buildContract: FIELD_FLOW_FRONTEND_BUILD_CONTRACT,
    source: 'typescript',
    ready: false,
    offlineCore: fieldFlowTypedOfflineApi,
    draftCore: fieldFlowTypedDraftCoreApi,
    formCore: fieldFlowTypedFormCoreApi,
    submissionCore: fieldFlowTypedSubmissionCoreApi,
    answerSemanticsCore: fieldFlowAnswerSemanticsCoreApi,
    specializedValidationCore: fieldFlowTypedSpecializedValidationCoreApi,
    snapshot: fieldFlowFrontendSnapshot,
    on: fieldFlowFrontendOn,
    emit: fieldFlowFrontendEmit
  };

  window.FieldFlowFrontend = api;

  const markReady = function markReady(): void {
    if (!window.FieldFlowFrontend || window.FieldFlowFrontend.__buildVersion !== FIELD_FLOW_FRONTEND_BUILD_VERSION) return;
    window.FieldFlowFrontend.ready = true;
    fieldFlowFrontendEmit('fieldflow:frontend:ready', fieldFlowFrontendSnapshot());
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', markReady, { once: true });
  } else {
    queueMicrotask(markReady);
  }
})();
