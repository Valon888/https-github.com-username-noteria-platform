// Service Worker for Noteria PWA
const CACHE_NAME = 'noteria-v1.0.0';
const STATIC_CACHE = 'noteria-static-v1.0.0';
const DYNAMIC_CACHE = 'noteria-dynamic-v1.0.0';

// Resources to cache immediately
const STATIC_ASSETS = [
  '/',
  '/register.php',
  '/login.php',
  '/manifest.json',
  '/images/Emblem_of_the_Republic_of_Kosovo.svg.png',
  'https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap',
  'https://challenges.cloudflare.com/turnstile/v0/api.js'
];

// API endpoints to cache for offline use
const API_ENDPOINTS = [
  '/api/profile',
  '/api/appointments',
  '/api/documents',
  '/api/notifications'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('Service Worker: Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
            console.log('Service Worker: Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Handle API requests
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(handleApiRequest(request));
    return;
  }

  // Handle static assets
  if (STATIC_ASSETS.includes(url.pathname) || url.hostname === 'fonts.googleapis.com') {
    event.respondWith(
      caches.match(request)
        .then(response => response || fetch(request))
    );
    return;
  }

  // Handle other requests with cache-first strategy
  event.respondWith(
    caches.match(request)
      .then(response => {
        if (response) {
          return response;
        }

        return fetch(request)
          .then(response => {
            // Don't cache if not successful
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // Cache successful responses
            const responseToCache = response.clone();
            caches.open(DYNAMIC_CACHE)
              .then(cache => {
                cache.put(request, responseToCache);
              });

            return response;
          })
          .catch(() => {
            // Return offline fallback for navigation requests
            if (request.mode === 'navigate') {
              return caches.match('/offline.html') || new Response('Offline', { status: 503 });
            }
          });
      })
  );
});

// Handle API requests with offline support
async function handleApiRequest(request) {
  const url = new URL(request.url);

  // For GET requests, try cache first, then network
  if (request.method === 'GET') {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      // Return cached response and update in background
      fetch(request)
        .then(response => {
          if (response.ok) {
            caches.open(DYNAMIC_CACHE)
              .then(cache => cache.put(request, response));
          }
        })
        .catch(() => {
          // Network failed, keep cached version
        });

      return cachedResponse;
    }

    // No cache, try network
    try {
      const response = await fetch(request);
      if (response.ok) {
        const cache = await caches.open(DYNAMIC_CACHE);
        cache.put(request, response.clone());
      }
      return response;
    } catch (error) {
      // Network failed, return offline response
      return new Response(JSON.stringify({
        success: false,
        message: 'You are offline. This data may be outdated.',
        offline: true
      }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      });
    }
  }

  // For POST/PUT/DELETE, try network first
  try {
    const response = await fetch(request);
    return response;
  } catch (error) {
    // Network failed, queue for later sync
    if (request.method !== 'GET') {
      queueRequestForSync(request);
    }
    return new Response(JSON.stringify({
      success: false,
      message: 'Request queued for when you are back online.',
      queued: true
    }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Queue requests for background sync
function queueRequestForSync(request) {
  // Store request in IndexedDB for later sync
  const requestData = {
    url: request.url,
    method: request.method,
    headers: Object.fromEntries(request.headers.entries()),
    body: null,
    timestamp: Date.now()
  };

  // Clone and read body if it exists
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    request.clone().text().then(body => {
      requestData.body = body;
      storeQueuedRequest(requestData);
    });
  } else {
    storeQueuedRequest(requestData);
  }
}

// Store queued request in IndexedDB
function storeQueuedRequest(requestData) {
  // This would use IndexedDB to store the request
  // For now, we'll use localStorage as fallback
  const queue = JSON.parse(localStorage.getItem('syncQueue') || '[]');
  queue.push(requestData);
  localStorage.setItem('syncQueue', JSON.stringify(queue));
}

// Background sync
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(processQueuedRequests());
  }
});

// Process queued requests when back online
async function processQueuedRequests() {
  const queue = JSON.parse(localStorage.getItem('syncQueue') || '[]');
  if (queue.length === 0) return;

  const newQueue = [];

  for (const requestData of queue) {
    try {
      const response = await fetch(requestData.url, {
        method: requestData.method,
        headers: requestData.headers,
        body: requestData.body
      });

      if (response.ok) {
        console.log('Synced request:', requestData.url);
      } else {
        // Keep in queue if failed
        newQueue.push(requestData);
      }
    } catch (error) {
      // Keep in queue if network error
      newQueue.push(requestData);
    }
  }

  localStorage.setItem('syncQueue', JSON.stringify(newQueue));
}

// Push notification event
self.addEventListener('push', event => {
  console.log('Push received:', event);

  let data = {};
  if (event.data) {
    data = event.data.json();
  }

  const options = {
    body: data.body || 'You have a new notification',
    icon: '/images/icon-192x192.png',
    badge: '/images/icon-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: data.id || 1
    },
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/images/icon-72x72.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title || 'Noteria', options)
  );
});

// Notification click event
self.addEventListener('notificationclick', event => {
  console.log('Notification click:', event);
  event.notification.close();

  if (event.action === 'view') {
    // Open the app
    event.waitUntil(
      clients.openWindow(event.notification.data.url || '/')
    );
  }
});

// Message event for communication with main thread
self.addEventListener('message', event => {
  console.log('Message received:', event.data);

  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

