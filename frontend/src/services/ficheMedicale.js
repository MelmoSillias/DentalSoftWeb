import { apiPrefix } from '@/config';
import http, { UPLOAD_REQUEST_TIMEOUT_MS } from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const loadFicheMedicale = async (ficheId, token) => {
    const res = await axios.get(`${apiPrefix}/fiches-medicales/${ficheId}/json`, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveEntretien = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches-medicales/${ficheId}/entretien`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveExamens = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches-medicales/${ficheId}/examens`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveBilans = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches-medicales/${ficheId}/bilans`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const savePlanTraitement = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches-medicales/${ficheId}/plan-traitement`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveDocuments = async (ficheId, payload, files, token, options = {}) => {
    const formData = new FormData();
    if (payload) formData.append('data', JSON.stringify(payload));
    (files || []).forEach((docFiles, index) => {
        (docFiles || []).forEach((file) => {
            if (file) formData.append(`documentsFiles[${index}][]`, file);
        });
    });

    const res = await axios.post(`${apiPrefix}/fiches-medicales/${ficheId}/documents`, formData, {
        headers: {
            ...authHeaders(token),
            'Content-Type': 'multipart/form-data'
        },
        timeout: UPLOAD_REQUEST_TIMEOUT_MS,
        onUploadProgress: (event) => {
            if (typeof options.onUploadProgress !== 'function') return;
            const total = event.total || 0;
            const loaded = event.loaded || 0;
            const percent = total > 0 ? Math.min(100, Math.round((loaded * 100) / total)) : 0;
            options.onUploadProgress({ percent, loaded, total });
        }
    });
    return res.data;
};

export const saveDevis = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches-medicales/${ficheId}/devis`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};
