<script setup>
defineProps({
    consultation: {
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
    <Card class="consult-card">
        <template #content>
            <template v-if="loading">
                <Skeleton width="75%" height="1rem" class="mb-2" />
                <Skeleton width="100%" height="0.9rem" class="mb-2" />
                <Skeleton width="92%" height="0.9rem" />
            </template>

            <div v-else-if="empty" class="empty-state">
                <i class="pi pi-file-edit empty-icon" />
                <p class="muted">
                    Aucune consultation enregistrée pour l'instant.
                </p>
            </div>

            <div v-else class="consult-body">
                <div class="consult-meta">
                    <div class="meta-chip">
                        <i class="pi pi-calendar-clock" />
                        <span>{{ consultation.date }}</span>
                    </div>
                    <div class="meta-chip">
                        <i class="pi pi-user" />
                        <span>{{ consultation.doctor }}</span>
                    </div>
                </div>
                <p class="consult-summary muted">{{ consultation.summary }}</p>
            </div>
        </template>
    </Card>
</template>
