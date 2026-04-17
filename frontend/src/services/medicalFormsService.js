import { apiPrefix } from '@/config';
import http from '@/service/http';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchMedicalForms = async (token) => {
    const res = await http.get(`${apiPrefix}/settings/medical-forms`, { headers: authHeaders(token) });
    return res.data;
};

export const fetchMedicalForm = async (id, token) => {
    const res = await http.get(`${apiPrefix}/settings/medical-forms/${id}`, { headers: authHeaders(token) });
    return res.data;
};

export const createMedicalForm = async (payload, token) => {
    const res = await http.post(`${apiPrefix}/settings/medical-forms`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const updateMedicalForm = async (id, payload, token) => {
    const res = await http.put(`${apiPrefix}/settings/medical-forms/${id}`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const duplicateMedicalForm = async (id, payload, token) => {
    const res = await http.post(`${apiPrefix}/settings/medical-forms/${id}/duplicate`, payload, { headers: authHeaders(token) });
    return res.data;
};