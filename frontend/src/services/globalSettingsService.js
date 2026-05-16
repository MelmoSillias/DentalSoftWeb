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

export const fetchTestModeStatus = async (token) => {
    const res = await http.get(`${apiPrefix}/settings/test-mode/status`, { headers: authHeaders(token) });
    return res.data;
};

export const toggleTestMode = async ({ enabled, password }, token) => {
    const res = await http.put(
        `${apiPrefix}/settings/test-mode/toggle`,
        { enabled, password },
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

export const resetDatabase = async ({ password }, token) => {
    const res = await http.post(
        `${apiPrefix}/settings/database/reset`,
        { password },
        { headers: authHeaders(token) }
    );

    return res.data;
};
