<script setup>
import { ref, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';

const props = defineProps({
    visible: Boolean,
    salle: { type: Object, default: () => ({}) },
    mode: { type: String, default: 'add' }, // 'add' ou 'edit'
    loading: Boolean
});
const emit = defineEmits(['update:visible', 'submit']);

const form = ref({
    nom: '',
    description: ''
});

watch(
    () => props.salle,
    (val) => {
        form.value = {
            nom: val?.nom || '',
            description: val?.description || ''
        };
    },
    { immediate: true }
);

function close() {
    emit('update:visible', false);
}

function onSubmit() {
    emit('submit', { ...form.value });
}
</script>
<template>
    <AppDialog
        :visible="visible"
        :title="mode === 'edit' ? 'Modifier la salle' : 'Ajouter une salle'"
        icon="pi pi-building"
        :icon-tone="mode === 'edit' ? 'success' : 'primary'"
        size="sm"
        :loading="loading"
        cancel-label="Annuler"
        :confirm-label="mode === 'edit' ? 'Mettre à jour' : 'Ajouter'"
        :confirm-severity="mode === 'edit' ? 'success' : 'primary'"
        @update:visible="close"
        @cancel="close"
        @confirm="onSubmit"
    >
        <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
            <div>
                <label class="block mb-1">Nom <span class="text-red-500">*</span></label>
                <InputText v-model="form.nom" required class="w-full" />
            </div>
            <div>
                <label class="block mb-1">Description</label>
                <Textarea v-model="form.description" autoResize rows="2" class="w-full" />
            </div>
        </form>
    </AppDialog>
</template>
