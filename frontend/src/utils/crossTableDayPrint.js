import { buildDataTableHtml, buildKeyValueTableHtml, formatPeriodSubtitle, openPrintWindow, printReport } from '@/utils/reportPrint';
import { buildPrintHtmlDocument, buildPrintTitleBandHtml } from '@/utils/printDocumentStyles';

export const DAY_PRINT_SECTIONS = [
    { key: 'newPatients', label: 'Nouveaux patients' },
    { key: 'appointments', label: 'Rendez-vous' },
    { key: 'consultations', label: 'Consultations' },
    { key: 'revenues', label: 'Encaissements validés' },
    { key: 'expenses', label: 'Dépenses (transactions validées)' },
    { key: 'actsDetail', label: 'Actes posés (liste détaillée)' },
    { key: 'actsByType', label: 'Totaux par actes posés (nombre)' },
    { key: 'doctorsTotals', label: 'Totaux par médecins (montant)' }
];

export const DEFAULT_DAY_PRINT_SELECTION = Object.fromEntries(DAY_PRINT_SECTIONS.map((section) => [section.key, true]));

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

function formatDateTime(value) {
    if (!value) {
        return '--';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '--';
    }
    return date.toLocaleString('fr-FR');
}

function mapTransactionRows(transactions) {
    return (transactions || []).map((row) => ({
        date: formatDateTime(row.validatedAt || row.dateTransaction),
        description: row.description || 'Sans description',
        type: row.typeLabel || row.typeKey || '--',
        motif: row.motif || 'Sans motif',
        amount: formatFcfa(row.amount ?? row.montant),
        mode: row.modeDePaiement?.libelle || '--'
    }));
}

function mapActRows(actes) {
    return (actes || []).map((act) => ({
        date: act.date || '--',
        medecin: act.medecin || '--',
        patient: act.patient || '--',
        description: act.description || '--',
        montant: formatFcfa(act.montant)
    }));
}

export function printDayTransactions(transactions, { dateLabel = '', filterLabel = 'Toutes' } = {}) {
    const rows = mapTransactionRows(transactions);
    const total = (transactions || []).reduce((sum, row) => sum + Number(row.amount ?? row.montant ?? 0), 0);

    const body = `
        ${buildPrintTitleBandHtml(`Transactions — ${filterLabel}`, formatPeriodSubtitle(dateLabel))}
        ${buildDataTableHtml({
            columns: [
                { key: 'date', label: 'Date validation' },
                { key: 'description', label: 'Description' },
                { key: 'type', label: 'Type' },
                { key: 'motif', label: 'Motif' },
                { key: 'amount', label: 'Montant', align: 'right' },
                { key: 'mode', label: 'Mode' }
            ],
            rows,
            emptyLabel: 'Aucune transaction sur cette journée.'
        })}
        <p style="margin-top: 12px; font-weight: 600;">Total = ${formatFcfa(total)}</p>
    `;

    openPrintWindow(buildPrintHtmlDocument({ title: `Transactions ${filterLabel}`, body }));
}

export function printDayActs(actes, { dateLabel = '' } = {}) {
    const rows = mapActRows(actes);
    const total = (actes || []).reduce((sum, act) => sum + Number(act.montant || 0), 0);

    const body = `
        ${buildPrintTitleBandHtml('Liste des actes médicaux', formatPeriodSubtitle(dateLabel))}
        ${buildDataTableHtml({
            columns: [
                { key: 'date', label: 'Date' },
                { key: 'medecin', label: 'Médecin' },
                { key: 'patient', label: 'Patient' },
                { key: 'description', label: 'Description' },
                { key: 'montant', label: 'Montant', align: 'right' }
            ],
            rows,
            emptyLabel: 'Aucun acte enregistré sur cette journée.'
        })}
        <p style="margin-top: 12px; font-weight: 600;">Total = ${formatFcfa(total)}</p>
    `;

    openPrintWindow(buildPrintHtmlDocument({ title: 'Actes médicaux', body }));
}

export function buildDayReportSections(data, selection = DEFAULT_DAY_PRINT_SELECTION) {
    const sections = [];

    if (selection.newPatients) {
        sections.push({
            title: 'Nouveaux patients',
            items: [
                { label: 'Nouveaux patients', value: data.patients?.newPatients ?? 0 },
                { label: 'Patients de retour', value: data.patients?.returningPatients ?? 0 }
            ],
            emptyLabel: 'Aucun patient enregistré.'
        });
    }

    if (selection.appointments) {
        sections.push({
            title: 'Rendez-vous',
            items: [
                { label: 'Planifiés', value: data.appointments?.scheduled ?? 0 },
                { label: 'Confirmés', value: data.appointments?.confirmed ?? 0 },
                { label: 'En attente', value: data.appointments?.pending ?? 0 },
                { label: 'Annulés / absences', value: data.appointments?.cancelled ?? 0 }
            ],
            emptyLabel: 'Aucun rendez-vous.'
        });
    }

    if (selection.consultations) {
        sections.push({
            title: 'Consultations',
            items: [
                { label: 'Total', value: data.consultations?.total ?? 0 },
                { label: 'Payantes', value: data.consultations?.paid ?? 0 },
                { label: 'Gratuites', value: data.consultations?.free ?? 0 },
                { label: 'En attente', value: data.consultations?.pending ?? 0 }
            ],
            emptyLabel: 'Aucune consultation.'
        });
    }

    if (selection.revenues) {
        const revenueTransactions = (data.transactions || []).filter((row) => row.typeKey === 'revenue');
        sections.push({
            title: 'Encaissements validés',
            items: [{ label: 'Total encaissements validés', value: formatFcfa(data.totals?.revenue) }],
            note: revenueTransactions.length ? `${revenueTransactions.length} transaction(s) validée(s).` : 'Aucune transaction de revenu validée.'
        });
        if (revenueTransactions.length) {
            sections.push({
                title: 'Détail des encaissements validés',
                columns: [
                    { key: 'date', label: 'Date' },
                    { key: 'description', label: 'Description' },
                    { key: 'amount', label: 'Montant', align: 'right' },
                    { key: 'mode', label: 'Mode' }
                ],
                rows: mapTransactionRows(revenueTransactions).map((row) => ({
                    date: row.date,
                    description: row.description,
                    amount: row.amount,
                    mode: row.mode
                })),
                emptyLabel: 'Aucun revenu.'
            });
        }
    }

    if (selection.expenses) {
        const expenseTransactions = (data.transactions || []).filter((row) => row.typeKey === 'expense');
        sections.push({
            title: 'Dépenses (transactions validées)',
            items: [{ label: 'Total dépenses', value: formatFcfa(data.totals?.expense) }],
            note: expenseTransactions.length ? `${expenseTransactions.length} transaction(s) validée(s).` : 'Aucune transaction de dépense validée.'
        });
        if (expenseTransactions.length) {
            sections.push({
                title: 'Détail des dépenses',
                columns: [
                    { key: 'date', label: 'Date' },
                    { key: 'description', label: 'Description' },
                    { key: 'amount', label: 'Montant', align: 'right' },
                    { key: 'mode', label: 'Mode' }
                ],
                rows: mapTransactionRows(expenseTransactions).map((row) => ({
                    date: row.date,
                    description: row.description,
                    amount: row.amount,
                    mode: row.mode
                })),
                emptyLabel: 'Aucune dépense.'
            });
        }
    }

    if (selection.actsDetail) {
        sections.push({
            title: 'Actes posés (liste détaillée)',
            columns: [
                { key: 'date', label: 'Date' },
                { key: 'medecin', label: 'Médecin' },
                { key: 'patient', label: 'Patient' },
                { key: 'description', label: 'Description' },
                { key: 'montant', label: 'Montant', align: 'right' }
            ],
            rows: mapActRows(data.actes),
            emptyLabel: 'Aucun acte enregistré.'
        });
    }

    if (selection.actsByType) {
        sections.push({
            title: 'Totaux par actes posés (nombre)',
            items: (data.actsByType || []).map((row) => ({
                label: row.label,
                value: row.value ?? 0
            })),
            emptyLabel: 'Aucun acte enregistré.'
        });
    }

    if (selection.doctorsTotals) {
        sections.push({
            title: 'Totaux par médecins (montant)',
            columns: [
                { key: 'name', label: 'Médecin' },
                { key: 'consultations', label: 'Consultations' },
                { key: 'apport', label: 'Montant facturé', align: 'right' },
                { key: 'revenue', label: 'Montant encaissé', align: 'right' }
            ],
            rows: (data.doctors || []).map((doctor) => ({
                name: doctor.name || '--',
                consultations: String(doctor.consultations ?? 0),
                apport: formatFcfa(doctor.apport),
                revenue: formatFcfa(doctor.revenue)
            })),
            emptyLabel: 'Aucune activité médicale.'
        });
    }

    return sections;
}

export function printDayCompositeReport(data, selection = DEFAULT_DAY_PRINT_SELECTION) {
    const sections = buildDayReportSections(data, selection);
    if (!sections.length) {
        return false;
    }

    return printReport({
        title: 'Rapport journalier',
        periodLabel: data.dateLabel || data.date || '',
        sections
    });
}
