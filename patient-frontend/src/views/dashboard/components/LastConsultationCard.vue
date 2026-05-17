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
    <Card class="consult-card">
        <template #content>
            <template v-if="loading">
                <Skeleton width="75%" height="1rem" class="mb-2" />
                <Skeleton width="100%" height="0.9rem" class="mb-2" />
                <Skeleton width="92%" height="0.9rem" />
            </template>

            <div v-else-if="empty" class="empty-state">
                <i class="pi pi-file-edit empty-icon" />
                <p class="muted">Aucune consultation enregistrée pour l'instant.</p>
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

<style scoped>
.consult-card {
    border-left: 4px solid #0891b2 !important;
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

.consult-body {
    display: grid;
    gap: 0.7rem;
}

.consult-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
}

.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: #ecfeff;
    color: #0e7490;
    border-radius: 8px;
    padding: 0.3rem 0.65rem;
    font-size: 0.82rem;
    font-weight: 600;
}

.meta-chip i {
    font-size: 0.78rem;
}

.consult-summary {
    margin: 0;
    font-size: 0.88rem;
    line-height: 1.55;
}

.mb-2 {
    margin-bottom: 0.5rem;
}
</style>

