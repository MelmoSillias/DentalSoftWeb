<script setup>
import AppDialog from '@/components/layout/AppDialog.vue';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    visible: Boolean,
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

const emit = defineEmits(['update:visible', 'submit']);

const localVisible = ref(props.visible);
watch(
    () => props.visible,
    (v) => (localVisible.value = v),
    { immediate: true }
);
watch(localVisible, (v) => emit('update:visible', v));

const form = reactive({
    medecinId: null,
    dateTime: new Date(),
    duration: 30
});

watch(
    () => props.rdv,
    (val) => {
        if (val) {
            form.medecinId = props.lockedMedecinId ?? val.medecinId;
            form.dateTime = new Date(val.start);
        }
    },
    { immediate: true }
);

watch(
    () => props.lockedMedecinId,
    (val) => {
        if (val) {
            form.medecinId = val;
        }
    },
    { immediate: true }
);

const close = () => (localVisible.value = false);
const submit = () => {
    if (!props.rdv) return;
    const end = new Date(form.dateTime.getTime() + form.duration * 60000);
    emit('submit', {
        id: props.rdv.id,
        medecinId: props.medecinReadonly ? (props.lockedMedecinId ?? form.medecinId) : form.medecinId,
        start: form.dateTime.toISOString(),
        end: end.toISOString()
    });
    close();
};
</script>

<template>
    <AppDialog
        v-model:visible="localVisible"
        title="Reporter le rendez-vous"
        icon="pi pi-calendar-plus"
        icon-tone="warning"
        size="md"
        :loading="loading"
        cancel-label="Fermer"
        confirm-label="Reporter"
        confirm-icon="pi pi-send"
        confirm-severity="warning"
        @cancel="close"
        @confirm="submit"
        @hide="close"
    >
        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Médecin</label>
                <Select v-model="form.medecinId" :options="medecins" optionLabel="name" optionValue="id" placeholder="Médecin" :disabled="medecinReadonly" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Nouvelle date</label>
                    <DatePicker v-model="form.dateTime" showTime hourFormat="24" dateFormat="dd/mm/yy" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Durée (minutes)</label>
                    <Select v-model="form.duration" :options="[15, 30, 45, 60]" placeholder="Durée" />
                </div>
            </div>
        </div>
    </AppDialog>
</template>
