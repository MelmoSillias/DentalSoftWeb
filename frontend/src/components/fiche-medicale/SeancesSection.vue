<script setup>
import PastSessions from '@/components/consultations/PastSessions.vue';

const props = defineProps({
    sessions: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <PastSessions :sessions="sessions" />
</template>
