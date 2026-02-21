<script setup>
import Drawer from 'primevue/drawer';
import { computed } from 'vue';

const props = defineProps({
  visible: { type: Boolean, default: false },
  rdv: { type: Object, default: null }
});

const emit = defineEmits(['close', 'update:visible']);

const close = () => {
  emit('update:visible', false);
  emit('close');
};

const title = computed(() => props.rdv?.patientName || 'Détails rendez-vous');

const initials = computed(() => {
  const name = props.rdv?.patientName || '';
  return name
    .split(' ')
    .map((n) => n[0] || '')
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase();
});

const statusClass = computed(() => {
  const s = (props.rdv?.statut || '').toString().toLowerCase();
  if (!s) return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-100';
  if (s.includes('valid') || s.includes('valide') || s.includes('confirmed')) return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200';
  if (s.includes('annul') || s.includes('cancel')) return 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200';
  if (s.includes('attente') || s.includes('pending')) return 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200';
  return 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200';
});

const formattedStart = computed(() => props.rdv?.start ? new Date(props.rdv.start).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const formattedEnd = computed(() => props.rdv?.end ? new Date(props.rdv.end).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—');
</script> 

<template>
  <Drawer :visible="visible" position="right" :modal="true" >
    <div class="p-4">
      <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3"> 
          <div>
            <h3 class="text-lg font-semibold text-slate-800 dark:text-slate-100">{{ title }}</h3>
            <p class="text-sm text-slate-500 dark:text-slate-300">{{ rdv?.medecinName || 'Médecin inconnu' }}</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div :class="['px-3 py-1 rounded-full text-xs font-medium', statusClass]">{{ rdv?.statut || '—' }}</div>
          <button class="text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 p-2 rounded" @click="close">✕</button>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-700 dark:text-slate-200">
        <div class="space-y-1">
          <div class="text-xs text-slate-500 dark:text-slate-400">Début</div>
          <div class="font-medium">{{ formattedStart }}</div>
        </div>
        <div class="space-y-1">
          <div class="text-xs text-slate-500 dark:text-slate-400">Fin</div>
          <div class="font-medium">{{ formattedEnd }}</div>
        </div>

        <div class="col-span-2 mt-2">
          <div class="text-xs text-slate-500 dark:text-slate-400">Patient</div>
          <div class="font-medium">{{ rdv?.patientName || '—' }}</div>
        </div>

        <div class="col-span-2">
          <div class="text-xs text-slate-500 dark:text-slate-400">Notes</div>
          <div class="mt-1 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-200">{{ rdv?.notes || 'Aucune note' }}</div>
        </div>
      </div>

      <div class="mt-6 flex gap-2">
        <button class="px-3 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded">Modifier</button>
        <button class="px-3 py-2 bg-white border border-slate-200 dark:bg-slate-700 dark:border-slate-600 text-slate-700 dark:text-slate-100 rounded" @click="close">Fermer</button>
      </div>
    </div>
  </Drawer>
</template>

<style scoped>
.p-drawer { padding: 1rem; width: 30rem; }
</style>
