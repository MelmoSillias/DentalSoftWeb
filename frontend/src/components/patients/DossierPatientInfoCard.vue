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
    /** Mode focus / panneau intégré : pas d'entête, pas d'ombre, pas de bordure ni d'arrondi */
    flat: {
        type: Boolean,
        default: false
    },
    /** @deprecated utiliser `flat` */
    embedded: {
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

const isFlat = computed(() => Boolean(props.flat || props.embedded));

const cardRootClass = computed(() =>
    isFlat.value
        ? 'dossier-patient-info-card dossier-patient-info-card--flat'
        : 'dossier-patient-info-card page-section'
);

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
    <div :class="cardRootClass">
        <div v-if="!isFlat" class="page-section__header">
            <h3 class="page-section__title">Informations patient</h3>
        </div>

        <div class="dossier-patient-info-card__body">
            <div class="dossier-patient-info-card__identity" data-tour="patients-dossier.identity">
                <input ref="photoInput" type="file" accept="image/*" class="hidden" @change="handlePhotoChange" />
                <div class="dossier-patient-info-card__avatar">
                    <PatientAvatar
                        :patient="patient"
                        :initials="patient.initials"
                        size-class="w-20 h-20"
                        text-class="text-2xl font-semibold"
                        alt="Photo du patient"
                        :elevated="false"
                    />
                    <Button v-if="!hidePhotoAction" icon="pi pi-pencil" rounded severity="secondary" text size="small" class="dossier-patient-info-card__photo-btn" @click="openPhotoPicker" />
                </div>
                <div class="min-w-0">
                    <h2 class="dossier-patient-info-card__name">{{ patient.nom }} {{ patient.prenom }}</h2>
                    <p class="dossier-patient-info-card__dossier-no">{{ patient.numeroDossier }}</p>
                </div>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.personal-details">
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Date de naissance</span>
                    <span class="dossier-field-row__value">{{ patient.dateNaissance }}</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Lieu de naissance</span>
                    <span class="dossier-field-row__value">{{ patient.lieuNaissance || '--' }}</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Âge</span>
                    <span class="dossier-field-row__value">{{ patient.age }} ans</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Sexe</span>
                    <span class="dossier-field-row__value">{{ patient.sexe }}</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Groupe sanguin</span>
                    <span class="dossier-field-row__value">{{ patient.groupeSanguin }}</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Profession</span>
                    <span class="dossier-field-row__value">{{ patient.profession || '--' }}</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Référencement</span>
                    <span class="dossier-field-row__value">{{ referencementLabel }}</span>
                </div>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.contact">
                <h4 class="dossier-section-label">Contact</h4>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Téléphone</span>
                    <span class="dossier-field-row__value">{{ hidePhone ? "Masqué par l'administrateur" : patient.telephone || '--' }}</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Email</span>
                    <span class="dossier-field-row__value">{{ patient.email || '--' }}</span>
                </div>
                <div class="dossier-field-row">
                    <span class="dossier-field-row__label">Adresse</span>
                    <span class="dossier-field-row__value">{{ patient.adresse || '--' }}</span>
                </div>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.insurance">
                <h4 class="dossier-section-label">Assurance</h4>
                <template v-if="insuranceProfile?.assurance">
                    <div class="dossier-field-row">
                        <span class="dossier-field-row__label">Organisme</span>
                        <span class="dossier-field-row__value">
                            <span class="dossier-tag dossier-tag--accent">{{ insuranceName }}</span>
                        </span>
                    </div>
                    <div class="dossier-field-row">
                        <span class="dossier-field-row__label">Taux de couverture</span>
                        <span class="dossier-field-row__value">{{ insuranceCoverageRate }} %</span>
                    </div>
                    <div v-for="entry in insuranceFormDataEntries" :key="entry.key" class="dossier-field-row">
                        <span class="dossier-field-row__label">{{ entry.label }}</span>
                        <span class="dossier-field-row__value">{{ entry.value }}</span>
                    </div>
                </template>
                <p v-else class="dossier-state__text" style="text-align: left">Patient non assuré.</p>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.antecedents">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h4 class="dossier-section-label mb-0">Antécédents médicaux</h4>
                    <Button icon="pi pi-plus" label="Ajouter" size="small" text @click="emit('add-antecedent')" />
                </div>
                <div v-if="patient.antecedents?.length">
                    <div v-for="(item, idx) in patient.antecedents" :key="idx" class="dossier-list-item">
                        <div class="min-w-0">
                            <div class="font-medium" style="font-size: var(--page-section-subtitle-size); color: var(--text-color)">{{ item.type || 'Antécédent' }}</div>
                            <div style="font-size: var(--page-kpi-meta-size); color: var(--text-color-secondary)">{{ item.description || '—' }}</div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="dossier-tag">{{ item.date || item.dateEnregistrement || '--' }}</span>
                            <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="emit('delete-antecedent', item)" />
                        </div>
                    </div>
                </div>
                <p v-else class="dossier-state__text" style="text-align: left">Aucun antécédent renseigné.</p>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.allergies">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h4 class="dossier-section-label mb-0">Allergies</h4>
                    <Button icon="pi pi-plus" label="Ajouter" size="small" text @click="emit('add-allergy')" />
                </div>
                <div v-if="patient.allergies?.length">
                    <div v-for="(item, idx) in patient.allergies" :key="idx" class="dossier-list-item">
                        <div class="min-w-0">
                            <div class="font-medium" style="font-size: var(--page-section-subtitle-size); color: var(--text-color)">{{ item.libelle || 'Allergie' }}</div>
                            <div style="font-size: var(--page-kpi-meta-size); color: var(--text-color-secondary)">{{ item.description || '—' }}</div>
                        </div>
                        <Button icon="pi pi-trash" severity="danger" text rounded size="small" @click="emit('delete-allergy', item)" />
                    </div>
                </div>
                <p v-else class="dossier-state__text" style="text-align: left">Aucune allergie renseignée.</p>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.ordonnances">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h4 class="dossier-section-label mb-0">Ordonnances</h4>
                    <Button v-if="!consultationReadonly" icon="pi pi-plus" label="Nouvelle" size="small" text @click="emit('open-ordonnance')" />
                </div>

                <div v-if="ordonnances?.length">
                    <div v-for="ordo in ordonnances" :key="ordo.id || `${ordo.date}-${ordo.medecinNom}`" class="dossier-list-item">
                        <div class="min-w-0">
                            <div class="font-medium" style="font-size: var(--page-section-subtitle-size); color: var(--text-color)">{{ ordo.date || '—' }}</div>
                            <div style="font-size: var(--page-kpi-meta-size); color: var(--text-color-secondary)">{{ ordo.medecinNom || ordo.medecin || '—' }} · {{ ordo.lignes?.length || 0 }} ligne(s)</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <Button icon="pi pi-eye" label="Voir" size="small" text class="!px-2 !py-0" @click="emit('view-ordonnance', ordo)" />
                                <Button icon="pi pi-pencil" label="Modifier" size="small" text class="!px-2 !py-0" @click="emit('edit-ordonnance', ordo)" />
                                <Button icon="pi pi-print" label="Imprimer" size="small" text class="!px-2 !py-0" @click="emit('print-ordonnance', ordo)" />
                            </div>
                        </div>
                    </div>
                </div>
                <p v-else class="dossier-state__text" style="text-align: left">Aucune ordonnance pour cette consultation.</p>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.emergency-contact">
                <h4 class="dossier-section-label">Contact d'urgence</h4>
                <template v-if="patient.contactUrgence">
                    <div class="dossier-field-row">
                        <span class="dossier-field-row__label">Nom</span>
                        <span class="dossier-field-row__value">{{ patient.contactUrgence.nom || '--' }}</span>
                    </div>
                    <div class="dossier-field-row">
                        <span class="dossier-field-row__label">Lien</span>
                        <span class="dossier-field-row__value">{{ patient.contactUrgence.lienParente || '--' }}</span>
                    </div>
                    <div class="dossier-field-row">
                        <span class="dossier-field-row__label">Téléphone</span>
                        <span class="dossier-field-row__value">{{ patient.contactUrgence.telephone || '--' }}</span>
                    </div>
                </template>
                <p v-else class="dossier-state__text" style="text-align: left">Aucun contact d'urgence renseigné.</p>
            </div>

            <div class="dossier-section-block" data-tour="patients-dossier.portal-account">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h4 class="dossier-section-label mb-0">Compte espace patient</h4>
                    <Button v-if="!patient.portalAccount" icon="pi pi-user-plus" label="Créer" size="small" text @click="emit('create-portal-account')" />
                </div>

                <template v-if="patient.portalAccount">
                    <div class="dossier-field-row">
                        <span class="dossier-field-row__label">Identifiant</span>
                        <span class="dossier-field-row__value">{{ patient.portalAccount.username }}</span>
                    </div>
                    <div class="dossier-field-row">
                        <span class="dossier-field-row__label">Statut</span>
                        <span class="dossier-field-row__value">
                            <span :class="patient.portalAccount.active ? 'dossier-tag dossier-tag--success' : 'dossier-tag dossier-tag--danger'">
                                {{ patient.portalAccount.active ? 'Actif' : 'Désactivé' }}
                            </span>
                        </span>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-2">
                        <Button icon="pi pi-key" label="Mot de passe = 123" size="small" outlined @click="emit('reset-portal-password')" />
                        <Button
                            :icon="patient.portalAccount.active ? 'pi pi-user-minus' : 'pi pi-user-plus'"
                            :label="patient.portalAccount.active ? 'Désactiver' : 'Activer'"
                            size="small"
                            :severity="patient.portalAccount.active ? 'danger' : 'success'"
                            outlined
                            @click="emit('toggle-portal-active', !patient.portalAccount.active)"
                        />
                    </div>
                </template>
                <p v-else class="dossier-state__text" style="text-align: left">Aucun compte lié. Cliquez sur Créer pour générer un identifiant (mot de passe par défaut: 123).</p>
            </div>
        </div>

        <div v-if="!hideActions" data-tour="patients-dossier.actions" class="dossier-patient-info-card__actions">
            <div class="hidden sm:flex flex-wrap gap-2">
                <Button icon="pi pi-print" label="Imprimer dossier" severity="secondary" outlined class="flex-1" @click="emit('print-dossier')" />
                <Button icon="pi pi-pencil" label="Modifier" severity="secondary" outlined class="flex-1" @click="emit('edit')" />
                <Button icon="pi pi-plus" label="Nouveau RDV" class="flex-1" @click="emit('new-rdv')" />
            </div>
            <div class="flex sm:hidden flex-wrap gap-2">
                <Button icon="pi pi-print" severity="secondary" outlined class="flex-1" @click="emit('print-dossier')" />
                <Button icon="pi pi-pencil" severity="secondary" outlined class="flex-1" @click="emit('edit')" />
                <Button icon="pi pi-plus" label="RDV" class="flex-1" @click="emit('new-rdv')" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.dossier-patient-info-card--flat {
    margin: 0;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    background: transparent;
}

.dossier-patient-info-card__body {
    padding: 0.75rem 0.875rem 1rem;
}

.dossier-patient-info-card--flat .dossier-patient-info-card__body {
    padding: 0;
}

.dossier-patient-info-card__identity {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    margin-bottom: 0.25rem;
}

.dossier-patient-info-card__avatar {
    position: relative;
    flex-shrink: 0;
}

.dossier-patient-info-card__photo-btn {
    position: absolute !important;
    right: -0.25rem;
    bottom: -0.25rem;
    width: 1.75rem !important;
    height: 1.75rem !important;
}

.dossier-patient-info-card__name {
    margin: 0;
    font-size: var(--page-section-title-size);
    font-weight: 600;
    line-height: 1.3;
    color: var(--text-color);
}

.dossier-patient-info-card__dossier-no {
    margin: 0.15rem 0 0;
    font-size: var(--page-section-subtitle-size);
    color: var(--text-color-secondary);
}

.dossier-patient-info-card__actions {
    padding: 0.625rem 0.875rem;
    border-top: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    background: color-mix(in srgb, var(--surface-card) 92%, var(--text-color) 4%);
}
</style>
