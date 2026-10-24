function safeNotificationLink(link) {
    if (typeof link !== 'string') {
        return null;
    }

    if (!link.startsWith('/') || link.startsWith('//') || /^[a-z][a-z0-9+.-]*:/i.test(link)) {
        return null;
    }

    if (link.includes('\\') || link.includes('\n') || link.includes('\r')) {
        return null;
    }

    return link;
}

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (_) {
        data = {};
    }

    const title = typeof data.title === 'string' && data.title ? data.title : 'Notification';
    const message = typeof data.message === 'string' ? data.message : '';
    const link = safeNotificationLink(data.link);
    const tag = data.id != null ? `notif-${data.id}` : `notif-${Date.now()}`;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const visible = clients.some((client) => client.focused || client.visibilityState === 'visible');
            if (visible) {
                return Promise.all(
                    clients.map((client) =>
                        client.postMessage({
                            type: 'push-notification',
                            notification: {
                                id: data.id ?? null,
                                title,
                                message,
                                link
                            }
                        })
                    )
                );
            }

            return self.registration.showNotification(title, {
                body: message,
                icon: '/logo.png',
                tag,
                data: { link }
            });
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const link = safeNotificationLink(event.notification.data?.link);

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
