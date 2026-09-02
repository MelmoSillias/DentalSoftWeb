<script setup>
import { computed } from 'vue';
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';
import { filePrefix } from '@/config';

const props = defineProps({
    fiche: { type: Object, default: () => ({}) },
    patient: { type: Object, default: () => ({}) },
    sections: { type: Array, default: () => [] },
    printEmpty: { type: Boolean, default: false },
    logoSrc: { type: String, default: logoImg }
});

const entretien = computed(() => props.fiche?.entretien || {});
const examens = computed(() => props.fiche?.examens || {});
const bilans = computed(() => props.fiche?.bilans || {});
const documents = computed(() => props.fiche?.documents || []);
const plans = computed(() => props.fiche?.planTraitement || []);
const patientAntecedents = computed(() => (Array.isArray(props.patient?.antecedents) ? props.patient.antecedents : []));
const patientAllergies = computed(() => (Array.isArray(props.patient?.allergies) ? props.patient.allergies : []));
const patientSex = computed(() => props.patient?.sexe || props.fiche?.patient?.sexe || props.fiche?.sexe || '');
const isFemalePatient = computed(() => {
    const normalized = String(patientSex.value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
    return ['f', 'femme', 'feminin', 'female', 'woman'].includes(normalized);
});

const dentalRows = [
    { left: [55, 54, 53, 52, 51], right: [61, 62, 63, 64, 65] },
    { left: [18, 17, 16, 15, 14, 13, 12, 11], right: [21, 22, 23, 24, 25, 26, 27, 28] },
    { left: [48, 47, 46, 45, 44, 43, 42, 41], right: [31, 32, 33, 34, 35, 36, 37, 38] },
    { left: [85, 84, 83, 82, 81], right: [71, 72, 73, 74, 75] }
];

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

const formatBool = (value) => (value === true ? 'Oui' : value === false ? 'Non' : '—');

const hasValue = (value) => {
    if (props.printEmpty) return true;
    if (value === null || value === undefined) return false;
    if (typeof value === 'boolean') return true;
    if (Array.isArray(value)) return value.length > 0;
    return String(value).trim() !== '';
};

const showArray = (value) => (props.printEmpty ? true : Array.isArray(value) && value.length > 0);

const entretienAntecedents = computed(() => {
    const medicaments = Array.isArray(entretien.value?.medicaments) ? entretien.value.medicaments : [];
    const affections = Array.isArray(entretien.value?.affections) ? entretien.value.affections : [];
    return [
        ...medicaments.map((item, idx) => ({
            key: `medicament-${item.id ?? item.nom ?? idx}`,
            type: 'Medicament en cours',
            nom: item.nom || '—',
            etat: item.estUtilise,
            details: item.details || ''
        })),
        ...affections.map((item, idx) => ({
            key: `affection-${item.id ?? item.nom ?? idx}`,
            type: 'Affection',
            nom: item.nom || '—',
            etat: item.estPresente,
            details: item.details || ''
        }))
    ];
});

const shouldPrint = (key) => {
    if (!props.sections || props.sections.length === 0) return true;
    return props.sections.includes(key);
};

const mapToRows = (map, prefix) =>
    Object.entries(map || {})
        .map(([label, value]) => ({
            key: `${prefix}-${label}`,
            label,
            value: typeof value === 'boolean' ? formatBool(value) : value || '—'
        }))
        .filter((row) => hasValue(row.value));

const entretienGroups = computed(() => {
    const groups = [
        {
            key: 'entretien',
            title: 'Anamnese',
            rows: [{ key: 'anamnese', label: 'Anamnese', value: entretien.value?.motifConsultation || '—' }]
        },
        ...(isFemalePatient.value
            ? [
                  {
                      key: 'etat-gynecologique',
                      title: 'Etat gynecologique',
                      rows: [
                          { key: 'allaitement', label: 'Allaitement', value: formatBool(entretien.value?.etatGynecologique?.allaitement) },
                          { key: 'grossesse', label: 'Grossesse en cours', value: formatBool(entretien.value?.etatGynecologique?.grossesseEnCours) },
                          { key: 'menstrues', label: 'Menstrues', value: formatBool(entretien.value?.etatGynecologique?.menstrues) }
                      ]
                  }
              ]
            : [])
    ];

    return groups
        .map((group) => ({
            ...group,
            rows: group.rows.filter((row) => hasValue(row.value))
        }))
        .filter((group) => group.rows.length > 0);
});

const entretienHasRows = computed(() => entretienGroups.value.length > 0);
const entretienQuestionsRows = computed(() => {
    const questions = Array.isArray(entretien.value?.questions) ? entretien.value.questions : [];
    const habitudes = Array.isArray(entretien.value?.habitudes) ? entretien.value.habitudes : [];
    return questions.length + habitudes.length;
});

const examensGroups = computed(() => {
    const groups = [
        {
            key: 'exobuccal-inspection',
            title: 'Exobuccal - Inspection',
            rows: mapToRows(examens.value?.exobuccalInspection, 'exobuccalInspection')
        },
        {
            key: 'exobuccal-palpation',
            title: 'Exobuccal - Palpation',
            rows: mapToRows(examens.value?.exobuccalPalpation, 'exobuccalPalpation')
        },
        {
            key: 'chaines-ganglionnaires',
            title: 'Chaines ganglionnaires',
            rows: mapToRows(examens.value?.chainesGanglionnaires, 'chainesGanglionnaires')
        },
        {
            key: 'endobuccal-fermee',
            title: 'Endobuccal - Bouche fermee',
            rows: [
                { key: 'occlusion', label: 'Occlusion', value: examens.value?.endobuccalBoucheFermee?.occlusion || '—' },
                { key: 'mediane', label: 'Mediane', value: examens.value?.endobuccalBoucheFermee?.mediane || '—' },
                { key: 'classesAngle', label: "Classes d'Angle", value: examens.value?.endobuccalBoucheFermee?.classesAngle || '—' },
                { key: 'vestibules', label: 'Vestibules', value: examens.value?.endobuccalBoucheFermee?.vestibules || '—' }
            ].filter((row) => hasValue(row.value))
        },
        {
            key: 'endobuccal-ouverte',
            title: 'Endobuccal - Bouche ouverte',
            rows: [
                { key: 'hbd', label: 'HBD', value: examens.value?.endobuccalBoucheOuverte?.hbd || '—' },
                { key: 'brossage', label: 'Brossage', value: examens.value?.endobuccalBoucheOuverte?.brossage || '—' },
                { key: 'soccu', label: 'Soccu', value: examens.value?.endobuccalBoucheOuverte?.soccu || '—' },
                { key: 'cinematique', label: 'Cinematique mandibulaire', value: examens.value?.endobuccalBoucheOuverte?.cinematiqueMandibulaire || '—' },
                { key: 'ouverture', label: 'Ouverture buccale', value: examens.value?.endobuccalBoucheOuverte?.ouvertureBuccale || '—' },
                { key: 'temperature', label: 'Temperature buccale', value: examens.value?.endobuccalBoucheOuverte?.temperatureBuccale || '—' },
                { key: 'amplitude', label: "Amplitude d'ouverture", value: examens.value?.endobuccalBoucheOuverte?.amplitudeOuverture || '—' },
                { key: 'bruits', label: 'Bruits articulaires', value: examens.value?.endobuccalBoucheOuverte?.bruitsArticulaires || '—' }
            ].filter((row) => hasValue(row.value))
        },
        {
            key: 'endobuccal-canaux',
            title: 'Endobuccal - Canaux excreteurs',
            rows: [{ key: 'canaux', label: 'Examens des canaux excreteurs', value: examens.value?.examenCanauxExcreteurs || '—' }].filter((row) => hasValue(row.value))
        }
    ];

    return groups.filter((group) => group.rows.length > 0);
});

const examensHasRows = computed(() => examensGroups.value.length > 0);

const tissusMousColumns = ['Levres', 'Joues', 'Langue', 'Gencive', 'Plancher', 'Voile', 'Freins'];
const tissusMousRows = ['Couleur', 'Consistance', 'Volume', 'Lesions', 'Tumeurs', 'Inflammation'];
const tissusDursColumns = ['Rempart alveolaire interne et externe', 'Palais'];
const tissusDursRows = ['Forme', 'Lesions', 'Excroissance osseuse'];

const getCrossValue = (table, row, col) => {
    const value = table?.[row]?.[col];
    if (hasValue(value)) return value;
    return props.printEmpty ? '—' : '';
};

const getExtension = (value) => {
    if (!value) return '';
    const cleaned = value.split('?')[0].split('#')[0];
    const parts = cleaned.split('.');
    return parts.length > 1 ? parts.pop().toLowerCase() : '';
};

const isImageExtension = (extension) => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'].includes(extension);

const resolveUrl = (url) => {
    if (!url || typeof url !== 'string') return '';
    if (/^https?:\/\//i.test(url) || url.startsWith('blob:') || url.startsWith('data:')) return url;
    const prefix = filePrefix.replace(/\/$/, '');
    return `${prefix}/${url.replace(/^\//, '')}`;
};

const buildEntries = (doc, docIndex) => {
    const urls = Array.isArray(doc?.urls) ? doc.urls : doc?.url ? [doc.url] : [];
    return urls
        .map((url, fileIndex) => {
            const extension = getExtension(url);
            return {
                entryKey: `${docIndex}-url-${fileIndex}`,
                isImage: isImageExtension(extension),
                previewSrc: resolveUrl(url),
                fileName: url?.split('/').pop() || 'fichier'
            };
        })
        .filter((entry) => props.printEmpty || entry.previewSrc);
};

const documentsView = computed(() =>
    (documents.value || [])
        .map((doc, index) => ({
            title: doc?.libelle || doc?.type || `Document ${index + 1}`,
            type: doc?.type || 'Document',
            entries: buildEntries(doc, index)
        }))
        .filter((doc) => props.printEmpty || doc.entries.length > 0)
);

const sortedPlans = computed(() => {
    const list = plans.value || [];
    return [...list].sort((a, b) => {
        const da = a?.dateSupposed ? new Date(a.dateSupposed).getTime() : null;
        const db = b?.dateSupposed ? new Date(b.dateSupposed).getTime() : null;
        if (!da && !db) return 0;
        if (!da) return 1;
        if (!db) return -1;
        return da - db;
    });
});

const toothEtat = (tooth) => {
    const entry = bilans.value?.bilanDentaire?.formuleDentaire?.[tooth];
    if (!entry?.etat || entry.etat.length === 0) return '';
    return Array.isArray(entry.etat) ? entry.etat.join('-') : String(entry.etat);
};

const resolveToothState = (tooth) => {
    const entry = bilans.value?.bilanDentaire?.formuleDentaire?.[tooth];
    if (!entry) return null;
    if (entry.estCausale) return 'CAUSALE';
    const etats = Array.isArray(entry.etat) ? entry.etat : [];
    const order = ['C', 'O', 'MP', 'E', 'A', 'M', 'I', 'P', 'BONNE'];
    for (const code of order) {
        if (etats.includes(code)) return code;
    }
    return etats[0] || null;
};

const toothClass = (tooth) => {
    const state = resolveToothState(tooth);
    return state ? `tooth-${state.toLowerCase()}` : 'tooth-empty';
};

const etatLegend = [
    { code: 'BONNE', label: 'Bonne' },
    { code: 'C', label: 'Carie' },
    { code: 'O', label: 'Obturee' },
    { code: 'MP', label: 'Malposition' },
    { code: 'E', label: 'Enclavee' },
    { code: 'A', label: 'Absente' },
    { code: 'M', label: 'Mobile' },
    { code: 'I', label: 'Incluse' },
    { code: 'P', label: 'Prothese' }
];
</script>

<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader title="Fiche médicale" :doc-id="fiche?.id" :date="fiche?.createdAt || fiche?.dateCreation" />
        </template>

        <h2 class="print-section-title">Résumé</h2>
        <table>
            <tbody>
                <tr>
                    <th>Patient</th>
                    <td>{{ patient?.nom || '—' }} {{ patient?.prenom || '' }}</td>
                </tr>
                <tr>
                    <th>Age</th>
                    <td>{{ patient?.age ?? '—' }} ans</td>
                </tr>
                <tr>
                    <th>Telephone</th>
                    <td>{{ patient?.telephone || '—' }}</td>
                </tr>
                <tr>
                    <th>Date de creation</th>
                    <td>{{ formatDateTime(fiche?.createdAt || fiche?.dateCreation) }}</td>
                </tr>
            </tbody>
        </table>

        <template v-if="shouldPrint('entretien')">
            <h2>Questionnaire médical</h2>
            <table v-if="entretienHasRows || printEmpty">
                <thead>
                    <tr>
                        <th>Champ</th>
                        <th>Valeur</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="group in entretienGroups" :key="group.key">
                        <tr class="row-group">
                            <th colspan="2">{{ group.title }}</th>
                        </tr>
                        <tr v-for="row in group.rows" :key="row.key">
                            <td>{{ row.label }}</td>
                            <td>{{ row.value }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <table v-if="showArray(entretienAntecedents)">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Element</th>
                        <th>Etat</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in entretienAntecedents" :key="item.key">
                        <td>{{ item.type || '—' }}</td>
                        <td>{{ item.nom || '—' }}</td>
                        <td>{{ formatBool(item.etat) }}</td>
                        <td>{{ item.details || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <table v-if="printEmpty || entretienQuestionsRows">
                <thead>
                    <tr>
                        <th>Questionnaire medical</th>
                        <th>Reponse</th>
                        <th>Precision</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in entretien.questions" :key="item.id || item.question">
                        <td>{{ item.question || '—' }}</td>
                        <td>{{ formatBool(item.reponse) }}</td>
                        <td>{{ item.precision || '—' }}</td>
                    </tr>
                    <tr v-for="item in entretien?.habitudes || []" :key="item.id || item.type">
                        <td>Habitude: {{ item.type || '—' }}</td>
                        <td>{{ formatBool(item.estPresente) }}</td>
                        <td>{{ item.quantite || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <template v-if="shouldPrint('synthese')">
            <h2>Synthèse clinique</h2>

            <h3>Anamnèse</h3>
            <table v-if="hasValue(entretien?.anamnese) || printEmpty">
                <tbody>
                    <tr>
                        <th>Résumé clinique</th>
                        <td>{{ entretien?.anamnese || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <h3>Antécédents & allergies</h3>
            <table v-if="showArray(patientAntecedents) || showArray(patientAllergies) || printEmpty">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Type</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in patientAntecedents" :key="`ant-${item.id || item.type || item.description}`">
                        <td>Antécédent</td>
                        <td>{{ item.type || '—' }}</td>
                        <td>{{ item.description || '—' }}</td>
                    </tr>
                    <tr v-for="item in patientAllergies" :key="`all-${item.id || item.libelle || item.description}`">
                        <td>Allergie</td>
                        <td>{{ item.libelle || '—' }}</td>
                        <td>{{ item.description || '—' }}</td>
                    </tr>
                    <tr v-if="!patientAntecedents.length && !patientAllergies.length && printEmpty">
                        <td colspan="3">—</td>
                    </tr>
                </tbody>
            </table>

            <h3>Examens complémentaires</h3>
            <table v-if="showArray(examens?.examensLabo) || printEmpty">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Résultat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in examens?.examensLabo || []" :key="`labo-${idx}`">
                        <td>{{ item?.type || '—' }}</td>
                        <td>{{ item?.description || item?.observation || '—' }}</td>
                        <td>{{ formatDate(item?.date) }}</td>
                        <td>{{ item?.resultat || '—' }}</td>
                    </tr>
                    <tr v-if="!(examens?.examensLabo || []).length && printEmpty">
                        <td colspan="4">—</td>
                    </tr>
                </tbody>
            </table>

            <h3>Bilan</h3>
            <table v-if="hasValue(bilans?.diagnosticPositif) || hasValue(bilans?.avisMedicales) || printEmpty">
                <tbody>
                    <tr>
                        <th>Bilan</th>
                        <td>{{ bilans?.diagnosticPositif || '—' }}</td>
                    </tr>
                    <tr>
                        <th>Avis médicales</th>
                        <td>{{ bilans?.avisMedicales || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <h3>Plan de traitement</h3>
            <table v-if="sortedPlans.length || printEmpty">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Date prevue</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="plan in sortedPlans" :key="`synth-plan-${plan.planIndex || plan.id}`">
                        <td>{{ plan.planIndex || '—' }}</td>
                        <td>{{ plan.type || '—' }}</td>
                        <td>{{ formatDate(plan.dateSupposed) }}</td>
                        <td>{{ plan.description || '—' }}</td>
                    </tr>
                    <tr v-if="!sortedPlans.length && printEmpty">
                        <td colspan="4">—</td>
                    </tr>
                </tbody>
            </table>

            <h3>Images & documents</h3>
            <div v-if="documentsView.length">
                <div v-for="(doc, idx) in documentsView" :key="`synth-doc-${idx}`" class="doc-block">
                    <div class="doc-header">
                        <div>
                            <strong>{{ doc.type }}</strong>
                            <div class="muted">{{ doc.title }}</div>
                        </div>
                    </div>
                    <div class="image-grid">
                        <div v-for="entry in doc.entries" :key="entry.entryKey" class="image-card">
                            <div class="image-box" v-if="entry.isImage && entry.previewSrc">
                                <img :src="entry.previewSrc" :alt="doc.title" />
                            </div>
                            <div class="image-box placeholder" v-else>
                                <span>{{ entry.fileName || entry.extension || 'Document' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="muted">Aucun document disponible.</p>
        </template>

        <template v-if="shouldPrint('examens')">
            <h2>Examens cliniques</h2>
            <table v-if="examensHasRows || printEmpty">
                <thead>
                    <tr>
                        <th>Champ</th>
                        <th>Valeur</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="group in examensGroups" :key="group.key">
                        <tr class="row-group">
                            <th colspan="2">{{ group.title }}</th>
                        </tr>
                        <tr v-for="row in group.rows" :key="row.key">
                            <td>{{ row.label }}</td>
                            <td>{{ row.value }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <h3>Examen des tissus mous</h3>
            <table class="cross-table">
                <thead>
                    <tr>
                        <th></th>
                        <th v-for="col in tissusMousColumns" :key="col">{{ col }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in tissusMousRows" :key="row">
                        <th>{{ row }}</th>
                        <td v-for="col in tissusMousColumns" :key="col">
                            {{ getCrossValue(examens?.tissusMousTable, row, col) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3>Examen des tissus durs</h3>
            <table class="cross-table">
                <thead>
                    <tr>
                        <th></th>
                        <th v-for="col in tissusDursColumns" :key="col">{{ col }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in tissusDursRows" :key="row">
                        <th>{{ row }}</th>
                        <td v-for="col in tissusDursColumns" :key="col">
                            {{ getCrossValue(examens?.tissusDursTable, row, col) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </template>

        <template v-if="shouldPrint('images')">
            <h2>Images et documents</h2>
            <div v-if="documentsView.length">
                <div v-for="(doc, idx) in documentsView" :key="idx" class="doc-block">
                    <div class="doc-header">
                        <div>
                            <strong>{{ doc.type }}</strong>
                            <div class="muted">{{ doc.title }}</div>
                        </div>
                    </div>
                    <div class="image-grid">
                        <div v-for="entry in doc.entries" :key="entry.entryKey" class="image-card">
                            <div class="image-box" v-if="entry.isImage && entry.previewSrc">
                                <img :src="entry.previewSrc" :alt="doc.title" />
                            </div>
                            <div class="image-box placeholder" v-else>
                                <span>{{ entry.fileName || entry.extension || 'Document' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="muted">Aucun document disponible.</p>
        </template>

        <template v-if="shouldPrint('plan')">
            <h2>Plan de traitement</h2>
            <table v-if="sortedPlans.length || printEmpty">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Date prevue</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="plan in sortedPlans" :key="plan.planIndex || plan.id">
                        <td>{{ plan.planIndex || '—' }}</td>
                        <td>{{ plan.type || '—' }}</td>
                        <td>{{ formatDate(plan.dateSupposed) }}</td>
                        <td>{{ plan.description || '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="muted">Aucun plan de traitement.</p>
        </template>

        <template v-if="shouldPrint('bilan')">
            <h2>Bilan dentaire</h2>
            <table class="teeth-grid">
                <tbody>
                    <tr v-for="(row, rowIndex) in dentalRows" :key="rowIndex">
                        <template v-if="rowIndex === 0 || rowIndex === dentalRows.length - 1">
                            <td class="grid-spacer"></td>
                            <td class="grid-spacer"></td>
                        </template>
                        <td v-for="tooth in row.left" :key="`left-${tooth}`" class="tooth-cell" :class="toothClass(tooth)">
                            <div class="tooth-number">{{ tooth }}</div>
                            <div class="tooth-state">{{ toothEtat(tooth) || '—' }}</div>
                        </td>
                        <template v-if="rowIndex === 0 || rowIndex === dentalRows.length - 1">
                            <td class="grid-spacer"></td>
                            <td class="grid-spacer"></td>
                            <td class="grid-spacer"></td>
                        </template>
                        <template v-else>
                            <td class="grid-spacer"></td>
                        </template>
                        <td v-for="tooth in row.right" :key="`right-${tooth}`" class="tooth-cell" :class="toothClass(tooth)">
                            <div class="tooth-number">{{ tooth }}</div>
                            <div class="tooth-state">{{ toothEtat(tooth) || '—' }}</div>
                        </td>
                        <template v-if="rowIndex === 0 || rowIndex === dentalRows.length - 1">
                            <td class="grid-spacer"></td>
                            <td class="grid-spacer"></td>
                        </template>
                    </tr>
                </tbody>
            </table>

            <div class="legend">
                <strong>Legende</strong>
                <div class="legend-list">
                    <div v-for="item in etatLegend" :key="item.code" class="legend-item">
                        <span class="legend-code">{{ item.code }}</span>
                        <span>{{ item.label }}</span>
                    </div>
                </div>
            </div>
        </template>

        <template v-if="shouldPrint('seances')">
            <h2>Seances passees</h2>
            <table v-if="(fiche?.consultations || []).length || printEmpty" class="sessions-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Equipe</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="seance in fiche?.consultations || []" :key="seance.id">
                        <td>{{ formatDateTime(seance.createdAt) }}</td>
                        <td>
                            Medecin : {{ seance?.medecin?.nom || '—' }}<br />
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
                                <table class="mini-table">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Dent</th>
                                            <th>Description</th>
                                            <th>Qt.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(acte, idx) in seance.actes" :key="idx">
                                            <td>{{ acte.type || '—' }}</td>
                                            <td>{{ acte.dent || '—' }}</td>
                                            <td>{{ acte.description || '—' }}</td>
                                            <td>{{ acte.quantite || '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="seance?.ordonnances?.length" class="session-block">
                                <strong>Ordonnances</strong>
                                <ul>
                                    <li v-for="ordo in seance.ordonnances" :key="ordo.id || ordo.date">
                                        {{ ordo.date || '—' }} - {{ ordo.medecinNom || '—' }}
                                        <template v-if="ordo.note"> : {{ ordo.note }}</template>
                                        <ul v-if="ordo.lignes?.length">
                                            <li v-for="(ligne, lidx) in ordo.lignes" :key="lidx">{{ ligne.designation || '—' }} | {{ ligne.posologie || '—' }} | {{ ligne.frequence || '—' }} | {{ ligne.duree || '—' }}</li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="muted">Aucune seance precedente.</p>
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

h3 {
    font-size: 13px;
    margin: 14px 0 6px;
    font-weight: 700;
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
    background: #f6f8fb;
    font-weight: 700;
}

.row-group th {
    background: #eef3f8;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.cross-table th {
    width: auto;
    text-align: left;
}

.muted {
    color: #586574;
}

.teeth-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 6px;
    margin-bottom: 12px;
}

.tooth-cell {
    border: 1px solid #cfd8e3;
    padding: 6px 4px;
    text-align: center;
    width: 42px;
    height: 42px;
    background: #fff;
    font-size: 11px;
}

.tooth-number {
    font-weight: 700;
    font-size: 11px;
}

.tooth-state {
    font-size: 10px;
    color: #2f3b49;
}

.grid-spacer {
    width: 16px;
}

.tooth-empty {
    background: #ffffff;
}

.tooth-bonne {
    background: #e7f7ef;
    border-color: #a9e4c0;
}

.tooth-c {
    background: #fde8e8;
    border-color: #f4b4b4;
}

.tooth-o {
    background: #e6f0ff;
    border-color: #a9c8ff;
}

.tooth-mp {
    background: #fef3c7;
    border-color: #f6d27b;
}

.tooth-e {
    background: #eef2f7;
    border-color: #c5d0de;
}

.tooth-a {
    background: #e8edf3;
    border-color: #b7c2d0;
}

.tooth-m {
    background: #fff2e0;
    border-color: #f1c48b;
}

.tooth-i {
    background: #efe6ff;
    border-color: #c5a7ff;
}

.tooth-p {
    background: #e6f7f4;
    border-color: #9bd8cc;
}

.tooth-causale {
    background: #f8d7da;
    border-color: #e59aa2;
}

.legend {
    margin-top: 10px;
    font-size: 12px;
}

.legend-list {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
    margin-top: 6px;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-code {
    display: inline-block;
    min-width: 52px;
    font-weight: 700;
}

.image-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 8px;
}

.image-card {
    border: 1px solid #cfd8e3;
    padding: 6px;
}

.image-box {
    width: 100%;
    aspect-ratio: 1 / 1;
    border: 1px solid #e1e7ef;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.image-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.image-box.placeholder {
    background: #f6f8fb;
    color: #586574;
    font-size: 11px;
    text-align: center;
    padding: 6px;
}

.doc-block {
    margin-bottom: 14px;
}

.sessions-table th,
.sessions-table td {
    border: 1px solid #cfd8e3;
    padding: 6px;
}

.session-block {
    margin-top: 6px;
}

.mini-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6px;
    font-size: 12px;
}

.mini-table th,
.mini-table td {
    border: 1px solid #d6dee8;
    padding: 4px 6px;
}
</style>
