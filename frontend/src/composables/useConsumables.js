import { ref, computed } from 'vue';
import {
    addConsumableTourMock,
    addStockTourMock,
    deleteConsumableTourMock,
    editConsumableTourMock,
    fetchConsumablesTourMock,
    getConsumableTourMock,
    isAdminTourMockEnabled,
    withdrawStockTourMock
} from '@/services/adminTourMock';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config'; 
import http from '@/service/http';

const consumables = ref([]);
const loading = ref(false);
const error = ref(null);
const auth = useAuthStore();

export function useConsumables() {
    const normalizeConsumable = (item) => {
        if (!item || typeof item !== 'object') return null;

        const quantity = Number(item.quantity ?? item.quantite ?? 0);
        const lowValue = Number(item.lowValue ?? item.seuil ?? 0);

        return {
            ...item,
            quantity: Number.isFinite(quantity) ? quantity : 0,
            lowValue: Number.isFinite(lowValue) ? lowValue : 0
        };
    };

    const totalConsumables = computed(() => {
        return consumables.value.reduce((total, item) => {
            const price = Number(item?.price ?? 0);
            const quantity = Number(item?.quantity ?? 0);
            return total + (Number.isFinite(price) ? price : 0) * (Number.isFinite(quantity) ? quantity : 0);
        }, 0);
    });

    function getHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function fetchConsumables() {
        loading.value = true;
        error.value = null;
        try {
            if (isAdminTourMockEnabled()) {
                const rows = fetchConsumablesTourMock();
                consumables.value = rows.map(normalizeConsumable).filter(Boolean);
                return {
                    consumables,
                    loading,
                    error,
                    totalConsumables,
                    fetchConsumables
                };
            }

            const response = await http.get(`${apiPrefix}/consumables`, {
                headers: getHeaders()
            });
            const data = response.data;
            const rows = Array.isArray(data) ? data : [];
            consumables.value = rows.map(normalizeConsumable).filter(Boolean);
        } catch (err) {
            error.value = err.message;
        } finally {
            loading.value = false;
        }

        return {
            consumables,
            loading,
            error,
            totalConsumables,
            fetchConsumables
        };
    }

    async function addConsumable(consumable) {
        loading.value = true; 
        try {
            if (isAdminTourMockEnabled()) {
                const result = addConsumableTourMock(consumable);
                await fetchConsumables();
                return result;
            }

            const response = await http.post(`${apiPrefix}/consumables`, consumable, {
                headers: getHeaders(true)
            });
            void response.data;
            await fetchConsumables();
            return { ok: true };
        } finally {
            loading.value = false;
        }
    }

    async function editConsumable(id, updates) {
        loading.value = true;
        try {
            if (isAdminTourMockEnabled()) {
                const result = editConsumableTourMock(id, updates);
                await fetchConsumables();
                return result;
            }

            const response = await http.put(`${apiPrefix}/consumables/${id}`, updates, {
                headers: getHeaders(true)
            });
            const updated = response.data ?? null;
            await fetchConsumables();
            return { ok: true, data: updated };
        } finally {
            loading.value = false;
        }
    }


    async function addStock(consumableId, values) {
        try {
            if (isAdminTourMockEnabled()) {
                return addStockTourMock(consumableId, values);
            }

            const response = await http.post(`${apiPrefix}/consumables/${consumableId}/stock`, values, {
                headers: getHeaders(true)
            });
            const updated = response.data ?? null;
            const index = consumables.value.findIndex(c => c.id === consumableId);
            if (index !== -1) {
                const delta = Number(values?.quantite ?? 0);
                consumables.value[index].quantity += Number.isFinite(delta) ? delta : 0;
            }
            return { ok: true, data: updated };
        
        } catch (err) {
            error.value = err.message;
        } finally {
            loading.value = false;
        }
    }

    async function withdrawStock(consumableId, values) {
         const payload = {
            ...values,
            employe: values?.employe ?? values?.employee ?? null
        };
        try {
            if (isAdminTourMockEnabled()) {
                return withdrawStockTourMock(consumableId, payload);
            }

            const response = await http.post(`${apiPrefix}/consumables/${consumableId}/withdraw`, payload, {
                headers: getHeaders(true)
            });
            const updated = response.data ?? null;
            const index = consumables.value.findIndex(c => c.id === consumableId);
            if (index !== -1) {
                const delta = Number(values?.quantite ?? 0);
                consumables.value[index].quantity -= Number.isFinite(delta) ? delta : 0;
            }
            return { ok: true, data: updated };
            
        } catch (err) {
            error.value = err.message;
        } finally {
            loading.value = false;
        }
    }

    async function getConsumable(consumableId) {
        try {
            if (isAdminTourMockEnabled()) {
                return normalizeConsumable(getConsumableTourMock(consumableId));
            }

            const response = await http.get(`${apiPrefix}/consumables/${consumableId}`, {
                headers: getHeaders()
            });
            const data = response.data;
            return normalizeConsumable(data);
        } catch (err) {
            error.value = err.message;
        } finally {
            loading.value = false;
        }
    }

    async function deleteConsumable(consumableId, csrfToken) {
        try {
            if (isAdminTourMockEnabled()) {
                return deleteConsumableTourMock(consumableId);
            }

            const body = new URLSearchParams();
            if (csrfToken) body.append('_token', csrfToken);
            await http.delete(`${apiPrefix}/consumables/${consumableId}`, {
                headers: {
                    ...getHeaders(),
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                data: body
            });
            const index = consumables.value.findIndex(c => c.id === consumableId);
            if (index !== -1) consumables.value.splice(index, 1);
            return { ok: true };
        } catch (err) {
            error.value = err.message;
        } finally {
            loading.value = false;
        }
    }

    return {
        consumables,
        loading,
        error,
        totalConsumables,
        fetchConsumables,
        addConsumable,
        withdrawStock,
        addStock,
        editConsumable,
        getConsumable,
        deleteConsumable
    };
}
