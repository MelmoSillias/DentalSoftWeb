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
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const motif = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const updateField = (key, value) => {
    motif.value = { ...motif.value, [key]: value };
};

const totalCharacters = computed(() => {
    const values = Object.values(props.motif || {});
    return values.reduce((sum, val) => sum + (val?.length || 0), 0);
});
</script>

<!-- AnamneseForm.vue -->
<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-file-edit text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Anamnèse</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        Informations sur la consultation et antécédents
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
        <div class="space-y-6">
            <!-- Motif Section -->
            <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                <div class="flex items-center gap-2 mb-3">
                    <div class="flex items-center justify-center w-6 h-6 rounded-md bg-primary-500/10">
                        <i class="pi pi-question-circle text-primary-500 text-sm"></i>
                    </div>
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100">Motif de consultation</h4>
                </div>
                <Textarea 
                    v-model="motif.motif" 
                    rows="4" 
                    placeholder="Décrivez le motif principal de la consultation..."
                    class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                    @update:modelValue="(v) => updateField('motif', v)" 
                />
            </div>

            <!-- Two Columns Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Anamnese -->
                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-md bg-amber-500/10">
                            <i class="pi pi-history text-amber-500 text-sm"></i>
                        </div>
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Anamnese</h4>
                    </div>
                    <Textarea 
                        v-model="motif.histoireMaladie" 
                        rows="6"
                        placeholder="Décrivez l'évolution de la maladie, les symptômes, les traitements antérieurs..."
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                        @update:modelValue="(v) => updateField('histoireMaladie', v)" 
                    />
                </div>

                <!-- Soins antérieurs -->
                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-md bg-emerald-500/10">
                            <i class="pi pi-heart text-emerald-500 text-sm"></i>
                        </div>
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Soins antérieurs</h4>
                    </div>
                    <Textarea 
                        v-model="motif.soinsAnterieurs" 
                        rows="6"
                        placeholder="Indiquez les traitements, chirurgies, ou soins précédemment reçus..."
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                        @update:modelValue="(v) => updateField('soinsAnterieurs', v)" 
                    />
                </div>
            </div>

            <!-- Character Counter & Status -->
            <div class="flex items-center justify-between pt-4 border-t border-surface-100 dark:border-surface-700">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-sm text-surface-600 dark:text-surface-400">
                        Sauvegarde automatique activée
                    </span>
                </div>
                <div class="text-sm text-surface-500 dark:text-surface-400">
                    {{ totalCharacters }} caractères
                </div>
            </div>
        </div>
    </div>
</template>
 