<template>
    <BaseAnamneseForm v-bind="$attrs" />
</template>

<script setup>
import BaseAnamneseForm from '@/components/consultations/AnamneseForm.vue';
defineOptions({ inheritAttrs: false });
</script>
