<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import { ref, watch } from 'vue';

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
    autoCreateConsultation: {
        type: Boolean,
        default: true
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'confirm']);
const medecinId = ref(null);

const localVisible = ref(props.visible);
watch(() => props.visible, (v) => (localVisible.value = v), { immediate: true });
watch(localVisible, (v) => emit('update:visible', v));

watch(
    () => props.rdv,
    (val) => {
        medecinId.value = props.lockedMedecinId ?? val?.medecinId ?? null;
    },
    { immediate: true }
);

watch(
    () => props.lockedMedecinId,
    (val) => {
        if (val) {
            medecinId.value = val;
        }
    },
    { immediate: true }
);

const close = () => (localVisible.value = false);
const confirm = () => {
    emit('confirm', {
        id: props.rdv?.id,
        medecinId: props.medecinReadonly ? (props.lockedMedecinId ?? medecinId.value) : medecinId.value
    });
    close();
};
</script>

<template>
    <Dialog v-model:visible="localVisible" modal header="Valider le rendez-vous" style="width: 420px" @hide="close">
        <div class="flex flex-col gap-3">
            <p class="text-sm text-surface-700 dark:text-surface-300">Confirmer la validation de ce rendez-vous ?</p>
            <p v-if="!autoCreateConsultation" class="text-xs text-surface-500 dark:text-surface-400">
                La validation n'entraîne pas la création automatique d'une consultation.
            </p>
            <Select
                v-model="medecinId"
                :options="medecins"
                optionLabel="name"
                optionValue="id"
                placeholder="Médecin"
                :disabled="medecinReadonly"
            />
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" text severity="secondary" @click="close" />
                <Button label="Valider" icon="pi pi-check" :loading="loading" @click="confirm" />
            </div>
        </template>
    </Dialog>
</template>

