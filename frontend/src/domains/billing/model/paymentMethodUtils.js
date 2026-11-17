const TYPE_DEFINITIONS = {
    cash: {
        key: 'cash',
        label: 'Espèces',
        family: 'classic',
        aliases: ['cash', 'especes', 'espece', 'especes', 'espèces', 'espece', 'liquide']
    },
    mobilemoney: {
        key: 'mobilemoney',
        label: 'Mobile Money',
        aliases: ['mobilemoney', 'mobile_money', 'mobile money', 'momo', 'orange money', 'wave', 'moov money']
    },
    transfer: {
        key: 'transfer',
        label: 'Virement bancaire',
        aliases: ['transfer', 'banktransfer', 'bank transfer', 'virement', 'virement bancaire', 'transfert bancaire']
    },
    card: {
        key: 'card',
        label: 'Carte bancaire',
        aliases: ['card', 'carte', 'carte bancaire', 'cb', 'visa', 'mastercard']
    }
};

const TYPE_ORDER = {
    cash: 1,
    transfer: 2,
    card: 3,
    mobilemoney: 4
};

export const normalizePaymentString = (value) =>
    String(value ?? '')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '');

export const resolvePaymentMethodTypeKey = (source) => {
    const candidates = [source?.type, source?.libelle, source].map(normalizePaymentString).filter(Boolean);

    for (const candidate of candidates) {
        for (const definition of Object.values(TYPE_DEFINITIONS)) {
            if (candidate === definition.key || definition.aliases.includes(candidate)) {
                return definition.key;
            }
        }

        if (candidate.includes('mobile') && candidate.includes('money')) {
            return 'mobilemoney';
        }
        if (candidate.includes('virement') || candidate.includes('transfer')) {
            return 'transfer';
        }
        if (candidate.includes('carte') || candidate.includes('card') || candidate.includes('cb')) {
            return 'card';
        }
        if (candidate.includes('espec') || candidate.includes('cash') || candidate.includes('liquid')) {
            return 'cash';
        }
    }

    return 'cash';
};

export const getPaymentMethodDefinition = (source) => TYPE_DEFINITIONS[resolvePaymentMethodTypeKey(source)] || TYPE_DEFINITIONS.cash;

export const getPaymentCoverageRate = (source) => {
    const rawValue = source?.coverageRate ?? source?.coverage_rate ?? source?.defaultCoverageRate ?? source?.default_coverage_rate ?? source?.tauxPriseEnCharge ?? source?.taux_prise_en_charge ?? source?.percentage;

    const parsed = Number(rawValue);
    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.min(100, Math.max(0, parsed));
};

export const isAutoValidatedMethod = (source) => {
    const typeKey = resolvePaymentMethodTypeKey(source);
    return typeKey === 'cash' || typeKey === 'mobilemoney';
};

export const getDefaultClassicMethod = (methods = []) => {
    const classics = (Array.isArray(methods) ? methods : []).filter((method) => method?.actif !== false);
    if (!classics.length) {
        return null;
    }

    return classics.find((method) => resolvePaymentMethodTypeKey(method) === 'cash') || classics[0];
};

export const formatCoverageRate = (value) => `${getPaymentCoverageRate({ coverageRate: value }).toLocaleString('fr-FR')} %`;

export const sortPaymentMethods = (methods = []) => {
    const list = Array.isArray(methods) ? [...methods] : [];
    return list.sort((left, right) => {
        const leftTypeOrder = TYPE_ORDER[resolvePaymentMethodTypeKey(left)] || 999;
        const rightTypeOrder = TYPE_ORDER[resolvePaymentMethodTypeKey(right)] || 999;
        if (leftTypeOrder !== rightTypeOrder) {
            return leftTypeOrder - rightTypeOrder;
        }

        return String(left?.libelle || '').localeCompare(String(right?.libelle || ''), 'fr');
    });
};
