self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const link = event.notification.data?.link;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            if (clients.length > 0) {
                const client = clients[0];
                return client.focus().then(() => {
                    if (link) {
                        client.postMessage({ type: 'notification-click', link });
                    }
                });
            }

            if (link) {
                return self.clients.openWindow(link);
            }

            return self.clients.openWindow('/');
        })
    );
});
