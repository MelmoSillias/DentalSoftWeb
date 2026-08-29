<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <!-- Header de la fiche -->
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                        <i class="pi pi-folder-open text-primary-500"></i>
                        Fiche Médicale {{ positionLabel || '' }}
                    </h3>
                    <p class="text-sm text-surface-600 dark:text-surface-300 mt-1">
                        Créée le {{ formatDate(fiche.date || fiche.dateCreation) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Tag value="V1" severity="info" class="px-3 py-1.5 rounded-full font-medium" />
                    <Tag :value="fiche.type || 'Consultation'" :severity="getTypeSeverity(fiche.type)" class="px-3 py-1.5 rounded-full font-medium" />
                </div>
            </div>
        </div>

        <!-- Contenu de la fiche - Sections -->
        <div class="p-5">
            <!-- Navigation des sections -->
            <div class="mb-6">
                <div class="flex flex-wrap gap-2 border-b border-surface-200/50 dark:border-surface-700/50 pb-4">
                    <button
                        v-for="(section, index) in sections"
                        :key="index"
                        @click="activeSection = index"
                        :class="[
                            'px-4 py-2 rounded-lg text-sm button-sm font-medium transition-all duration-300',
                            activeSection === index
                                ? 'bg-primary-500 text-white shadow-sm'
                                : 'text-surface-600 dark:text-surface-400 hover:text-surface-900 dark:hover:text-surface-100 hover:bg-surface-100 dark:hover:bg-surface-700'
                        ]"
                    >
                        <div class="flex items-center gap-2">
                            <i :class="section.icon"></i>
                            <span class="hidden sm:hidden">{{ section.title }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Contenu des sections -->
            <div class="space-y-6">
                <!-- Section 1: Motif -->
                <div v-if="activeSection === 0" class="animate-fadeIn">
                    <h4 class="text-md font-semibold text-surface-900 dark:text-surface-100 mb-4 flex items-center gap-2">
                        <i class="pi pi-info-circle text-primary-500"></i>
                        Motif
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <span class="text-surface-600 dark:text-surface-400">Motif de consultation</span>
                                <span class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.motif || '--' }}</span>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-surface-600 dark:text-surface-400 text-sm">Anamnese</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.histoireMaladie || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-surface-600 dark:text-surface-400 text-sm">Soins antérieurs</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.soinsAnterieurs || '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Examens -->
                <div v-if="activeSection === 1" class="animate-fadeIn">
                    <h4 class="text-md font-semibold text-surface-900 dark:text-surface-100 mb-4 flex items-center gap-2">
                        <i class="pi pi-search text-primary-500"></i>
                        Examens
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Exo Inspection</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.exoInspection || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Exo Palpation</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.exoPalpation || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Endo Inspection</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.endoInspection || '—' }}</div>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Endo Palpation</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.endoPalpation || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Occlusion</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.occlusion || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Examen parodontal</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.examenParodontal || '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 p-4 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <div class="text-sm text-surface-600 dark:text-surface-400 mb-2">Diagnostic</div>
                        <p class="text-surface-700 dark:text-surface-300">{{ fiche.diagnostic || 'Aucun diagnostic' }}</p>
                    </div>
                    <div v-if="examensEntries.length" class="mt-4">
                        <div class="text-sm text-surface-600 dark:text-surface-400 font-medium mb-2">Tooths check</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div v-for="([key, value], idx) in examensEntries" :key="idx"
                                class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <span class="text-surface-600 dark:text-surface-400">{{ key }}</span>
                                <span class="font-medium text-surface-900 dark:text-surface-100">{{ value }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Traitements -->
                <div v-if="activeSection === 2" class="animate-fadeIn">
                    <h4 class="text-md font-semibold text-surface-900 dark:text-surface-100 mb-4 flex items-center gap-2">
                        <i class="pi pi-box text-primary-500"></i>
                        Traitements
                    </h4>
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Traitement d'urgence</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.traitementUrgence || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Traitement dentaire</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.traitementDentaire || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Traitement parodontal</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.traitementParodontal || '—' }}</div>
                            </div>
                            <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400">Traitement orthodontique</div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.traitementOrthodontique || '—' }}</div>
                            </div>
                        </div>
                        <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                            <div class="text-sm text-surface-600 dark:text-surface-400">Autres</div>
                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.autres || '—' }}</div>
                        </div>
                        <div v-if="fiche.documents?.length" class="space-y-2">
                            <div class="text-sm text-surface-600 dark:text-surface-400 font-medium">Documents médicaux</div>
                            <div v-for="(doc, idx) in fiche.documents" :key="idx"
                                class="p-3 rounded-xl border border-surface-200/50 dark:border-surface-700/50">
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ doc.libelle || 'Document' }}</div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">{{ doc.description || '—' }}</div>
                                <div class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ doc.dateDossier || '--' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Devis -->
                <div v-if="activeSection === 3" class="animate-fadeIn">
                    <h4 class="text-md font-semibold text-surface-900 dark:text-surface-100 mb-4 flex items-center gap-2">
                        <i class="pi pi-file text-primary-500"></i>
                        Devis
                    </h4>
                    <div v-if="fiche.devis" class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                            <span class="text-surface-600 dark:text-surface-400">Date du devis</span>
                            <span class="font-medium text-surface-900 dark:text-surface-100">{{ fiche.devis.date || '--' }}</span>
                        </div>
                        <div class="space-y-2">
                            <div class="text-sm text-surface-600 dark:text-surface-400 font-medium">Contenus</div>
                            <div v-for="(item, idx) in (fiche.devis.contenus || [])" :key="idx"
                                class="flex items-center justify-between p-3 rounded-xl border border-surface-200/50 dark:border-surface-700/50">
                                <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.designation || 'Service' }}</div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">{{ item.qte || 1 }} x {{ item.montant || 0 }}</div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun devis enregistré.</p>
                </div>

                <!-- Section 5: Séances passées -->
                <div v-if="activeSection === 4" class="animate-fadeIn">
                    <h4 class="text-md font-semibold text-surface-900 dark:text-surface-100 mb-4 flex items-center gap-2">
                        <i class="pi pi-history text-primary-500"></i>
                        Séances passées
                    </h4>
                    <div v-if="fiche.consultations?.length" class="space-y-4">
                        <div v-for="(seance, idx) in fiche.consultations" :key="idx"
                            class="p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-surface-900 dark:text-surface-100">
                                        Séance du {{ formatDate(seance.date) }}
                                    </div>
                                    <div class="text-sm text-surface-600 dark:text-surface-400">
                                        {{ seance.medecin || '—' }} • {{ seance.infirmier || '—' }} • {{ seance.salle || '—' }}
                                    </div>
                                </div>
                                <Tag :value="seance.noteSeance || '—'" severity="info" class="px-3 py-1 rounded-full" />
                            </div>
                            <div v-if="seance.actes?.length" class="mt-3 pt-3 border-t border-surface-200/50 dark:border-surface-700/50">
                                <div class="text-sm text-surface-600 dark:text-surface-400 font-medium mb-2">Actes</div>
                                <div class="space-y-2">
                                    <div v-for="(acte, aidx) in seance.actes" :key="aidx"
                                        class="flex items-center justify-between text-sm text-surface-700 dark:text-surface-300">
                                        <span>{{ acte.dent }} • {{ acte.type }} • {{ acte.description || '—' }}</span>
                                        <span>{{ acte.quantite || 1 }} x {{ acte.prix || 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucune séance précédente.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-900/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button icon="pi pi-print" label="Imprimer" severity="secondary" outlined size="small" @click="$emit('print')" :pt="{ label: { class: 'hidden sm:inline' } }" /> 
                </div>
                <div class="text-sm text-surface-600 dark:text-surface-400">
                    Dernière modification : {{ formatDate(fiche.lastModified || fiche.date || fiche.dateCreation) }}
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'FicheMedical',
    emits: ['print'],
    props: {
        fiche: {
            type: Object,
            required: true
        },
        positionLabel: {
            type: String,
            default: ''
        }
    },
    data() {
        return {
            activeSection: 0,
            sections: [
                { title: 'Motif', icon: 'pi pi-info-circle' },
                { title: 'Examens', icon: 'pi pi-search' },
                { title: 'Traitements', icon: 'pi pi-cog' },
                { title: 'Devis', icon: 'pi pi-file' },
                { title: 'Séances passées', icon: 'pi pi-history' }
            ]
        };
    },
    computed: {
        examensEntries() {
            return Object.entries(this.fiche.examens || {});
        }
    },
    methods: {
        formatDate(date) {
            if (!date) return '--';
            return new Date(date).toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        getTypeSeverity(type) {
            const severities = {
                'Consultation': 'info',
                'Urgence': 'danger',
                'Suivi': 'success',
                'Spécialiste': 'warning',
                'Hospitalisation': 'help'
            };
            return severities[type] || 'info';
        }
    }
};
</script>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}
</style>