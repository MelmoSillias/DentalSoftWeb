<template>
    <section class="min-h-screen bg-gradient-to-br from-surface-50 via-surface-50/80 to-surface-100/60 p-4 transition-colors duration-300 dark:from-surface-900 dark:via-surface-900/80 dark:to-surface-800/90 md:p-6 lg:p-8">
        <Toast />
        <ConfirmPopup />

        <div class="mb-6 md:mb-8">
            <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div class="space-y-3" data-tour="admin-finances.header">
                    <div class="flex items-center gap-4">
                        <div class="rounded-2xl bg-gradient-to-br from-primary-500 to-primary-600 p-3 shadow-lg">
                            <i class="pi pi-wallet text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold tracking-tight text-surface-900 dark:text-surface-50 lg:text-4xl">
                                Tableau de bord financier
                            </h1>
                            <p class="mt-1 text-sm text-surface-600 dark:text-surface-300 md:text-base">
                                Transactions, validations manuelles et modes de paiement regroupés par famille
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        label="Nouvelle transaction"
                        icon="pi pi-plus"
                        class="rounded-xl border-0 bg-gradient-to-r from-primary-500 to-primary-600 px-5 py-3 font-medium text-white shadow-lg transition-all duration-300 hover:from-primary-600 hover:to-primary-700 hover:shadow-xl"
                        @click="openTransactionDialog" />
                    <Button
                        label="Nouveau mode"
                        icon="pi pi-credit-card"
                        severity="secondary"
                        outlined
                        class="rounded-xl px-5 py-3 transition-colors hover:bg-surface-100 dark:hover:bg-surface-700"
                        @click="openAddMode" />
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-4 shadow-sm backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="admin-finances.kpi">
            <article class="rounded-2xl border border-primary-200/70 bg-gradient-to-br from-primary-50/80 to-primary-100/50 p-5 shadow-md backdrop-blur-sm dark:border-primary-800/40 dark:from-primary-900/30 dark:to-primary-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-primary-700 dark:text-primary-300">Capital total</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-primary-900 dark:text-primary-100 lg:text-3xl">
                            {{ formatFcfa(capitalTotal) }}
                        </p>
                        <p class="mt-1 text-xs text-primary-600/70 dark:text-primary-400/70">Tous comptes confondus</p>
                    </div>
                    <div class="rounded-lg bg-primary-500/10 p-2 dark:bg-primary-500/20">
                        <i class="pi pi-database text-xl text-primary-500"></i>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50/80 to-emerald-100/50 p-5 shadow-md backdrop-blur-sm dark:border-emerald-800/40 dark:from-emerald-900/20 dark:to-emerald-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Transactions validées</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-900 dark:text-emerald-100 lg:text-3xl">
                            {{ validatedTransactionsCount }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-600/70 dark:text-emerald-400/70">{{ formatFcfa(validatedTransactionsAmount) }} sur la période</p>
                    </div>
                    <div class="rounded-lg bg-emerald-500/10 p-2 dark:bg-emerald-500/20">
                        <i class="pi pi-check-circle text-xl text-emerald-500"></i>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/80 to-amber-100/50 p-5 shadow-md backdrop-blur-sm dark:border-amber-800/40 dark:from-amber-900/20 dark:to-amber-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-amber-700 dark:text-amber-300">En attente</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-amber-900 dark:text-amber-100 lg:text-3xl">
                            {{ pendingTransactionsCount }}
                        </p>
                        <p class="mt-1 text-xs text-amber-600/70 dark:text-amber-400/70">{{ formatFcfa(pendingTransactionsAmount) }} à valider</p>
                    </div>
                    <div class="rounded-lg bg-amber-500/10 p-2 dark:bg-amber-500/20">
                        <i class="pi pi-hourglass text-xl text-amber-500"></i>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-100/50 p-5 shadow-md backdrop-blur-sm dark:border-slate-800/40 dark:from-slate-900/20 dark:to-slate-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-300">Modes actifs</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-surface-100 lg:text-3xl">
                            {{ comptesActifsCount }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500/70 dark:text-slate-400/70">{{ insuranceMethodsCount }} assurance(s) configurée(s)</p>
                    </div>
                    <div class="rounded-lg bg-slate-500/10 p-2 dark:bg-slate-500/20">
                        <i class="pi pi-credit-card text-xl text-slate-500"></i>
                    </div>
                </div>
            </article>
        </div>

        <Tabs :value="activeTab" @update:value="setActiveTab">
            <TabList data-tour="admin-finances.tabs">
                <Tab value="tables">Tableaux</Tab>
                <Tab value="charts">Graphiques</Tab>
            </TabList>

            <TabPanels class="mt-4">
                <TabPanel value="tables">
                    <div class="space-y-6">
                        <section data-tour="admin-finances.transactions" class="overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
                            <div class="border-b border-surface-200/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 px-5 py-4 dark:border-surface-700/50 dark:from-surface-900/50 dark:to-surface-800/30 md:px-6">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Historique des transactions</h2>
                                        <p class="text-sm text-surface-500 dark:text-surface-400">Filtre par période, statut de validation et recherche libre.</p>
                                    </div>

                                    <div class="grid w-full gap-3 md:grid-cols-2 xl:w-auto xl:grid-cols-4">
                                        <DatePicker
                                            v-model="transactionRange"
                                            selectionMode="range"
                                            dateFormat="dd/mm/yy"
                                            showIcon
                                            class="w-full min-w-0 xl:w-64"
                                            inputClass="w-full" />
                                        <Select
                                            v-model="transactionStatusFilter"
                                            :options="transactionStatusOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="Tous les statuts"
                                            class="w-full min-w-0 xl:w-52" />
                                        <InputText
                                            v-model="transactionSearch"
                                            placeholder="Rechercher une transaction"
                                            class="w-full min-w-0 xl:w-72" />
                                        <Button icon="pi pi-refresh" label="Rafraîchir" severity="secondary" outlined @click="loadTransactions" />
                                    </div>
                                </div>
                            </div>

                            <DataTable
                                :value="filteredTransactionsView"
                                dataKey="id"
                                :loading="loading.transactions"
                                paginator
                                :rows="10"
                                :rowsPerPageOptions="[5, 10, 20, 50]"
                                responsiveLayout="scroll"
                                stripedRows>
                                <Column field="dateLabel" header="Date" sortable>
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-2">
                                            <i class="pi pi-calendar text-surface-400"></i>
                                            <span class="font-medium text-surface-900 dark:text-surface-100">{{ data.dateLabel }}</span>
                                        </div>
                                    </template>
                                </Column>
                                <Column field="description" header="Description" sortable>
                                    <template #body="{ data }">
                                        <div class="max-w-md truncate" :title="data.description">
                                            {{ data.description || 'Sans description' }}
                                        </div>
                                    </template>
                                </Column>
                                <Column field="typeLabel" header="Type" sortable>
                                    <template #body="{ data }">
                                        <Tag :value="data.typeLabel" :severity="data.typeSeverity" />
                                    </template>
                                </Column>
                                <Column field="amountValue" header="Montant" sortable>
                                    <template #body="{ data }">
                                        <span class="font-semibold" :class="data.typeKey === 'entry' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                            {{ formatFcfa(data.amountValue) }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="modeLabel" header="Mode" sortable></Column>
                                <Column field="statusLabel" header="Statut" sortable>
                                    <template #body="{ data }">
                                        <Tag :value="data.statusLabel" :severity="data.statusSeverity" />
                                    </template>
                                </Column>
                                <Column header="Actions" style="width: 190px">
                                    <template #body="{ data }">
                                        <div v-if="data.statusKey === 'pending'" class="flex gap-2" data-tour="admin-finances.validation">
                                            <Button icon="pi pi-check" text severity="success" title="Valider" @click="handleValidateTransaction(data)" />
                                            <Button icon="pi pi-times" text severity="danger" title="Rejeter" @click="handleRejectTransaction(data)" />
                                        </div>
                                        <span v-else class="text-sm text-surface-400">Aucune action</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </section>

                        <section data-tour="admin-finances.methods" class="overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
                            <div class="border-b border-surface-200/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 px-5 py-4 dark:border-surface-700/50 dark:from-surface-900/50 dark:to-surface-800/30 md:px-6">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Modes de paiement</h2>
                                        <p class="text-sm text-surface-500 dark:text-surface-400">Modes classiques regroupés séparément des assurances avec taux par défaut.</p>
                                    </div>

                                    <div class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto">
                                        <InputText v-model="modeSearch" placeholder="Rechercher un mode" class="w-full sm:w-72" />
                                        <Button icon="pi pi-plus" label="Ajouter" @click="openAddMode" />
                                    </div>
                                </div>
                            </div>

                            <DataTable
                                :value="filteredPaymentMethodsView"
                                dataKey="id"
                                :loading="loading.methods"
                                paginator
                                :rows="8"
                                :rowsPerPageOptions="[8, 16, 24]"
                                responsiveLayout="scroll"
                                rowGroupMode="subheader"
                                groupRowsBy="groupLabel"
                                sortField="groupLabel"
                                :sortOrder="1">
                                <template #groupheader="{ data }">
                                    <div class="flex items-center gap-2 border-l-4 border-primary-500 bg-surface-50 px-4 py-3 dark:bg-surface-900/60">
                                        <i :class="data.familyKey === 'insurance' ? 'pi pi-shield' : 'pi pi-wallet'" class="text-primary-500"></i>
                                        <span class="font-semibold text-surface-900 dark:text-surface-100">{{ data.groupLabel }}</span>
                                    </div>
                                </template>

                                <Column field="libelle" header="Libellé" sortable>
                                    <template #body="{ data }">
                                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ data.libelle }}</span>
                                    </template>
                                </Column>
                                <Column field="typeLabel" header="Type" sortable>
                                    <template #body="{ data }">
                                        <Tag :value="data.typeLabel" :severity="data.familyKey === 'insurance' ? 'info' : 'secondary'" />
                                    </template>
                                </Column>
                                <Column field="coverageRate" header="Prise en charge">
                                    <template #body="{ data }">
                                        <span v-if="data.familyKey === 'insurance'" class="font-medium text-surface-700 dark:text-surface-300">
                                            {{ formatCoverageRate(data.coverageRate) }}
                                        </span>
                                        <span v-else class="text-sm text-surface-400">Non applicable</span>
                                    </template>
                                </Column>
                                <Column field="statusLabel" header="Statut" sortable>
                                    <template #body="{ data }">
                                        <Tag :value="data.statusLabel" :severity="data.actif ? 'success' : 'secondary'" />
                                    </template>
                                </Column>
                                <Column header="Actions" style="width: 140px">
                                    <template #body="{ data }">
                                        <div class="flex gap-1">
                                            <Button icon="pi pi-pencil" text severity="info" title="Modifier" @click="openEditMode(data)" />
                                            <Button
                                                :icon="data.actif ? 'pi pi-power-off' : 'pi pi-check'"
                                                text
                                                :severity="data.actif ? 'warning' : 'success'"
                                                :title="data.actif ? 'Désactiver' : 'Activer'"
                                                @click="handleToggleMode({ mode: data })" />
                                            <Button icon="pi pi-trash" text severity="danger" title="Supprimer" @click="handleDeleteMode({ mode: data })" />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </section>
                    </div>
                </TabPanel>

                <TabPanel value="charts">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                            <section data-tour="admin-finances.monthly-flow" class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 xl:col-span-2 md:p-6">
                                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Flux mensuel global</h2>
                                        <p class="text-sm text-surface-500 dark:text-surface-400">Entrées, dépenses et résultat net sur l'année sélectionnée.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Select v-model="selectedYear" :options="yearOptions" optionLabel="label" optionValue="value" class="w-40" />
                                        <Button icon="pi pi-refresh" text rounded severity="secondary" @click="refreshAll" />
                                    </div>
                                </div>

                                <div class="h-80">
                                    <Chart type="bar" :data="monthlyFlowData" :options="monthlyFlowOptions" class="h-full w-full" />
                                </div>
                            </section>

                            <section data-tour="admin-finances.distribution" class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 md:p-6">
                                <div class="mb-6">
                                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Répartition des encaissements</h2>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Transactions d'entrée regroupées par mode sur la période affichée.</p>
                                </div>

                                <div class="h-80">
                                    <Chart type="doughnut" :data="paymentDistributionData" :options="paymentDistributionOptions" class="h-full w-full" />
                                </div>
                            </section>
                        </div>

                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
                            <section data-tour="admin-finances.accounts" class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 md:p-6">
                                <div class="mb-6">
                                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Solde par compte</h2>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Entrées, sorties et solde courant par compte actif.</p>
                                </div>

                                <div class="h-80">
                                    <Chart type="bar" :data="accountFlowData" :options="accountFlowOptions" class="h-full w-full" />
                                </div>
                            </section>

                            <section class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 md:p-6">
                                <div class="mb-6">
                                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Capital par compte</h2>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Répartition du capital disponible sur tous les comptes.</p>
                                </div>

                                <div class="h-80">
                                    <Chart type="doughnut" :data="capitalShareData" :options="capitalShareOptions" class="h-full w-full" />
                                </div>
                            </section>

                            <section data-tour="admin-finances.status" class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 md:p-6">
                                <div class="mb-6">
                                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Statuts de validation</h2>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Visibilité immédiate sur les flux en attente, validés et rejetés.</p>
                                </div>

                                <div class="h-80">
                                    <Chart type="doughnut" :data="validationStatusData" :options="validationStatusOptions" class="h-full w-full" />
                                </div>
                            </section>
                        </div>

                        <section class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 md:p-6">
                            <div class="mb-6">
                                <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Évolution du capital</h2>
                                <p class="text-sm text-surface-500 dark:text-surface-400">Croissance cumulée du capital sur l'année sélectionnée.</p>
                            </div>

                            <div class="h-80">
                                <Chart type="line" :data="capitalEvolutionData" :options="capitalEvolutionOptions" class="h-full w-full" />
                            </div>
                        </section>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>

        <div data-tour="admin-finances.dialogs">
            <TransactionFormDialog
                v-model:visible="transactionDialogVisible"
                :payment-methods="paymentMethodsView"
                :loading="loading.action"
                @submit="handleTransactionSubmit" />

            <PaymentModeFormDialog
                v-model:visible="modeDialogVisible"
                :mode="editingMode"
                :loading="loading.action"
                @submit="handleModeSubmit" />
        </div>
    </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Chart from 'primevue/chart';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import PaymentModeFormDialog from '@/components/administration/finances/PaymentModeFormDialog.vue';
import TransactionFormDialog from '@/components/administration/finances/TransactionFormDialog.vue';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAdministrationFinancesTour, resolveAdministrationFinancesTourGroup } from '@/tours/administrationFinancesTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import { useFinances } from '@/composables/useFinances';
import {
    formatCoverageRate,
    getPaymentCoverageRate,
    getPaymentMethodDefinition,
    isInsuranceMethod,
    normalizePaymentString,
    resolvePaymentMethodFamily,
    resolvePaymentMethodTypeKey,
    sortPaymentMethods
} from '@/utils/paymentMethodUtils';

const toast = useToast();
const confirm = useConfirm();

const {
    chartData,
    paymentMethods,
    transactions,
    loading,
    fetchChartData,
    fetchPaymentMethods,
    fetchTransactionsRange,
    createTransaction,
    createPaymentMethod,
    updatePaymentMethod,
    deletePaymentMethod,
    togglePaymentMethod,
    validateTransaction,
    rejectTransaction
} = useFinances();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Finances' }];

const activeTab = ref('tables');
const transactionDialogVisible = ref(false);
const modeDialogVisible = ref(false);
const editingMode = ref(null);
const isGuidedTourStarting = ref(false);

const today = new Date();
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

const selectedYear = ref(today.getFullYear());
const transactionRange = ref([startOfMonth, today]);
const transactionSearch = ref('');
const transactionStatusFilter = ref('all');
const modeSearch = ref('');

const setActiveTab = (value) => {
    activeTab.value = value || 'tables';
};

const hasOpenDialogs = computed(() => transactionDialogVisible.value || modeDialogVisible.value);

const transactionStatusOptions = [
    { label: 'Tous les statuts', value: 'all' },
    { label: 'En attente', value: 'pending' },
    { label: 'Validées', value: 'validated' },
    { label: 'Rejetées', value: 'rejected' }
];

const normalizeText = (value) => normalizePaymentString(value).replace(/_/g, ' ');

const formatFcfa = (value) =>
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(Number(value || 0));

const formatDateTime = (value) => {
    if (!value) {
        return '--';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString('fr-FR', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const resolveTransactionStatus = (row) => {
    const rawStatus = normalizeText(row?.validationStatus || row?.status || row?.validation_status || '');
    const validated = row?.validated ?? row?.isValidated ?? row?.is_validated;

    if (rawStatus.includes('reject')) {
        return { key: 'rejected', label: 'Rejetée', severity: 'danger' };
    }
    if (rawStatus.includes('valid')) {
        return { key: 'validated', label: 'Validée', severity: 'success' };
    }
    if (validated === true) {
        return { key: 'validated', label: 'Validée', severity: 'success' };
    }
    return { key: 'pending', label: 'En attente', severity: 'warning' };
};

const transactionsView = computed(() =>
    (transactions.value || []).map((row) => {
        const typeLabel = row?.type || '--';
        const normalizedType = normalizeText(typeLabel);
        const typeKey = normalizedType.includes('entree') ? 'entry' : normalizedType.includes('sortie') ? 'exit' : 'other';
        const status = resolveTransactionStatus(row);
        const dateValue = row?.dateTransaction || row?.date;
        const mode = row?.modeDePaiement || {};
        const modeLabel = mode?.libelle || mode?.label || row?.mode || '--';

        return {
            ...row,
            dateLabel: formatDateTime(dateValue),
            amountValue: Number(row?.amount ?? row?.montant ?? 0),
            typeKey,
            typeLabel,
            typeSeverity: typeKey === 'entry' ? 'success' : typeKey === 'exit' ? 'danger' : 'secondary',
            modeLabel,
            statusKey: status.key,
            statusLabel: status.label,
            statusSeverity: status.severity,
            searchBlob: normalizeText([
                formatDateTime(dateValue),
                row?.description,
                typeLabel,
                modeLabel,
                status.label,
                row?.amount,
                row?.montant
            ].join(' '))
        };
    })
);

const filteredTransactionsView = computed(() => {
    const searchQuery = normalizeText(transactionSearch.value);
    return transactionsView.value.filter((row) => {
        const matchesStatus = transactionStatusFilter.value === 'all' || row.statusKey === transactionStatusFilter.value;
        const matchesSearch = !searchQuery || row.searchBlob.includes(searchQuery);
        return matchesStatus && matchesSearch;
    });
});

const paymentMethodsView = computed(() =>
    sortPaymentMethods(paymentMethods.value || []).map((mode) => {
        const definition = getPaymentMethodDefinition(mode);
        const familyKey = resolvePaymentMethodFamily(mode);
        const coverageRate = getPaymentCoverageRate(mode);
        const normalizedLabel = normalizeText(mode?.libelle);
        const isLocked = resolvePaymentMethodTypeKey(mode) === 'cash' || normalizedLabel.includes('par defaut');

        return {
            ...mode,
            typeKey: resolvePaymentMethodTypeKey(mode),
            familyKey,
            groupLabel: familyKey === 'insurance' ? 'Assurances' : 'Modes de paiement classiques',
            typeLabel: definition.label,
            coverageRate,
            statusLabel: mode?.actif ? 'Actif' : 'Inactif',
            isLocked,
            searchBlob: normalizeText([
                mode?.libelle,
                definition.label,
                familyKey,
                coverageRate,
                mode?.notes
            ].join(' '))
        };
    })
);

const filteredPaymentMethodsView = computed(() => {
    const searchQuery = normalizeText(modeSearch.value);
    return paymentMethodsView.value.filter((row) => !searchQuery || row.searchBlob.includes(searchQuery));
});

const soldesParCompte = computed(() => {
    const chart = chartData.value?.barSoldeChart || {};
    const labels = chart.labels || [];
    return labels.map((label, index) => ({
        label,
        solde: Number(chart.soldes?.[index] ?? 0),
        entree: Number(chart.entrees?.[index] ?? 0),
        depense: Number(chart.depenses?.[index] ?? 0),
        color: chart.colors?.[index] || null
    }));
});

const capitalTotal = computed(() => soldesParCompte.value.reduce((sum, item) => sum + Number(item.solde || 0), 0));
const comptesActifsCount = computed(() => paymentMethodsView.value.filter((mode) => mode.actif).length);
const insuranceMethodsCount = computed(() => paymentMethodsView.value.filter((mode) => isInsuranceMethod(mode)).length);
const validatedTransactionsCount = computed(() => transactionsView.value.filter((row) => row.statusKey === 'validated').length);
const validatedTransactionsAmount = computed(() => transactionsView.value.filter((row) => row.statusKey === 'validated').reduce((sum, row) => sum + row.amountValue, 0));
const pendingTransactionsCount = computed(() => transactionsView.value.filter((row) => row.statusKey === 'pending').length);
const pendingTransactionsAmount = computed(() => transactionsView.value.filter((row) => row.statusKey === 'pending').reduce((sum, row) => sum + row.amountValue, 0));

const yearOptions = computed(() => {
    const years = Array.isArray(chartData.value?.availableYears) && chartData.value.availableYears.length
        ? chartData.value.availableYears
        : [today.getFullYear()];
    return years.map((year) => ({ label: String(year), value: Number(year) }));
});

const monthlyFlowData = computed(() => {
    const months = chartData.value?.months?.length
        ? chartData.value.months
        : ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
    const revenues = Array(months.length).fill(0);
    const expenses = Array(months.length).fill(0);

    (chartData.value?.datasetsComptes || []).forEach((dataset) => {
        const label = normalizeText(dataset?.label);
        const data = Array.isArray(dataset?.data) ? dataset.data : [];

        if (label.includes('entree')) {
            data.forEach((value, index) => {
                revenues[index] += Number(value || 0);
            });
        }

        if (label.includes('depense') || label.includes('sortie')) {
            data.forEach((value, index) => {
                expenses[index] += Number(value || 0);
            });
        }
    });

    const net = revenues.map((value, index) => value - expenses[index]);
    const documentStyle = getComputedStyle(document.documentElement);

    return {
        labels: months,
        datasets: [
            {
                type: 'bar',
                label: 'Entrées',
                data: revenues,
                backgroundColor: documentStyle.getPropertyValue('--p-emerald-500') || '#10b981',
                borderRadius: 6
            },
            {
                type: 'bar',
                label: 'Dépenses',
                data: expenses,
                backgroundColor: documentStyle.getPropertyValue('--p-rose-500') || '#f43f5e',
                borderRadius: 6
            },
            {
                type: 'line',
                label: 'Net',
                data: net,
                borderColor: documentStyle.getPropertyValue('--p-primary-500') || '#3b82f6',
                backgroundColor: 'transparent',
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 3
            }
        ]
    };
});

const accountFlowData = computed(() => {
    const chart = chartData.value?.barSoldeChart || {};
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: chart.labels || [],
        datasets: [
            {
                type: 'bar',
                label: 'Entrées',
                data: chart.entrees || [],
                backgroundColor: documentStyle.getPropertyValue('--p-emerald-500') || '#10b981',
                borderRadius: 6
            },
            {
                type: 'bar',
                label: 'Dépenses',
                data: chart.depenses || [],
                backgroundColor: documentStyle.getPropertyValue('--p-rose-500') || '#f43f5e',
                borderRadius: 6
            },
            {
                type: 'line',
                label: 'Solde',
                data: chart.soldes || [],
                borderColor: documentStyle.getPropertyValue('--p-primary-500') || '#3b82f6',
                backgroundColor: 'transparent',
                tension: 0.35,
                borderWidth: 2,
                pointRadius: 4
            }
        ]
    };
});

const capitalEvolutionData = computed(() => {
    const months = chartData.value?.months?.length
        ? chartData.value.months
        : ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: months,
        datasets: [
            {
                label: 'Capital cumulé',
                data: chartData.value?.evolutionCapital || [],
                borderColor: documentStyle.getPropertyValue('--p-primary-500') || '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.12)',
                fill: true,
                tension: 0.35,
                pointRadius: 3
            }
        ]
    };
});

const capitalShareData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const colorsFallback = [
        documentStyle.getPropertyValue('--p-primary-500') || '#3b82f6',
        documentStyle.getPropertyValue('--p-emerald-500') || '#10b981',
        documentStyle.getPropertyValue('--p-amber-500') || '#f59e0b',
        documentStyle.getPropertyValue('--p-rose-500') || '#f43f5e',
        documentStyle.getPropertyValue('--p-cyan-500') || '#06b6d4',
        documentStyle.getPropertyValue('--p-violet-500') || '#8b5cf6'
    ];

    return {
        labels: soldesParCompte.value.map((item) => item.label),
        datasets: [
            {
                data: soldesParCompte.value.map((item) => Math.max(Number(item.solde || 0), 0)),
                backgroundColor: soldesParCompte.value.map((item, index) => item.color || colorsFallback[index % colorsFallback.length]),
                borderWidth: 0
            }
        ]
    };
});

const paymentDistributionData = computed(() => {
    const rows = filteredTransactionsView.value.filter((row) => row.typeKey === 'entry');
    const bucket = new Map();
    const documentStyle = getComputedStyle(document.documentElement);
    const colors = [
        documentStyle.getPropertyValue('--p-primary-500') || '#3b82f6',
        documentStyle.getPropertyValue('--p-emerald-500') || '#10b981',
        documentStyle.getPropertyValue('--p-amber-500') || '#f59e0b',
        documentStyle.getPropertyValue('--p-cyan-500') || '#06b6d4',
        documentStyle.getPropertyValue('--p-violet-500') || '#8b5cf6',
        documentStyle.getPropertyValue('--p-rose-500') || '#f43f5e'
    ];

    rows.forEach((row) => {
        bucket.set(row.modeLabel, (bucket.get(row.modeLabel) || 0) + row.amountValue);
    });

    const labels = Array.from(bucket.keys());
    const data = Array.from(bucket.values());

    return {
        labels,
        datasets: [
            {
                data,
                backgroundColor: labels.map((_, index) => colors[index % colors.length]),
                borderWidth: 0
            }
        ]
    };
});

const validationStatusData = computed(() => {
    const counts = { pending: 0, validated: 0, rejected: 0 };
    transactionsView.value.forEach((row) => {
        counts[row.statusKey] = (counts[row.statusKey] || 0) + 1;
    });

    return {
        labels: ['En attente', 'Validées', 'Rejetées'],
        datasets: [
            {
                data: [counts.pending, counts.validated, counts.rejected],
                backgroundColor: ['#f59e0b', '#10b981', '#f43f5e'],
                borderWidth: 0
            }
        ]
    };
});

const baseCartesianOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } },
            tooltip: {
                callbacks: {
                    label: (context) => `${context.dataset.label}: ${formatFcfa(context.parsed.y ?? context.parsed)}`
                }
            }
        },
        scales: {
            x: {
                ticks: { color: documentStyle.getPropertyValue('--text-color-secondary') },
                grid: { display: false }
            },
            y: {
                ticks: {
                    color: documentStyle.getPropertyValue('--text-color-secondary'),
                    callback: (value) => formatFcfa(value)
                },
                grid: { color: documentStyle.getPropertyValue('--surface-border') }
            }
        }
    };
});

const monthlyFlowOptions = computed(() => baseCartesianOptions.value);
const accountFlowOptions = computed(() => baseCartesianOptions.value);
const capitalEvolutionOptions = computed(() => ({
    ...baseCartesianOptions.value,
    plugins: {
        ...baseCartesianOptions.value.plugins,
        legend: { display: false }
    }
}));

const baseDoughnutOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } },
            tooltip: {
                callbacks: {
                    label: (context) => `${context.label}: ${formatFcfa(context.parsed)}`
                }
            }
        }
    };
});

const capitalShareOptions = computed(() => baseDoughnutOptions.value);
const paymentDistributionOptions = computed(() => baseDoughnutOptions.value);
const validationStatusOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } }
        }
    };
});

const loadTransactions = async () => {
    const [start, end] = transactionRange.value || [];
    if (!start || !end) {
        return;
    }

    await fetchTransactionsRange({
        startDate: new Date(start).toISOString().slice(0, 10),
        endDate: new Date(end).toISOString().slice(0, 10)
    });
};

const refreshAll = async () => {
    await Promise.all([fetchChartData(selectedYear.value), fetchPaymentMethods()]);
    await loadTransactions();
};

const openTransactionDialog = () => {
    transactionDialogVisible.value = true;
};

const openAddMode = () => {
    editingMode.value = null;
    modeDialogVisible.value = true;
};

const openEditMode = (mode) => {
    editingMode.value = mode;
    modeDialogVisible.value = true;
};

const handleTransactionSubmit = ({ payload, event }) => {
    if (!payload?.modeId || !payload?.montant || !payload?.date) {
        toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Compte, montant et date sont obligatoires.', life: 3000 });
        return;
    }

    confirm.require({
        target: event?.currentTarget,
        message: 'Confirmer la création de cette transaction ?',
        icon: 'pi pi-check',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await createTransaction(payload);
                toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction enregistrée.', life: 3000 });
                transactionDialogVisible.value = false;
                await refreshAll();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Enregistrement impossible.', life: 3500 });
            }
        }
    });
};

const handleModeSubmit = ({ payload, event }) => {
    if (!payload?.libelle) {
        toast.add({ severity: 'warn', summary: 'Libellé requis', detail: 'Veuillez saisir un libellé.', life: 3000 });
        return;
    }

    if (payload?.family === 'insurance' && !(Number(payload?.coverageRate) > 0)) {
        toast.add({ severity: 'warn', summary: 'Assurance', detail: 'Le pourcentage de prise en charge est obligatoire pour une assurance.', life: 3000 });
        return;
    }

    const isEdit = Boolean(editingMode.value?.id);
    confirm.require({
        target: event?.currentTarget,
        message: isEdit ? 'Confirmer la mise à jour du mode ?' : 'Confirmer la création du mode ?',
        icon: 'pi pi-check',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                if (isEdit) {
                    await updatePaymentMethod(editingMode.value.id, payload);
                    toast.add({ severity: 'success', summary: 'Mode de paiement', detail: 'Mode mis à jour.', life: 3000 });
                } else {
                    await createPaymentMethod(payload);
                    toast.add({ severity: 'success', summary: 'Mode de paiement', detail: 'Mode créé.', life: 3000 });
                }
                modeDialogVisible.value = false;
                await refreshAll();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Action impossible.', life: 3500 });
            }
        }
    });
};

const handleDeleteMode = ({ mode }) => {
    if (mode?.isLocked) {
        toast.add({ severity: 'warn', summary: 'Mode protégé', detail: 'Ce mode ne peut pas être supprimé.', life: 3000 });
        return;
    }

    confirm.require({
        message: 'Supprimer ce mode de paiement ?',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await deletePaymentMethod(mode.id);
                toast.add({ severity: 'success', summary: 'Suppression', detail: 'Mode supprimé.', life: 3000 });
                await refreshAll();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Suppression impossible.', life: 3500 });
            }
        }
    });
};

const handleToggleMode = ({ mode }) => {
    if (mode?.isLocked) {
        toast.add({ severity: 'warn', summary: 'Mode protégé', detail: 'Ce mode ne peut pas être désactivé.', life: 3000 });
        return;
    }

    confirm.require({
        message: mode?.actif ? 'Désactiver ce mode de paiement ?' : 'Activer ce mode de paiement ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await togglePaymentMethod(mode.id);
                toast.add({ severity: 'success', summary: 'Statut mis à jour', detail: 'Le mode a été mis à jour.', life: 3000 });
                await refreshAll();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Mise à jour impossible.', life: 3500 });
            }
        }
    });
};

const handleValidateTransaction = (row) => {
    confirm.require({
        message: 'Valider cette transaction en attente ?',
        icon: 'pi pi-check-circle',
        acceptLabel: 'Valider',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await validateTransaction(row.id);
                toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction validée.', life: 3000 });
                await loadTransactions();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Validation impossible.', life: 3500 });
            }
        }
    });
};

const handleRejectTransaction = (row) => {
    confirm.require({
        message: 'Rejeter cette transaction en attente ?',
        icon: 'pi pi-times-circle',
        acceptLabel: 'Rejeter',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await rejectTransaction(row.id, {});
                toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction rejetée.', life: 3000 });
                await loadTransactions();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Rejet impossible.', life: 3500 });
            }
        }
    });
};

const resetTourDialogs = () => {
    transactionDialogVisible.value = false;
    modeDialogVisible.value = false;
    editingMode.value = null;
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'administration-finances' || isGuidedTourStarting.value) {
        return;
    }

    if (loading.transactions || loading.methods || loading.chart || hasOpenDialogs.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidee',
            detail: 'Attendez la fin du chargement et fermez les fenetres ouvertes avant de lancer le tour.',
            life: 3000
        });
        return;
    }

    isGuidedTourStarting.value = true;

    try {
        const steps = createAdministrationFinancesTour({ activeTab: activeTab.value });
        await startTourGuide({
            group: resolveAdministrationFinancesTourGroup(activeTab.value),
            steps,
            onAfterExit: resetTourDialogs,
            onFinish: resetTourDialogs
        });
    } catch (error) {
        console.error('Erreur lancement guided tour finances', error);
        toast.add({
            severity: 'error',
            summary: 'Aide guidee',
            detail: 'Impossible de lancer le tour de la page finances.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

watch(transactionRange, () => {
    loadTransactions();
});

watch(selectedYear, async (value) => {
    if (!value) {
        return;
    }
    await fetchChartData(value);
});

onMounted(async () => {
    await refreshAll();
    if (chartData.value?.year) {
        selectedYear.value = Number(chartData.value.year);
    }
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    resetTourDialogs();
});
</script>