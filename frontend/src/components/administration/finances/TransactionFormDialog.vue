<script setup>
import { computed, reactive, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    visible: { type: Boolean, default: false },
    paymentMethods: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    transaction: { type: Object, default: null },
    mode: { type: String, default: 'create' }
});

const emit = defineEmits(['update:visible', 'submit']);

const defaultForm = () => ({
    type: 'entry',
    montant: null,
    description: '',
    date: new Date(),
    modeId: null
});

const form = reactive(defaultForm());

const typeOptions = [
    { label: 'Entree', value: 'entry' },
    { label: 'Sortie', value: 'exit' }
];

const methodOptions = computed(() =>
    (props.paymentMethods || [])
        .filter((m) => m?.actif !== false)
        .map((m) => ({
            label: m.type ? `${m.libelle} (${m.type})` : m.libelle,
            value: m.id
        }))
);

const toDate = (value) => {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return date;
};

const toDateString = (value) => {
    const date = toDate(value);
    if (!date) return '';
    return date.toISOString().slice(0, 10);
};

const syncForm = () => {
    const source = props.transaction || {};
    form.type = source?.type === 'Sortie' || source?.type === 'exit' ? 'exit' : source?.type === 'Entrée' ? 'entry' : 'entry';
    form.montant = source?.montant ?? source?.amount ?? null;
    form.description = source?.description || '';
    form.date = toDate(source?.date || source?.dateTransaction || new Date());
    form.modeId = source?.modeId || source?.modeDePaiement?.id || null;
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
    () => props.transaction,
    () => {
        if (props.visible) {
            syncForm();
        }
    }
);

const close = () => emit('update:visible', false);

const submitForm = (event) => {
    const payload = {
        type: form.type,
        montant: form.montant,
        description: form.description,
        date: toDateString(form.date),
        modeId: form.modeId
    };
    emit('submit', { payload, event });
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :style="{ width: '640px' }"
        :header="mode === 'edit' ? 'Modifier une transaction' : 'Nouvelle transaction'"
        @update:visible="close">
        <div class="grid gap-4">
            <div class="grid md:grid-cols-2 gap-3">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Type</label>
                    <Select v-model="form.type" :options="typeOptions" optionLabel="label" optionValue="value" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Compte</label>
                    <Select
                        v-model="form.modeId"
                        :options="methodOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Selectionner"
                        :loading="loading" />
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Montant</label>
                    <InputNumber v-model="form.montant" mode="decimal" locale="fr-FR" :minFractionDigits="0" class="w-full" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium text-surface-700">Date</label>
                    <DatePicker v-model="form.date" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Description</label>
                <Textarea v-model="form.description" rows="3" autoResize class="w-full" placeholder="Ex: Reglement, achat, transfert..." />
            </div>
        </div>

        <template #footer>
            <Button label="Annuler" text @click="close" />
            <Button
                :label="mode === 'edit' ? 'Mettre a jour' : 'Enregistrer'"
                icon="pi pi-check"
                :loading="loading"
                @click="submitForm" />
        </template>
    </Dialog>
</template>
