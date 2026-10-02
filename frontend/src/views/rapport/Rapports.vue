<script setup>
import { computed } from 'vue';
import { useGuidedTour } from '@/composables/useGuidedTour';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import { useAuthStore } from '@/stores/auth';
import RapportAdmin from '@/views/rapport/RapportAdmin.vue';
import RapportMedecin from '@/views/rapport/RapportMedecin.vue';
import RapportReception from '@/views/rapport/RapportReception.vue';

const auth = useAuthStore();
const roles = computed(() => auth.user?.roles || []);
const isAdmin = computed(() => roles.value.includes('ROLE_ADMIN'));
const isMedecin = computed(() => roles.value.includes('ROLE_MEDECIN'));
const isReception = computed(() => roles.value.includes('ROLE_RECEPTION') || roles.value.includes('ROLE_RECEPTIONNISTE'));
const reportRole = computed(() => {
    if (isAdmin.value) return 'admin';
    if (isMedecin.value) return 'medecin';
    if (isReception.value) return 'reception';
    return 'admin';
});

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = computed(() => {
    if (isAdmin.value) return [{ label: 'Rapports' }, { label: 'Administration' }];
    if (isMedecin.value) return [{ label: 'Rapports' }, { label: 'Médecin' }];
    if (isReception.value) return [{ label: 'Rapports' }, { label: 'Réception' }];
    return [{ label: 'Rapports' }];
});

useGuidedTour({
    routeName: 'rapports',
    getStepContext: () => ({ role: reportRole.value }),
    errorMessage: 'Impossible de lancer le tour de la page rapports.'
});
</script>

<template>
    <PageShell>
        <template #header>
            <PageHeader
                title="Rapports"
                icon="pi pi-chart-bar"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            />
        </template>

        <RapportAdmin v-if="isAdmin" />
        <RapportMedecin v-else-if="isMedecin" />
        <RapportReception v-else-if="isReception" />
        <div v-else class="rounded-2xl border border-surface-200/60 bg-surface-0 p-6 text-surface-500 dark:border-surface-700 dark:bg-surface-900">Aucun tableau de bord disponible pour ce profil.</div>
    </PageShell>
</template>
