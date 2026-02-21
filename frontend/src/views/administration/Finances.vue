<template>
	<section
		class="min-h-screen bg-gradient-to-br from-surface-50 via-surface-50/80 to-surface-100/60 dark:from-surface-900 dark:via-surface-900/80 dark:to-surface-800/90 p-4 md:p-6 lg:p-8 transition-colors duration-300">
		<Toast />
		<ConfirmPopup />

		<!-- Header Section -->
		<div class="mb-6 md:mb-8">
			<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
				<div class="space-y-3">
					<div class="flex items-center gap-4">
						<div class="p-3 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-600 shadow-lg">
							<i class="pi pi-wallet text-white text-2xl"></i>
						</div>
						<div>
							<h1
								class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
								Tableau de bord financier
							</h1>
							<p class="text-surface-600 dark:text-surface-300 text-sm md:text-base mt-1">
								Suivi des transactions, modes de paiement et synthèse graphique en temps réel
							</p>
						</div>
					</div>
				</div>
				<div class="flex flex-wrap items-center gap-3">
					<Button label="Nouvelle transaction" icon="pi pi-plus"
						class="shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 border-0 text-white px-5 py-3 rounded-xl font-medium"
						@click="openTransactionDialog" />
					<Button label="Nouveau mode" icon="pi pi-credit-card" severity="secondary" outlined
						class="px-5 py-3 rounded-xl border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
						@click="openAddMode" /> 
				</div>
			</div>

			<div
				class="bg-surface-0/80 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/70 dark:border-surface-700/50 backdrop-blur-sm">
				<Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
			</div>
		</div>

		<!-- Stats Cards -->
		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6 md:mb-8">
			<div
				class="bg-gradient-to-br from-primary-50/80 to-primary-100/50 dark:from-primary-900/30 dark:to-primary-800/20 rounded-2xl p-5 border border-primary-200/70 dark:border-primary-800/40 shadow-md backdrop-blur-sm hover:shadow-lg transition-shadow duration-300">
				<div class="flex items-center justify-between">
					<div class="space-y-2">
						<div class="flex items-center gap-2">
							<i class="pi pi-chart-line text-primary-500"></i>
							<p class="text-sm font-medium text-primary-700 dark:text-primary-300">Capital total</p>
						</div>
						<p class="text-2xl lg:text-3xl font-bold text-primary-900 dark:text-primary-100 tracking-tight">
							{{ formatFcfa(capitalTotal) }}
						</p>
						<p class="text-xs text-primary-600/70 dark:text-primary-400/70">Tous comptes confondus</p>
					</div>
					<div class="p-2 rounded-lg bg-primary-500/10 dark:bg-primary-500/20">
						<i class="pi pi-database text-primary-500 text-xl"></i>
					</div>
				</div>
			</div>

			<div
				class="bg-gradient-to-br from-emerald-50/80 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-2xl p-5 border border-emerald-200/70 dark:border-emerald-800/40 shadow-md backdrop-blur-sm hover:shadow-lg transition-shadow duration-300">
				<div class="flex items-center justify-between">
					<div class="space-y-2">
						<div class="flex items-center gap-2">
							<i class="pi pi-check-circle text-emerald-500"></i>
							<p class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Comptes actifs</p>
						</div>
						<p class="text-2xl lg:text-3xl font-bold text-emerald-900 dark:text-emerald-100 tracking-tight">
							{{ comptesActifsCount }}
						</p>
						<p class="text-xs text-emerald-600/70 dark:text-emerald-400/70">Sur {{ paymentMethodsView.length
						}} modes</p>
					</div>
					<div class="p-2 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/20">
						<i class="pi pi-shield text-emerald-500 text-xl"></i>
					</div>
				</div>
			</div>

			<div
				class="bg-gradient-to-br from-amber-50/80 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/70 dark:border-amber-800/40 shadow-md backdrop-blur-sm hover:shadow-lg transition-shadow duration-300">
				<div class="flex items-center justify-between">
					<div class="space-y-2">
						<div class="flex items-center gap-2">
							<i class="pi pi-list text-amber-500"></i>
							<p class="text-sm font-medium text-amber-700 dark:text-amber-300">Transactions</p>
						</div>
						<p class="text-2xl lg:text-3xl font-bold text-amber-900 dark:text-amber-100 tracking-tight">
							{{ transactionsView.length }}
						</p>
						<p class="text-xs text-amber-600/70 dark:text-amber-400/70">Affichées sur la période</p>
					</div>
					<div class="p-2 rounded-lg bg-amber-500/10 dark:bg-amber-500/20">
						<i class="pi pi-chart-bar text-amber-500 text-xl"></i>
					</div>
				</div>
			</div>

			<div
				class="bg-gradient-to-br from-slate-50/80 to-slate-100/50 dark:from-slate-900/20 dark:to-slate-800/20 rounded-2xl p-5 border border-slate-200/70 dark:border-slate-800/40 shadow-md backdrop-blur-sm hover:shadow-lg transition-shadow duration-300">
				<div class="flex items-center justify-between">
					<div class="space-y-2">
						<div class="flex items-center gap-2">
							<i class="pi pi-calendar text-slate-500"></i>
							<p class="text-sm font-medium text-slate-600 dark:text-slate-300">Période</p>
						</div>
						<p class="text-lg font-semibold text-slate-900 dark:text-slate-100">
							{{ formatDate(transactionRange?.[0]) }} - {{ formatDate(transactionRange?.[1]) }}
						</p>
						<p class="text-xs text-slate-500/70 dark:text-slate-400/70">Dernière mise à jour : {{
							formatTime(new Date()) }}</p>
					</div>
					<div class="p-2 rounded-lg bg-slate-500/10 dark:bg-slate-500/20">
						<i class="pi pi-clock text-slate-500 text-xl"></i>
					</div>
				</div>
			</div>
		</div>

		<!-- Main Charts Row -->
		<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
			<!-- Monthly Flow Chart -->
			<div
				class="xl:col-span-2 bg-surface-0/80 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/70 dark:border-surface-700/50 p-5 md:p-6 backdrop-blur-sm">
				<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
					<div class="space-y-1">
						<div class="flex items-center gap-2">
							<div class="w-2 h-6 rounded-full bg-gradient-to-b from-primary-500 to-primary-600"></div>
							<h3 class="text-lg md:text-xl font-semibold text-surface-900 dark:text-surface-100">Flux
								mensuel global</h3>
						</div>
						<p class="text-sm text-surface-500 dark:text-surface-400">Entrées, dépenses et bénéfice net par
							mois</p>
					</div>
					<div class="flex items-center gap-2">
						<Select v-model="selectedPeriod" :options="periodOptions" optionLabel="label"
							optionValue="value" class="w-40 rounded-xl border-surface-200 dark:border-surface-700"
							placeholder="Période" />
						<Button icon="pi pi-refresh" text rounded severity="secondary"
							class="hover:bg-surface-100 dark:hover:bg-surface-700" @click="refreshAll" />
					</div>
				</div>
				<div class="h-72 md:h-80">
					<Chart type="bar" :data="monthlyFlowData" :options="monthlyFlowOptions" class="h-full w-full" />
				</div>
			</div>

			<!-- Capital Share Chart -->
			<div
				class="bg-surface-0/80 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/70 dark:border-surface-700/50 p-5 md:p-6 backdrop-blur-sm">
				<div class="flex items-center justify-between mb-6">
					<div class="space-y-1">
						<div class="flex items-center gap-2">
							<div class="w-2 h-6 rounded-full bg-gradient-to-b from-emerald-500 to-emerald-600"></div>
							<h3 class="text-lg md:text-xl font-semibold text-surface-900 dark:text-surface-100">
								Répartition du capital</h3>
						</div>
						<p class="text-sm text-surface-500 dark:text-surface-400">Distribution par compte de paiement
						</p>
					</div>
					<Button icon="pi pi-info-circle" text rounded severity="secondary"
						v-tooltip.top="'Pourcentage du capital total par compte'" />
				</div>
				<div class="h-72 md:h-80 relative">
					<Chart type="doughnut" :data="capitalShareData" :options="capitalShareOptions"
						class="h-full w-full" />
					<div class="absolute inset-0 flex items-center justify-center pointer-events-none">
						<div class="text-center">
							<p class="text-2xl font-bold text-surface-900 dark:text-surface-100">{{
								paymentMethodsView.length }}</p>
							<p class="text-sm text-surface-500 dark:text-surface-400">Comptes</p>
						</div>
					</div> 
				</div>
			</div>
		</div>

		<!-- Secondary Charts Row -->
		<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-6 mb-6 md:mb-8">
			<!-- Account Balance Chart -->
			<div
				class="bg-surface-0/80 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/70 dark:border-surface-700/50 p-5 md:p-6 backdrop-blur-sm">
				<div class="flex items-center justify-between mb-6">
					<div class="space-y-1">
						<div class="flex items-center gap-2">
							<div class="w-2 h-6 rounded-full bg-gradient-to-b from-blue-500 to-blue-600"></div>
							<h3 class="text-lg md:text-xl font-semibold text-surface-900 dark:text-surface-100">
								Solde par compte</h3>
						</div>
						<p class="text-sm text-surface-500 dark:text-surface-400">Entrées, sorties et solde courant
						</p>
					</div>
					<Button icon="pi pi-download" text rounded severity="secondary"
						v-tooltip.top="'Exporter les données'" />
				</div>
				<div class="h-72 md:h-80">
					<Chart type="bar" :data="accountFlowData" :options="accountFlowOptions" class="h-full w-full" />
				</div>
			</div>

			<!-- Capital Evolution Chart -->
			<div
				class="bg-surface-0/80 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/70 dark:border-surface-700/50 p-5 md:p-6 backdrop-blur-sm">
				<div class="flex items-center justify-between mb-6">
					<div class="space-y-1">
						<div class="flex items-center gap-2">
							<div class="w-2 h-6 rounded-full bg-gradient-to-b from-purple-500 to-purple-600"></div>
							<h3 class="text-lg md:text-xl font-semibold text-surface-900 dark:text-surface-100">
								Évolution du capital</h3>
						</div>
						<p class="text-sm text-surface-500 dark:text-surface-400">Croissance cumulée sur l'année</p>
					</div>
					<div class="flex items-center gap-2">
						<Button label="Année" text severity="secondary" size="small"
							:class="{ 'bg-surface-100 dark:bg-surface-700': true }" />
						<Button label="Trimestre" text severity="secondary" size="small" />
					</div>
				</div>
				<div class="h-72 md:h-80">
					<Chart type="line" :data="capitalEvolutionData" :options="capitalEvolutionOptions"
						class="h-full w-full" />
				</div>
			</div>
		</div>

		<!-- Tables Section -->
		<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6">
			<!-- Transactions Table -->
			<div
				class="lg:col-span-2 bg-surface-0/80 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/70 dark:border-surface-700/50 backdrop-blur-sm overflow-hidden">
				<div
					class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 dark:from-surface-900/50 dark:to-surface-800/30">
					<div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
						<div class="space-y-1">
							<h3 class="text-lg md:text-xl font-semibold text-surface-900 dark:text-surface-100">
								Historique des transactions</h3>
							<p class="text-sm text-surface-500 dark:text-surface-400">Filtrez par période et
								recherchez rapidement</p>
						</div>
						<div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
							<DatePicker v-model="transactionRange" selectionMode="range" dateFormat="dd/mm/yy" showIcon
								class="w-full sm:w-60 rounded-xl border-surface-200 dark:border-surface-700 [&_.p-inputtext]:p-3"
								inputClass="rounded-xl" />
							<div class="flex items-center gap-2">
								<span class="p-input-icon-left w-full sm:w-52">
									<i class="pi pi-search text-surface-400" />
									<InputText v-model="transactionSearch" placeholder="Rechercher..."
										class="w-full rounded-xl p-3 border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50" />
								</span>
								<Button icon="pi pi-refresh" text rounded severity="secondary"
									class="hover:bg-surface-100 dark:hover:bg-surface-700" @click="loadTransactions" />
							</div>
						</div>
					</div>
				</div>
				<DataTable :value="transactionsView" dataKey="id" :loading="loading.transactions"
					:filters="transactionFilters" paginator :rows="10" :rowsPerPageOptions="[5, 10, 20, 50]" stripedRows
					responsiveLayout="scroll" class="rounded-none border-0" :pt="{
						table: 'rounded-none',
						thead: 'bg-surface-50 dark:bg-surface-900/50',
						headerCell: ({ state }) => ({
							class: [
								'py-4 px-5 text-left font-semibold text-surface-700 dark:text-surface-300',
								'border-b border-surface-200 dark:border-surface-700',
								'bg-gradient-to-b from-surface-50 to-surface-100/50 dark:from-surface-900/50 dark:to-surface-800',
								state.sorted && 'bg-primary-50 dark:bg-primary-900/20'
							]
						}),
						bodyCell: {
							class: 'py-4 px-5 border-b border-surface-100 dark:border-surface-800'
						},
						row: {
							class: 'hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors'
						},
						paginator: {
							class: 'px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800'
						}
					}">
					<Column field="dateLabel" header="Date" sortable>
						<template #body="{ data }">
							<div class="flex items-center gap-2">
								<i class="pi pi-calendar text-surface-400"></i>
								<span class="font-medium text-surface-900 dark:text-surface-100">{{ data.dateLabel
								}}</span>
							</div>
						</template>
					</Column>
					<Column field="description" header="Description" sortable>
						<template #body="{ data }">
							<div class="max-w-xs truncate" :title="data.description">
								{{ data.description }}
							</div>
						</template>
					</Column>
					<Column field="typeLabel" header="Type" sortable>
						<template #body="{ data }">
							<Tag :value="data.typeLabel"
								:severity="data.typeKey === 'entry' ? 'success' : data.typeKey === 'exit' ? 'danger' : 'secondary'"
								class="px-3 py-1.5 rounded-full font-medium shadow-sm" />
						</template>
					</Column>
					<Column field="amountValue" header="Montant" sortable>
						<template #body="{ data }">
							<div class="flex items-center gap-2"
								:class="data.typeKey === 'entry' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
								<i
									:class="data.typeKey === 'entry' ? 'pi pi-arrow-down-right' : 'pi pi-arrow-up-right'"></i>
								<span class="font-bold">{{ formatFcfa(data.amountValue) }}</span>
							</div>
						</template>
					</Column>
					<Column field="modeLabel" header="Compte" sortable>
						<template #body="{ data }">
							<div class="flex items-center gap-2">
								<div class="w-2 h-2 rounded-full bg-primary-500"></div>
								<span class="text-surface-700 dark:text-surface-300">{{ data.modeLabel }}</span>
							</div>
						</template>
					</Column>
				</DataTable>
			</div>

			<!-- Payment Methods Table -->
			<div
				class="bg-surface-0/80 dark:bg-surface-800/80 rounded-2xl shadow-xl border border-surface-200/70 dark:border-surface-700/50 backdrop-blur-sm overflow-hidden">
				<div
					class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 dark:from-surface-900/50 dark:to-surface-800/30">
					<div class="space-y-3">
						<div class="flex items-center justify-between">
							<div class="space-y-1">
								<h3 class="text-lg md:text-xl font-semibold text-surface-900 dark:text-surface-100">
									Modes de
									paiement</h3>
								<p class="text-sm text-surface-500 dark:text-surface-400">Activez, désactivez ou
									supprimez un mode
								</p>
							</div>
							<Button icon="pi pi-plus" rounded severity="primary" class="shadow-sm"
								@click="openAddMode" />
						</div>
						<span class="p-input-icon-left w-full">
							<i class="pi pi-search text-surface-400" />
							<InputText v-model="modeSearch" placeholder="Rechercher un mode..."
								class="w-full rounded-xl p-3 border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50" />
						</span>
					</div>
				</div>
				<DataTable :value="paymentMethodsView" dataKey="id" :loading="loading.methods" :filters="modeFilters"
					paginator :rows="6" :rowsPerPageOptions="[6, 10, 20]" stripedRows responsiveLayout="scroll"
					class="rounded-none border-0" :pt="{
						table: 'rounded-none',
						thead: 'bg-surface-50 dark:bg-surface-900/50',
						headerCell: {
							class: 'py-3 px-5 text-left font-semibold text-surface-700 dark:text-surface-300 border-b border-surface-200 dark:border-surface-700'
						},
						bodyCell: {
							class: 'py-3 px-5 border-b border-surface-100 dark:border-surface-800'
						},
						row: {
							class: 'hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors'
						},
						paginator: {
							class: 'px-5 py-3 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800'
						}
					}">
					<Column field="libelle" header="Libellé" sortable>
						<template #body="{ data }">
							<div class="flex items-center gap-3">
								<div
									class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
									<i class="pi pi-credit-card text-white text-sm"></i>
								</div>
								<span class="font-medium text-surface-900 dark:text-surface-100">{{ data.libelle
								}}</span>
							</div>
						</template>
					</Column>
					<Column field="type" header="Type" sortable>
						<template #body="{ data }">
							<span
								class="px-3 py-1 rounded-full bg-surface-100 dark:bg-surface-700 text-surface-700 dark:text-surface-300 text-xs font-medium">
								{{ data.type || '--' }}
							</span>
						</template>
					</Column>
					<Column field="actif" header="Statut" sortable>
						<template #body="{ data }">
							<div class="flex items-center gap-2">
								<div
									:class="data.actif ? 'w-2 h-2 rounded-full bg-emerald-500 animate-pulse' : 'w-2 h-2 rounded-full bg-surface-400'">
								</div>
								<Tag :value="data.actif ? 'Actif' : 'Inactif'"
									:severity="data.actif ? 'success' : 'secondary'"
									class="px-3 py-1 rounded-full font-medium" />
							</div>
						</template>
					</Column>
					<Column header="Actions" style="width: 120px">
						<template #body="{ data }">
							<div class="flex items-center gap-1">
								<Button icon="pi pi-pencil" text size="small" severity="info" v-tooltip.top="'Modifier'"
									class="hover:bg-blue-50 dark:hover:bg-blue-900/20" @click="openEditMode(data)" />
								<Button :icon="data.actif ? 'pi pi-power-off' : 'pi pi-check'" text size="small"
									:severity="data.actif ? 'warning' : 'success'"
									v-tooltip.top="data.actif ? 'Désactiver' : 'Activer'"
									:class="data.actif ? 'hover:bg-amber-50 dark:hover:bg-amber-900/20' : 'hover:bg-emerald-50 dark:hover:bg-emerald-900/20'"
									@click="(event) => handleToggleMode({ event, mode: data })" />
								<Button icon="pi pi-trash" text size="small" severity="danger"
									v-tooltip.top="'Supprimer'" class="hover:bg-red-50 dark:hover:bg-red-900/20"
									@click="(event) => handleDeleteMode({ event, mode: data })" />
							</div>
						</template>
					</Column>
				</DataTable>
			</div>
		</div>

		<!-- Dialogs -->
		<TransactionFormDialog v-model:visible="transactionDialogVisible" :payment-methods="paymentMethodsView"
			:loading="loading.action" @submit="handleTransactionSubmit" />

		<PaymentModeFormDialog v-model:visible="modeDialogVisible" :mode="editingMode" :loading="loading.action"
			@submit="handleModeSubmit" />

		<Dialog v-model:visible="transferDialogVisible" modal :style="{ width: '560px' }" :pt="{
			root: 'border border-surface-200/70 dark:border-surface-700/50 rounded-2xl overflow-hidden backdrop-blur-sm',
			header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50',
			content: 'p-6',
			footer: 'px-6 py-4 border-t border-surface-200/50 dark:border-surface-700/50 flex justify-end gap-2'
		}">
			<template #header>
				<div class="flex items-center gap-3">
					<div class="p-2 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600">
						<i class="pi pi-arrow-right-arrow-left text-white"></i>
					</div>
					<span class="text-xl font-semibold text-surface-900 dark:text-surface-100">Transfert
						inter-compte</span>
				</div>
			</template>

			<div class="space-y-5">
				<div class="grid md:grid-cols-2 gap-4">
					<div class="space-y-2">
						<label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
							Compte source <span class="text-red-500">*</span>
						</label>
						<Select v-model="transferForm.fromId" :options="transferFromOptions" optionLabel="label"
							optionValue="value" placeholder="Sélectionner"
							class="w-full rounded-xl border-surface-200 dark:border-surface-700 [&_.p-dropdown]:p-3"
							:invalid="transferFormErrors.fromId" />
						<small v-if="transferFormErrors.fromId" class="text-red-500 text-xs">{{
							transferFormErrors.fromId
						}}</small>
					</div>
					<div class="space-y-2">
						<label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
							Compte destination <span class="text-red-500">*</span>
						</label>
						<Select v-model="transferForm.toId" :options="transferToOptions" optionLabel="label"
							optionValue="value" optionDisabled="disabled" placeholder="Sélectionner"
							class="w-full rounded-xl border-surface-200 dark:border-surface-700 [&_.p-dropdown]:p-3"
							:invalid="transferFormErrors.toId" />
						<small v-if="transferFormErrors.toId" class="text-red-500 text-xs">{{
							transferFormErrors.toId }}</small>
					</div>
				</div>

				<div class="grid md:grid-cols-2 gap-4">
					<div class="space-y-2">
						<label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
							Montant <span class="text-red-500">*</span>
						</label>
						<InputNumber v-model="transferForm.montant" mode="decimal" locale="fr-FR" :minFractionDigits="0"
							class="w-full rounded-xl border-surface-200 dark:border-surface-700 [&_.p-inputtext]:p-3"
							:invalid="transferFormErrors.montant" inputClass="w-full" />
						<small v-if="transferFormErrors.montant" class="text-red-500 text-xs">{{
							transferFormErrors.montant
						}}</small>
					</div>
					<div class="space-y-2">
						<label class="block text-sm font-medium text-surface-700 dark:text-surface-300">
							Date <span class="text-red-500">*</span>
						</label>
						<DatePicker v-model="transferForm.date" dateFormat="dd/mm/yy" showIcon
							class="w-full rounded-xl border-surface-200 dark:border-surface-700 [&_.p-inputtext]:p-3"
							:invalid="transferFormErrors.date" inputClass="w-full" />
						<small v-if="transferFormErrors.date" class="text-red-500 text-xs">{{
							transferFormErrors.date }}</small>
					</div>
				</div>

				<div class="space-y-2">
					<label class="block text-sm font-medium text-surface-700 dark:text-surface-300">Motif</label>
					<Textarea v-model="transferForm.motif" rows="3" autoResize
						class="w-full rounded-xl border-surface-200 dark:border-surface-700 p-3"
						placeholder="Décrivez le motif du transfert..." />
				</div>
			</div>

			<template #footer>
				<Button label="Annuler" text severity="secondary" class="hover:bg-surface-100 dark:hover:bg-surface-700"
					@click="transferDialogVisible = false" />
				<Button label="Transférer" icon="pi pi-check" :loading="loading.action"
					class="bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
					@click="handleTransfer" />
			</template>
		</Dialog>
	</section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Chart from 'primevue/chart';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { FilterMatchMode } from '@primevue/core/api';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import TransactionFormDialog from '@/components/administration/finances/TransactionFormDialog.vue';
import PaymentModeFormDialog from '@/components/administration/finances/PaymentModeFormDialog.vue';
import { useFinances } from '@/composables/useFinances';

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
	transferInterCompte
} = useFinances();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Finances' }];

const transactionDialogVisible = ref(false);
const modeDialogVisible = ref(false);
const transferDialogVisible = ref(false);
const editingMode = ref(null);

const transactionRange = ref([]);
const today = new Date();
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
transactionRange.value = [startOfMonth, today];

const periodOptions = [
	{ label: '3 mois', value: '3m' },
	{ label: '6 mois', value: '6m' },
	{ label: '12 mois', value: '12m' }
];
const selectedPeriod = ref('12m');

const transferForm = ref({
	fromId: null,
	toId: null,
	montant: null,
	motif: '',
	date: new Date()
});

const transactionFilters = ref({
	global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const modeFilters = ref({
	global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const transactionSearch = ref('');
const modeSearch = ref('');

watch(transactionSearch, (value) => {
	transactionFilters.value.global.value = value;
});

watch(modeSearch, (value) => {
	modeFilters.value.global.value = value;
});

const normalizeLabel = (value) =>
	String(value || '')
		.toLowerCase()
		.normalize('NFD')
		.replace(/\p{Diacritic}/gu, '');

const formatFcfa = (value) =>
	new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(Number(value || 0));

const formatDate = (value) => {
	if (!value) return '--';
	const date = new Date(value);
	if (Number.isNaN(date.getTime())) return String(value);
	return date.toLocaleDateString('fr-FR');
};

const transactionsView = computed(() =>
	(transactions.value || []).map((row) => {
		const typeLabel = row?.type || '--';
		const typeNormalized = normalizeLabel(typeLabel);
		const typeKey = typeNormalized.includes('entree') ? 'entry' : typeNormalized.includes('sortie') ? 'exit' : '';
		return {
			...row,
			dateLabel: formatDate(row?.date || row?.dateTransaction),
			amountValue: Number(row?.amount ?? row?.montant ?? 0),
			modeLabel: row?.modeDePaiement?.libelle || row?.modeDePaiement?.label || '--',
			typeKey,
			typeLabel
		};
	})
);

const paymentMethodsView = computed(() =>
	(paymentMethods.value || []).map((mode) => {
		const normalized = normalizeLabel(mode?.libelle);
		const isLocked = normalized.includes('especes');
		return {
			...mode,
			isLocked
		};
	})
);

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

const capitalTotal = computed(() =>
	soldesParCompte.value.reduce((sum, item) => sum + Number(item.solde || 0), 0)
);

const comptesActifsCount = computed(() => paymentMethodsView.value.filter((m) => m.actif).length);

const monthlyFlowData = computed(() => {
	const months = chartData.value?.months?.length
		? chartData.value.months
		: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];
	const revenues = Array(months.length).fill(0);
	const expenses = Array(months.length).fill(0);

	(chartData.value?.datasetsComptes || []).forEach((dataset) => {
		const label = normalizeLabel(dataset?.label);
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
				label: 'Entrees',
				data: revenues,
				backgroundColor: documentStyle.getPropertyValue('--p-emerald-500') || '#10b981',
				borderRadius: 6
			},
			{
				type: 'bar',
				label: 'Depenses',
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

const monthlyFlowOptions = computed(() => {
	const documentStyle = getComputedStyle(document.documentElement);
	const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
	const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
	return {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } },
			tooltip: {
				callbacks: {
					label: (ctx) => `${ctx.dataset.label}: ${formatFcfa(ctx.parsed.y)}`
				}
			}
		},
		scales: {
			x: { ticks: { color: textColorSecondary }, grid: { display: false } },
			y: {
				ticks: { color: textColorSecondary, callback: (value) => formatFcfa(value) },
				grid: { color: surfaceBorder }
			}
		}
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
				label: 'Entrees',
				data: chart.entrees || [],
				backgroundColor: documentStyle.getPropertyValue('--p-emerald-500') || '#10b981',
				borderRadius: 6
			},
			{
				type: 'bar',
				label: 'Depenses',
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

const accountFlowOptions = computed(() => {
	const documentStyle = getComputedStyle(document.documentElement);
	const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
	const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
	return {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } },
			tooltip: {
				callbacks: {
					label: (ctx) => `${ctx.dataset.label}: ${formatFcfa(ctx.parsed.y)}`
				}
			}
		},
		scales: {
			x: { ticks: { color: textColorSecondary }, grid: { display: false } },
			y: {
				ticks: { color: textColorSecondary, callback: (value) => formatFcfa(value) },
				grid: { color: surfaceBorder }
			}
		}
	};
});

const capitalEvolutionData = computed(() => {
	const months = chartData.value?.months?.length
		? chartData.value.months
		: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];
	const documentStyle = getComputedStyle(document.documentElement);
	return {
		labels: months,
		datasets: [
			{
				label: 'Capital cumule',
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

const capitalEvolutionOptions = computed(() => {
	const documentStyle = getComputedStyle(document.documentElement);
	const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
	const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
	return {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: { display: false },
			tooltip: {
				callbacks: {
					label: (ctx) => `${ctx.dataset.label}: ${formatFcfa(ctx.parsed.y)}`
				}
			}
		},
		scales: {
			x: { ticks: { color: textColorSecondary }, grid: { display: false } },
			y: {
				ticks: { color: textColorSecondary, callback: (value) => formatFcfa(value) },
				grid: { color: surfaceBorder }
			}
		}
	};
});

const capitalShareData = computed(() => {
	const documentStyle = getComputedStyle(document.documentElement);
	const labels = soldesParCompte.value.map((item) => item.label);
	const values = soldesParCompte.value.map((item) => Math.max(Number(item.solde || 0), 0));
	const colors = soldesParCompte.value.map(
		(item, index) => item.color || [
			documentStyle.getPropertyValue('--p-primary-500') || '#3b82f6',
			documentStyle.getPropertyValue('--p-emerald-500') || '#10b981',
			documentStyle.getPropertyValue('--p-amber-500') || '#f59e0b',
			documentStyle.getPropertyValue('--p-rose-500') || '#f43f5e',
			documentStyle.getPropertyValue('--p-cyan-500') || '#06b6d4',
			documentStyle.getPropertyValue('--p-violet-500') || '#8b5cf6'
		][index % 6]
	);

	return {
		labels,
		datasets: [
			{
				data: values,
				backgroundColor: colors,
				borderWidth: 0
			}
		]
	};
});

const capitalShareOptions = computed(() => {
	const documentStyle = getComputedStyle(document.documentElement);
	return {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } },
			tooltip: {
				callbacks: {
					label: (ctx) => `${ctx.label}: ${formatFcfa(ctx.parsed)}`
				}
			}
		}
	};
});

const loadTransactions = async () => {
	const [start, end] = transactionRange.value || [];
	if (!start || !end) return;
	await fetchTransactionsRange({
		startDate: new Date(start).toISOString().slice(0, 10),
		endDate: new Date(end).toISOString().slice(0, 10)
	});
};

const refreshAll = async () => {
	await Promise.all([fetchChartData(), fetchPaymentMethods()]);
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

const openTransferDialog = () => {
	transferForm.value = { fromId: null, toId: null, montant: null, motif: '', date: new Date() };
	transferDialogVisible.value = true;
};

const handleTransactionSubmit = ({ payload, event }) => {
	if (!payload?.modeId || !payload?.montant || !payload?.date) {
		toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Compte, montant et date sont obligatoires.', life: 3000 });
		return;
	}

	confirm.require({
		target: event?.currentTarget,
		message: 'Confirmer la creation de cette transaction ?',
		icon: 'pi pi-check',
		acceptLabel: 'Confirmer',
		rejectLabel: 'Annuler',
		accept: async () => {
			try {
				await createTransaction(payload);
				toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction enregistree.', life: 3000 });
				transactionDialogVisible.value = false;
				await refreshAll();
			} catch (err) {
				toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Enregistrement impossible.', life: 3500 });
			}
		}
	});
};

const handleModeSubmit = ({ payload, event }) => {
	if (!payload?.libelle) {
		toast.add({ severity: 'warn', summary: 'Libelle requis', detail: 'Veuillez saisir un libelle.', life: 3000 });
		return;
	}

	const isEdit = Boolean(editingMode.value?.id);
	confirm.require({
		target: event?.currentTarget,
		message: isEdit ? 'Confirmer la mise a jour du mode ?' : 'Confirmer la creation du mode ?',
		icon: 'pi pi-check',
		acceptLabel: 'Confirmer',
		rejectLabel: 'Annuler',
		accept: async () => {
			try {
				if (isEdit) {
					await updatePaymentMethod(editingMode.value.id, payload);
					toast.add({ severity: 'success', summary: 'Mode de paiement', detail: 'Mode mis a jour.', life: 3000 });
				} else {
					await createPaymentMethod(payload);
					toast.add({ severity: 'success', summary: 'Mode de paiement', detail: 'Mode cree.', life: 3000 });
				}
				modeDialogVisible.value = false;
				await refreshAll();
			} catch (err) {
				toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Action impossible.', life: 3500 });
			}
		}
	});
};

const handleDeleteMode = ({ event, mode }) => {
	if (mode?.isLocked) {
		toast.add({ severity: 'warn', summary: 'Mode protege', detail: 'Ce mode ne peut pas etre supprime.', life: 3000 });
		return;
	}
	confirm.require({
		target: event?.currentTarget,
		message: 'Supprimer ce mode de paiement ?',
		icon: 'pi pi-exclamation-triangle',
		acceptClass: 'p-button-danger',
		acceptLabel: 'Supprimer',
		rejectLabel: 'Annuler',
		accept: async () => {
			try {
				await deletePaymentMethod(mode.id);
				toast.add({ severity: 'success', summary: 'Suppression', detail: 'Mode supprime.', life: 3000 });
				await refreshAll();
			} catch (err) {
				toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Suppression impossible.', life: 3500 });
			}
		}
	});
};

const handleToggleMode = ({ event, mode }) => {
	if (mode?.isLocked) {
		toast.add({ severity: 'warn', summary: 'Mode protege', detail: 'Ce mode ne peut pas etre desactive.', life: 3000 });
		return;
	}
	confirm.require({
		target: event?.currentTarget,
		message: mode?.actif ? 'Desactiver ce mode de paiement ?' : 'Activer ce mode de paiement ?',
		icon: 'pi pi-exclamation-triangle',
		acceptLabel: 'Confirmer',
		rejectLabel: 'Annuler',
		accept: async () => {
			try {
				await togglePaymentMethod(mode.id);
				toast.add({ severity: 'success', summary: 'Statut mis a jour', detail: 'Le mode a ete mis a jour.', life: 3000 });
				await refreshAll();
			} catch (err) {
				toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Mise a jour impossible.', life: 3500 });
			}
		}
	});
};

const handleTransfer = (event) => {
	const payload = { ...transferForm.value };
	if (!payload.fromId || !payload.toId || !payload.montant || !payload.date) {
		toast.add({ severity: 'warn', summary: 'Champs requis', detail: 'Source, destination, montant et date sont requis.', life: 3000 });
		return;
	}
	if (payload.fromId === payload.toId) {
		toast.add({ severity: 'warn', summary: 'Comptes identiques', detail: 'Selectionnez deux comptes differents.', life: 3000 });
		return;
	}

	confirm.require({
		target: event?.currentTarget,
		message: 'Confirmer ce transfert inter-compte ?',
		icon: 'pi pi-exclamation-triangle',
		acceptLabel: 'Confirmer',
		rejectLabel: 'Annuler',
		accept: async () => {
			try {
				await transferInterCompte({
					...payload,
					date: new Date(payload.date).toISOString().slice(0, 10)
				});
				toast.add({ severity: 'success', summary: 'Transfert', detail: 'Transfert enregistre.', life: 3000 });
				transferDialogVisible.value = false;
				await refreshAll();
			} catch (err) {
				toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Transfert impossible.', life: 3500 });
			}
		}
	});
};

const transferFromOptions = computed(() =>
	paymentMethodsView.value
		.filter((mode) => mode.actif)
		.map((mode) => ({
			label: mode.type ? `${mode.libelle} (${mode.type})` : mode.libelle,
			value: mode.id
		}))
);

const transferToOptions = computed(() =>
	paymentMethodsView.value
		.filter((mode) => mode.actif)
		.map((mode) => ({
			label: mode.type ? `${mode.libelle} (${mode.type})` : mode.libelle,
			value: mode.id,
			disabled: transferForm.value.fromId === mode.id
		}))
);

watch(transactionRange, () => {
	loadTransactions();
});

onMounted(async () => {
	await refreshAll();
});

function formatTime(date) {
			if (!date) return '--:--';
			return date.toLocaleTimeString('fr-FR', {
				hour: '2-digit',
				minute: '2-digit',
				hour12: false
			});
		}
</script>