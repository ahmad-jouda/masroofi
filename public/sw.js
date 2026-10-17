// Service Worker for Masroofi Pro PWA (Background Sync & Offline Engine)
const CACHE_NAME = 'masroofi-pwa-v5';
const STATIC_ASSETS = [
  '/',
  '/css/style.css',
  '/js/offline-sync.js',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/icon.svg'
];

// IndexedDB Helper inside Service Worker
const DB_NAME = 'MasroofiOfflineDB';
const DB_VERSION = 1;
const STORE_NAME = 'pending_transactions';

function openIDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
        store.createIndex('createdAt', 'createdAt', { unique: false });
      }
    };
    req.onsuccess = (e) => resolve(e.target.result);
    req.onerror = (e) => reject(e);
  });
}

function getAllPendingFromIDB() {
  return new Promise(async (resolve) => {
    try {
      const db = await openIDB();
      const tx = db.transaction([STORE_NAME], 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const req = store.getAll();
      req.onsuccess = (e) => resolve(e.target.result || []);
      req.onerror = () => resolve([]);
    } catch (e) {
      resolve([]);
    }
  });
}

function clearPendingFromIDB() {
  return new Promise(async (resolve) => {
    try {
      const db = await openIDB();
      const tx = db.transaction([STORE_NAME], 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const req = store.clear();
      req.onsuccess = () => resolve(true);
      req.onerror = () => resolve(false);
    } catch (e) {
      resolve(false);
    }
  });
}

// Background Sync Task
async function syncPendingTransactionsFromSW() {
  const pending = await getAllPendingFromIDB();
  if (!pending || pending.length === 0) return;

  const payload = {
    transactions: pending.map((item) => ({
      type: item.type,
      amount: item.amount,
      envelope_id: item.envelope_id || null,
      goal_id: item.goal_id || null,
      date: item.date,
      payment_method: item.payment_method,
      note: item.note || null
    }))
  };

  try {
    const response = await fetch('/transactions/sync', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'include',
      body: JSON.stringify(payload)
    });

    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        await clearPendingFromIDB();

        // Notify active windows/clients
        const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
        for (const client of clients) {
          client.postMessage({ type: 'TRANSACTIONS_SYNCED', count: data.synced_count });
        }

        // Show system notification in background if permission is granted
        if (self.registration && 'showNotification' in self.registration && self.Notification && Notification.permission === 'granted') {
          self.registration.showNotification('مصروفي برو 🚀', {
            body: `تم رفع ومزامنة ${data.synced_count} معاملة كانت معلقة في الخلفية بنجاح!`,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png'
          });
        }
      }
    }
  } catch (err) {
    console.error('Service Worker background sync error:', err);
    throw err; // Triggers browser automatic retry
  }
}

// Background Sync API listener (runs even when browser/tab is closed!)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-pending-transactions' || event.tag === 'masroofi-background-sync') {
    event.waitUntil(syncPendingTransactionsFromSW());
  }
});

// Periodic Background Sync (periodic checks when idle/charging)
self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'sync-pending-transactions' || event.tag === 'masroofi-background-sync') {
    event.waitUntil(syncPendingTransactionsFromSW());
  }
});

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(STATIC_ASSETS);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // For static assets: Cache first
  if (url.pathname.startsWith('/css/') || url.pathname.startsWith('/icons/') || url.pathname.startsWith('/js/')) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        return cached || fetch(event.request).then((response) => {
          if (response && response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // For navigation / pages: Network first, fallback to cached
  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
        }
        return response;
      })
      .catch(() => {
        return caches.match(event.request).then((cached) => {
          return cached || caches.match('/');
        });
      })
  );
});

// ==========================================
// NATIVE WEB PUSH NOTIFICATIONS LISTENER
// ==========================================
self.addEventListener('push', (event) => {
  let data = {
    title: 'مصروفي برو 🔔',
    body: 'لديك تنبيه مالي جديد في حسابك',
    icon: '/icons/icon-192.png',
    badge: '/icons/icon-192.png',
    url: '/',
    tag: 'masroofi-push-default'
  };

  if (event.data) {
    try {
      const parsed = event.data.json();
      data = Object.assign(data, parsed);
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/icons/icon-192.png',
    badge: data.badge || '/icons/icon-192.png',
    vibrate: [200, 100, 200, 100, 200],
    tag: data.tag || 'masroofi-push',
    renotify: true,
    requireInteraction: false,
    data: {
      url: data.url || '/',
      timestamp: Date.now()
    }
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = (event.notification.data && event.notification.data.url) 
    ? event.notification.data.url 
    : '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // If a tab is already open with the app, focus it and navigate
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          if ('navigate' in client && targetUrl !== '/') {
            client.navigate(targetUrl);
          }
          return client.focus();
        }
      }
      // Otherwise open a new window
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
