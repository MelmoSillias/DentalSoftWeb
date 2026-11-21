<script setup>
import OfflineConnectionBanner from '@/shared/ui/OfflineConnectionBanner.vue';
import PwaUpdateBanner from '@/shared/ui/PwaUpdateBanner.vue';
import PrintPreviewDialog from '@/shared/print/components/PrintPreviewDialog.vue';
import { watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useMutationStatus } from '@/shared/composables/useMutationStatus.js';

import { usePwaUpdate } from '@/shared/composables/usePwaUpdate.js';

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
    <PrintPreviewDialog v-if="!mandatory" />
</template>

<style scoped></style>
