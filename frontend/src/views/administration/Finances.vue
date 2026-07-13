<template>
    <section class="min-h-screen bg-gradient-to-br from-surface-50 via-surface-50/80 to-surface-100/60 p-4 transition-colors duration-300 dark:from-surface-900 dark:via-surface-900/80 dark:to-surface-800/90 md:p-6 lg:p-8">
        <AppToast />
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
                                Transactions, validations manuelles, modes de paiement et assurances séparés
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
                </div>
            </div>

            <div class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-4 shadow-sm backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="admin-finances.kpi">
            <article class="rounded-2xl border border-primary-200/70 bg-gradient-to-br from-primary-50/80 to-primary-100/50 p-5 shadow-md backdrop-blur-sm dark:border-primary-800/40 dark:from-primary-900/30 dark:to-primary-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-primary-700 dark:text-primary-300 sm:text-sm">Capital total</p>
                        <p class="mt-2 truncate text-lg font-bold tracking-tight text-primary-900 dark:text-primary-100 sm:text-xl lg:text-2xl">
                            {{ formatFcfa(capitalTotal) }}
                        </p>
                        <p class="mt-1 truncate text-xs text-primary-600/70 dark:text-primary-400/70">Tous comptes confondus</p>
                    </div>
                    <div class="flex-shrink-0 rounded-lg bg-primary-500/10 p-2 dark:bg-primary-500/20">
                        <i class="pi pi-database text-lg text-primary-500 sm:text-xl"></i>
                    </div>
                </div>
            </article>


            <article class="rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/80 to-amber-100/50 p-5 shadow-md backdrop-blur-sm dark:border-amber-800/40 dark:from-amber-900/20 dark:to-amber-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-amber-700 dark:text-amber-300 sm:text-sm">En attente</p>
                        <p class="mt-2 truncate text-lg font-bold tracking-tight text-amber-900 dark:text-amber-100 sm:text-xl lg:text-2xl">
                            {{ pendingTransactionsCount }}
                        </p>
                        <p class="mt-1 truncate text-xs text-amber-600/70 dark:text-amber-400/70">{{ formatFcfa(pendingTransactionsAmount) }} à valider</p>
                    </div>
                    <div class="flex-shrink-0 rounded-lg bg-amber-500/10 p-2 dark:bg-amber-500/20">
                        <i class="pi pi-hourglass text-lg text-amber-500 sm:text-xl"></i>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-100/50 p-5 shadow-md backdrop-blur-sm dark:border-slate-800/40 dark:from-slate-900/20 dark:to-slate-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 sm:text-sm">Modes actifs</p>
                        <p class="mt-2 truncate text-lg font-bold tracking-tight text-slate-900 dark:text-surface-100 sm:text-xl lg:text-2xl">
                            {{ comptesActifsCount }}
                        </p>
                        <p class="mt-1 truncate text-xs text-slate-500/70 dark:text-slate-400/70">{{ assurancesCount }} assurance(s) configurée(s)</p>
                    </div>
                    <div class="flex-shrink-0 rounded-lg bg-slate-500/10 p-2 dark:bg-slate-500/20">
                        <i class="pi pi-credit-card text-lg text-slate-500 sm:text-xl"></i>
                    </div>
                </div>
            </article>

            <article class="rounded-2xl border border-rose-200/70 bg-gradient-to-br from-rose-50/80 to-rose-100/50 p-5 shadow-md backdrop-blur-sm dark:border-rose-800/40 dark:from-rose-900/20 dark:to-rose-800/20">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-rose-700 dark:text-rose-300 sm:text-sm">Charges fixes</p>
                        <p class="mt-2 truncate text-lg font-bold tracking-tight text-rose-900 dark:text-rose-100 sm:text-xl lg:text-2xl">
                            {{ formatFcfa(fixedChargesTotal) }}
                        </p>
                        <p class="mt-1 truncate text-xs text-rose-600/70 dark:text-rose-400/70">{{ fixedCharges.length }} charge(s) configurée(s)</p>
                    </div>
                    <div class="flex-shrink-0 rounded-lg bg-rose-500/10 p-2 dark:bg-rose-500/20">
                        <i class="pi pi-building-columns text-lg text-rose-500 sm:text-xl"></i>
                    </div>
                </div>
            </article>
        </div>

        <Tabs :value="activeTab" @update:value="setActiveTab">
            <TabList data-tour="admin-finances.tabs">
                <Tab value="transactions">Transactions</Tab>
                <Tab value="payment-methods">Mode de paiement</Tab>
                <Tab value="insurances">Assurances</Tab>
                <Tab value="fixed-charges">Charges fixes</Tab>
                <Tab value="charts">Graphiques</Tab>
            </TabList>

            <TabPanels class="mt-4">
                <TabPanel value="transactions">
                    <div class="space-y-6">
                        <FinanceCrossTable
                            title="Tableau croisé Revenus / Dépenses"
                            subtitle="Synthèse hebdomadaire des transactions validées à partir de leur date de validation."
                            data-tour="admin-finances.cross-table" />

                        <section data-tour="admin-finances.transactions" class="overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
                            <div class="border-b border-surface-200/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 px-5 py-4 dark:border-surface-700/50 dark:from-surface-900/50 dark:to-surface-800/30 md:px-6">
                                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Historique des transactions</h2>
                                        <p class="text-sm text-surface-500 dark:text-surface-400">Filtre par période, type, recherche libre et statut en pied de tableau.</p>
                                    </div>

                                    <div class="grid w-full gap-3 md:grid-cols-2 xl:w-auto xl:grid-cols-[minmax(16rem,1fr)_auto_minmax(18rem,1fr)_auto]">
                                        <PanelDatePicker
                                            v-model="transactionRange"
                                            dateFormat="dd/mm/yy"
                                            showIcon
                                            class="w-full min-w-0"
                                            inputClass="w-full" />
                                        <SelectButton
                                            v-model="transactionTypeFilter"
                                            :options="transactionTypeOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full min-w-0" />
                                        <InputText
                                            v-model="transactionSearch"
                                            placeholder="Rechercher une transaction"
                                            class="w-full min-w-0" />
                                        <div class="flex gap-2">
                                            <Button icon="pi pi-print" severity="secondary" outlined @click="printTransactions" />
                                            <Button icon="pi pi-refresh" severity="secondary" outlined @click="loadTransactions" />
                                        </div>
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
                                        <div class="flex flex-col gap-1">
                                            <Tag :value="data.typeLabel" :severity="data.typeSeverity" />
                                            <small class="text-surface-500 dark:text-surface-400">{{ data.motif || 'Sans motif' }}</small>
                                        </div>
                                    </template>
                                </Column>
                                <Column field="amountValue" header="Montant" sortable>
                                    <template #body="{ data }">
                                        <span class="font-semibold" :class="data.typeKey === 'revenue' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                            {{ formatFcfa(data.amountValue) }}
                                        </span>
                                    </template>
                                </Column>
                                <Column field="modeLabel" header="Mode" sortable></Column>
                                <Column field="statusLabel" header="Statut" sortable>
                                    <template #body="{ data }">
                                        <Tag :value="data.statusLabel" :severity="data.statusSeverity" />
                                    </template>
                                    <template #footer>
                                        <Select
                                            v-model="transactionStatusFilter"
                                            :options="transactionStatusOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            placeholder="Tous les statuts"
                                            class="w-full min-w-0" />
                                    </template>
                                </Column>
                                <Column header="Actions" style="width: 220px">
                                    <template #body="{ data }">
                                        <div class="flex gap-1" data-tour="admin-finances.validation">
                                            <Button v-if="data.statusKey === 'pending'" icon="pi pi-check" text severity="success" title="Valider" @click="handleValidateTransaction(data)" />
                                            <Button v-if="data.statusKey === 'pending'" icon="pi pi-times" text severity="danger" title="Rejeter" @click="handleRejectTransaction(data)" />
                                            <Button icon="pi pi-trash" text severity="danger" title="Supprimer" @click="handleDeleteTransaction(data)" />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </section>
                    </div>
                </TabPanel>

                <TabPanel value="payment-methods">
                    <section data-tour="admin-finances.methods" class="overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
                        <div class="border-b border-surface-200/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 px-5 py-4 dark:border-surface-700/50 dark:from-surface-900/50 dark:to-surface-800/30 md:px-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Modes de paiement</h2>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Seuls les modes de paiement classiques sont gérés ici.</p>
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
                            sortField="libelle"
                            :sortOrder="1">

                            <Column field="libelle" header="Libellé" sortable>
                                <template #body="{ data }">
                                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ data.libelle }}</span>
                                </template>
                            </Column>
                            <Column field="typeLabel" header="Type" sortable>
                                <template #body="{ data }">
                                    <Tag :value="data.typeLabel" severity="secondary" />
                                </template>
                            </Column>
                            <Column field="notes" header="Notes">
                                <template #body="{ data }">
                                    <span class="text-sm text-surface-600 dark:text-surface-300">{{ data.notes || '-' }}</span>
                                </template>
                            </Column>
                            <Column field="statusLabel" header="Statut" sortable>
                                <template #body="{ data }">
                                    <Tag :value="data.statusLabel" :severity="data.actif ? 'success' : 'secondary'" />
                                </template>
                            </Column>
                            <Column header="Actions" style="width: 140px">
                                <template #body="{ data }">
                                    <div class="flex gap-1" data-tour="admin-finances.method-actions">
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
                </TabPanel>

                <TabPanel value="insurances">
                    <section class="overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80">
                        <div class="border-b border-surface-200/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 px-5 py-4 dark:border-surface-700/50 dark:from-surface-900/50 dark:to-surface-800/30 md:px-6">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Assurances</h2>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Assurances intégrées en dur avec activation/désactivation par cabinet.</p>
                                </div>

                                <InputText v-model="assuranceSearch" placeholder="Rechercher une assurance" class="w-full lg:w-72" />
                            </div>
                        </div>

                        <DataTable
                            :value="filteredAssurancesView"
                            dataKey="id"
                            :loading="loading.assurances"
                            paginator
                            :rows="8"
                            :rowsPerPageOptions="[8, 16, 24]"
                            responsiveLayout="scroll"
                            sortField="nom"
                            :sortOrder="1">
                            <Column header="Logo" style="width: 5.5rem">
                                <template #body="{ data }">
                                    <div class="assurance-table-logo">
                                        <img
                                            v-if="resolveAssuranceLogoUrl(data.logoPath)"
                                            :src="resolveAssuranceLogoUrl(data.logoPath)"
                                            :alt="data.nom"
                                            class="assurance-table-logo-img"
                                        />
                                        <i v-else class="pi pi-shield text-primary text-xl"></i>
                                    </div>
                                </template>
                            </Column>
                            <Column field="nom" header="Nom" sortable>
                                <template #body="{ data }">
                                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ data.nom || '-' }}</span>
                                </template>
                            </Column>
                            <Column field="code" header="Code" sortable>
                                <template #body="{ data }">
                                    <span class="text-sm text-surface-600 dark:text-surface-300">{{ data.code || '-' }}</span>
                                </template>
                            </Column>
                            <Column field="statusLabel" header="Statut" sortable>
                                <template #body="{ data }">
                                    <Tag :value="data.statusLabel" :severity="data.actif ? 'success' : 'secondary'" />
                                </template>
                            </Column>
                            <Column header="Actions" style="width: 11rem">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-1">
                                        <Button
                                            icon="pi pi-eye"
                                            text
                                            rounded
                                            severity="secondary"
                                            title="Voir les champs"
                                            @click="openAssuranceFieldsDialog(data)" />
                                        <Button
                                            icon="pi pi-pencil"
                                            text
                                            rounded
                                            severity="info"
                                            title="Modifier"
                                            @click="openAssuranceEditDialog(data)" />
                                        <Button
                                            :icon="data.actif ? 'pi pi-power-off' : 'pi pi-check'"
                                            text
                                            rounded
                                            :severity="data.actif ? 'warning' : 'success'"
                                            :title="data.actif ? 'Désactiver' : 'Activer'"
                                            @click="handleToggleAssurance(data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </section>
                </TabPanel>

                <TabPanel value="fixed-charges">
                    <FixedChargesTab
                        :items="fixedCharges"
                        :total="fixedChargesTotal"
                        :loading="loading.fixedCharges"
                        :action-loading="loading.action"
                        @create="handleCreateFixedCharge"
                        @update="handleUpdateFixedCharge"
                        @delete="handleDeleteFixedCharge"
                        @create-expense="handleCreateExpenseFromFixedCharge"
                        @create-global-expense="handleCreateGlobalExpenseFromFixedCharges" />
                </TabPanel>

                <TabPanel value="charts">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                            <section data-tour="admin-finances.monthly-flow" class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 xl:col-span-2 md:p-6">
                                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">Flux mensuel global</h2>
                                        <p class="text-sm text-surface-500 dark:text-surface-400">Revenus, dépenses et résultat net sur l'année sélectionnée.</p>
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
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Transactions de revenu regroupées par mode sur la période affichée.</p>
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
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Revenus, dépenses et solde courant par compte actif.</p>
                                </div>

                                <div class="h-80">
                                    <Chart type="bar" :data="accountFlowData" :options="accountFlowOptions" class="h-full w-full" />
                                </div>
                            </section>

                            <section data-tour="admin-finances.capital-share" class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 md:p-6">
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

                        <section data-tour="admin-finances.evolution" class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80 md:p-6">
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

        <TransactionFormDialog
            v-model:visible="transactionDialogVisible"
            :payment-methods="paymentMethodsView"
            :transaction-motifs="transactionMotifs"
            :transaction="draftTransaction"
            :loading="loading.action"
            tourTarget="admin-finances.dialog.transaction"
            @submit="handleTransactionSubmit" />

        <Dialog
            v-model:visible="validationDialogVisible"
            modal
            header="Confirmer la validation"
            :style="{ width: '420px' }">
            <div class="space-y-4">
                <p class="text-sm text-surface-600 dark:text-surface-300">
                    Choisissez la date de validation qui servira aux rapports et au tableau croisé.
                </p>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Date de validation</label>
                    <DatePicker v-model="transactionValidationDate" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>
            </div>

            <template #footer>
                <Button label="Annuler" text @click="closeValidationDialog" />
                <Button label="Valider" icon="pi pi-check" :loading="loading.action" @click="confirmTransactionValidation" />
            </template>
        </Dialog>

        <PaymentModeFormDialog
            v-model:visible="modeDialogVisible"
            :mode="editingMode"
            :loading="loading.action"
            tourTarget="admin-finances.dialog.mode"
            @submit="handleModeSubmit" />

        <Dialog
            v-model:visible="assuranceFieldsDialogVisible"
            modal
            :header="assuranceFieldsDialogTitle"
            :style="{ width: '560px' }">
            <div v-if="assuranceFieldsList.length" class="space-y-2">
                <div
                    v-for="field in assuranceFieldsList"
                    :key="field.key"
                    class="flex items-start justify-between gap-3 rounded-xl border border-surface-200/70 px-3 py-2.5 dark:border-surface-700/60">
                    <div class="min-w-0">
                        <p class="font-medium text-surface-900 dark:text-surface-100">{{ field.label }}</p>
                        <p class="text-xs text-surface-500 dark:text-surface-400">{{ field.key }} · {{ field.type }}</p>
                    </div>
                    <Tag :value="field.required ? 'Obligatoire' : 'Optionnel'" :severity="field.required ? 'warn' : 'secondary'" />
                </div>
            </div>
            <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun champ configuré pour cette assurance.</p>
        </Dialog>

        <Dialog
            v-model:visible="assuranceEditDialogVisible"
            modal
            header="Modifier l'assurance"
            :style="{ width: '480px' }">
            <div class="space-y-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Code</label>
                    <InputText :model-value="assuranceEditForm.code" disabled class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Nom</label>
                    <InputText v-model="assuranceEditForm.nom" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Site web</label>
                    <InputText v-model="assuranceEditForm.website" class="w-full" placeholder="https://..." />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-200">Email</label>
                    <InputText v-model="assuranceEditForm.email" class="w-full" placeholder="contact@..." />
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text @click="assuranceEditDialogVisible = false" />
                <Button label="Enregistrer" icon="pi pi-check" :loading="loading.action" @click="handleUpdateAssurance" />
            </template>
        </Dialog>

    </section>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { activateFinancesTourMock, deactivateFinancesTourMock, resetFinancesTourMockData } from '@/services/financesTourMock';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Chart from 'primevue/chart';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import SelectButton from 'primevue/selectbutton';
import FinanceCrossTable from '@/components/finances/FinanceCrossTable.vue';
import FixedChargesTab from '@/components/finances/FixedChargesTab.vue';
import PaymentModeFormDialog from '@/components/administration/finances/PaymentModeFormDialog.vue';
import TransactionFormDialog from '@/components/administration/finances/TransactionFormDialog.vue';
import { fetchGeneralSettings } from '@/services/globalSettingsService';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAdministrationFinancesTour, resolveAdministrationFinancesTourGroup } from '@/tours/administrationFinancesTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import { useFinances } from '@/composables/useFinances';
import {
    getPaymentMethodDefinition,
    normalizePaymentString,
    resolvePaymentMethodTypeKey,
    sortPaymentMethods
} from '@/utils/paymentMethodUtils';
import { resolveAssuranceLogoUrl } from '@/utils/assuranceUtils';

const toast = useToast();
const confirm = useConfirm();

const {
    chartData,
    fixedCharges,
    fixedChargesTotal,
    paymentMethods,
    assurances,
    transactions,
    loading,
    fetchChartData,
    fetchFixedCharges,
    fetchPaymentMethods,
    fetchAssurances,
    toggleAssurance,
    updateAssurance,
    fetchTransactionsRange,
    createFixedCharge,
    createTransaction,
    createPaymentMethod,
    updateFixedCharge,
    updatePaymentMethod,
    deleteFixedCharge,
    deletePaymentMethod,
    togglePaymentMethod,
    validateTransaction,
    rejectTransaction,
    deleteTransaction
} = useFinances();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Finances' }];

const activeTab = ref('transactions');
const transactionDialogVisible = ref(false);
const draftTransaction = ref(null);
const modeDialogVisible = ref(false);
const validationDialogVisible = ref(false);
const assuranceFieldsDialogVisible = ref(false);
const assuranceEditDialogVisible = ref(false);
const selectedAssuranceForFields = ref(null);
const assuranceEditForm = ref({
    code: '',
    nom: '',
    website: '',
    email: ''
});
const editingMode = ref(null);
const isGuidedTourStarting = ref(false);
const transactionValidationDate = ref(new Date());
const transactionToValidate = ref(null);
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const today = new Date();
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

const selectedYear = ref(today.getFullYear());
const transactionRange = ref([startOfMonth, today]);
const transactionSearch = ref('');
const transactionStatusFilter = ref('all');
const transactionTypeFilter = ref('all');
const modeSearch = ref('');
const assuranceSearch = ref('');
const transactionMotifs = ref({
    revenue: ['Paiement patient', 'Remboursement assurance', 'Vente produit', 'Autre'],
    expense: ['Charge fixe', 'Achat matériel', 'Frais généraux', 'Paiement salaire', 'Maintenance', 'Autre']
});

const setActiveTab = (value) => {
    activeTab.value = value || 'transactions';
};

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
});

const hasOpenDialogs = computed(() => (
    transactionDialogVisible.value
    || modeDialogVisible.value
    || validationDialogVisible.value
    || assuranceFieldsDialogVisible.value
    || assuranceEditDialogVisible.value
));

const transactionStatusOptions = [
    { label: 'Tous les statuts', value: 'all' },
    { label: 'En attente', value: 'pending' },
    { label: 'Validées', value: 'validated' },
    { label: 'Rejetées', value: 'rejected' }
];

const transactionTypeOptions = [
    { label: 'Tous', value: 'all' },
    { label: 'Revenus', value: 'revenue' },
    { label: 'Dépenses', value: 'expense' }
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

const formatLocalDateForApi = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
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

const resolveTransactionTypeKey = (row) => {
    const sourceType = row?.typeKey || row?.type || '';
    const normalizedType = normalizeText(sourceType);

    if (normalizedType.includes('revenu') || normalizedType.includes('entry') || normalizedType.includes('entree')) {
        return 'revenue';
    }

    if (normalizedType.includes('depense') || normalizedType.includes('expense') || normalizedType.includes('sortie') || normalizedType.includes('exit')) {
        return 'expense';
    }

    return 'other';
};

const transactionsView = computed(() =>
    (transactions.value || []).map((row) => {
        const typeKey = resolveTransactionTypeKey(row);
        const status = resolveTransactionStatus(row);
        const dateValue = row?.dateTransaction || row?.date;
        const mode = row?.modeDePaiement || {};
        const modeLabel = mode?.libelle || mode?.label || row?.mode || '--';
        const typeLabel = typeKey === 'expense' ? 'Dépense' : typeKey === 'revenue' ? 'Revenu' : (row?.typeLabel || row?.type || '--');

        return {
            ...row,
            dateLabel: formatDateTime(dateValue),
            amountValue: Number(row?.amount ?? row?.montant ?? 0),
            typeKey,
            typeLabel,
            typeSeverity: typeKey === 'revenue' ? 'success' : typeKey === 'expense' ? 'danger' : 'secondary',
            modeLabel,
            statusKey: status.key,
            statusLabel: status.label,
            statusSeverity: status.severity,
            searchBlob: normalizeText([
                formatDateTime(dateValue),
                row?.description,
                row?.motif,
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
        const matchesType = transactionTypeFilter.value === 'all' || row.typeKey === transactionTypeFilter.value;
        const matchesSearch = !searchQuery || row.searchBlob.includes(searchQuery);
        return matchesStatus && matchesType && matchesSearch;
    });
});

const { printComponent } = usePrinter();

const printTransactions = async () => {
    const rows = filteredTransactionsView.value.map((row) => ({
        dateLabel: row.dateLabel,
        description: row.description || 'Sans description',
        typeLabel: row.typeLabel,
        motif: row.motif || 'Sans motif',
        amountLabel: formatFcfa(row.amountValue),
        modeLabel: row.modeLabel || '—',
        statusLabel: row.statusLabel || '—'
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Historique des transactions',
        subtitle: `${rows.length} transaction(s)`,
        columns: [
            { key: 'dateLabel', label: 'Date' },
            { key: 'description', label: 'Description' },
            { key: 'typeLabel', label: 'Type' },
            { key: 'motif', label: 'Motif' },
            { key: 'amountLabel', label: 'Montant', align: 'right' },
            { key: 'modeLabel', label: 'Mode' },
            { key: 'statusLabel', label: 'Statut' }
        ],
        rows
    });
};

const paymentMethodsView = computed(() =>
    sortPaymentMethods(paymentMethods.value || []).map((mode) => {
        const definition = getPaymentMethodDefinition(mode);
        const normalizedLabel = normalizeText(mode?.libelle);
        const isLocked = resolvePaymentMethodTypeKey(mode) === 'cash' || normalizedLabel.includes('par defaut');

        return {
            ...mode,
            typeKey: resolvePaymentMethodTypeKey(mode),
            typeLabel: definition.label,
            statusLabel: mode?.actif ? 'Actif' : 'Inactif',
            isLocked,
            searchBlob: normalizeText([
                mode?.libelle,
                definition.label,
                mode?.notes
            ].join(' '))
        };
    })
);

const filteredPaymentMethodsView = computed(() => {
    const searchQuery = normalizeText(modeSearch.value);
    return paymentMethodsView.value.filter((row) => !searchQuery || row.searchBlob.includes(searchQuery));
});

const assurancesView = computed(() =>
    (assurances.value || []).map((item) => ({
        ...item,
        statusLabel: item?.actif ? 'Actif' : 'Inactif',
        searchBlob: normalizeText([
            item?.nom,
            item?.code,
            item?.website,
            item?.email,
            item?.actif ? 'actif' : 'inactif'
        ].join(' '))
    }))
);

const assuranceFieldsDialogTitle = computed(() => {
    const name = selectedAssuranceForFields.value?.nom || selectedAssuranceForFields.value?.code || 'Assurance';
    return `Champs — ${name}`;
});

const assuranceFieldsList = computed(() => {
    const fields = selectedAssuranceForFields.value?.formSchema?.fields;
    if (!Array.isArray(fields)) {
        return [];
    }

    return fields
        .filter((field) => field && typeof field === 'object')
        .map((field) => ({
            key: String(field.key || ''),
            label: String(field.label || field.key || 'Champ'),
            type: String(field.type || 'text'),
            required: Boolean(field.required)
        }));
});

const filteredAssurancesView = computed(() => {
    const searchQuery = normalizeText(assuranceSearch.value);
    return assurancesView.value.filter((row) => !searchQuery || row.searchBlob.includes(searchQuery));
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
const assurancesCount = computed(() => assurancesView.value.length);
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
                label: 'Revenus',
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
                label: 'Revenus',
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
    const rows = filteredTransactionsView.value.filter((row) => row.typeKey === 'revenue');
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
        startDate: formatLocalDateForApi(start),
        endDate: formatLocalDateForApi(end)
    });
};

const refreshAll = async () => {
    await Promise.all([fetchChartData(selectedYear.value), fetchPaymentMethods(), fetchAssurances(), fetchFixedCharges()]);
    await loadTransactions();
};

const loadTransactionMotifs = async () => {
    try {
        const token = localStorage.getItem('token');
        const settings = await fetchGeneralSettings(token);
        if (settings?.transactionMotifs) {
            const expense = Array.isArray(settings.transactionMotifs?.expense) ? [...settings.transactionMotifs.expense] : [];
            if (!expense.includes('Charge fixe')) {
                expense.unshift('Charge fixe');
            }
            transactionMotifs.value = {
                ...settings.transactionMotifs,
                expense
            };
        }
    } catch (error) {
        console.error('Erreur chargement motifs transaction', error);
    }
};

const openTransactionDialog = () => {
    draftTransaction.value = null;
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
    if (!payload?.modeId || !payload?.montant || !payload?.date || !payload?.motif) {
        toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Compte, montant, motif et date sont obligatoires.', life: 3000 });
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
                draftTransaction.value = null;
                await refreshAll();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Enregistrement impossible.', life: 3500 });
            }
        }
    });
};

const handleCreateFixedCharge = async (payload) => {
    try {
        await createFixedCharge(payload);
        toast.add({ severity: 'success', summary: 'Charges fixes', detail: 'Charge fixe enregistrée.', life: 3000 });
        await fetchFixedCharges();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Enregistrement impossible.', life: 3500 });
    }
};

const handleUpdateFixedCharge = async ({ id, payload }) => {
    try {
        await updateFixedCharge(id, payload);
        toast.add({ severity: 'success', summary: 'Charges fixes', detail: 'Charge fixe mise à jour.', life: 3000 });
        await fetchFixedCharges();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Mise à jour impossible.', life: 3500 });
    }
};

const handleDeleteFixedCharge = (charge) => {
    confirm.require({
        message: 'Supprimer cette charge fixe ? ',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await deleteFixedCharge(charge.id);
                toast.add({ severity: 'success', summary: 'Charges fixes', detail: 'Charge fixe supprimée.', life: 3000 });
                await fetchFixedCharges();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Suppression impossible.', life: 3500 });
            }
        }
    });
};

const handleCreateExpenseFromFixedCharge = (charge) => {
    draftTransaction.value = {
        typeKey: 'expense',
        type: 'expense',
        motif: 'Charge fixe',
        description: `Charge fixe | ${charge.designation}`,
        amount: Number(charge.montant || 0),
        date: new Date()
    };
    transactionDialogVisible.value = true;
};

const handleCreateGlobalExpenseFromFixedCharges = () => {
    draftTransaction.value = {
        typeKey: 'expense',
        type: 'expense',
        motif: 'Charge fixe',
        description: 'Charge fixe | Total global des charges fixes',
        amount: Number(fixedChargesTotal.value || 0),
        date: new Date()
    };
    transactionDialogVisible.value = true;
};

const handleModeSubmit = ({ payload, event }) => {
    if (!payload?.libelle) {
        toast.add({ severity: 'warn', summary: 'Libellé requis', detail: 'Veuillez saisir un libellé.', life: 3000 });
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

const handleToggleAssurance = (assurance) => {
    confirm.require({
        message: assurance?.actif ? 'Désactiver cette assurance ?' : 'Activer cette assurance ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await toggleAssurance(assurance?.code || '');
                toast.add({ severity: 'success', summary: 'Assurances', detail: 'Statut assurance mis à jour.', life: 3000 });
                await fetchAssurances();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Mise à jour impossible.', life: 3500 });
            }
        }
    });
};

const openAssuranceFieldsDialog = (assurance) => {
    selectedAssuranceForFields.value = assurance || null;
    assuranceFieldsDialogVisible.value = true;
};

const openAssuranceEditDialog = (assurance) => {
    assuranceEditForm.value = {
        code: assurance?.code || '',
        nom: assurance?.nom || '',
        website: assurance?.website || '',
        email: assurance?.email || ''
    };
    assuranceEditDialogVisible.value = true;
};

const handleUpdateAssurance = async () => {
    const code = String(assuranceEditForm.value.code || '').trim();
    const nom = String(assuranceEditForm.value.nom || '').trim();

    if (!code) {
        toast.add({ severity: 'warn', summary: 'Assurances', detail: 'Code assurance manquant.', life: 3000 });
        return;
    }

    if (!nom) {
        toast.add({ severity: 'warn', summary: 'Assurances', detail: 'Le nom est obligatoire.', life: 3000 });
        return;
    }

    try {
        await updateAssurance(code, {
            nom,
            website: String(assuranceEditForm.value.website || '').trim(),
            email: String(assuranceEditForm.value.email || '').trim()
        });
        toast.add({ severity: 'success', summary: 'Assurances', detail: 'Assurance mise à jour.', life: 3000 });
        assuranceEditDialogVisible.value = false;
        await fetchAssurances();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Mise à jour impossible.', life: 3500 });
    }
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
    transactionToValidate.value = row;
    transactionValidationDate.value = new Date();
    validationDialogVisible.value = true;
};

const closeValidationDialog = () => {
    validationDialogVisible.value = false;
    transactionToValidate.value = null;
};

const confirmTransactionValidation = async () => {
    if (!transactionToValidate.value?.id) {
        return;
    }

    try {
        await validateTransaction(transactionToValidate.value.id, {
            validatedAt: formatLocalDateForApi(transactionValidationDate.value)
        });
        toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction validée.', life: 3000 });
        closeValidationDialog();
        await loadTransactions();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Validation impossible.', life: 3500 });
    }
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

const handleDeleteTransaction = (row) => {
    confirm.require({
        message: 'Supprimer cette transaction et ses liens financiers associés ? ',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await deleteTransaction(row.id);
                toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction supprimée.', life: 3000 });
                await refreshAll();
            } catch (error) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: error?.message || 'Suppression impossible.', life: 3500 });
            }
        }
    });
};

const resetTourDialogs = () => {
    transactionDialogVisible.value = false;
    draftTransaction.value = null;
    modeDialogVisible.value = false;
    validationDialogVisible.value = false;
    assuranceFieldsDialogVisible.value = false;
    assuranceEditDialogVisible.value = false;
    selectedAssuranceForFields.value = null;
    editingMode.value = null;
    transactionToValidate.value = null;
};

const capturePageState = () => ({
    activeTab: activeTab.value,
    selectedYear: selectedYear.value,
    transactionRange: cloneValue(transactionRange.value),
    transactionSearch: transactionSearch.value,
    transactionStatusFilter: transactionStatusFilter.value,
    transactionTypeFilter: transactionTypeFilter.value,
    modeSearch: modeSearch.value,
    assuranceSearch: assuranceSearch.value,
    chartData: cloneValue(chartData.value),
    paymentMethods: cloneValue(paymentMethods.value),
    assurances: cloneValue(assurances.value),
    transactions: cloneValue(transactions.value)
});

const restorePageState = async (state) => {
    if (!state) return;
    setActiveTab(state.activeTab || 'transactions');
    selectedYear.value = state.selectedYear || today.getFullYear();
    transactionRange.value = cloneValue(state.transactionRange) || [startOfMonth, today];
    transactionSearch.value = state.transactionSearch || '';
    transactionStatusFilter.value = state.transactionStatusFilter || 'all';
    transactionTypeFilter.value = state.transactionTypeFilter || 'all';
    modeSearch.value = state.modeSearch || '';
    assuranceSearch.value = state.assuranceSearch || '';
    chartData.value = cloneValue(state.chartData) || chartData.value;
    paymentMethods.value = cloneValue(state.paymentMethods) || [];
    assurances.value = cloneValue(state.assurances) || [];
    transactions.value = cloneValue(state.transactions) || [];
    await nextTick();
};

const prepareGuidedTourDemo = async () => {
    guidedTourPageState = capturePageState();
    activateFinancesTourMock();
    resetFinancesTourMockData();
    guidedTourDemoActive = true;
    setActiveTab('transactions');
    selectedYear.value = 2026;
    transactionRange.value = [new Date('2026-04-01'), new Date('2026-04-03')];
    transactionSearch.value = '';
    transactionStatusFilter.value = 'all';
    transactionTypeFilter.value = 'all';
    modeSearch.value = '';
    assuranceSearch.value = '';
    await refreshAll();
    await nextTick();
};

const cleanupGuidedTourDemo = async () => {
    if (!guidedTourDemoActive) {
        resetTourDialogs();
        return;
    }

    if (guidedTourCleanupPromise) {
        return guidedTourCleanupPromise;
    }

    guidedTourCleanupPromise = (async () => {
        resetTourDialogs();
        deactivateFinancesTourMock();
        guidedTourDemoActive = false;
        const stateToRestore = guidedTourPageState;
        guidedTourPageState = null;
        await restorePageState(stateToRestore);
    })().finally(() => {
        guidedTourCleanupPromise = null;
    });

    return guidedTourCleanupPromise;
};

const switchTourTab = async (value) => {
    setActiveTab(value);
    resetTourDialogs();
    await nextTick();
    await waitForTourUi(220);
};

const openTourTransactionDialog = async () => {
    resetTourDialogs();
    setActiveTab('transactions');
    await nextTick();
    await waitForTourUi();
    transactionDialogVisible.value = true;
    await nextTick();
};

const openTourModeDialog = async () => {
    const firstMode = paymentMethodsView.value[0] || null;
    resetTourDialogs();
    setActiveTab('payment-methods');
    editingMode.value = firstMode;
    await nextTick();
    await waitForTourUi();
    modeDialogVisible.value = true;
    await nextTick();
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'administration-finances' || isGuidedTourStarting.value) {
        return;
    }

    if (loading.transactions || loading.methods || loading.charts || hasOpenDialogs.value) {
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
        await cleanupGuidedTourDemo();
        await prepareGuidedTourDemo();
        const steps = createAdministrationFinancesTour({
            switchTab: switchTourTab,
            openTransactionDialog: openTourTransactionDialog,
            openModeDialog: openTourModeDialog,
            closeAllDialogs: resetTourDialogs
        });
        await startTourGuide({
            group: resolveAdministrationFinancesTourGroup(activeTab.value),
            steps,
            onAfterExit: cleanupGuidedTourDemo,
            onFinish: cleanupGuidedTourDemo
        });
    } catch (error) {
        console.error('Erreur lancement guided tour finances', error);
        await cleanupGuidedTourDemo();
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
    await Promise.all([refreshAll(), loadTransactionMotifs()]);
    if (chartData.value?.year) {
        selectedYear.value = Number(chartData.value.year);
    }
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    deactivateFinancesTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});
</script>

<style scoped>
.assurance-table-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 4rem;
    height: 4rem;
    border-radius: 0.875rem;
    background: #fff;
    border: 1px solid var(--p-surface-200);
    padding: 0.375rem;
}

.assurance-table-logo-img {
    max-height: 3rem;
    max-width: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
}

:global(.app-dark) .assurance-table-logo {
    background: var(--p-surface-800);
    border-color: var(--p-surface-700);
}
</style>
