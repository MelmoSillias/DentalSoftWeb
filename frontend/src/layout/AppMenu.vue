<script setup>
import router from '@/router';
import { useAuthStore } from '@/stores/auth';
import { computed, ref } from 'vue';
import AppMenuItem from './AppMenuItem.vue';

const auth = useAuthStore();
const roles = computed(() => auth.user?.roles || []);

// Raccourcis de rôles
const hasRole = (role) => roles.value.includes(role);
const hasAnyRole = (list) => list.some((r) => roles.value.includes(r));
const isAdmin = computed(() => hasRole('ROLE_ADMIN'));
const isMedecin = computed(() => hasRole('ROLE_MEDECIN'));
const isReception = computed(() => hasRole('ROLE_RECEPTION'));

// Menu de base
const baseModel = ref([
    {
        label: 'Accueil',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/dashboard' }]
    },
    // Les autres sections seront ajoutées selon les rôles dans model computed
]);

const settingsSection = {
    label: 'Paramètres',
    items: [
        { label: 'Paramètres généraux', icon: 'pi pi-fw pi-cog', to: '/parametres/apparence' },
        { label: 'Options des fichiers', icon: 'pi pi-fw pi-file-edit', to: '/parametres/fileOptions' },

    ]
};

// Modèle final : on ajoute la section admin seulement si l'utilisateur est admin
const model = computed(() => {
    const menu = [...baseModel.value];

    // Agenda (mêmes rôles que le routage)
    if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
        const agendaItems = [];
        agendaItems.push({ label: 'Rendez-Vous', icon: 'pi pi-fw pi-calendar', to: router.resolve({ name: 'agenda-rendezvous' }).href });
        if (isAdmin.value) {
            agendaItems.push({ label: 'Evenements', icon: 'pi pi-fw pi-star', to: router.resolve({ name: 'agenda-evenements' }).href }); 
        }
        menu.push({ label: 'Agenda', items: agendaItems });
    }

    // Patients (mêmes rôles que le routage)
    if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
        menu.push({
            label: 'Patients',
            items: [
                { label: 'Liste', icon: 'pi pi-fw pi-users', to: router.resolve({ name: 'patients-liste' }).href },
                { label: 'Dossier', icon: 'pi pi-fw pi-folder', to: router.resolve({ name: 'patients-dossier', params: { patientId: null } }).href }
            ]
        });
    }

    // Consultations (médecin + admin + réception)
    if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
        const consultationItems = [];
        if (isMedecin.value || isAdmin.value) {
            consultationItems.push({ label: 'File d\'attente', icon: 'pi pi-fw pi-hourglass', to: router.resolve({ name: 'consultations-cards' }).href });
        }
        consultationItems.push({ label: 'Historique', icon: 'pi pi-fw pi-list', to: router.resolve({ name: 'consultations-table' }).href });
        menu.push({
            label: 'Consultations',
            items: consultationItems 
        });
    }

    // Caisse (réception + admin)
    if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION'])) {
        menu.push({
            label: 'Caisse',
            items: [
                { label: 'Encaissements', icon: 'pi pi-fw pi-briefcase', to: router.resolve({ name: 'caisse' }).href },
            ]
        });
    }

    // Rapports (mêmes rôles que le routage)
    if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
        menu.push({
            label: 'Rapports',
            items: [
                { label: 'Statistiques', icon: 'pi pi-fw pi-chart-bar', to: router.resolve({ name: 'rapports' }).href },
            ]
        });
    }

    // Administration (admin + sous-section RH pour secrétaire)
    const adminItems = [];
    if (isAdmin.value) {
        adminItems.push(
            { label: 'Consommables', icon: 'pi pi-fw pi-box', to: router.resolve({ name: 'administration-consommables' }).href },
            { label: 'Salles', icon: 'pi pi-fw pi-building', to: router.resolve({ name: 'administration-salles' }).href },
            { label: 'Gestion RH', icon: 'pi pi-fw pi-users', to: router.resolve({ name: 'administration-gestionrh' }).href },
            { label: 'Finances', icon: 'pi pi-fw pi-wallet', to: router.resolve({ name: 'administration-finances' }).href },
            { label: 'Utilisateurs', icon: 'pi pi-fw pi-id-card', to: router.resolve({ name: 'administration-utilisateurs' }).href },
            { label: 'Notifications', icon: 'pi pi-fw pi-bell', to: router.resolve({ name: 'administration-notifications' }).href },

        );
    }

    if (adminItems.length) {
        menu.push({ label: 'Administration', items: adminItems });
    }

    // Paramètres : mêmes rôles que dans le routage (admin, secrétaire, topo)
    // if (hasAnyRole(['ROLE_ADMIN', 'ROLE_RECEPTION', 'ROLE_MEDECIN'])) {
    //     menu.push(settingsSection);
    // }

    return menu;
});


</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="i">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped>
/* Ajoutez ici vos styles personnalisés si nécessaire */
</style>
