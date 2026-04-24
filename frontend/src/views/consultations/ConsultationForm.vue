<script setup>
import ConsultationEnCoursForm from '@/components/consultations/ConsultationEnCoursForm.vue';
import DevisForm from '@/components/consultations/DevisForm.vue';
import ExamensForm from '@/components/consultations/ExamensForm.vue';
import AnamneseForm from '@/components/consultations/AnamneseForm.vue';
import OrdonnanceModal from '@/components/consultations/OrdonnanceModal.vue';
import PastSessions from '@/components/consultations/PastSessions.vue';
import PatientInfoCard from '@/components/consultations/PatientInfoCard.vue';
import PrintOrdonnanceBody from '@/components/print/PrintOrdonnanceBody.vue';
import SaveIndicator from '@/components/consultations/SaveIndicator.vue';
import SectionSwitcher from '@/components/consultations/SectionSwitcher.vue';
import TraitementsDocumentsForm from '@/components/consultations/TraitementsDocumentsForm.vue';
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import { usePrinter } from '@/composables/usePrinter';
import { defaultSoinList, normalizeSoinList, setConsultationFiche } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent } from '@/services/patients';
import {
    closeConsultation,
    loadConsultationForm,
    loadOrdonnances,
    saveConsultation,
    saveDevis,
    saveExamens,
    saveMotif,
    saveTraitementsDocuments
} from '@/services/consultationsforms';
import { fetchOrdonnancePrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import { fetchInfirmiers, fetchMedecins } from '@/services/corpsmedical';
import { fetchSalles } from '@/services/salles';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import SelectButton from 'primevue/selectbutton';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const token = localStorage.getItem('token');
const auth = useAuthStore();
const { printComponent } = usePrinter();

const activeSection = ref('infos');
const switcherMode = ref('tabs');
const sectionInitKey = ref(0);

const loading = ref(false);
const saving = reactive({ motif: false, examens: false, traitements: false, devis: false, consult: false, ordonnances: false });
const dirty = reactive({ motif: false, examens: false, traitements: false, devis: false, consult: false, ordonnances: false });
const lastSavedAt = ref(null);
const autoSaveEnabled = ref(false);
const readyForDirty = ref(false);
const savingCount = computed(() => Object.values(saving).filter(Boolean).length);
const dirtySectionsList = computed(() => Object.entries(dirty).filter(([, v]) => v).map(([k]) => k));

const ficheId = ref(route.query.ficheId ? Number(route.query.ficheId) : null);
const consultId = ref(route.query.id ? Number(route.query.id) : null);
const mode = computed(() => (route.query.mode === 'new-fiche' ? 'new-fiche' : 'continue'));

const data = reactive({
    patient: { allergies: [], antecedents: [] },
    motif: { motif: '', histoireMaladie: '', soinsAnterieurs: '' },
    examens: { exoInspection: '', exoPalpation: '', endoInspection: '', endoPalpation: '', occlusion: '', examenParodontal: '', diagnostic: '', toothsCheck: {} },
    traitements: { traitementUrgence: '', traitementDentaire: '', traitementParodontal: '', traitementOrthodontique: '', autres: '', documents: [] },
    devis: { date: null, services: [] },
    consultation: { type: '', medecinId: null, infirmierIds: [], salleId: null, noteSeance: '', actes: [] },
    sessions: [],
    ordonnances: [],
    medecins: [],
    infirmiers: [],
    salles: []
});

const ordonnanceModalVisible = ref(false);
const ordonnanceDraft = ref({ date: '', medecinNom: '', note: '', lignes: [] });
const showAntecedentDialog = ref(false);
const showAllergyDialog = ref(false);
const savingAntecedent = ref(false);
const savingAllergy = ref(false);
const isIndicatorFloating = ref(false);
const allowRouteLeaveAfterCloture = ref(false);
let autosaveTimer = null;
let ignoreNextDirty = false;
const isMedecinOptionalOnCreation = ref(false);
const soinsList = ref([...defaultSoinList]);

const displayModeOptions = [
    { label: 'Onglets', value: 'tabs' },
    { label: 'Sidebar', value: 'sidebar' }
];

const hasValue = (value) => {
    if (Array.isArray(value)) return value.length > 0;
    if (value && typeof value === 'object') return Object.values(value).some(hasValue);
    if (typeof value === 'number') return value > 0;
    return typeof value === 'string' ? value.trim().length > 0 : Boolean(value);
};

const isSectionFilled = (id) => {
    switch (id) {
        case 'infos':
            return hasValue([data.patient?.nom, data.patient?.prenom, data.patient?.telephone, data.patient?.sexe, data.patient?.dateNaissance]);
        case 'motif':
            return hasValue([data.motif?.motif, data.motif?.histoireMaladie, data.motif?.soinsAnterieurs]);
        case 'examens':
            return hasValue(data.examens);
        case 'traitements':
            return hasValue(data.traitements);
        case 'devis':
            return hasValue([data.devis?.date, data.devis?.services]);
        case 'seances':
            return hasValue(data.sessions);
        case 'consult':
            return hasValue([data.consultation?.type, data.consultation?.medecinId, data.consultation?.infirmierIds, data.consultation?.salleId, data.consultation?.noteSeance, data.consultation?.actes]);
        default:
            return false;
    }
};

const getSectionStatus = (key) => {
    if (!key) return { status: 'readonly', label: 'Lecture seule', saveDisabled: true };
    if (saving[key]) return { status: 'saving', label: 'Sauvegarde...', saveDisabled: true };
    if (dirty[key]) return { status: 'dirty', label: 'Modifié', saveDisabled: false };
    return { status: 'saved', label: 'Sauvegardé', saveDisabled: true };
};

const sections = computed(() => {
    const motifStatus = getSectionStatus('motif');
    const examensStatus = getSectionStatus('examens');
    const traitementsStatus = getSectionStatus('traitements');
    const devisStatus = getSectionStatus('devis');
    const consultStatus = getSectionStatus('consult');

    return [
        {
            id: 'infos',
            label: 'Infos patient',
            filled: isSectionFilled('infos'),
            status: 'readonly',
            statusLabel: 'Lecture seule',
            saveDisabled: true
        },
        {
            id: 'motif',
            label: 'Motif & histoire',
            filled: isSectionFilled('motif'),
            status: motifStatus.status,
            statusLabel: motifStatus.label,
            saveDisabled: motifStatus.saveDisabled,
            saving: saving.motif,
            onSave: () => saveMotifSection()
        },
        {
            id: 'examens',
            label: 'Examens',
            filled: isSectionFilled('examens'),
            status: examensStatus.status,
            statusLabel: examensStatus.label,
            saveDisabled: examensStatus.saveDisabled,
            saving: saving.examens,
            onSave: () => saveExamensSection()
        },
        {
            id: 'traitements',
            label: 'Traitements & documents',
            filled: isSectionFilled('traitements'),
            status: traitementsStatus.status,
            statusLabel: traitementsStatus.label,
            saveDisabled: traitementsStatus.saveDisabled,
            saving: saving.traitements,
            onSave: () => saveTraitementsSection()
        },
        {
            id: 'devis',
            label: 'Devis',
            filled: isSectionFilled('devis'),
            status: devisStatus.status,
            statusLabel: devisStatus.label,
            saveDisabled: devisStatus.saveDisabled,
            saving: saving.devis,
            onSave: () => saveDevisSection()
        },
        {
            id: 'seances',
            label: 'Séances passées',
            filled: isSectionFilled('seances'),
            status: 'readonly',
            statusLabel: 'Lecture seule',
            saveDisabled: true
        },
        {
            id: 'consult',
            label: 'Consultation en cours',
            filled: isSectionFilled('consult'),
            status: consultStatus.status,
            statusLabel: consultStatus.label,
            saveDisabled: consultStatus.saveDisabled,
            saving: saving.consult,
            onSave: () => saveConsultSection()
        }
    ];
});

const medecinsOptions = computed(() => (data.medecins || []).map((m) => ({
    id: m.id,
    label: m.label || m.fullName || m.fullname || m.name || m.FullName || `${m.prenom ?? ''} ${m.nom ?? ''}`.trim() || m.nom
})));
const infirmiersOptions = computed(() => (data.infirmiers || []).map((i) => ({
    id: i.id,
    label: i.label || i.fullName || i.fullname || i.name || i.Fullname || `${i.prenom ?? ''} ${i.nom ?? ''}`.trim() || i.nom
})));
const sallesOptions = computed(() => (data.salles || []).map((s) => ({ id: s.id, label: s.label || s.nom || s.name || '' })));
const selectedMedecinLabel = computed(() => {
    const selectedId = data.consultation?.medecinId;
    const item = (medecinsOptions.value || []).find((m) => m.id === selectedId);
    if (item?.label) return item.label;
    const user = auth.user || {};
    const fullName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
    return fullName || user.name || user.username || '';
});

const normalizeText = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

const resolveConnectedMedecinId = () => {
    const user = auth.user || {};
    const directId = Number(user.medecinId ?? user.medecin_id ?? user.medecin?.id ?? Number.NaN);
    if (Number.isFinite(directId)) {
        const found = (medecinsOptions.value || []).find((m) => Number(m.id) === directId);
        if (found) return found.id;
    }

    const fullName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
    const candidates = [fullName, user.name, user.fullName, user.username].filter(Boolean).map(normalizeText);
    if (!candidates.length) return null;

    const foundByName = (medecinsOptions.value || []).find((m) => {
        const label = normalizeText(m.label);
        return candidates.some((candidate) => candidate && (label === candidate || label.includes(candidate) || candidate.includes(label)));
    });
    return foundByName?.id ?? null;
};

const formatDate = (value) => {
    if (!value) return '';
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString('fr-FR');
};

const formatDateApi = (value) => {
    if (!value) return null;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toISOString().slice(0, 10);
};

const computeAgeYears = (value) => {
    if (!value) return 0;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return Number(value) || 0;
    const diff = Date.now() - d.getTime();
    return Math.max(0, Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25)));
};

const markDirty = (key) => {
    if (!readyForDirty.value || ignoreNextDirty) return;
    if (dirty[key] !== undefined) dirty[key] = true;
    scheduleAutosave();
};

const watchSection = (getter, key) => {
    watch(getter, () => markDirty(key), { deep: true });
};

watchSection(() => data.motif, 'motif');
watchSection(() => data.examens, 'examens');
watchSection(() => data.traitements, 'traitements');
watchSection(() => data.devis, 'devis');
watchSection(() => data.consultation, 'consult');
watchSection(() => ordonnanceDraft.value, 'ordonnances');

const setSaving = (key, value) => {
    if (saving[key] === undefined) return;
    saving[key] = value;
};

const clearDirty = (keys) => {
    keys.forEach((k) => {
        if (dirty[k] !== undefined) dirty[k] = false;
    });
    lastSavedAt.value = new Date();
};

const scheduleAutosave = () => {
    if (!autoSaveEnabled.value) return;
    clearTimeout(autosaveTimer);
    if (!dirtySectionsList.value.length) return;
    autosaveTimer = setTimeout(() => {
        saveAll({ silent: true });
    }, 7000);
};

const loadReferenceData = async () => {
    try {
        const [meds, infs, salles] = await Promise.all([
            fetchMedecins(token),
            fetchInfirmiers(token),
            fetchSalles(token)
        ]);

        data.medecins = meds;
        data.infirmiers = infs;
        data.salles = salles;
    } catch (error) {
        console.error('Erreur chargement corps médical/salles', error);
        toast.add({ severity: 'warn', summary: 'Info', detail: 'Médecins, infirmiers ou salles indisponibles.', life: 2500 });
    }
};

const loadConsultationPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        isMedecinOptionalOnCreation.value = settings?.requireMedecinOnConsultationCreation === false;
        soinsList.value = normalizeSoinList(settings?.soinsList);
    } catch (error) {
        console.error('Erreur chargement politique consultation', error);
        isMedecinOptionalOnCreation.value = false;
        soinsList.value = [...defaultSoinList];
    }
};

const ensureFicheLinked = async () => {
    if (!consultId.value) return null;

    try {
        const requestedFicheId = mode.value === 'continue' ? (ficheId.value || null) : null;
        const res = await setConsultationFiche(consultId.value, requestedFicheId, token);
        const linkedId = res?.ficheId ?? res?.id ?? null;
        if (linkedId) {
            ficheId.value = Number(linkedId);
            const routeName = route.name || 'consultations-form';
            router.replace({ name: routeName, query: { ...route.query, id: consultId.value, ficheId: ficheId.value } });
        }
        return ficheId.value;
    } catch (error) {
        if (isClosedConsultationError(error)) {
            throw error;
        }
        console.error('Erreur création/liaison fiche', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de lier une fiche à la consultation.', life: 3000 });
        return null;
    }
};

const loadData = async () => {
    if (!consultId.value) return;
    loading.value = true;
    readyForDirty.value = false;
    try {
        await ensureFicheLinked();
        if (!ficheId.value) throw new Error('Fiche introuvable');

        const [_, res] = await Promise.all([
            loadReferenceData(),
            loadConsultationPolicy(),
            loadConsultationForm(ficheId.value, consultId.value, token)
        ]);
        ignoreNextDirty = true;
        hydrateFromResponse(res);
        if (isMedecinOptionalOnCreation.value && isMedecinUser.value && !data.consultation.medecinId) {
            const fallbackMedecinId = resolveConnectedMedecinId();
            if (fallbackMedecinId) {
                data.consultation = { ...data.consultation, medecinId: fallbackMedecinId };
            }
        }
        if (data.consultation.ficheId && !ficheId.value) ficheId.value = data.consultation.ficheId;
        if (res.ordonnances) data.ordonnances = res.ordonnances;
        else fetchOrdonnances();
        ignoreNextDirty = false;
        clearDirty(['motif', 'examens', 'traitements', 'devis', 'consult', 'ordonnances']);
        sectionInitKey.value += 1;
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur de chargement du formulaire consultation', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la consultation.', life: 3000 });
    } finally {
        loading.value = false;
        readyForDirty.value = true;
    }
};

const handleScroll = () => {
    isIndicatorFloating.value = window.scrollY > 180;
};

const handleBeforeUnload = (event) => {
    if (!dirtySectionsList.value.length) return;
    event.preventDefault();
    event.returnValue = '';
};

const confirmLeave = () => new Promise((resolve) => {
    confirm.require({
        message: 'Des modifications ne sont pas enregistrees. Quitter le formulaire ?',
        header: 'Confirmation',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Quitter',
        rejectLabel: 'Rester',
        accept: () => resolve(true),
        reject: () => resolve(false)
    });
});

onBeforeRouteLeave(async () => {
    if (allowRouteLeaveAfterCloture.value) return true;
    if (!dirtySectionsList.value.length) return true;
    return await confirmLeave();
});

onMounted(() => {
    loadData();
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('beforeunload', handleBeforeUnload);
});

onBeforeUnmount(() => {
    clearTimeout(autosaveTimer);
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('beforeunload', handleBeforeUnload);
});

const saveMotifSection = async ({ silent = false } = {}) => {
    if (!dirty.motif) return;
    setSaving('motif', true);
    try {
        await saveMotif(ficheId.value, data.motif, token);
        clearDirty(['motif']);
        if (!silent) toast.add({ severity: 'success', summary: 'Motif enregistré', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde motif', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde motif impossible.' });
    } finally {
        setSaving('motif', false);
    }
};

const saveExamensSection = async ({ silent = false } = {}) => {
    if (!dirty.examens) return;
    setSaving('examens', true);
    try {
        await saveExamens(ficheId.value, data.examens, token);
        clearDirty(['examens']);
        if (!silent) toast.add({ severity: 'success', summary: 'Examens enregistrés', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde examens', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde examens impossible.' });
    } finally {
        setSaving('examens', false);
    }
};

const saveTraitementsSection = async ({ silent = false } = {}) => {
    if (!dirty.traitements) return;
    setSaving('traitements', true);
    try {
        await saveTraitementsDocuments(ficheId.value, data.traitements, token);
        clearDirty(['traitements']);
        if (!silent) toast.add({ severity: 'success', summary: 'Traitements enregistrés', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde traitements', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde traitements impossible.' });
    } finally {
        setSaving('traitements', false);
    }
};

const saveDevisSection = async ({ silent = false } = {}) => {
    if (!dirty.devis) return;
    setSaving('devis', true);
    try {
        const payload = {
            date: formatDateApi(data.devis.date),
            contenus: (data.devis.services || []).map((s) => ({ designation: s.designation, qte: s.qte || 1, montant: s.montant || 0 }))
        };
        await saveDevis(ficheId.value, payload, token);
        clearDirty(['devis']);
        if (!silent) toast.add({ severity: 'success', summary: 'Devis enregistré', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde devis', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde devis impossible.' });
    } finally {
        setSaving('devis', false);
    }
};

const ensureMedecinSelected = ({ silent = false } = {}) => {
    const medecinId = Number(data.consultation?.medecinId ?? Number.NaN);
    const isValid = Number.isFinite(medecinId) && medecinId > 0;
    if (!isValid && !silent) {
        toast.add({ severity: 'warn', summary: 'Médecin requis', detail: 'Veuillez sélectionner un médecin avant de sauvegarder ou clôturer.', life: 3000 });
    }
    return isValid;
};

const saveConsultSection = async ({ silent = false } = {}) => {
    if (!dirty.consult && !dirty.ordonnances) return;
    if (!ensureMedecinSelected({ silent })) return;
    setSaving('consult', true);
    try {
        const payload = {
            ...data.consultation,
            medecinId: data.consultation?.medecinId ? Number(data.consultation.medecinId) : null,
            infirmierId: Array.isArray(data.consultation.infirmierIds)
                ? data.consultation.infirmierIds[0] ?? null
                : data.consultation.infirmierIds
        };

        if (dirty.ordonnances && Array.isArray(ordonnanceDraft.value?.lignes) && ordonnanceDraft.value.lignes.length > 0) {
            payload.ordonnance = ordonnanceDraft.value;
        }

        await saveConsultation(ficheId.value, consultId.value, payload, token);

        if (payload.ordonnance) {
            ordonnanceModalVisible.value = false;
            await fetchOrdonnances();
            clearDirty(['consult', 'ordonnances']);
        } else {
            clearDirty(['consult']);
        }

        if (!silent) toast.add({ severity: 'success', summary: 'Consultation enregistrée', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde consultation', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde consultation impossible.' });
    } finally {
        setSaving('consult', false);
    }
};

const saveOrdonnanceSection = async ({ silent = false } = {}) => {
    await saveConsultSection({ silent });
};

const fetchOrdonnances = async () => {
    try {
        data.ordonnances = await loadOrdonnances(consultId.value, token);
    } catch (error) {
        console.error('Erreur chargement ordonnances', error);
    }
};

const saveAll = async ({ silent = false } = {}) => {
    await Promise.all([
        saveMotifSection({ silent }),
        saveExamensSection({ silent }),
        saveTraitementsSection({ silent }),
        saveDevisSection({ silent }),
        saveConsultSection({ silent })
    ]);
};

const handleCloture = () => {
    if (!ensureMedecinSelected()) return;

    confirm.require({
        message: 'Clôturer définitivement cette consultation ?',
        header: 'Confirmation',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Clôturer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            setSaving('consult', true);
            try {
                await saveConsultSection({ silent: true });
                await closeConsultation(ficheId.value, consultId.value, token);
                toast.add({ severity: 'success', summary: 'Consultation clôturée' });
                allowRouteLeaveAfterCloture.value = true;
                router.replace({ name: 'consultations-table' });
            } catch (error) {
                if (isClosedConsultationError(error)) {
                    redirectClosedConsultation();
                    return;
                }
                console.error('Erreur clôture', error);
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Clôture impossible.' });
            } finally {
                setSaving('consult', false);
            }
        }
    });
};

const ensureConsultPatientLists = () => {
    if (!Array.isArray(data.patient.antecedents)) data.patient.antecedents = [];
    if (!Array.isArray(data.patient.allergies)) data.patient.allergies = [];
};

const handleSaveAntecedent = async (payload) => {
    if (!data.patient?.id) return;
    savingAntecedent.value = true;
    try {
        const res = await addPatientAntecedent(data.patient.id, payload, token);
        if (res?.antecedent) {
            ensureConsultPatientLists();
            data.patient.antecedents = [res.antecedent, ...data.patient.antecedents];
        }
        toast.add({ severity: 'success', summary: 'Antécédent ajouté', life: 2000 });
        showAntecedentDialog.value = false;
    } catch (error) {
        console.error('Erreur ajout antécédent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'antécédent." });
    } finally {
        savingAntecedent.value = false;
    }
};

const handleSaveAllergy = async (payload) => {
    if (!data.patient?.id) return;
    savingAllergy.value = true;
    try {
        const res = await addPatientAllergy(data.patient.id, payload, token);
        if (res?.allergy) {
            ensureConsultPatientLists();
            data.patient.allergies = [res.allergy, ...data.patient.allergies];
        }
        toast.add({ severity: 'success', summary: 'Allergie ajoutée', life: 2000 });
        showAllergyDialog.value = false;
    } catch (error) {
        console.error('Erreur ajout allergie', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'allergie." });
    } finally {
        savingAllergy.value = false;
    }
};

const handleDeleteAntecedent = async (item) => {
    if (!data.patient?.id || !item?.id) return;
    try {
        await deletePatientAntecedent(data.patient.id, item.id, token);
        ensureConsultPatientLists();
        data.patient.antecedents = data.patient.antecedents.filter((a) => a.id !== item.id);
        toast.add({ severity: 'success', summary: 'Antécédent supprimé', life: 2000 });
    } catch (error) {
        console.error('Erreur suppression antécédent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.' });
    }
};

const handleDeleteAllergy = async (item) => {
    if (!data.patient?.id || !item?.id) return;
    try {
        await deletePatientAllergy(data.patient.id, item.id, token);
        ensureConsultPatientLists();
        data.patient.allergies = data.patient.allergies.filter((a) => a.id !== item.id);
        toast.add({ severity: 'success', summary: 'Allergie supprimée', life: 2000 });
    } catch (error) {
        console.error('Erreur suppression allergie', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.' });
    }
};

function getStatutSeverity(statut) {
            const severities = {
                'En cours': 'warning',
                'Clôturé': 'success',
                'Annulé': 'danger',
                'Reporté': 'info'
            };
            return severities[statut] || 'info';
        }

const openOrdonnanceModal = () => {
    ordonnanceDraft.value = {
        date: new Date().toISOString().slice(0, 10),
        medecinNom: selectedMedecinLabel.value || '',
        note: '',
        lignes: []
    };
    ordonnanceModalVisible.value = true;
};

const goBack = () => router.back();

const ageNumber = computed(() => computeAgeYears(data.patient.dateNaissance || data.patient.age));
const isMedecinUser = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));

const isClosedConsultationError = (error) => Number(error?.response?.status) === 409;

const redirectClosedConsultation = () => {
    allowRouteLeaveAfterCloture.value = true;
    toast.add({ severity: 'warn', summary: 'Consultation clôturée', detail: 'Cette consultation est déjà clôturée.', life: 2500 });
    router.replace({ name: 'consultations-table' });
};

function hydrateFromResponse(res) {
    const fiche = res.fiche || {};
    const consultation = res.consultation || {};

    data.patient = {
        id: res.patient?.id ?? null,
        nom: res.patient?.nom ?? '',
        prenom: res.patient?.prenom ?? '',
        telephone: res.patient?.telephone ?? '',
        sexe: res.patient?.sexe ?? '',
        dateNaissance: res.patient?.age ?? null,
        allergies: Array.isArray(res.patient?.allergies) ? res.patient.allergies : [],
        antecedents: Array.isArray(res.patient?.antecedents) ? res.patient.antecedents : []
    };

    data.motif = {
        motif: fiche.motif ?? '',
        histoireMaladie: fiche.histoireMaladie ?? '',
        soinsAnterieurs: fiche.soinsAnterieurs ?? ''
    };

    data.examens = {
        exoInspection: fiche.exoInspection ?? '',
        exoPalpation: fiche.exoPalpation ?? '',
        endoInspection: fiche.endoInspection ?? '',
        endoPalpation: fiche.endoPalpation ?? '',
        occlusion: fiche.occlusion ?? '',
        examenParodontal: fiche.examenParodontal ?? '',
        diagnostic: fiche.diagnostic ?? '',
        toothsCheck: fiche.examens ?? fiche.toothsCheck ?? {}
    };

    data.traitements = {
        traitementUrgence: fiche.traitementUrgence ?? '',
        traitementDentaire: fiche.traitementDentaire ?? '',
        traitementParodontal: fiche.traitementParodontal ?? '',
        traitementOrthodontique: fiche.traitementOrthodontique ?? '',
        autres: fiche.autres ?? '',
        documents: Array.isArray(fiche.documents)
            ? fiche.documents.map((d) => ({
                titre: d.libelle ?? '',
                description: d.description ?? '',
                date: d.dateDossier ?? '',
                url: d.url ?? '',
                fichier: null
            }))
            : []
    };

    const devis = fiche.devis || null;
    data.devis = devis
        ? {
            id: devis.id,
            date: devis.date ? new Date(devis.date) : null,
            services: (devis.contenus || []).map((c) => ({
                designation: c.designation ?? '',
                qte: c.qte ?? 1,
                montant: c.montant ?? 0
            }))
        }
        : { date: null, services: [] };

    data.sessions = Array.isArray(fiche.consultations)
        ? fiche.consultations.map((s) => ({
            id: s.id,
            date: s.date,
            medecin: s.medecin,
            infirmier: s.infirmier,
            salle: s.salle,
            noteSeance: s.noteSeance || s.note || '',
            actes: []
        }))
        : [];

    data.consultation = {
        ficheId: fiche.id ?? null,
        type: consultation.type ?? '',
        medecinId: consultation.medecin?.id ?? consultation.medecinId ?? consultation.medecin_id ?? null,
        infirmierIds: consultation.infirmier?.id ? [consultation.infirmier.id] : [],
        salleId: consultation.salle?.id ?? null,
        noteSeance: consultation.noteSeance ?? '',
        actes: Array.isArray(res.actes)
            ? res.actes.map((a) => ({
                dent: a.dent ?? '',
                type: a.type ?? '',
                description: a.description ?? '',
                quantite: a.quantite ?? 1,
                prix: a.prix ?? 0
            }))
            : []
    };

    data.medecins = res.medecins || data.medecins;
    data.infirmiers = res.infirmiers || data.infirmiers;
    data.salles = res.salles || data.salles;
}

const handlePrintOrdonnance = async (ordo) => {
    if (!ordo?.id) return;
    try {
        const res = await fetchOrdonnancePrintData(ordo.id, token);
        await printComponent(PrintOrdonnanceBody, { data: res.data });
    } catch (error) {
        console.error('Erreur impression ordonnance', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer l'ordonnance." });
    }
};
</script>

<!-- ConsultationForm.vue -->
<template>
    <div class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <ConfirmDialog />
        <AppToast />

        <!-- Header Section -->
        <div class="mb-6 md:mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="pi pi-clipboard text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                                Consultation
                            </h1>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="flex items-center gap-2 px-3 py-1 bg-surface-100 dark:bg-surface-800 rounded-full">
                                    <i class="pi pi-user text-surface-500 text-sm"></i>
                                    <span class="text-sm font-medium text-surface-700 dark:text-surface-300">
                                        {{ data.patient.nom }} {{ data.patient.prenom }}
                                    </span>
                                </div>
                                <Tag v-if="data.consultation?.statut" :value="data.consultation.statut" 
                                    :severity="getStatutSeverity(data.consultation.statut)"
                                    class="px-3 py-1.5 rounded-full font-medium" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <SelectButton v-model="switcherMode" :options="displayModeOptions" option-label="label"
                        option-value="value" aria-label="Mode d'affichage"
                        class="bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-700 rounded-xl" />
                    <Button label="Retour" icon="pi pi-arrow-left" severity="secondary" outlined
                        class="rounded-xl px-5 py-3 border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                        @click="goBack" />
                </div>
            </div>

            <SaveIndicator
                v-model:auto-save-enabled="autoSaveEnabled"
                :dirty-sections="dirtySectionsList"
                :saving-count="savingCount"
                :last-saved-at="lastSavedAt"
                :floating="isIndicatorFloating"
                @save-all="() => saveAll({ silent: false })"
            />
        </div>

        <!-- Main Content Card -->
        <div class="p-6 bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
            <SectionSwitcher v-model="activeSection" :mode="switcherMode" :sections="sections"
                :init-key="sectionInitKey" class="p-0">
                
                <!-- Patient Info Section -->
                <template #infos>
                    <div class="p-6">
                        <PatientInfoCard
                            :patient="{ ...data.patient, dateNaissance: formatDate(data.patient.dateNaissance) }" />

                        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-300">Antécédents</h4>
                                    <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="showAntecedentDialog = true" />
                                </div>
                                <div v-if="data.patient.antecedents?.length" class="space-y-2">
                                    <div v-for="(item, idx) in data.patient.antecedents" :key="idx"
                                        class="flex items-start justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                        <div>
                                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.type || 'Antécédent' }}</div>
                                            <div class="text-sm text-surface-600 dark:text-surface-300">{{ item.description || '—' }}</div>
                                        </div>
                                        <Button icon="pi pi-trash" severity="danger" text rounded @click="handleDeleteAntecedent(item)" />
                                    </div>
                                </div>
                                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun antécédent renseigné.</p>
                            </div>

                            <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-300">Allergies</h4>
                                    <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="showAllergyDialog = true" />
                                </div>
                                <div v-if="data.patient.allergies?.length" class="space-y-2">
                                    <div v-for="(item, idx) in data.patient.allergies" :key="idx"
                                        class="flex items-start justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                        <div>
                                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.libelle || 'Allergie' }}</div>
                                            <div class="text-sm text-surface-600 dark:text-surface-300">{{ item.description || '—' }}</div>
                                        </div>
                                        <Button icon="pi pi-trash" severity="danger" text rounded @click="handleDeleteAllergy(item)" />
                                    </div>
                                </div>
                                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucune allergie renseignée.</p>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Other Sections -->
                <template #motif>
                    <div class="p-6">
                        <AnamneseForm v-model="data.motif" :saving="saving.motif" @save="saveMotifSection()" />
                    </div>
                </template>

                <template #examens>
                    <div class="p-6">
                        <ExamensForm v-model="data.examens" :patient-age="ageNumber" :saving="saving.examens"
                            @save="saveExamensSection()" />
                    </div>
                </template>

                <template #traitements>
                    <div class="p-6">
                        <TraitementsDocumentsForm v-model="data.traitements" :saving="saving.traitements"
                            @save="saveTraitementsSection()" />
                    </div>
                </template>

                <template #devis>
                    <div class="p-6">
                        <DevisForm v-model="data.devis" :saving="saving.devis" :soins="soinsList" @save="saveDevisSection()" />
                    </div>
                </template>

                <template #seances>
                    <div class="p-6">
                        <PastSessions :sessions="data.sessions" />
                    </div>
                </template>

                <template #consult>
                    <div class="p-6">
                        <ConsultationEnCoursForm v-model="data.consultation" :medecins="data.medecins" :soins="soinsList"
                            :formule-dentaire="data.bilans?.bilanDentaire?.formuleDentaire"
                            :infirmiers="data.infirmiers" :salles="data.salles" :ordonnances="data.ordonnances"
                            :medecin-readonly="isMedecinUser"
                            :saving="saving.consult" :medecins-options="medecinsOptions"
                            :infirmiers-options="infirmiersOptions" :salles-options="sallesOptions"
                            @save="saveConsultSection()" @cloture="handleCloture" @open-ordonnance="openOrdonnanceModal"
                            @print-ordonnance="handlePrintOrdonnance" />
                    </div>
                </template>
            </SectionSwitcher>
        </div>

        <!-- Stats Cards for Quick Overview -->
        <!-- <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-5 border border-blue-200/50 dark:border-blue-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Âge patient</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ ageNumber }} ans</p>
                    </div>
                    <i class="pi pi-calendar text-2xl text-blue-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-2xl p-5 border border-emerald-200/50 dark:border-emerald-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">Séances passées</p>
                        <p class="text-2xl font-bold text-emerald-900 dark:text-emerald-100 mt-2">{{ data.sessions.length }}</p>
                    </div>
                    <i class="pi pi-history text-2xl text-emerald-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">Examens</p>
                        <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">{{ data.examens?.length || 0 }}</p>
                    </div>
                    <i class="pi pi-chart-line text-2xl text-amber-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-5 border border-purple-200/50 dark:border-purple-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-purple-700 dark:text-purple-300 font-medium">Statut consultation</p>
                        <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-2">
                            {{ data.consultation?.statut || 'En cours' }}
                        </p>
                    </div>
                    <i class="pi pi-info-circle text-2xl text-purple-500"></i>
                </div>
            </div>
        </div> -->

        <AntecedentDialogForm v-model="showAntecedentDialog" :loading="savingAntecedent" @save="handleSaveAntecedent" />
        <AllergyDialogForm v-model="showAllergyDialog" :loading="savingAllergy" @save="handleSaveAllergy" />

        <OrdonnanceModal
            v-model="ordonnanceDraft"
            v-model:visible="ordonnanceModalVisible"
            :medecin-readonly="true"
            :saving="saving.consult"
            @save="saveOrdonnanceSection()"
        />
    </div>
</template>
  
