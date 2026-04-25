<template>
    <BaseFicheDocumentsForm v-bind="$attrs" />
</template>

<script setup>
import BaseFicheDocumentsForm from '@/components/fiche-medicale/FicheDocumentsForm.vue';
defineOptions({ inheritAttrs: false });
</script>
