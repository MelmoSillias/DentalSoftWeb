<script setup>
defineProps({
    cards: { type: Array, default: () => [] },
    title: { type: String, default: 'Aperçu rapide' },
    loading: { type: Boolean, default: false }
});

const toneClass = (tone) => {
    const key = tone || 'neutral';
    return `dash-stat--${key}`;
};
</script>

<template>
    <section class="dash-stats">
        <div class="dash-stats__head">
            <h3 class="dash-stats__title">
                <i class="pi pi-chart-line" aria-hidden="true"></i>
                {{ title }}
            </h3>
        </div>

        <div class="dash-stats__track scrollbar-hide">
            <template v-if="loading">
                <div v-for="idx in 4" :key="`loading-${idx}`" class="dash-stat dash-stat--skeleton">
                    <div class="dash-stat__body">
                        <div class="dash-stat__skel dash-stat__skel--label"></div>
                        <div class="dash-stat__skel dash-stat__skel--value"></div>
                        <div class="dash-stat__skel dash-stat__skel--sub"></div>
                    </div>
                    <div class="dash-stat__skel dash-stat__skel--icon"></div>
                </div>
            </template>

            <template v-else>
                <article
                    v-for="card in cards"
                    :key="card.id"
                    :class="['dash-stat', toneClass(card.tone)]"
                >
                    <div class="dash-stat__body">
                        <p class="dash-stat__label">{{ card.title }}</p>
                        <p class="dash-stat__value">{{ card.value }}</p>
                        <p v-if="card.subValue" class="dash-stat__sub">
                            <i v-if="card.subIcon" :class="card.subIcon" aria-hidden="true"></i>
                            <span>{{ card.subValue }}</span>
                        </p>
                        <RouterLink
                            v-if="card.link"
                            :to="card.link"
                            class="dash-stat__link"
                        >
                            {{ card.linkLabel || 'Voir' }}
                            <i class="pi pi-arrow-right" aria-hidden="true"></i>
                        </RouterLink>
                    </div>
                    <div class="dash-stat__icon" aria-hidden="true">
                        <i :class="card.icon"></i>
                    </div>
                </article>
            </template>
        </div>

        <p v-if="!loading && !cards.length" class="dash-stats__empty">Aucun indicateur disponible.</p>
    </section>
</template>

<style scoped>
.dash-stats {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    margin-bottom: 1.25rem;
}

.dash-stats__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dash-stats__title {
    margin: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: var(--page-section-title-size);
    font-weight: 600;
    letter-spacing: 0.01em;
    color: var(--text-color);
}

.dash-stats__title i {
    font-size: var(--page-kpi-label-size);
    color: var(--p-primary-color);
}

.dash-stats__track {
    display: flex;
    gap: 0.625rem;
    overflow-x: auto;
    padding-bottom: 0.125rem;
    -webkit-overflow-scrolling: touch;
}

.dash-stat {
    --stat-accent: var(--p-primary-color);
    --stat-accent-soft: color-mix(in srgb, var(--p-primary-color) 10%, var(--surface-card));
    --stat-accent-border: color-mix(in srgb, var(--p-primary-color) 18%, var(--surface-border));

    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.5rem;
    flex: 0 0 auto;
    min-width: 9.75rem;
    max-width: 12.5rem;
    padding: 0.625rem 0.75rem;
    border-radius: 0.625rem;
    border: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    background: var(--surface-card);
    transition: border-color 0.15s ease, background-color 0.15s ease;
}

.dash-stat:hover {
    border-color: var(--stat-accent-border);
    background: color-mix(in srgb, var(--surface-card) 92%, var(--text-color) 3%);
}

.dash-stat__body {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.dash-stat__label {
    margin: 0;
    font-size: var(--page-kpi-label-size);
    font-weight: 500;
    line-height: 1.3;
    color: var(--text-color-secondary);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-stat__value {
    margin: 0;
    font-size: var(--page-kpi-value-size);
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.02em;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dash-stat__sub {
    margin: 0.1rem 0 0;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: var(--page-kpi-meta-size);
    line-height: 1.3;
    color: var(--text-color-secondary);
}

.dash-stat__sub i {
    font-size: 0.85em;
}

.dash-stat__link {
    margin-top: 0.35rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: var(--page-kpi-meta-size);
    font-weight: 500;
    color: var(--stat-accent);
    text-decoration: none;
}

.dash-stat__link:hover {
    text-decoration: underline;
}

.dash-stat__link i {
    font-size: 0.8em;
}

.dash-stat__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.4rem;
    border: 1px solid var(--stat-accent-border);
    background: var(--stat-accent-soft);
    color: var(--stat-accent);
}

.dash-stat__icon i {
    font-size: var(--page-icon-size);
    line-height: 1;
}

.dash-stat--blue {
    --stat-accent: #3b82f6;
    --stat-accent-soft: color-mix(in srgb, #3b82f6 10%, var(--surface-card));
    --stat-accent-border: color-mix(in srgb, #3b82f6 22%, var(--surface-border));
}

.dash-stat--amber {
    --stat-accent: #d97706;
    --stat-accent-soft: color-mix(in srgb, #d97706 10%, var(--surface-card));
    --stat-accent-border: color-mix(in srgb, #d97706 22%, var(--surface-border));
}

.dash-stat--green {
    --stat-accent: #16a34a;
    --stat-accent-soft: color-mix(in srgb, #16a34a 10%, var(--surface-card));
    --stat-accent-border: color-mix(in srgb, #16a34a 22%, var(--surface-border));
}

.dash-stat--purple {
    --stat-accent: #7c3aed;
    --stat-accent-soft: color-mix(in srgb, #7c3aed 10%, var(--surface-card));
    --stat-accent-border: color-mix(in srgb, #7c3aed 22%, var(--surface-border));
}

.dash-stat--red {
    --stat-accent: #dc2626;
    --stat-accent-soft: color-mix(in srgb, #dc2626 10%, var(--surface-card));
    --stat-accent-border: color-mix(in srgb, #dc2626 22%, var(--surface-border));
}

.dash-stat--neutral {
    --stat-accent: var(--p-primary-color);
    --stat-accent-soft: color-mix(in srgb, var(--p-primary-color) 10%, var(--surface-card));
    --stat-accent-border: color-mix(in srgb, var(--p-primary-color) 18%, var(--surface-border));
}

.dash-stat--skeleton {
    pointer-events: none;
    animation: dash-stat-pulse 1.4s ease-in-out infinite;
}

.dash-stat__skel {
    border-radius: 0.25rem;
    background: color-mix(in srgb, var(--surface-border) 70%, transparent);
}

.dash-stat__skel--label {
    width: 4.5rem;
    height: 0.5rem;
}

.dash-stat__skel--value {
    width: 2.75rem;
    height: 1rem;
    margin-top: 0.35rem;
}

.dash-stat__skel--sub {
    width: 5.5rem;
    height: 0.45rem;
    margin-top: 0.35rem;
}

.dash-stat__skel--icon {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.4rem;
    flex-shrink: 0;
}

.dash-stats__empty {
    margin: 0;
    font-size: var(--page-subtitle-size);
    color: var(--text-color-secondary);
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

@keyframes dash-stat-pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.55;
    }
}

@media (min-width: 640px) {
    .dash-stats {
        margin-bottom: 1.5rem;
        gap: 0.75rem;
    }

    .dash-stat {
        min-width: 10.5rem;
        max-width: 13rem;
        padding: 0.7rem 0.85rem;
    }
}
</style>
