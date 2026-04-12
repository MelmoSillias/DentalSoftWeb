<script setup>
import * as fsService from '@/services/olders/fsService';
import {
    addProjectTimeline,
    deleteProjectTimeline,
    getGeoSheet,
    getGeoSheetVersions,
    getProject,
    getProjectTimeline,
    updateGeoPoints
} from '@/services/olders/topoApi';
import { computeCalculations, formatVersionLabel } from '@/services/olders/topoCalc';
import { exportTopoExcel, exportTopoPDF, exportTopoPDFCombined } from '@/services/olders/topoExport';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ConfirmPopup from 'primevue/confirmpopup';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
import Select from 'primevue/select';
import Sidebar from 'primevue/sidebar';
import Textarea from 'primevue/textarea';
import Timeline from 'primevue/timeline';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const isAdmin = computed(() => auth.user?.roles?.includes('ROLE_ADMIN'));
const isTopo = computed(() => auth.user?.roles?.includes('ROLE_TOPO'));
const pageHeader = computed(() => route.meta?.header || route.meta?.title || '');
const toast = useToast();
const confirm = useConfirm();
const token = localStorage.getItem('token');

const project = ref(null);
const parcels = ref([]); // [{ rootId, displayLabel, geo, versions, selectedVersionId, calculations }]
const loadingParcels = ref(false);

const exportDialogVisible = ref(false);
const exportTargetId = ref(null);
const sectionOptions = [
    { key: 'coord', label: 'Tableau de coordonnées' },
    { key: 'retour', label: 'Feuille de calcul retour' },
    { key: 'superficie', label: 'Calcul de Superficie' }
];
const selectedSections = ref(['coord', 'retour', 'superficie']);
const pageMode = ref('single');
const exportFormat = ref('pdf');
const pdfOrientation = ref('portrait');
const exportingAll = ref(false);
const exportingAllSinglePdf = ref(false);
const zoomDialogVisible = ref(false);
const zoomTargetId = ref(null);

const timelineVisible = ref(false);
const timelineEvents = ref([]);
const timelineLoading = ref(false);
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

const exportResultDialogVisible = ref(false);
const exportResultMessage = ref('');
const exportResultUrl = ref(null);
const exportResultFilename = ref('');

const openExportResultFile = () => {
    if (typeof window === 'undefined') return;
    if (!exportResultUrl.value) return;
    window.open(exportResultUrl.value, '_blank');
};

const resetTimelineForm = () => {
    timelineForm.value = { date: new Date().toISOString().slice(0, 16), description: '', type: 'milestone' };
};

const loadTimeline = async () => {
    if (!project.value?.id) return;
    timelineLoading.value = true;
    try {
        const res = await getProjectTimeline(project.value.id, token);
        timelineEvents.value = Array.isArray(res.data) ? res.data : [];
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Timeline', detail: 'Chargement impossible.', life: 2500 });
    } finally {
        timelineLoading.value = false;
    }
};

const openTimelineSidebar = async () => {
    timelineVisible.value = true;
    resetTimelineForm();
    await loadTimeline();
};

const submitTimelineEntry = async () => {
    if (!project.value?.id) return;
    timelineSubmitting.value = true;
    try {
        await addProjectTimeline(project.value.id, { ...timelineForm.value }, token);
        toast.add({ severity: 'success', summary: 'Timeline', detail: 'Etape ajoutée.', life: 2000 });
        resetTimelineForm();
        await loadTimeline();
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Timeline', detail: "Impossible d'ajouter l'étape.", life: 3000 });
    } finally {
        timelineSubmitting.value = false;
    }
};

const confirmDeleteTimelineEntry = (event, entryId) => {
    confirm.require({
        target: event?.currentTarget || event?.target,
        message: "Supprimer cette étape ?",
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Supprimer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await deleteProjectTimeline(entryId, token);
                await loadTimeline();
                toast.add({ severity: 'success', summary: 'Timeline', detail: 'Etape supprimée.', life: 2000 });
            } catch (e) {
                console.error(e);
                toast.add({ severity: 'error', summary: 'Timeline', detail: "Suppression impossible.", life: 3000 });
            }
        }
    });
};

const editDialogVisible = ref(false);
const editTargetId = ref(null);
const mode = ref('paste');
const pasteText = ref('');
const editedPoints = ref([]);

const rootConfigured = ref(false);
const projFolderExistsFlag = ref(false);
const geoFolderExistsFlag = ref(false);

const isSecretaire = computed(() => auth.user?.roles?.includes('ROLE_SECRETAIRE'));
const isProjectDone = computed(() => project.value?.status === 'done');

const formatDateSafe = (val) => {
    if (!val) return '—';
    const d = new Date(val);
    return Number.isNaN(d.getTime()) ? val : d.toLocaleDateString();
};

const totalParcels = computed(() => parcels.value.length);
const totalVersions = computed(() => parcels.value.reduce((acc, p) => acc + (p.versions?.length || 1), 0));
const lastUpdatedLabel = computed(() => {
    const dates = parcels.value
        .map((p) => p.geo?.updatedAt || p.geo?.createdAt)
        .filter(Boolean);
    if (!dates.length) return '—';
    const latest = dates.reduce((acc, cur) => (new Date(cur) > new Date(acc) ? cur : acc), dates[0]);
    return formatDateSafe(latest);
});

const activeExportParcel = computed(() => parcels.value.find((p) => p.rootId === exportTargetId.value));
const activeEditParcel = computed(() => parcels.value.find((p) => p.rootId === editTargetId.value));
const activeZoomParcel = computed(() => parcels.value.find((p) => p.rootId === zoomTargetId.value));

const fetchProject = async (projectId) => {
    const res = await getProject(projectId, token);
    project.value = res.data;
};

const pickLatestVersionId = (versions) => {
    if (!versions?.length) return null;
    const sorted = [...versions].sort(
        (a, b) => new Date(b.updatedAt || b.createdAt || 0) - new Date(a.updatedAt || a.createdAt || 0)
    );
    return sorted[0]?.id ?? null;
};

const buildParcelCard = async (geoId, targetVersionId = null) => {
    const resGeo = await getGeoSheet(geoId, token);
    let geo = resGeo.data;
    let versions = [];
    try {
        const resVer = await getGeoSheetVersions(geoId, token);
        versions = Array.isArray(resVer.data) ? resVer.data : [];
    } catch (e) {
        versions = [];
    }

    let activeVersionId = targetVersionId || pickLatestVersionId(versions) || geo.id;
    if (activeVersionId !== geo.id) {
        try {
            const resActive = await getGeoSheet(activeVersionId, token);
            geo = resActive.data;
        } catch (e) {
            activeVersionId = geo.id;
        }
    }

    return {
        rootId: geoId,
        displayLabel: geo.parcelNumber || geo.title || `GS-${geoId}`,
        geo,
        versions,
        selectedVersionId: activeVersionId,
        calculations: computeCalculations(geo)
    };
};

const loadAllParcels = async () => {
    if (!project.value?.geoSheets?.length) return;
    loadingParcels.value = true;
    const ids = project.value.geoSheets.map((gs) => gs.id);
    const cards = await Promise.all(ids.map((id) => buildParcelCard(id)));
    parcels.value = cards;
    loadingParcels.value = false;
    updateFolderStatuses().catch(() => { });
};

const refreshParcel = async (rootId, targetVersionId = null) => {
    const card = await buildParcelCard(rootId, targetVersionId);
    const idx = parcels.value.findIndex((p) => p.rootId === rootId);
    if (idx >= 0) parcels.value.splice(idx, 1, card);
    else parcels.value.push(card);
    updateFolderStatuses().catch(() => { });
};

const scrollToParcel = (id) => {
    const el = document.getElementById(`parcel-${id}`);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const onChangeVersion = async (parcel, versionId) => {
    if (!versionId) return;
    try {
        const res = await getGeoSheet(versionId, token);
        parcel.geo = res.data;
        parcel.selectedVersionId = versionId;
        parcel.calculations = computeCalculations(res.data);
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Version introuvable.', life: 2500 });
    }
};

const areaDisplay = (calc) => {
    try {
        if (!calc) return '';
        const sDHalf = Number(Number(calc.sumD).toFixed(6)) / 2;
        const ares = Math.trunc(sDHalf / 100);
        const ca = Math.trunc(Math.abs(ares - sDHalf / 100) * 100);
        if (ares >= 100) {
            const ha = Math.trunc(ares / 100);
            const remA = ares % 100;
            return `${ha}ha ${remA}a ${ca}ca`;
        }
        return `${ares}a ${ca}ca`;
    } catch (e) {
        return '';
    }
};

const toFixed6Str = (val) => {
    const num = typeof val === 'number' ? val : parseFloat(String(val).replace(',', '.'));
    if (Number.isFinite(num)) return num.toFixed(6);
    return '0.000000';
};

const openEditDialog = (parcelId) => {
    const parcel = parcels.value.find((p) => p.rootId === parcelId);
    if (!parcel?.geo?.points) return;
    editedPoints.value = parcel.geo.points.map((p) => ({
        designation: p.designation,
        x: Number(p.x),
        y: Number(p.y)
    }));
    pasteText.value = '';
    mode.value = 'paste';
    editTargetId.value = parcelId;
    editDialogVisible.value = true;
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

const cancelEditPoints = () => {
    editDialogVisible.value = false;
    editedPoints.value = [];
    pasteText.value = '';
};

const parsePaste = () => {
    editedPoints.value = [];
    const lines = pasteText.value
        .split(/\r?\n/)
        .map((l) => l.trim())
        .filter(Boolean);
    let idx = 1;
    for (const line of lines) {
        const rx = /X\s*=\s*([-+]?\d*\.?\d+(?:[eE][-+]?\d+)?)/i;
        const ry = /Y\s*=\s*([-+]?\d*\.?\d+(?:[eE][-+]?\d+)?)/i;
        const mx = line.match(rx);
        const my = line.match(ry);
        if (mx && my) {
            editedPoints.value.push({ designation: `B${idx}`, x: Number(parseFloat(mx[1])), y: Number(parseFloat(my[1])) });
            idx++;
        }
    }
    toast.add({ severity: 'info', summary: 'Parsing', detail: `${editedPoints.value.length} point(s) extraits`, life: 3000 });
};

const addEditedPoint = () => {
    const idx = editedPoints.value.length + 1;
    editedPoints.value.push({ designation: `B${idx}`, x: 0, y: 0 });
};

const removeEditedPoint = (i) => editedPoints.value.splice(i, 1);

const requestSaveNewVersion = (event) => {
    if (!editedPoints.value.length || !activeEditParcel.value) return;
    const parcel = activeEditParcel.value;
    const originalCount = parcel.geo?.points?.length ?? 0;
    const newCount = editedPoints.value.length;

    const doSave = async () => {
        try {
            const payloadPoints = editedPoints.value.map((p) => ({
                designation: p.designation,
                x: Number(p.x),
                y: Number(p.y)
            }));
            const res = await updateGeoPoints(parcel.geo.id, payloadPoints, token);
            const newVersionId = res.data?.newVersionId;
            toast.add({ severity: 'success', summary: 'Version créée', detail: `Version ${newVersionId || ''} créée`, life: 2500 });
            editDialogVisible.value = false;
            editedPoints.value = [];
            pasteText.value = '';
            await refreshParcel(parcel.rootId, newVersionId || parcel.geo.id);
        } catch (err) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de créer une nouvelle version.', life: 3000 });
        }
    };

    const confirmMsg = newCount !== originalCount
        ? `Le nombre de points a changé (${originalCount} → ${newCount}). Continuer ?`
        : 'Créer une nouvelle version avec ces modifications ?';

    confirm.require({
        target: event?.currentTarget,
        message: confirmMsg,
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui',
        rejectLabel: 'Non',
        acceptClass: 'p-button-danger',
        rejectClass: 'p-button-text',
        accept: doSave
    });
};

const openExportDialog = (parcelId) => {
    exportTargetId.value = parcelId;
    exportDialogVisible.value = true;
};

const openZoomDialog = (parcelId) => {
    zoomTargetId.value = parcelId;
    zoomDialogVisible.value = true;
};

const exportAllDocuments = async () => {
    if (!parcels.value.length) return;
    if (!selectedSections.value.length) {
        toast.add({ severity: 'warn', summary: 'Sélection vide', detail: 'Choisissez au moins une section.', life: 3000 });
        return;
    }
    exportingAll.value = true;
    let ok = 0;
    for (const parcel of parcels.value) {
        try {
            const { blob, filename } = await exportTopoPDF({
                project: { title: project.value.title, locality: project.value.locality },
                parcelNumber: parcel.geo?.parcelNumber || parcel.displayLabel,
                sections: selectedSections.value,
                mode: pageMode.value,
                calculations: parcel.calculations,
                orientation: pdfOrientation.value,
                reference: parcel.geo?.reference,
                returnBlob: true
            });

            const projName = project.value.title;
            const gName = parcel.geo?.parcelNumber || parcel.geo?.title || `geo-${parcel.geo?.id}`;
            const manualName = `manuel_${filename}`;
            await fsService.writeFileInGeo(projName, gName, manualName, blob, false);
            ok++;
        } catch (e) {
            toast.add({ severity: 'error', summary: 'Export', detail: `Échec pour ${parcel.displayLabel}`, life: 2500 });
        }
    }
    exportingAll.value = false;
    showExportResult({ message: `${ok}/${parcels.value.length} document(s) exporté(s) dans leurs dossiers respectifs.` });
};

const exportAllInSinglePDF = async () => {
    if (!parcels.value.length) return;
    if (!selectedSections.value.length) {
        toast.add({ severity: 'warn', summary: 'Sélection vide', detail: 'Choisissez au moins une section.', life: 3000 });
        return;
    }
    exportingAllSinglePdf.value = true;
    try {
        const parcelPayload = parcels.value.map((parcel) => ({
            parcelNumber: parcel.geo?.parcelNumber || parcel.displayLabel,
            calculations: parcel.calculations,
            reference: parcel.geo?.reference,
            id: parcel.geo?.id,
            title: parcel.geo?.title
        }));
        const { blob, filename } = await exportTopoPDFCombined({
            project: { title: project.value.title, locality: project.value.locality },
            parcels: parcelPayload,
            sections: selectedSections.value,
            mode: pageMode.value,
            orientation: pdfOrientation.value
        });
        const manualName = `manuel_${filename}`;
        await fsService.writeFileInProject(project.value.title, manualName, blob, false);
        showExportResult({ message: `PDF unique enregistré (${manualName}).`, blob, filename: manualName });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Export', detail: 'Échec de l\'export PDF unique.', life: 3000 });
    } finally {
        exportingAllSinglePdf.value = false;
    }
};

const validateAndExport = async () => {
    const parcel = activeExportParcel.value;
    if (!parcel) return;
    if (!selectedSections.value.length) {
        toast.add({ severity: 'warn', summary: 'Sélection vide', detail: 'Choisissez au moins une section.', life: 3000 });
        return;
    }
    exportDialogVisible.value = false;
    if (exportFormat.value === 'pdf') {
        try {
            const { blob, filename } = await exportTopoPDF({
                project: { title: project.value.title, locality: project.value.locality },
                parcelNumber: parcel.geo?.parcelNumber || parcel.displayLabel,
                sections: selectedSections.value,
                mode: pageMode.value,
                calculations: parcel.calculations,
                orientation: pdfOrientation.value,
                reference: parcel.geo?.reference,
            });
            const projName = project.value.title;
            const gName = parcel.geo?.parcelNumber || parcel.geo?.title || `geo-${parcel.geo?.id}`;
            const manualName = `manuel_${filename}`;
            await fsService.writeFileInGeo(projName, gName, manualName, blob, false);
            showExportResult({ message: 'PDF enregistré dans le dossier racine.', blob, filename: manualName });
        } catch (e) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Export PDF impossible.', life: 3000 });
        }
    } else {
        try {
            const { blob, filename } = await exportTopoExcel({
                parcelNumber: parcel.geo?.parcelNumber || parcel.displayLabel,
                sections: selectedSections.value,
                mode: pageMode.value,
                calculations: parcel.calculations
            });
            const projName = project.value.title;
            const gName = parcel.geo?.parcelNumber || parcel.geo?.title || `geo-${parcel.geo?.id}`;
            const manualName = `manuel_${filename}`;
            await fsService.writeFileInGeo(projName, gName, manualName, blob, false);
            showExportResult({ message: 'Excel enregistré dans le dossier racine.', blob, filename: manualName });
        } catch (e) {
            toast.add({ severity: 'error', summary: 'Erreur', detail: 'Export Excel impossible.', life: 3000 });
        }
    }
};

async function updateFolderStatuses() {
    try {
        const root = await fsService.getRootDirectoryHandle();
        rootConfigured.value = !!root;
        if (project.value && project.value.title) {
            projFolderExistsFlag.value = await fsService.projectFolderExists(project.value.title);
        } else {
            projFolderExistsFlag.value = false;
        }
        const firstParcel = parcels.value[0];
        if (project.value && firstParcel?.geo) {
            const gName = firstParcel.geo.parcelNumber || firstParcel.geo.title || `geo-${firstParcel.geo.id}`;
            geoFolderExistsFlag.value = await fsService.geoFolderExists(project.value.title, gName);
        } else {
            geoFolderExistsFlag.value = false;
        }
    } catch (e) {
        /* silent */
    }
}

onMounted(async () => {
    const { projectId } = route.params;
    if (projectId) {
        await fetchProject(projectId);
        await loadAllParcels();
    }
});
</script>

<template>
    <div class="page-container">
        <div class="top-row">
            <div class="flex items-start gap-4">
                <Button icon="pi pi-arrow-left" severity="secondary" label="Retour" class="shadow-sm"
                    @click="router.push({ name: 'topo-liste' })" />
                <div v-if="project" class="info-card">
                    <div class="flex items-center gap-4">
                        <div class="avatar">{{ (project.title || 'P').charAt(0) }}</div>
                        <div>
                            <div class="text-lg font-semibold">{{ project.title }}</div>
                            <div class="text-sm text-gray-500">{{ project.locality || 'Localité inconnue' }} • <span
                                    class="font-medium">{{ project.status }}</span></div>
                            <div class="text-xs text-gray-400 mt-1">Créé le: {{ project.createdAt }}</div>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-3 text-xs text-gray-600">
                        <div class="status-pill" :class="rootConfigured ? 'ok' : 'ko'"><i
                                :class="rootConfigured ? 'pi pi-check' : 'pi pi-times'" /> Dossier racine</div>
                        <div class="status-pill" :class="projFolderExistsFlag ? 'ok' : 'warn'"><i
                                :class="projFolderExistsFlag ? 'pi pi-folder' : 'pi pi-exclamation-triangle'" /> Dossier
                            projet</div>
                        <div class="status-pill" :class="geoFolderExistsFlag ? 'ok' : 'warn'"><i
                                :class="geoFolderExistsFlag ? 'pi pi-folder' : 'pi pi-exclamation-triangle'" /> Dossier
                            parcelle</div>
                        <div class="status-pill neutral"><i class="pi pi-map" /> {{ project.geoSheets?.length || 0 }}
                            parcelle(s)</div>
                    </div>
                </div>
            </div>
            <div class="actions">
                <Button label="Chronologie" icon="pi pi-calendar" size="small" severity="secondary"
                    @click="openTimelineSidebar" />
                <Button label="PDF unique" icon="pi pi-file-pdf" size="small" severity="help"
                    :loading="exportingAllSinglePdf" @click="exportAllInSinglePDF" />
                <Button label="Exporter tout" icon="pi pi-download" size="small" severity="info" :loading="exportingAll"
                    @click="exportAllDocuments" />
                <div class="page-header-label" v-if="pageHeader">{{ pageHeader }}</div>
                <div class="text-xs text-gray-500">Rôles: {{ isAdmin ? 'Admin' : isTopo ? 'Topo' : isSecretaire ?
                    'Secrétaire' : 'Utilisateur' }}</div>
            </div>
        </div>

        <div class="content-row">
            <div class="parcels-column" :class="loadingParcels ? 'opacity-70' : ''">
                <div v-if="loadingParcels" class="loading">Chargement des parcelles...</div>
                <div v-else class="parcels-list">
                    <div v-for="parcel in parcels" :key="parcel.rootId" :id="`parcel-${parcel.rootId}`"
                        class="parcel-card">
                        <div class="parcel-header">
                            <div>
                                <div class="parcel-title">Parcelle {{ parcel.displayLabel }}</div>
                                <div class="parcel-meta">Réf: {{ parcel.geo?.reference || '—' }} • Points: {{
                                    parcel.geo?.points?.length || 0 }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Select v-if="parcel.versions?.length" v-model="parcel.selectedVersionId"
                                    :options="parcel.versions.map((v) => ({ label: formatVersionLabel(v), value: v.id }))"
                                    optionLabel="label" optionValue="value" class="w-48"
                                    @change="onChangeVersion(parcel, parcel.selectedVersionId)" />
                                <Button icon="pi pi-external-link" class="p-button-sm" severity="secondary"
                                    @click="openZoomDialog(parcel.rootId)" />
                                <Button icon="pi pi-upload" class="p-button-sm" severity="success"
                                    @click="openExportDialog(parcel.rootId)" />
                                <Button v-if="isAdmin || isTopo" icon="pi pi-pencil" class="p-button-sm"
                                    severity="warning" @click="openEditDialog(parcel.rootId)" />
                            </div>
                        </div>

                        <div class="parcel-body">
                            <div class="parcel-submeta">
                                <span>Mis à jour: {{ parcel.geo?.updatedAt || parcel.geo?.createdAt || '—' }}</span>
                                <span>Source: REF=GPS</span>
                                <span>Status: {{ parcel.geo?.status }}</span>
                            </div>

                            <section class="parcel-section">
                                <h3 class="section-title">Tableau de coordonnées</h3>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Points</th>
                                            <th>X</th>
                                            <th>Y</th>
                                            <th>Observation</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(p, i) in parcel.calculations.points" :key="i">
                                            <td>{{ p.designation }}</td>
                                            <td class="text-right">{{ p.x.toFixed(6) }}</td>
                                            <td class="text-right">{{ p.y.toFixed(6) }}</td>
                                            <td class="text-center">{{ p.designation }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>

                            <section class="parcel-section">
                                <h3 class="section-title">Feuille de calcul retour</h3>
                                <table class="data-table text-sm">
                                    <thead>
                                        <tr>
                                            <th>Points</th>
                                            <th>X</th>
                                            <th>Y</th>
                                            <th>dx</th>
                                            <th>dy</th>
                                            <th>Gisement</th>
                                            <th>Distances</th>
                                            <th>Observations</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="(p, i) in parcel.calculations.points.slice(0, -1)" :key="i">
                                            <tr>
                                                <td class="text-center">{{ p.designation }}</td>
                                                <td class="text-right">{{ p.x.toFixed(6) }}</td>
                                                <td class="text-right">{{ p.y.toFixed(6) }}</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td class="text-center">{{ p.designation }}</td>
                                            </tr>
                                            <tr>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td class="text-right">{{ parcel.calculations.dxdy[i].dx }}</td>
                                                <td class="text-right">{{ parcel.calculations.dxdy[i].dy }}</td>
                                                <td class="text-right">{{ parcel.calculations.gis[i].toFixed(4) }} g
                                                </td>
                                                <td class="text-right">{{ parcel.calculations.dist[i] }}</td>
                                                <td></td>
                                            </tr>

                                            <tr v-if="i === parcel.calculations.points.length - 2">
                                                <td class="text-center">{{
                                                    parcel.calculations.points[parcel.calculations.points.length -
                                                        1].designation }}</td>
                                                <td class="text-right">{{
                                                    parcel.calculations.points[parcel.calculations.points.length -
                                                        1].x.toFixed(6) }}</td>
                                                <td class="text-right">{{
                                                    parcel.calculations.points[parcel.calculations.points.length -
                                                        1].y.toFixed(6) }}</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </section>

                            <section class="parcel-section">
                                <h3 class="section-title">Calcul de Superficie</h3>
                                <table class="data-table text-sm">
                                    <thead>
                                        <tr>
                                            <th>Points</th>
                                            <th>X</th>
                                            <th>Y</th>
                                            <th>(Yn-Yn+2)*Xn+1</th>
                                            <th>(Xn-Xn+2)*Yn+1</th>
                                            <th>Ares</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-if="parcel.calculations.points.length > 1">
                                            <tr v-for="rowIndex in parcel.calculations.points.slice(0, -1).length + 2"
                                                :key="rowIndex">
                                                <td class="text-center">
                                                    {{ parcel.calculations.points.slice(0, -1)[(rowIndex - 1) %
                                                        parcel.calculations.points.slice(0, -1).length].designation }}
                                                </td>
                                                <td class="text-right">
                                                    {{ Number(parcel.calculations.points.slice(0, -1)[(rowIndex - 1) %
                                                        parcel.calculations.points.slice(0, -1).length].x).toFixed(6) }}
                                                </td>
                                                <td class="text-right">
                                                    {{ Number(parcel.calculations.points.slice(0, -1)[(rowIndex - 1) %
                                                        parcel.calculations.points.slice(0, -1).length].y).toFixed(6) }}
                                                </td>
                                                <td class="text-right">
                                                    {{ rowIndex - 2 >= 0 && rowIndex - 2 <
                                                        parcel.calculations.shoelace.length ?
                                                        parcel.calculations.shoelace[rowIndex - 2].valE : '' }} </td>
                                                <td class="text-right">
                                                    {{ rowIndex - 2 >= 0 && rowIndex - 2 <
                                                        parcel.calculations.shoelace.length ?
                                                        parcel.calculations.shoelace[rowIndex - 2].valD : '' }} </td>
                                            </tr>
                                        </template>
                                        <template v-else>
                                            <tr>
                                                <td class="text-center" colspan="6">Pas assez de points</td>
                                            </tr>
                                        </template>
                                        <tr class="font-bold bg-yellow-50">
                                            <td colspan="3">2S=</td>
                                            <td class="text-right">{{ parcel.calculations.sumE.toFixed(6) }}</td>
                                            <td class="text-right">{{ parcel.calculations.sumD.toFixed(6) }}</td>
                                            <td></td>
                                        </tr>
                                        <tr class="font-bold bg-green-50">
                                            <td colspan="3">S=</td>
                                            <td class="text-right">{{ parcel.calculations.sumE.toFixed(6) / 2 }} m²</td>
                                            <td class="text-right">{{ parcel.calculations.sumD.toFixed(6) / 2 }} m²</td>
                                            <td class="text-center">{{ areaDisplay(parcel.calculations) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </section>

                            <div class="reference-box">Référence : {{ parcel.geo?.reference || '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="sidebar">
                <div class="sidebar-inner">
                    <h4 class="sidebar-title">Parcelles</h4>
                    <div class="sidebar-list">
                        <Button v-for="p in parcels" :key="p.rootId" :label="p.displayLabel"
                            class="w-full justify-start" icon="pi pi-map" text @click="scrollToParcel(p.rootId)" />
                    </div>
                </div>
            </aside>
        </div>

        <ConfirmPopup />

        <Dialog header="Modifier la parcelle" v-model:visible="editDialogVisible" :modal="true"
            :style="{ width: '900px', maxWidth: '95vw' }">
            <div class="flex gap-2 mb-4">
                <Button :label="mode === 'paste' ? 'Mode Coller' : 'Mode Manuel'"
                    @click="mode = mode === 'paste' ? 'manual' : 'paste'" />
                <Button label="Ajouter un point" @click="addEditedPoint" />
                <span class="ml-auto text-sm text-gray-500">Parcelle ID: {{ activeEditParcel?.geo?.id }}</span>
            </div>
            <div v-if="mode === 'paste'">
                <label class="mb-2 block">Coller le texte (X=..., Y=... par ligne)</label>
                <Textarea v-model="pasteText" rows="6" class="w-full mb-2" />
                <Button label="Extraire" @click="parsePaste" />
            </div>
            <div v-else>
                <label class="mb-2 block">Ajout manuel des points</label>
            </div>

            <div class="mt-4 grid md:grid-cols-2 gap-4">
                <div>
                    <label>Numéro Parcelle</label>
                    <InputText :value="activeEditParcel?.geo?.parcelNumber" disabled class="w-full mb-2" />
                </div>
                <div>
                    <label>Statut</label>
                    <InputText :value="activeEditParcel?.geo?.status" disabled class="w-full mb-2" />
                </div>
                <div class="md:col-span-2">
                    <label>Référence</label>
                    <InputText :value="activeEditParcel?.geo?.reference" disabled class="w-full mb-2" />
                </div>
            </div>

            <div class="mt-4 overflow-auto" style="max-height: 45vh">
                <h3 class="font-semibold mb-2">Points ({{ editedPoints.length }})</h3>
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
                        <tr v-for="(p, i) in editedPoints" :key="i">
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
                            <td class="border p-2"><Button label="Suppr" severity="danger"
                                    @click="removeEditedPoint(i)" /></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <template #footer>
                <Button label="Annuler" @click="cancelEditPoints" />
                <Button label="Enregistrer" severity="success"
                    :disabled="editedPoints.length < 3 || new Set(editedPoints.map((p) => `${Number(p.x) || 0}|${Number(p.y) || 0}`)).size < 3"
                    @click="() => {
                        if (editedPoints.length < 3 || new Set(editedPoints.map((p) => `${Number(p.x) || 0}|${Number(p.y) || 0}`)).size < 3) {
                            toast.add({ severity: 'warn', summary: 'Points insuffisants', detail: 'Au moins 3 points distincts requis.', life: 3000 });
                        } else {
                            requestSaveNewVersion($event);
                        }
                    }" />
            </template>
        </Dialog>

        <Dialog v-model:visible="exportDialogVisible" header="Options d'export" :style="{ width: '560px' }" modal>
            <div class="space-y-6">
                <div>
                    <h4 class="text-sm font-semibold mb-2">Sections à inclure :</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div v-for="opt in sectionOptions" :key="opt.key" class="flex items-center gap-2">
                            <Checkbox :inputId="'sec_' + opt.key" :value="opt.key" v-model="selectedSections" />
                            <label :for="'sec_' + opt.key" class="text-sm">{{ opt.label }}</label>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-6">
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold mb-2">Disposition :</h4>
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <RadioButton inputId="mode_single" value="single" v-model="pageMode" />
                                <label for="mode_single" class="text-sm">Tout sur une page / feuille</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioButton inputId="mode_multi" value="multi" v-model="pageMode" />
                                <label for="mode_multi" class="text-sm">Une section par page / feuille</label>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold mb-2">Format :</h4>
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <RadioButton inputId="fmt_pdf" value="pdf" v-model="exportFormat" />
                                <label for="fmt_pdf" class="text-sm">PDF</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioButton inputId="fmt_excel" value="excel" v-model="exportFormat" />
                                <label for="fmt_excel" class="text-sm">Excel</label>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1" v-if="exportFormat === 'pdf'">
                        <h4 class="text-sm font-semibold mb-2">Orientation (PDF) :</h4>
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-2">
                                <RadioButton inputId="ori_portrait" value="portrait" v-model="pdfOrientation" />
                                <label for="ori_portrait" class="text-sm">Portrait</label>
                            </div>
                            <div class="flex items-center gap-2">
                                <RadioButton inputId="ori_landscape" value="landscape" v-model="pdfOrientation" />
                                <label for="ori_landscape" class="text-sm">Paysage</label>
                            </div>
                            <small class="text-xs text-gray-500">Paysage conseillé pour de larges tableaux.</small>
                        </div>
                    </div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-xs leading-relaxed">
                    <strong>Rappel mise en forme :</strong> Arial, Titres 14pt gras centrés, Sous-titres 12pt gras,
                    En-têtes
                    10pt fond gris (#D3D3D3). X,Y : 3 déc; distances : 2 déc; gisement : 4 déc.
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" class="p-button-text" icon="pi pi-times" @click="exportDialogVisible = false" />
                <Button label="Exporter" icon="pi pi-check" severity="success" @click="validateAndExport" />
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

        <Sidebar v-model:visible="timelineVisible" position="right" :modal="true" style="width: 420px"
            class="timeline-sidebar">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="font-semibold">{{ project ? project.title : 'Projet' }}</div>
                    <div class="text-xs text-gray-500">ID {{ project ? project.id : '—' }} • {{ project ? project.status
                        : '—'
                    }}</div>
                </div>
                <Button icon="pi pi-refresh" text severity="secondary" size="small" @click="loadTimeline" />
            </div>

            <div v-if="timelineLoading" class="text-gray-500 text-sm mb-2">Chargement...</div>
            <Timeline v-else :value="timelineEvents" align="left" class="timeline-mini">
                <template #marker="slotProps">
                    <span class="flex w-8 h-8 items-center justify-center text-white rounded-full"
                        :style="{ backgroundColor: timelineTypeMeta[slotProps.item.type] ? timelineTypeMeta[slotProps.item.type].color : '#64748b' }">
                        <i
                            :class="timelineTypeMeta[slotProps.item.type] ? timelineTypeMeta[slotProps.item.type].icon : 'pi pi-circle'" />
                    </span>
                </template>
                <template #content="slotProps">
                    <div class="timeline-entry">
                        <div class="flex justify-between items-center mb-1">
                            <span class="font-semibold text-sm">{{ timelineTypeMeta[slotProps.item.type] ?
                                timelineTypeMeta[slotProps.item.type].label : slotProps.item.type }}</span>
                            <small class="text-xs text-gray-500">{{ slotProps.item.date }}</small>
                        </div>
                        <p class="text-xs text-gray-700 whitespace-pre-line mb-1">{{ slotProps.item.description }}</p>
                        <div class="flex justify-end gap-2">
                            <Button v-if="isAdmin || isTopo" icon="pi pi-trash" text size="small" severity="danger"
                                @click="(e) => confirmDeleteTimelineEntry(e, slotProps.item.id)" />
                        </div>
                    </div>
                </template>
            </Timeline>
            <div v-if="!timelineEvents.length && !timelineLoading" class="text-sm text-gray-500">Aucune étape.</div>

            <div class="mt-4 border-t pt-3">
                <h4 class="font-semibold text-sm mb-2">Ajouter une étape</h4>
                <p v-if="isProjectDone" class="text-xs text-orange-600 mb-2">Projet terminé : ajout désactivé.</p>
                <label class="block text-xs mb-1">Date</label>
                <InputText type="datetime-local" v-model="timelineForm.date" class="w-full mb-2" />
                <label class="block text-xs mb-1">Type</label>
                <Select v-model="timelineForm.type" :options="timelineTypeOptions" optionLabel="label"
                    optionValue="value" class="w-full mb-2" />
                <label class="block text-xs mb-1">Description</label>
                <Textarea v-model="timelineForm.description" rows="3" class="w-full mb-2" />
                <Button label="Ajouter" icon="pi pi-plus" class="w-full" :loading="timelineSubmitting"
                    :disabled="isProjectDone || !timelineForm.description" @click="submitTimelineEntry" />
            </div>
        </Sidebar>

        <Dialog v-model:visible="zoomDialogVisible" modal dismissableMask :closeOnEscape="true"
            :style="{ width: '96vw', maxWidth: '1400px' }" :contentStyle="{ height: '85vh', overflow: 'auto' }"
            class="zoom-dialog" header="Aperçu plein écran">
            <div v-if="activeZoomParcel" class="zoom-content">
                <div class="zoom-header">
                    <div>
                        <div class="parcel-title">Parcelle {{ activeZoomParcel.displayLabel }}</div>
                        <div class="parcel-meta">Réf: {{ activeZoomParcel.geo?.reference || '—' }} • Points: {{
                            activeZoomParcel.geo?.points?.length || 0 }}</div>
                        <div class="parcel-submeta">
                            <span>Mis à jour: {{ activeZoomParcel.geo?.updatedAt || activeZoomParcel.geo?.createdAt ||
                                '—'
                            }}</span>
                            <span>Status: {{ activeZoomParcel.geo?.status }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Select v-if="activeZoomParcel.versions?.length" v-model="activeZoomParcel.selectedVersionId"
                            :options="activeZoomParcel.versions.map((v) => ({ label: formatVersionLabel(v), value: v.id }))"
                            optionLabel="label" optionValue="value" class="w-52"
                            @change="onChangeVersion(activeZoomParcel, activeZoomParcel.selectedVersionId)" />
                        <Button icon="pi pi-upload" class="p-button-sm" severity="success"
                            @click="openExportDialog(activeZoomParcel.rootId)" />
                    </div>
                </div>

                <section class="parcel-section">
                    <h3 class="section-title">Tableau de coordonnées</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Points</th>
                                <th>X</th>
                                <th>Y</th>
                                <th>Observation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, i) in activeZoomParcel.calculations.points" :key="i">
                                <td>{{ p.designation }}</td>
                                <td class="text-right">{{ p.x.toFixed(6) }}</td>
                                <td class="text-right">{{ p.y.toFixed(6) }}</td>
                                <td class="text-center">{{ p.designation }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="parcel-section">
                    <h3 class="section-title">Feuille de calcul retour</h3>
                    <table class="data-table text-sm">
                        <thead>
                            <tr>
                                <th>Points</th>
                                <th>X</th>
                                <th>Y</th>
                                <th>dx</th>
                                <th>dy</th>
                                <th>Gisement</th>
                                <th>Distances</th>
                                <th>Observations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(p, i) in activeZoomParcel.calculations.points.slice(0, -1)" :key="i">
                                <tr>
                                    <td class="text-center">{{ p.designation }}</td>
                                    <td class="text-right">{{ p.x.toFixed(6) }}</td>
                                    <td class="text-right">{{ p.y.toFixed(6) }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-center">{{ p.designation }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-right">{{ activeZoomParcel.calculations.dxdy[i].dx }}</td>
                                    <td class="text-right">{{ activeZoomParcel.calculations.dxdy[i].dy }}</td>
                                    <td class="text-right">{{ activeZoomParcel.calculations.gis[i].toFixed(4) }} g</td>
                                    <td class="text-right">{{ activeZoomParcel.calculations.dist[i] }}</td>
                                    <td></td>
                                </tr>

                                <tr v-if="i === activeZoomParcel.calculations.points.length - 2">
                                    <td class="text-center">{{
                                        activeZoomParcel.calculations.points[activeZoomParcel.calculations.points.length
                                            - 1].designation }}</td>
                                    <td class="text-right">{{
                                        activeZoomParcel.calculations.points[activeZoomParcel.calculations.points.length
                                            - 1].x.toFixed(6) }}</td>
                                    <td class="text-right">{{
                                        activeZoomParcel.calculations.points[activeZoomParcel.calculations.points.length
                                            - 1].y.toFixed(6) }}</td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </section>

                <section class="parcel-section">
                    <h3 class="section-title">Calcul de Superficie</h3>
                    <table class="data-table text-sm">
                        <thead>
                            <tr>
                                <th>Points</th>
                                <th>X</th>
                                <th>Y</th>
                                <th>(Yn-Yn+2)*Xn+1</th>
                                <th>(Xn-Xn+2)*Yn+1</th>
                                <th>Ares</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-if="activeZoomParcel.calculations.points.length > 1">
                                <tr v-for="rowIndex in activeZoomParcel.calculations.points.slice(0, -1).length + 2"
                                    :key="rowIndex">
                                    <td class="text-center">
                                        {{ activeZoomParcel.calculations.points.slice(0, -1)[(rowIndex - 1) %
                                            activeZoomParcel.calculations.points.slice(0, -1).length].designation }}
                                    </td>
                                    <td class="text-right">
                                        {{ Number(activeZoomParcel.calculations.points.slice(0, -1)[(rowIndex - 1) %
                                            activeZoomParcel.calculations.points.slice(0, -1).length].x).toFixed(6) }}
                                    </td>
                                    <td class="text-right">
                                        {{ Number(activeZoomParcel.calculations.points.slice(0, -1)[(rowIndex - 1) %
                                            activeZoomParcel.calculations.points.slice(0, -1).length].y).toFixed(6) }}
                                    </td>
                                    <td class="text-right">
                                        {{ rowIndex - 2 >= 0 && rowIndex - 2 <
                                            activeZoomParcel.calculations.shoelace.length ?
                                            activeZoomParcel.calculations.shoelace[rowIndex - 2].valE : '' }} </td>
                                    <td class="text-right">
                                        {{ rowIndex - 2 >= 0 && rowIndex - 2 <
                                            activeZoomParcel.calculations.shoelace.length ?
                                            activeZoomParcel.calculations.shoelace[rowIndex - 2].valD : '' }} </td>
                                </tr>
                            </template>
                            <template v-else>
                                <tr>
                                    <td class="text-center" colspan="6">Pas assez de points</td>
                                </tr>
                            </template>
                            <tr class="font-bold bg-yellow-50">
                                <td colspan="3">2S=</td>
                                <td class="text-right">{{ activeZoomParcel.calculations.sumE.toFixed(6) }}</td>
                                <td class="text-right">{{ activeZoomParcel.calculations.sumD.toFixed(6) }}</td>
                                <td></td>
                            </tr>
                            <tr class="font-bold bg-green-50">
                                <td colspan="3">S=</td>
                                <td class="text-right">{{ activeZoomParcel.calculations.sumE.toFixed(6) / 2 }} m²</td>
                                <td class="text-right">{{ activeZoomParcel.calculations.sumD.toFixed(6) / 2 }} m²</td>
                                <td class="text-center">{{ areaDisplay(activeZoomParcel.calculations) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div class="reference-box">Référence : {{ activeZoomParcel.geo?.reference || '—' }}</div>
            </div>
            <template #footer>
                <Button label="Fermer" class="p-button-text" icon="pi pi-times" @click="zoomDialogVisible = false" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.page-container {
    padding: 0px;
    margin: 0 auto;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin: 0 -24px 12px;
    position: sticky;
    top: 0;
    z-index: 40;
    background: var(--surface-ground, #f7f7f8);
    padding: 10px 24px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    border-bottom: 1px solid var(--surface-border, #e5e7eb);
    backdrop-filter: blur(4px);
    background: var(--surface-card, #fff);
    border: 1px solid var(--surface-border, #e5e7eb);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
    border-radius: 12px;
}

.top-row> :first-child {
    flex: 1;
    min-width: 0;
}

.info-card {
    padding: 12px 14px;
    min-width: 360px;
    flex: 1;
}

.avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--primary-color, #3b82f6);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    /* Removed inner scroll */
    background: var(--surface-ground, #f9fafb);
}

.status-pill.ok {
    top: 88px;
    color: #166534;
}

.status-pill.ko {
    border-color: #dc2626;
    color: #991b1b;
}

.status-pill.warn {
    border-color: #d97706;
    color: #92400e;
}

.status-pill.neutral {
    color: #4b5563;
}

.actions {
    min-width: 180px;
    text-align: right;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    align-items: flex-end;
    gap: 4px;
}

.page-header-label {
    font-weight: 700;
    font-size: 14px;
    color: var(--text-color, #111827);
}

.content-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 260px;
    gap: 16px;
    flex: 1;
    min-height: 0;
    margin: 0;
}

.parcels-column {
    background: var(--surface-ground, #f7f7f8);
    border: 1px solid var(--surface-border, #e5e7eb);
    border-radius: 12px;
    height: 100%;
    min-height: 0;
    overflow: auto;
}

.parcels-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.parcel-card {
    background: #fff;
    border: 1px solid var(--surface-border, #e5e7eb);
    border-radius: 12px;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.04);
    overflow: hidden;
}

.parcel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.08), rgba(16, 185, 129, 0.08));
    border-bottom: 1px solid var(--surface-border, #e5e7eb);
}

.parcel-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-color, #111827);
}

.parcel-meta {
    font-size: 13px;
    color: #6b7280;
}

.parcel-body {
    padding: 12px 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.parcel-submeta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 13px;
    color: #4b5563;
}

.parcel-section {
    border: 1px solid var(--surface-border, #e5e7eb);
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}

.section-title {
    padding: 10px 12px;
    background: var(--surface-section, #f1f5f9);
    font-weight: 700;
    font-size: 15px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    border: 1px solid #111;
    padding: 8px 10px;
}

.data-table th {
    background: #e5e7eb;
    text-align: center;
}

.reference-box {
    border: 2px solid #000;
    padding: 10px 12px;
    font-weight: 700;
    font-size: 14px;
    text-align: center;
    background: #fff;
    max-width: 320px;
    margin: 8px auto 0;
}

.sidebar {
    position: relative;
}

.sidebar-inner {
    position: sticky;
    top: 84px;
    border: 1px solid var(--surface-border, #e5e7eb);
    border-radius: 12px;
    padding: 12px;
    background: #fff;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
}

.sidebar-title {
    font-weight: 700;
    margin-bottom: 8px;
}

.sidebar-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.loading {
    padding: 24px;
    text-align: center;
    color: #6b7280;
}

.zoom-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.zoom-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.timeline-sidebar :deep(.p-sidebar-content) {
    padding: 1.25rem;
}

.timeline-mini :deep(.p-timeline-event-opposite) {
    display: none;
}

.timeline-mini :deep(.p-timeline-event-content) {
    margin: 0.5rem 0 0 0.5rem;
}

.timeline-entry {
    background: #f8fafc;
    border: 1px solid var(--surface-border, #e5e7eb);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
}

@media (max-width: 1024px) {
    .content-row {
        grid-template-columns: 1fr;
    }

    .sidebar {
        order: -1;
    }
}

@media print {
    .page-container {
        padding: 0;
    }

    .top-row,
    .sidebar,
    .actions,
    .parcels-column {
        display: none;
    }

    .parcel-card,
    .parcel-section,
    .reference-box {
        box-shadow: none;
    }

    .parcel-card {
        border: 0;
    }

    .parcel-section {
        border: 2px solid #000;
    }

    .section-title {
        background: #d3d3d3;
    }

    .data-table th,
    .data-table td {
        padding: 4px !important;
    }
}
</style>