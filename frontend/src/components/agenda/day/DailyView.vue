<script setup>
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import ProgressSpinner from 'primevue/progressspinner';
import ScrollPanel from 'primevue/scrollpanel';
import { computed, onMounted, ref, watch } from 'vue';
import DateChooser from './DateChooser.vue';
import ScheduleTable from './ScheduleTable.vue';
import StatsCards from './StatsCards.vue';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({
    medecins: {
        type: Array,
        default: () => []
    },
    api: {
        type: Object,
        required: true
    },
    refreshKey: {
        type: Number,
        default: 0
    },
    lockedMedecinId: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['request-create', 'request-validate', 'request-cancel', 'request-report']);

const auth = useAuthStore();
const selectedDate = ref(new Date());
const zoom = ref(100);
const dayEvents = ref([]);
const stats = ref({ pending: 0, validated: 0, postponed: 0, cancelled: 0 });
const loadingDay = ref(false);
const loadingStats = ref(false);
const openingTime = ref('08:00');
const closingTime = ref('18:00');

const medecinsOptions = computed(() => (Array.isArray(props.medecins) ? props.medecins : props.medecins?.value || []));

const computeDateFromSlot = (slotMinutes) => {
    const base = new Date(selectedDate.value);
    const hours = Math.floor(slotMinutes / 60);
    const minutes = slotMinutes % 60;
    base.setHours(hours, minutes, 0, 0);
    return base;
};

const loadOpeningHours = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(auth.token);
        openingTime.value = settings?.openingTime || '08:00';
        closingTime.value = settings?.closingTime || '18:00';
    } catch (error) {
        console.error('Erreur chargement horaires journaliers:', error);
    }
};

const refreshDay = async () => {
    if (!props.api?.fetchEventsByDay) return;
    loadingDay.value = true;
    try {
        dayEvents.value = await props.api.fetchEventsByDay(selectedDate.value, props.lockedMedecinId);
    } catch (err) {
        console.error('Erreur chargement agenda du jour:', err);
        dayEvents.value = [];
    } finally {
        loadingDay.value = false;
    }
};

const refreshStats = async () => {
    if (!props.api?.fetchStats) return;
    loadingStats.value = true;
    try {
        stats.value = await props.api.fetchStats(selectedDate.value, props.lockedMedecinId);
    } catch (err) {
        console.error('Erreur chargement statistiques rendez-vous:', err);
        stats.value = { pending: 0, validated: 0, postponed: 0, cancelled: 0 };
    } finally {
        loadingStats.value = false;
    }
};

const toDayKey = (value) => {
    if (!(value instanceof Date) || Number.isNaN(value.getTime())) return '';
    return value.toISOString().slice(0, 10);
};

const onDateChange = (val) => {
    const next = val instanceof Date ? val : new Date(val);
    if (toDayKey(next) === toDayKey(selectedDate.value)) return;
    selectedDate.value = next;
};

const onCreate = ({ medecin, slot }) => {
    const start = computeDateFromSlot(slot.minutes);
    const end = new Date(start.getTime() + 30 * 60000);
    const effectiveMedecin = props.lockedMedecinId
        ? (medecinsOptions.value || []).find((m) => Number(m.id) === Number(props.lockedMedecinId)) || medecin
        : medecin;
    emit('request-create', { start, end, medecin: effectiveMedecin });
};

const onValidate = ({ rdv }) => emit('request-validate', rdv);
const onCancel = ({ rdv }) => emit('request-cancel', rdv);
const onReport = ({ rdv }) => emit('request-report', rdv);

const zoomIn = () => {
    zoom.value = Math.min(150, zoom.value + 10);
};

const zoomOut = () => {
    zoom.value = Math.max(50, zoom.value - 10);
};

onMounted(() => {
    loadOpeningHours();
});

watch(
    () => [selectedDate.value, props.refreshKey],
    () => {
        refreshDay();
        refreshStats();
    },
    { immediate: true }
);
</script>

<template>
    <section class="daily-view flex min-h-0 flex-1 flex-col gap-3 xs:gap-4">
        <div class="flex flex-shrink-0 flex-wrap items-center justify-between gap-3 xs:gap-4 rounded-xl bg-surface-0 p-2 xs:p-3 shadow-sm dark:bg-surface-900 dark:shadow-none dark:ring-1 dark:ring-surface-700">
            <DateChooser :modelValue="selectedDate" @update:modelValue="onDateChange" />
            <div class="flex items-center gap-1 xs:gap-2 text-xs xs:text-sm font-medium text-surface-700">
                <span>Zoom</span>
                <Button icon="pi pi-search-minus" size="small" text @click="zoomOut" />
                <Button icon="pi pi-search-plus" size="small" text @click="zoomIn" />
                <span class="font-semibold">{{ zoom }}%</span>
            </div>
        </div>

        <div class="flex-shrink-0 rounded-xl bg-surface-0 p-2 xs:p-3 shadow-sm dark:bg-surface-900 dark:shadow-none">
            <StatsCards :stats="stats" :loading="loadingStats" />
        </div>

        <Divider class="my-1 flex-shrink-0 xs:my-2" />

        <div class="daily-schedule-frame relative min-h-0 flex-1 overflow-hidden rounded-xl border border-surface-200 bg-surface-0 shadow-sm dark:border-surface-700 dark:bg-surface-900">
            <div v-if="loadingDay" class="absolute inset-0 z-10 flex items-center justify-center bg-surface-0/60 dark:bg-surface-900/60">
                <ProgressSpinner strokeWidth="4" style="width: 40px; height: 40px" />
            </div>
            <ScrollPanel class="daily-schedule-scroll h-full w-full">
                <div class="p-2 xs:p-3">
                    <ScheduleTable
                        :medecins="medecinsOptions"
                        :rdvs="dayEvents"
                        :zoom="zoom"
                        :opening-time="openingTime"
                        :closing-time="closingTime"
                        @create="onCreate"
                        @validate="onValidate"
                        @cancel="onCancel"
                        @report="onReport"
                    />
                </div>
            </ScrollPanel>
        </div>
    </section>
</template>

<style scoped>
.daily-schedule-frame {
    min-height: 18rem;
    height: calc(100dvh - 22rem);
}

.daily-schedule-scroll :deep(.p-scrollpanel-content) {
    padding: 0;
}

.daily-schedule-scroll :deep(.p-scrollpanel-wrapper),
.daily-schedule-scroll :deep(.p-scrollpanel-content) {
    height: 100%;
}
</style>
