// src/stores/auth.js
import { defineStore } from 'pinia';
import http, { isDeviceNotAllowedError } from '@/service/http';
import { resetMercureClient } from '@/composables/realtime/useMercureClient';
import { useRealtimeStore } from '@/stores/realtime';
import { useNotificationsStore } from '@/stores/notifications';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token') || null,
        loading: false,        error: null,
        deviceBlockMessage: null,
        deviceBlockStatus: null,
    }),

    actions: {
        setDeviceBlock(message, status = 'pending') {
            this.deviceBlockMessage = message || null;
            this.deviceBlockStatus = status || 'pending';
        },

        clearDeviceBlock() {
            this.deviceBlockMessage = null;
            this.deviceBlockStatus = null;
        },

        async login(username, password) {
            this.loading = true;
            this.error = null;
            this.clearDeviceBlock();
            try {
                const res = await http.post('login_check', { username, password });
                this.token = res.data.token;
                localStorage.setItem('token', this.token); 
                await this.fetchUser(); 
            } catch (err) {
                if (isDeviceNotAllowedError(err)) {
                    this.setDeviceBlock(err.response?.data?.message, err.response?.data?.status);
                    throw err;
                }
                this.logout();
                this.error = err.response?.data?.message || 'Erreur de connexion'; 
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async checkDeviceStatus() {
            try {
                const response = await http.get('device/status');
                return response.data;
            } catch (error) {
                const data = error?.response?.data;
                if (error?.response?.status === 403 && data?.status) {
                    return data;
                }
                throw error;
            }
        },

        async validateToken() {
            try {
                const response = await http.get('token/validate'); 
                return true;
            } catch (error) { 
                if (error.response && error.response.status === 401) { 
                    this.logout();
                } else {
                    
                }
                return false;
            }
        },

        async register(username, password) {
            return http.post('register', { username, password });
        },

        async fetchUser() {
            try {
                const res = await http.get('me');
                this.user = res.data.user;
                this.clearDeviceBlock();
                const { useInternetFeatures } = await import('@/composables/useInternetFeatures');
                const { syncFromServer } = useInternetFeatures();
                await syncFromServer(this.token);
                return res.data;
            } catch (err) {
                if (isDeviceNotAllowedError(err)) {
                    this.setDeviceBlock(err.response?.data?.message, err.response?.data?.status);
                    throw err;
                }
                if (err.response && err.response.status === 401) {
                    this.logout();
                }
                throw err;
            }
        },

        logout() {
            this.user = null;
            this.token = null;
            this.clearDeviceBlock();
            localStorage.removeItem('token');
            resetMercureClient();
            useRealtimeStore().reset();
            useNotificationsStore().setNotifications([]);
            import('@/composables/useInternetFeatures').then(({ useInternetFeatures }) => {
                useInternetFeatures().reset();
            });
        }
    }
});
