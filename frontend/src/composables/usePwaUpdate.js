import { onMounted, readonly, ref } from 'vue';
import { logAppError } from '@/utils/appLogger';
import { createReleaseGate, fetchRelease, requiresUpdate } from '@/utils/pwaRelease.mjs';
import { lockForPwaUpdate } from '@/utils/pwaUpdateLock.mjs';

const current = import.meta.env.VITE_APP_RELEASE;
const storageKey = `dentalsoft-pwa-release-v1:${import.meta.env.BASE_URL}`;
const gate = createReleaseGate(current);
const mandatory = ref(false);
const needRefresh = ref(false);
const installing = ref(false);
const checking = ref(false);
const errorMessage = ref('');
let registration = null;
let started = false;
let pendingCheck = null;
let remote = null;
let dismissedBuild = null;
let candidate = null;
let channel = null;
let reloading = false;

function workerIdentity(worker) {
    return new Promise((resolve, reject) => {
        const pipe = new MessageChannel();
        const timer = setTimeout(() => {
            pipe.port1.close();
            reject(new Error('Service worker identity timeout'));
        }, 5000);
        pipe.port1.onmessage = (event) => {
            clearTimeout(timer);
            pipe.port1.close();
            resolve(event.data);
        };
        worker.postMessage({ type: 'GET_PWA_RELEASE' }, [pipe.port2]);
    });
}

async function syncWaitingState() {
    const worker = registration?.waiting || registration?.active;
    candidate = null;
    needRefresh.value = false;
    if (!worker || !['installed', 'activated'].includes(worker.state)) return;
    const identity = await workerIdentity(worker);
    if (identity.buildId === current?.buildId || (registration.waiting !== worker && registration.active !== worker) || (remote && requiresUpdate(identity, remote))) return;
    candidate = worker;
    needRefresh.value = mandatory.value || dismissedBuild !== identity.buildId;
}

function acceptRelease(value, broadcast = false) {
    const remember = (release) => {
        gate.accept(release);
        const threshold = release.minimum?.generation || 0;
        const previousThreshold = remote?.minimum?.generation || 0;
        if (!remote || threshold > previousThreshold || (threshold === previousThreshold && release.generation >= remote.generation)) remote = release;
    };
    remember(value);
    // Read before writing, then repair stale storage events: another tab may have
    // announced a stronger minimum since this document last checked the network.
    let saved = null;
    try {
        saved = localStorage.getItem(storageKey);
        if (saved) remember(JSON.parse(saved));
    } catch {
        /* Invalid/unavailable storage must not prevent a valid remote instruction. */
    }
    if (gate.mandatory) lockForPwaUpdate();
    mandatory.value = gate.mandatory;
    candidate = null;
    needRefresh.value = false;
    try {
        const serialized = JSON.stringify(remote);
        if (serialized !== saved) localStorage.setItem(storageKey, serialized);
    } catch {
        /* Private mode / quota: network checks still work. */
    }
    if (broadcast) channel?.postMessage(remote);
}

export function checkForUpdates() {
    if (pendingCheck) return pendingCheck;
    checking.value = true;
    pendingCheck = (async () => {
        let failed = false;
        try {
            const value = await fetchRelease(fetch, `${import.meta.env.BASE_URL}release.json`);
            acceptRelease(value, true);
        } catch (error) {
            failed = true;
            logAppError('pwa.metadata', error);
        }
        try {
            if ('serviceWorker' in navigator) {
                if (!registration) {
                    registration = await navigator.serviceWorker.register(`${import.meta.env.BASE_URL}sw.js`, { updateViaCache: 'none' });
                    registration.addEventListener('updatefound', () => {
                        const worker = registration.installing;
                        worker?.addEventListener('statechange', () => {
                            if (worker.state === 'installed') syncWaitingState().catch(reportError);
                            if (worker.state === 'redundant') reportError(new Error('Service worker installation failed'));
                        });
                    });
                }
                try {
                    await registration.update();
                } finally {
                    // An offline check must not hide an already installed update.
                    await syncWaitingState();
                }
            } else if (mandatory.value) {
                throw new Error('Service workers unavailable');
            }
        } catch (error) {
            failed = true;
            logAppError('pwa.update', error);
        }
        errorMessage.value = failed ? 'La mise à jour n’a pas pu être vérifiée. Vérifiez votre connexion puis réessayez.' : '';
    })().finally(() => {
        checking.value = false;
        pendingCheck = null;
    });
    return pendingCheck;
}

function reportError(error) {
    errorMessage.value = 'Installation indisponible. Vérifiez votre connexion puis réessayez.';
    logAppError('pwa.install', error);
}

async function applyUpdate() {
    if (installing.value) return;
    installing.value = true;
    try {
        // Recheck the threshold and the exact installed worker before sending SKIP_WAITING.
        await checkForUpdates();
        const worker = candidate;
        if (!worker) throw new Error('No eligible installed worker');
        const identity = await workerIdentity(worker);
        if (candidate !== worker || (remote && requiresUpdate(identity, remote))) throw new Error('Installed worker no longer meets the required minimum');
        if (registration.active === worker && worker.state === 'activated') {
            reloading = true;
            window.location.reload();
            return;
        }
        if (registration.waiting !== worker || worker.state !== 'installed') throw new Error('No eligible installed worker');
        worker.postMessage({ type: 'SKIP_WAITING' });
        // No reload fallback: a failed activation must remain retryable, not loop.
        setTimeout(() => {
            installing.value = false;
            if (!reloading) reportError(new Error('Activation timeout'));
        }, 15000);
    } catch (error) {
        installing.value = false;
        reportError(error);
    }
}

function start() {
    if (started || !import.meta.env.PROD) return;
    started = true;
    try {
        const saved = localStorage.getItem(storageKey);
        if (saved) acceptRelease(JSON.parse(saved));
    } catch {
        /* Invalid or unavailable storage is not a remote update instruction. */
    }
    window.addEventListener('storage', ({ key, newValue }) => {
        if (key !== storageKey || !newValue) return;
        try {
            acceptRelease(JSON.parse(newValue));
            syncWaitingState().catch(reportError);
        } catch (error) {
            logAppError('pwa.storage', error);
        }
    });
    if ('BroadcastChannel' in window) {
        channel = new BroadcastChannel(storageKey);
        channel.onmessage = ({ data }) => {
            try {
                acceptRelease(data);
                syncWaitingState().catch(reportError);
            } catch (error) {
                logAppError('pwa.broadcast', error);
            }
        };
    }
    navigator.serviceWorker?.addEventListener('controllerchange', async () => {
        // Another tab can activate the installed worker. Never reload for a first claim of this same build.
        try {
            const identity = await workerIdentity(navigator.serviceWorker.controller);
            if (identity.buildId !== current?.buildId && !reloading) {
                reloading = true;
                window.location.reload();
            }
        } catch (error) {
            reportError(error);
        }
    });
    const onFocus = () => {
        if (document.visibilityState !== 'hidden') checkForUpdates();
    };
    window.addEventListener('focus', onFocus);
    window.addEventListener('online', onFocus);
    document.addEventListener('visibilitychange', onFocus);
    window.setInterval(checkForUpdates, 60000);
    checkForUpdates();
}

export function usePwaUpdate() {
    onMounted(start);
    return {
        mandatory: readonly(mandatory),
        needRefresh: readonly(needRefresh),
        installing: readonly(installing),
        checking: readonly(checking),
        errorMessage: readonly(errorMessage),
        applyUpdate,
        checkForUpdates,
        async dismissUpdate() {
            if (mandatory.value) return;
            const worker = registration?.waiting;
            try {
                if (worker) dismissedBuild = (await workerIdentity(worker)).buildId;
            } catch (error) {
                reportError(error);
            }
            if (!mandatory.value) needRefresh.value = false;
        }
    };
}
