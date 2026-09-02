<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import SelectButton from 'primevue/selectbutton';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';
import StatsCardsGrid from '@/components/rapport/common/StatsCardsGrid.vue';
import { DAY_PRINT_SECTIONS, DEFAULT_DAY_PRINT_SELECTION, printDayActs, printDayCompositeReport, printDayTransactions } from '@/utils/crossTableDayPrint';

const props = defineProps({
    overview: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' },
    scopeLabel: { type: String, default: 'période' }
});

const activeTab = ref('transactions');
const transactionTypeFilter = ref('all');

const transactionTypeOptions = [
    { label: 'Tous', value: 'all' },
    { label: 'Revenus', value: 'revenue' },
    { label: 'Dépenses', value: 'expense' }
];

const printSelection = reactive({ ...DEFAULT_DAY_PRINT_SELECTION });

const formatFcfa = (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(Number(value || 0));

const formatDateTime = (value) => {
    if (!value) {
        return '--';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '--';
    }
    return date.toLocaleString('fr-FR');
};

const transactionsView = computed(() =>
    (props.overview?.transactions || []).map((row) => ({
        ...row,
        dateLabel: formatDateTime(row.validatedAt || row.dateTransaction),
        amountValue: Number(row.amount ?? row.montant ?? 0),
        modeLabel: row.modeDePaiement?.libelle || '--',
        typeSeverity: row.typeKey === 'revenue' ? 'success' : row.typeKey === 'expense' ? 'danger' : 'secondary'
    }))
);

const filteredTransactions = computed(() =>
    transactionsView.value.filter((row) => {
        if (transactionTypeFilter.value === 'all') {
            return true;
        }
        return row.typeKey === transactionTypeFilter.value;
    })
);

const transactionFilterLabel = computed(() => {
    const option = transactionTypeOptions.find((item) => item.value === transactionTypeFilter.value);
    return option?.label || 'Toutes';
});

const synthesisItems = computed(() => [
    { key: 'newPatients', label: 'Nouveaux patients', value: props.overview?.patients?.newPatients ?? 0, icon: 'pi pi-user-plus' },
    { key: 'returning', label: 'Patients de retour', value: props.overview?.patients?.returningPatients ?? 0, icon: 'pi pi-users' },
    { key: 'consultations', label: 'Consultations', value: props.overview?.consultations?.total ?? 0, icon: 'pi pi-briefcase' },
    { key: 'consultPaid', label: 'Consultations payantes', value: props.overview?.consultations?.paid ?? 0, icon: 'pi pi-check-circle' },
    { key: 'consultPending', label: 'Consultations en attente', value: props.overview?.consultations?.pending ?? 0, icon: 'pi pi-clock' },
    { key: 'appointments', label: 'Rendez-vous planifiés', value: props.overview?.appointments?.scheduled ?? 0, icon: 'pi pi-calendar' },
    { key: 'appointmentsConfirmed', label: 'Rendez-vous confirmés', value: props.overview?.appointments?.confirmed ?? 0, icon: 'pi pi-calendar-plus' },
    { key: 'appointmentsCancelled', label: 'Annulations / absences', value: props.overview?.appointments?.cancelled ?? 0, icon: 'pi pi-user-minus' },
    { key: 'revenue', label: 'Encaissements validés', value: formatFcfa(props.overview?.totals?.revenue), icon: 'pi pi-arrow-down-left', iconBg: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300' },
    { key: 'expense', label: 'Dépenses validées', value: formatFcfa(props.overview?.totals?.expense), icon: 'pi pi-arrow-up-right', iconBg: 'bg-rose-50 text-rose-600 dark:bg-rose-900/20 dark:text-rose-300' },
    { key: 'doctors', label: 'Médecins actifs', value: (props.overview?.doctors || []).length, icon: 'pi pi-id-card' },
    { key: 'doctorRevenue', label: 'Total facturé médecins', value: formatFcfa(props.overview?.doctorsKpi?.totalApport ?? props.overview?.doctorsKpi?.totalRevenue), icon: 'pi pi-chart-bar' },
    { key: 'doctorInsurance', label: 'Parts assurance', value: formatFcfa(props.overview?.doctorsKpi?.totalPartAssurance), icon: 'pi pi-shield' },
    { key: 'doctorCash', label: 'Encaissé médecins', value: formatFcfa(props.overview?.doctorsKpi?.totalPaidCash ?? props.overview?.doctorsKpi?.totalPaid), icon: 'pi pi-wallet' },
    { key: 'cabinetRevenue', label: 'Revenus services cabinet', value: formatFcfa(props.overview?.doctorsKpi?.revenusServicesCabinet ?? props.overview?.doctorsKpi?.totalCabinetRevenue ?? 0), icon: 'pi pi-building' }
]);

const actsTotal = computed(() => (props.overview?.actes || []).reduce((sum, act) => sum + Number(act.montant || 0), 0));

const allPrintSectionsSelected = computed(() => DAY_PRINT_SECTIONS.every((section) => printSelection[section.key]));

const resetState = () => {
    activeTab.value = 'transactions';
    transactionTypeFilter.value = 'all';
    Object.assign(printSelection, DEFAULT_DAY_PRINT_SELECTION);
};

const handlePrintTransactions = () => {
    printDayTransactions(filteredTransactions.value, {
        dateLabel: props.periodLabel,
        filterLabel: transactionFilterLabel.value
    });
};

const handlePrintActs = () => {
    printDayActs(props.overview?.actes || [], { dateLabel: props.periodLabel });
};

const handlePrintComposite = () => {
    printDayCompositeReport(props.overview, { ...printSelection });
};

const toggleAllPrintSections = (checked) => {
    DAY_PRINT_SECTIONS.forEach((section) => {
        printSelection[section.key] = checked;
    });
};

watch(
    () => props.periodLabel,
    () => resetState()
);
</script>

<template>
    <div class="rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-sm dark:border-surface-700/50 dark:bg-surface-900/30">
        <div class="border-b border-surface-200/50 px-4 py-3 dark:border-surface-700/50 md:px-5">
            <h3 class="text-base font-semibold text-surface-900 dark:text-surface-100">Détail complet — {{ periodLabel || scopeLabel }}</h3>
            <p class="text-xs text-surface-500 dark:text-surface-400">Transactions, synthèse, actes et impressions pour la {{ scopeLabel }} sélectionnée.</p>
        </div>

        <div class="px-4 py-4 md:px-5">
            <div v-if="loading" class="space-y-3 py-4">
                <div v-for="index in 4" :key="index" class="h-12 animate-pulse rounded-xl bg-surface-100 dark:bg-surface-700/50"></div>
            </div>

            <Tabs v-else v-model:value="activeTab">
                <TabList class="flex-wrap">
                    <Tab value="transactions">Transactions</Tab>
                    <Tab value="synthese">Détails / synthèse</Tab>
                    <Tab value="actes">Actes médicaux</Tab>
                    <Tab value="imprimer">Imprimer</Tab>
                </TabList>

                <TabPanels class="mt-4">
                    <TabPanel value="transactions">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <SelectButton v-model="transactionTypeFilter" :options="transactionTypeOptions" optionLabel="label" optionValue="value" :allowEmpty="false" />
                            <Button label="Imprimer" icon="pi pi-print" outlined size="small" :disabled="!filteredTransactions.length" @click="handlePrintTransactions" />
                        </div>

                        <DataTable :value="filteredTransactions" dataKey="id" paginator :rows="10" responsiveLayout="scroll" stripedRows class="text-sm">
                            <Column field="dateLabel" header="Date validation" sortable />
                            <Column field="description" header="Description" sortable>
                                <template #body="{ data }">
                                    <span class="max-w-xs truncate" :title="data.description">{{ data.description || 'Sans description' }}</span>
                                </template>
                            </Column>
                            <Column field="typeLabel" header="Type" sortable>
                                <template #body="{ data }">
                                    <div class="flex flex-col gap-1">
                                        <Tag :value="data.typeLabel" :severity="data.typeSeverity" />
                                        <small class="text-surface-500">{{ data.motif || 'Sans motif' }}</small>
                                    </div>
                                </template>
                            </Column>
                            <Column field="amountValue" header="Montant" sortable>
                                <template #body="{ data }">
                                    <span class="font-semibold" :class="data.typeKey === 'revenue' ? 'text-emerald-600' : 'text-rose-600'">
                                        {{ formatFcfa(data.amountValue) }}
                                    </span>
                                </template>
                            </Column>
                            <Column field="modeLabel" header="Mode" sortable />
                            <template #empty>
                                <div class="py-8 text-center text-surface-500">Aucune transaction validée pour cette période.</div>
                            </template>
                        </DataTable>
                    </TabPanel>

                    <TabPanel value="synthese">
                        <StatsCardsGrid :title="`Synthèse de la ${scopeLabel}`" :subtitle="periodLabel ? `Période : ${periodLabel}` : ''" :items="synthesisItems" :loading="loading" />
                    </TabPanel>

                    <TabPanel value="actes">
                        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-surface-600 dark:text-surface-300">{{ (overview.actes || []).length }} acte(s) · Total {{ formatFcfa(actsTotal) }}</p>
                            <Button label="Imprimer" icon="pi pi-print" outlined size="small" :disabled="!(overview.actes || []).length" @click="handlePrintActs" />
                        </div>

                        <DataTable :value="overview.actes || []" dataKey="description" paginator :rows="10" responsiveLayout="scroll" stripedRows class="text-sm">
                            <Column field="date" header="Date" sortable />
                            <Column field="medecin" header="Médecin" sortable />
                            <Column field="patient" header="Patient" sortable />
                            <Column field="description" header="Description" sortable />
                            <Column field="montant" header="Montant" sortable>
                                <template #body="{ data }">{{ formatFcfa(data.montant) }}</template>
                            </Column>
                            <template #empty>
                                <div class="py-8 text-center text-surface-500">Aucun acte enregistré pour cette période.</div>
                            </template>
                        </DataTable>
                    </TabPanel>

                    <TabPanel value="imprimer">
                        <div class="space-y-4">
                            <p class="text-sm text-surface-600 dark:text-surface-300">Sélectionnez les sections à inclure dans le rapport complet.</p>

                            <div class="flex flex-wrap gap-3">
                                <Button :label="allPrintSectionsSelected ? 'Tout décocher' : 'Tout cocher'" size="small" text @click="toggleAllPrintSections(!allPrintSectionsSelected)" />
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label v-for="section in DAY_PRINT_SECTIONS" :key="section.key" class="flex items-center gap-3 rounded-xl border border-surface-200/70 px-4 py-3 dark:border-surface-700/60">
                                    <Checkbox v-model="printSelection[section.key]" :binary="true" :inputId="`print-${section.key}`" />
                                    <label :for="`print-${section.key}`" class="text-sm font-medium text-surface-800 dark:text-surface-100">
                                        {{ section.label }}
                                    </label>
                                </label>
                            </div>

                            <div class="flex justify-end pt-2">
                                <Button label="Imprimer le rapport" icon="pi pi-print" @click="handlePrintComposite" />
                            </div>
                        </div>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </div>
    </div>
</template>
