<script setup>
import ConsultationEnCoursForm from '@/components/consultations/ConsultationEnCoursForm.vue';
import DevisForm from '@/components/consultations/DevisForm.vue';
import OrdonnanceModal from '@/components/consultations/OrdonnanceModal.vue';
import SaveIndicator from '@/components/consultations/SaveIndicator.vue';
import SectionSwitcher from '@/components/consultations/SectionSwitcher.vue';
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
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
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent } from '@/services/patients';
import { fetchOrdonnancePrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import ConfirmDialog from 'primevue/confirmdialog';
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
const savingAntecedent = ref(false);
const savingAllergy = ref(false);
const ordonnanceModalVisible = ref(false);
const ordonnanceDraft = ref({ date: '', medecinNom: '', note: '', lignes: [] });
const showAntecedentDialog = ref(false);
const showAllergyDialog = ref(false);
const isIndicatorFloating = ref(false);
const isMedecinOptionalOnCreation = ref(false);
const soinsList = ref([...defaultSoinList]);

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
        || employee.name
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

    return [
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
            id: 'entretien',
            label: 'Entretien verbale',
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
});

const loadConsultationPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        isMedecinOptionalOnCreation.value = settings?.requireMedecinOnConsultationCreation === false;
        soinsList.value = normalizeSoinList(settings?.soinsList);
    } catch (_) {
        isMedecinOptionalOnCreation.value = false;
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
        if (!silent) toast.add({ severity: 'success', summary: 'Entretien enregistre', life: 2000 });
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
                toast.add({ severity: 'success', summary: 'Consultation cloturee', life: 2200 });
                emit('closed');
            } catch (error) {
                if (isClosedConsultationError(error)) {
                    emit('closed');
                    return;
                }
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Cloture impossible.', life: 2500 });
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
    if (!props.consultationId) {
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
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la fiche de consultation.', life: 3000 });
    } finally {
        pageLoading.value = false;
    }
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
</script>

<template>
    <div class="min-h-[32rem]">
        <ConfirmDialog />
        <AppToast />

        <div v-if="!pageLoading" class="space-y-5">
            <div class="rounded-2xl border border-surface-200/60 dark:border-surface-700/60 bg-surface-0 dark:bg-surface-800/70 p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-500/10 text-primary-600 dark:bg-primary-500/20 dark:text-primary-300">
                                <i class="pi pi-file-edit text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Fiche medicale focus</h3>
                                <p class="text-sm text-surface-600 dark:text-surface-300">Saisie embarquee sans quitter le Mode Focus.</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag :value="isReadonly ? 'Consultation terminee' : 'Consultation en cours'" :severity="isReadonly ? 'success' : 'info'" />
                            <Tag v-if="choiceLabel" :value="choiceLabel" severity="contrast" />
                            <Tag v-if="isReadonly" value="Lecture seule" severity="warn" />
                        </div>
                    </div>

                    <div v-if="!isReadonly" class="min-w-[18rem]">
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
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200/60 dark:border-surface-700/60 bg-surface-0 dark:bg-surface-800/70 shadow-sm">
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

                    <template #entretien>
                        <div :class="isReadonly ? 'pointer-events-none select-none' : ''">
                            <EntretienVerbalForm v-model="data.entretien" :saving="saving.entretien" @save="saveEntretienSection" />
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
                            <DevisForm v-model="data.devis" :saving="saving.devis" :soins="soinsList" @save="saveDevisSection" />
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

            <AntecedentDialogForm v-model="showAntecedentDialog" :loading="savingAntecedent" @save="handleSaveAntecedent" />
            <AllergyDialogForm v-model="showAllergyDialog" :loading="savingAllergy" @save="handleSaveAllergy" />
            <OrdonnanceModal
                v-model="ordonnanceDraft"
                v-model:visible="ordonnanceModalVisible"
                :medecin-readonly="true"
                :saving="saving.consult"
                @save="saveOrdonnanceSection"
            />
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