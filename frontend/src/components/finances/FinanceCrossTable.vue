<script setup>
import { computed, onMounted, ref, watch, createApp, nextTick } from 'vue';
import FinanceCrossTablePrint, { printStyles } from './FinanceCrossTablePrint.vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import SelectButton from 'primevue/selectbutton';
import { useFinances } from '@/composables/useFinances';

const props = defineProps({
    title: { type: String, default: 'Tableau croisé' },
    subtitle: { type: String, default: 'Répartition hebdomadaire des transactions validées par date de validation.' },
    className: { type: String, default: '' },
    printerHeader: { type: String, default: '' }
});

const { crossTableData, loading, fetchCrossTable } = useFinances();

const printRef = ref(null);

const printCrossTable = async () => {
    const headerHtml = props.printerHeader || '';
    const features = 'width=1200,height=900,scrollbars=yes';
    const target = window.open('', '_blank', features);

    if (!target) {
        window.print();
        return;
    }

    target.document.open();
    target.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${props.title || ''}</title></head><body></body></html>`);
    target.document.close();

    // copy styles from current document
    const styles = Array.from(document.querySelectorAll('style, link[rel="stylesheet"]'));
    styles.forEach((node) => target.document.head.appendChild(node.cloneNode(true)));

    // inject minimal print styles ensuring landscape plus component-specific styles
    const styleEl = target.document.createElement('style');
    styleEl.type = 'text/css';
    styleEl.textContent = `@page { size: landscape; margin: 15mm; } body { background:#fff; color:#111827; }\n` + (printStyles || '');
    target.document.head.appendChild(styleEl);

    const container = target.document.createElement('div');
    target.document.body.appendChild(container);

    const app = createApp(FinanceCrossTablePrint, { crossTableData: crossTableData.value, headerHtml, title: props.title });
    app.mount(container);

    await nextTick();

    // wait for a short time to ensure resources (fonts/images) are ready
    setTimeout(() => {
        try { target.focus(); target.print(); } catch (e) { /* ignore */ }
        try { target.close(); } catch (e) { /* ignore */ }
    }, 400);
};

const currentMonth = new Date();
const monthPicker = ref(new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1));
const selectedType = ref('revenue');

const typeOptions = computed(() => crossTableData.value?.availableTypes || [
    { label: 'Revenus', value: 'revenue' },
    { label: 'Dépenses', value: 'expense' }
]);

const loadCrossTable = async () => {
    const sourceDate = monthPicker.value instanceof Date ? monthPicker.value : new Date();
    await fetchCrossTable({
        year: sourceDate.getFullYear(),
        month: sourceDate.getMonth() + 1,
        type: selectedType.value
    });
};

watch([monthPicker, selectedType], () => {
    loadCrossTable();
});

onMounted(() => {
    loadCrossTable();
});

const weeks = computed(() => crossTableData.value?.weeks || []);
const rows = computed(() => crossTableData.value?.rows || []);
const columnTotals = computed(() => crossTableData.value?.columnTotals || []);
const grandTotal = computed(() => Number(crossTableData.value?.grandTotal || 0));

const formatFcfa = (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF' }).format(Number(value || 0));

const formatWeekRange = (week) => {
    if (!week?.startDate || !week?.endDate) {
        return '';
    }

    const start = new Date(week.startDate);
    const end = new Date(week.endDate);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return '';
    }

    return `du ${start.toLocaleDateString('fr-FR')} au ${end.toLocaleDateString('fr-FR')}`;
};

const getCellDate = (week, weekday) => {
    if (!week?.startDate || !week?.endDate || !weekday) {
        return null;
    }

    const start = new Date(`${week.startDate}T00:00:00`);
    const end = new Date(`${week.endDate}T00:00:00`);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return null;
    }

    const cursor = new Date(start);
    while (cursor <= end) {
        const cursorWeekday = cursor.getDay() === 0 ? 7 : cursor.getDay();
        if (cursorWeekday === Number(weekday)) {
            return cursor;
        }
        cursor.setDate(cursor.getDate() + 1);
    }

    return null;
};

const formatCellDate = (week, weekday) => {
    const date = getCellDate(week, weekday);
    if (!date) {
        return '';
    }

    return date.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit'
    });
};
</script>

<template>
    <section :class="['overflow-hidden rounded-2xl border border-surface-200/70 bg-surface-0/80 shadow-xl backdrop-blur-sm dark:border-surface-700/50 dark:bg-surface-800/80', className]">
        <div class="border-b border-surface-200/50 bg-gradient-to-r from-surface-50/50 to-surface-0/30 px-5 py-4 dark:border-surface-700/50 dark:from-surface-900/50 dark:to-surface-800/30 md:px-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100 md:text-xl">{{ title }}</h2>
                    <p class="text-sm text-surface-500 dark:text-surface-400">{{ subtitle }}</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <DatePicker
                        v-model="monthPicker"
                        view="month"
                        dateFormat="mm/yy"
                        showIcon
                        class="w-full sm:w-40"
                        inputClass="w-full" />
                    <SelectButton
                        v-model="selectedType"
                        :options="typeOptions"
                        optionLabel="label"
                        optionValue="value"
                        :allowEmpty="false" />
                    <Button icon="pi pi-print" severity="secondary" outlined class="ml-2" @click="printCrossTable" />
                    <Button icon="pi pi-refresh" severity="secondary" outlined @click="loadCrossTable" />
                </div>
            </div>
        </div>

        <div class="px-5 py-4 md:px-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm font-medium text-surface-700 dark:text-surface-300 ">
                    {{ crossTableData.monthLabel || 'Période courante' }} · {{ crossTableData.typeLabel || 'Revenus' }}
                </p>
                <Tag :severity="selectedType === 'revenue' ? 'success' : 'danger'" class="text-lg font-semibold">
                    Total général : {{ formatFcfa(grandTotal) }}
                </Tag>
            </div>

            <div v-if="loading.crossTable" class="grid gap-3">
                <div v-for="index in 5" :key="index" class="h-12 animate-pulse rounded-xl bg-surface-100 dark:bg-surface-700/50"></div>
            </div>

            <div v-else ref="printRef" class="overflow-x-auto">
                <table class="min-w-full border-separate border-spacing-0 overflow-hidden rounded-2xl text-sm">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 bg-surface-500 px-4 py-4 text-left font-semibold uppercase tracking-wide text-white dark:bg-surface-900">
                                Jours de la semaine
                            </th>
                            <th
                                v-for="week in weeks"
                                :key="week.index"
                                class="min-w-[180px] bg-surface-500 px-4 py-4 text-center font-semibold uppercase tracking-wide text-white dark:bg-surface-900"
                            >
                                <div>{{ week.label }}</div>
                                <div class="mt-1 text-xs font-normal normal-case text-surface-200">{{ formatWeekRange(week) }}</div>
                            </th>
                            <th class=" bg-surface-900 px-4 py-4 text-center font-semibold uppercase tracking-wide text-white dark:bg-surface-900">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.weekday" class="odd:bg-surface-50/80 odd:dark:bg-surface-900/30 even:bg-surface-0 even:dark:bg-surface-800/20 min-h-[3rem]">
                            <th class="sticky left-0 bg-inherit px-4 py-3 text-left text-base font-semibold text-primary-600 dark:text-primary-300">
                                {{ row.label }}
                            </th>
                            <td v-for="(value, index) in row.values" :key="`${row.weekday}-${index}`" class="px-4 py-3 text-surface-900 dark:text-surface-100">
                                <div class="relative flex min-h-[5.5rem] items-center justify-center">
                                    <span class="text-center text-base font-medium leading-snug">{{ formatFcfa(value) }}</span>
                                    <span class="absolute bottom-0 right-0 text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                        {{ formatCellDate(weeks[index], row.weekday) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold text-surface-900 dark:text-surface-50 min-w-[120px]">
                                {{ formatFcfa(row.total) }}
                            </td>
                        </tr>
                        <tr class="bg-surface-200/80 dark:bg-surface-700/70">
                            <th class="sticky left-0 bg-inherit px-4 py-3 text-left text-base font-semibold text-surface-900 dark:text-surface-50">
                                Total
                            </th>
                            <td v-for="(value, index) in columnTotals" :key="`total-${index}`" class="px-4 py-3 text-center font-semibold text-surface-900 dark:text-surface-50 min-w-[120px]">
                                {{ formatFcfa(value) }}
                            </td>
                            <td class="px-4 py-3 text-center font-bold text-surface-950 dark:text-white">
                                {{ formatFcfa(grandTotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<style scoped>  
    :deep(table td) {
        height: 6.5rem !important;
    }
</style>