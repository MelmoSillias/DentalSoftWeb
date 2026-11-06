<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { fetchPatientProfile } from '../../services/patientPortal';
import cabinetConfig from '../../cabinetConfig';

const authStore = useAuthStore();
const user = computed(() => authStore.user || { name: 'Patient', email: '-' });

const profileData = ref(null);
const loading = ref(true);
const loadError = ref('');

onMounted(async () => {
    try {
        profileData.value = await fetchPatientProfile(authStore.token);
    } catch {
        loadError.value =
            'Votre profil complet est indisponible pour le moment. Les informations de votre compte restent accessibles.';
    } finally {
        loading.value = false;
    }
});

const patient = computed(() => profileData.value?.patient || null);
const assurance = computed(() => profileData.value?.assurance || null);
const displayName = computed(() => {
    if (patient.value)
        return `${patient.value.prenom} ${patient.value.nom}`.trim();
    return user.value.name;
});
const displayEmail = computed(
    () => patient.value?.email || user.value.email || '-',
);
</script>

<template>
    <div class="profile-page">
        <Message v-if="loadError" severity="warn" :closable="false">{{
            loadError
        }}</Message>
        <div class="profile-header">
            <div class="avatar-wrap">
                <Avatar
                    icon="pi pi-user"
                    shape="circle"
                    size="xlarge"
                    class="profile-avatar"
                />
            </div>
            <div class="profile-info">
                <h2 class="profile-name">{{ displayName }}</h2>
                <p class="muted profile-email">{{ displayEmail }}</p>
            </div>
            <div class="cabinet-chip">
                <img
                    :src="'/' + (cabinetConfig.logo || 'logo.png')"
                    :alt="cabinetConfig.displayName"
                    class="cabinet-logo"
                    @error="$event.target.style.display = 'none'"
                />
                <span class="cabinet-label">{{
                    cabinetConfig.displayName
                }}</span>
            </div>
        </div>

        <div v-if="loading" class="profile-section">
            <Skeleton height="1rem" class="mb-2" />
            <Skeleton height="1rem" width="70%" />
        </div>

        <div v-else-if="patient" class="profile-section">
            <p class="section-title">Informations personnelles</p>
            <div class="info-row">
                <span class="info-label"
                    ><i class="pi pi-user" /> Nom complet</span
                >
                <span class="info-value"
                    >{{ patient.prenom }} {{ patient.nom }}</span
                >
            </div>
            <div v-if="patient.telephone" class="info-row">
                <span class="info-label"
                    ><i class="pi pi-phone" /> Téléphone</span
                >
                <span class="info-value">{{ patient.telephone }}</span>
            </div>
            <div v-if="patient.email" class="info-row">
                <span class="info-label"
                    ><i class="pi pi-envelope" /> Email</span
                >
                <span class="info-value">{{ patient.email }}</span>
            </div>
            <div v-if="patient.dateNaissance" class="info-row">
                <span class="info-label"
                    ><i class="pi pi-calendar" /> Date de naissance</span
                >
                <span class="info-value">{{ patient.dateNaissance }}</span>
            </div>
            <div v-if="patient.sexe" class="info-row">
                <span class="info-label"
                    ><i class="pi pi-user" /> Sexe</span
                >
                <span class="info-value">{{ patient.sexe }}</span>
            </div>
            <div v-if="patient.adresse" class="info-row">
                <span class="info-label"
                    ><i class="pi pi-map-marker" /> Adresse</span
                >
                <span class="info-value">{{ patient.adresse }}</span>
            </div>
            <div v-if="patient.numCarnet" class="info-row">
                <span class="info-label"
                    ><i class="pi pi-id-card" /> N° carnet</span
                >
                <span class="info-value">{{ patient.numCarnet }}</span>
            </div>
        </div>

        <div v-else class="profile-section">
            <p class="section-title">Informations du compte</p>
            <div class="info-row">
                <span class="info-label"><i class="pi pi-user" /> Nom</span>
                <span class="info-value">{{ user.name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label"
                    ><i class="pi pi-envelope" /> Email</span
                >
                <span class="info-value">{{ user.email }}</span>
            </div>
        </div>

        <!-- Section assurance -->
        <div v-if="assurance" class="profile-section assurance-section">
            <p class="section-title">
                <i
                    class="pi pi-shield profile-verified-icon"
                />Couverture assurance
            </p>
            <div class="info-row">
                <span class="info-label"
                    ><i class="pi pi-building" /> Assurance</span
                >
                <span class="info-value assurance-badge">{{
                    assurance.nom
                }}</span>
            </div>
            <div v-if="assurance.code" class="info-row">
                <span class="info-label"><i class="pi pi-tag" /> Code</span>
                <span class="info-value">{{ assurance.code }}</span>
            </div>
            <div v-if="assurance.coverageRate != null" class="info-row">
                <span class="info-label"
                    ><i class="pi pi-percentage" /> Taux de couverture</span
                >
                <span class="info-value coverage-rate"
                    >{{ assurance.coverageRate }}%</span
                >
            </div>
            <!-- Données spécifiques du formulaire assurance -->
            <template
                v-if="
                    assurance.formData &&
                    Object.keys(assurance.formData).length
                "
            >
                <div
                    v-for="(val, key) in assurance.formData"
                    :key="key"
                    class="info-row"
                >
                    <span class="info-label">{{ key }}</span>
                    <span class="info-value">{{ val }}</span>
                </div>
            </template>
        </div>
    </div>

</template>
