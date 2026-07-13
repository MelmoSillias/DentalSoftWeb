<script setup>
import { computed } from 'vue';
import ScheduleCell from './ScheduleCell.vue';
import { formatTimeLabel, parseISO } from '@/utils/dateUtils';

const props = defineProps({
  medecins: { type: Array, default: () => [] },
  rdvs: { type: Array, default: () => [] },
  zoom: { type: Number, default: 100 },
  openingTime: { type: String, default: '08:00' },
  closingTime: { type: String, default: '18:00' }
});

const emit = defineEmits(['create', 'validate', 'cancel', 'report']);

const toMinutes = (value, fallbackMinutes) => {
  const match = String(value || '').match(/^(\d{1,2}):(\d{2})/);
  if (!match) return fallbackMinutes;
  return Number(match[1]) * 60 + Number(match[2]);
};

const slots = computed(() => {
  const start = toMinutes(props.openingTime, 8 * 60);
  const end = toMinutes(props.closingTime, 18 * 60);
  const step = 15;
  const data = [];
  const safeEnd = end > start ? end : start + 60;
  for (let minutes = start; minutes <= safeEnd; minutes += step) {
    data.push({ minutes, label: formatTimeLabel(minutes) });
  }
  return data;
});

const doctorColumnWidth = 280;
const timeColumnWidth = 88;
const gridTemplateColumns = computed(() => {
  const count = Array.isArray(props.medecins) ? props.medecins.length : 0;
  return `${timeColumnWidth}px repeat(${Math.max(count, 1)}, ${doctorColumnWidth}px)`;
});

const doctorInitials = (name) => {
  const parts = String(name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  if (!parts.length) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase();
};

const rdvByKey = computed(() => {
  const map = new Map();
  props.rdvs.forEach((rdv) => {
    const start = parseISO(rdv.start);
    const minutes = start.getHours() * 60 + start.getMinutes();
    const key = `${rdv.medecinId}-${minutes}`;
    map.set(key, rdv);
  });
  return map;
});

const resolveRdv = (medecinId, minutes) => rdvByKey.value.get(`${medecinId}-${minutes}`) || null;
</script>

<template>
  <div
    class="schedule-table inline-block min-w-full overflow-hidden rounded-xl border border-surface-200 bg-surface-0 shadow-sm dark:border-surface-700 dark:bg-surface-900 dark:shadow-none"
    :style="{ transform: `scale(${zoom / 100})`, transformOrigin: 'top left' }"
  >
    <!-- En-tête -->
    <div
      class="sticky top-0 z-10 grid border-b border-surface-200 bg-gradient-to-b from-surface-100 to-surface-50 dark:border-surface-700 dark:from-surface-800 dark:to-surface-900"
      :style="{ gridTemplateColumns }"
    >
      <div class="flex flex-col items-center justify-center gap-1 px-2 py-3 text-center">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-surface-200/80 text-surface-600 dark:bg-surface-700 dark:text-surface-200">
          <i class="pi pi-clock text-sm" />
        </span>
        <span class="text-[10px] font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">Heure</span>
      </div>

      <div
        v-for="med in medecins"
        :key="med.id"
        class="flex items-center justify-center gap-2.5 border-l border-surface-200/80 px-3 py-3 dark:border-surface-700/80"
      >
        <span
          class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-500/15 text-xs font-bold text-primary-700 ring-1 ring-primary-500/25 dark:bg-primary-400/15 dark:text-primary-300 dark:ring-primary-400/30"
        >
          {{ doctorInitials(med.name) }}
        </span>
        <div class="min-w-0 text-left">
          <p class="truncate text-sm font-semibold text-surface-900 dark:text-surface-0" :title="med.name">
            {{ med.name }}
          </p>
          <p class="truncate text-[10px] font-medium uppercase tracking-wide text-surface-500 dark:text-surface-400">
            Médecin
          </p>
        </div>
      </div>

      <div
        v-if="!medecins.length"
        class="flex items-center justify-center border-l border-surface-200 px-3 py-3 text-xs text-surface-500 dark:border-surface-700 dark:text-surface-400"
      >
        Aucun médecin
      </div>
    </div>

    <!-- Corps -->
    <div class="divide-y divide-surface-100 bg-surface-0 dark:divide-surface-800 dark:bg-surface-900">
      <div
        v-for="slot in slots"
        :key="slot.minutes"
        class="grid"
        :style="{ gridTemplateColumns }"
      >
        <div
          class="flex items-center justify-center border-r border-surface-200 bg-surface-50 px-2 py-2 text-xs font-semibold tabular-nums text-surface-600 dark:border-surface-700 dark:bg-surface-950 dark:text-surface-300"
        >
          {{ slot.label }}
        </div>

        <template v-if="medecins.length">
          <div
            v-for="med in medecins"
            :key="med.id"
            class="border-l border-surface-200 bg-surface-0 transition-colors hover:bg-primary-50/40 dark:border-surface-700 dark:bg-surface-900 dark:hover:bg-primary-950/25"
          >
            <ScheduleCell
              :slot-label="slot.label"
              :rdv="resolveRdv(med.id, slot.minutes)"
              :medecin-name="med.name"
              @create="emit('create', { medecin: med, slot })"
              @validate="emit('validate', $event)"
              @cancel="emit('cancel', $event)"
              @report="emit('report', $event)"
            />
          </div>
        </template>
        <div
          v-else
          class="border-l border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900"
        />
      </div>
    </div>
  </div>
</template>
