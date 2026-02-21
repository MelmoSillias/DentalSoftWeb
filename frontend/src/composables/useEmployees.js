import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

const employees = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const error = ref(null);
const auth = useAuthStore(); 

export function useEmployees() {
    function buildAuthHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function fetchEmployees({ page = 0, rows = 1000, search = '', type = null } = {}) {
        loading.value = true;
        error.value = null;
        try {
            const params = new URLSearchParams();
            params.append('start', String(page * rows));
            params.append('length', String(rows));
            if (search) params.append('search[value]', search);
            if (type) params.append('typeFilter', type);

            const response = await http.get(`${apiPrefix}/employees?${params.toString()}`, {
                headers: buildAuthHeaders()
            });
            const data = response.data;
            employees.value = Array.isArray(data.data) ? data.data : [];
            totalRecords.value = data.recordsFiltered ?? data.recordsTotal ?? employees.value.length;
        } catch (err) {
            error.value = err.message;
        }   
        finally {
            loading.value = false;
        }    
    }   

    async function fetchUserlessEmployee() {
        loading.value = true;
        error.value = null;
        try {
            const response = await http.get(`${apiPrefix}/employees/userless`, {
                headers: buildAuthHeaders()
            });
            const data = response.data;
            return Array.isArray(data) ? data : [];
        } catch (err) {
            error.value = err.message;
            return [];
        } finally {
            loading.value = false;
        }
    }

    async function addEmployee(formData) {
        try {
            const response = await http.post(`${apiPrefix}/employees`, formData, {
                headers: buildAuthHeaders(false)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function updateEmployee(id, formData) {
        try {
            const response = await http.post(`${apiPrefix}/employees/${id}`, formData, {
                headers: buildAuthHeaders(false)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function deleteEmployee(id) {
        try {
            const response = await http.delete(`${apiPrefix}/employees/${id}`, {
                headers: buildAuthHeaders(false)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    return {
        employees,
        totalRecords,
        loading,
        error,
        fetchEmployees,
        addEmployee,
        updateEmployee,
        deleteEmployee
    };
}