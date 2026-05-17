<script setup>
import { useAuthForm } from '../../../composables/useAuthForm';

const emit = defineEmits(['success']);
const props = defineProps({
    disabled: {
        type: Boolean,
        default: false
    }
});
const { form, loading, errorMessage, submit } = useAuthForm();

async function onSubmit() {
    if (props.disabled) {
        return;
    }

    const ok = await submit();
    if (ok) {
        emit('success');
    }
}
</script>

<template>
    <div class="login-form-card">
        <p class="form-heading">Connexion</p>
        <form class="login-form" @submit.prevent="onSubmit">
            <div class="field-block">
                <label for="email" class="field-label">
                    <i class="pi pi-user" />
                    Identifiant patient
                </label>
                <InputText
                    id="email"
                    v-model="form.email"
                    type="text"
                    placeholder="Nom d'utilisateur"
                    :disabled="props.disabled"
                    class="w-full"
                />
            </div>

            <div class="field-block">
                <label for="password" class="field-label">
                    <i class="pi pi-lock" />
                    Mot de passe
                </label>
                <Password
                    id="password"
                    v-model="form.password"
                    :feedback="false"
                    toggle-mask
                    fluid
                    :disabled="props.disabled"
                />
            </div>

            <Message v-if="errorMessage" severity="error" :closable="false" size="small">{{ errorMessage }}</Message>

            <Button
                type="submit"
                label="Se connecter"
                icon="pi pi-sign-in"
                :loading="loading"
                :disabled="props.disabled"
                fluid
                class="submit-btn"
            />
        </form>
    </div>
</template>

<style scoped>
.login-form-card {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    padding: 1.5rem;
}

.form-heading {
    margin: 0 0 1.25rem;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--p-text-color);
}

.login-form {
    display: grid;
    gap: 1rem;
}

.field-block {
    display: grid;
    gap: 0.45rem;
}

.field-label {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--p-text-muted-color);
}

.field-label i {
    font-size: 0.78rem;
}

.submit-btn {
    margin-top: 0.25rem;
}
</style>
