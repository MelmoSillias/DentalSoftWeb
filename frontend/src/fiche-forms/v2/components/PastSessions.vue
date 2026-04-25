<template>
    <BaseSeancesSection v-bind="$attrs" />
</template>

<script setup>
import BaseSeancesSection from '@/components/fiche-medicale/SeancesSection.vue';
defineOptions({ inheritAttrs: false });
</script>
