const TYPE_DEFINITIONS = {
    cash: {
        key: 'cash',
        label: 'Espèces',
        family: 'classic',
        aliases: ['cash', 'especes', 'espece', 'especes', 'espèces', 'espece', 'liquide']
    },
    mobile_money: {
        key: 'mobile_money',
        label: 'Mobile Money',
        family: 'classic',
        aliases: ['mobile_money', 'mobile money', 'momo', 'orange money', 'wave', 'moov money']
    },
    cheque: {
        key: 'cheque',
        label: 'Chèque',
        family: 'classic',
        aliases: ['cheque', 'cheques', 'cheque bancaire', 'chèque', 'chèques']
    },
    bank_transfer: {
        key: 'bank_transfer',
        label: 'Virement bancaire',
        family: 'classic',
        aliases: ['bank_transfer', 'bank transfer', 'virement', 'virement bancaire', 'transfert bancaire']
    },
    insurance: {
        key: 'insurance',
        label: 'Assurance',
        family: 'insurance',
        aliases: ['insurance', 'assurance', 'assureur', 'prise en charge']
    },
    other: {
        key: 'other',
        label: 'Autre',
        family: 'classic',
        aliases: ['other', 'autre']
    }
};

const TYPE_ORDER = {
    cash: 1,
    cheque: 2,
    mobile_money: 3,
    bank_transfer: 4,
    insurance: 5,
    other: 6
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
    const candidates = [
        source?.typeKey,
        source?.type_key,
        source?.type,
        source?.libelle,
        source
    ]
        .map(normalizePaymentString)
        .filter(Boolean);

    for (const candidate of candidates) {
        for (const definition of Object.values(TYPE_DEFINITIONS)) {
            if (candidate === definition.key || definition.aliases.includes(candidate)) {
                return definition.key;
            }
        }

        if (candidate.includes('mobile') && candidate.includes('money')) {
            return 'mobile_money';
        }
        if (candidate.includes('virement') || candidate.includes('transfer')) {
            return 'bank_transfer';
        }
        if (candidate.includes('cheque')) {
            return 'cheque';
        }
        if (candidate.includes('assur')) {
            return 'insurance';
        }
        if (candidate.includes('espec') || candidate.includes('cash') || candidate.includes('liquid')) {
            return 'cash';
        }
    }

    return 'other';
};

export const getPaymentMethodDefinition = (source) =>
    TYPE_DEFINITIONS[resolvePaymentMethodTypeKey(source)] || TYPE_DEFINITIONS.other;

export const resolvePaymentMethodFamily = (source) => {
    const explicitFamily = normalizePaymentString(source?.family || source?.familyKey || source?.family_key);
    if (explicitFamily === 'insurance') {
        return 'insurance';
    }
    if (explicitFamily === 'classic') {
        return 'classic';
    }

    return getPaymentMethodDefinition(source).family;
};

export const getPaymentCoverageRate = (source) => {
    const rawValue = source?.coverageRate
        ?? source?.coverage_rate
        ?? source?.defaultCoverageRate
        ?? source?.default_coverage_rate
        ?? source?.tauxPriseEnCharge
        ?? source?.taux_prise_en_charge
        ?? source?.percentage;

    const parsed = Number(rawValue);
    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.min(100, Math.max(0, parsed));
};

export const isInsuranceMethod = (source) => resolvePaymentMethodFamily(source) === 'insurance';

export const isClassicMethod = (source) => resolvePaymentMethodFamily(source) === 'classic';

export const isAutoValidatedMethod = (source) => {
    const typeKey = resolvePaymentMethodTypeKey(source);
    return typeKey === 'cash' || typeKey === 'mobile_money';
};

export const buildPaymentMethodGroups = (methods = []) => {
    const list = Array.isArray(methods) ? methods : [];
    return {
        classics: list.filter(isClassicMethod),
        insurances: list.filter(isInsuranceMethod)
    };
};

export const getDefaultClassicMethod = (methods = []) => {
    const classics = buildPaymentMethodGroups(methods).classics.filter((method) => method?.actif !== false);
    if (!classics.length) {
        return null;
    }

    return classics.find((method) => resolvePaymentMethodTypeKey(method) === 'cash') || classics[0];
};

export const formatCoverageRate = (value) => `${getPaymentCoverageRate({ coverageRate: value }).toLocaleString('fr-FR')} %`;

export const sortPaymentMethods = (methods = []) => {
    const list = Array.isArray(methods) ? [...methods] : [];
    return list.sort((left, right) => {
        const leftFamily = resolvePaymentMethodFamily(left) === 'insurance' ? 2 : 1;
        const rightFamily = resolvePaymentMethodFamily(right) === 'insurance' ? 2 : 1;
        if (leftFamily !== rightFamily) {
            return leftFamily - rightFamily;
        }

        const leftTypeOrder = TYPE_ORDER[resolvePaymentMethodTypeKey(left)] || TYPE_ORDER.other;
        const rightTypeOrder = TYPE_ORDER[resolvePaymentMethodTypeKey(right)] || TYPE_ORDER.other;
        if (leftTypeOrder !== rightTypeOrder) {
            return leftTypeOrder - rightTypeOrder;
        }

        return String(left?.libelle || '').localeCompare(String(right?.libelle || ''), 'fr');
    });
};