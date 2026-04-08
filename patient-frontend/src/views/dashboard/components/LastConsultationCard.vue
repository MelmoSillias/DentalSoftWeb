<script setup>
defineProps({
    consultation: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <PvCard>
        <template #title>Dernière consultation</template>
        <template #content>
            <p><strong>{{ consultation.date }}</strong> avec <strong>{{ consultation.doctor }}</strong></p>
            <p class="muted">{{ consultation.summary }}</p>
        </template>
    </PvCard>
</template>
