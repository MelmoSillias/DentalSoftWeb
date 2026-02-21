<script setup>
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    medecins: {
        type: Array,
        default: () => []
    },
    defaultDate: {
        type: Date,
        default: () => new Date()
    },
    defaultMedecinId: {
        type: Number,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    },
    searchPatients: {
        type: Function,
        default: null
    }
});

const emit = defineEmits(['update:visible', 'submit']);

const localVisible = ref(props.visible);
watch(() => props.visible, (v) => (localVisible.value = v), { immediate: true });
watch(localVisible, (v) => emit('update:visible', v));

const form = reactive({
    patient: null,
    medecinId: props.defaultMedecinId,
    dateTime: props.defaultDate,
    duration: 30,
    description: ''
});

const patientOptions = ref([]);

watch(
    () => props.defaultDate,
    (val) => {
        form.dateTime = val || new Date();
    },
    { immediate: true }
);

watch(
    () => props.defaultMedecinId,
    (val) => {
        form.medecinId = val;
    },
    { immediate: true }
);

const close = () => (localVisible.value = false);

const submit = () => {
    if (!form.dateTime) return;
    const end = new Date(form.dateTime.getTime() + form.duration * 60000);
    emit('submit', {
        patientId: form.patient?.id || null,
        patientName: form.patient?.name || '',
        medecinId: form.medecinId,
        start: form.dateTime.toISOString(),
        end: end.toISOString(),
        description: form.description
    });
    close();
};

const searchPatients = async (event) => {
    if (!props.searchPatients) return;
    patientOptions.value = await props.searchPatients(event.query || '');
};
</script>

<template>
    <Dialog v-model:visible="localVisible" modal header="Créer un rendez-vous" style="width: 520px" @hide="close">
        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Patient</label>
                <AutoComplete
                    v-model="form.patient"
                    optionLabel="name"
                    forceSelection
                    :suggestions="patientOptions"
                    placeholder="Rechercher un patient"
                    @complete="searchPatients"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Médecin</label>
                <Select v-model="form.medecinId" :options="medecins" optionLabel="name" optionValue="id" placeholder="Sélectionner" />
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Date & heure</label>
                    <DatePicker v-model="form.dateTime" showTime hourFormat="24" dateFormat="dd/mm/yy" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Durée (minutes)</label>
                    <Select v-model="form.duration" :options="[15, 30, 45, 60]" placeholder="Durée" />
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-surface-700 dark:text-surface-50">Description</label>
                <Textarea v-model="form.description" rows="3" autoResize />
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" severity="secondary" text @click="close" />
                <Button label="Créer" icon="pi pi-check" :loading="loading" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>

