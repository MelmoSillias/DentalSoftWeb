<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useGuidedTour } from '@/composables/useGuidedTour';
import { activateSmsTourMock, deactivateSmsTourMock, fetchSmsOverviewTourMock, fetchSmsQueueTourMock, fetchSmsTemplatesTourMock, resetSmsTourMockData, resolveSmsTourMockScenario } from '@/services/smsTourMock';
import { useToast } from 'primevue/usetoast';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import Button from 'primevue/button';
import Chip from 'primevue/chip';
import Column from 'primevue/column';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import AppDialog from '@/components/layout/AppDialog.vue';
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
import AppChart from '@/components/common/AppChart.vue';
import ToggleSwitch from 'primevue/toggleswitch';
import { useSmsAdminSettings, SMS_PROVIDER_OPTIONS, SMS_CALLBACK_NOTIFY_OPTIONS } from '@/composables/useSmsAdminSettings';
import { fetchSmsQueueDetails } from '@/services/smsService';
import { getHttpErrorMessage } from '@/service/http';

const toast = useToast();
const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'API SMS' }];
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

const hasOpenDialogs = computed(() => queueDialogVisible.value || queueActionDialogVisible.value || queueDetailsDialogVisible.value);

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
    queueRefreshing,
    logsRefreshing,
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
    refreshSmsQueue,
    refreshSmsLogs,
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
        appointment_cancelled: 'Annulation RDV',
        appointment_rescheduled: 'Report RDV',
        'appointment change': 'Modification RDV',
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
        case 'sent':
            return 'Envoyé';
        case 'failed':
            return 'Échec';
        case 'sending':
            return 'Envoi';
        case 'cancelled':
            return 'Annulé';
        default:
            return 'En attente';
    }
};

const queueStatusSeverity = (status) => {
    switch (status) {
        case 'sent':
            return 'success';
        case 'failed':
            return 'danger';
        case 'sending':
            return 'info';
        case 'cancelled':
            return 'secondary';
        default:
            return 'warning';
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

const queueActionConfirmLabel = computed(() => {
    if (queueActionMode.value === 'reschedule') return 'Reprogrammer';
    if (queueActionMode.value === 'cancel') return 'Annuler le SMS';
    return 'Renvoyer';
});

const queueActionConfirmIcon = computed(() => {
    if (queueActionMode.value === 'reschedule') return 'pi pi-calendar';
    if (queueActionMode.value === 'cancel') return 'pi pi-times';
    return 'pi pi-refresh';
});

const queueActionConfirmSeverity = computed(() => {
    if (queueActionMode.value === 'cancel') return 'danger';
    if (queueActionMode.value === 'retry') return 'warning';
    return 'primary';
});

const openQueueActionDialog = (mode, item) => {
    queueActionMode.value = mode;
    queueActionItem.value = item;
    queueActionSendAt.value = mode === 'reschedule' ? (item?.sendAt ? new Date(item.sendAt) : new Date()) : null;
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

        await updateQueueItemAction(queueActionItem.value.id, { action: 'reschedule', sendAt: queueActionSendAt.value.toISOString() }, 'SMS reprogrammé.');
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

const totalCharacters = computed(() => smsTemplates.value.reduce((sum, template) => sum + String(template?.content || '').length, 0));
const recommendedContract = computed(() => providerOverview.value.contracts.find((item) => item.isRecommended) || providerOverview.value.contracts[0] || null);
const isOrangeProvider = computed(() => smsConfig.provider === 'orange');
const isAfrikSmsProvider = computed(() => smsConfig.provider === 'afriksms');
const providerLabel = computed(() => SMS_PROVIDER_OPTIONS.find((item) => item.value === smsConfig.provider)?.label || smsConfig.provider || '—');
const providerOverviewTitle = computed(() => (isAfrikSmsProvider.value ? 'Solde AfrikSms' : 'Contrat Orange'));
const providerOverviewEmptyMessage = computed(() =>
    isAfrikSmsProvider.value ? providerOverview.value.message || 'Aucun solde AfrikSms disponible pour le moment.' : providerOverview.value.message || 'Aucun contrat Orange disponible pour le moment.'
);
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
    return date.toLocaleDateString('fr-FR', short ? { day: '2-digit', month: 'short' } : { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
};

const readChartTheme = () => {
    const documentStyle = getComputedStyle(document.documentElement);
    const primary = documentStyle.getPropertyValue('--p-primary-color').trim() || '#3b82f6';
    return {
        primary,
        textColorSecondary: documentStyle.getPropertyValue('--text-color-secondary'),
        surfaceBorder: documentStyle.getPropertyValue('--surface-border'),
        surfaceCard: documentStyle.getPropertyValue('--surface-card').trim() || '#ffffff'
    };
};

const periodDailyChartData = computed(() => {
    const labels = periodDailySeries.value.map(([day]) => formatPeriodDayLabel(day));
    const values = periodDailySeries.value.map(([, count]) => Number(count) || 0);
    const { primary, surfaceCard } = readChartTheme();

    return {
        labels,
        datasets: [
            {
                label: 'SMS envoyés',
                data: values,
                fill: true,
                tension: 0.35,
                borderColor: primary,
                backgroundColor: `color-mix(in srgb, ${primary} 16%, transparent)`,
                pointBackgroundColor: primary,
                pointBorderColor: surfaceCard,
                pointHoverBackgroundColor: surfaceCard,
                pointHoverBorderColor: primary,
                pointRadius: 4,
                pointHoverRadius: 6
            }
        ]
    };
});

const periodDailyChartOptions = computed(() => {
    const { textColorSecondary, surfaceBorder } = readChartTheme();

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
            const haystack = [log.patient, log.phone, log.message, log.status, log.type, log.source].filter(Boolean).join(' ').toLowerCase();

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
    <PageShell>
        <template #header>
            <PageHeader
                title="API SMS"
                subtitle="Configuration du fournisseur, supervision du trafic, templates et file d'envoi."
                icon="pi pi-comment"
                tour-id="sms-settings.overview"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            >
                <template #actions>
                    <Button label="Rafraîchir" icon="pi pi-refresh" severity="secondary" outlined size="small" :loading="smsLoading" :disabled="Boolean(loadErrorMessage)" @click="refreshSmsData" />
                    <Button label="Traiter file" icon="pi pi-play" size="small" :loading="smsQueueing" :disabled="Boolean(loadErrorMessage)" @click="processQueueAction" />
                </template>
                <template v-if="!loadErrorMessage" #below>
                    <div class="sms-status" data-tour="sms-settings.status" :class="smsAutomationOperational ? 'sms-status--ok' : 'sms-status--warn'">
                        <i :class="smsAutomationOperational ? 'pi pi-check-circle' : 'pi pi-exclamation-triangle'" class="sms-status__icon"></i>
                        <div>
                            <div class="sms-status__row">
                                <span class="sms-status__title">{{ smsAutomationStatusLabel }}</span>
                                <Tag :severity="smsAutomationStatusSeverity" :value="smsConfig.enabled ? 'Activé' : 'Désactivé'" />
                            </div>
                            <p class="sms-status__detail">{{ smsAutomationStatusDetail }}</p>
                        </div>
                    </div>
                </template>
            </PageHeader>
        </template>

        <div v-if="loadErrorMessage" class="sms-alert">
            <div class="sms-alert__icon">
                <i class="pi pi-exclamation-triangle"></i>
            </div>
            <div>
                <p class="sms-alert__title">Chargement interrompu</p>
                <p class="sms-alert__detail">{{ loadErrorMessage }}</p>
            </div>
            <Button icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryLoadSmsSettings" />
        </div>

        <Tabs v-else class="sms-tabs" :value="activeTab" @update:value="activeTab = $event">
                <TabList data-tour="sms-settings.tabs">
                    <Tab v-for="item in tabItems" :key="item.value" :value="item.value">
                        <span class="sms-tab-label">
                            <i :class="item.icon"></i>
                            <span>{{ item.label }}</span>
                        </span>
                    </Tab>
                </TabList>

                <TabPanels class="sms-panels">
                    <TabPanel value="overview">
                        <div class="sms-stack">
                            <div v-if="smsLoading && !smsLoaded" class="sms-grid-2">
                                <PageSection padded>
                                    <Skeleton height="8rem" />
                                </PageSection>
                                <PageSection padded>
                                    <Skeleton height="8rem" />
                                </PageSection>
                            </div>

                            <template v-else>
                                <div class="page-kpi-grid">
                                    <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                                        <div>
                                            <p class="page-kpi-label text-blue-700 dark:text-blue-300">Provider</p>
                                            <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ providerLabel }}</p>
                                            <p class="sms-kpi-meta text-blue-700/80 dark:text-blue-300/80">{{ smsConfig.enabled ? 'Actif' : 'Désactivé' }}</p>
                                        </div>
                                        <i class="pi pi-megaphone page-kpi-icon text-blue-500"></i>
                                    </div>
                                    <div class="page-kpi-card border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                                        <div>
                                            <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">SMS envoyés aujourd'hui</p>
                                            <p class="page-kpi-value text-emerald-900 dark:text-emerald-100">{{ smsStats.balance.sentToday }}</p>
                                            <p class="sms-kpi-meta text-emerald-700/80 dark:text-emerald-300/80">Dernières 24h</p>
                                        </div>
                                        <i class="pi pi-send page-kpi-icon text-emerald-500"></i>
                                    </div>
                                    <div class="page-kpi-card border-violet-200/50 bg-gradient-to-br from-violet-50 to-violet-100/50 dark:border-violet-800/50 dark:from-violet-900/20 dark:to-violet-800/20">
                                        <div>
                                            <p class="page-kpi-label text-violet-700 dark:text-violet-300">SMS envoyés ce mois</p>
                                            <p class="page-kpi-value text-violet-900 dark:text-violet-100">{{ smsStats.balance.sentMonth }}</p>
                                            <p class="sms-kpi-meta text-violet-700/80 dark:text-violet-300/80">{{ trafficTrend >= 0 ? '+' : '' }}{{ trafficTrend }} vs jour précédent</p>
                                        </div>
                                        <i class="pi pi-chart-line page-kpi-icon text-violet-500"></i>
                                    </div>
                                    <div class="page-kpi-card border-amber-200/50 bg-gradient-to-br from-amber-50 to-amber-100/50 dark:border-amber-800/50 dark:from-amber-900/20 dark:to-amber-800/20">
                                        <div>
                                            <p class="page-kpi-label text-amber-700 dark:text-amber-300">Templates actifs</p>
                                            <p class="page-kpi-value text-amber-900 dark:text-amber-100">{{ smsTemplates.length }}</p>
                                            <p class="sms-kpi-meta text-amber-700/80 dark:text-amber-300/80">{{ totalCharacters }} caractères cumulés</p>
                                        </div>
                                        <i class="pi pi-file page-kpi-icon text-amber-500"></i>
                                    </div>
                                </div>

                                <PageSection title="Statistiques détaillées" :subtitle="statsPeriodLabel">
                                    <template #headerActions>
                                        <div class="sms-inline-actions">
                                            <PanelDatePicker v-model="statsPeriodRange" showIcon dateFormat="dd/mm/yy" class="w-72" placeholder="Choisir période" />
                                            <Button label="Rafraîchir" icon="pi pi-refresh" outlined :loading="smsPeriodLoading" @click="refreshPeriodStats(false)" />
                                        </div>
                                    </template>
                                    <div class="sms-pad">
                                        <div class="page-kpi-grid page-kpi-grid--compact">
                                            <div class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">Envoyés</p>
                                                    <p class="page-kpi-value">{{ smsStats.period.sent }}</p>
                                                </div>
                                            </div>
                                            <div class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">Échecs</p>
                                                    <p class="page-kpi-value">{{ smsStats.period.failed }}</p>
                                                </div>
                                            </div>
                                            <div class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">Total tentatives</p>
                                                    <p class="page-kpi-value">{{ smsStats.period.total }}</p>
                                                </div>
                                            </div>
                                            <div class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">Taux de succès</p>
                                                    <p class="page-kpi-value">{{ smsStats.period.successRate }}%</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="sms-grid-2">
                                            <div>
                                                <div class="sms-block-head">
                                                    <h4>Trafic journalier (période)</h4>
                                                    <Tag severity="info" :value="`${periodDailySeries.length} jour(s)`" />
                                                </div>
                                                <div v-if="periodDailySeries.length" class="sms-inset sms-chart">
                                                    <AppChart type="line" :data="periodDailyChartData" :options="periodDailyChartOptions" class="h-full w-full" />
                                                </div>
                                                <div v-else class="sms-empty">Aucun envoi sur cette période.</div>
                                            </div>

                                            <div>
                                                <div class="sms-block-head">
                                                    <h4>Répartition par type</h4>
                                                    <Tag severity="secondary" :value="`${periodByType.length} type(s)`" />
                                                </div>
                                                <div v-if="periodByType.length" class="sms-bars">
                                                    <div v-for="[type, count] in periodByType" :key="type" class="sms-bar-row">
                                                        <span class="sms-bar-label">{{ formatSmsTypeLabel(type) }}</span>
                                                        <div class="sms-bar">
                                                            <div class="sms-bar__fill" :style="{ width: `${Math.round((Number(count) / maxPeriodByType) * 100)}%` }" />
                                                        </div>
                                                        <span class="sms-bar-count">{{ count }}</span>
                                                    </div>
                                                </div>
                                                <div v-else class="sms-empty">Aucune répartition disponible pour cette période.</div>
                                            </div>
                                        </div>
                                    </div>
                                </PageSection>

                                <div class="sms-grid-2">
                                    <PageSection title="Trafic journalier" subtitle="Tendance">
                                        <template #headerActions>
                                            <Tag severity="success" :value="`${smsStats.balance.totalSent} total`" />
                                        </template>
                                        <div class="sms-pad">
                                            <div v-if="dailySeries.length" class="sms-bars">
                                                <div v-for="[day, count] in dailySeries" :key="day" class="sms-bar-row">
                                                    <span class="sms-bar-label sms-bar-label--date">{{ day }}</span>
                                                    <div class="sms-bar">
                                                        <div class="sms-bar__fill" :style="{ width: `${Math.round((Number(count) / maxDaily) * 100)}%` }" />
                                                    </div>
                                                    <span class="sms-bar-count">{{ count }}</span>
                                                </div>
                                            </div>
                                            <div v-else class="sms-empty">Aucune consommation journalière disponible.</div>
                                        </div>
                                    </PageSection>

                                    <PageSection title="Dernier test" subtitle="État">
                                        <div class="sms-pad">
                                            <div class="sms-inset">
                                                <p class="sms-kicker">Dernière vérification</p>
                                                <p class="sms-emphasis">{{ formatDateTime(lastTestAt) }}</p>
                                                <p class="sms-meta">{{ lastTestResult?.message || 'Aucun test effectué pour le moment.' }}</p>
                                            </div>
                                            <Tag v-if="lastTestResult" :severity="lastTestResult.success ? 'success' : 'danger'" :value="lastTestResult.kind === 'send' ? 'Envoi de test' : 'Connexion API'" />
                                            <p class="sms-meta">Les détails de forfait et de crédits restants ne sont pas exposés par votre backend actuel. La page affiche donc le trafic réellement historisé dans DentalSoft.</p>
                                        </div>
                                    </PageSection>
                                </div>

                                <PageSection :title="isAfrikSmsProvider ? 'Crédits par pays' : 'Forfait et disponibilité'" :subtitle="providerOverviewTitle">
                                    <template #headerActions>
                                        <Tag :severity="providerOverview.success ? 'success' : 'warn'" :value="providerOverview.success ? 'Synchronisé' : 'Indisponible'" />
                                    </template>
                                    <div class="sms-pad">
                                        <div v-if="recommendedContract" class="page-kpi-grid page-kpi-grid--compact">
                                            <div class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">Offre</p>
                                                    <p class="page-kpi-value">{{ recommendedContract.offerName || '—' }}</p>
                                                    <p class="sms-kpi-meta">{{ recommendedContract.country || '—' }}</p>
                                                </div>
                                            </div>
                                            <div class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">{{ isAfrikSmsProvider ? 'SMS restants' : 'Unités restantes' }}</p>
                                                    <p class="page-kpi-value">{{ recommendedContract.availableUnits ?? '—' }}</p>
                                                    <p class="sms-kpi-meta">{{ isAfrikSmsProvider ? 'Solde recommandé' : 'Contrat recommandé' }}</p>
                                                </div>
                                            </div>
                                            <div v-if="!isAfrikSmsProvider" class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">Statut</p>
                                                    <p class="page-kpi-value">{{ recommendedContract.status || '—' }}</p>
                                                    <p class="sms-kpi-meta">Type {{ recommendedContract.type || '—' }}</p>
                                                </div>
                                            </div>
                                            <div v-if="!isAfrikSmsProvider" class="page-kpi-card">
                                                <div>
                                                    <p class="page-kpi-label">Expiration</p>
                                                    <p class="page-kpi-value">{{ formatDateTime(recommendedContract.expirationDate) }}</p>
                                                    <p class="sms-kpi-meta">{{ providerOverview.message || 'Données Orange' }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="sms-empty">{{ providerOverviewEmptyMessage }}</div>
                                    </div>
                                </PageSection>
                            </template>
                        </div>
                    </TabPanel>

                    <!-- Configuration Tab -->
                    <TabPanel value="config">
                        <PageSection title="Configuration & test" subtitle="Configuration" tour-id="sms-settings.config">
                            <template #headerActions>
                                <div class="sms-inline-actions">
                                    <Button label="Test connexion" icon="pi pi-bolt" severity="secondary" :loading="smsTesting" data-tour="sms-settings.test-connection" @click="testConnectionAction" />
                                    <Button label="Envoyer SMS test" icon="pi pi-send" severity="info" :loading="smsSendingTest" @click="sendSmsTestAction" />
                                    <Button label="Sauvegarder" icon="pi pi-save" :loading="smsSaving" data-tour="sms-settings.save-config" @click="saveSmsConfigAction" />
                                </div>
                            </template>
                            <div class="sms-pad">
                            <div class="sms-field-grid">
                                <div class="space-y-2">
                                    <label class="sms-field-label">Provider</label>
                                    <Select id="sms-provider" v-model="smsConfig.provider" :options="SMS_PROVIDER_OPTIONS" optionLabel="label" optionValue="value" class="w-full" @update:modelValue="applyProviderDefaults" />
                                </div>

                                <div class="space-y-2">
                                    <label class="sms-field-label">Activation</label>
                                    <SelectButton
                                        v-model="smsConfig.enabled"
                                        :options="[
                                            { label: 'Activé', value: true },
                                            { label: 'Désactivé', value: false }
                                        ]"
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
                                    <p class="sms-meta">Pour Orange Mali, utilisez d'abord le sender technique standard tel:+2230000.</p>
                                </div>

                                <div class="space-y-3">
                                    <label class="sms-field-label">
                                        {{ isAfrikSmsProvider ? 'SenderId' : 'Sender Name' }}
                                        <span v-if="isAfrikSmsProvider" class="sms-required">*</span>
                                    </label>
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
                                    <p class="sms-meta">
                                        {{ isAfrikSmsProvider ? 'Obligatoire pour AfrikSms. 11 caractères maximum.' : 'Optionnel. Doit être whitelisté par Orange et limité à 11 caractères alphanumériques ou espaces.' }}
                                    </p>
                                </div>

                                <div v-if="isAfrikSmsProvider" class="space-y-2">
                                    <FloatLabel variant="on">
                                        <InputText id="sms-webhook-base-url" v-model="smsConfig.webhookBaseUrl" class="w-full" />
                                        <label for="sms-webhook-base-url">URL publique du backend</label>
                                    </FloatLabel>
                                    <p class="sms-meta">Ex: https://cabinet.example.com — utilisée pour enregistrer /api/sms/webhooks/afriksms chez AfrikSms.</p>
                                </div>

                                <div v-if="isAfrikSmsProvider" class="space-y-2">
                                    <label class="sms-field-label">Méthode callback DLR</label>
                                    <Select v-model="smsConfig.callbackNotifyType" :options="SMS_CALLBACK_NOTIFY_OPTIONS" optionLabel="label" optionValue="value" class="w-full" />
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

                            <div class="sms-inset">
                                <div class="mb-4">
                                    <h4 class="sms-subhead">Bypass des préférences SMS patient</h4>
                                    <p class="sms-meta">Activez les cas où l'API SMS doit ignorer les préférences portées sur la fiche patient.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div v-for="item in patientPreferenceBypassOptions" :key="item.key" class="sms-inset sms-bypass-card">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="sms-subhead">{{ item.label }}</p>
                                                <p class="sms-meta">{{ item.description }}</p>
                                            </div>
                                            <ToggleSwitch v-model="smsConfig.patientPreferenceBypass[item.key]" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Divider class="my-6" />

                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <!-- Approved Sender Names -->
                                <div v-if="isOrangeProvider" class="sms-inset">
                                    <div class="mb-4">
                                        <h4 class="sms-subhead">Sender Names approuvés</h4>
                                        <p class="sms-meta">Ajoutez ici les Sender Names déjà whitelistés dans votre portail Orange Developer.</p>
                                    </div>

                                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                                        <FloatLabel variant="on" class="flex-1">
                                            <InputText id="approved-sender-name" v-model="newApprovedSenderName" class="w-full" />
                                            <label for="approved-sender-name">Ajouter un Sender Name whitelisté</label>
                                        </FloatLabel>
                                        <Button label="Ajouter" icon="pi pi-plus" severity="secondary" @click="addApprovedSenderName" />
                                    </div>

                                    <div v-if="smsConfig.approvedSenderNames.length" class="flex flex-wrap gap-2">
                                        <div v-for="item in smsConfig.approvedSenderNames" :key="item" class="sms-chip">
                                            <button type="button" class="flex items-center" @click="applyApprovedSenderName(item)">
                                                <Chip :label="item" />
                                            </button>
                                            <Button icon="pi pi-times" text rounded severity="secondary" size="small" aria-label="Supprimer" @click="removeApprovedSenderName(item)" />
                                        </div>
                                    </div>
                                    <div v-else class="sms-empty">Aucun Sender Name enregistré pour le moment.</div>
                                </div>

                                <!-- Quick Test -->
                                <div class="sms-inset">
                                    <div class="mb-4">
                                        <h4 class="sms-subhead">Test rapide</h4>
                                        <p class="sms-meta">Saisissez ici un Sender Name déjà validé dans votre portail Orange Developer.</p>
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
                        </PageSection>
                    </TabPanel>

                    <TabPanel value="queue">
                        <div class="sms-grid-queue" data-tour="sms-settings.queue">
                            <PageSection title="Planifier un SMS" subtitle="Ajoute un message directement dans la file avec une date d’envoi et une répétition bornée.">
                                <template #headerActions>
                                    <Button label="Programmer" icon="pi pi-clock" @click="scheduleQueuedSmsAction" />
                                </template>
                                <div class="sms-pad sms-fields">
                                    <FloatLabel variant="on">
                                        <InputText id="queue-phone" v-model="queuedSms.phone" class="w-full" />
                                        <label for="queue-phone">Numéro destinataire <span class="sms-required">*</span></label>
                                    </FloatLabel>

                                    <FloatLabel variant="on">
                                        <DatePicker id="queue-send-at" v-model="queuedSms.sendAt" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
                                        <label for="queue-send-at">Date et heure d’envoi</label>
                                    </FloatLabel>

                                    <div class="space-y-2">
                                        <label class="sms-field-label">Répétition</label>
                                        <Select v-model="queuedSms.recurrence" :options="queueRecurrenceOptions" optionLabel="label" optionValue="value" class="w-full" />
                                    </div>

                                    <FloatLabel variant="on">
                                        <Textarea id="queue-message" v-model="queuedSms.message" rows="6" autoResize class="w-full" />
                                        <label for="queue-message">Message à programmer <span class="sms-required">*</span></label>
                                    </FloatLabel>
                                </div>
                            </PageSection>

                            <PageSection title="File d’attente SMS" subtitle="Suivi">
                                <template #headerActions>
                                    <div class="sms-inline-actions">
                                        <Tag severity="contrast" :value="`${smsQueue.length} élément(s)`" />
                                        <Button label="Rafraîchir" icon="pi pi-refresh" severity="secondary" outlined size="small" :loading="queueRefreshing" @click="refreshSmsQueue" />
                                        <Button label="Agrandir" icon="pi pi-external-link" severity="secondary" outlined size="small" @click="queueDialogVisible = true" />
                                    </div>
                                </template>

                                <div class="page-table-scroll">
                                <DataTable :value="smsQueue" paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]" dataKey="id" responsiveLayout="scroll" stripedRows showGridlines class="text-sm" data-tour="sms-settings.queue-actions">
                                    <template #empty>
                                        <div class="sms-empty">Aucun SMS en file pour le moment.</div>
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
                            </PageSection>
                        </div>

                        <AppDialog
                            v-model:visible="queueDialogVisible"
                            title="File SMS étendue"
                            icon="pi pi-list"
                            icon-tone="info"
                            size="full"
                            :show-footer="false"
                        >
                            <DataTable :value="smsQueue" paginator :rows="20" :rowsPerPageOptions="[20, 50, 100]" dataKey="id" responsiveLayout="scroll" stripedRows showGridlines class="text-sm">
                                <template #empty>
                                    <div class="sms-empty">Aucun SMS en file pour le moment.</div>
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
                                        <span class="block max-w-md whitespace-normal break-words text-xs sms-error">{{ data.lastError || '—' }}</span>
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
                        </AppDialog>

                        <AppDialog
                            v-model:visible="queueDetailsDialogVisible"
                            title="Détails SMS"
                            icon="pi pi-envelope"
                            icon-tone="info"
                            size="xl"
                            :show-footer="false"
                        >
                            <div v-if="queueDetailsLoading" class="py-8 text-center">Chargement…</div>
                            <div v-else class="space-y-4">
                                <div class="sms-inset">
                                    <p><strong>ID:</strong> {{ queueDetailsItem?.id || '—' }}</p>
                                    <p><strong>Patient:</strong> {{ queueDetailsItem?.patient || '—' }}</p>
                                    <p><strong>Numéro:</strong> {{ queueDetailsItem?.phone || '—' }}</p>
                                    <p><strong>Envoyé le:</strong> {{ formatDateTime(queueDetailsItem?.sentAt) }}</p>
                                    <p><strong>Planifié le:</strong> {{ formatDateTime(queueDetailsItem?.sendAt) }}</p>
                                    <p><strong>Statut:</strong> {{ queueStatusLabel(queueDetailsItem?.status) }}</p>
                                    <p><strong>Message:</strong></p>
                                    <div class="sms-message">
                                        <pre class="whitespace-pre-wrap">{{ queueDetailsItem?.message }}</pre>
                                    </div>
                                    <p v-if="queueDetailsItem?.lastError">
                                        <strong>Dernière erreur:</strong> <span class="sms-error">{{ queueDetailsItem.lastError }}</span>
                                    </p>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold mb-2">Logs associés</h4>
                                    <div v-if="(queueDetailsLogs || []).length === 0" class="sms-meta">Aucun log récent trouvé pour ce numéro.</div>
                                    <div v-else>
                                        <DataTable :value="queueDetailsLogs" dataKey="id" class="text-sm">
                                            <Column field="date" header="Date" />
                                            <Column field="status" header="Statut" />
                                            <Column field="providerMessageId" header="ID fournisseur" />
                                            <Column field="error" header="Erreur">
                                                <template #body="{ data }"
                                                    ><span class="text-xs sms-error">{{ data.error || '—' }}</span></template
                                                >
                                            </Column>
                                            <Column field="message" header="Message" />
                                        </DataTable>
                                    </div>
                                </div>
                            </div>
                        </AppDialog>

                        <AppDialog
                            v-model:visible="queueActionDialogVisible"
                            :title="queueActionTitle"
                            :icon="queueActionConfirmIcon"
                            :icon-tone="queueActionConfirmSeverity"
                            size="sm"
                            :loading="smsQueueItemUpdating === queueActionItem?.id"
                            cancel-label="Fermer"
                            :confirm-label="queueActionConfirmLabel"
                            :confirm-icon="queueActionConfirmIcon"
                            :confirm-severity="queueActionConfirmSeverity"
                            @cancel="closeQueueActionDialog"
                            @confirm="submitQueueAction"
                        >
                            <div class="space-y-4">
                                <p class="sms-meta">{{ queueActionDescription }}</p>
                                <div class="sms-inset">
                                    <p><strong>Destinataire:</strong> {{ queueActionItem?.phone || '—' }}</p>
                                    <p><strong>Statut:</strong> {{ queueStatusLabel(queueActionItem?.status) }}</p>
                                </div>
                                <div v-if="queueActionMode === 'reschedule'" class="space-y-2">
                                    <label class="sms-field-label">Nouvelle date d'envoi</label>
                                    <DatePicker v-model="queueActionSendAt" showTime hourFormat="24" dateFormat="dd/mm/yy" class="w-full" />
                                </div>
                            </div>
                        </AppDialog>
                    </TabPanel>

                    <!-- Logs Tab -->
                    <TabPanel value="logs">
                        <PageSection title="Logs d'envoi" subtitle="Historique">
                            <template #headerActions>
                                <div class="sms-inline-actions">
                                    <Tag severity="contrast" :value="`${logsFiltered.length} résultat(s)`" />
                                    <Button label="Rafraîchir" icon="pi pi-refresh" severity="secondary" outlined size="small" :loading="logsRefreshing" @click="refreshSmsLogs" />
                                </div>
                            </template>

                            <div class="sms-pad sms-logs-filters">
                                <FloatLabel variant="on">
                                    <InputText id="logs-search" v-model="logsSearch" class="w-full" />
                                    <label for="logs-search">Recherche libre</label>
                                </FloatLabel>

                                <div class="space-y-2">
                                    <label class="sms-field-label">Statut</label>
                                    <Select v-model="logsStatusFilter" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                                </div>

                                <div class="space-y-2">
                                    <label class="sms-field-label">Période</label>
                                    <PanelDatePicker v-model="logsDateRange" showIcon dateFormat="dd/mm/yy" class="w-full" fluid />
                                </div>
                            </div>

                            <div class="page-table-scroll">
                            <DataTable :value="logsFiltered" paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]" dataKey="id" responsiveLayout="scroll" stripedRows showGridlines class="text-sm">
                                <template #empty>
                                    <div class="sms-empty">Aucun log SMS à afficher avec les filtres actuels.</div>
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
                        </PageSection>
                    </TabPanel>

                    <TabPanel value="templates">
                        <PageSection title="Gestion des templates SMS" subtitle="Contenu" tour-id="sms-settings.templates">
                            <template #headerActions>
                                <div class="sms-inline-actions">
                                    <Button label="Ajouter" icon="pi pi-plus" severity="secondary" @click="addTemplate" />
                                    <Button label="Supprimer" icon="pi pi-trash" severity="danger" text :disabled="!selectedTemplateCode" @click="removeSelectedTemplate" />
                                    <Button label="Sauvegarder templates" icon="pi pi-save" :loading="smsTemplateSaving" @click="saveTemplatesAction" />
                                </div>
                            </template>
                            <div class="sms-pad">

                            <div v-if="smsTemplates.length" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <!-- Template Editor -->
                                <div class="sms-inset">
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 gap-4">
                                            <div class="space-y-2">
                                                <label class="sms-field-label">Template actif</label>
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
                                <div class="sms-inset" data-tour="sms-settings.template-preview">
                                    <div class="mb-4">
                                        <h4 class="sms-subhead">Variables dynamiques</h4>
                                        <p class="sms-meta">Ajustez les variables puis générez un aperçu.</p>
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
                                            <InputText id="prev-new-date" v-model="previewVariables.new_date" class="w-full" />
                                            <label for="prev-new-date">{new_date}</label>
                                        </FloatLabel>
                                        <FloatLabel variant="on">
                                            <InputText id="prev-new-time" v-model="previewVariables.new_time" class="w-full" />
                                            <label for="prev-new-time">{new_time}</label>
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

                                    <p class="sms-meta">{{ previewCharacters }} caractères · estimation {{ previewEstimatedSms }} SMS</p>
                                </div>
                            </div>

                            <div v-else class="sms-empty">Aucun template SMS configuré.</div>
                            </div>
                        </PageSection>
                    </TabPanel>

                    <TabPanel value="manual">
                        <PageSection title="Envoi manuel" subtitle="Action directe" tour-id="sms-settings.manual-send">
                            <template #headerActions>
                                <Button label="Envoyer" icon="pi pi-send" @click="sendManualSmsAction" />
                            </template>

                            <div class="sms-pad sms-field-grid sms-manual-grid">
                                <FloatLabel variant="on">
                                    <InputText id="manual-phone" v-model="manualSms.phone" class="w-full" />
                                    <label for="manual-phone">Numéro <span class="sms-required">*</span></label>
                                </FloatLabel>

                                <div class="space-y-2">
                                    <label class="sms-field-label">Pré-remplir depuis un template</label>
                                    <Select v-model="manualTemplateCode" :options="smsTemplates" optionLabel="name" optionValue="code" placeholder="Choisir un template" class="w-full" />
                                </div>

                                <div class="md:col-span-2 space-y-2">
                                    <FloatLabel variant="on">
                                        <Textarea id="manual-message" v-model="manualSms.message" rows="5" autoResize class="w-full" />
                                        <label for="manual-message">Message à envoyer <span class="sms-required">*</span></label>
                                    </FloatLabel>
                                    <p class="sms-meta">{{ manualSms.message.length }} caractères · estimation {{ Math.max(1, Math.ceil(Math.max(1, manualSms.message.length) / 160)) }} SMS</p>
                                </div>
                            </div>
                        </PageSection>
                    </TabPanel>
                </TabPanels>
            </Tabs>
    </PageShell>
</template>

<style scoped>
.sms-status {
    display: inline-flex;
    align-items: flex-start;
    gap: 0.75rem;
    max-width: 48rem;
    margin-top: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--page-section-radius);
    border: 1px solid color-mix(in srgb, var(--p-orange-500, #f97316) 32%, var(--surface-border));
    background: color-mix(in srgb, var(--p-orange-500, #f97316) 10%, var(--surface-card));
    color: var(--text-color);
}

.sms-status--ok {
    border-color: color-mix(in srgb, var(--p-green-500, #22c55e) 32%, var(--surface-border));
    background: color-mix(in srgb, var(--p-green-500, #22c55e) 10%, var(--surface-card));
}

.sms-status__icon {
    margin-top: 0.15rem;
    color: var(--p-primary-color);
}

.sms-status--warn .sms-status__icon {
    color: var(--p-orange-500, #f97316);
}

.sms-status--ok .sms-status__icon {
    color: var(--p-green-500, #22c55e);
}

.sms-status__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.sms-status__title {
    font-weight: 600;
    color: var(--text-color);
}

.sms-status__detail,
.sms-meta,
.sms-kpi-meta {
    margin: 0.25rem 0 0;
    color: var(--text-color-secondary);
    line-height: 1.45;
}

.sms-alert {
    display: flex;
    min-height: 20rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 2rem;
    text-align: center;
    border-radius: var(--page-section-radius);
    border: 1px solid color-mix(in srgb, var(--p-orange-500, #f97316) 32%, var(--surface-border));
    background: color-mix(in srgb, var(--p-orange-500, #f97316) 8%, var(--surface-card));
    color: var(--text-color);
}

.sms-alert__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 3.5rem;
    height: 3.5rem;
    border-radius: 999px;
    background: color-mix(in srgb, var(--p-orange-500, #f97316) 16%, var(--surface-card));
    color: var(--p-orange-500, #f97316);
}

.sms-alert__title {
    margin: 0;
    font-weight: 600;
    color: var(--text-color);
}

.sms-alert__detail {
    margin: 0.25rem 0 0;
    color: var(--text-color-secondary);
}

.sms-tabs :deep(.p-tablist),
.sms-tabs :deep(.p-tablist-content),
.sms-tabs :deep(.p-tablist-tab-list) {
    background: transparent;
}

.sms-tabs :deep(.p-tablist-tab-list) {
    gap: 0.25rem;
    border-bottom: 1px solid var(--surface-border);
}

.sms-tabs :deep(.p-tab) {
    border: 0;
    border-radius: 0.5rem 0.5rem 0 0;
    background: transparent;
    color: var(--text-color-secondary);
}

.sms-tabs :deep(.p-tab-active) {
    color: var(--p-primary-color);
    background: color-mix(in srgb, var(--p-primary-color) 10%, var(--surface-card));
}

.sms-tabs :deep(.p-tablist-active-bar) {
    background: var(--p-primary-color);
}

.sms-tab-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.sms-panels {
    margin-top: var(--page-content-gap);
    background: transparent !important;
    padding: 0 !important;
}

.sms-stack,
.sms-fields {
    display: flex;
    flex-direction: column;
    gap: var(--page-content-gap);
}

.sms-fields {
    gap: 1rem;
}

.sms-grid-2,
.sms-grid-queue {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--page-content-gap);
}

@media (min-width: 1280px) {
    .sms-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sms-grid-queue {
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.6fr);
    }
}

.sms-pad {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0.875rem 1rem 1rem;
}

.sms-inline-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
}

.sms-field-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 768px) {
    .sms-field-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sms-manual-grid > :last-child {
        grid-column: 1 / -1;
    }
}

.sms-field-label,
.sms-kicker {
    display: block;
    margin: 0 0 0.35rem;
    font-weight: 500;
    color: var(--text-color-secondary);
}

.sms-subhead,
.sms-emphasis {
    margin: 0;
    font-weight: 600;
    color: var(--text-color);
}

.sms-block-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.sms-block-head h4 {
    margin: 0;
    font-weight: 600;
    color: var(--text-color);
}

.sms-inset,
.sms-message,
.sms-chip {
    border-radius: calc(var(--page-section-radius) - 0.15rem);
    border: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    background: color-mix(in srgb, var(--surface-card) 88%, var(--text-color) 4%);
    color: var(--text-color);
}

.sms-inset {
    padding: 0.875rem 1rem;
}

.sms-message {
    padding: 0.75rem;
}

.sms-chip {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.25rem 0.15rem 0.35rem;
}

.sms-bypass-card {
    display: block;
}

.sms-chart {
    height: 18rem;
}

.sms-empty {
    padding: 1.5rem 1rem;
    text-align: center;
    color: var(--text-color-secondary);
    border-radius: var(--page-section-radius);
    border: 1px dashed var(--surface-border);
    background: transparent;
}

.sms-bars {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.sms-bar-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sms-bar-label {
    width: 7rem;
    flex-shrink: 0;
    color: var(--text-color-secondary);
}

.sms-bar-label--date {
    width: 5rem;
}

.sms-bar {
    flex: 1;
    height: 0.45rem;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in srgb, var(--surface-border) 85%, transparent);
}

.sms-bar__fill {
    height: 100%;
    border-radius: 999px;
    background: var(--p-primary-color);
}

.sms-bar-count {
    width: 2.5rem;
    text-align: right;
    font-weight: 600;
    color: var(--text-color);
}

.sms-required,
.sms-error {
    color: var(--p-red-500, #ef4444);
}

.sms-logs-filters {
    display: grid;
    grid-template-columns: 1fr;
    align-items: end;
}

@media (min-width: 1280px) {
    .sms-logs-filters {
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.2fr);
    }
}

:deep(.p-floatlabel .p-inputtext) {
    min-height: 3rem;
}
</style>
