import { apiPrefix } from '@/config';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchSmsSettings = async (token) => {
    const res = await axios.get(`${apiPrefix}/sms/settings`, { headers: authHeaders(token) });
    return res.data;
};

export const saveSmsSettings = async (payload, token) => {
    const res = await axios.put(`${apiPrefix}/sms/settings`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const testSmsConnection = async (token) => {
    const res = await axios.post(`${apiPrefix}/sms/test-connection`, {}, {
        headers: authHeaders(token),
        validateStatus: () => true
    });
    return res.data;
};

export const sendSmsTest = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/sms/test-send`, payload, {
        headers: authHeaders(token),
        validateStatus: () => true
    });
    return res.data;
};

export const fetchSmsStats = async (token) => {
    const res = await axios.get(`${apiPrefix}/sms/stats`, { headers: authHeaders(token) });
    return res.data;
};

export const fetchSmsLogs = async ({ limit = 50, offset = 0 } = {}, token) => {
    const res = await axios.get(`${apiPrefix}/sms/logs`, { headers: authHeaders(token), params: { limit, offset } });
    return Array.isArray(res.data) ? res.data : [];
};

export const fetchSmsTemplates = async (token) => {
    const res = await axios.get(`${apiPrefix}/sms/templates`, { headers: authHeaders(token) });
    return Array.isArray(res.data) ? res.data : [];
};

export const saveSmsTemplates = async (templates, token) => {
    const res = await axios.put(`${apiPrefix}/sms/templates`, { templates }, { headers: authHeaders(token) });
    return res.data;
};

export const previewSmsTemplate = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/sms/templates/preview`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendManualSms = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/sms/send/manual`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const processSmsQueue = async (payload = { async: true }, token) => {
    const res = await axios.post(`${apiPrefix}/sms/queue/process`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendAppointmentReminderSms = async (rdvId, payload = {}, token) => {
    const res = await axios.post(`${apiPrefix}/sms/appointments/${rdvId}/send-reminder`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const scheduleAppointmentReminderSms = async (rdvId, payload = {}, token) => {
    const res = await axios.post(`${apiPrefix}/sms/appointments/${rdvId}/schedule-reminder`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendInvoiceSms = async (invoiceId, payload = {}, token) => {
    const res = await axios.post(`${apiPrefix}/sms/invoices/${invoiceId}/send`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const sendReceiptSms = async (receiptId, payload = {}, token) => {
    const res = await axios.post(`${apiPrefix}/sms/receipts/${receiptId}/send`, payload, { headers: authHeaders(token) });
    return res.data;
};
