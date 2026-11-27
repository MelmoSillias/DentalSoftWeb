import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../public-shared/notification-handler.js', import.meta.url), 'utf8');

async function receivePush(link, visibilityState) {
    const handlers = new Map();
    const systemNotifications = [];
    const clientMessages = [];
    const clients = visibilityState ? [{ visibilityState, postMessage: (message) => clientMessages.push(message) }] : [];

    vm.runInNewContext(source, {
        self: {
            addEventListener: (name, handler) => handlers.set(name, handler),
            clients: { matchAll: async () => clients },
            registration: { showNotification: async (title, options) => systemNotifications.push({ title, ...options }) }
        }
    });

    let delivery;
    handlers.get('push')({
        data: { json: () => ({ id: 25, message: 'Nouvelle notification', link }) },
        waitUntil: (promise) => {
            delivery = promise;
        }
    });
    await delivery;
    return { systemNotifications, clientMessages };
}

test('mailbox arrivals display a system alert with or without an open app', async () => {
    const link = '/vitrine/messagerie?message=42';
    for (const state of ['visible', 'hidden', null]) {
        const { systemNotifications, clientMessages } = await receivePush(link, state);
        assert.equal(systemNotifications.length, 1);
        assert.equal(systemNotifications[0].tag, 'notif-25');
        assert.equal(systemNotifications[0].data.link, link);
        assert.equal(clientMessages.length, 0);
    }
});

test('other notifications keep the existing foreground behavior', async () => {
    const { systemNotifications, clientMessages } = await receivePush('/vitrine/avis-patients', 'visible');
    assert.equal(systemNotifications.length, 0);
    assert.equal(clientMessages[0].type, 'push-notification');
});
