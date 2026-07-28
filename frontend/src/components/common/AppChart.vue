<script setup>
import Chart from 'primevue/chart';
import { usePageRouteReady } from '@/composables/usePageRouteReady';
import { watch, ref } from 'vue';

defineOptions({ inheritAttrs: true });

const pageRouteReady = usePageRouteReady();
const canRender = ref(pageRouteReady.value);

watch(
    pageRouteReady,
    (ready) => {
        canRender.value = ready;
    },
    { immediate: true }
);
</script>

<template>
    <Chart v-if="canRender" v-bind="$attrs" />
</template>
