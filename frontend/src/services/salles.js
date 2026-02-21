import { apiPrefix } from '@/config';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchSalles = async (token) => {
    const res = await axios.get(`${apiPrefix}/salles`, { headers: authHeaders(token) });
    const payload = Array.isArray(res.data) ? res.data : [];
    return payload.map((s) => ({ ...s, label: s.nom || s.name || '' }));
};
