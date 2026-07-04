<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import {
    buildPrintHtmlDocument,
    buildPrintTitleBandHtml
} from '@/utils/printDocumentStyles';
import { openPrintWindow } from '@/utils/reportPrint';

const props = defineProps({
    title: { type: String, default: 'Rapports périodiques par médecin' },
    periodLabel: { type: String, default: '' },
    data: { type: Object, default: () => ({ kpi: {}, doctors: [] }) },
    loading: { type: Boolean, default: false },
    showKpi: { type: Boolean, default: false },
    variant: { type: String, default: 'admin' }
});

const expandedRows = ref(null);
const printDialogVisible = ref(false);

const doctors = computed(() => props.data?.doctors || []);
const kpi = computed(() => props.data?.kpi || {});

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

function formatDoctorDetails(row) {
    const items = Array.isArray(row?.actes) ? row.actes : [];
    if (!items.length) {
        return '<div class="p-3"><em>Aucune entrée enregistrée sur cette période.</em></div>';
    }

    let total = 0;
    const rows = items
        .map((item) => {
            total += Number(item.montant || 0);
            return `
                <tr>
                    <td>${item.date || '--'}</td>
                    <td>${item.patient || '--'}</td>
                    <td>${item.description || '--'}</td>
                    <td>${formatFcfa(item.montant)}</td>
                </tr>
            `;
        })
        .join('');

    return `
        <div class="p-3">
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Description</th>
                        <th>Montant</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows}
                </tbody>
            </table>
            <p style="margin-top: 12px; font-weight: 600;">Total = ${formatFcfa(total)}</p>
        </div>
    `;
}

function printDoctorRow(row) {
    const body = `
        ${buildPrintTitleBandHtml('Rapport de service médical', `${row.name || ''} — Période : ${props.periodLabel || '(non spécifiée)'}`)}
        <div class="print-section-title">Statistiques d'activité</div>
        ${formatDoctorTable(row)}
        <div class="print-section-title">Détails de l'activité</div>
        ${formatDoctorDetails(row)}
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-line"></div>
                    <p>Signature du praticien</p>
                </td>
                <td>
                    <div class="signature-line"></div>
                    <p>Cachet et visa de la direction</p>
                </td>
            </tr>
        </table>
    `;

    openPrintWindow(buildPrintHtmlDocument({
        title: `Rapport ${row.name || ''}`,
        body
    }));
}

function formatDoctorTable(row) {
    return `
        <table class="print-table">
            <tbody>
                <tr>
                    <td> <strong>Consultations réalisées</strong></td>
                    <td>${row.consultations || 0}</td>
                    <td>${row.consultations_amount ? formatFcfa(row.consultations_amount) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td> <strong>Actes posés</strong></td>
                    <td>${row.acts || 0}</td>
                    <td>${row.acts_amount ? formatFcfa(row.acts_amount) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td colspan="2"> <strong>Apport total</strong></td>
                    <td>${row.apport ? formatFcfa(row.apport) : formatFcfa(0)}</td>
                </tr>
            </tbody>
        </table>
    `;
}

function printSummary() {
    const rows = doctors.value
        .map(
            (row) => `
            <tr>
                <td>${row.name || ''}</td>
                <td>${row.consultations || 0} (${row.consultations_paid || 0} payantes)</td>
                <td>${formatFcfa(row.apport)}</td>
                <td>${formatFcfa(row.revenue)}</td>
                <td>${formatFcfa(row.reliquat)}</td>
                <td>${formatFcfa(row.salary)}</td>
            </tr>
        `
        )
        .join('');

    const body = `
        ${buildPrintTitleBandHtml('Rapport de service (Résumé)', `Période : ${props.periodLabel || '(non spécifiée)'}`)}
        <table class="print-table">
            <thead>
                <tr>
                    <th>Médecin</th>
                    <th>Consultations</th>
                    <th>Montant généré (Fcfa)</th>
                    <th>Montant payé (Fcfa)</th>
                    <th>Réliquat patient</th>
                    <th>Salaire</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    `;

    printDialogVisible.value = false;
    openPrintWindow(buildPrintHtmlDocument({ title: 'Rapport de service', body }));
}

function printAllActs() {
    const acts = [];
    doctors.value.forEach((doctor) => {
        if (Array.isArray(doctor.actes)) {
            doctor.actes.forEach((item) => {
                acts.push({
                    date: item.date || '--',
                    medecin: doctor.name || '--',
                    patient: item.patient || '--',
                    description: item.description || '--',
                    montant: item.montant || 0
                });
            });
        }
    });

    const rows = acts
        .map(
            (act) => `
            <tr>
                <td>${act.date}</td>
                <td>${act.medecin}</td>
                <td>${act.patient}</td>
                <td>${act.description}</td>
                <td>${formatFcfa(act.montant)}</td>
            </tr>
        `
        )
        .join('');

    const total = acts.reduce((sum, act) => sum + Number(act.montant || 0), 0);

    const body = `
        ${buildPrintTitleBandHtml('Liste des actes médicaux', `Période : ${props.periodLabel || '(non spécifiée)'}`)}
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Médecin</th>
                    <th>Patient</th>
                    <th>Description</th>
                    <th>Montant</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
        <p style="margin-top: 12px; font-weight: 600;">Total = ${formatFcfa(total)}</p>
    `;

    printDialogVisible.value = false;
    openPrintWindow(buildPrintHtmlDocument({ title: 'Liste des actes médicaux', body }));
}
</script>

<template>
    <Card class="rounded-2xl border border-surface-200/60 bg-gradient-to-b from-surface-0 via-surface-0 to-surface-50/70 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
        <template #title>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-surface-200/60 pb-3 dark:border-surface-700/60">
                <div>
                    <h3 class="text-base sm:text-lg font-semibold text-surface-900 dark:text-surface-0">{{ title }}</h3>
                    <p v-if="periodLabel" class="text-xs sm:text-sm text-surface-500 dark:text-surface-400">Période : {{ periodLabel }}</p>
                </div>
                <Button label="Imprimer" icon="pi pi-print" outlined class="w-full sm:w-auto" @click="printDialogVisible = true" />
            </div>
        </template>
        <template #content>
            <div v-if="showKpi" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
                <div class="rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70">
                    <p class="text-xs font-semibold uppercase text-surface-500">Apports total</p>
                    <p class="text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(kpi.totalRevenue) }}</p>
                </div>
                <div class="rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70">
                    <p class="text-xs font-semibold uppercase text-surface-500">Après retrait des %</p>
                    <p class="text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(kpi.afterFees) }}</p>
                </div>
                <div class="rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70">
                    <p class="text-xs font-semibold uppercase text-surface-500">Salaires totaux</p>
                    <p class="text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(kpi.totalSalaries) }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <DataTable
                    :value="doctors"
                    dataKey="name"
                    responsiveLayout="scroll"
                    :loading="loading"
                    v-model:expandedRows="expandedRows"
                    paginator
                    :rows="8"
                    class="min-w-[640px] text-xs sm:text-sm rounded-xl overflow-hidden border border-surface-200/60 dark:border-surface-700/60"
                >
                    <Column expander style="width: 2.5rem" />
                    <Column field="name" header="Médecin" />
                    <Column header="Consultations">
                        <template #body="{ data }">
                            <span>{{ data.consultations || 0 }}</span>
                            <span class="text-[11px] sm:text-xs text-surface-500"> ({{ data.consultations_paid || 0 }} payantes)</span>
                        </template>
                    </Column>
                    <Column v-if="variant === 'admin'" header="Montant généré">
                        <template #body="{ data }">{{ formatFcfa(data.apport) }}</template>
                    </Column>
                    <Column v-if="variant === 'admin'" header="Montant payé">
                        <template #body="{ data }">{{ formatFcfa(data.revenue) }}</template>
                    </Column>
                    <Column v-if="variant === 'admin'" header="Reliquat patient">
                        <template #body="{ data }">{{ formatFcfa(data.reliquat) }}</template>
                    </Column>
                    <Column v-if="variant === 'reception'" header="Montant">
                        <template #body="{ data }">{{ formatFcfa(data.apport) }}</template>
                    </Column>
                    <Column v-if="variant === 'reception'" header="Payantes / Gratuites">
                        <template #body="{ data }">
                            <Tag :value="`${data.consultations_paid || 0} / ${(data.consultations || 0) - (data.consultations_paid || 0)}`" severity="info" />
                        </template>
                    </Column>
                    <Column header="Salaire">
                        <template #body="{ data }">{{ formatFcfa(data.salary) }}</template>
                    </Column>
                    <Column header="Action" style="width: 6rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-print" text rounded @click="printDoctorRow(data)" />
                        </template>
                    </Column>
                    <template #expansion="{ data }">
                        <div class="rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 text-xs sm:text-sm shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70">
                            <div v-if="!data?.actes || !data.actes.length" class="text-surface-500">
                                Aucun acte enregistré sur cette période.
                            </div>
                            <div v-else>
                                <table class="w-full border-collapse text-xs sm:text-sm">
                                    <thead>
                                        <tr class="text-left text-surface-500">
                                            <th class="border-b p-2">Date</th>
                                            <th class="border-b p-2">Patient</th>
                                            <th class="border-b p-2">Description</th>
                                            <th class="border-b p-2">Montant des actes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in data.actes" :key="idx">
                                            <td class="border-b p-2">{{ row.date }}</td>
                                            <td class="border-b p-2">{{ row.patient }}</td>
                                            <td class="border-b p-2">{{ row.description }}</td>
                                            <td class="border-b p-2">{{ formatFcfa(row.montant) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
        </template>
    </Card>

    <Dialog v-model:visible="printDialogVisible" modal header="Choix d'impression" :style="{ width: '90vw', maxWidth: '32rem' }">
        <p class="mb-4">Souhaitez-vous imprimer :</p>
        <div class="flex flex-col gap-2">
            <Button label="Liste des médecins (résumé)" icon="pi pi-print" @click="printSummary" />
            <Button label="Liste des soins détaillée" icon="pi pi-file" severity="secondary" @click="printAllActs" />
        </div>
    </Dialog>
</template>
