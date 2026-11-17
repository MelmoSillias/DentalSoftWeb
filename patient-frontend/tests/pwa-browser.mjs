// Explicit opt-in integration test. Uses installed Chrome/Edge and Node's native CDP WebSocket.
import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { dirname, extname, join, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';
import { captureRelease } from '../scripts/pwa-release.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const browser = process.env.PWA_BROWSER_EXECUTABLE || ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
const pause = (ms) => new Promise((done) => setTimeout(done, ms));

async function until(check, label, timeout = 30000) {
    const deadline = Date.now() + timeout;
    let lastError;
    while (Date.now() < deadline) {
        try {
            if (await check()) return;
        } catch (error) {
            lastError = error;
        }
        await pause(150);
    }
    throw new Error(`Timed out: ${label}${lastError ? ` (${lastError.message})` : ''}`);
}

async function connect(url) {
    const socket = new WebSocket(url);
    await new Promise((done, reject) => {
        socket.addEventListener('open', done, { once: true });
        socket.addEventListener('error', reject, { once: true });
    });
    let nextId = 0;
    const pending = new Map();
    const listeners = new Set();
    socket.addEventListener('message', ({ data }) => {
        const value = JSON.parse(String(data));
        if (value.method) for (const listener of listeners) listener(value);
        const request = pending.get(value.id);
        if (!request) return;
        pending.delete(value.id);
        clearTimeout(request.timer);
        if (value.error) request.reject(new Error(value.error.message));
        else request.resolve(value.result);
    });
    return {
        close: () => socket.close(),
        onEvent: (listener) => listeners.add(listener),
        send(method, params = {}, sessionId) {
            const id = ++nextId;
            return new Promise((resolveRequest, reject) => {
                const timer = setTimeout(() => {
                    pending.delete(id);
                    reject(new Error(`CDP timeout: ${method}`));
                }, 10000);
                pending.set(id, { resolve: resolveRequest, reject, timer });
                socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
            });
        }
    };
}

async function tab(cdp, url) {
    const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
    const send = (method, params) => cdp.send(method, params, sessionId);
    let navigations = 0;
    cdp.onEvent((event) => {
        if (event.sessionId === sessionId && event.method === 'Page.frameNavigated' && !event.params.frame.parentId && event.params.frame.url.startsWith('http')) navigations++;
    });
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Network.enable');
    await send('Network.setBlockedURLs', { urls: ['*://fonts.googleapis.com/*', '*://fonts.gstatic.com/*', '*://cdnjs.cloudflare.com/*'] });
    await send('Page.navigate', { url });
    return {
        send,
        get navigations() {
            return navigations;
        },
        async evaluate(expression) {
            const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
            if (result.exceptionDetails) throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
            return result.result.value;
        }
    };
}

function launchBrowser(profile) {
    const child = spawn(
        browser,
        ['--headless=new', '--remote-debugging-port=0', `--user-data-dir=${profile}`, '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--disable-background-networking', '--disable-sync', '--no-proxy-server', 'about:blank'],
        { stdio: ['ignore', 'ignore', 'pipe'] }
    );
    const endpoint = new Promise((done, reject) => {
        const timer = setTimeout(() => reject(new Error('Browser startup timeout')), 20000);
        let output = '';
        child.stderr.on('data', (data) => {
            output += String(data);
            const match = output.match(/DevTools listening on (ws:\/\/[^\s]+)/);
            if (match) {
                clearTimeout(timer);
                done(match[1]);
            }
        });
        child.once('error', (error) => {
            clearTimeout(timer);
            reject(error);
        });
        child.once('exit', (code) => {
            clearTimeout(timer);
            reject(new Error(`Browser exited: ${code}\n${output}`));
        });
    });
    return { child, endpoint };
}

test('real production SW: required update blocks two tabs, installs on click and preserves storage', { timeout: 210000 }, async () => {
    assert.ok(browser && existsSync(browser), 'Set PWA_BROWSER_EXECUTABLE to an installed Chrome/Edge executable');
    const cache = join(root, 'node_modules', '.cache');
    mkdirSync(cache, { recursive: true });
    const temporary = mkdtempSync(join(cache, 'pwa-browser-'));
    let server;
    let chrome;
    let cdp;
    try {
        const original = captureRelease({ cwd: root, env: {} });
        const a = { ...original, policy: 'optional', minimum: null, buildId: `${original.revision}-browser-A` };
        const revision = createHash('sha1').update(`${original.revision}-browser-B`).digest('hex');
        const b = { ...a, revision, generation: a.generation + 1, policy: 'required', buildId: `${revision}-browser-B`, minimum: { revision, generation: a.generation + 1 } };
        for (const [name, release] of [
            ['a', a],
            ['b', b]
        ]) {
            console.log(`Building browser fixture ${name.toUpperCase()}…`);
            execFileSync(process.execPath, [join(root, 'node_modules/vite/bin/vite.js'), 'build', '--outDir', join(temporary, name)], {
                cwd: root,
                timeout: 75000,
                maxBuffer: 8 * 1024 * 1024,
                env: { ...process.env, VITE_ENABLE_PWA: 'true', VITE_DOKPLOY: 'true', VITE_API_BASE_URL: '', PWA_RELEASE_REVISION: release.revision, PWA_RELEASE_FILE: '', PWA_RELEASE_METADATA: Buffer.from(JSON.stringify(release)).toString('base64') }
            });
        }
        let deployed = 'a';
        let offline = false;
        server = createServer((request, response) => {
            const pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
            response.setHeader('Cache-Control', 'no-store');
            if (offline) {
                response.writeHead(503);
                response.end('offline fixture');
                return;
            }
            if (/^\/(api|uploads)(\/|$)/.test(pathname)) {
                response.writeHead(200, { 'Content-Type': 'application/json' });
                response.end(JSON.stringify(pathname === '/api/settings/general/public' ? { patientPortalEnabled: true } : { sensitive: 'fixture-only-never-cache' }));
                return;
            }
            const directory = join(temporary, deployed);
            let file = resolve(directory, `.${pathname}`);
            if (file !== directory && !file.startsWith(directory + sep)) {
                response.writeHead(404);
                response.end();
                return;
            }
            if (!existsSync(file) || !statSync(file).isFile()) file = join(directory, 'index.html');
            const types = {
                '.js': 'text/javascript',
                '.css': 'text/css',
                '.json': 'application/json',
                '.html': 'text/html',
                '.webmanifest': 'application/manifest+json',
                '.svg': 'image/svg+xml',
                '.png': 'image/png',
                '.ico': 'image/x-icon',
                '.woff2': 'font/woff2'
            };
            response.setHeader('Content-Type', types[extname(file)] || 'application/octet-stream');
            response.end(readFileSync(file));
        });
        await new Promise((done) => server.listen(0, '127.0.0.1', done));
        const origin = `http://127.0.0.1:${server.address().port}`;
        chrome = launchBrowser(join(temporary, 'profile'));
        cdp = await connect(await chrome.endpoint);
        const first = await tab(cdp, origin);
        await until(() => first.evaluate('Boolean(navigator.serviceWorker.controller)'), 'first SW activation');
        await first.evaluate("localStorage.setItem('pwa-user-data-sentinel', 'preserve-me'); sessionStorage.setItem('pwa-tab-sentinel', 'first')");
        await first.evaluate("Promise.all(['/api/pwa-sensitive-probe', '/uploads/private.pdf'].map(path => fetch(path).then(r => r.json())))");
        const cachedUrls = await first.evaluate("(async () => (await Promise.all((await caches.keys()).map(async key => (await (await caches.open(key)).keys()).map(request => request.url)))).flat())()");
        assert.ok(cachedUrls.length > 0, 'application shell is precached');
        assert.ok(cachedUrls.every(url => !/\/(api|uploads)\/|release\.json/.test(url)), 'no API, medical file or release metadata in CacheStorage');
        const manifest = await first.evaluate("fetch('/manifest.webmanifest').then(r => r.json())");
        assert.ok(manifest.name.includes('Espace patient'));
        assert.ok(manifest.icons.length > 0);
        const second = await tab(cdp, origin);
        await until(() => second.evaluate('Boolean(navigator.serviceWorker.controller)'), 'second tab controlled');
        await second.evaluate("sessionStorage.setItem('pwa-tab-sentinel', 'second')");
        const navigationCount = (page) => page.evaluate("performance.getEntriesByType('navigation')[0]?.type");
        assert.equal(await navigationCount(first), 'navigate');
        deployed = 'b';
        await first.send('Page.bringToFront');
        await first.evaluate("window.dispatchEvent(new Event('focus'))");
        for (const [name, page] of [
            ['first', first],
            ['second', second]
        ]) {
            await until(() => page.evaluate("Boolean(document.querySelector('.pwa-required-update'))"), `${name} tab mandatory gate`);
            await until(() => page.evaluate("Array.from(document.querySelectorAll('.pwa-required-update button')).some(button => button.textContent.includes('Installer'))"), `${name} tab eligible waiting SW`);
            assert.equal(await page.evaluate("Boolean(document.querySelector('.pwa-update-banner'))"), false);
            assert.equal(await page.evaluate("Boolean(document.querySelector('.route-host, .login-page, .app-layout'))"), false, 'mandatory update unmounts all business UI');
            assert.equal(await navigationCount(page), 'navigate', 'must not reload before a click');
            assert.equal(page.navigations, 1, 'only the initial navigation is allowed before a click');
        }
        // Real registration.update() now rejects/returns a server error, but B is already installed.
        offline = true;
        await first.evaluate("Array.from(document.querySelectorAll('.pwa-required-update button')).find(button => button.textContent.includes('Réessayer')).click()");
        await until(() => first.evaluate("Boolean(document.querySelector('.pwa-required-update [role=alert]'))"), 'retryable network error');
        assert.equal(await navigationCount(first), 'navigate');
        await first.evaluate("Array.from(document.querySelectorAll('.pwa-required-update button')).find(button => button.textContent.includes('Installer')).click()");
        for (const [name, page] of [
            ['first', first],
            ['second', second]
        ]) {
            await until(async () => (await navigationCount(page)) === 'reload' && (await page.evaluate("Boolean(document.querySelector('#app')?.children.length) && !document.querySelector('.pwa-required-update')")), `${name} tab reloaded into B`);
            assert.equal(await page.evaluate("localStorage.getItem('pwa-user-data-sentinel')"), 'preserve-me');
            assert.equal(await page.evaluate("sessionStorage.getItem('pwa-tab-sentinel')"), name);
        }
        await pause(1500);
        for (const page of [first, second]) {
            assert.equal(page.navigations, 2, 'exactly one reload per tab, with no reload loop');
            assert.equal(await page.evaluate("document.querySelector('meta[name=\"app-version\"]')?.content.endsWith('browser-B')"), true);
            assert.equal(await page.evaluate("Boolean(document.querySelector('.pwa-required-update'))"), false);
        }
        console.log('Two real tabs installed B while offline, reloaded once, and retained user storage.');
    } finally {
        if (cdp) {
            try {
                await cdp.send('Browser.close');
            } catch {
                /* Browser closes its CDP socket. */
            }
            cdp.close();
        }
        if (chrome && chrome.child.exitCode === null) chrome.child.kill();
        if (server) {
            server.closeAllConnections();
            await new Promise((done) => server.close(done));
        }
        rmSync(temporary, { recursive: true, force: true, maxRetries: 20, retryDelay: 200 });
    }
});
