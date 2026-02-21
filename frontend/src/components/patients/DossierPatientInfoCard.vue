<script setup>
import Button from 'primevue/button';

const props = defineProps({
    patient: {
        type: Object,
        required: true
    }
});

const emit = defineEmits([
    'print-dossier',
    'edit',
    'new-rdv',
    'add-antecedent',
    'add-allergy',
    'delete-antecedent',
    'delete-allergy'
]);
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                <i class="pi pi-user text-primary-500"></i>
                Informations Patient
            </h3>
        </div>
        <div class="p-5">
            <div class="flex flex-col items-center mb-6">
                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white text-3xl font-bold mb-4 shadow-lg">
                    {{ patient.initials }}
                </div>
                <h2 class="text-xl font-bold text-surface-900 dark:text-surface-100">{{ patient.nom }} {{ patient.prenom }}</h2>
                <p class="text-surface-600 dark:text-surface-400">{{ patient.numeroDossier }}</p>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Date de naissance</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.dateNaissance }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Lieu de naissance</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.lieuNaissance || '--' }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Âge</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.age }} ans</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Sexe</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.sexe }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Groupe sanguin</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.groupeSanguin }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Profession</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.profession || '--' }}</span>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50">
                <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">Contact</h4>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300">
                        <i class="pi pi-phone text-surface-400"></i>
                        {{ patient.telephone }}
                    </div>
                    <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300">
                        <i class="pi pi-envelope text-surface-400"></i>
                        {{ patient.email }}
                    </div>
                    <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300">
                        <i class="pi pi-map-marker text-surface-400"></i>
                        {{ patient.adresse }}
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300">Antécédents médicaux</h4>
                    <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="emit('add-antecedent')" />
                </div>
                <div v-if="patient.antecedents?.length" class="space-y-2">
                    <div v-for="(item, idx) in patient.antecedents" :key="idx"
                        class="flex items-start justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <div>
                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.type || 'Antécédent' }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-300">{{ item.description || '—' }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="text-xs text-surface-500 dark:text-surface-400">{{ item.date || item.dateEnregistrement || '--' }}</div>
                            <Button icon="pi pi-trash" severity="danger" text rounded @click="emit('delete-antecedent', item)" />
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun antécédent renseigné.</p>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300">Allergies</h4>
                    <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="emit('add-allergy')" />
                </div>
                <div v-if="patient.allergies?.length" class="space-y-2">
                    <div v-for="(item, idx) in patient.allergies" :key="idx"
                        class="flex items-start justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <div>
                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.libelle || 'Allergie' }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-300">{{ item.description || '—' }}</div>
                        </div>
                        <Button icon="pi pi-trash" severity="danger" text rounded @click="emit('delete-allergy', item)" />
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucune allergie renseignée.</p>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50">
                <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">Contact d'urgence</h4>
                <div v-if="patient.contactUrgence" class="space-y-2">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Nom</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.contactUrgence.nom || '--' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Lien</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.contactUrgence.lienParente || '--' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Téléphone</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.contactUrgence.telephone || '--' }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun contact d'urgence renseigné.</p>
            </div>
        </div> 
    
        <!-- MOBILE (Visible de 0px à 640px, caché après) -->
        <div class="px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-900/50">
        
            <!-- DESKTOP (Caché sur mobile, visible à partir de sm: 640px) -->
            <div class="hidden sm:flex flex-wrap gap-2">
                <Button icon="pi pi-print" label="Imprimer dossier" severity="secondary" outlined class="flex-1" @click="emit('print-dossier')" />
                <Button icon="pi pi-pencil" label="Modifier" severity="secondary" outlined class="flex-1" @click="emit('edit')" />
                <Button icon="pi pi-plus" label="Nouveau RDV" severity="primary" class="flex-1 bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="emit('new-rdv')" />
            </div>

            <!-- MOBILE (Visible sur mobile, caché dès 640px) -->
            <div class="flex sm:hidden flex-wrap gap-2">
                <Button icon="pi pi-print"  severity="secondary" outlined class="flex-1" @click="emit('print-dossier')" />
                <Button icon="pi pi-pencil"  severity="secondary" outlined class="flex-1" @click="emit('edit')" />
                <Button icon="pi pi-plus" label="RDV" severity="primary" class="flex-1 bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="emit('new-rdv')" />
            </div>
            
        </div> 

    </div>
</template>
