<script setup>
import cabinetConfig from '../cabinetConfig';
defineProps({ light: Boolean });
</script>
<template>
    <span class="brand-lockup" :class="{ 'brand-light': light }">
        <span class="brand-symbol" aria-hidden="true">
            <img v-if="cabinetConfig.logo" :src="'/' + cabinetConfig.logo" alt="" class="brand-logo" />
            <svg v-else viewBox="0 0 40 40" fill="none">
                <path d="M11 8c-6 1-7 8-4 15 2 5 3 11 6 11 3 0 2-10 7-10s4 10 7 10 4-6 6-11c3-7 2-14-4-15-4-1-5 2-9 2s-5-3-9-2Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" />
                <path d="M15 15c3 2 7 2 10 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </span>
        <span class="brand-copy"><strong>{{ cabinetConfig.brandName || cabinetConfig.displayName }}</strong><small>MON ESPACE PATIENT</small></span>
    </span>
</template>
