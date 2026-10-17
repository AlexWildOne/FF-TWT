const FIELD_FLOW_TYPED_SPECIALIZED_VALIDATION_CONTRACT = 'fieldflow-typed-specialized-validation-core-v1';

type FieldFlowSpecializedIssueCode =
  | 'media_required'
  | 'categorized_selection_required'
  | 'categorized_media_required'
  | 'share_space_configuration_missing'
  | 'share_space_incomplete'
  | 'evidence_photo_required'
  | 'evidence_reason_required'
  | 'product_matrix_required';

interface FieldFlowSpecializedValidationResult {
  contract: string;
  valid: boolean;
  issueCode: FieldFlowSpecializedIssueCode | '';
  missingKeys: string[];
}

interface FieldFlowSpecializedValidationApi {
  contract: string;
  mediaHasValue: (value: unknown) => boolean;
  mediaRequired: (required: boolean, value: unknown) => FieldFlowSpecializedValidationResult;
  categorized: (required: boolean, selectedCategories: unknown, mediaByCategory: unknown) => FieldFlowSpecializedValidationResult;
  shareSpace: (required: boolean, rows: unknown, expectedKeys: unknown) => FieldFlowSpecializedValidationResult;
  evidence: (answer: unknown, requirePhotoOnYes: boolean, requireReasonOnNo: boolean, photoValue: unknown, reasonValue: unknown) => FieldFlowSpecializedValidationResult;
  productMatrix: (required: boolean, rows: unknown) => FieldFlowSpecializedValidationResult;
}

interface FieldFlowTypedSpecializedValidationCoreApi {
  contract: string;
  buildVersion: string;
  validation: FieldFlowSpecializedValidationApi;
}

function fieldFlowSpecializedResult(valid: boolean, issueCode: FieldFlowSpecializedIssueCode | '' = '', missingKeys: string[] = []): FieldFlowSpecializedValidationResult {
  return {
    contract: FIELD_FLOW_TYPED_SPECIALIZED_VALIDATION_CONTRACT,
    valid,
    issueCode: valid ? '' : issueCode,
    missingKeys: valid ? [] : Array.from(new Set(missingKeys.map(fieldFlowFormKey).filter(Boolean)))
  };
}

function fieldFlowSpecializedMediaHasValue(value: unknown): boolean {
  if (value === null || value === undefined || value === '') return false;
  if (typeof value === 'string') return value.trim() !== '';
  if (typeof value === 'number') return Number.isFinite(value) && value > 0;
  if (typeof value === 'boolean') return value;
  if (Array.isArray(value)) return value.some(fieldFlowSpecializedMediaHasValue);
  if (typeof value !== 'object') return false;
  const item = value as Record<string, unknown>;
  for (const key of ['url', 'media_ref', 'mediaRef', 'path', 'src']) {
    if (typeof item[key] === 'string' && String(item[key]).trim() !== '') return true;
  }
  if (typeof item.size === 'number' && Number.isFinite(item.size) && item.size > 0) return true;
  if (Array.isArray(item.images) && item.images.some(fieldFlowSpecializedMediaHasValue)) return true;
  if (Array.isArray(item.files) && item.files.some(fieldFlowSpecializedMediaHasValue)) return true;
  return false;
}

function fieldFlowSpecializedMediaRequired(required: boolean, value: unknown): FieldFlowSpecializedValidationResult {
  if (!required || fieldFlowSpecializedMediaHasValue(value)) return fieldFlowSpecializedResult(true);
  return fieldFlowSpecializedResult(false, 'media_required');
}

function fieldFlowSpecializedCategoryKeys(raw: unknown): string[] {
  if (!Array.isArray(raw)) return [];
  return Array.from(new Set(raw.map(fieldFlowFormKey).filter(Boolean)));
}

function fieldFlowSpecializedMediaMap(raw: unknown): Record<string, unknown> {
  if (!raw || typeof raw !== 'object') return {};
  const out: Record<string, unknown> = {};
  Object.entries(raw as Record<string, unknown>).forEach(([rawKey, value]) => {
    const key = fieldFlowFormKey(rawKey);
    if (key) out[key] = value;
  });
  return out;
}

function fieldFlowSpecializedCategorized(required: boolean, rawSelected: unknown, rawMediaByCategory: unknown): FieldFlowSpecializedValidationResult {
  const selected = fieldFlowSpecializedCategoryKeys(rawSelected);
  if (required && selected.length === 0) return fieldFlowSpecializedResult(false, 'categorized_selection_required');
  if (selected.length === 0) return fieldFlowSpecializedResult(true);
  const mediaByCategory = fieldFlowSpecializedMediaMap(rawMediaByCategory);
  const missing = selected.filter((key) => !fieldFlowSpecializedMediaHasValue(mediaByCategory[key]));
  if (missing.length) return fieldFlowSpecializedResult(false, 'categorized_media_required', missing);
  return fieldFlowSpecializedResult(true);
}

function fieldFlowSpecializedExpectedKeys(raw: unknown): string[] {
  if (!Array.isArray(raw)) return [];
  return Array.from(new Set(raw.map(fieldFlowFormKey).filter(Boolean)));
}

function fieldFlowSpecializedNumericInput(value: unknown): boolean {
  if (value === null || value === undefined || value === '') return false;
  const normalized = typeof value === 'string' ? value.trim().replace(',', '.') : value;
  const number = typeof normalized === 'number' ? normalized : Number(normalized);
  return Number.isFinite(number) && number >= 0;
}

function fieldFlowSpecializedShareSpace(required: boolean, rawRows: unknown, rawExpectedKeys: unknown): FieldFlowSpecializedValidationResult {
  if (!required) return fieldFlowSpecializedResult(true);
  const expected = fieldFlowSpecializedExpectedKeys(rawExpectedKeys);
  if (!expected.length) return fieldFlowSpecializedResult(false, 'share_space_configuration_missing');
  const byKey = new Map<string, Record<string, unknown>>();
  if (Array.isArray(rawRows)) {
    rawRows.forEach((raw) => {
      if (!raw || typeof raw !== 'object') return;
      const row = raw as Record<string, unknown>;
      const key = fieldFlowFormKey(row.metric_key ?? row.metricKey);
      if (key) byKey.set(key, row);
    });
  }
  const missing = expected.filter((key) => {
    const row = byKey.get(key);
    if (!row) return true;
    return !fieldFlowSpecializedNumericInput(row.pr_fronts ?? row.prFronts)
      && !fieldFlowSpecializedNumericInput(row.competitor_fronts ?? row.competitorFronts);
  });
  if (missing.length) return fieldFlowSpecializedResult(false, 'share_space_incomplete', missing);
  return fieldFlowSpecializedResult(true);
}

function fieldFlowSpecializedEvidence(rawAnswer: unknown, requirePhotoOnYes: boolean, requireReasonOnNo: boolean, photoValue: unknown, reasonValue: unknown): FieldFlowSpecializedValidationResult {
  const answer = fieldFlowConditionToken(rawAnswer);
  if ((answer === 'sim' || answer === 'yes') && requirePhotoOnYes && !fieldFlowSpecializedMediaHasValue(photoValue)) {
    return fieldFlowSpecializedResult(false, 'evidence_photo_required');
  }
  if ((answer === 'nao' || answer === 'não' || answer === 'no') && requireReasonOnNo && fieldFlowFormIsEmpty(reasonValue, 'textarea')) {
    return fieldFlowSpecializedResult(false, 'evidence_reason_required');
  }
  return fieldFlowSpecializedResult(true);
}

function fieldFlowSpecializedProductRowMeaningful(raw: unknown): boolean {
  if (!raw || typeof raw !== 'object') return false;
  const row = raw as Record<string, unknown>;
  const identity = [row.category, row.ref, row.reference, row.name, row.product]
    .some((value) => typeof value === 'string' && value.trim() !== '');
  const numeric = [row.qty, row.quantity, row.before, row.after, row.order_qty, row.boxes]
    .some(fieldFlowSpecializedNumericInput);
  return identity || numeric;
}

function fieldFlowSpecializedProductMatrix(required: boolean, rawRows: unknown): FieldFlowSpecializedValidationResult {
  if (!required) return fieldFlowSpecializedResult(true);
  const rows = Array.isArray(rawRows) ? rawRows : [];
  if (rows.some(fieldFlowSpecializedProductRowMeaningful)) return fieldFlowSpecializedResult(true);
  return fieldFlowSpecializedResult(false, 'product_matrix_required');
}

const fieldFlowTypedSpecializedValidationCoreApi: FieldFlowTypedSpecializedValidationCoreApi = Object.freeze({
  contract: FIELD_FLOW_TYPED_SPECIALIZED_VALIDATION_CONTRACT,
  buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
  validation: Object.freeze({
    contract: FIELD_FLOW_TYPED_SPECIALIZED_VALIDATION_CONTRACT,
    mediaHasValue: fieldFlowSpecializedMediaHasValue,
    mediaRequired: fieldFlowSpecializedMediaRequired,
    categorized: fieldFlowSpecializedCategorized,
    shareSpace: fieldFlowSpecializedShareSpace,
    evidence: fieldFlowSpecializedEvidence,
    productMatrix: fieldFlowSpecializedProductMatrix
  })
});
