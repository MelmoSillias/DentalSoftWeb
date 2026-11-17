<script setup>
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import { computed, ref } from 'vue';

const props = defineProps({
    actions: {
        type: Array,
        default: () => []
    },
    dropdownLabel: {
        type: String,
        default: 'Actions'
    },
    dropdownIcon: {
        type: String,
        default: 'pi pi-ellipsis-v'
    },
    dropdownSeverity: {
        type: String,
        default: 'secondary'
    },
    showLabels: {
        type: Boolean,
        default: true
    },
    size: {
        type: String,
        default: 'normal'
    },
    rounded: {
        type: Boolean,
        default: false
    }
});

const menuRef = ref(null);

const inlineActions = computed(() => props.actions ?? []);

const dropdownItems = computed(() =>
    inlineActions.value
        .filter((action) => Boolean(action?.command))
        .map((action, index) => ({
            id: action.id ?? index,
            label: action.label,
            icon: action.icon,
            command: () => action.command?.()
        }))
);

const toggleMenu = (event) => {
    menuRef.value?.toggle(event);
};
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="hidden lg:flex flex-wrap gap-2">
            <Button
                v-for="(action, index) in inlineActions"
                :key="action.id ?? action.label ?? index"
                :icon="action.icon"
                :label="showLabels ? action.label : ''"
                :severity="action.severity || 'secondary'"
                :outlined="action.outlined"
                :text="action.text"
                :size="size"
                :rounded="rounded"
                :loading="action.loading"
                :disabled="action.disabled"
                @click="action.command?.()"
            />
        </div>
        <div class="flex lg:hidden items-center">
            <Button :label="dropdownLabel" :icon="dropdownIcon" :severity="dropdownSeverity" :size="size" :rounded="rounded" @click="toggleMenu" />
            <Menu ref="menuRef" :model="dropdownItems" popup />
        </div>
    </div>
</template>
