import { ref } from 'vue';
import { createPayroll, deletePayroll, fetchDailyPayroll, fetchEmployeePayrolls, fetchPayroll, fetchPayrollContext, fetchPayrollPaymentMethods, fetchPayrollPrintPayload, fetchPayrolls, updatePayroll } from '@/services/rhManagementService';

const payrolls = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const contextLoading = ref(false);
const paymentContext = ref(null);
const paymentMethods = ref([]);
const error = ref(null);
let contextRequestId = 0;

const getToken = () => localStorage.getItem('token') || localStorage.getItem('authToken');

export function usePayrolls() {
    const fetchEmployees = (filters) => fetchEmployeePayrolls(filters, getToken());
    const fetchDaily = (employeeId, filters) => fetchDailyPayroll(employeeId, filters, getToken());
    const resetContext = () => {
        contextRequestId += 1;
        paymentContext.value = null;
        contextLoading.value = false;
        error.value = null;
    };
    const fetchAll = async ({ employeeId = null, month, year }) => {
        const rows = [];
        let total = 0;
        do {
            const result = await fetchPayrolls({ start: rows.length, length: 200, employeeId, month, year }, getToken());
            const batch = Array.isArray(result.data) ? result.data : [];
            total = result.recordsFiltered ?? result.recordsTotal ?? batch.length;
            if (!batch.length && rows.length < total) throw new Error('La liste des salaires est incomplète. Veuillez réessayer.');
            rows.push(...batch);
        } while (rows.length < total);
        return rows;
    };

    const fetchData = async ({ page = 0, rows = 10, employeeId = null, month = null, year = null } = {}) => {
        loading.value = true;
        error.value = null;
        try {
            if (rows === 0) {
                payrolls.value = await fetchAll({ employeeId, month, year });
                totalRecords.value = payrolls.value.length;
                return;
            }
            const data = await fetchPayrolls(
                {
                    start: page * rows,
                    length: rows,
                    employeeId,
                    month,
                    year
                },
                getToken()
            );

            payrolls.value = Array.isArray(data.data) ? data.data : [];
            totalRecords.value = data.recordsFiltered ?? data.recordsTotal ?? payrolls.value.length;
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors du chargement des paiements.';
            payrolls.value = [];
            totalRecords.value = 0;
        } finally {
            loading.value = false;
        }
    };

    const fetchPaymentMethods = async () => {
        try {
            const data = await fetchPayrollPaymentMethods(getToken());
            paymentMethods.value = data;
            return data;
        } catch {
            paymentMethods.value = [];
            return [];
        }
    };

    const fetchOne = async (id) => {
        error.value = null;
        try {
            return await fetchPayroll(id, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors du chargement du paiement.';
            throw err;
        }
    };

    const fetchContext = async (employeeId, { month, year, day = null } = {}) => {
        const requestId = ++contextRequestId;
        paymentContext.value = null;
        error.value = null;
        if (!employeeId || !month || !year) {
            contextLoading.value = false;
            return null;
        }

        contextLoading.value = true;
        error.value = null;
        try {
            const data = await fetchPayrollContext(employeeId, { month, year, day }, getToken());
            if (requestId !== contextRequestId) return null;
            paymentContext.value = data;
            return data;
        } catch (err) {
            if (requestId !== contextRequestId) return null;
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors du calcul du salaire.';
            paymentContext.value = null;
            return null;
        } finally {
            if (requestId === contextRequestId) contextLoading.value = false;
        }
    };

    const add = async (payload) => {
        error.value = null;
        try {
            return await createPayroll(payload, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors de la creation du paiement.';
            throw err;
        }
    };

    const edit = async (id, payload) => {
        error.value = null;
        try {
            return await updatePayroll(id, payload, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors de la mise a jour du paiement.';
            throw err;
        }
    };

    const remove = async (id, payload = {}) => {
        error.value = null;
        try {
            return await deletePayroll(id, getToken(), payload);
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors de la suppression.';
            throw err;
        }
    };

    const fetchPrintPayload = async (id) => {
        error.value = null;
        try {
            return await fetchPayrollPrintPayload(id, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors du chargement du bulletin de paie.';
            throw err;
        }
    };

    return {
        payrolls,
        totalRecords,
        loading,
        contextLoading,
        paymentContext,
        paymentMethods,
        error,
        fetchData,
        fetchAll,
        fetchPaymentMethods,
        fetchOne,
        fetchContext,
        fetchEmployees,
        fetchDaily,
        resetContext,
        add,
        edit,
        remove,
        fetchPrintPayload
    };
}
