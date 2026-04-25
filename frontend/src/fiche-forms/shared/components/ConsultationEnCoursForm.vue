<template>
    <BaseConsultationEnCoursForm v-bind="$attrs" />
</template>

<script setup>
import BaseConsultationEnCoursForm from '@/components/consultations/ConsultationEnCoursForm.vue';
defineOptions({ inheritAttrs: false });
</script>
