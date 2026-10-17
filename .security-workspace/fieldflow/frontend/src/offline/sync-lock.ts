interface FieldFlowSyncLeaseRecord {
  owner: string;
  token: string;
  expiresAt: number;
  runtime: string;
}

interface FieldFlowSyncLockApi {
  parse: (raw: unknown) => FieldFlowSyncLeaseRecord | null;
  isExpired: (lease: FieldFlowSyncLeaseRecord | null, atMs?: number) => boolean;
  isForeignActive: (lease: FieldFlowSyncLeaseRecord | null, owner: string, atMs?: number) => boolean;
  owns: (lease: FieldFlowSyncLeaseRecord | null, owner: string, token: string) => boolean;
  create: (owner: string, token: string, atMs: number, ttlMs: number, runtime: string) => FieldFlowSyncLeaseRecord;
  renew: (lease: FieldFlowSyncLeaseRecord | null, owner: string, token: string, atMs: number, ttlMs: number, runtime: string) => FieldFlowSyncLeaseRecord | null;
}

function fieldFlowParseSyncLease(raw: unknown): FieldFlowSyncLeaseRecord | null {
  let value: unknown = raw;
  if (typeof raw === 'string') {
    try { value = JSON.parse(raw); } catch (_error) { return null; }
  }
  if (!value || typeof value !== 'object') return null;
  const candidate = value as Partial<FieldFlowSyncLeaseRecord>;
  const owner = String(candidate.owner || '');
  const token = String(candidate.token || '');
  const expiresAt = Number(candidate.expiresAt || 0);
  const runtime = String(candidate.runtime || '');
  if (!owner || !token || !Number.isFinite(expiresAt) || expiresAt < 0) return null;
  return { owner, token, expiresAt, runtime };
}

function fieldFlowCreateSyncLease(owner: string, token: string, atMs: number, ttlMs: number, runtime: string): FieldFlowSyncLeaseRecord {
  const safeNow = Math.max(0, Number.isFinite(atMs) ? atMs : Date.now());
  const safeTtl = Math.max(120000, Number.isFinite(ttlMs) ? ttlMs : 120000);
  return {
    owner: String(owner || ''),
    token: String(token || ''),
    expiresAt: safeNow + safeTtl,
    runtime: String(runtime || '')
  };
}

const fieldFlowSyncLockApi: FieldFlowSyncLockApi = {
  parse: fieldFlowParseSyncLease,
  isExpired: (lease: FieldFlowSyncLeaseRecord | null, atMs: number = Date.now()): boolean => !lease || lease.expiresAt <= atMs,
  isForeignActive: (lease: FieldFlowSyncLeaseRecord | null, owner: string, atMs: number = Date.now()): boolean => Boolean(lease && lease.owner !== owner && lease.expiresAt > atMs),
  owns: (lease: FieldFlowSyncLeaseRecord | null, owner: string, token: string): boolean => Boolean(lease && lease.owner === owner && lease.token === token),
  create: fieldFlowCreateSyncLease,
  renew: (lease: FieldFlowSyncLeaseRecord | null, owner: string, token: string, atMs: number, ttlMs: number, runtime: string): FieldFlowSyncLeaseRecord | null => {
    if (!fieldFlowSyncLockApi.owns(lease, owner, token)) return null;
    return fieldFlowCreateSyncLease(owner, token, atMs, ttlMs, runtime);
  }
};
