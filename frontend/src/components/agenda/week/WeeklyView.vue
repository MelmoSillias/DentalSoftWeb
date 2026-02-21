<script setup>
import FullCalendar from '@fullcalendar/vue3';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import ContextMenu from 'primevue/contextmenu';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, reactive, ref, watch } from 'vue';
import DetailsRdv from './DetailsRdv.vue';
import { addMinutes } from '@/utils/dateUtils';
import { useRdvStatus } from '@/composables/useRdvStatus';

const props = defineProps({
  medecins: { type: Array, default: () => [] },
  api: { type: Object, required: true },
  refreshKey: { type: Number, default: 0 }
});

const emit = defineEmits(['request-create', 'request-validate', 'request-cancel', 'request-report']);

const calendarPlugins = [timeGridPlugin, interactionPlugin];

const events = ref([]);
const loading = ref(false);
const filters = reactive({ medecinId: null, patient: '' });
const calendarRef = ref();
const contextMenu = ref();
const selectedEvent = ref(null);
const drawerVisible = ref(false);
const selectedRdvDetails = ref(null);
const currentRange = ref(null);
const lastFetchKey = ref('');
const isFetching = ref(false);
const { getCssClass } = useRdvStatus();

const medecinsOptions = computed(() => {
  return Array.isArray(props.medecins) ? props.medecins : props.medecins?.value || [];
});

const menuItems = [
  { label: 'Valider', icon: 'pi pi-check', command: () => { if (selectedEvent.value) { emit('request-validate', selectedEvent.value.extendedProps); loadEvents(true); } } },
  { label: 'Reporter', icon: 'pi pi-calendar-minus', command: () => { if (selectedEvent.value) { emit('request-report', selectedEvent.value.extendedProps); loadEvents(true); } } },
  { label: 'Annuler', icon: 'pi pi-times', command: () => { if (selectedEvent.value) { emit('request-cancel', selectedEvent.value.extendedProps); loadEvents(true); } } }
];

const loadEvents = async (force = false) => {
  if (!props.api?.fetchEvents) return;
  const start = currentRange.value?.start;
  const end = currentRange.value?.end;
  if (!start || !end) return;
  const fetchKey = `${start.toISOString()}_${end.toISOString()}_${filters.medecinId ?? 'all'}_${filters.patient ?? ''}`;
  if (!force && (isFetching.value || lastFetchKey.value === fetchKey)) return;
  isFetching.value = true;
  lastFetchKey.value = fetchKey;
  loading.value = true;
  try {
    const payload = await props.api.fetchEvents({
      start,
      end,
      medecinId: filters.medecinId,
      patientQuery: filters.patient
    });

    events.value = payload.map(rdv => ({
      id: rdv.id,
      title: `${rdv.patientName || 'Patient'} — ${rdv.medecinName || 'Médecin'}`,
      start: rdv.start,
      end: rdv.end,
      extendedProps: rdv,
      classNames: [getCssClass(rdv.statut)]
    }));
  } finally {
    loading.value = false;
    isFetching.value = false;
  }
};

const handleDateClick = (info) => {
  const start = info.date;
  const end = addMinutes(start, 30);
  emit('request-create', { start, end });
};

const handleEventClick = (info) => {
  // left-click selects the event but we don't show the context menu on single click
  selectedEvent.value = info.event;
};

const handleEventMount = (info) => {
  // show native tooltip with more details
  try {
    const props = info.event.extendedProps || {};
    const patient = props.patientName || 'Patient inconnu';
    const medecin = props.medecinName ? `Dr. ${props.medecinName}` : '';
    const motif = props.motif ? `Motif: ${props.motif}` : '';
    const start = info.event.start ? info.event.start.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '';
    const end = info.event.end ? info.event.end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
    const timeRange = start ? (end ? `${start} → ${end}` : start) : '';
    const title = [patient, medecin, timeRange, motif].filter(Boolean).join('\n');
    info.el.setAttribute('title', title);
  } catch (err) {
    // ignore formatting errors
  }

  info.el.addEventListener('contextmenu', (e) => {
    const statut = info.event.extendedProps?.statut;
    const css = getCssClass(statut);
    const isPending = css === 'rdv-pending';
    if (!isPending) return; // allow native browser menu for non-pending events
    e.preventDefault();
    selectedEvent.value = info.event;
    contextMenu.value.show(e);
  });

  // double-click left opens the details drawer
  info.el.addEventListener('dblclick', (e) => {
    e.preventDefault();
    selectedEvent.value = info.event;
    selectedRdvDetails.value = info.event.extendedProps;
    drawerVisible.value = true;
  });
};

const handleDatesSet = (info) => {
  currentRange.value = { start: info.start, end: info.end };
  loadEvents();
};

const calendarOptions = reactive({
  plugins: calendarPlugins,
  initialView: 'timeGridWeek',
  locale: 'fr',
  slotMinTime: '08:00:00',
  slotMaxTime: '18:00:00',
  slotDuration: '00:15:00',
  slotLabelInterval: '01:00',
  firstDay: 1,
  hiddenDays: [0],
  allDaySlot: false,
  nowIndicator: true,
  events: [],
  eventClick: handleEventClick,
  eventDidMount: handleEventMount,
  dateClick: handleDateClick,
  datesSet: handleDatesSet,
  height: 'auto', 
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'timeGridWeek,timeGridDay'
  },
  buttonText: {
    today: "Aujourd'hui",
    week: "Semaine",
    day: "Jour"
  }
});

watch(events, (next) => {
  calendarOptions.events = next;
});

// Refresh events when parent signals via `refreshKey`
watch(() => props.refreshKey, (newVal, oldVal) => {
  if (newVal === oldVal) return;
  loadEvents();
});
</script>

<template>
  <section class="flex flex-col gap-3 xs:gap-4 p-0.5 xs:p-1">
    <!-- Filtres – plus moderne et espacé -->
    <div class="flex flex-wrap items-center gap-3 xs:gap-4 rounded-xl xs:rounded-2xl bg-white p-3 xs:p-4 shadow-sm ring-1 ring-gray-200/70 dark:bg-gray-800 dark:ring-gray-700/60 dark:shadow-gray-900/20">
      <Select
        v-model="filters.medecinId"
        :options="medecinsOptions"
        optionLabel="name"
        optionValue="id"
        placeholder="Tous les médecins"
        showClear
        class="w-full xs:w-72 min-w-[160px] xs:min-w-[180px]"
        filter
      />
      <span class="p-input-icon-left w-full xs:w-72 min-w-[160px] xs:min-w-[180px]"> 
        <InputText
        icon   ="pi pi-search"
          v-model.trim="filters.patient"
          placeholder="Rechercher patient..."
          class="w-full"
        />
      </span>
    </div>

    <!-- Conteneur calendrier -->
    <div class="relative overflow-x-auto p-3 xs:p-4 rounded-xl xs:rounded-2xl border border-gray-200 bg-white shadow-md dark:border-gray-700 dark:bg-gray-800 dark:shadow-gray-950/30">
      <!-- Loading overlay plus doux -->
      <div
        v-if="loading"
        class="absolute inset-0 z-20 flex items-center justify-center bg-white/70 backdrop-blur-[1px] dark:bg-gray-900/70"
      >
        <ProgressSpinner strokeWidth="5" style="width: 3rem; height: 3rem" class="xs:style='width: 3.5rem; height: 3.5rem'" />
      </div>

        <!-- Indicateur central lorsque aucun événement n'est présent -->
      <div v-if="!loading && events.length === 0" class="absolute inset-0 z-10 flex items-center justify-center pointer-events-none">
        <div class="text-center">
          <div class="inline-flex items-center justify-center w-16 xs:w-20 h-16 xs:h-20 rounded-full bg-primary-50 dark:bg-primary-900/30 border-4 border-primary-200 dark:border-primary-700 text-primary-600 text-xl xs:text-2xl shadow-md">
            <i class="pi pi-calendar"></i>
          </div>
          <div class="mt-2 xs:mt-3 text-base xs:text-lg font-medium text-surface-700 dark:text-surface-300">Aucun rendez-vous</div>
          <div class="text-xs xs:text-sm text-surface-600 dark:text-surface-400">Changez les filtres ou sélectionnez une autre plage</div>
        </div>
      </div>

      <FullCalendar ref="calendarRef" :options="calendarOptions">
        <template v-slot:eventContent="arg">
          <!-- Simplified: only show patient name in the event. Full details are available on hover (native tooltip) -->
          <div class="event-inner">
            <div class="event-main">
              <div class="event-header">
                <strong>{{ arg.event.extendedProps.patientName || 'Patient' }}</strong>
              </div>
            </div>
          </div>
        </template>
      </FullCalendar>

      <ContextMenu ref="contextMenu" :model="menuItems" />
      <DetailsRdv v-model:visible="drawerVisible" :rdv="selectedRdvDetails" />
    </div>
  </section>
</template>

<style scoped>
/* ──────────────────────────────────────────────
   Personnalisation FullCalendar via variables CSS (v6)
   https://fullcalendar.io/docs/css-customization
   ────────────────────────────────────────────── */

.fc {
  --fc-border-color: theme('colors.gray.200');
  --fc-today-bg-color: theme('colors.blue.50');
  --fc-now-indicator-color: theme('colors.red.500');
  --fc-event-bg-color: theme('colors.blue.600');
  --fc-event-border-color: theme('colors.blue.700');
  --fc-event-text-color: white;
  --fc-daygrid-event-dot-width: 8px;

  @apply font-sans text-sm;
}

:deep(.app-dark .fc), :deep([class*="app-dark"] .fc) {
  --fc-border-color: theme('colors.gray.700');
  --fc-today-bg-color: theme('colors.blue.950');
  --fc-now-indicator-color: theme('colors.red.400');
  --fc-page-bg-color: theme('colors.gray.800');

}

/* Événements plus lisibles et modernes (apply to FullCalendar DOM via deep selector) */
:deep(.fc-event) {
  @apply rounded-md shadow-sm border border-opacity-60 overflow-hidden transition-all duration-150 hover:shadow-md hover:scale-[1.02] hover:z-10;
  padding: 2px 6px !important;
  font-weight: 500;
  line-height: 1.3; 
}

/* Cursor pointer on hover for events */
:deep(.fc-event:hover) {
  cursor: pointer;
}
 

/* Status-specific colors (matched with useRdvStatus composable) */
:deep(.fc-event.rdv-pending) {
  background-color: #2563eb !important;
  border-color: #1d4ed8 !important;
  color: #ffffff !important;
}

:deep(.fc-event.rdv-validated) {
  background-color: #16a34a !important;
  border-color: #15803d !important;
  color: #ffffff !important;
}

:deep(.fc-event.rdv-postponed) {
  background-color: #eab308 !important;
  border-color: #d97706 !important;
  color: #000000 !important;
}

:deep(.fc-event.rdv-cancelled) {
  background-color: #dc2626 !important;
  border-color: #b91c1c !important;
  color: #ffffff !important;
}
 
</style>