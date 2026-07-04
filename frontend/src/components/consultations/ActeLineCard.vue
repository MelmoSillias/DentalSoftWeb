<script setup>
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import { computed } from 'vue';
import {
    formatActeCurrency,
    normalizeDentList,
    normalizeSoinList,
    teethOptions
} from '@/services/consultations';

const props = defineProps({
    acte: {
        type: Object,
        required: true
    },
    index: {
        type: Number,
        default: 0
    },
    soins: {
        type: Array,
        default: () => []
    },
    formuleDentaire: {
        type: Object,
        default: () => ({})
    },
    showHeader: {
        type: Boolean,
        default: true
    },
    subtotal: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['update', 'remove']);

const soinOptions = computed(() => normalizeSoinList(props.soins).map((item) => ({ label: item, value: item })));

const dentSelection = computed(() => normalizeDentList(props.acte?.dent));

const acteTotal = computed(() => (Number(props.acte?.quantite) || 0) * (Number(props.acte?.prix) || 0));

const displayedSubtotal = computed(() => (props.subtotal ?? acteTotal.value));

const getToothEntry = (tooth) => props.formuleDentaire?.[tooth] || null;

const toothSummary = (tooth) => {
    const entry = getToothEntry(tooth);
    if (!entry?.etat || entry.etat.length === 0) {
        return '';
    }
    return entry.etat.join('-');
};

const hasToothData = (entry) => {
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

const toothStateClass = (tooth) => {
    const entry = getToothEntry(tooth);
    if (entry?.estCausale) {
        return 'text-red-700 border-red-200 dark:text-red-400 dark:border-red-700';
    }
    if (hasToothData(entry)) {
        return 'text-emerald-700 border-emerald-200 dark:text-emerald-400 dark:border-emerald-700';
    }
    return 'text-surface-600 border-surface-200 dark:text-surface-300 dark:border-surface-700';
};

const updateField = (patch) => emit('update', patch);
</script>

<template>
    <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-4 shadow-sm hover:shadow-md transition-all">
        <div v-if="showHeader" class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 text-sm font-bold">
                    {{ index + 1 }}
                </span>
                <span class="font-medium text-surface-900 dark:text-surface-100">Acte {{ index + 1 }}</span>
            </div>
            <Button
                icon="pi pi-trash"
                severity="danger"
                text
                rounded
                v-tooltip="'Supprimer cet acte'"
                class="hover:bg-red-50 dark:hover:bg-red-900/20"
                @click="emit('remove')"
            />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-4 h-full">
                <FloatLabel variant="in" class="h-full">
                    <MultiSelect
                        :options="teethOptions"
                        :modelValue="dentSelection"
                        optionLabel="label"
                        optionValue="value"
                        :filter="true"
                        showClear
                        class="w-full h-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 text-sm"
                        @update:modelValue="(value) => updateField({ dent: value })"
                    >
                        <template #value="slotProps">
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="val in (slotProps.value || [])"
                                    :key="val"
                                    class="flex items-center gap-1 px-2 py-1 rounded-full text-xs border h-full"
                                    :class="toothStateClass(val)"
                                >
                                    🦷 {{ val }}
                                </div>
                            </div>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center gap-3 p-2">
                                <div class="relative">
                                    <i class="fa fa-tooth text-lg" :class="toothStateClass(slotProps.option.value)"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-semibold">Dent {{ slotProps.option.label }}</span>
                                    <span class="text-xs text-gray-500">
                                        {{ toothSummary(slotProps.option.value) || 'Aucun acte' }}
                                    </span>
                                </div>
                            </div>
                        </template>
                    </MultiSelect>
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Dent</label>
                </FloatLabel>
            </div>
            <div class="w-full lg:col-span-4">
                <FloatLabel variant="in">
                    <Select
                        :modelValue="acte.type"
                        :options="soinOptions"
                        optionLabel="label"
                        optionValue="value"
                        :filter="true"
                        filterPlaceholder="Rechercher..."
                        showClear
                        class="w-full [&_.p-select]:rounded-lg [&_.p-select]:border-surface-200 [&_.p-select]:dark:border-surface-700 [&_.p-select]:bg-surface-50 [&_.p-select]:dark:bg-surface-800 [&_.p-select]:p-2 [&_.p-select]:text-sm"
                        @update:modelValue="(value) => updateField({ type: value || '' })"
                    />
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Type d'acte</label>
                </FloatLabel>
            </div>
            <div class="w-full">
                <FloatLabel variant="in">
                    <InputNumber
                        :modelValue="acte.quantite"
                        :min="1"
                        mode="decimal"
                        :useGrouping="false"
                        inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 p-2 text-sm"
                        @update:modelValue="(value) => updateField({ quantite: value ?? 1 })"
                    />
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Qté</label>
                </FloatLabel>
            </div>
            <div class="w-full lg:col-span-3">
                <FloatLabel variant="in">
                    <InputNumber
                        :modelValue="acte.prix"
                        mode="decimal"
                        :minFractionDigits="0"
                        :maxFractionDigits="2"
                        inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 p-2 text-sm"
                        @update:modelValue="(value) => updateField({ prix: value ?? 0 })"
                    />
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Prix</label>
                </FloatLabel>
            </div>
            <div class="w-full lg:col-span-12">
                <FloatLabel variant="in">
                    <InputText
                        :modelValue="acte.description"
                        class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 p-2 text-sm"
                        @update:modelValue="(value) => updateField({ description: value })"
                    />
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Description</label>
                </FloatLabel>
            </div>
        </div>

        <div class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700">
            <div class="flex items-center justify-end gap-2">
                <span class="text-sm text-surface-600 dark:text-surface-400">Sous-total :</span>
                <span class="font-bold text-primary-600 dark:text-primary-400">
                    {{ formatActeCurrency(displayedSubtotal) }}
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-inputnumber) {
    width: 100%;
}
</style>
