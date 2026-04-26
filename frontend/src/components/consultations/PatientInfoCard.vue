<script setup>
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

function getSexeSeverity(sexe) {
            const severities = {
                'M': 'info',
                'F': 'help',
                'Masculin': 'info',
                'Féminin': 'help'
            };
            return severities[sexe] || 'info';
        }
</script>

<!-- PatientInfoCard.vue -->
<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-user text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Informations du patient</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Données personnelles</p>
                </div>
            </div>
            <Button icon="pi pi-pencil" severity="secondary" text rounded aria-label="Modifier" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30 hover:bg-surface-100 dark:hover:bg-surface-700/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-500/10 dark:bg-blue-500/20">
                        <i class="pi pi-id-card text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Nom complet</p>
                        <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ patient.prenom }} {{ patient.nom }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30 hover:bg-surface-100 dark:hover:bg-surface-700/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/20">
                        <i class="pi pi-calendar text-emerald-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Date de naissance</p>
                        <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ patient.dateNaissance || '—' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30 hover:bg-surface-100 dark:hover:bg-surface-700/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-500/10 dark:bg-purple-500/20">
                        <i class="pi pi-venus-mars text-purple-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Sexe</p>
                        <Tag :value="patient.sexe || '—'" 
                             :severity="getSexeSeverity(patient.sexe)"
                             class="px-3 py-1.5 rounded-full font-medium" />
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30 hover:bg-surface-100 dark:hover:bg-surface-700/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/10 dark:bg-amber-500/20">
                        <i class="pi pi-phone text-amber-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Téléphone</p>
                        <a v-if="!hidePhone && patient.telephone" :href="`tel:${patient.telephone}`" 
                           class="text-lg font-semibold text-surface-900 dark:text-surface-100 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                            {{ patient.telephone }}
                        </a>
                        <p v-else class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ hidePhone ? 'Masqué par l\'administrateur' : '—' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30 hover:bg-surface-100 dark:hover:bg-surface-700/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-cyan-500/10 dark:bg-cyan-500/20">
                        <i class="pi pi-map-marker text-cyan-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Adresse</p>
                        <p class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ patient.adresse || '—' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30 hover:bg-surface-100 dark:hover:bg-surface-700/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-red-500/10 dark:bg-red-500/20">
                        <i class="pi pi-info-circle text-red-500"></i>
                    </div>
                    <div>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Statut</p>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                                Patient actif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6 pt-4 border-t border-surface-100 dark:border-surface-700">
            <div class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">Actions rapides</div>
            <div class="flex flex-wrap gap-2">
                <Button icon="pi pi-envelope" label="Contacter" severity="secondary" outlined size="small"
                    class="rounded-xl px-4 py-2 hover:shadow-sm" />
                <Button icon="pi pi-file-pdf" label="Dossier médical" severity="secondary" outlined size="small"
                    class="rounded-xl px-4 py-2 hover:shadow-sm" />
                <Button icon="pi pi-calendar-plus" label="Nouveau RDV" severity="secondary" outlined size="small"
                    class="rounded-xl px-4 py-2 hover:shadow-sm" />
            </div>
        </div>
    </div>
</template>
 