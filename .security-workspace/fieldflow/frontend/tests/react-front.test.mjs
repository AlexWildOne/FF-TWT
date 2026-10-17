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
const releaseAssetPath = path.join(pluginDir, 'assets', `fieldflow-react-front-${version}.js`);
const assetPath = path.join(frontendDir, '.react-front-test.js');
execFileSync(path.join(frontendDir, 'node_modules', '.bin', 'tsc'), ['-p', 'tsconfig.react-front.json', '--outFile', assetPath], { cwd: frontendDir });
after(() => fs.unlinkSync(assetPath));

function dispatcherHarness(onRender = () => {}) {
  let dispatcher = null;
  const roots = [];
  const runtime = {
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
  return runtime;
}

test('React front bundle uses wp-element and matches release identity', () => {
  assert.match(version, /^\d+\.\d+\.\d+(?:\.\d+)?$/);
  assert.ok(fs.existsSync(assetPath));
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(fs.readFileSync(releaseAssetPath, 'utf8').includes(`FieldFlow ${version}`));
  assert.ok(asset.includes('fieldflow-react-front-foundation-v1'));
  assert.ok(asset.includes('fieldflow-react-form-wizard-progress-v1'));
  assert.ok(asset.includes('fieldflow-react-operational-shell-v1'));
  assert.ok(asset.includes('fieldflow-react-my-route-toolbar-v1'));
  assert.ok(asset.includes('fieldflow-react-simple-field-assist-v1'));
  assert.ok(asset.includes('fieldflow-react-simple-field-control-v1'));
  assert.ok(asset.includes('form-wizard-progress'));
  assert.ok(asset.includes('wordpress-wp-element'));
});

test('React form wizard progress mounts progressively without owning submission', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  let rendered = null;
  const fallbackLabel = { textContent: 'Passo 1 de 3 · 33%' };
  const fallback = {
    attrs: new Map(),
    querySelector(selector) { return selector === '[data-rp-progress-label]' ? fallbackLabel : null; },
    setAttribute(name, value) { this.attrs.set(name, String(value)); },
    removeAttribute(name) { this.attrs.delete(name); }
  };
  const activeTitle = { textContent: 'Frentes' };
  const activeStep = {
    getAttribute(name) { return name === 'data-step-index' ? '1' : null; },
    querySelector(selector) { return selector === '.routespro-form-step-title' ? activeTitle : null; }
  };
  const wizard = {
    querySelector(selector) { return selector === '[data-rp-step]:not([hidden])' ? activeStep : null; },
    addEventListener() {}, removeEventListener() {}
  };
  const root = {
    attrs: new Map([
      ['data-fieldflow-react-front-island', 'form-wizard-progress'],
      ['data-fieldflow-react-front-props', JSON.stringify({ contract: 'fieldflow-react-front-foundation-v1', wizardContract: 'fieldflow-react-form-wizard-progress-v1', island: 'form-wizard-progress', fallbackId: 'ff-form-progress-test', totalSteps: 3, label: 'Progresso da visita' })]
    ]),
    getAttribute(name) { return this.attrs.get(name) || null; },
    setAttribute(name, value) { this.attrs.set(name, String(value)); },
    closest(selector) { return selector === '.routespro-form-wizard' ? wizard : null; }
  };
  const document = {
    readyState: 'complete',
    querySelectorAll() { return [root]; },
    getElementById(id) { return id === 'ff-form-progress-test' ? fallback : null; },
    addEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  class MutationObserver { observe() {} disconnect() {} }
  const context = { window: { wp: { element: wpElement } }, document, queueMicrotask, console, JSON, Number, MutationObserver };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(context.window.FieldFlowReactFront.version, version);
  assert.equal(context.window.FieldFlowReactFront.contract, 'fieldflow-react-front-foundation-v1');
  assert.equal(context.window.FieldFlowReactFront.source, 'wordpress-wp-element');
  assert.equal(root.getAttribute('data-fieldflow-react-front-mounted'), '1');
  assert.equal(fallback.attrs.get('data-fieldflow-react-front-fallback-hidden'), '1');
  assert.ok(rendered);
  assert.ok(asset.includes('legacy_typed_runtime') === false);
});

test('React operational front wave compiles shell, route toolbar and simple-field assist contracts', () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('fieldflow-react-operational-shell-v1'));
  assert.ok(asset.includes('fieldflow-react-my-route-toolbar-v1'));
  assert.ok(asset.includes('fieldflow-react-simple-field-assist-v1'));
  assert.ok(asset.includes('operational-shell'));
  assert.ok(asset.includes('my-route-toolbar'));
  assert.ok(asset.includes('simple-field-assist'));
  assert.ok(asset.includes('fieldflow:lazy-panel-loaded'));
  assert.ok(asset.includes('fieldflow:form-rendered'));
});

test('React operational shell mounts progressively over the legacy navigation', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  let rendered = null;
  const activePanel = { dataset: { panel: 'route' } };
  const shell = {
    querySelector(selector) {
      if (selector === '.rp-panel.active') return activePanel;
      if (selector.includes('[data-panel="route"]')) return { click() {} };
      return null;
    },
    addEventListener() {},
    removeEventListener() {}
  };
  const fallback = { attrs: new Map(), setAttribute(name, value) { this.attrs.set(name, String(value)); }, removeAttribute(name) { this.attrs.delete(name); } };
  const root = {
    attrs: new Map([
      ['data-fieldflow-react-front-island', 'operational-shell'],
      ['data-fieldflow-react-front-props', JSON.stringify({ contract: 'fieldflow-react-front-foundation-v1', shellContract: 'fieldflow-react-operational-shell-v1', island: 'operational-shell', fallbackId: 'ff-shell-fallback', rootId: 'routespro-app', panels: [{ key: 'route', label: 'Minha Rota' }, { key: 'report', label: 'Reportar' }] })]
    ]),
    getAttribute(name) { return this.attrs.get(name) || null; },
    setAttribute(name, value) { this.attrs.set(name, String(value)); }
  };
  const document = {
    readyState: 'complete',
    body: null,
    querySelectorAll() { return [root]; },
    getElementById(id) { return id === 'routespro-app' ? shell : (id === 'ff-shell-fallback' ? fallback : null); },
    addEventListener() {},
    removeEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  class MutationObserver { observe() {} disconnect() {} }
  const context = {
    window: { wp: { element: wpElement }, addEventListener() {}, removeEventListener() {} },
    document,
    navigator: { onLine: true },
    queueMicrotask,
    console,
    JSON,
    Number,
    MutationObserver,
    Event: class Event {},
    HTMLInputElement: class HTMLInputElement {},
    HTMLTextAreaElement: class HTMLTextAreaElement {},
    HTMLSelectElement: class HTMLSelectElement {},
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(root.getAttribute('data-fieldflow-react-front-mounted'), '1');
  assert.equal(fallback.attrs.get('data-fieldflow-react-front-fallback-hidden'), '1');
  assert.ok(rendered);
});

test('React simple field control owns visible UI while canonical input owns value and submit semantics', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  let rendered = null;
  const events = [];
  class HTMLElementBase {
    constructor() { this.attrs = new Map(); this.focused = false; }
    setAttribute(name, value) { this.attrs.set(name, String(value)); }
    removeAttribute(name) { this.attrs.delete(name); }
    focus() { this.focused = true; }
  }
  class HTMLInputElementMock extends HTMLElementBase {
    constructor() { super(); this.value = ''; this.disabled = false; this.readOnly = false; this.min = '0'; this.max = '100'; this.step = '0.01'; this.listeners = new Map(); }
    closest() { return null; }
    addEventListener(name, fn) { this.listeners.set(name, fn); }
    removeEventListener(name) { this.listeners.delete(name); }
    dispatchEvent(event) { events.push(event.type); const fn = this.listeners.get(event.type); if (fn) fn(event); return true; }
  }
  class HTMLTextAreaElementMock extends HTMLElementBase {}
  class HTMLSelectElementMock extends HTMLElementBase {}
  const input = new HTMLInputElementMock();
  const field = new HTMLElementBase();
  field.addEventListener = () => {};
  field.removeEventListener = () => {};
  const reactControl = new HTMLElementBase();
  const root = new HTMLElementBase();
  root.getAttribute = (name) => root.attrs.get(name) || null;
  root.querySelector = (selector) => selector === '[data-fieldflow-react-simple-control-input]' ? reactControl : null;
  root.closest = (selector) => selector === '.routespro-form-field' ? field : null;
  root.attrs.set('data-fieldflow-react-front-island', 'simple-field-assist');
  root.attrs.set('data-fieldflow-react-front-props', JSON.stringify({
    contract: 'fieldflow-react-front-foundation-v1',
    fieldContract: 'fieldflow-react-simple-field-assist-v1',
    controlContract: 'fieldflow-react-simple-field-control-v1',
    island: 'simple-field-assist',
    fieldKey: 'qty',
    fieldType: 'number',
    inputId: 'qty',
    required: true,
    label: 'Quantidade',
  }));
  const document = {
    readyState: 'complete', body: null,
    querySelectorAll() { return [root]; },
    getElementById(id) { return id === 'qty' ? input : null; },
    addEventListener() {}, removeEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  class MutationObserver { observe() {} disconnect() {} }
  class EventMock { constructor(type, options = {}) { this.type = type; this.bubbles = Boolean(options.bubbles); } preventDefault() { this.defaultPrevented = true; } }
  const context = {
    window: { wp: { element: wpElement } }, document, navigator: { onLine: true }, queueMicrotask, console, JSON, Number, MutationObserver,
    HTMLElement: HTMLElementBase, HTMLInputElement: HTMLInputElementMock, HTMLTextAreaElement: HTMLTextAreaElementMock, HTMLSelectElement: HTMLSelectElementMock, Event: EventMock,
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.ok(rendered);
  assert.equal(rendered.props['data-react-contract'], 'fieldflow-react-simple-field-control-v1');
  assert.equal(input.attrs.get('data-fieldflow-react-simple-native'), '1');
  const stack = [rendered];
  let visibleInput = null;
  while (stack.length) {
    const node = stack.pop();
    if (!node || typeof node !== 'object') continue;
    if (node.props?.['data-fieldflow-react-simple-control-input'] === '1') { visibleInput = node; break; }
    if (Array.isArray(node.children)) stack.push(...node.children);
  }
  assert.ok(visibleInput);
  visibleInput.props.onChange({ target: { value: '12.5' } });
  assert.equal(input.value, '12.5');
  assert.deepEqual(events.slice(-2), ['input', 'change']);
});

test('React choice and advanced form wave compiles conditional bridges and special-field assistants', () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('fieldflow-react-choice-field-control-v1'));
  assert.ok(asset.includes('fieldflow-react-special-field-assist-v1'));
  assert.ok(asset.includes('choice-field-control'));
  assert.ok(asset.includes('special-field-assist'));
  assert.ok(asset.includes('fieldFlowChoiceWrite'));
  assert.ok(asset.includes("dispatchEvent(new Event('change'"));
  assert.ok(asset.includes('data-category-open-all'));
  assert.ok(asset.includes('data-order-qty'));
  assert.ok(asset.includes('data-share-space-card'));
});

test('React choice control writes the canonical radio group so conditional listeners keep ownership', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  let rendered = null;
  const events = [];
  class HTMLElementBase {
    constructor() { this.attrs = new Map(); this.listeners = new Map(); this.focused = false; }
    setAttribute(name, value) { this.attrs.set(name, String(value)); }
    removeAttribute(name) { this.attrs.delete(name); }
    getAttribute(name) { return this.attrs.get(name) || null; }
    addEventListener(name, fn) { this.listeners.set(name, fn); }
    removeEventListener(name) { this.listeners.delete(name); }
    focus() { this.focused = true; }
  }
  class HTMLInputElementMock extends HTMLElementBase {
    constructor(name, value) { super(); this.name = name; this.value = value; this.checked = false; this.disabled = false; }
    dispatchEvent(event) { events.push(`${this.value}:${event.type}`); const fn = this.listeners.get(event.type); if (fn) fn(event); return true; }
  }
  const yes = new HTMLInputElementMock('decision', 'Sim');
  const no = new HTMLInputElementMock('decision', 'Não');
  const native = new HTMLElementBase();
  const field = new HTMLElementBase();
  field.querySelectorAll = (selector) => selector.includes('input') ? [yes, no] : [];
  field.querySelector = (selector) => selector.includes('.routespro-form-fieldset') ? native : null;
  const root = new HTMLElementBase();
  root.attrs.set('data-fieldflow-react-front-island', 'choice-field-control');
  root.attrs.set('data-fieldflow-react-front-props', JSON.stringify({
    contract: 'fieldflow-react-front-foundation-v1',
    choiceContract: 'fieldflow-react-choice-field-control-v1',
    island: 'choice-field-control', fieldKey: 'decision', fieldType: 'radio', required: true, multiple: false, label: 'Decisão',
    options: [{ value: 'Sim', label: 'Sim' }, { value: 'Não', label: 'Não' }]
  }));
  root.closest = (selector) => selector === '.routespro-form-field' ? field : null;
  root.querySelectorAll = () => [];
  const document = {
    readyState: 'complete', body: null,
    querySelectorAll() { return [root]; }, getElementById() { return null; },
    addEventListener() {}, removeEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  class MutationObserver { observe() {} disconnect() {} }
  class EventMock { constructor(type, options = {}) { this.type = type; this.bubbles = Boolean(options.bubbles); } preventDefault() { this.defaultPrevented = true; } }
  const context = {
    window: { wp: { element: wpElement } }, document, navigator: { onLine: true }, queueMicrotask, console, JSON, Number,
    MutationObserver, HTMLElement: HTMLElementBase, HTMLInputElement: HTMLInputElementMock,
    HTMLTextAreaElement: class {}, HTMLSelectElement: class {}, Event: EventMock,
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.ok(rendered);
  const stack = [rendered];
  let noButton = null;
  while (stack.length) {
    const node = stack.pop();
    if (!node || typeof node !== 'object') continue;
    if (node.props?.['data-fieldflow-react-choice-value'] === 'Não') { noButton = node; break; }
    if (Array.isArray(node.children)) stack.push(...node.children);
  }
  assert.ok(noButton);
  noButton.props.onClick();
  assert.equal(no.checked, true);
  assert.equal(yes.checked, false);
  assert.ok(events.includes('Não:change'));
});

test('React media control compiles final closure contract without taking media transport ownership', () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('fieldflow-react-media-control-v1'));
  assert.ok(asset.includes('fieldFlowMediaClick'));
  assert.ok(asset.includes('data-fieldflow-react-media-native'));
  assert.ok(asset.includes('fieldflow:offline-form-synced'));
  assert.ok(asset.includes('fieldflow:sync-completed'));
});

test('React media surface proxies camera action to certified native upload trigger', async () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  let rendered = null;
  let cameraClicks = 0;
  class HTMLElementBase {
    constructor() { this.attrs = new Map(); this.listeners = new Map(); }
    setAttribute(name, value) { this.attrs.set(name, String(value)); }
    removeAttribute(name) { this.attrs.delete(name); }
    getAttribute(name) { return this.attrs.get(name) || null; }
    addEventListener(name, fn) { this.listeners.set(name, fn); }
    removeEventListener(name) { this.listeners.delete(name); }
  }
  class HTMLInputElementMock extends HTMLElementBase {
    constructor() { super(); this.files = []; this.value = ''; }
    click() {}
  }
  const camera = new HTMLElementBase(); camera.click = () => { cameraClicks += 1; };
  const gallery = new HTMLElementBase(); gallery.click = () => {};
  const nativeActions = new HTMLElementBase();
  const input = new HTMLInputElementMock();
  const field = new HTMLElementBase();
  field.querySelectorAll = (selector) => {
    if (selector === '[data-rp-upload-source-actions]') return [nativeActions];
    if (selector === '[data-rp-upload-file]') return [input];
    if (selector.includes('.routespro-evidence-existing img')) return [];
    if (selector.includes('[data-rp-upload-preview]')) return [];
    if (selector.includes('[data-rp-existing-media]')) return [];
    return [];
  };
  field.querySelector = (selector) => {
    if (selector === '[data-rp-upload-trigger="camera"]') return camera;
    if (selector === '[data-rp-upload-trigger="gallery"]') return gallery;
    if (selector === '[data-rp-upload-file]') return input;
    return null;
  };
  const root = new HTMLElementBase();
  root.attrs.set('data-fieldflow-react-front-island', 'special-field-assist');
  root.attrs.set('data-fieldflow-react-front-props', JSON.stringify({
    contract: 'fieldflow-react-front-foundation-v1',
    specialContract: 'fieldflow-react-special-field-assist-v1',
    mediaControlContract: 'fieldflow-react-media-control-v1',
    island: 'special-field-assist', fieldKey: 'photo', fieldType: 'image_upload', required: true, label: 'Fotografia', badge: 'Fotografias'
  }));
  root.closest = (selector) => selector === '.routespro-form-field' ? field : null;
  const document = {
    readyState: 'complete', body: null,
    querySelectorAll() { return [root]; }, getElementById() { return null; },
    addEventListener() {}, removeEventListener() {}
  };
  const wpElement = dispatcherHarness(node => { rendered = node; });
  class MutationObserver { observe() {} disconnect() {} }
  class EventMock { constructor(type, options = {}) { this.type = type; this.bubbles = Boolean(options.bubbles); } }
  const context = {
    window: { wp: { element: wpElement }, addEventListener() {}, removeEventListener() {} },
    document, navigator: { onLine: true }, queueMicrotask, console, JSON, Number,
    MutationObserver, HTMLElement: HTMLElementBase, HTMLInputElement: HTMLInputElementMock,
    HTMLTextAreaElement: class {}, HTMLSelectElement: class {}, Event: EventMock,
  };
  vm.createContext(context);
  vm.runInContext(asset, context);
  await new Promise(resolve => setImmediate(resolve));
  assert.ok(rendered);
  const stack = [rendered];
  let cameraButton = null;
  while (stack.length) {
    const node = stack.pop();
    if (!node || typeof node !== 'object') continue;
    if (node.type === 'button' && Array.isArray(node.children) && node.children.includes('Tirar fotografia')) { cameraButton = node; break; }
    if (Array.isArray(node.children)) stack.push(...node.children);
  }
  assert.ok(cameraButton);
  cameraButton.props.onClick();
  assert.equal(cameraClicks, 1);
  assert.equal(nativeActions.attrs.get('data-fieldflow-react-media-native'), '1');
});

test('React operational modules compile a shared native-proxy contract for all priority surfaces', () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('fieldflow-react-operational-modules-v1'));
  assert.ok(asset.includes('operational-module-controls'));
  assert.ok(asset.includes('data-fieldflow-react-operational-native'));
  assert.ok(asset.includes('fieldFlowOperationalModuleWrite'));
  assert.ok(asset.includes('fieldFlowOperationalModuleClickAction'));
  assert.ok(asset.includes('fieldFlowOperationalModuleClientSearch'));
  assert.ok(!fs.readFileSync(path.join(frontendDir, 'package.json'), 'utf8').includes('"react"'));
  assert.ok(!fs.readFileSync(path.join(frontendDir, 'package.json'), 'utf8').includes('"react-dom"'));
});

test('React operational module mounts preserve the seven canonical PHP module owners', () => {
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'module-controls.ts'), 'utf8');
  assert.ok(source.includes("dispatchEvent(new Event(eventName, { bubbles: true }))"));
  assert.ok(source.includes("if (props.module === 'stocks')"));
  assert.ok(source.includes("if (props.module === 'shopping')"));
  assert.ok(source.includes("if (props.module === 'planner')"));
  assert.ok(source.includes("if (props.module === 'expenses')"));
  assert.ok(source.includes("if (props.module === 'messages')"));
  const renderers = {
    briefings: 'src/Front/BriefingsShortcodeRenderer.php',
    implementations: 'src/Front/ImplementationsShortcodeRenderer.php',
    stocks: 'src/Front/StocksShortcodeRenderer.php',
    shopping: 'src/Front/ShoppingShortcodeRenderer.php',
    planner: 'src/Front/SelfRoutePlannerShortcodeRenderer.php',
    expenses: 'src/Front/ExpensesShortcodeRenderer.php',
    messages: 'src/Front/TeamInboxShortcodeRenderer.php',
    academy: 'src/Front/PerformanceShortcodeRenderer.php',
  };
  for (const [module, relative] of Object.entries(renderers)) {
    const php = fs.readFileSync(path.join(pluginDir, relative), 'utf8');
    assert.ok(php.includes(`operationalModuleMount`));
    assert.ok(php.includes(`'${module}'`));
  }
});


test('Operational action hardening keeps Implementações chips stable and reconciles combined visibility', () => {
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'module-controls.ts'), 'utf8');
  const css = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-react-front.css'), 'utf8');
  assert.ok(source.includes('fieldflow-react-operational-actions-hardening-v1'));
  assert.ok(source.includes('fieldFlowOperationalModuleReconcileImplementationVisibility'));
  assert.ok(source.includes("queueMicrotask(() => fieldFlowOperationalModuleReconcileImplementationVisibility(moduleRoot))"));
  assert.ok(source.includes("'data-action-contract': FIELD_FLOW_REACT_OPERATIONAL_ACTIONS_CONTRACT"));
  assert.ok(css.includes('.ff-react-operational-module-implementations .ff-react-module-chips'));
  assert.ok(css.includes('.ff-operational-surface-implementations .ffi-front-filters'));
  assert.ok(css.includes('flex-wrap:nowrap!important'));
  assert.ok(css.includes('overflow-x:auto!important'));
});

test('Operational React inputs do not double-dispatch the same visible edit', () => {
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'module-controls.ts'), 'utf8');
  const inputRenderStart = source.indexOf("element.createElement('input', {");
  assert.ok(inputRenderStart >= 0);
  const inputRender = source.slice(inputRenderStart, inputRenderStart + 700);
  assert.ok(inputRender.includes('onInput:'));
  assert.ok(!inputRender.includes('onChange:'));
});

test('React form completion compiles category navigation, pending guidance and conditional suggestion awareness', () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  assert.ok(asset.includes('fieldflow-react-form-completion-v1'));
  assert.ok(asset.includes('fieldFlowSpecialGoToIncomplete'));
  assert.ok(asset.includes('fieldFlowSpecialOpenCategory'));
  assert.ok(asset.includes('data-fieldflow-react-form-completion'));
  assert.ok(asset.includes('Inativo pela condição anterior'));
  assert.ok(asset.includes('Com evidência'));
  assert.ok(asset.includes('Automáticas'));
  assert.ok(asset.includes('Objetivo atingido'));
  assert.ok(asset.includes('Zero automático'));
});

test('React form completion stays wired as UX over canonical special-field owners', () => {
  const foundation = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'ReactFrontFoundation.php'), 'utf8');
  const renderer = fs.readFileSync(path.join(pluginDir, 'src', 'Forms', 'FormRenderer.php'), 'utf8');
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'special-fields.ts'), 'utf8');
  assert.ok(foundation.includes("FORM_COMPLETION_CONTRACT = 'fieldflow-react-form-completion-v1'"));
  assert.ok(renderer.includes('ReactFrontFoundation::FORM_COMPLETION_CONTRACT'));
  assert.ok(source.includes("field.closest('[data-rp-question-wrap]')"));
  assert.ok(source.includes("[data-fronts-input]"));
  assert.ok(source.includes("[data-order-qty]"));
  assert.ok(source.includes("[data-rp-evidence-category]"));
  assert.ok(source.includes('fieldFlowSpecialMediaCount'));
  assert.ok(source.includes("['share_space_matrix','categorized_image_upload'].includes(props.fieldType)"), 'no false product-matrix pending action');
  assert.ok(source.includes('Captura, recuperação e envio continuam no runtime FieldFlow certificado.'));
});


test('Front transition stability hides React fallbacks before mount and initializes the app panel once', () => {
  const app = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'AppShortcodeRenderer.php'), 'utf8');
  const form = fs.readFileSync(path.join(pluginDir, 'src', 'Forms', 'FormRenderer.php'), 'utf8');
  const route = fs.readFileSync(path.join(pluginDir, 'src', 'Shortcodes.php'), 'utf8');
  const css = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-design-system.css'), 'utf8');
  const shellSource = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'operational-shell.ts'), 'utf8');
  assert.ok(app.includes('data-fieldflow-react-front-fallback-pending="1"'));
  assert.ok(form.includes('data-fieldflow-react-front-fallback-pending="1"'));
  assert.ok(route.includes('data-fieldflow-react-front-fallback-pending="1"'));
  assert.ok(css.includes('[data-fieldflow-react-front-fallback-pending="1"]{display:none!important}'));
  assert.ok(shellSource.includes("removeAttribute('data-fieldflow-react-front-fallback-pending')"));
  assert.ok(app.includes('let initialPanelApplied=false'));
  assert.ok(app.includes('openPanel(initialPanel,{remember:false,scroll:false})'));
  assert.equal((app.match(/setTimeout\(applyInitial/g) || []).length, 0);
});

test('Front transition stability delays lazy visuals and emits one canonical loaded event', () => {
  const runtime = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-ui-runtime.js'), 'utf8');
  const loader = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'PanelLoader.php'), 'utf8');
  assert.ok(runtime.includes('reactFallbackTimers'));
  assert.ok(runtime.includes('},1200)'));
  assert.ok(runtime.includes('const visualTimer=window.setTimeout'));
  assert.ok(runtime.includes('},180)'));
  assert.ok(loader.includes('class="ff-skeleton" aria-hidden="true" hidden'));
  assert.ok(loader.includes('class="ff-lazy-label" hidden'));
  assert.equal((runtime.match(/placeholder\.dispatchEvent\(new CustomEvent\('fieldflow:lazy-panel-loaded'/g) || []).length, 1);
  assert.equal((runtime.match(/document\.dispatchEvent\(new CustomEvent\('fieldflow:lazy-panel-loaded'/g) || []).length, 0);
});


test('Operational Modernization Wave uses event-driven React ownership for priority modules', () => {
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'module-controls.ts'), 'utf8');
  const foundation = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'ReactFrontFoundation.php'), 'utf8');
  assert.ok(source.includes('fieldflow-operational-modernization-wave-v1'));
  assert.ok(source.includes('react-event-shell-9o'));
  assert.ok(source.includes("['briefings', 'stocks', 'shopping', 'messages'].includes(props.module)"));
  assert.ok(source.includes('if (!modernized)'));
  assert.ok(source.includes('fieldflow:operational-module-refresh'));
  assert.ok(foundation.includes("OPERATIONAL_MODERNIZATION_CONTRACT = 'fieldflow-operational-modernization-wave-v1'"));
});

test('Operational Modernization Wave 2 modernizes Academy and Expenses without observers', () => {
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'module-controls.ts'), 'utf8');
  const foundation = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'ReactFrontFoundation.php'), 'utf8');
  const academy = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'PerformanceShortcodeRenderer.php'), 'utf8');
  const module = fs.readFileSync(path.join(pluginDir, 'src', 'Performance', 'Module.php'), 'utf8');
  const expenses = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'ExpensesShortcodeRenderer.php'), 'utf8');
  const analytics = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-premium-analytics.js'), 'utf8');
  const shortcodes = fs.readFileSync(path.join(pluginDir, 'src', 'Shortcodes.php'), 'utf8');
  assert.ok(source.includes('fieldflow-operational-modernization-wave2-v1'));
  assert.ok(source.includes('react-event-shell-9q'));
  assert.ok(source.includes("['academy', 'expenses'].includes(props.module)"));
  assert.ok(foundation.includes("OPERATIONAL_MODERNIZATION_WAVE2_CONTRACT = 'fieldflow-operational-modernization-wave2-v1'"));
  assert.ok(academy.includes("operationalModuleMount($id, 'academy'"));
  assert.ok(module.includes("ACADEMY_FRONT_BATCH_CONTRACT = 'fieldflow-academy-front-batch-v2'"));
  assert.ok(module.includes('academy_front_state'));
  assert.ok(expenses.includes("module:'expenses'"));
  assert.equal(analytics.includes('new MutationObserver'), false);
  assert.ok(analytics.includes('fieldflow:analytics-rendered'));
  assert.ok(shortcodes.includes("fieldflow:analytics-rendered"));
});

test('Operational Modernization Wave closes client mounts, scoped caches and duplicate panel listeners', () => {
  const briefings = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'BriefingsShortcodeRenderer.php'), 'utf8');
  const stocks = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'StocksShortcodeRenderer.php'), 'utf8');
  const shopping = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'ShoppingShortcodeRenderer.php'), 'utf8');
  assert.ok((briefings.match(/operationalModuleMount\(\$id, 'briefings'/g) || []).length >= 2);
  assert.ok((stocks.match(/operationalModuleMount\(\$id, 'stocks'/g) || []).length >= 2);
  assert.ok(briefings.includes('fieldflow_briefings_v2_'));
  assert.ok(shopping.includes('fieldflow:shopping:draft:v2:'));
  assert.ok(shopping.includes('legacyLocalKey'));
  assert.equal((shopping.match(/root\.addEventListener\('routespro:panel-open'/g) || []).length, 0);
  assert.equal((stocks.match(/root\.addEventListener\('routespro:panel-open'/g) || []).length, 0);
});

test('Locale switcher carries live client scope and branded interaction contract', () => {
  const asset = fs.readFileSync(assetPath, 'utf8');
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'locale-switcher.ts'), 'utf8');
  const runtime = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-i18n.js'), 'utf8');
  const css = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-i18n.css'), 'utf8');
  assert.ok(asset.includes('fieldflow-locale-brand-scope-continuity-v1'));
  assert.ok(source.includes('fieldFlowLocaleBrandStyle'));
  assert.ok(source.includes("document.addEventListener('routespro:scope-change'"));
  assert.ok(source.includes("window.addEventListener('fieldflow:i18n-context-changed'"));
  assert.ok(runtime.includes('setClientContext'));
  assert.ok(runtime.includes("document.addEventListener('routespro:scope-change'"));
  assert.ok(runtime.includes('applyLegacyBrand'));
  assert.ok(css.includes('.ff-language-trigger:hover'));
  assert.ok(css.includes('var(--ff-lang-primary)'));
  assert.ok(css.includes('var(--ff-lang-accent)'));
});

test('Locale switcher mounts hooks inside React and survives dual WebView shells', () => {
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'locale-switcher.ts'), 'utf8');
  const runtime = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-i18n.js'), 'utf8');
  const app = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'AppShortcodeRenderer.php'), 'utf8');
  const client = fs.readFileSync(path.join(pluginDir, 'src', 'Front', 'ClientPortalShortcodeRenderer.php'), 'utf8');
  const language = fs.readFileSync(path.join(pluginDir, 'src', 'Localization', 'LanguageService.php'), 'utf8');
  assert.ok(source.includes('element.createElement(fieldFlowReactLocaleSwitcherComponent, { root })'));
  assert.equal(source.includes('const node = fieldFlowReactLocaleSwitcherComponent();'), false);
  assert.ok(source.includes('Invalid hook call'));
  assert.ok(source.includes('fieldflow-app-shell-locale-runtime-v1'));
  assert.ok(source.includes('fieldflow-browserlike-auto-translation-v1'));
  assert.ok(source.includes('runtime.ensureSwitcher'));
  assert.ok(source.includes('runtime.prepareTranslator'));
  assert.ok(source.includes('fieldflow:portal-ready'));
  assert.ok(runtime.includes('window.Translator'));
  assert.ok(runtime.includes('serverTranslateBatch'));
  assert.ok(runtime.includes('preserveElement'));
  assert.ok(runtime.includes("locale==='zh-CN'"));
  assert.ok(runtime.includes('ensureSwitcher'));
  assert.ok(runtime.includes('data-fieldflow-locale-host'));
  assert.ok(app.includes('data-fieldflow-locale-host="1"'));
  assert.ok(app.includes('data-fieldflow-locale-shell="operative"'));
  assert.ok(client.includes('data-fieldflow-locale-host="1"'));
  assert.ok(client.includes('data-fieldflow-locale-shell="client"'));
  assert.ok(language.includes("'zh-CN'"));
});

test('Adaptive locale dock hides on scroll and leaves a branded side tab at rest', () => {
  const source = fs.readFileSync(path.join(frontendDir, 'src', 'react-front', 'locale-switcher.ts'), 'utf8');
  const runtime = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-i18n.js'), 'utf8');
  const css = fs.readFileSync(path.join(pluginDir, 'assets', 'fieldflow-i18n.css'), 'utf8');
  assert.ok(source.includes('fieldflow-adaptive-locale-dock-v1'));
  assert.ok(source.includes("window.addEventListener('scroll'"));
  assert.ok(source.includes("document.addEventListener('scroll'"));
  assert.ok(source.includes("switcherClasses.push('is-docked')"));
  assert.ok(source.includes("switcherClasses.push('is-scrolling')"));
  assert.ok(source.includes('setDocked(false)'));
  assert.ok(source.includes('setOpen(true)'));
  assert.ok(runtime.includes('fieldflow-adaptive-locale-dock-v1'));
  assert.ok(runtime.includes('wireLegacyAdaptiveDock'));
  assert.ok(runtime.includes("wrap.classList.add('is-docked','is-scrolling')"));
  assert.ok(css.includes('.ff-language-switcher.is-docked:not(.is-open):not(.is-scrolling)'));
  assert.ok(css.includes('.ff-language-switcher.is-scrolling:not(.is-open)'));
  assert.ok(css.includes('safe-area-inset-right'));
  assert.ok(css.includes('@media(prefers-reduced-motion:reduce)'));
});
