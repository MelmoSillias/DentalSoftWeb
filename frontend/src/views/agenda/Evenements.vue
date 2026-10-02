<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';

import EventForm from '@/components/agenda/EventForm.vue';
import EventActions from '@/components/agenda/EventActions.vue';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import { useEvents } from '@/composables/useEvents';
import { getFullCalendarResponsiveOptions, useFullCalendarResponsive } from '@/composables/useFullCalendarResponsive';
import { useGuidedTour } from '@/composables/useGuidedTour';

const { events, fetchEvents, createEvent, deleteEvent, validateEvent } = useEvents();

const calendarRef = ref(null);
const showForm = ref(false);
const actionsVisible = ref(false);
const selectedEventId = ref(null);
const toast = useToast();

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Agenda' }, { label: 'Événements', class: 'font-semibold' }];

const firstEventId = computed(() => {
    if (!Array.isArray(events.value) || !events.value.length) return null;
    return events.value[0]?.id ?? null;
});

const hasOpenDialogs = computed(() => showForm.value || actionsVisible.value);

const initialResponsive = getFullCalendarResponsiveOptions('events', {
    isMobile: typeof window !== 'undefined' && window.matchMedia('(max-width: 639.98px)').matches,
    isNarrow: typeof window !== 'undefined' && window.matchMedia('(max-width: 399.98px)').matches
});

const calendarOptions = reactive({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    editable: true,
    locale: 'fr',
    stickyHeaderDates: true,
    events: (info, successCallback, failureCallback) => {
        fetchEvents()
            .then(() => successCallback(events.value))
            .catch(failureCallback);
    },
    ...initialResponsive,
    eventDidMount: (info) => {
        if (info.event.extendedProps.statut === 1) {
            info.el.style.backgroundColor = '#1cc88a';
        }
        info.el.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            selectedEventId.value = info.event.id;
            actionsVisible.value = true;
        });
    }
});

useFullCalendarResponsive(calendarRef, 'events', (opts) => {
    Object.assign(calendarOptions, opts);
});

onMounted(() => {
    fetchEvents();
});

onBeforeUnmount(() => {
    resetTourDialogs();
});

const resetTourDialogs = () => {
    showForm.value = false;
    actionsVisible.value = false;
    selectedEventId.value = null;
};

const openTourActionsDialog = () => {
    if (!firstEventId.value) return;
    selectedEventId.value = firstEventId.value;
    actionsVisible.value = true;
};

const prepareGuidedTourDemo = async () => {
    await fetchEvents();
    resetTourDialogs();
    await nextTick();
};

useGuidedTour({
    routeName: 'agenda-evenements',
    hasOpenDialogs: () => hasOpenDialogs.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: resetTourDialogs,
    getStepContext: () => ({
        hasEvents: Array.isArray(events.value) && events.value.length > 0,
        openActionsDialog: openTourActionsDialog,
        closeAllDialogs: resetTourDialogs
    }),
    dialogsMessage: 'Fermez les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour de la page evenements.'
});

async function handleCreate(payload) {
    try {
        const res = await createEvent(payload);
        if (res && res.success) {
            showForm.value = false;
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Événement ajouté avec succès.' });
            calendarRef.value.getApi().refetchEvents();
        } else {
            toast.add({ severity: 'error', summary: 'Erreur', detail: res?.message || 'Erreur lors de l’ajout de l’événement.' });
        }
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Erreur lors de l’ajout de l’événement.' });
    }
}

async function handleDelete(id) {
    try {
        const res = await deleteEvent(id);
        actionsVisible.value = false;
        if (res && res.success) {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Événement supprimé avec succès.' });
            calendarRef.value.getApi().refetchEvents();
        } else {
            toast.add({ severity: 'error', summary: 'Erreur', detail: res?.message || 'Erreur lors de la suppression.' });
        }
    } catch (err) {
        actionsVisible.value = false;
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Erreur lors de la suppression.' });
    }
}

async function handleValidate(id) {
    try {
        const res = await validateEvent(id);
        actionsVisible.value = false;
        if (res && res.success) {
            toast.add({ severity: 'success', summary: 'Succès', detail: 'Événement validé avec succès.' });
            calendarRef.value.getApi().refetchEvents();
        } else {
            toast.add({ severity: 'error', summary: 'Erreur', detail: res?.message || 'Erreur lors de la validation.' });
        }
    } catch (err) {
        actionsVisible.value = false;
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Erreur lors de la validation.' });
    }
}
</script>

<template>
    <PageShell class="evenements-page">
        <template #header>
            <PageHeader
                title="Gestion des Événements"
                subtitle="Calendrier des événements du cabinet"
                icon="pi pi-calendar-plus"
                tour-id="agenda-events.header"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            >
                <template #actions>
                    <div data-tour="agenda-events.create">
                        <Button label="Nouvel Événement" icon="pi pi-plus" @click="showForm = true" />
                    </div>
                </template>
            </PageHeader>
        </template>

        <PageSection tour-id="agenda-events.calendar">
            <div id="calendar-holder" class="page-table-scroll p-2 sm:p-3" data-tour="agenda-events.status">
                <FullCalendar :options="calendarOptions" ref="calendarRef" />
            </div>
        </PageSection>

        <EventForm :visible="showForm" @create="handleCreate" @hide="showForm = false" />

        <div data-tour="agenda-events.actions">
            <EventActions :visible="actionsVisible" :eventId="selectedEventId" @delete="handleDelete" @validate="handleValidate" @hide="actionsVisible = false" />
        </div>
    </PageShell>
</template>

<style scoped>
.evenements-page {
    min-height: calc(100dvh - 6rem);
}

/* basic styles to mimic Twig layout */
#calendar-holder .fc {
    background-color: var(--fc-page-bg-color, rgba(255, 255, 255, 0.95));
    border-radius: 0.5rem;
    padding: 0.5rem;
}

@media (min-width: 768px) {
    #calendar-holder .fc {
        padding: 1rem;
    }
}

/* FullCalendar: enhance default and support dark mode (matches WeeklyView.vue) */
:deep(.fc) {
    --fc-border-color: theme('colors.gray.200');
    --fc-today-bg-color: theme('colors.blue.50');
    --fc-now-indicator-color: theme('colors.red.500');
    --fc-event-bg-color: theme('colors.blue.600');
    --fc-event-border-color: theme('colors.blue.700');
    --fc-event-text-color: white;
    --fc-daygrid-event-dot-width: 8px;

    @apply font-sans text-sm;
}

:deep(.app-dark .fc),
:deep([class*='app-dark'] .fc) {
    --fc-border-color: theme('colors.gray.700');
    --fc-today-bg-color: theme('colors.sky.950');
    --fc-now-indicator-color: theme('colors.red.400');
    --fc-page-bg-color: theme('colors.gray.800');
}

:deep(.fc-toolbar) {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 0.75rem;
    align-items: center;
    justify-content: space-between;
}

:deep(.fc-toolbar-chunk) {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem;
}

:deep(.fc-toolbar-title) {
    @apply text-base sm:text-lg font-semibold;
    line-height: 1.25;
    text-align: center;
}

:deep(.fc-button) {
    @apply text-xs sm:text-sm;
    margin: 0;
}

:deep(.fc-col-header-cell-cushion) {
    font-size: 0.8rem;
    padding: 0.4rem 0.15rem;
}

:deep(.fc-daygrid-day-number) {
    font-size: 0.8rem;
    padding: 0.2rem;
}

:deep(.fc-event) {
    @apply rounded-md shadow-sm border border-opacity-60 overflow-hidden transition-all duration-150 hover:shadow-md hover:z-10;
    padding: 2px 6px !important;
    font-weight: 500;
    line-height: 1.3;
}

:deep(.fc-event:hover) {
    cursor: pointer;
}

:deep(.fc-event .fc-event-title) {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Status-specific colors (keeps parity with WeeklyView) */
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

@media (max-width: 639.98px) {
    #calendar-holder .fc {
        padding: 0.35rem;
    }

    :deep(.fc-toolbar) {
        flex-direction: column;
        align-items: stretch;
        margin-bottom: 0.75rem;
        gap: 0.4rem;
    }

    :deep(.fc-toolbar-chunk) {
        justify-content: center;
        width: 100%;
    }

    :deep(.fc-toolbar-title) {
        font-size: 0.95rem;
        width: 100%;
    }

    :deep(.fc .fc-button) {
        font-size: 0.72rem;
        padding: 0.3rem 0.45rem;
    }

    :deep(.fc-col-header-cell-cushion) {
        font-size: 0.65rem;
        padding: 0.25rem 0.05rem;
        white-space: nowrap;
    }

    :deep(.fc-daygrid-day-number) {
        font-size: 0.7rem;
        padding: 0.15rem;
    }

    :deep(.fc-daygrid-day-frame) {
        min-height: 4.5rem;
    }

    :deep(.fc-event) {
        padding: 1px 3px !important;
        font-size: 0.62rem !important;
        margin-bottom: 0.1rem !important;
    }

    :deep(.fc-event:hover) {
        transform: none;
    }

    :deep(.fc-daygrid-more-link) {
        font-size: 0.65rem;
        margin-top: 0.1rem;
    }

    :deep(.fc-timegrid-axis) {
        width: 2.25rem !important;
        min-width: 2.25rem !important;
    }

    :deep(.fc-timegrid-slot-label-cushion) {
        font-size: 0.62rem;
    }
}

@media (max-width: 399.98px) {
    :deep(.fc-toolbar-title) {
        font-size: 0.85rem;
    }

    :deep(.fc .fc-button) {
        font-size: 0.65rem;
        padding: 0.25rem 0.35rem;
    }

    :deep(.fc-col-header-cell-cushion) {
        font-size: 0.58rem;
    }

    :deep(.fc-daygrid-day-number) {
        font-size: 0.65rem;
    }

    :deep(.fc-daygrid-day-frame) {
        min-height: 3.75rem;
    }

    :deep(.fc-event) {
        font-size: 0.55rem !important;
        padding: 0 2px !important;
        border-radius: 0.2rem !important;
    }

    :deep(.fc-timegrid-axis) {
        width: 1.85rem !important;
        min-width: 1.85rem !important;
    }

    :deep(.fc-timeGridWeek-view .fc-scrollgrid) {
        min-width: 28rem;
    }

    :deep(.fc-view-harness) {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}
</style>
