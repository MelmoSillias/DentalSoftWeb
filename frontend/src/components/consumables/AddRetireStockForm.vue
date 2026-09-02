<script setup>
import { onMounted, ref } from 'vue';
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
import { useEmployees } from '@/composables/useEmployees';

const consumablesStore = useConsumables();
const confirm = useConfirm();
const toast = useToast();
const employeeStore = useEmployees();

const props = defineProps({
    mode: {
        type: String,
        default: () => 'add'
    },
    consumable: {
        type: Object,
        default: () => ({})
    }
});

const emit = defineEmits(['saved', 'cancelled']);

const resolver = zodResolver(
    z.object({
        quantite: z.number().min(1, 'Entrez une quantité supérieure à 0').and(z.number().int('La quantité doit être un nombre entier')),
        employe: z.number().min(1, 'Veuillez choisir un employé').optional(),
        description: z.string().optional()
    })
);

const initialValues = ref({
    quantite: 1,
    employe: null,
    description: ''
});

async function onFormSubmit({ valid, values }) {
    if (!valid) return;
    if (!props.consumable) return;

    if (props.mode === 'add') {
        await consumablesStore.addStock(props.consumable.id, values);
        toast.add({ severity: 'success', summary: 'Succès', detail: `Ajout réussie avec succès.`, life: 3000 });
        emit('saved');
    } else {
        await consumablesStore.withdrawStock(props.consumable.id, values);
        toast.add({ severity: 'success', summary: 'Succès', detail: `Retrait réussie avec succès.`, life: 3000 });
        emit('saved');
    }
}

onMounted(async () => {
    await employeeStore.fetchEmployees();
});
</script>

<template>
    <Form v-slot="$form" :initialValues="initialValues" :resolver="resolver" @submit="onFormSubmit" class="flex flex-col gap-4 w-full">
        <div class="flex flex-col gap-4">
            <div v-if="props.consumable">
                <p class="text-lg">Consommable : {{ props.consumable.nom }}</p>
            </div>
            <Message v-else severity="warn" text="Aucun consommable sélectionné"></Message>
            <div class="col py-1">
                <FloatLabel variant="on">
                    <InputNumber name="quantite" showButtons buttonLayout="horizontal" class="w-full" :step="1">
                        <template #incrementicon>
                            <span class="pi pi-plus" />
                        </template>
                        <template #decrementicon>
                            <span class="pi pi-minus" />
                        </template>
                    </InputNumber>
                    <label for="quantite">Quantité <span class="text-red-500">*</span></label>
                    <Message v-if="$form.quantite?.invalid" severity="error" variant="simple">
                        {{ $form.quantite.error?.message }}
                    </Message>
                </FloatLabel>
            </div>
            <div v-if="props.mode !== 'add'" class="col">
                <FloatLabel variant="on">
                    <Select name="employe" :options="employeeStore.employees.value" option-label="fullname" option-value="id" class="w-full" filter></Select>
                    <label for="employe">Employé</label>
                    <Message v-if="$form.employe?.invalid" severity="error" variant="simple">
                        {{ $form.employe.error?.message }}
                    </Message>
                </FloatLabel>
            </div>
            <div>
                <FloatLabel variant="on">
                    <label class="font-semibold mb-1">Description (optionnel)</label>
                    <Textarea name="description" class="w-full" rows="3" autoResize></Textarea>
                    <Message v-if="$form.description?.invalid" severity="error" size="small" variant="simple">
                        {{ $form.description.error?.message }}
                    </Message>
                </FloatLabel>
            </div>
        </div>
        <Divider class="py-0"></Divider>
        <div class="flex justify-end">
            <Button label="Annuler" class="p-button-text mr-2" @click="emit('cancelled')"></Button>
            <Button label="Enregistrer" type="submit" :loading="consumablesStore.loading.value"></Button>
        </div>
    </Form>
</template>
