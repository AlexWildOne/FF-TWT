const FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT = 'fieldflow-canonical-answer-semantics-v1';

interface FieldFlowAnswerSemanticResult {
  contract: string;
  valid: boolean;
  type: string;
  value: unknown;
  issue: string;
  message: string;
  meta: Record<string, unknown>;
}

interface FieldFlowAnswerSemanticsApi {
  contract: string;
  buildVersion: string;
  normalize: (question: unknown, value: unknown, provided?: boolean) => FieldFlowAnswerSemanticResult;
}

function fieldFlowAnswerSemanticType(rawQuestion: unknown): string {
  if (!rawQuestion || typeof rawQuestion !== 'object') return 'text';
  return String((rawQuestion as Record<string, unknown>).type || 'text').trim().toLowerCase() || 'text';
}

function fieldFlowAnswerSemanticNumber(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null;
  if (value === undefined || value === null) return null;
  let raw = String(value).trim().replace(/\u00a0/g, '').replace(/\s+/g, '');
  if (!raw) return null;
  const comma = raw.lastIndexOf(',');
  const dot = raw.lastIndexOf('.');
  if (comma >= 0 && dot >= 0) {
    if (comma > dot) raw = raw.replace(/\./g, '').replace(',', '.');
    else raw = raw.replace(/,/g, '');
  } else if (comma >= 0) raw = raw.replace(',', '.');
  if (!/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(raw)) return null;
  const number = Number(raw);
  return Number.isFinite(number) ? number : null;
}

function fieldFlowAnswerSemanticChoiceValues(rawQuestion: unknown, type: string): string[] {
  if (!rawQuestion || typeof rawQuestion !== 'object') return [];
  const question = rawQuestion as Record<string, unknown>;
  const raw = type === 'visual_choice' && Array.isArray(question.visual_options)
    ? question.visual_options
    : (Array.isArray(question.options) ? question.options : []);
  const values: string[] = [];
  raw.forEach((option) => {
    let candidate = '';
    if (typeof option === 'string' || typeof option === 'number') candidate = String(option);
    else if (option && typeof option === 'object') {
      const item = option as Record<string, unknown>;
      candidate = String(item.value === undefined ? (item.label === undefined ? '' : item.label) : item.value);
    }
    candidate = candidate.trim();
    if (candidate && !values.includes(candidate)) values.push(candidate);
  });
  return values;
}

function fieldFlowAnswerSemanticCheckbox(value: unknown): boolean {
  if (value === true || value === 1) return true;
  if (value === false || value === 0 || value === undefined || value === null) return false;
  const token = String(value).trim().toLowerCase();
  return token !== '' && !['0', 'false', 'off', 'no', 'nao', 'não'].includes(token);
}

function fieldFlowAnswerSemanticOk(value: unknown, type: string, meta: Record<string, unknown> = {}): FieldFlowAnswerSemanticResult {
  return { contract: FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT, valid: true, type, value, issue: '', message: '', meta };
}

function fieldFlowAnswerSemanticInvalid(value: unknown, type: string, issue: string, message: string, meta: Record<string, unknown> = {}): FieldFlowAnswerSemanticResult {
  return { contract: FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT, valid: false, type, value, issue, message, meta };
}

function fieldFlowAnswerSemanticNormalize(rawQuestion: unknown, value: unknown, provided = true): FieldFlowAnswerSemanticResult {
  const type = fieldFlowAnswerSemanticType(rawQuestion);
  const question = rawQuestion && typeof rawQuestion === 'object' ? rawQuestion as Record<string, unknown> : {};
  if (!provided) return fieldFlowAnswerSemanticOk(null, type, { reason: 'not_provided' });

  if (['number', 'currency', 'percent'].includes(type)) {
    if (value === undefined || value === null || String(value).trim() === '') return fieldFlowAnswerSemanticOk('', type);
    const number = fieldFlowAnswerSemanticNumber(value);
    if (number === null) return fieldFlowAnswerSemanticInvalid(value, type, 'number_invalid', 'Invalid numeric value.');
    const min = question.min === undefined || question.min === null || String(question.min).trim() === '' ? null : fieldFlowAnswerSemanticNumber(question.min);
    const max = question.max === undefined || question.max === null || String(question.max).trim() === '' ? null : fieldFlowAnswerSemanticNumber(question.max);
    if (min !== null && number < min) return fieldFlowAnswerSemanticInvalid(value, type, 'number_below_min', 'Value is below the allowed minimum.', { min, actual: number });
    if (max !== null && number > max) return fieldFlowAnswerSemanticInvalid(value, type, 'number_above_max', 'Value is above the allowed maximum.', { max, actual: number });
    return fieldFlowAnswerSemanticOk(number, type);
  }

  if (type === 'checkbox') return fieldFlowAnswerSemanticOk(fieldFlowAnswerSemanticCheckbox(value) ? 1 : 0, type);

  if (['select', 'radio', 'visual_choice', 'multi_select', 'checkbox_group'].includes(type)) {
    const allowed = fieldFlowAnswerSemanticChoiceValues(question, type);
    const multiple = type === 'multi_select' || type === 'checkbox_group' || String(question.selection_mode || '').trim().toLowerCase() === 'multiple' || Boolean(question.multiple);
    if (multiple) {
      const input = Array.isArray(value) ? value : ((value === undefined || value === null || value === '') ? [] : [value]);
      const clean: string[] = [];
      for (const item of input) {
        if (typeof item !== 'string' && typeof item !== 'number') return fieldFlowAnswerSemanticInvalid(value, type, 'choice_shape_invalid', 'Invalid choice answer.');
        const token = String(item).trim();
        if (!token || clean.includes(token)) continue;
        if (allowed.length && !allowed.includes(token)) return fieldFlowAnswerSemanticInvalid(value, type, 'choice_not_allowed', 'Selected option is no longer available.', { invalid_value: token });
        clean.push(token);
      }
      return fieldFlowAnswerSemanticOk(clean, type);
    }
    if (Array.isArray(value)) {
      if (value.length > 1) return fieldFlowAnswerSemanticInvalid(value, type, 'choice_shape_invalid', 'Only one option is allowed.');
      value = value.length ? value[0] : '';
    }
    if (value === undefined || value === null || value === '') return fieldFlowAnswerSemanticOk('', type);
    if (typeof value !== 'string' && typeof value !== 'number') return fieldFlowAnswerSemanticInvalid(value, type, 'choice_shape_invalid', 'Invalid choice answer.');
    const token = String(value).trim();
    if (token && allowed.length && !allowed.includes(token)) return fieldFlowAnswerSemanticInvalid(value, type, 'choice_not_allowed', 'Selected option is no longer available.', { invalid_value: token });
    return fieldFlowAnswerSemanticOk(token, type);
  }

  if (['product_matrix', 'order_suggestion', 'share_space_matrix', 'categorized_image_upload'].includes(type)) {
    if (value === undefined || value === null || value === '') return fieldFlowAnswerSemanticOk([], type);
    if (!Array.isArray(value)) return fieldFlowAnswerSemanticInvalid(value, type, 'structured_value_invalid', 'Structured answer must be an array.');
    return fieldFlowAnswerSemanticOk(value, type);
  }

  return fieldFlowAnswerSemanticOk(value, type);
}

const fieldFlowAnswerSemanticsCoreApi: FieldFlowAnswerSemanticsApi = Object.freeze({
  contract: FIELD_FLOW_ANSWER_SEMANTICS_CONTRACT,
  buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
  normalize: fieldFlowAnswerSemanticNormalize
});
