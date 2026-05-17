import { apiRequest } from './api';

export async function loginPatient(identifier, password) {
    return apiRequest('/api/login_check', {
        method: 'POST',
        body: {
            username: identifier,
            password
        }
    });
}

export async function fetchCurrentUser(token) {
    return apiRequest('/api/me', { token });
}

export async function fetchPublicPortalSettings() {
    return apiRequest('/api/settings/general/public', { showToast: false });
}

export async function fetchPatientDashboard(token) {
    return apiRequest('/api/portal-patient/me/dashboard', { token });
}

export async function fetchPatientConsultations(token) {
    return apiRequest('/api/portal-patient/me/consultations', { token });
}

export async function fetchPatientAppointments(token) {
    return apiRequest('/api/portal-patient/me/rdvs', { token });
}

export async function fetchPatientPayments(token) {
    return apiRequest('/api/portal-patient/me/paiements', { token });
}

export async function fetchPatientDocuments(token) {
    return apiRequest('/api/portal-patient/me/devis-factures', { token });
}

export async function fetchPatientAppreciations(token) {
    return apiRequest('/api/portal-patient/me/appreciations', { token });
}

export async function submitConsultationAppreciation(token, consultationId, payload) {
    return apiRequest(`/api/portal-patient/me/consultations/${consultationId}/appreciation`, {
        method: 'POST',
        token,
        body: payload
    });
}

export async function submitAnonymousAppreciation(payload) {
    return apiRequest('/api/appreciations/anonymous', {
        method: 'POST',
        body: payload
    });
}

export async function fetchPatientProfile(token) {
    return apiRequest('/api/portal-patient/me/profil', { token });
}

export async function fetchConsultationDetail(token, consultationId) {
    return apiRequest(`/api/portal-patient/me/consultations/${consultationId}`, { token });
}

export async function fetchDocumentDetail(token, documentId) {
    return apiRequest(`/api/portal-patient/me/devis-factures/${documentId}`, { token });
}
