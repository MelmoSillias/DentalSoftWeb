let caisseTourMockEnabled = false;
let caisseTourMockState = buildSeedState();

function cloneValue(value) {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
}

function isoDateOffset(daysOffset = 0, hour = 9, minute = 0) {
    const date = new Date();
    date.setHours(hour, minute, 0, 0);
    date.setDate(date.getDate() + daysOffset);
    return date.toISOString();
}

function buildSeedState() {
    return {
        paymentMethods: [
            { id: 1, libelle: 'Especes', type: 'Especes', typeKey: 'cash', family: 'classic', coverageRate: null, actif: true },
            { id: 2, libelle: 'Wave', type: 'Mobile Money', typeKey: 'mobile_money', family: 'classic', coverageRate: null, actif: true },
            { id: 3, libelle: 'Carte bancaire', type: 'Carte', typeKey: 'card', family: 'classic', coverageRate: null, actif: true },
            { id: 4, libelle: 'Assurance Premium', type: 'Assurance', typeKey: 'insurance', family: 'insurance', coverageRate: 80, actif: true },
            { id: 5, libelle: 'IPM Standard', type: 'Assurance', typeKey: 'insurance', family: 'insurance', coverageRate: 50, actif: true }
        ],
        factures: [
            {
                id: 7001,
                date: isoDateOffset(-1, 10, 30),
                patient: { id: 1001, nom: 'Diallo', prenom: 'Aminata' },
                telephone: '+221771001010',
                montant: 45000,
                reste: 45000,
                isRegle: false,
                consultation: 9202
            },
            {
                id: 7002,
                date: isoDateOffset(-2, 12, 10),
                patient: { id: 1002, nom: 'Ndiaye', prenom: 'Ibrahima' },
                telephone: '+221775551212',
                montant: 60000,
                reste: 15000,
                isRegle: false,
                consultation: 9201
            },
            {
                id: 7003,
                date: isoDateOffset(0, 8, 45),
                patient: { id: 1003, nom: 'Sarr', prenom: 'Mariam' },
                telephone: '+221781231234',
                montant: 0,
                reste: 0,
                isRegle: false,
                consultation: 9203
            },
            {
                id: 7004,
                date: isoDateOffset(-4, 15, 20),
                patient: { id: 1004, nom: 'Ba', prenom: 'Cheikh' },
                telephone: '+221770909090',
                montant: 30000,
                reste: 0,
                isRegle: true,
                consultation: 9204
            }
        ],
        factureDetails: {
            7001: {
                id: 7001,
                date: '03/04/2026',
                patient: { nom: 'Diallo', prenom: 'Aminata' },
                montant: 45000,
                reste: 45000,
                contenus: [
                    { designation: 'Consultation', qte: 1, montant: 5000, total: 5000 },
                    { designation: 'Composite 16', qte: 1, montant: 40000, total: 40000 }
                ]
            },
            7002: {
                id: 7002,
                date: '02/04/2026',
                patient: { nom: 'Ndiaye', prenom: 'Ibrahima' },
                montant: 60000,
                reste: 15000,
                contenus: [
                    { designation: 'Traitement canal', qte: 1, montant: 45000, total: 45000 },
                    { designation: 'Radio', qte: 3, montant: 5000, total: 15000 }
                ]
            },
            7003: {
                id: 7003,
                date: '03/04/2026',
                patient: { nom: 'Sarr', prenom: 'Mariam' },
                montant: 0,
                reste: 0,
                contenus: []
            },
            7004: {
                id: 7004,
                date: '30/03/2026',
                patient: { nom: 'Ba', prenom: 'Cheikh' },
                montant: 30000,
                reste: 0,
                contenus: [
                    { designation: 'Extraction', qte: 1, montant: 30000, total: 30000 }
                ]
            }
        },
        factureLines: {
            9202: [
                { dent: '16', type: 'Composite', description: 'Restauration', prix: 40000, quantite: 1 },
                { dent: '', type: 'Consultation', description: 'Consultation initiale', prix: 5000, quantite: 1 }
            ],
            9203: [],
            9201: [
                { dent: '46', type: 'Traitement de canal', description: 'Suite de traitement', prix: 45000, quantite: 1 },
                { dent: '', type: 'Radio', description: 'Controle', prix: 5000, quantite: 3 }
            ]
        },
        payments: [
            {
                pId: 8801,
                factureId: 7002,
                patient: 'Ibrahima Ndiaye',
                telephone: '+221775551212',
                date: isoDateOffset(-1, 14, 15),
                montant: 45000,
                mode: 'Wave',
                type: 'facture'
            },
            {
                pId: 8802,
                factureId: 7004,
                patient: 'Cheikh Ba',
                telephone: '+221770909090',
                date: isoDateOffset(-4, 16, 30),
                montant: 30000,
                mode: 'Especes',
                type: 'receipt'
            }
        ],
        nextPaymentId: 9900
    };
}

function matchesRange(value, start, end) {
    const date = String(value || '').slice(0, 10);
    if (start && date < start) return false;
    if (end && date > end) return false;
    return true;
}

function findFacture(factureId) {
    return caisseTourMockState.factures.find((item) => Number(item.id) === Number(factureId)) || null;
}

export function isCaisseTourMockEnabled() {
    return caisseTourMockEnabled;
}

export function activateCaisseTourMock() {
    caisseTourMockState = buildSeedState();
    caisseTourMockEnabled = true;
    return cloneValue(caisseTourMockState);
}

export function resetCaisseTourMockData() {
    caisseTourMockState = buildSeedState();
    return cloneValue(caisseTourMockState);
}

export function deactivateCaisseTourMock() {
    caisseTourMockEnabled = false;
    caisseTourMockState = buildSeedState();
}

export function fetchFacturesTourMock({ start, end, factureType = 'all', unpaidOnly = false } = {}) {
    const type = factureType || (unpaidOnly ? 'impaye' : 'all');
    let list = caisseTourMockState.factures;

    if (type !== 'impaye_toutes') {
        list = list.filter((item) => matchesRange(item.date, start, end));
    }

    if (type === 'impaye' || type === 'impaye_toutes') {
        list = list.filter((item) => Number(item.reste) > 0);
    }

    return cloneValue(list);
}

export function fetchPaymentsTourMock({ start, end } = {}) {
    const list = caisseTourMockState.payments.filter((item) => matchesRange(item.date, start, end));
    return cloneValue(list);
}

export function fetchPaymentMethodsTourMock() {
    return cloneValue(caisseTourMockState.paymentMethods);
}

export function payFactureTourMock(factureId, payload = {}) {
    const facture = findFacture(factureId);
    if (!facture) throw new Error('Facture introuvable');

    const patientAmount = Number(payload.patient_amount ?? payload.montant ?? 0);
    const insuranceAmount = Number(payload.insurance_amount ?? 0);
    const totalPaid = patientAmount + insuranceAmount;

    facture.reste = Math.max(0, Number(facture.reste || 0) - totalPaid);
    facture.isRegle = facture.reste === 0;

    const paymentId = caisseTourMockState.nextPaymentId++;
    caisseTourMockState.payments.unshift({
        pId: paymentId,
        factureId: facture.id,
        patient: `${facture.patient?.nom || ''} ${facture.patient?.prenom || ''}`.trim(),
        telephone: facture.telephone,
        date: `${payload.date || new Date().toISOString().slice(0, 10)}T${payload.time || '09:00'}:00`,
        montant: totalPaid,
        mode: payload.insurance_enabled ? 'Paiement + Assurance' : resolvePaymentMethodLabel(payload.modeId),
        type: 'facture'
    });

    return { success: true, paiement_id: paymentId };
}

function resolvePaymentMethodLabel(modeId) {
    return caisseTourMockState.paymentMethods.find((item) => Number(item.id) === Number(modeId))?.libelle || 'Paiement';
}

export function validateEmptyFactureTourMock(factureId) {
    const facture = findFacture(factureId);
    if (!facture) throw new Error('Facture introuvable');
    facture.isRegle = true;
    facture.reste = 0;
    return { success: true };
}

export function fetchFactureLinesTourMock(consultationId) {
    const lines = cloneValue(caisseTourMockState.factureLines[consultationId] || []);
    return {
        lines,
        date: '2026-07-04',
        time: '10:30',
        modifiable: true
    };
}

export function updateFactureLinesTourMock(consultationId, lignes = [], options = {}) {
    const lines = Array.isArray(lignes) ? lignes : lignes?.lines ?? lignes?.lignes ?? [];
    caisseTourMockState.factureLines[consultationId] = cloneValue(lines);
    return { success: true, date: options?.date ?? null, time: options?.time ?? null };
}

export function fetchFactureDetailTourMock(factureId) {
    return cloneValue(caisseTourMockState.factureDetails[factureId] || null);
}
