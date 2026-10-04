<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';
import FormuleDentaire from './FormuleDentaire.vue';
import { defaultDentitionFromAge, DENTITION_OPTIONS } from '@/utils/formuleDentaireLayout';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    },
    saving: {
        type: Boolean,
        default: false
    },
    patientAge: {
        type: Number,
        default: 0
    },
    layout: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const isBook = computed(() => props.layout === 'book');

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const updateField = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

const updateNested = (section, field, value) => {
    const next = { ...(form.value[section] || {}) };
    next[field] = value;
    form.value = { ...form.value, [section]: next };
};

const dentitionType = ref(defaultDentitionFromAge(props.patientAge));

watch(
    () => props.patientAge,
    (age) => {
        dentitionType.value = defaultDentitionFromAge(age);
    },
    { immediate: true }
);
</script>

<template>
    <div class="medical-form-block" :class="{ 'is-book': isBook }">
        <div class="medical-form-block__header">
            <div>
                <h3 class="medical-form-block__title">Bilans</h3>
                <p v-if="!isBook" class="medical-form-block__subtitle">Formule dentaire et examens</p>
            </div>
            <Button
                label="Sauvegarder"
                icon="pi pi-save"
                :loading="saving"
                @click="emit('save')"
            />
        </div>

        <div class="medical-form-block__body space-y-4">
            <div class="dossier-section-block" style="padding-top:0;margin-top:0;border-top:0">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h4 class="dossier-section-label mb-0">Formule dentaire</h4>
                    <SelectButton v-model="dentitionType" :options="DENTITION_OPTIONS" optionLabel="label" optionValue="value" :allowEmpty="false" class="text-sm" />
                </div>
                <FormuleDentaire :modelValue="form.bilanDentaire?.formuleDentaire" :dentition-type="dentitionType" @update:modelValue="(v) => updateNested('bilanDentaire', 'formuleDentaire', v)" />
            </div>

            <h5>Bilan radiographiques</h5>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Radiographie extra buccale</label>
                    <Textarea :modelValue="form.bilanRadiographique?.radiographieExtraBuccaleHypothese" rows="3" class="w-full" @update:modelValue="(v) => updateNested('bilanRadiographique', 'radiographieExtraBuccaleHypothese', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Radiographie intra buccale</label>
                    <Textarea :modelValue="form.bilanRadiographique?.radiographieIntraBuccaleHypothese" rows="3" class="w-full" @update:modelValue="(v) => updateNested('bilanRadiographique', 'radiographieIntraBuccaleHypothese', v)" />
                </div>
            </div>

            <h5>Bilan sanguin</h5>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">NFS detaillee</label>
                    <InputText :modelValue="form.bilanSanguin?.nfsDetaillee" class="w-full" @update:modelValue="(v) => updateNested('bilanSanguin', 'nfsDetaillee', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">TP / TCA / INR</label>
                    <InputText :modelValue="form.bilanSanguin?.tpTcaInr" class="w-full" @update:modelValue="(v) => updateNested('bilanSanguin', 'tpTcaInr', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Uree</label>
                    <InputText :modelValue="form.bilanSanguin?.uree" class="w-full" @update:modelValue="(v) => updateNested('bilanSanguin', 'uree', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Creatininemie</label>
                    <InputText :modelValue="form.bilanSanguin?.creatininemie" class="w-full" @update:modelValue="(v) => updateNested('bilanSanguin', 'creatininemie', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Glycemie</label>
                    <InputText :modelValue="form.bilanSanguin?.glycemie" class="w-full" @update:modelValue="(v) => updateNested('bilanSanguin', 'glycemie', v)" />
                </div>
            </div>

            <h5 class="">Diagnostic positif</h5>

            <div class="space-y-2 border border-2 border-dashed border-emerald-500 dark:border-emerald-700 rounded-xl p-4">
                <Textarea :modelValue="form.diagnosticPositif" rows="4" class="w-full" @update:modelValue="(v) => updateField('diagnosticPositif', v)" />
            </div>
        </div>
    </div>
</template>
