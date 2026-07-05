<script setup>
import { computed, ref } from 'vue';
import Card from 'primevue/card';
import Chart from 'primevue/chart';
import ToggleButton from 'primevue/togglebutton';
import Tag from 'primevue/tag';

const props = defineProps({
    acts: { type: Array, default: () => [] },
    reliquatPayments: { type: Array, default: () => [] },
    reliquatTotal: { type: Number, default: 0 },
    loading: { type: Boolean, default: false }
});

const showChart = ref(false);

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

const chartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: props.acts.map((row) => row.description || 'Acte'),
        datasets: [
            {
                label: 'Montant apport',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: props.acts.map((row) => Number(row.montant || 0))
            },
            {
                label: 'Montant payé',
                backgroundColor: documentStyle.getPropertyValue('--p-emerald-500'),
                data: props.acts.map((row) => Number(row.montantPaye || 0))
            }
        ]
    };
});

const chartOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } }
        },
        scales: {
            x: { ticks: { color: textColorSecondary }, grid: { display: false } },
            y: { ticks: { color: textColorSecondary }, grid: { color: surfaceBorder } }
        }
    };
});
</script>

<template>
    <Card class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 via-surface-0 to-surface-50/70 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
        <template #title>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-base font-semibold text-surface-900 dark:text-surface-0">Soins médicaux par consultation</span>
                <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
            </div>
        </template>
        <template #content>
            <div v-if="showChart" class="min-h-[240px]">
                <div class="aspect-[16/9] w-full">
                    <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
                </div>
            </div>
            <template v-else>
                <div class="space-y-6">
                    <div>
                        <h4 class="mb-2 text-sm font-semibold text-surface-900 dark:text-surface-0">Soins de la période</h4>
                        <ul v-if="acts.length" class="space-y-2">
                            <li
                                v-for="(act, idx) in acts"
                                :key="idx"
                                class="flex flex-col gap-2 rounded-xl border border-surface-200/60 bg-surface-50 p-3 text-sm dark:border-surface-700 dark:bg-surface-800"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <strong class="text-surface-900 dark:text-surface-0">{{ act.description }}</strong>
                                        <Tag v-if="act.isInsurance" value="Assurance" severity="info" />
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Tag :value="`Apport ${formatFcfa(act.montant)}`" severity="secondary" />
                                        <Tag v-if="act.isInsurance" :value="`Assurance ${formatFcfa(act.montantAssurance)}`" severity="info" />
                                        <Tag v-if="act.isInsurance" :value="`Patient ${formatFcfa(act.montantPatient)}`" severity="secondary" />
                                        <Tag :value="`Payé ${formatFcfa(act.montantPaye)}`" severity="success" />
                                    </div>
                                </div>
                                <p class="text-surface-500">{{ act.patient }} • {{ act.date }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-surface-500">Aucun soin posé durant cette période.</p>
                    </div>

                    <div>
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-surface-900 dark:text-surface-0">Paiements de reliquats</h4>
                            <Tag :value="`Total ${formatFcfa(reliquatTotal)}`" severity="info" />
                        </div>
                        <ul v-if="reliquatPayments.length" class="space-y-2">
                            <li
                                v-for="(payment, idx) in reliquatPayments"
                                :key="`reliquat-${idx}`"
                                class="flex flex-col gap-1 rounded-xl border border-surface-200/60 bg-surface-50 p-3 text-sm dark:border-surface-700 dark:bg-surface-800"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <strong class="text-surface-900 dark:text-surface-0">{{ payment.description }}</strong>
                                        <Tag v-if="payment.isInsurance" value="Assurance" severity="info" />
                                    </div>
                                    <Tag :value="formatFcfa(payment.montant)" severity="info" />
                                </div>
                                <p class="text-surface-500">
                                    {{ payment.patient }} • Paiement {{ payment.date }} • Consultation {{ payment.consultation_date }}
                                </p>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-surface-500">Aucun paiement de reliquat sur cette période.</p>
                    </div>
                </div>
            </template>
        </template>
    </Card>
</template>
