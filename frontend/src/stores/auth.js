// src/stores/auth.js
import { defineStore } from 'pinia';
import http from '@/service/http';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem('token') || null,
        loading: false,
        error: null
    }),

    actions: {
        async login(username, password) {
            this.loading = true;
            this.error = null;
            try {
                const res = await http.post('login_check', { username, password });
                this.token = res.data.token;
                localStorage.setItem('token', this.token); 
                await this.fetchUser(); 
            } catch (err) {
                this.error = err.response?.data?.message || 'Erreur de connexion';
                console.log(err);
                throw err;
            } finally {
                this.loading = false;
            }
        },

        async validateToken() {
            try {
                const response = await http.get('token/validate');
                console.log('Token valide:', response.data);
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
            } catch (err) {
                if (err.response && err.response.status === 401) {
                    this.logout();
                }
            }
        },

        logout() {
            this.user = null;
            this.token = null;
            localStorage.removeItem('token');
        }
    }
});
