<script setup>
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { computed, ref } from 'vue';
import { defaultSoinList, findSoinMontant, normalizeSoinList, soinLabelList } from '@/services/consultations';

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
    },
    readonly: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'save', 'cloture', 'open-ordonnance', 'print-ordonnance', 'print-devis']);

const devis = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const createEmptyDevis = () => ({
    id: null,
    date: null,
    description: '',
    services: []
});

const normalizeService = (service = {}) => ({
    designation: service?.designation || '',
    qte: Number(service?.qte) || 1,
    montant: Number(service?.montant) || 0
});

const parseDevisType = (value) => {
    if (value === null || value === undefined || value === '') return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
};

const normalizeDevisEntry = (entry = {}) => ({
    id: Number(entry?.id) || null,
    type: parseDevisType(entry?.type),
    date: entry?.date ?? null,
    description: entry?.description || '',
    services: Array.isArray(entry?.services) ? entry.services.map((service) => normalizeService(service)) : Array.isArray(entry?.contenus) ? entry.contenus.map((service) => normalizeService(service)) : []
});

const getDevisState = (value) => {
    const source = value || {};

    // Sécurité au cas où un tableau brut est reçu
    if (Array.isArray(source)) {
        return {
            list: source.length ? source.map(normalizeDevisEntry) : [createEmptyDevis()],
            activeIndex: 0
        };
    }

    const fallbackEntry = normalizeDevisEntry({
        id: source?.id,
        type: source?.type,
        date: source?.date ?? null,
        description: source?.description || '',
        services: source?.services || source?.contenus || []
    });

    const parsedList = Array.isArray(source?.devisList) ? source.devisList.map((entry) => normalizeDevisEntry(entry)) : source?.id || source?.services?.length || source?.contenus?.length ? [fallbackEntry] : [createEmptyDevis()];

    const parsedIndex = Number(source?.activeDevisIndex);
    const activeIndex = Number.isInteger(parsedIndex) ? Math.min(Math.max(parsedIndex, 0), parsedList.length - 1) : 0;

    return { list: parsedList, activeIndex };
};

const updateDevisState = (updater) => {
    const { list: currentList, activeIndex: currentIndex } = getDevisState(devis.value);
    const list = currentList.map((entry) => ({ ...entry, services: (entry.services || []).map((service) => ({ ...service })) }));
    const context = {
        list,
        activeIndex: currentIndex
    };

    updater(context);

    if (!context.list.length) {
        context.list.push(createEmptyDevis());
        context.activeIndex = 0;
    }

    const clampedIndex = Math.min(Math.max(Number(context.activeIndex) || 0, 0), context.list.length - 1);
    const activeEntry = normalizeDevisEntry(context.list[clampedIndex]);
    const normalizedList = context.list.map((entry) => normalizeDevisEntry(entry));

    devis.value = {
        ...(devis.value && !Array.isArray(devis.value) ? devis.value : {}), // Préserve la structure
        id: activeEntry.id,
        type: activeEntry.type,
        date: activeEntry.date,
        description: activeEntry.description,
        services: activeEntry.services,
        contenus: activeEntry.services, // Ajout crucial pour le backend
        devisList: normalizedList,
        activeDevisIndex: clampedIndex
    };
};

const devisTabs = computed(() => getDevisState(devis.value).list);

const activeDevisIndex = computed({
    get: () => getDevisState(devis.value).activeIndex,
    set: (value) => {
        updateDevisState((state) => {
            state.activeIndex = value;
        });
    }
});

const activeDevis = computed(() => {
    const { list, activeIndex } = getDevisState(devis.value);
    return list[activeIndex] || createEmptyDevis();
});

const soinsSuggestions = ref([]);
const soinsList = computed(() => normalizeSoinList(props.soins));

const searchSoins = (event) => {
    const query = String(event?.query || '').toLowerCase();
    const list = soinLabelList(soinsList.value);
    soinsSuggestions.value = query ? list.filter((item) => item.toLowerCase().includes(query)) : list;
};

const onSoinItemSelect = (idx, event) => {
    const designation = String(event?.value ?? '').trim();
    const montant = findSoinMontant(soinsList.value, designation);
    if (montant === null) {
        updateService(idx, { designation });
        return;
    }
    updateService(idx, { designation, montant });
};

const dateModel = computed({
    get: () => {
        const value = activeDevis.value?.date;
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
    updateDevisState((state) => {
        const target = state.list[state.activeIndex] || createEmptyDevis();
        state.list[state.activeIndex] = {
            ...target,
            [key]: value
        };
    });
};

const updateService = (idx, patch) => {
    updateDevisState((state) => {
        const target = state.list[state.activeIndex] || createEmptyDevis();
        const services = Array.isArray(target.services) ? target.services : [];
        const list = services.map((service, index) => (index === idx ? { ...service, ...patch } : service));
        state.list[state.activeIndex] = {
            ...target,
            services: list
        };
    });
};

const addService = () => {
    updateDevisState((state) => {
        const target = state.list[state.activeIndex] || createEmptyDevis();
        const services = Array.isArray(target.services) ? target.services : [];
        state.list[state.activeIndex] = {
            ...target,
            services: [...services, { designation: '', qte: 1, montant: 0 }]
        };
    });
};

const removeService = (idx) => {
    updateDevisState((state) => {
        const target = state.list[state.activeIndex] || createEmptyDevis();
        const services = Array.isArray(target.services) ? target.services : [];
        state.list[state.activeIndex] = {
            ...target,
            services: services.filter((_, index) => index !== idx)
        };
    });
};

const addDevisTab = () => {
    updateDevisState((state) => {
        const usedTypes = new Set(state.list.map((entry) => parseDevisType(entry?.type)).filter((type) => type !== null));
        let nextType = state.list.length;
        while (usedTypes.has(nextType)) {
            nextType += 1;
        }

        state.list.push({
            ...createEmptyDevis(),
            type: nextType,
            description: `Devis ${state.list.length + 1}`
        });
        state.activeIndex = state.list.length - 1;
    });
};

const removeDevisTab = (idx) => {
    updateDevisState((state) => {
        if (state.list.length <= 1) {
            state.list[0] = createEmptyDevis();
            state.activeIndex = 0;
            return;
        }

        state.list = state.list.filter((_, index) => index !== idx);
        if (state.activeIndex > idx) {
            state.activeIndex -= 1;
        } else if (state.activeIndex >= state.list.length) {
            state.activeIndex = state.list.length - 1;
        }
    });
};

const devisTabLabel = (entry, idx) => {
    const description = String(entry?.description || '').trim();
    return description || `Devis ${idx + 1}`;
};

const emitPrintDevisAtIndex = (idx) => {
    const { list } = getDevisState(devis.value);
    const entry = normalizeDevisEntry(list[idx] || createEmptyDevis());

    emit('print-devis', {
        ...entry,
        index: idx
    });
};

const emitPrintActiveDevis = () => {
    emitPrintDevisAtIndex(activeDevisIndex.value);
};

const total = computed(() => (activeDevis.value.services || []).reduce((sum, s) => sum + (Number(s.qte) || 0) * (Number(s.montant) || 0), 0));

const totalQuantity = computed(() => {
    return (activeDevis.value.services || []).reduce((sum, service) => sum + (service.qte || 0), 0);
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
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Estimation des coûts et services proposés</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="!readonly"
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
            <!-- Devis Tabs -->
            <div class="rounded-xl border border-surface-200/70 dark:border-surface-700/70 p-3 bg-surface-50/60 dark:bg-surface-800/40">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="(entry, idx) in devisTabs"
                        :key="`devis-tab-${idx}`"
                        type="button"
                        @click="activeDevisIndex = idx"
                        :class="[
                            'inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm transition-all',
                            activeDevisIndex === idx
                                ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-900/30 dark:text-primary-200'
                                : 'border-surface-200 bg-surface-0 text-surface-600 hover:border-surface-300 dark:border-surface-700 dark:bg-surface-900/40 dark:text-surface-300'
                        ]"
                    >
                        <span class="truncate max-w-[12rem]">{{ devisTabLabel(entry, idx) }}</span>
                        <i class="pi pi-print text-xs cursor-pointer" v-tooltip="'Imprimer ce devis'" @click.stop="emitPrintDevisAtIndex(idx)"></i>
                        <i v-if="!readonly && devisTabs.length > 1" class="pi pi-times text-xs cursor-pointer" @click.stop="removeDevisTab(idx)"></i>
                    </button>
                    <Button v-if="!readonly" icon="pi pi-plus" label="Nouveau devis" size="small" outlined class="rounded-lg" @click="addDevisTab" />
                </div>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Description du devis</label>
                <InputText :modelValue="activeDevis.description" placeholder="Ex: Devis implanto-prothétique" class="w-full" :disabled="readonly" @update:modelValue="(value) => updateField('description', value || '')" />
            </div>

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
                        :disabled="readonly"
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
                    <div class="mt-3 text-sm text-emerald-600 dark:text-emerald-400">{{ activeDevis.services?.length || 0 }} service(s) | {{ totalQuantity }} unité(s)</div>
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
                    v-if="!readonly"
                    icon="pi pi-plus"
                    label="Ajouter un service"
                    size="small"
                    class="rounded-xl px-4 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white shadow-sm hover:shadow-md transition-all"
                    @click="addService"
                />
            </div>

            <!-- Services List -->
            <div class="space-y-4 grid grid-cols-2 gap-4 items-stretch">
                <div v-if="!(activeDevis.services && activeDevis.services.length)" class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 mb-3">
                        <i class="pi pi-inbox text-2xl text-surface-400"></i>
                    </div>
                    <p class="text-surface-600 dark:text-surface-400">Aucun service ajouté. Commencez par ajouter votre premier service.</p>
                </div>

                <div
                    v-for="(service, idx) in activeDevis.services"
                    :key="idx"
                    class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5 shadow-sm hover:shadow-md transition-all"
                    :class="readonly ? 'pointer-events-none opacity-90' : ''"
                >
                    <!-- Service Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <Tag severity="primary" class="flex items-center justify-center w-6 h-6 rounded-md bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-300 text-sm font-bold">
                                {{ idx + 1 }}
                            </Tag>
                            <span class="font-medium text-surface-900 dark:text-surface-100">Service {{ idx + 1 }}</span>
                        </div>
                        <Button v-if="!readonly" icon="pi pi-trash" severity="danger" text rounded v-tooltip="'Supprimer ce service'" class="hover:bg-red-50 dark:hover:bg-red-900/20" @click="removeService(idx)" />
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
                                @item-select="(event) => onSoinItemSelect(idx, event)"
                                @update:modelValue="(v) => updateService(idx, { designation: v || '' })"
                            />
                        </div>
                        <div class="space-y-2 flex flex-col">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Quantité</label>
                            <InputNumber
                                :modelValue="service.qte"
                                :min="1"
                                mode="decimal"
                                :useGrouping="false"
                                inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5"
                                @update:modelValue="(v) => updateService(idx, { qte: Number(v) || 1 })"
                            />
                        </div>
                        <div class="space-y-2 flex flex-col">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Prix unitaire</label>
                            <InputNumber
                                :modelValue="service.montant"
                                mode="decimal"
                                :minFractionDigits="0"
                                :maxFractionDigits="2"
                                inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5"
                                @update:modelValue="(v) => updateService(idx, { montant: Number(v) || 0 })"
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
            <div v-if="activeDevis.services?.length" class="rounded-xl bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-800 dark:to-surface-900 border border-surface-200 dark:border-surface-700 p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1">
                        <div class="text-sm text-surface-600 dark:text-surface-400">Services</div>
                        <div class="text-lg font-semibold text-surface-900 dark:text-surface-100">{{ activeDevis.services.length }}</div>
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
