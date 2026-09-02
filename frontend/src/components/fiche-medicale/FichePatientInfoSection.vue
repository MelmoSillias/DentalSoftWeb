<script setup>
import Button from 'primevue/button';
import Tag from 'primevue/tag';

const props = defineProps({
    patient: {
        type: Object,
        default: () => ({})
    },
    hidePhone: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['add-antecedent', 'add-allergy', 'delete-antecedent', 'delete-allergy']);

const handleDeleteAntecedent = (item) => emit('delete-antecedent', item);
const handleDeleteAllergy = (item) => emit('delete-allergy', item);
</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-user text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Informations du patient</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Dossier personnel et antecedents</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button icon="pi pi-plus" label="Allergie" size="small" outlined class="rounded-xl" @click="emit('add-allergy')" />
                <Button icon="pi pi-plus" label="Antecedent" size="small" outlined class="rounded-xl" @click="emit('add-antecedent')" />
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-500/10">
                        <i class="pi pi-id-card text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Nom complet</p>
                        <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">{{ patient.prenom }} {{ patient.nom }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/10">
                        <i class="pi pi-calendar text-emerald-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Date de naissance</p>
                        <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ patient.dateNaissance || '—' }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/10">
                        <i class="pi pi-phone text-amber-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Telephone</p>
                        <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ hidePhone ? "Masqué par l'administrateur" : patient.telephone || '—' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-cyan-500/10">
                        <i class="pi pi-map-marker text-cyan-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Adresse</p>
                        <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ patient.adresse || '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100">Antecedents</h4>
                    <Button icon="pi pi-plus" text rounded severity="secondary" @click="emit('add-antecedent')" />
                </div>
                <div v-if="patient.antecedents?.length" class="space-y-2">
                    <div v-for="item in patient.antecedents" :key="item.id" class="flex items-start justify-between gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                        <div>
                            <Tag :value="item.type || 'Antecedent'" class="mb-1" />
                            <div class="text-sm text-surface-700 dark:text-surface-300">{{ item.description || '—' }}</div>
                        </div>
                        <Button icon="pi pi-trash" text rounded severity="danger" @click="handleDeleteAntecedent(item)" />
                    </div>
                </div>
                <div v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun antecedent.</div>
            </div>

            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100">Allergies</h4>
                    <Button icon="pi pi-plus" text rounded severity="secondary" @click="emit('add-allergy')" />
                </div>
                <div v-if="patient.allergies?.length" class="space-y-2">
                    <div v-for="item in patient.allergies" :key="item.id" class="flex items-start justify-between gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                        <div>
                            <Tag :value="item.libelle || 'Allergie'" severity="info" class="mb-1" />
                            <div class="text-sm text-surface-700 dark:text-surface-300">{{ item.description || '—' }}</div>
                        </div>
                        <Button icon="pi pi-trash" text rounded severity="danger" @click="handleDeleteAllergy(item)" />
                    </div>
                </div>
                <div v-else class="text-sm text-surface-500 dark:text-surface-400">Aucune allergie.</div>
            </div>
        </div>
    </div>
</template>
