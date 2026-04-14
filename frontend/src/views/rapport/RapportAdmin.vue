<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import AdminActsStatsSection from '@/components/rapport/admin/AdminActsStatsSection.vue';
import FinanceCrossTable from '@/components/finances/FinanceCrossTable.vue';
import AdminGlobalStatsSection from '@/components/rapport/admin/AdminGlobalStatsSection.vue';
import AdminNonPeriodicDetailsSection from '@/components/rapport/admin/AdminNonPeriodicDetailsSection.vue';
import AdminPeriodicDetailsSection from '@/components/rapport/admin/AdminPeriodicDetailsSection.vue';
import DoctorReportsTable from '@/components/rapport/common/DoctorReportsTable.vue';
import { useRapports } from '@/composables/useRapports';

const {
    adminLoading,
    adminGlobalStats,
    adminEmployeeDistribution,
    adminLowStockConsumables,
    adminGlobalPatients,
    adminPeriodicPatients,
    adminPeriodicConsultations,
    adminPeriodicAppointments,
    adminRoomUsage,
    adminPaymentBalances,
    adminPaymentFrequency,
    adminActsStats,
    adminDoctorReports,
    fetchAdminRapport,
    toIsoDate
} = useRapports();

const startOfMonth = new Date();
startOfMonth.setDate(1);
const endOfMonth = new Date();

const range = ref([startOfMonth, endOfMonth]);
const hasLoaded = ref(false);

const periodLabel = computed(() => {
    const [start, end] = range.value || [];
    if (!start || !end) return 'Choisir période';
    const startLabel = start.toLocaleDateString('fr-FR');
    const endLabel = end.toLocaleDateString('fr-FR');
    return `${startLabel} - ${endLabel}`;
});

async function refresh(silent = false) {
    const [start, end] = range.value || [];
    await fetchAdminRapport({ from: toIsoDate(start), to: toIsoDate(end), silent });
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

function printSection(id) {
    const target = document.getElementById(id);
    if (!target) return;
    const html = `
        <html>
        <head>
            <title>Impression</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .card { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-bottom: 16px; }
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
        <div class="flex flex-wrap items-center justify-between gap-4" data-tour="rapports-admin.range">
            <div>
                <h2 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">Tableau de bord</h2>
                <p class="text-sm text-surface-500 dark:text-surface-400">Suivi global de l'activité du cabinet</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <DatePicker
                    v-model="range"
                    selectionMode="range"
                    showIcon
                    dateFormat="dd/mm/yy"
                    class="w-72"
                    :placeholder="'Choisir période'"
                />
                <Button label="Rafraîchir" icon="pi pi-refresh" outlined @click="refresh(false)" />
            </div>
        </div>

        <div data-tour="rapports-admin.global">
            <AdminGlobalStatsSection :stats="adminGlobalStats" :loading="adminLoading" @print="printSection" />
        </div>

        <div data-tour="rapports-admin.non-periodic">
            <AdminNonPeriodicDetailsSection
                :employee-distribution="adminEmployeeDistribution"
                :low-stock="adminLowStockConsumables"
                :patients="adminGlobalPatients"
                :loading="adminLoading"
                @print="printSection"
            />
        </div>

        <div data-tour="rapports-admin.periodic">
            <AdminPeriodicDetailsSection
                :patients="adminPeriodicPatients"
                :consultations="adminPeriodicConsultations"
                :appointments="adminPeriodicAppointments"
                :room-usage="adminRoomUsage"
                :payment-balances="adminPaymentBalances"
                :payment-frequency="adminPaymentFrequency"
                :loading="adminLoading"
                @print="printSection"
            />
        </div>

        <div class="rounded-2xl border border-surface-200/70 bg-surface-0/60 p-1 shadow-sm dark:border-surface-700/50 dark:bg-surface-900/30">
            <FinanceCrossTable
                title="Tableau croisé financier"
                subtitle="Section dédiée au suivi hebdomadaire des revenus et dépenses validés." />
        </div>

        <div data-tour="rapports-admin.acts">
            <AdminActsStatsSection :acts-stats="adminActsStats" :loading="adminLoading" @print="printSection" />
        </div>

        <div data-tour="rapports-admin.doctors">
            <DoctorReportsTable
                :data="adminDoctorReports"
                :loading="adminLoading"
                :period-label="periodLabel"
                :show-kpi="true"
                variant="admin"
            />
        </div>
    </div>
</template>
