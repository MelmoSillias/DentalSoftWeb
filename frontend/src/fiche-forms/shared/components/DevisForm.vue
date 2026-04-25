<template>
    <BaseDevisForm v-bind="$attrs" />
</template>

<script setup>
import BaseDevisForm from '@/components/consultations/DevisForm.vue';
defineOptions({ inheritAttrs: false });
</script>
