<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['update:modelValue']);

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const selectedTooth = ref(null);

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

const rows = [
    {
        left: [55, 54, 53, 52, 51],
        right: [61, 62, 63, 64, 65]
    },
    {
        left: [18, 17, 16, 15, 14, 13, 12, 11],
        right: [21, 22, 23, 24, 25, 26, 27, 28]
    },
    {
        left: [48, 47, 46, 45, 44, 43, 42, 41],
        right: [31, 32, 33, 34, 35, 36, 37, 38]
    },
    {
        left: [85, 84, 83, 82, 81],
        right: [71, 72, 73, 74, 75]
    }
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

const selectTooth = (tooth) => {
    selectedTooth.value = tooth;
    ensureTooth(tooth);
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

const toothSummary = (tooth) => {
    const entry = form.value?.[tooth];
    if (!entry?.etat || entry.etat.length === 0) {
        return '';
    }
    return entry.etat.join('-');
};

const hasData = (entry) => {
    if (!entry) {
        return false;
    }
    if (entry.etat && entry.etat.length) {
        return true;
    }
    if (entry.estCausale) {
        return true;
    }
    if (entry.diagnosticSuppose) {
        return true;
    }
    if (entry.examensComplementaires && entry.examensComplementaires.length) {
        return true;
    }
    return Object.values(entry.siCausale || {}).some((value) => value);
};

const toothClasses = (tooth) => {
    const entry = form.value?.[tooth];
    const active = selectedTooth.value === tooth;
    if (entry?.estCausale) {
        return [
            'bg-red-100 text-red-700 border-red-200',
            active ? 'ring-2 ring-red-400 ring-offset-2' : ''
        ];
    }
    if (hasData(entry)) {
        return [
            'bg-emerald-100 text-emerald-700 border-emerald-200',
            active ? 'ring-2 ring-emerald-400 ring-offset-2' : ''
        ];
    }
    return [
        'bg-white text-surface-600 border-surface-200',
        active ? 'ring-2 ring-primary-400 ring-offset-2' : ''
    ];
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
    <div class="grid grid-cols-1 xl:grid-cols-[1.3fr_0.8fr] gap-6 w-full min-w-0">
        <div
            class="rounded-2xl border border-surface-200/70 dark:border-surface-700 bg-gradient-to-br from-surface-0 to-surface-50 dark:from-surface-900 dark:to-surface-900 p-4 sm:p-5 shadow-sm w-full min-w-0 overflow-hidden"
        >
            <div class="sm:hidden space-y-4">
                <div v-for="(row, rowIndex) in rows" :key="'stack-' + rowIndex" class="space-y-2">
                    <div class="flex flex-wrap justify-center gap-2">
                        <button
                            v-for="tooth in row.left"
                            :key="'stack-left-' + tooth"
                            type="button"
                            class="h-10 w-10 rounded-xl border text-[10px] font-semibold tracking-tight transition-all duration-200"
                            :class="toothClasses(tooth)"
                            @click="selectTooth(tooth)"
                        >
                            <div class="text-[9px] leading-tight">Dent</div>
                            <div class="text-xs font-bold">{{ tooth }}</div>
                            <div class="text-[9px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                        </button>
                    </div>
                    <div class="flex flex-wrap justify-center gap-2">
                        <button
                            v-for="tooth in row.right"
                            :key="'stack-right-' + tooth"
                            type="button"
                            class="h-10 w-10 rounded-xl border text-[10px] font-semibold tracking-tight transition-all duration-200"
                            :class="toothClasses(tooth)"
                            @click="selectTooth(tooth)"
                        >
                            <div class="text-[9px] leading-tight">Dent</div>
                            <div class="text-xs font-bold">{{ tooth }}</div>
                            <div class="text-[9px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                        </button>
                    </div>
                </div>
            </div>

            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full sm:min-w-[520px] border-collapse mx-auto">
                    <tbody>
                        <tr v-for="(row, rowIndex) in rows" :key="rowIndex">
                            <!-- Offset for top/bottom rows to center them -->
                            <template v-if="rowIndex === 0 || rowIndex === rows.length - 1">
                                <td class="w-6 sm:w-8"></td>
                                <td class="w-6 sm:w-8"></td>
                            </template>
                            <!-- Left teeth -->
                            <td v-for="tooth in row.left" :key="'left-' + tooth" class="p-1">
                                <button
                                    type="button"
                                    class="h-12 w-12 sm:h-14 sm:w-14 rounded-xl border text-[11px] sm:text-xs font-semibold tracking-tight transition-all duration-200 hover:-translate-y-0.5"
                                    :class="toothClasses(tooth)"
                                    @click="selectTooth(tooth)"
                                >
                                    <div class="text-[10px] leading-tight">Dent</div>
                                    <div class="text-sm font-bold">{{ tooth }}</div>
                                    <div class="text-[10px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                                </button>
                            </td>
                            <!-- Spacer between left and right -->
                            <!-- Add 3 empty cells for the first and last row -->
                            <template v-if="rowIndex === 0 || rowIndex === rows.length - 1">
                                <td class="w-6 sm:w-8"></td>
                                <td class="w-6 sm:w-8"></td>
                                <td class="w-6 sm:w-8"></td>
                            </template>
                            <template v-else>
                                <td class="w-6 sm:w-8"></td>
                            </template>
                            <!-- Right teeth -->
                            <td v-for="tooth in row.right" :key="'right-' + tooth" class="p-1">
                                <button
                                    type="button"
                                    class="h-12 w-12 sm:h-14 sm:w-14 rounded-xl border text-[11px] sm:text-xs font-semibold tracking-tight transition-all duration-200 hover:-translate-y-0.5"
                                    :class="toothClasses(tooth)"
                                    @click="selectTooth(tooth)"
                                >
                                    <div class="text-[10px] leading-tight">Dent</div>
                                    <div class="text-sm font-bold">{{ tooth }}</div>
                                    <div class="text-[10px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                                </button>
                            </td>
                            <!-- Offset for top/bottom rows to center them -->
                            <template v-if="rowIndex === 0 || rowIndex === rows.length - 1">
                                <td class="w-6 sm:w-8"></td>
                                <td class="w-6 sm:w-8"></td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-surface-200/70 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-4 sm:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h5 class="text-base sm:text-lg font-semibold text-surface-900 dark:text-surface-100">
                        Apercu de la dent
                    </h5>
                    <p class="text-xs sm:text-sm text-surface-500 dark:text-surface-400">
                        Selectionnez une dent dans la grille.
                    </p>
                </div>
                <div
                    class="text-xs sm:text-sm font-semibold text-primary-600 bg-primary-50 dark:bg-primary-950/30 px-3 py-1 rounded-full w-fit"
                    v-if="selectedTooth"
                >
                    Dent {{ selectedTooth }}
                </div>
            </div>

            <div v-if="!selectedTooth" class="mt-6 text-sm text-surface-500 dark:text-surface-400">
                Cliquez sur une dent pour renseigner ses details.
            </div>

            <div v-else class="mt-5 space-y-5">
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

                <div v-if="selectedEntry?.estCausale" class="space-y-4 rounded-xl border border-red-200 bg-red-50/80 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700">Aspect</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.aspect"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('aspect', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700">Siege</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.siege"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('siege', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700">Profondeur</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.profondeur"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('profondeur', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700">Mobilite</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.mobilite"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('mobilite', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700">Sonde</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.sonde"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('sonde', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700">Tests de Vitalite (Froid, Chaud)</label>
                            <InputText
                                :modelValue="selectedEntry?.siCausale?.testsVitalite"
                                class="w-full"
                                @update:modelValue="(v) => updateSiCausale('testsVitalite', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-700">Percussions</label>
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
        </div>
    </div>
</template>
