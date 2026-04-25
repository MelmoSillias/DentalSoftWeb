<template>
    <BaseFichePatientInfoSection v-bind="$attrs" />
</template>

<script setup>
import BaseFichePatientInfoSection from '@/components/fiche-medicale/FichePatientInfoSection.vue';
defineOptions({ inheritAttrs: false });
</script>
