<script setup>
import Button from 'primevue/button';
import Carousel from 'primevue/carousel';
import ConfirmDialog from 'primevue/confirmdialog';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import FicheMedicalEditPanel from '@/components/patients/FicheMedicalEditPanel.vue';
import FicheMedicalV2 from '@/components/patients/FicheMedicalV2.vue';
import { createNewFicheForPatient } from '@/composables/useFicheMedicaleAccess';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';

const props = defineProps({
    fiches: {
        type: Array,
        default: () => []
    },
    patientId: {
        type: [Number, String],
        default: null
    },
    canCreateConsultation: {
        type: Boolean,
        default: true
    },
    canEditFiche: {
        type: Boolean,
        default: false
    },
    patientAge: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['print-fiche', 'new-consultation', 'fiche-updated', 'fiche-created']);

const confirm = useConfirm();
const toast = useToast();
const auth = useAuthStore();
const token = localStorage.getItem('token');

const currentFicheIndex = ref(0);
const isExpanded = ref(false);
const isEditMode = ref(false);
const editPanelRef = ref(null);
const editHasDirty = ref(false);
const creatingFiche = ref(false);

const orderedFiches = computed(() => props.fiches || []);
const selectedFiche = computed(() => orderedFiches.value[currentFicheIndex.value] || null);

const canEdit = computed(() => {
    if (props.canEditFiche) return true;
    const roles = auth.user?.roles || [];
    return roles.includes('ROLE_ADMIN') || roles.includes('ROLE_MEDECIN');
});

const ficheOptions = computed(() =>
    orderedFiches.value.map((fiche, index) => ({
        label: `${formatPosition(index)} - ${formatDateShort(fiche.dateCreation || fiche.createdAt || fiche.date)}`,
        value: index
    }))
);

const expandedDialogSubtitle = computed(() => {
    if (!selectedFiche.value) return null;
    return `Créée le ${formatDateShort(selectedFiche.value.dateCreation || selectedFiche.value.createdAt)}`;
});

const expandedDialogTitle = computed(() => `Fiche médicale ${formatPosition(currentFicheIndex.value)}`);


function prevFiche() {
    if (!orderedFiches.value.length) return;
    currentFicheIndex.value = (currentFicheIndex.value - 1 + orderedFiches.value.length) % orderedFiches.value.length;
}

function nextFiche() {
    if (!orderedFiches.value.length) return;
    currentFicheIndex.value = (currentFicheIndex.value + 1) % orderedFiches.value.length;
}

function formatDateShort(date) {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatPosition(index) {
    const position = index + 1;
    if (position === 1) return '1ère';
    if (position === 2) return '2ème';
    if (position === 3) return '3ème';
    return `${position}ème`;
}

function openExpanded() {
    if (!selectedFiche.value) return;
    isEditMode.value = false;
    editHasDirty.value = false;
    isExpanded.value = true;
}

function closeExpanded() {
    if (isEditMode.value && editHasDirty.value) {
        confirm.require({
            message: 'Des modifications non enregistrées seront perdues. Continuer ?',
            header: 'Modifications en cours',
            icon: 'pi pi-exclamation-triangle',
            rejectLabel: 'Annuler',
            acceptLabel: 'Fermer',
            accept: () => {
                isEditMode.value = false;
                editHasDirty.value = false;
                isExpanded.value = false;
            }
        });
        return;
    }
    isEditMode.value = false;
    isExpanded.value = false;
}

function toggleEditMode() {
    if (isEditMode.value && editHasDirty.value) {
        confirm.require({
            message: "Des modifications non enregistrées seront perdues. Revenir à l'aperçu ?",
            header: 'Modifications en cours',
            icon: 'pi pi-exclamation-triangle',
            rejectLabel: 'Annuler',
            acceptLabel: 'Aperçu',
            accept: () => {
                isEditMode.value = false;
                editHasDirty.value = false;
            }
        });
        return;
    }
    isEditMode.value = !isEditMode.value;
}

function handlePrint() {
    if (selectedFiche.value) emit('print-fiche', selectedFiche.value);
}

function handleFicheSaved() {
    editHasDirty.value = false;
    emit('fiche-updated');
}

function askCreateNewFiche() {
    if (!canEdit.value || !props.patientId || creatingFiche.value) return;

    confirm.require({
        message: 'Créer une nouvelle fiche médicale pour ce patient ? Les fiches précédentes restent consultables.',
        header: 'Nouvelle fiche médicale',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Annuler',
        acceptLabel: 'Créer',
        accept: () => createNewFiche()
    });
}

async function createNewFiche() {
    if (!props.patientId) return;
    creatingFiche.value = true;
    try {
        const result = await createNewFicheForPatient(props.patientId, token);
        toast.add({
            severity: 'success',
            summary: 'Fiche créée',
            detail: 'Une nouvelle fiche médicale a été créée.',
            life: 2500
        });
        currentFicheIndex.value = 0;
        emit('fiche-created', result);
    } catch (error) {
        logAppError('Erreur création fiche médicale', error);
        toast.add({
            severity: 'error',
            summary: 'Erreur',
            detail: 'Impossible de créer une nouvelle fiche médicale.',
            life: 3000
        });
    } finally {
        creatingFiche.value = false;
    }
}

watch(isExpanded, (visible) => {
    if (!visible) {
        isEditMode.value = false;
        editHasDirty.value = false;
    }
});

watch(
    () => props.fiches?.length,
    (length, previousLength) => {
        if (Number(length) > Number(previousLength || 0)) {
            currentFicheIndex.value = 0;
        }
    }
);
</script>

<template>
    <div>
        <div class="page-section">
            <div class="page-section__header" data-tour="patients-dossier.fiches-toolbar">
                <div class="page-section__header-main">
                    <h3 class="page-section__title">Fiches médicales</h3>
                    <p class="page-section__subtitle">{{ orderedFiches.length }} fiche(s)</p>
                </div>
                <div class="page-section__header-actions">
                    <div class="flex items-center gap-1 sm:hidden">
                        <Button icon="pi pi-chevron-left" severity="secondary" text size="small" :disabled="!orderedFiches.length" @click="prevFiche" />
                        <Button icon="pi pi-chevron-right" severity="secondary" text size="small" :disabled="!orderedFiches.length" @click="nextFiche" />
                    </div>
                    <Button
                        icon="pi pi-external-link"
                        label="Agrandir"
                        severity="secondary"
                        outlined
                        size="small"
                        :disabled="!orderedFiches.length"
                        @click="openExpanded"
                        data-tour="patients-dossier.fiches-expand"
                        :pt="{ label: { class: 'hidden sm:inline' } }"
                    />
                    <Button
                        v-if="canEdit"
                        icon="pi pi-file-plus"
                        label="Nouvelle fiche"
                        severity="secondary"
                        outlined
                        size="small"
                        :loading="creatingFiche"
                        :disabled="!patientId"
                        @click="askCreateNewFiche"
                        data-tour="patients-dossier.fiches-new"
                        :pt="{ label: { class: 'hidden sm:inline' } }"
                    />
                    <Button
                        v-if="canCreateConsultation"
                        icon="pi pi-plus"
                        label="Nouvelle consultation"
                        size="small"
                        @click="emit('new-consultation')"
                        data-tour="patients-dossier.fiches-new-consultation"
                        :pt="{ label: { class: 'hidden sm:inline' } }"
                    />
                </div>
            </div>
            <div class="p-3 md:p-4">
                <div class="relative" data-tour="patients-dossier.fiches-preview">
                    <Carousel :value="orderedFiches" :numVisible="1" :numScroll="1" v-model:page="currentFicheIndex" :showIndicators="false" :showNavigators="false" circular class="medical-fiches-carousel">
                        <template #item="slotProps">
                            <div class="medical-fiches-item">
                                <div class="medical-fiche-paper">
                                    <FicheMedicalV2 :fiche="slotProps.data" :position-label="formatPosition(slotProps.index)" :patient-age="patientAge" compact @print="emit('print-fiche', slotProps.data)" />
                                </div>
                            </div>
                        </template>
                    </Carousel>

                    <div class="flex items-center justify-center gap-1.5 mt-3">
                        <button
                            v-for="(fiche, index) in orderedFiches"
                            :key="fiche.id || index"
                            type="button"
                            @click="currentFicheIndex = index"
                            :class="['dossier-folder__tab-badge transition-all', currentFicheIndex === index ? 'dossier-tag--accent' : '']"
                            :style="currentFicheIndex === index ? { minWidth: '1.75rem' } : { minWidth: '0.5rem', padding: '0.2rem' }"
                            :aria-label="`Fiche ${index + 1}`"
                        >
                            <span v-if="currentFicheIndex === index">{{ index + 1 }}</span>
                        </button>
                    </div>

                    <div class="dossier-section-block" data-tour="patients-dossier.fiches-jump">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div style="font-size: var(--page-section-subtitle-size); color: var(--text-color-secondary)">Fiche {{ currentFicheIndex + 1 }} sur {{ orderedFiches.length }}</div>
                            <Select v-model="currentFicheIndex" :options="ficheOptions" optionLabel="label" optionValue="value" placeholder="Aller à une fiche..." class="w-full sm:w-48" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AppDialog
            v-model:visible="isExpanded"
            :title="expandedDialogTitle"
            :subtitle="expandedDialogSubtitle"
            icon="pi pi-folder-open"
            icon-tone="primary"
            size="full"
            :closable="false"
            :draggable="false"
            :style="{ width: 'min(96vw, 80rem)' }"
            :show-footer="true"
        >
            <template #headerExtra>
                <div class="flex items-center gap-2">
                    <span
                        v-if="isEditMode"
                        class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                    >
                        Édition
                    </span>
                    <Button icon="pi pi-chevron-left" severity="secondary" text rounded :disabled="orderedFiches.length <= 1" @click="prevFiche" />
                    <span class="text-sm text-surface-500">{{ currentFicheIndex + 1 }} / {{ orderedFiches.length }}</span>
                    <Button icon="pi pi-chevron-right" severity="secondary" text rounded :disabled="orderedFiches.length <= 1" @click="nextFiche" />
                </div>
            </template>

            <div>
                <FicheMedicalEditPanel v-if="isEditMode && selectedFiche?.id" ref="editPanelRef" :key="`edit-${selectedFiche.id}`" :fiche-id="selectedFiche.id" tall @saved="handleFicheSaved" @dirty-change="editHasDirty = $event" />
                <FicheMedicalV2 v-else-if="selectedFiche" :key="`view-${selectedFiche.id}`" :fiche="selectedFiche" :position-label="formatPosition(currentFicheIndex)" :patient-age="patientAge" compact hide-actions tall />
            </div>

            <template #footer>
                <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                    <div class="flex flex-wrap items-center gap-2">
                        <Button icon="pi pi-print" label="Imprimer" severity="secondary" outlined :disabled="!selectedFiche" @click="handlePrint" />
                        <Button
                            v-if="canEdit"
                            :icon="isEditMode ? 'pi pi-eye' : 'pi pi-pencil'"
                            :label="isEditMode ? 'Aperçu' : 'Modifier'"
                            :severity="isEditMode ? 'secondary' : 'primary'"
                            :outlined="isEditMode"
                            :disabled="!selectedFiche"
                            @click="toggleEditMode"
                        />
                    </div>
                    <Button label="Fermer" icon="pi pi-times" severity="secondary" text @click="closeExpanded" />
                </div>
            </template>
        </AppDialog>

        <ConfirmDialog />
    </div>
</template>

<style scoped>
.medical-fiches-carousel :deep(.p-carousel-content),
.medical-fiches-carousel :deep(.p-carousel-items-content) {
    overflow: hidden;
}

.medical-fiches-carousel :deep(.p-carousel-item) {
    padding: 0;
    width: 100%;
}

.medical-fiches-item {
    width: 100%;
}

@media (max-width: 639px) {
    .medical-fiches-carousel :deep(.p-carousel-prev-button),
    .medical-fiches-carousel :deep(.p-carousel-next-button) {
        display: none;
    }
}
</style>
