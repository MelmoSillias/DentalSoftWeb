<script setup>
import { computed, reactive, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import {
    getPaymentCoverageRate,
    getPaymentMethodDefinition,
    isInsuranceMethod,
    resolvePaymentMethodFamily,
    resolvePaymentMethodTypeKey
} from '@/utils/paymentMethodUtils';

const props = defineProps({
    visible: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    mode: { type: Object, default: null }
});

const emit = defineEmits(['update:visible', 'submit']);

const defaultForm = () => ({
    libelle: '',
    typeKey: 'cash',
    coverageRate: null,
    notes: ''
});

const form = reactive(defaultForm());

const typeOptions = [
    { label: 'Espèces', value: 'cash' },
    { label: 'Chèque', value: 'cheque' },
    { label: 'Mobile Money', value: 'mobile_money' },
    { label: 'Virement bancaire', value: 'bank_transfer' },
    { label: 'Assurance', value: 'insurance' },
    { label: 'Autre', value: 'other' }
];

const isEdit = computed(() => Boolean(props.mode?.id));
const selectedTypeDefinition = computed(() => getPaymentMethodDefinition({ typeKey: form.typeKey }));
const isInsuranceType = computed(() => isInsuranceMethod({ typeKey: form.typeKey }));
const canSubmit = computed(() => {
    if (!form.libelle.trim()) {
        return false;
    }

    if (!isInsuranceType.value) {
        return true;
    }

    return Number(form.coverageRate) > 0;
});

const syncForm = () => {
    const source = props.mode || {};
    form.libelle = source?.libelle || '';
    form.typeKey = resolvePaymentMethodTypeKey(source);
    form.coverageRate = isInsuranceMethod(source) ? getPaymentCoverageRate(source) : null;
    form.notes = source?.notes || '';
};

watch(
    () => form.typeKey,
    (typeKey) => {
        if (typeKey !== 'insurance') {
            form.coverageRate = null;
            return;
        }

        if (!(Number(form.coverageRate) > 0)) {
            form.coverageRate = 80;
        }
    }
);

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
        libelle: form.libelle.trim(),
        typeKey: form.typeKey,
        type: selectedTypeDefinition.value.label,
        family: resolvePaymentMethodFamily({ typeKey: form.typeKey }),
        coverageRate: isInsuranceType.value ? Number(form.coverageRate || 0) : null,
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
                <label class="text-sm font-medium text-surface-700">Type de mode</label>
                <Select v-model="form.typeKey" :options="typeOptions" optionLabel="label" optionValue="value" />
            </div>
            <div v-if="isInsuranceType" class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Pourcentage de prise en charge (%)</label>
                <InputNumber
                    v-model="form.coverageRate"
                    mode="decimal"
                    locale="fr-FR"
                    :min="0"
                    :max="100"
                    :minFractionDigits="0"
                    :maxFractionDigits="2"
                    inputClass="w-full"
                    class="w-full" />
                <small class="text-surface-500">Valeur obligatoire pour les assurances. Elle servira de valeur par défaut dans les paiements.</small>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-700">Notes</label>
                <Textarea v-model="form.notes" rows="3" autoResize class="w-full" placeholder="Informations internes" />
            </div>
        </div>

        <template #footer>
            <Button label="Annuler" text @click="close" />
            <Button :label="isEdit ? 'Mettre a jour' : 'Enregistrer'" icon="pi pi-check" :loading="loading" :disabled="!canSubmit" @click="submitForm" />
        </template>
    </Dialog>
</template>
