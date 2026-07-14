<script setup>
import EmbeddedConsultationFiche from '@/components/focus/EmbeddedConsultationFiche.vue';
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';

import Splitter from 'primevue/splitter';
import SplitterPanel from 'primevue/splitterpanel';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Tag from 'primevue/tag';

import { computed, onMounted, ref, toRefs, watch } from 'vue';

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

const emit = defineEmits([
    'clear-selection',
    'select-consultation',
    'select-action-choice',
    'patient-loaded',
    'consultation-closed'
]);

const { consultations, selectedConsultationId, selectedPatient, hidePatientDossier, hidePatientPhone } = toRefs(props);

const showCompletedMedecin = defineModel('showCompletedMedecin', {
    type: Boolean,
    default: false
});

const newestFirstMedecin = ref(false);
const embeddedFicheRef = ref(null);
const consultationOrdonnances = ref([]);

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
    return leftDate.getFullYear() === rightDate.getFullYear()
        && leftDate.getMonth() === rightDate.getMonth()
        && leftDate.getDate() === rightDate.getDate();
};

const patientCreatedAt = (consultation) => {
    const patient = consultation?.patient && typeof consultation.patient === 'object'
        ? consultation.patient
        : null;
    return consultation?.patientCreatedAt
        || consultation?.patient_created_at
        || patient?.createdAt
        || patient?.created_at
        || patient?.dateInscription
        || patient?.date_inscription
        || patient?.dateCreation
        || patient?.date_creation
        || null;
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

const selectedEmbeddedMode = computed(() => {
    // Focus never force-creates: backend links last fiche or creates only if none exist.
    return 'continue';
});

const selectedEmbeddedFicheId = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return null;
    if (consultation.ficheId) return consultation.ficheId;
    return consultation.lastFicheId || null;
});

const selectedChoiceLabel = computed(() => {
    if (currentConsultation.value?.ficheId) return 'Fiche liée en cours';
    if (currentConsultation.value?.hasFiche || currentConsultation.value?.lastFicheId) {
        return 'Reprise de la dernière fiche';
    }
    return 'Nouvelle fiche';
});

const currentConsultation = computed(() =>
    consultations.value.find((item) => item.id === selectedConsultationId.value) || null
);

const currentConsultationClosed = computed(() =>
    Number(currentConsultation.value?.state) === 1
);

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

const queueItemClass = (consultation) => {
    if (Number(consultation.state) === 1) {
        return 'border-green-400/70 bg-green-50/70 dark:bg-green-950/20';
    }
    if (consultation.id === selectedConsultationId.value) {
        return 'border-primary-500 ring-2 ring-primary-400/60 bg-primary-50 dark:bg-primary-950/20 dark:ring-offset-surface-900';
    }
    return 'border-surface-200/70 bg-surface-0/90 dark:border-surface-700/70 dark:bg-surface-800/80';
};

const openAntecedentDialog = () => {
    embeddedFicheRef.value?.openAntecedentDialog?.();
};

const openAllergyDialog = () => {
    embeddedFicheRef.value?.openAllergyDialog?.();
};

const deleteAntecedent = (item) => {
    embeddedFicheRef.value?.deleteAntecedent?.(item);
};

const deleteAllergy = (item) => {
    embeddedFicheRef.value?.deleteAllergy?.(item);
};

const updatePatientPhoto = (file) => {
    embeddedFicheRef.value?.updatePatientPhoto?.(file);
};

const handleOrdonnancesChanged = (ordonnances) => {
    consultationOrdonnances.value = Array.isArray(ordonnances) ? ordonnances : [];
};

const openOrdonnanceModal = () => {
    embeddedFicheRef.value?.openOrdonnanceModal?.();
};

const openViewOrdonnance = (ordo) => {
    embeddedFicheRef.value?.openViewOrdonnance?.(ordo);
};

const openEditOrdonnance = (ordo) => {
    embeddedFicheRef.value?.openEditOrdonnance?.(ordo);
};

const printOrdonnance = (ordo) => {
    embeddedFicheRef.value?.handlePrintOrdonnance?.(ordo);
};

onMounted(() => {
    emit('clear-selection');
});

watch(
    () => selectedConsultationId.value,
    () => {
        consultationOrdonnances.value = [];
    }
);
</script>

<template>
    <div class="max-h-[80vh] h-[80vh] bg-surface-50 dark:bg-surface-950 shadow-sm overflow-hidden">
        <Splitter class="h-full border border-surface-200 dark:border-surface-700 rounded-2xl overflow-hidden shadow-sm"
            :min-size="[220, 400, 240]">
            <!-- ==================== PANNEAU GAUCHE : DOSSIER PATIENT ==================== -->
            <SplitterPanel :size="20" class="flex flex-col">
                <div
                    class="p-2 bg-white dark:bg-surface-900 flex flex-col border-r border-surface-200 dark:border-surface-700 relative h-full overflow-y-auto">
                    <div class="absolute top-4 right-4 z-10">
                        <Button v-if="selectedPatient" icon="pi pi-times" severity="danger" text size="small"
                            @click="emit('clear-selection')" />
                    </div>

                    <DossierPatientInfoCard v-if="selectedPatient && !hidePatientDossier" :patient="selectedPatient"
                        :hide-actions="true"
                        :hide-phone="hidePatientPhone"
                        :ordonnances="consultationOrdonnances"
                        :consultation-readonly="currentConsultationClosed"
                        class="flex-1 overflow-y-auto"
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

                    <div v-else-if="selectedPatient && hidePatientDossier"
                        class="flex-1 flex flex-col items-center justify-center text-center text-surface-400 my-10">
                        <i class="pi pi-lock text-5xl mb-4 opacity-75"></i>
                        <p class="text-base">Dossier patient masqué</p>
                    </div>

                    <div v-else
                        class="flex-1 flex flex-col items-center justify-center text-center text-surface-400 my-10">
                        <i class="pi pi-user text-5xl mb-4 opacity-75"></i>
                        <p class="text-base">Aucun patient sélectionné</p>
                    </div>
                </div>
            </SplitterPanel>

            <SplitterPanel :size="56" :minSize="40"
                class="flex flex-col overflow-y-auto bg-surface-50 dark:bg-surface-950">
                <div v-if="canShowEmbeddedWorkspace"
                    class="flex-1 border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-900 overflow-y-auto">
                    <EmbeddedConsultationFiche ref="embeddedFicheRef" :consultation-id="currentConsultation.id"
                        :fiche-id="selectedEmbeddedFicheId" :mode="selectedEmbeddedMode"
                        :readonly="currentConsultationClosed" :choice-label="selectedChoiceLabel"
                        @patient-loaded="(payload) => emit('patient-loaded', payload)"
                        @ordonnances-changed="handleOrdonnancesChanged"
                        @closed="() => emit('consultation-closed')" />
                </div>

                <div v-else class="flex-1 flex flex-col items-center pt-5 text-surface-400">
                    <i class="pi pi-file-edit text-6xl mb-6 opacity-40"></i>
                    <p class="text-xl font-medium">Espace de consultation</p>
                    <p class="text-sm mt-2 max-w-xs text-center">
                        Sélectionnez une consultation dans la file d'attente pour commencer
                    </p>
                </div>
            </SplitterPanel>

            <SplitterPanel :size="20" class="flex flex-col">
                <div class="h-full flex flex-col bg-white dark:bg-surface-900 border-l border-surface-200 dark:border-surface-700">
                    <div
                        class="flex items-center justify-between px-5 py-4 border-b border-surface-200 dark:border-surface-700">
                        <div>
                            <h3 class="font-semibold text-lg text-surface-900 dark:text-white">
                                File d'attente
                            </h3>
                            <p class="text-xs text-surface-500">
                                {{ medecinQueue.length }} patients en attente
                            </p>
                        </div>

                        <div class="flex items-center gap-2 text-xs text-surface-500">
                            <ToggleSwitch v-model="showCompletedMedecin" />
                            <span>Terminées</span>
                            <Button
                                :icon="newestFirstMedecin ? 'pi pi-sort-amount-down' : 'pi pi-sort-amount-up'"
                                text
                                rounded
                                size="small"
                                :aria-label="newestFirstMedecin ? 'Plus récentes en haut' : 'Plus anciennes en haut'"
                                @click="newestFirstMedecin = !newestFirstMedecin"
                            />
                        </div>
                    </div>

                    <div class="flex-1 overflow-auto px-4 py-4 custom-scrollbar">
                        <div class="relative">
                            <div class="absolute left-4 top-0 bottom-0 w-px bg-surface-200 dark:bg-surface-700"></div>

                            <div class="space-y-4">
                                <button v-for="(consultation, index) in medecinQueue" :key="consultation.id"
                                    @click="emit('select-consultation', consultation.id)"
                                    class="relative w-full text-left flex gap-4 group">

                                    <div class="relative z-10 flex flex-col items-center">
                                        <div :class="[
                                            'w-8 h-8 flex items-center justify-center rounded-full text-xs font-bold border transition-all',

                                            consultation.id === selectedConsultationId
                                                ? 'bg-primary-500 text-white border-primary-500 scale-110 shadow-md'
                                                : Number(consultation.state) === 1
                                                    ? 'bg-green-100 text-green-700 border-green-300'
                                                    : 'bg-white dark:bg-surface-800 text-surface-500 border-surface-300'
                                        ]">
                                            {{ index + 1 }}
                                        </div>

                                        <div class="flex-1 w-px"></div>
                                    </div>

                                    <div :class="[
                                        'flex-1 rounded-xl border p-3 transition-all',

                                        consultation.id === selectedConsultationId
                                            ? 'bg-primary-50 dark:bg-primary-900/20 border-primary-300 shadow-md'
                                            : 'bg-surface-50 dark:bg-surface-800 border-surface-200 dark:border-surface-700 hover:shadow-sm'
                                    ]">

                                        <!-- Top -->
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="font-mono text-[11px] text-surface-500">
                                                {{ formatTime(consultation.createdAt) }}
                                            </span>

                                            <span :class="[
                                                'text-[11px] px-2 py-0.5 rounded-full font-medium',
                                                Number(consultation.state) === 1
                                                    ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-400'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-400'
                                            ]">
                                                {{ Number(consultation.state) === 1 ? 'Terminé' : 'En attente' }}
                                            </span>
                                        </div>

                                        <p class="font-semibold text-sm text-surface-900 dark:text-white truncate">
                                            {{ patientLabel(consultation) }}
                                        </p>

                                        <p class="text-xs text-surface-500 truncate">
                                            {{ medecinLabel(consultation) }}
                                        </p>

                                        <div class="mt-2 flex flex-wrap gap-1">
                                            <Tag v-if="isNewPatient(consultation)" value="Nouveau patient"
                                                size="small" severity="info" />
                                            <Tag
                                                v-if="consultation.hasInsurance || consultation.patient?.insuranceProfile"
                                                value="Assuré"
                                                size="small"
                                                severity="success"
                                                icon="pi pi-shield"
                                            />
                                        </div>

                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </SplitterPanel>
        </Splitter>
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
