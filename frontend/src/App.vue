<script setup>
import OfflineConnectionBanner from '@/components/OfflineConnectionBanner.vue';
import PwaUpdateBanner from '@/components/PwaUpdateBanner.vue';
import MutationPending from '@/components/MutationPending.vue';
</script>

<template>
    <OfflineConnectionBanner />
    <PwaUpdateBanner />
    <MutationPending />
    <router-view />
</template>

<style scoped></style>
