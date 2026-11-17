import { apiPrefix } from '@/config.js';
import http from '@/shared/api/http.js';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchAdminAppreciations = async (token, { limit = 200 } = {}) => {
    const res = await http.get(`${apiPrefix}/administration/appreciations`, {
        headers: authHeaders(token),
        params: { limit }
    });

    return res.data;
};

export const setAdminAppreciationPublished = async (token, id, isPublished) => {
    const res = await http.patch(`${apiPrefix}/administration/appreciations/${id}/publish`, { isPublished }, { headers: authHeaders(token) });

    return res.data;
};

export const deleteAdminAppreciation = async (token, id) => {
    const res = await http.delete(`${apiPrefix}/administration/appreciations/${id}`, {
        headers: authHeaders(token)
    });

    return res.data;
};
