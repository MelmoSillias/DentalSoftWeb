<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { computed, h } from 'vue';
import FormuleDentaire from './FormuleDentaire.vue';

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
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

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

</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-clipboard text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Bilans</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Formule dentaire et examens</p>
                </div>
            </div>
            <Button
                label="Sauvegarder"
                icon="pi pi-save"
                :loading="saving"
                @click="emit('save')"
                class="rounded-xl px-5 py-3 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
            />
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5">
                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-4">Formule dentaire</h4>
                <FormuleDentaire
                    :modelValue="form.bilanDentaire?.formuleDentaire"
                    @update:modelValue="(v) => updateNested('bilanDentaire', 'formuleDentaire', v)"
                />
            </div>

            <h5>Bilan radiographiques</h5>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Radiographie extra buccale</label>
                    <Textarea
                        :modelValue="form.bilanRadiographique?.radiographieExtraBuccaleHypothese"
                        rows="3"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('bilanRadiographique', 'radiographieExtraBuccaleHypothese', v)"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Radiographie intra buccale</label>
                    <Textarea
                        :modelValue="form.bilanRadiographique?.radiographieIntraBuccaleHypothese"
                        rows="3"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('bilanRadiographique', 'radiographieIntraBuccaleHypothese', v)"
                    />
                </div>
            </div>

           <h5>Bilan sanguin</h5>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">NFS detaillee</label>
                    <InputText
                        :modelValue="form.bilanSanguin?.nfsDetaillee"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('bilanSanguin', 'nfsDetaillee', v)"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">TP / TCA / INR</label>
                    <InputText
                        :modelValue="form.bilanSanguin?.tpTcaInr"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('bilanSanguin', 'tpTcaInr', v)"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Uree</label>
                    <InputText
                        :modelValue="form.bilanSanguin?.uree"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('bilanSanguin', 'uree', v)"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Creatininemie</label>
                    <InputText
                        :modelValue="form.bilanSanguin?.creatininemie"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('bilanSanguin', 'creatininemie', v)"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Glycemie</label>
                    <InputText
                        :modelValue="form.bilanSanguin?.glycemie"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('bilanSanguin', 'glycemie', v)"
                    />
                </div>
            </div>

            <h5 class="">Diagnostic positif</h5>

            <div class="space-y-2 border border-2 border-dashed border-emerald-500 dark:border-emerald-700 rounded-xl p-4"> 
                <Textarea
                    :modelValue="form.diagnosticPositif"
                    rows="4"
                    class="w-full"
                    @update:modelValue="(v) => updateField('diagnosticPositif', v)"
                />
            </div>

            
        </div>


    </div>
</template>
