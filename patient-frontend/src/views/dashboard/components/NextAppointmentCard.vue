<script setup> 

defineProps({
    appointment: {
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
    <Card> 
        <template #content>
            <template v-if="loading">
                <div class="row" v-for="n in 4" :key="`rdv-skeleton-${n}`">
                    <Skeleton width="4.5rem" height="0.9rem" />
                    <Skeleton width="7rem" height="0.9rem" />
                </div>
            </template>

            <p v-else-if="empty" class="muted">
                Aucun rendez-vous à venir.
                <Button label="Prendre rendez-vous" severity="secondary" class="ml-2" @click="$emit('book')"/>
            </p>

            <template v-else>
                <div class="row"><span class="muted">Date</span><strong>{{ appointment.date }}</strong></div>
                <div class="row"><span class="muted">Heure</span><strong>{{ appointment.time }}</strong></div>
                <div class="row"><span class="muted">Médecin</span><strong>{{ appointment.doctor }}</strong></div>
                <div class="row">
                <span class="muted">Statut</span>
                <Tag :value="appointment.status" severity="info" />
                </div>
            </template>
        </template>
    </Card>
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
