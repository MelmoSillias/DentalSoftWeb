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
