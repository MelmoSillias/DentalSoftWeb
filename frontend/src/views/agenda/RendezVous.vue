<script setup>
import Breadcrumb from 'primevue/breadcrumb';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, reactive, ref } from 'vue';
import DailyView from '@/components/agenda/day/DailyView.vue';
import StatusLegend from '@/components/agenda/shared/StatusLegend.vue';
import CancelRdvDialog from '@/components/agenda/shared/CancelRdvDialog.vue';
import CreateRdvDialog from '@/components/agenda/shared/CreateRdvDialog.vue';
import ReportRdvDialog from '@/components/agenda/shared/ReportRdvDialog.vue';
import ValidateRdvDialog from '@/components/agenda/shared/ValidateRdvDialog.vue';
import WeeklyView from '@/components/agenda/week/WeeklyView.vue';
import { useRdvApi } from '@/composables/useRdvApi';
import { addMinutes } from '@/utils/dateUtils';
import { useLayout } from '@/layout/composables/layout';

const toast = useToast();
const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [
	{ label: 'Agenda' },
	{ label: 'Rendez-vous', class: 'font-semibold' }
];

const api = useRdvApi();
const medecinsList = computed(() => api.medecins?.value ?? api.medecins ?? []);
const activeIndex = ref('week');
const refreshKey = ref(0);
const actionLoading = ref(false);



const dialogState = reactive({
	create: false,
	validate: false,
	cancel: false,
	report: false
});

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
	createDefaults.medecinId = payload.medecin?.id ?? payload.medecinId ?? null;
	dialogState.create = true;
};

const submitCreate = async (payload) => {
	actionLoading.value = true;
	try {
		await api.createRdv(payload);
		notify('Rendez-vous créé');
		refreshKey.value += 1;
	} catch (err) {
		notify("Création impossible", 'error');
		console.error(err);
	} finally {
		actionLoading.value = false;
	}
};

const openValidate = (rdv) => {
	currentRdv.value = rdv;
	dialogState.validate = true;
};

const confirmValidate = async ({ id, medecinId }) => {
	actionLoading.value = true;
	try {
		await api.validateRdv(id, medecinId);
		notify('Rendez-vous validé');
		refreshKey.value += 1;
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
	} catch (err) {
		notify('Annulation impossible', 'error');
		console.error(err);
	} finally {
		actionLoading.value = false;
	}
};

const openReport = (rdv) => {
	currentRdv.value = rdv;
	dialogState.report = true;
};

const submitReport = async (payload) => {
	actionLoading.value = true;
	try {
		await api.reportRdv(payload.id, payload);
		notify('Rendez-vous reporté');
		refreshKey.value += 1;
	} catch (err) {
		notify('Report impossible', 'error');
		console.error(err);
	} finally {
		actionLoading.value = false;
	}
};

onMounted(() => {
	useLayout().layoutState.overlayMenuActive = false; // Ferme le menu si on arrive sur cette page depuis un lien direct
});
</script>

<template>
	<section class="flex flex-col gap-3 xs:gap-4 rounded-xl xs:rounded-2xl bg-surface-0 p-4 xs:p-5 shadow-sm dark:bg-surface-900 dark:shadow-none dark:ring-1 dark:ring-surface-700 sm:shadow-none sm:ring-0 sm:m-0">
		<Toast />
		<div class="flex flex-wrap items-center justify-between gap-3 xs:gap-4 border-b border-surface-200 pb-2 xs:pb-3 dark:border-surface-700">
			<div class="space-y-0.5 xs:space-y-1">
				<h2 class="text-xl xs:text-2xl font-semibold text-surface-900 dark:text-surface-200">Gestion des Rendez-vous</h2>
				<Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" />
			</div>
			<StatusLegend />
		</div>

		<Tabs v-model:value="activeIndex">
			<TabList>
				<Tab value="week">Vue hebdomadaire</Tab>
				<Tab value="day">Vue journalière</Tab>
			</TabList>
			<TabPanels>
				<TabPanel value="week">
					<WeeklyView
						:medecins="medecinsList"
						:api="api"
						:refreshKey="refreshKey"
						@request-create="openCreate"
						@request-validate="openValidate"
						@request-cancel="openCancel"
						@request-report="openReport"
					/>
				</TabPanel>
				<TabPanel value="day">
					<DailyView
						:medecins="medecinsList"
						:api="api"
						:refreshKey="refreshKey"
						@request-create="openCreate"
						@request-validate="openValidate"
						@request-cancel="openCancel"
						@request-report="openReport"
					/>
				</TabPanel>
			</TabPanels>
		</Tabs>

		<CreateRdvDialog
			v-model:visible="dialogState.create"
			:medecins="medecinsList"
			:defaultDate="createDefaults.start"
			:defaultMedecinId="createDefaults.medecinId"
			:loading="actionLoading"
			:searchPatients="api.searchPatients"
			@submit="submitCreate"
		/>

		<ValidateRdvDialog
			v-model:visible="dialogState.validate"
			:rdv="currentRdv"
			:medecins="medecinsList"
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
			:medecins="medecinsList"
			:loading="actionLoading"
			@submit="submitReport"
		/>
	</section>
</template>

