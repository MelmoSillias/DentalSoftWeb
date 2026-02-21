<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ConfirmPopup from 'primevue/confirmpopup';
import { reactive, computed } from 'vue';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['save']);
const confirm = useConfirm();

const form = reactive({
    oldPassword: '',
    newPassword: '',
    confirmPassword: ''
});

const canSubmit = computed(() =>
    form.oldPassword.length >= 3 && form.newPassword.length >= 8 && form.newPassword === form.confirmPassword
);

const submit = (event) => {
    if (!canSubmit.value) return;
    confirm.require({
        target: event.currentTarget,
        message: 'Confirmer la modification du mot de passe ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Mettre à jour',
        rejectLabel: 'Annuler',
        accept: () => emit('save', { ...form })
    });
};
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Mot de passe actuel</label>
            <InputText v-model="form.oldPassword" type="password" placeholder="Mot de passe actuel" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Nouveau mot de passe</label>
            <InputText v-model="form.newPassword" type="password" placeholder="Nouveau mot de passe" />
            <small class="text-xs text-surface-500">Minimum 8 caractères.</small>
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Confirmer le mot de passe</label>
            <InputText v-model="form.confirmPassword" type="password" placeholder="Confirmer le mot de passe" />
        </div>
        <div class="flex justify-end">
            <Button type="button" label="Mettre à jour" icon="pi pi-lock" :loading="loading" :disabled="!canSubmit" @click="submit" />
        </div>
    </form>
    <ConfirmPopup />
</template>
