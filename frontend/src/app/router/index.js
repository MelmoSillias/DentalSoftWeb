import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/domains/auth/stores/auth.js';
import { useInternetFeatures } from '@/shared/composables/useInternetFeatures.js';
import { isDeviceNotAllowedError } from '@/shared/api/http.js';
import { logAppError } from '@/shared/lib/appLogger.js';
import { getHomeRoute } from '@/app/lib/navigationHome.js';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/dashbord',
            component: () => import('@/app/layout/AppLayout.vue'),
            redirect: () => getHomeRoute(),
            meta: { requiresAuth: true },
            children: [
                {
                    path: '/accueil',
                    name: 'navigation-hub',
                    component: () => import('@/app/views/NavigationHub.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] }
                },
                {
                    path: '/dashboard',
                    name: 'dashboard',
                    component: () => import('@/app/views/Dashboard.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] }
                },
                {
                    path: '/focus',
                    name: 'focus-mode',
                    component: () => import('@/domains/focus/views/ModeFocus.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE', 'ROLE_MEDECIN'] }
                },
                // Agenda
                {
                    path: '/agenda/rendez-vous',
                    name: 'agenda-rendezvous',
                    component: () => import('@/domains/scheduling/views/RendezVous.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] }
                },
                {
                    path: '/agenda/evenements',
                    name: 'agenda-evenements',
                    component: () => import('@/domains/scheduling/views/Evenements.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                // patients
                {
                    path: '/patients/liste',
                    name: 'patients-liste',
                    component: () => import('@/domains/patient/views/Liste.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] }
                },
                {
                    path: '/patients/dossier/:patientId?',
                    name: 'patients-dossier',
                    component: () => import('@/domains/patient/views/DossierPatient.vue'),
                    props: (route) => {
                        const rawId = route.params.patientId;
                        // Path `/patients/dossier` yields patientId: '' — Number('') === 0, not a real id.
                        if (rawId == null || rawId === '') {
                            return { patientId: null };
                        }
                        const parsedId = Number(rawId);
                        return {
                            patientId: Number.isFinite(parsedId) && parsedId > 0 ? parsedId : null
                        };
                    },
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] }
                },
                // Consultations
                {
                    path: '/consultations/cards',
                    name: 'consultations-cards',
                    component: () => import('@/domains/care-delivery/views/CardsPending.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_MEDECIN'] }
                },
                {
                    path: '/consultations/table',
                    name: 'consultations-table',
                    component: () => import('@/domains/care-delivery/views/TableConsultations.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] }
                },
                {
                    path: '/consultations/services-cabinets',
                    name: 'consultations-services-cabinets',
                    component: () => import('@/domains/care-delivery/views/ServicesCabinets.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] }
                },
                {
                    path: '/consultations/form',
                    name: 'consultations-form',
                    component: () => import('@/domains/care-delivery/views/FicheForm.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_MEDECIN'] }
                },
                // Caisse
                {
                    path: '/caisse',
                    name: 'caisse',
                    component: () => import('@/domains/billing/views/Caisse.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION'] }
                },
                // Rapports
                {
                    path: '/rapports',
                    name: 'rapports',
                    component: () => import('@/domains/reporting/views/Rapports.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RECEPTION'] }
                },
                // Administration
                {
                    path: '/administration/consommables',
                    name: 'administration-consommables',
                    component: () => import('@/domains/inventory/views/Consommables.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                {
                    path: '/administration/salles',
                    name: 'administration-salles',
                    component: () => import('@/domains/scheduling/views/Salles.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                {
                    path: '/administration/finances',
                    name: 'administration-finances',
                    component: () => import('@/domains/billing/views/Finances.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                {
                    path: '/administration/utilisateurs',
                    name: 'administration-utilisateurs',
                    component: () => import('@/domains/settings/views/Utilisateurs.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                {
                    path: '/administration/gestionrh',
                    name: 'administration-gestionrh',
                    component: () => import('@/domains/workforce/views/GestionRH.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                {
                    path: '/administration/employes/:id',
                    name: 'administration-employee-details',
                    component: () => import('@/domains/workforce/views/EmployeeDetails.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                {
                    path: '/administration/notifications',
                    name: 'administration-notifications',
                    component: () => import('@/domains/communication/views/Notifications.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] }
                },
                {
                    path: '/administration/avis-retours-patients',
                    name: 'administration-avis-retours-patients',
                    redirect: '/vitrine/avis-patients',
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'] }
                },
                {
                    path: '/vitrine/avis-patients',
                    name: 'vitrine-avis-patients',
                    component: () => import('@/domains/communication/views/AvisRetoursPatients.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'] }
                },
                {
                    path: '/vitrine/messagerie',
                    name: 'vitrine-messagerie',
                    component: () => import('@/domains/communication/views/vitrine/Messagerie.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'] }
                },
                {
                    path: '/vitrine/portail-patient',
                    name: 'vitrine-portails',
                    component: () => import('@/domains/communication/views/vitrine/PortailPatient.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE'] }
                },
                {
                    path: '/profile',
                    name: 'profile',
                    component: () => import('@/domains/auth/views/Profile.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/manual',
                    name: 'manual',
                    component: () => import('@/domains/docs/views/UserManual.vue'),
                    meta: { requiresAuth: true }
                },
                // {
                //    path: '/parametres/fileOptions',
                //    name: 'settings-fileOptions',
                //    component: () => import('@/domains/settings/views/FilesOptions.vue'),
                //    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', "ROLE_SECRETAIRE", "ROLE_TOPO"] }
                // },
                {
                    path: '/parametres/apparence',
                    name: 'settings-appearance',
                    component: () => import('@/domains/settings/views/AppearanceSettings.vue'),
                    meta: { requiresAuth: true }
                },
                {
                    path: '/parametres/general-options',
                    name: 'settings-general-options',
                    component: () => import('@/domains/settings/views/GeneralOptions.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_SECRETAIRE', 'ROLE_TOPO'] }
                },
                {
                    path: '/administration/api-sms',
                    name: 'administration-api-sms',
                    component: () => import('@/domains/settings/views/SmsSettings.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'], requiresInternet: true }
                }
            ]
        },
        // Routes publiques (pas d'authentification requise)
        {
            path: '/',
            redirect: '/auth/login'
        },
        {
            path: '/auth/login',
            name: 'login',
            component: () => import('@/domains/auth/views/Login.vue'),
            meta: { requiresGuest: true } // Accessible uniquement aux non-connectés
        },
        {
            path: '/auth/device-pending',
            name: 'devicePending',
            component: () => import('@/domains/auth/views/DevicePending.vue'),
            meta: { requiresToken: true }
        },
        {
            path: '/auth/access',
            name: 'accessDenied',
            component: () => import('@/domains/auth/views/Access.vue')
        },
        {
            path: '/landing',
            name: 'landing',
            component: () => import('@/app/views/pages/Landing.vue')
        },
        {
            path: '/auth/error',
            name: 'error',
            component: () => import('@/domains/auth/views/Error.vue')
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'notfound',
            component: () => import('@/app/views/pages/NotFound.vue')
        }
    ]
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();
    const token = localStorage.getItem('token');

    // Route en attente d'autorisation appareil
    if (to.name === 'devicePending') {
        if (!token) {
            return next({ name: 'login' });
        }
        return next();
    }

    // 🔹 1. Si route protégée sans token → redirection login
    if (to.meta.requiresAuth && !token) {
        return next({ name: 'login' });
    }

    // 🔹 2. Si token présent mais utilisateur non chargé (uniquement pour routes protégées)
    if (token && !authStore.user && to.meta.requiresAuth) {
        authStore.token = token; // on le réinjecte dans le store
        try {
            // Vérifie d'abord la validité du token côté serveur
            const isValid = await authStore.validateToken();
            if (!isValid) return next({ name: 'login' });

            // Puis récupère l'utilisateur
            await authStore.fetchUser();
        } catch (error) {
            if (isDeviceNotAllowedError(error)) {
                return next({ name: 'devicePending' });
            }
            logAppError('router.validateToken', error);
            localStorage.removeItem('token');
            return next({ name: 'login' });
        }
    }

    // 🔹 3. Vérification des rôles
    if (to.meta.roles && authStore.user) {
        const hasRole = to.meta.roles.some((role) => authStore.user.roles.includes(role));
        if (!hasRole) {
            return next({ name: 'accessDenied' });
        }
    }

    if (to.meta.requiresInternet) {
        const { isInternetFeaturesEnabled } = useInternetFeatures();
        if (!isInternetFeaturesEnabled.value) {
            return next(getHomeRoute());
        }
    }

    // 🔹 4. Empêche un utilisateur déjà connecté d'aller sur /login
    if (to.meta.requiresGuest && token && authStore.user) {
        return next(getHomeRoute());
    }

    next();
});

export default router;
