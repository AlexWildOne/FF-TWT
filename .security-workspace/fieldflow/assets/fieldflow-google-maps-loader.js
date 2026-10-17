(function (window, document) {
  'use strict';

  if (window.FieldFlowGoogleMaps && typeof window.FieldFlowGoogleMaps.load === 'function') return;

  const state = {
    status: 'idle',
    promise: null,
    error: '',
    requestedLibraries: new Set(),
    readyAt: 0,
    attempts: 0,
  };

  const normalizeLibraries = (value) => {
    const list = Array.isArray(value) ? value : [value || 'places'];
    return Array.from(new Set(list.map((item) => String(item || '').trim()).filter(Boolean)));
  };

  const hasBase = () => !!(window.google && window.google.maps);

  const libraryReady = (library) => {
    if (!hasBase()) return false;
    if (library === 'maps') return typeof window.google.maps.Map === 'function';
    if (library === 'places') {
      return !!(window.google.maps.places && typeof window.google.maps.places.Autocomplete === 'function');
    }
    if (library === 'geometry') return !!window.google.maps.geometry;
    if (library === 'drawing') return !!window.google.maps.drawing;
    if (library === 'visualization') return !!window.google.maps.visualization;
    if (library === 'marker') return !!window.google.maps.marker;
    return true;
  };

  const allReady = (libraries) => libraries.every(libraryReady);

  const dispatch = (name, detail) => {
    try {
      window.dispatchEvent(new CustomEvent(name, { detail }));
      document.dispatchEvent(new CustomEvent(name, { detail }));
    } catch (_) {}
  };

  const addAutocompleteLayerStyle = () => {
    if (document.getElementById('fieldflow-google-autocomplete-style')) return;
    const style = document.createElement('style');
    style.id = 'fieldflow-google-autocomplete-style';
    style.textContent = 'body .pac-container{z-index:2147483647!important;}';
    document.head.appendChild(style);
  };

  const importLibraries = async (libraries) => {
    if (!hasBase()) return false;
    if (allReady(libraries)) return true;
    if (typeof window.google.maps.importLibrary !== 'function') return allReady(libraries);

    for (const library of libraries) {
      if (libraryReady(library)) continue;
      try {
        await window.google.maps.importLibrary(library);
      } catch (_) {}
    }
    return allReady(libraries);
  };

  const findExistingScript = () => {
    const scripts = Array.from(document.scripts || []);
    return scripts.find((script) => {
      const src = String(script.src || '');
      return script.dataset.fieldflowGoogleMaps === '1' ||
        script.dataset.routesproGoogle === '1' ||
        /\/maps\/api\/js(?:\?|$)/i.test(src) && /maps\.googleapis\.com|maps\.google\.com/i.test(src);
    }) || null;
  };

  const waitForLibraries = (libraries, timeoutMs) => new Promise((resolve) => {
    const startedAt = Date.now();
    let settled = false;

    const finish = (value) => {
      if (settled) return;
      settled = true;
      resolve(!!value);
    };

    const tick = async () => {
      if (settled) return;
      if (await importLibraries(libraries)) return finish(true);
      if (Date.now() - startedAt >= timeoutMs) return finish(false);
      window.setTimeout(tick, 120);
    };

    tick();
  });

  const injectScript = (key, libraries, options) => new Promise((resolve) => {
    const callbackName = '__fieldflowGoogleMapsReady_' + Math.random().toString(36).slice(2);
    let settled = false;
    const cleanup = () => {
      try { delete window[callbackName]; } catch (_) { window[callbackName] = undefined; }
    };
    const finish = (value) => {
      if (settled) return;
      settled = true;
      cleanup();
      resolve(!!value);
    };

    window[callbackName] = () => finish(true);

    const params = new URLSearchParams();
    params.set('key', key);
    params.set('loading', 'async');
    params.set('callback', callbackName);
    params.set('v', String(options.version || 'weekly'));
    params.set('language', String(options.language || 'pt-PT'));
    params.set('region', String(options.region || 'PT'));
    if (libraries.length) params.set('libraries', libraries.join(','));

    const script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?' + params.toString();
    script.async = true;
    script.defer = true;
    script.dataset.fieldflowGoogleMaps = '1';
    script.dataset.routesproGoogle = '1';
    script.addEventListener('error', () => finish(false), { once: true });
    script.addEventListener('load', () => {
      window.setTimeout(() => {
        if (hasBase()) finish(true);
      }, 0);
    }, { once: true });
    document.head.appendChild(script);
  });

  const startLoad = async (options, libraries) => {
    state.attempts += 1;
    state.status = 'loading';
    state.error = '';

    if (await importLibraries(libraries)) return true;

    const key = String(options.key || '').trim();
    if (!key) {
      state.error = 'missing_api_key';
      return false;
    }

    const existing = findExistingScript();
    if (existing) {
      const existingReady = await waitForLibraries(libraries, Number(options.timeoutMs || 15000));
      if (!existingReady) state.error = 'existing_script_without_required_libraries';
      return existingReady;
    }

    const scriptLoaded = await injectScript(key, libraries, options);
    if (!scriptLoaded) {
      state.error = 'script_load_failed';
      return false;
    }

    const ready = await waitForLibraries(libraries, Number(options.timeoutMs || 15000));
    if (!ready) state.error = 'required_libraries_unavailable';
    return ready;
  };

  const load = (options) => {
    options = options && typeof options === 'object' ? options : {};
    const libraries = normalizeLibraries(options.libraries || ['places']);
    libraries.forEach((library) => state.requestedLibraries.add(library));
    addAutocompleteLayerStyle();

    if (allReady(libraries)) {
      state.status = 'ready';
      state.readyAt = state.readyAt || Date.now();
      return Promise.resolve(true);
    }

    if (state.promise) {
      return state.promise.then(async (loaded) => {
        if (!loaded) return false;
        return importLibraries(libraries);
      });
    }

    state.promise = startLoad(options, Array.from(state.requestedLibraries))
      .then((loaded) => {
        state.status = loaded ? 'ready' : 'error';
        if (loaded) {
          state.readyAt = Date.now();
          dispatch('fieldflow:google-maps-ready', { libraries: Array.from(state.requestedLibraries) });
        } else {
          dispatch('fieldflow:google-maps-error', { error: state.error });
        }
        return loaded;
      })
      .catch((error) => {
        state.status = 'error';
        state.error = error && error.message ? String(error.message) : 'unknown_error';
        dispatch('fieldflow:google-maps-error', { error: state.error });
        return false;
      })
      .finally(() => {
        state.promise = null;
      });

    return state.promise;
  };

  window.FieldFlowGoogleMaps = {
    version: '1.0.0',
    load,
    isReady(libraries) {
      return allReady(normalizeLibraries(libraries || ['places']));
    },
    getState() {
      return {
        status: state.status,
        error: state.error,
        attempts: state.attempts,
        readyAt: state.readyAt,
        libraries: Array.from(state.requestedLibraries),
      };
    },
  };
})(window, document);
