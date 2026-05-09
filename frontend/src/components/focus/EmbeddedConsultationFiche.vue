<script setup>
import ConsultationEnCoursForm from '@/components/consultations/ConsultationEnCoursForm.vue';
import DevisForm from '@/components/consultations/DevisForm.vue';
import OrdonnanceModal from '@/components/consultations/OrdonnanceModal.vue';
import SaveIndicator from '@/components/consultations/SaveIndicator.vue';
import SectionSwitcher from '@/components/consultations/SectionSwitcher.vue';
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import AutoComplete from 'primevue/autocomplete';
import PrintFicheV2Body from '@/components/print/PrintFicheV2Body.vue';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintOrdonnanceBody from '@/components/print/PrintOrdonnanceBody.vue';
import EntretienVerbalForm from '@/components/fiche-medicale/EntretienVerbalForm.vue';
import ExamensFicheForm from '@/components/fiche-medicale/ExamensFicheForm.vue';
import FicheBilansForm from '@/components/fiche-medicale/FicheBilansForm.vue';
import FicheDocumentsForm from '@/components/fiche-medicale/FicheDocumentsForm.vue';
import FichePlanTraitementForm from '@/components/fiche-medicale/FichePlanTraitementForm.vue';
import SeancesSection from '@/components/fiche-medicale/SeancesSection.vue';
import { useConsultationsForm } from '@/composables/useConsultationsForm';
import { usePrinter } from '@/composables/usePrinter';
import { defaultSoinList, normalizeSoinList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent, updatePatient } from '@/services/patients';
import { fetchFacturePrintData, fetchOrdonnancePrintData, fetchPatientFichePrintData } from '@/services/printService';
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
    mode: {
        type: String,
        default: 'continue'
    },
    readonly: {
        type: Boolean,
        default: false
    },
    choiceLabel: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['patient-loaded', 'closed']);
const toast = useToast();
const confirm = useConfirm();
const auth = useAuthStore();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const ficheIdRef = ref(null);
const consultIdRef = ref(null);
const modeRef = ref('continue');
const pageLoading = ref(false);
const loadErrorMessage = ref('');
const savingAntecedent = ref(false);
const savingAllergy = ref(false);
const ordonnanceModalVisible = ref(false);
const ordonnanceDraft = ref({ date: '', medecinNom: '', note: '', lignes: [] });
const showAntecedentDialog = ref(false);
const showAllergyDialog = ref(false);
const showRdvDialog = ref(false);
const isIndicatorFloating = ref(false);
const isMedecinOptionalOnCreation = ref(false);
const ficheFormSimplifie = ref(false);
const isClotureProcessing = ref(false);
const soinsList = ref([...defaultSoinList]);
const examensTypeOptions = ref(['Bacteriologique', 'Serologique', 'Histologique', 'Radiologique', 'Autre']);
const traitementTypeOptions = ref(['Urgence', 'Dentaires', 'Parodontaux', 'Orthodontiques', 'Autres']);
const allergyTypeOptions = ref(['Médicamenteuses', 'Alimentaires', 'Environnementales', 'Autres']);
const antecedentTypeOptions = ref(['Personnel', 'Familial', 'Médical']);
const examensTypeSuggestions = ref([]);
const traitementTypeSuggestions = ref([]);
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
    saveBilansSection: saveBilans,
    savePlanTraitementSection: savePlanTraitement,
    saveDevisSection: saveDevis,
    saveConsultSection: saveConsult,
    closeConsult
} = useConsultationsForm({ ficheId: ficheIdRef, consultId: consultIdRef, token, mode: computed(() => modeRef.value) });

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

const isClosedConsultationError = (error) => Number(error?.response?.status) === 409;

const getSectionStatus = (key) => {
    if (isReadonly.value) return { status: 'readonly', label: 'Lecture seule', saveDisabled: true };
    if (!key) return { status: 'readonly', label: 'Lecture seule', saveDisabled: true };
    if (saving[key]) return { status: 'saving', label: 'Sauvegarde...', saveDisabled: true };
    if (dirty[key]) return { status: 'dirty', label: 'Modifie', saveDisabled: false };
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
            return hasValue([data.consultation?.type, data.consultation?.medecinId, data.consultation?.infirmierIds, data.consultation?.salleId, data.consultation?.noteSeance, data.consultation?.actes]);
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
    const consultStatus = getSectionStatus('consult');

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
            saving: saving.consult,
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

const loadConsultationPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        isMedecinOptionalOnCreation.value = settings?.requireMedecinOnConsultationCreation === false;
        ficheFormSimplifie.value = settings?.ficheFormSimplifie === true;
        soinsList.value = normalizeSoinList(settings?.soinsList);
        examensTypeOptions.value = Array.isArray(settings?.examensTypes) && settings.examensTypes.length ? settings.examensTypes : examensTypeOptions.value;
        traitementTypeOptions.value = Array.isArray(settings?.traitementTypes) && settings.traitementTypes.length ? settings.traitementTypes : traitementTypeOptions.value;
        allergyTypeOptions.value = Array.isArray(settings?.allergyTypes) && settings.allergyTypes.length ? settings.allergyTypes : allergyTypeOptions.value;
        antecedentTypeOptions.value = Array.isArray(settings?.antecedentTypes) && settings.antecedentTypes.length ? settings.antecedentTypes : antecedentTypeOptions.value;
    } catch (_) {
        isMedecinOptionalOnCreation.value = false;
        ficheFormSimplifie.value = false;
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
            emit('closed');
            return;
        }
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
            emit('closed');
            return;
        }
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
            emit('closed');
            return;
        }
        if (!silent) toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sauvegarde devis impossible.' });
    }
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
            emit('closed');
            return;
        }
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
        const response = await addPatientAntecedent(data.patient.id, payload, token);
        if (response?.antecedent) data.patient.antecedents.push(response.antecedent);
        toast.add({ severity: 'success', summary: 'Antecedent ajoute', life: 2000 });
        showAntecedentDialog.value = false;
        emit('patient-loaded', { ...data.patient });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible d\'ajouter l\'antecedent.' });
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
    const factureId = Number(devisEntry?.id ?? Number.NaN);
    if (!Number.isFinite(factureId)) {
        toast.add({ severity: 'warn', summary: 'Impression', detail: 'Ce devis doit etre sauvegarde avant impression.' });
        return;
    }

    try {
        const response = await fetchFacturePrintData(factureId, token);
        await printComponent(PrintDevisBody, { doc: response.doc, title: response.title || 'Devis' });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer le devis." });
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

const initialize = async () => {
    loadErrorMessage.value = '';
    if (!props.consultationId) {
        loadErrorMessage.value = 'Aucune consultation sélectionnée.';
        return;
    }

    pageLoading.value = true;
    consultIdRef.value = Number(props.consultationId) || null;
    ficheIdRef.value = Number(props.ficheId) || null;
    modeRef.value = props.mode === 'new-fiche' ? 'new-fiche' : 'continue';
    activeSection.value = 'consult';

    try {
        await Promise.all([loadData(), loadConsultationPolicy()]);
        emit('patient-loaded', { ...data.patient });
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
    () => [props.consultationId, props.ficheId, props.mode],
    () => {
        initialize();
    },
    { immediate: true }
);

watch(
    () => [isMedecinUser.value, isMedecinOptionalOnCreation.value, data.consultation?.medecinId, medecinsOptions.value.length],
    () => {
        if (!isMedecinOptionalOnCreation.value) return;
        if (!isMedecinUser.value) return;
        if (data.consultation?.medecinId) return;
        const user = auth.user || {};
        const directId = Number(user.medecinId ?? user.medecin_id ?? user.medecin?.id ?? Number.NaN);
        if (Number.isFinite(directId) && directId > 0) {
            data.consultation = { ...data.consultation, medecinId: directId };
        }
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
                    Cette consultation est terminee. La fiche reste visible ici en lecture seule.
                </div>
                <div :class="isReadonly ? 'opacity-80' : ''">
                    <SectionSwitcher v-model="activeSection" :sections="sections" :mode="switcherMode" :init-key="sectionInitKey">
                    <template #consult>
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
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
                                :medecin-readonly="isMedecinUser || isReadonly"
                                :loading="loading || isClotureProcessing"
                                :saving="saving.consult"
                                :cloture-loading="isClotureProcessing"
                                @save="saveConsultSection"
                                @cloture="handleCloture"
                                @open-ordonnance="openOrdonnanceModal"
                                @print-ordonnance="handlePrintOrdonnance"
                            />
                        </div>
                    </template>

                    <template #synthese>
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''" class="rounded-2xl border border-surface-200/60 dark:border-surface-700/60 bg-surface-0 dark:bg-surface-900/40 p-4 md:p-5">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Synthèse clinique</h3>
                                    <p class="text-xs text-surface-500 dark:text-surface-400">Vue condensée du questionnaire, examens, bilan, plan de traitement et documents.</p>
                                </div>
                                <Button icon="pi pi-save" label="Enregistrer" size="small" :loading="isClotureProcessing || saving.entretien || saving.examens || saving.documents || saving.bilans || saving.planTraitement" :disabled="isClotureProcessing" @click="saveSyntheseSection" />
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
                                        <div class="space-y-2 max-h-72 overflow-auto pr-1">
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
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
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
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
                            <ExamensFicheForm v-model="data.examens" :saving="saving.examens" @save="saveExamensSection" />
                        </div>
                    </template>

                    <template #documents>
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
                            <FicheDocumentsForm v-model="data.documents" :saving="saving.documents" @save="saveDocumentsSection" />
                        </div>
                    </template>

                    <template #bilans>
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
                            <FicheBilansForm v-model="data.bilans" :saving="saving.bilans" :patient-age="ageNumber" @save="saveBilansSection" />
                        </div>
                    </template>

                    <template #plan-traitement>
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
                            <FichePlanTraitementForm v-model="data.planTraitement" :saving="saving.planTraitement" @save="savePlanTraitementSection" />
                        </div>
                    </template>

                    <template #devis>
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
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
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
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
