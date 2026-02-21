<script setup>
import ProjectCreateDialog from '@/components/projects/ProjectCreateDialog.vue';
import { apiPrefix } from '@/config';
import { fetchClients } from '@/services/olders/clientApi';
import * as fsService from '@/services/olders/fsService';
import { addProjectTimeline, deleteProjectTimeline, getGeoSheet, getProjectTimeline } from '@/services/olders/topoApi';
import { computeCalculations } from '@/services/olders/topoCalc';
import { exportTopoPDFCombined } from '@/services/olders/topoExport';
import { ensureFsRoot, saveDefaultFiles } from '@/services/projectFs';
import { useAuthStore } from '@/stores/auth';
import http from '@/service/http';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Menu from 'primevue/menu';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Timeline from 'primevue/timeline';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const axios = http;

const toast = useToast();
const token = localStorage.getItem('token');
const router = useRouter();
const auth = useAuthStore();
const isAdmin = computed(() => auth.user?.roles?.includes('ROLE_ADMIN'));
const isTopo = computed(() => auth.user?.roles?.includes('ROLE_TOPO'));
const confirmPopup = useConfirm();

const mode = ref('paste'); // pour le modal d'ajout de parcelle seule
const pasteText = ref('');
const points = ref([]);
const projects = ref([]);
// Filtres
const filterProjectTitle = ref('');
const filterProjectQuery = ref('');
const filterParcelNumber = ref('');
const filterParcelStatus = ref(null);
const selectedProject = ref(null);
const createProjectDialogVisible = ref(false);
const creatingGeoSheet = ref(false);
const parcelNumber = ref('');
const parcelReference = ref('');
const parcelNumberValid = ref(true);
const parcelNumberChecking = ref(false);
const parcelNumberMsg = ref('');
const geoStatus = ref('active');
const projectStatusOptions = ref([
    { label: 'En cours', value: 'ongoing' },
    { label: 'Terminé', value: 'done' }
]);
const geoStatusOptions = ref([
    { label: 'Actif', value: 'active' },
    { label: 'Brouillon', value: 'draft' },
    { label: 'Archivé', value: 'archived' }
]);
const clients = ref([]);
const clientsLoading = ref(false);
const editProjectClientId = ref(null);
// workTypeOptions non utilisé — supprimé pour corriger le lint
const projectIdForGeo = ref(null);
const finishingProjectId = ref(null);
const projectStatusLabels = {
    ongoing: 'En cours',
    done: 'Terminé',
    draft: 'En cours'
};
const projectStatusSeverity = {
    ongoing: 'info',
    done: 'success'
};
const exportingProjectId = ref(null);
const exportResultDialogVisible = ref(false);
const exportResultMessage = ref('');
const exportResultUrl = ref(null);
const exportResultFilename = ref('');
const actionMenus = ref({});
const requestDialogVisible = ref(false);
const requestGenerating = ref(false);
const requestSurfaceLoading = ref(false);
const requestForm = ref({
    type: 'titre_foncier',
    object: 'Demande de Titre Foncier',
    destinataire: 'Monsieur le Directeur régional des domaines et du Cadastre',
    direction: '',
    parcelId: null,
    parcelNumber: '',
    planName: '',
    surface: '',
    locality: '',
    demandeur: '',
    attachments: '',
    customNote: ''
});
const requestTemplates = {
    titre_foncier: {
        label: 'Titre Foncier',
        object: 'Demande de Titre Foncier',
        destinataire: 'Monsieur le Directeur régional des domaines et du Cadastre',
        attachments: [
            'Photocopie de la lettre d\'attribution',
            "Photocopie de la Carte d'identité",
            'Copie de la réquisition du Cercle de Kati',
            'Photocopie du reçu de la redevance domaniale',
            'Extrait de plan du site',
            "Rapport d'évaluation"
        ],
        compose: (ctx) => ({
            subject: ctx.object || 'Demande de Titre Foncier',
            paragraphs: [
                `Objet : ${ctx.object || 'Demande de Titre Foncier'}`,
                'A',
                `${ctx.destinataire}${ctx.direction ? ' de ' + ctx.direction : ''}.`,
                'Monsieur,',
                `J’ai l’honneur de solliciter de votre haute bienveillance l’acceptation de cette demande relative à la parcelle N°${ctx.parcelNumber || '______'} située dans le parcellement officiel ${ctx.planName || '______'}, d’une superficie de ${ctx.surface || '______'}.`,
                "Aussi je souhaiterais que la réquisition des travaux topographiques soit adressée au cabinet ‘’C.E.T.I.G’’ de M. Hamidou TOGOLA, géomètre expert.",
                'Dans l’attente d’une suite favorable, veuillez agréer monsieur le Directeur l’expression de mes sentiments les plus respectueux.'
            ],
            requesterLine: ctx.demandeur ? `Mr / Mme. ${ctx.demandeur}` : '',
            attachments: ctx.attachments
        })
    },
    morcellement: {
        label: 'Morcellement / Lotissement',
        object: 'Demande de morcellement de parcelle',
        destinataire: 'Monsieur le Maire',
        attachments: ['Plan de situation', 'Proposition de découpe', 'Justificatif de propriété', 'Copie de la pièce d’identité'],
        compose: (ctx) => ({
            subject: ctx.object || 'Demande de morcellement',
            paragraphs: [
                `Objet : ${ctx.object || 'Demande de morcellement'}`,
                'A',
                `${ctx.destinataire}${ctx.direction ? ' de ' + ctx.direction : ''}.`,
                'Monsieur,',
                `Je viens par la présente solliciter l’autorisation de morceler la parcelle N°${ctx.parcelNumber || '______'} sise à ${ctx.locality || ctx.planName || '______'} afin de procéder à une redistribution conforme au plan joint. La superficie concernée est estimée à ${ctx.surface || '______'}.`,
                'Je reste disponible pour toute visite technique ou information complémentaire.',
                'Dans l’attente de votre retour, veuillez agréer Monsieur l’expression de ma considération distinguée.'
            ],
            requesterLine: ctx.demandeur ? `Mr / Mme. ${ctx.demandeur}` : '',
            attachments: ctx.attachments
        })
    },
    mutation: {
        label: 'Mutation / Transfert',
        object: 'Demande de mutation',
        destinataire: 'Monsieur le Directeur des Domaines',
        attachments: ['Copie du titre ou attribution', 'Justificatif d’identité des parties', 'Acte de cession ou compromis', 'Quitus fiscal le cas échéant'],
        compose: (ctx) => ({
            subject: ctx.object || 'Demande de mutation',
            paragraphs: [
                `Objet : ${ctx.object || 'Demande de mutation'}`,
                'A',
                `${ctx.destinataire}${ctx.direction ? ' de ' + ctx.direction : ''}.`,
                'Monsieur,',
                `Je sollicite l’enregistrement de la mutation portant sur la parcelle N°${ctx.parcelNumber || '______'} située à ${ctx.locality || '______'} (${ctx.planName || 'plan'}). La parcelle couvre environ ${ctx.surface || '______'}.`,
                "L’opération concerne le transfert de droits conformément aux pièces jointes et doit être portée aux registres fonciers en vigueur.",
                'Je vous prie de bien vouloir y donner la suite favorable.'
            ],
            requesterLine: ctx.demandeur ? `Mr / Mme. ${ctx.demandeur}` : '',
            attachments: ctx.attachments
        })
    }
};
const requestTypeOptions = Object.keys(requestTemplates).map((key) => ({ label: requestTemplates[key].label, value: key }));
const timelineDialogVisible = ref(false);
const timelineLoading = ref(false);
const timelineProject = ref(null);
const timelineEvents = ref([]);
const timelineSubmitting = ref(false);
const timelineForm = ref({ date: new Date().toISOString().slice(0, 16), description: '', type: 'milestone' });
const timelineTypeOptions = [
    { label: 'Jalon', value: 'milestone' },
    { label: 'Info', value: 'info' },
    { label: 'Alerte', value: 'alert' }
];
const timelineTypeMeta = {
    milestone: { label: 'Jalon', color: '#10b981', icon: 'pi pi-flag' },
    info: { label: 'Info', color: '#3b82f6', icon: 'pi pi-info-circle' },
    alert: { label: 'Alerte', color: '#f59e0b', icon: 'pi pi-exclamation-triangle' }
};
const showExportResult = ({ message, blob, filename }) => {
    if (exportResultUrl.value) {
        URL.revokeObjectURL(exportResultUrl.value);
        exportResultUrl.value = null;
    }
    exportResultMessage.value = message;
    exportResultFilename.value = filename || '';
    exportResultUrl.value = blob ? URL.createObjectURL(blob) : null;
    exportResultDialogVisible.value = true;
};

const closeExportResultDialog = () => {
    if (exportResultUrl.value) {
        URL.revokeObjectURL(exportResultUrl.value);
        exportResultUrl.value = null;
    }
    exportResultDialogVisible.value = false;
};

const openExportResultFile = () => {
    if (typeof window === 'undefined') return;
    if (!exportResultUrl.value) return;
    window.open(exportResultUrl.value, '_blank');
};

const registerActionMenu = (el, id) => {
    if (el) {
        actionMenus.value[id] = el;
    }
};

const toggleProjectMenu = (event, project) => {
    selectedProject.value = project;
    const menu = actionMenus.value[project.id];
    if (menu) {
        menu.toggle(event);
    }
};

const projectMenuItems = (project) => {
    const items = [];
    if (isAdmin.value || isTopo.value) {
        items.push({ label: 'Ajouter une parcelle', icon: 'pi pi-plus', command: () => openCreateGeo(project) });
        items.push({ label: 'Exporter PDF unique', icon: 'pi pi-file-pdf', disabled: exportingProjectId.value === project.id, command: () => exportProjectAllInOne(project) });
        items.push({ label: 'Imprimer une demande', icon: 'pi pi-file', command: () => openRequestDialog(project) });
    }
    if ((isAdmin.value || isTopo.value) && project.status !== 'done') {
        items.push({ label: 'Marquer terminé', icon: 'pi pi-check', disabled: finishingProjectId.value === project.id, command: () => markProjectDone(project) });
    }
    if (isAdmin.value || isTopo.value) {
        items.push({ label: 'Modifier', icon: 'pi pi-pencil', command: () => openEditGeo(project) });
    }

    items.push({ label: 'Voir le projet', icon: 'pi pi-eye', command: () => router.push({ name: 'topo-projet-apercu', params: { projectId: project.id } }) });
    items.push({ label: 'Voir la timeline', icon: 'pi pi-calendar', command: () => openTimelineModal(project) });
    items.push({ label: 'Ajouter une étape', icon: 'pi pi-plus-circle', disabled: project.status === 'done', command: () => openTimelineModal(project) });

    if (isAdmin.value) {
        items.push({ separator: true });
        items.push({ label: 'Supprimer', icon: 'pi pi-trash', command: (evt) => confirmDeleteProject(evt?.originalEvent, project.id) });
    }

    return items;
};

const resetTimelineForm = () => {
    timelineForm.value = {
        date: new Date().toISOString().slice(0, 16),
        description: '',
        type: 'milestone'
    };
};

const loadTimeline = async (projectId) => {
    timelineLoading.value = true;
    try {
        const res = await getProjectTimeline(projectId, token);
        timelineEvents.value = Array.isArray(res.data) ? res.data : [];
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Timeline', detail: 'Impossible de charger la timeline.', life: 2500 });
    } finally {
        timelineLoading.value = false;
    }
};

const openTimelineModal = async (project) => {
    timelineProject.value = project;
    timelineDialogVisible.value = true;
    resetTimelineForm();
    await loadTimeline(project.id);
};

const submitTimelineEntry = async () => {
    if (!timelineProject.value) return;
    timelineSubmitting.value = true;
    try {
        const payload = { ...timelineForm.value };
        await addProjectTimeline(timelineProject.value.id, payload, token);
        toast.add({ severity: 'success', summary: 'Timeline', detail: 'Etape ajoutée.', life: 2000 });
        resetTimelineForm();
        await loadTimeline(timelineProject.value.id);
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Timeline', detail: "Impossible d'ajouter l'étape.", life: 3000 });
    } finally {
        timelineSubmitting.value = false;
    }
};

const confirmDeleteTimelineEntry = (event, entryId) => {
    confirmPopup.require({
        target: event?.currentTarget || event?.target,
        message: "Supprimer cette étape ?",
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            try {
                await deleteProjectTimeline(entryId, token);
                toast.add({ severity: 'success', summary: 'Timeline', detail: 'Etape supprimée.', life: 2000 });
                if (timelineProject.value) {
                    await loadTimeline(timelineProject.value.id);
                }
            } catch (e) {
                console.error(e);
                toast.add({ severity: 'error', summary: 'Timeline', detail: "Impossible de supprimer l'étape.", life: 3000 });
            }
        }
    });
};
// Modal de création GeoSheet
// title/subtitle supprimés du modèle GeoSheet
// Gestion de l'expansion des lignes projet
const expandedProjects = ref([]); // PrimeVue accepte un tableau de lignes ou un objet selon version

const clientOptions = computed(() =>
    clients.value.map((c) => ({
        label: `${c.name || c.nom || 'Sans nom'}${c.code ? ' (' + c.code + ')' : ''}${c.address ? ' • ' + c.address : ''}`,
        value: c.id
    }))
);
const requestParcelOptions = computed(() =>
    (selectedProject.value?.geoSheets || []).map((gs) => ({
        label: `${gs.parcelNumber || 'Parcelle'}${gs.reference ? ' — ' + gs.reference : ''}`,
        value: gs.id
    }))
);

// Load projects with root geosheets
const loadProjects = async () => {
    try {
        const res = await axios.get(`${apiPrefix}/projects`, { headers: { Authorization: `Bearer ${token}` } });
        projects.value = res.data;
    } catch (e) {
        console.error(e);
    }
};

const loadClients = async () => {
    try {
        clientsLoading.value = true;
        clients.value = await fetchClients(token);
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Clients', detail: 'Impossible de charger les clients.', life: 3000 });
    } finally {
        clientsLoading.value = false;
    }
};

const projectStats = computed(() => {
    const total = projects.value.length;
    let ongoing = 0;
    let done = 0;
    let parcels = 0;
    projects.value.forEach((p) => {
        if (p.status === 'done') done += 1;
        else ongoing += 1;
        parcels += Array.isArray(p.geoSheets) ? p.geoSheets.length : 0;
    });
    return { total, ongoing, done, parcels };
});

const openCreateProject = () => {
    createProjectDialogVisible.value = true;
};

const handleProjectCreated = () => {
    createProjectDialogVisible.value = false;
    loadProjects();
    loadClients();
};

const statusLabel = (status) => projectStatusLabels[status] || status;

const markProjectDone = async (project) => {
    try {
        finishingProjectId.value = project.id;
        await axios.put(
            `${apiPrefix}/projects/${project.id}`,
            { status: 'done' },
            { headers: { Authorization: `Bearer ${token}` } }
        );
        toast.add({ severity: 'success', summary: 'Projet terminé', detail: `Projet #${project.id} marqué terminé`, life: 3000 });
        await loadProjects();
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de terminer le projet', life: 3000 });
    } finally {
        finishingProjectId.value = null;
    }
};

const applyRequestTemplateDefaults = (type) => {
    const tpl = requestTemplates[type] || requestTemplates.titre_foncier;
    requestForm.value.object = tpl.object;
    requestForm.value.destinataire = tpl.destinataire;
    requestForm.value.attachments = (tpl.attachments || []).join('\n');
};

const syncParcelInfo = async (parcelId) => {
    if (!parcelId) return;
    const parcel = selectedProject.value?.geoSheets?.find((gs) => gs.id === parcelId);
    requestForm.value.parcelNumber = parcel?.parcelNumber || '';
    requestForm.value.planName = requestForm.value.planName || selectedProject.value?.locality || selectedProject.value?.title || '';
    requestSurfaceLoading.value = true;
    try {
        const res = await getGeoSheet(parcelId, token);
        const calc = computeCalculations(res.data);
        if (calc?.area) {
            const readable = calc.readableArea ? ` (${calc.readableArea})` : '';
            requestForm.value.surface = `${calc.area.toFixed(2)} m²${readable}`;
        }
    } catch (e) {
        console.warn('Surface indisponible pour la parcelle', parcelId, e);
    } finally {
        requestSurfaceLoading.value = false;
    }
};

const openRequestDialog = async (project) => {
    selectedProject.value = project;
    const tplKey = requestForm.value.type || 'titre_foncier';
    const tpl = requestTemplates[tplKey] || requestTemplates.titre_foncier;
    requestForm.value = {
        type: tplKey,
        object: tpl.object,
        destinataire: tpl.destinataire,
        direction: project?.locality || '',
        parcelId: project?.geoSheets?.[0]?.id || null,
        parcelNumber: project?.geoSheets?.[0]?.parcelNumber || '',
        planName: project?.locality || project?.title || '',
        surface: '',
        locality: project?.locality || '',
        demandeur: auth.user?.name || auth.user?.username || '',
        attachments: (tpl.attachments || []).join('\n'),
        customNote: ''
    };
    requestDialogVisible.value = true;
    if (requestForm.value.parcelId) {
        await syncParcelInfo(requestForm.value.parcelId);
    }
};

const openCreateGeo = (proj) => {
    creatingGeoSheet.value = true;
    selectedProject.value = proj;
    projectIdForGeo.value = proj.id;
    parcelNumber.value = '';
    parcelReference.value = '';
    geoStatus.value = 'active';
    points.value = [];
    pasteText.value = '';
    mode.value = 'paste';
};

const editingProject = ref(false);
const editProjectTitle = ref('');
const editProjectLocality = ref('');
const editProjectStatus = ref('');
const openEditGeo = (proj) => {
    editingProject.value = true;
    selectedProject.value = proj;
    editProjectTitle.value = proj.title;
    editProjectLocality.value = proj.locality;
    editProjectStatus.value = projectStatusLabels[proj.status] ? proj.status : 'ongoing';
    editProjectClientId.value = proj.clientId || null;
    loadClients();
};

const saveEditProject = async () => {
    try {
        const payload = {
            title: editProjectTitle.value,
            locality: editProjectLocality.value,
            status: editProjectStatus.value,
            clientId: editProjectClientId.value || null
        };
        const res = await axios.put(`${apiPrefix}/projects/${selectedProject.value.id}`, payload, { headers: { Authorization: `Bearer ${token}` } });
        toast.add({ severity: 'success', summary: 'Projet modifié', detail: `ID ${res.data.id}`, life: 3000 });
        editingProject.value = false;
        selectedProject.value = null;
        loadProjects();
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Modification projet impossible', life: 3000 });
    }
};

// Normaliser un nombre en chaîne avec 6 décimales (préserve les zéros)
const toFixed6Str = (val) => {
    const num = typeof val === 'number' ? val : parseFloat(String(val).replace(',', '.'));
    if (Number.isFinite(num)) return num.toFixed(6);
    return '0.000000';
};

const parsePaste = () => {
    points.value = [];
    const lines = pasteText.value
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter(Boolean);
    let idx = 1;
    for (const line of lines) {
        // try to extract X=number and Y=number
        const rx = /X\s*=\s*([-+]?\d*\.?\d+(?:[eE][-+]?\d+)?)/i;
        const ry = /Y\s*=\s*([-+]?\d*\.?\d+(?:[eE][-+]?\d+)?)/i;
        const mx = line.match(rx);
        const my = line.match(ry);
        if (mx && my) {
            points.value.push({ designation: `B${idx}`, x: toFixed6Str(mx[1]), y: toFixed6Str(my[1]) });
            idx++;
        }
    }
    toast.add({ severity: 'info', summary: 'Parsing', detail: `${points.value.length} point(s) extraits`, life: 3000 });
};

const addPointManual = () => {
    const idx = points.value.length + 1;
    points.value.push({ designation: `B${idx}`, x: '0.000000', y: '0.000000' });
};

const removePoint = (i) => points.value.splice(i, 1);

// Validation: au moins 3 points distincts par coordonnées
const hasMinDistinctPoints = () => {
    if (!Array.isArray(points.value) || points.value.length < 3) return false;
    const set = new Set(
        points.value.map((p) => `${Number.isFinite(Number(p.x)) ? parseFloat(p.x) : 0}|${Number.isFinite(Number(p.y)) ? parseFloat(p.y) : 0}`)
    );
    return set.size >= 3;
};

let pnTimer;
const validateParcelNumberServer = async (num) => {
    if (!num || num.length < 2) {
        parcelNumberValid.value = false;
        parcelNumberMsg.value = '';
        return;
    }
    if (/^\/\d+$/.test(num)) {
        parcelNumberValid.value = true;
        parcelNumberMsg.value = '';
        return;
    }
    try {
        parcelNumberChecking.value = true;
        const res = await axios.get(`${apiPrefix}/geosheets/validate/parcel`, {
            params: { number: num },
            headers: { Authorization: `Bearer ${token}` }
        });
        parcelNumberValid.value = !!res.data?.valid;
        parcelNumberMsg.value = res.data?.reason || '';
    } catch (e) {
        parcelNumberValid.value = true; // ne pas bloquer en cas d'erreur réseau
        parcelNumberMsg.value = '';
    } finally {
        parcelNumberChecking.value = false;
    }
};

watch(parcelNumber, (v) => {
    clearTimeout(pnTimer);
    pnTimer = setTimeout(() => validateParcelNumberServer(v), 300);
});

watch(
    () => requestForm.value.type,
    (type) => {
        applyRequestTemplateDefaults(type);
    }
);

watch(
    () => requestForm.value.parcelId,
    (id) => {
        if (id) syncParcelInfo(id);
    }
);

const canCreateParcel = computed(() => !!parcelNumber.value && parcelNumberValid.value && hasMinDistinctPoints());

const createGeo = async () => {
    try {
        if (!parcelNumber.value) {
            toast.add({ severity: 'warn', summary: 'Parcelle manquante', detail: 'Numéro parcelle requis', life: 3000 });
            return;
        }
        if (!hasMinDistinctPoints()) {
            toast.add({ severity: 'warn', summary: 'Points insuffisants', detail: 'Au moins 3 points distincts requis.', life: 3000 });
            return;
        }
        if (!parcelNumberValid.value) {
            toast.add({ severity: 'warn', summary: 'Numéro invalide', detail: parcelNumberMsg.value || 'Numéro déjà utilisé.', life: 3000 });
            return;
        }
        const payload = {
            parcelNumber: parcelNumber.value,
            reference: parcelReference.value || null,
            status: geoStatus.value,
            projectId: projectIdForGeo.value,
            points: points.value.map((p) => ({
                designation: p.designation,
                x: Number.isFinite(Number(p.x)) ? parseFloat(p.x) : 0,
                y: Number.isFinite(Number(p.y)) ? parseFloat(p.y) : 0
            }))
        };
        const res = await axios.post(`${apiPrefix}/geosheets`, payload, { headers: { Authorization: `Bearer ${token}` } });
        toast.add({ severity: 'success', summary: 'Parcelle créée', detail: `ID ${res.data.id}`, life: 3000 });
        const projectTitleLocal = selectedProject.value?.title || '';
        const projectLocality = selectedProject.value?.locality || '';
        await saveDefaultFiles(projectTitleLocal, projectLocality, res.data, (payload) => toast.add(payload));
        creatingGeoSheet.value = false;
        loadProjects();
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Création de la parcelle impossible', life: 3000 });
    }
};

const previewGeoSheet = (projectId, geoId) => router.push({ name: 'topo-projet-apercu', params: { projectId, geoId } });

const exportProjectAllInOne = async (proj) => {
    if (!proj?.geoSheets?.length) {
        toast.add({ severity: 'warn', summary: 'Export', detail: 'Aucune parcelle à exporter.', life: 3000 });
        return;
    }
    const ready = await ensureFsRoot((payload) => toast.add(payload));
    if (!ready) return;
    exportingProjectId.value = proj.id;
    try {
        const parcels = [];
        for (const gs of proj.geoSheets) {
            try {
                const res = await getGeoSheet(gs.id, token);
                const geo = res.data;
                parcels.push({
                    parcelNumber: geo.parcelNumber || gs.parcelNumber,
                    calculations: computeCalculations(geo),
                    reference: geo.reference,
                    id: geo.id,
                    title: geo.title
                });
            } catch (e) {
                console.warn('Impossible de charger la parcelle', gs.id, e);
            }
        }
        if (!parcels.length) {
            toast.add({ severity: 'warn', summary: 'Export', detail: 'Aucune parcelle valide à exporter.', life: 3000 });
            return;
        }

        const { blob, filename } = await exportTopoPDFCombined({
            project: { title: proj.title, locality: proj.locality },
            parcels,
            sections: ['coord', 'retour', 'superficie'],
            mode: 'single',
            orientation: 'portrait'
        });
        const manualName = `manuel_${filename}`;
        await fsService.writeFileInProject(proj.title, manualName, blob, false);
        showExportResult({ message: `PDF unique enregistré (${manualName}).`, blob, filename: manualName });
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Export', detail: 'Échec de l\'export tout-en-un.', life: 3500 });
    } finally {
        exportingProjectId.value = null;
    }
};

const buildRequestContent = (type, form) => {
    const tpl = requestTemplates[type] || requestTemplates.titre_foncier;
    const attList = (form.attachments || '')
        .split(/\r?\n/)
        .map((s) => s.trim())
        .filter(Boolean);
    const ctx = {
        ...form,
        attachments: attList.length ? attList : tpl.attachments
    };
    return tpl.compose(ctx);
};

const renderRequestHtml = (content) => {
    const attachmentsHtml = (content.attachments || [])
        .map((a) => `<li>${a}</li>`)
        .join('');
    const paragraphsHtml = (content.paragraphs || [])
        .map((p) => `<p class="para">${p}</p>`)
        .join('');
    const requester = content.requesterLine ? `<p class="sign">${content.requesterLine}</p>` : '';
    const note = requestForm.value.customNote ? `<p class="para">${requestForm.value.customNote}</p>` : '';
    const destBlock = `${content.destinataire || ''}${requestForm.value.direction ? '<br />' + requestForm.value.direction : ''}`;
    const cabinetBlock = 'Cabinet C.E.T.I.G<br />Directeur : M. Hamidou TOGOLA';

    return `<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>${content.subject || 'Demande'}</title>
    <style>
        body { font-family: 'Times New Roman', serif; margin: 0; padding: 0; background: #f6f7fb; }
        .page { max-width: 820px; margin: 24px auto; background: #fff; padding: 46px 56px; box-shadow: 0 15px 45px rgba(0,0,0,0.08); }
        .top-row { display: flex; justify-content: space-between; font-size: 13px; line-height: 1.5; margin-bottom: 12px; }
        .top-left { text-align: left; font-weight: 700; }
        .top-right { text-align: right; font-weight: 700; }
        .dest-row { display: flex; justify-content: flex-end; font-size: 13px; line-height: 1.4; margin-bottom: 14px; }
        .subject { font-size: 15px; font-weight: 700; margin-bottom: 14px; text-decoration: underline; }
        .para { font-size: 14px; margin: 0 0 12px 0; text-align: justify; line-height: 1.5; }
        .para.indent { margin-left: 24px; text-align: left; }
        .list-title { font-weight: 700; margin: 12px 0 6px 0; }
        ul { margin: 0 0 10px 18px; padding: 0; font-size: 14px; line-height: 1.5; }
        li { margin-bottom: 6px; }
        .footer-row { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 28px; }
        .footer-left { width: 55%; }
        .footer-right { width: 40%; min-height: 70px; text-align: right; display: flex; flex-direction: column; justify-content: flex-end; }
        .sign { font-weight: 700; font-size: 14px; margin: 0; }
    </style>
</head>
<body onload="setTimeout(() => { window.print(); }, 250);">
    <div class="page">
        <div class="top-row">
            <div class="top-left">${cabinetBlock}</div>
            <div class="top-right">Republique du Mali<br />Un Peuple - Un But - Une Foi</div>
        </div>
        <div class="dest-row">${destBlock}</div>
        <div class="subject">${content.subject || ''}</div>
        <p class="para indent">Monsieur le Directeur,</p>
        ${paragraphsHtml}
        ${note}
        <div class="footer-row">
            <div class="footer-left">
                ${attachmentsHtml ? `<div class="list-title">Pièces jointes :</div><ul>${attachmentsHtml}</ul>` : ''}
            </div>
            <div class="footer-right">
                ${requester}
            </div>
        </div>
    </div>
</body>
</html>`;
};

const generateRequestPrint = async () => {
    if (!selectedProject.value) {
        toast.add({ severity: 'warn', summary: 'Demande', detail: 'Aucun projet sélectionné.', life: 2500 });
        return;
    }
    requestGenerating.value = true;
    try {
        const content = buildRequestContent(requestForm.value.type, requestForm.value);
        const html = renderRequestHtml(content);
        const win = window.open('', '_blank');
        if (!win) {
            toast.add({ severity: 'error', summary: 'Demande', detail: 'Popup bloquée, autorisez les fenêtres.', life: 3000 });
            return;
        }
        win.document.open();
        win.document.write(html);
        win.document.close();
        win.focus();
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Demande', detail: 'Ouverture impression impossible.', life: 3000 });
    } finally {
        requestGenerating.value = false;
    }
};

const confirmDeleteGeo = (event, id) => {
    confirmPopup.require({
        target: event?.currentTarget || event?.target,
        message: 'Supprimer cette parcelle ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            try {
                await axios.delete(`${apiPrefix}/geosheets/${id}`, { headers: { Authorization: `Bearer ${token}` } });
                loadProjects();
                toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Parcelle supprimée', life: 3000 });
            } catch (e) {
                console.error(e);
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer', life: 3000 });
            }
        }
    });
};

onMounted(() => {
    loadProjects();
    loadClients();
});

// computed: projects filtrés
const filteredProjects = computed(() => {
    const title = String(filterProjectTitle.value || '').trim().toLowerCase();
    const projectQuery = String(filterProjectQuery.value || '').trim().toLowerCase();
    const parcelNum = String(filterParcelNumber.value || '').trim().toLowerCase();
    const parcelStat = filterParcelStatus.value;

    // si aucun filtre appliqué, renvoyer original
    const hasProjectFilters = title || projectQuery;
    const hasParcelFilters = parcelNum || parcelStat;

    if (!hasProjectFilters && !hasParcelFilters) return projects.value;

    return projects.value
        .map((proj) => {
            // filtre projet
            if (title && !proj.title?.toLowerCase().includes(title)) return null;
            if (projectQuery) {
                const haystack = `${proj.title || ''} ${proj.locality || ''}`.toLowerCase();
                if (!haystack.includes(projectQuery)) return null;
            }

            // si on filtre les parcelles, filtrer la liste interne
            if (hasParcelFilters && Array.isArray(proj.geoSheets)) {
                const filteredGeo = proj.geoSheets.filter((gs) => {
                    if (parcelNum && !(gs.parcelNumber || '').toLowerCase().includes(parcelNum)) return false;
                    if (parcelStat && gs.status !== parcelStat) return false;
                    return true;
                });
                // si aucune parcelle correspondante, ne pas afficher le projet
                if (filteredGeo.length === 0) return null;
                // renvoyer copie du projet avec geoSheets filtrées
                return { ...proj, geoSheets: filteredGeo };
            }

            return proj;
        })
        .filter(Boolean);
});

// handlers extraits pour éviter problèmes d'accès global
const confirmDeleteProject = (event, projectId) => {
    confirmPopup.require({
        target: event?.currentTarget || event?.target,
        message: 'Supprimer ce projet et toutes ses parcelles ?',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Supprimer', severity: 'danger' },
        accept: async () => {
            try {
                await axios.delete(`${apiPrefix}/projects/${projectId}`, { headers: { Authorization: `Bearer ${token}` } });
                toast.add({ severity: 'success', summary: 'Supprimé', detail: 'Projet supprimé', life: 3000 });
                loadProjects();
            } catch (e) {
                console.error(e);
                toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer le projet', life: 3000 });
            }
        }
    });
};
</script>

<template>
    <div class="page-shell">
        <ConfirmPopup />

        <div class="hero">
            <div>
                <p class="eyebrow">Topo • Projets</p>
                <h1>Suivi des projets et parcelles</h1>
                <p class="muted">Créez, filtrez et terminez vos projets en un coup d'œil.</p>
            </div>
            <div class="hero-actions">
                <Button v-if="isAdmin || isTopo" label="Nouveau Projet" severity="success" icon="pi pi-plus"
                    @click="openCreateProject" />
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card total">
                <p class="label">Projets</p>
                <p class="value">{{ projectStats.total }}</p>
            </div>
            <div class="stat-card ongoing">
                <p class="label">En cours</p>
                <p class="value">{{ projectStats.ongoing }}</p>
            </div>
            <div class="stat-card done">
                <p class="label">Terminés</p>
                <p class="value">{{ projectStats.done }}</p>
            </div>
            <div class="stat-card parcels">
                <p class="label">Parcelles</p>
                <p class="value">{{ projectStats.parcels }}</p>
            </div>
        </div>

        <Dialog header="Nouvelle parcelle" v-model:visible="creatingGeoSheet" :modal="true"
            :style="{ width: '900px', maxWidth: '95vw' }">
            <div class="flex gap-2 mb-4">
                <Button :label="mode === 'paste' ? 'Mode Coller' : 'Mode Manuel'"
                    @click="mode = mode === 'paste' ? 'manual' : 'paste'" />
                <Button label="Ajouter un point" @click="addPointManual" />
                <span class="ml-auto text-sm text-gray-500">Projet ID: {{ projectIdForGeo }}</span>
            </div>
            <div v-if="mode === 'paste'">
                <label class="mb-2 block">Coller le texte (X=..., Y=... par ligne)</label>
                <Textarea v-model="pasteText" rows="6" class="w-full mb-2" />
                <Button label="Traiter" @click="parsePaste" />
            </div>
            <div v-else>
                <label class="mb-2 block">Ajout manuel des points</label>
            </div>
            <div class="mt-4 grid md:grid-cols-2 gap-4">
                <div>
                    <label>Numéro de parcelle</label>
                    <InputText v-model="parcelNumber" class="w-full mb-1" />
                    <small v-if="parcelNumberChecking" class="text-gray-500">Vérification...</small>
                    <small v-else-if="parcelNumber && !parcelNumberValid" class="text-red-600">{{ parcelNumberMsg ||
                        'Numéro déjà utilisé' }}</small>
                    <small v-else-if="parcelNumber && parcelNumberValid" class="text-green-600">Numéro
                        disponible</small>
                </div>
                <div>
                    <label>Statut</label>
                    <Select v-model="geoStatus" :options="geoStatusOptions" optionLabel="label" optionValue="value"
                        class="w-full mb-2" placeholder="Choisir..." />
                </div>
                <div class="md:col-span-2">
                    <label>Référence (optionnel)</label>
                    <InputText v-model="parcelReference" class="w-full mb-2" />
                </div>
            </div>
            <div class="mt-4 overflow-auto" style="max-height: 45vh">
                <h3 class="font-semibold mb-2">Points ({{ points.length }})</h3>
                <table class="w-full table-auto border-collapse">
                    <thead>
                        <tr>
                            <th class="border p-2">#</th>
                            <th class="border p-2">Designation</th>
                            <th class="border p-2">X</th>
                            <th class="border p-2">Y</th>
                            <th class="border p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in points" :key="i">
                            <td class="border p-2">{{ i + 1 }}</td>
                            <td class="border p-2">
                                <InputText v-model="p.designation" />
                            </td>
                            <td class="border p-2">
                                <InputText v-model="p.x" @blur="p.x = toFixed6Str(p.x)" />
                            </td>
                            <td class="border p-2">
                                <InputText v-model="p.y" @blur="p.y = toFixed6Str(p.y)" />
                            </td>
                            <td class="border p-2"><Button label="Suppr" severity="danger" @click="removePoint(i)" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template #footer>
                <Button label="Annuler" @click="creatingGeoSheet = false" />
                <Button label="Créer" severity="success" :disabled="!canCreateParcel" @click="createGeo" />
            </template>
        </Dialog>

        <div class="card">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="mb-1">Projets</h3>
                    <p class="muted">Vue consolidée des projets et parcelles.</p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="isAdmin || isTopo" label="Nouveau Projet" severity="success" icon="pi pi-plus"
                        @click="openCreateProject" />
                </div>
            </div>

            <!-- Barre de filtres -->
            <div class="filters">
                <div>
                    <label class="block text-sm">Recherche titre</label>
                    <InputText v-model="filterProjectTitle" placeholder="Titre du projet..." class="w-full" />
                </div>
                <div>
                    <label class="block text-sm">Type ou adresse</label>
                    <InputText v-model="filterProjectQuery" placeholder="Type de projet ou adresse..." class="w-full" />
                </div>
                <div>
                    <label class="block text-sm">Parcelle (n°)</label>
                    <InputText v-model="filterParcelNumber" placeholder="Numéro parcelle..." class="w-full" />
                </div>
                <div>
                    <label class="block text-sm">Statut parcelle</label>
                    <Select v-model="filterParcelStatus" :options="geoStatusOptions" optionLabel="label"
                        optionValue="value" class="w-full" placeholder="Tous" />
                </div>
                <div class="md:col-span-4 flex gap-2 mt-2">
                    <Button label="Effacer les filtres" severity="secondary" class="mr-2"
                        @click="() => { filterProjectTitle = ''; filterProjectQuery = ''; filterParcelNumber = ''; filterParcelStatus = null }" />
                </div>
            </div>

            <DataTable :value="filteredProjects" dataKey="id" :rowHover="true" responsiveLayout="scroll"
                v-model:expandedRows="expandedProjects" class="project-table">
                <Column field="title" header="Type de travail">
                    <template #body="{ data }">
                        <div class="cell-main">{{ data.title }}</div>
                        <div class="cell-sub">{{ data.locality || '—' }}</div>
                    </template>
                </Column>
                <Column header="Statut" style="max-width: 7rem">
                    <template #body="{ data }">
                        <Tag :value="statusLabel(data.status)"
                            :severity="projectStatusSeverity[data.status] || 'info'" />
                    </template>
                </Column>
                <Column field="createdAt" header="Créé le" style="max-width: 8rem" />
                <Column header="Parcelles" style="max-width: 6rem">
                    <template #body="{ data }">{{ data.geoSheets.length }}</template>
                </Column>
                <Column header="Actions" style="width: 6rem">
                    <template #body="{ data }">
                        <Button icon="pi pi-ellipsis-v" rounded text @click="(event) => toggleProjectMenu(event, data)"
                            title="Actions" aria-label="Actions" />
                        <Menu :model="projectMenuItems(data)" :popup="true"
                            :ref="(el) => registerActionMenu(el, data.id)" />
                    </template>
                </Column>
                <Column expander style="width: 1rem" />
                <template #expansion="{ data }">
                    <div class="nested-panel">
                        <table class="w-full table-auto border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th class="border p-2">Parcelle</th>
                                    <th class="border p-2">Créé le</th>
                                    <th class="border p-2">Versions</th>
                                    <th class="border p-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="gs in data.geoSheets" :key="gs.id">
                                    <td class="border p-2 font-medium">{{ gs.parcelNumber || '—' }}</td>
                                    <td class="border p-2">{{ gs.createdAt }}</td>
                                    <td class="border p-2">{{ gs.versionsCount }}</td>
                                    <td class="border p-2">
                                        <div class="flex gap-2">
                                            <Button icon="pi pi-eye" rounded text
                                                @click="previewGeoSheet(data.id, gs.id)" title="Aperçu de la parcelle"
                                                aria-label="Aperçu de la parcelle" />
                                            <Button v-if="isAdmin" icon="pi pi-trash" severity="danger" rounded text
                                                @click="(e) => confirmDeleteGeo(e, gs.id)" title="Supprimer la parcelle"
                                                aria-label="Supprimer la parcelle" />
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!data.geoSheets.length">
                                    <td colspan="4" class="text-center p-3 text-gray-500">Aucune parcelle</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </DataTable>
        </div>

        <ProjectCreateDialog v-model:visible="createProjectDialogVisible" @created="handleProjectCreated" />

        <Dialog header="Imprimer une demande" v-model:visible="requestDialogVisible" :modal="true"
            :style="{ width: '720px', maxWidth: '95vw' }">
            <div class="text-sm text-gray-600 mb-3" v-if="selectedProject">
                Projet : <strong>{{ selectedProject.title }}</strong> — {{ selectedProject.locality || 'Localité non
                renseignée'
                }}
            </div>

            <div class="grid md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm mb-1">Type de demande</label>
                    <Select v-model="requestForm.type" :options="requestTypeOptions" optionLabel="label"
                        optionValue="value" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm mb-1">Objet</label>
                    <InputText v-model="requestForm.object" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm mb-1">Destinataire</label>
                    <InputText v-model="requestForm.destinataire" class="w-full" />
                </div>
                <div>
                    <label class="block text-sm mb-1">Direction / Ville</label>
                    <InputText v-model="requestForm.direction" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm mb-1">Parcelle</label>
                    <Select v-model="requestForm.parcelId" :options="requestParcelOptions" optionLabel="label"
                        optionValue="value" class="w-full" placeholder="Choisir une parcelle" />
                    <small class="text-gray-500" v-if="requestForm.parcelNumber">N° {{ requestForm.parcelNumber
                        }}</small>
                </div>
                <div>
                    <label class="block text-sm mb-1">Parcellement / Plan</label>
                    <InputText v-model="requestForm.planName" class="w-full" />
                </div>

                <div>
                    <label class="block text-sm mb-1">Superficie estimée</label>
                    <InputText v-model="requestForm.surface" class="w-full" />
                    <small v-if="requestSurfaceLoading" class="text-gray-500">Calcul en cours...</small>
                </div>
                <div>
                    <label class="block text-sm mb-1">Demandeur</label>
                    <InputText v-model="requestForm.demandeur" class="w-full" placeholder="Nom complet" />
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm mb-1">Pièces jointes (une par ligne)</label>
                    <Textarea v-model="requestForm.attachments" rows="4" class="w-full" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm mb-1">Note complémentaire (optionnel)</label>
                    <Textarea v-model="requestForm.customNote" rows="3" class="w-full" />
                </div>
            </div>

            <template #footer>
                <Button label="Annuler" severity="secondary" @click="requestDialogVisible = false" />
                <Button label="Imprimer" icon="pi pi-print" severity="success" :loading="requestGenerating"
                    @click="generateRequestPrint" />
            </template>
        </Dialog>

        <Dialog header="Modifier Projet" v-model:visible="editingProject" :modal="true">
            <div>
                <label>Titre</label>
                <InputText v-model="editProjectTitle" class="w-full mb-2" />
                <label>Localité</label>
                <InputText v-model="editProjectLocality" class="w-full mb-2" />
                <label>Statut</label>
                <Select v-model="editProjectStatus" :options="projectStatusOptions" optionLabel="label"
                    optionValue="value" class="w-full mb-2" placeholder="Choisir..." />
                <label>Client (optionnel)</label>
                <Select v-model="editProjectClientId" :options="clientOptions" optionLabel="label" optionValue="value"
                    class="w-full mb-2" placeholder="Sans client" :loading="clientsLoading" showClear />
            </div>
            <template #footer>
                <Button label="Annuler" @click="editingProject = false" />
                <Button label="Sauvegarder" severity="success" @click="saveEditProject" />
            </template>
        </Dialog>

        <Dialog header="Export terminé" v-model:visible="exportResultDialogVisible" :modal="true"
            :style="{ width: '520px' }" @hide="closeExportResultDialog">
            <p class="mb-3 text-sm">{{ exportResultMessage }}</p>
            <div v-if="exportResultFilename" class="text-xs text-gray-500 mb-2">Fichier : {{ exportResultFilename }}
            </div>
            <div class="flex justify-end gap-2">
                <Button label="Fermer" class="p-button-text" icon="pi pi-times" @click="closeExportResultDialog" />
                <Button v-if="exportResultUrl" label="Ouvrir" icon="pi pi-external-link" severity="info"
                    @click="openExportResultFile" />
            </div>
        </Dialog>

        <Dialog v-model:visible="timelineDialogVisible" header="Timeline du projet" :modal="true"
            :style="{ width: '1100px', maxWidth: '96vw' }">
            <div class="flex justify-between items-start gap-3 mb-4">
                <div>
                    <div class="font-semibold text-lg">{{ timelineProject ? timelineProject.title : 'Projet' }}</div>
                    <div class="text-sm text-gray-500">ID {{ timelineProject ? timelineProject.id : '—' }}</div>
                </div>
                <Tag v-if="timelineProject" :value="statusLabel(timelineProject.status)"
                    :severity="projectStatusSeverity[timelineProject.status] || 'info'" />
            </div>

            <div class="grid md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <div v-if="timelineLoading" class="text-gray-500 text-sm">Chargement...</div>
                    <div v-else>
                        <Timeline :value="timelineEvents" align="alternate" class="customized-timeline">
                            <template #marker="slotProps">
                                <span
                                    class="flex w-9 h-9 items-center justify-center text-white rounded-full z-10 shadow-sm"
                                    :style="{ backgroundColor: timelineTypeMeta[slotProps.item.type] ? timelineTypeMeta[slotProps.item.type].color : '#64748b' }">
                                    <i
                                        :class="timelineTypeMeta[slotProps.item.type] ? timelineTypeMeta[slotProps.item.type].icon : 'pi pi-circle'" />
                                </span>
                            </template>
                            <template #content="slotProps">
                                <Card class="mt-4 shadow-sm">
                                    <template #title>{{ timelineTypeMeta[slotProps.item.type] ?
                                        timelineTypeMeta[slotProps.item.type].label : slotProps.item.type }}</template>
                                    <template #subtitle>{{ slotProps.item.date }}</template>
                                    <template #content>
                                        <p class="text-sm mb-3 whitespace-pre-line">{{ slotProps.item.description }}</p>
                                        <div class="flex justify-end">
                                            <Button v-if="isAdmin || isTopo" icon="pi pi-trash" severity="danger" text
                                                size="small"
                                                @click="(e) => confirmDeleteTimelineEntry(e, slotProps.item.id)" />
                                        </div>
                                    </template>
                                </Card>
                            </template>
                        </Timeline>
                        <div v-if="!timelineEvents.length" class="text-gray-500 text-sm mt-3">Aucune étape pour le
                            moment.</div>
                    </div>
                </div>

                <div class="md:col-span-1 border rounded-lg p-3 bg-gray-50">
                    <h4 class="font-semibold mb-2">Ajouter une étape</h4>
                    <p v-if="timelineProject && timelineProject.status === 'done'" class="text-xs text-orange-600 mb-2">
                        Projet
                        terminé : ajout désactivé.</p>
                    <label class="block text-sm mb-1">Date</label>
                    <InputText type="datetime-local" v-model="timelineForm.date" class="w-full mb-2" />
                    <label class="block text-sm mb-1">Type</label>
                    <Select v-model="timelineForm.type" :options="timelineTypeOptions" optionLabel="label"
                        optionValue="value" class="w-full mb-2" />
                    <label class="block text-sm mb-1">Description</label>
                    <Textarea v-model="timelineForm.description" rows="5" class="w-full mb-3" />
                    <Button label="Ajouter" icon="pi pi-plus" class="w-full" severity="success"
                        :loading="timelineSubmitting"
                        :disabled="(timelineProject && timelineProject.status === 'done') || !timelineForm.description"
                        @click="submitTimelineEntry" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.page-shell {
    padding: 1.5rem;
    background: var(--surface-ground);
    min-height: 100vh;
}

.hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1.5rem;
    margin-bottom: 1.25rem;
}

.hero h1 {
    font-size: 1.6rem;
    margin: 0.15rem 0;
}

.hero .muted {
    color: #6b7280;
    margin: 0;
}

.hero .eyebrow {
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.8rem;
    color: #94a3b8;
    margin: 0;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background: var(--surface-card);
    color: var(--text-color);
    border-radius: 12px;
    padding: 0.9rem 1rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--surface-border);
}

.stat-card .label {
    font-size: 0.85rem;
    color: var(--text-color-secondary);
    margin: 0;
}

.stat-card .value {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0.1rem 0 0;
    color: var(--primary-color);
}

.stat-card.ongoing .value {
    color: var(--blue-500, var(--primary-color));
}

.stat-card.done .value {
    color: var(--green-500, var(--primary-color));
}

.stat-card.parcels .value {
    color: var(--orange-500, var(--primary-color));
}

.card {
    border-radius: 14px;
    padding: 1.25rem;
    background: var(--surface-card);
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--surface-border);
}

.filters {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.75rem;
    padding: 0.75rem;
    margin: 1rem 0;
    border: 1px dashed var(--surface-border);
    border-radius: 12px;
    background: var(--surface-ground);
}

.project-table :deep(.p-datatable-thead > tr > th) {
    background: var(--surface-section);
    color: var(--text-color);
    font-weight: 600;
}

.project-table :deep(.p-datatable-tbody > tr:hover) {
    background: var(--surface-hover);
}

.cell-main {
    font-weight: 600;
    color: var(--text-color);
}

.cell-sub {
    font-size: 0.85rem;
    color: var(--text-color-secondary);
}

.nested-panel {
    background: var(--surface-ground);
    border-radius: 10px;
    border: 1px solid var(--surface-border);
    padding: 0.75rem;
}

.muted {
    color: var(--text-color-secondary);
}

.project-dialog {
    position: relative;
}

.project-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 2;
    padding: 0.25rem 0;
    border-bottom: 1px solid #e5e7eb;
}

.project-dialog-scroll {
    max-height: 75vh;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.status-message :deep(.p-message-text) {
    font-size: 0.95rem;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 9999px;
    background: #d1d5db;
    display: inline-block;
}

.status-dot.ok {
    background: #22c55e;
}

.status-dot.warn {
    background: #ef4444;
}

.tab-tip {
    font-size: 0.85rem;
    color: #6b7280;
}

.customized-timeline :deep(.p-timeline-event-opposite) {
    flex: 0 0 7rem;
    color: #94a3b8;
}

.customized-timeline :deep(.p-timeline-event-content) {
    margin-top: 0;
}

.customized-timeline :deep(.p-timeline-event-connector) {
    background-color: var(--surface-border);
}
</style>
