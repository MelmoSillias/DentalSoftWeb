import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchComptes = async (token) => {
    const res = await axios.get(`${apiPrefix}/finances/comptes`, { headers: authHeaders(token) });
    return res.data || [];
};

export const createCompte = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/finances/comptes`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const updateCompte = async (id, payload, token) => {
    const res = await axios.put(`${apiPrefix}/finances/comptes/${id}`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const deleteCompte = async (id, token) => {
    const res = await axios.delete(`${apiPrefix}/finances/comptes/${id}`, { headers: authHeaders(token) });
    return res.data;
};

export const fetchTransactions = async (filters, token) => {
    const res = await axios.get(`${apiPrefix}/finances/transactions`, { params: filters, headers: authHeaders(token) });
    return res.data || [];
};

export const createTransaction = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/finances/transactions`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const updateTransaction = async (id, payload, token) => {
    const res = await axios.put(`${apiPrefix}/finances/transactions/${id}`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const deleteTransaction = async (id, token) => {
    const res = await axios.delete(`${apiPrefix}/finances/transactions/${id}`, { headers: authHeaders(token) });
    return res.data;
};

export const fetchFinanceSummary = async (filters, token) => {
    const res = await axios.get(`${apiPrefix}/finances/rapport`, { params: filters, headers: authHeaders(token) });
    return res.data || {};
};
