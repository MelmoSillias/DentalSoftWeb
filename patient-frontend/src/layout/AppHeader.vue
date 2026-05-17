<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { usePageMeta } from '../composables/usePageMeta'; 
import { usePortalSettingsStore } from '../stores/portalSettings';
import cabinetConfig from '../cabinetConfig';

const props = defineProps({
    dark: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle-theme']);
const router = useRouter();
const { title, breadcrumbItems } = usePageMeta();
const portalSettings = usePortalSettingsStore();

const home = computed(() => ({ icon: 'pi pi-home', route: '/dashboard' }));
const showcaseUrl = computed(() => portalSettings.data.cabinetShowcaseWebsiteUrl || '');

function openProfile() {
    router.push('/profil');
}

function openShowcase() {
    if (!showcaseUrl.value) return;
    window.open(showcaseUrl.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <header class="top-header">
        <div class="header-brand" @click="router.push('/dashboard')">
            <img
                :src="'/' + (cabinetConfig.logo || 'logo.png')"
                :alt="cabinetConfig.displayName"
                class="brand-logo"
                @error="$event.target.style.display = 'none'"
            />
            <span class="brand-name">{{ cabinetConfig.displayName }}</span>
        </div>

        <div class="title-block">
            <Breadcrumb :home="home" :model="breadcrumbItems" />
        </div>

        <div class="actions">
            <Button
                v-if="showcaseUrl"
                text
                rounded
                icon="pi pi-globe"
                aria-label="Site vitrine"
                class="header-btn"
                @click="openShowcase"
            />
            <Button
                text
                rounded
                :icon="props.dark ? 'pi pi-moon' : 'pi pi-sun'"
                aria-label="Toggle theme"
                class="header-btn"
                @click="emit('toggle-theme')"
            />
            <button class="avatar-trigger" type="button" @click="openProfile" aria-label="Aller au profil">
                <Avatar shape="circle" icon="pi pi-user" size="normal" class="avatar-chip" />
            </button>
        </div>
    </header>
</template>

<style scoped>
.top-header {
    position: sticky;
    top: 0;
    z-index: 20;
    background: var(--pp-gradient);
    padding: 0.8rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 2px 12px rgba(30,58,138,0.18);
}

.header-brand {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    cursor: pointer;
    flex-shrink: 0;
    min-width: 0;
}

.brand-logo {
    height: 2.1rem;
    width: auto;
    border-radius: 8px;
    object-fit: contain;
    background: rgb(255, 255, 255);
    padding: 0.15rem;
    flex-shrink: 0;
}

.brand-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: rgba(255,255,255,0.9);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 10rem;
}

.title-block {
    flex: 1;
    min-width: 0;
}

:deep(.p-breadcrumb) {
    padding: 0.15rem 0;
    border: 0;
    background: transparent;
}

:deep(.p-breadcrumb-item-link) {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.7) !important;
}

:deep(.p-breadcrumb-item-link:hover) {
    color: #fff !important;
}

:deep(.p-breadcrumb-separator) {
    color: rgba(255,255,255,0.5) !important;
}

.actions {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex-shrink: 0;
}

.header-btn {
    color: rgba(255,255,255,0.85) !important;
}

.header-btn:hover {
    color: #fff !important;
    background: rgba(255,255,255,0.12) !important;
}

.avatar-trigger {
    border: 0;
    padding: 0;
    cursor: pointer;
    background: transparent;
    display: flex;
    align-items: center;
}

.avatar-chip {
    background: rgba(255,255,255,0.22) !important;
    color: #fff !important;
    border: 2px solid rgba(255,255,255,0.4);
    transition: background 0.2s;
}

.avatar-chip:hover {
    background: rgba(255,255,255,0.32) !important;
}
</style>
