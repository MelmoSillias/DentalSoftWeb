<script setup>
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    details: {
        type: Object,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    },
    tourTarget: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['update:visible']);

const actes = computed(() => props.details?.actes ?? []);

const acteTotal = (acte) => (Number(acte?.prix) || 0) * (Number(acte?.quantite) || 0);

const totalActes = computed(() => actes.value.reduce((sum, acte) => sum + acteTotal(acte), 0));

const formatDateTime = (value) => {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
};

const handleHide = () => emit('update:visible', false);
</script>

<template>
    <Dialog :visible="visible" modal header="Détails de la consultation" :style="{ width: '50rem', maxWidth: '98vw' }"
        @update:visible="handleHide">
        <div :data-tour="props.tourTarget || null">
            <div v-if="loading" class="p-6 text-center text-gray-600">Chargement des détails...</div>
            <div v-else-if="details" class="flex flex-col gap-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="flex flex-col gap-1">
                        <div><span class="text-gray-500">N° Consultation :</span> <span class="font-semibold">{{ details.id
                                }}</span></div>
                        <div><span class="text-gray-500">Date :</span> <span class="font-semibold">{{
                                formatDateTime(details.date) }}</span>
                        </div>
                        <div><span class="text-gray-500">Patient :</span> <span class="font-semibold">{{ details.patient
                                }}</span></div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2"><span class="text-gray-500">Médecin :</span>
                            <Tag v-if="details.medecin" :value="details.medecin" />
                            <span v-else class="font-semibold">—</span>
                        </div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">Infirmier :</span>
                            <span class="font-semibold">{{ details.infirmier || '—' }}</span>
                        </div>
                        <div class="flex items-center gap-2"><span class="text-gray-500">Salle :</span>
                            <span class="font-semibold">{{ details.salle || '—' }}</span>
                        </div>
                    </div>
                </div>

                <div>
                    <h4 class="text-md font-semibold text-gray-700 mb-2 dark:text-gray-300">Note de séance</h4>
                    <div class="border rounded-lg p-3 bg-gray-50 dark:bg-gray-800 min-h-[60px]">{{ details.noteSeance || 'Aucune note' }}
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <h4 class="text-md font-semibold text-gray-700 dark:text-gray-300">Soins réalisés</h4>
                    <DataTable :value="actes" size="small" class="shadow-sm" :stripedRows="true">
                        <Column field="dent" header="Dent" style="width: 8rem"></Column>
                        <Column field="type" header="Type d'acte" style="width: 12rem"></Column>
                        <Column field="description" header="Description"></Column>
                        <Column field="quantite" header="Qté" bodyClass="text-right" headerClass="text-right"
                            style="width: 6rem">
                            <template #body="{ data }">{{ data.quantite || 0 }}</template>
                        </Column>
                        <Column field="prix" header="Prix" bodyClass="text-right" headerClass="text-right"
                            style="width: 8rem">
                            <template #body="{ data }">{{ (Number(data.prix) || 0).toFixed(2) }}</template>
                        </Column>
                        <Column header="Total" bodyClass="text-right" headerClass="text-right" style="width: 8rem">
                            <template #body="{ data }">{{ acteTotal(data).toFixed(2) }}</template>
                        </Column>
                    </DataTable>
                    <div class="flex justify-end text-right text-sm font-semibold text-gray-700">
                        Total : {{ totalActes.toFixed(2) }} F CFA
                    </div>
                </div>
            </div>
            <div v-else class="p-4 text-center text-gray-600">Aucune donnée disponible.</div>
        </div>
    </Dialog>
</template>
