type FieldFlowOutboxStatus = 'pending' | 'syncing' | 'failed' | 'conflict' | 'completed';

interface FieldFlowOutboxItemLike {
  status?: string;
  attempts?: number;
  createdAt?: number;
  updatedAt?: number;
  nextAttemptAt?: number;
}

interface FieldFlowOutboxApi {
  retryDelay: (attempts: number) => number;
  isEligible: (item: FieldFlowOutboxItemLike, atMs: number, includeFailed?: boolean) => boolean;
  shouldRecoverStaleSyncing: (item: FieldFlowOutboxItemLike, atMs: number, staleAfterMs?: number) => boolean;
  compareCreatedAt: (left: FieldFlowOutboxItemLike, right: FieldFlowOutboxItemLike) => number;
  normalizeStatus: (status: unknown) => FieldFlowOutboxStatus;
}

function fieldFlowNormalizeOutboxStatus(status: unknown): FieldFlowOutboxStatus {
  const clean = String(status || 'pending').trim().toLowerCase();
  if (clean === 'syncing' || clean === 'failed' || clean === 'conflict' || clean === 'completed') return clean;
  return 'pending';
}

function fieldFlowRetryDelay(attempts: number): number {
  const safeAttempts = Math.max(0, Math.min(8, Math.floor(Number(attempts) || 0)));
  return Math.min(3600000, Math.max(5000, Math.pow(2, safeAttempts) * 3000));
}

const fieldFlowOutboxApi: FieldFlowOutboxApi = {
  retryDelay: fieldFlowRetryDelay,
  isEligible: (item: FieldFlowOutboxItemLike, atMs: number, includeFailed: boolean = false): boolean => {
    const status = String(item && item.status || '').trim().toLowerCase();
    const allowed = status === 'pending' || status === 'syncing' || (includeFailed && status === 'failed');
    if (!allowed) return false;
    const nextAttemptAt = Math.max(0, Number(item && item.nextAttemptAt) || 0);
    return nextAttemptAt === 0 || nextAttemptAt <= atMs;
  },
  shouldRecoverStaleSyncing: (item: FieldFlowOutboxItemLike, atMs: number, staleAfterMs: number = 300000): boolean => {
    if (String(item && item.status || '').trim().toLowerCase() !== 'syncing') return false;
    const updatedAt = Math.max(0, Number(item && item.updatedAt) || 0);
    const staleAfter = Math.max(30000, Number(staleAfterMs) || 300000);
    return updatedAt < atMs - staleAfter;
  },
  compareCreatedAt: (left: FieldFlowOutboxItemLike, right: FieldFlowOutboxItemLike): number => (Number(left && left.createdAt) || 0) - (Number(right && right.createdAt) || 0),
  normalizeStatus: fieldFlowNormalizeOutboxStatus
};
