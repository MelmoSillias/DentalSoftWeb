<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import AmbientBackground from './components/AmbientBackground.vue';
import { useTheme } from './composables/useTheme';
import OfflineConnectionBanner from './components/OfflineConnectionBanner.vue';
import { APP_TOAST_EVENT } from './services/toastBus';

const toast = useToast();
useTheme().initTheme();

function handleAppToast(event) {
    const payload = event?.detail;
    if (!payload) {
        return;
    }

    toast.add({
        severity: payload.severity || 'info',
        summary: payload.summary || 'Information',
        detail: payload.detail || '',
        life: payload.life || 4000,
    });
}

onMounted(() => {
    window.addEventListener(APP_TOAST_EVENT, handleAppToast);
});

onBeforeUnmount(() => {
    window.removeEventListener(APP_TOAST_EVENT, handleAppToast);
});
</script>

<template>
    <a class="skip-link" href="#main-content">Aller au contenu</a>
    <AmbientBackground />
    <OfflineConnectionBanner />
    <Toast position="top-right" />
    <RouterView />
</template>
