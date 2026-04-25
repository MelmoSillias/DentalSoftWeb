<script setup>
import { computed } from 'vue';

const props = defineProps({
    crossTableData: { type: Object, required: true },
    headerHtml: { type: String, default: '' },
    title: { type: String, default: 'Tableau croisé' }
});

const weeks = computed(() => props.crossTableData?.weeks || []);
const rows = computed(() => props.crossTableData?.rows || []);
const columnTotals = computed(() => props.crossTableData?.columnTotals || []);
const grandTotal = computed(() => Number(props.crossTableData?.grandTotal || 0));

const formatFcfa = (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(Number(value || 0));

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
</script>

<script>
// Cette constante est utile si vous passez le style à une librairie comme Print.js
export const printStyles = `
    @page { size: landscape; margin: 10mm; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-root { font-family: sans-serif; color: #111827; background: #fff; padding: 5mm; }
    .print-header { margin-bottom: 15px; }
    .print-title { margin-bottom: 15px; font-size: 18pt; font-weight: bold; }
    .print-table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    .print-table th, .print-table td { border: 1px solid #d1d5db !important; padding: 8px; text-align: left; }
    .print-table thead th { background-color: #f3f4f6 !important; font-weight: bold; }
    .muted, .cell-date { font-size: 8pt; color: #6b7280 !important; }
    .row-label { font-weight: 600; background-color: #f9fafb; }
    .row-total, .grand-total { font-weight: bold; text-align: right; }
    .totals-row th, .totals-row td { background-color: #f3f4f6 !important; font-weight: bold; }
`;
</script>

<template>
    <div class="print-root">
        <div v-if="headerHtml" class="print-header" v-html="headerHtml"></div>
        <h2 class="print-title">{{ title }}</h2>

        <table class="print-table">
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
                        <div class="cell-value">{{ formatFcfa(value) }}</div>
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
    </div>
</template>

<style>
/* Style appliqué uniquement lors de l'impression physique */
@media print {
    /* Injecte la constante définie plus haut */
    @page { size: landscape; margin: 10mm; }
    
    body { background: white !important; }

    /* On force l'affichage des couleurs */
    * { 
        -webkit-print-color-adjust: exact !important; 
        print-color-adjust: exact !important; 
    }

    .print-root { 
        font-family: -apple-system, system-ui, sans-serif;
        width: 100%;
    }

    .print-table { 
        width: 100%; 
        border-collapse: collapse; 
    }

    .print-table th, .print-table td { 
        border: 1px solid #000 !important; /* Bordure plus sombre pour papier */
        padding: 4px 8px; 
    }

    .print-table thead th { 
        background-color: #f3f4f6 !important; 
    }

    .totals-row th, .totals-row td { 
        background-color: #f3f4f6 !important; 
    }
}

/* Style pour l'écran (optionnel, pour que ça soit joli aussi dans l'app) */
@media screen {
    .print-root { padding: 20px; background: #fff; border: 1px solid #eee; margin: 10px; border-radius: 8px; }
    .print-table { width: 100%; border-collapse: collapse; }
    .print-table th, .print-table td { border: 1px solid #e5e7eb; padding: 8px; }
    .print-table thead th { background: #f9fafb; }
}
</style>
