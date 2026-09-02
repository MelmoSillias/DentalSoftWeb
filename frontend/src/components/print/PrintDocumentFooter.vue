<script setup>
import { computed } from 'vue';
import { usePrintProfile } from '@/composables/usePrintProfile';

const { profile } = usePrintProfile();

const printedDate = computed(() => new Date().toLocaleDateString('fr-FR'));

const contactLine = computed(() => {
    const parts = [];
    if (profile.phones.length) parts.push(profile.phones.join(' · '));
    if (profile.email) parts.push(profile.email);
    return parts.join(' · ');
});
</script>

<template>
    <footer class="print-doc-footer">
        <div class="print-doc-footer__rule" />
        <div class="print-doc-footer__row">
            <span class="print-doc-footer__name">{{ profile.name }}</span>
            <span v-if="contactLine" class="print-doc-footer__contact">{{ contactLine }}</span>
            <span class="print-doc-footer__date">Édité le {{ printedDate }}</span>
        </div>
    </footer>
</template>

<style scoped>
.print-doc-footer__rule {
    height: 2px;
    background: linear-gradient(to right, transparent, #1d6fbf 20%, #1d6fbf 80%, transparent);
    margin-bottom: 6px;
}

.print-doc-footer__row {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 6px 12px;
    font-size: 8.5pt;
    color: #586574;
}

.print-doc-footer__name {
    font-weight: 700;
    color: #111827;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.print-doc-footer__date {
    margin-left: auto;
}

@media print {
    .print-doc-footer {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
