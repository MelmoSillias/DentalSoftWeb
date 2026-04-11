const DEVICE_ID_KEY = 'device_id';

function randomId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }

    return `dev_${Date.now()}_${Math.random().toString(36).slice(2, 12)}`;
}

export function getOrCreateDeviceId() {
    const existing = localStorage.getItem(DEVICE_ID_KEY);
    if (existing) {
        return existing;
    }

    const created = randomId();
    localStorage.setItem(DEVICE_ID_KEY, created);
    return created;
}

export function getDeviceMetadata() {
    const ua = navigator.userAgent || '';
    const platform = navigator.platform || '';

    const isMobile = /Mobi|Android/i.test(ua);
    const isTablet = /Tablet|iPad/i.test(ua);

    const type = isTablet ? 'tablet' : isMobile ? 'mobile' : 'desktop';
    const name = `${platform || 'Unknown'} - ${ua.slice(0, 48)}`;

    return {
        id: getOrCreateDeviceId(),
        type,
        name,
    };
}
