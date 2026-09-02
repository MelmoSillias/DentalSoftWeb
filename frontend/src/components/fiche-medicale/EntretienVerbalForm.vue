<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    },
    saving: {
        type: Boolean,
        default: false
    },
    patientSex: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['update:modelValue', 'save', 'open-rdv']);

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const yesNoOptions = [
    { label: 'Oui', value: true },
    { label: 'Non', value: false }
];

const antecedentTypeOptions = [
    { label: 'Medicament en cours', value: 'medicament' },
    { label: 'Affection', value: 'affection' }
];

const medicamentList = [
    'Hypotenseurs',
    'Anti-Inflammatoire',
    'Insuline',
    'Contraceptifs',
    'Anticoagulant',
    'Hypoglycemiants',
    'Antalgiques',
    'Cardiorhythmiques',
    'Antihistaminiques',
    'Salicyles',
    'Antidepresseurs',
    'Neuroleptiques',
    'Psychotropes',
    'Autres'
];

const affectionList = [
    'Diabete',
    'Allergie',
    'Gastrite',
    'Epilepsie',
    'Chaines ganglionnaires',
    'Hypertension',
    'Tuberculose',
    'Ulcere',
    'Affection hepatique',
    'Sous mandibulaires',
    'Hyperthyroidie',
    'Asthme',
    'Hemophilie',
    'Drepanocytose',
    'Examen buccal',
    'Hypothyroidie',
    'Insuffisance renale',
    'Anemie',
    'Cardiopathies',
    'Autres'
];

const questionsList = ['Avez-vous deja ete hospitalise', 'Avez-vous ete opere sous anesthesie generale', 'Supportez-vous les anesthesies locales', 'Avez-vous deja subi une evaluation dentaire', 'Etes-vous sujet aux hemorragies'];

const habitudesList = ['Tabac', 'Alcool', 'Autres'];

const showAntecedentDialog = ref(false);
const antecedentDraft = ref({
    type: 'medicament',
    option: null,
    customName: '',
    isPresent: true,
    details: ''
});

const isFemalePatient = computed(() => {
    const normalized = String(props.patientSex || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
    return ['f', 'femme', 'feminin', 'female', 'woman'].includes(normalized);
});

const antecedentOptionList = computed(() => {
    return antecedentDraft.value.type === 'affection' ? affectionList : medicamentList;
});

const antecedentsRows = computed(() => {
    const medicaments = Array.isArray(form.value.medicaments) ? form.value.medicaments : [];
    const affections = Array.isArray(form.value.affections) ? form.value.affections : [];

    const mappedMedicaments = medicaments.map((item, idx) => ({
        key: `medicament-${item.id ?? item.nom ?? idx}`,
        type: 'Medicament en cours',
        rawType: 'medicament',
        nom: item.nom || 'Autres',
        estPresent: item.estUtilise,
        details: item.details || ''
    }));

    const mappedAffections = affections.map((item, idx) => ({
        key: `affection-${item.id ?? item.nom ?? idx}`,
        type: 'Affection',
        rawType: 'affection',
        nom: item.nom || 'Autres',
        estPresent: item.estPresente,
        details: item.details || ''
    }));

    return [...mappedMedicaments, ...mappedAffections];
});

const updateField = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

const updateArrayItem = (key, nom, patch) => {
    const list = Array.isArray(form.value[key]) ? [...form.value[key]] : [];
    const index = list.findIndex((item) => item.nom === nom || item.question === nom || item.type === nom);
    if (index === -1) {
        const base = { nom };
        list.push({ ...base, ...patch });
    } else {
        list[index] = { ...list[index], ...patch, nom: list[index].nom ?? nom, question: list[index].question ?? nom, type: list[index].type ?? nom };
    }
    form.value = { ...form.value, [key]: list };
};

const getArrayItem = (key, nom) => {
    const list = Array.isArray(form.value[key]) ? form.value[key] : [];
    return list.find((item) => item.nom === nom || item.question === nom || item.type === nom) || {};
};

const openAddAntecedent = () => {
    antecedentDraft.value = {
        type: 'medicament',
        option: null,
        customName: '',
        isPresent: true,
        details: ''
    };
    showAntecedentDialog.value = true;
};

const saveAntecedentDraft = () => {
    const option = antecedentDraft.value.option;
    const customName = antecedentDraft.value.customName.trim();
    const name = option === 'Autres' ? customName : option;
    if (!name) return;

    if (antecedentDraft.value.type === 'affection') {
        const list = Array.isArray(form.value.affections) ? [...form.value.affections] : [];
        const index = list.findIndex((item) => item.nom === name);
        const nextItem = {
            ...(index >= 0 ? list[index] : {}),
            nom: name,
            estPresente: antecedentDraft.value.isPresent,
            details: antecedentDraft.value.details
        };
        if (index >= 0) {
            list[index] = nextItem;
        } else {
            list.push(nextItem);
        }
        updateField('affections', list);
    } else {
        const list = Array.isArray(form.value.medicaments) ? [...form.value.medicaments] : [];
        const index = list.findIndex((item) => item.nom === name);
        const nextItem = {
            ...(index >= 0 ? list[index] : {}),
            nom: name,
            estUtilise: antecedentDraft.value.isPresent,
            details: antecedentDraft.value.details
        };
        if (index >= 0) {
            list[index] = nextItem;
        } else {
            list.push(nextItem);
        }
        updateField('medicaments', list);
    }

    showAntecedentDialog.value = false;
};

const deleteAntecedent = (row) => {
    if (row.rawType === 'affection') {
        const list = Array.isArray(form.value.affections) ? form.value.affections : [];
        updateField(
            'affections',
            list.filter((item) => item.nom !== row.nom)
        );
        return;
    }

    const list = Array.isArray(form.value.medicaments) ? form.value.medicaments : [];
    updateField(
        'medicaments',
        list.filter((item) => item.nom !== row.nom)
    );
};
</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-file-edit text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Questionnaire médical</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Anamnese, antecedents et habitudes declarees</p>
                </div>
            </div>
            <Button
                label="Sauvegarder"
                icon="pi pi-save"
                :loading="saving"
                @click="emit('save')"
                class="rounded-xl px-5 py-3 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
            />
        </div>

        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="p-2 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700 lg:col-span-12">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="flex items-center justify-center w-6 h-6 rounded-md bg-amber-500/10">
                            <i class="pi pi-history text-amber-500 text-sm"></i>
                        </div>
                        <h5 class="font-semibold text-surface-900 dark:text-surface-100">Anamnese</h5>
                    </div>
                    <Textarea v-model="form.motifConsultation" rows="6" placeholder="Evolution de la maladie..." class="w-full rounded-xl border-surface-200 dark:border-surface-700" @update:modelValue="(v) => updateField('motifConsultation', v)" />
                </div>

                <div v-if="isFemalePatient" class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700 lg:col-span-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-6 h-6 rounded-md bg-emerald-500/10">
                            <i class="pi pi-heart text-emerald-500 text-sm"></i>
                        </div>
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Etat gynecologique</h4>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-700 dark:text-surface-300">Allaitement</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="form.etatGynecologique?.allaitement"
                                @update:modelValue="(v) => updateField('etatGynecologique', { ...form.etatGynecologique, allaitement: v })"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-700 dark:text-surface-300">Grossesse en cours</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="form.etatGynecologique?.grossesseEnCours"
                                @update:modelValue="(v) => updateField('etatGynecologique', { ...form.etatGynecologique, grossesseEnCours: v })"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-surface-700 dark:text-surface-300">Menstrues</span>
                            <SelectButton
                                :options="yesNoOptions"
                                optionLabel="label"
                                optionValue="value"
                                :modelValue="form.etatGynecologique?.menstrues"
                                @update:modelValue="(v) => updateField('etatGynecologique', { ...form.etatGynecologique, menstrues: v })"
                            />
                        </div>
                    </div>
                </div>

                <div v-if="isFemalePatient" class="p-4 rounded-xl bg-surface-50 dark:bg-surface-700/30 border border-surface-200 dark:border-surface-700 lg:col-span-8">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100">Prochain rendez-vous</h4>
                            <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Créer rapidement un nouveau rendez-vous pour ce patient.</p>
                        </div>
                        <Button icon="pi pi-calendar-plus" label="Créer" size="small" @click="emit('open-rdv')" />
                    </div>
                </div>
            </div>

            <!-- <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 lg:col-span-4">
                    <h4 class="font-semibold text-surface-900 dark:text-surface-100 mb-3">Questionnaire medical et habitudes de vie</h4>
                    <div class="space-y-3">
                        <div v-for="q in questionsList" :key="q" class="flex flex-col gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-900/40 border border-surface-200/70 dark:border-surface-700/70">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ q }}</span>
                                <SelectButton
                                    :options="yesNoOptions"
                                    optionLabel="label"
                                    optionValue="value"
                                    :modelValue="getArrayItem('questions', q).reponse"
                                    @update:modelValue="(v) => updateArrayItem('questions', q, { reponse: v, question: q })"
                                />
                            </div>
                            <InputText
                                v-if="getArrayItem('questions', q).reponse"
                                :value="getArrayItem('questions', q).precision"
                                placeholder="Precision si besoin"
                                class="w-full"
                                @update:modelValue="(v) => updateArrayItem('questions', q, { precision: v, question: q })"
                            />
                        </div>

                        <div class="pt-2 border-t border-surface-200/70 dark:border-surface-700/70">
                            <p class="text-xs uppercase tracking-wide text-surface-500 dark:text-surface-400 mb-2">Habitudes de vie</p>
                            <div class="space-y-3">
                                <div v-for="h in habitudesList" :key="h" class="flex flex-col gap-2 p-3 rounded-lg bg-surface-0 dark:bg-surface-900/40 border border-surface-200/70 dark:border-surface-700/70">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ h }}</span>
                                        <SelectButton
                                            :options="yesNoOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            :modelValue="getArrayItem('habitudes', h).estPresente"
                                            @update:modelValue="(v) => updateArrayItem('habitudes', h, { estPresente: v, type: h })"
                                        />
                                    </div>
                                    <InputText
                                        v-if="getArrayItem('habitudes', h).estPresente"
                                        :value="getArrayItem('habitudes', h).quantite"
                                        placeholder="Quantite / details"
                                        class="w-full"
                                        @update:modelValue="(v) => updateArrayItem('habitudes', h, { quantite: v, type: h })"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 lg:col-span-8">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Antecedents medicaux (medicaments et affections)</h4>
                        <Button icon="pi pi-plus" label="Ajouter" size="small" @click="openAddAntecedent" />
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900/40">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-surface-200 dark:border-surface-700 bg-surface-100/80 dark:bg-surface-800/80">
                                    <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300">Type</th>
                                    <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300">Element</th>
                                    <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300">Etat</th>
                                    <th class="p-3 text-left font-semibold text-surface-700 dark:text-surface-300">Details</th>
                                    <th class="p-3 text-right font-semibold text-surface-700 dark:text-surface-300">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!antecedentsRows.length">
                                    <td colspan="5" class="p-4 text-center text-surface-500 dark:text-surface-400">Aucun antecedent saisi.</td>
                                </tr>
                                <tr v-for="row in antecedentsRows" :key="row.key" class="border-b border-surface-200/70 dark:border-surface-700/70 last:border-b-0">
                                    <td class="p-3 text-surface-700 dark:text-surface-300">{{ row.type }}</td>
                                    <td class="p-3 text-surface-700 dark:text-surface-300">{{ row.nom }}</td>
                                    <td class="p-3">
                                        <span class="text-xs font-semibold" :class="row.estPresent ? 'text-emerald-600' : 'text-surface-500 dark:text-surface-400'">
                                            {{ row.estPresent ? 'Oui' : 'Non' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-surface-600 dark:text-surface-400">{{ row.details || '—' }}</td>
                                    <td class="p-3 text-right">
                                        <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="deleteAntecedent(row)" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> -->

            <Dialog v-model:visible="showAntecedentDialog" modal header="Ajouter un antecedent" class="w-full max-w-xl">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Type</label>
                        <Select
                            v-model="antecedentDraft.type"
                            :options="antecedentTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            @update:modelValue="
                                () => {
                                    antecedentDraft.option = null;
                                    antecedentDraft.customName = '';
                                }
                            "
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Element</label>
                        <Select v-model="antecedentDraft.option" :options="antecedentOptionList" placeholder="Selectionnez une option" class="w-full" />
                    </div>

                    <div v-if="antecedentDraft.option === 'Autres'" class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Nom personnalise</label>
                        <InputText v-model="antecedentDraft.customName" class="w-full" placeholder="Precisez l'element" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Etat</label>
                        <SelectButton v-model="antecedentDraft.isPresent" :options="yesNoOptions" optionLabel="label" optionValue="value" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-surface-700 dark:text-surface-300">Details</label>
                        <Textarea v-model="antecedentDraft.details" rows="3" class="w-full" placeholder="Details complementaires" />
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <Button label="Annuler" severity="secondary" outlined @click="showAntecedentDialog = false" />
                        <Button label="Ajouter" icon="pi pi-check" :disabled="!antecedentDraft.option || (antecedentDraft.option === 'Autres' && !antecedentDraft.customName.trim())" @click="saveAntecedentDraft" />
                    </div>
                </template>
            </Dialog>
        </div>
    </div>
</template>

<style scoped>
:deep(.p-togglebutton.p-togglebutton-checked .p-togglebutton-content) {
    background-color: rgb(54, 199, 97) !important;
    color: white;
}
</style>
