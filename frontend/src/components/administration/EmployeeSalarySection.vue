<script setup>
import { computed, watch } from 'vue';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';

const form = defineModel('form', { type: Object, required: true });

const props = defineProps({
    employeeType: {
        type: String,
        default: 'Medecin'
    },
    compact: {
        type: Boolean,
        default: false
    }
});

const frequenceOptions = [
    { label: 'Mensuel', value: 'mensuel' },
    { label: 'Journalier', value: 'journalier' }
];

const baseTypeSalaireOptions = [
    { label: 'Non défini', value: 'non_defini' },
    { label: 'Fixe', value: 'fixe' },
    { label: 'Pourcentage', value: 'pourcentage' }
];

const basePrimeOptions = [
    { label: 'Aucune', value: 'aucune' },
    { label: 'Montant fixe', value: 'fixe' },
    { label: '% sur actes posés', value: 'actes' }
];

const isMedecin = computed(() => props.employeeType === 'Medecin');

const typeSalaireOptions = computed(() => {
    if (isMedecin.value) return baseTypeSalaireOptions;
    return baseTypeSalaireOptions.filter((option) => option.value !== 'pourcentage');
});

const typePrimeOptions = computed(() => {
    if (isMedecin.value) return basePrimeOptions;
    return basePrimeOptions.filter((option) => option.value !== 'actes');
});

const isSalaireDisabled = computed(() => form.value.typeSalaire === 'non_defini');
const isPrimeDisabled = computed(() => form.value.typePrime === 'aucune');
const salaryMax = computed(() => (form.value.typeSalaire === 'pourcentage' ? 100 : null));
const primeMax = computed(() => (form.value.typePrime === 'actes' ? 100 : null));
const salarySuffix = computed(() => (form.value.typeSalaire === 'pourcentage' ? '%' : 'F CFA'));
const primeSuffix = computed(() => (form.value.typePrime === 'actes' ? '%' : 'F CFA'));

const salaryValueLabel = computed(() => {
    if (form.value.typeSalaire === 'pourcentage') return 'Pourcentage sur facturation';
    if (form.value.frequencePaiement === 'journalier') return 'Taux journalier';
    return 'Montant mensuel';
});

watch(
    () => props.employeeType,
    (value) => {
        if (value !== 'Medecin' && form.value.typeSalaire === 'pourcentage') {
            form.value.typeSalaire = 'fixe';
        }
        if (value !== 'Medecin' && form.value.typePrime === 'actes') {
            form.value.typePrime = 'aucune';
            form.value.valeurPrime = null;
        }
    }
);

watch(
    () => form.value.typeSalaire,
    (value) => {
        if (value === 'non_defini') {
            form.value.valeurSalaire = null;
            return;
        }

        if (value === 'pourcentage') {
            if (form.value.valeurSalaire === null || form.value.valeurSalaire === '') {
                form.value.valeurSalaire = 35;
            }
            if (form.value.valeurSalaire > 100) {
                form.value.valeurSalaire = 100;
            }
            return;
        }

        if (form.value.valeurSalaire === null || form.value.valeurSalaire === '') {
            form.value.valeurSalaire = 100000;
        }
    }
);

watch(
    () => form.value.typePrime,
    (value) => {
        if (value === 'aucune') {
            form.value.valeurPrime = null;
            return;
        }

        if (form.value.valeurPrime === null || form.value.valeurPrime === '') {
            form.value.valeurPrime = value === 'actes' ? 10 : 25000;
        }
    }
);
</script>

<template>
    <div :class="compact ? 'space-y-4' : 'space-y-5'">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label class="text-sm font-medium">Fréquence de paiement</label>
                <Select
                    v-model="form.frequencePaiement"
                    :options="frequenceOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                />
                <p class="text-xs text-surface-500">
                    {{ form.frequencePaiement === 'journalier'
                        ? 'Paiement par jour travaillé, au choix de l\'administrateur.'
                        : 'Paiement une fois par mois sur la période sélectionnée.' }}
                </p>
            </div>
            <div class="space-y-1">
                <label class="text-sm font-medium">Type de salaire</label>
                <Select
                    v-model="form.typeSalaire"
                    :options="typeSalaireOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                />
            </div>
            <div class="space-y-1">
                <label class="text-sm font-medium">{{ salaryValueLabel }}</label>
                <InputNumber
                    v-model="form.valeurSalaire"
                    class="w-full"
                    :min="0"
                    :max="salaryMax"
                    :step="0.01"
                    :suffix="` ${salarySuffix}`"
                    :disabled="isSalaireDisabled"
                />
            </div>
        </div>

        <div class="rounded-xl border border-surface-200 dark:border-surface-700 p-4 bg-surface-50/60 dark:bg-surface-900/30">
            <h4 class="text-sm font-semibold mb-3 flex items-center gap-2">
                <i class="pi pi-star text-amber-500"></i>
                Prime
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Type de prime</label>
                    <Select
                        v-model="form.typePrime"
                        :options="typePrimeOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Valeur de la prime</label>
                    <InputNumber
                        v-model="form.valeurPrime"
                        class="w-full"
                        :min="0"
                        :max="primeMax"
                        :step="0.01"
                        :suffix="` ${primeSuffix}`"
                        :disabled="isPrimeDisabled"
                    />
                </div>
            </div>
            <p v-if="form.typePrime === 'actes'" class="text-xs text-surface-500 mt-2">
                Calculée en pourcentage sur le montant des actes posés (médecins uniquement).
            </p>
            <p v-else-if="form.typePrime === 'fixe'" class="text-xs text-surface-500 mt-2">
                Montant par défaut, modifiable lors de chaque paiement.
            </p>
        </div>
    </div>
</template>
