import { apiPrefix } from '@/config';
import http from '@/service/http';

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchAdminAppreciations = async (token, { limit = 200 } = {}) => {
    const res = await http.get(`${apiPrefix}/administration/appreciations`, {
        headers: authHeaders(token),
        params: { limit }
    });

    return res.data;
};
