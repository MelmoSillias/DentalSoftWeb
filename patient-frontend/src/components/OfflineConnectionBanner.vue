<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useNetworkStatus } from '../composables/useNetworkStatus';

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

    return 'Connexion au cabinet indisponible';
});

const message = computed(() => {
    if (!isOffline.value) {
        return 'Vous êtes de nouveau en ligne.';
    }

    if (offlineCause.value === 'browser') {
        return "Votre appareil n'a plus accès au réseau. Ce n'est pas une erreur de l'application : les actions peuvent échouer tant que la connexion n'est pas rétablie.";
    }

    return 'Le serveur du cabinet est momentanément injoignable. Réessayez dans quelques instants.';
});

function syncOffset() {
    if (!bannerRef.value) {
        return;
    }

    document.documentElement.style.setProperty(
        '--connection-banner-offset',
        `${bannerRef.value.offsetHeight}px`,
    );
}

function clearOffset() {
    document.documentElement.style.setProperty(
        '--connection-banner-offset',
        '0px',
    );
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
        <div
            v-if="visible"
            ref="bannerRef"
            class="net-banner"
            :class="isOffline ? 'net-banner--offline' : 'net-banner--online'"
            :role="isOffline ? 'alert' : 'status'"
            aria-live="assertive"
        >
            <i
                :class="isOffline ? 'pi pi-wifi' : 'pi pi-check-circle'"
                class="net-banner__icon"
                aria-hidden="true"
            />
            <div class="net-banner__copy">
                <p class="net-banner__title">{{ title }}</p>
                <p class="net-banner__text">{{ message }}</p>
            </div>
        </div>
    </Transition>
</template>
