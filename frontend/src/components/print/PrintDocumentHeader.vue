<script setup>
import { computed } from 'vue';
import PrintCabinetHeader from './PrintCabinetHeader.vue';

const props = defineProps({
    title: { type: String, default: '' },
    docId: { type: [String, Number], default: '' },
    date: { type: [String, Date], default: '' }
});

const formattedNumber = computed(() => {
    const raw = props.docId ?? '';
    const text = typeof raw === 'number' ? String(raw) : String(raw || '');
    return text ? text.padStart(4, '0') : '—';
});

const dateLabel = computed(() => {
    if (!props.date) return '—';
    const raw = props.date instanceof Date ? props.date : new Date(props.date);
    if (Number.isNaN(raw.getTime())) return String(props.date);
    return raw.toLocaleDateString('fr-FR');
});

const hasMeta = computed(() => formattedNumber.value !== '—' || dateLabel.value !== '—');
</script>

<template>
    <header class="print-doc-header">
        <PrintCabinetHeader variant="a4" class="print-doc-header__cabinet" />

        <div class="print-doc-header__title-band">
            <span class="print-doc-header__ornament" aria-hidden="true" />
            <div class="print-doc-header__title-block">
                <h1 class="print-doc-header__title">{{ title }}</h1>
                <div v-if="hasMeta" class="print-doc-header__meta">
                    <span v-if="formattedNumber !== '—'" class="print-doc-header__badge">N° {{ formattedNumber }}</span>
                    <span v-if="dateLabel !== '—'" class="print-doc-header__date">Date : {{ dateLabel }}</span>
                </div>
            </div>
            <span class="print-doc-header__ornament" aria-hidden="true" />
        </div>
    </header>
</template>

<style scoped>
.print-doc-header {
    margin-bottom: 10mm;
}

.print-doc-header__cabinet {
    width: 100%;
    margin-bottom: 6mm;
    padding-bottom: 5mm;
    border-bottom: 2px solid #1d6fbf;
}

.print-doc-header__title-band {
    display: flex;
    align-items: center;
    gap: 10px;
}

.print-doc-header__ornament {
    flex: 1 1 0;
    height: 1px;
    background: linear-gradient(to right, transparent, #cfd8e3);
}

.print-doc-header__ornament:last-child {
    background: linear-gradient(to left, transparent, #cfd8e3);
}

.print-doc-header__title-block {
    flex: 0 1 auto;
    text-align: center;
    min-width: 0;
}

.print-doc-header__title {
    margin: 0;
    font-size: 17pt;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #111827;
    line-height: 1.2;
}

.print-doc-header__meta {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 8px 14px;
    margin-top: 5px;
    font-size: 10pt;
    color: #586574;
}

.print-doc-header__badge {
    display: inline-block;
    padding: 2px 10px;
    border: 1px solid #1d6fbf;
    border-radius: 2px;
    font-weight: 600;
    color: #1d6fbf;
    font-size: 9.5pt;
}

.print-doc-header__date {
    font-weight: 500;
}
</style>
