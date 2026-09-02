import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { apiPrefix } from '@/config';
import http from '@/service/http';

export function useEvents() {
    const events = ref([]);
    const loading = ref(false);
    const error = ref(null);
    const auth = useAuthStore();

    function getHeaders(includeJson = false) {
        const token = auth?.token || localStorage.getItem('token') || localStorage.getItem('authToken');
        const headers = {};
        if (includeJson) headers['Content-Type'] = 'application/json';
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    async function fetchEvents() {
        loading.value = true;
        error.value = null;
        try {
            const res = await http.get(`${apiPrefix}/events`, { headers: getHeaders(false) });
            const data = Array.isArray(res.data) ? res.data : [];
            // Accepts array of events in API shape
            events.value = data.map((e) => ({
                id: e.id,
                title: e.title,
                start: e.beginAt,
                end: e.endAt || null,
                description: e.description,
                statut: e.statut ?? 0
            }));
        } catch (err) {
            error.value = err;
        } finally {
            loading.value = false;
        }
    }

    async function createEvent(payload) {
        const res = await http.post(`${apiPrefix}/events`, payload, {
            headers: getHeaders(true)
        });
        return res.data;
    }

    async function deleteEvent(id) {
        const res = await http.delete(`${apiPrefix}/events/${id}`, {
            headers: getHeaders(false)
        });
        return res.data;
    }

    async function validateEvent(id) {
        const res = await http.post(`${apiPrefix}/events/${id}/validate`, {}, { headers: getHeaders(false) });
        return res.data;
    }

    return {
        events,
        loading,
        error,
        fetchEvents,
        createEvent,
        deleteEvent,
        validateEvent
    };
}
