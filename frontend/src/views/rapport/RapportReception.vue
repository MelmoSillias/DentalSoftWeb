<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
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

function printSection() {
    const target = document.getElementById('reception-daily-stats');
    if (!target) return;
    const html = `
        <html>
        <head>
            <title>Statistiques du jour</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
            </style>
        </head>
        <body>${target.outerHTML}</body>
        </html>
    `;
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }, 400);
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">Bienvenue, Réceptionniste</h2>
                <p class="text-sm text-surface-500 dark:text-surface-400">Journée du {{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <DatePicker
                    v-model="selectedDate"
                    showIcon
                    dateFormat="dd/mm/yy"
                    class="w-64"
                    placeholder="Choisir une date"
                />
                <Button label="Imprimer" icon="pi pi-print" outlined @click="printSection" />
            </div>
        </div>

        <div id="reception-daily-stats">
            <ReceptionStatsSection :stats="receptionStats" :loading="receptionLoading" />
        </div>

        <DoctorReportsTable
            title="Rapports périodiques par médecin"
            :data="receptionDoctorReports"
            :loading="receptionLoading"
            :period-label="periodLabel"
            variant="reception"
        />
    </div>
</template>
