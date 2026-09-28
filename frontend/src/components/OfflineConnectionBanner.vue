<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useNetworkStatus } from '@/composables/useNetworkStatus';

const { isOffline, justRestored, offlineCause } = useNetworkStatus();
const bannerRef = ref(null);

const visible = computed(() => isOffline.value || justRestored.value);

const title = computed(() => {
    if (!isOffline.value) {
        return 'Connexion rétablie';
    }

    if (offlineCause.value === 'browser') {
        return 'Connexion internet coupée';
    }

    return 'Connexion internet interrompue';
});

const message = computed(() => {
    if (!isOffline.value) {
        return 'Vous êtes de nouveau en ligne.';
    }

    if (offlineCause.value === 'browser') {
        return "Votre appareil n'a plus accès au réseau. Ce n'est pas une erreur de l'application : les actions peuvent échouer tant que la connexion n'est pas rétablie.";
    }

    return "Le serveur n'est plus joignable. Vérifiez votre connexion internet : le défaut vient de votre réseau, pas d'une erreur de l'application.";
});

function syncOffset() {
    if (!bannerRef.value) {
        return;
    }

    document.documentElement.style.setProperty('--connection-banner-offset', `${bannerRef.value.offsetHeight}px`);
}

function clearOffset() {
    document.documentElement.style.setProperty('--connection-banner-offset', '0px');
}

watch(visible, async (value) => {
    await nextTick();
    if (value) {
        syncOffset();
    }
});

onMounted(() => {
    window.addEventListener('resize', syncOffset);
    syncOffset();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', syncOffset);
    clearOffset();
});
</script>

<template>
    <Transition name="net-banner" @after-leave="clearOffset">
        <div v-if="visible" ref="bannerRef" class="net-banner" :class="isOffline ? 'net-banner--offline' : 'net-banner--online'" :role="isOffline ? 'alert' : 'status'" aria-live="assertive">
            <i :class="isOffline ? 'pi pi-wifi' : 'pi pi-check-circle'" class="net-banner__icon" aria-hidden="true" />
            <div class="net-banner__copy">
                <p class="net-banner__title">{{ title }}</p>
                <p class="net-banner__text">{{ message }}</p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.net-banner {
    position: sticky;
    top: 0;
    z-index: 12000;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    padding-top: calc(0.75rem + env(safe-area-inset-top, 0px));
    color: #0f172a;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.16);
}

.net-banner--offline {
    background: #fff7ed;
    border-bottom: 1px solid #fdba74;
}

.net-banner--online {
    background: #f0fdf4;
    border-bottom: 1px solid #86efac;
}

.net-banner__icon {
    margin-top: 0.1rem;
    font-size: 1.05rem;
    flex-shrink: 0;
}

.net-banner--offline .net-banner__icon {
    color: #c2410c;
}

.net-banner--online .net-banner__icon {
    color: #15803d;
}

.net-banner__copy {
    min-width: 0;
}

.net-banner__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    line-height: 1.3;
}

.net-banner__text {
    margin: 0.15rem 0 0;
    font-size: 0.84rem;
    line-height: 1.4;
}

.net-banner-enter-active,
.net-banner-leave-active {
    transition:
        transform 0.22s ease,
        opacity 0.22s ease;
}

.net-banner-enter-from,
.net-banner-leave-to {
    transform: translateY(-100%);
    opacity: 0;
}

.app-dark .net-banner--offline {
    background: #431407;
    border-bottom-color: #c2410c;
    color: #ffedd5;
}

.app-dark .net-banner--online {
    background: #052e16;
    border-bottom-color: #166534;
    color: #dcfce7;
}

.app-dark .net-banner--offline .net-banner__icon {
    color: #fdba74;
}

.app-dark .net-banner--online .net-banner__icon {
    color: #86efac;
}
</style>

<style>
html.is-connection-offline .auth,
html.is-connection-restored .auth {
    min-height: calc(100dvh - var(--connection-banner-offset, 0px));
}

html.is-connection-offline .p-toast-top-right,
html.is-connection-offline .p-toast-top-left,
html.is-connection-offline .p-toast-top-center,
html.is-connection-restored .p-toast-top-right,
html.is-connection-restored .p-toast-top-left,
html.is-connection-restored .p-toast-top-center {
    top: 5.25rem;
}
</style>
