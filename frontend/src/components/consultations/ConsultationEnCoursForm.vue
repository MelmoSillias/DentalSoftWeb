<script setup>
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button'; 
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import ProgressSpinner from 'primevue/progressspinner';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ type: '', actes: [] })
    },
    formuleDentaire: {
        type: Object,
        default: () => ({})
    },
    medecins: {
        type: Array,
        default: () => []
    },
    medecinsOptions: {
        type: Array,
        default: () => []
    },
    infirmiers: {
        type: Array,
        default: () => []
    },
    infirmiersOptions: {
        type: Array,
        default: () => []
    },
    salles: {
        type: Array,
        default: () => []
    },
    sallesOptions: {
        type: Array,
        default: () => []
    },
    ordonnances: {
        type: Array,
        default: () => []
    },
    saving: {
        type: Boolean,
        default: false
    },
    clotureLoading: {
        type: Boolean,
        default: false
    },
    medecinReadonly: {
        type: Boolean,
        default: false
    },
    loading: {
        type: Boolean,
        default: false
    },
    hideOrdonnances: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'save', 'cloture', 'open-ordonnance', 'print-ordonnance']);

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const medecinsList = computed(() => (props.medecinsOptions.length ? props.medecinsOptions : props.medecins));
const infirmiersList = computed(() => (props.infirmiersOptions.length ? props.infirmiersOptions : props.infirmiers));
const sallesList = computed(() => (props.sallesOptions.length ? props.sallesOptions : props.salles));

const medecinSuggestions = ref([]);

watch(
    medecinsList,
    (list) => {
        medecinSuggestions.value = list || [];
    },
    { immediate: true }
);

const medecinSelection = computed({
    get: () => medecinsList.value.find((m) => m.id === form.value.medecinId) || null,
    set: (val) => updateField('medecinId', val?.id ?? null)
});

const searchMedecin = (event) => {
    const query = (event.query || '').toLowerCase();
    const list = medecinsList.value || [];
    medecinSuggestions.value = query
        ? list.filter((m) => (m.label || '').toLowerCase().includes(query))
        : list;
};

const updateField = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

const addActe = (dent = '') => {
    const actes = form.value.actes || [];
    form.value = {
        ...form.value,
        actes: [...actes, { dent, type: '', description: '', quantite: 1, prix: 0 }]
    };
};

const addActeDialogVisible = ref(false);
const selectedTeeth = ref([]);

const formuleRows = [
    {
        left: [55, 54, 53, 52, 51],
        right: [61, 62, 63, 64, 65]
    },
    {
        left: [18, 17, 16, 15, 14, 13, 12, 11],
        right: [21, 22, 23, 24, 25, 26, 27, 28]
    },
    {
        left: [48, 47, 46, 45, 44, 43, 42, 41],
        right: [31, 32, 33, 34, 35, 36, 37, 38]
    },
    {
        left: [85, 84, 83, 82, 81],
        right: [71, 72, 73, 74, 75]
    }
];

const getToothEntry = (tooth) => props.formuleDentaire?.[tooth] || null;

const toothSummary = (tooth) => {
    const entry = getToothEntry(tooth);
    if (!entry?.etat || entry.etat.length === 0) {
        return '';
    }
    return entry.etat.join('-');
};

const hasToothData = (entry) => {
    if (!entry) {
        return false;
    }
    if (entry.etat && entry.etat.length) {
        return true;
    }
    if (entry.estCausale) {
        return true;
    }
    if (entry.diagnosticSuppose) {
        return true;
    }
    if (entry.examensComplementaires && entry.examensComplementaires.length) {
        return true;
    }
    return Object.values(entry.siCausale || {}).some((value) => value);
};

const toothStateClass = (tooth) => {
    const entry = getToothEntry(tooth);
    if (entry?.estCausale) {
        return 'bg-red-100 text-red-700 border-red-200';
    }
    if (hasToothData(entry)) {
        return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    }
    return 'bg-white text-surface-600 border-surface-200';
};

const isToothSelected = (tooth) => {
    const value = String(tooth);
    return (selectedTeeth.value || []).includes(value);
};

const toggleToothSelection = (tooth) => {
    const value = String(tooth);
    const current = Array.isArray(selectedTeeth.value) ? selectedTeeth.value : [];
    if (current.includes(value)) {
        selectedTeeth.value = current.filter((item) => item !== value);
        return;
    }
    selectedTeeth.value = [...current, value];
};

const openAddActeDialog = () => {
    selectedTeeth.value = [];
    addActeDialogVisible.value = true;
};

const confirmAddActes = () => {
    const dents = Array.isArray(selectedTeeth.value) ? selectedTeeth.value : [];
    const cleanDents = [...new Set(dents.map((dent) => String(dent)).filter((dent) => dent))];
    if (!cleanDents.length) {
        return;
    }
    const actes = form.value.actes || [];
    const newActes = cleanDents.map((dent) => ({
        dent,
        type: '',
        description: '',
        quantite: 1,
        prix: 0
    }));
    form.value = { ...form.value, actes: [...actes, ...newActes] };
    selectedTeeth.value = [];
    addActeDialogVisible.value = false;
};

const updateActe = (idx, patch) => {
    const actes = (form.value.actes || []).map((a, i) => (i === idx ? { ...a, ...patch } : a));
    form.value = { ...form.value, actes };
};

const removeActe = (idx) => {
    form.value = { ...form.value, actes: (form.value.actes || []).filter((_, i) => i !== idx) };
};

const acteTotal = (a) => (Number(a.quantite) || 0) * (Number(a.prix) || 0);

const totalActes = computed(() => (form.value.actes || []).reduce((sum, a) => sum + acteTotal(a), 0));
const acteSubtotals = ref([]);
const totalActesValue = ref(0);

watch(
    () => form.value.actes,
    (actes) => {
        const list = (actes || []).map((acte) => acteTotal(acte));
        acteSubtotals.value = list;
        totalActesValue.value = list.reduce((sum, val) => sum + (Number(val) || 0), 0);
    },
    { deep: true, immediate: true }
);
  
function formatCurrency(value) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'XOF',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value);
}

const soinsList = [
	'Consultation',
	'Détartrage',
	'Extraction',
	'Remplissage',
	'Composite',
	'Amalgame',
	'Traitement de canal',
	'Traumatisme',
	'Couronne',
	'Blanchiment',
	'Radio',
	'Prothèse',
	'Orthodontie',
	'Chirurgie'
];

const consultationTypes = [
    { label: 'Première Consultation', value: 'initiale' },
    { label: 'Contrôle ou prévention', value: 'controle' },
    { label: 'Suivi de traitement', value: 'traitement' },
    { label: 'Urgence Dentaire', value: 'urgence' },
    { label: 'Autre', value: 'autre' }
];

const teethOptions = (() => {
    const options = [];
    const quadrants = [1, 2, 3, 4];
    quadrants.forEach((q) => {
        for (let i = 1; i <= 8; i += 1) {
            const value = `${q}${i}`;
            options.push({ label: value, value });
        }
    });
    const temporaryQuadrants = [5, 6, 7, 8];
    temporaryQuadrants.forEach((q) => {
        for (let i = 1; i <= 5; i += 1) {
            const value = `${q}${i}`;
            options.push({ label: value, value });
        }
    });
    return options;
})();

const isValidTooth = (value) => {
    return (
        value !== null &&
        typeof value !== 'undefined' &&
        !(typeof value === 'object' && 'target' in value)
    )
}

</script>

<!-- ConsultationEnCours.vue -->
<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div v-if="loading" class="flex min-h-[20rem] flex-col items-center justify-center gap-3">
            <ProgressSpinner strokeWidth="4" style="width: 48px; height: 48px" />
            <p class="text-sm text-surface-500 dark:text-surface-400">Chargement des données de consultation...</p>
        </div>
        <template v-else>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-calendar-clock text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Consultation en cours</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                        Informations de la séance actuelle
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button 
                    label="Sauvegarder" 
                    icon="pi pi-save" 
                    :loading="saving"
                    class="rounded-xl px-4 py-2.5 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
                    @click="emit('save')" 
                />
                <Button 
                    label="Clôturer" 
                    icon="pi pi-lock" 
                    severity="danger"
                    :loading="clotureLoading"
                    class="rounded-xl px-4 py-2.5 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-red-500 to-red-600 border-0 text-white"
                    @click="emit('cloture')" 
                />
            </div>
        </div>

        <!-- Content -->
        <div class="space-y-6">
            <!-- Personnel & Salle -->
            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5">
                <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-100 mb-4">Personnel & Localisation</h4>
                <div class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-user-md text-surface-400"></i>
                            Médecin
                        </label>
                        <AutoComplete 
                            v-model="medecinSelection" 
                            :suggestions="medecinSuggestions"
                            :optionLabel="'label'" 
                            placeholder="Rechercher un médecin"
                            :disabled="medecinReadonly"
                            class="w-full"
                            dropdown 
                            forceSelection
                            :completeOnFocus="false" 
                            :delay="0"
                            inputClass="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 p-3"
                            @complete="searchMedecin"
                            @update:modelValue="(v) => updateField('medecinId', v?.id ?? null)" 
                        />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-users text-surface-400"></i>
                            Infirmier(ère)s
                        </label>
                        <MultiSelect 
                            v-model="form.infirmierIds" 
                            :options="infirmiersList" 
                            optionLabel="label"
                            optionValue="id" 
                            placeholder="Sélectionner"
                            display="chip" 
                            :filter="true"
                            class="w-full [&_.p-multiselect]:rounded-xl [&_.p-multiselect]:border-surface-200 [&_.p-multiselect]:dark:border-surface-700 [&_.p-multiselect]:p-3"
                            @update:modelValue="(v) => updateField('infirmierIds', v || [])" 
                        />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-building text-surface-400"></i>
                            Salle
                        </label>
                        <Select 
                            v-model="form.salleId" 
                            :options="sallesList" 
                            optionLabel="label" 
                            optionValue="id"
                            placeholder="Choisir une salle"
                            class="w-full [&_.p-dropdown]:rounded-xl [&_.p-dropdown]:border-surface-200 [&_.p-dropdown]:dark:border-surface-700 [&_.p-dropdown]:p-3"
                            @update:modelValue="(v) => updateField('salleId', v)" 
                        />
                    </div>
                    <div class="flex-1 space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                            <i class="pi pi-tag text-surface-400"></i>
                            Type de consultation
                        </label>
                        <Select
                            v-model="form.type"
                            :options="consultationTypes"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Choisir un type"
                            class="w-full [&_.p-dropdown]:rounded-xl [&_.p-dropdown]:border-surface-200 [&_.p-dropdown]:dark:border-surface-700 [&_.p-dropdown]:p-3"
                            @update:modelValue="(v) => updateField('type', v)"
                        />
                    </div>
                </div>
            </div>

            <!-- Note de séance -->
            <div class="space-y-2">
                <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                    <i class="pi pi-file-edit text-surface-400"></i>
                    Note de séance
                </label>
                <Textarea 
                    v-model="form.noteSeance" 
                    rows="4"
                    placeholder="Notes et observations de la séance..."
                    class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                    @update:modelValue="(v) => updateField('noteSeance', v)" 
                />
            </div>

            <!-- Soins -->
            <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-500/10">
                            <i class="pi pi-heart text-blue-500"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Soins réalisés</h4>
                            <p class="text-sm text-surface-500 dark:text-surface-400">Actes médicaux effectués</p>
                        </div>
                    </div>
                    <Button 
                        icon="pi pi-plus" 
                        label="Ajouter un soin" 
                        size="small"
                        class="rounded-xl px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 border-0 text-white shadow-sm hover:shadow-md transition-all"
                        @click="addActe" 
                    />
                    <Button
                        icon="pi pi-list-check"
                        label="Ajouter plusieurs"
                        size="small"
                        severity="secondary"
                        class="rounded-xl px-4 py-2.5"
                        @click="openAddActeDialog"
                    />
                </div>

                <!-- Soins List -->
                <div class="space-y-4">
                    <div v-if="!(form.actes && form.actes.length)" class="text-center py-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 mb-3">
                            <i class="pi pi-inbox text-2xl text-surface-400"></i>
                        </div>
                        <p class="text-surface-600 dark:text-surface-400">Aucun acte ajouté. Commencez par ajouter votre premier soin.</p>
                    </div>
                    
                    <div v-for="(acte, idx) in form.actes" :key="idx" 
                         class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-4 shadow-sm hover:shadow-md transition-all">
                        <!-- Acte Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <span class="flex items-center justify-center w-6 h-6 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 text-sm font-bold">
                                    {{ idx + 1 }}
                                </span>
                                <span class="font-medium text-surface-900 dark:text-surface-100">Acte {{ idx + 1 }}</span>
                            </div>
                            <Button 
                                icon="pi pi-trash" 
                                severity="danger" 
                                text 
                                rounded
                                v-tooltip="'Supprimer cet acte'"
                                class="hover:bg-red-50 dark:hover:bg-red-900/20"
                                @click="removeActe(idx)" 
                            />
                        </div>

                        <!-- Acte Content -->
                        <div class="grid grid-cols-1 lg:grid-cols-5 gap-3">
                            <div>
                                <FloatLabel variant="in">
                                    <Select
                                        :options="teethOptions"
                                        :modelValue="acte.dent"
                                        optionLabel="label"
                                        optionValue="value" 
                                        :filter="true"
                                        showClear
                                        class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 text-sm"
                                        @update:modelValue="(v) => updateActe(idx, { dent: v })"
                                    >
                                        <template #value="slotProps">
                                            <div v-if="isValidTooth(slotProps.value)">
                                                <span
                                                    class="inline-flex h-2.5 w-2.5 rounded-full border"
                                                    :class="toothStateClass(slotProps.value)"
                                                ></span>
                                                <span class="font-medium">{{ slotProps.value }}</span>
                                                <span class="text-xs text-surface-500">
                                                    {{ toothSummary(slotProps.value) || '---' }}
                                                </span>
                                            </div> 
                                        </template>
                                        <template #option="slotProps">
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="inline-flex h-2.5 w-2.5 rounded-full border"
                                                    :class="toothStateClass(slotProps.option.value)"
                                                ></span>
                                                <span class="font-medium">{{ slotProps.option.label }}</span>
                                                <span class="text-xs text-surface-500">
                                                    {{ toothSummary(slotProps.option.value) || '---' }}
                                                </span>
                                            </div>
                                        </template>
                                    </Select>
                                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Dent</label>
                                </FloatLabel>
                            </div>
                            <div class="w-full">
                                <FloatLabel variant="in">
                                    <Select
                                        :options="soinsList"
                                        :value="acte.type"  
                                        class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800  text-sm"
                                        @update:modelValue="(v) => updateActe(idx, { type: v })" 
                                    />
                                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Type d'acte</label>
                                </FloatLabel>
                            </div>
                            <div class="w-full">
                                <FloatLabel variant="in">
                                    <InputText 
                                        :value="acte.description"  
                                        class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 p-2 text-sm"
                                        @update:modelValue="(v) => updateActe(idx, { description: v })" 
                                    />
                                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Description</label>
                                </FloatLabel>
                            </div>
                            <div class="w-full">
                                <FloatLabel variant="in">
                                    <InputNumber 
                                        :modelValue="acte.quantite" 
                                        :min="1" 
                                        mode="decimal" 
                                        :useGrouping="false"
                                        inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 p-2 text-sm"
                                        @update:modelValue="(v) => updateActe(idx, { quantite: v ?? 1 })" 
                                    />
                                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Qté</label>
                                </FloatLabel>
                            </div>
                            <div>
                                <FloatLabel variant="in">
                                    <InputNumber 
                                        :modelValue="acte.prix" 
                                        mode="decimal" 
                                        :minFractionDigits="0"
                                        :maxFractionDigits="2"
                                        inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 p-2 text-sm"
                                        @update:modelValue="(v) => updateActe(idx, { prix: v ?? 0 })" 
                                    />
                                    <label class="text-xs font-medium text-surface-600 dark:text-surface-400">Prix</label>
                                </FloatLabel>
                            </div>
                        </div>

                        <!-- Acte Subtotal -->
                        <div class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700">
                            <div class="flex items-center justify-end gap-2">
                                <span class="text-sm text-surface-600 dark:text-surface-400">Sous-total :</span>
                                <span class="font-bold text-primary-600 dark:text-primary-400">
                                    {{ formatCurrency(acteSubtotals[idx] ?? acteTotal(acte)) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Soins -->
                <div v-if="form.actes?.length" class="mt-4 pt-4 border-t border-surface-200 dark:border-surface-700">
                    <div class="flex items-center justify-between">
                        <div class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            Total des soins
                        </div>
                        <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                            {{ formatCurrency(totalActesValue) }}
                        </div>
                    </div>
                </div>
            </div>

            <Dialog v-model:visible="addActeDialogVisible" header="Ajouter plusieurs soins" modal class="w-full max-w-3xl">
                <div class="space-y-4">
                    <p class="text-sm text-surface-600 dark:text-surface-300">Sélectionnez une ou plusieurs dents.</p>
                    <div class="space-y-4">
                        <div v-for="(row, rowIndex) in formuleRows" :key="'dialog-' + rowIndex" class="space-y-2">
                            <div class="flex flex-wrap justify-center gap-2">
                                <button
                                    v-for="tooth in row.left"
                                    :key="'dialog-left-' + tooth"
                                    type="button"
                                    class="h-12 w-12 rounded-xl border text-[10px] font-semibold tracking-tight transition-all duration-200"
                                    :class="[toothStateClass(tooth), isToothSelected(tooth) ? 'ring-2 ring-primary-400 ring-offset-2' : '']"
                                    @click="toggleToothSelection(tooth)"
                                >
                                    <div class="text-[9px] leading-tight">Dent</div>
                                    <div class="text-xs font-bold">{{ tooth }}</div>
                                    <div class="text-[9px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                                </button>
                            </div>
                            <div class="flex flex-wrap justify-center gap-2">
                                <button
                                    v-for="tooth in row.right"
                                    :key="'dialog-right-' + tooth"
                                    type="button"
                                    class="h-12 w-12 rounded-xl border text-[10px] font-semibold tracking-tight transition-all duration-200"
                                    :class="[toothStateClass(tooth), isToothSelected(tooth) ? 'ring-2 ring-primary-400 ring-offset-2' : '']"
                                    @click="toggleToothSelection(tooth)"
                                >
                                    <div class="text-[9px] leading-tight">Dent</div>
                                    <div class="text-xs font-bold">{{ tooth }}</div>
                                    <div class="text-[9px] leading-tight opacity-80">{{ toothSummary(tooth) || '---' }}</div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <template #footer>
                    <Button label="Annuler" severity="secondary" text @click="addActeDialogVisible = false" />
                    <Button label="Ajouter" icon="pi pi-check" :disabled="!selectedTeeth.length" @click="confirmAddActes" />
                </template>
            </Dialog>

            <!-- Ordonnances -->
            <div v-if="!hideOrdonnances" class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-purple-500/10">
                            <i class="pi pi-prescription text-purple-500"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Ordonnances</h4>
                            <p class="text-sm text-surface-500 dark:text-surface-400">Prescriptions médicamenteuses</p>
                        </div>
                    </div>
                    <Button 
                        icon="pi pi-plus" 
                        label="Nouvelle ordonnance" 
                        size="small"
                        class="rounded-xl px-4 py-2.5 bg-gradient-to-r from-purple-500 to-purple-600 border-0 text-white shadow-sm hover:shadow-md transition-all"
                        @click="emit('open-ordonnance')" 
                    />
                </div>

                <!-- Ordonnances List -->
                <div class="space-y-3">
                    <div v-if="!(ordonnances && ordonnances.length)" class="text-center py-6">
                        <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 mb-3">
                            <i class="pi pi-file text-2xl text-surface-400"></i>
                        </div>
                        <p class="text-surface-600 dark:text-surface-400">Aucune ordonnance. Créez une nouvelle ordonnance.</p>
                    </div>
                    
                    <div v-for="ordo in ordonnances" :key="ordo.id" 
                         class="flex items-center justify-between p-3 rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 hover:bg-surface-50 dark:hover:bg-surface-700 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-purple-500/10">
                                <i class="pi pi-prescription text-purple-500"></i>
                            </div>
                            <div>
                                <div class="font-medium text-surface-900 dark:text-surface-100">
                                    Ordonnance du {{ ordo.date || '—' }}
                                </div>
                                <div class="text-sm text-surface-600 dark:text-surface-400">
                                    Par {{ ordo.medecinNom || ordo.medecin || '—' }}
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge :value="ordo.lignes?.length || 0" severity="info" class="px-2 py-1 text-xs" />
                            <Button 
                                icon="pi pi-print" 
                                label="Imprimer" 
                                size="small"
                                outlined
                                class="rounded-lg px-3 py-1.5"
                                @click="emit('print-ordonnance', ordo)" 
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session Summary -->
            <div class="grid grid-cols-1 gap-4" :class="hideOrdonnances ? 'md:grid-cols-2' : 'md:grid-cols-3'">
                <div class="p-4 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 border border-blue-200/50 dark:border-blue-800/50">
                    <div class="text-sm font-medium text-blue-700 dark:text-blue-300">Actes réalisés</div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">{{ form.actes?.length || 0 }}</div>
                </div>
                <div v-if="!hideOrdonnances" class="p-4 rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:from-emerald-900/20 dark:to-emerald-800/20 border border-emerald-200/50 dark:border-emerald-800/50">
                    <div class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Ordonnances</div>
                    <div class="text-2xl font-bold text-emerald-900 dark:text-emerald-100 mt-1">{{ ordonnances?.length || 0 }}</div>
                </div>
                <div class="p-4 rounded-xl bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 border border-amber-200/50 dark:border-amber-800/50">
                    <div class="text-sm font-medium text-amber-700 dark:text-amber-300">Coût total</div>
                    <div class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-1">{{ formatCurrency(totalActesValue) }}</div>
                </div>
            </div>
        </div>
        </template>
    </div>
</template>

<style scoped>
    :deep(.p-inputnumber ) {
        width: 100%;
    }
</style>