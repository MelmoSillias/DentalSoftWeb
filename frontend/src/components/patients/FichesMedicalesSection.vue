<script setup>
import Button from 'primevue/button';
import Carousel from 'primevue/carousel';
import ConfirmDialog from 'primevue/confirmdialog';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { computed, ref, watch } from 'vue';
import FicheMedicalEditPanel from '@/components/patients/FicheMedicalEditPanel.vue';
import FicheMedicalV2 from '@/components/patients/FicheMedicalV2.vue';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({
    fiches: {
        type: Array,
        default: () => []
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

const emit = defineEmits(['print-fiche', 'new-consultation', 'fiche-updated']);

const confirm = useConfirm();
const auth = useAuthStore();

const currentFicheIndex = ref(0);
const isExpanded = ref(false);
const isEditMode = ref(false);
const editPanelRef = ref(null);
const editHasDirty = ref(false);

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

const expandedDialogPt = {
    root: { class: 'w-full max-w-7xl flex flex-col max-h-[90vh]' },
    header: { class: 'shrink-0 border-b border-surface-200/50 dark:border-surface-700/50' },
    content: { class: 'flex-1 overflow-y-auto p-0' },
    footer: { class: 'shrink-0 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50/80 dark:bg-surface-900/80 px-6 py-4' }
};

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
            message: 'Des modifications non enregistrées seront perdues. Revenir à l\'aperçu ?',
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

watch(isExpanded, (visible) => {
    if (!visible) {
        isEditMode.value = false;
        editHasDirty.value = false;
    }
});
</script>

<template>
    <div>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800" data-tour="patients-dossier.fiches-toolbar">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                    <i class="pi pi-file-medical text-primary-500"></i>
                    Fiches médicales
                    <span class="ml-2 px-2.5 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 text-sm font-medium">
                        {{ orderedFiches.length }}
                    </span>
                </h3>
                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                    <div class="flex items-center gap-1 sm:hidden">
                        <Button
                            icon="pi pi-chevron-left"
                            severity="secondary"
                            text
                            size="small"
                            :disabled="!orderedFiches.length"
                            @click="prevFiche"
                        />
                        <Button
                            icon="pi pi-chevron-right"
                            severity="secondary"
                            text
                            size="small"
                            :disabled="!orderedFiches.length"
                            @click="nextFiche"
                        />
                    </div>
                    <Button
                        icon="pi pi-external-link"
                        label="Agrandir"
                        severity="secondary"
                        outlined
                        :disabled="!orderedFiches.length"
                        @click="openExpanded"
                        data-tour="patients-dossier.fiches-expand"
                        :pt="{ label: { class: 'hidden sm:inline' } }"
                    />
                    <Button
                        v-if="canCreateConsultation"
                        icon="pi pi-plus"
                        label="Nouvelle consultation"
                        severity="primary"
                        class="bg-gradient-to-r from-primary-500 to-primary-600 border-0"
                        @click="emit('new-consultation')"
                        data-tour="patients-dossier.fiches-new-consultation"
                        :pt="{ label: { class: 'hidden sm:inline' } }"
                    />
                </div>
            </div>
        </div>
        <div class="p-5">
            <div class="relative" data-tour="patients-dossier.fiches-preview">
                <Carousel
                    :value="orderedFiches"
                    :numVisible="1"
                    :numScroll="1"
                    v-model:page="currentFicheIndex"
                    :showIndicators="false"
                    :showNavigators="false"
                    circular
                    class="medical-fiches-carousel"
                >
                    <template #item="slotProps">
                        <div class="medical-fiches-item">
                            <FicheMedicalV2
                                :fiche="slotProps.data"
                                :position-label="formatPosition(slotProps.index)"
                                :patient-age="patientAge"
                                compact
                                @print="emit('print-fiche', slotProps.data)"
                            />
                        </div>
                    </template>
                </Carousel>

                <div class="flex items-center justify-center gap-2 mt-4">
                    <button
                        v-for="(fiche, index) in orderedFiches"
                        :key="fiche.id || index"
                        @click="currentFicheIndex = index"
                        :class="[
                            'w-2 h-2 rounded-full transition-all',
                            currentFicheIndex === index
                                ? 'w-8 bg-primary-500'
                                : 'bg-surface-300 dark:bg-surface-600 hover:bg-surface-400 dark:hover:bg-surface-500'
                        ]"
                    />
                </div>

                <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.fiches-jump">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm text-surface-600 dark:text-surface-400">
                            Fiche {{ currentFicheIndex + 1 }} sur {{ orderedFiches.length }}
                        </div>
                        <Select
                            v-model="currentFicheIndex"
                            :options="ficheOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Aller à une fiche..."
                            class="w-full sm:w-48"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Dialog
        v-model:visible="isExpanded"
        modal
        :closable="false"
        :draggable="false"
        class="w-full max-w-7xl"
        :pt="expandedDialogPt"
    >
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between w-full pr-8">
                <div>
                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                        <i class="pi pi-file-medical text-primary-500"></i>
                        Fiche médicale {{ formatPosition(currentFicheIndex) }}
                        <span
                            v-if="isEditMode"
                            class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                        >
                            Édition
                        </span>
                    </h3>
                    <p v-if="selectedFiche" class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        Créée le {{ formatDateShort(selectedFiche.dateCreation || selectedFiche.createdAt) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        icon="pi pi-chevron-left"
                        severity="secondary"
                        text
                        rounded
                        :disabled="orderedFiches.length <= 1"
                        @click="prevFiche"
                    />
                    <span class="text-sm text-surface-500">{{ currentFicheIndex + 1 }} / {{ orderedFiches.length }}</span>
                    <Button
                        icon="pi pi-chevron-right"
                        severity="secondary"
                        text
                        rounded
                        :disabled="orderedFiches.length <= 1"
                        @click="nextFiche"
                    />
                </div>
            </div>
        </template>

        <div class="p-5">
            <FicheMedicalEditPanel
                v-if="isEditMode && selectedFiche?.id"
                ref="editPanelRef"
                :key="`edit-${selectedFiche.id}`"
                :fiche-id="selectedFiche.id"
                @saved="handleFicheSaved"
                @dirty-change="editHasDirty = $event"
            />
            <FicheMedicalV2
                v-else-if="selectedFiche"
                :key="`view-${selectedFiche.id}`"
                :fiche="selectedFiche"
                :position-label="formatPosition(currentFicheIndex)"
                :patient-age="patientAge"
                compact
                hide-actions
            />
        </div>

        <template #footer>
            <div class="flex flex-wrap items-center justify-between gap-3 w-full">
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        icon="pi pi-print"
                        label="Imprimer"
                        severity="secondary"
                        outlined
                        :disabled="!selectedFiche"
                        @click="handlePrint"
                    />
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
    </Dialog>

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
