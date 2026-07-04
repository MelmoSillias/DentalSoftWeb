<script setup>
import FicheDocumentsForm from '@/components/fiche-medicale/FicheDocumentsForm.vue';
import AutoComplete from 'primevue/autocomplete';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { computed, ref } from 'vue';

const props = defineProps({
    entretien: {
        type: Object,
        default: () => ({})
    },
    patient: {
        type: Object,
        default: () => ({ antecedents: [], allergies: [] })
    },
    documents: {
        type: Object,
        default: () => ({ documents: [] })
    },
    examens: {
        type: Object,
        default: () => ({ examensLabo: [] })
    },
    bilans: {
        type: Object,
        default: () => ({})
    },
    planTraitement: {
        type: Array,
        default: () => []
    },
    saving: {
        type: Object,
        default: () => ({})
    },
    documentsUploadProgress: {
        type: Object,
        default: null
    },
    isClotureProcessing: {
        type: Boolean,
        default: false
    },
    examensTypeOptions: {
        type: Array,
        default: () => ['Bacteriologique', 'Serologique', 'Histologique', 'Radiologique', 'Autre']
    },
    traitementTypeOptions: {
        type: Array,
        default: () => ['Urgence', 'Dentaires', 'Parodontaux', 'Orthodontiques', 'Autres']
    }
});

const emit = defineEmits([
    'update:entretien',
    'update:documents',
    'update:examens',
    'update:bilans',
    'update:planTraitement',
    'save',
    'save-documents',
    'add-antecedent',
    'add-allergy',
    'delete-antecedent',
    'delete-allergy',
    'open-rdv'
]);

const examensTypeSuggestions = ref([]);
const traitementTypeSuggestions = ref([]);

const isSaving = computed(() => Boolean(
    props.saving?.entretien
    || props.saving?.examens
    || props.saving?.documents
    || props.saving?.bilans
    || props.saving?.planTraitement
));

const antecedentsCount = computed(() => props.patient?.antecedents?.length || 0);
const allergiesCount = computed(() => props.patient?.allergies?.length || 0);
const examensCount = computed(() => props.examens?.examensLabo?.length || 0);
const plansCount = computed(() => props.planTraitement?.length || 0);

const normalizeText = (value) => String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

const searchExamensTypes = (event) => {
    const query = normalizeText(event?.query);
    examensTypeSuggestions.value = query
        ? props.examensTypeOptions.filter((item) => normalizeText(item).includes(query))
        : [...props.examensTypeOptions];
};

const searchTraitementTypes = (event) => {
    const query = normalizeText(event?.query);
    traitementTypeSuggestions.value = query
        ? props.traitementTypeOptions.filter((item) => normalizeText(item).includes(query))
        : [...props.traitementTypeOptions];
};

const updateEntretienField = (key, value) => {
    emit('update:entretien', { ...props.entretien, [key]: value });
};

const updateBilanField = (key, value) => {
    emit('update:bilans', { ...props.bilans, [key]: value });
};

const addExamComplementaireRow = () => {
    const current = Array.isArray(props.examens?.examensLabo) ? props.examens.examensLabo : [];
    emit('update:examens', {
        ...props.examens,
        examensLabo: [...current, { type: '', description: '', date: null, resultat: '' }]
    });
};

const removeExamComplementaireRow = (index) => {
    const current = Array.isArray(props.examens?.examensLabo) ? props.examens.examensLabo : [];
    emit('update:examens', {
        ...props.examens,
        examensLabo: current.filter((_, idx) => idx !== index)
    });
};

const addPlanRow = () => {
    const plans = Array.isArray(props.planTraitement) ? props.planTraitement : [];
    emit('update:planTraitement', [
        ...plans,
        {
            planIndex: plans.length + 1,
            type: '',
            dateSupposed: null,
            description: ''
        }
    ]);
};

const removePlanRow = (index) => {
    const plans = Array.isArray(props.planTraitement) ? props.planTraitement : [];
    emit('update:planTraitement', plans.filter((_, idx) => idx !== index).map((item, idx) => ({
        ...item,
        planIndex: idx + 1
    })));
};
</script>

<template>
    <div class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-0 to-surface-50/80 dark:from-surface-800 dark:to-surface-900/80 p-5 md:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-surface-100 dark:border-surface-700">
            <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-file-edit text-primary-600 dark:text-primary-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">Synthèse clinique</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Vue condensée du questionnaire, examens, bilan, plan de traitement et documents</p>
                </div>
            </div>
            <Button
                label="Enregistrer"
                icon="pi pi-save"
                :loading="isClotureProcessing || isSaving"
                :disabled="isClotureProcessing"
                class="rounded-xl px-5 py-3 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white shrink-0"
                @click="emit('save')"
            />
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[0.92fr_1.08fr] gap-5 xl:gap-6">
            <!-- Colonne gauche : contexte patient -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 px-1">
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-surface-200 dark:via-surface-600 to-transparent"></div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-surface-400 dark:text-surface-500">Contexte patient</span>
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-surface-200 dark:via-surface-600 to-transparent"></div>
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/30 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-amber-500/10">
                            <i class="pi pi-history text-amber-500 text-sm"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100">Anamnèse</h4>
                            <p class="text-xs text-surface-500 dark:text-surface-400">Résumé clinique du patient</p>
                        </div>
                    </div>
                    <Textarea
                        :modelValue="entretien.anamnese"
                        rows="4"
                        placeholder="Motif, évolution, symptômes rapportés..."
                        class="w-full rounded-xl"
                        @update:modelValue="(v) => updateEntretienField('anamnese', v)"
                    />
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/30 p-4">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-rose-500/10">
                            <i class="pi pi-shield text-rose-500 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100">Antécédents & allergies</h4>
                            <p class="text-xs text-surface-500 dark:text-surface-400">{{ antecedentsCount }} antécédent(s) · {{ allergiesCount }} allergie(s)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <div class="rounded-xl border border-surface-200/80 dark:border-surface-700/80 bg-white/70 dark:bg-surface-900/50 p-3">
                            <div class="flex items-center justify-between mb-2.5">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-bookmark text-emerald-500 text-sm"></i>
                                    <p class="text-sm font-semibold text-surface-800 dark:text-surface-100">Antécédents</p>
                                </div>
                                <Button icon="pi pi-plus" label="Ajouter" text size="small" class="!px-2" @click="emit('add-antecedent')" />
                            </div>
                            <div class="space-y-2 max-h-32 overflow-auto pr-1 custom-scrollbar">
                                <div
                                    v-for="item in patient.antecedents"
                                    :key="`a-${item.id}`"
                                    class="group flex items-start justify-between gap-2 rounded-lg border border-surface-200/80 dark:border-surface-700/80 bg-surface-50/80 dark:bg-surface-800/40 px-2.5 py-2 transition-colors hover:border-emerald-300/60 dark:hover:border-emerald-700/40"
                                >
                                    <div class="text-sm text-surface-700 dark:text-surface-300 leading-5 min-w-0">
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 px-2 py-0.5 text-xs font-medium mr-1.5 shrink-0">{{ item.type || 'Antécédent' }}</span>
                                        <span class="break-words">{{ item.description || '—' }}</span>
                                    </div>
                                    <Button icon="pi pi-trash" text severity="danger" size="small" class="opacity-60 group-hover:opacity-100 shrink-0" @click="emit('delete-antecedent', item)" />
                                </div>
                                <div v-if="!patient.antecedents?.length" class="flex flex-col items-center justify-center py-5 text-center rounded-lg border border-dashed border-surface-200 dark:border-surface-700">
                                    <i class="pi pi-inbox text-surface-300 dark:text-surface-600 text-lg mb-1.5"></i>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Aucun antécédent enregistré</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-surface-200/80 dark:border-surface-700/80 bg-white/70 dark:bg-surface-900/50 p-3">
                            <div class="flex items-center justify-between mb-2.5">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-exclamation-triangle text-sky-500 text-sm"></i>
                                    <p class="text-sm font-semibold text-surface-800 dark:text-surface-100">Allergies</p>
                                </div>
                                <Button icon="pi pi-plus" label="Ajouter" text size="small" class="!px-2" @click="emit('add-allergy')" />
                            </div>
                            <div class="space-y-2 max-h-32 overflow-auto pr-1 custom-scrollbar">
                                <div
                                    v-for="item in patient.allergies"
                                    :key="`al-${item.id}`"
                                    class="group flex items-start justify-between gap-2 rounded-lg border border-surface-200/80 dark:border-surface-700/80 bg-surface-50/80 dark:bg-surface-800/40 px-2.5 py-2 transition-colors hover:border-sky-300/60 dark:hover:border-sky-700/40"
                                >
                                    <div class="text-sm text-surface-700 dark:text-surface-300 leading-5 min-w-0">
                                        <span class="inline-flex items-center rounded-full bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 px-2 py-0.5 text-xs font-medium mr-1.5 shrink-0">{{ item.libelle || 'Allergie' }}</span>
                                        <span class="break-words">{{ item.description || '—' }}</span>
                                    </div>
                                    <Button icon="pi pi-trash" text severity="danger" size="small" class="opacity-60 group-hover:opacity-100 shrink-0" @click="emit('delete-allergy', item)" />
                                </div>
                                <div v-if="!patient.allergies?.length" class="flex flex-col items-center justify-center py-5 text-center rounded-lg border border-dashed border-surface-200 dark:border-surface-700">
                                    <i class="pi pi-inbox text-surface-300 dark:text-surface-600 text-lg mb-1.5"></i>
                                    <p class="text-sm text-surface-500 dark:text-surface-400">Aucune allergie enregistrée</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-primary-200/50 dark:border-primary-800/40 bg-gradient-to-br from-primary-50/80 to-surface-0 dark:from-primary-950/30 dark:to-surface-900/40 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="flex items-center justify-center w-9 h-9 rounded-xl bg-primary-500/15 dark:bg-primary-500/25 shrink-0">
                                <i class="pi pi-calendar-plus text-primary-600 dark:text-primary-400"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-surface-900 dark:text-surface-50">Prochain rendez-vous</p>
                                <p class="text-xs text-surface-500 dark:text-surface-400 mt-0.5">Planifier un suivi pour ce patient</p>
                            </div>
                        </div>
                        <Button icon="pi pi-calendar-plus" label="Créer" size="small" class="rounded-lg shrink-0" @click="emit('open-rdv')" />
                    </div>
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/30 p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-violet-500/10">
                            <i class="pi pi-paperclip text-violet-500 text-sm"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100">Documents</h4>
                            <p class="text-xs text-surface-500 dark:text-surface-400">Radios, analyses, comptes rendus</p>
                        </div>
                    </div>
                    <FicheDocumentsForm
                        :modelValue="documents"
                        :saving="saving.documents"
                        :upload-progress="documentsUploadProgress"
                        :compact="true"
                        @update:modelValue="(v) => emit('update:documents', v)"
                        @save="emit('save-documents')"
                    />
                </div>
            </div>

            <!-- Colonne droite : actes & décisions -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 px-1">
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-surface-200 dark:via-surface-600 to-transparent"></div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-surface-400 dark:text-surface-500">Actes & décisions</span>
                    <div class="h-px flex-1 bg-gradient-to-r from-transparent via-surface-200 dark:via-surface-600 to-transparent"></div>
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/30 p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-cyan-500/10">
                                <i class="pi pi-search text-cyan-500 text-sm"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100">Examens complémentaires</h4>
                                <p class="text-xs text-surface-500 dark:text-surface-400">{{ examensCount }} ligne(s)</p>
                            </div>
                        </div>
                        <Button icon="pi pi-plus" label="Ajouter" text size="small" class="!px-2" @click="addExamComplementaireRow" />
                    </div>
                    <div class="space-y-3 max-h-80 overflow-auto pr-1 custom-scrollbar">
                        <div
                            v-for="(item, examIndex) in examens.examensLabo"
                            :key="examIndex"
                            class="relative rounded-xl border border-surface-200/90 dark:border-surface-700/90 bg-white/80 dark:bg-surface-900/50 p-3 pt-4 shadow-sm"
                        >
                            <span class="absolute -top-2.5 left-3 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-cyan-500 text-white text-[0.65rem] font-bold leading-none">
                                {{ examIndex + 1 }}
                            </span>
                            <div class="grid grid-cols-12 gap-2 items-start">
                                <AutoComplete
                                    v-model="item.type"
                                    :suggestions="examensTypeSuggestions"
                                    dropdown
                                    class="col-span-12 sm:col-span-4"
                                    inputClass="w-full rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-2.5 py-1.5 text-sm"
                                    placeholder="Type d'examen"
                                    @complete="searchExamensTypes"
                                />
                                <InputText
                                    v-model="item.description"
                                    class="col-span-12 sm:col-span-8 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-2.5 py-1.5 text-sm"
                                    placeholder="Description"
                                />
                                <Textarea
                                    v-model="item.resultat"
                                    class="col-span-12 sm:col-span-8 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-2.5 py-1.5 text-sm"
                                    placeholder="Résultat"
                                    rows="2"
                                    autoResize
                                />
                                <DatePicker
                                    v-model="item.date"
                                    showIcon
                                    fluid
                                    iconDisplay="input"
                                    class="col-span-11 sm:col-span-3 rounded-lg text-sm"
                                    placeholder="Date"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    text
                                    severity="danger"
                                    size="small"
                                    class="col-span-1 justify-self-end self-start"
                                    @click="removeExamComplementaireRow(examIndex)"
                                />
                            </div>
                        </div>
                        <div v-if="!examens.examensLabo?.length" class="flex flex-col items-center justify-center py-8 text-center rounded-xl border border-dashed border-surface-200 dark:border-surface-700">
                            <i class="pi pi-search text-surface-300 dark:text-surface-600 text-2xl mb-2"></i>
                            <p class="text-sm text-surface-500 dark:text-surface-400">Aucun examen complémentaire</p>
                            <Button icon="pi pi-plus" label="Ajouter un examen" text size="small" class="mt-2" @click="addExamComplementaireRow" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/30 p-4">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-indigo-500/10">
                            <i class="pi pi-clipboard text-indigo-500 text-sm"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-surface-900 dark:text-surface-100">Bilan & avis</h4>
                            <p class="text-xs text-surface-500 dark:text-surface-400">Diagnostic et recommandations</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5 block">Bilan</label>
                            <Textarea
                                :modelValue="bilans.diagnosticPositif"
                                rows="3"
                                placeholder="Diagnostic positif, constatations cliniques..."
                                class="w-full rounded-xl"
                                @update:modelValue="(v) => updateBilanField('diagnosticPositif', v)"
                            />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-1.5 block">Avis médicaux</label>
                            <Textarea
                                :modelValue="bilans.avisMedicales"
                                rows="3"
                                placeholder="Avis, recommandations, orientation..."
                                class="w-full rounded-xl"
                                @update:modelValue="(v) => updateBilanField('avisMedicales', v)"
                            />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50/80 dark:bg-surface-800/30 p-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="flex items-center justify-center w-7 h-7 rounded-lg bg-teal-500/10">
                                <i class="pi pi-list-check text-teal-500 text-sm"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-surface-900 dark:text-surface-100">Plan de traitement</h4>
                                <p class="text-xs text-surface-500 dark:text-surface-400">{{ plansCount }} acte(s) planifié(s)</p>
                            </div>
                        </div>
                        <Button icon="pi pi-plus" label="Ajout rapide" text size="small" class="!px-2" @click="addPlanRow" />
                    </div>
                    <div class="space-y-3 max-h-80 overflow-auto pr-1 custom-scrollbar">
                        <div
                            v-for="(plan, planIndex) in planTraitement"
                            :key="plan.id || planIndex"
                            class="relative rounded-xl border border-surface-200/90 dark:border-surface-700/90 bg-white/80 dark:bg-surface-900/50 p-3 pt-4 shadow-sm"
                        >
                            <span class="absolute -top-2.5 left-3 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-teal-500 text-white text-[0.65rem] font-bold leading-none">
                                {{ planIndex + 1 }}
                            </span>
                            <div class="grid grid-cols-12 gap-2 items-start">
                                <AutoComplete
                                    v-model="plan.type"
                                    :suggestions="traitementTypeSuggestions"
                                    dropdown
                                    class="col-span-12 sm:col-span-7"
                                    inputClass="w-full rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-2.5 py-1.5 text-sm"
                                    placeholder="Type de traitement"
                                    @complete="searchTraitementTypes"
                                />
                                <DatePicker
                                    v-model="plan.dateSupposed"
                                    showIcon
                                    fluid
                                    class="col-span-11 sm:col-span-4 rounded-lg text-sm"
                                    placeholder="Date prévue"
                                />
                                <Button
                                    icon="pi pi-trash"
                                    text
                                    severity="danger"
                                    size="small"
                                    class="col-span-1 justify-self-end self-start"
                                    @click="removePlanRow(planIndex)"
                                />
                                <Textarea
                                    v-model="plan.description"
                                    class="col-span-12 rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-0 dark:bg-surface-900 px-2.5 py-1.5 text-sm"
                                    placeholder="Description du traitement"
                                    rows="2"
                                    autoResize
                                />
                            </div>
                        </div>
                        <div v-if="!planTraitement?.length" class="flex flex-col items-center justify-center py-8 text-center rounded-xl border border-dashed border-surface-200 dark:border-surface-700">
                            <i class="pi pi-list text-surface-300 dark:text-surface-600 text-2xl mb-2"></i>
                            <p class="text-sm text-surface-500 dark:text-surface-400">Aucun plan de traitement</p>
                            <Button icon="pi pi-plus" label="Ajouter un acte" text size="small" class="mt-2" @click="addPlanRow" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--p-surface-400) 50%, transparent);
    border-radius: 999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--p-surface-500) 70%, transparent);
}
</style>
