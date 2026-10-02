<script setup>
defineProps({
    title: {
        type: String,
        default: null
    },
    subtitle: {
        type: String,
        default: null
    },
    padded: {
        type: Boolean,
        default: false
    },
    /** No card chrome — when child items own their own background/border */
    plain: {
        type: Boolean,
        default: false
    },
    tourId: {
        type: String,
        default: null
    }
});
</script>

<template>
    <section
        class="page-section"
        :class="[
            plain ? 'page-section--plain' : null,
            padded ? 'page-section--padded' : 'page-section--flush'
        ]"
        :data-tour="tourId || undefined"
    >
        <div v-if="title || subtitle || $slots.header || $slots.headerActions" class="page-section__header">
            <div class="page-section__header-main">
                <slot name="header">
                    <h3 v-if="title" class="page-section__title">{{ title }}</h3>
                    <p v-if="subtitle" class="page-section__subtitle">{{ subtitle }}</p>
                </slot>
            </div>
            <div v-if="$slots.headerActions" class="page-section__header-actions">
                <slot name="headerActions" />
            </div>
        </div>
        <div class="page-section__body">
            <slot />
        </div>
    </section>
</template>
