<script setup>
import { computed, reactive, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    visible: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    mode: { type: Object, default: null }
});

const emit = defineEmits(['update:visible', 'submit']);

const defaultForm = () => ({
    libelle: '',
    type: 'Especes',
    notes: ''
});

const form = reactive(defaultForm());

const typeOptions = [
    { label: 'Especes', value: 'Especes' },
    { label: 'Mobile Money', value: 'Mobile Money' },
    { label: 'Assurance', value: 'Assurance' },
    { label: 'Cheque', value: 'Cheque' },
    { label: 'Virement', value: 'Virement' },
    { label: 'Autre', value: 'Autre' }
];

const isEdit = computed(() => Boolean(props.mode?.id));

const syncForm = () => {
    const source = props.mode || {};
    form.libelle = source?.libelle || '';
    form.type = source?.type || 'Especes';
    form.notes = source?.notes || '';
};

watch(
    () => props.visible,
    (value) => {
        if (value) {
            syncForm();
        }
    }
);

watch(
    () => props.mode,
    () => {
        if (props.visible) {
            syncForm();
        }
    }
);

const close = () => emit('update:visible', false);

const submitForm = (event) => {
    const payload = {
        libelle: form.libelle,
        type: form.type,
        notes: form.notes
    };
    emit('submit', { payload, event });
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :style="{ width: '560px' }"
        :header="isEdit ? 'Modifier un mode de paiement' : 'Ajouter un mode de paiement'"
        @update:visible="close">
        <div class="grid gap-4">
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Libelle</label>
                <InputText v-model="form.libelle" class="w-full" placeholder="Ex: Orange Money, Banque" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Type</label>
                <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" />
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Notes</label>
                <Textarea v-model="form.notes" rows="3" autoResize class="w-full" placeholder="Informations internes" />
            </div>
        </div>

        <template #footer>
            <Button label="Annuler" text @click="close" />
            <Button :label="isEdit ? 'Mettre a jour' : 'Enregistrer'" icon="pi pi-check" :loading="loading" @click="submitForm" />
        </template>
    </Dialog>
</template>
