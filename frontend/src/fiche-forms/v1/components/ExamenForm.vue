<template>
    <BaseExamensForm v-bind="$attrs" />
</template>

<script setup>
import BaseExamensForm from '@/components/consultations/ExamensForm.vue';
defineOptions({ inheritAttrs: false });
</script>
