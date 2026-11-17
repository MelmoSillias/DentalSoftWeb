import { apiRequest } from '../../shared/api/api.js';

const root = '/api/patient-portal/me';
const get = (path, token) => apiRequest(`${root}/${path}`, { token });
const id = (value) => encodeURIComponent(String(value));

export const fetchPublicPortalSettings = () => apiRequest('/api/settings/general/public', { showToast: false });
export const fetchPatientDashboard = (token) => get('dashboard', token);
export const fetchPatientConsultations = (token) => get('consultations', token);
export const fetchPatientAppointments = (token) => get('appointments', token);
export const fetchPatientPayments = (token) => get('payments', token);
export const fetchPatientDocuments = (token) => get('billing-documents', token);
export const fetchPatientAppreciations = (token) => get('reviews', token);
export const fetchPatientProfile = (token) => get('profile', token);
export const fetchConsultationDetail = (token, consultationId) => get(`consultations/${id(consultationId)}`, token);
export const fetchDocumentDetail = (token, documentId) => get(`billing-documents/${id(documentId)}`, token);
export const fetchPaymentReceipt = (token, paymentId) => get(`payments/${id(paymentId)}/receipt`, token);
export const submitConsultationAppreciation = (token, consultationId, body) => apiRequest(`${root}/consultations/${id(consultationId)}/review`, { method: 'POST', token, body });
export const submitAnonymousAppreciation = (body) => apiRequest('/api/public/review-submissions', { method: 'POST', body });
