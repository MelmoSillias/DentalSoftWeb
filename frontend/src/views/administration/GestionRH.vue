<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import EmployeeForm from '@/components/administration/EmployeeForm.vue';
import LeaveFormDialog from '@/components/administration/LeaveFormDialog.vue';
import PayrollPaymentDialog from '@/components/administration/PayrollPaymentDialog.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import PrintPayrollSlipPage from '@/components/print/PrintPayrollSlipPage.vue';
import { useEmployees } from '@/composables/useEmployees';
import { useLeaves } from '@/composables/useLeaves';
import { usePayrolls } from '@/composables/usePayrolls';
import { usePrinter } from '@/composables/usePrinter';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAdministrationGestionRHTour } from '@/tours/administrationGestionRHTour';
import { startTourGuide } from '@/tours/tourGuideClient';

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const { printComponent } = usePrinter();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Gestion RH' }];

const activeTab = ref('employees');

const typeOptions = [
    { label: 'Tous les types', value: null },
    { label: 'Médecin', value: 'Medecin' },
    { label: 'Infirmier', value: 'Infirmier' },
    { label: 'Réceptionniste', value: 'Receptionniste' },
    { label: 'Admin', value: 'Admin' },
    { label: 'Autre', value: 'Autre' }
];

const {
    employees,
    totalRecords,
    loading: employeesLoading,
    error: employeesError,
    fetchEmployees,
    addEmployee,
    updateEmployee,
    deleteEmployee
} = useEmployees();

const {
    payrolls,
    totalRecords: payrollTotalRecords,
    loading: payrollLoading,
    contextLoading: payrollContextLoading,
    paymentContext,
    error: payrollError,
    fetchData: fetchPayrolls,
    fetchContext: fetchPayrollContext,
    add: addPayroll,
    remove: removePayroll,
    fetchPrintPayload: fetchPayrollPrintPayload
} = usePayrolls();

const {
    leaves,
    loading: leavesLoading,
    error: leavesError,
    fetchData: fetchLeaves,
    add: addLeave,
    edit: editLeave,
    remove: removeLeave
} = useLeaves();

const search = ref('');
const typeFilter = ref(null);
const employeePage = ref(0);
const employeeRows = ref(15);

const payrollPage = ref(0);
const payrollRows = ref(10);
const payrollMonthModel = ref(new Date());
const payrollEmployeeId = ref(null);
const payrollDialogVisible = ref(false);
const payrollSaving = ref(false);

const leaveDialogVisible = ref(false);
const leaveMode = ref('create');
const selectedLeave = ref(null);
const leaveSaving = ref(false);
const leaveEmployeeId = ref(null);
const leaveTypeFilter = ref('');
const leaveRange = ref([]);

const formVisible = ref(false);
const formMode = ref('create');
const currentEmployee = ref(null);

const isGuidedTourStarting = ref(false);

const employeeOptions = computed(() => employees.value || []);

const filteredEmployees = computed(() => {
    const rows = Array.isArray(employees.value) ? employees.value : [];
    if (!typeFilter.value) return rows;
    return rows.filter((employee) => employee?.type === typeFilter.value);
});

const groupedCounts = computed(() => {
    const map = new Map();
    (filteredEmployees.value || []).forEach((employee) => {
        const key = employee?.type || 'Non classé';
        map.set(key, (map.get(key) || 0) + 1);
    });

    return map;
});

const payrollMonth = computed(() => (payrollMonthModel.value ? payrollMonthModel.value.getMonth() + 1 : null));
const payrollYear = computed(() => (payrollMonthModel.value ? payrollMonthModel.value.getFullYear() : null));

const leavesFiltered = computed(() => {
    let rows = Array.isArray(leaves.value) ? [...leaves.value] : [];

    if (leaveEmployeeId.value) {
        rows = rows.filter((row) => Number(row.employeId) === Number(leaveEmployeeId.value));
    }

    if (leaveTypeFilter.value) {
        const target = String(leaveTypeFilter.value).toLowerCase();
        rows = rows.filter((row) => String(row.type || '').toLowerCase().includes(target));
    }

    const [start, end] = leaveRange.value || [];
    if (start) {
        const startIso = start.toISOString().slice(0, 10);
        rows = rows.filter((row) => String(row.end || row.start || '') >= startIso);
    }
    if (end) {
        const endIso = end.toISOString().slice(0, 10);
        rows = rows.filter((row) => String(row.start || '') <= endIso);
    }

    return rows;
});

const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('fr-FR');
};

const formatAmount = (value) => `${Number(value || 0).toLocaleString('fr-FR')} F CFA`;

const monthLabel = (month, year) => {
    if (!month || !year) return '-';
    const date = new Date(year, month - 1, 1);
    return date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
};

const loadEmployees = async () => {
    await fetchEmployees({ page: 0, rows: 1000, search: search.value || '' });
    if (employeesError.value) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: employeesError.value, life: 4000 });
    }
};

const loadPayrolls = async ({ page = payrollPage.value, rows = payrollRows.value } = {}) => {
    payrollPage.value = page;
    payrollRows.value = rows;

    await fetchPayrolls({
        page,
        rows,
        employeeId: payrollEmployeeId.value,
        month: payrollMonth.value,
        year: payrollYear.value
    });

    if (payrollError.value) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: payrollError.value, life: 4000 });
    }
};

const loadLeaves = async () => {
    const [start, end] = leaveRange.value || [];
    await fetchLeaves({
        employeId: leaveEmployeeId.value,
        type: leaveTypeFilter.value,
        start: start ? start.toISOString().slice(0, 10) : '',
        end: end ? end.toISOString().slice(0, 10) : ''
    });

    if (leavesError.value) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: leavesError.value, life: 4000 });
    }
};

const resetFilters = () => {
    search.value = '';
    typeFilter.value = null;
    loadEmployees();
};

const openCreateEmployee = () => {
    formMode.value = 'create';
    currentEmployee.value = null;
    formVisible.value = true;
};

const openEditEmployee = (employee) => {
    formMode.value = 'edit';
    currentEmployee.value = employee;
    formVisible.value = true;
};

const openDetails = (employee) => {
    router.push({ name: 'administration-employee-details', params: { id: employee.id } });
};

const confirmEmployeeSave = (payload, event) => {
    confirm.require({
        target: event?.currentTarget,
        message: formMode.value === 'edit' ? 'Confirmer la mise a jour ?' : 'Confirmer la creation ?',
        icon: 'pi pi-check',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                if (payload.mode === 'edit' && payload.id) {
                    await updateEmployee(payload.id, payload.formData);
                    toast.add({ severity: 'success', summary: 'Succes', detail: 'Employe mis a jour.', life: 3000 });
                } else {
                    await addEmployee(payload.formData);
                    toast.add({ severity: 'success', summary: 'Succes', detail: 'Employe cree.', life: 3000 });
                }
                formVisible.value = false;
                await loadEmployees();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Echec de sauvegarde.', life: 4000 });
            }
        }
    });
};

const confirmEmployeeDelete = (event, employee) => {
    confirm.require({
        target: event?.currentTarget,
        message: `Supprimer ${employee?.prenom || ''} ${employee?.nom || ''} ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await deleteEmployee(employee.id);
                toast.add({ severity: 'success', summary: 'Succes', detail: 'Employe supprime.', life: 3000 });
                await loadEmployees();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Suppression impossible.', life: 4000 });
            }
        }
    });
};

const printEmployees = async () => {
    const rows = filteredEmployees.value.map((employee) => ({
        nom: employee?.nom || '-',
        prenom: employee?.prenom || '-',
        type: employee?.type || '-',
        telephone: employee?.telephone || '-',
        dateEmbauche: formatDate(employee?.dateEmbauche)
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Liste des employes',
        subtitle: `${rows.length} employe(s)`,
        columns: [
            { key: 'nom', label: 'Nom' },
            { key: 'prenom', label: 'Prenom' },
            { key: 'type', label: 'Type' },
            { key: 'telephone', label: 'Telephone' },
            { key: 'dateEmbauche', label: "Date d'embauche" }
        ],
        rows
    });
};

const openPayrollDialog = () => {
    payrollDialogVisible.value = true;
};

const requestPayrollContext = async ({ employeeId, month, year }) => {
    await fetchPayrollContext(employeeId, month, year);
};

const submitPayroll = async (payload) => {
    payrollSaving.value = true;
    try {
        await addPayroll(payload);
        toast.add({ severity: 'success', summary: 'Succes', detail: 'Paiement enregistre.', life: 3000 });
        payrollDialogVisible.value = false;
        await loadPayrolls({ page: 0, rows: payrollRows.value });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Echec de creation.', life: 4000 });
    } finally {
        payrollSaving.value = false;
    }
};

const confirmDeletePayroll = (event, row) => {
    confirm.require({
        target: event?.currentTarget,
        message: `Supprimer le paiement de ${row.employeeName} (${monthLabel(row.month, row.year)}) ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await removePayroll(row.id);
                toast.add({ severity: 'success', summary: 'Succes', detail: 'Paiement supprime.', life: 3000 });
                await loadPayrolls({ page: payrollPage.value, rows: payrollRows.value });
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Suppression impossible.', life: 4000 });
            }
        }
    });
};

const printPayrollSlip = async (row) => {
    try {
        const data = await fetchPayrollPrintPayload(row.id);
        await printComponent(PrintPayrollSlipPage, {
            title: 'Fiche de paie',
            docId: data.id,
            periodLabel: monthLabel(data.period?.month, data.period?.year),
            employeeName: data.employee?.fullname,
            employeeFonction: data.employee?.fonction,
            salaryType: data.salaryType,
            salaryValue: data.salaryValue,
            baseAmount: data.baseAmount,
            calculatedAmount: data.calculatedAmount,
            paidAmount: data.paidAmount,
            paidAt: formatDate(data.paidAt),
            note: data.note
        });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Impression impossible.', life: 4000 });
    }
};

const openCreateLeave = () => {
    leaveMode.value = 'create';
    selectedLeave.value = null;
    leaveDialogVisible.value = true;
};

const openEditLeave = (row) => {
    leaveMode.value = 'edit';
    selectedLeave.value = row;
    leaveDialogVisible.value = true;
};

const submitLeave = async (payload) => {
    leaveSaving.value = true;
    try {
        if (leaveMode.value === 'edit' && selectedLeave.value?.id) {
            await editLeave(selectedLeave.value.id, payload);
            toast.add({ severity: 'success', summary: 'Succes', detail: 'Conge mis a jour.', life: 3000 });
        } else {
            await addLeave(payload);
            toast.add({ severity: 'success', summary: 'Succes', detail: 'Conge cree.', life: 3000 });
        }
        leaveDialogVisible.value = false;
        await loadLeaves();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Operation impossible.', life: 4000 });
    } finally {
        leaveSaving.value = false;
    }
};

const confirmDeleteLeave = (event, row) => {
    confirm.require({
        target: event?.currentTarget,
        message: `Supprimer ce conge de ${row.employe || '-'} ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await removeLeave(row.id);
                toast.add({ severity: 'success', summary: 'Succes', detail: 'Conge supprime.', life: 3000 });
                await loadLeaves();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Suppression impossible.', life: 4000 });
            }
        }
    });
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'administration-gestionrh' || isGuidedTourStarting.value) return;

    isGuidedTourStarting.value = true;
    try {
        activeTab.value = 'employees';
        await startTourGuide({
            group: 'administration-gestionrh',
            steps: createAdministrationGestionRHTour({
                hasEmployees: filteredEmployees.value.length > 0,
                openCreateDialog: async () => openCreateEmployee(),
                openEditDialog: async () => {
                    if (filteredEmployees.value.length) {
                        openEditEmployee(filteredEmployees.value[0]);
                    }
                },
                expandGroups: async () => undefined,
                closeAllDialogs: () => {
                    formVisible.value = false;
                    leaveDialogVisible.value = false;
                    payrollDialogVisible.value = false;
                }
            })
        });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Aide guidee', detail: 'Impossible de lancer le tour RH.', life: 3000 });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

let searchTimer = null;
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadEmployees(), 350);
});

watch([payrollEmployeeId, payrollMonthModel], () => {
    if (activeTab.value !== 'payroll') return;
    loadPayrolls({ page: 0, rows: payrollRows.value });
});

watch([leaveEmployeeId, leaveTypeFilter, leaveRange], () => {
    if (activeTab.value !== 'leaves') return;
    loadLeaves();
}, { deep: true });

watch(activeTab, (tab) => {
    if (tab === 'payroll') {
        loadPayrolls({ page: 0, rows: payrollRows.value });
        return;
    }

    if (tab === 'leaves') {
        loadLeaves();
    }
});

onMounted(async () => {
    await loadEmployees();
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});
</script>

<template>
    <section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <AppToast />
        <ConfirmPopup />

        <div data-tour="admin-rh.header" class="mb-6 md:mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="pi pi-users text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">Gestion RH</h1>
                    </div>
                    <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base">Gestion des employes, paie et conges.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button v-if="activeTab === 'employees'" icon="pi pi-plus" label="Nouvel employe" class="bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="openCreateEmployee" />
                    <Button v-if="activeTab === 'payroll'" icon="pi pi-wallet" label="Ajouter un paiement" class="bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="openPayrollDialog" />
                    <Button v-if="activeTab === 'leaves'" icon="pi pi-calendar-plus" label="Ajouter un conge" class="bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="openCreateLeave" />
                </div>
            </div>

            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
        </div>

        <Tabs :value="activeTab" @update:value="activeTab = $event">
            <TabList>
                <Tab value="employees">Employes</Tab>
                <Tab value="payroll">Gestion de la paie</Tab>
                <Tab value="leaves">Gestion des conges</Tab>
            </TabList>

            <TabPanels class="mt-4">
                <TabPanel value="employees">
                    <div data-tour="admin-rh.table" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                        <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-5">
                                    <label class="block text-sm font-medium mb-2">Recherche</label>
                                    <InputText v-model="search" class="w-full" placeholder="Nom, prenom, telephone" />
                                </div>
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-2">Type</label>
                                    <Select v-model="typeFilter" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" />
                                </div>
                                <div class="md:col-span-3 flex justify-end gap-2">
                                    <Button icon="pi pi-refresh" severity="secondary" outlined @click="loadEmployees" />
                                    <Button icon="pi pi-filter-slash" severity="secondary" outlined @click="resetFilters" />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            :value="filteredEmployees"
                            :loading="employeesLoading"
                            dataKey="id"
                            paginator
                            :rows="employeeRows"
                            :first="employeePage * employeeRows"
                            :totalRecords="filteredEmployees.length"
                            rowGroupMode="subheader"
                            groupRowsBy="type"
                            sortField="type"
                            :sortOrder="1"
                            @page="(e) => { employeePage = e.page; employeeRows = e.rows; }"
                        >
                            <template #groupheader="{ data }">
                                <div class="flex items-center justify-between py-1">
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-folder text-primary-500"></i>
                                        <span class="font-semibold text-surface-900 dark:text-surface-100">{{ data?.type || 'Non classé' }}</span>
                                    </div>
                                    <Tag :value="`${groupedCounts.get(data?.type || 'Non classé') || 0} employé(s)`" severity="secondary" />
                                </div>
                            </template>

                            <Column field="nom" header="Nom & Prenom(s)">
                                <template #body="{ data }">
                                    <span class="font-medium">{{ `${data.nom || ''} ${data.prenom || ''}`.trim() }}</span>
                                </template>
                            </Column>
                            <Column field="fonction" header="Fonction" />
                            <Column field="type" header="Type">
                                <template #body="{ data }">
                                    <Tag :value="data.type || '-'" severity="info" />
                                </template>
                            </Column>
                            <Column field="telephone" header="Telephone" />
                            <Column field="dateEmbauche" header="Date d'embauche">
                                <template #body="{ data }">{{ formatDate(data.dateEmbauche) }}</template>
                            </Column>
                            <Column header="Actions" style="min-width: 170px">
                                <template #body="{ data }">
                                    <div data-tour="admin-rh.actions" class="flex items-center gap-2">
                                        <Button icon="pi pi-eye" text rounded severity="secondary" @click="openDetails(data)" />
                                        <Button icon="pi pi-pen-to-square" text rounded severity="info" @click="openEditEmployee(data)" />
                                        <Button icon="pi pi-trash" text rounded severity="danger" @click="(event) => confirmEmployeeDelete(event, data)" />
                                    </div>
                                </template>
                            </Column>

                            <template #empty>
                                <div class="text-center py-10 text-surface-500">Aucun employe trouve.</div>
                            </template>

                            <template #footer>
                                <div class="flex justify-end py-2">
                                    <Button icon="pi pi-file-pdf" label="Exporter PDF" severity="secondary" outlined @click="printEmployees" />
                                </div>
                            </template>
                        </DataTable>
                    </div>

                    <div data-tour="admin-rh.stats" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-5 border border-blue-200/50 dark:border-blue-800/50">
                            <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Total employes</p>
                            <p class="text-2xl font-bold mt-2">{{ totalRecords || filteredEmployees.length }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 rounded-2xl p-5 border border-emerald-200/50 dark:border-emerald-800/50">
                            <p class="text-sm text-emerald-700 dark:text-emerald-300 font-medium">Paiements enregistres</p>
                            <p class="text-2xl font-bold mt-2">{{ payrollTotalRecords }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50">
                            <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">Conges enregistres</p>
                            <p class="text-2xl font-bold mt-2">{{ leaves.length }}</p>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="payroll">
                    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50">
                        <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-2">Mois / Annee</label>
                                    <DatePicker v-model="payrollMonthModel" view="month" dateFormat="mm/yy" showIcon class="w-full" />
                                </div>
                                <div class="md:col-span-5">
                                    <label class="block text-sm font-medium mb-2">Employe</label>
                                    <Select
                                        v-model="payrollEmployeeId"
                                        :options="employeeOptions"
                                        optionLabel="fullname"
                                        optionValue="id"
                                        class="w-full"
                                        showClear
                                        placeholder="Tous les employes"
                                    >
                                        <template #option="slotProps">
                                            {{ `${slotProps.option.prenom || ''} ${slotProps.option.nom || ''}`.trim() }}
                                        </template>
                                    </Select>
                                </div>
                                <div class="md:col-span-3 flex justify-end">
                                    <Button icon="pi pi-refresh" severity="secondary" outlined @click="loadPayrolls({ page: 0, rows: payrollRows })" />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            :value="payrolls"
                            :loading="payrollLoading"
                            dataKey="id"
                            paginator
                            :rows="payrollRows"
                            :first="payrollPage * payrollRows"
                            :totalRecords="payrollTotalRecords"
                            lazy
                            @page="(e) => loadPayrolls({ page: e.page, rows: e.rows })"
                        >
                            <Column field="employeeName" header="Employe" />
                            <Column field="employeeFonction" header="Fonction" />
                            <Column header="Periode">
                                <template #body="{ data }">{{ monthLabel(data.month, data.year) }}</template>
                            </Column>
                            <Column field="calculatedAmount" header="Montant calcule">
                                <template #body="{ data }">{{ formatAmount(data.calculatedAmount) }}</template>
                            </Column>
                            <Column field="paidAmount" header="Montant verse">
                                <template #body="{ data }"><span class="font-semibold">{{ formatAmount(data.paidAmount) }}</span></template>
                            </Column>
                            <Column field="paidAt" header="Date">
                                <template #body="{ data }">{{ formatDate(data.paidAt) }}</template>
                            </Column>
                            <Column field="note" header="Note">
                                <template #body="{ data }">{{ data.note || '-' }}</template>
                            </Column>
                            <Column header="Actions" style="min-width: 140px">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <Button icon="pi pi-print" text rounded severity="secondary" @click="printPayrollSlip(data)" />
                                        <Button icon="pi pi-trash" text rounded severity="danger" @click="(event) => confirmDeletePayroll(event, data)" />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </TabPanel>

                <TabPanel value="leaves">
                    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50">
                        <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-2">Employe</label>
                                    <Select
                                        v-model="leaveEmployeeId"
                                        :options="employeeOptions"
                                        optionLabel="fullname"
                                        optionValue="id"
                                        class="w-full"
                                        showClear
                                        placeholder="Tous les employes"
                                    >
                                        <template #option="slotProps">
                                            {{ `${slotProps.option.prenom || ''} ${slotProps.option.nom || ''}`.trim() }}
                                        </template>
                                    </Select>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium mb-2">Type</label>
                                    <InputText v-model="leaveTypeFilter" class="w-full" placeholder="vacances, arret..." />
                                </div>
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-2">Periode</label>
                                    <DatePicker v-model="leaveRange" selectionMode="range" showIcon dateFormat="yy-mm-dd" class="w-full" />
                                </div>
                                <div class="md:col-span-1 flex justify-end">
                                    <Button icon="pi pi-refresh" severity="secondary" outlined @click="loadLeaves" />
                                </div>
                            </div>
                        </div>

                        <DataTable :value="leavesFiltered" :loading="leavesLoading" dataKey="id">
                            <Column field="employe" header="Employe" />
                            <Column field="type" header="Type" />
                            <Column field="start" header="Debut">
                                <template #body="{ data }">{{ formatDate(data.start) }}</template>
                            </Column>
                            <Column field="end" header="Fin">
                                <template #body="{ data }">{{ formatDate(data.end) }}</template>
                            </Column>
                            <Column field="durationDays" header="Duree (jours)" />
                            <Column header="Actions" style="min-width: 150px">
                                <template #body="{ data }">
                                    <div class="flex items-center gap-2">
                                        <Button icon="pi pi-pen-to-square" text rounded severity="info" @click="openEditLeave(data)" />
                                        <Button icon="pi pi-trash" text rounded severity="danger" @click="(event) => confirmDeleteLeave(event, data)" />
                                    </div>
                                </template>
                            </Column>

                            <template #empty>
                                <div class="text-center py-10 text-surface-500">Aucun conge enregistre.</div>
                            </template>
                        </DataTable>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>

        <EmployeeForm
            v-model:visible="formVisible"
            :mode="formMode"
            :employee="currentEmployee"
            :loading="employeesLoading"
            tourTarget="admin-rh.dialog.form"
            @submit="confirmEmployeeSave"
        />

        <PayrollPaymentDialog
            v-model:visible="payrollDialogVisible"
            :employees="employeeOptions"
            :context="paymentContext"
            :context-loading="payrollContextLoading"
            :loading="payrollSaving"
            @request-context="requestPayrollContext"
            @submit="submitPayroll"
        />

        <LeaveFormDialog
            v-model:visible="leaveDialogVisible"
            :mode="leaveMode"
            :leave="selectedLeave"
            :employees="employeeOptions"
            :loading="leaveSaving"
            @submit="submitLeave"
        />
    </section>
</template>
