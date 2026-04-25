import { computed, reactive, ref, watch } from 'vue';
import { fetchMedecins, fetchInfirmiers } from '@/services/corpsmedical';
import { fetchSalles } from '@/services/salles';
import { fetchConsultationDetails, setConsultationFiche } from '@/services/consultations';
import { loadOrdonnances, closeConsultation } from '@/services/consultationsforms';
import { loadFicheMedicale, saveTemplateForm } from '@/services/ficheMedicale';
import { filePrefix } from '@/config';

const stripFilePrefix = (url) => {
    if (!url || typeof url !== 'string') return '';
    if (/^https?:\/\//i.test(url) || url.startsWith('blob:') || url.startsWith('data:')) return url;
    const prefix = filePrefix.replace(/\/$/, '');
    if (url.startsWith(prefix)) {
        return url.slice(prefix.length).replace(/^\//, '');
    }
    return url;
};

const defaultEntretien = () => ({
    motifConsultation: '',
    anamnese: '',
    etatGynecologique: { allaitement: null, grossesseEnCours: null, menstrues: null },
    medicaments: [],
    affections: [],
    questions: [],
    habitudes: []
});

const defaultExamens = () => ({
    exobuccalInspection: {},
    exobuccalPalpation: {},
    chainesGanglionnaires: {},
    endobuccalBoucheFermee: {
        occlusion: '',
        mediane: '',
        classesAngle: '',
        vestibules: ''
    },
    endobuccalBoucheOuverte: {
        hbd: '',
        brossage: '',
        soccu: '',
        cinematiqueMandibulaire: '',
        ouvertureBuccale: '',
        temperatureBuccale: '',
        amplitudeOuverture: '',
        bruitsArticulaires: ''
    },
    tissusMousTable: {},
    tissusDursTable: {},
    examenCanauxExcreteurs: '',
    examensBacteriologiques: { observation: '', resultat: '' },
    examensSerologiques: { observation: '', resultat: '' },
    examensHistologiques: { observation: '', resultat: '' }
});

const defaultBilans = () => ({
    bilanDentaire: { formuleDentaire: {} },
    bilanRadiographique: {
        radiographieExtraBuccaleHypothese: '',
        radiographieIntraBuccaleHypothese: ''
    },
    bilanSanguin: {
        nfsDetaillee: '',
        tpTcaInr: '',
        uree: '',
        creatininemie: '',
        glycemie: ''
    },
    diagnosticPositif: ''
});

const defaultPlanTraitement = () => ([]);

const defaultDevis = () => ({ date: null, services: [] });

const normalizeDateForApi = (value) => {
    if (!value) return null;
    if (typeof value === 'string') {
        return value;
    }
    const parsed = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsed.getTime())) return null;
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const defaultConsultation = () => ({
    type: '',
    medecinId: null,
    infirmierIds: [],
    salleId: null,
    noteSeance: '',
    actes: []
});

export const useConsultationsForm = ({ ficheId, consultId, token, mode }) => {
    const loading = ref(false);
    const activeSection = ref('infos');
    const switcherMode = ref('tabs');
    const sectionInitKey = ref(0);

    const saving = reactive({
        entretien: false,
        examens: false,
        documents: false,
        bilans: false,
        planTraitement: false,
        devis: false,
        consult: false,
        ordonnances: false
    });

    const dirty = reactive({
        entretien: false,
        examens: false,
        documents: false,
        bilans: false,
        planTraitement: false,
        devis: false,
        consult: false,
        ordonnances: false
    });

    const lastSavedAt = ref(null);
    const autoSaveEnabled = ref(false);
    const readyForDirty = ref(false);
    const formTemplateKey = ref('fiche_medicale_v2');
    const requiredTemplateFields = ref(['entretien', 'examens', 'bilans']);
    const savingCount = computed(() => Object.values(saving).filter(Boolean).length);
    const dirtySectionsList = computed(() => Object.entries(dirty).filter(([, v]) => v).map(([k]) => k));

    const data = reactive({
        patient: { allergies: [], antecedents: [] },
        entretien: defaultEntretien(),
        examens: defaultExamens(),
        documents: { documents: [] },
        bilans: defaultBilans(),
        planTraitement: defaultPlanTraitement(),
        devis: defaultDevis(),
        consultation: defaultConsultation(),
        sessions: [],
        ordonnances: [],
        medecins: [],
        infirmiers: [],
        salles: []
    });

    let autosaveTimer = null;
    let ignoreNextDirty = false;

    const setSaving = (key, value) => {
        if (saving[key] === undefined) return;
        saving[key] = value;
    };

    const buildDevisPayload = () => ({
        date: normalizeDateForApi(data.devis.date),
        type: 0,
        contenus: (data.devis.services || []).map((s) => ({
            designation: s.designation ?? '',
            qte: s.qte ?? 1,
            montant: s.montant ?? 0
        }))
    });

    const buildDocumentsPayload = () => (data.documents?.documents || []).map((d) => ({
        groupKey: d.groupKey ?? null,
        type: d.type ?? 'Document',
        libelle: d.libelle ?? '',
        urls: (d.urls || []).map(stripFilePrefix).filter(Boolean)
    }));

    const buildTemplateFormData = () => {
        const documents = buildDocumentsPayload();
        const traitementsDocuments = {
            documents,
        };

        return {
            entretien: data.entretien,
            examens: data.examens,
            bilans: data.bilans,
            planTraitement: data.planTraitement,
            documents,
            traitementsDocuments,
            devis: buildDevisPayload(),
        };
    };

    const clearDirty = (keys) => {
        keys.forEach((k) => {
            if (dirty[k] !== undefined) dirty[k] = false;
        });
        lastSavedAt.value = new Date();
    };

    const scheduleAutosave = (saveAll) => {
        if (!autoSaveEnabled.value) return;
        clearTimeout(autosaveTimer);
        if (!dirtySectionsList.value.length) return;
        autosaveTimer = setTimeout(() => {
            saveAll({ silent: true });
        }, 7000);
    };

    const markDirty = (key, saveAll) => {
        if (!readyForDirty.value || ignoreNextDirty) return;
        if (dirty[key] !== undefined) dirty[key] = true;
        scheduleAutosave(saveAll);
    };

    const watchSection = (getter, key, saveAll) => {
        watch(getter, () => markDirty(key, saveAll), { deep: true });
    };

    const loadReferenceData = async () => {
        const [meds, infs, salles] = await Promise.all([
            fetchMedecins(token),
            fetchInfirmiers(token),
            fetchSalles(token)
        ]);

        data.medecins = meds;
        data.infirmiers = infs;
        data.salles = salles;
    };

    const ensureFicheLinked = async () => {
        if (!consultId.value) return null;

        const requestedFicheId = mode?.value === 'continue' ? (ficheId.value || null) : null;
        const res = await setConsultationFiche(consultId.value, requestedFicheId, token);
        const linkedId = res?.ficheId ?? res?.id ?? null;
        if (linkedId) ficheId.value = linkedId;
        return ficheId.value;
    };

    const hydrateFromFiche = (res) => {
        const fiche = res || {};
        const formData = fiche.formData && typeof fiche.formData === 'object' ? fiche.formData : {};
        const source = Object.keys(formData).length ? formData : fiche;
        ignoreNextDirty = true;
        formTemplateKey.value = fiche.formTemplateKey || 'fiche_medicale_v2';
        const required = fiche.formTemplate?.structure?.required;
        requiredTemplateFields.value = Array.isArray(required) && required.length ? required : ['entretien', 'examens', 'bilans'];

        data.patient = {
            id: fiche.patient?.id ?? null,
            nom: fiche.patient?.nom ?? '',
            prenom: fiche.patient?.prenom ?? '',
            telephone: fiche.patient?.telephone ?? '',
            sexe: fiche.patient?.sexe ?? '',
            dateNaissance: fiche.patient?.dateNaissance ?? null,
            email: fiche.patient?.email ?? '',
            profession: fiche.patient?.profession ?? '',
            lieuNaissance: fiche.patient?.lieuNaissance ?? '',
            adresse: fiche.patient?.adresse ?? '',
            allergies: Array.isArray(fiche.patient?.allergies) ? fiche.patient.allergies : [],
            antecedents: Array.isArray(fiche.patient?.antecedents) ? fiche.patient.antecedents : []
        };

        data.entretien = source.entretien ? { ...defaultEntretien(), ...source.entretien } : defaultEntretien();
        const examens = source.examens || {};
        data.examens = {
            ...defaultExamens(),
            exobuccalInspection: examens.exobuccalInspection ?? {},
            exobuccalPalpation: examens.exobuccalPalpation ?? {},
            chainesGanglionnaires: examens.chainesGanglionnaires ?? {},
            endobuccalBoucheFermee: examens.endobuccalBoucheFermee || {
                occlusion: examens.occlusion ?? '',
                mediane: examens.mediane ?? '',
                classesAngle: examens.classesAngle ?? '',
                vestibules: examens.vestibules ?? ''
            },
            endobuccalBoucheOuverte: examens.endobuccalBoucheOuverte || {
                hbd: examens.hbd ?? '',
                brossage: examens.brossage ?? '',
                soccu: examens.soccu ?? '',
                cinematiqueMandibulaire: examens.cinematiqueMandibulaire ?? '',
                ouvertureBuccale: examens.ouvertureBuccale ?? '',
                temperatureBuccale: examens.temperatureBuccale ?? '',
                amplitudeOuverture: examens.amplitudeOuverture ?? '',
                bruitsArticulaires: examens.bruitsArticulaires ?? ''
            },
            tissusMousTable: examens.tissusMousTable ?? {},
            tissusDursTable: examens.tissusDursTable ?? {},
            examenCanauxExcreteurs: examens.examenCanauxExcreteurs ?? '',
            examensBacteriologiques: examens.examensBacteriologiques ?? { observation: '', resultat: '' },
            examensSerologiques: examens.examensSerologiques ?? { observation: '', resultat: '' },
            examensHistologiques: examens.examensHistologiques ?? { observation: '', resultat: '' }
        };

        const bilans = source.bilans || {};
        data.bilans = {
            ...defaultBilans(),
            bilanDentaire: bilans.bilanDentaire || { formuleDentaire: bilans.formuleDentaire ?? {} },
            bilanRadiographique: bilans.bilanRadiographique || {
                radiographieExtraBuccaleHypothese: bilans.radiographieExtraBuccaleHypothese ?? '',
                radiographieIntraBuccaleHypothese: bilans.radiographieIntraBuccaleHypothese ?? ''
            },
            bilanSanguin: bilans.bilanSanguin || {
                nfsDetaillee: bilans.nfsDetaillee ?? '',
                tpTcaInr: bilans.tpTcaInr ?? '',
                uree: bilans.uree ?? '',
                creatininemie: bilans.creatininemie ?? '',
                glycemie: bilans.glycemie ?? ''
            },
            diagnosticPositif: bilans.diagnosticPositif ?? ''
        };

        const sourceDocuments = Array.isArray(source.documents)
            ? source.documents
            : Array.isArray(source.traitementsDocuments?.documents)
                ? source.traitementsDocuments.documents
                : Array.isArray(fiche.documents)
                    ? fiche.documents
                    : [];

        data.documents = {
            documents: sourceDocuments.map((d, idx) => ({
                id: d.id,
                groupKey: d.groupKey ?? `doc-${d.id ?? idx}`,
                type: d.type ?? 'Document',
                libelle: d.libelle ?? '',
                urls: Array.isArray(d.urls) ? d.urls.filter(Boolean) : d.url ? [d.url] : [],
                files: []
            }))
        };

        const rawDevis = source.devis ?? fiche.devis;
        const devis = Array.isArray(rawDevis) ? rawDevis[0] : rawDevis || null;
        data.devis = devis
            ? {
                  date: devis.date ?? null,
                  services: Array.isArray(devis.contenus ?? devis.services)
                      ? (devis.contenus ?? devis.services).map((c) => ({
                            designation: c.designation ?? '',
                            qte: c.qte ?? 1,
                            montant: c.montant ?? 0
                        }))
                      : []
              }
            : defaultDevis();

        const plans = source.planTraitement ?? fiche.planTraitement ?? fiche.plansTraitement ?? [];
        data.planTraitement = Array.isArray(plans) ? plans.map((p, idx) => ({
            planIndex: p.planIndex ?? idx + 1,
            type: p.type ?? '',
            dateSupposed: p.dateSupposed ?? null,
            description: p.description ?? p.Description ?? ''
        })) : defaultPlanTraitement();

        data.sessions = Array.isArray(fiche.consultations) ? fiche.consultations.map((s) => ({
            id: s.id,
            date: s.createdAt ?? s.date ?? null,
            medecin: s.medecin?.name ?? s.medecin ?? null,
            infirmier: s.infirmier?.name ?? s.infirmier ?? null,
            salle: s.salle?.name ?? s.salle ?? null,
            noteSeance: s.noteSeance ?? '',
            statut: s.statut ?? null,
            actes: s.actes ?? []
        })) : [];

        ignoreNextDirty = false;
        clearDirty(['entretien', 'examens', 'documents', 'bilans', 'planTraitement', 'devis', 'consult', 'ordonnances']);
        sectionInitKey.value += 1;
    };

    const loadData = async () => {
        if (!ficheId.value && !consultId.value) return;
        loading.value = true;
        readyForDirty.value = false;
        try {
            await loadReferenceData();
            await ensureFicheLinked();

            try {
                const res = await loadFicheMedicale(ficheId.value, token);
                hydrateFromFiche(res);
            } catch (error) {
                const status = error?.response?.status;
                if (status === 404 && consultId.value) {
                    await ensureFicheLinked();
                    if (ficheId.value) {
                        const res = await loadFicheMedicale(ficheId.value, token);
                        hydrateFromFiche(res);
                    }
                } else {
                    throw error;
                }
            }

            if (consultId.value) {
                try {
                    const consult = await fetchConsultationDetails(consultId.value, token);
                    data.consultation = {
                        ...defaultConsultation(),
                        type: consult.type ?? '',
                        medecinId: consult.medecinId ?? null,
                        noteSeance: consult.noteSeance ?? '',
                        actes: consult.actes ?? []
                    };
                } catch (error) {
                    console.error('Erreur chargement consultation', error);
                }

                try {
                    data.ordonnances = await loadOrdonnances(consultId.value, token);
                } catch (error) {
                    console.error('Erreur chargement ordonnances', error);
                }
            }
        } finally {
            loading.value = false;
            readyForDirty.value = true;
        }
    };

    const saveEntretienSection = async () => {
        if (!dirty.entretien) return;
        setSaving('entretien', true);
        try {
            await saveTemplateForm(
                ficheId.value,
                formTemplateKey.value,
                buildTemplateFormData(),
                [],
                token
            );
            clearDirty(['entretien']);
        } finally {
            setSaving('entretien', false);
        }
    };

    const saveExamensSection = async () => {
        if (!dirty.examens) return;
        setSaving('examens', true);
        try {
            await saveTemplateForm(
                ficheId.value,
                formTemplateKey.value,
                buildTemplateFormData(),
                [],
                token
            );
            clearDirty(['examens']);
        } finally {
            setSaving('examens', false);
        }
    };

    const saveBilansSection = async () => {
        if (!dirty.bilans) return;
        setSaving('bilans', true);
        try {
            await saveTemplateForm(
                ficheId.value,
                formTemplateKey.value,
                buildTemplateFormData(),
                [],
                token
            );
            clearDirty(['bilans']);
        } finally {
            setSaving('bilans', false);
        }
    };

    const savePlanTraitementSection = async () => {
        if (!dirty.planTraitement) return;
        setSaving('planTraitement', true);
        try {
            await saveTemplateForm(
                ficheId.value,
                formTemplateKey.value,
                buildTemplateFormData(),
                [],
                token
            );
            clearDirty(['planTraitement']);
        } finally {
            setSaving('planTraitement', false);
        }
    };

    const saveDocumentsSection = async () => {
        if (!dirty.documents) return;
        setSaving('documents', true);
        try {
            const files = (data.documents?.documents || []).map((d) => d.files || []);
            await saveTemplateForm(
                ficheId.value,
                formTemplateKey.value,
                buildTemplateFormData(),
                files,
                token
            );
            clearDirty(['documents']);
        } finally {
            setSaving('documents', false);
        }
    };

    const saveDevisSection = async () => {
        if (!dirty.devis) return;
        setSaving('devis', true);
        try {
            await saveTemplateForm(
                ficheId.value,
                formTemplateKey.value,
                buildTemplateFormData(),
                [],
                token
            );
            clearDirty(['devis']);
        } finally {
            setSaving('devis', false);
        }
    };

    const saveConsultSection = async ({ ordonnancePayload = null } = {}) => {
        if (!consultId.value) return;

        const shouldSaveConsult = dirty.consult;
        const shouldSaveOrdonnance = dirty.ordonnances && ordonnancePayload && Array.isArray(ordonnancePayload.lignes) && ordonnancePayload.lignes.length > 0;
        if (!shouldSaveConsult && !shouldSaveOrdonnance) return;

        setSaving('consult', true);
        try {
            const payload = {
                ...data.consultation,
                infirmierId: Array.isArray(data.consultation.infirmierIds)
                    ? data.consultation.infirmierIds[0] ?? null
                    : data.consultation.infirmierIds,
            };

            if (shouldSaveOrdonnance) {
                payload.ordonnance = ordonnancePayload;
            }

            await saveTemplateForm(
                ficheId.value,
                formTemplateKey.value,
                buildTemplateFormData(),
                [],
                token,
                {
                    consultationId: consultId.value,
                    consultation: payload,
                }
            );

            if (shouldSaveOrdonnance) {
                data.ordonnances = await loadOrdonnances(consultId.value, token);
                clearDirty(['consult', 'ordonnances']);
            } else {
                clearDirty(['consult']);
            }
        } finally {
            setSaving('consult', false);
        }
    };

    const saveOrdonnanceSection = async (payload) => {
        if (payload) {
            dirty.ordonnances = true;
        }
        await saveConsultSection({ ordonnancePayload: payload });
    };

    const closeConsult = async () => {
        if (!consultId.value) return;
        await closeConsultation(ficheId.value, consultId.value, token);
    };

    return {
        loading,
        activeSection,
        switcherMode,
        sectionInitKey,
        data,
        saving,
        dirty,
        lastSavedAt,
        autoSaveEnabled,
        savingCount,
        dirtySectionsList,
        loadData,
        watchSection,
        setSaving,
        clearDirty,
        saveEntretienSection,
        saveExamensSection,
        saveBilansSection,
        savePlanTraitementSection,
        saveDocumentsSection,
        saveDevisSection,
        saveConsultSection,
        saveOrdonnanceSection,
        closeConsult
    };
};
