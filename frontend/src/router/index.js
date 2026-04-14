import AppLayout from '@/layout/AppLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/dashbord',
            component: AppLayout,
            redirect: '/dashboard',
            meta: { requiresAuth: true }, 
            children: [
                {
                    path: '/dashboard',
                    name: 'dashboard',
                    component: () => import('@/views/Dashboard.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'], fixedWidth: true }
                },
                // Agenda
                {
                    path: '/agenda/rendez-vous',
                    name: 'agenda-rendezvous',
                    component: () => import('@/views/agenda/RendezVous.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'], fixedWidth: true }
                },
                {
                    path: '/agenda/evenements',
                    name: 'agenda-evenements',
                    component: () => import('@/views/agenda/Evenements.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] , fixedWidth: true}
                }, 
                // patients
                {
                    path: '/patients/liste',
                    name: 'patients-liste',
                    component: () => import('@/views/patients/Liste.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] , fixedWidth: true}
                },
                {
                    path: '/patients/dossier/:patientId?',
                    name: 'patients-dossier',
                    component: () => import('@/views/patients/DossierPatient.vue'),
                    props: (route) => {
                        const rawId = route.params.patientId;
                        const parsedId = Number(rawId);
                        return {
                            patientId: Number.isNaN(parsedId) ? null : parsedId
                        };
                    },
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'] , fixedWidth: true}
                },
                // Consultations 
                {
                    path: '/consultations/cards',
                    name: 'consultations-cards',
                    component: () => import('@/views/consultations/CardsPending.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_MEDECIN'], fixedWidth: true }
                },
                {
                    path: '/consultations/table',
                    name: 'consultations-table',
                    component: () => import('@/views/consultations/TableConsultations.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'], fixedWidth: true }
                },  
                {
                    path: '/consultations/form',
                    name: 'consultations-form',
                    component: () => import('@/views/consultations/FicheForm.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_MEDECIN'], fixedWidth: true }
                },  
                {
                    path: '/consultations/form-legacy',
                    name: 'consultations-form-legacy',
                    component: () => import('@/views/consultations/ConsultationForm.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_MEDECIN'] , fixedWidth: true}
                },

                // Caisse
                {
                    path: '/caisse',
                    name: 'caisse',
                    component: () => import('@/views/caisse/Caisse.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_RECEPTION'], fixedWidth: true }
                }, 
                // Rapports
                {
                    path: '/rapports',
                    name: 'rapports',
                    component: () => import('@/views/rapport/Rapports.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RECEPTION'], fixedWidth: true }
                }, 
                // Administration
                {
                    path: '/administration/consommables',
                    name: 'administration-consommables',
                    component: () => import('@/views/administration/Consommables.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] , fixedWidth: true}
                }, 
                {
                    path: '/administration/salles',
                    name: 'administration-salles',
                    component: () => import('@/views/administration/Salles.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] , fixedWidth: true}
                }, 
                {
                    path: '/administration/finances',
                    name: 'administration-finances',
                    component: () => import('@/views/administration/Finances.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'], fixedWidth: true } // Réservé aux admins
                },
                {
                    path: '/administration/utilisateurs',
                    name: 'administration-utilisateurs',
                    component: () => import('@/views/administration/Utilisateurs.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'] , fixedWidth: true}
                },
                {
                    path: '/administration/gestionrh',
                    name: 'administration-gestionrh',
                    component: () => import('@/views/administration/GestionRH.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'], fixedWidth: true }
                },
                {
                    path: '/administration/employes/:id',
                    name: 'administration-employee-details',
                    component: () => import('@/views/administration/EmployeeDetails.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'], fixedWidth: true }
                },
                {
                    path: '/administration/notifications',
                    name: 'administration-notifications',
                    component: () => import('@/views/administration/Notifications.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'], fixedWidth: true }
                },
                
                {
                    path: '/profile',
                    name: 'profile',
                    component: () => import('@/views/Profile.vue'),
                    meta: { requiresAuth: true , fixedWidth: true}
                },
                {
                    path: '/manual',
                    name: 'manual',
                    component: () => import('@/views/manual/UserManual.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'], fixedWidth: true }
                },
                // {
                //    path: '/parametres/fileOptions',
                //    name: 'settings-fileOptions',
                //    component: () => import('@/views/settings/FilesOptions.vue'),
                //    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', "ROLE_SECRETAIRE", "ROLE_TOPO"], fixedWidth: true }
                // },
                {
                    path: '/parametres/apparence',
                    name: 'settings-apparence',
                    component: () => import('@/views/settings/GeneralOptions.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN', "ROLE_SECRETAIRE", "ROLE_TOPO"], fixedWidth: true }
                },
                {
                    path: '/administration/api-sms',
                    name: 'administration-api-sms',
                    component: () => import('@/views/settings/SmsSettings.vue'),
                    meta: { requiresAuth: true, roles: ['ROLE_ADMIN'], fixedWidth: true }
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
            component: () => import('@/views/pages/auth/Login.vue'),
            meta: { requiresGuest: true } // Accessible uniquement aux non-connectés
        },
        {
            path: '/auth/access',
            name: 'accessDenied',
            component: () => import('@/views/pages/auth/Access.vue')
        },
        {
            path: '/landing',
            name: 'landing',
            component: () => import('@/views/pages/Landing.vue')
        },
        {
            path: '/auth/error',
            name: 'error',
            component: () => import('@/views/pages/auth/Error.vue')
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'notfound',
            component: () => import('@/views/pages/NotFound.vue')
        }
    ]
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();
    const token = localStorage.getItem('token');

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
            console.error('Erreur lors de la validation du token:', error);
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

    // 🔹 4. Empêche un utilisateur déjà connecté d'aller sur /login
    if (to.meta.requiresGuest && token && authStore.user) {
        return next({ name: 'dashboard' });
    }

    next();
});

export default router;
