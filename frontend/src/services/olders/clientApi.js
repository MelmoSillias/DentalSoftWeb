import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const normalizeClient = (raw = {}) => ({
    id: raw.id,
    name: raw.name ?? raw.nom ?? '',
    nom: raw.nom ?? raw.name ?? '',
    code: raw.code ?? null,
    address: raw.address ?? raw.adresse ?? null,
    adresse: raw.adresse ?? raw.address ?? null,
    nbFactures: raw.nbFactures ?? raw.nb_factures ?? 0
});

const normalizeInvoice = (inv = {}) => {
    const paid = Array.isArray(inv.paiements) ? inv.paiements.reduce((sum, p) => sum + (p.montant || 0), 0) : 0;
    const total = inv.totalAvecDebours ?? 0;
    return {
        ...inv,
        paid,
        remaining: total - paid
    };
};

export const fetchClients = async (token) => {
    const res = await axios.get(`${apiPrefix}/clients`, { headers: authHeaders(token) });
    const data = Array.isArray(res.data) ? res.data : [];
    return data.map(normalizeClient);
};

export const fetchClientById = async (clientId, token) => {
    const res = await axios.get(`${apiPrefix}/clients/${clientId}`, { headers: authHeaders(token) });
    const normalized = normalizeClient(res.data);
    return { ...res.data, ...normalized };
};

export const fetchInvoices = async (token) => {
    const res = await axios.get(`${apiPrefix}/factures`, { headers: authHeaders(token) });
    const data = Array.isArray(res.data) ? res.data : [];
    return data.map(normalizeInvoice);
};

export const fetchInvoicesByClient = async (clientId, token) => {
    const res = await axios.get(`${apiPrefix}/invoices_by_client/${clientId}`, { headers: authHeaders(token) });
    const data = Array.isArray(res.data) ? res.data : [];
    return data.map(normalizeInvoice);
};

export const createClient = async (payload, token) => {
    const res = await axios.post(`${apiPrefix}/clients`, payload, { headers: authHeaders(token) });
    return normalizeClient(res.data);
};
