import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

import LoginView from '../views/auth/LoginView.vue';
import DashboardView from '../views/dashboard/DashboardView.vue';
import ConsultationsView from '../views/consultations/ConsultationsView.vue';
import AppointmentsView from '../views/appointments/AppointmentsView.vue';
import ProfileView from '../views/profile/ProfileView.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: { public: true, title: 'Connexion', breadcrumb: ['Auth', 'Connexion'] }
    },
    {
        path: '/',
        redirect: '/dashboard'
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardView,
        meta: { requiresAuth: true, title: 'Dashboard', breadcrumb: ['Accueil', 'Dashboard'] }
    },
    {
        path: '/consultations',
        name: 'consultations',
        component: ConsultationsView,
        meta: { requiresAuth: true, title: 'Consultations', breadcrumb: ['Espace patient', 'Consultations'] }
    },
    {
        path: '/rendez-vous',
        name: 'appointments',
        component: AppointmentsView,
        meta: { requiresAuth: true, title: 'Rendez-vous', breadcrumb: ['Espace patient', 'Rendez-vous'] }
    },
    {
        path: '/profil',
        name: 'profile',
        component: ProfileView,
        meta: { requiresAuth: true, title: 'Profil', breadcrumb: ['Espace patient', 'Profil'] }
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    auth.hydrate();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.name === 'login' && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    return true;
});

export default router;
