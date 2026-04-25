<template>
    <BaseFichePlanTraitementForm v-bind="$attrs" />
</template>

<script setup>
import BaseFichePlanTraitementForm from '@/components/fiche-medicale/FichePlanTraitementForm.vue';
defineOptions({ inheritAttrs: false });
</script>
