<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import { useRapports } from '@/composables/useRapports';
import MedecinGlobalStatsSection from '@/components/rapport/medecin/MedecinGlobalStatsSection.vue';
import MedecinQuickStatsSection from '@/components/rapport/medecin/MedecinQuickStatsSection.vue';
import MedecinPeriodicDetailsSection from '@/components/rapport/medecin/MedecinPeriodicDetailsSection.vue';
import MedecinMedicalActsSection from '@/components/rapport/medecin/MedecinMedicalActsSection.vue';
import MedecinProfileSection from '@/components/rapport/medecin/MedecinProfileSection.vue';
import { useAuthStore } from '@/stores/auth';
import { loadRapportsPageState, saveRapportsPageState, RAPPORTS_MEDECIN_TABS } from '@/composables/useRapportsPageState';

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
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2" data-tour="rapports-medecin.range">
            <div class="min-w-0">
                <h2 class="truncate text-lg font-semibold text-surface-900 dark:text-surface-0">Bienvenue {{ connectedMedecinFullName }}</h2>
                <p class="text-xs text-surface-500 dark:text-surface-400">{{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <PanelDatePicker v-model="range" showIcon dateFormat="dd/mm/yy" class="w-64" placeholder="Choisir période" />
                <Button label="Rafraîchir" icon="pi pi-refresh" outlined size="small" @click="refresh(false)" />
            </div>
        </div>

        <Tabs :value="activeTab" @update:value="setActiveTab">
            <TabList data-tour="rapports-medecin.tabs" class="flex-wrap">
                <Tab value="summary">Synthèse</Tab>
                <Tab value="activity">Activité</Tab>
                <Tab value="acts">Actes</Tab>
                <Tab value="profile">Profil</Tab>
            </TabList>
            <TabPanels class="mt-3">
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
