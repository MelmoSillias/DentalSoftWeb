<script setup>
import BrandMark from '../../components/BrandMark.vue';
import { computed, onMounted, reactive, ref } from 'vue';
import { usePortalSettingsStore } from '../../stores/portalSettings';
import { submitAnonymousAppreciation } from '../../services/patientPortal';

const portalSettings = usePortalSettingsStore();
const showcaseUrl = computed(
    () => portalSettings.data.cabinetShowcaseWebsiteUrl || '',
);
const loading = ref(false);
const success = ref(false);
const submitError = ref('');
const copied = ref(false);
const publicPageUrl = ref('');

const publicQrSrc = computed(() => {
    if (!publicPageUrl.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(publicPageUrl.value)}`;
});

const form = reactive({
    rating: 5,
    comment: '',
    authorName: '',
    authorEmail: '',
});

onMounted(async () => {
    await portalSettings.load();
    publicPageUrl.value = window.location.href;
});

function setRating(value) {
    form.rating = value;
}

async function submitReview() {
    if (!String(form.comment || '').trim()) {
        return;
    }

    submitError.value = '';
    loading.value = true;
    success.value = false;
    try {
        await submitAnonymousAppreciation({
            rating: Number(form.rating || 5),
            comment: String(form.comment || '').trim(),
            authorName: String(form.authorName || '').trim() || null,
            authorEmail: String(form.authorEmail || '').trim() || null,
        });
        success.value = true;
        form.comment = '';
    } catch {
        submitError.value =
            'Votre avis n’a pas pu être envoyé. Veuillez réessayer.';
    } finally {
        loading.value = false;
    }
}

function openShowcase() {
    if (!showcaseUrl.value) return;
    window.open(showcaseUrl.value, '_blank', 'noopener,noreferrer');
}

async function copyPublicUrl() {
    if (!publicPageUrl.value) return;
    try {
        await navigator.clipboard.writeText(publicPageUrl.value);
        copied.value = true;
    } catch {
        submitError.value =
            'Copie indisponible. Vous pouvez copier l’adresse depuis votre navigateur.';
    }
}
</script>

<template>
    <main id="main-content" class="public-page">
        <Card class="public-card">
            <template #content
                ><div class="public-heading">
                    <BrandMark /><span class="welcome-icon"
                        ><i class="pi pi-heart"
                    /></span>
                    <h1>Votre expérience compte.</h1>
                </div>
                <p class="muted">
                    Partagez votre expérience en quelques secondes. Aucun compte
                    requis.
                </p>

                <div class="stars-row mt-3">
                    <button
                        v-for="star in 5"
                        :key="`public-star-${star}`"
                        type="button"
                        class="star-btn"
                        :aria-label="`${star} sur 5`"
                        :aria-pressed="star <= form.rating"
                        :class="{ active: star <= form.rating }"
                        @click="setRating(star)"
                    >
                        <i class="pi pi-star-fill"></i>
                    </button>
                </div>

                <div class="field-stack mt-3">
                    <InputText
                        aria-label="Nom (facultatif)"
                        autocomplete="name"
                        v-model="form.authorName"
                        placeholder="Nom (facultatif)"
                    />
                    <InputText
                        aria-label="Email (facultatif)"
                        type="email"
                        autocomplete="email"
                        v-model="form.authorEmail"
                        placeholder="Email (facultatif)"
                    />
                    <textarea
                        aria-label="Votre avis"
                        v-model="form.comment"
                        rows="4"
                        class="feedback-textarea"
                        placeholder="Votre avis"
                    />
                </div>

                <Button
                    class="mt-3"
                    icon="pi pi-send"
                    label="Publier mon avis"
                    :loading="loading"
                    :disabled="!form.comment"
                    @click="submitReview"
                />

                <Message
                    v-if="submitError"
                    severity="error"
                    :closable="false"
                    class="mt-3"
                    >{{ submitError }}</Message
                >
                <Message
                    v-if="success"
                    severity="success"
                    :closable="false"
                    class="mt-3"
                >
                    Merci, votre avis a bien été enregistré. Il sera publié
                    après validation par le cabinet.
                </Message>

                <Divider />
                <p class="m-0 text-sm font-medium">
                    QR code de la page d'avis anonyme
                </p>
                <p class="muted text-sm">
                    Partagez ce QR pour collecter des avis anonymes en salle
                    d'attente.
                </p>
                <div class="qr-block">
                    <img
                        v-if="publicQrSrc"
                        :src="publicQrSrc"
                        alt="QR avis anonyme"
                        class="public-qr"
                    />
                    <Button
                        text
                        icon="pi pi-copy"
                        :label="copied ? 'Lien copié' : 'Copier le lien'"
                        @click="copyPublicUrl"
                    />
                </div>

                <Button
                    v-if="showcaseUrl"
                    class="mt-3"
                    text
                    icon="pi pi-globe"
                    label="Visiter le site du cabinet"
                    @click="openShowcase"
                />
                <RouterLink
                    to="/login"
                    class="hero-link public-notice"
                    ><i class="pi pi-arrow-left" />Retour à la
                    connexion</RouterLink
                ></template
            >
        </Card>
    </main>
</template>
