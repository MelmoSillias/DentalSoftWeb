<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { ref } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Checkbox from 'primevue/checkbox';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';

const authStore = useAuthStore();
const router = useRouter();

const username = ref('');
const password = ref('');
const checked = ref(false);
const errorMessage = ref('');
const loading = ref(false);

const onSubmit = async () => {
    errorMessage.value = '';
    loading.value = true;
    try {
        await authStore.login(username.value, password.value);
        router.push({ name: 'dashboard' });
    } catch (e) {
        // Gestion des erreurs en français
        if (e.response) {
            const status = e.response.status;
            switch (status) {
                case 400:
                    errorMessage.value = 'Requête invalide. Veuillez vérifier les informations saisies.';
                    break;
                case 401:
                    errorMessage.value = "Nom d'utilisateur ou mot de passe incorrect.";
                    break;
                case 403:
                    errorMessage.value = e.response?.data?.message || "Accès refusé. Vous n'êtes pas autorisé à accéder à cette ressource.";
                    break;
                case 404:
                    errorMessage.value = 'Serveur non trouvé. Veuillez réessayer plus tard.';
                    break;
                case 500:
                    errorMessage.value = 'Erreur interne du serveur. Veuillez réessayer plus tard.';
                    break;
                default:
                    errorMessage.value = e.response.data?.message || 'Une erreur est survenue. Veuillez réessayer.';
            }
        } else {
            errorMessage.value = 'Impossible de se connecter au serveur. Vérifiez votre connexion internet.';
        }
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <main class="auth">
        <div class="auth_container">
            <section class="auth_illustration">
                <div class="auth_illustration_content">
                    <img src="@/assets/logo.png" alt="Logo Dentalsoft" class="auth_illustration_logo" />
                    <h2>Dentalsoft - Orodent</h2>
                    <p>Votre plateforme de gestion pour cabinet dentaire.</p>
                </div>
            </section>

            <section class="auth_form_container">
                <FloatingConfigurator container-class="auth_tools" />

                <div class="auth_brand">
                    <img src="@/assets/logo.png" alt="Logo Dentalsoft" />
                </div>

                <h1>Connexion</h1>

                <form class="auth_form" @submit.prevent="onSubmit">
                    <div class="field">
                        <label for="username">Nom d'utilisateur</label>
                        <InputText
                            id="username"
                            v-model="username"
                            type="text"
                            placeholder="Nom d'utilisateur"
                            class="auth-text-input"
                        />
                    </div>

                    <div class="field">
                        <label for="password">Mot de passe</label>
                        <Password
                            id="password"
                            v-model="password"
                            placeholder="Mot de passe"
                            :toggleMask="true"
                            :feedback="false" 
                            fluid
                        />
                    </div>

                    <transition name="fade">
                        <div v-if="errorMessage" class="auth_error">{{ errorMessage }}</div>
                    </transition>

                    <div class="auth_meta">
                        <div class="remember_group">
                            <Checkbox v-model="checked" id="stayconnected" binary />
                            <label for="stayconnected">Rester connecté</label>
                        </div>
                        <a href="#" class="forgot_password">Mot de passe oublié</a>
                    </div>

                    <Button type="submit" class="auth_submit_button" :disabled="loading">
                        <template v-if="loading">
                            <span class="my-spinner-container">
                                <ProgressSpinner class="my-spinner" />
                            </span>
                            <span>Connexion...</span>
                        </template>
                        <template v-else>Se connecter</template>
                    </Button>
                </form>
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
    --auth-error-bg: #ffe7e7;
    --auth-error-text: #9f1d1d;
}

:global(.app-dark) {
    --auth-bg: #08111f;
    --auth-card-bg: #0f1c2d;
    --auth-text: #edf4ff;
    --auth-subtext: #9dafc8;
    --auth-accent: #6fb1ff;
    --auth-accent-dark: #9ac9ff;
    --auth-border: #24354d;
    --auth-error-bg: rgba(148, 35, 35, 0.28);
    --auth-error-text: #ffc8c8;
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
    width: min(980px, 100%);
    min-height: 620px;
    background: var(--auth-card-bg);
    border-radius: 1.25rem;
    overflow: hidden;
    border: 1px solid var(--auth-border);
    box-shadow: 0 28px 68px rgba(15, 30, 51, 0.18);
    display: grid;
    grid-template-columns: 1.05fr 1fr;
}

.auth_illustration {
    position: relative;
    padding: 2rem; 
    background-image: url("@/assets/illustration.png");
    background-repeat: no-repeat;
    background-position: center;
    background-size: cover;
    color: #ffffff;
    display: flex;
    align-items: flex-end;
}

.auth_illustration::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.15), rgba(0, 0, 0, 0.45));
}

.auth_illustration_content {
    position: relative;
    z-index: 1;
}

.auth_illustration_logo {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #ffffff;
    padding: 0.5rem;
    margin-bottom: 1rem;
}

.auth_illustration h2 {
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    margin: 0 0 0.5rem 0;
}

.auth_illustration p {
    font-size: 0.98rem;
    opacity: 0.9;
    max-width: 28ch;
    margin: 0;
}

.auth_form_container {
    position: relative;
    padding: 2.4rem 2rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
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

h1 {
    margin: 0;
    color: var(--auth-text);
    font-size: 1.65rem;
    font-weight: 700;
    margin-bottom: 1.4rem;
}

.auth_form {
    display: grid;
    gap: 1rem;
}

.field {
    display: grid;
    gap: 0.5rem;
}

.field label {
    font-size: 0.9rem;
    color: var(--auth-subtext);
    font-weight: 600;
}

:deep(.auth-text-input input),
:deep(.auth-text-input .p-inputtext),
:deep(.auth-text-input .p-password-input) {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid var(--auth-border);
    padding: 0.75rem 0.95rem;
    background: #fff;
}

:deep(.auth-text-input .p-password),
:deep(.auth-text-input.p-password) {
    width: 100%;
}

.auth_error {
    background: var(--auth-error-bg);
    color: var(--auth-error-text);
    border: 1px solid color-mix(in srgb, var(--auth-error-text), transparent 70%);
    border-radius: 0.7rem;
    font-size: 0.9rem;
    padding: 0.7rem 0.85rem;
}

.auth_meta {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

.remember_group {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    color: var(--auth-subtext);
    font-size: 0.9rem;
}

.forgot_password {
    color: var(--auth-accent);
    font-size: 0.9rem;
    text-decoration: none;
    font-weight: 600;
}

.forgot_password:hover {
    color: var(--auth-accent-dark);
    text-decoration: underline;
}

.auth_submit_button {
    margin-top: 0.35rem;
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
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.auth_submit_button:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(44, 123, 229, 0.28);
}

:global(.app-dark) .auth {
    background:
        radial-gradient(circle at 12% 18%, rgba(45, 93, 168, 0.28) 0, transparent 30%),
        radial-gradient(circle at 88% 80%, rgba(20, 130, 106, 0.2) 0, transparent 32%),
        var(--auth-bg);
}

:global(.app-dark) .auth_illustration::before {
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.28), rgba(0, 0, 0, 0.62));
}

:global(.app-dark) .auth_brand {
    background: #13253b;
}

:global(.app-dark) .auth_illustration_logo {
    background: #ffffff;
}

:deep(.auth-text-input input:hover),
:deep(.auth-text-input .p-inputtext:hover),
:deep(.auth-text-input .p-password-input:hover) {
    border-color: color-mix(in srgb, var(--auth-accent), white 50%);
}

:deep(.auth-text-input input:focus),
:deep(.auth-text-input .p-inputtext:focus),
:deep(.auth-text-input .p-password-input:focus) {
    border-color: var(--auth-accent);
    box-shadow: 0 0 0 0.18rem color-mix(in srgb, var(--auth-accent), transparent 78%);
}

:global(.app-dark) :deep(.auth-text-input input),
:global(.app-dark) :deep(.auth-text-input .p-inputtext),
:global(.app-dark) :deep(.auth-text-input .p-password-input) {
    background: #0b1728;
    border-color: var(--auth-border);
    color: var(--auth-text);
}

:global(.app-dark) :deep(.auth-text-input input::placeholder),
:global(.app-dark) :deep(.auth-text-input .p-inputtext::placeholder),
:global(.app-dark) :deep(.auth-text-input .p-password-input::placeholder) {
    color: #7f92ad;
}

:global(.app-dark) .remember_group,
:global(.app-dark) .field label {
    color: var(--auth-subtext);
}

:global(.app-dark) .auth_submit_button:hover {
    box-shadow: 0 10px 20px rgba(111, 177, 255, 0.22);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
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
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@media (max-width: 900px) {
    .auth_container {
        grid-template-columns: 1fr;
        min-height: auto;
    }

    .auth_illustration {
        min-height: 220px;
        align-items: flex-start;
    }

    .auth_form_container {
        padding: 4.8rem 1.6rem 1.6rem;
    }
}

@media (max-width: 560px) {
    .auth {
        padding: 0.8rem;
    }

    .auth_container {
        border-radius: 1rem;
    }

    h1 {
        font-size: 1.45rem;
    }

    .auth_tools {
        top: 1rem;
        right: 1rem;
        gap: 0.5rem;
    }
}
</style>