import { test } from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import { createMutationGuard, isMutation, stableValue } from '../src/service/mutationGuard.mjs';

const memoryStorage = () => {
    const data = new Map();
    return { getItem: (key) => data.get(key), setItem: (key, value) => data.set(key, value), removeItem: (key) => data.delete(key), data };
};

test('double clicks share a single pending payment and release the lock', async () => {
    let resolve;
    let sends = 0;
    const states = [];
    const run = createMutationGuard({ crypto: webcrypto, onPending: (count) => states.push(count) });
    const send = () => {
        sends++;
        return new Promise((done) => {
            resolve = done;
        });
    };
    const first = run('payment', send);
    const second = run('payment', send);
    assert.equal(first, second);
    assert.deepEqual(states, [1]);
    await Promise.resolve();
    assert.equal(sends, 1);
    resolve({ id: 42 });
    assert.deepEqual(await second, { id: 42 });
    assert.deepEqual(states, [1, 0]);
});

test('ambiguous failures reuse their key, including after a reload', async () => {
    const storage = memoryStorage();
    let firstKey;
    const run = createMutationGuard({ storage, crypto: webcrypto });
    await assert.rejects(
        run('invoice', async (key) => {
            firstKey = key;
            throw new Error('timeout');
        })
    );
    const reload = createMutationGuard({ storage, crypto: webcrypto });
    await reload('invoice', async (key) => {
        assert.equal(key, firstKey);
        return 'saved';
    });
    assert.equal(storage.data.size, 0);
    await reload('invoice', async (key) => {
        assert.notEqual(key, firstKey);
    });
});

test('server failures keep their key but validation errors allow a new action', async () => {
    const run = createMutationGuard({ crypto: webcrypto });
    let savedKey;
    await assert.rejects(
        run('consultation', async (key) => {
            savedKey = key;
            throw { response: { status: 500 } };
        })
    );
    await assert.rejects(
        run('consultation', async (key) => {
            assert.equal(key, savedKey);
            throw { response: { status: 422 } };
        })
    );
    await run('consultation', async (key) => {
        assert.notEqual(key, savedKey);
    });
});

test('distinct payloads are independent and synchronous errors unlock correctly', async () => {
    const states = [];
    const run = createMutationGuard({ crypto: webcrypto, onPending: (count) => states.push(count) });
    let key;
    await assert.rejects(
        run('one', (value) => {
            key = value;
            throw new Error('network');
        })
    );
    await run('one', async (value) => assert.equal(value, key));
    const keys = await Promise.all([run('one', async (value) => value), run('two', async (value) => value)]);
    assert.notEqual(keys[0], keys[1]);
    assert.equal(states.at(-1), 0);
});

test('blocked storage and insecure HTTP still permit safe in-memory retries', async () => {
    const run = createMutationGuard({
        crypto: { getRandomValues: webcrypto.getRandomValues.bind(webcrypto) },
        storage: {
            getItem() {
                throw new Error('blocked');
            }
        }
    });
    let key;
    await assert.rejects(
        run('appointment', async (value) => {
            key = value;
            throw new Error('offline');
        })
    );
    await run('appointment', async (value) => assert.equal(value, key));
});

test('expired uncertain operations get a new key and canonical objects preserve array order', async () => {
    let time = 0;
    let key;
    const run = createMutationGuard({ crypto: webcrypto, now: () => time });
    await assert.rejects(
        run('payment', async (value) => {
            key = value;
            throw new Error('offline');
        })
    );
    time = 86400001;
    await run('payment', async (value) => assert.notEqual(value, key));
    assert.deepEqual(stableValue({ z: [{ b: 2, a: 1 }], a: 4 }), { a: 4, z: [{ a: 1, b: 2 }] });
    assert.equal(isMutation('post'), true);
    assert.equal(isMutation('GET'), false);
});
