<script setup>
import Button from 'primevue/button';
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

    <div v-else class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2" :class="wrapperClass">
        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
            <span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: status.tone }" />
            <span class="text-sm font-medium text-surface-800 dark:text-surface-100">{{ status.text }}</span>
            <span class="text-xs text-surface-500 dark:text-surface-400">{{ lastSavedText }}</span>
            <span
                v-for="section in dirtySections"
                :key="section"
                class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                {{ section }}
            </span>
        </div>

        <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                <span class="hidden sm:inline">Auto-sauvegarde</span>
                <ToggleSwitch :modelValue="autoSaveEnabled" @update:modelValue="(value) => emit('update:autoSaveEnabled', value)" />
            </label>
            <Button
                label="Enregistrer tout"
                icon="pi pi-save"
                size="small"
                severity="secondary"
                outlined
                :loading="savingCount > 0"
                :disabled="savingCount > 0 || !dirtySections.length"
                @click="emit('save-all')"
            />
        </div>
    </div>
</template>
