<script setup>
import Breadcrumb from 'primevue/breadcrumb';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import DailyView from '@/components/agenda/day/DailyView.vue';
import StatusLegend from '@/components/agenda/shared/StatusLegend.vue';
import CancelRdvDialog from '@/components/agenda/shared/CancelRdvDialog.vue';
import CreateRdvDialog from '@/components/agenda/shared/CreateRdvDialog.vue';
import ReportRdvDialog from '@/components/agenda/shared/ReportRdvDialog.vue';
import ValidateRdvDialog from '@/components/agenda/shared/ValidateRdvDialog.vue';
import WeeklyView from '@/components/agenda/week/WeeklyView.vue';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAgendaRendezvousTour } from '@/tours/agendaRendezvousTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import { scheduleAppointmentReminderSms, sendAppointmentReminderSms } from '@/services/smsService';
import { useRdvApi } from '@/composables/useRdvApi';
import { useAuthStore } from '@/stores/auth';
import { addMinutes } from '@/utils/dateUtils';
import { useLayout } from '@/layout/composables/layout';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import cabinetConfig from '@/cabinetConfig';

const toast = useToast();
const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [
	{ label: 'Agenda' },
	{ label: 'Rendez-vous', class: 'font-semibold' }
];

const api = useRdvApi();
const auth = useAuthStore();
const medecinsList = computed(() => api.medecins?.value ?? api.medecins ?? []);
const isMedecinUser = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));

const normalizeText = (value) => String(value || '')
	.normalize('NFD')
	.replace(/[\u0300-\u036f]/g, '')
	.toLowerCase()
	.trim();

const connectedMedecinId = computed(() => {
	const user = auth.user || {};
	const options = medecinsList.value || [];
	const directId = Number(user.medecinId ?? user.medecin_id ?? user.medecin?.id ?? Number.NaN);
	if (Number.isFinite(directId)) {
		const found = options.find((m) => Number(m.id) === directId);
		if (found) return found.id;
	}

	const fullName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
	const candidates = [fullName, user.name, user.fullName, user.username].filter(Boolean).map(normalizeText);
	if (!candidates.length) return null;

	const foundByName = options.find((m) => {
		const label = normalizeText(m.name);
		return candidates.some((candidate) => candidate && (label === candidate || label.includes(candidate) || candidate.includes(label)));
	});

	return foundByName?.id ?? null;
});

const scopedMedecinsList = computed(() => {
	if (!isMedecinUser.value) return medecinsList.value;
	const id = connectedMedecinId.value;
	if (!id) return [];
	return (medecinsList.value || []).filter((m) => Number(m.id) === Number(id));
});
const activeIndex = ref('week');
const refreshKey = ref(0);
const weeklyViewRef = ref();
const actionLoading = ref(false);
const smsDialogVisible = ref(false);
const smsScheduleDialogVisible = ref(false);
const smsDraft = ref('');
const smsRdv = ref(null);
const smsScheduleHours = ref(24);
const smsScheduleOptions = ref([
	{ label: '24h avant', value: 24 },
	{ label: '12h avant', value: 12 },
	{ label: '2h avant', value: 2 }
]);
const smsLoading = ref(false);
const token = localStorage.getItem('token');
const isGuidedTourStarting = ref(false);



const dialogState = reactive({
	create: false,
	validate: false,
	cancel: false,
	report: false
});

const hasOpenDialogs = computed(() => (
	dialogState.create
	|| dialogState.validate
	|| dialogState.cancel
	|| dialogState.report
	|| smsDialogVisible.value
	|| smsScheduleDialogVisible.value
));

const createDefaults = reactive({
	start: new Date(),
	end: addMinutes(new Date(), 30),
	medecinId: null
});

const currentRdv = ref(null);

const notify = (detail, severity = 'success') => {
	toast.add({ severity, summary: 'Agenda', detail, life: 2500 });
};

const openCreate = (payload = {}) => {
	const start = payload.start ? new Date(payload.start) : new Date();
	const end = payload.end ? new Date(payload.end) : addMinutes(start, 30);
	createDefaults.start = start;
	createDefaults.end = end;
	createDefaults.medecinId = isMedecinUser.value
		? connectedMedecinId.value
		: (payload.medecin?.id ?? payload.medecinId ?? null);
	dialogState.create = true;
};

const submitCreate = async (payload) => {
	actionLoading.value = true;
	try {
		await api.createRdv(payload);
		notify('Rendez-vous créé');
		refreshKey.value += 1;
		nextTick(() => {
			weeklyViewRef.value?.reloadOnAction?.();
		});
	} catch (err) {
		notify("Création impossible", 'error');
		console.error(err);
	} finally {
		actionLoading.value = false;
	}
};

const openValidate = (rdv) => {
	currentRdv.value = {
		...rdv,
		medecinId: isMedecinUser.value ? connectedMedecinId.value : rdv?.medecinId
	};
	dialogState.validate = true;
};

const confirmValidate = async ({ id, medecinId }) => {
	actionLoading.value = true;
	try {
		const effectiveMedecinId = isMedecinUser.value ? connectedMedecinId.value : medecinId;
		await api.validateRdv(id, effectiveMedecinId, { createConsultation: !isMedecinUser.value });
		notify('Rendez-vous validé');
		refreshKey.value += 1;
		nextTick(() => {
			weeklyViewRef.value?.reloadOnAction?.();
		});
	} catch (err) {
		notify('Validation impossible', 'error');
		console.error(err);
	} finally {
		actionLoading.value = false;
	}
};

const openCancel = (rdv) => {
	currentRdv.value = rdv;
	dialogState.cancel = true;
};

const confirmCancel = async ({ id }) => {
	actionLoading.value = true;
	try {
		await api.cancelRdv(id);
		notify('Rendez-vous annulé');
		refreshKey.value += 1;
		nextTick(() => {
			weeklyViewRef.value?.reloadOnAction?.();
		});
	} catch (err) {
		notify('Annulation impossible', 'error');
		console.error(err);
	} finally {
		actionLoading.value = false;
	}
};

const openReport = (rdv) => {
	currentRdv.value = {
		...rdv,
		medecinId: isMedecinUser.value ? connectedMedecinId.value : rdv?.medecinId
	};
	dialogState.report = true;
};

const openSmsReminder = (rdv) => {
	smsRdv.value = rdv;
	const patientName = rdv?.patientName || 'Patient';
	const when = rdv?.start ? new Date(rdv.start) : null;
	const dateStr = when ? when.toLocaleDateString('fr-FR') : '';
	const timeStr = when ? when.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '';
	smsDraft.value = `Rappel : rendez-vous le ${dateStr} à ${timeStr}. Cabinet ${cabinetConfig.smsCabinetName}.`.trim();
	smsDialogVisible.value = true;
};

const sendSmsReminder = async () => {
	if (!smsRdv.value?.id || !smsDraft.value?.trim()) return;
	smsLoading.value = true;
	try {
		const result = await sendAppointmentReminderSms(smsRdv.value.id, { message: smsDraft.value }, token);
		if (!result?.success) throw new Error(result?.error || 'Erreur envoi SMS');
		notify('Rappel SMS ajouté à la file');
		smsDialogVisible.value = false;
	} catch (err) {
		notify('Envoi SMS impossible', 'error');
		console.error(err);
	} finally {
		smsLoading.value = false;
	}
};

const openScheduleReminder = (rdv) => {
	smsRdv.value = rdv;
	smsScheduleHours.value = 24;
	smsScheduleDialogVisible.value = true;
};

const scheduleSmsReminder = async () => {
	if (!smsRdv.value?.id) return;
	smsLoading.value = true;
	try {
		const result = await scheduleAppointmentReminderSms(smsRdv.value.id, { hoursBefore: smsScheduleHours.value }, token);
		if (!result?.success) throw new Error(result?.error || 'Erreur programmation');
		notify(`Rappel SMS programmé (${smsScheduleHours.value}h avant)`);
		smsScheduleDialogVisible.value = false;
	} catch (err) {
		notify('Programmation SMS impossible', 'error');
		console.error(err);
	} finally {
		smsLoading.value = false;
	}
};

const submitReport = async (payload) => {
	actionLoading.value = true;
	try {
		const patchedPayload = isMedecinUser.value
			? { ...payload, medecinId: connectedMedecinId.value }
			: payload;
		await api.reportRdv(payload.id, patchedPayload);
		notify('Rendez-vous reporté');
		refreshKey.value += 1;
		nextTick(() => {
			weeklyViewRef.value?.reloadOnAction?.();
		});
	} catch (err) {
		notify('Report impossible', 'error');
		console.error(err);
	} finally {
		actionLoading.value = false;
	}
};

const resetTourDialogs = () => {
	dialogState.create = false;
	dialogState.validate = false;
	dialogState.cancel = false;
	dialogState.report = false;
	smsDialogVisible.value = false;
	smsScheduleDialogVisible.value = false;
	currentRdv.value = null;
};

const openTourCreateDialog = () => {
	openCreate({
		start: new Date(),
		end: addMinutes(new Date(), 30),
		medecinId: isMedecinUser.value ? connectedMedecinId.value : null
	});
};

const handleGuidedTourRequest = async (event) => {
	if (event?.detail?.routeName !== 'agenda-rendezvous' || isGuidedTourStarting.value) {
		return;
	}

	if (hasOpenDialogs.value) {
		toast.add({
			severity: 'warn',
			summary: 'Aide guidee',
			detail: 'Fermez les fenetres ouvertes avant de lancer le tour.',
			life: 3000
		});
		return;
	}

	isGuidedTourStarting.value = true;

	try {
		activeIndex.value = 'week';
		resetTourDialogs();
		await nextTick();

		const steps = createAgendaRendezvousTour({
			isMedecin: isMedecinUser.value,
			openCreateDialog: openTourCreateDialog,
			closeAllDialogs: resetTourDialogs
		});

		await startTourGuide({
			group: 'agenda-rendezvous',
			steps,
			onAfterExit: resetTourDialogs,
			onFinish: resetTourDialogs
		});
	} catch (error) {
		console.error('Erreur lancement guided tour agenda rendez-vous', error);
		toast.add({
			severity: 'error',
			summary: 'Aide guidee',
			detail: 'Impossible de lancer le tour de la page rendez-vous.',
			life: 3000
		});
	} finally {
		isGuidedTourStarting.value = false;
	}
};

onMounted(() => {
	useLayout().layoutState.overlayMenuActive = false; // Ferme le menu si on arrive sur cette page depuis un lien direct
	window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
	window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
	resetTourDialogs();
});
</script>

<template>
	<section class="flex flex-col ml-4 gap-3 xs:gap-4 rounded-xl xs:rounded-2xl bg-surface-0 p-4 xs:p-5 shadow-sm dark:bg-surface-900 dark:shadow-none dark:ring-1 dark:ring-surface-700 sm:shadow-none sm:ring-0 ">
		<Toast />
		<div data-tour="agenda-rdv.header" class="flex flex-wrap items-center justify-between gap-3 xs:gap-4 border-b border-surface-200 pb-2 xs:pb-3 dark:border-surface-700">
			<div class="space-y-0.5 xs:space-y-1">
				<h2 class="text-xl xs:text-2xl font-semibold text-surface-900 dark:text-surface-200">Gestion des Rendez-vous</h2>
				<Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" />
			</div>
			<div data-tour="agenda-rdv.legend">
				<StatusLegend />
			</div>
		</div>

		<Tabs v-model:value="activeIndex">
			<TabList data-tour="agenda-rdv.tabs">
				<Tab value="week">Vue hebdomadaire</Tab>
				<Tab value="day">Vue journalière</Tab>
			</TabList>
			<TabPanels>
				<TabPanel value="week">
					<div data-tour="agenda-rdv.calendar">
					       <WeeklyView
						       ref="weeklyViewRef"
						       :medecins="scopedMedecinsList"
						       :api="api"
						       :refreshKey="refreshKey"
						       :lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
						       :medecinReadonly="isMedecinUser"
						       @request-create="openCreate"
						       @request-validate="openValidate"
						       @request-cancel="openCancel"
						       @request-report="openReport"
					       	@request-sms-reminder="openSmsReminder"
					       	@request-sms-schedule="openScheduleReminder"
					       />
					</div>
				</TabPanel>
				<TabPanel value="day">
					<div data-tour="agenda-rdv.calendar">
					<DailyView
						:medecins="scopedMedecinsList"
						:api="api"
						:refreshKey="refreshKey"
						:lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
						@request-create="openCreate"
						@request-validate="openValidate"
						@request-cancel="openCancel"
						@request-report="openReport"
					/>
					</div>
				</TabPanel>
			</TabPanels>
		</Tabs>

		<div data-tour="agenda-rdv.dialogs">
		<CreateRdvDialog
			v-model:visible="dialogState.create"
			:medecins="scopedMedecinsList"
			:defaultDate="createDefaults.start"
			:defaultMedecinId="createDefaults.medecinId"
			:lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
			:medecinReadonly="isMedecinUser"
			:loading="actionLoading"
			:searchPatients="api.searchPatients"
			@submit="submitCreate"
		/>

		<ValidateRdvDialog
			v-model:visible="dialogState.validate"
			:rdv="currentRdv"
			:medecins="scopedMedecinsList"
			:lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
			:medecinReadonly="isMedecinUser"
			:autoCreateConsultation="!isMedecinUser"
			:loading="actionLoading"
			@confirm="confirmValidate"
		/>

		<CancelRdvDialog
			v-model:visible="dialogState.cancel"
			:rdv="currentRdv"
			:loading="actionLoading"
			@confirm="confirmCancel"
		/>

		<ReportRdvDialog
			v-model:visible="dialogState.report"
			:rdv="currentRdv"
			:medecins="scopedMedecinsList"
			:lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
			:medecinReadonly="isMedecinUser"
			:loading="actionLoading"
			@submit="submitReport"
		/>
		</div>

		<Dialog v-model:visible="smsDialogVisible" modal header="Envoyer rappel SMS" :style="{ width: '38rem' }">
			<div class="flex flex-col gap-3">
				<div class="text-sm text-surface-600">Message personnalisable avant envoi.</div>
				<InputText v-model="smsDraft" />
				<div class="text-xs text-surface-500">{{ smsDraft.length }} caractères • {{ Math.max(1, Math.ceil(smsDraft.length / 160)) }} SMS estimé(s)</div>
			</div>
			<template #footer>
				<Button label="Annuler" text @click="smsDialogVisible = false" />
				<Button label="Envoyer SMS" icon="pi pi-send" :loading="smsLoading" @click="sendSmsReminder" />
			</template>
		</Dialog>

		<Dialog v-model:visible="smsScheduleDialogVisible" modal header="Programmer rappel automatique" :style="{ width: '30rem' }">
			<div class="flex flex-col gap-3">
				<div class="text-sm text-surface-600">Choisissez le délai avant le rendez-vous.</div>
				<SelectButton v-model="smsScheduleHours" :options="smsScheduleOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
			</div>
			<template #footer>
				<Button label="Annuler" text @click="smsScheduleDialogVisible = false" />
				<Button label="Programmer" icon="pi pi-clock" :loading="smsLoading" @click="scheduleSmsReminder" />
			</template>
		</Dialog>
	</section>
</template>

