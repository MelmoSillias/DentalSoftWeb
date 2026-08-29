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

function formatFcfaNumber(amount) {
    return new Intl.NumberFormat('fr-FR').format(Number(amount || 0));
}

function actPaidIncludingInsurance(act) {
    return Number(act?.montantPaye || 0) + Number(act?.montantAssurance || 0);
}

function formatPaidWithInsurance(act) {
    const paid = actPaidIncludingInsurance(act);
    const assurance = Number(act?.montantAssurance || 0);
    if (act?.isInsurance && assurance > 0) {
        return `${formatFcfa(paid)} (${formatFcfaNumber(assurance)})`;
    }
    return formatFcfa(paid);
}

function parseReportDate(value) {
    if (!value || value === '--') {
        return 0;
    }
    const parts = String(value).split('/');
    if (parts.length !== 3) {
        return 0;
    }
    const [day, month, year] = parts.map(Number);
    if (!day || !month || !year) {
        return 0;
    }
    return new Date(year, month - 1, day).getTime();
}

function doctorRevenueTotal(row) {
    return Number(row?.revenue_total ?? row?.revenue ?? 0);
}

function doctorRevenueCash(row) {
    return Number(row?.revenue_cash ?? (Number(row?.revenue || 0) + Number(row?.revenue_reliquats || 0)));
}

function doctorCashCollected(row) {
    return doctorRevenueCash(row);
}

function actPaymentBasis(act) {
    if (act?.isInsurance) {
        return {
            due: Number(act.montantPatient || 0),
            paid: Number(act.montantPaye || 0)
        };
    }

    return {
        due: Number(act.montant || 0),
        paid: Number(act.montantPaye || 0)
    };
}

function sumActesField(row, field) {
    return (row?.actes || []).reduce((sum, item) => sum + Number(item?.[field] || 0), 0);
}

function paymentProgress(montant, montantPaye) {
    const total = Number(montant || 0);
    const paid = Number(montantPaye || 0);
    if (total <= 0) {
        return paid > 0 ? 100 : 0;
    }

    return Math.min(100, Math.round((paid / total) * 100));
}

function paymentStatus(montant, montantPaye) {
    const total = Number(montant || 0);
    const paid = Number(montantPaye || 0);
    const remaining = Math.max(0, total - paid);

    if (total <= 0 && paid <= 0) {
        return { label: 'Gratuit', severity: 'secondary', remaining: 0 };
    }
    if (remaining <= 0) {
        return { label: 'Soldé', severity: 'success', remaining: 0 };
    }
    if (paid > 0) {
        return { label: 'Partiel', severity: 'warn', remaining };
    }

    return { label: 'Impayé', severity: 'danger', remaining: total };
}

function doctorSummaryCards(row) {
    return [
        {
            key: 'apport',
            label: 'Apport (hors cabinet)',
            value: formatFcfa(row?.apport),
            icon: 'pi pi-briefcase',
            tone: 'text-indigo-600 dark:text-indigo-300',
            bg: 'bg-indigo-50 dark:bg-indigo-950/40'
        },
        {
            key: 'cash',
            label: 'Encaissé patient',
            value: formatFcfa(doctorRevenueCash(row)),
            icon: 'pi pi-wallet',
            tone: 'text-emerald-600 dark:text-emerald-300',
            bg: 'bg-emerald-50 dark:bg-emerald-950/40'
        },
        {
            key: 'assurance',
            label: 'Part assurance',
            value: formatFcfa(row?.revenue_assurance ?? row?.apport_assurance),
            icon: 'pi pi-shield',
            tone: 'text-violet-600 dark:text-violet-300',
            bg: 'bg-violet-50 dark:bg-violet-950/40'
        },
        {
            key: 'reliquats',
            label: 'Reliquats encaissés',
            value: formatFcfa(row?.revenue_reliquats),
            icon: 'pi pi-history',
            tone: 'text-sky-600 dark:text-sky-300',
            bg: 'bg-sky-50 dark:bg-sky-950/40'
        },
        {
            key: 'remaining',
            label: 'Réliquat patient',
            value: formatFcfa(row?.reliquat),
            icon: 'pi pi-exclamation-circle',
            tone: 'text-amber-600 dark:text-amber-300',
            bg: 'bg-amber-50 dark:bg-amber-950/40'
        }
    ];
}

function formatReliquatPaymentsSection(row) {
    const items = Array.isArray(row?.paiements_reliquats) ? row.paiements_reliquats : [];
    if (!items.length) {
        return '';
    }

    const sorted = [...items].sort((a, b) => parseReportDate(a.date) - parseReportDate(b.date));
    const rows = sorted
        .map(
            (item) => `
                <tr>
                    <td>${item.date || '--'}</td>
                    <td>${item.consultation_date || '--'}</td>
                    <td>${item.patient || '--'}</td>
                    <td>${item.description || '--'}</td>
                    <td>${formatFcfa(item.montant)}</td>
                </tr>
            `
        )
        .join('');

    return `
        <div class="print-section-title" style="margin-top: 16px;">Paiements de reliquats</div>
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date paiement</th>
                    <th>Date consultation</th>
                    <th>Patient</th>
                    <th>Description</th>
                    <th>Montant</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    `;
}

function formatDoctorDetails(row) {
    const items = Array.isArray(row?.actes) ? [...(row.actes || [])].sort((a, b) => parseReportDate(a.date) - parseReportDate(b.date)) : [];
    const reliquats = Array.isArray(row?.paiements_reliquats) ? row.paiements_reliquats : [];

    if (!items.length && !reliquats.length) {
        return '<div class="p-3"><em>Aucune entrée enregistrée sur cette période.</em></div>';
    }

    let apportTotal = 0;
    let paidTotal = 0;
    const actRows = items
        .map((item) => {
            apportTotal += Number(item.montant || 0);
            paidTotal += actPaidIncludingInsurance(item);
            return `
                <tr>
                    <td>${item.date || '--'}</td>
                    <td>${item.patient || '--'}</td>
                    <td>${item.description || '--'}</td>
                    <td>${formatFcfa(item.montant)}</td>
                    <td>${formatFcfa(actPaidIncludingInsurance(item))}</td>
                </tr>
            `;
        })
        .join('');

    const reliquatTotal = reliquats.reduce((sum, item) => sum + Number(item.montant || 0), 0);

    const actsSection = items.length
        ? `
        <div class="print-section-title">Soins de la période</div>
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Description</th>
                    <th>Montant apport</th>
                    <th>Montant payé</th>
                </tr>
            </thead>
            <tbody>
                ${actRows}
            </tbody>
        </table>
    `
        : '';

    const recap = `
        <div class="print-section-title" style="margin-top: 16px;">Récapitulatif</div>
        <p style="margin-top: 8px; font-weight: 600;">
            Total apport = ${formatFcfa(apportTotal)} ·
            Total payé = ${formatFcfa(paidTotal)} ·
            Total Paiements de reliquats = ${formatFcfa(reliquatTotal)}
        </p>
    `;

    return `
        <div class="p-3">
            ${actsSection}
            ${formatReliquatPaymentsSection(row)}
            ${recap}
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
                    <td><strong>Consultations réalisées</strong></td>
                    <td>${row.consultations || 0}</td>
                    <td>${row.consultations_amount ? formatFcfa(row.consultations_amount) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td><strong>Actes posés</strong></td>
                    <td>${row.acts || 0}</td>
                    <td>${row.acts_amount ? formatFcfa(row.acts_amount) : formatFcfa(0)}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Apport total</strong></td>
                    <td>${formatFcfa(row.apport)}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Paiements de reliquats</strong></td>
                    <td>${formatFcfa(row.revenue_reliquats)}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Total encaissé</strong></td>
                    <td>${formatFcfa(doctorRevenueTotal(row))}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Réliquat patient</strong></td>
                    <td>${formatFcfa(row.reliquat)}</td>
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
                <td>${formatFcfa(doctorRevenueTotal(row))}</td>
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
                    <th>Apport (Fcfa)</th>
                    <th>Total encaissé</th>
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
    const reliquats = [];

    doctors.value.forEach((doctor) => {
        if (Array.isArray(doctor.actes)) {
            doctor.actes.forEach((item) => {
                acts.push({
                    date: item.date || '--',
                    medecin: doctor.name || '--',
                    patient: item.patient || '--',
                    description: item.description || '--',
                    montant: item.montant || 0,
                    montantPaye: item.montantPaye || 0,
                    montantAssurance: item.montantAssurance || 0,
                    isInsurance: Boolean(item.isInsurance)
                });
            });
        }

        if (Array.isArray(doctor.paiements_reliquats)) {
            doctor.paiements_reliquats.forEach((item) => {
                reliquats.push({
                    date: item.date || '--',
                    consultationDate: item.consultation_date || '--',
                    medecin: doctor.name || '--',
                    patient: item.patient || '--',
                    description: item.description || '--',
                    montant: item.montant || 0,
                    isInsurance: Boolean(item.isInsurance)
                });
            });
        }
    });

    acts.sort((a, b) => parseReportDate(a.date) - parseReportDate(b.date));
    reliquats.sort((a, b) => parseReportDate(a.date) - parseReportDate(b.date));

    const actRows = acts
        .map(
            (act) => `
            <tr>
                <td>${act.date}</td>
                <td>${act.medecin}</td>
                <td>${act.patient}</td>
                <td>${act.description}</td>
                <td>${formatFcfa(act.montant)}</td>
                <td>${formatPaidWithInsurance(act)}</td>
            </tr>
        `
        )
        .join('');

    const reliquatRows = reliquats
        .map(
            (item) => `
            <tr>
                <td>${item.date}</td>
                <td>${item.consultationDate}</td>
                <td>${item.medecin}</td>
                <td>${item.patient}</td>
                <td>${item.description}</td>
                <td>${formatFcfa(item.montant)}</td>
            </tr>
        `
        )
        .join('');

    const apportTotal = acts.reduce((sum, act) => sum + Number(act.montant || 0), 0);
    const paidTotal = acts.reduce((sum, act) => sum + actPaidIncludingInsurance(act), 0);
    const assuranceTotal = acts.reduce((sum, act) => sum + Number(act.montantAssurance || 0), 0);
    const reliquatTotal = reliquats.reduce((sum, item) => sum + Number(item.montant || 0), 0);

    const body = `
        ${buildPrintTitleBandHtml('Liste des soins médicaux', `Période : ${props.periodLabel || '(non spécifiée)'}`)}
        <div class="print-section-title">Soins de la période</div>
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Médecin</th>
                    <th>Patient</th>
                    <th>Description</th>
                    <th>Montant apport</th>
                    <th>Montant payé</th>
                </tr>
            </thead>
            <tbody>
                ${actRows}
            </tbody>
        </table>
        <div class="print-section-title" style="margin-top: 16px;">Paiements de reliquats</div>
        <table class="print-table">
            <thead>
                <tr>
                    <th>Date paiement</th>
                    <th>Date consultation</th>
                    <th>Médecin</th>
                    <th>Patient</th>
                    <th>Description</th>
                    <th>Montant</th>
                </tr>
            </thead>
            <tbody>
                ${reliquatRows || '<tr><td colspan="6"><em>Aucun paiement de reliquat</em></td></tr>'}
            </tbody>
        </table>
        <div class="print-section-title" style="margin-top: 16px;">Récapitulatif</div>
        <table class="print-table">
            <tbody>
                <tr>
                    <td><strong>Total apport</strong></td>
                    <td>${formatFcfa(apportTotal)}</td>
                </tr>
                <tr>
                    <td><strong>Total payé</strong></td>
                    <td>${formatFcfa(paidTotal)}</td>
                </tr>
                <tr>
                    <td><strong>Total parts assurances</strong></td>
                    <td>${formatFcfa(assuranceTotal)}</td>
                </tr>
                <tr>
                    <td><strong>Total Paiements de reliquats</strong></td>
                    <td>${formatFcfa(reliquatTotal)}</td>
                </tr>
            </tbody>
        </table>
    `;

    printDialogVisible.value = false;
    openPrintWindow(buildPrintHtmlDocument({ title: 'Liste des soins médicaux', body }));
}
</script>

<template>
    <Card class="overflow-hidden rounded-2xl border border-surface-200/60 bg-gradient-to-b from-surface-0 via-surface-0 to-surface-50/70 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
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
            <div v-if="showKpi" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 mb-6">
                <div class="rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70">
                    <p class="text-xs font-semibold uppercase text-surface-500">Apports total</p>
                    <p class="text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(kpi.totalApport ?? 0) }}</p>
                </div>
                <div class="rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70">
                    <p class="text-xs font-semibold uppercase text-surface-500">Parts assurance</p>
                    <p class="text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(kpi.totalPartAssurance ?? 0) }}</p>
                </div>
                <div class="rounded-2xl border border-surface-200/60 bg-surface-50/80 p-3 sm:p-4 text-center shadow-sm dark:border-surface-700/60 dark:bg-surface-800/70">
                    <p class="text-xs font-semibold uppercase text-surface-500">Encaissement réel</p>
                    <p class="text-lg sm:text-xl font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(kpi.totalPaidCash ?? kpi.totalPaid ?? 0) }}</p>
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
                        <template #body="{ data }">
                            <div>{{ formatFcfa(doctorRevenueTotal(data)) }}</div>
                            <div class="text-[11px] text-surface-500">
                                encaissé : {{ formatFcfa(doctorRevenueCash(data)) }}
                                <span v-if="Number(data.revenue_assurance || 0) > 0">
                                    · assurance : {{ formatFcfa(data.revenue_assurance) }}
                                </span>
                            </div>
                        </template>
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
                        <div class="doctor-row-details space-y-5 p-1 sm:p-2">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-surface-500">Détail financier</p>
                                    <h4 class="text-base font-semibold text-surface-900 dark:text-surface-0">
                                        {{ data.name || 'Médecin' }}
                                    </h4>
                                    <p class="text-xs text-surface-500">
                                        Rémunération :
                                        <span class="font-semibold text-violet-600 dark:text-violet-300">
                                            {{ formatFcfa(doctorRevenueTotal(data)) }}
                                        </span>
                                        · Encaissé :
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-300">
                                            {{ formatFcfa(doctorRevenueCash(data)) }}
                                        </span>
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Tag
                                        :value="`${data.consultations || 0} consultation${(data.consultations || 0) > 1 ? 's' : ''}`"
                                        severity="secondary"
                                    />
                                    <Tag
                                        :value="`${(data.actes || []).length} soin${(data.actes || []).length > 1 ? 's' : ''}`"
                                        severity="info"
                                    />
                                    <Tag
                                        v-if="(data.paiements_reliquats || []).length"
                                        :value="`${data.paiements_reliquats.length} reliquat${data.paiements_reliquats.length > 1 ? 's' : ''}`"
                                        severity="warn"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                <div
                                    v-for="card in doctorSummaryCards(data)"
                                    :key="card.key"
                                    class="rounded-xl border border-surface-200/70 p-3 shadow-sm dark:border-surface-700/70"
                                    :class="card.bg"
                                >
                                    <div class="mb-2 flex items-center gap-2">
                                        <span
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/70 dark:bg-surface-900/40"
                                            :class="card.tone"
                                        >
                                            <i :class="card.icon" />
                                        </span>
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-surface-500">{{ card.label }}</p>
                                    </div>
                                    <p class="text-sm font-semibold text-surface-900 dark:text-surface-0">{{ card.value }}</p>
                                </div>
                            </div>

                            <div class="grid gap-5 xl:grid-cols-2">
                                <section class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-4 shadow-sm dark:border-surface-700/70 dark:bg-surface-900/40">
                                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-surface-200/60 pb-3 dark:border-surface-700/60">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-300">
                                                <i class="pi pi-heart" />
                                            </span>
                                            <div>
                                                <h5 class="text-sm font-semibold text-surface-900 dark:text-surface-0">Soins de la période</h5>
                                                <p class="text-xs text-surface-500">Consultations et actes réalisés</p>
                                            </div>
                                        </div>
                                        <Tag :value="`${(data.actes || []).length} ligne${(data.actes || []).length > 1 ? 's' : ''}`" severity="secondary" />
                                    </div>

                                    <div
                                        v-if="!data?.actes?.length"
                                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-surface-300/80 px-4 py-8 text-center dark:border-surface-600/80"
                                    >
                                        <i class="pi pi-inbox mb-2 text-2xl text-surface-400" />
                                        <p class="text-sm text-surface-500">Aucun soin enregistré sur cette période.</p>
                                    </div>

                                    <ul v-else class="max-h-[320px] space-y-3 overflow-y-auto pr-1">
                                        <li
                                            v-for="(row, idx) in data.actes"
                                            :key="`acte-${idx}`"
                                            class="rounded-xl border border-surface-200/70 bg-surface-50/80 p-3 dark:border-surface-700/70 dark:bg-surface-800/60"
                                        >
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0 flex-1">
                                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                                        <strong class="truncate text-sm text-surface-900 dark:text-surface-0">
                                                            {{ row.description }}
                                                        </strong>
                                                        <Tag v-if="row.isInsurance" value="Assurance" severity="info" />
                                                        <Tag
                                                            :value="paymentStatus(actPaymentBasis(row).due, actPaymentBasis(row).paid).label"
                                                            :severity="paymentStatus(actPaymentBasis(row).due, actPaymentBasis(row).paid).severity"
                                                        />
                                                    </div>
                                                    <p class="text-xs text-surface-500">
                                                        <i class="pi pi-user mr-1 text-[10px]" />
                                                        {{ row.patient }}
                                                        <span class="mx-1">•</span>
                                                        <i class="pi pi-calendar mr-1 text-[10px]" />
                                                        {{ row.date }}
                                                    </p>
                                                </div>
                                                <div class="grid shrink-0 gap-2 text-right" :class="row.isInsurance ? 'grid-cols-2 sm:min-w-[280px] sm:grid-cols-4' : 'grid-cols-2 sm:min-w-[210px]'">
                                                    <div class="rounded-lg bg-surface-0 px-2 py-1 dark:bg-surface-900/50">
                                                        <p class="text-[10px] uppercase text-surface-500">Apport</p>
                                                        <p class="text-xs font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(row.montant) }}</p>
                                                    </div>
                                                    <div v-if="row.isInsurance" class="rounded-lg bg-violet-50 px-2 py-1 dark:bg-violet-950/30">
                                                        <p class="text-[10px] uppercase text-violet-700 dark:text-violet-300">Assurance</p>
                                                        <p class="text-xs font-semibold text-violet-700 dark:text-violet-200">{{ formatFcfa(row.montantAssurance) }}</p>
                                                    </div>
                                                    <div v-if="row.isInsurance" class="rounded-lg bg-surface-0 px-2 py-1 dark:bg-surface-900/50">
                                                        <p class="text-[10px] uppercase text-surface-500">Part patient</p>
                                                        <p class="text-xs font-semibold text-surface-900 dark:text-surface-0">{{ formatFcfa(row.montantPatient) }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-emerald-50 px-2 py-1 dark:bg-emerald-950/30">
                                                        <p class="text-[10px] uppercase text-emerald-700 dark:text-emerald-300">Payé</p>
                                                        <p class="text-xs font-semibold text-emerald-700 dark:text-emerald-200">{{ formatFcfa(row.montantPaye) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <div class="mb-1 flex items-center justify-between text-[10px] text-surface-500">
                                                    <span>{{ row.isInsurance ? 'Recouvrement part patient' : 'Taux de recouvrement' }}</span>
                                                    <span>{{ paymentProgress(actPaymentBasis(row).due, actPaymentBasis(row).paid) }}%</span>
                                                </div>
                                                <div class="h-1.5 overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700">
                                                    <div
                                                        class="h-full rounded-full bg-emerald-500 transition-all"
                                                        :style="{ width: `${paymentProgress(actPaymentBasis(row).due, actPaymentBasis(row).paid)}%` }"
                                                    />
                                                </div>
                                            </div>
                                        </li>
                                    </ul>

                                    <div
                                        v-if="data?.actes?.length"
                                        class="mt-4 grid gap-2 rounded-xl border border-surface-200/70 bg-surface-50/70 p-3 text-xs dark:border-surface-700/70 dark:bg-surface-800/50 sm:grid-cols-2"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-surface-500">Total apport</span>
                                            <strong class="text-surface-900 dark:text-surface-0">{{ formatFcfa(sumActesField(data, 'montant')) }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-surface-500">Total payé (soins)</span>
                                            <strong class="text-emerald-600 dark:text-emerald-300">{{ formatFcfa(sumActesField(data, 'montantPaye')) }}</strong>
                                        </div>
                                        <div class="flex items-center justify-between gap-2 sm:col-span-2">
                                            <span class="text-surface-500">Montant réellement encaissé</span>
                                            <strong class="text-sky-700 dark:text-sky-300">{{ formatFcfa(doctorCashCollected(data)) }}</strong>
                                        </div>
                                        <div
                                            v-if="Number(data?.apport_cabinet_exclu) > 0"
                                            class="flex items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-2 text-amber-900 sm:col-span-2 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                                        >
                                            <span>Services cabinet sur vos consultations (non inclus dans vos apports)</span>
                                            <strong>{{ formatFcfa(data.apport_cabinet_exclu) }}</strong>
                                        </div>
                                    </div>
                                </section>

                                <section class="rounded-2xl border border-surface-200/70 bg-surface-0/80 p-4 shadow-sm dark:border-surface-700/70 dark:bg-surface-900/40">
                                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-surface-200/60 pb-3 dark:border-surface-700/60">
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-300">
                                                <i class="pi pi-replay" />
                                            </span>
                                            <div>
                                                <h5 class="text-sm font-semibold text-surface-900 dark:text-surface-0">Paiements de reliquats</h5>
                                                <p class="text-xs text-surface-500">Encaissements sur consultations antérieures</p>
                                            </div>
                                        </div>
                                        <Tag :value="formatFcfa(data.paiements_reliquats_total)" severity="info" />
                                    </div>

                                    <div
                                        v-if="!data?.paiements_reliquats?.length"
                                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-surface-300/80 px-4 py-8 text-center dark:border-surface-600/80"
                                    >
                                        <i class="pi pi-check-circle mb-2 text-2xl text-surface-400" />
                                        <p class="text-sm text-surface-500">Aucun paiement de reliquat sur cette période.</p>
                                    </div>

                                    <ul v-else class="max-h-[320px] space-y-3 overflow-y-auto pr-1">
                                        <li
                                            v-for="(row, idx) in data.paiements_reliquats"
                                            :key="`reliquat-${idx}`"
                                            class="rounded-xl border border-sky-200/70 bg-sky-50/50 p-3 dark:border-sky-900/40 dark:bg-sky-950/20"
                                        >
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0 flex-1">
                                                    <strong class="block truncate text-sm text-surface-900 dark:text-surface-0">
                                                        {{ row.description }}
                                                    </strong>
                                                    <p class="mt-1 text-xs text-surface-500">
                                                        <i class="pi pi-user mr-1 text-[10px]" />
                                                        {{ row.patient }}
                                                    </p>
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-white/80 px-2 py-0.5 text-[11px] text-surface-600 dark:bg-surface-900/50 dark:text-surface-300">
                                                            <i class="pi pi-credit-card text-[10px]" />
                                                            Paiement {{ row.date }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-white/80 px-2 py-0.5 text-[11px] text-surface-600 dark:bg-surface-900/50 dark:text-surface-300">
                                                            <i class="pi pi-calendar text-[10px]" />
                                                            Consultation {{ row.consultation_date }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <Tag :value="formatFcfa(row.montant)" severity="info" class="shrink-0" />
                                            </div>
                                        </li>
                                    </ul>

                                    <div
                                        v-if="data?.paiements_reliquats?.length"
                                        class="mt-4 flex items-center justify-between rounded-xl border border-sky-200/70 bg-sky-50/70 px-3 py-2 text-sm dark:border-sky-900/40 dark:bg-sky-950/20"
                                    >
                                        <span class="font-medium text-surface-700 dark:text-surface-200">Total Paiements de reliquats</span>
                                        <strong class="text-sky-700 dark:text-sky-300">{{ formatFcfa(data.paiements_reliquats_total) }}</strong>
                                    </div>
                                </section>
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
