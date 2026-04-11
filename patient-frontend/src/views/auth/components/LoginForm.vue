<script setup>
import { useAuthForm } from '../../../composables/useAuthForm';

const emit = defineEmits(['success']);
const { form, loading, errorMessage, submit } = useAuthForm();

async function onSubmit() {
    const ok = await submit();
    if (ok) {
        emit('success');
    }
}
</script>

<template>
    <Card>
        <template #title>Connexion patient</template>
        <template #content>
            <form class="login-form" @submit.prevent="onSubmit">
                <div class="field-block">
                    <label for="email">Identifiant patient</label>
                    <InputText id="email" v-model="form.email" type="text" placeholder="Nom d'utilisateur" />
                </div>

                <div class="field-block">
                    <label for="password">Mot de passe</label>
                    <Password id="password" v-model="form.password" :feedback="false" toggle-mask fluid />
                </div>

                <small v-if="errorMessage" class="error">{{ errorMessage }}</small>

                <Button type="submit" label="Se connecter" icon="pi pi-sign-in" :loading="loading" fluid />
            </form>
        </template>
    </Card>
</template>

<style scoped>
.login-form {
    display: grid;
    gap: 1rem;
}

.field-block {
    display: grid;
    gap: 0.4rem;
}

.field-block label {
    font-size: 0.85rem;
    color: var(--p-text-muted-color);
}

.error {
    color: var(--p-red-500);
}
</style>
