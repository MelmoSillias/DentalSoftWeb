<script setup>
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { computed, ref } from 'vue';
import { defaultSoinList, normalizeSoinList } from '@/services/consultations';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ date: null, services: [] })
    },
    saving: {
        type: Boolean,
        default: false
    },
    soins: {
        type: Array,
        default: () => defaultSoinList
    }
});
 
const emit = defineEmits(['update:modelValue', 'save', 'cloture', 'open-ordonnance', 'print-ordonnance']);

const devis = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const soinsSuggestions = ref([]);
const soinsList = computed(() => normalizeSoinList(props.soins));

const searchSoins = (event) => {
    const query = String(event?.query || '').toLowerCase();
    const list = soinsList.value;
    soinsSuggestions.value = query ? list.filter((item) => item.toLowerCase().includes(query)) : list;
};

const dateModel = computed({
    get: () => {
        const value = devis.value?.date;
        if (!value) return null;
        if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;
        if (typeof value === 'string') {
            const isoMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (isoMatch) {
                const year = Number(isoMatch[1]);
                const month = Number(isoMatch[2]) - 1;
                const day = Number(isoMatch[3]);
                const parsed = new Date(year, month, day);
                return Number.isNaN(parsed.getTime()) ? null : parsed;
            }
            const parsed = new Date(value);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        }
        return null;
    },
    set: (value) => {
        if (!value) {
            updateField('date', null);
            return;
        }
        const parsed = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(parsed.getTime())) {
            updateField('date', null);
            return;
        }
        const year = parsed.getFullYear();
        const month = String(parsed.getMonth() + 1).padStart(2, '0');
        const day = String(parsed.getDate()).padStart(2, '0');
        updateField('date', `${year}-${month}-${day}`);
    }
});

const updateField = (key, value) => {
    devis.value = { ...devis.value, [key]: value };
};

const updateService = (idx, patch) => {
    const list = (devis.value.services || []).map((s, i) => (i === idx ? { ...s, ...patch } : s));
    devis.value = { ...devis.value, services: list };
};

const addService = () => {
    const list = devis.value.services || [];
    devis.value = { ...devis.value, services: [...list, { designation: '', qte: 1, montant: 0 }] };
};

const removeService = (idx) => {
    devis.value = { ...devis.value, services: (devis.value.services || []).filter((_, i) => i !== idx) };
};

const total = computed(() =>
    (devis.value.services || []).reduce(
        (sum, s) => sum + (Number(s.qte) || 0) * (Number(s.montant) || 0),
        0
    )
);
    
const totalQuantity = computed(() => {
    return (props.modelValue.services || []).reduce((sum, service) => sum + (service.qte || 0), 0);
});

function formatCurrency(value) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'XOF',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
}

function subtotal(service) {
    return (service.qte || 0) * (service.montant || 0);
}
</script>

<!-- DevisForm.vue -->
<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-file-pdf text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Devis & Facturation</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        Estimation des coûts et services proposés
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button 
                    label="Imprimer" 
                    icon="pi pi-print" 
                    outlined
                    class="rounded-xl px-4 py-2.5 border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                    @click="emit('print')" 
                />
                <Button 
                    label="Sauvegarder" 
                    icon="pi pi-save" 
                    :loading="saving"
                    class="rounded-xl px-5 py-2.5 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
                    @click="emit('save')" 
                />
            </div>
        </div>

        <!-- Content -->
        <div class="space-y-6">
            <!-- Date & Total -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                        <i class="pi pi-calendar text-surface-400"></i>
                        Date du devis
                    </label>
                    <DatePicker 
                        v-model="dateModel" 
                        dateFormat="dd/mm/yy" 
                        showIcon
                        inputClass="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 p-3 focus:ring-2 focus:ring-primary-500/20 transition-all"
                    />
                </div>
                <div class="rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 p-5 border border-emerald-200/50 dark:border-emerald-800/50">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <div class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Montant total</div>
                            <div class="text-3xl font-bold text-emerald-900 dark:text-emerald-100">
                                {{ formatCurrency(total) }}
                            </div>
                        </div>
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-emerald-500/10">
                            <i class="pi pi-wallet text-2xl text-emerald-500"></i>
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-emerald-600 dark:text-emerald-400">
                        {{ devis.services?.length || 0 }} service(s) | {{ totalQuantity }} unité(s)
                    </div>
                </div>
            </div>

            <!-- Services Header -->
            <div class="flex items-center justify-between pt-4 border-t border-surface-100 dark:border-surface-700">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-500/10">
                        <i class="pi pi-shopping-cart text-primary-500"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Services proposés</h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400">Détail des prestations et tarifs</p>
                    </div>
                </div>
                <Button 
                    icon="pi pi-plus" 
                    label="Ajouter un service" 
                    size="small"
                    class="rounded-xl px-4 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white shadow-sm hover:shadow-md transition-all"
                    @click="addService" 
                />
            </div>

            <!-- Services List -->
            <div class="space-y-4">
                <div v-if="!(devis.services && devis.services.length)" class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 mb-3">
                        <i class="pi pi-inbox text-2xl text-surface-400"></i>
                    </div>
                    <p class="text-surface-600 dark:text-surface-400">Aucun service ajouté. Commencez par ajouter votre premier service.</p>
                </div>
                
                <div v-for="(service, idx) in devis.services" :key="idx" 
                     class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5 shadow-sm hover:shadow-md transition-all">
                    <!-- Service Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center justify-center w-6 h-6 rounded-md bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-300 text-sm font-bold">
                                {{ idx + 1 }}
                            </span>
                            <span class="font-medium text-surface-900 dark:text-surface-100">Service {{ idx + 1 }}</span>
                        </div>
                        <Button 
                            icon="pi pi-trash" 
                            severity="danger" 
                            text 
                            rounded
                            v-tooltip="'Supprimer ce service'"
                            class="hover:bg-red-50 dark:hover:bg-red-900/20"
                            @click="removeService(idx)" 
                        />
                    </div>

                    <!-- Service Content -->
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div class="lg:col-span-2 space-y-2 flex flex-col">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Désignation</label>
                            <AutoComplete 
                                :modelValue="service.designation"
                                :suggestions="soinsSuggestions"
                                dropdown
                                class="w-full"
                                inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5"
                                placeholder="Description du service"
                                @complete="searchSoins"
                                @update:modelValue="(v) => updateService(idx, { designation: v || '' })" 
                            />
                        </div>
                        <div class="space-y-2 flex flex-col">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Quantité</label>
                            <InputNumber 
                                :value="service.qte" 
                                :min="1" 
                                mode="decimal" 
                                :useGrouping="false"
                                inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5"
                                @update:modelValue="(v) => updateService(idx, { qte: v ?? 1 })" 
                            />
                        </div>
                        <div class="space-y-2 flex flex-col">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Prix unitaire</label>
                            <InputNumber 
                                :value="service.montant" 
                                mode="decimal" 
                                :minFractionDigits="0" 
                                :maxFractionDigits="2"
                                inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5"
                                @update:modelValue="(v) => updateService(idx, { montant: v ?? 0 })" 
                            />
                        </div>
                    </div>

                    <!-- Service Subtotal -->
                    <div class="mt-4 pt-3 border-t border-surface-200 dark:border-surface-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-calculator text-surface-400"></i>
                                <span class="text-sm text-surface-600 dark:text-surface-400">Sous-total</span>
                            </div>
                            <div class="text-lg font-bold text-primary-600 dark:text-primary-400">
                                {{ formatCurrency(subtotal(service)) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div v-if="devis.services?.length" class="rounded-xl bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-800 dark:to-surface-900 border border-surface-200 dark:border-surface-700 p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <div class="text-sm text-surface-600 dark:text-surface-400">Services</div>
                        <div class="text-lg font-semibold text-surface-900 dark:text-surface-100">{{ devis.services.length }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="text-sm text-surface-600 dark:text-surface-400">Quantité totale</div>
                        <div class="text-lg font-semibold text-surface-900 dark:text-surface-100">{{ totalQuantity }}</div>
                    </div>
                    <div class="space-y-1">
                        <div class="text-sm text-surface-600 dark:text-surface-400">Montant total</div>
                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ formatCurrency(total) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
 