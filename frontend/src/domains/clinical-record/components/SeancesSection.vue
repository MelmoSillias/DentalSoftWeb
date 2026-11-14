<script setup>
import PastSessions from '@/domains/care-delivery/components/PastSessions.vue';

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
