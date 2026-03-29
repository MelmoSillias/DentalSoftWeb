<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Divider from 'primevue/divider';
import AppConfigurator from '@/layout/AppConfigurator.vue';
import { useUiSettingsStore } from '@/stores/uiSettings';
import {
    fetchSmsLogs,
    fetchSmsSettings,
    fetchSmsStats,
    fetchSmsTemplates,
    previewSmsTemplate,
    processSmsQueue,
    saveSmsSettings,
    saveSmsTemplates,
    sendManualSms,
    sendSmsTest,
    testSmsConnection
} from '@/services/smsService';

const toast = useToast();
const token = localStorage.getItem('token');
const uiSettings = useUiSettingsStore();

const sections = [
    { id: 'appearance', label: 'Apparence' },
    { id: 'appearance-theme', label: 'Mode theme' },
    { id: 'appearance-primary', label: 'Couleur principale' },
    { id: 'appearance-surface', label: 'Surface UI' },
    { id: 'appearance-presets', label: 'Presets' },
    { id: 'appearance-font-family', label: 'Police' },
    { id: 'appearance-font-size', label: 'Taille texte' },
    { id: 'sms-api', label: 'API SMS' }
];
const activeSection = ref('appearance');
let observer = null;

const smsLoading = ref(false);
const smsLoaded = ref(false);
const smsTesting = ref(false);
const smsSendingTest = ref(false);
const smsSaving = ref(false);
const smsQueueing = ref(false);
const smsTemplateSaving = ref(false);

const smsConfig = reactive({
    provider: 'orange',
    enabled: false,
    clientId: '',
    clientSecret: '',
    senderName: '',
    baseUrl: 'https://api.orange.com',
    oauthUrl: 'https://api.orange.com/oauth/v3/token'
});

const smsStats = reactive({
    balance: {
        sentToday: 0,
        sentMonth: 0,
        totalSent: 0
    },
    dailyConsumption: {},
    monthlyConsumption: {}
});

const smsLogs = ref([]);
const smsTemplates = ref([]);
const selectedTemplateCode = ref('');
const previewVariables = reactive({
    patient_name: 'John Doe',
    date: '12/03/2026',
    time: '09:30',
    amount: '25000',
    invoice_number: 'F-000123',
    cabinet_name: 'ORODENT'
});
const previewResult = ref('');
const manualSms = reactive({
    phone: '',
    message: ''
});
const testSms = reactive({
    phone: '',
    message: 'Message de test ORODENT.'
});

const selectedTemplate = computed(() => smsTemplates.value.find((tpl) => tpl.code === selectedTemplateCode.value) || null);
const previewCharacters = computed(() => (previewResult.value || '').length);
const previewEstimatedSms = computed(() => Math.max(1, Math.ceil(previewCharacters.value / 160)));

const dailySeries = computed(() => Object.entries(smsStats.dailyConsumption || {}));
const monthlySeries = computed(() => Object.entries(smsStats.monthlyConsumption || {}));
const maxDaily = computed(() => Math.max(1, ...dailySeries.value.map(([, value]) => Number(value) || 0)));
const maxMonthly = computed(() => Math.max(1, ...monthlySeries.value.map(([, value]) => Number(value) || 0)));

const saveAppearance = () => {
    uiSettings.persistAppearance();
    uiSettings.persistLayout();
    toast.add({ severity: 'success', summary: 'Apparence', detail: 'Parametres enregistres.', life: 2500 });
};

const scrollToSection = async (id) => {
    activeSection.value = id;
    await nextTick();
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const loadSmsData = async () => {
    if (smsLoaded.value || smsLoading.value) return;
    smsLoading.value = true;
    try {
        const [settings, stats, logs, templates] = await Promise.all([
            fetchSmsSettings(token),
            fetchSmsStats(token),
            fetchSmsLogs({ limit: 50 }, token),
            fetchSmsTemplates(token)
        ]);

        smsConfig.provider = settings.provider || 'orange';
        smsConfig.enabled = Boolean(settings.enabled);
        smsConfig.clientId = settings.clientId || '';
        smsConfig.clientSecret = '';
        smsConfig.senderName = settings.senderName || '';
        smsConfig.baseUrl = settings.baseUrl || 'https://api.orange.com';
        smsConfig.oauthUrl = settings.oauthUrl || 'https://api.orange.com/oauth/v3/token';

        smsStats.balance = stats.balance || smsStats.balance;
        smsStats.dailyConsumption = stats.dailyConsumption || {};
        smsStats.monthlyConsumption = stats.monthlyConsumption || {};

        smsLogs.value = logs;
        smsTemplates.value = templates;
        if (templates.length > 0) {
            selectedTemplateCode.value = templates[0].code;
        }

        smsLoaded.value = true;
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'SMS', detail: 'Chargement des données SMS impossible.', life: 3500 });
    } finally {
        smsLoading.value = false;
    }
};

const saveSmsConfigAction = async () => {
    smsSaving.value = true;
    try {
        await saveSmsSettings(
            {
                provider: smsConfig.provider,
                enabled: smsConfig.enabled,
                clientId: smsConfig.clientId,
                clientSecret: smsConfig.clientSecret,
                senderName: smsConfig.senderName,
                baseUrl: smsConfig.baseUrl,
                oauthUrl: smsConfig.oauthUrl
            },
            token
        );
        smsConfig.clientSecret = '';
        toast.add({ severity: 'success', summary: 'SMS', detail: 'Configuration sauvegardée.', life: 2500 });
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'SMS', detail: 'Sauvegarde impossible.', life: 3500 });
    } finally {
        smsSaving.value = false;
    }
};

const testConnectionAction = async () => {
    smsTesting.value = true;
    try {
        const res = await testSmsConnection(token);
        toast.add({ severity: res.success ? 'success' : 'warn', summary: 'SMS', detail: res.message || 'Test terminé.', life: 3000 });
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'SMS', detail: 'Connexion API impossible.', life: 3500 });
    } finally {
        smsTesting.value = false;
    }
};

const sendSmsTestAction = async () => {
    smsSendingTest.value = true;
    try {
        const res = await sendSmsTest({ phone: testSms.phone, message: testSms.message }, token);
        toast.add({ severity: res.success ? 'success' : 'warn', summary: 'SMS test', detail: res.success ? 'SMS envoyé.' : (res.error || 'Échec.'), life: 3000 });
        await refreshSmsData();
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'SMS test', detail: 'Envoi test impossible.', life: 3500 });
    } finally {
        smsSendingTest.value = false;
    }
};

const refreshSmsData = async () => {
    smsLoaded.value = false;
    await loadSmsData();
};

const saveTemplatesAction = async () => {
    smsTemplateSaving.value = true;
    try {
        await saveSmsTemplates(smsTemplates.value, token);
        toast.add({ severity: 'success', summary: 'Templates', detail: 'Templates sauvegardés.', life: 2500 });
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'Templates', detail: 'Sauvegarde impossible.', life: 3500 });
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
        console.error(error);
        toast.add({ severity: 'warn', summary: 'Preview', detail: 'Preview indisponible.', life: 3000 });
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
        console.error(error);
        toast.add({ severity: 'error', summary: 'SMS manuel', detail: 'Envoi impossible.', life: 3500 });
    }
};

const processQueueAction = async () => {
    smsQueueing.value = true;
    try {
        await processSmsQueue({ async: true, limit: 20 }, token);
        toast.add({ severity: 'success', summary: 'Queue SMS', detail: 'Traitement de queue lancé.', life: 2500 });
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'Queue SMS', detail: 'Lancement impossible.', life: 3500 });
    } finally {
        smsQueueing.value = false;
    }
};

watch(activeSection, (value) => {
    if (value === 'sms-api') {
        loadSmsData();
    }
});

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                }
            });
        },
        { rootMargin: '-20% 0px -65% 0px', threshold: [0.2] }
    );

    sections.forEach((section) => {
        const el = document.getElementById(section.id);
        if (el) observer.observe(el);
    });

    if (activeSection.value === 'sms-api') {
        loadSmsData();
    }
});

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect();
    }
});
</script>

<template>
    <div class="settings-page">
        <div class="settings-main"> 

            <section id="appearance" class="settings-section">
                <h2 class="text-xl font-semibold mb-3 section-title"> <i class="pi pi-objects-column pr-2"></i> Apparence</h2>
                <div class="panel">
                    <AppConfigurator
                        embedded
                        :show-menu-mode="false"
                        :show-theme-mode="true"
                        :show-primary="true"
                        :show-surface="true"
                        :show-presets="true"
                        :show-typography="true"
                    />
                </div>

                <div class="mt-4 flex justify-end">
                    <Button label="Sauvegarder apparence" icon="pi pi-save" @click="saveAppearance" />
                </div>
            </section>

            <section id="sms-api" class="settings-section">
                <h2 class="text-xl font-semibold mb-3 section-title"> <i class="pi pi-send pr-2"></i> API SMS</h2>

                <div class="panel mb-4">
                    <h3 class="font-semibold mb-3">Configuration API</h3>
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="label">SMS Provider</label>
                            <InputText v-model="smsConfig.provider" disabled />
                        </div>
                        <div>
                            <label class="label">Activation</label>
                            <SelectButton v-model="smsConfig.enabled" :options="[{ label: 'Activé', value: true }, { label: 'Désactivé', value: false }]" optionLabel="label" optionValue="value" :allowEmpty="false" />
                        </div>
                        <div>
                            <label class="label">Client ID</label>
                            <InputText v-model="smsConfig.clientId" />
                        </div>
                        <div>
                            <label class="label">Client Secret</label>
                            <InputText v-model="smsConfig.clientSecret" type="password" placeholder="Laisser vide pour conserver" />
                        </div>
                        <div>
                            <label class="label">Sender Name</label>
                            <InputText v-model="smsConfig.senderName" placeholder="ex: ORODENT" />
                        </div>
                        <div>
                            <label class="label">Base URL API</label>
                            <InputText v-model="smsConfig.baseUrl" />
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 justify-end">
                        <Button label="Test connexion" icon="pi pi-bolt" severity="secondary" :loading="smsTesting" @click="testConnectionAction" />
                        <Button label="Envoyer SMS test" icon="pi pi-send" severity="info" :loading="smsSendingTest" @click="sendSmsTestAction" />
                        <Button label="Sauvegarder" icon="pi pi-save" :loading="smsSaving" @click="saveSmsConfigAction" />
                    </div>
                    <Divider />
                    <div class="grid md:grid-cols-2 gap-3">
                        <InputText v-model="testSms.phone" placeholder="Numéro test" />
                        <InputText v-model="testSms.message" placeholder="Message test" />
                    </div>
                </div>

                <div class="panel mb-4">
                    <h3 class="font-semibold mb-3">Gestion du solde</h3>
                    <div class="grid md:grid-cols-4 gap-3">
                        <div class="metric-card">
                            <p>Solde SMS</p>
                            <strong>—</strong>
                        </div>
                        <div class="metric-card">
                            <p>SMS envoyés aujourd'hui</p>
                            <strong>{{ smsStats.balance.sentToday }}</strong>
                        </div>
                        <div class="metric-card">
                            <p>SMS envoyés ce mois</p>
                            <strong>{{ smsStats.balance.sentMonth }}</strong>
                        </div>
                        <div class="metric-card">
                            <p>Total SMS envoyés</p>
                            <strong>{{ smsStats.balance.totalSent }}</strong>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <p class="label mb-2">Consommation journalière</p>
                            <div v-for="([day, count]) in dailySeries" :key="day" class="bar-row">
                                <span>{{ day }}</span>
                                <div class="bar-wrap"><div class="bar" :style="{ width: `${Math.round((Number(count) / maxDaily) * 100)}%` }" /></div>
                                <span>{{ count }}</span>
                            </div>
                        </div>
                        <div>
                            <p class="label mb-2">Consommation mensuelle</p>
                            <div v-for="([month, count]) in monthlySeries" :key="month" class="bar-row">
                                <span>{{ month }}</span>
                                <div class="bar-wrap"><div class="bar" :style="{ width: `${Math.round((Number(count) / maxMonthly) * 100)}%` }" /></div>
                                <span>{{ count }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <Button label="Traiter file SMS" icon="pi pi-play" severity="secondary" :loading="smsQueueing" @click="processQueueAction" />
                    </div>
                </div>

                <div class="panel mb-4">
                    <h3 class="font-semibold mb-3">SMS Logs</h3>
                    <div class="overflow-auto">
                        <table class="w-full text-sm sms-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Numéro</th>
                                    <th>Message</th>
                                    <th>Statut</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="log in smsLogs" :key="log.id">
                                    <td>{{ log.date }}</td>
                                    <td>{{ log.patient || '—' }}</td>
                                    <td>{{ log.phone }}</td>
                                    <td class="truncate max-w-xs">{{ log.message }}</td>
                                    <td>{{ log.status }}</td>
                                    <td>{{ log.type }}</td>
                                    <td>{{ log.source }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel mb-4">
                    <h3 class="font-semibold mb-3">Templates SMS</h3>
                    <div class="grid lg:grid-cols-2 gap-4">
                        <div>
                            <Select v-model="selectedTemplateCode" :options="smsTemplates" optionLabel="name" optionValue="code" class="w-full" />
                            <Textarea
                                v-if="selectedTemplate"
                                v-model="selectedTemplate.content"
                                rows="8"
                                autoResize
                                class="mt-3 w-full"
                            />
                            <div class="flex justify-end mt-3">
                                <Button label="Sauvegarder templates" icon="pi pi-save" :loading="smsTemplateSaving" @click="saveTemplatesAction" />
                            </div>
                        </div>
                        <div>
                            <p class="label mb-2">Variables dynamiques</p>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <InputText v-model="previewVariables.patient_name" placeholder="{patient_name}" />
                                <InputText v-model="previewVariables.date" placeholder="{date}" />
                                <InputText v-model="previewVariables.time" placeholder="{time}" />
                                <InputText v-model="previewVariables.amount" placeholder="{amount}" />
                                <InputText v-model="previewVariables.invoice_number" placeholder="{invoice_number}" />
                                <InputText v-model="previewVariables.cabinet_name" placeholder="{cabinet_name}" />
                            </div>
                            <div class="flex gap-2 mb-3">
                                <Button label="Preview" icon="pi pi-eye" severity="secondary" @click="previewTemplateAction" />
                            </div>
                            <Textarea v-model="previewResult" rows="6" autoResize class="w-full" readonly />
                            <div class="text-xs text-surface-500 mt-2">
                                {{ previewCharacters }} caractères • estimation {{ previewEstimatedSms }} SMS
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h3 class="font-semibold mb-3">SMS manuel</h3>
                    <div class="grid md:grid-cols-2 gap-3">
                        <InputText v-model="manualSms.phone" placeholder="Numéro" />
                        <InputText v-model="manualSms.message" placeholder="Message" />
                    </div>
                    <div class="mt-3 flex justify-end">
                        <Button label="Envoyer SMS manuel" icon="pi pi-send" @click="sendManualSmsAction" />
                    </div>
                </div>
            </section>
        </div>

        <aside class="settings-sidebar">
            <div class="sidebar-card">
                <p class="font-semibold mb-3">Sections</p>
                <nav class="flex flex-col gap-2">
                    <button
                        v-for="section in sections"
                        :key="section.id"
                        type="button"
                        class="anchor-btn"
                        :class="[{ active: activeSection === section.id }, { child: section.id.startsWith('appearance-') && section.id !== 'appearance' }]"
                        @click="scrollToSection(section.id)"
                    >
                        {{ section.label }}
                    </button>
                </nav>
            </div>
        </aside>
    </div>
</template>

<style scoped>
.settings-page {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 240px;
    gap: 1rem;
    background-color: var(--surface-overlay);
}

.settings-main {
    min-width: 0;
}

.settings-section {
    background: none;
    border: none;
    padding: 1rem;
    margin-bottom: 1rem;
    scroll-margin-top: 80px;
}

.panel {
    border: 1px solid var(--surface-border);
    border-radius: 0;
    padding: 0.9rem;
    background: var(--surface-0);
}

.panel h3 {
    font-size: 1.2rem;
}

.section-title{
    padding-bottom: 10px;
    border-bottom: 1px solid var(--surface-border);
}

.label {
    font-size: 0.85rem;
    font-weight: 600;
    display: block;
    margin-bottom: 0.35rem;
}

.color-dot {
    width: 1.3rem;
    height: 1.3rem;
    border-radius: 999px;
    border: none;
    cursor: pointer;
}

.color-dot.selected {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.settings-sidebar {
    position: sticky;
    top: 5rem;
    align-self: start;
    margin-top: 20px;
    max-height: calc(100vh - 2rem);
}

.sidebar-card {
    border-left: 1px solid var(--surface-border);
    border-radius: none;
    padding: 0.9rem;
    background: none;
}

.anchor-btn {
    text-align: left; 
    padding: 0.55rem 0.7rem;
    background: transparent;
    cursor: pointer;
}

.anchor-btn.child {
    opacity: 0.85;
    padding-inline-start: 1.3rem;
}

.anchor-btn.active {
    border-inline-start: 2px solid ;
    border-color: var(--primary-color);
    background: color-mix(in srgb, var(--primary-color), transparent 90%);
}

.metric-card {
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    padding: 0.7rem;
}

.metric-card p {
    font-size: 0.8rem;
    opacity: 0.7;
}

.metric-card strong {
    font-size: 1.05rem;
}

.bar-row {
    display: grid;
    grid-template-columns: 90px 1fr 36px;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
}

.bar-wrap {
    height: 8px;
    border-radius: 999px;
    background: var(--surface-200);
    overflow: hidden;
}

.bar {
    height: 8px;
    border-radius: 999px;
    background: var(--primary-color);
}

.sms-table th,
.sms-table td {
    border-bottom: 1px solid var(--surface-border);
    padding: 0.4rem;
    text-align: left;
}

@media (max-width: 1024px) {
    .settings-page {
        grid-template-columns: 1fr;
    }

    .settings-sidebar {
        position: static;
        max-height: none;
    }
}
</style>
