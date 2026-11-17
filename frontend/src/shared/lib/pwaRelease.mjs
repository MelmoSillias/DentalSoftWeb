// Shared by the build, browser and Node tests. No browser globals here.
const hash = /^[a-f0-9]{40,64}$/;

export function validateRelease(value) {
    const validPoint = (point) => point && hash.test(point.revision) && Number.isSafeInteger(point.generation) && point.generation > 0;
    if (value?.schema !== 1 || !validPoint(value) || !hash.test(value.lineage) || typeof value.buildId !== 'string' || !value.buildId || !['optional', 'required'].includes(value.policy)) {
        throw new Error('Invalid PWA release metadata');
    }
    if (value.minimum !== null && (!validPoint(value.minimum) || value.minimum.generation > value.generation || (value.minimum.generation === value.generation && value.minimum.revision !== value.revision))) {
        throw new Error('Invalid PWA minimum release');
    }
    if (value.policy === 'required' && (value.minimum?.revision !== value.revision || value.minimum?.generation !== value.generation)) throw new Error('Required release must raise the minimum');
    return value;
}

export function requiresUpdate(current, remote) {
    validateRelease(remote);
    if (!remote.minimum) return false;
    return (
        !current ||
        !hash.test(current.revision) ||
        !Number.isSafeInteger(current.generation) ||
        current.lineage !== remote.lineage ||
        current.generation < remote.minimum.generation ||
        (current.generation === remote.minimum.generation && current.revision !== remote.minimum.revision)
    );
}

export async function fetchRelease(fetcher, url) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 10000);
    try {
        const response = await fetcher(url, { cache: 'no-store', signal: controller.signal, headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`PWA metadata HTTP ${response.status}`);
        return validateRelease(await response.json());
    } finally {
        clearTimeout(timer);
    }
}

// Once an obligation is known, a network error (or a stale response) cannot unlock the UI.
export function createReleaseGate(current) {
    let mandatory = false;
    return {
        accept(remote) {
            validateRelease(remote);
            mandatory ||= requiresUpdate(current, remote);
            return mandatory;
        },
        get mandatory() {
            return mandatory;
        }
    };
}
