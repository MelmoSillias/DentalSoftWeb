<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import AdminActsStatsSection from '@/components/rapport/admin/AdminActsStatsSection.vue';
import FinanceCrossTable from '@/components/finances/FinanceCrossTable.vue';
import AdminGlobalStatsSection from '@/components/rapport/admin/AdminGlobalStatsSection.vue';
import AdminNonPeriodicDetailsSection from '@/components/rapport/admin/AdminNonPeriodicDetailsSection.vue';
import AdminPeriodicDetailsSection from '@/components/rapport/admin/AdminPeriodicDetailsSection.vue';
import DoctorReportsTable from '@/components/rapport/common/DoctorReportsTable.vue';
import { useRapports } from '@/composables/useRapports';
import {
    loadRapportsPageState,
    saveRapportsPageState,
    RAPPORTS_ADMIN_TABS
} from '@/composables/useRapportsPageState';

const {
    adminLoading,
    adminGlobalStats,
    adminEmployeeDistribution,
    adminLowStockConsumables,
    adminGlobalPatients,
    adminPatientReferrals,
    adminPeriodicPatients,
    adminPeriodicConsultations,
    adminPeriodicAppointments,
    adminRoomUsage,
    adminPaymentBalances,
    adminPaymentFrequency,
    adminActsStats,
    adminDoctorReports,
    fetchAdminRapport,
    toIsoDate
} = useRapports();

const persistedState = loadRapportsPageState('admin', {
    allowedTabs: RAPPORTS_ADMIN_TABS,
    defaultTab: 'overview'
});

const range = ref(persistedState.period.range);
const activeTab = ref(persistedState.tab);
const hasLoaded = ref(false);

const periodLabel = computed(() => {
    const [start, end] = range.value || [];
    if (!start || !end) return 'Choisir période';
    const startLabel = start.toLocaleDateString('fr-FR');
    const endLabel = end.toLocaleDateString('fr-FR');
    return `${startLabel} - ${endLabel}`;
});

function setActiveTab(tab) {
    const normalized = RAPPORTS_ADMIN_TABS.includes(tab) ? tab : 'overview';
    activeTab.value = normalized;
}

function persistPageState() {
    saveRapportsPageState('admin', {
        tab: activeTab.value,
        period: { range: range.value }
    });
}

async function refresh(silent = false) {
    const [start, end] = range.value || [];
    await fetchAdminRapport({ from: toIsoDate(start), to: toIsoDate(end), silent });
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
        <div class="flex flex-wrap items-center justify-end gap-2" data-tour="rapports-admin.range">
            <div class="flex flex-wrap items-center gap-2">
                <PanelDatePicker
                    v-model="range"
                    showIcon
                    dateFormat="dd/mm/yy"
                    class="w-64"
                    placeholder="Choisir période"
                />
                <Button label="Rafraîchir" icon="pi pi-refresh" outlined size="small" @click="refresh(false)" />
            </div>
        </div>

        <Tabs :value="activeTab" @update:value="setActiveTab">
            <TabList data-tour="rapports-admin.tabs" class="flex-wrap">
                <Tab value="overview">Vue d'ensemble</Tab>
                <Tab value="activity">Activité</Tab>
                <Tab value="finances">Finances</Tab>
                <Tab value="acts">Soins</Tab>
                <Tab value="doctors">Médecins</Tab>
            </TabList>
            <TabPanels class="mt-3">
                <TabPanel value="overview">
                    <div class="space-y-4">
                        <div data-tour="rapports-admin.global">
                            <AdminGlobalStatsSection :stats="adminGlobalStats" :loading="adminLoading" />
                        </div>
                        <div data-tour="rapports-admin.non-periodic">
                            <AdminNonPeriodicDetailsSection
                                :employee-distribution="adminEmployeeDistribution"
                                :low-stock="adminLowStockConsumables"
                                :patients="adminGlobalPatients"
                                :patient-referrals="adminPatientReferrals"
                                :loading="adminLoading"
                            />
                        </div>
                    </div>
                </TabPanel>
                <TabPanel value="activity">
                    <div data-tour="rapports-admin.periodic">
                        <AdminPeriodicDetailsSection
                            :patients="adminPeriodicPatients"
                            :consultations="adminPeriodicConsultations"
                            :appointments="adminPeriodicAppointments"
                            :room-usage="adminRoomUsage"
                            :payment-balances="adminPaymentBalances"
                            :payment-frequency="adminPaymentFrequency"
                            :loading="adminLoading"
                            :period-label="periodLabel"
                        />
                    </div>
                </TabPanel>
                <TabPanel value="finances">
                    <FinanceCrossTable
                        show-period-details
                        title="Tableau croisé financier"
                        subtitle="Section dédiée au suivi hebdomadaire des revenus et dépenses validés."
                    />
                </TabPanel>
                <TabPanel value="acts">
                    <div data-tour="rapports-admin.acts">
                        <AdminActsStatsSection
                            :acts-stats="adminActsStats"
                            :loading="adminLoading"
                            :period-label="periodLabel"
                        />
                    </div>
                </TabPanel>
                <TabPanel value="doctors">
                    <div data-tour="rapports-admin.doctors">
                        <DoctorReportsTable
                            :data="adminDoctorReports"
                            :loading="adminLoading"
                            :period-label="periodLabel"
                            :show-kpi="true"
                            variant="admin"
                        />
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>
