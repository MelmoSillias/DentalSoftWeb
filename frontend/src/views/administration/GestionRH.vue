<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import EmployeeForm from '@/components/administration/EmployeeForm.vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import LeaveFormDialog from '@/components/administration/LeaveFormDialog.vue';
import PayrollPaymentDialog from '@/components/administration/PayrollPaymentDialog.vue';
import PayrollDetailDialog from '@/components/administration/PayrollDetailDialog.vue';
import InpsPanel from '@/components/administration/InpsPanel.vue';
import PayrollPaymentGroups from '@/components/administration/PayrollPaymentGroups.vue';
import EmployeePayrollTable from '@/components/administration/EmployeePayrollTable.vue';
import DailyPayrollPaymentDialog from '@/components/administration/DailyPayrollPaymentDialog.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import PrintPayrollSlipPage from '@/components/print/PrintPayrollSlipPage.vue';
import { useEmployees } from '@/composables/useEmployees';
import { useLeaves } from '@/composables/useLeaves';
import { usePayrolls } from '@/composables/usePayrolls';
import { usePrinter } from '@/composables/usePrinter';
import { activateAdminTourMock, deactivateAdminTourMock, resetAdminTourMockData } from '@/services/adminTourMock';
import { useGuidedTour } from '@/composables/useGuidedTour';
import { employeeTypeInfirmierOption, formatEmployeeTypeLabel } from '@/utils/employeeTypeUtils';

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();
const { printComponent } = usePrinter();

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Gestion RH' }];

const activeTab = ref('employees');

const typeOptions = [
    { label: 'Tous les types', value: null },
    { label: 'Médecin', value: 'Medecin' },
    employeeTypeInfirmierOption,
    { label: 'Réceptionniste', value: 'Receptionniste' },
    { label: 'Admin', value: 'Admin' },
    { label: 'Autre', value: 'Autre' }
];

const { employees, totalRecords, loading: employeesLoading, error: employeesError, fetchEmployees, fetchEmployeesTrash, addEmployee, updateEmployee, deleteEmployee, restoreEmployee } = useEmployees();

const {
    payrolls,
    loading: payrollLoading,
    contextLoading: payrollContextLoading,
    paymentContext,
    paymentMethods,
    error: payrollError,
    fetchData: fetchPayrolls,
    fetchAll: fetchAllPayrolls,
    fetchEmployees: fetchEmployeePayrolls,
    fetchDaily: fetchDailyPayroll,
    resetContext: resetPayrollContext,
    fetchPaymentMethods,
    fetchOne: fetchPayrollOne,
    fetchContext: fetchPayrollContext,
    add: addPayroll,
    edit: editPayroll,
    remove: removePayroll,
    fetchPrintPayload: fetchPayrollPrintPayload
} = usePayrolls();

const { leaves, loading: leavesLoading, error: leavesError, fetchData: fetchLeaves, add: addLeave, edit: editLeave, remove: removeLeave } = useLeaves();

const search = ref('');
const typeFilter = ref(null);
const employeePage = ref(0);
const employeeRows = ref(15);

const payrollPage = ref(0);
const payrollRows = ref(0);
const payrollMonthModel = ref(new Date());
const payrollEmployeeId = ref(null);
const payrollDialogVisible = ref(false);
const payrollDetailVisible = ref(false);
const payrollCancelMode = ref(false);
const selectedPayroll = ref(null);
const payrollDetailSaving = ref(false);
const payrollSaving = ref(false);
const payrollPrinting = ref(false);
const payrollView = ref('payments');
const payrollViewOptions = [{ label: 'Paiements', value: 'payments' }, { label: 'Employés', value: 'employees' }];
const employeePayrollRows = ref([]);
const employeePayrollLoading = ref(false);
const payrollInitialEmployeeId = ref(null);
const dailyPayrollDialogVisible = ref(false);
const dailyPayrollRefreshKey = ref(0);
let employeePayrollRequestId = 0;

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
const showTrashDialog = ref(false);
const trashEmployees = ref([]);
const trashLoading = ref(false);
const trashSearch = ref('');
const trashFirst = ref(0);
const trashRows = ref(10);
const trashTotalRecords = ref(0);
const restoringEmployeeId = ref(null);
let trashSearchTimer = null;

let guidedTourDemoActive = false;

const employeeOptions = computed(() => [...new Map([...(employees.value || []), ...employeePayrollRows.value.map((row) => row.context.employee)].map((employee) => [employee.id, employee])).values()]);

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
        rows = rows.filter((row) =>
            String(row.type || '')
                .toLowerCase()
                .includes(target)
        );
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

    if (payrollView.value === 'employees') {
        const requestId = ++employeePayrollRequestId;
        employeePayrollLoading.value = true;
        employeePayrollRows.value = [];
        try {
            if (!payrollMonth.value || !payrollYear.value) return;
            const data = await fetchEmployeePayrolls({ month: payrollMonth.value, year: payrollYear.value, employeeId: payrollEmployeeId.value });
            if (requestId === employeePayrollRequestId) employeePayrollRows.value = data;
        } catch (err) {
            if (requestId === employeePayrollRequestId) toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Chargement des salaires impossible.', life: 4000 });
        } finally {
            if (requestId === employeePayrollRequestId) employeePayrollLoading.value = false;
        }
        return;
    }

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
    const name = `${employee?.prenom || ''} ${employee?.nom || ''}`.trim();
    confirm.require({
        target: event?.currentTarget,
        message: `Deplacer ${name || 'cet employe'} vers la corbeille ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await deleteEmployee(employee.id);
                toast.add({ severity: 'success', summary: 'Succes', detail: 'Employe deplace dans la corbeille.', life: 3000 });
                await loadEmployees();
                clearMissingEmployeeFilters();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Suppression impossible.', life: 4000 });
            }
        }
    });
};

const clearMissingEmployeeFilters = () => {
    const ids = new Set((employees.value || []).map((employee) => employee.id));
    if (payrollEmployeeId.value && !ids.has(payrollEmployeeId.value)) {
        payrollEmployeeId.value = null;
    }
    if (leaveEmployeeId.value && !ids.has(leaveEmployeeId.value)) {
        leaveEmployeeId.value = null;
    }
};

const loadTrash = async ({ page = 1, rows = trashRows.value } = {}) => {
    trashLoading.value = true;
    try {
        const data = await fetchEmployeesTrash({ page, limit: rows, q: trashSearch.value || '' });
        trashEmployees.value = Array.isArray(data?.items) ? data.items : [];
        trashTotalRecords.value = data?.total ?? trashEmployees.value.length;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Corbeille indisponible.', life: 4000 });
    } finally {
        trashLoading.value = false;
    }
};

const openTrashDialog = async () => {
    showTrashDialog.value = true;
    trashFirst.value = 0;
    await loadTrash({ page: 1, rows: trashRows.value });
};

const handleTrashPage = (event) => {
    trashFirst.value = event.first;
    trashRows.value = event.rows;
    loadTrash({ page: event.page + 1, rows: event.rows });
};

const restoreEmployeeFromTrash = async (employee) => {
    restoringEmployeeId.value = employee.id;
    try {
        await restoreEmployee(employee.id);
        toast.add({ severity: 'success', summary: 'Succes', detail: 'Employe restaure.', life: 3000 });
        const page = Math.floor(trashFirst.value / trashRows.value) + 1;
        await loadTrash({ page, rows: trashRows.value });
        await loadEmployees();
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Restauration impossible.', life: 4000 });
    } finally {
        restoringEmployeeId.value = null;
    }
};

const printEmployees = async () => {
    const rows = filteredEmployees.value.map((employee) => ({
        nom: employee?.nom || '-',
        prenom: employee?.prenom || '-',
        type: formatEmployeeTypeLabel(employee?.type) || '-',
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

const openPayrollDialog = async (employee = null) => {
    resetPayrollContext();
    payrollInitialEmployeeId.value = Number(employee?.id) || null;
    await fetchPaymentMethods();
    if (employee?.context?.frequencePaiement === 'journalier' || employee?.frequencePaiement === 'journalier') {
        dailyPayrollDialogVisible.value = true;
        return;
    }
    payrollDialogVisible.value = true;
};

const openDailyPayrollDialog = ({ id }) => {
    payrollInitialEmployeeId.value = Number(id);
    payrollDialogVisible.value = false;
    dailyPayrollDialogVisible.value = true;
};

const submitDailyPayroll = async (payload) => {
    payrollSaving.value = true;
    try {
        await addPayroll(payload);
        dailyPayrollRefreshKey.value += 1;
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Journée intégralement payée.', life: 3000 });
        await loadPayrolls({ page: 0, rows: payrollRows.value });
    } catch (err) {
        dailyPayrollRefreshKey.value += 1;
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Paiement impossible.', life: 4000 });
    } finally {
        payrollSaving.value = false;
    }
};

const openPayrollDetail = async (row, cancel = false) => {
    payrollCancelMode.value = cancel === true;
    try {
        await fetchPaymentMethods();
        selectedPayroll.value = await fetchPayrollOne(row.id);
        payrollDetailVisible.value = true;
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Chargement impossible.', life: 4000 });
    }
};

const submitPayrollEdit = async (payload) => {
    if (!selectedPayroll.value?.id) return;
    payrollDetailSaving.value = true;
    try {
        await editPayroll(selectedPayroll.value.id, payload);
        toast.add({ severity: 'success', summary: 'Succes', detail: 'Paiement mis a jour.', life: 3000 });
        selectedPayroll.value = await fetchPayrollOne(selectedPayroll.value.id);
        payrollDetailVisible.value = false;
        await loadPayrolls({ page: payrollPage.value, rows: payrollRows.value });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Mise a jour impossible.', life: 4000 });
    } finally {
        payrollDetailSaving.value = false;
    }
};

const requestPayrollContext = async ({ employeeId, month, year, day = null }) => {
    await fetchPayrollContext(employeeId, { month, year, day });
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

const confirmDeletePayroll = (event, row) => openPayrollDetail(row, true);
const cancelPayroll = async (payload) => {
    if (!selectedPayroll.value?.id || payrollDetailSaving.value) return;
    payrollDetailSaving.value = true;
    try {
        await removePayroll(selectedPayroll.value.id, payload);
        payrollDetailVisible.value = false;
        toast.add({ severity: 'success', summary: 'Paie', detail: 'Paiement annul?. Historique conserv?.', life: 3000 });
        await loadPayrolls({ page: payrollPage.value, rows: payrollRows.value });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Paie', detail: err?.response?.data?.message || 'Annulation impossible.', life: 5000 });
    } finally { payrollDetailSaving.value = false; }
};

const printPayrollList = async () => {
    if (!payrollMonth.value || !payrollYear.value || payrollPrinting.value) return;
    payrollPrinting.value = true;
    const period = monthLabel(payrollMonth.value, payrollYear.value);
    try {
        const payments = (await fetchAllPayrolls({ employeeId: payrollEmployeeId.value, month: payrollMonth.value, year: payrollYear.value })).filter((payment) => payment.status !== 'cancelled');
        const rows = payments.map((payment) => ({
            employee: payment.employeeName || '-',
            netSalary: formatAmount(payment.calculatedAmount),
            amount: formatAmount(payment.paidAmount),
            method: payment.paymentMethod?.libelle || '-',
            date: formatDate(payment.paidAt)
        }));
        if (rows.length) rows.push({ employee: 'Total versé', netSalary: '', amount: formatAmount(payments.reduce((total, payment) => total + Number(payment.paidAmount || 0), 0)), method: '', date: '' });
        await printComponent(PrintDataTablePage, {
            title: 'Liste des salaires',
            subtitle: `Période : ${period} — ${payments.length} paiement(s)`,
            columns: [
                { key: 'employee', label: 'Employé' },
                { key: 'netSalary', label: 'Salaire net', align: 'right' },
                { key: 'amount', label: 'Montant versé', align: 'right' },
                { key: 'method', label: 'Mode' },
                { key: 'date', label: 'Date de paiement' }
            ],
            rows
        });
    } catch (err) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: err?.response?.data?.message || err?.message || 'Impression impossible.', life: 4000 });
    } finally {
        payrollPrinting.value = false;
    }
};

const printPayrollSlip = async (row) => {
    try {
        const data = await fetchPayrollPrintPayload(row?.id ?? row);
        await printComponent(PrintPayrollSlipPage, {
            title: data.status === 'cancelled' ? 'Bulletin de paie ? ANNUL?' : 'Bulletin de paie',
            docId: data.id,
            periodLabel: data.period?.day ? formatDate(data.period.day) : monthLabel(data.period?.month, data.period?.year),
            employeeName: data.employee?.fullname,
            employeeFonction: data.employee?.fonction,
            employeeTitle: data.employee?.titre,
            employeeAddress: data.employee?.adresse,
            matricule: data.employee?.matricule,
            frequenceSnapshot: data.frequenceSnapshot,
            salaryType: data.salaryType,
            salaryValue: data.salaryValue,
            primeType: data.primeType,
            primeValue: data.primeValue,
            primeBaseAmount: data.primeBaseAmount,
            baseAmount: data.baseAmount,
            baseSalaryAmount: data.baseSalaryAmount,
            primeAmount: data.primeAmount,
            indemniteSpeciale: data.indemniteSpeciale,
            inps: data.inps,
            salaireBrut: data.salaireBrut,
            totalRetenues: data.totalRetenues,
            netAPayer: data.netAPayer,
            calculatedAmount: data.calculatedAmount,
            paidAmount: data.paidAmount,
            previousPaidAmount: data.previousPaidAmount,
            remainingAmount: data.remainingAmount,
            paidAt: formatDate(data.paidAt),
            paymentMethodLabel: data.paymentMethod?.libelle,
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

const closeRhTourDialogs = () => {
    dailyPayrollDialogVisible.value = false;
    formVisible.value = false;
    leaveDialogVisible.value = false;
    payrollDialogVisible.value = false;
    payrollDetailVisible.value = false;
};

const prepareGuidedTourDemo = async () => {
    activateAdminTourMock();
    resetAdminTourMockData();
    guidedTourDemoActive = true;
    activeTab.value = 'employees';
    await loadEmployees();
    await nextTick();
};

const cleanupGuidedTourDemo = async () => {
    if (!guidedTourDemoActive) {
        closeRhTourDialogs();
        return;
    }

    closeRhTourDialogs();
    deactivateAdminTourMock();
    guidedTourDemoActive = false;
    await loadEmployees();
    await nextTick();
};

useGuidedTour({
    routeName: 'administration-gestionrh',
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    getStepContext: () => ({
        hasEmployees: filteredEmployees.value.length > 0,
        openCreateDialog: async () => openCreateEmployee(),
        openEditDialog: async () => {
            if (filteredEmployees.value.length) {
                openEditEmployee(filteredEmployees.value[0]);
            }
        },
        expandGroups: async () => undefined,
        closeAllDialogs: closeRhTourDialogs
    }),
    errorMessage: 'Impossible de lancer le tour RH.'
});

let searchTimer = null;
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadEmployees(), 350);
});

watch([payrollEmployeeId, payrollMonthModel, payrollView], () => {
    if (activeTab.value !== 'payroll') return;
    loadPayrolls({ page: 0, rows: payrollRows.value });
});

watch(
    [leaveEmployeeId, leaveTypeFilter, leaveRange],
    () => {
        if (activeTab.value !== 'leaves') return;
        loadLeaves();
    },
    { deep: true }
);

watch(activeTab, (tab) => {
    if (tab === 'payroll') {
        fetchPaymentMethods();
        loadPayrolls({ page: 0, rows: payrollRows.value });
        return;
    }

    if (tab === 'leaves') {
        loadLeaves();
    }
});

watch(trashSearch, () => {
    if (!showTrashDialog.value) return;
    clearTimeout(trashSearchTimer);
    trashSearchTimer = setTimeout(() => {
        trashFirst.value = 0;
        loadTrash({ page: 1, rows: trashRows.value });
    }, 350);
});

onMounted(async () => {
    await loadEmployees();
});

onBeforeUnmount(() => {
    clearTimeout(trashSearchTimer);
    deactivateAdminTourMock();
    guidedTourDemoActive = false;
    closeRhTourDialogs();
});
</script>

<template>
    <PageShell>
        <ConfirmPopup />

        <template #header>
            <PageHeader
                title="Gestion RH"
                subtitle="Gestion des employes, paie et conges."
                icon="pi pi-users"
                tour-id="admin-rh.header"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            >
                <template #actions>
                    <Button v-if="activeTab === 'employees'" icon="pi pi-trash" label="Corbeille" severity="secondary" outlined @click="openTrashDialog" />
                    <Button v-if="activeTab === 'employees'" icon="pi pi-plus" label="Nouvel employe" @click="openCreateEmployee" />
                    <Button v-if="activeTab === 'payroll'" icon="pi pi-wallet" label="Ajouter un paiement" @click="openPayrollDialog()" />
                    <Button v-if="activeTab === 'leaves'" icon="pi pi-calendar-plus" label="Ajouter un conge" @click="openCreateLeave" />
                </template>
            </PageHeader>
        </template>

        <Tabs :value="activeTab" @update:value="activeTab = $event">
            <TabList>
                <Tab value="employees">Employes</Tab>
                <Tab value="payroll">Gestion de la paie</Tab>
                <Tab value="inps">INPS</Tab>
                <Tab value="leaves">Gestion des conges</Tab>
            </TabList>

            <TabPanels class="mt-4">
                <TabPanel value="employees">
                    <PageSection title="Employés" tour-id="admin-rh.table">
                        <template #header>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end w-full">
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
                        </template>

                        <div class="page-table-scroll">
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
                            @page="
                                (e) => {
                                    employeePage = e.page;
                                    employeeRows = e.rows;
                                }
                            "
                        >
                            <template #groupheader="{ data }">
                                <div class="flex items-center justify-between py-1">
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-folder text-primary-500"></i>
                                        <span class="font-semibold text-surface-900 dark:text-surface-100">{{ formatEmployeeTypeLabel(data?.type) || 'Non classé' }}</span>
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
                                    <Tag :value="formatEmployeeTypeLabel(data.type) || '-'" severity="info" />
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
                    </PageSection>

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
                    <PageSection title="Gestion de la paie">
                        <template #header>
                            <div class="flex flex-col lg:flex-row gap-3 items-end w-full">
                                <div class="w-full lg:w-auto lg:shrink-0">
                                    <label class="block text-sm font-medium mb-2">Vue de la paie</label>
                                    <SelectButton v-model="payrollView" :options="payrollViewOptions" optionLabel="label" optionValue="value" :allowEmpty="false" aria-label="Vue de la paie" />
                                </div>
                                <div class="w-full lg:w-44 lg:shrink-0">
                                    <label class="block text-sm font-medium mb-2">Mois / Annee</label>
                                    <DatePicker v-model="payrollMonthModel" view="month" dateFormat="mm/yy" showIcon class="w-full" />
                                </div>
                                <div class="w-full lg:flex-1 min-w-0">
                                    <label class="block text-sm font-medium mb-2">Employe</label>
                                    <Select v-model="payrollEmployeeId" :options="employeeOptions" optionLabel="fullname" optionValue="id" class="w-full" showClear placeholder="Tous les employes">
                                        <template #option="slotProps">
                                            {{ `${slotProps.option.prenom || ''} ${slotProps.option.nom || ''}`.trim() }}
                                        </template>
                                    </Select>
                                </div>
                                <div class="flex justify-end gap-2 w-full lg:w-auto lg:shrink-0">
                                    <Button icon="pi pi-print" label="Imprimer" severity="secondary" outlined :loading="payrollPrinting" :disabled="!payrollMonthModel" @click="printPayrollList" />
                                    <Button icon="pi pi-refresh" severity="secondary" outlined @click="loadPayrolls({ page: 0, rows: payrollRows })" />
                                </div>
                            </div>
                        </template>

                        <div class="page-table-scroll">
                        <EmployeePayrollTable v-if="payrollView === 'employees'" :rows="employeePayrollRows" :loading="employeePayrollLoading" @pay="openPayrollDialog" @detail="openPayrollDetail" @print="printPayrollSlip" @remove="confirmDeletePayroll" />
                        <PayrollPaymentGroups v-else :payments="payrolls" :loading="payrollLoading" @detail="openPayrollDetail" @print="printPayrollSlip" @remove="confirmDeletePayroll" />
                        </div>
                    </PageSection>
                </TabPanel>

                <TabPanel value="inps"><InpsPanel v-if="activeTab === 'inps'" /></TabPanel>
                <TabPanel value="leaves">
                    <PageSection title="Gestion des congés">
                        <template #header>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end w-full">
                                <div class="md:col-span-4">
                                    <label class="block text-sm font-medium mb-2">Employe</label>
                                    <Select v-model="leaveEmployeeId" :options="employeeOptions" optionLabel="fullname" optionValue="id" class="w-full" showClear placeholder="Tous les employes">
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
                                    <PanelDatePicker v-model="leaveRange" showIcon dateFormat="yy-mm-dd" class="w-full" fluid />
                                </div>
                                <div class="md:col-span-1 flex justify-end">
                                    <Button icon="pi pi-refresh" severity="secondary" outlined @click="loadLeaves" />
                                </div>
                            </div>
                        </template>

                        <div class="page-table-scroll">
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
                    </PageSection>
                </TabPanel>
            </TabPanels>
        </Tabs>

        <EmployeeForm v-model:visible="formVisible" :mode="formMode" :employee="currentEmployee" :loading="employeesLoading" tourTarget="admin-rh.dialog.form" @submit="confirmEmployeeSave" />

        <PayrollPaymentDialog
            v-model:visible="payrollDialogVisible"
            :employees="employeeOptions"
            :payment-methods="paymentMethods"
            :context="paymentContext"
            :context-loading="payrollContextLoading"
            :context-error="payrollError || ''"
            :initial-employee-id="payrollInitialEmployeeId"
            :initial-period="payrollMonthModel"
            :loading="payrollSaving"
            @request-context="requestPayrollContext"
            @submit="submitPayroll"
            @open-daily="openDailyPayrollDialog"
        />

        <DailyPayrollPaymentDialog v-model:visible="dailyPayrollDialogVisible" :employees="employeeOptions" :payment-methods="paymentMethods" :initial-employee-id="payrollInitialEmployeeId" :initial-period="payrollMonthModel" :load-context="fetchDailyPayroll" :refresh-key="dailyPayrollRefreshKey" :loading="payrollSaving" @submit="submitDailyPayroll" @detail="openPayrollDetail" @print="printPayrollSlip" />

        <PayrollDetailDialog v-model:visible="payrollDetailVisible" :initial-cancel="payrollCancelMode" @cancel-payment="cancelPayroll" :payment="selectedPayroll" :payment-methods="paymentMethods" :loading="payrollDetailSaving" @submit="submitPayrollEdit" @print="printPayrollSlip" />

        <LeaveFormDialog v-model:visible="leaveDialogVisible" :mode="leaveMode" :leave="selectedLeave" :employees="employeeOptions" :loading="leaveSaving" @submit="submitLeave" />

        <AppDialog
            v-model:visible="showTrashDialog"
            title="Corbeille des employes"
            subtitle="Restaurez un employe supprime par erreur"
            icon="pi pi-trash"
            icon-tone="neutral"
            size="xl"
            maximizable
            :show-footer="false"
        >
            <div class="space-y-4">
                <div class="w-full sm:max-w-md">
                    <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Rechercher dans la corbeille</label>
                    <InputText v-model="trashSearch" placeholder="Nom, prenom, telephone..." class="w-full" />
                </div>

                <DataTable
                    :value="trashEmployees"
                    dataKey="id"
                    :loading="trashLoading"
                    paginator
                    lazy
                    :rows="trashRows"
                    :rowsPerPageOptions="[5, 10, 20]"
                    :first="trashFirst"
                    :totalRecords="trashTotalRecords"
                    class="rounded-xl border border-surface-200 dark:border-surface-700"
                    @page="handleTrashPage"
                >
                    <Column field="fullname" header="Employe">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-semibold text-surface-900 dark:text-surface-100">{{ data.fullname || `${data.prenom || ''} ${data.nom || ''}`.trim() }}</span>
                                <span class="text-xs text-surface-500">{{ data.telephone || '—' }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column field="type" header="Type">
                        <template #body="{ data }">
                            <Tag :value="formatEmployeeTypeLabel(data.type) || '-'" severity="info" />
                        </template>
                    </Column>
                    <Column field="deletedAt" header="Supprime le">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-700 dark:text-surface-300">{{ formatDate(data.deletedAt) }}</span>
                        </template>
                    </Column>
                    <Column header="Actions" :style="{ width: '180px' }">
                        <template #body="{ data }">
                            <Button label="Restaurer" icon="pi pi-replay" severity="success" text :loading="restoringEmployeeId === data.id" @click="restoreEmployeeFromTrash(data)" />
                        </template>
                    </Column>
                    <template #empty>
                        <div class="py-10 text-center text-surface-500">Aucun employe dans la corbeille.</div>
                    </template>
                </DataTable>
            </div>
        </AppDialog>
    </PageShell>
</template>

<style scoped>
.p-tabpanels {
    margin-top: var(--page-content-gap);
    background: transparent !important;
    padding: 0 !important;
}
</style>
