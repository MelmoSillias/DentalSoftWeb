import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

const employee = ref(null);
const loading = ref(false);
const error = ref(null);
const auth = useAuthStore();

export function useEmployeeDetails() {
    function buildAuthHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function fetchEmployee(id) {
        if (!id) return null;
        loading.value = true;
        error.value = null;
        try {
            const response = await http.get(`${apiPrefix}/employee/${id}`, {
                headers: buildAuthHeaders()
            });
            employee.value = response.data || null;
            return employee.value;
        } catch (err) {
            error.value = err?.message || 'Erreur lors du chargement.';
            employee.value = null;
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function updateEmployee(id, formData) {
        if (!id) throw new Error('Identifiant manquant.');
        try {
            const response = await http.post(`${apiPrefix}/employees/${id}`, formData, {
                headers: buildAuthHeaders(false)
            });
            return response.data;
        } catch (err) {
            error.value = err?.message || 'Erreur de mise a jour.';
            throw err;
        }
    }

    return {
        employee,
        loading,
        error,
        fetchEmployee,
        updateEmployee
    };
}
