<script setup>
import { computed } from 'vue';
import Breadcrumb from 'primevue/breadcrumb';
import Toast from 'primevue/toast';
import { useAuthStore } from '@/stores/auth';
import RapportAdmin from '@/views/rapport/RapportAdmin.vue';
import RapportMedecin from '@/views/rapport/RapportMedecin.vue';
import RapportReception from '@/views/rapport/RapportReception.vue';

const auth = useAuthStore();
const roles = computed(() => auth.user?.roles || []);
const isAdmin = computed(() => roles.value.includes('ROLE_ADMIN'));
const isMedecin = computed(() => roles.value.includes('ROLE_MEDECIN'));
const isReception = computed(() => roles.value.includes('ROLE_RECEPTION'));

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = computed(() => {
    if (isAdmin.value) return [{ label: 'Rapports' }, { label: 'Administration' }];
    if (isMedecin.value) return [{ label: 'Rapports' }, { label: 'Médecin' }];
    if (isReception.value) return [{ label: 'Rapports' }, { label: 'Réception' }];
    return [{ label: 'Rapports' }];
});
</script>

<template>
    <section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <Toast />

        <div class="mb-6 md:mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="pi pi-chart-bar text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                            Rapports
                        </h1>
                    </div>
                    <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base">
                        Visualisez vos rapports et statistiques
                    </p>
                </div> 
            </div>
            
            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
        </div>

        <RapportAdmin v-if="isAdmin" />
        <RapportMedecin v-else-if="isMedecin" />
        <RapportReception v-else-if="isReception" />
        <div v-else class="rounded-2xl border border-surface-200/60 bg-surface-0 p-6 text-surface-500 dark:border-surface-700 dark:bg-surface-900">
            Aucun tableau de bord disponible pour ce profil.
        </div>
    </section>
</template>
