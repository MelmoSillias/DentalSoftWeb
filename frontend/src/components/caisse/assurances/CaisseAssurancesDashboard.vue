<script setup>
import Button from 'primevue/button';
import { resolveAssuranceLogoUrl } from '@/utils/assuranceUtils';

defineProps({
    cards: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['refresh', 'view-lots']);

const countValue = (card, key) => Number(card?.counts?.[key] ?? 0);
</script>

<template>
  <div class="flex flex-col gap-8">
    <div class="section-card p-5">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
          <i class="pi pi-shield text-primary text-xl"></i>
          <div>
            <p class="section-eyebrow">Assurances</p>
            <h2 class="section-title text-xl font-bold">Vue par assureur</h2>
          </div>
        </div>
        <Button icon="pi pi-refresh" label="Rafraîchir" outlined rounded @click="emit('refresh')" />
      </div>

      <div v-if="loading" class="py-12 text-center muted-text">
        <i class="pi pi-spin pi-spinner text-2xl"></i>
      </div>

      <div v-else-if="!cards.length" class="py-12 text-center muted-text">
        Aucune assurance active.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <article
          v-for="card in cards"
          :key="card.id || card.code"
          class="assurance-card group cursor-pointer"
          @click="emit('view-lots', card)"
        >
          <div class="assurance-card-brand">
            <div class="assurance-logo">
              <img
                v-if="resolveAssuranceLogoUrl(card.logoPath)"
                :src="resolveAssuranceLogoUrl(card.logoPath)"
                :alt="card.nom"
                class="assurance-logo-img"
              />
              <i v-else class="pi pi-shield text-4xl text-primary"></i>
            </div>
            <div class="text-center min-w-0 px-2">
              <h3 class="card-title font-bold text-lg truncate">{{ card.nom }}</h3>
              <p class="card-subtitle text-xs uppercase tracking-wide mt-0.5">{{ card.code }}</p>
            </div>
          </div>

          <div class="assurance-card-body">
            <div class="counts-grid">
              <div class="count-item">
                <span class="count-value">{{ countValue(card, 'sansLot') }}</span>
                <span class="count-label">Sans lot</span>
              </div>
              <div class="count-item">
                <span class="count-value">{{ countValue(card, 'ouverts') }}</span>
                <span class="count-label">Ouverts</span>
              </div>
              <div class="count-item">
                <span class="count-value">{{ countValue(card, 'envoyes') }}</span>
                <span class="count-label">Envoyés</span>
              </div>
              <div class="count-item">
                <span class="count-value">{{ countValue(card, 'confirmes') }}</span>
                <span class="count-label">Confirmés</span>
              </div>
              <div class="count-item">
                <span class="count-value">{{ countValue(card, 'rembourses') }}</span>
                <span class="count-label">Remboursés</span>
              </div>
            </div>
          </div>

          <div class="assurance-card-actions">
            <Button label="Ouvrir" icon="pi pi-arrow-right" size="small" @click.stop="emit('view-lots', card)" />
          </div>
        </article>
      </div>
    </div>
  </div>
</template>

<style scoped>
.section-card {
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
}

.section-eyebrow {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--p-primary-color);
}

.section-title,
.card-title {
  color: var(--text-color);
}

.card-subtitle,
.muted-text {
  color: var(--text-color-secondary);
}

.assurance-card {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--surface-border);
  border-radius: 1.25rem;
  background: var(--surface-card);
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  overflow: hidden;
}

.assurance-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1);
  border-color: var(--p-primary-200);
}

.assurance-card-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.875rem;
  padding: 1.5rem 1.25rem 1rem;
  background: linear-gradient(180deg, color-mix(in srgb, var(--p-primary-50) 70%, transparent) 0%, transparent 100%);
}

.assurance-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 6.5rem;
  height: 6.5rem;
  border-radius: 1.25rem;
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
  flex-shrink: 0;
  padding: 0.75rem;
}

.assurance-logo-img {
  max-height: 4.5rem;
  max-width: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
}

.assurance-card-body {
  padding: 0.5rem 1.25rem 1rem;
  flex: 1;
}

.counts-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.5rem;
}

.count-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.15rem;
  padding: 0.5rem 0.25rem;
  border-radius: 0.75rem;
  background: var(--p-surface-50);
  border: 1px solid var(--surface-border);
}

.count-value {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text-color);
}

.count-label {
  font-size: 0.65rem;
  text-align: center;
  color: var(--text-color-secondary);
  line-height: 1.2;
}

.assurance-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0.875rem 1.25rem;
  border-top: 1px solid var(--surface-border);
  background: var(--p-surface-50);
}

.app-dark .assurance-card:hover {
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.35);
  border-color: var(--p-primary-700);
}

.app-dark .assurance-card-brand {
  background: linear-gradient(180deg, color-mix(in srgb, var(--p-primary-900) 35%, transparent) 0%, transparent 100%);
}

.app-dark .assurance-logo {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}

.app-dark .count-item,
.app-dark .assurance-card-actions {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}
</style>
