<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';

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
    const items = Array.isArray(row?.paiements_period) ? row.paiements_period : [];
    if (!items.length) {
        return '<div class="p-3"><em>Aucune entrée enregistrée sur cette période.</em></div>';
    }

    let total = 0;
    const rows = items
        .map((item) => {
            total += Number(item.montant_paye || 0);
            return `
                <tr>
                    <td>${item.date || '--'}</td>
                    <td>${item.patient || '--'}</td>
                    <td>${item.description || '--'}</td>
                    <td>${formatFcfa(item.montant_paye)}</td>
                </tr>
            `;
        })
        .join('');

    return `
        <div class="p-3">
            <table style="width:100%; border-collapse: collapse;" border="1" cellpadding="6">
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

function openPrintWindow(html) {
    const printWindow = window.open('', '_blank');
    if (!printWindow) return;
    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }, 500);
}

function printDoctorRow(row) {
    const currentDate = new Date().toLocaleDateString('fr-FR');
    const html = `
        <html>
        <head>
            <title>Rapport Dr ${row.name || ''}</title>
            <style>
                @page { size: A4 landscape; margin: 10mm; }
                body { font-family: "Times New Roman", serif; font-size: 12pt; line-height: 1.4; color: #000; margin: 0; padding: 10mm; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                .header h2 { margin: 0; font-size: 18pt; text-transform: uppercase; }
                .header p { margin: 5px 0 0; font-size: 11pt; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .section-title { font-weight: bold; font-size: 13pt; margin: 25px 0 10px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
                .signature-table { margin-top: 40px; width: 100%; }
                .signature-cell { width: 45%; text-align: center; }
                .signature-line { border-top: 1px solid #000; width: 80%; margin: 15px auto 5px; }
                .footer { font-size: 9pt; text-align: center; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Cabinet Dentaire Centre Dentaire Massaman</h2>
                <p>RAPPORT DE SERVICE MÉDICAL</p>
                <p>Rue 404 - Porte 963 KalabanCoura ACI | Bamako-MALI | Tél: +223 44 54 26 09 / +223 97 08 12 92</p>
            </div>
            <div>
                <strong>Médecin :</strong> Dr ${row.name || ''}<br />
                <strong>Période concernée :</strong> ${props.periodLabel || '(non spécifiée)'}
            </div>
            <div class="section-title">Statistiques d'activité</div>
            ${formatDoctorTable(row)}
            <div class="section-title">Détails des soins effectués</div>
            ${formatDoctorDetails(row)}
            <table class="signature-table">
                <tr>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <p>Signature du praticien</p>
                    </td>
                    <td class="signature-cell">
                        <div class="signature-line"></div>
                        <p>Cachet et visa de la direction</p>
                    </td>
                </tr>
            </table>
            <div class="footer">Document généré automatiquement le ${currentDate}</div>
        </body>
        </html>
    `;

    openPrintWindow(html);
}

function formatDoctorTable(row) {
    return `
        <table>
            <thead>
                <tr>
                    <th>Indicateur</th>
                    <th>Nombre</th>
                    <th>Montant total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Consultations réalisées</td>
                    <td>${row.consultations || 0}</td>
                    <td>${row.consultations_amount ? formatFcfa(row.consultations_amount) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td>Actes posés</td>
                    <td>${row.acts || 0}</td>
                    <td>${row.acts_amount ? formatFcfa(row.acts_amount) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td colspan="2">Apport total</td>
                    <td>${row.apport ? formatFcfa(row.apport) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td colspan="2">Montant payé</td>
                    <td>${row.revenue ? formatFcfa(row.revenue) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td colspan="2">Reliquat patients</td>
                    <td>${row.reliquat ? formatFcfa(row.reliquat) : formatFcfa(0)}</td>
                </tr>
            </tbody>
        </table>
    `;
}

function printSummary() {
    const currentDate = new Date().toLocaleDateString('fr-FR');
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

    const html = `
        <html>
        <head>
            <title>Rapport de service</title>
            <style>
                @page { size: A4 landscape; margin: 20mm; }
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f2f2f2; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>CABINET DENTAIRE Centre Dentaire Massaman</h2>
                <p><strong>Rapport de service (Résumé)</strong> - ${currentDate}</p>
                <p>Rue 404 - Porte 963 KalabanCoura ACI | Bamako-MALI | Tél: +223 44 54 26 09 / +223 97 08 12 92</p>
            </div>
            <p><strong>Période :</strong> ${props.periodLabel || '(non spécifiée)'}</p>
            <table>
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
        </body>
        </html>
    `;

    printDialogVisible.value = false;
    openPrintWindow(html);
}

function printAllActs() {
    const acts = [];
    doctors.value.forEach((doctor) => {
        if (Array.isArray(doctor.paiements_period)) {
            doctor.paiements_period.forEach((item) => {
                acts.push({
                    date: item.date || '--',
                    medecin: doctor.name || '--',
                    patient: item.patient || '--',
                    description: item.description || '--',
                    montant: item.montant_paye || 0
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

    const html = `
        <html>
        <head>
            <title>Liste des actes médicaux</title>
            <style>
                @page { size: A4 landscape; margin: 20mm; }
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background: #f2f2f2; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Liste des actes médicaux</h2>
                <p><strong>Période :</strong> ${props.periodLabel || '(non spécifiée)'}</p>
            </div>
            <table>
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
        </body>
        </html>
    `;

    printDialogVisible.value = false;
    openPrintWindow(html);
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
                            <div v-if="!data?.paiements_period || !data.paiements_period.length" class="text-surface-500">
                                Aucune entrée enregistrée sur cette période.
                            </div>
                            <div v-else>
                                <table class="w-full border-collapse text-xs sm:text-sm">
                                    <thead>
                                        <tr class="text-left text-surface-500">
                                            <th class="border-b p-2">Date</th>
                                            <th class="border-b p-2">Patient</th>
                                            <th class="border-b p-2">Description</th>
                                            <th class="border-b p-2">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, idx) in data.paiements_period" :key="idx">
                                            <td class="border-b p-2">{{ row.date }}</td>
                                            <td class="border-b p-2">{{ row.patient }}</td>
                                            <td class="border-b p-2">{{ row.description }}</td>
                                            <td class="border-b p-2">{{ formatFcfa(row.montant_paye) }}</td>
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
