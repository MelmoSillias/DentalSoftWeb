import { ref } from 'vue';
import { apiPrefix } from '@/config';
import { useAuthStore } from '@/stores/auth';
import http from '@/service/http';

function toIsoDate(value) {
    if (!value) return null;
    if (typeof value === 'string') return value;
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export function useDashboards() {
    const auth = useAuthStore();
    const loading = ref(false);
    const error = ref(null);

    const cards = ref(null);
    const carousels = ref(null);
    const tabs = ref(null);

    function hasToken() {
        return Boolean(auth?.token || localStorage.getItem('token'));
    }

    function buildAuthHeaders() {
        const token = auth?.token || localStorage.getItem('token');
        const headers = {};
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    function buildParams(params = {}) {
        const query = {};
        if (params.date) query.date = params.date;
        if (params.from) query.from = params.from;
        if (params.to) query.to = params.to;
        return query;
    }

    async function fetchJson(path, params = {}) {
        if (!hasToken()) {
            return null;
        }

        const url = new URL(`${apiPrefix}${path}`);
        const query = buildParams(params);
        Object.entries(query).forEach(([key, value]) => {
            if (!value) return;
            url.searchParams.append(key, value);
        });

        try {
            const response = await http.get(url.toString(), {
                headers: buildAuthHeaders()
            });
            return response.data;
        } catch (err) {
            const status = err?.response?.status;
            if (status === 401 || !hasToken()) {
                return null;
            }

            const body = err?.response?.data;
            const message = body?.message || body?.error || err?.message || `Erreur ${status || 'inconnue'}`;
            throw new Error(message);
        }
    }

    async function fetchCards(role, params = {}) {
        return fetchJson(`/dashboard/${role}/cards`, params);
    }

    async function fetchCarousels(role, params = {}) {
        return fetchJson(`/dashboard/${role}/carousels`, params);
    }

    async function fetchTabs(role, params = {}) {
        return fetchJson(`/dashboard/${role}/tabs`, params);
    }

    async function fetchDashboard(role, params = {}) {
        if (!hasToken()) {
            cards.value = {};
            carousels.value = {};
            tabs.value = {};
            error.value = null;
            return { cards: cards.value, carousels: carousels.value, tabs: tabs.value };
        }

        loading.value = true;
        error.value = null;
        try {
            const [cardsData, carouselsData, tabsData] = await Promise.all([fetchCards(role, params), fetchCarousels(role, params), fetchTabs(role, params)]);
            cards.value = cardsData || {};
            carousels.value = carouselsData || {};
            tabs.value = tabsData || {};
            return { cards: cards.value, carousels: carousels.value, tabs: tabs.value };
        } catch (err) {
            if (!hasToken()) {
                cards.value = {};
                carousels.value = {};
                tabs.value = {};
                error.value = null;
                return { cards: cards.value, carousels: carousels.value, tabs: tabs.value };
            }

            error.value = err;
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        error,
        cards,
        carousels,
        tabs,
        toIsoDate,
        fetchCards,
        fetchCarousels,
        fetchTabs,
        fetchDashboard
    };
}
