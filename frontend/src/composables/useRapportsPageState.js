const STORAGE_KEYS = {
    admin: 'rapports.state.admin',
    medecin: 'rapports.state.medecin',
    reception: 'rapports.state.reception'
};

const TTL_MS = 7 * 24 * 60 * 60 * 1000;

function parseIsoDate(iso) {
    if (!iso || typeof iso !== 'string') return null;
    const [year, month, day] = iso.split('-').map(Number);
    if (!year || !month || !day) return null;
    const date = new Date(year, month - 1, day);
    return Number.isNaN(date.getTime()) ? null : date;
}

function toIsoDate(value) {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getStorageKey(role) {
    return STORAGE_KEYS[role] || `rapports.state.${role}`;
}

function readStoredState(role) {
    try {
        const raw = localStorage.getItem(getStorageKey(role));
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') return null;
        if (typeof parsed.savedAt !== 'number' || Date.now() - parsed.savedAt > TTL_MS) {
            localStorage.removeItem(getStorageKey(role));
            return null;
        }
        return parsed;
    } catch {
        return null;
    }
}

function normalizeTab(tab, allowedTabs, defaultTab) {
    const migrated = tab === 'clinical' ? 'acts' : tab;
    if (allowedTabs.includes(migrated)) return migrated;
    return defaultTab;
}

export function getDefaultMonthRange() {
    const startOfMonth = new Date();
    startOfMonth.setDate(1);
    const endOfMonth = new Date();
    return [startOfMonth, endOfMonth];
}

export function loadRapportsPageState(role, { allowedTabs, defaultTab }) {
    const stored = readStoredState(role);
    const tab = normalizeTab(stored?.tab, allowedTabs, defaultTab);

    if (role === 'reception') {
        const date = parseIsoDate(stored?.period?.date) || new Date();
        return { tab, period: { date } };
    }

    const defaultRange = getDefaultMonthRange();
    const from = parseIsoDate(stored?.period?.from) || defaultRange[0];
    const to = parseIsoDate(stored?.period?.to) || defaultRange[1];
    return { tab, period: { range: [from, to] } };
}

export function saveRapportsPageState(role, { tab, period }) {
    if (!tab) return;

    const payload = {
        tab,
        savedAt: Date.now()
    };

    if (role === 'reception') {
        payload.period = { date: toIsoDate(period?.date) };
    } else {
        const [from, to] = period?.range || [];
        payload.period = {
            from: toIsoDate(from),
            to: toIsoDate(to)
        };
    }

    localStorage.setItem(getStorageKey(role), JSON.stringify(payload));
}

export const RAPPORTS_ADMIN_TABS = ['overview', 'activity', 'finances', 'acts', 'doctors'];
export const RAPPORTS_MEDECIN_TABS = ['summary', 'activity', 'acts', 'profile'];
export const RAPPORTS_RECEPTION_TABS = ['daily', 'doctors'];
