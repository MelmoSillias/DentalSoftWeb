<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import DatePicker from 'primevue/datepicker';
import { useRapports } from '@/composables/useRapports';
import ReceptionStatsSection from '@/components/rapport/reception/ReceptionStatsSection.vue';
import DoctorReportsTable from '@/components/rapport/common/DoctorReportsTable.vue';

const { receptionLoading, receptionStats, receptionDoctorReports, fetchReceptionRapport, toIsoDate } = useRapports();
const selectedDate = ref(new Date());
const hasLoaded = ref(false);

const periodLabel = computed(() => selectedDate.value?.toLocaleDateString('fr-FR'));

async function refresh(silent = false) {
    await fetchReceptionRapport({ date: toIsoDate(selectedDate.value), silent });
}

watch(
    () => selectedDate.value,
    () => {
        if (!selectedDate.value) return;
        if (!hasLoaded.value) {
            hasLoaded.value = true;
            refresh(true);
        } else {
            refresh(false);
        }
    }
);

onMounted(() => {
    refresh(true);
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4" data-tour="rapports-reception.date">
            <div>
                <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">Bienvenue, Réceptionniste</h2>
                <p class="text-sm text-surface-500 dark:text-surface-400">Journée du {{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3" data-tour="rapports-reception.print">
                <DatePicker
                    v-model="selectedDate"
                    showIcon
                    dateFormat="dd/mm/yy"
                    class="w-64"
                    placeholder="Choisir une date"
                />
            </div>
        </div>

        <div data-tour="rapports-reception.daily">
            <ReceptionStatsSection
                :stats="receptionStats"
                :loading="receptionLoading"
                :period-label="periodLabel"
            />
        </div>

        <div data-tour="rapports-reception.doctors">
            <DoctorReportsTable
                title="Rapports périodiques par médecin"
                :data="receptionDoctorReports"
                :loading="receptionLoading"
                :period-label="periodLabel"
                variant="reception"
            />
        </div>
    </div>
</template>
