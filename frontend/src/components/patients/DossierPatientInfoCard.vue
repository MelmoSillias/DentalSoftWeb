<script setup>
import Button from 'primevue/button';
import { computed, ref } from 'vue';
import PatientAvatar from '@/components/patients/PatientAvatar.vue';

const props = defineProps({
    patient: {
        type: Object,
        required: true
    },
    hideActions: {
        type: Boolean,
        default: false
    },
    hidePhone: {
        type: Boolean,
        default: false
    },
    hidePhotoAction: {
        type: Boolean,
        default: false
    },
    ordonnances: {
        type: Array,
        default: () => []
    },
    consultationReadonly: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits([
    'print-dossier',
    'edit',
    'new-rdv',
    'photo-selected',
    'add-antecedent',
    'add-allergy',
    'delete-antecedent',
    'delete-allergy',
    'create-portal-account',
    'reset-portal-password',
    'toggle-portal-active',
    'open-ordonnance',
    'view-ordonnance',
    'edit-ordonnance',
    'print-ordonnance'
]);

const photoInput = ref(null);

const referralLabels = {
    'Reseaux sociaux': 'Réseaux sociaux',
    'Bouche a oreille': 'Bouche à oreille',
    'Bouche a bouche': 'Bouche à oreille',
    Recommandation: 'Recommandation',
    'Par un medecin': 'Par un médecin',
    Publicite: 'Publicité',
    Autres: 'Autres'
};

const referencementLabel = computed(() => {
    const value = String(props.patient?.referencement || '').trim();
    if (!value) return '--';
    return referralLabels[value] || value;
});

const insuranceProfile = computed(() => props.patient?.insuranceProfile || null);
const insuranceName = computed(() => insuranceProfile.value?.assurance?.nom || insuranceProfile.value?.assurance?.code || 'Assurance');
const insuranceCoverageRate = computed(() => Number(insuranceProfile.value?.coverageRate ?? 0) || 0);
const insuranceFormDataEntries = computed(() => {
    const formData = insuranceProfile.value?.formData;
    if (!formData || typeof formData !== 'object') {
        return [];
    }

    const labels = {
        societe: 'Société',
        assureNom: 'Nom assuré',
        assureNumero: 'N° assuré',
        beneficiaireNom: 'Bénéficiaire',
        beneficiaireNumero: 'N° bénéficiaire',
        sexe: 'Sexe assuré',
        souscripteur: 'Souscripteur',
        salarieNomPrenom: 'Salarié',
        salarieMatricule: 'Matricule salarié',
        patientNomPrenom: 'Patient',
        patientMatricule: 'Matricule patient',
        patientAge: 'Âge patient',
        patientSexe: 'Sexe patient',
        carteNumero: 'Carte N°',
        numeroPolice: 'N° police',
        titulaireNomPrenoms: 'Titulaire',
        assurePrincipalNom: 'Assuré principal',
        assurePrincipalTel: 'Tél. assuré principal',
        avenant: 'Avenant',
        numeroAssure: 'N° assuré',
        assureNomPrenom: 'Nom et prénom',
        assureNomPrenoms: 'Assuré',
        beneficiaireNomPrenoms: 'Bénéficiaire',
        beneficiaireMatricule: 'Matricule bénéficiaire',
        identifiant: 'Identifiant',
        nomPrenoms: 'Nom et prénoms'
    };

    return Object.entries(formData)
        .filter(([, value]) => String(value || '').trim() !== '')
        .map(([key, value]) => ({ key, label: labels[key] || key, value: String(value) }));
});

const openPhotoPicker = () => {
    photoInput.value?.click();
};

const handlePhotoChange = (event) => {
    const [file] = event.target?.files || [];
    if (file) {
        emit('photo-selected', file);
    }
    event.target.value = '';
};
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                <i class="pi pi-user text-primary-500"></i>
                Informations Patient
            </h3>
        </div>
        <div class="p-5">
            <div class="flex flex-col items-center mb-6" data-tour="patients-dossier.identity">
                <input ref="photoInput" type="file" accept="image/*" class="hidden" @change="handlePhotoChange" />
                <div class="relative mb-4">
                    <PatientAvatar :patient="patient" :initials="patient.initials" size-class="w-24 h-24" text-class="text-3xl font-bold" alt="Photo du patient" class="shadow-lg" />
                    <Button v-if="!hidePhotoAction" icon="pi pi-pencil" rounded severity="secondary" class="!absolute -bottom-1 -right-1 !w-9 !h-9 shadow-md" @click="openPhotoPicker" />
                </div>
                <h2 class="text-xl font-bold text-surface-900 dark:text-surface-100">{{ patient.nom }} {{ patient.prenom }}</h2>
                <p class="text-surface-600 dark:text-surface-400">{{ patient.numeroDossier }}</p>
            </div>

            <div class="space-y-3" data-tour="patients-dossier.personal-details">
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Date de naissance</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.dateNaissance }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Lieu de naissance</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.lieuNaissance || '--' }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Âge</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.age }} ans</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Sexe</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.sexe }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Groupe sanguin</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.groupeSanguin }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Profession</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.profession || '--' }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                    <span class="text-surface-600 dark:text-surface-400">Référencement</span>
                    <span class="font-medium text-surface-900 dark:text-surface-100 text-right">{{ referencementLabel }}</span>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.contact">
                <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">Contact</h4>
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300">
                        <i class="pi pi-phone text-surface-400"></i>
                        {{ hidePhone ? "Masqué par l'administrateur" : patient.telephone || '--' }}
                    </div>
                    <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300">
                        <i class="pi pi-envelope text-surface-400"></i>
                        {{ patient.email }}
                    </div>
                    <div class="flex items-center gap-2 text-surface-700 dark:text-surface-300">
                        <i class="pi pi-map-marker text-surface-400"></i>
                        {{ patient.adresse }}
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.insurance">
                <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">Assurance</h4>
                <div v-if="insuranceProfile?.assurance" class="space-y-2">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                        <span class="text-surface-600 dark:text-surface-400">Organisme</span>
                        <span class="font-medium text-emerald-700 dark:text-emerald-300">{{ insuranceName }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Taux de couverture</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ insuranceCoverageRate }} %</span>
                    </div>
                    <div v-for="entry in insuranceFormDataEntries" :key="entry.key" class="flex items-center justify-between gap-4 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">{{ entry.label }}</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100 text-right">{{ entry.value }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Patient non assuré.</p>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.antecedents">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300">Antécédents médicaux</h4>
                    <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="emit('add-antecedent')" />
                </div>
                <div v-if="patient.antecedents?.length" class="space-y-2">
                    <div v-for="(item, idx) in patient.antecedents" :key="idx" class="flex items-start justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <div>
                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.type || 'Antécédent' }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-300">{{ item.description || '—' }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="text-xs text-surface-500 dark:text-surface-400">{{ item.date || item.dateEnregistrement || '--' }}</div>
                            <Button icon="pi pi-trash" severity="danger" text rounded @click="emit('delete-antecedent', item)" />
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun antécédent renseigné.</p>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.allergies">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300">Allergies</h4>
                    <Button icon="pi pi-plus" label="Ajouter" size="small" outlined @click="emit('add-allergy')" />
                </div>
                <div v-if="patient.allergies?.length" class="space-y-2">
                    <div v-for="(item, idx) in patient.allergies" :key="idx" class="flex items-start justify-between gap-3 p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <div>
                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.libelle || 'Allergie' }}</div>
                            <div class="text-sm text-surface-600 dark:text-surface-300">{{ item.description || '—' }}</div>
                        </div>
                        <Button icon="pi pi-trash" severity="danger" text rounded @click="emit('delete-allergy', item)" />
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucune allergie renseignée.</p>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.ordonnances">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300">Ordonnances</h4>
                    <Button v-if="!consultationReadonly" icon="pi pi-plus" label="Nouvelle" size="small" outlined @click="emit('open-ordonnance')" />
                </div>

                <div v-if="ordonnances?.length" class="space-y-2">
                    <div v-for="ordo in ordonnances" :key="ordo.id || `${ordo.date}-${ordo.medecinNom}`" class="rounded-xl border border-surface-200/80 dark:border-surface-700/80 bg-surface-50/80 dark:bg-surface-800/40 p-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-surface-900 dark:text-surface-100 truncate">
                                    {{ ordo.date || '—' }}
                                </div>
                                <div class="text-xs text-surface-500 dark:text-surface-400 truncate">{{ ordo.medecinNom || ordo.medecin || '—' }} · {{ ordo.lignes?.length || 0 }} ligne(s)</div>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <Button icon="pi pi-eye" label="Voir" size="small" text class="!px-2 !py-1" @click="emit('view-ordonnance', ordo)" />
                            <Button icon="pi pi-pencil" label="Modifier" size="small" text class="!px-2 !py-1" @click="emit('edit-ordonnance', ordo)" />
                            <Button icon="pi pi-print" label="Imprimer" size="small" text class="!px-2 !py-1" @click="emit('print-ordonnance', ordo)" />
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucune ordonnance pour cette consultation.</p>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.emergency-contact">
                <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 mb-3">Contact d'urgence</h4>
                <div v-if="patient.contactUrgence" class="space-y-2">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Nom</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.contactUrgence.nom || '--' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Lien</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.contactUrgence.lienParente || '--' }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Téléphone</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.contactUrgence.telephone || '--' }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun contact d'urgence renseigné.</p>
            </div>

            <div class="mt-6 pt-6 border-t border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.portal-account">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300">Compte espace patient</h4>
                    <Button v-if="!patient.portalAccount" icon="pi pi-user-plus" label="Créer" size="small" outlined @click="emit('create-portal-account')" />
                </div>

                <div v-if="patient.portalAccount" class="space-y-2">
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Identifiant</span>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ patient.portalAccount.username }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-surface-50 dark:bg-surface-700/50">
                        <span class="text-surface-600 dark:text-surface-400">Statut</span>
                        <span :class="patient.portalAccount.active ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="font-medium">
                            {{ patient.portalAccount.active ? 'Actif' : 'Désactivé' }}
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <Button icon="pi pi-key" label="Mot de passe = 123" size="small" outlined @click="emit('reset-portal-password')" />
                        <Button
                            :icon="patient.portalAccount.active ? 'pi pi-user-minus' : 'pi pi-user-plus'"
                            :label="patient.portalAccount.active ? 'Désactiver' : 'Activer'"
                            size="small"
                            :severity="patient.portalAccount.active ? 'danger' : 'success'"
                            @click="emit('toggle-portal-active', !patient.portalAccount.active)"
                        />
                    </div>
                </div>

                <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun compte lié. Cliquez sur Créer pour générer un identifiant (mot de passe par défaut: 123).</p>
            </div>
        </div>

        <!-- MOBILE (Visible de 0px à 640px, caché après) -->
        <div v-if="!hideActions" data-tour="patients-dossier.actions" class="px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-900/50">
            <!-- DESKTOP (Caché sur mobile, visible à partir de sm: 640px) -->
            <div class="hidden sm:flex flex-wrap gap-2">
                <Button icon="pi pi-print" label="Imprimer dossier" severity="secondary" outlined class="flex-1" @click="emit('print-dossier')" />
                <Button icon="pi pi-pencil" label="Modifier" severity="secondary" outlined class="flex-1" @click="emit('edit')" />
                <Button icon="pi pi-plus" label="Nouveau RDV" severity="primary" class="flex-1 bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="emit('new-rdv')" />
            </div>

            <!-- MOBILE (Visible sur mobile, caché dès 640px) -->
            <div class="flex sm:hidden flex-wrap gap-2">
                <Button icon="pi pi-print" severity="secondary" outlined class="flex-1" @click="emit('print-dossier')" />
                <Button icon="pi pi-pencil" severity="secondary" outlined class="flex-1" @click="emit('edit')" />
                <Button icon="pi pi-plus" label="RDV" severity="primary" class="flex-1 bg-gradient-to-r from-primary-500 to-primary-600 border-0" @click="emit('new-rdv')" />
            </div>
        </div>
    </div>
</template>
