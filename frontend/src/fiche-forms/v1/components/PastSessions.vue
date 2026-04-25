<template>
    <BasePastSessions v-bind="$attrs" />
</template>

<script setup>
import BasePastSessions from '@/components/consultations/PastSessions.vue';
defineOptions({ inheritAttrs: false });
</script>
