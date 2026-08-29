<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import SelectButton from 'primevue/selectbutton';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import Tag from 'primevue/tag';
import StatsCardsGrid from '@/components/rapport/common/StatsCardsGrid.vue';
import { useFinances } from '@/composables/useFinances';
import {
    DAY_PRINT_SECTIONS,
    DEFAULT_DAY_PRINT_SELECTION,
    printDayActs,
    printDayCompositeReport,
    printDayTransactions
} from '@/utils/crossTableDayPrint';

const props = defineProps({
    visible: { type: Boolean, default: false },
    date: { type: String, default: '' }
});

const emit = defineEmits(['update:visible']);

const { crossTableDayOverview, loading, fetchCrossTableDayOverview } = useFinances();

const activeTab = ref('transactions');
const transactionTypeFilter = ref('all');

const transactionTypeOptions = [
    { label: 'Tous', value: 'all' },
    { label: 'Revenus', value: 'revenue' },
    { label: 'Dépenses', value: 'expense' }
];

const printSelection = reactive({ ...DEFAULT_DAY_PRINT_SELECTION });

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const overview = computed(() => crossTableDayOverview.value || {});
const periodLabel = computed(() => overview.value.dateLabel || overview.value.date || '');

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
    (overview.value.transactions || []).map((row) => ({
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
    { key: 'newPatients', label: 'Nouveaux patients', value: overview.value.patients?.newPatients ?? 0, icon: 'pi pi-user-plus' },
    { key: 'returning', label: 'Patients de retour', value: overview.value.patients?.returningPatients ?? 0, icon: 'pi pi-users' },
    { key: 'consultations', label: 'Consultations', value: overview.value.consultations?.total ?? 0, icon: 'pi pi-briefcase' },
    { key: 'consultPaid', label: 'Consultations payantes', value: overview.value.consultations?.paid ?? 0, icon: 'pi pi-check-circle' },
    { key: 'consultPending', label: 'Consultations en attente', value: overview.value.consultations?.pending ?? 0, icon: 'pi pi-clock' },
    { key: 'appointments', label: 'Rendez-vous planifiés', value: overview.value.appointments?.scheduled ?? 0, icon: 'pi pi-calendar' },
    { key: 'appointmentsConfirmed', label: 'Rendez-vous confirmés', value: overview.value.appointments?.confirmed ?? 0, icon: 'pi pi-calendar-plus' },
    { key: 'appointmentsCancelled', label: 'Annulations / absences', value: overview.value.appointments?.cancelled ?? 0, icon: 'pi pi-user-minus' },
    { key: 'revenue', label: 'Encaissements validés', value: formatFcfa(overview.value.totals?.revenue), icon: 'pi pi-arrow-down-left', iconBg: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300' },
    { key: 'expense', label: 'Dépenses validées', value: formatFcfa(overview.value.totals?.expense), icon: 'pi pi-arrow-up-right', iconBg: 'bg-rose-50 text-rose-600 dark:bg-rose-900/20 dark:text-rose-300' },
    { key: 'doctors', label: 'Médecins actifs', value: (overview.value.doctors || []).length, icon: 'pi pi-id-card' },
    { key: 'doctorRevenue', label: 'Total facturé médecins', value: formatFcfa(overview.value.doctorsKpi?.totalApport ?? overview.value.doctorsKpi?.totalRevenue), icon: 'pi pi-chart-bar' },
    { key: 'doctorInsurance', label: 'Parts assurance', value: formatFcfa(overview.value.doctorsKpi?.totalPartAssurance), icon: 'pi pi-shield' },
    { key: 'doctorCash', label: 'Encaissé médecins', value: formatFcfa(overview.value.doctorsKpi?.totalPaidCash ?? overview.value.doctorsKpi?.totalPaid), icon: 'pi pi-wallet' },
    { key: 'cabinetRevenue', label: 'Revenus services cabinet', value: formatFcfa(overview.value.doctorsKpi?.revenusServicesCabinet ?? overview.value.doctorsKpi?.totalCabinetRevenue ?? 0), icon: 'pi pi-building' }
]);

const actsTotal = computed(() =>
    (overview.value.actes || []).reduce((sum, act) => sum + Number(act.montant || 0), 0)
);

const resetPrintSelection = () => {
    Object.assign(printSelection, DEFAULT_DAY_PRINT_SELECTION);
};

const loadOverview = async () => {
    if (!props.date) {
        return;
    }
    await fetchCrossTableDayOverview(props.date);
};

watch(
    () => [props.visible, props.date],
    ([visible, date]) => {
        if (visible && date) {
            activeTab.value = 'transactions';
            transactionTypeFilter.value = 'all';
            resetPrintSelection();
            loadOverview();
        }
    }
);

const handlePrintTransactions = () => {
    printDayTransactions(filteredTransactions.value, {
        dateLabel: periodLabel.value,
        filterLabel: transactionFilterLabel.value
    });
};

const handlePrintActs = () => {
    printDayActs(overview.value.actes || [], { dateLabel: periodLabel.value });
};

const handlePrintComposite = () => {
    printDayCompositeReport(overview.value, { ...printSelection });
};

const toggleAllPrintSections = (checked) => {
    DAY_PRINT_SECTIONS.forEach((section) => {
        printSelection[section.key] = checked;
    });
};

const allPrintSectionsSelected = computed(() => DAY_PRINT_SECTIONS.every((section) => printSelection[section.key]));
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :header="`Détail du ${periodLabel || 'jour'}`"
        :style="{ width: 'min(96vw, 1100px)' }"
        :breakpoints="{ '960px': '96vw' }"
        :draggable="false"
    >
        <div v-if="loading.dayOverview" class="space-y-3 py-6">
            <div v-for="index in 4" :key="index" class="h-12 animate-pulse rounded-xl bg-surface-100 dark:bg-surface-700/50"></div>
        </div>

        <Tabs v-else v-model:value="activeTab" class="mt-1">
            <TabList>
                <Tab value="transactions">Transactions</Tab>
                <Tab value="synthese">Détails / synthèse</Tab>
                <Tab value="actes">Actes médicaux</Tab>
                <Tab value="imprimer">Imprimer</Tab>
            </TabList>

            <TabPanels class="mt-4">
                <TabPanel value="transactions">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <SelectButton
                            v-model="transactionTypeFilter"
                            :options="transactionTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            :allowEmpty="false"
                        />
                        <Button
                            label="Imprimer"
                            icon="pi pi-print"
                            outlined
                            size="small"
                            :disabled="!filteredTransactions.length"
                            @click="handlePrintTransactions"
                        />
                    </div>

                    <DataTable
                        :value="filteredTransactions"
                        dataKey="id"
                        paginator
                        :rows="8"
                        responsiveLayout="scroll"
                        stripedRows
                        class="text-sm"
                    >
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
                            <div class="py-8 text-center text-surface-500">Aucune transaction validée pour cette journée.</div>
                        </template>
                    </DataTable>
                </TabPanel>

                <TabPanel value="synthese">
                    <StatsCardsGrid
                        title="Synthèse de la journée"
                        :subtitle="periodLabel ? `Date : ${periodLabel}` : ''"
                        :items="synthesisItems"
                        :loading="loading.dayOverview"
                    />
                </TabPanel>

                <TabPanel value="actes">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-surface-600 dark:text-surface-300">
                            {{ (overview.actes || []).length }} acte(s) · Total {{ formatFcfa(actsTotal) }}
                        </p>
                        <Button
                            label="Imprimer"
                            icon="pi pi-print"
                            outlined
                            size="small"
                            :disabled="!(overview.actes || []).length"
                            @click="handlePrintActs"
                        />
                    </div>

                    <DataTable
                        :value="overview.actes || []"
                        dataKey="description"
                        paginator
                        :rows="8"
                        responsiveLayout="scroll"
                        stripedRows
                        class="text-sm"
                    >
                        <Column field="date" header="Date" sortable />
                        <Column field="medecin" header="Médecin" sortable />
                        <Column field="patient" header="Patient" sortable />
                        <Column field="description" header="Description" sortable />
                        <Column field="montant" header="Montant" sortable>
                            <template #body="{ data }">{{ formatFcfa(data.montant) }}</template>
                        </Column>
                        <template #empty>
                            <div class="py-8 text-center text-surface-500">Aucun acte enregistré pour cette journée.</div>
                        </template>
                    </DataTable>
                </TabPanel>

                <TabPanel value="imprimer">
                    <div class="space-y-4">
                        <p class="text-sm text-surface-600 dark:text-surface-300">
                            Sélectionnez les sections à inclure dans le rapport journalier complet.
                        </p>

                        <div class="flex flex-wrap gap-3">
                            <Button
                                :label="allPrintSectionsSelected ? 'Tout décocher' : 'Tout cocher'"
                                size="small"
                                text
                                @click="toggleAllPrintSections(!allPrintSectionsSelected)"
                            />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="section in DAY_PRINT_SECTIONS"
                                :key="section.key"
                                class="flex items-center gap-3 rounded-xl border border-surface-200/70 px-4 py-3 dark:border-surface-700/60"
                            >
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
    </Dialog>
</template>
