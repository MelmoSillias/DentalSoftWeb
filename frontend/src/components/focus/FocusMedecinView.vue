<script setup>
import EmbeddedConsultationFiche from '@/components/focus/EmbeddedConsultationFiche.vue';
import OrdonnanceModal from '@/components/consultations/OrdonnanceModal.vue';
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';
import PrintOrdonnanceBody from '@/components/print/PrintOrdonnanceBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import { fetchOrdonnanceById, loadOrdonnances, saveOrdonnance, updateOrdonnance } from '@/services/consultationsforms';
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent } from '@/services/patients';
import { fetchOrdonnancePrintData } from '@/services/printService';

import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import { useToast } from 'primevue/usetoast';

import { computed, nextTick, onBeforeUnmount, onMounted, ref, toRefs, watch } from 'vue';

const props = defineProps({
    consultations: {
        type: Array,
        default: () => []
    },
    selectedConsultationId: {
        type: [Number, String, null],
        default: null
    },
    selectedPatient: {
        type: Object,
        default: null
    },
    hidePatientDossier: {
        type: Boolean,
        default: false
    },
    hidePatientPhone: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['clear-selection', 'select-consultation', 'patient-loaded', 'consultation-closed']);

const { consultations, selectedConsultationId, selectedPatient, hidePatientDossier, hidePatientPhone } = toRefs(props);

const showCompletedMedecin = defineModel('showCompletedMedecin', {
    type: Boolean,
    default: false
});

const newestFirstMedecin = ref(false);
const embeddedFicheRef = ref(null);
const consultationOrdonnances = ref([]);
const queueItemRefs = ref({});
const isMobileLayout = ref(typeof window !== 'undefined' && window.matchMedia('(max-width: 1023.98px)').matches);
const queueDrawerVisible = ref(false);
const dossierDrawerVisible = ref(false);

const toast = useToast();
const { printComponent } = usePrinter();
const token = localStorage.getItem('token');

const showAntecedentDialog = ref(false);
const showAllergyDialog = ref(false);
const savingAntecedent = ref(false);
const savingAllergy = ref(false);
const ordonnanceModalVisible = ref(false);
const ordonnanceModalMode = ref('create');
const ordonnanceDraft = ref({ date: '', medecinNom: '', note: '', lignes: [] });
const savingOrdonnance = ref(false);

const MOBILE_MQ = '(max-width: 1023.98px)';
let mobileMq = null;

const syncMobileLayout = () => {
    isMobileLayout.value = Boolean(mobileMq?.matches);
    if (!isMobileLayout.value) {
        queueDrawerVisible.value = false;
        dossierDrawerVisible.value = false;
    }
};

onMounted(() => {
    if (typeof window === 'undefined') return;
    mobileMq = window.matchMedia(MOBILE_MQ);
    syncMobileLayout();
    mobileMq.addEventListener?.('change', syncMobileLayout) || mobileMq.addListener?.(syncMobileLayout);
});

onBeforeUnmount(() => {
    mobileMq?.removeEventListener?.('change', syncMobileLayout) || mobileMq?.removeListener?.(syncMobileLayout);
});

const setQueueItemRef = (id, element) => {
    if (element) {
        queueItemRefs.value[id] = element;
        return;
    }
    delete queueItemRefs.value[id];
};

const parseDateTime = (value) => {
    if (!value) return null;
    if (value instanceof Date) return value;
    if (/^\d{2}\/\d{2}\/\d{4}\s\d{2}:\d{2}$/.test(String(value))) {
        const [datePart, timePart] = String(value).split(' ');
        const [day, month, year] = datePart.split('/').map(Number);
        const [hours, minutes] = timePart.split(':').map(Number);
        return new Date(year, month - 1, day, hours, minutes, 0, 0);
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatTime = (value) => {
    const parsed = parseDateTime(value);
    return parsed ? parsed.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '--:--';
};

const isSameCalendarDay = (left, right) => {
    const leftDate = parseDateTime(left);
    const rightDate = parseDateTime(right);
    if (!leftDate || !rightDate) return false;
    return leftDate.getFullYear() === rightDate.getFullYear() && leftDate.getMonth() === rightDate.getMonth() && leftDate.getDate() === rightDate.getDate();
};

const patientCreatedAt = (consultation) => {
    const patient = consultation?.patient && typeof consultation.patient === 'object' ? consultation.patient : null;
    return consultation?.patientCreatedAt || consultation?.patient_created_at || patient?.createdAt || patient?.created_at || patient?.dateInscription || patient?.date_inscription || patient?.dateCreation || patient?.date_creation || null;
};

const isNewPatient = (consultation) => isSameCalendarDay(patientCreatedAt(consultation), new Date());

const patientLabel = (consultation) => {
    if (!consultation) return 'Patient';
    if (typeof consultation.patientName === 'string' && consultation.patientName.trim()) return consultation.patientName;
    if (typeof consultation.patient === 'string' && consultation.patient.trim()) return consultation.patient;
    const patient = consultation.patient || {};
    return `${patient.prenom ?? ''} ${patient.nom ?? ''}`.trim() || patient.nom || 'Patient';
};

const medecinLabel = (consultation) => {
    const medecin = consultation?.medecin;
    if (!medecin) return 'Non assigné';
    if (typeof medecin === 'string') return medecin;
    return medecin.label || medecin.fullName || medecin.name || `${medecin.prenom ?? ''} ${medecin.nom ?? ''}`.trim() || 'Non assigné';
};

const currentConsultation = computed(() => consultations.value.find((item) => item.id === selectedConsultationId.value) || null);

const selectedPatientLabel = computed(() => {
    if (currentConsultation.value) return patientLabel(currentConsultation.value);
    const patient = selectedPatient.value;
    if (!patient) return null;
    return `${patient.prenom ?? ''} ${patient.nom ?? ''}`.trim() || patient.nom || 'Patient';
});

const selectedEmbeddedFicheId = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return null;
    if (consultation.ficheId) return consultation.ficheId;
    return consultation.lastFicheId || null;
});

const currentConsultationClosed = computed(() => Number(currentConsultation.value?.state) === 1);

const canShowEmbeddedWorkspace = computed(() => {
    if (!currentConsultation.value || !selectedPatient.value) return false;
    if (!hidePatientDossier.value) return true;
    return !currentConsultationClosed.value;
});

const medecinQueue = computed(() => {
    const source = [...consultations.value].sort((left, right) => {
        const leftTime = parseDateTime(left.createdAt)?.getTime() || 0;
        const rightTime = parseDateTime(right.createdAt)?.getTime() || 0;
        return newestFirstMedecin.value ? rightTime - leftTime : leftTime - rightTime;
    });

    if (showCompletedMedecin.value) {
        return source;
    }

    return source.filter((item) => Number(item.state) !== 1);
});

const queueStats = computed(() => {
    const all = consultations.value || [];
    const pending = all.filter((item) => Number(item.state) !== 1).length;
    const completed = all.filter((item) => Number(item.state) === 1).length;
    return { pending, completed, total: all.length };
});

const selectConsultationFromQueue = (consultationId) => {
    emit('select-consultation', consultationId);
    if (isMobileLayout.value) {
        queueDrawerVisible.value = false;
    }
};

const syncEmbeddedPatientLists = (patient) => {
    embeddedFicheRef.value?.syncPatientMedicalLists?.(patient);
};

const openAntecedentDialog = () => {
    if (currentConsultationClosed.value) return;
    showAntecedentDialog.value = true;
};

const openAllergyDialog = () => {
    if (currentConsultationClosed.value) return;
    showAllergyDialog.value = true;
};

const handleSaveAntecedent = async (payload) => {
    const patientId = selectedPatient.value?.id;
    if (!patientId) return;
    savingAntecedent.value = true;
    try {
        const response = await addPatientAntecedent(patientId, payload, token);
        const antecedents = [...(selectedPatient.value?.antecedents || [])];
        if (response?.antecedent) antecedents.push(response.antecedent);
        const nextPatient = { ...selectedPatient.value, antecedents };
        emit('patient-loaded', nextPatient);
        syncEmbeddedPatientLists(nextPatient);
        showAntecedentDialog.value = false;
        toast.add({ severity: 'success', summary: 'Antécédent ajouté', life: 2000 });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'antécédent.", life: 3000 });
    } finally {
        savingAntecedent.value = false;
    }
};

const handleSaveAllergy = async (payload) => {
    const patientId = selectedPatient.value?.id;
    if (!patientId) return;
    savingAllergy.value = true;
    try {
        const response = await addPatientAllergy(patientId, payload, token);
        const allergies = [...(selectedPatient.value?.allergies || [])];
        if (response?.allergy) allergies.push(response.allergy);
        const nextPatient = { ...selectedPatient.value, allergies };
        emit('patient-loaded', nextPatient);
        syncEmbeddedPatientLists(nextPatient);
        showAllergyDialog.value = false;
        toast.add({ severity: 'success', summary: 'Allergie ajoutée', life: 2000 });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'allergie.", life: 3000 });
    } finally {
        savingAllergy.value = false;
    }
};

const deleteAntecedent = async (item) => {
    const patientId = selectedPatient.value?.id;
    if (!patientId || !item?.id || currentConsultationClosed.value) return;
    try {
        await deletePatientAntecedent(patientId, item.id, token);
        const nextPatient = {
            ...selectedPatient.value,
            antecedents: (selectedPatient.value?.antecedents || []).filter((entry) => entry.id !== item.id)
        };
        emit('patient-loaded', nextPatient);
        syncEmbeddedPatientLists(nextPatient);
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.', life: 3000 });
    }
};

const deleteAllergy = async (item) => {
    const patientId = selectedPatient.value?.id;
    if (!patientId || !item?.id || currentConsultationClosed.value) return;
    try {
        await deletePatientAllergy(patientId, item.id, token);
        const nextPatient = {
            ...selectedPatient.value,
            allergies: (selectedPatient.value?.allergies || []).filter((entry) => entry.id !== item.id)
        };
        emit('patient-loaded', nextPatient);
        syncEmbeddedPatientLists(nextPatient);
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.', life: 3000 });
    }
};

const updatePatientPhoto = (file) => {
    embeddedFicheRef.value?.updatePatientPhoto?.(file);
};

const handleOrdonnancesChanged = (ordonnances) => {
    consultationOrdonnances.value = Array.isArray(ordonnances) ? ordonnances : [];
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

const refreshOrdonnancesList = async () => {
    const consultationId = currentConsultation.value?.id;
    if (!consultationId) return;
    try {
        consultationOrdonnances.value = await loadOrdonnances(consultationId, token);
        await embeddedFicheRef.value?.refreshOrdonnances?.();
    } catch (_) {
        // La liste locale reste inchangée en cas d'échec
    }
};

const openOrdonnanceModal = () => {
    if (currentConsultationClosed.value || !currentConsultation.value?.id) return;
    ordonnanceModalMode.value = 'create';
    ordonnanceDraft.value = {
        date: new Date().toISOString().slice(0, 10),
        medecinNom: medecinLabel(currentConsultation.value),
        note: '',
        lignes: []
    };
    ordonnanceModalVisible.value = true;
};

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

const openViewOrdonnance = async (ordo) => {
    await loadOrdonnanceDraft(ordo, 'view');
};

const openEditOrdonnance = async (ordo) => {
    if (currentConsultationClosed.value) return;
    await loadOrdonnanceDraft(ordo, 'edit');
};

const saveOrdonnanceFromDossier = async () => {
    if (ordonnanceModalMode.value === 'view') {
        ordonnanceModalVisible.value = false;
        return;
    }

    const consultationId = currentConsultation.value?.id;
    if (!consultationId) return;

    savingOrdonnance.value = true;
    try {
        if (ordonnanceModalMode.value === 'edit' && ordonnanceDraft.value?.id) {
            await updateOrdonnance(ordonnanceDraft.value.id, ordonnanceDraft.value, token);
            toast.add({ severity: 'success', summary: 'Ordonnance mise à jour', life: 2000 });
        } else {
            await saveOrdonnance(consultationId, ordonnanceDraft.value, token);
            toast.add({ severity: 'success', summary: 'Ordonnance créée', life: 2000 });
        }
        ordonnanceModalVisible.value = false;
        await refreshOrdonnancesList();
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'enregistrer l'ordonnance.", life: 3000 });
    } finally {
        savingOrdonnance.value = false;
    }
};

const printOrdonnance = async (ordo) => {
    if (!ordo?.id) return;
    try {
        const response = await fetchOrdonnancePrintData(ordo.id, token);
        await printComponent(PrintOrdonnanceBody, { data: response.data });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'imprimer l'ordonnance.", life: 3000 });
    }
};

watch(
    () => selectedConsultationId.value,
    async (id) => {
        consultationOrdonnances.value = [];
        showAntecedentDialog.value = false;
        showAllergyDialog.value = false;
        ordonnanceModalVisible.value = false;
        if (id == null) return;
        await nextTick();
        queueItemRefs.value[id]?.scrollIntoView({ block: 'nearest' });
    },
    { immediate: true }
);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden lg:grid lg:grid-cols-[280px_minmax(0,1fr)_340px] lg:gap-4 xl:grid-cols-[300px_minmax(0,1fr)_360px] xl:gap-5">
        <!-- Mobile chrome -->
        <div
            v-if="isMobileLayout"
            class="flex shrink-0 items-center justify-between gap-2 rounded-2xl border border-cyan-200/60 bg-white/90 px-3 py-2.5 shadow-sm backdrop-blur-sm dark:border-cyan-800/40 dark:bg-surface-900/90"
        >
            <button
                type="button"
                class="flex min-h-10 items-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-cyan-600 px-3 py-2 text-xs font-semibold text-white shadow-md transition-all hover:shadow-lg active:scale-95"
                @click="queueDrawerVisible = true"
            >
                <i class="pi pi-list"></i>
                File
                <span class="rounded-full bg-white/25 px-1.5 py-0.5 text-[10px] font-bold">{{ queueStats.pending }}</span>
            </button>

            <div class="min-w-0 flex-1 truncate text-center">
                <template v-if="currentConsultation">
                    <p class="truncate text-xs font-semibold text-surface-800 dark:text-surface-100">{{ patientLabel(currentConsultation) }}</p>
                    <p class="truncate text-[10px] text-surface-400">{{ medecinLabel(currentConsultation) }}</p>
                </template>
                <template v-else>
                    <p class="text-xs font-medium text-surface-500">Aucune consultation</p>
                </template>
            </div>

            <button
                type="button"
                class="flex min-h-10 items-center gap-2 rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-700 transition-all hover:bg-cyan-100 disabled:opacity-40 dark:border-cyan-800/50 dark:bg-cyan-950/40 dark:text-cyan-300"
                :disabled="!selectedPatient"
                @click="dossierDrawerVisible = true"
            >
                <i class="pi pi-id-card"></i>
                Dossier
            </button>
        </div>

        <!-- Mobile main: fiche or empty -->
        <div v-if="isMobileLayout" class="mt-2 min-h-0 flex-1 overflow-hidden rounded-2xl border border-surface-200/60 bg-white/90 shadow-sm backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/90">
            <div v-if="canShowEmbeddedWorkspace" class="h-full overflow-y-auto">
                <EmbeddedConsultationFiche
                    ref="embeddedFicheRef"
                    :consultation-id="currentConsultation.id"
                    :fiche-id="selectedEmbeddedFicheId"
                    :readonly="currentConsultationClosed"
                    @patient-loaded="(payload) => emit('patient-loaded', payload)"
                    @ordonnances-changed="handleOrdonnancesChanged"
                    @closed="() => emit('consultation-closed')"
                />
            </div>
            <div v-else class="flex h-full flex-col items-center justify-center gap-4 p-8 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-cyan-100 to-cyan-200 dark:from-cyan-900/40 dark:to-cyan-800/40">
                    <i class="pi pi-file-edit text-3xl text-cyan-600 dark:text-cyan-400"></i>
                </div>
                <div>
                    <p class="text-base font-semibold text-surface-700 dark:text-surface-200">Espace de consultation</p>
                    <p class="mt-1 text-sm text-surface-400">Ouvrez la file pour sélectionner un patient</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl bg-gradient-to-r from-cyan-500 to-cyan-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all hover:shadow-lg active:scale-95"
                    @click="queueDrawerVisible = true"
                >
                    Ouvrir la file d'attente
                </button>
            </div>
        </div>

        <!-- Desktop: Dossier patient -->
        <aside v-if="!isMobileLayout" class="hidden min-h-0 flex-col overflow-hidden lg:flex">
            <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-surface-200/60 bg-white/90 shadow-sm backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/90">
                <div class="shrink-0 border-b border-surface-200/60 px-4 py-3 dark:border-surface-700/60">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-teal-600 shadow-md">
                                <i class="pi pi-id-card text-sm text-white"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-surface-900 dark:text-surface-50">Dossier patient</h3>
                                <p class="truncate text-xs text-surface-500">
                                    {{ selectedPatientLabel || 'Aucun sélectionné' }}
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="selectedPatient"
                            icon="pi pi-times"
                            severity="danger"
                            text
                            rounded
                            size="small"
                            v-tooltip.top="'Fermer la sélection'"
                            @click="emit('clear-selection')"
                        />
                    </div>
                </div>

                <div class="custom-scrollbar min-h-0 flex-1 overflow-y-auto p-3 ">
                    <DossierPatientInfoCard
                        v-if="selectedPatient && !hidePatientDossier"
                        :patient="selectedPatient"
                        :flat="true"
                        :hide-actions="true"
                        :hide-phone="hidePatientPhone"
                        :ordonnances="consultationOrdonnances"
                        :consultation-readonly="currentConsultationClosed"
                        @add-antecedent="openAntecedentDialog"
                        @add-allergy="openAllergyDialog"
                        @delete-antecedent="deleteAntecedent"
                        @delete-allergy="deleteAllergy"
                        @photo-selected="updatePatientPhoto"
                        @open-ordonnance="openOrdonnanceModal"
                        @view-ordonnance="openViewOrdonnance"
                        @edit-ordonnance="openEditOrdonnance"
                        @print-ordonnance="printOrdonnance"
                    />

                    <div v-else-if="selectedPatient && hidePatientDossier" class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-amber-100 to-amber-200 dark:from-amber-900/40 dark:to-amber-800/40">
                            <i class="pi pi-lock text-2xl text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <p class="text-sm font-medium text-surface-600 dark:text-surface-300">Dossier patient masqué</p>
                        <p class="mt-1 text-xs text-surface-400">Accès restreint pour ce profil</p>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-surface-100 to-surface-200 dark:from-surface-800 dark:to-surface-700">
                            <i class="pi pi-user text-2xl text-surface-400"></i>
                        </div>
                        <p class="text-sm font-medium text-surface-600 dark:text-surface-300">Aucun patient</p>
                        <p class="mt-1 max-w-[12rem] text-xs text-surface-400">Sélectionnez une consultation dans la file</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Desktop: Espace consultation -->
        <main v-if="!isMobileLayout" class="hidden min-h-0 flex-col overflow-hidden lg:flex">
            <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-surface-200/60 bg-white/90 shadow-sm backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/90">
                <div
                    v-if="currentConsultation"
                    class="flex shrink-0 items-center justify-between gap-3 border-b border-surface-200/60 px-4 py-3 dark:border-surface-700/60"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-cyan-600 shadow-md">
                            <i class="pi pi-file-edit text-sm text-white"></i>
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-semibold text-surface-900 dark:text-surface-50">
                                {{ patientLabel(currentConsultation) }}
                            </h3>
                            <p class="truncate text-xs text-surface-500">
                                {{ formatTime(currentConsultation.createdAt) }}
                                <span class="text-surface-300">·</span>
                                {{ medecinLabel(currentConsultation) }}
                            </p>
                        </div>
                    </div>
                    <span
                        :class="[
                            'shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                            currentConsultationClosed
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                        ]"
                    >
                        {{ currentConsultationClosed ? 'Terminée' : 'En cours' }}
                    </span>
                </div>

                <div v-if="canShowEmbeddedWorkspace" class="custom-scrollbar min-h-0 flex-1 overflow-y-auto">
                    <EmbeddedConsultationFiche
                        ref="embeddedFicheRef"
                        :consultation-id="currentConsultation.id"
                        :fiche-id="selectedEmbeddedFicheId"
                        :readonly="currentConsultationClosed"
                        @patient-loaded="(payload) => emit('patient-loaded', payload)"
                        @ordonnances-changed="handleOrdonnancesChanged"
                        @closed="() => emit('consultation-closed')"
                    />
                </div>

                <div v-else class="flex flex-1 flex-col items-center justify-center gap-4 p-10 text-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-cyan-100 to-teal-100 dark:from-cyan-900/30 dark:to-teal-900/30">
                        <i class="pi pi-file-edit text-4xl text-cyan-500 dark:text-cyan-400"></i>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-surface-700 dark:text-surface-200">Espace de consultation</p>
                        <p class="mt-1.5 max-w-sm text-sm text-surface-400">Sélectionnez une consultation dans la file d'attente pour commencer</p>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-xs text-surface-400">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                            {{ queueStats.pending }} en attente
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            {{ queueStats.completed }} terminée{{ queueStats.completed > 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>
            </div>
        </main>

        <!-- Desktop: File d'attente -->
        <aside v-if="!isMobileLayout" class="hidden min-h-0 flex-col overflow-hidden lg:flex">
            <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-2xl border border-surface-200/60 bg-white/90 shadow-sm backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/90">
                <div class="shrink-0 border-b border-surface-200/60 px-4 py-3 dark:border-surface-700/60">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 shadow-md">
                                <i class="pi pi-list text-sm text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">File d'attente</h3>
                                <p class="text-xs text-surface-500">
                                    <span class="font-medium text-amber-600 dark:text-amber-400">{{ queueStats.pending }}</span> en attente
                                    <span class="text-surface-300">·</span>
                                    <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ queueStats.completed }}</span> terminée{{ queueStats.completed > 1 ? 's' : '' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <label class="flex cursor-pointer items-center gap-1.5 rounded-lg px-1.5 py-1 text-[11px] text-surface-500 transition-colors hover:bg-surface-100 dark:hover:bg-surface-800">
                                <ToggleSwitch v-model="showCompletedMedecin" />
                                <span class="hidden xl:inline">Terminées</span>
                            </label>
                            <button
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-100 text-surface-500 transition-all hover:bg-surface-200 dark:bg-surface-800 dark:hover:bg-surface-700"
                                :title="newestFirstMedecin ? 'Plus récentes en haut' : 'Plus anciennes en haut'"
                                :aria-label="newestFirstMedecin ? 'Plus récentes en haut' : 'Plus anciennes en haut'"
                                @click="newestFirstMedecin = !newestFirstMedecin"
                            >
                                <i :class="newestFirstMedecin ? 'pi pi-sort-amount-down' : 'pi pi-sort-amount-up'" class="text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="custom-scrollbar min-h-0 flex-1 overflow-y-auto p-4">
                    <div v-if="medecinQueue.length" class="relative">
                        <div class="absolute bottom-0 left-4 top-0 w-0.5 bg-gradient-to-b from-cyan-300 via-cyan-200 to-transparent dark:from-cyan-600 dark:via-cyan-700"></div>

                        <div class="space-y-3">
                            <button
                                v-for="(consultation, index) in medecinQueue"
                                :key="consultation.id"
                                :ref="(element) => setQueueItemRef(consultation.id, element)"
                                type="button"
                                class="group relative flex w-full gap-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400"
                                @click="emit('select-consultation', consultation.id)"
                            >
                                <div class="relative z-10">
                                    <div
                                        :class="[
                                            'flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold shadow-md transition-all duration-200',
                                            consultation.id === selectedConsultationId
                                                ? 'scale-110 bg-gradient-to-br from-cyan-500 to-teal-600 text-white ring-2 ring-cyan-300 ring-offset-2 dark:ring-offset-surface-900'
                                                : Number(consultation.state) === 1
                                                  ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 text-white'
                                                  : 'bg-gradient-to-br from-surface-400 to-surface-500 text-white dark:from-surface-600 dark:to-surface-700'
                                        ]"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                </div>

                                <div
                                    :class="[
                                        'flex-1 rounded-xl border p-3 transition-all duration-200',
                                        consultation.id === selectedConsultationId
                                            ? 'border-cyan-200 bg-gradient-to-r from-cyan-50 to-transparent shadow-md dark:border-cyan-800 dark:from-cyan-950/30'
                                            : Number(consultation.state) === 1
                                              ? 'border-emerald-200 bg-emerald-50/30 opacity-75 dark:border-emerald-800 dark:bg-emerald-950/20'
                                              : 'border-surface-200 bg-surface-50/40 hover:border-cyan-200 hover:shadow-md dark:border-surface-700 dark:bg-surface-800/30'
                                    ]"
                                >
                                    <div class="mb-1.5 flex items-center justify-between gap-2">
                                        <span class="font-mono text-[11px] text-surface-400">{{ formatTime(consultation.createdAt) }}</span>
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                                Number(consultation.state) === 1
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                                            ]"
                                        >
                                            {{ Number(consultation.state) === 1 ? 'Terminé' : 'En attente' }}
                                        </span>
                                    </div>

                                    <p class="truncate text-sm font-semibold text-surface-900 dark:text-surface-50">
                                        {{ patientLabel(consultation) }}
                                    </p>
                                    <p class="mt-0.5 truncate text-xs text-surface-400">
                                        {{ medecinLabel(consultation) }}
                                        <span v-if="consultation.motif" class="text-surface-300">· {{ consultation.motif }}</span>
                                    </p>

                                    <div v-if="isNewPatient(consultation) || consultation.hasInsurance || consultation.patient?.insuranceProfile" class="mt-2 flex flex-wrap gap-1">
                                        <Tag v-if="isNewPatient(consultation)" value="Nouveau patient" size="small" severity="info" />
                                        <Tag v-if="consultation.hasInsurance || consultation.patient?.insuranceProfile" value="Assuré" size="small" severity="success" icon="pi pi-shield" />
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-surface-100 to-surface-200 dark:from-surface-800 dark:to-surface-700">
                            <i class="pi pi-inbox text-2xl text-surface-400"></i>
                        </div>
                        <p class="text-sm font-medium text-surface-600 dark:text-surface-300">File vide</p>
                        <p class="mt-1 text-xs text-surface-400">Aucune consultation à afficher</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile: File drawer -->
        <Drawer
            v-model:visible="queueDrawerVisible"
            position="bottom"
            class="focus-medecin-queue-drawer"
            :style="{ height: 'min(78dvh, 640px)' }"
            :dismissableMask="true"
            :blockScroll="true"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-primary-600">
                        <i class="pi pi-list text-xs text-white"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">File d'attente</p>
                        <p class="text-[11px] text-surface-500">{{ queueStats.pending }} en attente · {{ queueStats.completed }} terminée{{ queueStats.completed > 1 ? 's' : '' }}</p>
                    </div>
                </div>
            </template>

            <div class="mb-3 flex items-center justify-between gap-2 text-xs text-surface-500">
                <span>{{ medecinQueue.length }} affichée{{ medecinQueue.length > 1 ? 's' : '' }}</span>
                <div class="flex items-center gap-2">
                    <label class="flex cursor-pointer items-center gap-1.5">
                        <ToggleSwitch v-model="showCompletedMedecin" />
                        <span>Terminées</span>
                    </label>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-100 text-surface-500 dark:bg-surface-800"
                        :aria-label="newestFirstMedecin ? 'Plus récentes en haut' : 'Plus anciennes en haut'"
                        @click="newestFirstMedecin = !newestFirstMedecin"
                    >
                        <i :class="newestFirstMedecin ? 'pi pi-sort-amount-down' : 'pi pi-sort-amount-up'" class="text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="custom-scrollbar max-h-[calc(78dvh-8rem)] space-y-3 overflow-y-auto pb-4">
                <button
                    v-for="(consultation, index) in medecinQueue"
                    :key="`mobile-q-${consultation.id}`"
                    type="button"
                    class="flex w-full gap-3 rounded-xl border p-3 text-left transition-all"
                    :class="
                        consultation.id === selectedConsultationId
                            ? 'border-cyan-300 bg-gradient-to-r from-cyan-50 to-transparent shadow-sm dark:border-cyan-800 dark:from-cyan-950/30'
                            : Number(consultation.state) === 1
                              ? 'border-emerald-200 bg-emerald-50/30 opacity-80 dark:border-emerald-800 dark:bg-emerald-950/20'
                              : 'border-surface-200 bg-surface-50 dark:border-surface-700 dark:bg-surface-800'
                    "
                    @click="selectConsultationFromQueue(consultation.id)"
                >
                    <div
                        :class="[
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold shadow-sm',
                            consultation.id === selectedConsultationId
                                ? 'bg-gradient-to-br from-cyan-500 to-teal-600 text-white'
                                : Number(consultation.state) === 1
                                  ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 text-white'
                                  : 'bg-gradient-to-br from-surface-400 to-surface-500 text-white dark:from-surface-600 dark:to-surface-700'
                        ]"
                    >
                        {{ index + 1 }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="mb-0.5 flex items-center justify-between gap-2">
                            <span class="font-mono text-[11px] text-surface-400">{{ formatTime(consultation.createdAt) }}</span>
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-medium',
                                    Number(consultation.state) === 1 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400'
                                ]"
                            >
                                {{ Number(consultation.state) === 1 ? 'Terminé' : 'En attente' }}
                            </span>
                        </div>
                        <p class="truncate text-sm font-semibold text-surface-900 dark:text-white">{{ patientLabel(consultation) }}</p>
                        <p class="truncate text-xs text-surface-400">{{ medecinLabel(consultation) }}</p>
                        <div class="mt-1.5 flex flex-wrap gap-1">
                            <Tag v-if="isNewPatient(consultation)" value="Nouveau" size="small" severity="info" />
                            <Tag v-if="consultation.hasInsurance || consultation.patient?.insuranceProfile" value="Assuré" size="small" severity="success" icon="pi pi-shield" />
                        </div>
                    </div>
                </button>

                <div v-if="!medecinQueue.length" class="flex flex-col items-center py-12 text-center">
                    <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                        <i class="pi pi-inbox text-xl text-surface-400"></i>
                    </div>
                    <p class="text-sm text-surface-400">Aucune consultation dans la file</p>
                </div>
            </div>
        </Drawer>

        <!-- Mobile: Dossier drawer -->
        <Drawer
            v-model:visible="dossierDrawerVisible"
            position="bottom"
            :style="{ height: 'min(85dvh, 720px)' }"
            :dismissableMask="true"
            :blockScroll="true"
        >
            <template #header>
                <div class="flex w-full items-center justify-between gap-2 pr-2">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-500 to-teal-600">
                            <i class="pi pi-id-card text-xs text-white"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-surface-900 dark:text-surface-50">Dossier patient</p>
                            <p class="truncate text-[11px] text-surface-500">{{ selectedPatientLabel || 'Aucun sélectionné' }}</p>
                        </div>
                    </div>
                    <Button
                        v-if="selectedPatient"
                        icon="pi pi-times"
                        severity="danger"
                        text
                        rounded
                        size="small"
                        v-tooltip.top="'Fermer la sélection'"
                        @click="emit('clear-selection')"
                    />
                </div>
            </template>

            <div class="custom-scrollbar m-5 max-h-[calc(85dvh-5rem)] overflow-y-auto p-3">
                <DossierPatientInfoCard
                    v-if="selectedPatient && !hidePatientDossier"
                    :patient="selectedPatient"
                    :flat="true"
                    :hide-actions="true"
                    :hide-phone="hidePatientPhone"
                    :ordonnances="consultationOrdonnances"
                    :consultation-readonly="currentConsultationClosed"
                    @add-antecedent="openAntecedentDialog"
                    @add-allergy="openAllergyDialog"
                    @delete-antecedent="deleteAntecedent"
                    @delete-allergy="deleteAllergy"
                    @photo-selected="updatePatientPhoto"
                    @open-ordonnance="openOrdonnanceModal"
                    @view-ordonnance="openViewOrdonnance"
                    @edit-ordonnance="openEditOrdonnance"
                    @print-ordonnance="printOrdonnance"
                />

                <div v-else-if="selectedPatient && hidePatientDossier" class="flex flex-col items-center justify-center px-5 py-16 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-amber-100 to-amber-200 dark:from-amber-900/40 dark:to-amber-800/40">
                        <i class="pi pi-lock text-2xl text-amber-600"></i>
                    </div>
                    <p class="text-sm font-medium text-surface-600">Dossier patient masqué</p>
                </div>

                <div v-else class="flex flex-col items-center justify-center px-5 py-16 text-center">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                        <i class="pi pi-user text-2xl text-surface-400"></i>
                    </div>
                    <p class="text-sm font-medium text-surface-600">Aucun patient sélectionné</p>
                </div>
            </div>
        </Drawer>

        <AntecedentDialogForm v-model="showAntecedentDialog" :loading="savingAntecedent" @save="handleSaveAntecedent" />
        <AllergyDialogForm v-model="showAllergyDialog" :loading="savingAllergy" @save="handleSaveAllergy" />
        <OrdonnanceModal
            v-model="ordonnanceDraft"
            v-model:visible="ordonnanceModalVisible"
            :mode="ordonnanceModalMode"
            :medecin-readonly="true"
            :saving="savingOrdonnance"
            @save="saveOrdonnanceFromDossier"
        />
    </div>
</template>

<style scoped>

.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #9ca3af;
    border-radius: 20px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background-color: #6b7280;
}
</style>
