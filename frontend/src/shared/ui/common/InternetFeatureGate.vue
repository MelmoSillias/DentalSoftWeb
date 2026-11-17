<script setup>
import { useInternetFeatures } from '@/shared/composables/useInternetFeatures.js';

const { isInternetFeaturesEnabled } = useInternetFeatures();
</script>

<template>
    <slot v-if="isInternetFeaturesEnabled" />
    <slot v-else name="fallback" />
</template>
