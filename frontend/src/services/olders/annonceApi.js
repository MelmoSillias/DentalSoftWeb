import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchAnnonces = async (filters = {}, token) => {
    const res = await axios.get(`${apiPrefix}/annonces`, { params: filters, headers: authHeaders(token) });
    return res.data || [];
};

const buildFormData = (payload) => {
    const fd = new FormData();
    Object.entries(payload || {}).forEach(([k, v]) => {
        if (v === undefined || v === null) return;
        fd.append(k, v);
    });
    return fd;
};

export const createAnnonce = async (payload, documentFile, token) => {
    const fd = buildFormData(payload);
    if (documentFile) fd.append('document', documentFile);
    const res = await axios.post(`${apiPrefix}/annonces`, fd, { headers: { ...authHeaders(token), 'Content-Type': 'multipart/form-data' } });
    return res.data;
};

export const updateAnnonce = async (id, payload, documentFile, token) => {
    const fd = buildFormData(payload);
    if (documentFile) fd.append('document', documentFile);
    const res = await axios.put(`${apiPrefix}/annonces/${id}`, fd, { headers: { ...authHeaders(token), 'Content-Type': 'multipart/form-data' } });
    return res.data;
};

export const deleteAnnonce = async (id, token) => {
    const res = await axios.delete(`${apiPrefix}/annonces/${id}`, { headers: authHeaders(token) });
    return res.data;
};
