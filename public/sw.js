const CACHE_NAME = 'mrcharlie-v1';
const urlsToCache = [
  '/',
  '/admin_styles.css',
  '/logocharlie.svg'
];

self.addEventListener('install', event => {
  console.log('[SW] Install event');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('[SW] Caching files:', urlsToCache);
        return cache.addAll(urlsToCache);
      })
      .catch(err => console.error('[SW] Cache addAll error:', err))
  );
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  // Bypass cache for non-GET or dynamic endpoints like form submission
  if (event.request.method !== 'GET' || url.pathname === '/save-form') {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    caches.match(event.request).then(response => {
      if (response) return response;
      return fetch(event.request);
    })
  );
});
