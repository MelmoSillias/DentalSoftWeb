<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import AmbientBackground from '@/shared/ui/AmbientBackground.vue';
import AppLayout from '@/app/layout/AppLayout.vue';
import { useTheme } from '@/shared/composables/useTheme.js';
import OfflineConnectionBanner from '@/shared/ui/OfflineConnectionBanner.vue';
import { APP_TOAST_EVENT } from '@/shared/api/toastBus.js';

import { useAuthStore } from '../domains/auth/index.js';
import PwaUpdateBanner from '../shared/ui/PwaUpdateBanner.vue';
import { usePwaUpdate } from '../shared/composables/usePwaUpdate.js';
const { mandatory } = usePwaUpdate();
const auth = useAuthStore();
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
    <PwaUpdateBanner />
    <template v-if="!mandatory">
    <a class="skip-link" href="#main-content">Aller au contenu</a>
    <AmbientBackground />
    <OfflineConnectionBanner />
    <Toast position="top-right" />
    <RouterView v-slot="{ Component, route }">
        <component :is="route.meta.requiresAuth ? AppLayout : 'div'" class="route-host">
            <Transition name="portal-page" mode="out-in">
                <component :is="Component" :key="`${auth.version}:${route.path}`" />
            </Transition>
        </component>
    </RouterView>
    </template>
</template>
