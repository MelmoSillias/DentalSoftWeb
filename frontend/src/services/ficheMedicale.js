import { apiPrefix } from '@/config';
import http from '@/service/http';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const loadFicheMedicale = async (ficheId, token) => {
    const res = await axios.get(`${apiPrefix}/fiches-medicales/${ficheId}/json`, {
        headers: authHeaders(token)
    });
    return res.data;
};

export const saveTemplateForm = async (ficheId, formTemplateKey, formData, files, token, extraPayload = {}) => {
    const payload = {
        ficheId,
        ...extraPayload
    };

    if (formTemplateKey !== undefined && formTemplateKey !== null) {
        payload.formTemplateKey = formTemplateKey;
    }

    if (formData !== undefined && formData !== null) {
        payload.formData = formData;
    }

    const hasFiles = Array.isArray(files) && files.some((docFiles) => Array.isArray(docFiles) && docFiles.some(Boolean));

    if (hasFiles) {
        const multipart = new FormData();
        multipart.append('data', JSON.stringify(payload));
        (files || []).forEach((docFiles, index) => {
            (docFiles || []).forEach((file) => {
                if (file) multipart.append(`documentsFiles[${index}][]`, file);
            });
        });

        const response = await axios.post(`${apiPrefix}/fiche-medicale/update`, multipart, {
            headers: {
                ...authHeaders(token),
                'Content-Type': 'multipart/form-data'
            }
        });

        return response.data;
    }

    const response = await axios.post(
        `${apiPrefix}/fiche-medicale/update`,
        payload,
        { headers: authHeaders(token) }
    );

    return response.data;
};

export const loadFormTemplates = async (token) => {
    const response = await axios.get(`${apiPrefix}/fiche-medicale/templates`, {
        headers: authHeaders(token)
    });

    return Array.isArray(response.data) ? response.data : [];
};
