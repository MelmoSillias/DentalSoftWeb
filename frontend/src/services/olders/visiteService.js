import http from '@/service/http';

const serializePayload = (payload) => {
    const safe = { ...payload };
    safe.fichiersJoints = (payload.fichiersJoints || []).map((file) => {
        const copy = { ...file };
        delete copy.file;
        return copy;
    });
    return safe;
};

const buildFormData = (payload, uploads = []) => {
    const fd = new FormData();
    fd.append('payload', JSON.stringify(serializePayload(payload)));
    uploads.forEach((item) => {
        if (item?.field && item?.file) {
            fd.append(item.field, item.file);
        }
    });
    return fd;
};

export const fetchVisits = (params = {}) => http.get('/visites', { params }).then((r) => r.data);

export const fetchVisit = (id) => http.get(`/visites/${id}`).then((r) => r.data);

export const createVisit = (payload, uploads = []) => {
    const fd = buildFormData(payload, uploads);
    return http
        .post('/visites', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
        .then((r) => r.data);
};

export const updateVisit = (id, payload, uploads = []) => {
    const fd = buildFormData(payload, uploads);
    fd.append('_method', 'PUT');
    return http.post(`/visites/${id}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } }).then((r) => r.data);
};

export const deleteVisit = (id) => http.delete(`/visites/${id}`).then((r) => r.data);

export const fetchAgences = () => http.get('/agences').then((r) => r.data);
