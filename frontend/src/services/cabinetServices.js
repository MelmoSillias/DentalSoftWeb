import { apiPrefix } from '@/config';
import http from '@/service/http';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchCabinetServices = async (params = {}, token) => {
    const res = await http.get(`${apiPrefix}/services-cabinet`, {
        headers: authHeaders(token),
        params
    });
    return res.data?.data ?? res.data ?? [];
};

export const fetchCabinetService = async (serviceId, token) => {
    const res = await http.get(`${apiPrefix}/services-cabinet/${serviceId}`, { headers: authHeaders(token) });
    return res.data?.data ?? res.data;
};

export const createCabinetService = async (patientId, payload, token) => {
    const res = await http.post(`${apiPrefix}/patients/${patientId}/services-cabinet`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const updateCabinetService = async (serviceId, payload, token) => {
    const res = await http.put(`${apiPrefix}/services-cabinet/${serviceId}`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const cancelCabinetService = async (serviceId, token) => {
    const res = await http.post(`${apiPrefix}/services-cabinet/${serviceId}/cancel`, {}, { headers: authHeaders(token) });
    return res.data;
};

export const fetchCabinetFacture = async (factureId, token) => {
    const res = await http.get(`${apiPrefix}/factures-cabinet/${factureId}`, { headers: authHeaders(token) });
    return res.data;
};

export const payCabinetFacture = async (factureId, payload, token) => {
    const res = await http.post(`${apiPrefix}/factures-cabinet/${factureId}/pay`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const fetchCabinetInvoicePrintData = async (factureId, token) => {
    const res = await http.get(`${apiPrefix}/prints/factures-cabinet/${factureId}`, { headers: authHeaders(token) });
    return res.data;
};
