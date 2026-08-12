<script setup>
import { computed } from 'vue';
import PrintA4Page from '@/components/print/PrintA4Page.vue';
import PrintDocumentHeader from '@/components/print/PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    crossTableData: { type: Object, required: true },
    headerHtml: { type: String, default: '' },
    title: { type: String, default: 'Tableau croisé' },
    logoSrc: { type: String, default: logoImg }
});

const weeks = computed(() => props.crossTableData?.weeks || []);
const rows = computed(() => props.crossTableData?.rows || []);
const columnTotals = computed(() => props.crossTableData?.columnTotals || []);
const grandTotal = computed(() => Number(props.crossTableData?.grandTotal || 0));
const printDate = computed(() => new Date());

const formatFcfa = (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(Number(value || 0));

const formatCellAmount = (week, weekday, value) => {
    if (!getCellDate(week, weekday)) {
        return '--';
    }
    return formatFcfa(value);
};

const formatWeekRange = (week) => {
    if (!week?.startDate || !week?.endDate) return '';
    const start = new Date(week.startDate);
    const end = new Date(week.endDate);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return '';
    return `du ${start.toLocaleDateString('fr-FR')} au ${end.toLocaleDateString('fr-FR')}`;
};

const getCellDate = (week, weekday) => {
    if (!week?.startDate || !week?.endDate || !weekday) return null;
    const start = new Date(`${week.startDate}T00:00:00`);
    const end = new Date(`${week.endDate}T00:00:00`);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
    const cursor = new Date(start);
    while (cursor <= end) {
        const cursorWeekday = cursor.getDay() === 0 ? 7 : cursor.getDay();
        if (cursorWeekday === Number(weekday)) return cursor;
        cursor.setDate(cursor.getDate() + 1);
    }
    return null;
};

const formatCellDate = (week, weekday) => {
    const date = getCellDate(week, weekday);
    if (!date) return '';
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
};

const getCellMonthEdgeTag = (week, weekday) => {
    const date = getCellDate(week, weekday);
    if (!date) return '';
    const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    if (date.getDate() === 1) return '1er';
    if (date.getDate() === lastDay) return 'Fin';
    return '';
};
</script>

<script>
export const printStyles = `
    @page { size: A4 landscape; margin: 10mm; }
    .print-cross-table { font-size: 10pt; }
    .print-cross-table th, .print-cross-table td { padding: 6px 8px; }
    .muted, .cell-date { font-size: 8pt; color: #6b7280 !important; text-align: right; }
    .cell-value { font-size: 11pt; font-weight: 700; text-align: center; }
    .cell-tag { font-size: 7pt; font-weight: 700; color: #1d4ed8 !important; text-align: left; margin-bottom: 2px; }
    .row-label { font-weight: 600; background-color: #f9fafb; }
    .row-total, .grand-total { font-weight: bold; text-align: right; font-size: 11pt; }
    .totals-row th, .totals-row td { background-color: #eef3f8 !important; font-weight: bold; }
`;
</script>

<template>
    <PrintA4Page :logo-src="logoSrc" orientation="landscape">
        <template #header>
            <div v-if="headerHtml" class="legacy-header" v-html="headerHtml" />
            <PrintDocumentHeader v-else :title="title" :date="printDate" />
        </template>

        <table class="print-doc-table print-cross-table">
            <thead>
                <tr>
                    <th>Jours de la semaine</th>
                    <th v-for="week in weeks" :key="week.index">
                        <div>{{ week.label }}</div>
                        <div class="muted">{{ formatWeekRange(week) }}</div>
                    </th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.weekday">
                    <th class="row-label">{{ row.label }}</th>
                    <td v-for="(value, index) in row.values" :key="`${row.weekday}-${index}`">
                        <div class="cell-tag" v-if="getCellMonthEdgeTag(weeks[index], row.weekday)">{{ getCellMonthEdgeTag(weeks[index], row.weekday) }}</div>
                        <div class="cell-value">{{ formatCellAmount(weeks[index], row.weekday, value) }}</div>
                        <div class="cell-date">{{ formatCellDate(weeks[index], row.weekday) }}</div>
                    </td>
                    <td class="row-total">{{ formatFcfa(row.total) }}</td>
                </tr>
                <tr class="totals-row">
                    <th>Total</th>
                    <td v-for="(value, index) in columnTotals" :key="`total-${index}`">{{ formatFcfa(value) }}</td>
                    <td class="grand-total">{{ formatFcfa(grandTotal) }}</td>
                </tr>
            </tbody>
        </table>
    </PrintA4Page>
</template>

<style scoped>
.legacy-header {
    margin-bottom: 8px;
}

.cell-value {
    font-size: 11pt;
    font-weight: 700;
    text-align: center;
}

.cell-tag {
    font-size: 7pt;
    font-weight: 700;
    color: #1d4ed8;
    text-align: left;
    margin-bottom: 2px;
}

.muted,
.cell-date {
    font-size: 8pt;
    color: #6b7280;
    text-align: right;
}

.row-label {
    font-weight: 600;
    background-color: #f9fafb;
}

.row-total,
.grand-total {
    font-weight: 700;
    text-align: right;
}

.totals-row th,
.totals-row td {
    background-color: #eef3f8;
    font-weight: 700;
}

.print-cross-table {
    font-size: 9.5pt;
}
</style>
