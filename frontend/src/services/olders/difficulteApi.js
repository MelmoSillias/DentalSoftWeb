import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const headers = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchDifficultes = async (filters = {}, token) => {
    const res = await axios.get(`${apiPrefix}/difficultes`, { params: filters, headers: headers(token) });
    return res.data || [];
};

export const createDifficulte = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/difficultes`, payload, { headers: headers(token) });
    return res.data;
};

export const updateDifficulte = async (id, payload, token) => {
    const res = await axios.put(`${apiPrefix}/difficultes/${id}`, payload, { headers: headers(token) });
    return res.data;
};

export const deleteDifficulte = async (id, token) => {
    const res = await axios.delete(`${apiPrefix}/difficultes/${id}`, { headers: headers(token) });
    return res.data;
};
