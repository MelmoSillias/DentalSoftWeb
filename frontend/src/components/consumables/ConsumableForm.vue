<script setup>
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import Message from 'primevue/message';
import ConfirmPopup from 'primevue/confirmpopup';
import { useToast } from 'primevue/usetoast';
import { Form } from '@primevue/forms';
import { useConfirm } from 'primevue/useconfirm';
import { useConsumables } from '@/composables/useConsumables';
import { computed } from 'vue';
import { zodResolver } from '@primevue/forms/resolvers/zod';
import { z } from 'zod';

const consommablesStore = useConsumables();
const confirm = useConfirm();
const toast = useToast();

const props = defineProps({
    consumable: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits([
    'saved', // retourne le consommable sauvegardé
    'close' // permet au parent de fermer le dialog
]);

const isEditMode = computed(() => !!props.consumable?.id);

const initialValues = computed(() => ({
    nom: props.consumable?.nom ?? '',
    fournisseur: props.consumable?.fournisseur ?? '',
    quantite: props.consumable?.quantity ?? props.consumable?.quantite ?? 0,
    lowValue: props.consumable?.lowValue ?? 0
}));

const resolver = zodResolver(
    z.object({
        nom: z.string().min(3, 'Le nom du consommable est requis. 3 caractères minimum.'),
        fournisseur: z.string().optional(),
        quantite: z.number().min(0, 'Quantité invalide.').optional(),
        lowValue: z.number().min(0, 'La valeur de stock bas doit être un nombre valide et non négatif.').optional()
    })
);

async function saveConsumable(values) {
    if (isEditMode.value) {
        return await consommablesStore.editConsumable(props.consumable.id, values);
    }

    return await consommablesStore.addConsumable(values);
}

async function onFormSubmit({ valid, values }) {
    if (!valid) return;

    const result = await saveConsumable(values);

    if (result?.ok) {
        emit('saved'); // informer le parent
        toast.add({ severity: 'success', summary: 'Succès', detail: `Consommable ${isEditMode.value ? 'mis à jour' : 'ajouté'} avec succès.` });
    } else {
        toast.add({ severity: 'error', summary: 'Erreur', detail: `Échec lors de ${isEditMode.value ? 'la mise à jour' : "l'ajout"} du consommable. <br> ${consommablesStore.error.value || ''}` });
    }
}
</script>

<template>
    <Form v-slot="$form" :initialValues="initialValues" :resolver="resolver" @submit="onFormSubmit" class="flex flex-col gap-4 w-full">
        <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
            <div class="flex flex-col gap-2">
                <label class="font-semibold mb-1">Nom du consommable <span class="text-red-500">*</span></label>
                <InputText name="nom" class="w-full" />
                <Message v-if="$form.nom?.invalid" severity="error" size="small" variant="simple">
                    {{ $form.nom.error?.message }}
                </Message>
            </div>

            <div class="flex flex-col gap-2">
                <label class="font-semibold mb-1">Fournisseur</label>
                <InputText name="fournisseur" />
            </div>

            <div v-if="!isEditMode" class="flex flex-col gap-2">
                <label class="font-semibold mb-1">Quantité</label>
                <InputNumber name="quantite" class="w-full" />
                <Message v-if="$form.quantite?.invalid" severity="error" size="small" variant="simple">
                    {{ $form.quantite.error?.message }}
                </Message>
            </div>

            <div class="flex flex-col gap-2">
                <label class="font-semibold mb-1">Valeur de Stock Bas</label>
                <InputNumber name="lowValue" showButtons buttonLayout="horizontal" :step="1">
                    <template #incrementicon>
                        <span class="pi pi-plus" />
                    </template>
                    <template #decrementicon>
                        <span class="pi pi-minus" />
                    </template>
                </InputNumber>
                <Message v-if="$form.lowValue?.invalid" severity="error" size="small" variant="simple">
                    {{ $form.lowValue.error?.message }}
                </Message>
            </div>
        </div>

        <Button type="submit" :label="isEditMode ? 'Mettre à jour' : 'Enregistrer'" :severity="isEditMode ? 'success' : 'primary'" />
    </Form>

    <ConfirmPopup />
</template>
