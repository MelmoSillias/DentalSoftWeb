import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const authHeaders = (token) => (token ? { Authorization: `Bearer ${token}` } : {});

export const fetchProjects = async (token) => {
    return axios.get(`${apiPrefix}/projects`, { headers: authHeaders(token) });
};

export const createProjectWithParcels = async (payload, token) => {
    return axios.post(`${apiPrefix}/projects/auto`, payload, { headers: authHeaders(token) });
};

export const validateParcelNumber = async (parcelNumber, token) => {
    return axios.get(`${apiPrefix}/geosheets/validate/parcel`, {
        params: { number: parcelNumber },
        headers: authHeaders(token)
    });
};

export const updateProject = async (projectId, payload, token) => {
    return axios.put(`${apiPrefix}/projects/${projectId}`, payload, { headers: authHeaders(token) });
};
