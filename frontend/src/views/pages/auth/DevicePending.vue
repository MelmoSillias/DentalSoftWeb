<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';
import cabinetConfig from '@/cabinetConfig';

const authStore = useAuthStore();
const router = useRouter();

const checking = ref(false);
const status = ref('pending');
const message = ref('Cet appareil n\'est pas encore autorise. Veuillez attendre qu\'un administrateur valide votre demande.');

const syncFromResponse = (data) => {
    status.value = data?.status || 'pending';
    if (data?.message) {
        message.value = data.message;
    }
};

const checkAuthorization = async () => {
    checking.value = true;
    try {
        const data = await authStore.checkDeviceStatus();
        syncFromResponse(data);

        if (data?.allowed) {
            await authStore.fetchUser();
            authStore.clearDeviceBlock();
            router.replace({ name: 'dashboard' });
            return;
        }

        if (data?.status === 'rejected') {
            message.value = data.message || 'Cet appareil a ete refuse. Contactez un administrateur.';
        }
    } catch (error) {
        const payload = error?.response?.data;
        if (payload) {
            syncFromResponse(payload);
        }
    } finally {
        checking.value = false;
    }
};

const logout = () => {
    authStore.logout();
    authStore.clearDeviceBlock();
    router.replace({ name: 'login' });
};

onMounted(() => {
    if (!authStore.token) {
        router.replace({ name: 'login' });
        return;
    }

    if (authStore.deviceBlockMessage) {
        message.value = authStore.deviceBlockMessage;
    }
    if (authStore.deviceBlockStatus) {
        status.value = authStore.deviceBlockStatus;
    }
});
</script>

<template>
    <main class="auth">
        <div class="auth_container auth_container--single">
            <section class="auth_form_container">
                <FloatingConfigurator container-class="auth_tools" />

                <div class="auth_brand">
                    <img src="/logo.png" :alt="`Logo ${cabinetConfig.brandName}`" />
                </div>

                <div class="device-pending-icon" :class="{ 'device-pending-icon--rejected': status === 'rejected' }">
                    <i :class="status === 'rejected' ? 'pi pi-times' : 'pi pi-desktop'"></i>
                </div>

                <h1>Appareil non autorise</h1>
                <p class="device-pending-message">{{ message }}</p>

                <p v-if="status === 'pending'" class="device-pending-hint">
                    Un administrateur doit approuver cet appareil depuis les parametres du cabinet.
                    Cliquez sur le bouton ci-dessous pour verifier si l'autorisation a ete accordee.
                </p>

                <div class="device-pending-actions">
                    <Button
                        v-if="status !== 'rejected'"
                        class="auth_submit_button"
                        :disabled="checking"
                        @click="checkAuthorization"
                    >
                        <template v-if="checking">
                            <span class="my-spinner-container">
                                <ProgressSpinner class="my-spinner" />
                            </span>
                            <span>Verification...</span>
                        </template>
                        <template v-else>Verifier l'autorisation</template>
                    </Button>

                    <Button
                        class="auth_secondary_button"
                        severity="secondary"
                        outlined
                        :disabled="checking"
                        @click="logout"
                    >
                        Se deconnecter
                    </Button>
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
:global(:root) {
    --auth-bg: #f4f7fb;
    --auth-card-bg: #ffffff;
    --auth-text: #16253c;
    --auth-subtext: #63738a;
    --auth-accent: #2c7be5;
    --auth-accent-dark: #1f5fb7;
    --auth-border: #d7dfec;
}

:global(.app-dark) {
    --auth-bg: #08111f;
    --auth-card-bg: #0f1c2d;
    --auth-text: #edf4ff;
    --auth-subtext: #9dafc8;
    --auth-accent: #6fb1ff;
    --auth-accent-dark: #9ac9ff;
    --auth-border: #24354d;
}

.auth {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 1.5rem;
    background:
        radial-gradient(circle at 8% 15%, #dbe9ff 0, transparent 34%),
        radial-gradient(circle at 92% 80%, #d8fff0 0, transparent 30%),
        var(--auth-bg);
}

.auth_container {
    width: min(520px, 100%);
    background: var(--auth-card-bg);
    border-radius: 1.25rem;
    overflow: hidden;
    border: 1px solid var(--auth-border);
    box-shadow: 0 28px 68px rgba(15, 30, 51, 0.18);
}

.auth_form_container {
    position: relative;
    padding: 2.4rem 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.auth_tools {
    position: absolute;
    top: 1.25rem;
    right: 1.25rem;
    display: flex;
    gap: 0.75rem;
    z-index: 5;
}

.auth_brand {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 0.9rem;
    background: #f2f7ff;
    margin-bottom: 1rem;
}

.auth_brand img {
    width: 36px;
    height: 36px;
}

.device-pending-icon {
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff4e5;
    color: #e67e22;
    font-size: 1.6rem;
    margin-bottom: 1rem;
}

.device-pending-icon--rejected {
    background: #ffe7e7;
    color: #c0392b;
}

h1 {
    margin: 0 0 0.75rem;
    color: var(--auth-text);
    font-size: 1.5rem;
    font-weight: 700;
}

.device-pending-message {
    color: var(--auth-subtext);
    font-size: 0.95rem;
    line-height: 1.5;
    margin: 0 0 0.75rem;
}

.device-pending-hint {
    color: var(--auth-subtext);
    font-size: 0.88rem;
    line-height: 1.45;
    margin: 0 0 1.5rem;
    opacity: 0.9;
}

.device-pending-actions {
    width: 100%;
    display: grid;
    gap: 0.75rem;
}

.auth_submit_button {
    width: 100%;
    border: none;
    border-radius: 0.82rem;
    background: linear-gradient(130deg, var(--auth-accent), #3ea0f8);
    color: #fff;
    font-weight: 700;
    padding: 0.9rem 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
}

.auth_secondary_button {
    width: 100%;
    border-radius: 0.82rem;
    font-weight: 600;
}

.my-spinner-container {
    display: flex;
    justify-content: center;
    align-items: center;
}

.my-spinner {
    width: 20px !important;
    height: 20px !important;
    stroke: #ffffff !important;
    animation: spin 1s linear infinite !important;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

:global(.app-dark) .auth_brand {
    background: #13253b;
}
</style>
