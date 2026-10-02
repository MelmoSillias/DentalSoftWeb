<script setup>
import Breadcrumb from 'primevue/breadcrumb';

defineProps({
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
    tourId: {
        type: String,
        default: null
    },
    breadcrumbItems: {
        type: Array,
        default: null
    },
    breadcrumbHome: {
        type: Object,
        default: () => ({ icon: 'pi pi-home', to: '/dashboard' })
    },
    showBreadcrumb: {
        type: Boolean,
        default: undefined
    }
});
</script>

<template>
    <header class="page-header" :data-tour="tourId || undefined">
        <div class="page-header__row">
            <div class="page-header__identity">
                <div v-if="icon || $slots.icon" class="page-header__icon" aria-hidden="true">
                    <slot name="icon">
                        <i :class="icon" />
                    </slot>
                </div>
                <div class="page-header__text">
                    <h1 class="page-header__title">{{ title }}</h1>
                    <p v-if="subtitle" class="page-header__subtitle">{{ subtitle }}</p>
                    <slot name="meta" />
                </div>
            </div>
            <div v-if="$slots.actions" class="page-header__actions">
                <slot name="actions" />
            </div>
        </div>
        <!-- <div
            v-if="(showBreadcrumb !== false && breadcrumbItems?.length) || $slots.breadcrumb"
            class="page-header__breadcrumb"
        >
            <slot name="breadcrumb">
                <Breadcrumb
                    v-if="breadcrumbItems?.length"
                    :home="breadcrumbHome"
                    :model="breadcrumbItems"
                    class="text-xs"
                />
            </slot>
        </div> -->
        <slot name="below" />
    </header>
</template>
