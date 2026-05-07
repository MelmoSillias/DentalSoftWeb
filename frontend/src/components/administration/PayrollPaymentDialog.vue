<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

const props = defineProps({
    visible: Boolean,
    employees: {
        type: Array,
        default: () => []
    },
    context: {
        type: Object,
        default: null
    },
    contextLoading: {
        type: Boolean,
        default: false
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'request-context', 'submit']);

const localVisible = ref(false);
const monthModel = ref(new Date());
const form = ref({
    employeeId: null,
    paidAmount: null,
    paidAt: new Date(),
    note: ''
});

watch(() => props.visible, (val) => {
    localVisible.value = val;
    if (val) {
        monthModel.value = new Date();
        form.value = {
            employeeId: null,
            paidAmount: null,
            paidAt: new Date(),
            note: ''
        };
    }
}, { immediate: true });

watch(localVisible, (val) => emit('update:visible', val));

const selectedMonth = computed(() => (monthModel.value ? monthModel.value.getMonth() + 1 : null));
const selectedYear = computed(() => (monthModel.value ? monthModel.value.getFullYear() : null));

watch(
    () => [form.value.employeeId, selectedMonth.value, selectedYear.value],
    ([employeeId, month, year]) => {
        if (!employeeId || !month || !year) return;
        emit('request-context', { employeeId, month, year });
    }
);

watch(
    () => props.context,
    (val) => {
        if (!val) return;
        const calculated = Number(val?.calculatedAmount || 0);
        form.value.paidAmount = calculated > 0 ? calculated : null;
    }
);

const close = () => {
    localVisible.value = false;
};

const submit = () => {
    if (!form.value.employeeId || !selectedMonth.value || !selectedYear.value || !form.value.paidAmount) return;

    const paidAt = form.value.paidAt instanceof Date
        ? form.value.paidAt.toISOString().slice(0, 10)
        : '';

    emit('submit', {
        employeeId: form.value.employeeId,
        month: selectedMonth.value,
        year: selectedYear.value,
        paidAmount: Number(form.value.paidAmount),
        paidAt,
        note: form.value.note || ''
    });
};

const employeeOptions = computed(() =>
    (props.employees || []).map((employee) => ({
        label: `${employee.prenom || ''} ${employee.nom || ''}`.trim() || `Employe #${employee.id}`,
        value: employee.id
    }))
);
</script>

<template>
    <Dialog v-model:visible="localVisible" modal header="Paiement de salaire" :style="{ width: '44rem' }" @hide="close">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label class="text-sm font-medium">Employe</label>
                <Select
                    v-model="form.employeeId"
                    :options="employeeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                    placeholder="Selectionnez un employe"
                />
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium">Mois de paie</label>
                <DatePicker v-model="monthModel" view="month" dateFormat="mm/yy" showIcon class="w-full" />
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium">Montant verse</label>
                <InputNumber v-model="form.paidAmount" class="w-full" :min="0" mode="decimal" :minFractionDigits="0" :maxFractionDigits="2" suffix=" F CFA" />
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium">Date de paiement</label>
                <DatePicker v-model="form.paidAt" dateFormat="yy-mm-dd" showIcon class="w-full" />
            </div>
        </div>

        <div class="space-y-1 mt-4">
            <label class="text-sm font-medium">Note (optionnel)</label>
            <Textarea v-model="form.note" rows="3" class="w-full" autoResize />
        </div>

        <div class="mt-4 rounded-xl border border-surface-200 dark:border-surface-700 p-4 bg-surface-50/70 dark:bg-surface-800/40">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold">Apercu du salaire</h4>
                <span v-if="contextLoading" class="text-sm text-surface-500">Calcul en cours...</span>
            </div>
            <div v-if="context" class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <div><strong>Nom:</strong> {{ context.employee?.fullname || '-' }}</div>
                <div><strong>Fonction:</strong> {{ context.employee?.fonction || '-' }}</div>
                <div><strong>Type salaire:</strong> {{ context.employee?.typeSalaire || '-' }}</div>
                <div><strong>Valeur salaire:</strong> {{ context.employee?.valeurSalaire ?? '-' }}</div>
                <div><strong>Dernier paiement:</strong> {{ context.employee?.dateDernierPaiement || '-' }}</div>
                <div><strong>Apport mensuel:</strong> {{ Number(context.baseAmount || 0).toLocaleString('fr-FR') }} F CFA</div>
                <div class="md:col-span-2 text-base font-semibold text-primary-600 dark:text-primary-300">
                    Montant calcule: {{ Number(context.calculatedAmount || 0).toLocaleString('fr-FR') }} F CFA
                </div>
            </div>
            <p v-else class="text-sm text-surface-500">Selectionnez un employe et un mois pour calculer automatiquement.</p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Annuler" text severity="secondary" @click="close" />
                <Button label="Enregistrer" icon="pi pi-check" :loading="loading" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>
