const STATIC_MEDECINS = [
    {
        id: 1,
        nom: 'Fall',
        prenom: 'Aissatou',
        label: 'Aissatou Fall',
        fullName: 'Dr Aissatou Fall'
    },
    {
        id: 2,
        nom: 'Seck',
        prenom: 'Mamadou',
        label: 'Mamadou Seck',
        fullName: 'Dr Mamadou Seck'
    },
    {
        id: 3,
        nom: 'Ndiaye',
        prenom: 'Khadija',
        label: 'Khadija Ndiaye',
        fullName: 'Dr Khadija Ndiaye'
    }
];

const STATIC_INFIRMIERS = [
    { id: 11, nom: 'Ba', prenom: 'Rokhaya', label: 'Rokhaya Ba' },
    { id: 12, nom: 'Diop', prenom: 'Malick', label: 'Malick Diop' }
];

const STATIC_SALLES = [
    { id: 21, nom: 'Salle 1', label: 'Salle 1' },
    { id: 22, nom: 'Salle 2', label: 'Salle 2' }
];

let consultationsTourMockEnabled = false;
let consultationsTourMockScenario = 'queue-mixed';
let consultationsTourMockState = buildSeedState('queue-mixed');

function cloneValue(value) {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
}

function hoursAgo(hours) {
    return new Date(Date.now() - hours * 60 * 60 * 1000).toISOString();
}

function buildStaticDailyConsultations() {
    return [
        {
            id: 9201,
            patient: { id: 1002, nom: 'Ndiaye', prenom: 'Ibrahima', telephone: '+221775551212', createdAt: hoursAgo(48) },
            patientName: 'Ibrahima Ndiaye',
            patientCreatedAt: hoursAgo(48),
            medecin: 'Dr Mamadou Seck',
            createdAt: hoursAgo(5.2),
            motif: 'Derniere fiche a reprendre',
            statut: 'EN_ATTENTE',
            urgence: false,
            hasFiche: true,
            ficheId: null,
            lastFicheId: 8101,
            state: 0,
            factState: 0
        },
        {
            id: 9202,
            patient: { id: 1001, nom: 'Diallo', prenom: 'Aminata', telephone: '+221771001010', createdAt: hoursAgo(72) },
            patientName: 'Aminata Diallo',
            patientCreatedAt: hoursAgo(72),
            medecin: 'Dr Aissatou Fall',
            createdAt: hoursAgo(2.4),
            motif: 'Fiche deja liee a poursuivre',
            statut: 'EN_COURS',
            urgence: true,
            hasFiche: true,
            ficheId: 8201,
            lastFicheId: 8201,
            state: 0,
            factState: 0
        },
        {
            id: 9203,
            patient: { id: 1003, nom: 'Sarr', prenom: 'Mariam', telephone: '+221781231234', createdAt: hoursAgo(0.5) },
            patientName: 'Mariam Sarr',
            patientCreatedAt: hoursAgo(0.5),
            medecin: 'Dr Khadija Ndiaye',
            createdAt: hoursAgo(1.1),
            motif: 'Nouvelle prise en charge',
            statut: 'EN_ATTENTE',
            urgence: false,
            hasFiche: false,
            ficheId: null,
            lastFicheId: null,
            state: 0,
            factState: 0
        },
        {
            id: 9204,
            patient: { id: 1001, nom: 'Diallo', prenom: 'Aminata', telephone: '+221771001010', createdAt: hoursAgo(72) },
            patientName: 'Aminata Diallo',
            patientCreatedAt: hoursAgo(72),
            medecin: 'Dr Aissatou Fall',
            createdAt: hoursAgo(8.7),
            motif: 'Consultation cloturee du jour',
            statut: 'TERMINEE',
            urgence: false,
            hasFiche: true,
            ficheId: 8301,
            lastFicheId: 8301,
            state: 1,
            factState: 0,
            factModifiable: true
        }
    ];
}

function buildStaticConsultationInvoices() {
    return {
        9201: [
            { id: 1, dent: '46', type: 'Consultation', prix: 5000, quantite: 1, description: 'Reprise clinique' },
            { id: 2, dent: '46', type: 'Radio', prix: 5000, quantite: 1, description: 'Controle radiographique' }
        ],
        9202: [{ id: 1, dent: '16', type: 'Détartrage', prix: 10000, quantite: 1, description: 'Suivi de traitement' }],
        9203: [{ id: 1, dent: '', type: 'Consultation', prix: 5000, quantite: 1, description: 'Premiere consultation' }],
        9204: [
            { id: 1, dent: '24', type: 'Composite', prix: 15000, quantite: 1, description: 'Restauration definitive' },
            { id: 2, dent: '24', type: 'Radio', prix: 5000, quantite: 1, description: 'Controle final' }
        ]
    };
}

function normalizeConsultationsScenario(scenario) {
    const normalized = String(scenario || '').toLowerCase();
    if (normalized === 'queue-empty') return 'queue-empty';
    if (normalized === 'fiche-draft') return 'fiche-draft';
    return 'queue-mixed';
}

function buildSeedState(scenario = 'queue-mixed') {
    const normalizedScenario = normalizeConsultationsScenario(scenario);
    const dayConsultations = normalizedScenario === 'queue-empty' ? [] : buildStaticDailyConsultations();
    const pendingConsultations = dayConsultations.filter((consultation) => Number(consultation.state) !== 1);

    return {
        dayConsultations,
        pendingConsultations,
        consultationDetails:
            normalizedScenario === 'queue-empty'
                ? {}
                : {
                      9201: {
                          id: 9201,
                          date: hoursAgo(5.2),
                          patient: 'Ibrahima Ndiaye',
                          type: 'controle',
                          medecin: 'Dr Mamadou Seck',
                          medecinId: 2,
                          infirmierId: 11,
                          salleId: 22,
                          noteSeance: 'Controle post urgence avec reprise de la fiche precedente.',
                          actes: [
                              { dent: '46', type: 'Consultation', description: 'Reprise clinique', quantite: 1, prix: 5000 },
                              { dent: '46', type: 'Radio', description: 'Controle radiographique', quantite: 1, prix: 5000 }
                          ]
                      },
                      9202: {
                          id: 9202,
                          date: hoursAgo(2.4),
                          patient: 'Aminata Diallo',
                          type: 'traitement',
                          medecin: 'Dr Aissatou Fall',
                          medecinId: 1,
                          infirmierId: 12,
                          salleId: 21,
                          noteSeance: 'Traitement deja entame avec fiche liee.',
                          actes: [{ dent: '16', type: 'Détartrage', description: 'Suivi de traitement', quantite: 1, prix: 10000 }]
                      },
                      9203: {
                          id: 9203,
                          date: hoursAgo(1.1),
                          patient: 'Mariam Sarr',
                          type: 'initiale',
                          medecin: 'Dr Khadija Ndiaye',
                          medecinId: 3,
                          infirmierId: 11,
                          salleId: 21,
                          noteSeance: 'Premiere consultation, aucune fiche precedente.',
                          actes: []
                      },
                      9204: {
                          id: 9204,
                          date: hoursAgo(8.7),
                          patient: 'Aminata Diallo',
                          type: 'controle',
                          medecin: 'Dr Aissatou Fall',
                          medecinId: 1,
                          infirmierId: 12,
                          salleId: 21,
                          noteSeance: 'Consultation cloturee et facture prete a etre revue.',
                          actes: [
                              { dent: '24', type: 'Composite', description: 'Restauration definitive', quantite: 1, prix: 15000 },
                              { dent: '24', type: 'Radio', description: 'Controle final', quantite: 1, prix: 5000 }
                          ]
                      }
                  },
        consultationInvoices: normalizedScenario === 'queue-empty' ? {} : buildStaticConsultationInvoices(),
        activeFicheLinks:
            normalizedScenario === 'queue-empty'
                ? {}
                : {
                      9201: 8101,
                      9202: 8201,
                      9203: null,
                      9204: 8301
                  },
        nextConsultationId: 9300,
        nextFicheId: 8400,
        draftFiche:
            normalizedScenario === 'fiche-draft'
                ? {
                      id: 8401,
                      consultationId: 9202,
                      entretien: { motifConsultation: 'Douleur persistante molaire 16.' },
                      examens: { observations: 'Sensibilite au froid.' },
                      status: 'draft'
                  }
                : null
    };
}

function findPendingConsultation(consultationId) {
    return consultationsTourMockState.pendingConsultations.find((consultation) => Number(consultation.id) === Number(consultationId)) || null;
}

function resolveMedecinLabel(medecinId) {
    return STATIC_MEDECINS.find((item) => Number(item.id) === Number(medecinId))?.fullName || 'Médecin';
}

export function isConsultationsTourMockEnabled() {
    return consultationsTourMockEnabled;
}

export function resolveConsultationsTourMockScenario(taskId = 'overview', variantId = null, fallbackScenario = 'queue-mixed') {
    const taskKey = String(taskId || 'overview').toLowerCase();
    const variantKey = String(variantId || '').toLowerCase();

    if (taskKey === 'empty-queue') return 'queue-empty';
    if (taskKey === 'fill-entretien' || taskKey === 'fill-examens' || taskKey === 'manage-ordonnance' || taskKey === 'treatment-plan' || taskKey === 'close-fiche') {
        return 'fiche-draft';
    }

    return normalizeConsultationsScenario(fallbackScenario);
}

export function activateConsultationsTourMock(scenario = 'queue-mixed') {
    consultationsTourMockScenario = normalizeConsultationsScenario(scenario);
    consultationsTourMockState = buildSeedState(consultationsTourMockScenario);
    consultationsTourMockEnabled = true;
    return cloneValue(consultationsTourMockState);
}

export function resetConsultationsTourMockData(scenario = consultationsTourMockScenario) {
    consultationsTourMockScenario = normalizeConsultationsScenario(scenario);
    consultationsTourMockState = buildSeedState(consultationsTourMockScenario);
    return cloneValue(consultationsTourMockState);
}

export function deactivateConsultationsTourMock() {
    consultationsTourMockEnabled = false;
    consultationsTourMockScenario = 'queue-mixed';
    consultationsTourMockState = buildSeedState('queue-mixed');
}

export function fetchPendingConsultationsTourMock() {
    return cloneValue(consultationsTourMockState.pendingConsultations);
}

export function cancelConsultationTourMock(consultationId) {
    consultationsTourMockState.pendingConsultations = consultationsTourMockState.pendingConsultations.filter((consultation) => Number(consultation.id) !== Number(consultationId));
    consultationsTourMockState.dayConsultations = consultationsTourMockState.dayConsultations.filter((consultation) => Number(consultation.id) !== Number(consultationId));
    delete consultationsTourMockState.consultationDetails[consultationId];
    delete consultationsTourMockState.consultationInvoices[consultationId];
    delete consultationsTourMockState.activeFicheLinks[consultationId];
    return { success: true };
}

export function fetchConsultationsByDateTourMock(date) {
    const targetDate = String(date || '').trim();
    const consultations = consultationsTourMockState.dayConsultations || [];

    if (!targetDate) {
        return cloneValue(consultations);
    }

    return cloneValue(consultations.filter((consultation) => String(consultation.createdAt || '').slice(0, 10) === targetDate));
}

export function fetchConsultationDetailsTourMock(consultationId) {
    const details = consultationsTourMockState.consultationDetails[consultationId] || null;
    if (!details) {
        throw new Error('Consultation introuvable');
    }
    return cloneValue(details);
}

export function verifyConsultationMedecinPasswordTourMock() {
    return true;
}

export function setConsultationFicheTourMock(consultationId, ficheId = null, options = {}) {
    const consultation = findPendingConsultation(consultationId);
    if (!consultation) {
        throw new Error('Consultation introuvable');
    }

    const createNew = Boolean(options?.createNew);
    const allowDuplicate = Boolean(options?.allowDuplicate);
    let resolvedFicheId = ficheId;
    if (createNew && allowDuplicate) {
        resolvedFicheId = consultationsTourMockState.nextFicheId++;
    } else if (createNew) {
        resolvedFicheId = consultation.ficheId || consultation.lastFicheId || consultationsTourMockState.nextFicheId++;
    } else if (!resolvedFicheId) {
        resolvedFicheId = consultation.ficheId || consultation.lastFicheId || consultationsTourMockState.nextFicheId++;
    }

    consultationsTourMockState.activeFicheLinks[consultationId] = resolvedFicheId;
    return {
        ficheId: resolvedFicheId,
        created: createNew || (!ficheId && !consultation.ficheId && !consultation.lastFicheId)
    };
}

export function saveConsultationTourMock(ficheId, consultationId, payload = {}) {
    const consultation = findPendingConsultation(consultationId);
    if (!consultation) {
        throw new Error('Consultation introuvable');
    }

    const medecinLabel = resolveMedecinLabel(payload.medecinId);
    consultation.medecin = medecinLabel;
    consultation.ficheId = consultation.ficheId || ficheId || consultationsTourMockState.activeFicheLinks[consultationId] || null;
    consultation.hasFiche = true;

    consultationsTourMockState.consultationDetails[consultationId] = {
        ...consultationsTourMockState.consultationDetails[consultationId],
        medecin: medecinLabel,
        medecinId: payload.medecinId ?? consultationsTourMockState.consultationDetails[consultationId]?.medecinId ?? null,
        infirmierId: Array.isArray(payload.infirmierId) ? (payload.infirmierId[0] ?? null) : (payload.infirmierId ?? consultationsTourMockState.consultationDetails[consultationId]?.infirmierId ?? null),
        salleId: payload.salleId ?? consultationsTourMockState.consultationDetails[consultationId]?.salleId ?? null,
        noteSeance: payload.noteSeance ?? consultationsTourMockState.consultationDetails[consultationId]?.noteSeance ?? '',
        actes: Array.isArray(payload.actes) ? cloneValue(payload.actes) : []
    };

    return { success: true };
}

export function closeConsultationTourMock(ficheId, consultationId) {
    const consultation = findPendingConsultation(consultationId);
    if (!consultation) {
        throw new Error('Consultation introuvable');
    }

    consultation.state = 1;
    consultation.statut = 'TERMINEE';
    consultationsTourMockState.pendingConsultations = consultationsTourMockState.pendingConsultations.filter((item) => Number(item.id) !== Number(consultationId));
    return { success: true };
}

export function fetchConsultationInvoiceTourMock(consultationId) {
    const lines = cloneValue(consultationsTourMockState.consultationInvoices?.[consultationId] || []);
    return {
        lines,
        date: '2026-07-04',
        time: '10:30',
        modifiable: true
    };
}

export function updateConsultationInvoiceTourMock(consultationId, lignes = [], options = {}) {
    const lines = Array.isArray(lignes) ? lignes : (lignes?.lines ?? lignes?.lignes ?? []);
    consultationsTourMockState.consultationInvoices[consultationId] = (lines || []).map((line, index) => ({
        id: line.id ?? index + 1,
        dent: line.dent ?? '',
        type: line.type ?? '',
        prix: Number(line.prix ?? 0),
        quantite: Number(line.quantite ?? 1),
        description: line.description ?? ''
    }));
    return { success: true, date: options?.date ?? null, time: options?.time ?? null };
}

export function fetchTourMockMedecins() {
    return cloneValue(STATIC_MEDECINS);
}

export function fetchTourMockInfirmiers() {
    return cloneValue(STATIC_INFIRMIERS);
}

export function fetchTourMockSalles() {
    return cloneValue(STATIC_SALLES);
}

export function registerCreatedPendingConsultationTourMock(patient, payload = {}, result = {}) {
    if (!consultationsTourMockEnabled || !patient?.id) {
        return null;
    }

    const consultationId = result?.consultation_id ?? consultationsTourMockState.nextConsultationId++;
    const createdAt = payload?.consultation_date && payload?.consultation_time ? `${payload.consultation_date}T${payload.consultation_time}:00` : new Date().toISOString();
    const medecinId = Number(payload?.medecin_id || 1);
    const consultation = {
        id: consultationId,
        patient: {
            id: patient.id,
            nom: patient.nom,
            prenom: patient.prenom,
            telephone: patient.telephone || ''
        },
        patientName: patient.fullname || `${patient.prenom ?? ''} ${patient.nom ?? ''}`.trim(),
        medecin: resolveMedecinLabel(medecinId),
        createdAt,
        motif: payload?.notes || payload?.motif || 'Consultation créée depuis le tour',
        statut: 'Nouvelle prise en charge',
        hasFiche: false,
        ficheId: null,
        lastFicheId: null,
        state: 0,
        factState: 0
    };

    consultationsTourMockState.pendingConsultations = [...consultationsTourMockState.pendingConsultations, consultation];
    consultationsTourMockState.dayConsultations = [...consultationsTourMockState.dayConsultations, consultation];
    consultationsTourMockState.consultationDetails[consultationId] = {
        id: consultationId,
        date: createdAt,
        patient: consultation.patientName,
        type: 'initiale',
        medecin: consultation.medecin,
        medecinId,
        infirmierId: null,
        salleId: null,
        noteSeance: payload?.notes || '',
        actes: []
    };
    consultationsTourMockState.consultationInvoices[consultationId] = [
        {
            id: 1,
            dent: '',
            type: 'Consultation',
            prix: Number(payload?.consultation_amount ?? payload?.patient_amount ?? 5000),
            quantite: 1,
            description: payload?.notes || 'Consultation creee depuis le tour'
        }
    ];
    consultationsTourMockState.activeFicheLinks[consultationId] = null;

    return cloneValue(consultation);
}
