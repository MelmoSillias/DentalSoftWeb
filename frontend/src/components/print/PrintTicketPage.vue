<template>
    <div class="print-ticket-page" :style="watermarkStyle">
        <div v-if="watermark" class="print-ticket-page__watermark" aria-hidden="true" />
        <div class="print-ticket-page__inner">
            <slot />
            <footer v-if="showFooter" class="print-ticket-footer">
                <slot name="footer">
                    Merci de votre confiance !<br />
                    <span v-if="profile.phones.length">Tél : {{ profile.phones.join(' · ') }}</span>
                </slot>
            </footer>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePrintProfile } from '@/composables/usePrintProfile';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    logoSrc: { type: String, default: logoImg },
    watermark: { type: Boolean, default: true },
    showFooter: { type: Boolean, default: true }
});

const { profile } = usePrintProfile();

const watermarkStyle = computed(() => ({
    '--print-watermark': props.watermark ? `url(${props.logoSrc})` : 'none'
}));
</script>

<style src="@/styles/print-layout.css"></style>
