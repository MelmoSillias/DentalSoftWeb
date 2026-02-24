<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import { useRapports } from '@/composables/useRapports';
import MedecinGlobalStatsSection from '@/components/rapport/medecin/MedecinGlobalStatsSection.vue';
import MedecinQuickStatsSection from '@/components/rapport/medecin/MedecinQuickStatsSection.vue';
import MedecinPeriodicDetailsSection from '@/components/rapport/medecin/MedecinPeriodicDetailsSection.vue';
import MedecinMedicalActsSection from '@/components/rapport/medecin/MedecinMedicalActsSection.vue';
import MedecinProfileSection from '@/components/rapport/medecin/MedecinProfileSection.vue';
import { useAuthStore } from '@/stores/auth';
const { medecinLoading, medecinData, fetchMedecinRapport, toIsoDate } = useRapports();
const auth = useAuthStore();

const startOfMonth = new Date();
startOfMonth.setDate(1);
const endOfMonth = new Date();

const range = ref([startOfMonth, endOfMonth]);
const hasLoaded = ref(false);

const periodLabel = computed(() => {
    const [start, end] = range.value || [];
    if (!start || !end) return 'Choisir période';
    return `${start.toLocaleDateString('fr-FR')} - ${end.toLocaleDateString('fr-FR')}`;
});

const connectedMedecinFullName = computed(() => {
    const identity = medecinData.value?.identity || {};
    const fromRapport = medecinData.value?.fullName
        || identity.fullName
        || [identity.prenom, identity.nom].filter(Boolean).join(' ').trim();
    if (fromRapport) return fromRapport;

    const user = auth.user || {};
    return [user.prenom, user.nom].filter(Boolean).join(' ').trim()
        || user.fullName
        || user.name
        || user.username
        || '';
});

async function refresh(silent = false) {
    const [start, end] = range.value || [];
    await fetchMedecinRapport({ from: toIsoDate(start), to: toIsoDate(end), silent });
}

watch(
    () => range.value,
    () => {
        if (!range.value?.[0] || !range.value?.[1]) return;
        if (!hasLoaded.value) {
            hasLoaded.value = true;
            refresh(true);
        } else {
            refresh(false);
        }
    },
    { deep: true }
);

onMounted(() => {
    refresh(true);
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
                    Bienvenue Dr {{ connectedMedecinFullName }}
                </h2>
                <p class="text-sm text-surface-500 dark:text-surface-400">Période : {{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <DatePicker
                    v-model="range"
                    selectionMode="range"
                    showIcon
                    dateFormat="dd/mm/yy"
                    class="w-72"
                    placeholder="Choisir période"
                />
                <Button label="Rafraîchir" icon="pi pi-refresh" outlined @click="refresh(false)" />
            </div>
        </div>

        <MedecinGlobalStatsSection :data="medecinData" :loading="medecinLoading" />
        <MedecinQuickStatsSection :stats="medecinData.stats" :loading="medecinLoading" />
        <MedecinPeriodicDetailsSection :period="medecinData.period" :loading="medecinLoading" />
        <MedecinMedicalActsSection :acts="medecinData.period?.paiements_period || []" :loading="medecinLoading" />
        <MedecinProfileSection :data="medecinData" :loading="medecinLoading" />
    </div>
</template>
