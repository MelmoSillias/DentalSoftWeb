import http from '@/service/http';
import { apiPrefix } from '@/config';

const base = `${apiPrefix}/vitrine`;
export const getMailbox = async () => (await http.get(`${base}/mailbox`)).data;
export const saveMailbox = async (data) => (await http.put(`${base}/mailbox`, data, { timeout: 60000 })).data;
export const getMessages = async () => (await http.get(`${base}/messages`)).data;
export const syncMailbox = async () => (await http.post(`${base}/messages/sync`, {}, { timeout: 120000 })).data;
export const setMessageStatus = async (id, status) => (await http.patch(`${base}/messages/${id}`, { status })).data;
export const replyToMessage = async (id, data) => (await http.post(`${base}/messages/${id}/reply`, data)).data;
export const getPortals = async (params, signal) => (await http.get(`${base}/portals`, { params, signal })).data;
export const changePortalUsername = async (id, username) => (await http.patch(`${base}/portals/${id}/username`, { username })).data;
export const resetPortalPassword = async (id) => (await http.post(`${base}/portals/${id}/reset-password`)).data;
