import { ref } from 'vue';
import {
    createFinancesPaymentMethodTourMock,
    createFinancesTransactionTourMock,
    deleteFinancesPaymentMethodTourMock,
    fetchFinancesChartTourMock,
    fetchFinancesPaymentMethodsTourMock,
    fetchFinancesTransactionsTourMock,
    isFinancesTourMockEnabled,
    rejectFinancesTransactionTourMock,
    toggleFinancesPaymentMethodTourMock,
    updateFinancesPaymentMethodTourMock,
    validateFinancesTransactionTourMock
} from '@/services/financesTourMock';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

const defaultChartState = () => ({
    year: new Date().getFullYear(),
    availableYears: [new Date().getFullYear()],
    months: [],
    datasetsComptes: [],
    barSoldeChart: {
        labels: [],
        entrees: [],
        depenses: [],
        soldes: [],
        colors: []
    },
    evolutionCapital: []
});

const defaultCrossTableState = () => ({
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
    type: 'revenue',
    typeLabel: 'Revenus',
    monthLabel: '',
    weeks: [],
    rows: [],
    columnTotals: [],
    grandTotal: 0,
    availableTypes: [
        { label: 'Revenus', value: 'revenue' },
        { label: 'Dépenses', value: 'expense' }
    ],
    transactionMotifs: {
        revenue: ['Paiement patient', 'Remboursement assurance', 'Vente produit', 'Autre'],
        expense: ['Achat matériel', 'Frais généraux', 'Paiement salaire', 'Maintenance', 'Autre']
    }
});

const buildMockCrossTable = ({ year, month, type }) => {
    const safeYear = Number(year || new Date().getFullYear());
    const safeMonth = Number(month || new Date().getMonth() + 1);
    const monthDate = new Date(safeYear, safeMonth - 1, 1);
    const lastDay = new Date(safeYear, safeMonth, 0).getDate();
    const weeksCount = Math.ceil(lastDay / 7);
    const weeks = Array.from({ length: weeksCount }, (_, index) => {
        const startDay = (index * 7) + 1;
        const endDay = Math.min((index + 1) * 7, lastDay);
        return {
            index: index + 1,
            label: `Semaine ${index + 1}`,
            startDate: `${safeYear}-${String(safeMonth).padStart(2, '0')}-${String(startDay).padStart(2, '0')}`,
            endDate: `${safeYear}-${String(safeMonth).padStart(2, '0')}-${String(endDay).padStart(2, '0')}`
        };
    });

    const labels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    const rows = labels.map((label, rowIndex) => {
        const values = Array.from({ length: weeksCount }, (_, columnIndex) => {
            const base = (rowIndex + 1) * (columnIndex + 2) * 2500;
            return type === 'expense' ? Math.round(base * 0.6) : base;
        });
        return {
            weekday: rowIndex + 1,
            label,
            values,
            total: values.reduce((sum, value) => sum + value, 0)
        };
    });

    const columnTotals = Array.from({ length: weeksCount }, (_, columnIndex) =>
        rows.reduce((sum, row) => sum + Number(row.values[columnIndex] || 0), 0)
    );

    return {
        ...defaultCrossTableState(),
        year: safeYear,
        month: safeMonth,
        type,
        typeLabel: type === 'expense' ? 'Dépenses' : 'Revenus',
        monthLabel: monthDate.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' }),
        weeks,
        rows,
        columnTotals,
        grandTotal: columnTotals.reduce((sum, value) => sum + value, 0)
    };
};

export function useFinances() {
    const auth = useAuthStore();

    const chartData = ref(defaultChartState());
    const crossTableData = ref(defaultCrossTableState());
    const fixedCharges = ref([]);
    const fixedChargesTotal = ref(0);
    const paymentMethods = ref([]);
    const assurances = ref([]);
    const transactions = ref([]);

    const loading = ref({
        charts: false,
        crossTable: false,
        fixedCharges: false,
        methods: false,
        assurances: false,
        transactions: false,
        action: false
    });
    const error = ref(null);

    const buildHeaders = (includeJson = false) => {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) {
            headers['Content-Type'] = 'application/json';
        }
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
        return headers;
    };

    const handleError = (err) => {
        const body = err?.response?.data;
        error.value = body?.message || body?.error || err?.message || err || 'Erreur inattendue.';
        throw err;
    };

    const fetchChartData = async (year = null) => {
        loading.value.charts = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                chartData.value = fetchFinancesChartTourMock(year);
                return chartData.value;
            }

            const params = year ? `?year=${encodeURIComponent(year)}` : '';
            const res = await http.get(`${apiPrefix}/finances/chart-data${params}`, { headers: buildHeaders(false) });
            const data = res.data;
            chartData.value = {
                ...defaultChartState(),
                ...(data || {})
            };
            return chartData.value;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.charts = false;
        }
    };

    const fetchPaymentMethods = async () => {
        loading.value.methods = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                paymentMethods.value = fetchFinancesPaymentMethodsTourMock().filter((item) => String(item?.type || '').toLowerCase() !== 'insurance');
                return paymentMethods.value;
            }

            const res = await http.get(`${apiPrefix}/payment-methods`, { headers: buildHeaders(false) });
            const data = res.data;
            paymentMethods.value = Array.isArray(data) ? data : [];
            return paymentMethods.value;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.methods = false;
        }
    };

    const fetchAssurances = async () => {
        loading.value.assurances = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                assurances.value = fetchFinancesPaymentMethodsTourMock()
                    .filter((item) => String(item?.type || '').toLowerCase() === 'insurance')
                    .map((item) => ({
                        id: item.id,
                        nom: item.libelle,
                        code: null,
                        actif: item.actif !== false,
                        notes: item.notes || null,
                        defaultRate: Number(item?.coverageRate ?? 0) || 0
                    }));
                return assurances.value;
            }

            const res = await http.get(`${apiPrefix}/assurances`, { headers: buildHeaders(false) });
            assurances.value = Array.isArray(res.data) ? res.data : [];
            return assurances.value;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.assurances = false;
        }
    };

    const createAssurance = async (payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                const nextId = (assurances.value || []).reduce((maxId, item) => {
                    const id = Number(item?.id || 0);
                    return id > maxId ? id : maxId;
                }, 0) + 1;

                const item = {
                    id: nextId,
                    nom: String(payload?.nom || '').trim(),
                    code: String(payload?.code || '').trim() || null,
                    actif: payload?.actif !== false,
                    notes: String(payload?.notes || '').trim() || null,
                    defaultRate: Number(payload?.defaultRate || 0) || 0
                };
                assurances.value = [...(assurances.value || []), item];
                return item;
            }

            throw new Error('La creation manuelle des assurances est desactivee.');
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const toggleAssurance = async (code) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                assurances.value = (assurances.value || []).map((item) => {
                    if ((item?.code || '') !== code) {
                        return item;
                    }

                    return { ...item, actif: item?.actif === false };
                });

                return assurances.value.find((item) => (item?.code || '') === code) || null;
            }

            const res = await http.patch(`${apiPrefix}/assurances/${encodeURIComponent(code)}/toggle`, {}, {
                headers: buildHeaders(true)
            });

            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const fetchFixedCharges = async () => {
        loading.value.fixedCharges = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                fixedCharges.value = [];
                fixedChargesTotal.value = 0;
                return { items: fixedCharges.value, total: fixedChargesTotal.value };
            }

            const res = await http.get(`${apiPrefix}/finances/fixed-charges`, { headers: buildHeaders(false) });
            fixedCharges.value = Array.isArray(res.data?.items) ? res.data.items : [];
            fixedChargesTotal.value = Number(res.data?.total || 0);
            return { items: fixedCharges.value, total: fixedChargesTotal.value };
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.fixedCharges = false;
        }
    };

    const createFixedCharge = async (payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            const res = await http.post(`${apiPrefix}/finances/fixed-charges`, {
                designation: payload?.designation || '',
                montant: Number(payload?.montant || 0)
            }, {
                headers: buildHeaders(true)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const updateFixedCharge = async (id, payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            const res = await http.put(`${apiPrefix}/finances/fixed-charges/${id}`, {
                designation: payload?.designation || '',
                montant: Number(payload?.montant || 0)
            }, {
                headers: buildHeaders(true)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const deleteFixedCharge = async (id) => {
        loading.value.action = true;
        error.value = null;
        try {
            const res = await http.delete(`${apiPrefix}/finances/fixed-charges/${id}`, {
                headers: buildHeaders(false)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const fetchCrossTable = async ({ year, month, type = 'revenue' } = {}) => {
        loading.value.crossTable = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                crossTableData.value = buildMockCrossTable({ year, month, type });
                return crossTableData.value;
            }

            const params = new URLSearchParams({
                year: String(year || new Date().getFullYear()),
                month: String(month || new Date().getMonth() + 1),
                type: type || 'revenue'
            });
            const res = await http.get(`${apiPrefix}/finances/cross-table?${params.toString()}`, {
                headers: buildHeaders(false)
            });
            crossTableData.value = {
                ...defaultCrossTableState(),
                ...(res.data || {})
            };
            return crossTableData.value;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.crossTable = false;
        }
    };

    const createPaymentMethod = async (payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return createFinancesPaymentMethodTourMock(payload);
            }

            const body = {
                nom: payload?.nom || payload?.libelle || '',
                libelle: payload?.libelle || payload?.nom || '',
                type: payload?.type || 'cash',
                notes: payload?.notes || null
            };
            const res = await http.post(`${apiPrefix}/payment-methods`, body, {
                headers: buildHeaders(true)
            });
            return res.data;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const updatePaymentMethod = async (id, payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return updateFinancesPaymentMethodTourMock(id, payload);
            }

            const body = {
                nom: payload?.nom || payload?.libelle || '',
                libelle: payload?.libelle || payload?.nom || '',
                type: payload?.type || null,
                notes: payload?.notes || null,
                actif: typeof payload?.actif === 'boolean' ? payload.actif : undefined
            };
            const res = await http.put(`${apiPrefix}/payment-methods/${id}`, body, {
                headers: buildHeaders(true)
            });
            return res.data;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const deletePaymentMethod = async (id) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return deleteFinancesPaymentMethodTourMock(id);
            }

            const res = await http.delete(`${apiPrefix}/payment-methods/${id}`, {
                headers: buildHeaders(false)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const togglePaymentMethod = async (id) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return toggleFinancesPaymentMethodTourMock(id);
            }

            const res = await http.patch(
                `${apiPrefix}/payment-methods/${id}/toggle`,
                {},
                { headers: buildHeaders(false) }
            );
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const fetchTransactionsRange = async ({ startDate, endDate }) => {
        loading.value.transactions = true;
        error.value = null;
        try {
            if (!startDate || !endDate) {
                throw new Error('La periode est requise.');
            }
            if (isFinancesTourMockEnabled()) {
                transactions.value = fetchFinancesTransactionsTourMock({ startDate, endDate });
                return transactions.value;
            }

            const params = new URLSearchParams({ startDate, endDate });
            const res = await http.get(`${apiPrefix}/transactions?${params.toString()}`, {
                headers: buildHeaders(false)
            });
            const data = res.data;
            transactions.value = Array.isArray(data) ? data : [];
            return transactions.value;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.transactions = false;
        }
    };

    const createTransaction = async (payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return createFinancesTransactionTourMock(payload);
            }

            const body = {
                type: payload?.type,
                montant: Number(payload?.montant || 0),
                description: payload?.description || '',
                motif: payload?.motif || '',
                date: payload?.date,
                modeId: payload?.modeId
            };
            const res = await http.post(`${apiPrefix}/transaction`, body, {
                headers: buildHeaders(true)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const transferInterCompte = async (payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            const body = {
                fromId: payload?.fromId,
                toId: payload?.toId,
                montant: Number(payload?.montant || 0),
                motif: payload?.motif || '',
                date: payload?.date
            };
            const res = await http.post(`${apiPrefix}/transactions/intercompte`, body, {
                headers: buildHeaders(true)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const validateTransaction = async (id, payload = {}) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return validateFinancesTransactionTourMock(id);
            }

            const res = await http.patch(`${apiPrefix}/transactions/${id}/validate`, payload, {
                headers: buildHeaders(true)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const rejectTransaction = async (id, payload = {}) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return rejectFinancesTransactionTourMock(id);
            }

            const res = await http.patch(`${apiPrefix}/transactions/${id}/reject`, payload, {
                headers: buildHeaders(true)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    const deleteTransaction = async (id) => {
        loading.value.action = true;
        error.value = null;
        try {
            if (isFinancesTourMockEnabled()) {
                return { success: true };
            }

            const res = await http.delete(`${apiPrefix}/transactions/${id}`, {
                headers: buildHeaders(false)
            });
            return res.data ?? null;
        } catch (err) {
            handleError(err);
        } finally {
            loading.value.action = false;
        }
    };

    return {
        chartData,
        crossTableData,
        fixedCharges,
        fixedChargesTotal,
        paymentMethods,
        assurances,
        transactions,
        loading,
        error,
        fetchChartData,
        fetchCrossTable,
        fetchFixedCharges,
        fetchPaymentMethods,
        fetchAssurances,
        createAssurance,
        toggleAssurance,
        createFixedCharge,
        createPaymentMethod,
        updateFixedCharge,
        updatePaymentMethod,
        deleteFixedCharge,
        deletePaymentMethod,
        togglePaymentMethod,
        fetchTransactionsRange,
        createTransaction,
        transferInterCompte,
        validateTransaction,
        rejectTransaction,
        deleteTransaction
    };
}
