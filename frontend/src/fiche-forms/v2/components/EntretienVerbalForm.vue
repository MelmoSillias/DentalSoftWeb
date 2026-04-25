<template>
    <BaseEntretienVerbalForm v-bind="$attrs" />
</template>

<script setup>
import BaseEntretienVerbalForm from '@/components/fiche-medicale/EntretienVerbalForm.vue';
defineOptions({ inheritAttrs: false });
</script>
