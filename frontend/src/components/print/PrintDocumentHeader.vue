<template>
    <div class="print-doc-header">
        <div class="logo">
            <img :src="logoSrc" alt="Cabinet Dentaire Orodent" />
        </div>
        <div class="meta">
            <p class="title">{{ title }}</p>
            <p class="info">N° {{ formattedNumber }}</p>
            <p class="info">Date : {{ dateLabel }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import headerImg from '@/assets/header-big.jpeg';

const props = defineProps({
    title: { type: String, default: '' },
    docId: { type: [String, Number], default: '' },
    date: { type: [String, Date], default: '' },
    logoSrc: { type: String, default: headerImg }
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
</script>

<style scoped>
.print-doc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16mm;
}

.logo img {
    max-height: 90px;
    width: 100%;
    object-fit: contain;
}

.meta {
    text-align: right;
}

.meta .title {
    font-size: 20pt;
    font-weight: 700;
    margin: 0;
    text-transform: uppercase;
}

.meta .info {
    color: #586574;
    margin: 2px 0 0;
    font-size: 12pt;
}
</style>
