<template>
    <BaseFicheBilansForm v-bind="$attrs" />
</template>

<script setup>
import BaseFicheBilansForm from '@/components/fiche-medicale/FicheBilansForm.vue';
defineOptions({ inheritAttrs: false });
</script>
