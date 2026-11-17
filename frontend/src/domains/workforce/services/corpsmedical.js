import { apiPrefix } from '@/config.js';
import { fetchTourMockInfirmiers, fetchTourMockMedecins, isConsultationsTourMockEnabled } from '@/domains/care-delivery/services/consultationsTourMock.js';
import http from '@/shared/api/http.js';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const formatPerson = (p = {}) => {
    const label = `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom || '';
    return { ...p, label };
};

export const fetchMedecins = async (token) => {
    if (isConsultationsTourMockEnabled()) {
        return fetchTourMockMedecins().map(formatPerson);
    }

    const res = await axios.get(`${apiPrefix}/medecins`, { headers: authHeaders(token) });
    const payload = Array.isArray(res.data) ? res.data : [];
    return payload.map(formatPerson);
};

export const fetchInfirmiers = async (token) => {
    if (isConsultationsTourMockEnabled()) {
        return fetchTourMockInfirmiers().map(formatPerson);
    }

    const res = await axios.get(`${apiPrefix}/infirmiers`, { headers: authHeaders(token) });
    const payload = Array.isArray(res.data) ? res.data : [];
    return payload.map(formatPerson);
};
