export const formatSalaryTypeLabel = (type) => {
    const map = {
        fixe: 'FIXE',
        pourcentage: 'POURCENTAGE',
        non_defini: 'NON DÉFINI'
    };
    return map[String(type || '').toLowerCase()] || String(type || '—').toUpperCase();
};

export const formatPrimeTypeLabel = (type) => {
    const map = {
        aucune: 'AUCUNE',
        fixe: 'FIXE',
        actes: 'ACTES'
    };
    return map[String(type || '').toLowerCase()] || String(type || '—').toUpperCase();
};

export const formatFrequenceLabel = (value) => (value === 'journalier' ? 'JOURNALIER' : 'MENSUEL');
