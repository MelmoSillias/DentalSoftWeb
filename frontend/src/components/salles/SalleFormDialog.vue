<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

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
    <Dialog :visible="visible" modal :header="mode === 'edit' ? 'Modifier la salle' : 'Ajouter une salle'" :style="{ width: '30rem' }" @update:visible="close">
        <form @submit.prevent="onSubmit" class="flex flex-col gap-4">
            <div>
                <label class="block mb-1">Nom <span class="text-red-500">*</span></label>
                <InputText v-model="form.nom" required class="w-full" />
            </div>
            <div>
                <label class="block mb-1">Description</label>
                <Textarea v-model="form.description" autoResize rows="2" class="w-full" />
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <Button type="button" label="Annuler" severity="secondary" @click="close" />
                <Button type="submit" :label="mode === 'edit' ? 'Mettre à jour' : 'Ajouter'" :loading="loading" :severity="mode === 'edit' ? 'success' : 'primary'" />
            </div>
        </form>
    </Dialog>
</template>
