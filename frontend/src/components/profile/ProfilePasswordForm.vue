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
    form.oldPassword.length >= 3 && form.newPassword.length >= 4 && form.newPassword === form.confirmPassword
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
        <!-- Avertissement sécurité -->
        <div class="flex items-start gap-3 rounded-xl border border-amber-200/60 bg-amber-50/40 dark:border-amber-800/40 dark:bg-amber-950/10 px-4 py-3">
            <i class="pi pi-shield text-amber-500 mt-0.5"></i>
            <div class="text-xs text-amber-700 dark:text-amber-400">
                <p class="font-medium">Sécurité renforcée</p>
                <p class="mt-0.5 text-amber-600/80 dark:text-amber-400/70">Utilisez au moins 8 caractères avec majuscules, chiffres et symboles.</p>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                <i class="pi pi-lock text-[11px] text-surface-400"></i>
                Mot de passe actuel
            </label>
            <InputText v-model="form.oldPassword" type="password" placeholder="Mot de passe actuel" />
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                <i class="pi pi-lock text-[11px] text-surface-400"></i>
                Nouveau mot de passe
            </label>
            <InputText v-model="form.newPassword" type="password" placeholder="Nouveau mot de passe" />
            <div class="flex items-center gap-2">
                <div class="h-1 flex-1 rounded-full" :class="form.newPassword.length === 0 ? 'bg-surface-200 dark:bg-surface-700' : form.newPassword.length < 6 ? 'bg-red-400' : form.newPassword.length < 10 ? 'bg-amber-400' : 'bg-emerald-500'"></div>
                <span class="text-xs" :class="form.newPassword.length === 0 ? 'text-surface-400' : form.newPassword.length < 6 ? 'text-red-500' : form.newPassword.length < 10 ? 'text-amber-500' : 'text-emerald-600'">
                    {{ form.newPassword.length === 0 ? 'min. 8 car.' : form.newPassword.length < 6 ? 'Trop court' : form.newPassword.length < 10 ? 'Moyen' : 'Fort' }}
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                <i class="pi pi-lock text-[11px] text-surface-400"></i>
                Confirmer le mot de passe
            </label>
            <InputText v-model="form.confirmPassword" type="password" placeholder="Confirmer le mot de passe" />
            <small v-if="form.confirmPassword && form.newPassword !== form.confirmPassword" class="text-xs text-red-500 flex items-center gap-1">
                <i class="pi pi-times-circle text-[11px]"></i>
                Les mots de passe ne correspondent pas
            </small>
            <small v-else-if="form.confirmPassword && form.newPassword === form.confirmPassword" class="text-xs text-emerald-600 flex items-center gap-1">
                <i class="pi pi-check-circle text-[11px]"></i>
                Mots de passe identiques
            </small>
        </div>

        <div class="flex justify-end pt-1">
            <Button type="button" label="Mettre à jour" icon="pi pi-lock" :loading="loading" :disabled="!canSubmit" @click="submit" />
        </div>
    </form>
</template>
