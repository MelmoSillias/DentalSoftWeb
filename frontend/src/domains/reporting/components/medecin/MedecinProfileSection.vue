<script setup>
import Accordion from 'primevue/accordion';
import AccordionContent from 'primevue/accordioncontent';
import AccordionHeader from 'primevue/accordionheader';
import AccordionPanel from 'primevue/accordionpanel';
import Card from 'primevue/card';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

const _props = defineProps({
    data: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const typeOptions = [
    { label: 'Médecin', value: 'Medecin' },
    { label: 'GRH', value: 'GRH' },
    { label: 'Réceptionniste', value: 'Receptionniste' },
    { label: 'Admin', value: 'Admin' },
    { label: 'Autre', value: 'Autre' }
];

const salaryOptions = [
    { label: 'Fixe', value: 'fixe' },
    { label: 'Pourcentage', value: 'pourcentage' }
];
</script>

<template>
    <section class="space-y-4">
        <Card class="min-w-0 overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:bg-surface-900 dark:border-surface-700">
            <template #title>
                <div class="flex items-center gap-2 text-base font-semibold text-surface-900 dark:text-surface-0">Informations personnelles de l'employé</div>
            </template>
            <template #content>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Nom</label>
                        <InputText :model-value="data.nom" class="w-full" readonly />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Prénom</label>
                        <InputText :model-value="data.prenom" class="w-full" readonly />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Matricule</label>
                        <InputText :model-value="data.matricule" class="w-full" readonly />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Fonction</label>
                        <InputText :model-value="data.fonction" class="w-full" readonly />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Type</label>
                        <Select :model-value="data.type" :options="typeOptions" optionLabel="label" optionValue="value" class="w-full" disabled />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Téléphone</label>
                        <InputText :model-value="data.telephone" class="w-full" readonly />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Email</label>
                        <InputText :model-value="data.email" class="w-full" readonly />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-sm text-surface-500">Date d'embauche</label>
                        <InputText :model-value="data.dateEmbauche" class="w-full" readonly />
                    </div>
                </div>
            </template>
        </Card>

        <Card class="min-w-0 overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:bg-surface-900 dark:border-surface-700">
            <template #title>
                <div class="flex items-center gap-2 text-base font-semibold text-surface-900 dark:text-surface-0">Profil du médecin</div>
            </template>
            <template #content>
                <Accordion multiple>
                    <AccordionPanel value="0">
                        <AccordionHeader>Informations RH</AccordionHeader>
                        <AccordionContent>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-1.5">
                                    <label class="text-sm text-surface-500">Type de salaire</label>
                                    <Select :model-value="data.typeSalaire" :options="salaryOptions" optionLabel="label" optionValue="value" class="w-full" disabled />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-sm text-surface-500">Valeur du salaire</label>
                                    <InputNumber :model-value="data.valeurSalaire" class="w-full" :minFractionDigits="0" :maxFractionDigits="2" disabled />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-sm text-surface-500">Type de contrat</label>
                                    <InputText :model-value="data.typeContrat" class="w-full" readonly />
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-sm text-surface-500">Durée de contrat (mois)</label>
                                    <InputNumber :model-value="data.dureeContrat" class="w-full" disabled />
                                </div>
                            </div>
                        </AccordionContent>
                    </AccordionPanel>
                    <AccordionPanel value="1">
                        <AccordionHeader>Jours travaillés</AccordionHeader>
                        <AccordionContent>
                            <div class="flex flex-wrap gap-2">
                                <Tag v-for="jour in data.joursTravailles || []" :key="jour" :value="jour" severity="info" />
                                <p v-if="!(data.joursTravailles || []).length" class="text-sm text-surface-500">Aucun jour renseigné.</p>
                            </div>
                        </AccordionContent>
                    </AccordionPanel>
                </Accordion>
            </template>
        </Card>
    </section>
</template>
