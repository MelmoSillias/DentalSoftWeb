<template>
    <div class="ticket-header">
        <img :src="logoSrc" alt="Cabinet Dentaire Orodent" class="header-logo" />
        <div class="title">{{ title }}</div>
    </div>
</template>

<script setup>
import headerImg from '@/assets/header-big.jpeg';

defineProps({
    title: { type: String, default: 'Ticket de caisse' },
    logoSrc: { type: String, default: headerImg }
});
</script>

<style scoped>
.ticket-header {
    text-align: center;
    margin-bottom: 3px;
}

.header-logo {
    width: 100%;
    max-height: 30mm;
    object-fit: contain;
    margin-bottom: 3px;
}

.title {
    font-weight: 700;
}
</style>
