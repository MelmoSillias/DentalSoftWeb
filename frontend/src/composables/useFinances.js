import { ref } from 'vue';
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

export function useFinances() {
    const auth = useAuthStore();

    const chartData = ref(defaultChartState());
    const paymentMethods = ref([]);
    const transactions = ref([]);

    const loading = ref({
        charts: false,
        methods: false,
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

    const createPaymentMethod = async (payload) => {
        loading.value.action = true;
        error.value = null;
        try {
            const body = {
                nom: payload?.nom || payload?.libelle || '',
                libelle: payload?.libelle || payload?.nom || '',
                type: payload?.type || null,
                typeKey: payload?.typeKey || null,
                family: payload?.family || null,
                coverageRate: typeof payload?.coverageRate === 'number' ? payload.coverageRate : null,
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
            const body = {
                nom: payload?.nom || payload?.libelle || '',
                libelle: payload?.libelle || payload?.nom || '',
                type: payload?.type || null,
                typeKey: payload?.typeKey || null,
                family: payload?.family || null,
                coverageRate: typeof payload?.coverageRate === 'number' ? payload.coverageRate : null,
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
            const body = {
                type: payload?.type,
                montant: Number(payload?.montant || 0),
                description: payload?.description || '',
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

    const validateTransaction = async (id) => {
        loading.value.action = true;
        error.value = null;
        try {
            const res = await http.patch(`${apiPrefix}/transactions/${id}/validate`, {}, {
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

    return {
        chartData,
        paymentMethods,
        transactions,
        loading,
        error,
        fetchChartData,
        fetchPaymentMethods,
        createPaymentMethod,
        updatePaymentMethod,
        deletePaymentMethod,
        togglePaymentMethod,
        fetchTransactionsRange,
        createTransaction,
        transferInterCompte,
        validateTransaction,
        rejectTransaction
    };
}
