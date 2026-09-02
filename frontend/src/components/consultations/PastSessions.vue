<script setup>
import Accordion from 'primevue/accordion';
import AccordionContent from 'primevue/accordioncontent';
import AccordionHeader from 'primevue/accordionheader';
import AccordionPanel from 'primevue/accordionpanel';
import Tag from 'primevue/tag';
import { computed } from 'vue';
import Fieldset from 'primevue/fieldset';

const props = defineProps({
    sessions: {
        type: Array,
        default: () => []
    }
});

const formatCurrency = (value) => {
    const amount = Number(value);
    const safeAmount = Number.isFinite(amount) ? amount : 0;
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'XOF'
    }).format(safeAmount);
};

const getSessionSeverity = (statut) => {
    const severities = {
        Terminé: 'success',
        Annulé: 'danger',
        Reporté: 'warning',
        'En cours': 'info'
    };
    return severities[statut] || 'info';
};

const totalActes = computed(() => (props.sessions || []).reduce((sum, session) => sum + (session.actes?.length || 0), 0));

const totalMontant = computed(() =>
    (props.sessions || []).reduce((sum, session) => {
        const actesTotal = (session.actes || []).reduce((acteSum, acte) => {
            const value = Number(acte.montantTotal ?? acte.total ?? 0);
            return acteSum + (Number.isFinite(value) ? value : 0);
        }, 0);
        const sessionTotal = actesTotal || Number(session.total ?? 0) || 0;
        return sum + (Number.isFinite(sessionTotal) ? sessionTotal : 0);
    }, 0)
);

const getSessionOrdonnances = (session) => {
    const list = session?.ordonnances || session?.prescriptions || session?.ordonnance || [];
    return Array.isArray(list) ? list : [];
};
</script>

<!-- PastSessions.vue -->
<template>
    <div class="overflow-hidden rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-history text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Séances passées</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Historique des consultations précédentes</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button icon="pi pi-download" severity="secondary" text rounded aria-label="Exporter" />
                <Button icon="pi pi-filter" severity="secondary" text rounded aria-label="Filtrer" />
            </div>
        </div>

        <div v-if="sessions.length" class="space-y-4">
            <Accordion multiple :pt="{ root: 'space-y-4' }">
                <AccordionPanel
                    v-for="(session, index) in sessions"
                    :key="session.id ?? index"
                    :value="String(session.id ?? index)"
                    class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/60 overflow-hidden"
                >
                    <AccordionHeader class="px-5 py-4 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-500/10 dark:bg-primary-500/20">
                                    <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                                        {{ index + 1 }}
                                    </span>
                                </div>
                                <div class="text-left">
                                    <div class="font-semibold text-surface-900 dark:text-surface-100">Séance du {{ session.date || '' }}</div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Tag :value="session.medecin || '—'" severity="info" class="px-2 py-1 text-xs rounded-full" />
                                        <span class="text-xs text-surface-500 dark:text-surface-400">
                                            {{ session.duration || '1h' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <Badge v-if="session.statut" :value="session.statut" :severity="getSessionSeverity(session.statut)" class="px-3 py-1 rounded-full font-medium" />
                        </div>
                    </AccordionHeader>

                    <AccordionContent>
                        <div class="space-y-4 px-5 py-4">
                            <!-- Session Details -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="pi pi-id-card text-surface-400"></i>
                                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">Médecin</span>
                                    </div>
                                    <p class="font-semibold text-surface-900 dark:text-surface-100">{{ session.medecin || '—' }}</p>
                                </div>

                                <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="pi pi-user text-surface-400"></i>
                                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">Aide soignant(e)</span>
                                    </div>
                                    <p class="font-semibold text-surface-900 dark:text-surface-100">{{ session.infirmier || '—' }}</p>
                                </div>

                                <div class="p-3 rounded-xl bg-surface-50 dark:bg-surface-700/30">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="pi pi-building text-surface-400"></i>
                                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">Salle</span>
                                    </div>
                                    <p class="font-semibold text-surface-900 dark:text-surface-100">{{ session.salle || '—' }}</p>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <Fieldset v-if="session.noteSeance" legend="Notes de la séance" class="mb-4">
                                    <p class="text-sm text-surface-700 dark:text-surface-300 whitespace-pre-wrap">
                                        {{ session.noteSeance || 'Aucune note pour cette séance.' }}
                                    </p>
                                </Fieldset>
                            </div>

                            <!-- Actes Table -->
                            <div v-if="session.actes && session.actes.length">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <h6 class="font-semibold text-surface-900 dark:text-surface-100"><i class="pi pi-list-check text-primary-500"></i> Actes réalisés</h6>
                                    </div>
                                    <Badge :value="session.actes.length" severity="info" class="px-3 py-1 rounded-full" />
                                </div>

                                <div class="rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700">
                                    <table class="w-full">
                                        <thead class="bg-surface-50 dark:bg-surface-800">
                                            <tr>
                                                <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300 text-sm">Type</th>
                                                <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300 text-sm">Dent</th>
                                                <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300 text-sm">Description</th>
                                                <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300 text-sm">Qté</th>
                                                <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300 text-sm">Prix unit.</th>
                                                <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300 text-sm">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                            <tr v-for="(acte, idx) in session.actes" :key="idx" class="hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors">
                                                <td class="p-3">
                                                    <Tag :value="acte.type" severity="secondary" class="px-2 py-1 text-xs rounded-full" />
                                                </td>
                                                <td class="p-3 font-medium text-surface-900 dark:text-surface-100">
                                                    {{ acte.dent || '—' }}
                                                </td>
                                                <td class="p-3 text-surface-700 dark:text-surface-300">
                                                    {{ acte.description || '—' }}
                                                </td>
                                                <td class="p-3 text-right font-medium text-surface-900 dark:text-surface-100">
                                                    {{ acte.quantite || 0 }}
                                                </td>
                                                <td class="p-3 text-right font-medium text-surface-900 dark:text-surface-100">
                                                    {{ formatCurrency(acte.prix || 0) }}
                                                </td>
                                                <td class="p-3 text-right font-bold text-primary-600 dark:text-primary-400">
                                                    {{ formatCurrency((acte.quantite || 0) * (acte.prix || 0)) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot v-if="session.total" class="bg-surface-50 dark:bg-surface-800">
                                            <tr>
                                                <td colspan="5" class="p-3 text-right font-semibold text-surface-700 dark:text-surface-300">Total de la séance</td>
                                                <td class="p-3 text-right font-bold text-lg text-primary-600 dark:text-primary-400">
                                                    {{ formatCurrency(session.total) }}
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                            <!-- Ordonnances -->
                            <div v-if="getSessionOrdonnances(session).length">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-clipboard text-purple-500"></i>
                                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Prescriptions</h4>
                                    </div>
                                    <Badge :value="getSessionOrdonnances(session).length" severity="info" class="px-3 py-1 rounded-full" />
                                </div>

                                <div class="space-y-3">
                                    <div v-for="ordo in getSessionOrdonnances(session)" :key="ordo.id || ordo.date" class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="font-medium text-surface-900 dark:text-surface-100">Ordonnance du {{ ordo.date || '—' }}</div>
                                            <Tag :value="ordo.medecinNom || ordo.medecin || '—'" severity="info" class="px-2 py-1 text-xs rounded-full" />
                                        </div>

                                        <div v-if="ordo.note" class="mt-2 text-sm text-surface-600 dark:text-surface-300">
                                            {{ ordo.note }}
                                        </div>

                                        <div v-if="ordo.lignes && ordo.lignes.length" class="mt-3 rounded-lg border border-surface-200 dark:border-surface-700 overflow-hidden">
                                            <table class="w-full">
                                                <thead class="bg-surface-50 dark:bg-surface-800">
                                                    <tr>
                                                        <th class="p-2 text-left text-xs font-semibold text-surface-700 dark:text-surface-300">Médicament</th>
                                                        <th class="p-2 text-left text-xs font-semibold text-surface-700 dark:text-surface-300">Posologie</th>
                                                        <th class="p-2 text-left text-xs font-semibold text-surface-700 dark:text-surface-300">Fréquence</th>
                                                        <th class="p-2 text-left text-xs font-semibold text-surface-700 dark:text-surface-300">Durée</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-surface-100 dark:divide-surface-800">
                                                    <tr v-for="(ligne, lidx) in ordo.lignes" :key="lidx">
                                                        <td class="p-2 text-sm text-surface-900 dark:text-surface-100">{{ ligne.medicament || '—' }}</td>
                                                        <td class="p-2 text-sm text-surface-700 dark:text-surface-300">{{ ligne.posologie || '—' }}</td>
                                                        <td class="p-2 text-sm text-surface-700 dark:text-surface-300">{{ ligne.frequence || '—' }}</td>
                                                        <td class="p-2 text-sm text-surface-700 dark:text-surface-300">{{ ligne.duree || '—' }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div v-else class="mt-3 text-sm text-surface-500 dark:text-surface-400">Aucune ligne de prescription.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </AccordionContent>
                </AccordionPanel>
            </Accordion>
        </div>

        <div v-else class="text-center py-12">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-surface-100 dark:bg-surface-800 mb-4">
                <i class="pi pi-inbox text-3xl text-surface-400"></i>
            </div>
            <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300 mb-2">Aucune séance passée</h4>
            <p class="text-surface-600 dark:text-surface-400 mb-6 max-w-md mx-auto">Aucune séance n'a été clôturée pour ce patient.</p>
        </div>

        <!-- Summary -->
        <div v-if="sessions.length" class="mt-6 pt-4 border-t border-surface-100 dark:border-surface-700">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20">
                    <div class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-1">Total séances</div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ sessions.length }}</div>
                </div>
                <div class="p-4 rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                    <div class="text-sm font-medium text-emerald-700 dark:text-emerald-300 mb-1">Actes réalisés</div>
                    <div class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">
                        {{ totalActes }}
                    </div>
                </div>
                <div class="p-4 rounded-xl bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20">
                    <div class="text-sm font-medium text-purple-700 dark:text-purple-300 mb-1">Montant total</div>
                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">
                        {{ formatCurrency(totalMontant) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
