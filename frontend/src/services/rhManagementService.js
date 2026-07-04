import { apiPrefix } from '@/config';
import http from '@/service/http';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});
const withHeaders = (token) => ({ headers: authHeaders(token) });

export const fetchPayrolls = async ({ start = 0, length = 10, employeeId = null, month = null, year = null }, token) => {
    const params = { start, length };
    if (employeeId) params.employeeId = employeeId;
    if (month) params.month = month;
    if (year) params.year = year;

    const res = await http.get(`${apiPrefix}/payrolls`, { params, ...withHeaders(token) });
    return res.data || { data: [], recordsFiltered: 0, recordsTotal: 0 };
};

export const fetchPayrollContext = async (employeeId, { month, year, day = null }, token) => {
    const params = { month, year };
    if (day) params.day = day;

    const res = await http.get(`${apiPrefix}/payrolls/context/${employeeId}`, {
        params,
        ...withHeaders(token)
    });
    return res.data;
};

export const fetchPayrollPaymentMethods = async (token) => {
    const res = await http.get(`${apiPrefix}/payrolls/payment-methods`, withHeaders(token));
    return Array.isArray(res.data) ? res.data : [];
};

export const fetchPayroll = async (id, token) => {
    const res = await http.get(`${apiPrefix}/payrolls/${id}`, withHeaders(token));
    return res.data;
};

export const createPayroll = async (payload, token) => {
    const res = await http.post(`${apiPrefix}/payrolls`, payload, withHeaders(token));
    return res.data;
};

export const updatePayroll = async (id, payload, token) => {
    const res = await http.put(`${apiPrefix}/payrolls/${id}`, payload, withHeaders(token));
    return res.data;
};

export const deletePayroll = async (id, token) => {
    const res = await http.delete(`${apiPrefix}/payrolls/${id}`, withHeaders(token));
    return res.data;
};

export const fetchPayrollPrintPayload = async (id, token) => {
    const res = await http.get(`${apiPrefix}/payrolls/${id}/print`, withHeaders(token));
    return res.data;
};

export const fetchLeaves = async ({ employeId = null, type = '', start = '', end = '' } = {}, token) => {
    const params = {};
    if (employeId) params.employeId = employeId;
    if (type) params.type = type;
    if (start) params.start = start;
    if (end) params.end = end;

    const res = await http.get(`${apiPrefix}/conges`, { params, ...withHeaders(token) });
    return Array.isArray(res.data) ? res.data : [];
};

export const createLeave = async (payload, token) => {
    const res = await http.post(`${apiPrefix}/conges`, payload, withHeaders(token));
    return res.data;
};

export const updateLeave = async (id, payload, token) => {
    const res = await http.put(`${apiPrefix}/conges/${id}`, payload, withHeaders(token));
    return res.data;
};

export const deleteLeave = async (id, token) => {
    const res = await http.delete(`${apiPrefix}/conges/${id}`, withHeaders(token));
    return res.data;
};
