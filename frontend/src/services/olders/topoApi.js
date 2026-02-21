import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

export const getProject = (projectId, token) => {
    return axios.get(`${apiPrefix}/projects/${projectId}`, { headers: { Authorization: `Bearer ${token}` } });
};

export const getGeoSheet = (geoId, token) => {
    return axios.get(`${apiPrefix}/geosheets/${geoId}`, { headers: { Authorization: `Bearer ${token}` } });
};

export const getGeoSheetVersions = (geoId, token) => {
    return axios.get(`${apiPrefix}/geosheets/${geoId}/versions`, { headers: { Authorization: `Bearer ${token}` } });
};

export const updateGeoPoints = (geoId, points, token) => {
    return axios.put(`${apiPrefix}/geosheets/${geoId}/points`, { points }, { headers: { Authorization: `Bearer ${token}` } });
};

export const getProjectTimeline = (projectId, token) => {
    return axios.get(`${apiPrefix}/projects/${projectId}/timeline`, { headers: { Authorization: `Bearer ${token}` } });
};

export const addProjectTimeline = (projectId, payload, token) => {
    return axios.post(`${apiPrefix}/projects/${projectId}/timeline`, payload, { headers: { Authorization: `Bearer ${token}` } });
};

export const deleteProjectTimeline = (timelineId, token) => {
    return axios.delete(`${apiPrefix}/project-timeline/${timelineId}`, { headers: { Authorization: `Bearer ${token}` } });
};
