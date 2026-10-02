<script setup>
import OfflineConnectionBanner from '@/components/OfflineConnectionBanner.vue';
import PwaUpdateBanner from '@/components/PwaUpdateBanner.vue';
</script>

<template>
    <OfflineConnectionBanner />
    <PwaUpdateBanner />
    <router-view />
</template>

<style scoped></style>
