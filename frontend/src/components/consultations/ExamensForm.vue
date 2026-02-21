<script setup>
import Button from 'primevue/button';
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
    },
    patientAge: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const examens = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const updateField = (key, value) => {
    examens.value = { ...examens.value, [key]: value };
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
    return Object.keys(props.examens?.toothsCheck || {}).filter(key => props.examens.toothsCheck[key]).length;
});
</script>

<!-- ExamenForm.vue -->
<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-stethoscope text-primary-600 dark:text-primary-400 text-xl"></i>
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

        <!-- Content -->
        <div class="space-y-8">
            <!-- Examen Exobuccal -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500/10">
                        <i class="pi pi-eye text-blue-500"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Examen Exobuccal</h4>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-search text-surface-400"></i>
                            Inspection
                        </label>
                        <Textarea 
                            v-model="examens.exoInspection" 
                            rows="3"
                            placeholder="Description de l'inspection visuelle..."
                            class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-blue-500/20 transition-all"
                            @update:modelValue="(v) => updateField('exoInspection', v)" 
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-hand-tap text-surface-400"></i>
                            Palpation
                        </label>
                        <Textarea 
                            v-model="examens.exoPalpation" 
                            rows="3"
                            placeholder="Résultats de la palpation..."
                            class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-blue-500/20 transition-all"
                            @update:modelValue="(v) => updateField('exoPalpation', v)" 
                        />
                    </div>
                </div>
            </div>

            <!-- Examen Endobuccal -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/10">
                        <i class="pi pi-comment text-emerald-500"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Examen Endobuccal</h4>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-eye text-surface-400"></i>
                            Inspection
                        </label>
                        <Textarea 
                            v-model="examens.endoInspection" 
                            rows="3"
                            placeholder="Inspection de la cavité buccale..."
                            class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-emerald-500/20 transition-all"
                            @update:modelValue="(v) => updateField('endoInspection', v)" 
                        />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-hand-tap text-surface-400"></i>
                            Palpation
                        </label>
                        <Textarea 
                            v-model="examens.endoPalpation" 
                            rows="3"
                            placeholder="Palpation des structures internes..."
                            class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-emerald-500/20 transition-all"
                            @update:modelValue="(v) => updateField('endoPalpation', v)" 
                        />
                    </div>
                </div>
            </div>

            <!-- Occlusion & Parodontal -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                        <i class="pi pi-teeth text-surface-400"></i>
                        Occlusion
                    </label>
                    <Textarea 
                        v-model="examens.occlusion" 
                        rows="4"
                        placeholder="État de l'occlusion dentaire..."
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-amber-500/20 transition-all"
                        @update:modelValue="(v) => updateField('occlusion', v)" 
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                        <i class="pi pi-tooth text-surface-400"></i>
                        Examen parodontal
                    </label>
                    <Textarea 
                        v-model="examens.examenParodontal" 
                        rows="4"
                        placeholder="État des gencives et du parodonte..."
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-amber-500/20 transition-all"
                        @update:modelValue="(v) => updateField('examenParodontal', v)" 
                    />
                </div>
            </div>

            <!-- Diagnostic -->
            <div class="space-y-2">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                    <i class="pi pi-clipboard text-surface-400"></i>
                    Diagnostic
                </label>
                <Textarea 
                    v-model="examens.diagnostic" 
                    rows="4"
                    placeholder="Diagnostic principal et diagnostics secondaires..."
                    class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-red-500/20 transition-all"
                    @update:modelValue="(v) => updateField('diagnostic', v)" 
                />
            </div>

            <!-- Examens Dentaires -->
            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-500/10">
                            <i class="pi pi-tooth text-purple-500"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Examens dentaires détaillés</h4>
                            <p class="text-sm text-surface-500 dark:text-surface-400">État de chaque dent</p>
                        </div>
                    </div>
                    <div class="text-sm text-surface-600 dark:text-surface-400">
                        {{ examensCount }} dent(s) examinée(s)
                    </div>
                </div>

                <!-- Arcade supérieure -->
                <div class="mb-8">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                        <h5 class="font-semibold text-surface-900 dark:text-surface-100">Arcade supérieure</h5>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="pair in toothPairs.upper" :key="pair.join('-')" 
                             class="space-y-4 border border-surface-200 dark:border-surface-700 rounded-lg p-4 bg-surface-0 dark:bg-surface-800">
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
                                    class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/50 text-sm focus:ring-1 focus:ring-purple-500/20 transition-all"
                                    @update:modelValue="(v) => updateTooth(tooth, v)" 
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Arcade inférieure -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                        <h5 class="font-semibold text-surface-900 dark:text-surface-100">Arcade inférieure</h5>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div v-for="pair in toothPairs.lower" :key="pair.join('-')" 
                             class="space-y-4 border border-surface-200 dark:border-surface-700 rounded-lg p-4 bg-surface-0 dark:bg-surface-800">
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
                                    class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/50 text-sm focus:ring-1 focus:ring-emerald-500/20 transition-all"
                                    @update:modelValue="(v) => updateTooth(tooth, v)" 
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>