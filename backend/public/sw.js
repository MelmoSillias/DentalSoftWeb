const CACHE_NAME = 'dentalsoft-pwa-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/manifest.webmanifest',
  '/sb-admin/css/sb-admin-2.min.css',
  '/sb-admin/js/sb-admin-2.min.js',
  '/sb-admin/vendor/jquery/jquery.min.js',
  '/sb-admin/vendor/bootstrap/js/bootstrap.bundle.min.js',
  '/sb-admin/vendor/fontawesome-free/css/all.min.css'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS_TO_CACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.map(key => (key !== CACHE_NAME ? caches.delete(key) : null)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET') return;

  event.respondWith(
    caches.match(request).then(cached => {
      if (cached) return cached;
      return fetch(request)
        .then(response => {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
          return response;
        })
        .catch(() => cached);
    })
  );
});
