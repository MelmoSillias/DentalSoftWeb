import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchEmployes = async (filters = {}, token) => {
    const res = await axios.get(`${apiPrefix}/rh/employes`, { params: filters, headers: authHeaders(token) });
    return res.data || [];
};

export const fetchEmployeDetails = async (id, token) => {
    const res = await axios.get(`${apiPrefix}/rh/employes/${id}`, { headers: authHeaders(token) });
    return res.data;
};

export const createEmploye = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/rh/employes`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const updateEmploye = async (id, payload, token) => {
    const res = await axios.put(`${apiPrefix}/rh/employes/${id}`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const deleteEmploye = async (id, token) => {
    const res = await axios.delete(`${apiPrefix}/rh/employes/${id}`, { headers: authHeaders(token) });
    return res.data;
};

export const paySalaire = async (id, payload, token) => {
    const res = await axios.post(`${apiPrefix}/rh/employes/${id}/paiements`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const createUserForEmploye = async (id, payload, token) => {
    const res = await axios.post(`${apiPrefix}/rh/employes/${id}/user`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const toggleUserAccount = async (userId, action, token) => {
    const res = await axios.patch(`${apiPrefix}/users/${userId}/toggle/${action}`, {}, { headers: authHeaders(token) });
    return res.data;
};
