<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useGuidedTour } from '@/composables/useGuidedTour';
import {
    activateSmsTourMock,
    deactivateSmsTourMock,
    fetchSmsOverviewTourMock,
    fetchSmsQueueTourMock,
    fetchSmsTemplatesTourMock,
    resetSmsTourMockData,
    resolveSmsTourMockScenario
} from '@/services/smsTourMock';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Chip from 'primevue/chip';
import Column from 'primevue/column';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import Dialog from 'primevue/dialog';
import Divider from 'primevue/divider';
import FloatLabel from 'primevue/floatlabel';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';
import Chart from 'primevue/chart';
import ToggleSwitch from 'primevue/toggleswitch';
import { useSmsAdminSettings, SMS_PROVIDER_OPTIONS, SMS_CALLBACK_NOTIFY_OPTIONS } from '@/composables/useSmsAdminSettings';
import { fetchSmsQueueDetails } from '@/services/smsService';
import { getHttpErrorMessage } from '@/service/http';

const toast = useToast();
const token = localStorage.getItem('token');
const activeTab = ref('overview');
const logsStatusFilter = ref(null);
const logsDateRange = ref(null);
const logsSearch = ref('');
const manualTemplateCode = ref(null);
const newApprovedSenderName = ref('');
const loadErrorMessage = ref('');
const queueDialogVisible = ref(false);
const queueActionDialogVisible = ref(false);
const queueActionMode = ref(null);
const queueActionItem = ref(null);
const queueActionSendAt = ref(null);

const queueDetailsDialogVisible = ref(false);
const queueDetailsLoading = ref(false);
const queueDetailsItem = ref(null);
const queueDetailsLogs = ref([]);

let guidedTourDemoActive = false;
let guidedTourPageState = null;

const hasOpenDialogs = computed(() => (
    queueDialogVisible.value
    || queueActionDialogVisible.value
    || queueDetailsDialogVisible.value
));

const switchTab = async (tab) => {
    activeTab.value = tab;
    await new Promise((resolve) => window.setTimeout(resolve, 180));
};

const smsAutomationOperational = computed(() => smsConfig.enabled && providerOverview.value.success);
const smsAutomationStatusLabel = computed(() => (smsAutomationOperational.value ? 'Service automatique opérationnel' : 'Service automatique à vérifier'));
const smsAutomationStatusSeverity = computed(() => (smsAutomationOperational.value ? 'success' : 'warn'));
const smsAutomationStatusDetail = computed(() => {
    if (smsAutomationOperational.value) {
        return 'Configuration active et fournisseur joignable. Le déclenchement automatique dépend ensuite du worker Messenger côté serveur.';
    }

    if (!smsConfig.enabled) {
        return 'Le module SMS est désactivé dans la configuration actuelle.';
    }

    return providerOverview.value.message || 'Le fournisseur ne confirme pas encore un état exploitable pour l’automatisation.';
});

const tabItems = [
    { value: 'overview', label: 'Aperçu', icon: 'pi pi-chart-bar' },
    { value: 'config', label: 'Configuration & Test', icon: 'pi pi-cog' },
    { value: 'queue', label: 'File SMS', icon: 'pi pi-clock' },
    { value: 'logs', label: 'Logs', icon: 'pi pi-list' },
    { value: 'templates', label: 'Templates', icon: 'pi pi-file-edit' },
    { value: 'manual', label: 'Envoi Manuel', icon: 'pi pi-send' }
];

const extractApiError = (error, fallback) => getHttpErrorMessage(error, fallback);

const {
    smsLoading,
    smsLoaded,
    smsPeriodLoading,
    smsTesting,
    smsSendingTest,
    smsSaving,
    smsQueueing,
    smsQueueItemUpdating,
    smsTemplateSaving,
    lastTestResult,
    lastTestAt,
    providerOverview,
    smsConfig,
    smsStats,
    smsQueue,
    smsLogs,
    smsTemplates,
    selectedTemplateCode,
    previewVariables,
    previewResult,
    manualSms,
    queuedSms,
    testSms,
    selectedTemplate,
    previewCharacters,
    previewEstimatedSms,
    dailySeries,
    monthlySeries,
    maxDaily,
    maxMonthly,
    periodDailySeries,
    periodByType,
    maxPeriodByType,
    toIsoDate,
    loadPeriodStats,
    loadSmsData,
    refreshSmsData,
    saveSmsConfigAction,
    testConnectionAction,
    sendSmsTestAction,
    saveTemplatesAction,
    previewTemplateAction,
    sendManualSmsAction,
    scheduleQueuedSmsAction,
    processQueueAction,
    updateQueueItemAction
} = useSmsAdminSettings(token, toast, extractApiError);

const applySmsTourMockData = () => {
    const overview = fetchSmsOverviewTourMock();
    smsConfig.enabled = overview.configured;
    providerOverview.value = {
        success: Boolean(overview.automationOperational),
        message: overview.configured ? 'Provider joignable pour la demonstration.' : 'Configuration requise.',
        contracts: []
    };
    smsStats.balance.sentToday = overview.stats?.sentToday ?? 0;
    smsStats.balance.sentMonth = overview.stats?.sentToday ?? 0;
    smsQueue.value = fetchSmsQueueTourMock().map((item) => ({
        id: item.id,
        createdAt: item.scheduledAt,
        sendAt: item.scheduledAt,
        patient: null,
        phone: item.recipient,
        message: item.message,
        status: item.status,
        source: 'tour-mock'
    }));
    smsTemplates.value = fetchSmsTemplatesTourMock().map((item) => ({
        code: item.key,
        name: item.label,
        content: item.body,
        enabled: true
    }));
    if (smsTemplates.value.length > 0) {
        selectedTemplateCode.value = smsTemplates.value[0].code;
    }
    smsLoaded.value = true;
};

const prepareGuidedTourDemo = async ({ taskId = 'overview', variantId = null } = {}) => {
    guidedTourPageState = { activeTab: activeTab.value };
    const scenario = resolveSmsTourMockScenario(taskId, variantId);
    activateSmsTourMock(scenario);
    resetSmsTourMockData(scenario);
    guidedTourDemoActive = true;
    applySmsTourMockData();
    activeTab.value = 'overview';
    await switchTab(activeTab.value);
};

const cleanupGuidedTourDemo = async () => {
    if (!guidedTourDemoActive) {
        return;
    }

    deactivateSmsTourMock();
    guidedTourDemoActive = false;
    const previousTab = guidedTourPageState?.activeTab || 'overview';
    guidedTourPageState = null;
    smsLoaded.value = false;
    await loadSmsData(true);
    activeTab.value = previousTab;
};

useGuidedTour({
    routeName: 'administration-api-sms',
    isLoading: () => smsLoading.value && !smsLoaded.value,
    hasOpenDialogs: () => hasOpenDialogs.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    getStepContext: () => ({
        switchTab
    }),
    loadingMessage: 'Attendez la fin du chargement SMS avant de lancer le tour.',
    dialogsMessage: 'Fermez les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour de la page SMS.'
});

const statsPeriodStart = new Date();
statsPeriodStart.setDate(1);
statsPeriodStart.setHours(0, 0, 0, 0);
const statsPeriodRange = ref([statsPeriodStart, new Date()]);
const statsPeriodHasLoaded = ref(false);

const statsPeriodLabel = computed(() => {
    const [start, end] = statsPeriodRange.value || [];
    if (!start || !end) return 'Choisir période';
    return `${start.toLocaleDateString('fr-FR')} - ${end.toLocaleDateString('fr-FR')}`;
});

const formatSmsTypeLabel = (type) => {
    const labels = {
        manual: 'Manuel',
        receipt: 'Reçu',
        invoice: 'Facture',
        ticket: 'Ticket',
        'appointment reminder': 'Rappel RDV',
        appointment_reminder: 'Rappel RDV',
        reminder: 'Rappel',
        test: 'Test'
    };
    return labels[type] || type || 'Autre';
};

const refreshPeriodStats = async (silent = false) => {
    const [start, end] = statsPeriodRange.value || [];
    if (!start || !end) return;
    await loadPeriodStats(toIsoDate(start), toIsoDate(end), { silent });
};

const queueRecurrenceOptions = [
    { label: 'Sans répétition', value: 'none' },
    { label: 'Tous les jours x3', value: 'daily_3' },
    { label: 'Toutes les semaines x4', value: 'weekly_4' }
];

const queueStatusLabel = (status) => {
    switch (status) {
    case 'sent': return 'Envoyé';
    case 'failed': return 'Échec';
    case 'sending': return 'Envoi';
    case 'cancelled': return 'Annulé';
    default: return 'En attente';
    }
};

const queueStatusSeverity = (status) => {
    switch (status) {
    case 'sent': return 'success';
    case 'failed': return 'danger';
    case 'sending': return 'info';
    case 'cancelled': return 'secondary';
    default: return 'warning';
    }
};

const queueActionTitle = computed(() => {
    if (queueActionMode.value === 'reschedule') return 'Reprogrammer le SMS';
    if (queueActionMode.value === 'cancel') return 'Annuler le SMS';
    if (queueActionMode.value === 'retry') return 'Renvoyer le SMS';
    return 'Action sur la file SMS';
});

const queueActionDescription = computed(() => {
    if (queueActionMode.value === 'reschedule') return 'Choisissez une nouvelle date et heure d’envoi pour ce SMS en attente.';
    if (queueActionMode.value === 'cancel') return 'Ce SMS en attente sera retiré du traitement automatique.';
    if (queueActionMode.value === 'retry') return 'Ce SMS échoué sera remis immédiatement dans la file d’envoi.';
    return '';
});

const openQueueActionDialog = (mode, item) => {
    queueActionMode.value = mode;
    queueActionItem.value = item;
    queueActionSendAt.value = mode === 'reschedule'
        ? (item?.sendAt ? new Date(item.sendAt) : new Date())
        : null;
    queueActionDialogVisible.value = true;
};

const openQueueDetails = async (item) => {
    queueDetailsItem.value = null;
    queueDetailsLogs.value = [];
    queueDetailsLoading.value = true;
    queueDetailsDialogVisible.value = true;
    try {
        const token = localStorage.getItem('token');
        const res = await fetchSmsQueueDetails(item.id, token);
        if (res && res.success) {
            queueDetailsItem.value = res.queueItem || null;
            queueDetailsLogs.value = Array.isArray(res.logs) ? res.logs : [];
        } else {
            queueDetailsItem.value = { id: item.id, phone: item.phone, message: item.message, status: item.status, lastError: item.lastError };
        }
    } catch (e) {
        queueDetailsItem.value = { id: item.id, phone: item.phone, message: item.message, status: item.status, lastError: item.lastError };
    } finally {
        queueDetailsLoading.value = false;
    }
};

const closeQueueActionDialog = () => {
    queueActionDialogVisible.value = false;
    queueActionMode.value = null;
    queueActionItem.value = null;
    queueActionSendAt.value = null;
};

const submitQueueAction = async () => {
    if (!queueActionItem.value?.id || !queueActionMode.value) return;

    if (queueActionMode.value === 'reschedule') {
        if (!(queueActionSendAt.value instanceof Date) || Number.isNaN(queueActionSendAt.value.getTime())) {
            toast.add({ severity: 'warn', summary: 'File SMS', detail: 'Sélectionnez une date valide.', life: 2500 });
            return;
        }

        await updateQueueItemAction(
            queueActionItem.value.id,
            { action: 'reschedule', sendAt: queueActionSendAt.value.toISOString() },
            'SMS reprogrammé.'
        );
        closeQueueActionDialog();
        return;
    }

    if (queueActionMode.value === 'cancel') {
        await updateQueueItemAction(queueActionItem.value.id, { action: 'cancel' }, 'SMS annulé.');
        closeQueueActionDialog();
        return;
    }

    if (queueActionMode.value === 'retry') {
        await updateQueueItemAction(queueActionItem.value.id, { action: 'retry' }, 'SMS remis en file.');
        closeQueueActionDialog();
    }
};

const totalCharacters = computed(() =>
    smsTemplates.value.reduce((sum, template) => sum + String(template?.content || '').length, 0)
);
const recommendedContract = computed(() => providerOverview.value.contracts.find((item) => item.isRecommended) || providerOverview.value.contracts[0] || null);
const isOrangeProvider = computed(() => smsConfig.provider === 'orange');
const isAfrikSmsProvider = computed(() => smsConfig.provider === 'afriksms');
const providerLabel = computed(() => SMS_PROVIDER_OPTIONS.find((item) => item.value === smsConfig.provider)?.label || smsConfig.provider || '—');
const providerOverviewTitle = computed(() => (isAfrikSmsProvider.value ? 'Solde AfrikSms' : 'Contrat Orange'));
const providerOverviewEmptyMessage = computed(() => (
    isAfrikSmsProvider.value
        ? (providerOverview.value.message || 'Aucun solde AfrikSms disponible pour le moment.')
        : (providerOverview.value.message || 'Aucun contrat Orange disponible pour le moment.')
));
const approvedSenderNameOptions = computed(() => smsConfig.approvedSenderNames.map((item) => ({ label: item, value: item })));
const patientPreferenceBypassOptions = [
    { key: 'patientCreated', label: 'Création patient', description: 'Ignore la préférence patient de SMS après création.' },
    { key: 'receipt', label: 'Reçu', description: 'Force l’envoi des SMS de reçu même si le patient les a désactivés.' },
    { key: 'ticket', label: 'Ticket', description: 'Force l’envoi des tickets SMS malgré la préférence patient.' },
    { key: 'invoice', label: 'Facture', description: 'Force l’envoi des factures SMS malgré la préférence patient.' },
    { key: 'appointmentReminder', label: 'Rappel de rendez-vous', description: 'Ignore l’option patient de rappel SMS.' },
    { key: 'unsubscribed', label: 'Patient désabonné', description: 'Autorise les envois template même si le patient est marqué désabonné.' },
    { key: 'blacklisted', label: 'Numéro blacklisté', description: 'Autorise les envois template même si le numéro est blacklisté.' }
];

const formatDateTime = (value) => {
    if (!value) return 'Jamais';

    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return 'Jamais';
    }

    return date.toLocaleString('fr-FR');
};

const statusOptions = [
    { label: 'Tous', value: null },
    { label: 'Envoyé', value: 'sent' },
    { label: 'Livré', value: 'delivered' },
    { label: 'Échec', value: 'failed' },
    { label: 'En attente', value: 'pending' }
];

const logStatusSeverity = (status) => {
    if (status === 'delivered' || status === 'sent') return 'success';
    if (status === 'failed') return 'danger';
    return 'warning';
};

const applyProviderDefaults = (provider) => {
    if (provider === 'afriksms') {
        smsConfig.baseUrl = 'https://api.afriksms.com/api/web/web_v1/outbounds';
        return;
    }

    smsConfig.baseUrl = 'https://api.orange.com';
    smsConfig.oauthUrl = 'https://api.orange.com/oauth/v3/token';
};

const formatPeriodDayLabel = (day, { short = true } = {}) => {
    const date = new Date(`${day}T00:00:00`);
    if (Number.isNaN(date.getTime())) return day;
    return date.toLocaleDateString('fr-FR', short
        ? { day: '2-digit', month: 'short' }
        : { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
};

const periodDailyChartData = computed(() => {
    const labels = periodDailySeries.value.map(([day]) => formatPeriodDayLabel(day));
    const values = periodDailySeries.value.map(([, count]) => Number(count) || 0);

    return {
        labels,
        datasets: [
            {
                label: 'SMS envoyés',
                data: values,
                fill: true,
                tension: 0.35,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.12)',
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#ffffff',
                pointHoverBackgroundColor: '#ffffff',
                pointHoverBorderColor: '#059669',
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ]
    };
});

const periodDailyChartOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: (items) => {
                        const index = items[0]?.dataIndex ?? 0;
                        const [day] = periodDailySeries.value[index] || [];
                        return day ? formatPeriodDayLabel(day, { short: false }) : '';
                    },
                    label: (item) => `${item.formattedValue} SMS envoyé(s)`
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: textColorSecondary,
                    maxRotation: 45,
                    minRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 12
                },
                grid: { display: false }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    color: textColorSecondary,
                    precision: 0,
                    stepSize: 1
                },
                grid: { color: surfaceBorder }
            }
        }
    };
});

const trafficTrend = computed(() => {
    const series = dailySeries.value;
    if (series.length < 2) return 0;
    const previous = Number(series.at(-2)?.[1] || 0);
    const current = Number(series.at(-1)?.[1] || 0);
    return current - previous;
});

const logsFiltered = computed(() => {
    const query = logsSearch.value.trim().toLowerCase();
    const [start, end] = Array.isArray(logsDateRange.value) ? logsDateRange.value : [];

    return smsLogs.value.filter((log) => {
        if (logsStatusFilter.value && log.status !== logsStatusFilter.value) {
            return false;
        }

        if (query) {
            const haystack = [log.patient, log.phone, log.message, log.status, log.type, log.source]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();

            if (!haystack.includes(query)) {
                return false;
            }
        }

        if (start || end) {
            const logDate = log.date ? new Date(log.date) : null;
            if (!logDate || Number.isNaN(logDate.getTime())) {
                return false;
            }
            if (start && logDate < start) return false;
            if (end) {
                const normalizedEnd = new Date(end);
                normalizedEnd.setHours(23, 59, 59, 999);
                if (logDate > normalizedEnd) return false;
            }
        }

        return true;
    });
});

const applyTemplateToManualSms = () => {
    const template = smsTemplates.value.find((item) => item.code === manualTemplateCode.value);
    if (!template) return;
    manualSms.message = String(template.content || '');
};

const applyApprovedSenderName = (value) => {
    smsConfig.senderName = value || '';
};

const addApprovedSenderName = () => {
    const value = newApprovedSenderName.value.trim();
    if (!value) return;
    if (!/^[A-Za-z0-9 ]{1,11}$/.test(value)) {
        toast.add({ severity: 'warn', summary: 'Sender Name', detail: 'Utilisez 11 caractères maximum, alphanumériques et espaces uniquement.', life: 3000 });
        return;
    }
    if (!smsConfig.approvedSenderNames.includes(value)) {
        smsConfig.approvedSenderNames = [...smsConfig.approvedSenderNames, value];
    }
    smsConfig.senderName = value;
    newApprovedSenderName.value = '';
};

const removeApprovedSenderName = (value) => {
    smsConfig.approvedSenderNames = smsConfig.approvedSenderNames.filter((item) => item !== value);
    if (smsConfig.senderName === value) {
        smsConfig.senderName = smsConfig.approvedSenderNames[0] || '';
    }
};

const addTemplate = () => {
    const timestamp = Date.now();
    const code = `custom_${timestamp}`;
    smsTemplates.value = [
        {
            code,
            name: `Nouveau template ${smsTemplates.value.length + 1}`,
            content: '',
            enabled: true
        },
        ...smsTemplates.value
    ];
    selectedTemplateCode.value = code;
    activeTab.value = 'templates';
};

const removeSelectedTemplate = () => {
    if (!selectedTemplateCode.value) return;
    smsTemplates.value = smsTemplates.value.filter((item) => item.code !== selectedTemplateCode.value);
    selectedTemplateCode.value = smsTemplates.value[0]?.code || '';
};

watch(manualTemplateCode, () => {
    applyTemplateToManualSms();
});

watch(
    () => statsPeriodRange.value,
    () => {
        const [start, end] = statsPeriodRange.value || [];
        if (!start || !end) return;
        if (!statsPeriodHasLoaded.value) {
            statsPeriodHasLoaded.value = true;
            if (smsLoaded.value) {
                refreshPeriodStats(true);
            }
            return;
        }
        refreshPeriodStats(true);
    },
    { deep: true }
);

onMounted(async () => {
    try {
        const [start, end] = statsPeriodRange.value || [];
        if (start && end) {
            smsStats.period.from = toIsoDate(start);
            smsStats.period.to = toIsoDate(end);
        }
        await loadSmsData(true);
        statsPeriodHasLoaded.value = true;
        loadErrorMessage.value = '';
    } catch (error) {
        loadErrorMessage.value = extractApiError(error, 'Impossible de charger les paramètres SMS.');
    }
});

onBeforeUnmount(() => {
    deactivateSmsTourMock();
    guidedTourDemoActive = false;
});

const retryLoadSmsSettings = async () => {
    loadErrorMessage.value = '';
    await loadSmsData(true);
};
</script>

<template>
    <div class="space-y-6 pb-6 ml-8">
        <div v-if="loadErrorMessage" class="flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 dark:border-amber-800/70 dark:bg-amber-950/20">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</p>
                <p class="text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
            </div>
            <Button icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryLoadSmsSettings" />
        </div>

        <template v-else>
        <!-- Header Section -->
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50" data-tour="sms-settings.overview">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">
                        Administration
                    </p>
                    <div class="space-y-1">
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            API SMS
                        </h1>
                        <p class="max-w-3xl text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Configuration du fournisseur, supervision du trafic, templates et file d'envoi.
                        </p>
                        <div
                            class="mt-3 inline-flex max-w-3xl items-start gap-3 rounded-2xl border px-4 py-3"
                            data-tour="sms-settings.status"
                            :class="smsAutomationOperational
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/20 dark:text-emerald-200'
                                : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/20 dark:text-amber-200'"
                        >
                            <i :class="smsAutomationOperational ? 'pi pi-check-circle' : 'pi pi-exclamation-triangle'" class="mt-0.5 text-base"></i>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold">{{ smsAutomationStatusLabel }}</span>
                                    <Tag :severity="smsAutomationStatusSeverity" :value="smsConfig.enabled ? 'Activé' : 'Désactivé'" />
                                </div>
                                <p class="mt-1 text-xs leading-relaxed opacity-90">{{ smsAutomationStatusDetail }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Button
                        label="Rafraîchir"
                        icon="pi pi-refresh"
                        severity="secondary"
                        outlined
                        :loading="smsLoading"
                        @click="refreshSmsData"
                    />
                    <Button
                        label="Traiter file"
                        icon="pi pi-play"
                        :loading="smsQueueing"
                        @click="processQueueAction"
                    />
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <Tabs :value="activeTab" @update:value="activeTab = $event">
            <TabList class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-800" data-tour="sms-settings.tabs">
                <Tab
                    v-for="item in tabItems"
                    :key="item.value"
                    :value="item.value"
                    class="rounded-t-xl px-4 py-2.5 font-medium transition-all data-[selected]:bg-white data-[selected]:text-blue-600 dark:data-[selected]:bg-gray-900 dark:data-[selected]:text-blue-400"
                >
                    <span class="flex items-center gap-2">
                        <i :class="item.icon" class="text-sm"></i>
                        <span class="text-sm">{{ item.label }}</span>
                    </span>
                </Tab>
            </TabList>

            <TabPanels class="mt-6">
                <!-- Overview Tab -->
                <TabPanel value="overview">
                    <div class="space-y-6">
                        <!-- Loading State -->
                        <div v-if="smsLoading && !smsLoaded" class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                                <Skeleton height="8rem" />
                            </div>
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                                <Skeleton height="8rem" />
                            </div>
                        </div>

                        <!-- Content -->
                        <template v-else>
                            <!-- Stats Grid -->
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="group rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:shadow-lg dark:border-gray-700 dark:bg-gray-900/50">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Provider</p>
                                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ providerLabel }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ smsConfig.enabled ? 'Actif' : 'Désactivé' }}</p>
                                        </div>
                                        <div class="rounded-xl bg-blue-50 p-2 dark:bg-blue-900/20">
                                            <i class="pi pi-megaphone text-blue-600 dark:text-blue-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="group rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:shadow-lg dark:border-gray-700 dark:bg-gray-900/50">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">SMS envoyés aujourd'hui</p>
                                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ smsStats.balance.sentToday }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Dernières 24h</p>
                                        </div>
                                        <div class="rounded-xl bg-green-50 p-2 dark:bg-green-900/20">
                                            <i class="pi pi-send text-green-600 dark:text-green-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="group rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:shadow-lg dark:border-gray-700 dark:bg-gray-900/50">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">SMS envoyés ce mois</p>
                                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ smsStats.balance.sentMonth }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ trafficTrend >= 0 ? '+' : '' }}{{ trafficTrend }} vs jour précédent
                                            </p>
                                        </div>
                                        <div class="rounded-xl bg-purple-50 p-2 dark:bg-purple-900/20">
                                            <i class="pi pi-chart-line text-purple-600 dark:text-purple-400"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="group rounded-2xl border border-gray-200 bg-white p-5 transition-all hover:shadow-lg dark:border-gray-700 dark:bg-gray-900/50">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Templates actifs</p>
                                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ smsTemplates.length }}</p>
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ totalCharacters }} caractères cumulés</p>
                                        </div>
                                        <div class="rounded-xl bg-orange-50 p-2 dark:bg-orange-900/20">
                                            <i class="pi pi-file text-orange-600 dark:text-orange-400"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Period detailed stats -->
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                                <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Période</p>
                                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Statistiques détaillées</h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ statsPeriodLabel }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <PanelDatePicker
                                            v-model="statsPeriodRange"
                                            showIcon
                                            dateFormat="dd/mm/yy"
                                            class="w-72"
                                            placeholder="Choisir période"
                                        />
                                        <Button
                                            label="Rafraîchir"
                                            icon="pi pi-refresh"
                                            outlined
                                            :loading="smsPeriodLoading"
                                            @click="refreshPeriodStats(false)"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Envoyés</p>
                                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ smsStats.period.sent }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Échecs</p>
                                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ smsStats.period.failed }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total tentatives</p>
                                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ smsStats.period.total }}</p>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Taux de succès</p>
                                        <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ smsStats.period.successRate }}%</p>
                                    </div>
                                </div>

                                <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                                    <div>
                                        <div class="mb-4 flex items-center justify-between gap-3">
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Trafic journalier (période)</h4>
                                            <Tag severity="info" :value="`${periodDailySeries.length} jour(s)`" />
                                        </div>
                                        <div v-if="periodDailySeries.length" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                            <div class="h-72">
                                                <Chart type="line" :data="periodDailyChartData" :options="periodDailyChartOptions" class="h-full w-full" />
                                            </div>
                                        </div>
                                        <div
                                            v-else
                                            class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
                                        >
                                            Aucun envoi sur cette période.
                                        </div>
                                    </div>

                                    <div>
                                        <div class="mb-4 flex items-center justify-between gap-3">
                                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Répartition par type</h4>
                                            <Tag severity="secondary" :value="`${periodByType.length} type(s)`" />
                                        </div>
                                        <div v-if="periodByType.length" class="space-y-3">
                                            <div
                                                v-for="([type, count]) in periodByType"
                                                :key="type"
                                                class="flex items-center gap-3 text-sm"
                                            >
                                                <span class="w-28 shrink-0 text-gray-600 dark:text-gray-400">{{ formatSmsTypeLabel(type) }}</span>
                                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div
                                                        class="h-2 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600"
                                                        :style="{ width: `${Math.round((Number(count) / maxPeriodByType) * 100)}%` }"
                                                    />
                                                </div>
                                                <span class="w-12 text-right font-medium text-gray-700 dark:text-gray-300">{{ count }}</span>
                                            </div>
                                        </div>
                                        <div
                                            v-else
                                            class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400"
                                        >
                                            Aucune répartition disponible pour cette période.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Traffic and Test Results -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <!-- Daily Traffic -->
                                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                                    <div class="mb-4 flex items-start justify-between">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tendance</p>
                                            <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Trafic journalier</h3>
                                        </div>
                                        <Tag severity="success" :value="`${smsStats.balance.totalSent} total`" />
                                    </div>

                                    <div v-if="dailySeries.length" class="space-y-3">
                                        <div v-for="([day, count]) in dailySeries" :key="day" class="flex items-center gap-3 text-sm">
                                            <span class="w-20 text-gray-600 dark:text-gray-400">{{ day }}</span>
                                            <div class="flex-1 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                <div
                                                    class="h-2 rounded-full bg-gradient-to-r from-blue-500 to-blue-600"
                                                    :style="{ width: `${Math.round((Number(count) / maxDaily) * 100)}%` }"
                                                />
                                            </div>
                                            <span class="w-12 text-right font-medium text-gray-700 dark:text-gray-300">{{ count }}</span>
                                        </div>
                                    </div>
                                    <div v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                        Aucune consommation journalière disponible.
                                    </div>
                                </div>

                                <!-- Last Test -->
                                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                                    <div class="mb-4">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">État</p>
                                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Dernier test</h3>
                                    </div>

                                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800/50">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dernière vérification</p>
                                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ formatDateTime(lastTestAt) }}</p>
                                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                            {{ lastTestResult?.message || 'Aucun test effectué pour le moment.' }}
                                        </p>
                                    </div>

                                    <div class="mt-4">
                                        <Tag
                                            v-if="lastTestResult"
                                            :severity="lastTestResult.success ? 'success' : 'danger'"
                                            :value="lastTestResult.kind === 'send' ? 'Envoi de test' : 'Connexion API'"
                                        />
                                    </div>

                                    <p class="mt-4 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                        Les détails de forfait et de crédits restants ne sont pas exposés par votre backend actuel.
                                        La page affiche donc le trafic réellement historisé dans DentalSoft.
                                    </p>
                                </div>
                            </div>

                            <!-- Provider Overview -->
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ providerOverviewTitle }}</p>
                                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ isAfrikSmsProvider ? 'Crédits par pays' : 'Forfait et disponibilité' }}</h3>
                                    </div>
                                    <Tag
                                        :severity="providerOverview.success ? 'success' : 'warn'"
                                        :value="providerOverview.success ? 'Synchronisé' : 'Indisponible'"
                                    />
                                </div>

                                <div v-if="recommendedContract" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Offre</p>
                                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ recommendedContract.offerName || '—' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ recommendedContract.country || '—' }}</p>
                                    </div>

                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ isAfrikSmsProvider ? 'SMS restants' : 'Unités restantes' }}</p>
                                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ recommendedContract.availableUnits ?? '—' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ isAfrikSmsProvider ? 'Solde recommandé' : 'Contrat recommandé' }}</p>
                                    </div>

                                    <div v-if="!isAfrikSmsProvider" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Statut</p>
                                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ recommendedContract.status || '—' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Type {{ recommendedContract.type || '—' }}</p>
                                    </div>

                                    <div v-if="!isAfrikSmsProvider" class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Expiration</p>
                                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ formatDateTime(recommendedContract.expirationDate) }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ providerOverview.message || 'Données Orange' }}</p>
                                    </div>
                                </div>

                                <div v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                    {{ providerOverviewEmptyMessage }}
                                </div>
                            </div>
                        </template>
                    </div>
                </TabPanel>

                <!-- Configuration Tab -->
                <TabPanel value="config">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50" data-tour="sms-settings.config">
                        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Configuration</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Configuration & test</h3>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <Button label="Test connexion" icon="pi pi-bolt" severity="secondary" :loading="smsTesting" data-tour="sms-settings.test-connection" @click="testConnectionAction" />
                                <Button label="Envoyer SMS test" icon="pi pi-send" severity="info" :loading="smsSendingTest" @click="sendSmsTestAction" />
                                <Button label="Sauvegarder" icon="pi pi-save" :loading="smsSaving" data-tour="sms-settings.save-config" @click="saveSmsConfigAction" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Provider</label>
                                <Select
                                    id="sms-provider"
                                    v-model="smsConfig.provider"
                                    :options="SMS_PROVIDER_OPTIONS"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                    @update:modelValue="applyProviderDefaults"
                                />
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Activation</label>
                                <SelectButton
                                    v-model="smsConfig.enabled"
                                    :options="[{ label: 'Activé', value: true }, { label: 'Désactivé', value: false }]"
                                    optionLabel="label"
                                    optionValue="value"
                                    :allowEmpty="false"
                                />
                            </div>

                            <div>
                                <FloatLabel variant="on">
                                    <InputText id="sms-client-id" v-model="smsConfig.clientId" class="w-full" />
                                    <label for="sms-client-id">{{ isAfrikSmsProvider ? 'Identifiant API (ClientId)' : 'Client ID' }}</label>
                                </FloatLabel>
                            </div>

                            <div>
                                <FloatLabel variant="on">
                                    <InputText id="sms-client-secret" v-model="smsConfig.clientSecret" type="password" class="w-full" />
                                    <label for="sms-client-secret">{{ isAfrikSmsProvider ? 'Clé API (ApiKey)' : 'Client Secret' }}</label>
                                </FloatLabel>
                            </div>

                            <div v-if="isOrangeProvider" class="space-y-2">
                                <FloatLabel variant="on">
                                    <InputText id="sms-sender-address" v-model="smsConfig.senderAddress" class="w-full" />
                                    <label for="sms-sender-address">Sender Address</label>
                                </FloatLabel>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Pour Orange Mali, utilisez d'abord le sender technique standard tel:+2230000.</p>
                            </div>

                            <div class="space-y-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ isAfrikSmsProvider ? 'SenderId' : 'Sender Name' }}</label>
                                <Select
                                    v-if="isOrangeProvider && approvedSenderNameOptions.length"
                                    v-model="smsConfig.senderName"
                                    :options="approvedSenderNameOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    placeholder="Choisir un Sender Name approuvé"
                                    class="w-full"
                                    @update:modelValue="applyApprovedSenderName"
                                />
                                <FloatLabel variant="on">
                                    <InputText id="sms-sender-name" v-model="smsConfig.senderName" class="w-full" />
                                    <label for="sms-sender-name">{{ isAfrikSmsProvider ? 'SenderId (11 caractères max)' : 'Saisie manuelle' }}</label>
                                </FloatLabel>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ isAfrikSmsProvider ? 'Obligatoire pour AfrikSms. 11 caractères maximum.' : 'Optionnel. Doit être whitelisté par Orange et limité à 11 caractères alphanumériques ou espaces.' }}
                                </p>
                            </div>

                            <div v-if="isAfrikSmsProvider" class="space-y-2">
                                <FloatLabel variant="on">
                                    <InputText id="sms-webhook-base-url" v-model="smsConfig.webhookBaseUrl" class="w-full" />
                                    <label for="sms-webhook-base-url">URL publique du backend</label>
                                </FloatLabel>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Ex: https://cabinet.example.com — utilisée pour enregistrer /api/sms/webhooks/afriksms chez AfrikSms.</p>
                            </div>

                            <div v-if="isAfrikSmsProvider" class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Méthode callback DLR</label>
                                <Select
                                    v-model="smsConfig.callbackNotifyType"
                                    :options="SMS_CALLBACK_NOTIFY_OPTIONS"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                />
                            </div>

                            <div>
                                <FloatLabel variant="on">
                                    <InputText id="sms-base-url" v-model="smsConfig.baseUrl" class="w-full" />
                                    <label for="sms-base-url">Base URL</label>
                                </FloatLabel>
                            </div>

                            <div v-if="isOrangeProvider">
                                <FloatLabel variant="on">
                                    <InputText id="sms-oauth-url" v-model="smsConfig.oauthUrl" class="w-full" />
                                    <label for="sms-oauth-url">OAuth URL</label>
                                </FloatLabel>
                            </div>
                        </div>

                        <Divider class="my-6" />

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/30">
                            <div class="mb-4">
                                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Bypass des préférences SMS patient</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Activez les cas où l'API SMS doit ignorer les préférences portées sur la fiche patient.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div v-for="item in patientPreferenceBypassOptions" :key="item.key" class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-900/70">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ item.label }}</p>
                                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ item.description }}</p>
                                        </div>
                                        <ToggleSwitch v-model="smsConfig.patientPreferenceBypass[item.key]" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Divider class="my-6" />

                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <!-- Approved Sender Names -->
                            <div v-if="isOrangeProvider" class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/30">
                                <div class="mb-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Sender Names approuvés</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajoutez ici les Sender Names déjà whitelistés dans votre portail Orange Developer.</p>
                                </div>

                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                                    <FloatLabel variant="on" class="flex-1">
                                        <InputText id="approved-sender-name" v-model="newApprovedSenderName" class="w-full" />
                                        <label for="approved-sender-name">Ajouter un Sender Name whitelisté</label>
                                    </FloatLabel>
                                    <Button label="Ajouter" icon="pi pi-plus" severity="secondary" @click="addApprovedSenderName" />
                                </div>

                                <div v-if="smsConfig.approvedSenderNames.length" class="flex flex-wrap gap-2">
                                    <div v-for="item in smsConfig.approvedSenderNames" :key="item" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2 py-1 dark:border-gray-700 dark:bg-gray-900">
                                        <button type="button" class="flex items-center" @click="applyApprovedSenderName(item)">
                                            <Chip :label="item" />
                                        </button>
                                        <Button icon="pi pi-times" text rounded severity="secondary" size="small" aria-label="Supprimer" @click="removeApprovedSenderName(item)" />
                                    </div>
                                </div>
                                <div v-else class="rounded-lg border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                    Aucun Sender Name enregistré pour le moment.
                                </div>
                            </div>

                            <!-- Quick Test -->
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/30">
                                <div class="mb-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Test rapide</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Saisissez ici un Sender Name déjà validé dans votre portail Orange Developer.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 gap-4">
                                    <FloatLabel variant="on">
                                        <InputText id="sms-test-phone" v-model="testSms.phone" class="w-full" />
                                        <label for="sms-test-phone">Numéro de test</label>
                                    </FloatLabel>
                                    <FloatLabel variant="on">
                                        <InputText id="sms-test-message" v-model="testSms.message" class="w-full" />
                                        <label for="sms-test-message">Message de test</label>
                                    </FloatLabel>
                                </div>
                            </div>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="queue">
                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,1.6fr)]" data-tour="sms-settings.queue">
                        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                            <div class="mb-6 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Programmation</p>
                                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Planifier un SMS</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajoute un message directement dans la file avec une date d’envoi et une répétition bornée.</p>
                                </div>
                                <Button label="Programmer" icon="pi pi-clock" @click="scheduleQueuedSmsAction" />
                            </div>

                            <div class="space-y-4">
                                <FloatLabel variant="on">
                                    <InputText id="queue-phone" v-model="queuedSms.phone" class="w-full" />
                                    <label for="queue-phone">Numéro destinataire</label>
                                </FloatLabel>

                                <FloatLabel variant="on">
                                    <DatePicker id="queue-send-at" v-model="queuedSms.sendAt" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
                                    <label for="queue-send-at">Date et heure d’envoi</label>
                                </FloatLabel>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Répétition</label>
                                    <Select v-model="queuedSms.recurrence" :options="queueRecurrenceOptions" optionLabel="label" optionValue="value" class="w-full" />
                                </div>

                                <FloatLabel variant="on">
                                    <Textarea id="queue-message" v-model="queuedSms.message" rows="6" autoResize class="w-full" />
                                    <label for="queue-message">Message à programmer</label>
                                </FloatLabel>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                            <div class="mb-6 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Suivi</p>
                                    <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">File d’attente SMS</h3>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Tag severity="contrast" :value="`${smsQueue.length} élément(s)`" />
                                    <Button label="Agrandir" icon="pi pi-external-link" severity="secondary" outlined @click="queueDialogVisible = true" />
                                </div>
                            </div>

                            <DataTable :value="smsQueue" paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]" dataKey="id" responsiveLayout="scroll" stripedRows showGridlines class="text-sm" data-tour="sms-settings.queue-actions">
                                <template #empty>
                                    <div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Aucun SMS en file pour le moment.
                                    </div>
                                </template>
                                <Column field="createdAt" header="Créé le" class="whitespace-nowrap" />
                                <Column field="sendAt" header="Prévu le" class="whitespace-nowrap">
                                    <template #body="{ data }">{{ formatDateTime(data.sendAt) }}</template>
                                </Column>
                                <Column field="patient" header="Patient">
                                    <template #body="{ data }">{{ data.patient || '—' }}</template>
                                </Column>
                                <Column field="phone" header="Numéro" class="whitespace-nowrap" />
                                <Column field="message" header="Message">
                                    <template #body="{ data }">
                                        <span class="block max-w-md whitespace-normal break-words">{{ data.message }}</span>
                                    </template>
                                </Column>
                                <Column field="status" header="Statut" class="whitespace-nowrap">
                                    <template #body="{ data }">
                                        <Tag :severity="queueStatusSeverity(data.status)" :value="queueStatusLabel(data.status)" />
                                    </template>
                                </Column>
                                <Column field="source" header="Source" class="whitespace-nowrap" />
                                <Column header="Actions" class="whitespace-nowrap">
                                    <template #body="{ data }">
                                        <div class="flex flex-wrap gap-2">
                                            <Button
                                                v-if="data.status === 'pending'"
                                                icon="pi pi-calendar"
                                                label="Reprogrammer"
                                                size="small"
                                                severity="secondary"
                                                outlined
                                                :loading="smsQueueItemUpdating === data.id && queueActionMode === 'reschedule'"
                                                @click="openQueueActionDialog('reschedule', data)"
                                            />
                                            <Button
                                                v-if="data.status === 'pending'"
                                                icon="pi pi-times"
                                                label="Annuler"
                                                size="small"
                                                severity="danger"
                                                outlined
                                                :loading="smsQueueItemUpdating === data.id && queueActionMode === 'cancel'"
                                                @click="openQueueActionDialog('cancel', data)"
                                            />
                                            <Button
                                                v-if="data.status === 'failed'"
                                                icon="pi pi-refresh"
                                                label="Renvoyer"
                                                size="small"
                                                severity="warning"
                                                outlined
                                                :loading="smsQueueItemUpdating === data.id && queueActionMode === 'retry'"
                                                @click="openQueueActionDialog('retry', data)"
                                            />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                    </div>

                    <Dialog v-model:visible="queueDialogVisible" modal header="File SMS étendue" :style="{ width: 'min(1400px, 98vw)' }">
                        <DataTable :value="smsQueue" paginator :rows="20" :rowsPerPageOptions="[20, 50, 100]" dataKey="id" responsiveLayout="scroll" stripedRows showGridlines class="text-sm">
                            <template #empty>
                                <div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Aucun SMS en file pour le moment.
                                </div>
                            </template>
                            <Column field="createdAt" header="Créé le" class="whitespace-nowrap" />
                            <Column field="sendAt" header="Prévu le" class="whitespace-nowrap">
                                <template #body="{ data }">{{ formatDateTime(data.sendAt) }}</template>
                            </Column>
                            <Column field="patient" header="Patient">
                                <template #body="{ data }">{{ data.patient || '—' }}</template>
                            </Column>
                            <Column field="phone" header="Numéro" class="whitespace-nowrap" />
                            <Column field="message" header="Message">
                                <template #body="{ data }">
                                    <span class="block max-w-xl whitespace-normal break-words">{{ data.message }}</span>
                                </template>
                            </Column>
                            <Column field="status" header="Statut" class="whitespace-nowrap">
                                <template #body="{ data }">
                                    <Tag :severity="queueStatusSeverity(data.status)" :value="queueStatusLabel(data.status)" />
                                </template>
                            </Column>
                            <Column field="source" header="Source" class="whitespace-nowrap" />
                            <Column field="lastError" header="Dernière erreur">
                                <template #body="{ data }">
                                    <span class="block max-w-md whitespace-normal break-words text-xs text-red-600 dark:text-red-300">{{ data.lastError || '—' }}</span>
                                </template>
                            </Column>
                            <Column header="Actions" class="whitespace-nowrap">
                                <template #body="{ data }">
                                    <div class="flex flex-wrap gap-2">
                                        <Button icon="pi pi-eye" label="Détails" size="small" severity="secondary" outlined @click="openQueueDetails(data)" />
                                        <Button v-if="data.status === 'pending'" icon="pi pi-calendar" label="Reprogrammer" size="small" severity="secondary" outlined @click="openQueueActionDialog('reschedule', data)" />
                                        <Button v-if="data.status === 'pending'" icon="pi pi-times" label="Annuler" size="small" severity="danger" outlined @click="openQueueActionDialog('cancel', data)" />
                                        <Button v-if="data.status === 'failed'" icon="pi pi-refresh" label="Renvoyer" size="small" severity="warning" outlined @click="openQueueActionDialog('retry', data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </Dialog>

                    <Dialog v-model:visible="queueDetailsDialogVisible" modal header="Détails SMS" :style="{ width: 'min(900px, 96vw)' }">
                        <div v-if="queueDetailsLoading" class="py-8 text-center">Chargement…</div>
                        <div v-else class="space-y-4">
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                <p><strong>ID:</strong> {{ queueDetailsItem?.id || '—' }}</p>
                                <p><strong>Patient:</strong> {{ queueDetailsItem?.patient || '—' }}</p>
                                <p><strong>Numéro:</strong> {{ queueDetailsItem?.phone || '—' }}</p>
                                <p><strong>Envoyé le:</strong> {{ formatDateTime(queueDetailsItem?.sentAt) }}</p>
                                <p><strong>Planifié le:</strong> {{ formatDateTime(queueDetailsItem?.sendAt) }}</p>
                                <p><strong>Statut:</strong> {{ queueStatusLabel(queueDetailsItem?.status) }}</p>
                                <p><strong>Message:</strong></p>
                                <div class="p-3 rounded bg-white dark:bg-gray-900/50"><pre class="whitespace-pre-wrap">{{ queueDetailsItem?.message }}</pre></div>
                                <p v-if="queueDetailsItem?.lastError"><strong>Dernière erreur:</strong> <span class="text-red-600 dark:text-red-300">{{ queueDetailsItem.lastError }}</span></p>
                            </div>

                            <div>
                                <h4 class="text-sm font-semibold mb-2">Logs associés</h4>
                                <div v-if="(queueDetailsLogs || []).length === 0" class="text-sm text-gray-500">Aucun log récent trouvé pour ce numéro.</div>
                                <div v-else>
                                    <DataTable :value="queueDetailsLogs" dataKey="id" class="text-sm">
                                        <Column field="date" header="Date" />
                                        <Column field="status" header="Statut" />
                                        <Column field="providerMessageId" header="ID fournisseur" />
                                        <Column field="error" header="Erreur">
                                            <template #body="{ data }"><span class="text-xs text-red-600 dark:text-red-300">{{ data.error || '—' }}</span></template>
                                        </Column>
                                        <Column field="message" header="Message" />
                                    </DataTable>
                                </div>
                            </div>
                        </div>
                    </Dialog>

                    <Dialog v-model:visible="queueActionDialogVisible" modal :header="queueActionTitle" :style="{ width: 'min(32rem, 96vw)' }">
                        <div class="space-y-4">
                            <p class="text-sm text-gray-600 dark:text-gray-300">{{ queueActionDescription }}</p>
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-800/40">
                                <p><strong>Destinataire:</strong> {{ queueActionItem?.phone || '—' }}</p>
                                <p><strong>Statut:</strong> {{ queueStatusLabel(queueActionItem?.status) }}</p>
                            </div>
                            <div v-if="queueActionMode === 'reschedule'" class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Nouvelle date d'envoi</label>
                                <DatePicker v-model="queueActionSendAt" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
                            </div>
                        </div>
                        <template #footer>
                            <div class="flex justify-end gap-2">
                                <Button label="Fermer" severity="secondary" outlined @click="closeQueueActionDialog" />
                                <Button
                                    :label="queueActionMode === 'reschedule' ? 'Reprogrammer' : (queueActionMode === 'cancel' ? 'Annuler le SMS' : 'Renvoyer')"
                                    :icon="queueActionMode === 'reschedule' ? 'pi pi-calendar' : (queueActionMode === 'cancel' ? 'pi pi-times' : 'pi pi-refresh')"
                                    :severity="queueActionMode === 'cancel' ? 'danger' : (queueActionMode === 'retry' ? 'warning' : 'primary')"
                                    :loading="smsQueueItemUpdating === queueActionItem?.id"
                                    @click="submitQueueAction"
                                />
                            </div>
                        </template>
                    </Dialog>
                </TabPanel>

                <!-- Logs Tab -->
                <TabPanel value="logs">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Historique</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Logs d'envoi</h3>
                            </div>
                            <Tag severity="contrast" :value="`${logsFiltered.length} résultat(s)`" />
                        </div>

                        <div class="mb-6 grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1.2fr)] items-center">
                            <FloatLabel variant="on">
                                <InputText id="logs-search" v-model="logsSearch" class="w-full" />
                                <label for="logs-search">Recherche libre</label>
                            </FloatLabel>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Statut</label>
                                <Select v-model="logsStatusFilter" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Période</label>
                                <PanelDatePicker v-model="logsDateRange" showIcon dateFormat="dd/mm/yy" class="w-full" fluid />
                            </div>
                        </div>

                        <DataTable
                            :value="logsFiltered"
                            paginator
                            :rows="10"
                            :rowsPerPageOptions="[10, 20, 50]"
                            dataKey="id"
                            responsiveLayout="scroll"
                            stripedRows
                            showGridlines
                            class="text-sm"
                        >
                            <template #empty>
                                <div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Aucun log SMS à afficher avec les filtres actuels.
                                </div>
                            </template>
                            <Column field="date" header="Date" class="whitespace-nowrap"></Column>
                            <Column field="patient" header="Patient" class="whitespace-nowrap">
                                <template #body="{ data }">{{ data.patient || '—' }}</template>
                            </Column>
                            <Column field="phone" header="Numéro" class="whitespace-nowrap"></Column>
                            <Column field="message" header="Message">
                                <template #body="{ data }">
                                    <span class="block max-w-md whitespace-normal break-words">{{ data.message }}</span>
                                </template>
                            </Column>
                            <Column field="status" header="Statut" class="whitespace-nowrap">
                                <template #body="{ data }">
                                    <Tag :severity="logStatusSeverity(data.status)" :value="data.status" />
                                </template>
                            </Column>
                            <Column field="type" header="Type" class="whitespace-nowrap"></Column>
                            <Column field="source" header="Source" class="whitespace-nowrap"></Column>
                        </DataTable>
                    </div>
                </TabPanel>

                <!-- Templates Tab -->
                <TabPanel value="templates">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50" data-tour="sms-settings.templates">
                        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Contenu</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Gestion des templates SMS</h3>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <Button label="Ajouter" icon="pi pi-plus" severity="secondary" @click="addTemplate" />
                                <Button label="Supprimer" icon="pi pi-trash" severity="danger" text :disabled="!selectedTemplateCode" @click="removeSelectedTemplate" />
                                <Button label="Sauvegarder templates" icon="pi pi-save" :loading="smsTemplateSaving" @click="saveTemplatesAction" />
                            </div>
                        </div>

                        <div v-if="smsTemplates.length" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <!-- Template Editor -->
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/30">
                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 gap-4">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Template actif</label>
                                            <Select v-model="selectedTemplateCode" :options="smsTemplates" optionLabel="name" optionValue="code" class="w-full" />
                                        </div>
                                        <FloatLabel variant="on">
                                            <InputText id="template-name" v-model="selectedTemplate.name" class="w-full" :disabled="!selectedTemplate" />
                                            <label for="template-name">Nom du template</label>
                                        </FloatLabel>
                                    </div>
                                    <FloatLabel variant="on">
                                        <Textarea id="template-content" v-if="selectedTemplate" v-model="selectedTemplate.content" rows="12" autoResize class="w-full" />
                                        <label for="template-content">Contenu du message</label>
                                    </FloatLabel>
                                </div>
                            </div>

                            <!-- Preview Section -->
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/30" data-tour="sms-settings.template-preview">
                                <div class="mb-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Variables dynamiques</h4>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ajustez les variables puis générez un aperçu.</p>
                                </div>

                                <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <FloatLabel variant="on">
                                        <InputText id="prev-patient" v-model="previewVariables.patient_name" class="w-full" />
                                        <label for="prev-patient">{patient_name}</label>
                                    </FloatLabel>
                                    <FloatLabel variant="on">
                                        <InputText id="prev-date" v-model="previewVariables.date" class="w-full" />
                                        <label for="prev-date">{date}</label>
                                    </FloatLabel>
                                    <FloatLabel variant="on">
                                        <InputText id="prev-time" v-model="previewVariables.time" class="w-full" />
                                        <label for="prev-time">{time}</label>
                                    </FloatLabel>
                                    <FloatLabel variant="on">
                                        <InputText id="prev-amount" v-model="previewVariables.amount" class="w-full" />
                                        <label for="prev-amount">{amount}</label>
                                    </FloatLabel>
                                    <FloatLabel variant="on">
                                        <InputText id="prev-invoice" v-model="previewVariables.invoice_number" class="w-full" />
                                        <label for="prev-invoice">{invoice_number}</label>
                                    </FloatLabel>
                                    <FloatLabel variant="on">
                                        <InputText id="prev-cabinet" v-model="previewVariables.cabinet_name" class="w-full" />
                                        <label for="prev-cabinet">{cabinet_name}</label>
                                    </FloatLabel>
                                </div>

                                <Button label="Prévisualiser" icon="pi pi-eye" severity="secondary" class="mb-4" @click="previewTemplateAction" />

                                <Textarea v-model="previewResult" rows="6" autoResize class="w-full" readonly />

                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ previewCharacters }} caractères · estimation {{ previewEstimatedSms }} SMS
                                </p>
                            </div>
                        </div>

                        <div v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                            Aucun template SMS configuré.
                        </div>
                    </div>
                </TabPanel>

                <!-- Manual Send Tab -->
                <TabPanel value="manual">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50" data-tour="sms-settings.manual-send">
                        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Action directe</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Envoi manuel</h3>
                            </div>
                            <Button label="Envoyer" icon="pi pi-send" @click="sendManualSmsAction" />
                        </div>

                        <div class="flex flex-col gap-6 md:grid md:grid-cols-2 items-center">
                            <FloatLabel variant="on">
                                <InputText id="manual-phone" v-model="manualSms.phone" class="w-full" />
                                <label for="manual-phone">Numéro</label>
                            </FloatLabel>

                            <div class="space-y-2">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Pré-remplir depuis un template</label>
                                <Select v-model="manualTemplateCode" :options="smsTemplates" optionLabel="name" optionValue="code" placeholder="Choisir un template" class="w-full" />
                            </div>

                            <div class="md:col-span-2 space-y-2">
                                <FloatLabel variant="on">
                                    <Textarea id="manual-message" v-model="manualSms.message" rows="5" autoResize class="w-full" />
                                    <label for="manual-message">Message à envoyer</label>
                                </FloatLabel>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ manualSms.message.length }} caractères · estimation {{ Math.max(1, Math.ceil(Math.max(1, manualSms.message.length) / 160)) }} SMS
                                </p>
                            </div>
                        </div>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
        </template>
    </div>
</template>

<style scoped>
:deep(.p-floatlabel .p-inputtext) {
    min-height: 3rem; /* Adjust based on label size */
}

</style>
