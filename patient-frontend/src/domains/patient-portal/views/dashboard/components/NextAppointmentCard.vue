<script setup>
defineProps({
    appointment: {
        type: Object,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    empty: {
        type: Boolean,
        default: false,
    },
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
                        <p class="m-0 font-bold text-lg">
                            {{ appointment.date }}
                        </p>
                        <p class="m-0 muted text-sm">
                            à {{ appointment.time }}
                        </p>
                    </div>
                </div>
                <div class="appt-rows">
                    <div class="row">
                        <span class="row-label"
                            ><i class="pi pi-user" /> Médecin</span
                        ><strong>{{ appointment.doctor }}</strong>
                    </div>
                    <div class="row">
                        <span class="row-label"
                            ><i class="pi pi-info-circle" /> Statut</span
                        >
                        <Tag :value="appointment.status" severity="info" />
                    </div>
                </div>
            </div>
        </template>
    </Card>
</template>
