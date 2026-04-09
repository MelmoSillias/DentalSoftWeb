import { apiPrefix } from '@/config';
import {
    addPatientAllergyTourMock,
    addPatientAntecedentTourMock,
    checkConsultationActiveTourMock,
    createConsultationForPatientTourMock,
    createPatientTourMock,
    createRdvForPatientTourMock,
    deletePatientAllergyTourMock,
    deletePatientAntecedentTourMock,
    deleteConsultationTourMock,
    fetchMedecinsTourMock,
    fetchPatientConsultationsTourMock,
    fetchPatientDossierTourMock,
    fetchPatientByIdTourMock,
    fetchPaymentMethodsTourMock,
    isPatientsTourMockEnabled,
    listPatientsTourMock,
    searchPatientsTourMock,
    updatePatientTourMock
} from '@/services/patientsTourMock';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const normalizePatient = (raw = {}) => ({
    id: raw.id,
    nom: raw.nom ?? '',
    prenom: raw.prenom ?? '',
    fullname: raw.fullname ?? '',
    age: raw.age ?? null,
    dateNaissance: raw.dateNaissance ?? raw.date_naissance ?? '',
    sexe: raw.sexe ?? '',
    telephone: raw.telephone ?? '',
    email: raw.email ?? '',
    adresse: raw.adresse ?? '',
    profession: raw.profession ?? '',
    lieuNaissance: raw.lieuNaissance ?? raw.lieu_naissance ?? '',
    groupeSanguin: raw.groupeSanguin ?? raw.groupe_sanguin ?? '',
    notes: raw.notes ?? '',
    contactUrgence: raw.contactUrgence ?? raw.contact_urgence ?? null,
    smsPreferences: raw.smsPreferences ?? {
        patientCreated: raw.smsPatientCreated ?? false,
        receipt: raw.smsReceipt ?? false,
        ticket: raw.smsTicket ?? false,
        invoice: raw.smsInvoice ?? false,
        appointmentReminder: raw.smsAppointmentReminder ?? false,
        unsubscribed: raw.smsUnsubscribed ?? false,
        blacklisted: raw.smsBlacklisted ?? false
    },
    portalAccount: raw.portalAccount ?? null,
    derniereConsultation: raw.derniereConsultation ?? raw.derniere_consultation ?? null
});

export const fetchPatients = async (token, { page = 1, limit = 10, q = '', sortField = null, sortOrder = null } = {}) => {
    if (isPatientsTourMockEnabled()) {
        return listPatientsTourMock({ page, limit, q, sortField, sortOrder });
    }

    const res = await axios.get(`${apiPrefix}/patients`, {
        headers: authHeaders(token),
        params: { page, limit, q, sortField, sortOrder }
    });
    const data = res.data || {};
    const items = Array.isArray(data.items) ? data.items.map(normalizePatient) : [];
    return { ...data, items };
};

export const fetchPatientById = async (patientId, token) => {
    if (isPatientsTourMockEnabled()) {
        const data = fetchPatientByIdTourMock(patientId);
        const normalized = normalizePatient(data || {});
        return data ? { ...data, ...normalized } : null;
    }

    const res = await axios.get(`${apiPrefix}/patient/${patientId}`, { headers: authHeaders(token) });
    const normalized = normalizePatient(res.data);
    return { ...res.data, ...normalized };
};

export const createPatient = async (payload, token) => {
    if (isPatientsTourMockEnabled()) {
        return normalizePatient(createPatientTourMock(payload));
    }

    const res = await axios.post(`${apiPrefix}/patient/add`, payload, { headers: authHeaders(token) });
    return normalizePatient(res.data);
};

export const fetchPatientsByMedecin = async (token, { page = 1, limit = 10, q = '', sortField = null, sortOrder = null } = {}) => {
    if (isPatientsTourMockEnabled()) {
        return listPatientsTourMock({ page, limit, q, sortField, sortOrder });
    }

    const res = await axios.get(`${apiPrefix}/patients/medecin`, {
        headers: authHeaders(token),
        params: { page, limit, q, sortField, sortOrder }
    });
    const data = res.data || {};
    const items = Array.isArray(data.items) ? data.items.map(normalizePatient) : [];
    return { ...data, items };
};

export const updatePatient = async (patientId, payload, token) => {
    if (isPatientsTourMockEnabled()) {
        const data = updatePatientTourMock(patientId, payload);
        return data ? normalizePatient(data) : null;
    }

    const res = await axios.post(`${apiPrefix}/patient/${patientId}/update`, payload, {
        headers: authHeaders(token)
    });
    return normalizePatient(res.data);
};

export const searchPatients = async (query, token, limit = 20) => {
    if (isPatientsTourMockEnabled()) {
        return searchPatientsTourMock(query, limit).map(normalizePatient);
    }

    const res = await axios.get(`${apiPrefix}/patients/search`, {
        headers: authHeaders(token),
        params: { q: query, limit }
    });
    const results = Array.isArray(res.data?.results) ? res.data.results : [];
    return results.map(normalizePatient);
};

export const fetchPatientDossier = async (patientId, token) => {
    if (isPatientsTourMockEnabled()) {
        return fetchPatientDossierTourMock(patientId);
    }

    const res = await axios.get(`${apiPrefix}/patient/${patientId}/dossier`, { headers: authHeaders(token) });
    return res.data;
};

export const fetchPatientConsultations = async (patientId, token) => {
    if (isPatientsTourMockEnabled()) {
        return fetchPatientConsultationsTourMock(patientId);
    }

    const res = await axios.get(`${apiPrefix}/patient/${patientId}/consultations`, { headers: authHeaders(token) });
    return Array.isArray(res.data) ? res.data : [];
};

export const updatePatientDossier = async (patientId, payload, token) => {
    const res = await axios.put(`${apiPrefix}/patient/${patientId}/dossier/update`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const createConsultationForPatient = async (patientId, payload, token) => {
    if (isPatientsTourMockEnabled()) {
        return createConsultationForPatientTourMock(patientId, payload);
    }

    const res = await axios.post(`${apiPrefix}/patient/${patientId}/consultation/create`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const addPatientAntecedent = async (patientId, payload, token) => {
    if (isPatientsTourMockEnabled()) {
        return addPatientAntecedentTourMock(patientId, payload);
    }

    const res = await axios.post(`${apiPrefix}/patient/${patientId}/antecedents`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const deletePatientAntecedent = async (patientId, antecedentId, token) => {
    if (isPatientsTourMockEnabled()) {
        return deletePatientAntecedentTourMock(patientId, antecedentId);
    }

    const res = await axios.delete(`${apiPrefix}/patient/${patientId}/antecedents/${antecedentId}`, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const addPatientAllergy = async (patientId, payload, token) => {
    if (isPatientsTourMockEnabled()) {
        return addPatientAllergyTourMock(patientId, payload);
    }

    const res = await axios.post(`${apiPrefix}/patient/${patientId}/allergies`, payload, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const deletePatientAllergy = async (patientId, allergyId, token) => {
    if (isPatientsTourMockEnabled()) {
        return deletePatientAllergyTourMock(patientId, allergyId);
    }

    const res = await axios.delete(`${apiPrefix}/patient/${patientId}/allergies/${allergyId}`, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const checkConsultationActive = async (patientId, token) => {
    if (isPatientsTourMockEnabled()) {
        return checkConsultationActiveTourMock(patientId);
    }

    const res = await axios.get(`${apiPrefix}/patient/${patientId}/consultation-en-cours`, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const deleteConsultation = async (consultationId, token) => { 
    if (isPatientsTourMockEnabled()) {
        return deleteConsultationTourMock(consultationId);
    }

    const res = await axios.delete(`${apiPrefix}/consultations/${consultationId}`, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const fetchMedecins = async (token) => {
    if (isPatientsTourMockEnabled()) {
        return fetchMedecinsTourMock();
    }

    const res = await axios.get(`${apiPrefix}/medecins`, { headers: authHeaders(token) });
    return Array.isArray(res.data) ? res.data : [];
};

export const fetchPaymentMethods = async (token) => {
    if (isPatientsTourMockEnabled()) {
        return fetchPaymentMethodsTourMock();
    }

    const res = await axios.get(`${apiPrefix}/payment-methods`, { headers: authHeaders(token) });
    return Array.isArray(res.data) ? res.data : [];
};

export const createRdvForPatient = async (patientId, payload, token) => {
    if (isPatientsTourMockEnabled()) {
        return createRdvForPatientTourMock(patientId, payload);
    }

    const res = await axios.post(
        `${apiPrefix}/patient/${patientId}/rdv/create`,
        { ...payload, patient_id: patientId },
        {
            headers: authHeaders(token)
        }
    );
    return res.data;
};

export const printPatientInfosPerso = async (patientId, token) => {
    const res = await axios.get(`${apiPrefix}/patient/${patientId}/dossier/print/infosperso`, {
        headers: authHeaders(token),
        responseType: 'blob'
    });
    return res.data;
};

export const printPatientFiche = async (patientId, ficheId, token) => {
    const res = await axios.get(`${apiPrefix}/patient/${patientId}/fiche/${ficheId}/print`, {
        headers: authHeaders(token),
        responseType: 'blob'
    });
    return res.data;
};

export const fetchPatientPortalUser = async (patientId, token) => {
    const res = await axios.get(`${apiPrefix}/patient/${patientId}/portal-user`, {
        headers: authHeaders(token)
    });

    return res.data;
};

export const createPatientPortalUser = async (patientId, token) => {
    const res = await axios.post(`${apiPrefix}/patient/${patientId}/portal-user/create`, {}, {
        headers: authHeaders(token)
    });

    return res.data;
};

export const resetPatientPortalPassword = async (patientId, token) => {
    const res = await axios.post(`${apiPrefix}/patient/${patientId}/portal-user/reset-password`, {}, {
        headers: authHeaders(token)
    });

    return res.data;
};

export const togglePatientPortalUser = async (patientId, active, token) => {
    const res = await axios.patch(`${apiPrefix}/patient/${patientId}/portal-user/active`, { active }, {
        headers: authHeaders(token)
    });

    return res.data;
};
