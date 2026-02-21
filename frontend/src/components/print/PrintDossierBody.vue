<template>
    <div class="paper" :style="{ '--watermark': `url(${logoSrc})` }">
        <header>
            <img :src="headerSrc" alt="Centre Dentaire Massaman" class="header-banner" />
            <h3>Dossier patient</h3>
        </header>

        <h2 class="section-title">Informations personnelles</h2>
        <table class="info-table">
            <tbody>
                <tr><th>Nom</th><td>{{ patient?.nom || '—' }}</td></tr>
                <tr><th>Prénom</th><td>{{ patient?.prenom || '—' }}</td></tr>
                <tr><th>Date de naissance</th><td>{{ formatDate(patient?.dateNaissance) }}</td></tr>
                <tr><th>Sexe</th><td>{{ patient?.sexe || '—' }}</td></tr>
                <tr><th>Lieu de naissance</th><td>{{ patient?.lieuNaissance || '—' }}</td></tr>
                <tr><th>Profession</th><td>{{ patient?.profession || '—' }}</td></tr>
                <tr><th>Email</th><td>{{ patient?.email || '—' }}</td></tr>
                <tr><th>Téléphone</th><td>{{ patient?.telephone || '—' }}</td></tr>
                <tr><th>Adresse</th><td>{{ patient?.adresse || '—' }}</td></tr>
                <tr><th>Numéro de carnet</th><td>{{ patient?.numCarnet || '—' }}</td></tr>
                <tr><th>Groupe sanguin</th><td>{{ patient?.groupeSanguin || '—' }}</td></tr>
                <tr><th>Date d'inscription</th><td>{{ formatDateTime(patient?.dateInscription) }}</td></tr>
                <tr>
                    <th>Contact d'urgence</th>
                    <td>
                        <template v-if="patient?.contactUrgence">
                            {{ patient.contactUrgence?.nom }} ({{ patient.contactUrgence?.lienParente }})<br />
                            Tél : {{ patient.contactUrgence?.telephone }}
                        </template>
                        <template v-else>
                            Aucun contact d'urgence disponible.
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2 class="section-title">Allergies</h2>
        <ul v-if="patient?.allergies?.length">
            <li v-for="(a, idx) in patient.allergies" :key="idx">
                {{ a.libelle }}<template v-if="a.description"> : {{ a.description }}</template>
            </li>
        </ul>
        <p v-else class="muted">Aucune allergie renseignée.</p>

        <h2 class="section-title">Antécédents médicaux</h2>
        <ul v-if="patient?.antecedents?.length">
            <li v-for="(ant, idx) in patient.antecedents" :key="idx">
                {{ ant.type || '—' }}
                <template v-if="ant.description"> ({{ ant.description }})</template>
                <template v-if="ant.dateEnregistrement"> – {{ formatDate(ant.dateEnregistrement) }}</template>
            </li>
        </ul>
        <p v-else class="muted">Aucun antécédent médical enregistré.</p>
    </div>
</template>

<script setup>
import headerImg from '@/assets/header.jpeg';
import logoImg from '@/assets/logo.png';

defineProps({
    patient: { type: Object, default: () => ({}) },
    headerSrc: { type: String, default: headerImg },
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

<style scoped>
.paper {
    position: relative;
    background: #fff;
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    font-family: Arial, sans-serif;
    color: #1f2d3d;
}

.paper::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--watermark) center center no-repeat;
    background-size: 60% auto;
    opacity: 0.06;
    pointer-events: none;
}

header {
    text-align: center;
    margin-bottom: 24px;
}

.header-banner {
    width: 100%;
    max-height: 120px;
    object-fit: contain;
    margin-bottom: 8px;
}

.section-title {
    margin: 26px 0 12px;
    font-size: 16px;
    color: #0f4c75;
    display: inline-block;
    position: relative;
    padding-left: 18px;
}

.section-title::before {
    content: '◆';
    position: absolute;
    left: 0;
    top: -1px;
    color: #0f4c75;
    font-size: 12px;
}

.info-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
    font-size: 13px;
}

.info-table th {
    width: 210px;
    text-align: left;
    padding: 8px 10px;
    color: #5f6c7b;
    font-weight: 600;
    border-bottom: 1px solid #d9e2ec;
    background: #f9fbfd;
}

.info-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #d9e2ec;
}

.info-table tr:nth-child(even) td {
    background: #fafbff;
}

ul {
    list-style: none;
    padding-left: 0;
    margin: 0 0 8px;
    font-size: 13px;
}

ul li {
    margin: 4px 0;
    padding-left: 14px;
    position: relative;
}

ul li::before {
    content: '•';
    position: absolute;
    left: 0;
    color: #0f4c75;
}

.muted {
    color: #5f6c7b;
    font-size: 12px;
}
</style>
