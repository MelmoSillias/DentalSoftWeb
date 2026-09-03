<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DataView from 'primevue/dataview';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Menu from 'primevue/menu';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import DatePicker from 'primevue/datepicker';
import { useToast } from 'primevue/usetoast';
import { resolveAssuranceLogoUrl } from '@/utils/assuranceUtils';

const CLAIM_DND_MIME = 'application/x-assurance-claim';

const props = defineProps({
    assurance: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
    unassignedClaims: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    actionLoadingId: { type: Number, default: null }
});

const emit = defineEmits([
    'back',
    'refresh',
    'create-lot',
    'update-lot',
    'view-lot',
    'send-lot',
    'reopen-lot',
    'confirm-lot',
    'unconfirm-lot',
    'refund-lot',
    'view-claim',
    'pay-claim',
    'modify-claim',
    'assign-claim',
    'change-claim-lot',
    'print-lot'
]);

const toast = useToast();

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const lotStatutTag = (statut) => {
    const map = {
        ouvert: { label: 'Ouvert', severity: 'warning' },
        envoye: { label: 'Envoyé', severity: 'info' },
        confirme: { label: 'Confirmé', severity: 'primary' },
        partiellement_rembourse: { label: 'Partiellement remboursé', severity: 'help' },
        rembourse: { label: 'Remboursé', severity: 'success' },
        recouvre: { label: 'Remboursé', severity: 'success' }
    };
    return map[statut] || { label: statut || '—', severity: 'secondary' };
};

const canAct = (id) => props.actionLoadingId === null || props.actionLoadingId !== Number(id);

const viewMode = ref('board');
const viewModeOptions = [
    { label: 'Liste', value: 'list', icon: 'pi pi-list' },
    { label: 'Affectation', value: 'board', icon: 'pi pi-objects-column' }
];

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const assignDialogVisible = ref(false);
const lotForm = ref({ description: '', dateDebut: null, dateFin: null });
const editingLot = ref(null);
const assignClaim = ref(null);
const selectedAssignLotId = ref(null);
const claimMenu = ref();
const claimMenuItems = ref([]);
const activeClaim = ref(null);

const draggingClaimId = ref(null);
const dropTargetLotId = ref(null);

const openLotsOptions = computed(() => props.lots.filter((l) => l.statut === 'ouvert'));

const isLotDroppable = (lot) => lot?.statut === 'ouvert';

const openCreateDialog = () => {
    lotForm.value = { description: '', dateDebut: new Date(), dateFin: new Date() };
    createDialogVisible.value = true;
};

const submitCreate = () => {
    emit('create-lot', {
        description: lotForm.value.description,
        dateDebut: formatDate(lotForm.value.dateDebut),
        dateFin: formatDate(lotForm.value.dateFin)
    });
    createDialogVisible.value = false;
};

const openEditDialog = (lot) => {
    editingLot.value = lot;
    lotForm.value = {
        description: lot.description || '',
        dateDebut: lot.dateDebut ? new Date(lot.dateDebut) : null,
        dateFin: lot.dateFin ? new Date(lot.dateFin) : null
    };
    editDialogVisible.value = true;
};

const submitEdit = () => {
    if (!editingLot.value) return;
    emit('update-lot', {
        lot: editingLot.value,
        payload: {
            description: lotForm.value.description,
            dateDebut: formatDate(lotForm.value.dateDebut),
            dateFin: formatDate(lotForm.value.dateFin)
        }
    });
    editDialogVisible.value = false;
};

const formatDate = (value) => {
    if (!value) return null;
    const d = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return d.toISOString().slice(0, 10);
};

const openClaimMenu = (event, claim) => {
    activeClaim.value = claim;
    claimMenuItems.value = [
        {
            label: 'Gestion du lot',
            items: [
                {
                    label: 'Affecter à un lot',
                    icon: 'pi pi-folder-plus',
                    disabled: !openLotsOptions.value.length,
                    command: () => openAssignDialog(claim, false)
                },
                {
                    label: 'Changer de lot',
                    icon: 'pi pi-sync',
                    disabled: true,
                    command: () => openAssignDialog(claim, true)
                }
            ]
        },
        { separator: true },
        {
            label: 'Gestion de la facture',
            items: [
                {
                    label: 'Payer',
                    icon: 'pi pi-wallet',
                    disabled: !(claim?.canPay || Number(claim?.restePatient) > 0),
                    command: () => emit('pay-claim', claim)
                },
                {
                    label: 'Voir',
                    icon: 'pi pi-eye',
                    command: () => emit('view-claim', claim)
                },
                {
                    label: 'Modifier',
                    icon: 'pi pi-pencil',
                    disabled: claim?.canModify === false,
                    command: () => emit('modify-claim', claim)
                }
            ]
        }
    ];
    claimMenu.value?.toggle(event);
};

const openAssignDialog = (claim, isChange) => {
    assignClaim.value = { ...claim, isChange };
    selectedAssignLotId.value = openLotsOptions.value[0]?.id ?? null;
    assignDialogVisible.value = true;
};

const submitAssign = () => {
    if (!assignClaim.value || !selectedAssignLotId.value) return;
    if (assignClaim.value.isChange) {
        emit('change-claim-lot', { claim: assignClaim.value, lotId: selectedAssignLotId.value });
    } else {
        emit('assign-claim', { claim: assignClaim.value, lotId: selectedAssignLotId.value });
    }
    assignDialogVisible.value = false;
};

const onClaimDragStart = (claim, event) => {
    if (!claim?.id) return;
    draggingClaimId.value = claim.id;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData(CLAIM_DND_MIME, JSON.stringify(claim));
    event.dataTransfer.setData('text/plain', String(claim.id));
};

const onClaimDragEnd = () => {
    draggingClaimId.value = null;
    dropTargetLotId.value = null;
};

const parseDraggedClaim = (event) => {
    const raw = event.dataTransfer?.getData(CLAIM_DND_MIME) || event.dataTransfer?.getData('text/plain');
    if (!raw) return null;
    try {
        if (raw.startsWith('{')) return JSON.parse(raw);
        const id = Number(raw);
        return props.unassignedClaims.find((c) => Number(c.id) === id) || { id };
    } catch {
        return null;
    }
};

const onLotDragOver = (lot, event) => {
    if (!draggingClaimId.value && !event.dataTransfer?.types?.length) return;
    if (!isLotDroppable(lot)) {
        event.dataTransfer.dropEffect = 'none';
        return;
    }
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dropTargetLotId.value = lot.id;
};

const onLotDragEnter = (lot, event) => {
    if (!isLotDroppable(lot)) return;
    event.preventDefault();
    dropTargetLotId.value = lot.id;
};

const onLotDragLeave = (lot, event) => {
    const related = event.relatedTarget;
    if (related && event.currentTarget?.contains?.(related)) return;
    if (dropTargetLotId.value === lot.id) {
        dropTargetLotId.value = null;
    }
};

const onLotDrop = (lot, event) => {
    event.preventDefault();
    dropTargetLotId.value = null;
    const claim = parseDraggedClaim(event);
    draggingClaimId.value = null;
    if (!claim?.id) return;
    if (!isLotDroppable(lot)) {
        toast.add({
            severity: 'warn',
            summary: 'Affectation',
            detail: 'Seul un lot ouvert accepte de nouvelles factures.',
            life: 3000
        });
        return;
    }
    emit('assign-claim', { claim, lotId: lot.id });
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-4">
                <Button icon="pi pi-arrow-left" text rounded @click="emit('back')" />
                <div class="assurance-logo-sm">
                    <img v-if="resolveAssuranceLogoUrl(assurance?.logoPath)" :src="resolveAssuranceLogoUrl(assurance?.logoPath)" :alt="assurance?.nom" class="assurance-logo-sm-img" />
                    <i v-else class="pi pi-shield text-primary text-2xl"></i>
                </div>
                <div>
                    <h2 class="page-title text-xl font-bold">{{ assurance?.nom || 'Assurance' }}</h2>
                    <p class="muted-text text-sm">{{ assurance?.code }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <SelectButton v-model="viewMode" :options="viewModeOptions" option-label="label" option-value="value" :allow-empty="false" class="view-mode-toggle">
                    <template #option="{ option }">
                        <i :class="option.icon" class="mr-1" />
                        <span>{{ option.label }}</span>
                    </template>
                </SelectButton>
                <Button icon="pi pi-refresh" label="Rafraîchir" outlined rounded @click="emit('refresh')" />
                <Button icon="pi pi-plus" label="Créer un lot" @click="openCreateDialog" />
            </div>
        </div>

        <!-- Mode Liste -->
        <template v-if="viewMode === 'list'">
            <div class="section-panel">
                <div class="mb-4">
                    <h3 class="page-title text-lg font-bold">Gestion des lots</h3>
                    <p class="muted-text text-sm">Glissez une facture non affectée sur un lot ouvert pour l’y rattacher.</p>
                </div>

                <DataTable :value="lots" :loading="loading" striped-rows paginator :rows="10" size="small" row-hover>
                    <Column header="Lot">
                        <template #body="{ data }">
                            <div
                                class="lot-drop-zone"
                                :class="{
                                    'lot-drop-zone--accept': isLotDroppable(data),
                                    'lot-drop-target--active': dropTargetLotId === data.id,
                                    'lot-drop-zone--closed': !isLotDroppable(data)
                                }"
                                @dragover="onLotDragOver(data, $event)"
                                @dragenter="onLotDragEnter(data, $event)"
                                @dragleave="onLotDragLeave(data, $event)"
                                @drop="onLotDrop(data, $event)"
                            >
                                <p class="page-title font-medium">{{ data.description || `Lot #${data.id}` }}</p>
                                <p class="muted-text text-xs">#{{ data.id }}</p>
                                <p v-if="isLotDroppable(data)" class="drop-hint muted-text text-xs">Déposer une facture ici</p>
                            </div>
                        </template>
                    </Column>
                    <Column header="Période">
                        <template #body="{ data }"> {{ data.dateDebut || '—' }} → {{ data.dateFin || '—' }} </template>
                    </Column>
                    <Column header="Statut">
                        <template #body="{ data }">
                            <Tag :value="lotStatutTag(data.statut).label" :severity="lotStatutTag(data.statut).severity" />
                        </template>
                    </Column>
                    <Column field="nbFactures" header="Factures" />
                    <Column header="Montant assurance">
                        <template #body="{ data }">{{ formatFcfa(data.montantTotal) }}</template>
                    </Column>
                    <Column header="Reste à rembourser">
                        <template #body="{ data }">{{ formatFcfa(data.resteARembourser) }}</template>
                    </Column>
                    <Column header="Actions" style="min-width: 20rem">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <Button icon="pi pi-eye" label="Voir" size="small" text @click="emit('view-lot', data)" />
                                <Button icon="pi pi-print" size="small" text rounded v-tooltip.top="'Imprimer'" :disabled="!canAct(data.id)" @click="emit('print-lot', data)" />
                                <Button v-if="data.availableActions?.canEdit" icon="pi pi-pencil" size="small" text rounded :disabled="!canAct(data.id)" @click="openEditDialog(data)" />
                                <Button v-if="data.availableActions?.canSend" icon="pi pi-send" label="Envoyer" size="small" :disabled="!canAct(data.id)" @click="emit('send-lot', data)" />
                                <Button v-if="data.availableActions?.canReopen" icon="pi pi-replay" label="Rouvrir" size="small" outlined :disabled="!canAct(data.id)" @click="emit('reopen-lot', data)" />
                                <Button v-if="data.availableActions?.canConfirm" icon="pi pi-check" label="Confirmer" size="small" :disabled="!canAct(data.id)" @click="emit('confirm-lot', data)" />
                                <Button v-if="data.availableActions?.canUnconfirm" icon="pi pi-undo" label="Retour" size="small" outlined :disabled="!canAct(data.id)" @click="emit('unconfirm-lot', data)" />
                                <Button v-if="data.availableActions?.canRefund" icon="pi pi-wallet" label="Rembourser" size="small" severity="success" :disabled="!canAct(data.id)" @click="emit('refund-lot', data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>

            <div class="section-panel">
                <div class="mb-4">
                    <h3 class="page-title text-lg font-bold">Factures non affectées</h3>
                    <p class="muted-text text-sm">FacturesAssurance clôturées non rattachées à un lot. Glissez-déposez vers un lot ouvert.</p>
                </div>

                <DataTable :value="unassignedClaims" :loading="loading" striped-rows paginator :rows="8" size="small" row-hover class="text-sm">
                    <Column header="" style="width: 2.75rem">
                        <template #body="{ data }">
                            <button
                                type="button"
                                class="claim-drag-handle"
                                :class="{ 'claim-drag-handle--dragging': draggingClaimId === data.id }"
                                title="Glisser vers un lot"
                                aria-label="Glisser vers un lot"
                                draggable="true"
                                @dragstart="onClaimDragStart(data, $event)"
                                @dragend="onClaimDragEnd"
                                @click.prevent
                            >
                                <i class="pi pi-bars"></i>
                            </button>
                        </template>
                    </Column>
                    <Column header="Date">
                        <template #body="{ data }">{{ data?.dateFacture?.slice(0, 10) || '—' }}</template>
                    </Column>
                    <Column field="patient" header="Patient" />
                    <Column header="Total">
                        <template #body="{ data }">{{ formatFcfa(data?.montantTotal) }}</template>
                    </Column>
                    <Column header="Taux">
                        <template #body="{ data }">{{ Number(data?.tauxCouverture || 0) }} %</template>
                    </Column>
                    <Column header="Reste patient">
                        <template #body="{ data }">
                            <span class="font-semibold">{{ formatFcfa(data?.restePatient) }}</span>
                        </template>
                    </Column>
                    <Column header="Actions" style="min-width: 6rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-ellipsis-v" text rounded @click="openClaimMenu($event, data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </template>

        <!-- Mode Affectation (2 colonnes) -->
        <div v-else class="board-layout">
            <div class="section-panel board-col board-col--lots">
                <div class="mb-3">
                    <h3 class="page-title text-lg font-bold">Lots</h3>
                    <p class="muted-text text-sm">Déposez une facture sur un lot ouvert.</p>
                </div>

                <div v-if="loading" class="py-8 text-center muted-text">
                    <i class="pi pi-spin pi-spinner text-xl"></i>
                </div>
                <div v-else-if="!lots.length" class="empty-board muted-text text-sm">Aucun lot pour cette assurance.</div>
                <DataView v-else :value="lots" paginator :rows="6" data-key="id">
                    <template #list="slotProps">
                        <div class="lot-cards">
                            <div
                                v-for="lot in slotProps.items"
                                :key="lot.id"
                                class="lot-card"
                                :class="{
                                    'lot-card--droppable': isLotDroppable(lot),
                                    'lot-drop-target--active': dropTargetLotId === lot.id
                                }"
                                @dragover="onLotDragOver(lot, $event)"
                                @dragenter="onLotDragEnter(lot, $event)"
                                @dragleave="onLotDragLeave(lot, $event)"
                                @drop="onLotDrop(lot, $event)"
                            >
                                <div class="lot-card__header">
                                    <div class="min-w-0">
                                        <p class="lot-card__title">{{ lot.description || `Lot #${lot.id}` }}</p>
                                        <p class="muted-text text-xs">#{{ lot.id }} · {{ lot.dateDebut || '—' }} → {{ lot.dateFin || '—' }}</p>
                                    </div>
                                    <Tag :value="lotStatutTag(lot.statut).label" :severity="lotStatutTag(lot.statut).severity" />
                                </div>

                                <div class="lot-card__meta">
                                    <div>
                                        <span class="muted-text text-xs uppercase">Factures</span>
                                        <strong>{{ lot.nbFactures || 0 }}</strong>
                                    </div>
                                    <div>
                                        <span class="muted-text text-xs uppercase">Montant</span>
                                        <strong>{{ formatFcfa(lot.montantTotal) }}</strong>
                                    </div>
                                    <div>
                                        <span class="muted-text text-xs uppercase">Reste</span>
                                        <strong>{{ formatFcfa(lot.resteARembourser) }}</strong>
                                    </div>
                                </div>

                                <p v-if="isLotDroppable(lot)" class="drop-hint muted-text text-xs">Zone de dépôt</p>

                                <div class="lot-card__actions">
                                    <Button icon="pi pi-eye" label="Voir" size="small" text @click="emit('view-lot', lot)" />
                                    <Button icon="pi pi-print" size="small" text rounded v-tooltip.top="'Imprimer'" :disabled="!canAct(lot.id)" @click="emit('print-lot', lot)" />
                                    <Button v-if="lot.availableActions?.canEdit" icon="pi pi-pencil" size="small" text rounded :disabled="!canAct(lot.id)" @click="openEditDialog(lot)" />
                                    <Button v-if="lot.availableActions?.canSend" icon="pi pi-send" size="small" text :disabled="!canAct(lot.id)" v-tooltip.top="'Envoyer'" @click="emit('send-lot', lot)" />
                                    <Button v-if="lot.availableActions?.canReopen" icon="pi pi-replay" size="small" text :disabled="!canAct(lot.id)" v-tooltip.top="'Rouvrir'" @click="emit('reopen-lot', lot)" />
                                    <Button v-if="lot.availableActions?.canConfirm" icon="pi pi-check" size="small" text :disabled="!canAct(lot.id)" v-tooltip.top="'Confirmer'" @click="emit('confirm-lot', lot)" />
                                    <Button v-if="lot.availableActions?.canUnconfirm" icon="pi pi-undo" size="small" text :disabled="!canAct(lot.id)" v-tooltip.top="'Retour'" @click="emit('unconfirm-lot', lot)" />
                                    <Button v-if="lot.availableActions?.canRefund" icon="pi pi-wallet" size="small" text severity="success" :disabled="!canAct(lot.id)" v-tooltip.top="'Rembourser'" @click="emit('refund-lot', lot)" />
                                </div>
                            </div>
                        </div>
                    </template>
                </DataView>
            </div>

            <div class="section-panel board-col board-col--claims">
                <div class="mb-3">
                    <h3 class="page-title text-lg font-bold">Factures non affectées</h3>
                    <p class="muted-text text-sm">{{ unassignedClaims.length }} facture(s) · glisser vers un lot ouvert</p>
                </div>

                <DataTable :value="unassignedClaims" :loading="loading" striped-rows paginator :rows="10" size="small" row-hover class="text-sm">
                    <Column header="" style="width: 2.75rem">
                        <template #body="{ data }">
                            <button
                                type="button"
                                class="claim-drag-handle"
                                :class="{ 'claim-drag-handle--dragging': draggingClaimId === data.id }"
                                title="Glisser vers un lot"
                                aria-label="Glisser vers un lot"
                                draggable="true"
                                @dragstart="onClaimDragStart(data, $event)"
                                @dragend="onClaimDragEnd"
                                @click.prevent
                            >
                                <i class="pi pi-bars"></i>
                            </button>
                        </template>
                    </Column>
                    <Column header="Date">
                        <template #body="{ data }">{{ data?.dateFacture?.slice(0, 10) || '—' }}</template>
                    </Column>
                    <Column field="patient" header="Patient" />
                    <Column header="Total">
                        <template #body="{ data }">{{ formatFcfa(data?.montantTotal) }}</template>
                    </Column>
                    <Column header="Taux">
                        <template #body="{ data }">{{ Number(data?.tauxCouverture || 0) }} %</template>
                    </Column>
                    <Column header="Reste">
                        <template #body="{ data }">
                            <span class="font-semibold">{{ formatFcfa(data?.restePatient) }}</span>
                        </template>
                    </Column>
                    <Column header="" style="width: 3rem">
                        <template #body="{ data }">
                            <Button icon="pi pi-ellipsis-v" text rounded @click="openClaimMenu($event, data)" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <Menu ref="claimMenu" :model="claimMenuItems" popup />

        <Dialog v-model:visible="createDialogVisible" modal header="Créer un lot" class="w-full max-w-lg">
            <div class="flex flex-col gap-4 py-2">
                <div>
                    <label class="block text-sm mb-1">Nom</label>
                    <InputText v-model="lotForm.description" class="w-full" placeholder="Nom du lot" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm mb-1">Début</label>
                        <DatePicker v-model="lotForm.dateDebut" date-format="yy-mm-dd" class="w-full" show-icon />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Fin</label>
                        <DatePicker v-model="lotForm.dateFin" date-format="yy-mm-dd" class="w-full" show-icon />
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text @click="createDialogVisible = false" />
                <Button label="Créer" icon="pi pi-check" @click="submitCreate" />
            </template>
        </Dialog>

        <Dialog v-model:visible="editDialogVisible" modal header="Modifier le lot" class="w-full max-w-lg">
            <div class="flex flex-col gap-4 py-2">
                <div>
                    <label class="block text-sm mb-1">Nom</label>
                    <InputText v-model="lotForm.description" class="w-full" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm mb-1">Début</label>
                        <DatePicker v-model="lotForm.dateDebut" date-format="yy-mm-dd" class="w-full" show-icon />
                    </div>
                    <div>
                        <label class="block text-sm mb-1">Fin</label>
                        <DatePicker v-model="lotForm.dateFin" date-format="yy-mm-dd" class="w-full" show-icon />
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text @click="editDialogVisible = false" />
                <Button label="Enregistrer" icon="pi pi-check" @click="submitEdit" />
            </template>
        </Dialog>

        <Dialog v-model:visible="assignDialogVisible" modal header="Affecter à un lot" class="w-full max-w-md">
            <div class="flex flex-col gap-3 py-2">
                <p class="muted-text text-sm">{{ assignClaim?.patient }}</p>
                <label class="block text-sm mb-1">Lot ouvert</label>
                <select v-model="selectedAssignLotId" class="lot-select">
                    <option v-for="lot in openLotsOptions" :key="lot.id" :value="lot.id">
                        {{ lot.description || `Lot #${lot.id}` }}
                    </option>
                </select>
            </div>
            <template #footer>
                <Button label="Annuler" text @click="assignDialogVisible = false" />
                <Button label="Affecter" icon="pi pi-check" :disabled="!selectedAssignLotId" @click="submitAssign" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.section-panel {
    padding: 1.25rem;
    border-radius: 1rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
}

.page-title {
    color: var(--text-color);
}

.muted-text {
    color: var(--text-color-secondary);
}

.assurance-logo-sm {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 4.5rem;
    height: 4.5rem;
    border-radius: 1rem;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
    padding: 0.5rem;
}

.assurance-logo-sm-img {
    max-height: 3.25rem;
    max-width: 100%;
    object-fit: contain;
}

.lot-select {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    color: var(--text-color);
}

.board-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
    gap: 1.25rem;
    align-items: start;
}

@media (max-width: 960px) {
    .board-layout {
        grid-template-columns: 1fr;
    }
}

.lot-cards {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.15rem;
}

.lot-card {
    border: 1px solid var(--surface-border);
    border-radius: 0.75rem;
    background: var(--surface-ground);
    padding: 0.9rem 1rem;
    transition:
        border-color 0.15s ease,
        background-color 0.15s ease,
        box-shadow 0.15s ease;
}

.lot-card--droppable {
    border-style: dashed;
    border-color: color-mix(in srgb, var(--primary-color) 35%, var(--surface-border));
}

.lot-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.lot-card__title {
    font-weight: 600;
    color: var(--text-color);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.lot-card__meta {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.lot-card__meta strong {
    display: block;
    font-size: 0.9rem;
    color: var(--text-color);
    font-weight: 600;
}

.lot-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.15rem;
    margin-top: 0.35rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--surface-border);
}

.lot-drop-zone {
    border-radius: 0.5rem;
    padding: 0.35rem 0.45rem;
    margin: -0.35rem -0.45rem;
    transition:
        background-color 0.15s ease,
        box-shadow 0.15s ease;
}

.lot-drop-zone--accept {
    border: 1px dashed transparent;
}

.lot-drop-target--active {
    background: color-mix(in srgb, var(--primary-color) 10%, transparent) !important;
    border-color: var(--primary-color) !important;
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary-color) 40%, transparent);
}

.drop-hint {
    margin: 0.25rem 0 0;
    opacity: 0.75;
}

.claim-drag-handle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border: none;
    border-radius: 0.4rem;
    background: transparent;
    color: var(--text-color-secondary);
    cursor: grab;
    padding: 0;
}

.claim-drag-handle:hover {
    background: var(--surface-hover);
    color: var(--text-color);
}

.claim-drag-handle--dragging,
.claim-drag-handle:active {
    cursor: grabbing;
    opacity: 0.65;
}

.empty-board {
    padding: 1.5rem 0.5rem;
    text-align: center;
}

.app-dark .section-panel {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.app-dark .lot-card {
    background: color-mix(in srgb, var(--surface-card) 88%, #000);
}
</style>
