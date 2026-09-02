<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import DatePicker from 'primevue/datepicker';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import { useRapports } from '@/composables/useRapports';
import ReceptionStatsSection from '@/components/rapport/reception/ReceptionStatsSection.vue';
import DoctorReportsTable from '@/components/rapport/common/DoctorReportsTable.vue';
import { loadRapportsPageState, saveRapportsPageState, RAPPORTS_RECEPTION_TABS } from '@/composables/useRapportsPageState';

const { receptionLoading, receptionStats, receptionDoctorReports, fetchReceptionRapport, toIsoDate } = useRapports();

const persistedState = loadRapportsPageState('reception', {
    allowedTabs: RAPPORTS_RECEPTION_TABS,
    defaultTab: 'daily'
});

const selectedDate = ref(persistedState.period.date);
const activeTab = ref(persistedState.tab);
const hasLoaded = ref(false);

const periodLabel = computed(() => selectedDate.value?.toLocaleDateString('fr-FR'));

function setActiveTab(tab) {
    const normalized = RAPPORTS_RECEPTION_TABS.includes(tab) ? tab : 'daily';
    activeTab.value = normalized;
}

function persistPageState() {
    saveRapportsPageState('reception', {
        tab: activeTab.value,
        period: { date: selectedDate.value }
    });
}

async function refresh(silent = false) {
    await fetchReceptionRapport({ date: toIsoDate(selectedDate.value), silent });
}

watch(
    () => selectedDate.value,
    () => {
        if (!selectedDate.value) return;
        persistPageState();
        if (!hasLoaded.value) {
            hasLoaded.value = true;
            refresh(true);
        } else {
            refresh(false);
        }
    }
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
        <div class="flex flex-wrap items-center justify-between gap-2" data-tour="rapports-reception.date">
            <div>
                <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Réception</h2>
                <p class="text-xs text-surface-500 dark:text-surface-400">{{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2" data-tour="rapports-reception.print">
                <DatePicker v-model="selectedDate" showIcon dateFormat="dd/mm/yy" class="w-56" placeholder="Choisir une date" />
            </div>
        </div>

        <Tabs :value="activeTab" @update:value="setActiveTab">
            <TabList data-tour="rapports-reception.tabs" class="flex-wrap">
                <Tab value="daily">Stats du jour</Tab>
                <Tab value="doctors">Par médecin</Tab>
            </TabList>
            <TabPanels class="mt-3">
                <TabPanel value="daily">
                    <div data-tour="rapports-reception.daily">
                        <ReceptionStatsSection :stats="receptionStats" :loading="receptionLoading" :period-label="periodLabel" />
                    </div>
                </TabPanel>
                <TabPanel value="doctors">
                    <div data-tour="rapports-reception.doctors">
                        <DoctorReportsTable title="Rapports périodiques par médecin" :data="receptionDoctorReports" :loading="receptionLoading" :period-label="periodLabel" variant="reception" />
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>
    </div>
</template>
