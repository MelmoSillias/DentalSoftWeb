<template>
    <BasePatientInfoCard v-bind="$attrs" />
</template>

<script setup>
import BasePatientInfoCard from '@/components/consultations/PatientInfoCard.vue';
defineOptions({ inheritAttrs: false });
</script>
