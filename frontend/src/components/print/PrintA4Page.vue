<template>
    <div
        class="print-a4-page"
        :class="[`print-a4-page--${orientation}`]"
        :style="watermarkStyle"
    >
        <div v-if="watermark" class="print-a4-page__watermark" aria-hidden="true" />
        <div class="print-a4-page__inner">
            <slot name="header" />
            <main class="print-a4-page__body">
                <slot />
            </main>
            <PrintDocumentFooter v-if="showFooter" class="print-a4-page__footer" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import PrintDocumentFooter from './PrintDocumentFooter.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    logoSrc: { type: String, default: logoImg },
    watermark: { type: Boolean, default: true },
    showFooter: { type: Boolean, default: true },
    orientation: {
        type: String,
        default: 'portrait',
        validator: (value) => ['portrait', 'landscape'].includes(value)
    }
});

const watermarkStyle = computed(() => ({
    '--print-watermark': props.watermark ? `url(${props.logoSrc})` : 'none'
}));
</script>

<style src="@/styles/print-layout.css"></style>
