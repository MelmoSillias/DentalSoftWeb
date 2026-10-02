<script setup>
defineOptions({
    inheritAttrs: false
});

defineProps({
    /** Optional guided-tour id prefixed on the shell (e.g. admin-finances) */
    tourId: {
        type: String,
        default: null
    },
    as: {
        type: String,
        default: 'section'
    }
});
</script>

<template>
    <component :is="as" class="page-shell" :data-tour="tourId || undefined" v-bind="$attrs">
        <slot name="header" />
        <div v-if="$slots.toolbar" class="page-shell__toolbar">
            <slot name="toolbar" />
        </div>
        <div class="page-shell__body">
            <slot />
        </div>
        <slot name="dialogs" />
    </component>
</template>
