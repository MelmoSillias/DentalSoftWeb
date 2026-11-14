import { apiRequest } from '../../shared/api/api.js';

const root = '/api/patient-portal/auth';
const post = (path, body) => apiRequest(`${root}/${path}`, { method: 'POST', body, showToast: false });
export const loginPatient = (email, password) => post('login', { email, password });
export const refreshPatient = (refresh_token) => post('refresh', { refresh_token });
export const logoutPatient = (refresh_token) => post('logout', { refresh_token });
export const fetchCurrentUser = (token) => apiRequest('/api/me', { token, authenticated: false, showToast: false });
export const updatePassword = (token, oldPassword, newPassword) => apiRequest('/api/me/password', {
    method: 'PATCH', token, body: { oldPassword, newPassword }, showToast: false
});
