<template>
    <BaseOrdonnanceModal v-bind="$attrs" />
</template>

<script setup>
import BaseOrdonnanceModal from '@/components/consultations/OrdonnanceModal.vue';
defineOptions({ inheritAttrs: false });
</script>
