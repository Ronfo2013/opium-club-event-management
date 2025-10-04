const CACHE_NAME = 'opium-club-v1.0.0';
const urlsToCache = [
  '/',
  '/static/js/bundle.js',
  '/static/css/main.css',
  '/manifest.json',
  '/favicon.ico',
  '/logo192.png',
  '/logo512.png'
];

// Installazione del Service Worker
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installazione in corso...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Service Worker: Cache aperta');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('Service Worker: Installazione completata');
        return self.skipWaiting();
      })
      .catch((error) => {
        console.error('Service Worker: Errore durante l\'installazione:', error);
      })
  );
});

// Attivazione del Service Worker
self.addEventListener('activate', (event) => {
  console.log('Service Worker: Attivazione in corso...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Rimozione cache vecchia:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker: Attivazione completata');
      return self.clients.claim();
    })
  );
});

// Intercettazione delle richieste
self.addEventListener('fetch', (event) => {
  // Ignora le richieste non GET
  if (event.request.method !== 'GET') {
    return;
  }

  // Ignora le richieste API (devono sempre andare al server)
  if (event.request.url.includes('/api/')) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Cache hit - restituisci la risposta dalla cache
        if (response) {
          console.log('Service Worker: Risposta dalla cache:', event.request.url);
          return response;
        }

        // Cache miss - fai la richiesta al network
        console.log('Service Worker: Richiesta al network:', event.request.url);
        return fetch(event.request).then((response) => {
          // Controlla se abbiamo ricevuto una risposta valida
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // Clona la risposta
          const responseToCache = response.clone();

          // Aggiungi alla cache
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(event.request, responseToCache);
            });

          return response;
        }).catch(() => {
          // Se la richiesta fallisce, prova a restituire una pagina offline
          if (event.request.destination === 'document') {
            return caches.match('/');
          }
        });
      })
  );
});

// Gestione delle notifiche push
self.addEventListener('push', (event) => {
  console.log('Service Worker: Notifica push ricevuta');
  
  const options = {
    body: event.data ? event.data.text() : 'Nuova notifica da Opium Club',
    icon: '/logo192.png',
    badge: '/logo192.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'Visualizza',
        icon: '/logo192.png'
      },
      {
        action: 'close',
        title: 'Chiudi',
        icon: '/logo192.png'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification('Opium Club', options)
  );
});

// Gestione dei click sulle notifiche
self.addEventListener('notificationclick', (event) => {
  console.log('Service Worker: Click su notifica');
  
  event.notification.close();

  if (event.action === 'explore') {
    // Apri l'app
    event.waitUntil(
      clients.openWindow('/')
    );
  } else if (event.action === 'close') {
    // Chiudi la notifica
    event.notification.close();
  } else {
    // Click sulla notifica stessa
    event.waitUntil(
      clients.openWindow('/')
    );
  }
});

// Gestione della sincronizzazione in background
self.addEventListener('sync', (event) => {
  console.log('Service Worker: Sincronizzazione in background');
  
  if (event.tag === 'background-sync') {
    event.waitUntil(
      // Qui puoi implementare la logica di sincronizzazione
      // Ad esempio, inviare dati offline quando la connessione torna disponibile
      doBackgroundSync()
    );
  }
});

async function doBackgroundSync() {
  try {
    // Implementa la logica di sincronizzazione
    console.log('Service Worker: Esecuzione sincronizzazione in background');
  } catch (error) {
    console.error('Service Worker: Errore durante la sincronizzazione:', error);
  }
}

// Gestione dei messaggi dal client
self.addEventListener('message', (event) => {
  console.log('Service Worker: Messaggio ricevuto:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
