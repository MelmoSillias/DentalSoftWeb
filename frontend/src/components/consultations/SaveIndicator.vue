<script setup>
import Button from 'primevue/button';
import InputSwitch from 'primevue/inputswitch';
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    dirtySections: {
        type: Array,
        default: () => []
    },
    savingCount: {
        type: Number,
        default: 0
    },
    lastSavedAt: {
        type: [Date, String, Number, null],
        default: null
    },
    autoSaveEnabled: {
        type: Boolean,
        default: false
    },
    floating: {
        type: Boolean,
        default: false
    },
    minimalDesign: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['save-all', 'update:autoSaveEnabled']);

const status = computed(() => {
    const dirty = props.dirtySections?.length > 0;
    const primary = 'var(--p-primary-500, #0ea5e9)';
    const warn = 'var(--p-amber-500, #f59e0b)';
    const muted = 'var(--p-surface-500, #9ca3af)';
    if (props.savingCount > 0) return { tone: primary, text: 'Enregistrement en cours...' };
    if (dirty) return { tone: warn, text: 'Modifications non sauvegardées' };
    return { tone: muted, text: 'Aucune modification' };
});

const lastSavedText = computed(() => {
    if (!props.lastSavedAt) return 'Jamais enregistré';
    const d = new Date(props.lastSavedAt);
    if (Number.isNaN(d.getTime())) return props.lastSavedAt;
    return `Dernière sauvegarde à ${d.toLocaleTimeString('fr-FR')}`;
});

const wrapperClass = computed(() => {
    if (!props.floating) return 'mb-0';
    return ''; //fixed top-[80px] right-[10px] -translate-x-1/2 z-50 w-[min(1100px,95vw)] shadow-2xl;
});
</script>

<!-- SaveIndicator.vue -->
<template>
    <div v-if="minimalDesign" class="flex items-center gap-3">
        <!-- Status dot -->
        <div class="flex items-center gap-2 text-sm">
            <span class="w-2.5 h-2.5 rounded-full" :style="{ backgroundColor: status.tone }"></span>

            <span class="text-surface-700 dark:text-surface-300 font-medium">
                {{ status.text }}
            </span>
        </div>

        <!-- Saving spinner -->
        <i v-if="savingCount > 0" class="pi pi-spin pi-spinner text-primary-500 text-sm"></i>

        <!-- Last saved -->
        <span class="text-xs text-surface-500 dark:text-surface-400">
            {{ lastSavedText }}
        </span>

        <!-- Save button (icon only) -->
        <Button icon="pi pi-save" text rounded size="small" :disabled="savingCount > 0 || !dirtySections.length" @click="emit('save-all')" />
    </div>

    <div v-else class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-5 shadow-sm" :class="wrapperClass">
        <div class="flex items-center justify-between gap-4">
            <!-- Left: Status -->
            <div class="flex items-center gap-4">
                <div class="relative">
                    <div class="w-4 h-4 rounded-full animate-pulse" :style="{ backgroundColor: status.tone }" />
                    <div v-if="savingCount > 0" class="absolute -top-1 -right-1">
                        <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-500 animate-spin">
                            <i class="pi pi-spinner text-white text-xs"></i>
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-surface-900 dark:text-surface-100">{{ status.text }}</span>
                        <Badge v-if="savingCount > 0" :value="savingCount" severity="info" class="px-2 py-0.5 text-xs animate-pulse" />
                    </div>
                    <div class="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-400">
                        <i class="pi pi-clock"></i>
                        <span>{{ lastSavedText }}</span>
                    </div>
                </div>
            </div>

            <!-- Center: Dirty Sections -->
            <div class="flex-1">
                <div v-if="dirtySections.length" class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-surface-700 dark:text-surface-300">Modifications en attente :</span>
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="section in dirtySections"
                            :key="section"
                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-sm font-medium border border-amber-200 dark:border-amber-800"
                        >
                            <i class="pi pi-exclamation-circle text-xs"></i>
                            {{ section }}
                        </span>
                    </div>
                </div>
                <div v-else class="text-sm text-emerald-600 dark:text-emerald-400 font-medium">
                    <i class="pi pi-check-circle mr-2"></i>
                    Toutes les modifications sont enregistrées
                </div>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-surface-600 dark:text-surface-300">Auto-sauvegarde</span>
                    <ToggleSwitch :modelValue="autoSaveEnabled" @update:modelValue="(value) => emit('update:autoSaveEnabled', value)" />
                </div>
                <Button
                    label="Enregistrer tout"
                    icon="pi pi-save"
                    size="small"
                    :disabled="savingCount > 0 || !dirtySections.length"
                    @click="emit('save-all')"
                    class="rounded-xl px-5 py-2.5 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                />
            </div>
        </div>

        <!-- Progress Bar -->
        <div v-if="savingCount > 0" class="mt-4 pt-4 border-t border-surface-100 dark:border-surface-700">
            <div class="flex items-center justify-between text-sm text-surface-600 dark:text-surface-400 mb-2">
                <span>Sauvegarde en cours...</span>
                <span>{{ savingCount }} section(s)</span>
            </div>
            <div class="w-full h-2 bg-surface-100 dark:bg-surface-700 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-primary-500 to-primary-600 rounded-full animate-pulse" style="width: 80%" />
            </div>
        </div>
    </div>
</template>
