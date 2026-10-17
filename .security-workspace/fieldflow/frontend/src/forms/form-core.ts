const FIELD_FLOW_TYPED_FORM_CONTRACT = 'fieldflow-typed-form-state-conditional-core-v1';

type FieldFlowFormScalar = string | number | boolean | null | undefined;
type FieldFlowFormValue = FieldFlowFormScalar | FieldFlowFormScalar[];

interface FieldFlowConditionalRuleInput {
  source?: unknown;
  sourceKey?: unknown;
  source_key?: unknown;
  target?: unknown;
  targetKey?: unknown;
  target_key?: unknown;
  value?: unknown;
  expectedValue?: unknown;
  expected_value?: unknown;
  operator?: unknown;
}

interface FieldFlowConditionalRule {
  sourceKey: string;
  targetKey: string;
  expectedValue: string;
  operator: 'equals';
}

interface FieldFlowConditionalEvaluation {
  contract: string;
  visibility: Record<string, boolean>;
  activeKeys: string[];
  hiddenKeys: string[];
  targetKeys: string[];
  passes: number;
}

interface FieldFlowFormQuestionDefinition {
  key: string;
  type?: string;
  required?: boolean;
}

interface FieldFlowFormQuestionState {
  key: string;
  type: string;
  required: boolean;
  visible: boolean;
  value: FieldFlowFormValue;
  empty: boolean;
}

interface FieldFlowConditionalSnapshot {
  contract: string;
  hidden_question_keys: string[];
  order_suggestion_keys: string[];
  active_order_suggestion_keys: string[];
  active_required_keys: string[];
}

interface FieldFlowConditionsApi {
  contract: string;
  token: (value: unknown) => string;
  matches: (actual: unknown, expected: unknown) => boolean;
  normaliseRules: (rules: unknown) => FieldFlowConditionalRule[];
  evaluate: (keys: unknown, rules: unknown, values: unknown) => FieldFlowConditionalEvaluation;
  snapshot: (questions: unknown, visibility: unknown) => FieldFlowConditionalSnapshot;
}

interface FieldFlowFormStateApi {
  build: (questions: unknown, visibility: unknown, values: unknown) => FieldFlowFormQuestionState[];
  activeRequiredKeys: (questions: unknown, visibility: unknown) => string[];
  filterActiveValues: (values: unknown, visibility: unknown) => Record<string, unknown>;
  isEmpty: (value: unknown, type?: unknown) => boolean;
}

interface FieldFlowTypedFormCoreApi {
  contract: string;
  buildVersion: string;
  conditions: FieldFlowConditionsApi;
  state: FieldFlowFormStateApi;
}

function fieldFlowFormKey(value: unknown): string {
  return String(value === undefined || value === null ? '' : value).trim().toLowerCase().replace(/[^a-z0-9_\-]/g, '');
}

function fieldFlowConditionToken(value: unknown): string {
  if (value === true) return '1';
  if (value === false) return '0';
  if (value === undefined || value === null) return '';
  return String(value).trim().toLowerCase();
}

function fieldFlowConditionMatches(actual: unknown, expected: unknown): boolean {
  const wanted = fieldFlowConditionToken(expected);
  if (Array.isArray(actual)) return actual.some((value) => fieldFlowConditionToken(value) === wanted);
  return fieldFlowConditionToken(actual) === wanted;
}

function fieldFlowNormaliseConditionalRules(rawRules: unknown): FieldFlowConditionalRule[] {
  if (!Array.isArray(rawRules)) return [];
  const seen = new Set<string>();
  const result: FieldFlowConditionalRule[] = [];
  rawRules.forEach((raw) => {
    if (!raw || typeof raw !== 'object') return;
    const rule = raw as FieldFlowConditionalRuleInput;
    const sourceKey = fieldFlowFormKey(rule.sourceKey ?? rule.source_key ?? rule.source);
    const targetKey = fieldFlowFormKey(rule.targetKey ?? rule.target_key ?? rule.target);
    const expectedRaw = rule.expectedValue ?? rule.expected_value ?? rule.value;
    const expectedValue = fieldFlowConditionToken(expectedRaw);
    if (!sourceKey || !targetKey || sourceKey === targetKey || expectedValue === '') return;
    const operator = String(rule.operator || 'equals').toLowerCase();
    if (operator !== 'equals') return;
    const signature = `${sourceKey}|${targetKey}|${fieldFlowConditionToken(expectedValue)}`;
    if (seen.has(signature)) return;
    seen.add(signature);
    result.push({ sourceKey, targetKey, expectedValue, operator: 'equals' });
  });
  return result;
}

function fieldFlowConditionalEvaluate(rawKeys: unknown, rawRules: unknown, rawValues: unknown): FieldFlowConditionalEvaluation {
  const keys = Array.isArray(rawKeys)
    ? Array.from(new Set(rawKeys.map(fieldFlowFormKey).filter(Boolean)))
    : [];
  const keySet = new Set(keys);
  const rules = fieldFlowNormaliseConditionalRules(rawRules).filter((rule) => keySet.has(rule.sourceKey) && keySet.has(rule.targetKey));
  const values = rawValues && typeof rawValues === 'object' ? rawValues as Record<string, unknown> : {};
  const targetKeys = Array.from(new Set(rules.map((rule) => rule.targetKey)));
  const targets = new Set(targetKeys);
  const visibility: Record<string, boolean> = {};
  keys.forEach((key) => { visibility[key] = !targets.has(key); });

  let passes = 0;
  const limit = Math.max(1, keys.length + rules.length + 1);
  for (let pass = 0; pass < limit; pass += 1) {
    passes = pass + 1;
    let changed = false;
    rules.forEach((rule) => {
      if (!visibility[rule.sourceKey] || visibility[rule.targetKey]) return;
      if (!fieldFlowConditionMatches(values[rule.sourceKey], rule.expectedValue)) return;
      visibility[rule.targetKey] = true;
      changed = true;
    });
    if (!changed) break;
  }

  return {
    contract: FIELD_FLOW_TYPED_FORM_CONTRACT,
    visibility,
    activeKeys: keys.filter((key) => visibility[key] !== false),
    hiddenKeys: keys.filter((key) => visibility[key] === false),
    targetKeys,
    passes
  };
}

function fieldFlowFormQuestions(rawQuestions: unknown): FieldFlowFormQuestionDefinition[] {
  if (!Array.isArray(rawQuestions)) return [];
  const result: FieldFlowFormQuestionDefinition[] = [];
  rawQuestions.forEach((raw) => {
    if (!raw || typeof raw !== 'object') return;
    const item = raw as Partial<FieldFlowFormQuestionDefinition>;
    const key = fieldFlowFormKey(item.key);
    if (!key) return;
    result.push({
      key,
      type: String(item.type || 'text').trim().toLowerCase(),
      required: Boolean(item.required)
    });
  });
  return result;
}

function fieldFlowVisibilityMap(rawVisibility: unknown): Record<string, boolean> {
  if (!rawVisibility || typeof rawVisibility !== 'object') return {};
  const visibility: Record<string, boolean> = {};
  Object.entries(rawVisibility as Record<string, unknown>).forEach(([rawKey, rawValue]) => {
    const key = fieldFlowFormKey(rawKey);
    if (key) {
      const token = String(rawValue === undefined || rawValue === null ? '' : rawValue).trim().toLowerCase();
      visibility[key] = rawValue !== false && rawValue !== 0 && token !== '0' && token !== 'false';
    }
  });
  return visibility;
}

function fieldFlowFormIsEmpty(value: unknown, type: unknown = 'text'): boolean {
  const questionType = String(type || 'text').trim().toLowerCase();
  if (Array.isArray(value)) return value.length === 0 || value.every((item) => fieldFlowFormIsEmpty(item, questionType));
  if (value === undefined || value === null) return true;
  if (typeof value === 'boolean') return questionType === 'checkbox' ? value === false : false;
  if (typeof value === 'number') return !Number.isFinite(value);
  if (typeof value === 'object') return Object.keys(value as Record<string, unknown>).length === 0;
  const token = String(value).trim();
  if (questionType === 'checkbox') return token === '' || token === '0' || token.toLowerCase() === 'false';
  return token === '';
}

function fieldFlowActiveRequiredKeys(rawQuestions: unknown, rawVisibility: unknown): string[] {
  const questions = fieldFlowFormQuestions(rawQuestions);
  const visibility = fieldFlowVisibilityMap(rawVisibility);
  return questions
    .filter((question) => question.required && (!Object.prototype.hasOwnProperty.call(visibility, question.key) || visibility[question.key]))
    .map((question) => question.key);
}

function fieldFlowBuildQuestionState(rawQuestions: unknown, rawVisibility: unknown, rawValues: unknown): FieldFlowFormQuestionState[] {
  const questions = fieldFlowFormQuestions(rawQuestions);
  const visibility = fieldFlowVisibilityMap(rawVisibility);
  const values = rawValues && typeof rawValues === 'object' ? rawValues as Record<string, FieldFlowFormValue> : {};
  return questions.map((question) => {
    const visible = !Object.prototype.hasOwnProperty.call(visibility, question.key) || visibility[question.key];
    const value = values[question.key];
    return {
      key: question.key,
      type: String(question.type || 'text'),
      required: Boolean(question.required),
      visible,
      value,
      empty: fieldFlowFormIsEmpty(value, question.type)
    };
  });
}

function fieldFlowFilterActiveValues(rawValues: unknown, rawVisibility: unknown): Record<string, unknown> {
  const values = rawValues && typeof rawValues === 'object' ? rawValues as Record<string, unknown> : {};
  const visibility = fieldFlowVisibilityMap(rawVisibility);
  const result: Record<string, unknown> = {};
  Object.entries(values).forEach(([rawKey, value]) => {
    const key = fieldFlowFormKey(rawKey);
    if (!key) return;
    if (Object.prototype.hasOwnProperty.call(visibility, key) && !visibility[key]) return;
    result[key] = value;
  });
  return result;
}

function fieldFlowConditionalSnapshot(rawQuestions: unknown, rawVisibility: unknown): FieldFlowConditionalSnapshot {
  const questions = fieldFlowFormQuestions(rawQuestions);
  const visibility = fieldFlowVisibilityMap(rawVisibility);
  const hidden: string[] = [];
  const orderKeys: string[] = [];
  const activeOrderKeys: string[] = [];
  questions.forEach((question) => {
    const visible = !Object.prototype.hasOwnProperty.call(visibility, question.key) || visibility[question.key];
    if (!visible) hidden.push(question.key);
    if (question.type === 'order_suggestion') {
      orderKeys.push(question.key);
      if (visible) activeOrderKeys.push(question.key);
    }
  });
  return {
    contract: FIELD_FLOW_TYPED_FORM_CONTRACT,
    hidden_question_keys: Array.from(new Set(hidden)),
    order_suggestion_keys: Array.from(new Set(orderKeys)),
    active_order_suggestion_keys: Array.from(new Set(activeOrderKeys)),
    active_required_keys: fieldFlowActiveRequiredKeys(questions, visibility)
  };
}

const fieldFlowTypedFormCoreApi: FieldFlowTypedFormCoreApi = Object.freeze({
  contract: FIELD_FLOW_TYPED_FORM_CONTRACT,
  buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
  conditions: Object.freeze({
    contract: FIELD_FLOW_TYPED_FORM_CONTRACT,
    token: fieldFlowConditionToken,
    matches: fieldFlowConditionMatches,
    normaliseRules: fieldFlowNormaliseConditionalRules,
    evaluate: fieldFlowConditionalEvaluate,
    snapshot: fieldFlowConditionalSnapshot
  }),
  state: Object.freeze({
    build: fieldFlowBuildQuestionState,
    activeRequiredKeys: fieldFlowActiveRequiredKeys,
    filterActiveValues: fieldFlowFilterActiveValues,
    isEmpty: fieldFlowFormIsEmpty
  })
});
