<script setup>
import { computed, reactive, watch } from 'vue';
import { STAFF_ROLE_ADMIN, STAFF_ROLE_MEDECIN, STAFF_ROLE_OPTIONS_WITHOUT_PATIENT, resolveRoleFromRoles, suggestRoleFromEmployeeType } from '@/utils/employeeTypeUtils';
import AppDialog from '@/components/layout/AppDialog.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const props = defineProps({
    visible: { type: Boolean, default: false },
    mode: { type: String, default: 'create' },
    user: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    employeeLocked: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    tourTarget: { type: String, default: null }
});
const emit = defineEmits(['update:visible', 'submit']);
const formState = reactive({ username: '', role: STAFF_ROLE_ADMIN, employee_id: null });
const dialogTitle = computed(() => (props.mode === 'edit' ? "Modifier l'utilisateur" : 'Ajouter un utilisateur'));
const submitLabel = computed(() => (props.mode === 'edit' ? 'Enregistrer' : 'Créer'));
const localVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});
const eligibleEmployees = computed(() => {
    const employees = [...props.employees];
    const current = props.user?.employee;
    if (current?.id && !employees.some((employee) => employee.id === current.id)) employees.push(current);
    return employees.filter((employee) => suggestRoleFromEmployeeType(employee.type));
});
const employeeOptions = computed(() => eligibleEmployees.value.map((employee) => ({
    label: `${employee.nom || ''} ${employee.prenom || ''}`.trim() || `Employé #${employee.id}`,
    value: employee.id
})));
const selectedEmployee = computed(() => eligibleEmployees.value.find((employee) => employee.id === formState.employee_id));
const employeeRole = computed(() => suggestRoleFromEmployeeType(selectedEmployee.value?.type));
const validationMessage = computed(() => {
    if (formState.employee_id && !employeeRole.value) return 'Ce poste ne permet pas de créer un compte utilisateur.';
    if (formState.role === STAFF_ROLE_MEDECIN && !selectedEmployee.value) return 'Un compte médecin doit être lié à un employé médecin.';
    return '';
});
watch(
    [() => props.user, () => props.visible],
    () => {
        if (!props.visible) return;
        const user = props.user;
        formState.username = user?.username || '';
        formState.employee_id = user?.employee?.id || user?.employee_id || null;
        formState.role = user ? (STAFF_ROLE_OPTIONS_WITHOUT_PATIENT.find((option) => option.value === user.role)?.value || resolveRoleFromRoles(user.roles)) : STAFF_ROLE_ADMIN;
        if (employeeRole.value) formState.role = employeeRole.value;
    },
    { immediate: true }
);
watch(employeeRole, (role) => {
    if (role) formState.role = role;
});
const closeDialog = () => emit('update:visible', false);
const handleSubmit = (event) => {
    if (validationMessage.value || !formState.username.trim()) return;
    emit('submit', {
        username: formState.username.trim(),
        role: employeeRole.value || formState.role,
        employee_id: formState.employee_id || null
    }, event);
};
</script>

<template>
    <AppDialog
        v-model:visible="localVisible"
        :title="dialogTitle"
        icon="pi pi-user"
        icon-tone="primary"
        width="640px"
        :loading="loading"
        :confirm-disabled="!!validationMessage || !formState.username.trim()"
        cancel-label="Annuler"
        :confirm-label="submitLabel"
        confirm-icon="pi pi-check"
        @cancel="closeDialog"
        @confirm="handleSubmit"
        @hide="closeDialog"
    >
        <div class="flex flex-col gap-4" :data-tour="props.tourTarget || null">
            <div class="flex flex-col gap-2">
                <label for="user-username" class="font-medium">Nom d'utilisateur <span class="text-red-500">*</span></label>
                <InputText id="user-username" v-model="formState.username" placeholder="Nom d'utilisateur" class="w-full" />
            </div>
            <div class="flex flex-col gap-2">
                <label for="user-employee" class="font-medium">Employé associé <span v-if="formState.role === STAFF_ROLE_MEDECIN" class="text-red-500">*</span></label>
                <Select inputId="user-employee" v-model="formState.employee_id" :options="employeeOptions" optionLabel="label" optionValue="value" placeholder="Sélectionner un employé" class="w-full" filter :showClear="!employeeLocked" :disabled="employeeLocked" />
                <small v-if="employeeLocked" class="text-surface-500">Ce compte reste lié à cet employé.</small>
                <small v-else class="text-surface-500">Postes habilités : réceptionniste, médecin et administrateur. Seuls les employés sans compte sont proposés.</small>
            </div>
            <div class="flex flex-col gap-2">
                <label for="user-role" class="font-medium">Rôle <span class="text-red-500">*</span></label>
                <Select inputId="user-role" v-model="formState.role" :options="STAFF_ROLE_OPTIONS_WITHOUT_PATIENT" optionLabel="label" optionValue="value" class="w-full" :disabled="!!formState.employee_id" />
                <small class="text-surface-500">Le rôle est déterminé par le poste de l'employé associé. Sans employé, seuls les comptes administrateur et réceptionniste sont autorisés.</small>
            </div>
            <small v-if="validationMessage" role="alert" class="text-red-500">{{ validationMessage }}</small>
        </div>
    </AppDialog>
</template>
