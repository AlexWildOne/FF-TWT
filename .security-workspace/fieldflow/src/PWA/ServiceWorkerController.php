<?php
namespace RoutesPro\PWA;

if (!defined('ABSPATH')) exit;

class ServiceWorkerController {
    public static function serve(): void {
        nocache_headers();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        $version = defined('FIELDFLOW_VERSION') ? FIELDFLOW_VERSION : (defined('ROUTESPRO_VERSION') ? ROUTESPRO_VERSION : '1');
        $pluginPath = (string)(parse_url(defined('ROUTESPRO_URL') ? ROUTESPRO_URL : '', PHP_URL_PATH) ?: '/wp-content/plugins/fieldflow/');
        ?>
const FIELDFLOW_PWA_VERSION = <?php echo wp_json_encode($version); ?>;
const FIELDFLOW_PWA_CACHE_PREFIX = 'fieldflow-pwa-static-';
const FIELDFLOW_PWA_CACHE = FIELDFLOW_PWA_CACHE_PREFIX + FIELDFLOW_PWA_VERSION;
const FIELDFLOW_PLUGIN_PATH = <?php echo wp_json_encode($pluginPath); ?>;

self.addEventListener('install', event => {
  event.waitUntil(caches.open(FIELDFLOW_PWA_CACHE).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(key => key.indexOf(FIELDFLOW_PWA_CACHE_PREFIX) === 0 && key !== FIELDFLOW_PWA_CACHE).map(key => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

function isFieldFlowStatic(request) {
  try {
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return false;
    if (FIELDFLOW_PLUGIN_PATH && url.pathname.indexOf(FIELDFLOW_PLUGIN_PATH) === 0) return true;
    return ['script','style','font','image'].includes(request.destination) && url.pathname.indexOf('/fieldflow') !== -1;
  } catch (_) { return false; }
}

function offlineDocument() {
  return new Response('<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>FieldFlow offline</title></head><body style="font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;background:#0f172a;color:#fff;margin:0;padding:32px"><main style="max-width:560px;margin:15vh auto"><h1>FieldFlow está offline</h1><p>Os reportes já guardados no dispositivo continuam protegidos. Abre novamente esta área quando existir ligação para carregar o ecrã e sincronizar.</p></main></body></html>', {
    status: 503,
    headers: {'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store','X-FieldFlow-Offline':'1'}
  });
}

self.addEventListener('fetch', event => {
  const request = event.request;
  if (!request || request.method !== 'GET') return;
  let url;
  try { url = new URL(request.url); } catch (_) { return; }
  if (url.origin !== self.location.origin) return;

  if (isFieldFlowStatic(request)) {
    event.respondWith(
      caches.open(FIELDFLOW_PWA_CACHE).then(cache => cache.match(request).then(cached => {
        const refresh = fetch(request).then(response => {
          if (response && response.ok && response.type === 'basic') cache.put(request, response.clone()).catch(() => {});
          return response;
        });
        return cached || refresh;
      })).catch(() => fetch(request))
    );
    return;
  }

  if (request.mode === 'navigate' && url.pathname.indexOf('/fieldflow-mobile/') !== -1) {
    event.respondWith(fetch(request).catch(() => offlineDocument()));
  }
});
<?php
        exit;
    }
}
