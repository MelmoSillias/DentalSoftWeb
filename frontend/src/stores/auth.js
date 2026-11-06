// src/stores/auth.js
import { defineStore } from 'pinia';
import http, { isDeviceNotAllowedError } from '@/service/http';
import { resetMercureClient } from '@/composables/realtime/useMercureClient';
import { releasePushSubscription } from '@/composables/webPushSession';
import { useRealtimeStore } from '@/stores/realtime';
import { useNotificationsStore } from '@/stores/notifications';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token') || null,
        loading: false,
        sessionVersion: 0,
        error: null,
        deviceBlockMessage: null,
        deviceBlockStatus: null
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
            const version = ++this.sessionVersion;
            this.loading = true;
            this.error = null;
            this.clearDeviceBlock();
            try {
                const res = await http.post('login_check', { username, password });
                if (version !== this.sessionVersion) throw new Error('Connexion annulée.');
                if (typeof res.data?.token !== 'string' || !res.data.token) {
                    throw new Error('Réponse de connexion invalide.');
                }
                this.user = null;
                this.token = res.data.token;
                localStorage.setItem('token', this.token);
                await this.fetchUser();
            } catch (err) {
                if (version !== this.sessionVersion) throw err;
                if (isDeviceNotAllowedError(err)) {
                    this.setDeviceBlock(err.response?.data?.message, err.response?.data?.status);
                    throw err;
                }
                this.logout();
                this.error = err.response?.status === 401 ? 'Identifiants incorrects.' : err.response?.data?.message || 'Erreur de connexion';
                throw err;
            } finally {
                if (version === this.sessionVersion) this.loading = false;
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
            const requestedToken = this.token;
            try {
                const response = await http.get('token/validate');
                if (this.token !== requestedToken) return false;
                const valid = response.data?.status === 'valid';
                if (!valid) this.logout();
                return valid;
            } catch (error) {
                if (this.token !== requestedToken) return false;
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
            const requestedToken = this.token;
            try {
                const res = await http.get('me');
                if (!requestedToken || this.token !== requestedToken) throw new Error('Session modifiée.');
                this.user = {
                    ...res.data.user,
                    employee: res.data.employee ?? null,
                    // Keep the linked employee identity handy for doctor scoped forms.
                    medecinId: res.data.employee?.id ?? res.data.user?.medecinId ?? null,
                    prenom: res.data.employee?.prenom ?? res.data.user?.prenom ?? null,
                    nom: res.data.employee?.nom ?? res.data.user?.nom ?? null
                };
                this.clearDeviceBlock();
                const { useInternetFeatures } = await import('@/composables/useInternetFeatures');
                const { syncFromServer } = useInternetFeatures();
                await syncFromServer(this.token);
                import('@/composables/useBrowserNotifications').then(({ useBrowserNotifications }) => {
                    useBrowserNotifications().syncSubscription();
                });
                return res.data;
            } catch (err) {
                if (this.token !== requestedToken) throw err;
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
            this.sessionVersion += 1;
            this.loading = false;
            releasePushSubscription(this.token);
            import('@/composables/useBrowserNotifications').then(({ useBrowserNotifications }) => {
                useBrowserNotifications().markPushSubscriptionReleased();
            });
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
