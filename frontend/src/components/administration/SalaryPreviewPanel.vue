<script setup>
import { computed } from 'vue';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import { formatEmployeeTypeLabel } from '@/utils/employeeTypeUtils';
import { formatSalaryTypeLabel } from '@/utils/payrollUtils';

const props = defineProps({
    context: {
        type: Object,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    },
    primeAmount: {
        type: Number,
        default: null
    },
    editablePrime: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:primeAmount']);

const formatCurrency = (value) => {
    if (value === null || value === undefined) return '-';
    return `${Number(value).toLocaleString('fr-FR')} F CFA`;
};

const frequenceLabel = computed(() => {
    const freq = props.context?.employee?.frequencePaiement || props.context?.frequencePaiement;
    return freq === 'journalier' ? 'Journalier' : 'Mensuel';
});

const primeTypeLabel = computed(() => {
    const map = { aucune: 'Aucune', fixe: 'Montant fixe', actes: '% actes posés' };
    const type = props.context?.employee?.typePrime || props.context?.typePrime || 'aucune';
    return map[type] || type;
});

const breakdown = computed(() => props.context?.breakdown || {});
const canPay = computed(() => props.context?.canPay !== false);
const blockReason = computed(() => props.context?.blockReason || 'Cette période est déjà entièrement réglée.');

const localPrimeAmount = computed({
    get: () => props.primeAmount ?? props.context?.primeAmount ?? 0,
    set: (val) => emit('update:primeAmount', val)
});

const adjustedTotal = computed(() => {
    const base = Number(breakdown.value.baseSalary || props.context?.baseSalaryAmount || 0);
    const prime = Number(localPrimeAmount.value || 0);
    const alreadyPaid = Number(breakdown.value.alreadyPaid || 0);
    const total = base + prime;
    return {
        total,
        remaining: Math.max(0, total - alreadyPaid)
    };
});
</script>

<template>
    <div class="rounded-xl border border-surface-200 dark:border-surface-700 overflow-hidden">
        <div class="px-4 py-3 bg-gradient-to-r from-primary-500/10 to-transparent border-b border-surface-200 dark:border-surface-700 flex items-center justify-between">
            <h4 class="font-semibold flex items-center gap-2">
                <i class="pi pi-wallet text-primary-500"></i>
                Aperçu du salaire
            </h4>
            <span v-if="loading" class="text-sm text-surface-500">Calcul en cours...</span>
        </div>

        <div v-if="context" class="p-4 space-y-4">
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div><span class="text-surface-500">Employé</span><p class="font-medium">{{ context.employee?.fullname || '-' }}</p></div>
                <div><span class="text-surface-500">Rôle</span><p class="font-medium">{{ formatEmployeeTypeLabel(context.employee?.type) || '-' }}</p></div>
                <div><span class="text-surface-500">Fréquence</span><p class="font-medium">{{ frequenceLabel }}</p></div>
                <div><span class="text-surface-500">Dernier paiement</span><p class="font-medium">{{ context.employee?.dateDernierPaiement || '-' }}</p></div>
            </div>

            <div class="rounded-lg bg-surface-50 dark:bg-surface-900/40 p-3 space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-surface-500">Salaire de base</p>
                <div class="flex justify-between text-sm">
                    <span>Type</span>
                    <span class="font-medium uppercase tracking-wide">{{ formatSalaryTypeLabel(context.employee?.typeSalaire || context.salaryType) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span>Base chiffrée</span>
                    <span class="font-medium">{{ formatCurrency(context.baseAmount) }}</span>
                </div>
                <div class="flex justify-between text-sm font-semibold text-primary-600 dark:text-primary-300">
                    <span>Montant salaire</span>
                    <span>{{ formatCurrency(breakdown.baseSalary ?? context.baseSalaryAmount) }}</span>
                </div>
            </div>

            <div v-if="context.employee?.typePrime !== 'aucune' && context.typePrime !== 'aucune'" class="rounded-lg bg-amber-50/70 dark:bg-amber-900/20 p-3 space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Prime — {{ primeTypeLabel }}</p>
                <div class="flex justify-between text-sm">
                    <span>Valeur configurée</span>
                    <span class="font-medium">
                        {{ context.employee?.typePrime === 'actes' || context.typePrime === 'actes'
                            ? `${context.employee?.valeurPrime ?? context.valeurPrime ?? 0}%`
                            : formatCurrency(context.employee?.valeurPrime ?? context.valeurPrime) }}
                    </span>
                </div>
                <div v-if="context.employee?.typePrime === 'actes' || context.typePrime === 'actes'" class="flex justify-between text-sm">
                    <span>Base actes</span>
                    <span class="font-medium">{{ formatCurrency(context.primeBaseAmount) }}</span>
                </div>
                <div v-if="editablePrime" class="space-y-1">
                    <label class="text-sm font-medium">Montant prime (modifiable)</label>
                    <InputNumber
                        v-model="localPrimeAmount"
                        class="w-full"
                        :min="0"
                        mode="decimal"
                        :minFractionDigits="0"
                        :maxFractionDigits="2"
                        suffix=" F CFA"
                    />
                </div>
                <div class="flex justify-between text-sm font-semibold text-amber-700 dark:text-amber-300">
                    <span>Montant prime</span>
                    <span>{{ formatCurrency(editablePrime ? localPrimeAmount : (breakdown.prime ?? context.primeAmount)) }}</span>
                </div>
            </div>

            <div class="rounded-lg border border-primary-200 dark:border-primary-800 p-3 space-y-2 bg-primary-50/40 dark:bg-primary-900/20">
                <div class="flex justify-between text-sm">
                    <span>Total calculé</span>
                    <span class="font-semibold">{{ formatCurrency(editablePrime ? adjustedTotal.total : (breakdown.total ?? context.calculatedAmount)) }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span>Déjà versé</span>
                    <span>{{ formatCurrency(breakdown.alreadyPaid) }}</span>
                </div>
                <div class="flex justify-between text-base font-bold text-primary-600 dark:text-primary-300">
                    <span>Reste à payer</span>
                    <span>{{ formatCurrency(editablePrime ? adjustedTotal.remaining : breakdown.remaining) }}</span>
                </div>
            </div>

            <Message v-if="!canPay" severity="warn" :closable="false">{{ blockReason }}</Message>
        </div>

        <p v-else class="p-4 text-sm text-surface-500">
            Sélectionnez un employé et une période pour calculer automatiquement le salaire.
        </p>
    </div>
</template>
