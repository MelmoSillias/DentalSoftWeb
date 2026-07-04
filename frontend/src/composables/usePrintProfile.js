import cabinetConfig from '@/cabinetConfig';

function parsePhones(raw) {
    if (!raw || typeof raw !== 'string') return [];
    return raw
        .split(/\s*\/\s*|\s*\|\s*/)
        .map((part) => part.trim())
        .filter(Boolean);
}

function normalizePrintProfile(config) {
    const profile = config.printProfile || {};
    const phones = Array.isArray(profile.phones) && profile.phones.length
        ? profile.phones.filter(Boolean)
        : parsePhones(config.cabinetPhone);

    const addressLines = Array.isArray(profile.addressLines)
        ? profile.addressLines.filter((line) => typeof line === 'string' && line.trim() !== '')
        : [];

    return {
        name: profile.name || config.reportCabinetName || config.displayName || 'Cabinet dentaire',
        addressLines,
        phones,
        email: typeof profile.email === 'string' ? profile.email.trim() : '',
        website: typeof profile.website === 'string' ? profile.website.trim() : ''
    };
}

export function usePrintProfile() {
    const profile = normalizePrintProfile(cabinetConfig);

    return {
        profile,
        logoSrc: '/logo.png'
    };
}
