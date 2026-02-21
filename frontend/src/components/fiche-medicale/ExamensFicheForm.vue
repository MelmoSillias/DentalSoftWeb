<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
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

const updateField = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

const updateMapValue = (key, label, value) => {
    const map = { ...(form.value[key] || {}) };
    map[label] = value;
    form.value = { ...form.value, [key]: map };
};

const getMapValue = (key, label) => {
    return form.value?.[key]?.[label] ?? '';
};

const updateNested = (section, field, value) => {
    const next = { ...(form.value[section] || {}) };
    next[field] = value;
    form.value = { ...form.value, [section]: next };
};

const getTableValue = (key, row, col) => {
    return form.value?.[key]?.[row]?.[col] ?? '';
};

const setTableValue = (key, row, col, value) => {
    const table = { ...(form.value[key] || {}) };
    const rowData = { ...(table[row] || {}) };
    rowData[col] = value;
    table[row] = rowData;
    form.value = { ...form.value, [key]: table };
};

const exoInspectionList = ['Asymetrie', 'Coloration', 'Fistule', 'Autres (a preciser)', 'Tumefaction', 'Plaie', 'Cicatrice', 'autres'];
const exoPalpationList = ['Douleur', 'Siege', 'Constance'];
const chainesList = [
    'Sous mentales',
    'Adenopathies',
    'Unilaterale',
    'Fixe',
    'Sub mandibulaires',
    'Claviculaires',
    'Jugulo-carotidiennes',
    'Bilaterale',
    'Mobile'
];

const tissusMousColumns = ['Levres', 'Joues', 'Langue', 'Gencive', 'Plancher', 'Voile', 'Freins'];
const tissusMousRows = ['Couleur', 'Consistance', 'Volume', 'Lesions', 'Tumeurs', 'Inflammation'];
const tissusDursColumns = ['Rempart alveolaire interne et externe', 'Palais'];
const tissusDursRows = ['Forme', 'Lesions', 'Excroissance osseuse'];
</script>

<template>
    <div
        class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-stethoscope text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Examens cliniques</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Observation et examens locaux</p>
                </div>
            </div>
            <Button label="Sauvegarder" icon="pi pi-save" :loading="saving" @click="emit('save')"
                class="rounded-xl px-5 py-3 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white" />
        </div>

        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div
                    class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Exobuccal - Inspection</h4>
                    <div class="space-y-2 grid lg:grid-cols-2 sm:grid-cols-1 gap-3">
                        <div v-for="label in exoInspectionList" :key="label"
                            class="flex flex-col gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</span>
                            <Textarea :value="getMapValue('exobuccalInspection', label)" placeholder="Details" rows="4"
                                class="w-full h-auto"
                                @update:modelValue="(v) => updateMapValue('exobuccalInspection', label, v)" />
                        </div>
                    </div>
                </div>
                <div
                    class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Exobuccal - Palpation</h4>
                    <div class="space-y-2 grid grid-cols-2 sm:grid-cols-1 gap-3">
                        <div v-for="label in exoPalpationList" :key="label"
                            class="flex flex-col gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</span>
                            <Textarea :value="getMapValue('exobuccalPalpation', label)" placeholder="Details"
                                class="w-full"
                                @update:modelValue="(v) => updateMapValue('exobuccalPalpation', label, v)" />
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Chaines ganglionnaires</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div v-for="label in chainesList" :key="label"
                        class="flex items-center gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                        <Checkbox :modelValue="Boolean(form?.chainesGanglionnaires?.[label])" binary
                            @update:modelValue="(v) => updateMapValue('chainesGanglionnaires', label, v)" />
                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</span>
                    </div>
                </div>
            </div>

            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen endobuccal</h4>
            <div class="">
                <h5> <i class="pi pi-mouth"></i> Bouche fermée</h5>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Occlusion</label>
                    <Textarea :modelValue="form.endobuccalBoucheFermee?.occlusion" rows="3" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'occlusion', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Mediane</label>
                    <InputText :modelValue="form.endobuccalBoucheFermee?.mediane" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'mediane', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Classes d'Angle</label>
                    <InputText :modelValue="form.endobuccalBoucheFermee?.classesAngle" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'classesAngle', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Vestibules</label>
                    <InputText :modelValue="form.endobuccalBoucheFermee?.vestibules" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'vestibules', v)" />
                </div>
            </div>

            <div class="">
                <h5> <i class="pi pi-mouth"></i> Bouche ouverte</h5>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">HBD</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.hbd" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'hbd', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Brossage</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.brossage" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'brossage', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Soccu</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.soccu" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'soccu', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Cinematique
                        mandibulaire</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.cinematiqueMandibulaire" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'cinematiqueMandibulaire', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Ouverture buccale</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.ouvertureBuccale" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'ouvertureBuccale', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Temperature
                        buccale</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.temperatureBuccale" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'temperatureBuccale', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Amplitude
                        d'ouverture</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.amplitudeOuverture" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'amplitudeOuverture', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Bruits
                        articulaires</label>
                    <InputText :modelValue="form.endobuccalBoucheOuverte?.bruitsArticulaires" class="w-full"
                        @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'bruitsArticulaires', v)" />
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Examen des canaux
                    excreteurs</label>
                <Textarea :modelValue="form.examenCanauxExcreteurs" rows="3" class="w-full"
                    @update:modelValue="(v) => updateField('examenCanauxExcreteurs', v)" />
            </div>

            <div
                class="col-6 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 overflow-x-auto">
                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen des tissus mous</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="p-2 text-left"></th>
                            <th v-for="col in tissusMousColumns" :key="col"
                                class="p-2 text-left font-semibold text-surface-700 dark:text-surface-300">
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tissusMousRows" :key="row"
                            class="border-t border-surface-200 dark:border-surface-700">
                            <td class="p-2 font-semibold text-surface-700 dark:text-surface-300">{{ row }}</td>
                            <td v-for="col in tissusMousColumns" :key="col" class="p-2">
                                <InputText :value="getTableValue('tissusMousTable', row, col)" class="w-full"
                                    @update:modelValue="(v) => setTableValue('tissusMousTable', row, col, v)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 overflow-x-auto">
                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen des tissus durs</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th class="p-2 text-left"></th>
                            <th v-for="col in tissusDursColumns" :key="col"
                                class="p-2 text-left font-semibold text-surface-700 dark:text-surface-300">
                                {{ col }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tissusDursRows" :key="row"
                            class="border-t border-surface-200 dark:border-surface-700">
                            <td class="p-2 font-semibold text-surface-700 dark:text-surface-300">{{ row }}</td>
                            <td v-for="col in tissusDursColumns" :key="col" class="p-2">
                                <InputText :value="getTableValue('tissusDursTable', row, col)" class="w-full"
                                    @update:modelValue="(v) => setTableValue('tissusDursTable', row, col, v)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Examens
                        bacteriologiques</label>
                    <InputText :modelValue="form.examensBacteriologiques?.observation" placeholder="Observation"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('examensBacteriologiques', 'observation', v)" />
                    <InputText :modelValue="form.examensBacteriologiques?.resultat" placeholder="Resultat"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('examensBacteriologiques', 'resultat', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Examens
                        serologiques</label>
                    <InputText :modelValue="form.examensSerologiques?.observation" placeholder="Observation"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('examensSerologiques', 'observation', v)" />
                    <InputText :modelValue="form.examensSerologiques?.resultat" placeholder="Resultat" class="w-full"
                        @update:modelValue="(v) => updateNested('examensSerologiques', 'resultat', v)" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Examens
                        histologiques</label>
                    <InputText :modelValue="form.examensHistologiques?.observation" placeholder="Observation"
                        class="w-full"
                        @update:modelValue="(v) => updateNested('examensHistologiques', 'observation', v)" />
                    <InputText :modelValue="form.examensHistologiques?.resultat" placeholder="Resultat" class="w-full"
                        @update:modelValue="(v) => updateNested('examensHistologiques', 'resultat', v)" />
                </div>
            </div>
        </div>
    </div>
</template>
