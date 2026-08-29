<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';
import FormuleDentaireGrid from '@/components/fiche-medicale/FormuleDentaireGrid.vue';
import { getMatrixForDentition } from '@/utils/formuleDentaireLayout';

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

const emit = defineEmits(['update:modelValue']);

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const selectedTooth = ref(null);
const detailVisible = ref(false);

const matrix = computed(() => getMatrixForDentition(props.dentitionType));

const etatOptions = [
    { label: 'Bonne', value: 'BONNE', isDefault: true },
    { label: 'C = Carie', value: 'C' },
    { label: 'O = Obturee', value: 'O' },
    { label: 'MP = Malposition', value: 'MP' },
    { label: 'E = Enclavee', value: 'E' },
    { label: 'A = Absente', value: 'A' },
    { label: 'M = Mobile', value: 'M' },
    { label: 'I = Incluse', value: 'I' },
    { label: 'P = Prothese', value: 'P' }
];

const defaultEntry = () => ({
    etat: [],
    estCausale: false,
    siCausale: {
        aspect: '',
        siege: '',
        profondeur: '',
        mobilite: '',
        sonde: '',
        testsVitalite: '',
        percussions: ''
    },
    diagnosticSuppose: '',
    examensComplementaires: []
});

const ensureTooth = (tooth) => {
    const current = form.value?.[tooth] || {};
    const next = {
        ...defaultEntry(),
        ...current,
        siCausale: {
            ...defaultEntry().siCausale,
            ...(current.siCausale || {})
        },
        examensComplementaires: Array.isArray(current.examensComplementaires)
            ? current.examensComplementaires
            : []
    };

    if (!form.value?.[tooth]) {
        form.value = { ...form.value, [tooth]: next };
    }
    return next;
};

const openToothDetail = (tooth) => {
    selectedTooth.value = tooth;
    ensureTooth(tooth);
    detailVisible.value = true;
};

const closeToothDetail = () => {
    detailVisible.value = false;
    selectedTooth.value = null;
};

const updateTooth = (tooth, patch) => {
    const current = ensureTooth(tooth);
    const next = {
        ...current,
        ...patch,
        siCausale: {
            ...current.siCausale,
            ...(patch.siCausale || {})
        }
    };
    form.value = { ...form.value, [tooth]: next };
};

const normalizeEtat = (value, previous = []) => {
    const next = Array.isArray(value) ? value : [];
    const prev = Array.isArray(previous) ? previous : [];
    const added = next.filter((item) => !prev.includes(item));

    if (added.includes('BONNE')) {
        return ['BONNE'];
    }
    if (next.includes('BONNE') && next.length > 1) {
        return next.filter((item) => item !== 'BONNE');
    }
    return next;
};

const updateEtat = (value) => {
    if (!selectedTooth.value) {
        return;
    }
    const previous = selectedEntry.value?.etat || [];
    const normalized = normalizeEtat(value, previous);
    const estCausale = normalized.length > 0 && !normalized.includes('BONNE');
    updateTooth(selectedTooth.value, { etat: normalized, estCausale });
};

const resetEtat = () => {
    if (!selectedTooth.value) {
        return;
    }
    updateTooth(selectedTooth.value, { etat: [], estCausale: false });
};

const updateSiCausale = (field, value) => {
    if (!selectedTooth.value) {
        return;
    }
    const current = ensureTooth(selectedTooth.value);
    updateTooth(selectedTooth.value, {
        siCausale: {
            ...current.siCausale,
            [field]: value
        }
    });
};

const updateExamen = (index, field, value) => {
    if (!selectedTooth.value) {
        return;
    }
    const current = ensureTooth(selectedTooth.value);
    const next = current.examensComplementaires.map((item, idx) =>
        idx === index ? { ...item, [field]: value } : item
    );
    updateTooth(selectedTooth.value, { examensComplementaires: next });
};

const addExamen = () => {
    if (!selectedTooth.value) {
        return;
    }
    const current = ensureTooth(selectedTooth.value);
    updateTooth(selectedTooth.value, {
        examensComplementaires: [...current.examensComplementaires, { titre: '', raison: '' }]
    });
};

const removeExamen = (index) => {
    if (!selectedTooth.value) {
        return;
    }
    const current = ensureTooth(selectedTooth.value);
    updateTooth(selectedTooth.value, {
        examensComplementaires: current.examensComplementaires.filter((_, idx) => idx !== index)
    });
};

const selectedEntry = computed(() => {
    if (!selectedTooth.value) {
        return null;
    }
    return ensureTooth(selectedTooth.value);
});

const clearEtatLabel = computed(() => {
    const etat = selectedEntry.value?.etat || [];
    if (etat.length === 0) {
        return 'Etat non defini';
    }
    if (etat.length === 1 && etat[0] === 'BONNE') {
        return 'Etat: Bonne';
    }
    return 'Revenir a non defini';
});
</script>

<template>
    <div class="w-full min-w-0">
        <div
            class="rounded-2xl border border-surface-200/70 dark:border-surface-700 bg-gradient-to-br from-surface-0 to-surface-50 dark:from-surface-900 dark:to-surface-900 p-4 sm:p-5 shadow-sm w-full min-w-0 overflow-hidden"
        >
            <FormuleDentaireGrid
                :matrix="matrix"
                :form="form"
                :selected-tooth="selectedTooth"
                mode="edit"
                @tooth-click="openToothDetail"
            />
        </div>

        <p class="text-xs text-surface-500 dark:text-surface-400 mt-3 text-center">
            Cliquez sur une dent pour renseigner ses détails
        </p>

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
                        <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-50 m-0">
                            Dent {{ selectedTooth }}
                        </h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 m-0 mt-0.5">
                            Détails de la formule dentaire
                        </p>
                    </div>
                </div>
            </template>

            <div v-if="selectedTooth" class="space-y-5 max-h-[60vh] overflow-y-auto pr-1">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Etat</label>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-surface-500">
                            {{ clearEtatLabel }}
                        </div>
                        <Button
                            label="Tout deselectionner"
                            icon="pi pi-times"
                            text
                            size="small"
                            :disabled="(selectedEntry?.etat || []).length === 0"
                            @click="resetEtat"
                        />
                    </div>
                    <MultiSelect
                        :modelValue="selectedEntry?.etat"
                        :options="etatOptions"
                        optionLabel="label"
                        optionValue="value"
                        display="chip"
                        class="w-full"
                        placeholder="Selectionner"
                        @update:modelValue="updateEtat"
                    />
                </div>

                <div v-if="selectedEntry?.estCausale" class="space-y-4 rounded-xl border border-red-200 bg-red-50/80 dark:bg-red-950/20 dark:border-red-800 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700 dark:text-surface-300">Aspect</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.aspect"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('aspect', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700 dark:text-surface-300">Siege</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.siege"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('siege', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700 dark:text-surface-300">Profondeur</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.profondeur"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('profondeur', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700 dark:text-surface-300">Mobilite</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.mobilite"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('mobilite', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700 dark:text-surface-300">Sonde</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.sonde"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('sonde', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700 dark:text-surface-300">Tests de Vitalite (Froid, Chaud)</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.testsVitalite"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('testsVitalite', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700 dark:text-surface-300">Percussions</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.percussions"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('percussions', v)"
                            />
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Diagnostic suppose</label>
                    <InputText
                        :modelValue="selectedEntry?.diagnosticSuppose"
                        class="w-full"
                        @update:modelValue="(v) => updateTooth(selectedTooth, { diagnosticSuppose: v })"
                    />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Examen complementaires</label>
                    <div class="space-y-3">
                        <div
                            v-for="(item, index) in selectedEntry?.examensComplementaires"
                            :key="index"
                            class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-3 items-center"
                        >
                            <InputText
                                :modelValue="item.titre"
                                placeholder="Titre"
                                class="w-full"
                                @update:modelValue="(v) => updateExamen(index, 'titre', v)"
                            />
                            <Textarea
                                :modelValue="item.raison"
                                rows="2"
                                placeholder="Raison"
                                class="w-full"
                                @update:modelValue="(v) => updateExamen(index, 'raison', v)"
                            />
                            <Button
                                icon="pi pi-trash"
                                severity="danger"
                                text
                                @click="removeExamen(index)"
                            />
                        </div>
                        <Button
                            label="Ajouter un examen"
                            icon="pi pi-plus"
                            class="w-full"
                            outlined
                            @click="addExamen"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end w-full">
                    <Button
                        label="Fermer"
                        icon="pi pi-times"
                        severity="secondary"
                        class="dark:border-surface-600 dark:text-surface-200"
                        @click="closeToothDetail"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>
