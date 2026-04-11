// service/http.js
// Client Axios centralisé avec intercepteurs pour gestion automatique du token et du logout sur 401
import axios from 'axios';
import { apiPrefix } from '@/config';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { getDeviceMetadata } from '@/utils/deviceFingerprint';

const http = axios.create({
    baseURL: apiPrefix
});

// Intercepteur requêtes: ajoute Authorization si token présent
http.interceptors.request.use(
    (config) => {
        try {
            const auth = useAuthStore();
            const token = auth?.token || localStorage.getItem('token');
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }

            const device = getDeviceMetadata();
            config.headers['X-Device-Id'] = device.id;
            config.headers['X-Device-Name'] = device.name;
            config.headers['X-Device-Type'] = device.type;
        } catch (_) {
            // Pinia pas initialisé encore, fallback localStorage déjà géré
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Intercepteur réponses: gère 401 -> logout + redirection login
http.interceptors.response.use(
    (response) => response,
    (error) => {
        // Si réponse HTTP et statut 401 => token invalide/expiré
        if (error.response && error.response.status === 401) {
            try {
                const auth = useAuthStore();
                auth?.logout();
            } catch (_) {
                localStorage.removeItem('token');
            }
            if (router.currentRoute.value.name !== 'login') {
                router.replace({ name: 'login' });
            }
        }
        return Promise.reject(error);
    }
);

export default http;
