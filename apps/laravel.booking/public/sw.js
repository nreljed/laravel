var CACHE_VERSION = '1.0.0';

self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(clients.claim());
});

self.addEventListener('push', function (event) {
  var data;
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = {};
  }

  var title = data.title || 'Booking App';
  var options = {
    body: data.body || 'You have a new notification.',
    icon: data.icon || '/build/assets/favicon.ico',
    badge: data.badge || data.icon || '/build/assets/favicon.ico',
    data: data.data || { url: '/dashboard' },
    vibrate: [200, 100, 200],
    tag: 'crm-notification-' + (data.data && data.data.notification_id ? data.data.notification_id : Date.now()),
    renotify: true,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  var url = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : '/dashboard';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (var i = 0; i < clientList.length; i++) {
        var client = clientList[i];
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
