import { ref } from 'vue';
import { fetchUsersTourMock, isAdminTourMockEnabled } from '@/services/adminTourMock';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

const users = ref([]);
const availableEmployees = ref([]);
const availablePatients = ref([]);
const totalRecords = ref(0);
const loading = ref(false);
const error = ref(null);

export function useUsers() {
    const auth = useAuthStore();

    function buildAuthHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function fetchUsers({ page = 0, rows = 1000, search = '' } = {}) {
        loading.value = true;
        error.value = null;
        try {
            if (isAdminTourMockEnabled()) {
                const data = fetchUsersTourMock();
                const list = Array.isArray(data.data) ? data.data : [];
                users.value = list;
                totalRecords.value = data.recordsFiltered ?? data.recordsTotal ?? list.length;
                return;
            }

            const params = new URLSearchParams();
            params.append('start', String(page * rows));
            params.append('length', String(rows));
            if (search) params.append('search[value]', search);

            const response = await http.get(`${apiPrefix}/users?${params.toString()}`, {
                headers: buildAuthHeaders()
            });
            const data = response.data;
            const list = Array.isArray(data) ? data : Array.isArray(data.data) ? data.data : [];
            users.value = list;
            totalRecords.value = data.recordsFiltered ?? data.recordsTotal ?? list.length;
        } catch (err) {
            error.value = err.message;
        } finally {
            loading.value = false;
        }
    }

    async function addUser(payload) {
        try {
            const response = await http.post(`${apiPrefix}/users`, payload, {
                headers: buildAuthHeaders(true)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function updateUser(id, payload) {
        try {
            const response = await http.put(`${apiPrefix}/users/${id}`, payload, {
                headers: buildAuthHeaders(true)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function resetPassword(id, password) {
        try {
            const response = await http.post(
                `${apiPrefix}/users/${id}/reset-password`,
                { password },
                { headers: buildAuthHeaders(true) }
            );
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function deleteUser(id) {
        try {
            const response = await http.delete(`${apiPrefix}/users/${id}`, {
                headers: buildAuthHeaders(true),
                data: { user_id: id }
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function toggleUserStatus(id, action) {
        try {
            const response = await http.patch(
                `${apiPrefix}/users/${id}/toggle/${action}`,
                {},
                { headers: buildAuthHeaders(true) }
            );
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function fetchUserAssociations() {
        loading.value = true;
        error.value = null;
        try {
            const response = await http.get(`${apiPrefix}/users/associations`, {
                headers: buildAuthHeaders()
            });
            const data = response.data || {};
            availableEmployees.value = Array.isArray(data.employees) ? data.employees : [];
            availablePatients.value = Array.isArray(data.patients) ? data.patients : [];
            return data;
        } catch (err) {
            error.value = err.message;
            availableEmployees.value = [];
            availablePatients.value = [];
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchUserDevices(userId) {
        try {
            const response = await http.get(`${apiPrefix}/users/${userId}/devices`, {
                headers: buildAuthHeaders()
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function approveUserDevice(userId, deviceId) {
        try {
            const response = await http.post(`${apiPrefix}/users/${userId}/devices/${deviceId}/approve`, {}, {
                headers: buildAuthHeaders(true)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function rejectUserDevice(userId, deviceId) {
        try {
            const response = await http.post(`${apiPrefix}/users/${userId}/devices/${deviceId}/reject`, {}, {
                headers: buildAuthHeaders(true)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    async function deleteUserDevice(userId, deviceId) {
        try {
            const response = await http.delete(`${apiPrefix}/users/${userId}/devices/${deviceId}`, {
                headers: buildAuthHeaders(true)
            });
            return response.data;
        } catch (err) {
            error.value = err.message;
            throw err;
        }
    }

    return {
        users,
        availableEmployees,
        availablePatients,
        totalRecords,
        loading,
        error,
        fetchUsers,
        fetchUserAssociations,
        fetchUserDevices,
        addUser,
        updateUser,
        resetPassword,
        deleteUser,
        toggleUserStatus,
        approveUserDevice,
        rejectUserDevice,
        deleteUserDevice
    };
}
