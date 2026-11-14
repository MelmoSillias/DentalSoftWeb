<script setup>
import OfflineConnectionBanner from '@/components/OfflineConnectionBanner.vue';
import PwaUpdateBanner from '@/components/PwaUpdateBanner.vue';
import { watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useMutationStatus } from '@/composables/useMutationStatus';
import { usePwaUpdate } from '@/composables/usePwaUpdate';

const { mandatory } = usePwaUpdate();

const { issue } = useMutationStatus();
const toast = useToast();
watch(issue, (value) => {
    if (value) toast.add({ severity: 'warn', summary: 'Confirmation incertaine', detail: value.message, life: 12000 });
});
</script>

<template>
    <OfflineConnectionBanner v-if="!mandatory" />
    <PwaUpdateBanner />
    <router-view v-if="!mandatory" />
</template>

<style scoped></style>
