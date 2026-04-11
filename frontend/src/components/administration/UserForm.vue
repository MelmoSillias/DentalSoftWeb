<script setup>
import { computed, reactive, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';

const props = defineProps({
    visible: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    user: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    patients: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    tourTarget: { type: String, default: null }
});

const emit = defineEmits(['update:visible', 'submit']);

const formState = reactive({
    username: '',
    role: 'Admin',
    employee_id: '',
    patient_id: '',
    associationMode: 'staff'
});

const dialogTitle = computed(() => (props.mode === 'edit' ? "Modifier l'utilisateur" : 'Ajouter un utilisateur'));
const submitLabel = computed(() => (props.mode === 'edit' ? 'Enregistrer' : 'Créer'));

const roleOptions = [
    { label: 'Admin', value: 'Admin' },
    { label: 'Medecin', value: 'Medecin' },
    { label: 'Secretaire', value: 'Secretaire' },
    { label: 'Patient', value: 'Patient' }
];

const staffRoleOptions = computed(() => roleOptions.filter((option) => option.value !== 'Patient'));
const patientRoleOptions = computed(() => roleOptions.filter((option) => option.value === 'Patient'));
const displayedRoleOptions = computed(() =>
    formState.associationMode === 'patient' ? patientRoleOptions.value : staffRoleOptions.value
);

const activeTabIndex = computed({
    get: () => (formState.associationMode === 'patient' ? 1 : 0),
    set: (index) => {
        formState.associationMode = index === 1 ? 'patient' : 'staff';
    }
});

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

const patientOptions = computed(() =>
    (props.patients || []).map((patient) => ({
        label: `${patient?.nom || ''} ${patient?.prenom || ''}`.trim() || `Patient #${patient?.id}`,
        value: patient?.id
    }))
);

const displayedEmployeeOptions = computed(() => {
    const options = [...employeeOptions.value];
    const current = props.user?.employee;
    const currentId = current?.id || props.user?.employee_id;

    if (currentId && !options.some((option) => option.value === currentId)) {
        options.push({
            label: `${current?.nom || ''} ${current?.prenom || ''}`.trim() || `Employé #${currentId}`,
            value: currentId
        });
    }

    return options;
});

const displayedPatientOptions = computed(() => {
    const options = [...patientOptions.value];
    const current = props.user?.patient;
    const currentId = current?.id || props.user?.patient_id;

    if (currentId && !options.some((option) => option.value === currentId)) {
        options.push({
            label: `${current?.nom || ''} ${current?.prenom || ''}`.trim() || `Patient #${currentId}`,
            value: currentId
        });
    }

    return options;
});

const resolveRoleFromUser = (user) => {
    const roles = Array.isArray(user?.roles) ? user.roles : [];

    if (roles.includes('ROLE_ADMIN')) return 'Admin';
    if (roles.includes('ROLE_MEDECIN')) return 'Medecin';
    if (roles.includes('ROLE_PATIENT')) return 'Patient';

    return 'Secretaire';
};

watch(
    () => props.user,
    (value) => {
        formState.username = value?.username || '';
        formState.role = resolveRoleFromUser(value);
        formState.employee_id = value?.employee?.id || value?.employee_id || '';
        formState.patient_id = value?.patient?.id || value?.patient_id || '';
        formState.associationMode = formState.role === 'Patient' ? 'patient' : 'staff';

        if (!value && props.mode === 'create') {
            formState.role = 'Admin';
            formState.employee_id = '';
            formState.patient_id = '';
            formState.associationMode = 'staff';
        }
    },
    { immediate: true }
);

watch(
    () => formState.associationMode,
    (mode) => {
        if (mode === 'patient') {
            formState.employee_id = '';
            formState.role = 'Patient';
            return;
        }

        formState.patient_id = '';
        if (formState.role === 'Patient') {
            formState.role = 'Admin';
        }
    }
);

const closeDialog = () => {
    emit('update:visible', false);
};

const handleSubmit = (event) => {
    emit(
        'submit',
        {
            username: formState.username?.trim(),
            role: formState.role,
            employee_id: formState.employee_id || null,
            patient_id: formState.patient_id || null
        },
        event
    );
};
</script>

<template>
    <Dialog :header="dialogTitle" v-model:visible="localVisible" :style="{ width: '640px' }" :modal="true" @hide="closeDialog">
        <div class="flex flex-col gap-4" :data-tour="props.tourTarget || null">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label for="user-username" class="font-medium">Nom d'utilisateur <span class="text-red-500">*</span></label>
                    <InputText id="user-username" v-model="formState.username" placeholder="Nom d'utilisateur" class="w-full" />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="user-role" class="font-medium">Role <span class="text-red-500">*</span></label>
                    <Select
                        id="user-role"
                        v-model="formState.role"
                        :options="displayedRoleOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        :disabled="formState.associationMode === 'patient'"
                    />
                </div>
            </div>

            <div>
                <p class="text-sm text-surface-600 mb-2">Type d'association (optionnelle)</p>
                <TabView v-model:activeIndex="activeTabIndex">
                    <TabPanel header="User / Employe (Staff)">
                        <div class="flex flex-col gap-2 pt-1">
                            <label for="user-employee" class="font-medium">Employe associe</label>
                            <Select
                                id="user-employee"
                                v-model="formState.employee_id"
                                :options="displayedEmployeeOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Selectionner un employe"
                                class="w-full"
                                showClear
                            />
                            <small class="text-surface-500">Optionnel: vous pourrez associer cet utilisateur plus tard.</small>
                        </div>
                    </TabPanel>

                    <TabPanel header="User / Patient">
                        <div class="flex flex-col gap-2 pt-1">
                            <label for="user-patient" class="font-medium">Patient associe</label>
                            <Select
                                id="user-patient"
                                v-model="formState.patient_id"
                                :options="displayedPatientOptions"
                                optionLabel="label"
                                optionValue="value"
                                placeholder="Selectionner un patient"
                                class="w-full"
                                showClear
                            />
                            <small class="text-surface-500">Optionnel: vous pourrez associer ce patient plus tard.</small>
                        </div>
                    </TabPanel>
                </TabView>
            </div>
        </div>

        <template #footer>
            <Button label="Annuler" icon="pi pi-times" severity="secondary" text @click="closeDialog" />
            <Button :label="submitLabel" icon="pi pi-check" :loading="loading" @click="handleSubmit" />
        </template>
    </Dialog>
</template>
