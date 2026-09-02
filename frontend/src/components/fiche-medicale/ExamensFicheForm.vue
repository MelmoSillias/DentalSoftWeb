<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';

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

const activeTab = ref('examens-complementaires');

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const updateField = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

const updateMapValue = (key, label, value) => {
    const map = { ...(form.value?.[key] || {}) };
    map[label] = value;
    form.value = { ...form.value, [key]: map };
};

const getMapValue = (key, label) => form.value?.[key]?.[label] ?? '';

const updateNested = (section, field, value) => {
    const next = { ...(form.value?.[section] || {}) };
    next[field] = value;
    form.value = { ...form.value, [section]: next };
};

const getTableValue = (key, row, col) => form.value?.[key]?.[row]?.[col] ?? '';

const setTableValue = (key, row, col, value) => {
    const table = { ...(form.value?.[key] || {}) };
    const rowData = { ...(table[row] || {}) };
    rowData[col] = value;
    table[row] = rowData;
    form.value = { ...form.value, [key]: table };
};

const normalizeDateForApi = (value) => {
    if (!value) return null;
    if (typeof value === 'string') return value;
    const parsed = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsed.getTime())) return null;
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
};

const parseDate = (value) => {
    if (!value) return null;
    if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;
    if (typeof value === 'string') {
        const parts = value.split('-');
        if (parts.length === 3) {
            const y = Number(parts[0]);
            const m = Number(parts[1]);
            const d = Number(parts[2]);
            if (Number.isFinite(y) && Number.isFinite(m) && Number.isFinite(d)) {
                const localDate = new Date(y, m - 1, d);
                if (!Number.isNaN(localDate.getTime())) return localDate;
            }
        }
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const createExamenLabo = () => ({
    type: '',
    description: '',
    date: null,
    resultat: ''
});

const examensLaboRows = computed({
    get: () => {
        const list = Array.isArray(form.value?.examensLabo) ? form.value.examensLabo : [];
        return list.map((item) => ({
            ...item,
            type: item?.type ?? '',
            description: item?.description ?? item?.observation ?? '',
            date: parseDate(item?.date),
            resultat: item?.resultat ?? ''
        }));
    },
    set: (rows) => {
        const normalized = (rows || []).map((row) => {
            const description = String(row?.description ?? row?.observation ?? '');
            return {
                ...row,
                type: String(row?.type ?? ''),
                description,
                observation: description,
                date: normalizeDateForApi(row?.date),
                resultat: String(row?.resultat ?? '')
            };
        });
        updateField('examensLabo', normalized);
    }
});

const addExamenLabo = () => {
    examensLaboRows.value = [...examensLaboRows.value, createExamenLabo()];
};

const removeExamenLabo = (idx) => {
    examensLaboRows.value = examensLaboRows.value.filter((_, i) => i !== idx);
};

const updateExamenLabo = (idx, key, value) => {
    examensLaboRows.value = examensLaboRows.value.map((item, i) => (i === idx ? { ...item, [key]: value } : item));
};

const exoInspectionList = ['Asymetrie', 'Coloration', 'Fistule', 'Autres (a preciser)', 'Tumefaction', 'Plaie', 'Cicatrice', 'autres'];

const exoPalpationList = ['Douleur', 'Siege', 'Constance'];

const chainesList = ['Sous mentales', 'Adenopathies', 'Unilaterale', 'Fixe', 'Sub mandibulaires', 'Claviculaires', 'Jugulo-carotidiennes', 'Bilaterale', 'Mobile'];

const tissusMousColumns = ['Levres', 'Joues', 'Langue', 'Gencive', 'Plancher', 'Voile', 'Freins'];
const tissusMousRows = ['Couleur', 'Consistance', 'Volume', 'Lesions', 'Tumeurs', 'Inflammation'];
const tissusDursColumns = ['Rempart alveolaire interne et externe', 'Palais'];
const tissusDursRows = ['Forme', 'Lesions', 'Excroissance osseuse'];
</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-search text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Examens cliniques</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Observation et examens locaux</p>
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

        <Tabs v-model:value="activeTab">
            <TabList class="flex flex-wrap gap-2 border-b border-surface-200 dark:border-surface-700">
                <Tab value="examens-complementaires">Examens complémentaires</Tab>
                <Tab value="exobuccal">Exobuccal</Tab>
                <Tab value="endobuccal">Endobuccal</Tab>
                <Tab value="tissus">Tissus</Tab>
                <Tab value="examens-biologiques">Examens biologiques</Tab>
            </TabList>

            <TabPanels class="mt-6">
                <TabPanel value="examens-complementaires">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-surface-600 dark:text-surface-300">Renseignez les examens personnalisés de laboratoire.</p>
                            <Button label="Ajouter" icon="pi pi-plus" size="small" outlined @click="addExamenLabo" />
                        </div>

                        <div v-if="!examensLaboRows.length" class="text-sm text-surface-500 dark:text-surface-400 border border-dashed border-surface-300 dark:border-surface-600 rounded-xl p-4">Aucun examen complémentaire ajouté.</div>

                        <div v-else class="space-y-3">
                            <div v-for="(item, idx) in examensLaboRows" :key="idx" class="rounded-xl border border-surface-200 dark:border-surface-700 p-4 bg-surface-0 dark:bg-surface-800/40">
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                                    <div class="lg:col-span-2 space-y-1">
                                        <label class="text-xs text-surface-500">Type</label>
                                        <InputText :modelValue="item.type" class="w-full" @update:modelValue="(v) => updateExamenLabo(idx, 'type', v)" />
                                    </div>

                                    <div class="lg:col-span-4 space-y-1">
                                        <label class="text-xs text-surface-500">Description</label>
                                        <InputText :modelValue="item.description" class="w-full" @update:modelValue="(v) => updateExamenLabo(idx, 'description', v)" />
                                    </div>

                                    <div class="lg:col-span-2 space-y-1">
                                        <label class="text-xs text-surface-500">Date</label>
                                        <DatePicker :modelValue="item.date" dateFormat="dd/mm/yy" showIcon class="w-full" @update:modelValue="(v) => updateExamenLabo(idx, 'date', v)" />
                                    </div>

                                    <div class="lg:col-span-3 space-y-1">
                                        <label class="text-xs text-surface-500">Résultat</label>
                                        <InputText :modelValue="item.resultat" class="w-full" @update:modelValue="(v) => updateExamenLabo(idx, 'resultat', v)" />
                                    </div>

                                    <div class="lg:col-span-1 flex items-end justify-end">
                                        <Button icon="pi pi-trash" severity="danger" text rounded @click="removeExamenLabo(idx)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 pt-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Diagnostic supposé </label>
                            <Textarea :modelValue="form.diagnosticSupposeExamens" rows="4" class="w-full" @update:modelValue="(v) => updateField('diagnosticSupposeExamens', v)" />
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="exobuccal">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Exobuccal - Inspection</h4>
                                <div class="space-y-2 grid lg:grid-cols-2 sm:grid-cols-1 gap-3">
                                    <div v-for="label in exoInspectionList" :key="label" class="flex flex-col gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</span>
                                        <Textarea :modelValue="getMapValue('exobuccalInspection', label)" placeholder="Details" rows="4" class="w-full h-auto" @update:modelValue="(v) => updateMapValue('exobuccalInspection', label, v)" />
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Exobuccal - Palpation</h4>
                                <div class="space-y-2 grid grid-cols-2 sm:grid-cols-1 gap-3">
                                    <div v-for="label in exoPalpationList" :key="label" class="flex flex-col gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</span>
                                        <Textarea :modelValue="getMapValue('exobuccalPalpation', label)" placeholder="Details" class="w-full" @update:modelValue="(v) => updateMapValue('exobuccalPalpation', label, v)" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Chaines ganglionnaires</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div v-for="label in chainesList" :key="label" class="flex items-center gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                    <Checkbox :modelValue="Boolean(form?.chainesGanglionnaires?.[label])" binary @update:modelValue="(v) => updateMapValue('chainesGanglionnaires', label, v)" />
                                    <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="endobuccal">
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Bouche fermee</h4>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Occlusion</label>
                                    <Textarea :modelValue="form.endobuccalBoucheFermee?.occlusion" rows="3" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'occlusion', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Mediane</label>
                                    <InputText :modelValue="form.endobuccalBoucheFermee?.mediane" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'mediane', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Classes d'Angle</label>
                                    <InputText :modelValue="form.endobuccalBoucheFermee?.classesAngle" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'classesAngle', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Vestibules</label>
                                    <InputText :modelValue="form.endobuccalBoucheFermee?.vestibules" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheFermee', 'vestibules', v)" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Bouche ouverte</h4>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">HBD</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.hbd" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'hbd', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Brossage</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.brossage" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'brossage', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Soccu</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.soccu" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'soccu', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Cinematique mandibulaire</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.cinematiqueMandibulaire" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'cinematiqueMandibulaire', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Ouverture buccale</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.ouvertureBuccale" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'ouvertureBuccale', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Temperature buccale</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.temperatureBuccale" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'temperatureBuccale', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Amplitude d'ouverture</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.amplitudeOuverture" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'amplitudeOuverture', v)" />
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Bruits articulaires</label>
                                    <InputText :modelValue="form.endobuccalBoucheOuverte?.bruitsArticulaires" class="w-full" @update:modelValue="(v) => updateNested('endobuccalBoucheOuverte', 'bruitsArticulaires', v)" />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Examen des canaux excreteurs </label>
                            <Textarea :modelValue="form.examenCanauxExcreteurs" rows="3" class="w-full" @update:modelValue="(v) => updateField('examenCanauxExcreteurs', v)" />
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="tissus">
                    <div class="space-y-6">
                        <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 overflow-x-auto">
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen des tissus mous</h4>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-left"></th>
                                        <th v-for="col in tissusMousColumns" :key="col" class="p-2 text-left font-semibold text-surface-700 dark:text-surface-300">
                                            {{ col }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in tissusMousRows" :key="row" class="border-t border-surface-200 dark:border-surface-700">
                                        <td class="p-2 font-semibold text-surface-700 dark:text-surface-300">{{ row }}</td>
                                        <td v-for="col in tissusMousColumns" :key="col" class="p-2">
                                            <InputText :modelValue="getTableValue('tissusMousTable', row, col)" class="w-full" @update:modelValue="(v) => setTableValue('tissusMousTable', row, col, v)" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 overflow-x-auto">
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen des tissus durs</h4>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr>
                                        <th class="p-2 text-left"></th>
                                        <th v-for="col in tissusDursColumns" :key="col" class="p-2 text-left font-semibold text-surface-700 dark:text-surface-300">
                                            {{ col }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in tissusDursRows" :key="row" class="border-t border-surface-200 dark:border-surface-700">
                                        <td class="p-2 font-semibold text-surface-700 dark:text-surface-300">{{ row }}</td>
                                        <td v-for="col in tissusDursColumns" :key="col" class="p-2">
                                            <InputText :modelValue="getTableValue('tissusDursTable', row, col)" class="w-full" @update:modelValue="(v) => setTableValue('tissusDursTable', row, col, v)" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="examens-biologiques">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Examens bacteriologiques </label>
                            <InputText :modelValue="form.examensBacteriologiques?.observation" placeholder="Observation" class="w-full" @update:modelValue="(v) => updateNested('examensBacteriologiques', 'observation', v)" />
                            <InputText :modelValue="form.examensBacteriologiques?.resultat" placeholder="Resultat" class="w-full" @update:modelValue="(v) => updateNested('examensBacteriologiques', 'resultat', v)" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Examens serologiques </label>
                            <InputText :modelValue="form.examensSerologiques?.observation" placeholder="Observation" class="w-full" @update:modelValue="(v) => updateNested('examensSerologiques', 'observation', v)" />
                            <InputText :modelValue="form.examensSerologiques?.resultat" placeholder="Resultat" class="w-full" @update:modelValue="(v) => updateNested('examensSerologiques', 'resultat', v)" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300"> Examens histologiques </label>
                            <InputText :modelValue="form.examensHistologiques?.observation" placeholder="Observation" class="w-full" @update:modelValue="(v) => updateNested('examensHistologiques', 'observation', v)" />
                            <InputText :modelValue="form.examensHistologiques?.resultat" placeholder="Resultat" class="w-full" @update:modelValue="(v) => updateNested('examensHistologiques', 'resultat', v)" />
                        </div>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>
