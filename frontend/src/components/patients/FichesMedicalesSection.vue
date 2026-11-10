<script setup>
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, ref, useId, watch } from 'vue';
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
const ficheSelectId = useId();
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

function selectFiche(index) {
    if (index < 0 || index >= orderedFiches.value.length || index === currentFicheIndex.value) return;
    const change = () => {
        isEditMode.value = false;
        editHasDirty.value = false;
        currentFicheIndex.value = index;
    };
    if (isEditMode.value && editHasDirty.value) {
        confirm.require({
            message: 'Des modifications non enregistrées seront perdues. Changer de fiche ?',
            header: 'Modifications en cours',
            icon: 'pi pi-exclamation-triangle',
            rejectLabel: 'Annuler',
            acceptLabel: 'Changer de fiche',
            accept: change
        });
    } else change();
}

function prevFiche() {
    selectFiche(currentFicheIndex.value - 1);
}
function nextFiche() {
    selectFiche(currentFicheIndex.value + 1);
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
    editHasDirty.value = editPanelRef.value?.hasDirtyChanges() ?? false;
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
        } else {
            currentFicheIndex.value = Math.max(0, Math.min(currentFicheIndex.value, Number(length || 0) - 1));
        }
    }
);

watch(
    () => props.patientId,
    () => {
        currentFicheIndex.value = 0;
        isExpanded.value = false;
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
                <template v-if="selectedFiche">
                    <div class="medical-fiches-navigation" data-tour="patients-dossier.fiches-jump">
                        <div class="medical-fiches-navigation__selection">
                            <label :for="ficheSelectId" class="font-semibold">Fiche à consulter</label>
                            <Select :inputId="ficheSelectId" aria-label="Fiche à consulter" :modelValue="currentFicheIndex" :options="ficheOptions" optionLabel="label" optionValue="value" @update:modelValue="selectFiche" />
                        </div>
                        <div class="flex items-center gap-2">
                            <Button icon="pi pi-chevron-left" severity="secondary" outlined aria-label="Fiche précédente" :disabled="currentFicheIndex === 0" @click="prevFiche" />
                            <span class="text-sm" aria-live="polite">{{ currentFicheIndex + 1 }} / {{ orderedFiches.length }}</span>
                            <Button icon="pi pi-chevron-right" severity="secondary" outlined aria-label="Fiche suivante" :disabled="currentFicheIndex >= orderedFiches.length - 1" @click="nextFiche" />
                        </div>
                    </div>
                    <div data-tour="patients-dossier.fiches-preview">
                        <FicheMedicalV2 :key="selectedFiche.id || currentFicheIndex" :fiche="selectedFiche" :position-label="formatPosition(currentFicheIndex)" :patient-age="patientAge" compact @print="handlePrint" />
                    </div>
                </template>
                <div v-else class="medical-fiches-empty" role="status">
                    <i class="pi pi-folder-open text-3xl" aria-hidden="true"></i>
                    <h4 class="font-semibold text-lg">Aucune fiche médicale</h4>
                    <p>Les fiches médicales de ce patient apparaîtront ici.</p>
                    <Button v-if="canEdit" label="Créer la première fiche" icon="pi pi-plus" :loading="creatingFiche" :disabled="!patientId" @click="askCreateNewFiche" />
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
                    <span v-if="isEditMode" class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"> Édition </span>
                    <Button icon="pi pi-chevron-left" severity="secondary" text rounded aria-label="Fiche précédente" :disabled="currentFicheIndex === 0" @click="prevFiche" />
                    <span class="text-sm text-surface-500">{{ currentFicheIndex + 1 }} / {{ orderedFiches.length }}</span>
                    <Button icon="pi pi-chevron-right" severity="secondary" text rounded aria-label="Fiche suivante" :disabled="currentFicheIndex >= orderedFiches.length - 1" @click="nextFiche" />
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
.medical-fiches-navigation {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--surface-ground);
    border-radius: 0.75rem;
}
.medical-fiches-navigation__selection {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}
.medical-fiches-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 3rem 1rem;
    text-align: center;
    color: var(--text-color-secondary);
}
@media (max-width: 639px) {
    .medical-fiches-navigation__selection {
        width: 100%;
    }
    .medical-fiches-navigation__selection :deep(.p-select) {
        width: 100%;
    }
}
</style>
