/* FieldFlow 2.2.639 | fieldflow-operative-dock-bootstrap-recovery-9de-v1
 * Operative-only bootstrap. The 9DD production evidence showed a rendered
 * .rp-tabs with no scroll owner. This runtime does not rely on the 9D9/9DC
 * enqueue switches or on a scrollable page existing at first render.
 * No network, persistent storage, form mutations or client-portal selectors.
 */
(function () {
  'use strict';
  const CONTRACT = 'fieldflow-operative-dock-bootstrap-recovery-9de-v1';
  const MOTION_CONTRACT = 'fieldflow-operative-dock-calm-motion-9df-v1';
  const HIDE_DISTANCE = 54;
  const SHOW_DISTANCE = 25;
  const TOUCH_SCROLL_GRACE_MS = 480;
  const HOLD_VISIBLE_MS = 900;
  const HOLD_HIDDEN_MS = 430;
  const ROOT = '#routespro-app[data-fieldflow-portal="operative"]';
  const PHONE = '(max-width:760px)';
  const EXCLUDE = 'input,textarea,select,[contenteditable="true"],[role="combobox"],[role="slider"],canvas,video,.rp-tabs,.rp-tabbar,[data-ff-horizontal-pan="1"]';
  const win = window;
  const doc = document;
  if (win.FieldFlowOperativeDock9DE && win.FieldFlowOperativeDock9DE.contract === CONTRACT) return;
  const isElement = value => !!value && value.nodeType === 1;
  const elementFrom = value => isElement(value) ? value : (isElement(value && value.parentElement) ? value.parentElement : null);
  const mobile = () => !win.matchMedia || win.matchMedia(PHONE).matches;
  const keyboard = () => !!doc.body?.classList.contains('ff-keyboard-open') || !!doc.activeElement?.matches?.('input,textarea,select,[contenteditable="true"],[role="combobox"]');
  const frame = fn => typeof win.requestAnimationFrame === 'function' ? win.requestAnimationFrame(fn) : win.setTimeout(fn, 16);
  const pageY = () => Math.max(0, Number(win.scrollY) || 0, Number(doc.scrollingElement?.scrollTop) || 0, Number(doc.documentElement?.scrollTop) || 0, Number(doc.body?.scrollTop) || 0);
  let root = null, dock = null, visible = true, bootAttempts = 0, direction = 0, distance = 0;
  let touch = null, pointer = null, lastTouchAt = 0, reconcileScheduled = false;
  let observed = false, watcher = null, lastScroll = new WeakMap();
  let movementCount = 0, lastInput = 'boot';
  let lastTransitionAt = -Infinity, lastTransitionVisible = true, lastTouchMoveAt = -Infinity;
  let suppressedScroll = 0;
  const lookup = () => {
    const roots = Array.from(doc.querySelectorAll(ROOT));
    return roots.find(el => el.isConnected && el.querySelector('.rp-tabs .rp-tabbar') && el.getBoundingClientRect().width > 1)
      || roots.find(el => el.isConnected && el.querySelector('.rp-tabs .rp-tabbar')) || null;
  };
  const currentDock = el => Array.from(el?.querySelectorAll('.rp-tabs') || []).find(bar => bar.querySelector('.rp-tabbar')) || null;
  function apply(source) {
    if (!root || !root.isConnected) return;
    const bar = currentDock(root);
    if (!bar) return;
    if (bar !== dock && dock) clearInline(dock);
    dock = bar;
    if (!mobile()) visible = true;
    root.dataset.ffvDockControllerActive = 'operative-9de';
    root.dataset.ffvDockAuthority = '9d8'; // prevents the unchanged 9D5 scroll owner from competing
    root.dataset.ffvDockState = visible ? 'visible' : 'hidden';
    root.dataset.ffvDockSource = source || lastInput;
    root.dataset.ffvDockBootstrap9de = CONTRACT;
    root.classList.add('ffv-operative-dock-9de');
    root.classList.toggle('ffv-operative-dock-collapsed-9de', !visible);
    bar.classList.toggle('ffv-dock-hidden', !visible);
    // Inline important fallback survives an Elementor stylesheet that misses
    // the new CSS, and is removed entirely when the bar becomes visible.
    if (visible) clearInline(bar);
    else {
      bar.style.setProperty('transform', 'translate3d(0,14px,0)', 'important');
      bar.style.setProperty('opacity', '0', 'important');
      bar.style.setProperty('pointer-events', 'none', 'important');
    }
    const barInner = bar.querySelector('.rp-tabbar');
    if (barInner) {
      const height = Math.max(55, Math.ceil(barInner.getBoundingClientRect().height || 0));
      root.style.setProperty('--ffv-dock-height', height + 'px');
    }
  }
  function clearInline(el) {
    if (!el?.style) return;
    ['transform', 'opacity', 'pointer-events'].forEach(prop => el.style.removeProperty(prop));
  }
  function setVisible(value, source, force = false) {
    const next = !!value || !mobile();
    if (next === visible) return;
    const now = Date.now();
    const hold = lastTransitionVisible ? HOLD_VISIBLE_MS : HOLD_HIDDEN_MS;
    if (!force && now - lastTransitionAt < hold) {
      // A brief counter-motion (momentum or overscroll) must not toggle the bar.
      distance = 0;
      return;
    }
    visible = next;
    movementCount++;
    lastTransitionAt = now;
    lastTransitionVisible = next;
    lastInput = source;
    distance = 0;
    apply(source);
  }
  function move(delta, source) {
    if (!root || !mobile() || keyboard() || !Number.isFinite(delta) || Math.abs(delta) < 1.5) return;
    const nextDirection = delta > 0 ? 1 : -1;
    if (nextDirection !== direction) { direction = nextDirection; distance = 0; }
    // Ignore reversed movement while the last fade is still settling.
    const hold = lastTransitionVisible ? HOLD_VISIBLE_MS : HOLD_HIDDEN_MS;
    if (Date.now() - lastTransitionAt < hold) { distance = 0; return; }
    // Once at the intended state, more motion in the same direction is irrelevant.
    if ((nextDirection > 0 && !visible) || (nextDirection < 0 && visible)) { distance = 0; return; }
    distance = Math.min(240, distance + Math.abs(delta));
    if (nextDirection > 0 && distance >= HIDE_DISTANCE) setVisible(false, source);
    else if (nextDirection < 0 && distance >= SHOW_DISTANCE) setVisible(true, source);
  }
  function relevant(node) {
    const el = elementFrom(node);
    return !!(root && el && (root.contains(el) || el.contains(root) || el === doc.body || el === doc.documentElement));
  }
  function onScroll(event) {
    const target = event.target;
    if (!root || !relevant(target)) return;
    const host = target === doc || target === win || target === doc.documentElement || target === doc.body ? win : target;
    const current = host === win ? pageY() : Math.max(0, Number(host.scrollTop) || 0);
    const previous = lastScroll.has(host) ? lastScroll.get(host) : 0;
    lastScroll.set(host, current);
    if (Math.abs(current - previous) < 1) return;
    if (Date.now() - lastTouchMoveAt < TOUCH_SCROLL_GRACE_MS) { suppressedScroll++; return; }
    root.dataset.ffvDockScrollHost = host === win ? 'page' : String(host.id || host.className || host.tagName || 'container').slice(0, 60);
    move(current - previous, 'scroll');
  }
  function accepts(target) {
    const el = elementFrom(target);
    return !!(root && el && root.contains(el) && !el.closest(EXCLUDE));
  }
  function touchPoint(event, id) {
    const list = Array.from(event.touches || []);
    return list.find(p => p.identifier === id) || null;
  }
  function onTouchStart(event) {
    touch = null;
    if (!mobile() || keyboard() || event.touches?.length !== 1 || !accepts(event.target)) return;
    const p = event.touches[0];
    touch = { id: p.identifier, x: p.clientX, y: p.clientY, startX: p.clientX, startY: p.clientY, axis: null };
    lastTouchAt = Date.now();
  }
  function onTouchMove(event) {
    if (!touch || !mobile() || keyboard()) return;
    const p = touchPoint(event, touch.id);
    if (!p) { touch = null; return; }
    const totalX = p.clientX - touch.startX, totalY = p.clientY - touch.startY;
    if (!touch.axis && Math.max(Math.abs(totalX), Math.abs(totalY)) > 8)
      touch.axis = Math.abs(totalY) > Math.abs(totalX) * 1.3 ? 'vertical' : 'horizontal';
    const dy = touch.y - p.clientY;
    touch.x = p.clientX; touch.y = p.clientY;
    lastTouchAt = Date.now();
    if (touch.axis === 'vertical') { lastTouchMoveAt = Date.now(); move(dy, 'touch'); }
  }
  function onPointerDown(event) {
    pointer = null;
    if (event.pointerType !== 'touch' || !mobile() || keyboard() || !accepts(event.target)) return;
    pointer = { id: event.pointerId, x: event.clientX, y: event.clientY, startX: event.clientX, startY: event.clientY, axis: null };
  }
  function onPointerMove(event) {
    if (!pointer || event.pointerId !== pointer.id || !mobile() || keyboard() || Date.now() - lastTouchAt < 250) return;
    const dx = event.clientX - pointer.startX, dy = event.clientY - pointer.startY;
    if (!pointer.axis && Math.max(Math.abs(dx), Math.abs(dy)) > 8)
      pointer.axis = Math.abs(dy) > Math.abs(dx) * 1.3 ? 'vertical' : 'horizontal';
    const delta = pointer.y - event.clientY;
    pointer.x = event.clientX; pointer.y = event.clientY;
    if (pointer.axis === 'vertical') move(delta, 'pointer');
  }
  function onWheel(event) {
    if (root && Date.now() - lastTouchMoveAt >= TOUCH_SCROLL_GRACE_MS && accepts(event.target) && Math.abs(event.deltaY) > Math.abs(event.deltaX) * 1.2)
      move(event.deltaY, 'wheel');
  }
  function onClick(event) {
    const el = elementFrom(event.target);
    if (!root || !el || !root.contains(el)) return;
    if (el.closest('.rp-tabbar button,.ff-react-front-operational-nav button,[data-panel],.ff-ope-command'))
      setVisible(true, 'navigation', true);
  }
  function scheduleReconcile() {
    if (reconcileScheduled) return;
    reconcileScheduled = true;
    frame(() => { reconcileScheduled = false; reconcile(); });
  }
  function reconcile() {
    bootAttempts++;
    const next = lookup();
    if (!next) { root = null; dock = null; return; }
    if (next !== root) {
      root = next; dock = null; visible = true; direction = 0; distance = 0; lastTransitionAt = -Infinity; lastScroll = new WeakMap();
      lastScroll.set(win, pageY());
    }
    apply(lastInput);
  }
  function listen(target, type, fn, options) { target.addEventListener(type, fn, options); }
  function start() {
    if (observed) return;
    observed = true;
    listen(win, 'scroll', onScroll, { passive: true });
    listen(doc, 'scroll', onScroll, true);
    listen(doc, 'touchstart', onTouchStart, { capture: true, passive: true });
    listen(doc, 'touchmove', onTouchMove, { capture: true, passive: true });
    listen(doc, 'touchend', () => { touch = null; }, { capture: true, passive: true });
    listen(doc, 'touchcancel', () => { touch = null; }, { capture: true, passive: true });
    listen(doc, 'pointerdown', onPointerDown, true);
    listen(doc, 'pointermove', onPointerMove, true);
    listen(doc, 'pointerup', () => { pointer = null; }, true);
    listen(doc, 'pointercancel', () => { pointer = null; }, true);
    listen(doc, 'wheel', onWheel, { capture: true, passive: true });
    listen(doc, 'click', onClick, true);
    listen(win, 'resize', scheduleReconcile, { passive: true });
    if (win.visualViewport) listen(win.visualViewport, 'resize', scheduleReconcile, { passive: true });
    listen(win, 'pageshow', scheduleReconcile);
    listen(doc, 'visibilitychange', () => { if (!doc.hidden) scheduleReconcile(); });
    listen(doc, 'fieldflow:responsive-ready', scheduleReconcile);
    if (typeof MutationObserver === 'function') {
      watcher = new MutationObserver(scheduleReconcile);
      watcher.observe(doc.documentElement || doc, { childList: true, subtree: true });
    }
    reconcile();
  }
  win.FieldFlowOperativeDock9DE = {
    contract: CONTRACT, motionContract: MOTION_CONTRACT,
    snapshot: () => ({ motionContract: MOTION_CONTRACT, emitted: true, bootAttempts, mounted: !!root, connected: !!root?.isConnected,
      controller: root?.dataset.ffvDockControllerActive || 'absent', bar: !!dock,
      state: root?.dataset.ffvDockState || 'absent', source: root?.dataset.ffvDockSource || 'absent',
      movementCount, suppressedScroll, lastTransitionAt: Number.isFinite(lastTransitionAt) ? lastTransitionAt : null,
      motion: { hideDistance: HIDE_DISTANCE, showDistance: SHOW_DISTANCE, visibleHoldMs: HOLD_VISIBLE_MS, hiddenHoldMs: HOLD_HIDDEN_MS },
      observer: !!watcher }),
    reconnect: scheduleReconcile,
    hide: () => setVisible(false, 'manual'),
    show: () => setVisible(true, 'manual')
  };
  start();
  if (doc.readyState === 'loading') listen(doc, 'DOMContentLoaded', scheduleReconcile, { once: true });
})();
