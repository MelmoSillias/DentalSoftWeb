<script setup>
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const props = defineProps({
    rdvs: {
        type: Array,
        default: () => []
    },
    paiements: {
        type: Array,
        default: () => []
    },
    factures: {
        type: Array,
        default: () => []
    },
    consultations: {
        type: Array,
        default: () => []
    },
    showConsultations: {
        type: Boolean,
        default: false
    }
});

const tabs = computed(() => {
    const base = [
        { id: 'rdv', label: 'Rendez-vous', icon: 'pi pi-calendar' },
        { id: 'paiements', label: 'Paiements', icon: 'pi pi-credit-card' },
        { id: 'factures', label: 'Factures', icon: 'pi pi-file' }
    ];

    if (props.showConsultations) {
        base.push({ id: 'consultations', label: 'Consultations', icon: 'pi pi-file-medical' });
    }

    return base;
});
const activeTab = ref('rdv');

const totalPaye = computed(() =>
    props.paiements
        .filter(p => getPaiementStatut(p) === 'Payé')
        .reduce((sum, p) => sum + getPaiementMontant(p), 0)
);

const totalAttente = computed(() =>
    props.paiements
        .filter(p => getPaiementStatut(p) === 'En attente')
        .reduce((sum, p) => sum + getPaiementMontant(p), 0)
);

const totalImpaye = computed(() =>
    props.paiements
        .filter(p => getPaiementStatut(p) === 'Impayé')
        .reduce((sum, p) => sum + getPaiementMontant(p), 0)
);

function formatDate(date) {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function getRdvStatus(rdv) {
    return rdv.statut || rdv.status || '—';
}

function getRdvLabel(rdv) {
    return rdv.type || rdv.motif || rdv.salle || 'Rendez-vous';
}

function getRdvDate(rdv) {
    return rdv.dateRdv || rdv.date || rdv.dateCreation || null;
}

function getRdvMedecin(rdv) {
    return rdv.medecinNom || rdv.medecin || '--';
}

function getPaiementLabel(paiement) {
    return paiement.motif || paiement.libelle || paiement.designation || `Paiement #${paiement.id ?? ''}`.trim();
}

function getPaiementDate(paiement) {
    return paiement.date || paiement.datePaiement || paiement.createdAt || null;
}

function getPaiementMontant(paiement) {
    return Number(paiement.montant ?? paiement.amount ?? 0);
}

function getPaiementStatut(paiement) {
    return paiement.statut || paiement.status || '—';
}

function getPaiementMode(paiement) {
    return paiement.mode || paiement.modePaiement || paiement.methode || '—';
}

function getFactureLabel(facture) {
    return facture.libelle || facture.designation || facture.motif || `Facture #${facture.id ?? ''}`.trim();
}

function getFactureDate(facture) {
    return facture.date || facture.dateCreation || facture.createdAt || null;
}

function getFactureMontant(facture) {
    return Number(facture.montant ?? facture.total ?? 0);
}

function getFactureStatut(facture) {
    return facture.statut || facture.status || '—';
}

function getConsultationDate(consultation) {
    return consultation.date || consultation.createdAt || consultation.created_at || null;
}

function getConsultationMedecin(consultation) {
    return consultation.medecin || consultation.medecinNom || '—';
}

function getConsultationMontant(consultation) {
    return Number(consultation.factureMontant ?? consultation.montant ?? 0);
}

function getConsultationStatut(consultation) {
    return consultation.statut === 1 || consultation.state === 1 ? 'Clôturée' : 'En cours';
}

function getConsultationStatusSeverity(stat) {
    return stat === 'Clôturée' ? 'success' : 'warning';
}

function getRDVStatusSeverity(status) {
    const severities = {
        'Terminé': 'success',
        'Confirmé': 'info',
        'Planifié': 'warning',
        'Annulé': 'danger',
        'Reporté': 'secondary'
    };
    return severities[status] || 'info';
}

function getRDVStatusIcon(status) {
    const icons = {
        'Terminé': 'pi pi-check-circle',
        'Confirmé': 'pi pi-calendar-check',
        'Planifié': 'pi pi-calendar-plus',
        'Annulé': 'pi pi-times-circle',
        'Reporté': 'pi pi-calendar-times'
    };
    return icons[status] || 'pi pi-calendar';
}

function getRDVStatusColor(status) {
    const colors = {
        'Terminé': { bg: 'bg-emerald-100 dark:bg-emerald-900/30', text: 'text-emerald-600 dark:text-emerald-400' },
        'Confirmé': { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-600 dark:text-blue-400' },
        'Planifié': { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-600 dark:text-amber-400' },
        'Annulé': { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-600 dark:text-red-400' },
        'Reporté': { bg: 'bg-surface-100 dark:bg-surface-700', text: 'text-surface-600 dark:text-surface-400' }
    };
    return colors[status] || { bg: 'bg-surface-100', text: 'text-surface-600' };
}
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="border-b border-surface-200/50 dark:border-surface-700/50">
            <div class="flex">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="activeTab = tab.id"
                    :class="[
                        'flex-1 px-5 py-4 text-sm font-medium transition-all duration-300 border-b-2',
                        activeTab === tab.id
                            ? 'text-primary-600 dark:text-primary-400 border-primary-500 bg-primary-50/30 dark:bg-primary-900/20'
                            : 'text-surface-600 dark:text-surface-400 border-transparent hover:text-surface-900 dark:hover:text-surface-100 hover:bg-surface-50/50 dark:hover:bg-surface-700/30'
                    ]"
                >
                    <div class="flex items-center justify-center gap-2">
                        <i :class="tab.icon"></i>
                        <span>{{ tab.label }}</span>
                    </div>
                </button>
            </div>
        </div>
        <div class="p-5">
            <div v-if="activeTab === 'rdv'" class="space-y-4">
                <div v-for="rdv in rdvs" :key="rdv.id" class="p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-primary-300/50 dark:hover:border-primary-700/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div :class="[
                                'p-2 rounded-lg',
                                getRDVStatusColor(getRdvStatus(rdv)).bg
                            ]">
                                <IconField :class="getRDVStatusIcon(getRdvStatus(rdv)) + ' ' + getRDVStatusColor(getRdvStatus(rdv)).text">
                                    <InputIcon :class="getRDVStatusIcon(getRdvStatus(rdv))" />
                                </IconField>
                            </div>
                            <div>
                                <div class="font-semibold text-surface-900 dark:text-surface-100">{{ getRdvLabel(rdv) }}</div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">
                                    {{ formatDate(getRdvDate(rdv)) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <Tag :value="getRdvStatus(rdv)" :severity="getRDVStatusSeverity(getRdvStatus(rdv))" class="px-3 py-1 rounded-full" />
                            <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ getRdvMedecin(rdv) }}</div>
                        </div>
                    </div>
                    <div v-if="rdv.notes" class="mt-3 pt-3 border-t border-surface-200/50 dark:border-surface-700/50">
                        <p class="text-sm text-surface-700 dark:text-surface-300">{{ rdv.notes }}</p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'paiements'" class="space-y-4">
                <div v-for="paiement in paiements" :key="paiement.id" class="p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-surface-300/50 dark:hover:border-surface-600/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                <i class="pi pi-check-circle text-emerald-600 dark:text-emerald-400"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-surface-900 dark:text-surface-100">{{ getPaiementLabel(paiement) }}</div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">
                                    {{ formatDate(getPaiementDate(paiement)) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                {{ getPaiementMontant(paiement) }} F CFA
                            </div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">{{ getPaiementMode(paiement) }}</div>
                        </div>
                    </div>
                    <div v-if="paiement.notes" class="mt-3 pt-3 border-t border-surface-200/50 dark:border-surface-700/50">
                        <p class="text-sm text-surface-700 dark:text-surface-300">{{ paiement.notes }}</p>
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50">
                    <div class="grid grid-cols-3 gap-4">
                        <div class="text-center p-3 rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 border border-emerald-200/50 dark:border-emerald-800/50">
                            <div class="text-sm text-emerald-700 dark:text-emerald-300">Total payé</div>
                            <div class="text-xl font-bold text-emerald-900 dark:text-emerald-100">{{ totalPaye }} F CFA</div>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 border border-amber-200/50 dark:border-amber-800/50">
                            <div class="text-sm text-amber-700 dark:text-amber-300">En attente</div>
                            <div class="text-xl font-bold text-amber-900 dark:text-amber-100">{{ totalAttente }} F CFA</div>
                        </div>
                        <div class="text-center p-3 rounded-xl bg-gradient-to-br from-red-50 to-red-100/50 dark:from-red-900/20 dark:to-red-800/20 border border-red-200/50 dark:border-red-800/50">
                            <div class="text-sm text-red-700 dark:text-red-300">Impayés</div>
                            <div class="text-xl font-bold text-red-900 dark:text-red-100">{{ totalImpaye }} F CFA</div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'factures'" class="space-y-4">
                <div v-for="facture in factures" :key="facture.id" class="p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-surface-300/50 dark:hover:border-surface-600/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-surface-100 dark:bg-surface-700">
                                <i class="pi pi-file text-surface-600 dark:text-surface-300"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-surface-900 dark:text-surface-100">{{ getFactureLabel(facture) }}</div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">
                                    {{ formatDate(getFactureDate(facture)) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-surface-900 dark:text-surface-100">
                                {{ getFactureMontant(facture) }} F CFA
                            </div>
                            <div class="text-sm text-surface-600 dark:text-surface-400">{{ getFactureStatut(facture) }}</div>
                        </div>
                    </div>
                </div>
                <p v-if="!factures.length" class="text-sm text-surface-500 dark:text-surface-400">Aucune facture disponible.</p>
            </div>

            <div v-if="activeTab === 'consultations'" class="space-y-4">
                <div v-for="consultation in consultations" :key="consultation.id" class="p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-surface-300/50 dark:hover:border-surface-600/50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-surface-100 dark:bg-surface-700">
                                <i class="pi pi-file-medical text-surface-600 dark:text-surface-300"></i>
                            </div>
                            <div>
                                <div class="font-semibold text-surface-900 dark:text-surface-100">
                                    Consultation #{{ consultation.id }}
                                </div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">
                                    {{ formatDate(getConsultationDate(consultation)) }}
                                </div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">
                                    {{ getConsultationMedecin(consultation) }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <Tag :value="getConsultationStatut(consultation)" :severity="getConsultationStatusSeverity(getConsultationStatut(consultation))" class="px-3 py-1 rounded-full" />
                            <div class="text-lg font-bold text-surface-900 dark:text-surface-100 mt-2">
                                {{ getConsultationMontant(consultation) }} F CFA
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="!consultations.length" class="text-sm text-surface-500 dark:text-surface-400">Aucune consultation disponible.</p>
            </div>
        </div>
    </div>
</template>
