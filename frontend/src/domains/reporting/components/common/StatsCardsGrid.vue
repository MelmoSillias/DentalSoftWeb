<script setup>
import Skeleton from 'primevue/skeleton';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});

function subLines(sub) {
    if (Array.isArray(sub)) {
        return sub.filter((line) => line != null && String(line).trim() !== '');
    }
    if (sub == null || String(sub).trim() === '') {
        return [];
    }
    return [sub];
}
</script>

<template>
    <section class="report-metrics" :aria-busy="loading">
        <div v-if="title || subtitle || $slots.actions" class="report-metrics__heading">
            <div>
                <h3 v-if="title">{{ title }}</h3>
                <p v-if="subtitle">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div class="report-metrics__grid">
            <article v-for="item in items" :key="item.key || item.label" class="report-metric">
                <div class="report-metric__heading">
                    <h4>{{ item.label }}</h4>
                    <i v-if="item.icon" :class="item.icon" aria-hidden="true"></i>
                </div>
                <Skeleton v-if="loading" class="my-3" width="7rem" height="1.75rem" />
                <p v-else class="report-metric__value">{{ item.value }}</p>
                <dl v-if="item.subStats?.length" class="report-metric__details">
                    <div v-for="(stat, index) in item.subStats" :key="`${item.key || item.label}-stat-${index}`">
                        <dt>{{ stat.label }}</dt>
                        <dd>{{ loading ? '…' : stat.value }}</dd>
                    </div>
                </dl>
                <div v-else-if="subLines(item.sub).length" class="report-metric__notes">
                    <p v-for="(line, index) in subLines(item.sub)" :key="`${item.key || item.label}-sub-${index}`">{{ loading ? '…' : line }}</p>
                </div>
            </article>
        </div>
    </section>
</template>

<style scoped>
.report-metrics__heading {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.report-metrics__heading h3 {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--text-color);
}
.report-metrics__heading p {
    margin: 0.25rem 0 0;
    font-size: 0.8125rem;
    line-height: 1.5;
    color: var(--text-color-secondary);
}
.report-metrics__grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
.report-metric {
    min-width: 0;
    padding: 1.125rem;
    border: 1px solid var(--surface-border);
    border-radius: 0.75rem;
    background: var(--surface-card);
}
.report-metric__heading {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    color: var(--text-color-secondary);
}
.report-metric__heading h4 {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1.5;
}
.report-metric__heading i {
    flex-shrink: 0;
    font-size: 0.875rem;
}
.report-metric__value {
    margin: 0.625rem 0 0;
    color: var(--text-color);
    font-size: clamp(1.25rem, 1.8vw, 1.625rem);
    font-weight: 600;
    letter-spacing: -0.025em;
    line-height: 1.4;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.report-metric__details {
    display: grid;
    gap: 0.625rem;
    margin: 1rem 0 0;
    padding-top: 0.75rem;
    border-top: 1px solid var(--surface-border);
    font-size: 0.75rem;
    line-height: 1.5;
}
.report-metric__details > div {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 0.75rem;
}
.report-metric__details dt,
.report-metric__notes {
    color: var(--text-color-secondary);
}
.report-metric__details dd {
    margin: 0;
    text-align: right;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.report-metric__notes {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    line-height: 1.5;
}
.report-metric__notes p {
    margin: 0.25rem 0 0;
}
@media (max-width: 1199px) {
    .report-metrics__grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 479px) {
    .report-metrics__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
@media print {
    .report-metric {
        break-inside: avoid;
    }
}
</style>
