<script setup>
import { logAppError } from '@/utils/appLogger';

import { createClient, fetchClients } from '@/services/olders/clientApi';
import { createProjectWithParcels, validateParcelNumber as validateParcelNumberApi } from '@/services/projectApi';
import { saveDefaultFiles } from '@/services/projectFs';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import TabPanel from 'primevue/tabpanel';
import TabView from 'primevue/tabview';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    clientId: { type: [Number, String, null], default: null }
});

const emit = defineEmits(['update:visible', 'created']);

const toast = useToast();
const token = localStorage.getItem('token');

const modelVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
});

const projectTitle = ref('');
const projectLocality = ref('');
const projectStatus = ref('ongoing');
const projectStatusOptions = ref([
    { label: 'En cours', value: 'ongoing' },
    { label: 'Terminé', value: 'done' }
]);
const createProjectLoading = ref(false);

const clients = ref([]);
const clientsLoading = ref(false);
const projectClientId = ref(null);
const inlineClientName = ref('');
const inlineClientAddress = ref('');
const creatingInlineClient = ref(false);

const clientOptions = computed(() =>
    clients.value.map((c) => ({
        label: `${c.name || 'Sans nom'}${c.code ? ' (' + c.code + ')' : ''}${c.address ? ' • ' + c.address : ''}`,
        value: c.id
    }))
);

const createParcelTab = (initialNumber = '/1') => ({
    id: `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 6)}`,
    mode: 'paste',
    pasteText: '',
    points: [],
    parcelNumber: initialNumber,
    parcelReference: '',
    parcelNumberValid: true,
    parcelNumberChecking: false,
    parcelNumberMsg: '',
    geoStatus: 'active'
});

const parcelTabs = ref([createParcelTab('/1')]);
const activeParcelIndex = ref(0);
const parcelValidationTimers = new Map();

const toFixed6Str = (val) => {
    const num = typeof val === 'number' ? val : parseFloat(String(val).replace(',', '.'));
    if (Number.isFinite(num)) return num.toFixed(6);
    return '0.000000';
};

const parseParcelNumber = (num) => {
    const m = String(num ?? '').match(/^(.+?)?\/(\d+)$/);
    if (!m) return null;
    return { prefix: m[1] ?? '', index: Number(m[2]) };
};

const nextParcelNumberSuggestion = () => {
    const lastWithNumber = [...parcelTabs.value].reverse().find((tab) => tab.parcelNumber && tab.parcelNumber.includes('/'));
    if (!lastWithNumber) return '/1';
    const parsed = parseParcelNumber(lastWithNumber.parcelNumber);
    if (!parsed) return '/1';
    const nextIdx = parsed.index + 1;
    const prefixPart = parsed.prefix ? `${parsed.prefix}/` : '/';
    return `${prefixPart}${nextIdx}`;
};

const resetParcelTab = (tab) => {
    const timer = parcelValidationTimers.get(tab.id);
    if (timer) clearTimeout(timer);
    const fresh = createParcelTab(nextParcelNumberSuggestion());
    Object.assign(tab, fresh, { id: tab.id });
};

const addParcelTab = () => {
    const suggested = nextParcelNumberSuggestion();
    parcelTabs.value.push(createParcelTab(suggested));
    nextTick(() => {
        activeParcelIndex.value = parcelTabs.value.length - 1;
    });
};

const removeParcelTab = (id) => {
    if (parcelTabs.value.length === 1) {
        resetParcelTab(parcelTabs.value[0]);
        return;
    }
    const timer = parcelValidationTimers.get(id);
    if (timer) clearTimeout(timer);
    parcelValidationTimers.delete(id);
    parcelTabs.value = parcelTabs.value.filter((tab) => tab.id !== id);
    activeParcelIndex.value = Math.max(0, activeParcelIndex.value - 1);
};

const parsePasteForTab = (tab) => {
    tab.points = [];
    const lines = (tab.pasteText || '')
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
            tab.points.push({ designation: `B${idx}`, x: toFixed6Str(mx[1]), y: toFixed6Str(my[1]) });
            idx++;
        }
    }
};

const handlePasteInput = (tab) => {
    parsePasteForTab(tab);
};

const addPointManualTab = (tab) => {
    const idx = (tab.points?.length || 0) + 1;
    tab.points.push({ designation: `B${idx}`, x: '0.000000', y: '0.000000' });
};

const removePointTab = (tab, i) => {
    tab.points.splice(i, 1);
};

const isParcelTabEmpty = (tab) => {
    const numberVal = tab.parcelNumber?.trim() ?? '';
    const numberIsDefault = /^\/\d+$/.test(numberVal);
    const hasNumber = !!numberVal && !numberIsDefault;
    const hasPoints = Array.isArray(tab.points) && tab.points.length > 0;
    const hasPaste = !!tab.pasteText?.trim();
    const hasRef = !!tab.parcelReference?.trim();
    return !hasNumber && !hasPoints && !hasPaste && !hasRef;
};

const hasMinDistinctPointsTab = (tab) => {
    if (!Array.isArray(tab.points) || tab.points.length < 3) return false;
    const set = new Set(
        tab.points.map((p) => `${Number.isFinite(Number(p.x)) ? parseFloat(p.x) : 0}|${Number.isFinite(Number(p.y)) ? parseFloat(p.y) : 0}`)
    );
    return set.size >= 3;
};

const parcelTabIssues = (tab) => {
    const issues = [];
    if (isParcelTabEmpty(tab)) return issues;
    if (!tab.parcelNumber?.trim()) issues.push('numéro manquant');
    else if (!tab.parcelNumberValid) issues.push(tab.parcelNumberMsg || 'numéro indisponible');
    if (tab.mode === 'paste' && tab.pasteText?.trim() && tab.points.length === 0) issues.push('aucun point détecté');
    if (!hasMinDistinctPointsTab(tab)) issues.push('points insuffisants');
    return issues;
};

const isParcelTabValid = (tab) => {
    if (isParcelTabEmpty(tab)) return true;
    return parcelTabIssues(tab).length === 0;
};

const filledParcelTabs = computed(() => parcelTabs.value.filter((tab) => !isParcelTabEmpty(tab)));
const invalidParcelTabs = computed(() => filledParcelTabs.value.filter((tab) => !isParcelTabValid(tab)));

const parcelStatusMessage = computed(() => {
    if (filledParcelTabs.value.length === 0) {
        return { severity: 'info', text: 'Aucune parcelle ajoutée (optionnel).' };
    }
    if (invalidParcelTabs.value.length > 0) {
        const detail = invalidParcelTabs.value
            .map((tab, idx) => `Tab ${idx + 1}: ${parcelTabIssues(tab).join(', ')}`)
            .join(' | ');
        return {
            severity: 'warn',
            text: `Parcelles à corriger (${invalidParcelTabs.value.length}/${filledParcelTabs.value.length}) — ${detail}`
        };
    }
    return { severity: 'success', text: `${filledParcelTabs.value.length} parcelle(s) prêtes à être créées.` };
});

const onParcelNumberInput = (tab) => {
    const t = parcelValidationTimers.get(tab.id);
    if (t) clearTimeout(t);
    parcelValidationTimers.set(
        tab.id,
        setTimeout(() => {
            validateParcelNumberForTab(tab);
        }, 300)
    );
};

const validateParcelNumberForTab = async (tab) => {
    if (!tab.parcelNumber || tab.parcelNumber.length < 2) {
        tab.parcelNumberValid = false;
        tab.parcelNumberMsg = '';
        return;
    }
    if (/^\/\d+$/.test(tab.parcelNumber)) {
        tab.parcelNumberValid = true;
        tab.parcelNumberMsg = '';
        return;
    }
    try {
        tab.parcelNumberChecking = true;
        const res = await validateParcelNumberApi(tab.parcelNumber, token);
        tab.parcelNumberValid = !!res.data?.valid;
        tab.parcelNumberMsg = res.data?.reason || '';
    } catch (e) {
        tab.parcelNumberValid = true;
        tab.parcelNumberMsg = '';
    } finally {
        tab.parcelNumberChecking = false;
    }
};

const canSubmitProjectWithParcels = computed(() => {
    if (!projectTitle.value?.trim()) return false;
    if (filledParcelTabs.value.length === 0) return true;
    if (invalidParcelTabs.value.length > 0) return false;
    return !filledParcelTabs.value.some((tab) => tab.parcelNumberChecking);
});

const buildParcelPayload = (tab) => ({
    parcelNumber: tab.parcelNumber,
    reference: tab.parcelReference || null,
    status: tab.geoStatus,
    points: tab.points.map((p) => ({
        designation: p.designation,
        x: Number.isFinite(Number(p.x)) ? parseFloat(p.x) : 0,
        y: Number.isFinite(Number(p.y)) ? parseFloat(p.y) : 0
    }))
});

const resetProjectForm = () => {
    projectTitle.value = '';
    projectLocality.value = '';
    projectStatus.value = 'ongoing';
    parcelTabs.value = [createParcelTab('/1')];
    activeParcelIndex.value = 0;
    projectClientId.value = props.clientId ?? null;
    inlineClientName.value = '';
    inlineClientAddress.value = '';
};

const loadClients = async () => {
    try {
        clientsLoading.value = true;
        clients.value = await fetchClients(token);
    } catch (e) {
        logAppError('ProjectCreateDialog', e);
        toast.add({ severity: 'error', summary: 'Clients', detail: 'Impossible de charger les clients.', life: 3000 });
    } finally {
        clientsLoading.value = false;
    }
};

const createInlineClient = async () => {
    if (creatingInlineClient.value) return;
    if (!inlineClientName.value.trim()) {
        toast.add({ severity: 'warn', summary: 'Client', detail: 'Nom du client requis.', life: 2500 });
        return;
    }
    try {
        creatingInlineClient.value = true;
        const saved = await createClient({ nom: inlineClientName.value, adresse: inlineClientAddress.value || null }, token);
        clients.value.push(saved);
        projectClientId.value = saved.id;
        inlineClientName.value = '';
        inlineClientAddress.value = '';
        toast.add({ severity: 'success', summary: 'Client', detail: 'Client créé et sélectionné.', life: 3000 });
    } catch (e) {
        logAppError('ProjectCreateDialog', e);
        toast.add({ severity: 'error', summary: 'Client', detail: 'Création du client impossible.', life: 3000 });
    } finally {
        creatingInlineClient.value = false;
    }
};

const closeDialog = () => {
    modelVisible.value = false;
};

const createProject = async () => {
    if (createProjectLoading.value) return;
    try {
        createProjectLoading.value = true;
        if (!projectTitle.value?.trim()) {
            toast.add({ severity: 'warn', summary: 'Titre requis', detail: 'Le titre du projet est obligatoire.', life: 3000 });
            return;
        }
        if (filledParcelTabs.value.length > 0 && invalidParcelTabs.value.length > 0) {
            toast.add({ severity: 'warn', summary: 'Parcelles incomplètes', detail: 'Corrigez les parcelles avant de créer.', life: 3000 });
            return;
        }

        const parcelsPayload = filledParcelTabs.value.filter((tab) => isParcelTabValid(tab)).map((tab) => buildParcelPayload(tab));
        const payload = {
            title: projectTitle.value,
            locality: projectLocality.value,
            status: projectStatus.value,
            parcels: parcelsPayload,
            clientId: projectClientId.value || null
        };
        const res = await createProjectWithParcels(payload, token);

        const projectId = res.data?.project?.id ?? res.data?.id ?? '—';
        const projTitle = res.data?.project?.title || projectTitle.value;
        const projLocality = res.data?.project?.locality || projectLocality.value;
        const createdGeoSheets = Array.isArray(res.data?.geoSheets) ? res.data.geoSheets : [];
        const createdParcels = res.data?.createdParcels ?? createdGeoSheets.length ?? parcelsPayload.length;

        if (createdGeoSheets.length) {
            for (const geo of createdGeoSheets) {
                await saveDefaultFiles(projTitle, projLocality, geo, (payload) => toast.add(payload));
            }
        }

        toast.add({
            severity: 'success',
            summary: 'Projet créé',
            detail: createdParcels ? `Projet ID ${projectId} avec ${createdParcels} parcelle(s)` : `Projet ID ${projectId} sans parcelle`,
            life: 4000
        });
        emit('created', res.data);
        resetProjectForm();
        closeDialog();
    } catch (e) {
        logAppError('ProjectCreateDialog', e);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Création projet impossible', life: 3000 });
    } finally {
        createProjectLoading.value = false;
    }
};

onMounted(() => {
    if (props.visible) {
        resetProjectForm();
        loadClients();
    }
});

watch(
    () => props.visible,
    (val) => {
        if (val) {
            resetProjectForm();
            loadClients();
        }
    }
);

watch(
    () => props.clientId,
    (val) => {
        if (val && props.visible) {
            projectClientId.value = val;
        }
    }
);
</script>

<template>
    <Dialog v-model:visible="modelVisible" :modal="true" :style="{ width: '1100px', maxWidth: '95vw' }"
        contentClass="project-dialog" @hide="resetProjectForm">
        <template #header>
            <div class="project-modal-header">
                <div>
                    <div class="text-lg font-semibold">Nouveau Projet</div>
                    <small class="text-gray-500">Créez le projet et ajoutez plusieurs parcelles via les onglets.</small>
                </div>
                <Message :severity="parcelStatusMessage.severity" :closable="false" class="status-message">
                    {{ parcelStatusMessage.text }}
                </Message>
            </div>
        </template>

        <div v-if="createProjectLoading" class="project-dialog-loading">
            <i class="pi pi-spin pi-spinner"></i>
            <span>Création en cours...</span>
        </div>

        <div class="project-dialog-scroll">
            <div class="grid md:grid-cols-3 gap-3 mb-4">
                <div class="md:col-span-2">
                    <label>Type de travail <span class="text-red-500">*</span></label>
                    <InputText v-model="projectTitle" class="w-full" />
                </div>
                <div>
                    <label>Statut</label>
                    <Select v-model="projectStatus" :options="projectStatusOptions" optionLabel="label"
                        optionValue="value" class="w-full" placeholder="Choisir..." />
                </div>
                <div class="md:col-span-3">
                    <label>Localité</label>
                    <InputText v-model="projectLocality" class="w-full" />
                </div>
            </div>

            <div class="client-panel mb-5">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <div class="font-semibold">Client (optionnel)</div>
                        <small class="text-gray-500">Sélectionnez un client existant ou créez-le à la volée.</small>
                    </div>
                    <Button text icon="pi pi-refresh" label="Rafraîchir" :loading="clientsLoading"
                        @click="loadClients" />
                </div>
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label>Client existant</label>
                        <Select v-model="projectClientId" :options="clientOptions" optionLabel="label"
                            optionValue="value" class="w-full" placeholder="Sans client" :loading="clientsLoading"
                            showClear />
                        <small class="text-gray-500">Laissez vide pour ne pas lier de client.</small>
                    </div>
                    <div class="space-y-2">
                        <label>Créer et sélectionner</label>
                        <div class="grid md:grid-cols-2 gap-2">
                            <InputText v-model="inlineClientName" placeholder="Nom du client *" />
                            <InputText v-model="inlineClientAddress" placeholder="Adresse (optionnel)" />
                        </div>
                        <div class="flex gap-2 items-center">
                            <Button label="Créer le client" icon="pi pi-plus" :loading="creatingInlineClient"
                                @click="createInlineClient" />
                            <small class="text-gray-500">Le client sera automatiquement sélectionné.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mb-3">
                <h4 class="text-base font-semibold">Parcelles (optionnel)</h4>
                <div class="flex items-center gap-2">
                    <Button icon="pi pi-plus" label="Ajouter une parcelle" @click="addParcelTab" />
                    <span class="tab-tip">Les onglets vides sont ignorés.</span>
                </div>
            </div>

            <TabView v-model:activeIndex="activeParcelIndex">
                <TabPanel v-for="(tab, i) in parcelTabs" :key="tab.id">
                    <template #header>
                        <span class="flex items-center gap-2">
                            <span class="status-dot"
                                :class="{ ok: !isParcelTabEmpty(tab) && isParcelTabValid(tab), warn: !isParcelTabValid(tab) && !isParcelTabEmpty(tab) }"></span>
                            <span>Parcelle {{ i + 1 }}</span>
                        </span>
                    </template>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm">
                            <Button text icon="pi pi-refresh" label="Réinitialiser"
                                @click="() => resetParcelTab(tab)" />
                            <Button v-if="parcelTabs.length > 1" text icon="pi pi-trash" severity="danger"
                                label="Supprimer" @click="() => removeParcelTab(tab.id)" />
                            <span
                                :class="isParcelTabEmpty(tab) ? 'text-gray-500' : isParcelTabValid(tab) ? 'text-green-600' : 'text-red-600'">
                                <span v-if="isParcelTabEmpty(tab)">Onglet vide : ignoré</span>
                                <span v-else-if="isParcelTabValid(tab)">Parcelle prête</span>
                                <span v-else>Problèmes : {{ parcelTabIssues(tab).join(', ') }}</span>
                            </span>
                        </div>

                        <div class="grid md:grid-cols-3 gap-3">
                            <div>
                                <label>Numéro de parcelle</label>
                                <InputText v-model="tab.parcelNumber" class="w-full"
                                    @input="() => onParcelNumberInput(tab)" />
                                <small v-if="tab.parcelNumberChecking" class="text-gray-500">Vérification...</small>
                                <small v-else-if="tab.parcelNumber && !tab.parcelNumberValid" class="text-red-600">{{
                                    tab.parcelNumberMsg || 'Numéro déjà utilisé' }}</small>
                                <small v-else-if="tab.parcelNumber && tab.parcelNumberValid"
                                    class="text-green-600">Numéro
                                    disponible</small>
                            </div>
                            <div>
                                <label>Statut</label>
                                <Select v-model="tab.geoStatus"
                                    :options="[{ label: 'Actif', value: 'active' }, { label: 'Brouillon', value: 'draft' }, { label: 'Archivé', value: 'archived' }]"
                                    optionLabel="label" optionValue="value" class="w-full" placeholder="Choisir..." />
                            </div>
                            <div>
                                <label>Référence (optionnel)</label>
                                <InputText v-model="tab.parcelReference" class="w-full" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button :label="tab.mode === 'paste' ? 'Mode Coller' : 'Mode Manuel'"
                                @click="tab.mode = tab.mode === 'paste' ? 'manual' : 'paste'" />
                            <Button label="Ajouter un point" @click="() => addPointManualTab(tab)" />
                            <span class="tab-tip">Copiez/collez ou ajoutez les points manuellement.</span>
                        </div>

                        <div v-if="tab.mode === 'paste'">
                            <label class="mb-2 block">Coller le texte (X=..., Y=... par ligne)</label>
                            <Textarea v-model="tab.pasteText" rows="5" class="w-full mb-2"
                                @input="() => handlePasteInput(tab)" />
                            <small class="text-gray-600">{{ tab.points.length }} point(s) détectés</small>
                        </div>

                        <div class="overflow-auto" style="max-height: 40vh">
                            <table class="w-full table-auto border-collapse text-sm">
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
                                    <tr v-for="(p, idx) in tab.points" :key="idx">
                                        <td class="border p-2">{{ idx + 1 }}</td>
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
                                                @click="() => removePointTab(tab, idx)" /></td>
                                    </tr>
                                    <tr v-if="!tab.points.length">
                                        <td colspan="5" class="p-3 text-center text-gray-500">Aucun point</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </TabPanel>
            </TabView>
        </div>

        <template #footer>
            <Button label="Annuler" @click="closeDialog" />
            <Button label="Créer" severity="success" :disabled="!canSubmitProjectWithParcels || createProjectLoading"
                :loading="createProjectLoading" @click="createProject" />
        </template>
    </Dialog>
</template>

<style scoped>
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

.project-dialog-loading {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    z-index: 5;
}
</style>
