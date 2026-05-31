// Service Worker — JB Admin PWA
const CACHE = 'jb-admin-v1';

// Solo cachear assets estáticos esenciales
const PRECACHE = [
  '/admin/dashboard.php',
  '/assets/img/logos/logorp.png',
];

self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(PRECACHE).catch(() => {}))
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    ).then(() => clients.claim())
  );
});

// Network-first: intenta red, cae a caché solo si falla
self.addEventListener('fetch', e => {
  // Solo interceptar GET del mismo origen
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);
  if (url.origin !== location.origin) return;

  e.respondWith(
    fetch(e.request)
      .then(res => {
        // Actualizar caché con respuesta fresca
        if (res.ok) {
          const clone = res.clone();
          caches.open(CACHE).then(c => c.put(e.request, clone)).catch(() => {});
        }
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});
