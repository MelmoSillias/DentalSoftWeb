<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Chip from 'primevue/chip';
import Column from 'primevue/column';
import Card from 'primevue/card';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
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
import { useSmsAdminSettings } from '@/composables/useSmsAdminSettings';
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

const tabItems = [
    { value: 'overview', label: 'Aperçu', icon: 'pi pi-chart-bar' },
    { value: 'config', label: 'Configuration & Test', icon: 'pi pi-cog' },
    { value: 'logs', label: 'Logs', icon: 'pi pi-list' },
    { value: 'templates', label: 'Templates', icon: 'pi pi-file-edit' },
    { value: 'manual', label: 'Envoi Manuel', icon: 'pi pi-send' }
];

const extractApiError = (error, fallback) => getHttpErrorMessage(error, fallback);

const {
    smsLoading,
    smsLoaded,
    smsTesting,
    smsSendingTest,
    smsSaving,
    smsQueueing,
    smsTemplateSaving,
    lastTestResult,
    lastTestAt,
    providerOverview,
    smsConfig,
    smsStats,
    smsLogs,
    smsTemplates,
    selectedTemplateCode,
    previewVariables,
    previewResult,
    manualSms,
    testSms,
    selectedTemplate,
    previewCharacters,
    previewEstimatedSms,
    dailySeries,
    monthlySeries,
    maxDaily,
    maxMonthly,
    loadSmsData,
    refreshSmsData,
    saveSmsConfigAction,
    testConnectionAction,
    sendSmsTestAction,
    saveTemplatesAction,
    previewTemplateAction,
    sendManualSmsAction,
    processQueueAction
} = useSmsAdminSettings(token, toast, extractApiError);

const totalCharacters = computed(() =>
    smsTemplates.value.reduce((sum, template) => sum + String(template?.content || '').length, 0)
);
const recommendedContract = computed(() => providerOverview.value.contracts.find((item) => item.isRecommended) || providerOverview.value.contracts[0] || null);
const approvedSenderNameOptions = computed(() => smsConfig.approvedSenderNames.map((item) => ({ label: item, value: item })));

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
    { label: 'Échec', value: 'failed' },
    { label: 'En attente', value: 'pending' }
];

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

onMounted(async () => {
    try {
        await loadSmsData(true);
        loadErrorMessage.value = '';
    } catch (error) {
        loadErrorMessage.value = extractApiError(error, 'Impossible de charger les paramètres SMS.');
    }
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
        <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
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
            <TabList class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-800">
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
                                            <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{{ smsConfig.provider || '—' }}</p>
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

                            <!-- Orange Contract -->
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Contrat Orange</p>
                                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Forfait et disponibilité</h3>
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
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Unités restantes</p>
                                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ recommendedContract.availableUnits ?? '—' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Contrat recommandé</p>
                                    </div>
                                    
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Statut</p>
                                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ recommendedContract.status || '—' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Type {{ recommendedContract.type || '—' }}</p>
                                    </div>
                                    
                                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/30">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Expiration</p>
                                        <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ formatDateTime(recommendedContract.expirationDate) }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ providerOverview.message || 'Données Orange' }}</p>
                                    </div>
                                </div>

                                <div v-else class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                    {{ providerOverview.message || 'Aucun contrat Orange disponible pour le moment.' }}
                                </div>
                            </div>
                        </template>
                    </div>
                </TabPanel>

                <!-- Configuration Tab -->
                <TabPanel value="config">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
                        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Configuration</p>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Configuration & test</h3>
                            </div>
                            <div class="flex flex-wrap gap-3">
                                <Button label="Test connexion" icon="pi pi-bolt" severity="secondary" :loading="smsTesting" @click="testConnectionAction" />
                                <Button label="Envoyer SMS test" icon="pi pi-send" severity="info" :loading="smsSendingTest" @click="sendSmsTestAction" />
                                <Button label="Sauvegarder" icon="pi pi-save" :loading="smsSaving" @click="saveSmsConfigAction" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <FloatLabel variant="on">
                                    <InputText id="sms-provider" v-model="smsConfig.provider" disabled class="w-full" />
                                    <label for="sms-provider">Provider</label>
                                </FloatLabel>
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
                                    <label for="sms-client-id">Client ID</label>
                                </FloatLabel>
                            </div>
                            
                            <div>
                                <FloatLabel variant="on">
                                    <InputText id="sms-client-secret" v-model="smsConfig.clientSecret" type="password" class="w-full" />
                                    <label for="sms-client-secret">Client Secret</label>
                                </FloatLabel>
                            </div>
                            
                            <div class="space-y-2">
                                <FloatLabel variant="on">
                                    <InputText id="sms-sender-address" v-model="smsConfig.senderAddress" class="w-full" />
                                    <label for="sms-sender-address">Sender Address</label>
                                </FloatLabel>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Pour Orange Mali, utilisez d'abord le sender technique standard tel:+2230000.</p>
                            </div>
                            
                            <div class="space-y-3">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Sender Name</label>
                                <Select
                                    v-if="approvedSenderNameOptions.length"
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
                                    <label for="sms-sender-name">Saisie manuelle</label>
                                </FloatLabel>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Optionnel. Doit être whitelisté par Orange et limité à 11 caractères alphanumériques ou espaces.</p>
                            </div>
                            
                            <div>
                                <FloatLabel variant="on">
                                    <InputText id="sms-base-url" v-model="smsConfig.baseUrl" class="w-full" />
                                    <label for="sms-base-url">Base URL</label>
                                </FloatLabel>
                            </div>
                            
                            <div>
                                <FloatLabel variant="on">
                                    <InputText id="sms-oauth-url" v-model="smsConfig.oauthUrl" class="w-full" />
                                    <label for="sms-oauth-url">OAuth URL</label>
                                </FloatLabel>
                            </div>
                        </div>

                        <Divider class="my-6" />

                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <!-- Approved Sender Names -->
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/30">
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
                                <DatePicker v-model="logsDateRange" selectionMode="range" showIcon dateFormat="dd/mm/yy" class="w-full" />
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
                                    <Tag :severity="data.status === 'sent' ? 'success' : data.status === 'failed' ? 'danger' : 'warning'" :value="data.status" />
                                </template>
                            </Column>
                            <Column field="type" header="Type" class="whitespace-nowrap"></Column>
                            <Column field="source" header="Source" class="whitespace-nowrap"></Column>
                        </DataTable>
                    </div>
                </TabPanel>

                <!-- Templates Tab -->
                <TabPanel value="templates">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
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
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800/30">
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
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900/50">
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