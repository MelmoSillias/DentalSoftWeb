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
    <Card class="appt-card">
        <template #content>
            <template v-if="loading">
                <div class="row" v-for="n in 4" :key="`rdv-skeleton-${n}`">
                    <Skeleton width="4.5rem" height="0.9rem" />
                    <Skeleton width="7rem" height="0.9rem" />
                </div>
            </template>

            <div v-else-if="empty" class="empty-state">
                <i class="pi pi-calendar empty-icon" />
                <p class="muted">Aucun rendez-vous à venir.</p>
            </div>

            <div v-else class="appt-details">
                <div class="appt-highlight">
                    <i class="pi pi-clock appt-clock-icon" />
                    <div>
                        <p class="m-0 font-bold text-lg">{{ appointment.date }}</p>
                        <p class="m-0 muted text-sm">à {{ appointment.time }}</p>
                    </div>
                </div>
                <div class="appt-rows">
                    <div class="row"><span class="row-label"><i class="pi pi-user-md" /> Médecin</span><strong>{{ appointment.doctor }}</strong></div>
                    <div class="row">
                        <span class="row-label"><i class="pi pi-info-circle" /> Statut</span>
                        <Tag :value="appointment.status" severity="info" />
                    </div>
                </div>
            </div>
        </template>
    </Card>
</template>

<style scoped>
.appt-card {
    border-left: 4px solid #2563eb !important;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 0;
}

.empty-icon {
    font-size: 1.8rem;
    color: var(--p-text-muted-color);
    opacity: 0.5;
}

.appt-details {
    display: grid;
    gap: 0.85rem;
}

.appt-highlight {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    background: #eff6ff;
    border-radius: 12px;
    padding: 0.75rem 1rem;
}

.appt-clock-icon {
    font-size: 1.6rem;
    color: #2563eb;
    flex-shrink: 0;
}

.appt-rows {
    display: grid;
    gap: 0.55rem;
}

.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.row-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    color: var(--p-text-muted-color);
}

.row-label i {
    font-size: 0.8rem;
}
</style>

