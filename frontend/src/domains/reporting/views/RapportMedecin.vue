<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import PanelDatePicker from '@/shared/ui/common/PanelDatePicker.vue';
import { useRapports } from '@/domains/reporting/composables/useRapports.js';
import MedecinGlobalStatsSection from '@/domains/reporting/components/medecin/MedecinGlobalStatsSection.vue';
import MedecinQuickStatsSection from '@/domains/reporting/components/medecin/MedecinQuickStatsSection.vue';
import MedecinPeriodicDetailsSection from '@/domains/reporting/components/medecin/MedecinPeriodicDetailsSection.vue';
import MedecinMedicalActsSection from '@/domains/reporting/components/medecin/MedecinMedicalActsSection.vue';
import MedecinProfileSection from '@/domains/reporting/components/medecin/MedecinProfileSection.vue';
import { useAuthStore } from '@/domains/auth/stores/auth.js';
import { loadRapportsPageState, saveRapportsPageState, RAPPORTS_MEDECIN_TABS } from '@/domains/reporting/composables/useRapportsPageState.js';

const { medecinLoading, medecinData, fetchMedecinRapport, toIsoDate } = useRapports();
const auth = useAuthStore();

const persistedState = loadRapportsPageState('medecin', {
    allowedTabs: RAPPORTS_MEDECIN_TABS,
    defaultTab: 'summary'
});

const range = ref(persistedState.period.range);
const activeTab = ref(persistedState.tab);
const hasLoaded = ref(false);

const periodLabel = computed(() => {
    const [start, end] = range.value || [];
    if (!start || !end) return 'Choisir période';
    return `${start.toLocaleDateString('fr-FR')} - ${end.toLocaleDateString('fr-FR')}`;
});

const connectedMedecinFullName = computed(() => {
    const identity = medecinData.value?.identity || {};
    const fromRapport = medecinData.value?.fullName || identity.fullName || [identity.prenom, identity.nom].filter(Boolean).join(' ').trim();
    if (fromRapport) return fromRapport;

    const user = auth.user || {};
    return [user.prenom, user.nom].filter(Boolean).join(' ').trim() || user.fullName || user.name || user.username || '';
});

function setActiveTab(tab) {
    const normalized = RAPPORTS_MEDECIN_TABS.includes(tab) ? tab : 'summary';
    activeTab.value = normalized;
}

function persistPageState() {
    saveRapportsPageState('medecin', {
        tab: activeTab.value,
        period: { range: range.value }
    });
}

async function refresh(silent = false) {
    const [start, end] = range.value || [];
    await fetchMedecinRapport({ from: toIsoDate(start), to: toIsoDate(end), silent });
}

watch(
    () => range.value,
    () => {
        if (!range.value?.[0] || !range.value?.[1]) return;
        persistPageState();
        if (!hasLoaded.value) {
            hasLoaded.value = true;
            refresh(true);
        } else {
            refresh(false);
        }
    },
    { deep: true }
);

watch(activeTab, () => {
    persistPageState();
});

onMounted(() => {
    refresh(true);
});
</script>

<template>
    <div class="reports-workspace">
        <div class="reports-toolbar" data-tour="rapports-medecin.range">
            <div class="reports-toolbar__context">
                <h2>{{ connectedMedecinFullName || 'Mon activité' }}</h2>
                <p>Votre activité · {{ periodLabel }}</p>
            </div>
            <div class="reports-toolbar__controls">
                <div class="reports-period-control">
                    <span id="doctor-report-period" class="reports-field-label">Période du rapport</span>
                    <PanelDatePicker v-model="range" aria-labelledby="doctor-report-period" showIcon dateFormat="dd/mm/yy" class="reports-date-picker" placeholder="Choisir une période" />
                </div>
                <Button label="Actualiser" icon="pi pi-refresh" outlined size="small" :loading="medecinLoading" @click="refresh(false)" />
            </div>
        </div>

        <Tabs :value="activeTab" class="reports-tabs" @update:value="setActiveTab">
            <TabList data-tour="rapports-medecin.tabs" class="flex-wrap">
                <Tab value="summary">Synthèse</Tab>
                <Tab value="activity">Activité</Tab>
                <Tab value="acts">Actes</Tab>
                <Tab value="profile">Profil</Tab>
            </TabList>
            <TabPanels class="reports-panels">
                <TabPanel value="summary">
                    <div class="space-y-6">
                        <div data-tour="rapports-medecin.global">
                            <MedecinGlobalStatsSection :data="medecinData" :loading="medecinLoading" />
                        </div>
                        <div data-tour="rapports-medecin.quick">
                            <MedecinQuickStatsSection :stats="medecinData.stats" :loading="medecinLoading" />
                        </div>
                    </div>
                </TabPanel>
                <TabPanel value="activity">
                    <div data-tour="rapports-medecin.periodic">
                        <MedecinPeriodicDetailsSection :period="medecinData.period" :loading="medecinLoading" />
                    </div>
                </TabPanel>
                <TabPanel value="acts">
                    <div data-tour="rapports-medecin.acts">
                        <MedecinMedicalActsSection
                            :acts="medecinData.period?.actesMedicaux || []"
                            :reliquat-payments="medecinData.period?.paiementsReliquats || []"
                            :reliquat-total="medecinData.period?.paiementsReliquatsTotal || 0"
                            :loading="medecinLoading"
                        />
                    </div>
                </TabPanel>
                <TabPanel value="profile">
                    <div data-tour="rapports-medecin.profile">
                        <MedecinProfileSection :data="medecinData" :loading="medecinLoading" />
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>
