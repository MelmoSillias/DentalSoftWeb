<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader :title="title" :doc-id="docId" :date="date" />
        </template>

        <div v-if="subtitle" class="subtitle">{{ subtitle }}</div>

        <table class="print-doc-table">
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
    </PrintA4Page>
</template>

<script setup>
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

defineProps({
    title: { type: String, default: 'Export' },
    subtitle: { type: String, default: '' },
    date: { type: [String, Date], default: '' },
    docId: { type: [String, Number], default: '' },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    logoSrc: { type: String, default: logoImg }
});
</script>

<style scoped>
.subtitle {
    color: #586574;
    font-size: 10.5pt;
    margin: -4mm 0 8px;
}
</style>
