<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Breadcrumb from 'primevue/breadcrumb';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import { useUsers } from '@/composables/useUsers';
import { useEmployees } from '@/composables/useEmployees';
import UserForm from '@/components/administration/UserForm.vue';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createAdministrationUsersTour } from '@/tours/administrationUsersTour';
import { startTourGuide } from '@/tours/tourGuideClient';

const toast = useToast();
const confirm = useConfirm();

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Utilisateurs' }];

const { users, loading, error, fetchUsers, addUser, updateUser, resetPassword, deleteUser, toggleUserStatus } = useUsers();
const { employees, fetchEmployees, error: employeesError } = useEmployees();

const search = ref('');
const filters = ref({
    global: { value: null, matchMode: 'contains' }
});

const formVisible = ref(false);
const formMode = ref('create');
const currentUser = ref(null);

const resetDialogVisible = ref(false);
const resetPasswordValue = ref('');
const resetTargetUser = ref(null);

const groupByType = ref(false);
const expandedGroups = ref([]);
const isGuidedTourStarting = ref(false);

const usersView = computed(() =>
    (users.value || []).map((user) => {
        const roles = Array.isArray(user?.roles) ? user.roles : user?.role ? [user.role] : [];
        const fonction = user?.fonction ? String(user.fonction) : '';

        const typeLabel =
            user?.type ||
            (fonction
                ? fonction.charAt(0).toUpperCase() + fonction.slice(1)
                : roles.includes('ROLE_ADMIN')
                  ? 'Administrateur'
                  : roles.includes('ROLE_SECRETAIRE')
                    ? 'Secrétaire'
                    : roles.includes('ROLE_TOPO')
                      ? 'Topographe'
                      : roles.includes('ROLE_AGENT')
                        ? 'Agent'
                        : roles.includes('ROLE_COMMERCIAL')
                          ? 'Commercial'
                          : 'Utilisateur');

        const employee = user?.employee || user?.employe || null;
        const employeeLabel = employee ? `${employee?.nom || ''} ${employee?.prenom || ''}`.trim() : '-';

        const activeValue = user?.active ?? user?.enabled ?? user?.isActive;

        return {
            ...user,
            typeLabel,
            employeeLabel,
            isActive: typeof activeValue === 'boolean' ? activeValue : null
        };
    })
);

const loadUsers = async () => {
    await fetchUsers();
    if (error.value) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: error.value, life: 4000 });
    }
};

const loadEmployees = async () => {
    await fetchEmployees({ page: 0, rows: 1000 });
    if (employeesError.value) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: employeesError.value, life: 4000 });
    }
};

const openCreate = () => {
    formMode.value = 'create';
    currentUser.value = null;
    formVisible.value = true;
};

const openEdit = (user) => {
    formMode.value = 'edit';
    currentUser.value = user;
    formVisible.value = true;
};

const confirmFormSubmit = (payload, event) => {
    if (!payload?.username) {
        toast.add({ severity: 'warn', summary: 'Attention', detail: "Le nom d'utilisateur est requis.", life: 3000 });
        return;
    }
    const actionLabel = formMode.value === 'edit' ? 'mettre à jour' : 'créer';
    confirm.require({
        target: event?.currentTarget,
        message: `Confirmer la demande pour ${actionLabel} cet utilisateur ?`,
        icon: 'pi pi-check',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                if (formMode.value === 'edit' && currentUser.value?.id) {
                    await updateUser(currentUser.value.id, payload);
                    toast.add({ severity: 'success', summary: 'Succès', detail: 'Utilisateur mis à jour.', life: 3000 });
                } else {
                    await addUser(payload);
                    toast.add({ severity: 'success', summary: 'Succès', detail: 'Utilisateur créé.', life: 3000 });
                }
                formVisible.value = false;
                await loadUsers();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Action impossible.', life: 4000 });
            }
        }
    });
};

const confirmDelete = (user, event) => {
    confirm.require({
        target: event?.currentTarget,
        message: `Supprimer ${user?.username || 'cet utilisateur'} ?`,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await deleteUser(user.id);
                toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Utilisateur supprimé.', life: 3000 });
                await loadUsers();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Suppression impossible.', life: 4000 });
            }
        }
    });
};

const openResetPassword = (user) => {
    resetTargetUser.value = user;
    resetPasswordValue.value = '';
    resetDialogVisible.value = true;
};

const confirmResetPassword = (event) => {
    if (!resetTargetUser.value?.id || !resetPasswordValue.value) {
        toast.add({ severity: 'warn', summary: 'Attention', detail: 'Veuillez saisir un mot de passe.', life: 3000 });
        return;
    }

    confirm.require({
        target: event?.currentTarget,
        message: "Confirmer la réinitialisation du mot de passe ?",
        icon: 'pi pi-key',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await resetPassword(resetTargetUser.value.id, resetPasswordValue.value);
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Mot de passe réinitialisé.', life: 3000 });
                resetDialogVisible.value = false;
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Réinitialisation impossible.', life: 4000 });
            }
        }
    });
};

const confirmToggleStatus = (user, event) => {
    if (user?.isActive === null) {
        return;
    }
    const action = user.isActive ? 'deactivate' : 'activate';
    const label = user.isActive ? 'désactiver' : 'activer';
    confirm.require({
        target: event?.currentTarget,
        message: `Confirmer pour ${label} cet utilisateur ?`,
        icon: 'pi pi-refresh',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        accept: async () => {
            try {
                await toggleUserStatus(user.id, action);
                toast.add({ severity: 'success', summary: 'Succès', detail: 'Statut mis à jour.', life: 3000 });
                await loadUsers();
            } catch (err) {
                toast.add({ severity: 'error', summary: 'Erreur', detail: err?.message || 'Mise à jour impossible.', life: 4000 });
            }
        }
    });
};

const toggleGrouping = () => {
    groupByType.value = !groupByType.value;
    if (!groupByType.value) {
        expandedGroups.value = [];
    }
};

const hasOpenDialogs = computed(() => formVisible.value || resetDialogVisible.value);
const firstUser = computed(() => (usersView.value || [])[0] || null);

const resetTourDialogs = () => {
    formVisible.value = false;
    currentUser.value = null;
    resetDialogVisible.value = false;
    resetTargetUser.value = null;
    resetPasswordValue.value = '';
};

const openTourCreateDialog = () => {
    openCreate();
};

const openTourResetDialog = () => {
    if (!firstUser.value) return;
    openResetPassword(firstUser.value);
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'administration-utilisateurs' || isGuidedTourStarting.value) {
        return;
    }

    if (loading.value || hasOpenDialogs.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidee',
            detail: 'Attendez la fin du chargement et fermez les fenetres ouvertes avant de lancer le tour.',
            life: 3000
        });
        return;
    }

    isGuidedTourStarting.value = true;

    try {
        resetTourDialogs();
        await nextTick();

        const steps = createAdministrationUsersTour({
            hasUsers: usersView.value.length > 0,
            openCreateDialog: openTourCreateDialog,
            openResetDialog: openTourResetDialog,
            closeAllDialogs: resetTourDialogs
        });

        await startTourGuide({
            group: 'administration-utilisateurs',
            steps,
            onAfterExit: resetTourDialogs,
            onFinish: resetTourDialogs
        });
    } catch (error) {
        console.error('Erreur lancement guided tour utilisateurs', error);
        toast.add({
            severity: 'error',
            summary: 'Aide guidee',
            detail: 'Impossible de lancer le tour de la page utilisateurs.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

watch(search, (value) => {
    filters.value.global.value = value;
});

onMounted(() => {
    loadUsers();
    loadEmployees();
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    resetTourDialogs();
});
</script>

<template>
    <section
        class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <Toast />
        <ConfirmPopup />

        <div data-tour="admin-users.header" class="mb-6 md:mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-500/10 text-primary-500">
                            <i class="pi pi-users text-2xl"></i>
                        </span>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-semibold text-surface-900 dark:text-surface-50">Gestion des utilisateurs</h1>
                            <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base">
                                Ajoutez, modifiez et sécurisez les comptes utilisateurs.
                            </p>
                        </div>
                    </div>
                </div>
                <div data-tour="admin-users.grouping" class="flex flex-wrap items-center gap-3">
                    <Button
                        icon="pi pi-sitemap"
                        :label="groupByType ? 'Regroupement actif' : 'Regrouper par type'"
                        :severity="groupByType ? 'info' : 'secondary'"
                        outlined
                        @click="toggleGrouping" />
                    <Button
                        icon="pi pi-plus"
                        label="Nouvel utilisateur"
                        class="shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-6 py-3 rounded-xl font-medium"
                        @click="openCreate" />
                </div>
            </div>

            <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl p-4 shadow-sm border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
        </div>

        <div data-tour="admin-users.search" class="mb-6 md:mb-8">
            <div class="card p-5 md:p-6 border-0 rounded-2xl bg-gradient-to-r from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 shadow-lg backdrop-blur-sm">
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 mb-4 flex items-center gap-2">
                    <i class="pi pi-filter text-primary-500"></i>
                    Recherche
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                    <div class="md:col-span-6">
                        <IconField class="p-input-icon-left w-full">
                            <InputIcon class="pi pi-search text-surface-400" />
                            <InputText 
                                v-model="search" 
                                placeholder="Nom d'utilisateur, employé, type..." 
                                name="users-search"
                                autocomplete="off"
                                data-lpignore="true"
                                class="w-full p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                            />
                        </IconField>
                    </div>
                </div>
            </div>
        </div>

        <div data-tour="admin-users.table" class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
            <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Liste des utilisateurs</h2>
                        <p class="text-sm text-surface-600 dark:text-surface-300">Consultez et gérez les comptes existants.</p>
                    </div>
                </div>
            </div>

            <DataTable
                :value="usersView"
                dataKey="id"
                :paginator="true"
                :rows="10"
                :rowsPerPageOptions="[10, 20, 50]"
                :filters="filters"
                :globalFilterFields="['username', 'employeeLabel', 'typeLabel']"
                :loading="loading"
                sortMode="multiple"
                :rowGroupMode="groupByType ? 'subheader' : null"
                :groupRowsBy="groupByType ? 'typeLabel' : null"
                v-model:expandedRowGroups="expandedGroups"
                class="rounded-none border-0"
                :pt="{
                    thead: 'bg-surface-50 dark:bg-surface-900/50',
                    bodyCell: {
                        class: 'py-4 px-5 border-b border-surface-100 dark:border-surface-800'
                    },
                    row: {
                        class: 'hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors'
                    }
                }"
            >
                <template v-if="groupByType" #groupheader="{ data }">
                    <div class="flex items-center justify-between w-full px-4 py-3 bg-gradient-to-r from-surface-100/80 to-surface-50 dark:from-surface-800/80 dark:to-surface-900/60">
                        <span class="font-semibold text-surface-800 dark:text-surface-100">{{ data.typeLabel }}</span>
                        <Tag :value="data.typeLabel" severity="info" />
                    </div>
                </template>

                <Column field="username" header="Nom d'utilisateur" sortable>
                    <template #body="{ data }">
                        <div class="font-medium text-surface-900 dark:text-surface-100">{{ data.username }}</div>
                    </template>
                </Column>
                <Column field="employee" header="Employé" sortable></Column>
                <Column field="typeLabel" header="Type" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.typeLabel" :severity="data.typeLabel === 'Administrateur' ? 'danger' : 'info'" />
                    </template>
                </Column> 
                <Column header="Actions" style="min-width: 200px">
                    <template #body="{ data }">
                        <div data-tour="admin-users.actions" class="flex flex-wrap gap-2">
                            <Button icon="pi pi-pencil" severity="secondary" text @click="openEdit(data)" />
                            <Button icon="pi pi-key" severity="info" text @click="openResetPassword(data)" /> 
                            <Button icon="pi pi-trash" severity="danger" text @click="confirmDelete(data, $event)" />
                        </div>
                    </template>
                </Column>

                <template #empty>
                    <div class="text-center py-12 text-surface-600 dark:text-surface-300">
                        Aucun utilisateur trouvé.
                    </div>
                </template>
            </DataTable>
        </div>

        <div data-tour="admin-users.dialogs">
            <UserForm
                v-model:visible="formVisible"
                :mode="formMode"
                :user="currentUser"
                :employees="employees"
                :loading="loading"
                @submit="confirmFormSubmit" />

            <Dialog header="Réinitialiser le mot de passe" v-model:visible="resetDialogVisible" :style="{ width: '420px' }" :modal="true">
                <div class="flex flex-col gap-3">
                    <label for="reset-password" class="font-medium">Nouveau mot de passe</label>
                    <Password
                        inputId="reset-password"
                        v-model="resetPasswordValue"
                        toggleMask
                        :feedback="false"
                        placeholder="Saisir un mot de passe"
                        :inputProps="{
                            autocomplete: 'new-password',
                            name: 'user-reset-password',
                            'data-lpignore': 'true'
                        }"
                    />
                </div>
                <template #footer>
                    <Button label="Annuler" icon="pi pi-times" severity="secondary" text @click="resetDialogVisible = false" />
                    <Button label="Réinitialiser" icon="pi pi-check" @click="confirmResetPassword" />
                </template>
            </Dialog>
        </div>
    </section>
</template>
