<script setup>
import { logAppError } from '@/utils/appLogger';

import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import DailyView from '@/components/agenda/day/DailyView.vue';
import StatusLegend from '@/components/agenda/shared/StatusLegend.vue';
import CancelRdvDialog from '@/components/agenda/shared/CancelRdvDialog.vue';
import ReportRdvDialog from '@/components/agenda/shared/ReportRdvDialog.vue';
import ValidateRdvDialog from '@/components/agenda/shared/ValidateRdvDialog.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import WeeklyView from '@/components/agenda/week/WeeklyView.vue';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import { useGuidedTour } from '@/composables/useGuidedTour';
import { scheduleAppointmentReminderSms, sendAppointmentReminderSms } from '@/services/smsService';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { useRdvApi } from '@/composables/useRdvApi';
import { useAuthStore } from '@/stores/auth';
import { useLayout } from '@/layout/composables/layout';
import AppDialog from '@/components/layout/AppDialog.vue';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import cabinetConfig from '@/cabinetConfig';

defineProps({
    embedded: { type: Boolean, default: false }
});

const smsCabinetName = ref(cabinetConfig.smsCabinetName || cabinetConfig.displayName || 'Cabinet dentaire');
const requireMedecinOnConsultationCreation = ref(true);
const defaultCreateConsultationOnRdvValidation = ref(false);

const toast = useToast();
const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Agenda' }, { label: 'Rendez-vous', class: 'font-semibold' }];

const api = useRdvApi();
const auth = useAuthStore();
const medecinsList = computed(() => api.medecins?.value ?? api.medecins ?? []);
const isMedecinUser = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));

const normalizeText = (value) =>
    String(value || '')
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
const dailyViewRef = ref();
const actionLoading = ref(false);
const smsDialogVisible = ref(false);
const smsScheduleDialogVisible = ref(false);
const smsDraft = ref('');
const smsRdv = ref(null);
const smsScheduleHours = ref(24);
const smsScheduleOptions = ref([
    { label: '72h avant', value: 72 },
    { label: '48h avant', value: 48 },
    { label: '24h avant', value: 24 },
    { label: '12h avant', value: 12 },
    { label: '2h avant', value: 2 }
]);
const smsLoading = ref(false);
const token = localStorage.getItem('token');
const rdvLoadErrorMessage = computed(() => {
    const errorValue = api.error?.value ?? api.error;
    return typeof errorValue === 'string' && errorValue.trim() ? errorValue : '';
});

const dialogState = reactive({
    create: false,
    validate: false,
    cancel: false,
    report: false
});

const hasOpenDialogs = computed(() => dialogState.create || dialogState.validate || dialogState.cancel || dialogState.report || smsDialogVisible.value || smsScheduleDialogVisible.value);

const createDefaults = reactive({
    start: new Date(),
    medecinId: null
});

const currentRdv = ref(null);

const notify = (detail, severity = 'success') => {
    toast.add({ severity, summary: 'Agenda', detail, life: 2500 });
};

const refreshAgenda = () => {
    refreshKey.value += 1;
};

const openCreate = (payload = {}) => {
    const start = payload.start ? new Date(payload.start) : new Date();
    createDefaults.start = start;
    createDefaults.medecinId = isMedecinUser.value ? connectedMedecinId.value : (payload.medecin?.id ?? payload.medecinId ?? null);
    dialogState.create = true;
};

const submitCreate = async () => {
    try {
        dialogState.create = false;
        refreshAgenda();
    } catch (err) {
        logAppError('RendezVous', err);
    }
};

const openValidate = (rdv) => {
    currentRdv.value = {
        ...rdv,
        medecinId: isMedecinUser.value ? connectedMedecinId.value : rdv?.medecinId
    };
    dialogState.validate = true;
};

const confirmValidate = async ({ id, medecinId, createConsultation }) => {
    actionLoading.value = true;
    try {
        const wantsConsultation = Boolean(createConsultation);
        const effectiveMedecinId = wantsConsultation ? (isMedecinUser.value ? connectedMedecinId.value : medecinId) : null;
        await api.validateRdv(id, effectiveMedecinId, { createConsultation: wantsConsultation });
        notify('Rendez-vous validé');
        refreshAgenda();
    } catch (err) {
        const detail = err?.response?.data?.error || 'Validation impossible';
        notify(detail, 'error');
        logAppError('RendezVous', err);
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
        refreshAgenda();
    } catch (err) {
        notify('Annulation impossible', 'error');
        logAppError('RendezVous', err);
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
    smsDraft.value = `Bonjour ${patientName}, rappel de votre RDV le ${dateStr} à ${timeStr}. ${smsCabinetName.value}`.trim();
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
        refreshAgenda();
    } catch (err) {
        notify('Envoi SMS impossible', 'error');
        logAppError('RendezVous', err);
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
        refreshAgenda();
    } catch (err) {
        notify('Programmation SMS impossible', 'error');
        logAppError('RendezVous', err);
    } finally {
        smsLoading.value = false;
    }
};

const submitReport = async (payload) => {
    actionLoading.value = true;
    try {
        const patchedPayload = isMedecinUser.value ? { ...payload, medecinId: connectedMedecinId.value } : payload;
        await api.reportRdv(payload.id, patchedPayload);
        notify('Rendez-vous reporté');
        refreshAgenda();
    } catch (err) {
        notify('Report impossible', 'error');
        logAppError('RendezVous', err);
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
        medecinId: isMedecinUser.value ? connectedMedecinId.value : null
    });
};

const prepareGuidedTourDemo = async () => {
    activeIndex.value = 'week';
    resetTourDialogs();
    await nextTick();
};

useGuidedTour({
    routeName: 'agenda-rendezvous',
    hasOpenDialogs: () => hasOpenDialogs.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: resetTourDialogs,
    getStepContext: () => ({
        isMedecin: isMedecinUser.value,
        openCreateDialog: openTourCreateDialog,
        closeAllDialogs: resetTourDialogs
    }),
    dialogsMessage: 'Fermez les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour de la page rendez-vous.'
});

const retryLoadAgenda = async () => {
    refreshAgenda();
};

onMounted(async () => {
    useLayout().layoutState.overlayMenuActive = false;
    try {
        const settings = await fetchPublicGeneralSettings(token);
        if (settings?.smsCabinetName) {
            smsCabinetName.value = settings.smsCabinetName;
        }
        requireMedecinOnConsultationCreation.value = settings?.requireMedecinOnConsultationCreation !== false;
        defaultCreateConsultationOnRdvValidation.value = settings?.defaultCreateConsultationOnRdvValidation === true;
    } catch (error) {
        logAppError('RendezVous', error);
    }
});

onBeforeUnmount(() => {
    resetTourDialogs();
});
</script>

<template>
    <PageShell
        class="rendez-vous-page"
        :class="embedded ? 'is-embedded overflow-hidden' : undefined"
    >
        <template #header>
            <PageHeader
                title="Gestion des Rendez-vous"
                subtitle="Planifiez et suivez les rendez-vous du cabinet"
                icon="pi pi-calendar"
                tour-id="agenda-rdv.header"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
                :show-breadcrumb="!embedded"
            >
                <template #actions>
                    <div data-tour="agenda-rdv.legend">
                        <StatusLegend />
                    </div>
                </template>
            </PageHeader>
        </template>

        <div v-if="rdvLoadErrorMessage" class="flex min-h-[280px] flex-col items-center justify-center gap-4 rounded-xl border border-amber-200/70 bg-amber-50/70 p-6 dark:border-amber-800/70 dark:bg-amber-950/20">
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-xl"></i>
            </div>
            <div class="text-center">
                <p class="text-base font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</p>
                <p class="text-sm text-amber-700/90 dark:text-amber-300/90">{{ rdvLoadErrorMessage }}</p>
            </div>
            <Button icon="pi pi-refresh" label="Réessayer" severity="warning" size="small" @click="retryLoadAgenda" />
        </div>

        <template v-else>
            <PageSection tour-id="agenda-rdv.calendar" class="rendez-vous-section min-h-0 flex-1">
                <Tabs v-model:value="activeIndex" class="rendez-vous-tabs flex min-h-0 min-w-0 flex-1 flex-col" :class="embedded ? 'overflow-hidden' : undefined">
                    <TabList data-tour="agenda-rdv.tabs" class="flex-shrink-0 px-2 pt-2 sm:px-3">
                        <Tab value="week">Vue hebdomadaire</Tab>
                        <Tab value="day">Vue journalière</Tab>
                    </TabList>
                    <TabPanels class="min-h-0 flex-1">
                        <TabPanel value="week">
                            <div :class="embedded ? 'flex h-full min-h-0 min-w-0 flex-1 flex-col' : 'page-table-scroll'">
                                <WeeklyView
                                    ref="weeklyViewRef"
                                    :medecins="scopedMedecinsList"
                                    :api="api"
                                    :refreshKey="refreshKey"
                                    :lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
                                    :medecinReadonly="isMedecinUser"
                                    :embedded="embedded"
                                    @request-create="openCreate"
                                    @request-validate="openValidate"
                                    @request-cancel="openCancel"
                                    @request-report="openReport"
                                    @request-sms-reminder="openSmsReminder"
                                    @request-sms-schedule="openScheduleReminder"
                                />
                            </div>
                        </TabPanel>
                        <TabPanel value="day" class="h-full">
                            <div class="flex h-full min-h-0 flex-1 flex-col" :class="embedded ? 'min-w-0' : 'page-table-scroll'">
                                <DailyView
                                    ref="dailyViewRef"
                                    :medecins="scopedMedecinsList"
                                    :api="api"
                                    :refreshKey="refreshKey"
                                    :lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
                                    :embedded="embedded"
                                    @request-create="openCreate"
                                    @request-validate="openValidate"
                                    @request-cancel="openCancel"
                                    @request-report="openReport"
                                />
                            </div>
                        </TabPanel>
                    </TabPanels>
                </Tabs>
            </PageSection>

            <div data-tour="agenda-rdv.dialogs">
                <AppDialog
                    v-model:visible="dialogState.create"
                    title="Nouveau rendez-vous"
                    subtitle="Planifiez un rendez-vous depuis l'agenda"
                    icon="fas fa-calendar-plus"
                    icon-tone="primary"
                    size="lg"
                    :show-footer="false"
                    :content-padding="false"
                >
                    <FormRendezVous
                        :initial-date="createDefaults.start"
                        :initial-medecin-id="createDefaults.medecinId"
                        :locked-medecin-id="isMedecinUser ? connectedMedecinId : null"
                        :medecin-readonly="isMedecinUser"
                        @saved="submitCreate"
                        @cancel="dialogState.create = false"
                    />
                </AppDialog>

                <ValidateRdvDialog
                    v-model:visible="dialogState.validate"
                    :rdv="currentRdv"
                    :medecins="scopedMedecinsList"
                    :lockedMedecinId="isMedecinUser ? connectedMedecinId : null"
                    :medecinReadonly="isMedecinUser"
                    :requireMedecinOnConsultationCreation="requireMedecinOnConsultationCreation"
                    :defaultCreateConsultation="defaultCreateConsultationOnRdvValidation"
                    :loading="actionLoading"
                    @confirm="confirmValidate"
                />

                <CancelRdvDialog v-model:visible="dialogState.cancel" :rdv="currentRdv" :loading="actionLoading" @confirm="confirmCancel" />

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

            <AppDialog
                v-model:visible="smsDialogVisible"
                title="Envoyer rappel SMS"
                icon="pi pi-send"
                icon-tone="info"
                size="md"
                :loading="smsLoading"
                cancel-label="Annuler"
                confirm-label="Envoyer SMS"
                confirm-icon="pi pi-send"
                @confirm="sendSmsReminder"
            >
                <div class="flex flex-col gap-3">
                    <div class="text-sm text-surface-600">Message personnalisable avant envoi.</div>
                    <InputText v-model="smsDraft" />
                    <div class="text-xs text-surface-500">{{ smsDraft.length }} caractères • {{ Math.max(1, Math.ceil(smsDraft.length / 160)) }} SMS estimé(s)</div>
                </div>
            </AppDialog>

            <AppDialog
                v-model:visible="smsScheduleDialogVisible"
                title="Programmer rappel automatique"
                icon="pi pi-clock"
                icon-tone="info"
                size="sm"
                :loading="smsLoading"
                cancel-label="Annuler"
                confirm-label="Programmer"
                confirm-icon="pi pi-clock"
                @confirm="scheduleSmsReminder"
            >
                <div class="flex flex-col gap-3">
                    <div class="text-sm text-surface-600">Choisissez le délai avant le rendez-vous.</div>
                    <SelectButton v-model="smsScheduleHours" :options="smsScheduleOptions" optionLabel="label" optionValue="value" :allowEmpty="false" class="flex-wrap" />
                </div>
            </AppDialog>
        </template>
    </PageShell>
</template>

<style scoped>
.rendez-vous-page:not(.is-embedded) {
    min-height: calc(100dvh - 6rem);
}

.rendez-vous-page.is-embedded {
    min-height: 0;
    height: 100%;
}

.rendez-vous-page.is-embedded :deep(.page-shell__body) {
    flex: 1 1 auto;
    min-height: 0;
}

.rendez-vous-section :deep(.page-section__body) {
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.rendez-vous-page.is-embedded .rendez-vous-section,
.rendez-vous-page.is-embedded .rendez-vous-section :deep(.page-section__body) {
    flex: 1 1 auto;
    min-height: 0;
    height: 100%;
}

.rendez-vous-page.is-embedded :deep(.p-tabpanels),
.rendez-vous-page.is-embedded :deep(.p-tabpanel) {
    height: auto;
    max-height: 100%;
}

.rendez-vous-tabs :deep(.p-tabpanels),
.rendez-vous-tabs :deep(.p-tabpanel) {
    display: flex;
    flex: 1 1 auto;
    min-height: 0;
    flex-direction: column;
    height: 100%;
}
</style>
