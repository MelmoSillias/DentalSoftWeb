<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { usePageMeta } from '../composables/usePageMeta';

const props = defineProps({
    dark: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle-theme']);
const router = useRouter();
const { title, breadcrumbItems } = usePageMeta();

const home = computed(() => ({ icon: 'pi pi-home', route: '/dashboard' }));

function openProfile() {
    router.push('/profil');
}
</script>

<template>
    <header class="top-header">
        <div class="title-block">
            <h1 class="page-title">{{ title }}</h1>
            <PvBreadcrumb :home="home" :model="breadcrumbItems" />
        </div>

        <div class="actions">
            <PvButton
                text
                rounded
                :icon="props.dark ? 'pi pi-moon' : 'pi pi-sun'"
                aria-label="Toggle theme"
                @click="emit('toggle-theme')"
            />
            <button class="avatar-trigger" type="button" @click="openProfile" aria-label="Aller au profil">
                <PvAvatar shape="circle" icon="pi pi-user" size="normal" />
            </button>
        </div>
    </header>
</template>

<style scoped>
.top-header {
    position: sticky;
    top: 0;
    z-index: 20;
    backdrop-filter: blur(10px);
    background: color-mix(in srgb, var(--p-surface-0), transparent 12%);
    border-bottom: 1px solid var(--p-surface-200);
    padding: 0.75rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}

.title-block {
    min-width: 0;
}

:deep(.p-breadcrumb) {
    padding: 0.2rem 0;
    border: 0;
    background: transparent;
}

:deep(.p-breadcrumb-item-link) {
    font-size: 0.8rem;
}

.actions {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.avatar-trigger {
    border: 0;
    background: transparent;
    padding: 0;
    cursor: pointer;
}
</style>
