<template>
    <BaseTraitementsDocumentsForm v-bind="$attrs" />
</template>

<script setup>
import BaseTraitementsDocumentsForm from '@/components/consultations/TraitementsDocumentsForm.vue';
defineOptions({ inheritAttrs: false });
</script>
