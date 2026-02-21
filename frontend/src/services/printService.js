import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});
const withHeaders = (token) => ({ headers: authHeaders(token) });

export const fetchDevisPrintData = async (devisId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/devis/${devisId}`, withHeaders(token));
    return res.data;
};

export const fetchInvoicePrintData = async (factureId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/invoices/${factureId}`, withHeaders(token));
    return res.data;
};

export const fetchReceiptPrintData = async (paiementId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/receipts/${paiementId}`, withHeaders(token));
    return res.data;
};

export const fetchTicketPrintData = async (paiementId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/tickets/${paiementId}`, withHeaders(token));
    return res.data;
};

export const fetchPaymentsListPrintData = async ({ start, end }, token) => {
    const res = await axios.get(`${apiPrefix}/prints/payments`, { params: { start, end }, ...withHeaders(token) });
    return res.data;
};

export const fetchOrdonnancePrintData = async (ordonnanceId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/ordonnances/${ordonnanceId}`, withHeaders(token));
    return res.data;
};

export const fetchPatientDossierPrintData = async (patientId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/patient/${patientId}/dossier`, withHeaders(token));
    return res.data;
};

export const fetchPatientFichePrintData = async (patientId, ficheId, token) => {
    const res = await axios.get(`${apiPrefix}/prints/patient/${patientId}/fiche/${ficheId}`, withHeaders(token));
    return res.data;
};
