import auth from '@/domains/auth/stores/auth.js';
import router from '@/app/router/index.js'; // ton router

auth.api.interceptors.response.use(
    (response) => response,
    (err) => {
        if (err.response && err.response.status === 401) {
            auth.logout();
            router.push({ name: 'login' });
        }
        return Promise.reject(err);
    }
);
