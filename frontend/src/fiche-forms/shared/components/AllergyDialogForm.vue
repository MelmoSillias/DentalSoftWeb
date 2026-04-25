<template>
    <BaseAllergyDialogForm v-bind="$attrs" />
</template>

<script setup>
import BaseAllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
defineOptions({ inheritAttrs: false });
</script>
