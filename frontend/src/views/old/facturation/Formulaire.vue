<script setup>
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import FloatLabel from 'primevue/floatlabel';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import http from '@/service/http';
import { apiPrefix } from '@/config';

const axios = http;

const token = localStorage.getItem('token');
const route = useRoute();
const toast = useToast();
const visibleRight = ref(false);
const displayConfirmation = ref(false);
// Le bouton est désactivé si un champ est vide
const isFormInvalid = computed(() => {
    return !clientName.value || !yearSortie.value;
});

// Infos Générales
const clientName = ref('');
const address = ref('');
const refDossier = ref('');
const nbHectares = ref(1);
const yearSortie = ref(null);
const yearJour = new Date();
const dateFacture = new Date();

// Ouverture du Dossier
const proceduresChecked = ref(true);
const proceduresQte = ref(1);
const proceduresPu = ref(100000);
const proceduresTotal = computed(() => (proceduresChecked.value ? proceduresQte.value * proceduresPu.value : 0));
const ouvertureSectionTotal = computed(() => proceduresTotal.value);

// Operations Topographiques
const idChecked = ref(true);
const idQte = ref(1);
const idPu = ref(75000);
const idTotal = computed(() => (idChecked.value ? idQte.value * idPu.value : 0));
const delimChecked = ref(true);
const delimQte = ref(1);
const delimPu = ref(125000);
const delimTotal = computed(() => (delimChecked.value ? delimQte.value * delimPu.value : 0));
const morcChecked = ref(true);
const morcQte = ref(1);
const morcPu = ref(600000);
const morcTotal = computed(() => (morcChecked.value ? morcQte.value * morcPu.value : 0));
const leveChecked = ref(true);
const leveQte = ref(1);
const levePu = ref(125000);
const leveTotal = computed(() => (leveChecked.value ? leveQte.value * levePu.value : 0));
const etatTopoChecked = ref(true);
const etatTopoQte = ref(1);
const etatTopoPu = ref(270000);
const etatTopoTotal = computed(() => (etatTopoChecked.value ? etatTopoQte.value * etatTopoPu.value : 0));
const operationsSectionTotal = computed(() => idTotal.value + delimTotal.value + morcTotal.value + leveTotal.value + etatTopoTotal.value);

// Acquisition Titres
const acqChecked = ref(true);
const acqQte = ref(1);
const acqPu = ref(950000);
const acqTotal = computed(() => (acqChecked.value ? acqQte.value * acqPu.value : 0));
const redChecked = ref(true);
const redQte = ref(0);
const redPu = ref(20000);
const redTotal = computed(() => (redChecked.value ? redQte.value * redPu.value : 0));
const prixChecked = ref(true);
const prixQte = ref(1);
const prixPu = ref(850000);
const prixTotal = computed(() => (prixChecked.value ? prixQte.value * prixPu.value : 0));
const acquisitionSectionTotal = computed(() => acqTotal.value + redTotal.value + prixTotal.value);

// Suivi du Dossier
const suiviChecked = ref(true);
const suiviPu = ref(1588000);
const suiviTotal = computed(() => (suiviChecked.value ? suiviPu.value : 0));
const visChecked = ref(true);
const visQte = ref(3);
const visPu = ref(50000);
const visTotal = computed(() => (visChecked.value ? visQte.value * visPu.value : 0));
const trRegChecked = ref(true);
const trRegQte = ref(10);
const trRegPu = ref(20000);
const trRegTotal = computed(() => (trRegChecked.value ? trRegQte.value * trRegPu.value : 0));
const pubChecked = ref(true);
const pubQte = ref(2);
const pubPu = ref(40000);
const pubTotal = computed(() => (pubChecked.value ? pubQte.value * pubPu.value : 0));
const huiChecked = ref(true);
const huiQte = ref(5);
const huiPu = ref(50000);
const huiTotal = computed(() => (huiChecked.value ? huiQte.value * huiPu.value : 0));
const trCerChecked = ref(true);
const trCerQte = ref(10);
const trCerPu = ref(20000);
const trCerTotal = computed(() => (trCerChecked.value ? trCerQte.value * trCerPu.value : 0));
const signChecked = ref(true);
const signQte = ref(2);
const signPu = ref(200000);
const signTotal = computed(() => (signChecked.value ? signQte.value * signPu.value : 0));
const suiviSectionTotal = computed(() => suiviTotal.value);

// Expertise
const recChecked = ref(true);
const recQte = ref(1);
const recPu = ref(300000);
const recTotal = computed(() => (recChecked.value ? recQte.value * recPu.value : 0));
const elabLChecked = ref(true);
const elabLQte = ref(1);
const elabLPu = ref(200000);
const elabLTotal = computed(() => (elabLChecked.value ? elabLQte.value * elabLPu.value : 0));
const etatChecked = ref(true);
const etatQte = ref(1);
const etatPu = ref(125000);
const etatTotal = computed(() => (etatChecked.value ? etatQte.value * etatPu.value : 0));
const elabRChecked = ref(true);
const elabRQte = ref(1);
const elabRPu = ref(750000);
const elabRTotal = computed(() => (elabRChecked.value ? elabRQte.value * elabRPu.value : 0));
const expertiseSectionTotal = computed(() => recTotal.value + elabLTotal.value + etatTotal.value + elabRTotal.value);

// Evaluation
const evalChecked = ref(true);
const evalQte = ref(1);
const evalPu = ref(1188000);
const evalTotal = computed(() => (evalChecked.value ? evalQte.value * evalPu.value : 0));
const evaluationSectionTotal = computed(() => evalTotal.value);

// Assistances Conseils et Orientations
const assChecked = ref(true);
const assQte = ref(1);
const assPu = ref(250000);
const assTotal = computed(() => (assChecked.value ? assQte.value * assPu.value : 0));
const assistancesSectionTotal = computed(() => assTotal.value);

// Autres
const autres = ref([]);
const addAutre = () => {
    autres.value.push({ title: '', checked: true, qte: 1, pu: 0 });
};
const removeAutre = (index) => {
    autres.value.splice(index, 1);
};
const autresTotals = computed(() => autres.value.reduce((sum, a) => sum + (a.checked ? a.qte * a.pu : 0), 0));

// Calculs
const debours1 = computed(() => acqTotal.value + redTotal.value + prixTotal.value);
const debours2 = computed(() => visTotal.value + trRegTotal.value + pubTotal.value + huiTotal.value + trCerTotal.value + signTotal.value);
const totalDebours = computed(() => debours1.value + debours2.value);
const totalAvecDebours = computed(
    () => ouvertureSectionTotal.value + operationsSectionTotal.value + acquisitionSectionTotal.value + suiviSectionTotal.value + expertiseSectionTotal.value + evaluationSectionTotal.value + assistancesSectionTotal.value + autresTotals.value
);
const totalPrestations = computed(() => totalAvecDebours.value - debours1.value);

const applyPrefillFromQuery = () => {
    const { clientName: queryName, address: queryAddress, refDossier: queryRef, nbHectares: queryHectares } = route.query;
    if (queryName) clientName.value = String(queryName);
    if (queryAddress) address.value = String(queryAddress);
    if (queryRef) refDossier.value = String(queryRef);
    if (queryHectares && !Number.isNaN(Number(queryHectares))) {
        nbHectares.value = Number(queryHectares);
    }
};

// Fonction pour soumettre le formulaire
const submitForm = async () => {
    try {
        const formData = {
            clientName: clientName.value,
            address: address.value,
            refDossier: refDossier.value,
            nbHectares: nbHectares.value,
            yearSortie: yearSortie.value,
            dateFacture: dateFacture.value,
            // Ouverture du Dossier
            proceduresChecked: proceduresChecked.value,
            proceduresQte: proceduresQte.value,
            proceduresPu: proceduresPu.value,
            // Operations Topographiques
            idChecked: idChecked.value,
            idQte: idQte.value,
            idPu: idPu.value,
            delimChecked: delimChecked.value,
            delimQte: delimQte.value,
            delimPu: delimPu.value,
            morcChecked: morcChecked.value,
            morcQte: morcQte.value,
            morcPu: morcPu.value,
            leveChecked: leveChecked.value,
            leveQte: leveQte.value,
            levePu: levePu.value,
            etatTopoChecked: etatTopoChecked.value,
            etatTopoQte: etatTopoQte.value,
            etatTopoPu: etatTopoPu.value,
            // Acquisition Titres
            acqChecked: acqChecked.value,
            acqQte: acqQte.value,
            acqPu: acqPu.value,
            redChecked: redChecked.value,
            redQte: redQte.value,
            redPu: redPu.value,
            prixChecked: prixChecked.value,
            prixQte: prixQte.value,
            prixPu: prixPu.value,
            // Suivi du Dossier
            suiviChecked: suiviChecked.value,
            suiviPu: suiviPu.value,
            visChecked: visChecked.value,
            visQte: visQte.value,
            visPu: visPu.value,
            trRegChecked: trRegChecked.value,
            trRegQte: trRegQte.value,
            trRegPu: trRegPu.value,
            pubChecked: pubChecked.value,
            pubQte: pubQte.value,
            pubPu: pubPu.value,
            huiChecked: huiChecked.value,
            huiQte: huiQte.value,
            huiPu: huiPu.value,
            trCerChecked: trCerChecked.value,
            trCerQte: trCerQte.value,
            trCerPu: trCerPu.value,
            signChecked: signChecked.value,
            signQte: signQte.value,
            signPu: signPu.value,
            // Expertise
            recChecked: recChecked.value,
            recQte: recQte.value,
            recPu: recPu.value,
            elabLChecked: elabLChecked.value,
            elabLQte: elabLQte.value,
            elabLPu: elabLPu.value,
            etatChecked: etatChecked.value,
            etatQte: etatQte.value,
            etatPu: etatPu.value,
            elabRChecked: elabRChecked.value,
            elabRQte: elabRQte.value,
            elabRPu: elabRPu.value,
            // Evaluation
            evalChecked: evalChecked.value,
            evalQte: evalQte.value,
            evalPu: evalPu.value,
            // Assistances
            assChecked: assChecked.value,
            assQte: assQte.value,
            assPu: assPu.value,
            // Autres
            autres: autres.value
        };

        await axios.post(`${apiPrefix}/form/submit`, formData, {
            headers: { Authorization: `Bearer ${token}` }
        });
        toast.add({ severity: 'success', summary: 'Succès', detail: 'Formulaire soumis avec succès', life: 3000 });
        closeConfirmation();
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de soumettre le formulaire', life: 3000 });
    }
};

// Fonctions pour la gestion de la confirmation
const openConfirmation = () => {
    displayConfirmation.value = true;
};

const closeConfirmation = () => {
    displayConfirmation.value = false;
};

// Chargement initial
onMounted(() => {
    applyPrefillFromQuery();
    delimQte.value = nbHectares.value;
    acqQte.value = Math.ceil(nbHectares.value / 2.5);
});

watch(
    () => route.query,
    () => {
        applyPrefillFromQuery();
    }
);

// Surveillance des changements
watch(yearSortie, (newVal) => {
    if (newVal) {
        redQte.value = yearJour.getFullYear() - newVal.getFullYear() + 5;
    } else {
        redQte.value = 0;
    }
});

watch(nbHectares, (newVal) => {
    delimQte.value = newVal;
    acqQte.value = Math.ceil(newVal / 2.5);
    if (newVal === 1) {
        suiviPu.value = 1588000;
    } else if (newVal > 2.5) {
        suiviPu.value = 2000000;
    } else {
        suiviPu.value = 1588000;
    }
});
</script>

<template>
    <div class="flex flex-col gap-8 p-4">
        <!-- Aide -->
        <div class="flex justify-start">
            <Button icon="pi pi-question" label="Aide" @click="visibleRight = true" />
        </div>
        <Drawer v-model:visible="visibleRight" position="right" header="Aide">
            <p>Si Hectare égal 1: 1 588 000</p>
            <p>Si Hectare supérieur à 2,5: 2 000 000</p>
            <p>La redevance domaniale est égale à l'année actuelle - l'année de sortie + 5</p>
            <p>La quantité de titres précaires est égale au multiple de 2.5 correspondant à la borne supérieure de l'intervalle dans lequel se trouve le nombre d'hectares (ex: &lt;=2.5:1, &lt;=5:2, &lt;=7.5:3, etc.)</p>
            <p>Vous devez entrez le nom du client et l'année de sortie pour confirmer le formulaire</p>
        </Drawer>

        <!-- Infos Générales -->
        <div class="card p-6 border-round-lg shadow-sm">
            <h2 class="font-semibold text-2xl mb-6 text-gray-800">Infos Générales</h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <div class="flex flex-col gap-2">
                    <FloatLabel class="w-full">
                        <InputText inputId="clientName" v-model="clientName" class="w-full" required="true" />
                        <label for="clientName" class="font-medium">Nom client</label>
                    </FloatLabel>
                </div>
                <div class="flex flex-col gap-2">
                    <FloatLabel>
                        <InputText inputId="address" v-model="address" class="w-full" />
                        <label for="address" class="font-medium">Adresse</label>
                    </FloatLabel>
                </div>
                <div class="flex flex-col gap-2">
                    <FloatLabel>
                        <InputText inputId="refDossier" v-model="refDossier" class="w-full" />
                        <label for="refDossier" class="font-medium">Référence Dossier</label>
                    </FloatLabel>
                </div>
                <div class="flex flex-col gap-2">
                    <FloatLabel>
                        <InputNumber inputId="nbHectares" v-model="nbHectares" :min="0" class="w-full" />
                        <label for="nbHectares" class="font-medium">Nombre d'hectares</label>
                    </FloatLabel>
                </div>
                <div class="flex flex-col gap-2">
                    <FloatLabel>
                        <DatePicker inputId="yearSortie" v-model="yearSortie" view="year" dateFormat="yy" class="w-full" />
                        <label for="yearSortie" class="font-medium">Année de sortie</label>
                    </FloatLabel>
                </div>
                <div class="flex flex-col gap-2">
                    <FloatLabel>
                        <DatePicker inputId="yearJour" v-model="yearJour" view="year" dateFormat="yy" readonly class="w-full bg-gray-50" />
                        <label for="yearJour" class="font-medium">Date du jour</label>
                    </FloatLabel>
                </div>
                <div>
                    <FloatLabel>
                        <DatePicker inputId="dateFacture" v-model="dateFacture" view="date" dateFormat="yy-M-d" class="w-full bg-gray-50" />
                        <label for="dateFacture" class="font-medium">Date de la facture</label>
                    </FloatLabel>
                </div>
            </div>
        </div>

        <!-- Sections en 2 colonnes -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <!-- Ouverture du Dossier -->
            <div class="card p-6 border-round-lg shadow-sm">
                <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Ouverture du Dossier</h2>
                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="proceduresChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Procedures administratives internes</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="proceduresQte" v-model="proceduresQte" mode="decimal" class="w-full" :disabled="!proceduresChecked" />
                                    <label for="proceduresQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="proceduresPu" v-model="proceduresPu" mode="decimal" class="w-full" :disabled="!proceduresChecked" />
                                    <label for="proceduresPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ ouvertureSectionTotal.toLocaleString() }} F CFA</p>
                </div>
            </div>

            <!-- Operations Topographiques -->
            <div class="card p-6 border-round-lg shadow-sm">
                <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Operations Topographiques</h2>
                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="idChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Identification</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="idQte" v-model="idQte" mode="decimal" class="w-full" :disabled="!idChecked" />
                                    <label for="idQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="idPu" v-model="idPu" mode="decimal" class="w-full" :disabled="!idChecked" />
                                    <label for="idPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="delimChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Delimitation/Hectare</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="delimQte" v-model="delimQte" mode="decimal" class="w-full" :disabled="!delimChecked" />
                                    <label for="delimQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="delimPu" v-model="delimPu" mode="decimal" class="w-full" :disabled="!delimChecked" />
                                    <label for="delimPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="morcChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Morcellement</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="morcQte" v-model="morcQte" mode="decimal" class="w-full" :disabled="!morcChecked" />
                                    <label for="morcQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="morcPu" v-model="morcPu" mode="decimal" class="w-full" :disabled="!morcChecked" />
                                    <label for="morcPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="leveChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Levé d'etudes</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="leveQte" v-model="leveQte" mode="decimal" class="w-full" :disabled="!leveChecked" />
                                    <label for="leveQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="levePu" v-model="levePu" mode="decimal" class="w-full" :disabled="!leveChecked" />
                                    <label for="levePu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="etatTopoChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Etat des lieux</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="etatTopoQte" v-model="etatTopoQte" mode="decimal" class="w-full" :disabled="!etatTopoChecked" />
                                    <label for="etatTopoQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="etatTopoPu" v-model="etatTopoPu" mode="decimal" class="w-full" :disabled="!etatTopoChecked" />
                                    <label for="etatTopoPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ operationsSectionTotal.toLocaleString() }} F CFA</p>
                </div>
            </div>

            <!-- Acquisition Titres -->
            <div class="card p-6 border-round-lg shadow-sm">
                <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Acquisition Titres</h2>
                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="acqChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Titres Precaires</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="acqQte" v-model="acqQte" mode="decimal" class="w-full" :disabled="!acqChecked" />
                                    <label for="acqQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="acqPu" v-model="acqPu" mode="decimal" class="w-full" :disabled="!acqChecked" />
                                    <label for="acqPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="space-y-4">
                            <p class="font-medium text-gray-700">Titres Fonciers</p>
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="redChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Redevance domaniale</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="redQte" v-model="redQte" mode="decimal" class="w-full" :disabled="!redChecked" />
                                            <label for="redQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="redPu" v-model="redPu" mode="decimal" class="w-full" :disabled="!redChecked" />
                                            <label for="redPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="prixChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Prix de Cession</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="prixQte" v-model="prixQte" mode="decimal" class="w-full" :disabled="!prixChecked" />
                                            <label for="prixQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="prixPu" v-model="prixPu" mode="decimal" class="w-full" :disabled="!prixChecked" />
                                            <label for="prixPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ acquisitionSectionTotal.toLocaleString() }} F CFA</p>
                </div>
            </div>

            <!-- Suivi du Dossier -->
            <div class="card p-6 border-round-lg shadow-sm">
                <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Suivi du Dossier</h2>
                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="suiviChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Suivi du Dossier</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="suiviPu" v-model="suiviPu" mode="decimal" class="w-full" :disabled="!suiviChecked" />
                                    <label for="suiviPu">Prix</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="space-y-4">
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="visChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Visite de Terrain agents</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="visQte" v-model="visQte" mode="decimal" class="w-full" :disabled="!visChecked" />
                                            <label for="visQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="visPu" v-model="visPu" mode="decimal" class="w-full" :disabled="!visChecked" />
                                            <label for="visPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="trRegChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Traitement administratif Dossier Region</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="trRegQte" v-model="trRegQte" mode="decimal" class="w-full" :disabled="!trRegChecked" />
                                            <label for="trRegQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="trRegPu" v-model="trRegPu" mode="decimal" class="w-full" :disabled="!trRegChecked" />
                                            <label for="trRegPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="pubChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Publication</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="pubQte" v-model="pubQte" mode="decimal" class="w-full" :disabled="!pubChecked" />
                                            <label for="pubQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="pubPu" v-model="pubPu" mode="decimal" class="w-full" :disabled="!pubChecked" />
                                            <label for="pubPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="huiChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Frais Huissier</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="huiQte" v-model="huiQte" mode="decimal" class="w-full" :disabled="!huiChecked" />
                                            <label for="huiQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="huiPu" v-model="huiPu" mode="decimal" class="w-full" :disabled="!huiChecked" />
                                            <label for="huiPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="trCerChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Traitement administratif Dossier Cercle</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="trCerQte" v-model="trCerQte" mode="decimal" class="w-full" :disabled="!trCerChecked" />
                                            <label for="trCerQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="trCerPu" v-model="trCerPu" mode="decimal" class="w-full" :disabled="!trCerChecked" />
                                            <label for="trCerPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-4">
                                <Checkbox v-model="signChecked" :binary="true" class="mt-1" />
                                <div class="flex-1">
                                    <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Signature de l'acte Administratif Prefecture</label>
                                    <div class="flex gap-3 flex-wrap">
                                        <FloatLabel class="flex-1 min-w-[120px]">
                                            <InputNumber inputId="signQte" v-model="signQte" mode="decimal" class="w-full" :disabled="!signChecked" />
                                            <label for="signQte">Quantité</label>
                                        </FloatLabel>
                                        <FloatLabel class="flex-1 min-w-[140px]">
                                            <InputNumber inputId="signPu" v-model="signPu" mode="decimal" class="w-full" :disabled="!signChecked" />
                                            <label for="signPu">Prix Unitaire</label>
                                        </FloatLabel>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ suiviSectionTotal.toLocaleString() }} F CFA</p>
                </div>
            </div>

            <!-- Expertise -->
            <div class="card p-6 border-round-lg shadow-sm">
                <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Expertise</h2>
                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="recChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Recueil informations et Documentations</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="recQte" v-model="recQte" mode="decimal" class="w-full" :disabled="!recChecked" />
                                    <label for="recQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="recPu" v-model="recPu" mode="decimal" class="w-full" :disabled="!recChecked" />
                                    <label for="recPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="elabLChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Elaboration et transmission de lettre</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="elabLQte" v-model="elabLQte" mode="decimal" class="w-full" :disabled="!elabLChecked" />
                                    <label for="elabLQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="elabLPu" v-model="elabLPu" mode="decimal" class="w-full" :disabled="!elabLChecked" />
                                    <label for="elabLPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="etatChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Etat des Lieux</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="etatQte" v-model="etatQte" mode="decimal" class="w-full" :disabled="!etatChecked" />
                                    <label for="etatQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="etatPu" v-model="etatPu" mode="decimal" class="w-full" :disabled="!etatChecked" />
                                    <label for="etatPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                        <Checkbox v-model="elabRChecked" :binary="true" class="mt-1" />
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Elaboration du Rapport</label>
                            <div class="flex gap-3 flex-wrap">
                                <FloatLabel class="flex-1 min-w-[120px]">
                                    <InputNumber inputId="elabRQte" v-model="elabRQte" mode="decimal" class="w-full" :disabled="!elabRChecked" />
                                    <label for="elabRQte">Quantité</label>
                                </FloatLabel>
                                <FloatLabel class="flex-1 min-w-[140px]">
                                    <InputNumber inputId="elabRPu" v-model="elabRPu" mode="decimal" class="w-full" :disabled="!elabRChecked" />
                                    <label for="elabRPu">Prix Unitaire</label>
                                </FloatLabel>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ expertiseSectionTotal.toLocaleString() }} F CFA</p>
                </div>
            </div>

            <!-- Evaluation -->
            <div class="card p-6 border-round-lg shadow-sm">
                <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Evaluation</h2>
                <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <Checkbox v-model="evalChecked" :binary="true" class="mt-1" />
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Evaluation</label>
                        <div class="flex gap-3 flex-wrap">
                            <FloatLabel class="flex-1 min-w-[120px]">
                                <InputNumber inputId="evalQte" v-model="evalQte" mode="decimal" class="w-full" :disabled="!evalChecked" />
                                <label for="evalQte">Quantité</label>
                            </FloatLabel>
                            <FloatLabel class="flex-1 min-w-[140px]">
                                <InputNumber inputId="evalPu" v-model="evalPu" mode="decimal" class="w-full" :disabled="!evalChecked" />
                                <label for="evalPu">Prix Unitaire</label>
                            </FloatLabel>
                        </div>
                    </div>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ evaluationSectionTotal.toLocaleString() }} F CFA</p>
                </div>
            </div>

            <!-- Assistances Conseils et Orientations -->
            <div class="card p-6 border-round-lg shadow-sm">
                <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Assistances Conseils et Orientations</h2>
                <div class="flex items-start gap-4 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <Checkbox v-model="assChecked" :binary="true" class="mt-1" />
                    <div class="flex-1">
                        <label class="block text-sm font-medium mb-2 text-gray-700 mb-[30px]">Assistances Conseils et Orientations</label>
                        <div class="flex gap-3 flex-wrap">
                            <FloatLabel class="flex-1 min-w-[120px]">
                                <InputNumber inputId="assQte" v-model="assQte" mode="decimal" class="w-full" :disabled="!assChecked" />
                                <label for="assQte">Quantité</label>
                            </FloatLabel>
                            <FloatLabel class="flex-1 min-w-[140px]">
                                <InputNumber inputId="assPu" v-model="assPu" mode="decimal" class="w-full" :disabled="!assChecked" />
                                <label for="assPu">Prix Unitaire</label>
                            </FloatLabel>
                        </div>
                    </div>
                </div>
                <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ assistancesSectionTotal.toLocaleString() }} F CFA</p>
                </div>
            </div>
        </div>

        <!-- Autres -->
        <div class="card p-6 border-round-lg shadow-sm mb-0">
            <h2 class="font-semibold text-xl mb-4 text-gray-800 border-b pb-3">Autres</h2>
            <div class="flex justify-end mb-4">
                <Button label="Ajouter un élément" icon="pi pi-plus" @click="addAutre" />
            </div>
            <div v-for="(autre, index) in autres" :key="index" class="flex items-start gap-4 p-3 mb-3 hover:bg-gray-50 rounded-lg transition-colors border border-gray-200">
                <Checkbox v-model="autre.checked" :binary="true" class="mt-1" />
                <FloatLabel class="flex-1 min-w-[200px]">
                    <InputText inputId="autreTitle" v-model="autre.title" class="w-full" :disabled="!autre.checked" />
                    <label for="autreTitle">Titre</label>
                </FloatLabel>
                <FloatLabel class="flex-1 min-w-[120px]">
                    <InputNumber inputId="autreQte" v-model="autre.qte" mode="decimal" class="w-full" :disabled="!autre.checked" />
                    <label for="autreQte">Quantité</label>
                </FloatLabel>
                <FloatLabel class="flex-1 min-w-[140px]">
                    <InputNumber inputId="autrePu" v-model="autre.pu" mode="decimal" class="w-full" :disabled="!autre.checked" />
                    <label for="autrePu">Prix Unitaire</label>
                </FloatLabel>
                <Button icon="pi pi-trash" severity="danger" @click="removeAutre(index)" class="p-button-sm mt-1" title="Supprimer cet élément" aria-label="Supprimer cet élément" />
            </div>
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-blue-800 font-bold text-lg text-center">Total section: {{ autresTotals.toLocaleString() }} F CFA</p>
            </div>
        </div>

        <!-- Totaux -->
        <div class="space-y-3">
            <!-- Total Prestations -->
            <div class="flex justify-end">
                <div class="w-full md:w-2/3 lg:w-1/2 p-4 border-2 border-green-500 rounded-lg bg-green-50 shadow-sm">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-green-800 text-lg">Total 1 :</span>
                        <span class="font-bold text-green-800 text-xl">{{ totalAvecDebours.toLocaleString() }} F CFA</span>
                    </div>
                </div>
            </div>

            <!-- Débours 1 -->
            <div class="flex justify-end">
                <div class="w-full md:w-2/3 lg:w-1/2 p-4 border border-gray-300 rounded-lg bg-gray-50 shadow-sm">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-700">Débours 1 :</span>
                        <span class="font-bold text-gray-800">{{ debours1.toLocaleString() }} F CFA</span>
                    </div>
                </div>
            </div>

            <!-- Débours 2 -->
            <div class="flex justify-end">
                <div class="w-full md:w-2/3 lg:w-1/2 p-4 border border-gray-300 rounded-lg bg-gray-50 shadow-sm">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-700">Débours 2 :</span>
                        <span class="font-bold text-gray-800">{{ debours2.toLocaleString() }} F CFA</span>
                    </div>
                </div>
            </div>

            <!-- Total Débours -->
            <div class="flex justify-end">
                <div class="w-full md:w-2/3 lg:w-1/2 p-4 border border-gray-300 rounded-lg bg-gray-100 shadow-sm">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold text-gray-700">Total Débours :</span>
                        <span class="font-bold text-gray-800">{{ totalDebours.toLocaleString() }} F CFA</span>
                    </div>
                </div>
            </div>

            <!-- Total 2 -->
            <div class="flex justify-end">
                <div class="w-full md:w-2/3 lg:w-1/2 p-4 border-2 border-green-700 rounded-lg bg-green-100 shadow-sm">
                    <div class="flex justify-between items-center font-bold">
                        <span class="text-green-700">Total 2 :</span>
                        <span class="text-green-700 text-lg">{{ totalPrestations.toLocaleString() }} F CFA</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bouton Soumettre -->
        <div class="flex justify-end mt-8">
            <Button label="Soumettre le formulaire" icon="pi pi-check" @click="openConfirmation" :disabled="isFormInvalid" class="bg-blue-600 hover:bg-blue-700 border-blue-600 px-6 py-3" />
        </div>

        <!-- Dialogue de confirmation -->
        <Dialog header="Confirmation" v-model:visible="displayConfirmation" :style="{ width: '450px' }" :modal="true">
            <div class="flex items-center gap-4">
                <i class="pi pi-exclamation-triangle text-yellow-500" style="font-size: 2rem" />
                <span class="text-gray-700">Êtes-vous sûr de vouloir soumettre ce formulaire ?</span>
            </div>
            <template #footer>
                <Button label="Annuler" icon="pi pi-times" @click="closeConfirmation" severity="secondary" />
                <Button label="Confirmer" icon="pi pi-check" @click="submitForm" />
            </template>
        </Dialog>
    </div>
</template>
