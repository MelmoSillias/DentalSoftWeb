<template>
	<section class="flex flex-col gap-4 rounded-2xl bg-surface-0 p-5 shadow-sm dark:bg-surface-900 dark:shadow-none dark:ring-1 dark:ring-surface-700">
		<Toast ref="toast" />
		<div data-tour="agenda-events.header" class="flex flex-wrap items-center justify-between gap-4 border-b border-surface-200 pb-3 dark:border-surface-700">
			<div class="space-y-1">
				<h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-200">Gestion des Evenements</h2>
				<Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" />
			</div> 
			<div data-tour="agenda-events.create" class=" "> 
				<Button label="Nouvel Événement" icon="pi pi-plus" class="p-button-primary" @click="showForm = true" />
			</div>
		</div>

		<div class="card shadow mb-4">
			
			<div class="card-body">
				<div id="calendar-holder" data-tour="agenda-events.calendar">
					<div data-tour="agenda-events.status">
						<FullCalendar :options="calendarOptions" ref="calendarRef" />
					</div>
				</div>
			</div>
		</div>

		<EventForm :visible="showForm" @create="handleCreate" @hide="showForm=false" />

		<div data-tour="agenda-events.actions">
			<EventActions :visible="actionsVisible" :eventId="selectedEventId" @delete="handleDelete" @validate="handleValidate" @hide="actionsVisible=false" />
		</div>
 
	</section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import interactionPlugin from '@fullcalendar/interaction'

import Button from 'primevue/button'
import Toast from 'primevue/toast'

import EventForm from '@/components/agenda/EventForm.vue'
import EventActions from '@/components/agenda/EventActions.vue'
import { useEvents } from '@/composables/useEvents'
import { GUIDED_TOUR_START_EVENT } from '@/tours'
import { createAgendaEvenementsTour } from '@/tours/agendaEvenementsTour'
import { startTourGuide } from '@/tours/tourGuideClient'

const { events, fetchEvents, createEvent, deleteEvent, validateEvent } = useEvents()

const calendarRef = ref(null)
const showForm = ref(false)
const actionsVisible = ref(false)
const selectedEventId = ref(null)
const toast = ref(null)
const isGuidedTourStarting = ref(false)

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [
	{ label: 'Agenda' },
	{ label: 'Evenements', class: 'font-semibold' }
];

const firstEventId = computed(() => {
	if (!Array.isArray(events.value) || !events.value.length) return null
	return events.value[0]?.id ?? null
})

const hasOpenDialogs = computed(() => showForm.value || actionsVisible.value)

const calendarOptions = {
	plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
	initialView: 'dayGridMonth',
	editable: true,
	locale: 'fr',
	events: (info, successCallback, failureCallback) => {
		// ensure events are loaded
		fetchEvents().then(() => successCallback(events.value)).catch(failureCallback)
	},
	headerToolbar: {
		left: 'prev,next today',
		center: 'title',
		right: 'dayGridMonth,timeGridWeek'
	},
	eventDidMount: (info) => {
		// set statut style
		if (info.event.extendedProps.statut === 1) {
			info.el.style.backgroundColor = '#1cc88a'
		}
		// right click to open actions
		info.el.addEventListener('contextmenu', (e) => {
			e.preventDefault()
			selectedEventId.value = info.event.id
			actionsVisible.value = true
		})
	}
}

onMounted(() => {
	// initial fetch
	fetchEvents()
	window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest)
})

onBeforeUnmount(() => {
	window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest)
	resetTourDialogs()
})

const resetTourDialogs = () => {
	showForm.value = false
	actionsVisible.value = false
	selectedEventId.value = null
}

const openTourActionsDialog = () => {
	if (!firstEventId.value) return
	selectedEventId.value = firstEventId.value
	actionsVisible.value = true
}

const handleGuidedTourRequest = async (event) => {
	if (event?.detail?.routeName !== 'agenda-evenements' || isGuidedTourStarting.value) {
		return
	}

	if (hasOpenDialogs.value) {
		toast.value?.add({
			severity: 'warn',
			summary: 'Aide guidee',
			detail: 'Fermez les fenetres ouvertes avant de lancer le tour.',
			life: 3000
		})
		return
	}

	isGuidedTourStarting.value = true

	try {
		await fetchEvents()
		resetTourDialogs()
		await nextTick()

		const steps = createAgendaEvenementsTour({
			hasEvents: Array.isArray(events.value) && events.value.length > 0,
			openActionsDialog: openTourActionsDialog,
			closeAllDialogs: resetTourDialogs
		})

		await startTourGuide({
			group: 'agenda-evenements',
			steps,
			onAfterExit: resetTourDialogs,
			onFinish: resetTourDialogs
		})
	} catch (error) {
		console.error('Erreur lancement guided tour agenda evenements', error)
		toast.value?.add({
			severity: 'error',
			summary: 'Aide guidee',
			detail: 'Impossible de lancer le tour de la page evenements.',
			life: 3000
		})
	} finally {
		isGuidedTourStarting.value = false
	}
}

async function handleCreate(payload) {
	try {
		const res = await createEvent(payload)
		if (res && res.success) {
			showForm.value = false
			toast.value.add({ severity: 'success', summary: 'Succès', detail: 'Événement ajouté avec succès.' })
			calendarRef.value.getApi().refetchEvents()
		} else {
			toast.value.add({ severity: 'error', summary: 'Erreur', detail: res?.message || 'Erreur lors de l’ajout de l’événement.' })
		}
	} catch (err) {
		toast.value.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Erreur lors de l’ajout de l’événement.' })
	}
}

async function handleDelete(id) {
	try {
		const res = await deleteEvent(id)
		actionsVisible.value = false
		if (res && res.success) {
			toast.value.add({ severity: 'success', summary: 'Succès', detail: 'Événement supprimé avec succès.' })
			calendarRef.value.getApi().refetchEvents()
		} else {
			toast.value.add({ severity: 'error', summary: 'Erreur', detail: res?.message || 'Erreur lors de la suppression.' })
		}
	} catch (err) {
		actionsVisible.value = false
		toast.value.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Erreur lors de la suppression.' })
	}
}

async function handleValidate(id) {
	try {
		const res = await validateEvent(id)
		actionsVisible.value = false
		if (res && res.success) {
			toast.value.add({ severity: 'success', summary: 'Succès', detail: 'Événement validé avec succès.' })
			calendarRef.value.getApi().refetchEvents()
		} else {
			toast.value.add({ severity: 'error', summary: 'Erreur', detail: res?.message || 'Erreur lors de la validation.' })
		}
	} catch (err) {
		actionsVisible.value = false
		toast.value.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Erreur lors de la validation.' })
	}
}
</script>

<style scoped>
/* basic styles to mimic Twig layout */
#calendar-holder .fc { background-color: var(--fc-page-bg-color, rgba(255,255,255,0.95)); border-radius: 0.5rem; padding: 1rem }

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

:deep(.app-dark .fc), :deep([class*="app-dark"] .fc) {
	--fc-border-color: theme('colors.gray.700');
	--fc-today-bg-color: theme('colors.sky.950');
	--fc-now-indicator-color: theme('colors.red.400');
	--fc-page-bg-color: theme('colors.gray.800');
}

:deep(.fc-event) {
	@apply rounded-md shadow-sm border border-opacity-60 overflow-hidden transition-all duration-150 hover:shadow-md hover:scale-[1.02] hover:z-10;
	padding: 2px 6px !important;
	font-weight: 500;
	line-height: 1.3;
}

:deep(.fc-event:hover) { cursor: pointer; }

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
</style>
