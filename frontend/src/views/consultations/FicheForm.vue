<script setup>
import ConsultationEnCoursForm from '@/components/consultations/ConsultationEnCoursForm.vue';
import DevisForm from '@/components/consultations/DevisForm.vue';
import OrdonnanceModal from '@/components/consultations/OrdonnanceModal.vue';
import SaveIndicator from '@/components/consultations/SaveIndicator.vue';
import SectionSwitcher from '@/components/consultations/SectionSwitcher.vue';
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintFicheV2Body from '@/components/print/PrintFicheV2Body.vue';
import PrintOrdonnanceBody from '@/components/print/PrintOrdonnanceBody.vue';
import EntretienVerbalForm from '@/components/fiche-medicale/EntretienVerbalForm.vue';
import ExamensFicheForm from '@/components/fiche-medicale/ExamensFicheForm.vue';
import FicheBilansForm from '@/components/fiche-medicale/FicheBilansForm.vue';
import FicheDocumentsForm from '@/components/fiche-medicale/FicheDocumentsForm.vue';
import FichePatientInfoSection from '@/components/fiche-medicale/FichePatientInfoSection.vue';
import FichePlanTraitementForm from '@/components/fiche-medicale/FichePlanTraitementForm.vue';
import SeancesSection from '@/components/fiche-medicale/SeancesSection.vue';
import { useConsultationsForm } from '@/composables/useConsultationsForm';
import { usePrinter } from '@/composables/usePrinter';
import { defaultSoinList, normalizeSoinList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent } from '@/services/patients';
import { fetchDevisPrintData, fetchOrdonnancePrintData, fetchPatientFichePrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createConsultationsFormTour } from '@/tours/consultationsFormTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import Button from 'primevue/button';
import AutoComplete from 'primevue/autocomplete';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import SelectButton from 'primevue/selectbutton';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useLayout } from '@/layout/composables/layout';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const token = localStorage.getItem('token');
const auth = useAuthStore();
const { printComponent } = usePrinter();

const ficheId = ref(route.query.ficheId ? Number(route.query.ficheId) : null);
const consultId = ref(route.query.id ? Number(route.query.id) : null);
const mode = computed(() => (route.query.mode === 'new-fiche' ? 'new-fiche' : 'continue'));
 
const pageLoading = ref(false);
const loadErrorMessage = ref('');

const {
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
    saveEntretienSection: saveEntretien,
    saveExamensSection: saveExamens,
    saveDocumentsSection: saveDocuments,
    saveBilansSection: saveBilans,
    savePlanTraitementSection: savePlanTraitement,
    saveDevisSection: saveDevis,
    saveConsultSection: saveConsult,
    closeConsult
} = useConsultationsForm({ ficheId, consultId, token, mode });

const ordonnanceModalVisible = ref(false);
const ordonnanceDraft = ref({ date: '', medecinNom: '', note: '', lignes: [] });
const showAntecedentDialog = ref(false);
const showAllergyDialog = ref(false);
const savingAntecedent = ref(false);
const savingAllergy = ref(false);
const isIndicatorFloating = ref(false);
const allowRouteLeaveAfterCloture = ref(false);
const isGuidedTourStarting = ref(false);
const isMedecinOptionalOnCreation = ref(false);
const hidePatientPhoneForMedecins = ref(false);
const ficheFormSimplifie = ref(false);
const showRdvDialog = ref(false);
const soinsList = ref([...defaultSoinList]);
const examensTypeOptions = ref(['Bacteriologique', 'Serologique', 'Histologique', 'Radiologique', 'Autre']);
const traitementTypeOptions = ref(['Urgence', 'Dentaires', 'Parodontaux', 'Orthodontiques', 'Autres']);
const allergyTypeOptions = ref(['Médicamenteuses', 'Alimentaires', 'Environnementales', 'Autres']);
const antecedentTypeOptions = ref(['Personnel', 'Familial', 'Médical']);
const examensTypeSuggestions = ref([]);
const traitementTypeSuggestions = ref([]);
const shouldHidePatientPhoneForMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')) && !Boolean(auth.user?.roles?.includes('ROLE_ADMIN')) && hidePatientPhoneForMedecins.value);
const isSimplifiedFicheFormEnabled = computed(() => ficheFormSimplifie.value === true);

const displayModeOptions = [
    { label: 'Onglets', value: 'tabs' },
    { label: 'Sidebar', value: 'sidebar' }
];

const toFullName = (employee = {}) => {
    return employee.label
        || employee.fullName
        || employee.fullname
        || employee.name
        || employee.FullName
        || employee.Fullname
        || `${employee.prenom ?? ''} ${employee.nom ?? ''}`.trim()
        || employee.nom
        || '';
};

const medecinsOptions = computed(() => (data.medecins || []).map((m) => ({
    id: m.id,
    label: toFullName(m)
})));

const infirmiersOptions = computed(() => (data.infirmiers || []).map((i) => ({
    id: i.id,
    label: toFullName(i)
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

const computeAgeYears = (value) => {
    if (!value) return 0;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return Number(value) || 0;
    const diff = Date.now() - d.getTime();
    return Math.max(0, Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25)));
};

const ageNumber = computed(() => computeAgeYears(data.patient.dateNaissance || data.patient.age));
const isMedecinUser = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const hasUnsavedChanges = computed(() => dirtySectionsList.value.length > 0);

const isClosedConsultationError = (error) => Number(error?.response?.status) === 409;

const loadConsultationPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        isMedecinOptionalOnCreation.value = settings?.requireMedecinOnConsultationCreation === false;
        hidePatientPhoneForMedecins.value = settings?.hidePatientPhoneForMedecins === true;
        ficheFormSimplifie.value = settings?.ficheFormSimplifie === true;
        soinsList.value = normalizeSoinList(settings?.soinsList);
        examensTypeOptions.value = Array.isArray(settings?.examensTypes) && settings.examensTypes.length ? settings.examensTypes : examensTypeOptions.value;
        traitementTypeOptions.value = Array.isArray(settings?.traitementTypes) && settings.traitementTypes.length ? settings.traitementTypes : traitementTypeOptions.value;
        allergyTypeOptions.value = Array.isArray(settings?.allergyTypes) && settings.allergyTypes.length ? settings.allergyTypes : allergyTypeOptions.value;
        antecedentTypeOptions.value = Array.isArray(settings?.antecedentTypes) && settings.antecedentTypes.length ? settings.antecedentTypes : antecedentTypeOptions.value;
    } catch (error) {
        console.error('Erreur chargement politique consultation', error);
        isMedecinOptionalOnCreation.value = false;
        hidePatientPhoneForMedecins.value = false;
        ficheFormSimplifie.value = false;
        soinsList.value = [...defaultSoinList];
    }
};

const searchExamensTypes = (event) => {
    const query = String(event?.query || '').toLowerCase().trim();
    examensTypeSuggestions.value = query
        ? examensTypeOptions.value.filter((item) => String(item).toLowerCase().includes(query))
        : examensTypeOptions.value;
};

const searchTraitementTypes = (event) => {
    const query = String(event?.query || '').toLowerCase().trim();
    traitementTypeSuggestions.value = query
        ? traitementTypeOptions.value.filter((item) => String(item).toLowerCase().includes(query))
        : traitementTypeOptions.value;
};

const redirectClosedConsultation = () => {
    Object.keys(dirty).forEach((key) => {
        dirty[key] = false;
    });
    allowRouteLeaveAfterCloture.value = true;
    toast.add({ severity: 'warn', summary: 'Consultation clôturée', detail: 'Cette consultation est déjà clôturée.', life: 2500 });
    router.replace({ name: 'consultations-table' });
};

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
        case 'entretien':
            return hasValue(data.entretien);
        case 'examens':
            return hasValue(data.examens);
        case 'documents':
            return hasValue(data.documents);
        case 'bilans':
            return hasValue(data.bilans);
        case 'plan-traitement':
            return hasValue(data.planTraitement);
        case 'devis':
            return hasValue([data.devis?.date, data.devis?.services]);
        case 'synthese':
            return hasValue([data.entretien, data.examens?.examensLabo, data.bilans, data.planTraitement, data.documents]);
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
    if (dirty[key]) return { status: 'dirty', label: 'Modifie', saveDisabled: false };
    return { status: 'saved', label: 'Sauvegarde', saveDisabled: true };
};

const sections = computed(() => {
    const entretienStatus = getSectionStatus('entretien');
    const examensStatus = getSectionStatus('examens');
    const documentsStatus = getSectionStatus('documents');
    const bilansStatus = getSectionStatus('bilans');
    const planTraitementStatus = getSectionStatus('planTraitement');
    const devisStatus = getSectionStatus('devis');
    const consultStatus = getSectionStatus('consult');

    const syntheseSaving = Boolean(saving.entretien || saving.examens || saving.documents || saving.bilans || saving.planTraitement);
    const syntheseDirty = Boolean(dirty.entretien || dirty.examens || dirty.documents || dirty.bilans || dirty.planTraitement);
    const syntheseStatus = syntheseSaving ? 'saving' : syntheseDirty ? 'dirty' : 'saved';
    const syntheseStatusLabel = syntheseSaving ? 'Sauvegarde...' : syntheseDirty ? 'Modifie' : 'Sauvegarde';

    const baseSections = [
        {
            id: 'infos',
            label: 'Informations patient',
            filled: isSectionFilled('infos'),
            status: 'readonly',
            statusLabel: 'Lecture seule',
            saveDisabled: true
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
            label: 'Seances',
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

    const detailedSections = [
        {
            id: 'entretien',
            label: 'Questionnaire médical',
            filled: isSectionFilled('entretien'),
            status: entretienStatus.status,
            statusLabel: entretienStatus.label,
            saveDisabled: entretienStatus.saveDisabled,
            saving: saving.entretien,
            onSave: () => saveEntretienSection()
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
            id: 'documents',
            label: 'Images & documents',
            filled: isSectionFilled('documents'),
            status: documentsStatus.status,
            statusLabel: documentsStatus.label,
            saveDisabled: documentsStatus.saveDisabled,
            saving: saving.documents,
            onSave: () => saveDocumentsSection()
        },
        {
            id: 'bilans',
            label: 'Bilans',
            filled: isSectionFilled('bilans'),
            status: bilansStatus.status,
            statusLabel: bilansStatus.label,
            saveDisabled: bilansStatus.saveDisabled,
            saving: saving.bilans,
            onSave: () => saveBilansSection()
        },
        {
            id: 'plan-traitement',
            label: 'Plan de traitement',
            filled: isSectionFilled('plan-traitement'),
            status: planTraitementStatus.status,
            statusLabel: planTraitementStatus.label,
            saveDisabled: planTraitementStatus.saveDisabled,
            saving: saving.planTraitement,
            onSave: () => savePlanTraitementSection()
        }
    ];

    if (isSimplifiedFicheFormEnabled.value) {
        return [
            baseSections[0],
            {
                id: 'synthese',
                label: 'Synthèse clinique',
                filled: isSectionFilled('synthese'),
                status: syntheseStatus,
                statusLabel: syntheseStatusLabel,
                saveDisabled: !syntheseDirty,
                saving: syntheseSaving,
                onSave: () => saveSyntheseSection()
            },
            ...baseSections.slice(1)
        ];
    }

    return [baseSections[0], ...detailedSections, ...baseSections.slice(1)];
});

const saveSyntheseSection = async ({ silent = false } = {}) => {
    await Promise.all([
        saveEntretienSection({ silent }),
        saveExamensSection({ silent }),
        saveDocumentsSection({ silent }),
        saveBilansSection({ silent }),
        savePlanTraitementSection({ silent })
    ]);
};

const createGroupKey = () => {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return `doc_${Date.now()}_${Math.random().toString(16).slice(2)}`;
};

const addExamComplementaireRow = () => {
    const current = Array.isArray(data.examens?.examensLabo) ? data.examens.examensLabo : [];
    data.examens = {
        ...data.examens,
        examensLabo: [...current, { type: '', description: '', date: null, resultat: '' }]
    };
};

const removeExamComplementaireRow = (index) => {
    const current = Array.isArray(data.examens?.examensLabo) ? data.examens.examensLabo : [];
    data.examens = {
        ...data.examens,
        examensLabo: current.filter((_, idx) => idx !== index)
    };
};

const addPlanRow = () => {
    const plans = Array.isArray(data.planTraitement) ? data.planTraitement : [];
    data.planTraitement = [
        ...plans,
        {
            planIndex: plans.length + 1,
            type: '',
            dateSupposed: null,
            description: ''
        }
    ];
};

const removePlanRow = (index) => {
    const plans = Array.isArray(data.planTraitement) ? data.planTraitement : [];
    data.planTraitement = plans.filter((_, idx) => idx !== index).map((item, idx) => ({
        ...item,
        planIndex: idx + 1
    }));
};

const handleRdvSaved = () => {
    showRdvDialog.value = false;
    toast.add({ severity: 'success', summary: 'Rendez-vous créé', life: 2200 });
};

const saveEntretienSection = async ({ silent = false } = {}) => {
    if (!dirty.entretien) return;
    try {
        await saveEntretien();
        if (!silent) toast.add({ severity: 'success', summary: 'Interrogatoire enregistre', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde entretien', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde entretien impossible.' });
    }
};

const saveExamensSection = async ({ silent = false } = {}) => {
    if (!dirty.examens) return;
    try {
        await saveExamens();
        if (!silent) toast.add({ severity: 'success', summary: 'Examens enregistres', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde examens', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde examens impossible.' });
    }
};

const saveDocumentsSection = async ({ silent = false } = {}) => {
    if (!dirty.documents) return;
    try {
        await saveDocuments();
        if (!silent) toast.add({ severity: 'success', summary: 'Documents enregistres', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde documents', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde documents impossible.' });
    }
};

const saveBilansSection = async ({ silent = false } = {}) => {
    if (!dirty.bilans) return;
    try {
        await saveBilans();
        if (!silent) toast.add({ severity: 'success', summary: 'Bilans enregistres', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde bilans', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde bilans impossible.' });
    }
};

const savePlanTraitementSection = async ({ silent = false } = {}) => {
    if (!dirty.planTraitement) return;
    try {
        await savePlanTraitement();
        if (!silent) toast.add({ severity: 'success', summary: 'Plan enregistre', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde plan traitement', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde plan traitement impossible.' });
    }
};

const saveDevisSection = async ({ silent = false } = {}) => {
    if (!dirty.devis) return;
    try {
        await saveDevis();
        if (!silent) toast.add({ severity: 'success', summary: 'Devis enregistre', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde devis', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde devis impossible.' });
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
    try {
        await saveConsult({ ordonnancePayload: dirty.ordonnances ? ordonnanceDraft.value : null });
        if (dirty.ordonnances) {
            ordonnanceModalVisible.value = false;
        }
        if (!silent) toast.add({ severity: 'success', summary: 'Consultation enregistree', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        console.error('Erreur sauvegarde consultation', error);
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde consultation impossible.' });
    }
};

const saveOrdonnanceSection = async ({ silent = false } = {}) => {
    await saveConsultSection({ silent });
};

const saveAll = async ({ silent = false } = {}) => {
    await Promise.all([
        saveEntretienSection({ silent }),
        saveExamensSection({ silent }),
        saveDocumentsSection({ silent }),
        saveBilansSection({ silent }),
        savePlanTraitementSection({ silent }),
        saveDevisSection({ silent }),
        saveConsultSection({ silent })
    ]);
};

watchSection(() => data.entretien, 'entretien', saveAll);
watchSection(() => data.examens, 'examens', saveAll);
watchSection(() => data.documents, 'documents', saveAll);
watchSection(() => data.bilans, 'bilans', saveAll);
watchSection(() => data.planTraitement, 'planTraitement', saveAll);
watchSection(() => data.devis, 'devis', saveAll);
watchSection(() => data.consultation, 'consult', saveAll);
watchSection(() => ordonnanceDraft.value, 'ordonnances', saveAll);

const handleSaveAntecedent = async (payload) => {
    if (!data.patient?.id) return;
    savingAntecedent.value = true;
    try {
        const res = await addPatientAntecedent(data.patient.id, payload, token);
        if (res?.antecedent) data.patient.antecedents.push(res.antecedent);
        toast.add({ severity: 'success', summary: 'Antecedent ajoute', life: 2000 });
        showAntecedentDialog.value = false;
    } catch (error) {
        console.error('Erreur ajout antecedent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'ajouter l\'antecedent.' });
    } finally {
        savingAntecedent.value = false;
    }
};

const handleSaveAllergy = async (payload) => {
    if (!data.patient?.id) return;
    savingAllergy.value = true;
    try {
        const res = await addPatientAllergy(data.patient.id, payload, token);
        if (res?.allergy) data.patient.allergies.push(res.allergy);
        toast.add({ severity: 'success', summary: 'Allergie ajoutee', life: 2000 });
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
        data.patient.antecedents = data.patient.antecedents.filter((a) => a.id !== item.id);
        toast.add({ severity: 'success', summary: 'Antecedent supprime', life: 2000 });
    } catch (error) {
        console.error('Erreur suppression antecedent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.' });
    }
};

const handleDeleteAllergy = async (item) => {
    if (!data.patient?.id || !item?.id) return;
    try {
        await deletePatientAllergy(data.patient.id, item.id, token);
        data.patient.allergies = data.patient.allergies.filter((a) => a.id !== item.id);
        toast.add({ severity: 'success', summary: 'Allergie supprimee', life: 2000 });
    } catch (error) {
        console.error('Erreur suppression allergie', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.' });
    }
};

const handleCloture = () => {
    if (!ensureMedecinSelected()) return;

    confirm.require({
        message: 'Cloturer definitivement cette consultation ?',
        header: 'Confirmation',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Cloturer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await closeConsult();
                Object.keys(dirty).forEach((key) => {
                    dirty[key] = false;
                });
                allowRouteLeaveAfterCloture.value = true;
                router.replace({ name: 'consultations-table' });
            } catch (error) {
                if (isClosedConsultationError(error)) {
                    redirectClosedConsultation();
                    return;
                }
                console.error('Erreur clôture consultation', error);
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Clôture impossible.', life: 2500 });
            }
        }
    });
};

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

const handlePrintFiche = async () => {
    const patientId = Number(data.patient?.id ?? Number.NaN);
    if (!Number.isFinite(patientId) || !ficheId.value) {
        toast.add({ severity: 'warn', summary: 'Impression', detail: 'Fiche non disponible pour impression.' });
        return;
    }

    try {
        const res = await fetchPatientFichePrintData(patientId, ficheId.value, token);
        const sectionsToPrint = isSimplifiedFicheFormEnabled.value
            ? ['synthese']
            : ['entretien', 'examens', 'images', 'plan', 'bilan', 'seances'];
        await printComponent(PrintFicheV2Body, {
            patient: res.patient,
            fiche: res.fiche,
            sections: sectionsToPrint,
            printEmpty: false
        });
    } catch (error) {
        console.error('Erreur impression fiche', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer la fiche." });
    }
};

const handlePrintDevis = async (devisEntry) => {
    const devisId = Number(devisEntry?.id ?? Number.NaN);
    if (!Number.isFinite(devisId)) {
        toast.add({ severity: 'warn', summary: 'Impression', detail: 'Ce devis doit etre sauvegarde avant impression.', life: 3000 });
        return;
    }

    try {
        const result = await fetchDevisPrintData(devisId, token);
        await printComponent(PrintDevisBody, { doc: result.doc, title: result.title || 'Devis' });
    } catch (error) {
        console.error('Erreur impression devis', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer le devis.", life: 3000 });
    }
};

const openOrdonnanceModal = () => {
    ordonnanceDraft.value = {
        date: new Date().toISOString().slice(0, 10),
        medecinNom: selectedMedecinLabel.value || '',
        note: '',
        lignes: []
    };
    ordonnanceModalVisible.value = true;
};

const handleScroll = () => {
    isIndicatorFloating.value = window.scrollY > 180;
};

const handleBeforeUnload = (event) => {
    if (!hasUnsavedChanges.value) return;
    event.preventDefault();
    event.returnValue = '';
};

const hasOpenDialogs = computed(() => showAntecedentDialog.value || showAllergyDialog.value || ordonnanceModalVisible.value || showRdvDialog.value);

const setTourSection = (sectionId) => {
    activeSection.value = sectionId;
};

const resetTourDialogs = () => {
    showAntecedentDialog.value = false;
    showAllergyDialog.value = false;
    ordonnanceModalVisible.value = false;
    showRdvDialog.value = false;
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'consultations-form' || isGuidedTourStarting.value) {
        return;
    }

    if (pageLoading.value || loading.value || hasOpenDialogs.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidee',
            detail: 'Attendez la fin du chargement et fermez les fenetres ouvertes avant de lancer le tour.',
            life: 3000
        });
        return;
    }

    isGuidedTourStarting.value = true;

    try {
        resetTourDialogs();
        await nextTick();

        const steps = createConsultationsFormTour({
            setSection: setTourSection,
            openOrdonnanceDialog: openOrdonnanceModal,
            closeAllDialogs: resetTourDialogs
        });

        await startTourGuide({
            group: 'consultations-form',
            steps,
            onAfterExit: resetTourDialogs,
            onFinish: resetTourDialogs
        });
    } catch (error) {
        console.error('Erreur lancement guided tour fiche consultation', error);
        toast.add({
            severity: 'error',
            summary: 'Aide guidee',
            detail: 'Impossible de lancer le tour de la fiche medicale.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
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
    if (!hasUnsavedChanges.value) return true;
    return await confirmLeave();
});

onMounted(async () => {
    try {
        loadErrorMessage.value = '';
        pageLoading.value = true;
        await Promise.all([loadData(), loadConsultationPolicy()]);
        if (!useLayout().isSidebarActive) useLayout().toggleMenu();
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
    
            return;
        }
        loadErrorMessage.value = 'Impossible de charger la fiche médicale.';
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la fiche médicale.', life: 3000 });
    } finally {
        pageLoading.value = false;
    }
    handleScroll();
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('beforeunload', handleBeforeUnload);
});

watch(
    () => [isMedecinUser.value, isMedecinOptionalOnCreation.value, data.consultation?.medecinId, medecinsOptions.value.length],
    () => {
        if (!isMedecinOptionalOnCreation.value) return;
        if (!isMedecinUser.value) return;
        if (data.consultation?.medecinId) return;
        const fallbackMedecinId = resolveConnectedMedecinId();
        if (!fallbackMedecinId) return;
        data.consultation = { ...data.consultation, medecinId: fallbackMedecinId };
    },
    { immediate: true }
);

watch(
    () => isSimplifiedFicheFormEnabled.value,
    (enabled) => {
        if (!enabled) return;
        if (['entretien', 'examens', 'documents', 'bilans', 'plan-traitement'].includes(activeSection.value)) {
            activeSection.value = 'synthese';
        }
    }
);

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener('beforeunload', handleBeforeUnload);
    resetTourDialogs();
    if (!useLayout().isSidebarActive) useLayout().toggleMenu();
});

const retryLoad = async () => {
    loadErrorMessage.value = '';
    pageLoading.value = true;
    try {
        await Promise.all([loadData(), loadConsultationPolicy()]);
    } catch (error) {
        if (isClosedConsultationError(error)) {
            redirectClosedConsultation();
            return;
        }
        loadErrorMessage.value = 'Impossible de charger la fiche médicale.';
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la fiche médicale.', life: 3000 });
    } finally {
        pageLoading.value = false;
    }
};

</script>

<template>
    
    <div class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <ConfirmDialog />
        <AppToast />
        
        <div v-if="!pageLoading && !loadErrorMessage">
            <div data-tour="consultations-form.header" class="mb-6 md:mb-8 gap-4 flex flex-row justify-items-strech rounded-2xl bg-surface-0/80 dark:bg-surface-800/80 backdrop-blur-sm border border-surface-200/50 dark:border-surface-700/50">
                <div class="inline-flex items-center gap-3 mb-4 p-3 ">
                    <div class="p-2.5 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600">
                        <i class="pi pi-file text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-50">Fiche medicale</h1>
                        <p class="text-sm text-surface-600 dark:text-surface-300">Suivi complet du patient</p>
                    </div>
                </div>
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div data-tour="consultations-form.navigation" class="flex items-center gap-2">
                        <Button icon="pi pi-arrow-left" label="Retour" severity="danger" @click="() => router.back()" />
                        <Button icon="pi pi-print" label="Imprimer fiche" severity="secondary" outlined @click="handlePrintFiche" />
                    </div>
                    <div data-tour="consultations-form.display-mode" class="flex items-center gap-2">
                        <SelectButton v-model="switcherMode" :options="displayModeOptions" optionLabel="label" optionValue="value" />
                    </div>
                </div>
            </div>

            <div class="p-6 bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <div data-tour="consultations-form.save-indicator">
                    <SaveIndicator
                        v-model:auto-save-enabled="autoSaveEnabled"
                        :loading="loading"
                        :saving-count="savingCount"
                        :last-saved-at="lastSavedAt"
                        :dirty-sections="dirtySectionsList"
                        :floating="isIndicatorFloating"
                        @save-all="() => saveAll({ silent: false })"
                    />
                </div>

                <div data-tour="consultations-form.switcher">
                    <SectionSwitcher v-model="activeSection" :sections="sections" :mode="switcherMode" :init-key="sectionInitKey">
                    <template #infos>
                        <div data-tour="consultations-form.section.infos">
                            <FichePatientInfoSection
                                :patient="data.patient"
                                :hide-phone="shouldHidePatientPhoneForMedecin"
                                @add-antecedent="() => (showAntecedentDialog = true)"
                                @add-allergy="() => (showAllergyDialog = true)"
                                @delete-antecedent="handleDeleteAntecedent"
                                @delete-allergy="handleDeleteAllergy"
                            />
                        </div>
                    </template>

                    <template #synthese>
                        <div class="rounded-2xl border border-surface-200/60 dark:border-surface-700/60 bg-surface-0 dark:bg-surface-900/40 p-4 md:p-5">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Synthèse clinique</h3>
                                    <p class="text-xs text-surface-500 dark:text-surface-400">Vue condensée du questionnaire, examens, bilan, plan de traitement et documents.</p>
                                </div>
                                <Button icon="pi pi-save" label="Enregistrer" size="small" :loading="saving.entretien || saving.examens || saving.documents || saving.bilans || saving.planTraitement" @click="saveSyntheseSection" />
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-[0.88fr_1.12fr] gap-4">
                                <div class="space-y-4">
                                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                        <label class="text-sm font-semibold text-surface-700 dark:text-surface-200 uppercase tracking-wide">Anamnèse</label>
                                        <textarea
                                            v-model="data.entretien.anamnese"
                                            rows="4"
                                            class="mt-2 w-full rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-3 py-2 text-[0.95rem]"
                                            placeholder="Résumé clinique du patient"
                                        ></textarea>
                                    </div>

                                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0/80 dark:bg-surface-900/30 p-3.5">
                                        <div class="mb-3">
                                            <label class="text-sm font-semibold text-surface-700 dark:text-surface-200 uppercase tracking-wide">Antécédents & allergies</label>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3">
                                            <div class="rounded-lg border border-surface-200/80 dark:border-surface-700/80 bg-surface-50/80 dark:bg-surface-800/40 p-2.5">
                                                <div class="flex items-center justify-between mb-2">
                                                    <p class="text-sm font-semibold text-surface-700 dark:text-surface-200">Antécédents</p>
                                                    <Button icon="pi pi-plus" label="Ajouter" text size="small" @click="showAntecedentDialog = true" />
                                                </div>
                                                <div class="space-y-2 max-h-28 overflow-auto pr-1">
                                                    <div v-for="item in data.patient.antecedents" :key="`a-${item.id}`" class="flex items-start justify-between gap-2 rounded-md border border-surface-200 dark:border-surface-700 bg-white/80 dark:bg-surface-900/60 px-2 py-1.5">
                                                        <div class="text-sm text-surface-700 dark:text-surface-300 leading-5">
                                                            <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 px-2 py-0.5 text-xs font-medium mr-1.5">{{ item.type || 'Antécédent' }}</span>
                                                            <span>{{ item.description || '—' }}</span>
                                                        </div>
                                                        <Button icon="pi pi-trash" text severity="danger" size="small" @click="handleDeleteAntecedent(item)" />
                                                    </div>
                                                    <div v-if="!data.patient.antecedents?.length" class="text-sm text-surface-500 dark:text-surface-400">Aucun antécédent enregistré.</div>
                                                </div>
                                            </div>

                                            <div class="rounded-lg border border-surface-200/80 dark:border-surface-700/80 bg-surface-50/80 dark:bg-surface-800/40 p-2.5">
                                                <div class="flex items-center justify-between mb-2">
                                                    <p class="text-sm font-semibold text-surface-700 dark:text-surface-200">Allergies</p>
                                                    <Button icon="pi pi-plus" label="Ajouter" text size="small" @click="showAllergyDialog = true" />
                                                </div>
                                                <div class="space-y-2 max-h-28 overflow-auto pr-1">
                                                    <div v-for="item in data.patient.allergies" :key="`al-${item.id}`" class="flex items-start justify-between gap-2 rounded-md border border-surface-200 dark:border-surface-700 bg-white/80 dark:bg-surface-900/60 px-2 py-1.5">
                                                        <div class="text-sm text-surface-700 dark:text-surface-300 leading-5">
                                                            <span class="inline-flex items-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 px-2 py-0.5 text-xs font-medium mr-1.5">{{ item.libelle || 'Allergie' }}</span>
                                                            <span>{{ item.description || '—' }}</span>
                                                        </div>
                                                        <Button icon="pi pi-trash" text severity="danger" size="small" @click="handleDeleteAllergy(item)" />
                                                    </div>
                                                    <div v-if="!data.patient.allergies?.length" class="text-sm text-surface-500 dark:text-surface-400">Aucune allergie enregistrée.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-semibold text-surface-700 dark:text-surface-200 uppercase tracking-wide">Prochain rendez-vous</p>
                                                <p class="text-sm text-surface-500 dark:text-surface-400">Créer rapidement un nouveau rendez-vous pour ce patient.</p>
                                            </div>
                                            <Button icon="pi pi-calendar-plus" label="Créer" size="small" @click="showRdvDialog = true" />
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                        <FicheDocumentsForm v-model="data.documents" :saving="saving.documents" :compact="true" @save="saveDocumentsSection" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="text-sm font-semibold text-surface-700 dark:text-surface-200 uppercase tracking-wide">Examens</label>
                                            <Button icon="pi pi-plus" label="Ligne" text size="small" @click="addExamComplementaireRow" />
                                        </div>
                                        <div class="space-y-2 max-h-72 overflow-auto pr-1">
                                            <div v-for="(item, examIndex) in data.examens.examensLabo" :key="examIndex" class="grid grid-cols-12 gap-2 items-center rounded-md border border-surface-300 dark:border-surface-700 p-2">
                                                <AutoComplete
                                                    v-model="item.type"
                                                    :suggestions="examensTypeSuggestions"
                                                    dropdown
                                                    class="col-span-4"
                                                    inputClass="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm"
                                                    placeholder="Type"
                                                    @complete="searchExamensTypes"
                                                />
                                                <InputText v-model="item.description" class="col-span-8 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm min-h-full" placeholder="Description" />
                                                <Textarea v-model="item.resultat" class="col-span-8 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" placeholder="Résultat" />
                                                <DatePicker v-model="item.date" showIcon fluid iconDisplay="input" class="col-span-3 rounded bg-transparent text-sm self-start" placeholder="Date" /> 
                                                <Button icon="pi pi-trash" text severity="danger" size="small" class="col-span-1 justify-self-end self-start" @click="removeExamComplementaireRow(examIndex)" />
                                            </div>
                                            <div v-if="!data.examens.examensLabo?.length" class="text-sm text-surface-500 dark:text-surface-400">Aucun examen complémentaire.</div>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                        <label class="text-sm font-semibold text-surface-700 dark:text-surface-200 uppercase tracking-wide">Bilan</label>
                                        <textarea
                                            v-model="data.bilans.diagnosticPositif"
                                            rows="3"
                                            class="mt-2 w-full rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-3 py-2 text-[0.95rem]"
                                            placeholder="Bilan"
                                        ></textarea>
                                        <label class="mt-3 block text-sm font-semibold text-surface-700 dark:text-surface-200 uppercase tracking-wide">Avis médicaux</label>
                                        <textarea
                                            v-model="data.bilans.avisMedicales"
                                            rows="3"
                                            class="mt-2 w-full rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-3 py-2 text-[0.95rem]"
                                            placeholder="Avis médicaux"
                                        ></textarea>
                                    </div>

                                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="text-sm font-semibold text-surface-700 dark:text-surface-200 uppercase tracking-wide">Plan de traitement</label>
                                            <Button icon="pi pi-plus" label="Ajout rapide" text size="small" @click="addPlanRow" />
                                        </div>
                                        <div class="space-y-2  overflow-auto pr-1">
                                            <div v-for="(plan, planIndex) in data.planTraitement" :key="plan.id || planIndex" class="grid grid-cols-12 gap-2 items-center rounded-md border border-surface-200 dark:border-surface-700 p-2">
                                                <AutoComplete
                                                    v-model="plan.type"
                                                    :suggestions="traitementTypeSuggestions"
                                                    dropdown
                                                    class="col-span-7"
                                                    inputClass="w-full rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm"
                                                    placeholder="Type"
                                                    @complete="searchTraitementTypes"
                                                />

                                                <DatePicker v-model="plan.dateSupposed" showIcon fluid class="col-span-4 rounded  bg-transparent px-2 py-1 text-sm" />
                                                <Button icon="pi pi-trash" text severity="danger" size="small" class="col-span-1 justify-self-center" @click="removePlanRow(planIndex)" />
                                                <Textarea v-model="plan.description" class="col-span-11 rounded border border-surface-300 dark:border-surface-600 bg-transparent px-2 py-1 text-sm" placeholder="Description" />
                                                
                                            </div>
                                            <div v-if="!data.planTraitement?.length" class="text-sm text-surface-500 dark:text-surface-400">Aucun plan ajouté.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #entretien>
                        <div data-tour="consultations-form.section.entretien">
                            <EntretienVerbalForm
                                v-model="data.entretien"
                                :saving="saving.entretien"
                                :patient-sex="data.patient?.sexe"
                                @save="saveEntretienSection"
                            />
                        </div>
                    </template>

                    <template #examens>
                        <div data-tour="consultations-form.section.examens">
                            <ExamensFicheForm v-model="data.examens" :saving="saving.examens" @save="saveExamensSection" />
                        </div>
                    </template>

                    <template #documents>
                        <div data-tour="consultations-form.section.documents">
                            <FicheDocumentsForm v-model="data.documents" :saving="saving.documents" @save="saveDocumentsSection" />
                        </div>
                    </template>

                    <template #bilans>
                        <div data-tour="consultations-form.section.bilans">
                            <FicheBilansForm v-model="data.bilans" :saving="saving.bilans" :patient-age="ageNumber" @save="saveBilansSection" />
                        </div>
                    </template>

                    <template #plan-traitement>
                        <div data-tour="consultations-form.section.plan-traitement">
                            <FichePlanTraitementForm v-model="data.planTraitement" :saving="saving.planTraitement" @save="savePlanTraitementSection" />
                        </div>
                    </template>

                    <template #devis>
                        <div data-tour="consultations-form.section.devis">
                            <DevisForm
                                v-model="data.devis"
                                :saving="saving.devis"
                                :soins="soinsList"
                                @save="saveDevisSection"
                                @print-devis="handlePrintDevis"
                            />
                        </div>
                    </template>

                    <template #seances>
                        <div data-tour="consultations-form.section.seances">
                            <SeancesSection :sessions="data.sessions" />
                        </div>
                    </template>

                    <template #consult>
                        <div data-tour="consultations-form.section.consult">
                            <ConsultationEnCoursForm
                                v-model="data.consultation"
                                :soins="soinsList"
                                :formule-dentaire="data.bilans?.bilanDentaire?.formuleDentaire"
                                :medecins="data.medecins"
                                :medecins-options="medecinsOptions"
                                :infirmiers="data.infirmiers"
                                :infirmiers-options="infirmiersOptions"
                                :salles="data.salles"
                                :salles-options="sallesOptions"
                                :ordonnances="data.ordonnances"
                                :medecin-readonly="isMedecinUser"
                                :loading="loading"
                                :saving="saving.consult"
                                :cloture-loading="false"
                                @save="saveConsultSection"
                                @cloture="handleCloture"
                                @open-ordonnance="openOrdonnanceModal"
                                @print-ordonnance="handlePrintOrdonnance"
                            />
                        </div>
                    </template>
                    </SectionSwitcher>
                </div>
            </div>

            <div data-tour="consultations-form.dialogs">
                <AntecedentDialogForm v-model="showAntecedentDialog" :loading="savingAntecedent" :type-options="antecedentTypeOptions" @save="handleSaveAntecedent" />
                <AllergyDialogForm v-model="showAllergyDialog" :loading="savingAllergy" :type-options="allergyTypeOptions" @save="handleSaveAllergy" />
                <Dialog
                    v-model:visible="showRdvDialog"
                    modal
                    :style="{ width: '45rem' }"
                    :pt="{
                        root: 'rounded-2xl',
                        header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
                        content: 'p-0 mt-4'
                    }"
                >
                    <template #header>
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                <i class="fas fa-calendar-plus text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <div>
                                <h4 class="m-0 text-surface-900 dark:text-surface-100">Nouveau rendez-vous</h4>
                                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                                    {{ `${data.patient?.prenom || ''} ${data.patient?.nom || ''}`.trim() || 'Patient' }}
                                </p>
                            </div>
                        </div>
                    </template>
                    <FormRendezVous
                        :patient="data.patient"
                        :patient-id="data.patient?.id"
                        @saved="handleRdvSaved"
                        @cancel="showRdvDialog = false"
                    />
                </Dialog>
                <OrdonnanceModal
                    v-model="ordonnanceDraft"
                    v-model:visible="ordonnanceModalVisible"
                    :medecin-readonly="true"
                    :saving="saving.consult"
                    @save="saveOrdonnanceSection"
                />
            </div>
        </div>
        <div v-else-if="loadErrorMessage" class="flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 dark:border-amber-800/70 dark:bg-amber-950/20">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</p>
                <p class="text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
            </div>
            <Button icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryLoad" />
        </div>

        <div v-else class="flex flex-col items-center justify-center min-h-[300px]">
            <div class="relative mb-4">
            <span class="block w-16 h-16 rounded-full border-4 border-primary-500 border-t-transparent pi-spin"></span>
                <i class="pi pi-file text-primary-500 text-2xl absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></i>
            </div>
            <span class="text-lg font-semibold text-primary-600">Chargement de la fiche médicale...</span>
        </div>
    </div>
</template>
