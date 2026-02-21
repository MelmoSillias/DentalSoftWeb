<script setup>
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({})
    },
    saving: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'save']);

const form = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
});

const updateField = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

const addDocument = () => {
    const docs = form.value.documents || [];
    form.value = {
        ...form.value,
        documents: [...docs, { titre: '', description: '', date: '', fichier: null, url: '' }]
    };
};

const updateDocument = (idx, patch) => {
    const docs = (form.value.documents || []).map((doc, i) => (i === idx ? { ...doc, ...patch } : doc));
    form.value = { ...form.value, documents: docs };
};

const removeDocument = (idx) => {
    const docs = (form.value.documents || []).filter((_, i) => i !== idx);
    form.value = { ...form.value, documents: docs };
};
</script>

<template>
    <div class="card p-0">
        <div class="card-header flex justify-between items-center">
            <h6 class="m-0 font-bold text-primary">Traitements & Documents</h6>
            <Button label="Sauvegarder" icon="pi pi-save" :loading="saving" @click="emit('save')" />
        </div>
        <div class="card-body flex flex-col gap-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Urgence</label>
                    <Textarea v-model="form.traitementUrgence" rows="3"
                        @update:modelValue="(v) => updateField('traitementUrgence', v)" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Dentaire</label>
                    <Textarea v-model="form.traitementDentaire" rows="3"
                        @update:modelValue="(v) => updateField('traitementDentaire', v)" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Parodontal</label>
                    <Textarea v-model="form.traitementParodontal" rows="3"
                        @update:modelValue="(v) => updateField('traitementParodontal', v)" />
                </div>
                <div class="flex flex-col gap-2 md:col-span-2">
                    <label class="font-semibold">Orthodontique</label>
                    <Textarea v-model="form.traitementOrthodontique" rows="3"
                        @update:modelValue="(v) => updateField('traitementOrthodontique', v)" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-semibold">Autres</label>
                    <Textarea v-model="form.autres" rows="3" @update:modelValue="(v) => updateField('autres', v)" />
                </div>
            </div>

            <div class="card">
                <div class="card-header flex justify-between items-center">
                    <h6 class="m-0 font-semibold">Documents médicaux</h6>
                    <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="addDocument" />
                </div>
                <div class="card-body flex flex-col gap-3">
                    <div v-if="!(form.documents && form.documents.length)" class="text-gray-500 text-sm">
                        Aucun document ajouté.
                    </div>
                    <div v-for="(doc, idx) in form.documents" :key="idx"
                        class="p-3 border rounded-md shadow-sm theme-surface">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm text-gray-600">Titre</label>
                                <InputText :value="doc.titre"
                                    @update:modelValue="(v) => updateDocument(idx, { titre: v })" />
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-sm text-gray-600">Date</label>
                                <InputText :value="doc.date"
                                    @update:modelValue="(v) => updateDocument(idx, { date: v })" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-1 mb-3">
                            <label class="text-sm text-gray-600">Description</label>
                            <Textarea :value="doc.description" rows="2"
                                @update:modelValue="(v) => updateDocument(idx, { description: v })" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2">
                            <div class="flex flex-col gap-1">
                                <label class="text-sm text-gray-600">Fichier</label>
                                <input type="file" class="p-2 border rounded"
                                    @change="(e) => updateDocument(idx, { fichier: e.target.files?.[0] || null })" />
                                <span v-if="doc.url" class="text-xs text-primary-500 break-words">Fichier existant :
                                    <a :href="doc.url" target="_blank" class="underline">voir</a>
                                </span>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <Button icon="pi pi-trash" label="Supprimer" size="small" severity="danger" outlined
                                @click="removeDocument(idx)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.theme-surface {
    background-color: var(--p-surface-0, #ffffff);
}

@media (prefers-color-scheme: dark) {
    .theme-surface {
        background-color: var(--p-surface-900, #0b1220);
    }
}
</style>
