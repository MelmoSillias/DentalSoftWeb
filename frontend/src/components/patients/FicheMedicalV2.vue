<script setup>
import Button from 'primevue/button';
import Card from 'primevue/card';
import Galleria from 'primevue/galleria';
import Timeline from 'primevue/timeline';
import { computed, ref } from 'vue';
import { filePrefix } from '@/config';
import FormuleDentaireReadonly from '@/components/fiche-medicale/FormuleDentaireReadonly.vue';
import SeancesSection from '@/components/fiche-medicale/SeancesSection.vue';

const props = defineProps({
    fiche: {
        type: Object,
        required: true
    },
    positionLabel: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['print']);

const activeSection = ref(0);

const sections = [
    { title: 'Entretien verbal', icon: 'pi pi-file-edit' },
    { title: 'Examen', icon: 'pi pi-stethoscope' },
    { title: 'Images et Docs', icon: 'pi pi-images' },
    { title: 'Plan de traitement', icon: 'pi pi-sitemap' },
    { title: 'Bilan', icon: 'pi pi-clipboard' },
    { title: 'Devis', icon: 'pi pi-file' },
    { title: 'Seances passees', icon: 'pi pi-history' }
];

const entretien = computed(() => props.fiche?.entretien || {});
const examens = computed(() => props.fiche?.examens || {});
const bilans = computed(() => props.fiche?.bilans || {});
const documents = computed(() => props.fiche?.documents || []);
const devis = computed(() => (Array.isArray(props.fiche?.devis) ? props.fiche.devis : props.fiche?.devis ? [props.fiche.devis] : []));
const plansTraitement = computed(() => props.fiche?.planTraitement || []);
const consultations = computed(() => props.fiche?.consultations || []);

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
            return 'pi-file-powerpoint';
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

const iconMap = {
    Urgence: { icon: 'pi pi-bolt', color: '#ef4444' },
    Dentaires: { icon: 'pi pi-tooth', color: '#0ea5e9' },
    Parodontaux: { icon: 'pi pi-heart', color: '#22c55e' },
    Orthodontiques: { icon: 'pi pi-sliders-h', color: '#f59e0b' },
    Autres: { icon: 'pi pi-briefcase', color: '#64748b' }
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

const timelineEvents = computed(() =>
    sortedPlans.value.map((plan, idx) => {
        const type = plan.type || 'Autres';
        const iconMeta = iconMap[type] || iconMap.Autres;
        return {
            status: plan.type || `Plan ${idx + 1}`,
            date: formatPlanDate(plan.dateSupposed),
            icon: iconMeta.icon,
            color: iconMeta.color,
            description: plan.description || 'Aucune description.',
            planIndex: plan.planIndex ?? idx + 1
        };
    })
);

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
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                        <i class="pi pi-file-medical text-primary-500"></i>
                        Fiche Medicale {{ positionLabel || '' }}
                    </h3>
                    <p class="text-sm text-surface-600 dark:text-surface-300 mt-1">
                        Creee le {{ formatDate(props.fiche?.dateCreation || props.fiche?.createdAt) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="p-5">
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
                            <span class="hidden sm:inline">{{ section.title }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="space-y-6">
                <div v-if="activeSection === 0" class="animate-fadeIn space-y-6">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
                            <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                                <i class="pi pi-file-edit text-primary-600 dark:text-primary-400 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Entretien verbal</h3>
                                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Motif, anamnese et habitudes</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Motif de consultation</h4>
                                <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ entretien.motifConsultation || '—' }}</p>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Anamnese</h4>
                                    <p class="text-surface-700 dark:text-surface-300 whitespace-pre-wrap">{{ entretien.anamnese || '—' }}</p>
                                </div>
                                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Etat gynecologique</h4>
                                    <div class="space-y-3 text-sm">
                                        <div class="flex items-center justify-between">
                                            <span class="text-surface-700 dark:text-surface-300">Allaitement</span>
                                            <span class="font-medium text-surface-900 dark:text-surface-100">{{ formatBool(entretien.etatGynecologique?.allaitement) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-surface-700 dark:text-surface-300">Grossesse en cours</span>
                                            <span class="font-medium text-surface-900 dark:text-surface-100">{{ formatBool(entretien.etatGynecologique?.grossesseEnCours) }}</span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-surface-700 dark:text-surface-300">Menstrues</span>
                                            <span class="font-medium text-surface-900 dark:text-surface-100">{{ formatBool(entretien.etatGynecologique?.menstrues) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="(entretien.medicaments || []).length" class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Medicaments en cours</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div v-for="med in entretien.medicaments" :key="med.id || med.nom" class="p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ med.nom }}</span>
                                            <span class="text-xs font-semibold" :class="med.estUtilise ? 'text-emerald-600' : 'text-surface-400'">
                                                {{ med.estUtilise ? 'Oui' : 'Non' }}
                                            </span>
                                        </div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400 mt-2">
                                            {{ med.details || '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="(entretien.affections || []).length" class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Affections</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div v-for="aff in entretien.affections" :key="aff.id || aff.nom" class="p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ aff.nom }}</span>
                                            <span class="text-xs font-semibold" :class="aff.estPresente ? 'text-emerald-600' : 'text-surface-400'">
                                                {{ aff.estPresente ? 'Oui' : 'Non' }}
                                            </span>
                                        </div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400 mt-2">
                                            {{ aff.details || '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="(entretien.questions || []).length" class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Questions</h4>
                                <div class="space-y-3">
                                    <div v-for="q in entretien.questions" :key="q.id || q.question" class="p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ q.question }}</span>
                                            <span class="text-xs font-semibold" :class="q.reponse ? 'text-emerald-600' : 'text-surface-400'">
                                                {{ q.reponse === true ? 'Oui' : q.reponse === false ? 'Non' : '--' }}
                                            </span>
                                        </div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400 mt-2">
                                            {{ q.precision || '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="(entretien.habitudes || []).length" class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Habitudes</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div v-for="h in entretien.habitudes" :key="h.id || h.type" class="p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ h.type }}</span>
                                            <span class="text-xs font-semibold" :class="h.estPresente ? 'text-emerald-600' : 'text-surface-400'">
                                                {{ h.estPresente ? 'Oui' : 'Non' }}
                                            </span>
                                        </div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400 mt-2">
                                            {{ h.quantite || '—' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeSection === 1" class="animate-fadeIn space-y-6">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
                            <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                                <i class="pi pi-stethoscope text-primary-600 dark:text-primary-400 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Examens cliniques</h3>
                                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Observation et examens locaux</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Exobuccal - Inspection</h4>
                                    <div class="space-y-2">
                                        <div v-for="[label, value] in mapEntries(examens.exobuccalInspection)" :key="label" class="p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                            <div class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</div>
                                            <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ value || '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Exobuccal - Palpation</h4>
                                    <div class="space-y-2">
                                        <div v-for="[label, value] in mapEntries(examens.exobuccalPalpation)" :key="label" class="p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                            <div class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</div>
                                            <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ value || '—' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Chaines ganglionnaires</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div v-for="[label, value] in mapEntries(examens.chainesGanglionnaires)" :key="label" class="flex items-center justify-between p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ label }}</span>
                                        <span class="text-xs font-semibold" :class="value ? 'text-emerald-600' : 'text-surface-400'">
                                            {{ value ? 'Oui' : 'Non' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen endobuccal</h4>
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <h5 class="text-sm font-semibold text-surface-700 dark:text-surface-300">Bouche fermee</h5>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Occlusion</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheFermee?.occlusion || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Mediane</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheFermee?.mediane || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Classes d'Angle</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheFermee?.classesAngle || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Vestibules</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheFermee?.vestibules || '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <h5 class="text-sm font-semibold text-surface-700 dark:text-surface-300">Bouche ouverte</h5>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">HBD</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.hbd || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Brossage</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.brossage || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Soccu</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.soccu || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Cinematique mandibulaire</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.cinematiqueMandibulaire || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Ouverture buccale</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.ouvertureBuccale || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Temperature buccale</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.temperatureBuccale || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Amplitude d'ouverture</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.amplitudeOuverture || '—' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30">
                                            <span class="text-sm text-surface-600 dark:text-surface-400">Bruits articulaires</span>
                                            <span class="text-sm font-medium text-surface-900 dark:text-surface-100">{{ examens.endobuccalBoucheOuverte?.bruitsArticulaires || '—' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <h5 class="text-sm font-semibold text-surface-700 dark:text-surface-300">Examen des canaux excreteurs</h5>
                                <div class="p-3 rounded-lg bg-surface-50 dark:bg-surface-700/30 text-surface-700 dark:text-surface-300">
                                    {{ examens.examenCanauxExcreteurs || '—' }}
                                </div>
                            </div>

                            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 overflow-x-auto">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen des tissus mous</h4>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="p-2 text-left"></th>
                                            <th v-for="col in tissusMousColumns" :key="col" class="p-2 text-left font-semibold text-surface-700 dark:text-surface-300">
                                                {{ col }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in tissusMousRows" :key="row" class="border-t border-surface-200 dark:border-surface-700">
                                            <td class="p-2 font-semibold text-surface-700 dark:text-surface-300">{{ row }}</td>
                                            <td v-for="col in tissusMousColumns" :key="col" class="p-2">
                                                <span class="text-surface-600 dark:text-surface-400">
                                                    {{ examens.tissusMousTable?.[row]?.[col] || '—' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 overflow-x-auto">
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Examen des tissus durs</h4>
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr>
                                            <th class="p-2 text-left"></th>
                                            <th v-for="col in tissusDursColumns" :key="col" class="p-2 text-left font-semibold text-surface-700 dark:text-surface-300">
                                                {{ col }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in tissusDursRows" :key="row" class="border-t border-surface-200 dark:border-surface-700">
                                            <td class="p-2 font-semibold text-surface-700 dark:text-surface-300">{{ row }}</td>
                                            <td v-for="col in tissusDursColumns" :key="col" class="p-2">
                                                <span class="text-surface-600 dark:text-surface-400">
                                                    {{ examens.tissusDursTable?.[row]?.[col] || '—' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-2">Examens bacteriologiques</h4>
                                    <p class="text-sm text-surface-600 dark:text-surface-400">Observation: {{ examens.examensBacteriologiques?.observation || '—' }}</p>
                                    <p class="text-sm text-surface-600 dark:text-surface-400">Resultat: {{ examens.examensBacteriologiques?.resultat || '—' }}</p>
                                </div>
                                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-2">Examens serologiques</h4>
                                    <p class="text-sm text-surface-600 dark:text-surface-400">Observation: {{ examens.examensSerologiques?.observation || '—' }}</p>
                                    <p class="text-sm text-surface-600 dark:text-surface-400">Resultat: {{ examens.examensSerologiques?.resultat || '—' }}</p>
                                </div>
                                <div class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700">
                                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-2">Examens histologiques</h4>
                                    <p class="text-sm text-surface-600 dark:text-surface-400">Observation: {{ examens.examensHistologiques?.observation || '—' }}</p>
                                    <p class="text-sm text-surface-600 dark:text-surface-400">Resultat: {{ examens.examensHistologiques?.resultat || '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeSection === 2" class="animate-fadeIn">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
                            <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                                <i class="pi pi-images text-primary-600 dark:text-primary-400 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Images et Docs</h3>
                                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Pieces jointes et documents associes</p>
                            </div>
                        </div>

                        <div v-if="documentsView.length" class="space-y-4">
                            <div v-for="(item, idx) in documentsView" :key="idx" class="rounded-2xl border border-surface-200/70 dark:border-surface-700/70 bg-surface-50 dark:bg-surface-800/30 p-5">
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <h4 class="text-base font-semibold text-surface-900 dark:text-surface-100">{{ item.title }}</h4>
                                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-1 break-words">{{ item.type }}</p>
                                    </div>
                                </div>

                                <div v-if="item.entries.length" class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <button
                                        v-for="entry in item.entries"
                                        :key="entry.entryKey"
                                        type="button"
                                        class="group relative flex h-20 sm:h-24 md:h-28 lg:h-32 items-center justify-center overflow-hidden rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900/40 transition-shadow hover:shadow-md"
                                        @click="openPreviewByKey(entry.entryKey)"
                                    >
                                        <img
                                            v-if="entry.isImage && entry.previewSrc"
                                            :src="entry.previewSrc"
                                            :alt="item.title"
                                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                        />
                                        <div v-else class="flex flex-col items-center justify-center gap-2 text-surface-500 dark:text-surface-400">
                                            <div class="h-10 w-10 sm:h-12 sm:w-12 rounded-xl bg-surface-100 dark:bg-surface-800 flex items-center justify-center">
                                                <i :class="['pi', entry.icon, 'text-lg']"></i>
                                            </div>
                                            <span class="text-[10px] sm:text-[11px] uppercase tracking-wide">
                                                {{ entry.extension || 'file' }}
                                            </span>
                                        </div>
                                        <div class="absolute inset-0 bg-primary-500/0 transition-colors group-hover:bg-primary-500/10"></div>
                                    </button>
                                </div>
                                <div v-else class="mt-4 text-xs text-surface-500 dark:text-surface-400">
                                    Aucun fichier attache.
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun document disponible.</p>
                    </div>
                </div>

                <div v-if="activeSection === 3" class="animate-fadeIn">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
                            <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                                <i class="pi pi-sitemap text-primary-600 dark:text-primary-400 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Plan de traitement</h3>
                                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Planifier les actes et priorites</p>
                            </div>
                        </div>

                        <div v-if="!timelineEvents.length" class="text-sm text-surface-500 dark:text-surface-400">
                            Aucun plan de traitement ajoute.
                        </div>

                        <Timeline v-else :value="timelineEvents" align="alternate" class="customized-timeline">
                            <template #marker="slotProps">
                                <span
                                    class="flex w-8 h-8 items-center justify-center text-white rounded-full z-10 shadow-sm"
                                    :style="{ backgroundColor: slotProps.item.color }"
                                >
                                    <i :class="slotProps.item.icon"></i>
                                </span>
                            </template>
                            <template #content="slotProps">
                                <Card class="mt-4">
                                    <template #title>
                                        {{ slotProps.item.status }}
                                    </template>
                                    <template #subtitle>
                                        {{ slotProps.item.date }}
                                    </template>
                                    <template #content>
                                        <p class="text-sm text-surface-600 dark:text-surface-300">
                                            {{ slotProps.item.description }}
                                        </p>
                                    </template>
                                </Card>
                            </template>
                        </Timeline>
                    </div>
                </div>

                <div v-if="activeSection === 4" class="animate-fadeIn">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
                            <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                                <i class="pi pi-clipboard text-primary-600 dark:text-primary-400 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Bilan</h3>
                                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Formule dentaire</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5">
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-4">Formule dentaire</h4>
                            <FormuleDentaireReadonly :modelValue="bilans.bilanDentaire?.formuleDentaire" />
                        </div>
                    </div>
                </div>

                <div v-if="activeSection === 5" class="animate-fadeIn">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
                            <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                                <i class="pi pi-file text-primary-600 dark:text-primary-400 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Devis</h3>
                                <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Details des devis</p>
                            </div>
                        </div>

                        <div v-if="devis.length" class="space-y-4">
                            <div v-for="(item, index) in devis" :key="item.id || item.date || index" class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Devis du {{ formatDateShort(item.date) }}</h4>
                                        <p class="text-sm text-surface-600 dark:text-surface-400">{{ item.type || 'Devis' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm text-surface-500">Montant</div>
                                        <div class="font-semibold text-surface-900 dark:text-surface-100">{{ item.montant ?? 0 }}</div>
                                    </div>
                                </div>
                                <div v-if="item.reste !== undefined" class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                                    Reste: {{ item.reste }}
                                </div>
                                <div v-if="item.contenus?.length" class="mt-3 space-y-2">
                                    <div v-for="contenu in item.contenus" :key="contenu.id" class="flex items-center justify-between p-3 rounded-lg bg-surface-0 dark:bg-surface-800">
                                        <div class="font-medium text-surface-700 dark:text-surface-300">{{ contenu.designation || 'Service' }}</div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400">{{ contenu.qte || 1 }} x {{ contenu.montant || 0 }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun devis enregistre.</p>
                    </div>
                </div>

                <div v-if="activeSection === 6" class="animate-fadeIn">
                    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
                        <SeancesSection :sessions="sessions" />
                        <p v-if="!sessions.length" class="text-sm text-surface-500 dark:text-surface-400 mt-4">Aucune seance precedente.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-900/50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button icon="pi pi-print" label="Imprimer" severity="secondary" outlined size="small" @click="emit('print')" :pt="{ label: { class: 'hidden sm:inline' } }" />
                </div>
                <div class="text-sm text-surface-600 dark:text-surface-400">
                    Derniere modification : {{ formatDate(props.fiche?.createdAt || props.fiche?.dateCreation) }}
                </div>
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
                    <img
                        v-if="item.isImage && item.previewSrc"
                        :src="item.previewSrc"
                        :alt="item.title"
                        class="max-h-[70vh] w-auto max-w-[90vw] rounded-2xl shadow-xl"
                    />
                    <div v-else class="flex flex-col items-center justify-center text-center text-white/90">
                        <div class="h-28 w-28 rounded-3xl bg-white/10 flex items-center justify-center">
                            <i :class="['pi', item.icon, 'text-4xl text-white']"></i>
                        </div>
                    </div>
                </div>
                <div class="text-center text-white/90">
                    <div class="text-lg font-semibold">{{ item.fileName || item.title }}</div>
                    <div class="text-sm text-white/70 mt-1 max-w-xl break-words">{{ item.description }}</div>
                    <a
                        v-if="item.downloadUrl"
                        :href="item.downloadUrl"
                        download
                        class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2 text-sm text-white hover:bg-white/10"
                    >
                        <i class="pi pi-download"></i>
                        Telecharger
                    </a>
                </div>
            </div>
        </template>
    </Galleria>
</template>

<style scoped>
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-out;
}
</style>
