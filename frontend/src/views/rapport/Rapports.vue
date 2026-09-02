<script setup>
import { computed } from 'vue';
import { useGuidedTour } from '@/composables/useGuidedTour';
import Breadcrumb from 'primevue/breadcrumb';
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

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
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
    <section class="min-h-screen p-3 md:p-4 lg:p-5 transition-colors duration-300">
        <AppToast />

        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <i class="pi pi-chart-bar text-primary-600 dark:text-primary-400 text-lg"></i>
                <h1 class="text-xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">Rapports</h1>
            </div>
            <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-xs md:text-sm" />
        </div>

        <RapportAdmin v-if="isAdmin" />
        <RapportMedecin v-else-if="isMedecin" />
        <RapportReception v-else-if="isReception" />
        <div v-else class="rounded-2xl border border-surface-200/60 bg-surface-0 p-6 text-surface-500 dark:border-surface-700 dark:bg-surface-900">Aucun tableau de bord disponible pour ce profil.</div>
    </section>
</template>
