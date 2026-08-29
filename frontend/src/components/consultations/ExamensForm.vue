<script setup>
import Button from 'primevue/button';
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
    },
    patientAge: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['update:modelValue', 'save']);
const activeTab = ref('examens-complementaires');

const examens = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const updateField = (key, value) => {
    examens.value = { ...examens.value, [key]: value };
};

const createComplementaire = () => ({
    type: '',
    description: '',
    date: null,
    resultat: ''
});

const examensComplementaires = computed(() =>
    Array.isArray(examens.value?.examensComplementaires)
        ? examens.value.examensComplementaires
        : []
);

const setExamensComplementaires = (nextList) => {
    updateField('examensComplementaires', nextList);
};

const addExamenComplementaire = () => {
    setExamensComplementaires([...examensComplementaires.value, createComplementaire()]);
};

const removeExamenComplementaire = (idx) => {
    setExamensComplementaires(examensComplementaires.value.filter((_, i) => i !== idx));
};

const updateExamenComplementaire = (idx, key, value) => {
    setExamensComplementaires(
        examensComplementaires.value.map((item, i) => (i === idx ? { ...item, [key]: value } : item))
    );
};

const toothPairs = computed(() => {
    if (props.patientAge > 5) {
    return {
        upper: [
            [11, 21],
            [12, 22],
            [13, 23],
            [14, 24],
            [15, 25],
            [16, 26],
            [17, 27],
            [18, 28]
        ],
        lower: [
            [31, 41],
            [32, 42],
            [33, 43],
            [34, 44],
            [35, 45],
            [36, 46],
            [37, 47],
            [38, 48]
        ]
    };
    }
    return {
        upper: [
            [51, 61],
            [52, 62],
            [53, 63],
             [54, 64],
            [55, 65]
        ],
        lower: [
            [71, 81],
            [72, 82],
            [73, 83],
             [74, 84],
             [75, 85]
        ]
    };
});

const updateTooth = (tooth, value) => {
    const next = { ...(examens.value.toothsCheck || {}) };
    next[tooth] = value;
    examens.value = { ...examens.value, toothsCheck: next };
};

const examensCount = computed(() => {
    return Object.keys(examens.value?.toothsCheck || {}).filter((key) => examens.value.toothsCheck[key]).length;
});
</script>

<!-- ExamenForm.vue -->
<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-search text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Examen clinique</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        Observations et diagnostics
                    </p>
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
                <Tab value="occlusion">Occlusion & parodontal</Tab>
                <Tab value="diagnostic">Diagnostic</Tab>
                <Tab value="dentaire">Dentaire</Tab>
            </TabList>

            <TabPanels class="mt-6">
                <TabPanel value="examens-complementaires">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-surface-600 dark:text-surface-300">Renseignez les examens complémentaires liés à cette consultation.</p>
                            <Button label="Ajouter" icon="pi pi-plus" size="small" outlined @click="addExamenComplementaire" />
                        </div>

                        <div v-if="!examensComplementaires.length" class="text-sm text-surface-500 dark:text-surface-400 border border-dashed border-surface-300 dark:border-surface-600 rounded-xl p-4">
                            Aucun examen complémentaire ajouté.
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(item, idx) in examensComplementaires"
                                :key="idx"
                                class="rounded-xl border border-surface-200 dark:border-surface-700 p-4 bg-surface-0 dark:bg-surface-800/40"
                            >
                                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
                                    <div class="lg:col-span-3 space-y-1">
                                        <label class="text-xs text-surface-500">Type</label>
                                        <InputText :modelValue="item.type" class="w-full" @update:modelValue="(v) => updateExamenComplementaire(idx, 'type', v)" />
                                    </div>
                                    <div class="lg:col-span-4 space-y-1">
                                        <label class="text-xs text-surface-500">Description</label>
                                        <InputText :modelValue="item.description" class="w-full" @update:modelValue="(v) => updateExamenComplementaire(idx, 'description', v)" />
                                    </div>
                                    <div class="lg:col-span-2 space-y-1">
                                        <label class="text-xs text-surface-500">Date</label>
                                        <DatePicker
                                            :modelValue="item.date"
                                            dateFormat="dd/mm/yy"
                                            showIcon
                                            class="w-full"
                                            @update:modelValue="(v) => updateExamenComplementaire(idx, 'date', v)"
                                        />
                                    </div>
                                    <div class="lg:col-span-3 space-y-1">
                                        <label class="text-xs text-surface-500">Résultat</label>
                                        <div class="flex items-center gap-2">
                                            <InputText :modelValue="item.resultat" class="w-full" @update:modelValue="(v) => updateExamenComplementaire(idx, 'resultat', v)" />
                                            <Button icon="pi pi-trash" severity="danger" text @click="removeExamenComplementaire(idx)" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 pt-2 border-t border-surface-200 dark:border-surface-700">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Diagnostic supposé</label>
                            <Textarea
                                :modelValue="examens.diagnosticSupposeExamens"
                                rows="3"
                                placeholder="Hypothèse diagnostique associée aux examens complémentaires..."
                                class="w-full"
                                @update:modelValue="(v) => updateField('diagnosticSupposeExamens', v)"
                            />
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="exobuccal">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Inspection</label>
                            <Textarea
                                v-model="examens.exoInspection"
                                rows="5"
                                placeholder="Description de l'inspection visuelle..."
                                class="w-full"
                                @update:modelValue="(v) => updateField('exoInspection', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Palpation</label>
                            <Textarea
                                v-model="examens.exoPalpation"
                                rows="5"
                                placeholder="Résultats de la palpation..."
                                class="w-full"
                                @update:modelValue="(v) => updateField('exoPalpation', v)"
                            />
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="endobuccal">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Inspection</label>
                            <Textarea
                                v-model="examens.endoInspection"
                                rows="5"
                                placeholder="Inspection de la cavité buccale..."
                                class="w-full"
                                @update:modelValue="(v) => updateField('endoInspection', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Palpation</label>
                            <Textarea
                                v-model="examens.endoPalpation"
                                rows="5"
                                placeholder="Palpation des structures internes..."
                                class="w-full"
                                @update:modelValue="(v) => updateField('endoPalpation', v)"
                            />
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="occlusion">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Occlusion</label>
                            <Textarea
                                v-model="examens.occlusion"
                                rows="5"
                                placeholder="État de l'occlusion dentaire..."
                                class="w-full"
                                @update:modelValue="(v) => updateField('occlusion', v)"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Examen parodontal</label>
                            <Textarea
                                v-model="examens.examenParodontal"
                                rows="5"
                                placeholder="État des gencives et du parodonte..."
                                class="w-full"
                                @update:modelValue="(v) => updateField('examenParodontal', v)"
                            />
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="diagnostic">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Diagnostic</label>
                        <Textarea
                            v-model="examens.diagnostic"
                            rows="6"
                            placeholder="Diagnostic principal et diagnostics secondaires..."
                            class="w-full"
                            @update:modelValue="(v) => updateField('diagnostic', v)"
                        />
                    </div>
                </TabPanel>

                <TabPanel value="dentaire">
                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Examens dentaires détaillés</h4>
                                <p class="text-sm text-surface-500 dark:text-surface-400">État de chaque dent</p>
                            </div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">
                                {{ examensCount }} dent(s) examinée(s)
                            </div>
                        </div>

                        <div class="mb-8">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <h5 class="font-semibold text-surface-900 dark:text-surface-100">Arcade supérieure</h5>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div
                                    v-for="pair in toothPairs.upper"
                                    :key="pair.join('-')"
                                    class="space-y-4 border border-surface-200 dark:border-surface-700 rounded-lg p-4 bg-surface-0 dark:bg-surface-800"
                                >
                                    <div v-for="tooth in pair" :key="tooth" class="space-y-2">
                                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center justify-between">
                                            <span>Dent {{ tooth }}</span>
                                            <span class="text-xs px-2 py-1 rounded-full bg-surface-100 dark:bg-surface-700 text-surface-600 dark:text-surface-400">
                                                {{ examens.toothsCheck?.[tooth]?.length || 0 }} caractères
                                            </span>
                                        </label>
                                        <Textarea
                                            :value="examens.toothsCheck?.[tooth]"
                                            rows="2"
                                            :placeholder="`État de la dent ${tooth}...`"
                                            class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/50 text-sm"
                                            @update:modelValue="(v) => updateTooth(tooth, v)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                                <h5 class="font-semibold text-surface-900 dark:text-surface-100">Arcade inférieure</h5>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div
                                    v-for="pair in toothPairs.lower"
                                    :key="pair.join('-')"
                                    class="space-y-4 border border-surface-200 dark:border-surface-700 rounded-lg p-4 bg-surface-0 dark:bg-surface-800"
                                >
                                    <div v-for="tooth in pair" :key="tooth" class="space-y-2">
                                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center justify-between">
                                            <span>Dent {{ tooth }}</span>
                                            <span class="text-xs px-2 py-1 rounded-full bg-surface-100 dark:bg-surface-700 text-surface-600 dark:text-surface-400">
                                                {{ examens.toothsCheck?.[tooth]?.length || 0 }} caractères
                                            </span>
                                        </label>
                                        <Textarea
                                            :value="examens.toothsCheck?.[tooth]"
                                            rows="2"
                                            :placeholder="`État de la dent ${tooth}...`"
                                            class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/50 text-sm"
                                            @update:modelValue="(v) => updateTooth(tooth, v)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>