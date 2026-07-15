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
import http, { HEAVY_REQUEST_TIMEOUT_MS } from '@/service/http';
import { normalizeDentList, parseConsultationInvoiceResponse } from '@/services/consultations';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

const withHeaders = (token) => ({ headers: authHeaders(token) });

const heavyListConfig = (token) => ({
    ...withHeaders(token),
    timeout: HEAVY_REQUEST_TIMEOUT_MS,
});

const fetchPrintBlob = async (url, token) => {
    const res = await axios.get(url, { responseType: 'blob', ...withHeaders(token) });
    return res.data;
};

export const fetchFactures = async ({ start, end, factureType = 'all', unpaidOnly = false }, token) => {
    const type = factureType || (unpaidOnly ? 'impaye' : 'all');

    if (isCaisseTourMockEnabled()) {
        return fetchFacturesTourMock({ start, end, factureType: type });
    }

    if (type === 'impaye_toutes') {
        const res = await axios.get(`${apiPrefix}/factures/unpaid`, heavyListConfig(token));
        return res.data || [];
    }

    if (type === 'impaye') {
        const res = await axios.get(`${apiPrefix}/factures/unpaid`, {
            params: { start, end },
            ...heavyListConfig(token),
        });
        return res.data || [];
    }

    const res = await axios.get(`${apiPrefix}/factures`, {
        params: { start, end },
        ...heavyListConfig(token),
    });
    const data = res.data;
    if (data && Array.isArray(data.all)) {
        return data.all;
    }
    return Array.isArray(data) ? data : [];
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
                notes: item.notes || null
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
    return parseConsultationInvoiceResponse(res.data);
};

export const updateFactureLines = async (consultationId, payload, token) => {
    const lines = Array.isArray(payload) ? payload : payload?.lines ?? payload?.lignes ?? [];
    const date = Array.isArray(payload) ? null : payload?.date ?? null;
    const time = Array.isArray(payload) ? null : payload?.time ?? null;

    if (isCaisseTourMockEnabled()) {
        return updateFactureLinesTourMock(consultationId, lines, { date, time });
    }

    const requestPayload = {
        lignes: (lines || []).map((line) => ({
            dent: normalizeDentList(line.dent ?? line.dents ?? '').join(','),
            dents: normalizeDentList(line.dent ?? line.dents ?? ''),
            type: line.type || '',
            prix: Number(line.prix) || 0,
            quantite: Number(line.quantite) || 1,
            description: line.description || ''
        }))
    };

    if (date) {
        requestPayload.date = date;
    }
    if (time) {
        requestPayload.time = time;
    }

    const res = await axios.put(`${apiPrefix}/consultations/${consultationId}/facture/update`, requestPayload, withHeaders(token));
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

export const payInsurancePatientShare = async (claimId, { modeId, date, amount } = {}, token) => {
    const res = await axios.post(
        `${apiPrefix}/assurances/claims/${claimId}/patient-pay`,
        { modeId, date, amount },
        withHeaders(token)
    );
    return res.data;
};

export const fetchAssurancesDashboard = async (token) => {
    const res = await axios.get(`${apiPrefix}/assurances/dashboard`, withHeaders(token));
    return Array.isArray(res?.data?.data) ? res.data.data : [];
};

export const fetchAssuranceLots = async (assuranceCode, { statut } = {}, token) => {
    const params = {};
    if (statut && statut !== 'all') params.statut = statut;
    const res = await axios.get(`${apiPrefix}/assurances/${encodeURIComponent(assuranceCode)}/lots`, { params, ...withHeaders(token) });
    return res.data || {};
};

export const openAssuranceLot = async (assuranceCode, payload = {}, token) => {
    const res = await axios.post(`${apiPrefix}/assurances/${encodeURIComponent(assuranceCode)}/lots`, payload, withHeaders(token));
    return res.data;
};

export const updateAssuranceLot = async (lotId, payload = {}, token) => {
    const res = await axios.patch(`${apiPrefix}/assurances/lots/${lotId}`, payload, withHeaders(token));
    return res.data;
};

export const fetchAssuranceLotDetail = async (lotId, token) => {
    const res = await axios.get(`${apiPrefix}/assurances/lots/${lotId}`, withHeaders(token));
    return res?.data?.data || null;
};

export const sendAssuranceLot = async (lotId, token) => {
    const res = await axios.post(`${apiPrefix}/assurances/lots/${lotId}/send`, {}, withHeaders(token));
    return res.data;
};

export const reopenAssuranceLot = async (lotId, token) => {
    const res = await axios.post(`${apiPrefix}/assurances/lots/${lotId}/reopen`, {}, withHeaders(token));
    return res.data;
};

export const confirmAssuranceLot = async (lotId, token) => {
    const res = await axios.post(`${apiPrefix}/assurances/lots/${lotId}/confirm`, {}, withHeaders(token));
    return res.data;
};

export const unconfirmAssuranceLot = async (lotId, token) => {
    const res = await axios.post(`${apiPrefix}/assurances/lots/${lotId}/unconfirm`, {}, withHeaders(token));
    return res.data;
};

export const refundAssuranceLot = async (lotId, { modeId, date, amount } = {}, token) => {
    const res = await axios.post(
        `${apiPrefix}/assurances/lots/${lotId}/refund`,
        { modeId, date, amount },
        withHeaders(token)
    );
    return res.data;
};

/** @deprecated Use refundAssuranceLot */
export const recoverAssuranceLot = async (lotId, payload = {}, token) => refundAssuranceLot(lotId, payload, token);

export const cancelAssuranceLotRefund = async (lotId, transactionId, { comment } = {}, token) => {
    const res = await axios.patch(
        `${apiPrefix}/assurances/lots/${lotId}/refunds/${transactionId}/cancel`,
        { comment },
        withHeaders(token)
    );
    return res.data;
};

/** @deprecated Use cancelAssuranceLotRefund */
export const cancelAssuranceLotRecovery = async (lotId, { comment } = {}, token) => {
    const res = await axios.patch(`${apiPrefix}/assurances/lots/${lotId}/recover/cancel`, { comment }, withHeaders(token));
    return res.data;
};

export const addClaimToAssuranceLot = async (lotId, factureId, token) => {
    const res = await axios.post(`${apiPrefix}/assurances/lots/${lotId}/claims`, { factureId }, withHeaders(token));
    return res.data;
};

export const moveClaimToAssuranceLot = async (factureId, lotId, token) => {
    const res = await axios.post(
        `${apiPrefix}/assurances/claims/${factureId}/move-lot`,
        { lotId },
        withHeaders(token)
    );
    return res.data;
};

export const removeClaimFromAssuranceLot = async (lotId, factureId, token) => {
    const res = await axios.delete(`${apiPrefix}/assurances/lots/${lotId}/claims/${factureId}`, withHeaders(token));
    return res.data;
};

export const fetchInsuranceClaimDetail = async (claimId, token) => {
    const res = await axios.get(`${apiPrefix}/assurances/claims/${claimId}`, withHeaders(token));
    return res?.data?.data || null;
};
