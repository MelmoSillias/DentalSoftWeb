<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Chip from 'primevue/chip';
import ProfileInfoForm from './ProfileInfoForm.vue';
import ProfilePasswordForm from './ProfilePasswordForm.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    user: { type: Object, default: () => ({}) },
    employee: { type: Object, default: () => null },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['save-info', 'change-password']);

const editVisible = ref(false);
const passwordVisible = ref(false);

const displayName = computed(() => {
    if (props.employee?.prenom || props.employee?.nom) {
        return `${props.employee?.prenom ?? ''} ${props.employee?.nom ?? ''}`.trim();
    }
    return props.user?.username || 'Utilisateur';
});
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-wider text-surface-500">Espace personnel</p>
                <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-100">{{ displayName }}</h2>
                <p class="text-sm text-surface-500">{{ user?.username }}</p>
            </div>
            <div class="flex gap-2">
                <Button label="Modifier" icon="pi pi-user-edit" severity="secondary" outlined @click="editVisible = true" />
                <Button label="Mot de passe" icon="pi pi-key" severity="primary" @click="passwordVisible = true" />
            </div>
        </div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <div class="text-sm text-surface-500">Rôles</div>
                <div class="flex flex-wrap gap-2 mt-2">
                    <Chip v-for="role in (user?.roles || [])" :key="role" :label="role" class="bg-surface-100 dark:bg-surface-700" />
                </div>
            </div>
            <div v-if="employee">
                <div class="text-sm text-surface-500">Fonction</div>
                <div class="text-base font-medium text-surface-900 dark:text-surface-100">{{ employee.fonction || '—' }}</div>
            </div>
            <div v-if="employee">
                <div class="text-sm text-surface-500">Email</div>
                <div class="text-base font-medium text-surface-900 dark:text-surface-100">{{ employee.email || '—' }}</div>
            </div>
            <div v-if="employee">
                <div class="text-sm text-surface-500">Téléphone</div>
                <div class="text-base font-medium text-surface-900 dark:text-surface-100">{{ employee.telephone || '—' }}</div>
            </div>
            <div v-if="employee">
                <div class="text-sm text-surface-500">Matricule</div>
                <div class="text-base font-medium text-surface-900 dark:text-surface-100">{{ employee.matricule || '—' }}</div>
            </div>
            <div v-if="employee">
                <div class="text-sm text-surface-500">Type contrat</div>
                <div class="text-base font-medium text-surface-900 dark:text-surface-100">{{ employee.typeContrat || '—' }}</div>
            </div>
            <div v-if="employee">
                <div class="text-sm text-surface-500">Date embauche</div>
                <div class="text-base font-medium text-surface-900 dark:text-surface-100">{{ employee.dateEmbauche || '—' }}</div>
            </div>
        </div>
    </div>

    <Dialog v-model:visible="editVisible" header="Modifier les informations" modal class="w-full max-w-3xl">
        <ProfileInfoForm :user="user" :employee="employee" :loading="loading" @save="emit('save-info', $event); editVisible = false" />
    </Dialog>

    <Dialog v-model:visible="passwordVisible" header="Changer le mot de passe" modal class="w-full max-w-xl">
        <ProfilePasswordForm :loading="loading" @save="emit('change-password', $event); passwordVisible = false" />
    </Dialog>
</template>
