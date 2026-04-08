<script setup>
defineProps({
    appointment: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <PvCard>
        <template #title>Prochain rendez-vous</template>
        <template #content>
            <div class="row"><span class="muted">Date</span><strong>{{ appointment.date }}</strong></div>
            <div class="row"><span class="muted">Heure</span><strong>{{ appointment.time }}</strong></div>
            <div class="row"><span class="muted">Médecin</span><strong>{{ appointment.doctor }}</strong></div>
            <div class="row">
                <span class="muted">Statut</span>
                <PvTag :value="appointment.status" severity="info" />
            </div>
        </template>
    </PvCard>
</template>

<style scoped>
.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.55rem;
}

.row:last-child {
    margin-bottom: 0;
}
</style>
