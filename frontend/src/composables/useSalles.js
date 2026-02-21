import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

export function useSalles() {
    const salles = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const selectedSalle = ref(null);

    const auth = useAuthStore();

    function getHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function fetchSalles() {
        loading.value = true;
        try {
            const res = await http.get(`${apiPrefix}/salles`, {
                headers: getHeaders(false)
            });
            const payload = res.data;
            const data = Array.isArray(payload) ? payload : [];
            salles.value = data.map((s) => ({
                ...s,
                label: s.nom || s.name || ''
            }));
        } catch (e) {
            error.value = e;
        } finally {
            loading.value = false;
        }
    }

    async function addSalle(data) {
        loading.value = true;
        try {
            const res = await http.post(`${apiPrefix}/salles`, data, {
                headers: getHeaders(true)
            });
            const Resdata = res.data;
            if (Resdata?.salle) {
                salles.value = [...salles.value, Resdata.salle];
            }
            return Resdata.salle;
        } catch (e) {
            error.value = e;
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function editSalle(id, data) {
        loading.value = true;
        try {
            const res = await http.put(`${apiPrefix}/salles/${id}`, data, {
                headers: getHeaders(true)
            });
            const Resdata = res.data;
            const idx = salles.value.findIndex((s) => s.id === id);
            if (idx !== -1 && Resdata?.salle) salles.value[idx] = Resdata.salle;
            return Resdata.salle;
        } catch (e) {
            error.value = e;
            throw e;
        } finally {
            loading.value = false;
        }
    }

    async function deleteSalle(id) {
        loading.value = true;
        try {
            await http.delete(`${apiPrefix}/salles/${id}`, {
                headers: getHeaders(false)
            });
            salles.value = salles.value.filter((s) => s.id !== id);
        } catch (e) {
            error.value = e;
            throw e;
        } finally {
            loading.value = false;
        }
    }

    return {
        salles,
        loading,
        error,
        selectedSalle,
        fetchSalles,
        addSalle,
        editSalle,
        deleteSalle
    };
}
