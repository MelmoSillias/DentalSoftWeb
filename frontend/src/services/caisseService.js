import { apiPrefix } from '@/config';
import {
    fetchFactureDetailTourMock,
    fetchFacturesTourMock,
    fetchFactureLinesTourMock,
    fetchPaymentMethodsTourMock,
    fetchPaymentsTourMock,
    isCaisseTourMockEnabled,
    payFactureTourMock,
    updateFactureLinesTourMock,
    validateEmptyFactureTourMock
} from '@/services/caisseTourMock';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const withHeaders = (token) => ({ headers: authHeaders(token) });

const fetchPrintBlob = async (url, token) => {
    const res = await axios.get(url, { responseType: 'blob', ...withHeaders(token) });
    return res.data;
};

export const fetchFactures = async ({ start, end, unpaidOnly = false }, token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchFacturesTourMock({ start, end, unpaidOnly });
    }

    const url = unpaidOnly ? `${apiPrefix}/factures/unpaid` : `${apiPrefix}/factures/classiques`;
    const res = await axios.get(url, { params: { start, end }, ...withHeaders(token) });
    return res.data || [];
};

export const fetchPayments = async ({ start, end }, token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchPaymentsTourMock({ start, end: end === '' ? start : end });
    }

    const res = await axios.get(`${apiPrefix}/factures/payments`, { params: { start, end: end === "" ? start : end }, ...withHeaders(token) });
    return res.data || [];
};

export const fetchPaymentMethods = async (token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchPaymentMethodsTourMock();
    }

    const res = await axios.get(`${apiPrefix}/payment-methods`, withHeaders(token));
    return Array.isArray(res.data) ? res.data : [];
};

export const fetchAssurances = async (token) => {
    if (isCaisseTourMockEnabled()) {
        const methods = fetchPaymentMethodsTourMock();
        return methods
            .filter((item) => String(item?.type || '').toLowerCase().includes('assur'))
            .map((item) => ({
                id: item.id,
                nom: item.libelle,
                code: null,
                actif: item.actif !== false,
                notes: item.notes || null,
                defaultRate: Number(item?.coverageRate ?? 0) || 0
            }));
    }

    const res = await axios.get(`${apiPrefix}/assurances`, withHeaders(token));
    return Array.isArray(res.data) ? res.data : [];
};

export const payFacture = async (factureId, payload = {}, token) => {
    if (isCaisseTourMockEnabled()) {
        return payFactureTourMock(factureId, payload);
    }

    const res = await axios.post(`${apiPrefix}/factures/${factureId}/pay`, payload, withHeaders(token));
    return res.data;
};

export const resetFacturePayments = async (factureId, token) => {
    if (isCaisseTourMockEnabled()) {
        return { success: true };
    }

    const res = await axios.delete(`${apiPrefix}/factures/${factureId}/payments/reset`, withHeaders(token));
    return res.data;
};

export const validateEmptyFacture = async (factureId, token) => {
    if (isCaisseTourMockEnabled()) {
        return validateEmptyFactureTourMock(factureId);
    }

    return payFacture(factureId, {}, token);
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

export const fetchFactureDetail = async (factureId, token) => {
    if (isCaisseTourMockEnabled()) {
        return fetchFactureDetailTourMock(factureId);
    }

    const res = await axios.get(`${apiPrefix}/factures/${factureId}`, withHeaders(token));
    return res.data;
};

export const getPaymentPrint = (id, token) => fetchPrintBlob(`${apiPrefix}/payments/${id}/print`, token);
export const getReceiptPrint = (id, token) => fetchPrintBlob(`${apiPrefix}/receipts/${id}/print`, token);
export const getPaymentsRangePrint = ({ start, end }, token) => fetchPrintBlob(`${apiPrefix}/payments/print?start=${start}&end=${end}`, token);
export const getInvoicePrint = (factureId, token) => fetchPrintBlob(`${apiPrefix}/invoices/${factureId}/print`, token);

export const fetchInsuranceClaims = async ({ status, start, end, patient, assuranceCode } = {}, token) => {
    const params = {};
    if (status && status !== 'all') {
        params.status = status;
    }

    if (start) {
        params.start = start;
    }

    if (end) {
        params.end = end;
    }

    if (patient && String(patient).trim() !== '') {
        params.patient = String(patient).trim();
    }

    if (assuranceCode && assuranceCode !== 'all') {
        params.assuranceCode = assuranceCode;
    }

    const res = await axios.get(`${apiPrefix}/factures/assurances`, { params, ...withHeaders(token) });
    return Array.isArray(res?.data?.data) ? res.data.data : [];
};

export const validateInsuranceClaim = async (claimId, token) => {
    const res = await axios.patch(`${apiPrefix}/assurances/claims/${claimId}/validate`, {}, withHeaders(token));
    return res.data;
};

export const rejectInsuranceClaim = async (claimId, reason, token) => {
    const res = await axios.patch(`${apiPrefix}/assurances/claims/${claimId}/reject`, { reason }, withHeaders(token));
    return res.data;
};

export const recoverInsuranceClaim = async (claimId, { modeId, date } = {}, token) => {
    const res = await axios.post(`${apiPrefix}/assurances/claims/${claimId}/recover`, { modeId, date }, withHeaders(token));
    return res.data;
};

export const payInsurancePatientShare = async (claimId, { modeId, date, amount } = {}, token) => {
    const res = await axios.post(
        `${apiPrefix}/assurances/claims/${claimId}/patient-pay`,
        { modeId, date, amount },
        withHeaders(token)
    );
    return res.data;
};
