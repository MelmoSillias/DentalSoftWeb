<template>
    <BaseExamensFicheForm v-bind="$attrs" />
</template>

<script setup>
import BaseExamensFicheForm from '@/components/fiche-medicale/ExamensFicheForm.vue';
defineOptions({ inheritAttrs: false });
</script>
