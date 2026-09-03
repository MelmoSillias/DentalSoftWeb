<script setup>
import { computed } from 'vue';
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    lot: { type: Object, default: null },
    title: { type: String, default: 'Bordereau de lot assurance' },
    logoSrc: { type: String, default: logoImg }
});

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const statutLabel = (statut) => {
    const map = {
        ouvert: 'Ouvert',
        envoye: 'Envoyé',
        confirme: 'Confirmé',
        partiellement_rembourse: 'Partiellement remboursé',
        rembourse: 'Remboursé',
        recouvre: 'Remboursé'
    };
    return map[statut] || statut || '—';
};

const factures = computed(() => (Array.isArray(props.lot?.factures) ? props.lot.factures : []));

const totals = computed(() => {
    let montantTotal = 0;
    let montantAssurance = 0;
    let montantPatient = 0;
    for (const f of factures.value) {
        montantTotal += Number(f?.montantTotal || 0);
        montantAssurance += Number(f?.montantAssurance || 0);
        montantPatient += Number(f?.montantPatient || 0);
    }
    return { montantTotal, montantAssurance, montantPatient };
});

const printDate = computed(() => new Date());
</script>

<template>
    <PrintA4Page :logo-src="logoSrc" orientation="landscape">
        <template #header>
            <PrintDocumentHeader :title="title" :doc-id="lot?.id" :date="printDate" />
        </template>

        <div class="lot-meta">
            <div>
                <span class="label">Assurance</span>
                <strong>{{ lot?.assurance?.nom || '—' }}</strong>
                <span v-if="lot?.assurance?.code" class="muted"> ({{ lot.assurance.code }})</span>
            </div>
            <div>
                <span class="label">Lot</span>
                <strong>{{ lot?.description || `Lot #${lot?.id}` }}</strong>
            </div>
            <div>
                <span class="label">Période</span>
                <strong>{{ lot?.dateDebut || '—' }} → {{ lot?.dateFin || '—' }}</strong>
            </div>
            <div>
                <span class="label">Statut</span>
                <strong>{{ statutLabel(lot?.statut) }}</strong>
            </div>
            <div>
                <span class="label">Nb factures</span>
                <strong>{{ factures.length || lot?.nbFactures || 0 }}</strong>
            </div>
            <div>
                <span class="label">Montant assurance</span>
                <strong>{{ formatFcfa(lot?.montantTotal ?? totals.montantAssurance) }}</strong>
            </div>
        </div>

        <table class="print-doc-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Infos assuré</th>
                    <th style="text-align: right">Total</th>
                    <th style="text-align: right">Part assurance</th>
                    <th style="text-align: right">Part patient</th>
                    <th style="text-align: center">Taux</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="facture in factures" :key="facture.id">
                    <td>{{ facture?.dateFacture?.slice?.(0, 10) || '—' }}</td>
                    <td>
                        <div>{{ facture?.patient || '—' }}</div>
                        <div v-if="facture?.telephone" class="muted small">{{ facture.telephone }}</div>
                    </td>
                    <td class="assure-cell">{{ facture?.assureResume || '—' }}</td>
                    <td style="text-align: right">{{ formatFcfa(facture?.montantTotal) }}</td>
                    <td style="text-align: right">{{ formatFcfa(facture?.montantAssurance) }}</td>
                    <td style="text-align: right">{{ formatFcfa(facture?.montantPatient) }}</td>
                    <td style="text-align: center">{{ Number(facture?.tauxCouverture || 0) }} %</td>
                </tr>
                <tr v-if="!factures.length">
                    <td colspan="7" class="empty">Aucune facture dans ce lot.</td>
                </tr>
            </tbody>
            <tfoot v-if="factures.length">
                <tr>
                    <td colspan="3" style="text-align: right; font-weight: 700">Totaux</td>
                    <td style="text-align: right; font-weight: 700">{{ formatFcfa(totals.montantTotal) }}</td>
                    <td style="text-align: right; font-weight: 700">{{ formatFcfa(totals.montantAssurance) }}</td>
                    <td style="text-align: right; font-weight: 700">{{ formatFcfa(totals.montantPatient) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </PrintA4Page>
</template>

<style scoped>
.lot-meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 6px 14px;
    margin-bottom: 8mm;
    font-size: 10pt;
}

.lot-meta .label {
    display: block;
    font-size: 8.5pt;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #6b7280;
    margin-bottom: 1px;
}

.lot-meta strong {
    color: #111827;
    font-weight: 600;
}

.muted {
    color: #6b7280;
}

.small {
    font-size: 8.5pt;
}

.assure-cell {
    font-size: 9pt;
    max-width: 45mm;
    word-break: break-word;
}

.empty {
    text-align: center;
    color: #6b7280;
    padding: 8px;
}
</style>
