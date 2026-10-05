<script setup>
import DevisDialog from '@/components/patients/DevisDialog.vue';
import { saveDevis } from '@/services/ficheMedicale';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';
import { devisLineTotal, formatDevisDate, listFicheDevis, toApiDevis } from '@/utils/patientDevis';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' },
    fiches: { type: Array, default: () => [] }
});

const emit = defineEmits(['refresh']);

const auth = useAuthStore();
const confirm = useConfirm();
const toast = useToast();
const token = () => auth.token || localStorage.getItem('token');

const dialogVisible = ref(false);
const editingDevis = ref(null);
const deletingId = ref(null);

const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const rows = computed(() =>
    (props.fiches || [])
        .flatMap((fiche) =>
            listFicheDevis(fiche)
                .filter((entry) => entry?.id)
                .map((entry) => ({
                    ...entry,
                    ficheId: fiche.id,
                    montant: entry.montant ?? devisLineTotal(entry)
                }))
        )
        .sort((left, right) => String(right.date || '').localeCompare(String(left.date || '')))
);

const openCreate = () => {
    editingDevis.value = null;
    dialogVisible.value = true;
};

const openEdit = (row) => {
    editingDevis.value = row;
    dialogVisible.value = true;
};

const askDelete = (event, row) => {
        confirm.require({
        group: 'patient-devis-delete',
        target: event.currentTarget,
        message: 'Supprimer ce devis ?',
        icon: 'pi pi-exclamation-triangle',
        rejectLabel: 'Annuler',
        acceptLabel: 'Supprimer',
        acceptClass: 'p-button-danger',
        accept: () => deleteDevis(row)
    });
};

const deleteDevis = async (row) => {
    if (!row?.id || !row?.ficheId) return;
    const fiche = (props.fiches || []).find((item) => Number(item?.id) === Number(row.ficheId));
    const remaining = listFicheDevis(fiche).filter((entry) => Number(entry?.id) !== Number(row.id));
    deletingId.value = row.id;
    try {
        await saveDevis(
            row.ficheId,
            {
                devisList: remaining.map((entry, index) => toApiDevis(entry, entry.type ?? index))
            },
            token()
        );
        toast.add({ severity: 'success', summary: 'Devis', detail: 'Devis supprimé', life: 2500 });
        emit('refresh');
    } catch (error) {
        logAppError('Suppression devis', error);
        toast.add({
            severity: 'error',
            summary: 'Devis',
            detail: error?.response?.data?.error || 'Suppression impossible',
            life: 3500
        });
    } finally {
        deletingId.value = null;
    }
};

const onSaved = () => {
    emit('refresh');
};
</script>

<template>
    <section class="space-y-3">
        <ConfirmPopup group="patient-devis-delete" />

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="m-0 text-sm text-surface-500 dark:text-surface-400">Devis rattachés à la dernière fiche du patient lors de la création.</p>
            <Button label="Ajouter" icon="pi pi-plus" size="small" outlined @click="openCreate" />
        </div>

        <div v-if="rows.length" class="page-table-scroll">
            <DataTable :value="rows" dataKey="id" paginator :rows="8" :rowsPerPageOptions="[5, 8, 15]" responsiveLayout="scroll" stripedRows size="small" class="text-sm">
                <Column header="Date" sortable style="min-width: 7rem">
                    <template #body="{ data }">
                        {{ formatDevisDate(data.date) }}
                    </template>
                </Column>
                <Column header="Description" style="min-width: 12rem">
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.description || 'Devis' }}</span>
                    </template>
                </Column>
                <Column header="Montant" style="min-width: 8rem">
                    <template #body="{ data }">
                        {{ formatMoney(data.montant) }}
                    </template>
                </Column>
                <Column header="" style="width: 7rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-1">
                            <Button icon="pi pi-pencil" text rounded size="small" v-tooltip.top="'Modifier'" @click="openEdit(data)" />
                            <Button
                                icon="pi pi-trash"
                                text
                                rounded
                                severity="danger"
                                size="small"
                                v-tooltip.top="'Supprimer'"
                                :loading="deletingId === data.id"
                                @click="askDelete($event, data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
        <div v-else class="dossier-state dossier-state--dashed py-8" style="box-shadow: none">
            <div class="dossier-state__icon">
                <i class="pi pi-file-edit"></i>
            </div>
            <h4 class="dossier-state__title">Aucun devis</h4>
            <p class="dossier-state__text">Aucun devis n’est encore enregistré pour ce patient.</p>
        </div>

        <DevisDialog
            v-model:visible="dialogVisible"
            :patient-id="patientId"
            :patient-name="patientName"
            :devis="editingDevis"
            :fiches="fiches"
            @saved="onSaved"
        />
    </section>
</template>
