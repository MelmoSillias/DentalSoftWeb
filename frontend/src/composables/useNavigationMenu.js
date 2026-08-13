import router from '@/router';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { useAuthStore } from '@/stores/auth';
import { computed } from 'vue';

/**
 * Shared role-filtered navigation model for classic sidebar and hub cards.
 */
export function useNavigationMenu() {
    const auth = useAuthStore();
    const { isInternetFeaturesEnabled } = useInternetFeatures();
    const roles = computed(() => auth.user?.roles || []);

    const hasRole = (role) => roles.value.includes(role);
    const hasAnyRole = (list) => list.some((r) => roles.value.includes(r));
    const isAdmin = computed(() => hasRole('ROLE_ADMIN'));
    const isMedecin = computed(() => hasRole('ROLE_MEDECIN'));
    const isReception = computed(() => hasAnyRole(['ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE']));

    const settingsSection = {
        label: 'Paramètres',
        items: [
            { label: 'Paramètres généraux', icon: 'pi pi-fw pi-cog', iconKey: 'general-options', to: '/parametres/general-options' },
            { label: 'API SMS', icon: 'pi pi-fw pi-send', iconKey: 'api-sms', to: '/administration/api-sms', adminOnly: true, requiresInternet: true }
        ]
    };

    const model = computed(() => {
        const menu = [
            {
                label: 'Accueil',
                items: [
                    { label: 'Dashboard', icon: 'pi pi-fw pi-home', iconKey: 'dashboard', to: '/dashboard' },
                    {
                        label: 'Mode Focus',
                        icon: 'pi pi-fw pi-bolt',
                        iconKey: 'focus-mode',
                        to: router.resolve({ name: 'focus-mode' }).href,
                        separator: true
                    }
                ]
            }
        ];

        if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
            const agendaItems = [];
            agendaItems.push({
                label: 'Rendez-Vous',
                icon: 'pi pi-fw pi-calendar',
                iconKey: 'agenda-rendezvous',
                to: router.resolve({ name: 'agenda-rendezvous' }).href
            });
            if (isAdmin.value) {
                agendaItems.push({
                    label: 'Evenements',
                    icon: 'pi pi-fw pi-star',
                    iconKey: 'agenda-evenements',
                    to: router.resolve({ name: 'agenda-evenements' }).href
                });
            }
            menu.push({ label: 'Agenda', items: agendaItems });
        }

        if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
            menu.push({
                label: 'Patients',
                items: [
                    {
                        label: 'Liste',
                        icon: 'pi pi-fw pi-users',
                        iconKey: 'patients-liste',
                        to: router.resolve({ name: 'patients-liste' }).href
                    },
                    {
                        label: 'Dossier',
                        icon: 'pi pi-fw pi-folder',
                        iconKey: 'patients-dossier',
                        to: router.resolve({ name: 'patients-dossier', params: { patientId: null } }).href
                    }
                ]
            });
        }

        if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
            const consultationItems = [];
            if (isMedecin.value || isAdmin.value) {
                consultationItems.push({
                    label: "File d'attente",
                    icon: 'pi pi-fw pi-hourglass',
                    iconKey: 'consultations-cards',
                    to: router.resolve({ name: 'consultations-cards' }).href
                });
            }
            consultationItems.push({
                label: 'Historique',
                icon: 'pi pi-fw pi-list',
                iconKey: 'consultations-table',
                to: router.resolve({ name: 'consultations-table' }).href
            });
            menu.push({
                label: 'Consultations',
                items: consultationItems
            });
        }

        if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION'])) {
            menu.push({
                label: 'Caisse',
                items: [
                    {
                        label: 'Encaissements',
                        icon: 'pi pi-fw pi-briefcase',
                        iconKey: 'caisse',
                        to: router.resolve({ name: 'caisse' }).href
                    }
                ]
            });
        }

        if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
            menu.push({
                label: 'Rapports',
                items: [
                    {
                        label: 'Statistiques',
                        icon: 'pi pi-fw pi-chart-bar',
                        iconKey: 'rapports',
                        to: router.resolve({ name: 'rapports' }).href
                    }
                ]
            });
        }

        const adminItems = [];
        if (isAdmin.value) {
            adminItems.push(
                {
                    label: 'Consommables',
                    icon: 'pi pi-fw pi-box',
                    iconKey: 'consommables',
                    to: router.resolve({ name: 'administration-consommables' }).href,
                    separator: true
                },
                {
                    label: 'Salles',
                    icon: 'pi pi-fw pi-building',
                    iconKey: 'salles',
                    to: router.resolve({ name: 'administration-salles' }).href
                },
                {
                    label: 'Gestion RH',
                    icon: 'pi pi-fw pi-users',
                    iconKey: 'gestion-rh',
                    to: router.resolve({ name: 'administration-gestionrh' }).href
                },
                {
                    label: 'Finances',
                    icon: 'pi pi-fw pi-wallet',
                    iconKey: 'finances',
                    to: router.resolve({ name: 'administration-finances' }).href
                },
                {
                    label: 'Utilisateurs',
                    icon: 'pi pi-fw pi-id-card',
                    iconKey: 'utilisateurs',
                    to: router.resolve({ name: 'administration-utilisateurs' }).href
                },
                {
                    label: 'Notifications',
                    icon: 'pi pi-fw pi-bell',
                    iconKey: 'notifications',
                    to: router.resolve({ name: 'administration-notifications' }).href
                },
                {
                    label: 'Avis & retours patients',
                    icon: 'pi pi-fw pi-comments',
                    iconKey: 'avis-retours',
                    to: router.resolve({ name: 'administration-avis-retours-patients' }).href
                }
            );
        }

        if (adminItems.length) {
            menu.push({ label: 'Administration', items: adminItems });
        }

        if (hasAnyRole(['ROLE_ADMIN', 'ROLE_SECRETAIRE', 'ROLE_TOPO'])) {
            menu.push({
                ...settingsSection,
                items: settingsSection.items.filter((item) => {
                    if (item.adminOnly && !isAdmin.value) {
                        return false;
                    }
                    if (item.requiresInternet && !isInternetFeaturesEnabled.value) {
                        return false;
                    }
                    return true;
                })
            });
        }

        return menu;
    });

    return {
        model,
        roles,
        isAdmin,
        isMedecin,
        isReception
    };
}
