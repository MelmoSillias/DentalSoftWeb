import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

import LoginView from '../views/auth/LoginView.vue';
import DashboardView from '../views/dashboard/DashboardView.vue';
import ConsultationsView from '../views/consultations/ConsultationsView.vue';
import AppointmentsView from '../views/appointments/AppointmentsView.vue';
import PaymentsView from '../views/payments/PaymentsView.vue';
import DocumentsView from '../views/documents/DocumentsView.vue';
import ProfileView from '../views/profile/ProfileView.vue';
import AnonymousReviewView from '../views/public/AnonymousReviewView.vue';
import PortalClosedView from '../views/public/PortalClosedView.vue';
import { usePortalSettingsStore } from '../stores/portalSettings';

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
    },
    {
        path: '/paiements',
        name: 'payments',
        component: PaymentsView,
        meta: { requiresAuth: true, title: 'Paiements', breadcrumb: ['Espace patient', 'Paiements'] }
    },
    {
        path: '/documents',
        name: 'documents',
        component: DocumentsView,
        meta: { requiresAuth: true, title: 'Documents', breadcrumb: ['Espace patient', 'Documents'] }
    },
    {
        path: '/avis-anonyme',
        name: 'anonymous-review',
        component: AnonymousReviewView,
        meta: { public: true, title: 'Avis anonyme', breadcrumb: ['Public', 'Avis anonyme'] }
    },
    {
        path: '/portail-ferme',
        name: 'portal-closed',
        component: PortalClosedView,
        meta: { public: true, title: 'Portail fermé', breadcrumb: ['Public', 'Portail fermé'] }
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes
});

router.beforeEach((to) => {
    const auth = useAuthStore();
    const portal = usePortalSettingsStore();
    auth.hydrate();

    return portal.load().then(() => {
        const isPublicRoute = to.meta.public === true;
        const isAnonymousReviewRoute = to.name === 'anonymous-review';
        const isPortalClosedRoute = to.name === 'portal-closed';

        if (
            portal.isPortalClosed
            && to.meta.requiresAuth
            && !isAnonymousReviewRoute
            && !isPortalClosedRoute
        ) {
            return { name: 'portal-closed' };
        }

        if (to.meta.requiresAuth && !auth.isAuthenticated) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        if (to.name === 'login' && auth.isAuthenticated) {
            return portal.isPortalClosed ? { name: 'portal-closed' } : { name: 'dashboard' };
        }

        if (portal.isPortalClosed && isPortalClosedRoute && isPublicRoute) {
            return true;
        }

        return true;
    });
});

export default router;
