import { registerCreatedPendingConsultationTourMock } from '@/services/consultationsTourMock';

const DEFAULT_SCENARIO = 'static';

const STATIC_MEDECINS = [
    {
        id: 1,
        nom: 'Fall',
        prenom: 'Aissatou',
        fullName: 'Dr Aissatou Fall',
        fullname: 'Dr Aissatou Fall',
        name: 'Dr Aissatou Fall',
        label: 'Dr Aissatou Fall',
        fonction: 'Chirurgien-dentiste',
        type: 'medecin',
        dateEmbauche: '2024-01-15',
        comingDays: ['Lun', 'Mar', 'Jeu']
    },
    {
        id: 2,
        nom: 'Seck',
        prenom: 'Mamadou',
        fullName: 'Dr Mamadou Seck',
        fullname: 'Dr Mamadou Seck',
        name: 'Dr Mamadou Seck',
        label: 'Dr Mamadou Seck',
        fonction: 'Orthodontiste',
        type: 'medecin',
        dateEmbauche: '2023-09-04',
        comingDays: ['Mer', 'Ven']
    },
    {
        id: 3,
        nom: 'Ndiaye',
        prenom: 'Khadija',
        fullName: 'Dr Khadija Ndiaye',
        fullname: 'Dr Khadija Ndiaye',
        name: 'Dr Khadija Ndiaye',
        label: 'Dr Khadija Ndiaye',
        fonction: 'Dentiste generaliste',
        type: 'medecin',
        dateEmbauche: '2022-05-02',
        comingDays: ['Lun', 'Jeu', 'Sam']
    }
];

const STATIC_PAYMENT_METHODS = [
    {
        id: 1,
        libelle: 'Especes',
        type: 'Especes',
        typeKey: 'cash',
        family: 'classic',
        coverageRate: null,
        actif: true,
        notes: null,
        autoValidate: true
    },
    {
        id: 2,
        libelle: 'Wave',
        type: 'Mobile Money',
        typeKey: 'mobile_money',
        family: 'classic',
        coverageRate: null,
        actif: true,
        notes: null,
        autoValidate: true
    },
    {
        id: 3,
        libelle: 'Assurance Premium',
        type: 'Assurance',
        typeKey: 'insurance',
        family: 'insurance',
        coverageRate: 80,
        actif: true,
        notes: 'Couverture de demonstration',
        autoValidate: false
    }
];

let tourMockEnabled = false;
let tourMockScenario = DEFAULT_SCENARIO;
let tourMockState = buildSeedState(DEFAULT_SCENARIO);

function cloneValue(value) {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
}

function normalizeScenario(scenario) {
    const normalized = String(scenario || '').toLowerCase();

    if (normalized === 'empty') return 'empty';
    if (normalized === 'clean-patient') return 'clean-patient';
    if (normalized === 'active-no-fiche') return 'active-no-fiche';
    if (normalized === 'active-with-fiche') return 'active-with-fiche';

    return DEFAULT_SCENARIO;
}

function buildStaticConsultationsForScenario(scenario) {
    if (scenario === 'clean-patient') {
        return {};
    }

    if (scenario === 'active-with-fiche') {
        return {
            5001: {
                id: 5001,
                patientId: 1002,
                medecinId: 2,
                date: '2026-04-01 10:15',
                hasFiche: true,
                previousDerniereConsultation: {
                    id: 4102,
                    date: '2026-03-25 14:15',
                    motif: 'Urgence molaire',
                    statut: 'NORMAL'
                }
            }
        };
    }

    return buildStaticConsultations();
}

function defaultSmsPreferences() {
    return {
        patientCreated: false,
        receipt: false,
        ticket: false,
        invoice: false,
        appointmentReminder: false,
        unsubscribed: false,
        blacklisted: false
    };
}

function computeAge(dateNaissance) {
    if (!dateNaissance) return null;

    const birthDate = new Date(dateNaissance);
    if (Number.isNaN(birthDate.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }

    return age;
}

function buildStaticPatients() {
    return {
        1001: {
            id: 1001,
            nom: 'Diallo',
            prenom: 'Aminata',
            fullname: 'Aminata Diallo',
            age: 29,
            dateNaissance: '1996-02-14',
            sexe: 'Femme',
            telephone: '+221771001010',
            email: 'aminata.diallo@example.test',
            adresse: 'Mermoz, Dakar',
            profession: 'Comptable',
            lieuNaissance: 'Dakar',
            groupeSanguin: 'O+',
            notes: 'Patiente de demonstration',
            contactUrgence: {
                nom: 'Moussa Diallo',
                telephone: '+221760001010',
                lienParente: 'Frere'
            },
            smsPreferences: defaultSmsPreferences(),
            derniereConsultation: {
                id: 4101,
                date: '2026-03-28 09:30',
                motif: 'Controle post detartrage',
                statut: 'NORMAL'
            },
            impayees: 0
        },
        1002: {
            id: 1002,
            nom: 'Ndiaye',
            prenom: 'Ibrahima',
            fullname: 'Ibrahima Ndiaye',
            age: 42,
            dateNaissance: '1984-07-03',
            sexe: 'Homme',
            telephone: '+221775551212',
            email: 'ibrahima.ndiaye@example.test',
            adresse: 'Sacre-Coeur, Dakar',
            profession: 'Architecte',
            lieuNaissance: 'Thies',
            groupeSanguin: 'A+',
            notes: 'Consultation active de demonstration',
            contactUrgence: {
                nom: 'Fatou Ndiaye',
                telephone: '+221760001212',
                lienParente: 'Epouse'
            },
            smsPreferences: defaultSmsPreferences(),
            insuranceProfile: {
                enabled: true,
                assuranceCode: 'PREMIUM',
                assuranceId: 3,
                coverageRate: 80,
                assurance: {
                    id: 3,
                    nom: 'Assurance Premium',
                    code: 'PREMIUM'
                },
                formData: {
                    societe: 'Entreprise Demo SA',
                    assureNom: 'Ibrahima Ndiaye',
                    assureNumero: 'ASS-2026-0042',
                    beneficiaireNom: 'Ibrahima Ndiaye',
                    beneficiaireNumero: 'BEN-0042'
                }
            },
            derniereConsultation: {
                id: 5001,
                date: '2026-04-01 10:15',
                motif: 'Consultation en cours de demonstration',
                statut: 'SUIVI'
            },
            impayees: 15000
        },
        1003: {
            id: 1003,
            nom: 'Sarr',
            prenom: 'Mariam',
            fullname: 'Mariam Sarr',
            age: 35,
            dateNaissance: '1990-11-19',
            sexe: 'Femme',
            telephone: '+221781231234',
            email: 'mariam.sarr@example.test',
            adresse: 'Ouakam, Dakar',
            profession: 'Enseignante',
            lieuNaissance: 'Saint-Louis',
            groupeSanguin: 'B+',
            notes: 'Nouveau dossier',
            contactUrgence: null,
            smsPreferences: defaultSmsPreferences(),
            derniereConsultation: null,
            impayees: 0
        }
    };
}

function buildStaticConsultations() {
    return {
        5001: {
            id: 5001,
            patientId: 1002,
            medecinId: 2,
            date: '2026-04-01 10:15',
            hasFiche: false,
            previousDerniereConsultation: {
                id: 4102,
                date: '2026-03-25 14:15',
                motif: 'Urgence molaire',
                statut: 'NORMAL'
            }
        }
    };
}

function buildBaseDossier(patient) {
    return {
        patient: {
            ...cloneValue(patient),
            numeroDossier: `DSP-${patient.id}`
        },
        antecedents: [],
        allergies: [],
        rdvs: [],
        fiches: [],
        paiements: [],
        factures: [],
        stats: {
            fiches: 0,
            rdv: 0,
            hospitalisations: 0,
            urgences: 0
        }
    };
}

function buildMockFiche() {
    return {
        id: 8101,
        dateCreation: '2026-03-25T09:30:00',
        createdAt: '2026-03-25T09:30:00',
        entretien: {
            motifConsultation: 'Douleur intermittente sur la molaire inferieure droite.',
            anamnese: 'Douleur accentuee au froid depuis une semaine avec gene a la mastication.',
            etatGynecologique: {
                allaitement: false,
                grossesseEnCours: false,
                menstrues: false
            },
            medicaments: [
                {
                    id: 1,
                    nom: 'Paracetamol',
                    estUtilise: true,
                    details: '500 mg si douleur importante.'
                }
            ],
            affections: [
                {
                    id: 1,
                    nom: 'Diabete type 2',
                    estPresente: true,
                    details: 'Equilibre par regime et suivi trimestriel.'
                }
            ],
            questions: [
                {
                    id: 1,
                    question: 'Bruxisme nocturne',
                    reponse: 'Oui',
                    details: 'Port d une gouttiere irregular.'
                }
            ],
            habitudes: [
                {
                    id: 1,
                    type: 'Hygiene',
                    details: 'Brossage biquotidien et bain de bouche une fois par jour.'
                }
            ]
        },
        examens: {
            exobuccalInspection: {
                symetrie: 'Respectee',
                peau: 'Saine'
            },
            exobuccalPalpation: {
                atm: 'Souple',
                muscles: 'Sans douleur'
            },
            chainesGanglionnaires: {
                sousMandibulaires: 'RAS'
            },
            endobuccalBoucheFermee: {
                occlusion: 'Classe I',
                mediane: 'Respectee',
                classesAngle: 'Classe I',
                vestibules: 'Normaux'
            },
            endobuccalBoucheOuverte: {
                hbd: 'Moyenne',
                brossage: 'Biquotidien',
                soccu: 'Faible',
                cinematiqueMandibulaire: 'Normale',
                ouvertureBuccale: 'Correcte',
                temperatureBuccale: 'Normale',
                amplitudeOuverture: '42 mm',
                bruitsArticulaires: 'Aucun'
            },
            examenCanauxExcreteurs: 'RAS',
            tissusMousTable: {},
            tissusDursTable: {},
            examensBacteriologiques: {
                observation: 'Aucune anomalie',
                resultat: 'Negatif'
            }
        },
        documents: [],
        planTraitement: [
            {
                type: 'Urgence',
                dateSupposed: '2026-04-03',
                description: 'Traitement endodontique de la 46',
                planIndex: 1
            },
            {
                type: 'Dentaires',
                dateSupposed: '2026-04-10',
                description: 'Reconstitution coronaire definitive',
                planIndex: 2
            }
        ],
        bilans: {
            bilanDentaire: {
                formuleDentaire: null
            },
            bilanRadiographique: {
                radiographieExtraBuccaleHypothese: 'Image compatible avec une lésion carieuse sur la 46.',
                radiographieIntraBuccaleHypothese: 'Perte osseuse localisée au niveau de la 46.'
            },
            bilanSanguin: {
                nfsDetaillee: 'Normale',
                tpTcaInr: 'Dans les normes',
                uree: '0,35 g/L',
                creatininemie: '7 mg/L',
                glycemie: '0,95 g/L'
            },
            diagnosticPositif: 'Pulpite irréversible sur la 46 avec indication de traitement endodontique.'
        },
        devis: [],
        consultations: [
            {
                id: 9101,
                createdAt: '2026-03-25T09:45:00',
                medecin: 'Dr Mamadou Seck',
                infirmier: 'Aide clinique A',
                salle: 'Salle 2',
                noteSeance: 'Premiere prise en charge et prescription antalgique.',
                actes: [
                    {
                        libelle: 'Examen clinique'
                    }
                ],
                total: 15000,
                statut: 1
            }
        ]
    };
}

function buildStaticConsultationHistory() {
    return {
        1001: [
            {
                id: 4101,
                date: '2026-03-28T09:30:00',
                medecin: 'Dr Aissatou Fall',
                statut: 1,
                state: 1,
                factureMontant: 10000,
                factureId: 8101,
                factModifiable: false,
                actes: [
                    {
                        id: 1,
                        type: 'Consultation',
                        description: 'Consultation de contrôle',
                        dent: '11',
                        prix: 10000,
                        quantite: 1,
                        montant: 10000
                    }
                ]
            }
        ],
        1002: [
            {
                id: 5001,
                date: '2026-04-01T10:15:00',
                medecin: 'Dr Mamadou Seck',
                statut: 0,
                state: 0,
                factureMontant: 30000,
                factureId: null,
                factModifiable: false,
                actes: []
            },
            {
                id: 4102,
                date: '2026-03-25T14:15:00',
                medecin: 'Dr Mamadou Seck',
                statut: 1,
                state: 1,
                factureMontant: 25000,
                factureId: 8102,
                factModifiable: true,
                actes: [
                    {
                        id: 2,
                        type: 'Détartrage',
                        description: 'Détartrage complet',
                        dent: '',
                        prix: 15000,
                        quantite: 1,
                        montant: 15000
                    },
                    {
                        id: 3,
                        type: 'Extraction',
                        description: 'Extraction simple',
                        dent: '36',
                        prix: 10000,
                        quantite: 1,
                        montant: 10000
                    }
                ]
            }
        ],
        1003: []
    };
}

function buildStaticPatientDossiers(patients) {
    const dossiers = {};

    Object.values(patients).forEach((patient) => {
        dossiers[patient.id] = buildBaseDossier(patient);
    });

    dossiers[1001] = {
        ...dossiers[1001],
        rdvs: [
            {
                id: 7001,
                statut: 'Confirmé',
                motif: 'Controle annuel',
                date: '2026-04-05T10:00:00',
                medecinNom: 'Dr Aissatou Fall',
                notes: 'Patient ponctuel.'
            }
        ],
        paiements: [
            {
                id: 3001,
                motif: 'Consultation de suivi',
                date: '2026-03-28T10:20:00',
                montant: 10000,
                modePaiement: 'Especes'
            }
        ],
        factures: [],
        stats: {
            fiches: 0,
            rdv: 1,
            hospitalisations: 0,
            urgences: 0
        }
    };

    dossiers[1002] = {
        ...dossiers[1002],
        antecedents: [
            {
                id: 1,
                type: 'Diabete',
                description: 'Type 2 suivi depuis 4 ans.',
                date: '2025-09-15'
            },
            {
                id: 2,
                type: 'Hypertension',
                description: 'Traitement stabilise.',
                date: '2024-11-20'
            }
        ],
        allergies: [
            {
                id: 1,
                libelle: 'Penicilline',
                description: 'Reaction cutanee observee en 2021.'
            }
        ],
        rdvs: [
            {
                id: 7002,
                statut: 'Confirmé',
                motif: 'Controle endodontique',
                date: '2026-04-08T11:30:00',
                medecinNom: 'Dr Mamadou Seck',
                notes: 'Verifier l evolution de la douleur.'
            },
            {
                id: 7003,
                statut: 'Planifié',
                motif: 'Reconstitution coronaire',
                date: '2026-04-15T09:00:00',
                medecinNom: 'Dr Mamadou Seck',
                notes: 'Prevoir controle radiographique.'
            }
        ],
        fiches: [buildMockFiche()],
        paiements: [
            {
                id: 3002,
                motif: 'Examen et soins d urgence',
                date: '2026-03-25T15:10:00',
                montant: 25000,
                modePaiement: 'Wave',
                notes: 'Reglement partiel effectue le jour meme.'
            },
            {
                id: 3003,
                motif: 'Acompte consultation en cours',
                date: '2026-04-01T10:45:00',
                montant: 15000,
                modePaiement: 'Assurance Premium',
                notes: 'Prise en charge assurance en attente de validation finale.'
            }
        ],
        factures: [
            {
                id: 4001,
                libelle: 'Facture traitement 46',
                date: '2026-04-01T11:00:00',
                montant: 15000,
                statut: 'Impayee'
            }
        ],
        stats: {
            fiches: 1,
            rdv: 2,
            hospitalisations: 0,
            urgences: 1
        }
    };

    dossiers[1003] = {
        ...dossiers[1003],
        rdvs: [],
        fiches: [],
        paiements: [],
        factures: [],
        stats: {
            fiches: 0,
            rdv: 0,
            hospitalisations: 0,
            urgences: 0
        }
    };

    return dossiers;
}

function buildSeedState(scenario) {
    const normalizedScenario = normalizeScenario(scenario);
    const patients = normalizedScenario === 'empty' ? {} : buildStaticPatients();
    const consultations = normalizedScenario === 'empty' ? {} : buildStaticConsultationsForScenario(normalizedScenario);
    const dossiers = normalizedScenario === 'empty' ? {} : buildStaticPatientDossiers(patients);
    const consultationHistory = normalizedScenario === 'empty' ? {} : buildStaticConsultationHistory();

    if (normalizedScenario === 'active-with-fiche' && dossiers[1002]) {
        dossiers[1002].fiches = [buildMockFiche()];
        dossiers[1002].stats = {
            ...dossiers[1002].stats,
            fiches: 1
        };
    }

    return {
        patients,
        consultations,
        dossiers,
        consultationHistory,
        rdvs: {},
        nextPatientId: normalizedScenario === 'empty' ? 1001 : 1004,
        nextConsultationId: normalizedScenario === 'empty' ? 5001 : 5002,
        nextRdvId: 7001,
        nextPaymentId: 9001
    };
}

function getPatientsList() {
    return Object.values(tourMockState.patients || {});
}

function filterPatients(query) {
    const needle = String(query || '')
        .trim()
        .toLowerCase();
    if (!needle) return getPatientsList();

    return getPatientsList().filter((patient) => {
        const haystack = [patient.fullname, patient.nom, patient.prenom, patient.telephone, patient.adresse].filter(Boolean).join(' ').toLowerCase();

        return haystack.includes(needle);
    });
}

function getSortableValue(patient, sortField) {
    if (!sortField) return patient?.fullname || patient?.nom || '';
    if (sortField === 'derniereConsultation.date') {
        return patient?.derniereConsultation?.date || '';
    }
    return patient?.[sortField] || '';
}

function sortPatients(patients, sortField, sortOrder) {
    const direction = sortOrder === 'desc' || sortOrder === -1 ? -1 : 1;
    const list = [...patients];

    return list.sort((left, right) => {
        const leftValue = getSortableValue(left, sortField);
        const rightValue = getSortableValue(right, sortField);

        if (typeof leftValue === 'number' && typeof rightValue === 'number') {
            return (leftValue - rightValue) * direction;
        }

        return String(leftValue).localeCompare(String(rightValue), 'fr') * direction;
    });
}

function formatConsultationDate(datePart, timePart) {
    const dateValue = datePart || new Date().toISOString().slice(0, 10);
    const timeValue = timePart || '09:00';
    return `${dateValue} ${timeValue}`;
}

function createPatientRecord(payload, existingPatient = null, id = null) {
    const base = existingPatient || {
        id,
        notes: '',
        contactUrgence: null,
        smsPreferences: defaultSmsPreferences(),
        derniereConsultation: null,
        impayees: 0
    };
    const nom = String(payload?.nom ?? base.nom ?? 'Patient').trim();
    const prenom = String(payload?.prenom ?? base.prenom ?? 'Test').trim();
    const dateNaissance = payload?.dateNaissance ?? base.dateNaissance ?? '';
    const contactUrgencePayload = payload?.contactUrgence;
    const contactUrgence =
        contactUrgencePayload && typeof contactUrgencePayload === 'object'
            ? {
                  nom: String(contactUrgencePayload.nom || '').trim(),
                  telephone: String(contactUrgencePayload.telephone || '').trim(),
                  lienParente: String(contactUrgencePayload.lienParente || '').trim()
              }
            : base.contactUrgence;
    const hasContactUrgence = contactUrgence && Object.values(contactUrgence).some(Boolean);

    return {
        id: id ?? base.id ?? null,
        nom,
        prenom,
        fullname: `${prenom} ${nom}`.trim(),
        age: computeAge(dateNaissance),
        dateNaissance,
        sexe: payload?.sexe ?? base.sexe ?? '',
        telephone: payload?.telephone ?? base.telephone ?? '',
        email: payload?.email ?? base.email ?? '',
        adresse: payload?.adresse ?? base.adresse ?? '',
        profession: payload?.profession ?? base.profession ?? '',
        lieuNaissance: payload?.lieuNaissance ?? base.lieuNaissance ?? '',
        groupeSanguin: payload?.groupeSanguin ?? base.groupeSanguin ?? '',
        notes: payload?.notes ?? base.notes ?? '',
        contactUrgence: hasContactUrgence ? contactUrgence : null,
        smsPreferences: {
            ...defaultSmsPreferences(),
            ...(base.smsPreferences || {}),
            ...(payload?.smsPreferences || {})
        },
        derniereConsultation: base.derniereConsultation ?? null,
        impayees: Number(base.impayees || 0)
    };
}

function findActiveConsultation(patientId) {
    return Object.values(tourMockState.consultations || {}).find((consultation) => Number(consultation.patientId) === Number(patientId)) || null;
}

export function resolvePatientsTourMockScenario(taskId = 'overview', variantId = null, fallbackScenario = DEFAULT_SCENARIO) {
    const taskKey = String(taskId || 'overview').toLowerCase();
    const variantKey = String(variantId || '').toLowerCase();

    if (taskKey === 'search-patient' && variantKey === 'empty') {
        return 'empty';
    }

    if (taskKey === 'create-consultation') {
        if (variantKey === 'blocked-no-fiche') return 'active-no-fiche';
        if (variantKey === 'blocked-with-fiche') return 'active-with-fiche';
        return 'clean-patient';
    }

    return normalizeScenario(fallbackScenario);
}

export function getPatientsTourMockPatientIdForScenario(scenario) {
    const normalized = normalizeScenario(scenario);
    if (normalized === 'clean-patient') return 1001;
    if (normalized === 'active-no-fiche' || normalized === 'active-with-fiche') return 1002;
    return 1002;
}

export function isPatientsTourMockEnabled() {
    return tourMockEnabled;
}

export function activatePatientsTourMock(scenario = DEFAULT_SCENARIO) {
    tourMockScenario = normalizeScenario(scenario);
    tourMockState = buildSeedState(tourMockScenario);
    tourMockEnabled = true;
    return cloneValue(tourMockState);
}

export function resetPatientsTourMockData(scenario = tourMockScenario) {
    tourMockScenario = normalizeScenario(scenario);
    tourMockState = buildSeedState(tourMockScenario);
    return cloneValue(tourMockState);
}

export function deactivatePatientsTourMock() {
    tourMockEnabled = false;
    tourMockScenario = DEFAULT_SCENARIO;
    tourMockState = buildSeedState(DEFAULT_SCENARIO);
}

export function listPatientsTourMock({ page = 1, limit = 10, q = '', sortField = null, sortOrder = null } = {}) {
    const filtered = sortPatients(filterPatients(q), sortField, sortOrder);
    const safePage = Math.max(1, Number(page) || 1);
    const safeLimit = Math.max(1, Math.min(100, Number(limit) || 10));
    const start = (safePage - 1) * safeLimit;

    return {
        items: cloneValue(filtered.slice(start, start + safeLimit)),
        total: filtered.length,
        page: safePage,
        limit: safeLimit,
        sortField,
        sortOrder
    };
}

export function searchPatientsTourMock(query, limit = 20) {
    const safeLimit = Math.max(1, Math.min(50, Number(limit) || 20));
    return cloneValue(filterPatients(query).slice(0, safeLimit));
}

export function fetchPatientByIdTourMock(patientId) {
    return cloneValue(tourMockState.patients?.[patientId] || null);
}

export function createPatientTourMock(payload) {
    const id = tourMockState.nextPatientId;
    tourMockState.nextPatientId += 1;
    const patient = createPatientRecord(payload, null, id);
    tourMockState.patients[id] = patient;
    tourMockState.dossiers[id] = buildBaseDossier(patient);
    tourMockState.consultationHistory[id] = [];
    return cloneValue(patient);
}

export function updatePatientTourMock(patientId, payload) {
    const existingPatient = tourMockState.patients?.[patientId] || null;
    if (!existingPatient) return null;

    const patient = createPatientRecord(payload, existingPatient, patientId);
    tourMockState.patients[patientId] = patient;
    if (tourMockState.dossiers?.[patientId]) {
        tourMockState.dossiers[patientId].patient = {
            ...tourMockState.dossiers[patientId].patient,
            ...cloneValue(patient),
            numeroDossier: tourMockState.dossiers[patientId].patient.numeroDossier || `DSP-${patientId}`
        };
    }
    return cloneValue(patient);
}

export function createConsultationForPatientTourMock(patientId, payload) {
    const existingPatient = tourMockState.patients?.[patientId] || null;
    if (!existingPatient) return null;

    const activeConsultation = findActiveConsultation(patientId);
    if (activeConsultation) {
        return {
            success: false,
            consultation_id: activeConsultation.id,
            paiement_id: null,
            error: 'Une consultation est deja en cours pour ce patient.'
        };
    }

    const consultationId = tourMockState.nextConsultationId;
    tourMockState.nextConsultationId += 1;
    const paiementId = payload?.payant ? tourMockState.nextPaymentId++ : null;
    const previousDerniereConsultation = cloneValue(existingPatient.derniereConsultation);
    const date = formatConsultationDate(payload?.consultation_date, payload?.consultation_time);

    tourMockState.consultations[consultationId] = {
        id: consultationId,
        patientId: Number(patientId),
        medecinId: Number(payload?.medecin_id || 1),
        date,
        hasFiche: false,
        previousDerniereConsultation
    };

    tourMockState.patients[patientId] = {
        ...existingPatient,
        derniereConsultation: {
            id: consultationId,
            date,
            motif: payload?.notes || payload?.motif || 'Consultation de demonstration',
            statut: 'SUIVI'
        }
    };

    if (tourMockState.dossiers?.[patientId]) {
        tourMockState.dossiers[patientId].patient = {
            ...tourMockState.dossiers[patientId].patient,
            ...cloneValue(tourMockState.patients[patientId])
        };
    }

    if (!Array.isArray(tourMockState.consultationHistory[patientId])) {
        tourMockState.consultationHistory[patientId] = [];
    }
    tourMockState.consultationHistory[patientId].unshift({
        id: consultationId,
        date: `${date.replace(' ', 'T')}:00`,
        medecin: STATIC_MEDECINS.find((medecin) => medecin.id === Number(payload?.medecin_id || 1))?.fullName || 'Médecin',
        statut: 0,
        factureMontant: Number(payload?.consultation_amount || 0)
    });

    const result = {
        success: true,
        consultation_id: consultationId,
        paiement_id: paiementId
    };

    registerCreatedPendingConsultationTourMock(existingPatient, payload, result);

    return result;
}

export function checkConsultationActiveTourMock(patientId) {
    const activeConsultation = findActiveConsultation(patientId);
    return {
        hasActive: Boolean(activeConsultation),
        consultationId: activeConsultation?.id ?? null,
        hasFiche: Boolean(activeConsultation?.hasFiche)
    };
}

export function deleteConsultationTourMock(consultationId) {
    const consultation = tourMockState.consultations?.[consultationId] || null;
    if (!consultation) {
        return { success: false };
    }

    const patientId = consultation.patientId;
    if (tourMockState.patients?.[patientId]) {
        tourMockState.patients[patientId] = {
            ...tourMockState.patients[patientId],
            derniereConsultation: cloneValue(consultation.previousDerniereConsultation) || null
        };
        if (tourMockState.dossiers?.[patientId]) {
            tourMockState.dossiers[patientId].patient = {
                ...tourMockState.dossiers[patientId].patient,
                ...cloneValue(tourMockState.patients[patientId])
            };
        }
    }

    if (Array.isArray(tourMockState.consultationHistory?.[patientId])) {
        tourMockState.consultationHistory[patientId] = tourMockState.consultationHistory[patientId].filter((item) => Number(item.id) !== Number(consultationId));
    }

    delete tourMockState.consultations[consultationId];
    return { success: true };
}

export function fetchMedecinsTourMock() {
    return cloneValue(STATIC_MEDECINS);
}

export function fetchPaymentMethodsTourMock() {
    return cloneValue(STATIC_PAYMENT_METHODS);
}

export function createRdvForPatientTourMock(patientId, payload) {
    const patient = tourMockState.patients?.[patientId] || null;
    if (!patient) return null;

    const rdvId = tourMockState.nextRdvId;
    tourMockState.nextRdvId += 1;
    tourMockState.rdvs[rdvId] = {
        id: rdvId,
        patientId: Number(patientId),
        medecinId: Number(payload?.medecin_id || 1),
        date: payload?.date || new Date().toISOString().slice(0, 10),
        time: payload?.time || '09:00',
        duration: Number(payload?.duration || 30),
        description: payload?.description || '',
        notes: payload?.notes || ''
    };

    if (tourMockState.dossiers?.[patientId]) {
        tourMockState.dossiers[patientId].rdvs = [
            {
                id: rdvId,
                statut: 'Planifié',
                motif: payload?.description || 'Rendez-vous',
                date: `${tourMockState.rdvs[rdvId].date}T${tourMockState.rdvs[rdvId].time}:00`,
                medecinNom: STATIC_MEDECINS.find((medecin) => medecin.id === Number(payload?.medecin_id || 1))?.fullName || 'Médecin',
                notes: payload?.notes || ''
            },
            ...(tourMockState.dossiers[patientId].rdvs || [])
        ];
        tourMockState.dossiers[patientId].stats = {
            ...tourMockState.dossiers[patientId].stats,
            rdv: (tourMockState.dossiers[patientId].rdvs || []).length
        };
    }

    return {
        success: true,
        rdv_id: rdvId
    };
}

export function fetchPatientDossierTourMock(patientId) {
    return cloneValue(tourMockState.dossiers?.[patientId] || null);
}

export function fetchPatientConsultationsTourMock(patientId) {
    return cloneValue(tourMockState.consultationHistory?.[patientId] || []);
}

export function addPatientAntecedentTourMock(patientId, payload) {
    const dossier = tourMockState.dossiers?.[patientId] || null;
    if (!dossier) return null;

    const antecedent = {
        id: Date.now(),
        type: payload?.type || 'Antécédent',
        description: payload?.description || '',
        date: payload?.date || new Date().toISOString().slice(0, 10)
    };
    dossier.antecedents = [antecedent, ...(dossier.antecedents || [])];
    return { antecedent: cloneValue(antecedent) };
}

export function deletePatientAntecedentTourMock(patientId, antecedentId) {
    const dossier = tourMockState.dossiers?.[patientId] || null;
    if (!dossier) return { success: false };

    dossier.antecedents = (dossier.antecedents || []).filter((item) => Number(item.id) !== Number(antecedentId));
    return { success: true };
}

export function addPatientAllergyTourMock(patientId, payload) {
    const dossier = tourMockState.dossiers?.[patientId] || null;
    if (!dossier) return null;

    const allergy = {
        id: Date.now(),
        libelle: payload?.libelle || payload?.nom || 'Allergie',
        description: payload?.description || ''
    };
    dossier.allergies = [allergy, ...(dossier.allergies || [])];
    return { allergy: cloneValue(allergy) };
}

export function deletePatientAllergyTourMock(patientId, allergyId) {
    const dossier = tourMockState.dossiers?.[patientId] || null;
    if (!dossier) return { success: false };

    dossier.allergies = (dossier.allergies || []).filter((item) => Number(item.id) !== Number(allergyId));
    return { success: true };
}

export function getPatientsTourMockPrimaryPatientId() {
    return 1002;
}

export function getPatientsTourMockActivePatient() {
    const activeConsultation = Object.values(tourMockState.consultations || {})[0] || null;
    if (!activeConsultation) return null;

    return cloneValue(tourMockState.patients?.[activeConsultation.patientId] || null);
}
