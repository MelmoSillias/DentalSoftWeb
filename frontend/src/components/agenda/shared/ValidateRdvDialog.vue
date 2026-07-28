<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    rdv: {
        type: Object,
        default: null
    },
    medecins: {
        type: Array,
        default: () => []
    },
    lockedMedecinId: {
        type: Number,
        default: null
    },
    medecinReadonly: {
        type: Boolean,
        default: false
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'confirm']);

const medecinId = ref(null);
const createConsultation = ref(false);

const consultationOptions = [
    { label: 'Non', value: false },
    { label: 'Oui', value: true }
];

const localVisible = ref(props.visible);
watch(() => props.visible, (v) => (localVisible.value = v), { immediate: true });
watch(localVisible, (v) => emit('update:visible', v));

const resetForm = () => {
    createConsultation.value = false;
    medecinId.value = props.lockedMedecinId ?? props.rdv?.medecinId ?? null;
};

watch(
    () => [props.rdv, props.visible],
    () => {
        if (props.visible) {
            resetForm();
        }
    },
    { immediate: true }
);

watch(
    () => props.lockedMedecinId,
    (val) => {
        if (val && createConsultation.value) {
            medecinId.value = val;
        }
    }
);

watch(createConsultation, (yes) => {
    if (!yes) {
        return;
    }

    medecinId.value = props.lockedMedecinId ?? props.rdv?.medecinId ?? medecinId.value ?? null;
});

const canSubmit = computed(() => !createConsultation.value || Boolean(medecinId.value));

const close = () => (localVisible.value = false);

const confirm = () => {
    if (!canSubmit.value) {
        return;
    }

    emit('confirm', {
        id: props.rdv?.id,
        createConsultation: createConsultation.value,
        medecinId: createConsultation.value
            ? (props.medecinReadonly ? (props.lockedMedecinId ?? medecinId.value) : medecinId.value)
            : null
    });
    close();
};
</script>

<template>
    <Dialog v-model:visible="localVisible" modal header="Valider le rendez-vous" style="width: 440px" @hide="close">
        <div class="flex flex-col gap-4">
            <p class="text-sm text-surface-700 dark:text-surface-300">
                Confirmer la validation de ce rendez-vous ?
            </p>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-800 dark:text-surface-100">
                    Créer une consultation maintenant ?
                </label>
                <SelectButton
                    v-model="createConsultation"
                    :options="consultationOptions"
                    option-label="label"
                    option-value="value"
                    aria-labelledby="validate-rdv-create-consultation"
                />
                <p v-if="!createConsultation" class="text-xs text-surface-500 dark:text-surface-400">
                    Le rendez-vous sera validé sans consultation ni attribution de médecin.
                </p>
            </div>

            <div v-if="createConsultation" class="flex flex-col gap-2">
                <label class="text-sm font-medium text-surface-800 dark:text-surface-100">
                    Médecin <span class="text-red-500">*</span>
                </label>
                <Select
                    v-model="medecinId"
                    :options="medecins"
                    option-label="name"
                    option-value="id"
                    placeholder="Choisir le médecin"
                    :disabled="medecinReadonly"
                />
                <p v-if="!medecinId" class="text-xs text-amber-600 dark:text-amber-400">
                    Un médecin est obligatoire pour créer la consultation.
                </p>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" text severity="secondary" @click="close" />
                <Button
                    label="Valider"
                    icon="pi pi-check"
                    :loading="loading"
                    :disabled="!canSubmit"
                    @click="confirm"
                />
            </div>
        </template>
    </Dialog>
</template>
