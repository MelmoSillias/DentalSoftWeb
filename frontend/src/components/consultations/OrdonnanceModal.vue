<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { computed } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    modelValue: {
        type: Object,
        default: () => ({ date: null, medecinNom: '', note: '', lignes: [] })
    },
    saving: {
        type: Boolean,
        default: false
    },
    medecinReadonly: {
        type: Boolean,
        default: false
    },
    mode: {
        type: String,
        default: 'create'
    }
});

const emit = defineEmits(['update:visible', 'update:modelValue', 'save']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val)
});

const ordonnance = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const dateModel = computed({
    get: () => {
        const value = ordonnance.value?.date;
        if (!value) return null;
        if (value instanceof Date) return Number.isNaN(value.getTime()) ? null : value;
        if (typeof value === 'string') {
            const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (match) {
                const parsed = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
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

const close = () => emit('update:visible', false);

const updateField = (key, value) => {
    ordonnance.value = { ...ordonnance.value, [key]: value };
};

const addLine = () => {
    const lignes = ordonnance.value.lignes || [];
    ordonnance.value = {
        ...ordonnance.value,
        lignes: [...lignes, { designation: '', posologie: '', frequence: '', duree: '', quantite: 1, instructions: '' }]
    };
};

const updateLine = (idx, patch) => {
    const lignes = (ordonnance.value.lignes || []).map((l, i) => (i === idx ? { ...l, ...patch } : l));
    ordonnance.value = { ...ordonnance.value, lignes };
};

const removeLine = (idx) => {
    ordonnance.value = { ...ordonnance.value, lignes: (ordonnance.value.lignes || []).filter((_, i) => i !== idx) };
};

const totalMedicaments = computed(() => ordonnance.value?.lignes?.length || 0);
const totalBoites = computed(() => {
    return ordonnance.value?.lignes?.reduce((sum, line) => sum + (line.quantite || 0), 0) || 0;
});

const isViewMode = computed(() => props.mode === 'view');
const isEditMode = computed(() => props.mode === 'edit');
const dialogTitle = computed(() => {
    if (isViewMode.value) return "Voir l'ordonnance";
    if (isEditMode.value) return "Modifier l'ordonnance";
    return 'Nouvelle ordonnance';
});
const dialogSubtitle = computed(() => {
    if (isViewMode.value) return 'Détail de la prescription';
    if (isEditMode.value) return 'Mettre à jour la prescription';
    return 'Prescrire des médicaments et traitements';
});
</script>
<!-- OrdonnanceModal.vue -->
<template>
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :style="{ width: '60rem', maxWidth: '98vw' }"
        :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'px-6 py-4 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 border-b border-surface-200 dark:border-surface-700',
            content: 'px-6 py-4 bg-surface-0 dark:bg-surface-900',
            footer: 'px-6 py-4 bg-surface-50 dark:bg-surface-800 border-t border-surface-200 dark:border-surface-700'
        }"
    >
        <!-- Header -->
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-primary-500/10 dark:bg-primary-500/20">
                    <i class="pi pi-file-plus text-primary-600 dark:text-primary-400 text-2xl px-4"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-surface-900 dark:text-surface-50">{{ dialogTitle }}</h3>
                    <p class="text-sm text-surface-500 dark:text-surface-400">{{ dialogSubtitle }}</p>
                </div>
            </div>
        </template>

        <!-- Content -->
        <div class="space-y-6">
            <!-- Basic Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                        <i class="pi pi-calendar text-surface-400"></i>
                        Date
                    </label>
                    <DatePicker
                        v-model="dateModel"
                        placeholder="JJ/MM/AAAA"
                        :disabled="isViewMode"
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 p-3 focus:ring-2 focus:ring-primary-500/20 transition-all"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                        <i class="pi pi-id-card text-surface-400"></i>
                        Médecin
                    </label>
                    <InputText
                        :value="ordonnance.medecinNom"
                        placeholder="Nom du médecin prescripteur"
                        :disabled="medecinReadonly || isViewMode"
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 p-3 focus:ring-2 focus:ring-primary-500/20 transition-all"
                        @update:modelValue="(v) => updateField('medecinNom', v)"
                    />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium text-surface-700 dark:text-surface-300 flex items-center gap-2">
                        <i class="pi pi-info-circle text-surface-400"></i>
                        Note
                    </label>
                    <InputText
                        :value="ordonnance.note"
                        placeholder="Informations complémentaires"
                        :disabled="isViewMode"
                        class="w-full rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800/50 p-3 focus:ring-2 focus:ring-primary-500/20 transition-all"
                        @update:modelValue="(v) => updateField('note', v)"
                    />
                </div>
            </div>

            <!-- Lignes Header -->
            <div class="flex items-center justify-between pt-4 border-t border-surface-100 dark:border-surface-700">
                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-primary-500/10">
                        <i class="pi pi-list-check text-primary-500"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-surface-900 dark:text-surface-100">Lignes de prescription</h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400">{{ ordonnance.lignes?.length || 0 }} médicament(s)</p>
                    </div>
                </div>
                <Button
                    v-if="!isViewMode"
                    icon="pi pi-plus"
                    label="Ajouter une ligne"
                    size="small"
                    class="rounded-xl px-4 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white shadow-sm hover:shadow-md transition-all"
                    @click="addLine"
                />
            </div>

            <!-- Lignes List -->
            <div class="space-y-4">
                <div v-if="!(ordonnance.lignes && ordonnance.lignes.length)" class="text-center py-8">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-100 dark:bg-surface-800 mb-3">
                        <i class="pi pi-list text-2xl text-surface-400"></i>
                    </div>
                    <p class="text-surface-600 dark:text-surface-400">Aucune ligne de prescription. Cliquez sur "Ajouter une ligne" pour commencer.</p>
                </div>

                <div
                    v-for="(line, idx) in ordonnance.lignes"
                    :key="idx"
                    class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800/30 p-4 shadow-sm hover:shadow-md transition-all"
                    :class="isViewMode ? 'pointer-events-none opacity-95' : ''"
                >
                    <!-- Line Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center justify-center w-6 h-6 rounded-md bg-surface-200 dark:bg-surface-700 text-surface-700 dark:text-surface-300 text-sm font-bold">
                                {{ idx + 1 }}
                            </span>
                            <span class="font-medium text-surface-900 dark:text-surface-100">Ligne {{ idx + 1 }}</span>
                        </div>
                        <Button v-if="!isViewMode" icon="pi pi-trash" severity="danger" text rounded v-tooltip="'Supprimer cette ligne'" class="hover:bg-red-50 dark:hover:bg-red-900/20" @click="removeLine(idx)" />
                    </div>

                    <!-- Line Content -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Médicament</label>
                            <InputText
                                :value="line.designation"
                                placeholder="Nom du médicament"
                                class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5 text-sm"
                                @update:modelValue="(v) => updateLine(idx, { designation: v })"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Posologie</label>
                            <InputText
                                :value="line.posologie"
                                placeholder="Ex: 1 comprimé"
                                class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5 text-sm"
                                @update:modelValue="(v) => updateLine(idx, { posologie: v })"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Fréquence</label>
                            <InputText
                                :value="line.frequence"
                                placeholder="Ex: 3 fois par jour"
                                class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5 text-sm"
                                @update:modelValue="(v) => updateLine(idx, { frequence: v })"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Durée</label>
                            <InputText
                                :value="line.duree"
                                placeholder="Ex: 7 jours"
                                class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5 text-sm"
                                @update:modelValue="(v) => updateLine(idx, { duree: v })"
                            />
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Quantité</label>
                            <InputNumber
                                :modelValue="line.quantite"
                                :min="1"
                                mode="decimal"
                                :useGrouping="false"
                                inputClass="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5 text-sm"
                                @update:modelValue="(v) => updateLine(idx, { quantite: v ?? 1 })"
                            />
                        </div>
                        <div class="space-y-2 md:col-span-3">
                            <label class="text-xs font-medium text-surface-600 dark:text-surface-400 uppercase tracking-wider">Instructions spéciales</label>
                            <InputText
                                :value="line.instructions"
                                placeholder="Instructions particulières (repas, précautions...)"
                                class="w-full rounded-lg border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-800 p-2.5 text-sm"
                                @update:modelValue="(v) => updateLine(idx, { instructions: v })"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div v-if="ordonnance.lignes?.length" class="rounded-xl bg-gradient-to-r from-primary-50 to-primary-100/50 dark:from-primary-900/20 dark:to-primary-800/20 p-4 border border-primary-200/50 dark:border-primary-800/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-info-circle text-primary-500"></i>
                        <span class="text-sm font-medium text-primary-700 dark:text-primary-300"> Total : {{ totalMedicaments }} médicament(s) prescrit(s) </span>
                    </div>
                    <div class="text-sm text-primary-600 dark:text-primary-400">{{ totalBoites }} boîte(s) au total</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <template #footer>
            <div class="flex justify-end gap-3">
                <Button
                    :label="isViewMode ? 'Fermer' : 'Annuler'"
                    severity="secondary"
                    outlined
                    class="rounded-xl px-5 py-2.5 border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                    @click="close"
                />
                <Button
                    v-if="!isViewMode"
                    :label="isEditMode ? 'Enregistrer les modifications' : 'Enregistrer l\'ordonnance'"
                    icon="pi pi-save"
                    :loading="saving"
                    class="rounded-xl px-5 py-2.5 font-medium shadow-sm hover:shadow-md transition-all bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white"
                    @click="emit('save')"
                />
            </div>
        </template>
    </Dialog>
</template>
