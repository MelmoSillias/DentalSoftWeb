<script setup>
import { computed } from 'vue';
import ScheduleCell from './ScheduleCell.vue';
import { formatTimeLabel, parseISO } from '@/utils/dateUtils';

const props = defineProps({
  medecins: { type: Array, default: () => [] },
  rdvs: { type: Array, default: () => [] },
  zoom: { type: Number, default: 100 }
});

const emit = defineEmits(['create', 'validate', 'cancel', 'report']);

const slots = computed(() => {
  const start = 6 * 60;
  const end = 20 * 60;
  const step = 15;
  const data = [];
  for (let minutes = start; minutes <= end; minutes += step) {
    data.push({ minutes, label: formatTimeLabel(minutes) });
  }
  return data;
});

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
    class="w-full overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-850"
    :style="{ transform: `scale(${zoom / 100})`, transformOrigin: 'top left' }"
  >
    <!-- En-tête -->
    <div
      class="sticky top-0 z-10 grid grid-cols-[80px_repeat(auto-fit,minmax(200px,1fr))] xs:grid-cols-[70px_repeat(auto-fit,minmax(240px,1fr))] bg-gray-50 font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700"
    >
      <div class="flex items-center justify-center px-2 xs:px-4 py-2 xs:py-3.5 text-xs xs:text-sm">Heure</div>
      <div
        v-for="med in medecins"
        :key="med.id"
        class="flex items-center justify-center border-l border-gray-200 px-2 xs:px-4 py-2 xs:py-3.5 text-center text-xs xs:text-sm dark:border-gray-700"
      >
        {{ med.name }}
      </div>
    </div>

    <!-- Corps -->
    <div class="divide-y divide-gray-100 dark:divide-gray-800 overflow-x-auto min-w-[600px]"> 
      <div v-for="slot in slots" :key="slot.minutes" class="grid grid-cols-[80px_repeat(auto-fit,minmax(200px,1fr))] xs:grid-cols-[100px_repeat(auto-fit,minmax(240px,1fr))]">
        <div
          class="flex items-center justify-center bg-gray-50 px-2 xs:px-4 py-2 xs:py-3 text-xs xs:text-sm font-medium text-gray-600 dark:bg-gray-900 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700"
        >
          {{ slot.label }}
        </div>

        <div
          v-for="med in medecins"
          :key="med.id"
          class="border-l border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-850 transition-colors hover:bg-blue-50/40 dark:hover:bg-blue-950/20"
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
      </div>
    </div>
  </div>
</template>