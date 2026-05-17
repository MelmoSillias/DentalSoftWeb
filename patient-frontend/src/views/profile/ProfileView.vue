<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { fetchPatientProfile } from '../../services/patientPortal';
import cabinetConfig from '../../cabinetConfig';
import AppLayout from '../../layout/AppLayout.vue';

const authStore = useAuthStore();
const user = computed(() => authStore.user || { name: 'Patient', email: '-' });

const profileData = ref(null);
const loading = ref(true);

onMounted(async () => {
    try {
        profileData.value = await fetchPatientProfile(authStore.token);
    } catch {
        // fallback silencieux
    } finally {
        loading.value = false;
    }
});

const patient = computed(() => profileData.value?.patient || null);
const assurance = computed(() => profileData.value?.assurance || null);
const displayName = computed(() => {
    if (patient.value) return `${patient.value.prenom} ${patient.value.nom}`.trim();
    return user.value.name;
});
const displayEmail = computed(() => patient.value?.email || user.value.email || '-');
</script>

<template>
    <AppLayout>
        <div class="profile-page">
            <div class="profile-header">
                <div class="avatar-wrap">
                    <Avatar icon="pi pi-user" shape="circle" size="xlarge" class="profile-avatar" />
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
                        @error="$event.target.style.display='none'"
                    />
                    <span class="cabinet-label">{{ cabinetConfig.displayName }}</span>
                </div>
            </div>

            <div v-if="loading" class="profile-section">
                <Skeleton height="1rem" class="mb-2" />
                <Skeleton height="1rem" width="70%" />
            </div>

            <div v-else-if="patient" class="profile-section">
                <p class="section-title">Informations personnelles</p>
                <div class="info-row">
                    <span class="info-label"><i class="pi pi-user" /> Nom complet</span>
                    <span class="info-value">{{ patient.prenom }} {{ patient.nom }}</span>
                </div>
                <div v-if="patient.telephone" class="info-row">
                    <span class="info-label"><i class="pi pi-phone" /> Téléphone</span>
                    <span class="info-value">{{ patient.telephone }}</span>
                </div>
                <div v-if="patient.email" class="info-row">
                    <span class="info-label"><i class="pi pi-envelope" /> Email</span>
                    <span class="info-value">{{ patient.email }}</span>
                </div>
                <div v-if="patient.dateNaissance" class="info-row">
                    <span class="info-label"><i class="pi pi-calendar" /> Date de naissance</span>
                    <span class="info-value">{{ patient.dateNaissance }}</span>
                </div>
                <div v-if="patient.sexe" class="info-row">
                    <span class="info-label"><i class="pi pi-user" /> Sexe</span>
                    <span class="info-value">{{ patient.sexe }}</span>
                </div>
                <div v-if="patient.adresse" class="info-row">
                    <span class="info-label"><i class="pi pi-map-marker" /> Adresse</span>
                    <span class="info-value">{{ patient.adresse }}</span>
                </div>
                <div v-if="patient.numCarnet" class="info-row">
                    <span class="info-label"><i class="pi pi-id-card" /> N° carnet</span>
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
                    <span class="info-label"><i class="pi pi-envelope" /> Email</span>
                    <span class="info-value">{{ user.email }}</span>
                </div>
            </div>

            <!-- Section assurance -->
            <div v-if="assurance" class="profile-section assurance-section">
                <p class="section-title"><i class="pi pi-shield" style="margin-right:0.35rem;color:#2563eb" />Couverture assurance</p>
                <div class="info-row">
                    <span class="info-label"><i class="pi pi-building" /> Assurance</span>
                    <span class="info-value assurance-badge">{{ assurance.nom }}</span>
                </div>
                <div v-if="assurance.code" class="info-row">
                    <span class="info-label"><i class="pi pi-tag" /> Code</span>
                    <span class="info-value">{{ assurance.code }}</span>
                </div>
                <div v-if="assurance.coverageRate != null" class="info-row">
                    <span class="info-label"><i class="pi pi-percentage" /> Taux de couverture</span>
                    <span class="info-value coverage-rate">{{ assurance.coverageRate }}%</span>
                </div>
                <!-- Données spécifiques du formulaire assurance -->
                <template v-if="assurance.formData && Object.keys(assurance.formData).length">
                    <div v-for="(val, key) in assurance.formData" :key="key" class="info-row">
                        <span class="info-label">{{ key }}</span>
                        <span class="info-value">{{ val }}</span>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.profile-page {
    display: grid;
    gap: 1rem;
}

.profile-header {
    background: var(--pp-gradient);
    border-radius: var(--pp-radius);
    padding: 1.75rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    box-shadow: 0 4px 16px rgba(30,58,138,0.2);
    flex-wrap: wrap;
}

.cabinet-chip {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 0.45rem;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 999px;
    padding: 0.3rem 0.75rem 0.3rem 0.4rem;
}

.cabinet-logo {
    height: 1.6rem;
    width: auto;
    border-radius: 6px;
    object-fit: contain;
}

.cabinet-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    white-space: nowrap;
}

.avatar-wrap {
    flex-shrink: 0;
}

.profile-avatar {
    background: rgba(255,255,255,0.25) !important;
    color: #fff !important;
    border: 3px solid rgba(255,255,255,0.4);
    width: 4rem !important;
    height: 4rem !important;
    font-size: 1.5rem !important;
}

.profile-name {
    margin: 0 0 0.25rem;
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
}

.profile-email {
    margin: 0;
    font-size: 0.875rem;
    color: rgba(255,255,255,0.75) !important;
}

.profile-section {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    padding: 1rem 1.2rem;
    display: grid;
    gap: 0.7rem;
}

.assurance-section {
    border-left: 4px solid #2563eb;
}

.section-title {
    margin: 0 0 0.2rem;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--p-text-muted-color);
    display: flex;
    align-items: center;
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid var(--p-surface-100);
}

.info-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.info-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--p-text-muted-color);
}

.info-label i {
    font-size: 0.8rem;
}

.info-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--p-text-color);
    text-align: right;
}

.assurance-badge {
    background: #eff6ff;
    color: #2563eb;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.82rem;
}

.coverage-rate {
    color: #16a34a;
    background: #f0fdf4;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    font-size: 0.82rem;
}
</style>
