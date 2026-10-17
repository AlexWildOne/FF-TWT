/* FieldFlow Premium Shell v2 — 2.2.517
 *
 * Rail lateral categorizada para o Portal Cliente, em desktop largo (>=1080px).
 * Não regista NENHUMA lógica de troca de painel nova: lê os botões que a
 * própria ClientPortalShortcodeRenderer já imprime em .rp-tabbar, e cada
 * clique na rail apenas dispara .click() no botão original correspondente.
 * O switchPanel(), o carregamento de dados e os listeners existentes nunca
 * são tocados. Falha sempre em silêncio: se algo correr mal, a tabbar
 * original continua a funcionar exatamente como antes.
 */
(function () {
  'use strict';

  var SERVER_SHELL_CONTRACT = 'fieldflow-client-portal-shell-stability-v1';

  var GROUPS = [
    { label: 'Visão geral', panels: ['overview'] },
    { label: 'Operação', panels: ['routes', 'campaign-pdvs', 'stocks', 'implementations', 'briefings'] },
    { label: 'Comercial', panels: ['commercial', 'shopping-results'] },
    { label: 'Relatórios', panels: ['reports', 'analytics', 'performance'] },
    { label: 'Equipa', panels: ['team'] },
  ];

  var ICONS = {
    'overview': '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'routes': '<path d="M9 20l-5-2V6l5 2m0 12l6-2m-6 2V8m6 10l5 2V8l-5-2m0 14V6m0 2L9 6"/>',
    'campaign-pdvs': '<path d="M12 21s7-6.5 7-12a7 7 0 10-14 0c0 5.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.3"/>',
    'stocks': '<path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
    'implementations': '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 10h16M10 4v16"/>',
    'briefings': '<path d="M4 19.5A2.5 2.5 0 016.5 17H20M4 19.5A2.5 2.5 0 006.5 22H20V2H6.5A2.5 2.5 0 004 4.5v15z"/>',
    'commercial': '<path d="M3 3v18h18M7 15l4-6 3 3 5-8"/>',
    'shopping-results': '<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M2 3h2l2.4 12.2a2 2 0 002 1.8h8.6a2 2 0 002-1.6L21 7H6"/>',
    'reports': '<path d="M14 3v5h5M6 3h8l6 6v12H6z"/>',
    'analytics': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
    'performance': '<path d="M3 20h18M6 20V10m6 10V4m6 16v-7"/>',
    'team': '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20a6.5 6.5 0 0113 0M16 8.5a3 3 0 110 6M21.5 20a5.5 5.5 0 00-6-5.4"/>',
  };
  var FALLBACK_ICON = '<circle cx="12" cy="12" r="4"/>';

  function icon(key) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[key] || FALLBACK_ICON) + '</svg>';
  }

  function buildRail(root, tabbar, existingRail) {
    var buttons = Array.prototype.slice.call(tabbar.querySelectorAll('button[data-panel]'));
    if (buttons.length < 2) return null;

    var byPanel = {};
    buttons.forEach(function (btn) { byPanel[btn.getAttribute('data-panel')] = btn; });

    var rail = existingRail || document.createElement('nav');
    rail.className = 'ff2-rail';
    rail.setAttribute('aria-label', 'Navegação do portal');
    rail.setAttribute('data-ff2-contract', SERVER_SHELL_CONTRACT);
    rail.innerHTML = '';

    var seen = {};
    GROUPS.forEach(function (group) {
      var itemsHtml = '';
      group.panels.forEach(function (key) {
        var btn = byPanel[key];
        if (!btn) return;
        seen[key] = true;
        var active = btn.classList.contains('active') ? ' active' : '';
        itemsHtml += '<button type="button" class="ff2-rail-item' + active + '" data-ff2-panel="' + key + '">' + icon(key) + '<span>' + btn.textContent.trim() + '</span></button>';
      });
      if (itemsHtml) {
        rail.insertAdjacentHTML('beforeend', '<div class="ff2-rail-group"><div class="ff2-rail-label">' + group.label + '</div>' + itemsHtml + '</div>');
      }
    });

    // Qualquer painel futuro que não esteja no mapa de categorias ainda aparece,
    // agrupado à parte, para nunca desaparecer silenciosamente da navegação.
    var leftoverHtml = '';
    buttons.forEach(function (btn) {
      var key = btn.getAttribute('data-panel');
      if (seen[key]) return;
      var active = btn.classList.contains('active') ? ' active' : '';
      leftoverHtml += '<button type="button" class="ff2-rail-item' + active + '" data-ff2-panel="' + key + '">' + icon(key) + '<span>' + btn.textContent.trim() + '</span></button>';
    });
    if (leftoverHtml) {
      rail.insertAdjacentHTML('beforeend', '<div class="ff2-rail-group"><div class="ff2-rail-label">Mais</div>' + leftoverHtml + '</div>');
    }

    rail.addEventListener('click', function (ev) {
      var item = ev.target.closest('[data-ff2-panel]');
      if (!item) return;
      var key = item.getAttribute('data-ff2-panel');
      var original = byPanel[key];
      if (!original) return;
      rail.querySelectorAll('.ff2-rail-item').forEach(function (el) {
        var selected = el === item;
        el.classList.toggle('active', selected);
        el.setAttribute('aria-current', selected ? 'page' : 'false');
      });
      original.click();
    });

    root.addEventListener('routespro:panel-open', function (ev) {
      var key = ev && ev.detail ? ev.detail.panel : '';
      if (!key) return;
      rail.querySelectorAll('[data-ff2-panel]').forEach(function (el) {
        var selected = el.getAttribute('data-ff2-panel') === key;
        el.classList.toggle('active', selected);
        el.setAttribute('aria-current', selected ? 'page' : 'false');
      });
    });

    return rail;
  }

  function init(root) {
    if (!root || root.dataset.ff2RailInit) return;
    var shell = root.querySelector('.rp-shell');
    var tabbar = root.querySelector('.rp-tabbar');
    if (!shell || !tabbar) return;

    var serverLayout = root.querySelector(':scope > [data-ff2-layout="server"]');
    var serverRail = serverLayout ? serverLayout.querySelector(':scope > [data-ff2-rail="server"]') : null;
    var rail = buildRail(root, tabbar, serverRail);
    if (!rail) return;

    if (!serverLayout) {
      var layout = document.createElement('div');
      layout.className = 'ff2-layout';
      layout.setAttribute('data-ff2-layout', 'fallback');
      shell.parentNode.insertBefore(layout, shell);
      layout.appendChild(rail);
      layout.appendChild(shell);
    }

    root.dataset.ff2RailInit = '1';
    root.dataset.ff2ShellContract = SERVER_SHELL_CONTRACT;
    root.classList.add('ff2-has-rail');
    rail.setAttribute('data-ff2-rail-ready', '1');
  }

  function boot() {
    try {
      document.querySelectorAll('#routespro-client-portal').forEach(init);
    } catch (e) {
      /* Camada aditiva: qualquer erro aqui nunca deve afetar o portal real. */
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
