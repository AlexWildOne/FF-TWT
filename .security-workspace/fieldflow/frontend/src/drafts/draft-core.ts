const FIELD_FLOW_TYPED_DRAFT_CONTRACT = 'fieldflow-typed-draft-media-core-v1';
const FIELD_FLOW_DRAFT_DB_NAME = 'fieldflow-form-drafts';
const FIELD_FLOW_DRAFT_DB_VERSION = 2;
const FIELD_FLOW_DRAFT_SNAPSHOT_STORE = 'snapshots';
const FIELD_FLOW_DRAFT_FILE_STORE = 'files';

interface FieldFlowDraftSnapshotLike {
  version?: number;
  createdAt?: number;
  updatedAt?: number;
  expiresAt?: number;
  schemaRevision?: string;
  existingSubmissionId?: number;
  editMode?: boolean;
  identity?: FieldFlowDraftIdentity;
  files?: FieldFlowDraftFileDescriptor[];
  [key: string]: unknown;
}

interface FieldFlowDraftIdentity {
  formId?: number;
  bindingId?: number;
  clientId?: number;
  projectId?: number;
  routeId?: number;
  routeStopId?: number;
  locationId?: number;
  reportDay?: string;
}

interface FieldFlowDraftFileDescriptor {
  name?: string;
  ordinal?: number;
  count?: number;
  names?: string[];
}

interface FieldFlowDraftStoredFile {
  id: string;
  draftKey: string;
  inputName: string;
  ordinal: number;
  fileIndex: number;
  name: string;
  type: string;
  lastModified: number;
  blob: Blob;
  updatedAt: number;
  size?: number;
  token?: string;
  [key: string]: unknown;
}

interface FieldFlowDraftContext {
  nowMs: number;
  dailyExisting: boolean;
  dailyCanEdit: boolean;
  currentExistingSubmissionId: number;
  identity: FieldFlowDraftIdentity;
  allowRouteRebase?: boolean;
}

interface FieldFlowDraftNewestResult {
  source: 'local' | 'indexed' | 'none';
  snapshot: FieldFlowDraftSnapshotLike | null;
  updatedAt: number;
}

interface FieldFlowDraftPersistenceApi {
  available: () => boolean;
  storeSnapshot: (draftKey: string, snapshot: FieldFlowDraftSnapshotLike) => Promise<boolean>;
  readSnapshot: (draftKey: string) => Promise<FieldFlowDraftSnapshotLike | null>;
  deleteSnapshot: (draftKey: string) => Promise<void>;
  storeFiles: (draftKey: string, inputName: string, ordinal: number, files: Blob[], rawCount?: number) => Promise<number>;
  readFiles: (draftKey: string) => Promise<FieldFlowDraftStoredFile[]>;
  deleteFiles: (draftKey: string) => Promise<void>;
  cleanupExpiredSnapshots: (nowMs?: number) => Promise<number>;
}

interface FieldFlowDraftStateApi {
  contextValid: (snapshot: FieldFlowDraftSnapshotLike | null, context: FieldFlowDraftContext) => boolean;
  schemaMatches: (snapshot: FieldFlowDraftSnapshotLike | null, schemaRevision: string) => boolean;
  expectedFileCount: (snapshot: FieldFlowDraftSnapshotLike | null) => number;
  newestLocal: (local: FieldFlowDraftSnapshotLike | null, indexed: FieldFlowDraftSnapshotLike | null) => FieldFlowDraftNewestResult;
}

interface FieldFlowDraftMediaApi {
  groupKey: (inputName: unknown, ordinal: unknown) => string;
  signature: (files: ArrayLike<{name?: unknown; size?: unknown; type?: unknown; lastModified?: unknown}> | null | undefined) => string;
  validRecords: (records: unknown) => FieldFlowDraftStoredFile[];
  recordsForGroup: (records: unknown, inputName: string, ordinal: number) => FieldFlowDraftStoredFile[];
}

interface FieldFlowTypedDraftCoreApi {
  contract: string;
  buildVersion: string;
  dbName: string;
  dbVersion: number;
  persistence: FieldFlowDraftPersistenceApi;
  state: FieldFlowDraftStateApi;
  media: FieldFlowDraftMediaApi;
}

let fieldFlowDraftDbPromise: Promise<IDBDatabase> | null = null;

function fieldFlowDraftAvailable(): boolean {
  return Boolean(window.indexedDB);
}

function fieldFlowDraftOpenDb(): Promise<IDBDatabase> {
  if (fieldFlowDraftDbPromise) return fieldFlowDraftDbPromise;
  fieldFlowDraftDbPromise = new Promise<IDBDatabase>((resolve, reject) => {
    if (!window.indexedDB) {
      reject(new Error('indexeddb_unavailable'));
      return;
    }
    const request = window.indexedDB.open(FIELD_FLOW_DRAFT_DB_NAME, FIELD_FLOW_DRAFT_DB_VERSION);
    request.onupgradeneeded = function onDraftUpgrade(): void {
      const db = request.result;
      const transaction = request.transaction;
      const files = db.objectStoreNames.contains(FIELD_FLOW_DRAFT_FILE_STORE)
        ? transaction?.objectStore(FIELD_FLOW_DRAFT_FILE_STORE)
        : db.createObjectStore(FIELD_FLOW_DRAFT_FILE_STORE, { keyPath: 'id' });
      if (files && !files.indexNames.contains('draftKey')) files.createIndex('draftKey', 'draftKey', { unique: false });
      if (!db.objectStoreNames.contains(FIELD_FLOW_DRAFT_SNAPSHOT_STORE)) {
        db.createObjectStore(FIELD_FLOW_DRAFT_SNAPSHOT_STORE, { keyPath: 'draftKey' });
      }
    };
    request.onsuccess = function onDraftOpen(): void { resolve(request.result); };
    request.onerror = function onDraftOpenError(): void { reject(request.error || new Error('indexeddb_open_failed')); };
  }).catch((error: unknown) => {
    fieldFlowDraftDbPromise = null;
    throw error;
  });
  return fieldFlowDraftDbPromise;
}

function fieldFlowDraftTransactionDone(transaction: IDBTransaction, errorCode: string): Promise<void> {
  return new Promise<void>((resolve, reject) => {
    transaction.oncomplete = function onComplete(): void { resolve(); };
    transaction.onerror = function onError(): void { reject(transaction.error || new Error(errorCode)); };
    transaction.onabort = function onAbort(): void { reject(transaction.error || new Error(errorCode + '_aborted')); };
  });
}

async function fieldFlowDraftStoreSnapshot(draftKey: string, snapshot: FieldFlowDraftSnapshotLike): Promise<boolean> {
  if (!draftKey || !snapshot) return false;
  const db = await fieldFlowDraftOpenDb();
  const transaction = db.transaction(FIELD_FLOW_DRAFT_SNAPSHOT_STORE, 'readwrite');
  transaction.objectStore(FIELD_FLOW_DRAFT_SNAPSHOT_STORE).put({
    draftKey,
    snapshot,
    updatedAt: Math.max(0, Number(snapshot.updatedAt) || Date.now()),
    expiresAt: Math.max(0, Number(snapshot.expiresAt) || 0)
  });
  await fieldFlowDraftTransactionDone(transaction, 'indexeddb_snapshot_write_failed');
  return true;
}

async function fieldFlowDraftReadSnapshot(draftKey: string): Promise<FieldFlowDraftSnapshotLike | null> {
  if (!draftKey) return null;
  const db = await fieldFlowDraftOpenDb();
  return new Promise<FieldFlowDraftSnapshotLike | null>((resolve, reject) => {
    const transaction = db.transaction(FIELD_FLOW_DRAFT_SNAPSHOT_STORE, 'readonly');
    const request = transaction.objectStore(FIELD_FLOW_DRAFT_SNAPSHOT_STORE).get(draftKey);
    request.onsuccess = function onSnapshotRead(): void {
      const row = request.result as { snapshot?: FieldFlowDraftSnapshotLike } | undefined;
      resolve(row && row.snapshot ? row.snapshot : null);
    };
    request.onerror = function onSnapshotReadError(): void { reject(request.error || new Error('indexeddb_snapshot_read_failed')); };
  });
}

async function fieldFlowDraftDeleteSnapshot(draftKey: string): Promise<void> {
  if (!draftKey) return;
  const db = await fieldFlowDraftOpenDb();
  const transaction = db.transaction(FIELD_FLOW_DRAFT_SNAPSHOT_STORE, 'readwrite');
  transaction.objectStore(FIELD_FLOW_DRAFT_SNAPSHOT_STORE).delete(draftKey);
  await fieldFlowDraftTransactionDone(transaction, 'indexeddb_snapshot_delete_failed');
}

async function fieldFlowDraftDeleteFiles(draftKey: string): Promise<void> {
  if (!draftKey) return;
  const db = await fieldFlowDraftOpenDb();
  const transaction = db.transaction(FIELD_FLOW_DRAFT_FILE_STORE, 'readwrite');
  const index = transaction.objectStore(FIELD_FLOW_DRAFT_FILE_STORE).index('draftKey');
  const request = index.openCursor(IDBKeyRange.only(draftKey));
  request.onsuccess = function onDraftFileCursor(): void {
    const cursor = request.result;
    if (!cursor) return;
    cursor.delete();
    cursor.continue();
  };
  await fieldFlowDraftTransactionDone(transaction, 'indexeddb_delete_failed');
}

function fieldFlowDraftValidBlobs(files: Blob[]): Blob[] {
  return Array.from(files || []).filter((file): file is Blob => file instanceof Blob && Number(file.size || 0) > 0);
}

async function fieldFlowDraftStoreFiles(draftKey: string, inputName: string, ordinal: number, rawFiles: Blob[], rawCount: number = rawFiles.length): Promise<number> {
  if (!draftKey || !inputName) return 0;
  const files = fieldFlowDraftValidBlobs(rawFiles);
  if (Math.max(0, Number(rawCount) || 0) > 0 && files.length === 0) return 0;
  const db = await fieldFlowDraftOpenDb();
  const transaction = db.transaction(FIELD_FLOW_DRAFT_FILE_STORE, 'readwrite');
  const store = transaction.objectStore(FIELD_FLOW_DRAFT_FILE_STORE);
  const index = store.index('draftKey');
  const safeOrdinal = Math.max(0, Math.floor(Number(ordinal) || 0));
  const request = index.openCursor(IDBKeyRange.only(draftKey));
  request.onsuccess = function onExistingDraftFile(): void {
    const cursor = request.result;
    if (cursor) {
      const value = cursor.value as Partial<FieldFlowDraftStoredFile>;
      if (String(value.inputName || '') === inputName && Math.max(0, Number(value.ordinal) || 0) === safeOrdinal) cursor.delete();
      cursor.continue();
      return;
    }
    const generation = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    files.forEach((blob, fileIndex) => {
      const fileLike = blob as File;
      const name = String(fileLike.name || `ficheiro-${fileIndex + 1}`);
      const type = String(blob.type || '');
      const lastModified = Math.max(0, Number(fileLike.lastModified) || Date.now());
      store.put({
        id: `${draftKey}::${encodeURIComponent(inputName)}::${safeOrdinal}::${fileIndex}`,
        draftKey,
        inputName,
        ordinal: safeOrdinal,
        fileIndex,
        name,
        type,
        lastModified,
        blob,
        size: Number(blob.size || 0),
        updatedAt: Date.now(),
        generation
      });
    });
  };
  await fieldFlowDraftTransactionDone(transaction, 'indexeddb_write_failed');
  return files.length;
}

async function fieldFlowDraftReadFiles(draftKey: string): Promise<FieldFlowDraftStoredFile[]> {
  if (!draftKey) return [];
  const db = await fieldFlowDraftOpenDb();
  return new Promise<FieldFlowDraftStoredFile[]>((resolve, reject) => {
    const rows: FieldFlowDraftStoredFile[] = [];
    const transaction = db.transaction(FIELD_FLOW_DRAFT_FILE_STORE, 'readonly');
    const request = transaction.objectStore(FIELD_FLOW_DRAFT_FILE_STORE).index('draftKey').openCursor(IDBKeyRange.only(draftKey));
    request.onsuccess = function onDraftFileRead(): void {
      const cursor = request.result;
      if (!cursor) return;
      rows.push(cursor.value as FieldFlowDraftStoredFile);
      cursor.continue();
    };
    transaction.oncomplete = function onDraftFileReadComplete(): void { resolve(rows); };
    transaction.onerror = function onDraftFileReadError(): void { reject(transaction.error || new Error('indexeddb_read_failed')); };
  });
}

async function fieldFlowDraftCleanupExpiredSnapshots(nowMs: number = Date.now()): Promise<number> {
  const db = await fieldFlowDraftOpenDb();
  const expired: string[] = [];
  const transaction = db.transaction(FIELD_FLOW_DRAFT_SNAPSHOT_STORE, 'readwrite');
  const request = transaction.objectStore(FIELD_FLOW_DRAFT_SNAPSHOT_STORE).openCursor();
  request.onsuccess = function onSnapshotCursor(): void {
    const cursor = request.result;
    if (!cursor) return;
    const row = cursor.value as { draftKey?: unknown; expiresAt?: unknown; snapshot?: FieldFlowDraftSnapshotLike };
    const expiresAt = Math.max(0, Number(row.expiresAt || row.snapshot?.expiresAt) || 0);
    if (expiresAt > 0 && expiresAt < nowMs) {
      expired.push(String(row.draftKey || cursor.key || ''));
      cursor.delete();
    }
    cursor.continue();
  };
  await fieldFlowDraftTransactionDone(transaction, 'indexeddb_snapshot_cleanup_failed');
  for (const key of expired.filter(Boolean)) await fieldFlowDraftDeleteFiles(key);
  return expired.length;
}

function fieldFlowDraftContextValid(snapshot: FieldFlowDraftSnapshotLike | null, context: FieldFlowDraftContext): boolean {
  if (!snapshot || Math.floor(Number(snapshot.version) || 0) !== 2) return false;
  const nowMs = Math.max(0, Number(context.nowMs) || Date.now());
  if (Math.max(0, Number(snapshot.expiresAt) || 0) < nowMs) return false;

  const saved = snapshot.identity;
  const current = context.identity;
  if (!saved || !current) return false;
  const numericIdentity = (value: unknown): number => Math.max(0, Math.floor(Number(value) || 0));
  const stableKeys: Array<keyof FieldFlowDraftIdentity> = ['formId', 'bindingId', 'clientId', 'projectId', 'locationId'];
  for (const key of stableKeys) {
    if (numericIdentity(saved[key]) !== numericIdentity(current[key])) return false;
  }
  if (String(saved.reportDay || '') !== String(current.reportDay || '')) return false;
  if (!context.allowRouteRebase) {
    if (numericIdentity(saved.routeId) !== numericIdentity(current.routeId)) return false;
    if (numericIdentity(saved.routeStopId) !== numericIdentity(current.routeStopId)) return false;
  }
  if (numericIdentity(current.formId) <= 0 || numericIdentity(current.locationId) <= 0) return false;

  const currentExisting = Math.max(0, Math.floor(Number(context.currentExistingSubmissionId) || 0));
  const snapshotExisting = Math.max(0, Math.floor(Number(snapshot.existingSubmissionId) || 0));
  if (context.dailyExisting) {
    if (!context.dailyCanEdit || !snapshot.editMode || !currentExisting || currentExisting !== snapshotExisting) return false;
  } else if (snapshotExisting > 0) {
    return false;
  }
  return true;
}

function fieldFlowDraftExpectedFileCount(snapshot: FieldFlowDraftSnapshotLike | null): number {
  return Array.isArray(snapshot?.files)
    ? snapshot.files.reduce((total, item) => total + Math.max(0, Math.floor(Number(item && item.count) || 0)), 0)
    : 0;
}

function fieldFlowDraftNewestLocal(local: FieldFlowDraftSnapshotLike | null, indexed: FieldFlowDraftSnapshotLike | null): FieldFlowDraftNewestResult {
  const localUpdated = Math.max(0, Number(local?.updatedAt) || 0);
  const indexedUpdated = Math.max(0, Number(indexed?.updatedAt) || 0);
  if (!local && !indexed) return { source: 'none', snapshot: null, updatedAt: 0 };
  if (indexed && indexedUpdated > localUpdated) return { source: 'indexed', snapshot: indexed, updatedAt: indexedUpdated };
  return { source: 'local', snapshot: local || indexed, updatedAt: Math.max(localUpdated, indexedUpdated) };
}

function fieldFlowDraftGroupKey(inputName: unknown, ordinal: unknown): string {
  return `${String(inputName || '')}|${Math.max(0, Math.floor(Number(ordinal) || 0))}`;
}

function fieldFlowDraftSignature(files: ArrayLike<{name?: unknown; size?: unknown; type?: unknown; lastModified?: unknown}> | null | undefined): string {
  return Array.from(files || []).map((file) => [
    String(file && file.name || ''),
    Math.max(0, Number(file && file.size) || 0),
    String(file && file.type || ''),
    Math.max(0, Number(file && file.lastModified) || 0)
  ].join(':')).join('|');
}

function fieldFlowDraftValidRecords(records: unknown): FieldFlowDraftStoredFile[] {
  if (!Array.isArray(records)) return [];
  return records.filter((record): record is FieldFlowDraftStoredFile => {
    if (!record || typeof record !== 'object') return false;
    const candidate = record as Partial<FieldFlowDraftStoredFile>;
    const size = Math.max(0, Number(candidate.blob?.size || candidate.size) || 0);
    return Boolean(candidate.inputName && candidate.blob instanceof Blob && size > 0);
  }).sort((left, right) => (Number(left.fileIndex) || 0) - (Number(right.fileIndex) || 0));
}

const fieldFlowDraftPersistenceApi: FieldFlowDraftPersistenceApi = Object.freeze({
  available: fieldFlowDraftAvailable,
  storeSnapshot: fieldFlowDraftStoreSnapshot,
  readSnapshot: fieldFlowDraftReadSnapshot,
  deleteSnapshot: fieldFlowDraftDeleteSnapshot,
  storeFiles: fieldFlowDraftStoreFiles,
  readFiles: fieldFlowDraftReadFiles,
  deleteFiles: fieldFlowDraftDeleteFiles,
  cleanupExpiredSnapshots: fieldFlowDraftCleanupExpiredSnapshots
});

const fieldFlowDraftStateApi: FieldFlowDraftStateApi = Object.freeze({
  contextValid: fieldFlowDraftContextValid,
  schemaMatches: (snapshot: FieldFlowDraftSnapshotLike | null, schemaRevision: string): boolean => String(snapshot?.schemaRevision || '') === String(schemaRevision || ''),
  expectedFileCount: fieldFlowDraftExpectedFileCount,
  newestLocal: fieldFlowDraftNewestLocal
});

const fieldFlowDraftMediaApi: FieldFlowDraftMediaApi = Object.freeze({
  groupKey: fieldFlowDraftGroupKey,
  signature: fieldFlowDraftSignature,
  validRecords: fieldFlowDraftValidRecords,
  recordsForGroup: (records: unknown, inputName: string, ordinal: number): FieldFlowDraftStoredFile[] => {
    const groupKey = fieldFlowDraftGroupKey(inputName, ordinal);
    return fieldFlowDraftValidRecords(records).filter((record) => fieldFlowDraftGroupKey(record.inputName, record.ordinal) === groupKey);
  }
});

const fieldFlowTypedDraftCoreApi: FieldFlowTypedDraftCoreApi = Object.freeze({
  contract: FIELD_FLOW_TYPED_DRAFT_CONTRACT,
  buildVersion: FIELD_FLOW_FRONTEND_BUILD_VERSION,
  dbName: FIELD_FLOW_DRAFT_DB_NAME,
  dbVersion: FIELD_FLOW_DRAFT_DB_VERSION,
  persistence: fieldFlowDraftPersistenceApi,
  state: fieldFlowDraftStateApi,
  media: fieldFlowDraftMediaApi
});
