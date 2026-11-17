import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/domains/auth/index.js';

import LoginView from '@/domains/auth/views/LoginView.vue';
import DashboardView from '@/domains/patient-portal/views/dashboard/DashboardView.vue';
import ConsultationsView from '@/domains/patient-portal/views/consultations/ConsultationsView.vue';
import AppointmentsView from '@/domains/patient-portal/views/appointments/AppointmentsView.vue';
import PaymentsView from '@/domains/patient-portal/views/payments/PaymentsView.vue';
import DocumentsView from '@/domains/patient-portal/views/documents/DocumentsView.vue';
import ProfileView from '@/domains/patient-portal/views/profile/ProfileView.vue';
import AnonymousReviewView from '@/domains/patient-portal/views/public/AnonymousReviewView.vue';
import PortalClosedView from '@/domains/patient-portal/views/public/PortalClosedView.vue';
import { usePortalSettingsStore } from '@/domains/patient-portal/index.js';

import { createPortalGuard } from './guard.js';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: {
            public: true,
            title: 'Connexion',
            breadcrumb: ['Auth', 'Connexion'],
        },
    },
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: DashboardView,
        meta: {
            requiresAuth: true,
            title: 'Vue d’ensemble',
            breadcrumb: ['Accueil', 'Vue d’ensemble'],
        },
    },
    {
        path: '/consultations',
        name: 'consultations',
        component: ConsultationsView,
        meta: {
            requiresAuth: true,
            title: 'Consultations',
            breadcrumb: ['Espace patient', 'Consultations'],
        },
    },
    {
        path: '/rendez-vous',
        name: 'appointments',
        component: AppointmentsView,
        meta: {
            requiresAuth: true,
            title: 'Rendez-vous',
            breadcrumb: ['Espace patient', 'Rendez-vous'],
        },
    },
    {
        path: '/profil',
        name: 'profile',
        component: ProfileView,
        meta: {
            requiresAuth: true,
            title: 'Profil',
            breadcrumb: ['Espace patient', 'Profil'],
        },
    },
    {
        path: '/paiements',
        name: 'payments',
        component: PaymentsView,
        meta: {
            requiresAuth: true,
            title: 'Paiements',
            breadcrumb: ['Espace patient', 'Paiements'],
        },
    },
    {
        path: '/documents',
        name: 'documents',
        component: DocumentsView,
        meta: {
            requiresAuth: true,
            title: 'Documents',
            breadcrumb: ['Espace patient', 'Documents'],
        },
    },
    {
        path: '/avis-anonyme',
        name: 'anonymous-review',
        component: AnonymousReviewView,
        meta: {
            public: true,
            title: 'Avis anonyme',
            breadcrumb: ['Public', 'Avis anonyme'],
        },
    },
    {
        path: '/portail-ferme',
        name: 'portal-closed',
        component: PortalClosedView,
        meta: {
            public: true,
            title: 'Portail fermé',
            breadcrumb: ['Public', 'Portail fermé'],
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

router.beforeEach(createPortalGuard({ getAuth: useAuthStore, getPortal: usePortalSettingsStore }));

router.afterEach((to) => {
    document.title = `${to.meta.title || 'Mon espace'} · Espace patient`;
});

export default router;
