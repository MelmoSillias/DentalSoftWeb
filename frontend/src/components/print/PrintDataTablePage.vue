<template>
    <div class="page">
        <div class="content">
            <PrintDocumentHeader :title="title" :doc-id="docId" :date="date" />

            <div v-if="subtitle" class="subtitle">{{ subtitle }}</div>

            <table class="table">
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key" :style="{ textAlign: col.align || 'left' }">
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in rows" :key="idx">
                        <td v-for="col in columns" :key="col.key" :style="{ textAlign: col.align || 'left' }">
                            {{ row[col.key] ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td :colspan="columns.length" class="empty">Aucune donnée.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import PrintDocumentHeader from './PrintDocumentHeader.vue';

defineProps({
    title: { type: String, default: 'Export' },
    subtitle: { type: String, default: '' },
    date: { type: [String, Date], default: '' },
    docId: { type: [String, Number], default: '' },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] }
});
</script>

<style scoped>
.page {
    position: relative;
    max-width: 210mm;
    margin: 12mm auto;
    padding: 14mm 16mm;
    background: #fff;
    box-shadow: 0 0 8mm rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.content {
    position: relative;
    z-index: 1;
}

.subtitle {
    color: #586574;
    font-size: 11pt;
    margin: 6px 0 14px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11pt;
}

.table th,
.table td {
    border: 1px solid #cfd8e3;
    padding: 8px 10px;
    vertical-align: top;
}

.table th {
    background: #f6f8fb;
    text-align: left;
    font-weight: 700;
}

.table tr:nth-child(even) td {
    background: #fafbfd;
}

.empty {
    text-align: center;
    color: #586574;
}
</style>
