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
