<script setup>
import { computed, reactive, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';

const props = defineProps({
    visible: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    user: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'submit']);

const formState = reactive({
    username: '',
    employee_id: ''
});

const dialogTitle = computed(() => (props.mode === 'edit' ? "Modifier l'utilisateur" : 'Ajouter un utilisateur'));
const submitLabel = computed(() => (props.mode === 'edit' ? 'Enregistrer' : 'Créer'));

const localVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const employeeOptions = computed(() =>
    (props.employees || []).map((emp) => ({
        label: `${emp?.nom || ''} ${emp?.prenom || ''}`.trim() || `Employé #${emp?.id}`,
        value: emp?.id
    }))
);

watch(
    () => props.user,
    (value) => {
        formState.username = value?.username || '';
        formState.employee_id = value?.employee?.id || value?.employee_id || '';
    },
    { immediate: true }
);

const closeDialog = () => {
    emit('update:visible', false);
};

const handleSubmit = (event) => {
    emit('submit', { ...formState }, event);
};
</script>

<template>
    <Dialog :header="dialogTitle" v-model:visible="localVisible" :style="{ width: '520px' }" :modal="true" @hide="closeDialog">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-2">
                <label for="user-username" class="font-medium">Nom d'utilisateur <span class="text-red-500">*</span></label>
                <InputText id="user-username" v-model="formState.username" placeholder="Nom d'utilisateur" class="w-full" />
            </div>

            <div class="flex flex-col gap-2">
                <label for="user-employee" class="font-medium">Employé associé</label>
                <Select
                    id="user-employee"
                    v-model="formState.employee_id"
                    :options="employeeOptions"
                    optionLabel="label"
                    optionValue="value"
                    placeholder="Sélectionner un employé"
                    class="w-full"
                    showClear
                />
            </div>
        </div>

        <template #footer>
            <Button label="Annuler" icon="pi pi-times" severity="secondary" text @click="closeDialog" />
            <Button :label="submitLabel" icon="pi pi-check" :loading="loading" @click="handleSubmit" />
        </template>
    </Dialog>
</template>
