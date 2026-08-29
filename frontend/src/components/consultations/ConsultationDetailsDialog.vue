<script setup>
import { formatActeCurrency, normalizeDentList } from '@/services/consultations';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
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

const getActeDents = (acte) => normalizeDentList(acte?.dent ?? acte?.dents ?? '');

const formatDateTime = (value) => {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
};

const handleHide = () => emit('update:visible', false);
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :style="{ width: '52rem', maxWidth: '98vw' }"
        class="consultation-details-dialog"
        @update:visible="handleHide"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-9 h-9 rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                    <i class="pi pi-file-edit text-lg"></i>
                </span>
                <div>
                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-0 leading-tight">Détails de la consultation</h2>
                    <p v-if="details?.patient" class="text-sm text-surface-500 dark:text-surface-400 mt-0.5">{{ details.patient }}</p>
                </div>
            </div>
        </template>

        <div :data-tour="props.tourTarget || null">
            <div v-if="loading" class="flex flex-col items-center justify-center gap-3 py-10 text-surface-500 dark:text-surface-400">
                <ProgressSpinner style="width: 2.5rem; height: 2.5rem" strokeWidth="4" />
                <span class="text-sm">Chargement des détails...</span>
            </div>

            <div v-else-if="details" class="flex flex-col gap-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/60 p-4">
                        <div class="flex items-center gap-2 mb-3 text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                            <i class="pi pi-calendar"></i>
                            <span>Informations générales</span>
                        </div>
                        <dl class="flex flex-col gap-2.5 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-surface-500 dark:text-surface-400">N° consultation</dt>
                                <dd class="font-semibold text-surface-900 dark:text-surface-100">#{{ details.id }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-surface-500 dark:text-surface-400">Date</dt>
                                <dd class="font-medium text-surface-900 dark:text-surface-100 text-right">{{ formatDateTime(details.date) || '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-surface-500 dark:text-surface-400">Patient</dt>
                                <dd class="font-medium text-surface-900 dark:text-surface-100 text-right">{{ details.patient || '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/60 p-4">
                        <div class="flex items-center gap-2 mb-3 text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                            <i class="pi pi-users"></i>
                            <span>Équipe & lieu</span>
                        </div>
                        <dl class="flex flex-col gap-2.5 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-surface-500 dark:text-surface-400">Médecin</dt>
                                <dd>
                                    <Tag v-if="details.medecin" :value="details.medecin" severity="info" rounded />
                                    <span v-else class="font-medium text-surface-900 dark:text-surface-100">—</span>
                                </dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-surface-500 dark:text-surface-400">Aide soignant(e)</dt>
                                <dd class="font-medium text-surface-900 dark:text-surface-100 text-right">{{ details.infirmier || '—' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-3">
                                <dt class="text-surface-500 dark:text-surface-400">Salle</dt>
                                <dd class="font-medium text-surface-900 dark:text-surface-100 text-right">{{ details.salle || '—' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <i class="pi pi-comment text-primary-500"></i>
                        <h4 class="text-sm font-semibold text-surface-800 dark:text-surface-200">Note de séance</h4>
                    </div>
                    <div
                        class="rounded-xl border border-surface-200 dark:border-surface-700 p-4 min-h-[72px] text-sm leading-relaxed whitespace-pre-wrap"
                        :class="details.noteSeance
                            ? 'bg-surface-0 dark:bg-surface-900 text-surface-800 dark:text-surface-200'
                            : 'bg-surface-50 dark:bg-surface-800/60 text-surface-400 dark:text-surface-500 italic'"
                    >
                        {{ details.noteSeance || 'Aucune note enregistrée.' }}
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-list-check text-primary-500"></i>
                            <h4 class="text-sm font-semibold text-surface-800 dark:text-surface-200">Soins réalisés</h4>
                        </div>
                        <span v-if="actes.length" class="text-xs font-medium text-surface-500 dark:text-surface-400">
                            {{ actes.length }} acte{{ actes.length > 1 ? 's' : '' }}
                        </span>
                    </div>

                    <DataTable
                        :value="actes"
                        size="small"
                        class="consultation-details-table rounded-xl overflow-hidden border border-surface-200 dark:border-surface-700"
                        :stripedRows="true"
                        :pt="{
                            table: { class: 'text-sm' },
                            headerRow: { class: 'bg-surface-100 dark:bg-surface-800' },
                            column: {
                                headerCell: { class: 'text-xs font-semibold uppercase tracking-wide text-surface-600 dark:text-surface-300 py-3' },
                                bodyCell: { class: 'py-2.5 align-middle' }
                            }
                        }"
                    >
                        <Column field="dent" header="Dent" style="width: 9rem">
                            <template #body="{ data }">
                                <div v-if="getActeDents(data).length" class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="dent in getActeDents(data)"
                                        :key="dent"
                                        class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium border border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300"
                                        :title="`Dent ${dent}`"
                                    >
                                        <span>{{ dent }}</span>
                                    </span>
                                </div>
                                <span v-else class="text-surface-400 dark:text-surface-500">—</span>
                            </template>
                        </Column>
                        <Column field="type" header="Type d'acte" style="width: 11rem">
                            <template #body="{ data }">
                                <Tag v-if="data.type" :value="data.type" severity="secondary" class="text-xs" />
                                <span v-else class="text-surface-400 dark:text-surface-500">—</span>
                            </template>
                        </Column>
                        <Column field="description" header="Description">
                            <template #body="{ data }">
                                <span class="text-surface-700 dark:text-surface-300">{{ data.description || '—' }}</span>
                            </template>
                        </Column>
                        <Column field="quantite" header="Qté" bodyClass="text-right" headerClass="text-right" style="width: 4.5rem">
                            <template #body="{ data }">{{ data.quantite || 0 }}</template>
                        </Column>
                        <Column field="prix" header="Prix" bodyClass="text-right" headerClass="text-right" style="width: 7rem">
                            <template #body="{ data }">{{ formatActeCurrency(Number(data.prix) || 0) }}</template>
                        </Column>
                        <Column header="Total" bodyClass="text-right" headerClass="text-right" style="width: 7rem">
                            <template #body="{ data }">
                                <span class="font-semibold text-primary-600 dark:text-primary-400">{{ formatActeCurrency(acteTotal(data)) }}</span>
                            </template>
                        </Column>
                    </DataTable>

                    <div class="flex justify-end">
                        <div class="inline-flex items-center gap-2 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950/30 px-4 py-2.5 text-sm">
                            <span class="text-surface-600 dark:text-surface-300">Total des soins</span>
                            <span class="font-bold text-primary-700 dark:text-primary-300">{{ formatActeCurrency(totalActes) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center gap-2 py-10 text-surface-500 dark:text-surface-400">
                <i class="pi pi-inbox text-2xl"></i>
                <span class="text-sm">Aucune donnée disponible.</span>
            </div>
        </div>
    </Dialog>
</template>
