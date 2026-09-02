<script setup>
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

defineProps({
    patient: { type: Object, default: () => ({}) },
    logoSrc: { type: String, default: logoImg }
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
</script>

<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader title="Dossier patient" :date="patient?.dateInscription" />
        </template>

        <h2 class="print-section-title">Informations personnelles</h2>
        <table class="print-doc-table info-table">
            <tbody>
                <tr>
                    <th>Nom</th>
                    <td>{{ patient?.nom || '—' }}</td>
                </tr>
                <tr>
                    <th>Prénom</th>
                    <td>{{ patient?.prenom || '—' }}</td>
                </tr>
                <tr>
                    <th>Date de naissance</th>
                    <td>{{ formatDate(patient?.dateNaissance) }}</td>
                </tr>
                <tr>
                    <th>Sexe</th>
                    <td>{{ patient?.sexe || '—' }}</td>
                </tr>
                <tr>
                    <th>Lieu de naissance</th>
                    <td>{{ patient?.lieuNaissance || '—' }}</td>
                </tr>
                <tr>
                    <th>Profession</th>
                    <td>{{ patient?.profession || '—' }}</td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>{{ patient?.email || '—' }}</td>
                </tr>
                <tr>
                    <th>Téléphone</th>
                    <td>{{ patient?.telephone || '—' }}</td>
                </tr>
                <tr>
                    <th>Adresse</th>
                    <td>{{ patient?.adresse || '—' }}</td>
                </tr>
                <tr>
                    <th>Numéro de carnet</th>
                    <td>{{ patient?.numCarnet || '—' }}</td>
                </tr>
                <tr>
                    <th>Groupe sanguin</th>
                    <td>{{ patient?.groupeSanguin || '—' }}</td>
                </tr>
                <tr>
                    <th>Date d'inscription</th>
                    <td>{{ formatDateTime(patient?.dateInscription) }}</td>
                </tr>
                <tr>
                    <th>Contact d'urgence</th>
                    <td>
                        <template v-if="patient?.contactUrgence">
                            {{ patient.contactUrgence?.nom }} ({{ patient.contactUrgence?.lienParente }})<br />
                            Tél : {{ patient.contactUrgence?.telephone }}
                        </template>
                        <template v-else>Aucun contact d'urgence disponible.</template>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 class="print-section-title">Allergies</h2>
        <ul v-if="patient?.allergies?.length" class="list">
            <li v-for="(a, idx) in patient.allergies" :key="idx">
                {{ a.libelle }}<template v-if="a.description"> : {{ a.description }}</template>
            </li>
        </ul>
        <p v-else class="muted">Aucune allergie renseignée.</p>

        <h2 class="print-section-title">Antécédents médicaux</h2>
        <ul v-if="patient?.antecedents?.length" class="list">
            <li v-for="(ant, idx) in patient.antecedents" :key="idx">
                {{ ant.type || '—' }}
                <template v-if="ant.description"> ({{ ant.description }})</template>
                <template v-if="ant.dateEnregistrement"> – {{ formatDate(ant.dateEnregistrement) }}</template>
            </li>
        </ul>
        <p v-else class="muted">Aucun antécédent médical enregistré.</p>
    </PrintA4Page>
</template>

<style scoped>
.info-table th {
    width: 32%;
    background: #f6f8fb;
    font-weight: 600;
}

.list {
    list-style: none;
    padding: 0;
    margin: 0 0 12px;
    font-size: 10.5pt;
}

.list li {
    margin: 4px 0;
    padding-left: 14px;
    position: relative;
}

.list li::before {
    content: '•';
    position: absolute;
    left: 0;
    color: #1d6fbf;
    font-weight: 700;
}

.muted {
    color: #586574;
    font-size: 10pt;
}
</style>
