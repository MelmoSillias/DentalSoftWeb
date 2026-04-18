import { apiPrefix } from '@/config';
import { saveGeneralSettings } from '@/services/globalSettingsService';
import http from '@/service/http';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const normalizeCollectionResponse = (data) => {
    if (Array.isArray(data)) return data;
    if (Array.isArray(data?.items)) return data.items;
    if (Array.isArray(data?.data)) return data.data;
    return [];
};

export const fetchFormulaires = async (token) => {
    const res = await http.get(`${apiPrefix}/formulaires`, { headers: authHeaders(token) });
    return normalizeCollectionResponse(res.data);
};

export const fetchFormulaireById = async (id, token) => {
    const res = await http.get(`${apiPrefix}/formulaires/${id}`, { headers: authHeaders(token) });
    return res.data;
};

export const createFormulaire = async (payload, token) => {
    const res = await http.post(`${apiPrefix}/formulaires`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const updateFormulaire = async (id, payload, token) => {
    const res = await http.put(`${apiPrefix}/formulaires/${id}`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const deleteFormulaire = async (id, token) => {
    await http.delete(`${apiPrefix}/formulaires/${id}`, { headers: authHeaders(token) });
    return true;
};

export const duplicateFormulaire = async (id, label, token) => {
    const payload = label ? { label } : {};
    const res = await http.post(`${apiPrefix}/formulaires/${id}/duplicate`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const publishFormulaire = async (id, token) => {
    const res = await http.post(`${apiPrefix}/formulaires/${id}/publish`, {}, { headers: authHeaders(token) });
    return res.data;
};

export const fetchDefaultMedicalForm = async (token) => {
    const res = await http.get(`${apiPrefix}/formulaires/medical/default`, { headers: authHeaders(token) });
    return res.data;
};

export const setDefaultMedicalFormCode = async (code, token) => {
    return saveGeneralSettings({ medicalFormsDefaultCode: code }, token);
};
