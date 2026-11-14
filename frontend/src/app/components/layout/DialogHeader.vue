<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    subtitle: {
        type: String,
        default: null
    },
    icon: {
        type: String,
        default: null
    },
    iconTone: {
        type: String,
        default: 'primary',
        validator: (value) => ['primary', 'success', 'warning', 'danger', 'info', 'neutral'].includes(value)
    }
});

const toneClasses = computed(() => {
    const map = {
        primary: {
            badge: 'bg-primary-100 dark:bg-primary-900/30',
            icon: 'text-primary-600 dark:text-primary-400'
        },
        success: {
            badge: 'bg-green-100 dark:bg-green-900/30',
            icon: 'text-green-600 dark:text-green-400'
        },
        warning: {
            badge: 'bg-amber-100 dark:bg-amber-900/30',
            icon: 'text-amber-600 dark:text-amber-400'
        },
        danger: {
            badge: 'bg-red-100 dark:bg-red-900/30',
            icon: 'text-red-600 dark:text-red-400'
        },
        info: {
            badge: 'bg-blue-100 dark:bg-blue-900/30',
            icon: 'text-blue-600 dark:text-blue-400'
        },
        neutral: {
            badge: 'bg-surface-100 dark:bg-surface-700',
            icon: 'text-surface-600 dark:text-surface-300'
        }
    };
    return map[props.iconTone] || map.primary;
});
</script>

<template>
    <div class="dialog-header flex items-center gap-3 w-full min-w-0">
        <div v-if="icon || $slots.icon" class="p-2 rounded-lg shrink-0" :class="toneClasses.badge" aria-hidden="true">
            <slot name="icon">
                <i :class="[icon, toneClasses.icon]" />
            </slot>
        </div>
        <div class="min-w-0 flex-1">
            <h4 class="m-0 text-surface-900 dark:text-surface-100 truncate">{{ title }}</h4>
            <p v-if="subtitle" class="text-sm text-surface-500 dark:text-surface-400 mt-1 m-0 truncate">{{ subtitle }}</p>
            <slot name="meta" />
        </div>
        <div v-if="$slots.extra" class="shrink-0 ml-auto">
            <slot name="extra" />
        </div>
    </div>
</template>
