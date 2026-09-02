<script setup>
import { computed } from 'vue';
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    fiche: { type: Object, default: () => ({}) },
    patient: { type: Object, default: () => ({}) },
    logoSrc: { type: String, default: logoImg }
});

const teeth = [11, 21, 12, 22, 13, 23, 14, 24, 15, 25, 16, 26, 17, 27, 18, 28, 31, 41, 32, 42, 33, 43, 34, 44, 35, 45, 36, 46, 37, 47, 38, 48];
const teethRows = computed(() => {
    const rows = [];
    for (let i = 0; i < teeth.length; i += 2) {
        rows.push([teeth[i], teeth[i + 1] || '']);
    }
    return rows;
});

const formatDate = (value) => {
    if (!value) return '—';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('fr-FR');
};

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return `${date.toLocaleDateString('fr-FR')} ${date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
};

const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;
</script>

<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader title="Fiche de consultation" :doc-id="fiche?.id" :date="fiche?.createdAt" />
        </template>

        <h2 class="print-section-title">Résumé</h2>
        <table>
            <tbody>
                <tr>
                    <th>Patient</th>
                    <td>{{ patient?.nom || '—' }} {{ patient?.prenom || '' }}</td>
                </tr>
                <tr>
                    <th>Âge</th>
                    <td>{{ patient?.age ?? '—' }} ans</td>
                </tr>
                <tr>
                    <th>Téléphone</th>
                    <td>{{ patient?.telephone || '—' }}</td>
                </tr>
                <tr>
                    <th>Date de création</th>
                    <td>{{ formatDateTime(fiche?.createdAt) }}</td>
                </tr>
            </tbody>
        </table>

        <h2 class="print-section-title">Motif et historique</h2>
        <table>
            <tbody>
                <tr>
                    <th>Motif</th>
                    <td>{{ fiche?.motif || '—' }}</td>
                </tr>
                <tr>
                    <th>Histoire</th>
                    <td>{{ fiche?.histoireMaladie || '—' }}</td>
                </tr>
                <tr>
                    <th>Soins antérieurs</th>
                    <td>{{ fiche?.soinsAnterieurs || '—' }}</td>
                </tr>
                <tr>
                    <th>Diagnostic</th>
                    <td>{{ fiche?.diagnostic || '—' }}</td>
                </tr>
            </tbody>
        </table>

        <h2 class="print-section-title">Examens cliniques</h2>
        <table>
            <tbody>
                <tr>
                    <th>Exo-Inspection</th>
                    <td>{{ fiche?.exoInspection || '—' }}</td>
                </tr>
                <tr>
                    <th>Exo-Palpation</th>
                    <td>{{ fiche?.exoPalpation || '—' }}</td>
                </tr>
                <tr>
                    <th>Endo-Inspection</th>
                    <td>{{ fiche?.endoInspection || '—' }}</td>
                </tr>
                <tr>
                    <th>Endo-Palpation</th>
                    <td>{{ fiche?.endoPalpation || '—' }}</td>
                </tr>
            </tbody>
        </table>

        <h2 class="print-section-title">Examens dentaires</h2>
        <table>
            <tbody>
                <tr>
                    <th>Occlusion</th>
                    <td>{{ fiche?.occlusion || '—' }}</td>
                </tr>
                <tr>
                    <th>Parodontal</th>
                    <td>{{ fiche?.examenParodontal || '—' }}</td>
                </tr>
                <tr>
                    <th>Diagnostic</th>
                    <td>{{ fiche?.diagnostic || '—' }}</td>
                </tr>
            </tbody>
        </table>

        <h2 class="print-section-title">Examens dentaires détaillés</h2>
        <table class="teeth-table">
            <tbody>
                <tr v-for="row in teethRows" :key="row.join('-')">
                    <td v-for="num in row" :key="num" class="teeth-cell">
                        <template v-if="num">
                            <strong>{{ num }}</strong
                            ><br />
                            <span class="muted">{{ fiche?.toothChecks?.[String(num)] || '' }}</span>
                        </template>
                        <template v-else>&nbsp;</template>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 class="print-section-title">Traitements</h2>
        <table>
            <tbody>
                <tr>
                    <th>Urgence</th>
                    <td>{{ fiche?.traitementUrgence || '—' }}</td>
                </tr>
                <tr>
                    <th>Dentaire</th>
                    <td>{{ fiche?.traitementDentaire || '—' }}</td>
                </tr>
                <tr>
                    <th>Parodontal</th>
                    <td>{{ fiche?.traitementParodontal || '—' }}</td>
                </tr>
                <tr>
                    <th>Orthodontique</th>
                    <td>{{ fiche?.traitementOrthodontique || '—' }}</td>
                </tr>
                <tr>
                    <th>Autres</th>
                    <td>{{ fiche?.autres || '—' }}</td>
                </tr>
            </tbody>
        </table>

        <template v-if="fiche?.devis">
            <h2 class="print-section-title">Devis</h2>
            <table>
                <tbody>
                    <tr>
                        <th>Date</th>
                        <td>{{ formatDate(fiche?.devis?.date) }}</td>
                    </tr>
                    <tr>
                        <th>Montant total</th>
                        <td>{{ formatMoney(fiche?.devis?.montant) }}</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <template v-if="fiche?.consultations?.length">
            <h2 class="print-section-title">Séances passées</h2>
            <table class="sessions-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Equipe</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="seance in fiche.consultations" :key="seance.id">
                        <td>{{ formatDate(seance.createdAt) }}</td>
                        <td>
                            Médecin : {{ seance?.medecin?.nom || '—' }}<br />
                            <hr />
                            Aide soignant(e) : {{ seance?.infirmier?.nom || '—' }}<br />
                            <hr />
                            Salle : {{ seance?.salle?.nom || '—' }}
                        </td>
                        <td>
                            <div class="muted" v-if="!seance?.actes?.length && !seance?.ordonnances?.length && !seance?.noteSeance && !seance?.type">—</div>
                            <div v-if="seance?.type"><strong>Type :</strong> {{ seance.type }}</div>
                            <div v-if="seance?.noteSeance"><strong>Note :</strong> {{ seance.noteSeance }}</div>
                            <div v-if="seance?.actes?.length" class="session-block">
                                <strong>Actes</strong>
                                <ul>
                                    <li v-for="(acte, idx) in seance.actes" :key="idx">
                                        {{ acte.type || '—' }} | Dent {{ acte.dent || '—' }} | {{ acte.description || '—' }}
                                        <template v-if="acte.quantite"> (x{{ acte.quantite }})</template>
                                    </li>
                                </ul>
                            </div>
                            <div v-if="seance?.ordonnances?.length" class="session-block">
                                <strong>Ordonnances</strong>
                                <ul>
                                    <li v-for="ordo in seance.ordonnances" :key="ordo.id || ordo.date">
                                        {{ ordo.date || '—' }} - {{ ordo.medecinNom || '—' }}
                                        <template v-if="ordo.note"> : {{ ordo.note }}</template>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>
    </PrintA4Page>
</template>

<style scoped>
h2 {
    font-size: 12pt;
    margin: 14px 0 6px;
    font-weight: 700;
    color: #1d6fbf;
    padding-bottom: 4px;
    border-bottom: 1px solid #cfd8e3;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
    font-size: 13px;
}

th,
td {
    border: 1px solid #cfd8e3;
    padding: 8px 10px;
    text-align: left;
    vertical-align: top;
}

th {
    width: 28%;
    background: #f6f8fb;
    font-weight: 700;
}

.muted {
    color: #586574;
}

.teeth-table td {
    border: 1px solid #cfd8e3;
    padding: 6px;
    width: 50%;
}

.sessions-table th,
.sessions-table td {
    border: 1px solid #cfd8e3;
    padding: 6px;
}

.session-block {
    margin-top: 6px;
}

ul {
    margin: 6px 0;
    padding-left: 18px;
}

ul li {
    margin: 2px 0;
}
</style>
