<script setup>
import FullCalendar from '@fullcalendar/vue3';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import Select from 'primevue/select';
import MultiSelect from 'primevue/multiselect';
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
  refreshKey: { type: Number, default: 0 },
  lockedMedecinId: { type: Number, default: null },
  medecinReadonly: { type: Boolean, default: false }
});

const emit = defineEmits(['request-create', 'request-validate', 'request-cancel', 'request-report', 'request-sms-reminder', 'request-sms-schedule']);

const calendarPlugins = [timeGridPlugin, interactionPlugin];

const events = ref([]);
const loading = ref(false);
const statusOptions = [
  { label: 'En attente', value: 'pending' },
  { label: 'Validé', value: 'validated' },
  { label: 'Reporté', value: 'postponed' },
  { label: 'Annulé', value: 'cancelled' }
];
const filters = reactive({
  medecinId: props.lockedMedecinId,
  patient: '',
  statuses: ['pending', 'validated', 'postponed']
});
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

const extractStatusValue = (rdv) => {
  const raw = rdv?.statut ?? rdv?.status ?? rdv?.statusValue ?? rdv?.etat ?? rdv?.state;
  if (raw === null || raw === undefined) return 0;
  const normalized = String(raw).trim().toLowerCase();

  if (normalized === '1' || normalized.includes('valid')) return 1;
  if (normalized === '-1' || normalized.includes('report') || normalized.includes('postpon')) return -1;
  if (normalized === '-2' || normalized.includes('annul') || normalized.includes('cancel')) return -2;
  if (normalized === '0' || normalized.includes('attente') || normalized.includes('pending')) return 0;

  const numeric = Number(normalized);
  return Number.isFinite(numeric) ? numeric : 0;
};

const getStatusCssClass = (rdv) => getCssClass(extractStatusValue(rdv));

const getStatusKey = (rdv) => {
  const cssClass = getStatusCssClass(rdv);
  if (cssClass === 'rdv-validated') return 'validated';
  if (cssClass === 'rdv-postponed') return 'postponed';
  if (cssClass === 'rdv-cancelled') return 'cancelled';
  return 'pending';
};

const menuItems = [
  { label: 'Valider', icon: 'pi pi-check', command: () => { if (selectedEvent.value) { emit('request-validate', selectedEvent.value.extendedProps); } } },
  { label: 'Reporter', icon: 'pi pi-calendar-minus', command: () => { if (selectedEvent.value) { emit('request-report', selectedEvent.value.extendedProps); } } },
  { label: 'Annuler', icon: 'pi pi-times', command: () => { if (selectedEvent.value) { emit('request-cancel', selectedEvent.value.extendedProps); } } },
  { label: 'Envoyer rappel SMS', icon: 'pi pi-send', command: () => { if (selectedEvent.value) { emit('request-sms-reminder', selectedEvent.value.extendedProps); } } },
  { label: 'Programmer rappel auto', icon: 'pi pi-clock', command: () => { if (selectedEvent.value) { emit('request-sms-schedule', selectedEvent.value.extendedProps); } } }
];

const loadEvents = async (force = false) => {
  if (!props.api?.fetchEvents) return;
  const start = currentRange.value?.start;
  const end = currentRange.value?.end;
  if (!start || !end) return;
  const selectedStatuses = Array.isArray(filters.statuses) ? [...filters.statuses].sort().join(',') : '';
  const fetchKey = `${start.toISOString()}_${end.toISOString()}_${filters.medecinId ?? 'all'}_${filters.patient ?? ''}_${selectedStatuses}`;
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

    const selectedStatusSet = new Set(filters.statuses || []);
    const filteredPayload = (Array.isArray(payload) ? payload : []).filter((rdv) => selectedStatusSet.has(getStatusKey(rdv)));

    events.value = filteredPayload.map(rdv => ({
      id: rdv.id,
      title: `${rdv.patientName || 'Patient'} — ${rdv.medecinName || 'Médecin'}`,
      start: rdv.start,
      end: rdv.end,
      extendedProps: rdv,
      classNames: [getStatusCssClass(rdv)]
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
    const css = getStatusCssClass(info.event.extendedProps || {});
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

watch(
  () => [filters.medecinId, filters.patient, [...(filters.statuses || [])].sort().join(',')],
  () => {
    loadEvents(true);
  }
);

watch(
  () => props.lockedMedecinId,
  (next) => {
    if (next) {
      filters.medecinId = next;
    }
  },
  { immediate: true }
);

// Refresh events when parent signals via `refreshKey`
watch(() => props.refreshKey, (newVal, oldVal) => {
  if (newVal === oldVal) return;
  loadEvents();
});

// Recharge le calendrier après chaque action (création, validation, annulation, report)
const reloadOnAction = () => loadEvents(true);

// Écoute les événements émis par le parent
defineExpose({ reloadOnAction });
</script>

<template>
  <section class="weekly-view-page flex flex-col gap-3 xs:gap-4 p-0.5 xs:p-1">
    <!-- Filtres – plus moderne et espacé -->
    <div data-tour="agenda-rdv.scope" class="flex flex-row  items-center gap-3 xs:gap-4 rounded-xl xs:rounded-2xl bg-white p-3 xs:p-4 shadow-sm ring-1 ring-gray-200/70 dark:bg-gray-800 dark:ring-gray-700/60 dark:shadow-gray-900/20">
      <Select
        v-model="filters.medecinId"
        :options="medecinsOptions"
        optionLabel="name"
        optionValue="id"
        :placeholder="medecinReadonly ? 'Médecin connecté' : 'Tous les médecins'"
        :showClear="!medecinReadonly"
        class="w-full xs:w-72 min-w-[160px] xs:min-w-[180px]"
        :filter="!medecinReadonly"
        :disabled="medecinReadonly"
      />
      <span class="p-input-icon-left w-full xs:w-72 min-w-[160px] xs:min-w-[180px]"> 
        <InputText
        icon   ="pi pi-search"
          v-model.trim="filters.patient"
          placeholder="Rechercher patient..."
          class="w-full"
        />
      </span>
      <MultiSelect
        v-model="filters.statuses"
        :options="statusOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Statuts"
        display="chip"
        class="w-full xs:w-80 min-w-[180px]"
      />
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

.weekly-view-page .fc {
  --fc-border-color: theme('colors.gray.200');
  --fc-today-bg-color: theme('colors.blue.50');
  --fc-now-indicator-color: theme('colors.red.500');
  --fc-event-bg-color: #ffffff;
  --fc-event-border-color: #cbd5e1;
  --fc-event-text-color: #1e293b;
  --fc-daygrid-event-dot-width: 8px;

  @apply font-sans text-sm;
}

.weekly-view-page :deep(.app-dark .fc), .weekly-view-page :deep([class*="app-dark"] .fc) {
  --fc-border-color: theme('colors.gray.700');
  --fc-today-bg-color: theme('colors.blue.950');
  --fc-now-indicator-color: theme('colors.red.400');
  --fc-page-bg-color: theme('colors.gray.800');

}

/* Événements plus lisibles et modernes (apply to FullCalendar DOM via deep selector) */
.weekly-view-page :deep(.fc-event) {
  @apply rounded-md shadow-sm border border-opacity-60 overflow-hidden transition-all duration-150 hover:shadow-md hover:scale-[1.02] hover:z-10;
  padding: 2px 6px !important;
  font-weight: 500;
  line-height: 1.3; 
  background-color: #ffffff !important;
  color: #1e293b !important;
}

.weekly-view-page :deep(.fc-event .fc-event-main),
.weekly-view-page :deep(.fc-event .fc-event-main-frame),
.weekly-view-page :deep(.fc-event .fc-event-title),
.weekly-view-page :deep(.fc-event .fc-event-time) {
  color: inherit !important;
}

/* Cursor pointer on hover for events */
.weekly-view-page :deep(.fc-event:hover) {
  cursor: pointer;
}

.weekly-view-page :deep(.fc-event .event-header strong) {
  color: inherit !important;
}
 

/* Status-specific colors (matched with useRdvStatus composable) */
.weekly-view-page :deep(.fc-event.rdv-pending) {
  border-color: #1d4ed8 !important;
  border-left: 4px solid #1d4ed8 !important;
  color: #1d4ed8 !important;
}

.weekly-view-page :deep(.fc-event.rdv-pending .fc-event-main) {
  color: #1d4ed8 !important;
}


.weekly-view-page :deep(.fc-event.rdv-validated) {
  border-color: #15803d !important;
  border-left: 4px solid #15803d !important;
  color: #15803d !important;
}

.weekly-view-page :deep(.fc-event.rdv-validated .fc-event-main) {
  color: #15803d !important;
}

.weekly-view-page :deep(.fc-event.rdv-postponed) {
  border-color: #d97706 !important;
  border-left: 4px solid #d97706 !important;
  color: #b45309 !important;
}

.weekly-view-page :deep(.fc-event.rdv-postponed .fc-event-main) {
  color: #b45309 !important;
}

.weekly-view-page :deep(.fc-event.rdv-cancelled) {
  border-color: #b91c1c !important;
  border-left: 4px solid #b91c1c !important;
  color: #b91c1c !important;
}

.weekly-view-page :deep(.fc-event.rdv-cancelled .fc-event-main) {
  color: #b91c1c !important;
}
 
.app-dark .weekly-view-page :deep(.fc-event) {
  background-color: #030d20 !important;
  color: #cbd5e1 !important;
}

.app-dark .weekly-view-page :deep(.fc-event .fc-event-main),
.app-dark .weekly-view-page :deep(.fc-event .fc-event-main-frame) {
  background-color: #030d20 !important;
  color: #cbd5e1 !important;
}

</style>