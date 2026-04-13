<script setup>
import ConsultationEnCoursForm from '@/components/consultations/ConsultationEnCoursForm.vue';
import { fetchConsultationDetails, setConsultationFiche, verifyConsultationMedecinPassword } from '@/services/consultations';
import { isConsultationsTourMockEnabled } from '@/services/consultationsTourMock';
import { closeConsultation, saveConsultation } from '@/services/consultationsforms';
import { fetchMedecins, fetchInfirmiers } from '@/services/corpsmedical';
import { fetchSalles } from '@/services/salles';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Password from 'primevue/password';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    consultation: {
        type: Object,
        default: null
    },
    actionMode: {
        type: String,
        default: 'continue'
    },
    tourTarget: {
        type: String,
        default: null
    }
});

const emit = defineEmits(['update:visible', 'saved', 'closed']);

const toast = useToast();
const auth = useAuthStore();
const token = localStorage.getItem('token');

const visibleProxy = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const loading = ref(false);
const saving = ref(false);
const clotureLoading = ref(false);
const verifyLoading = ref(false);
const ficheId = ref(null);

const medecins = ref([]);
const infirmiers = ref([]);
const salles = ref([]);

const doctorPassword = ref('');
const passwordValidated = ref(false);

const form = ref({
    type: '',
    medecinId: null,
    infirmierIds: [],
    salleId: null,
    noteSeance: '',
    actes: []
});

const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const isReception = computed(() => Boolean(auth.user?.roles?.includes('ROLE_RECEPTION') || auth.user?.roles?.includes('ROLE_RECEPTIONNISTE')));

const requiresDoctorPassword = computed(() => !isConsultationsTourMockEnabled() && isReception.value && !isAdmin.value && !isMedecin.value);
const canAccessForm = computed(() => !requiresDoctorPassword.value || passwordValidated.value);
const hasSelectedMedecin = computed(() => Number.isFinite(Number(form.value?.medecinId)) && Number(form.value?.medecinId) > 0);

const patientLabel = computed(() => {
    const p = props.consultation?.patient;
    if (typeof p === 'string' && p.trim()) return p;
    const fromName = props.consultation?.patientName;
    if (typeof fromName === 'string' && fromName.trim()) return fromName;
    return 'Patient';
});

const actionLabel = computed(() => {
    if (props.actionMode === 'continue-last') return 'Continuer avec la dernière fiche';
    if (props.actionMode === 'new-fiche') return 'Nouvelle fiche';
    return 'Continuer';
});

const normalizeText = (value) =>
    String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

const resolveMedecinFallbackId = () => {
    const fullName = props.consultation?.medecin;
    if (typeof fullName === 'string' && fullName.trim()) {
        const target = normalizeText(fullName);
        const found = (medecins.value || []).find((m) => normalizeText(m.label) === target);
        if (found?.id) return found.id;
    }

    const user = auth.user || {};
    const directId = Number(user.medecinId ?? user.medecin_id ?? user.medecin?.id ?? Number.NaN);
    if (Number.isFinite(directId)) return directId;

    const userName = [user.prenom, user.nom].filter(Boolean).join(' ').trim();
    const candidates = [userName, user.name, user.fullName, user.username]
        .filter(Boolean)
        .map(normalizeText);

    const foundByName = (medecins.value || []).find((m) => {
        const label = normalizeText(m.label);
        return candidates.some((candidate) => candidate && (label === candidate || label.includes(candidate) || candidate.includes(label)));
    });

    return foundByName?.id ?? null;
};

const isLinked = (consultation) => Boolean(consultation?.ficheId);

const resolveTargetFicheId = (consultation, mode) => {
    if (!consultation) return null;
    if (mode === 'new-fiche') return null;
    if (mode === 'continue-last') {
        return consultation.lastFicheId || null;
    }
    if (mode === 'continue') {
        return isLinked(consultation) ? consultation.ficheId : null;
    }
    return null;
};

const loadQuickData = async () => {
    const consultation = props.consultation;
    if (!consultation?.id) return;

    loading.value = true;
    doctorPassword.value = '';
    passwordValidated.value = !requiresDoctorPassword.value;

    try {
        const [meds, infs, salleItems] = await Promise.all([
            fetchMedecins(token),
            fetchInfirmiers(token),
            fetchSalles(token)
        ]);

        medecins.value = meds || [];
        infirmiers.value = infs || [];
        salles.value = salleItems || [];

        const targetFicheId = resolveTargetFicheId(consultation, props.actionMode);
        const linked = await setConsultationFiche(consultation.id, targetFicheId, token);
        ficheId.value = linked?.ficheId ?? null;

        const details = await fetchConsultationDetails(consultation.id, token);
        const fallbackMedecinId = resolveMedecinFallbackId();

        form.value = {
            type: details?.type ?? '',
            medecinId: details?.medecinId ?? fallbackMedecinId,
            infirmierIds: details?.infirmierId ? [details.infirmierId] : [],
            salleId: details?.salleId ?? null,
            noteSeance: details?.noteSeance ?? '',
            actes: Array.isArray(details?.actes) ? details.actes : []
        };
    } catch (error) {
        console.error('Erreur chargement clôturation rapide', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de préparer la clôturation rapide.', life: 3000 });
        visibleProxy.value = false;
    } finally {
        loading.value = false;
    }
};

const buildPayload = () => ({
    ...form.value,
    medecinId: form.value?.medecinId ? Number(form.value.medecinId) : null,
    infirmierId: Array.isArray(form.value.infirmierIds) ? form.value.infirmierIds[0] ?? null : form.value.infirmierIds
});

const handleSave = async ({ silent = false } = {}) => {
    if (!props.consultation?.id || !ficheId.value || !canAccessForm.value) return false;
    if (!hasSelectedMedecin.value) {
        toast.add({ severity: 'warn', summary: 'Médecin requis', detail: 'Veuillez sélectionner un médecin avant la sauvegarde.', life: 3000 });
        return false;
    }

    saving.value = true;
    try {
        await saveConsultation(ficheId.value, props.consultation.id, buildPayload(), token);
        if (!silent) {
            toast.add({ severity: 'success', summary: 'Consultation sauvegardée', detail: 'Données enregistrées.', life: 2200 });
            emit('saved');
        }
        return true;
    } catch (error) {
        console.error('Erreur sauvegarde rapide', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de sauvegarder la consultation.', life: 3000 });
        return false;
    } finally {
        saving.value = false;
    }
};

const handleCloture = async () => {
    const consultationId = Number(props.consultation?.id);
    const currentFicheId = ficheId.value;
    if (!Number.isFinite(consultationId) || consultationId <= 0 || !currentFicheId || !canAccessForm.value) return;

    clotureLoading.value = true;
    try {
        const saved = await handleSave({ silent: true });
        if (!saved) return;

        await closeConsultation(currentFicheId, consultationId, token);
        toast.add({ severity: 'success', summary: 'Consultation clôturée', detail: 'Clôture rapide effectuée.', life: 2500 });
        emit('closed');
        visibleProxy.value = false;
    } catch (error) {
        console.error('Erreur clôture rapide', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de clôturer la consultation.', life: 3000 });
    } finally {
        clotureLoading.value = false;
    }
};

const handleDialogHide = () => {
    doctorPassword.value = '';
    passwordValidated.value = !requiresDoctorPassword.value;
    emit('update:visible', false);
};

const verifyDoctorPassword = async () => {
    if (!props.consultation?.id || !doctorPassword.value) return;

    verifyLoading.value = true;
    try {
        const valid = await verifyConsultationMedecinPassword(props.consultation.id, doctorPassword.value, token);
        if (!valid) {
            toast.add({ severity: 'warn', summary: 'Mot de passe invalide', detail: 'Veuillez réessayer.', life: 2500 });
            return;
        }

        passwordValidated.value = true;
        toast.add({ severity: 'success', summary: 'Accès autorisé', detail: 'Vous pouvez clôturer la consultation.', life: 2000 });
    } catch (error) {
        console.error('Erreur vérification mot de passe médecin', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Vérification du mot de passe impossible.', life: 3000 });
    } finally {
        verifyLoading.value = false;
    }
};

watch(
    () => props.visible,
    async (visible) => {
        if (!visible) return;
        await loadQuickData();
    }
);
</script>

<template>
    <Dialog
        v-model:visible="visibleProxy"
        modal
        :dismissable-mask="false"
        :closable="!saving && !clotureLoading"
        @hide="handleDialogHide"
        :style="{ width: '92vw', maxWidth: '1100px' }"
        :pt="{
            root: 'rounded-2xl',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-4 md:p-6'
        }"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-primary-100 dark:bg-primary-900/30">
                    <i class="pi pi-bolt text-primary-600 dark:text-primary-400"></i>
                </div>
                <div>
                    <h4 class="m-0 text-surface-900 dark:text-surface-100">Clôturation rapide</h4>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">{{ actionLabel }} · {{ patientLabel }}</p>
                </div>
            </div>
        </template>

        <div :data-tour="props.tourTarget || null">
            <div v-if="loading" class="flex min-h-[16rem] flex-col items-center justify-center gap-3">
                <ProgressSpinner strokeWidth="4" style="width: 44px; height: 44px" />
                <p class="text-sm text-surface-500 dark:text-surface-400">Préparation de la consultation...</p>
            </div>

            <template v-else>
                <div v-if="requiresDoctorPassword && !passwordValidated" class="rounded-xl border border-amber-200 dark:border-amber-700 bg-amber-50/60 dark:bg-amber-900/20 p-4 md:p-5 mb-5">
                    <div class="flex items-start gap-3">
                        <i class="pi pi-shield text-amber-600 mt-1"></i>
                        <div class="flex-1 space-y-3">
                            <p class="text-sm text-amber-900 dark:text-amber-100 font-medium">
                                Validation médecin requise avant accès à la clôturation.
                            </p>
                            <Password
                                v-model="doctorPassword"
                                placeholder="Mot de passe du médecin pré-sélectionné"
                                :feedback="false"
                                toggleMask
                                class="w-full"
                                inputClass="w-full"
                                @keyup.enter="verifyDoctorPassword"
                            />
                            <div class="flex justify-end">
                                <Button
                                    label="Vérifier"
                                    icon="pi pi-check"
                                    :loading="verifyLoading"
                                    :disabled="!doctorPassword"
                                    class="rounded-xl"
                                    @click="verifyDoctorPassword"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <ConsultationEnCoursForm
                    v-if="canAccessForm"
                    v-model="form"
                    :medecins="medecins"
                    :infirmiers="infirmiers"
                    :salles="salles"
                    :ordonnances="[]"
                    :saving="saving"
                    :cloture-loading="clotureLoading"
                    :medecin-readonly="true"
                    :hide-ordonnances="true"
                    @save="handleSave"
                    @cloture="handleCloture"
                />
            </template>
        </div>
    </Dialog>
</template>
