<script setup>
import PrintCabinetHeader from './PrintCabinetHeader.vue';

defineProps({
    title: { type: String, default: '' }
});
</script>

<template>
    <header class="ticket-header">
        <PrintCabinetHeader variant="ticket" />
        <div v-if="title" class="ticket-header__title-band">
            <span class="ticket-header__line" />
            <span class="ticket-header__title">{{ title }}</span>
            <span class="ticket-header__line" />
        </div>
    </header>
</template>

<style scoped>
.ticket-header {
    margin-bottom: 6px;
    color: #000;
}

.ticket-header__title-band {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
}

.ticket-header__line {
    flex: 1;
    height: 2px;
    background: #000;
}

.ticket-header__title {
    flex-shrink: 0;
    font-weight: 800;
    font-size: 12px;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #000;
    -webkit-text-stroke: 0.2px #000;
}

@media print {
    .ticket-header,
    .ticket-header__title {
        color: #000 !important;
    }

    .ticket-header__line {
        background: #000 !important;
    }
}
</style>
