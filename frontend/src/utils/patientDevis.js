export const listFicheDevis = (fiche) => {
    const raw = fiche?.devis;
    if (Array.isArray(raw)) return raw;
    if (raw && Array.isArray(raw.devisList)) return raw.devisList;
    if (raw && typeof raw === 'object' && (raw.id || raw.description || raw.services?.length || raw.contenus?.length)) {
        return [raw];
    }
    return [];
};

export const countPatientDevis = (fiches) => (fiches || []).reduce((total, fiche) => total + listFicheDevis(fiche).length, 0);

const serviceLines = (entry) => {
    if (Array.isArray(entry?.services)) return entry.services;
    if (Array.isArray(entry?.contenus)) return entry.contenus;
    return [];
};

export const devisLineTotal = (entry) =>
    serviceLines(entry).reduce((sum, line) => sum + (Number(line?.qte) || 0) * (Number(line?.montant) || 0), 0);

export const formatDevisDate = (value) => {
    if (!value) return '—';
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
        const [year, month, day] = value.slice(0, 10).split('-');
        return `${day}/${month}/${year}`;
    }
    const parsed = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsed.getTime())) return '—';
    return parsed.toLocaleDateString('fr-FR');
};

export const toApiDevis = (entry, type) => ({
    id: Number(entry?.id) || null,
    type,
    date: entry?.date || null,
    description: entry?.description || '',
    contenus: serviceLines(entry).map((line) => ({
        designation: line?.designation ?? '',
        qte: Number(line?.qte) || 1,
        montant: Number(line?.montant) || 0
    }))
});

export const nextDevisType = (entries) => {
    const used = new Set(entries.map((entry) => Number(entry?.type)).filter((type) => Number.isFinite(type)));
    let type = 0;
    while (used.has(type)) type += 1;
    return type;
};

export const todayIsoDate = () => {
    const date = new Date();
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

export const emptyDevisEntry = () => ({
    id: null,
    type: null,
    date: todayIsoDate(),
    description: '',
    services: []
});

export const normalizeDevisServices = (entry) =>
    serviceLines(entry).map((line) => ({
        designation: line?.designation || '',
        qte: Number(line?.qte) || 1,
        montant: Number(line?.montant) || 0
    }));
