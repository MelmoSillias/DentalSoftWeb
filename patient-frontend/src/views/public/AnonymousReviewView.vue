<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { usePortalSettingsStore } from '../../stores/portalSettings';
import { submitAnonymousAppreciation } from '../../services/patientPortal';

const portalSettings = usePortalSettingsStore();
const showcaseUrl = computed(() => portalSettings.data.cabinetShowcaseWebsiteUrl || '');
const loading = ref(false);
const success = ref(false);
const publicPageUrl = ref('');

const publicQrSrc = computed(() => {
    if (!publicPageUrl.value) return '';
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(publicPageUrl.value)}`;
});

const form = reactive({
    rating: 5,
    comment: '',
    authorName: '',
    authorEmail: ''
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

    loading.value = true;
    success.value = false;
    try {
        await submitAnonymousAppreciation({
            rating: Number(form.rating || 5),
            comment: String(form.comment || '').trim(),
            authorName: String(form.authorName || '').trim() || null,
            authorEmail: String(form.authorEmail || '').trim() || null
        });
        success.value = true;
        form.comment = '';
    } finally {
        loading.value = false;
    }
}

function openShowcase() {
    if (!showcaseUrl.value) return;
    window.open(showcaseUrl.value, '_blank', 'noopener,noreferrer');
}

function copyPublicUrl() {
    if (!publicPageUrl.value) return;
    navigator.clipboard.writeText(publicPageUrl.value);
}
</script>

<template>
    <main class="public-page">
        <Card class="public-card">
            <template #title>Avis anonyme</template>
            <template #content>
                <p class="muted">Partagez votre expérience en quelques secondes. Aucun compte requis.</p>

                <div class="stars-row mt-3">
                    <button
                        v-for="star in 5"
                        :key="`public-star-${star}`"
                        type="button"
                        class="star-btn"
                        :class="{ active: star <= form.rating }"
                        @click="setRating(star)"
                    >
                        <i class="pi pi-star-fill"></i>
                    </button>
                </div>

                <div class="field-stack mt-3">
                    <InputText v-model="form.authorName" placeholder="Nom (facultatif)" />
                    <InputText v-model="form.authorEmail" placeholder="Email (facultatif)" />
                    <textarea
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

                <Message v-if="success" severity="success" :closable="false" class="mt-3">
                    Merci, votre avis a bien été enregistré. Il sera publié après validation par le cabinet.
                </Message>

                <Divider />
                <p class="m-0 text-sm font-medium">QR code de la page d'avis anonyme</p>
                <p class="muted text-sm">Partagez ce QR pour collecter des avis anonymes en salle d'attente.</p>
                <div class="qr-block">
                    <img v-if="publicQrSrc" :src="publicQrSrc" alt="QR avis anonyme" class="public-qr" />
                    <Button text icon="pi pi-copy" label="Copier le lien" @click="copyPublicUrl" />
                </div>

                <Button
                    v-if="showcaseUrl"
                    class="mt-3"
                    text
                    icon="pi pi-globe"
                    label="Visiter le site du cabinet"
                    @click="openShowcase"
                />
            </template>
        </Card>
    </main>
</template>

<style scoped>
.public-page {
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: 1rem;
}

.public-card {
    width: min(100%, 620px);
}

.stars-row {
    display: flex;
    gap: 0.35rem;
}

.star-btn {
    border: none;
    background: transparent;
    color: #cbd5e1;
    cursor: pointer;
    font-size: 1.25rem;
}

.star-btn.active {
    color: #f59e0b;
}

.field-stack {
    display: grid;
    gap: 0.65rem;
}

.feedback-textarea {
    width: 100%;
    border: 1px solid var(--p-surface-300);
    border-radius: 0.7rem;
    padding: 0.65rem 0.75rem;
    font-family: inherit;
    resize: vertical;
    min-height: 90px;
}

.qr-block {
    display: grid;
    justify-items: start;
    gap: 0.6rem;
}

.public-qr {
    width: min(220px, 100%);
    border: 1px solid var(--p-surface-300);
    border-radius: 0.75rem;
    background: var(--p-surface-0);
    padding: 0.5rem;
}
</style>
