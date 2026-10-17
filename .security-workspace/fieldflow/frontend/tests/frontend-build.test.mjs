import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const testsDir = path.dirname(fileURLToPath(import.meta.url));
const frontendDir = path.resolve(testsDir, '..');
const pluginDir = path.resolve(frontendDir, '..');
const plugin = fs.readFileSync(path.join(pluginDir, 'fieldflow.php'), 'utf8');
const version = plugin.match(/define\(\s*['"]FIELDFLOW_VERSION['"]\s*,\s*['"]([^'"]+)['"]\s*\)/)?.[1] || '';
const assetPath = path.join(pluginDir, 'assets', `fieldflow-frontend-core-${version}.js`);
const submissionFixtures = JSON.parse(fs.readFileSync(path.join(frontendDir, 'tests', 'submission-validation-fixtures.json'), 'utf8'));
const conditionalFixtures = JSON.parse(fs.readFileSync(path.join(testsDir, 'conditional-flow-fixtures.json'), 'utf8'));
const specializedFixtures = JSON.parse(fs.readFileSync(path.join(testsDir, 'specialized-validation-fixtures.json'), 'utf8'));

test('compiled asset identity matches the plugin release', () => {
  assert.match(version, /^\d+\.\d+\.\d+(?:\.\d+)?$/);
  assert.ok(fs.existsSync(assetPath));
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes(`FieldFlow ${version}`));
  assert.ok(asset.includes('fieldflow-frontend-build-foundation-v1'));
  assert.ok(asset.includes('fieldflow-typed-offline-core-v1'));
  assert.ok(asset.includes('fieldflow-typed-draft-media-core-v1'));
  assert.ok(asset.includes('fieldflow-typed-form-state-conditional-core-v1'));
  assert.ok(asset.includes('fieldflow-typed-submission-validation-core-v1'));
  assert.ok(asset.includes('fieldflow-typed-specialized-validation-core-v1'));
  assert.ok(asset.includes("source: 'typescript'"));
});

test('typed form state core matches canonical conditional fixtures', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = { window: { FieldFlowFrontendConfig: { version } }, document, CustomEvent: FakeCustomEvent, Date, Blob, queueMicrotask, console };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const forms = context.window.FieldFlowFrontend.formCore;
  assert.equal(forms.contract, 'fieldflow-typed-form-state-conditional-core-v1');
  for (const fixture of conditionalFixtures) {
    const result = forms.conditions.evaluate(fixture.keys, fixture.rules, fixture.values);
    assert.deepEqual(JSON.parse(JSON.stringify(result.visibility)), fixture.expected, fixture.name);
  }
});

test('typed form state keeps hidden required and order suggestion branches inactive', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = { window: { FieldFlowFrontendConfig: { version } }, document, CustomEvent: FakeCustomEvent, Date, Blob, queueMicrotask, console };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const forms = context.window.FieldFlowFrontend.formCore;
  const questions = [
    { key: 'need_order', type: 'radio', required: true },
    { key: 'order_suggestion', type: 'order_suggestion', required: true },
    { key: 'notes', type: 'text', required: true }
  ];
  const visibility = { need_order: true, order_suggestion: false, notes: true };
  assert.deepEqual(Array.from(forms.state.activeRequiredKeys(questions, visibility)), ['need_order', 'notes']);
  const snapshot = forms.conditions.snapshot(questions, visibility);
  assert.deepEqual(Array.from(snapshot.hidden_question_keys), ['order_suggestion']);
  assert.deepEqual(Array.from(snapshot.active_order_suggestion_keys), []);
  assert.deepEqual(JSON.parse(JSON.stringify(forms.state.filterActiveValues({ need_order: 'Não', order_suggestion: [{ order_qty: 2 }], notes: 'ok' }, visibility))), { need_order: 'Não', notes: 'ok' });
});

test('compiled TypeScript bootstrap exposes a stable browser API and is idempotent', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const events = [];
  class FakeCustomEvent {
    constructor(type, options = {}) { this.type = type; this.detail = options.detail; }
  }
  const listeners = new Map();
  const document = {
    readyState: 'complete',
    addEventListener(name, listener) { listeners.set(name, listener); },
    removeEventListener(name) { listeners.delete(name); },
    dispatchEvent(event) { events.push(event); const listener = listeners.get(event.type); if (typeof listener === 'function') listener(event); return true; }
  };
  const context = {
    window: {
      FieldFlowFrontendConfig: { version, context: 'front', native: true },
      FieldFlowOffline: { __runtimeBuild: version }
    },
    document,
    CustomEvent: FakeCustomEvent,
    Date,
    queueMicrotask,
    console
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(context.window.FieldFlowFrontend.version, version);
  assert.equal(context.window.FieldFlowFrontend.__buildVersion, version);
  assert.equal(context.window.FieldFlowFrontend.source, 'typescript');
  assert.equal(context.window.FieldFlowFrontend.ready, true);
  assert.equal(context.window.FieldFlowFrontend.snapshot().offlineRuntimeBuild, version);
  assert.equal(events.filter(event => event.type === 'fieldflow:frontend:ready').length, 1);

  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(events.filter(event => event.type === 'fieldflow:frontend:ready').length, 1);
});

test('typed offline core preserves degraded connectivity as usable', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = {
    readyState: 'complete',
    addEventListener() {},
    removeEventListener() {},
    dispatchEvent() { return true; }
  };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = {
    window: { FieldFlowFrontendConfig: { version, context: 'front', native: true } },
    document,
    CustomEvent: FakeCustomEvent,
    Date,
    queueMicrotask,
    console
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const core = context.window.FieldFlowFrontend.offlineCore;
  assert.equal(core.contract, 'fieldflow-typed-offline-core-v1');
  assert.equal(core.connectivity.isUsable('online', true), true);
  assert.equal(core.connectivity.isUsable('degraded', true), true);
  assert.equal(core.connectivity.isUsable('offline', true), false);
  assert.equal(core.connectivity.isUsable('degraded', false), false);
});

test('typed sync lease enforces owner and token with renewable ttl', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = { window: { FieldFlowFrontendConfig: { version } }, document, CustomEvent: FakeCustomEvent, Date, queueMicrotask, console };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const lock = context.window.FieldFlowFrontend.offlineCore.syncLock;
  const lease = lock.create('tab-a', 'token-a', 1000, 120000, version);
  assert.equal(lease.expiresAt, 121000);
  assert.equal(lock.owns(lease, 'tab-a', 'token-a'), true);
  assert.equal(lock.owns(lease, 'tab-a', 'token-b'), false);
  assert.equal(lock.isForeignActive(lease, 'tab-b', 2000), true);
  const renewed = lock.renew(lease, 'tab-a', 'token-a', 5000, 120000, version);
  assert.equal(renewed.expiresAt, 125000);
  assert.equal(lock.renew(lease, 'tab-b', 'token-a', 5000, 120000, version), null);
});

test('typed outbox keeps retry and stale recovery semantics compatible', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = { window: { FieldFlowFrontendConfig: { version } }, document, CustomEvent: FakeCustomEvent, Date, queueMicrotask, console };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const outbox = context.window.FieldFlowFrontend.offlineCore.outbox;
  assert.equal(outbox.retryDelay(0), 5000);
  assert.equal(outbox.retryDelay(2), 12000);
  assert.equal(outbox.isEligible({ status: 'pending', nextAttemptAt: 0 }, 1000, false), true);
  assert.equal(outbox.isEligible({ nextAttemptAt: 0 }, 1000, false), false);
  assert.equal(outbox.isEligible({ status: 'failed', nextAttemptAt: 0 }, 1000, false), false);
  assert.equal(outbox.isEligible({ status: 'failed', nextAttemptAt: 0 }, 1000, true), true);
  assert.equal(outbox.shouldRecoverStaleSyncing({ status: 'syncing', updatedAt: 1000 }, 302000, 300000), true);
  assert.equal(outbox.shouldRecoverStaleSyncing({ status: 'syncing', updatedAt: 0 }, 302000, 300000), true);
  assert.equal(outbox.shouldRecoverStaleSyncing({ status: 'pending', updatedAt: 1000 }, 302000, 300000), false);
});


test('typed draft core preserves snapshot, media and reconciliation semantics', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = {
    window: { FieldFlowFrontendConfig: { version, context: 'front', native: true } },
    document,
    CustomEvent: FakeCustomEvent,
    Date,
    Blob,
    queueMicrotask,
    console
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const drafts = context.window.FieldFlowFrontend.draftCore;
  assert.equal(drafts.contract, 'fieldflow-typed-draft-media-core-v1');
  assert.equal(drafts.dbName, 'fieldflow-form-drafts');
  assert.equal(drafts.dbVersion, 2);
  const identity = { formId: 88, bindingId: 5, clientId: 12, projectId: 34, routeId: 10, routeStopId: 142, locationId: 87, reportDay: '2026-10-01' };
  const baseContext = { nowMs: 1000, dailyExisting: false, dailyCanEdit: false, currentExistingSubmissionId: 0, identity };
  assert.equal(drafts.state.contextValid({ version: 2, expiresAt: 2000, existingSubmissionId: 0, identity }, baseContext), true);
  assert.equal(drafts.state.contextValid({ version: 2, expiresAt: 500, existingSubmissionId: 0, identity }, baseContext), false);
  assert.equal(drafts.state.contextValid({ version: 2, expiresAt: 2000, existingSubmissionId: 0 }, baseContext), false);
  assert.equal(drafts.state.contextValid({ version: 2, expiresAt: 2000, existingSubmissionId: 0, identity: { ...identity, locationId: 142 } }, baseContext), false);
  assert.equal(drafts.state.contextValid({ version: 2, expiresAt: 2000, existingSubmissionId: 0, identity: { ...identity, routeId: 11, routeStopId: 200 } }, { ...baseContext, allowRouteRebase: true }), true);
  assert.equal(drafts.state.contextValid({ version: 2, expiresAt: 2000, editMode: true, existingSubmissionId: 77, identity }, { ...baseContext, dailyExisting: true, dailyCanEdit: true, currentExistingSubmissionId: 77 }), true);
  assert.equal(drafts.state.contextValid({ version: 2, expiresAt: 2000, editMode: false, existingSubmissionId: 77, identity }, { ...baseContext, dailyExisting: true, dailyCanEdit: true, currentExistingSubmissionId: 77 }), false);
  assert.equal(drafts.state.schemaMatches({ schemaRevision: 'abc' }, 'abc'), true);
  assert.equal(drafts.state.expectedFileCount({ files: [{ count: 2 }, { count: 3 }] }), 5);
  assert.equal(drafts.state.newestLocal({ updatedAt: 10 }, { updatedAt: 20 }).source, 'indexed');
  assert.equal(drafts.media.groupKey('photo[]', 2), 'photo[]|2');
  assert.equal(drafts.media.signature([{ name: 'a.jpg', size: 10, type: 'image/jpeg', lastModified: 5 }]), 'a.jpg:10:image/jpeg:5');
});


test('typed submission validation core matches shared fixtures and filters hidden payload', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = { window: { FieldFlowFrontendConfig: { version } }, document, CustomEvent: FakeCustomEvent, Date, Blob, queueMicrotask, console };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const submission = context.window.FieldFlowFrontend.submissionCore;
  assert.equal(submission.contract, 'fieldflow-typed-submission-validation-core-v1');
  for (const fixture of submissionFixtures) {
    const state = submission.validation.prepare(fixture.questions, fixture.visibility, fixture.values);
    assert.equal(state.valid, fixture.expected.valid, fixture.name);
    assert.deepEqual(Array.from(state.missingRequiredKeys), fixture.expected.missing, fixture.name + ' missing');
    assert.deepEqual(Array.from(state.activeKeys), fixture.expected.active, fixture.name + ' active');
    assert.deepEqual(Array.from(state.hiddenKeys), fixture.expected.hidden, fixture.name + ' hidden');
    if (fixture.expected.active_values) {
      assert.deepEqual(JSON.parse(JSON.stringify(state.activeValues)), fixture.expected.active_values, fixture.name + ' payload');
    }
  }
  assert.equal(submission.validation.requiredMissing({ key: 'confirm', type: 'checkbox', required: true }, false, { confirm: true }), true);
  assert.equal(submission.validation.shouldPersist(false, 'checkbox', true), true);
});


test('canonical answer semantics normalizes numeric and choice values consistently', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = { window: { FieldFlowFrontendConfig: { version } }, document, CustomEvent: FakeCustomEvent, Date, Blob, queueMicrotask, console };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const core = context.window.FieldFlowFrontend.answerSemanticsCore;
  assert.equal(core.contract, 'fieldflow-canonical-answer-semantics-v1');
  assert.equal(core.normalize({ type: 'currency' }, '1.234,50').value, 1234.5);
  assert.equal(core.normalize({ type: 'number' }, 'abc').valid, false);
  assert.equal(core.normalize({ type: 'percent', min: 0, max: 100 }, '101').issue, 'number_above_max');
  assert.equal(core.normalize({ type: 'select', options: ['A', 'B'] }, 'B').value, 'B');
  assert.equal(core.normalize({ type: 'select', options: ['A', 'B'] }, 'C').issue, 'choice_not_allowed');
  assert.deepEqual(Array.from(core.normalize({ type: 'select', selection_mode: 'multiple', options: ['A', 'B'] }, ['A', 'A', 'B']).value), ['A', 'B']);
  assert.equal(core.normalize({ type: 'checkbox' }, false).value, 0);
  assert.equal(core.normalize({ type: 'product_matrix' }, 'bad').issue, 'structured_value_invalid');

  const submission = context.window.FieldFlowFrontend.submissionCore;
  const prepared = submission.validation.prepare(
    [{ key: 'amount', type: 'currency', required: true, min: 1, max: 10 }],
    { amount: true },
    { amount: '2,5' }
  );
  assert.equal(prepared.valid, true);
  assert.equal(prepared.activeValues.amount, 2.5);
  const invalid = submission.validation.prepare(
    [{ key: 'amount', type: 'currency', required: true, min: 1, max: 10 }],
    { amount: true },
    { amount: 'oops' }
  );
  assert.equal(invalid.valid, false);
  assert.deepEqual(Array.from(invalid.invalidSemanticKeys), ['amount']);
});

test('typed specialized validation core keeps web and mobile special-field semantics aligned', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const document = { readyState: 'complete', addEventListener() {}, removeEventListener() {}, dispatchEvent() { return true; } };
  class FakeCustomEvent { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } }
  const context = { window: { FieldFlowFrontendConfig: { version } }, document, CustomEvent: FakeCustomEvent, Date, Blob, queueMicrotask, console };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  const core = context.window.FieldFlowFrontend.specializedValidationCore;
  assert.equal(core.contract, 'fieldflow-typed-specialized-validation-core-v1');
  for (const fixture of specializedFixtures.categorized) {
    const result = core.validation.categorized(fixture.required, fixture.selected, fixture.media);
    assert.equal(result.valid, fixture.valid, fixture.name);
    assert.equal(result.issueCode, fixture.issue, fixture.name + ' issue');
  }
  for (const fixture of specializedFixtures.share_space) {
    const result = core.validation.shareSpace(fixture.required, fixture.rows, fixture.expected);
    assert.equal(result.valid, fixture.valid, fixture.name);
    assert.equal(result.issueCode, fixture.issue, fixture.name + ' issue');
  }
  for (const fixture of specializedFixtures.evidence) {
    const result = core.validation.evidence(fixture.answer, fixture.requirePhoto, fixture.requireReason, fixture.photo, fixture.reason);
    assert.equal(result.valid, fixture.valid, fixture.name);
    assert.equal(result.issueCode, fixture.issue, fixture.name + ' issue');
  }
  for (const fixture of specializedFixtures.product_matrix) {
    const result = core.validation.productMatrix(fixture.required, fixture.rows);
    assert.equal(result.valid, fixture.valid, fixture.name);
    assert.equal(result.issueCode, fixture.issue, fixture.name + ' issue');
  }
  assert.equal(core.validation.mediaRequired(true, []).valid, false);
  assert.equal(core.validation.mediaRequired(true, ['https://example.test/photo.jpg']).valid, true);
});
