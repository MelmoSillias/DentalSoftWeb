<script setup>
import OfflineConnectionBanner from '@/components/OfflineConnectionBanner.vue';
import PwaUpdateBanner from '@/components/PwaUpdateBanner.vue';
import { watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useMutationStatus } from '@/composables/useMutationStatus';

const { issue } = useMutationStatus();
const toast = useToast();
watch(issue, (value) => {
    if (value) toast.add({ severity: 'warn', summary: 'Confirmation incertaine', detail: value.message, life: 12000 });
});
</script>

<template>
    <OfflineConnectionBanner />
    <PwaUpdateBanner />
    <router-view />
</template>

<style scoped></style>
