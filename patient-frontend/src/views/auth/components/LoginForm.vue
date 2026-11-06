<script setup>
import { useAuthForm } from '../../../composables/useAuthForm';

const emit = defineEmits(['success']);
const props = defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
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
        <form class="login-form" @submit.prevent="onSubmit">
            <div class="field-block">
                <label for="email" class="field-label">
                    <i class="pi pi-user" />
                    Identifiant patient
                </label>
                <InputText
                    id="email"
                    autocomplete="username"
                    required
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
                    input-id="password"
                    autocomplete="current-password"
                    required
                    placeholder="Votre mot de passe"
                    v-model="form.password"
                    :feedback="false"
                    toggle-mask
                    fluid
                    :disabled="props.disabled"
                />
            </div>

            <Message
                v-if="errorMessage"
                severity="error"
                :closable="false"
                size="small"
                >{{ errorMessage }}</Message
            >

            <Button
                type="submit"
                label="Accéder à mon espace"
                icon="pi pi-arrow-right"
                icon-pos="right"
                :loading="loading"
                :disabled="props.disabled"
                fluid
                class="submit-btn"
            />
        </form>
    </div>
</template>
