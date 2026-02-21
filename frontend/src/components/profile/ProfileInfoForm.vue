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
    <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Identifiant</label>
            <InputText v-model="form.username" placeholder="Identifiant" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Fonction</label>
            <InputText v-model="form.fonction" placeholder="Fonction" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Nom</label>
            <InputText v-model="form.nom" placeholder="Nom" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Prénom</label>
            <InputText v-model="form.prenom" placeholder="Prénom" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Email</label>
            <InputText v-model="form.email" placeholder="Email" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Téléphone</label>
            <InputText v-model="form.telephone" placeholder="Téléphone" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Matricule</label>
            <InputText v-model="form.matricule" placeholder="Matricule" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Type</label>
            <InputText v-model="form.type" placeholder="Type" />
        </div>
        <div class="flex flex-col gap-2">
            <label class="text-sm font-medium">Type de contrat</label>
            <InputText v-model="form.typeContrat" placeholder="Type de contrat" />
        </div>
        <div class="md:col-span-2 flex justify-end">
            <Button type="button" label="Enregistrer" icon="pi pi-save" :loading="loading" :disabled="!canSubmit" @click="submit" />
        </div>
    </form>
    <ConfirmPopup />
</template>
