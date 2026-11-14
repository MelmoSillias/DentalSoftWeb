<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '../store.js';
import { emitAppToast } from '../../../shared/api/toastBus.js';

const auth = useAuthStore();
const form = reactive({ oldPassword: '', newPassword: '', confirmation: '' });
const loading = ref(false);
const error = ref('');
async function submit() {
    error.value = '';
    if (form.newPassword !== form.confirmation) { error.value = 'Les mots de passe ne correspondent pas.'; return; }
    loading.value = true;
    try {
        await auth.changePassword(form.oldPassword, form.newPassword);
        form.oldPassword = form.newPassword = form.confirmation = '';
        emitAppToast({ severity: 'success', summary: 'Mot de passe modifié', detail: 'Veuillez vous reconnecter avec votre nouveau mot de passe.' });
    } catch (failure) { error.value = failure.message || 'Modification impossible.'; }
    finally { loading.value = false; }
}
</script>

<template>
    <form class="profile-section" @submit.prevent="submit">
        <p class="section-title">Changer mon mot de passe</p>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <div class="info-row">
            <label for="current-password">Mot de passe actuel</label>
            <InputText id="current-password" v-model="form.oldPassword" type="password" autocomplete="current-password" required :disabled="loading" />
        </div>
        <div class="info-row">
            <label for="new-password">Nouveau mot de passe</label>
            <InputText id="new-password" v-model="form.newPassword" type="password" autocomplete="new-password" required :disabled="loading" />
        </div>
        <div class="info-row">
            <label for="confirm-password">Confirmer le mot de passe</label>
            <InputText id="confirm-password" v-model="form.confirmation" type="password" autocomplete="new-password" required :disabled="loading" />
        </div>
        <p class="muted">Cette modification déconnecte vos sessions existantes.</p>
        <Button type="submit" label="Modifier le mot de passe" :loading="loading" />
    </form>
</template>
