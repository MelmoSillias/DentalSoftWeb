<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import ConfirmPopup from 'primevue/confirmpopup';
import { computed, reactive } from 'vue';
import { useConfirm } from 'primevue/useconfirm';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    employee: { type: Object, default: () => null },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['save']);
const confirm = useConfirm();

const form = reactive({
    username: props.user?.username || '',
    nom: props.employee?.nom || '',
    prenom: props.employee?.prenom || '',
    email: props.employee?.email || '',
    telephone: props.employee?.telephone || '',
    fonction: props.employee?.fonction || '',
    matricule: props.employee?.matricule || '',
    type: props.employee?.type || '',
    typeContrat: props.employee?.typeContrat || ''
});

const canSubmit = computed(() => form.username?.trim().length > 0);

const submit = (event) => {
    if (!canSubmit.value) return;
    confirm.require({
        target: event.currentTarget,
        message: 'Confirmer la mise à jour du profil ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Mettre à jour',
        rejectLabel: 'Annuler',
        accept: () => emit('save', { ...form })
    });
};
</script>

<template>
    <form class="space-y-5" @submit.prevent>
        <!-- Section identifiant -->
        <div class="rounded-xl border border-surface-100 dark:border-surface-700/50 bg-surface-50/60 dark:bg-surface-700/20 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-surface-400 mb-3">Compte</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-at text-[11px] text-surface-400"></i>
                        Identifiant
                    </label>
                    <InputText v-model="form.username" placeholder="Identifiant" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-briefcase text-[11px] text-surface-400"></i>
                        Fonction
                    </label>
                    <InputText v-model="form.fonction" placeholder="Fonction" />
                </div>
            </div>
        </div>

        <!-- Section infos personnelles -->
        <div class="rounded-xl border border-surface-100 dark:border-surface-700/50 bg-surface-50/60 dark:bg-surface-700/20 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-surface-400 mb-3">Informations personnelles</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-user text-[11px] text-surface-400"></i>
                        Nom
                    </label>
                    <InputText v-model="form.nom" placeholder="Nom" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-user text-[11px] text-surface-400"></i>
                        Prénom
                    </label>
                    <InputText v-model="form.prenom" placeholder="Prénom" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-envelope text-[11px] text-surface-400"></i>
                        Email
                    </label>
                    <InputText v-model="form.email" placeholder="Email" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-phone text-[11px] text-surface-400"></i>
                        Téléphone
                    </label>
                    <InputText v-model="form.telephone" placeholder="Téléphone" />
                </div>
            </div>
        </div>

        <!-- Section emploi -->
        <div class="rounded-xl border border-surface-100 dark:border-surface-700/50 bg-surface-50/60 dark:bg-surface-700/20 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-surface-400 mb-3">Emploi</p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-id-card text-[11px] text-surface-400"></i>
                        Matricule
                    </label>
                    <InputText v-model="form.matricule" placeholder="Matricule" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-users text-[11px] text-surface-400"></i>
                        Type
                    </label>
                    <InputText v-model="form.type" placeholder="Type" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400 flex items-center gap-1.5">
                        <i class="pi pi-file text-[11px] text-surface-400"></i>
                        Type de contrat
                    </label>
                    <InputText v-model="form.typeContrat" placeholder="Type de contrat" />
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-1">
            <Button type="button" label="Enregistrer" icon="pi pi-save" :loading="loading" :disabled="!canSubmit" @click="submit" />
        </div>
    </form>
    <ConfirmPopup />
</template>
