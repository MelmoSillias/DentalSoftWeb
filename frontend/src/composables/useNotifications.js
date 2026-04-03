import { ref } from 'vue';
import { isAdminTourMockEnabled, sendNotificationTourMock } from '@/services/adminTourMock';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

const loading = ref(false);
const error = ref(null);

export function useNotifications() {
    const auth = useAuthStore();

    function buildAuthHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function sendNotification(payload) {
        loading.value = true;
        error.value = null;
        try {
            if (isAdminTourMockEnabled()) {
                return sendNotificationTourMock(payload);
            }

            const response = await http.post(`${apiPrefix}/admin/notifications/send`, payload, {
                headers: buildAuthHeaders(true)
            });
            return response.data;
        } catch (err) {
            error.value = err.message || String(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        error,
        sendNotification
    };
}
