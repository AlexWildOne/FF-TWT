const FIELD_FLOW_TYPED_SUBMISSION_CONTRACT = 'fieldflow-typed-submission-validation-core-v1';

interface FieldFlowSubmissionQuestionDefinition {
  key: string;
  type: string;
  required: boolean;
  label: string;
  min?: unknown;
  max?: unknown;
  options?: unknown;
  visual_options?: unknown;
  selection_mode?: unknown;
  multiple?: unknown;
}

interface FieldFlowSubmissionPreparedState {
  contract: string;
  valid: boolean;
  activeKeys: string[];
  hiddenKeys: string[];
  requiredKeys: string[];
  missingRequiredKeys: string[];
  invalidSemanticKeys: string[];
  semanticIssues: Record<string, string>;
  activeValues: Record<string, unknown>;
}

interface FieldFlowSubmissionValidationApi {
  contract: string;
  isQuestionActive: (key: unknown, visibility: unknown) => boolean;
  requiredMissing: (question: unknown, value: unknown, visibility?: unknown) => boolean;
  shouldPersist: (value: unknown, type?: unknown, provided?: boolean) => boolean;
  prepare: (questions: unknown, visibility: unknown, values: unknown) => FieldFlowSubmissionPreparedState;
}

interface FieldFlowTypedSubmissionCoreApi {
  contract: string;
  buildVersion: string;
  validation: FieldFlowSubmissionValidationApi;
}

function fieldFlowSubmissionQuestion(raw: unknown): FieldFlowSubmissionQuestionDefinition | null {
  if (!raw || typeof raw !== 'object') return null;
  const item = raw as Record<string, unknown>;
  const key = fieldFlowFormKey(item.key);
  if (!key) return null;
  return {
    key,
    type: String(item.type || 'text').trim().toLowerCase(),
    required: Boolean(item.required),
    label: String(item.label || key).trim(),
    min: item.min,
    max: item.max,
    options: item.options,
    visual_options: item.visual_options,
    selection_mode: item.selection_mode,
    multiple: item.multiple
  };
}

function fieldFlowSubmissionQuestions(rawQuestions: unknown): FieldFlowSubmissionQuestionDefinition[] {
  if (!Array.isArray(rawQuestions)) return [];
  const result: FieldFlowSubmissionQuestionDefinition[] = [];
  rawQuestions.forEach((raw) => {
    const question = fieldFlowSubmissionQuestion(raw);
    if (question) result.push(question);
  });
  return result;
}

function fieldFlowSubmissionIsQuestionActive(rawKey: unknown, rawVisibility: unknown): boolean {
  const key = fieldFlowFormKey(rawKey);
  if (!key) return false;
  const visibility = fieldFlowVisibilityMap(rawVisibility);
  return !Object.prototype.hasOwnProperty.call(visibility, key) || visibility[key] !== false;
}

function fieldFlowSubmissionCheckboxChecked(value: unknown): boolean {
  if (value === true || value === 1) return true;
  const token = String(value === undefined || value === null ? '' : value).trim().toLowerCase();
  return token !== '' && token !== '0' && token !== 'false' && token !== 'off' && token !== 'no' && token !== 'nao' && token !== 'não';
}

function fieldFlowSubmissionRequiredMissing(rawQuestion: unknown, value: unknown, rawVisibility: unknown = {}): boolean {
  const question = fieldFlowSubmissionQuestion(rawQuestion);
  if (!question || !question.required || !fieldFlowSubmissionIsQuestionActive(question.key, rawVisibility)) return false;
  if (question.type === 'checkbox') return !fieldFlowSubmissionCheckboxChecked(value);
  return fieldFlowFormIsEmpty(value, question.type);
}

function fieldFlowSubmissionShouldPersist(value: unknown, rawType: unknown = 'text', provided = true): boolean {
  const type = String(rawType || 'text').trim().toLowerCase();
  if (type === 'checkbox') return Boolean(provided);
  return !fieldFlowFormIsEmpty(value, type);
}

function fieldFlowSubmissionPrepare(rawQuestions: unknown, rawVisibility: unknown, rawValues: unknown): FieldFlowSubmissionPreparedState {
  const questions = fieldFlowSubmissionQuestions(rawQuestions);
  const visibility = fieldFlowVisibilityMap(rawVisibility);
  const values = rawValues && typeof rawValues === 'object' ? rawValues as Record<string, unknown> : {};
  const activeKeys: string[] = [];
  const hiddenKeys: string[] = [];
  const requiredKeys: string[] = [];
  const missingRequiredKeys: string[] = [];
  const invalidSemanticKeys: string[] = [];
  const semanticIssues: Record<string, string> = {};
  const activeValues: Record<string, unknown> = {};

  questions.forEach((question) => {
    const active = fieldFlowSubmissionIsQuestionActive(question.key, visibility);
    if (!active) {
      hiddenKeys.push(question.key);
      return;
    }
    activeKeys.push(question.key);
    if (question.required) requiredKeys.push(question.key);
    const provided = Object.prototype.hasOwnProperty.call(values, question.key);
    const rawValue = provided ? values[question.key] : undefined;
    const semantic = fieldFlowAnswerSemanticNormalize(question, rawValue, provided);
    if (!semantic.valid) {
      invalidSemanticKeys.push(question.key);
      semanticIssues[question.key] = semantic.issue;
      return;
    }
    const value = semantic.value;
    if (fieldFlowSubmissionRequiredMissing(question, value, visibility)) missingRequiredKeys.push(question.key);
    if (fieldFlowSubmissionShouldPersist(value, question.type, provided)) activeValues[question.key] = value;
  });

  return {
    contract: FIELD_FLOW_TYPED_SUBMISSION_CONTRACT,
    valid: missingRequiredKeys.length === 0 && invalidSemanticKeys.length === 0,
    activeKeys,
    hiddenKeys,
    requiredKeys,
    missingRequiredKeys,
    invalidSemanticKeys,
    semanticIssues,
    activeValues
  };
}

const fieldFlowTypedSubmissionCoreApi: FieldFlowTypedSubmissionCoreApi = Object.freeze({
  contract: FIELD_FLOW_TYPED_SUBMISSION_CONTRACT,
  buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
  validation: Object.freeze({
    contract: FIELD_FLOW_TYPED_SUBMISSION_CONTRACT,
    isQuestionActive: fieldFlowSubmissionIsQuestionActive,
    requiredMissing: fieldFlowSubmissionRequiredMissing,
    shouldPersist: fieldFlowSubmissionShouldPersist,
    prepare: fieldFlowSubmissionPrepare
  })
});
