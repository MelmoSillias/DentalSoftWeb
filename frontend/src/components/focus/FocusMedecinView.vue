<script setup>
import EmbeddedConsultationFiche from '@/components/focus/EmbeddedConsultationFiche.vue';
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Tag from 'primevue/tag';
import { computed, defineEmits, defineProps, toRefs } from 'vue';

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
    }
});

const emit = defineEmits([
    'clear-selection',
    'select-consultation',
    'select-action-choice',
    'patient-loaded',
    'consultation-closed'
]);

const { consultations, selectedConsultationId, selectedPatient } = toRefs(props);

const showCompletedMedecin = defineModel('showCompletedMedecin', {
    type: Boolean,
    default: false
});

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

const patientLabel = (consultation) => {
    if (!consultation) return 'Patient';
    if (typeof consultation.patientName === 'string' && consultation.patientName.trim()) return consultation.patientName;
    if (typeof consultation.patient === 'string' && consultation.patient.trim()) return consultation.patient;
    const patient = consultation.patient || {};
    return `${patient.prenom ?? ''} ${patient.nom ?? ''}`.trim() || patient.nom || 'Patient';
};

const medecinLabel = (consultation) => {
    const medecin = consultation?.medecin;
    if (!medecin) return 'Non assigne';
    if (typeof medecin === 'string') return medecin;
    return medecin.label || medecin.fullName || medecin.name || `${medecin.prenom ?? ''} ${medecin.nom ?? ''}`.trim() || 'Non assigne';
};

const patientHistoryState = (consultation) => {
    if (consultation?.hasFiche || consultation?.lastFicheId || consultation?.ficheId) {
        return { label: 'Ancien patient', severity: 'info' };
    }
    return { label: 'Nouveau patient', severity: 'contrast' };
};

const queueItemClass = (consultation) => {
    if (Number(consultation.state) === 1) {
        return 'border-green-400/70 bg-green-50/70 dark:bg-green-950/20';
    }
    if (consultation.id === selectedConsultationId.value) {
        return 'border-primary-500 ring-2 ring-primary-400/60 ring-offset-2 ring-offset-surface-0 shadow-xl shadow-primary-500/20 bg-primary-50 dark:bg-primary-950/20 dark:ring-offset-surface-900';
    }
    return 'border-surface-200/70 bg-surface-0/90 dark:border-surface-700/70 dark:bg-surface-800/80';
};

const medecinQueue = computed(() => {
    const source = [...consultations.value].sort((left, right) => {
        const leftTime = parseDateTime(left.createdAt)?.getTime() || 0;
        const rightTime = parseDateTime(right.createdAt)?.getTime() || 0;
        return leftTime - rightTime;
    });

    if (showCompletedMedecin.value) {
        return source;
    }

    return source.filter((item) => Number(item.state) !== 1);
});

const currentConsultation = computed(() => consultations.value.find((item) => item.id === selectedConsultationId.value) || null);

const selectedActionChoice = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return null;
    return consultation.ficheId ? 'continue-last' : consultation.focusActionChoice || null;
});

const requiresChoice = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return false;
    if (consultation.ficheId) return false;
    return Boolean(consultation.hasFiche || consultation.lastFicheId);
});

const selectedEmbeddedMode = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return 'continue';
    if (consultation.ficheId) return 'continue';
    if (!requiresChoice.value) return 'new-fiche';
    return selectedActionChoice.value === 'new-fiche' ? 'new-fiche' : 'continue';
});

const selectedEmbeddedFicheId = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return null;
    if (consultation.ficheId) return consultation.ficheId;
    if (selectedActionChoice.value === 'continue-last') return consultation.lastFicheId || null;
    return null;
});

const selectedChoiceLabel = computed(() => {
    if (selectedActionChoice.value === 'continue-last') return 'Reprise de la derniere fiche';
    if (selectedActionChoice.value === 'new-fiche') return 'Nouvelle fiche choisie';
    if (currentConsultation.value?.ficheId) return 'Fiche liee en cours';
    if (!requiresChoice.value) return 'Nouvelle fiche automatique';
    return '';
});

const currentConsultationClosed = computed(() => Number(currentConsultation.value?.state) === 1);
</script>
 
<template>
    <div class="grid gap-4 xl:grid-cols-[300px_minmax(0,1fr)_300px]">
        <!-- Colonne gauche : Patient sélectionné -->
        <aside>
            <div class="rounded-xl border-2 border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">Patient</h3>
                    <button v-if="selectedPatient" @click="emit('clear-selection')" class="text-xs text-surface-400 hover:text-red-500">
                        <i class="pi pi-times"></i>
                    </button>
                </div>
                <DossierPatientInfoCard v-if="selectedPatient" :patient="selectedPatient" :hide-actions="true" />
                <div v-else class="py-10 text-center text-sm text-surface-400">
                    <i class="pi pi-user text-2xl mb-2 opacity-50"></i>
                    <p>Aucun patient sélectionné</p>
                </div>
            </div>
        </aside>

        <!-- Zone centrale : Fiche ou placeholder -->
        <section>
            <!-- Choix de fiche -->
            <div v-if="currentConsultation && !currentConsultationClosed && requiresChoice && !selectedActionChoice"
                 class="mb-3 rounded-lg border-l-4 border-amber-500 bg-amber-50 p-3 dark:border-amber-600 dark:bg-amber-950/20">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-100">Fiche existante détectée</p>
                        <p class="text-xs text-amber-700 mt-0.5 dark:text-amber-300">Choisissez une action pour ce patient</p>
                    </div>
                    <div class="flex gap-1.5">
                        <button
                            @click="emit('select-action-choice', currentConsultation, 'continue-last')"
                            class="rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-amber-700 border border-amber-200 hover:bg-amber-100 dark:bg-amber-900/40 dark:text-amber-300 dark:border-amber-700">
                            Reprendre
                        </button>
                        <button
                            @click="emit('select-action-choice', currentConsultation, 'new-fiche')"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium text-amber-600 hover:bg-white dark:text-amber-300 dark:hover:bg-amber-900/40">
                            Nouvelle
                        </button>
                    </div>
                </div>
            </div>

            <!-- Fiche intégrée -->
            <div v-if="currentConsultation && selectedPatient" class="rounded-xl border-2 border-surface-200 bg-white dark:border-surface-700 dark:bg-surface-900">
                <EmbeddedConsultationFiche 
                    :consultation-id="currentConsultation.id"
                    :fiche-id="selectedEmbeddedFicheId"
                    :mode="selectedEmbeddedMode"
                    :readonly="currentConsultationClosed"
                    :choice-label="selectedChoiceLabel"
                    @patient-loaded="(payload) => emit('patient-loaded', payload)"
                    @closed="async () => { emit('consultation-closed'); }"
                />
            </div>

            <div v-else class="flex flex-col items-center justify-center py-16 rounded-xl border-2 border-dashed border-surface-300 bg-white dark:border-surface-700 dark:bg-surface-900">
                <i class="pi pi-file-edit text-3xl text-surface-300 mb-3"></i>
                <p class="text-sm text-surface-500">Espace de consultation</p>
            </div>
        </section>

        <!-- Colonne droite : File d'attente médecin -->
        <aside>
            <div class="rounded-xl border-2 border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-50">File d'attente</h3>
                        <p class="text-xs text-surface-500">{{ medecinQueue.length }} patients</p>
                    </div>
                    <label class="flex cursor-pointer items-center gap-1.5 text-xs text-surface-600 dark:text-surface-400">
                        <input type="checkbox" v-model="showCompletedMedecin" class="h-3.5 w-3.5 rounded border-surface-300 text-primary-600 dark:border-surface-600" />
                        Terminées
                    </label>
                </div>

                <div class="space-y-2">
                    <button
                        v-for="consultation in medecinQueue"
                        :key="consultation.id"
                        @click="emit('select-consultation', consultation.id)"
                        class="group w-full rounded-lg border-l-4 p-3 text-left transition-all duration-200 hover:scale-[1.02] hover:shadow-md"
                        :class="[
                            consultation.id === selectedConsultationId
                                ? 'bg-primary-50/90 border-l-primary-500 shadow-md ring-1 ring-primary-200 dark:bg-primary-950/30 dark:border-l-primary-400'
                                : Number(consultation.state) === 1
                                    ? 'bg-green-50/40 border-l-green-400 opacity-75 dark:bg-green-950/10 dark:border-l-green-600'
                                    : 'bg-white border-l-amber-400 hover:bg-amber-50/30 dark:bg-surface-900 dark:border-l-amber-500 dark:hover:bg-amber-950/20'
                        ]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-mono font-bold text-surface-600 dark:text-surface-300">{{ formatTime(consultation.createdAt) }}</span>
                                    <span :class="[
                                        'inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium',
                                        consultation.hasFiche || consultation.lastFicheId
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-400'
                                            : 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-400'
                                    ]">
                                        {{ consultation.hasFiche || consultation.lastFicheId ? 'Ancien' : 'Nouveau' }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold truncate text-surface-900 dark:text-surface-50">{{ patientLabel(consultation) }}</p>
                                <p class="text-xs text-surface-500 truncate">{{ medecinLabel(consultation) }}</p>
                            </div>
                            <span :class="[
                                'mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0',
                                Number(consultation.state) === 1
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-400'
                                    : consultation.id === selectedConsultationId
                                        ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/40 dark:text-primary-400'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-400'
                            ]">
                                {{ Number(consultation.state) === 1 ? '✓' : consultation.id === selectedConsultationId ? 'Actif' : '●' }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </aside>
    </div>
</template>