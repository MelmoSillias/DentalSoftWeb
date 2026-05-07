<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
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

const initials = computed(() => {
    const name = displayName.value;
    return name.split(' ').filter(Boolean).map((w) => w[0]).join('').slice(0, 2).toUpperCase() || 'U';
});

const roleLabel = (role) => {
    const map = {
        ROLE_ADMIN: { label: 'Administrateur', severity: 'danger' },
        ROLE_MEDECIN: { label: 'Médecin', severity: 'info' },
        ROLE_RECEPTION: { label: 'Réception', severity: 'success' },
        ROLE_RECEPTIONNISTE: { label: 'Réceptionniste', severity: 'success' },
        ROLE_SECRETAIRE: { label: 'Secrétaire', severity: 'secondary' },
        ROLE_USER: { label: 'Utilisateur', severity: 'secondary' }
    };
    return map[role] || { label: role.replace('ROLE_', ''), severity: 'secondary' };
};

const infoFields = computed(() => {
    if (!props.employee) return [];
    return [
        { icon: 'pi pi-briefcase', label: 'Fonction', value: props.employee.fonction },
        { icon: 'pi pi-envelope', label: 'Email', value: props.employee.email },
        { icon: 'pi pi-phone', label: 'Téléphone', value: props.employee.telephone },
        { icon: 'pi pi-id-card', label: 'Matricule', value: props.employee.matricule },
        { icon: 'pi pi-file', label: 'Type contrat', value: props.employee.typeContrat },
        { icon: 'pi pi-calendar', label: 'Date embauche', value: props.employee.dateEmbauche }
    ].filter((f) => f.value);
});
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 shadow-sm">
        <!-- Bannière / avatar -->
        <div class="relative h-24 bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700 overflow-hidden">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 80%, white 1px, transparent 1px), radial-gradient(circle at 80% 20%, white 1px, transparent 1px); background-size: 30px 30px;"></div>
        </div>

        <!-- Avatar + actions -->
        <div class="px-5 pb-4">
            <div class="flex items-end justify-between -mt-10 mb-4">
                <div class="relative">
                    <div class="h-20 w-20 rounded-2xl border-4 border-surface-0 dark:border-surface-800 bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg">
                        <span class="text-2xl font-bold text-white">{{ initials }}</span>
                    </div>
                    <span class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full bg-emerald-500 border-2 border-surface-0 dark:border-surface-800 flex items-center justify-center">
                        <i class="pi pi-check text-white text-[9px]"></i>
                    </span>
                </div>
                <div class="flex gap-2 pb-1">
                    <Button icon="pi pi-user-edit" severity="secondary" outlined size="small" v-tooltip.top="'Modifier le profil'" @click="editVisible = true" />
                    <Button icon="pi pi-lock" severity="primary" size="small" v-tooltip.top="'Changer le mot de passe'" @click="passwordVisible = true" />
                </div>
            </div>

            <!-- Nom & identifiant -->
            <div class="mb-4">
                <h2 class="text-xl font-bold text-surface-900 dark:text-surface-50">{{ displayName }}</h2>
                <p class="text-sm text-surface-500 flex items-center gap-1.5 mt-0.5">
                    <i class="pi pi-at text-xs"></i>
                    {{ user?.username }}
                </p>
            </div>

            <!-- Rôles -->
            <div class="mb-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-surface-400 mb-2">Rôles</p>
                <div class="flex flex-wrap gap-1.5">
                    <Tag
                        v-for="role in (user?.roles || [])"
                        :key="role"
                        :value="roleLabel(role).label"
                        :severity="roleLabel(role).severity"
                        class="text-xs"
                    />
                </div>
            </div>

            <!-- Infos employé -->
            <div v-if="infoFields.length" class="space-y-2.5 pt-3 border-t border-surface-100 dark:border-surface-700/60">
                <div
                    v-for="field in infoFields"
                    :key="field.label"
                    class="flex items-center gap-3"
                >
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-surface-100 dark:bg-surface-700">
                        <i :class="field.icon" class="text-xs text-surface-500 dark:text-surface-400"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-surface-400 dark:text-surface-500">{{ field.label }}</p>
                        <p class="text-sm font-medium text-surface-900 dark:text-surface-100 truncate">{{ field.value || '—' }}</p>
                    </div>
                </div>
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
