<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { activateAdminTourMock, deactivateAdminTourMock, resetAdminTourMockData } from '@/services/adminTourMock';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import EmployeeForm from '@/components/administration/EmployeeForm.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import { useEmployees } from '@/composables/useEmployees';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAdministrationGestionRHTour } from '@/tours/administrationGestionRHTour';
import { startTourGuide } from '@/tours/tourGuideClient';

const toast = useToast();
const confirm = useConfirm();
const { printComponent } = usePrinter();
const router = useRouter();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [
    { label: 'Administration' },
    { label: 'Gestion RH' }
];

const typeOptions = [
    { label: 'Tous les types', value: null },
    { label: 'Médecin', value: 'Medecin' },
    { label: 'Réceptionniste', value: 'Receptionniste' },
    { label: 'Admin', value: 'Admin' },
    { label: 'Autre', value: 'Autre' }
];

const { employees, totalRecords, loading, error, fetchEmployees, addEmployee, updateEmployee, deleteEmployee } =
    useEmployees();

const search = ref('');
const typeFilter = ref(null);
const tableState = ref({ page: 0, rows: 10 });
const expandedGroups = ref([]);

const formVisible = ref(false);
const formMode = ref('create');
const currentEmployee = ref(null);
const isGuidedTourStarting = ref(false);
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const groupedCounts = computed(() => {
    const map = new Map();
    (employees.value || []).forEach((emp) => {
        const key = emp?.type || 'Non défini';
        map.set(key, (map.get(key) || 0) + 1);
    });
    return map;
});

const allGroupKeys = computed(() => Array.from(new Set((employees.value || []).map((emp) => emp?.type || 'Non défini'))));

const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('fr-FR');
};

const loadEmployees = async ({ page = tableState.value.page, rows = tableState.value.rows } = {}) => {
    tableState.value.page = page;
    tableState.value.rows = rows;
    await fetchEmployees({ page, rows, search: search.value, type: typeFilter.value });
    if (error.value) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error.value, life: 4000 });
    }
};

const printEmployees = async () => {
    const rows = (employees.value || []).map((emp) => ({
        nom: emp?.nom || '—',
        prenom: emp?.prenom || '—',
        type: emp?.type || '—',
        telephone: emp?.telephone || '—',
        dateEmbauche: formatDate(emp?.dateEmbauche)
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Liste des employés',
        subtitle: `${totalRecords.value || rows.length} employé(s)`,
        columns: [
            { key: 'nom', label: 'Nom' },
            { key: 'prenom', label: 'Prénom' },
            { key: 'type', label: 'Type' },
            { key: 'telephone', label: 'Téléphone' },
            { key: 'dateEmbauche', label: "Date d'embauche" }
        ],
        rows
    });
};

const onPage = (event) => {
    loadEmployees({ page: event.page, rows: event.rows });
};

const openCreate = () => {
    formMode.value = 'create';
    currentEmployee.value = null;
    formVisible.value = true;
};

const openEdit = (employee) => {
    formMode.value = 'edit';
    currentEmployee.value = employee;
    formVisible.value = true;
};

const hasOpenDialogs = computed(() => formVisible.value);
const firstEmployee = computed(() => (employees.value || [])[0] || null);

const resetTourDialogs = () => {
    formVisible.value = false;
    formMode.value = 'create';
    currentEmployee.value = null;
};

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
});

const expandAllGroupsForTour = () => {
    expandedGroups.value = [...allGroupKeys.value];
};

const capturePageState = () => ({
    search: search.value,
    typeFilter: typeFilter.value,
    tableState: cloneValue(tableState.value),
    expandedGroups: cloneValue(expandedGroups.value),
    employees: cloneValue(employees.value),
    totalRecords: totalRecords.value
});

const restorePageState = async (state) => {
    if (!state) return;
    search.value = state.search || '';
    typeFilter.value = state.typeFilter ?? null;
    tableState.value = cloneValue(state.tableState) || { page: 0, rows: 10 };
    expandedGroups.value = cloneValue(state.expandedGroups) || [];
    employees.value = cloneValue(state.employees) || [];
    totalRecords.value = state.totalRecords ?? employees.value.length;
    await nextTick();
};

const prepareGuidedTourDemo = async () => {
    guidedTourPageState = capturePageState();
    activateAdminTourMock();
    resetAdminTourMockData();
    guidedTourDemoActive = true;
    search.value = '';
    typeFilter.value = null;
    tableState.value = { page: 0, rows: 10 };
    await loadEmployees({ page: 0, rows: 10 });
    expandAllGroupsForTour();
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
        deactivateAdminTourMock();
        guidedTourDemoActive = false;
        const stateToRestore = guidedTourPageState;
        guidedTourPageState = null;
        await restorePageState(stateToRestore);
    })().finally(() => {
        guidedTourCleanupPromise = null;
    });

    return guidedTourCleanupPromise;
};

const openTourCreateDialog = async () => {
    resetTourDialogs();
    expandAllGroupsForTour();
    await nextTick();
    await waitForTourUi();
    openCreate();
    await nextTick();
};

const openTourEditDialog = async () => {
    if (!firstEmployee.value) return;
    resetTourDialogs();
    expandAllGroupsForTour();
    await nextTick();
    await waitForTourUi();
    openEdit(firstEmployee.value);
    await nextTick();
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'administration-gestionrh' || isGuidedTourStarting.value) {
        return;
    }

    if (loading.value || hasOpenDialogs.value) {
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
        resetTourDialogs();
        expandAllGroupsForTour();
        await nextTick();

        const steps = createAdministrationGestionRHTour({
            hasEmployees: (employees.value || []).length > 0,
            openCreateDialog: openTourCreateDialog,
            openEditDialog: openTourEditDialog,
            expandGroups: async () => {
                expandAllGroupsForTour();
                await nextTick();
                await waitForTourUi();
            },
            closeAllDialogs: resetTourDialogs
        });

        await startTourGuide({
            group: 'administration-gestionrh',
            steps,
            onAfterExit: cleanupGuidedTourDemo,
            onFinish: cleanupGuidedTourDemo
        });
    } catch (error) {
        console.error('Erreur lancement guided tour gestion rh', error);
        await cleanupGuidedTourDemo();
        toast.add({
            severity: 'error',
            summary: 'Aide guidee',
            detail: 'Impossible de lancer le tour de la page gestion RH.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

const confirmSave = (payload, event) => {
    confirm.require({
        target: event?.currentTarget,
        message: formMode.value === 'edit' ? 'Confirmer la mise à jour ?' : 'Confirmer la création ?',
        icon: 'pi pi-check',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                if (payload.mode === 'edit' && payload.id) {
                    await updateEmployee(payload.id, payload.formData);
                    toast.add({ severity: 'success', summary: 'Succès', detail: 'Employé mis à jour.', life: 3000 });
                } else {
                    await addEmployee(payload.formData);
                    toast.add({ severity: 'success', summary: 'Succès', detail: 'Employé créé.', life: 3000 });
                }
                formVisible.value = false;
                loadEmployees({ page: 0, rows: tableState.value.rows });
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Échec de sauvegarde.', life: 4000 });
            }
        }
    });
};

const confirmDelete = (event, employee) => {
    confirm.require({
        target: event?.currentTarget,
        message: `Supprimer ${employee?.prenom || ''} ${employee?.nom || ''} ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await deleteEmployee(employee.id);
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Employé supprimé.', life: 3000 });
                loadEmployees({ page: 0, rows: tableState.value.rows });
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Suppression impossible.', life: 4000 });
            }
        }
    });
};

const detailsUrl = (employee) =>
    router.resolve({ name: 'administration-employee-details', params: { id: employee.id } }).href;
const openDetails = (employee) => {
    window.open(detailsUrl(employee), '_blank');
};

let searchTimer = null;
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadEmployees({ page: 0, rows: tableState.value.rows }), 350);
});

watch(typeFilter, () => loadEmployees({ page: 0, rows: tableState.value.rows }));

onMounted(() => {
    loadEmployees();
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    deactivateAdminTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});
</script>
<template>
    <section
        class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <Toast />
        <ConfirmPopup />

        <!-- Header Section -->
        <div data-tour="admin-rh.header" class="mb-6 md:mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="pi pi-users text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                            Gestion RH
                        </h1>
                    </div>
                    <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base">
                        Gérez vos collaborateurs et leur informations
                    </p>
                </div>
                <Button 
                    icon="pi pi-plus" 
                    label="Nouvel employé" 
                    class="shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-6 py-3 rounded-xl font-medium"
                    @click="openCreate" 
                />
            </div>
            
            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
        </div>

        <!-- Filters Card -->
        <div data-tour="admin-rh.filters" class="mb-6 md:mb-8">
            <div class="card p-5 md:p-6 border-0 rounded-2xl bg-gradient-to-r from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 shadow-lg backdrop-blur-sm">
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 mb-4 flex items-center gap-2">
                    <i class="pi pi-filter text-primary-500"></i>
                    Filtres de recherche
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-5 lg:col-span-6">

                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                            Rechercher un employé
                        </label>
                        <IconField class="p-input-icon-left w-full">
                            <InputIcon class="pi pi-search text-surface-400" />
                            <InputText 
                                v-model="search" 
                                placeholder="Nom, prénom, téléphone..." 
                                class="w-full p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                            />
                        </IconField>
                    </div>
                    
                    <div class="md:col-span-4 lg:col-span-3">
                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                            Type d'employé
                        </label>
                        <Select 
                            v-model="typeFilter" 
                            :options="typeOptions" 
                            optionLabel="label" 
                            optionValue="value"
                            class="w-full rounded-xl border-surface-200 dark:border-surface-700 [&_.p-dropdown]:p-3.5"
                            placeholder="Sélectionnez un type"
                        />
                    </div>
                    
                    <div class="md:col-span-3 lg:col-span-3 flex justify-end">
                        <Button 
                            icon="pi pi-refresh" 
                            severity="secondary" 
                            outlined
                            label="Rafraîchir"
                            class="rounded-xl px-5 py-3.5 border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                            @click="loadEmployees({ page: 0, rows: tableState.rows })" 
                        />
                    </div>
                </div>
                
                <!-- Active filters indicator -->
                <div v-if="search || typeFilter" class="mt-4 pt-4 border-t border-surface-200/50 dark:border-surface-700/50">
                    <div class="flex items-center gap-2 text-sm">
                        <i class="pi pi-info-circle text-primary-500"></i>
                        <span class="text-surface-600 dark:text-surface-400">
                            Filtres actifs : 
                            <span class="font-medium text-surface-900 dark:text-surface-200">
                                {{ search ? `"${search}"` : '' }}
                                {{ search && typeFilter ? ' • ' : '' }}
                                {{ typeFilter ? typeOptions.find(o => o.value === typeFilter)?.label : '' }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table Card -->
        <div data-tour="admin-rh.table" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
            <!-- Table Header -->
            <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            Liste des employés
                        </h3>
                        <p class="text-sm text-surface-600 dark:text-surface-400">
                            {{ totalRecords }} employé(s) au total
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            icon="pi pi-download" 
                            severity="secondary" 
                            text 
                            size="small"
                            label="Exporter"
                            class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400"
                            @click="printEmployees"
                        />
                        <Button 
                            icon="pi pi-cog" 
                            severity="secondary" 
                            text 
                            size="small"
                            class="text-surface-600 dark:text-surface-400"
                        />
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <DataTable 
                :value="employees" 
                :loading="loading" 
                dataKey="id"  
                :rows="tableState.rows"
                :first="tableState.page * tableState.rows" 
                :totalRecords="totalRecords" 
                :lazy="true"
                rowGroupMode="subheader" 
                groupRowsBy="type" 
                :expandableRowGroups="true"
                v-model:expandedRowGroups="expandedGroups" 
                @page="onPage"
                class="rounded-none border-0"
                :pt="{
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
                }"
            >
                <!-- Group Header -->
                <template #groupheader="{ data }">
                    <div class="flex items-center justify-between ">
                        <div class="flex items-center gap-3">
                            <i class="pi pi-folder text-primary-500"></i>
                            <span class="font-semibold text-surface-900 dark:text-surface-100">
                                {{ data?.type || 'Non classé' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-surface-700 rounded-full">
                                <span class="w-2 h-2 rounded-full bg-primary-500 animate-pulse"></span>
                                <span class="text-sm font-medium text-surface-700 dark:text-surface-300">
                                    {{ groupedCounts.get(data?.type || 'Non défini') || 0 }} employé(s)
                                </span>
                            </div> 
                        </div>
                    </div>
                </template>

                <!-- Columns -->
                <Column field="nom" header="Nom & Prénom(s)" :sortable="true" 
                    :pt="{
                        headerContent: 'flex items-center gap-2',
                        sortIcon: 'text-primary-500'
                    }">
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white text-sm font-medium">
                                {{ data.nom.charAt(0) }}{{ data.prenom.charAt(0) }}
                            </div>
                            <span class="font-medium text-surface-900 dark:text-surface-100">{{ data.nom + " " + data.prenom}}</span>
                        </div>
                    </template>
                </Column>
                
                <Column field="fonction" header="Fonction" :sortable="true">
                    <template #body="{ data }">
                        <span class="text-surface-700 dark:text-surface-300">{{ data.fonction }}</span>
                    </template>
                </Column>
<!--                 
                <Column field="type" header="Type" :sortable="true">
                    <template #body="{ data }">
                        <Tag 
                            :value="data.type" 
                            :severity="getTypeSeverity(data.type)"
                            class="px-3 py-1.5 rounded-full font-medium shadow-sm"
                        />
                    </template>
                </Column> -->
                
                <Column field="telephone" header="Téléphone">
                    <template #body="{ data }">
                        <div data-tour="admin-rh.actions" class="flex items-center gap-2">
                            <i class="pi pi-phone text-surface-400"></i>
                            <span class="text-surface-700 dark:text-surface-300 font-mono">{{ data.telephone }}</span>
                        </div>
                    </template>
                </Column>
                
                <Column field="dateEmbauche" header="Date d'embauche" :sortable="true">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-calendar text-surface-400"></i>
                            <span class="text-surface-700 dark:text-surface-300">{{ formatDate(data.dateEmbauche) }}</span>
                        </div>
                    </template>
                </Column>
                
                <Column header="Actions" style="min-width: 180px">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <Button 
                                icon="pi pi-eye" 
                                severity="secondary" 
                                text 
                                rounded
                                v-tooltip.top="'Voir détails'"
                                class="hover:bg-surface-100 dark:hover:bg-surface-700"
                                @click="() => openDetails(data)" 
                            />
                            <Button 
                                icon="pi pi-pen-to-square" 
                                severity="info" 
                                text 
                                rounded
                                v-tooltip.top="'Modifier'"
                                class="hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                @click="openEdit(data)" 
                            />
                            <Button 
                                icon="pi pi-trash" 
                                severity="danger" 
                                text 
                                rounded
                                v-tooltip.top="'Supprimer'"
                                class="hover:bg-red-50 dark:hover:bg-red-900/20"
                                @click="(event) => confirmDelete(event, data)" 
                            />
                        </div>
                    </template>
                </Column>

                <!-- Empty State -->
                <template #empty>
                    <div class="text-center py-12">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 mb-4">
                            <i class="pi pi-users text-3xl text-surface-400"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300 mb-2">
                            Aucun employé trouvé
                        </h4>
                        <p class="text-surface-600 dark:text-surface-400 mb-6 max-w-md mx-auto">
                            {{ search || typeFilter ? 'Aucun résultat ne correspond à vos filtres.' : 'Commencez par ajouter votre premier employé.' }}
                        </p>
                        <Button 
                            v-if="!(search || typeFilter)"
                            icon="pi pi-plus" 
                            label="Ajouter un employé" 
                            @click="openCreate" 
                            class="bg-gradient-to-r from-primary-500 to-primary-600 border-0"
                        />
                        <Button 
                            v-else
                            icon="pi pi-filter-slash" 
                            label="Réinitialiser les filtres" 
                            severity="secondary" 
                            outlined
                            @click="resetFilters" 
                        />
                    </div>
                </template>

                <!-- Loading State -->
                <template #loading>
                    <div class="flex items-center justify-center py-12">
                        <div class="text-center">
                            <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-4"></i>
                            <p class="text-surface-600 dark:text-surface-400">Chargement des données...</p>
                        </div>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Stats Cards (Optional addition) -->
        <div data-tour="admin-rh.stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-5 border border-blue-200/50 dark:border-blue-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Total employés</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ totalRecords }}</p>
                    </div>
                    <i class="pi pi-users text-2xl text-blue-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-5 border border-green-200/50 dark:border-green-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-700 dark:text-green-300 font-medium">Employés actifs</p>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-2">--</p>
                    </div>
                    <i class="pi pi-check-circle text-2xl text-green-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">En congés</p>
                        <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">--</p>
                    </div>
                    <i class="pi pi-calendar-minus text-2xl text-amber-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-5 border border-purple-200/50 dark:border-purple-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-purple-700 dark:text-purple-300 font-medium">Nouveaux ce mois</p>
                        <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-2">--</p>
                    </div>
                    <i class="pi pi-chart-line text-2xl text-purple-500"></i>
                </div>
            </div>
        </div>

        <EmployeeForm 
            v-model:visible="formVisible" 
            :mode="formMode" 
            :employee="currentEmployee" 
            :loading="loading"
            tourTarget="admin-rh.dialog.form"
            @submit="confirmSave" 
        />
    </section>
</template>
 