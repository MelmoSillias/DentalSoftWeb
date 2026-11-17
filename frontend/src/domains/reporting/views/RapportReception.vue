<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import DatePicker from 'primevue/datepicker';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import { useRapports } from '@/domains/reporting/composables/useRapports.js';
import ReceptionStatsSection from '@/domains/reporting/components/reception/ReceptionStatsSection.vue';
import DoctorReportsTable from '@/domains/reporting/components/common/DoctorReportsTable.vue';
import { loadRapportsPageState, saveRapportsPageState, RAPPORTS_RECEPTION_TABS } from '@/domains/reporting/composables/useRapportsPageState.js';

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
    <div class="reports-workspace">
        <div class="reports-toolbar" data-tour="rapports-reception.date">
            <div class="reports-toolbar__context">
                <h2>Activité de la réception</h2>
                <p>Consultations et encaissements de la journée.</p>
            </div>
            <div class="reports-toolbar__controls" data-tour="rapports-reception.print">
                <div class="reports-period-control">
                    <label for="reception-report-date" class="reports-field-label">Journée du rapport</label>
                    <DatePicker v-model="selectedDate" inputId="reception-report-date" showIcon dateFormat="dd/mm/yy" class="reports-date-picker" placeholder="Choisir une date" />
                </div>
            </div>
        </div>

        <Tabs :value="activeTab" class="reports-tabs" @update:value="setActiveTab">
            <TabList data-tour="rapports-reception.tabs" class="flex-wrap">
                <Tab value="daily">Synthèse du jour</Tab>
                <Tab value="doctors">Par médecin</Tab>
            </TabList>
            <TabPanels class="reports-panels">
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
