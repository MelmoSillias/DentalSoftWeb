import { apiPrefix } from '@/config';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const formatPerson = (p = {}) => {
    const label = `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom || '';
    return { ...p, label };
};

export const fetchMedecins = async (token) => {
    const res = await axios.get(`${apiPrefix}/medecins`, { headers: authHeaders(token) });
    const payload = Array.isArray(res.data) ? res.data : [];
    return payload.map(formatPerson);
};

export const fetchInfirmiers = async (token) => {
    const res = await axios.get(`${apiPrefix}/infirmiers`, { headers: authHeaders(token) });
    const payload = Array.isArray(res.data) ? res.data : [];
    return payload.map(formatPerson);
};
