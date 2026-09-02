<script setup>
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

defineProps({
    data: { type: Object, default: () => ({}) },
    logoSrc: { type: String, default: logoImg }
});
</script>

<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader title="Ordonnance" :doc-id="data?.id" :date="data?.date" />
        </template>

        <div class="print-info-card">
            <div>
                <strong>Patient :</strong> {{ data?.patient || '—' }}<br />
                <span class="muted" v-if="data?.note">{{ data.note }}</span>
            </div>
        </div>

        <table class="print-doc-table">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th style="width: 30%">Posologie</th>
                    <th style="width: 20%">Fréquence</th>
                    <th style="width: 10%">Qté</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(ligne, idx) in data?.lignes || []" :key="idx">
                    <td>
                        <strong>{{ ligne.designation || '—' }}</strong>
                    </td>
                    <td>{{ ligne.posologie || '—' }} <small>unités</small></td>
                    <td>{{ ligne.frequence || '—' }} <small>par jour</small></td>
                    <td>{{ ligne.quantite ?? '—' }}</td>
                </tr>
                <tr v-if="!(data?.lignes && data.lignes.length)">
                    <td colspan="4" class="empty">Aucune prescription renseignée.</td>
                </tr>
            </tbody>
        </table>

        <p class="notice">Merci de suivre rigoureusement la posologie indiquée.</p>

        <div class="signature-zone">
            <div class="sign-line" />
            <p>Signature et cachet du médecin</p>
        </div>
    </PrintA4Page>
</template>

<style scoped>
.notice {
    font-size: 10pt;
    margin: 16px 0;
    padding: 8px 12px;
    border-left: 3px solid #1d6fbf;
    background: #f9fbfd;
    color: #374151;
}

.signature-zone {
    margin-top: 24px;
    width: 45%;
    margin-left: auto;
    text-align: center;
    font-size: 10pt;
    color: #586574;
}

.sign-line {
    border-top: 1px solid #111;
    margin-bottom: 6px;
    height: 1px;
}
</style>
