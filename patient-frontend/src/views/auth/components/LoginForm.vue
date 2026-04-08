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
    <PvCard>
        <template #title>Connexion patient</template>
        <template #content>
            <form class="login-form" @submit.prevent="onSubmit">
                <div class="field-block">
                    <label for="email">Email</label>
                    <PvInputText id="email" v-model="form.email" type="email" placeholder="email@exemple.com" />
                </div>

                <div class="field-block">
                    <label for="password">Mot de passe</label>
                    <PvPassword id="password" v-model="form.password" :feedback="false" toggle-mask fluid />
                </div>

                <small v-if="errorMessage" class="error">{{ errorMessage }}</small>

                <PvButton type="submit" label="Se connecter" icon="pi pi-sign-in" :loading="loading" fluid />
            </form>
        </template>
    </PvCard>
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
