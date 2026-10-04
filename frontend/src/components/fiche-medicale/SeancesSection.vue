<script setup>
import PastSessions from '@/components/consultations/PastSessions.vue';

defineProps({
    sessions: {
        type: Array,
        default: () => []
    },
    layout: {
        type: String,
        default: ''
    }
});
</script>

<template>
    <PastSessions :sessions="sessions" :layout="layout" />
</template>
