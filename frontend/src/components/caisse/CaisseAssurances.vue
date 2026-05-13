<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const props = defineProps({
    claims: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    statusFilter: { type: String, default: 'all' },
    actionLoadingId: { type: Number, default: null },
    assuranceOptions: { type: Array, default: () => [] },
    insuranceRange: { type: Array, default: () => [] },
    insurancePatientFilter: { type: String, default: '' },
    insuranceAssuranceFilter: { type: String, default: 'all' }
});

const emit = defineEmits([
    'update:statusFilter',
    'update:insuranceRange',
    'update:insurancePatientFilter',
    'update:insuranceAssuranceFilter',
    'refresh',
    'validate-claim',
    'reject-claim',
    'recover-claim',
    'collect-patient-share'
]);

const search = ref('');

const statusOptions = [
    { label: 'Tous les statuts', value: 'all' },
    { label: 'En attente', value: 'pending' },
    { label: 'Validées', value: 'validated' },
    { label: 'Rejetées', value: 'rejected' },
    { label: 'Recouvrées', value: 'recouvre' }
];

const normalizeText = (value) => String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

const statusModel = computed({
    get: () => props.statusFilter,
    set: (value) => emit('update:statusFilter', value || 'all')
});

const insuranceStart = computed({
    get: () => props.insuranceRange?.[0] || null,
    set: (value) => emit('update:insuranceRange', [value || null, props.insuranceRange?.[1] || null])
});

const insuranceEnd = computed({
    get: () => props.insuranceRange?.[1] || null,
    set: (value) => emit('update:insuranceRange', [props.insuranceRange?.[0] || null, value || null])
});

const insurancePatientModel = computed({
    get: () => props.insurancePatientFilter || '',
    set: (value) => emit('update:insurancePatientFilter', value || '')
});

const insuranceAssuranceModel = computed({
    get: () => props.insuranceAssuranceFilter || 'all',
    set: (value) => emit('update:insuranceAssuranceFilter', value || 'all')
});

const filteredClaims = computed(() => {
    const query = normalizeText(search.value.trim());
    return (Array.isArray(props.claims) ? props.claims : []).filter((claim) => {
        if (!query) return true;

        return [
            claim?.patient,
            claim?.telephone,
            claim?.assurance?.nom,
            claim?.assurance?.code,
            claim?.insuranceStatus,
            claim?.dateFacture
        ].some((part) => normalizeText(part).includes(query));
    });
});

const statusTag = (status) => {
    if (status === 'validated') return { label: 'Validée', severity: 'success' };
    if (status === 'rejected') return { label: 'Rejetée', severity: 'danger' };
    if (status === 'recouvre') return { label: 'Recouvrée', severity: 'info' };
    return { label: 'En attente', severity: 'warning' };
};

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;
const canAct = (claimId) => props.actionLoadingId === null || props.actionLoadingId !== Number(claimId);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="section-card">
            <div class="section-header">
                <div>
                    <p class="section-eyebrow">Suivi Assurances</p>
                    <p class="section-title">Validation, rejet et recouvrement des créances d'assurance.</p>
                </div>
                <div class="filters">
                    <div class="filter-item">
                        <label>Du</label>
                        <InputText :modelValue="insuranceStart" type="date" @update:modelValue="insuranceStart = $event" />
                    </div>
                    <div class="filter-item">
                        <label>Au</label>
                        <InputText :modelValue="insuranceEnd" type="date" @update:modelValue="insuranceEnd = $event" />
                    </div>
                    <div class="filter-item">
                        <label>Patient</label>
                        <InputText v-model="insurancePatientModel" placeholder="Nom, prénom, téléphone" fluid />
                    </div>
                    <div class="filter-item">
                        <label>Assurance</label>
                        <Select v-model="insuranceAssuranceModel" :options="assuranceOptions || []" optionLabel="label" optionValue="value" />
                    </div>
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="search" placeholder="Patient, assurance, téléphone..." fluid />
                    </div>
                    <div class="filter-item">
                        <label>Statut</label>
                        <Select v-model="statusModel" :options="statusOptions" optionLabel="label" optionValue="value" />
                    </div>
                    <Button icon="pi pi-refresh" label="Rafraîchir" text @click="emit('refresh')" />
                </div>
            </div>
        </div>

        <div class="section-card">
            <div v-if="loading" class="text-sm text-surface-500">Chargement des créances assurances...</div>

            <div v-else-if="!filteredClaims.length" class="text-sm text-surface-500">
                Aucune créance assurance pour le filtre courant.
            </div>

            <div v-else class="grid gap-3">
                <article v-for="claim in filteredClaims" :key="claim.id" class="rounded-xl border border-surface-200 p-4 dark:border-surface-700">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="font-semibold text-surface-900 dark:text-surface-0">{{ claim.patient || 'Patient inconnu' }}</p>
                            <p class="text-xs text-surface-500">{{ claim.telephone || 'Téléphone indisponible' }}</p>
                            <p class="text-xs text-surface-500">
                                Assurance: {{ claim?.assurance?.nom || '—' }}
                                <span v-if="claim?.assurance?.code">({{ claim.assurance.code }})</span>
                            </p>
                            <p class="text-xs text-surface-500">Date: {{ claim.dateFacture || '—' }}</p>
                        </div>
                        <Tag :value="statusTag(claim.insuranceStatus).label" :severity="statusTag(claim.insuranceStatus).severity" />
                    </div>

                    <div class="mt-3 grid gap-2 md:grid-cols-4 text-sm">
                        <p>Total: <strong>{{ formatFcfa(claim.montantTotal) }}</strong></p>
                        <p>Part assurance: <strong>{{ formatFcfa(claim.montantAssurance) }}</strong></p>
                        <p>Part patient: <strong>{{ formatFcfa(claim.montantPatient) }}</strong></p>
                        <p>Taux: <strong>{{ Number(claim.tauxCouverture || 0).toLocaleString('fr-FR') }}%</strong></p>
                        <p>Déjà encaissé patient: <strong>{{ formatFcfa(claim.patientPaidAmount) }}</strong></p>
                        <p>Reste patient: <strong>{{ formatFcfa(claim.restePatient) }}</strong></p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
                            v-if="claim.availableActions?.canValidate"
                            size="small"
                            label="Valider"
                            icon="pi pi-check"
                            severity="success"
                            :disabled="!canAct(claim.id)"
                            @click="emit('validate-claim', claim)"
                        />
                        <Button
                            v-if="claim.availableActions?.canReject"
                            size="small"
                            label="Rejeter"
                            icon="pi pi-times"
                            severity="danger"
                            :disabled="!canAct(claim.id)"
                            @click="emit('reject-claim', claim)"
                        />
                        <Button
                            v-if="claim.availableActions?.canRecover"
                            size="small"
                            label="Recouvrer"
                            icon="pi pi-wallet"
                            severity="info"
                            :disabled="!canAct(claim.id)"
                            @click="emit('recover-claim', claim)"
                        />
                        <Button
                            v-if="claim.availableActions?.canCollectPatient"
                            size="small"
                            label="Encaisser part patient"
                            icon="pi pi-credit-card"
                            severity="secondary"
                            :disabled="!canAct(claim.id)"
                            @click="emit('collect-patient-share', claim)"
                        />
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>
