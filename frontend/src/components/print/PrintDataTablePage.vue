<script setup>
import { computed } from 'vue';
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    title: { type: String, default: 'Export' },
    subtitle: { type: String, default: '' },
    date: { type: [String, Date], default: '' },
    docId: { type: [String, Number], default: '' },
    columns: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    logoSrc: { type: String, default: logoImg },
    orientation: {
        type: String,
        default: '',
        validator: (value) => ['', 'portrait', 'landscape'].includes(value)
    }
});

// Les listes larges ont besoin de place pour les noms, dates et descriptions.
const pageOrientation = computed(() => props.orientation || (props.columns.length >= 4 ? 'landscape' : 'portrait'));
</script>

<template>
    <PrintA4Page :logo-src="logoSrc" :orientation="pageOrientation">
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

<style scoped>
.subtitle {
    color: #586574;
    font-size: 10.5pt;
    margin: -4mm 0 8px;
}
</style>
