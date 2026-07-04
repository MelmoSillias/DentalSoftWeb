import { apiPrefix } from '@/config';
import http from '@/service/http';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchGeneralSettings = async (token) => {
    const res = await http.get(`${apiPrefix}/settings/general`, { headers: authHeaders(token) });
    return res.data;
};

export const fetchPublicGeneralSettings = async (token) => {
    const res = await http.get(`${apiPrefix}/settings/general/public`, { headers: authHeaders(token) });
    return res.data;
};

export const saveGeneralSettings = async (payload, token) => {
    const res = await http.put(`${apiPrefix}/settings/general`, payload, { headers: authHeaders(token) });
    return res.data;
};

export const createMissingPatientPortalAccounts = async (token) => {
    const res = await http.post(`${apiPrefix}/settings/general/patient-portal/create-missing`, {}, { headers: authHeaders(token) });
    return res.data;
};

export const fetchTestModeStatus = async (token) => {
    const res = await http.get(`${apiPrefix}/settings/test-mode/status`, { headers: authHeaders(token) });
    return res.data;
};

export const toggleTestMode = async ({ enabled, password, deleteTestData = true }, token) => {
    const res = await http.put(
        `${apiPrefix}/settings/test-mode/toggle`,
        { enabled, password, deleteTestData },
        { headers: authHeaders(token) }
    );

    return res.data;
};

export const cleanTestMode = async ({ password }, token) => {
    const res = await http.post(
        `${apiPrefix}/settings/test-mode/clean`,
        { password },
        { headers: authHeaders(token) }
    );

    return res.data;
};

export const exportDatabase = async ({ password, formats }, token) => {
    const res = await http.post(
        `${apiPrefix}/settings/database/export`,
        { password, formats },
        { headers: authHeaders(token) }
    );

    return res.data;
};

export const downloadDatabaseExport = async ({ file }, token) => {
    const res = await http.get(`${apiPrefix}/settings/database/export/download`, {
        params: { file },
        headers: authHeaders(token),
        responseType: 'blob',
    });

    const disposition = res.headers['content-disposition'] || '';
    const filenameMatch = disposition.match(/filename\*?=(?:UTF-8''|\")?([^\";]+)/i);
    const filename = filenameMatch ? decodeURIComponent(filenameMatch[1].replace(/\"/g, '').trim()) : null;

    return {
        blob: res.data,
        filename,
    };
};

export const resetDatabase = async ({ password }, token) => {
    const res = await http.post(
        `${apiPrefix}/settings/database/reset`,
        { password },
        { headers: authHeaders(token) }
    );

    return res.data;
};

export const fetchApprovedDevices = async (token) => {
    const res = await http.get(`${apiPrefix}/settings/devices`, { headers: authHeaders(token) });
    return res.data;
};

export const approveDevice = async (deviceId, token) => {
    const res = await http.post(`${apiPrefix}/settings/devices/${deviceId}/approve`, {}, { headers: authHeaders(token) });
    return res.data;
};

export const rejectDevice = async (deviceId, token) => {
    const res = await http.post(`${apiPrefix}/settings/devices/${deviceId}/reject`, {}, { headers: authHeaders(token) });
    return res.data;
};

export const deleteDevice = async (deviceId, token) => {
    const res = await http.delete(`${apiPrefix}/settings/devices/${deviceId}`, { headers: authHeaders(token) });
    return res.data;
};

export const renameDevice = async (deviceId, name, token) => {
    const res = await http.put(
        `${apiPrefix}/settings/devices/${deviceId}/rename`,
        { name },
        { headers: authHeaders(token) }
    );
    return res.data;
};
