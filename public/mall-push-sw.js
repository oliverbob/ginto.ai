// Ginto Mall Push Notification Service Worker
// Handles background Web Push notifications

self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(clients.claim());
});

self.addEventListener('push', (e) => {
    if (!e.data) return;

    let payload;
    try {
        payload = e.data.json();
    } catch (err) {
        payload = { title: 'Ginto Notification', body: e.data.text() };
    }

    const title   = payload.title   || 'Ginto Mall';
    const options = {
        body:    payload.body    || '',
        icon:    payload.icon    || '/assets/img/logo.png',
        badge:   payload.badge   || '/assets/img/badge.png',
        tag:     payload.tag     || 'ginto-mall',
        data:    payload.data    || {},
        actions: payload.actions || [],
        vibrate: [200, 100, 200],
    };

    e.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', (e) => {
    e.notification.close();

    const url = (e.notification.data && e.notification.data.url)
        ? e.notification.data.url
        : '/mall/delivery';

    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

self.addEventListener('sync', (e) => {
    if (e.tag === 'gps-sync') {
        // handled by main app JS
    }
});
