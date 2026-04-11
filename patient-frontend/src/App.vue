<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { APP_TOAST_EVENT } from './services/toastBus';

const toast = useToast();

function handleAppToast(event) {
    const payload = event?.detail;
    if (!payload) {
        return;
    }

    toast.add({
        severity: payload.severity || 'info',
        summary: payload.summary || 'Information',
        detail: payload.detail || '',
        life: payload.life || 4000
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
    <Toast position="top-right" />
    <RouterView />
</template>
