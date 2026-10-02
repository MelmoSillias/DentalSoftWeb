<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import AppDialog from '@/components/layout/AppDialog.vue';
import FormCreateConsultation from '@/components/patients/FormCreateConsultation.vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    patient: {
        type: Object,
        default: null
    },
    patientId: {
        type: [Number, String],
        default: null
    },
    subtitle: {
        type: String,
        default: null
    },
    maximizable: {
        type: Boolean,
        default: false
    },
    tourId: {
        type: String,
        default: null
    },
    contentTourId: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['update:visible', 'saved', 'cancel']);

const formRef = ref(null);

const resolvedSubtitle = computed(() => {
    if (props.subtitle) return props.subtitle;
    const p = props.patient;
    if (!p) return 'Créer une consultation';
    return p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || 'Nouveau patient';
});

const formLoading = computed(() => Boolean(formRef.value?.loading || formRef.value?.checkingActive));
const formDisabled = computed(() => Boolean(formRef.value?.hasActiveConsultation || formRef.value?.checkingActive));

const onUpdateVisible = (value) => {
    emit('update:visible', value);
};

const onCancel = () => {
    emit('cancel');
    emit('update:visible', false);
};

const onConfirm = () => {
    formRef.value?.submit?.();
};

const onSaved = (payload) => {
    emit('saved', payload);
    emit('update:visible', false);
};
</script>

<template>
    <AppDialog
        :visible="visible"
        title="Nouvelle consultation"
        :subtitle="resolvedSubtitle"
        icon="fas fa-stethoscope"
        icon-tone="success"
        size="lg"
        :maximizable="maximizable"
        :loading="formLoading"
        :confirm-disabled="formDisabled"
        :content-padding="true"
        :data-tour="tourId || undefined"
        @update:visible="onUpdateVisible"
        @cancel="onCancel"
    >
        <div :data-tour="contentTourId || undefined">
            <FormCreateConsultation
                ref="formRef"
                hide-actions
                :patient="patient"
                :patient-id="patientId"
                @saved="onSaved"
                @cancel="onCancel"
            />
        </div>
        <template #footer>
            <div class="flex justify-end gap-2 w-full" data-tour="patients-form-consultation.actions">
                <Button type="button" label="Annuler" severity="secondary" text class="rounded-xl px-5" :disabled="formLoading" @click="onCancel" />
                <Button
                    type="button"
                    label="Créer"
                    icon="pi pi-check"
                    class="rounded-xl px-5"
                    :loading="formLoading"
                    :disabled="formDisabled"
                    @click="onConfirm"
                />
            </div>
        </template>
    </AppDialog>
</template>
