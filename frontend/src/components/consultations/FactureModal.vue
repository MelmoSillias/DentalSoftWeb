<script setup>
import { defaultSoinList } from '@/services/consultations';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog'; 
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    lines: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    },
    saving: {
        type: Boolean,
        default: false
    },
    soins: {
        type: Array,
        default: () => defaultSoinList
    },
    tourTarget: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['update:visible', 'save']);

const localLines = ref([]);

const normalizeLine = (line = {}, idx = 0) => ({
    id: line.id ?? `${Date.now()}-${idx}-${Math.round(Math.random() * 1000)}`,
    dent: line.dent ?? '',
    type: line.type ?? '',
    prix: Number(line.prix ?? 0) || 0,
    quantite: Number(line.quantite ?? 1) || 1,
    description: line.description ?? ''
});

const syncLines = () => {
    const source = props.lines?.length ? props.lines : [normalizeLine()];
    localLines.value = source.map((l, idx) => normalizeLine(l, idx));
};

watch(
    () => props.lines,
    () => syncLines(),
    { immediate: true, deep: true }
);

watch(
    () => props.visible,
    (visible) => {
        if (visible) syncLines();
    }
);

const addLine = () => {
    localLines.value = [...localLines.value, normalizeLine({}, localLines.value.length)];
};

const removeLine = (idx) => {
    if (localLines.value.length <= 1) {
        localLines.value = [normalizeLine()];
        return;
    }
    localLines.value = localLines.value.filter((_, i) => i !== idx);
};

const updateField = (idx, key, value) => {
    localLines.value = localLines.value.map((line, i) => (i === idx ? { ...line, [key]: value } : line));
};

const totalTtc = computed(() =>
    localLines.value.reduce((sum, line) => sum + (Number(line.prix) || 0) * (Number(line.quantite) || 0), 0)
);

const handleSave = () => {
    emit('save', localLines.value.map((l, idx) => normalizeLine(l, idx)));
};

const handleHide = () => emit('update:visible', false);
</script>

<template>
    <Dialog :visible="visible" modal header="Modifier la facture" :style="{ width: '52rem', maxWidth: '98vw' }"
        @update:visible="handleHide">
        <div :data-tour="props.tourTarget || null">
            <div v-if="loading" class="p-6 text-center text-gray-600">Chargement des lignes de facture...</div>

            <div v-else class="flex flex-col gap-4">
                <div v-for="(line, idx) in localLines" :key="line.id" class="border rounded-lg p-3 shadow-sm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm text-gray-600">Dent</label>
                            <InputText v-model="line.dent" placeholder="Ex : 21"
                                @update:modelValue="(val) => updateField(idx, 'dent', val)" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm text-gray-600">Acte / Soin</label>
                            <Select v-model="line.type" :options="soins" placeholder="Choisir un soin" class="w-full"
                                showClear @update:modelValue="(val) => updateField(idx, 'type', val)" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-1 mb-3">
                        <label class="text-sm text-gray-600">Description</label>
                        <Textarea v-model="line.description" autoResize rows="2"
                            @update:modelValue="(val) => updateField(idx, 'description', val)" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                        <div class="flex flex-col gap-1">
                            <label class="text-sm text-gray-600">Prix (FCFA)</label>
                            <InputNumber v-model="line.prix" mode="decimal" :minFractionDigits="0" :maxFractionDigits="2"
                                :min="0" class="w-full" inputClass="w-full"
                                @update:modelValue="(val) => updateField(idx, 'prix', val ?? 0)" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-sm text-gray-600">Quantité</label>
                            <InputNumber v-model="line.quantite" :min="1" :max="999" mode="decimal" :useGrouping="false"
                                class="w-full" inputClass="w-full"
                                @update:modelValue="(val) => updateField(idx, 'quantite', val ?? 1)" />
                        </div>
                        <div class="flex justify-end sm:justify-start md:justify-end">
                            <Button icon="pi pi-trash" label="Retirer" severity="danger" outlined size="small"
                                @click="removeLine(idx)" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <Button icon="pi pi-plus" label="Ajouter une ligne" outlined severity="primary" @click="addLine" />
                    <div class="text-right text-lg font-semibold">Total TTC : {{ totalTtc.toFixed(2) }} F CFA</div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Fermer" severity="secondary" @click="handleHide" />
                <Button label="Enregistrer" icon="pi pi-save" :loading="saving" severity="primary"
                    @click="handleSave" />
            </div>
        </template>
    </Dialog>
</template>
