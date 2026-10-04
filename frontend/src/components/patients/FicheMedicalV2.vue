<script setup>
import Button from 'primevue/button';
import Galleria from 'primevue/galleria';
import SelectButton from 'primevue/selectbutton';
import { computed, ref, watch } from 'vue';
import { defaultDentitionFromAge, DENTITION_OPTIONS } from '@/utils/formuleDentaireLayout';
import { filePrefix } from '@/config';
import DevisForm from '@/components/consultations/DevisForm.vue';
import FormuleDentaireReadonly from '@/components/fiche-medicale/FormuleDentaireReadonly.vue';
import ReadonlyFieldGrid from '@/components/fiche-medicale/ReadonlyFieldGrid.vue';
import SeancesSection from '@/components/fiche-medicale/SeancesSection.vue';

const props = defineProps({
    fiche: {
        type: Object,
        required: true
    },
    positionLabel: {
        type: String,
        default: ''
    },
    compact: {
        type: Boolean,
        default: false
    },
    hideActions: {
        type: Boolean,
        default: false
    },
    tall: {
        type: Boolean,
        default: false
    },
    patientAge: {
        type: Number,
        default: 0
    }
});

const emit = defineEmits(['print']);

const dentitionType = ref(defaultDentitionFromAge(props.patientAge));

watch(
    () => props.patientAge,
    (age) => {
        dentitionType.value = defaultDentitionFromAge(age);
    },
    { immediate: true }
);

const entretien = computed(() => props.fiche?.entretien || {});
const examens = computed(() => props.fiche?.examens || {});
const bilans = computed(() => props.fiche?.bilans || {});
const documents = computed(() => props.fiche?.documents || []);
const normalizeDevisEntry = (entry = {}) => ({
    id: Number(entry?.id) || null,
    type: entry?.type ?? null,
    date: entry?.date ?? null,
    description: entry?.description || '',
    services: Array.isArray(entry?.services)
        ? entry.services
        : Array.isArray(entry?.contenus)
          ? entry.contenus.map((s) => ({
                designation: s?.designation || '',
                qte: Number(s?.qte) || 1,
                montant: Number(s?.montant) || 0
            }))
          : []
});

const devisModel = computed(() => {
    const rawDevis = props.fiche?.devis;
    let entries = [];
    if (Array.isArray(rawDevis)) {
        entries = rawDevis.map(normalizeDevisEntry);
    } else if (rawDevis && Array.isArray(rawDevis.devisList)) {
        entries = rawDevis.devisList.map(normalizeDevisEntry);
    } else if (rawDevis && typeof rawDevis === 'object') {
        entries = [normalizeDevisEntry(rawDevis)];
    }
    return {
        devisList: entries.length ? entries : [],
        activeDevisIndex: 0,
        date: entries[0]?.date ?? null,
        services: entries[0]?.services ?? []
    };
});

const examensLabo = computed(() => (Array.isArray(examens.value?.examensLabo) ? examens.value.examensLabo : []));

const bilanRadiographiqueFields = computed(() => [
    { label: 'Radiographie extra buccale', value: bilans.value?.bilanRadiographique?.radiographieExtraBuccaleHypothese },
    { label: 'Radiographie intra buccale', value: bilans.value?.bilanRadiographique?.radiographieIntraBuccaleHypothese }
]);

const bilanSanguinFields = computed(() => [
    { label: 'NFS détaillée', value: bilans.value?.bilanSanguin?.nfsDetaillee },
    { label: 'TP / TCA / INR', value: bilans.value?.bilanSanguin?.tpTcaInr },
    { label: 'Urée', value: bilans.value?.bilanSanguin?.uree },
    { label: 'Créatininémie', value: bilans.value?.bilanSanguin?.creatininemie },
    { label: 'Glycémie', value: bilans.value?.bilanSanguin?.glycemie }
]);

const diagnosticPositifValue = computed(() => {
    const raw = bilans.value?.diagnosticPositif ?? bilans.value?.diagnostic_positif ?? '';
    return typeof raw === 'string' ? raw.trim() : String(raw || '').trim();
});

const plansTraitement = computed(() => props.fiche?.planTraitement || []);
const consultations = computed(() => props.fiche?.consultations || []);
const patientSex = computed(() => props.fiche?.patient?.sexe || props.fiche?.sexe || '');
const isFemalePatient = computed(() => {
    const normalized = String(patientSex.value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
    return ['f', 'femme', 'feminin', 'female', 'woman'].includes(normalized);
});
const antecedentsRows = computed(() => {
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

const formatDate = (date) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatDateShort = (date) => {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const formatBool = (value) => (value === true ? 'Oui' : value === false ? 'Non' : '--');

const resolveUrl = (url) => {
    if (!url || typeof url !== 'string') return '';
    if (/^https?:\/\//i.test(url) || url.startsWith('blob:') || url.startsWith('data:')) return url;
    const prefix = filePrefix.replace(/\/$/, '');
    return `${prefix}/${url.replace(/^\//, '')}`;
};

const getDocTitle = (doc, idx) => doc?.libelle?.trim() || doc?.type || `Document ${idx + 1}`;

const getExtension = (value) => {
    if (!value) return '';
    const cleaned = value.split('?')[0].split('#')[0];
    const parts = cleaned.split('.');
    return parts.length > 1 ? parts.pop().toLowerCase() : '';
};

const isImageExtension = (extension) => ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg'].includes(extension);

const getDocumentIcon = (extension) => {
    switch (extension) {
        case 'pdf':
            return 'pi-file-pdf';
        case 'doc':
        case 'docx':
            return 'pi-file-word';
        case 'xls':
        case 'xlsx':
            return 'pi-file-excel';
        case 'ppt':
        case 'pptx':
            return 'pi-file';
        default:
            return 'pi-file';
    }
};

const mapEntries = (source) =>
    Object.entries(source || {}).filter(([, value]) => {
        if (value === null || value === undefined) return false;
        if (typeof value === 'boolean') return true;
        return String(value).trim() !== '';
    });

const tissusMousColumns = ['Levres', 'Joues', 'Langue', 'Gencive', 'Plancher', 'Voile', 'Freins'];
const tissusMousRows = ['Couleur', 'Consistance', 'Volume', 'Lesions', 'Tumeurs', 'Inflammation'];
const tissusDursColumns = ['Rempart alveolaire interne et externe', 'Palais'];
const tissusDursRows = ['Forme', 'Lesions', 'Excroissance osseuse'];

const buildEntries = (doc, docIndex) => {
    const urls = Array.isArray(doc?.urls) ? doc.urls : doc?.url ? [doc.url] : [];
    return urls.map((url, fileIndex) => {
        const resolved = resolveUrl(url);
        const extension = getExtension(url);
        return {
            entryKey: `${docIndex}-url-${fileIndex}`,
            isImage: isImageExtension(extension),
            previewSrc: resolved,
            extension,
            icon: getDocumentIcon(extension),
            downloadUrl: resolved,
            fileName: url?.split('/').pop() || 'fichier'
        };
    });
};

const documentsView = computed(() =>
    (documents.value || []).map((doc, index) => ({
        doc,
        title: getDocTitle(doc, index),
        type: doc?.type || 'Document',
        entries: buildEntries(doc, index)
    }))
);

const galleryItems = computed(() =>
    documentsView.value.flatMap((item, docIndex) =>
        item.entries.map((entry) => ({
            ...entry,
            docIndex,
            title: item.title,
            description: item.type
        }))
    )
);

const previewVisible = ref(false);
const previewIndex = ref(0);
const previewItems = ref([]);

const openPreviewByKey = (entryKey) => {
    const idx = galleryItems.value.findIndex((item) => item.entryKey === entryKey);
    if (idx < 0) return;
    const selected = galleryItems.value[idx];
    const sameDocItems = galleryItems.value.filter((item) => item.docIndex === selected.docIndex);
    previewItems.value = sameDocItems.map((item) => ({ ...item }));
    const nextIndex = sameDocItems.findIndex((item) => item.entryKey === entryKey);
    previewIndex.value = nextIndex >= 0 ? nextIndex : 0;
    previewVisible.value = true;
};

const goPrevPreview = () => {
    if (!previewItems.value.length) return;
    previewIndex.value = (previewIndex.value - 1 + previewItems.value.length) % previewItems.value.length;
};

const goNextPreview = () => {
    if (!previewItems.value.length) return;
    previewIndex.value = (previewIndex.value + 1) % previewItems.value.length;
};

const galleriaPt = {
    mask: { class: 'bg-surface-950/90 backdrop-blur-sm' },
    content: { class: 'flex items-center justify-center' },
    itemsContainer: { class: 'flex-1' },
    item: { class: 'flex items-center justify-center' },
    closeButton: { class: 'text-white hover:text-primary-200' },
    closeIcon: { class: 'text-white' },
    prevButton: { class: 'absolute left-4 top-1/2 -translate-y-1/2 z-10 text-white/90 hover:text-white' },
    nextButton: { class: 'absolute right-4 top-1/2 -translate-y-1/2 z-10 text-white/90 hover:text-white' },
    prevIcon: { class: 'text-white/90' },
    nextIcon: { class: 'text-white/90' }
};

const parseDate = (value) => {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const formatPlanDate = (value) => {
    const date = parseDate(value);
    if (!date) return 'Date non definie';
    return date.toLocaleDateString('fr-FR');
};

const sortedPlans = computed(() => {
    const list = plansTraitement.value || [];
    return [...list].sort((a, b) => {
        const da = parseDate(a.dateSupposed);
        const db = parseDate(b.dateSupposed);
        if (!da && !db) return 0;
        if (!da) return 1;
        if (!db) return -1;
        return da.getTime() - db.getTime();
    });
});

const sessions = computed(() =>
    (consultations.value || []).map((session) => ({
        id: session.id,
        date: formatDate(session.createdAt || session.date),
        medecin: session.medecin?.name || session.medecin || '—',
        infirmier: session.infirmier || '—',
        salle: session.salle || '—',
        noteSeance: session.noteSeance || '',
        actes: session.actes || [],
        total: session.total,
        statut: session.statut
    }))
);
</script>

<template>
    <div>
        <div :class="compact ? '' : 'page-section'">
            <div v-if="!compact" class="page-section__header">
                <div class="page-section__header-main">
                    <h3 class="page-section__title">Fiche médicale {{ positionLabel || '' }}</h3>
                    <p class="page-section__subtitle">Créée le {{ formatDate(props.fiche?.dateCreation || props.fiche?.createdAt) }}</p>
                </div>
            </div>

            <div class="fiche-book" :class="{ 'fiche-book--tall': tall }">
                <div class="fiche-book__pages">
                    <h3 class="fiche-book__title">Questionnaire médical</h3>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Anamnèse</span>
                        <span class="fiche-book__value">{{ entretien.motifConsultation || '—' }}</span>
                    </div>

                    <template v-if="isFemalePatient">
                        <h4 class="fiche-book__title">État gynécologique</h4>
                        <div class="fiche-book__line">
                            <span class="fiche-book__label">Allaitement</span>
                            <span class="fiche-book__value">{{ formatBool(entretien.etatGynecologique?.allaitement) }}</span>
                        </div>
                        <div class="fiche-book__line">
                            <span class="fiche-book__label">Grossesse en cours</span>
                            <span class="fiche-book__value">{{ formatBool(entretien.etatGynecologique?.grossesseEnCours) }}</span>
                        </div>
                        <div class="fiche-book__line">
                            <span class="fiche-book__label">Menstrues</span>
                            <span class="fiche-book__value">{{ formatBool(entretien.etatGynecologique?.menstrues) }}</span>
                        </div>
                    </template>

                    <h4 class="fiche-book__title">Antécédents médicaux</h4>
                    <div class="fiche-book__block">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Élément</th>
                                    <th>État</th>
                                    <th>Détails</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!antecedentsRows.length">
                                    <td colspan="4">Aucun antécédent enregistré.</td>
                                </tr>
                                <tr v-for="row in antecedentsRows" :key="row.key">
                                    <td>{{ row.type }}</td>
                                    <td>{{ row.nom }}</td>
                                    <td>{{ row.etat ? 'Oui' : 'Non' }}</td>
                                    <td>{{ row.details || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <template v-if="(entretien.questions || []).length || (entretien.habitudes || []).length">
                        <h4 class="fiche-book__title">Questionnaire et habitudes</h4>
                        <div v-for="q in entretien.questions" :key="q.id || q.question" class="fiche-book__line">
                            <span class="fiche-book__label">{{ q.question }} — {{ q.reponse === true ? 'Oui' : q.reponse === false ? 'Non' : '--' }}</span>
                            <span class="fiche-book__value">{{ q.precision || '—' }}</span>
                        </div>
                        <div v-for="h in entretien.habitudes || []" :key="h.id || h.type" class="fiche-book__line">
                            <span class="fiche-book__label">Habitude : {{ h.type || '—' }} — {{ h.estPresente ? 'Oui' : 'Non' }}</span>
                            <span class="fiche-book__value">{{ h.quantite || '—' }}</span>
                        </div>
                    </template>

                    <h3 class="fiche-book__title">Examen</h3>
                    <h4 class="fiche-book__title">Exobuccal — inspection</h4>
                    <div v-for="[label, value] in mapEntries(examens.exobuccalInspection)" :key="`insp-${label}`" class="fiche-book__line">
                        <span class="fiche-book__label">{{ label }}</span>
                        <span class="fiche-book__value">{{ value || '—' }}</span>
                    </div>
                    <p v-if="!mapEntries(examens.exobuccalInspection).length" class="fiche-book__empty">—</p>

                    <h4 class="fiche-book__title">Exobuccal — palpation</h4>
                    <div v-for="[label, value] in mapEntries(examens.exobuccalPalpation)" :key="`palp-${label}`" class="fiche-book__line">
                        <span class="fiche-book__label">{{ label }}</span>
                        <span class="fiche-book__value">{{ value || '—' }}</span>
                    </div>
                    <p v-if="!mapEntries(examens.exobuccalPalpation).length" class="fiche-book__empty">—</p>

                    <h4 class="fiche-book__title">Chaînes ganglionnaires</h4>
                    <div v-for="[label, value] in mapEntries(examens.chainesGanglionnaires)" :key="`gang-${label}`" class="fiche-book__line">
                        <span class="fiche-book__label">{{ label }}</span>
                        <span class="fiche-book__value">{{ value ? 'Oui' : 'Non' }}</span>
                    </div>
                    <p v-if="!mapEntries(examens.chainesGanglionnaires).length" class="fiche-book__empty">—</p>

                    <h4 class="fiche-book__title">Bouche fermée</h4>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Occlusion</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheFermee?.occlusion || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Médiane</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheFermee?.mediane || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Classes d'Angle</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheFermee?.classesAngle || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Vestibules</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheFermee?.vestibules || '—' }}</span>
                    </div>

                    <h4 class="fiche-book__title">Bouche ouverte</h4>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">HBD</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.hbd || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Brossage</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.brossage || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Soccu</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.soccu || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Cinématique mandibulaire</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.cinematiqueMandibulaire || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Ouverture buccale</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.ouvertureBuccale || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Température buccale</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.temperatureBuccale || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Amplitude d'ouverture</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.amplitudeOuverture || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Bruits articulaires</span>
                        <span class="fiche-book__value">{{ examens.endobuccalBoucheOuverte?.bruitsArticulaires || '—' }}</span>
                    </div>

                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Canaux excréteurs</span>
                        <span class="fiche-book__value">{{ examens.examenCanauxExcreteurs || '—' }}</span>
                    </div>

                    <h4 class="fiche-book__title">Tissus mous</h4>
                    <div class="fiche-book__block">
                        <table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th v-for="col in tissusMousColumns" :key="col">{{ col }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in tissusMousRows" :key="row">
                                    <td>{{ row }}</td>
                                    <td v-for="col in tissusMousColumns" :key="col">{{ examens.tissusMousTable?.[row]?.[col] || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="fiche-book__title">Tissus durs</h4>
                    <div class="fiche-book__block">
                        <table>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th v-for="col in tissusDursColumns" :key="col">{{ col }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in tissusDursRows" :key="row">
                                    <td>{{ row }}</td>
                                    <td v-for="col in tissusDursColumns" :key="col">{{ examens.tissusDursTable?.[row]?.[col] || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="fiche-book__title">Examens biologiques</h4>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Bactériologiques</span>
                        <span class="fiche-book__value">{{ examens.examensBacteriologiques?.observation || '—' }} — {{ examens.examensBacteriologiques?.resultat || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Sérologiques</span>
                        <span class="fiche-book__value">{{ examens.examensSerologiques?.observation || '—' }} — {{ examens.examensSerologiques?.resultat || '—' }}</span>
                    </div>
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Histologiques</span>
                        <span class="fiche-book__value">{{ examens.examensHistologiques?.observation || '—' }} — {{ examens.examensHistologiques?.resultat || '—' }}</span>
                    </div>

                    <h4 class="fiche-book__title">Examens complémentaires</h4>
                    <div class="fiche-book__block">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Résultat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!examensLabo.length">
                                    <td colspan="4">Aucun examen complémentaire.</td>
                                </tr>
                                <tr v-for="(item, idx) in examensLabo" :key="idx">
                                    <td>{{ item.type || '—' }}</td>
                                    <td>{{ item.description || '—' }}</td>
                                    <td>{{ formatDateShort(item.date) }}</td>
                                    <td>{{ item.resultat || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="examens.diagnosticSupposeExamens" class="fiche-book__line">
                        <span class="fiche-book__label">Diagnostic supposé</span>
                        <span class="fiche-book__value">{{ examens.diagnosticSupposeExamens }}</span>
                    </div>

                    <h3 class="fiche-book__title">Images et documents</h3>
                    <template v-if="documentsView.length">
                        <div v-for="(item, idx) in documentsView" :key="idx" class="fiche-book__block">
                            <div class="fiche-book__line">
                                <span class="fiche-book__label">{{ item.type }}</span>
                                <span class="fiche-book__value">{{ item.title }}</span>
                            </div>
                            <div v-if="item.entries.length" class="flex flex-wrap gap-2">
                                <button
                                    v-for="entry in item.entries"
                                    :key="entry.entryKey"
                                    type="button"
                                    class="fiche-book__thumb"
                                    @click="openPreviewByKey(entry.entryKey)"
                                >
                                    <img v-if="entry.isImage && entry.previewSrc" :src="entry.previewSrc" :alt="item.title" />
                                    <span v-else>{{ entry.extension || 'fichier' }}</span>
                                </button>
                            </div>
                            <p v-else class="fiche-book__empty">Aucun fichier attaché.</p>
                        </div>
                    </template>
                    <p v-else class="fiche-book__empty">Aucun document.</p>

                    <h3 class="fiche-book__title">Plan de traitement</h3>
                    <p v-if="!sortedPlans.length" class="fiche-book__empty">Aucun plan de traitement.</p>
                    <div v-for="(plan, idx) in sortedPlans" :key="plan.id || idx" class="fiche-book__line">
                        <span class="fiche-book__label">{{ plan.type || `Plan ${idx + 1}` }} — {{ formatPlanDate(plan.dateSupposed) }}</span>
                        <span class="fiche-book__value">{{ plan.description || 'Aucune description.' }}</span>
                    </div>

                    <h3 class="fiche-book__title">Bilan</h3>
                    <div class="fiche-book__block">
                        <div class="mb-2">
                            <SelectButton v-model="dentitionType" :options="DENTITION_OPTIONS" optionLabel="label" optionValue="value" :allowEmpty="false" />
                        </div>
                        <FormuleDentaireReadonly :modelValue="bilans.bilanDentaire?.formuleDentaire" :dentition-type="dentitionType" />
                    </div>
                    <h4 class="fiche-book__title">Bilan radiographique</h4>
                    <ReadonlyFieldGrid :fields="bilanRadiographiqueFields" :columns="1" />
                    <h4 class="fiche-book__title">Bilan sanguin</h4>
                    <ReadonlyFieldGrid :fields="bilanSanguinFields" :columns="1" />
                    <div class="fiche-book__line">
                        <span class="fiche-book__label">Diagnostic positif</span>
                        <span class="fiche-book__value">{{ diagnosticPositifValue || '—' }}</span>
                    </div>

                    <DevisForm v-if="devisModel.devisList.length" :modelValue="devisModel" readonly layout="book" />
                    <template v-else>
                        <h3 class="fiche-book__title">Devis</h3>
                        <p class="fiche-book__empty">Aucun devis enregistré.</p>
                    </template>

                    <SeancesSection v-if="sessions.length" :sessions="sessions" layout="book" />
                    <template v-else>
                        <h3 class="fiche-book__title">Séances passées</h3>
                        <p class="fiche-book__empty">Aucune séance précédente.</p>
                    </template>
                </div>
            </div>

            <div v-if="!hideActions" class="px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-900/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Button icon="pi pi-print" label="Imprimer" severity="secondary" outlined size="small" @click="emit('print')" :pt="{ label: { class: 'hidden sm:inline' } }" />
                    </div>
                    <div class="text-sm text-surface-600 dark:text-surface-400">Dernière modification : {{ formatDate(props.fiche?.createdAt || props.fiche?.dateCreation) }}</div>
                </div>
            </div>
        </div>

        <Galleria
            v-model:visible="previewVisible"
            v-model:activeIndex="previewIndex"
            :value="previewItems"
            :numVisible="7"
            :fullScreen="true"
            :showThumbnails="false"
            :showItemNavigators="false"
            :circular="true"
            :pt="galleriaPt"
            containerClass="w-screen h-screen"
        >
            <template #item="{ item }">
                <div class="relative flex h-full w-full flex-col items-center justify-center gap-6 bg-transparent px-6 py-8">
                    <button
                        v-if="previewItems.length > 1"
                        type="button"
                        class="absolute left-0 top-1/2 -translate-y-1/2 z-10 ml-3 inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white hover:bg-white/20"
                        @click.stop="goPrevPreview"
                    >
                        <i class="pi pi-chevron-left"></i>
                    </button>
                    <button
                        v-if="previewItems.length > 1"
                        type="button"
                        class="absolute right-0 top-1/2 -translate-y-1/2 z-10 mr-3 inline-flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white hover:bg-white/20"
                        @click.stop="goNextPreview"
                    >
                        <i class="pi pi-chevron-right"></i>
                    </button>
                    <div class="flex flex-1 items-center justify-center">
                        <img v-if="item.isImage && item.previewSrc" :src="item.previewSrc" :alt="item.title" class="max-h-[70vh] w-auto max-w-[90vw] rounded-2xl shadow-xl" />
                        <div v-else class="flex flex-col items-center justify-center text-center text-white/90">
                            <div class="h-28 w-28 rounded-3xl bg-white/10 flex items-center justify-center">
                                <i :class="['pi', item.icon, 'text-4xl text-white']"></i>
                            </div>
                        </div>
                    </div>
                    <div class="text-center text-white/90">
                        <div class="text-lg font-semibold">{{ item.fileName || item.title }}</div>
                        <div class="text-sm text-white/70 mt-1 max-w-xl break-words">{{ item.description }}</div>
                        <a v-if="item.downloadUrl" :href="item.downloadUrl" download class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm text-white hover:bg-white/10">
                            <i class="pi pi-download"></i>
                            Telecharger
                        </a>
                    </div>
                </div>
            </template>
        </Galleria>
    </div>
</template>

