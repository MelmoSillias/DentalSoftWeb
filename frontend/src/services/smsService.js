import { apiPrefix } from '@/config';
import http from '@/service/http';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchSmsSettings = async (token) => {
    const res = await http.get(`${apiPrefix}/sms/settings`, { headers: authHeaders(token) });
    return res.data;
};

export const saveSmsSettings = async (payload, token) => {
    const res = await http.put(`${apiPrefix}/sms/settings`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const testSmsConnection = async (token) => {
    const res = await http.post(`${apiPrefix}/sms/test-connection`, {}, {
        headers: authHeaders(token),
        validateStatus: () => true
    });
    return res.data;
};

export const sendSmsTest = async (payload, token) => {
    const res = await http.post(`${apiPrefix}/sms/test-send`, payload, {
        headers: authHeaders(token),
        validateStatus: () => true
    });
    return res.data;
};

export const fetchSmsStats = async (token, { from = null, to = null } = {}) => {
    const params = {};
    if (from) params.from = from;
    if (to) params.to = to;

    const res = await http.get(`${apiPrefix}/sms/stats`, {
        headers: authHeaders(token),
        params
    });
    return res.data;
};

export const fetchSmsProviderOverview = async (token) => {
    const res = await http.get(`${apiPrefix}/sms/provider-overview`, {
        headers: authHeaders(token),
        validateStatus: () => true
    });
    return res.data;
};

export const fetchSmsLogs = async ({ limit = 50, offset = 0 } = {}, token) => {
    const res = await http.get(`${apiPrefix}/sms/logs`, { headers: authHeaders(token), params: { limit, offset } });
    return Array.isArray(res.data) ? res.data : [];
};

export const fetchSmsQueue = async ({ limit = 100, offset = 0, status = null } = {}, token) => {
    const params = { limit, offset };
    if (status) {
        params.status = status;
    }

    const res = await http.get(`${apiPrefix}/sms/queue`, { headers: authHeaders(token), params });
    return Array.isArray(res.data) ? res.data : [];
};

export const fetchSmsQueueDetails = async (queueId, token) => {
    const res = await http.get(`${apiPrefix}/sms/queue/${queueId}/details`, { headers: authHeaders(token) });
    return res.data;
};

export const updateSmsQueueItem = async (queueId, payload, token) => {
    const res = await http.patch(`${apiPrefix}/sms/queue/${queueId}`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const fetchSmsTemplates = async (token) => {
    const res = await http.get(`${apiPrefix}/sms/templates`, { headers: authHeaders(token) });
    return Array.isArray(res.data) ? res.data : [];
};

export const saveSmsTemplates = async (templates, token) => {
    const res = await http.put(`${apiPrefix}/sms/templates`, { templates }, { headers: authHeaders(token) });
    return res.data;
};

export const previewSmsTemplate = async (payload, token) => {
    const res = await http.post(`${apiPrefix}/sms/templates/preview`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendManualSms = async (payload, token) => {
    const res = await http.post(`${apiPrefix}/sms/send/manual`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const processSmsQueue = async (payload = { async: true }, token) => {
    const res = await http.post(`${apiPrefix}/sms/queue/process`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendAppointmentReminderSms = async (rdvId, payload = {}, token) => {
    const res = await http.post(`${apiPrefix}/sms/appointments/${rdvId}/send-reminder`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const scheduleAppointmentReminderSms = async (rdvId, payload = {}, token) => {
    const res = await http.post(`${apiPrefix}/sms/appointments/${rdvId}/schedule-reminder`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendInvoiceSms = async (invoiceId, payload = {}, token) => {
    const res = await http.post(`${apiPrefix}/sms/invoices/${invoiceId}/send`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendReceiptSms = async (receiptId, payload = {}, token) => {
    const res = await http.post(`${apiPrefix}/sms/receipts/${receiptId}/send`, payload, { headers: authHeaders(token) });
    return res.data;
};
