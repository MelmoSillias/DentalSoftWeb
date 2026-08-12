import { logAppError } from '@/utils/appLogger';
import { computed, reactive, ref } from 'vue';
import {
    fetchSmsLogs,
    fetchSmsProviderOverview,
    fetchSmsQueue,
    fetchSmsSettings,
    fetchSmsStats,
    fetchSmsTemplates,
    previewSmsTemplate,
    processSmsQueue,
    saveSmsSettings,
    saveSmsTemplates,
    sendManualSms,
    sendSmsTest,
    testSmsConnection,
    updateSmsQueueItem
} from '@/services/smsService';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import cabinetConfig from '@/cabinetConfig';

export const SMS_PROVIDER_OPTIONS = [
    { value: 'orange', label: 'Orange' },
    { value: 'afriksms', label: 'AfrikSms' }
];

export const SMS_CALLBACK_NOTIFY_OPTIONS = [
    { value: 1, label: 'POST' },
    { value: 2, label: 'GET' }
];

export function useSmsAdminSettings(token, toast, extractApiError) {
    const smsLoading = ref(false);
    const smsLoaded = ref(false);
    const smsTesting = ref(false);
    const smsSendingTest = ref(false);
    const smsSaving = ref(false);
    const smsQueueing = ref(false);
    const smsQueueItemUpdating = ref(null);
    const smsTemplateSaving = ref(false);
    const lastTestResult = ref(null);
    const lastTestAt = ref(null);
    const providerOverview = ref({ success: false, message: '', contracts: [] });

    const smsConfig = reactive({
        provider: 'orange',
        enabled: false,
        clientId: '',
        clientSecret: '',
        senderAddress: '',
        senderName: '',
        approvedSenderNames: [],
        patientPreferenceBypass: {
            patientCreated: false,
            receipt: false,
            ticket: false,
            invoice: false,
            appointmentReminder: false,
            unsubscribed: false,
            blacklisted: false
        },
        baseUrl: 'https://api.orange.com',
        oauthUrl: 'https://api.orange.com/oauth/v3/token',
        webhookBaseUrl: '',
        callbackNotifyType: 2
    });

    const smsStats = reactive({
        balance: {
            sentToday: 0,
            sentMonth: 0,
            totalSent: 0
        },
        dailyConsumption: {},
        monthlyConsumption: {},
        period: {
            from: null,
            to: null,
            sent: 0,
            failed: 0,
            total: 0,
            successRate: 0,
            daily: {},
            byType: {}
        }
    });

    const smsPeriodLoading = ref(false);

    const smsLogs = ref([]);
    const smsQueue = ref([]);
    const smsTemplates = ref([]);
    const selectedTemplateCode = ref('');
    const previewVariables = reactive({
        patient_name: 'John Doe',
        date: '12/03/2026',
        time: '09:30',
        amount: '25000',
        invoice_number: 'F-000123',
        cabinet_name: cabinetConfig.smsCabinetName || cabinetConfig.displayName || 'Cabinet dentaire'
    });
    const previewResult = ref('');
    const manualSms = reactive({ phone: '', message: '' });
    const queuedSms = reactive({
        phone: '',
        message: '',
        sendAt: null,
        recurrence: 'none'
    });
    const testSms = reactive({ phone: '', message: cabinetConfig.smsTestMessage });

    const selectedTemplate = computed(() => smsTemplates.value.find((tpl) => tpl.code === selectedTemplateCode.value) || null);
    const previewCharacters = computed(() => (previewResult.value || '').length);
    const previewEstimatedSms = computed(() => Math.max(1, Math.ceil(previewCharacters.value / 160)));
    const dailySeries = computed(() => Object.entries(smsStats.dailyConsumption || {}));
    const monthlySeries = computed(() => Object.entries(smsStats.monthlyConsumption || {}));
    const maxDaily = computed(() => Math.max(1, ...dailySeries.value.map(([, value]) => Number(value) || 0)));
    const maxMonthly = computed(() => Math.max(1, ...monthlySeries.value.map(([, value]) => Number(value) || 0)));
    const periodDailySeries = computed(() => Object.entries(smsStats.period?.daily || {}));
    const periodByType = computed(() => Object.entries(smsStats.period?.byType || {}));
    const maxPeriodDaily = computed(() => Math.max(1, ...periodDailySeries.value.map(([, value]) => Number(value) || 0)));
    const maxPeriodByType = computed(() => Math.max(1, ...periodByType.value.map(([, value]) => Number(value) || 0)));

    const applyStatsPayload = (stats) => {
        smsStats.balance = stats.balance || smsStats.balance;
        smsStats.dailyConsumption = stats.dailyConsumption || {};
        smsStats.monthlyConsumption = stats.monthlyConsumption || {};
        if (stats.period && typeof stats.period === 'object') {
            smsStats.period = {
                from: stats.period.from || null,
                to: stats.period.to || null,
                sent: Number(stats.period.sent) || 0,
                failed: Number(stats.period.failed) || 0,
                total: Number(stats.period.total) || 0,
                successRate: Number(stats.period.successRate) || 0,
                daily: stats.period.daily || {},
                byType: stats.period.byType || {}
            };
        }
    };

    const toIsoDate = (value) => {
        if (!value) return null;
        if (typeof value === 'string') return value;
        const date = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(date.getTime())) return null;
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const loadPeriodStats = async (from, to, { silent = true } = {}) => {
        const fromIso = toIsoDate(from);
        const toIso = toIsoDate(to);
        if (!fromIso || !toIso) return;

        smsPeriodLoading.value = true;
        try {
            const stats = await fetchSmsStats(token, { from: fromIso, to: toIso });
            applyStatsPayload(stats);
            if (!silent) {
                toast.add({ severity: 'success', summary: 'Stats SMS', detail: 'Statistiques période mises à jour.', life: 2500 });
            }
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'Stats SMS', detail: extractApiError(error, 'Chargement des stats période impossible.'), life: 3500 });
        } finally {
            smsPeriodLoading.value = false;
        }
    };

    const loadSmsData = async (force = false) => {
        if (!force && (smsLoaded.value || smsLoading.value)) return;

        smsLoading.value = true;
        try {
            const [smsSettings, publicSettings] = await Promise.all([
                fetchSmsSettings(token),
                fetchPublicGeneralSettings(token).catch(() => ({}))
            ]);
            previewVariables.cabinet_name = publicSettings.smsCabinetName
                || cabinetConfig.smsCabinetName
                || cabinetConfig.displayName
                || 'Cabinet dentaire';
            smsConfig.provider = smsSettings.provider || 'orange';
            smsConfig.enabled = Boolean(smsSettings.enabled);
            smsConfig.clientId = smsSettings.clientId || '';
            smsConfig.clientSecret = '';
            smsConfig.senderAddress = smsSettings.senderAddress || smsSettings.senderName || '';
            smsConfig.senderName = smsSettings.senderName || '';
            smsConfig.approvedSenderNames = Array.isArray(smsSettings.approvedSenderNames) ? smsSettings.approvedSenderNames : [];
            smsConfig.patientPreferenceBypass = {
                patientCreated: Boolean(smsSettings.patientPreferenceBypass?.patientCreated),
                receipt: Boolean(smsSettings.patientPreferenceBypass?.receipt),
                ticket: Boolean(smsSettings.patientPreferenceBypass?.ticket),
                invoice: Boolean(smsSettings.patientPreferenceBypass?.invoice),
                appointmentReminder: Boolean(smsSettings.patientPreferenceBypass?.appointmentReminder),
                unsubscribed: Boolean(smsSettings.patientPreferenceBypass?.unsubscribed),
                blacklisted: Boolean(smsSettings.patientPreferenceBypass?.blacklisted)
            };
            smsConfig.baseUrl = smsSettings.baseUrl || 'https://api.orange.com';
            smsConfig.oauthUrl = smsSettings.oauthUrl || 'https://api.orange.com/oauth/v3/token';
            smsConfig.webhookBaseUrl = smsSettings.webhookBaseUrl || '';
            smsConfig.callbackNotifyType = Number(smsSettings.callbackNotifyType) || 2;

            const from = smsStats.period.from;
            const to = smsStats.period.to;
            const statsParams = from && to ? { from, to } : {};

            const [stats, logs, queue, templates, overview] = await Promise.all([
                fetchSmsStats(token, statsParams),
                fetchSmsLogs({ limit: 50 }, token),
                fetchSmsQueue({ limit: 100 }, token),
                fetchSmsTemplates(token),
                fetchSmsProviderOverview(token)
            ]);

            applyStatsPayload(stats);
            smsLogs.value = logs;
            smsQueue.value = queue;
            smsTemplates.value = templates;
            providerOverview.value = overview && typeof overview === 'object'
                ? { success: Boolean(overview.success), message: overview.message || '', contracts: Array.isArray(overview.contracts) ? overview.contracts : [] }
                : { success: false, message: '', contracts: [] };
            if (templates.length > 0 && !selectedTemplateCode.value) {
                selectedTemplateCode.value = templates[0].code;
            }

            smsLoaded.value = true;
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'SMS', detail: extractApiError(error, 'Chargement des données SMS impossible.'), life: 3500 });
        } finally {
            smsLoading.value = false;
        }
    };

    const refreshSmsData = async () => {
        smsLoaded.value = false;
        await loadSmsData(true);
    };

    const saveSmsConfigAction = async () => {
        smsSaving.value = true;
        try {
            const res = await saveSmsSettings({
                provider: smsConfig.provider,
                enabled: smsConfig.enabled,
                clientId: smsConfig.clientId,
                clientSecret: smsConfig.clientSecret,
                senderAddress: smsConfig.senderAddress,
                senderName: smsConfig.senderName,
                approvedSenderNames: smsConfig.approvedSenderNames,
                patientPreferenceBypass: smsConfig.patientPreferenceBypass,
                baseUrl: smsConfig.baseUrl,
                oauthUrl: smsConfig.oauthUrl,
                webhookBaseUrl: smsConfig.webhookBaseUrl,
                callbackNotifyType: smsConfig.callbackNotifyType
            }, token);
            smsConfig.clientSecret = '';
            const callbackRegistration = res?.callbackRegistration;
            const detail = callbackRegistration
                ? `Configuration sauvegardée. Callback: ${callbackRegistration.success ? 'enregistré' : callbackRegistration.message || 'non enregistré'}.`
                : 'Configuration sauvegardée.';
            toast.add({ severity: 'success', summary: 'SMS', detail, life: 3500 });
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'SMS', detail: extractApiError(error, 'Sauvegarde impossible.'), life: 3500 });
        } finally {
            smsSaving.value = false;
        }
    };

    const testConnectionAction = async () => {
        smsTesting.value = true;
        try {
            const res = await testSmsConnection(token);
            lastTestResult.value = {
                kind: 'connection',
                success: Boolean(res.success),
                message: res.message || 'Test terminé.'
            };
            lastTestAt.value = new Date();
            toast.add({ severity: res.success ? 'success' : 'warn', summary: 'SMS', detail: res.message || 'Test terminé.', life: 3000 });
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            lastTestResult.value = {
                kind: 'connection',
                success: false,
                message: extractApiError(error, 'Connexion API impossible.')
            };
            lastTestAt.value = new Date();
            toast.add({ severity: 'error', summary: 'SMS', detail: extractApiError(error, 'Connexion API impossible.'), life: 5500 });
        } finally {
            smsTesting.value = false;
        }
    };

    const sendSmsTestAction = async () => {
        smsSendingTest.value = true;
        try {
            const res = await sendSmsTest({ phone: testSms.phone, message: testSms.message }, token);
            lastTestResult.value = {
                kind: 'send',
                success: Boolean(res.success),
                message: res.success ? 'SMS envoyé.' : (res.error || 'Échec.')
            };
            lastTestAt.value = new Date();
            toast.add({ severity: res.success ? 'success' : 'warn', summary: 'SMS test', detail: res.success ? 'SMS envoyé.' : (res.error || 'Échec.'), life: 3000 });
            await refreshSmsData();
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            lastTestResult.value = {
                kind: 'send',
                success: false,
                message: extractApiError(error, 'Envoi test impossible.')
            };
            lastTestAt.value = new Date();
            toast.add({ severity: 'error', summary: 'SMS test', detail: extractApiError(error, 'Envoi test impossible.'), life: 10000 });
        } finally {
            smsSendingTest.value = false;
        }
    };

    const saveTemplatesAction = async () => {
        smsTemplateSaving.value = true;
        try {
            await saveSmsTemplates(smsTemplates.value, token);
            toast.add({ severity: 'success', summary: 'Templates', detail: 'Templates sauvegardés.', life: 2500 });
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'Templates', detail: extractApiError(error, 'Sauvegarde impossible.'), life: 3500 });
        } finally {
            smsTemplateSaving.value = false;
        }
    };

    const previewTemplateAction = async () => {
        if (!selectedTemplateCode.value) return;
        try {
            const res = await previewSmsTemplate({ code: selectedTemplateCode.value, variables: previewVariables }, token);
            previewResult.value = res.message || '';
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'warn', summary: 'Preview', detail: extractApiError(error, 'Preview indisponible.'), life: 3000 });
        }
    };

    const sendManualSmsAction = async () => {
        if (!manualSms.phone || !manualSms.message) {
            toast.add({ severity: 'warn', summary: 'SMS manuel', detail: 'Numéro et message requis.', life: 2500 });
            return;
        }
        try {
            const res = await sendManualSms({ phone: manualSms.phone, message: manualSms.message }, token);
            toast.add({ severity: res.success ? 'success' : 'warn', summary: 'SMS manuel', detail: res.success ? 'Ajouté à la file.' : (res.error || 'Échec.'), life: 3000 });
            await refreshSmsData();
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'SMS manuel', detail: extractApiError(error, 'Envoi impossible.'), life: 5500 });
        }
    };

    const scheduleQueuedSmsAction = async () => {
        if (!queuedSms.phone || !queuedSms.message) {
            toast.add({ severity: 'warn', summary: 'Programmation SMS', detail: 'Numéro et message requis.', life: 2500 });
            return;
        }

        try {
            const payload = {
                phone: queuedSms.phone,
                message: queuedSms.message,
                sendAt: queuedSms.sendAt instanceof Date ? queuedSms.sendAt.toISOString() : null,
                recurrence: queuedSms.recurrence,
            };
            const res = await sendManualSms(payload, token);
            toast.add({
                severity: res.success ? 'success' : 'warn',
                summary: 'Programmation SMS',
                detail: res.success
                    ? `${res.queuedCount || 1} SMS programmé(s).`
                    : (res.error || 'Programmation impossible.'),
                life: 3500
            });
            if (res.success) {
                queuedSms.phone = '';
                queuedSms.message = '';
                queuedSms.sendAt = null;
                queuedSms.recurrence = 'none';
                await refreshSmsData();
            }
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'Programmation SMS', detail: extractApiError(error, 'Programmation impossible.'), life: 5500 });
        }
    };

    const processQueueAction = async () => {
        smsQueueing.value = true;
        try {
            await processSmsQueue({ async: true, limit: 20 }, token);
            toast.add({ severity: 'success', summary: 'Queue SMS', detail: 'Traitement de queue lancé.', life: 2500 });
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'Queue SMS', detail: extractApiError(error, 'Lancement impossible.'), life: 3500 });
        } finally {
            smsQueueing.value = false;
        }
    };

    const updateQueueItemAction = async (queueId, payload, successMessage) => {
        smsQueueItemUpdating.value = queueId;
        try {
            const res = await updateSmsQueueItem(queueId, payload, token);
            toast.add({ severity: 'success', summary: 'File SMS', detail: successMessage || 'Action appliquée.', life: 2500 });
            await refreshSmsData();
            return res;
        } catch (error) {
            logAppError('useSmsAdminSettings', error);
            toast.add({ severity: 'error', summary: 'File SMS', detail: extractApiError(error, 'Action impossible.'), life: 4000 });
            throw error;
        } finally {
            smsQueueItemUpdating.value = null;
        }
    };

    return {
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
        smsLogs,
        smsQueue,
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
        maxPeriodDaily,
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
    };
}
