const FIELD_FLOW_REACT_SHARED_CONTRACT = 'fieldflow-react-backoffice-shared-v1';

function fieldFlowReactRawProps(root: Element): unknown {
  const raw = String(root.getAttribute('data-fieldflow-react-props') || '').trim();
  if (!raw) return null;
  try {
    return JSON.parse(raw) as unknown;
  } catch (_error) {
    return null;
  }
}

async function fieldFlowReactPost<T>(url: string, body: Record<string, string>, signal?: AbortSignal): Promise<FieldFlowAjaxEnvelope<T>> {
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
    body: new URLSearchParams(body).toString(),
    signal,
  });
  const payload = await response.json() as FieldFlowAjaxEnvelope<T>;
  if (!response.ok || !payload || !payload.success) throw new Error('request_failed');
  return payload;
}

function fieldFlowReactIsoDate(date: Date): string {
  return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
}

function fieldFlowReactTodayRange(days: number, endValue = ''): { from: string; to: string } {
  const end = endValue ? new Date(endValue + 'T12:00:00') : new Date();
  const safeEnd = Number.isNaN(end.getTime()) ? new Date() : end;
  const start = new Date(safeEnd.getTime());
  start.setDate(start.getDate() - (Math.max(1, days) - 1));
  return { from: fieldFlowReactIsoDate(start), to: fieldFlowReactIsoDate(safeEnd) };
}
