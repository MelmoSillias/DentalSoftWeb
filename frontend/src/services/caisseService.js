import { apiPrefix } from '@/config';
import {
    fetchDevisDetailTourMock,
    fetchDevisTourMock,
    fetchFactureLinesTourMock,
    fetchPaymentMethodsTourMock,
    fetchPaymentsTourMock,
    isCaisseTourMockEnabled,
    payDevisTourMock,
    updateFactureLinesTourMock,
    validateEmptyDevisTourMock
} from '@/services/caisseTourMock';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const withHeaders = (token) => ({ headers: authHeaders(token) });

const fetchPrintBlob = async (url, token) => {
    const res = await axios.get(url, { responseType: 'blob', ...withHeaders(token) });
    return res.data;
};

export const fetchDevis = async ({ start, end, unpaidOnly = false }, token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchDevisTourMock({ start, end, unpaidOnly });
    }

    const url = unpaidOnly ? `${apiPrefix}/devis/unpaid` : `${apiPrefix}/devis`;
    const res = await axios.get(url, { params: { start, end }, ...withHeaders(token) });
    return res.data || [];
};

export const fetchPayments = async ({ start, end }, token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchPaymentsTourMock({ start, end: end === '' ? start : end });
    }

    const res = await axios.get(`${apiPrefix}/devis/payments`, { params: { start, end: end === "" ? start : end }, ...withHeaders(token) });
    return res.data || [];
};

export const fetchPaymentMethods = async (token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchPaymentMethodsTourMock();
    }

    const res = await axios.get(`${apiPrefix}/payment-methods`, withHeaders(token));
    return Array.isArray(res.data) ? res.data : [];
};

export const payDevis = async (devisId, payload = {}, token) => {
    if (isCaisseTourMockEnabled()) {
        return payDevisTourMock(devisId, payload);
    }

    const res = await axios.post(`${apiPrefix}/devis/${devisId}/pay`, payload, withHeaders(token));
    return res.data;
};

export const resetDevisPayments = async (devisId, token) => {
    if (isCaisseTourMockEnabled()) {
        return { success: true };
    }

    const res = await axios.delete(`${apiPrefix}/devis/${devisId}/payments/reset`, withHeaders(token));
    return res.data;
};

export const validateEmptyDevis = async (devisId, token) => {
    if (isCaisseTourMockEnabled()) {
        return validateEmptyDevisTourMock(devisId);
    }

    return payDevis(devisId, {}, token);
};

export const fetchFactureLines = async (consultationId, token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchFactureLinesTourMock(consultationId);
    }

    const res = await axios.get(`${apiPrefix}/consultations/${consultationId}/facture`, withHeaders(token));
    return Array.isArray(res.data) ? res.data : [];
};

export const updateFactureLines = async (consultationId, lignes, token) => {
    if (isCaisseTourMockEnabled()) {
        return updateFactureLinesTourMock(consultationId, lignes);
    }

    const res = await axios.put(`${apiPrefix}/consultations/${consultationId}/facture/update`, { lignes }, withHeaders(token));
    return res.data;
};

export const fetchDevisDetail = async (devisId, token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchDevisDetailTourMock(devisId);
    }

    const res = await axios.get(`${apiPrefix}/devis/${devisId}`, withHeaders(token));
    return res.data;
};

export const getPaymentPrint = (id, token) => fetchPrintBlob(`${apiPrefix}/payments/${id}/print`, token);
export const getReceiptPrint = (id, token) => fetchPrintBlob(`${apiPrefix}/receipts/${id}/print`, token);
export const getPaymentsRangePrint = ({ start, end }, token) => fetchPrintBlob(`${apiPrefix}/payments/print?start=${start}&end=${end}`, token);
export const getInvoicePrint = (devisId, token) => fetchPrintBlob(`${apiPrefix}/invoices/${devisId}/print`, token);
