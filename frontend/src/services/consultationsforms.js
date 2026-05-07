import { apiPrefix } from '@/config';
import { closeConsultationTourMock, isConsultationsTourMockEnabled, saveConsultationTourMock } from '@/services/consultationsTourMock';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const loadConsultationForm = async (ficheId, consultId, token) => {
    const res = await axios.get(`${apiPrefix}/fiches/${ficheId}/consultations/${consultId}/json`, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveMotif = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches/${ficheId}/motif`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveExamens = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches/${ficheId}/examens`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveTraitementsDocuments = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches/${ficheId}/traitements`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveDevis = async (ficheId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/fiches/${ficheId}/devis`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveConsultation = async (ficheId, consultId, payload, token) => {
    if (isConsultationsTourMockEnabled()) {
        return saveConsultationTourMock(ficheId, consultId, payload);
    }

    const res = await axios.post(`${apiPrefix}/fiches/${ficheId}/consultations/${consultId}`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const closeConsultation = async (ficheId, consultId, token, payload = null) => { 
    if (isConsultationsTourMockEnabled()) {
        return closeConsultationTourMock(ficheId, consultId);
    }

    const res = await axios.post(
        `${apiPrefix}/fiches/${ficheId}/consultations/${consultId}/cloture`,
        payload && typeof payload === 'object' ? payload : {},
        {
            headers: authHeaders(token)
        }
    );
    return res.data;
};

export const loadOrdonnances = async (consultId, token) => {
    const res = await axios.get(`${apiPrefix}/consultations/${consultId}/ordonnances`, {
        headers: authHeaders(token)
    });
    return Array.isArray(res.data) ? res.data : [];
};

export const saveOrdonnance = async (consultId, payload, token) => {
    const res = await axios.post(`${apiPrefix}/consultations/${consultId}/ordonnances`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const printOrdonnance = async (ordonnanceId, token) => {
    const res = await axios.get(`${apiPrefix}/ordonnance/${ordonnanceId}/print`, {
        headers: authHeaders(token),
        responseType: 'blob'
    });
    return res.data;
};

export const defaultActesList = ['Consultation', 'Détartrage', 'Extraction', 'Remplissage', 'Composite', 'Amalgame', 'Traitement de canal', 'Traumatisme', 'Couronne', 'Blanchiment', 'Radio', 'Prothèse', 'Orthodontie', 'Chirurgie'];
