<script setup>
import { useInternetFeatures } from '@/composables/useInternetFeatures';

const { isInternetFeaturesEnabled } = useInternetFeatures();
</script>

<template>
    <slot v-if="isInternetFeaturesEnabled" />
    <slot v-else name="fallback" />
</template>
