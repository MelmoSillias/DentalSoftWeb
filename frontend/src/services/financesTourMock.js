let financesTourMockEnabled = false;
let financesTourMockState = buildSeedState();

function cloneValue(value) {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
}

function buildSeedState() {
    const year = 2026;
    const paymentMethods = [
        { id: 801, libelle: 'Espèces', type: 'Espèces', typeKey: 'cash', family: 'classic', coverageRate: null, actif: true, notes: 'Compte principal caisse' },
        { id: 802, libelle: 'Wave', type: 'Mobile Money', typeKey: 'mobile_money', family: 'classic', coverageRate: null, actif: true, notes: 'Encaissements mobiles' },
        { id: 803, libelle: 'Virement bancaire', type: 'Virement bancaire', typeKey: 'bank_transfer', family: 'classic', coverageRate: null, actif: true, notes: 'Règlements fournisseurs' },
        { id: 804, libelle: 'IPM Santé Plus', type: 'Assurance', typeKey: 'insurance', family: 'insurance', coverageRate: 80, actif: true, notes: 'Assurance partenaire' },
        { id: 805, libelle: 'Mutuelle Entreprise', type: 'Assurance', typeKey: 'insurance', family: 'insurance', coverageRate: 60, actif: false, notes: 'En attente de réactivation' }
    ];

    const transactions = [
        { id: 901, type: 'Entrée', montant: 45000, dateTransaction: '2026-04-02T09:15:00', description: 'Encaissement consultation cabinet 1', modeDePaiement: paymentMethods[0], validationStatus: 'validated' },
        { id: 902, type: 'Sortie', montant: 18000, dateTransaction: '2026-04-02T12:10:00', description: 'Achat consommables urgence', modeDePaiement: paymentMethods[2], validationStatus: 'validated' },
        { id: 903, type: 'Entrée', montant: 72500, dateTransaction: '2026-04-03T08:40:00', description: 'Règlement prothèse partiel', modeDePaiement: paymentMethods[1], validationStatus: 'pending' },
        { id: 904, type: 'Entrée', montant: 52000, dateTransaction: '2026-04-03T10:20:00', description: 'Paiement assurance IPM', modeDePaiement: paymentMethods[3], validationStatus: 'validated' },
        { id: 905, type: 'Sortie', montant: 9500, dateTransaction: '2026-04-03T11:45:00', description: 'Petite caisse maintenance', modeDePaiement: paymentMethods[0], validationStatus: 'rejected' }
    ];

    const chartData = {
        year,
        availableYears: [2025, 2026],
        months: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasetsComptes: [
            { label: 'Entrées', data: [210000, 240000, 260000, 169500, 0, 0, 0, 0, 0, 0, 0, 0] },
            { label: 'Dépenses', data: [120000, 145000, 138000, 27500, 0, 0, 0, 0, 0, 0, 0, 0] }
        ],
        barSoldeChart: {
            labels: ['Espèces', 'Wave', 'Virement bancaire', 'IPM Santé Plus'],
            entrees: [98000, 72500, 0, 52000],
            depenses: [9500, 0, 18000, 0],
            soldes: [88500, 72500, -18000, 52000],
            colors: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6']
        },
        evolutionCapital: [90000, 135000, 188000, 195000, 0, 0, 0, 0, 0, 0, 0, 0]
    };

    return {
        chartData,
        paymentMethods,
        transactions,
        nextTransactionId: 950,
        nextModeId: 850
    };
}

export function isFinancesTourMockEnabled() {
    return financesTourMockEnabled;
}

export function activateFinancesTourMock() {
    financesTourMockState = buildSeedState();
    financesTourMockEnabled = true;
    return cloneValue(financesTourMockState);
}

export function resetFinancesTourMockData() {
    financesTourMockState = buildSeedState();
    return cloneValue(financesTourMockState);
}

export function deactivateFinancesTourMock() {
    financesTourMockEnabled = false;
    financesTourMockState = buildSeedState();
}

export function fetchFinancesChartTourMock(year = null) {
    const data = cloneValue(financesTourMockState.chartData);
    if (year) {
        data.year = Number(year);
    }
    return data;
}

export function fetchFinancesPaymentMethodsTourMock() {
    return cloneValue(financesTourMockState.paymentMethods);
}

export function fetchFinancesTransactionsTourMock({ startDate, endDate }) {
    const start = String(startDate || '').slice(0, 10);
    const end = String(endDate || '').slice(0, 10);
    return cloneValue(
        financesTourMockState.transactions.filter((row) => {
            const date = String(row.dateTransaction || row.date || '').slice(0, 10);
            return (!start || date >= start) && (!end || date <= end);
        })
    );
}

export function createFinancesTransactionTourMock(payload = {}) {
    const mode = financesTourMockState.paymentMethods.find((item) => Number(item.id) === Number(payload.modeId)) || null;
    const transaction = {
        id: financesTourMockState.nextTransactionId++,
        type: payload.type === 'exit' ? 'Sortie' : 'Entrée',
        montant: Number(payload.montant || 0),
        dateTransaction: `${payload.date || '2026-04-03'}T09:00:00`,
        description: payload.description || 'Transaction démo',
        modeDePaiement: mode,
        validationStatus: 'pending'
    };
    financesTourMockState.transactions.unshift(transaction);
    return cloneValue(transaction);
}

export function createFinancesPaymentMethodTourMock(payload = {}) {
    const method = {
        id: financesTourMockState.nextModeId++,
        libelle: payload.libelle || 'Nouveau mode démo',
        type: payload.type || 'Autre',
        typeKey: payload.typeKey || 'other',
        family: payload.family || 'classic',
        coverageRate: payload.family === 'insurance' ? Number(payload.coverageRate || 0) : null,
        actif: true,
        notes: payload.notes || ''
    };
    financesTourMockState.paymentMethods.push(method);
    return cloneValue(method);
}

export function updateFinancesPaymentMethodTourMock(id, payload = {}) {
    const index = financesTourMockState.paymentMethods.findIndex((item) => Number(item.id) === Number(id));
    if (index === -1) throw new Error('Mode introuvable');
    financesTourMockState.paymentMethods[index] = {
        ...financesTourMockState.paymentMethods[index],
        ...payload,
        coverageRate: payload.family === 'insurance' || financesTourMockState.paymentMethods[index].family === 'insurance' ? Number(payload.coverageRate ?? financesTourMockState.paymentMethods[index].coverageRate ?? 0) : null
    };
    return cloneValue(financesTourMockState.paymentMethods[index]);
}

export function deleteFinancesPaymentMethodTourMock(id) {
    financesTourMockState.paymentMethods = financesTourMockState.paymentMethods.filter((item) => Number(item.id) !== Number(id));
    return { ok: true };
}

export function toggleFinancesPaymentMethodTourMock(id) {
    const mode = financesTourMockState.paymentMethods.find((item) => Number(item.id) === Number(id));
    if (!mode) throw new Error('Mode introuvable');
    mode.actif = !mode.actif;
    return cloneValue(mode);
}

export function validateFinancesTransactionTourMock(id) {
    const row = financesTourMockState.transactions.find((item) => Number(item.id) === Number(id));
    if (!row) throw new Error('Transaction introuvable');
    row.validationStatus = 'validated';
    return cloneValue(row);
}

export function rejectFinancesTransactionTourMock(id) {
    const row = financesTourMockState.transactions.find((item) => Number(item.id) === Number(id));
    if (!row) throw new Error('Transaction introuvable');
    row.validationStatus = 'rejected';
    return cloneValue(row);
}
