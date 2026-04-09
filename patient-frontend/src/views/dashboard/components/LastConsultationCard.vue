<script setup>
defineProps({
    consultation: {
        type: Object,
        required: true
    },
    loading: {
        type: Boolean,
        default: false
    },
    empty: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <PvCard>
        <template #title>Dernière consultation</template>
        <template #content>
            <template v-if="loading">
                <PvSkeleton width="75%" height="1rem" class="mb-2" />
                <PvSkeleton width="100%" height="0.9rem" class="mb-2" />
                <PvSkeleton width="92%" height="0.9rem" />
            </template>

            <p v-else-if="empty" class="muted">Aucune consultation enregistrée pour l'instant.</p>

            <template v-else>
                <p><strong>{{ consultation.date }}</strong> avec <strong>{{ consultation.doctor }}</strong></p>
                <p class="muted">{{ consultation.summary }}</p>
            </template>
        </template>
    </PvCard>
</template>

<style scoped>
.mb-2 {
    margin-bottom: 0.5rem;
}
</style>
