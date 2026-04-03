import { apiPrefix } from '@/config';
import { fetchTourMockSalles, isConsultationsTourMockEnabled } from '@/services/consultationsTourMock';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchSalles = async (token) => {
    if (isConsultationsTourMockEnabled()) {
        return fetchTourMockSalles();
    }

    const res = await axios.get(`${apiPrefix}/salles`, { headers: authHeaders(token) });
    const payload = Array.isArray(res.data) ? res.data : [];
    return payload.map((s) => ({ ...s, label: s.nom || s.name || '' }));
};
