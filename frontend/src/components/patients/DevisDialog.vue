<script setup>
import DevisForm from '@/components/consultations/DevisForm.vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import { defaultSoinList, normalizeSoinList } from '@/services/consultations';
import { createPatientFiche, loadFicheMedicale, saveDevis } from '@/services/ficheMedicale';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { fetchPatientDossier } from '@/services/patients';
import { fetchDevisPrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';
import { emptyDevisEntry, listFicheDevis, nextDevisType, normalizeDevisServices, toApiDevis } from '@/utils/patientDevis';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, ref, watch } from 'vue';

const visible = defineModel('visible', { type: Boolean, default: false });

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' },
    /** Devis existant : { id, ficheId, date, description, type, services|contenus } */
    devis: { type: Object, default: null },
    /** Fiches déjà chargées, la plus récente en premier. */
    fiches: { type: Array, default: () => [] }
});

const emit = defineEmits(['saved']);

const auth = useAuthStore();
const toast = useToast();
const { printComponent } = usePrinter();
const token = () => auth.token || localStorage.getItem('token');

const soinsList = ref(defaultSoinList);
const draft = ref(toDraft(emptyDevisEntry()));
const saving = ref(false);
const printing = ref(false);
const saved = ref(false);
const savedDevisId = ref(null);
const savedFicheId = ref(null);
const savedType = ref(null);
const ignoreDraftWatch = ref(false);

const isEdit = computed(() => Boolean(props.devis?.id || savedDevisId.value));
const dialogTitle = computed(() => (isEdit.value ? 'Modifier le devis' : 'Nouveau devis'));
const canPrint = computed(() => saved.value && Number.isFinite(Number(savedDevisId.value)));

function toDraft(entry) {
    const services = normalizeDevisServices(entry);
    const normalized = {
        id: Number(entry?.id) || null,
        type: entry?.type ?? null,
        date: entry?.date || emptyDevisEntry().date,
        description: entry?.description || '',
        services
    };
    return {
        ...normalized,
        devisList: [normalized],
        activeDevisIndex: 0
    };
}

const activeEntry = () => {
    const list = draft.value?.devisList;
    if (Array.isArray(list) && list.length) return list[draft.value.activeDevisIndex || 0] || list[0];
    return draft.value;
};

const markUnsaved = () => {
    if (ignoreDraftWatch.value) return;
    saved.value = false;
};

watch(draft, markUnsaved, { deep: true });

const loadSoins = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token());
        soinsList.value = normalizeSoinList(settings?.soins || defaultSoinList);
    } catch {
        soinsList.value = defaultSoinList;
    }
};

const resetState = async () => {
    ignoreDraftWatch.value = true;
    saved.value = false;
    savedDevisId.value = props.devis?.id ? Number(props.devis.id) : null;
    savedFicheId.value = props.devis?.ficheId ? Number(props.devis.ficheId) : null;
    savedType.value = props.devis?.type ?? null;
    draft.value = toDraft(props.devis || emptyDevisEntry());
    if (savedDevisId.value) {
        saved.value = true;
    }
    await nextTick();
    ignoreDraftWatch.value = false;
};

watch(visible, (open) => {
    if (!open) return;
    resetState();
    loadSoins();
});

const resolveTargetFicheId = async () => {
    if (savedFicheId.value) {
        return Number(savedFicheId.value);
    }

    let fiches = Array.isArray(props.fiches) ? props.fiches : [];
    if (!fiches.length && props.patientId) {
        const dossier = await fetchPatientDossier(props.patientId, token());
        fiches = Array.isArray(dossier?.fiches) ? dossier.fiches : [];
    }

    if (fiches.length && fiches[0]?.id) {
        return Number(fiches[0].id);
    }

    if (!props.patientId) {
        throw new Error('Patient requis');
    }

    const created = await createPatientFiche(props.patientId, token());
    return Number(created?.ficheId);
};

const submit = async () => {
    const entry = activeEntry();
    const lines = normalizeDevisServices(entry).filter((line) => String(line.designation || '').trim());
    if (!lines.length) {
        toast.add({ severity: 'warn', summary: 'Devis', detail: 'Ajoutez au moins une prestation.', life: 2500 });
        return;
    }

    const updating = Boolean(savedDevisId.value);
    saving.value = true;
    try {
        const ficheId = await resolveTargetFicheId();
        if (!ficheId) {
            throw new Error('Fiche introuvable');
        }

        const currentFiche = await loadFicheMedicale(ficheId, token());
        const existing = listFicheDevis(currentFiche);
        const beforeIds = new Set(existing.map((item) => Number(item?.id)).filter((id) => id > 0));
        const type = savedDevisId.value ? (entry.type ?? savedType.value ?? 0) : nextDevisType(existing);
        const payload = toApiDevis({ ...entry, id: savedDevisId.value, services: lines }, Number.isFinite(Number(type)) ? Number(type) : 0);

        await saveDevis(ficheId, payload, token());

        const refreshed = await loadFicheMedicale(ficheId, token());
        const refreshedList = listFicheDevis(refreshed);
        let devisId = savedDevisId.value ? Number(savedDevisId.value) : null;
        if (!devisId) {
            const created = refreshedList.find((item) => item?.id && !beforeIds.has(Number(item.id)));
            devisId = created?.id ? Number(created.id) : null;
        }

        ignoreDraftWatch.value = true;
        const savedEntry = refreshedList.find((item) => Number(item?.id) === devisId) || { ...entry, id: devisId, services: lines };
        draft.value = toDraft({ ...savedEntry, ficheId });
        savedDevisId.value = devisId;
        savedFicheId.value = ficheId;
        savedType.value = savedEntry?.type ?? type;
        saved.value = Boolean(devisId);
        await nextTick();
        ignoreDraftWatch.value = false;

        toast.add({
            severity: 'success',
            summary: 'Devis',
            detail: updating ? 'Devis modifié' : 'Devis enregistré',
            life: 2500
        });
        emit('saved', { ficheId, devisId });
    } catch (error) {
        logAppError('Devis', error);
        toast.add({
            severity: 'error',
            summary: 'Devis',
            detail: error?.response?.data?.error || "Enregistrement impossible",
            life: 3500
        });
    } finally {
        saving.value = false;
    }
};

const printDevis = async () => {
    if (!canPrint.value) return;
    printing.value = true;
    try {
        const result = await fetchDevisPrintData(savedDevisId.value, token());
        await printComponent(PrintDevisBody, { doc: result.doc, title: result.title || 'Devis' });
    } catch (error) {
        logAppError('Impression devis', error);
        toast.add({ severity: 'error', summary: 'Impression', detail: "Impossible d'imprimer le devis.", life: 3000 });
    } finally {
        printing.value = false;
    }
};
</script>

<template>
    <AppDialog
        v-model:visible="visible"
        :title="dialogTitle"
        :subtitle="patientName || null"
        icon="pi pi-file-edit"
        icon-tone="primary"
        size="xl"
        :loading="saving"
        cancel-label="Fermer"
        confirm-label="Enregistrer"
        confirm-icon="pi pi-check"
        @cancel="visible = false"
        @confirm="submit"
    >
        <template #headerExtra>
            <Tag :value="saved ? 'Enregistré' : 'Non enregistré'" :severity="saved ? 'success' : 'warn'" />
        </template>

        <DevisForm v-model="draft" single :soins="soinsList" :saving="saving" />

        <template #footerStart>
            <Button v-if="canPrint" label="Imprimer" icon="pi pi-print" severity="secondary" outlined :loading="printing" @click="printDevis" />
        </template>
    </AppDialog>
</template>
