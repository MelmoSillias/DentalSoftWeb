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
import http, { UPLOAD_REQUEST_TIMEOUT_MS } from '@/service/http';

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
    referencement: raw.referencement ?? '',
    groupeSanguin: raw.groupeSanguin ?? raw.groupe_sanguin ?? '',
    notes: raw.notes ?? '',
    deletedAt: raw.deletedAt ?? raw.deleted_at ?? null,
    dateInscription: raw.dateInscription ?? raw.date_inscription ?? raw.createdAt ?? raw.created_at ?? '',
    createdAt: raw.createdAt ?? raw.created_at ?? raw.dateInscription ?? raw.date_inscription ?? '',
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
    insuranceProfile: raw.insuranceProfile ?? raw.assuranceProfile ?? null,
    portalAccount: raw.portalAccount ?? null,
    derniereConsultation: raw.derniereConsultation ?? raw.derniere_consultation ?? null,
    archiveFiles: Array.isArray(raw.archiveFiles) ? raw.archiveFiles : []
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
    const createdPatientId = res.data?.patientId ?? res.data?.id ?? null;
    if (!createdPatientId) {
        return normalizePatient(res.data);
    }

    return fetchPatientById(createdPatientId, token);
};

export const fetchAllPatients = async (token) => {
    if (isPatientsTourMockEnabled()) {
        const data = listPatientsTourMock({ page: 1, limit: 1000, q: '', sortField: null, sortOrder: null });
        const items = Array.isArray(data?.items) ? data.items : Array.isArray(data) ? data : [];
        return items.map(normalizePatient);
    }

    const res = await axios.get(`${apiPrefix}/patients`, {
        headers: authHeaders(token)
    });
    const items = Array.isArray(res.data?.items) ? res.data.items : Array.isArray(res.data) ? res.data : [];
    return items.map(normalizePatient);
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

export const fetchPatientsOverviewStats = async (token, { medecinOnly = false } = {}) => {
    const endpoint = medecinOnly ? `${apiPrefix}/patients/medecin/overview-stats` : `${apiPrefix}/patients/overview-stats`;
    const res = await axios.get(endpoint, { headers: authHeaders(token) });
    const data = res.data || {};

    return {
        totalPatients: Number(data.totalPatients || 0),
        consultationsToday: Number(data.consultationsToday || 0),
        upcomingAppointments: Number(data.upcomingAppointments || 0),
        newPatientsThisMonth: Number(data.newPatientsThisMonth || 0),
        referrals: Array.isArray(data.referrals) ? data.referrals : []
    };
};

export const updatePatient = async (patientId, payload, token) => {
    if (isPatientsTourMockEnabled()) {
        const data = updatePatientTourMock(patientId, payload);
        return data ? normalizePatient(data) : null;
    }

    await axios.post(`${apiPrefix}/patient/${patientId}/update`, payload, {
        headers: authHeaders(token)
    });
    return fetchPatientById(patientId, token);
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

export const addArchiveFile = async (patientId, formData, token) => {
    const res = await http.post(`${apiPrefix}/patient/${patientId}/archive-file`, formData, {
        headers: {
            ...authHeaders(token),
            'Content-Type': 'multipart/form-data'
        },
        timeout: UPLOAD_REQUEST_TIMEOUT_MS
    });
    return res.data;
};

export const deleteArchiveFile = async (patientId, fileUrl, token) => {
    const res = await http.delete(`${apiPrefix}/patient/${patientId}/archive-file`, {
        headers: authHeaders(token),
        data: { url: fileUrl }
    });
    return res.data;
};
