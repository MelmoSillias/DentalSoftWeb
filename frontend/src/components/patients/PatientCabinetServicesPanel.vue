<script setup>
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import CabinetServiceDialog from '@/components/patients/CabinetServiceDialog.vue';
import { cancelCabinetService } from '@/services/cabinetServices';
import { useAuthStore } from '@/stores/auth';
import { useToast } from 'primevue/usetoast';
import { logAppError } from '@/utils/appLogger';

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' },
    services: { type: Array, default: () => [] },
    ficheId: { type: [Number, String], default: null }
});

const emit = defineEmits(['refresh']);

const auth = useAuthStore();
const toast = useToast();
const dialogVisible = ref(false);
const cancellingId = ref(null);

const rows = computed(() => {
    const list = Array.isArray(props.services) ? props.services : [];
    if (props.ficheId == null || props.ficheId === '') {
        return list;
    }
    return list.filter((item) => Number(item?.ficheId) === Number(props.ficheId));
});

const statusLabel = (service) => {
    if (service?.statut === 'annule') {
        return { label: 'Annulé', severity: 'secondary' };
    }
    if (service?.facture?.isRegle) {
        return { label: 'Payé', severity: 'success' };
    }
    const reste = Number(service?.facture?.reste) || 0;
    const montant = Number(service?.facture?.montant ?? service?.montant) || 0;
    if (reste > 0 && reste < montant) {
        return { label: 'Partiellement payé', severity: 'warning' };
    }
    return { label: 'Impayé', severity: 'danger' };
};

const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const cancelService = async (service) => {
    if (!service?.id) {
        return;
    }
    try {
        cancellingId.value = service.id;
        await cancelCabinetService(service.id, auth.token);
        toast.add({ severity: 'success', summary: 'Service cabinet', detail: 'Service annulé', life: 2500 });
        emit('refresh');
    } catch (error) {
        logAppError('Services cabinet', error);
        toast.add({
            severity: 'error',
            summary: 'Service cabinet',
            detail: error?.response?.data?.error || 'Annulation impossible',
            life: 3500
        });
    } finally {
        cancellingId.value = null;
    }
};
</script>

<template>
    <section class="rounded-2xl border border-surface-200/70 bg-surface-0 p-4 dark:border-surface-700/70 dark:bg-surface-900/40">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Services cabinet</h3>
                <p class="text-sm text-surface-500">Radiographie et autres prestations facturées hors consultation.</p>
            </div>
            <Button label="Enregistrer un service cabinet" icon="pi pi-plus" size="small" @click="dialogVisible = true" />
        </div>

        <p v-if="!rows.length" class="text-sm text-surface-500">Aucun service cabinet enregistré.</p>

        <ul v-else class="flex flex-col gap-2">
            <li v-for="service in rows" :key="service.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-surface-200/80 px-3 py-2 dark:border-surface-700/80">
                <div class="min-w-0">
                    <p class="font-medium text-surface-900 dark:text-surface-0">{{ service.designation }}</p>
                    <p class="text-xs text-surface-500">{{ service.date || '—' }} · {{ formatMoney(service.montant) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Tag :value="statusLabel(service).label" :severity="statusLabel(service).severity" />
                    <Button
                        v-if="service.statut !== 'annule' && !service.facture?.hasPayments"
                        icon="pi pi-times"
                        text
                        rounded
                        severity="danger"
                        v-tooltip.top="'Annuler'"
                        :loading="cancellingId === service.id"
                        @click="cancelService(service)"
                    />
                </div>
            </li>
        </ul>

        <CabinetServiceDialog v-model:visible="dialogVisible" :patient-id="patientId" :patient-name="patientName" @created="emit('refresh')" />
    </section>
</template>
