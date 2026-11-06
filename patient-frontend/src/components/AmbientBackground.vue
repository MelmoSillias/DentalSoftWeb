<script setup>
import cabinetConfig from '../cabinetConfig';
</script>
<template>
    <div class="ambient-background" aria-hidden="true">
        <img v-if="cabinetConfig.logo" class="ambient-logo-watermark" :src="'/' + cabinetConfig.logo" alt="" />
        <span class="ambient-orb orb-one" /><span class="ambient-orb orb-two" />
        <span class="ambient-ring ring-one" /><span class="ambient-ring ring-two" />
        <span class="ambient-grid" />
    </div>
</template>
