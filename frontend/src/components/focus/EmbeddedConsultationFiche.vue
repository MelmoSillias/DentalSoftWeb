<script setup>
import ConsultationEnCoursForm from '@/components/consultations/ConsultationEnCoursForm.vue';
import DevisForm from '@/components/consultations/DevisForm.vue';
import OrdonnanceModal from '@/components/consultations/OrdonnanceModal.vue';
import SaveIndicator from '@/components/consultations/SaveIndicator.vue';
import SectionSwitcher from '@/components/consultations/SectionSwitcher.vue';
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import PrintFicheV2Body from '@/components/print/PrintFicheV2Body.vue';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintOrdonnanceBody from '@/components/print/PrintOrdonnanceBody.vue';
import EntretienVerbalForm from '@/components/fiche-medicale/EntretienVerbalForm.vue';
import ExamensFicheForm from '@/components/fiche-medicale/ExamensFicheForm.vue';
import FicheBilansForm from '@/components/fiche-medicale/FicheBilansForm.vue';
import FicheDocumentsForm from '@/components/fiche-medicale/FicheDocumentsForm.vue';
import FichePlanTraitementForm from '@/components/fiche-medicale/FichePlanTraitementForm.vue';
import FicheSyntheseForm from '@/components/fiche-medicale/FicheSyntheseForm.vue';
import SeancesSection from '@/components/fiche-medicale/SeancesSection.vue';
import { useConsultationsForm } from '@/composables/useConsultationsForm';
import { usePrinter } from '@/composables/usePrinter';
import { defaultSoinList, normalizeSoinList } from '@/services/consultations';
import { fetchOrdonnanceById, loadOrdonnances, updateOrdonnance } from '@/services/consultationsforms';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent, updatePatient } from '@/services/patients';
import { fetchDevisPrintData, fetchOrdonnancePrintData, fetchPatientFichePrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    consultationId: {
        type: Number,
        default: null
    },
    ficheId: {
        type: Number,
        default: null
    },
    readonly: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['patient-loaded', 'closed', 'ordonnances-changed']);
const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const ficheIdRef = ref(null);
const consultIdRef = ref(null);
const mode = computed(() => 'continue');
const pageLoading = ref(false);
const loadErrorMessage = ref('');
const savingAntecedent = ref(false);
const savingAllergy = ref(false);
const ordonnanceModalVisible = ref(false);
const ordonnanceModalMode = ref('create');
const ordonnanceDraft = ref({ date: '', medecinNom: '', note: '', lignes: [] });
const showAntecedentDialog = ref(false);
const showAllergyDialog = ref(false);
const showRdvDialog = ref(false);
const isIndicatorFloating = ref(false);
const isMedecinOptionalOnCreation = ref(false);
const ficheFormSimplifie = ref(false);
const showDiagnosticPositifInConsultation = ref(true);
const isClotureProcessing = ref(false);
const soinsList = ref([...defaultSoinList]);
const examensTypeOptions = ref(['Bacteriologique', 'Serologique', 'Histologique', 'Radiologique', 'Autre']);
const traitementTypeOptions = ref(['Urgence', 'Dentaires', 'Parodontaux', 'Orthodontiques', 'Autres']);
const allergyTypeOptions = ref(['Médicamenteuses', 'Alimentaires', 'Environnementales', 'Autres']);
const antecedentTypeOptions = ref(['Personnel', 'Familial', 'Médical']);
const isSimplifiedFicheFormEnabled = computed(() => ficheFormSimplifie.value === true);

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
    documentsUploadProgress,
    saveBilansSection: saveBilans,
    savePlanTraitementSection: savePlanTraitement,
    saveDevisSection: saveDevis,
    saveConsultSection: saveConsult,
    closeConsult
} = useConsultationsForm({ ficheId: ficheIdRef, consultId: consultIdRef, token, mode });

switcherMode.value = 'tabs';
activeSection.value = 'consult';

const toFullName = (employee = {}) => {
    return employee.label
        || employee.fullName
        || employee.fullname
        || employee.FullName
        || employee.Fullname
        || `${employee.prenom ?? ''} ${employee.nom ?? ''}`.trim()
        || employee.nom
        || '';
};

const medecinsOptions = computed(() => (data.medecins || []).map((item) => ({
    id: item.id,
    label: toFullName(item)
})));

const infirmiersOptions = computed(() => (data.infirmiers || []).map((item) => ({
    id: item.id,
    label: toFullName(item)
})));

const sallesOptions = computed(() => (data.salles || []).map((item) => ({
    id: item.id,
    label: item.label || item.nom || item.name || ''
})));

const normalizeText = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

const resolveConnectedMedecinId = () => {
    const user = auth.user || {};
    const directId = Number(user.medecinId ?? user.medecin_id ?? user.medecin?.id ?? Number.NaN);
    if (Number.isFinite(directId)) {
        const found = medecinsOptions.value.find((medecin) => Number(medecin.id) === directId);
        if (found) return found.id;
    }

    const fullName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
    const candidates = [fullName, user.name, user.fullName, user.username].filter(Boolean).map(normalizeText);
    if (!candidates.length) return null;

    const foundByName = medecinsOptions.value.find((medecin) => {
        const label = normalizeText(medecin.label);
        return candidates.some((candidate) => candidate && (label === candidate || label.includes(candidate) || candidate.includes(label)));
    });
    return foundByName?.id ?? null;
};

const selectedMedecinLabel = computed(() => {
    const selectedId = data.consultation?.medecinId;
    const item = medecinsOptions.value.find((medecin) => medecin.id === selectedId);
    if (item?.label) {
        return item.label;
    }

    const user = auth.user || {};
    const fullName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
    return fullName || user.name || user.username || '';
});

const rdvInitialMedecinId = computed(() => {
    const consultationMedecinId = Number(data.consultation?.medecinId ?? Number.NaN);
    if (Number.isFinite(consultationMedecinId) && consultationMedecinId > 0) {
        return consultationMedecinId;
    }
    if (isMedecinUser.value) {
        return resolveConnectedMedecinId();
    }
    return null;
});

const rdvLockedMedecinId = computed(() => (isMedecinUser.value ? resolveConnectedMedecinId() : null));

const computeAgeYears = (value) => {
    if (!value) return 0;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return Number(value) || 0;
    const diff = Date.now() - date.getTime();
    return Math.max(0, Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25)));
};

const ageNumber = computed(() => computeAgeYears(data.patient.dateNaissance || data.patient.age));
const isMedecinUser = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const isReadonly = computed(() => props.readonly);

const isSectionInteractionLocked = (sectionId) => {
    if (!isReadonly.value) return false;
    return !['devis', 'seances', 'consult'].includes(sectionId);
};

const notifyOrdonnancesChanged = () => {
    emit('ordonnances-changed', Array.isArray(data.ordonnances) ? [...data.ordonnances] : []);
};

const isClosedConsultationError = (error) => Number(error?.response?.status) === 409;

const getSectionStatus = (key) => {
    if (isReadonly.value) return { status: 'readonly', label: 'Lecture seule', saveDisabled: true };
    if (!key) return { status: 'readonly', label: 'Lecture seule', saveDisabled: true };
    if (saving[key]) return { status: 'saving', label: 'Sauvegarde...', saveDisabled: true };
    if (dirty[key]) return { status: 'dirty', label: 'Modifie', saveDisabled: false };
    return { status: 'saved', label: 'Sauvegarde', saveDisabled: true };
};

const getConsultSectionStatus = () => {
    if (isReadonly.value) return { status: 'readonly', label: 'Lecture seule', saveDisabled: true };

    const consultDirty = Boolean(dirty.consult || dirty.ordonnances);
    const bilanShortcutDirty = showDiagnosticPositifInConsultation.value && dirty.bilans;
    const consultSaving = Boolean(saving.consult || saving.ordonnances);
    const bilanShortcutSaving = showDiagnosticPositifInConsultation.value && saving.bilans;

    if (consultSaving || bilanShortcutSaving) {
        return { status: 'saving', label: 'Sauvegarde...', saveDisabled: true };
    }
    if (consultDirty || bilanShortcutDirty) {
        return { status: 'dirty', label: 'Modifie', saveDisabled: false };
    }
    return { status: 'saved', label: 'Sauvegarde', saveDisabled: true };
};

const hasValue = (value) => {
    if (Array.isArray(value)) return value.length > 0;
    if (value && typeof value === 'object') return Object.values(value).some(hasValue);
    if (typeof value === 'number') return value > 0;
    return typeof value === 'string' ? value.trim().length > 0 : Boolean(value);
};

const isSectionFilled = (id) => {
    switch (id) {
        case 'synthese':
            return hasValue([data.entretien, data.examens?.examensLabo, data.bilans, data.planTraitement, data.documents]);
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
        case 'seances':
            return hasValue(data.sessions);
        case 'consult':
            return hasValue([
                data.consultation?.type,
                data.consultation?.medecinId,
                data.consultation?.infirmierIds,
                data.consultation?.salleId,
                data.consultation?.noteSeance,
                data.consultation?.actes,
                showDiagnosticPositifInConsultation.value ? data.bilans?.diagnosticPositif : null
            ]);
        default:
            return false;
    }
};

const sections = computed(() => {
    const entretienStatus = getSectionStatus('entretien');
    const examensStatus = getSectionStatus('examens');
    const documentsStatus = getSectionStatus('documents');
    const bilansStatus = getSectionStatus('bilans');
    const planTraitementStatus = getSectionStatus('planTraitement');
    const devisStatus = getSectionStatus('devis');
    const consultStatus = getConsultSectionStatus();

    const syntheseSaving = Boolean(saving.entretien || saving.examens || saving.documents || saving.bilans || saving.planTraitement);
    const syntheseDirty = Boolean(dirty.entretien || dirty.examens || dirty.documents || dirty.bilans || dirty.planTraitement);
    const syntheseStatus = syntheseSaving ? 'saving' : syntheseDirty ? 'dirty' : 'saved';
    const syntheseStatusLabel = syntheseSaving ? 'Sauvegarde...' : syntheseDirty ? 'Modifie' : 'Sauvegarde';

    const baseSections = [
        {
            id: 'consult',
            label: 'Consultation en cours',
            filled: isSectionFilled('consult'),
            status: consultStatus.status,
            statusLabel: consultStatus.label,
            saveDisabled: consultStatus.saveDisabled,
            saving: Boolean(saving.consult || (showDiagnosticPositifInConsultation.value && saving.bilans)),
            onSave: () => saveConsultSection()
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
        },
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

const handleRdvSaved = () => {
    showRdvDialog.value = false;
    toast.add({ severity: 'success', summary: 'Rendez-vous créé', life: 2200 });
};

const loadConsultationPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        isMedecinOptionalOnCreation.value = settings?.requireMedecinOnConsultationCreation === false;
        ficheFormSimplifie.value = settings?.ficheFormSimplifie === true;
        showDiagnosticPositifInConsultation.value = settings?.showDiagnosticPositifInConsultation !== false;
        soinsList.value = normalizeSoinList(settings?.soinsList);
        examensTypeOptions.value = Array.isArray(settings?.examensTypes) && settings.examensTypes.length ? settings.examensTypes : examensTypeOptions.value;
        traitementTypeOptions.value = Array.isArray(settings?.traitementTypes) && settings.traitementTypes.length ? settings.traitementTypes : traitementTypeOptions.value;
        allergyTypeOptions.value = Array.isArray(settings?.allergyTypes) && settings.allergyTypes.length ? settings.allergyTypes : allergyTypeOptions.value;
        antecedentTypeOptions.value = Array.isArray(settings?.antecedentTypes) && settings.antecedentTypes.length ? settings.antecedentTypes : antecedentTypeOptions.value;
    } catch (_) {
        isMedecinOptionalOnCreation.value = false;
        ficheFormSimplifie.value = false;
        showDiagnosticPositifInConsultation.value = true;
        soinsList.value = [...defaultSoinList];
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

const saveEntretienSection = async ({ silent = false } = {}) => {
    if (!dirty.entretien) return;
    try {
        await saveEntretien();
        if (!silent) toast.add({ severity: 'success', summary: 'Interrogatoire enregistre', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            emit('closed');
            return;
        }
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde entretien impossible.', life: 3000 });
    }
};

const saveExamensSection = async ({ silent = false } = {}) => {
    if (!dirty.examens) return;
    try {
        await saveExamens();
        if (!silent) toast.add({ severity: 'success', summary: 'Examens enregistres', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            emit('closed');
            return;
        }
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde examens impossible.', life: 3000 });
    }
};

const saveDocumentsSection = async ({ silent = false } = {}) => {
    if (!dirty.documents) return;
    try {
        await saveDocuments();
        if (!silent) toast.add({ severity: 'success', summary: 'Documents enregistres', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            emit('closed');
            return;
        }
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde documents impossible.', life: 3000 });
    }
};

const saveBilansSection = async ({ silent = false } = {}) => {
    if (!dirty.bilans) return;
    try {
        await saveBilans();
        if (!silent) toast.add({ severity: 'success', summary: 'Bilans enregistres', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            emit('closed');
            return;
        }
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
            emit('closed');
            return;
        }
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde plan traitement impossible.', life: 3000 });
    }
};

const saveDevisSection = async ({ silent = false } = {}) => {
    if (!dirty.devis) return;
    try {
        await saveDevis();
        if (!silent) toast.add({ severity: 'success', summary: 'Devis enregistre', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            emit('closed');
            return;
        }
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde devis impossible.', life: 3000 });
    }
};

const saveConsultSection = async ({ silent = false } = {}) => {
    const shouldSaveConsult = dirty.consult || dirty.ordonnances;
    const shouldSaveDiagnostic = showDiagnosticPositifInConsultation.value && dirty.bilans;
    if (!shouldSaveConsult && !shouldSaveDiagnostic) return;
    if (shouldSaveConsult && !ensureMedecinSelected({ silent })) return;

    try {
        if (shouldSaveConsult) {
            await saveConsult({ ordonnancePayload: dirty.ordonnances ? ordonnanceDraft.value : null });
            if (dirty.ordonnances) {
                ordonnanceModalVisible.value = false;
            }
        }
        if (shouldSaveDiagnostic) {
            await saveBilansSection({ silent: true });
        }
        if (!silent) toast.add({ severity: 'success', summary: 'Consultation enregistree', life: 2000 });
    } catch (error) {
        if (isClosedConsultationError(error)) {
            emit('closed');
            return;
        }
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde consultation impossible.', life: 3000 });
    }
};

const saveOrdonnanceSection = async ({ silent = false } = {}) => {
    if (ordonnanceModalMode.value === 'view') {
        ordonnanceModalVisible.value = false;
        return;
    }

    if (ordonnanceModalMode.value === 'edit' && ordonnanceDraft.value?.id) {
        try {
            await updateOrdonnance(ordonnanceDraft.value.id, ordonnanceDraft.value, token);
            if (consultIdRef.value) {
                data.ordonnances = await loadOrdonnances(consultIdRef.value, token);
            }
            ordonnanceModalVisible.value = false;
            notifyOrdonnancesChanged();
            if (!silent) toast.add({ severity: 'success', summary: 'Ordonnance mise à jour', life: 2000 });
        } catch (_) {
            if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible de modifier l'ordonnance.", life: 3000 });
        }
        return;
    }

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
        const response = await addPatientAntecedent(data.patient.id, payload, token);
        if (response?.antecedent) data.patient.antecedents.push(response.antecedent);
        toast.add({ severity: 'success', summary: 'Antecedent ajoute', life: 2000 });
        showAntecedentDialog.value = false;
        emit('patient-loaded', { ...data.patient });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'ajouter l\'antecedent.', life: 3000 });
    } finally {
        savingAntecedent.value = false;
    }
};

const handleSaveAllergy = async (payload) => {
    if (!data.patient?.id) return;
    savingAllergy.value = true;
    try {
        const response = await addPatientAllergy(data.patient.id, payload, token);
        if (response?.allergy) data.patient.allergies.push(response.allergy);
        toast.add({ severity: 'success', summary: 'Allergie ajoutee', life: 2000 });
        showAllergyDialog.value = false;
        emit('patient-loaded', { ...data.patient });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'allergie." });
    } finally {
        savingAllergy.value = false;
    }
};

const handleDeleteAntecedent = async (item) => {
    if (!data.patient?.id || !item?.id) return;
    try {
        await deletePatientAntecedent(data.patient.id, item.id, token);
        data.patient.antecedents = data.patient.antecedents.filter((entry) => entry.id !== item.id);
        emit('patient-loaded', { ...data.patient });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.' });
    }
};

const handleDeleteAllergy = async (item) => {
    if (!data.patient?.id || !item?.id) return;
    try {
        await deletePatientAllergy(data.patient.id, item.id, token);
        data.patient.allergies = data.patient.allergies.filter((entry) => entry.id !== item.id);
        emit('patient-loaded', { ...data.patient });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.' });
    }
};

const handlePhotoSelected = async (file) => {
    if (!data.patient?.id || !file) return;

    const formData = new FormData();
    formData.append('photo', file);

    try {
        const updated = await updatePatient(data.patient.id, formData, token);
        if (!updated?.id) {
            throw new Error('patient_photo_update_failed');
        }

        data.patient = {
            ...data.patient,
            photo: updated.photo ?? data.patient.photo
        };

        emit('patient-loaded', { ...data.patient });
        toast.add({ severity: 'success', summary: 'Photo patient', detail: 'Photo mise à jour.', life: 2500 });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour la photo du patient.', life: 3000 });
    }
};

const handleCloture = () => {
    if (isClotureProcessing.value) return;
    if (!ensureMedecinSelected()) return;

    if (savingCount.value > 0) {
        toast.add({ severity: 'warn', summary: 'Sauvegarde en cours', detail: 'Veuillez patienter avant de clôturer.', life: 3000 });
        return;
    }

    confirm.require({
        message: 'Cloturer definitivement cette consultation ?',
        header: 'Confirmation',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Cloturer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            isClotureProcessing.value = true;
            try {
                await saveAll({ silent: true });

                if (dirtySectionsList.value.length > 0) {
                    toast.add({
                        severity: 'warn',
                        summary: 'Sauvegarde incomplète',
                        detail: 'Toutes les sections doivent être sauvegardées avant la clôture.',
                        life: 3500
                    });
                    return;
                }

                await closeConsult({
                    forcePersistConsult: true,
                    ordonnancePayload: dirty.ordonnances ? ordonnanceDraft.value : null
                });
                Object.keys(dirty).forEach((key) => {
                    dirty[key] = false;
                });
                toast.add({ severity: 'success', summary: 'Consultation cloturee', life: 2200 });
                emit('closed');
            } catch (error) {
                if (isClosedConsultationError(error)) {
                    emit('closed');
                    return;
                }
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Cloture impossible.', life: 2500 });
            } finally {
                isClotureProcessing.value = false;
            }
        }
    });
};

const handlePrintOrdonnance = async (ordonnance) => {
    if (!ordonnance?.id) return;
    try {
        const response = await fetchOrdonnancePrintData(ordonnance.id, token);
        await printComponent(PrintOrdonnanceBody, { data: response.data });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer l'ordonnance." });
    }
};

const handlePrintFiche = async () => {
    const patientId = Number(data.patient?.id ?? Number.NaN);
    if (!Number.isFinite(patientId) || !ficheIdRef.value) {
        toast.add({ severity: 'warn', summary: 'Impression', detail: 'Fiche non disponible pour impression.' });
        return;
    }

    try {
        const res = await fetchPatientFichePrintData(patientId, ficheIdRef.value, token);
        const sectionsToPrint = isSimplifiedFicheFormEnabled.value
            ? ['synthese']
            : ['entretien', 'examens', 'images', 'plan', 'bilan', 'seances'];
        await printComponent(PrintFicheV2Body, {
            patient: res.patient,
            fiche: res.fiche,
            sections: sectionsToPrint,
            printEmpty: false
        });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer la fiche." });
    }
};

const handlePrintDevis = async (devisEntry) => {
    const devisId = Number(devisEntry?.id ?? Number.NaN);
    if (!Number.isFinite(devisId)) {
        toast.add({ severity: 'warn', summary: 'Impression', detail: 'Ce devis doit etre sauvegarde avant impression.' });
        return;
    }

    try {
        const response = await fetchDevisPrintData(devisId, token);
        await printComponent(PrintDevisBody, { doc: response.doc, title: response.title || 'Devis' });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer le devis." });
    }
};

const normalizeOrdonnanceDraft = (ordo = {}) => ({
    id: ordo.id ?? null,
    date: ordo.date || '',
    medecinNom: ordo.medecinNom || ordo.medecin || '',
    note: ordo.note || '',
    lignes: Array.isArray(ordo.lignes)
        ? ordo.lignes.map((line) => ({
            designation: line.designation || line.medicament || '',
            posologie: line.posologie || '',
            frequence: line.frequence || '',
            duree: line.duree || '',
            quantite: Number(line.quantite) || 1,
            instructions: line.instructions || ''
        }))
        : []
});

const loadOrdonnanceDraft = async (ordo, mode) => {
    ordonnanceModalMode.value = mode;
    if (ordo?.id) {
        try {
            const full = await fetchOrdonnanceById(ordo.id, token);
            ordonnanceDraft.value = normalizeOrdonnanceDraft(full);
        } catch (_) {
            ordonnanceDraft.value = normalizeOrdonnanceDraft(ordo);
        }
    } else {
        ordonnanceDraft.value = normalizeOrdonnanceDraft(ordo);
    }
    ordonnanceModalVisible.value = true;
};

const openOrdonnanceModal = () => {
    ordonnanceModalMode.value = 'create';
    ordonnanceDraft.value = {
        date: new Date().toISOString().slice(0, 10),
        medecinNom: selectedMedecinLabel.value || '',
        note: '',
        lignes: []
    };
    ordonnanceModalVisible.value = true;
};

const openViewOrdonnance = async (ordo) => {
    await loadOrdonnanceDraft(ordo, 'view');
};

const openEditOrdonnance = async (ordo) => {
    await loadOrdonnanceDraft(ordo, 'edit');
};

const initialize = async () => {
    loadErrorMessage.value = '';
    if (!props.consultationId) {
        loadErrorMessage.value = 'Aucune consultation sélectionnée.';
        return;
    }

    pageLoading.value = true;
    consultIdRef.value = Number(props.consultationId) || null;
    ficheIdRef.value = Number(props.ficheId) || null;
    activeSection.value = 'consult';

    try {
        await Promise.all([loadData(), loadConsultationPolicy()]);
        emit('patient-loaded', { ...data.patient });
        notifyOrdonnancesChanged();
    } catch (error) {
        if (isClosedConsultationError(error)) {
            emit('closed');
            return;
        }
        loadErrorMessage.value = 'Impossible de charger la fiche de consultation.';
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la fiche de consultation.', life: 3000 });
    } finally {
        pageLoading.value = false;
    }
};

const retryInitialize = async () => {
    await initialize();
};

const openAntecedentDialog = () => {
    if (isReadonly.value) return;
    showAntecedentDialog.value = true;
};

const openAllergyDialog = () => {
    if (isReadonly.value) return;
    showAllergyDialog.value = true;
};

const deleteAntecedent = async (item) => {
    if (isReadonly.value) return;
    await handleDeleteAntecedent(item);
};

const deleteAllergy = async (item) => {
    if (isReadonly.value) return;
    await handleDeleteAllergy(item);
};

const updatePatientPhoto = async (file) => {
    if (isReadonly.value) return;
    await handlePhotoSelected(file);
};

watch(
    () => data.ordonnances,
    () => {
        notifyOrdonnancesChanged();
    },
    { deep: true }
);

watch(
    () => props.consultationId,
    (consultationId, previousId) => {
        if (!consultationId) return;
        if (consultationId === previousId && previousId !== undefined) return;
        initialize();
    },
    { immediate: true }
);

watch(
    () => props.ficheId,
    () => {
        ficheIdRef.value = Number(props.ficheId) || null;
    }
);

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

defineExpose({
    openAntecedentDialog,
    openAllergyDialog,
    deleteAntecedent,
    deleteAllergy,
    updatePatientPhoto,
    openOrdonnanceModal,
    openViewOrdonnance,
    openEditOrdonnance,
    handlePrintOrdonnance,
    retryLoad: retryInitialize
});
</script>

<template>
    <div class="min-h-[32rem]">
        <ConfirmDialog />
        <AppToast />

        <div v-if="!pageLoading && !loadErrorMessage" class="relative space-y-5">
            <div v-if="isClotureProcessing" class="absolute inset-0 z-30 flex items-center justify-center bg-surface-0/60 dark:bg-surface-900/60 backdrop-blur-[1px]">
                <div class="flex items-center gap-2 rounded-xl border border-surface-300 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 px-4 py-2 text-sm font-medium text-surface-700 dark:text-surface-100 shadow">
                    <i class="pi pi-spin pi-spinner"></i>
                    Clôture en cours...
                </div>
            </div>
            <div class="flex items-center justify-between border border-surface-200/60 dark:border-surface-700/60 bg-surface-0 dark:bg-surface-800/70 px-4 py-3 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:bg-primary-500/20 dark:text-primary-300">
                        <i class="pi pi-file-edit text-sm"></i>
                    </div>

                    <div class="leading-tight">
                        <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">
                            Fiche médicale
                        </h3>
                        <span class="text-xs text-surface-500 dark:text-surface-400">
                            Mode focus
                        </span>
                    </div>

                    <!-- Status -->
                    <Tag
                        :value="isReadonly ? 'Terminée' : 'En cours'"
                        :severity="isReadonly ? 'success' : 'info'"
                        class="ml-2"
                    />
                </div>

                <!-- Right -->
                <div class="flex items-center gap-2">
                    <Button
                        icon="pi pi-print"
                        severity="secondary"
                        text
                        rounded
                        size="small"
                        :disabled="isClotureProcessing"
                        @click="handlePrintFiche"
                    />

                    <div v-if="!isReadonly">
                        <SaveIndicator
                            minimalDesign
                            v-model:auto-save-enabled="autoSaveEnabled"
                            :loading="loading || isClotureProcessing"
                            :saving-count="savingCount"
                            :last-saved-at="lastSavedAt"
                            :dirty-sections="dirtySectionsList"
                            @save-all="() => saveAll({ silent: false })"
                        />
                    </div>
                </div>
            </div>

            <div class="border border-surface-200/60 dark:border-surface-700/60 bg-surface-0 dark:bg-surface-800/70 shadow-sm" :class="isClotureProcessing ? 'pointer-events-none opacity-80' : ''">
                <div v-if="isReadonly" class="border-b border-amber-200/80 bg-amber-50/90 px-5 py-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                    Cette consultation est terminee. La fiche reste visible en lecture seule. Vous pouvez consulter les devis, derouler les seances passees et gerer les ordonnances.
                </div>
                <div :class="isReadonly ? 'opacity-95' : ''">
                    <SectionSwitcher v-model="activeSection" :sections="sections" :mode="switcherMode" :init-key="sectionInitKey">
                    <template #consult>
                        <div :class="isSectionInteractionLocked('consult') ? 'pointer-events-none select-none' : ''">
                            <ConsultationEnCoursForm
                                v-model="data.consultation"
                                v-model:diagnostic-positif="data.bilans.diagnosticPositif"
                                :show-diagnostic-positif="showDiagnosticPositifInConsultation"
                                :soins="soinsList"
                                :formule-dentaire="data.bilans?.bilanDentaire?.formuleDentaire"
                                :medecins="data.medecins"
                                :medecins-options="medecinsOptions"
                                :infirmiers="data.infirmiers"
                                :infirmiers-options="infirmiersOptions"
                                :salles="data.salles"
                                :salles-options="sallesOptions"
                                :ordonnances="data.ordonnances"
                                :medecin-readonly="isMedecinUser || isReadonly"
                                :readonly="isReadonly"
                                :loading="loading || isClotureProcessing"
                                :saving="Boolean(saving.consult || (showDiagnosticPositifInConsultation && saving.bilans))"
                                :cloture-loading="isClotureProcessing"
                                @save="saveConsultSection"
                                @cloture="handleCloture"
                                @open-ordonnance="openOrdonnanceModal"
                                @view-ordonnance="openViewOrdonnance"
                                @edit-ordonnance="openEditOrdonnance"
                                @print-ordonnance="handlePrintOrdonnance"
                            />
                        </div>
                    </template>

                    <template #synthese>
                        <div :class="isSectionInteractionLocked('synthese') ? 'pointer-events-none select-none' : ''">
                            <FicheSyntheseForm
                                v-model:entretien="data.entretien"
                                :patient="data.patient"
                                v-model:documents="data.documents"
                                v-model:examens="data.examens"
                                v-model:bilans="data.bilans"
                                v-model:planTraitement="data.planTraitement"
                                :saving="saving"
                                :documents-upload-progress="documentsUploadProgress"
                                :is-cloture-processing="isClotureProcessing"
                                :examens-type-options="examensTypeOptions"
                                :traitement-type-options="traitementTypeOptions"
                                @save="saveSyntheseSection"
                                @save-documents="saveDocumentsSection"
                                @add-antecedent="showAntecedentDialog = true"
                                @add-allergy="showAllergyDialog = true"
                                @delete-antecedent="handleDeleteAntecedent"
                                @delete-allergy="handleDeleteAllergy"
                                @open-rdv="showRdvDialog = true"
                            />
                        </div>
                    </template>

                    <template #entretien>
                        <div :class="isSectionInteractionLocked('entretien') ? 'pointer-events-none select-none' : ''">
                            <EntretienVerbalForm
                                v-model="data.entretien"
                                :saving="saving.entretien"
                                :patient-sex="data.patient?.sexe"
                                @save="saveEntretienSection"
                                @open-rdv="showRdvDialog = true"
                            />
                        </div>
                    </template>

                    <template #examens>
                        <div :class="isSectionInteractionLocked('examens') ? 'pointer-events-none select-none' : ''">
                            <ExamensFicheForm v-model="data.examens" :saving="saving.examens" @save="saveExamensSection" />
                        </div>
                    </template>

                    <template #documents>
                        <div :class="isSectionInteractionLocked('documents') ? 'pointer-events-none select-none' : ''">
                            <FicheDocumentsForm v-model="data.documents" :saving="saving.documents" :upload-progress="documentsUploadProgress" @save="saveDocumentsSection" />
                        </div>
                    </template>

                    <template #bilans>
                        <div :class="isSectionInteractionLocked('bilans') ? 'pointer-events-none select-none' : ''">
                            <FicheBilansForm v-model="data.bilans" :saving="saving.bilans" :patient-age="ageNumber" @save="saveBilansSection" />
                        </div>
                    </template>

                    <template #plan-traitement>
                        <div :class="isSectionInteractionLocked('plan-traitement') ? 'pointer-events-none select-none' : ''">
                            <FichePlanTraitementForm v-model="data.planTraitement" :saving="saving.planTraitement" @save="savePlanTraitementSection" />
                        </div>
                    </template>

                    <template #devis>
                        <div :class="isSectionInteractionLocked('devis') ? 'pointer-events-none select-none' : ''">
                            <DevisForm
                                v-model="data.devis"
                                :saving="saving.devis"
                                :soins="soinsList"
                                :readonly="isReadonly"
                                @save="saveDevisSection"
                                @print-devis="handlePrintDevis"
                            />
                        </div>
                    </template>

                    <template #seances>
                        <div :class="isSectionInteractionLocked('seances') ? 'pointer-events-none select-none' : ''">
                            <SeancesSection :sessions="data.sessions" />
                        </div>
                    </template>
                    </SectionSwitcher>
                </div>
            </div>

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
                    v-if="showRdvDialog"
                    :patient="data.patient"
                    :patient-id="data.patient?.id"
                    :initial-medecin-id="rdvInitialMedecinId"
                    :locked-medecin-id="rdvLockedMedecinId"
                    :medecin-readonly="isMedecinUser"
                    @saved="handleRdvSaved"
                    @cancel="showRdvDialog = false"
                />
            </Dialog>
            <OrdonnanceModal
                v-model="ordonnanceDraft"
                v-model:visible="ordonnanceModalVisible"
                :mode="ordonnanceModalMode"
                :medecin-readonly="true"
                :saving="saving.consult"
                @save="saveOrdonnanceSection"
            />
        </div>

        <div v-else-if="loadErrorMessage" class="flex min-h-[28rem] flex-col items-center justify-center gap-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 dark:border-amber-800/70 dark:bg-amber-950/20">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</p>
                <p class="text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
            </div>
            <Button icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryInitialize" />
        </div>

        <div v-else class="flex min-h-[28rem] flex-col items-center justify-center gap-4 rounded-2xl border border-surface-200/60 bg-surface-0/80 p-8 dark:border-surface-700/60 dark:bg-surface-800/70">
            <span class="block h-16 w-16 rounded-full border-4 border-primary-500 border-t-transparent pi-spin"></span>
            <div class="text-center">
                <p class="text-lg font-semibold text-primary-600">Chargement de la fiche focus...</p>
                <p class="text-sm text-surface-500 dark:text-surface-400">Préparation des données patient, consultation et formulaires.</p>
            </div>
        </div>
    </div>
</template>
