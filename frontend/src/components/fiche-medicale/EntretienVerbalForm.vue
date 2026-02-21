<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    },
    saving: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const yesNoOptions = [
    { label: 'Oui', value: true },
    { label: 'Non', value: false }
];

const medicamentList = [
    'Hypotenseurs',
    'Anti-Inflammatoire',
    'Insuline',
    'Contraceptifs',
    'Anticoagulant',
    'Hypoglycemiants',
    'Antalgiques',
    'Cardiorhythmiques',
    'Antihistaminiques',
    'Salicyles',
    'Antidepresseurs',
    'Neuroleptiques',
    'Psychotropes'
];

const affectionList = [
    'Diabete',
    'Allergie',
    'Gastrite',
    'Epilepsie',
    'Chaines ganglionnaires',
    'Hypertension',
    'Tuberculose',
    'Ulcere',
    'Affection hepatique',
    'Sous mandibulaires',
    'Hyperthyroidie',
    'Asthme',
    'Hemophilie',
    'Drepanocytose',
    'Examen buccal',
    'Hypothyroidie',
    'Insuffisance renale',
    'Anemie',
    'Cardiopathies'
];

const questionsList = [
    'Avez-vous deja ete hospitalise',
    'Avez-vous ete opere sous anesthesie generale',
    'Supportez-vous les anesthesies locales',
    'Avez-vous deja subi une evaluation dentaire',
    'Etes-vous sujet aux hemorragies'
];

const habitudesList = ['Tabac', 'Alcool'];

const updateField = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

const updateArrayItem = (key, nom, patch) => {
    const list = Array.isArray(form.value[key]) ? [...form.value[key]] : [];
    const index = list.findIndex((item) => item.nom === nom || item.question === nom || item.type === nom);
    if (index === -1) {
        const base = { nom };
        list.push({ ...base, ...patch });
    } else {
        list[index] = { ...list[index], ...patch, nom: list[index].nom ?? nom, question: list[index].question ?? nom, type: list[index].type ?? nom };
    }
    form.value = { ...form.value, [key]: list };
};

const getArrayItem = (key, nom) => {
    const list = Array.isArray(form.value[key]) ? form.value[key] : [];
    return list.find((item) => item.nom === nom || item.question === nom || item.type === nom) || {};
};
</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-file-edit text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Entretien verbal</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Motif, anamnese et habitudes</p>
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
            <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex items-center justify-center w-6 h-6 rounded-md bg-primary-500/10">
                        <i class="pi pi-question-circle text-primary-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100">Motif de consultation</h4>
                </div>
                <Textarea
                    v-model="form.motifConsultation"
                    rows="4"
                    placeholder="Motif principal..."
                    class="w-full rounded-xl border-surface-200 dark:border-surface-700 /50"
                    @update:modelValue="(v) => updateField('motifConsultation', v)"
                />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-md bg-amber-500/10">
                            <i class="pi pi-history text-amber-500 text-sm"></i>
                        </div>
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Anamnese</h4>
                    </div>
                    <Textarea
                        v-model="form.anamnese"
                        rows="6"
                        placeholder="Evolution de la maladie..."
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 "
                        @update:modelValue="(v) => updateField('anamnese', v)"
                    />
                </div>
                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-md bg-emerald-500/10">
                            <i class="pi pi-heart text-emerald-500 text-sm"></i>
                        </div>
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Etat gynecologique</h4>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-700 dark:text-surface-300">Allaitement</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="form.etatGynecologique?.allaitement"
                                @update:modelValue="(v) => updateField('etatGynecologique', { ...form.etatGynecologique, allaitement: v })"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-700 dark:text-surface-300">Grossesse en cours</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="form.etatGynecologique?.grossesseEnCours"
                                @update:modelValue="(v) => updateField('etatGynecologique', { ...form.etatGynecologique, grossesseEnCours: v })"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-700 dark:text-surface-300">Menstrues</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="form.etatGynecologique?.menstrues"
                                @update:modelValue="(v) => updateField('etatGynecologique', { ...form.etatGynecologique, menstrues: v })"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Medicaments en cours</h4>
                    <div class="space-y-3">
                        <div v-for="name in medicamentList" :key="name" class="flex flex-col gap-2 p-3 rounded-lg ">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Checkbox :modelValue="getArrayItem('medicaments', name).estUtilise" binary @update:modelValue="(v) => updateArrayItem('medicaments', name, { estUtilise: v })" />
                                    <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ name }}</span>
                                </div>
                            </div>
                            <InputText
                                :value="getArrayItem('medicaments', name).details"
                                placeholder="Details"
                                class="w-full"
                                @update:modelValue="(v) => updateArrayItem('medicaments', name, { details: v })"
                            />
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Affections</h4>
                    <div class="space-y-3">
                        <div v-for="name in affectionList" :key="name" class="flex flex-col gap-2 p-3 rounded-lg ">
                            <div class="flex items-center gap-2">
                                <Checkbox :modelValue="getArrayItem('affections', name).estPresente" binary @update:modelValue="(v) => updateArrayItem('affections', name, { estPresente: v })" />
                                <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ name }}</span>
                            </div>
                            <InputText
                                :value="getArrayItem('affections', name).details"
                                placeholder="Details"
                                class="w-full"
                                @update:modelValue="(v) => updateArrayItem('affections', name, { details: v })"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Questions</h4>
                <div class="space-y-3">
                    <div v-for="q in questionsList" :key="q" class="flex flex-col gap-2 p-3 rounded-lg ">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ q }}</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="getArrayItem('questions', q).reponse"
                                @update:modelValue="(v) => updateArrayItem('questions', q, { reponse: v, question: q })"
                            />
                        </div>
                        <InputText
                            :value="getArrayItem('questions', q).precision"
                            placeholder="Precision si besoin"
                            class="w-full"
                            @update:modelValue="(v) => updateArrayItem('questions', q, { precision: v, question: q })"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Habitudes de vie</h4>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div v-for="h in habitudesList" :key="h" class="flex flex-col gap-2 p-3 rounded-lg ">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ h }}</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="getArrayItem('habitudes', h).estPresente"
                                @update:modelValue="(v) => updateArrayItem('habitudes', h, { estPresente: v, type: h })"
                            />
                        </div>
                        <InputText
                            :value="getArrayItem('habitudes', h).quantite"
                            placeholder="Quantite / details"
                            class="w-full"
                            @update:modelValue="(v) => updateArrayItem('habitudes', h, { quantite: v, type: h })"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
