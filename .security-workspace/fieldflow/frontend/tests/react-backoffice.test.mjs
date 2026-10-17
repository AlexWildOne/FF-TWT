import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test, { after } from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';

const testsDir = path.dirname(fileURLToPath(import.meta.url));
const frontendDir = path.resolve(testsDir, '..');
const pluginDir = path.resolve(frontendDir, '..');
const plugin = fs.readFileSync(path.join(pluginDir, 'fieldflow.php'), 'utf8');
const version = plugin.match(/define\(\s*['\"]FIELDFLOW_VERSION['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/)?.[1] || '';
const releaseAssetPath = path.join(pluginDir, 'assets', `fieldflow-react-backoffice-${version}.js`);
const assetPath = path.join(frontendDir, '.react-backoffice-test.js');
execFileSync(path.join(frontendDir, 'node_modules', '.bin', 'tsc'), ['-p', 'tsconfig.react-backoffice.json', '--outFile', assetPath], { cwd: frontendDir });
after(() => fs.unlinkSync(assetPath));

function dispatcherHarness(onRender = () => {}) {
  let dispatcher = null;
  const roots = [];
  return {
    roots,
    createElement(type, props, ...children) { return { type, props: props || {}, children }; },
    useState(initial) {
      assert.ok(dispatcher, 'Invalid hook call: state outside React render');
      const owner = dispatcher;
      const index = owner.cursor++;
      if (!(index in owner.slots)) owner.slots[index] = initial;
      return [owner.slots[index], next => {
        assert.equal(owner.disposed, false, 'State update after unmount');
        owner.slots[index] = next;
      }];
    },
    useEffect(effect, deps) {
      assert.ok(dispatcher, 'Invalid hook call: effect outside React render');
      const owner = dispatcher;
      const index = owner.cursor++;
      const old = owner.slots[index];
      if (!old || !deps || deps.some((value, i) => !Object.is(value, old.deps?.[i]))) {
        owner.effects.push(() => {
          old?.cleanup?.();
          owner.slots[index] = { deps, cleanup: effect() };
        });
      }
    },
    createRoot(host) {
      assert.ok(!roots.some(root => root.host === host && !root.disposed), 'Duplicate React root');
      const owner = {
        host, slots: [], effects: [], cursor: 0, disposed: false, node: null, output: null,
        render(node) {
          this.node = node;
          this.cursor = 0;
          dispatcher = this;
          try { this.output = typeof node.type === 'function' ? node.type(node.props, undefined) : node; }
          finally { dispatcher = null; }
          this.effects.splice(0).forEach(effect => effect());
          onRender(this.output);
        },
        unmount() {
          this.slots.forEach(slot => slot?.cleanup?.());
          this.disposed = true;
        },
      };
      roots.push(owner);
      return owner;
    },
  };
}

test('React backoffice bundle uses WordPress wp-element and matches release identity', () => {
  assert.match(version, /^\d+\.\d+\.\d+(?:\.\d+)?$/);
  assert.ok(fs.existsSync(assetPath));
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(fs.readFileSync(releaseAssetPath, 'utf8').includes(`FieldFlow ${version}`));
  assert.ok(asset.includes('fieldflow-react-backoffice-foundation-v1'));
  assert.ok(asset.includes('mobile-release-status'));
  assert.ok(asset.includes('wordpress-wp-element'));
});

test('React mobile release island mounts progressively and keeps a stable API', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  let rendered = null;
  const root = {
    attrs: new Map([
      ['data-fieldflow-react-island', 'mobile-release-status'],
      ['data-fieldflow-react-props', JSON.stringify({
        contract: 'fieldflow-react-backoffice-foundation-v1',
        island: 'mobile-release-status',
        title: 'Estado de publicação',
        description: 'Teste',
        bridgeStatus: 'Operacional',
        maintenanceStatus: 'Desligada',
        platforms: [
          { key: 'android', label: 'Android', enabled: true, minimumVersion: '1.0.1', latestVersion: '1.0.2', buildNumber: '10' },
          { key: 'ios', label: 'iOS', enabled: true, minimumVersion: '1.0.1', latestVersion: '1.0.2', buildNumber: '11' }
        ]
      })]
    ]),
    getAttribute(name) { return this.attrs.get(name) || null; },
    setAttribute(name, value) { this.attrs.set(name, String(value)); }
  };
  const document = {
    readyState: 'complete',
    querySelectorAll() { return [root]; },
    addEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  const context = { window: { wp: { element: wpElement } }, document, queueMicrotask, console, JSON };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(context.window.FieldFlowReactBackoffice.version, version);
  assert.equal(context.window.FieldFlowReactBackoffice.contract, 'fieldflow-react-backoffice-foundation-v1');
  assert.equal(context.window.FieldFlowReactBackoffice.source, 'wordpress-wp-element');
  assert.equal(context.window.FieldFlowReactBackoffice.ready, true);
  assert.equal(root.getAttribute('data-fieldflow-react-mounted'), '1');
  assert.ok(rendered);
});

test('React device control island is compiled with async list/action contracts and fallback-safe mount', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('device-control-table'));
  assert.ok(asset.includes('fieldflow_react_devices_list'));
  assert.ok(asset.includes('fieldflow_react_device_action'));
  assert.ok(asset.includes('same-origin'));

  let rendered = null;
  const root = {
    attrs: new Map([
      ['data-fieldflow-react-island', 'device-control-table'],
      ['data-fieldflow-react-props', JSON.stringify({
        contract: 'fieldflow-react-backoffice-foundation-v1',
        island: 'device-control-table',
        title: 'Dispositivos',
        description: 'Teste',
        ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
        nonce: 'nonce-test',
        listing: {
          rows: [], total: 0, page: 1, perPage: 25, pages: 1, search: '', platform: ''
        },
        platformOptions: [{ value: '', label: 'Todas as plataformas' }]
      })]
    ]),
    getAttribute(name) { return this.attrs.get(name) || null; },
    setAttribute(name, value) { this.attrs.set(name, String(value)); }
  };
  const document = {
    readyState: 'complete',
    querySelectorAll() { return [root]; },
    addEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  const context = {
    window: { wp: { element: wpElement }, confirm: () => true },
    document,
    queueMicrotask,
    console,
    JSON,
    fetch: async () => ({ ok: true, json: async () => ({ success: true, data: {} }) }),
    URLSearchParams,
    Intl,
    Date,
    Number,
    setTimeout,
    clearTimeout,
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(root.getAttribute('data-fieldflow-react-mounted'), '1');
  assert.ok(rendered);
  assert.equal(context.window.FieldFlowReactBackoffice.ready, true);
});

test('React shared backoffice foundation centralizes transport and date helpers', () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('fieldflow-react-backoffice-shared-v1'));
  assert.ok(asset.includes('fieldFlowReactPost'));
  assert.ok(asset.includes('fieldFlowReactTodayRange'));
});

test('React Analytics context and KPI island is compiled with no-refresh controller contract', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('fieldflow-react-analytics-foundation-v1'));
  assert.ok(asset.includes('analytics-context-kpis'));
  assert.ok(asset.includes('FieldFlowAnalytics2'));
  assert.ok(asset.includes('fieldflow:analytics2-data'));
  assert.ok(asset.includes('fieldflow:analytics2-state'));

  let rendered = null;
  const root = {
    attrs: new Map([
      ['data-fieldflow-react-island', 'analytics-context-kpis'],
      ['data-fieldflow-react-props', JSON.stringify({
        contract: 'fieldflow-react-backoffice-foundation-v1',
        sharedContract: 'fieldflow-react-backoffice-shared-v1',
        island: 'analytics-context-kpis',
        title: 'Analytics em tempo real',
        description: 'Teste',
        fallbackId: 'ff-a2-legacy-control-fallback',
        values: { from: '2026-09-01', to: '2026-09-18', client_id: '0', project_id: '0', route_id: '0', user_id: '0', location_id: '0' },
        defaultValues: { from: '2026-08-20', to: '2026-09-18', client_id: '0', project_id: '0', route_id: '0', user_id: '0', location_id: '0' },
        clients: [], projects: [], routes: [], users: [], locations: []
      })]
    ]),
    getAttribute(name) { return this.attrs.get(name) || null; },
    setAttribute(name, value) { this.attrs.set(name, String(value)); }
  };
  const fallback = { classList: { add() {}, remove() {} } };
  const document = {
    readyState: 'complete',
    querySelectorAll() { return [root]; },
    getElementById(id) { return id === 'ff-a2-legacy-control-fallback' ? fallback : null; },
    addEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  const listeners = new Map();
  const context = {
    window: {
      wp: { element: wpElement },
      addEventListener(name, fn) { listeners.set(name, fn); },
      removeEventListener(name) { listeners.delete(name); },
      location: { href: 'https://example.test/wp-admin/admin.php?page=routespro-assignments-hub&tab=analytics', assign() {} }
    },
    document,
    queueMicrotask,
    console,
    JSON,
    URL,
    Intl,
    Date,
    Number,
    fetch: async () => ({ ok: true, json: async () => ({ success: true, data: {} }) }),
    URLSearchParams,
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(root.getAttribute('data-fieldflow-react-mounted'), '1');
  assert.ok(rendered);
  assert.equal(context.window.FieldFlowReactBackoffice.ready, true);
});
