<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed, ref } from 'vue';
import FormuleDentaireGrid from '@/components/fiche-medicale/FormuleDentaireGrid.vue';
import ReadonlyFieldGrid from '@/components/fiche-medicale/ReadonlyFieldGrid.vue';
import { getMatrixForDentition, hasToothData } from '@/utils/formuleDentaireLayout';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    },
    dentitionType: {
        type: String,
        default: 'adulte'
    }
});

const form = computed(() => props.modelValue || {});
const selectedTooth = ref(null);
const detailVisible = ref(false);

const matrix = computed(() => getMatrixForDentition(props.dentitionType));

const selectedEntry = computed(() => {
    if (!selectedTooth.value) return null;
    return form.value?.[selectedTooth.value] || null;
});

const selectedHasData = computed(() => hasToothData(selectedEntry.value));

const siCausaleFields = computed(() => {
    const si = selectedEntry.value?.siCausale || {};
    return [
        { label: 'Aspect', value: si.aspect },
        { label: 'Siège', value: si.siege },
        { label: 'Profondeur', value: si.profondeur },
        { label: 'Mobilité', value: si.mobilite },
        { label: 'Sonde', value: si.sonde },
        { label: 'Tests de vitalité (froid, chaud)', value: si.testsVitalite },
        { label: 'Percussions', value: si.percussions }
    ];
});

const openToothDetail = (tooth) => {
    selectedTooth.value = tooth;
    detailVisible.value = true;
};

const closeToothDetail = () => {
    detailVisible.value = false;
    selectedTooth.value = null;
};

const formatEtat = (etat) => {
    if (!etat || (Array.isArray(etat) && !etat.length)) return '—';
    return Array.isArray(etat) ? etat.join(', ') : String(etat);
};

const formatField = (value) => {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'string' && !value.trim()) return '—';
    return String(value);
};

const examensComplementairesView = computed(() => {
    const list = Array.isArray(selectedEntry.value?.examensComplementaires) ? selectedEntry.value.examensComplementaires : [];
    return list.length ? list : [{ titre: null, description: null, isPlaceholder: true }];
});
</script>

<template>
    <div class="w-full min-w-0">
        <FormuleDentaireGrid :matrix="matrix" :form="form" mode="readonly" @tooth-click="openToothDetail" />

        <p class="text-xs text-surface-500 dark:text-surface-400 mt-3 text-center">Cliquez sur une dent pour afficher les détails</p>

        <Dialog
            v-model:visible="detailVisible"
            modal
            :closable="false"
            :draggable="false"
            class="w-full max-w-lg formule-dentaire-detail-dialog"
            :pt="{
                root: 'rounded-2xl overflow-hidden border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 shadow-xl',
                header: 'border-b border-surface-200/50 dark:border-surface-700/50 bg-surface-50 dark:bg-surface-900 px-5 py-4',
                content: 'px-5 py-4 bg-surface-0 dark:bg-surface-900 text-surface-900 dark:text-surface-100',
                footer: 'border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50 dark:bg-surface-900 px-5 py-4'
            }"
            @hide="selectedTooth = null"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-primary-500/10 dark:bg-primary-500/25">
                        <i class="pi pi-th-large text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-50 m-0">Dent {{ selectedTooth }}</h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 m-0 mt-0.5">Détails de la formule dentaire</p>
                    </div>
                </div>
            </template>

            <div v-if="selectedTooth && !selectedHasData" class="flex flex-col items-center justify-center text-center py-10 px-4 rounded-xl border border-dashed border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/50">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800 text-surface-400 dark:text-surface-500">
                    <i class="pi pi-th-large text-2xl"></i>
                </div>
                <p class="text-base font-medium text-surface-700 dark:text-surface-200">Dent non examinée</p>
                <p class="mt-2 max-w-xs text-sm text-surface-500 dark:text-surface-400">Aucune information n'a été saisie pour cette dent dans la formule dentaire.</p>
            </div>

            <div v-else-if="selectedTooth" class="space-y-5 text-sm max-h-[60vh] overflow-y-auto pr-1">
                <div class="space-y-2">
                    <h5 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">État</h5>
                    <p class="text-surface-800 dark:text-surface-200">{{ formatEtat(selectedEntry?.etat) }}</p>
                </div>

                <div class="space-y-2">
                    <h5 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">Dent causale</h5>
                    <span v-if="selectedEntry?.estCausale" class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-1 text-xs font-semibold text-red-700 dark:text-red-300">
                        <i class="pi pi-exclamation-circle text-[10px]"></i>
                        Oui
                    </span>
                    <span v-else class="text-surface-600 dark:text-surface-400">Non</span>
                </div>

                <div class="space-y-2">
                    <h5 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">Si causale</h5>
                    <ReadonlyFieldGrid :fields="siCausaleFields" :columns="1" />
                </div>

                <div class="space-y-2">
                    <h5 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">Diagnostic supposé</h5>
                    <p class="text-surface-800 dark:text-surface-200 whitespace-pre-wrap">
                        {{ formatField(selectedEntry?.diagnosticSuppose) }}
                    </p>
                </div>

                <div class="space-y-2">
                    <h5 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">Examens complémentaires</h5>
                    <div class="space-y-2">
                        <div v-for="(examen, idx) in examensComplementairesView" :key="idx" class="p-3 rounded-lg bg-surface-50 dark:bg-surface-800 border border-surface-200 dark:border-surface-700">
                            <template v-if="examen.isPlaceholder">
                                <p class="text-surface-600 dark:text-surface-400">—</p>
                            </template>
                            <template v-else>
                                <div class="font-medium text-surface-800 dark:text-surface-200">
                                    {{ formatField(examen.titre) }}
                                </div>
                                <p class="text-surface-600 dark:text-surface-400 mt-1 whitespace-pre-wrap">
                                    {{ formatField(examen.description ?? examen.raison) }}
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end w-full">
                    <Button label="Fermer" icon="pi pi-times" severity="secondary" class="dark:border-surface-600 dark:text-surface-200" @click="closeToothDetail" />
                </div>
            </template>
        </Dialog>
    </div>
</template>
