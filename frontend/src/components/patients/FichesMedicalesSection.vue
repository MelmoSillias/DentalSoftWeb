<script setup>
import Button from 'primevue/button';
import Carousel from 'primevue/carousel';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import { computed, ref } from 'vue';
import FicheMedicalV2 from '@/components/patients/FicheMedicalV2.vue';

const props = defineProps({
    fiches: {
        type: Array,
        default: () => []
    },
    canCreateConsultation: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['print-fiche', 'new-consultation']);

const currentFicheIndex = ref(0);
const isExpanded = ref(false);

const orderedFiches = computed(() => props.fiches || []);

const selectedFiche = computed(() => orderedFiches.value[currentFicheIndex.value] || null);

const ficheOptions = computed(() =>
    orderedFiches.value.map((fiche, index) => ({
        label: `${formatPosition(index)} - ${formatDateShort(fiche.dateCreation || fiche.createdAt || fiche.date)}`,
        value: index
    }))
);

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
    if (position === 1) return '1ere';
    if (position === 2) return '2eme';
    if (position === 3) return '3eme';
    return `${position}eme`;
}

function openExpanded() {
    if (!selectedFiche.value) return;
    isExpanded.value = true;
}
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800" data-tour="patients-dossier.fiches-toolbar">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                    <i class="pi pi-file-medical text-primary-500"></i>
                    Fiches Médicales
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
                            class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400 p-1"
                        />
                        <Button
                            icon="pi pi-chevron-right"
                            severity="secondary"
                            text
                            size="small"
                            :disabled="!orderedFiches.length"
                            @click="nextFiche"
                            class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400 p-1"
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
                        <div class="flex items-center gap-2 w-full sm:w-auto">
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
    </div>

    <Dialog v-model:visible="isExpanded" modal header="Fiche médicale" class="w-full max-w-7xl">
        <FicheMedicalV2
            v-if="selectedFiche"
            :fiche="selectedFiche"
            :position-label="formatPosition(currentFicheIndex)"
            @print="emit('print-fiche', selectedFiche)"
        />
    </Dialog>
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
