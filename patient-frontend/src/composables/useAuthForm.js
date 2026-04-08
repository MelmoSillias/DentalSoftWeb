import { reactive, ref } from 'vue';
import { useAuthStore } from '../stores/auth';

export function useAuthForm() {
    const authStore = useAuthStore();

    const form = reactive({
        email: '',
        password: ''
    });

    const loading = ref(false);
    const errorMessage = ref('');

    async function submit() {
        loading.value = true;
        errorMessage.value = '';

        try {
            await authStore.login(form);
            return true;
        } catch (error) {
            errorMessage.value = error instanceof Error ? error.message : 'Connexion impossible';
            return false;
        } finally {
            loading.value = false;
        }
    }

    return {
        form,
        loading,
        errorMessage,
        submit
    };
}
