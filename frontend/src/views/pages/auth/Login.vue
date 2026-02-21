<script setup>
import FloatingConfigurator from '@/components/FloatingConfigurator.vue';
import { ref, onMounted } from 'vue';
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
const formVisible = ref(false); // Pour l'animation d'apparition

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
                    errorMessage.value = "Accès refusé. Vous n'êtes pas autorisé à accéder à cette ressource.";
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

// Animation d'apparition au montage
onMounted(() => {
    setTimeout(() => {
        formVisible.value = true;
    }, 100);
});
</script>

<template>
    <FloatingConfigurator />
    <div class="bg-surface-50 dark:bg-surface-950 flex items-center justify-center min-h-screen min-w-[100vw] overflow-hidden p-4 md:p-0">
        <transition name="fade-scale">
            <div v-if="formVisible" class="flex flex-col items-center justify-center w-full max-w-md mx-auto">
                <div class="w-full rounded-3xl shadow-2xl overflow-hidden bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700">
                    <div class="relative overflow-hidden">
                        <!-- Gradient header pour un effet moderne -->
                        <div class="h-24 bg-gradient-to-br from-primary to-primary-500"></div>
                        <!-- Logo positionné au centre du header -->
                        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 flex justify-center items-center bg-surface-0 dark:bg-surface-0 rounded-full p-2 shadow-md">
                            <img src="@/assets/logo.png" alt="Logo Dentalsoft" class="h-16 w-16" />
                        </div>
                    </div>
                    <div class="p-6 md:p-8">
                        <div class="text-center mb-6">
                            <div class="text-surface-900 dark:text-surface-0 text-2xl font-semibold mb-2">Bienvenue sur Dentalsoft! <br> <small class="text-sm text-surface-600 dark:text-surface-400">Centre Medical Massaman</small></div>
                            
                            <span class="text-surface-500 dark:text-surface-400 text-sm">Connectez-vous pour continuer</span>
                        </div>

                        <div>
                            <label for="username1" class="block text-surface-700 dark:text-surface-200 text-sm font-medium mb-2">Nom d'utilisateur</label>
                            <InputText id="username1" type="text" placeholder="Nom d'utilisateur" class="w-full mb-4 transition-all duration-300 ease-in-out focus:shadow-md" v-model="username" />

                            <label for="password1" class="block text-surface-700 dark:text-surface-200 text-sm font-medium mb-2">Mot de passe</label>
                            <Password id="password1" v-model="password" placeholder="Mot de passe" :toggleMask="true" class="mb-4 w-full" fluid :feedback="false"></Password>

                            <!-- Message d'erreur avec animation -->
                            <transition name="fade">
                                <div v-if="errorMessage" class="text-red-500 mb-4 text-sm bg-red-100 dark:bg-red-900 p-3 rounded-md">{{ errorMessage }}</div>
                            </transition>

                            <div class="flex flex-col sm:flex-row items-center justify-between mb-6 gap-4">
                                <div class="flex items-center">
                                    <Checkbox v-model="checked" id="rememberme1" binary class="mr-2"></Checkbox>
                                    <label for="rememberme1" class="text-sm">Se souvenir de moi</label>
                                </div>
                                <span class="font-medium text-sm cursor-pointer text-primary hover:underline">Mot de passe oublié ?</span>
                            </div>

                            <!-- Bouton avec effet hover et spinner -->
                            <Button label="Se connecter" class="w-full flex items-center justify-center transition-all duration-300 ease-in-out hover:shadow-lg hover:scale-105" @click="onSubmit" :disabled="loading">
                                <template v-if="loading">
                                    <span class="my-spinner-container">
                                        <ProgressSpinner class="my-spinner" />
                                    </span>
                                    <span class="ml-3">Connexion...</span>
                                </template>
                                <template v-else> Se connecter </template>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
/* Transitions et animations */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

.fade-scale-enter-active {
    transition: all 0.5s ease;
}
.fade-scale-enter-from {
    opacity: 0;
    transform: scale(0.9) translateY(20px);
}
.fade-scale-enter-to {
    opacity: 1;
    transform: scale(1) translateY(0);
}

/* Styles pour le spinner */
.my-spinner-container {
    display: flex;
    justify-content: center;
    align-items: center;
}

.my-spinner {
    width: 20px !important;
    height: 20px !important;
    stroke: #ffffff !important; /* Couleur blanche pour contraster avec le bouton */
    animation: spin 1s linear infinite !important;
}

/* Animation rotation fluide */
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* Améliorations responsives */
@media (max-width: 640px) {
    .p-6 {
        padding: 1.5rem;
    }
}
</style>