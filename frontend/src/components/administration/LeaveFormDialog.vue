<script setup>
import { ref, watch, computed } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const props = defineProps({
    visible: Boolean,
    mode: {
        type: String,
        default: 'create'
    },
    leave: {
        type: Object,
        default: null
    },
    employees: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'submit']);

const localVisible = ref(false);
const form = ref({
    employeId: null,
    type: 'Vacances',
    startDate: new Date(),
    endDate: new Date()
});

watch(
    () => props.visible,
    (val) => {
        localVisible.value = val;
    },
    { immediate: true }
);

watch(localVisible, (val) => emit('update:visible', val));

watch(
    () => [props.leave, props.mode],
    () => {
        if (props.mode === 'edit' && props.leave) {
            form.value = {
                employeId: props.leave.employeId,
                type: props.leave.type || 'Vacances',
                startDate: props.leave.start ? new Date(props.leave.start) : new Date(),
                endDate: props.leave.end ? new Date(props.leave.end) : new Date()
            };
            return;
        }

        form.value = {
            employeId: null,
            type: 'Vacances',
            startDate: new Date(),
            endDate: new Date()
        };
    },
    { immediate: true }
);

const employeeOptions = computed(() =>
    (props.employees || []).map((employee) => ({
        label: `${employee.prenom || ''} ${employee.nom || ''}`.trim(),
        value: employee.id
    }))
);

const close = () => {
    localVisible.value = false;
};

const submit = () => {
    if (!form.value.employeId || !form.value.type || !form.value.startDate || !form.value.endDate) return;

    emit('submit', {
        employeId: form.value.employeId,
        type: form.value.type,
        startDate: form.value.startDate.toISOString().slice(0, 10),
        endDate: form.value.endDate.toISOString().slice(0, 10)
    });
};
</script>

<template>
    <Dialog v-model:visible="localVisible" modal :header="mode === 'edit' ? 'Modifier un conge' : 'Nouveau conge'" :style="{ width: '34rem' }" @hide="close">
        <div class="space-y-4">
            <div class="space-y-1">
                <label class="text-sm font-medium">Employe <span class="text-red-500">*</span></label>
                <Select v-model="form.employeId" :options="employeeOptions" optionLabel="label" optionValue="value" class="w-full" placeholder="Selectionnez un employe" />
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium">Type de conge <span class="text-red-500">*</span></label>
                <InputText v-model="form.type" class="w-full" placeholder="Ex: vacances, arret" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Debut <span class="text-red-500">*</span></label>
                    <DatePicker v-model="form.startDate" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Fin <span class="text-red-500">*</span></label>
                    <DatePicker v-model="form.endDate" dateFormat="yy-mm-dd" showIcon class="w-full" />
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" text severity="secondary" @click="close" />
                <Button :label="mode === 'edit' ? 'Mettre a jour' : 'Creer'" icon="pi pi-check" :loading="loading" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>
